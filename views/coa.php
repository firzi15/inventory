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

// Filter periode (dipakai saat telusur dari Laba Rugi)
$dari = $_GET['dari'] ?? '';
$sampai = $_GET['sampai'] ?? '';
$adaPeriode = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai);
if (!$adaPeriode) { $dari = $sampai = ''; }

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
                        <th style="text-align: right;">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $induk => $list):
                        $sub = 0.0;
                        $signGrup = \App\Services\CoaService::TIPE[reset($list)[6]][2];
                        foreach ($list as $kode => $a) { $sub += ($a[2] === $signGrup ? 1 : -1) * $balances[$kode]; }
                    ?>
                        <tr style="background: var(--bg-muted);">
                            <td colspan="5" style="font-weight: 800;"><?= htmlspecialchars($induk) ?></td>
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
                                <td style="text-align: right; font-weight: 700;"><?= Helper::formatRupiah($balances[$kode]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding: 12px 20px; border-top: 1px solid var(--border); display: flex; gap: 24px; flex-wrap: wrap; font-size: 12.5px;">
            <span>Total Debit: <strong><?= Helper::formatRupiah($seimbang['debit']) ?></strong></span>
            <span>Total Kredit: <strong><?= Helper::formatRupiah($seimbang['kredit']) ?></strong></span>
            <span style="font-weight: 700; color: <?= $seimbang['seimbang'] ? '#15803d' : '#b91c1c' ?>;"><?= $seimbang['seimbang'] ? 'Jurnal seimbang' : 'Jurnal tidak seimbang' ?></span>
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
                    <div style="font-size: 11px; color: var(--text-muted); font-weight: 700;">Saldo</div>
                    <div style="font-size: 18px; font-weight: 800;"><?= Helper::formatRupiah($balances[$akun]) ?></div>
                </div>
                <?php if ($adaPeriode): ?>
                    <a href="javascript:history.back()" class="btn btn-outline btn-sm">Kembali ke Laba Rugi</a>
                <?php else: ?>
                    <a href="index.php?page=coa" class="btn btn-outline btn-sm">Kembali ke COA</a>
                <?php endif; ?>
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
