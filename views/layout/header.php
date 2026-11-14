<?php
/**
 * Header Global Sistem Barcode Inventory Multi-Mitra (Sidebar & Responsive Edition)
 */

use App\Config\Config;
use App\Core\Database;

$currentPage = $_GET['page'] ?? 'dashboard';

// User login dari session
$currentUser = $_SESSION['user_login'] ?? [
    'id' => 1,
    'username' => 'nia',
    'nama_lengkap' => 'Nia',
    'role' => 'ADMIN'
];

$userDisplayName = $currentUser['nama_lengkap'] ?? 'User';
$roleUpper = strtoupper($currentUser['role'] ?? '');
$nameLower = strtolower($userDisplayName);

if (str_contains($nameLower, 'nia') || $roleUpper === 'ADMIN') {
    $userInitials = 'NI';
    $userRoleBadge = 'Admin';
    $userBadgeClass = 'badge-admin';
    $userAvatarClass = 'avatar-nia';
} elseif (str_contains($nameLower, 'arif') || $roleUpper === 'ARIF' || $roleUpper === 'SUPERVISOR' || str_contains($roleUpper, 'INBOUND')) {
    $userInitials = 'AR';
    $userRoleBadge = 'Founder';
    $userBadgeClass = 'badge-arif';
    $userAvatarClass = 'avatar-arif';
} else {
    $userInitials = 'OP';
    $userRoleBadge = 'Operator';
    $userBadgeClass = 'badge-operator';
    $userAvatarClass = 'avatar-operator';
}

// Mapping info halaman agar sangat ramah bagi orang awam
$pageInfoMap = [
    'dashboard'            => ['title' => 'Dashboard Ringkasan', 'desc' => 'Pantauan cepat stok dan aktivitas gudang'],
    'timbang_cetak'        => ['title' => 'Penerimaan Barang', 'desc' => 'Buat barcode keranjang dan timbang barang masuk dari pemasok'],
    'pengeluaran'          => ['title' => 'Pengeluaran Barang ke Mitra', 'desc' => 'Keluarkan stok fisik pemasok dari gudang ke mitra bisnis'],
    'ar_aging'             => ['title' => 'AR Aging', 'desc' => 'Pelacakan umur piutang, setoran cicilan parsial, dan buku kas masuk mitra'],
    'ap_aging'             => ['title' => 'AP Aging', 'desc' => 'Pelacakan umur utang surat jalan masuk, cicilan pembayaran kas keluar ke pemasok'],
    'tutup_periode'        => ['title' => 'Tutup Periode', 'desc' => 'Kunci laporan bulanan setelah dicek; periode tertutup tidak menerima transaksi atau pembatalan'],
    'kas_bank'             => ['title' => 'Transaksi Kas & Bank', 'desc' => 'Catat biaya tunai/transfer, setoran modal, dan prive; jurnal terbit otomatis'],
    'rekonsiliasi_bank'    => ['title' => 'Rekonsiliasi Bank', 'desc' => 'Audit harian total setoran mitra dengan uang masuk di bank, lengkap dengan lampiran screenshot'],
    'barang_datang'        => ['title' => 'Barang Datang', 'desc' => 'Barang yang diterima gudang dari pemasok dan total nilai pembelian'],
    'purchase_book'        => ['title' => 'Purchase Book', 'desc' => 'Riwayat harga beli per artikel dari pemasok'],
    'sales_book'           => ['title' => 'Sales Book', 'desc' => 'Riwayat harga jual per artikel ke mitra'],
    'coa'                  => ['title' => 'COA', 'desc' => 'Daftar akun dan saldo dari data sumber'],
    'profit_loss'          => ['title' => 'Laba Rugi', 'desc' => 'Penjualan, HPP, dan biaya dari jurnal'],
    'stok_mitra'           => ['title' => 'Saldo Stok Mitra', 'desc' => 'Stok per mitra dan nilai persediaan sebesar harga beli, dicocokkan dengan jurnal'],
    'stok_opname'          => ['title' => 'Stock Opname Fisik', 'desc' => 'Pencocokan stok fisik di gudang dengan data sistem'],
    'master'               => ['title' => 'Master Data', 'desc' => 'Kelola pemasok, harga pemasok, harga mitra, produk, dan API'],
    'master_mitra'         => ['title' => 'Master Mitra', 'desc' => 'Kelola data 7 perusahaan mitra bisnis'],
    'master_produk'        => ['title' => 'Master Produk', 'desc' => 'Kelola katalog dan spesifikasi barang'],
    'master_harga_pemasok' => ['title' => 'Master Harga Pemasok', 'desc' => 'Kelola tarif harga beli dari pemasok per jenis barang'],
    'master_harga_mitra'   => ['title' => 'Master Harga Mitra', 'desc' => 'Kelola tarif harga jual pokok ke mitra bisnis'],
    'master_harga'         => ['title' => 'Master Harga Mitra', 'desc' => 'Kelola tarif harga jual pokok ke mitra bisnis'],
    'accurate_sync'        => ['title' => 'Integrasi Accurate Online', 'desc' => 'Sinkronisasi otomatis dengan API Accurate Online'],
    'prd_viewer'           => ['title' => 'Panduan Cara Pakai', 'desc' => 'Cara memakai sistem dari penerimaan barang sampai laporan keuangan']
];
$currentPageInfo = $pageInfoMap[$currentPage] ?? [
    'title' => 'Sistem Barcode Gudang',
    'desc'  => 'Manajemen Persediaan Multi-Mitra'
];

