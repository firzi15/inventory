<?php

namespace App\Core;

/**
 * Rentang tanggal untuk filter laporan (hari ini, minggu ini, bulan ini, tahun berjalan, atau tanggal bebas).
 */
class Periode
{
    public static function sah(?string $tgl): bool
    {
        return is_string($tgl) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl) === 1 && strtotime($tgl) !== false
            && date('Y-m-d', strtotime($tgl)) === $tgl;
    }

    /** Pilihan cepat: kunci => [label, dari, sampai] */
    public static function daftarPreset(): array
    {
        $hariIni = date('Y-m-d');
        $senin = date('Y-m-d', strtotime('monday this week'));
        return [
            'hari'   => ['Hari ini',        $hariIni, $hariIni],
            'minggu' => ['Minggu ini',      $senin, date('Y-m-d', strtotime($senin . ' +6 days'))],
            'bulan'  => ['Bulan ini',       date('Y-m-01'), date('Y-m-t')],
            'ytd'    => ['Tahun ini (YTD)', date('Y-01-01'), $hariIni],
        ];
    }

    /**
     * Baca ?dari=&sampai= dari query. Bila tidak ada/tidak sah dipakai preset bawaan
     * (null = tanpa filter, dikembalikan ['', '']). Bila terbalik, ditukar.
     */
    public static function baca(array $q, ?string $bawaan): array
    {
        $dari = (string)($q['dari'] ?? '');
        $sampai = (string)($q['sampai'] ?? '');
        if (self::sah($dari) && self::sah($sampai)) {
            return $dari <= $sampai ? [$dari, $sampai] : [$sampai, $dari];
        }
        if (self::sah($dari) && $sampai === '') return [$dari, $dari];
        if ($bawaan === null) return ['', ''];
        $p = self::daftarPreset()[$bawaan] ?? self::daftarPreset()['bulan'];
        return [$p[1], $p[2]];
    }
}
