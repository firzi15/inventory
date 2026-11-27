<?php
/**
 * Model Transaksi Stok (Penerimaan, Pengeluaran, Mutasi, dan Serah Terima BAST)
 * Mendukung pergerakan multi-tahap: Pengepul -> Gudang Mitra -> Office -> Pembeli (POS)
 */

namespace App\Models;

use PDO;
use Exception;
use App\Core\Database;
use App\Services\AccurateApiService;
use App\Models\Pemasok;
use App\Models\Mitra;
use App\Models\MasterHarga;
use App\Models\MasterHargaPemasok;

class Transaksi {
    /**
     * Menghasilkan Nomor Surat Jalan Pengeluaran / DO Otomatis dan Berurutan (Continue)
     * Format: SJ-OUT-[KODEPMS/MITRA]-[YYMMDD]-[Urut 4 Digit]
     */
    public static function generateNextSuratJalanKeluar(?string $kodePemasokAtauMitra = null, ?string $tanggal = null): string {
        $db = Database::getConnection();
        $dateStr = $tanggal ? date('ymd', strtotime($tanggal)) : date('ymd');
        $cleanKode = !empty($kodePemasokAtauMitra) ? strtoupper(preg_replace('/[^A-Z0-9]/', '', $kodePemasokAtauMitra)) : '';

        $prefix = $cleanKode 
            ? sprintf('SJ-OUT-%s-%s-', $cleanKode, $dateStr)
            : sprintf('SJ-OUT-%s-', $dateStr);

        $stmt = $db->prepare("
            SELECT no_referensi_eksternal 
            FROM transaksi_stok 
            WHERE jenis_transaksi IN ('PENGELUARAN', 'PENGELUARAN_BATAL')
              AND (no_referensi_eksternal LIKE ? OR no_bast LIKE ?)
            ORDER BY LENGTH(no_referensi_eksternal) DESC, no_referensi_eksternal DESC 
            LIMIT 1
        ");
        $stmt->execute([$prefix . '%', $prefix . '%']);
        $lastRow = $stmt->fetch();

        $nextSeq = 1;
        if ($lastRow && !empty($lastRow['no_referensi_eksternal'])) {
            $parts = explode('-', $lastRow['no_referensi_eksternal']);
            $lastNum = (int)end($parts);
            if ($lastNum > 0) {
                $nextSeq = $lastNum + 1;
            }
        }

        return sprintf('%s%04d', $prefix, $nextSeq);
    }

    /**
     * Memproses Pengeluaran Barang (Outbound) berbasis Scan Barcode
     * Dikeluarkan dari stok pemasok di gudang ke mitra bisnis penerima,
     * sekaligus mencatat harga beli pemasok, harga jual mitra, dan margin keuntungan (profit/loss).
     */
    public static function processPengeluaran($pemasokIdOrMitraId, $mitraIdOrBarcodes, $barcodeList = [], string $noReferensi = '', string $keterangan = '', int $userId = 4, string $namaPenerima = '', ?int $terminHari = null, ?string $waktu = null): array {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // Deteksi parameter signature fleksibel (Pemasok -> Mitra atau Mitra saja)
            if (is_array($mitraIdOrBarcodes)) {
                $mitraId = (int)$pemasokIdOrMitraId;
                $barcodeList = $mitraIdOrBarcodes;
                $pemasokId = 0;
            } else {
                $pemasokId = (int)$pemasokIdOrMitraId;
                $mitraId = (int)$mitraIdOrBarcodes;
            }

            if (empty($barcodeList)) {
                throw new Exception("Daftar barcode kosong. Silakan scan minimal 1 kemasan.");
            }

            $mitra = Mitra::findById($mitraId);
            if (!$mitra) throw new Exception("Mitra tujuan pengeluaran tidak ditemukan");

            // Ambil nama user login pemroses
            $userStmt = $db->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $userRow = $userStmt->fetch();
            $namaPenyerah = $userRow['nama_lengkap'] ?? 'Petugas Gudang Outbound';

            $namaPenerima = trim($namaPenerima);
            if (empty($namaPenerima)) {
                $namaPenerima = $mitra['nama_mitra'] . ' (Mitra Bisnis)';
            }

            $totalKg = 0.0;
            $totalNilaiBeli = 0.0;
            $totalNilaiJual = 0.0;
            $totalProfit = 0.0;
            $verifiedLabels = [];
            $pemasokSet = [];
            $sudahDiscan = [];

            // 1. Validasi setiap barcode yang discan
            foreach ($barcodeList as $barcode) {
                $barcode = trim($barcode);
                if (empty($barcode)) continue;

                $label = Label::findByBarcode($barcode);
                if (!$label) {
                    throw new Exception("Barcode '{$barcode}' tidak terdaftar di sistem!");
                }

                if ($label['status'] !== 'AKTIF') {
                    throw new Exception("Barcode '{$barcode}' tidak dapat dikeluarkan karena berstatus '{$label['status']}'!");
                }

                // Satu dokumen boleh berisi box dari beberapa pemasok; asal dicatat per box (labels.pemasok_id)
                $pemasokLabel = (int)($label['pemasok_id'] ?? 0);
                if ($pemasokLabel > 0) $pemasokSet[$pemasokLabel] = true;
                if (!empty($label['id']) && isset($sudahDiscan[(int)$label['id']])) {
                    throw new Exception("Barcode '{$barcode}' discan lebih dari sekali dalam dokumen ini.");
                }
                $sudahDiscan[(int)$label['id']] = true;

                $netto = (float)$label['berat_netto'];
                $itemId = (int)$label['item_id'];

                // Dapatkan harga beli pemasok & harga jual ke mitra
                // HPP memakai biaya beli yang dibekukan saat penerimaan; master harga hanya cadangan untuk label lama
                $hargaPemasok = (float)($label['harga_beli_per_kg'] ?? 0);
                if ($hargaPemasok <= 0 && $pemasokLabel > 0) {
                    $hargaPemasok = MasterHargaPemasok::getHargaByPemasokAndItem($pemasokLabel, $itemId);
                }
                $hargaMitra = MasterHarga::getHargaByMitraAndItem($mitraId, $itemId);

                $subBeli = round($netto * $hargaPemasok, 2);
                $subJual = round($netto * $hargaMitra, 2);
                $profit = round($subJual - $subBeli, 2);

                $totalKg += $netto;
                $totalNilaiBeli += $subBeli;
                $totalNilaiJual += $subJual;
                $totalProfit += $profit;

                $label['calc_harga_pemasok'] = $hargaPemasok;
                $label['calc_harga_mitra']   = $hargaMitra;
                $label['calc_sub_beli']       = $subBeli;
                $label['calc_sub_jual']       = $subJual;
                $label['calc_profit']         = $profit;

                $verifiedLabels[] = $label;
            }

            $totalLabel = count($verifiedLabels);
            if ($totalLabel === 0) {
                throw new Exception("Tidak ada label valid untuk diproses.");
            }

            // Header menyimpan pemasok hanya bila semua box dari satu pemasok; campuran = NULL (rincian per box di detail)
            $pemasokId = count($pemasokSet) === 1 ? (int)array_key_first($pemasokSet) : 0;
            $pemasok = $pemasokId > 0 ? Pemasok::findById($pemasokId) : null;
            $kodeRefAsal = $pemasok['kode_pemasok'] ?? $mitra['kode_mitra'];

            // Jika no_referensi kosong, otomatis generate
            // Waktu pengeluaran pilihan user (sudah divalidasi WaktuTransaksi); kosong = sekarang. Nomor dokumen ikut tanggalnya.
            $waktu = $waktu ?: date('Y-m-d H:i:s');
            $stmtMasuk = $db->prepare("SELECT id_label, created_at FROM labels WHERE id = ?");
            foreach ($verifiedLabels as $vl) {
                $stmtMasuk->execute([(int)$vl['id']]);
                $m = $stmtMasuk->fetch(PDO::FETCH_ASSOC);
                if ($m && $m['created_at'] > $waktu) {
                    throw new Exception("Waktu keluar (" . date('d/m/Y H:i', strtotime($waktu)) . ") lebih awal dari waktu box {$m['id_label']} diterima (" . date('d/m/Y H:i', strtotime($m['created_at'])) . ").");
                }
            }

            if (empty($noReferensi)) {
                $noReferensi = self::generateNextSuratJalanKeluar($kodeRefAsal, $waktu);
            }

            // 2. Buat Nomor Transaksi Unik: TRX-OUT-[YYMMDD]-[Urut 4 digit]
            $datePrefix = 'TRX-OUT-' . date('ymd', strtotime($waktu)) . '-';
            $stmtSeq = $db->prepare("SELECT no_transaksi FROM transaksi_stok WHERE no_transaksi LIKE ? ORDER BY no_transaksi DESC LIMIT 1");
            $stmtSeq->execute([$datePrefix . '%']);
            $lastTrx = $stmtSeq->fetch();
            $nextTrxNum = 1;
            if ($lastTrx && !empty($lastTrx['no_transaksi'])) {
                $parts = explode('-', $lastTrx['no_transaksi']);
                $nextTrxNum = ((int)end($parts)) + 1;
            }
            $noTransaksi = sprintf('%s%04d', $datePrefix, $nextTrxNum);

            // 3. Simpan Header Transaksi Pengeluaran (Lengkap dari Pemasok ke Mitra & Total Profit)
            $stmtTrx = $db->prepare("
                INSERT INTO transaksi_stok (
                    no_transaksi, jenis_transaksi, alur_pergerakan, no_bast, pemasok_id, mitra_id, mitra_tujuan_id,
                    total_kg, total_label, total_nilai_beli, total_nilai_jual, total_profit,
                    user_id, nama_penyerah, nama_penerima, no_referensi_eksternal, keterangan, status_accurate, created_at
                ) VALUES (?, 'PENGELUARAN', 'GUDANG_KE_MITRA', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?)
            ");
            $stmtTrx->execute([
                $noTransaksi,
                $noReferensi,
                $pemasokId ?: null,
                $mitraId,
                $mitraId,
                $totalKg,
                $totalLabel,
                $totalNilaiBeli,
                $totalNilaiJual,
                $totalProfit,
                $userId,
                $namaPenyerah,
                $namaPenerima,
                $noReferensi,
                $keterangan,
                $waktu
            ]);
            $transaksiId = $db->lastInsertId();

            // Jatuh tempo nota per dokumen (termin dalam hari, dihitung dari tanggal nota)
            if ($terminHari !== null && $terminHari >= 0) {
                $db->prepare("UPDATE transaksi_stok SET termin_hari = ?, tanggal_jatuh_tempo = DATE_ADD(DATE(created_at), INTERVAL ? DAY) WHERE id = ?")
                   ->execute([$terminHari, $terminHari, $transaksiId]);
            }

            // 4. Update status setiap label menjadi 'KELUAR', kaitkan ke mitra penerima, dan simpan detail transaksi
            $stmtDetail = $db->prepare("
                INSERT INTO transaksi_stok_detail (
                    transaksi_id, label_id, berat_netto, harga_pemasok, harga_mitra, total_beli, total_jual, profit
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtUpdateLabel = $db->prepare("
                UPDATE labels 
                SET status = 'KELUAR', 
                    mitra_id = ?, 
                    harga_per_kg = ?, 
                    total_nilai = ?, 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");

            foreach ($verifiedLabels as $lbl) {
                $stmtDetail->execute([
                    $transaksiId,
                    $lbl['id'],
                    $lbl['berat_netto'],
                    $lbl['calc_harga_pemasok'],
                    $lbl['calc_harga_mitra'],
                    $lbl['calc_sub_beli'],
                    $lbl['calc_sub_jual'],
                    $lbl['calc_profit']
                ]);

                $stmtUpdateLabel->execute([
                    $mitraId,
                    $lbl['calc_harga_mitra'],
                    $lbl['calc_sub_jual'],
                    $lbl['id']
                ]);
            }

            // 5. Masukkan ke antrean sinkronisasi Accurate Online
            AccurateApiService::enqueuePengeluaran($transaksiId, $mitra, $verifiedLabels, $totalKg, $noReferensi);

            \App\Services\JurnalService::postPengeluaran((int)$transaksiId);

            $db->commit();

            return [
                'success'           => true,
                'transaksi_id'      => (int)$transaksiId,
                'no_transaksi'      => $noTransaksi,
                'no_surat_jalan'    => $noReferensi,
                'total_label'       => $totalLabel,
                'total_kg'          => $totalKg,
                'total_nilai_beli'  => $totalNilaiBeli,
                'total_nilai_jual'  => $totalNilaiJual,
                'total_profit'      => $totalProfit,
                'pemasok'           => $pemasok['nama_pemasok'] ?? (count($pemasokSet) > 1 ? 'Beberapa pemasok' : 'Gudang Utama'),
                'mitra'             => $mitra['nama_mitra'],
                'kode_mitra'        => $mitra['kode_mitra'],
                'tujuan'            => $namaPenerima,
                'next_surat_jalan'  => self::generateNextSuratJalanKeluar($kodeRefAsal)
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Nilai pengeluaran dikunci saat surat jalan keluar terbit (harga jual dan HPP dibekukan).
     * Perubahan Master Harga hanya berlaku untuk transaksi berikutnya, tidak menghitung ulang yang sudah terbit.
     */
    public static function recalculateOutboundNilai(int $transaksiId): bool {
        return false;
    }

    /**
     * Mengambil riwayat transaksi pengeluaran barang (Surat Jalan Outbound)
     * Menampilkan dari stok pemasok mana, dikeluarkan ke mitra mana, total kemasan, bobot, dan profit
     */
    public static function getHistoryPengeluaran(array $filters = [], int $limit = 20, int $offset = 0, string $orderBy = 't.id DESC'): array {
        $db = Database::getConnection();
        $where = ["t.jenis_transaksi IN ('PENGELUARAN','PENGELUARAN_BATAL')"];
        $params = [];

        if (!empty($filters['pemasok_id'])) {
            $where[] = "(t.pemasok_id = ? OR EXISTS (SELECT 1 FROM transaksi_stok_detail dx JOIN labels lx ON lx.id = dx.label_id WHERE dx.transaksi_id = t.id AND lx.pemasok_id = ?))";
            $params[] = (int)$filters['pemasok_id'];
            $params[] = (int)$filters['pemasok_id'];
        }

        if (!empty($filters['mitra_id'])) {
            $where[] = "t.mitra_id = ?";
            $params[] = (int)$filters['mitra_id'];
        }

        if (!empty($filters['start_date'])) {
            $where[] = "DATE(t.created_at) >= ?";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where[] = "DATE(t.created_at) <= ?";
            $params[] = $filters['end_date'];
        }

        if (!empty($filters['search'])) {
            $kw = '%' . trim($filters['search']) . '%';
            $where[] = "(t.no_referensi_eksternal LIKE ? OR t.no_bast LIKE ? OR t.no_transaksi LIKE ? OR t.nama_penerima LIKE ? OR t.keterangan LIKE ? OR p.nama_pemasok LIKE ? OR m.nama_mitra LIKE ?)";
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT 
                t.*,
                COALESCE(t.no_referensi_eksternal, t.no_bast, t.no_transaksi) AS no_surat_jalan_keluar,
                m.kode_mitra, m.nama_mitra, m.warna_identitas, m.kode_gudang_accurate,
                p.kode_pemasok, p.nama_pemasok,
                u.nama_lengkap AS operator_name
            FROM transaksi_stok t
            LEFT JOIN mitra m ON t.mitra_id = m.id
            LEFT JOIN pemasok p ON t.pemasok_id = p.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE {$whereClause}
            ORDER BY {$orderBy}
            LIMIT ? OFFSET ?
        ";

        $stmt = $db->prepare($sql);
        $idx = 1;
        foreach ($params as $p) {
            $stmt->bindValue($idx++, $p);
        }
        $stmt->bindValue($idx++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Menghitung total riwayat transaksi pengeluaran untuk pagination
     */
    /** WHERE untuk countHistoryPengeluaran/ringkasHistoryPengeluaran: [klausa, parameter]. */
    private static function whereHistoryPengeluaran(array $filters): array {
        $where = ["t.jenis_transaksi IN ('PENGELUARAN','PENGELUARAN_BATAL')"];
        $params = [];

        if (!empty($filters['pemasok_id'])) {
            $where[] = "(t.pemasok_id = ? OR EXISTS (SELECT 1 FROM transaksi_stok_detail dx JOIN labels lx ON lx.id = dx.label_id WHERE dx.transaksi_id = t.id AND lx.pemasok_id = ?))";
            $params[] = (int)$filters['pemasok_id'];
            $params[] = (int)$filters['pemasok_id'];
        }

        if (!empty($filters['mitra_id'])) {
            $where[] = "t.mitra_id = ?";
            $params[] = (int)$filters['mitra_id'];
        }

        if (!empty($filters['start_date'])) {
            $where[] = "DATE(t.created_at) >= ?";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where[] = "DATE(t.created_at) <= ?";
            $params[] = $filters['end_date'];
        }

        if (!empty($filters['search'])) {
            $kw = '%' . trim($filters['search']) . '%';
            $where[] = "(t.no_referensi_eksternal LIKE ? OR t.no_bast LIKE ? OR t.no_transaksi LIKE ? OR t.nama_penerima LIKE ? OR t.keterangan LIKE ? OR p.nama_pemasok LIKE ? OR m.nama_mitra LIKE ?)";
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        return [implode(' AND ', $where), $params];
    }

    public static function countHistoryPengeluaran(array $filters = []): int {
        $db = Database::getConnection();
        [$whereClause, $params] = self::whereHistoryPengeluaran($filters);
        $sql = "
            SELECT COUNT(*) 
            FROM transaksi_stok t
            LEFT JOIN mitra m ON t.mitra_id = m.id
            LEFT JOIN pemasok p ON t.pemasok_id = p.id
            WHERE {$whereClause}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Ringkasan untuk kartu Riwayat Pengeluaran: jumlah SJ, box, total netto, nominal jual, nilai beli.
     * Pengeluaran yang dibatalkan dan nota saldo awal (sebelum aplikasi dipakai) tidak dihitung.
     */
    public static function ringkasHistoryPengeluaran(array $filters = []): array {
        $db = Database::getConnection();
        [$whereClause, $params] = self::whereHistoryPengeluaran($filters);
        $stmt = $db->prepare("
            SELECT COUNT(*) AS sj, COALESCE(SUM(t.total_label), 0) AS box, COALESCE(SUM(t.total_kg), 0) AS netto,
                   COALESCE(SUM(t.total_nilai_jual), 0) AS nominal, COALESCE(SUM(t.total_nilai_beli), 0) AS nilai_beli
            FROM transaksi_stok t
            LEFT JOIN mitra m ON t.mitra_id = m.id
            LEFT JOIN pemasok p ON t.pemasok_id = p.id
            WHERE {$whereClause} AND t.jenis_transaksi = 'PENGELUARAN' AND t.alur_pergerakan <> 'SALDO_AWAL'
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Memproses Mutasi / Pergerakan Barang Multi-Tahap dengan Berita Acara Serah Terima (BAST)
     * Alur: Pengepul -> Gudang Mitra -> Office/Toko -> Pembeli (POS)
     */
    public static function processMutasi(array $data): array {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $alur           = $data['alur_pergerakan'] ?? 'PEMASOK_KE_MITRA';
            $barcodes       = (array)($data['barcodes'] ?? []);
            $namaPenyerah   = trim($data['nama_penyerah'] ?? '');
            $namaPenerima   = trim($data['nama_penerima'] ?? '');
            $userIdLogin    = (int)($data['user_id'] ?? 1);
            $pemasokId      = !empty($data['pemasok_id']) ? (int)$data['pemasok_id'] : null;
            $mitraAsalId    = !empty($data['mitra_asal_id']) ? (int)$data['mitra_asal_id'] : null;
            $mitraTujuanId  = !empty($data['mitra_tujuan_id']) ? (int)$data['mitra_tujuan_id'] : (!empty($data['mitra_id']) ? (int)$data['mitra_id'] : null);
            $mitraId        = $mitraTujuanId ?? $mitraAsalId;
            $noRef          = trim($data['no_referensi'] ?? '');
            $catatanBAST    = trim($data['catatan_serah_terima'] ?? '');

            $mitra = $mitraId ? Mitra::findById($mitraId) : null;

            if (empty($barcodes)) {
                throw new Exception("Daftar kemasan kosong. Silakan scan minimal 1 barcode.");
            }

            if (empty($namaPenyerah)) {
                throw new Exception("Nama pihak yang menyerahkan barang wajib diisi.");
            }

            if (empty($namaPenerima)) {
                throw new Exception("Nama pihak penerima serah terima wajib diisi.");
            }

            $totalKg = 0.0;
            $verifiedLabels = [];

            // 1. Validasi barcode
            foreach ($barcodes as $code) {
                $code = trim($code);
                if (empty($code)) continue;

                $lbl = Label::findByBarcode($code);
                if (!$lbl) throw new Exception("Barcode '{$code}' tidak terdaftar di sistem!");

                if ($lbl['status'] !== 'AKTIF') {
                    throw new Exception("Barcode '{$code}' berstatus '{$lbl['status']}', tidak dapat dipindahkan!");
                }

                // Validasi: Barcode belum ditimbang (0 KG) tidak boleh dipindahkan
                if ((float)($lbl['berat_netto'] ?? 0) <= 0) {
                    throw new Exception("Barcode '{$code}' belum memiliki berat timbangan (0 KG)! Silakan lakukan penimbangan barang terlebih dahulu sebelum dipindahkan.");
                }

                // Barcode yang sudah dipindahkan tidak dapat dipindahkan kembali
                if (!empty($lbl['last_mutasi'])) {
                    $bastNo = $lbl['last_mutasi']['no_bast'] ?? $lbl['last_mutasi']['no_transaksi'];
                    $mitraTujuan = $lbl['last_mutasi']['nama_mitra_tujuan'] ?? ($lbl['nama_mitra'] ?? 'Mitra Lain');
                    throw new Exception("Barcode '{$code}' sudah pernah dipindahkan ke {$mitraTujuan} dengan dokumen {$bastNo}. Tidak dapat dipindahkan lagi!");
                }

                if ($alur === 'PEMASOK_KE_MITRA' && !empty($lbl['mitra_id'])) {
                    $namaMtr = $lbl['nama_mitra'] ?? 'Mitra';
                    throw new Exception("Barcode '{$code}' sudah dialokasikan ke {$namaMtr}, tidak dapat dipindahkan dari pemasok lagi!");
                }

                // Jika transfer antar mitra, cek kepemilikan awal
                if ($alur === 'MUTASI_ANTAR_MITRA' && $mitraAsalId && !empty($lbl['mitra_id']) && $lbl['mitra_id'] != $mitraAsalId) {
                    throw new Exception("Barcode '{$code}' bukan milik mitra asal yang dipilih!");
                }

                $totalKg += (float)$lbl['berat_netto'];
                $verifiedLabels[] = $lbl;
            }

            $totalLabel = count($verifiedLabels);
            if ($totalLabel === 0) throw new Exception("Tidak ada label valid.");

            // 2. Generate Nomor Transaksi & Nomor BAST
            $prefixTrx = match($alur) {
                'KE_PEMBELI_POS'    => 'TRX-POS-' . date('ymd') . '-',
                'PEMASOK_KE_MITRA'  => 'TRX-ALOK-' . date('ymd') . '-',
                'MUTASI_ANTAR_MITRA'=> 'TRX-TRF-' . date('ymd') . '-',
                default             => 'TRX-MUT-' . date('ymd') . '-'
            };

            $stmtSeq = $db->prepare("SELECT no_transaksi FROM transaksi_stok WHERE no_transaksi LIKE ? ORDER BY no_transaksi DESC LIMIT 1");
            $stmtSeq->execute([$prefixTrx . '%']);
            $lastRow = $stmtSeq->fetch();
            $nextTrxNum = 1;
            if ($lastRow && !empty($lastRow['no_transaksi'])) {
                $parts = explode('-', $lastRow['no_transaksi']);
                $nextTrxNum = ((int)end($parts)) + 1;
            }
            $noTransaksi = sprintf('%s%04d', $prefixTrx, $nextTrxNum);

            // BAST Number: BAST-[YYMMDD]-[Urut 4 digit]
            $prefixBast = 'BAST-' . date('ymd') . '-';
            $stmtBast = $db->prepare("SELECT no_bast FROM transaksi_stok WHERE no_bast LIKE ? ORDER BY no_bast DESC LIMIT 1");
            $stmtBast->execute([$prefixBast . '%']);
            $lastBast = $stmtBast->fetch();
            $nextBastNum = 1;
            if ($lastBast && !empty($lastBast['no_bast'])) {
                $parts = explode('-', $lastBast['no_bast']);
                $nextBastNum = ((int)end($parts)) + 1;
            }
            $noBast = sprintf('%s%04d', $prefixBast, $nextBastNum);

            // Tentukan jenis transaksi
            $jenisTrx = match($alur) {
                'KE_PEMBELI_POS' => 'PENGELUARAN',
                default          => 'MUTASI'
            };

            // 3. Simpan Header Transaksi
            $sqlHeader = "
                INSERT INTO transaksi_stok (
                    no_transaksi, jenis_transaksi, alur_pergerakan, no_bast, mitra_id,
                    pemasok_id, mitra_asal_id, mitra_tujuan_id,
                    total_kg, total_label, user_id, nama_penyerah, nama_penerima, user_penerima_id,
                    lokasi_asal_id, lokasi_tujuan_id, no_referensi_eksternal, keterangan, catatan_serah_terima,
                    status_accurate
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, 'PENDING')
            ";
            $stmtH = $db->prepare($sqlHeader);
            $stmtH->execute([
                $noTransaksi,
                $jenisTrx,
                $alur,
                $noBast,
                $mitraId,
                $pemasokId,
                $mitraAsalId,
                $mitraTujuanId,
                $totalKg,
                $totalLabel,
                $userIdLogin,
                $namaPenyerah,
                $namaPenerima,
                $userIdLogin,
                $noRef,
                $data['keterangan'] ?? ('Pemindahan Stok: ' . $alur),
                $catatanBAST
            ]);
            $transaksiId = $db->lastInsertId();

            // 4. Update status dan kepemilikan mitra pada label
            $newStatus = ($alur === 'KE_PEMBELI_POS') ? 'KELUAR' : 'AKTIF';
            $newMitraId = ($alur === 'KE_PEMBELI_POS') ? null : $mitraTujuanId;

            $stmtUpLbl = $db->prepare("
                UPDATE labels 
                SET status = ?, 
                    mitra_id = COALESCE(?, mitra_id), 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmtDetail = $db->prepare("INSERT INTO transaksi_stok_detail (transaksi_id, label_id, berat_netto) VALUES (?, ?, ?)");

            foreach ($verifiedLabels as $lbl) {
                $stmtDetail->execute([$transaksiId, $lbl['id'], $lbl['berat_netto']]);
                $stmtUpLbl->execute([$newStatus, $newMitraId, $lbl['id']]);
            }

            // 5. Enqueue ke API Accurate Online sesuai alur
            if ($alur === 'KE_PEMBELI_POS') {
                if ($mitra) AccurateApiService::enqueuePOS($transaksiId, $mitra, $verifiedLabels, $totalKg, $noRef, $namaPenerima);
            } elseif ($alur === 'MUTASI_ANTAR_MITRA') {
                if ($mitra) AccurateApiService::enqueueItemTransfer($transaksiId, $mitra, $verifiedLabels, $totalKg, $noRef);
            } else {
                if ($mitra) AccurateApiService::enqueuePengeluaran($transaksiId, $mitra, $verifiedLabels, $totalKg, $noRef);
            }

            $db->commit();

            return [
                'success'      => true,
                'transaksi_id' => $transaksiId,
                'no_transaksi' => $noTransaksi,
                'no_bast'      => $noBast,
                'total_label'  => $totalLabel,
                'total_kg'     => $totalKg,
                'alur'         => $alur,
                'mitra'        => $mitra['nama_mitra'] ?? '-'
            ];
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Mengambil detail lengkap satu transaksi untuk cetak dokumen BAST
     */
    public static function getByIdWithDetails(int $id): ?array {
        $db = Database::getConnection();
        $sql = "
            SELECT 
                t.*,
                m.kode_mitra, m.nama_mitra, m.warna_identitas, m.kode_gudang_accurate, m.pic_nama AS pic_mitra,
                ma.kode_mitra AS kode_mitra_asal, ma.nama_mitra AS nama_mitra_asal,
                mt.kode_mitra AS kode_mitra_tujuan, mt.nama_mitra AS nama_mitra_tujuan,
                p.kode_pemasok, p.nama_pemasok,
                u.nama_lengkap AS operator_pemroses, u.role AS role_operator,
                COALESCE(ma.nama_mitra, p.nama_pemasok, 'Gudang Asal') AS nama_lokasi_asal,
                COALESCE(mt.nama_mitra, m.nama_mitra, 'Gudang Tujuan') AS nama_lokasi_tujuan
            FROM transaksi_stok t
            LEFT JOIN mitra m ON t.mitra_id = m.id
            LEFT JOIN mitra ma ON t.mitra_asal_id = ma.id
            LEFT JOIN mitra mt ON t.mitra_tujuan_id = mt.id
            LEFT JOIN pemasok p ON t.pemasok_id = p.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE t.id = ?
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        $header = $stmt->fetch();
        if (!$header) return null;

        // Ambil item kemasan
        $sqlDetail = "
            SELECT 
                d.berat_netto,
                d.harga_pemasok,
                d.harga_mitra,
                d.total_beli,
                d.total_jual,
                d.profit,
                l.id_label, l.batch_no, l.no_surat_jalan, l.berat_bruto, l.berat_tara, l.tgl_produksi,
                i.kode_item, i.nama_item,
                j.nama_jenis,
                lp.nama_pemasok, lp.kode_pemasok
            FROM transaksi_stok_detail d
            JOIN labels l ON d.label_id = l.id
            JOIN items i ON l.item_id = i.id
            JOIN jenis_barang j ON i.jenis_id = j.id
            LEFT JOIN pemasok lp ON l.pemasok_id = lp.id
            WHERE d.transaksi_id = ?
            ORDER BY l.id_label ASC
        ";
        $stmtD = $db->prepare($sqlDetail);
        $stmtD->execute([$id]);
        $header['items'] = $stmtD->fetchAll();

        return $header;
    }

    /**
     * Mengambil daftar riwayat transaksi terbaru
     */
    public static function getRecent(int $limit = 10): array {
        $db = Database::getConnection();
        $sql = "
            SELECT 
                t.*,
                m.kode_mitra, m.nama_mitra, m.warna_identitas,
                u.nama_lengkap AS nama_operator
            FROM transaksi_stok t
            JOIN mitra m ON t.mitra_id = m.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE t.jenis_transaksi <> 'PENGELUARAN_BATAL'
            ORDER BY t.id DESC
            LIMIT ?
        ";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
