<?php
namespace App\Core;

/**
 * Pusat pengecekan hak akses berdasarkan peran.
 * SEMENTARA: SEMUA = true, artinya semua akun yang sudah login boleh mengakses semua fitur
 * (catat kas, rekonsiliasi, batalkan dokumen, tutup/buka hari, ubah jatuh tempo, backup, dst.).
 * Ubah menjadi false untuk kembali ke pembagian peran yang tertulis di tiap pemanggilan.
 */
class Akses
{
    public const SEMUA = true;

    /** @param string[] $diizinkan peran yang diizinkan bila pembagian peran aktif */
    public static function boleh(?string $role, array $diizinkan): bool
    {
        return self::SEMUA || in_array(strtoupper((string)$role), $diizinkan, true);
    }
}
