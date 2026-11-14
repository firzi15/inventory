<?php
/**
 * Stasiun Pemindahan Stok (Mutasi) & Log Alur Siklus Hidup Barang
 * Alur Pergerakan: Pemasok Mitra Bisnis Pengeluaran (Outbound)
 * Sepenuhnya mengacu pada 7 Mitra Bisnis (Master Lokasi Gudang Telah Dihapus)
 */

use App\Core\Helper;
use App\Models\Mitra;
use App\Models\Pemasok;
use App\Models\Item;
use App\Models\Label;
use App\Core\Database;

$pageTitle = 'Pemindahan Stok & Log Alur Barang - Barcode WES Multi-Mitra';

// Simulasi user login saat ini (default: session atau default Operator Inbound)
$currentUser = $_SESSION['user_login'] ?? [
    'id' => 3,
    'username' => 'opr_timbang',
    'nama_lengkap' => 'Arif Operator Penerimaan',
    'role' => 'OPERATOR_INBOUND'
];

$mitraList = Mitra::getAll();
$pemasokList = Pemasok::getAll();
$activeSampleLabels = Label::getRecent(10);

// Parameter Filter untuk Log Alur Siklus Hidup Barang (Lifecycle)
$filterPemasok = isset($_GET['filter_pemasok']) && $_GET['filter_pemasok'] !== '' ? (int)$_GET['filter_pemasok'] : null;
$filterMitra = isset($_GET['filter_mitra']) && $_GET['filter_mitra'] !== '' ? (int)$_GET['filter_mitra'] : null;
$filterStatus = trim($_GET['filter_status'] ?? '');
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$lifecycleFilters = [
    'pemasok_id' => $filterPemasok,
    'mitra_id' => $filterMitra,
    'status' => $filterStatus,
    'search' => $search
];

$lifecycleLogs = Label::getLifecycleLogs($lifecycleFilters, $perPage, $offset);
$totalLifecycle = Label::countLifecycleLogs($lifecycleFilters);
$totalPages = max(1, ceil($totalLifecycle / $perPage));

