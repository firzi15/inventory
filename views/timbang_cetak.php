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
$sjTertinggal = Label::getSuratJalanBelumDifaktur();

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
$urutTerimaBox = \App\Core\Urut::sql('terima_box', [
    'barcode' => 'l.id_label', 'pemasok' => 'p.nama_pemasok', 'produk' => 'i.nama_item', 'netto' => 'l.berat_netto',
    'sj' => 'l.no_surat_jalan', 'waktu' => 'l.created_at',
], 'l.id DESC');
$penerimaanList = Label::getFiltered($filterParams, $perPage, $offset, $urutTerimaBox);
$totalPages = max(1, (int)ceil($totalPenerimaan / $perPage));

// Ambil data penerimaan yang dikelompokkan per Surat Jalan dengan Pagination
$grpPageNumber = max(1, (int)($_GET['p_grp'] ?? 1));
$grpPerPage = 15;
$grpOffset = ($grpPageNumber - 1) * $grpPerPage;
$totalGrouped = Label::countGroupedPenerimaan($filterParams);
$urutTerimaSj = \App\Core\Urut::sql('terima_sj', [
    'sj' => 'l.no_surat_jalan', 'waktu' => 'MAX(l.created_at)', 'pemasok' => 'MAX(p.nama_pemasok)', 'produk' => 'MAX(i.nama_item)',
    'jumlah' => 'COUNT(*)', 'netto' => 'SUM(COALESCE(l.berat_netto, 0))',
], 'MAX(l.id) DESC');
$groupedPenerimaan = Label::getGroupedPenerimaan($filterParams, $grpPerPage, $grpOffset, $urutTerimaSj);
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
if ($activeTab === 'stok_gudang') {
    // Sisa Stok Gudang sudah dihapus; tautan lama diarahkan ke Barang Datang (barang yang masih Active di gudang)
    $arah = 'index.php?page=barang_datang&stok=AKTIF&dari=2000-01-01';
    if (!empty($_GET['item_id']))    $arah .= '&item=' . (int)$_GET['item_id'];
    if (!empty($_GET['pemasok_id'])) $arah .= '&pihak=' . (int)$_GET['pemasok_id'];
    header('Location: ' . $arah);
    exit;
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
        $arrowColor = 'color: var(--aktif-fg); opacity: 1; font-weight: 900;';
    } elseif ($isAsc) {
        $arrow = '↑';
        $arrowColor = 'color: var(--aktif-fg); opacity: 1; font-weight: 900;';
    }

    $justify = 'flex-start';
    if ($align === 'center') $justify = 'center';
    if ($align === 'right') $justify = 'flex-end';

    $activeBg = $isActive ? 'background: rgba(2, 132, 199, 0.08); border-radius: 6px; padding: 2px 6px;' : '';

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
<?php elseif ($activeTab === 'riwayat_penerimaan'): ?>
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Riwayat Penerimaan Barang Masuk</h1>
        <p class="page-subtitle">Daftar lengkap seluruh arsip label barcode kemasan, surat jalan masuk, dan bobot timbangan riil</p>
    </div>
    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
        <?php $eksporTabel = 'allPenerimaanTable'; $eksporNama = 'Daftar_Penerimaan_Barang'; include __DIR__ . '/layout/ekspor.php'; ?>
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

<!-- Ukuran stiker barcode untuk semua tombol cetak barcode di halaman ini (disimpan di browser ini) -->
<div class="no-print" style="display: flex; justify-content: space-between; align-items: center; gap: 12px 20px; flex-wrap: wrap; margin: -6px 0 12px;">
    <?php if ($activeTab === 'timbang_cetak'): ?>
    <!-- Waktu penerimaan: kosong = sekarang; isi tanggal kemarin bila kerja lewat tengah malam (nomor SJ ikut tanggalnya) -->
    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        <label for="onestop_waktu" style="font-size: 12.5px; font-weight: 700; color: var(--text-muted); display: inline-flex; align-items: center; gap: 6px; margin: 0;">
            <i data-lucide="calendar-clock" style="width: 14px; height: 14px;"></i>Waktu penerimaan
        </label>
        <input type="datetime-local" id="onestop_waktu" class="form-control" style="width: auto; height: 34px; font-size: 12.5px; font-weight: 700; padding: 0 8px;" onfocus="this.max = waktuSekarangLokal()" onchange="onOneStopWaktuChanged()" title="Terisi waktu sekarang. Ubah ke tanggal kemarin bila kerja lewat tengah malam.">
        <button type="button" class="btn btn-outline btn-sm" style="height: 34px; width: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" onclick="geserHariWaktu('onestop_waktu', -1); onOneStopWaktuChanged()" title="Mundur 1 hari"><i data-lucide="minus" style="width: 14px; height: 14px;"></i></button>
        <button type="button" class="btn btn-outline btn-sm" style="height: 34px; width: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" onclick="geserHariWaktu('onestop_waktu', 1); onOneStopWaktuChanged()" title="Maju 1 hari (paling jauh waktu sekarang)"><i data-lucide="plus" style="width: 14px; height: 14px;"></i></button>
        <span id="onestop_waktu_info" style="font-size: 12px; font-weight: 700; color: var(--warn-fg);"></span>
    </div>
    <?php else: ?>
    <div></div>
    <?php endif; ?>
    <div style="display: flex; align-items: center; gap: 8px;">
        <label for="pilihUkuranStiker" style="font-size: 12.5px; font-weight: 700; color: var(--text-muted); display: inline-flex; align-items: center; gap: 6px; margin: 0;">
            <i data-lucide="printer" style="width: 14px; height: 14px;"></i>Ukuran stiker barcode
        </label>
        <select id="pilihUkuranStiker" class="form-control" style="width: auto; height: 34px; font-size: 12.5px; font-weight: 700;" onchange="pilihUkuranStiker(this.value)">
            <option value="70x50">70 x 50 mm</option>
            <option value="150x100">150 x 100 mm</option>
        </select>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded', function () { var s = document.getElementById('pilihUkuranStiker'); if (s) s.value = ukuranStiker(); });</script>

<!-- Hidden fallback container untuk template barcode SVG -->
<div id="barcodeSvgContainer" style="display: none;">
    <?= BarcodeService::generateCode128Svg($sampleBarcode, 120, 2.0) ?>
</div>

