<?php
require_once __DIR__ . '/../app/Config/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';

use App\Core\Database;

try {
    $db = Database::getConnection();
    echo "=== MIGRATION: MASTER HARGA PEMASOK PER PRODUK (ITEM SKU) ===\n";

    // 1. Cek apakah kolom item_id sudah ada di master_harga_pemasok
    $cols = $db->query("SHOW COLUMNS FROM master_harga_pemasok LIKE 'item_id'")->fetchAll();
    if (empty($cols)) {
        echo "Menambahkan kolom item_id ke tabel master_harga_pemasok...\n";
        
        // Buat tabel backup sementara jika perlu
        $db->exec("CREATE TABLE IF NOT EXISTS master_harga_pemasok_old_jenis AS SELECT * FROM master_harga_pemasok");
        
        // Bersihkan data lama untuk rekonstruksi per item
        $db->exec("TRUNCATE TABLE master_harga_pemasok");

        // Ubah jenis_id menjadi nullable
        $db->exec("ALTER TABLE master_harga_pemasok MODIFY jenis_id INT(11) NULL");

        // Tambah item_id
        $db->exec("ALTER TABLE master_harga_pemasok ADD COLUMN item_id INT(11) NOT NULL AFTER pemasok_id");

        // Hapus indeks unik lama jika ada
        try {
            $db->exec("ALTER TABLE master_harga_pemasok DROP INDEX uk_pemasok_jenis");
        } catch (Exception $e) {
            // Abaikan jika tidak ada
        }

        // Tambah indeks baru uk_pemasok_item
        $db->exec("ALTER TABLE master_harga_pemasok ADD UNIQUE KEY uk_pemasok_item (pemasok_id, item_id)");
        $db->exec("ALTER TABLE master_harga_pemasok ADD CONSTRAINT fk_mhp_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE");

        echo "Struktur tabel master_harga_pemasok berhasil disesuaikan dengan item_id!\n";
    } else {
        echo "Kolom item_id sudah ada di master_harga_pemasok.\n";
    }

    // 2. Populasi harga beli default per produk untuk setiap pemasok
    $pemasoks = $db->query("SELECT id, kode_pemasok, nama_pemasok FROM pemasok ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $items = $db->query("SELECT i.id, i.kode_item, i.nama_item, i.jenis_id, j.nama_jenis FROM items i LEFT JOIN jenis_barang j ON i.jenis_id = j.id ORDER BY i.id ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Ambil referensi harga lama jika ada
    $oldPrices = [];
    try {
        $oldRows = $db->query("SELECT pemasok_id, jenis_id, harga_per_kg FROM master_harga_pemasok_old_jenis")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($oldRows as $r) {
            $oldPrices[$r['pemasok_id']][$r['jenis_id']] = (float)$r['harga_per_kg'];
        }
    } catch (Exception $e) {}

    $stmtInsert = $db->prepare("
        INSERT INTO master_harga_pemasok (pemasok_id, item_id, jenis_id, harga_per_kg, catatan)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            jenis_id = VALUES(jenis_id),
            harga_per_kg = IF(harga_per_kg <= 0, VALUES(harga_per_kg), harga_per_kg),
            updated_at = CURRENT_TIMESTAMP
    ");

    $count = 0;
    foreach ($pemasoks as $p) {
        foreach ($items as $it) {
            $jId = (int)$it['jenis_id'];
            $defaultPrice = $oldPrices[$p['id']][$jId] ?? 0.0;

            if ($defaultPrice <= 0) {
                // Fallback default berdasarkan nama item
                if (stripos($it['nama_item'], 'udang') !== false) {
                    $defaultPrice = 65000.00;
                } elseif (stripos($it['nama_item'], 'cumi') !== false) {
                    $defaultPrice = 55000.00;
                } elseif (stripos($it['nama_item'], 'rebon') !== false) {
                    $defaultPrice = 25000.00;
                } elseif (stripos($it['nama_item'], 'teri') !== false) {
                    $defaultPrice = 40000.00;
                } else {
                    $defaultPrice = 30000.00;
                }
            }

            $catatan = "Harga Beli Standar {$p['nama_pemasok']} - {$it['nama_item']}";
            $stmtInsert->execute([$p['id'], $it['id'], $jId, $defaultPrice, $catatan]);
            $count++;
        }
    }

    echo "Berhasil mempopulasi {$count} kombinasi harga pemasok per produk SKU!\n";
    echo "=== MIGRATION COMPLETED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    echo "ERROR MIGRATION: " . $e->getMessage() . "\n";
    exit(1);
}
