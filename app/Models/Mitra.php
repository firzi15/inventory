<?php
/**
 * Model Mitra Bisnis (M01 - M07)
 */

namespace App\Models;

use PDO;
use App\Core\Database;

class Mitra {
    public static function getAll(): array {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM mitra ORDER BY kode_mitra ASC");
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM mitra WHERE id = ?");
        $stmt->execute([$id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public static function findByKode(string $kode): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM mitra WHERE kode_mitra = ?");
        $stmt->execute([strtoupper(trim($kode))]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public static function getStockSummaryPerMitra(): array {
        $db = Database::getConnection();
        $sql = "
            SELECT 
                m.id AS mitra_id,
                m.kode_mitra,
                m.nama_mitra,
                m.kode_gudang_accurate,
                m.warna_identitas,
                COUNT(l.id) AS total_kemasan,
                COALESCE(SUM(l.berat_netto), 0) AS total_kg
            FROM mitra m
            LEFT JOIN labels l ON m.id = l.mitra_id AND l.status = 'AKTIF'
            WHERE m.is_active = 1
            GROUP BY m.id, m.kode_mitra, m.nama_mitra, m.kode_gudang_accurate, m.warna_identitas
            ORDER BY m.kode_mitra ASC
        ";
        return $db->query($sql)->fetchAll();
    }

    /** Kode mitra otomatis: M01, M02, ... */
    public static function generateNextKode(): string {
        return \App\Core\KodeGenerator::next('mitra', 'kode_mitra', 'M', 2, '');
    }

    public static function create(array $data): int {
        $db = Database::getConnection();
        $kode = self::generateNextKode();
        $namaBersih = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($data['nama_mitra'])));
        $stmt = $db->prepare("
            INSERT INTO mitra (kode_mitra, nama_mitra, pic_nama, pic_telepon, kode_gudang_accurate, warna_identitas, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $kode,
            trim($data['nama_mitra']),
            trim($data['pic_nama'] ?? ''),
            trim($data['pic_telepon'] ?? ''),
            strtoupper(trim($data['kode_gudang_accurate'] ?? '')) ?: ('GDG-' . $kode . '-' . $namaBersih),
            $data['warna_identitas'] ?? '#3b82f6'
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE mitra 
            SET nama_mitra = ?, pic_nama = ?, pic_telepon = ?, kode_gudang_accurate = ?, warna_identitas = ?, is_active = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            trim($data['nama_mitra']),
            trim($data['pic_nama'] ?? ''),
            trim($data['pic_telepon'] ?? ''),
            strtoupper(trim($data['kode_gudang_accurate'])),
            $data['warna_identitas'] ?? '#3b82f6',
            isset($data['is_active']) ? (int)$data['is_active'] : 1,
            $id
        ]);
    }

    /** Hapus permanen; hanya jika mitra tidak dipakai di menu lain. */
    public static function delete(int $id): bool {
        $db = Database::getConnection();
        $m = self::findById($id);
        if (!$m) throw new \Exception('Data mitra tidak ditemukan.');
        \App\Services\MasterGuard::pastikanTidakDipakai('mitra', $id, "Mitra {$m['kode_mitra']} {$m['nama_mitra']}");
        $db->prepare("DELETE FROM master_harga WHERE mitra_id = ?")->execute([$id]);
        return $db->prepare("DELETE FROM mitra WHERE id = ?")->execute([$id]);
    }
}
