<?php
/**
 * Migration: Create master_harga table and update labels table for Surat Jalan & Pricing
 */

require_once __DIR__ . '/../app/Config/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';

use App\Core\Database;

try {
    $db = Database::getConnection();
    $driver = Database::getDriver();
    echo "Connecting using driver: {$driver}...\n";

    // 1. Create master_harga table
    $sqlMasterHarga = "
    CREATE TABLE IF NOT EXISTS master_harga (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mitra_id INT NOT NULL,
        item_id INT NOT NULL,
        harga_per_kg DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        catatan VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_mitra_item (mitra_id, item_id)
    ) ENGINE=InnoDB;
    ";
    $db->exec($sqlMasterHarga);
    echo "✓ Table master_harga ready.\n";

    // 2. Add columns to labels table if not exists
    $columns = $db->query("SHOW COLUMNS FROM labels")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('no_surat_jalan', $columns)) {
        $db->exec("ALTER TABLE labels ADD COLUMN no_surat_jalan VARCHAR(100) NULL AFTER batch_no");
        echo "✓ Column no_surat_jalan added to labels.\n";
    }
    if (!in_array('harga_per_kg', $columns)) {
        $db->exec("ALTER TABLE labels ADD COLUMN harga_per_kg DECIMAL(12, 2) NOT NULL DEFAULT 0.00 AFTER berat_netto");
        echo "✓ Column harga_per_kg added to labels.\n";
    }
    if (!in_array('total_nilai', $columns)) {
        $db->exec("ALTER TABLE labels ADD COLUMN total_nilai DECIMAL(14, 2) NOT NULL DEFAULT 0.00 AFTER harga_per_kg");
        echo "✓ Column total_nilai added to labels.\n";
    }

    // Populate existing labels with default surat jalan from batch_no
    $db->exec("UPDATE labels SET no_surat_jalan = batch_no WHERE no_surat_jalan IS NULL OR no_surat_jalan = ''");

    // 3. Seed Master Harga for 7 Mitra & Items
    $mitras = $db->query("SELECT id, kode_mitra FROM mitra ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $items  = $db->query("SELECT id, kode_item, nama_item FROM items ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Baseline pricing map for products
    $basePrice = [
        'SKU-MEAT-001' => 120000, // Sirloin
        'SKU-MEAT-002' => 145000, // Tenderloin
        'SKU-MEAT-003' => 135000, // Rib Eye
        'SKU-MEAT-004' => 85000,  // Daging Giling
        'SKU-MEAT-005' => 160000, // Oxtail / Buntut
    ];

    // Multipliers / variance for each of the 7 partners to ensure distinct pricing
    $mitraVariance = [
        'M01' => 1.00, // Normal Base
        'M02' => 1.04, // +4%
        'M03' => 0.98, // -2% (Volume discount)
        'M04' => 1.02, // +2%
        'M05' => 1.08, // +8% (Premium contract)
        'M06' => 0.96, // -4% (Special wholesale)
        'M07' => 1.06  // +6%
    ];

    $insertStmt = $db->prepare("
        INSERT INTO master_harga (mitra_id, item_id, harga_per_kg, catatan)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            harga_per_kg = VALUES(harga_per_kg),
            catatan = VALUES(catatan)
    ");

    $count = 0;
    foreach ($mitras as $m) {
        $multiplier = $mitraVariance[$m['kode_mitra']] ?? 1.00;
        foreach ($items as $it) {
            $base = $basePrice[$it['kode_item']] ?? 100000;
            $finalPrice = round(($base * $multiplier) / 500) * 500; // Round to nearest 500
            $note = "Kontrak Harga {$m['kode_mitra']} - {$it['nama_item']}";

            $insertStmt->execute([$m['id'], $it['id'], $finalPrice, $note]);
            $count++;
        }
    }

    echo "✓ Seeded {$count} master harga records across 7 Mitra successfully!\n";
    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
