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
    <form method="GET" action="index.php" id="<?= htmlspecialchars($c['form_id']) ?>" class="card no-print" style="padding: 14px 16px; margin-bottom: 18px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="page" value="pengeluaran">
        <input type="hidden" name="tab" value="riwayat">
        <input type="hidden" name="subtab" value="<?= htmlspecialchars($c['subtab']) ?>">
        <div style="flex: 1; min-width: 220px;">
            <label class="form-label">Cari</label>
            <input type="text" name="<?= $c['search_name'] ?>" class="form-control" placeholder="<?= htmlspecialchars($c['placeholder']) ?>" value="<?= htmlspecialchars($c['search_val']) ?>">
        </div>
        <?php if ($hasDates): ?>
        <div><label class="form-label">Dari</label><input type="date" name="<?= $c['start_name'] ?>" value="<?= htmlspecialchars($c['start_val']) ?>" class="form-control"></div>
        <div><label class="form-label">Sampai</label><input type="date" name="<?= $c['end_name'] ?>" value="<?= htmlspecialchars($c['end_val']) ?>" class="form-control"></div>
        <?php endif; ?>
        <div style="min-width: 190px;">
            <label class="form-label">Pemasok</label>
            <select name="<?= $c['pemasok_name'] ?>" class="form-control">
                <option value="">Semua pemasok</option>
                <?php foreach ($pemasokList as $pms): ?>
                    <option value="<?= $pms['id'] ?>" <?= (int)$c['pemasok_val'] === (int)$pms['id'] ? 'selected' : '' ?>><?= htmlspecialchars('[' . $pms['kode_pemasok'] . '] ' . $pms['nama_pemasok']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="min-width: 190px;">
            <label class="form-label">Mitra</label>
            <select name="<?= $c['mitra_name'] ?>" class="form-control">
                <option value="">Semua mitra</option>
                <?php foreach ($mitraList as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= (int)$c['mitra_val'] === (int)$m['id'] ? 'selected' : '' ?>><?= htmlspecialchars('[' . $m['kode_mitra'] . '] ' . $m['nama_mitra']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Tampilkan</button>
        <a href="index.php?page=pengeluaran&tab=riwayat&subtab=<?= htmlspecialchars($c['subtab']) ?>" class="btn btn-outline">Reset</a>
    </form>
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
    <?php if ($outboundTab === 'riwayat'): ?>
    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
        <?php $eksporNama = 'Riwayat_Pengeluaran'; include __DIR__ . '/layout/ekspor.php'; ?>
    </div>
    <?php endif; ?>
</div>


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
                    <div class="card-description">Pilih mitra tujuan, lalu scan barcode box (pemasok boleh campur)</div>
                </div>
            </div>

            <form id="formOutbound" onsubmit="return false;">
                <!-- 1. Asal stok: otomatis dari barcode, boleh campur pemasok -->
                <div class="form-group" style="margin-bottom: 14px;">
                    <div class="form-label" style="font-weight: 800;">1. Pemasok Asal Stok</div>
                    <div id="infoPemasokAsal" style="font-weight: 700; font-size: 13.5px; min-height: 24px; color: var(--text-muted);">Belum ada box discan</div>
                    <div style="margin-top: 3px; font-size: 11px; color: var(--text-muted);">
                        Tidak perlu dipilih. Asal tiap box dibaca dari barcodenya; satu surat jalan boleh berisi box dari beberapa pemasok.
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

                <!-- Termin pembayaran nota -->
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" for="termin_hari" style="margin: 0 0 4px; font-weight: 700;">Jatuh Tempo Nota</label>
                    <select id="termin_hari" class="form-control">
                        <?php foreach (\App\Services\AgingService::TERMIN as $hr => $lb): ?>
                            <option value="<?= $hr ?>" <?= $hr === 14 ? 'selected' : '' ?>><?= $lb ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Dihitung dari tanggal nota. Bisa diubah nanti di Payment In.</div>
                </div>

                <hr style="border: none; border-top: 1px solid var(--border); margin: 16px 0;">

                <!-- 6. Input Scan Barcode -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="inputBarcodeScan" style="font-weight: 800; color: var(--primary); display: flex; align-items: center; gap: 6px;">
                        <span>Input Scan Barcode Kemasan</span>
                    </label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="inputBarcodeScan" class="form-control form-control-lg" placeholder="Scan barcode kemasan..." style="font-size: 15px; font-family: 'JetBrains Mono', monospace; font-weight: 700;">
                        <button type="button" class="btn btn-primary" id="btnProsesScan" style="padding: 0 18px; font-weight: 700;">Scan</button>
                    </div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                        Arahkan scanner laser atau ketik ID label lalu tekan tombol Enter.
                        <span id="infoAutoScan" style="display: block; margin-top: 2px;"></span>
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
                        <span>Kemasan Terverifikasi (<span id="cartCount" style="color: var(--primary);">0</span> box)</span>
                    </h2>
                    <div class="card-description">Daftar kemasan fisik siap dikeluarkan dari gudang ke mitra penerima</div>
                </div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <span style="font-size: 13.5px; color: var(--text-muted); font-weight: 600;">Total Netto:</span>
                    <span id="cartTotalKg" style="font-size: 22px; font-weight: 900; color: var(--ok-fg); font-family: 'JetBrains Mono', monospace;">0.00 kg</span>
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
                                <div style="font-size: 12px; margin-top: 4px;">Pilih mitra di sebelah kiri, lalu arahkan scanner ke barcode kemasan fisik.</div>
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
<div class="no-print">

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

    <!-- Pilihan tampilan: Per Surat Jalan vs Rincian Barang Keluar -->
    <div style="display: flex; gap: 8px; margin-bottom: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
        <div style="display: flex; gap: 8px; align-items: center;">
            <button type="button" class="btn <?= $outboundSubTab === 'sj' ? 'btn-primary' : 'btn-outline' ?> btn-sm" id="btnViewModeSj" onclick="switchOutboundViewMode('sj')">Per Surat Jalan</button>
            <button type="button" class="btn <?= $outboundSubTab === 'barang' ? 'btn-primary' : 'btn-outline' ?> btn-sm" id="btnViewModeBarang" onclick="switchOutboundViewMode('barang')">Rincian Barang Keluar</button>
        </div>
        <span id="outboundCountBadge" style="font-size: 12.5px; color: var(--text-muted);"><?= number_format($outboundSubTab === 'sj' ? $totalHistory : $totalCount, 0, ',', '.') ?> Data</span>
    </div>

    <div class="card">
    <!-- ========================================================================= -->
    <!-- TAMPILAN 1: PER SURAT JALAN PENGELUARAN                                   -->
    <!-- ========================================================================= -->
    <div id="containerViewSj" style="<?= $outboundSubTab === 'sj' ? 'display: block;' : 'display: none;' ?>">

    <!-- Tabel Riwayat Transaksi Pengeluaran (Fokus Fisik Gudang) -->
    <div class="table-responsive">
        <table class="table-custom" id="tableHistoryOutbound" data-no-tools>
            <thead>
                <tr>
                    <th style="width: 35px; text-align: center;">No</th>
                    <th style="width: 185px;">No. Surat Jalan / DO</th>
                    <th style="width: 130px;">Waktu Keluar</th>
                    <th style="width: 175px;">Pemasok Asal</th>
                    <th style="width: 175px;">Mitra Penerima</th>
                    <th style="text-align: right;">Kemasan</th>
                    <th style="text-align: right;">Total Netto</th>
                    <th>Petugas</th>
                    <th>Status</th>
                    <th style="text-align: center; width: 160px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($historyOutbound)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 36px;">
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
                        <td style="text-align: center; color: var(--text-muted);"><?= $hNo++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($sjKeluar) ?></strong>
                            <div style="font-size: 11.5px; color: var(--text-muted);"><?= htmlspecialchars($ht['no_transaksi']) ?></div>
                        </td>
                        <td style="color: var(--text-muted);"><?= date('d/m/Y H:i', strtotime($ht['created_at'])) ?></td>
                        <td><?= htmlspecialchars($ht['nama_pemasok'] ?? 'Beberapa pemasok') ?></td>
                        <td>
                            <?= htmlspecialchars($ht['nama_mitra'] ?? 'Mitra') ?>
                            <?php if (!empty($ht['nama_penerima']) && stripos($ht['nama_penerima'], (string)($ht['nama_mitra'] ?? '')) === false): ?>
                                <div style="font-size: 11.5px; color: var(--text-muted);">PIC: <?= htmlspecialchars($ht['nama_penerima']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;"><?= number_format($ht['total_label']) ?> box</td>
                        <td style="text-align: right; font-weight: 600;"><?= Helper::formatKg($ht['total_kg']) ?></td>
                        <td style="color: var(--text-muted);"><?= htmlspecialchars($ht['operator_name'] ?? 'Petugas Gudang') ?></td>
                        <td>
                            <?php if ($ht['jenis_transaksi'] === 'PENGELUARAN_BATAL'): ?>
                                <span class="badge badge-danger" title="<?= htmlspecialchars($ht['alasan_batal'] ?? '', ENT_QUOTES) ?>">Dibatalkan</span>
                                <?php if (!empty($ht['alasan_batal'])): ?><span style="font-size: 12px; color: var(--text-muted);"> <?= htmlspecialchars($ht['alasan_batal']) ?></span><?php endif; ?>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">Selesai</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                                <?php if ($ht['jenis_transaksi'] === 'PENGELUARAN'): ?>
                                <a href="index.php?page=surat_jalan_print&id=<?= $ht['id'] ?>" target="_blank" class="btn btn-outline btn-sm" style="padding: 5px 9px; font-size: 12px; color: var(--aktif-fg); border-color: var(--border-hover); display: inline-flex; align-items: center; gap: 4px;" title="Cetak Dokumen Surat Jalan Resmi A4">
                                    <i data-lucide="printer" style="width: 14px; height: 14px;"></i>
                                    <span>Cetak</span>
                                </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline btn-sm" onclick="viewOutboundDetail(<?= $ht['id'] ?>)" style="padding: 5px 9px; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;" title="Lihat Rincian Kemasan">
                                    <i data-lucide="eye" style="width: 14px; height: 14px;"></i>
                                    <span>Rincian</span>
                                </button>
                                <?php if ($ht['jenis_transaksi'] === 'PENGELUARAN'): ?>
                                <button type="button" class="btn btn-outline btn-sm" onclick="batalDokumen('batal_pengeluaran', { id: <?= (int)$ht['id'] ?> }, 'surat jalan keluar <?= htmlspecialchars($ht['no_transaksi'], ENT_QUOTES) ?>')" style="padding: 5px 9px; font-size: 12px; color: var(--bad-fg); border-color: rgba(220,38,38,0.35);" title="Batalkan dokumen"><i data-lucide="ban" style="width: 14px; height: 14px;"></i><span>Batalkan</span></button>
                                <?php else: ?>
                                <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 5px 9px; font-size: 12px; opacity: .45; cursor: not-allowed;" title="Dokumen sudah dibatalkan"><i data-lucide="ban" style="width: 14px; height: 14px;"></i><span>Batalkan</span></button>
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
    <?php
    require_once __DIR__ . '/layout/pager_riwayat.php';
    $hDari = $totalHistory ? ($hPage - 1) * $hPerPage + 1 : 0;
    renderPagerRiwayat($hPage, $totalHistoryPages, 'Menampilkan ' . $hDari . '-' . min($totalHistory, $hPage * $hPerPage) . ' dari ' . number_format($totalHistory) . ' surat jalan', $baseHistoryUrl, 'hp');
    ?>
    </div>
    <!-- END TAMPILAN 1: PER SURAT JALAN -->

    <!-- ========================================================================= -->
    <!-- TAMPILAN 2: RINCIAN BARANG KELUAR / KEMASAN                               -->
    <!-- ========================================================================= -->
    <div id="containerViewBarang" style="<?= $outboundSubTab === 'barang' ? 'display: block;' : 'display: none;' ?>">

    <!-- Tabel Data Seluruh Barang Keluar -->
    <div class="table-responsive">
        <table class="table-custom" id="tableAllGoodsOutbound" data-no-tools>
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
                        <td style="text-align: center; color: var(--text-muted);"><?= $no++ ?></td>
                        <td><strong><?= htmlspecialchars($item['id_label']) ?></strong></td>
                        <td><?= htmlspecialchars($item['nama_pemasok'] ?? 'Pemasok') ?></td>
                        <td>
                            <strong><?= htmlspecialchars($item['nama_item']) ?></strong>
                            <div style="font-size: 11.5px; color: var(--text-muted);"><?= htmlspecialchars($item['nama_jenis']) ?></div>
                        </td>
                        <td style="text-align: right; font-weight: 600;"><?= Helper::formatKg($berat) ?></td>
                        <td><?= (!empty($namaMitraKeluar) && $namaMitraKeluar !== '-') ? htmlspecialchars($namaMitraKeluar) : '-' ?></td>
                        <td><?= htmlspecialchars($sjKeluar) ?></td>
                        <td style="color: var(--text-muted);"><?= $waktuKeluar ?></td>
                        <td style="text-align: center; color: var(--text-muted);">Sudah keluar</td>
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
    <?php
    $oDari = $totalCount ? ($page - 1) * $perPage + 1 : 0;
    renderPagerRiwayat($page, $totalPages, 'Menampilkan ' . $oDari . '-' . min($totalCount, $page * $perPage) . ' dari ' . number_format($totalCount, 0, ',', '.') . ' kemasan', $baseOutUrl, 'p');
    ?>
    </div>
    <!-- END TAMPILAN 2: RINCIAN BARANG KELUAR -->

    </div>
</div>
</div>
<!-- END SECTION OUTBOUND RIWAYAT -->

<!-- Modal Detail Rincian Barcode Surat Jalan Pengeluaran -->
<div id="modalOutboundDetail" class="sheet-preview-backdrop no-print" style="display: none; align-items: center; justify-content: center; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 9999;">
    <div class="sheet-preview-dialog" style="max-width: 820px; width: 95%; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow: hidden; display: flex; flex-direction: column; max-height: 90vh;">
        <div class="sheet-preview-header" style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 800;" id="modalSjTitle">Rincian Surat Jalan</h3>
                <div style="font-size: 12.5px; color: var(--text-muted); margin-top: 2px;" id="modalSjSubtitle">-</div>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <a href="#" id="modalBtnPrintSj" target="_blank" class="btn btn-primary btn-sm">Cetak Surat Jalan</a>
                <button type="button" class="btn btn-outline btn-sm" onclick="closeOutboundDetailModal()">Tutup</button>
            </div>
        </div>
        <div style="padding: 16px 20px; overflow-y: auto;">
            <!-- Info asal dan tujuan -->
            <div id="modalSjPartyInfo" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px 24px; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--border);"></div>

            <!-- Tabel barcode transaksi -->
            <div class="table-responsive" style="max-height: none;">
                <table class="table-custom" data-no-tools>
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">No</th>
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
    const selectMitra = document.getElementById('outbound_mitra_id');
    // Fokus otomatis saat halaman dibuka. Di HP/tablet (layar sentuh) keyboard layar ditahan dengan inputmode=none,
    // sehingga scanner bawaan tetap bisa mengetik; keyboard baru muncul bila kolom diketuk langsung.
    (function () {
        const sentuh = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
        if (sentuh) {
            inputScan.setAttribute('inputmode', 'none');
            inputScan.addEventListener('pointerdown', function () {
                if (inputScan.getAttribute('inputmode') !== 'none') return;
                inputScan.setAttribute('inputmode', 'text');
                if (document.activeElement === inputScan) { inputScan.blur(); setTimeout(function () { inputScan.focus(); }, 30); }
            });
        }
        inputScan.focus({ preventScroll: true });
    })();

    // Notifikasi scan: toast SweetAlert + bunyi (true = berhasil, 'warning' = perhatian, false = gagal)
    function showAlert(msg, isSuccess = true) {
        if (isSuccess === 'warning') {
            showToast(msg, 'warning');
            if (window.scannerAudio) window.scannerAudio.playSuccessBeep();
        } else if (isSuccess === true) {
            showToast(msg, 'success');
            if (window.scannerAudio) window.scannerAudio.playSuccessBeep();
        } else {
            showToast(msg, 'error');
            if (window.scannerAudio) window.scannerAudio.playErrorBuzz();
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

    // Tarif jual mitra diambil dari Master Harga untuk mitra yang sedang dipilih. Dihitung ulang tiap kali mitra berganti
    // atau barcode baru discan, karena mitra bisa dipilih sesudah scan.
    // Harga jual per produk untuk mitra terpilih disimpan sementara (cache) supaya scan berikutnya tidak memanggil server lagi
    // untuk produk yang sama. Hanya satu panggilan per PRODUK (bukan per box); force=true mengambil ulang (mis. setelah
    // harga diubah di tab Master Harga).
    const cacheHarga = new Map();
    async function refreshHargaMitra(force) {
        const mitraId = parseInt(selectMitra.value || 0, 10);
        if (!mitraId) {
            scannedLabels.forEach(l => { l.harga_mitra = 0; l.subtotal_jual = 0; });
            renderCart();
            return;
        }
        if (force === true) cacheHarga.clear();
        const perItem = new Map();   // item_id -> barcode wakil
        scannedLabels.forEach((l, bc) => {
            const k = mitraId + ':' + l.item_id;
            if (!cacheHarga.has(k) && !perItem.has(k)) perItem.set(k, bc);
        });
        await Promise.all(Array.from(perItem.entries()).map(async ([k, bc]) => {
            try {
                const resp = await fetch(`index.php?action=verify_label&barcode=${encodeURIComponent(bc)}&mitra_id=${mitraId}`);
                const d = await resp.json();
                if (d.success) cacheHarga.set(k, parseFloat(d.label.harga_mitra || 0));
            } catch (e) { /* tarif tetap seperti sebelumnya */ }
        }));
        scannedLabels.forEach(l => {
            const k = mitraId + ':' + l.item_id;
            if (cacheHarga.has(k)) {
                l.harga_mitra = cacheHarga.get(k);
                l.subtotal_jual = Math.round(parseFloat(l.berat_netto || 0) * l.harga_mitra * 100) / 100;
                l.profit = l.subtotal_jual - parseFloat(l.subtotal_beli || 0);
            }
        });
        renderCart();
    }
    selectMitra.addEventListener('change', () => refreshHargaMitra());
    // Kembali dari tab Master Harga (harga baru disimpan di sana): tarif keranjang diambil ulang otomatis
    document.addEventListener('visibilitychange', () => { if (!document.hidden && scannedLabels.size) refreshHargaMitra(true); });

    async function handleScan(barcode) {
        barcode = barcode.trim();
        if (!barcode) return;
        inputScan.value = '';

        if (scannedLabels.has(barcode)) {
            showAlert(`Barcode ${barcode} sudah discan di sesi ini.`, false);
            return;
        }

        try {
            const mitraScan = parseInt(selectMitra.value || 0, 10);
            const url = `index.php?action=verify_label&barcode=${encodeURIComponent(barcode)}${mitraScan ? '&mitra_id=' + mitraScan : ''}`;
            const resp = await fetch(url);
            const data = await resp.json();

            if (!data.success) {
                showAlert(data.message || 'Barcode tidak dapat diverifikasi.', false);
                return;
            }

            const label = data.label;
            if (mitraScan && label.item_id) cacheHarga.set(mitraScan + ':' + label.item_id, parseFloat(label.harga_mitra || 0));

            // Validasi status
            if (label.status !== 'AKTIF') {
                showAlert(`Barcode ${barcode} berstatus ${label.status} sehingga tidak bisa dikeluarkan.`, false);
                return;
            }

            // Sinkronkan Pemasok & Mitra dengan barcode yang discan
            const syncNotes = [];

            if (label.mitra_id) {
                if (String(selectMitra.value) !== String(label.mitra_id) && syncSelect(selectMitra, label.mitra_id)) {
                    syncNotes.push('mitra');
                }
            }

            scannedLabels.set(barcode, label);
            renderCart();
            refreshHargaMitra();
            perbaruiAsalPemasok();

            if (data.fifo_info && data.fifo_info.has_older_stock) {
                // Notifikasi reguler scan berhasil di alert box atas
                showAlert(`<strong>${label.nama_item}</strong> &middot; ${parseFloat(label.berat_netto).toFixed(2)} kg ditambahkan${syncNotes.length ? '<br>' + syncNotes.join(' & ') + ' disesuaikan otomatis' : ''}`, true);

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

                    const pmsId = label.pemasok_id || '';
                    const itmId = label.item_id || '';
                    const stockUrl = `index.php?page=barang_datang&stok=AKTIF&dari=2000-01-01&item=${encodeURIComponent(itmId)}${pmsId ? '&pihak=' + encodeURIComponent(pmsId) : ''}`;

                    Toast.fire({
                        icon: 'warning',
                        html: `
                            <div style="display: flex; flex-direction: column; gap: 4px; text-align: left; padding: 2px 0 2px 14px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                                    <span style="font-size: 13.5px; font-weight: 700; color: var(--text-main);">
                                        Ada ${data.fifo_info.older_count} box ${label.nama_item}
                                    </span>
                                    <a href="${stockUrl}" target="_blank" title="Buka Sisa Stok Gudang" style="display: inline-flex; align-items: center; justify-content: center; color: var(--text-muted); text-decoration: none; padding: 2px; border-radius: 4px; transition: color 0.15s ease;" onmouseover="this.style.color='#1e293b'" onmouseout="this.style.color='#94a3b8'">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                    </a>
                                </div>
                                <div style="font-size: 12px; color: var(--text-muted);">
                                    Lebih lama mengendap (${data.fifo_info.aging_text})
                                </div>
                            </div>
                        `
                    });
                }
            } else {
                showAlert(`<strong>${label.nama_item}</strong> &middot; ${parseFloat(label.berat_netto).toFixed(2)} kg ditambahkan${syncNotes.length ? '<br>' + syncNotes.join(' & ') + ' disesuaikan otomatis' : ''}`, true);
            }

        } catch (err) {
            showAlert('Koneksi bermasalah saat memeriksa barcode: ' + err.message, false);
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
                        <div style="font-size: 12px; margin-top: 4px;">Pilih mitra di samping, lalu scan barcode kemasan fisik di atas.</div>
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
                        <a href="index.php?page=barang_datang&stok=AKTIF&dari=2000-01-01&item=${encodeURIComponent(label.item_id || '')}${label.pemasok_id ? '&pihak=' + encodeURIComponent(label.pemasok_id) : ''}" target="_blank" title="Lihat riwayat harga dan stok aktif ${label.nama_item}" style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 4px; background: var(--bg-muted); color: var(--text-muted); border: 1px solid var(--border); text-decoration: none; transition: all 0.2s;" onmouseover="this.style.color='var(--primary)'; this.style.borderColor='var(--primary)';" onmouseout="this.style.color='var(--text-muted)'; this.style.borderColor='var(--border)';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                        </a>
                        <a href="index.php?page=master&tab=harga_mitra&item=${encodeURIComponent(label.item_id || '')}${selectMitra.value ? '&pihak=' + encodeURIComponent(selectMitra.value) : ''}" target="_blank" class="btn-ikon-harga" title="Atur harga jual ${label.nama_item} di Master Harga (tab baru)" style="width: auto; height: 22px; flex-shrink: 0;">Rp &#8599;</a>
                    </div>
                    ${hrgMitra <= 0 && !selectMitra.value
                        ? `<div style="font-size: 10.5px; color: var(--text-muted); font-weight: 600; margin-top: 3px;">Pilih mitra penerima untuk melihat tarif jual</div>`
                        : hrgMitra <= 0 
                        ? `<div style="font-size: 10.5px; color: var(--bad-fg); font-weight: 700; margin-top: 3px;">⚠️ Tarif Jual: Rp 0 (Belum diatur di Master)</div>`
                        : `<div style="font-size: 10.5px; color: var(--ok-fg); font-weight: 600; margin-top: 2px;">Tarif: Rp ${new Intl.NumberFormat('id-ID').format(hrgMitra)}/kg • Subtotal: Rp ${new Intl.NumberFormat('id-ID').format(netto * hrgMitra)}</div>`
                    }
                </td>
                <td>
                    <span class="badge badge-outline" style="font-size: 11px;">${label.nama_jenis || '-'}</span>
                </td>
                <td style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; font-size: 13px; color: var(--ok-fg);">
                    ${netto.toFixed(2)} kg
                </td>
                <td>
                    <code style="font-size: 11px;">${label.no_surat_jalan || label.batch_no || '-'}</code>
                    <div style="font-size: 10.5px; color: var(--text-muted); font-weight: 600; margin-top: 2px;">${label.nama_pemasok ? '[' + label.kode_pemasok + '] ' + label.nama_pemasok : ''}</div>
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
            perbaruiAsalPemasok();
            showAlert(`Barcode ${barcode} dikeluarkan dari daftar.`, true);
        }
    }

    function triggerSimulatedScan(barcode) {
        handleScan(barcode);
    }

    document.getElementById('btnProsesScan').addEventListener('click', () => {
        handleScan(inputScan.value);
    });

    // Scan otomatis: begitu ID barcode lengkap terbaca (scanner mengetik cepat), langsung diproses tanpa menekan Enter.
    // Hanya berlaku bila mitra penerima sudah dipilih; tanpa mitra, tarif tidak bisa dihitung, jadi scan tetap manual dengan Enter.
    const POLA_BARCODE = /^[A-Za-z0-9]+(-[A-Za-z0-9]+){2,}-\d{5,}$/;
    const infoAutoScan = document.getElementById('infoAutoScan');
    let timerAutoScan = null;

    function perbaruiInfoAutoScan() {
        if (!infoAutoScan) return;
        if (selectMitra.value) {
            infoAutoScan.style.color = 'var(--ok-fg)';
            infoAutoScan.textContent = 'Scan otomatis aktif: barcode diproses begitu terbaca, tanpa menekan Enter.';
        } else {
            infoAutoScan.style.color = 'var(--warn-fg)';
            infoAutoScan.textContent = 'Pilih Mitra Bisnis Penerima dulu agar scan berjalan otomatis.';
        }
    }
    selectMitra.addEventListener('change', perbaruiInfoAutoScan);
    // Setelah mitra dipilih, kursor kembali ke kolom scan (tanpa memunculkan keyboard layar di HP)
    selectMitra.addEventListener('change', function () { if (selectMitra.value) inputScan.focus({ preventScroll: true }); });
    perbaruiInfoAutoScan();

    inputScan.addEventListener('input', () => {
        clearTimeout(timerAutoScan);
        const v = inputScan.value.trim();
        if (!selectMitra.value || !POLA_BARCODE.test(v)) return;
        timerAutoScan = setTimeout(() => {
            if (inputScan.value.trim() === v) handleScan(v);
        }, 150);
    });

    inputScan.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(timerAutoScan);
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
                perbaruiAsalPemasok();
                showAlert('Sesi scan dikosongkan.', true);
            }
        }
    });

    // Submit pengeluaran stok pemasok ke mitra
    document.getElementById('btnSubmitOutbound').addEventListener('click', async () => {
        if (scannedLabels.size === 0) {
            showAlert('Belum ada kemasan yang discan. Scan minimal satu barcode.', false);
            return;
        }

        const mitraId = selectMitra.value;
        const noRef = document.getElementById('no_referensi').value.trim();
        const namaPenerima = document.getElementById('nama_penerima') ? document.getElementById('nama_penerima').value.trim() : '';
        const ket = document.getElementById('keterangan') ? document.getElementById('keterangan').value.trim() : '';

        if (!mitraId) {
            showAlert('Pilih mitra tujuan pengeluaran terlebih dahulu.', false);
            selectMitra.focus();
            return;
        }

        if (!noRef) {
            showAlert('Isi nomor surat jalan / DO terlebih dahulu.', false);
            return;
        }

        const payload = {
            mitra_id: parseInt(mitraId),
            no_referensi: noRef,
            nama_penerima: namaPenerima,
            keterangan: ket,
            termin_hari: document.getElementById('termin_hari') ? document.getElementById('termin_hari').value : '',
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
                        <p style="color: var(--warn-fg); font-weight: 700; margin-bottom: 8px;">⚠️ Dampak: Transaksi akan diterbitkan dengan nilai tagihan Rp 0 dan tidak akan muncul sebagai piutang aktif di AR Aging.</p>
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
                            <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--ok-solid); padding: 10px; border-radius: 6px;">
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
                showAlert('Pengeluaran belum bisa diselesaikan: ' + data.message, false);
                submitBtn.disabled = false;
                submitBtn.innerHTML = oldHtml;
            }
        } catch (err) {
            showAlert('Pengeluaran gagal diproses: ' + err.message, false);
            submitBtn.disabled = false;
            submitBtn.innerHTML = oldHtml;
        }
    });

    // Ringkasan asal pemasok dari box yang discan, dan nomor surat jalan mengikuti: satu pemasok -> kode pemasok,
    // campur pemasok -> kode mitra penerima. Nomor hanya diambil ulang bila kodenya berganti.
    let kodeSjTerakhir = null;
    async function ambilNomorSj(tampilkan) {
        const set = new Map();
        scannedLabels.forEach(l => { if (l.pemasok_id) set.set(String(l.pemasok_id), l); });
        let kode = '';
        if (set.size === 1) kode = Array.from(set.values())[0].kode_pemasok || '';
        if (!kode) {
            const o = selectMitra.options[selectMitra.selectedIndex];
            kode = (o && o.getAttribute('data-kode')) || '';
        }
        if (!kode) return;
        if (!tampilkan && kode === kodeSjTerakhir) return;
        try {
            const resp = await fetch(`index.php?action=get_next_outbound_sj&kode=${encodeURIComponent(kode)}`);
            const data = await resp.json();
            if (data.success && data.no_surat_jalan) {
                kodeSjTerakhir = kode;
                document.getElementById('no_referensi').value = data.no_surat_jalan;
                if (tampilkan) showAlert('Nomor surat jalan diperbarui menjadi ' + data.no_surat_jalan, true);
            }
        } catch (e) { console.error(e); }
    }

    function perbaruiAsalPemasok() {
        const el = document.getElementById('infoPemasokAsal');
        const set = new Map();
        scannedLabels.forEach(l => {
            const k = l.pemasok_id ? '[' + l.kode_pemasok + '] ' + l.nama_pemasok : 'Tanpa pemasok';
            set.set(k, (set.get(k) || 0) + 1);
        });
        if (el) {
            if (!set.size) { el.style.color = 'var(--text-muted)'; el.textContent = 'Belum ada box discan'; }
            else {
                el.style.color = 'var(--text-main)';
                el.innerHTML = Array.from(set.entries()).map(([k, n]) => k.replace(/</g, '&lt;') + ' <span style="font-weight:600;color:var(--text-muted);">(' + n + ' box)</span>').join('<br>');
            }
        }
        ambilNomorSj(false);
    }
    selectMitra.addEventListener('change', () => ambilNomorSj(false));

    // Tombol Generate Ulang Surat Jalan
    document.getElementById('btnRefreshSj')?.addEventListener('click', () => ambilNomorSj(true));

    // Panduan Antrean FIFO Stok Tertua
    async function fetchFifoGuide() {
        const container = document.getElementById('fifoGuideListContainer');
        if (!container) return;

        container.innerHTML = '<div style="padding: 10px; text-align: center; font-size: 11.5px; color: var(--text-muted);">Memuat antrean FIFO...</div>';

        try {
            const resp = await fetch(`index.php?action=get_pemasok_fifo_stock`);
            const data = await resp.json();

            if (!data.success || !data.fifo_list || data.fifo_list.length === 0) {
                container.innerHTML = '<div style="padding: 12px; text-align: center; font-size: 11.5px; color: var(--text-muted);">Tidak ada stok aktif di gudang untuk pemasok ini.</div>';
                return;
            }

            let html = '<table style="width: 100%; border-collapse: collapse; font-size: 11.5px;">';
            html += `
                <thead>
                    <tr style="background: var(--bg-muted); border-bottom: 1px solid var(--border); color: var(--text-muted); font-size: 10.5px; text-transform: uppercase;">
                        <th style="padding: 6px 8px; text-align: left;">ID Barcode</th>
                        <th style="padding: 6px 8px; text-align: left;">Produk</th>
                        <th style="padding: 6px 8px; text-align: right;">Netto</th>
                        <th style="padding: 6px 8px; text-align: center;">Umur Gudang</th>
                    </tr>
                </thead>
                <tbody>
            `;

            data.fifo_list.forEach(item => {
                const badgeColor = item.aging_badge === 'danger' ? 'background: var(--bad-bg); color: var(--bad-fg);' : (item.aging_badge === 'warning' ? 'background: var(--warn-bg); color: var(--warn-fg);' : 'background: var(--ok-bg); color: var(--ok-fg);');
                html += `
                    <tr style="border-bottom: 1px dashed var(--border);">
                        <td style="padding: 6px 8px;">
                            <strong style="font-family: 'JetBrains Mono', monospace; font-size: 11.5px; color: var(--aktif-fg);">${item.id_label}</strong>
                        </td>
                        <td style="padding: 6px 8px;">
                            <strong>${item.nama_item}</strong>
                            <div style="font-size: 10px; color: var(--text-muted);">${item.nama_jenis}</div>
                        </td>
                        <td style="padding: 6px 8px; text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--ok-fg);">
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
            subtitleEl.innerText = `${t.created_at}  ·  ${t.total_label} kemasan  ·  ${parseFloat(t.total_kg).toFixed(2)} kg`;

            const namaPms = t.nama_pemasok || 'Gudang Pemasok';
            const namaMtr = t.nama_mitra || 'Mitra Penerima';

            const bidang = (label, isi) => `<div><div style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 2px;">${label}</div><div style="font-weight: 600; font-size: 13.5px;">${isi}</div></div>`;
            partyInfoEl.innerHTML =
                bidang('Pemasok asal', namaPms) +
                bidang('Mitra penerima', namaMtr) +
                bidang('Petugas penyerah', t.nama_penyerah || t.operator_pemroses || 'Petugas Gudang') +
                bidang('PIC / penerima', t.nama_penerima || '-') +
                bidang('Catatan', t.keterangan || '-');

            if (!t.items || t.items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 25px;">Tidak ada kemasan terdaftar dalam transaksi ini.</td></tr>';
                return;
            }

            tbody.innerHTML = '';
            t.items.forEach((it, idx) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="text-align: center; color: var(--text-muted);">${idx + 1}</td>
                    <td><strong>${it.id_label}</strong></td>
                    <td><strong>${it.nama_item}</strong></td>
                    <td style="color: var(--text-muted);">${it.nama_jenis || '-'}</td>
                    <td style="text-align: right; font-weight: 600;">${parseFloat(it.berat_netto).toFixed(2)} kg</td>
                    <td>${it.no_surat_jalan || it.batch_no || '-'}</td>
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

</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
