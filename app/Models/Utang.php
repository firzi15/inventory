<?php
/**
 * Model Pengelolaan Utang Usaha (AP Aging Pemasok) & Alokasi Pembayaran Parsial
 * Menangani pelacakan kewajiban tagihan atas penerimaan barang (Surat Jalan Masuk),
 * kalkulasi umur utang (Aging Buckets), cicilan bertahap, dan buku kas keluar pemasok.
 */

namespace App\Models;

use App\Core\Database;
use PDO;
use Exception;

class Utang {

    /**
     * Mengambil Ringkasan KPI Utang & Pembayaran Keseluruhan
     */
    public static function getSummaryKpi(): array {
        $pdo = Database::getConnection();

        // 1. Ambil seluruh Surat Jalan Penerimaan dari labels
        $sjList = self::getSuratJalanList();

        $totalSj = count($sjList);
        $totalUtangTerbit = 0.0;
        $totalBayarTeralokasi = 0.0;

        $ag = \App\Services\AgingService::kosong();

        foreach ($sjList as $sj) {
            $totalUtangTerbit += (float)$sj['total_nilai_beli'];
            $totalBayarTeralokasi += (float)$sj['total_bayar'];

            $sisa = (float)$sj['sisa_utang'];
            if ($sisa <= 0.01) continue;

            \App\Services\AgingService::tambah($ag, (int)$sj['hari_telat'], $sisa);
        }

        // 2. Ambil Total Kas Keluar dari ap_pembayaran
        $stmtBayar = $pdo->query("
            SELECT 
                COALESCE(SUM(jumlah_bayar), 0) as total_kas_keluar,
                COUNT(CASE WHEN status = 'AKTIF' THEN 1 END) as total_transaksi_bayar
            FROM ap_pembayaran
        ");
        $resBayar = $stmtBayar->fetch(PDO::FETCH_ASSOC);
        $totalPembayaranKeluar = (float)$resBayar['total_kas_keluar'];

        $sisaUtangAktif = max(0, round($totalUtangTerbit - $totalPembayaranKeluar, 2));

        return [
            'total_sj'              => $totalSj,
            'total_utang_terbit'    => $totalUtangTerbit,
            'total_pembayaran_keluar'=> $totalPembayaranKeluar,
            'sisa_utang_aktif'      => $sisaUtangAktif,
            'total_transaksi_bayar' => (int)$resBayar['total_transaksi_bayar'],
            'aging_belum'           => $ag['aging_belum'],
            'aging_1_7'             => $ag['aging_1_7'],
            'aging_8_14'            => $ag['aging_8_14'],
            'aging_15_30'           => $ag['aging_15_30'],
            'aging_gt_30'           => $ag['aging_gt_30'],
            'total_overdue'         => \App\Services\AgingService::totalTelat($ag)
        ];
    }

    /**
     * Mengambil Matriks Ringkasan Umur Utang per Pemasok (Aging Buckets)
     */
    public static function getAgingSummaryPerPemasok(): array {
        $pdo = Database::getConnection();

        // Ambil seluruh pemasok aktif
        $stmtPemasok = $pdo->query("
            SELECT id, kode_pemasok, nama_pemasok, pic_nama, telepon, alamat
            FROM pemasok
            WHERE is_active = 1
            ORDER BY id ASC
        ");
        $pemasokList = $stmtPemasok->fetchAll(PDO::FETCH_ASSOC);

        $pemasokMap = [];
        foreach ($pemasokList as $p) {
            $pId = (int)$p['id'];
            $pemasokMap[$pId] = [
                'pemasok_id'      => $pId,
                'kode_pemasok'    => $p['kode_pemasok'],
                'nama_pemasok'    => $p['nama_pemasok'],
                'pic_pemasok'     => $p['pic_nama'] ?? '',
                'telepon_pemasok' => $p['telepon'] ?? '',
                'alamat_pemasok'  => $p['alamat'] ?? '',
                'total_sj'        => 0,
                'total_tagihan'   => 0.0,
                'total_dibayar'   => 0.0,
                'sisa_utang'      => 0.0,
                'aging_belum'     => 0.0,
                'aging_1_7'       => 0.0,
                'aging_8_14'      => 0.0,
                'aging_15_30'     => 0.0,
                'aging_gt_30'     => 0.0,
                'sj_terbuka'      => 0
            ];
        }

        // Ambil total kas keluar yang sudah dibayarkan per pemasok
        $stmtBayar = $pdo->query("
            SELECT 
                pemasok_id,
                COALESCE(SUM(jumlah_bayar), 0) as total_dibayar
            FROM ap_pembayaran
            GROUP BY pemasok_id
        ");
        while ($row = $stmtBayar->fetch(PDO::FETCH_ASSOC)) {
            $pId = (int)$row['pemasok_id'];
            if (isset($pemasokMap[$pId])) {
                $pemasokMap[$pId]['total_dibayar'] = (float)$row['total_dibayar'];
            }
        }

        // Ambil seluruh surat jalan masuk
        $sjList = self::getSuratJalanList();
        foreach ($sjList as $sj) {
            $pId = (int)$sj['pemasok_id'];
            if (!isset($pemasokMap[$pId])) continue;

            $pemasokMap[$pId]['total_sj']++;
            $pemasokMap[$pId]['total_tagihan'] += (float)$sj['total_nilai_beli'];

            $sisa = (float)$sj['sisa_utang'];
            if ($sisa > 0.01) {
                $pemasokMap[$pId]['sj_terbuka']++;
                \App\Services\AgingService::tambah($pemasokMap[$pId], (int)$sj['hari_telat'], $sisa);
            }
        }

        // Hitung sisa utang aktual per pemasok
        foreach ($pemasokMap as &$row) {
            $row['sisa_utang'] = max(0, round($row['total_tagihan'] - $row['total_dibayar'], 2));
        }
        unset($row);

        return array_values($pemasokMap);
    }

    /**
     * Mengambil Daftar Surat Jalan Penerimaan Barang beserta Status Bayar & Umur Utang
     */
    public static function getSuratJalanList(array $filters = []): array {
        $pdo = Database::getConnection();

        $where = ["l.no_surat_jalan IS NOT NULL AND l.no_surat_jalan != '' AND l.status <> 'VOID'"];
        $params = [];

        if (!empty($filters['pemasok_id'])) {
            $where[] = "l.pemasok_id = :pemasok_id";
            $params[':pemasok_id'] = (int)$filters['pemasok_id'];
        }

        if (!empty($filters['date_start'])) {
            $where[] = "DATE(l.created_at) >= :date_start";
            $params[':date_start'] = $filters['date_start'];
        }

        if (!empty($filters['date_end'])) {
            $where[] = "DATE(l.created_at) <= :date_end";
            $params[':date_end'] = $filters['date_end'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(l.no_surat_jalan LIKE :search OR p.nama_pemasok LIKE :search OR p.kode_pemasok LIKE :search)";
            $params[':search'] = '%' . trim($filters['search']) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT 
                l.no_surat_jalan,
                l.pemasok_id,
                p.kode_pemasok,
                p.nama_pemasok,
                p.pic_nama as pic_pemasok,
                p.telepon as telepon_pemasok,
                p.alamat as alamat_pemasok,
                COUNT(l.id) as total_box,
                COALESCE(SUM(l.berat_netto), 0) as total_kg,
                COALESCE(SUM(l.berat_netto * l.harga_beli_per_kg), 0) as total_nilai_beli,
                MIN(l.created_at) as tanggal_masuk,
                DATEDIFF(CURRENT_DATE, DATE(MIN(l.created_at))) as umur_hari,
                COALESCE((
                    SELECT MIN(pi.tanggal_jatuh_tempo)
                    FROM purchase_invoices pi
                    WHERE pi.no_surat_jalan = l.no_surat_jalan AND pi.status_bayar <> 'BATAL'
                ), DATE(MIN(l.created_at))) as jatuh_tempo,
                (
                    SELECT MIN(pi.id)
                    FROM purchase_invoices pi
                    WHERE pi.no_surat_jalan = l.no_surat_jalan AND pi.status_bayar <> 'BATAL'
                ) as invoice_id,
                COALESCE((
                    SELECT SUM(nominal_alokasi)
                    FROM ap_alokasi_pembayaran
                    WHERE no_surat_jalan = l.no_surat_jalan
                ), 0) as total_bayar,
                (
                    SELECT COUNT(id)
                    FROM ap_alokasi_pembayaran
                    WHERE no_surat_jalan = l.no_surat_jalan
                ) as jml_kali_bayar
            FROM labels l
            JOIN pemasok p ON l.pemasok_id = p.id
            WHERE {$whereSql}
            GROUP BY l.no_surat_jalan, l.pemasok_id, p.kode_pemasok, p.nama_pemasok, p.pic_nama, p.telepon, p.alamat
            ORDER BY MIN(l.created_at) DESC, l.no_surat_jalan DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        $filterStatus = $filters['status'] ?? 'SEMUA';

        foreach ($rows as $r) {
            $totalBeli = (float)$r['total_nilai_beli'];
            $totalBayar = (float)$r['total_bayar'];
            $sisa = max(0, round($totalBeli - $totalBayar, 2));

            if ($sisa <= 0.01) {
                $statusBayar = 'LUNAS';
            } elseif ($totalBayar > 0) {
                $statusBayar = 'PARSIAL';
            } else {
                $statusBayar = 'BELUM_BAYAR';
            }

            // Filter status bayar jika dipilih
            if ($filterStatus === 'BELUM_LUNAS' && $statusBayar === 'LUNAS') {
                continue;
            } elseif (in_array($filterStatus, ['LUNAS', 'PARSIAL', 'BELUM_BAYAR']) && $statusBayar !== $filterStatus) {
                continue;
            }

            $r['total_nilai_beli'] = $totalBeli;
            $r['total_bayar']      = $totalBayar;
            $r['sisa_utang']       = $sisa;
            $r['status_bayar']     = $statusBayar;
            $r['umur_hari']        = (int)$r['umur_hari'];
            $r['hari_telat']       = \App\Services\AgingService::hariTelat($r['jatuh_tempo']);
            $r['jml_kali_bayar']   = (int)$r['jml_kali_bayar'];

            $result[] = $r;
        }

        return $result;
    }

    /**
     * Mengambil Surat Jalan Belum Lunas Milik Satu Pemasok (Urutan FIFO: Tertua Lebih Dahulu)
     */
    public static function getUnpaidSjByPemasok(int $pemasokId): array {
        $allSj = self::getSuratJalanList(['pemasok_id' => $pemasokId, 'status' => 'BELUM_LUNAS']);
        
        // Urutkan tertua lebih dahulu (FIFO)
        usort($allSj, function($a, $b) {
            return strcmp($a['tanggal_masuk'], $b['tanggal_masuk']);
        });

        return $allSj;
    }

    /**
     * Mengambil Sisa Utang Riil Satu Surat Jalan Tertentu
     */
    public static function getSisaUtangSj(string $noSuratJalan): float {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(l.berat_netto * l.harga_beli_per_kg), 0) as total_nilai_beli,
                COALESCE((
                    SELECT SUM(nominal_alokasi)
                    FROM ap_alokasi_pembayaran
                    WHERE no_surat_jalan = :sj_nomor
                ), 0) as total_bayar
            FROM labels l
            WHERE l.no_surat_jalan = :sj AND l.status <> 'VOID'
            GROUP BY l.no_surat_jalan
        ");
        $stmt->execute([':sj' => $noSuratJalan, ':sj_nomor' => $noSuratJalan]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return 0.0;
        return max(0, round((float)$row['total_nilai_beli'] - (float)$row['total_bayar'], 2));
    }

    /**
     * Membukukan Pembayaran Kas/Bank ke Pemasok & Mengalokasikan ke Surat Jalan (FIFO atau Manual)
     */
    public static function recordPayment(array $data, int $userId = 1): array {
        $pdo = Database::getConnection();

        $pemasokId    = (int)($data['pemasok_id'] ?? 0);
        $jumlahBayar  = (float)($data['jumlah_bayar'] ?? 0);
        $tglBayar     = trim($data['tanggal_bayar'] ?? date('Y-m-d'));
        $metodeBayar  = strtoupper(trim($data['metode_bayar'] ?? 'TRANSFER_BANK'));
        $bankSumber   = trim($data['bank_sumber'] ?? '');
        $noRekTujuan  = trim($data['no_rekening_tujuan'] ?? '');
        $keterangan   = trim($data['keterangan'] ?? '');
        $modeAlokasi  = strtoupper(trim($data['mode_alokasi'] ?? 'FIFO'));

        if ($pemasokId <= 0) {
            throw new Exception("Pemasok penerima pembayaran wajib dipilih.");
        }
        if ($jumlahBayar <= 0) {
            throw new Exception("Jumlah pembayaran utang harus lebih besar dari 0.");
        }
        if (!in_array($metodeBayar, ['TUNAI', 'TRANSFER_BANK'])) {
            $metodeBayar = 'TRANSFER_BANK';
        }

        // Aturan bisnis: pemasok selalu dibayar penuh per surat jalan / faktur (tidak ada cicilan)
        if (!empty($data['invoice_id'])) {
            $invCek = PurchaseInvoice::findById((int)$data['invoice_id']);
            if ($invCek && abs($jumlahBayar - (float)$invCek['sisa_utang']) > 0.01) {
                throw new Exception("Pembayaran ke pemasok harus lunas penuh (Rp " . number_format((float)$invCek['sisa_utang'], 0, ',', '.') . ").");
            }
        } else {
            $alokCek = $data['alokasi_manual'] ?? [];
            if (!is_array($alokCek) || !$alokCek) {
                throw new Exception("Pilih minimal satu surat jalan yang dibayar.");
            }
            $totalCek = 0.0;
            foreach ($alokCek as $noSjCek => $nomCek) {
                if ((float)$nomCek <= 0) continue;
                $sisaCek = self::getSisaUtangSj(trim((string)$noSjCek));
                if (abs((float)$nomCek - $sisaCek) > 0.01) {
                    throw new Exception("Surat jalan {$noSjCek} harus dibayar lunas penuh (Rp " . number_format($sisaCek, 0, ',', '.') . ").");
                }
                $totalCek += $sisaCek;
            }
            if (abs($totalCek - $jumlahBayar) > 0.01) {
                throw new Exception("Total pembayaran tidak sama dengan jumlah surat jalan yang dipilih.");
            }
            $modeAlokasi = 'MANUAL';
        }

        // Generate No Bukti Pengeluaran Unik: CD-YYYYMMDD-XXXX (Cash Disbursement)
        $dateCode = date('Ymd', strtotime($tglBayar));
        $countToday = $pdo->query("SELECT COUNT(id) FROM ap_pembayaran WHERE tanggal_bayar = '{$tglBayar}'")->fetchColumn();
        $nextSeq = str_pad((int)$countToday + 1, 4, '0', STR_PAD_LEFT);
        $noBukti = "CD-{$dateCode}-{$nextSeq}";

        // Cek duplikasi no bukti
        $cekDup = $pdo->prepare("SELECT id FROM ap_pembayaran WHERE no_bukti_keluar = ?");
        $cekDup->execute([$noBukti]);
        if ($cekDup->fetch()) {
            $noBukti = "CD-{$dateCode}-" . strtoupper(substr(uniqid(), -4));
        }

        $pdo->beginTransaction();
        try {
            // 1. Simpan Header Pembayaran ke `ap_pembayaran`
            $stmtInsert = $pdo->prepare("
                INSERT INTO ap_pembayaran 
                (no_bukti_keluar, pemasok_id, tanggal_bayar, metode_bayar, bank_sumber, no_rekening_tujuan, jumlah_bayar, keterangan, user_id)
                VALUES 
                (:no_bukti, :pemasok_id, :tgl, :metode, :bank, :rekening, :jumlah, :ket, :uid)
            ");
            $stmtInsert->execute([
                ':no_bukti'    => $noBukti,
                ':pemasok_id'  => $pemasokId,
                ':tgl'         => $tglBayar,
                ':metode'      => $metodeBayar,
                ':bank'        => ($metodeBayar === 'TRANSFER_BANK' && !empty($bankSumber)) ? $bankSumber : null,
                ':rekening'    => ($metodeBayar === 'TRANSFER_BANK' && !empty($noRekTujuan)) ? $noRekTujuan : null,
                ':jumlah'      => $jumlahBayar,
                ':ket'         => $keterangan ?: null,
                ':uid'         => $userId
            ]);
            $pembayaranId = (int)$pdo->lastInsertId();

            // 2. Alokasikan ke Surat Jalan Penerimaan atau Purchase Invoice
            $allocatedSj = [];
            $sisaUangBayar = $jumlahBayar;

            if (!empty($data['invoice_id'])) {
                // ALOKASI SPESIFIK KE 1 PURCHASE INVOICE
                $targetInvId = (int)$data['invoice_id'];
                $inv = PurchaseInvoice::findById($targetInvId);
                if ($inv) {
                    $sisaReal = (float)$inv['sisa_utang'];
                    $alokasiNominal = min($jumlahBayar, $sisaReal);
                    $sisaSetelah = round($sisaReal - $alokasiNominal, 2);

                    $stmtAlok = $pdo->prepare("
                        INSERT INTO ap_alokasi_pembayaran 
                        (pembayaran_id, no_surat_jalan, invoice_id, nominal_alokasi, sisa_utang_setelah)
                        VALUES 
                        (:pid, :sj, :inv_id, :nom, :sisa)
                    ");
                    $stmtAlok->execute([
                        ':pid'    => $pembayaranId,
                        ':sj'     => $inv['no_surat_jalan'] ?: '',
                        ':inv_id' => $targetInvId,
                        ':nom'    => $alokasiNominal,
                        ':sisa'   => $sisaSetelah
                    ]);

                    $allocatedSj[] = [
                        'no_surat_jalan'  => $inv['no_surat_jalan'] ?: $inv['no_invoice'],
                        'invoice_id'      => $targetInvId,
                        'nominal_alokasi' => $alokasiNominal,
                        'sisa_setelah'    => $sisaSetelah
                    ];

                    $sisaUangBayar -= $alokasiNominal;
                    PurchaseInvoice::syncPaymentStatus($targetInvId);
                }
            } elseif ($modeAlokasi === 'MANUAL' && !empty($data['alokasi_manual']) && is_array($data['alokasi_manual'])) {
                // ALOKASI MANUAL
                $totalAlokasiReq = 0.0;
                foreach ($data['alokasi_manual'] as $noSj => $nom) {
                    $nom = (float)$nom;
                    if ($nom <= 0) continue;
                    $totalAlokasiReq += $nom;
                }

                if ($totalAlokasiReq > $jumlahBayar + 0.01) {
                    throw new Exception("Total alokasi manual (Rp " . number_format($totalAlokasiReq, 0, ',', '.') . ") melebihi jumlah uang yang dibayarkan (Rp " . number_format($jumlahBayar, 0, ',', '.') . ").");
                }

                foreach ($data['alokasi_manual'] as $noSj => $nom) {
                    $noSj = trim($noSj);
                    $nom = (float)$nom;
                    if ($nom <= 0 || empty($noSj)) continue;

                    $sisaReal = self::getSisaUtangSj($noSj);
                    if ($sisaReal <= 0.01) continue;

                    $alokasiNominal = min($nom, $sisaReal);
                    $sisaSetelah = round($sisaReal - $alokasiNominal, 2);

                    $inv = PurchaseInvoice::findBySuratJalan($noSj);
                    $invId = $inv ? (int)$inv['id'] : null;

                    $stmtAlok = $pdo->prepare("
                        INSERT INTO ap_alokasi_pembayaran 
                        (pembayaran_id, no_surat_jalan, invoice_id, nominal_alokasi, sisa_utang_setelah)
                        VALUES 
                        (:pid, :sj, :inv_id, :nom, :sisa)
                    ");
                    $stmtAlok->execute([
                        ':pid'    => $pembayaranId,
                        ':sj'     => $noSj,
                        ':inv_id' => $invId,
                        ':nom'    => $alokasiNominal,
                        ':sisa'   => $sisaSetelah
                    ]);

                    if ($invId) {
                        PurchaseInvoice::syncPaymentStatus($invId);
                    }

                    $allocatedSj[] = [
                        'no_surat_jalan'  => $noSj,
                        'nominal_alokasi' => $alokasiNominal,
                        'sisa_setelah'    => $sisaSetelah
                    ];
                }
            } else {
                // ALOKASI OTOMATIS (FIFO): Urutkan Surat Jalan tertua lebih dahulu
                $unpaidSjList = self::getUnpaidSjByPemasok($pemasokId);

                foreach ($unpaidSjList as $sj) {
                    if ($sisaUangBayar <= 0.001) break;

                    $sisaSj = (float)$sj['sisa_utang'];
                    if ($sisaSj <= 0.01) continue;

                    $potong = min($sisaUangBayar, $sisaSj);
                    $sisaSetelah = round($sisaSj - $potong, 2);

                    $inv = PurchaseInvoice::findBySuratJalan($sj['no_surat_jalan']);
                    $invId = $inv ? (int)$inv['id'] : null;

                    $stmtAlok = $pdo->prepare("
                        INSERT INTO ap_alokasi_pembayaran 
                        (pembayaran_id, no_surat_jalan, invoice_id, nominal_alokasi, sisa_utang_setelah)
                        VALUES 
                        (:pid, :sj, :inv_id, :nom, :sisa)
                    ");
                    $stmtAlok->execute([
                        ':pid'    => $pembayaranId,
                        ':sj'     => $sj['no_surat_jalan'],
                        ':inv_id' => $invId,
                        ':nom'    => $potong,
                        ':sisa'   => $sisaSetelah
                    ]);

                    if ($invId) {
                        PurchaseInvoice::syncPaymentStatus($invId);
                    }

                    $allocatedSj[] = [
                        'no_surat_jalan'  => $sj['no_surat_jalan'],
                        'nominal_alokasi' => $potong,
                        'sisa_setelah'    => $sisaSetelah
                    ];

                    $sisaUangBayar -= $potong;
                }
            }

            \App\Services\JurnalService::postApPayment((int)$pembayaranId);

            $pdo->commit();

            return [
                'success'         => true,
                'pembayaran_id'   => $pembayaranId,
                'no_bukti_keluar' => $noBukti,
                'jumlah_bayar'    => $jumlahBayar,
                'allocated_count' => count($allocatedSj),
                'allocated_sj'    => $allocatedSj,
                'sisa_deposit'    => max(0, round($sisaUangBayar, 2))
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Mengambil Riwayat Pembayaran untuk Satu Surat Jalan Tertentu
     */
    public static function getPaymentHistoryBySj(string $noSuratJalan): array {
        $pdo = Database::getConnection();

        $sql = "
            SELECT 
                al.id as alokasi_id,
                al.nominal_alokasi,
                al.sisa_utang_setelah,
                al.created_at as tgl_alokasi,
                p.id as pembayaran_id,
                p.no_bukti_keluar,
                p.tanggal_bayar,
                p.metode_bayar,
                p.bank_sumber,
                p.no_rekening_tujuan,
                p.jumlah_bayar as total_kas_keluar_induk,
                p.keterangan,
                u.nama_lengkap as nama_kasir
            FROM ap_alokasi_pembayaran al
            JOIN ap_pembayaran p ON al.pembayaran_id = p.id
            LEFT JOIN users u ON p.user_id = u.id
            WHERE al.no_surat_jalan = :sj
            ORDER BY p.tanggal_bayar ASC, al.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':sj' => $noSuratJalan]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil Riwayat Semua Pengeluaran Kas / Pembayaran ke Pemasok
     */
    public static function getAllPayments(array $filters = []): array {
        $pdo = Database::getConnection();

        $where = ["1=1"];
        $params = [];

        if (!empty($filters['pemasok_id'])) {
            $where[] = "p.pemasok_id = :pemasok_id";
            $params[':pemasok_id'] = (int)$filters['pemasok_id'];
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
            $where[] = "(p.no_bukti_keluar LIKE :search OR pm.nama_pemasok LIKE :search OR p.keterangan LIKE :search)";
            $params[':search'] = '%' . trim($filters['search']) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT 
                p.id,
                p.no_bukti_keluar,
                p.pemasok_id,
                p.tanggal_bayar,
                p.metode_bayar,
                p.bank_sumber,
                p.no_rekening_tujuan,
                p.jumlah_bayar,
                p.status,
                p.jumlah_asal,
                p.alasan_batal,
                p.keterangan,
                p.created_at,
                pm.kode_pemasok,
                pm.nama_pemasok,
                u.nama_lengkap as nama_kasir,
                (
                    SELECT COUNT(id) 
                    FROM ap_alokasi_pembayaran 
                    WHERE pembayaran_id = p.id
                ) as jml_sj_terpotong,
                (
                    SELECT GROUP_CONCAT(no_surat_jalan SEPARATOR ', ')
                    FROM ap_alokasi_pembayaran
                    WHERE pembayaran_id = p.id
                ) as daftar_sj_terpotong
            FROM ap_pembayaran p
            JOIN pemasok pm ON p.pemasok_id = pm.id
            LEFT JOIN users u ON p.user_id = u.id
            WHERE {$whereSql}
            ORDER BY p.tanggal_bayar DESC, p.id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

