<?php
/**
 * Halaman AP Aging (Utang Usaha) & Alokasi Pembayaran Kas Keluar ke Pemasok
 * Bagian dari Modul Finance & Kas Keluar WMS
 */

use App\Core\Helper;
use App\Models\Pemasok;
use App\Models\Utang;

$pageTitle = 'AP Aging & Utang Pemasok';
$activeTab = $_GET['tab'] ?? 'matriks';
if (!in_array($activeTab, ['matriks', 'sj_masuk', 'riwayat_bayar'])) {
    $activeTab = 'matriks';
}

$pemasokList = Pemasok::getAll();
$kpi = Utang::getSummaryKpi();
$agingPemasok = Utang::getAgingSummaryPerPemasok();

// Filter untuk Tab SJ Masuk
$selectedPemasokId = (int)($_GET['pemasok_id'] ?? 0);
$selectedStatus    = trim($_GET['status'] ?? 'SEMUA');
$searchKeyword     = trim($_GET['search'] ?? '');

$sjList = Utang::getSuratJalanList([
    'pemasok_id' => $selectedPemasokId,
    'status'     => $selectedStatus,
    'search'     => $searchKeyword
]);

// Data untuk Tab Riwayat Kas Keluar
$riwayatPayments = Utang::getAllPayments([
    'pemasok_id' => $selectedPemasokId,
    'search'     => $searchKeyword
]);

require_once __DIR__ . '/layout/header.php';
?>