<!-- ========================================================================= -->
<!-- SUB-MENU UTAMA: PENERIMAAN & TIMBANG SATU PINTU (ONE-STOP WEIGH & PRINT)   -->
<!-- ========================================================================= -->
<div id="sectionOneStopWeigh" style="<?= $activeTab === 'timbang_cetak' ? 'display: block;' : 'display: none;' ?>">
    <?php if ($sjTertinggal): ?>
    <!-- Surat Jalan yang box-nya sudah ditimbang tetapi sesinya tidak diselesaikan (faktur belum terbentuk) -->
    <div class="card no-print" style="border: 1.5px solid var(--warn-solid); margin-bottom: 20px;">
        <div class="card-header" style="border-bottom: 1px solid var(--border);">
            <h2 class="card-title" style="font-size: 15px; color: var(--warn-fg);">
                <?= count($sjTertinggal) ?> Surat Jalan belum diselesaikan
            </h2>
            <div class="card-description">Barangnya sudah masuk stok, tetapi faktur dan jurnalnya belum terbentuk. Klik <strong>Selesaikan</strong> bila barangnya benar-benar diterima, atau <strong>Batalkan</strong> bila salah/percobaan.</div>
        </div>
        <div class="table-responsive">
            <table class="table-custom">
                <thead><tr>
                    <th>Surat Jalan</th><th>Pemasok</th><th style="text-align:right;">Box</th><th style="text-align:right;">Berat (kg)</th><th style="text-align:right;">Nilai</th><th>Ditimbang</th><th style="text-align:right;">Aksi</th>
                </tr></thead>
                <tbody>
                <?php foreach ($sjTertinggal as $st): $sjEsc = htmlspecialchars($st['no_surat_jalan'], ENT_QUOTES); ?>
                    <tr>
                        <td style="font-family: 'JetBrains Mono', monospace; font-weight: 700;"><?= $sjEsc ?></td>
                        <td><?= htmlspecialchars($st['nama_pemasok'] ?? '-') ?></td>
                        <td style="text-align:right;"><?= (int)$st['jumlah_box'] ?></td>
                        <td style="text-align:right;"><?= Helper::formatNumber((float)$st['total_kg']) ?></td>
                        <td style="text-align:right; font-weight:700;"><?= Helper::formatRupiah((float)$st['total_nilai']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($st['mulai'])) ?></td>
                        <td style="text-align:right; white-space: nowrap;">
                            <button type="button" class="btn btn-success btn-sm" onclick="selesaikanSjTertinggal('<?= $sjEsc ?>', <?= (int)$st['jumlah_box'] ?>)">Selesaikan</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="batalDokumen('batal_penerimaan', { no_surat_jalan: '<?= $sjEsc ?>' }, 'penerimaan <?= $sjEsc ?>')" style="color: var(--bad-fg); border-color: rgba(220,38,38,0.35);">Batalkan</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    async function selesaikanSjTertinggal(noSj, jmlBox) {
        const ok = await window.showAppConfirm({
            title: 'Selesaikan Surat Jalan ini?',
            html: 'Surat Jalan <strong>' + noSj + '</strong> (' + jmlBox + ' box) akan diselesaikan dan faktur penerimaan dibuat.',
            confirmButtonText: 'Ya, Selesaikan'
        });
        if (!ok.isConfirmed) return;
        try {
            const fd = new FormData();
            fd.append('no_surat_jalan', noSj);
            const resp = await fetch('index.php?action=selesaikan_sj_tertinggal', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data && data.success) {
                if (data.print_bast_url) window.open(data.print_bast_url, '_blank');
                location.reload();
            } else {
                alert('Gagal menyelesaikan: ' + ((data && data.message) || 'Error'));
            }
        } catch (e) {
            alert('Kesalahan jaringan: ' + e.message);
        }
    }
    </script>
    <?php endif; ?>
    <!-- Grid Desktop 2-Kolom: Form Timbang & Stiker di Kiri, Live Rekap Surat Jalan di Kanan -->
    <div style="display: grid; grid-template-columns: minmax(340px, 4fr) minmax(420px, 6fr); gap: 24px; align-items: start; margin-bottom: 30px;">
        
        <!-- KOLOM KIRI: STASIUN KERJA PENIMBANGAN & CETAK LANGSUNG -->
        <div class="card no-print" style="box-shadow: var(--shadow-md);">
            <div class="card-header" style="border-bottom: 1.5px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 class="card-title" style="font-size: 16px; display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="scale" style="width: 18px; height: 18px; color: var(--ok-fg);"></i>
                        <span>Input Timbang & Cetak Barcode</span>
                    </h2>
                    <div class="card-description">Input penimbangan keranjang masuk per surat jalan</div>
                </div>
            </div>

            <div style="padding: 16px;">
                <!-- 1. HEADER SESI SURAT JALAN -->
                <div style="background: var(--bg-muted); border: 1.5px solid var(--border); border-radius: 8px; padding: 14px; margin-bottom: 16px;">
                    <div style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                        <span>Informasi Surat Jalan</span>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" for="onestop_pemasok_id" style="font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 5px; display: flex; align-items: center; gap: 5px;">
                                <i data-lucide="building-2" style="width: 14px; height: 14px; color: var(--text-muted);"></i>
                                <span>Pemasok <span style="color: var(--bad-fg);">*</span></span>
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
                                <label class="form-label" for="onestop_no_sj" style="font-size: 12px; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 5px;">
                                    <i data-lucide="file-text" style="width: 14px; height: 14px; color: var(--text-muted);"></i>
                                    <span>No. Surat Jalan <span style="color: var(--bad-fg);">*</span></span>
                                </label>
                                <span class="badge badge-secondary" style="font-size: 10px; cursor: pointer;" onclick="onOneStopPemasokChanged()" title="Klik untuk refresh nomor surat jalan">Auto</span>
                            </div>
                            <input type="text" id="onestop_no_sj" class="form-control" value="<?= htmlspecialchars($defaultSuratJalan) ?>" style="font-family: 'JetBrains Mono', monospace; font-weight: 800; color: var(--aktif-fg); height: 42px; background: var(--bg-card); border: 1.5px solid var(--border-hover); font-size: 13.5px;" placeholder="No Surat Jalan..." onchange="onOneStopSjChanged()">
                        </div>
                    </div>
                </div>

                <!-- 2. PEMILIHAN KATEGORI & PRODUK -->
                <div style="margin-bottom: 16px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" for="onestop_jenis_filter" style="font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 5px; display: flex; align-items: center; gap: 5px;">
                                <i data-lucide="layers" style="width: 14px; height: 14px; color: var(--text-muted);"></i>
                                <span>Kategori <span style="color: var(--bad-fg);">*</span></span>
                            </label>
                            <select id="onestop_jenis_filter" class="form-control" style="font-weight: 700; height: 42px; font-size: 13px;" onchange="onOneStopJenisChanged()">
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($jenisList as $j): ?>
                                    <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['nama_jenis']) ?> (<?= htmlspecialchars($j['kode_jenis']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" for="onestop_item_id" style="font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 5px; display: flex; align-items: center; gap: 5px;">
                                <i data-lucide="package" style="width: 14px; height: 14px; color: var(--text-muted);"></i>
                                <span>Produk <span style="color: var(--bad-fg);">*</span></span>
                                <a id="linkHargaBeli" href="index.php?page=master&tab=harga_pemasok" target="_blank" class="btn-ikon-harga" style="margin-left: auto;" title="Atur harga beli produk ini di Master Harga (tab baru)">Atur harga &#8599;</a>
                            </label>
                            <select id="onestop_item_id" class="form-control" disabled style="font-weight: 800; height: 42px; font-size: 13.5px;" onchange="onOneStopProductChanged(this.value)">
                                <option value="">-- Pilih Kategori Dahulu --</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 3. DISPLAY TIMBANGAN DIGITAL & INPUT NETTO -->
                <div style="background: var(--bg-muted); border: 2px solid var(--ok-solid); border-radius: 10px; padding: 16px; margin-bottom: 16px; text-align: center;">
                    <div style="display: flex; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 12px; font-weight: 800; color: var(--ok-fg); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                            <i data-lucide="scale" style="width: 15px; height: 15px;"></i>
                            <span>Berat Bersih</span>
                        </span>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                        <input type="number" id="onestop_berat_netto" step="0.01" min="0.01" max="999.99" class="form-control" placeholder="0.00" 
                               style="font-family: 'JetBrains Mono', monospace; font-size: 44px; font-weight: 900; text-align: center; color: var(--text-main); height: 70px; width: 220px; border: 2px solid var(--ok-solid); border-radius: 8px; background: var(--bg-card);"
                               onkeydown="if(event.key === 'Enter') { event.preventDefault(); handleSaveWeighedBox(); }">
                        <span style="font-size: 26px; font-weight: 900; color: var(--ok-fg);">KG</span>
                    </div>

                    <div style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 10px; font-size: 11.5px; color: var(--text-muted); flex-wrap: wrap;">
                        <span>Tekan <strong style="color: var(--text-main); background: var(--border); padding: 2px 6px; border-radius: 4px;">[ENTER]</strong> untuk tambah box</span>
                    </div>
                </div>

                <!-- 4. TOMBOL TAMBAH BOX KE DAFTAR -->
                <button type="button" id="btnSaveWeighedBox" class="btn btn-primary" onclick="handleSaveWeighedBox()" style="width: 100%; height: 46px; font-size: 14.5px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 8px; background: var(--ok-solid); border-color: var(--ok-solid); border-radius: 8px; cursor: pointer;">
                    <i data-lucide="plus-circle" style="width: 18px; height: 18px;"></i>
                    <span id="labelBtnSaveWeighed">Tambah Box</span>
                </button>
            </div>
        </div>

        <!-- KOLOM KANAN: LIVE SUMMARY SURAT JALAN & DAFTAR KERANJANG TERAKHIR -->
        <div class="no-print" style="display: flex; flex-direction: column; gap: 12px; min-width: 0;">
        <div class="card no-print" style="box-shadow: var(--shadow-md);">
            <div class="card-header" style="border-bottom: 1.5px solid var(--border);">
                <div>
                    <h2 class="card-title" style="font-size: 16px; display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="clipboard-check" style="width: 18px; height: 18px; color: var(--aktif-fg);"></i>
                        <span>Ringkasan Surat Jalan</span>
                    </h2>
                    <div class="card-description" id="summarySjSubtitle">No. SJ: <?= htmlspecialchars($defaultSuratJalan) ?></div>
                </div>
            </div>

            <div style="padding: 16px;">
                <!-- Rincian Produk di Surat Jalan Ini (Sama Persis seperti Tabel Scan Pengeluaran) -->
                <div style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 11.5px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Rincian Produk:</span>
                        <span style="font-size: 11.5px; color: var(--text-muted);">Terbaru di atas</span>
                    </div>
                    <div id="containerProductBreakdown" style="display: none;"></div>
                    <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                        <table class="table-custom" id="tableInboundRecentBoxes">
                            <thead>
                                <tr>
                                    <th style="width: 34px; text-align: center;">No</th>
                                    <th>ID Barcode &amp; Produk</th>
                                    <th style="text-align: right; width: 98px;">Netto &amp; Harga/Kg</th>
                                    <th style="text-align: right; width: 104px;">Nominal</th>
                                    <th style="text-align: center; width: 50px; position: sticky; right: 0; background: var(--bg-muted, #f8fafc); z-index: 25; box-shadow: -2px 0 4px rgba(0,0,0,0.04);">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="containerRecentBoxes">
                                <tr id="emptyInboundRow">
                                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 40px 20px;">
                                        <div style="font-weight: 700; font-size: 13.5px; color: var(--text-main);">Belum ada box ditimbang</div>
                                        <div style="font-size: 11.5px; margin-top: 3px;">Timbang box pada formulir di sebelah kiri untuk memasukkan ke sesi ini.</div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot id="inboundTotalFoot" style="display: none;"></tfoot>
                        </table>
                    </div>
                </div>

                <!-- Tombol Cetak Semua Barcode Thermal di Sesi Ini -->
                <div style="background: var(--bg-muted); border: 1.5px solid var(--border); border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 11.5px; font-weight: 800; color: var(--text-main); text-transform: uppercase;">
                            Cetak Barcode Box:
                        </span>
                        <span class="badge badge-purple" id="badgePrintCount" style="font-size: 11px; font-weight: 800;">0 Barcode</span>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="printAllBarcodesInSession('thermal')" id="btnPrintBatchThermal" style="width: 100%; font-weight: 700; font-size: 13px; height: 40px; display: flex; align-items: center; justify-content: center; gap: 8px; background: #0284c7; border-color: #0284c7; border-radius: 6px; cursor: pointer;">
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
</div>

<!-- ========================================================================= -->
<!-- SUB-MENU 1: BUAT & CETAK BARCODE KERANJANG (DESKTOP WORKBENCH LAYOUT)     -->
<!-- ========================================================================= -->
<div id="sectionMassal" style="<?= $activeTab === 'massal' ? 'display: block;' : 'display: none;' ?>">
    <!-- Grid Desktop 2-Kolom: Form Pembuatan di Kiri, Live Result & Preview di Kanan -->
    <div style="display: grid; grid-template-columns: minmax(360px, 1fr) minmax(360px, 1fr); gap: 24px; align-items: start; margin-bottom: 30px;">
        <!-- Kolom Kiri: Formulir Cetak Barcode Baru -->
        <div class="card no-print" style="box-shadow: var(--shadow-md);">
            <div class="card-header" style="border-bottom: 1.5px solid var(--border);">
                <div>
                    <h2 class="card-title" style="font-size: 17px;">
                        <i data-lucide="printer" style="width: 20px; height: 20px; color: var(--aktif-fg);"></i>
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
                        <input type="text" name="no_surat_jalan" id="bulk_no_sj" class="form-control" value="<?= htmlspecialchars($defaultSuratJalan) ?>" required readonly style="font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--primary); height: 42px; background: var(--bg-muted); cursor: not-allowed; border: 1.5px solid var(--border);" title="Nomor Surat Jalan otomatis berurutan dari sistem dan tidak dapat diedit manual oleh pengguna">
                    </div>
                </div>

                <!-- Dropdown Kategori & Produk -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
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
                        <label class="form-label" for="bulk_quantity" style="font-weight: 800; font-size: 13.5px; color: var(--aktif-fg); display: flex; justify-content: space-between; align-items: center;">
                            <span>Jumlah Barcode yang Ingin Dicetak <span style="color:red;">*</span></span>
                            <span class="badge badge-purple" style="font-size: 11px;">10 Pcs / Lembar A4</span>
                        </label>
                        
                        <div style="display: flex; gap: 10px; align-items: center; margin-top: 8px; flex-wrap: wrap;">
                            <input type="number" min="1" max="200" step="1" name="quantity_barcode" id="bulk_quantity" class="form-control" value="10" required 
                                   onkeydown="if(['-', '+', 'e', 'E', '.'].includes(event.key)) event.preventDefault();"
                                   oninput="let v = this.value.replace(/[^0-9]/g, ''); if(v === '' || parseInt(v) < 1) { this.value = 1; } else { this.value = Math.min(200, parseInt(v)); }"
                                   style="font-size: 24px; font-weight: 900; text-align: center; color: var(--aktif-fg); height: 46px; width: 100px;">
                            
                            <!-- Pilihan Cepat Kuantitas Sederhana -->
                            <div class="qty-pill-group">
                                <button type="button" class="qty-pill-btn active" data-qty="10" onclick="setBulkQty(10, this)" title="1 Lembar A4 Penuh (10 Barcode)">10 Pcs</button>
                                <button type="button" class="qty-pill-btn" data-qty="20" onclick="setBulkQty(20, this)" title="2 Lembar A4">20 Pcs</button>
                                <button type="button" class="qty-pill-btn" data-qty="5" onclick="setBulkQty(5, this)" title="Setengah Lembar A4 (5 Pcs)">5 Pcs</button>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" id="btnGenerateBulk" style="width: 100%; height: 50px; font-weight: 800; font-size: 15px; background: var(--primary); border-color: var(--primary); cursor: pointer;">
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
                        <i data-lucide="check-circle-2" style="width: 18px; height: 18px; color: var(--ok-fg);"></i>
                        <span>Hasil Barcode Massal Siap Cetak</span>
                    </h2>
                    <div class="card-description" id="bulkResultDesc">Daftar barcode siap cetak lembar PDF</div>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-outline btn-sm" id="btnPreviewSheet" style="display: none;" onclick="openSheetPreviewModal()">
                        <i data-lucide="eye" style="width: 15px; height: 15px; color: var(--aktif-fg);"></i>
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
    <div style="display: grid; grid-template-columns: minmax(360px, 1fr) minmax(360px, 1fr); gap: 24px; align-items: start; margin-bottom: 30px;">
        <!-- Formulir Scan Barcode & Rekam Timbangan Riil -->
        <div class="card no-print" style="box-shadow: var(--shadow-md);">
            <div class="card-header" style="border-bottom: 1.5px solid var(--border);">
                <div>
                    <h2 class="card-title" style="font-size: 17px;">
                        <i data-lucide="scale" style="width: 20px; height: 20px; color: var(--ok-fg);"></i>
                        <span>Stasiun Timbang Barang Masuk</span>
                    </h2>
                    <div class="card-description">Scan barcode di keranjang, lalu masukkan bobot riil timbangan</div>
                </div>
                <span class="badge badge-success">Stasiun Timbang</span>
            </div>

            <!-- 0. Pemilihan Surat Jalan Penerimaan & List Barcode Belum Ditimbang -->
            <div style="background: var(--bg-card); border: 2px solid var(--primary); border-radius: var(--radius); padding: 16px; margin: 16px 0 14px 0; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.08);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <label for="selectWeighSJ" style="font-size: 14px; font-weight: 800; color: var(--aktif-fg); display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                            <i data-lucide="file-text" style="width: 18px; height: 18px; color: var(--aktif-fg);"></i>
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
                        <button type="button" id="btnSelesaiTimbangSJ" class="btn btn-sm" onclick="handleSelesaiTimbangClick()" disabled style="padding: 4px 12px; font-size: 12px; font-weight: 800; height: 32px; display: inline-flex; align-items: center; gap: 6px; background: var(--border); border-color: var(--border); color: var(--text-muted); cursor: not-allowed; transition: all 0.2s;" title="Scan barcode dan isi berat timbangan terlebih dahulu">
                            <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i>
                            <span id="labelBtnSelesaiTimbang">Selesai Timbang</span>
                        </button>
                        <a id="btnCetakSJPenerimaan" href="#" target="_blank" class="btn btn-primary btn-sm" onclick="handleCetakSJClick(event)" style="padding: 4px 12px; font-size: 12px; font-weight: 700; height: 32px; display: inline-flex; align-items: center; gap: 6px; background: var(--text-muted); border-color: #64748b; color: #ffffff; opacity: 0.55; cursor: not-allowed; transition: all 0.2s;" title="Tombol cetak terkunci. Klik 'Selesai Timbang' terlebih dahulu setelah selesai menimbang.">
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
                <div id="containerSJUnweighed" style="display: none; margin-top: 14px; padding-top: 14px; border-top: 1.5px dashed var(--border);">
                    <!-- Ringkasan Info SJ Terpilih -->
                    <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-muted); border-radius: 8px; padding: 10px 14px; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
                        <div>
                            <span style="font-size: 12px; color: var(--text-muted);">Surat Jalan:</span>
                            <span id="infoSJNo" style="font-family: 'JetBrains Mono', monospace; font-size: 13.5px; font-weight: 800; color: var(--aktif-fg); margin-left: 4px;">-</span>
                            <span style="font-size: 12px; color: var(--text-muted); margin-left: 8px;">• Item: <strong id="infoSJItem" style="color: var(--text-main);">-</strong></span>
                            <span style="font-size: 12px; color: var(--text-muted); margin-left: 8px;">• Pemasok: <strong id="infoSJPemasok" style="color: var(--text-main);">-</strong></span>
                        </div>
                        <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                            <span class="badge badge-secondary" id="badgeSJTotal">Total: 0 Box</span>
                            <span class="badge badge-warning" id="badgeSJSisa" style="font-weight: 800;">Belum: 0 Box</span>
                            <span class="badge badge-success" id="badgeSJSelesai">Sudah: 0 Box</span>
                            <button type="button" class="btn btn-outline btn-sm" id="btnLihatBarcodeDiRiwayat" onclick="openSJBarcodeHistoryModal(document.getElementById('infoSJNo')?.innerText)" style="font-size: 11.5px; font-weight: 700; color: var(--aktif-fg); border-color: var(--border-hover); background: var(--info-bg); cursor: pointer;" title="Lihat daftar barcode keranjang surat jalan ini di riwayat">
                                <i data-lucide="list" style="width: 13px; height: 13px;"></i>
                                <span>Lihat Daftar Barcode</span>
                            </button>
                        </div>
                    </div>

                    <!-- Banner jika semua keranjang pada SJ terpilih sudah selesai ditimbang -->
                    <div id="allWeighedSuccessNotice" style="display: none; background: var(--ok-bg); border: 1.5px solid var(--ok-solid); border-radius: 8px; padding: 14px; text-align: center;">
                        <div style="font-size: 15px; font-weight: 800; color: var(--ok-fg); display: flex; align-items: center; justify-content: center; gap: 6px; margin-bottom: 4px;">
                            <i data-lucide="check-circle-2" style="width: 20px; height: 20px;"></i>
                            <span>Semua Keranjang pada Surat Jalan Ini Telah Selesai Ditimbang!</span>
                        </div>
                        <div style="font-size: 12.5px; color: var(--ok-fg); margin-bottom: 0;">
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
                <div style="background: rgba(16, 185, 129, 0.08); border: 2px solid var(--ok-solid); border-radius: var(--radius); padding: 16px; margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label class="form-label" for="scanWeightValue" style="margin: 0; font-size: 14px; font-weight: 800; color: var(--ok-fg);">
                            Berat Timbangan Riil (KG) <span style="color:red;">*</span>
                        </label>
                    </div>

                    <div style="position: relative; margin-bottom: 10px;">
                        <input type="number" step="0.01" id="scanWeightValue" class="form-control" placeholder="0.00" style="font-size: 28px; font-weight: 900; color: var(--ok-fg); padding-right: 60px; height: 52px;">
                        <span style="position: absolute; right: 16px; top: 12px; font-size: 18px; font-weight: 900; color: var(--ok-fg);">KG</span>
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
                        <i data-lucide="history" style="width: 18px; height: 18px; color: var(--ok-fg);"></i>
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
<!-- CONTAINER KHUSUS CETAK THERMAL & SHEET BULK (PRINT-ONLY)                 -->
<!-- ========================================================================= -->
<div id="bulkPrintContainer" class="print-area"></div>

<!-- Modal Pratinjau Lembar Cetak PDF (A4 10 pcs / 5 pcs 1/2 lembar) -->
<div id="modalSheetPreview" class="sheet-preview-backdrop no-print" style="display: none;">
    <div class="sheet-preview-dialog">
        <div class="sheet-preview-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="background: rgba(3, 105, 161, 0.15); color: var(--aktif-fg); padding: 6px; border-radius: 8px;">
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
        <div style="padding: 16px 20px; background: var(--primary); color: #ffffff; display: flex; justify-content: space-between; align-items: center;">
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
                <div style="font-family: 'JetBrains Mono', monospace; font-size: 15px; font-weight: 800; color: var(--aktif-fg);" id="reprintTargetBarcode">-</div>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;" id="reprintTargetMeta">-</div>
            </div>

            <!-- Pilihan Menu Cetak -->
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <!-- Opsi 1: Cetak Barcode Ini dengan Pilihan Jumlah Lembar (Custom Qty) -->
                <div style="background: var(--info-bg); border: 2px solid var(--primary); border-radius: 12px; padding: 14px 16px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <strong style="color: var(--aktif-fg); font-size: 14px;">Cetak Barcode Ini</strong>
                                </div>
                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 3px;">
                                    Jumlah stiker yang dicetak:
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px; margin-left: auto;">
                            <button type="button" class="btn btn-outline" style="padding: 4px 10px; font-weight: 800; height: 36px;" onclick="stepReprintQty(-1)">−</button>
                            <input type="number" id="reprintQtyInput" min="1" max="100" value="10" style="width: 58px; height: 36px; text-align: center; font-weight: 800; font-size: 14px; border: 1.5px solid var(--primary); border-radius: 6px; background: var(--bg-card);" onchange="validateReprintQty(this)">
                            <button type="button" class="btn btn-outline" style="padding: 4px 10px; font-weight: 800; height: 36px;" onclick="stepReprintQty(1)">+</button>
                            <button type="button" onclick="executeReprintCustomQty()" class="btn btn-primary" style="padding: 6px 16px; height: 36px; font-weight: 700; background: var(--primary); border-color: var(--primary); display: flex; align-items: center; gap: 6px;">
                                <i data-lucide="printer" style="width: 15px; height: 15px;"></i>
                                <span>Cetak</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Opsi 2: Cetak Seluruh Barcode di Surat Jalan Ini -->
                <button type="button" onclick="executeReprintGroupSJ()" class="btn" style="display: flex; align-items: center; gap: 14px; padding: 14px 16px; text-align: left; background: var(--ok-bg); border: 1.5px solid var(--ok-solid); border-radius: 12px; cursor: pointer; transition: all 0.2s;">
                    <div style="flex: 1;">
                        <strong style="color: var(--ok-fg); font-size: 14px;">Cetak Seluruh Barcode di Surat Jalan Ini</strong>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 3px;" id="reprintGroupDesc">
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
    <div style="background: var(--bg-card, #ffffff); border-radius: 12px; width: 100%; max-width: 840px; max-height: 88vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.04); border: 1px solid var(--border, var(--border));">
        <!-- Header Modal -->
        <div style="padding: 16px 20px; background: var(--bg-card); border-bottom: 1px solid var(--border, var(--border)); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-size: 16px; font-weight: 800; color: var(--text-main, #0f172a); display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="package" style="width: 18px; height: 18px; color: var(--primary, #0284c7);"></i>
                    <span>Daftar Box Surat Jalan</span>
                </div>
                <div style="font-size: 12.5px; color: var(--text-muted, #64748b); margin-top: 2px;">
                    No. Surat Jalan: <code id="modalSJNoText" style="font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--primary, #2563eb); background: var(--info-bg); padding: 2px 8px; border-radius: 4px; border: 1px solid var(--border-hover);">-</code>
                </div>
            </div>
            <button type="button" onclick="closeSJBarcodeListModal()" style="background: transparent; border: 1px solid var(--border, var(--border)); color: var(--text-muted); width: 32px; height: 32px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 700; transition: all 0.15s ease;" onmouseover="this.style.background='#f1f5f9'; this.style.color='#0f172a';" onmouseout="this.style.background='transparent'; this.style.color='#64748b';" title="Tutup">✕</button>
        </div>

        <!-- Meta Info Bar: Produk, Pemasok & Counter -->
        <div style="padding: 12px 20px; background: var(--bg-muted); border-bottom: 1px solid var(--border, var(--border)); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="font-size: 12.5px; color: var(--text-muted, #64748b);">
                <span>Pemasok:</span> <strong id="modalSJPemasokText" style="color: var(--text-main, #0f172a);">-</strong>
                <span style="color: #cbd5e1; margin: 0 8px;">•</span>
                <span>Produk:</span> <strong id="modalSJItemText" style="color: var(--text-main, #0f172a);">-</strong>
            </div>
        </div>

        <!-- Body Modal: Tabel Elegan Seluruh Box -->
        <div style="padding: 0; overflow-y: auto; flex: 1; max-height: 440px; background: var(--bg-card);">
            <table class="table-custom" id="tabelRincianTerima" style="margin: 0; border: none;">
                <thead style="position: sticky; top: 0; z-index: 5; background: var(--bg-muted);">
                    <tr>
                        <th style="width: 45px; text-align: center;">No</th>
                        <th>Kode Box / Barcode</th>
                        <th>Nama Produk</th>
                        <th style="text-align: right; width: 110px;">Netto (Kg)</th>
                        <th style="text-align: right; width: 110px;">Harga Beli/kg</th>
                        <th style="text-align: right; width: 130px;">Nominal</th>
                        <th style="text-align: center; width: 90px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="modalSJTableBody">
                    <!-- Diisi via JS -->
                </tbody>
                <tfoot id="modalSJTableFoot" style="display: none;">
                    <tr style="background: var(--bg-muted);">
                        <td colspan="3" style="text-align: right; font-weight: 700; color: var(--text-muted, #64748b); font-size: 12.5px;">Total <span id="modalSJTotalCount">0</span> box</td>
                        <td id="modalSJTotalNetto" style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; font-size: 13px;">0.00 kg</td>
                        <td></td>
                        <td id="modalSJTotalNominal" style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; font-size: 13px;">Rp 0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Footer Modal -->
        <div style="padding: 14px 20px; background: var(--bg-muted); border-top: 1px solid var(--border, var(--border)); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <button type="button" class="btn btn-primary" id="btnModalPrintAllBarcodes" onclick="printAllModalBarcodes()" style="font-size: 12.5px; font-weight: 700; height: 38px; padding: 0 16px; display: inline-flex; align-items: center; gap: 6px; border-radius: 6px;">
                    <i data-lucide="tag" style="width: 14px; height: 14px;"></i>
                    <span>Cetak Semua Barcode</span>
                </button>
                <button type="button" class="btn btn-outline" onclick="eksporRincianTerima()" style="font-size: 12.5px; font-weight: 700; height: 38px; padding: 0 16px; display: inline-flex; align-items: center; gap: 6px; border-radius: 6px; color: var(--ok-fg); border-color: var(--border-hover); background: var(--ok-bg);" title="Ekspor rincian box ke Excel">
                    <i data-lucide="file-spreadsheet" style="width: 14px; height: 14px;"></i>
                    <span>Excel</span>
                </button>
                <a id="btnModalCetakSuratJalan" href="#" target="_blank" class="btn btn-outline" style="font-size: 12.5px; font-weight: 700; height: 38px; padding: 0 16px; display: inline-flex; align-items: center; gap: 6px; border-radius: 6px;">
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
<div class="no-print" id="detailsRiwayatPenerimaan">
    <?php // Kartu ringkasan: ikut filter (tanggal, pemasok, produk, pencarian)
    $rkTerima = Label::ringkasFiltered($filterParams);
    $krKartu = [
        ['Total Netto', number_format((float)$rkTerima['netto'], 2, ',', '.') . ' kg', number_format((int)$rkTerima['box']) . ' box dari ' . number_format((int)$rkTerima['sj']) . ' surat jalan', 'var(--aktif-fg)', 'scale'],
        ['Uang Terima (Nilai Beli)', \App\Core\Helper::formatRupiah($rkTerima['nilai_beli']), 'Netto x harga beli pemasok', 'var(--ok-fg)', 'wallet'],
        ['Rata-rata Harga / kg', (float)$rkTerima['netto'] > 0 ? \App\Core\Helper::formatRupiah((float)$rkTerima['nilai_beli'] / (float)$rkTerima['netto']) : '-', 'Rata-rata ' . ((int)$rkTerima['box'] > 0 ? number_format((float)$rkTerima['netto'] / (int)$rkTerima['box'], 2, ',', '.') : '0') . ' kg per box', 'var(--warn-fg, #b45309)', 'trending-up'],
    ];
    include __DIR__ . '/layout/kartu_ringkas.php'; ?>
    <form method="GET" action="index.php#detailsRiwayatPenerimaan" id="formFilterRiwayat" class="card" style="padding: 14px 16px; margin-bottom: 18px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="page" value="timbang_cetak">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
        <?php if (($_GET['mode'] ?? '') === 'detail'): ?><input type="hidden" name="mode" value="detail"><?php endif; ?>
        <?= \App\Core\Urut::hidden() ?>
        <div style="flex: 1; min-width: 220px;">
            <label class="form-label">Cari</label>
            <input type="text" name="search" class="form-control" placeholder="No. surat jalan, barcode, produk, pemasok..." value="<?= htmlspecialchars($searchQuery) ?>">
        </div>
        <div><label class="form-label">Dari</label><input type="date" name="filter_start_date" value="<?= htmlspecialchars($filterStartDate) ?>" class="form-control"></div>
        <div><label class="form-label">Sampai</label><input type="date" name="filter_end_date" value="<?= htmlspecialchars($filterEndDate) ?>" class="form-control"></div>
        <div style="min-width: 190px;">
            <label class="form-label">Pemasok</label>
            <select name="filter_pemasok" class="form-control">
                <option value="">Semua pemasok</option>
                <?php foreach ($pemasokList as $pms): ?>
                    <option value="<?= $pms['id'] ?>" <?= $filterPemasok == $pms['id'] ? 'selected' : '' ?>><?= htmlspecialchars('[' . $pms['kode_pemasok'] . '] ' . $pms['nama_pemasok']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="min-width: 190px;">
            <label class="form-label">Produk</label>
            <select name="filter_item" class="form-control">
                <option value="">Semua produk</option>
                <?php foreach ($itemsList as $it): ?>
                    <option value="<?= $it['id'] ?>" <?= $filterItem == $it['id'] ? 'selected' : '' ?>><?= htmlspecialchars($it['nama_item'] . ' (' . $it['nama_jenis'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Tampilkan</button>
        <a href="index.php?page=timbang_cetak&tab=<?= htmlspecialchars($activeTab) ?>#detailsRiwayatPenerimaan" class="btn btn-outline">Reset</a>
    </form>

    <!-- Pilihan tampilan: Per Surat Jalan vs Rincian Satuan -->
    <div style="display: flex; gap: 8px; margin-bottom: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
        <div style="display: flex; gap: 8px; align-items: center;">
            <?php $modeRiwayat = ($_GET['mode'] ?? '') === 'detail' ? 'detail' : 'group'; ?>
            <button type="button" class="btn <?= $modeRiwayat === 'group' ? 'btn-primary' : 'btn-outline' ?> btn-sm" id="btnViewModeGroup" onclick="switchTableViewMode('group')">Per Surat Jalan</button>
            <button type="button" class="btn <?= $modeRiwayat === 'detail' ? 'btn-primary' : 'btn-outline' ?> btn-sm" id="btnViewModeDetail" onclick="switchTableViewMode('detail')">Rincian Satuan</button>
        </div>
        <span style="font-size: 12.5px; color: var(--text-muted);"><?= number_format($totalPenerimaan) ?> Data</span>
    </div>

    <div class="card">
    <!-- ========================================================================= -->
    <!-- TABEL 1: MODE KELOMPOK BATCH SURAT JALAN (10 PCS / LEMBAR A4)            -->
    <!-- ========================================================================= -->
    <div class="table-responsive" id="containerViewGrouped" style="<?= $modeRiwayat === 'detail' ? 'display: none;' : '' ?>">
        <table class="table-custom" data-no-tools>
            <thead>
                <tr>
                    <?php $U = '\App\Core\Urut'; $tTr = ['page' => 'timbang_cetak', 'tab' => $activeTab, 'mode' => 'group']; ?>
                    <?= $U::th('No. Surat Jalan', 'terima_sj', 'sj', 'p_grp', '', $tTr) ?>
                    <?= $U::th('Waktu', 'terima_sj', 'waktu', 'p_grp', '', $tTr) ?>
                    <?= $U::th('Pemasok', 'terima_sj', 'pemasok', 'p_grp', '', $tTr) ?>
                    <?= $U::th('Nama Produk', 'terima_sj', 'produk', 'p_grp', '', $tTr) ?>
                    <?= $U::th('Jumlah Barcode', 'terima_sj', 'jumlah', 'p_grp', 'style="text-align: right;"', $tTr) ?>
                    <?= $U::th('Netto', 'terima_sj', 'netto', 'p_grp', 'style="text-align: right;"', $tTr) ?>
                    <th>Rentang ID Barcode</th>
                    <th>Status</th>
                    <th style="text-align: center;" class="no-print">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($groupedPenerimaan)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 36px; color: var(--text-muted);">
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
                    <tr<?= $sjBatal ? ' style="opacity: 0.65;"' : '' ?>>
                        <td>
                            <a href="javascript:void(0)" onclick="openSJBarcodeHistoryModal('<?= htmlspecialchars(addslashes($noSj)) ?>')" style="font-weight: 700; color: var(--aktif-fg); text-decoration: none;" title="Klik untuk melihat rincian barcode Surat Jalan ini"><?= htmlspecialchars($noSj) ?></a>
                        </td>
                        <td style="color: var(--text-muted);"><?= date('d/m/Y H:i', strtotime($grp['created_at'])) ?></td>
                        <td><?= htmlspecialchars($pmsNama) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($grp['nama_item']) ?></strong>
                            <div style="font-size: 11.5px; color: var(--text-muted);"><?= htmlspecialchars($grp['nama_jenis']) ?></div>
                        </td>
                        <td style="text-align: right;"><?= $totalPcs ?> box</td>
                        <td style="text-align: right; font-weight: 600;"><?= Helper::formatKg($grp['total_berat']) ?></td>
                        <td style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($grp['min_barcode']) ?> s/d <?= htmlspecialchars($grp['max_barcode']) ?></td>
                        <td>
                            <?php if ($sjBatal): $alasanBatal = preg_replace('/^Penerimaan dibatalkan: /', '', (string)($grp['alasan_void'] ?? '')); ?>
                                <span class="badge badge-danger" title="<?= htmlspecialchars($alasanBatal, ENT_QUOTES) ?>">Dibatalkan</span>
                                <?php if ($alasanBatal !== ''): ?><span style="font-size: 12px; color: var(--text-muted);"> <?= htmlspecialchars($alasanBatal) ?></span><?php endif; ?>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">Selesai</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;" class="no-print">
                            <button type="button" class="btn btn-outline btn-sm" onclick="openSJBarcodeHistoryModal('<?= htmlspecialchars(addslashes($noSj)) ?>')" title="Lihat Rincian Barcode Surat Jalan <?= htmlspecialchars($noSj) ?>">Detail</button>
                            <?php if (!$sjBatal): ?>
                            <button type="button" class="btn btn-outline btn-sm" onclick="batalDokumen('batal_penerimaan', { no_surat_jalan: '<?= htmlspecialchars(addslashes($noSj), ENT_QUOTES) ?>' }, 'penerimaan <?= htmlspecialchars(addslashes($noSj), ENT_QUOTES) ?>')" style="color: var(--bad-fg); border-color: rgba(220,38,38,0.35);" title="Batalkan dokumen">Batalkan</button>
                            <?php else: ?>
                            <button type="button" class="btn btn-outline btn-sm" disabled style="opacity: .45; cursor: not-allowed;" title="Dokumen sudah dibatalkan">Batalkan</button>
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
    <div class="table-responsive" id="containerViewDetail" style="<?= $modeRiwayat === 'detail' ? '' : 'display: none;' ?>">
        <table class="table-custom" id="allPenerimaanTable" data-no-tools>
            <thead>
                <tr>
                    <?php $tTd = ['page' => 'timbang_cetak', 'tab' => $activeTab, 'mode' => 'detail']; ?>
                    <?= $U::th('ID Barcode', 'terima_box', 'barcode', 'p', 'style="width: 155px;"', $tTd) ?>
                    <?= $U::th('Pemasok', 'terima_box', 'pemasok', 'p', '', $tTd) ?>
                    <?= $U::th('Nama Produk', 'terima_box', 'produk', 'p', '', $tTd) ?>
                    <?= $U::th('Netto (Kg)', 'terima_box', 'netto', 'p', 'style="text-align: right;"', $tTd) ?>
                    <?= $U::th('No. Surat Jalan', 'terima_box', 'sj', 'p', '', $tTd) ?>
                    <?= $U::th('Waktu Penerimaan', 'terima_box', 'waktu', 'p', '', $tTd) ?>
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
                        <td><strong><?= htmlspecialchars($lbl['id_label']) ?></strong></td>
                        <td><?= htmlspecialchars($lblPmsNama) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($lbl['nama_item']) ?></strong>
                            <div style="font-size: 11.5px; color: var(--text-muted);"><?= htmlspecialchars($lbl['nama_jenis'] ?? '') ?></div>
                        </td>
                        <td style="text-align: right; font-weight: 600;"><?= Helper::formatKg($netto) ?></td>
                        <td><?= htmlspecialchars($noSj) ?></td>
                        <td style="color: var(--text-muted);"><?= Helper::formatDateTime($lbl['created_at']) ?></td>
                        <td style="text-align: center;" class="no-print">
                            <button type="button" class="btn btn-outline btn-sm" onclick="reprintBarcodeDirect('<?= htmlspecialchars($lbl['id_label']) ?>')" title="Cetak Barcode Box (Format Stiker 70x50 mm)">Cetak</button>
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
        if ($modeRiwayat === 'detail') $baseUrl .= "&mode=detail";
    ?>
    <?php
    require_once __DIR__ . '/layout/pager_riwayat.php';
    renderPagerRiwayat($pageNumber, $totalPages, 'Menampilkan ' . ($totalPenerimaan ? $offset + 1 : 0) . '-' . min($offset + $perPage, $totalPenerimaan) . ' dari ' . number_format($totalPenerimaan) . ' penerimaan', $baseUrl, 'p');
    ?>
    </div>
</div>
</div>

<!-- ========================================================================= -->
<!-- MODAL POPUP: PERINGATAN BARCODE SUDAH DIGUNAKAN (ANTI-DUPLIKAT DATA)      -->
<!-- ========================================================================= -->
<div id="modalBarcodeAlreadyUsed" style="display: none; position: fixed; inset: 0; z-index: 99999; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(5px); align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--card-bg, #ffffff); border-radius: 16px; max-width: 520px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45); border: 2px solid var(--bad-solid); overflow: hidden; animation: popIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
        <!-- Header Banner Merah Menyala -->
        <div style="background: var(--bad-solid); padding: 18px 22px; color: #ffffff; display: flex; align-items: center; gap: 14px;">
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
            <div style="background: var(--bad-bg); border: 1.5px solid var(--border-hover); border-radius: 10px; padding: 12px 16px; margin-bottom: 16px;">
                <div style="font-size: 13.5px; color: var(--bad-fg); line-height: 1.5; font-weight: 600;" id="usedModalWarningText">
                    Barcode ini <strong>sudah pernah discan dan ditimbang</strong>. Sistem menolak penimbangan ulang agar tidak terjadi pencatatan ganda pada stok fisik gudang.
                </div>
            </div>

            <!-- Rincian Data Barcode yang Ditolak -->
            <div style="background: var(--bg-muted, #f8fafc); border: 1px solid var(--border, var(--border)); border-radius: 10px; padding: 14px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px dashed var(--border, var(--border)); margin-bottom: 8px;">
                    <span style="color: var(--text-muted, #64748b);">ID Barcode:</span>
                    <strong id="usedBarcodeId" style="font-family: 'JetBrains Mono', monospace; font-size: 14px; color: var(--bad-fg);">-</strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px dashed var(--border, var(--border)); margin-bottom: 8px;">
                    <span style="color: var(--text-muted, #64748b);">Nama Komoditas:</span>
                    <strong id="usedBarcodeItem" style="color: var(--text-main, #1e293b);">-</strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px dashed var(--border, var(--border)); margin-bottom: 8px;">
                    <span style="color: var(--text-muted, #64748b);">Pemasok / Mitra:</span>
                    <span id="usedBarcodeMitra" style="font-weight: 700; color: var(--text-main, #1e293b);">-</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px dashed var(--border, var(--border)); margin-bottom: 8px;">
                    <span style="color: var(--text-muted, #64748b);">No. Surat Jalan:</span>
                    <strong id="usedBarcodeSJ" style="color: var(--primary, #0284c7);">-</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 4px;">
                    <span style="color: var(--text-muted, #64748b); font-weight: 700;">Bobot Tercatat:</span>
                    <span class="badge badge-success" id="usedBarcodeWeight" style="font-size: 13.5px; font-weight: 900; padding: 4px 10px;">0.00 KG</span>
                </div>
            </div>

            <!-- Tombol Tutup / Kembali -->
            <div style="margin-top: 20px;">
                <button type="button" id="btnCloseUsedModal" class="btn btn-danger" style="width: 100%; height: 46px; font-size: 14.5px; font-weight: 800; justify-content: center; border-radius: 10px; background: var(--bad-solid); border-color: var(--bad-solid); cursor: pointer;">
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
    // Pintasan "Atur harga": ikut produk dan pemasok yang sedang dipilih
    setInterval(function () {
        var tautan = document.getElementById('linkHargaBeli');
        if (!tautan) return;
        var item = (document.getElementById('onestop_item_id') || {}).value || '';
        var pemasok = (document.getElementById('onestop_pemasok_id') || {}).value || '';
        tautan.href = 'index.php?page=master&tab=harga_pemasok' + (item ? '&item=' + encodeURIComponent(item) + (pemasok ? '&pihak=' + encodeURIComponent(pemasok) : '') : '');
    }, 500);
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

    // Waktu transaksi (input datetime-local): batas atas = sekarang menurut jam komputer
    function waktuSekarangLokal() {
        const d = new Date();
        const p = n => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
    }
    // Isian waktu terisi waktu sekarang dan ikut berjalan (tiap 30 detik) sampai user mengubahnya sendiri.
    // Selama belum diubah, server memakai waktu saat disimpan; setelah diubah, waktu pilihan user yang dipakai.
    function pasangWaktuOtomatis(id) {
        const inp = document.getElementById(id);
        if (!inp) return;
        inp.dataset.manual = '0';
        inp.value = waktuSekarangLokal();
        if (!inp._jam) inp._jam = setInterval(() => { if (inp.dataset.manual !== '1') inp.value = waktuSekarangLokal(); }, 30000);
        perbaruiTombolPlus(id);
    }
    function tandaiWaktuManual(id) {
        const inp = document.getElementById(id);
        if (!inp) return;
        if (!inp.value) pasangWaktuOtomatis(id); // dikosongkan = kembali ke waktu sekarang
        else inp.dataset.manual = '1';
        perbaruiTombolPlus(id);
    }
    // Tombol + nonaktif bila isian masih waktu sekarang (otomatis) atau sudah tanggal hari ini: tidak bisa maju lagi
    function perbaruiTombolPlus(id) {
        const inp = document.getElementById(id);
        const btn = inp && inp.parentNode.querySelector("[title^='Maju 1 hari']");
        if (!btn) return;
        const mati = inp.dataset.manual !== '1' || (inp.value || '').slice(0, 10) >= waktuSekarangLokal().slice(0, 10);
        btn.disabled = mati;
        btn.style.opacity = mati ? '0.4' : '';
        btn.style.cursor = mati ? 'not-allowed' : '';
    }
    // Tombol - / + di samping isian waktu: geser tanggal 1 hari dengan jam yang sama, tidak melewati waktu sekarang
    function geserHariWaktu(id, delta) {
        const inp = document.getElementById(id);
        if (!inp) return;
        const d = new Date(inp.value || waktuSekarangLokal());
        if (isNaN(d)) return;
        d.setDate(d.getDate() + delta);
        const p = n => String(n).padStart(2, '0');
        const v = d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
        // sampai hari ini atau lewat: kembali ke waktu sekarang (otomatis)
        inp.value = v.slice(0, 10) >= waktuSekarangLokal().slice(0, 10) ? '' : v;
    }
    function nilaiWaktuKirim(id) {
        const inp = document.getElementById(id);
        return inp && inp.dataset.manual === '1' ? inp.value : '';
    }
    document.addEventListener('DOMContentLoaded', () => { pasangWaktuOtomatis('onestop_waktu'); });
    // Waktu penerimaan berubah: nomor surat jalan ikut tanggalnya, dan beri tanda bila tanggalnya bukan hari ini
    function onOneStopWaktuChanged() {
        tandaiWaktuManual('onestop_waktu');
        const inp = document.getElementById('onestop_waktu');
        const info = document.getElementById('onestop_waktu_info');
        const tgl = inp && inp.value ? inp.value.slice(0, 10) : '';
        const hariIni = waktuSekarangLokal().slice(0, 10);
        if (info) info.textContent = tgl && tgl !== hariIni ? 'Dicatat ke tanggal ' + tgl.split('-').reverse().join('/') : '';
        onOneStopPemasokChanged();
    }

    // Ubah Nomor Surat Jalan saat Pemasok Berubah
    async function onOneStopPemasokChanged() {
        const selPemasok = document.getElementById('onestop_pemasok_id');
        const inpSj = document.getElementById('onestop_no_sj');
        if (!selPemasok || !inpSj) return;

        const opt = selPemasok.options[selPemasok.selectedIndex];
        const kode = opt ? (opt.dataset.kode || 'PMS01') : 'PMS01';

        try {
            const tglPilih = (document.getElementById('onestop_waktu')?.value || '').slice(0, 10);
            const resp = await fetch('index.php?action=gen_surat_jalan&kode=' + encodeURIComponent(kode) + '&tanggal=' + encodeURIComponent(tglPilih));
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
            fd.append('waktu', nilaiWaktuKirim('onestop_waktu'));

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
        // Kode produk di kepala stiker: makin panjang makin kecil hurufnya agar tidak keluar dari kotak;
        // bila sangat panjang, dipecah dua baris dengan huruf kecil.
        const skuLebar = 54, skuK = 0.8; // lebar tersedia (px) dan perkiraan lebar huruf kapital tebal (em)
        const barisSku = (teks, fs) => {
            let n = 1, kini = 0;
            for (const w of teks.split(/\s+/)) {
                const lw = w.length * fs * skuK;
                if (lw > skuLebar) return 99; // satu kata saja sudah melebihi lebar kotak
                const sp = kini ? fs * skuK : 0;
                if (kini + sp + lw <= skuLebar) kini += sp + lw; else { n++; kini = lw; }
            }
            return n;
        };
        let skuFont = 12.5, skuDuaBaris = false;
        while (skuFont >= 8 && barisSku(skuCode, skuFont) > 1) skuFont -= 0.5;
        if (skuFont < 8) {
            skuDuaBaris = true;
            skuFont = 7;
            while (skuFont > 5.5 && barisSku(skuCode, skuFont) > 2) skuFont -= 0.25;
        }
        skuFont = Math.round(skuFont * 100) / 100;
        const skuGaya = `font-size: ${skuFont}px; max-width: 54px; ` + (skuDuaBaris ? 'white-space: normal; text-align: center; line-height: 1.05; word-break: break-word;' : 'white-space: nowrap; line-height: 1;');
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

        // Kode jenis + jumlah box produk itu dalam surat jalan (pojok kiri bawah), mis. PRD01-7; kode jenis dari segmen kedua ID barcode
        let kodeUrut = '';
        const urutJenis = parseInt(lbl.urut_jenis, 10);
        if (urutJenis > 0) {
            const segJenis = barcodeText.split('-')[1] || '';
            kodeUrut = (segJenis ? segJenis + '-' : '') + urutJenis;
        }

        return `
            <div class="square-sheet-card" style="box-sizing: border-box; width: 64mm; height: 46mm; border: 2px solid #000; background: #fff; color: #000; font-family: 'Plus Jakarta Sans', Arial, Helvetica, sans-serif; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; page-break-inside: avoid; margin: 0; padding: 0;">
                <div style="padding: 1.2mm 2.2mm; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #000; flex-shrink: 0; box-sizing: border-box;">
                    <span style="font-size: 13.5px; font-weight: 800; letter-spacing: -0.2px; color: #000; line-height: 1; white-space: nowrap;">AR_BAROKAH</span>
                    <div style="border: 1.8px solid #000; border-radius: 2px; display: inline-flex; align-items: stretch; height: 20px; box-sizing: border-box;">
                        <span style="padding: 0 6px; font-weight: 800; font-size: 12.5px; border-right: 1.8px solid #000; display: flex; align-items: center; justify-content: center; line-height: 1;">#${padIndex}</span>
                        <span style="padding: 0 4px; font-weight: 800; ${skuGaya} display: flex; align-items: center; justify-content: center; text-transform: uppercase;">${skuDuaBaris ? '<span style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">' + skuCode + '</span>' : skuCode}</span>
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
                        <div class="square-barcode-svg" style="width: 100%; height: 17mm; max-height: 17mm; display: flex; align-items: center; justify-content: center; overflow: hidden; background: var(--bg-card);">
                            ${barcodeSvg}
                        </div>
                        <div style="font-size: 12px; font-weight: 800; font-family: 'JetBrains Mono', monospace; letter-spacing: 0.8px; color: #000; margin-top: 0.5mm; text-align: center; line-height: 1;">
                            ${displayBarcodeNo}
                        </div>
                    </div>
                    <!-- Baris bawah (tinggi tetap agar tata letak stiker sama): kode jenis + jumlah box di kiri -->
                    <div style="height: 9px; padding-bottom: 0.5mm; flex-shrink: 0; display: flex; align-items: flex-end;">
                        <span style="font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 800; color: #000; line-height: 9px; letter-spacing: 0.2px;">${kodeUrut}</span>
                    </div>
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

        // Kaki tabel: subtotal per produk (kg dan nominal rupiah) lalu GRAND TOTAL
        const foot = document.getElementById('inboundTotalFoot');
        if (foot) {
            foot.style.display = totalBox >= 1 ? '' : 'none';
            const rp = (n) => 'Rp ' + new Intl.NumberFormat('en-US').format(Math.round(parseFloat(n || 0)));
            const esc = (t) => String(t == null ? '' : t).split('&').join('&amp;').split('<').join('&lt;').split('>').join('&gt;');
            const mono = "text-align: right; white-space: nowrap; font-family: JetBrains Mono, monospace;";
            let html = '';
            (summary.products || []).forEach((p) => {
                html += '<tr style="background: var(--bg-card);">' +
                    '<td colspan="2" style="text-align: right; color: var(--text-muted); font-size: 12px;">Subtotal <strong style="color: var(--text-main);">' + esc(p.nama_item) + '</strong> (' + esc(p.nama_jenis || '-') + ') &middot; ' + p.box_count + ' box</td>' +
                    '<td style="' + mono + ' font-weight: 700; font-size: 12.5px;">' + parseFloat(p.total_berat || 0).toFixed(2) + ' kg</td>' +
                    '<td style="' + mono + ' font-weight: 700; font-size: 12.5px;">' + rp(p.total_nilai) + '</td>' +
                    '<td></td></tr>';
            });
            html += '<tr style="background: var(--bg-muted);">' +
                '<td colspan="2" style="text-align: right; font-weight: 800; color: var(--text-main); font-size: 12px; text-transform: uppercase;">Grand total &middot; ' + totalBox + ' box</td>' +
                '<td style="' + mono + ' font-weight: 800; font-size: 12.5px;">' + parseFloat(summary.total_berat || 0).toFixed(2) + ' kg</td>' +
                '<td style="' + mono + ' font-weight: 800; font-size: 13px; color: var(--ok-fg);">' + rp(summary.total_nilai) + '</td>' +
                '<td></td></tr>';
            foot.innerHTML = html;
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
                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 40px 20px;">
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
                    const hrg = parseFloat(b.harga_beli_per_kg || 0);
                    const nominal = parseFloat(b.total_nilai || 0);
                    const fmtRp = (n) => 'Rp ' + new Intl.NumberFormat('en-US').format(Math.round(n));
                    row.innerHTML = `
                        <td style="color: var(--text-muted); font-size: 12px; text-align: center; padding-left: 4px; padding-right: 4px;">${index++}</td>
                        <td style="white-space: nowrap;">
                            <strong style="font-family: 'JetBrains Mono', monospace; font-size: 11.5px; color: var(--primary);">${b.id_label}</strong>
                            <div style="font-size: 11px; color: var(--text-muted); white-space: normal;"><span style="color: var(--text-main); font-weight: 700;">${b.nama_item}</span> &middot; ${b.nama_jenis || '-'}</div>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <div style="font-weight: 800; font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--ok-fg);">${netto.toFixed(2)} kg</div>
                            <div style="font-size: 10.5px; color: var(--text-muted);">${hrg > 0 ? '@ ' + fmtRp(hrg) : '-'}</div>
                        </td>
                        <td style="text-align: right; white-space: nowrap; font-weight: 700; font-family: 'JetBrains Mono', monospace; font-size: 12.5px;">${nominal > 0 ? fmtRp(nominal) : '-'}</td>
                        <td style="text-align: center; padding-left: 4px; padding-right: 4px; position: sticky; right: 0; background: var(--bg-card, #ffffff); z-index: 5; box-shadow: -2px 0 4px rgba(0,0,0,0.04);">
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

        const hapus = await window.showAppConfirm({
            title: 'Hapus box ini?',
            icon: 'warning',
            html: `Box barcode <strong>${barcode}</strong> akan dihapus dari daftar sesi ini.`,
            confirmButtonText: 'Ya, Hapus',
            customClass: { confirmButton: 'btn btn-primary', cancelButton: 'btn btn-outline' }
        });
        if (!hapus.isConfirmed) return;

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
            Swal.fire({ icon: 'info', title: 'Cetak barcode dulu', text: 'Silakan cetak barcode terlebih dahulu sebelum mencetak Surat Jalan.', confirmButtonText: 'Mengerti' });
            return;
        }

        const selesai = await window.showAppConfirm({
            title: 'Selesaikan sesi ini?',
            html: `Surat Jalan <strong>${noSj}</strong> (${boxCount} box) akan diselesaikan dan dokumen penerimaan dicetak.`,
            confirmButtonText: 'Ya, Selesaikan'
        });
        if (!selesai.isConfirmed) return;

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

                // Perbarui ke Surat Jalan berikutnya untuk truk baru (waktu kembali ke sekarang)
                const pakaiWaktuLain = nilaiWaktuKirim('onestop_waktu') !== '';
                pasangWaktuOtomatis('onestop_waktu');
                const infoWaktu = document.getElementById('onestop_waktu_info');
                if (infoWaktu) infoWaktu.textContent = '';
                if (pakaiWaktuLain) {
                    onOneStopPemasokChanged();
                } else if (inpSj && data.next_surat_jalan) {
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
        return new Intl.NumberFormat('en-US').format(num);
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
                        background: var(--bg-card) !important;
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
                        background: var(--bg-card) !important;
                    }
                    .square-barcode-svg svg {
                        width: 100% !important;
                        height: 17mm !important;
                        max-height: 17mm !important;
                    }
                }
            `;
        }
        terapkanUkuranStiker(mode);
    }

    // Ukuran stiker barcode: 70x50 (bawaan, aturan di atas tidak diubah) atau 150x100.
    // Mode 150x100 hanya menimpa saat cetak: halaman 150x100 mm dan isi stiker 70x50 yang sama diperbesar 2,1x di tengah.
    function ukuranStiker() {
        try { return localStorage.getItem('ukuranStiker') === '150x100' ? '150x100' : '70x50'; } catch (e) { return '70x50'; }
    }
    function pilihUkuranStiker(v) {
        try { localStorage.setItem('ukuranStiker', v === '150x100' ? '150x100' : '70x50'); } catch (e) {}
        const sel = document.getElementById('pilihUkuranStiker');
        if (sel) sel.value = ukuranStiker();
    }
    function terapkanUkuranStiker(mode) {
        let el = document.getElementById('gayaStiker150');
        if (mode !== 'single' || ukuranStiker() !== '150x100') { if (el) el.remove(); return; }
        // Ukuran halaman: Chrome memakai @page pertama, jadi aturan dinamis (dibuat ulang tiap cetak) yang diubah ukurannya
        const fmt = document.getElementById('dynamicPrintPageFormat');
        if (fmt) fmt.innerHTML = fmt.innerHTML.replace(/size:\s*70mm 50mm/, 'size: 150mm 100mm');
        if (!el) { el = document.createElement('style'); el.id = 'gayaStiker150'; }
        document.head.appendChild(el); // selalu paling akhir agar menimpa aturan 70x50
        el.innerHTML = `
            @media print {
                body.print-mode-single-thermal .app-layout,
                body.print-mode-single-thermal .app-main-container,
                body.print-mode-single-thermal .main-wrapper,
                body.print-mode-single-thermal #bulkPrintContainer,
                body.print-mode-single-thermal .print-area,
                .app-layout, .app-main-container, .main-wrapper, #bulkPrintContainer, .print-area { max-width: 150mm !important; }
                .bulk-print-sheet.single-thermal-sheet {
                    width: 150mm !important;
                    height: 100mm !important;
                    padding: 0 !important;
                    align-items: center !important;
                    justify-content: center !important;
                }
                .bulk-print-sheet.single-thermal-sheet > * { zoom: 2.1; }
            }
        `;
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
            pageHeader.style.cssText = 'display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #cbd5e1; width: 64mm; padding-bottom: 4px; margin-bottom: 8px; font-size: 11px; font-weight: 800; color: var(--aktif-fg);';
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
        // Mode tampilan ikut di filter dan tautan halaman agar tidak kembali ke Per Surat Jalan
        const form = document.getElementById('formFilterRiwayat');
        if (form) {
            let inp = form.querySelector('input[name="mode"]');
            if (mode === 'detail') {
                if (!inp) { inp = document.createElement('input'); inp.type = 'hidden'; inp.name = 'mode'; form.appendChild(inp); }
                inp.value = 'detail';
            } else if (inp) inp.remove();
        }
        document.querySelectorAll('#detailsRiwayatPenerimaan .sj-halaman a[href]').forEach(a => {
            const u = new URL(a.getAttribute('href'), window.location.href);
            if (mode === 'detail') u.searchParams.set('mode', 'detail'); else u.searchParams.delete('mode');
            a.setAttribute('href', 'index.php' + u.search + u.hash);
        });
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

    // Di Riwayat Penerimaan, setiap kali cetak ditanya dulu ukuran stikernya (70x50 atau 150x100).
    // Alur timbang tidak ditanya agar tetap cepat; di sana memakai pilihan Ukuran stiker barcode di atas halaman.
    function riwayatPenerimaanTampil() {
        const s = document.getElementById('sectionRiwayatPenerimaan');
        return !!(s && s.offsetParent !== null);
    }
    async function tanyaUkuranStiker() {
        if (!riwayatPenerimaanTampil() || typeof Swal === 'undefined') return true;
        const kini = ukuranStiker();
        const r = await Swal.fire({
            title: 'Ukuran stiker barcode',
            text: 'Pilih ukuran kertas stiker yang terpasang di printer.',
            icon: 'question',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: '70 x 50 mm',
            denyButtonText: '150 x 100 mm',
            cancelButtonText: 'Batal',
            denyButtonColor: '#3085d6',
            focusConfirm: kini === '70x50',
            focusDeny: kini === '150x100',
            didOpen: () => { const c = Swal.getContainer(); if (c) c.style.zIndex = '10050'; }, // di atas modal rincian
        });
        if (r.isConfirmed) { pilihUkuranStiker('70x50'); return true; }
        if (r.isDenied) { pilihUkuranStiker('150x100'); return true; }
        return false;
    }

    // Cetak Kelompok Surat Jalan
    async function reprintGroupSJ(noSj, status = 'all') {
        if (!(await tanyaUkuranStiker())) return;
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
        if (!(await tanyaUkuranStiker())) return;
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
                <span style="${isPlaceholder ? 'color: var(--text-muted); font-weight: 500;' : 'color: var(--text-main); font-weight: 700;'} overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${text}</span>
                <i data-lucide="chevron-down" class="shadcn-select-chevron" style="width: 16px; height: 16px; color: var(--text-muted); flex-shrink: 0; transition: transform 0.2s;"></i>
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
                    <span style="${isPlaceholder ? 'color: var(--text-muted);' : ''}">${opt.textContent.trim()}</span>
                    ${isSelected && !isPlaceholder ? '<i data-lucide="check" style="width: 14px; height: 14px; color: var(--aktif-fg); flex-shrink: 0;"></i>' : ''}
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
                                <td style="text-align: right; font-weight: 800; color: var(--ok-fg);">${parseFloat(data.label.berat_netto).toFixed(2)} kg</td>
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
                        <div style="background: rgba(2, 132, 199, 0.08); border-left: 4px solid var(--primary); padding: 10px; border-radius: 6px; font-size: 12.5px;">
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
                        bridgeBanner.style.cssText = 'background: var(--ok-bg); border: 1.5px solid #10b981; border-radius: 8px; padding: 12px 14px; margin-bottom: 6px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;';
                        bridgeBanner.innerHTML = `
                            <div>
                                <div style="font-weight: 800; font-size: 13.5px; color: var(--ok-fg);">✓ ${data.labels.length} Barcode Selesai Dibuat!</div>
                                <div style="font-size: 12px; color: var(--ok-fg); margin-top: 2px;">Cetak stiker, tempelkan ke keranjang, lalu timbang bobot riilnya.</div>
                            </div>
                            <button type="button" class="btn btn-success btn-sm" onclick="window.location.href='index.php?page=timbang_cetak&tab=scantimbang'" style="font-weight: 800; font-size: 12px; background: var(--ok-solid); border-color: var(--ok-solid); cursor: pointer;">
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
                            btnCetakAll.innerHTML = `<i data-lucide="printer" style="width: 15px; height: 15px;"></i><span>Cetak Barcode (${totalBoxes} Box)</span>`;
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
                `<p style="margin-bottom: 8px;">• Total Sudah Ditimbang: <strong style="color: var(--ok-fg);">${sudah}</strong> Keranjang</p>`;

            if (sisa > 0) {
                htmlPesan += `<p style="margin-bottom: 12px; color: var(--warn-fg);">• Sisa Belum Ditimbang: <strong>${sisa}</strong> Keranjang</p>` +
                    `<div style="background: rgba(245, 158, 11, 0.1); border-left: 4px solid var(--warn-solid); padding: 10px; border-radius: 6px; margin-top: 10px; font-size: 13px;">` +
                    `⚠️ <strong>Perhatian:</strong> Masih ada <strong>${sisa} keranjang</strong> yang belum ditimbang pada Surat Jalan ini.<br>` +
                    `Apakah Anda yakin ingin menyelesaikan proses timbang sekarang dan mengaktifkan cetak surat jalan penerimaan?` +
                    `</div></div>`;
            } else {
                htmlPesan += `<div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--ok-solid); padding: 10px; border-radius: 6px; margin-top: 10px; font-size: 13px;">` +
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
                        badgeSJTotal.innerText = `Total: ${data.total_pcs} Box`;
                    }
                    if (badgeSJSisa) {
                        badgeSJSisa.dataset.count = data.belum_ditimbang;
                        badgeSJSisa.innerText = `⏳ Belum: ${data.belum_ditimbang} Box`;
                    }
                    if (badgeSJSelesai) {
                        badgeSJSelesai.dataset.count = data.sudah_ditimbang;
                        badgeSJSelesai.innerText = `✓ Sudah: ${data.sudah_ditimbang} Box (${parseFloat(data.total_berat).toFixed(2)} KG)`;
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
            if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 36px; color: var(--text-muted); font-size: 13px;">Memuat data box Surat Jalan...</td></tr>';

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
                    if (tbody) tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--danger); padding: 30px;">Gagal memuat: ${res.message || 'Data tidak ditemukan'}</td></tr>`;
                }
            } catch (err) {
                if (tbody) tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--danger); padding: 30px;">Kesalahan jaringan: ${err.message}</td></tr>`;
            }

            if (window.lucide) window.lucide.createIcons();
        };

        window.eksporRincianTerima = function() {
            const no = (currentModalSJData && currentModalSJData.no_surat_jalan) || 'Surat_Jalan_Masuk';
            window.eksporExcel('Rincian_' + no, 'tabelRincianTerima');
        };

        window.closeSJBarcodeListModal = function() {
            const modal = document.getElementById('modalSJBarcodeList');
            if (modal) modal.style.display = 'none';
        };

        window.renderModalBoxesTable = function(labels) {
            const tbody = document.getElementById('modalSJTableBody');
            if (!tbody) return;

            // Baris total: jumlah box, netto, dan nominal (berat x harga beli). Box yang dibatalkan (VOID) tidak dihitung nominalnya.
            const rpTerima = n => 'Rp ' + Math.round(n).toLocaleString('en-US');
            const foot = document.getElementById('modalSJTableFoot');
            if (foot) {
                const n = (labels || []).length;
                const sum = (labels || []).reduce((a, l) => a + parseFloat(l.berat_netto || 0), 0);
                const nom = (labels || []).reduce((a, l) => a + (l.status === 'VOID' ? 0 : parseFloat(l.berat_netto || 0) * parseFloat(l.harga_beli_per_kg || 0)), 0);
                foot.style.display = n >= 1 ? '' : 'none';
                const c = document.getElementById('modalSJTotalCount');
                const v = document.getElementById('modalSJTotalNetto');
                const nm = document.getElementById('modalSJTotalNominal');
                if (c) c.innerText = n;
                if (v) v.innerText = sum.toFixed(2) + ' kg';
                if (nm) nm.innerText = rpTerima(nom);
            }

            if (!labels || !labels.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 36px 20px; color: var(--text-muted);">
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
                    <td style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; font-size: 13px; color: var(--ok-fg);">
                        ${netto.toFixed(2)} kg
                    </td>
                    <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px;">
                        ${parseFloat(lbl.harga_beli_per_kg || 0) > 0 ? rpTerima(parseFloat(lbl.harga_beli_per_kg)) : '-'}
                    </td>
                    <td style="text-align: right; font-weight: 700; font-family: 'JetBrains Mono', monospace; font-size: 12.5px;">
                        ${lbl.status === 'VOID' ? '<span style="color: var(--text-muted);" title="Box dibatalkan">batal</span>' : (netto * parseFloat(lbl.harga_beli_per_kg || 0) > 0 ? rpTerima(netto * parseFloat(lbl.harga_beli_per_kg)) : '-')}
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
                                badgeSJSisa.innerText = '⏳ Belum: ' + sisa + ' Box';
                                if (badgeSJSelesai) {
                                    badgeSJSelesai.dataset.count = sudah;
                                    badgeSJSelesai.innerText = '✓ Sudah: ' + sudah + ' Box';
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
                    <div style="background: var(--bg-card); padding: 14px 24px; border-radius: 12px; box-shadow: var(--shadow-lg); display: flex; align-items: center; gap: 12px; border: 1.5px solid var(--border);">
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
                if (href.startsWith('index.php') && (href.includes('filter_') || href.includes('search=') || href.includes('p=') || href.includes('p_grp=') || href.includes('urut_'))) {
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
