<?php
namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Stock On Hand: sisa, masuk, dan keluar barang per jenis dan per produk, dihitung dari status tiap box (label).
 * - Sisa   : box masih di gudang (status AKTIF dan belum dimiliki mitra)
 * - Keluar : box sudah pindah ke mitra (status KELUAR, atau AKTIF tetapi sudah dimiliki mitra)
 * - Masuk  : semua box yang pernah diterima (selain yang dibatalkan/VOID)
 */
class StockOnHandService
{
    public const SISA   = "(l.status = 'AKTIF' AND l.mitra_id IS NULL)";
    public const KELUAR = "(l.status = 'KELUAR' OR (l.status = 'AKTIF' AND l.mitra_id IS NOT NULL))";

    /** Bagian WHERE bersama untuk filter jenis, produk, pemasok, dan tanggal masuk. */
    private static function filter(array $f, array &$p): string
    {
        $w = "l.status <> 'VOID'";
        if (!empty($f['jenis_id']))   { $w .= ' AND i.jenis_id = :jenis';    $p[':jenis'] = (int)$f['jenis_id']; }
        if (!empty($f['item_id']))    { $w .= ' AND l.item_id = :item';      $p[':item'] = (int)$f['item_id']; }
        if (!empty($f['pemasok_id'])) { $w .= ' AND l.pemasok_id = :pemasok'; $p[':pemasok'] = (int)$f['pemasok_id']; }
        if (!empty($f['dari']))       { $w .= ' AND DATE(l.created_at) >= :dari';   $p[':dari'] = $f['dari']; }
        if (!empty($f['sampai']))     { $w .= ' AND DATE(l.created_at) <= :sampai'; $p[':sampai'] = $f['sampai']; }
        return $w;
    }

    /**
     * Ringkasan per produk.
     * $f: jenis_id, item_id, pemasok_id, dari, sampai, tampil ('' semua | 'sisa' masih ada sisa | 'habis' sudah habis keluar)
     */
    public static function perProduk(array $f): array
    {
        $p = [];
        $where = self::filter($f, $p);
        $s = self::SISA;
        $k = self::KELUAR;
        $st = Database::getConnection()->prepare("
            SELECT i.id AS item_id, i.kode_item, i.nama_item, i.jenis_id, COALESCE(j.nama_jenis, '-') AS jenis,
                   SUM($s) AS sisa_box,  COALESCE(SUM(IF($s, l.berat_netto, 0)), 0) AS sisa_kg,
                   COALESCE(SUM(IF($s, l.berat_netto * l.harga_beli_per_kg, 0)), 0) AS sisa_nilai,
                   COUNT(*) AS masuk_box, COALESCE(SUM(l.berat_netto), 0) AS masuk_kg,
                   SUM($k) AS keluar_box, COALESCE(SUM(IF($k, l.berat_netto, 0)), 0) AS keluar_kg,
                   MIN(IF($s, l.created_at, NULL)) AS sisa_tertua
            FROM labels l
            JOIN items i ON i.id = l.item_id
            LEFT JOIN jenis_barang j ON j.id = i.jenis_id
            WHERE $where
            GROUP BY i.id, i.kode_item, i.nama_item, i.jenis_id, j.nama_jenis
            ORDER BY jenis, i.nama_item");
        $st->execute($p);
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            foreach (['sisa_box', 'masuk_box', 'keluar_box'] as $c) $r[$c] = (int)$r[$c];
            foreach (['sisa_kg', 'sisa_nilai', 'masuk_kg', 'keluar_kg'] as $c) $r[$c] = (float)$r[$c];
            $r['umur_tertua'] = $r['sisa_tertua'] ? (int)floor((strtotime(date('Y-m-d')) - strtotime(substr($r['sisa_tertua'], 0, 10))) / 86400) : null;
            $tampil = $f['tampil'] ?? '';
            if ($tampil === 'sisa' && $r['sisa_box'] <= 0) continue;
            if ($tampil === 'habis' && $r['sisa_box'] > 0) continue;
            $rows[] = $r;
        }
        return $rows;
    }

    /** Jumlahkan baris produk per jenis: [jenis => angka], diurutkan nama jenis. */
    public static function perJenis(array $produk): array
    {
        $g = [];
        foreach ($produk as $r) {
            $j = $r['jenis'];
            $g[$j] ??= ['jenis' => $j, 'jenis_id' => (int)$r['jenis_id'], 'produk' => 0, 'produk_sisa' => 0,
                        'sisa_box' => 0, 'sisa_kg' => 0.0, 'sisa_nilai' => 0.0, 'masuk_box' => 0, 'masuk_kg' => 0.0, 'keluar_box' => 0, 'keluar_kg' => 0.0];
            $g[$j]['produk']++;
            if ($r['sisa_box'] > 0) $g[$j]['produk_sisa']++;
            foreach (['sisa_box', 'sisa_kg', 'sisa_nilai', 'masuk_box', 'masuk_kg', 'keluar_box', 'keluar_kg'] as $c) $g[$j][$c] += $r[$c];
        }
        ksort($g, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values($g);
    }

    /** Total keseluruhan dari baris produk. */
    public static function total(array $produk): array
    {
        $t = ['produk' => count($produk), 'produk_sisa' => 0, 'sisa_box' => 0, 'sisa_kg' => 0.0, 'sisa_nilai' => 0.0, 'masuk_box' => 0, 'masuk_kg' => 0.0, 'keluar_box' => 0, 'keluar_kg' => 0.0];
        foreach ($produk as $r) {
            if ($r['sisa_box'] > 0) $t['produk_sisa']++;
            foreach (['sisa_box', 'sisa_kg', 'sisa_nilai', 'masuk_box', 'masuk_kg', 'keluar_box', 'keluar_kg'] as $c) $t[$c] += $r[$c];
        }
        return $t;
    }

    /** Rincian per box satu produk (filter pemasok/tanggal sama dengan ringkasan); box yang masih di gudang tampil lebih dulu. */
    public static function rincian(int $itemId, array $f): array
    {
        $f['item_id'] = $itemId;
        unset($f['jenis_id']);
        $p = [];
        $where = self::filter($f, $p);
        $s = self::SISA;
        $st = Database::getConnection()->prepare("
            SELECT l.id_label, l.no_surat_jalan, COALESCE(pm.nama_pemasok, '-') AS pemasok, l.created_at AS tgl_masuk,
                   DATEDIFF(CURRENT_DATE, DATE(l.created_at)) AS umur, l.berat_netto, l.harga_beli_per_kg,
                   IF($s, 'SISA', 'KELUAR') AS posisi, m.nama_mitra, k.no_sj_keluar, k.tgl_keluar
            FROM labels l
            JOIN items i ON i.id = l.item_id
            LEFT JOIN pemasok pm ON pm.id = l.pemasok_id
            LEFT JOIN mitra m ON m.id = l.mitra_id
            LEFT JOIN (
                SELECT d.label_id,
                       MAX(COALESCE(NULLIF(t.no_referensi_eksternal, ''), NULLIF(t.no_bast, ''), t.no_transaksi)) AS no_sj_keluar,
                       MAX(t.created_at) AS tgl_keluar
                FROM transaksi_stok_detail d
                JOIN transaksi_stok t ON t.id = d.transaksi_id AND t.jenis_transaksi = 'PENGELUARAN'
                GROUP BY d.label_id
            ) k ON k.label_id = l.id
            WHERE $where
            ORDER BY ($s) DESC, l.created_at, l.id");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