// Menyesuaikan judul topbar khusus sub-menu Penerimaan
if ($currentPage === 'timbang_cetak') {
    $currentSubTab = $_GET['tab'] ?? 'timbang_cetak';
    if ($currentSubTab === 'riwayat_penerimaan') {
        $currentPageInfo = [
            'title' => 'Riwayat Penerimaan',
            'desc'  => 'Arsip data surat jalan dan penimbangan barang masuk'
        ];
    } elseif ($currentSubTab === 'stok_gudang') {
        $currentPageInfo = [
            'title' => 'Stok Barang Gudang Utama',
            'desc'  => 'Sisa persediaan fisik di gudang penerimaan setelah penerimaan dan mutasi ke mitra'
        ];
    } else {
        $currentPageInfo = [
            'title' => 'Penerimaan & Timbang Barang',
            'desc'  => 'Input penimbangan keranjang masuk dan cetak barcode per surat jalan'
        ];
    }
} elseif (($currentPage === 'master' && isset($_GET['tab']) && $_GET['tab'] === 'accurate') || $currentPage === 'accurate_sync') {
    $currentPageInfo = [
        'title' => 'Settingan API Accurate',
        'desc'  => 'Konfigurasi API token, otentikasi database, dan antrean sinkronisasi Accurate Online'
    ];
}

$hariIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$bulanIndo = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$todayText = $hariIndo[date('w')] . ', ' . date('j') . ' ' . $bulanIndo[(int)date('n')] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? $currentPageInfo['title'] . ' - Sistem Barcode Inventory') ?></title>
    
    <!-- Favicon Berwarna: Vector SVG & PNG Multi-Resolution Fallbacks -->
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/apple-touch-icon.png">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    
    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- SweetAlert2 Modern Dialogs CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- CSS Aplikasi Utama & Cetak Barcode -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/style.css') ?>">
    <link rel="stylesheet" href="assets/css/print-label.css?v=<?= filemtime(__DIR__ . '/../../assets/css/print-label.css') ?>">

    <!-- SweetAlert2 Theme Helper & Seamless Fallback Adapter -->
    <script>
        // Custom Styled SweetAlert Mixins
        const SwalCustom = Swal.mixin({
            customClass: {
                popup: 'swal2-modern-popup',
                title: 'swal2-modern-title',
                confirmButton: 'btn btn-primary',
                cancelButton: 'btn btn-outline',
                denyButton: 'btn btn-danger'
            },
            buttonsStyling: false,
            focusConfirm: true
        });

        // Global Alert Helpers
        window.showToast = function(msg, icon = 'success') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
            Toast.fire({
                icon: icon,
                title: msg
            });
        };

        window.showAppAlert = function(options) {
            let config = {
                confirmButtonText: 'Mengerti',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            };

            if (typeof options === 'string') {
                let icon = 'info';
                let title = 'Pemberitahuan';
                let text = options;

                if (options.includes('⚠️') || options.toLowerCase().includes('peringatan') || options.toLowerCase().includes('terkunci') || options.toLowerCase().includes('tidak dapat')) {
                    icon = 'warning';
                    title = 'Perhatian';
                } else if (options.includes('✅') || options.includes('✓') || options.toLowerCase().includes('sukses') || options.toLowerCase().includes('berhasil')) {
                    icon = 'success';
                    title = 'Berhasil';
                } else if (options.toLowerCase().includes('gagal') || options.toLowerCase().includes('kesalahan') || options.toLowerCase().includes('error')) {
                    icon = 'error';
                    title = 'Terjadi Kesalahan';
                }

                config.title = title;
                config.html = text.replace(/\n/g, '<br>');
                config.icon = icon;
            } else {
                config = Object.assign(config, options);
            }

            return SwalCustom.fire(config);
        };

        window.showAppConfirm = function(options) {
            let config = {
                title: 'Konfirmasi Tindakan',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Lanjutkan',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-outline'
                }
            };

            if (typeof options === 'string') {
                config.html = options.replace(/\n/g, '<br>');
            } else {
                config = Object.assign(config, options);
            }

            return SwalCustom.fire(config);
        };

        // Otomatis arahkan alert() bawaan browser ke SweetAlert2 yang lebih elegan
        window.alert = function(msg) {
            return window.showAppAlert(msg);
        };
    </script>

    <!-- Submenu Accordion Handler -->
    <script>
        function toggleSidebarSubmenu(menuId, event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            const targetMenu = document.getElementById(menuId);
            if (!targetMenu) return;
            const willOpen = !targetMenu.classList.contains('is-open');

            // Tutup sub-menu lain yang bukan halaman aktif saat ini agar sidebar tetap rapi
            document.querySelectorAll('.nav-has-submenu').forEach(function(el) {
                if (el !== targetMenu && !el.dataset.activePage) {
                    el.classList.remove('is-open');
                    const btn = el.querySelector('.nav-item');
                    if (btn) btn.setAttribute('aria-expanded', 'false');
                }
            });

            if (willOpen) {
                targetMenu.classList.add('is-open');
                const btn = targetMenu.querySelector('.nav-item');
                if (btn) btn.setAttribute('aria-expanded', 'true');
            } else {
                targetMenu.classList.remove('is-open');
                const btn = targetMenu.querySelector('.nav-item');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            }

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    </script>
</head>
<body class="has-sidebar">
    <div class="app-layout">
        <!-- Overlay Backdrop Mobile -->
        <div class="sidebar-backdrop no-print" id="sidebarBackdrop" onclick="toggleSidebar(false)" aria-label="Tutup Menu"></div>

        <!-- SIDEBAR KIRI UTAMA -->
        <aside class="app-sidebar no-print" id="appSidebar">
            <!-- Header Sidebar: Logo & Tombol Close Mobile -->
            <div class="sidebar-header">
                <a href="index.php?page=dashboard" class="sidebar-brand" title="Ke Halaman Beranda">
                    <div class="brand-info">
                        <span class="brand-title">AR_BAROKAH</span>
                        <span class="brand-subtitle">BARCODE & TIMBANG</span>
                    </div>
                </a>
                <button type="button" class="sidebar-close-btn" onclick="toggleSidebar(false)" title="Tutup Menu">
                    <i data-lucide="x" style="width: 20px; height: 20px;"></i>
                </button>
            </div>



            <?php
            $currentTab = $_GET['tab'] ?? '';
            $isPenerimaanActive = (($currentPage === 'timbang_cetak' && $currentTab !== 'stok_gudang') || $currentPage === 'barang_datang');
            $isOutboundActive = ($currentPage === 'pengeluaran');
            $isStokGudangActive = (($currentPage === 'timbang_cetak' && $currentTab === 'stok_gudang') || $currentPage === 'stok_opname');
            $isArAgingActive = ($currentPage === 'ar_aging' && $currentTab !== 'nota');
            $isSalesInvActive = ($currentPage === 'ar_aging' && $currentTab === 'nota');
            $isApAgingActive = ($currentPage === 'ap_aging' && $currentTab !== 'invoices');
            $isPurchInvActive = ($currentPage === 'ap_aging' && $currentTab === 'invoices');
            $isRekonActive   = ($currentPage === 'rekonsiliasi_bank');
            $isKasActive     = ($currentPage === 'kas_bank');
            $isPurchGroupActive = ($isPurchInvActive || $currentPage === 'purchase_book');
            $isSalesGroupActive = ($isSalesInvActive || $currentPage === 'sales_book');
            $isProfitActive = in_array($currentPage, ['profit_loss', 'estimasi_keuntungan', 'profit', 'coa', 'tutup_periode']);
            $isStokMitraActive = ($currentPage === 'stok_mitra');
            $isMasterActive = in_array($currentPage, ['master', 'master_mitra', 'master_produk', 'master_harga', 'master_harga_pemasok', 'master_harga_mitra', 'accurate_sync']);
            ?>
            <!-- Navigasi Menu Vertikal -->
            <nav class="sidebar-nav">
                <!-- MENU UTAMA -->
                <ul class="nav-list" style="margin-bottom: 6px;">
                    <li>
                        <a href="index.php?page=dashboard" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                            <div class="nav-item-icon icon-blue">
                                <i data-lucide="layout-dashboard" style="width: 17px; height: 17px;"></i>
                            </div>
                            <div class="nav-item-text">
                                <span class="nav-item-title">Dashboard</span>
                                <span class="nav-item-desc">Ringkasan & aktivitas gudang</span>
                            </div>
                        </a>
                    </li>
                </ul>

                <!-- 3 ALUR KERJA UTAMA GUDANG -->
                <div class="nav-section-label">
                    <span>PROSES UTAMA</span>
                </div>
                <ul class="nav-list">
                    <li class="nav-has-submenu <?= $isPenerimaanActive ? 'is-open' : '' ?>" id="menuPenerimaan" data-active-page="<?= $isPenerimaanActive ? '1' : '' ?>">
                        <a href="javascript:void(0)" class="nav-item <?= $isPenerimaanActive ? 'active' : '' ?>" onclick="toggleSidebarSubmenu('menuPenerimaan', event)" role="button" aria-expanded="<?= $isPenerimaanActive ? 'true' : 'false' ?>" title="Klik untuk menampilkan / menyembunyikan sub menu">
                            <div class="nav-item-icon icon-green">
                                <i data-lucide="package-plus" style="width: 17px; height: 17px;"></i>
                            </div>
                            <div class="nav-item-text">
                                <span class="nav-item-title">Penerimaan Barang</span>
                                <span class="nav-item-desc">Barcode & timbang masuk</span>
                            </div>
                            <div class="nav-item-chevron">
                                <i data-lucide="chevron-down" style="width: 15px; height: 15px;"></i>
                            </div>
                        </a>
                        <!-- Sub Menu Penerimaan (One-Stop Weigh & Tag + Riwayat) -->
                        <ul class="nav-sub-list">
                            <li>
                                <a href="index.php?page=timbang_cetak" id="sidebarSubPenerimaanTimbang" class="nav-sub-item <?= ($currentPage === 'timbang_cetak' && (!isset($_GET['tab']) || $_GET['tab'] === 'timbang_cetak' || $_GET['tab'] === 'massal' || $_GET['tab'] === 'scantimbang')) ? 'active' : '' ?>">
                                    <i data-lucide="scale" style="width: 14px; height: 14px; color: #8b5cf6;"></i>
                                    <span>Penerimaan & Timbang</span>
                                </a>
                            </li>
                            <li>
                                <a href="index.php?page=timbang_cetak&tab=riwayat_penerimaan" id="sidebarSubRiwayatPenerimaan" class="nav-sub-item <?= ($currentPage === 'timbang_cetak' && isset($_GET['tab']) && $_GET['tab'] === 'riwayat_penerimaan') ? 'active' : '' ?>" title="Daftar lengkap seluruh barcode dan timbangan penerimaan">
                                    <i data-lucide="clipboard-list" style="width: 14px; height: 14px; color: #8b5cf6;"></i>
                                    <span>Riwayat Penerimaan</span>
                                </a>
                            </li>
                            <li>
                                <a href="index.php?page=barang_datang" class="nav-sub-item <?= ($currentPage === 'barang_datang') ? 'active' : '' ?>" title="Barang yang diterima gudang dan total nilai pembelian">
                                    <i data-lucide="package-check" style="width: 14px; height: 14px; color: #8b5cf6;"></i>
                                    <span>Barang Datang</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-has-submenu <?= $isOutboundActive ? 'is-open' : '' ?>" id="menuPengeluaran" data-active-page="<?= $isOutboundActive ? '1' : '' ?>">
                        <a href="javascript:void(0)" class="nav-item <?= $isOutboundActive ? 'active' : '' ?>" onclick="toggleSidebarSubmenu('menuPengeluaran', event)" role="button" aria-expanded="<?= $isOutboundActive ? 'true' : 'false' ?>" title="Klik untuk menampilkan / menyembunyikan sub menu">
                            <div class="nav-item-icon icon-amber">
                                <i data-lucide="package-minus" style="width: 17px; height: 17px;"></i>
                            </div>
                            <div class="nav-item-text">
                                <span class="nav-item-title">Pengeluaran Barang ke Mitra</span>
                                <span class="nav-item-desc">Keluarkan stok pemasok ke mitra</span>
                            </div>
                            <div class="nav-item-chevron">
                                <i data-lucide="chevron-down" style="width: 15px; height: 15px;"></i>
                            </div>
                        </a>
                        <!-- Sub Menu Pengeluaran -->
                        <ul class="nav-sub-list">
                            <li>
                                <a href="index.php?page=pengeluaran&tab=scan" class="nav-sub-item <?= ($currentPage === 'pengeluaran' && (!isset($_GET['tab']) || $_GET['tab'] === 'scan')) ? 'active' : '' ?>">
                                    <i data-lucide="scan-barcode" style="width: 14px; height: 14px;"></i>
                                    <span>Scan Pengeluaran</span>
                                </a>
                            </li>
                            <li>
                                <a href="index.php?page=pengeluaran&tab=riwayat" class="nav-sub-item <?= ($currentPage === 'pengeluaran' && isset($_GET['tab']) && in_array($_GET['tab'], ['riwayat', 'semua_barang'])) ? 'active' : '' ?>" title="Arsip surat jalan dan rincian kemasan barang keluar">
                                    <i data-lucide="clipboard-list" style="width: 14px; height: 14px; color: #10b981;"></i>
                                    <span>Riwayat Pengeluaran</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!-- MENU UTAMA 3: STOK BARANG GUDANG & STOCK OPNAME -->
                    <li class="nav-has-submenu <?= $isStokGudangActive ? 'is-open' : '' ?>" id="menuStokGudang" data-active-page="<?= $isStokGudangActive ? '1' : '' ?>">
                        <a href="javascript:void(0)" class="nav-item <?= $isStokGudangActive ? 'active' : '' ?>" onclick="toggleSidebarSubmenu('menuStokGudang', event)" role="button" aria-expanded="<?= $isStokGudangActive ? 'true' : 'false' ?>" title="Klik untuk menampilkan / menyembunyikan sub menu">
                            <div class="nav-item-icon icon-cyan" style="background: rgba(6, 182, 212, 0.15); color: #0891b2;">
                                <i data-lucide="boxes" style="width: 17px; height: 17px;"></i>
                            </div>
                            <div class="nav-item-text">
                                <span class="nav-item-title">Stok Barang Gudang</span>
                                <span class="nav-item-desc">Sisa fisik & stock opname</span>
                            </div>
                            <div class="nav-item-chevron">
                                <i data-lucide="chevron-down" style="width: 15px; height: 15px;"></i>
                            </div>
                        </a>
                        <!-- Sub Menu Stok Gudang & Opname -->
                        <ul class="nav-sub-list">
                            <li>
                                <a href="index.php?page=timbang_cetak&tab=stok_gudang" id="sidebarSubSisaStok" class="nav-sub-item <?= ($currentPage === 'timbang_cetak' && isset($_GET['tab']) && $_GET['tab'] === 'stok_gudang') ? 'active' : '' ?>" title="Sisa persediaan fisik gudang utama setelah penerimaan">
                                    <i data-lucide="package" style="width: 14px; height: 14px; color: #0891b2;"></i>
                                    <span>Sisa Stok Gudang</span>
                                </a>
                            </li>
                            <li>
                                <a href="index.php?page=stok_opname" id="sidebarSubStockOpname" class="nav-sub-item <?= ($currentPage === 'stok_opname') ? 'active' : '' ?>" title="Stock opname barcode & audit fisik gudang">
                                    <i data-lucide="clipboard-check" style="width: 14px; height: 14px; color: #10b981;"></i>
                                    <span>Stock Opname</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>

                <!-- KEUANGAN & STOK -->
                <div class="nav-section-label" style="margin-top: 14px;">
                    <span>KEUANGAN & STOK</span>
                </div>
                <ul class="nav-list">
                    <!-- 2. AP AGING (UTANG USAHA) (SUBMENU) -->
                    <li class="nav-has-submenu <?= $isApAgingActive ? 'is-open' : '' ?>" id="menuApAging" data-active-page="<?= $isApAgingActive ? '1' : '' ?>">
                        <a href="javascript:void(0)" class="nav-item <?= $isApAgingActive ? 'active' : '' ?>" onclick="toggleSidebarSubmenu('menuApAging', event)" role="button" aria-expanded="<?= $isApAgingActive ? 'true' : 'false' ?>" title="Pelacakan umur utang dan pembayaran cicilan ke pemasok">
                            <div class="nav-item-icon icon-purple" style="background: rgba(139, 92, 246, 0.12); color: #8b5cf6;">
                                <i data-lucide="receipt" style="width: 17px; height: 17px;"></i>
                            </div>
                            <div class="nav-item-text">
                                <span class="nav-item-title" style="font-weight: 700;">AP Aging</span>
                                <span class="nav-item-desc">Umur utang & bayar pemasok</span>
                            </div>
                            <div class="nav-item-chevron">
                                <i data-lucide="chevron-down" style="width: 15px; height: 15px;"></i>
                            </div>
                        </a>
                        <ul class="nav-sub-list">
                            <li>
                                <a href="index.php?page=ap_aging&tab=matriks" class="nav-sub-item <?= ($isApAgingActive && ($currentTab === 'matriks' || empty($currentTab))) ? 'active' : '' ?>">
                                    <i data-lucide="bar-chart-3" style="width: 14px; height: 14px; color: #8b5cf6;"></i>
                                    <span>Ringkasan per Pemasok</span>
                                </a>
                            </li>
                            <li>
                                <a href="index.php?page=ap_aging&tab=riwayat_bayar" class="nav-sub-item <?= ($isApAgingActive && $currentTab === 'riwayat_bayar') ? 'active' : '' ?>">
                                    <i data-lucide="credit-card" style="width: 14px; height: 14px; color: #ea580c;"></i>
                                    <span>Riwayat Pembayaran</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- 1. AR AGING (PIUTANG USAHA) (SUBMENU) -->
                    <li class="nav-has-submenu <?= $isArAgingActive ? 'is-open' : '' ?>" id="menuArAging" data-active-page="<?= $isArAgingActive ? '1' : '' ?>">
                        <a href="javascript:void(0)" class="nav-item <?= $isArAgingActive ? 'active' : '' ?>" onclick="toggleSidebarSubmenu('menuArAging', event)" role="button" aria-expanded="<?= $isArAgingActive ? 'true' : 'false' ?>" title="Pelacakan umur piutang dan cicilan setoran mitra">
                            <div class="nav-item-icon icon-blue" style="background: rgba(37, 99, 235, 0.12); color: #2563eb;">
                                <i data-lucide="hand-coins" style="width: 17px; height: 17px;"></i>
                            </div>
                            <div class="nav-item-text">
                                <span class="nav-item-title" style="font-weight: 700;">AR Aging</span>
                                <span class="nav-item-desc">Umur piutang & cicilan setoran</span>
                            </div>
                            <div class="nav-item-chevron">
                                <i data-lucide="chevron-down" style="width: 15px; height: 15px;"></i>
                            </div>
                        </a>
                        <ul class="nav-sub-list">
                            <li>
                                <a href="index.php?page=ar_aging&tab=matriks" class="nav-sub-item <?= ($isArAgingActive && ($currentTab === 'matriks' || empty($currentTab))) ? 'active' : '' ?>">
                                    <i data-lucide="bar-chart-3" style="width: 14px; height: 14px; color: #2563eb;"></i>
                                    <span>Ringkasan per Mitra</span>
                                </a>
                            </li>
                            <li>
                                <a href="index.php?page=ar_aging&tab=riwayat_setoran" class="nav-sub-item <?= ($isArAgingActive && $currentTab === 'riwayat_setoran') ? 'active' : '' ?>">
                                    <i data-lucide="wallet" style="width: 14px; height: 14px; color: #f59e0b;"></i>
                                    <span>Riwayat Setoran</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="nav-has-submenu <?= $isPurchGroupActive ? 'is-open' : '' ?>" id="menuPurchase" data-active-page="<?= $isPurchGroupActive ? '1' : '' ?>">
                        <a href="javascript:void(0)" class="nav-item <?= $isPurchGroupActive ? 'active' : '' ?>" onclick="toggleSidebarSubmenu('menuPurchase', event)" role="button" aria-expanded="<?= $isPurchGroupActive ? 'true' : 'false' ?>">
                            <div class="nav-item-icon" style="background: #10b98122; color: #10b981;">
                                <i data-lucide="file-input" style="width: 17px; height: 17px;"></i>
                            </div>
                            <div class="nav-item-text">
                                <span class="nav-item-title" style="font-weight: 700;">Purchase Invoice</span>
                                <span class="nav-item-desc">Faktur dan riwayat harga beli</span>
                            </div>
                            <div class="nav-item-chevron">
                                <i data-lucide="chevron-down" style="width: 15px; height: 15px;"></i>
                            </div>
                        </a>
                        <ul class="nav-sub-list">
                            <li>
                                <a href="index.php?page=ap_aging&tab=invoices" class="nav-sub-item <?= $isPurchInvActive ? 'active' : '' ?>">
                                    <i data-lucide="file-input" style="width: 14px; height: 14px; color: #10b981;"></i>
                                    <span>Daftar Faktur</span>
                                </a>
                            </li>
                            <li>
                                <a href="index.php?page=purchase_book" class="nav-sub-item <?= ($currentPage === 'purchase_book') ? 'active' : '' ?>">
                                    <i data-lucide="book-marked" style="width: 14px; height: 14px; color: #10b981;"></i>
                                    <span>Purchase Book</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="nav-has-submenu <?= $isSalesGroupActive ? 'is-open' : '' ?>" id="menuSales" data-active-page="<?= $isSalesGroupActive ? '1' : '' ?>">
                        <a href="javascript:void(0)" class="nav-item <?= $isSalesGroupActive ? 'active' : '' ?>" onclick="toggleSidebarSubmenu('menuSales', event)" role="button" aria-expanded="<?= $isSalesGroupActive ? 'true' : 'false' ?>">
                            <div class="nav-item-icon" style="background: #2563eb22; color: #2563eb;">
                                <i data-lucide="file-output" style="width: 17px; height: 17px;"></i>
                            </div>
                            <div class="nav-item-text">
                                <span class="nav-item-title" style="font-weight: 700;">Sales Invoice</span>
                                <span class="nav-item-desc">Nota dan riwayat harga jual</span>
                            </div>
                            <div class="nav-item-chevron">
                                <i data-lucide="chevron-down" style="width: 15px; height: 15px;"></i>
                            </div>
                        </a>
                        <ul class="nav-sub-list">
                            <li>
                                <a href="index.php?page=ar_aging&tab=nota" class="nav-sub-item <?= $isSalesInvActive ? 'active' : '' ?>">
                                    <i data-lucide="file-output" style="width: 14px; height: 14px; color: #2563eb;"></i>
                                    <span>Daftar Nota</span>
                                </a>
                            </li>
                            <li>
                                <a href="index.php?page=sales_book" class="nav-sub-item <?= ($currentPage === 'sales_book') ? 'active' : '' ?>">
                                    <i data-lucide="book-marked" style="width: 14px; height: 14px; color: #2563eb;"></i>
                                    <span>Sales Book</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- LABA RUGI (SUBMENU) -->
                    <li class="nav-has-submenu <?= $isProfitActive ? 'is-open' : '' ?>" id="menuProfit" data-active-page="<?= $isProfitActive ? '1' : '' ?>">
                        <a href="javascript:void(0)" class="nav-item <?= $isProfitActive ? 'active' : '' ?>" onclick="toggleSidebarSubmenu('menuProfit', event)" role="button" aria-expanded="<?= $isProfitActive ? 'true' : 'false' ?>" title="Klik untuk menampilkan / menyembunyikan menu Laba Rugi">
                            <div class="nav-item-icon icon-green" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                                <i data-lucide="trending-up" style="width: 17px; height: 17px;"></i>
                            </div>
                            <div class="nav-item-text">
                                <span class="nav-item-title" style="font-weight: 700;">Laba Rugi</span>
                                <span class="nav-item-desc">Penjualan, HPP & biaya dari jurnal</span>
                            </div>
                            <div class="nav-item-chevron">
                                <i data-lucide="chevron-down" style="width: 15px; height: 15px;"></i>
                            </div>
                        </a>
                        <ul class="nav-sub-list">
                            <li>
                                <a href="index.php?page=coa" class="nav-sub-item <?= ($currentPage === 'coa') ? 'active' : '' ?>">
                                    <i data-lucide="book-open" style="width: 14px; height: 14px; color: #4f46e5;"></i>
                                    <span>COA</span>
                                </a>
                            </li>
                            <li>
                                <a href="index.php?page=profit_loss" class="nav-sub-item <?= ($currentPage === 'profit_loss' && $currentTab !== 'laba_sj' && $currentTab !== 'riwayat_sj') ? 'active' : '' ?>">
                                    <i data-lucide="scale" style="width: 14px; height: 14px; color: #8b5cf6;"></i>
                                    <span>Laba Rugi</span>
                                </a>
                            </li>
                            <li>
                                <a href="index.php?page=profit_loss&tab=laba_sj" class="nav-sub-item <?= ($currentPage === 'profit_loss' && ($currentTab === 'laba_sj' || $currentTab === 'riwayat_sj')) ? 'active' : '' ?>">
                                    <i data-lucide="receipt" style="width: 14px; height: 14px; color: #10b981;"></i>
                                    <span>Laba per Surat Jalan</span>
                                </a>
                            </li>
                            <li>
                                <a href="index.php?page=tutup_periode" class="nav-sub-item <?= $currentPage === 'tutup_periode' ? 'active' : '' ?>">
                                    <i data-lucide="lock" style="width: 14px; height: 14px; color: #64748b;"></i>
                                    <span>Tutup Periode</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- TRANSAKSI KAS & BANK -->
                    <li>
                        <a href="index.php?page=kas_bank" class="nav-item <?= $isKasActive ? 'active' : '' ?>" title="Biaya tunai/transfer, setoran modal, dan prive">
                            <div class="nav-item-icon icon-teal" style="background: rgba(16, 185, 129, 0.12); color: #059669;">
                                <i data-lucide="wallet" style="width: 17px; height: 17px;"></i>
                            </div>
                            <div class="nav-item-text">
                                <span class="nav-item-title" style="font-weight: 700;">Transaksi Kas &amp; Bank</span>
                                <span class="nav-item-desc">Biaya, modal, dan prive</span>
                            </div>
                        </a>
                    </li>

                    <!-- REKONSILIASI BANK (HARIAN) -->
                    <li>
                        <a href="index.php?page=rekonsiliasi_bank" class="nav-item <?= $isRekonActive ? 'active' : '' ?>" title="Audit harian total setoran mitra dengan uang masuk di bank">
                            <div class="nav-item-icon icon-teal" style="background: rgba(14, 165, 233, 0.12); color: #0284c7;">
                                <i data-lucide="scale" style="width: 17px; height: 17px;"></i>
                            </div>
                            <div class="nav-item-text">
                                <span class="nav-item-title" style="font-weight: 700;">Rekonsiliasi Bank</span>
                                <span class="nav-item-desc">Audit harian setoran vs bank</span>
                            </div>
                        </a>
                    </li>

                    <!-- 2. SALDO STOK GUDANG (SUBMENU) -->
                    <li class="nav-has-submenu <?= $isStokMitraActive ? 'is-open' : '' ?>" id="menuStokMitra" data-active-page="<?= $isStokMitraActive ? '1' : '' ?>">
                        <a href="javascript:void(0)" class="nav-item <?= $isStokMitraActive ? 'active' : '' ?>" onclick="toggleSidebarSubmenu('menuStokMitra', event)" role="button" aria-expanded="<?= $isStokMitraActive ? 'true' : 'false' ?>" title="Klik untuk menampilkan / menyembunyikan tabel saldo stok">
                            <div class="nav-item-icon icon-cyan">
                                <i data-lucide="layers" style="width: 17px; height: 17px;"></i>
                            </div>
                            <div class="nav-item-text">
                                <span class="nav-item-title">Saldo Stok Gudang</span>
                                <span class="nav-item-desc">Sisa barang fisik per mitra</span>
                            </div>
                            <div class="nav-item-chevron">
                                <i data-lucide="chevron-down" style="width: 15px; height: 15px;"></i>
                            </div>
                        </a>
                        <ul class="nav-sub-list">
                            <li>
                                <a href="index.php?page=stok_mitra&tab=rincian" class="nav-sub-item <?= ($isStokMitraActive && ($currentTab === 'rincian' || empty($currentTab))) ? 'active' : '' ?>">
                                    <i data-lucide="table" style="width: 14px; height: 14px; color: #059669;"></i>
                                    <span>Rincian Stok Barang Mitra</span>
                                </a>
                            </li>
                            <li>
                                <a href="index.php?page=stok_mitra&tab=matriks" class="nav-sub-item <?= ($isStokMitraActive && $currentTab === 'matriks') ? 'active' : '' ?>">
                                    <i data-lucide="grid" style="width: 14px; height: 14px; color: #3b82f6;"></i>
                                    <span>Matriks Stok Kategori</span>
                                </a>
                            </li>
                            <li>
                                <a href="index.php?page=stok_mitra&tab=ringkasan" class="nav-sub-item <?= ($isStokMitraActive && $currentTab === 'ringkasan') ? 'active' : '' ?>">
                                    <i data-lucide="credit-card" style="width: 14px; height: 14px; color: #8b5cf6;"></i>
                                    <span>Valuasi per Mitra</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-has-submenu <?= $isMasterActive ? 'is-open' : '' ?>" id="menuMaster" data-active-page="<?= $isMasterActive ? '1' : '' ?>">
                        <a href="javascript:void(0)" class="nav-item <?= $isMasterActive ? 'active' : '' ?>" onclick="toggleSidebarSubmenu('menuMaster', event)" role="button" aria-expanded="<?= $isMasterActive ? 'true' : 'false' ?>" title="Klik untuk menampilkan / menyembunyikan sub menu">
                            <div class="nav-item-icon icon-slate">
                                <i data-lucide="settings" style="width: 17px; height: 17px;"></i>
                            </div>
                            <div class="nav-item-text">
                                <span class="nav-item-title">Master & Pengaturan</span>
                                <span class="nav-item-desc">Data & API Accurate</span>
                            </div>
                            <div class="nav-item-chevron">
                                <i data-lucide="chevron-down" style="width: 15px; height: 15px;"></i>
                            </div>
                        </a>
                        <!-- Sub Menu Master & Pengaturan -->
                        <?php
                        $masterTabAktif = $_GET['tab'] ?? 'pemasok';
                        if ($masterTabAktif === 'harga') $masterTabAktif = 'harga_mitra';
                        if ($currentPage === 'accurate_sync') $masterTabAktif = 'accurate';
                        $masterMenu = [
                            ['pemasok',            'Pemasok Nelayan',       'truck',            '#f59e0b'],
                            ['vendor_non_pemasok', 'Vendor Non-Pemasok',    'store',            '#3b82f6'],
                            ['tipe_biaya',         'Tipe Biaya Operasional', 'receipt',         '#8b5cf6'],
                            ['harga_pemasok',      'Harga Pemasok',         'tag',              '#f59e0b'],
                            ['harga_mitra',        'Harga Mitra',           'badge-dollar-sign', '#10b981'],
                            ['mitra',              'Mitra Bisnis',          'building-2',       '#3b82f6'],
                            ['produk',             'Master Produk',         'boxes',            '#8b5cf6'],
                            ['rekening_bank',      'Rekening Bank',         'landmark',         '#0ea5e9'],
                            ['coa',                'Akun COA',              'book-open',        '#4f46e5'],
                            ['log_harga',          'Log Harga',             'history',          '#ef4444'],
                            ['accurate',           'Settingan API Accurate', 'key',             '#0284c7'],
                        ];
                        ?>
                        <ul class="nav-sub-list">
                            <?php foreach ($masterMenu as [$mTab, $mLabel, $mIkon, $mWarna]):
                                $mAktif = ($currentPage === 'master' || $currentPage === 'accurate_sync') && $masterTabAktif === $mTab; ?>
                            <li>
                                <a href="index.php?page=master&tab=<?= $mTab ?>" class="nav-sub-item <?= $mAktif ? 'active' : '' ?>">
                                    <i data-lucide="<?= $mIkon ?>" style="width: 14px; height: 14px; color: <?= $mWarna ?>;"></i>
                                    <span><?= $mLabel ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <li>
                        <a href="index.php?page=prd_viewer" class="nav-item <?= $currentPage === 'prd_viewer' ? 'active' : '' ?>" style="padding: 7px 11px;">
                            <div class="nav-item-icon icon-rose" style="width: 28px; height: 28px;">
                                <i data-lucide="help-circle" style="width: 15px; height: 15px;"></i>
                            </div>
                            <div class="nav-item-text">
                                <span class="nav-item-title" style="font-size: 12.5px;">Panduan Cara Pakai</span>
                            </div>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Footer Sidebar: Switcher Tema -->
            <div class="sidebar-footer">
                <button type="button" class="sidebar-theme-btn" id="themeToggle" title="Ganti Mode Gelap / Terang">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="sun" id="sunIcon" style="width: 16px; height: 16px; color: #f59e0b;"></i>
                        <i data-lucide="moon" id="moonIcon" style="width: 16px; height: 16px; color: #38bdf8; display: none;"></i>
                        <span id="themeToggleText">Mode Tampilan</span>
                    </div>
                    <span class="theme-badge" id="themeBadge">Terang</span>
                </button>
            </div>
        </aside>

        <!-- AREA KONTEN KANAN -->
        <div class="app-main-container">
            <!-- TOPBAR ATAS RINGKAS -->
            <header class="app-topbar no-print">
                <div class="topbar-left">
                    <!-- Tombol Hamburger Mobile Menu -->
                    <button type="button" class="btn-hamburger" id="btnSidebarToggle" onclick="toggleSidebar(true)" title="Buka Menu Navigasi">
                        <i data-lucide="menu" style="width: 18px; height: 18px;"></i>
                        <span class="btn-hamburger-text">MENU</span>
                    </button>

                </div>

                <div class="topbar-right">
                    <!-- User Profile & Role Switcher Dropdown -->
                    <div class="topbar-user-dropdown-wrapper" id="userMenuWrapper">
                        <button type="button" class="topbar-user-btn" id="btnUserMenu" onclick="toggleUserDropdown(event)" title="Akun: <?= htmlspecialchars($userDisplayName) ?>">
                            <div class="topbar-user-avatar <?= $userAvatarClass ?>"><?= $userInitials ?></div>
                            <div class="topbar-user-info-text">
                                <span class="topbar-user-name"><?= htmlspecialchars(explode(' ', $userDisplayName)[0]) ?></span>
                                <span class="topbar-role-badge <?= $userBadgeClass ?>"><?= $userRoleBadge ?></span>
                            </div>
                            <i data-lucide="chevron-down" style="width: 14px; height: 14px; color: var(--text-muted); margin-left: 2px;"></i>
                        </button>

                        <div class="user-dropdown-menu" id="userDropdownMenu">
                            <div class="user-dropdown-header">
                                <div class="user-dropdown-name"><?= htmlspecialchars($userDisplayName) ?></div>
                                <div class="user-dropdown-role">Role: <span class="topbar-role-badge <?= $userBadgeClass ?>"><?= $userRoleBadge ?></span></div>
                            </div>
                            <div class="user-dropdown-divider"></div>
                            <a href="index.php?action=logout" class="user-dropdown-item dropdown-logout-btn">
                                <i data-lucide="log-out" style="width: 15px; height: 15px; color: #ef4444;"></i>
                                <span style="color: #ef4444; font-weight: 700;">Keluar / Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="main-wrapper">
