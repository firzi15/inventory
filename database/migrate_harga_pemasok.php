<?php
/**
 * Migration: Create master_harga_pemasok table & add profit/loss tracking to transaksi_stok
 */

require_once __DIR__ . '/../app/Config/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';

use App\Core\Database;

try {
    $db = Database::getConnection();
    echo "Connecting to database...\n";

    // 1. Create master_harga_pemasok table
    $sqlMasterHargaPemasok = "
    CREATE TABLE IF NOT EXISTS `master_harga_pemasok` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `pemasok_id` INT NOT NULL,
        `jenis_id` INT NOT NULL,
        `harga_per_kg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        `catatan` VARCHAR(255) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_pemasok_jenis` (`pemasok_id`, `jenis_id`),
        INDEX `idx_mhp_pemasok` (`pemasok_id`),
        INDEX `idx_mhp_jenis` (`jenis_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($sqlMasterHargaPemasok);
    echo "✓ Table master_harga_pemasok ready.\n";

    // 2. Add profit tracking columns to transaksi_stok
    $trxCols = $db->query("SHOW COLUMNS FROM transaksi_stok")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('total_nilai_beli', $trxCols)) {
        $db->exec("ALTER TABLE transaksi_stok ADD COLUMN total_nilai_beli DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER total_label");
        echo "✓ Added total_nilai_beli to transaksi_stok.\n";
    }
    if (!in_array('total_nilai_jual', $trxCols)) {
        $db->exec("ALTER TABLE transaksi_stok ADD COLUMN total_nilai_jual DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER total_nilai_beli");
        echo "✓ Added total_nilai_jual to transaksi_stok.\n";
    }
    if (!in_array('total_profit', $trxCols)) {
        $db->exec("ALTER TABLE transaksi_stok ADD COLUMN total_profit DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER total_nilai_jual");
        echo "✓ Added total_profit to transaksi_stok.\n";
    }

    // 3. Add financial columns to transaksi_stok_detail
    $detailCols = $db->query("SHOW COLUMNS FROM transaksi_stok_detail")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('harga_pemasok', $detailCols)) {
        $db->exec("ALTER TABLE transaksi_stok_detail ADD COLUMN harga_pemasok DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER berat_netto");
        echo "✓ Added harga_pemasok to transaksi_stok_detail.\n";
    }
    if (!in_array('harga_mitra', $detailCols)) {
        $db->exec("ALTER TABLE transaksi_stok_detail ADD COLUMN harga_mitra DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER harga_pemasok");
        echo "✓ Added harga_mitra to transaksi_stok_detail.\n";
    }
    if (!in_array('total_beli', $detailCols)) {
        $db->exec("ALTER TABLE transaksi_stok_detail ADD COLUMN total_beli DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER harga_mitra");
        echo "✓ Added total_beli to transaksi_stok_detail.\n";
    }
    if (!in_array('total_jual', $detailCols)) {
        $db->exec("ALTER TABLE transaksi_stok_detail ADD COLUMN total_jual DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER total_beli");
        echo "✓ Added total_jual to transaksi_stok_detail.\n";
    }
    if (!in_array('profit', $detailCols)) {
        $db->exec("ALTER TABLE transaksi_stok_detail ADD COLUMN profit DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER total_jual");
        echo "✓ Added profit to transaksi_stok_detail.\n";
    }

    // 4. Seed baseline harga pemasok untuk setiap jenis barang
    // Basis harga beli pemasok (biasanya lebih rendah dari harga jual mitra agar profit):
    // PRD01 Udang: Rp 65.000 / kg (Harga jual mitra avg 85.000 - 95.000)
    // PRD02 Cumi:  Rp 55.000 / kg (Harga jual mitra avg 75.000 - 85.000)
    // PRD03 IKAN:  Rp 30.000 / kg (Harga jual mitra avg 45.000 - 55.000)
    // PRD04 TERI:  Rp 42.000 / kg (Harga jual mitra avg 60.000 - 68.000)
    $pemasoks = $db->query("SELECT id, kode_pemasok, nama_pemasok FROM pemasok ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $jenisList = $db->query("SELECT id, kode_jenis, nama_jenis FROM jenis_barang ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

    $baseBuyPrices = [
        'PRD01' => 65000,
        'PRD02' => 55000,
        'PRD03' => 30000,
        'PRD04' => 42000,
        'JB01'  => 85000,
        'JB02'  => 95000,
        'JB03'  => 105000,
        'JB04'  => 40000,
    ];

    // Variasi kecil tiap pemasok agar realistis
    $pemasokVariance = [
        1 => 1.00, // Nelayan Muara Baru (Standard)
        2 => 0.96, // Tambak Bahari (Lebih murah 4%)
        3 => 1.02, // PT Sumber Laut (+2%)
        4 => 0.98, // Pengepul Mina Jaya (-2%)
        5 => 1.00  // UDIN (Standard)
    ];

    $stmtInsert = $db->prepare("
        INSERT INTO master_harga_pemasok (pemasok_id, jenis_id, harga_per_kg, catatan)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            harga_per_kg = VALUES(harga_per_kg),
            catatan = VALUES(catatan)
    ");

    $count = 0;
    foreach ($pemasoks as $pms) {
        $multiplier = $pemasokVariance[$pms['id']] ?? 1.00;
        foreach ($jenisList as $j) {
            $base = $baseBuyPrices[$j['kode_jenis']] ?? 50000;
            $price = round(($base * $multiplier) / 500) * 500;
            $note = "Harga Beli Standar {$pms['nama_pemasok']} - {$j['nama_jenis']}";
            $stmtInsert->execute([$pms['id'], $j['id'], $price, $note]);
            $count++;
        }
    }

    echo "✓ Seeded {$count} records into master_harga_pemasok.\n";
    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
