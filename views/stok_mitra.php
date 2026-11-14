<?php
/**
 * Laporan & Saldo Stok Multi-Mitra Terintegrasi Master Harga & Gudang Accurate
 * Menampilkan data stok per mitra fisik (Kg & Kemasan) serta valuasi finansial berdasarkan Master Harga Mitra
 */

use App\Core\Helper;
use App\Models\Mitra;
use App\Services\StockService;

use App\Models\Item;
use App\Models\JenisBarang;

$pageTitle = 'Saldo Stok Mitra & Master Harga - Warehouse Management';

// Matriks stok jenis barang x mitra
$stockData = StockService::getMatrixStock();
$mitraList = $stockData['mitraList'];
$jenisList = $stockData['jenisList'];
$itemList  = Item::getAll();
$matrix    = $stockData['matrix'];
$totalsPerJenis = $stockData['totalsPerJenis'];
$totalsPerMitra = $stockData['totalsPerMitra'];

// Ringkasan per Mitra (terintegrasi master harga & valuasi finansial)
$mitraSummaryList = StockService::getStockSummaryPerMitra();

// Buat map lookup untuk akses instan data valuasi mitra
$mitraSummaryMap = [];
$grandTotalKg = 0;
$grandTotalLabel = 0;
$grandTotalValuasi = 0;
$grandTotalJual = 0;
$mitraWithStockCount = 0;
$rekonPersediaan = StockService::rekonsiliasiPersediaan();

foreach ($mitraSummaryList as $ms) {
    $mId = (int)$ms['id'];
    $mitraSummaryMap[$mId] = $ms;
    $grandTotalKg += (float)$ms['total_kg'];
    $grandTotalLabel += (int)$ms['total_kemasan'];
    $grandTotalValuasi += (float)$ms['total_nilai_stok'];
    $grandTotalJual += (float)$ms['total_nilai_jual'];
    if ((float)$ms['total_kg'] > 0) {
        $mitraWithStockCount++;
    }
}

$avgValuasiPerKg = $grandTotalKg > 0 ? ($grandTotalValuasi / $grandTotalKg) : 0;

$activeTab = $_GET['tab'] ?? 'rincian';
if (!in_array($activeTab, ['rincian', 'matriks', 'ringkasan', 'semua'])) {
    $activeTab = 'rincian';
}

// Filter Mitra, Kategori, Produk, Urutan, dan Pencarian untuk Rincian Stok
$selectedMitraFilter = isset($_GET['filter_mitra']) && $_GET['filter_mitra'] !== '' ? (int)$_GET['filter_mitra'] : null;
$selectedJenisFilter = isset($_GET['filter_jenis']) && $_GET['filter_jenis'] !== '' ? (int)$_GET['filter_jenis'] : null;
$selectedItemFilter  = isset($_GET['filter_item']) && $_GET['filter_item'] !== '' ? (int)$_GET['filter_item'] : null;
$selectedSortFilter  = trim($_GET['sort'] ?? '');
$searchQuery         = trim($_GET['search'] ?? '');

$filterParams = [
    'mitra_id' => $selectedMitraFilter,
    'jenis_id' => $selectedJenisFilter,
    'item_id'  => $selectedItemFilter,
    'sort'     => $selectedSortFilter,
    'search'   => $searchQuery
];

$allDetailItems = StockService::getStockPerItem($filterParams);
$totalDetailItemsCount = count($allDetailItems);

// Pagination Rincian Stok Mitra
$perPage = 15;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $perPage;
$totalPages = max(1, (int)ceil($totalDetailItemsCount / $perPage));
$detailItems = array_slice($allDetailItems, $offset, $perPage);

// Hitung total untuk allDetailItems yang cocok dengan filter
$subtotalDetailKemasan = 0;
$subtotalDetailKg = 0;
$subtotalDetailNilai = 0;
foreach ($allDetailItems as $row) {
    $subtotalDetailKemasan += (int)$row['total_label'];
    $subtotalDetailKg += (float)$row['total_kg'];
    $subtotalDetailNilai += (float)$row['total_nilai'];
}

// Hitung jumlah filter aktif
$activeFilterCount = 0;
if ($selectedMitraFilter) $activeFilterCount++;
if ($selectedJenisFilter) $activeFilterCount++;
if ($selectedItemFilter) $activeFilterCount++;
if (!empty($selectedSortFilter)) $activeFilterCount++;
if (!empty($searchQuery)) $activeFilterCount++;
$hasAnyFilter = $activeFilterCount > 0;

// Cari mitra terpilih untuk teks header jika difilter
$activeMitraObj = null;
if ($selectedMitraFilter) {
    foreach ($mitraList as $m) {
        if ((int)$m['id'] === $selectedMitraFilter) {
            $activeMitraObj = $m;
            break;
        }
    }
}

require_once __DIR__ . '/layout/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Saldo Stok Mitra Bisnis</h1>
        <p class="page-subtitle">Stok catch-weight fisik per mitra, dinilai dengan harga beli saat barang diterima dan dicocokkan dengan akun Inventory di jurnal</p>
    </div>
</div>

