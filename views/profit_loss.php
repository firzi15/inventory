<?php
/**
 * Halaman Estimasi Keuntungan (Profit) & Analisa Keuangan
 * Fitur:
 * 1. Rekap Metrik Finansial Keseluruhan (Total Beli, Total Jual, Total Profit, Rata-rata Margin %)
 * 2. Simulator Kalkulator Keuntungan Real-Time (Pemasok x Mitra x Produk x Bobot Kg)
 * 3. Tabel Komparasi Lengkap Harga Beli Pemasok vs Harga Jual Mitra per Produk SKU
 * 4. Riwayat Finansial Surat Jalan Pengeluaran Barang
 */

use App\Core\Helper;
use App\Models\Pemasok;
use App\Models\Mitra;
use App\Models\Item;
use App\Models\MasterHarga;
use App\Models\MasterHargaPemasok;
use App\Models\Transaksi;

$pageTitle = 'Profit and Loss - Analisa Laba & Margin';

$pemasokList = Pemasok::getAll();
$mitraList   = Mitra::getAll();
$itemList    = Item::getAll();

// Filter untuk Tabel Analisa Komparasi
$activeTab = $_GET['tab'] ?? 'komparasi';
if (!in_array($activeTab, ['komparasi', 'riwayat_sj', 'semua'])) {
    $activeTab = 'komparasi';
}

$filterPemasokId = isset($_GET['f_pemasok']) && $_GET['f_pemasok'] !== '' ? (int)$_GET['f_pemasok'] : null;
$filterMitraId   = isset($_GET['f_mitra']) && $_GET['f_mitra'] !== '' ? (int)$_GET['f_mitra'] : null;
$search          = trim($_GET['search'] ?? '');

$profitLossData = MasterHargaPemasok::getProfitLossSummary($filterPemasokId, $filterMitraId, $search);

// Riwayat Transaksi Finansial Pengeluaran
$hSearch  = trim($_GET['h_search'] ?? '');
$hFilters = [];
if (!empty($hSearch)) $hFilters['search'] = $hSearch;
$historyTransactions = Transaksi::getHistoryPengeluaran($hFilters, 15, 0);

