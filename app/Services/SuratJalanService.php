<?php
namespace App\Services;

use App\Core\Database;
use PDO;

/** Daftar surat jalan (pengeluaran ke mitra dan penerimaan dari pemasok) untuk dicetak ulang. */
class SuratJalanService
{
    private const BATAS = 500;

    /** $f: dari, sampai, mitra_id, cari, status (AKTIF|BATAL) */
    public static function pengeluaran(array $f): array
    {
        $db = Database::getConnection();
        $w = ["ts.jenis_transaksi IN ('PENGELUARAN','PENGELUARAN_BATAL')", 'DATE(ts.created_at) BETWEEN ? AND ?'];
        $p = [$f['dari'], $f['sampai']];
        if (!empty($f['mitra_id'])) { $w[] = 'ts.mitra_id = ?'; $p[] = (int)$f['mitra_id']; }
        if (($f['status'] ?? '') === 'AKTIF') $w[] = "ts.jenis_transaksi = 'PENGELUARAN'";
        if (($f['status'] ?? '') === 'BATAL') $w[] = "ts.jenis_transaksi = 'PENGELUARAN_BATAL'";
        if (!empty($f['cari'])) {
            $w[] = '(ts.no_transaksi LIKE ? OR ts.no_referensi_eksternal LIKE ? OR ts.no_bast LIKE ? OR m.nama_mitra LIKE ?)';
            $q = '%' . trim($f['cari']) . '%';
            array_push($p, $q, $q, $q, $q);
        }
        $st = $db->prepare("
            SELECT ts.id, ts.no_transaksi, ts.jenis_transaksi, ts.created_at, ts.total_label, ts.total_kg, ts.total_nilai_jual,
                   ts.alasan_batal, ts.tanggal_jatuh_tempo,
                   COALESCE(NULLIF(ts.no_referensi_eksternal, ''), NULLIF(ts.no_bast, ''), ts.no_transaksi) AS no_sj,
                   m.kode_mitra, m.nama_mitra
            FROM transaksi_stok ts
            LEFT JOIN mitra m ON m.id = ts.mitra_id
            WHERE " . implode(' AND ', $w) . "
            ORDER BY ts.created_at DESC, ts.id DESC
            LIMIT " . self::BATAS);
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** $f: dari, sampai, pemasok_id, cari, status (AKTIF|BATAL) */
    public static function penerimaan(array $f): array
    {
        $db = Database::getConnection();
        $w = ["l.no_surat_jalan IS NOT NULL AND l.no_surat_jalan <> ''", 'DATE(l.created_at) BETWEEN ? AND ?'];
        $p = [$f['dari'], $f['sampai']];
        if (!empty($f['pemasok_id'])) { $w[] = 'l.pemasok_id = ?'; $p[] = (int)$f['pemasok_id']; }
        if (!empty($f['cari'])) {
            $w[] = '(l.no_surat_jalan LIKE ? OR pm.nama_pemasok LIKE ? OR pm.kode_pemasok LIKE ?)';
            $q = '%' . trim($f['cari']) . '%';
            array_push($p, $q, $q, $q);
        }
        $having = [];
        if (($f['status'] ?? '') === 'AKTIF') $having[] = "SUM(l.status <> 'VOID') > 0";
        if (($f['status'] ?? '') === 'BATAL') $having[] = "SUM(l.status <> 'VOID') = 0";
        $st = $db->prepare("
            SELECT l.no_surat_jalan, MIN(l.created_at) AS tanggal, pm.kode_pemasok, pm.nama_pemasok,
                   COUNT(*) AS box, COALESCE(SUM(CASE WHEN l.status <> 'VOID' THEN l.berat_netto END), 0) AS kg,
                   COALESCE(SUM(CASE WHEN l.status <> 'VOID' THEN l.berat_netto * l.harga_beli_per_kg END), 0) AS nilai,
                   (SUM(l.status <> 'VOID') = 0) AS batal
            FROM labels l
            JOIN pemasok pm ON pm.id = l.pemasok_id
            WHERE " . implode(' AND ', $w) . "
            GROUP BY l.no_surat_jalan, pm.kode_pemasok, pm.nama_pemasok
            " . ($having ? 'HAVING ' . implode(' AND ', $having) : '') . "
            ORDER BY MIN(l.created_at) DESC, l.no_surat_jalan DESC
            LIMIT " . self::BATAS);
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function batas(): int
    {
        return self::BATAS;
    }
}
