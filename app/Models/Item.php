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

    public static function create(array $data): int {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO items (kode_item, nama_item, jenis_id, default_tara_kg, berat_standar_kg, satuan_accurate, is_active)
            VALUES (?, ?, ?, 0.000, NULL, ?, 1)
        ");
        $stmt->execute([
            strtoupper(trim($data['kode_item'])),
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

    public static function delete(int $id): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE items SET is_active = 0 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function saveJenis(array $data): int {
        $db = Database::getConnection();
        $id = (int)($data['id'] ?? 0);
        $kode = strtoupper(trim($data['kode_jenis']));
        $nama = trim($data['nama_jenis']);

        if ($id > 0) {
            $stmt = $db->prepare("UPDATE jenis_barang SET kode_jenis = ?, nama_jenis = ? WHERE id = ?");
            $stmt->execute([$kode, $nama, $id]);
            return $id;
        } else {
            $stmt = $db->prepare("INSERT INTO jenis_barang (kode_jenis, nama_jenis, satuan_stok, is_active) VALUES (?, ?, 'kg', 1)");
            $stmt->execute([$kode, $nama]);
            return (int)$db->lastInsertId();
        }
    }

    public static function deleteJenis(int $id): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE jenis_barang SET is_active = 0 WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
