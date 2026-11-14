<?php
/**
 * View Dashboard Utama (Vibrant & Eyecatching Edition)
 */

use App\Core\Helper;
use App\Models\Mitra;
use App\Models\Piutang;
use App\Models\Label;
use App\Models\Transaksi;
use App\Services\StockService;

$pageTitle = 'Dashboard - Sistem Barcode Inventory Multi-Mitra';
$ov = StockService::getDashboardOverview();
$recentTrx = Transaksi::getRecent(6);

// Utang mitra: total belanja (utang), total setoran (terbayar), dan sisa utang tiap mitra
$warnaMitra = [];
foreach (Mitra::getAll() as $mm) $warnaMitra[(int)$mm['id']] = $mm['warna_identitas'] ?? '#0284c7';
$utangMitra = Piutang::getAgingSummaryPerMitra();
$totUtang = array_sum(array_column($utangMitra, 'total_belanja'));
$totBayar = array_sum(array_column($utangMitra, 'total_setoran'));
$totSisa  = array_sum(array_column($utangMitra, 'sisa_piutang'));
$mitraTop = $utangMitra;
usort($mitraTop, fn($x, $y) => ($y['sisa_piutang'] <=> $x['sisa_piutang']) ?: ($y['total_belanja'] <=> $x['total_belanja']));
$mitraTop = array_slice($mitraTop, 0, 5);

require_once __DIR__ . '/layout/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Ringkasan Operasional Gudang</h1>
        <p class="page-subtitle">Kondisi stok, aktivitas hari ini, dan hal yang perlu ditindaklanjuti</p>
    </div>
</div>

<!-- KPI harian -->
<div class="grid grid-cols-4" style="margin-bottom: 24px;">
    <a class="kpi-card" href="index.php?page=timbang_cetak&tab=stok_gudang" title="Buka stok gudang utama">
        <div class="kpi-body">
            <div class="kpi-label">Stok Gudang Utama</div>
            <div class="kpi-value"><?= Helper::formatKg($ov['gudang_kg']) ?></div>
            <div class="kpi-sub"><?= Helper::formatNumber($ov['gudang_keranjang']) ?> keranjang, belum dimutasi ke mitra</div>
        </div>
        <div class="kpi-icon-pod"><i data-lucide="warehouse" style="width: 18px; height: 18px;"></i></div>
    </a>
    <a class="kpi-card" href="index.php?page=timbang_cetak&tab=riwayat_penerimaan" title="Buka riwayat penerimaan">
        <div class="kpi-body">
            <div class="kpi-label">Masuk Hari Ini</div>
            <div class="kpi-value"><?= Helper::formatKg($ov['masuk_kg']) ?></div>
            <div class="kpi-sub"><?= Helper::formatNumber($ov['masuk_sj']) ?> surat jalan, <?= Helper::formatNumber($ov['masuk_pcs']) ?> keranjang</div>
        </div>
        <div class="kpi-icon-pod"><i data-lucide="arrow-down-to-line" style="width: 18px; height: 18px;"></i></div>
    </a>
    <a class="kpi-card" href="index.php?page=pengeluaran&tab=riwayat" title="Buka riwayat pengeluaran">
        <div class="kpi-body">
            <div class="kpi-label">Keluar Hari Ini</div>
            <div class="kpi-value"><?= Helper::formatKg($ov['keluar_kg']) ?></div>
            <div class="kpi-sub"><?= Helper::formatNumber($ov['keluar_nota']) ?> nota pengeluaran ke mitra</div>
        </div>
        <div class="kpi-icon-pod"><i data-lucide="arrow-up-from-line" style="width: 18px; height: 18px;"></i></div>
    </a>
    <a class="kpi-card" href="index.php?page=ar_aging" title="Buka AR Aging">
        <div class="kpi-body">
            <div class="kpi-label">Piutang Lewat Tempo</div>
            <div class="kpi-value"><?= Helper::formatRupiah($ov['piutang_overdue']) ?></div>
            <div class="kpi-sub">Belum disetor lebih dari 7 hari</div>
        </div>
        <div class="kpi-icon-pod"><i data-lucide="hand-coins" style="width: 18px; height: 18px;"></i></div>
    </a>
</div>

<!-- Perlu tindakan & Tren -->
<div class="grid" style="grid-template-columns: minmax(320px, 4fr) minmax(0, 6fr); gap: 20px; margin-bottom: 24px; align-items: stretch;">
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><span>Perlu Tindakan</span></h2>
                <div class="card-description">Yang perlu dicek hari ini</div>
            </div>
        </div>
        <?php
        $actions = [
            ['Surat jalan belum selesai ditimbang', $ov['sj_belum_timbang'], Helper::formatNumber($ov['sj_belum_timbang']) . ' surat jalan', 'index.php?page=timbang_cetak&tab=timbang_cetak'],
            ['Stok mengendap lebih dari 3 hari', $ov['gudang_mengendap'], Helper::formatNumber($ov['gudang_mengendap']) . ' keranjang', 'index.php?page=timbang_cetak&tab=stok_gudang'],
            ['Piutang mitra lewat tempo', $ov['piutang_overdue'], Helper::formatRupiah($ov['piutang_overdue']), 'index.php?page=ar_aging'],
            ['Utang pemasok lewat tempo', $ov['utang_overdue'], Helper::formatRupiah($ov['utang_overdue']), 'index.php?page=ap_aging'],
        ];
        // yang perlu tindakan tampil di atas
        usort($actions, fn($x, $y) => (($y[1] > 0) <=> ($x[1] > 0)));
        ?>
        <div>
            <?php foreach ($actions as $act): $clear = !($act[1] > 0); ?>
            <a href="<?= $act[3] ?>" class="dash-action<?= $clear ? ' is-clear' : '' ?>">
                <span class="dash-action-text"><?= htmlspecialchars($act[0]) ?></span>
                <span class="dash-action-val"><?= $clear ? 'Aman' : $act[2] ?></span>
                <i data-lucide="chevron-right" style="width: 15px; height: 15px; color: var(--text-muted);"></i>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><span>Barang Masuk vs Keluar</span></h2>
                <div class="card-description">Total kg per hari, 14 hari terakhir</div>
            </div>
        </div>
        <div style="padding: 0 20px 18px; height: 240px; position: relative;">
            <canvas id="chartMasukKeluar"></canvas>
        </div>
    </div>
