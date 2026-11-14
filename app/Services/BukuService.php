<?php
namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Purchase Book (pembelian dari pemasok) dan laporan Barang Datang.
 * Satu baris = satu dokumen x satu artikel x satu harga. Harga memakai nilai yang dibekukan saat transaksi,
 * jadi perubahan master harga tidak mengubah riwayat. Penerimaan yang di-VOID dan pengeluaran yang dibatalkan tidak ikut.
 */
class BukuService
{
    /** Pemasok yang pernah ada penerimaan */
    public static function daftarPemasok(): array
    {
        return Database::getConnection()->query("SELECT id, kode_pemasok AS kode, nama_pemasok AS nama FROM pemasok ORDER BY nama_pemasok")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function daftarMitra(): array
    {
        return Database::getConnection()->query("SELECT id, kode_mitra AS kode, nama_mitra AS nama FROM mitra ORDER BY nama_mitra")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function daftarKategori(): array
    {
        return Database::getConnection()->query("SELECT id, nama_jenis AS nama FROM jenis_barang ORDER BY nama_jenis")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function daftarArtikel(): array
    {
        return Database::getConnection()->query("SELECT id, kode_item AS kode, nama_item AS nama FROM items ORDER BY nama_item")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Baris Purchase Book urut tanggal. Selisih harga dihitung terhadap pembelian sebelumnya
     * untuk artikel dan pemasok yang sama, termasuk yang sebelum tanggal awal filter.
     * Filter: dari, sampai (Y-m-d), item_id, pihak_id (pemasok).
     */
    public static function pembelian(array $f): array
    {
        $db = Database::getConnection();
        $sql = "
            SELECT DATE(MIN(l.created_at)) tanggal, l.pemasok_id pihak_id, COALESCE(p.nama_pemasok, '-') pihak,
                   l.item_id, i.kode_item, i.nama_item,
                   COALESCE(pi.no_invoice, '-') dokumen, COALESCE(l.no_surat_jalan, '-') sj,
                   COUNT(*) kemasan, SUM(l.berat_netto) qty, l.harga_beli_per_kg harga,
                   SUM(l.berat_netto * l.harga_beli_per_kg) nilai, 0 hpp
            FROM labels l
            JOIN items i ON i.id = l.item_id
            LEFT JOIN pemasok p ON p.id = l.pemasok_id
            LEFT JOIN purchase_invoices pi ON pi.no_surat_jalan = l.no_surat_jalan AND pi.tipe_invoice = 'STOK' AND pi.status_bayar <> 'BATAL'
            WHERE l.status <> 'VOID' AND l.no_surat_jalan IS NOT NULL AND l.no_surat_jalan <> ''
              AND DATE(l.created_at) <= :sampai";
        $p = [':sampai' => $f['sampai']];
        if (!empty($f['item_id']))  { $sql .= " AND l.item_id = :item";  $p[':item'] = (int)$f['item_id']; }
        if (!empty($f['pihak_id'])) { $sql .= " AND l.pemasok_id = :pihak"; $p[':pihak'] = (int)$f['pihak_id']; }
        $sql .= " GROUP BY l.no_surat_jalan, l.item_id, l.pemasok_id, l.harga_beli_per_kg, p.nama_pemasok, i.kode_item, i.nama_item, pi.no_invoice
                  ORDER BY tanggal, l.no_surat_jalan, i.nama_item";
        $st = $db->prepare($sql);
        $st->execute($p);
        return self::beriSelisih($st->fetchAll(PDO::FETCH_ASSOC), $f['dari']);
    }

    /**
     * Laporan Barang Datang: satu baris = satu Surat Jalan x artikel x harga, dengan kategori dan status bayar faktur.
     * Filter: dari, sampai, kategori_id, item_id, pihak_id (pemasok), status (BELUM | LUNAS | '').
     * Angka memakai sumber yang sama dengan Purchase Book (harga beli yang dibekukan; keranjang VOID tidak ikut).
     */
    public static function barangDatang(array $f): array
    {
        $db = Database::getConnection();
        $sql = "
            SELECT DATE(MIN(l.created_at)) tanggal, l.pemasok_id pihak_id, COALESCE(p.nama_pemasok, '-') pihak, COALESCE(p.kode_pemasok, '') kode_pihak,
                   l.item_id, i.kode_item, i.nama_item, i.jenis_id, COALESCE(j.nama_jenis, '-') kategori,
                   COALESCE(l.no_surat_jalan, '-') sj, COALESCE(pi.no_invoice, '-') faktur, COALESCE(pi.status_bayar, '') status_bayar,
                   COUNT(*) kemasan, SUM(l.berat_netto) qty, l.harga_beli_per_kg harga, SUM(l.berat_netto * l.harga_beli_per_kg) nilai,
                   SUM(CASE WHEN l.status = 'KELUAR' OR l.mitra_id IS NOT NULL THEN 0 ELSE 1 END) kemasan_gudang,
                   SUM(CASE WHEN l.status = 'KELUAR' OR l.mitra_id IS NOT NULL THEN 0 ELSE l.berat_netto END) qty_gudang,
                   GROUP_CONCAT(DISTINCT CASE WHEN l.status = 'KELUAR' OR l.mitra_id IS NOT NULL THEN m.nama_mitra END ORDER BY m.nama_mitra SEPARATOR ', ') mitra_tujuan
            FROM labels l
            JOIN items i ON i.id = l.item_id
            LEFT JOIN jenis_barang j ON j.id = i.jenis_id
            LEFT JOIN pemasok p ON p.id = l.pemasok_id
            LEFT JOIN mitra m ON m.id = l.mitra_id
            LEFT JOIN purchase_invoices pi ON pi.no_surat_jalan = l.no_surat_jalan AND pi.tipe_invoice = 'STOK' AND pi.status_bayar <> 'BATAL'
            WHERE l.status <> 'VOID' AND l.no_surat_jalan IS NOT NULL AND l.no_surat_jalan <> ''
              AND DATE(l.created_at) BETWEEN :dari AND :sampai";
        $p = [':dari' => $f['dari'], ':sampai' => $f['sampai']];
        if (!empty($f['kategori_id'])) { $sql .= " AND i.jenis_id = :kat";   $p[':kat'] = (int)$f['kategori_id']; }
        if (!empty($f['item_id']))     { $sql .= " AND l.item_id = :item";   $p[':item'] = (int)$f['item_id']; }
        if (!empty($f['pihak_id']))    { $sql .= " AND l.pemasok_id = :pihak"; $p[':pihak'] = (int)$f['pihak_id']; }
        if (($f['status'] ?? '') === 'LUNAS') $sql .= " AND pi.status_bayar = 'LUNAS'";
        if (($f['status'] ?? '') === 'BELUM') $sql .= " AND (pi.status_bayar IS NULL OR pi.status_bayar <> 'LUNAS')";
        $sql .= " GROUP BY l.no_surat_jalan, l.item_id, l.pemasok_id, l.harga_beli_per_kg, p.nama_pemasok, p.kode_pemasok,
                           i.kode_item, i.nama_item, i.jenis_id, j.nama_jenis, pi.no_invoice, pi.status_bayar
                  ORDER BY tanggal, l.no_surat_jalan, i.nama_item";
        $st = $db->prepare($sql);
        $st->execute($p);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['qty'] = (float)$r['qty']; $r['harga'] = (float)$r['harga']; $r['nilai'] = (float)$r['nilai']; $r['kemasan'] = (int)$r['kemasan'];
            $r['kemasan_gudang'] = (int)$r['kemasan_gudang']; $r['qty_gudang'] = (float)$r['qty_gudang'];
            // Active = masih ada kemasan di gudang; Closed = seluruhnya sudah pindah ke mitra
            $r['status_stok'] = $r['kemasan_gudang'] > 0 ? 'AKTIF' : 'CLOSED';
        }
        unset($r);
        $stok = strtoupper((string)($f['stok'] ?? ''));
        if ($stok === 'AKTIF' || $stok === 'CLOSED') {
            $rows = array_values(array_filter($rows, fn($r) => $r['status_stok'] === $stok));
        }
        return $rows;
    }

    /** Kelompokkan baris barang datang: [namaKelompok => ['rows' => [...], 'qty', 'kemasan', 'nilai']] */
    public static function kelompokkan(array $rows, string $dasar): array
    {
        $kunci = ['pemasok' => 'pihak', 'dokumen' => 'sj', 'kategori' => 'kategori'][$dasar] ?? 'pihak';
        $g = [];
        foreach ($rows as $r) {
            $k = $r[$kunci];
            $g[$k] ??= ['rows' => [], 'qty' => 0.0, 'kemasan' => 0, 'nilai' => 0.0];
            $g[$k]['rows'][] = $r;
            $g[$k]['qty'] += $r['qty']; $g[$k]['kemasan'] += $r['kemasan']; $g[$k]['nilai'] += $r['nilai'];
        }
        ksort($g);
        return $g;
    }

    /** Rincian per box untuk satu Surat Jalan dan artikel. */
    public static function rincianBox(string $sj, int $itemId): array
    {
        $st = Database::getConnection()->prepare("
            SELECT id_label, berat_bruto, berat_tara, berat_netto, harga_beli_per_kg, berat_netto * harga_beli_per_kg nilai, status, created_at
            FROM labels WHERE no_surat_jalan = ? AND item_id = ? AND status <> 'VOID' ORDER BY id");
        $st->execute([$sj, $itemId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Tambah selisih harga terhadap baris sebelumnya (artikel + pihak sama), lalu buang baris sebelum $dari. */
    private static function beriSelisih(array $rows, string $dari): array
    {
        $prev = [];
        $out = [];
        foreach ($rows as $r) {
            $k = $r['item_id'] . '|' . $r['pihak_id'];
            $r['qty'] = (float)$r['qty'];
            $r['harga'] = (float)$r['harga'];
            $r['nilai'] = (float)$r['nilai'];
            $r['hpp'] = (float)$r['hpp'];
            $r['kemasan'] = (int)$r['kemasan'];
            $r['selisih'] = isset($prev[$k]) ? $r['harga'] - $prev[$k] : null;
            $r['persen'] = (isset($prev[$k]) && $prev[$k] > 0) ? ($r['harga'] - $prev[$k]) / $prev[$k] * 100 : null;
            $prev[$k] = $r['harga'];
            if ($r['tanggal'] >= $dari) $out[] = $r;
        }
        return $out;
    }

    /** Ringkasan harga untuk baris yang tampil: min, max, terakhir, rata-rata tertimbang, total qty dan nilai. */
    public static function ringkas(array $rows): array
    {
        $qty = $nilai = 0.0;
        $min = $max = null;
        $last = null;
        foreach ($rows as $r) {
            $qty += $r['qty']; $nilai += $r['nilai'];
            $min = $min === null ? $r['harga'] : min($min, $r['harga']);
            $max = $max === null ? $r['harga'] : max($max, $r['harga']);
            $last = $r['harga'];
        }
        return ['baris' => count($rows), 'qty' => $qty, 'nilai' => $nilai, 'rata' => $qty > 0 ? $nilai / $qty : 0.0,
                'min' => $min ?? 0.0, 'max' => $max ?? 0.0, 'terakhir' => $last ?? 0.0];
    }

    /** Perbandingan per pihak: [pihak => [transaksi, qty, nilai, rata, min, max, terakhir]] */
    public static function perPihak(array $rows): array
    {
        $g = [];
        foreach ($rows as $r) $g[$r['pihak']][] = $r;
        $out = [];
        foreach ($g as $nama => $list) {
            $s = self::ringkas($list);
            $out[$nama] = ['transaksi' => $s['baris'], 'qty' => $s['qty'], 'nilai' => $s['nilai'], 'rata' => $s['rata'],
                           'min' => $s['min'], 'max' => $s['max'], 'terakhir' => $s['terakhir']];
        }
        ksort($out);
        return $out;
    }
}
