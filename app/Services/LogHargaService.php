<?php
namespace App\Services;

use App\Core\Database;
use Exception;
use PDO;

/**
 * Log perubahan harga master (harga beli per pemasok dan harga jual per mitra).
 * Setiap perubahan nilai dicatat: harga lama, harga baru, siapa, kapan, dan alasan.
 * Harga yang tidak berubah tidak dicatat.
 */
class LogHargaService
{
    private static bool $ready = false;

    /** Buat tabel bila belum ada, dan isi harga yang berlaku saat ini sebagai titik awal. Jalankan di luar transaksi. */
    public static function ensureTables(): void
    {
        if (self::$ready) return;
        $db = Database::getConnection();
        if (!$db->query("SHOW TABLES LIKE 'log_harga'")->fetchColumn()) {
            if ($db->inTransaction()) throw new Exception('Tabel log harga belum ada. Muat ulang halaman.');
            $db->exec("
                CREATE TABLE log_harga (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    jenis ENUM('PEMASOK','MITRA') NOT NULL,
                    pihak_id INT NOT NULL,
                    item_id INT NOT NULL,
                    harga_lama DECIMAL(12,2) DEFAULT NULL,
                    harga_baru DECIMAL(12,2) NOT NULL,
                    alasan VARCHAR(255) DEFAULT NULL,
                    user_id INT DEFAULT NULL,
                    created_at DATETIME NOT NULL,
                    KEY idx_item (item_id),
                    KEY idx_pihak (jenis, pihak_id),
                    KEY idx_waktu (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            // titik awal: harga yang sudah ada saat fitur ini dipasang
            $db->exec("INSERT INTO log_harga (jenis, pihak_id, item_id, harga_lama, harga_baru, alasan, user_id, created_at)
                       SELECT 'PEMASOK', pemasok_id, item_id, NULL, harga_per_kg, 'Harga awal (saat log dipasang)', NULL, COALESCE(updated_at, NOW())
                       FROM master_harga_pemasok WHERE harga_per_kg > 0");
            $db->exec("INSERT INTO log_harga (jenis, pihak_id, item_id, harga_lama, harga_baru, alasan, user_id, created_at)
                       SELECT 'MITRA', mitra_id, item_id, NULL, harga_per_kg, 'Harga awal (saat log dipasang)', NULL, COALESCE(updated_at, NOW())
                       FROM master_harga WHERE harga_per_kg > 0");
        }
        self::$ready = true;
    }

    /** Catat bila nilainya berubah. $lama null = belum pernah ditetapkan. Kembalikan true bila tercatat. */
    public static function catat(string $jenis, int $pihakId, int $itemId, ?float $lama, float $baru, ?string $alasan = null, ?int $userId = null): bool
    {
        self::ensureTables();
        $baru = round($baru, 2);
        $lama = $lama === null ? null : round($lama, 2);
        if ($lama === null && $baru <= 0) return false;      // belum ada harga dan tetap kosong
        if ($lama !== null && abs($lama - $baru) < 0.005) return false;  // tidak berubah
        $userId = $userId ?? (isset($_SESSION['user_login']['id']) ? (int)$_SESSION['user_login']['id'] : null);
        Database::getConnection()
            ->prepare("INSERT INTO log_harga (jenis, pihak_id, item_id, harga_lama, harga_baru, alasan, user_id, created_at) VALUES (?,?,?,?,?,?,?,NOW())")
            ->execute([$jenis, $pihakId, $itemId, $lama, $baru, $alasan !== null && trim($alasan) !== '' ? mb_substr(trim($alasan), 0, 255) : null, $userId]);
        return true;
    }

    /**
     * Log perubahan harga, terbaru dulu. Filter: jenis, pihak_id, item_id, dari, sampai (Y-m-d), limit,
     * dengan_awal (true = sertakan baris harga awal saat log dipasang; bawaan disembunyikan).
     * Kolom: waktu, jenis, pihak, kode_item, nama_item, harga_lama, harga_baru, selisih, persen, oleh, alasan.
     */
    public static function daftar(array $f = []): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $sql = "
            SELECT l.id, l.jenis, l.pihak_id, l.item_id, l.harga_lama, l.harga_baru, l.alasan, l.created_at waktu,
                   CASE WHEN l.jenis = 'PEMASOK' THEN p.nama_pemasok ELSE m.nama_mitra END pihak,
                   i.kode_item, i.nama_item, u.nama_lengkap oleh
            FROM log_harga l
            JOIN items i ON i.id = l.item_id
            LEFT JOIN pemasok p ON l.jenis = 'PEMASOK' AND p.id = l.pihak_id
            LEFT JOIN mitra m ON l.jenis = 'MITRA' AND m.id = l.pihak_id
            LEFT JOIN users u ON u.id = l.user_id
            WHERE 1=1";
        $p = [];
        if (!empty($f['jenis']))    { $sql .= " AND l.jenis = ?";    $p[] = $f['jenis']; }
        if (!empty($f['pihak_id'])) { $sql .= " AND l.pihak_id = ?"; $p[] = (int)$f['pihak_id']; }
        if (!empty($f['item_id']))  { $sql .= " AND l.item_id = ?";  $p[] = (int)$f['item_id']; }
        if (empty($f['dengan_awal'])) { $sql .= " AND NOT (l.harga_lama IS NULL AND COALESCE(l.alasan, '') LIKE 'Harga awal%')"; }
        if (!empty($f['dari']))     { $sql .= " AND DATE(l.created_at) >= ?"; $p[] = $f['dari']; }
        if (!empty($f['sampai']))   { $sql .= " AND DATE(l.created_at) <= ?"; $p[] = $f['sampai']; }
        $sql .= " ORDER BY l.created_at DESC, l.id DESC LIMIT " . (int)($f['limit'] ?? 500);
        $st = $db->prepare($sql);
        $st->execute($p);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['harga_baru'] = (float)$r['harga_baru'];
            $r['harga_lama'] = $r['harga_lama'] === null ? null : (float)$r['harga_lama'];
            $r['selisih'] = $r['harga_lama'] === null ? null : $r['harga_baru'] - $r['harga_lama'];
            $r['persen'] = ($r['harga_lama'] !== null && $r['harga_lama'] > 0) ? ($r['harga_baru'] - $r['harga_lama']) / $r['harga_lama'] * 100 : null;
        }
        return $rows;
    }
}
