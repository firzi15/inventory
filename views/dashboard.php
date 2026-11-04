ok<?php
/**
 * View Dashboard Utama (Vibrant & Eyecatching Edition)
 */

use App\Core\Helper;
use App\Models\Mitra;
use App\Models\Label;
use App\Models\Transaksi;
use App\Services\StockService;

$pageTitle = 'Dashboard - Sistem Barcode Inventory Multi-Mitra';
$kpi = StockService::getDashboardKpi();
$mitraSummary = Mitra::getStockSummaryPerMitra();
$recentTrx = Transaksi::getRecent(6);

require_once __DIR__ . '/layout/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Ringkasan Operasional Gudang</h1>
        <p class="page-subtitle">Pusat kendali 2 proses utama: Penerimaan Barang (Masuk) dan Pengeluaran Barang (Keluar ke Mitra)</p>
    </div>
</div>


<!-- 4 Eyecatching Clickable KPI Cards with Glowing Colorful Icon Pods -->
<div class="grid grid-cols-4" style="margin-bottom: 26px;">
    <!-- KPI 1: Total Stok Fisik (Blue) -> Buka Stok Mitra -->
    <a href="index.php?page=stok_mitra" class="kpi-card blue" style="text-decoration: none; cursor: pointer;" title="Klik untuk melihat rincian stok fisik per mitra">
        <div>
            <div class="kpi-label" style="color: #0284c7; display: flex; align-items: center; gap: 4px;">
                <span>Total Stok Fisik</span>
                <i data-lucide="arrow-up-right" style="width: 14px; height: 14px; opacity: 0.7;"></i>
            </div>
            <div class="kpi-value"><?= Helper::formatKg($kpi['total_stok_kg']) ?></div>
            <div class="kpi-sub">
                <span class="badge badge-info" style="font-weight: 700;">
                    <?= Helper::formatNumber($kpi['total_label_aktif']) ?> Kemasan
                </span>
                <span style="margin-left: 4px;">Label Aktif &rarr;</span>
            </div>
        </div>
        <div class="kpi-icon-pod">
            <i data-lucide="scale" style="width: 24px; height: 24px;"></i>
        </div>
    </a>

    <!-- KPI 2: Mitra Terdaftar (Green) -> Buka Master Mitra -->
    <a href="index.php?page=master&tab=mitra" class="kpi-card green" style="text-decoration: none; cursor: pointer;" title="Klik untuk membuka Master Mitra Bisnis">
        <div>
            <div class="kpi-label" style="color: #059669; display: flex; align-items: center; gap: 4px;">
                <span>Mitra Bisnis</span>
                <i data-lucide="arrow-up-right" style="width: 14px; height: 14px; opacity: 0.7;"></i>
            </div>
            <div class="kpi-value"><?= $kpi['total_mitra'] ?> Mitra</div>
            <div class="kpi-sub">
                <span class="badge badge-success" style="font-weight: 700;">Kelola Master</span>
                <span style="margin-left: 4px;">7 Mitra &rarr;</span>
            </div>
        </div>
        <div class="kpi-icon-pod">
            <i data-lucide="building-2" style="width: 24px; height: 24px;"></i>
        </div>
    </a>

    <!-- KPI 3: Aktivitas Hari Ini (Purple) -> Buka Mutasi & Riwayat Transaksi -->
    <a href="index.php?page=mutasi" class="kpi-card purple" style="text-decoration: none; cursor: pointer;" title="Klik untuk melihat riwayat mutasi & transaksi">
        <div>
            <div class="kpi-label" style="color: #7c3aed; display: flex; align-items: center; gap: 4px;">
                <span>Total Transaksi</span>
                <i data-lucide="arrow-up-right" style="width: 14px; height: 14px; opacity: 0.7;"></i>
            </div>
            <div class="kpi-value"><?= Helper::formatNumber($kpi['trx_hari_ini']) ?> Transaksi</div>
            <div class="kpi-sub">
                <span class="badge badge-purple" style="font-weight: 700;">
                    <?= Helper::formatKg($kpi['trx_kg_hari_ini']) ?>
                </span>
                <span style="margin-left: 4px;">Mutasi & Keluar &rarr;</span>
            </div>
        </div>
        <div class="kpi-icon-pod">
            <i data-lucide="activity" style="width: 24px; height: 24px;"></i>
        </div>
    </a>

    <!-- KPI 4: Antrean Accurate (Amber) -> Buka Settingan API Accurate -->
    <a href="index.php?page=master&tab=accurate" class="kpi-card amber" style="text-decoration: none; cursor: pointer;" title="Klik untuk membuka Settingan & Antrean API Accurate">
        <div>
            <div class="kpi-label" style="color: #d97706; display: flex; align-items: center; gap: 4px;">
                <span>Antrean Accurate</span>
                <i data-lucide="arrow-up-right" style="width: 14px; height: 14px; opacity: 0.7;"></i>
            </div>
            <div class="kpi-value"><?= $kpi['queue_pending'] ?> Dokumen</div>
            <div class="kpi-sub">
                <?php if ($kpi['queue_pending'] > 0): ?>
                    <span class="badge badge-warning" style="font-weight: 700;">⏳ Sedang Sinkron</span>
                <?php else: ?>
                    <span class="badge badge-success" style="font-weight: 700;">✓ 100% Tersinkron</span>
                <?php endif; ?>
                <span style="margin-left: 4px;">Sync API &rarr;</span>
            </div>
        </div>
        <div class="kpi-icon-pod">
            <i data-lucide="refresh-cw" style="width: 24px; height: 24px;"></i>
        </div>
    </a>
