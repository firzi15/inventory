<?php
/**
 * Purchase Book: riwayat pembelian per artikel dari tiap pemasok.
 */

use App\Core\Helper;
use App\Services\BukuService;

$judul = 'Purchase Book';
$pageTitle = $judul;
$labelPihak = 'Pemasok';

$preset = $_GET['periode'] ?? 'tahun_ini';
$hariIni = date('Y-m-d');
switch ($preset) {
    case 'bulan_ini':  $dari = date('Y-m-01'); $sampai = date('Y-m-t'); break;
    case 'tahun_lalu': $dari = (date('Y') - 1) . '-01-01'; $sampai = (date('Y') - 1) . '-12-31'; break;
    case 'semua':      $dari = '2000-01-01'; $sampai = $hariIni; break;
    case 'rentang':
        $dari = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dari'] ?? '') ? $_GET['dari'] : date('Y-01-01');
        $sampai = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sampai'] ?? '') ? $_GET['sampai'] : $hariIni;
        break;
    default: $preset = 'tahun_ini'; $dari = date('Y-01-01'); $sampai = $hariIni;
}
$itemId  = (int)($_GET['item'] ?? 0);
$pihakId = (int)($_GET['pihak'] ?? 0);

$kolomUrut = ['tanggal' => 'tanggal', 'pihak' => 'pihak', 'artikel' => 'nama_item', 'dokumen' => 'dokumen', 'sj' => 'sj', 'kemasan' => 'kemasan',
              'qty' => 'qty', 'harga' => 'harga', 'nilai' => 'nilai', 'selisih' => 'selisih'];
$urut = array_key_exists($_GET['urut'] ?? '', $kolomUrut) ? $_GET['urut'] : 'tanggal';
$arah = ($_GET['arah'] ?? 'naik') === 'turun' ? 'turun' : 'naik';
$f = ['dari' => $dari, 'sampai' => $sampai, 'item_id' => $itemId, 'pihak_id' => $pihakId];
$rows = BukuService::pembelian($f);
$sum = BukuService::ringkas($rows);

// Urutan tampilan tabel. Selisih harga sudah dihitung kronologis, jadi tidak berubah oleh pengurutan.
$kunciUrut = $kolomUrut[$urut];

$rowsTampil = $rows;
usort($rowsTampil, function ($a, $b) use ($kunciUrut, $arah) {
    $x = $a[$kunciUrut] ?? null; $y = $b[$kunciUrut] ?? null;
    if ($x === null || $y === null) { $c = ($x === null) <=> ($y === null); return $c; } // nilai kosong selalu di bawah
    $c = (is_int($x) || is_float($x)) ? $x <=> $y : strcasecmp((string)$x, (string)$y);
    if ($c === 0) $c = strcmp($a['tanggal'], $b['tanggal']);
    return $arah === 'turun' ? -$c : $c;
});
// Halaman: 25 / 50 / 100 / 200 baris, atau Semua (per=0). KPI, ringkasan per pemasok, dan grafik tetap memakai seluruh data.
$opsiPer = [25, 50, 100, 200, 0];
$perGet = isset($_GET['per']) ? (int)$_GET['per'] : 50;
$per = in_array($perGet, $opsiPer, true) ? $perGet : 50;
$total = count($rowsTampil);
$perEf = $per === 0 ? max(1, $total) : $per;
$halMax = max(1, (int)ceil($total / $perEf));
$hal = min($halMax, max(1, (int)($_GET['hal'] ?? 1)));
$rowsHalaman = array_slice($rowsTampil, ($hal - 1) * $perEf, $perEf);
$qs = function (array $x) {
    $q = array_merge($_GET, $x);
    return htmlspecialchars(http_build_query(array_filter($q, fn($v) => $v !== null && $v !== '')));
};

