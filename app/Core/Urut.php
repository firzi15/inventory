<?php
namespace App\Core;

/**
 * Pengurutan tabel dari server (berlaku untuk seluruh halaman, bukan hanya halaman yang tampil).
 * Parameter URL: urut_{nama}=kunci kolom, arah_{nama}=asc|desc. Kunci kolom dicocokkan dengan daftar yang diizinkan
 * (kunci => ekspresi SQL), jadi nilai dari URL tidak pernah masuk ke SQL secara langsung.
 */
class Urut
{
    /**
     * Klausa ORDER BY untuk tabel $nama, atau $bawaan bila tidak ada pengurutan yang sah di URL.
     * @param array<string,string> $kolom kunci => ekspresi SQL
     */
    public static function sql(string $nama, array $kolom, string $bawaan): string
    {
        [$k, $a] = self::aktif($nama);
        if ($k === '' || !isset($kolom[$k])) return $bawaan;
        return $kolom[$k] . ' ' . strtoupper($a) . ', ' . $bawaan;
    }

    /** @return array{0:string,1:string} kunci dan arah yang sedang aktif */
    public static function aktif(string $nama): array
    {
        $k = preg_replace('/[^a-z0-9_]/', '', (string)($_GET['urut_' . $nama] ?? ''));
        $a = ($_GET['arah_' . $nama] ?? '') === 'asc' ? 'asc' : 'desc';
        return [$k, $a];
    }

    /** Potongan query string pengurutan yang sedang aktif (semua tabel), untuk ditempel ke tautan halaman. */
    public static function kueri(): string
    {
        $q = '';
        foreach ($_GET as $k => $v) {
            if (is_string($v) && preg_match('/^(urut|arah)_[a-z0-9_]+$/', (string)$k)) $q .= '&' . $k . '=' . urlencode($v);
        }
        return $q;
    }

    /** Input tersembunyi pengurutan yang sedang aktif, untuk form filter GET. */
    public static function hidden(): string
    {
        $h = '';
        foreach ($_GET as $k => $v) {
            if (is_string($v) && preg_match('/^(urut|arah)_[a-z0-9_]+$/', (string)$k)) {
                $h .= '<input type="hidden" name="' . htmlspecialchars((string)$k) . '" value="' . htmlspecialchars($v) . '">';
            }
        }
        return $h;
    }

    /**
     * Sel judul yang bisa diklik. Klik pertama mengurutkan naik (A-Z, kecil ke besar), klik berikutnya membalik arah.
     * $halaman: nama parameter nomor halaman tabel ini; dihapus agar kembali ke halaman 1.
     * $tambahan: parameter yang harus ikut di tautan (mis. sub-tab yang dipilih tanpa memuat ulang halaman).
     */
    public static function th(string $label, string $nama, string $kunci, string $halaman, string $attr = '', array $tambahan = []): string
    {
        [$k, $a] = self::aktif($nama);
        $aktif = $k === $kunci;
        $arahBaru = $aktif && $a === 'asc' ? 'desc' : 'asc';
        $q = array_merge($_GET, $tambahan);
        unset($q[$halaman]);
        $q['urut_' . $nama] = $kunci;
        $q['arah_' . $nama] = $arahBaru;
        $url = 'index.php?' . http_build_query($q);
        // panah diberi no-print agar tidak ikut ke cetakan dan ekspor Excel/PDF
        $panah = '<span class="no-print"' . ($aktif ? '>' . ($a === 'asc' ? ' &#9650;' : ' &#9660;') : ' style="opacity:.35;"> &#8693;') . '</span>';
        $judul = $aktif ? ($a === 'asc' ? 'Urut naik; klik untuk urut turun' : 'Urut turun; klik untuk urut naik') : 'Klik untuk mengurutkan';
        return '<th ' . $attr . '><a href="' . htmlspecialchars($url) . '" title="' . $judul . '" style="color: inherit; text-decoration: none; white-space: nowrap;">'
             . $label . $panah . '</a></th>';
    }
}