<div class="content-wrapper">

    <!-- PAGE HEADER -->
    <div class="page-header no-print">
        <div>
            <h1 class="page-title">AP Aging & Utang Usaha Pemasok</h1>
            <p class="page-subtitle">Pemantauan kewajiban tagihan pembayaran atas penerimaan barang (Surat Jalan Masuk) dan cicilan kas keluar pemasok</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button type="button" class="btn btn-primary" onclick="openPaymentModal()" style="display: flex; align-items: center; gap: 6px; font-weight: 700;">
                <i data-lucide="plus-circle" style="width: 16px; height: 16px;"></i>
                <span>Catat Pembayaran Kas Keluar</span>
            </button>
            <button type="button" class="btn btn-outline" onclick="window.print()" style="display: flex; align-items: center; gap: 6px;">
                <i data-lucide="printer" style="width: 16px; height: 16px;"></i>
                <span>Cetak Rekap</span>
            </button>
        </div>
    </div>

    <!-- 4 KARTU KPI AP AGING KEUANGAN -->
    <div class="grid grid-cols-4 no-print" style="gap: 16px; margin-bottom: 22px;">
        <!-- Card 1: Total Utang Terbit -->
        <div class="card" style="padding: 16px; border-left: 4px solid #6366f1; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Total Nilai Tagihan Masuk</span>
                <div style="background: rgba(99, 102, 241, 0.12); color: #6366f1; padding: 6px; border-radius: 8px;">
                    <i data-lucide="receipt" style="width: 17px; height: 17px;"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: #3730a3; line-height: 1.1;">
                <?= Helper::formatRupiah($kpi['total_utang_terbit']) ?>
            </div>
            <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 6px;">
                Dari total <strong><?= number_format($kpi['total_sj']) ?></strong> Surat Jalan penerimaan
            </div>
        </div>

        <!-- Card 2: Total Pembayaran Kas Keluar -->
        <div class="card" style="padding: 16px; border-left: 4px solid #10b981; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Total Kas Dibayarkan</span>
                <div style="background: rgba(16, 185, 129, 0.12); color: #10b981; padding: 6px; border-radius: 8px;">
                    <i data-lucide="credit-card" style="width: 17px; height: 17px;"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: #047857; line-height: 1.1;">
                <?= Helper::formatRupiah($kpi['total_pembayaran_keluar']) ?>
            </div>
            <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 6px;">
                Tercatat <strong><?= number_format($kpi['total_transaksi_bayar']) ?></strong> kali pencairan kas / bank
            </div>
        </div>

        <!-- Card 3: Sisa Utang Berjalan -->
        <div class="card" style="padding: 16px; border-left: 4px solid #f59e0b; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Sisa Utang Belum Lunas</span>
                <div style="background: rgba(245, 158, 11, 0.12); color: #f59e0b; padding: 6px; border-radius: 8px;">
                    <i data-lucide="hourglass" style="width: 17px; height: 17px;"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: #b45309; line-height: 1.1;">
                <?= Helper::formatRupiah($kpi['sisa_utang_aktif']) ?>
            </div>
            <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 6px;">
                Lancar (0-7 hari): <strong><?= Helper::formatRupiah($kpi['aging_0_7']) ?></strong>
            </div>
        </div>

        <!-- Card 4: Utang Lewat Tempo (>14 Hari) -->
        <div class="card" style="padding: 16px; border-left: 4px solid #ef4444; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Utang Jatuh Tempo (&gt;14 Hari)</span>
                <div style="background: rgba(239, 68, 68, 0.12); color: #ef4444; padding: 6px; border-radius: 8px;">
                    <i data-lucide="alert-circle" style="width: 17px; height: 17px;"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: #b91c1c; line-height: 1.1;">
                <?= Helper::formatRupiah($kpi['total_overdue']) ?>
            </div>
            <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 6px;">
                Macet (&gt;30 hari): <strong><?= Helper::formatRupiah($kpi['aging_gt_30']) ?></strong>
            </div>
        </div>
    </div>

    <!-- TAB NAVIGASI -->
    <div class="tabs-nav no-print" style="margin-bottom: 18px; border-bottom: 2px solid #e2e8f0; display: flex; gap: 8px;">
        <a href="index.php?page=ap_aging&tab=matriks" class="tab-btn <?= $activeTab === 'matriks' ? 'active' : '' ?>" style="padding: 10px 18px; text-decoration: none; font-weight: 700; font-size: 13.5px; color: <?= $activeTab === 'matriks' ? 'var(--primary)' : 'var(--text-muted)' ?>; border-bottom: <?= $activeTab === 'matriks' ? '3px solid var(--primary)' : '3px solid transparent' ?>; display: inline-flex; align-items: center; gap: 8px;">
            <i data-lucide="bar-chart-3" style="width: 16px; height: 16px;"></i>
            <span>1. Matriks Umur Utang per Pemasok</span>
        </a>
        <a href="index.php?page=ap_aging&tab=sj_masuk" class="tab-btn <?= $activeTab === 'sj_masuk' ? 'active' : '' ?>" style="padding: 10px 18px; text-decoration: none; font-weight: 700; font-size: 13.5px; color: <?= $activeTab === 'sj_masuk' ? 'var(--primary)' : 'var(--text-muted)' ?>; border-bottom: <?= $activeTab === 'sj_masuk' ? '3px solid var(--primary)' : '3px solid transparent' ?>; display: inline-flex; align-items: center; gap: 8px;">
            <i data-lucide="truck" style="width: 16px; height: 16px;"></i>
            <span>2. Pelacakan Surat Jalan Masuk</span>
        </a>
        <a href="index.php?page=ap_aging&tab=riwayat_bayar" class="tab-btn <?= $activeTab === 'riwayat_bayar' ? 'active' : '' ?>" style="padding: 10px 18px; text-decoration: none; font-weight: 700; font-size: 13.5px; color: <?= $activeTab === 'riwayat_bayar' ? 'var(--primary)' : 'var(--text-muted)' ?>; border-bottom: <?= $activeTab === 'riwayat_bayar' ? '3px solid var(--primary)' : '3px solid transparent' ?>; display: inline-flex; align-items: center; gap: 8px;">
            <i data-lucide="credit-card" style="width: 16px; height: 16px;"></i>
            <span>3. Buku Kas Keluar Pemasok</span>
        </a>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 1: MATRIKS UMUR UTANG PER PEMASOK (AGING BUCKETS)                    -->
    <!-- ========================================================================= -->
    <?php if ($activeTab === 'matriks'): ?>
        <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 class="card-title" style="margin: 0; font-size: 16px;">Rekapitulasi Umur Utang Pemasok & Nelayan</h2>
                    <p class="card-description" style="margin: 2px 0 0 0; font-size: 12.5px;">Klasifikasi sisa kewajiban pembayaran per pemasok berdasarkan periode hari berjalan (Current, 8-14 hari, 15-30 hari, &gt;30 hari)</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">No</th>
                            <th style="min-width: 180px;">Pemasok / Supplier</th>
                            <th style="width: 100px; text-align: center;">SJ Terbuka</th>
                            <th style="text-align: right; min-width: 130px;">Total Tagihan</th>
                            <th style="text-align: right; min-width: 130px;">Total Dibayar</th>
                            <th style="text-align: right; min-width: 135px; background: #fffbeb;">Sisa Utang</th>
                            <th style="text-align: right; min-width: 110px; color: #15803d;">0 - 7 Hari</th>
                            <th style="text-align: right; min-width: 110px; color: #b45309;">8 - 14 Hari</th>
                            <th style="text-align: right; min-width: 110px; color: #c2410c;">15 - 30 Hari</th>
                            <th style="text-align: right; min-width: 110px; color: #b91c1c;">&gt; 30 Hari</th>
                            <th class="no-print" style="width: 100px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        $totTagihan = 0.0;
                        $totBayar   = 0.0;
                        $totSisa    = 0.0;
                        $tot0to7    = 0.0;
                        $tot8to14   = 0.0;
                        $tot15to30  = 0.0;
                        $totGt30    = 0.0;

                        foreach ($agingPemasok as $p): 
                            $totTagihan += $p['total_tagihan'];
                            $totBayar   += $p['total_dibayar'];
                            $totSisa    += $p['sisa_utang'];
                            $tot0to7    += $p['aging_0_7'];
                            $tot8to14   += $p['aging_8_14'];
                            $tot15to30  += $p['aging_15_30'];
                            $totGt30    += $p['aging_gt_30'];
                        ?>
                        <tr>
                            <td style="text-align: center; color: var(--text-muted);"><?= $no++ ?></td>
                            <td>
                                <strong style="color: var(--text-main);">[<?= htmlspecialchars($p['kode_pemasok']) ?>] <?= htmlspecialchars($p['nama_pemasok']) ?></strong>
                                <div style="font-size: 11px; color: var(--text-muted); margin-top: 1px;">
                                    PIC: <?= htmlspecialchars($p['pic_pemasok'] ?: '-') ?> <?= !empty($p['telepon_pemasok']) ? '• ' . htmlspecialchars($p['telepon_pemasok']) : '' ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($p['sj_terbuka'] > 0): ?>
                                    <span style="font-weight: 800; color: #b45309;"><?= $p['sj_terbuka'] ?> SJ</span>
                                <?php else: ?>
                                    <span style="color: #10b981; font-weight: 700;">Lunas</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px;">
                                <?= Helper::formatRupiah($p['total_tagihan']) ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: #047857;">
                                <?= Helper::formatRupiah($p['total_dibayar']) ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 800; background: #fffbeb; color: <?= $p['sisa_utang'] > 0 ? '#b45309' : '#047857' ?>;">
                                <?= Helper::formatRupiah($p['sisa_utang']) ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12px; color: <?= $p['aging_0_7'] > 0 ? '#15803d' : '#94a3b8' ?>;">
                                <?= $p['aging_0_7'] > 0 ? Helper::formatRupiah($p['aging_0_7']) : '-' ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12px; color: <?= $p['aging_8_14'] > 0 ? '#b45309' : '#94a3b8' ?>;">
                                <?= $p['aging_8_14'] > 0 ? Helper::formatRupiah($p['aging_8_14']) : '-' ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12px; color: <?= $p['aging_15_30'] > 0 ? '#c2410c' : '#94a3b8' ?>;">
                                <?= $p['aging_15_30'] > 0 ? Helper::formatRupiah($p['aging_15_30']) : '-' ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: <?= $p['aging_gt_30'] > 0 ? '800' : 'normal' ?>; color: <?= $p['aging_gt_30'] > 0 ? '#b91c1c' : '#94a3b8' ?>;">
                                <?= $p['aging_gt_30'] > 0 ? Helper::formatRupiah($p['aging_gt_30']) : '-' ?>
                            </td>
                            <td class="no-print" style="text-align: center;">
                                <?php if ($p['sisa_utang'] > 0): ?>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="openPaymentModal(<?= $p['pemasok_id'] ?>, '<?= htmlspecialchars($p['nama_pemasok'], ENT_QUOTES) ?>')" style="padding: 4px 12px; font-size: 11.5px; font-weight: 700;" title="Bayar utang ke pemasok ini">
                                        Bayar
                                    </button>
                                <?php else: ?>
                                    <span style="display: inline-block; padding: 3px 10px; background: #d1fae5; color: #065f46; font-weight: 700; font-size: 11px; border-radius: 4px;">
                                        Lunas
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f8fafc; font-weight: 800;">
                            <td colspan="3" style="text-align: right; text-transform: uppercase;">Total Keseluruhan Pemasok:</td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace;"><?= Helper::formatRupiah($totTagihan) ?></td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; color: #047857;"><?= Helper::formatRupiah($totBayar) ?></td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; background: #fef3c7; color: #b45309;"><?= Helper::formatRupiah($totSisa) ?></td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; color: #15803d;"><?= Helper::formatRupiah($tot0to7) ?></td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; color: #b45309;"><?= Helper::formatRupiah($tot8to14) ?></td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; color: #c2410c;"><?= Helper::formatRupiah($tot15to30) ?></td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; color: #b91c1c;"><?= Helper::formatRupiah($totGt30) ?></td>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- TAB 2: PELACAKAN SURAT JALAN MASUK (INBOUND GOODS RECEIPT NOTES)          -->
    <!-- ========================================================================= -->
    <?php if ($activeTab === 'sj_masuk'): ?>
        <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 class="card-title" style="margin: 0; font-size: 16px;">Daftar Surat Jalan Penerimaan & Status Pelunasan</h2>
                    <p class="card-description" style="margin: 2px 0 0 0; font-size: 12.5px;">Rincian tagihan per surat jalan masuk dari supplier beserta umur utang dan riwayat cicilan</p>
                </div>
            </div>

            <!-- FILTER BAR -->
            <form method="GET" action="index.php" class="no-print" style="background: #f8fafc; padding: 14px 16px; border-radius: 8px; margin-bottom: 18px; border: 1px solid #e2e8f0; display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                <input type="hidden" name="page" value="ap_aging">
                <input type="hidden" name="tab" value="sj_masuk">

                <div style="flex: 1; min-width: 180px;">
                    <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">Pemasok / Supplier</label>
                    <select name="pemasok_id" class="form-control" style="font-size: 12.5px;" onchange="this.form.submit()">
                        <option value="0">-- Seluruh Pemasok --</option>
                        <?php foreach ($pemasokList as $pm): ?>
                            <option value="<?= $pm['id'] ?>" <?= $selectedPemasokId === (int)$pm['id'] ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($pm['kode_pemasok']) ?>] <?= htmlspecialchars($pm['nama_pemasok']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="width: 160px;">
                    <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">Status Bayar</label>
                    <select name="status" class="form-control" style="font-size: 12.5px;" onchange="this.form.submit()">
                        <option value="SEMUA" <?= $selectedStatus === 'SEMUA' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="BELUM_LUNAS" <?= $selectedStatus === 'BELUM_LUNAS' ? 'selected' : '' ?>>Belum Lunas (Sisa &gt; 0)</option>
                        <option value="BELUM_BAYAR" <?= $selectedStatus === 'BELUM_BAYAR' ? 'selected' : '' ?>>Belum Pernah Dibayar</option>
                        <option value="PARSIAL" <?= $selectedStatus === 'PARSIAL' ? 'selected' : '' ?>>Cicilan / Parsial</option>
                        <option value="LUNAS" <?= $selectedStatus === 'LUNAS' ? 'selected' : '' ?>>Lunas 100%</option>
                    </select>
                </div>

                <div style="flex: 1.2; min-width: 200px;">
                    <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">Cari No. SJ / Nama</label>
                    <input type="text" name="search" class="form-control" placeholder="Ketik No SJ / Pemasok..." value="<?= htmlspecialchars($searchKeyword) ?>" style="font-size: 12.5px;">
                </div>

                <div style="display: flex; gap: 6px;">
                    <button type="submit" class="btn btn-primary" style="font-size: 12px; padding: 8px 14px;">
                        <i data-lucide="filter" style="width: 14px; height: 14px;"></i>
                        <span>Filter</span>
                    </button>
                    <?php if ($selectedPemasokId > 0 || $selectedStatus !== 'SEMUA' || !empty($searchKeyword)): ?>
                        <a href="index.php?page=ap_aging&tab=sj_masuk" class="btn btn-outline" style="font-size: 12px; padding: 8px 12px;">Reset</a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center;">No</th>
                            <th style="min-width: 160px;">No. Surat Jalan</th>
                            <th style="width: 105px;">Tgl Masuk</th>
                            <th style="min-width: 170px;">Pemasok</th>
                            <th style="text-align: right; min-width: 125px;">Total Tagihan</th>
                            <th style="text-align: right; min-width: 125px;">Total Dibayar</th>
                            <th style="text-align: right; min-width: 130px; background: #fffbeb;">Sisa Utang</th>
                            <th style="width: 100px; text-align: center;">Umur Utang</th>
                            <th style="width: 110px; text-align: center;">Status Bayar</th>
                            <th class="no-print" style="width: 130px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sjList)): ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 32px; color: var(--text-muted);">
                                    Tidak ada data Surat Jalan Penerimaan yang sesuai dengan kriteria filter.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $no = 1;
                            foreach ($sjList as $sj): 
                                $umur = $sj['umur_hari'];
                                $umurLabel = '0-7 Hari';
                                $umurColor = '#15803d';
                                if ($umur > 30) {
                                    $umurLabel = '> 30 Hari';
                                    $umurColor = '#b91c1c';
                                } elseif ($umur > 14) {
                                    $umurLabel = '15-30 Hari';
                                    $umurColor = '#c2410c';
                                } elseif ($umur > 7) {
                                    $umurLabel = '8-14 Hari';
                                    $umurColor = '#b45309';
                                }
                            ?>
                            <tr>
                                <td style="text-align: center; color: var(--text-muted);"><?= $no++ ?></td>
                                <td>
                                    <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--text-main);">
                                        <?= htmlspecialchars($sj['no_surat_jalan']) ?>
                                    </strong>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                        <?= number_format($sj['total_box']) ?> Box • <?= Helper::formatKg($sj['total_kg']) ?> Kg
                                    </div>
                                </td>
                                <td style="font-size: 12px; color: var(--text-main);">
                                    <?= date('d/m/Y', strtotime($sj['tanggal_masuk'])) ?>
                                    <div style="font-size: 10.5px; color: var(--text-muted);"><?= date('H:i', strtotime($sj['tanggal_masuk'])) ?> WIB</div>
                                </td>
                                <td>
                                    <strong style="color: var(--text-main);">[<?= htmlspecialchars($sj['kode_pemasok']) ?>] <?= htmlspecialchars($sj['nama_pemasok']) ?></strong>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($sj['pic_pemasok'] ?: '-') ?></div>
                                </td>
                                <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px;">
                                    <?= Helper::formatRupiah($sj['total_nilai_beli']) ?>
                                </td>
                                <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: #047857;">
                                    <?= Helper::formatRupiah($sj['total_bayar']) ?>
                                    <?php if ($sj['jml_kali_bayar'] > 0): ?>
                                        <div style="font-size: 10.5px; color: var(--text-muted);"><?= $sj['jml_kali_bayar'] ?>x cicilan</div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 800; background: #fffbeb; color: <?= $sj['sisa_utang'] > 0 ? '#b45309' : '#047857' ?>;">
                                    <?= Helper::formatRupiah($sj['sisa_utang']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($sj['sisa_utang'] > 0): ?>
                                        <div style="font-weight: 800; font-size: 13px; color: <?= $umurColor ?>;">
                                            <?= $umur ?> Hari
                                        </div>
                                        <div style="font-size: 10px; color: <?= $umurColor ?>; text-transform: uppercase; font-weight: 700;">
                                            <?= $umurLabel ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #10b981; font-weight: 700; font-size: 12px;">Selesai</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($sj['status_bayar'] === 'LUNAS'): ?>
                                        <span style="font-weight: 800; font-size: 11px; color: #047857; background: #d1fae5; padding: 2px 8px; border-radius: 4px;">LUNAS</span>
                                    <?php elseif ($sj['status_bayar'] === 'PARSIAL'): ?>
                                        <span style="font-weight: 800; font-size: 11px; color: #b45309; background: #fef3c7; padding: 2px 8px; border-radius: 4px;">PARSIAL</span>
                                    <?php else: ?>
                                        <span style="font-weight: 800; font-size: 11px; color: #b91c1c; background: #fee2e2; padding: 2px 8px; border-radius: 4px;">BELUM BAYAR</span>
                                    <?php endif; ?>
                                </td>
                                <td class="no-print" style="text-align: center;">
                                    <div style="display: flex; gap: 4px; justify-content: center;">
                                        <button type="button" class="btn btn-outline btn-sm" onclick="viewSjHistory('<?= htmlspecialchars($sj['no_surat_jalan'], ENT_QUOTES) ?>', <?= $sj['total_nilai_beli'] ?>, <?= $sj['sisa_utang'] ?>)" style="padding: 4px 8px; font-size: 11px;" title="Lihat riwayat pembayaran surat jalan ini">
                                            Riwayat (<?= $sj['jml_kali_bayar'] ?>)
                                        </button>
                                        <?php if ($sj['sisa_utang'] > 0): ?>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="openPaymentModal(<?= $sj['pemasok_id'] ?>, '<?= htmlspecialchars($sj['nama_pemasok'], ENT_QUOTES) ?>', '<?= htmlspecialchars($sj['no_surat_jalan'], ENT_QUOTES) ?>', <?= $sj['sisa_utang'] ?>)" style="padding: 4px 8px; font-size: 11px;" title="Bayar utang surat jalan ini">
                                                Bayar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- TAB 3: BUKU KAS KELUAR PEMASOK (CASH DISBURSEMENTS LOG)                   -->
    <!-- ========================================================================= -->
    <?php if ($activeTab === 'riwayat_bayar'): ?>
        <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 class="card-title" style="margin: 0; font-size: 16px;">Buku Kas Pengeluaran Pembayaran Pemasok</h2>
                    <p class="card-description" style="margin: 2px 0 0 0; font-size: 12.5px;">Riwayat seluruh transaksi uang keluar kepada supplier / nelayan (tunai / mutasi bank) dan alokasi Surat Jalan yang dilunasi</p>
                </div>
            </div>

            <!-- FILTER BAR -->
            <form method="GET" action="index.php" class="no-print" style="background: #f8fafc; padding: 14px 16px; border-radius: 8px; margin-bottom: 18px; border: 1px solid #e2e8f0; display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                <input type="hidden" name="page" value="ap_aging">
                <input type="hidden" name="tab" value="riwayat_bayar">

                <div style="flex: 1; min-width: 180px;">
                    <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">Pemasok</label>
                    <select name="pemasok_id" class="form-control" style="font-size: 12.5px;" onchange="this.form.submit()">
                        <option value="0">-- Seluruh Pemasok --</option>
                        <?php foreach ($pemasokList as $pm): ?>
                            <option value="<?= $pm['id'] ?>" <?= $selectedPemasokId === (int)$pm['id'] ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($pm['kode_pemasok']) ?>] <?= htmlspecialchars($pm['nama_pemasok']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="flex: 1.5; min-width: 220px;">
                    <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">Cari No. Bukti / Keterangan</label>
                    <input type="text" name="search" class="form-control" placeholder="No. Bukti / Catatan..." value="<?= htmlspecialchars($searchKeyword) ?>" style="font-size: 12.5px;">
                </div>

                <div style="display: flex; gap: 6px;">
                    <button type="submit" class="btn btn-primary" style="font-size: 12px; padding: 8px 14px;">
                        <i data-lucide="filter" style="width: 14px; height: 14px;"></i>
                        <span>Filter</span>
                    </button>
                    <?php if ($selectedPemasokId > 0 || !empty($searchKeyword)): ?>
                        <a href="index.php?page=ap_aging&tab=riwayat_bayar" class="btn btn-outline" style="font-size: 12px; padding: 8px 12px;">Reset</a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center;">No</th>
                            <th style="width: 140px;">No. Bukti Kas Keluar</th>
                            <th style="width: 110px;">Tanggal Bayar</th>
                            <th style="min-width: 160px;">Pemasok Penerima</th>
                            <th style="width: 120px; text-align: center;">Metode Bayar</th>
                            <th style="text-align: right; width: 140px;">Jumlah Kas Keluar</th>
                            <th style="min-width: 200px;">Surat Jalan Terpotong</th>
                            <th style="width: 130px;">Petugas / Kasir</th>
                            <th style="min-width: 150px;">Keterangan / Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($riwayatPayments)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 32px; color: var(--text-muted);">
                                    Belum ada transaksi pengeluaran pembayaran utang yang tercatat.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $no = 1;
                            $totKasKeluar = 0.0;
                            foreach ($riwayatPayments as $p): 
                                $totKasKeluar += (float)$p['jumlah_bayar'];
                            ?>
                            <tr>
                                <td style="text-align: center; color: var(--text-muted);"><?= $no++ ?></td>
                                <td>
                                    <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: #1e3a8a;">
                                        <?= htmlspecialchars($p['no_bukti_keluar']) ?>
                                    </strong>
                                </td>
                                <td style="font-size: 12px;">
                                    <?= date('d/m/Y', strtotime($p['tanggal_bayar'])) ?>
                                </td>
                                <td>
                                    <strong style="color: var(--text-main);">[<?= htmlspecialchars($p['kode_pemasok']) ?>] <?= htmlspecialchars($p['nama_pemasok']) ?></strong>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($p['metode_bayar'] === 'TRANSFER_BANK'): ?>
                                        <span style="display: inline-block; padding: 2px 8px; background: #e0f2fe; color: #0369a1; font-weight: 700; font-size: 11px; border-radius: 4px;">
                                            TRANSFER
                                        </span>
                                        <?php if (!empty($p['bank_sumber'])): ?>
                                            <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;"><?= htmlspecialchars($p['bank_sumber']) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 2px 8px; background: #ecfdf5; color: #047857; font-weight: 700; font-size: 11px; border-radius: 4px;">
                                            TUNAI
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 800; color: #047857;">
                                    <?= Helper::formatRupiah($p['jumlah_bayar']) ?>
                                </td>
                                <td>
                                    <?php if ($p['jml_sj_terpotong'] > 0): ?>
                                        <div style="font-size: 11.5px; color: var(--text-main); font-family: 'JetBrains Mono', monospace;">
                                            <?= htmlspecialchars($p['daftar_sj_terpotong'] ?: '-') ?>
                                        </div>
                                        <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 1px;">
                                            <?= $p['jml_sj_terpotong'] ?> Surat Jalan terpotong
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 11px; font-style: italic;">Saldo titipan / Tidak ada SJ</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 11.5px; color: var(--text-muted);">
                                    <?= htmlspecialchars($p['nama_kasir'] ?: 'Petugas Gudang') ?>
                                </td>
                                <td style="font-size: 11.5px; color: var(--text-muted);">
                                    <?= htmlspecialchars($p['keterangan'] ?: '-') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($riwayatPayments)): ?>
                    <tfoot>
                        <tr style="background: #f8fafc; font-weight: 800;">
                            <td colspan="5" style="text-align: right; text-transform: uppercase;">Total Kas Keluar Terbuku:</td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; color: #047857;"><?= Helper::formatRupiah($totKasKeluar) ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- ========================================================================= -->
