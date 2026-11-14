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
    public static function getSummaryKpi(): array {
        $pdo = Database::getConnection();

        // 1. Total Belanja / Piutang Terbit dari seluruh transaksi pengeluaran
        $stmtBeli = $pdo->query("
            SELECT 
                COUNT(id) as total_nota,
                COALESCE(SUM(total_nilai_jual), 0) as total_piutang_terbit
            FROM transaksi_stok
            WHERE jenis_transaksi = 'PENGELUARAN'
        ");
        $resBeli = $stmtBeli->fetch(PDO::FETCH_ASSOC);

        // 2. Total Setoran Uang Masuk yang telah dibukukan
        $stmtSetor = $pdo->query("
            SELECT 
                COALESCE(SUM(jumlah_bayar), 0) as total_setoran_masuk,
                COUNT(CASE WHEN status = 'AKTIF' THEN 1 END) as total_transaksi_setor
            FROM ar_pembayaran
        ");
        $resSetor = $stmtSetor->fetch(PDO::FETCH_ASSOC);

        $totalPiutangTerbit = (float)$resBeli['total_piutang_terbit'];
        $totalSetoranMasuk  = (float)$resSetor['total_setoran_masuk'];
        $sisaPiutangAktif   = max(0, round($totalPiutangTerbit - $totalSetoranMasuk, 2));

        // 3. Klasifikasi Umur Piutang yang masih belum lunas
        $notasList = self::getNotaList(['status' => 'BELUM_LUNAS']);
        
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
    public static function getAgingSummaryPerMitra(): array {
        $pdo = Database::getConnection();

        // Ambil seluruh mitra bisnis aktif
        $stmtMitra = $pdo->query("
            SELECT id, kode_mitra, nama_mitra, pic_nama, pic_telepon
            FROM mitra
            ORDER BY id ASC
        ");
        $mitraList = $stmtMitra->fetchAll(PDO::FETCH_ASSOC);

        // Ambil seluruh nota belum lunas untuk kalkulasi aging per mitra
        $unpaidNotas = self::getNotaList(['status' => 'BELUM_LUNAS']);
        
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
                    WHERE transaksi_stok_id = ts.id
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
                ), 0) as total_bayar
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

        if ($mitraId <= 0) {
            throw new Exception("Mitra pembayar wajib dipilih.");
        }
        if ($jumlahBayar <= 0) {
            throw new Exception("Jumlah uang setoran harus lebih besar dari 0.");
        }
        if (!in_array($metodeBayar, ['TUNAI', 'TRANSFER_BANK'])) {
            $metodeBayar = 'TUNAI';
        }

        // Aturan bisnis: setoran tidak boleh melebihi total sisa piutang mitra (belum ada uang muka)
        $totalSisaMitra = 0.0;
        foreach (self::getUnpaidNotasByMitra($mitraId) as $notaCek) {
            $totalSisaMitra += (float)$notaCek['sisa_piutang'];
        }
        $totalSisaMitra = round($totalSisaMitra, 2);
        if ($jumlahBayar > $totalSisaMitra + 0.01) {
            if ($totalSisaMitra <= 0.01) {
                throw new Exception("Mitra ini tidak punya piutang terbuka, setoran tidak dapat dicatat.");
            }
            throw new Exception("Setoran (Rp " . number_format($jumlahBayar, 0, ',', '.') . ") melebihi total sisa piutang mitra (Rp " . number_format($totalSisaMitra, 0, ',', '.') . ").");
        }

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
                    throw new Exception("Total alokasi manual (Rp " . number_format($totalAlokasiReq, 0, ',', '.') . ") melebihi jumlah uang yang disetor (Rp " . number_format($jumlahBayar, 0, ',', '.') . ").");
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

                if (empty($unpaidNotas)) {
                    // Jika mitra tidak memiliki nota terutang, setoran tetap dicatat di ar_pembayaran sebagai saldo titipan/deposit
                } else {
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

            \App\Services\JurnalService::postArPayment((int)$pembayaranId);

            $pdo->commit();

            return [
                'success'           => true,
                'pembayaran_id'     => $pembayaranId,
                'no_bukti_bayar'    => $noBukti,
                'jumlah_bayar'      => $jumlahBayar,
                'allocated_count'   => count($allocatedNotas),
                'allocated_notas'   => $allocatedNotas,
                'sisa_deposit'      => max(0, round($sisaUangSetoran, 2))
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
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
