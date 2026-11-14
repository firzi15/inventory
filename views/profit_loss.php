<?php
/**
 * Laba Rugi - seluruh angka dari jurnal (akrual).
 * 4000 Penjualan, 5000 HPP, 6000 Biaya Operasional.
 */

use App\Core\Helper;
use App\Services\CoaService;
use App\Services\JurnalService;

$pageTitle = 'Laba Rugi';

$tab = ($_GET['tab'] ?? '') === 'laba_sj' || ($_GET['tab'] ?? '') === 'riwayat_sj' ? 'laba_sj' : 'ringkasan';

$tahunList = JurnalService::tahunTersedia();
$tahun = (int)($_GET['year'] ?? date('Y'));
if (!in_array($tahun, $tahunList, true)) $tahun = (int)date('Y');
// bulan 0 = setahun penuh
$bulan = isset($_GET['month']) ? (int)$_GET['month'] : ($tahun === (int)date('Y') ? (int)date('n') : 12);
if ($bulan < 0 || $bulan > 12) $bulan = 0;

$namaBulan = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
              7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
$bulanPendek = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];

$rentang = function (int $th, int $bl): array {
    if ($bl === 0) return [sprintf('%04d-01-01', $th), sprintf('%04d-12-31', $th)];
    $awal = sprintf('%04d-%02d-01', $th, $bl);
    return [$awal, date('Y-m-t', strtotime($awal))];
};
[$dari, $sampai] = $rentang($tahun, $bulan);
$labelPeriode = $bulan === 0 ? "Tahun $tahun" : $namaBulan[$bulan] . " $tahun";

$hitung = function (array $a): array {
    $jual = (float)$a['4000']; $hpp = (float)$a['5000']; $biaya = (float)$a['6000'];
    $kotor = $jual - $hpp;
    return ['jual' => $jual, 'hpp' => $hpp, 'kotor' => $kotor, 'biaya' => $biaya, 'bersih' => $kotor - $biaya];
};
$pct = fn(float $a, float $b): string => $b != 0.0 ? number_format($a / $b * 100, 1, ',', '.') . '%' : '-';
$rp = fn($n) => Helper::formatRupiah($n);
$warna = fn($n) => $n < 0 ? '#b91c1c' : 'inherit';
$linkAkun = fn(string $akun, string $d, string $s) => 'index.php?page=coa&akun=' . $akun . '&dari=' . $d . '&sampai=' . $s;

require_once __DIR__ . '/layout/header.php';
?>

