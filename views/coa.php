<?php
/**
 * COA (Chart of Accounts) - read-only.
 * Saldo dan mutasi dihitung dari data sumber (penerimaan, pengeluaran, faktur, pembayaran).
 */

use App\Core\Helper;
use App\Services\CoaService;

$pageTitle = 'COA';

$accounts = CoaService::accounts();
$sources  = CoaService::sources();
$balances = CoaService::balances();
$seimbang = CoaService::keseimbangan();

$akun = $_GET['akun'] ?? '';
if (!isset($accounts[$akun])) {
    $akun = '';
}

// Filter periode (hari / minggu / bulan / YTD / tanggal bebas); kosong = semua transaksi
[$dari, $sampai] = \App\Core\Periode::baca($_GET, null);
$adaPeriode = $dari !== '';

// Dengan periode: saldo awal (sebelum $dari), mutasi debit/kredit pada periode, dan saldo akhir (sampai $sampai)
$awalPer = $mutPer = $akhirPer = [];
if ($adaPeriode) {
    $totAwal  = \App\Services\JurnalService::totalsPerAkun(null, date('Y-m-d', strtotime($dari . ' -1 day')));
    $totMut   = \App\Services\JurnalService::totalsPerAkun($dari, $sampai);
    $totAkhir = \App\Services\JurnalService::totalsPerAkun(null, $sampai);
    $net = fn(array $tot, string $kode, string $normal): float => ($normal === 'Debit' ? 1 : -1) * (($tot[$kode][0] ?? 0.0) - ($tot[$kode][1] ?? 0.0));
    foreach ($accounts as $kodeA => $aA) {
        $awalPer[$kodeA]  = $net($totAwal, $kodeA, $aA[2]);
        $akhirPer[$kodeA] = $net($totAkhir, $kodeA, $aA[2]);
        $mutPer[$kodeA]   = [$totMut[$kodeA][0] ?? 0.0, $totMut[$kodeA][1] ?? 0.0];
    }
    $seimbang = ['debit' => array_sum(array_column($totMut, 0)), 'kredit' => array_sum(array_column($totMut, 1))];
    $seimbang['seimbang'] = abs($seimbang['debit'] - $seimbang['kredit']) < 0.01;
}

// Kelompokkan per akun induk (urutan sesuai definisi)
$groups = [];
foreach ($accounts as $kode => $a) {
    $groups[$a[4]][$kode] = $a;
}

require_once __DIR__ . '/layout/header.php';
?>

