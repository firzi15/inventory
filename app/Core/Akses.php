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

    /** Akun yang boleh membuka menu Laba Rugi (Laba Rugi, Detail Laba, COA, Tutup Harian). Tidak ikut sakelar SEMUA. */
    public const AKUN_LABA_RUGI = ['nia', 'arif'];

    public static function lihatLabaRugi(): bool
    {
        $u = strtolower((string)($_SESSION['user_login']['username'] ?? ''));
        return in_array($u, self::AKUN_LABA_RUGI, true);
    }
}
