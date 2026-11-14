<?php
namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Daftar surat jalan (pengeluaran ke mitra dan penerimaan dari pemasok) untuk dicetak ulang.
 * Pengurutan dan halaman dilakukan di SQL, jadi urutan berlaku untuk seluruh hasil, bukan hanya halaman yang tampil.
 */
class SuratJalanService
{
    /** kunci urutan => ekspresi SQL (kunci dari pengguna tidak pernah masuk langsung ke SQL) */
    public const URUT_KELUAR = [
        'tanggal' => 'ts.created_at', 'no' => 'no_sj', 'pihak' => 'm.nama_mitra',
        'box' => 'ts.total_label', 'kg' => 'ts.total_kg', 'nilai' => 'ts.total_nilai_jual', 'status' => 'ts.jenis_transaksi',
    ];
    public const URUT_TERIMA = [
        'tanggal' => 'MIN(l.created_at)', 'no' => 'l.no_surat_jalan', 'pihak' => 'pm.nama_pemasok',
        'box' => 'box', 'kg' => 'kg', 'nilai' => 'nilai', 'status' => 'batal',
    ];

    private static function arah(array $f): string
    {
        return (($f['arah'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
    }

    /**
     * $f: dari, sampai, mitra_id, cari, status (AKTIF|BATAL), urut, arah (asc|desc), limit, offset
     * @return array{rows: array, total: int}
     */
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
        $where = implode(' AND ', $w);

        $c = $db->prepare("SELECT COUNT(*) FROM transaksi_stok ts LEFT JOIN mitra m ON m.id = ts.mitra_id WHERE $where");
        $c->execute($p);
        $total = (int)$c->fetchColumn();

        $kolom = self::URUT_KELUAR[$f['urut'] ?? 'tanggal'] ?? self::URUT_KELUAR['tanggal'];
        $st = $db->prepare("
            SELECT ts.id, ts.no_transaksi, ts.jenis_transaksi, ts.created_at, ts.total_label, ts.total_kg, ts.total_nilai_jual,
                   ts.alasan_batal, ts.tanggal_jatuh_tempo,
                   COALESCE(NULLIF(ts.no_referensi_eksternal, ''), NULLIF(ts.no_bast, ''), ts.no_transaksi) AS no_sj,
                   m.kode_mitra, m.nama_mitra
            FROM transaksi_stok ts
            LEFT JOIN mitra m ON m.id = ts.mitra_id
            WHERE $where
            ORDER BY $kolom " . self::arah($f) . ", ts.id " . self::arah($f) . "
            LIMIT " . max(1, (int)($f['limit'] ?? 25)) . " OFFSET " . max(0, (int)($f['offset'] ?? 0)));
        $st->execute($p);
        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /**
     * $f: dari, sampai, pemasok_id, cari, status (AKTIF|BATAL), urut, arah (asc|desc), limit, offset
     * @return array{rows: array, total: int}
     */
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
        $dasar = "
            SELECT l.no_surat_jalan, MIN(l.created_at) AS tanggal, pm.kode_pemasok, pm.nama_pemasok,
                   COUNT(*) AS box, COALESCE(SUM(CASE WHEN l.status <> 'VOID' THEN l.berat_netto END), 0) AS kg,
                   COALESCE(SUM(CASE WHEN l.status <> 'VOID' THEN l.berat_netto * l.harga_beli_per_kg END), 0) AS nilai,
                   (SUM(l.status <> 'VOID') = 0) AS batal
            FROM labels l
            JOIN pemasok pm ON pm.id = l.pemasok_id
            WHERE " . implode(' AND ', $w) . "
            GROUP BY l.no_surat_jalan, pm.kode_pemasok, pm.nama_pemasok
            " . ($having ? 'HAVING ' . implode(' AND ', $having) : '');

        $c = $db->prepare("SELECT COUNT(*) FROM ($dasar) x");
        $c->execute($p);
        $total = (int)$c->fetchColumn();

        $kolom = self::URUT_TERIMA[$f['urut'] ?? 'tanggal'] ?? self::URUT_TERIMA['tanggal'];
        $st = $db->prepare($dasar . "
            ORDER BY $kolom " . self::arah($f) . ", l.no_surat_jalan " . self::arah($f) . "
            LIMIT " . max(1, (int)($f['limit'] ?? 25)) . " OFFSET " . max(0, (int)($f['offset'] ?? 0)));
        $st->execute($p);
        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }
}
