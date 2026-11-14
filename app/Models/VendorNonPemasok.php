<?php
/**
 * Model Vendor Non-Pemasok
 * Mengelola data rekanan vendor operasional (Es Balok, Styrofoam, Solar, Perawatan Mesin, Ekspedisi, dll)
 * Terpisah dari Pemasok Nelayan/Ikan
 */

namespace App\Models;

use PDO;
use Exception;
use App\Core\Database;

class VendorNonPemasok {
    /**
     * Dapatkan semua vendor non-pemasok
     */
    public static function getAll(): array {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM vendor_non_pemasok ORDER BY kode_vendor ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Dapatkan semua vendor non-pemasok aktif
     */
    public static function getAllActive(): array {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM vendor_non_pemasok WHERE is_active = 1 ORDER BY nama_vendor ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cari vendor berdasarkan ID
     */
    public static function findById(int $id): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM vendor_non_pemasok WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Cari vendor berdasarkan Kode
     */
    public static function findByKode(string $kode): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM vendor_non_pemasok WHERE kode_vendor = ?");
        $stmt->execute([strtoupper(trim($kode))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Buat data vendor baru
     */
    public static function create(array $data): int {
        $db = Database::getConnection();
        $kode = self::generateNextKode();
        $nama = trim($data['nama_vendor'] ?? '');
        if (empty($nama)) {
            throw new Exception("Nama vendor wajib diisi.");
        }

        // Cek duplikasi kode
        $existing = self::findByKode($kode);
        if ($existing) {
            throw new Exception("Kode vendor '{$kode}' sudah digunakan oleh {$existing['nama_vendor']}.");
        }

        $stmt = $db->prepare("
            INSERT INTO vendor_non_pemasok 
            (kode_vendor, nama_vendor, kategori_vendor, pic_nama, telepon, alamat, bank, no_rekening, nama_rekening, keterangan, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $kode,
            $nama,
            trim($data['kategori_vendor'] ?? 'OPERASIONAL') ?: 'OPERASIONAL',
            trim($data['pic_nama'] ?? '') ?: null,
            trim($data['telepon'] ?? '') ?: null,
            trim($data['alamat'] ?? '') ?: null,
            trim($data['bank'] ?? '') ?: null,
            trim($data['no_rekening'] ?? '') ?: null,
            trim($data['nama_rekening'] ?? '') ?: null,
            trim($data['keterangan'] ?? '') ?: null,
            isset($data['is_active']) ? (int)$data['is_active'] : 1
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Perbarui data vendor
     */
    public static function update(int $id, array $data): bool {
        $db = Database::getConnection();
        $vendor = self::findById($id);
        if (!$vendor) {
            throw new Exception("Vendor tidak ditemukan.");
        }

        $nama = trim($data['nama_vendor'] ?? '');
        if (empty($nama)) {
            throw new Exception("Nama vendor wajib diisi.");
        }

        $kode = $vendor['kode_vendor']; // kode tidak diubah setelah dibuat
        if ($kode !== $vendor['kode_vendor']) {
            $existing = self::findByKode($kode);
            if ($existing && (int)$existing['id'] !== $id) {
                throw new Exception("Kode vendor '{$kode}' sudah digunakan.");
            }
        }

        $stmt = $db->prepare("
            UPDATE vendor_non_pemasok SET
                kode_vendor = ?,
                nama_vendor = ?,
                kategori_vendor = ?,
                pic_nama = ?,
                telepon = ?,
                alamat = ?,
                bank = ?,
                no_rekening = ?,
                nama_rekening = ?,
                keterangan = ?,
                is_active = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $kode,
            $nama,
            trim($data['kategori_vendor'] ?? 'OPERASIONAL') ?: 'OPERASIONAL',
            trim($data['pic_nama'] ?? '') ?: null,
            trim($data['telepon'] ?? '') ?: null,
            trim($data['alamat'] ?? '') ?: null,
            trim($data['bank'] ?? '') ?: null,
            trim($data['no_rekening'] ?? '') ?: null,
            trim($data['nama_rekening'] ?? '') ?: null,
            trim($data['keterangan'] ?? '') ?: null,
            isset($data['is_active']) ? (int)$data['is_active'] : 1,
            $id
        ]);
    }

    /**
     * Update status aktif / nonaktif
     */
    public static function updateStatus(int $id, int $isActive): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE vendor_non_pemasok SET is_active = ? WHERE id = ?");
        return $stmt->execute([$isActive ? 1 : 0, $id]);
    }

    /**
     * Toggle status aktif / nonaktif
     */
    public static function toggleStatus(int $id): int {
        $vendor = self::findById($id);
        if (!$vendor) {
            throw new Exception("Data vendor tidak ditemukan.");
        }
        $newStatus = $vendor['is_active'] ? 0 : 1;
        self::updateStatus($id, $newStatus);
        return $newStatus;
    }

    /**
     * Hitung pemakaian vendor di Purchase Invoice
     */
    public static function countInvoices(int $id): int {
        $db = Database::getConnection();
        $vendor = self::findById($id);
        if (!$vendor) return 0;

        $check = $db->query("SHOW TABLES LIKE 'purchase_invoices'")->fetch();
        if (!$check) return 0;

        $stmt = $db->prepare("SELECT COUNT(*) FROM purchase_invoices WHERE (pemasok_id = ? AND tipe_invoice != 'STOK') OR nama_vendor = ?");
        $stmt->execute([$id, $vendor['nama_vendor']]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Hapus vendor jika belum ada transaksi purchase invoice
     */
    public static function delete(int $id): bool {
        $db = Database::getConnection();
        $vendor = self::findById($id);
        if (!$vendor) {
            throw new Exception("Data vendor tidak ditemukan.");
        }

        $invCount = self::countInvoices($id);
        if ($invCount > 0) {
            throw new Exception("Vendor [{$vendor['kode_vendor']}] {$vendor['nama_vendor']} tidak dapat dihapus karena sudah tercatat dalam {$invCount} transaksi faktur pembelian operasional. Silakan nonaktifkan status vendor jika sudah tidak digunakan.");
        }

        $stmt = $db->prepare("DELETE FROM vendor_non_pemasok WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Generate kode vendor berikutnya otomatis: VND-01, VND-02, dst
     */
    public static function generateNextKode(): string {
        return \App\Core\KodeGenerator::next('vendor_non_pemasok', 'kode_vendor', 'VND', 2, '-');
    }
}