<!-- 1. KARTU RINGKASAN KPI UTAMA (FISIK & VALUASI FINANSIAL) -->
<div class="grid grid-cols-4 kpi-clean" style="margin-bottom: 24px;">
    <div class="kpi-card">
        <div class="kpi-body">
            <div class="kpi-label">Total Stok Fisik</div>
            <div class="kpi-value"><?= Helper::formatKg($grandTotalKg) ?></div>
            <div class="kpi-sub"><?= Helper::formatNumber($grandTotalLabel) ?> kemasan aktif</div>
        </div>
        <div class="kpi-icon-pod"><i data-lucide="layers" style="width: 18px; height: 18px;"></i></div>
    </div>

    <div class="kpi-card">
        <div class="kpi-body">
            <div class="kpi-label">Nilai Stok di Mitra (HPP)</div>
            <div class="kpi-value"><?= Helper::formatRupiah($grandTotalValuasi) ?></div>
            <div class="kpi-sub">Harga beli saat barang diterima &bull; Estimasi jual <?= Helper::formatRupiah($grandTotalJual) ?></div>
        </div>
        <div class="kpi-icon-pod"><i data-lucide="wallet" style="width: 18px; height: 18px;"></i></div>
    </div>

    <div class="kpi-card">
        <div class="kpi-body">
            <div class="kpi-label">Mitra Terisi Stok</div>
            <div class="kpi-value"><?= $mitraWithStockCount ?><span class="kpi-unit">/ <?= count($mitraList) ?> mitra</span></div>
            <div class="kpi-sub">Per gudang Accurate</div>
        </div>
        <div class="kpi-icon-pod"><i data-lucide="building-2" style="width: 18px; height: 18px;"></i></div>
    </div>

    <div class="kpi-card">
        <div class="kpi-body">
            <div class="kpi-label">Rata-rata HPP per Kg</div>
            <div class="kpi-value"><?= Helper::formatRupiah($avgValuasiPerKg) ?><span class="kpi-unit">/kg</span></div>
            <div class="kpi-sub">Dihitung tertimbang</div>
        </div>
        <div class="kpi-icon-pod"><i data-lucide="tag" style="width: 18px; height: 18px;"></i></div>
    </div>
</div>

