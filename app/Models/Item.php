<?php
/**
 * Model Item (SKU) & Jenis Barang
 */

namespace App\Models;

use App\Core\Database;

class Item {
    public static function getAll(): array {
        $db = Database::getConnection();
        $sql = "
            SELECT i.*, j.nama_jenis, j.kode_jenis, j.toleransi_min_kg, j.toleransi_maks_kg
            FROM items i
            JOIN jenis_barang j ON i.jenis_id = j.id
            WHERE i.is_active = 1
            ORDER BY j.kode_jenis ASC, i.nama_item ASC
        ";
        return $db->query($sql)->fetchAll();
    }

    public static function findById(int $id): ?array {
        $db = Database::getConnection();
        $sql = "
            SELECT i.*, j.nama_jenis, j.kode_jenis, j.toleransi_min_kg, j.toleransi_maks_kg
            FROM items i
            JOIN jenis_barang j ON i.jenis_id = j.id
            WHERE i.id = ?
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public static function getAllJenis(): array {
        $db = Database::getConnection();
        return $db->query("SELECT * FROM jenis_barang WHERE is_active = 1 ORDER BY kode_jenis ASC")->fetchAll();
    }

    public static function getAllLokasi(): array {
        return [];
    }

    /** Awalan kode produk mengikuti kategori: dipelajari dari kode produk yang sudah ada di kategori itu. */
    public static function prefixUntukJenis(int $jenisId): string {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT kode_item FROM items WHERE jenis_id = ?");
        $st->execute([$jenisId]);
        $hitung = [];
        foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $k) {
            if (preg_match('/^([A-Z]+)-\d+$/', (string)$k, $m)) {
                $hitung[$m[1]] = ($hitung[$m[1]] ?? 0) + 1;
            }
        }
        if ($hitung) {
            arsort($hitung);
            return (string)array_key_first($hitung);
        }
        $st = $db->prepare("SELECT nama_jenis FROM jenis_barang WHERE id = ?");
        $st->execute([$jenisId]);
        $huruf = preg_replace('/[^A-Z]/', '', strtoupper((string)$st->fetchColumn()));
        return substr($huruf !== '' ? $huruf : 'SKU', 0, 2);
    }

    /** Kode produk otomatis per kategori: IK-001, TR-045, ... */
    public static function generateNextKode(int $jenisId): string {
        return \App\Core\KodeGenerator::next('items', 'kode_item', self::prefixUntukJenis($jenisId), 3, '-');
    }

    /** Kode kategori otomatis: PRD01, PRD02, ... */
    public static function generateNextKodeJenis(): string {
        return \App\Core\KodeGenerator::next('jenis_barang', 'kode_jenis', 'PRD', 2, '');
    }

    public static function create(array $data): int {
        $db = Database::getConnection();
        $kodeBaru = self::generateNextKode((int)$data['jenis_id']);
        $stmt = $db->prepare("
            INSERT INTO items (kode_item, nama_item, jenis_id, default_tara_kg, berat_standar_kg, satuan_accurate, is_active)
            VALUES (?, ?, ?, 0.000, NULL, ?, 1)
        ");
        $stmt->execute([
            $kodeBaru,
            trim($data['nama_item']),
            (int)$data['jenis_id'],
            trim($data['satuan_accurate'] ?? 'KG')
        ]);
        $newItemId = (int)$db->lastInsertId();

        // Inisialisasi otomatis baris di master_harga untuk seluruh mitra aktif agar tarif siap disetting
        $mitraIds = $db->query("SELECT id FROM mitra WHERE is_active = 1")->fetchAll(\PDO::FETCH_COLUMN);
        if (!empty($mitraIds)) {
            $stmtHrg = $db->prepare("INSERT IGNORE INTO master_harga (mitra_id, item_id, harga_per_kg) VALUES (?, ?, 0.00)");
            foreach ($mitraIds as $mId) {
                $stmtHrg->execute([(int)$mId, $newItemId]);
            }
        }

        return $newItemId;
    }

    public static function update(int $id, array $data): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE items 
            SET nama_item = ?, jenis_id = ?, default_tara_kg = 0.000, satuan_accurate = ?, is_active = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            trim($data['nama_item']),
            (int)$data['jenis_id'],
            trim($data['satuan_accurate'] ?? 'KG'),
            isset($data['is_active']) ? (int)$data['is_active'] : 1,
            $id
        ]);
    }

    /** Hapus permanen; hanya jika produk tidak dipakai di menu lain. */
    public static function delete(int $id): bool {
        $db = Database::getConnection();
        $it = self::findById($id);
        if (!$it) throw new \Exception('Data produk tidak ditemukan.');
        \App\Services\MasterGuard::pastikanTidakDipakai('item', $id, "Produk {$it['kode_item']} {$it['nama_item']}");
        $db->prepare("DELETE FROM master_harga WHERE item_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM master_harga_pemasok WHERE item_id = ?")->execute([$id]);
        return $db->prepare("DELETE FROM items WHERE id = ?")->execute([$id]);
    }

    public static function saveJenis(array $data): int {
        $db = Database::getConnection();
        $id = (int)($data['id'] ?? 0);
        $nama = trim($data['nama_jenis']);

        if ($id > 0) {
            // Kode kategori tidak diubah setelah dibuat
            $stmt = $db->prepare("UPDATE jenis_barang SET nama_jenis = ? WHERE id = ?");
            $stmt->execute([$nama, $id]);
            return $id;
        } else {
            $kode = self::generateNextKodeJenis();
            $stmt = $db->prepare("INSERT INTO jenis_barang (kode_jenis, nama_jenis, satuan_stok, is_active) VALUES (?, ?, 'kg', 1)");
            $stmt->execute([$kode, $nama]);
            return (int)$db->lastInsertId();
        }
    }

    /** Hapus permanen; hanya jika kategori tidak dipakai produk. */
    public static function deleteJenis(int $id): bool {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT kode_jenis, nama_jenis FROM jenis_barang WHERE id = ?");
        $st->execute([$id]);
        $j = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$j) throw new \Exception('Data kategori tidak ditemukan.');
        \App\Services\MasterGuard::pastikanTidakDipakai('jenis', $id, "Kategori {$j['kode_jenis']} {$j['nama_jenis']}");
        return $db->prepare("DELETE FROM jenis_barang WHERE id = ?")->execute([$id]);
    }
}
