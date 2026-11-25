<?php
namespace App\Core;

use App\Services\PeriodeService;
use Exception;

/**
 * Waktu transaksi pilihan user untuk penerimaan dan pengeluaran (mis. kerja lewat tengah malam dicatat ke tanggal kemarin).
 * Kosong = sekarang. Boleh tanggal mana pun yang belum ditutup di Tutup Harian, tetapi tidak boleh di masa depan.
 * Nomor dokumen (SJ, barcode, transaksi) mengikuti tanggal yang dipilih.
 */
class WaktuTransaksi
{
    /**
     * @param mixed  $input "YYYY-MM-DDTHH:MM" (input datetime-local) atau "YYYY-MM-DD HH:MM[:SS]"; kosong = sekarang
     * @param string $aksi  untuk pesan error, mis. "mencatat penerimaan"
     * @return string "Y-m-d H:i:s"
     */
    public static function baca($input, string $aksi = 'mencatat transaksi'): string
    {
        $s = trim((string)($input ?? ''));
        if ($s === '') {
            PeriodeService::pastikanTerbuka(date('Y-m-d'), $aksi);
            return date('Y-m-d H:i:s');
        }

        $s = str_replace('T', ' ', $s);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $s)) $s .= ':00';
        $d = \DateTime::createFromFormat('Y-m-d H:i:s', $s);
        if (!$d || $d->format('Y-m-d H:i:s') !== $s) throw new Exception("Format waktu transaksi tidak valid.");

        // toleransi 5 menit untuk selisih jam komputer user dan server
        if ($d->getTimestamp() > time() + 300) throw new Exception("Waktu transaksi tidak boleh melebihi waktu sekarang.");

        PeriodeService::pastikanTerbuka($d->format('Y-m-d'), $aksi);
        return $d->format('Y-m-d H:i:s');
    }

    /** Tanggal (Y-m-d) dari input yang sudah divalidasi ringan, untuk penomoran dokumen sebelum disimpan; kosong/tidak sah = hari ini. */
    public static function tanggalNomor($input): string
    {
        $s = substr(str_replace('T', ' ', trim((string)($input ?? ''))), 0, 10);
        $d = \DateTime::createFromFormat('Y-m-d', $s);
        if (!$d || $d->format('Y-m-d') !== $s || $s > date('Y-m-d')) return date('Y-m-d');
        return $s;
    }
}