</div>

<!-- Grid 2 Kolom: Stok per 7 Mitra & Transaksi Terakhir -->
<div class="grid grid-cols-2" style="margin-bottom: 26px;">
    <!-- Kolom Kiri: Breakdown Stok Mitra -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">
                    <i data-lucide="layers" style="width: 18px; height: 18px; color: var(--primary);"></i>
                    <span>Stok Fisik Mitra</span>
                </h2>
                <div class="card-description">Distribusi bobot kilogram dan kemasan per entitas bisnis</div>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <button type="button" class="btn btn-outline btn-sm" onclick="exportTableToExcel('tableDashMitra', 'Dashboard_Stok_Mitra')">
                    <i data-lucide="file-spreadsheet" style="width: 13px; height: 13px; color: #10b981;"></i>
                    <span>Excel</span>
                </button>
                <a href="index.php?page=stok_mitra" class="btn btn-outline btn-sm">
                    <span>Matriks</span>
                    <i data-lucide="arrow-right" style="width: 13px; height: 13px;"></i>
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table-custom" id="tableDashMitra">
                <thead>
                    <tr>
                        <th>Mitra Pemilik</th>
                        <th>Gudang Accurate</th>
                        <th style="text-align: right;">Kemasan</th>
                        <th style="text-align: right;">Total Netto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalAllKg = max(1, $kpi['total_stok_kg']);
                    foreach ($mitraSummary as $m): 
                        $pct = round(($m['total_kg'] / $totalAllKg) * 100, 1);
                        $warna = $m['warna_identitas'] ?? '#4f46e5';
                    ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="mitra-tag <?= strtolower($m['kode_mitra']) ?>" style="background: <?= htmlspecialchars($warna) ?>;">
                                    <?= htmlspecialchars($m['kode_mitra']) ?>
                                </span>
                                <span style="font-weight: 700; font-size: 13.5px;"><?= htmlspecialchars($m['nama_mitra']) ?></span>
                            </div>
                            <div style="margin-top: 8px; background: var(--bg-muted); border-radius: 9999px; height: 6px; width: 100%; overflow: hidden;">
                                <div style="background: <?= htmlspecialchars($warna) ?>; height: 100%; width: <?= max(4, $pct) ?>%; border-radius: 9999px; box-shadow: 0 0 6px <?= htmlspecialchars($warna) ?>;"></div>
                            </div>
                        </td>
                        <td><code style="font-size: 11px;"><?= htmlspecialchars($m['kode_gudang_accurate']) ?></code></td>
                        <td style="text-align: right; font-weight: 700; font-size: 13px;"><?= Helper::formatNumber($m['total_kemasan']) ?> pcs</td>
                        <td style="text-align: right; font-weight: 800; font-size: 15px; color: <?= htmlspecialchars($warna) ?>;">
                            <?= Helper::formatKg($m['total_kg']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Kolom Kanan: Transaksi Terakhir & Status Accurate -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">
                    <i data-lucide="clock" style="width: 18px; height: 18px; color: var(--purple);"></i>
                    <span>Transaksi Terakhir & Sinkronisasi</span>
                </h2>
                <div class="card-description">Pergerakan barang masuk/keluar dan status API</div>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <button type="button" class="btn btn-outline btn-sm" onclick="exportTableToExcel('tableDashTrx', 'Dashboard_Transaksi_Terakhir')">
                    <i data-lucide="file-spreadsheet" style="width: 13px; height: 13px; color: #10b981;"></i>
                    <span>Excel</span>
                </button>
                <a href="index.php?page=master&tab=accurate" class="btn btn-outline btn-sm">
                    <span>Antrean API</span>
                    <i data-lucide="refresh-cw" style="width: 13px; height: 13px;"></i>
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table-custom" id="tableDashTrx">
                <thead>
                    <tr>
                        <th>No Dokumen</th>
                        <th>Mitra</th>
                        <th>Jenis</th>
                        <th style="text-align: right;">Total Kg</th>
                        <th>Status Accurate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentTrx)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">Belum ada riwayat transaksi.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentTrx as $t): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700; font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--text-main);"><?= htmlspecialchars($t['no_transaksi']) ?></div>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= Helper::formatDateTime($t['created_at']) ?></div>
                            </td>
                            <td>
                                <span class="mitra-tag <?= strtolower($t['kode_mitra']) ?>" style="background: <?= htmlspecialchars($t['warna_identitas']) ?>; padding: 2px 7px; font-size: 11px;">
                                    <?= htmlspecialchars($t['kode_mitra']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($t['jenis_transaksi'] === 'PENGELUARAN'): ?>
                                    <span class="badge badge-info">📤 KELUAR</span>
                                <?php else: ?>
                                    <span class="badge badge-success">📥 MASUK</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; font-weight: 800; font-size: 14px; color: #10b981;"><?= Helper::formatKg($t['total_kg']) ?></td>
                            <td>
                                <?php if ($t['status_accurate'] === 'SENT'): ?>
                                    <span class="badge badge-success">✓ Terkirim</span>
                                <?php elseif ($t['status_accurate'] === 'PENDING'): ?>
                                    <span class="badge badge-warning">⏳ Pending</span>
                                <?php else: ?>
                                    <span class="badge badge-destructive">⚠ Gagal</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
