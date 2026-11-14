<?php
/**
 * Model Pemasok (Supplier)
 * Mengelola data pemasok barang masuk (Nelayan, Tambak, Pengepul, Vendor)
 */

namespace App\Models;

use PDO;
use Exception;
use App\Core\Database;

class Pemasok {
    /**
     * Dapatkan semua pemasok
     */
    public static function getAll(): array {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM pemasok ORDER BY kode_pemasok ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Dapatkan semua pemasok aktif
     */
    public static function getAllActive(): array {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM pemasok WHERE is_active = 1 ORDER BY kode_pemasok ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cari pemasok berdasarkan ID
     */
    public static function findById(int $id): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM pemasok WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Cari pemasok berdasarkan Kode
     */
    public static function findByKode(string $kode): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM pemasok WHERE kode_pemasok = ?");
        $stmt->execute([strtoupper(trim($kode))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Buat data pemasok baru
     */
    public static function create(array $data): int {
        $db = Database::getConnection();
        // Kode pemasok diisi manual (dipakai sebagai awalan barcode label); otomatis hanya jika dikosongkan
        $kode = !empty($data['kode_pemasok']) ? strtoupper(trim($data['kode_pemasok'])) : self::generateNextKode();
        if (self::findByKode($kode)) {
            throw new Exception("Kode pemasok '{$kode}' sudah dipakai pemasok lain.");
        }
        $nama = trim($data['nama_pemasok'] ?? '');
        if (empty($nama)) {
            throw new Exception("Nama pemasok wajib diisi.");
        }

        $stmt = $db->prepare("
            INSERT INTO pemasok (kode_pemasok, nama_pemasok, pic_nama, telepon, alamat, keterangan, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $kode,
            $nama,
            trim($data['pic_nama'] ?? '') ?: null,
            trim($data['telepon'] ?? '') ?: null,
            trim($data['alamat'] ?? '') ?: null,
            trim($data['keterangan'] ?? '') ?: null,
            isset($data['is_active']) ? (int)$data['is_active'] : 1
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Perbarui data pemasok
     */
    public static function update(int $id, array $data): bool {
        $db = Database::getConnection();
        $nama = trim($data['nama_pemasok'] ?? '');
        if (empty($nama)) {
            throw new Exception("Nama pemasok wajib diisi.");
        }

        $kode = !empty($data['kode_pemasok']) ? strtoupper(trim($data['kode_pemasok'])) : null;
        if ($kode) {
            $dipakai = self::findByKode($kode);
            if ($dipakai && (int)$dipakai['id'] !== $id) {
                throw new Exception("Kode pemasok '{$kode}' sudah dipakai pemasok lain.");
            }
        }

        $fields = [
            "nama_pemasok = ?",
            "pic_nama = ?",
            "telepon = ?",
            "alamat = ?",
            "keterangan = ?",
            "is_active = ?"
        ];
        $params = [
            $nama,
            trim($data['pic_nama'] ?? '') ?: null,
            trim($data['telepon'] ?? '') ?: null,
            trim($data['alamat'] ?? '') ?: null,
            trim($data['keterangan'] ?? '') ?: null,
            isset($data['is_active']) ? (int)$data['is_active'] : 1
        ];

        if ($kode) {
            $fields[] = "kode_pemasok = ?";
            $params[] = $kode;
        }

        $params[] = $id;

        $sql = "UPDATE pemasok SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Update status aktif / nonaktif pemasok
     */
    public static function updateStatus(int $id, int $isActive): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE pemasok SET is_active = ? WHERE id = ?");
        return $stmt->execute([$isActive ? 1 : 0, $id]);
    }

    /**
     * Toggle status aktif / nonaktif pemasok (mengembalikan status baru: 1 atau 0)
     */
    public static function toggleStatus(int $id): int {
        $pemasok = self::findById($id);
        if (!$pemasok) {
            throw new Exception("Data pemasok tidak ditemukan.");
        }
        $newStatus = $pemasok['is_active'] ? 0 : 1;
        self::updateStatus($id, $newStatus);
        return $newStatus;
    }

    /**
     * Hitung total kemasan / label penerimaan barang masuk dari pemasok ini
     */
    public static function countIncomingGoods(int $id): int {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM labels WHERE pemasok_id = ?");
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Hitung total transaksi pergerakan stok pemasok ini
     */
    public static function countTransactions(int $id): int {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM transaksi_stok WHERE pemasok_id = ?");
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Hapus pemasok permanen jika belum ada barang masuk.
     * Jika sudah ada barang masuk, lempar Exception agar tidak dapat dihapus.
     */
    public static function delete(int $id): bool {
        $db = Database::getConnection();
        $pemasok = self::findById($id);
        if (!$pemasok) {
            throw new Exception("Data pemasok tidak ditemukan.");
        }

        \App\Services\MasterGuard::pastikanTidakDipakai('pemasok', $id, "Pemasok [{$pemasok['kode_pemasok']}] {$pemasok['nama_pemasok']}");

        // Hapus referensi harga pemasok jika ada
        $stmtDelMhp = $db->prepare("DELETE FROM master_harga_pemasok WHERE pemasok_id = ?");
        $stmtDelMhp->execute([$id]);

        $checkOld = $db->query("SHOW TABLES LIKE 'master_harga_pemasok_old_jenis'")->fetch();
        if ($checkOld) {
            $stmtDelOld = $db->prepare("DELETE FROM master_harga_pemasok_old_jenis WHERE pemasok_id = ?");
            $stmtDelOld->execute([$id]);
        }

        $stmt = $db->prepare("DELETE FROM pemasok WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Generate kode pemasok berikutnya otomatis: PMS-01, PMS-02, dst
     */
    public static function generateNextKode(): string {
        return \App\Core\KodeGenerator::next('pemasok', 'kode_pemasok', 'PMS', 2, '-');
    }
}
