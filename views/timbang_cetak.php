<?php
/**
 * Stasiun Penerimaan Barang Masuk & Timbang Berat
 * Desain Bersih, Ringkas, dan Mudah Dipahami Operator Gudang
 */

use App\Core\Helper;
use App\Models\Pemasok;
use App\Models\Mitra;
use App\Models\Item;
use App\Models\Label;
use App\Models\MasterHarga;
use App\Services\BarcodeService;
use App\Services\StockService;

$pageTitle = 'Penerimaan Barang Masuk - Warehouse System';
$pemasokList = Pemasok::getAllActive();
if (empty($pemasokList)) {
    $pemasokList = Pemasok::getAll();
}
$mitraList = Mitra::getAll();
$itemList = Item::getAll();
$jenisList = Item::getAllJenis();
$allPricesJson = MasterHarga::getAllPricesJson();
$suratJalanWeighList = Label::getSuratJalanListForWeighing();

// Default Surat Jalan untuk Pemasok Pertama
$firstPemasokKode = $pemasokList[0]['kode_pemasok'] ?? 'PMS-01';
$defaultSuratJalan = Label::generateNextSuratJalan($firstPemasokKode);
$sampleBarcode = $firstPemasokKode . '-JB02-' . date('ymd') . '-00001';

// -----------------------------------------------------------------------------
// FILTER & PAGINATION DAFTAR SELURUH PENERIMAAN
// -----------------------------------------------------------------------------
$pageNumber = max(1, (int)($_GET['p'] ?? 1));
$perPage = 15;
$offset = ($pageNumber - 1) * $perPage;

$filterPemasok   = !empty($_GET['filter_pemasok']) ? (int)$_GET['filter_pemasok'] : 0;
$filterItem      = !empty($_GET['filter_item']) ? (int)$_GET['filter_item'] : 0;
$filterMitra     = !empty($_GET['filter_mitra']) ? (int)$_GET['filter_mitra'] : 0;
$filterStartDate = !empty($_GET['filter_start_date']) ? trim($_GET['filter_start_date']) : (!empty($_GET['filter_date']) ? trim($_GET['filter_date']) : '');
$filterEndDate   = !empty($_GET['filter_end_date']) ? trim($_GET['filter_end_date']) : '';
$filterWeighed   = !empty($_GET['filter_weighed']) ? trim($_GET['filter_weighed']) : ''; // 'SUDAH', 'BELUM'
$searchQuery     = trim($_GET['search'] ?? '');

$filterParams = [];
if ($filterPemasok > 0) $filterParams['pemasok_id'] = $filterPemasok;
if ($filterItem > 0) $filterParams['item_id'] = $filterItem;
if (!empty($filterStartDate)) $filterParams['start_date'] = $filterStartDate;
if (!empty($filterEndDate)) $filterParams['end_date'] = $filterEndDate;
if (!empty($filterWeighed)) $filterParams['weighed_status'] = $filterWeighed;
if (!empty($searchQuery)) $filterParams['search'] = $searchQuery;

$totalPenerimaan = Label::countFiltered($filterParams);
$penerimaanList = Label::getFiltered($filterParams, $perPage, $offset);
$totalPages = max(1, (int)ceil($totalPenerimaan / $perPage));

// Ambil data penerimaan yang dikelompokkan per Surat Jalan dengan Pagination
$grpPageNumber = max(1, (int)($_GET['p_grp'] ?? 1));
$grpPerPage = 15;
$grpOffset = ($grpPageNumber - 1) * $grpPerPage;
$totalGrouped = Label::countGroupedPenerimaan($filterParams);
$groupedPenerimaan = Label::getGroupedPenerimaan($filterParams, $grpPerPage, $grpOffset);
$totalGroupedPages = max(1, (int)ceil($totalGrouped / $grpPerPage));
$itemsList = Item::getAll();

// Counter ringkasan
$countTotalAll = Label::countFiltered([]);
$countSudahDitimbang = Label::countFiltered(['weighed_status' => 'SUDAH']);
$countBelumDitimbang = Label::countFiltered(['weighed_status' => 'BELUM']);

// Tab aktif (default ke Penerimaan & Timbang Satu Pintu)
$activeTab = $_GET['tab'] ?? 'timbang_cetak';
if (!in_array($activeTab, ['timbang_cetak', 'massal', 'scantimbang', 'stok_gudang', 'riwayat_penerimaan'])) {
    $activeTab = 'timbang_cetak';
}
if ($activeTab === 'scantimbang') {
    $activeTab = 'timbang_cetak';
}
$prefillBarcode = trim($_GET['barcode'] ?? '');

