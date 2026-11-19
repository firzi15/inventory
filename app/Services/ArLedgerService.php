<?php
namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * AR Ledger: mutasi piutang per mitra dalam satu periode.
 * Saldo Awal = nota sebelum periode - setoran sebelum periode
 * Debit      = nota pengeluaran ke mitra di dalam periode (piutang baru)
 * Kredit     = setoran mitra di dalam periode
 * Saldo Akhir = Saldo Awal + Debit - Kredit
 * Tanggal nota = tanggal pengeluaran, tanggal setoran = tanggal bayar (sama dengan tanggal jurnalnya).
 * Pengeluaran yang dibatalkan tidak dihitung; setoran yang dibatalkan bernilai 0.
 */
class ArLedgerService
{
    /** @return array<int, array> baris per mitra: mitra_id, kode_mitra, nama_mitra, awal, debit, kredit, akhir */
    public static function perMitra(string $dari, string $sampai, int $mitraId = 0): array
    {
        $db = Database::getConnection();
        $p = [':n1' => $dari, ':n2' => $dari, ':n3' => $sampai, ':n4' => $dari, ':n5' => $sampai,
              ':s1' => $dari, ':s2' => $dari, ':s3' => $sampai, ':s4' => $dari, ':s5' => $sampai];
        $whereMitra = '';
        if ($mitraId > 0) { $whereMitra = ' WHERE m.id = :mitra'; $p[':mitra'] = $mitraId; }
        $st = $db->prepare("
            SELECT m.id AS mitra_id, m.kode_mitra, m.nama_mitra,
                   COALESCE(n.sebelum, 0) AS nota_sebelum, COALESCE(n.periode, 0) AS debit,
                   COALESCE(s.sebelum, 0) AS setor_sebelum, COALESCE(s.periode, 0) AS kredit,
                   COALESCE(n.jml, 0) AS jml_nota, COALESCE(s.jml, 0) AS jml_setor
            FROM mitra m
            LEFT JOIN (
                SELECT mitra_id,
                       SUM(IF(DATE(created_at) < :n1, total_nilai_jual, 0)) AS sebelum,
                       SUM(IF(DATE(created_at) BETWEEN :n2 AND :n3, total_nilai_jual, 0)) AS periode,
                       SUM(IF(DATE(created_at) BETWEEN :n4 AND :n5, 1, 0)) AS jml
                FROM transaksi_stok WHERE jenis_transaksi = 'PENGELUARAN' GROUP BY mitra_id
            ) n ON n.mitra_id = m.id
            LEFT JOIN (
                SELECT mitra_id,
                       SUM(IF(tanggal_bayar < :s1, jumlah_bayar, 0)) AS sebelum,
                       SUM(IF(tanggal_bayar BETWEEN :s2 AND :s3, jumlah_bayar, 0)) AS periode,
                       SUM(IF(tanggal_bayar BETWEEN :s4 AND :s5 AND status = 'AKTIF', 1, 0)) AS jml
                FROM ar_pembayaran GROUP BY mitra_id
            ) s ON s.mitra_id = m.id
            $whereMitra
            ORDER BY m.kode_mitra, m.id");
        $st->execute($p);
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $awal = round((float)$r['nota_sebelum'] - (float)$r['setor_sebelum'], 2);
            $debit = (float)$r['debit'];
            $kredit = (float)$r['kredit'];
            $rows[] = [
                'mitra_id' => (int)$r['mitra_id'], 'kode_mitra' => $r['kode_mitra'], 'nama_mitra' => $r['nama_mitra'],
                'awal' => $awal, 'debit' => $debit, 'kredit' => $kredit, 'akhir' => round($awal + $debit - $kredit, 2),
                'jml_nota' => (int)$r['jml_nota'], 'jml_setor' => (int)$r['jml_setor'],
            ];
        }
        return $rows;
    }

    public static function total(array $rows): array
    {
        $t = ['awal' => 0.0, 'debit' => 0.0, 'kredit' => 0.0, 'akhir' => 0.0, 'jml_nota' => 0, 'jml_setor' => 0];
        foreach ($rows as $r) foreach ($t as $k => $_) $t[$k] += $r[$k];
        return $t;
    }

    /**
     * Kartu piutang satu mitra: baris saldo awal, lalu nota (debit) dan setoran (kredit) di periode, urut tanggal, dengan saldo berjalan.
     * @return array{awal: float, baris: array, akhir: float}
     */
    public static function kartu(int $mitraId, string $dari, string $sampai): array
    {
        $db = Database::getConnection();
        $awal = self::perMitra($dari, $sampai, $mitraId)[0]['awal'] ?? 0.0;

        $n = $db->prepare("
            SELECT DATE(created_at) AS tanggal, created_at AS urut, 'NOTA' AS jenis,
                   COALESCE(NULLIF(no_referensi_eksternal, ''), NULLIF(no_bast, ''), no_transaksi) AS dokumen,
                   CONCAT(total_label, ' box, ', REPLACE(FORMAT(total_kg, 2), ',', ''), ' kg') AS keterangan,
                   total_nilai_jual AS debit, 0 AS kredit
            FROM transaksi_stok
            WHERE jenis_transaksi = 'PENGELUARAN' AND mitra_id = ? AND DATE(created_at) BETWEEN ? AND ?");
        $n->execute([$mitraId, $dari, $sampai]);
        $s = $db->prepare("
            SELECT tanggal_bayar AS tanggal, CONCAT(tanggal_bayar, ' 23:59:59') AS urut, 'SETORAN' AS jenis, no_bukti_bayar AS dokumen,
                   IF(metode_bayar = 'TRANSFER_BANK', CONCAT('Transfer', IF(bank_tujuan IS NULL OR bank_tujuan = '', '', CONCAT(' ', bank_tujuan))), 'Tunai') AS keterangan,
                   0 AS debit, jumlah_bayar AS kredit
            FROM ar_pembayaran
            WHERE mitra_id = ? AND status = 'AKTIF' AND tanggal_bayar BETWEEN ? AND ?");
        $s->execute([$mitraId, $dari, $sampai]);

        $baris = array_merge($n->fetchAll(PDO::FETCH_ASSOC), $s->fetchAll(PDO::FETCH_ASSOC));
        usort($baris, fn($a, $b) => [$a['urut'], $a['jenis']] <=> [$b['urut'], $b['jenis']]);
        $saldo = $awal;
        foreach ($baris as &$b) {
            $b['debit'] = (float)$b['debit'];
            $b['kredit'] = (float)$b['kredit'];
            $saldo = round($saldo + $b['debit'] - $b['kredit'], 2);
            $b['saldo'] = $saldo;
            unset($b['urut']);
        }
        unset($b);
        return ['awal' => $awal, 'baris' => $baris, 'akhir' => $saldo];
    }
}