</div>

<!-- Utang mitra & transaksi terakhir -->
<div class="grid grid-cols-2" style="gap: 20px; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);">
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><span>Utang Mitra</span></h2>
                <div class="card-description">Total utang, terbayar, dan sisa utang; 5 mitra dengan sisa terbesar</div>
            </div>
            <a href="index.php?page=ar_aging&tab=matriks" class="btn btn-outline btn-sm">
                <span>Lihat semua</span>
                <i data-lucide="arrow-right" style="width: 13px; height: 13px;"></i>
            </a>
        </div>
        <div class="table-responsive">
            <table class="table-custom" id="tableDashMitra" style="font-size: 12.5px;">
                <thead>
                    <tr>
                        <th>Mitra</th>
                        <th style="text-align: right;">Total Utang</th>
                        <th style="text-align: right;">Terbayar</th>
                        <th style="text-align: right;">Sisa Utang</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mitraTop)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 30px;">Belum ada mitra.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($mitraTop as $m): $warna = $warnaMitra[(int)$m['mitra_id']] ?? '#0284c7'; ?>
                    <tr>
                        <td>
                            <span class="mitra-tag <?= strtolower($m['kode_mitra']) ?>" style="background: <?= htmlspecialchars($warna) ?>;"><?= htmlspecialchars($m['kode_mitra']) ?></span>
                            <span style="font-weight: 600; font-size: 13px; margin-left: 6px;"><?= htmlspecialchars($m['nama_mitra']) ?></span>
                        </td>
                        <td style="text-align: right; white-space: nowrap;"><?= Helper::formatRupiah($m['total_belanja']) ?></td>
                        <td style="text-align: right; white-space: nowrap; color: var(--ok-fg);"><?= Helper::formatRupiah($m['total_setoran']) ?></td>
                        <td style="text-align: right; white-space: nowrap; font-weight: 700; color: <?= $m['sisa_piutang'] > 0.01 ? '#b45309' : 'inherit' ?>;"><?= Helper::formatRupiah($m['sisa_piutang']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if (!empty($utangMitra)): ?>
                <tfoot>
                    <tr style="font-weight: 800; background: var(--bg-muted);">
                        <td>Semua mitra (<?= count($utangMitra) ?>)</td>
                        <td style="text-align: right;"><?= Helper::formatRupiah($totUtang) ?></td>
                        <td style="text-align: right; color: var(--ok-fg);"><?= Helper::formatRupiah($totBayar) ?></td>
                        <td style="text-align: right; color: <?= $totSisa > 0.01 ? '#b45309' : 'inherit' ?>;"><?= Helper::formatRupiah($totSisa) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><span>Transaksi Terakhir</span></h2>
                <div class="card-description">Pergerakan barang masuk dan keluar</div>
            </div>
            <a href="index.php?page=pengeluaran&tab=riwayat" class="btn btn-outline btn-sm">
                <span>Lihat semua</span>
                <i data-lucide="arrow-right" style="width: 13px; height: 13px;"></i>
            </a>
        </div>
        <div class="table-responsive">
            <table class="table-custom" id="tableDashTrx">
                <thead>
                    <tr>
                        <th>No Dokumen</th>
                        <th>Mitra</th>
                        <th>Jenis</th>
                        <th style="text-align: right;">Total Kg</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentTrx)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 30px;">Belum ada riwayat transaksi.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentTrx as $t): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; font-family: 'JetBrains Mono', monospace; font-size: 12.5px;"><?= htmlspecialchars($t['no_transaksi']) ?></div>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= Helper::formatDateTime($t['created_at']) ?></div>
                            </td>
                            <td>
                                <span class="mitra-tag <?= strtolower($t['kode_mitra']) ?>" style="background: <?= htmlspecialchars($t['warna_identitas']) ?>; padding: 2px 7px; font-size: 11px;"><?= htmlspecialchars($t['kode_mitra']) ?></span>
                            </td>
                            <td><?= $t['jenis_transaksi'] === 'PENGELUARAN' ? 'Keluar' : 'Masuk' ?></td>
                            <td style="text-align: right; font-weight: 700;"><?= Helper::formatKg($t['total_kg']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('chartMasukKeluar');
    if (!el || typeof Chart === 'undefined') return;
    var t = <?= json_encode($ov['trend']) ?>;
    var css = getComputedStyle(document.documentElement);
    var muted = (css.getPropertyValue('--text-muted') || '#64748b').trim();
    var grid = (css.getPropertyValue('--border') || '#e2e8f0').trim();
    new Chart(el, {
        type: 'bar',
        data: {
            labels: t.labels,
            datasets: [
                { label: 'Masuk (kg)', data: t.masuk, backgroundColor: '#0284c7', borderRadius: 3, maxBarThickness: 18 },
                { label: 'Keluar (kg)', data: t.keluar, backgroundColor: '#94a3b8', borderRadius: 3, maxBarThickness: 18 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, color: muted, font: { size: 12 } } } },
            scales: {
                x: { grid: { display: false }, ticks: { color: muted, font: { size: 11 } } },
                y: { beginAtZero: true, grid: { color: grid }, ticks: { color: muted, font: { size: 11 } } }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