// Pengurutan per pemasok / mitra / artikel menampilkan TOTAL tiap kelompok; urutan lain datar
$grupTampil = [];
if ($urut === 'pihak' || $urut === 'artikel') {
    $kg_ = $urut === 'pihak' ? 'pihak' : 'nama_item';
    foreach ($rowsHalaman as $r) { $grupTampil[$r[$kg_]][] = $r; }
} else {
    $grupTampil[''] = $rowsHalaman;
}
$perPihak = $itemId ? BukuService::perPihak($rows) : [];
$kolomUb = ['pihak', 'transaksi', 'qty', 'nilai', 'min', 'max', 'rata', 'terakhir'];
$ub = in_array($_GET['ub'] ?? '', $kolomUb, true) ? $_GET['ub'] : 'pihak';
$ab = ($_GET['ab'] ?? 'naik') === 'turun' ? 'turun' : 'naik';
if ($ub === 'pihak') { $ab === 'turun' ? krsort($perPihak, SORT_NATURAL | SORT_FLAG_CASE) : ksort($perPihak, SORT_NATURAL | SORT_FLAG_CASE); }
else { uasort($perPihak, fn($a, $b) => $ab === 'turun' ? $b[$ub] <=> $a[$ub] : $a[$ub] <=> $b[$ub]); }

// Judul kolom yang bisa diklik: tautan mempertahankan semua filter yang sedang aktif
$tautanUrut = function (string $kunciParam, string $kunciArah, string $kolom, string $label, string $sekarang, string $arahSekarang) {
    $baru = ($sekarang === $kolom && $arahSekarang === 'naik') ? 'turun' : 'naik';
    $q = array_merge($_GET, [$kunciParam => $kolom, $kunciArah => $baru, 'hal' => 1]);
    $aktif = $sekarang === $kolom;
    return '<a class="th-sort ' . ($aktif ? 'aktif' : '') . '" href="index.php?' . htmlspecialchars(http_build_query($q)) . '">' . $label
         . '<span class="panah">' . ($aktif ? ($arahSekarang === 'naik' ? '&#9650;' : '&#9660;') : '&#8645;') . '</span></a>';
};
$thUrut  = fn(string $kolom, string $label, bool $kanan = false) => '<th' . ($kanan ? ' style="text-align: right;"' : '') . '>' . $tautanUrut('urut', 'arah', $kolom, $label, $urut, $arah) . '</th>';
$thPihak = fn(string $kolom, string $label, bool $kanan = false) => '<th' . ($kanan ? ' style="text-align: right;"' : '') . '>' . $tautanUrut('ub', 'ab', $kolom, $label, $ub, $ab) . '</th>';
$daftarPihak = BukuService::daftarPemasok();
$daftarArtikel = BukuService::daftarArtikel();
$namaArtikel = '';
foreach ($daftarArtikel as $a) { if ((int)$a['id'] === $itemId) $namaArtikel = $a['nama']; }

$rp = fn($n) => Helper::formatRupiah($n);
$kg = fn($n) => number_format($n, 2, ',', '.') . ' kg';
$tglFmt = fn($d) => date('d/m/Y', strtotime($d));

// Data grafik (hanya jika satu artikel dipilih): satu garis per pemasok / mitra
$chart = ['labels' => [], 'series' => []];
if ($itemId && $rows) {
    $tgls = array_values(array_unique(array_column($rows, 'tanggal')));
    sort($tgls);
    $chart['labels'] = array_map($tglFmt, $tgls);
    $tmp = [];
    foreach ($rows as $r) { $tmp[$r['pihak']][$r['tanggal']] = $r['harga']; }
    foreach ($tmp as $nama => $perTgl) {
        $data = [];
        foreach ($tgls as $t) { $data[] = $perTgl[$t] ?? null; }
        $chart['series'][] = ['label' => $nama, 'data' => $data];
    }
}

$eksporNama = str_replace(' ', '_', $judul) . ($namaArtikel ? '_' . preg_replace('/[^A-Za-z0-9]+/', '_', $namaArtikel) : '');
$eksporJudul = $judul . ($namaArtikel ? ' - ' . $namaArtikel : '') . ' (' . $tglFmt($dari) . ' s/d ' . $tglFmt($sampai) . ')';