<div class="content-wrapper">

    <div class="page-header no-print">
        <div>
            <h1 class="page-title">COA</h1>
            <p class="page-subtitle">Daftar akun dan saldo dari jurnal setiap dokumen</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="index.php?page=master&tab=coa" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 6px;">
                <i data-lucide="settings-2" style="width: 16px; height: 16px;"></i>
                <span>Kelola Akun</span>
            </a>
            <?php $eksporNama = 'COA' . ($akun !== '' ? '_' . $akun : ''); $eksporJudul = 'COA' . ($akun !== '' ? ' ' . $akun : ''); include __DIR__ . '/layout/ekspor.php'; ?>
        </div>
    </div>

    <?php
    $fpDari = $dari; $fpSampai = $sampai; $fpHidden = ['page' => 'coa'] + ($akun !== '' ? ['akun' => $akun] : []); $fpSemua = true;
    include __DIR__ . '/layout/filter_periode.php';
    ?>

    <?php if ($akun === ''): ?>

    <div class="card">
        <div class="table-responsive">
            <table class="table-custom" data-no-tools>
                <thead>
                    <tr>
                        <th style="width: 90px;">Kode</th>
                        <th>Nama Akun</th>
                        <th>Type</th>
                        <th>Sign</th>
                        <th>Sumber Data</th>
                        <?php if ($adaPeriode): ?>
                            <th style="text-align: right;">Saldo Awal</th>
                            <th style="text-align: right;">Debit</th>
                            <th style="text-align: right;">Kredit</th>
                            <th style="text-align: right;">Saldo Akhir</th>
                        <?php else: ?>
                            <th style="text-align: right;">Saldo</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $induk => $list):
                        $sub = 0.0;
                        $signGrup = \App\Services\CoaService::TIPE[reset($list)[6]][2];
                        $subAwal = $subD = $subK = 0.0;
                        foreach ($list as $kode => $a) {
                            $sub += ($a[2] === $signGrup ? 1 : -1) * ($adaPeriode ? $akhirPer[$kode] : $balances[$kode]);
                            if ($adaPeriode) { $subAwal += ($a[2] === $signGrup ? 1 : -1) * $awalPer[$kode]; $subD += $mutPer[$kode][0]; $subK += $mutPer[$kode][1]; }
                        }
                    ?>
                        <tr style="background: var(--bg-muted);">
                            <td colspan="5" style="font-weight: 800;"><?= htmlspecialchars($induk) ?></td>
                            <?php if ($adaPeriode): ?>
                                <td style="text-align: right; font-weight: 800;"><?= Helper::formatRupiah($subAwal) ?></td>
                                <td style="text-align: right; font-weight: 800;"><?= Helper::formatRupiah($subD) ?></td>
                                <td style="text-align: right; font-weight: 800;"><?= Helper::formatRupiah($subK) ?></td>
                            <?php endif; ?>
                            <td style="text-align: right; font-weight: 800;"><?= Helper::formatRupiah($sub) ?></td>
                        </tr>
                        <?php foreach ($list as $kode => $a): ?>
                            <tr>
                                <td><a href="index.php?page=coa&akun=<?= $kode ?>" style="font-weight: 700; color: var(--primary);"><?= $kode ?></a></td>
                                <td style="font-weight: <?= $a[9] === 0 ? '700' : '500' ?>; padding-left: <?= 12 + $a[9] * 22 ?>px;">
                                    <?= $a[9] > 0 ? '<span style="color: var(--text-muted);">&#8627;</span> ' : '' ?><?= htmlspecialchars($a[0]) ?>
                                    <?php if (!$a[7]): ?><span class="badge" style="margin-left: 6px;">Nonaktif</span><?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($a[1]) ?></td>
                                <td><?= $a[2] ?></td>
                                <td style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($sources[$kode]) ?></td>
                                <?php if ($adaPeriode): ?>
                                    <td style="text-align: right;"><?= Helper::formatRupiah($awalPer[$kode]) ?></td>
                                    <td style="text-align: right;"><?= $mutPer[$kode][0] != 0 ? Helper::formatRupiah($mutPer[$kode][0]) : '-' ?></td>
                                    <td style="text-align: right;"><?= $mutPer[$kode][1] != 0 ? Helper::formatRupiah($mutPer[$kode][1]) : '-' ?></td>
                                    <td style="text-align: right; font-weight: 700;"><?= Helper::formatRupiah($akhirPer[$kode]) ?></td>
                                <?php else: ?>
                                    <td style="text-align: right; font-weight: 700;"><?= Helper::formatRupiah($balances[$kode]) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding: 12px 20px; border-top: 1px solid var(--border); display: flex; gap: 24px; flex-wrap: wrap; font-size: 12.5px;">
            <?php if ($adaPeriode): ?><span style="color: var(--text-muted);">Periode <?= htmlspecialchars($dari) ?> s/d <?= htmlspecialchars($sampai) ?></span><?php endif; ?>
            <span>Total Debit: <strong><?= Helper::formatRupiah($seimbang['debit']) ?></strong></span>
            <span>Total Kredit: <strong><?= Helper::formatRupiah($seimbang['kredit']) ?></strong></span>
            <span style="font-weight: 700; color: <?= $seimbang['seimbang'] ? 'var(--ok-fg)' : 'var(--bad-fg)' ?>;"><?= $seimbang['seimbang'] ? 'Jurnal seimbang' : 'Jurnal tidak seimbang' ?></span>
        </div>
    </div>

    <?php else:
        $a = $accounts[$akun];
        $mutasi = CoaService::mutasi($akun, 300, $adaPeriode ? $dari : null, $adaPeriode ? $sampai : null);
        $totDebit = array_sum(array_column($mutasi, 'debit'));
        $totKredit = array_sum(array_column($mutasi, 'kredit'));
    ?>

    <div class="card">
        <div style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); gap: 12px; flex-wrap: wrap;">
            <div>
                <div style="font-size: 16px; font-weight: 800;"><?= $akun ?> &nbsp;<?= htmlspecialchars($a[0]) ?></div>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?= htmlspecialchars($sources[$akun]) ?></div>
                <?php if ($adaPeriode): ?><div style="font-size: 12px; margin-top: 4px; font-weight: 700;">Periode <?= htmlspecialchars($dari) ?> s/d <?= htmlspecialchars($sampai) ?></div><?php endif; ?>
            </div>
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="text-align: right;">
                    <div style="font-size: 11px; color: var(--text-muted); font-weight: 700;"><?= $adaPeriode ? 'Saldo akhir periode' : 'Saldo' ?></div>
                    <div style="font-size: 18px; font-weight: 800;"><?= Helper::formatRupiah($adaPeriode ? $akhirPer[$akun] : $balances[$akun]) ?></div>
                    <?php if ($adaPeriode): ?><div style="font-size: 11.5px; color: var(--text-muted);">Saldo awal <?= Helper::formatRupiah($awalPer[$akun]) ?></div><?php endif; ?>
                </div>
                <a href="index.php?page=coa<?= $adaPeriode ? '&dari=' . $dari . '&sampai=' . $sampai : '' ?>" class="btn btn-outline btn-sm">Kembali ke COA</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Dokumen</th>
                        <th>Keterangan</th>
                        <th style="text-align: right;">Debit</th>
                        <th style="text-align: right;">Kredit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$mutasi): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 28px; color: var(--text-muted);">Belum ada mutasi</td></tr>
                    <?php endif; ?>
                    <?php foreach ($mutasi as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$m['tanggal']) ?></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars((string)$m['dokumen']) ?></td>
                            <td><?= htmlspecialchars((string)$m['ket']) ?></td>
                            <td style="text-align: right;"><?= $m['debit'] > 0 ? Helper::formatRupiah($m['debit']) : '' ?></td>
                            <td style="text-align: right;"><?= $m['kredit'] > 0 ? Helper::formatRupiah($m['kredit']) : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($mutasi): ?>
                        <tr>
                            <td colspan="3" style="font-weight: 800;">Total</td>
                            <td style="text-align: right; font-weight: 800;"><?= Helper::formatRupiah($totDebit) ?></td>
                            <td style="text-align: right; font-weight: 800;"><?= Helper::formatRupiah($totKredit) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
