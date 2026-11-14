<?php
/**
 * Stasiun Pengeluaran Barang ke Mitra (Gudang Operasional)
 * Fitur:
 * 1. Parameter Pengeluaran (Pemasok Asal Stok di Gudang & Mitra Bisnis Penerima)
 * 2. Visualisasi Sisa Stok Fisik Pemasok di Gudang (Diposisikan di panel kanan bersama detail barang)
 * 3. Scan Barcode Kemasan Catch-Weight (Verifikasi kepemilikan pemasok & berat netto)
 * 4. Penerbitan Surat Jalan Pengeluaran Resmi & Sinkronisasi Accurate
 */

use App\Core\Helper;
use App\Models\Mitra;
use App\Models\Pemasok;
use App\Models\Label;
use App\Models\Transaksi;
use App\Services\StockService;

$pageTitle = 'Pengeluaran Barang ke Mitra';
$pemasokList = Pemasok::getAll();
$mitraList = Mitra::getAll();
$activeSampleLabels = Label::getRecent(10);

$outboundTab = $_GET['tab'] ?? '';
$outboundSubTab = $_GET['subtab'] ?? '';

if (empty($outboundTab)) {
    if (isset($_GET['filter_status']) || isset($_GET['filter_pemasok']) || isset($_GET['filter_mitra']) || isset($_GET['search']) || isset($_GET['p'])) {
        $outboundTab = 'riwayat';
        if (empty($outboundSubTab)) $outboundSubTab = 'barang';
    } elseif (isset($_GET['h_search']) || isset($_GET['h_pemasok']) || isset($_GET['h_mitra']) || isset($_GET['h_start_date']) || isset($_GET['h_end_date']) || isset($_GET['h_date_range']) || isset($_GET['hp'])) {
        $outboundTab = 'riwayat';
        if (empty($outboundSubTab)) $outboundSubTab = 'sj';
    } else {
        $outboundTab = 'scan';
    }
} elseif ($outboundTab === 'semua_barang') {
    $outboundTab = 'riwayat';
    if (empty($outboundSubTab)) $outboundSubTab = 'barang';
}

if (!in_array($outboundTab, ['scan', 'riwayat'])) {
    $outboundTab = 'scan';
}

if ($outboundTab === 'riwayat' && empty($outboundSubTab)) {
    if (isset($_GET['filter_status']) || isset($_GET['filter_pemasok']) || isset($_GET['filter_mitra']) || isset($_GET['search']) || isset($_GET['p'])) {
        $outboundSubTab = 'barang';
    } else {
        $outboundSubTab = 'sj';
    }
}
if (!in_array($outboundSubTab, ['sj', 'barang'])) {
    $outboundSubTab = 'sj';
}

// Default Pemasok & No. Surat Jalan Keluar Otomatis
$firstPemasok = $pemasokList[0] ?? null;
$firstPemasokKode = $firstPemasok['kode_pemasok'] ?? 'PMS';
$defaultOutboundSj = Transaksi::generateNextSuratJalanKeluar($firstPemasokKode);

// Riwayat Surat Jalan Pengeluaran Barang
$hSearch     = trim($_GET['h_search'] ?? '');
$hPemasok    = isset($_GET['h_pemasok']) && $_GET['h_pemasok'] !== '' ? (int)$_GET['h_pemasok'] : null;
$hMitra      = isset($_GET['h_mitra']) && $_GET['h_mitra'] !== '' ? (int)$_GET['h_mitra'] : null;
$hStartDate  = trim($_GET['h_start_date'] ?? '');
$hEndDate    = trim($_GET['h_end_date'] ?? '');
$hDatePreset = trim($_GET['h_date_range'] ?? '');
$hPage       = max(1, (int)($_GET['hp'] ?? 1));
$hPerPage    = 10;
$hOffset     = ($hPage - 1) * $hPerPage;

if ($hDatePreset === 'today') {
    $hStartDate = date('Y-m-d');
    $hEndDate = date('Y-m-d');
} elseif ($hDatePreset === 'yesterday') {
    $hStartDate = date('Y-m-d', strtotime('-1 day'));
    $hEndDate = date('Y-m-d', strtotime('-1 day'));
} elseif ($hDatePreset === '7days') {
    $hStartDate = date('Y-m-d', strtotime('-7 days'));
    $hEndDate = date('Y-m-d');
} elseif ($hDatePreset === 'this_month') {
    $hStartDate = date('Y-m-01');
    $hEndDate = date('Y-m-d');
}

$hFilters = [];
if (!empty($hSearch)) $hFilters['search'] = $hSearch;
if ($hPemasok > 0) $hFilters['pemasok_id'] = $hPemasok;
if ($hMitra > 0) $hFilters['mitra_id'] = $hMitra;
if (!empty($hStartDate)) $hFilters['start_date'] = $hStartDate;
if (!empty($hEndDate)) $hFilters['end_date'] = $hEndDate;

$historyOutbound = Transaksi::getHistoryPengeluaran($hFilters, $hPerPage, $hOffset);
$totalHistory = Transaksi::countHistoryPengeluaran($hFilters);
$totalHistoryPages = max(1, ceil($totalHistory / $hPerPage));

// Parameter Filter untuk Rincian Barang Keluar (Pure Barang Keluar)
$filterPemasok  = isset($_GET['filter_pemasok']) && $_GET['filter_pemasok'] !== '' ? (int)$_GET['filter_pemasok'] : null;
$filterMitra    = isset($_GET['filter_mitra']) && $_GET['filter_mitra'] !== '' ? (int)$_GET['filter_mitra'] : null;
$search         = trim($_GET['search'] ?? '');
$page           = max(1, (int)($_GET['p'] ?? 1));
$perPage        = 15;
$offset         = ($page - 1) * $perPage;

$filterParams = [
    'status'     => 'KELUAR', // Pure hanya menampilkan barang yang sudah keluar
    'pemasok_id' => $filterPemasok,
    'mitra_id'   => $filterMitra,
    'search'     => $search
];

$allGoods = Label::getFiltered($filterParams, $perPage, $offset);
$totalCount = Label::countFiltered($filterParams);
$totalPages = max(1, ceil($totalCount / $perPage));

/**
 * Bar filter inline untuk Riwayat Pengeluaran (tata letak sama dengan Riwayat Penerimaan).
 * Keys: subtab, search_name, search_val, start_name?, start_val?, end_name?, end_val?,
 *       pemasok_name, pemasok_val, mitra_name, mitra_val, placeholder, form_id
 */
