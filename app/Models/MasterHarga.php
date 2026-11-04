<?php
/**
 * Model Master Harga per Mitra & Item
 * Mengelola daftar harga per Kg untuk masing-masing 7 Mitra Bisnis
 */

namespace App\Models;

use PDO;
use Exception;
use App\Core\Database;

class MasterHarga {
    /**
     * Dapatkan semua data harga dengan detail mitra dan item
     */
    public static function getAllWithDetails(?int $filterMitraId = null): array {
        $db = Database::getConnection();
        $sql = "
            SELECT 
                h.id,
                h.mitra_id,
                h.item_id,
                h.harga_per_kg,
                h.catatan,
                h.updated_at,
                m.kode_mitra,
                m.nama_mitra,
                m.warna_identitas,
                i.kode_item,
                i.nama_item,
                i.default_tara_kg,
                j.kode_jenis,
                j.nama_jenis
            FROM master_harga h
            JOIN mitra m ON h.mitra_id = m.id
            JOIN items i ON h.item_id = i.id
            JOIN jenis_barang j ON i.jenis_id = j.id
        ";

        if ($filterMitraId) {
            $sql .= " WHERE h.mitra_id = ? ORDER BY i.nama_item ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$filterMitraId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $sql .= " ORDER BY m.kode_mitra ASC, j.kode_jenis ASC, i.nama_item ASC";
        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Dapatkan harga per kg spesifik berdasarkan Mitra dan Item
     */
    public static function getHargaByMitraAndItem(int $mitraId, int $itemId): float {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT harga_per_kg FROM master_harga WHERE mitra_id = ? AND item_id = ?");
        $stmt->execute([$mitraId, $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (float)$row['harga_per_kg'] : 0.00;
    }

    /**
     * Simpan atau perbarui harga satu produk untuk mitra tertentu
     */
    public static function saveHarga(int $mitraId, int $itemId, float $hargaPerKg, ?string $catatan = null): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO master_harga (mitra_id, item_id, harga_per_kg, catatan)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                harga_per_kg = VALUES(harga_per_kg),
                catatan = VALUES(catatan),
                updated_at = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$mitraId, $itemId, $hargaPerKg, $catatan]);
    }

    /**
     * Matriks harga: Produk × 7 Mitra untuk tampilan tabel komparasi & bulk update
     */
    public static function getHargaMatrix(): array {
        $db = Database::getConnection();
        $mitras = Mitra::getAll();
        $items = Item::getAll();

        $rawPrices = $db->query("SELECT mitra_id, item_id, harga_per_kg FROM master_harga")->fetchAll(PDO::FETCH_ASSOC);
        $lookup = [];
        foreach ($rawPrices as $rp) {
            $lookup[$rp['item_id']][$rp['mitra_id']] = (float)$rp['harga_per_kg'];
        }

        $matrix = [];
        foreach ($items as $it) {
            $row = [
                'item' => $it,
                'prices' => []
            ];
            foreach ($mitras as $m) {
                $row['prices'][$m['id']] = $lookup[$it['id']][$m['id']] ?? 0.00;
            }
            $matrix[] = $row;
        }

        return [
            'mitras' => $mitras,
            'matrix' => $matrix
        ];
    }

    /**
     * Dapatkan peta JSON harga untuk semua mitra & item (untuk kalkulasi instan di front-end)
     */
    public static function getAllPricesJson(): string {
        $db = Database::getConnection();
        $raw = $db->query("SELECT mitra_id, item_id, harga_per_kg FROM master_harga")->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($raw as $r) {
            $key = $r['mitra_id'] . '_' . $r['item_id'];
            $map[$key] = (float)$r['harga_per_kg'];
        }
        return json_encode($map);
    }
}
