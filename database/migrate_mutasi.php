<?php
/**
 * Migrasi Database: Penambahan Fitur Mutasi, BAST, dan Integrasi POS
 */

require_once __DIR__ . '/../app/Config/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';

use App\Core\Database;

try {
    $db = Database::getConnection();

    // 1. Tambah kolom pada tabel transaksi_stok
    $alterStatements = [
        "ALTER TABLE `transaksi_stok` ADD COLUMN `alur_pergerakan` ENUM('PENGEPUL_KE_GUDANG', 'GUDANG_KE_OFFICE', 'KE_PEMBELI_POS', 'MUTASI_LOKASI') NOT NULL DEFAULT 'MUTASI_LOKASI' AFTER `jenis_transaksi`",
        "ALTER TABLE `transaksi_stok` ADD COLUMN `no_bast` VARCHAR(100) NULL AFTER `no_transaksi`",
        "ALTER TABLE `transaksi_stok` ADD COLUMN `nama_penyerah` VARCHAR(150) NULL AFTER `user_id`",
        "ALTER TABLE `transaksi_stok` ADD COLUMN `nama_penerima` VARCHAR(150) NULL AFTER `nama_penyerah`",
        "ALTER TABLE `transaksi_stok` ADD COLUMN `user_penerima_id` INT NULL AFTER `nama_penerima`",
        "ALTER TABLE `transaksi_stok` ADD COLUMN `catatan_serah_terima` TEXT NULL AFTER `keterangan`"
    ];

    foreach ($alterStatements as $sql) {
        try {
            $db->exec($sql);
        } catch (\PDOException $e) {
            // Kolom mungkin sudah ada, lanjutkan
        }
    }

    // 2. Tambah data master lokasi baru (Office/POS, Pengepul, Customer POS)
    $db->exec("
        INSERT INTO `gudang_lokasi` (`kode_lokasi`, `nama_lokasi`, `gudang_induk`, `suhu_ruang`) VALUES
        ('GDG-OFFICE-POS', 'Office & Display Toko POS', 'Office Utama', 'AC Ruang (20°C - 24°C)'),
        ('SRC-PENGEPUL-01', 'Pengepul / Peternak Mitra Jawa Barat', 'Pemasok Pengepul', 'Area Penerimaan Luar'),
        ('DEST-CUSTOMER-POS', 'Pelanggan / Kasir POS Accurate', 'POS Customer', 'Langsung Serah Terima')
        ON DUPLICATE KEY UPDATE `nama_lokasi`=VALUES(`nama_lokasi`);
    ");

    echo "MIGRATION_MUTASI_OK" . PHP_EOL;
} catch (\Throwable $e) {
    echo "MIGRATION_ERROR: " . $e->getMessage() . PHP_EOL;
}
