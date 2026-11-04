<?php
/**
 * Model Lokasi Gudang (gudang_lokasi)
 * Mengelola master lokasi penyimpanan kamar pendingin, rak, office, dan titik transit
 */

namespace App\Models;

use App\Core\Database;
use PDO;

class Lokasi {
    public static function getAll(): array {
        $db = Database::getConnection();
        return $db->query("SELECT * FROM gudang_lokasi WHERE is_active = 1 ORDER BY kode_lokasi ASC")->fetchAll();
    }

    public static function findById(int $id): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM gudang_lokasi WHERE id = ?");
        $stmt->execute([$id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public static function create(array $data): int {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO gudang_lokasi (kode_lokasi, nama_lokasi, gudang_induk, suhu_ruang, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            strtoupper(trim($data['kode_lokasi'])),
            trim($data['nama_lokasi']),
            trim($data['gudang_induk'] ?? 'Gudang Utama'),
            trim($data['suhu_ruang'] ?? 'Ambient (Normal)')
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE gudang_lokasi
            SET kode_lokasi = ?, nama_lokasi = ?, gudang_induk = ?, suhu_ruang = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            strtoupper(trim($data['kode_lokasi'])),
            trim($data['nama_lokasi']),
            trim($data['gudang_induk'] ?? 'Gudang Utama'),
            trim($data['suhu_ruang'] ?? 'Ambient (Normal)'),
            $id
        ]);
    }

    public static function delete(int $id): bool {
        $db = Database::getConnection();
        // Cek apakah lokasi pernah digunakan di tabel labels
        $count = $db->prepare("SELECT COUNT(*) FROM labels WHERE lokasi_id = ?");
        $count->execute([$id]);
        if ((int)$count->fetchColumn() > 0) {
            // Soft delete jika ada referensi
            $stmt = $db->prepare("UPDATE gudang_lokasi SET is_active = 0 WHERE id = ?");
            return $stmt->execute([$id]);
        }
        // Hard delete jika belum pernah digunakan
        $stmt = $db->prepare("DELETE FROM gudang_lokasi WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
