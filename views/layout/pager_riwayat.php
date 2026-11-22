<?php
/**
 * Bilah halaman seragam untuk daftar riwayat (gaya sama dengan Barang Datang).
 * renderPagerRiwayat(halaman_kini, jumlah_halaman, teks_info, url_dasar, nama_parameter_halaman)
 */
if (!function_exists('renderPagerRiwayat')) {
    function renderPagerRiwayat(int $hal, int $halMax, string $info, string $urlDasar, string $param): void
    {
        $urlDasar .= \App\Core\Urut::kueri(); // urutan kolom yang sedang dipakai ikut ke halaman lain
        $halMax = max(1, $halMax);
        $hal = min(max(1, $hal), $halMax);
        $tautan = function (int $h, string $label, bool $aktif = false, bool $mati = false) use ($urlDasar, $param): string {
            if ($mati) return '<span class="sj-hal mati">' . $label . '</span>';
            return '<a class="sj-hal ' . ($aktif ? 'aktif' : '') . '" href="' . htmlspecialchars($urlDasar . '&' . $param . '=' . $h) . '">' . $label . '</a>';
        };
        echo '<div class="sj-halaman no-print"><div class="sj-halaman-info">' . htmlspecialchars($info) . '</div><div class="sj-halaman-nomor">';
        echo $tautan(1, '&laquo;', false, $hal <= 1) . $tautan($hal - 1, '&lsaquo;', false, $hal <= 1);
        $mulai = max(1, $hal - 2);
        $akhir = min($halMax, $hal + 2);
        if ($mulai > 1) echo $tautan(1, '1') . ($mulai > 2 ? '<span class="sj-hal mati">&hellip;</span>' : '');
        for ($i = $mulai; $i <= $akhir; $i++) echo $tautan($i, (string)$i, $i === $hal);
        if ($akhir < $halMax) echo ($akhir < $halMax - 1 ? '<span class="sj-hal mati">&hellip;</span>' : '') . $tautan($halMax, (string)$halMax);
        echo $tautan($hal + 1, '&rsaquo;', false, $hal >= $halMax) . $tautan($halMax, '&raquo;', false, $hal >= $halMax);
        echo '</div><div></div></div>';
    }
}
