<?php
/**
 * Modul Stok Opname Digital Berbasis Barcode (Vibrant Edition)
 * Fitur: Menampilkan seluruh barang yang belum keluar (Aktif), Filter per Lokasi Gudang & Mitra, Real-time Physical Verification, dan Ekspor Excel
 */

use App\Core\Helper;
use App\Models\Mitra;
use App\Models\Pemasok;
use App\Models\Item;
use App\Models\Label;

$pageTitle = 'Stok Opname Gudang - Warehouse Management System';
$pemasokList = Pemasok::getAll();

// Filter Pemasok Asal, No. Surat Jalan, Bulan/Periode & Pencarian
$filterPemasok    = isset($_GET['filter_pemasok']) && $_GET['filter_pemasok'] !== '' ? (int)$_GET['filter_pemasok'] : null;
$filterSuratJalan = trim($_GET['filter_sj'] ?? '');
$search           = trim($_GET['search'] ?? '');
// Default bulan ini saat user mengakses halaman jika parameter belum ditentukan di URL
$filterBulan      = isset($_GET['filter_bulan']) ? trim($_GET['filter_bulan']) : date('Y-m'); // Format: YYYY-MM e.g. 2026-11

$startDate = null;
$endDate   = null;
$namaBulanIndo = '';

if (!empty($filterBulan) && preg_match('/^\d{4}-\d{2}$/', $filterBulan)) {
    $startDate = $filterBulan . '-01';
    $endDate   = date('Y-m-t', strtotime($startDate));
    
    $bulanMap = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    $bParts = explode('-', $filterBulan);
    $namaBulanIndo = ($bulanMap[$bParts[1]] ?? $bParts[1]) . ' ' . $bParts[0];
}

$filterParams = [
    'pemasok_id'         => $filterPemasok,
    'no_surat_jalan'     => $filterSuratJalan,
    'search'             => $search,
    'start_date'         => $startDate,
    'end_date'           => $endDate
];

// Ambil seluruh persediaan barang yang masuk pada periode filter ini
$activeGoods = Label::getFiltered($filterParams, 1000, 0);
$totalFisikCount = count($activeGoods);
$totalFisikKg = array_sum(array_column($activeGoods, 'berat_netto'));

// Hitung rekap berapa yang masih di gudang utama vs berapa yang sudah mutasi ke mitra
$countGudangUtama = 0;
$kgGudangUtama = 0.0;
$countMutasiMitra = 0;
$kgMutasiMitra = 0.0;
$countOpnameMatched = 0;
$kgOpnameMatched = 0.0;

foreach ($activeGoods as $itemG) {
    $isMutated = !empty($itemG['mitra_id']) && (int)$itemG['mitra_id'] > 0;
    $isOpnameVerified = !empty($itemG['last_opname_at']);
    if ($isMutated) {
        $countMutasiMitra++;
        $kgMutasiMitra += (float)$itemG['berat_netto'];
    } else {
        $countGudangUtama++;
        $kgGudangUtama += (float)$itemG['berat_netto'];
        if ($isOpnameVerified) {
            $countOpnameMatched++;
            $kgOpnameMatched += (float)$itemG['berat_netto'];
        }
    }
}
$pctOpnameMatched = $countGudangUtama > 0 ? round(($countOpnameMatched / $countGudangUtama) * 100) : 0;

// Ambil daftar nomor surat jalan unik pada periode ini
$activeSjList = Label::getActiveSuratJalanList(null, false);

require_once __DIR__ . '/layout/header.php';
?>

<div class="page-header no-print">
    <div>
        <h1 class="page-title">Stok Opname Persediaan Gudang Utama</h1>
        <p class="page-subtitle">Pencocokan stok fisik riil di gudang utama yang belum dimutasi ke mitra vs sistem database menggunakan scanner genggam</p>
    </div>
</div>

<div id="opnameAlertBox" style="display: none; margin-bottom: 20px;"></div>