// Hitung Agregasi Finansial dari Seluruh Riwayat Pengeluaran
$db = \App\Core\Database::getConnection();
$aggRow = $db->query("
    SELECT 
        COUNT(id) AS total_transaksi,
        COALESCE(SUM(total_label), 0) AS total_kemasan,
        COALESCE(SUM(total_kg), 0) AS total_kg,
        COALESCE(SUM(total_nilai_beli), 0) AS total_beli,
        COALESCE(SUM(total_nilai_jual), 0) AS total_jual,
        COALESCE(SUM(total_profit), 0) AS total_profit
    FROM transaksi_stok 
    WHERE jenis_transaksi = 'PENGELUARAN'
")->fetch(PDO::FETCH_ASSOC);

$totalAllBeli   = (float)($aggRow['total_beli'] ?? 0);
$totalAllJual   = (float)($aggRow['total_jual'] ?? 0);
$totalAllProfit = (float)($aggRow['total_profit'] ?? 0);
$avgMarginPct   = $totalAllBeli > 0 ? (($totalAllProfit / $totalAllBeli) * 100) : 0.0;

require_once __DIR__ . '/layout/header.php';
?>

<div class="page-header no-print">
    <div>
        <h1 class="page-title">Profit and Loss</h1>
        <p class="page-subtitle">Pantau laba kotor, selisih harga beli pemasok vs harga jual ke mitra, serta analisa margin keuntungan per produk</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <button type="button" class="btn btn-outline" onclick="exportTableToExcel('tableProfitLoss', 'Laporan_Profit_and_Loss')">
            <i data-lucide="file-spreadsheet" style="width: 16px; height: 16px; color: #10b981;"></i>
            <span>Ekspor Excel (.xlsx)</span>
        </button>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i data-lucide="printer" style="width: 16px; height: 16px;"></i>
            <span>Cetak Laporan</span>
        </button>
    </div>
</div>

<!-- 4 KARTU METRIK FINANSIAL KESELURUHAN -->
<div class="grid grid-cols-4 no-print" style="gap: 16px; margin-bottom: 24px;">
    <!-- 1. Modal Beli Pemasok -->
    <div class="card" style="padding: 16px 20px; border-left: 4px solid #3b82f6;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <span style="font-size: 12px; font-weight: 700; color: #1d4ed8; text-transform: uppercase;">🏷️ Total Modal Beli (Pemasok)</span>
            <i data-lucide="shopping-bag" style="width: 18px; height: 18px; color: #3b82f6;"></i>
        </div>
        <div style="font-size: 22px; font-weight: 900; color: #1e3a8a; font-family: 'JetBrains Mono', monospace;">
            <?= Helper::formatRupiah($totalAllBeli) ?>
        </div>
        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px;">
            Total pengeluaran modal beli ke pemasok
        </div>
    </div>

    <!-- 2. Nilai Penjualan ke Mitra -->
    <div class="card" style="padding: 16px 20px; border-left: 4px solid #10b981;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <span style="font-size: 12px; font-weight: 700; color: #15803d; text-transform: uppercase;">💰 Total Nilai Jual (Mitra)</span>
            <i data-lucide="badge-dollar-sign" style="width: 18px; height: 18px; color: #10b981;"></i>
        </div>
        <div style="font-size: 22px; font-weight: 900; color: #065f46; font-family: 'JetBrains Mono', monospace;">
            <?= Helper::formatRupiah($totalAllJual) ?>
        </div>
        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px;">
            Total tagihan omzet penjualan ke 7 mitra
        </div>
    </div>

    <!-- 3. Total Keuntungan Bersih (Profit) -->
    <div class="card" style="padding: 16px 20px; border-left: 4px solid <?= $totalAllProfit >= 0 ? '#059669' : '#dc2626' ?>; background: <?= $totalAllProfit >= 0 ? 'linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%)' : 'linear-gradient(135deg, #ffffff 0%, #fef2f2 100%)' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <span style="font-size: 12px; font-weight: 800; color: <?= $totalAllProfit >= 0 ? '#047857' : '#b91c1c' ?>; text-transform: uppercase;">📈 Total Keuntungan (Profit)</span>
            <i data-lucide="trending-up" style="width: 18px; height: 18px; color: <?= $totalAllProfit >= 0 ? '#059669' : '#dc2626' ?>;"></i>
        </div>
        <div style="font-size: 22px; font-weight: 900; color: <?= $totalAllProfit >= 0 ? '#047857' : '#b91c1c' ?>; font-family: 'JetBrains Mono', monospace;">
            <?= ($totalAllProfit > 0 ? '+' : '') . Helper::formatRupiah($totalAllProfit) ?>
        </div>
        <div style="font-size: 11.5px; color: <?= $totalAllProfit >= 0 ? '#047857' : '#b91c1c' ?>; margin-top: 4px; font-weight: 600;">
            Selisih harga jual dikurangi modal beli
        </div>
    </div>

    <!-- 4. Rata-Rata Margin Keuntungan -->
    <div class="card" style="padding: 16px 20px; border-left: 4px solid #8b5cf6;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <span style="font-size: 12px; font-weight: 700; color: #6d28d9; text-transform: uppercase;">📊 Rata-Rata Margin</span>
            <i data-lucide="percent" style="width: 18px; height: 18px; color: #8b5cf6;"></i>
        </div>
        <div style="font-size: 22px; font-weight: 900; color: #5b21b6; font-family: 'JetBrains Mono', monospace;">
            <?= ($avgMarginPct > 0 ? '+' : '') . number_format($avgMarginPct, 1) ?>%
        </div>
        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px;">
            Persentase margin laba dari total modal
        </div>
    </div>
</div>

<?php if ($activeTab === 'komparasi' || $activeTab === 'semua'): ?>
<!-- ========================================================================= -->
<!-- SIMULATOR KALKULATOR KEUNTUNGAN REAL-TIME                                  -->
<!-- ========================================================================= -->
<div class="card no-print" style="margin-bottom: 24px; box-shadow: var(--shadow-sm); border: 1.5px solid #c7d2fe;">
    <div class="card-header" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.06), rgba(16, 185, 129, 0.06)); padding-bottom: 14px; border-bottom: 1px solid var(--border);">
        <div>
            <h2 class="card-title" style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="calculator" style="width: 20px; height: 20px; color: var(--primary);"></i>
                <span>Simulasi Kalkulator Keuntungan Real-Time</span>
            </h2>
            <div class="card-description">
                Pilih pemasok, mitra tujuan, produk SKU, dan bobot untuk menghitung estimasi modal beli, omzet jual, dan keuntungan secara instan
            </div>
        </div>
    </div>

    <div style="padding: 18px 20px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 1.4fr; gap: 14px; align-items: end;">
            <div>
                <label class="form-label" for="simPemasok" style="font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">🚚 Pemasok (Asal Beli):</label>
                <select id="simPemasok" class="form-control" style="font-size: 13px; font-weight: 600; height: 40px;" onchange="runSimulasiProfit()">
                    <?php foreach ($pemasokList as $p): ?>
                        <option value="<?= $p['id'] ?>">[<?= htmlspecialchars($p['kode_pemasok']) ?>] <?= htmlspecialchars($p['nama_pemasok']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="simMitra" style="font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">🏢 Mitra (Tujuan Jual):</label>
                <select id="simMitra" class="form-control" style="font-size: 13px; font-weight: 600; height: 40px;" onchange="runSimulasiProfit()">
                    <?php foreach ($mitraList as $m): ?>
                        <option value="<?= $m['id'] ?>">[<?= htmlspecialchars($m['kode_mitra']) ?>] <?= htmlspecialchars($m['nama_mitra']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="simItem" style="font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">📦 Produk SKU:</label>
                <select id="simItem" class="form-control" style="font-size: 13px; font-weight: 600; height: 40px;" onchange="runSimulasiProfit()">
                    <?php foreach ($itemList as $it): ?>
                        <option value="<?= $it['id'] ?>" data-jenis="<?= $it['jenis_id'] ?>">
                            <?= htmlspecialchars($it['nama_item']) ?> (<?= htmlspecialchars($it['kode_item']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="simBerat" style="font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">⚖️ Simulasi Bobot (Kg):</label>
                <input type="number" id="simBerat" class="form-control" value="50" min="0.1" step="any" style="font-size: 14px; font-weight: 800; text-align: right; height: 40px; font-family: 'JetBrains Mono', monospace;" oninput="runSimulasiProfit()">
            </div>
            <!-- Kotak Hasil Real-Time -->
            <div id="boxSimHasil" style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); padding: 12px 16px; border-radius: 8px; border: 1.5px solid #10b981;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <span style="font-size: 11px; color: #065f46; font-weight: 800; text-transform: uppercase;">Estimasi Laba Bersih:</span>
                    <span id="simMarginBadge" class="badge badge-success" style="font-size: 10px; font-weight: 800;">Margin +0.0%</span>
                </div>
                <div id="simHasilProfit" style="font-size: 20px; font-weight: 900; color: #047857; font-family: 'JetBrains Mono', monospace;">+ Rp 0</div>
                <div id="simHasilDetail" style="font-size: 11px; color: #065f46; margin-top: 2px;">Beli: Rp 0 • Jual: Rp 0</div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- TABEL KOMPARASI HARGA BELI PEMASOK VS HARGA JUAL MITRA PER PRODUK SKU     -->
<!-- ========================================================================= -->
<div class="card" style="margin-bottom: 24px; box-shadow: var(--shadow-sm);">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h2 class="card-title" style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="scale" style="width: 20px; height: 20px; color: #8b5cf6;"></i>
                <span>Matriks Komparasi Harga Beli vs Harga Jual per Produk</span>
            </h2>
            <div class="card-description">
                Perbandingan komparatif tarif beli dari pemasok dengan tarif jual ke mitra untuk setiap produk/item SKU
            </div>
        </div>
        <span class="badge badge-purple" style="font-weight: 700;"><?= count($profitLossData) ?> Kombinasi Harga</span>
    </div>

    <!-- Filter Bar Komparasi -->
    <div style="padding: 12px 20px; background: var(--bg-muted); border-bottom: 1px solid var(--border);">
        <form method="GET" action="index.php" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="page" value="profit_loss">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">

            <div style="flex: 1; min-width: 180px;">
                <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama produk, SKU, pemasok, atau mitra..." style="height: 38px;">
            </div>

            <div style="width: 210px;">
                <select name="f_pemasok" class="form-control" style="height: 38px;">
                    <option value="">-- Semua Pemasok Beli --</option>
                    <?php foreach ($pemasokList as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $filterPemasokId === (int)$p['id'] ? 'selected' : '' ?>>
                            [<?= htmlspecialchars($p['kode_pemasok']) ?>] <?= htmlspecialchars($p['nama_pemasok']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="width: 210px;">
                <select name="f_mitra" class="form-control" style="height: 38px;">
                    <option value="">-- Semua Mitra Jual --</option>
                    <?php foreach ($mitraList as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $filterMitraId === (int)$m['id'] ? 'selected' : '' ?>>
                            [<?= htmlspecialchars($m['kode_mitra']) ?>] <?= htmlspecialchars($m['nama_mitra']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="height: 38px;">
                <i data-lucide="filter" style="width: 14px; height: 14px;"></i>
                <span>Filter</span>
            </button>
            <?php if (!empty($search) || $filterPemasokId || $filterMitraId): ?>
                <a href="index.php?page=profit_loss&tab=<?= htmlspecialchars($activeTab) ?>" class="btn btn-outline" style="height: 38px;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabel Data Komparasi -->
    <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
        <table class="table-custom" id="tableProfitLoss">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">No</th>
                    <th>Pemasok Asal</th>
                    <th>Produk / Item SKU</th>
                    <th>Kategori</th>
                    <th style="text-align: right; background: rgba(245, 158, 11, 0.05);">Hrg Beli Pemasok</th>
                    <th>Mitra Bisnis Tujuan</th>
                    <th style="text-align: right; background: rgba(16, 185, 129, 0.05);">Hrg Jual Mitra</th>
                    <th style="text-align: right;">Margin Profit / Kg</th>
                    <th style="text-align: center;">Margin %</th>
                    <th style="text-align: center; width: 130px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($profitLossData)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 36px;">
                            Tidak ada kombinasi harga yang cocok dengan filter yang dipilih.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $plNo = 1; foreach ($profitLossData as $row): 
                        $profitKg = (float)$row['profit_per_kg'];
                        $isProfit = $profitKg > 0;
                        $isRugi   = $profitKg < 0;
                    ?>
                    <tr>
                        <td style="text-align: center; color: var(--text-muted); font-size: 11.5px;"><?= $plNo++ ?></td>
                        <td>
                            <strong style="color: var(--text-main); font-size: 13px;"><?= htmlspecialchars($row['nama_pemasok']) ?></strong>
                            <div style="font-size: 10.5px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace;"><?= htmlspecialchars($row['kode_pemasok']) ?></div>
                        </td>
                        <td>
                            <strong style="color: var(--text-main); font-size: 13.5px;"><?= htmlspecialchars($row['nama_item']) ?></strong>
                            <div style="font-size: 10.5px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace;"><?= htmlspecialchars($row['kode_item']) ?></div>
                        </td>
                        <td>
                            <span class="badge badge-purple" style="font-size: 10.5px;"><?= htmlspecialchars($row['nama_jenis']) ?></span>
                        </td>
                        <td style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; color: #d97706; background: rgba(245, 158, 11, 0.02);">
                            Rp <?= number_format($row['harga_pemasok'], 0, ',', '.') ?>
                        </td>
                        <td>
                            <span class="mitra-tag <?= strtolower($row['kode_mitra']) ?>" style="background: <?= htmlspecialchars($row['warna_identitas']) ?>; font-size: 11px;">
                                <?= htmlspecialchars($row['kode_mitra']) ?>
                            </span>
                            <span style="font-size: 12px; margin-left: 4px; font-weight: 600;"><?= htmlspecialchars($row['nama_mitra']) ?></span>
                        </td>
                        <td style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; color: #10b981; background: rgba(16, 185, 129, 0.02);">
                            Rp <?= number_format($row['harga_mitra'], 0, ',', '.') ?>
                        </td>
                        <td style="text-align: right; font-weight: 900; font-family: 'JetBrains Mono', monospace; font-size: 13px; color: <?= $isProfit ? '#10b981' : ($isRugi ? '#ef4444' : 'var(--text-muted)') ?>;">
                            <?= $isProfit ? '+' : '' ?>Rp <?= number_format($profitKg, 0, ',', '.') ?>
                        </td>
                        <td style="text-align: center; font-weight: 800; font-family: 'JetBrains Mono', monospace; font-size: 12px; color: <?= $isProfit ? '#059669' : ($isRugi ? '#dc2626' : 'var(--text-muted)') ?>;">
                            <?= ($row['margin_persen'] > 0 ? '+' : '') . number_format($row['margin_persen'], 1) ?>%
                        </td>
                        <td style="text-align: center;">
                            <?php if ($isProfit): ?>
                                <span class="badge badge-success" style="font-size: 11px;">✓ Untung (Profit)</span>
                            <?php elseif ($isRugi): ?>
                                <span class="badge badge-danger" style="font-size: 11px;">⚠️ Rugi</span>
                            <?php else: ?>
                                <span class="badge badge-secondary" style="font-size: 11px;">Impas (0)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($activeTab === 'riwayat_sj' || $activeTab === 'semua'): ?>
<!-- ========================================================================= -->
<!-- RIWAYAT FINANSIAL SURAT JALAN PENGELUARAN BARANG                           -->
<!-- ========================================================================= -->
<div class="card" style="box-shadow: var(--shadow-sm);">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h2 class="card-title" style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="receipt" style="width: 20px; height: 20px; color: #10b981;"></i>
                <span>Rekap Keuntungan dari Surat Jalan Pengeluaran Barang</span>
            </h2>
            <div class="card-description">
                Laporan realisasi laba kotor dari transaksi pengeluaran barang yang telah diterbitkan surat jalannya
            </div>
        </div>
        <button type="button" class="btn btn-outline btn-sm" onclick="exportTableToExcel('tableHistoryFinancial', 'Laporan_Keuntungan_Surat_Jalan')">
            <i data-lucide="file-spreadsheet" style="width: 14px; height: 14px; color: #10b981;"></i>
            <span>Ekspor Excel (.xlsx)</span>
        </button>
    </div>

    <div class="table-responsive">
        <table class="table-custom" id="tableHistoryFinancial">
            <thead>
                <tr>
                    <th style="width: 35px; text-align: center;">No</th>
                    <th>No. Surat Jalan / DO</th>
                    <th>Waktu Keluar</th>
                    <th>Pemasok Asal</th>
                    <th>Mitra Penerima</th>
                    <th style="text-align: center;">Kemasan</th>
                    <th style="text-align: right;">Total Netto</th>
                    <th style="text-align: right;">Modal Beli</th>
                    <th style="text-align: right;">Nilai Jual</th>
                    <th style="text-align: right;">Keuntungan (Profit)</th>
                    <th style="text-align: center; width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($historyTransactions)): ?>
                    <tr>
                        <td colspan="11" style="text-align: center; color: var(--text-muted); padding: 36px;">
                            Belum ada riwayat transaksi pengeluaran tercatat.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $htNo = 1;
                    foreach ($historyTransactions as $ht):
                        $totBeli = (float)($ht['total_nilai_beli'] ?? 0);
                        $totJual = (float)($ht['total_nilai_jual'] ?? 0);
                        $profit  = (float)($ht['total_profit'] ?? ($totJual - $totBeli));
                        $isPos   = $profit >= 0;
                    ?>
                    <tr>
                        <td style="text-align: center; color: var(--text-muted); font-size: 11.5px;"><?= $htNo++ ?></td>
                        <td>
                            <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--primary);">
                                <?= htmlspecialchars($ht['no_surat_jalan_keluar']) ?>
                            </strong>
                            <div style="font-size: 10.5px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace;">
                                <?= htmlspecialchars($ht['no_transaksi']) ?>
                            </div>
                        </td>
                        <td style="font-size: 11.5px; color: var(--text-muted);">
                            <?= date('d/m/Y H:i', strtotime($ht['created_at'])) ?>
                        </td>
                        <td>
                            <span class="badge badge-info" style="font-size: 11.5px; font-weight: 700;">
                                [<?= htmlspecialchars($ht['kode_pemasok'] ?? 'PMS') ?>] <?= htmlspecialchars($ht['nama_pemasok'] ?? 'Pemasok') ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-purple" style="font-size: 11.5px; font-weight: 700;">
                                [<?= htmlspecialchars($ht['kode_mitra'] ?? 'MTR') ?>] <?= htmlspecialchars($ht['nama_mitra'] ?? 'Mitra') ?>
                            </span>
                        </td>
                        <td style="text-align: center; font-weight: 700;">
                            <?= number_format($ht['total_label']) ?> Pcs
                        </td>
                        <td style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; color: #10b981;">
                            <?= Helper::formatKg($ht['total_kg']) ?>
                        </td>
                        <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12px; color: #1e40af;">
                            <?= Helper::formatRupiah($totBeli) ?>
                        </td>
                        <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12px; color: #065f46;">
                            <?= Helper::formatRupiah($totJual) ?>
                        </td>
                        <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 900; color: <?= $isPos ? '#059669' : '#dc2626' ?>;">
                            <?= ($profit > 0 ? '+' : '') . Helper::formatRupiah($profit) ?>
                        </td>
                        <td style="text-align: center;">
                            <a href="index.php?page=surat_jalan_print&id=<?= $ht['id'] ?>" target="_blank" class="btn btn-outline btn-sm" style="font-size: 11.5px; padding: 4px 8px; color: #4f46e5; border-color: #c7d2fe;">
                                🖨️ Cetak SJ
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
    // Simulator Keuntungan Real-Time
    const pricesPemasokMap = JSON.parse('<?= MasterHargaPemasok::getAllPricesJson() ?>');
    const pricesMitraMap   = JSON.parse('<?= MasterHarga::getAllPricesJson() ?>');

    function formatRupiah(num) {
        return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
    }

    function runSimulasiProfit() {
        if (!document.getElementById('simPemasok')) return;
        const pmsId = document.getElementById('simPemasok').value;
        const mtrId = document.getElementById('simMitra').value;
        const itemId = document.getElementById('simItem').value;
        const berat = parseFloat(document.getElementById('simBerat').value) || 0;

        const hrgBeli = pricesPemasokMap[pmsId + '_' + itemId] || 0;
        const hrgJual = pricesMitraMap[mtrId + '_' + itemId] || 0;

        const totBeli = berat * hrgBeli;
        const totJual = berat * hrgJual;
        const profit = totJual - totBeli;
        const marginPct = totBeli > 0 ? ((profit / totBeli) * 100).toFixed(1) : 0;

        const elHasil = document.getElementById('simHasilProfit');
        const elDetail = document.getElementById('simHasilDetail');
        const elBadge = document.getElementById('simMarginBadge');
        const boxContainer = document.getElementById('boxSimHasil');

        if (profit >= 0) {
            elHasil.innerText = '+' + formatRupiah(profit);
            elHasil.style.color = '#047857';
            elBadge.className = 'badge badge-success';
            elBadge.innerText = `Margin +${marginPct}%`;
            boxContainer.style.background = 'linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%)';
            boxContainer.style.borderColor = '#10b981';
        } else {
            elHasil.innerText = formatRupiah(profit);
            elHasil.style.color = '#b91c1c';
            elBadge.className = 'badge badge-danger';
            elBadge.innerText = `Loss ${marginPct}%`;
            boxContainer.style.background = 'linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%)';
            boxContainer.style.borderColor = '#ef4444';
        }

        elDetail.innerText = `Beli: ${formatRupiah(totBeli)} (@${formatRupiah(hrgBeli)}) • Jual: ${formatRupiah(totJual)} (@${formatRupiah(hrgJual)})`;
    }

    document.addEventListener('DOMContentLoaded', () => {
        runSimulasiProfit();
    });
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
