<?php
/**
 * Purchase Book (pembelian per artikel per pemasok) dan Sales Book (penjualan per artikel per mitra).
 * Dipakai bersama; mode ditentukan dari halaman yang dibuka.
 */

use App\Core\Helper;
use App\Services\BukuService;

$jual = ($page ?? '') === 'sales_book';
$judul = $jual ? 'Sales Book' : 'Purchase Book';
$pageTitle = $judul;
$labelPihak = $jual ? 'Mitra' : 'Pemasok';

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

$urut = in_array($_GET['urut'] ?? '', ['tanggal', 'pihak', 'harga', 'artikel'], true) ? $_GET['urut'] : 'tanggal';
$arah = ($_GET['arah'] ?? 'naik') === 'turun' ? 'turun' : 'naik';
$f = ['dari' => $dari, 'sampai' => $sampai, 'item_id' => $itemId, 'pihak_id' => $pihakId];
$rows = $jual ? BukuService::penjualan($f) : BukuService::pembelian($f);
$sum = BukuService::ringkas($rows);

// Urutan tampilan tabel. Selisih harga sudah dihitung kronologis, jadi tidak berubah oleh pengurutan.
$kunciUrut = ['tanggal' => 'tanggal', 'pihak' => 'pihak', 'harga' => 'harga', 'artikel' => 'nama_item'][$urut];
$rowsTampil = $rows;
usort($rowsTampil, function ($a, $b) use ($kunciUrut, $arah) {
    $c = is_numeric($a[$kunciUrut]) && !is_string($a[$kunciUrut]) ? $a[$kunciUrut] <=> $b[$kunciUrut] : strcasecmp((string)$a[$kunciUrut], (string)$b[$kunciUrut]);
    if ($c === 0) $c = strcmp($a['tanggal'], $b['tanggal']);
    return $arah === 'turun' ? -$c : $c;
});
// Pengurutan per pemasok / mitra / artikel menampilkan TOTAL tiap kelompok; urutan lain datar
$grupTampil = [];
if ($urut === 'pihak' || $urut === 'artikel') {
    $kg_ = $urut === 'pihak' ? 'pihak' : 'nama_item';
    foreach ($rowsTampil as $r) { $grupTampil[$r[$kg_]][] = $r; }
} else {
    $grupTampil[''] = $rowsTampil;
}
$perPihak = $itemId ? BukuService::perPihak($rows) : [];
$daftarPihak = $jual ? BukuService::daftarMitra() : BukuService::daftarPemasok();
$daftarArtikel = BukuService::daftarArtikel();
$namaArtikel = '';
foreach ($daftarArtikel as $a) { if ((int)$a['id'] === $itemId) $namaArtikel = $a['nama']; }