<!-- STATS SUMMARY COUNTER WIDGETS -->
<div class="grid grid-cols-4 no-print" style="margin-bottom: 24px; gap: 16px;">
    <div class="kpi-card">
        <div class="kpi-body">
            <div class="kpi-label">Total Masuk Periode Ini</div>
            <div class="kpi-value" id="statTotalCount"><?= number_format($totalFisikCount, 0, ',', '.') ?><span class="kpi-unit">Kemasan</span></div>
            <div class="kpi-sub">Total bobot: <?= Helper::formatKg($totalFisikKg) ?></div>
        </div>
        <div class="kpi-icon-pod"><i data-lucide="package" style="width: 18px; height: 18px;"></i></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-body">
            <div class="kpi-label">Sisa Fisik Gudang Utama</div>
            <div class="kpi-value" id="statGudangCount"><?= number_format($countGudangUtama, 0, ',', '.') ?><span class="kpi-unit">Kemasan</span></div>
            <div class="kpi-sub">Bobot: <?= Helper::formatKg($kgGudangUtama) ?></div>
        </div>
        <div class="kpi-icon-pod"><i data-lucide="warehouse" style="width: 18px; height: 18px;"></i></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-body">
            <div class="kpi-label">Sudah Mutasi ke Mitra</div>
            <div class="kpi-value" id="statMutasiCount"><?= number_format($countMutasiMitra, 0, ',', '.') ?><span class="kpi-unit">Kemasan</span></div>
            <div class="kpi-sub">Bobot: <?= Helper::formatKg($kgMutasiMitra) ?></div>
        </div>
        <div class="kpi-icon-pod"><i data-lucide="truck" style="width: 18px; height: 18px;"></i></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-body">
            <div class="kpi-label">Hasil Scan Fisik</div>
            <div class="kpi-value" id="statMatchedCount"><?= number_format($countOpnameMatched, 0, ',', '.') ?><span class="kpi-unit">Kemasan</span></div>
            <div class="kpi-sub" id="statMatchedPct"><?= $pctOpnameMatched ?>% terverifikasi (<?= $countOpnameMatched ?>/<?= $countGudangUtama ?>)</div>
        </div>
        <div class="kpi-icon-pod"><i data-lucide="scan-line" style="width: 18px; height: 18px;"></i></div>
    </div>
</div>

