<?php
/**
 * Model Master Harga Pemasok per Produk (Item SKU)
 * Mengelola harga beli persediaan per Kg untuk masing-masing pemasok (supplier) berdasarkan produk SKU
 */

namespace App\Models;

use PDO;
use Exception;
use App\Core\Database;
use App\Models\Pemasok;
use App\Models\Item;

class MasterHargaPemasok {
    /**
     * Dapatkan semua data harga pemasok dengan detail produk SKU dan pemasok
     */
    public static function getAllWithDetails(?int $filterPemasokId = null): array {
        $db = Database::getConnection();
        $sql = "
            SELECT 
                h.id,
                h.pemasok_id,
                h.item_id,
                h.jenis_id,
                h.harga_per_kg,
                h.catatan,
                h.updated_at,
                p.kode_pemasok,
                p.nama_pemasok,
                i.kode_item,
                i.nama_item,
                j.kode_jenis,
                j.nama_jenis,
                j.satuan_stok
            FROM master_harga_pemasok h
            JOIN pemasok p ON h.pemasok_id = p.id
            JOIN items i ON h.item_id = i.id
            LEFT JOIN jenis_barang j ON i.jenis_id = j.id
        ";

        if ($filterPemasokId) {
            $sql .= " WHERE h.pemasok_id = ? ORDER BY i.id ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$filterPemasokId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $sql .= " ORDER BY p.kode_pemasok ASC, i.id ASC";
        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Dapatkan harga per kg berdasarkan Pemasok dan Item SKU
     */
    public static function getHargaByPemasokAndItem(int $pemasokId, int $itemId): float {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT harga_per_kg FROM master_harga_pemasok WHERE pemasok_id = ? AND item_id = ?");
        $stmt->execute([$pemasokId, $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && (float)$row['harga_per_kg'] > 0) {
            return (float)$row['harga_per_kg'];
        }

        // Fallback jika belum terset per item: cari berdasarkan jenis_id item tersebut
        $stmtFallback = $db->prepare("
            SELECT mhp.harga_per_kg 
            FROM items i 
            JOIN master_harga_pemasok mhp ON mhp.jenis_id = i.jenis_id AND mhp.pemasok_id = ?
            WHERE i.id = ? AND mhp.harga_per_kg > 0
            LIMIT 1
        ");
        $stmtFallback->execute([$pemasokId, $itemId]);
        $fbRow = $stmtFallback->fetch(PDO::FETCH_ASSOC);
        return $fbRow ? (float)$fbRow['harga_per_kg'] : 0.00;
    }

    /**
     * Simpan atau perbarui harga satu produk untuk pemasok tertentu
     */
    public static function saveHarga(int $pemasokId, int $itemId, float $hargaPerKg, ?string $catatan = null): bool {
        $db = Database::getConnection();
        
        // Ambil jenis_id dari item
        $stmtItem = $db->prepare("SELECT jenis_id FROM items WHERE id = ?");
        $stmtItem->execute([$itemId]);
        $item = $stmtItem->fetch(PDO::FETCH_ASSOC);
        $jenisId = $item ? (int)$item['jenis_id'] : null;

        $stmt = $db->prepare("
            INSERT INTO master_harga_pemasok (pemasok_id, item_id, jenis_id, harga_per_kg, catatan)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                jenis_id = VALUES(jenis_id),
                harga_per_kg = VALUES(harga_per_kg),
                catatan = VALUES(catatan),
                updated_at = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$pemasokId, $itemId, $jenisId, $hargaPerKg, $catatan]);
    }

    /**
     * Matriks harga pemasok: Produk SKU x Seluruh Pemasok untuk tabel dan bulk-edit
     */
    public static function getHargaMatrix(): array {
        $db = Database::getConnection();
        $pemasoks = Pemasok::getAllActive();
        if (empty($pemasoks)) {
            $pemasoks = Pemasok::getAll();
        }
        $itemList = Item::getAll();

        $rawPrices = $db->query("SELECT pemasok_id, item_id, harga_per_kg FROM master_harga_pemasok")->fetchAll(PDO::FETCH_ASSOC);
        $lookup = [];
        foreach ($rawPrices as $rp) {
            $lookup[$rp['item_id']][$rp['pemasok_id']] = (float)$rp['harga_per_kg'];
        }

        $matrix = [];
        foreach ($itemList as $it) {
            $row = [
                'item' => $it,
                'prices' => []
            ];
            foreach ($pemasoks as $p) {
                $row['prices'][$p['id']] = $lookup[$it['id']][$p['id']] ?? 0.00;
            }
            $matrix[] = $row;
        }

        return [
            'pemasoks' => $pemasoks,
            'matrix' => $matrix
        ];
    }

    /**
     * Dapatkan peta JSON harga pemasok untuk perhitungan instan di front-end
     * Format: { "pemasokId_itemId": nominal }
     */
    public static function getAllPricesJson(): string {
        $db = Database::getConnection();
        $raw = $db->query("SELECT pemasok_id, item_id, harga_per_kg FROM master_harga_pemasok")->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($raw as $r) {
            $key = $r['pemasok_id'] . '_' . $r['item_id'];
            $map[$key] = (float)$r['harga_per_kg'];
        }
        return json_encode($map);
    }

    /**
     * Analisa Profit & Margin: Komparasi Harga Beli Pemasok vs Harga Jual Mitra per Produk
     */
    public static function getProfitLossSummary(?int $filterPemasokId = null, ?int $filterMitraId = null, ?string $search = null): array {
        $db = Database::getConnection();
        $where = ["p.is_active = 1", "m.is_active = 1"];
        $params = [];

        if ($filterPemasokId) {
            $where[] = "p.id = ?";
            $params[] = $filterPemasokId;
        }
        if ($filterMitraId) {
            $where[] = "m.id = ?";
            $params[] = $filterMitraId;
        }
        if (!empty($search)) {
            $kw = '%' . trim($search) . '%';
            $where[] = "(i.nama_item LIKE ? OR i.kode_item LIKE ? OR p.nama_pemasok LIKE ? OR m.nama_mitra LIKE ?)";
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT 
                p.id AS pemasok_id,
                p.kode_pemasok,
                p.nama_pemasok,
                m.id AS mitra_id,
                m.kode_mitra,
                m.nama_mitra,
                m.warna_identitas,
                i.id AS item_id,
                i.kode_item,
                i.nama_item,
                j.id AS jenis_id,
                j.nama_jenis,
                COALESCE(mhp.harga_per_kg, 0.00) AS harga_pemasok,
                COALESCE(mhm.harga_per_kg, 0.00) AS harga_mitra,
                (COALESCE(mhm.harga_per_kg, 0.00) - COALESCE(mhp.harga_per_kg, 0.00)) AS profit_per_kg,
                CASE 
                    WHEN COALESCE(mhp.harga_per_kg, 0) > 0 
                    THEN ROUND(((COALESCE(mhm.harga_per_kg, 0) - COALESCE(mhp.harga_per_kg, 0)) / COALESCE(mhp.harga_per_kg, 0)) * 100, 1)
                    ELSE 0.0 
                END AS margin_persen
            FROM items i
            JOIN jenis_barang j ON i.jenis_id = j.id
            CROSS JOIN pemasok p
            CROSS JOIN mitra m
            LEFT JOIN master_harga_pemasok mhp ON mhp.pemasok_id = p.id AND mhp.item_id = i.id
            LEFT JOIN master_harga mhm ON mhm.mitra_id = m.id AND mhm.item_id = i.id
            WHERE {$whereClause}
            ORDER BY j.id ASC, i.nama_item ASC, p.kode_pemasok ASC, m.kode_mitra ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