$logHarga = \App\Services\LogHargaService::daftar([
    'jenis' => $jual ? 'MITRA' : 'PEMASOK', 'item_id' => $itemId, 'pihak_id' => $pihakId, 'dari' => $dari, 'sampai' => $sampai, 'limit' => 300,
]);
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
            <p class="page-subtitle"><?= $jual
                ? 'Riwayat harga jual per artikel ke setiap mitra, lengkap dengan HPP dan margin'
                : 'Riwayat harga beli per artikel dari setiap pemasok' ?></p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <?php include __DIR__ . '/layout/ekspor.php'; ?>
        </div>
    </div>

    <form method="GET" class="card no-print" style="padding: 14px 16px; margin-bottom: 18px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="page" value="<?= $jual ? 'sales_book' : 'purchase_book' ?>">
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
        <div style="min-width: 170px;">
            <label class="form-label">Urutkan</label>
            <select name="urut" class="form-control" onchange="this.form.submit()">
                <option value="tanggal" <?= $urut === 'tanggal' ? 'selected' : '' ?>>Tanggal</option>
                <option value="pihak" <?= $urut === 'pihak' ? 'selected' : '' ?>><?= $labelPihak ?></option>
                <option value="harga" <?= $urut === 'harga' ? 'selected' : '' ?>>Harga</option>
                <option value="artikel" <?= $urut === 'artikel' ? 'selected' : '' ?>>Nama artikel</option>
            </select>
        </div>
        <div style="min-width: 130px;">
            <label class="form-label">Arah</label>
            <select name="arah" class="form-control" onchange="this.form.submit()">
                <option value="naik" <?= $arah === 'naik' ? 'selected' : '' ?>>Naik (A-Z, kecil-besar)</option>
                <option value="turun" <?= $arah === 'turun' ? 'selected' : '' ?>>Turun (Z-A, besar-kecil)</option>
            </select>
        </div>
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
                        <th><?= $labelPihak ?></th>
                        <th style="text-align: right;">Transaksi</th>
                        <th style="text-align: right;">Qty</th>
                        <th style="text-align: right;">Nilai</th>
                        <th style="text-align: right;">Harga Terendah</th>
                        <th style="text-align: right;">Harga Tertinggi</th>
                        <th style="text-align: right;">Harga Rata-rata</th>
                        <th style="text-align: right;">Harga Terakhir</th>
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
                        <th>Tanggal</th>
                        <th><?= $labelPihak ?></th>
                        <th>Artikel</th>
                        <th><?= $jual ? 'No. Surat Jalan' : 'No. Faktur' ?></th>
                        <?php if (!$jual): ?><th>No. Surat Jalan</th><?php endif; ?>
                        <th style="text-align: right;">Kemasan</th>
                        <th style="text-align: right;">Qty</th>
                        <th style="text-align: right;">Harga/kg</th>
                        <th style="text-align: right;">Nilai</th>
                        <th style="text-align: right;">Selisih Harga</th>
                        <?php if ($jual): ?>
                            <th style="text-align: right;">HPP/kg</th>
                            <th style="text-align: right;">Margin</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="<?= $jual ? 11 : 10 ?>" style="text-align: center; padding: 28px; color: var(--text-muted);">Tidak ada data pada filter ini</td></tr>
                    <?php endif; ?>
                    <?php
                    // satu baris total (dipakai untuk TOTAL kelompok dan GRAND TOTAL)
                    $barisTotal = function (string $label, array $list, bool $besar) use ($jual, $kg, $rp) {
                        $q = array_sum(array_column($list, 'qty'));
                        $n = array_sum(array_column($list, 'nilai'));
                        $k = array_sum(array_column($list, 'kemasan'));
                        $h = array_sum(array_column($list, 'hpp'));
                        $gaya = $besar ? 'font-weight: 800; background: var(--primary); color: #fff;' : 'font-weight: 700; background: rgba(79, 70, 229, 0.06);';
                        echo '<tr style="' . $gaya . '"><td colspan="' . ($jual ? 4 : 5) . '" style="text-align: right;">' . htmlspecialchars($label) . '</td>';
                        echo '<td style="text-align: right;">' . $k . '</td><td style="text-align: right;">' . $kg($q) . '</td>';
                        echo '<td style="text-align: right;">' . $rp($q > 0 ? $n / $q : 0) . '</td><td style="text-align: right;">' . $rp($n) . '</td><td></td>';
                        if ($jual) {
                            echo '<td style="text-align: right;">' . $rp($q > 0 ? $h / $q : 0) . '</td>';
                            echo '<td style="text-align: right;">' . ($n > 0 ? number_format(($n - $h) / $n * 100, 1, ',', '.') . '%' : '-') . '</td>';
                        }
                        echo '</tr>';
                    };
                    foreach ($grupTampil as $namaGrup => $listGrup):
                        foreach ($listGrup as $r):
                        $hppKg = $r['qty'] > 0 ? $r['hpp'] / $r['qty'] : 0;
                        $margin = $r['nilai'] > 0 ? ($r['nilai'] - $r['hpp']) / $r['nilai'] * 100 : 0; ?>
                        <tr>
                            <td><?= $tglFmt($r['tanggal']) ?></td>
                            <td><?= htmlspecialchars($r['pihak']) ?></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($r['nama_item']) ?></td>
                            <td><?= htmlspecialchars($r['dokumen']) ?></td>
                            <?php if (!$jual): ?><td><?= htmlspecialchars($r['sj']) ?></td><?php endif; ?>
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
                            <?php if ($jual): ?>
                                <td style="text-align: right;"><?= $rp($hppKg) ?></td>
                                <td style="text-align: right; color: <?= $margin < 0 ? '#b91c1c' : 'inherit' ?>;"><?= number_format($margin, 1, ',', '.') ?>%</td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach;
                        if ($namaGrup !== '') $barisTotal('TOTAL ' . mb_strtoupper($namaGrup), $listGrup, false);
                    endforeach;
                    if ($rows) $barisTotal('GRAND TOTAL', $rows, true);
                    ?>
                </tbody>
            </table>
        </div>
    </div>


    <div class="card" style="margin-top: 18px;">
        <div style="padding: 14px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
            <div style="font-weight: 800;">Log perubahan harga master <?= $jual ? '(harga jual mitra)' : '(harga beli pemasok)' ?></div>
            <a href="index.php?page=master&tab=log_harga&jenis=<?= $jual ? 'MITRA' : 'PEMASOK' ?><?= $itemId ? '&item=' . $itemId : '' ?>" class="no-print" style="font-size: 12.5px; color: var(--primary); font-weight: 700;">Lihat semua di Master</a>
        </div>
        <div class="table-responsive">
            <table class="table-custom" data-no-tools>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th><?= $labelPihak ?></th>
                        <th>Artikel</th>
                        <th style="text-align: right;">Harga Lama</th>
                        <th style="text-align: right;">Harga Baru</th>
                        <th style="text-align: right;">Selisih</th>
                        <th>Diubah Oleh</th>
                        <th>Alasan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$logHarga): ?>
                        <tr><td colspan="8" style="text-align: center; padding: 24px; color: var(--text-muted);">Tidak ada perubahan harga master pada filter ini</td></tr>
                    <?php endif; ?>
                    <?php foreach ($logHarga as $lg): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($lg['waktu'])) ?></td>
                            <td><?= htmlspecialchars((string)$lg['pihak']) ?></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($lg['nama_item']) ?></td>
                            <td style="text-align: right;"><?= $lg['harga_lama'] === null ? '-' : $rp($lg['harga_lama']) ?></td>
                            <td style="text-align: right; font-weight: 700;"><?= $rp($lg['harga_baru']) ?></td>
                            <td style="text-align: right; color: <?= $lg['selisih'] === null ? 'inherit' : ($lg['selisih'] > 0 ? '#b91c1c' : '#15803d') ?>;">
                                <?php if ($lg['selisih'] === null): ?>harga awal
                                <?php else: ?><?= ($lg['selisih'] > 0 ? 'naik ' : 'turun ') . $rp(abs($lg['selisih'])) ?><?= $lg['persen'] !== null ? ' (' . ($lg['selisih'] > 0 ? '+' : '-') . number_format(abs($lg['persen']), 1, ',', '.') . '%)' : '' ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars((string)($lg['oleh'] ?? '-')) ?></td>
                            <td style="font-size: 12.5px; color: var(--text-muted);"><?= htmlspecialchars((string)($lg['alasan'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php if ($itemId && $chart['series']): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var cv = document.getElementById('chartHarga');
    if (!cv || typeof Chart === 'undefined') return;
    var warna = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#0ea5e9', '#8b5cf6', '#14b8a6'];
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
            scales: { y: { ticks: { callback: function (v) { return 'Rp ' + Number(v).toLocaleString('id-ID'); } } } }
        }
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
