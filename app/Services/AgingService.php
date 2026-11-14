<?php
namespace App\Services;

/**
 * Umur utang dan piutang berdasarkan jatuh tempo.
 * Hari terlambat = hari ini dikurangi tanggal jatuh tempo (positif = lewat, nol atau negatif = belum jatuh tempo).
 * Dokumen tanpa jatuh tempo dianggap jatuh tempo pada tanggal dokumennya.
 */
class AgingService
{
    /** Pilihan termin pada form: hari => label */
    public const TERMIN = [0 => 'Tunai (langsung)', 7 => '7 hari', 14 => '14 hari', 30 => '30 hari', 45 => '45 hari', 60 => '60 hari'];

    /** Kolom ringkasan: kunci => label */
    public const KOLOM = [
        'aging_belum'  => 'Belum jatuh tempo',
        'aging_1_7'    => 'Telat 1-7 hari',
        'aging_8_14'   => 'Telat 8-14 hari',
        'aging_15_30'  => 'Telat 15-30 hari',
        'aging_gt_30'  => 'Telat > 30 hari',
    ];

    public static function hariTelat(?string $jatuhTempo, ?string $hariIni = null): int
    {
        if (!$jatuhTempo) return 0;
        $a = strtotime(substr($jatuhTempo, 0, 10));
        $b = strtotime($hariIni ?: date('Y-m-d'));
        return (int)round(($b - $a) / 86400);
    }

    public static function kunci(int $telat): string
    {
        if ($telat <= 0) return 'aging_belum';
        if ($telat <= 7) return 'aging_1_7';
        if ($telat <= 14) return 'aging_8_14';
        if ($telat <= 30) return 'aging_15_30';
        return 'aging_gt_30';
    }

    public static function kosong(): array
    {
        return array_fill_keys(array_keys(self::KOLOM), 0.0);
    }

    /** Tambahkan sisa ke kolom yang sesuai pada $total (array dengan kunci KOLOM). */
    public static function tambah(array &$total, int $telat, float $sisa): void
    {
        $total[self::kunci($telat)] += $sisa;
    }

    public static function totalTelat(array $total): float
    {
        return $total['aging_1_7'] + $total['aging_8_14'] + $total['aging_15_30'] + $total['aging_gt_30'];
    }

    /** [label, gaya badge] untuk satu dokumen yang masih terutang. */
    public static function status(int $telat): array
    {
        if ($telat > 0) return ['Telat ' . $telat . ' hari', 'background: #fee2e2; color: #b91c1c;'];
        if ($telat === 0) return ['Jatuh tempo hari ini', 'background: #fef3c7; color: #b45309;'];
        return ['Sisa ' . abs($telat) . ' hari', 'background: #dcfce7; color: #15803d;'];
    }

    /** Ubah jatuh tempo satu faktur pemasok atau nota mitra yang belum lunas. */
    public static function ubahJatuhTempo(string $jenis, int $id, string $tanggal, int $userId): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || strtotime($tanggal) === false) throw new \Exception('Tanggal jatuh tempo tidak valid.');
        $db = \App\Core\Database::getConnection();

        if ($jenis === 'FAKTUR') {
            $st = $db->prepare("SELECT no_invoice, tanggal_invoice, status_bayar FROM purchase_invoices WHERE id = ?");
            $st->execute([$id]);
            $d = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$d) throw new \Exception('Faktur tidak ditemukan.');
            if (in_array($d['status_bayar'], ['BATAL', 'LUNAS'], true)) throw new \Exception('Faktur yang sudah lunas atau dibatalkan tidak perlu diubah jatuh temponya.');
            if ($tanggal < $d['tanggal_invoice']) throw new \Exception('Jatuh tempo tidak boleh sebelum tanggal faktur.');
            $db->prepare("UPDATE purchase_invoices SET tanggal_jatuh_tempo = ? WHERE id = ?")->execute([$tanggal, $id]);
            $ref = $d['no_invoice'];
            $tabel = 'purchase_invoices';
        } elseif ($jenis === 'NOTA') {
            $st = $db->prepare("SELECT no_transaksi, DATE(created_at) AS tgl FROM transaksi_stok WHERE id = ? AND jenis_transaksi = 'PENGELUARAN'");
            $st->execute([$id]);
            $d = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$d) throw new \Exception('Nota tidak ditemukan atau sudah dibatalkan.');
            if ($tanggal < $d['tgl']) throw new \Exception('Jatuh tempo tidak boleh sebelum tanggal nota.');
            $termin = (int)round((strtotime($tanggal) - strtotime($d['tgl'])) / 86400);
            $db->prepare("UPDATE transaksi_stok SET tanggal_jatuh_tempo = ?, termin_hari = ? WHERE id = ?")->execute([$tanggal, $termin, $id]);
            $ref = $d['no_transaksi'];
            $tabel = 'transaksi_stok';
        } else {
            throw new \Exception('Jenis dokumen tidak dikenal.');
        }
        $db->prepare("INSERT INTO audit_logs (user_id, action_name, target_table, target_id, reason, ip_address) VALUES (?,?,?,?,?,?)")
           ->execute([$userId, 'UBAH_JATUH_TEMPO', $tabel, (string)$id, 'Jatuh tempo menjadi ' . $tanggal, $_SERVER['REMOTE_ADDR'] ?? 'cli']);
        return "Jatuh tempo {$ref} diubah menjadi " . date('d/m/Y', strtotime($tanggal)) . '.';
    }

    /** Tanggal jatuh tempo dari tanggal dokumen dan termin (hari). */
    public static function hitung(string $tanggalDokumen, int $terminHari): string
    {
        return date('Y-m-d', strtotime(substr($tanggalDokumen, 0, 10) . ' +' . max(0, $terminHari) . ' days'));
    }
}
