<?php
/**
 * Rekonsiliasi Bank Harian.
 * Sehari satu kali: total setoran mitra pada tanggal itu (dari sistem) dicocokkan dengan total uang yang
 * benar-benar masuk di bank, dengan lampiran screenshot / slip bank sebagai bukti audit.
 * Setoran pada hari yang sudah direkonsiliasi ikut terkunci (tidak bisa dibatalkan) sampai rekonsiliasinya dibuka kembali.
 */

namespace App\Models;

use App\Core\Database;
use Exception;
use PDO;

class RekonsiliasiBank {

    private static bool $ready = false;

    /** Buat tabel bila belum ada. Jalankan di luar transaksi (dipanggil dari Migrasi). */
    public static function ensureTables(): void {
        if (self::$ready) return;
        $db = Database::getConnection();
        if (!$db->query("SHOW TABLES LIKE 'rekonsiliasi_harian'")->fetchColumn()) {
            if ($db->inTransaction()) throw new Exception('Tabel rekonsiliasi belum ada. Muat ulang halaman.');
            $db->exec("
                CREATE TABLE rekonsiliasi_harian (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tanggal DATE NOT NULL,
                    jumlah_setoran INT NOT NULL DEFAULT 0,
                    total_tunai DECIMAL(14,2) NOT NULL DEFAULT 0,
                    total_transfer DECIMAL(14,2) NOT NULL DEFAULT 0,
                    total_sistem DECIMAL(14,2) NOT NULL DEFAULT 0,
                    total_bank DECIMAL(14,2) NOT NULL DEFAULT 0,
                    selisih DECIMAL(14,2) NOT NULL DEFAULT 0,
                    status ENUM('SESUAI','SELISIH') NOT NULL,
                    catatan VARCHAR(255) DEFAULT NULL,
                    verified_by INT DEFAULT NULL,
                    verified_at DATETIME DEFAULT NULL,
                    UNIQUE KEY uq_tanggal (tanggal)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $db->exec("
                CREATE TABLE rekonsiliasi_harian_bukti (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    rekon_id INT NOT NULL,
                    file_path VARCHAR(255) NOT NULL,
                    nama_asli VARCHAR(150) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_rekon (rekon_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        self::$ready = true;
    }

    /**
     * Daftar hari yang punya setoran (atau sudah direkonsiliasi) pada rentang tanggal.
     * Tiap baris: tanggal, jumlah_setoran, total_tunai, total_transfer, total_sistem, rekon (atau null), status_hari.
     * status_hari: BELUM | SESUAI | SELISIH | BERUBAH (total sistem berubah setelah direkonsiliasi).
     */
    public static function daftarHari(string $dari, string $sampai, string $status = ''): array {
        self::ensureTables();
        $db = Database::getConnection();

        $st = $db->prepare("
            SELECT tanggal_bayar tanggal, COUNT(*) jumlah,
                   SUM(CASE WHEN metode_bayar = 'TUNAI' THEN jumlah_bayar ELSE 0 END) tunai,
                   SUM(CASE WHEN metode_bayar <> 'TUNAI' THEN jumlah_bayar ELSE 0 END) transfer,
                   SUM(jumlah_bayar) total
            FROM ar_pembayaran
            WHERE status = 'AKTIF' AND jumlah_bayar > 0 AND tanggal_bayar BETWEEN ? AND ?
            GROUP BY tanggal_bayar");
        $st->execute([$dari, $sampai]);
        $hari = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $hari[$r['tanggal']] = [
                'tanggal' => $r['tanggal'], 'jumlah_setoran' => (int)$r['jumlah'],
                'total_tunai' => (float)$r['tunai'], 'total_transfer' => (float)$r['transfer'], 'total_sistem' => (float)$r['total'],
                'rekon' => null,
            ];
        }

        $st = $db->prepare("
            SELECT r.*, u.nama_lengkap verifikator, (SELECT COUNT(*) FROM rekonsiliasi_harian_bukti b WHERE b.rekon_id = r.id) jumlah_bukti
            FROM rekonsiliasi_harian r LEFT JOIN users u ON u.id = r.verified_by
            WHERE r.tanggal BETWEEN ? AND ?");
        $st->execute([$dari, $sampai]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (!isset($hari[$r['tanggal']])) {
                // hari sudah direkonsiliasi tetapi setorannya kini kosong (dibatalkan)
                $hari[$r['tanggal']] = ['tanggal' => $r['tanggal'], 'jumlah_setoran' => 0, 'total_tunai' => 0.0, 'total_transfer' => 0.0, 'total_sistem' => 0.0, 'rekon' => null];
            }
            $hari[$r['tanggal']]['rekon'] = $r;
        }

        $out = [];
        foreach ($hari as $h) {
            if ($h['rekon']) {
                $berubah = abs((float)$h['rekon']['total_sistem'] - $h['total_sistem']) > 0.009;
                $h['status_hari'] = $berubah ? 'BERUBAH' : $h['rekon']['status'];
            } else {
                $h['status_hari'] = 'BELUM';
            }
            if ($status !== '' && $h['status_hari'] !== $status) continue;
            $out[] = $h;
        }
        usort($out, fn($a, $b) => strcmp($b['tanggal'], $a['tanggal']));
        return $out;
    }

    /** Ringkasan untuk kartu: jumlah hari per status dan total selisih. */
    public static function ringkas(array $hari): array {
        $r = ['hari' => count($hari), 'belum' => 0, 'sesuai' => 0, 'selisih' => 0, 'berubah' => 0, 'total_sistem' => 0.0, 'total_bank' => 0.0, 'total_selisih' => 0.0, 'belum_nominal' => 0.0];
        foreach ($hari as $h) {
            $r['total_sistem'] += $h['total_sistem'];
            switch ($h['status_hari']) {
                case 'BELUM': $r['belum']++; $r['belum_nominal'] += $h['total_sistem']; break;
                case 'SESUAI': $r['sesuai']++; break;
                case 'SELISIH': $r['selisih']++; break;
                case 'BERUBAH': $r['berubah']++; break;
            }
            if ($h['rekon']) { $r['total_bank'] += (float)$h['rekon']['total_bank']; $r['total_selisih'] += (float)$h['rekon']['selisih']; }
        }
        return $r;
    }

    /** Setoran aktif pada satu tanggal (rincian per mitra). */
    public static function setoranTanggal(string $tanggal): array {
        $st = Database::getConnection()->prepare("
            SELECT p.id, p.no_bukti_bayar, p.metode_bayar, p.bank_tujuan, p.jumlah_bayar, p.keterangan, m.kode_mitra, m.nama_mitra
            FROM ar_pembayaran p JOIN mitra m ON m.id = p.mitra_id
            WHERE p.status = 'AKTIF' AND p.jumlah_bayar > 0 AND p.tanggal_bayar = ?
            ORDER BY p.id");
        $st->execute([$tanggal]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function ambil(string $tanggal): ?array {
        self::ensureTables();
        $db = Database::getConnection();
        $st = $db->prepare("SELECT r.*, u.nama_lengkap verifikator FROM rekonsiliasi_harian r LEFT JOIN users u ON u.id = r.verified_by WHERE r.tanggal = ?");
        $st->execute([$tanggal]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return null;
        $b = $db->prepare("SELECT id, file_path, nama_asli FROM rekonsiliasi_harian_bukti WHERE rekon_id = ? ORDER BY id");
        $b->execute([$r['id']]);
        $r['bukti'] = $b->fetchAll(PDO::FETCH_ASSOC);
        return $r;
    }

    /**
     * Simpan rekonsiliasi satu hari. $bukti: [[file_path, nama_asli], ...] (minimal satu).
     * Setoran hari itu ditandai MATCHED (sesuai) atau SELISIH sehingga terkunci dari pembatalan.
     */
    public static function simpan(string $tanggal, float $totalBank, string $catatan, array $bukti, int $userId): array {
        self::ensureTables();
        $db = Database::getConnection();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) throw new Exception('Tanggal tidak valid.');
        if ($tanggal > date('Y-m-d')) throw new Exception('Tanggal rekonsiliasi tidak boleh di masa depan.');
        if (!$bukti) throw new Exception('Lampirkan minimal satu screenshot / slip bank sebagai bukti.');
        if ($totalBank < 0) throw new Exception('Total uang di bank tidak valid.');
        if (self::ambil($tanggal)) throw new Exception('Tanggal ini sudah direkonsiliasi. Buka kembali dulu bila perlu mengubahnya.');

        $setoran = self::setoranTanggal($tanggal);
        if (!$setoran) throw new Exception('Tidak ada setoran pada tanggal ini.');
        $tunai = $transfer = 0.0;
        foreach ($setoran as $s) {
            if ($s['metode_bayar'] === 'TUNAI') $tunai += (float)$s['jumlah_bayar']; else $transfer += (float)$s['jumlah_bayar'];
        }
        $sistem = $tunai + $transfer;
        $selisih = round($totalBank - $sistem, 2);
        $catatan = trim($catatan);
        if (abs($selisih) >= 0.01 && mb_strlen($catatan) < 3) {
            throw new Exception('Total bank berbeda dari total sistem. Isi catatan penyebab selisih.');
        }
        $status = abs($selisih) < 0.01 ? 'SESUAI' : 'SELISIH';

        $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO rekonsiliasi_harian (tanggal, jumlah_setoran, total_tunai, total_transfer, total_sistem, total_bank, selisih, status, catatan, verified_by, verified_at)
                          VALUES (?,?,?,?,?,?,?,?,?,?,NOW())")
               ->execute([$tanggal, count($setoran), $tunai, $transfer, $sistem, $totalBank, $selisih, $status, $catatan ?: null, $userId]);
            $id = (int)$db->lastInsertId();
            $ib = $db->prepare("INSERT INTO rekonsiliasi_harian_bukti (rekon_id, file_path, nama_asli) VALUES (?,?,?)");
            foreach ($bukti as [$path, $asli]) $ib->execute([$id, $path, mb_substr($asli, 0, 150)]);
            $db->prepare("UPDATE ar_pembayaran SET status_rekonsiliasi = ?, nominal_aktual = jumlah_bayar, selisih_nominal = 0,
                                 catatan_rekonsiliasi = ?, verified_at = NOW(), verified_by = ?
                          WHERE status = 'AKTIF' AND tanggal_bayar = ?")
               ->execute([$status === 'SESUAI' ? 'MATCHED' : 'SELISIH', $catatan ?: null, $userId, $tanggal]);
            self::audit($userId, 'REKONSILIASI_HARIAN', $tanggal, "Status {$status}, sistem {$sistem}, bank {$totalBank}");
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        return ['status' => $status, 'selisih' => $selisih, 'total_sistem' => $sistem];
    }

    /** Buka kembali rekonsiliasi satu hari: setoran kembali PENDING; file bukti dibiarkan sebagai jejak. */
    public static function bukaKembali(string $tanggal, string $alasan, int $userId): void {
        self::ensureTables();
        $db = Database::getConnection();
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 3) throw new Exception('Alasan membuka kembali wajib diisi.');
        $r = self::ambil($tanggal);
        if (!$r) throw new Exception('Tanggal ini belum direkonsiliasi.');

        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM rekonsiliasi_harian_bukti WHERE rekon_id = ?")->execute([$r['id']]);
            $db->prepare("DELETE FROM rekonsiliasi_harian WHERE id = ?")->execute([$r['id']]);
            $db->prepare("UPDATE ar_pembayaran SET status_rekonsiliasi = 'PENDING', nominal_aktual = NULL, selisih_nominal = 0,
                                 catatan_rekonsiliasi = NULL, verified_at = NULL, verified_by = NULL
                          WHERE tanggal_bayar = ?")->execute([$tanggal]);
            self::audit($userId, 'BUKA_REKONSILIASI', $tanggal, $alasan . ' (sebelumnya ' . $r['status'] . ', bank ' . $r['total_bank'] . ')');
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    private static function audit(int $userId, string $aksi, string $target, string $alasan): void {
        Database::getConnection()
            ->prepare("INSERT INTO audit_logs (user_id, action_name, target_table, target_id, reason, ip_address) VALUES (?,?,?,?,?,?)")
            ->execute([$userId, $aksi, 'rekonsiliasi_harian', $target, mb_substr($alasan, 0, 255), $_SERVER['REMOTE_ADDR'] ?? 'cli']);
    }
}
