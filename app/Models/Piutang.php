<?php
/**
 * Model Pengelolaan Piutang Usaha (AR Aging) & Alokasi Setoran Parsial
 * Menangani pelacakan piutang per nota, pembagian cicilan ("netes"),
 * kalkulasi umur piutang (Aging Buckets), dan pencatatan buku kas masuk.
 */

namespace App\Models;

use App\Core\Database;
use PDO;
use Exception;

class Piutang {

    /**
     * Mengambil Ringkasan KPI Piutang & Setoran Keseluruhan
     */
    public static function getSummaryKpi(int $mitraId = 0): array {
        $pdo = Database::getConnection();

        // 1. Total Belanja / Piutang Terbit dari seluruh transaksi pengeluaran
        $stmtBeli = $pdo->prepare("
            SELECT 
                COUNT(id) as total_nota,
                COALESCE(SUM(total_nilai_jual), 0) as total_piutang_terbit
            FROM transaksi_stok
            WHERE jenis_transaksi = 'PENGELUARAN' AND (:mid = 0 OR mitra_id = :mid2)
        ");
        $stmtBeli->execute([':mid' => $mitraId, ':mid2' => $mitraId]);
        $resBeli = $stmtBeli->fetch(PDO::FETCH_ASSOC);

        // 2. Total Setoran Uang Masuk yang telah dibukukan
        $stmtSetor = $pdo->prepare("
            SELECT 
                COALESCE(SUM(jumlah_bayar), 0) as total_setoran_masuk,
                COUNT(CASE WHEN status = 'AKTIF' THEN 1 END) as total_transaksi_setor
            FROM ar_pembayaran
            WHERE (:mid = 0 OR mitra_id = :mid2)
        ");
        $stmtSetor->execute([':mid' => $mitraId, ':mid2' => $mitraId]);
        $resSetor = $stmtSetor->fetch(PDO::FETCH_ASSOC);

        $totalPiutangTerbit = (float)$resBeli['total_piutang_terbit'];
        $totalSetoranMasuk  = (float)$resSetor['total_setoran_masuk'];
        $sisaPiutangAktif   = max(0, round($totalPiutangTerbit - $totalSetoranMasuk, 2));

        // 3. Klasifikasi Umur Piutang yang masih belum lunas
        $notasList = self::getNotaList(['status' => 'BELUM_LUNAS', 'mitra_id' => $mitraId]);
        
        $ag = \App\Services\AgingService::kosong();

        foreach ($notasList as $nota) {
            $sisa = (float)$nota['sisa_piutang'];
            if ($sisa <= 0) continue;
            \App\Services\AgingService::tambah($ag, (int)$nota['hari_telat'], $sisa);
        }

        return [
            'total_nota'           => (int)$resBeli['total_nota'],
            'total_piutang_terbit' => $totalPiutangTerbit,
            'total_setoran_masuk'  => $totalSetoranMasuk,
            'sisa_piutang_aktif'   => $sisaPiutangAktif,
            'total_transaksi_bayar'=> (int)$resSetor['total_transaksi_setor'],
            'aging_belum'          => $ag['aging_belum'],
            'aging_1_7'            => $ag['aging_1_7'],
            'aging_8_14'           => $ag['aging_8_14'],
            'aging_15_30'          => $ag['aging_15_30'],
            'aging_gt_30'          => $ag['aging_gt_30'],
            'total_overdue'        => \App\Services\AgingService::totalTelat($ag)
        ];
    }

    /**
     * Mengambil Matriks Ringkasan Umur Piutang per Mitra (7 Mitra)
     */
    public static function getAgingSummaryPerMitra(int $mitraId = 0): array {
        $pdo = Database::getConnection();

        // Ambil seluruh mitra bisnis aktif
        $stmtMitra = $pdo->query("
            SELECT id, kode_mitra, nama_mitra, pic_nama, pic_telepon
            FROM mitra" . ($mitraId > 0 ? " WHERE id = " . (int)$mitraId : "") . "
            ORDER BY id ASC
        ");
        $mitraList = $stmtMitra->fetchAll(PDO::FETCH_ASSOC);

        // Ambil seluruh nota belum lunas untuk kalkulasi aging per mitra
        $unpaidNotas = self::getNotaList(['status' => 'BELUM_LUNAS', 'mitra_id' => $mitraId]);
        
        $mitraAgingMap = [];
        foreach ($mitraList as $m) {
            $mId = (int)$m['id'];
            $mitraAgingMap[$mId] = [
                'mitra_id'      => $mId,
                'kode_mitra'    => $m['kode_mitra'],
                'nama_mitra'    => $m['nama_mitra'],
                'pic_mitra'     => $m['pic_nama'] ?? '',
                'telepon_mitra' => $m['pic_telepon'] ?? '',
                'total_nota'    => 0,
                'total_belanja' => 0.0,
                'total_setoran' => 0.0,
                'sisa_piutang'  => 0.0,
                'aging_belum'   => 0.0,
                'aging_1_7'     => 0.0,
                'aging_8_14'    => 0.0,
                'aging_15_30'   => 0.0,
                'aging_gt_30'   => 0.0,
                'nota_terbuka'  => 0
            ];
        }

        // Ambil agregat belanja per mitra
        $stmtBelanja = $pdo->query("
            SELECT 
                mitra_id,
                COUNT(id) as jml_nota,
                COALESCE(SUM(total_nilai_jual), 0) as total_belanja
            FROM transaksi_stok
            WHERE jenis_transaksi = 'PENGELUARAN'
            GROUP BY mitra_id
        ");
        while ($row = $stmtBelanja->fetch(PDO::FETCH_ASSOC)) {
            $mId = (int)$row['mitra_id'];
            if (isset($mitraAgingMap[$mId])) {
                $mitraAgingMap[$mId]['total_nota']    = (int)$row['jml_nota'];
                $mitraAgingMap[$mId]['total_belanja'] = (float)$row['total_belanja'];
            }
        }

        // Ambil agregat setoran per mitra
        $stmtSetoran = $pdo->query("
            SELECT 
                mitra_id,
                COALESCE(SUM(jumlah_bayar), 0) as total_setoran
            FROM ar_pembayaran
            GROUP BY mitra_id
        ");
        while ($row = $stmtSetoran->fetch(PDO::FETCH_ASSOC)) {
            $mId = (int)$row['mitra_id'];
            if (isset($mitraAgingMap[$mId])) {
                $mitraAgingMap[$mId]['total_setoran'] = (float)$row['total_setoran'];
            }
        }

        // Hitung sisa piutang dan petakan nota belum lunas ke bucket aging
        foreach ($unpaidNotas as $nota) {
            $mId = (int)$nota['mitra_id'];
            if (!isset($mitraAgingMap[$mId])) continue;

            $sisa = (float)$nota['sisa_piutang'];
            if ($sisa <= 0) continue;

            $mitraAgingMap[$mId]['nota_terbuka']++;
            \App\Services\AgingService::tambah($mitraAgingMap[$mId], (int)$nota['hari_telat'], $sisa);
        }

        // Hitung ulang total sisa piutang
        foreach ($mitraAgingMap as &$row) {
            $row['sisa_piutang'] = max(0, round($row['total_belanja'] - $row['total_setoran'], 2));
        }
        unset($row);

        return array_values($mitraAgingMap);
    }

    /**
     * Mengambil Daftar Nota Pengeluaran beserta Status Bayar & Umur Piutang
     */
    public static function getNotaList(array $filters = []): array {
        $pdo = Database::getConnection();

        $where = ["ts.jenis_transaksi = 'PENGELUARAN'"];
        $params = [];

        if (!empty($filters['mitra_id'])) {
            $where[] = "ts.mitra_id = :mitra_id";
            $params[':mitra_id'] = (int)$filters['mitra_id'];
        }

        if (!empty($filters['date_start'])) {
            $where[] = "DATE(ts.created_at) >= :date_start";
            $params[':date_start'] = $filters['date_start'];
        }

        if (!empty($filters['date_end'])) {
            $where[] = "DATE(ts.created_at) <= :date_end";
            $params[':date_end'] = $filters['date_end'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(ts.no_transaksi LIKE :search OR ts.no_referensi_eksternal LIKE :search OR ts.no_bast LIKE :search OR m.nama_mitra LIKE :search)";
            $params[':search'] = '%' . trim($filters['search']) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT 
                ts.id,
                ts.no_transaksi,
                COALESCE(ts.no_referensi_eksternal, ts.no_bast, ts.no_transaksi) as no_surat_jalan,
                ts.mitra_id,
                ts.total_kg,
                ts.total_label,
                ts.total_nilai_jual,
                ts.created_at,
                m.kode_mitra,
                m.nama_mitra,
                m.pic_nama,
                m.pic_telepon,
                DATEDIFF(CURRENT_DATE, DATE(ts.created_at)) as umur_hari,
                ts.termin_hari,
                COALESCE(ts.tanggal_jatuh_tempo, DATE(ts.created_at)) as jatuh_tempo,
                COALESCE((
                    SELECT SUM(nominal_alokasi) 
                    FROM ar_alokasi_pembayaran 
                    WHERE transaksi_stok_id = ts.id
                ), 0) as total_bayar,
                (
                    SELECT COUNT(id)
                    FROM ar_alokasi_pembayaran
                    WHERE transaksi_stok_id = ts.id AND nominal_alokasi > 0
                ) as jml_kali_setor
            FROM transaksi_stok ts
            JOIN mitra m ON ts.mitra_id = m.id
            WHERE {$whereSql}
            ORDER BY ts.created_at DESC, ts.id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        $filterStatus = $filters['status'] ?? 'SEMUA';

        foreach ($rows as $r) {
            $totalJual = (float)$r['total_nilai_jual'];
            $totalBayar = (float)$r['total_bayar'];
            $sisa = max(0, round($totalJual - $totalBayar, 2));

            if ($sisa <= 0) {
                $statusBayar = 'LUNAS';
            } elseif ($totalBayar > 0) {
                $statusBayar = 'PARSIAL';
            } else {
                $statusBayar = 'BELUM_BAYAR';
            }

            // Filter status bayar
            if ($filterStatus === 'BELUM_LUNAS' && $statusBayar === 'LUNAS') {
                continue;
            } elseif (in_array($filterStatus, ['LUNAS', 'PARSIAL', 'BELUM_BAYAR']) && $statusBayar !== $filterStatus) {
                continue;
            }

            $r['total_nilai_jual'] = $totalJual;
            $r['total_bayar']      = $totalBayar;
            $r['sisa_piutang']      = $sisa;
            $r['status_bayar']     = $statusBayar;
            $r['umur_hari']        = (int)$r['umur_hari'];
            $r['hari_telat']       = \App\Services\AgingService::hariTelat($r['jatuh_tempo']);
            $r['jml_kali_setor']   = (int)$r['jml_kali_setor'];

            $result[] = $r;
        }

        return $result;
    }

    /**
     * Mengambil Daftar Nota Belum Lunas Milik Satu Mitra (Urutan FIFO: Tertua Lebih Dahulu)
     */
    public static function getUnpaidNotasByMitra(int $mitraId): array {
        $pdo = Database::getConnection();

        $sql = "
            SELECT 
                ts.id,
                ts.no_transaksi,
                COALESCE(ts.no_referensi_eksternal, ts.no_bast, ts.no_transaksi) as no_surat_jalan,
                ts.total_kg,
                ts.total_nilai_jual,
                ts.created_at,
                DATEDIFF(CURRENT_DATE, DATE(ts.created_at)) as umur_hari,
                COALESCE(ts.tanggal_jatuh_tempo, DATE(ts.created_at)) as jatuh_tempo,
                COALESCE((
                    SELECT SUM(nominal_alokasi) 
                    FROM ar_alokasi_pembayaran 
                    WHERE transaksi_stok_id = ts.id
                ), 0) as total_bayar,
                COALESCE((
                    SELECT SUM(a.nominal_alokasi)
                    FROM ar_alokasi_pembayaran a
                    JOIN jurnal j ON j.sumber_tipe = 'AR_TITIPAN' AND j.sumber_id = a.id AND j.status = 'POSTED'
                    WHERE a.transaksi_stok_id = ts.id
                ), 0) as bayar_titipan,
                (
                    SELECT GROUP_CONCAT(DISTINCT p.no_bukti_bayar ORDER BY p.id SEPARATOR ', ')
                    FROM ar_alokasi_pembayaran a
                    JOIN jurnal j ON j.sumber_tipe = 'AR_TITIPAN' AND j.sumber_id = a.id AND j.status = 'POSTED'
                    JOIN ar_pembayaran p ON p.id = a.pembayaran_id
                    WHERE a.transaksi_stok_id = ts.id AND a.nominal_alokasi > 0
                ) as titipan_dari
            FROM transaksi_stok ts
            WHERE ts.jenis_transaksi = 'PENGELUARAN'
              AND ts.mitra_id = :mitra_id
            ORDER BY ts.created_at ASC, ts.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':mitra_id' => $mitraId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $unpaid = [];
        foreach ($rows as $r) {
            $totalJual = (float)$r['total_nilai_jual'];
            $totalBayar = (float)$r['total_bayar'];
            $sisa = round($totalJual - $totalBayar, 2);

            if ($sisa > 0.01) {
                $r['total_nilai_jual'] = $totalJual;
                $r['total_bayar']      = $totalBayar;
                $r['sisa_piutang']      = $sisa;
                $r['umur_hari']        = (int)$r['umur_hari'];
                $r['hari_telat']       = \App\Services\AgingService::hariTelat($r['jatuh_tempo']);
                $r['status_bayar']     = ($totalBayar > 0) ? 'PARSIAL' : 'BELUM_BAYAR';
                $unpaid[] = $r;
            }
        }

        return $unpaid;
    }

    /**
     * Saldo titipan (uang muka) mitra: uang setoran aktif yang belum dialokasikan ke nota mana pun.
     * Dengan $mitraId: float. Tanpa $mitraId: [mitra_id => saldo] untuk mitra yang punya titipan.
     */
    public static function saldoTitipan(int $mitraId = 0) {
        $pdo = Database::getConnection();
        $sql = "SELECT p.mitra_id, SUM(p.jumlah_bayar - COALESCE(a.alok, 0)) AS saldo
                FROM ar_pembayaran p
                LEFT JOIN (SELECT pembayaran_id, SUM(nominal_alokasi) AS alok FROM ar_alokasi_pembayaran GROUP BY pembayaran_id) a ON a.pembayaran_id = p.id
                WHERE p.status = 'AKTIF'" . ($mitraId > 0 ? " AND p.mitra_id = " . (int)$mitraId : "") . "
                GROUP BY p.mitra_id HAVING saldo > 0.009";
        $peta = [];
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $peta[(int)$r['mitra_id']] = round((float)$r['saldo'], 2);
        }
        return $mitraId > 0 ? ($peta[$mitraId] ?? 0.0) : $peta;
    }

    /**
     * Riwayat titipan mitra (terbaru dulu): MASUK = lebih bayar dari setoran, KELUAR = dipotong ke nota.
     * Tiap baris: jenis, tanggal, no_bukti_bayar, no_surat_jalan (KELUAR), nominal.
     */
    public static function riwayatTitipan(int $mitraId, int $limit = 10): array {
        $pdo = Database::getConnection();
        $st = $pdo->prepare("
            SELECT * FROM (
                SELECT 'MASUK' AS jenis, p.tanggal_bayar AS tanggal, p.id AS urut, p.no_bukti_bayar, NULL AS no_surat_jalan,
                       p.jumlah_bayar - COALESCE((SELECT SUM(a.nominal_alokasi) FROM ar_alokasi_pembayaran a
                            WHERE a.pembayaran_id = p.id AND NOT EXISTS (SELECT 1 FROM jurnal j WHERE j.sumber_tipe = 'AR_TITIPAN' AND j.sumber_id = a.id)), 0) AS nominal
                FROM ar_pembayaran p WHERE p.mitra_id = ? AND p.status = 'AKTIF'
                UNION ALL
                SELECT 'KELUAR', j.tanggal, a.id, p.no_bukti_bayar, COALESCE(ts.no_referensi_eksternal, ts.no_bast, ts.no_transaksi), a.nominal_alokasi
                FROM ar_alokasi_pembayaran a
                JOIN jurnal j ON j.sumber_tipe = 'AR_TITIPAN' AND j.sumber_id = a.id AND j.status = 'POSTED'
                JOIN ar_pembayaran p ON p.id = a.pembayaran_id
                JOIN transaksi_stok ts ON ts.id = a.transaksi_stok_id
                WHERE p.mitra_id = ? AND a.nominal_alokasi > 0
            ) x WHERE nominal > 0.009
            ORDER BY tanggal DESC, jenis ASC, urut DESC
            LIMIT " . max(1, (int)$limit));
        $st->execute([$mitraId, $mitraId]);
        return array_map(function ($r) { $r['nominal'] = round((float)$r['nominal'], 2); return $r; }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Potong saldo titipan mitra ke nota yang belum lunas (titipan terlama -> nota terlama).
     * Tiap potongan = baris ar_alokasi_pembayaran biasa + jurnal AR_TITIPAN (D 2100 Uang Muka / K 1100 Piutang),
     * sumber_id jurnal = id baris alokasi. Dipanggil di dalam transaksi yang sedang berjalan.
     */
    public static function potongTitipan(int $mitraId, ?string $tanggal = null): array {
        $pdo = Database::getConnection();
        if ($mitraId <= 0 || self::saldoTitipan($mitraId) <= 0.009) return [];
        $tanggal = $tanggal ?: date('Y-m-d');

        $st = $pdo->prepare("
            SELECT p.id, p.no_bukti_bayar, p.tanggal_bayar,
                   p.jumlah_bayar - COALESCE((SELECT SUM(a.nominal_alokasi) FROM ar_alokasi_pembayaran a WHERE a.pembayaran_id = p.id), 0) AS sisa
            FROM ar_pembayaran p
            WHERE p.mitra_id = ? AND p.status = 'AKTIF'
            ORDER BY p.tanggal_bayar, p.id
        ");
        $st->execute([$mitraId]);
        $titipan = array_values(array_filter($st->fetchAll(PDO::FETCH_ASSOC), fn($t) => (float)$t['sisa'] > 0.009));
        if (!$titipan) return [];

        $ins = $pdo->prepare("INSERT INTO ar_alokasi_pembayaran (pembayaran_id, transaksi_stok_id, nominal_alokasi, sisa_piutang_setelah) VALUES (?, ?, ?, ?)");
        $hasil = [];
        $ti = 0;
        foreach (self::getUnpaidNotasByMitra($mitraId) as $n) {
            $sisaNota = (float)$n['sisa_piutang'];
            while ($sisaNota > 0.009 && $ti < count($titipan)) {
                $x = round(min($sisaNota, (float)$titipan[$ti]['sisa']), 2);
                $sisaNota = round($sisaNota - $x, 2);
                $titipan[$ti]['sisa'] = round((float)$titipan[$ti]['sisa'] - $x, 2);
                $ins->execute([(int)$titipan[$ti]['id'], (int)$n['id'], $x, $sisaNota]);
                $alokId = (int)$pdo->lastInsertId();
                // tanggal jurnal: tanggal nota, tapi tidak sebelum uang titipannya diterima
                \App\Services\JurnalService::post(max($tanggal, (string)$titipan[$ti]['tanggal_bayar']), 'AR_TITIPAN', $alokId, $titipan[$ti]['no_bukti_bayar'],
                    'Potong titipan ' . $titipan[$ti]['no_bukti_bayar'] . ' ke ' . $n['no_surat_jalan'],
                    [['2100', $x, 0], ['1100', 0, $x]]);
                $hasil[] = ['no_bukti_bayar' => $titipan[$ti]['no_bukti_bayar'], 'transaksi_stok_id' => (int)$n['id'], 'no_surat_jalan' => $n['no_surat_jalan'], 'nominal' => $x];
                if ((float)$titipan[$ti]['sisa'] <= 0.009) $ti++;
            }
            if ($ti >= count($titipan)) break;
        }
        return $hasil;
    }

    /**
     * Membukukan Setoran Kas/Bank & Mengalokasikan ke Nota Piutang (FIFO atau Manual)
     */
    public static function recordPayment(array $data, int $userId = 1): array {
        $pdo = Database::getConnection();

        $mitraId     = (int)($data['mitra_id'] ?? 0);
        $jumlahBayar = (float)($data['jumlah_bayar'] ?? 0);
        $tglBayar    = trim($data['tanggal_bayar'] ?? date('Y-m-d'));
        \App\Services\PeriodeService::pastikanTerbuka($tglBayar, 'mencatat setoran');
        $metodeBayar = strtoupper(trim($data['metode_bayar'] ?? 'TUNAI'));
        $bankTujuan  = trim($data['bank_tujuan'] ?? '');
        $keterangan  = trim($data['keterangan'] ?? '');
        $modeAlokasi = strtoupper(trim($data['mode_alokasi'] ?? 'FIFO'));

        // Centang "Pakai titipan": saldo titipan mitra dipakai dulu untuk nota terlama, baru uang setoran membayar sisanya
        $pakaiTitipan = !empty($data['pakai_titipan']);

        if ($mitraId <= 0) {
            throw new Exception("Mitra pembayar wajib dipilih.");
        }
        if ($jumlahBayar < 0 || ($jumlahBayar == 0 && !($pakaiTitipan && self::saldoTitipan($mitraId) > 0.009))) {
            throw new Exception("Jumlah uang setoran harus lebih besar dari 0.");
        }
        if (!in_array($metodeBayar, ['TUNAI', 'TRANSFER_BANK'])) {
            $metodeBayar = 'TUNAI';
        }

        // Hanya memakai titipan tanpa uang baru: tidak ada setoran yang dicatat, cukup potong titipan ke nota
        if ($jumlahBayar == 0) {
            $pdo->beginTransaction();
            try {
                $potong = self::potongTitipan($mitraId, $tglBayar);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            if (!$potong) throw new Exception("Tidak ada nota terutang yang bisa dipotong dari titipan.");
            return [
                'success' => true, 'pembayaran_id' => 0, 'no_bukti_bayar' => '-', 'jumlah_bayar' => 0,
                'allocated_count' => count(array_unique(array_column($potong, 'transaksi_stok_id'))), 'allocated_notas' => [],
                'titipan_dipakai' => round(array_sum(array_column($potong, 'nominal')), 2), 'sisa_deposit' => 0
            ];
        }

        // Setoran boleh melebihi piutang: kelebihannya menjadi saldo titipan mitra (akun 2100)

        // Generate No Bukti Bayar Unik: CR-YYYYMMDD-XXXX
        $dateCode = date('Ymd', strtotime($tglBayar));
        $countToday = $pdo->query("SELECT COUNT(id) FROM ar_pembayaran WHERE tanggal_bayar = '{$tglBayar}'")->fetchColumn();
        $nextSeq = str_pad((int)$countToday + 1, 4, '0', STR_PAD_LEFT);
        $noBukti = "CR-{$dateCode}-{$nextSeq}";

        // Cek jika duplikat
        $cekDup = $pdo->prepare("SELECT id FROM ar_pembayaran WHERE no_bukti_bayar = ?");
        $cekDup->execute([$noBukti]);
        if ($cekDup->fetch()) {
            $noBukti = "CR-{$dateCode}-" . strtoupper(substr(uniqid(), -4));
        }

        $pdo->beginTransaction();
        try {
            // 0. Titipan lama dipakai dulu (sebelum setoran baru dicatat, supaya setoran ini tidak ikut terhitung titipan)
            $potongTitipan = $pakaiTitipan ? self::potongTitipan($mitraId, $tglBayar) : [];

            // 1. Simpan Header Setoran ke `ar_pembayaran`
            $stmtInsert = $pdo->prepare("
                INSERT INTO ar_pembayaran 
                (no_bukti_bayar, mitra_id, tanggal_bayar, metode_bayar, bank_tujuan, jumlah_bayar, keterangan, user_id)
                VALUES 
                (:no_bukti, :mitra_id, :tgl, :metode, :bank, :jumlah, :ket, :uid)
            ");
            $stmtInsert->execute([
                ':no_bukti' => $noBukti,
                ':mitra_id' => $mitraId,
                ':tgl'      => $tglBayar,
                ':metode'   => $metodeBayar,
                ':bank'     => ($metodeBayar === 'TRANSFER_BANK' && !empty($bankTujuan)) ? $bankTujuan : null,
                ':jumlah'   => $jumlahBayar,
                ':ket'      => $keterangan ?: null,
                ':uid'      => $userId
            ]);
            $pembayaranId = (int)$pdo->lastInsertId();

            // 2. Alokasikan Uang Setoran ke Nota Piutang
            $allocatedNotas = [];
            $sisaUangSetoran = $jumlahBayar;

            if ($modeAlokasi === 'MANUAL' && !empty($data['alokasi_manual']) && is_array($data['alokasi_manual'])) {
                // ALOKASI MANUAL: Ditentukan spesifik per transaksi_stok_id
                $totalAlokasiReq = 0.0;
                foreach ($data['alokasi_manual'] as $trxId => $nom) {
                    $nom = (float)$nom;
                    if ($nom <= 0) continue;
                    $totalAlokasiReq += $nom;
                }

                if ($totalAlokasiReq > $jumlahBayar + 0.01) {
                    throw new Exception("Total alokasi manual (Rp " . number_format($totalAlokasiReq, 0, '.', ',') . ") melebihi jumlah uang yang disetor (Rp " . number_format($jumlahBayar, 0, '.', ',') . ").");
                }

                foreach ($data['alokasi_manual'] as $trxId => $nom) {
                    $nom = (float)$nom;
                    $trxId = (int)$trxId;
                    if ($nom <= 0 || $trxId <= 0) continue;

                    // Cek sisa piutang riil nota saat ini
                    $sisaReal = self::getSisaPiutangNota($trxId);
                    if ($sisaReal <= 0) continue;

                    $alokasiNominal = min($nom, $sisaReal);
                    $sisaSetelah = round($sisaReal - $alokasiNominal, 2);

                    $stmtAlok = $pdo->prepare("
                        INSERT INTO ar_alokasi_pembayaran 
                        (pembayaran_id, transaksi_stok_id, nominal_alokasi, sisa_piutang_setelah)
                        VALUES 
                        (:pid, :tid, :nom, :sisa)
                    ");
                    $stmtAlok->execute([
                        ':pid'  => $pembayaranId,
                        ':tid'  => $trxId,
                        ':nom'  => $alokasiNominal,
                        ':sisa' => $sisaSetelah
                    ]);

                    $allocatedNotas[] = [
                        'transaksi_stok_id' => $trxId,
                        'nominal_alokasi'   => $alokasiNominal,
                        'sisa_setelah'      => $sisaSetelah
                    ];
                }
            } else {
                // ALOKASI OTOMATIS (FIFO): Urutkan nota tertua lebih dahulu
                $unpaidNotas = self::getUnpaidNotasByMitra($mitraId);

                if (!empty($unpaidNotas)) {
                    foreach ($unpaidNotas as $nota) {
                        if ($sisaUangSetoran <= 0.001) break;

                        $sisaNota = (float)$nota['sisa_piutang'];
                        $potong = min($sisaUangSetoran, $sisaNota);
                        $sisaSetelah = round($sisaNota - $potong, 2);

                        $stmtAlok = $pdo->prepare("
                            INSERT INTO ar_alokasi_pembayaran 
                            (pembayaran_id, transaksi_stok_id, nominal_alokasi, sisa_piutang_setelah)
                            VALUES 
                            (:pid, :tid, :nom, :sisa)
                        ");
                        $stmtAlok->execute([
                            ':pid'  => $pembayaranId,
                            ':tid'  => (int)$nota['id'],
                            ':nom'  => $potong,
                            ':sisa' => $sisaSetelah
                        ]);

                        $allocatedNotas[] = [
                            'transaksi_stok_id' => (int)$nota['id'],
                            'no_surat_jalan'    => $nota['no_surat_jalan'],
                            'nominal_alokasi'   => $potong,
                            'sisa_setelah'      => $sisaSetelah
                        ];

                        $sisaUangSetoran -= $potong;
                    }
                }
            }

            // Sisa yang tidak teralokasi = saldo titipan
            $sisaUangSetoran = round($jumlahBayar - array_sum(array_column($allocatedNotas, 'nominal_alokasi')), 2);

            \App\Services\JurnalService::postArPayment((int)$pembayaranId);

            $pdo->commit();

            return [
                'success'           => true,
                'pembayaran_id'     => $pembayaranId,
                'no_bukti_bayar'    => $noBukti,
                'jumlah_bayar'      => $jumlahBayar,
                'allocated_count'   => count(array_unique(array_merge(array_column($allocatedNotas, 'transaksi_stok_id'), array_column($potongTitipan, 'transaksi_stok_id')))),
                'allocated_notas'   => $allocatedNotas,
                'titipan_dipakai'   => round(array_sum(array_column($potongTitipan, 'nominal')), 2),
                'sisa_deposit'      => max(0, round($sisaUangSetoran, 2))
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Setoran untuk beberapa nota satu mitra sekaligus (setor gelondongan dari daftar Payment In).
     * Nominal boleh kurang dari total: dibagi ke nota yang jatuh temponya paling lama lebih dahulu,
     * lalu dibukukan lewat recordPayment (alokasi manual), jadi aturan, jurnal, dan riwayatnya sama dengan setoran biasa.
     * $data: mitra_id, nota_ids[], jumlah_bayar, tanggal_bayar, metode_bayar, bank_tujuan, keterangan
     */
    public static function recordPaymentTerpilih(array $data, int $userId = 1): array {
        $mitraId = (int)($data['mitra_id'] ?? 0);
        $ids     = array_values(array_unique(array_filter(array_map('intval', (array)($data['nota_ids'] ?? [])))));
        $jumlah  = round((float)($data['jumlah_bayar'] ?? 0), 2);
        $tgl     = trim((string)($data['tanggal_bayar'] ?? date('Y-m-d')));

        if ($mitraId <= 0) throw new Exception("Pilih mitra terlebih dahulu.");
        if (!$ids) throw new Exception("Pilih minimal satu nota yang disetor.");
        if ($jumlah <= 0) throw new Exception("Nominal setoran harus lebih besar dari 0.");
        if (!\App\Core\Periode::sah($tgl)) throw new Exception("Tanggal setor tidak valid.");

        $notas = array_values(array_filter(
            self::getNotaList(['mitra_id' => $mitraId, 'status' => 'BELUM_LUNAS']),
            fn($n) => in_array((int)$n['id'], $ids, true)
        ));
        if (count($notas) !== count($ids)) {
            throw new Exception("Ada nota yang tidak ditemukan, sudah lunas, atau bukan milik mitra ini. Muat ulang halaman lalu coba lagi.");
        }

        // Jatuh tempo paling lama lebih dahulu
        usort($notas, fn($a, $b) => [$a['jatuh_tempo'], $a['created_at'], (int)$a['id']] <=> [$b['jatuh_tempo'], $b['created_at'], (int)$b['id']]);
        $totalSisa = round(array_sum(array_map(fn($n) => (float)$n['sisa_piutang'], $notas)), 2);
        if ($jumlah > $totalSisa + 0.01) {
            throw new Exception("Nominal (Rp " . number_format($jumlah, 0, '.', ',') . ") melebihi total sisa nota terpilih (Rp " . number_format($totalSisa, 0, '.', ',') . ").");
        }

        $alokasi = [];
        $sisaUang = $jumlah;
        foreach ($notas as $n) {
            if ($sisaUang <= 0.001) break;
            $potong = round(min($sisaUang, (float)$n['sisa_piutang']), 2);
            $alokasi[(int)$n['id']] = $potong;
            $sisaUang = round($sisaUang - $potong, 2);
        }

        return self::recordPayment([
            'mitra_id'       => $mitraId,
            'jumlah_bayar'   => $jumlah,
            'tanggal_bayar'  => $tgl,
            'metode_bayar'   => $data['metode_bayar'] ?? 'TUNAI',
            'bank_tujuan'    => $data['bank_tujuan'] ?? '',
            'keterangan'     => $data['keterangan'] ?? '',
            'mode_alokasi'   => 'MANUAL',
            'alokasi_manual' => $alokasi,
        ], $userId);
    }

    /**
     * Mengambil Sisa Piutang Riil Satu Nota Tertentu
     */
    public static function getSisaPiutangNota(int $transaksiStokId): float {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT 
                ts.total_nilai_jual,
                COALESCE((
                    SELECT SUM(nominal_alokasi) 
                    FROM ar_alokasi_pembayaran 
                    WHERE transaksi_stok_id = ts.id
                ), 0) as total_bayar
            FROM transaksi_stok ts
            WHERE ts.id = ?
        ");
        $stmt->execute([$transaksiStokId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return 0.0;
        return max(0, round((float)$row['total_nilai_jual'] - (float)$row['total_bayar'], 2));
    }

    /**
     * Mengambil Riwayat Pembayaran / Cicilan Lengkap untuk Satu Nota Tertentu
     */
    public static function getPaymentHistoryByNota(int $transaksiStokId): array {
        $pdo = Database::getConnection();

        $sql = "
            SELECT 
                ap.id as alokasi_id,
                ap.nominal_alokasi,
                ap.nominal_asal,
                p.status AS status_pembayaran,
                p.alasan_batal,
                p.jumlah_asal,
                ap.sisa_piutang_setelah,
                ap.created_at as tgl_alokasi,
                p.id as pembayaran_id,
                p.no_bukti_bayar,
                p.tanggal_bayar,
                p.metode_bayar,
                p.bank_tujuan,
                p.jumlah_bayar as total_setoran_induk,
                p.keterangan,
                u.nama_lengkap as nama_kasir
            FROM ar_alokasi_pembayaran ap
            JOIN ar_pembayaran p ON ap.pembayaran_id = p.id
            LEFT JOIN users u ON p.user_id = u.id
            WHERE ap.transaksi_stok_id = :trx_id
            ORDER BY p.tanggal_bayar ASC, ap.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':trx_id' => $transaksiStokId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil Riwayat Semua Setoran / Penerimaan Kas
     */
    public static function getAllPayments(array $filters = []): array {
        $pdo = Database::getConnection();

        $where = ["1=1"];
        $params = [];

        if (!empty($filters['mitra_id'])) {
            $where[] = "p.mitra_id = :mitra_id";
            $params[':mitra_id'] = (int)$filters['mitra_id'];
        }

        if (!empty($filters['date_start'])) {
            $where[] = "p.tanggal_bayar >= :date_start";
            $params[':date_start'] = $filters['date_start'];
        }

        if (!empty($filters['date_end'])) {
            $where[] = "p.tanggal_bayar <= :date_end";
            $params[':date_end'] = $filters['date_end'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(p.no_bukti_bayar LIKE :search OR m.nama_mitra LIKE :search OR p.keterangan LIKE :search)";
            $params[':search'] = '%' . trim($filters['search']) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT 
                p.id,
                p.no_bukti_bayar,
                p.mitra_id,
                p.tanggal_bayar,
                p.metode_bayar,
                p.bank_tujuan,
                p.jumlah_bayar,
                p.status,
                p.jumlah_asal,
                p.alasan_batal,
                p.keterangan,
                p.created_at,
                m.kode_mitra,
                m.nama_mitra,
                u.nama_lengkap as nama_kasir,
                (
                    SELECT COUNT(id) 
                    FROM ar_alokasi_pembayaran 
                    WHERE pembayaran_id = p.id
                ) as jml_nota_terpotong,
                (
                    SELECT GROUP_CONCAT(
                        COALESCE(ts.no_referensi_eksternal, ts.no_bast, ts.no_transaksi) 
                        SEPARATOR ', '
                    )
                    FROM ar_alokasi_pembayaran al
                    JOIN transaksi_stok ts ON al.transaksi_stok_id = ts.id
                    WHERE al.pembayaran_id = p.id
                ) as daftar_nota_terpotong
            FROM ar_pembayaran p
            JOIN mitra m ON p.mitra_id = m.id
            LEFT JOIN users u ON p.user_id = u.id
            WHERE {$whereSql}
            ORDER BY p.tanggal_bayar DESC, p.id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
