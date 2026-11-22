<?php
namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Rincian box di balik satu surat jalan untuk menu AR Aging dan AP Aging.
 * Hanya memuat sisi yang relevan: AR memakai harga jual ke mitra, AP memakai harga beli dari pemasok (tanpa HPP/laba).
 */
class RincianSuratJalanService
{
    /** Box pada satu surat jalan keluar (transaksi PENGELUARAN): nominal = berat x harga jual mitra. */
    public static function keluar(int $transaksiId): array
    {
        $db = Database::getConnection();
        $h = $db->prepare("
            SELECT t.id, COALESCE(NULLIF(t.no_referensi_eksternal, ''), NULLIF(t.no_bast, ''), t.no_transaksi) AS sj, t.no_transaksi,
                   t.created_at, CONCAT('[', m.kode_mitra, '] ', m.nama_mitra) AS pihak, t.total_nilai_jual AS total
            FROM transaksi_stok t LEFT JOIN mitra m ON m.id = t.mitra_id
            WHERE t.id = ? AND t.jenis_transaksi = 'PENGELUARAN'");
        $h->execute([$transaksiId]);
        $kepala = $h->fetch(PDO::FETCH_ASSOC);
        if (!$kepala) return ['kepala' => null, 'box' => []];

        $st = $db->prepare("
            SELECT i.nama_item AS produk, COALESCE(jb.nama_jenis, '-') AS jenis, l.id_label,
                   COALESCE(CONCAT('[', p.kode_pemasok, '] ', p.nama_pemasok), '-') AS pemasok, COALESCE(l.no_surat_jalan, '-') AS sj_masuk,
                   d.berat_netto, d.harga_mitra AS harga, d.total_jual AS nominal
            FROM transaksi_stok_detail d
            JOIN labels l ON l.id = d.label_id
            JOIN items i ON i.id = l.item_id
            LEFT JOIN jenis_barang jb ON jb.id = i.jenis_id
            LEFT JOIN pemasok p ON p.id = l.pemasok_id
            WHERE d.transaksi_id = ?
            ORDER BY i.nama_item, l.id_label");
        $st->execute([$transaksiId]);
        return ['kepala' => $kepala, 'box' => self::angka($st->fetchAll(PDO::FETCH_ASSOC))];
    }

    /** Box pada satu surat jalan masuk dari pemasok (box batal tidak dihitung): nominal = berat x harga beli. */
    public static function masuk(string $noSj): array
    {
        $db = Database::getConnection();
        $h = $db->prepare("
            SELECT l.no_surat_jalan AS sj, MIN(l.created_at) AS created_at,
                   MAX(CONCAT('[', p.kode_pemasok, '] ', p.nama_pemasok)) AS pihak,
                   SUM(l.berat_netto * l.harga_beli_per_kg) AS total
            FROM labels l LEFT JOIN pemasok p ON p.id = l.pemasok_id
            WHERE l.no_surat_jalan = ? AND l.status <> 'VOID'
            GROUP BY l.no_surat_jalan");
        $h->execute([$noSj]);
        $kepala = $h->fetch(PDO::FETCH_ASSOC);
        if (!$kepala) return ['kepala' => null, 'box' => []];

        $st = $db->prepare("
            SELECT i.nama_item AS produk, COALESCE(jb.nama_jenis, '-') AS jenis, l.id_label, l.status,
                   l.berat_netto, l.harga_beli_per_kg AS harga, ROUND(l.berat_netto * l.harga_beli_per_kg, 2) AS nominal
            FROM labels l
            JOIN items i ON i.id = l.item_id
            LEFT JOIN jenis_barang jb ON jb.id = i.jenis_id
            WHERE l.no_surat_jalan = ? AND l.status <> 'VOID'
            ORDER BY i.nama_item, l.id_label");
        $st->execute([$noSj]);
        return ['kepala' => $kepala, 'box' => self::angka($st->fetchAll(PDO::FETCH_ASSOC))];
    }

    private static function angka(array $rows): array
    {
        foreach ($rows as &$r) foreach (['berat_netto', 'harga', 'nominal'] as $c) $r[$c] = (float)$r[$c];
        unset($r);
        return $rows;
    }
}