<!-- Kecocokan nilai persediaan dengan jurnal (akun 1200 Inventory) -->
<div class="card" style="padding: 16px 20px; margin-bottom: 22px; border-left: 4px solid <?= $rekonPersediaan['cocok'] ? 'var(--ok-solid)' : '#dc2626' ?>;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
        <div style="font-weight: 800; font-size: 14px;">Kecocokan dengan Jurnal (akun 1200 Inventory)</div>
        <span class="badge" style="<?= $rekonPersediaan['cocok'] ? 'background: var(--ok-bg); color: var(--ok-fg);' : 'background: var(--bad-bg); color: var(--bad-fg);' ?> font-weight: 800;">
            <?= $rekonPersediaan['cocok'] ? 'Cocok' : 'Selisih ' . Helper::formatRupiah(abs($rekonPersediaan['selisih'])) ?>
        </span>
    </div>
    <div class="grid grid-cols-4" style="gap: 14px; font-size: 13px;">
        <div><div style="color: var(--text-muted); font-size: 11.5px;">Nilai barang aktif (HPP)</div><strong><?= Helper::formatRupiah($rekonPersediaan['fisik']) ?></strong>
            <div style="font-size: 11px; color: var(--text-muted);">gudang utama <?= Helper::formatRupiah($rekonPersediaan['fisik_gudang']) ?> &bull; di mitra <?= Helper::formatRupiah($rekonPersediaan['fisik_mitra']) ?></div></div>
        <div><div style="color: var(--text-muted); font-size: 11.5px;">Saldo Inventory di jurnal</div><strong><?= Helper::formatRupiah($rekonPersediaan['coa']) ?></strong></div>
        <div><div style="color: var(--text-muted); font-size: 11.5px;">Masuk dari faktur penerimaan</div><strong><?= Helper::formatRupiah($rekonPersediaan['masuk']) ?></strong></div>
        <div><div style="color: var(--text-muted); font-size: 11.5px;">Keluar (HPP pengeluaran)</div><strong><?= Helper::formatRupiah(abs($rekonPersediaan['keluar'])) ?></strong></div>
    </div>
    <?php if (!$rekonPersediaan['cocok']): ?>
        <div style="margin-top: 12px; font-size: 12.5px; color: var(--bad-fg);">
            Nilai barang di gudang berbeda dari saldo jurnal.
            <?php if (abs($rekonPersediaan['lain']) > 0.5): ?>Ada mutasi akun 1200 dari dokumen lain sebesar <?= Helper::formatRupiah($rekonPersediaan['lain']) ?>.<?php endif; ?>
            <?php if ($rekonPersediaan['sj_beda']): ?>Surat Jalan berikut nilai barangnya tidak sama dengan fakturnya:<?php endif; ?>
        </div>
        <?php if ($rekonPersediaan['sj_beda']): ?>
        <div class="table-responsive" style="margin-top: 8px;">
            <table class="table-custom" data-no-tools>
                <thead><tr><th>Surat Jalan</th><th style="text-align: right;">Nilai Barang</th><th style="text-align: right;">Nilai Faktur</th><th style="text-align: right;">Selisih</th></tr></thead>
                <tbody>
                <?php foreach ($rekonPersediaan['sj_beda'] as $b): ?>
                    <tr>
                        <td style="font-family: 'JetBrains Mono', monospace; font-weight: 700;"><?= htmlspecialchars($b['no_surat_jalan']) ?></td>
                        <td style="text-align: right;"><?= Helper::formatRupiah($b['nilai_label']) ?></td>
                        <td style="text-align: right;"><?= Helper::formatRupiah($b['nilai_faktur']) ?></td>
                        <td style="text-align: right; color: var(--bad-fg);"><?= Helper::formatRupiah($b['nilai_label'] - $b['nilai_faktur']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($activeTab === 'ringkasan' || $activeTab === 'semua'): ?>
<!-- 2. KARTU DATA STOK & MASTER HARGA PER MITRA (PENGGANTI TREN DATA) -->
<div style="margin-bottom: 26px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
        <div>
            <h2 style="font-size: 16px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px; margin: 0;">
                <i data-lucide="layout-grid" style="width: 20px; height: 20px; color: var(--primary);"></i>
                <span>Ringkasan Stok dan Nilai Persediaan per Mitra</span>
            </h2>
            <div style="font-size: 12.5px; color: var(--text-muted); margin-top: 3px;">
                Pilih kartu mitra untuk memfilter rincian produk fisik dan harga master yang berlaku
            </div>
        </div>
        <div style="display: flex; gap: 8px;" class="no-print">
            <?php if ($selectedMitraFilter): ?>
                <a href="index.php?page=stok_mitra" class="btn btn-outline btn-sm" style="color: var(--primary); font-weight: 700;">
                    <i data-lucide="refresh-cw" style="width: 14px; height: 14px;"></i>
                    <span>Tampilkan Semua Mitra</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Grid Kartu 7 Mitra Bisnis -->
    <div class="grid grid-cols-4" style="gap: 14px;">
        <?php foreach ($mitraSummaryList as $ms): 
            $mId = (int)$ms['id'];
            $isSelected = ($selectedMitraFilter === $mId);
            $warna = !empty($ms['warna_identitas']) ? $ms['warna_identitas'] : '#0284c7';
            $hasStock = ((float)$ms['total_kg'] > 0);
            $pctMitra = $grandTotalKg > 0 ? round(((float)$ms['total_kg'] / $grandTotalKg) * 100, 1) : 0;
        ?>
        <div class="card" style="padding: 16px; border-radius: 12px; position: relative; display: flex; flex-direction: column; justify-content: space-between; border: <?= $isSelected ? '2px solid ' . htmlspecialchars($warna) : '1px solid var(--border)' ?>; <?= $isSelected ? 'box-shadow: 0 4px 18px rgba(2, 132, 199, 0.2);' : '' ?> background: var(--card-bg);">
            <!-- Header Kartu Mitra -->
            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                            <span class="mitra-tag" style="background: <?= htmlspecialchars($warna) ?>; font-weight: 800; font-size: 11px; padding: 2px 8px; border-radius: 6px; color: #fff;">
                                <?= htmlspecialchars($ms['kode_mitra']) ?>
                            </span>
                            <strong style="font-size: 14px; color: var(--text-main);"><?= htmlspecialchars($ms['nama_mitra']) ?></strong>
                        </div>
                        <div style="display: flex; align-items: center; gap: 4px; font-size: 11px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace;">
                            <i data-lucide="database" style="width: 12px; height: 12px;"></i>
                            <span><?= htmlspecialchars($ms['kode_gudang_accurate'] ?? '-') ?></span>
                        </div>
                    </div>
                    <?php if ($isSelected): ?>
                        <span class="badge badge-primary" style="font-size: 10.5px; font-weight: 800; padding: 3px 8px;">Aktif</span>
                    <?php elseif ($hasStock): ?>
                        <span class="badge badge-success" style="font-size: 10.5px; font-weight: 700; padding: 3px 8px;">Ada Stok</span>
                    <?php else: ?>
                        <span class="badge" style="background: var(--bg-muted); color: var(--text-muted); font-size: 10.5px; padding: 3px 8px;">0 kg</span>
                    <?php endif; ?>
                </div>

                <!-- Nilai Valuasi Finansial & Bobot Fisik -->
                <div style="background: rgba(0, 0, 0, 0.025); border: 1px solid var(--border); border-radius: 10px; padding: 12px; margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                        <span style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.3px;">Nilai Persediaan (HPP)</span>
                        <span style="font-size: 11px; font-weight: 800; color: <?= htmlspecialchars($warna) ?>;"><?= $pctMitra ?>%</span>
                    </div>
                    <div style="font-size: 18px; font-weight: 900; color: <?= $hasStock ? '#059669' : 'var(--text-muted)' ?>; margin-bottom: 8px;">
                        <?= Helper::formatRupiah($ms['total_nilai_stok']) ?>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12.5px; border-top: 1px dashed var(--border); padding-top: 6px;">
                        <span style="color: var(--text-muted);">Berat Fisik:</span>
                        <strong style="font-size: 13.5px; color: var(--text-main);"><?= Helper::formatKg($ms['total_kg']) ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; margin-top: 4px;">
                        <span style="color: var(--text-muted);">Kemasan:</span>
                        <span style="font-weight: 700; color: var(--text-main);"><?= Helper::formatNumber($ms['total_kemasan']) ?> pcs <span style="color: var(--text-muted);">(<?= $ms['total_sku'] ?> SKU)</span></span>
                    </div>
                </div>
            </div>

            <!-- Tombol Aksi Filter -->
            <div class="no-print">
                <a href="index.php?page=stok_mitra&filter_mitra=<?= $mId ?>" class="btn <?= $isSelected ? 'btn-primary' : 'btn-outline' ?>" style="width: 100%; justify-content: center; font-size: 12px; padding: 7px 10px; font-weight: 700;">
                    <i data-lucide="<?= $isSelected ? 'check-circle' : 'eye' ?>" style="width: 14px; height: 14px;"></i>
                    <span><?= $isSelected ? 'Sedang Difilter' : 'Filter Mitra Ini' ?></span>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($activeTab === 'rincian' || $activeTab === 'semua'): ?>
<!-- 3. TABEL UTAMA: RINCIAN DATA STOK PER MITRA & MASTER HARGA -->
<div class="card" style="margin-bottom: 26px;">
    <!-- Header Tabel -->
    <div class="card-header no-print">
        <div>
            <h2 class="card-title">
                <i data-lucide="calculator" style="width: 18px; height: 18px; color: var(--ok-fg);"></i>
                <span>Data Stok per Mitra & Master Harga</span>
                <?php if ($activeMitraObj): ?>
                    <span class="badge badge-primary" style="margin-left: 6px;">
                        Mitra: <?= htmlspecialchars($activeMitraObj['kode_mitra']) ?> - <?= htmlspecialchars($activeMitraObj['nama_mitra']) ?>
                    </span>
                <?php endif; ?>
            </h2>
            <div class="card-description">
                Rincian stok aktif beserta harga per kg dan total nilai per mitra
            </div>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <?php $eksporTabel = 'detailTable'; $eksporNama = 'Rincian_Stok_Mitra_Harga'; include __DIR__ . '/layout/ekspor.php'; ?>
        </div>
    </div>

    <!-- Toolbar: pencarian & filter -->
    <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 16px;">
        <!-- Instant Search Input -->
        <div style="position: relative;">
            <input type="text" id="tableSearchInput" class="form-control" placeholder="Cari nama barang / SKU..." style="height: 38px; padding-left: 32px; font-size: 13px; width: 300px;" onkeyup="filterDetailTable()">
            <i data-lucide="search" style="position: absolute; left: 10px; top: 11px; width: 15px; height: 15px; color: var(--text-muted); pointer-events: none;"></i>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <button type="button" class="btn <?= $hasAnyFilter ? 'btn-primary' : 'btn-outline' ?>" onclick="toggleMitraFilterPanel()" id="btnToggleFilterMitra" style="font-weight: 700; font-size: 12.5px; padding: 7px 16px; border-radius: 10px; height: 38px; display: inline-flex; align-items: center; gap: 7px; box-shadow: var(--shadow-sm);">
                <i data-lucide="sliders-horizontal" style="width: 15px; height: 15px;"></i>
                <span>Filter Lanjutan</span>
                <?php if ($activeFilterCount > 0): ?>
                    <span class="badge" style="background: var(--bg-card); color: var(--primary); font-size: 11px; font-weight: 900; padding: 1px 7px; border-radius: 10px;"><?= $activeFilterCount ?></span>
                <?php endif; ?>
            </button>
            <?php if ($hasAnyFilter): ?>
                <a href="index.php?page=stok_mitra" class="btn btn-outline" style="font-size: 12px; padding: 7px 14px; border-radius: 10px; height: 38px; display: inline-flex; align-items: center; gap: 5px;" title="Reset Semua Filter">
                    <i data-lucide="rotate-ccw" style="width: 13px; height: 13px;"></i>
                    <span>Reset</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- JUMBOTRON PANEL FILTER LENGKAP & DETAIL (COLLAPSIBLE) -->
    <div id="panelJumbotronFilterMitra" class="no-print" style="display: none; padding: 20px 24px; background: var(--bg-card); border-bottom: 1.5px solid var(--border);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1.5px solid var(--border);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="background: var(--ok-solid); color: white; width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 8px rgba(16, 185, 129, 0.3);">
                    <i data-lucide="sliders" style="width: 18px; height: 18px;"></i>
                </div>
                <div>
                    <h4 style="margin: 0; font-size: 14.5px; font-weight: 800; color: var(--text-main);">Panel Pengaturan Filter Stok Mitra</h4>
                    <div style="font-size: 11.5px; color: var(--text-muted);">Saring persediaan per mitra, komoditas, SKU, dan urutan valuasi</div>
                </div>
            </div>
            <button type="button" onclick="toggleMitraFilterPanel(false)" class="btn btn-outline btn-sm" style="padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 8px;">
                Tutup Panel
            </button>
        </div>

        <form method="GET" action="index.php" id="formFilterMitra">
            <input type="hidden" name="page" value="stok_mitra">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 14px;">
                <!-- 1. Pilihan Mitra -->
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px; font-weight: 700;">Mitra Bisnis:</label>
                    <select name="filter_mitra" class="form-control" style="font-size: 12.5px; font-weight: 600; height: 38px;">
                        <option value="">-- Semua Mitra --</option>
                        <?php foreach ($mitraList as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $selectedMitraFilter === (int)$m['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['kode_mitra']) ?> - <?= htmlspecialchars($m['nama_mitra']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. Kategori / Jenis -->
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px; font-weight: 700;">Kategori / Jenis:</label>
                    <select name="filter_jenis" class="form-control" style="font-size: 12.5px; font-weight: 600; height: 38px;">
                        <option value="">-- Semua Kategori --</option>
                        <?php foreach ($jenisList as $j): ?>
                            <option value="<?= $j['id'] ?>" <?= $selectedJenisFilter === (int)$j['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($j['nama_jenis']) ?> (<?= htmlspecialchars($j['kode_jenis']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3. Komoditas / Produk -->
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px; font-weight: 700;">Produk / Item:</label>
                    <select name="filter_item" class="form-control" style="font-size: 12.5px; font-weight: 600; height: 38px;">
                        <option value="">-- Semua Produk --</option>
                        <?php foreach ($itemList as $itm): ?>
                            <option value="<?= $itm['id'] ?>" <?= $selectedItemFilter === (int)$itm['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($itm['nama_item']) ?> (<?= htmlspecialchars($itm['kode_item']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 4. Urutan Tampilan (Sorting) -->
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px; font-weight: 700;">Urutan Tampilan:</label>
                    <select name="sort" class="form-control" style="font-size: 12.5px; font-weight: 600; height: 38px;">
                        <option value="" <?= empty($selectedSortFilter) ? 'selected' : '' ?>>Valuasi Tertinggi</option>
                        <option value="berat_desc" <?= $selectedSortFilter === 'berat_desc' ? 'selected' : '' ?>>Total Berat</option>
                        <option value="kemasan_desc" <?= $selectedSortFilter === 'kemasan_desc' ? 'selected' : '' ?>>Jumlah Kemasan Terbanyak</option>
                        <option value="nama_asc" <?= $selectedSortFilter === 'nama_asc' ? 'selected' : '' ?>>Nama Produk</option>
                        <option value="mitra_asc" <?= $selectedSortFilter === 'mitra_asc' ? 'selected' : '' ?>>Nama Mitra</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group" style="margin: 0; flex: 1; min-width: 250px;">
                    <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px; font-weight: 700;">Pencarian Kata Kunci:</label>
                    <div style="position: relative;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama barang / kode SKU / mitra..." value="<?= htmlspecialchars($searchQuery) ?>" style="font-size: 12.5px; height: 38px; padding-left: 34px;">
                        <i data-lucide="search" style="position: absolute; left: 10px; top: 11px; width: 15px; height: 15px; color: var(--text-muted);"></i>
                    </div>
                </div>

                <div style="display: flex; gap: 8px; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 20px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3); background: var(--ok-solid); border-color: var(--ok-solid);">
                        <i data-lucide="filter" style="width: 14px; height: 14px;"></i>
                        <span>Terapkan Filter</span>
                    </button>
                    <?php if ($hasAnyFilter): ?>
                        <a href="index.php?page=stok_mitra&tab=<?= htmlspecialchars($activeTab) ?>" class="btn btn-outline" style="height: 38px; padding: 0 14px; display: inline-flex; align-items: center; gap: 6px;" title="Reset Semua Filter">
                            <i data-lucide="rotate-ccw" style="width: 14px; height: 14px;"></i>
                            <span>Reset</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tag Filter Aktif -->
            <?php if ($hasAnyFilter): ?>
                <div style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--border); display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: 11.5px;">
                    <span style="color: var(--text-muted); font-weight: 700;">Filter Sedang Aktif:</span>
                    <?php if ($selectedMitraFilter): ?>
                        <span class="badge badge-secondary" style="font-size: 11px; padding: 3px 8px; display: inline-flex; align-items: center; gap: 4px;">
                            Mitra: <?= htmlspecialchars($activeMitraObj['nama_mitra'] ?? 'ID '.$selectedMitraFilter) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($selectedJenisFilter): ?>
                        <span class="badge badge-secondary" style="font-size: 11px; padding: 3px 8px; display: inline-flex; align-items: center; gap: 4px;">
                            Kategori Terpilih
                        </span>
                    <?php endif; ?>
                    <?php if ($selectedItemFilter): ?>
                        <span class="badge badge-secondary" style="font-size: 11px; padding: 3px 8px; display: inline-flex; align-items: center; gap: 4px;">
                            Produk Terpilih
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($searchQuery)): ?>
                        <span class="badge badge-secondary" style="font-size: 11px; padding: 3px 8px; display: inline-flex; align-items: center; gap: 4px;">
                            Kata Kunci: "<?= htmlspecialchars($searchQuery) ?>"
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table-custom" id="detailTable">
            <thead>
                <tr>
                    <th style="width: 50px; text-align: center;">No</th>
                    <th style="min-width: 180px;">Mitra Pemilik</th>
                    <th style="min-width: 160px;">Gudang Accurate</th>
                    <th style="min-width: 130px;">Kode SKU</th>
                    <th style="min-width: 200px;">Nama Komoditas / Barang</th>
                    <th style="min-width: 130px;">Kategori</th>
                    <th style="text-align: right; min-width: 120px;">Jumlah Kemasan</th>
                    <th style="text-align: right; min-width: 130px;">Rata-rata/Pcs</th>
                    <th style="text-align: right; min-width: 140px; background: rgba(2, 132, 199, 0.05);">Total Netto (Kg)</th>
                    <th style="text-align: right; min-width: 160px; background: rgba(16, 185, 129, 0.08);">HPP Rata-rata</th>
                    <th style="text-align: right; min-width: 180px; background: rgba(16, 185, 129, 0.16); color: var(--ok-fg); font-weight: 800;">Nilai Persediaan (Rp)</th>
                </tr>
            </thead>
            <tbody id="detailTableBody">
                <?php if (empty($detailItems)): ?>
                    <tr>
                        <td colspan="11" style="text-align: center; color: var(--text-muted); padding: 40px;">
                            <i data-lucide="inbox" style="width: 32px; height: 32px; margin-bottom: 8px; opacity: 0.5;"></i>
                            <div>Tidak ada data stok fisik aktif pada mitra yang dipilih.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = 1;
                    foreach ($detailItems as $row): 
                        $warna = $row['warna_identitas'] ?? '#0284c7';
                        $hargaPerKg = (float)$row['harga_per_kg'];
                        $totalNilai = (float)$row['total_nilai'];
                    ?>
                    <tr class="detail-row">
                        <td style="text-align: center; color: var(--text-muted); font-size: 12px;"><?= $no++ ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <span class="mitra-tag <?= strtolower($row['kode_mitra']) ?>" style="background: <?= htmlspecialchars($warna) ?>; padding: 2px 7px; font-size: 11px;">
                                    <?= htmlspecialchars($row['kode_mitra']) ?>
                                </span>
                                <span style="font-weight: 700; font-size: 13px;"><?= htmlspecialchars($row['nama_mitra']) ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="badge" style="background: rgba(14, 165, 233, 0.08); color: var(--aktif-fg); border: 1px solid rgba(14, 165, 233, 0.2); font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 700; padding: 3px 6px; border-radius: 5px;">
                                <?= htmlspecialchars($row['kode_gudang_accurate'] ?? '-') ?>
                            </span>
                        </td>
                        <td>
                            <code style="font-size: 12px; font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($row['kode_item']) ?></code>
                        </td>
                        <td>
                            <div style="font-weight: 800; font-size: 13.5px; color: var(--text-main);"><?= htmlspecialchars($row['nama_item']) ?></div>
                        </td>
                        <td>
                            <span class="badge badge-purple" style="font-weight: 700; font-size: 11px;">
                                <?= htmlspecialchars($row['nama_jenis']) ?>
                            </span>
                        </td>
                        <td style="text-align: right; font-weight: 800;">
                            <?= Helper::formatNumber($row['total_label']) ?> <span style="font-size: 11px; color: var(--text-muted); font-weight: normal;">pcs</span>
                        </td>
                        <td style="text-align: right; color: var(--text-muted); font-size: 12px;">
                            <?= Helper::formatKg($row['rata_rata_kg']) ?>
                        </td>
                        <td style="text-align: right; font-weight: 900; font-size: 14.5px; color: var(--aktif-fg); background: rgba(2, 132, 199, 0.03);">
                            <?= Helper::formatKg($row['total_kg']) ?>
                        </td>
                        <!-- KOLOM HARGA MASTER MITRA -->
                        <td style="text-align: right; background: rgba(16, 185, 129, 0.05);">
                            <?php if ($hargaPerKg > 0): ?>
                                <div style="font-weight: 800; font-size: 13.5px; color: var(--ok-fg);">
                                    <?= Helper::formatRupiah($hargaPerKg) ?> <span style="font-size: 11px; font-weight: 600; color: var(--text-muted);">/kg</span>
                                </div>
                                <div style="font-size: 10px; color: var(--text-muted); font-weight: 600;">
                                    harga beli terbobot
                                </div>
                            <?php else: ?>
                                <span class="badge badge-warning" style="font-size: 10.5px;" title="Harga belum diset di menu Master Harga">
                                    Rp 0 / kg
                                </span>
                            <?php endif; ?>
                        </td>
                        <!-- KOLOM TOTAL NILAI STOK -->
                        <td style="text-align: right; font-weight: 900; font-size: 15px; color: var(--ok-fg); background: rgba(16, 185, 129, 0.1);">
                            <?= Helper::formatRupiah($totalNilai) ?>
                            <?php if ((float)($row['total_nilai_jual'] ?? 0) > 0): ?>
                                <div style="font-size: 10.5px; font-weight: 600; color: var(--text-muted);">estimasi jual <?= Helper::formatRupiah($row['total_nilai_jual']) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background: var(--bg-muted); font-weight: 800; border-top: 2px solid var(--border);">
                    <td colspan="6" style="text-align: right; font-size: 13px; text-transform: uppercase;">
                        TOTAL AKUMULASI (<?= count($detailItems) ?> ITEM):
                    </td>
                    <td style="text-align: right; font-size: 14px; font-weight: 900;">
                        <?= Helper::formatNumber($subtotalDetailKemasan) ?> pcs
                    </td>
                    <td></td>
                    <td style="text-align: right; font-size: 15px; font-weight: 900; color: var(--aktif-fg); background: rgba(2, 132, 199, 0.08);">
                        <?= Helper::formatKg($subtotalDetailKg) ?>
                    </td>
                    <td style="text-align: right; font-size: 11.5px; color: var(--text-muted);">
                        Nilai Persediaan:
                    </td>
                    <td style="text-align: right; font-size: 16px; font-weight: 900; color: var(--ok-fg); background: rgba(16, 185, 129, 0.2);">
                        <?= Helper::formatRupiah($subtotalDetailNilai) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- PAGINATION STANDAR RINCIAN STOK MITRA (SELALU TAMPIL) -->
    <?php
    $pParams = $_GET;
    unset($pParams['p']);
    $baseMitraUrl = 'index.php?' . http_build_query($pParams);
    ?>
    <div class="card-footer no-print" style="padding: 14px 18px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; background: var(--bg-card);">
        <div style="font-size: 12.5px; color: var(--text-muted);">
            Menampilkan halaman <strong><?= $page ?></strong> dari <strong><?= $totalPages ?></strong> (Total <?= number_format($totalDetailItemsCount) ?> data stok)
        </div>
        <div style="display: flex; gap: 5px; align-items: center;">
            <?php if ($page > 1): ?>
                <a href="<?= $baseMitraUrl ?>&p=1" class="btn btn-outline btn-sm" style="padding: 4px 10px;">« Pertama</a>
                <a href="<?= $baseMitraUrl ?>&p=<?= $page - 1 ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">‹ Sebelumnya</a>
            <?php else: ?>
                <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">« Pertama</button>
                <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">‹ Sebelumnya</button>
            <?php endif; ?>

            <?php
            $startP = max(1, $page - 2);
            $endP = min($totalPages, $page + 2);
            for ($pi = $startP; $pi <= $endP; $pi++):
            ?>
                <a href="<?= $baseMitraUrl ?>&p=<?= $pi ?>" class="btn <?= $pi === $page ? 'btn-primary' : 'btn-outline' ?> btn-sm" style="padding: 4px 10px; font-weight: <?= $pi === $page ? '800' : '600' ?>; <?= $pi === $page ? 'background: var(--ok-solid); border-color: var(--ok-solid);' : '' ?>">
                    <?= $pi ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= $baseMitraUrl ?>&p=<?= $page + 1 ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">Selanjutnya ›</a>
                <a href="<?= $baseMitraUrl ?>&p=<?= $totalPages ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">Terakhir »</a>
            <?php else: ?>
                <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">Selanjutnya ›</button>
                <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">Terakhir »</button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($activeTab === 'matriks' || $activeTab === 'semua'): ?>
<!-- 4. TABEL PIVOT MATRIKS: 7 MITRA x JENIS BARANG (DENGAN VALUASI STOK) -->
<div class="card" style="margin-bottom: 26px;">
    <div class="card-header">
        <div>
            <h2 class="card-title">
                <i data-lucide="layers" style="width: 18px; height: 18px; color: var(--primary);"></i>
                <span>Matriks Segregasi Stok 7 Mitra x Kategori Komoditas</span>
            </h2>
            <div class="card-description">Sebaran bobot catch-weight per jenis barang dan total valuasi per gudang mitra Accurate</div>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;" class="no-print">
            <button type="button" class="btn btn-outline btn-sm" onclick="exportToCsv()">
                <i data-lucide="file-text" style="width: 14px; height: 14px;"></i>
                <span>CSV</span>
            </button>
            <span class="badge badge-success">Terkoneksi Real-time</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table-custom" id="matrixTable">
            <thead>
                <tr>
                    <th style="min-width: 200px;">Mitra Bisnis</th>
                    <th style="min-width: 180px;">Gudang Accurate</th>
                    <?php foreach ($jenisList as $j): ?>
                        <th style="text-align: right; min-width: 130px;">
                            <div style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($j['nama_jenis']) ?></div>
                            <div style="font-size: 11px; color: var(--primary); font-weight: 600;">(<?= htmlspecialchars($j['kode_jenis']) ?>)</div>
                        </th>
                    <?php endforeach; ?>
                    <th style="text-align: right; min-width: 160px; background: rgba(2, 132, 199, 0.08); color: var(--primary); font-weight: 800;">
                        TOTAL STOK FISIK
                    </th>
                    <th style="text-align: right; min-width: 180px; background: rgba(16, 185, 129, 0.12); color: var(--ok-fg); font-weight: 900;">
                        TOTAL VALUASI (RP)
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mitraList as $m): 
                    $mId = (int)$m['id'];
                    $totalMitraKg = $totalsPerMitra[$mId]['kg'] ?? 0;
                    $totalMitraLabel = $totalsPerMitra[$mId]['label'] ?? 0;
                    $totalMitraValuasi = $mitraSummaryMap[$mId]['total_nilai_stok'] ?? 0;
                    $warna = $m['warna_identitas'] ?? '#0284c7';
                    $pctMitra = $grandTotalKg > 0 ? round(($totalMitraKg / $grandTotalKg) * 100, 1) : 0;
                ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="mitra-tag <?= strtolower($m['kode_mitra']) ?>" style="background: <?= htmlspecialchars($warna) ?>;">
                                <?= htmlspecialchars($m['kode_mitra']) ?>
                            </span>
                            <span style="font-weight: 700; font-size: 13.5px;"><?= htmlspecialchars($m['nama_mitra']) ?></span>
                        </div>
                    </td>
                    <td>
                        <span class="badge" style="background: rgba(14, 165, 233, 0.08); color: var(--aktif-fg); border: 1px solid rgba(14, 165, 233, 0.25); font-family: 'JetBrains Mono', monospace; font-size: 11.5px; font-weight: 700; padding: 4px 8px; border-radius: 6px;">
                            <?= htmlspecialchars($m['kode_gudang_accurate'] ?? '-') ?>
                        </span>
                    </td>

                    <?php foreach ($jenisList as $j): 
                        $jId = $j['id'];
                        $cell = $matrix[$mId][$jId] ?? null;
                    ?>
                        <td style="text-align: right;">
                            <?php if ($cell && $cell['label'] > 0): ?>
                                <div style="font-weight: 800; font-size: 13.5px; color: var(--ok-fg);"><?= Helper::formatKg($cell['kg']) ?></div>
                                <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;"><?= Helper::formatNumber($cell['label']) ?> pcs</div>
                            <?php else: ?>
                                <span style="color: var(--text-muted); opacity: 0.35;">-</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>

                    <td style="text-align: right; background: rgba(2, 132, 199, 0.04); border-left: 1px solid rgba(2, 132, 199, 0.15);">
                        <div style="font-weight: 900; font-size: 14.5px; color: <?= htmlspecialchars($warna) ?>;"><?= Helper::formatKg($totalMitraKg) ?></div>
                        <div style="font-size: 11px; font-weight: 700; color: var(--text-muted);"><?= Helper::formatNumber($totalMitraLabel) ?> kemasan</div>
                    </td>

                    <td style="text-align: right; background: rgba(16, 185, 129, 0.08); border-left: 1px solid rgba(16, 185, 129, 0.2);">
                        <div style="font-weight: 900; font-size: 14.5px; color: var(--ok-fg);"><?= Helper::formatRupiah($totalMitraValuasi) ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;"><?= $pctMitra ?>% pangsa</div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: var(--bg-muted); font-weight: 800; border-top: 2px solid var(--border);">
                    <td colspan="2" style="font-size: 13px; text-transform: uppercase;">TOTAL KESELURUHAN GUDANG</td>
                    <?php 
                    $checkGrandKg = 0;
                    $checkGrandLabel = 0;
                    foreach ($jenisList as $j): 
                        $jId = $j['id'];
                        $totKg = $totalsPerJenis[$jId]['kg'] ?? 0;
                        $totLbl = $totalsPerJenis[$jId]['label'] ?? 0;
                        $checkGrandKg += $totKg;
                        $checkGrandLabel += $totLbl;
                    ?>
                        <td style="text-align: right;">
                            <div style="font-weight: 800; color: var(--text-main);"><?= Helper::formatKg($totKg) ?></div>
                            <div style="font-size: 11px; color: var(--text-muted);"><?= Helper::formatNumber($totLbl) ?> pcs</div>
                        </td>
                    <?php endforeach; ?>
                    <td style="text-align: right; background: var(--primary-gradient); color: #ffffff;">
                        <div style="font-size: 15px; font-weight: 900;"><?= Helper::formatKg($checkGrandKg) ?></div>
                        <div style="font-size: 11px; opacity: 0.95; font-weight: 600;"><?= Helper::formatNumber($checkGrandLabel) ?> Kemasan</div>
                    </td>
                    <td style="text-align: right; background: var(--ok-solid); color: #ffffff;">
                        <div style="font-size: 16px; font-weight: 900;"><?= Helper::formatRupiah($grandTotalValuasi) ?></div>
                        <div style="font-size: 11px; opacity: 0.95; font-weight: 600;">Valuasi Finansial 100%</div>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- JAVASCRIPT: FILTER INSTAN PENCARIAN & EKSPOR -->
<script>
    // Toggle Jumbotron Filter Panel
    function toggleMitraFilterPanel(forceState) {
        const panel = document.getElementById('panelJumbotronFilterMitra');
        if (!panel) return;
        const isHidden = panel.style.display === 'none' || panel.style.display === '';
        const shouldShow = forceState !== undefined ? forceState : isHidden;
        panel.style.display = shouldShow ? 'block' : 'none';
        if (shouldShow) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    // Fitur instant search realtime pada tabel detail
    function filterDetailTable() {
        const input = document.getElementById('tableSearchInput');
        const filter = input.value.toLowerCase().trim();
        const rows = document.querySelectorAll('#detailTableBody tr.detail-row');

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            if (text.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function exportToCsv() {
        const table = document.getElementById('matrixTable');
        let csv = [];
        for (let row of table.rows) {
            let rowData = [];
            for (let cell of row.cells) {
                let text = cell.innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/"/g, '""');
                rowData.push('"' + text.trim() + '"');
            }
            csv.push(rowData.join(','));
        }
        const csvBlob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(csvBlob);
        link.download = `Matriks_Stok_Multi_Mitra_${new Date().toISOString().slice(0,10)}.csv`;
        link.click();
    }
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
