<?php
/**
 * Model Tipe Biaya Operasional (Master Jenis Beban / Pengeluaran)
 * Mengelola katalog tipe biaya pengeluaran operasional dan pemetaannya ke Chart of Account (COA)
 */

namespace App\Models;

use PDO;
use Exception;
use App\Core\Database;

class TipeBiaya {
    /**
     * Daftar akun COA standar yang dapat digunakan
     */
    public static function getCoaAccounts(): array {
        $kategori = ['EXPENSE' => 'OPERASIONAL', 'COGS' => 'HPP', 'ASSET' => 'ASET'];
        $out = [];
        $akun = \App\Services\CoaService::akunFaktur();
        foreach (array_merge($akun['biaya'], $akun['aset']) as $a) {
            $out[] = ['kode' => $a['kode'], 'nama' => $a['nama'], 'kategori' => $kategori[$a['tipe']] ?? 'OPERASIONAL'];
        }
        return $out;
    }

    /**
     * Dapatkan semua tipe biaya
     */
    public static function getAll(): array {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM master_tipe_biaya ORDER BY kode_biaya ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Dapatkan semua tipe biaya aktif
     */
    public static function getAllActive(): array {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM master_tipe_biaya WHERE is_active = 1 ORDER BY nama_biaya ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cari tipe biaya berdasarkan ID
     */
    public static function findById(int $id): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM master_tipe_biaya WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Cari tipe biaya berdasarkan Kode
     */
    public static function findByKode(string $kode): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM master_tipe_biaya WHERE kode_biaya = ?");
        $stmt->execute([strtoupper(trim($kode))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Buat data tipe biaya baru
     */
    public static function create(array $data): int {
        $db = Database::getConnection();
        $kode = self::generateNextKode();
        $nama = trim($data['nama_biaya'] ?? '');
        if (empty($nama)) {
            throw new Exception("Nama tipe biaya wajib diisi.");
        }

        $existing = self::findByKode($kode);
        if ($existing) {
            throw new Exception("Kode biaya '{$kode}' sudah digunakan.");
        }

        $kodeAkun = trim($data['kode_akun'] ?? '6000') ?: '6000';
        $namaAkun = trim($data['nama_akun'] ?? '');
        if (empty($namaAkun)) {
            $akunRow = \App\Services\CoaService::cari($kodeAkun);
            $namaAkun = $akunRow['nama'] ?? 'Biaya Operasional';
        }

        $stmt = $db->prepare("
            INSERT INTO master_tipe_biaya 
            (kode_biaya, nama_biaya, kategori_beban, kode_akun, nama_akun, satuan_default, estimasi_harga, keterangan, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $kode,
            $nama,
            trim($data['kategori_beban'] ?? 'OPERASIONAL') ?: 'OPERASIONAL',
            $kodeAkun,
            $namaAkun,
            trim($data['satuan_default'] ?? 'Pcs') ?: 'Pcs',
            floatval(str_replace(',', '', (string)($data['estimasi_harga'] ?? 0))),
            trim($data['keterangan'] ?? '') ?: null,
            isset($data['is_active']) ? (int)$data['is_active'] : 1
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Perbarui data tipe biaya
     */
    public static function update(int $id, array $data): bool {
        $db = Database::getConnection();
        $biaya = self::findById($id);
        if (!$biaya) {
            throw new Exception("Data tipe biaya tidak ditemukan.");
        }

        $nama = trim($data['nama_biaya'] ?? '');
        if (empty($nama)) {
            throw new Exception("Nama tipe biaya wajib diisi.");
        }

        $kode = $biaya['kode_biaya']; // kode tidak diubah setelah dibuat
        if ($kode !== $biaya['kode_biaya']) {
            $existing = self::findByKode($kode);
            if ($existing && (int)$existing['id'] !== $id) {
                throw new Exception("Kode biaya '{$kode}' sudah digunakan.");
            }
        }

        $kodeAkun = trim($data['kode_akun'] ?? $biaya['kode_akun']) ?: '6000';
        $namaAkun = trim($data['nama_akun'] ?? '');
        if (empty($namaAkun)) {
            $akunRow = \App\Services\CoaService::cari($kodeAkun);
            $namaAkun = $akunRow['nama'] ?? $biaya['nama_akun'];
        }

        $stmt = $db->prepare("
            UPDATE master_tipe_biaya SET
                kode_biaya = ?,
                nama_biaya = ?,
                kategori_beban = ?,
                kode_akun = ?,
                nama_akun = ?,
                satuan_default = ?,
                estimasi_harga = ?,
                keterangan = ?,
                is_active = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $kode,
            $nama,
            trim($data['kategori_beban'] ?? 'OPERASIONAL') ?: 'OPERASIONAL',
            $kodeAkun,
            $namaAkun,
            trim($data['satuan_default'] ?? 'Pcs') ?: 'Pcs',
            floatval(str_replace(',', '', (string)($data['estimasi_harga'] ?? 0))),
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
        $stmt = $db->prepare("UPDATE master_tipe_biaya SET is_active = ? WHERE id = ?");
        return $stmt->execute([$isActive ? 1 : 0, $id]);
    }

    /**
     * Toggle status aktif / nonaktif
     */
    public static function toggleStatus(int $id): int {
        $biaya = self::findById($id);
        if (!$biaya) {
            throw new Exception("Data tipe biaya tidak ditemukan.");
        }
        $newStatus = $biaya['is_active'] ? 0 : 1;
        self::updateStatus($id, $newStatus);
        return $newStatus;
    }

    /**
     * Hapus tipe biaya jika belum digunakan pada faktur pembelian
     */
    public static function delete(int $id): bool {
        $db = Database::getConnection();
        $biaya = self::findById($id);
        if (!$biaya) {
            throw new Exception("Data tipe biaya tidak ditemukan.");
        }

        // Cek pemakaian pada purchase_invoice_items
        $check = $db->query("SHOW TABLES LIKE 'purchase_invoice_items'")->fetch();
        if ($check) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM purchase_invoice_items WHERE deskripsi LIKE ?");
            $stmt->execute(['%' . $biaya['nama_biaya'] . '%']);
            $cnt = (int)$stmt->fetchColumn();
            if ($cnt > 0) {
                throw new Exception("Tipe biaya [{$biaya['kode_biaya']}] {$biaya['nama_biaya']} pernah digunakan pada rincian faktur pembelian. Silakan gunakan tombol Status untuk menonaktifkan.");
            }
        }

        $stmt = $db->prepare("DELETE FROM master_tipe_biaya WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Generate kode biaya berikutnya otomatis: BY-01, BY-02, dst
     */
    public static function generateNextKode(): string {
        return \App\Core\KodeGenerator::next('master_tipe_biaya', 'kode_biaya', 'BY', 2, '-');
    }
}