<!-- MODAL 1: CATAT PEMBAYARAN KAS KELUAR KE PEMASOK                          -->
<!-- ========================================================================= -->
<div id="modalCatatBayarPemasok" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 680px; margin: auto; padding: 24px; box-shadow: var(--shadow-lg); border-radius: 12px; background: #fff;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 18px;">
            <div>
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #1e293b;">Catat Pembayaran Kas Keluar ke Pemasok</h3>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted);">Bukukan pengeluaran uang tunai atau mutasi bank untuk melunasi tagihan penerimaan barang</p>
            </div>
            <button type="button" onclick="closePaymentModal()" style="border: none; background: transparent; font-size: 20px; color: #64748b; cursor: pointer; padding: 4px;">&times;</button>
        </div>

        <form id="formCatatBayarPemasok" onsubmit="submitPaymentAp(event)">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                <!-- Pemasok -->
                <div style="grid-column: span 2;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">Pemasok / Supplier Penerima <span style="color: #ef4444;">*</span></label>
                    <select id="payPemasokId" name="pemasok_id" class="form-control" required onchange="loadUnpaidSjForPemasok(this.value)">
                        <option value="">-- Pilih Pemasok / Supplier --</option>
                        <?php foreach ($pemasokList as $pm): ?>
                            <option value="<?= $pm['id'] ?>">[<?= htmlspecialchars($pm['kode_pemasok']) ?>] <?= htmlspecialchars($pm['nama_pemasok']) ?> (<?= htmlspecialchars($pm['pic_nama'] ?: '-') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tanggal Bayar -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">Tanggal Pembayaran <span style="color: #ef4444;">*</span></label>
                    <input type="date" id="payTanggal" name="tanggal_bayar" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <!-- Metode Bayar -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">Metode Pembayaran <span style="color: #ef4444;">*</span></label>
                    <select id="payMetode" name="metode_bayar" class="form-control" required onchange="toggleMetodeBayar(this.value)">
                        <option value="TRANSFER_BANK">Transfer Bank</option>
                        <option value="TUNAI">Uang Tunai (Kasbon / Cash)</option>
                    </select>
                </div>

                <!-- Bank Sumber -->
                <div id="wrapperBankSumber">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">Rekening Bank Sumber (Pengirim)</label>
                    <input type="text" id="payBankSumber" name="bank_sumber" class="form-control" placeholder="Contoh: BCA 8820192837 a/n PT WMS">
                </div>

                <!-- No Rekening Tujuan -->
                <div id="wrapperNoRekTujuan">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">No. Rekening Tujuan Pemasok</label>
                    <input type="text" id="payNoRekTujuan" name="no_rekening_tujuan" class="form-control" placeholder="Contoh: Mandiri 1320019283 a/n Udin">
                </div>

                <!-- Jumlah Bayar -->
                <div style="grid-column: span 2;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">Jumlah Uang Dibayarkan (Rp) <span style="color: #ef4444;">*</span></label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 700; color: #64748b;">Rp</span>
                        <input type="number" id="payJumlah" name="jumlah_bayar" class="form-control" placeholder="0" min="1" step="any" required style="padding-left: 38px; font-size: 15px; font-weight: 800; font-family: 'JetBrains Mono', monospace;" oninput="updatePaymentPreview()">
                    </div>
                </div>

                <!-- Mode Alokasi -->
                <div style="grid-column: span 2;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">Aturan Alokasi Surat Jalan</label>
                    <div style="display: flex; gap: 16px;">
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 12.5px; cursor: pointer;">
                            <input type="radio" name="mode_alokasi" value="FIFO" checked onchange="toggleModeAlokasi(this.value)">
                            <strong>FIFO (Otomatis potong SJ tertua lebih dulu)</strong>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 12.5px; cursor: pointer;">
                            <input type="radio" name="mode_alokasi" value="MANUAL" onchange="toggleModeAlokasi(this.value)">
                            <span>Manual (Tentukan per Surat Jalan)</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- PREVIEW DAFTAR SURAT JALAN BELUM LUNAS -->
            <div id="wrapperUnpaidSj" style="margin-top: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 12px; font-weight: 800; color: #334155;">Surat Jalan Terbuka Milik Pemasok</span>
                    <span id="badgeTotalUnpaid" style="font-size: 11px; font-weight: 700; color: #b45309;">Sisa Utang: Rp 0</span>
                </div>

                <div id="containerUnpaidTable" style="max-height: 200px; overflow-y: auto;">
                    <div style="text-align: center; color: var(--text-muted); font-size: 12px; padding: 16px;">
                        Silakan pilih pemasok terlebih dahulu untuk memuat Surat Jalan yang belum lunas.
                    </div>
                </div>
            </div>

            <!-- Keterangan -->
            <div style="margin-top: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">Keterangan / Catatan Pengeluaran</label>
                <textarea name="keterangan" id="payKeterangan" class="form-control" rows="2" placeholder="Catatan opsional (misal: Pelunasan tahap 1 ikan tongkol)..." style="font-size: 12px;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                <button type="button" class="btn btn-outline" onclick="closePaymentModal()">Batal</button>
                <button type="submit" id="btnSubmitPay" class="btn btn-primary" style="font-weight: 700;">
                    <i data-lucide="check" style="width: 15px; height: 15px;"></i>
                    <span>Simpan &amp; Bukukan Pembayaran</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 2: RIWAYAT PEMBAYARAN PER SURAT JALAN                              -->
<!-- ========================================================================= -->
<div id="modalRiwayatBayarSj" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 640px; margin: auto; padding: 24px; box-shadow: var(--shadow-lg); border-radius: 12px; background: #fff;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #1e293b;">Riwayat Cicilan Pembayaran Surat Jalan</h3>
                <p id="histSjSubtitle" style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace;">-</p>
            </div>
            <button type="button" onclick="closeHistSjModal()" style="border: none; background: transparent; font-size: 20px; color: #64748b; cursor: pointer; padding: 4px;">&times;</button>
        </div>

        <div id="histSjSummary" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <div>
                <div style="font-size: 11px; color: var(--text-muted); font-weight: 700;">TOTAL NILAI PEMBELIAN</div>
                <div id="histSjTotalTagihan" style="font-size: 15px; font-weight: 800; color: #1e293b; font-family: 'JetBrains Mono', monospace;">Rp 0</div>
            </div>
            <div>
                <div style="font-size: 11px; color: var(--text-muted); font-weight: 700;">SISA UTANG BERJALAN</div>
                <div id="histSjSisaUtang" style="font-size: 15px; font-weight: 800; color: #b45309; font-family: 'JetBrains Mono', monospace;">Rp 0</div>
            </div>
        </div>

        <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
            <table class="table-custom" style="font-size: 12px;">
                <thead>
                    <tr>
                        <th style="width: 35px; text-align: center;">No</th>
                        <th style="width: 130px;">No. Bukti Kas</th>
                        <th style="width: 95px;">Tgl Bayar</th>
                        <th style="text-align: right; width: 110px;">Nominal Cicil</th>
                        <th style="text-align: right; width: 110px;">Sisa Utang</th>
                        <th>Metode / Kasir</th>
                    </tr>
                </thead>
                <tbody id="histSjTableBody">
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 20px;">Memuat riwayat cicilan...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 16px; border-top: 1px solid #e2e8f0; padding-top: 12px;">
            <button type="button" class="btn btn-outline" onclick="closeHistSjModal()">Tutup</button>
        </div>
    </div>
</div>

<script>
let currentUnpaidSjList = [];
let currentModeAlokasi = 'FIFO';
let targetSpecificSj = null;

// Buka Modal Pembayaran Kas Keluar
function openPaymentModal(pemasokId = null, pemasokNama = '', specificSj = null, specificSisa = null) {
    const modal = document.getElementById('modalCatatBayarPemasok');
    const selectPemasok = document.getElementById('payPemasokId');
    const inputJumlah = document.getElementById('payJumlah');

    document.getElementById('formCatatBayarPemasok').reset();
    document.getElementById('payTanggal').value = new Date().toISOString().split('T')[0];
    document.getElementById('payMetode').value = 'TRANSFER_BANK';
    toggleMetodeBayar('TRANSFER_BANK');

    targetSpecificSj = specificSj;

    if (pemasokId) {
        selectPemasok.value = pemasokId;
        loadUnpaidSjForPemasok(pemasokId, specificSisa);
    } else {
        selectPemasok.value = '';
        renderUnpaidTable([]);
    }

    modal.style.display = 'flex';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closePaymentModal() {
    document.getElementById('modalCatatBayarPemasok').style.display = 'none';
}

function toggleMetodeBayar(val) {
    const bankSumber = document.getElementById('wrapperBankSumber');
    const noRek = document.getElementById('wrapperNoRekTujuan');
    if (val === 'TRANSFER_BANK') {
        bankSumber.style.display = 'block';
        noRek.style.display = 'block';
    } else {
        bankSumber.style.display = 'none';
        noRek.style.display = 'none';
    }
}

function toggleModeAlokasi(val) {
    currentModeAlokasi = val;
    renderUnpaidTable(currentUnpaidSjList);
    updatePaymentPreview();
}

// Muat daftar Surat Jalan yang belum lunas milik pemasok via AJAX
async function loadUnpaidSjForPemasok(pemasokId, prefillNominal = null) {
    const container = document.getElementById('containerUnpaidTable');
    const badge = document.getElementById('badgeTotalUnpaid');

    if (!pemasokId) {
        currentUnpaidSjList = [];
        renderUnpaidTable([]);
        badge.textContent = 'Sisa Utang: Rp 0';
        return;
    }

    container.innerHTML = '<div style="text-align: center; padding: 14px; font-size: 12px; color: var(--text-muted);"><i data-lucide="loader-2" class="animate-spin" style="width: 16px; height: 16px; display: inline-block;"></i> Mengambil Surat Jalan terutang...</div>';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    try {
        const resp = await fetch(`index.php?action=get_unpaid_sj_pemasok&pemasok_id=${pemasokId}`);
        const data = await resp.json();

        if (data.success) {
            currentUnpaidSjList = data.list || [];
            let totalSisa = 0;
            currentUnpaidSjList.forEach(s => totalSisa += parseFloat(s.sisa_utang));

            badge.textContent = `Sisa Utang: Rp ${new Intl.NumberFormat('id-ID').format(totalSisa)}`;

            if (prefillNominal && prefillNominal > 0) {
                document.getElementById('payJumlah').value = prefillNominal;
            } else if (totalSisa > 0 && !document.getElementById('payJumlah').value) {
                document.getElementById('payJumlah').value = totalSisa;
            }

            renderUnpaidTable(currentUnpaidSjList);
            updatePaymentPreview();
        } else {
            container.innerHTML = `<div style="text-align: center; color: #ef4444; font-size: 12px; padding: 12px;">Gagal: ${data.message}</div>`;
        }
    } catch (err) {
        container.innerHTML = `<div style="text-align: center; color: #ef4444; font-size: 12px; padding: 12px;">Terjadi kesalahan koneksi</div>`;
    }
}

// Render tabel daftar Surat Jalan terbuka
function renderUnpaidTable(list) {
    const container = document.getElementById('containerUnpaidTable');

    if (!list || list.length === 0) {
        container.innerHTML = '<div style="text-align: center; color: #10b981; font-weight: 700; font-size: 12px; padding: 16px;">Tidak ada Surat Jalan yang terutang (Seluruh transaksi lunas).</div>';
        return;
    }

    let html = `
        <table class="table-custom" style="font-size: 11.5px; width: 100%;">
            <thead>
                <tr style="background: #e2e8f0;">
                    <th style="padding: 6px 8px;">No. SJ</th>
                    <th style="padding: 6px 8px;">Tgl Terima</th>
                    <th style="text-align: right; padding: 6px 8px;">Sisa Utang</th>
                    <th style="text-align: center; padding: 6px 8px; width: 80px;">Umur</th>
                    <th style="text-align: right; padding: 6px 8px; width: 130px;">Alokasi Potongan</th>
                </tr>
            </thead>
            <tbody>
    `;

    list.forEach(sj => {
        const isTarget = (targetSpecificSj && targetSpecificSj === sj.no_surat_jalan);
        html += `
            <tr style="${isTarget ? 'background: #eff6ff;' : ''}">
                <td style="padding: 6px 8px; font-family: 'JetBrains Mono', monospace; font-weight: 700;">
                    ${sj.no_surat_jalan}
                </td>
                <td style="padding: 6px 8px; color: var(--text-muted);">
                    ${sj.tanggal_masuk.substring(0, 10)}
                </td>
                <td style="text-align: right; padding: 6px 8px; font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #b45309;">
                    Rp ${new Intl.NumberFormat('id-ID').format(sj.sisa_utang)}
                </td>
                <td style="text-align: center; padding: 6px 8px; font-weight: 700; color: ${sj.umur_hari > 14 ? '#b91c1c' : '#15803d'};">
                    ${sj.umur_hari} Hari
                </td>
                <td style="text-align: right; padding: 6px 8px;">
        `;

        if (currentModeAlokasi === 'MANUAL') {
            html += `
                <input type="number" 
                       name="alokasi_manual[${sj.no_surat_jalan}]" 
                       class="form-control input-alokasi-manual" 
                       data-sisa="${sj.sisa_utang}"
                       data-sj="${sj.no_surat_jalan}"
                       placeholder="0" 
                       min="0" 
                       max="${sj.sisa_utang}"
                       value="${isTarget ? sj.sisa_utang : 0}"
                       step="any"
                       style="padding: 2px 6px; font-size: 11.5px; height: 26px; text-align: right; font-family: 'JetBrains Mono', monospace;"
                       oninput="onManualAllocInput()">
            `;
        } else {
            html += `
                <span id="allocPreview_${sj.no_surat_jalan}" style="font-family: 'JetBrains Mono', monospace; font-weight: 800; color: #047857;">
                    Rp 0
                </span>
            `;
        }

        html += `</td></tr>`;
    });

    html += `</tbody></table>`;
    container.innerHTML = html;
}

// Update kalkulasi preview alokasi FIFO
function updatePaymentPreview() {
    if (currentModeAlokasi === 'MANUAL') return;

    let totalBayar = parseFloat(document.getElementById('payJumlah').value) || 0;
    let sisaUang = totalBayar;

    currentUnpaidSjList.forEach(sj => {
        const span = document.getElementById(`allocPreview_${sj.no_surat_jalan}`);
        if (!span) return;

        let sisaSj = parseFloat(sj.sisa_utang);
        let potong = Math.min(sisaUang, sisaSj);

        if (potong > 0) {
            span.textContent = `Rp ${new Intl.NumberFormat('id-ID').format(potong)}`;
            span.style.color = '#047857';
            sisaUang -= potong;
        } else {
            span.textContent = 'Rp 0';
            span.style.color = '#94a3b8';
        }
    });
}

// Handler perubahan input manual
function onManualAllocInput() {
    const inputs = document.querySelectorAll('.input-alokasi-manual');
    let total = 0;
    inputs.forEach(inp => {
        total += parseFloat(inp.value) || 0;
    });
    document.getElementById('payJumlah').value = total;
}

// Submit Form Pembayaran AP
async function submitPaymentAp(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitPay');
    const form = document.getElementById('formCatatBayarPemasok');
    const formData = new FormData(form);

    const jumlah = parseFloat(formData.get('jumlah_bayar')) || 0;
    if (jumlah <= 0) {
        alert('Jumlah pembayaran harus lebih besar dari Rp 0');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = 'Memproses...';

    try {
        const resp = await fetch('index.php?action=save_ap_payment', {
            method: 'POST',
            body: formData
        });
        const res = await resp.json();

        if (res.success) {
            alert(`Berhasil! ${res.message}`);
            closePaymentModal();
            window.location.reload();
        } else {
            alert(`Gagal: ${res.message}`);
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="check" style="width: 15px; height: 15px;"></i><span>Simpan &amp; Bukukan Pembayaran</span>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    } catch (err) {
        alert('Terjadi kesalahan jaringan saat membukukan pembayaran.');
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" style="width: 15px; height: 15px;"></i><span>Simpan &amp; Bukukan Pembayaran</span>';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

// Modal Riwayat Cicilan Surat Jalan
async function viewSjHistory(noSj, totalBeli, sisaUtang) {
    const modal = document.getElementById('modalRiwayatBayarSj');
    document.getElementById('histSjSubtitle').textContent = `Surat Jalan: ${noSj}`;
    document.getElementById('histSjTotalTagihan').textContent = `Rp ${new Intl.NumberFormat('id-ID').format(totalBeli)}`;
    document.getElementById('histSjSisaUtang').textContent = `Rp ${new Intl.NumberFormat('id-ID').format(sisaUtang)}`;

    const tbody = document.getElementById('histSjTableBody');
    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: var(--text-muted);"><i data-lucide="loader-2" class="animate-spin" style="width: 16px; height: 16px; display: inline-block;"></i> Memuat data cicilan...</td></tr>';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    modal.style.display = 'flex';

    try {
        const resp = await fetch(`index.php?action=get_sj_payment_history&no_surat_jalan=${encodeURIComponent(noSj)}`);
        const data = await resp.json();

        if (data.success && data.history && data.history.length > 0) {
            let html = '';
            let no = 1;
            data.history.forEach(h => {
                html += `
                    <tr>
                        <td style="text-align: center; color: var(--text-muted);">${no++}</td>
                        <td style="font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #1e3a8a;">${h.no_bukti_keluar}</td>
                        <td>${h.tanggal_bayar.substring(0, 10)}</td>
                        <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 800; color: #047857;">
                            Rp ${new Intl.NumberFormat('id-ID').format(h.nominal_alokasi)}
                        </td>
                        <td style="text-align: right; font-family: 'JetBrains Mono', monospace; color: #b45309;">
                            Rp ${new Intl.NumberFormat('id-ID').format(h.sisa_utang_setelah)}
                        </td>
                        <td>
                            <div style="font-size: 11px; font-weight: 700;">${h.metode_bayar}</div>
                            <div style="font-size: 10px; color: var(--text-muted);">${h.nama_kasir || '-'}</div>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: var(--text-muted);">Belum ada riwayat cicilan pembayaran untuk Surat Jalan ini.</td></tr>';
        }
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #ef4444;">Gagal memuat riwayat cicilan.</td></tr>';
    }
}

function closeHistSjModal() {
    document.getElementById('modalRiwayatBayarSj').style.display = 'none';
}

// Tutup modal jika klik di luar area card
window.addEventListener('click', function(e) {
    const modal1 = document.getElementById('modalCatatBayarPemasok');
    const modal2 = document.getElementById('modalRiwayatBayarSj');
    if (e.target === modal1) closePaymentModal();
    if (e.target === modal2) closeHistSjModal();
});
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>