// Ambil riwayat mutasi / pergerakan BAST terakhir
$db = Database::getConnection();
$recentMutasi = $db->query("
    SELECT t.*, 
           m.kode_mitra, m.nama_mitra, m.warna_identitas,
           ma.kode_mitra AS kode_mitra_asal, ma.nama_mitra AS nama_mitra_asal,
           mt.kode_mitra AS kode_mitra_tujuan, mt.nama_mitra AS nama_mitra_tujuan,
           p.nama_pemasok,
           u.nama_lengkap AS operator_input
    FROM transaksi_stok t
    LEFT JOIN mitra m ON t.mitra_id = m.id
    LEFT JOIN mitra ma ON t.mitra_asal_id = ma.id
    LEFT JOIN mitra mt ON t.mitra_tujuan_id = mt.id
    LEFT JOIN pemasok p ON t.pemasok_id = p.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.alur_pergerakan IS NOT NULL AND t.alur_pergerakan != ''
    ORDER BY t.id DESC
    LIMIT 20
")->fetchAll();

require_once __DIR__ . '/layout/header.php';
?>

<!-- PAGE HEADER -->
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Mutasi Barang (Pemindahan Stok)</h1>
        <p class="page-subtitle">Pemindahan stok fisik antar mitra atau alokasi penerimaan dengan Berita Acara Serah Terima (BAST)</p>
    </div>
</div>

<div id="mutasiAlertBox" style="display: none; margin-bottom: 20px;"></div>

<!-- ========================================================================= -->
<!-- GRID FORMULIR PEMINDAHAN STOK & KERANJANG SCAN                             -->
<!-- ========================================================================= -->
<div class="grid grid-cols-3" style="align-items: start; margin-bottom: 30px;">
    <!-- Kolom 1: Formulir Pemindahan Stok -->
    <div class="card no-print">
        <div class="card-header">
            <div>
                <h2 class="card-title">
                    <i data-lucide="route" style="width: 18px; height: 18px; color: var(--primary);"></i>
                    <span>Formulir Pemindahan Stok</span>
                </h2>
                <div class="card-description">Pilih jenis alur pemindahan stok dan pihak serah terima</div>
            </div>
        </div>

        <form id="formMutasi">
            <!-- 1. Pilihan Alur Pemindahan -->
            <div class="form-group">
                <label class="form-label" for="alur_pergerakan">Jenis Alur Pemindahan <span style="color:red;">*</span></label>
                <select name="alur_pergerakan" id="alur_pergerakan" class="form-control" required style="font-weight: 700; font-size: 13px;">
                    <option value="PEMASOK_KE_MITRA" selected>Alokasi Stok: Pemasok Mitra Bisnis</option>
                    <option value="MUTASI_ANTAR_MITRA">Pemindahan Antar-Mitra (Transfer Stok)</option>
                    <option value="KE_PEMBELI_POS">Pengeluaran: Mitra Pembeli / POS</option>
                </select>
            </div>

            <!-- 2. Blok Pemasok Asal & Mitra Tujuan (Dinamis) -->
            <div id="boxPemasokKeMitra" style="background: var(--bg-muted); padding: 12px; border-radius: var(--radius); border: 1.5px solid var(--border); margin-bottom: 14px;">
                <div class="form-group" style="margin-bottom: 10px;">
                    <label class="form-label" for="mutasi_pemasok_id" style="font-size: 12px;">Pemasok Asal (Nelayan / Supplier) <span style="color:red;">*</span></label>
                    <select name="pemasok_id" id="mutasi_pemasok_id" class="form-control" style="font-weight: 700;">
                        <?php foreach ($pemasokList as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['kode_pemasok']) ?> - <?= htmlspecialchars($p['nama_pemasok']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label class="form-label" for="mutasi_mitra_tujuan_id" style="font-size: 12px;">Mitra Penerima / Tujuan <span style="color:red;">*</span></label>
                    <select name="mitra_tujuan_id" id="mutasi_mitra_tujuan_id" class="form-control" style="font-weight: 700;">
                        <?php foreach ($mitraList as $m): ?>
                            <option value="<?= $m['id'] ?>">
                                <?= htmlspecialchars($m['kode_mitra']) ?> - <?= htmlspecialchars($m['nama_mitra']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Blok Mutasi Antar Mitra (Hidden by default, shown if MUTASI_ANTAR_MITRA) -->
            <div id="boxAntarMitra" style="display: none; background: var(--bg-muted); padding: 12px; border-radius: var(--radius); border: 1.5px solid var(--border); margin-bottom: 14px;">
                <div class="form-group" style="margin-bottom: 10px;">
                    <label class="form-label" for="mutasi_mitra_asal_id" style="font-size: 12px;">Mitra Pemilik Asal <span style="color:red;">*</span></label>
                    <select name="mitra_asal_id" id="mutasi_mitra_asal_id" class="form-control" style="font-weight: 700;">
                        <?php foreach ($mitraList as $m): ?>
                            <option value="<?= $m['id'] ?>">
                                <?= htmlspecialchars($m['kode_mitra']) ?> - <?= htmlspecialchars($m['nama_mitra']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label class="form-label" for="mutasi_mitra_tujuan_id_2" style="font-size: 12px;">Mitra Tujuan Transfer <span style="color:red;">*</span></label>
                    <select id="mutasi_mitra_tujuan_id_2" class="form-control" style="font-weight: 700;">
                        <?php foreach ($mitraList as $idx => $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $idx === 1 ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['kode_mitra']) ?> - <?= htmlspecialchars($m['nama_mitra']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- 3. Form Pihak Serah Terima (BAST) -->
            <div style="background: var(--bg-muted); padding: 14px; border-radius: var(--radius); border: 1.5px solid var(--border); margin-bottom: 14px;">
                <div style="font-size: 11px; font-weight: 800; color: var(--primary); text-transform: uppercase; margin-bottom: 8px;">
                    Pihak Serah Terima (Berita Acara)
                </div>

                <div class="form-group" style="margin-bottom: 10px;">
                    <label class="form-label" for="nama_penyerah" style="font-size: 12px;">Diserahkan Oleh (Pihak Penyerah) <span style="color:red;">*</span></label>
                    <input type="text" name="nama_penyerah" id="nama_penyerah" class="form-control" value="Supriyadi (Perwakilan Pemasok / Driver)" required style="font-size: 13px;">
                </div>

                <div class="form-group" style="margin-bottom: 10px;">
                    <label class="form-label" for="nama_penerima" style="font-size: 12px;">Diterima Oleh (Pihak Penerima) <span style="color:red;">*</span></label>
                    <input type="text" name="nama_penerima" id="nama_penerima" class="form-control" value="<?= htmlspecialchars($currentUser['nama_lengkap']) ?>" required style="font-size: 13px; font-weight: 700; color: var(--ok-fg);">
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">
                        Terikat ke akun operator: <strong><?= htmlspecialchars($currentUser['nama_lengkap']) ?></strong>
                    </div>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label class="form-label" for="catatan_serah_terima" style="font-size: 12px;">Catatan Serah Terima / Kondisi Kemasan</label>
                    <input type="text" name="catatan_serah_terima" id="catatan_serah_terima" class="form-control" value="Kemasan utuh tersegel rapi, suhu cold storage terjaga." style="font-size: 12px;">
                </div>
            </div>

            <!-- 4. No Surat Jalan / Referensi -->
            <div class="form-group">
                <label class="form-label" for="no_referensi">No. Surat Jalan / No. Referensi BAST</label>
                <input type="text" name="no_referensi" id="no_referensi" class="form-control" value="SJ-MUT-<?= date('Ymd') ?>-001" style="font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 700;">
            </div>

            <hr style="border: none; border-top: 1px solid var(--border); margin: 18px 0;">

            <!-- 5. Input Scan Barcode Kemasan -->
            <div class="form-group">
                <label class="form-label" for="inputBarcodeScanMutasi">
                    Input Scan Barcode yang Dipindahkan
                </label>
                <div style="display: flex; gap: 6px;">
                    <input type="text" id="inputBarcodeScanMutasi" class="form-control" placeholder="Scan barcode & Enter..." autofocus style="font-family: 'JetBrains Mono', monospace; font-weight: 700;">
                    <button type="button" class="btn btn-primary" id="btnScanMutasi">Scan</button>
                </div>
            </div>

            <!-- Shortcut Simulasi Barcode -->
            <div>
                <label class="form-label" style="font-size: 11px; color: var(--text-muted);">Simulasi Scan Barcode Aktif (Klik untuk Uji):</label>
                <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                    <?php foreach ($activeSampleLabels as $idx => $sample): ?>
                        <?php if ($idx < 5): ?>
                        <button type="button" class="btn btn-outline btn-sm" onclick="triggerScanMutasi('<?= htmlspecialchars($sample['id_label']) ?>')" style="font-family: 'JetBrains Mono', monospace; font-size: 10.5px; padding: 2px 7px;">
                            <?= htmlspecialchars($sample['id_label']) ?>
                        </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Kolom 2 & 3: Keranjang Kemasan yang Dimutasi -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 class="card-title">
                    <i data-lucide="file-check" style="width: 18px; height: 18px; color: var(--purple);"></i>
                    <span>Kemasan Masuk Dokumen BAST (<span id="mutasiCartCount" style="color: var(--primary);">0</span> pcs)</span>
                </h2>
                <div class="card-description">Daftar kemasan fisik yang masuk dalam berita acara serah terima</div>
            </div>
            <div style="display: flex; gap: 10px; align-items: baseline;">
                <span style="font-size: 13.5px; color: var(--text-muted); font-weight: 600;">Total Bobot Mutasi:</span>
                <span id="mutasiTotalKg" style="font-size: 24px; font-weight: 900; color: var(--ok-fg); font-family: 'JetBrains Mono', monospace;">0.00 kg</span>
            </div>
        </div>

        <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th style="width: 40px;">No</th>
                        <th>ID Barcode</th>
                        <th>Nama Barang / Produk</th>
                        <th>Pemasok Asal</th>
                        <th>Mitra Saat Ini</th>
                        <th style="text-align: right;">Netto (Kg)</th>
                        <th style="text-align: center;">Hapus</th>
                    </tr>
                </thead>
                <tbody id="mutasiCartTableBody">
                    <tr id="emptyMutasiRow">
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 40px;">
                            Belum ada kemasan yang discan untuk sesi pemindahan stok ini.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--border);">
            <button type="button" class="btn btn-outline" id="btnClearMutasiCart" style="color: var(--danger);">
                <i data-lucide="trash-2" style="width: 15px; height: 15px;"></i>
                <span>Kosongkan Sesi</span>
            </button>
            <button type="button" class="btn btn-primary btn-lg" id="btnSubmitMutasi">
                <i data-lucide="check-circle-2" style="width: 18px; height: 18px;"></i>
                <span>Selesaikan Pemindahan & Terbitkan BAST</span>
            </button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- SECTION DUA TAB: 1. LOG ALUR PERGERAKAN STOK  |  2. RIWAYAT BAST          -->
<!-- ========================================================================= -->
<details class="card no-print" style="margin-top: 25px; border: 1.5px solid var(--border);" <?= (!empty($search) || !empty($filterStatus) || $filterMitra || $filterPemasok) ? 'open' : '' ?>>
    <summary style="padding: 16px 20px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: space-between; user-select: none; background: var(--bg-card); list-style: none;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <i data-lucide="history" style="width: 20px; height: 20px; color: var(--primary);"></i>
            <div>
                <span style="font-size: 15px; font-weight: 800; color: var(--text-main);">Riwayat Log Alur & Dokumen BAST Mutasi</span>
                <span class="badge badge-purple" style="margin-left: 8px;"><?= number_format($totalLifecycle, 0, ',', '.') ?> Total Kemasan</span>
            </div>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <span style="font-size: 12px; color: var(--text-muted);">Log & BAST</span>
            <span class="collapsible-indicator" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; font-size: 12px; font-weight: 700; border-radius: 20px; border: 1px solid var(--border); background: var(--bg-surface, #ffffff); color: var(--text-main); pointer-events: none; transition: all 0.2s;">
                <span class="indicator-text"></span>
                <i data-lucide="chevron-down" class="indicator-chevron" style="width: 15px; height: 15px; transition: transform 0.25s ease;"></i>
            </span>
        </div>
    </summary>

    <!-- Tab Navigasi -->
    <div style="display: flex; gap: 8px; border-bottom: 2px solid var(--border); border-top: 1px solid var(--border); padding: 0 20px; background: var(--bg-muted);">
        <button type="button" id="tabBtnLifecycle" class="btn" onclick="switchMainTab('lifecycle')" style="border-radius: 8px 8px 0 0; border-bottom: 3px solid var(--primary); background: var(--bg-card); font-weight: 800; color: var(--primary); padding: 12px 18px;">
            <i data-lucide="activity" style="width: 16px; height: 16px; margin-right: 6px;"></i>
            <span>Log Alur Siklus Hidup Barang (Lifecycle Barcode Tracker)</span>
        </button>
        <button type="button" id="tabBtnBast" class="btn" onclick="switchMainTab('bast')" style="border-radius: 8px 8px 0 0; border-bottom: 3px solid transparent; background: transparent; font-weight: 600; color: var(--text-muted); padding: 12px 18px;">
            <i data-lucide="file-text" style="width: 16px; height: 16px; margin-right: 6px;"></i>
            <span>Riwayat Dokumen BAST & Transaksi Mutasi (<?= count($recentMutasi) ?>)</span>
        </button>
    </div>

    <!-- TAB 1: LOG ALUR PERGERAKAN SIKLUS HIDUP BARANG -->
    <div id="sectionLifecycle" style="display: block;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <h2 class="card-title">
                    <i data-lucide="history" style="width: 18px; height: 18px; color: var(--primary);"></i>
                    <span>Log Perjalanan Kemasan Barcode (Pemasok Mitra Pengeluaran)</span>
                </h2>
                <div class="card-description">
                    Pelacakan riwayat dan status perjalanan setiap kemasan dari pertama diterima sampai dengan keluar gudang
                </div>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <?php $eksporTabel = 'tableLifecycleLogs'; $eksporNama = 'Log_Alur_Siklus_Stok'; include __DIR__ . '/layout/ekspor.php'; ?>
                <span class="badge badge-purple" style="font-weight: 700;"><?= number_format($totalLifecycle, 0, ',', '.') ?> Total Kemasan</span>
            </div>
        </div>

        <!-- Quick Filter Pills & Filter Lanjutan Button Bar -->
        <?php
        $activeMutasiFilterCount = 0;
        if (!empty($filterPemasok)) $activeMutasiFilterCount++;
        if (!empty($filterMitra)) $activeMutasiFilterCount++;
        if (!empty($filterStatus)) $activeMutasiFilterCount++;
        if (!empty($search)) $activeMutasiFilterCount++;
        $hasAnyMutasiFilter = $activeMutasiFilterCount > 0;
        ?>
        <div style="padding: 14px 20px; background: var(--bg-card); border-bottom: 1px solid var(--border); display: flex; justify-content: flex-end; align-items: center; flex-wrap: wrap; gap: 12px;">

            <!-- Sisi Kanan: Tombol Buka Jumbotron Filter & Reset -->
            <div style="display: flex; gap: 8px; align-items: center;">
                <button type="button" class="btn <?= $hasAnyMutasiFilter ? 'btn-primary' : 'btn-outline' ?>" onclick="toggleMutasiFilterPanel()" id="btnToggleFilterMutasi" style="font-weight: 700; font-size: 12.5px; padding: 7px 16px; border-radius: 20px; display: inline-flex; align-items: center; gap: 7px; box-shadow: var(--shadow-sm);">
                    <i data-lucide="sliders-horizontal" style="width: 15px; height: 15px;"></i>
                    <span>Filter Lanjutan</span>
                    <?php if ($activeMutasiFilterCount > 0): ?>
                        <span class="badge" style="background: var(--bg-card); color: var(--primary); font-size: 11px; font-weight: 900; padding: 1px 7px; border-radius: 10px;"><?= $activeMutasiFilterCount ?></span>
                    <?php endif; ?>
                </button>

                <?php if ($hasAnyMutasiFilter): ?>
                    <a href="index.php?page=mutasi" class="btn btn-outline" style="font-size: 12px; padding: 7px 14px; border-radius: 20px; display: inline-flex; align-items: center; gap: 5px;" title="Reset Semua Filter">
                        <i data-lucide="rotate-ccw" style="width: 13px; height: 13px;"></i>
                        <span>Reset</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- JUMBOTRON PANEL FILTER MUTASI LENGKAP & DETAIL (COLLAPSIBLE) -->
        <div id="panelJumbotronFilterMutasi" class="no-print" style="display: none; padding: 20px 24px; background: var(--bg-card); border-bottom: 1.5px solid var(--border);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1.5px solid var(--border);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="background: var(--primary); color: white; width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 8px rgba(2, 132, 199, 0.3);">
                        <i data-lucide="sliders" style="width: 18px; height: 18px;"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0; font-size: 14.5px; font-weight: 800; color: var(--text-main);">Panel Pengaturan Filter Siklus Alur Kemasan</h4>
                        <div style="font-size: 11.5px; color: var(--text-muted);">Saring riwayat perjalanan barcode berdasarkan pemasok, mitra pemegang, dan status alur</div>
                    </div>
                </div>
                <button type="button" onclick="toggleMutasiFilterPanel(false)" class="btn btn-outline btn-sm" style="padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 8px;">
                    Tutup Panel
                </button>
            </div>

            <form method="GET" action="index.php" id="formFilterMutasi">
                <input type="hidden" name="page" value="mutasi">

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 14px;">
                    <!-- Filter Pemasok -->
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px; font-weight: 700;">Pemasok Asal:</label>
                        <select name="filter_pemasok" class="form-control" style="font-size: 12.5px; font-weight: 600; height: 38px;">
                            <option value="">-- Semua Pemasok --</option>
                            <?php foreach ($pemasokList as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $filterPemasok === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['kode_pemasok']) ?> - <?= htmlspecialchars($p['nama_pemasok']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filter Mitra -->
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px; font-weight: 700;">Mitra Pemilik Saat Ini:</label>
                        <select name="filter_mitra" class="form-control" style="font-size: 12.5px; font-weight: 600; height: 38px;">
                            <option value="">-- Semua Mitra --</option>
                            <?php foreach ($mitraList as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= $filterMitra === (int)$m['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['kode_mitra']) ?> - <?= htmlspecialchars($m['nama_mitra']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filter Status -->
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px; font-weight: 700;">Status Barang:</label>
                        <select name="filter_status" class="form-control" style="font-size: 12.5px; font-weight: 600; height: 38px;">
                            <option value="" <?= $filterStatus === '' ? 'selected' : '' ?>>-- Semua Status --</option>
                            <option value="AKTIF" <?= $filterStatus === 'AKTIF' ? 'selected' : '' ?>>● Di Gudang Mitra (Aktif)</option>
                            <option value="KELUAR" <?= $filterStatus === 'KELUAR' ? 'selected' : '' ?>>Selesai Keluar (Outbound)</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap;">
                    <!-- Search Input -->
                    <div class="form-group" style="margin: 0; flex: 1; min-width: 250px;">
                        <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px; font-weight: 700;">Pencarian Barcode / No. SJ / Nama Barang:</label>
                        <div style="position: relative;">
                            <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Ketik barcode / SJ / nama produk..." style="font-size: 12.5px; height: 38px; padding-left: 34px;">
                            <i data-lucide="search" style="position: absolute; left: 10px; top: 11px; width: 15px; height: 15px; color: var(--text-muted);"></i>
                        </div>
                    </div>

                    <div style="display: flex; gap: 8px; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 20px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(2, 132, 199, 0.3);">
                            <i data-lucide="filter" style="width: 14px; height: 14px;"></i>
                            <span>Terapkan Filter</span>
                        </button>
                        <?php if ($hasAnyMutasiFilter): ?>
                            <a href="index.php?page=mutasi" class="btn btn-outline" style="height: 38px; padding: 0 14px; display: inline-flex; align-items: center; gap: 6px;" title="Reset Semua Filter">
                                <i data-lucide="rotate-ccw" style="width: 14px; height: 14px;"></i>
                                <span>Reset</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tag Filter Aktif -->
                <?php if ($hasAnyMutasiFilter): ?>
                    <div style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--border); display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: 11.5px;">
                        <span style="color: var(--text-muted); font-weight: 700;">Filter Sedang Aktif:</span>
                        <?php if ($filterPemasok): ?>
                            <span class="badge badge-secondary" style="font-size: 11px; padding: 3px 8px;">Pemasok Terpilih</span>
                        <?php endif; ?>
                        <?php if ($filterMitra): ?>
                            <span class="badge badge-secondary" style="font-size: 11px; padding: 3px 8px;">Mitra Terpilih</span>
                        <?php endif; ?>
                        <?php if ($filterStatus): ?>
                            <span class="badge badge-secondary" style="font-size: 11px; padding: 3px 8px;">Status: <?= htmlspecialchars($filterStatus) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($search)): ?>
                            <span class="badge badge-secondary" style="font-size: 11px; padding: 3px 8px;">Pencarian: "<?= htmlspecialchars($search) ?>"</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Tabel Log Lifecycle -->
        <div class="table-responsive">
            <table class="table-custom" id="tableLifecycleLogs">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">No</th>
                        <th>ID Barcode Kemasan</th>
                        <th>Nama Barang / Produk</th>
                        <th>Pemasok Asal (Langkah 1)</th>
                        <th>No. SJ Penerimaan</th>
                        <th>Mitra Pemilik (Langkah 2)</th>
                        <th style="text-align: right;">Netto (Kg)</th>
                        <th>Tahap Alur Saat Ini</th>
                        <th style="text-align: center; width: 140px;">Timeline Alur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lifecycleLogs)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 36px;">
                                Belum ada data barcode atau tidak ada yang cocok dengan kriteria filter.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($lifecycleLogs as $log): 
                            $berat = (float)$log['berat_netto'];
                        ?>
                        <tr>
                            <td style="text-align: center; color: var(--text-muted); font-size: 12px;"><?= $no++ ?></td>
                            <td>
                                <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--primary);">
                                    <?= htmlspecialchars($log['id_label']) ?>
                                </strong>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($log['nama_item']) ?></strong>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($log['nama_jenis']) ?></div>
                            </td>
                            <td>
                                <span class="badge badge-info" style="font-size: 11px;">
                                    <?= htmlspecialchars($log['nama_pemasok'] ?? 'Pemasok Umum') ?>
                                </span>
                            </td>
                            <td><code style="font-size: 11px;"><?= htmlspecialchars($log['sj_pemasok'] ?: '-') ?></code></td>
                            <td>
                                <?php if (!empty($log['kode_mitra'])): ?>
                                    <span class="mitra-tag <?= strtolower($log['kode_mitra']) ?>" style="background: <?= htmlspecialchars($log['warna_identitas'] ?? '#0369a1') ?>; padding: 2px 7px; font-size: 11px;">
                                        <?= htmlspecialchars($log['kode_mitra']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 11px;">Belum Dialokasi</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; font-weight: 800; font-size: 13.5px; color: <?= $berat > 0 ? '#10b981' : '#f59e0b' ?>;">
                                <?= $berat > 0 ? Helper::formatKg($berat) : '<span style="font-size:11px;">0.00 kg</span>' ?>
                            </td>
                            <td>
                                <span class="badge <?= $log['tahap_badge'] ?>" style="font-size: 11.5px;">
                                    <?= htmlspecialchars($log['tahap_label']) ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="btn btn-outline btn-sm" onclick="showTimelineModal('<?= htmlspecialchars($log['id_label']) ?>')" style="padding: 3px 9px; font-size: 11px; color: var(--primary); border-color: var(--primary);">
                                    <i data-lucide="milestone" style="width: 13px; height: 13px; margin-right: 3px;"></i>
                                    <span>Lihat Alur</span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls (SELALU TAMPIL) -->
        <?php
        $baseMutasiUrl = "index.php?page=mutasi&filter_pemasok=" . urlencode((string)$filterPemasok) . "&filter_mitra=" . urlencode((string)$filterMitra) . "&filter_status=" . urlencode($filterStatus) . "&search=" . urlencode($search);
        ?>
        <div style="padding: 14px 20px; background: var(--bg-card); display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); flex-wrap: wrap; gap: 10px;">
            <div style="font-size: 12.5px; color: var(--text-muted);">
                Menampilkan halaman <strong><?= $page ?></strong> dari <strong><?= $totalPages ?></strong> (Total <?= number_format($totalLifecycle, 0, ',', '.') ?> barcode)
            </div>
            <div style="display: flex; gap: 5px; align-items: center;">
                <?php if ($page > 1): ?>
                    <a href="<?= $baseMutasiUrl ?>&p=1" class="btn btn-outline btn-sm" style="padding: 4px 10px;">« Pertama</a>
                    <a href="<?= $baseMutasiUrl ?>&p=<?= $page - 1 ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">‹ Sebelumnya</a>
                <?php else: ?>
                    <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">« Pertama</button>
                    <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">‹ Sebelumnya</button>
                <?php endif; ?>

                <?php 
                $startP = max(1, $page - 2);
                $endP = min($totalPages, $page + 2);
                for ($i = $startP; $i <= $endP; $i++): 
                ?>
                    <a href="<?= $baseMutasiUrl ?>&p=<?= $i ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-outline' ?> btn-sm" style="padding: 4px 10px; font-weight: <?= $i === $page ? '800' : '600' ?>;">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= $baseMutasiUrl ?>&p=<?= $page + 1 ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">Selanjutnya ›</a>
                    <a href="<?= $baseMutasiUrl ?>&p=<?= $totalPages ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">Terakhir »</a>
                <?php else: ?>
                    <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">Selanjutnya ›</button>
                    <button type="button" class="btn btn-outline btn-sm" disabled style="padding: 4px 10px; opacity: 0.5;">Terakhir »</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB 2: RIWAYAT DOKUMEN BAST -->
    <div id="sectionBast" style="display: none;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 class="card-title">
                    <i data-lucide="file-text" style="width: 18px; height: 18px; color: var(--warning);"></i>
                    <span>Riwayat Berita Acara Serah Terima (BAST)</span>
                </h2>
                <div class="card-description">Daftar dokumen fisik serah terima barang yang telah diterbitkan dan ditandatangani</div>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <?php $eksporTabel = 'tableRiwayatMutasi'; $eksporNama = 'Riwayat_BAST_Mutasi'; include __DIR__ . '/layout/ekspor.php'; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table-custom" id="tableRiwayatMutasi">
                <thead>
                    <tr>
                        <th>No. BAST</th>
                        <th>No. Transaksi</th>
                        <th>Alur Pemindahan</th>
                        <th>Pihak Penyerah (Asal)</th>
                        <th>Pihak Penerima (Tujuan)</th>
                        <th style="text-align: right;">Total Netto</th>
                        <th>Status Accurate</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentMutasi)): ?>
                        <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">Belum ada riwayat dokumen BAST mutasi.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentMutasi as $rm): 
                            $alurLabel = match($rm['alur_pergerakan']) {
                                'PEMASOK_KE_MITRA'   => 'Pemasok Mitra',
                                'MUTASI_ANTAR_MITRA' => 'Antar-Mitra',
                                'PENGEPUL_KE_GUDANG' => 'Pemasok Mitra',
                                'GUDANG_KE_OFFICE'   => 'Mitra Outlet POS',
                                'KE_PEMBELI_POS'     => 'Mitra Pembeli POS',
                                default              => 'Pemindahan Stok'
                            };
                            $alurBadge = match($rm['alur_pergerakan']) {
                                'PEMASOK_KE_MITRA'   => 'badge-info',
                                'MUTASI_ANTAR_MITRA' => 'badge-purple',
                                'PENGEPUL_KE_GUDANG' => 'badge-info',
                                'KE_PEMBELI_POS'     => 'badge-success',
                                default              => 'badge-secondary'
                            };
                            $asalName = $rm['nama_pemasok'] ?? ($rm['nama_mitra_asal'] ?? ($rm['nama_penyerah'] ?? 'Pihak Penyerah'));
                            $tujuanName = $rm['nama_mitra_tujuan'] ?? ($rm['nama_mitra'] ?? ($rm['nama_penerima'] ?? 'Mitra Penerima'));
                        ?>
                        <tr>
                            <td>
                                <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--primary);">
                                    <?= htmlspecialchars($rm['no_bast'] ?? '-') ?>
                                </strong>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= Helper::formatDateTime($rm['created_at']) ?></div>
                            </td>
                            <td><code style="font-size: 11.5px;"><?= htmlspecialchars($rm['no_transaksi']) ?></code></td>
                            <td><span class="badge <?= $alurBadge ?>" style="font-size: 11.5px;"><?= $alurLabel ?></span></td>
                            <td>
                                <strong><?= htmlspecialchars($asalName) ?></strong>
                                <div style="font-size: 11px; color: var(--text-muted);">Penyerah: <?= htmlspecialchars($rm['nama_penyerah'] ?? '-') ?></div>
                            </td>
                            <td>
                                <strong style="color: var(--ok-fg);"><?= htmlspecialchars($tujuanName) ?></strong>
                                <div style="font-size: 11px; color: var(--text-muted);">Penerima: <?= htmlspecialchars($rm['nama_penerima'] ?? '-') ?> (User: <?= htmlspecialchars($rm['operator_input'] ?? 'System') ?>)</div>
                            </td>
                            <td style="text-align: right; font-weight: 800; font-size: 14px; color: var(--ok-fg);"><?= Helper::formatKg($rm['total_kg']) ?></td>
                            <td><?= Helper::renderStatusBadge($rm['status_accurate']) ?></td>
                            <td style="text-align: center;">
                                <a href="index.php?page=bast_print&id=<?= $rm['id'] ?>" target="_blank" class="btn btn-outline btn-sm">
                                    <i data-lucide="printer" style="width: 14px; height: 14px;"></i>
                                    <span>Cetak BAST</span>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</details>

<!-- ========================================================================= -->
<!-- MODAL TIMELINE SIKLUS HIDUP BARCODE (POPUP INTERAKTIF)                    -->
<!-- ========================================================================= -->
<div id="modalTimeline" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-card); border-radius: 12px; width: 90%; max-width: 680px; max-height: 85vh; overflow-y: auto; box-shadow: var(--shadow-xl); border: 1.5px solid var(--border);">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--bg-muted);">
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="milestone" style="width: 18px; height: 18px; color: var(--primary);"></i>
                    <span>Timeline Alur Perjalanan Barcode</span>
                </h3>
                <div style="font-size: 12px; color: var(--text-muted);">ID Barcode: <strong id="modalBarcodeTitle" style="font-family: 'JetBrains Mono', monospace; color: var(--primary);">-</strong></div>
            </div>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeTimelineModal()" style="font-weight: 800;">✕</button>
        </div>

        <div id="timelineModalBody" style="padding: 20px;">
            <div style="text-align: center; color: var(--text-muted); padding: 30px;">
                Memuat riwayat alur barcode...
            </div>
        </div>

        <div style="padding: 12px 20px; border-top: 1px solid var(--border); text-align: right; background: var(--bg-muted);">
            <button type="button" class="btn btn-primary btn-sm" onclick="closeTimelineModal()">Tutup</button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL POPUP: BARCODE BELUM DITIMBANG (WAJIB DITIMBANG TERLEBIH DAHULU)     -->
<!-- ========================================================================= -->
<div id="modalBarcodeNotWeighed" style="display: none; position: fixed; inset: 0; z-index: 99999; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(6px); align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--bg-card); border-radius: 16px; max-width: 530px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45); border: 2px solid var(--warn-solid); overflow: hidden; animation: popIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
        <!-- Header Banner Amber / Peringatan -->
        <div style="background: var(--warn-solid); padding: 18px 22px; color: #ffffff; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="background: rgba(255, 255, 255, 0.25); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i data-lucide="scale" style="width: 25px; height: 25px; color: #ffffff;"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 17px; font-weight: 800; letter-spacing: 0.2px; color: #ffffff;">BARCODE BELUM DITIMBANG!</h3>
                    <p style="margin: 3px 0 0 0; font-size: 12px; opacity: 0.95; color: #ffffff;">Wajib ditimbang terlebih dahulu sebelum dipindahkan</p>
                </div>
            </div>
            <button type="button" onclick="closeBarcodeNotWeighedModal()" style="background: rgba(255, 255, 255, 0.2); border: none; color: #ffffff; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700;" title="Tutup Popup (Esc)">✕</button>
        </div>

        <!-- Body Modal Informasi -->
        <div style="padding: 22px;">
            <!-- Kotak Pesan Peringatan Proporsional -->
            <div style="background: rgba(245, 158, 11, 0.1); border: 1.5px solid rgba(245, 158, 11, 0.35); border-radius: 12px; padding: 13px 16px; margin-bottom: 16px;">
                <div style="font-size: 13px; color: var(--text-main); line-height: 1.55; font-weight: 600;" id="notWeighedModalWarningText">
                    Barcode keranjang ini <strong>belum memiliki berat riil timbangan (0.00 KG)</strong>. Semua barang masuk wajib ditimbang di <strong>Stasiun Penerimaan & Timbang</strong> agar bobot fisik tercatat sebelum dapat dipindahkan antar mitra.
                </div>
            </div>

            <!-- Rincian Data Barcode yang Belum Ditimbang -->
            <div style="background: var(--bg-muted); border: 1px solid var(--border); border-radius: 12px; padding: 14px 16px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px dashed var(--border); margin-bottom: 8px;">
                    <span style="color: var(--text-muted);">ID Barcode:</span>
                    <strong id="notWeighedBarcodeId" style="font-family: 'JetBrains Mono', monospace; font-size: 13.5px; color: var(--warn-fg);">-</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px dashed var(--border); margin-bottom: 8px;">
                    <span style="color: var(--text-muted);">Nama Komoditas:</span>
                    <strong id="notWeighedBarcodeItem" style="color: var(--text-main);">-</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px dashed var(--border); margin-bottom: 8px;">
                    <span style="color: var(--text-muted);">Pemasok Asal:</span>
                    <span id="notWeighedBarcodePemasok" style="font-weight: 600; color: var(--text-main);">-</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px dashed var(--border); margin-bottom: 8px;">
                    <span style="color: var(--text-muted);">No. Surat Jalan:</span>
                    <strong id="notWeighedBarcodeSJ" style="font-family: 'JetBrains Mono', monospace; color: var(--primary);">-</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 4px;">
                    <span style="color: var(--text-muted); font-weight: 700;">Status Timbangan:</span>
                    <span class="badge badge-warning" id="notWeighedBarcodeBadge" style="font-size: 13px; font-weight: 900; padding: 4px 10px; background: var(--warn-bg); color: var(--warn-fg); border: 1px solid var(--warn-solid);">
                        BELUM DITIMBANG (0.00 KG)
                    </span>
                </div>
            </div>

            <!-- Tombol Aksi Bawah -->
            <div style="margin-top: 18px; display: flex; flex-direction: column; gap: 8px;">
                <!-- Tombol Utama: Langsung Menuju Stasiun Timbang dengan Barcode Ini -->
                <button type="button" id="btnGoToWeighStation" class="btn btn-success" style="width: 100%; height: 46px; font-size: 14px; font-weight: 800; justify-content: center; border-radius: 10px; background: var(--ok-solid); border: none; cursor: pointer; color: white;">
                    <i data-lucide="scale" style="width: 17px; height: 17px; margin-right: 6px;"></i>
                    <span>Buka Stasiun Timbang Sekarang</span>
                </button>
                <button type="button" id="btnCloseNotWeighedModal" onclick="closeBarcodeNotWeighedModal()" class="btn btn-outline" style="width: 100%; height: 40px; font-size: 13px; font-weight: 700; justify-content: center; border-radius: 10px; cursor: pointer;">
                    <span>Tutup & Lanjutkan Scan (Tekan Esc)</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL POPUP: BARCODE SUDAH DIPINDAHKAN (PROPORSIONAL & RAMAH OPERATOR)     -->
<!-- ========================================================================= -->
<div id="modalBarcodeAlreadyMoved" style="display: none; position: fixed; inset: 0; z-index: 99999; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(6px); align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--bg-card); border-radius: 16px; max-width: 530px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45); border: 2px solid var(--primary); overflow: hidden; animation: popIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
        <!-- Header Banner Violet Modern -->
        <div style="background: var(--primary); padding: 18px 22px; color: #ffffff; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="background: rgba(255, 255, 255, 0.2); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i data-lucide="package-check" style="width: 25px; height: 25px; color: #ffffff;"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 17px; font-weight: 800; letter-spacing: 0.2px; color: #ffffff;">BARCODE SUDAH DIPINDAHKAN!</h3>
                    <p style="margin: 3px 0 0 0; font-size: 12px; opacity: 0.95; color: #ffffff;">Tidak dapat discan kembali dalam sesi pemindahan stok ini</p>
                </div>
            </div>
            <button type="button" onclick="closeBarcodeAlreadyMovedModal()" style="background: rgba(255, 255, 255, 0.15); border: none; color: #ffffff; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700;" title="Tutup Popup (Esc)">✕</button>
        </div>

        <!-- Body Modal Informasi -->
        <div style="padding: 22px;">
            <!-- Kotak Pesan Proporsional -->
            <div style="background: rgba(3, 105, 161, 0.08); border: 1.5px solid rgba(3, 105, 161, 0.25); border-radius: 12px; padding: 13px 16px; margin-bottom: 16px;">
                <div style="font-size: 13px; color: var(--text-main); line-height: 1.55; font-weight: 600;" id="movedModalWarningText">
                    ℹ️ Barcode kemasan ini <strong>sudah pernah dipindahkan</strong> dan telah tercatat dalam dokumen <strong>Berita Acara Serah Terima (BAST)</strong>. Untuk mencegah duplikasi stok fisik di gudang, barcode tidak dapat discan lagi.
                </div>
            </div>

            <!-- Rincian Data Barcode yang Ditolak -->
            <div style="background: var(--bg-muted); border: 1px solid var(--border); border-radius: 12px; padding: 14px 16px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px dashed var(--border); margin-bottom: 8px;">
                    <span style="color: var(--text-muted);">ID Barcode:</span>
                    <strong id="movedBarcodeId" style="font-family: 'JetBrains Mono', monospace; font-size: 13.5px; color: var(--aktif-fg);">-</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px dashed var(--border); margin-bottom: 8px;">
                    <span style="color: var(--text-muted);">Nama Komoditas:</span>
                    <strong id="movedBarcodeItem" style="color: var(--text-main);">-</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px dashed var(--border); margin-bottom: 8px;">
                    <span style="color: var(--text-muted);">Pemasok Asal:</span>
                    <span id="movedBarcodePemasok" style="font-weight: 600; color: var(--text-main);">-</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px dashed var(--border); margin-bottom: 8px;">
                    <span style="color: var(--text-muted);">Pemilik Saat Ini:</span>
                    <span class="badge badge-purple" id="movedBarcodeMitra" style="font-size: 12px; font-weight: 700; padding: 3px 8px;">-</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px dashed var(--border); margin-bottom: 8px;">
                    <span style="color: var(--text-muted);">Dokumen BAST:</span>
                    <strong id="movedBarcodeBast" style="font-family: 'JetBrains Mono', monospace; color: var(--primary);">-</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px dashed var(--border); margin-bottom: 8px;">
                    <span style="color: var(--text-muted);">Waktu Pemindahan:</span>
                    <span id="movedBarcodeTime" style="color: var(--text-muted); font-size: 12px; font-weight: 600;">-</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 4px;">
                    <span style="color: var(--text-muted); font-weight: 700;">Bobot Tercatat:</span>
                    <span class="badge badge-success" id="movedBarcodeWeight" style="font-size: 13.5px; font-weight: 900; padding: 4px 10px;">0.00 KG</span>
                </div>
            </div>

            <!-- Tombol Aksi Bawah -->
            <div style="margin-top: 18px; display: flex; flex-direction: column; gap: 8px;">
                <button type="button" id="btnCloseMovedModal" onclick="closeBarcodeAlreadyMovedModal()" class="btn btn-primary" style="width: 100%; height: 44px; font-size: 14px; font-weight: 800; justify-content: center; border-radius: 10px; background: var(--primary); border: none; cursor: pointer;">
                    <span>Tutup & Lanjutkan Scan (Tekan Enter / Esc)</span>
                </button>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <button type="button" id="btnOpenMovedBast" class="btn btn-outline" style="height: 38px; font-size: 12px; font-weight: 700; justify-content: center; border-radius: 8px;" title="Cetak atau Lihat Berita Acara Serah Terima">
                        <i data-lucide="printer" style="width: 14px; height: 14px;"></i>
                        <span>Lihat Dokumen BAST</span>
                    </button>
                    <button type="button" id="btnOpenMovedTimeline" class="btn btn-outline" style="height: 38px; font-size: 12px; font-weight: 700; justify-content: center; border-radius: 8px;" title="Lihat Riwayat Perjalanan Kemasan Barcode">
                        <i data-lucide="route" style="width: 14px; height: 14px;"></i>
                        <span>Riwayat Alur</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const mutasiCart = new Map();
    const inputScan = document.getElementById('inputBarcodeScanMutasi');
    const selectAlur = document.getElementById('alur_pergerakan');
    const alertBox = document.getElementById('mutasiAlertBox');

    // Tab Switching
    function switchMainTab(tab) {
        const secLifecycle = document.getElementById('sectionLifecycle');
        const secBast = document.getElementById('sectionBast');
        const btnLifecycle = document.getElementById('tabBtnLifecycle');
        const btnBast = document.getElementById('tabBtnBast');

        if (tab === 'lifecycle') {
            secLifecycle.style.display = 'block';
            secBast.style.display = 'none';
            btnLifecycle.style.borderBottom = '3px solid var(--primary)';
            btnLifecycle.style.background = 'var(--bg-card)';
            btnLifecycle.style.color = 'var(--primary)';
            btnBast.style.borderBottom = '3px solid transparent';
            btnBast.style.background = 'transparent';
            btnBast.style.color = 'var(--text-muted)';
        } else {
            secLifecycle.style.display = 'none';
            secBast.style.display = 'block';
            btnBast.style.borderBottom = '3px solid var(--primary)';
            btnBast.style.background = 'var(--bg-card)';
            btnBast.style.color = 'var(--primary)';
            btnLifecycle.style.borderBottom = '3px solid transparent';
            btnLifecycle.style.background = 'transparent';
            btnLifecycle.style.color = 'var(--text-muted)';
        }
    }

    // Toggle Box Asal & Tujuan sesuai Alur
    selectAlur.addEventListener('change', function() {
        const val = this.value;
        const boxPms = document.getElementById('boxPemasokKeMitra');
        const boxMtr = document.getElementById('boxAntarMitra');
        const penyerah = document.getElementById('nama_penyerah');
        const penerima = document.getElementById('nama_penerima');

        if (val === 'PEMASOK_KE_MITRA') {
            boxPms.style.display = 'block';
            boxMtr.style.display = 'none';
            penyerah.value = 'Perwakilan Pemasok / Driver';
            penerima.value = '<?= htmlspecialchars($currentUser['nama_lengkap']) ?> (Penerima Gudang)';
        } else if (val === 'MUTASI_ANTAR_MITRA') {
            boxPms.style.display = 'none';
            boxMtr.style.display = 'block';
            penyerah.value = 'Petugas Gudang Mitra Asal';
            penerima.value = '<?= htmlspecialchars($currentUser['nama_lengkap']) ?> (Petugas Gudang Mitra Tujuan)';
        } else if (val === 'KE_PEMBELI_POS') {
            boxPms.style.display = 'none';
            boxMtr.style.display = 'block';
            penyerah.value = '<?= htmlspecialchars($currentUser['nama_lengkap']) ?> (Kasir / Staff Mitra)';
            penerima.value = 'Pembeli / Customer POS';
        }
    });

    function showMutasiAlert(msg, isSuccess = true) {
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

    // Scan Barcode Mutasi
    async function handleMutasiScan(barcode) {
        barcode = barcode.trim();
        if (!barcode) return;
        inputScan.value = '';

        if (mutasiCart.has(barcode)) {
            showMutasiAlert(`Barcode '${barcode}' SUDAH PERNAH DISCAN dalam sesi pemindahan ini!`, false);
            return;
        }

        try {
            const resp = await fetch('index.php?action=verify_label&barcode=' + encodeURIComponent(barcode));
            const data = await resp.json();

            if (!data.success) {
                showMutasiAlert(`Gagal: ${data.message}`, false);
                return;
            }

            const label = data.label;
            const alur = selectAlur.value;

            // 1. Validasi: Jika barang sudah berstatus KELUAR, tidak bisa dipindah lagi
            if (label.status === 'KELUAR') {
                showMutasiAlert(`Barcode '${barcode}' sudah berstatus KELUAR dari gudang, tidak dapat dipindahkan lagi!`, false);
                return;
            }

            // 2. Validasi Kunci: Barcode BELUM DITIMBANG (0 KG) TIDAK DAPAT DISCAN
            // Munculkan popup untuk ditimbang terlebih dahulu di Stasiun Penerimaan & Timbang
            const beratNetto = parseFloat(label.berat_netto || 0);
            if (beratNetto <= 0) {
                showBarcodeNotWeighedModal(label);
                showMutasiAlert(`Barcode '${barcode}' BELUM DITIMBANG (0.00 KG)! Silakan timbang terlebih dahulu sebelum dipindahkan.`, false);
                return;
            }

            // 3. Validasi: Barcode yang SUDAH DIPINDAHKAN tidak bisa discan lagi
            // Munculkan pop-up modal proporsional dan tolak scan
            if (label.already_moved || label.last_mutasi || (alur === 'PEMASOK_KE_MITRA' && label.mitra_id)) {
                showBarcodeAlreadyMovedModal(label);
                const namaTujuan = (label.last_mutasi && label.last_mutasi.nama_mitra_tujuan) 
                    ? label.last_mutasi.nama_mitra_tujuan 
                    : (label.nama_mitra || 'Mitra');
                showMutasiAlert(`Barcode '${barcode}' SUDAH PERNAH DIPINDAHKAN ke ${namaTujuan}! Tidak dapat discan kembali.`, false);
                return;
            }

            // 3. Validasi khusus alur antar-mitra: harus sesuai mitra asal yang dipilih
            if (alur === 'MUTASI_ANTAR_MITRA') {
                const mitraAsalId = document.getElementById('mutasi_mitra_asal_id').value;
                if (!label.mitra_id) {
                    showMutasiAlert(`Barcode '${barcode}' belum dialokasikan ke mitra mana pun! Lakukan alur 'Pemasok ke Mitra' terlebih dahulu.`, false);
                    return;
                }
                if (label.mitra_id != mitraAsalId) {
                    showMutasiAlert(`Barcode '${barcode}' terdaftar milik ${label.nama_mitra || 'Mitra Lain'}, bukan mitra asal yang dipilih!`, false);
                    return;
                }
            }

            mutasiCart.set(label.id_label, label);
            renderMutasiCart();
            showMutasiAlert(`Kemasan ditambahkan ke pemindahan: ${label.nama_item} (${parseFloat(label.berat_netto || 0).toFixed(2)} kg)`, true);

        } catch (err) {
            showMutasiAlert('Kesalahan koneksi saat verifikasi barcode: ' + err.message, false);
        }
    }

    function renderMutasiCart() {
        const tbody = document.getElementById('mutasiCartTableBody');

        if (mutasiCart.size === 0) {
            tbody.innerHTML = `
                <tr id="emptyMutasiRow">
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 40px;">
                        Belum ada kemasan yang discan untuk sesi pemindahan stok ini.
                    </td>
                </tr>
            `;
            document.getElementById('mutasiCartCount').innerText = '0';
            document.getElementById('mutasiTotalKg').innerText = '0.00 kg';
            return;
        }

        let html = '';
        let totalKg = 0;
        let index = 1;

        mutasiCart.forEach((lbl, code) => {
            const berat = parseFloat(lbl.berat_netto || 0);
            totalKg += berat;
            html += `
                <tr>
                    <td style="color: var(--text-muted); font-size: 12px;">${index++}</td>
                    <td><strong style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--primary);">${lbl.id_label}</strong></td>
                    <td>
                        <strong>${lbl.nama_item}</strong>
                        <div style="font-size: 11px; color: var(--text-muted);">${lbl.nama_jenis || ''}</div>
                    </td>
                    <td><span class="badge badge-info" style="font-size: 11px;">${lbl.nama_pemasok || 'Pemasok Umum'}</span></td>
                    <td>
                        <span class="mitra-tag ${(lbl.kode_mitra || 'MTR').toLowerCase()}" style="background: ${lbl.warna_identitas || '#0369a1'}; padding: 2px 7px; font-size: 11px;">
                            ${lbl.kode_mitra || 'Belum Ada'}
                        </span>
                    </td>
                    <td style="text-align: right; font-weight: 800; font-size: 14px; color: var(--ok-fg);">${berat.toFixed(2)} kg</td>
                    <td style="text-align: center;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="removeMutasiLabel('${code}')" style="color: var(--danger); padding: 2px 7px; font-size: 11px;">✕</button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        document.getElementById('mutasiCartCount').innerText = mutasiCart.size;
        document.getElementById('mutasiTotalKg').innerText = totalKg.toFixed(2) + ' kg';
    }

    function removeMutasiLabel(barcode) {
        mutasiCart.delete(barcode);
        renderMutasiCart();
    }

    function triggerScanMutasi(barcode) {
        handleMutasiScan(barcode);
    }

    inputScan.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleMutasiScan(this.value);
        }
    });

    document.getElementById('btnScanMutasi').addEventListener('click', () => {
        handleMutasiScan(inputScan.value);
    });

    document.getElementById('btnClearMutasiCart').addEventListener('click', async () => {
        if (mutasiCart.size > 0) {
            const res = await window.showAppConfirm({
                title: 'Kosongkan Sesi Pemindahan?',
                html: 'Apakah Anda yakin ingin mengosongkan semua kemasan dari sesi pemindahan ini?',
                icon: 'warning',
                confirmButtonText: 'Ya, Kosongkan',
                cancelButtonText: 'Batal'
            });
            if (res.isConfirmed) {
                mutasiCart.clear();
                renderMutasiCart();
            }
        }
    });

    // Submit Pemindahan Stok & BAST
    document.getElementById('btnSubmitMutasi').addEventListener('click', async () => {
        if (mutasiCart.size === 0) {
            alert('Daftar barcode masih kosong. Scan minimal 1 kemasan.');
            return;
        }

        const penyerah = document.getElementById('nama_penyerah').value.trim();
        const penerima = document.getElementById('nama_penerima').value.trim();
        if (!penyerah || !penerima) {
            alert('Harap lengkapi nama pihak penyerah dan penerima serah terima.');
            return;
        }

        const alur = selectAlur.value;
        let pemasokId = null;
        let mitraAsalId = null;
        let mitraTujuanId = null;

        if (alur === 'PEMASOK_KE_MITRA') {
            pemasokId = parseInt(document.getElementById('mutasi_pemasok_id').value);
            mitraTujuanId = parseInt(document.getElementById('mutasi_mitra_tujuan_id').value);
        } else if (alur === 'MUTASI_ANTAR_MITRA') {
            mitraAsalId = parseInt(document.getElementById('mutasi_mitra_asal_id').value);
            mitraTujuanId = parseInt(document.getElementById('mutasi_mitra_tujuan_id_2').value);
            if (mitraAsalId === mitraTujuanId) {
                alert('Mitra asal dan mitra tujuan tidak boleh sama untuk transfer antar-mitra!');
                return;
            }
        } else if (alur === 'KE_PEMBELI_POS') {
            mitraAsalId = parseInt(document.getElementById('mutasi_mitra_asal_id').value);
            mitraTujuanId = mitraAsalId;
        }

        const barcodes = Array.from(mutasiCart.keys());
        const payload = {
            alur_pergerakan: alur,
            pemasok_id: pemasokId,
            mitra_asal_id: mitraAsalId,
            mitra_tujuan_id: mitraTujuanId,
            mitra_id: mitraTujuanId || mitraAsalId || 1,
            nama_penyerah: penyerah,
            nama_penerima: penerima,
            user_id: <?= $currentUser['id'] ?>,
            no_referensi: document.getElementById('no_referensi').value,
            catatan_serah_terima: document.getElementById('catatan_serah_terima').value,
            barcodes: barcodes
        };

        try {
            const resp = await fetch('index.php?action=process_mutasi', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await resp.json();

            if (data.success) {
                const cetakResult = await window.showAppConfirm({
                    title: 'Pemindahan Stok Berhasil!',
                    html: `
                        <div style="text-align: left; font-size: 13.5px;">
                            <p style="margin-bottom: 6px;"><strong>No. BAST:</strong> <code>${data.no_bast}</code></p>
                            <p style="margin-bottom: 6px;"><strong>No. Transaksi:</strong> <code>${data.no_transaksi}</code></p>
                            <p style="margin-bottom: 6px;"><strong>Total Muatan:</strong> ${data.total_label} pcs (${parseFloat(data.total_kg).toFixed(2)} kg)</p>
                            <p style="margin-bottom: 12px;"><strong>Diterima Oleh:</strong> ${penerima}</p>
                            <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--ok-solid); padding: 10px; border-radius: 6px;">
                                Dokumen Berita Acara Serah Terima (BAST) siap dicetak. Apakah Anda ingin membukanya sekarang?
                            </div>
                        </div>
                    `,
                    icon: 'success',
                    confirmButtonText: '🖨️ Cetak Dokumen BAST',
                    cancelButtonText: 'Tutup & Selesai'
                });

                mutasiCart.clear();

                if (cetakResult.isConfirmed && data.transaksi_id) {
                    window.open('index.php?page=bast_print&id=' + data.transaksi_id, '_blank');
                }
                location.reload();
            } else {
                showMutasiAlert('Gagal Menyelesaikan Pemindahan: ' + data.message, false);
            }
        } catch (err) {
            showMutasiAlert('Kesalahan sistem saat memproses: ' + err.message, false);
        }
    });

    // Modal Timeline Barcode
    async function showTimelineModal(barcode) {
        const modal = document.getElementById('modalTimeline');
        const modalBody = document.getElementById('timelineModalBody');
        const modalTitle = document.getElementById('modalBarcodeTitle');

        modalTitle.innerText = barcode;
        modal.style.display = 'flex';
        modalBody.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 30px;">Mengambil data timeline perjalanan...</div>';

        try {
            const resp = await fetch('index.php?action=get_barcode_timeline&barcode=' + encodeURIComponent(barcode));
            const data = await resp.json();

            if (!data.success) {
                modalBody.innerHTML = `<div class="alert alert-danger">Gagal memuat timeline: ${data.message}</div>`;
                return;
            }

            const label = data.timeline.label;
            const history = data.timeline.history || [];

            let html = `
                <!-- Ringkasan Produk Kemasan -->
                <div style="background: var(--bg-muted); border: 1px solid var(--border); border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12.5px;">
                    <div>
                        <div style="color: var(--text-muted);">Nama Produk:</div>
                        <strong style="font-size: 14px;">${label.nama_item}</strong>
                        <div style="font-size: 11.5px; color: var(--text-muted);">${label.nama_jenis || ''}</div>
                    </div>
                    <div>
                        <div style="color: var(--text-muted);">Berat Netto Aktual:</div>
                        <strong style="font-size: 16px; color: var(--ok-fg);">${parseFloat(label.berat_netto || 0).toFixed(2)} kg</strong>
                        <div>Status: <span class="badge ${label.status === 'KELUAR' ? 'badge-secondary' : 'badge-success'}">${label.status}</span></div>
                    </div>
                </div>

                <!-- Timeline Alur Vertikal -->
                <div style="position: relative; padding-left: 32px;">
                    <div style="position: absolute; left: 13px; top: 12px; bottom: 12px; width: 3px; background: var(--border);"></div>

                    <!-- Step 1: Penerimaan dari Pemasok -->
                    <div style="position: relative; margin-bottom: 22px;">
                        <div style="position: absolute; left: -32px; top: 0; width: 28px; height: 28px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; box-shadow: 0 0 0 4px #eff6ff;">
                            1
                        </div>
                        <div style="background: var(--bg-card); border: 1.5px solid var(--border-hover); border-radius: 8px; padding: 12px 14px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong style="color: var(--aktif-fg); font-size: 13.5px;">🚚 Langkah 1: Diterima dari Pemasok</strong>
                                <span style="font-size: 11px; color: var(--text-muted);">${label.created_at || '-'}</span>
                            </div>
                            <div style="margin-top: 6px; font-size: 12px; color: var(--text-secondary); line-height: 1.5;">
                                <div>Pemasok: <strong>${label.nama_pemasok || 'Pemasok Umum'}</strong> (${label.kode_pemasok || 'PMS-01'})</div>
                                <div>No. Surat Jalan: <code>${label.no_surat_jalan || label.batch_no || '-'}</code></div>
                                <div>Bobot Awal Ditimbang: <strong>${parseFloat(label.berat_netto || 0).toFixed(2)} kg</strong></div>
                            </div>
                        </div>
                    </div>
            `;

            // Step 2: Riwayat Mutasi / Pemindahan BAST
            if (history.length > 0) {
                history.forEach((trx, hIdx) => {
                    const isOutbound = (trx.tipe_transaksi === 'KELUAR' || trx.alur_pergerakan === 'KE_PEMBELI_POS');
                    const stepNum = hIdx + 2;
                    const stepColor = isOutbound ? '#10b981' : '#0369a1';
                    const stepBg = isOutbound ? '#ecfdf5' : '#f5f3ff';
                    const stepBorder = isOutbound ? '#a7f3d0' : '#ddd6fe';
                    const stepTitle = isOutbound ? '📦 Langkah 3: Pengeluaran Barang (Outbound)' : `🔄 Langkah ${stepNum}: Pemindahan Stok (BAST)`;

                    html += `
                        <div style="position: relative; margin-bottom: 22px;">
                            <div style="position: absolute; left: -32px; top: 0; width: 28px; height: 28px; border-radius: 50%; background: ${stepColor}; color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; box-shadow: 0 0 0 4px ${stepBg};">
                                ${stepNum}
                            </div>
                            <div style="background: var(--bg-card); border: 1.5px solid ${stepBorder}; border-radius: 8px; padding: 12px 14px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong style="color: ${stepColor}; font-size: 13.5px;">${stepTitle}</strong>
                                    <span style="font-size: 11px; color: var(--text-muted);">${trx.created_at}</span>
                                </div>
                                <div style="margin-top: 6px; font-size: 12px; color: var(--text-secondary); line-height: 1.5;">
                                    ${trx.no_bast ? `<div>No. BAST: <strong style="font-family: 'JetBrains Mono', monospace; color: var(--primary);">${trx.no_bast}</strong></div>` : ''}
                                    <div>No. Transaksi: <code>${trx.no_transaksi}</code></div>
                                    <div>Alur: <strong>${trx.alur_pergerakan || '-'}</strong></div>
                                    ${trx.nama_penyerah ? `<div>Diserahkan: <strong>${trx.nama_penyerah}</strong> ➔ Diterima: <strong>${trx.nama_penerima}</strong></div>` : ''}
                                    ${trx.catatan_serah_terima ? `<div style="font-style: italic; color: var(--text-muted); margin-top: 4px;">"${trx.catatan_serah_terima}"</div>` : ''}
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Operator: ${trx.operator_nama || 'System'}</div>
                                </div>
                            </div>
                        </div>
                    `;
                });
            } else {
                // Belum ada mutasi
                html += `
                    <div style="position: relative; margin-bottom: 22px;">
                        <div style="position: absolute; left: -32px; top: 0; width: 28px; height: 28px; border-radius: 50%; background: var(--text-muted); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px;">
                            2
                        </div>
                        <div style="background: var(--bg-card); border: 1.5px dashed var(--border); border-radius: 8px; padding: 12px 14px; color: var(--text-muted); font-size: 12px;">
                            <strong>Belum Ada Mutasi Pemindahan Stok</strong>
                            <div style="margin-top: 4px;">Kemasan ini saat ini masih berada pada status stok aktif milik mitra <strong>${label.nama_mitra || 'Utama'}</strong>.</div>
                        </div>
                    </div>
                `;
            }

            // Jika status KELUAR dan belum tercatat di history transaksi di atas
            if (label.status === 'KELUAR' && (!history.length || !history.some(h => h.tipe_transaksi === 'KELUAR'))) {
                html += `
                    <div style="position: relative; margin-bottom: 10px;">
                        <div style="position: absolute; left: -32px; top: 0; width: 28px; height: 28px; border-radius: 50%; background: var(--ok-solid); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px;">
                            ✓
                        </div>
                        <div style="background: var(--bg-card); border: 1.5px solid var(--border-hover); border-radius: 8px; padding: 12px 14px;">
                            <strong style="color: var(--ok-fg); font-size: 13.5px;">📦 Langkah 3: Barang Telah Keluar dari Gudang</strong>
                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                                Status barang: <span class="badge badge-secondary">KELUAR</span> (Telah dikirim ke pembeli / outlet POS).
                            </div>
                        </div>
                    </div>
                `;
            }

            html += `</div>`;
            modalBody.innerHTML = html;

        } catch (err) {
            modalBody.innerHTML = `<div class="alert alert-danger">Kesalahan sistem: ${err.message}</div>`;
        }
    }

    function closeTimelineModal() {
        document.getElementById('modalTimeline').style.display = 'none';
    }

    // Modal Popup Barcode Sudah Dipindahkan (Proporsional & Ramah Operator)
    let currentMovedBarcode = null;
    let currentMovedBastTrxId = null;

    function showBarcodeAlreadyMovedModal(label) {
        currentMovedBarcode = label.id_label;
        const modal = document.getElementById('modalBarcodeAlreadyMoved');
        if (!modal) return;

        const lastMut = label.last_mutasi || {};
        currentMovedBastTrxId = lastMut.transaksi_id || null;

        // Set teks & rincian
        document.getElementById('movedBarcodeId').innerText = label.id_label;
        document.getElementById('movedBarcodeItem').innerText = label.nama_item || '-';
        document.getElementById('movedBarcodePemasok').innerText = label.nama_pemasok || '-';
        
        const mitraName = lastMut.nama_mitra_tujuan || label.nama_mitra || 'Sudah Ada Mitra';
        const kodeMitra = lastMut.kode_mitra_tujuan || label.kode_mitra || '';
        document.getElementById('movedBarcodeMitra').innerText = kodeMitra ? `${kodeMitra} - ${mitraName}` : mitraName;

        const noBast = lastMut.no_bast || lastMut.no_transaksi || 'Tercatat di Sistem BAST';
        document.getElementById('movedBarcodeBast').innerText = noBast;

        const waktuStr = lastMut.waktu_mutasi || label.updated_at || label.created_at || '-';
        document.getElementById('movedBarcodeTime').innerText = waktuStr;

        const bobot = parseFloat(label.berat_netto || 0).toFixed(2);
        document.getElementById('movedBarcodeWeight').innerText = `${bobot} KG`;

        // Teks kalimat proporsional
        const penyerah = lastMut.nama_penyerah ? ` oleh ${lastMut.nama_penyerah}` : '';
        const penerima = lastMut.nama_penerima ? ` kepada ${lastMut.nama_penerima}` : '';
        const serahTerimaInfo = (penyerah || penerima) ? ` (${penyerah}${penerima})` : '';

        document.getElementById('movedModalWarningText').innerHTML = `
            ℹ️ Barcode kemasan <strong>${label.id_label}</strong> sudah pernah dipindahkan ke <strong>${mitraName}</strong>${serahTerimaInfo} dengan dokumen <strong>${noBast}</strong>.<br>
            Untuk menjaga validitas mutasi fisik dan mencegah pencatatan ganda, kemasan yang telah dipindahkan <strong>tidak dapat discan kembali</strong>.
        `;

        // Konfigurasi tombol BAST
        const btnBast = document.getElementById('btnOpenMovedBast');
        if (btnBast) {
            if (currentMovedBastTrxId) {
                btnBast.style.display = 'inline-flex';
                btnBast.onclick = function() {
                    window.open('index.php?page=bast_print&id=' + currentMovedBastTrxId, '_blank');
                };
            } else {
                btnBast.style.display = 'none';
            }
        }

        // Konfigurasi tombol Timeline
        const btnTimeline = document.getElementById('btnOpenMovedTimeline');
        if (btnTimeline) {
            btnTimeline.onclick = function() {
                closeBarcodeAlreadyMovedModal();
                showTimelineModal(label.id_label);
            };
        }

        // Tampilkan modal
        modal.style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
        if (window.scannerAudio) window.scannerAudio.playErrorBuzz();

        // Fokus ke tombol tutup
        setTimeout(() => {
            const btnClose = document.getElementById('btnCloseMovedModal');
            if (btnClose) btnClose.focus();
        }, 100);
    }

    function closeBarcodeAlreadyMovedModal() {
        const modal = document.getElementById('modalBarcodeAlreadyMoved');
        if (modal) modal.style.display = 'none';
        
        // Kembalikan fokus ke input barcode scan
        if (inputScan) {
            inputScan.value = '';
            inputScan.focus();
        }
    }

    // Modal Popup Barcode Belum Ditimbang (Wajib Ditimbang Terlebih Dahulu)
    let currentNotWeighedBarcode = null;

    function showBarcodeNotWeighedModal(label) {
        currentNotWeighedBarcode = label.id_label;
        const modal = document.getElementById('modalBarcodeNotWeighed');
        if (!modal) return;

        // Isi rincian data barcode
        document.getElementById('notWeighedBarcodeId').innerText = label.id_label;
        document.getElementById('notWeighedBarcodeItem').innerText = label.nama_item || '-';
        document.getElementById('notWeighedBarcodePemasok').innerText = label.nama_pemasok || '-';
        document.getElementById('notWeighedBarcodeSJ').innerText = label.no_surat_jalan || label.batch_no || '-';

        document.getElementById('notWeighedModalWarningText').innerHTML = `
            ⚠️ Barcode keranjang <strong>${label.id_label}</strong> belum memiliki bobot timbangan riil (0.00 KG).<br>
            Semua barang masuk wajib ditimbang di <strong>Stasiun Penerimaan & Timbang</strong> agar bobot fisik tercatat sebelum dapat dipindahkan antar mitra.
        `;

        // Atur tombol langsung menuju stasiun penimbangan
        const btnGoWeigh = document.getElementById('btnGoToWeighStation');
        if (btnGoWeigh) {
            btnGoWeigh.onclick = function() {
                window.location.href = 'index.php?page=timbang_cetak&tab=scantimbang&barcode=' + encodeURIComponent(label.id_label);
            };
        }

        // Tampilkan modal
        modal.style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
        if (window.scannerAudio) window.scannerAudio.playErrorBuzz();

        // Fokus ke tombol timbang sekarang
        setTimeout(() => {
            if (btnGoWeigh) btnGoWeigh.focus();
        }, 100);
    }

    function closeBarcodeNotWeighedModal() {
        const modal = document.getElementById('modalBarcodeNotWeighed');
        if (modal) modal.style.display = 'none';
        
        // Kembalikan fokus ke input barcode scan
        if (inputScan) {
            inputScan.value = '';
            inputScan.focus();
        }
    }

    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeTimelineModal();
            closeBarcodeAlreadyMovedModal();
            closeBarcodeNotWeighedModal();
        }
        const modalMoved = document.getElementById('modalBarcodeAlreadyMoved');
        if (modalMoved && modalMoved.style.display === 'flex') {
            if (e.key === 'Enter') {
                e.preventDefault();
                closeBarcodeAlreadyMovedModal();
            }
        }
        const modalNotWeighed = document.getElementById('modalBarcodeNotWeighed');
        if (modalNotWeighed && modalNotWeighed.style.display === 'flex') {
            if (e.key === 'Enter') {
                e.preventDefault();
                const btnGoWeigh = document.getElementById('btnGoToWeighStation');
                if (btnGoWeigh) btnGoWeigh.click();
            }
        }
    });

    // Toggle Jumbotron Filter Panel Mutasi
    function toggleMutasiFilterPanel(forceState) {
        const panel = document.getElementById('panelJumbotronFilterMutasi');
        if (!panel) return;
        const isHidden = panel.style.display === 'none' || panel.style.display === '';
        const shouldShow = forceState !== undefined ? forceState : isHidden;
        panel.style.display = shouldShow ? 'block' : 'none';
        if (shouldShow) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