require_once __DIR__ . '/layout/header.php';
?>

<div class="content-wrapper">

    <div class="page-header no-print">
        <div>
            <h1 class="page-title"><?= $judul ?></h1>
            <p class="page-subtitle">Riwayat harga beli tiap produk dari setiap pemasok</p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <?php include __DIR__ . '/layout/ekspor.php'; ?>
        </div>
    </div>

    <form method="GET" class="card no-print" style="padding: 14px 16px; margin-bottom: 18px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="page" value="purchase_book">
        <div style="min-width: 190px;">
            <label class="form-label">Periode</label>
            <select name="periode" class="form-control" onchange="this.form.submit()">
                <option value="tahun_ini" <?= $preset === 'tahun_ini' ? 'selected' : '' ?>>Awal tahun s/d hari ini</option>
                <option value="bulan_ini" <?= $preset === 'bulan_ini' ? 'selected' : '' ?>>Bulan ini</option>
                <option value="tahun_lalu" <?= $preset === 'tahun_lalu' ? 'selected' : '' ?>>Tahun lalu</option>
                <option value="semua" <?= $preset === 'semua' ? 'selected' : '' ?>>Semua</option>
                <option value="rentang" <?= $preset === 'rentang' ? 'selected' : '' ?>>Rentang tanggal</option>
            </select>
        </div>
        <?php if ($preset === 'rentang'): ?>
        <div>
            <label class="form-label">Dari</label>
            <input type="date" name="dari" value="<?= $dari ?>" class="form-control">
        </div>
        <div>
            <label class="form-label">Sampai</label>
            <input type="date" name="sampai" value="<?= $sampai ?>" class="form-control">
        </div>
        <?php endif; ?>
        <div style="min-width: 260px;">
            <label class="form-label">Artikel</label>
            <select name="item" class="form-control" onchange="this.form.submit()">
                <option value="0">-- Semua artikel --</option>
                <?php foreach ($daftarArtikel as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= (int)$a['id'] === $itemId ? 'selected' : '' ?>><?= htmlspecialchars($a['kode'] . ' - ' . $a['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="min-width: 220px;">
            <label class="form-label"><?= $labelPihak ?></label>
            <select name="pihak" class="form-control" onchange="this.form.submit()">
                <option value="0">-- Semua <?= strtolower($labelPihak) ?> --</option>
                <?php foreach ($daftarPihak as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= (int)$p['id'] === $pihakId ? 'selected' : '' ?>><?= htmlspecialchars('[' . $p['kode'] . '] ' . $p['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="urut" value="<?= $urut ?>">
        <input type="hidden" name="arah" value="<?= $arah ?>">
        <?php if ($preset === 'rentang'): ?>
            <button type="submit" class="btn btn-primary">Tampilkan</button>
        <?php endif; ?>
    </form>

    <div class="grid grid-cols-4 no-print" style="gap: 16px; margin-bottom: 18px;">
        <div class="kpi-card"><div class="kpi-body"><div class="kpi-label">Jumlah Baris</div><div class="kpi-value"><?= number_format($sum['baris'], 0, ',', '.') ?></div></div></div>
        <div class="kpi-card"><div class="kpi-body"><div class="kpi-label">Total Qty</div><div class="kpi-value"><?= $kg($sum['qty']) ?></div></div></div>
        <div class="kpi-card"><div class="kpi-body"><div class="kpi-label">Total Nilai</div><div class="kpi-value"><?= $rp($sum['nilai']) ?></div></div></div>
        <div class="kpi-card"><div class="kpi-body"><div class="kpi-label">Rata-rata Harga/kg</div><div class="kpi-value"><?= $rp($sum['rata']) ?></div>
            <?php if ($itemId && $rows): ?><div class="kpi-sub">Terendah <?= $rp($sum['min']) ?> &bull; Tertinggi <?= $rp($sum['max']) ?> &bull; Terakhir <?= $rp($sum['terakhir']) ?></div><?php endif; ?>
        </div></div>
    </div>

    <?php if ($itemId && $chart['series']): ?>
    <div class="card no-print" style="margin-bottom: 18px; padding: 16px 18px;">
        <div style="font-weight: 800; margin-bottom: 10px;">Tren harga per kg &middot; <?= htmlspecialchars($namaArtikel) ?></div>
        <div style="position: relative; height: 260px;"><canvas id="chartHarga"></canvas></div>
    </div>
    <?php endif; ?>

    <?php if ($itemId && count($perPihak) > 0): ?>
    <div class="card" style="margin-bottom: 18px;">
        <div style="padding: 14px 20px; border-bottom: 1px solid var(--border); font-weight: 800;">Perbandingan per <?= strtolower($labelPihak) ?> &middot; <?= htmlspecialchars($namaArtikel) ?></div>
        <div class="table-responsive">
            <table class="table-custom" data-no-tools>
                <thead>
                    <tr>
                        <?= $thPihak('pihak', $labelPihak) ?>
                        <?= $thPihak('transaksi', 'Transaksi', true) ?>
                        <?= $thPihak('qty', 'Qty', true) ?>
                        <?= $thPihak('nilai', 'Nilai', true) ?>
                        <?= $thPihak('min', 'Harga Terendah', true) ?>
                        <?= $thPihak('max', 'Harga Tertinggi', true) ?>
                        <?= $thPihak('rata', 'Harga Rata-rata', true) ?>
                        <?= $thPihak('terakhir', 'Harga Terakhir', true) ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($perPihak as $nama => $v): ?>
                        <tr>
                            <td style="font-weight: 600;"><?= htmlspecialchars($nama) ?></td>
                            <td style="text-align: right;"><?= $v['transaksi'] ?></td>
                            <td style="text-align: right;"><?= $kg($v['qty']) ?></td>
                            <td style="text-align: right;"><?= $rp($v['nilai']) ?></td>
                            <td style="text-align: right;"><?= $rp($v['min']) ?></td>
                            <td style="text-align: right;"><?= $rp($v['max']) ?></td>
                            <td style="text-align: right;"><?= $rp($v['rata']) ?></td>
                            <td style="text-align: right; font-weight: 700;"><?= $rp($v['terakhir']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="table-responsive">
            <table class="table-custom" data-no-tools>
                <thead>
                    <tr>
                        <th style="width: 44px; text-align: center;">No</th>
                        <?= $thUrut('tanggal', 'Tanggal') ?>
                        <?= $thUrut('pihak', $labelPihak) ?>
                        <?= $thUrut('artikel', 'Artikel') ?>
                        <?= $thUrut('dokumen', 'No. Faktur') ?>
                        <?= $thUrut('sj', 'No. Surat Jalan') ?>
                        <?= $thUrut('kemasan', 'Kemasan', true) ?>
                        <?= $thUrut('qty', 'Qty', true) ?>
                        <?= $thUrut('harga', 'Harga/kg', true) ?>
                        <?= $thUrut('nilai', 'Nilai', true) ?>
                        <?= $thUrut('selisih', 'Selisih Harga', true) ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="11" style="text-align: center; padding: 28px; color: var(--text-muted);">Tidak ada data pada filter ini</td></tr>
                    <?php endif; ?>
                    <?php
                    $no = ($hal - 1) * $perEf;
                    foreach ($grupTampil as $namaGrup => $listGrup):
                        foreach ($listGrup as $r): ?>
                        <tr>
                            <td style="text-align: center; color: var(--text-muted);"><?= ++$no ?></td>
                            <td><?= $tglFmt($r['tanggal']) ?></td>
                            <td><?= htmlspecialchars($r['pihak']) ?></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($r['nama_item']) ?></td>
                            <td><?= htmlspecialchars($r['dokumen']) ?></td>
                            <td><?= htmlspecialchars($r['sj']) ?></td>
                            <td style="text-align: right;"><?= $r['kemasan'] ?></td>
                            <td style="text-align: right;"><?= $kg($r['qty']) ?></td>
                            <td style="text-align: right; font-weight: 700;"><?= $rp($r['harga']) ?></td>
                            <td style="text-align: right;"><?= $rp($r['nilai']) ?></td>
                            <td style="text-align: right; color: <?= $r['selisih'] === null ? 'inherit' : ($r['selisih'] > 0 ? '#b91c1c' : ($r['selisih'] < 0 ? '#15803d' : 'inherit')) ?>;">
                                <?php if ($r['selisih'] === null): ?>-
                                <?php elseif ($r['selisih'] == 0): ?>tetap
                                <?php else: ?><?= ($r['selisih'] > 0 ? 'naik ' : 'turun ') . $rp(abs($r['selisih'])) ?><?= $r['persen'] !== null ? ' (' . ($r['selisih'] > 0 ? '+' : '-') . number_format(abs($r['persen']), 1, ',', '.') . '%)' : '' ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach;
                    endforeach;
                    ?>
                </tbody>
            </table>
        </div>
        <div class="sj-halaman no-print">
            <div class="sj-halaman-info">Menampilkan <?= $total ? ($hal - 1) * $perEf + 1 : 0 ?>-<?= min($total, $hal * $perEf) ?> dari <?= $total ?> baris</div>
            <div class="sj-halaman-nomor">
                <?php
                $tautan = function ($h, $label, $aktif = false, $mati = false) use ($qs) {
                    if ($mati) return '<span class="sj-hal mati">' . $label . '</span>';
                    return '<a class="sj-hal ' . ($aktif ? 'aktif' : '') . '" href="index.php?' . $qs(['hal' => $h]) . '">' . $label . '</a>';
                };
                echo $tautan(1, '&laquo;', false, $hal <= 1) . $tautan($hal - 1, '&lsaquo;', false, $hal <= 1);
                $mulai = max(1, $hal - 2); $akhir = min($halMax, $hal + 2);
                if ($mulai > 1) echo $tautan(1, '1') . ($mulai > 2 ? '<span class="sj-hal mati">&hellip;</span>' : '');
                for ($i = $mulai; $i <= $akhir; $i++) echo $tautan($i, (string)$i, $i === $hal);
                if ($akhir < $halMax) echo ($akhir < $halMax - 1 ? '<span class="sj-hal mati">&hellip;</span>' : '') . $tautan($halMax, (string)$halMax);
                echo $tautan($hal + 1, '&rsaquo;', false, $hal >= $halMax) . $tautan($halMax, '&raquo;', false, $hal >= $halMax);
                ?>
            </div>
            <div class="sj-halaman-per"><label for="bkPer">Per halaman</label>
                <select id="bkPer" class="form-control" style="width: auto; display: inline-block;" onchange="location.href=this.value">
                    <?php foreach ($opsiPer as $o): ?><option value="index.php?<?= $qs(['per' => $o, 'hal' => 1]) ?>" <?= $o === $per ? 'selected' : '' ?>><?= $o === 0 ? 'Semua' : $o ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>


</div>

<?php if ($itemId && $chart['series']): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var cv = document.getElementById('chartHarga');
    if (!cv || typeof Chart === 'undefined') return;
    var warna = ['#0284c7', '#10b981', '#f59e0b', '#ef4444', '#0ea5e9', '#0369a1', '#14b8a6'];
    var d = <?= json_encode($chart) ?>;
    new Chart(cv, {
        type: 'line',
        data: {
            labels: d.labels,
            datasets: d.series.map(function (s, i) {
                return { label: s.label, data: s.data, borderColor: warna[i % warna.length], backgroundColor: warna[i % warna.length],
                         tension: 0.25, spanGaps: true, pointRadius: 4 };
            })
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { ticks: { callback: function (v) { return 'Rp ' + Number(v).toLocaleString('en-US'); } } } }
        }
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