<div class="content-wrapper">

    <div class="page-header no-print">
        <div>
            <h1 class="page-title"><?= $tab === 'laba_sj' ? 'Laba per Surat Jalan' : 'Laba Rugi' ?></h1>
            <p class="page-subtitle">Dihitung dari jurnal: penjualan diakui saat barang keluar, HPP memakai harga beli yang sudah dibekukan</p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
        <?php $eksporNama = ($tab === 'laba_sj' ? 'Laba_per_Surat_Jalan' : 'Laba_Rugi') . '_' . $tahun . ($bulan ? sprintf('-%02d', $bulan) : ''); $eksporJudul = ($tab === 'laba_sj' ? 'Laba per Surat Jalan ' : 'Laba Rugi ') . $labelPeriode; include __DIR__ . '/layout/ekspor.php'; ?>
        <form method="GET" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="page" value="profit_loss">
            <input type="hidden" name="tab" value="<?= $tab ?>">
            <select name="year" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <?php foreach ($tahunList as $t): ?>
                    <option value="<?= $t ?>" <?= $t === $tahun ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
            <select name="month" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="0" <?= $bulan === 0 ? 'selected' : '' ?>>Setahun</option>
                <?php foreach ($namaBulan as $n => $nm): ?>
                    <option value="<?= $n ?>" <?= $n === $bulan ? 'selected' : '' ?>><?= $nm ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        </div>
    </div>

    <?php if ($tab === 'ringkasan'):
        $bulanan = JurnalService::labaRugiBulanan($tahun);
        $hasilBulan = [];
        $ytd = ['4000' => 0.0, '5000' => 0.0, '6000' => 0.0];
        foreach ($bulanan as $m => $a) {
            $hasilBulan[$m] = $hitung($a);
            foreach ($ytd as $k => $_) $ytd[$k] += $a[$k];
        }
        $hasilYtd = $hitung($ytd);

        // Pemeriksaan terhadap COA (seluruh periode)
        $semua = JurnalService::labaRugiRentang('1900-01-01', '2999-12-31');
        $hs = $hitung($semua);
        $saldoCoa = CoaService::balances();
        $grupCoa = ['REVENUE' => 0.0, 'COGS' => 0.0, 'EXPENSE' => 0.0];
        foreach (CoaService::accounts() as $kodeC => $c) {
            if (!isset($grupCoa[$c[6]])) continue;
            $grupCoa[$c[6]] += ($c[2] === CoaService::TIPE[$c[6]][2] ? 1 : -1) * $saldoCoa[$kodeC];
        }
        $labaCoa = $grupCoa['REVENUE'] - $grupCoa['COGS'] - $grupCoa['EXPENSE'];
        $cocok = abs($labaCoa - $hs['bersih']) < 1;

        $detailMitra  = JurnalService::detailPerMitra($dari, $sampai);
        $detailVendor = JurnalService::detailPerVendor($dari, $sampai);
    ?>

    <div class="card" style="margin-bottom: 20px;">
        <div style="padding: 14px 20px; border-bottom: 1px solid var(--border); font-weight: 800;">Laba Rugi <?= $tahun ?> per bulan</div>
        <div class="table-responsive">
            <table class="table-custom" data-no-tools style="min-width: 1250px;">
                <thead>
                    <tr>
                        <th style="min-width: 190px;">Keterangan</th>
                        <?php foreach ($bulanPendek as $n => $nm): ?>
                            <th style="text-align: right; <?= $n === $bulan ? 'background: var(--bg-muted);' : '' ?>"><?= $nm ?></th>
                        <?php endforeach; ?>
                        <th style="text-align: right;">Total <?= $tahun ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $akunBulan = JurnalService::labaRugiAkunBulanan($tahun);
                    $coaAkun = CoaService::accounts();
                    $tipeGrup = ['4000' => 'REVENUE', '5000' => 'COGS', '6000' => 'EXPENSE'];
                    $baris = [
                        ['Penjualan', 'jual', '4000', false],
                        ['HPP', 'hpp', '5000', false],
                        ['Laba Kotor', 'kotor', null, true],
                        ['Biaya Operasional', 'biaya', '6000', false],
                        ['Laba Bersih', 'bersih', null, true],
                    ];
                    foreach ($baris as [$label, $key, $grup, $tebal]): ?>
                        <tr style="<?= $tebal ? 'background: var(--bg-muted);' : '' ?> font-weight: 800;">
                            <td><?= $label ?></td>
                            <?php foreach ($hasilBulan as $m => $h): ?>
                                <td style="text-align: right; color: <?= $warna($h[$key]) ?>;"><?= $h[$key] != 0 ? $rp($h[$key]) : '-' ?></td>
                            <?php endforeach; ?>
                            <td style="text-align: right; color: <?= $warna($hasilYtd[$key]) ?>;"><?= $hasilYtd[$key] != 0 ? $rp($hasilYtd[$key]) : '-' ?></td>
                        </tr>
                        <?php if ($grup):
                            foreach ($akunBulan as $kodeAk => $perBulan):
                                if (!isset($coaAkun[$kodeAk]) || $coaAkun[$kodeAk][6] !== $tipeGrup[$grup]) continue; ?>
                            <tr style="font-size: 12.5px;">
                                <td style="padding-left: 28px; font-weight: 500;"><?= $kodeAk ?> &nbsp;<?= htmlspecialchars($coaAkun[$kodeAk][0]) ?></td>
                                <?php for ($m = 1; $m <= 12; $m++):
                                    $v = $perBulan[$m] ?? 0.0;
                                    [$d1, $s1] = $rentang($tahun, $m); ?>
                                    <td style="text-align: right; color: <?= $warna($v) ?>;">
                                        <?php if ($v != 0): ?><a href="<?= $linkAkun($kodeAk, $d1, $s1) ?>" style="color: inherit;"><?= $rp($v) ?></a><?php else: ?>-<?php endif; ?>
                                    </td>
                                <?php endfor;
                                $tot = array_sum($perBulan); ?>
                                <td style="text-align: right; font-weight: 700; color: <?= $warna($tot) ?>;">
                                    <?php if ($tot != 0): ?><a href="<?= $linkAkun($kodeAk, "$tahun-01-01", "$tahun-12-31") ?>" style="color: inherit;"><?= $rp($tot) ?></a><?php else: ?>-<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        <?php if ($key === 'kotor' || $key === 'bersih'): ?>
                            <tr style="font-size: 12px; color: var(--text-muted);">
                                <td style="padding-left: 28px;">Margin <?= $key === 'kotor' ? 'kotor' : 'bersih' ?></td>
                                <?php foreach ($hasilBulan as $h): ?>
                                    <td style="text-align: right;"><?= $pct($h[$key], $h['jual']) ?></td>
                                <?php endforeach; ?>
                                <td style="text-align: right;"><?= $pct($hasilYtd[$key], $hasilYtd['jual']) ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding: 12px 20px; border-top: 1px solid var(--border); font-size: 12.5px; display: flex; gap: 18px; flex-wrap: wrap; align-items: center;">
            <span style="font-weight: 700; color: <?= $cocok ? '#15803d' : '#b91c1c' ?>;">
                <?= $cocok ? 'Cocok dengan COA' : 'Tidak cocok dengan COA' ?>
            </span>
            <span style="color: var(--text-muted);">
                Laba bersih seluruh periode <?= $rp($hs['bersih']) ?> = saldo COA akun Pendapatan &minus; HPP &minus; Beban (<?= $rp($labaCoa) ?>)
            </span>
        </div>
    </div>

    <?php if ($bulan > 0):
        $prevTh = $bulan === 1 ? $tahun - 1 : $tahun;
        $prevBl = $bulan === 1 ? 12 : $bulan - 1;
        [$pd, $ps] = $rentang($prevTh, $prevBl);
        $ini = $hasilBulan[$bulan];
        $lalu = $hitung(JurnalService::labaRugiRentang($pd, $ps));
        $banding = [['Penjualan', 'jual'], ['HPP', 'hpp'], ['Laba Kotor', 'kotor'], ['Biaya Operasional', 'biaya'], ['Laba Bersih', 'bersih']];
    ?>
    <div class="card" style="margin-bottom: 20px;">
        <div style="padding: 14px 20px; border-bottom: 1px solid var(--border); font-weight: 800;">
            <?= $namaBulan[$bulan] ?> <?= $tahun ?> dibanding <?= $namaBulan[$prevBl] ?> <?= $prevTh ?>
        </div>
        <div class="table-responsive">
            <table class="table-custom" data-no-tools>
                <thead>
                    <tr>
                        <th>Keterangan</th>
                        <th style="text-align: right;"><?= $namaBulan[$bulan] ?></th>
                        <th style="text-align: right;"><?= $namaBulan[$prevBl] ?></th>
                        <th style="text-align: right;">Selisih</th>
                        <th style="text-align: right;">Perubahan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($banding as [$label, $key]):
                        $sel = $ini[$key] - $lalu[$key];
                        $tebal = in_array($key, ['kotor', 'bersih'], true); ?>
                        <tr style="<?= $tebal ? 'font-weight: 800; background: var(--bg-muted);' : '' ?>">
                            <td><?= $label ?></td>
                            <td style="text-align: right;"><?= $rp($ini[$key]) ?></td>
                            <td style="text-align: right;"><?= $rp($lalu[$key]) ?></td>
                            <td style="text-align: right; color: <?= $warna($sel) ?>;"><?= $rp($sel) ?></td>
                            <td style="text-align: right;"><?= $lalu[$key] != 0 ? number_format($sel / abs($lalu[$key]) * 100, 1, ',', '.') . '%' : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 20px;">
        <div class="card">
            <div style="padding: 14px 20px; border-bottom: 1px solid var(--border); font-weight: 800;">Penjualan dan HPP per mitra &middot; <?= $labelPeriode ?></div>
            <div class="table-responsive">
                <table class="table-custom" data-no-tools>
                    <thead>
                        <tr>
                            <th>Mitra</th>
                            <th style="text-align: right;">Penjualan</th>
                            <th style="text-align: right;">HPP</th>
                            <th style="text-align: right;">Laba Kotor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$detailMitra): ?>
                            <tr><td colspan="4" style="text-align: center; padding: 24px; color: var(--text-muted);">Belum ada penjualan pada periode ini</td></tr>
                        <?php endif;
                        $tj = $th = 0.0;
                        foreach ($detailMitra as $nama => $v):
                            $tj += $v['4000']; $th += $v['5000']; $lk = $v['4000'] - $v['5000']; ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($nama) ?></td>
                                <td style="text-align: right;"><?= $rp($v['4000']) ?></td>
                                <td style="text-align: right;"><?= $rp($v['5000']) ?></td>
                                <td style="text-align: right; color: <?= $warna($lk) ?>;"><?= $rp($lk) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($detailMitra): ?>
                            <tr style="font-weight: 800; background: var(--bg-muted);">
                                <td>Total</td>
                                <td style="text-align: right;"><?= $rp($tj) ?></td>
                                <td style="text-align: right;"><?= $rp($th) ?></td>
                                <td style="text-align: right;"><?= $rp($tj - $th) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div style="padding: 14px 20px; border-bottom: 1px solid var(--border); font-weight: 800;">Biaya operasional per vendor &middot; <?= $labelPeriode ?></div>
            <div class="table-responsive">
                <table class="table-custom" data-no-tools>
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th style="text-align: right;">Biaya</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$detailVendor): ?>
                            <tr><td colspan="2" style="text-align: center; padding: 24px; color: var(--text-muted);">Belum ada biaya operasional pada periode ini</td></tr>
                        <?php endif; ?>
                        <?php foreach ($detailVendor as $nama => $v): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($nama) ?></td>
                                <td style="text-align: right;"><?= $rp($v) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($detailVendor): ?>
                            <tr style="font-weight: 800; background: var(--bg-muted);">
                                <td>Total</td>
                                <td style="text-align: right;"><?= $rp(array_sum($detailVendor)) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php else:
        $rows = JurnalService::labaPerSuratJalan($dari, $sampai);
        $tj = $th = 0.0;
    ?>

    <div class="card">
        <div style="padding: 14px 20px; border-bottom: 1px solid var(--border); font-weight: 800;">Surat jalan keluar &middot; <?= $labelPeriode ?></div>
        <div class="table-responsive">
            <table class="table-custom" data-no-tools>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>No. Surat Jalan</th>
                        <th>Mitra</th>
                        <th style="text-align: right;">Penjualan</th>
                        <th style="text-align: right;">HPP</th>
                        <th style="text-align: right;">Laba</th>
                        <th style="text-align: right;">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 28px; color: var(--text-muted);">Belum ada surat jalan keluar pada periode ini</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r):
                        $batal = $r['jenis_transaksi'] === 'PENGELUARAN_BATAL';
                        $j = (float)$r['penjualan']; $h = (float)$r['hpp'];
                        if (!$batal) { $tj += $j; $th += $h; } ?>
                        <tr style="<?= $batal ? 'color: var(--text-muted); text-decoration: line-through;' : '' ?>">
                            <td><?= htmlspecialchars((string)$r['tanggal']) ?></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars((string)$r['no_transaksi']) ?><?= $batal ? ' <span style="text-decoration: none; display: inline-block;" class="badge">Dibatalkan</span>' : '' ?></td>
                            <td><?= htmlspecialchars((string)$r['nama_mitra']) ?></td>
                            <td style="text-align: right;"><?= $rp($j) ?></td>
                            <td style="text-align: right;"><?= $rp($h) ?></td>
                            <td style="text-align: right; color: <?= $batal ? 'inherit' : $warna($j - $h) ?>;"><?= $rp($j - $h) ?></td>
                            <td style="text-align: right;"><?= $pct($j - $h, $j) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows): ?>
                        <tr style="font-weight: 800; background: var(--bg-muted);">
                            <td colspan="3">Total (tanpa yang dibatalkan)</td>
                            <td style="text-align: right;"><?= $rp($tj) ?></td>
                            <td style="text-align: right;"><?= $rp($th) ?></td>
                            <td style="text-align: right;"><?= $rp($tj - $th) ?></td>
                            <td style="text-align: right;"><?= $pct($tj - $th, $tj) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