function renderOutboundFilterBar(array $c, array $pemasokList, array $mitraList): void {
    $hasDates = !empty($c['start_name']);
    ?>
    <div class="no-print" style="background: var(--bg-muted); padding: 16px; border-bottom: 1.5px solid var(--border);">
        <form method="GET" action="index.php" id="<?= htmlspecialchars($c['form_id']) ?>" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="page" value="pengeluaran">
            <input type="hidden" name="tab" value="riwayat">
            <input type="hidden" name="subtab" value="<?= htmlspecialchars($c['subtab']) ?>">

            <div style="flex: 2; min-width: 200px;">
                <div style="position: relative;">
                    <input type="text" name="<?= $c['search_name'] ?>" class="form-control" placeholder="<?= htmlspecialchars($c['placeholder']) ?>" value="<?= htmlspecialchars($c['search_val']) ?>" style="padding-left: 36px; height: 38px; font-size: 13px;">
                    <i data-lucide="search" style="position: absolute; left: 12px; top: 11px; width: 15px; height: 15px; color: var(--text-muted);"></i>
                </div>
            </div>

            <?php if ($hasDates): ?>
            <div style="display: flex; align-items: center; gap: 6px; background: var(--bg-card); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 0 10px; height: 38px; min-width: 145px; flex: 1;">
                <span style="font-size: 11.5px; font-weight: 700; color: var(--text-muted); white-space: nowrap;">Mulai:</span>
                <input type="date" name="<?= $c['start_name'] ?>" value="<?= htmlspecialchars($c['start_val']) ?>" style="border: none; background: transparent; font-size: 12px; font-weight: 600; color: var(--text-main); height: 100%; padding: 0; outline: none; cursor: pointer; flex: 1; min-width: 0;" title="Tanggal mulai">
            </div>
            <div style="display: flex; align-items: center; gap: 6px; background: var(--bg-card); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 0 10px; height: 38px; min-width: 145px; flex: 1;">
                <span style="font-size: 11.5px; font-weight: 700; color: var(--text-muted); white-space: nowrap;">Sampai:</span>
                <input type="date" name="<?= $c['end_name'] ?>" value="<?= htmlspecialchars($c['end_val']) ?>" style="border: none; background: transparent; font-size: 12px; font-weight: 600; color: var(--text-main); height: 100%; padding: 0; outline: none; cursor: pointer; flex: 1; min-width: 0;" title="Tanggal sampai">
            </div>
            <?php endif; ?>

            <div style="flex: 1.3; min-width: 150px;">
                <select name="<?= $c['pemasok_name'] ?>" class="form-control" style="height: 38px; font-size: 12.5px; font-weight: 600;">
                    <option value="">-- Semua Pemasok --</option>
                    <?php foreach ($pemasokList as $pms): ?>
                        <option value="<?= $pms['id'] ?>" <?= (int)$c['pemasok_val'] === (int)$pms['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pms['kode_pemasok']) ?> - <?= htmlspecialchars($pms['nama_pemasok']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex: 1.3; min-width: 150px;">
                <select name="<?= $c['mitra_name'] ?>" class="form-control" style="height: 38px; font-size: 12.5px; font-weight: 600;">
                    <option value="">-- Semua Mitra --</option>
                    <?php foreach ($mitraList as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= (int)$c['mitra_val'] === (int)$m['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['kode_mitra']) ?> - <?= htmlspecialchars($m['nama_mitra']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 6px;">
                <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 16px; font-weight: 700; font-size: 12.5px; display: inline-flex; align-items: center; gap: 5px;">
                    <i data-lucide="filter" style="width: 14px; height: 14px;"></i>
                    <span>Filter</span>
                </button>
                <a href="index.php?page=pengeluaran&tab=riwayat&subtab=<?= htmlspecialchars($c['subtab']) ?>" class="btn btn-outline" style="height: 38px; padding: 0 14px; display: inline-flex; align-items: center;">Reset</a>
            </div>
        </form>
    </div>
    <?php
}

require_once __DIR__ . '/layout/header.php';
?>

<div class="page-header no-print">
    <div>
        <?php if ($outboundTab === 'riwayat'): ?>
            <h1 class="page-title">Riwayat Pengeluaran Barang</h1>
            <p class="page-subtitle">Arsip dokumen surat jalan dan rincian kemasan barang keluar ke mitra bisnis</p>
        <?php else: ?>
            <h1 class="page-title">Pengeluaran Barang ke Mitra</h1>
            <p class="page-subtitle">Keluarkan stok fisik pemasok di gudang ke mitra bisnis dan sinkronisasi otomatis ke Accurate</p>
        <?php endif; ?>
    </div>
</div>

<div id="scanAlertBox" style="display: none; margin-bottom: 20px;"></div>

<!-- GRID SESI SCAN OUTBOUND (TAB SCAN) -->
<div id="sectionOutboundScan" style="<?= $outboundTab === 'scan' ? 'display: block;' : 'display: none;' ?>">
    <!-- BARIS 1: PARAMETER PENGELUARAN BERDAMPINGAN DENGAN KEMASAN TERVERIFIKASI -->
    <div style="display: grid; grid-template-columns: minmax(360px, 1.05fr) minmax(420px, 1.45fr); gap: 20px; align-items: start; margin-bottom: 24px;" class="no-print">
        <!-- Kolom Kiri: Parameter Pengeluaran -->
        <div class="card" style="display: flex; flex-direction: column; gap: 16px; box-shadow: var(--shadow-sm);">
            <div class="card-header" style="padding-bottom: 12px; border-bottom: 1px solid var(--border);">
                <div>
                    <h2 class="card-title" style="display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="settings-2" style="width: 18px; height: 18px; color: var(--primary);"></i>
                        <span>Parameter Pengeluaran</span>
                    </h2>
                    <div class="card-description">Pilih pemasok asal stok & mitra tujuan sebelum scan</div>
                </div>
            </div>

            <form id="formOutbound" onsubmit="return false;">
                <!-- 1. Pemasok Asal Stok -->
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" for="outbound_pemasok_id" style="font-weight: 800; display: flex; align-items: center; gap: 6px;">
                        <span>1. Pemasok Asal Stok</span>
                        <span style="color:red;">*</span>
                    </label>
                    <select name="pemasok_id" id="outbound_pemasok_id" class="form-control" required disabled style="font-weight: 700; height: 42px;">
                        <option value="" selected>Otomatis dari barcode yang di-scan</option>
                        <?php foreach ($pemasokList as $p): ?>
                            <option value="<?= $p['id'] ?>" data-kode="<?= htmlspecialchars($p['kode_pemasok']) ?>" data-nama="<?= htmlspecialchars($p['nama_pemasok']) ?>">
                                [<?= htmlspecialchars($p['kode_pemasok']) ?>] <?= htmlspecialchars($p['nama_pemasok']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="margin-top: 5px;">
                        <span style="font-size: 11px; color: var(--text-muted);">
                            Otomatis mengikuti barcode yang di-scan.
                        </span>
                    </div>
                </div>

                <!-- 2. Mitra Tujuan Pengeluaran -->
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" for="outbound_mitra_id" style="font-weight: 800; display: flex; align-items: center; gap: 6px;">
                        <span>2. Mitra Bisnis Penerima</span>
                        <span style="color:red;">*</span>
                    </label>
                    <select name="mitra_id" id="outbound_mitra_id" class="form-control" required style="font-weight: 700; height: 42px;">
                        <option value="" selected disabled>-- Pilih mitra tujuan --</option>
                        <?php foreach ($mitraList as $m): ?>
                            <option value="<?= $m['id'] ?>" data-kode="<?= htmlspecialchars($m['kode_mitra']) ?>" data-nama="<?= htmlspecialchars($m['nama_mitra']) ?>">
                                [<?= htmlspecialchars($m['kode_mitra']) ?>] <?= htmlspecialchars($m['nama_mitra']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">
                        Pilih mitra penerima barang secara manual.
                    </div>
                </div>

                <!-- 3. No. Surat Jalan Pengeluaran / DO -->
                <div class="form-group" style="margin-bottom: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <label class="form-label" for="no_referensi" style="margin: 0; font-weight: 700;">No. Surat Jalan Pengeluaran / DO <span style="color:red;">*</span></label>
                        <span class="badge badge-purple" style="font-size: 10px; padding: 2px 6px; font-weight: 700;">Otomatis</span>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <input type="text" name="no_referensi" id="no_referensi" class="form-control" value="<?= htmlspecialchars($defaultOutboundSj) ?>" required style="font-weight: 700; font-family: 'JetBrains Mono', monospace; color: var(--primary); height: 40px;" title="Nomor Surat Jalan otomatis berurutan">
                        <button type="button" class="btn btn-outline" id="btnRefreshSj" title="Generate ulang nomor surat jalan berikutnya" style="padding: 0 10px;">
                            <i data-lucide="refresh-cw" style="width: 14px; height: 14px;"></i>
                        </button>
                    </div>
                </div>

                <hr style="border: none; border-top: 1px solid var(--border); margin: 16px 0;">

                <!-- 6. Input Scan Barcode -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="inputBarcodeScan" style="font-weight: 800; color: var(--primary); display: flex; align-items: center; gap: 6px;">
                        <span>Input Scan Barcode Kemasan</span>
                    </label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="inputBarcodeScan" class="form-control form-control-lg" placeholder="Scan barcode kemasan..." autofocus style="font-size: 15px; font-family: 'JetBrains Mono', monospace; font-weight: 700;">
                        <button type="button" class="btn btn-primary" id="btnProsesScan" style="padding: 0 18px; font-weight: 700;">Scan</button>
                    </div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                        Arahkan scanner laser atau ketik ID label lalu tekan tombol Enter.
                    </div>
                </div>
            </form>
        </div>

        <!-- Kolom Kanan: KEMASAN TERVERIFIKASI (SIAP DIKELUARKAN) -->
        <div class="card" style="box-shadow: var(--shadow-sm); display: flex; flex-direction: column; height: 100%;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding-bottom: 14px; border-bottom: 1px solid var(--border);">
                <div>
                    <h2 class="card-title" style="display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="package-check" style="width: 20px; height: 20px; color: var(--success);"></i>
                        <span>Kemasan Terverifikasi (<span id="cartCount" style="color: var(--primary);">0</span> pcs)</span>
                    </h2>
                    <div class="card-description">Daftar kemasan fisik siap dikeluarkan dari gudang ke mitra penerima</div>
                </div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <span style="font-size: 13.5px; color: var(--text-muted); font-weight: 600;">Total Netto:</span>
                    <span id="cartTotalKg" style="font-size: 22px; font-weight: 900; color: #10b981; font-family: 'JetBrains Mono', monospace;">0.00 kg</span>
                </div>
            </div>

            <!-- Tabel Keranjang Scan Kemasan -->
            <div class="table-responsive" style="max-height: 430px; overflow-y: auto; flex: 1;">
                <table class="table-custom" id="tableOutboundCart">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">No</th>
                            <th>ID Barcode Kemasan</th>
                            <th>Nama Barang / Produk</th>
                            <th>Kategori</th>
                            <th style="text-align: right; width: 120px;">Netto (Kg)</th>
                            <th>SJ Asal Inbound</th>
                            <th style="text-align: center; width: 70px; position: sticky; right: 0; background: var(--bg-muted, #f8fafc); z-index: 25; box-shadow: -2px 0 4px rgba(0,0,0,0.04);">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="cartTableBody">
                        <tr id="emptyCartRow">
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 50px 20px;">
                                <div style="font-weight: 700; font-size: 14px; color: var(--text-main);">Belum ada kemasan yang discan</div>
                                <div style="font-size: 12px; margin-top: 4px;">Pilih pemasok & mitra di sebelah kiri, lalu arahkan scanner ke barcode kemasan fisik.</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Tombol Aksi Keranjang -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding: 14px 20px; border-top: 1px solid var(--border); background: var(--bg-card);">
                <button type="button" class="btn btn-outline" id="btnClearCart" style="color: var(--danger);">
                    <i data-lucide="trash-2" style="width: 15px; height: 15px;"></i>
                    <span>Kosongkan Sesi</span>
                </button>
                <button type="button" class="btn btn-success btn-lg" id="btnSubmitOutbound" style="font-weight: 800; font-size: 14px; padding: 10px 24px;">
                    <i data-lucide="send" style="width: 18px; height: 18px;"></i>
                    <span>Selesaikan Pengeluaran</span>
                </button>
            </div>
        </div>
    </div>


</div>

<!-- ========================================================================= -->
<!-- RIWAYAT PENGELUARAN BARANG (TAB RIWAYAT - MERGED SURAT JALAN & BARANG)     -->
<!-- ========================================================================= -->
<div id="sectionOutboundRiwayat" style="<?= $outboundTab === 'riwayat' ? 'display: block;' : 'display: none;' ?>">
<div class="card no-print" style="margin-top: 10px; border: 1.5px solid var(--border);">
    <div style="padding: 18px 22px; font-weight: 700; display: flex; align-items: center; justify-content: space-between; user-select: none; background: var(--bg-card); border-bottom: 1px solid var(--border);">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="background: rgba(79, 70, 229, 0.1); width: 38px; height: 38px; border-radius: 9px; display: flex; align-items: center; justify-content: center;">
                <i data-lucide="package-open" style="width: 22px; height: 22px; color: var(--primary);"></i>
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 16px; font-weight: 800; color: var(--text-main);">Daftar Seluruh Pengeluaran Barang ke Mitra</span>
                    <span class="badge badge-purple" id="outboundCountBadge"><?= number_format($outboundSubTab === 'sj' ? $totalHistory : $totalCount, 0, ',', '.') ?> Data</span>
                </div>
                <div style="font-size: 12px; color: var(--text-muted); font-weight: 500; margin-top: 2px;">
                    Daftar lengkap surat jalan keluar, rincian kemasan, mitra penerima, dan status fisik
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <?php $eksporNama = 'Riwayat_Pengeluaran'; include __DIR__ . '/layout/ekspor.php'; ?>
        </div>
    </div>

    <!-- Filter Bar Dinamis (mengikuti tampilan aktif) -->
    <div id="filterBarSj" style="<?= $outboundSubTab === 'sj' ? '' : 'display: none;' ?>">
    <?php renderOutboundFilterBar([
        'form_id' => 'formFilterHistory', 'subtab' => 'sj',
        'search_name' => 'h_search', 'search_val' => $hSearch, 'placeholder' => 'Cari No. Surat Jalan, Penerima, Catatan...',
        'start_name' => 'h_start_date', 'start_val' => $hStartDate,
        'end_name' => 'h_end_date', 'end_val' => $hEndDate,
        'pemasok_name' => 'h_pemasok', 'pemasok_val' => $hPemasok,
        'mitra_name' => 'h_mitra', 'mitra_val' => $hMitra,
    ], $pemasokList, $mitraList); ?>
    </div>
    <div id="filterBarBarang" style="<?= $outboundSubTab === 'barang' ? '' : 'display: none;' ?>">
    <?php renderOutboundFilterBar([
        'form_id' => 'formFilterOutbound', 'subtab' => 'barang',
        'search_name' => 'search', 'search_val' => $search, 'placeholder' => 'Cari barcode, produk, atau no. surat jalan...',
        'pemasok_name' => 'filter_pemasok', 'pemasok_val' => $filterPemasok,
        'mitra_name' => 'filter_mitra', 'mitra_val' => $filterMitra,
    ], $pemasokList, $mitraList); ?>
    </div>

    <!-- Switch Mode Tampilan: Per Surat Jalan vs Rincian Barang Keluar -->
    <div style="display: flex; gap: 8px; padding: 12px 16px; background: var(--bg-card); border-bottom: 1.5px solid var(--border); align-items: center; justify-content: space-between; flex-wrap: wrap;">
        <div style="display: flex; gap: 8px; align-items: center;">
            <span style="font-size: 13px; font-weight: 700; color: var(--text-muted); margin-right: 4px;">Tampilan:</span>
            <button type="button" class="btn <?= $outboundSubTab === 'sj' ? 'btn-primary' : 'btn-outline' ?> btn-sm" id="btnViewModeSj" onclick="switchOutboundViewMode('sj')" style="font-weight: 700; border-radius: 8px;">
                <i data-lucide="package" style="width: 14px; height: 14px;"></i>
                <span>Per Surat Jalan</span>
            </button>
            <button type="button" class="btn <?= $outboundSubTab === 'barang' ? 'btn-primary' : 'btn-outline' ?> btn-sm" id="btnViewModeBarang" onclick="switchOutboundViewMode('barang')" style="font-weight: 700; border-radius: 8px;">
                <i data-lucide="list" style="width: 14px; height: 14px;"></i>
                <span>Rincian Barang Keluar</span>
            </button>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAMPILAN 1: PER SURAT JALAN PENGELUARAN                                   -->
    <!-- ========================================================================= -->
    <div id="containerViewSj" style="<?= $outboundSubTab === 'sj' ? 'display: block;' : 'display: none;' ?>">

    <!-- Tabel Riwayat Transaksi Pengeluaran (Fokus Fisik Gudang) -->
    <div class="table-responsive">
        <table class="table-custom" id="tableHistoryOutbound">
            <thead>
                <tr>
                    <th style="width: 35px; text-align: center;">No</th>
                    <th style="width: 185px;">No. Surat Jalan / DO</th>
                    <th style="width: 130px;">Waktu Keluar</th>
                    <th style="width: 175px;">Pemasok Asal</th>
                    <th style="width: 175px;">Mitra Penerima</th>
                    <th style="text-align: center; width: 95px;">Kemasan</th>
                    <th style="text-align: right; width: 115px;">Total Netto</th>
                    <th style="width: 130px;">Petugas</th>
                    <th style="text-align: center; width: 160px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($historyOutbound)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 36px;">
                            Belum ada riwayat pengeluaran barang tercatat.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $hNo = $hOffset + 1;
                    foreach ($historyOutbound as $ht): 
                        $sjKeluar = $ht['no_surat_jalan_keluar'];
                    ?>
                    <tr<?= $ht['jenis_transaksi'] === 'PENGELUARAN_BATAL' ? ' style="opacity: 0.65;"' : '' ?>>
                        <td style="text-align: center; color: var(--text-muted); font-size: 11.5px;"><?= $hNo++ ?></td>
                        <td>
                            <div style="font-family: 'JetBrains Mono', monospace; font-weight: 800; font-size: 12.5px; color: var(--primary);">
                                <?= htmlspecialchars($sjKeluar) ?>
                            </div>
                            <div style="font-size: 10.5px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace;">
                                <?= htmlspecialchars($ht['no_transaksi']) ?>
                            </div>
                            <?php if ($ht['jenis_transaksi'] === 'PENGELUARAN_BATAL'): ?>
                                <span class="badge badge-danger" style="font-size: 10.5px; margin-top: 3px;">Dibatalkan</span>
                                <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 2px;"><?= htmlspecialchars($ht['alasan_batal'] ?? '') ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 11.5px; color: var(--text-muted);">
                            <?= date('d/m/Y H:i', strtotime($ht['created_at'])) ?>
                        </td>
                        <td>
                            <span class="badge badge-info" style="font-size: 11.5px; padding: 3px 8px; font-weight: 700;">
                                [<?= htmlspecialchars($ht['kode_pemasok'] ?? 'PMS') ?>] <?= htmlspecialchars($ht['nama_pemasok'] ?? 'Pemasok') ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-purple" style="font-size: 11.5px; padding: 3px 8px; font-weight: 700;">
                                [<?= htmlspecialchars($ht['kode_mitra'] ?? 'MTR') ?>] <?= htmlspecialchars($ht['nama_mitra'] ?? 'Mitra') ?>
                            </span>
                            <?php if (!empty($ht['nama_penerima'])): ?>
                                <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 2px;">
                                    PIC: <?= htmlspecialchars($ht['nama_penerima']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge badge-secondary" style="font-weight: 700;">
                                <?= number_format($ht['total_label']) ?> Pcs
                            </span>
                        </td>
                        <td style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; font-size: 13.5px; color: #10b981;">
                            <?= Helper::formatKg($ht['total_kg']) ?>
                        </td>
                        <td style="font-size: 11.5px; color: var(--text-muted);">
                            <?= htmlspecialchars($ht['operator_name'] ?? 'Petugas Gudang') ?>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                                <a href="index.php?page=surat_jalan_print&id=<?= $ht['id'] ?>" target="_blank" class="btn btn-outline btn-sm" style="padding: 5px 9px; font-size: 12px; color: #4f46e5; border-color: #c7d2fe; display: inline-flex; align-items: center; gap: 4px;" title="Cetak Dokumen Surat Jalan Resmi A4">
                                    <i data-lucide="printer" style="width: 14px; height: 14px;"></i>
                                    <span>Cetak</span>
                                </a>
                                <button type="button" class="btn btn-outline btn-sm" onclick="viewOutboundDetail(<?= $ht['id'] ?>)" style="padding: 5px 9px; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;" title="Lihat Rincian Kemasan">
                                    <i data-lucide="eye" style="width: 14px; height: 14px;"></i>
                                    <span>Rincian</span>
                                </button>
                                <?php if ($ht['jenis_transaksi'] === 'PENGELUARAN'): ?>
                                <button type="button" class="btn btn-outline btn-sm" onclick="batalDokumen('batal_pengeluaran', { id: <?= (int)$ht['id'] ?> }, 'surat jalan keluar <?= htmlspecialchars($ht['no_transaksi'], ENT_QUOTES) ?>')" style="padding: 5px 9px; font-size: 12px; color: #dc2626; border-color: rgba(220,38,38,0.35);" title="Batalkan dokumen"><i data-lucide="ban" style="width: 14px; height: 14px;"></i><span>Batalkan</span></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Riwayat (SELALU TAMPIL) -->
    <?php
    $baseHistoryUrl = "index.php?page=pengeluaran&tab=riwayat&subtab=sj&h_search=" . urlencode($hSearch) . "&h_pemasok=" . urlencode((string)$hPemasok) . "&h_mitra=" . urlencode((string)$hMitra) . "&h_start_date=" . urlencode($hStartDate) . "&h_end_date=" . urlencode($hEndDate) . "&h_date_range=" . urlencode($hDatePreset);
    ?>
    <div style="padding: 14px 20px; background: var(--bg-card); display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); flex-wrap: wrap; gap: 10px;">
        <div style="font-size: 12.5px; color: var(--text-muted);">
            Menampilkan riwayat <strong><?= $hPage ?></strong> dari <strong><?= $totalHistoryPages ?></strong> (Total <?= number_format($totalHistory) ?> Surat Jalan)
        </div>
        <div style="display: flex; gap: 5px; align-items: center;">
            <?php if ($hPage > 1): ?>
                <a href="<?= $baseHistoryUrl ?>&hp=1" class="btn btn-outline btn-sm" style="padding: 4px 10px;">« Pertama</a>
                <a href="<?= $baseHistoryUrl ?>&hp=<?= $hPage - 1 ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">‹ Sebelumnya</a>
            <?php else: ?>
                <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">« Pertama</button>
                <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">‹ Sebelumnya</button>
            <?php endif; ?>

            <?php 
            $startHP = max(1, $hPage - 2);
            $endHP = min($totalHistoryPages, $hPage + 2);
            for ($hp = $startHP; $hp <= $endHP; $hp++): 
            ?>
                <a href="<?= $baseHistoryUrl ?>&hp=<?= $hp ?>" class="btn <?= $hp === $hPage ? 'btn-primary' : 'btn-outline' ?> btn-sm" style="padding: 4px 10px; font-weight: <?= $hp === $hPage ? '800' : '600' ?>;">
                    <?= $hp ?>
                </a>
            <?php endfor; ?>

            <?php if ($hPage < $totalHistoryPages): ?>
                <a href="<?= $baseHistoryUrl ?>&hp=<?= $hPage + 1 ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">Selanjutnya ›</a>
                <a href="<?= $baseHistoryUrl ?>&hp=<?= $totalHistoryPages ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">Terakhir »</a>
            <?php else: ?>
                <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">Selanjutnya ›</button>
                <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">Terakhir »</button>
            <?php endif; ?>
        </div>
    </div>
    </div>
    <!-- END TAMPILAN 1: PER SURAT JALAN -->

    <!-- ========================================================================= -->
    <!-- TAMPILAN 2: RINCIAN BARANG KELUAR / KEMASAN                               -->
    <!-- ========================================================================= -->
    <div id="containerViewBarang" style="<?= $outboundSubTab === 'barang' ? 'display: block;' : 'display: none;' ?>">

    <!-- Tabel Data Seluruh Barang Keluar -->
    <div class="table-responsive">
        <table class="table-custom" id="tableAllGoodsOutbound">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">No</th>
                    <th>ID Barcode Kemasan</th>
                    <th>Pemasok Asal</th>
                    <th>Nama Barang / Produk</th>
                    <th style="text-align: right;">Netto (Kg)</th>
                    <th>Mitra Penerima</th>
                    <th>No. Surat Jalan Keluar</th>
                    <th>Waktu Keluar</th>
                    <th style="text-align: center; width: 120px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allGoods)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 36px;">
                            Tidak ada data barang keluar yang cocok dengan kriteria filter yang dipilih.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = $offset + 1;
                    foreach ($allGoods as $item): 
                        $berat = (float)$item['berat_netto'];
                        $sjKeluar = !empty($item['no_bast']) ? $item['no_bast'] : (!empty($item['no_trx_mutasi']) ? $item['no_trx_mutasi'] : (!empty($item['no_surat_jalan']) ? $item['no_surat_jalan'] : '-'));
                        $waktuKeluar = !empty($item['waktu_mutasi']) ? Helper::formatDateTime($item['waktu_mutasi']) : Helper::formatDateTime($item['updated_at']);
                        $namaMitraKeluar = !empty($item['nama_mitra_tujuan']) ? $item['nama_mitra_tujuan'] : (!empty($item['nama_mitra']) ? $item['nama_mitra'] : '-');
                        $kodeMitraKeluar = !empty($item['kode_mitra_tujuan']) ? $item['kode_mitra_tujuan'] : (!empty($item['kode_mitra']) ? $item['kode_mitra'] : '');
                    ?>
                    <tr>
                        <td style="text-align: center; color: var(--text-muted); font-size: 12px;"><?= $no++ ?></td>
                        <td>
                            <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--primary);">
                                <?= htmlspecialchars($item['id_label']) ?>
                            </strong>
                        </td>
                        <td>
                            <span class="badge badge-info" style="font-size: 11px; font-weight: 700;">
                                <?= htmlspecialchars($item['nama_pemasok'] ?? 'Pemasok') ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($item['nama_item']) ?></strong>
                            <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($item['nama_jenis']) ?></div>
                        </td>
                        <td style="text-align: right; font-weight: 800; font-size: 13.5px; color: #10b981;">
                            <?= Helper::formatKg($berat) ?>
                        </td>
                        <td>
                            <?php if (!empty($namaMitraKeluar) && $namaMitraKeluar !== '-'): ?>
                                <span class="badge badge-purple" style="font-size: 11px; font-weight: 700;">
                                    <?php if (!empty($kodeMitraKeluar)): ?>[<?= htmlspecialchars($kodeMitraKeluar) ?>] <?php endif; ?><?= htmlspecialchars($namaMitraKeluar) ?>
                                </span>
                            <?php else: ?>
                                <span style="font-size: 11px; color: var(--text-muted);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code style="font-size: 11.5px; font-weight: 700; color: #2563eb;"><?= htmlspecialchars($sjKeluar) ?></code>
                        </td>
                        <td style="font-size: 11.5px; color: var(--text-muted);">
                            <?= $waktuKeluar ?>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge badge-secondary" style="background: #475569; color: #fff; font-size: 11px;">Sudah Keluar</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls (SELALU TAMPIL) -->
    <?php
    $baseOutUrl = "index.php?page=pengeluaran&tab=riwayat&subtab=barang&filter_pemasok=" . urlencode((string)$filterPemasok) . "&filter_mitra=" . urlencode((string)$filterMitra) . "&search=" . urlencode($search);
    ?>
    <div style="padding: 14px 20px; background: var(--bg-card); display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); flex-wrap: wrap; gap: 10px;">
        <div style="font-size: 12.5px; color: var(--text-muted);">
            Menampilkan halaman <strong><?= $page ?></strong> dari <strong><?= $totalPages ?></strong> (Total <?= number_format($totalCount, 0, ',', '.') ?> data)
        </div>
        <div style="display: flex; gap: 5px; align-items: center;">
            <?php if ($page > 1): ?>
                <a href="<?= $baseOutUrl ?>&p=1" class="btn btn-outline btn-sm" style="padding: 4px 10px;">« Pertama</a>
                <a href="<?= $baseOutUrl ?>&p=<?= $page - 1 ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">‹ Sebelumnya</a>
            <?php else: ?>
                <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">« Pertama</button>
                <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">‹ Sebelumnya</button>
            <?php endif; ?>

            <?php 
            $startP = max(1, $page - 2);
            $endP = min($totalPages, $page + 2);
            for ($i = $startP; $i <= $endP; $i++): 
            ?>
                <a href="<?= $baseOutUrl ?>&p=<?= $i ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-outline' ?> btn-sm" style="padding: 4px 10px; font-weight: <?= $i === $page ? '800' : '600' ?>;">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= $baseOutUrl ?>&p=<?= $page + 1 ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">Selanjutnya ›</a>
                <a href="<?= $baseOutUrl ?>&p=<?= $totalPages ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">Terakhir »</a>
            <?php else: ?>
                <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">Selanjutnya ›</button>
                <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">Terakhir »</button>
            <?php endif; ?>
        </div>
    </div>
    </div>
    <!-- END TAMPILAN 2: RINCIAN BARANG KELUAR -->

</div>
</div>
<!-- END SECTION OUTBOUND RIWAYAT -->

<!-- Modal Detail Rincian Barcode Surat Jalan Pengeluaran -->
<div id="modalOutboundDetail" class="sheet-preview-backdrop no-print" style="display: none; align-items: center; justify-content: center; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 9999;">
    <div class="sheet-preview-dialog" style="max-width: 860px; width: 95%; background: var(--bg-surface, #ffffff); border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow: hidden; display: flex; flex-direction: column; max-height: 90vh;">
        <div class="sheet-preview-header" style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 6px; border-radius: 8px;">
                    <i data-lucide="package" style="width: 20px; height: 20px;"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 16px; font-weight: 800;" id="modalSjTitle">Rincian Surat Jalan</h3>
                    <div style="font-size: 12px; color: var(--text-muted);" id="modalSjSubtitle">-</div>
                </div>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <a href="#" id="modalBtnPrintSj" target="_blank" class="btn btn-primary btn-sm" style="font-weight: 700;">
                    <i data-lucide="printer" style="width: 14px; height: 14px;"></i>
                    <span>Cetak Surat Jalan</span>
                </a>
                <button type="button" class="btn btn-outline btn-sm" onclick="closeOutboundDetailModal()" style="font-weight: 700;">Tutup</button>
            </div>
        </div>
        <div style="padding: 16px 20px; overflow-y: auto;">
            <!-- Info Asal & Tujuan -->
            <div id="modalSjPartyInfo" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; background: var(--bg-muted); padding: 12px 16px; border-radius: 8px; border: 1px solid var(--border);"></div>

            <!-- Tabel Barcode Transaksi -->
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center;">No</th>
                            <th>ID Barcode Kemasan</th>
                            <th>Nama Barang / Produk</th>
                            <th>Kategori</th>
                            <th style="text-align: right;">Netto</th>
                            <th>SJ Inbound</th>
                        </tr>
                    </thead>
                    <tbody id="modalSjItemsBody">
                        <tr><td colspan="6" style="text-align: center; padding: 25px;">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    const scannedLabels = new Map();
    const inputScan = document.getElementById('inputBarcodeScan');
    const selectPemasok = document.getElementById('outbound_pemasok_id');
    const selectMitra = document.getElementById('outbound_mitra_id');
    const alertBox = document.getElementById('scanAlertBox');

    function showAlert(msg, isSuccess = true) {
        alertBox.style.display = 'block';
        if (isSuccess === 'warning') {
            alertBox.className = 'alert';
            alertBox.style.background = '#fffbeb';
            alertBox.style.border = '1.5px solid #fcd34d';
            alertBox.style.color = '#92400e';
            alertBox.innerHTML = `⚠️ ${msg}`;
            if (window.scannerAudio) window.scannerAudio.playSuccessBeep();
        } else if (isSuccess === true) {
            alertBox.className = 'alert alert-success';
            alertBox.style.background = '';
            alertBox.style.border = '';
            alertBox.style.color = '';
            alertBox.innerHTML = `✓ ${msg}`;
            if (window.scannerAudio) window.scannerAudio.playSuccessBeep();
        } else {
            alertBox.className = 'alert alert-danger';
            alertBox.style.background = '';
            alertBox.style.border = '';
            alertBox.style.color = '';
            alertBox.innerHTML = `⚠️ ${msg}`;
            if (window.scannerAudio) window.scannerAudio.playErrorBuzz();
        }
    }

    // Mengambil dan menampilkan informasi stok fisik pemasok di gudang
    async function fetchPemasokStock() {
        if (!document.getElementById('boxPemasokStock')) return;
        const pemasokId = selectPemasok.value;
        const mitraId = selectMitra.value;
        if (!pemasokId) return;

        const pmsOpt = selectPemasok.options[selectPemasok.selectedIndex];
        const pmsNama = pmsOpt ? pmsOpt.getAttribute('data-nama') : '';
        document.getElementById('pmsHeaderNama').innerText = pmsNama || 'Pemasok Terpilih';

        const badgeEl = document.getElementById('stockPmsBadge');
        const pcsEl = document.getElementById('pmsTotalPcs');
        const kgEl = document.getElementById('pmsTotalKg');
        const itemsEl = document.getElementById('pmsItemsContainer');
        const chipsEl = document.getElementById('pmsBarcodesChips');

        badgeEl.innerText = 'Memuat stok...';
        badgeEl.style.background = '#64748b';

        try {
            const resp = await fetch(`index.php?action=get_pemasok_warehouse_stock&pemasok_id=${pemasokId}&mitra_id=${mitraId}`);
            const data = await resp.json();

            if (!data.success || !data.stock) {
                badgeEl.innerText = 'Gagal memuat stok';
                badgeEl.style.background = '#dc2626';
                itemsEl.innerHTML = '<div style="color: #dc2626; padding: 4px;">Gagal memuat persediaan gudang.</div>';
                return;
            }

            const stock = data.stock;
            const summary = stock.summary || {};
            const totalPcs = parseInt(summary.total_kemasan || 0);
            const totalKg = parseFloat(summary.total_kg || 0);

            pcsEl.innerText = totalPcs + ' Pcs';
            kgEl.innerText = totalKg.toFixed(2) + ' Kg';

            if (totalPcs > 0) {
                badgeEl.innerText = `${totalPcs} Pcs Tersedia di Gudang`;
                badgeEl.style.background = '#059669';
            } else {
                badgeEl.innerText = 'Stok Fisik Kosong di Gudang';
                badgeEl.style.background = '#f59e0b';
            }

            // Render daftar breakdown item
            const items = stock.items || [];
            if (items.length === 0) {
                itemsEl.innerHTML = '<div style="color: var(--text-muted); text-align: center; padding: 8px;">Tidak ada stok aktif di gudang untuk pemasok ini.</div>';
            } else {
                let html = '<table style="width: 100%; border-collapse: collapse; font-size: 11.5px;">';
                items.forEach(it => {
                    const kg = parseFloat(it.total_kg || 0).toFixed(2);
                    html += `
                        <tr style="border-bottom: 1px dashed #d1fae5;">
                            <td style="padding: 4px 6px; font-weight: 700; color: #065f46;">${it.nama_item}</td>
                            <td style="padding: 4px 6px; color: #64748b; font-size: 11px;">(${it.nama_jenis})</td>
                            <td style="padding: 4px 6px; text-align: right; font-weight: 700; color: #1e40af;">${it.total_kemasan} Pcs</td>
                            <td style="padding: 4px 6px; text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; color: #059669;">${kg} Kg</td>
                        </tr>
                    `;
                });
                html += '</table>';
                itemsEl.innerHTML = html;
            }

            // Render chips barcode aktif yang bisa diklik langsung untuk simulasi scan
            const labels = stock.labels || [];
            if (labels.length === 0) {
                chipsEl.innerHTML = '<span style="font-size: 11px; color: var(--text-muted);">Tidak ada barcode aktif</span>';
            } else {
                let chipsHtml = '';
                labels.slice(0, 10).forEach(lbl => {
                    chipsHtml += `
                        <button type="button" class="btn btn-outline btn-sm" onclick="triggerSimulatedScan('${lbl.id_label}')" style="font-family: 'JetBrains Mono', monospace; font-size: 11px; padding: 3px 7px; background: #fff; border-color: #86efac; color: #065f46;" title="Klik untuk scan ${lbl.id_label} (${lbl.nama_item})">
                            + ${lbl.id_label} (${parseFloat(lbl.berat_netto).toFixed(1)}kg)
                        </button>
                    `;
                });
                chipsEl.innerHTML = chipsHtml;
            }

        } catch (e) {
            console.error(e);
            badgeEl.innerText = 'Error Koneksi';
            badgeEl.style.background = '#dc2626';
        }
    }

    // Scan & verifikasi barcode kemasan
    // Ubah pilihan <select> sesuai barcode yang discan dan picu event change (stok & no. surat jalan ikut diperbarui)
    function syncSelect(sel, value) {
        const opt = Array.from(sel.options).find(o => String(o.value) === String(value));
        if (!opt) return false;
        sel.value = opt.value;
        sel.dispatchEvent(new Event('change'));
        return true;
    }

    async function handleScan(barcode) {
        barcode = barcode.trim();
        if (!barcode) return;
        inputScan.value = '';

        if (scannedLabels.has(barcode)) {
            showAlert(`Barcode '${barcode}' SUDAH PERNAH DISCAN dalam sesi ini!`, false);
            return;
        }

        try {
            const url = `index.php?action=verify_label&barcode=${encodeURIComponent(barcode)}`;
            const resp = await fetch(url);
            const data = await resp.json();

            if (!data.success) {
                showAlert(`Gagal: ${data.message}`, false);
                return;
            }

            const label = data.label;

            // Validasi status
            if (label.status !== 'AKTIF') {
                showAlert(`Barcode '${barcode}' berstatus '${label.status}', tidak dapat dikeluarkan dari gudang!`, false);
                return;
            }

            // Sinkronkan Pemasok & Mitra dengan barcode yang discan
            const syncNotes = [];
            const firstInCart = scannedLabels.size > 0 ? scannedLabels.values().next().value : null;

            if (label.pemasok_id) {
                if (firstInCart && firstInCart.pemasok_id && firstInCart.pemasok_id != label.pemasok_id) {
                    const namaPms = label.nama_pemasok ? `[${label.kode_pemasok}] ${label.nama_pemasok}` : 'pemasok lain';
                    showAlert(`Barcode '${barcode}' berasal dari ${namaPms}. Satu surat jalan hanya untuk satu pemasok, selesaikan atau kosongkan sesi ini dulu.`, false);
                    return;
                }
                if (String(selectPemasok.value) !== String(label.pemasok_id) && syncSelect(selectPemasok, label.pemasok_id)) {
                    syncNotes.push('pemasok');
                }
            }

            if (label.mitra_id) {
                if (String(selectMitra.value) !== String(label.mitra_id) && syncSelect(selectMitra, label.mitra_id)) {
                    syncNotes.push('mitra');
                }
            }

            scannedLabels.set(barcode, label);
            renderCart();

            if (data.fifo_info && data.fifo_info.has_older_stock) {
                // Notifikasi reguler scan berhasil di alert box atas
                showAlert(`Kemasan berhasil diverifikasi: <strong>${label.nama_item}</strong> (${parseFloat(label.berat_netto).toFixed(2)} kg)${syncNotes.length ? ' &middot; ' + syncNotes.join(' & ') + ' disesuaikan otomatis' : ''}`, true);

                // Notifikasi Peringatan FIFO standar SweetAlert Toast di pojok kanan atas
                if (typeof Swal !== 'undefined') {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 5000,
                        timerProgressBar: true,
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer);
                            toast.addEventListener('mouseleave', Swal.resumeTimer);
                        }
                    });

                    const pmsId = label.pemasok_id || selectPemasok.value || '';
                    const itmId = label.item_id || '';
                    const stockUrl = `index.php?page=timbang_cetak&tab=stok_gudang&item_id=${encodeURIComponent(itmId)}${pmsId ? '&pemasok_id=' + encodeURIComponent(pmsId) : ''}`;

                    Toast.fire({
                        icon: 'warning',
                        html: `
                            <div style="display: flex; flex-direction: column; gap: 4px; text-align: left; padding: 2px 0 2px 14px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                                    <span style="font-size: 13.5px; font-weight: 700; color: #1e293b;">
                                        Ada ${data.fifo_info.older_count} box ${label.nama_item}
                                    </span>
                                    <a href="${stockUrl}" target="_blank" title="Buka Sisa Stok Gudang" style="display: inline-flex; align-items: center; justify-content: center; color: #94a3b8; text-decoration: none; padding: 2px; border-radius: 4px; transition: color 0.15s ease;" onmouseover="this.style.color='#1e293b'" onmouseout="this.style.color='#94a3b8'">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                    </a>
                                </div>
                                <div style="font-size: 12px; color: #64748b;">
                                    Lebih lama mengendap (${data.fifo_info.aging_text})
                                </div>
                            </div>
                        `
                    });
                }
            } else {
                showAlert(`Kemasan berhasil diverifikasi: ${label.nama_item} (${parseFloat(label.berat_netto).toFixed(2)} kg)${syncNotes.length ? ' · ' + syncNotes.join(' & ') + ' disesuaikan otomatis' : ''}`, true);
            }

        } catch (err) {
            showAlert('Kesalahan koneksi saat verifikasi barcode: ' + err.message, false);
        }
    }

    // Render tabel keranjang (fokus fisik gudang tanpa angka rupiah)
    function renderCart() {
        const tbody = document.getElementById('cartTableBody');

        if (scannedLabels.size === 0) {
            tbody.innerHTML = `
                <tr id="emptyCartRow">
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 40px;">
                        <div style="font-weight: 700; font-size: 14px; color: var(--text-main);">Belum ada kemasan yang discan</div>
                        <div style="font-size: 12px; margin-top: 4px;">Pilih pemasok & mitra di samping, lalu scan barcode kemasan fisik di atas.</div>
                    </td>
                </tr>
            `;
            document.getElementById('cartCount').innerText = '0';
            document.getElementById('cartTotalKg').innerText = '0.00 kg';
            return;
        }

        tbody.innerHTML = '';
        let totalKg = 0;
        let index = 1;

        scannedLabels.forEach((label, barcode) => {
            const netto = parseFloat(label.berat_netto || 0);
            totalKg += netto;
            const hrgMitra = parseFloat(label.harga_mitra || 0);

            const row = document.createElement('tr');
            row.innerHTML = `
                <td style="color: var(--text-muted); font-size: 12px; text-align: center;">${index++}</td>
                <td>
                    <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--primary);">
                        ${label.id_label}
                    </strong>
                </td>
                <td>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                        <strong>${label.nama_item}</strong>
                        <a href="index.php?page=timbang_cetak&tab=stok_gudang&item_id=${encodeURIComponent(label.item_id || '')}${label.pemasok_id ? '&pemasok_id=' + encodeURIComponent(label.pemasok_id) : ''}" target="_blank" title="Lihat Sisa Stok ${label.nama_item} di Gudang" style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 4px; background: var(--bg-muted); color: var(--text-muted); border: 1px solid var(--border); text-decoration: none; transition: all 0.2s;" onmouseover="this.style.color='var(--primary)'; this.style.borderColor='var(--primary)';" onmouseout="this.style.color='var(--text-muted)'; this.style.borderColor='var(--border)';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                        </a>
                    </div>
                    ${hrgMitra <= 0 
                        ? `<div style="font-size: 10.5px; color: #dc2626; font-weight: 700; margin-top: 3px;">⚠️ Tarif Jual: Rp 0 (Belum diatur di Master)</div>`
                        : `<div style="font-size: 10.5px; color: #059669; font-weight: 600; margin-top: 2px;">Tarif: Rp ${new Intl.NumberFormat('id-ID').format(hrgMitra)}/kg • Subtotal: Rp ${new Intl.NumberFormat('id-ID').format(netto * hrgMitra)}</div>`
                    }
                </td>
                <td>
                    <span class="badge badge-outline" style="font-size: 11px;">${label.nama_jenis || '-'}</span>
                </td>
                <td style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; font-size: 13px; color: #10b981;">
                    ${netto.toFixed(2)} kg
                </td>
                <td>
                    <code style="font-size: 11px;">${label.no_surat_jalan || label.batch_no || '-'}</code>
                </td>
                <td style="text-align: center; position: sticky; right: 0; background: var(--bg-card, #ffffff); z-index: 5; box-shadow: -2px 0 4px rgba(0,0,0,0.04);">
                    <button type="button" class="btn btn-outline btn-sm" style="padding: 2px 7px; color: var(--danger);" onclick="removeLabel('${barcode}')" title="Hapus dari sesi">
                        ✕
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });

        document.getElementById('cartCount').innerText = scannedLabels.size;
        document.getElementById('cartTotalKg').innerText = totalKg.toFixed(2) + ' kg';
    }

    function removeLabel(barcode) {
        if (scannedLabels.has(barcode)) {
            scannedLabels.delete(barcode);
            renderCart();
            showAlert(`Barcode '${barcode}' dihapus dari sesi pengeluaran.`, true);
        }
    }

    function triggerSimulatedScan(barcode) {
        handleScan(barcode);
    }

    document.getElementById('btnProsesScan').addEventListener('click', () => {
        handleScan(inputScan.value);
    });

    inputScan.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleScan(inputScan.value);
        }
    });

    document.getElementById('btnClearCart').addEventListener('click', async () => {
        if (scannedLabels.size > 0) {
            const res = await window.showAppConfirm({
                title: 'Kosongkan Sesi Scan?',
                html: 'Apakah Anda yakin ingin membatalkan dan mengosongkan semua barcode yang sudah diverifikasi?',
                icon: 'warning',
                confirmButtonText: 'Ya, Kosongkan',
                cancelButtonText: 'Batal'
            });
            if (res.isConfirmed) {
                scannedLabels.clear();
                renderCart();
                showAlert('Sesi scan dikosongkan.', true);
            }
        }
    });

    // Submit pengeluaran stok pemasok ke mitra
    document.getElementById('btnSubmitOutbound').addEventListener('click', async () => {
        if (scannedLabels.size === 0) {
            showAlert('Belum ada kemasan yang discan! Silakan scan minimal 1 barcode.', false);
            return;
        }

        const pemasokId = selectPemasok.value;
        const mitraId = selectMitra.value;
        const noRef = document.getElementById('no_referensi').value.trim();
        const namaPenerima = document.getElementById('nama_penerima') ? document.getElementById('nama_penerima').value.trim() : '';
        const ket = document.getElementById('keterangan') ? document.getElementById('keterangan').value.trim() : '';

        if (!pemasokId) {
            showAlert('Pemasok asal stok wajib dipilih!', false);
            selectPemasok.focus();
            return;
        }

        if (!mitraId) {
            showAlert('Mitra tujuan pengeluaran wajib dipilih!', false);
            selectMitra.focus();
            return;
        }

        if (!noRef) {
            showAlert('Nomor Surat Jalan / DO wajib diisi!', false);
            return;
        }

        const payload = {
            pemasok_id: parseInt(pemasokId),
            mitra_id: parseInt(mitraId),
            no_referensi: noRef,
            nama_penerima: namaPenerima,
            keterangan: ket,
            barcodes: Array.from(scannedLabels.keys())
        };

        // Validasi jika ada item yang belum memiliki tarif harga jual mitra (Rp 0)
        let zeroItems = [];
        scannedLabels.forEach((lbl) => {
            if (parseFloat(lbl.harga_mitra || 0) <= 0) {
                zeroItems.push(lbl.nama_item);
            }
        });

        if (zeroItems.length > 0) {
            const uniqueZero = [...new Set(zeroItems)].join(', ');
            const confirmZero = await window.showAppConfirm({
                title: 'Tarif Jual Mitra Rp 0 Terdeteksi!',
                html: `
                    <div style="text-align: left; font-size: 13.5px; line-height: 1.5;">
                        <p style="margin-bottom: 8px;">Produk <strong>${uniqueZero}</strong> belum memiliki tarif harga jual di <strong>Master Harga Mitra</strong> (harga saat ini: Rp 0).</p>
                        <p style="color: #b45309; font-weight: 700; margin-bottom: 8px;">⚠️ Dampak: Transaksi akan diterbitkan dengan nilai tagihan Rp 0 dan tidak akan muncul sebagai piutang aktif di AR Aging.</p>
                        <p>Apakah Anda ingin tetap memproses pengeluaran Rp 0, atau membatalkan untuk mengisi tarif di Master Data?</p>
                    </div>
                `,
                icon: 'warning',
                confirmButtonText: 'Tetap Simpan (Rp 0)',
                cancelButtonText: 'Batal, Atur Harga Dulu'
            });

            if (!confirmZero.isConfirmed) {
                return;
            }
        }

        const submitBtn = document.getElementById('btnSubmitOutbound');
        const oldHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Memproses Pengeluaran...';

        try {
            const resp = await fetch('index.php?action=process_outbound', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await resp.json();

            if (data.success) {
                if (window.scannerAudio) window.scannerAudio.playSuccessBeep();
                
                const confirmedResult = await window.showAppConfirm({
                    title: 'Pengeluaran Barang Berhasil!',
                    html: `
                        <div style="text-align: left; font-size: 13.5px;">
                            <p style="margin-bottom: 6px;"><strong>No. Surat Jalan:</strong> <code>${data.no_surat_jalan}</code></p>
                            <p style="margin-bottom: 6px;"><strong>Mitra Tujuan:</strong> ${data.mitra}</p>
                            <p style="margin-bottom: 6px;"><strong>Penerima:</strong> ${data.tujuan}</p>
                            <p style="margin-bottom: 12px;"><strong>Total Muatan:</strong> ${data.total_label} Kemasan (${parseFloat(data.total_kg).toFixed(2)} kg)</p>
                            <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; padding: 10px; border-radius: 6px;">
                                Dokumen Surat Jalan siap dicetak. Apakah Anda ingin membuka dokumen sekarang?
                            </div>
                        </div>
                    `,
                    icon: 'success',
                    confirmButtonText: '🖨️ Cetak Surat Jalan',
                    cancelButtonText: 'Tutup & Selesai'
                });

                if (confirmedResult.isConfirmed) {
                    window.open('index.php?page=surat_jalan_print&id=' + data.transaksi_id, '_blank');
                }

                scannedLabels.clear();
                window.location.reload();
            } else {
                showAlert('Gagal Menyelesaikan Pengeluaran: ' + data.message, false);
                submitBtn.disabled = false;
                submitBtn.innerHTML = oldHtml;
            }
        } catch (err) {
            showAlert('Kesalahan saat memproses pengeluaran: ' + err.message, false);
            submitBtn.disabled = false;
            submitBtn.innerHTML = oldHtml;
        }
    });

    // Otomatis update info stok & nomor Surat Jalan saat Pemasok diganti
    selectPemasok.addEventListener('change', async function() {
        const pmsId = this.value;
        const opt = this.options[this.selectedIndex];
        const kode = opt.getAttribute('data-kode') || '';
        
        fetchPemasokStock();
        if (document.getElementById('panelFifoGuide') && document.getElementById('panelFifoGuide').style.display !== 'none') {
            fetchFifoGuide();
        }

        // Update No Surat Jalan
        try {
            const resp = await fetch(`index.php?action=get_next_outbound_sj&pemasok_id=${pmsId}&kode=${encodeURIComponent(kode)}`);
            const data = await resp.json();
            if (data.success && data.no_surat_jalan) {
                document.getElementById('no_referensi').value = data.no_surat_jalan;
            }
        } catch (e) {
            console.error(e);
        }
    });

    // Tombol Generate Ulang Surat Jalan
    document.getElementById('btnRefreshSj')?.addEventListener('click', async function() {
        const pmsId = selectPemasok.value;
        const opt = selectPemasok.options[selectPemasok.selectedIndex];
        const kode = opt.getAttribute('data-kode') || '';
        try {
            const resp = await fetch(`index.php?action=get_next_outbound_sj&pemasok_id=${pmsId}&kode=${encodeURIComponent(kode)}`);
            const data = await resp.json();
            if (data.success && data.no_surat_jalan) {
                document.getElementById('no_referensi').value = data.no_surat_jalan;
                showAlert('Nomor Surat Jalan diperbarui: ' + data.no_surat_jalan, true);
            }
        } catch (e) {
            console.error(e);
        }
    });

    // Panduan Antrean FIFO Stok Tertua
    async function fetchFifoGuide() {
        const pemasokId = selectPemasok.value;
        const container = document.getElementById('fifoGuideListContainer');
        if (!container) return;

        if (!pemasokId) {
            container.innerHTML = '<div style="padding: 10px; text-align: center; font-size: 11.5px; color: var(--text-muted);">Pilih pemasok terlebih dahulu.</div>';
            return;
        }

        container.innerHTML = '<div style="padding: 10px; text-align: center; font-size: 11.5px; color: var(--text-muted);">Memuat antrean FIFO...</div>';

        try {
            const resp = await fetch(`index.php?action=get_pemasok_fifo_stock&pemasok_id=${pemasokId}`);
            const data = await resp.json();

            if (!data.success || !data.fifo_list || data.fifo_list.length === 0) {
                container.innerHTML = '<div style="padding: 12px; text-align: center; font-size: 11.5px; color: var(--text-muted);">Tidak ada stok aktif di gudang untuk pemasok ini.</div>';
                return;
            }

            let html = '<table style="width: 100%; border-collapse: collapse; font-size: 11.5px;">';
            html += `
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: var(--text-muted); font-size: 10.5px; text-transform: uppercase;">
                        <th style="padding: 6px 8px; text-align: left;">ID Barcode</th>
                        <th style="padding: 6px 8px; text-align: left;">Produk</th>
                        <th style="padding: 6px 8px; text-align: right;">Netto</th>
                        <th style="padding: 6px 8px; text-align: center;">Umur Gudang</th>
                    </tr>
                </thead>
                <tbody>
            `;

            data.fifo_list.forEach(item => {
                const badgeColor = item.aging_badge === 'danger' ? 'background: #fee2e2; color: #b91c1c;' : (item.aging_badge === 'warning' ? 'background: #fef3c7; color: #b45309;' : 'background: #dcfce7; color: #15803d;');
                html += `
                    <tr style="border-bottom: 1px dashed #e2e8f0;">
                        <td style="padding: 6px 8px;">
                            <strong style="font-family: 'JetBrains Mono', monospace; font-size: 11.5px; color: #1e40af;">${item.id_label}</strong>
                        </td>
                        <td style="padding: 6px 8px;">
                            <strong>${item.nama_item}</strong>
                            <div style="font-size: 10px; color: var(--text-muted);">${item.nama_jenis}</div>
                        </td>
                        <td style="padding: 6px 8px; text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #047857;">
                            ${parseFloat(item.berat_netto).toFixed(2)} kg
                        </td>
                        <td style="padding: 6px 8px; text-align: center;">
                            <span class="badge" style="${badgeColor} font-size: 10.5px; font-weight: 700; padding: 2px 7px; border-radius: 4px;">
                                ${item.aging_text}
                            </span>
                        </td>
                    </tr>
                `;
            });
            html += '</tbody></table>';
            container.innerHTML = html;
        } catch (err) {
            container.innerHTML = `<div style="padding: 10px; text-align: center; font-size: 11.5px; color: var(--danger);">Gagal memuat antrean FIFO: ${err.message}</div>`;
        }
    }

    function toggleFifoGuidePanel(forceState) {
        const panel = document.getElementById('panelFifoGuide');
        if (!panel) return;
        const isHidden = panel.style.display === 'none' || panel.style.display === '';
        const shouldShow = forceState !== undefined ? forceState : isHidden;
        panel.style.display = shouldShow ? 'block' : 'none';
        if (shouldShow) {
            fetchFifoGuide();
            if (window.lucide) window.lucide.createIcons();
        }
    }

    // Modal Rincian Barcode Surat Jalan Pengeluaran
    async function viewOutboundDetail(trxId) {
        const modal = document.getElementById('modalOutboundDetail');
        const titleEl = document.getElementById('modalSjTitle');
        const subtitleEl = document.getElementById('modalSjSubtitle');
        const partyInfoEl = document.getElementById('modalSjPartyInfo');
        const tbody = document.getElementById('modalSjItemsBody');
        const btnPrint = document.getElementById('modalBtnPrintSj');

        modal.style.display = 'flex';
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 25px; color: var(--text-muted);">Memuat rincian barcode...</td></tr>';
        btnPrint.href = 'index.php?page=surat_jalan_print&id=' + trxId;

        try {
            const resp = await fetch('index.php?action=get_outbound_detail&id=' + trxId);
            const data = await resp.json();

            if (!data.success || !data.transaksi) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--danger); padding: 25px;">Gagal memuat rincian: ${data.message || 'Data tidak ditemukan'}</td></tr>`;
                return;
            }

            const t = data.transaksi;
            const sjNo = t.no_referensi_eksternal || t.no_bast || t.no_transaksi;
            titleEl.innerText = 'Rincian Surat Jalan: ' + sjNo;
            subtitleEl.innerText = `Waktu: ${t.created_at} • Total: ${t.total_label} Kemasan (${parseFloat(t.total_kg).toFixed(2)} kg)`;

            const namaPms = t.nama_pemasok ? `[${t.kode_pemasok}] ${t.nama_pemasok}` : 'Gudang Pemasok';
            const namaMtr = t.nama_mitra ? `[${t.kode_mitra}] ${t.nama_mitra}` : 'Mitra Penerima';

            partyInfoEl.innerHTML = `
                <div>
                    <div style="font-size: 11px; font-weight: 800; color: #1d4ed8; text-transform: uppercase; margin-bottom: 4px;">🚚 Pemasok Asal Stok (Gudang)</div>
                    <div style="font-weight: 800; font-size: 13px; color: #1e3a8a;">${namaPms}</div>
                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">Petugas Penyerah: <strong>${t.nama_penyerah || t.operator_pemroses || 'Petugas Gudang'}</strong></div>
                </div>
                <div>
                    <div style="font-size: 11px; font-weight: 800; color: #15803d; text-transform: uppercase; margin-bottom: 4px;">🏢 Mitra Bisnis Penerima (Tujuan)</div>
                    <div style="font-weight: 800; font-size: 13px; color: #065f46;">${namaMtr}</div>
                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">PIC / Penerima: <strong>${t.nama_penerima || '-'}</strong> • Catatan: <strong>${t.keterangan || '-'}</strong></div>
                </div>
            `;

            if (!t.items || t.items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 25px;">Tidak ada kemasan terdaftar dalam transaksi ini.</td></tr>';
                return;
            }

            tbody.innerHTML = '';
            t.items.forEach((it, idx) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="text-align: center; color: var(--text-muted); font-size: 11.5px;">${idx + 1}</td>
                    <td><strong style="font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--primary);">${it.id_label}</strong></td>
                    <td><strong>${it.nama_item}</strong></td>
                    <td><span class="badge badge-outline" style="font-size: 10.5px;">${it.nama_jenis || '-'}</span></td>
                    <td style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; color: #10b981;">${parseFloat(it.berat_netto).toFixed(2)} kg</td>
                    <td><code style="font-size: 10.5px;">${it.no_surat_jalan || it.batch_no || '-'}</code></td>
                `;
                tbody.appendChild(tr);
            });

            if (window.lucide) window.lucide.createIcons();

        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--danger); padding: 25px;">Kesalahan jaringan: ${e.message}</td></tr>`;
        }
    }

    function closeOutboundDetailModal() {
        document.getElementById('modalOutboundDetail').style.display = 'none';
    }

    // Switch Tampilan Riwayat Pengeluaran (Per Surat Jalan vs Rincian Barang Keluar)
    const OUTBOUND_COUNT_SJ = '<?= number_format($totalHistory, 0, ',', '.') ?>';
    const OUTBOUND_COUNT_BARANG = '<?= number_format($totalCount, 0, ',', '.') ?>';

    // Ekspor Excel sesuai tampilan aktif
    function exportActiveOutboundView() {
        const sj = document.getElementById('containerViewSj');
        if (sj && sj.style.display !== 'none') {
            exportTableToExcel('tableHistoryOutbound', 'Riwayat_Surat_Jalan_Pengeluaran');
        } else {
            exportTableToExcel('tableAllGoodsOutbound', 'Riwayat_Barang_Pengeluaran');
        }
    }

    function switchOutboundViewMode(mode) {
        const btnSj = document.getElementById('btnViewModeSj');
        const btnBarang = document.getElementById('btnViewModeBarang');
        const containerSj = document.getElementById('containerViewSj');
        const containerBarang = document.getElementById('containerViewBarang');

        const barSj = document.getElementById('filterBarSj');
        const barBarang = document.getElementById('filterBarBarang');
        const badge = document.getElementById('outboundCountBadge');
        if (barSj) barSj.style.display = mode === 'sj' ? '' : 'none';
        if (barBarang) barBarang.style.display = mode === 'sj' ? 'none' : '';
        if (badge) badge.textContent = (mode === 'sj' ? OUTBOUND_COUNT_SJ : OUTBOUND_COUNT_BARANG) + ' Data';

        if (mode === 'sj') {
            if (btnSj) btnSj.className = 'btn btn-primary btn-sm';
            if (btnBarang) btnBarang.className = 'btn btn-outline btn-sm';
            if (containerSj) containerSj.style.display = 'block';
            if (containerBarang) containerBarang.style.display = 'none';
        } else {
            if (btnSj) btnSj.className = 'btn btn-outline btn-sm';
            if (btnBarang) btnBarang.className = 'btn btn-primary btn-sm';
            if (containerSj) containerSj.style.display = 'none';
            if (containerBarang) containerBarang.style.display = 'block';
        }
        if (window.lucide) window.lucide.createIcons();
    }

    // Inisialisasi stok awal saat halaman dimuat
    document.addEventListener('DOMContentLoaded', () => {
        fetchPemasokStock();
    });
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