// -----------------------------------------------------------------------------
// DATA SISA STOK GUDANG UTAMA (TRANSIT PENERIMAAN SEBELUM/SETELAH MUTASI KE MITRA)
// -----------------------------------------------------------------------------
$filterGudangPemasok   = !empty($_GET['pemasok_id']) ? (int)$_GET['pemasok_id'] : 0;
$filterGudangJenis     = !empty($_GET['jenis_id']) ? (int)$_GET['jenis_id'] : 0;
$filterGudangItem      = !empty($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$filterGudangTimbang   = !empty($_GET['status_timbang']) ? trim($_GET['status_timbang']) : '';
$filterGudangDate      = !empty($_GET['date_range']) ? trim($_GET['date_range']) : '';
$filterGudangStartDate = !empty($_GET['start_date']) ? trim($_GET['start_date']) : (!empty($_GET['filter_start_date']) ? trim($_GET['filter_start_date']) : '');
$filterGudangEndDate   = !empty($_GET['end_date']) ? trim($_GET['end_date']) : (!empty($_GET['filter_end_date']) ? trim($_GET['filter_end_date']) : '');
$filterGudangSort      = !empty($_GET['sort']) ? trim($_GET['sort']) : '';
$filterGudangSearch    = trim($_GET['search_gudang'] ?? '');
$gudangPage            = max(1, (int)($_GET['p_gudang'] ?? 1));
$gudangPerPage         = 20;
$gudangOffset          = ($gudangPage - 1) * $gudangPerPage;
$gudangSubTab          = $_GET['subtab'] ?? (!empty($_GET['p_gudang']) ? 'detail' : 'rekap');

$gudangFilterParams = [
    'pemasok_id'     => $filterGudangPemasok,
    'jenis_id'       => $filterGudangJenis,
    'item_id'        => $filterGudangItem,
    'status_timbang' => $filterGudangTimbang,
    'date_range'     => $filterGudangDate,
    'start_date'     => $filterGudangStartDate,
    'end_date'       => $filterGudangEndDate,
    'sort'           => $filterGudangSort,
    'search'         => $filterGudangSearch
];

// Helper URL untuk Quick Pills & Sortable Table Headers
function buildStockGudangUrl(array $overrides = []): string {
    $params = $_GET;
    $params['page'] = 'timbang_cetak';
    $params['tab'] = 'stok_gudang';
    unset($params['p_gudang']); // Reset ke halaman 1 saat ubah filter
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return 'index.php?' . http_build_query($params);
}

// Helper untuk Render TH Sortable Berpenampilan Modern
function renderStockGudangTh(string $label, string $ascKey, string $descKey, string $currentSort, string $align = 'left', string $extraStyle = ''): string {
    $isAsc = ($currentSort === $ascKey);
    $isDesc = ($currentSort === $descKey);
    $isActive = ($isAsc || $isDesc);

    // Toggle: jika sedang DESC, klik berikutnya jadi ASC; jika ASC jadi default/DESC
    $nextSort = $isDesc ? $ascKey : $descKey;
    $url = buildStockGudangUrl(['sort' => $nextSort]);

    $arrow = '↕';
    $arrowColor = 'opacity: 0.35;';
    if ($isDesc) {
        $arrow = '↓';
        $arrowColor = 'color: #4f46e5; opacity: 1; font-weight: 900;';
    } elseif ($isAsc) {
        $arrow = '↑';
        $arrowColor = 'color: #4f46e5; opacity: 1; font-weight: 900;';
    }

    $justify = 'flex-start';
    if ($align === 'center') $justify = 'center';
    if ($align === 'right') $justify = 'flex-end';

    $activeBg = $isActive ? 'background: rgba(79, 70, 229, 0.08); border-radius: 6px; padding: 2px 6px;' : '';

    return "
        <th style='text-align: {$align}; user-select: none; white-space: nowrap; {$extraStyle}'>
            <a href='{$url}' style='color: inherit; text-decoration: none; display: inline-flex; align-items: center; justify-content: {$justify}; gap: 5px; width: 100%; white-space: nowrap; {$activeBg}' title='Klik untuk mengurutkan berdasarkan {$label}'>
                <span>{$label}</span>
                <span style='font-size: 11px; {$arrowColor}'>{$arrow}</span>
            </a>
        </th>
    ";
}

// Base summary untuk indikator counter quick-pills (tanpa filter status_timbang & date_range)
$gudangBaseSummary = StockService::getStockGudangUtamaSummary([
    'pemasok_id' => $filterGudangPemasok,
    'jenis_id'   => $filterGudangJenis,
    'item_id'    => $filterGudangItem,
    'search'     => $filterGudangSearch
]);

$gudangSummary = StockService::getStockGudangUtamaSummary($gudangFilterParams);
$gudangPerItem = StockService::getStockGudangUtamaPerItem($gudangFilterParams);
$totalGudangLabels = StockService::countStockGudangUtamaLabels($gudangFilterParams);
$gudangLabels = StockService::getStockGudangUtamaLabels($gudangFilterParams, $gudangPerPage, $gudangOffset);
$totalGudangPages = max(1, (int)ceil($totalGudangLabels / $gudangPerPage));

require_once __DIR__ . '/layout/header.php';
?>

<!-- Header Halaman -->
<?php if ($activeTab === 'timbang_cetak'): ?>
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Penerimaan & Timbang Barang Masuk</h1>
        <p class="page-subtitle">Penimbangan keranjang masuk dan pencetakan barcode berbobot riil per surat jalan</p>
    </div>
</div>
<?php elseif ($activeTab === 'massal'): ?>
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Buat & Cetak Barcode Keranjang</h1>
        <p class="page-subtitle">Cetak stiker barcode untuk keranjang baru sebelum dilakukan penimbangan barang masuk</p>
    </div>
</div>
<?php elseif ($activeTab === 'stok_gudang'): ?>
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Stok Barang Gudang Utama</h1>
        <p class="page-subtitle">Sisa stok fisik di gudang penerimaan utama yang belum dialokasikan / dimutasi ke mitra bisnis</p>
    </div>
</div>
<?php elseif ($activeTab === 'riwayat_penerimaan'): ?>
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Riwayat Penerimaan Barang Masuk</h1>
        <p class="page-subtitle">Daftar lengkap seluruh arsip label barcode kemasan, surat jalan masuk, dan bobot timbangan riil</p>
    </div>
</div>
<?php else: ?>
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Penerimaan & Timbang Barang Masuk</h1>
        <p class="page-subtitle">Penimbangan keranjang masuk dan pencetakan barcode</p>
    </div>
</div>
<?php endif; ?>

<!-- Hidden fallback container untuk template barcode SVG -->
<div id="barcodeSvgContainer" style="display: none;">
    <?= BarcodeService::generateCode128Svg($sampleBarcode, 120, 2.0) ?>
</div>

<!-- ========================================================================= -->
<!-- SUB-MENU UTAMA: PENERIMAAN & TIMBANG SATU PINTU (ONE-STOP WEIGH & PRINT)   -->
<!-- ========================================================================= -->
<div id="sectionOneStopWeigh" style="<?= $activeTab === 'timbang_cetak' ? 'display: block;' : 'display: none;' ?>">
    <!-- Grid Desktop 2-Kolom: Form Timbang & Stiker di Kiri, Live Rekap Surat Jalan di Kanan -->
    <div style="display: grid; grid-template-columns: minmax(380px, 1.25fr) minmax(360px, 1fr); gap: 24px; align-items: start; margin-bottom: 30px;">
        
        <!-- KOLOM KIRI: STASIUN KERJA PENIMBANGAN & CETAK LANGSUNG -->
        <div class="card no-print" style="box-shadow: var(--shadow-md);">
            <div class="card-header" style="border-bottom: 1.5px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 class="card-title" style="font-size: 16px; display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="scale" style="width: 18px; height: 18px; color: #10b981;"></i>
                        <span>Input Timbang & Cetak Barcode</span>
                    </h2>
                    <div class="card-description">Input penimbangan keranjang masuk per surat jalan</div>
                </div>
            </div>

            <div style="padding: 16px;">
                <!-- 1. HEADER SESI SURAT JALAN -->
                <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 14px; margin-bottom: 16px;">
                    <div style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                        <span>Informasi Surat Jalan</span>
                    </div>
                    <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 12px;">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" for="onestop_pemasok_id" style="font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px; display: flex; align-items: center; gap: 5px;">
                                <i data-lucide="building-2" style="width: 14px; height: 14px; color: #64748b;"></i>
                                <span>Pemasok <span style="color: #ef4444;">*</span></span>
                            </label>
                            <select id="onestop_pemasok_id" class="form-control" style="font-weight: 700; height: 42px; font-size: 13px;" onchange="onOneStopPemasokChanged()">
                                <?php foreach ($pemasokList as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-kode="<?= htmlspecialchars($p['kode_pemasok']) ?>" data-nama="<?= htmlspecialchars($p['nama_pemasok']) ?>">
                                        <?= htmlspecialchars($p['kode_pemasok']) ?> — <?= htmlspecialchars($p['nama_pemasok']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                <label class="form-label" for="onestop_no_sj" style="font-size: 12px; font-weight: 700; color: #334155; margin: 0; display: flex; align-items: center; gap: 5px;">
                                    <i data-lucide="file-text" style="width: 14px; height: 14px; color: #64748b;"></i>
                                    <span>No. Surat Jalan <span style="color: #ef4444;">*</span></span>
                                </label>
                                <span class="badge badge-secondary" style="font-size: 10px; cursor: pointer;" onclick="onOneStopPemasokChanged()" title="Klik untuk refresh nomor surat jalan">Auto</span>
                            </div>
                            <input type="text" id="onestop_no_sj" class="form-control" value="<?= htmlspecialchars($defaultSuratJalan) ?>" style="font-family: 'JetBrains Mono', monospace; font-weight: 800; color: #1e40af; height: 42px; background: #ffffff; border: 1.5px solid #93c5fd; font-size: 13.5px;" placeholder="No Surat Jalan..." onchange="onOneStopSjChanged()">
                        </div>
                    </div>
                </div>

                <!-- 2. PEMILIHAN KATEGORI & PRODUK -->
                <div style="margin-bottom: 16px;">
                    <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 12px;">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" for="onestop_jenis_filter" style="font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px; display: flex; align-items: center; gap: 5px;">
                                <i data-lucide="layers" style="width: 14px; height: 14px; color: #64748b;"></i>
                                <span>Kategori <span style="color: #ef4444;">*</span></span>
                            </label>
                            <select id="onestop_jenis_filter" class="form-control" style="font-weight: 700; height: 42px; font-size: 13px;" onchange="onOneStopJenisChanged()">
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($jenisList as $j): ?>
                                    <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['nama_jenis']) ?> (<?= htmlspecialchars($j['kode_jenis']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" for="onestop_item_id" style="font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px; display: flex; align-items: center; gap: 5px;">
                                <i data-lucide="package" style="width: 14px; height: 14px; color: #64748b;"></i>
                                <span>Produk <span style="color: #ef4444;">*</span></span>
                            </label>
                            <select id="onestop_item_id" class="form-control" disabled style="font-weight: 800; height: 42px; font-size: 13.5px;" onchange="onOneStopProductChanged(this.value)">
                                <option value="">-- Pilih Kategori Dahulu --</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 3. DISPLAY TIMBANGAN DIGITAL & INPUT NETTO -->
                <div style="background: #f8fafc; border: 2px solid #10b981; border-radius: 10px; padding: 16px; margin-bottom: 16px; text-align: center;">
                    <div style="display: flex; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 12px; font-weight: 800; color: #047857; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                            <i data-lucide="scale" style="width: 15px; height: 15px;"></i>
                            <span>Berat Bersih</span>
                        </span>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                        <input type="number" id="onestop_berat_netto" step="0.01" min="0.01" max="999.99" class="form-control" placeholder="0.00" 
                               style="font-family: 'JetBrains Mono', monospace; font-size: 44px; font-weight: 900; text-align: center; color: #0f172a; height: 70px; width: 220px; border: 2px solid #10b981; border-radius: 8px; background: #ffffff;"
                               onkeydown="if(event.key === 'Enter') { event.preventDefault(); handleSaveWeighedBox(); }">
                        <span style="font-size: 26px; font-weight: 900; color: #047857;">KG</span>
                    </div>

                    <div style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 10px; font-size: 11.5px; color: #64748b; flex-wrap: wrap;">
                        <span>Tekan <strong style="color: #0f172a; background: #e2e8f0; padding: 2px 6px; border-radius: 4px;">[ENTER]</strong> untuk tambah box</span>
                    </div>
                </div>

                <!-- 4. TOMBOL TAMBAH BOX KE DAFTAR -->
                <button type="button" id="btnSaveWeighedBox" class="btn btn-primary" onclick="handleSaveWeighedBox()" style="width: 100%; height: 46px; font-size: 14.5px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 8px; background: #059669; border-color: #059669; border-radius: 8px; cursor: pointer;">
                    <i data-lucide="plus-circle" style="width: 18px; height: 18px;"></i>
                    <span id="labelBtnSaveWeighed">Tambah Box</span>
                </button>
            </div>
        </div>

        <!-- KOLOM KANAN: LIVE SUMMARY SURAT JALAN & DAFTAR KERANJANG TERAKHIR -->
        <div class="card no-print" style="box-shadow: var(--shadow-md);">
            <div class="card-header" style="border-bottom: 1.5px solid var(--border);">
                <div>
                    <h2 class="card-title" style="font-size: 16px; display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="clipboard-check" style="width: 18px; height: 18px; color: #3b82f6;"></i>
                        <span>Ringkasan Surat Jalan</span>
                    </h2>
                    <div class="card-description" id="summarySjSubtitle">No. SJ: <?= htmlspecialchars($defaultSuratJalan) ?></div>
                </div>
            </div>

            <div style="padding: 16px;">
                <!-- Rincian Produk di Surat Jalan Ini (Sama Persis seperti Tabel Scan Pengeluaran) -->
                <div style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 11.5px; font-weight: 800; color: #64748b; text-transform: uppercase;">Rincian Produk:</span>
                        <span style="font-size: 11.5px; color: #94a3b8;">Terbaru di atas</span>
                    </div>
                    <div id="containerProductBreakdown" style="display: none;"></div>
                    <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                        <table class="table-custom" id="tableInboundRecentBoxes">
                            <thead>
                                <tr>
                                    <th style="width: 40px; text-align: center;">No</th>
                                    <th>ID Barcode Kemasan</th>
                                    <th>Nama Barang / Produk</th>
                                    <th>Kategori</th>
                                    <th style="text-align: right; width: 110px;">Netto (Kg)</th>
                                    <th style="text-align: center; width: 60px; position: sticky; right: 0; background: var(--bg-muted, #f8fafc); z-index: 25; box-shadow: -2px 0 4px rgba(0,0,0,0.04);">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="containerRecentBoxes">
                                <tr id="emptyInboundRow">
                                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 40px 20px;">
                                        <div style="font-weight: 700; font-size: 13.5px; color: var(--text-main);">Belum ada box ditimbang</div>
                                        <div style="font-size: 11.5px; margin-top: 3px;">Timbang box pada formulir di sebelah kiri untuk memasukkan ke sesi ini.</div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot id="inboundTotalFoot" style="display: none;">
                                <tr style="background: var(--bg-muted);">
                                    <td colspan="4" style="text-align: right; font-weight: 700; color: var(--text-muted); font-size: 12.5px;">Total <span id="inboundTotalBoxCount">0</span> box</td>
                                    <td id="inboundTotalBerat" style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; font-size: 13px;">0.00 kg</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Tombol Cetak Semua Barcode Thermal di Sesi Ini -->
                <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 11.5px; font-weight: 800; color: #334155; text-transform: uppercase;">
                            Cetak Barcode Box:
                        </span>
                        <span class="badge badge-purple" id="badgePrintCount" style="font-size: 11px; font-weight: 800;">0 Barcode</span>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="printAllBarcodesInSession('thermal')" id="btnPrintBatchThermal" style="width: 100%; font-weight: 700; font-size: 13px; height: 40px; display: flex; align-items: center; justify-content: center; gap: 8px; background: #4f46e5; border-color: #4f46e5; border-radius: 6px; cursor: pointer;">
                        <i data-lucide="tag" style="width: 15px; height: 15px;"></i>
                        <span>Cetak Barcode</span>
                    </button>
                </div>

                <!-- Tombol Tunggal: Cetak Surat Jalan (Disabled sampai barcode dicetak) -->
                <button type="button" class="btn btn-secondary" onclick="handleFinishInboundSession()" id="btnFinishSession" disabled style="width: 100%; font-weight: 700; height: 42px; display: flex; align-items: center; justify-content: center; gap: 8px; background: #1e293b; color: #ffffff; border-radius: 6px; cursor: not-allowed; opacity: 0.5;" title="Silakan cetak barcode terlebih dahulu">
                    <i data-lucide="check-circle" style="width: 16px; height: 16px;"></i>
                    <span id="labelBtnFinishSession">Cetak Surat Jalan</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- SUB-MENU 1: BUAT & CETAK BARCODE KERANJANG (DESKTOP WORKBENCH LAYOUT)     -->
<!-- ========================================================================= -->
<div id="sectionMassal" style="<?= $activeTab === 'massal' ? 'display: block;' : 'display: none;' ?>">
    <!-- Grid Desktop 2-Kolom: Form Pembuatan di Kiri, Live Result & Preview di Kanan -->
    <div style="display: grid; grid-template-columns: minmax(360px, 1.15fr) minmax(360px, 1fr); gap: 24px; align-items: start; margin-bottom: 30px;">
        <!-- Kolom Kiri: Formulir Cetak Barcode Baru -->
        <div class="card no-print" style="box-shadow: var(--shadow-md);">
            <div class="card-header" style="border-bottom: 1.5px solid var(--border);">
                <div>
                    <h2 class="card-title" style="font-size: 17px;">
                        <i data-lucide="printer" style="width: 20px; height: 20px; color: #8b5cf6;"></i>
                        <span>Cetak Barcode Keranjang Baru</span>
                    </h2>
                    <div class="card-description">Pilih pemasok, nama barang, dan tentukan jumlah stiker yang ingin dicetak</div>
                </div>
            </div>

            <form id="formBulkBarcode" style="padding-top: 6px;">
                <!-- Pemasok & Surat Jalan -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="bulk_pemasok_id">Pemasok <span style="color:red;">*</span></label>
                        <select name="pemasok_id" id="bulk_pemasok_id" class="form-control" required style="font-weight: 700; height: 42px;">
                            <?php foreach ($pemasokList as $p): ?>
                                <option value="<?= $p['id'] ?>" data-kode="<?= htmlspecialchars($p['kode_pemasok']) ?>" data-nama="<?= htmlspecialchars($p['nama_pemasok']) ?>">
                                    <?= htmlspecialchars($p['kode_pemasok']) ?> - <?= htmlspecialchars($p['nama_pemasok']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label class="form-label" for="bulk_no_sj" style="margin: 0;">No. Surat Jalan <span style="color:red;">*</span></label>
                            <span class="badge badge-purple" style="font-size: 10px; padding: 2px 6px; font-weight: 700;">Otomatis Sistem</span>
                        </div>
                        <input type="text" name="no_surat_jalan" id="bulk_no_sj" class="form-control" value="<?= htmlspecialchars($defaultSuratJalan) ?>" required readonly style="font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--primary); height: 42px; background: #f8fafc; cursor: not-allowed; border: 1.5px solid #cbd5e1;" title="Nomor Surat Jalan otomatis berurutan dari sistem dan tidak dapat diedit manual oleh pengguna">
                    </div>
                </div>

                <!-- Dropdown Kategori & Produk -->
                <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 14px; margin-bottom: 14px;">
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="bulk_jenis_filter">Jenis / Kategori <span style="color:red;">*</span></label>
                        <select id="bulk_jenis_filter" class="form-control" required style="font-weight: 700; height: 42px;">
                            <option value="">-- Pilih Jenis / Kategori Terlebih Dahulu --</option>
                            <?php foreach ($jenisList as $j): ?>
                                <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['nama_jenis']) ?> (<?= htmlspecialchars($j['kode_jenis']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="bulk_item_id">Produk <span style="color:red;">*</span></label>
                        <select name="item_id" id="bulk_item_id" class="form-control" required style="font-weight: 700; height: 42px;">
                            <option value="">-- Pilih Jenis Dahulu --</option>
                        </select>
                    </div>
                </div>

                <!-- Input Jumlah Barcode yang Ingin Dicetak -->
                <div style="background: var(--bg-muted); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 14px; margin-bottom: 18px;">
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="bulk_quantity" style="font-weight: 800; font-size: 13.5px; color: #8b5cf6; display: flex; justify-content: space-between; align-items: center;">
                            <span>Jumlah Barcode yang Ingin Dicetak <span style="color:red;">*</span></span>
                            <span class="badge badge-purple" style="font-size: 11px;">10 Pcs / Lembar A4</span>
                        </label>
                        
                        <div style="display: flex; gap: 10px; align-items: center; margin-top: 8px; flex-wrap: wrap;">
                            <input type="number" min="1" max="200" step="1" name="quantity_barcode" id="bulk_quantity" class="form-control" value="10" required 
                                   onkeydown="if(['-', '+', 'e', 'E', '.'].includes(event.key)) event.preventDefault();"
                                   oninput="let v = this.value.replace(/[^0-9]/g, ''); if(v === '' || parseInt(v) < 1) { this.value = 1; } else { this.value = Math.min(200, parseInt(v)); }"
                                   style="font-size: 24px; font-weight: 900; text-align: center; color: #8b5cf6; height: 46px; width: 100px;">
                            
                            <!-- Pilihan Cepat Kuantitas Sederhana -->
                            <div class="qty-pill-group">
                                <button type="button" class="qty-pill-btn active" data-qty="10" onclick="setBulkQty(10, this)" title="1 Lembar A4 Penuh (10 Barcode)">10 Pcs</button>
                                <button type="button" class="qty-pill-btn" data-qty="20" onclick="setBulkQty(20, this)" title="2 Lembar A4">20 Pcs</button>
                                <button type="button" class="qty-pill-btn" data-qty="5" onclick="setBulkQty(5, this)" title="Setengah Lembar A4 (5 Pcs)">5 Pcs</button>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" id="btnGenerateBulk" style="width: 100%; height: 50px; font-weight: 800; font-size: 15px; background: #8b5cf6; border-color: #8b5cf6; cursor: pointer;">
                    <i data-lucide="barcode" style="width: 18px; height: 18px;"></i>
                    <span>Buat Barcode Kemasan</span>
                </button>
            </form>
        </div>

        <!-- Kolom Kanan: Kartu Hasil Barcode Massal Siap Cetak (Desktop Live Preview) -->
        <div class="card no-print" id="bulkResultCard" style="box-shadow: var(--shadow-md);">
            <div class="card-header" style="border-bottom: 1.5px solid var(--border);">
                <div>
                    <h2 class="card-title" style="font-size: 16px;">
                        <i data-lucide="check-circle-2" style="width: 18px; height: 18px; color: #10b981;"></i>
                        <span>Hasil Barcode Massal Siap Cetak</span>
                    </h2>
                    <div class="card-description" id="bulkResultDesc">Daftar barcode siap cetak lembar PDF</div>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-outline btn-sm" id="btnPreviewSheet" style="display: none;" onclick="openSheetPreviewModal()">
                        <i data-lucide="eye" style="width: 15px; height: 15px; color: #8b5cf6;"></i>
                        <span>Pratinjau Lembar</span>
                    </button>
                    <button type="button" class="btn btn-success btn-sm" id="btnCetakSemuaBulk" style="display: none;" onclick="printBulkContainer()">
                        <i data-lucide="printer" style="width: 15px; height: 15px;"></i>
                        <span>Cetak PDF</span>
                    </button>
                </div>
            </div>

            <!-- Placeholder sederhana saat belum ada barcode -->
            <div id="bulkStatusPlaceholder" style="padding: 40px 24px; text-align: center; color: var(--text-muted); font-size: 13.5px;">
                <div>Belum ada barcode yang dibuat.</div>
                <div style="font-size: 12px; margin-top: 4px; color: var(--text-muted);">Pilih pemasok dan produk pada formulir di sebelah kiri lalu klik <strong>"Buat Barcode Kemasan"</strong>.</div>
            </div>

            <!-- List Preview Hasil Batch -->
            <div id="bulkResultList" style="display: none; max-height: 520px; overflow-y: auto; padding: 14px; gap: 10px; flex-direction: column;">
                <!-- Dinamis diisi JS -->
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- SUB-MENU 2: SCAN BARCODE & TIMBANG BERAT RIIL (DESKTOP WORKBENCH LAYOUT)   -->
<!-- ========================================================================= -->
<div id="sectionScanTimbang" style="<?= $activeTab === 'scantimbang' ? 'display: block;' : 'display: none;' ?>">
    <!-- Grid Desktop 2-Kolom: Stasiun Timbang di Kiri, Live History Sesi Timbang di Kanan -->
    <div style="display: grid; grid-template-columns: minmax(360px, 1.25fr) minmax(340px, 1fr); gap: 24px; align-items: start; margin-bottom: 30px;">
        <!-- Formulir Scan Barcode & Rekam Timbangan Riil -->
        <div class="card no-print" style="box-shadow: var(--shadow-md);">
            <div class="card-header" style="border-bottom: 1.5px solid var(--border);">
                <div>
                    <h2 class="card-title" style="font-size: 17px;">
                        <i data-lucide="scale" style="width: 20px; height: 20px; color: #10b981;"></i>
                        <span>Stasiun Timbang Barang Masuk</span>
                    </h2>
                    <div class="card-description">Scan barcode di keranjang, lalu masukkan bobot riil timbangan</div>
                </div>
                <span class="badge badge-success">Stasiun Timbang</span>
            </div>

            <!-- 0. Pemilihan Surat Jalan Penerimaan & List Barcode Belum Ditimbang -->
            <div style="background: #ffffff; border: 2px solid #3b82f6; border-radius: var(--radius); padding: 16px; margin: 16px 0 14px 0; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.08);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <label for="selectWeighSJ" style="font-size: 14px; font-weight: 800; color: #1e40af; display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                            <i data-lucide="file-text" style="width: 18px; height: 18px; color: #2563eb;"></i>
                            <span>Pilih Surat Jalan Penerimaan Barang Masuk:</span>
                        </label>
                        <div style="font-size: 11.5px; color: var(--text-muted);">
                            Barcode keranjang yang discan wajib terdaftar dan sesuai dengan Surat Jalan yang dipilih.
                        </div>
                    </div>
                    <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                        <button type="button" id="btnRefreshSJList" class="btn btn-outline btn-sm" title="Segarkan pilihan Surat Jalan" style="padding: 4px 10px; font-size: 11.5px; height: 32px;">
                            <i data-lucide="refresh-cw" style="width: 13px; height: 13px;"></i>
                            <span>Refresh SJ</span>
                        </button>
                        <button type="button" id="btnSelesaiTimbangSJ" class="btn btn-sm" onclick="handleSelesaiTimbangClick()" disabled style="padding: 4px 12px; font-size: 12px; font-weight: 800; height: 32px; display: inline-flex; align-items: center; gap: 6px; background: #cbd5e1; border-color: #cbd5e1; color: #64748b; cursor: not-allowed; transition: all 0.2s;" title="Scan barcode dan isi berat timbangan terlebih dahulu">
                            <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                            <span id="labelBtnSelesaiTimbang">Selesai Timbang</span>
                        </button>
                        <a id="btnCetakSJPenerimaan" href="#" target="_blank" class="btn btn-primary btn-sm" onclick="handleCetakSJClick(event)" style="padding: 4px 12px; font-size: 12px; font-weight: 700; height: 32px; display: inline-flex; align-items: center; gap: 6px; background: #64748b; border-color: #64748b; color: #ffffff; opacity: 0.55; cursor: not-allowed; transition: all 0.2s;" title="Tombol cetak terkunci. Klik 'Selesai Timbang' terlebih dahulu setelah selesai menimbang.">
                            <i data-lucide="printer" style="width: 14px; height: 14px;"></i>
                            <span>Cetak Surat Jalan</span>
                        </a>
                    </div>
                </div>

                <!-- Dropdown Pilih Surat Jalan -->
                <div style="position: relative;">
                    <select id="selectWeighSJ" class="form-control" style="font-size: 13.5px; font-weight: 700; height: 46px;">
                        <option value="">-- Pilih Surat Jalan Penerimaan --</option>
                        <?php if (empty($suratJalanWeighList)): ?>
                            <option value="" disabled>-- Tidak ada Surat Jalan yang menunggu penimbangan --</option>
                        <?php else: ?>
                            <?php foreach ($suratJalanWeighList as $sj): ?>
                                <option value="<?= htmlspecialchars($sj['no_surat_jalan']) ?>" 
                                        data-item="<?= htmlspecialchars($sj['nama_item']) ?>" 
                                        data-pemasok="<?= htmlspecialchars($sj['nama_pemasok']) ?>"
                                        data-total="<?= (int)$sj['total_pcs'] ?>"
                                        data-unweighed="<?= (int)$sj['belum_ditimbang'] ?>"
                                        data-weighed="<?= (int)$sj['sudah_ditimbang'] ?>"
                                        data-berat="<?= (float)$sj['total_berat'] ?>">
                                    <?= htmlspecialchars($sj['no_surat_jalan']) ?> — <?= htmlspecialchars($sj['nama_item']) ?> | <?= htmlspecialchars($sj['nama_pemasok']) ?> (Total: <?= (int)$sj['total_pcs'] ?> Barcode)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Box Ringkasan Surat Jalan Terpilih (Bersih & Ringkas tanpa tumpukan chip barcode) -->
                <div id="containerSJUnweighed" style="display: none; margin-top: 14px; padding-top: 14px; border-top: 1.5px dashed #cbd5e1;">
                    <!-- Ringkasan Info SJ Terpilih -->
                    <div style="display: flex; justify-content: space-between; align-items: center; background: #f1f5f9; border-radius: 8px; padding: 10px 14px; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
                        <div>
                            <span style="font-size: 12px; color: var(--text-muted);">Surat Jalan:</span>
                            <span id="infoSJNo" style="font-family: 'JetBrains Mono', monospace; font-size: 13.5px; font-weight: 800; color: #1d4ed8; margin-left: 4px;">-</span>
                            <span style="font-size: 12px; color: #64748b; margin-left: 8px;">• Item: <strong id="infoSJItem" style="color: #0f172a;">-</strong></span>
                            <span style="font-size: 12px; color: #64748b; margin-left: 8px;">• Pemasok: <strong id="infoSJPemasok" style="color: #0f172a;">-</strong></span>
                        </div>
                        <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                            <span class="badge badge-secondary" id="badgeSJTotal">Total: 0 Pcs</span>
                            <span class="badge badge-warning" id="badgeSJSisa" style="font-weight: 800;">Belum: 0 Pcs</span>
                            <span class="badge badge-success" id="badgeSJSelesai">Sudah: 0 Pcs</span>
                            <button type="button" class="btn btn-outline btn-sm" id="btnLihatBarcodeDiRiwayat" onclick="openSJBarcodeHistoryModal(document.getElementById('infoSJNo')?.innerText)" style="font-size: 11.5px; font-weight: 700; color: #4f46e5; border-color: #c7d2fe; background: #eef2ff; cursor: pointer;" title="Lihat daftar barcode keranjang surat jalan ini di riwayat">
                                <i data-lucide="list" style="width: 13px; height: 13px;"></i>
                                <span>Lihat Daftar Barcode</span>
                            </button>
                        </div>
                    </div>

                    <!-- Banner jika semua keranjang pada SJ terpilih sudah selesai ditimbang -->
                    <div id="allWeighedSuccessNotice" style="display: none; background: #ecfdf5; border: 1.5px solid #10b981; border-radius: 8px; padding: 14px; text-align: center;">
                        <div style="font-size: 15px; font-weight: 800; color: #047857; display: flex; align-items: center; justify-content: center; gap: 6px; margin-bottom: 4px;">
                            <i data-lucide="check-circle-2" style="width: 20px; height: 20px;"></i>
                            <span>Semua Keranjang pada Surat Jalan Ini Telah Selesai Ditimbang!</span>
                        </div>
                        <div style="font-size: 12.5px; color: #065f46; margin-bottom: 0;">
                            Total berat riil penerimaan barang masuk telah tercatat lengkap di sistem.
                            Gunakan tombol Selesai Timbang dan Cetak Surat Jalan di bagian atas.
                        </div>
                    </div>
                </div>
            </div>

            <!-- 1. Scanner Barcode Input Utama -->
            <div style="background: var(--bg-muted); border: 2px solid var(--primary); border-radius: var(--radius); padding: 16px; margin: 16px 0;">
                <label class="form-label" for="scanBarcodeWeighInput" style="font-size: 14px; font-weight: 800; color: var(--primary); margin-bottom: 8px;">
                    Arahkan Scanner ke Barcode:
                </label>
                <div style="display: flex; gap: 8px;">
                    <div style="position: relative; flex: 1;">
                        <input type="text" id="scanBarcodeWeighInput" class="form-control" placeholder="Scan barcode keranjang di sini (atau ketik lalu tekan Enter)..." value="<?= htmlspecialchars($prefillBarcode) ?>" autofocus style="font-family: 'JetBrains Mono', monospace; font-size: 18px; font-weight: 800; height: 48px; padding-left: 42px;">
                        <i data-lucide="barcode" style="position: absolute; left: 14px; top: 14px; width: 20px; height: 20px; color: var(--text-muted);"></i>
                    </div>
                    <button type="button" class="btn btn-primary" id="btnLookupBarcode" style="font-weight: 700; padding: 0 18px;">
                        Cari / Verifikasi
                    </button>
                </div>
            </div>

            <!-- 2. Kartu Rincian Produk Hasil Scan -->
            <div id="cardItemScanned" style="display: none; border: 1.5px solid var(--border); border-radius: var(--radius); padding: 14px; margin-bottom: 16px; background: var(--bg-card);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                    <div>
                        <span class="badge badge-secondary" id="scanBadgeMitra">PEMASOK</span>
                        <h3 id="scanItemTitle" style="font-size: 18px; font-weight: 800; margin: 4px 0 2px 0;">-</h3>
                        <div style="font-size: 12.5px; color: var(--text-muted);">
                            SJ: <strong id="scanSJText" style="color: var(--primary);">-</strong>
                        </div>
                    </div>
                    <div id="scanStatusBadgeContainer">
                        <span class="badge badge-warning" id="scanStatusBadge">Belum Ditimbang</span>
                    </div>
                </div>
            </div>

            <!-- 3. Form Input Bobot Timbangan Riil -->
            <div id="formWeighArea" style="display: none;">
                <div style="background: rgba(16, 185, 129, 0.08); border: 2px solid #10b981; border-radius: var(--radius); padding: 16px; margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label class="form-label" for="scanWeightValue" style="margin: 0; font-size: 14px; font-weight: 800; color: #047857;">
                            Berat Timbangan Riil (KG) <span style="color:red;">*</span>
                        </label>
                    </div>

                    <div style="position: relative; margin-bottom: 10px;">
                        <input type="number" step="0.01" id="scanWeightValue" class="form-control" placeholder="0.00" style="font-size: 28px; font-weight: 900; color: #047857; padding-right: 60px; height: 52px;">
                        <span style="position: absolute; right: 16px; top: 12px; font-size: 18px; font-weight: 900; color: #047857;">KG</span>
                    </div>

                    <!-- Tombol Cepat Pilihan Berat Sederhana -->
                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="setScanWeight(5.00)" style="padding: 3px 8px; font-weight: 700;">5 kg</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setScanWeight(10.00)" style="padding: 3px 8px; font-weight: 700;">10 kg</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setScanWeight(15.00)" style="padding: 3px 8px; font-weight: 700;">15 kg</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setScanWeight(20.00)" style="padding: 3px 8px; font-weight: 700;">20 kg</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setScanWeight(25.00)" style="padding: 3px 8px; font-weight: 700;">25 kg</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setScanWeight(30.00)" style="padding: 3px 8px; font-weight: 700;">30 kg</button>
                    </div>
                </div>

                <button type="button" class="btn btn-success btn-lg" id="btnSimpanBerat" style="width: 100%; height: 50px; font-size: 15px; font-weight: 800;">
                    <i data-lucide="check" style="width: 20px; height: 20px;"></i>
                    <span>Simpan Berat Riil</span>
                </button>
            </div>
        </div>

        <!-- Kolom Kanan: Kartu Barcode yang Baru Saja Ditimbang (Sesi Ini) -->
        <div class="card no-print" id="sessionWeighedCard" style="box-shadow: var(--shadow-md);">
            <div class="card-header" style="border-bottom: 1.5px solid var(--border);">
                <div>
                    <h2 class="card-title" style="font-size: 16px;">
                        <i data-lucide="history" style="width: 18px; height: 18px; color: #10b981;"></i>
                        <span>Barcode yang Baru Saja Ditimbang</span>
                    </h2>
                    <div class="card-description">Daftar barcode yang sudah selesai ditimbang pada sesi ini</div>
                </div>
            </div>

            <div id="sessionWeighedPlaceholder" style="padding: 40px 24px; text-align: center; color: var(--text-muted); font-size: 13.5px;">
                <div>Belum ada barcode yang ditimbang sesi ini.</div>
                <div style="font-size: 12px; margin-top: 4px; color: var(--text-muted);">Pilih Surat Jalan di sebelah kiri, scan barcode keranjang, lalu simpan bobot riil.</div>
            </div>

            <div id="sessionWeighedList" style="display: none; max-height: 520px; overflow-y: auto; padding: 14px; gap: 10px; flex-direction: column;">
                <!-- Dinamis diisi JS -->
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- SUB-MENU 3: STOK BARANG GUDANG UTAMA (SISA STOK PENERIMAAN)               -->
<!-- ========================================================================= -->
<div id="sectionStokGudang" style="<?= $activeTab === 'stok_gudang' ? 'display: block;' : 'display: none;' ?>">
    <!-- WRAPPER AJAX STOK GUDANG (MEMUNGKINKAN FILTER & SORT TANPA RELOAD HALAMAN) -->

    <!-- WRAPPER AJAX STOK GUDANG (MEMUNGKINKAN FILTER & SORT TANPA RELOAD HALAMAN) -->
    <div id="ajaxStokGudangContainer" style="position: relative;">
        <!-- 4 KARTU KPI / STATISTIK GUDANG UTAMA -->
        <div class="grid grid-cols-4" style="gap: 16px; margin-bottom: 22px;">
        <!-- Card 1: Total Sisa Stok KG -->
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Total Sisa Stok Gudang</div>
                <div class="kpi-value"><?= number_format($gudangSummary['total_kg'], 2, ',', '.') ?> <span class="kpi-unit">KG</span></div>
                <div class="kpi-sub">Siap Mutasi: <?= number_format($gudangSummary['total_kg_siap'], 2, ',', '.') ?> KG</div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="package" style="width: 18px; height: 18px;"></i></div>
        </div>

        <!-- Card 2: Total Keranjang -->
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Jumlah Keranjang / Pcs</div>
                <div class="kpi-value"><?= number_format($gudangSummary['total_barcode']) ?> <span class="kpi-unit">Keranjang</span></div>
                <div class="kpi-sub">Rata-rata: <strong><?= number_format($gudangSummary['avg_kg_per_keranjang'], 2, ',', '.') ?></strong> kg/keranjang</div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="boxes" style="width: 18px; height: 18px;"></i></div>
        </div>

        <!-- Card 3: Status Penimbangan -->
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Status Penimbangan</div>
                <div class="kpi-value"><?= number_format($gudangSummary['total_barcode']) ?> <span class="kpi-unit">Keranjang</span></div>
                <div class="kpi-sub">100% Ditimbang di Awal</div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="check-circle-2" style="width: 18px; height: 18px;"></i></div>
        </div>

        <!-- Card 4: Asal Pemasok & Produk -->
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Pemasok & Varian</div>
                <div class="kpi-value"><?= number_format($gudangSummary['total_pemasok']) ?> <span class="kpi-unit">Pemasok</span></div>
                <div class="kpi-sub">Terdiri dari <strong><?= number_format($gudangSummary['total_item']) ?></strong> jenis produk</div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="truck" style="width: 18px; height: 18px;"></i></div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <?php 
    $hasAnyFilter = !empty($filterGudangPemasok) || !empty($filterGudangJenis) || !empty($filterGudangItem) || 
                    !empty($filterGudangDate) || !empty($filterGudangStartDate) || 
                    !empty($filterGudangEndDate) || !empty($filterGudangSort) || !empty($filterGudangSearch);
    $activeFilterCount = 0;
    if (!empty($filterGudangPemasok)) $activeFilterCount++;
    if (!empty($filterGudangJenis)) $activeFilterCount++;
    if (!empty($filterGudangItem)) $activeFilterCount++;
    if (!empty($filterGudangDate) || !empty($filterGudangStartDate) || !empty($filterGudangEndDate)) $activeFilterCount++;
    if (!empty($filterGudangSort)) $activeFilterCount++;
    if (!empty($filterGudangSearch)) $activeFilterCount++;
    ?>

    <!-- ===================================================================== -->
    <!-- TOMBOL JUMBOTRON FILTER & RESET DATA GUDANG                            -->
    <!-- ===================================================================== -->
    <div class="no-print" style="display: flex; gap: 10px; align-items: center; justify-content: flex-end; flex-wrap: wrap; margin-bottom: 14px;">
        <!-- Sisi Kanan: Tombol Buka Jumbotron Filter & Reset -->
        <div style="display: flex; gap: 8px; align-items: center;">
            <button type="button" class="btn <?= $hasAnyFilter ? 'btn-primary' : 'btn-outline' ?>" onclick="toggleGudangFilterPanel()" id="btnToggleFilterGudang" style="font-weight: 700; font-size: 12.5px; padding: 7px 16px; border-radius: 20px; display: inline-flex; align-items: center; gap: 7px; box-shadow: var(--shadow-sm);">
                <i data-lucide="sliders-horizontal" style="width: 15px; height: 15px;"></i>
                <span>Filter Lanjutan</span>
                <?php if ($activeFilterCount > 0): ?>
                    <span class="badge" style="background: #ffffff; color: var(--primary); font-size: 11px; font-weight: 900; padding: 1px 7px; border-radius: 10px;"><?= $activeFilterCount ?></span>
                <?php endif; ?>
            </button>

            <?php if ($hasAnyFilter): ?>
                <a href="index.php?page=timbang_cetak&tab=stok_gudang<?= !empty($gudangSubTab) ? '&subtab=' . urlencode($gudangSubTab) : '' ?>" class="btn btn-outline" style="font-size: 12px; padding: 7px 14px; border-radius: 20px; display: inline-flex; align-items: center; gap: 5px;" title="Reset Semua Filter">
                    <i data-lucide="rotate-ccw" style="width: 13px; height: 13px;"></i>
                    <span>Reset</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- JUMBOTRON PANEL FILTER LENGKAP & DETAIL (COLLAPSIBLE / MODAL-LIKE)    -->
    <!-- ===================================================================== -->
    <div id="panelJumbotronFilterGudang" class="no-print" style="display: none; margin-bottom: 22px;">
        <div class="card" style="background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); border: 1.5px solid #cbd5e1; border-radius: var(--radius); padding: 22px 24px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1.5px solid var(--border);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="background: linear-gradient(135deg, #4f46e5, #6366f1); color: white; width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 8px rgba(79, 70, 229, 0.3);">
                        <i data-lucide="sliders" style="width: 18px; height: 18px;"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0; font-size: 14.5px; font-weight: 800; color: var(--text-main);">Panel Pengaturan Filter Data Gudang</h4>
                        <div style="font-size: 11.5px; color: var(--text-muted);">Pilih kriteria untuk menyaring sisa persediaan fisik secara akurat tanpa refresh halaman.</div>
                    </div>
                </div>
                <button type="button" onclick="toggleGudangFilterPanel(false)" class="btn btn-outline btn-sm" style="padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 8px;">
                    Tutup Panel
                </button>
            </div>

            <form method="GET" action="index.php" id="formFilterGudang">
                <input type="hidden" name="page" value="timbang_cetak">
                <input type="hidden" name="tab" value="stok_gudang">
                <input type="hidden" name="subtab" id="hiddenSubtab" value="<?= htmlspecialchars($gudangSubTab) ?>">

                <!-- Grid Baris 1: Dropdown Pilihan Kategori, Pemasok, Produk, Status Timbang, & Urutan -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 14px;">
                    <!-- 1. Pemasok Asal -->
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px; font-weight: 700;">Pemasok Asal:</label>
                        <select name="pemasok_id" class="form-control" style="font-size: 12.5px; font-weight: 600; height: 38px;">
                            <option value="">-- Semua Pemasok --</option>
                            <?php foreach ($pemasokList as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $filterGudangPemasok == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['kode_pemasok']) ?> - <?= htmlspecialchars($p['nama_pemasok']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Kategori / Jenis -->
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px; font-weight: 700;">Kategori / Jenis:</label>
                        <select name="jenis_id" id="filterGudangJenisSelect" class="form-control" onchange="syncItemFilter(this.value)" style="font-size: 12.5px; font-weight: 600; height: 38px;">
                            <option value="">-- Semua Kategori --</option>
                            <?php foreach ($jenisList as $j): ?>
                                <option value="<?= $j['id'] ?>" <?= $filterGudangJenis == $j['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($j['nama_jenis']) ?> (<?= htmlspecialchars($j['kode_jenis']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 3. Nama Barang / Produk Spesifik -->
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px; font-weight: 700;">Produk:</label>
                        <select name="item_id" id="filterGudangItemSelect" class="form-control" style="font-size: 12.5px; font-weight: 600; height: 38px;">
                            <option value="">-- Semua Produk --</option>
                            <?php foreach ($itemList as $itm): ?>
                                <option value="<?= $itm['id'] ?>" data-jenis="<?= $itm['jenis_id'] ?>" <?= $filterGudangItem == $itm['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($itm['nama_item']) ?> (<?= htmlspecialchars($itm['kode_item']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Urutan (Sorting) -->
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px; font-weight: 700;">Urutan Tampilan:</label>
                        <select name="sort" class="form-control" style="font-size: 12.5px; font-weight: 600; height: 38px;">
                            <option value="" <?= empty($filterGudangSort) ? 'selected' : '' ?>>Total Berat</option>
                            <option value="fifo" <?= $filterGudangSort === 'fifo' ? 'selected' : '' ?>>FIFO: Masuk Terlama</option>
                            <option value="lifo" <?= $filterGudangSort === 'lifo' ? 'selected' : '' ?>>🆕 Masuk Terbaru</option>
                            <option value="keranjang_desc" <?= $filterGudangSort === 'keranjang_desc' ? 'selected' : '' ?>>Keranjang Terbanyak</option>
                            <option value="nama_asc" <?= $filterGudangSort === 'nama_asc' ? 'selected' : '' ?>>Nama Produk</option>
                        </select>
                    </div>
                </div>

                <!-- Grid Baris 2: RENTANG TANGGAL MASUK + PENCARIAN & TOMBOL -->
                <div style="display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap;">
                    <!-- Blok Rentang Tanggal Range Langsung Terlihat & Interaktif -->
                    <div style="flex: 2; min-width: 320px;">
                        <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px; font-weight: 700; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 4px;">
                            <span><i data-lucide="calendar-range" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; color: #4f46e5;"></i> Rentang Tanggal Masuk:</span>
                            <!-- Dropdown Preset Tanggal Tunggal -->
                            <div style="display: flex; gap: 4px; align-items: center;">
                                <select onchange="setDateRangePreset(this.value); this.value='';" style="font-size: 11px; font-weight: 700; height: 24px; padding: 0 6px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--primary); cursor: pointer;" title="Pilih preset tanggal cepat">
                                    <option value="" disabled selected>Pilih Periode ▼</option>
                                    <option value="today">Hari Ini</option>
                                    <option value="yesterday">Kemarin</option>
                                    <option value="7days">7 Hari Terakhir</option>
                                    <option value="this_month">Bulan Ini</option>
                                    <option value="clear">Bersihkan Tanggal</option>
                                </select>
                            </div>
                        </label>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <div style="display: flex; align-items: center; gap: 6px; background: #ffffff; border: 1.5px solid var(--border); border-radius: var(--radius); padding: 0 10px; height: 38px; flex: 1;">
                                <span style="font-size: 11.5px; font-weight: 700; color: var(--text-muted); white-space: nowrap;">Mulai:</span>
                                <input type="date" name="start_date" id="gudangStartDate" value="<?= htmlspecialchars($filterGudangStartDate) ?>" style="border: none; background: transparent; font-size: 12px; font-weight: 600; color: var(--text-main); height: 100%; padding: 0; outline: none; cursor: pointer; flex: 1; min-width: 0;" title="Tanggal Mulai Masuk">
                            </div>
                            <span style="font-size: 12px; font-weight: 700; color: var(--text-muted);">s/d</span>
                            <div style="display: flex; align-items: center; gap: 6px; background: #ffffff; border: 1.5px solid var(--border); border-radius: var(--radius); padding: 0 10px; height: 38px; flex: 1;">
                                <span style="font-size: 11.5px; font-weight: 700; color: var(--text-muted); white-space: nowrap;">Sampai:</span>
                                <input type="date" name="end_date" id="gudangEndDate" value="<?= htmlspecialchars($filterGudangEndDate) ?>" style="border: none; background: transparent; font-size: 12px; font-weight: 600; color: var(--text-main); height: 100%; padding: 0; outline: none; cursor: pointer; flex: 1; min-width: 0;" title="Sampai Tanggal Masuk">
                            </div>
                        </div>
                    </div>

                    <!-- Input Pencarian -->
                    <div class="form-group" style="margin: 0; flex: 1.8; min-width: 220px;">
                        <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px; font-weight: 700;">Pencarian Barcode / No. SJ / Produk / Pemasok:</label>
                        <div style="position: relative;">
                            <input type="text" name="search_gudang" class="form-control" placeholder="Cari barcode / no surat jalan / nama ikan..." value="<?= htmlspecialchars($filterGudangSearch) ?>" style="font-size: 12.5px; height: 38px; padding-left: 34px;">
                            <i data-lucide="search" style="position: absolute; left: 10px; top: 11px; width: 15px; height: 15px; color: var(--text-muted);"></i>
                        </div>
                    </div>

                    <!-- Tombol Aksi Filter -->
                    <div style="display: flex; gap: 8px; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 20px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);">
                            <i data-lucide="filter" style="width: 14px; height: 14px;"></i>
                            <span>Terapkan Filter</span>
                        </button>
                        <?php if ($hasAnyFilter): ?>
                            <a href="index.php?page=timbang_cetak&tab=stok_gudang<?= !empty($gudangSubTab) ? '&subtab=' . urlencode($gudangSubTab) : '' ?>" class="btn btn-outline" style="height: 38px; padding: 0 14px; display: inline-flex; align-items: center; gap: 6px;" title="Reset Semua Filter">
                                <i data-lucide="rotate-ccw" style="width: 14px; height: 14px;"></i>
                                <span>Reset</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tag Filter Aktif Di Dalam Jumbotron -->
                <?php if ($hasAnyFilter): ?>
                    <div style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--border); display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: 11.5px;">
                        <span style="color: var(--text-muted); font-weight: 700;">Filter Sedang Aktif:</span>
                        <?php if ($filterGudangPemasok): ?>
                            <?php 
                            $pName = '';
                            foreach ($pemasokList as $p) { if ($p['id'] == $filterGudangPemasok) { $pName = $p['nama_pemasok']; break; } }
                            ?>
                            <span class="badge badge-secondary" style="font-size: 11px; padding: 3px 8px; display: inline-flex; align-items: center; gap: 4px;">
                                Pemasok: <?= htmlspecialchars($pName) ?>
                                <a href="<?= buildStockGudangUrl(['pemasok_id' => '']) ?>" style="color: inherit; text-decoration: none; font-weight: 900; margin-left: 2px;">✕</a>
                            </span>
                        <?php endif; ?>
                        <?php if ($filterGudangJenis): ?>
                            <?php 
                            $jName = '';
                            foreach ($jenisList as $j) { if ($j['id'] == $filterGudangJenis) { $jName = $j['nama_jenis']; break; } }
                            ?>
                            <span class="badge badge-secondary" style="font-size: 11px; padding: 3px 8px; display: inline-flex; align-items: center; gap: 4px;">
                                Kategori: <?= htmlspecialchars($jName) ?>
                                <a href="<?= buildStockGudangUrl(['jenis_id' => '']) ?>" style="color: inherit; text-decoration: none; font-weight: 900; margin-left: 2px;">✕</a>
                            </span>
                        <?php endif; ?>
                        <?php if ($filterGudangItem): ?>
                            <?php 
                            $iName = '';
                            foreach ($itemList as $itm) { if ($itm['id'] == $filterGudangItem) { $iName = $itm['nama_item']; break; } }
                            ?>
                            <span class="badge badge-secondary" style="font-size: 11px; padding: 3px 8px; display: inline-flex; align-items: center; gap: 4px;">
                                Produk: <?= htmlspecialchars($iName) ?>
                                <a href="<?= buildStockGudangUrl(['item_id' => '']) ?>" style="color: inherit; text-decoration: none; font-weight: 900; margin-left: 2px;">✕</a>
                            </span>
                        <?php endif; ?>

                        <?php if ($filterGudangDate): ?>
                            <?php
                            $dateLabel = [
                                'today' => 'Hari Ini',
                                'yesterday' => 'Kemarin',
                                'last_7_days' => '7 Hari Terakhir',
                                'older_3_days' => 'Mengendap > 3 Hari'
                            ][$filterGudangDate] ?? $filterGudangDate;
                            ?>
                            <span class="badge badge-secondary" style="font-size: 11px; padding: 3px 8px; display: inline-flex; align-items: center; gap: 4px;">
                                Waktu: <?= htmlspecialchars($dateLabel) ?>
                                <a href="<?= buildStockGudangUrl(['date_range' => '', 'start_date' => '', 'end_date' => '']) ?>" style="color: inherit; text-decoration: none; font-weight: 900; margin-left: 2px;">✕</a>
                            </span>
                        <?php elseif (!empty($filterGudangStartDate) || !empty($filterGudangEndDate)): ?>
                            <span class="badge badge-secondary" style="font-size: 11px; padding: 3px 8px; display: inline-flex; align-items: center; gap: 4px;">
                                Tgl: <?= htmlspecialchars($filterGudangStartDate ?: '...') ?> s/d <?= htmlspecialchars($filterGudangEndDate ?: '...') ?>
                                <a href="<?= buildStockGudangUrl(['start_date' => '', 'end_date' => '', 'date_range' => '']) ?>" style="color: inherit; text-decoration: none; font-weight: 900; margin-left: 2px;">✕</a>
                            </span>
                        <?php endif; ?>
                        <?php if ($filterGudangSort): ?>
                            <?php
                            $sortLabel = [
                                'fifo' => 'FIFO (Terlama)',
                                'lifo' => 'Terbaru',
                                'keranjang_desc' => 'Keranjang Terbanyak',
                                'keranjang_asc' => 'Keranjang Tersedikit',
                                'nama_asc' => 'Nama (A-Z)',
                                'nama_desc' => 'Nama (Z-A)',
                                'pemasok_asc' => 'Pemasok (A-Z)',
                                'pemasok_desc' => 'Pemasok (Z-A)',
                                'kategori_asc' => 'Kategori (A-Z)',
                                'kategori_desc' => 'Kategori (Z-A)',
                                'kg_desc' => 'Total Berat Terbanyak',
                                'kg_asc' => 'Total Berat Tersedikit',
                                'sj_desc' => 'Tgl Masuk Surat Jalan (Terbaru)',
                                'sj_asc' => 'Tgl Masuk Surat Jalan (Terlama)',
                                'tgl_desc' => 'Tanggal Masuk (Terbaru)',
                                'tgl_asc' => 'Tanggal Masuk (Terlama)',
                                'barcode_asc' => 'ID Barcode (Kecil-Besar)',
                                'barcode_desc' => 'ID Barcode (Besar-Kecil)',
                            ][$filterGudangSort] ?? $filterGudangSort;
                            ?>
                            <span class="badge badge-secondary" style="font-size: 11px; padding: 3px 8px; display: inline-flex; align-items: center; gap: 4px;">
                                Urutan: <?= htmlspecialchars($sortLabel) ?>
                                <a href="<?= buildStockGudangUrl(['sort' => '']) ?>" style="color: inherit; text-decoration: none; font-weight: 900; margin-left: 2px;">✕</a>
                            </span>
                        <?php endif; ?>
                        <?php if ($filterGudangSearch): ?>
                            <span class="badge badge-secondary" style="font-size: 11px; padding: 3px 8px; display: inline-flex; align-items: center; gap: 4px;">
                                Kata Kunci: "<?= htmlspecialchars($filterGudangSearch) ?>"
                                <a href="<?= buildStockGudangUrl(['search_gudang' => '']) ?>" style="color: inherit; text-decoration: none; font-weight: 900; margin-left: 2px;">✕</a>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- CARD UTAMA: DAFTAR SISA STOK DENGAN DUA TAMPILAN (TAB) -->
    <div class="card" style="box-shadow: var(--shadow-md); margin-bottom: 30px;">
        <!-- Header Card: Navigasi Antara Rekap Ringkas & Detail Stiker -->
        <div class="card-header no-print" style="border-bottom: 1.5px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 14px 18px;">
            <div style="display: flex; gap: 8px; align-items: center;">
                <button type="button" id="btnTabRekapGudang" class="btn btn-sm <?= $gudangSubTab === 'rekap' ? 'btn-primary' : 'btn-outline' ?>" onclick="switchGudangTab('rekap')" style="font-weight: 700; border-radius: 8px;">
                    <i data-lucide="layout-grid" style="width: 14px; height: 14px;"></i>
                    <span>Rekapitulasi per Produk & Pemasok (<?= count($gudangPerItem) ?>)</span>
                </button>
                <button type="button" id="btnTabDetailGudang" class="btn btn-sm <?= $gudangSubTab === 'detail' ? 'btn-primary' : 'btn-outline' ?>" onclick="switchGudangTab('detail')" style="font-weight: 700; border-radius: 8px;">
                    <i data-lucide="list" style="width: 14px; height: 14px;"></i>
                    <span>Rincian Stiker Barcode Satuan (<?= number_format($totalGudangLabels) ?>)</span>
                </button>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="exportGudangCsv()" style="font-weight: 600; padding: 6px 12px;">
                    <i data-lucide="file-spreadsheet" style="width: 14px; height: 14px; color: #10b981;"></i>
                    <span>Ekspor CSV</span>
                </button>
                <button type="button" class="btn btn-outline btn-sm" onclick="window.print()" style="font-weight: 600; padding: 6px 12px;">
                    <i data-lucide="printer" style="width: 14px; height: 14px; color: #6366f1;"></i>
                    <span>Cetak</span>
                </button>
            </div>
        </div>

        <!-- TAMPILAN 1: REKAPITULASI PRODUK & PEMASOK -->
        <div id="viewGudangRekap" style="display: <?= $gudangSubTab === 'rekap' ? 'block' : 'none' ?>;">
            <div class="table-responsive">
                <table class="table" id="tableGudangRekap" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                    <thead>
                        <tr style="background: var(--bg-muted);">
                            <th style="width: 50px; text-align: center; padding: 12px 10px; white-space: nowrap;">No</th>
                            <?= renderStockGudangTh('Pemasok Asal', 'pemasok_asc', 'pemasok_desc', $filterGudangSort, 'left', 'min-width: 140px; padding: 12px 14px;') ?>
                            <?= renderStockGudangTh('Kategori', 'kategori_asc', 'kategori_desc', $filterGudangSort, 'left', 'min-width: 110px; padding: 12px 14px;') ?>
                            <?= renderStockGudangTh('Produk', 'nama_asc', 'nama_desc', $filterGudangSort, 'left', 'min-width: 180px; padding: 12px 14px;') ?>
                            <?= renderStockGudangTh('Keranjang', 'keranjang_asc', 'keranjang_desc', $filterGudangSort, 'center', 'min-width: 110px; padding: 12px 14px;') ?>
                            <?= renderStockGudangTh('Total Berat (KG)', 'kg_asc', 'kg_desc', $filterGudangSort, 'right', 'min-width: 150px; padding: 12px 20px 12px 14px;') ?>
                            <th style="text-align: right; min-width: 120px; padding: 12px 14px; white-space: nowrap;">Rata-rata / Pcs</th>
                            <th style="text-align: center; min-width: 140px; padding: 12px 14px; white-space: nowrap;">Status Kesiapan</th>
                            <?= renderStockGudangTh('Surat Jalan Terakhir', 'sj_asc', 'sj_desc', $filterGudangSort, 'left', 'min-width: 180px; padding: 12px 14px;') ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($gudangPerItem)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <div style="font-weight: 700; font-size: 15px;">Tidak ada sisa stok barang di gudang utama.</div>
                                    <div style="font-size: 12.5px; margin-top: 4px;">Semua barang masuk telah berhasil dimutasikan ke mitra atau belum ada penerimaan baru.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($gudangPerItem as $idx => $row): ?>
                                <tr>
                                    <td style="text-align: center; color: var(--text-muted); font-size: 12px; padding: 12px 10px; white-space: nowrap;"><?= $idx + 1 ?></td>
                                    <td style="padding: 12px 14px;">
                                        <div style="font-weight: 700; color: var(--text-main); font-size: 13px; white-space: nowrap;">
                                            <?= htmlspecialchars($row['nama_pemasok']) ?>
                                        </div>
                                        <span class="badge badge-secondary" style="font-size: 10px; padding: 2px 6px; margin-top: 3px;">
                                            <?= htmlspecialchars($row['kode_pemasok']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px 14px; white-space: nowrap;">
                                        <span class="badge badge-purple" style="font-size: 11px;">
                                            <?= htmlspecialchars($row['nama_jenis']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px 14px;">
                                        <div style="font-weight: 700; color: var(--text-main); font-size: 13.5px; white-space: nowrap;">
                                            <?= htmlspecialchars($row['nama_item']) ?>
                                        </div>
                                        <div style="font-size: 11.5px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace; margin-top: 2px;">
                                            <?= htmlspecialchars($row['kode_item']) ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center; padding: 12px 14px; white-space: nowrap;">
                                        <strong style="font-size: 14px;"><?= number_format($row['total_keranjang']) ?></strong>
                                        <span style="font-size: 11.5px; color: var(--text-muted);"> pcs</span>
                                    </td>
                                    <td style="text-align: right; padding: 12px 20px 12px 14px; white-space: nowrap;">
                                        <strong style="font-size: 15px; color: #4f46e5;">
                                            <?= number_format((float)$row['total_kg'], 2, ',', '.') ?>
                                        </strong>
                                        <span style="font-size: 11.5px; color: var(--text-muted); font-weight: 600;"> KG</span>
                                    </td>
                                    <td style="text-align: right; color: var(--text-muted); font-size: 12.5px; padding: 12px 14px; white-space: nowrap;">
                                        <?= $row['avg_kg'] !== null ? number_format((float)$row['avg_kg'], 2, ',', '.') . ' kg' : '-' ?>
                                    </td>
                                    <td style="text-align: center; padding: 12px 14px; white-space: nowrap;">
                                        <span class="badge badge-success" style="font-size: 11px; padding: 4px 8px;" title="Seluruh keranjang sudah ditimbang saat penerimaan dan siap dialokasikan ke mitra">
                                            <?= (int)($row['total_keranjang'] ?? $row['total_pcs'] ?? 0) ?> Siap Alokasi
                                        </span>
                                    </td>
                                    <td style="padding: 12px 14px; white-space: nowrap;">
                                        <div style="font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 700; color: var(--primary); background: rgba(0,0,0,0.03); padding: 3px 6px; border-radius: 4px; border: 1px solid var(--border); display: inline-block;">
                                            <?= htmlspecialchars($row['latest_surat_jalan'] ?: '-') ?>
                                        </div>
                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">
                                            <?= !empty($row['latest_masuk']) ? date('d/m/Y H:i', strtotime($row['latest_masuk'])) : '' ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION REKAPITULASI PRODUK GUDANG UTAMA (SELALU TAMPIL) -->
            <div class="card-footer no-print" style="padding: 14px 18px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; background: var(--bg-card);">
                <div style="font-size: 12.5px; color: var(--text-muted);">
                    Menampilkan <strong><?= count($gudangPerItem) ?></strong> produk dalam rekapitulasi stok
                </div>
                <div style="display: flex; gap: 5px; align-items: center;">
                    <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">« Pertama</button>
                    <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">‹ Sebelumnya</button>
                    <button type="button" class="btn btn-primary btn-sm" style="padding: 4px 10px; font-weight: 800;">1</button>
                    <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">Selanjutnya ›</button>
                    <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">Terakhir »</button>
                </div>
            </div>
        </div>

        <!-- TAMPILAN 2: RINCIAN STIKER BARCODE SATUAN -->
        <div id="viewGudangDetail" style="display: <?= $gudangSubTab === 'detail' ? 'block' : 'none' ?>;">
            <div class="table-responsive">
                <table class="table" id="tableGudangDetail" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                    <thead>
                        <tr style="background: var(--bg-muted);">
                            <th style="width: 50px; text-align: center; padding: 12px 10px; white-space: nowrap;">No</th>
                            <?= renderStockGudangTh('ID Barcode', 'barcode_asc', 'barcode_desc', $filterGudangSort, 'left', 'min-width: 180px; padding: 12px 14px;') ?>
                            <?= renderStockGudangTh('Pemasok Asal', 'pemasok_asc', 'pemasok_desc', $filterGudangSort, 'left', 'min-width: 140px; padding: 12px 14px;') ?>
                            <?= renderStockGudangTh('Produk', 'nama_asc', 'nama_desc', $filterGudangSort, 'left', 'min-width: 180px; padding: 12px 14px;') ?>
                            <?= renderStockGudangTh('Berat Riil (KG)', 'kg_asc', 'kg_desc', $filterGudangSort, 'right', 'min-width: 160px; padding: 12px 20px 12px 14px;') ?>
                            <?= renderStockGudangTh('No. Surat Jalan', 'sj_asc', 'sj_desc', $filterGudangSort, 'left', 'min-width: 180px; padding: 12px 14px;') ?>
                            <?= renderStockGudangTh('Tanggal Masuk', 'tgl_asc', 'tgl_desc', $filterGudangSort, 'left', 'min-width: 140px; padding: 12px 14px;') ?>
                            <th style="text-align: center; min-width: 140px; padding: 12px 14px; white-space: nowrap;">Status Kesiapan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($gudangLabels)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <div style="font-weight: 700;">Tidak ada stiker barcode yang cocok dengan filter ini.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($gudangLabels as $idx => $lbl): ?>
                                <tr>
                                    <td style="text-align: center; color: var(--text-muted); font-size: 12px; padding: 12px 10px; white-space: nowrap;">
                                        <?= $idx + 1 + $gudangOffset ?>
                                    </td>
                                    <td style="padding: 12px 14px; white-space: nowrap;">
                                        <span style="font-family: 'JetBrains Mono', monospace; font-weight: 800; font-size: 13px; color: #4f46e5; letter-spacing: 0.5px;">
                                            <?= htmlspecialchars($lbl['id_label']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px 14px;">
                                        <div style="font-weight: 600; font-size: 12.5px; white-space: nowrap;">
                                            <?= htmlspecialchars($lbl['nama_pemasok']) ?>
                                        </div>
                                        <span class="badge badge-secondary" style="font-size: 10px; margin-top: 3px;">
                                            <?= htmlspecialchars($lbl['kode_pemasok']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px 14px;">
                                        <div style="font-weight: 700; font-size: 13px; white-space: nowrap;">
                                            <?= htmlspecialchars($lbl['nama_item']) ?>
                                        </div>
                                        <span class="badge badge-purple" style="font-size: 10px; margin-top: 3px;">
                                            <?= htmlspecialchars($lbl['nama_jenis']) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right; padding: 12px 20px 12px 14px; white-space: nowrap;">
                                        <strong style="color: #10b981; font-size: 14px;">
                                            <?= number_format((float)$lbl['berat_netto'], 2, ',', '.') ?>
                                        </strong>
                                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 600;"> KG</span>
                                    </td>
                                    <td style="padding: 12px 14px; white-space: nowrap;">
                                        <span style="font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 600; color: var(--text-main); background: rgba(0,0,0,0.03); padding: 3px 6px; border-radius: 4px; border: 1px solid var(--border);">
                                            <?= htmlspecialchars($lbl['no_surat_jalan'] ?: '-') ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 12px; color: var(--text-muted); padding: 12px 14px; white-space: nowrap;">
                                        <?= date('d/m/Y H:i', strtotime($lbl['created_at'])) ?>
                                    </td>
                                    <td style="text-align: center; padding: 12px 14px; white-space: nowrap;">
                                        <span class="badge badge-success" style="font-size: 11px; padding: 4px 8px;">
                                            Siap Alokasi
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION RINCIAN BARCODE GUDANG UTAMA (SELALU TAMPIL) -->
            <?php
            $pParams = $_GET;
            unset($pParams['p_gudang']);
            $baseGudangUrl = 'index.php?' . http_build_query($pParams);
            ?>
            <div class="card-footer no-print" style="padding: 14px 18px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; background: var(--bg-card);">
                <div style="font-size: 12.5px; color: var(--text-muted);">
                    Menampilkan halaman <strong><?= $gudangPage ?></strong> dari <strong><?= $totalGudangPages ?></strong> (Total <?= number_format($totalGudangLabels) ?> stiker barcode)
                </div>
                <div style="display: flex; gap: 5px; align-items: center;">
                    <?php if ($gudangPage > 1): ?>
                        <a href="<?= $baseGudangUrl ?>&p_gudang=1" class="btn btn-outline btn-sm" style="padding: 4px 10px;">« Pertama</a>
                        <a href="<?= $baseGudangUrl ?>&p_gudang=<?= $gudangPage - 1 ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">‹ Sebelumnya</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">« Pertama</button>
                        <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">‹ Sebelumnya</button>
                    <?php endif; ?>

                    <?php
                    $startP = max(1, $gudangPage - 2);
                    $endP = min($totalGudangPages, $gudangPage + 2);
                    for ($pi = $startP; $pi <= $endP; $pi++):
                    ?>
                        <a href="<?= $baseGudangUrl ?>&p_gudang=<?= $pi ?>" class="btn <?= $pi === $gudangPage ? 'btn-primary' : 'btn-outline' ?> btn-sm" style="padding: 4px 10px; font-weight: <?= $pi === $gudangPage ? '800' : '600' ?>;">
                            <?= $pi ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($gudangPage < $totalGudangPages): ?>
                        <a href="<?= $baseGudangUrl ?>&p_gudang=<?= $gudangPage + 1 ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">Selanjutnya ›</a>
                        <a href="<?= $baseGudangUrl ?>&p_gudang=<?= $totalGudangPages ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">Terakhir »</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">Selanjutnya ›</button>
                        <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">Terakhir »</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    </div> <!-- /#ajaxStokGudangContainer -->
</div> <!-- /#sectionStokGudang -->

<!-- ========================================================================= -->
<!-- CONTAINER KHUSUS CETAK THERMAL & SHEET BULK (PRINT-ONLY)                 -->
<!-- ========================================================================= -->
<div id="bulkPrintContainer" class="print-area"></div>

<!-- Modal Pratinjau Lembar Cetak PDF (A4 10 pcs / 5 pcs 1/2 lembar) -->
<div id="modalSheetPreview" class="sheet-preview-backdrop no-print" style="display: none;">
    <div class="sheet-preview-dialog">
        <div class="sheet-preview-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6; padding: 6px; border-radius: 8px;">
                    <i data-lucide="file-text" style="width: 20px; height: 20px;"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 16px; font-weight: 800;" id="sheetPreviewTitle">Pratinjau Lembar Cetak PDF</h3>
                    <div style="font-size: 12px; color: var(--text-muted);" id="sheetPreviewSubtitle">10 Pcs per Lembar A4 • 5 Pcs Proporsional ½ Lembar</div>
                </div>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <button type="button" class="btn btn-success btn-sm" onclick="printBulkContainer()" style="font-weight: 700;">
                    <i data-lucide="printer" style="width: 15px; height: 15px;"></i>
                    <span>Cetak ke PDF / Printer</span>
                </button>
                <button type="button" class="btn btn-outline btn-sm" onclick="closeSheetPreviewModal()" style="font-weight: 700;">
                    Tutup
                </button>
            </div>
        </div>
        <div class="sheet-preview-body" id="sheetPreviewBody">
            <!-- Dinamis diisi kloning lembar preview -->
        </div>
    </div>
</div>

<!-- Modal Pilihan Cetak Barcode Ulang (1 Pcs Jadi 1 Lembar 10 Pcs / Kelompok SJ) -->
<div class="sheet-preview-backdrop" id="modalReprintChoice" style="display: none; align-items: center; justify-content: center; z-index: 100000;">
    <div style="background: var(--bg-card, #ffffff); border-radius: 16px; width: 560px; max-width: 95vw; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); border: 1.5px solid var(--border); overflow: hidden; animation: popIn 0.2s ease;">
        <div style="padding: 16px 20px; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #ffffff; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 10px;">
                
                <div>
                    <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #ffffff;">Opsi Cetak Ulang Barcode</h3>
                    <div style="font-size: 12px; opacity: 0.9;">Pilih format cetak barcode yang diinginkan</div>
                </div>
            </div>
            <button type="button" onclick="closeReprintChoiceModal()" style="background: rgba(255,255,255,0.2); border: none; color: #ffffff; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; display: flex; align-items: center; justify-content: center;">✕</button>
        </div>
        <div style="padding: 20px;">
            <!-- Info Barcode Terpilih -->
            <div style="background: var(--bg-muted, #f8fafc); border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <span style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: var(--text-muted); letter-spacing: 0.5px;">Barcode Terpilih</span>
                    <span class="badge badge-purple" id="reprintTargetMitra" style="font-size: 11px;">PEMASOK</span>
                </div>
                <div style="font-family: 'JetBrains Mono', monospace; font-size: 15px; font-weight: 800; color: #4f46e5;" id="reprintTargetBarcode">-</div>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;" id="reprintTargetMeta">-</div>
            </div>

            <!-- Pilihan Menu Cetak -->
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <!-- Opsi 1: Cetak Barcode Ini dengan Pilihan Jumlah Lembar (Custom Qty) -->
                <div style="background: #eef2ff; border: 2px solid #6366f1; border-radius: 12px; padding: 14px 16px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <strong style="color: #4338ca; font-size: 14px;">Cetak Barcode Ini</strong>
                                </div>
                                <div style="font-size: 12px; color: #4b5563; margin-top: 3px;">
                                    Jumlah stiker yang dicetak:
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px; margin-left: auto;">
                            <button type="button" class="btn btn-outline" style="padding: 4px 10px; font-weight: 800; height: 36px;" onclick="stepReprintQty(-1)">−</button>
                            <input type="number" id="reprintQtyInput" min="1" max="100" value="10" style="width: 58px; height: 36px; text-align: center; font-weight: 800; font-size: 14px; border: 1.5px solid #6366f1; border-radius: 6px; background: #fff;" onchange="validateReprintQty(this)">
                            <button type="button" class="btn btn-outline" style="padding: 4px 10px; font-weight: 800; height: 36px;" onclick="stepReprintQty(1)">+</button>
                            <button type="button" onclick="executeReprintCustomQty()" class="btn btn-primary" style="padding: 6px 16px; height: 36px; font-weight: 700; background: #4f46e5; border-color: #4f46e5; display: flex; align-items: center; gap: 6px;">
                                <i data-lucide="printer" style="width: 15px; height: 15px;"></i>
                                <span>Cetak</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Opsi 2: Cetak Seluruh Barcode di Surat Jalan Ini -->
                <button type="button" onclick="executeReprintGroupSJ()" class="btn" style="display: flex; align-items: center; gap: 14px; padding: 14px 16px; text-align: left; background: #f0fdf4; border: 1.5px solid #22c55e; border-radius: 12px; cursor: pointer; transition: all 0.2s;">
                    <div style="flex: 1;">
                        <strong style="color: #15803d; font-size: 14px;">Cetak Seluruh Barcode di Surat Jalan Ini</strong>
                        <div style="font-size: 12px; color: #4b5563; margin-top: 3px;" id="reprintGroupDesc">
                            Mencetak seluruh stiker barcode pada kelompok surat jalan ini.
                        </div>
                    </div>
                </button>
            </div>
        </div>
        <div style="padding: 12px 20px; background: var(--bg-muted); border-top: 1px solid var(--border); display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-outline" onclick="closeReprintChoiceModal()" style="font-weight: 700; font-size: 13px;">Batal</button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL DAFTAR BARCODE SURAT JALAN (RIWAYAT & ARSIP PENERIMAAN)             -->
<!-- ========================================================================= -->
<div class="sheet-preview-backdrop no-print" id="modalSJBarcodeList" style="display: none; align-items: center; justify-content: center; z-index: 100000; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); padding: 18px;">
    <div style="background: var(--bg-card, #ffffff); border-radius: 12px; width: 100%; max-width: 840px; max-height: 88vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.04); border: 1px solid var(--border, #e2e8f0);">
        <!-- Header Modal -->
        <div style="padding: 16px 20px; background: #ffffff; border-bottom: 1px solid var(--border, #e2e8f0); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-size: 16px; font-weight: 800; color: var(--text-main, #0f172a); display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="package" style="width: 18px; height: 18px; color: var(--primary, #4f46e5);"></i>
                    <span>Daftar Box Surat Jalan</span>
                </div>
                <div style="font-size: 12.5px; color: var(--text-muted, #64748b); margin-top: 2px;">
                    No. Surat Jalan: <code id="modalSJNoText" style="font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--primary, #2563eb); background: #eff6ff; padding: 2px 8px; border-radius: 4px; border: 1px solid #bfdbfe;">-</code>
                </div>
            </div>
            <button type="button" onclick="closeSJBarcodeListModal()" style="background: transparent; border: 1px solid var(--border, #e2e8f0); color: #64748b; width: 32px; height: 32px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 700; transition: all 0.15s ease;" onmouseover="this.style.background='#f1f5f9'; this.style.color='#0f172a';" onmouseout="this.style.background='transparent'; this.style.color='#64748b';" title="Tutup">✕</button>
        </div>

        <!-- Meta Info Bar: Produk, Pemasok & Counter -->
        <div style="padding: 12px 20px; background: #f8fafc; border-bottom: 1px solid var(--border, #e2e8f0); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="font-size: 12.5px; color: var(--text-muted, #64748b);">
                <span>Pemasok:</span> <strong id="modalSJPemasokText" style="color: var(--text-main, #0f172a);">-</strong>
                <span style="color: #cbd5e1; margin: 0 8px;">•</span>
                <span>Produk:</span> <strong id="modalSJItemText" style="color: var(--text-main, #0f172a);">-</strong>
            </div>
        </div>

        <!-- Body Modal: Tabel Elegan Seluruh Box -->
        <div style="padding: 0; overflow-y: auto; flex: 1; max-height: 440px; background: #ffffff;">
            <table class="table-custom" style="margin: 0; border: none;">
                <thead style="position: sticky; top: 0; z-index: 5; background: #f8fafc;">
                    <tr>
                        <th style="width: 45px; text-align: center;">No</th>
                        <th>Kode Box / Barcode</th>
                        <th>Nama Produk</th>
                        <th style="text-align: right; width: 120px;">Netto (Kg)</th>
                        <th style="text-align: center; width: 90px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="modalSJTableBody">
                    <!-- Diisi via JS -->
                </tbody>
                <tfoot id="modalSJTableFoot" style="display: none;">
                    <tr style="background: #f8fafc;">
                        <td colspan="3" style="text-align: right; font-weight: 700; color: var(--text-muted, #64748b); font-size: 12.5px;">Total <span id="modalSJTotalCount">0</span> box</td>
                        <td id="modalSJTotalNetto" style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; font-size: 13px;">0.00 kg</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Footer Modal -->
        <div style="padding: 14px 20px; background: #f8fafc; border-top: 1px solid var(--border, #e2e8f0); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <button type="button" class="btn btn-primary" id="btnModalPrintAllBarcodes" onclick="printAllModalBarcodes()" style="font-size: 12.5px; font-weight: 700; height: 38px; padding: 0 16px; display: inline-flex; align-items: center; gap: 6px; border-radius: 6px;">
                    <i data-lucide="tag" style="width: 14px; height: 14px;"></i>
                    <span>Cetak Semua Barcode</span>
                </button>
                <a id="btnModalCetakSuratJalan" href="#" target="_blank" class="btn btn-outline" style="font-size: 12.5px; font-weight: 700; height: 38px; padding: 0 16px; display: inline-flex; align-items: center; gap: 6px; border-radius: 6px; color: #047857; border-color: #a7f3d0; background: #ecfdf5;">
                    <i data-lucide="file-text" style="width: 14px; height: 14px;"></i>
                    <span>Cetak Surat Jalan</span>
                </a>
            </div>
            <button type="button" class="btn btn-outline" onclick="closeSJBarcodeListModal()" style="font-size: 12.5px; font-weight: 600; height: 38px; padding: 0 18px; border-radius: 6px;">Tutup</button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- SUB-MENU 4: DAFTAR SELURUH PENERIMAAN BARANG (TAB DEDIKASI DARI SIDEBAR)   -->
<!-- ========================================================================= -->
<div id="sectionRiwayatPenerimaan" style="<?= $activeTab === 'riwayat_penerimaan' ? 'display: block;' : 'display: none;' ?>">
<div class="card no-print" id="detailsRiwayatPenerimaan" style="margin-top: 10px; border: 1.5px solid var(--border);">
    <div style="padding: 18px 22px; font-weight: 700; display: flex; align-items: center; justify-content: space-between; user-select: none; background: var(--bg-card); border-bottom: 1px solid var(--border);">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="background: rgba(79, 70, 229, 0.1); width: 38px; height: 38px; border-radius: 9px; display: flex; align-items: center; justify-content: center;">
                <i data-lucide="archive" style="width: 22px; height: 22px; color: var(--primary);"></i>
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 16px; font-weight: 800; color: var(--text-main);">Daftar Seluruh Penerimaan Barang Masuk</span>
                    <span class="badge badge-purple"><?= number_format($totalPenerimaan) ?> Data</span>
                </div>
                <div style="font-size: 12px; color: var(--text-muted); font-weight: 500; margin-top: 2px;">
                    Daftar lengkap label barcode kemasan, surat jalan, bobot timbangan riil, dan status fisik
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <?php $eksporTabel = 'allPenerimaanTable'; $eksporNama = 'Daftar_Penerimaan_Barang'; include __DIR__ . '/layout/ekspor.php'; ?>
        </div>
    </div>

    <!-- Filter Bar Dinamis: Filter & Cari Berdasarkan No. Surat Jalan, Waktu, Pemasok, dan Nama Produk -->
    <div style="background: var(--bg-muted); padding: 16px; border-bottom: 1.5px solid var(--border);">
        <form method="GET" action="index.php#detailsRiwayatPenerimaan" id="formFilterRiwayat" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="page" value="timbang_cetak">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">

            <!-- 1. Pencarian No. Surat Jalan, Barcode, Produk -->
            <div style="flex: 2; min-width: 200px;">
                <div style="position: relative;">
                    <input type="text" name="search" class="form-control" placeholder="Cari No. Surat Jalan, Barcode, Produk, Pemasok..." value="<?= htmlspecialchars($searchQuery) ?>" style="padding-left: 36px; height: 38px; font-size: 13px;">
                    <i data-lucide="search" style="position: absolute; left: 12px; top: 11px; width: 15px; height: 15px; color: var(--text-muted);"></i>
                </div>
            </div>

            <!-- 2. Filter Tanggal Mulai Dari -->
            <div style="display: flex; align-items: center; gap: 6px; background: #ffffff; border: 1.5px solid var(--border); border-radius: var(--radius); padding: 0 10px; height: 38px; min-width: 145px; flex: 1;">
                <span style="font-size: 11.5px; font-weight: 700; color: var(--text-muted); white-space: nowrap;">Mulai:</span>
                <input type="date" name="filter_start_date" value="<?= htmlspecialchars($filterStartDate) ?>" style="border: none; background: transparent; font-size: 12px; font-weight: 600; color: var(--text-main); height: 100%; padding: 0; outline: none; cursor: pointer; flex: 1; min-width: 0;" title="Tanggal Mulai Dari">
            </div>

            <!-- 3. Filter Sampai Dengan Tanggal -->
            <div style="display: flex; align-items: center; gap: 6px; background: #ffffff; border: 1.5px solid var(--border); border-radius: var(--radius); padding: 0 10px; height: 38px; min-width: 145px; flex: 1;">
                <span style="font-size: 11.5px; font-weight: 700; color: var(--text-muted); white-space: nowrap;">Sampai:</span>
                <input type="date" name="filter_end_date" value="<?= htmlspecialchars($filterEndDate) ?>" style="border: none; background: transparent; font-size: 12px; font-weight: 600; color: var(--text-main); height: 100%; padding: 0; outline: none; cursor: pointer; flex: 1; min-width: 0;" title="Sampai Dengan Tanggal">
            </div>

            <!-- 3. Filter Pemasok -->
            <div style="flex: 1.3; min-width: 150px;">
                <select name="filter_pemasok" class="form-control" style="height: 38px; font-size: 12.5px; font-weight: 600;">
                    <option value="">-- Semua Pemasok --</option>
                    <?php foreach ($pemasokList as $pms): ?>
                        <option value="<?= $pms['id'] ?>" <?= $filterPemasok == $pms['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pms['kode_pemasok']) ?> - <?= htmlspecialchars($pms['nama_pemasok']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 4. Filter Nama Produk -->
            <div style="flex: 1.3; min-width: 150px;">
                <select name="filter_item" class="form-control" style="height: 38px; font-size: 12.5px; font-weight: 600;">
                    <option value="">-- Semua Produk --</option>
                    <?php foreach ($itemsList as $it): ?>
                        <option value="<?= $it['id'] ?>" <?= $filterItem == $it['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($it['nama_item']) ?> (<?= htmlspecialchars($it['nama_jenis']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>


            <!-- Action Buttons Filter -->
            <div style="display: flex; gap: 6px;">
                <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 16px; font-weight: 700; font-size: 12.5px; display: inline-flex; align-items: center; gap: 5px;">
                    <i data-lucide="filter" style="width: 14px; height: 14px;"></i>
                    <span>Filter</span>
                </button>
                <a href="index.php?page=timbang_cetak&tab=<?= htmlspecialchars($activeTab) ?>#detailsRiwayatPenerimaan" class="btn btn-outline" style="height: 38px; padding: 0 12px; font-size: 12.5px; display: inline-flex; align-items: center;" title="Reset Semua Filter">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Switch Mode Tampilan: Kelompok Batch (10 Pcs/Lembar) vs Barcode Rinci -->
    <div style="display: flex; gap: 8px; padding: 12px 16px; background: var(--bg-card); border-bottom: 1.5px solid var(--border); align-items: center; justify-content: space-between; flex-wrap: wrap;">
        <div style="display: flex; gap: 8px; align-items: center;">
            <span style="font-size: 13px; font-weight: 700; color: var(--text-muted); margin-right: 4px;">Tampilan:</span>
            <button type="button" class="btn btn-primary btn-sm" id="btnViewModeGroup" onclick="switchTableViewMode('group')" style="font-weight: 700; border-radius: 8px;">
                <i data-lucide="package" style="width: 14px; height: 14px;"></i>
                <span>Per Surat Jalan</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" id="btnViewModeDetail" onclick="switchTableViewMode('detail')" style="font-weight: 700; border-radius: 8px;">
                <i data-lucide="list" style="width: 14px; height: 14px;"></i>
                <span>Rincian Satuan</span>
            </button>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TABEL 1: MODE KELOMPOK BATCH SURAT JALAN (10 PCS / LEMBAR A4)            -->
    <!-- ========================================================================= -->
    <div class="table-responsive" id="containerViewGrouped">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>No. Surat Jalan</th>
                    <th>Waktu</th>
                    <th>Pemasok</th>
                    <th>Nama Produk</th>
                    <th style="text-align: center;">Jumlah Barcode</th>
                    <th style="text-align: right;">Netto</th>
                    <th>Rentang ID Barcode</th>
                    <th style="text-align: center; width: 210px;" class="no-print">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($groupedPenerimaan)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 36px; color: var(--text-muted);">
                            <i data-lucide="inbox" style="width: 40px; height: 40px; margin: 0 auto 8px auto; opacity: 0.3;"></i>
                            <div style="font-weight: 700; font-size: 15px;">Tidak ada kelompok barcode yang cocok dengan filter</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($groupedPenerimaan as $grpIdx => $grp): 
                        $safeId = 'grp_' . $grpIdx;
                        $noSj = $grp['no_surat_jalan'];
                        $totalPcs = (int)$grp['total_pcs'];
                        $pmsKode = $grp['kode_pemasok'] ?? ($grp['kode_mitra'] ?? 'PMS');
                        $pmsNama = $grp['nama_pemasok'] ?? ($grp['nama_mitra'] ?? 'Pemasok');
                    ?>
                    <?php $sjBatal = (int)($grp['jml_void'] ?? 0) > 0 && (int)($grp['jml_void'] ?? 0) === (int)($grp['total_pcs'] ?? 0); ?>
                    <tr style="border-bottom: 1px solid var(--border);<?= $sjBatal ? ' opacity: 0.65;' : '' ?>">
                        <td>
                            <a href="javascript:void(0)" onclick="openSJBarcodeHistoryModal('<?= htmlspecialchars(addslashes($noSj)) ?>')" style="font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 800; color: #2563eb; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; border-bottom: 1.5px dashed #93c5fd; padding-bottom: 1px; cursor: pointer;" title="Klik untuk melihat rincian barcode Surat Jalan ini">
                                <i data-lucide="file-text" style="width: 14px; height: 14px; color: #3b82f6;"></i>
                                <span><?= htmlspecialchars($noSj) ?></span>
                            </a>
                            <?php if ($sjBatal): ?>
                                <div><span class="badge badge-danger" style="font-size: 10.5px; margin-top: 3px;">Dibatalkan</span></div>
                                <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 2px;"><?= htmlspecialchars(preg_replace('/^Penerimaan dibatalkan: /', '', (string)($grp['alasan_void'] ?? ''))) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="white-space: nowrap;">
                            <div style="font-size: 12px; font-weight: 700; color: #334155;"><?= date('d M Y', strtotime($grp['created_at'])) ?></div>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 1px;"><?= date('H:i', strtotime($grp['created_at'])) ?> WIB</div>
                        </td>
                        <td>
                            <span class="badge" style="background: #0284c7; color: #fff; padding: 2px 7px; font-size: 11px; font-weight: 700; border-radius: 4px;">
                                <?= htmlspecialchars($pmsKode) ?>
                            </span>
                            <span style="font-size: 12px; font-weight: 600; margin-left: 4px;"><?= htmlspecialchars($pmsNama) ?></span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($grp['nama_item']) ?></strong>
                            <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($grp['nama_jenis']) ?></div>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge badge-purple" style="font-size: 12px; font-weight: 800; padding: 4px 10px;">
                                <?= $totalPcs ?> Pcs
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <strong style="color: #10b981; font-size: 13.5px; font-weight: 800; font-family: 'JetBrains Mono', monospace;"><?= Helper::formatKg($grp['total_berat']) ?></strong>
                        </td>
                        <td>
                            <code style="font-size: 11px;"><?= htmlspecialchars($grp['min_barcode']) ?></code>
                            <span style="color: var(--text-muted); font-size: 11px;"> s/d </span><br>
                            <code style="font-size: 11px;"><?= htmlspecialchars($grp['max_barcode']) ?></code>
                        </td>
                        <td style="text-align: center;" class="no-print">
                            <button type="button" class="btn btn-outline btn-sm" onclick="openSJBarcodeHistoryModal('<?= htmlspecialchars(addslashes($noSj)) ?>')" style="font-weight: 700; font-size: 11.5px; padding: 5px 14px; display: inline-flex; align-items: center; gap: 5px; color: #4f46e5; border-color: #c7d2fe; background: #eef2ff; border-radius: 6px; cursor: pointer; transition: all 0.2s;" title="Lihat Rincian Barcode Surat Jalan <?= htmlspecialchars($noSj) ?>">
                                <i data-lucide="eye" style="width: 14px; height: 14px;"></i>
                                <span>Detail</span>
                            </button>
                            <?php if (!$sjBatal): ?>
                            <button type="button" class="btn btn-outline btn-sm" onclick="batalDokumen('batal_penerimaan', { no_surat_jalan: '<?= htmlspecialchars(addslashes($noSj), ENT_QUOTES) ?>' }, 'penerimaan <?= htmlspecialchars(addslashes($noSj), ENT_QUOTES) ?>')" style="padding: 5px 9px; font-size: 12px; color: #dc2626; border-color: rgba(220,38,38,0.35);" title="Batalkan dokumen"><i data-lucide="ban" style="width: 14px; height: 14px;"></i><span>Batalkan</span></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ========================================================================= -->
    <!-- TABEL 2: MODE RINCIAN PER 1 PCS BARCODE                                  -->
    <!-- ========================================================================= -->
    <div class="table-responsive" id="containerViewDetail" style="display: none;">
        <table class="table-custom" id="allPenerimaanTable">
            <thead>
                <tr>
                    <th style="width: 155px;">ID Barcode</th>
                    <th>Pemasok</th>
                    <th>Nama Produk</th>
                    <th style="text-align: right;">Netto (Kg)</th>
                    <th>No. Surat Jalan</th>
                    <th>Waktu Penerimaan</th>
                    <th style="text-align: center; width: 100px;" class="no-print">Aksi</th>
                </tr>
            </thead>
            <tbody id="allPenerimaanTableBody">
                <?php if (empty($penerimaanList)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 36px; color: var(--text-muted);">
                            <i data-lucide="inbox" style="width: 40px; height: 40px; margin: 0 auto 8px auto; opacity: 0.3;"></i>
                            <div style="font-weight: 700; font-size: 15px;">Tidak ada data penerimaan yang cocok dengan filter</div>
                            <p style="font-size: 13px; margin-top: 4px;">Coba atur ulang filter pencarian atau buat barcode baru.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($penerimaanList as $lbl): 
                        $netto = (float)$lbl['berat_netto'];
                        $hPerKg = (float)($lbl['harga_per_kg'] ?? 0);
                        $totVal = (float)($lbl['total_nilai'] ?? 0);
                        $noSj = !empty($lbl['no_surat_jalan']) ? $lbl['no_surat_jalan'] : ($lbl['batch_no'] ?? '-');
                        $isWeighed = $netto > 0;
                        $lblPmsKode = $lbl['kode_pemasok'] ?? ($lbl['kode_mitra'] ?? 'PMS');
                        $lblPmsNama = $lbl['nama_pemasok'] ?? ($lbl['nama_mitra'] ?? 'Pemasok');
                    ?>
                    <tr>
                        <td>
                            <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--primary);">
                                <?= htmlspecialchars($lbl['id_label']) ?>
                            </strong>
                        </td>
                        <td>
                            <span class="badge" style="background: #0284c7; color: #fff; padding: 2px 7px; font-size: 11px; font-weight: 700; border-radius: 4px;">
                                <?= htmlspecialchars($lblPmsKode) ?>
                            </span>
                            <span style="font-size: 12px; font-weight: 600; margin-left: 4px;"><?= htmlspecialchars($lblPmsNama) ?></span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($lbl['nama_item']) ?></strong>
                            <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($lbl['nama_jenis'] ?? '') ?></div>
                        </td>
                        <td style="text-align: right;">
                            <strong style="color: #10b981; font-size: 14px; font-weight: 900;"><?= Helper::formatKg($netto) ?></strong>
                        </td>
                        <td>
                            <code style="font-size: 11.5px; font-weight: 700; color: var(--primary);"><?= htmlspecialchars($noSj) ?></code>
                        </td>
                        <td style="font-size: 12px; color: var(--text-muted);"><?= Helper::formatDateTime($lbl['created_at']) ?></td>
                        <td style="text-align: center;" class="no-print">
                            <button type="button" class="btn btn-outline btn-sm" onclick="reprintBarcodeDirect('<?= htmlspecialchars($lbl['id_label']) ?>')" style="padding: 3px 10px; font-size: 11.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; border-radius: 6px; cursor: pointer;" title="Cetak Barcode Box (Format Stiker 70x50 mm)">
                                <i data-lucide="printer" style="width: 12px; height: 12px;"></i>
                                <span>Cetak</span>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls Komprehensif (SELALU TAMPIL) -->
    <?php 
        $baseUrl = "index.php?page=timbang_cetak&tab=" . urlencode($activeTab);
        if ($filterPemasok > 0) $baseUrl .= "&filter_pemasok=" . $filterPemasok;
        if ($filterItem > 0) $baseUrl .= "&filter_item=" . $filterItem;
        if (!empty($filterStartDate)) $baseUrl .= "&filter_start_date=" . urlencode($filterStartDate);
        if (!empty($filterEndDate)) $baseUrl .= "&filter_end_date=" . urlencode($filterEndDate);
        if (!empty($filterWeighed)) $baseUrl .= "&filter_weighed=" . urlencode($filterWeighed);
        if (!empty($searchQuery)) $baseUrl .= "&search=" . urlencode($searchQuery);
    ?>
    <div style="padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); flex-wrap: wrap; gap: 12px;">
        <div style="font-size: 13px; color: var(--text-muted);">
            Menampilkan <strong><?= $offset + 1 ?></strong> - <strong><?= min($offset + $perPage, $totalPenerimaan) ?></strong> dari <strong><?= number_format($totalPenerimaan) ?></strong> total penerimaan
        </div>
        <div style="display: flex; gap: 5px; align-items: center;">
            <?php if ($pageNumber > 1): ?>
                <a href="<?= $baseUrl ?>&p=1" class="btn btn-outline btn-sm" style="padding: 4px 10px;">« Pertama</a>
                <a href="<?= $baseUrl ?>&p=<?= $pageNumber - 1 ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">‹ Sebelumnya</a>
            <?php else: ?>
                <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">« Pertama</button>
                <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">‹ Sebelumnya</button>
            <?php endif; ?>

            <?php
            $startPage = max(1, $pageNumber - 2);
            $endPage = min($totalPages, $pageNumber + 2);
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
                <a href="<?= $baseUrl ?>&p=<?= $i ?>" class="btn <?= $i === $pageNumber ? 'btn-primary' : 'btn-outline' ?> btn-sm" style="padding: 4px 10px; font-weight: <?= $i === $pageNumber ? '800' : '600' ?>;">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($pageNumber < $totalPages): ?>
                <a href="<?= $baseUrl ?>&p=<?= $pageNumber + 1 ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">Selanjutnya ›</a>
                <a href="<?= $baseUrl ?>&p=<?= $totalPages ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">Terakhir »</a>
            <?php else: ?>
                <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">Selanjutnya ›</button>
                <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">Terakhir »</button>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<!-- ========================================================================= -->
<!-- MODAL POPUP: PERINGATAN BARCODE SUDAH DIGUNAKAN (ANTI-DUPLIKAT DATA)      -->
<!-- ========================================================================= -->
<div id="modalBarcodeAlreadyUsed" style="display: none; position: fixed; inset: 0; z-index: 99999; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(5px); align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--card-bg, #ffffff); border-radius: 16px; max-width: 520px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45); border: 2px solid #ef4444; overflow: hidden; animation: popIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
        <!-- Header Banner Merah Menyala -->
        <div style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 18px 22px; color: #ffffff; display: flex; align-items: center; gap: 14px;">
            <div style="background: rgba(255, 255, 255, 0.2); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i data-lucide="shield-alert" style="width: 26px; height: 26px; color: #ffffff;"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 18px; font-weight: 800; letter-spacing: 0.3px; color: #ffffff;">BARCODE SUDAH DIGUNAKAN!</h3>
                <p style="margin: 3px 0 0 0; font-size: 12.5px; opacity: 0.95; color: #ffffff;">Tidak dapat discan kembali untuk menghindari duplikasi data</p>
            </div>
        </div>

        <!-- Body Modal Informasi -->
        <div style="padding: 22px;">
            <div style="background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px;">
                <div style="font-size: 13.5px; color: #991b1b; line-height: 1.5; font-weight: 600;" id="usedModalWarningText">
                    Barcode ini <strong>sudah pernah discan dan ditimbang</strong>. Sistem menolak penimbangan ulang agar tidak terjadi pencatatan ganda pada stok fisik gudang.
                </div>
            </div>

            <!-- Rincian Data Barcode yang Ditolak -->
            <div style="background: var(--bg-muted, #f8fafc); border: 1px solid var(--border, #e2e8f0); border-radius: 10px; padding: 14px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px dashed var(--border, #e2e8f0); margin-bottom: 8px;">
                    <span style="color: var(--text-muted, #64748b);">ID Barcode:</span>
                    <strong id="usedBarcodeId" style="font-family: 'JetBrains Mono', monospace; font-size: 14px; color: #dc2626;">-</strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px dashed var(--border, #e2e8f0); margin-bottom: 8px;">
                    <span style="color: var(--text-muted, #64748b);">Nama Komoditas:</span>
                    <strong id="usedBarcodeItem" style="color: var(--text-main, #1e293b);">-</strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px dashed var(--border, #e2e8f0); margin-bottom: 8px;">
                    <span style="color: var(--text-muted, #64748b);">Pemasok / Mitra:</span>
                    <span id="usedBarcodeMitra" style="font-weight: 700; color: var(--text-main, #1e293b);">-</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px dashed var(--border, #e2e8f0); margin-bottom: 8px;">
                    <span style="color: var(--text-muted, #64748b);">No. Surat Jalan:</span>
                    <strong id="usedBarcodeSJ" style="color: var(--primary, #4f46e5);">-</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 4px;">
                    <span style="color: var(--text-muted, #64748b); font-weight: 700;">Bobot Tercatat:</span>
                    <span class="badge badge-success" id="usedBarcodeWeight" style="font-size: 13.5px; font-weight: 900; padding: 4px 10px;">0.00 KG</span>
                </div>
            </div>

            <!-- Tombol Tutup / Kembali -->
            <div style="margin-top: 20px;">
                <button type="button" id="btnCloseUsedModal" class="btn btn-danger" style="width: 100%; height: 46px; font-size: 14.5px; font-weight: 800; justify-content: center; border-radius: 10px; background: #dc2626; border-color: #dc2626; cursor: pointer;">
                    <span>Tutup & Scan Barcode Lain</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT LOGIC & HANDLERS                                               -->
<!-- ========================================================================= -->
<script>
    const pricesLookup = <?= $allPricesJson ?>;
    const masterItemsList = <?= json_encode($itemList) ?>;

    // =========================================================================
    // MODUL PENERIMAAN & TIMBANG SATU PINTU (ONE-STOP WEIGH & PRINT)
    // =========================================================================

    // Audio chime player
    function playBeepSound() {
        try {
            if (window.scannerAudio && typeof window.scannerAudio.playSuccessBeep === 'function') {
                window.scannerAudio.playSuccessBeep();
                return;
            }
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.25);
        } catch (e) {}
    }

    // Ubah Nomor Surat Jalan saat Pemasok Berubah
    async function onOneStopPemasokChanged() {
        const selPemasok = document.getElementById('onestop_pemasok_id');
        const inpSj = document.getElementById('onestop_no_sj');
        if (!selPemasok || !inpSj) return;

        const opt = selPemasok.options[selPemasok.selectedIndex];
        const kode = opt ? (opt.dataset.kode || 'PMS01') : 'PMS01';

        try {
            const resp = await fetch('index.php?action=gen_surat_jalan&kode=' + encodeURIComponent(kode));
            const data = await resp.json();
            if (data && data.success && data.no_surat_jalan) {
                inpSj.value = data.no_surat_jalan;
            }
        } catch (e) {
            const d = new Date();
            const yy = String(d.getFullYear()).slice(-2);
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            const rnd = Math.floor(1000 + Math.random() * 9000);
            inpSj.value = `SJ-${kode}-${yy}${mm}${dd}-${rnd}`;
        }

        onOneStopSjChanged();
    }

    function onOneStopSjChanged() {
        const inpSj = document.getElementById('onestop_no_sj');
        const noSj = inpSj ? inpSj.value.trim() : '';
        const subtitle = document.getElementById('summarySjSubtitle');
        if (subtitle) subtitle.innerText = 'No. SJ: ' + (noSj || '-');

        const btnBAST = document.getElementById('btnCetakBASTPenerimaan');
        if (btnBAST) {
            btnBAST.href = 'index.php?page=surat_jalan_penerimaan_print&no_surat_jalan=' + encodeURIComponent(noSj);
        }

        fetchInboundSessionSummary();
    }

    // Filter Produk Berdasarkan Kategori
    function onOneStopJenisChanged() {
        const filterJenis = document.getElementById('onestop_jenis_filter')?.value;
        const selItem = document.getElementById('onestop_item_id');
        if (!selItem) return;

        if (!filterJenis) {
            // Jika kategori belum dipilih, produk tidak bisa dipilih (disabled)
            selItem.innerHTML = '<option value="">-- Pilih Kategori Dahulu --</option>';
            selItem.disabled = true;
            selItem.style.cursor = 'not-allowed';
            return;
        }

        // Aktifkan pilihan produk sesuai kategori yang dipilih
        selItem.disabled = false;
        selItem.style.cursor = 'pointer';
        selItem.innerHTML = '<option value="">-- Pilih Produk --</option>';

        const filtered = masterItemsList.filter(it => String(it.jenis_id) === String(filterJenis));

        filtered.forEach(it => {
            const opt = document.createElement('option');
            opt.value = it.id;
            opt.setAttribute('data-jenis', it.jenis_id);
            opt.setAttribute('data-nama', it.nama_item);
            opt.setAttribute('data-kode', it.kode_item || '');
            opt.textContent = `${it.nama_item} (${it.kode_item || '-'})`;
            selItem.appendChild(opt);
        });

        if (filtered.length > 0) {
            selItem.selectedIndex = 1;
            onOneStopProductChanged(selItem.value);
        } else {
            selItem.innerHTML = '<option value="">-- Tidak ada produk di kategori ini --</option>';
        }
    }

    function onOneStopProductChanged(itemId) {
        // Autofocus ke input timbangan
        const inpNetto = document.getElementById('onestop_berat_netto');
        if (inpNetto) {
            inpNetto.focus();
            inpNetto.select();
        }
    }

    // Ambil Data dari Timbangan Serial (scale.js)
    async function readFromConnectedScale() {
        const badge = document.getElementById('onestop_scale_status');
        if (window.weighScale && typeof window.weighScale.connectSerial === 'function') {
            try {
                if (badge) badge.innerText = '● Menghubungkan Timbangan...';
                const ok = await window.weighScale.connectSerial(9600);
                if (ok && badge) {
                    badge.innerText = '● Timbangan Serial Aktif';
                    badge.className = 'badge badge-success';
                }
            } catch (e) {
                if (badge) badge.innerText = '● Input Manual';
                alert('Timbangan serial belum terhubung. Silakan input bobot secara manual.');
            }
        } else {
            alert('Fitur Web Serial API tidak didukung pada browser ini atau timbangan belum dicolok.');
        }
    }

    // Simpan Box & Cetak Barcode Sekaligus
    async function handleSaveWeighedBox() {
        const pemasokId = document.getElementById('onestop_pemasok_id')?.value;
        const noSj = document.getElementById('onestop_no_sj')?.value.trim();
        const itemId = document.getElementById('onestop_item_id')?.value;
        const inpNetto = document.getElementById('onestop_berat_netto');
        const beratNetto = parseFloat(inpNetto?.value || 0);

        if (!pemasokId) {
            alert('Pemasok wajib dipilih.');
            return;
        }
        if (!noSj) {
            alert('Nomor Surat Jalan wajib diisi.');
            document.getElementById('onestop_no_sj')?.focus();
            return;
        }
        const jenisId = document.getElementById('onestop_jenis_filter')?.value;
        if (!jenisId) {
            alert('Silakan pilih Kategori terlebih dahulu.');
            document.getElementById('onestop_jenis_filter')?.focus();
            return;
        }
        if (!itemId) {
            alert('Silakan pilih Produk terlebih dahulu.');
            document.getElementById('onestop_item_id')?.focus();
            return;
        }
        if (isNaN(beratNetto) || beratNetto <= 0) {
            alert('Bobot timbangan harus lebih besar dari 0 KG.');
            if (inpNetto) { inpNetto.focus(); inpNetto.select(); }
            return;
        }

        const btnSave = document.getElementById('btnSaveWeighedBox');
        const labelBtn = document.getElementById('labelBtnSaveWeighed');
        if (btnSave) btnSave.disabled = true;
        if (labelBtn) labelBtn.innerText = 'Menyimpan...';

        try {
            const fd = new FormData();
            fd.append('pemasok_id', pemasokId);
            fd.append('no_surat_jalan', noSj);
            fd.append('item_id', itemId);
            fd.append('berat_netto', beratNetto.toFixed(2));

            const resp = await fetch('index.php?action=save_weighed_box', {
                method: 'POST',
                body: fd
            });
            const data = await resp.json();

            if (!data.success) {
                alert('Gagal menyimpan: ' + (data.message || 'Terjadi kesalahan sistem.'));
                return;
            }

            playBeepSound();

            // Opsi B: Tidak langsung mencetak 1 per 1, melainkan menampung ke daftar di section kanan
            // Reset status cetak barcode sesi ini karena ada box baru
            if (noSj) sessionStorage.removeItem('barcode_printed_' + noSj);

            // Update UI Ringkasan Sesi & Daftar Box di kanan
            if (data.session_summary) {
                renderInboundSessionSummary(data.session_summary);
            }

            // Reset input berat dan fokuskan kembali untuk box berikutnya
            if (inpNetto) {
                inpNetto.value = '';
                inpNetto.focus();
            }

        } catch (e) {
            alert('Kesalahan koneksi / sistem: ' + e.message);
        } finally {
            if (btnSave) btnSave.disabled = false;
            if (labelBtn) labelBtn.innerText = 'Tambah Box';
        }
    }

    // Helper Render Stiker Barcode (Sticker 70mm x 50mm Sesuai Format Thermal Roll)
    function renderSquareBarcodeCardHtml(lbl, boxIdx = 1, totalBoxes = 1) {
        const padIndex = String(boxIdx).padStart(2, '0');
        const skuCode = (lbl.nama_item || lbl.kode_item || lbl.sku || '-').trim();
        const noSj = (lbl.no_surat_jalan || '').trim();
        const weightVal = parseFloat(lbl.berat_netto || 0).toFixed(2);
        const labelDateStr = lbl.created_at ? new Date(lbl.created_at).toLocaleDateString('id-ID') : new Date().toLocaleDateString('id-ID');
        const barcodeText = lbl.id_label || '';
        const barcodeSvg = lbl.barcode_svg || '';
        const lembarStr = totalBoxes > 1 ? `${boxIdx}/${totalBoxes}` : `${boxIdx}`;

        // Sembunyikan seri pemasok (misal: BH / BH-01) dari No Surat Jalan agar anonim di pasar
        let displaySj = noSj;
        if (noSj) {
            const sjParts = noSj.split('-');
            if (sjParts.length >= 4 && sjParts[0].toUpperCase() === 'SJ') {
                // Format SJ-[Pemasok]-[YYMMDD]-[Urut] -> sembunyikan pemasok, ambil hanya SJ-[YYMMDD]-[Urut]
                const dateAndSeq = sjParts.slice(-2).join('-');
                displaySj = `SJ-${dateAndSeq}`;
            }
        }

        // Sembunyikan prefix pemasok & jenis produk (contoh: BH01-PRD01) agar anonim di pasar
        let displayBarcodeNo = barcodeText;
        if (barcodeText.includes('-')) {
            const parts = barcodeText.split('-');
            if (parts.length >= 4) {
                // Sembunyikan 2 segmen awal (pemasok & item), tampilkan hanya [YYMMDD]-[Urut]
                displayBarcodeNo = parts.slice(2).join('-');
            } else if (parts.length === 3) {
                displayBarcodeNo = parts.slice(1).join('-');
            }
        }

        return `
            <div class="square-sheet-card" style="box-sizing: border-box; width: 64mm; height: 46mm; border: 2px solid #000; background: #fff; color: #000; font-family: 'Plus Jakarta Sans', Arial, Helvetica, sans-serif; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; page-break-inside: avoid; margin: 0; padding: 0;">
                <div style="padding: 1.2mm 2.2mm; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #000; flex-shrink: 0; box-sizing: border-box;">
                    <span style="font-size: 13.5px; font-weight: 800; letter-spacing: -0.2px; color: #000; line-height: 1; white-space: nowrap;">AR_BAROKAH</span>
                    <div style="border: 1.8px solid #000; border-radius: 2px; display: inline-flex; align-items: stretch; height: 20px; box-sizing: border-box;">
                        <span style="padding: 0 6px; font-weight: 800; font-size: 12.5px; border-right: 1.8px solid #000; display: flex; align-items: center; justify-content: center; line-height: 1;">#${padIndex}</span>
                        <span style="padding: 0 7px; font-weight: 800; font-size: 12.5px; display: flex; align-items: center; justify-content: center; line-height: 1; text-transform: uppercase;">${skuCode}</span>
                    </div>
                </div>
                <div style="flex: 1; padding: 1.2mm 2.5mm 1mm 2.5mm; display: flex; flex-direction: column; justify-content: space-between; box-sizing: border-box;">
                    <div style="display: flex; justify-content: space-between; align-items: baseline;">
                        ${displaySj ? `
                        <div style="display: flex; align-items: baseline;">
                            <span style="font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 800; color: #000; letter-spacing: 0.1px;">${displaySj}</span>
                        </div>` : '<div></div>'}
                        <div style="display: flex; align-items: baseline; gap: 3px;">
                            <span style="font-size: 12px; font-weight: 800; color: #000;">Netto :</span>
                            <span style="font-family: 'JetBrains Mono', monospace; font-size: 14.5px; font-weight: 900; color: #000;">${weightVal} Kg</span>
                        </div>
                    </div>
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; margin: 0.5mm 0;">
                        <div class="square-barcode-svg" style="width: 100%; height: 17mm; max-height: 17mm; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #ffffff;">
                            ${barcodeSvg}
                        </div>
                        <div style="font-size: 12px; font-weight: 800; font-family: 'JetBrains Mono', monospace; letter-spacing: 0.8px; color: #000; margin-top: 0.5mm; text-align: center; line-height: 1;">
                            ${displayBarcodeNo}
                        </div>
                    </div>
                    <!-- Ruang kosong (pengganti Tgl./Lembar) agar tata letak & ukuran stiker tetap sama -->
                    <div style="height: 9px; padding-bottom: 0.5mm; flex-shrink: 0;"></div>
                </div>
            </div>
        `;
    }

    // Cetak Stiker Thermal (70x50 mm) Satuan dengan Mockup Format Client
    async function printThermalLabelWithWeight(lbl, boxIdx = 1, totalBoxes = 1) {
        if (!lbl) return;

        // Ambil data barcode_svg jika belum terlampir
        if (!lbl.barcode_svg && lbl.id_label) {
            try {
                const resp = await fetch('index.php?action=get_labels_for_reprint&type=selected_ids&barcodes=' + encodeURIComponent(lbl.id_label) + '&ids=' + encodeURIComponent(lbl.id_label));
                const data = await resp.json();
                if (data && data.success && data.labels && data.labels.length) {
                    lbl = data.labels[0];
                }
            } catch (e) {
                console.error('Gagal mengambil barcode SVG:', e);
            }
        }

        const printContainer = document.getElementById('bulkPrintContainer');
        if (!printContainer) return;
        printContainer.removeAttribute('style');
        printContainer.innerHTML = '';

        const sheetDiv = document.createElement('div');
        sheetDiv.className = 'bulk-print-sheet single-thermal-sheet';
        sheetDiv.style.cssText = 'width: 70mm; height: 50mm; display: flex; flex-direction: column; justify-content: center; align-items: flex-start; padding: 0 0 0 1.0mm; margin: 0 auto; box-sizing: border-box;';

        sheetDiv.innerHTML = renderSquareBarcodeCardHtml(lbl, boxIdx, totalBoxes);
        printContainer.appendChild(sheetDiv);

        setPrintPageFormat('single');
        setTimeout(() => {
            window.print();
        }, 150);
    }

    // Update status disabled/enabled tombol Cetak Surat Jalan
    function updateFinishButtonState(totalBox = 0) {
        const btnFinish = document.getElementById('btnFinishSession');
        if (!btnFinish) return;

        const inpSj = document.getElementById('onestop_no_sj');
        const noSj = inpSj ? inpSj.value.trim() : '';

        const printedCount = parseInt(sessionStorage.getItem('barcode_printed_' + noSj) || '0', 10);
        const canFinish = totalBox > 0 && printedCount >= totalBox;

        if (canFinish) {
            btnFinish.disabled = false;
            btnFinish.style.opacity = '1';
            btnFinish.style.cursor = 'pointer';
            btnFinish.removeAttribute('title');
        } else {
            btnFinish.disabled = true;
            btnFinish.style.opacity = '0.5';
            btnFinish.style.cursor = 'not-allowed';
            btnFinish.title = totalBox === 0 
                ? 'Belum ada box yang ditimbang' 
                : 'Silakan cetak barcode terlebih dahulu sebelum mencetak surat jalan';
        }
    }

    // Render Ringkasan Sesi Surat Jalan
    function renderInboundSessionSummary(summary) {
        if (!summary) return;

        const badgePrint = document.getElementById('badgePrintCount');

        const totalBox = summary.total_box || 0;
        window.inboundTotalBox = totalBox;
        if (badgePrint) badgePrint.innerText = totalBox + ' Barcode';

        // Baris total di tabel (hanya bila lebih dari 1 box, agar tidak mengulang angka baris tunggal)
        const foot = document.getElementById('inboundTotalFoot');
        if (foot) {
            foot.style.display = totalBox >= 2 ? '' : 'none';
            const cnt = document.getElementById('inboundTotalBoxCount');
            const brt = document.getElementById('inboundTotalBerat');
            if (cnt) cnt.innerText = totalBox;
            if (brt) brt.innerText = parseFloat(summary.total_berat || 0).toFixed(2) + ' kg';
        }

        // Update enable/disable tombol Cetak Surat Jalan
        updateFinishButtonState(totalBox);

        // Breakdown per produk (tersembunyi karena sudah digabung)
        const breakdownDiv = document.getElementById('containerProductBreakdown');
        if (breakdownDiv) {
            breakdownDiv.innerHTML = '';
        }

        // Live Feed Rincian Produk (Sama Persis seperti Tabel Scan Pengeluaran)
        const tbody = document.getElementById('containerRecentBoxes');
        if (tbody) {
            if (!summary.recent_boxes || !summary.recent_boxes.length) {
                tbody.innerHTML = `
                    <tr id="emptyInboundRow">
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 40px 20px;">
                            <div style="font-weight: 700; font-size: 13.5px; color: var(--text-main);">Belum ada box ditimbang</div>
                            <div style="font-size: 11.5px; margin-top: 3px;">Timbang box pada formulir di sebelah kiri untuk memasukkan ke sesi ini.</div>
                        </td>
                    </tr>
                `;
            } else {
                tbody.innerHTML = '';
                let index = 1;
                summary.recent_boxes.forEach((b) => {
                    const netto = parseFloat(b.berat_netto || 0);
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td style="color: var(--text-muted); font-size: 12px; text-align: center;">${index++}</td>
                        <td>
                            <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--primary);">
                                ${b.id_label}
                            </strong>
                        </td>
                        <td>
                            <strong style="color: var(--text-main); font-size: 13px;">${b.nama_item}</strong>
                        </td>
                        <td>
                            <span class="badge badge-outline" style="font-size: 11px;">${b.nama_jenis || '-'}</span>
                        </td>
                        <td style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; font-size: 13px; color: #10b981;">
                            ${netto.toFixed(2)} kg
                        </td>
                        <td style="text-align: center; position: sticky; right: 0; background: var(--bg-card, #ffffff); z-index: 5; box-shadow: -2px 0 4px rgba(0,0,0,0.04);">
                            <button type="button" class="btn btn-outline btn-sm" style="padding: 2px 7px; color: var(--danger); cursor: pointer;" onclick="handleDeleteInboundBox(${b.id}, '${b.id_label}')" title="Hapus dari sesi">
                                ✕
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }
        }
    }

    // Hapus Box dari Sesi Penerimaan Aktif
    async function handleDeleteInboundBox(labelId, barcode) {
        const inpSj = document.getElementById('onestop_no_sj');
        const noSj = inpSj ? inpSj.value.trim() : '';

        const confirmed = confirm(`Hapus box barcode ${barcode} dari daftar sesi ini?`);
        if (!confirmed) return;

        try {
            const fd = new FormData();
            fd.append('label_id', labelId);
            fd.append('barcode', barcode);
            fd.append('no_surat_jalan', noSj);

            const resp = await fetch('index.php?action=delete_inbound_box', {
                method: 'POST',
                body: fd
            });
            const data = await resp.json();

            if (data && data.success) {
                if (noSj) sessionStorage.removeItem('barcode_printed_' + noSj);
                if (data.session_summary) {
                    renderInboundSessionSummary(data.session_summary);
                } else {
                    fetchInboundSessionSummary();
                }
            } else {
                alert('Gagal menghapus box: ' + (data.message || 'Error'));
            }
        } catch (e) {
            alert('Kesalahan jaringan: ' + e.message);
        }
    }

    // Opsi B: Cetak Semua Barcode di Surat Jalan Ini (Thermal Roll)
    async function printAllBarcodesInSession(format = 'thermal') {
        const inpSj = document.getElementById('onestop_no_sj');
        const noSj = inpSj ? inpSj.value.trim() : '';

        if (!noSj) {
            alert('Nomor Surat Jalan tidak valid.');
            return;
        }

        try {
            const resp = await fetch('index.php?action=get_labels_for_reprint&type=group_sj&no_surat_jalan=' + encodeURIComponent(noSj) + '&status=all');
            const data = await resp.json();

            if (!data || !data.success || !data.labels || !data.labels.length) {
                alert('Belum ada box yang ditambahkan untuk Surat Jalan ini.');
                return;
            }

            const labels = data.labels;
            const printContainer = document.getElementById('bulkPrintContainer');
            if (!printContainer) return;
            printContainer.removeAttribute('style');
            printContainer.innerHTML = '';

            // Seluruh pencetakan barcode menggunakan Format Stiker 70x50 mm (Urutan Terbesar ke Terkecil)
            const totalBoxes = labels.length;
            const indexedLabels = labels.map((lbl, idx) => ({
                lbl: lbl,
                boxIdx: idx + 1
            })).reverse();

            indexedLabels.forEach(item => {
                const sheetDiv = document.createElement('div');
                sheetDiv.className = 'bulk-print-sheet single-thermal-sheet';
                sheetDiv.style.cssText = 'width: 70mm; height: 50mm; display: flex; flex-direction: column; justify-content: center; align-items: flex-start; padding: 0 0 0 1.0mm; margin: 0 auto; box-sizing: border-box;';

                sheetDiv.innerHTML = renderSquareBarcodeCardHtml(item.lbl, item.boxIdx, totalBoxes);
                printContainer.appendChild(sheetDiv);
            });
            setPrintPageFormat('single');

            // Tandai bahwa barcode untuk sesi ini telah dicetak
            sessionStorage.setItem('barcode_printed_' + noSj, labels.length);
            updateFinishButtonState(labels.length);

            setTimeout(() => {
                window.print();
            }, 200);

        } catch (e) {
            alert('Kesalahan cetak barcode: ' + e.message);
        }
    }

    // Ambil Summary Sesi Terkini dari Database
    async function fetchInboundSessionSummary() {
        const inpSj = document.getElementById('onestop_no_sj');
        const noSj = inpSj ? inpSj.value.trim() : '';
        if (!noSj) {
            renderInboundSessionSummary({ total_box: 0, total_berat: 0, products: [], recent_boxes: [] });
            return;
        }

        try {
            const resp = await fetch('index.php?action=get_inbound_session_summary&no_surat_jalan=' + encodeURIComponent(noSj));
            const data = await resp.json();
            if (data && data.success && data.summary) {
                renderInboundSessionSummary(data.summary);
            } else {
                renderInboundSessionSummary({ total_box: 0, total_berat: 0, products: [], recent_boxes: [] });
            }
        } catch (e) {
            renderInboundSessionSummary({ total_box: 0, total_berat: 0, products: [], recent_boxes: [] });
        }
    }

    // Selesaikan Sesi Surat Jalan Truk Ini
    async function handleFinishInboundSession() {
        const inpSj = document.getElementById('onestop_no_sj');
        const noSj = inpSj ? inpSj.value.trim() : '';
        const pemasokId = document.getElementById('onestop_pemasok_id')?.value;
        
        if (!noSj) {
            alert('Nomor Surat Jalan tidak valid.');
            return;
        }

        const boxCount = parseInt(window.inboundTotalBox) || 0;
        if (boxCount <= 0) {
            alert('Belum ada keranjang yang ditimbang untuk Surat Jalan ini.');
            return;
        }

        const printedCount = parseInt(sessionStorage.getItem('barcode_printed_' + noSj) || '0', 10);
        if (printedCount < boxCount) {
            alert('Silakan cetak barcode terlebih dahulu sebelum mencetak Surat Jalan.');
            return;
        }

        const confirmed = confirm(`Selesaikan sesi Surat Jalan ${noSj} (${boxCount} box) dan cetak dokumen penerimaan?`);
        if (!confirmed) return;

        try {
            const fd = new FormData();
            fd.append('no_surat_jalan', noSj);
            fd.append('pemasok_id', pemasokId);

            const resp = await fetch('index.php?action=finish_inbound_session', {
                method: 'POST',
                body: fd
            });
            const data = await resp.json();

            if (data && data.success) {
                sessionStorage.removeItem('barcode_printed_' + noSj);

                // Otomatis buka dokumen Surat Jalan Penerimaan / BAST untuk tanda tangan & cetak
                if (data.print_bast_url) {
                    window.open(data.print_bast_url, '_blank');
                }

                // Perbarui ke Surat Jalan berikutnya untuk truk baru
                if (inpSj && data.next_surat_jalan) {
                    inpSj.value = data.next_surat_jalan;
                    onOneStopSjChanged();
                }
            } else {
                alert('Gagal menyelesaikan sesi: ' + (data.message || 'Error'));
            }
        } catch (e) {
            alert('Kesalahan jaringan: ' + e.message);
        }
    }

    // Stepper Navigation (Alur 2 Langkah)
    function switchPenerimaanTab(tabName) {
        const secMassal = document.getElementById('sectionMassal');
        const secScanTimbang = document.getElementById('sectionScanTimbang');
        const btnMassal = document.getElementById('tabBtnMassal');
        const btnScanTimbang = document.getElementById('tabBtnScanTimbang');

        if (secMassal) secMassal.style.display = 'none';
        if (secScanTimbang) secScanTimbang.style.display = 'none';
        if (btnMassal) btnMassal.classList.remove('active');
        if (btnScanTimbang) btnScanTimbang.classList.remove('active');

        if (tabName === 'massal' && secMassal && btnMassal) {
            secMassal.style.display = 'block';
            btnMassal.classList.add('active');
        } else if (tabName === 'scantimbang' && secScanTimbang && btnScanTimbang) {
            secScanTimbang.style.display = 'block';
            btnScanTimbang.classList.add('active');
            const scanInp = document.getElementById('scanBarcodeWeighInput');
            if (scanInp) {
                setTimeout(() => scanInp.focus(), 150);
            }
        }

        // Sinkronkan status active ke sidebar sub-menu
        const subBuat = document.getElementById('sidebarSubBuatCetak');
        const subTimbang = document.getElementById('sidebarSubTimbang');
        if (subBuat) subBuat.classList.toggle('active', tabName === 'massal');
        if (subTimbang) subTimbang.classList.toggle('active', tabName === 'scantimbang');

        // Update URL tanpa reload
        try {
            history.replaceState(null, '', 'index.php?page=timbang_cetak&tab=' + tabName);
        } catch (e) {}

        if (window.lucide) window.lucide.createIcons();
    }

    function goToScanWeigh(barcode) {
        window.location.href = 'index.php?page=timbang_cetak&tab=scantimbang&barcode=' + encodeURIComponent(barcode);
    }

    // Helper formatting
    function formatRupiah(num) {
        return new Intl.NumberFormat('id-ID').format(num);
    }

    function setBulkQty(q, btnEl) {
        const inp = document.getElementById('bulk_quantity');
        const safeQ = Math.max(1, Math.min(200, Math.abs(parseInt(q) || 1)));
        if (inp) inp.value = safeQ;
        document.querySelectorAll('.qty-pill-btn').forEach(b => b.classList.remove('active'));
        if (btnEl) {
            btnEl.classList.add('active');
        } else {
            const matchBtn = document.querySelector(`.qty-pill-btn[data-qty="${safeQ}"]`);
            if (matchBtn) matchBtn.classList.add('active');
        }
    }

    // Modal Anti-Duplikat: Barcode Sudah Digunakan
    function showBarcodeAlreadyUsedModal(label, customType) {
        const modal = document.getElementById('modalBarcodeAlreadyUsed');
        if (!modal) {
            alert('PERINGATAN: Barcode ' + label.id_label + ' sudah pernah discan & ditimbang (' + parseFloat(label.berat_netto || 0).toFixed(2) + ' KG). Barcode tidak dapat discan lagi!');
            return;
        }

        document.getElementById('usedBarcodeId').innerText = label.id_label || '-';
        document.getElementById('usedBarcodeItem').innerText = label.nama_item || '-';
        
        const pmsCode = label.kode_pemasok || label.kode_mitra || 'PMS';
        const pmsName = label.nama_pemasok || label.nama_mitra || 'Pemasok';
        document.getElementById('usedBarcodeMitra').innerText = `${pmsCode} - ${pmsName}`;
        document.getElementById('usedBarcodeSJ').innerText = label.no_surat_jalan || label.batch_no || '-';

        const warnText = document.getElementById('usedModalWarningText');
        const weightBadge = document.getElementById('usedBarcodeWeight');

        if (customType === 'SUDAH KELUAR') {
            warnText.innerHTML = `⚠️ Barcode ini <strong>sudah keluar dari gudang (Status KELUAR)</strong>. Tidak dapat discan atau ditimbang kembali.`;
            weightBadge.className = 'badge badge-info';
            weightBadge.innerText = `${parseFloat(label.berat_netto || 0).toFixed(2)} KG (Sudah Keluar)`;
        } else if (customType === 'VOID') {
            warnText.innerHTML = `⚠️ Barcode ini <strong>telah dibatalkan (Status VOID)</strong> dan tidak dapat digunakan lagi.`;
            weightBadge.className = 'badge badge-destructive';
            weightBadge.innerText = `VOID / DIBATALKAN`;
        } else {
            warnText.innerHTML = `⚠️ Barcode ini <strong>sudah pernah discan dan ditimbang</strong>. Sistem menolak penimbangan ulang agar tidak terjadi pencatatan ganda pada stok fisik gudang.`;
            weightBadge.className = 'badge badge-success';
            weightBadge.innerText = `${parseFloat(label.berat_netto || 0).toFixed(2)} KG (Selesai Ditimbang)`;
        }

        modal.style.display = 'flex';
        if (window.lucide) lucide.createIcons();

        const btnClose = document.getElementById('btnCloseUsedModal');
        if (btnClose) {
            setTimeout(() => btnClose.focus(), 50);
        }
    }

    function closeBarcodeAlreadyUsedModal() {
        const modal = document.getElementById('modalBarcodeAlreadyUsed');
        if (modal) modal.style.display = 'none';
        const scanInp = document.getElementById('scanBarcodeWeighInput');
        if (scanInp) {
            scanInp.value = '';
            setTimeout(() => scanInp.focus(), 50);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const btnClose = document.getElementById('btnCloseUsedModal');
        if (btnClose) {
            btnClose.addEventListener('click', closeBarcodeAlreadyUsedModal);
        }

        document.addEventListener('keydown', function(e) {
            const modal = document.getElementById('modalBarcodeAlreadyUsed');
            if (modal && modal.style.display === 'flex') {
                if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    closeBarcodeAlreadyUsedModal();
                }
            }
        });
    });

    // Helper mengatur @page size dinamis saat mencetak
    function setPrintPageFormat(mode) {
        let styleEl = document.getElementById('dynamicPrintPageFormat');
        if (!styleEl) {
            styleEl = document.createElement('style');
            styleEl.id = 'dynamicPrintPageFormat';
            document.head.appendChild(styleEl);
        }

        if (mode === 'bulk') {
            document.body.classList.remove('print-mode-single-thermal');
            document.body.classList.add('print-mode-bulk-sheet');
            styleEl.innerHTML = `
                @page {
                    size: A4 portrait !important;
                    margin: 5mm 6mm 5mm 6mm !important;
                }
                @media print {
                    .app-layout, .app-main-container, .main-wrapper {
                        display: block !important;
                        visibility: visible !important;
                        margin: 0 !important;
                        margin-left: 0 !important;
                        padding: 0 !important;
                        width: 100% !important;
                        min-height: 0 !important;
                        height: auto !important;
                        position: static !important;
                    }
                    #bulkPrintContainer, .print-area {
                        display: block !important;
                        visibility: visible !important;
                        height: auto !important;
                        position: static !important;
                        left: auto !important;
                        top: auto !important;
                        width: 100% !important;
                    }
                }
            `;
        } else {
            document.body.classList.remove('print-mode-bulk-sheet');
            document.body.classList.add('print-mode-single-thermal');
            styleEl.innerHTML = `
                @page {
                    size: 70mm 50mm !important;
                    margin: 0 !important;
                }
                @media print {
                    html, body {
                        width: 100% !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        background: #ffffff !important;
                    }
                    .app-layout, .app-main-container, .main-wrapper {
                        display: block !important;
                        visibility: visible !important;
                        margin: 0 auto !important;
                        padding: 0 !important;
                        width: 100% !important;
                        max-width: 70mm !important;
                        min-height: 0 !important;
                        height: auto !important;
                        position: static !important;
                    }
                    #bulkPrintContainer, .print-area {
                        display: flex !important;
                        flex-direction: column !important;
                        align-items: center !important;
                        justify-content: center !important;
                        visibility: visible !important;
                        height: auto !important;
                        position: static !important;
                        left: auto !important;
                        top: auto !important;
                        width: 100% !important;
                        max-width: 70mm !important;
                        margin: 0 auto !important;
                        padding: 0 !important;
                    }
                    .bulk-print-sheet.single-thermal-sheet {
                        width: 70mm !important;
                        height: 50mm !important;
                        display: flex !important;
                        flex-direction: column !important;
                        justify-content: center !important;
                        align-items: flex-start !important;
                        margin: 0 auto !important;
                        padding: 0 0 0 1.0mm !important;
                        box-sizing: border-box !important;
                        page-break-after: always !important;
                        break-after: page !important;
                    }
                    .bulk-print-sheet.single-thermal-sheet:last-child {
                        page-break-after: avoid !important;
                        break-after: avoid !important;
                    }
                    .square-sheet-card {
                        width: 64mm !important;
                        height: 46mm !important;
                        max-width: 64mm !important;
                        max-height: 46mm !important;
                        margin: 0 !important;
                        box-sizing: border-box !important;
                        border: 2px solid #000000 !important;
                        background: #ffffff !important;
                    }
                    .square-barcode-svg svg {
                        width: 100% !important;
                        height: 17mm !important;
                        max-height: 17mm !important;
                    }
                }
            `;
        }
    }

    // Print all labels inside #bulkPrintContainer (Mode Sheet A4: 10 pcs/lembar)
    function printBulkContainer() {
        const container = document.getElementById('bulkPrintContainer');
        if (!container || !container.children.length) {
            alert('Tidak ada barcode massal untuk dicetak');
            return;
        }
        container.removeAttribute('style');
        setPrintPageFormat('single');
        setTimeout(() => {
            window.print();
        }, 200);
    }

    // Pratinjau Stiker Kotak di Modal Layar
    function openSheetPreviewModal() {
        const modal = document.getElementById('modalSheetPreview');
        const body = document.getElementById('sheetPreviewBody');
        const container = document.getElementById('bulkPrintContainer');
        if (!modal || !body || !container || !container.children.length) {
            alert('Belum ada barcode yang digenerate untuk dipratinjau');
            return;
        }

        body.innerHTML = '';
        const sheets = container.querySelectorAll('.bulk-print-sheet');
        const totalSheets = sheets.length;

        document.getElementById('sheetPreviewTitle').innerText = `Pratinjau Cetak: ${totalSheets} Stiker Barcode (70×50 mm)`;

        sheets.forEach((sheet, idx) => {
            const pageWrapper = document.createElement('div');
            pageWrapper.className = 'sheet-preview-page';
            pageWrapper.style.cssText = 'background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 12px; margin-bottom: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center;';
            
            const pageHeader = document.createElement('div');
            pageHeader.style.cssText = 'display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #cbd5e1; width: 64mm; padding-bottom: 4px; margin-bottom: 8px; font-size: 11px; font-weight: 800; color: #4f46e5;';
            pageHeader.innerHTML = `
                <span>🏷️ STIKER #${idx + 1} DARI ${totalSheets}</span>
                <span>FORMAT: 70 × 50 MM</span>
            `;
            pageWrapper.appendChild(pageHeader);

            const clone = sheet.cloneNode(true);
            pageWrapper.appendChild(clone);

            body.appendChild(pageWrapper);
        });

        modal.style.display = 'flex';
        if (window.lucide) window.lucide.createIcons();
    }

    function closeSheetPreviewModal() {
        const modal = document.getElementById('modalSheetPreview');
        if (modal) modal.style.display = 'none';
    }

    // Toggle Switch Mode Tampilan Tabel
    function switchTableViewMode(mode) {
        const btnGroup = document.getElementById('btnViewModeGroup');
        const btnDetail = document.getElementById('btnViewModeDetail');
        const containerGroup = document.getElementById('containerViewGrouped');
        const containerDetail = document.getElementById('containerViewDetail');

        if (mode === 'group') {
            if (btnGroup) btnGroup.className = 'btn btn-primary btn-sm';
            if (btnDetail) btnDetail.className = 'btn btn-outline btn-sm';
            if (containerGroup) containerGroup.style.display = 'block';
            if (containerDetail) containerDetail.style.display = 'none';
        } else {
            if (btnGroup) btnGroup.className = 'btn btn-outline btn-sm';
            if (btnDetail) btnDetail.className = 'btn btn-primary btn-sm';
            if (containerGroup) containerGroup.style.display = 'none';
            if (containerDetail) containerDetail.style.display = 'block';
        }
        if (window.lucide) window.lucide.createIcons();
    }

    // Toggle Accordion Rincian Barcode dalam Kelompok
    async function toggleGroupAccordion(safeId, noSj) {
        const row = document.getElementById('detailRow_' + safeId);
        const icon = document.getElementById('icon_' + safeId);
        const listContainer = document.getElementById('detailList_' + safeId);
        if (!row) return;

        if (row.style.display === 'none' || !row.style.display) {
            row.style.display = 'table-row';
            if (icon) icon.setAttribute('data-lucide', 'chevron-up');

            // Ambil rincian data barcode jika belum pernah diambil
            if (listContainer && listContainer.getAttribute('data-loaded') !== 'true') {
                try {
                    const resp = await fetch('index.php?action=get_labels_for_reprint&type=group_sj&no_surat_jalan=' + encodeURIComponent(noSj));
                    const data = await resp.json();
                    if (data.success && data.labels && data.labels.length) {
                        listContainer.setAttribute('data-loaded', 'true');
                        listContainer.innerHTML = '';

                        data.labels.forEach((item, i) => {
                            const beratNetto = parseFloat(item.berat_netto || 0);
                            const itemCard = document.createElement('div');
                            itemCard.style.cssText = 'background: var(--bg-muted); border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center;';
                            itemCard.innerHTML = `
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="badge badge-purple" style="font-size: 10.5px;">#${i+1}</span>
                                    <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--primary);">${item.id_label}</strong>
                                    <span class="badge badge-success" style="font-size: 10.5px;">
                                        ✓ ${beratNetto.toFixed(2)} KG
                                    </span>
                                </div>
                                <div style="display: flex; gap: 6px; align-items: center;">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="openReprintSingleModal('${item.id_label}', '${item.kode_mitra || ''}', '${item.nama_item.replace(/'/g, "\\'")}', '${noSj.replace(/'/g, "\\'")}', '${item.nama_lokasi || 'Gudang'}', ${beratNetto}, ${parseFloat(item.harga_per_kg || 0)})" style="padding: 2px 8px; font-size: 11px; font-weight: 700;" title="Pilihan Cetak Barcode">
                                        🖨️ Cetak
                                    </button>
                                </div>
                            `;
                            listContainer.appendChild(itemCard);
                        });
                    } else {
                        listContainer.innerHTML = '<div style="color: var(--text-muted); font-size: 12px; padding: 6px;">Tidak ada rincian barcode ditemukan.</div>';
                    }
                } catch (err) {
                    listContainer.innerHTML = '<div style="color: red; font-size: 12px;">Gagal memuat rincian: ' + err.message + '</div>';
                }
            }
        } else {
            row.style.display = 'none';
            if (icon) icon.setAttribute('data-lucide', 'chevron-down');
        }
        if (window.lucide) window.lucide.createIcons();
    }

    // Modal Opsi Cetak Ulang Barcode 1 Pcs
    let currentReprintData = null;

    function openReprintSingleModal(barcode, kodeMitra, namaItem, noSj, lokasiName, beratKg, hrgPerKg) {
        currentReprintData = {
            barcode: barcode,
            kodeMitra: kodeMitra,
            namaItem: namaItem,
            noSj: noSj,
            lokasiName: lokasiName,
            beratKg: beratKg || 0,
            hrgPerKg: hrgPerKg || 0
        };

        document.getElementById('reprintTargetBarcode').innerText = barcode;
        document.getElementById('reprintTargetMitra').innerText = kodeMitra;
        document.getElementById('reprintTargetMeta').innerText = `${namaItem} • SJ: ${noSj} • Lok: ${lokasiName}`;
        document.getElementById('reprintGroupDesc').innerText = `Mencetak seluruh barcode kelompok surat jalan ${noSj} (format stiker 70×50 mm)`;

        const qtyInput = document.getElementById('reprintQtyInput');
        if (qtyInput) qtyInput.value = 1;

        const modal = document.getElementById('modalReprintChoice');
        if (modal) modal.style.display = 'flex';
        if (window.lucide) window.lucide.createIcons();
    }

    function setReprintQty(val) {
        const input = document.getElementById('reprintQtyInput');
        if (input) input.value = val;
    }

    function stepReprintQty(delta) {
        const input = document.getElementById('reprintQtyInput');
        if (!input) return;
        let val = parseInt(input.value) || 1;
        val = Math.max(1, Math.min(100, val + delta));
        input.value = val;
    }

    function validateReprintQty(input) {
        let val = parseInt(input.value) || 1;
        val = Math.max(1, Math.min(100, val));
        input.value = val;
    }

    function closeReprintChoiceModal() {
        const modal = document.getElementById('modalReprintChoice');
        if (modal) modal.style.display = 'none';
        currentReprintData = null;
    }

    // Eksekusi Cetak Duplikat Barcode Ini (Bisa Tentukan Jumlah Pcs)
    async function executeReprintCustomQty() {
        if (!currentReprintData) return;
        const barcode = currentReprintData.barcode;
        const input = document.getElementById('reprintQtyInput');
        const qty = Math.max(1, Math.min(100, parseInt(input ? input.value : 10) || 10));
        closeReprintChoiceModal();

        try {
            const resp = await fetch('index.php?action=get_labels_for_reprint&type=single_duplicate&qty=' + qty + '&barcode=' + encodeURIComponent(barcode));
            const data = await resp.json();
            if (data.success && data.labels && data.labels.length) {
                renderAndPrintSheetLabels(data.labels);
            } else {
                alert('Gagal mengambil data barcode: ' + (data.message || 'Terjadi kesalahan'));
            }
        } catch (e) {
            alert('Kesalahan jaringan: ' + e.message);
        }
    }

    // Eksekusi Cetak 10 Pcs (Backward compatibility)
    function executeReprintSingle10() {
        setReprintQty(10);
        executeReprintCustomQty();
    }

    // Eksekusi Cetak Seluruh Barcode di Surat Jalan Tersebut
    async function executeReprintGroupSJ() {
        if (!currentReprintData) return;
        const noSj = currentReprintData.noSj;
        closeReprintChoiceModal();
        reprintGroupSJ(noSj, 'all');
    }

    // Eksekusi Cetak Single Roll Thermal
    function executeReprintSingleThermal() {
        if (!currentReprintData) return;
        const d = currentReprintData;
        closeReprintChoiceModal();
        reprintBarcodeDirect(d.barcode, d.kodeMitra, d.namaItem, d.beratKg, d.noSj, d.lokasiName, d.hrgPerKg);
    }

    // Cetak Kelompok Surat Jalan
    async function reprintGroupSJ(noSj, status = 'all') {
        try {
            const resp = await fetch('index.php?action=get_labels_for_reprint&type=group_sj&no_surat_jalan=' + encodeURIComponent(noSj) + '&status=' + encodeURIComponent(status));
            const data = await resp.json();
            if (data.success && data.labels && data.labels.length) {
                renderAndPrintSheetLabels(data.labels);
            } else {
                alert('Tidak ada barcode yang dapat dicetak ulang pada surat jalan ini');
            }
        } catch (e) {
            alert('Kesalahan jaringan: ' + e.message);
        }
    }

    // Render Data Barcode ke Format Stiker 70x50 mm (Urutan Terbesar ke Terkecil)
    function renderAndPrintSheetLabels(labels) {
        const printContainer = document.getElementById('bulkPrintContainer');
        if (!printContainer) return;
        printContainer.removeAttribute('style');
        printContainer.innerHTML = '';

        const totalBoxes = labels.length;
        const indexedLabels = labels.map((lbl, idx) => ({
            lbl: lbl,
            boxIdx: idx + 1
        })).reverse();

        indexedLabels.forEach(item => {
            const sheetDiv = document.createElement('div');
            sheetDiv.className = 'bulk-print-sheet single-thermal-sheet';
            sheetDiv.style.cssText = 'width: 70mm; height: 50mm; display: flex; flex-direction: column; justify-content: center; align-items: flex-start; padding: 0 0 0 1.0mm; margin: 0 auto; box-sizing: border-box;';

            sheetDiv.innerHTML = renderSquareBarcodeCardHtml(item.lbl, item.boxIdx, totalBoxes);
            printContainer.appendChild(sheetDiv);
        });

        setPrintPageFormat('single');
        setTimeout(() => {
            window.print();
        }, 200);
    }

    // Cetak Satuan Langsung (Thermal Roll 70x50 mm)
    async function reprintBarcodeDirect(barcodeOrObj, kodeMitra, namaItem, beratKg, noSj, lokasiName, hrgPerKg) {
        try {
            let lbl = null;

            // Jika yang dikirim sudah berupa objek label lengkap dengan barcode_svg (dari update_weight)
            if (barcodeOrObj && typeof barcodeOrObj === 'object' && barcodeOrObj.id_label && barcodeOrObj.barcode_svg) {
                lbl = barcodeOrObj;
            } else {
                const barcode = typeof barcodeOrObj === 'string' ? barcodeOrObj : (barcodeOrObj?.id_label || '');
                if (!barcode) {
                    alert('ID Barcode tidak valid');
                    return;
                }
                const resp = await fetch('index.php?action=get_labels_for_reprint&type=selected_ids&barcodes=' + encodeURIComponent(barcode) + '&ids=' + encodeURIComponent(barcode));
                const data = await resp.json();
                if (data.success && data.labels && data.labels.length) {
                    lbl = data.labels[0];
                } else {
                    alert('Gagal mengambil data barcode: ' + (data.message || 'Data barcode tidak ditemukan'));
                    return;
                }
            }

            if (!lbl) return;

            const printContainer = document.getElementById('bulkPrintContainer');
            printContainer.removeAttribute('style');
            printContainer.innerHTML = '';
            
            const sheetDiv = document.createElement('div');
            sheetDiv.className = 'bulk-print-sheet single-thermal-sheet';
            sheetDiv.style.cssText = 'width: 70mm; height: 50mm; display: flex; flex-direction: column; justify-content: center; align-items: flex-start; padding: 0 0 0 1.0mm; margin: 0 auto; box-sizing: border-box;';
            
            sheetDiv.innerHTML = renderSquareBarcodeCardHtml(lbl, 1, 1);
            printContainer.appendChild(sheetDiv);

            setPrintPageFormat('single');
            setTimeout(() => {
                window.print();
            }, 200);

        } catch (e) {
            alert('Kesalahan jaringan: ' + e.message);
        }
    }

    // Custom Floating Shadcn-style Dropdown Enhancer (Menggantikan menu select OS bawaan)
    function makeShadcnSelect(selectEl) {
        if (window.enhanceSelect) { window.enhanceSelect(selectEl); return; }
        if (!selectEl || selectEl.dataset.shadcnEnhanced) return;
        selectEl.dataset.shadcnEnhanced = "true";
        selectEl.style.display = "none";

        const wrapper = document.createElement("div");
        wrapper.className = "shadcn-select-wrapper";

        const trigger = document.createElement("button");
        trigger.type = "button";
        trigger.className = "shadcn-select-trigger";
        trigger.id = selectEl.id + "_trigger";

        const popup = document.createElement("div");
        popup.className = "shadcn-select-content";
        popup.style.display = "none";

        function updateTriggerText() {
            const selectedOpt = selectEl.options[selectEl.selectedIndex];
            const text = selectedOpt ? selectedOpt.textContent.trim() : "-- Pilih --";
            const isPlaceholder = !selectEl.value || selectEl.value === "";

            trigger.innerHTML = `
                <span style="${isPlaceholder ? 'color: #94a3b8; font-weight: 500;' : 'color: #0f172a; font-weight: 700;'} overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${text}</span>
                <i data-lucide="chevron-down" class="shadcn-select-chevron" style="width: 16px; height: 16px; color: #64748b; flex-shrink: 0; transition: transform 0.2s;"></i>
            `;
            if (window.lucide) window.lucide.createIcons({ root: trigger });

            if (selectEl.disabled) {
                trigger.disabled = true;
                trigger.classList.add("disabled");
            } else {
                trigger.disabled = false;
                trigger.classList.remove("disabled");
            }
        }

        function renderOptions() {
            popup.innerHTML = "";
            Array.from(selectEl.options).forEach((opt) => {
                const item = document.createElement("div");
                const isSelected = opt.value === selectEl.value;
                item.className = "shadcn-select-item" + (isSelected ? " selected" : "");
                const isPlaceholder = !opt.value || opt.value === "";

                item.innerHTML = `
                    <span style="${isPlaceholder ? 'color: #94a3b8;' : ''}">${opt.textContent.trim()}</span>
                    ${isSelected && !isPlaceholder ? '<i data-lucide="check" style="width: 14px; height: 14px; color: #2563eb; flex-shrink: 0;"></i>' : ''}
                `;
                if (window.lucide) window.lucide.createIcons({ root: item });

                item.addEventListener("click", (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    selectEl.value = opt.value;
                    updateTriggerText();
                    closePopup();
                    selectEl.dispatchEvent(new Event("change", { bubbles: true }));
                });
                popup.appendChild(item);
            });
        }

        function openPopup() {
            if (selectEl.disabled) return;
            document.querySelectorAll(".shadcn-select-content").forEach(p => {
                if (p !== popup) p.style.display = "none";
            });
            document.querySelectorAll(".shadcn-select-chevron").forEach(c => {
                c.style.transform = "rotate(0deg)";
            });

            renderOptions();
            popup.style.display = "block";
            const chevron = trigger.querySelector(".shadcn-select-chevron");
            if (chevron) chevron.style.transform = "rotate(180deg)";
            trigger.classList.add("open");
        }

        function closePopup() {
            popup.style.display = "none";
            const chevron = trigger.querySelector(".shadcn-select-chevron");
            if (chevron) chevron.style.transform = "rotate(0deg)";
            trigger.classList.remove("open");
        }

        trigger.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (popup.style.display === "block") {
                closePopup();
            } else {
                openPopup();
            }
        });

        document.addEventListener("click", (e) => {
            if (!wrapper.contains(e.target)) {
                closePopup();
            }
        });

        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") closePopup();
        });

        const observer = new MutationObserver(() => {
            updateTriggerText();
        });
        observer.observe(selectEl, { attributes: true, childList: true, subtree: true });

        selectEl.addEventListener("change", () => {
            updateTriggerText();
        });

        selectEl.parentNode.insertBefore(wrapper, selectEl);
        wrapper.appendChild(selectEl);
        wrapper.appendChild(trigger);
        wrapper.appendChild(popup);

        updateTriggerText();
    }

    // Eksekusi utama setelah DOM siap
    document.addEventListener('DOMContentLoaded', function() {
        // Inisialisasi dropdown bergaya shadcn (menggantikan menu select OS default)
        makeShadcnSelect(document.getElementById('onestop_pemasok_id'));
        makeShadcnSelect(document.getElementById('onestop_jenis_filter'));
        makeShadcnSelect(document.getElementById('onestop_item_id'));
        // Inisialisasi Modul Penerimaan & Timbang Satu Pintu
        if (typeof onOneStopJenisChanged === 'function') {
            onOneStopJenisChanged();
        }
        if (typeof fetchInboundSessionSummary === 'function') {
            fetchInboundSessionSummary();
        }

        // Hubungkan timbangan digital ke input onestop
        if (window.weighScale && typeof window.weighScale.onWeight === 'function') {
            window.weighScale.onWeight((weightKg) => {
                const onestopWeightInp = document.getElementById('onestop_berat_netto');
                if (onestopWeightInp) {
                    onestopWeightInp.value = weightKg.toFixed(2);
                }
            });
        }

        const mitraSelect = document.getElementById('mitra_id');
        const itemSelect  = document.getElementById('item_id');
        const brutoInput  = document.getElementById('berat_bruto');
        const nettoInput  = document.getElementById('berat_netto');
        const sjInput     = document.getElementById('no_surat_jalan');
        const lokasiSelect= document.getElementById('lokasi_id');
        const hrgPerKgInp = document.getElementById('input_harga_per_kg');

        // Update harga otomatis berdasarkan Mitra dan Item yang dipilih
        function updateHargaOtomatis() {
            if (!mitraSelect || !itemSelect) return;
            const mitraId = mitraSelect.value;
            const itemId  = itemSelect.value;
            const key = mitraId + '_' + itemId;

            const optMitra = mitraSelect.options[mitraSelect.selectedIndex];
            const badgeMitra = document.getElementById('lblMitraBadge');
            if (badgeMitra && optMitra) badgeMitra.innerText = optMitra.dataset.kode;

            let harga = pricesLookup[key] || 0;
            if (hrgPerKgInp) hrgPerKgInp.value = harga;
            const lblHrg = document.getElementById('lblHargaPerKg');
            if (lblHrg) lblHrg.innerText = formatRupiah(harga);

            const netto = parseFloat(nettoInput.value) || 0;
            const totalNilai = Math.round(netto * harga);
            const lblTot = document.getElementById('lblTotalNilai');
            if (lblTot) lblTot.innerText = formatRupiah(totalNilai);

            const lblHrgBadge = document.getElementById('lblHargaBadgeText');
            if (lblHrgBadge) lblHrgBadge.innerText = harga > 0 ? ('Rp ' + formatRupiah(harga) + '/kg') : 'Harga: -';
        }

        window.updateCalculations = function() {
            if (!nettoInput) return;
            const berat = parseFloat(nettoInput.value) || 0;
            if (brutoInput) brutoInput.value = berat.toFixed(2);
            const lblNetto = document.getElementById('lblNettoText');
            if (lblNetto) lblNetto.innerText = berat.toFixed(2);

            if (mitraSelect && mitraSelect.selectedIndex >= 0) {
                const optMitra = mitraSelect.options[mitraSelect.selectedIndex];
                const lblBox = document.getElementById('lblMitraBox');
                if (lblBox && optMitra) {
                    lblBox.innerText = optMitra.dataset.kode + ' - ' + (optMitra.dataset.nama || '').substring(0, 15);
                }
            }

            if (itemSelect && itemSelect.selectedIndex >= 0) {
                const optItem = itemSelect.options[itemSelect.selectedIndex];
                const lblItem = document.getElementById('lblItemName');
                if (lblItem && optItem) lblItem.innerText = optItem.dataset.nama || '';
                const lblJenis = document.getElementById('lblJenisText');
                if (lblJenis && optItem) {
                    lblJenis.innerText = (optItem.dataset.jenis || '') + ' (' + (optItem.dataset.kodeJenis || '') + ')';
                }
            }

            if (sjInput) {
                const lblSJ = document.getElementById('lblSJText');
                if (lblSJ) lblSJ.innerText = 'SJ: ' + sjInput.value;
            }

            if (lokasiSelect && lokasiSelect.selectedIndex >= 0) {
                const lblLok = document.getElementById('lblLokasiText');
                if (lblLok) lblLok.innerText = lokasiSelect.options[lokasiSelect.selectedIndex].text.split(' - ')[0];
            }

            updateHargaOtomatis();
            const harga = parseFloat(hrgPerKgInp ? hrgPerKgInp.value : 0) || 0;
            const total = Math.round(berat * harga);
            const lblPrev = document.getElementById('lblPreviewNilai');
            if (lblPrev) lblPrev.innerText = 'Rp ' + formatRupiah(total);
        };

        window.setQuickWeight = function(beratKg) {
            if (!nettoInput) return;
            nettoInput.value = beratKg.toFixed(2);
            window.updateCalculations();
            if (window.scannerAudio) window.scannerAudio.playSuccessBeep();
        };

        // Buat Nomor Surat Jalan Otomatis Baru
        async function generateNewSuratJalan(targetInput, targetLabel, optMitra) {
            const kode = (optMitra && optMitra.dataset.kode) ? optMitra.dataset.kode : 'M01';
            try {
                const resp = await fetch('index.php?action=gen_surat_jalan&kode=' + encodeURIComponent(kode));
                const data = await resp.json();
                if (data.success) {
                    targetInput.value = data.no_surat_jalan;
                    if (targetLabel) targetLabel.innerText = 'SJ: ' + data.no_surat_jalan;
                }
            } catch (e) {
                const d = new Date();
                const yy = String(d.getFullYear()).slice(-2);
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const dd = String(d.getDate()).padStart(2, '0');
                const rnd = Math.floor(1000 + Math.random() * 9000);
                targetInput.value = `SJ-${kode}-${yy}${mm}${dd}-${rnd}`;
                if (targetLabel) targetLabel.innerText = 'SJ: ' + targetInput.value;
            }
        }

        const btnGenSJ = document.getElementById('btnGenSJ');
        if (btnGenSJ) {
            btnGenSJ.addEventListener('click', () => {
                generateNewSuratJalan(sjInput, document.getElementById('lblSJText'), mitraSelect.options[mitraSelect.selectedIndex]);
            });
        }

        const bulkPemasok = document.getElementById('bulk_pemasok_id');
        const bulkSJ = document.getElementById('bulk_no_sj');

        if (bulkPemasok && bulkSJ) {
            bulkPemasok.addEventListener('change', function() {
                generateNewSuratJalan(bulkSJ, null, this.options[this.selectedIndex]);
            });
        }

        if (mitraSelect) {
            mitraSelect.addEventListener('change', function() {
                generateNewSuratJalan(sjInput, document.getElementById('lblSJText'), mitraSelect.options[mitraSelect.selectedIndex]);
                window.updateCalculations();
            });
        }

        if (itemSelect) itemSelect.addEventListener('change', window.updateCalculations);
        if (nettoInput) nettoInput.addEventListener('input', window.updateCalculations);
        if (sjInput) {
            sjInput.addEventListener('input', function() {
                const lbl = document.getElementById('lblSJText');
                if (lbl) lbl.innerText = 'SJ: ' + this.value;
            });
        }
        if (lokasiSelect) lokasiSelect.addEventListener('change', window.updateCalculations);

        // Web Serial Connect (Guarded)
        const btnConnect = document.getElementById('btnConnectScale');
        if (btnConnect) {
            btnConnect.addEventListener('click', async () => {
                if (window.weighScale && typeof window.weighScale.connectSerial === 'function') {
                    const connected = await window.weighScale.connectSerial(9600);
                    const badge = document.getElementById('scaleStatusBadge');
                    if (connected && badge) {
                        badge.className = 'badge badge-success';
                        badge.innerText = '● Timbangan Serial OK (9600 bps)';
                    }
                } else {
                    alert('Driver timbangan belum dimuat di peramban ini');
                }
            });
        }

        if (window.weighScale && typeof window.weighScale.onWeight === 'function') {
            window.weighScale.onWeight((weightKg) => {
                // Jika aktif di tab Scan & Timbang
                const scanWeightInp = document.getElementById('scanWeightValue');
                const secScan = document.getElementById('sectionScanTimbang');
                if (scanWeightInp && secScan && secScan.style.display !== 'none') {
                    scanWeightInp.value = weightKg.toFixed(2);
                    window.recalcScanWeight();
                }
                if (window.scannerAudio) window.scannerAudio.playSuccessBeep();
            });
        }

        // -------------------------------------------------------------
        // TOMBOL: SIMPAN & CETAK SATUAN
        // -------------------------------------------------------------
        const btnSimpanCetak = document.getElementById('btnSimpanCetak');
        if (btnSimpanCetak) {
            btnSimpanCetak.addEventListener('click', async function() {
                btnSimpanCetak.disabled = true;
                const origHtml = btnSimpanCetak.innerHTML;
                btnSimpanCetak.innerHTML = '<span>Menyimpan...</span>';

                const formData = new FormData(document.getElementById('formTimbang'));

                try {
                    const resp = await fetch('index.php?action=save_label', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await resp.json();

                    if (data.success) {
                        if (window.scannerAudio) window.scannerAudio.playSuccessBeep();

                        document.getElementById('lblBarcodeText').innerText = data.label.id_label;
                        if (data.label.barcode_svg) {
                            document.getElementById('barcodeSvgContainer').innerHTML = data.label.barcode_svg;
                        }
                        if (data.label.qr_svg) {
                            document.getElementById('lblQrContainer').innerHTML = data.label.qr_svg;
                        }
                        if (data.label.nama_lokasi) {
                            document.getElementById('lblLokasiText').innerText = data.label.nama_lokasi;
                        }

                        // Tambahkan ke tabel penerimaan di bawah
                        const tbody = document.getElementById('allPenerimaanTableBody');
                        if (tbody) {
                            const row = document.createElement('tr');
                            const hrg = parseFloat(data.label.harga_per_kg || 0);
                            const tot = parseFloat(data.label.total_nilai || (hrg * data.label.berat_netto));
                            const sj = data.label.no_surat_jalan || data.label.batch_no || '-';
                            const lokName = data.label.nama_lokasi || 'Gudang';

                            row.innerHTML = `
                                <td><strong style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--primary);">${data.label.id_label}</strong></td>
                                <td><span class="mitra-tag ${data.label.kode_mitra.toLowerCase()}" style="background: ${data.label.warna_identitas}; padding: 2px 7px; font-size: 11px;">${data.label.kode_mitra}</span></td>
                                <td><strong>${data.label.nama_item}</strong></td>
                                <td style="text-align: right; font-weight: 800; color: #10b981;">${parseFloat(data.label.berat_netto).toFixed(2)} kg</td>
                                <td><code style="font-size: 11.5px; font-weight: 700; color: var(--primary);">${sj}</code></td>
                                <td style="font-size: 12px; color: var(--text-muted);">Baru Saja</td>
                                <td style="text-align: center;"><button type="button" class="btn btn-outline btn-sm" onclick="reprintBarcodeDirect('${data.label.id_label}')" style="padding: 3px 10px; font-size: 11.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; border-radius: 6px; cursor: pointer;" title="Cetak Barcode Thermal 70x50 mm"><i data-lucide="printer" style="width: 12px; height: 12px;"></i><span>Cetak</span></button></td>
                            `;
                            tbody.insertBefore(row, tbody.firstChild);
                        }

                        // Cetak langsung ke printer
                        reprintBarcodeDirect(data.label);

                    } else {
                        if (window.scannerAudio) window.scannerAudio.playErrorBuzz();
                        alert('Gagal Simpan: ' + (data.message || 'Terjadi kesalahan sistem'));
                    }
                } catch (err) {
                    alert('Kesalahan saat mengirim data: ' + err.message);
                } finally {
                    btnSimpanCetak.disabled = false;
                    btnSimpanCetak.innerHTML = origHtml;
                }
            });
        }

        // Cetak Preview Saja
        const btnCetakSaja = document.getElementById('btnCetakSaja');
        if (btnCetakSaja) {
            btnCetakSaja.addEventListener('click', () => {
                const idLabel = document.getElementById('lblBarcodeText')?.innerText?.trim();
                if (idLabel && idLabel !== 'AUTO-GENERATE' && idLabel !== '-') {
                    reprintBarcodeDirect(idLabel);
                } else {
                    alert('Silakan simpan label terlebih dahulu sebelum mencetak.');
                }
            });
        }

        // Filter Dinamis Jenis / Kategori di Form Massal Sesuai Master Kategori
        const bulkJenisFilter = document.getElementById('bulk_jenis_filter');
        const bulkItemSelect  = document.getElementById('bulk_item_id');

        function filterBulkItemsByJenis(selectedJenisId) {
            if (!bulkItemSelect || typeof masterItemsList === 'undefined') return;

            bulkItemSelect.innerHTML = '';

            if (!selectedJenisId || selectedJenisId === '') {
                const opt = document.createElement('option');
                opt.value = '';
                opt.text = '-- Pilih Jenis / Kategori Dahulu --';
                opt.disabled = true;
                opt.selected = true;
                bulkItemSelect.appendChild(opt);
                return;
            }

            const filtered = masterItemsList.filter(it => String(it.jenis_id) === String(selectedJenisId));

            if (filtered.length === 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.text = '-- Tidak ada barang untuk jenis/kategori ini --';
                opt.disabled = true;
                opt.selected = true;
                bulkItemSelect.appendChild(opt);
            } else {
                const optDefault = document.createElement('option');
                optDefault.value = '';
                optDefault.text = '-- Pilih Produk --';
                optDefault.disabled = true;
                optDefault.selected = true;
                bulkItemSelect.appendChild(optDefault);

                filtered.forEach(it => {
                    const opt = document.createElement('option');
                    opt.value = it.id;
                    opt.text = `${it.nama_item} (${it.kode_item})`;
                    opt.dataset.jenisId = it.jenis_id;
                    opt.dataset.nama = it.nama_item;
                    opt.dataset.kode = it.kode_item;
                    bulkItemSelect.appendChild(opt);
                });
            }
        }

        if (bulkJenisFilter) {
            bulkJenisFilter.addEventListener('change', function() {
                filterBulkItemsByJenis(this.value);
            });
            // Jalankan filter saat awal jika sudah ada jenis terpilih
            if (bulkJenisFilter.value) {
                filterBulkItemsByJenis(bulkJenisFilter.value);
            }
        }

        // Input manual quantity agar sync ke tombol pill preset
        const bulkQtyInp = document.getElementById('bulk_quantity');
        if (bulkQtyInp) {
            bulkQtyInp.addEventListener('input', function() {
                const val = parseInt(this.value);
                document.querySelectorAll('.qty-pill-btn').forEach(b => {
                    if (parseInt(b.dataset.qty) === val) {
                        b.classList.add('active');
                    } else {
                        b.classList.remove('active');
                    }
                });
            });
        }

        // -------------------------------------------------------------
        // SUB-MENU 2: GENERATE BARCODE MASSAL (TANPA BERAT)
        // -------------------------------------------------------------
        async function executeGenerateBulkBarcodes() {
            const form = document.getElementById('formBulkBarcode');
            if (!form) return;

            // Validasi wajib pilih jenis / kategori
            const bulkJenisFilterInp = document.getElementById('bulk_jenis_filter');
            if (!bulkJenisFilterInp || !bulkJenisFilterInp.value) {
                alert('Silakan pilih Jenis / Kategori barang terlebih dahulu.');
                bulkJenisFilterInp?.focus();
                return;
            }

            // Validasi kelengkapan form
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                if (typeof form.reportValidity === 'function') form.reportValidity();
                return;
            }

            const bulkItemSelect = document.getElementById('bulk_item_id');
            if (!bulkItemSelect || !bulkItemSelect.value) {
                alert('Silakan pilih Produk terlebih dahulu.');
                bulkItemSelect?.focus();
                return;
            }

            const bulkQtyInp = document.getElementById('bulk_quantity');
            const qtyVal = parseInt(bulkQtyInp?.value || '0', 10);
            if (isNaN(qtyVal) || qtyVal < 1) {
                alert('Jumlah barcode harus minimal 1 pcs.');
                bulkQtyInp?.focus();
                return;
            }

            // Info ringkasan untuk modal konfirmasi
            const pmsSelect = document.getElementById('bulk_pemasok_id');
            const namaPemasok = pmsSelect ? pmsSelect.options[pmsSelect.selectedIndex]?.text : '-';
            const namaItem = bulkItemSelect.options[bulkItemSelect.selectedIndex]?.text || '-';
            const noSJ = document.getElementById('bulk_no_sj')?.value || '-';
            const totalLembar = Math.ceil(qtyVal / 10);

            const confirmRes = await window.showAppConfirm({
                title: 'Konfirmasi Cetak Barcode',
                html: `
                    <div style="text-align: left; font-size: 13.5px;">
                        <p style="margin-bottom: 7px;"><strong>Pemasok:</strong> ${namaPemasok}</p>
                        <p style="margin-bottom: 7px;"><strong>Barang:</strong> ${namaItem}</p>
                        <p style="margin-bottom: 7px;"><strong>Surat Jalan:</strong> <code>${noSJ}</code></p>
                        <p style="margin-bottom: 12px;"><strong>Jumlah Cetak:</strong> <strong style="color: var(--primary);">${qtyVal} Barcode</strong> (${totalLembar} Lembar A4)</p>
                        <div style="background: rgba(79, 70, 229, 0.08); border-left: 4px solid var(--primary); padding: 10px; border-radius: 6px; font-size: 12.5px;">
                            Apakah Anda yakin data sudah benar dan ingin membuat barcode ini sekarang?
                        </div>
                    </div>
                `,
                icon: 'question',
                confirmButtonText: 'Ya, Buat & Cetak',
                cancelButtonText: 'Batal'
            });

            if (!confirmRes.isConfirmed) {
                return;
            }

            const btnGenBulk = document.getElementById('btnGenerateBulk');
            const origHtml = btnGenBulk ? btnGenBulk.innerHTML : '';
            if (btnGenBulk) {
                btnGenBulk.disabled = true;
                btnGenBulk.innerHTML = '<span>Sedang Membuat Barcode...</span>';
            }

            const formData = new FormData(form);

            try {
                const resp = await fetch('index.php?action=generate_bulk_barcodes', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();

                if (data.success && data.labels && data.labels.length) {
                    if (window.scannerAudio) window.scannerAudio.playSuccessBeep();

                    // Otomatis lanjutkan (continue) nomor Surat Jalan ke nomor urutan berikutnya
                    if (data.next_surat_jalan) {
                        const bulkSJ = document.getElementById('bulk_no_sj');
                        if (bulkSJ) bulkSJ.value = data.next_surat_jalan;
                    }

                    const placeholder = document.getElementById('bulkStatusPlaceholder');
                    if (placeholder) placeholder.style.display = 'none';

                    const resList = document.getElementById('bulkResultList');
                    if (resList) {
                        resList.style.display = 'flex';
                        resList.innerHTML = '';

                        // Banner Jembatan Cepat ke Langkah 2 (Timbang)
                        const bridgeBanner = document.createElement('div');
                        bridgeBanner.style.cssText = 'background: #ecfdf5; border: 1.5px solid #10b981; border-radius: 8px; padding: 12px 14px; margin-bottom: 6px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;';
                        bridgeBanner.innerHTML = `
                            <div>
                                <div style="font-weight: 800; font-size: 13.5px; color: #065f46;">✓ ${data.labels.length} Barcode Selesai Dibuat!</div>
                                <div style="font-size: 12px; color: #047857; margin-top: 2px;">Cetak stiker, tempelkan ke keranjang, lalu timbang bobot riilnya.</div>
                            </div>
                            <button type="button" class="btn btn-success btn-sm" onclick="window.location.href='index.php?page=timbang_cetak&tab=scantimbang'" style="font-weight: 800; font-size: 12px; background: #059669; border-color: #059669; cursor: pointer;">
                                Buka Menu Timbang Berat Sekarang ➔
                            </button>
                        `;
                        resList.appendChild(bridgeBanner);

                        // 1. Tampilkan kartu interaktif di antarmuka layar (UI)
                        data.labels.forEach((lbl, idx) => {
                            const card = document.createElement('div');
                            card.style.background = 'var(--bg-muted)';
                            card.style.border = '1px solid var(--border)';
                            card.style.borderRadius = '8px';
                            card.style.padding = '10px 14px';
                            card.style.display = 'flex';
                            card.style.justifyContent = 'space-between';
                            card.style.alignItems = 'center';
                            card.innerHTML = `
                                <div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="badge badge-purple" style="font-size: 11px;">#${idx+1}</span>
                                        <strong style="font-family: 'JetBrains Mono', monospace; font-size: 13px; color: var(--primary);">${lbl.id_label}</strong>
                                        <span class="badge badge-secondary" style="font-size: 11px;">${lbl.kode_pemasok || lbl.kode_mitra || 'PMS'}</span>
                                        <span class="badge badge-success" style="font-size: 11px;">${parseFloat(lbl.berat_netto || 0) > 0 ? parseFloat(lbl.berat_netto).toFixed(2) + ' KG' : 'Siap Alokasi'}</span>
                                    </div>
                                    <div style="font-size: 12.5px; color: var(--text-muted); margin-top: 3px;">
                                        ${lbl.nama_item} • SJ: ${lbl.no_surat_jalan} • Lok: ${lbl.nama_lokasi || 'Gudang'}
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="goToScanWeigh('${lbl.id_label}')" style="font-size: 11.5px; font-weight: 700;">
                                        ⚖️ Timbang
                                    </button>
                                </div>
                            `;
                            resList.appendChild(card);
                        });
                    }

                    const printContainer = document.getElementById('bulkPrintContainer');
                    if (printContainer) {
                        printContainer.innerHTML = '';

                        // 2. Susun container cetak: Format Stiker 70x50 mm (Urutan Terbesar ke Terkecil)
                        const totalBoxes = data.labels.length;
                        const indexedLabels = data.labels.map((lbl, idx) => ({
                            lbl: lbl,
                            boxIdx: idx + 1
                        })).reverse();

                        indexedLabels.forEach(item => {
                            const sheetDiv = document.createElement('div');
                            sheetDiv.className = 'bulk-print-sheet single-thermal-sheet';
                            sheetDiv.style.cssText = 'width: 70mm; height: 50mm; display: flex; flex-direction: column; justify-content: center; align-items: flex-start; padding: 0 0 0 1.0mm; margin: 0 auto; box-sizing: border-box;';

                            sheetDiv.innerHTML = renderSquareBarcodeCardHtml(item.lbl, item.boxIdx, totalBoxes);
                            printContainer.appendChild(sheetDiv);
                        });
                        setPrintPageFormat('single');

                        // Tampilkan tombol Pratinjau & Cetak di UI
                        const btnPreview = document.getElementById('btnPreviewSheet');
                        if (btnPreview) btnPreview.style.display = 'inline-flex';

                        const btnCetakAll = document.getElementById('btnCetakSemuaBulk');
                        if (btnCetakAll) {
                            btnCetakAll.style.display = 'inline-flex';
                            btnCetakAll.innerHTML = `<i data-lucide="printer" style="width: 15px; height: 15px;"></i><span>Cetak Barcode (${totalBoxes} Pcs)</span>`;
                        }

                        const descEl = document.getElementById('bulkResultDesc');
                        if (descEl) {
                            descEl.innerHTML = `Total <strong>${data.labels.length} barcode</strong> siap cetak dalam format stiker 70×50 mm. Silakan klik tombol <strong>"Cetak Barcode"</strong> di bawah untuk mencetak.`;
                        }

                        if (window.lucide) window.lucide.createIcons();

                        if (window.showToast) {
                            window.showToast(`Berhasil membuat ${data.labels.length} barcode kemasan (${totalSheets} lembar PDF)! Silakan cetak melalui tombol Cetak PDF.`, 'success');
                        }
                    }

                } else {
                    alert('Gagal membuat barcode: ' + (data.message || 'Terjadi kesalahan'));
                }
            } catch (e) {
                alert('Kesalahan jaringan: ' + e.message);
            } finally {
                if (btnGenBulk) {
                    btnGenBulk.disabled = false;
                    btnGenBulk.innerHTML = origHtml;
                    if (window.lucide) window.lucide.createIcons();
                }
            }
        }

        // Form submit handler: Menangani tombol Enter di input form maupun klik submit
        const formBulkBarcode = document.getElementById('formBulkBarcode');
        if (formBulkBarcode) {
            formBulkBarcode.addEventListener('submit', function(e) {
                e.preventDefault();
                executeGenerateBulkBarcodes();
            });
        }

        const btnGenBulk = document.getElementById('btnGenerateBulk');
        if (btnGenBulk) {
            btnGenBulk.addEventListener('click', function(e) {
                e.preventDefault();
                executeGenerateBulkBarcodes();
            });
        }

        if (bulkQtyInp) {
            bulkQtyInp.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    executeGenerateBulkBarcodes();
                }
            });
        }

        // -------------------------------------------------------------
        // SUB-MENU 3: SCAN BARCODE & TIMBANG BERAT
        // -------------------------------------------------------------
        let currentScannedLabel = null;
        const scanBarcodeInp = document.getElementById('scanBarcodeWeighInput');
        const btnLookup = document.getElementById('btnLookupBarcode');
        const cardItemScanned = document.getElementById('cardItemScanned');
        const formWeighArea = document.getElementById('formWeighArea');
        const scanWeightVal = document.getElementById('scanWeightValue');
        const btnSimpanBerat = document.getElementById('btnSimpanBerat');

        // Elemen Pemilihan Surat Jalan & List Barcode Belum Ditimbang
        const selectWeighSJ = document.getElementById('selectWeighSJ');
        const btnRefreshSJList = document.getElementById('btnRefreshSJList');
        const btnSelesaiTimbangSJ = document.getElementById('btnSelesaiTimbangSJ');
        const btnCetakSJPenerimaan = document.getElementById('btnCetakSJPenerimaan');
        const btnNoticeSelesaiTimbang = document.getElementById('btnNoticeSelesaiTimbang');
        const btnCetakSJNotice = document.getElementById('btnCetakSJNotice');
        const containerSJUnweighed = document.getElementById('containerSJUnweighed');
        const infoSJNo = document.getElementById('infoSJNo');
        const infoSJItem = document.getElementById('infoSJItem');
        const infoSJPemasok = document.getElementById('infoSJPemasok');
        const badgeSJTotal = document.getElementById('badgeSJTotal');
        const badgeSJSisa = document.getElementById('badgeSJSisa');
        const badgeSJSelesai = document.getElementById('badgeSJSelesai');
        let activeWeighSJData = null;
        let isCurrentSJTimbangSelesai = false;

        // Sinkronisasi status tombol Selesai Timbang dan Cetak Surat Jalan Penerimaan
        function syncSJTimbangButtons(noSj, sudahCount, isSelesai) {
            const hasSJ = !!(noSj && noSj.trim());
            const sudahDitimbang = parseInt(sudahCount || 0, 10);
            isCurrentSJTimbangSelesai = !!(isSelesai);

            const printUrl = hasSJ ? ('index.php?page=surat_jalan_penerimaan_print&no_surat_jalan=' + encodeURIComponent(noSj)) : '#';

            if (!hasSJ) {
                // Tidak ada Surat Jalan dipilih
                isCurrentSJTimbangSelesai = false;
                if (btnSelesaiTimbangSJ) {
                    btnSelesaiTimbangSJ.disabled = true;
                    btnSelesaiTimbangSJ.style.background = '#cbd5e1';
                    btnSelesaiTimbangSJ.style.borderColor = '#cbd5e1';
                    btnSelesaiTimbangSJ.style.color = '#64748b';
                    btnSelesaiTimbangSJ.style.cursor = 'not-allowed';
                    btnSelesaiTimbangSJ.style.boxShadow = 'none';
                    btnSelesaiTimbangSJ.title = 'Pilih Surat Jalan terlebih dahulu';
                    btnSelesaiTimbangSJ.innerHTML = '<i data-lucide="check-circle" style="width: 14px; height: 14px;"></i> <span>Selesai Timbang</span>';
                }
                if (btnNoticeSelesaiTimbang) {
                    btnNoticeSelesaiTimbang.style.display = 'none';
                }
                if (btnCetakSJPenerimaan) {
                    btnCetakSJPenerimaan.removeAttribute('href');
                    btnCetakSJPenerimaan.style.background = '#64748b';
                    btnCetakSJPenerimaan.style.borderColor = '#64748b';
                    btnCetakSJPenerimaan.style.color = '#ffffff';
                    btnCetakSJPenerimaan.style.opacity = '0.55';
                    btnCetakSJPenerimaan.style.cursor = 'not-allowed';
                    btnCetakSJPenerimaan.style.boxShadow = 'none';
                    btnCetakSJPenerimaan.title = 'Pilih Surat Jalan terlebih dahulu untuk mencetak dokumen';
                }
                if (btnCetakSJNotice) {
                    btnCetakSJNotice.removeAttribute('href');
                    btnCetakSJNotice.style.background = '#64748b';
                    btnCetakSJNotice.style.borderColor = '#64748b';
                    btnCetakSJNotice.style.color = '#ffffff';
                    btnCetakSJNotice.style.opacity = '0.55';
                    btnCetakSJNotice.style.cursor = 'not-allowed';
                }
            } else if (isCurrentSJTimbangSelesai) {
                // Surat Jalan telah dinyatakan SELESAI TIMBANG: Tombol cetak AKTIF & BISA DIKLIK!
                if (btnSelesaiTimbangSJ) {
                    btnSelesaiTimbangSJ.disabled = true;
                    btnSelesaiTimbangSJ.style.background = '#065f46';
                    btnSelesaiTimbangSJ.style.borderColor = '#065f46';
                    btnSelesaiTimbangSJ.style.color = '#ffffff';
                    btnSelesaiTimbangSJ.style.cursor = 'default';
                    btnSelesaiTimbangSJ.style.boxShadow = 'none';
                    btnSelesaiTimbangSJ.title = 'Penimbangan Surat Jalan ini telah dinyatakan selesai';
                    btnSelesaiTimbangSJ.innerHTML = '<i data-lucide="check-check" style="width: 14px; height: 14px; color: #6ee7b7;"></i> <span>Timbangan Selesai</span>';
                }
                if (btnNoticeSelesaiTimbang) {
                    btnNoticeSelesaiTimbang.style.display = 'none';
                }
                if (btnCetakSJPenerimaan) {
                    btnCetakSJPenerimaan.href = printUrl;
                    btnCetakSJPenerimaan.style.background = '#047857';
                    btnCetakSJPenerimaan.style.borderColor = '#047857';
                    btnCetakSJPenerimaan.style.color = '#ffffff';
                    btnCetakSJPenerimaan.style.opacity = '1';
                    btnCetakSJPenerimaan.style.cursor = 'pointer';
                    btnCetakSJPenerimaan.style.boxShadow = '0 2px 8px rgba(4, 120, 87, 0.4)';
                    btnCetakSJPenerimaan.title = 'Cetak Dokumen Resmi Surat Jalan Penerimaan Masuk untuk ' + noSj;
                }
                if (btnCetakSJNotice) {
                    btnCetakSJNotice.href = printUrl;
                    btnCetakSJNotice.style.background = '#047857';
                    btnCetakSJNotice.style.borderColor = '#047857';
                    btnCetakSJNotice.style.color = '#ffffff';
                    btnCetakSJNotice.style.opacity = '1';
                    btnCetakSJNotice.style.cursor = 'pointer';
                }
            } else {
                // Surat Jalan terpilih, BELUM diklik Selesai Timbang
                if (sudahDitimbang > 0) {
                    // Ada barcode yang selesai discan & diisi timbangannya -> Tombol "Selesai Timbang" AKTIF!
                    if (btnSelesaiTimbangSJ) {
                        btnSelesaiTimbangSJ.disabled = false;
                        btnSelesaiTimbangSJ.style.background = '#059669';
                        btnSelesaiTimbangSJ.style.borderColor = '#059669';
                        btnSelesaiTimbangSJ.style.color = '#ffffff';
                        btnSelesaiTimbangSJ.style.cursor = 'pointer';
                        btnSelesaiTimbangSJ.style.boxShadow = '0 2px 8px rgba(5, 150, 105, 0.4)';
                        btnSelesaiTimbangSJ.title = 'Klik di sini untuk menyelesaikan proses timbang agar tombol cetak dapat digunakan';
                        btnSelesaiTimbangSJ.innerHTML = '<i data-lucide="check-circle" style="width: 14px; height: 14px;"></i> <span>Selesai Timbang</span>';
                    }
                    if (btnNoticeSelesaiTimbang) {
                        btnNoticeSelesaiTimbang.style.display = 'inline-flex';
                    }
                } else {
                    // Belum ada keranjang yang ditimbang sama sekali
                    if (btnSelesaiTimbangSJ) {
                        btnSelesaiTimbangSJ.disabled = true;
                        btnSelesaiTimbangSJ.style.background = '#cbd5e1';
                        btnSelesaiTimbangSJ.style.borderColor = '#cbd5e1';
                        btnSelesaiTimbangSJ.style.color = '#64748b';
                        btnSelesaiTimbangSJ.style.cursor = 'not-allowed';
                        btnSelesaiTimbangSJ.style.boxShadow = 'none';
                        btnSelesaiTimbangSJ.title = 'Scan barcode dan isi berat timbangan terlebih dahulu';
                        btnSelesaiTimbangSJ.innerHTML = '<i data-lucide="check-circle" style="width: 14px; height: 14px;"></i> <span>Selesai Timbang</span>';
                    }
                    if (btnNoticeSelesaiTimbang) {
                        btnNoticeSelesaiTimbang.style.display = 'none';
                    }
                }

                // TOMBOL CETAK SURAT JALAN PENERIMAAN WAJIB TERKUNCI (TIDAK BISA DIKLIK) SEBELUM SELESAI TIMBANG
                if (btnCetakSJPenerimaan) {
                    btnCetakSJPenerimaan.removeAttribute('href');
                    btnCetakSJPenerimaan.style.background = '#64748b';
                    btnCetakSJPenerimaan.style.borderColor = '#64748b';
                    btnCetakSJPenerimaan.style.color = '#ffffff';
                    btnCetakSJPenerimaan.style.opacity = '0.55';
                    btnCetakSJPenerimaan.style.cursor = 'not-allowed';
                    btnCetakSJPenerimaan.style.boxShadow = 'none';
                    btnCetakSJPenerimaan.title = sudahDitimbang > 0 
                        ? 'Tombol cetak terkunci. Silakan klik tombol "✓ Selesai Timbang" terlebih dahulu!' 
                        : 'Tombol cetak terkunci. Scan barcode dan isi berat timbangan lalu klik "Selesai Timbang".';
                }
                if (btnCetakSJNotice) {
                    btnCetakSJNotice.removeAttribute('href');
                    btnCetakSJNotice.style.background = '#64748b';
                    btnCetakSJNotice.style.borderColor = '#64748b';
                    btnCetakSJNotice.style.color = '#ffffff';
                    btnCetakSJNotice.style.opacity = '0.55';
                    btnCetakSJNotice.style.cursor = 'not-allowed';
                }
            }

            if (window.lucide) window.lucide.createIcons();
        }

        // Handler saat tombol Cetak Surat Jalan Penerimaan diklik
        window.handleCetakSJClick = function(e) {
            if (!isCurrentSJTimbangSelesai) {
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }

                const noSj = selectWeighSJ ? selectWeighSJ.value.trim() : '';
                if (!noSj) {
                    alert('⚠️ Silakan pilih Surat Jalan Penerimaan terlebih dahulu.');
                    return false;
                }

                let sudah = activeWeighSJData ? (activeWeighSJData.sudah_ditimbang || 0) : 0;
                if (badgeSJSelesai && badgeSJSelesai.dataset.count) {
                    sudah = parseInt(badgeSJSelesai.dataset.count, 10);
                }

                if (sudah <= 0) {
                    alert('⚠️ Tombol Cetak Surat Jalan Penerimaan Tidak Dapat Diklik!\n\n' +
                          'Belum ada keranjang yang ditimbang untuk Surat Jalan ini.\n' +
                          'Silakan scan barcode keranjang dan masukkan berat timbangan terlebih dahulu.');
                } else {
                    alert('⚠️ Tombol Cetak Surat Jalan Penerimaan Masih Terkunci!\n\n' +
                          'Anda telah menimbang keranjang, tetapi belum menyelesaikan sesi timbang.\n' +
                          'Silakan klik tombol "✓ Selesai Timbang" terlebih dahulu untuk membuka akses cetak dokumen resmi Surat Jalan Penerimaan.');
                }
                return false;
            }
        };

        // Handler saat tombol Selesai Timbang diklik oleh operator
        window.handleSelesaiTimbangClick = async function() {
            const noSj = selectWeighSJ ? selectWeighSJ.value.trim() : '';
            if (!noSj) {
                alert('⚠️ Silakan pilih Surat Jalan terlebih dahulu.');
                return;
            }

            let sudah = activeWeighSJData ? (activeWeighSJData.sudah_ditimbang || 0) : 0;
            let sisa = activeWeighSJData ? (activeWeighSJData.belum_ditimbang || 0) : 0;
            if (badgeSJSelesai && badgeSJSelesai.dataset.count) {
                sudah = parseInt(badgeSJSelesai.dataset.count, 10);
            }
            if (badgeSJSisa && badgeSJSisa.dataset.count) {
                sisa = parseInt(badgeSJSisa.dataset.count, 10);
            }

            if (sudah <= 0) {
                alert('⚠️ Belum ada keranjang yang ditimbang untuk Surat Jalan ini.\n\nSilakan scan barcode dan isi berat timbangan terlebih dahulu!');
                return;
            }

            let htmlPesan = `<div style="text-align: left; font-size: 13.5px;">` +
                `<p style="margin-bottom: 8px;"><strong>Surat Jalan:</strong> <code>${noSj}</code></p>` +
                `<p style="margin-bottom: 8px;">• Total Sudah Ditimbang: <strong style="color: #10b981;">${sudah}</strong> Keranjang</p>`;

            if (sisa > 0) {
                htmlPesan += `<p style="margin-bottom: 12px; color: #f59e0b;">• Sisa Belum Ditimbang: <strong>${sisa}</strong> Keranjang</p>` +
                    `<div style="background: rgba(245, 158, 11, 0.1); border-left: 4px solid #f59e0b; padding: 10px; border-radius: 6px; margin-top: 10px; font-size: 13px;">` +
                    `⚠️ <strong>Perhatian:</strong> Masih ada <strong>${sisa} keranjang</strong> yang belum ditimbang pada Surat Jalan ini.<br>` +
                    `Apakah Anda yakin ingin menyelesaikan proses timbang sekarang dan mengaktifkan cetak surat jalan penerimaan?` +
                    `</div></div>`;
            } else {
                htmlPesan += `<div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; padding: 10px; border-radius: 6px; margin-top: 10px; font-size: 13px;">` +
                    `✅ <strong>Seluruh keranjang telah selesai ditimbang.</strong><br>` +
                    `Apakah Anda yakin ingin menyelesaikan penimbangan dan mengaktifkan cetak surat jalan penerimaan?` +
                    `</div></div>`;
            }

            const confirmRes = await window.showAppConfirm({
                title: 'Konfirmasi Selesai Timbang',
                html: htmlPesan,
                icon: sisa > 0 ? 'warning' : 'question',
                confirmButtonText: '✓ Ya, Selesaikan Sesi',
                cancelButtonText: 'Kembali Timbang'
            });

            if (!confirmRes.isConfirmed) {
                return;
            }

            const btn = document.getElementById('btnSelesaiTimbangSJ');
            const origHtml = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="width: 13px; height: 13px; border-width: 2px;"></span> Menyimpan...`;
            }

            try {
                const formData = new FormData();
                formData.append('no_surat_jalan', noSj);

                const resp = await fetch('index.php?action=complete_weighing_sj', {
                    method: 'POST',
                    body: formData
                });
                const res = await resp.json();

                if (res.success) {
                    isCurrentSJTimbangSelesai = true;
                    if (activeWeighSJData) {
                        activeWeighSJData.is_timbang_selesai = 1;
                    }

                    // Sinkronkan UI tombol menjadi aktif
                    syncSJTimbangButtons(noSj, sudah, true);

                    alert(`✅ SUKSES!\n\nProses timbang untuk Surat Jalan "${noSj}" telah dinyatakan SELESAI.\n\nTombol "🖨️ Cetak Surat Jalan Penerimaan" sekarang telah AKTIF dan siap dicetak.`);
                } else {
                    alert('Gagal menyelesaikan timbang: ' + (res.message || 'Terjadi kesalahan sistem'));
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = origHtml;
                    }
                }
            } catch (err) {
                alert('Kesalahan koneksi saat menyelesaikan timbang: ' + err.message);
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                }
            }
        };

        // Memuat detail ringkasan Surat Jalan terpilih (daftar barcode dipindah ke riwayat)
        async function loadSuratJalanWeighDetail(noSj) {
            if (!noSj) {
                activeWeighSJData = null;
                if (containerSJUnweighed) containerSJUnweighed.style.display = 'none';
                syncSJTimbangButtons('', 0, false);
                return;
            }

            // Inisialisasi awal terkunci saat loading detail
            syncSJTimbangButtons(noSj, 0, false);

            try {
                const resp = await fetch('index.php?action=get_sj_weigh_detail&no_surat_jalan=' + encodeURIComponent(noSj));
                const res = await resp.json();

                if (res.success && res.data) {
                    const data = res.data;
                    activeWeighSJData = data;
                    if (containerSJUnweighed) containerSJUnweighed.style.display = 'block';

                    if (infoSJNo) infoSJNo.innerText = data.no_surat_jalan;
                    if (infoSJItem) infoSJItem.innerText = data.nama_item;
                    if (infoSJPemasok) infoSJPemasok.innerText = `${data.kode_pemasok} - ${data.nama_pemasok}`;

                    if (badgeSJTotal) {
                        badgeSJTotal.dataset.count = data.total_pcs;
                        badgeSJTotal.innerText = `Total: ${data.total_pcs} Pcs`;
                    }
                    if (badgeSJSisa) {
                        badgeSJSisa.dataset.count = data.belum_ditimbang;
                        badgeSJSisa.innerText = `⏳ Belum: ${data.belum_ditimbang} Pcs`;
                    }
                    if (badgeSJSelesai) {
                        badgeSJSelesai.dataset.count = data.sudah_ditimbang;
                        badgeSJSelesai.innerText = `✓ Sudah: ${data.sudah_ditimbang} Pcs (${parseFloat(data.total_berat).toFixed(2)} KG)`;
                    }

                    if (data.belum_ditimbang === 0) {
                        if (allWeighedSuccessNotice) allWeighedSuccessNotice.style.display = 'block';
                    } else {
                        if (allWeighedSuccessNotice) allWeighedSuccessNotice.style.display = 'none';
                    }

                    // Sinkronkan status tombol Selesai Timbang dan Cetak Surat Jalan Penerimaan
                    syncSJTimbangButtons(data.no_surat_jalan, data.sudah_ditimbang, data.is_timbang_selesai == 1);

                } else {
                    alert('Gagal mengambil data Surat Jalan: ' + (res.message || 'Terjadi kesalahan'));
                }
            } catch (e) {
                console.error('Error loadSuratJalanWeighDetail:', e);
            }
        }

        // =============================================================
        // MODAL DAFTAR BARCODE KERANJANG SURAT JALAN (RIWAYAT PENERIMAAN)
        // =============================================================
        let currentModalSJData = null;

        window.openSJBarcodeHistoryModal = async function(noSj) {
            const targetSJ = (noSj || (activeWeighSJData ? activeWeighSJData.no_surat_jalan : '')).trim();
            if (!targetSJ) {
                alert('Silakan pilih Surat Jalan terlebih dahulu.');
                return;
            }

            const modal = document.getElementById('modalSJBarcodeList');
            const tbody = document.getElementById('modalSJTableBody');
            if (modal) modal.style.display = 'flex';
            if (tbody) tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 36px; color: var(--text-muted); font-size: 13px;">Memuat data box Surat Jalan...</td></tr>';

            try {
                const resp = await fetch('index.php?action=get_sj_weigh_detail&no_surat_jalan=' + encodeURIComponent(targetSJ));
                const res = await resp.json();

                if (res.success && res.data) {
                    currentModalSJData = res.data;
                    const noEl = document.getElementById('modalSJNoText');
                    const itemEl = document.getElementById('modalSJItemText');
                    const pmsEl = document.getElementById('modalSJPemasokText');
                    
                    if (noEl) noEl.innerText = res.data.no_surat_jalan;
                    if (itemEl) itemEl.innerText = `${res.data.nama_item || '-'} (${res.data.kode_item || '-'})`;
                    if (pmsEl) pmsEl.innerText = `${res.data.kode_pemasok || 'PMS'} - ${res.data.nama_pemasok || '-'}`;
                    
                    const btnCetakSJ = document.getElementById('btnModalCetakSuratJalan');
                    if (btnCetakSJ) {
                        btnCetakSJ.href = 'index.php?page=surat_jalan_penerimaan_print&no_surat_jalan=' + encodeURIComponent(res.data.no_surat_jalan);
                    }

                    renderModalBoxesTable(res.data.all_labels || []);
                } else {
                    if (tbody) tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--danger); padding: 30px;">Gagal memuat: ${res.message || 'Data tidak ditemukan'}</td></tr>`;
                }
            } catch (err) {
                if (tbody) tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--danger); padding: 30px;">Kesalahan jaringan: ${err.message}</td></tr>`;
            }

            if (window.lucide) window.lucide.createIcons();
        };

        window.closeSJBarcodeListModal = function() {
            const modal = document.getElementById('modalSJBarcodeList');
            if (modal) modal.style.display = 'none';
        };

        window.renderModalBoxesTable = function(labels) {
            const tbody = document.getElementById('modalSJTableBody');
            if (!tbody) return;

            // Baris total: hanya bila lebih dari 1 box (1 box = baris itu sendiri)
            const foot = document.getElementById('modalSJTableFoot');
            if (foot) {
                const n = (labels || []).length;
                const sum = (labels || []).reduce((a, l) => a + parseFloat(l.berat_netto || 0), 0);
                foot.style.display = n >= 2 ? '' : 'none';
                const c = document.getElementById('modalSJTotalCount');
                const v = document.getElementById('modalSJTotalNetto');
                if (c) c.innerText = n;
                if (v) v.innerText = sum.toFixed(2) + ' kg';
            }

            if (!labels || !labels.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 36px 20px; color: var(--text-muted);">
                            <div style="font-weight: 700; font-size: 13.5px;">Belum ada box pada Surat Jalan ini</div>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = '';
            labels.forEach((lbl, idx) => {
                const netto = parseFloat(lbl.berat_netto || 0);
                const tr = document.createElement('tr');
                tr.style.borderBottom = '1px solid var(--border, #e2e8f0)';
                tr.innerHTML = `
                    <td style="color: var(--text-muted, #64748b); font-size: 12px; text-align: center;">${idx + 1}</td>
                    <td>
                        <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--primary, #2563eb);">
                            ${lbl.id_label}
                        </strong>
                    </td>
                    <td>
                        <strong style="color: var(--text-main, #0f172a); font-size: 13px;">${lbl.nama_item || currentModalSJData?.nama_item || '-'}</strong>
                        ${lbl.nama_jenis ? `<span style="font-size: 11px; color: var(--text-muted, #64748b); margin-left: 4px;">(${lbl.nama_jenis})</span>` : ''}
                    </td>
                    <td style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; font-size: 13px; color: #10b981;">
                        ${netto.toFixed(2)} kg
                    </td>
                    <td style="text-align: center;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="reprintBarcodeDirect('${lbl.id_label}')" style="padding: 3px 10px; font-size: 11.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; border-radius: 6px; cursor: pointer;" title="Cetak Barcode Box Ini Saja (Format Stiker 70x50 mm)">
                            <i data-lucide="printer" style="width: 12px; height: 12px;"></i>
                            <span>Cetak</span>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            if (window.lucide) window.lucide.createIcons();
        };

        window.printAllModalBarcodes = function() {
            if (!currentModalSJData || !currentModalSJData.no_surat_jalan) {
                alert('Data Surat Jalan tidak ditemukan.');
                return;
            }
            reprintGroupSJ(currentModalSJData.no_surat_jalan, 'all');
        };

        window.quickWeighFromModal = function(barcode, noSj) {
            closeSJBarcodeListModal();

            if (selectWeighSJ && noSj) {
                selectWeighSJ.value = noSj;
                loadSuratJalanWeighDetail(noSj);
            }

            if (scanBarcodeInp) {
                scanBarcodeInp.value = barcode;
                scanBarcodeInp.focus();
                performBarcodeLookup();
            }
        };

        // Quick Weigh saat barcode diklik
        window.quickWeighBarcode = function(barcode) {
            if (!scanBarcodeInp) return;
            scanBarcodeInp.value = barcode;
            performBarcodeLookup();
        };

        // Event listener saat dropdown Surat Jalan diganti
        if (selectWeighSJ) {
            selectWeighSJ.addEventListener('change', function() {
                const noSj = this.value.trim();
                loadSuratJalanWeighDetail(noSj);
                if (scanBarcodeInp) {
                    scanBarcodeInp.value = '';
                    scanBarcodeInp.focus();
                }
            });
        }

        // Tombol refresh daftar Surat Jalan
        if (btnRefreshSJList) {
            btnRefreshSJList.addEventListener('click', async function() {
                const origHtml = btnRefreshSJList.innerHTML;
                btnRefreshSJList.disabled = true;
                btnRefreshSJList.innerHTML = '<span>Memuat...</span>';

                try {
                    const resp = await fetch('index.php?action=get_active_sj_list');
                    const res = await resp.json();
                    if (res.success && res.list && selectWeighSJ) {
                        const currentSelected = selectWeighSJ.value;
                        if (res.list.length === 0) {
                            selectWeighSJ.innerHTML = '<option value="">-- Tidak ada Surat Jalan yang menunggu penimbangan (Semua telah selesai) --</option>';
                        } else {
                            selectWeighSJ.innerHTML = '<option value="">-- Pilih Surat Jalan Penerimaan (Klik untuk Memilih) --</option>';
                            res.list.forEach(sj => {
                                const total = parseInt(sj.total_pcs || 0, 10);
                                const opt = document.createElement('option');
                                opt.value = sj.no_surat_jalan;
                                opt.text = `${sj.no_surat_jalan} — ${sj.nama_item} | ${sj.nama_pemasok} (Total: ${total} Barcode)`;
                                if (sj.no_surat_jalan === currentSelected) {
                                    opt.selected = true;
                                }
                                selectWeighSJ.appendChild(opt);
                            });
                        }

                        if (currentSelected) {
                            loadSuratJalanWeighDetail(currentSelected);
                        }
                    }
                } catch (e) {
                    alert('Gagal menyegarkan Surat Jalan: ' + e.message);
                } finally {
                    btnRefreshSJList.disabled = false;
                    btnRefreshSJList.innerHTML = origHtml;
                    if (window.lucide) window.lucide.createIcons();
                }
            });
        }

        window.recalcScanWeight = function() {
            if (!currentScannedLabel) return;
            const berat = parseFloat(scanWeightVal.value) || 0;
            const key = currentScannedLabel.mitra_id + '_' + currentScannedLabel.item_id;
            const harga = pricesLookup[key] || 0;

            const scanHrg = document.getElementById('scanHargaPerKg');
            if (scanHrg) scanHrg.innerText = 'Rp ' + formatRupiah(harga);

            const total = Math.round(berat * harga);
            const scanTot = document.getElementById('scanTotalNilai');
            if (scanTot) scanTot.innerText = 'Rp ' + formatRupiah(total);
        };

        window.setScanWeight = function(kg) {
            if (!scanWeightVal) return;
            scanWeightVal.value = kg.toFixed(2);
            window.recalcScanWeight();
            if (window.scannerAudio) window.scannerAudio.playSuccessBeep();
        };

        async function performBarcodeLookup() {
            const barcode = scanBarcodeInp.value.trim();
            if (!barcode) {
                alert('Silakan scan atau masukkan ID Barcode terlebih dahulu');
                scanBarcodeInp.focus();
                return;
            }

            try {
                btnLookup.disabled = true;
                btnLookup.innerText = 'Mencari...';

                const resp = await fetch('index.php?action=verify_label&barcode=' + encodeURIComponent(barcode));
                const data = await resp.json();

                if (data.success && data.label) {
                    const currentWeight = parseFloat(data.label.berat_netto || 0);

                    // OTOMATIS PILIH SURAT JALAN SESUAI BARCODE YANG DISCAN:
                    // Tidak perlu operator memilih manual atau terkena blokir mismatch;
                    // Surat Jalan otomatis terpilih dan tersinkronisasi langsung dengan barcode keranjang yang discan.
                    const selectedSJ = selectWeighSJ ? selectWeighSJ.value.trim() : '';
                    const labelSJ = (data.label.no_surat_jalan || data.label.batch_no || '').trim();

                    if (labelSJ !== '') {
                        if (selectWeighSJ) {
                            let optionExists = false;
                            for (let i = 0; i < selectWeighSJ.options.length; i++) {
                                if (selectWeighSJ.options[i].value === labelSJ) {
                                    optionExists = true;
                                    break;
                                }
                            }

                            // Jika Surat Jalan belum ada di daftar dropdown (misal baru dibuat atau filter berubah), tambahkan opsi otomatis
                            if (!optionExists) {
                                const opt = document.createElement('option');
                                opt.value = labelSJ;
                                const pmsNama = data.label.nama_pemasok || data.label.nama_mitra || 'Pemasok';
                                const itemNama = data.label.nama_item || 'Barang';
                                opt.text = `${labelSJ} — ${itemNama} | ${pmsNama}`;
                                selectWeighSJ.appendChild(opt);
                            }

                            if (selectedSJ !== labelSJ) {
                                selectWeighSJ.value = labelSJ;
                                loadSuratJalanWeighDetail(labelSJ);
                            } else if (!activeWeighSJData) {
                                loadSuratJalanWeighDetail(labelSJ);
                            }
                        }
                    }

                    // PENCEGAHAN DUPLIKASI DATA:
                    // Jika barcode sudah discan dan ditimbang sebelumnya (berat_netto > 0),
                    // tolak scan dan munculkan popup bahwa barcode sudah digunakan tidak dapat discan lagi.
                    if (currentWeight > 0) {
                        if (window.scannerAudio) window.scannerAudio.playErrorBuzz();
                        currentScannedLabel = null;
                        cardItemScanned.style.display = 'none';
                        formWeighArea.style.display = 'none';
                        scanBarcodeInp.value = '';
                        
                        showBarcodeAlreadyUsedModal(data.label, 'SUDAH DITIMBANG');
                        return;
                    }

                    // Cek jika barcode sudah berstatus KELUAR
                    if (data.label.status === 'KELUAR') {
                        if (window.scannerAudio) window.scannerAudio.playErrorBuzz();
                        currentScannedLabel = null;
                        cardItemScanned.style.display = 'none';
                        formWeighArea.style.display = 'none';
                        scanBarcodeInp.value = '';
                        
                        showBarcodeAlreadyUsedModal(data.label, 'SUDAH KELUAR');
                        return;
                    }

                    // Cek jika barcode berstatus VOID
                    if (data.label.status === 'VOID') {
                        if (window.scannerAudio) window.scannerAudio.playErrorBuzz();
                        currentScannedLabel = null;
                        cardItemScanned.style.display = 'none';
                        formWeighArea.style.display = 'none';
                        scanBarcodeInp.value = '';
                        
                        showBarcodeAlreadyUsedModal(data.label, 'VOID');
                        return;
                    }

                    currentScannedLabel = data.label;
                    if (window.scannerAudio) window.scannerAudio.playSuccessBeep();

                    // Tampilkan kartu info produk
                    cardItemScanned.style.display = 'block';
                    formWeighArea.style.display = 'block';

                    const pmsKode = data.label.kode_pemasok || data.label.kode_mitra || 'PMS';
                    const pmsNama = data.label.nama_pemasok || data.label.nama_mitra || 'Pemasok';
                    document.getElementById('scanBadgeMitra').innerText = `${pmsKode} - ${pmsNama}`;
                    document.getElementById('scanItemTitle').innerText = `${data.label.nama_item} (${data.label.id_label})`;
                    document.getElementById('scanSJText').innerText = data.label.no_surat_jalan || data.label.batch_no || '-';

                    const badgeContainer = document.getElementById('scanStatusBadgeContainer');
                    badgeContainer.innerHTML = `<span class="badge badge-warning">⚠ Belum Ditimbang (0.00 KG)</span>`;
                    scanWeightVal.value = '';

                    window.recalcScanWeight();
                    setTimeout(() => scanWeightVal.focus(), 150);

                } else {
                    if (window.scannerAudio) window.scannerAudio.playErrorBuzz();
                    alert(data.message || 'Barcode tidak ditemukan di sistem!');
                    scanBarcodeInp.select();
                }
            } catch (err) {
                alert('Gagal mengambil data barcode: ' + err.message);
            } finally {
                btnLookup.disabled = false;
                btnLookup.innerText = 'Cari / Verifikasi';
            }
        }

        if (btnLookup) btnLookup.addEventListener('click', performBarcodeLookup);

        if (scanBarcodeInp) {
            scanBarcodeInp.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    performBarcodeLookup();
                }
            });
        }

        if (scanWeightVal) {
            scanWeightVal.addEventListener('input', window.recalcScanWeight);
            scanWeightVal.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (btnSimpanBerat) btnSimpanBerat.click();
                }
            });
        }

        // Tombol Simpan Berat & Cetak Ulang Label
        if (btnSimpanBerat) {
            btnSimpanBerat.addEventListener('click', async function() {
                if (!currentScannedLabel) {
                    alert('Belum ada barcode yang discan');
                    return;
                }
                const berat = parseFloat(scanWeightVal.value) || 0;
                if (berat <= 0) {
                    alert('Bobot timbangan harus lebih besar dari 0 KG');
                    scanWeightVal.focus();
                    return;
                }

                btnSimpanBerat.disabled = true;
                const origText = btnSimpanBerat.innerHTML;
                btnSimpanBerat.innerHTML = '<span>Menyimpan Bobot...</span>';

                try {
                    const formData = new FormData();
                    formData.append('barcode', currentScannedLabel.id_label);
                    formData.append('berat_netto', berat);

                    const resp = await fetch('index.php?action=update_weight', {
                        method: 'POST',
                        body: formData
                    });
                    const res = await resp.json();

                    if (res.success && res.label) {
                        if (window.scannerAudio) window.scannerAudio.playSuccessBeep();

                        // Tambahkan ke riwayat sesi penimbangan
                        document.getElementById('sessionWeighedPlaceholder').style.display = 'none';
                        const list = document.getElementById('sessionWeighedList');
                        list.style.display = 'flex';

                        const hrg = parseFloat(res.label.harga_per_kg || 0);
                        const tot = parseFloat(res.label.total_nilai || (hrg * berat));

                        const itemDiv = document.createElement('div');
                        itemDiv.style.background = 'var(--bg-muted)';
                        itemDiv.style.border = '1.5px solid #10b981';
                        itemDiv.style.borderRadius = '8px';
                        itemDiv.style.padding = '10px 14px';
                        itemDiv.style.display = 'flex';
                        itemDiv.style.justifyContent = 'space-between';
                        itemDiv.style.alignItems = 'center';
                        itemDiv.innerHTML = `
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <strong style="font-family: 'JetBrains Mono', monospace; font-size: 13px; color: var(--primary);">${res.label.id_label}</strong>
                                    <span class="badge badge-success">✓ ${berat.toFixed(2)} KG</span>
                                    <span class="badge badge-secondary">${res.label.kode_pemasok || res.label.kode_mitra || 'PMS'}</span>
                                </div>
                                <div style="font-size: 12.5px; color: var(--text-muted); margin-top: 3px;">
                                    <strong>${res.label.nama_item}</strong> • SJ: <strong>${res.label.no_surat_jalan || '-'}</strong>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline btn-sm" onclick="reprintBarcodeDirect('${res.label.id_label}')">
                                🖨️ Cetak
                            </button>
                        `;
                        list.insertBefore(itemDiv, list.firstChild);

                        // Update status counter Surat Jalan aktif
                        if (badgeSJSisa) {
                            let sisa = parseInt(badgeSJSisa.dataset.count || '0', 10);
                            let sudah = parseInt(badgeSJSelesai ? (badgeSJSelesai.dataset.count || '0') : '0', 10);
                            if (sisa > 0) {
                                sisa--;
                                sudah++;
                                badgeSJSisa.dataset.count = sisa;
                                badgeSJSisa.innerText = '⏳ Belum: ' + sisa + ' Pcs';
                                if (badgeSJSelesai) {
                                    badgeSJSelesai.dataset.count = sudah;
                                    badgeSJSelesai.innerText = '✓ Sudah: ' + sudah + ' Pcs';
                                }
                            }

                            if (activeWeighSJData) {
                                activeWeighSJData.sudah_ditimbang = sudah;
                                activeWeighSJData.belum_ditimbang = sisa;
                            }

                            // Update tombol Selesai Timbang menjadi aktif setelah barcode selesai discan dan diisi berat timbangannya
                            const curSj = selectWeighSJ ? selectWeighSJ.value : '';
                            syncSJTimbangButtons(curSj, sudah, isCurrentSJTimbangSelesai);

                            if (sisa === 0) {
                                if (allWeighedSuccessNotice) allWeighedSuccessNotice.style.display = 'block';
                            }
                        }

                        // Reset input untuk scan barcode berikutnya tanpa membuka dialog cetak
                        currentScannedLabel = null;
                        cardItemScanned.style.display = 'none';
                        formWeighArea.style.display = 'none';
                        scanBarcodeInp.value = '';
                        scanBarcodeInp.focus();

                    } else {
                        if (window.scannerAudio) window.scannerAudio.playErrorBuzz();
                        alert('Gagal simpan berat: ' + (res.message || 'Terjadi kesalahan'));
                    }
                } catch (err) {
                    alert('Kesalahan saat mengirim: ' + err.message);
                } finally {
                    btnSimpanBerat.disabled = false;
                    btnSimpanBerat.innerHTML = origText;
                }
            });
        }

        // Jalankan kalkulasi satuan pertama kali
        window.updateCalculations();

        // Inisialisasi awal status Surat Jalan & tombol Selesai Timbang
        if (selectWeighSJ && selectWeighSJ.value) {
            loadSuratJalanWeighDetail(selectWeighSJ.value);
        } else {
            syncSJTimbangButtons('', 0, false);
        }

        // Jika ada prefill barcode dari URL
        if (scanBarcodeInp && scanBarcodeInp.value) {
            performBarcodeLookup();
        }

        // -------------------------------------------------------------
        // FUNGSI NAVIGASI TAB & EKSPOR STOK GUDANG UTAMA
        // -------------------------------------------------------------
        window.switchGudangTab = function(tab) {
            const viewRekap = document.getElementById('viewGudangRekap');
            const viewDetail = document.getElementById('viewGudangDetail');
            const btnRekap = document.getElementById('btnTabRekapGudang');
            const btnDetail = document.getElementById('btnTabDetailGudang');
            const hiddenSubtab = document.getElementById('hiddenSubtab');

            if (!viewRekap || !viewDetail) return;
            if (hiddenSubtab) hiddenSubtab.value = tab;

            if (tab === 'rekap') {
                viewRekap.style.display = 'block';
                viewDetail.style.display = 'none';
                if (btnRekap) btnRekap.className = 'btn btn-sm btn-primary';
                if (btnDetail) btnDetail.className = 'btn btn-sm btn-outline';
            } else {
                viewRekap.style.display = 'none';
                viewDetail.style.display = 'block';
                if (btnRekap) btnRekap.className = 'btn btn-sm btn-outline';
                if (btnDetail) btnDetail.className = 'btn btn-sm btn-primary';
            }
        };

        window.setDateRangePreset = function(type) {
            const startInp = document.getElementById('gudangStartDate');
            const endInp = document.getElementById('gudangEndDate');
            if (!startInp || !endInp) return;

            const pad = (n) => String(n).padStart(2, '0');
            const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
            const today = new Date();

            if (type === 'today') {
                startInp.value = fmt(today);
                endInp.value = fmt(today);
            } else if (type === 'yesterday') {
                const y = new Date();
                y.setDate(today.getDate() - 1);
                startInp.value = fmt(y);
                endInp.value = fmt(y);
            } else if (type === '7days') {
                const d7 = new Date();
                d7.setDate(today.getDate() - 7);
                startInp.value = fmt(d7);
                endInp.value = fmt(today);
            } else if (type === 'this_month') {
                const dM = new Date(today.getFullYear(), today.getMonth(), 1);
                startInp.value = fmt(dM);
                endInp.value = fmt(today);
            } else if (type === 'clear') {
                startInp.value = '';
                endInp.value = '';
            }
        };

        window.syncItemFilter = function(jenisId) {
            const itemSelect = document.getElementById('filterGudangItemSelect');
            if (!itemSelect) return;
            const currentVal = itemSelect.value;
            const options = itemSelect.querySelectorAll('option');

            let hasCurrentMatch = false;
            options.forEach(opt => {
                if (!opt.value) {
                    opt.style.display = '';
                    return;
                }
                const optJenis = opt.getAttribute('data-jenis');
                if (!jenisId || optJenis === String(jenisId)) {
                    opt.style.display = '';
                    if (opt.value === currentVal) hasCurrentMatch = true;
                } else {
                    opt.style.display = 'none';
                }
            });

            if (!hasCurrentMatch && currentVal) {
                itemSelect.value = '';
            }
        };

        // Jalankan filter item pertama kali jika jenis sudah terpilih
        const jenisSelInit = document.getElementById('filterGudangJenisSelect');
        if (jenisSelInit && jenisSelInit.value) {
            syncItemFilter(jenisSelInit.value);
        }

        window.exportGudangCsv = function() {
            const isDetail = document.getElementById('viewGudangDetail') && document.getElementById('viewGudangDetail').style.display !== 'none';
            const targetTable = isDetail ? document.getElementById('tableGudangDetail') : document.getElementById('tableGudangRekap');
            if (!targetTable) return;

            let csv = [];
            const rows = targetTable.querySelectorAll('tr');
            for (let i = 0; i < rows.length; i++) {
                let row = [], cols = rows[i].querySelectorAll('td, th');
                for (let j = 0; j < cols.length; j++) {
                    if (cols[j].classList.contains('no-print')) continue;
                    let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/"/g, '""').trim();
                    row.push('"' + text + '"');
                }
                if (row.length > 0) csv.push(row.join(','));
            }

            const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + csv.join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', (isDetail ? 'Rincian_Barcode_Stok_Gudang_' : 'Rekap_Sisa_Stok_Gudang_') + new Date().toISOString().slice(0, 10) + '.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        };

        // Fungsi Toggle Jumbotron Panel Filter Gudang
        window.toggleGudangFilterPanel = function(forceState) {
            const panel = document.getElementById('panelJumbotronFilterGudang');
            const btn = document.getElementById('btnToggleFilterGudang');
            if (!panel) return;

            let shouldShow = forceState !== undefined ? forceState : (panel.style.display === 'none' || panel.style.display === '');
            panel.style.display = shouldShow ? 'block' : 'none';

            if (btn) {
                if (shouldShow) {
                    btn.classList.remove('btn-outline');
                    btn.classList.add('btn-primary');
                }
            }

            if (shouldShow) {
                // Focus ke input pertama
                const firstInput = panel.querySelector('select, input');
                if (firstInput) firstInput.focus();
            }
        };

        // =====================================================================
        // FILTER TANPA REFRESH HALAMAN (AJAX SEAMLESS LOADING)
        // =====================================================================
        let isAjaxFiltering = false;

        function showAjaxLoadingOverlay(container) {
            if (!container) return;
            let overlay = container.querySelector('.ajax-filter-loading-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'ajax-filter-loading-overlay';
                overlay.style.cssText = 'position: absolute; inset: 0; background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(2px); z-index: 50; display: flex; flex-direction: column; align-items: center; justify-content: center; border-radius: var(--radius); min-height: 200px;';
                overlay.innerHTML = `
                    <div style="background: white; padding: 14px 24px; border-radius: 12px; box-shadow: var(--shadow-lg); display: flex; align-items: center; gap: 12px; border: 1.5px solid var(--border);">
                        <svg class="animate-spin" style="width: 24px; height: 24px; color: var(--primary); animation: spin 1s linear infinite;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span style="font-weight: 700; font-size: 13px; color: var(--text-main);">Memuat data filter...</span>
                    </div>
                `;
                container.appendChild(overlay);
            } else {
                overlay.style.display = 'flex';
            }
        }

        function hideAjaxLoadingOverlay(container) {
            if (!container) return;
            const overlay = container.querySelector('.ajax-filter-loading-overlay');
            if (overlay) overlay.style.display = 'none';
        }

        window.loadStockGudangAjax = async function(url, pushState = true) {
            const container = document.getElementById('ajaxStokGudangContainer');
            if (!container || isAjaxFiltering) return;
            isAjaxFiltering = true;
            showAjaxLoadingOverlay(container);

            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) throw new Error('Gagal memuat data');
                const htmlText = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(htmlText, 'text/html');

                const newContainer = doc.getElementById('ajaxStokGudangContainer');
                if (newContainer) {
                    // Cek subtab yang sedang aktif di UI saat ini agar tetap konsisten
                    const currentSubtab = document.getElementById('hiddenSubtab') ? document.getElementById('hiddenSubtab').value : 'rekap';

                    container.innerHTML = newContainer.innerHTML;

                    // Pulihkan tampilan tab yang aktif
                    if (window.switchGudangTab) {
                        window.switchGudangTab(currentSubtab);
                    }

                    // Sinkronkan kembali opsi jenis produk
                    const currentJenisSel = document.getElementById('filterGudangJenisSelect');
                    if (currentJenisSel && currentJenisSel.value && window.syncItemFilter) {
                        window.syncItemFilter(currentJenisSel.value);
                    }

                    // Re-inisialisasi ikon Lucide
                    if (window.lucide && typeof window.lucide.createIcons === 'function') {
                        window.lucide.createIcons();
                    }

                    // Perbarui URL browser tanpa reload page
                    if (pushState) {
                        window.history.pushState({ type: 'stock_gudang', url: url }, '', url);
                    }
                } else {
                    // Fallback jika tidak menemukan container
                    window.location.href = url;
                }
            } catch (err) {
                console.error('AJAX Stock Gudang Error:', err);
                if (typeof showToast === 'function') {
                    showToast('Terjadi kesalahan saat memfilter data', 'error');
                }
            } finally {
                isAjaxFiltering = false;
                hideAjaxLoadingOverlay(container);
            }
        };

        window.loadRiwayatAjax = async function(url, pushState = true) {
            const details = document.getElementById('detailsRiwayatPenerimaan');
            if (!details || isAjaxFiltering) return;
            isAjaxFiltering = true;
            showAjaxLoadingOverlay(details);

            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) throw new Error('Gagal memuat riwayat');
                const htmlText = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(htmlText, 'text/html');

                const newDetails = doc.getElementById('detailsRiwayatPenerimaan');
                if (newDetails) {
                    const isOpen = details.open;
                    details.innerHTML = newDetails.innerHTML;
                    if (isOpen) details.open = true;

                    // Re-inisialisasi ikon Lucide
                    if (window.lucide && typeof window.lucide.createIcons === 'function') {
                        window.lucide.createIcons();
                    }

                    if (pushState) {
                        window.history.pushState({ type: 'riwayat', url: url }, '', url);
                    }
                } else {
                    window.location.href = url;
                }
            } catch (err) {
                console.error('AJAX Riwayat Error:', err);
                if (typeof showToast === 'function') {
                    showToast('Gagal memfilter riwayat penerimaan', 'error');
                }
            } finally {
                isAjaxFiltering = false;
                hideAjaxLoadingOverlay(details);
            }
        };

        // Event delegation untuk form filter stok gudang & tombol linknya
        document.addEventListener('submit', function(e) {
            if (e.target && e.target.id === 'formFilterGudang') {
                e.preventDefault();
                const form = e.target;
                const formData = new FormData(form);
                const params = new URLSearchParams(formData);
                const targetUrl = 'index.php?' + params.toString();
                window.loadStockGudangAjax(targetUrl);
            } else if (e.target && e.target.id === 'formFilterRiwayat') {
                e.preventDefault();
                const form = e.target;
                const formData = new FormData(form);
                const params = new URLSearchParams(formData);
                const targetUrl = 'index.php?' + params.toString() + '#detailsRiwayatPenerimaan';
                window.loadRiwayatAjax(targetUrl);
            }
        });

        // Intercept klik tautan di dalam ajaxStokGudangContainer (Sort Header, Quick Filter, Pagination, Tag Filter)
        document.addEventListener('click', function(e) {
            const stockLink = e.target.closest('#ajaxStokGudangContainer a');
            if (stockLink && stockLink.getAttribute('href')) {
                const href = stockLink.getAttribute('href');
                // Jangan cegah export atau external link
                if (href.startsWith('index.php') && href.includes('tab=stok_gudang')) {
                    e.preventDefault();
                    window.loadStockGudangAjax(href);
                    return;
                }
            }

            const riwayatLink = e.target.closest('#detailsRiwayatPenerimaan a');
            if (riwayatLink && riwayatLink.getAttribute('href')) {
                const href = riwayatLink.getAttribute('href');
                if (href.startsWith('index.php') && (href.includes('filter_') || href.includes('search=') || href.includes('p=') || href.includes('p_grp='))) {
                    e.preventDefault();
                    window.loadRiwayatAjax(href);
                    return;
                }
            }
        });

        // Tangani navigasi back/forward browser
        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.url) {
                if (e.state.type === 'stock_gudang') {
                    window.loadStockGudangAjax(e.state.url, false);
                } else if (e.state.type === 'riwayat') {
                    window.loadRiwayatAjax(e.state.url, false);
                }
            }
        });
    });
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