<div class="grid grid-cols-3 no-print" style="grid-template-columns: 1fr; align-items: start; margin-bottom: 30px; gap: 20px;">
    <!-- Kolom 1: Sesi Scanner & Filter Cepat -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">
                    <i data-lucide="scan-line" style="width: 18px; height: 18px; color: var(--primary);"></i>
                    <span>Sesi Scanner Opname</span>
                </h2>
                <div class="card-description">Arahkan scanner genggam ke barcode kemasan</div>
            </div>
        </div>

        <!-- Input Scanner Laser Genggam -->
        <div style="padding: 16px 20px; display: grid; grid-template-columns: minmax(300px, 1fr) 1.4fr; gap: 20px; align-items: center;">
            <div class="form-group" style="margin: 0;">
                <label class="form-label" for="opname_scan_input" style="font-size: 13px; font-weight: 800; color: var(--primary);">
                    Input Scan Barcode Kemasan Fisik
                </label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="opname_scan_input" class="form-control form-control-lg" placeholder="Scan barcode & Enter..." autofocus style="font-family: 'JetBrains Mono', monospace; font-size: 15px; font-weight: 700;">
                    <button type="button" class="btn btn-primary" id="btnScanOpname" style="padding: 0 18px;">Scan</button>
                </div>
            </div>

            <div style="background: var(--bg-muted); border-radius: 8px; padding: 12px; border: 1px dashed var(--border); font-size: 12px; color: var(--text-muted); line-height: 1.5;">
                <strong>Petunjuk:</strong> Arahkan scanner laser ke label kemasan. Barcode yang discan akan otomatis diverifikasi, row tabel ditandai hijau, dan counter bertambah. Jika barcode ternyata sudah dimutasi ke mitra, sistem akan langsung memberi info nama mitra penerimanya.
            </div>
        </div>
    </div>

    <!-- Kolom 2 & 3: Daftar Seluruh Barang Belum Dimutasi ke Mitra (Gudang Utama) -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <h2 class="card-title">
                    <i data-lucide="clipboard-check" style="width: 18px; height: 18px; color: var(--ok-fg);"></i>
                    <span>Daftar Fisik Gudang Utama</span>
                </h2>
                <div class="card-description">Seluruh kemasan berstatus aktif yang masih tersimpan di gudang utama transit</div>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <?php $eksporTabel = 'tableOpname'; $eksporNama = 'Laporan_Hasil_Stok_Opname_Gudang'; include __DIR__ . '/layout/ekspor.php'; ?>
            </div>
        </div>

        <!-- Filter Bar Interaktif: Bulan, Pemasok, No Surat Jalan, & Pencarian -->
        <div style="padding: 14px 20px; background: var(--bg-muted); border-bottom: 1px solid var(--border);">
            <form method="GET" action="index.php" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
                <input type="hidden" name="page" value="stok_opname">

                <!-- Filter Bulan / Periode Cek Bulanan -->
                <div style="flex: 1.1; min-width: 160px;">
                    <label class="form-label" style="font-size: 11.5px; font-weight: 700; margin-bottom: 4px; display: block; color: var(--text-main);">
                        Cek Perbulan:
                    </label>
                    <input type="month" name="filter_bulan" class="form-control" 
                           value="<?= htmlspecialchars($filterBulan) ?>" 
                           style="font-size: 12.5px; height: 38px;">
                </div>

                <!-- Filter Pemasok -->
                <div style="flex: 1.2; min-width: 170px;">
                    <label class="form-label" style="font-size: 11.5px; font-weight: 700; margin-bottom: 4px; display: block; color: var(--text-main);">
                        Pemasok Asal:
                    </label>
                    <select name="filter_pemasok" class="form-control" style="font-size: 12.5px; height: 38px;">
                        <option value="">-- Semua Pemasok --</option>
                        <?php foreach ($pemasokList as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $filterPemasok === (int)$p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['kode_pemasok']) ?> - <?= htmlspecialchars($p['nama_pemasok']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter No. Surat Jalan -->
                <div style="flex: 1.2; min-width: 180px;">
                    <label class="form-label" style="font-size: 11.5px; font-weight: 700; margin-bottom: 4px; display: block; color: var(--text-main);">
                        No. Surat Jalan:
                    </label>
                    <input type="text" name="filter_sj" list="mainSuratJalanList" class="form-control" 
                           placeholder="Ketik / pilih no surat jalan..." 
                           value="<?= htmlspecialchars($filterSuratJalan) ?>" 
                           style="font-size: 12.5px; height: 38px; font-family: 'JetBrains Mono', monospace;">
                    <datalist id="mainSuratJalanList">
                        <?php foreach ($activeSjList as $sj): ?>
                            <option value="<?= htmlspecialchars($sj) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <!-- Input Pencarian Bebas -->
                <div style="flex: 1; min-width: 140px;">
                    <label class="form-label" style="font-size: 11.5px; font-weight: 700; margin-bottom: 4px; display: block; color: var(--text-main);">
                        Cari Bebas:
                    </label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Barcode / produk..." 
                           value="<?= htmlspecialchars($search) ?>" 
                           style="font-size: 12.5px; height: 38px;">
                </div>

                <!-- Tombol Aksi Filter & Reset -->
                <div style="display: flex; gap: 6px;">
                    <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 16px; font-weight: 700;">
                        <i data-lucide="filter" style="width: 15px; height: 15px;"></i>
                        <span>Filter</span>
                    </button>
                    <?php if ($filterPemasok || $filterSuratJalan || $search || ($filterBulan !== date('Y-m'))): ?>
                        <a href="index.php?page=stok_opname" class="btn btn-outline" style="height: 38px; padding: 0 12px; color: var(--danger); border-color: rgba(239, 68, 68, 0.3); font-weight: 600;" title="Kembali ke Default Bulan Ini">
                            <i data-lucide="x" style="width: 15px; height: 15px;"></i>
                            <span>Reset</span>
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Status Ringkasan Filter yang Sedang Aktif -->
            <?php if ($filterPemasok || $filterSuratJalan || $search || $filterBulan): ?>
                <div style="display: flex; gap: 8px; align-items: center; margin-top: 10px; font-size: 12px; flex-wrap: wrap;">
                    <span style="font-weight: 700; color: var(--text-muted);">Filter Terpasang:</span>
                    <?php if (!empty($namaBulanIndo)): ?>
                        <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: var(--ok-fg); font-weight: 800; font-size: 11.5px;">Bulan: <?= htmlspecialchars($namaBulanIndo) ?></span>
                    <?php endif; ?>
                    <?php if ($filterPemasok): 
                        $pObj = array_values(array_filter($pemasokList, fn($p) => (int)$p['id'] === $filterPemasok))[0] ?? null;
                    ?>
                        <span class="badge badge-purple" style="font-size: 11.5px;">Pemasok: <?= htmlspecialchars($pObj['kode_pemasok'] ?? $filterPemasok) ?></span>
                    <?php endif; ?>
                    <?php if ($filterSuratJalan): ?>
                        <span class="badge badge-info" style="font-size: 11.5px;">Surat Jalan: <?= htmlspecialchars($filterSuratJalan) ?></span>
                    <?php endif; ?>
                    <?php if ($search): ?>
                        <span class="badge badge-secondary" style="font-size: 11.5px;">Cari: "<?= htmlspecialchars($search) ?>"</span>
                    <?php endif; ?>
                    <span style="color: var(--text-muted); font-size: 11.5px; margin-left: auto;">(Total Fisik: <strong><?= number_format($totalFisikCount, 0, ',', '.') ?></strong> kemasan / <strong><?= Helper::formatKg($totalFisikKg) ?></strong>)</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="table-responsive" style="max-height: 540px; overflow-y: auto;">
            <table class="table-custom" id="tableOpname">
                <thead>
                    <tr>
                        <th style="width: 35px; text-align: center;">No</th>
                        <th>ID Barcode</th>
                        <th>Tgl Masuk</th>
                        <th>Lokasi Fisik</th>
                        <th>Pemasok Asal</th>
                        <th>Nama Produk / Item</th>
                        <th style="text-align: right;">Netto (Kg)</th>
                        <th>No. Surat Jalan</th>
                        <th style="text-align: center;">Status Verifikasi Fisik</th>
                    </tr>
                </thead>
                <tbody id="opnameTableBody">
                    <?php if (empty($activeGoods)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 40px;">
                                <div style="font-size: 15px; font-weight: 700; margin-bottom: 6px;">Tidak ada persediaan fisik di gudang utama</div>
                                <div style="font-size: 12.5px;">Semua barang pada filter ini mungkin telah dialokasikan / dimutasi ke mitra bisnis atau belum ada data masuk.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = 1;
                        foreach ($activeGoods as $g): 
                            $barcode = htmlspecialchars($g['id_label']);
                            $berat = (float)$g['berat_netto'];
                            $sj = !empty($g['no_surat_jalan']) ? $g['no_surat_jalan'] : ($g['batch_no'] ?? '-');
                            $tglMasuk = !empty($g['created_at']) ? date('d/m/Y', strtotime($g['created_at'])) : (!empty($g['tgl_produksi']) ? date('d/m/Y', strtotime($g['tgl_produksi'])) : '-');
                            
                            // Cek apakah barang sudah dimutasi ke mitra
                            $isMutasi = !empty($g['mitra_id']) && (int)$g['mitra_id'] > 0;
                            $namaMitraTujuan = $g['nama_mitra_tujuan'] ?? ($g['nama_mitra'] ?? ('Mitra ' . ($g['kode_mitra'] ?? '')));
                            $kodeMitraTujuan = $g['kode_mitra_tujuan'] ?? ($g['kode_mitra'] ?? '');
                            $noBast = $g['no_bast'] ?? ($g['no_trx_mutasi'] ?? '');
                            $tglMutasi = !empty($g['waktu_mutasi']) ? date('d/m/Y H:i', strtotime($g['waktu_mutasi'])) : '';

                            // Status opname langsung dari DB
                            $isOpnameVerified = !empty($g['last_opname_at']);
                            $tglOpname = $isOpnameVerified ? date('d/m/Y H:i', strtotime($g['last_opname_at'])) : '';
                        ?>
                        <tr id="row-<?= $barcode ?>" data-barcode="<?= $barcode ?>" data-mutasi="<?= $isMutasi ? '1' : '0' ?>" data-verified="<?= $isOpnameVerified ? '1' : '0' ?>" class="opname-item-row" style="<?= $isMutasi ? 'background: rgba(241, 245, 249, 0.6);' : ($isOpnameVerified ? 'background: rgba(16, 185, 129, 0.08);' : '') ?>">
                            <td style="text-align: center; color: var(--text-muted); font-size: 11.5px;"><?= $no++ ?></td>
                            <td>
                                <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--primary);">
                                    <?= $barcode ?>
                                </strong>
                            </td>
                            <td style="font-size: 12px; color: var(--text-muted); white-space: nowrap;">
                                <?= $tglMasuk ?>
                            </td>
                            <td>
                                <?php if ($isMutasi): ?>
                                    <div style="display: flex; flex-direction: column; gap: 3px;">
                                        <span class="badge" style="background: rgba(3, 105, 161, 0.15); color: var(--aktif-fg); font-weight: 800; font-size: 11px; display: inline-flex; align-items: center; gap: 4px; width: fit-content;">
                                            <i data-lucide="arrow-right" style="width: 12px; height: 12px;"></i>
                                            Mutasi ke: <?= htmlspecialchars($namaMitraTujuan) ?>
                                        </span>
                                        <?php if ($noBast): ?>
                                            <span style="font-size: 10.5px; color: var(--text-muted);">
                                                BAST: <code><?= htmlspecialchars($noBast) ?></code> <?= $tglMutasi ? "({$tglMutasi})" : '' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(2, 132, 199, 0.12); color: var(--aktif-fg); font-weight: 700; font-size: 11px;">
                                        Gudang Utama
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-purple" style="font-size: 11px; padding: 2px 7px;">
                                    <?= htmlspecialchars($g['nama_pemasok'] ?? ($g['kode_pemasok'] ?? '-')) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($g['nama_item']) ?></strong>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($g['nama_jenis']) ?></div>
                            </td>
                            <td style="text-align: right; font-weight: 800; font-size: 13.5px; color: var(--ok-fg);">
                                <?= Helper::formatKg($berat) ?>
                            </td>
                            <td><code style="font-size: 11px;"><?= htmlspecialchars($sj) ?></code></td>
                            <td id="status-<?= $barcode ?>" style="text-align: center;">
                                <?php if ($isMutasi): ?>
                                    <span class="badge" style="background: rgba(148, 163, 184, 0.2); color: var(--text-muted); font-weight: 700; font-size: 11px;" title="Barang ini sudah dipindahkan ke gudang mitra sehingga tidak lagi berada di fisik gudang utama">
                                        Sudah di <?= htmlspecialchars($namaMitraTujuan) ?>
                                    </span>
                                <?php elseif ($isOpnameVerified): ?>
                                    <span class="badge badge-success" style="background: var(--ok-solid); color: #fff; font-weight: 800;">
                                        COCOK FISIK
                                    </span>
                                    <div style="font-size: 10px; color: var(--ok-fg); margin-top: 3px; font-weight: 600;">
                                        <?= $tglOpname ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge badge-warning" style="background: rgba(245, 158, 11, 0.15); color: var(--warn-fg); font-weight: 700;">
                                        Menunggu Scan Barcode
                                    </span>
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

<script>
    const opnameInput = document.getElementById('opname_scan_input');
    const alertBox = document.getElementById('opnameAlertBox');
    const totalItems = <?= $totalFisikCount ?>;
    const totalItemsGudang = <?= (int)$countGudangUtama ?>;

    function showOpnameAlert(msg, isSuccess = true) {
        alertBox.style.display = 'block';
        if (isSuccess) {
            alertBox.className = 'alert alert-success';
            alertBox.innerHTML = `✓ ${msg}`;
            if (window.scannerAudio) window.scannerAudio.playSuccessBeep();
        } else {
            alertBox.className = 'alert alert-danger';
            alertBox.innerHTML = `⚠️ ${msg}`;
            if (window.scannerAudio) window.scannerAudio.playErrorBuzz();
        }
    }

    function updateOpnameCounters() {
        let matchedInView = 0;
        document.querySelectorAll('.opname-item-row').forEach(row => {
            if (row.dataset.mutasi !== '1' && row.dataset.verified === '1') {
                matchedInView++;
            }
        });

        const target = totalItemsGudang > 0 ? totalItemsGudang : totalItems;
        const pct = target > 0 ? Math.min(100, Math.round((matchedInView / target) * 100)) : 0;

        const elCount = document.getElementById('statMatchedCount');
        const elPct = document.getElementById('statMatchedPct');
        if (elCount) {
            elCount.innerHTML = `${matchedInView}<span class="kpi-unit">Kemasan</span>`;
        }
        if (elPct) {
            elPct.innerText = `${pct}% terverifikasi (${matchedInView}/${target})`;
        }
    }

    async function verifyOpnameRow(barcode) {
        barcode = barcode.trim();
        if (!barcode) return;

        try {
            const formData = new URLSearchParams();
            formData.append('barcode', barcode);

            const res = await fetch('index.php?action=opname_verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });

            const data = await res.json();

            if (data.success) {
                const actualId = data.id_label || data.barcode || barcode;
                let row = document.getElementById('row-' + actualId) || document.getElementById('row-' + barcode);
                if (!row) {
                    row = document.querySelector(`tr[data-barcode="${actualId}"]`) ||
                          document.querySelector(`tr[data-barcode$="${barcode}"]`) ||
                          document.querySelector(`tr[id$="${barcode}"]`);
                }
                let statusCell = document.getElementById('status-' + actualId) || document.getElementById('status-' + barcode);
                if (!statusCell && row) {
                    statusCell = row.querySelector('[id^="status-"]') || row.querySelector('td:last-child');
                }

                if (row && statusCell) {
                    row.dataset.verified = '1';
                    row.style.background = 'rgba(16, 185, 129, 0.08)';
                    row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                    statusCell.innerHTML = `
                        <span class="badge badge-success" style="background: var(--ok-solid); color: #fff; font-weight: 800;">
                            ✓ COCOK FISIK
                        </span>
                        <div style="font-size: 10px; color: var(--ok-fg); margin-top: 3px; font-weight: 600;">
                            ${data.formatted_time || 'Baru saja'}
                        </div>
                    `;
                }

                updateOpnameCounters();
                showOpnameAlert(data.message, true);
            } else {
                showOpnameAlert(data.message || 'Gagal memverifikasi barcode', false);
            }
        } catch (err) {
            console.error('Error saat verifikasi opname:', err);
            showOpnameAlert('Gagal terhubung ke server saat memverifikasi barcode ke database.', false);
        }
    }

    opnameInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            verifyOpnameRow(opnameInput.value);
            opnameInput.value = '';
        }
    });

    document.getElementById('btnScanOpname').addEventListener('click', () => {
        verifyOpnameRow(opnameInput.value);
        opnameInput.value = '';
    });
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
