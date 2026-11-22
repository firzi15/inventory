<?php
/**
 * Halaman AP Aging (Utang Usaha) & Faktur Pembelian (Purchase Invoice)
 * Mengintegrasikan COA:
 * 1000 Kas & Bank (Kas Keluar)
 * 1100 Piutang & Aset Lainnya
 * 1200 Inventory / Persediaan (Stok Barang Masuk via Surat Jalan)
 * 2000 Utang Usaha (Kewajiban Tagihan Pembelian & Operasional)
 * 6000 Biaya Operasional (Es Balok, Box, Solar, Transport, dll)
 */

use App\Core\Helper;
use App\Models\Pemasok;
use App\Models\VendorNonPemasok;
use App\Models\TipeBiaya;
use App\Models\Utang;
use App\Models\PurchaseInvoice;

$pageTitle = 'AP Aging & Payment Out';
$activeTab = $_GET['tab'] ?? 'matriks';
if (!in_array($activeTab, ['invoices', 'matriks', 'riwayat_bayar'])) {
    $activeTab = 'matriks';
}

$pemasokList          = Pemasok::getAll();
$vendorNonPemasokList = VendorNonPemasok::getAllActive();
$tipeBiayaList        = TipeBiaya::getAllActive();
$akunFaktur           = \App\Services\CoaService::akunFaktur();
$selectedPemasokId = (int)($_GET['pemasok_id'] ?? 0);
$tampil = ($_GET['tampil'] ?? 'sj') === 'pemasok' ? 'pemasok' : 'sj';
$kpi          = Utang::getSummaryKpi($selectedPemasokId);
$kpiInvoice   = PurchaseInvoice::getSummaryKpi($selectedPemasokId);
$agingPemasok = Utang::getAgingSummaryPerPemasok($selectedPemasokId);
// Satu sumber angka: utang stok dari surat jalan + faktur operasional (faktur STOK hanya cerminan surat jalan)
$totalTagihan = (float)$kpi['total_utang_terbit'] + (float)$kpiInvoice['total_operasional'];
$sisaUtang    = (float)$kpi['sisa_utang_aktif'] + (float)$kpiInvoice['sisa_operasional'];
$sjBelumLunas = array_sum(array_column($agingPemasok, 'sj_terbuka'));
$pemasokBerutang = count(array_filter($agingPemasok, fn($x) => (float)$x['sisa_utang'] > 0.01));

// Tampilan matriks per surat jalan: hanya yang masih terutang, paling telat di atas
$agRows = [];
if ($activeTab === 'matriks' && $tampil === 'sj') {
    $sjAging = Utang::getSuratJalanList(['pemasok_id' => $selectedPemasokId, 'status' => 'BELUM_LUNAS']);
    usort($sjAging, fn($a, $b) => $b['hari_telat'] <=> $a['hari_telat'] ?: strcmp($a['tanggal_masuk'], $b['tanggal_masuk']));
    foreach ($sjAging as $sj) {
        $agRows[] = ['no_dok' => $sj['no_surat_jalan'], 'tanggal' => $sj['tanggal_masuk'], 'pihak' => '[' . $sj['kode_pemasok'] . '] ' . $sj['nama_pemasok'],
            'tempo' => $sj['jatuh_tempo'], 'tagihan' => $sj['total_nilai_beli'], 'bayar' => $sj['total_bayar'], 'sisa' => $sj['sisa_utang'], 'telat' => $sj['hari_telat']];
    }
}

// Filter Umum (Pemasok, Status, Keyword, Tipe)
$selectedTipe      = trim($_GET['tipe'] ?? 'SEMUA');
$selectedStatus    = trim($_GET['status'] ?? 'SEMUA');
$searchKeyword     = trim($_GET['search'] ?? '');
$startDate         = trim($_GET['start_date'] ?? '');
$endDate           = trim($_GET['end_date'] ?? '');

// Data Tab 1: Faktur Pembelian (Purchase Invoices). Faktur penerimaan terbit otomatis saat surat jalan selesai.
$invoicesList = PurchaseInvoice::getAll([
    'pemasok_id'   => $selectedPemasokId,
    'tipe_invoice' => $selectedTipe,
    'status_bayar' => $selectedStatus,
    'search'       => $searchKeyword,
    'start_date'   => $startDate,
    'end_date'     => $endDate
], 150);

// Data Tab 3: Filter untuk Tab SJ Masuk
$sjList = Utang::getSuratJalanList([
    'pemasok_id' => $selectedPemasokId,
    'status'     => $selectedStatus,
    'search'     => $searchKeyword
]);

// Data Tab 4: Tab Riwayat Kas Keluar
$riwayatPayments = Utang::getAllPayments([
    'pemasok_id' => $selectedPemasokId,
    'search'     => $searchKeyword
]);

// Bayar gelondongan: checklist hanya muncul setelah pemasok dipilih di filter
$btBoleh = $activeTab === 'invoices' && $selectedPemasokId > 0;
$btPihakNama = '';
foreach ($pemasokList as $pmBt) { if ((int)$pmBt['id'] === $selectedPemasokId) $btPihakNama = '[' . $pmBt['kode_pemasok'] . '] ' . $pmBt['nama_pemasok']; }

require_once __DIR__ . '/layout/header.php';
?>

<div class="content-wrapper">

    <!-- PAGE HEADER -->
    <div class="page-header no-print">
        <div>
            <h1 class="page-title"><?= $activeTab === 'invoices' ? 'Payment Out' : 'AP Aging' ?></h1>
            <p class="page-subtitle"><?= $activeTab === 'invoices' ? 'Faktur pembelian dan biaya operasional' : 'Utang dan pembayaran ke pemasok' ?></p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <?php if ($activeTab === 'invoices'): ?>
            <button type="button" class="btn btn-primary" onclick="openCreateInvoiceModal()" style="display: flex; align-items: center; gap: 6px; font-weight: 700;">
                <i data-lucide="plus" style="width: 16px; height: 16px;"></i>
                <span>Faktur Operasional</span>
            </button>
            <?php endif; ?>
            <?php $eksporNama = 'AP_Aging_' . $activeTab; $eksporJudul = ($activeTab === 'invoices' ? 'Payment Out' : 'AP Aging'); include __DIR__ . '/layout/ekspor.php'; ?>
        </div>
    </div>

    <!-- 4 KARTU KPI RINGKASAN UTANG -->
    <div class="grid grid-cols-4 no-print" style="gap: 16px; margin-bottom: 22px;">
        <!-- Card 1: Total Nilai Tagihan -->
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Total Tagihan</div>
                <div class="kpi-value"><?= Helper::formatRupiah($totalTagihan) ?></div>
                <div class="kpi-sub">Stok: <?= Helper::formatRupiah($kpi['total_utang_terbit']) ?> &bull; Opr: <?= Helper::formatRupiah($kpiInvoice['total_operasional']) ?></div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="receipt" style="width: 18px; height: 18px;"></i></div>
        </div>

        <!-- Card 2: Total Pembayaran Kas Keluar -->
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Total Dibayar</div>
                <div class="kpi-value"><?= Helper::formatRupiah($kpi['total_pembayaran_keluar']) ?></div>
                <div class="kpi-sub"><?= number_format($kpi['total_transaksi_bayar']) ?> transaksi kas keluar</div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="credit-card" style="width: 18px; height: 18px;"></i></div>
        </div>

        <!-- Card 3: Sisa Utang Berjalan -->
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Sisa Utang</div>
                <div class="kpi-value"><?= Helper::formatRupiah($sisaUtang) ?></div>
                <div class="kpi-sub"><?= number_format($sjBelumLunas) ?> surat jalan belum lunas</div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="hourglass" style="width: 18px; height: 18px;"></i></div>
        </div>

        <!-- Card 4: Pemasok yang masih berutang -->
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Pemasok Berutang</div>
                <div class="kpi-value"><?= number_format($pemasokBerutang) ?></div>
                <div class="kpi-sub"><?= $selectedPemasokId > 0 ? 'pemasok terpilih' : 'dari ' . number_format(count($agingPemasok)) . ' pemasok aktif' ?></div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="users" style="width: 18px; height: 18px;"></i></div>
        </div>
    </div>


    <!-- ========================================================================= -->
    <!-- TAB 1: FAKTUR PEMBELIAN                                                   -->
    <!-- ========================================================================= -->
    <?php if ($activeTab === 'invoices'): ?>
        <div class="card" style="padding: 20px;">
            <!-- FILTER BAR -->
            <form method="GET" action="index.php" class="no-print" style="background: var(--bg-muted); padding: 14px 16px; border-radius: 8px; margin-bottom: 18px; border: 1px solid var(--border); display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                <input type="hidden" name="page" value="ap_aging">
                <input type="hidden" name="tab" value="invoices">

                <div style="flex: 1.2; min-width: 180px;">
                    <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">Pemasok / Vendor</label>
                    <select name="pemasok_id" class="form-control" style="font-size: 12.5px;" onchange="this.form.submit()">
                        <option value="0">-- Seluruh Vendor / Pemasok --</option>
                        <?php foreach ($pemasokList as $pm): ?>
                            <option value="<?= $pm['id'] ?>" <?= $selectedPemasokId === (int)$pm['id'] ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($pm['kode_pemasok']) ?>] <?= htmlspecialchars($pm['nama_pemasok']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="width: 170px;">
                    <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">Tipe &amp; Akun COA</label>
                    <select name="tipe" class="form-control" style="font-size: 12.5px;" onchange="this.form.submit()">
                        <option value="NON_STOK" <?= $selectedTipe === 'NON_STOK' ? 'selected' : '' ?>>Operasional &amp; Aset</option>
                        <option value="SEMUA" <?= $selectedTipe === 'SEMUA' ? 'selected' : '' ?>>Semua Tipe Akun</option>
                        <option value="STOK" <?= $selectedTipe === 'STOK' ? 'selected' : '' ?>>1200 - Inventory / Persediaan</option>
                        <option value="OPERASIONAL" <?= $selectedTipe === 'OPERASIONAL' ? 'selected' : '' ?>>6000 - Biaya Operasional</option>
                        <option value="ASET" <?= $selectedTipe === 'ASET' ? 'selected' : '' ?>>1100 - Piutang &amp; Aset Lainnya</option>
                    </select>
                </div>

                <div style="width: 155px;">
                    <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">Status Bayar</label>
                    <select name="status" class="form-control" style="font-size: 12.5px;" onchange="this.form.submit()">
                        <option value="SEMUA" <?= $selectedStatus === 'SEMUA' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="BELUM_LUNAS" <?= $selectedStatus === 'BELUM_LUNAS' ? 'selected' : '' ?>>Belum Lunas Sisa &gt; 0</option>
                        <option value="BELUM_BAYAR" <?= $selectedStatus === 'BELUM_BAYAR' ? 'selected' : '' ?>>Belum Ada Bayar</option>
                        <option value="SEBAGIAN" <?= $selectedStatus === 'SEBAGIAN' ? 'selected' : '' ?>>Sebagian / Cicil</option>
                        <option value="LUNAS" <?= $selectedStatus === 'LUNAS' ? 'selected' : '' ?>>Lunas 100%</option>
                    </select>
                </div>

                <div style="flex: 1.5; min-width: 200px;">
                    <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">Cari No. Faktur / Vendor / Catatan</label>
                    <input type="text" name="search" class="form-control" placeholder="Ketik nomor invoice, SJ, atau vendor..." value="<?= htmlspecialchars($searchKeyword) ?>" style="font-size: 12.5px;">
                </div>

                <div style="display: flex; gap: 6px;">
                    <button type="submit" class="btn btn-primary" style="font-size: 12px; padding: 8px 14px;">
                        <i data-lucide="filter" style="width: 14px; height: 14px;"></i>
                        <span>Filter</span>
                    </button>
                    <?php if ($selectedPemasokId > 0 || $selectedTipe !== 'SEMUA' || $selectedStatus !== 'SEMUA' || !empty($searchKeyword)): ?>
                        <a href="index.php?page=ap_aging&tab=invoices" class="btn btn-outline" style="font-size: 12px; padding: 8px 12px;">Reset</a>
                    <?php endif; ?>
                    <?php if ($btBoleh): ?>
                        <button type="button" id="btnBayarTerpilih" class="btn btn-primary" onclick="bukaBayarTerpilih()" disabled
                                style="font-size: 12px; padding: 8px 14px; display: inline-flex; align-items: center; gap: 6px;" title="Centang dokumen di tabel, lalu klik untuk membayar sekaligus">
                            <i data-lucide="wallet" style="width: 14px; height: 14px;"></i><span id="lblBayarTerpilih">Bayar Terpilih</span>
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline" disabled title="Pilih pemasok di filter dulu untuk membayar beberapa dokumen sekaligus"
                                style="font-size: 12px; padding: 8px 14px; display: inline-flex; align-items: center; gap: 6px; opacity: .6;">
                            <i data-lucide="wallet" style="width: 14px; height: 14px;"></i><span>Bayar Terpilih</span>
                        </button>
                    <?php endif; ?>
                </div>
            </form>

            <!-- TABEL PURCHASE INVOICE -->
            <div class="table-responsive">
                <table class="table-custom" data-halaman="25">
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center;">No</th>
                            <?php if ($btBoleh): ?><th class="no-print" style="width: 34px; text-align: center;"><input type="checkbox" id="cekSemuaBayar" onchange="pilihSemuaBayar(this.checked)" title="Pilih semua yang tampil"></th><?php endif; ?>
                            <th style="width: 155px;">No. Faktur</th>
                            <th style="width: 150px;">Tipe &amp; Akun</th>
                            <th style="min-width: 170px;">Pemasok / Vendor</th>
                            <th style="width: 120px;">Tanggal &amp; Tempo</th>
                            <th style="width: 135px;">Ref. Surat Jalan</th>
                            <th style="text-align: right; width: 125px;">Total Tagihan</th>
                            <th style="text-align: right; width: 120px;">Total Dibayar</th>
                            <th style="text-align: right; width: 125px; background: var(--bg-muted);">Sisa Utang</th>
                            <th style="text-align: center; width: 105px;">Status</th>
                            <th class="no-print" style="width: 145px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoicesList)): ?>
                            <tr>
                                <td colspan="<?= $btBoleh ? 12 : 11 ?>" style="text-align: center; padding: 36px; color: var(--text-muted);">
                                    Tidak ada data Faktur Pembelian yang cocok dengan kriteria filter.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $no = 1;
                            $sumTotalTagihan = 0.0;
                            $sumTotalBayar   = 0.0;
                            $sumSisaUtang    = 0.0;

                            foreach ($invoicesList as $inv): 
                                if ($inv['status_bayar'] !== 'BATAL') {
                                $sumTotalTagihan += (float)$inv['total_nominal'];
                                $sumTotalBayar   += (float)$inv['total_bayar'];
                                $sumSisaUtang    += (float)$inv['sisa_utang'];
                                }
                                $isOverdue = ((int)($inv['sisa_hari_jatuh_tempo'] ?? 0) < 0 && (float)$inv['sisa_utang'] > 0.01);
                            ?>
                            <tr>
                                <td style="text-align: center; color: var(--text-muted);"><?= $no++ ?></td>
                                <?php if ($btBoleh): ?>
                                    <td class="no-print" style="text-align: center;">
                                        <?php if (!in_array($inv['status_bayar'], ['BATAL', 'LUNAS'], true) && (float)$inv['sisa_utang'] > 0.01): ?>
                                            <input type="checkbox" class="cek-bayar-ap" value="<?= (int)$inv['id'] ?>" onchange="perbaruiTombolBayar()"
                                                   data-no="<?= htmlspecialchars($inv['no_invoice']) ?>" data-sisa="<?= (float)$inv['sisa_utang'] ?>"
                                                   data-tempo="<?= htmlspecialchars(substr((string)$inv['tanggal_jatuh_tempo'], 0, 10)) ?>" data-tgl="<?= htmlspecialchars((string)$inv['tanggal_invoice']) ?>">
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <div style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; font-weight: 800; color: var(--aktif-fg);">
                                        <?= htmlspecialchars($inv['no_invoice']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($inv['tipe_invoice'] === 'STOK'): ?>
                                        <span style="display: inline-block; padding: 2px 7px; background: var(--info-bg); color: var(--aktif-fg); font-weight: 700; font-size: 11px; border-radius: 4px;">
                                            1200 Persediaan
                                        </span>
                                    <?php elseif ($inv['tipe_invoice'] === 'OPERASIONAL'): ?>
                                        <span style="display: inline-block; padding: 2px 7px; background: var(--warn-bg); color: var(--warn-fg); font-weight: 700; font-size: 11px; border-radius: 4px;">
                                            6000 Operasional
                                        </span>
                                    <?php elseif ($inv['tipe_invoice'] === 'ASET'): ?>
                                        <span style="display: inline-block; padding: 2px 7px; background: var(--ok-bg); color: var(--ok-fg); font-weight: 700; font-size: 11px; border-radius: 4px;">
                                            1100 Aset
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 2px 7px; background: var(--bg-muted); color: var(--text-muted); font-weight: 700; font-size: 11px; border-radius: 4px;">
                                            <?= htmlspecialchars($inv['kode_akun_debit']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: var(--text-main);">
                                        <?= !empty($inv['kode_pemasok']) ? '[' . htmlspecialchars($inv['kode_pemasok']) . '] ' : '' ?><?= htmlspecialchars($inv['nama_vendor']) ?>
                                    </strong>
                                    <?php if (!empty($inv['keterangan'])): ?>
                                        <div style="font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;">
                                            <?= htmlspecialchars($inv['keterangan']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 11.5px;">
                                    <div>Tgl: <?= date('d/m/Y', strtotime($inv['tanggal_invoice'])) ?></div>
                                    <div style="font-size: 10.5px; color: <?= $isOverdue ? '#b91c1c; font-weight: 800;' : 'var(--text-muted);' ?>">
                                        Tempo: <?= date('d/m/Y', strtotime($inv['tanggal_jatuh_tempo'])) ?>
                                        <?php if ($isOverdue): ?>
                                            (Lewat <?= abs((int)$inv['sisa_hari_jatuh_tempo']) ?> hr)
                                        <?php endif; ?>
                                        <?php if ($inv['status_bayar'] !== 'BATAL' && $inv['status_bayar'] !== 'LUNAS' && \App\Core\Akses::boleh(strtoupper($_SESSION['user_login']['role'] ?? ''), ['ADMIN', 'SUPERVISOR', 'FINANCE'])): ?>
                                            <a href="javascript:void(0)" class="no-print" style="font-size: 10.5px; margin-left: 4px;" onclick='ubahJatuhTempo("FAKTUR", <?= (int)$inv['id'] ?>, <?= json_encode(substr($inv['tanggal_jatuh_tempo'], 0, 10)) ?>, <?= json_encode($inv['no_invoice']) ?>)'>ubah</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($inv['no_surat_jalan'])): ?>
                                        <span style="font-family: 'JetBrains Mono', monospace; font-size: 11.5px; font-weight: 700; color: var(--aktif-fg);">
                                            <?= htmlspecialchars($inv['no_surat_jalan']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: var(--text-muted); font-style: italic;">Non-SJ</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px;">
                                    <?= Helper::formatRupiah($inv['total_nominal']) ?>
                                </td>
                                <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--ok-fg);">
                                    <?= Helper::formatRupiah($inv['total_bayar']) ?>
                                </td>
                                <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 800; background: var(--bg-muted); color: <?= (float)$inv['sisa_utang'] > 0.01 ? '#b45309' : '#047857' ?>;">
                                    <?= Helper::formatRupiah($inv['sisa_utang']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($inv['status_bayar'] === 'LUNAS'): ?>
                                        <span style="display: inline-block; padding: 2px 8px; background: var(--ok-bg); color: var(--ok-fg); font-weight: 700; font-size: 11px; border-radius: 4px;">Lunas</span>
                                    <?php elseif ($inv['status_bayar'] === 'BATAL'): ?>
                                        <span style="display: inline-block; padding: 2px 8px; background: var(--border); color: var(--text-muted); font-weight: 700; font-size: 11px; border-radius: 4px;" title="<?= htmlspecialchars($inv['alasan_batal'] ?? '') ?>">Dibatalkan</span>
                                    <?php elseif ($inv['status_bayar'] === 'SEBAGIAN'): ?>
                                        <span style="display: inline-block; padding: 2px 8px; background: var(--warn-bg); color: var(--warn-fg); font-weight: 700; font-size: 11px; border-radius: 4px;">Cicil / Parsial</span>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 2px 8px; background: var(--bad-bg); color: var(--bad-fg); font-weight: 700; font-size: 11px; border-radius: 4px;">Belum Bayar</span>
                                    <?php endif; ?>
                                </td>
                                <td class="no-print" style="text-align: center; white-space: nowrap;">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="viewInvoiceDetail(<?= $inv['id'] ?>)" style="padding: 3px 8px; font-size: 11px;" title="Lihat Rincian Item &amp; Jurnal">
                                        <i data-lucide="eye" style="width: 12px; height: 12px; display: inline-block;"></i> Rincian
                                    </button>
                                    <?php if ((float)$inv['sisa_utang'] > 0.01): ?>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="openPaymentForInvoice(<?= $inv['id'] ?>, '<?= htmlspecialchars($inv['nama_vendor'], ENT_QUOTES) ?>', <?= (float)$inv['sisa_utang'] ?>, '<?= htmlspecialchars($inv['no_invoice'], ENT_QUOTES) ?>', <?= (int)$inv['pemasok_id'] ?>)" style="padding: 3px 8px; font-size: 11px; font-weight: 700;" title="Bayar Faktur Ini">
                                            Bayar
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($inv['tipe_invoice'] !== 'STOK' && $inv['status_bayar'] === 'BELUM_BAYAR'): ?>
                                        <button type="button" class="btn btn-outline btn-sm" onclick="batalDokumen('batal_purchase_invoice', { id: <?= (int)$inv['id'] ?> }, 'faktur <?= htmlspecialchars($inv['no_invoice'], ENT_QUOTES) ?>')" style="padding: 5px 9px; font-size: 12px; color: var(--bad-fg); border-color: rgba(220,38,38,0.35);" title="Batalkan dokumen"><i data-lucide="ban" style="width: 14px; height: 14px;"></i><span>Batalkan</span></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($invoicesList)): ?>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- TAB 2: RINGKASAN UTANG PER PEMASOK                                    -->
    <!-- ========================================================================= -->
    <?php if ($activeTab === 'matriks'): ?>
        <div class="card" style="padding: 20px;">
            <form method="GET" action="index.php" class="no-print" style="background: var(--bg-muted); padding: 14px 16px; border-radius: 8px; margin-bottom: 18px; border: 1px solid var(--border); display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                <input type="hidden" name="page" value="ap_aging">
                <input type="hidden" name="tab" value="matriks">
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
                <div style="width: 180px;">
                    <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">Tampilan</label>
                    <select name="tampil" class="form-control" style="font-size: 12.5px;" onchange="this.form.submit()">
                        <option value="sj" <?= $tampil === 'sj' ? 'selected' : '' ?>>Per Surat Jalan</option>
                        <option value="pemasok" <?= $tampil === 'pemasok' ? 'selected' : '' ?>>Per Pemasok</option>
                    </select>
                </div>
                <?php if ($selectedPemasokId > 0): ?>
                    <a href="index.php?page=ap_aging&tab=matriks&tampil=<?= $tampil ?>" class="btn btn-outline" style="font-size: 12px; padding: 8px 12px;">Reset</a>
                <?php endif; ?>
            </form>
            <?php if ($tampil === 'sj'):
                $agLabelDok = 'No. Surat Jalan'; $agLabelPihak = 'Pemasok'; $agLabelTagihan = 'Tagihan'; $agLabelBayar = 'Dibayar'; $agLabelSisa = 'Sisa Utang';
                include __DIR__ . '/layout/aging_dokumen.php';
            else: ?>
            <div class="table-responsive">
                <table class="table-custom" data-halaman="25">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">No</th>
                            <th style="min-width: 180px;">Pemasok / Supplier</th>
                            <th style="width: 100px; text-align: center;">SJ Terbuka</th>
                            <th style="text-align: right; min-width: 130px;">Total Tagihan</th>
                            <th style="text-align: right; min-width: 130px;">Total Dibayar</th>
                            <th style="text-align: right; min-width: 135px; background: var(--bg-muted);">Sisa Utang</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        $totTagihan = 0.0;
                        $totBayar   = 0.0;
                        $totSisa    = 0.0;

                        foreach ($agingPemasok as $p): 
                            $totTagihan += $p['total_tagihan'];
                            $totBayar   += $p['total_dibayar'];
                            $totSisa    += $p['sisa_utang'];
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
                                    <span style="font-weight: 800; color: var(--warn-fg);"><?= $p['sj_terbuka'] ?> SJ</span>
                                <?php else: ?>
                                    <span style="color: var(--ok-fg); font-weight: 700;">Lunas</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px;">
                                <?= Helper::formatRupiah($p['total_tagihan']) ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--ok-fg);">
                                <?= Helper::formatRupiah($p['total_dibayar']) ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 800; background: var(--bg-muted); color: <?= $p['sisa_utang'] > 0 ? '#b45309' : '#047857' ?>;">
                                <?= Helper::formatRupiah($p['sisa_utang']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- TAB 3: SURAT JALAN MASUK                                                 -->
    <!-- ========================================================================= -->
    <?php if ($activeTab === 'sj_masuk'): ?>
        <div class="card" style="padding: 20px;">
            <!-- FILTER BAR -->
            <form method="GET" action="index.php" class="no-print" style="background: var(--bg-muted); padding: 14px 16px; border-radius: 8px; margin-bottom: 18px; border: 1px solid var(--border); display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
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
                        <option value="BELUM_LUNAS" <?= $selectedStatus === 'BELUM_LUNAS' ? 'selected' : '' ?>>Belum Lunas Sisa &gt; 0</option>
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
                <table class="table-custom" data-halaman="25">
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center;">No</th>
                            <th style="min-width: 160px;">No. Surat Jalan</th>
                            <th style="width: 105px;">Tgl Masuk</th>
                            <th style="min-width: 170px;">Pemasok</th>
                            <th style="text-align: right; min-width: 125px;">Total Tagihan</th>
                            <th style="text-align: right; min-width: 125px;">Total Dibayar</th>
                            <th style="text-align: right; min-width: 130px; background: var(--bg-muted);">Sisa Utang</th>
                            <th style="width: 130px; text-align: center;">Jatuh Tempo</th>
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
                                $bolehTempo = \App\Core\Akses::boleh(strtoupper($_SESSION['user_login']['role'] ?? ''), ['ADMIN', 'SUPERVISOR', 'FINANCE']);
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
                                <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--ok-fg);">
                                    <?= Helper::formatRupiah($sj['total_bayar']) ?>
                                    <?php if ($sj['jml_kali_bayar'] > 0): ?>
                                        <div style="font-size: 10.5px; color: var(--text-muted);"><?= $sj['jml_kali_bayar'] ?>x cicilan</div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 800; background: var(--bg-muted); color: <?= $sj['sisa_utang'] > 0 ? '#b45309' : '#047857' ?>;">
                                    <?= Helper::formatRupiah($sj['sisa_utang']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($sj['sisa_utang'] > 0): ?>
                                        <div style="font-size: 12px; font-weight: 700;"><?= date('d/m/Y', strtotime($sj['jatuh_tempo'])) ?></div>
                                        <?php if ($bolehTempo && !empty($sj['invoice_id'])): ?>
                                            <div class="no-print"><a href="javascript:void(0)" style="font-size: 11px;" onclick='ubahJatuhTempo("FAKTUR", <?= (int)$sj['invoice_id'] ?>, <?= json_encode(substr($sj['jatuh_tempo'], 0, 10)) ?>, <?= json_encode($sj['no_surat_jalan']) ?>)'>ubah</a></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--ok-fg); font-weight: 700; font-size: 12px;">Selesai</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($sj['status_bayar'] === 'LUNAS'): ?>
                                        <span style="font-weight: 800; font-size: 11px; color: var(--ok-fg); background: var(--ok-bg); padding: 2px 8px; border-radius: 4px;">LUNAS</span>
                                    <?php elseif ($sj['status_bayar'] === 'PARSIAL'): ?>
                                        <span style="font-weight: 800; font-size: 11px; color: var(--warn-fg); background: var(--warn-bg); padding: 2px 8px; border-radius: 4px;">PARSIAL</span>
                                    <?php else: ?>
                                        <span style="font-weight: 800; font-size: 11px; color: var(--bad-fg); background: var(--bad-bg); padding: 2px 8px; border-radius: 4px;">BELUM BAYAR</span>
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
    <!-- TAB 4: BUKU KAS KELUAR                                                    -->
    <!-- ========================================================================= -->
    <?php if ($activeTab === 'riwayat_bayar'): ?>
        <div class="card" style="padding: 20px;">
            <!-- FILTER BAR -->
            <form method="GET" action="index.php" class="no-print" style="background: var(--bg-muted); padding: 14px 16px; border-radius: 8px; margin-bottom: 18px; border: 1px solid var(--border); display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
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
                <table class="table-custom" data-halaman="25">
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
                                    <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--aktif-fg);">
                                        <?= htmlspecialchars($p['no_bukti_keluar']) ?>
                                    </strong>
                                    <?php if (($p['status'] ?? 'AKTIF') === 'BATAL'): ?>
                                        <div><span style="display: inline-block; padding: 1px 6px; background: var(--border); color: var(--text-muted); font-size: 10.5px; border-radius: 4px;">Dibatalkan</span></div>
                                        <div style="font-size: 10.5px; color: var(--text-muted);"><?= htmlspecialchars($p['alasan_batal'] ?? '') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 12px;">
                                    <?= date('d/m/Y', strtotime($p['tanggal_bayar'])) ?>
                                </td>
                                <td>
                                    <strong style="color: var(--text-main);">[<?= htmlspecialchars($p['kode_pemasok']) ?>] <?= htmlspecialchars($p['nama_pemasok']) ?></strong>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($p['metode_bayar'] === 'TRANSFER_BANK'): ?>
                                        <span style="display: inline-block; padding: 2px 8px; background: var(--info-bg); color: var(--aktif-fg); font-weight: 700; font-size: 11px; border-radius: 4px;">
                                            TRANSFER
                                        </span>
                                        <?php if (!empty($p['bank_sumber'])): ?>
                                            <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;"><?= htmlspecialchars($p['bank_sumber']) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 2px 8px; background: var(--ok-bg); color: var(--ok-fg); font-weight: 700; font-size: 11px; border-radius: 4px;">
                                            TUNAI
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 800; color: var(--ok-fg);">
                                    <?php if (($p['status'] ?? 'AKTIF') === 'BATAL'): ?>
                                        <span style="color: var(--text-muted); text-decoration: line-through;"><?= Helper::formatRupiah($p['jumlah_asal']) ?></span>
                                    <?php else: ?>
                                        <?= Helper::formatRupiah($p['jumlah_bayar']) ?>
                                    <?php endif; ?>
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
                    <?php endif; ?>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- ========================================================================= -->
<!-- MODAL 1: CATAT PEMBAYARAN KAS KELUAR KE PEMASOK / INVOICE                -->
<!-- ========================================================================= -->
<div id="modalCatatBayarPemasok" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 680px; margin: auto; padding: 24px; box-shadow: var(--shadow-lg); border-radius: 12px; background: var(--bg-card);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 18px;">
            <div>
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: var(--text-main);">Catat Pembayaran Kas Keluar</h3>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted);">Pembayaran tagihan via transfer bank atau kas tunai</p>
            </div>
            <button type="button" onclick="closePaymentModal()" style="border: none; background: transparent; font-size: 20px; color: var(--text-muted); cursor: pointer; padding: 4px;">&times;</button>
        </div>

        <!-- Banner jika pembayaran diarahkan ke Faktur tertentu -->
        <div id="bannerTargetInvoice" style="display: none; background: var(--info-bg); border: 1px solid var(--border-hover); border-radius: 8px; padding: 10px 14px; margin-bottom: 14px;">
            <div style="font-size: 11px; font-weight: 800; color: var(--aktif-fg); text-transform: uppercase;">Pembayaran Ditujukan Khusus Ke Faktur:</div>
            <div id="bannerTargetInvoiceText" style="font-size: 13px; font-weight: 800; color: var(--aktif-fg); margin-top: 2px;">-</div>
        </div>

        <form id="formCatatBayarPemasok" onsubmit="submitPaymentAp(event)">
            <input type="hidden" id="payInvoiceId" name="invoice_id" value="">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                <!-- Pemasok -->
                <div style="grid-column: span 2;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Pemasok / Vendor Penerima <span style="color: var(--bad-fg);">*</span></label>
                    <select id="payPemasokId" name="pemasok_id" class="form-control" required onchange="loadUnpaidSjForPemasok(this.value)">
                        <option value="">-- Pilih Pemasok / Supplier --</option>
                        <?php foreach ($pemasokList as $pm): ?>
                            <option value="<?= $pm['id'] ?>">[<?= htmlspecialchars($pm['kode_pemasok']) ?>] <?= htmlspecialchars($pm['nama_pemasok']) ?> (<?= htmlspecialchars($pm['pic_nama'] ?: '-') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tanggal Bayar -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Tanggal Pembayaran <span style="color: var(--bad-fg);">*</span></label>
                    <input type="date" id="payTanggal" name="tanggal_bayar" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <!-- Metode Bayar -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Metode Pembayaran <span style="color: var(--bad-fg);">*</span></label>
                    <select id="payMetode" name="metode_bayar" class="form-control" required onchange="toggleMetodeBayar(this.value)">
                        <option value="TRANSFER_BANK">Transfer Bank</option>
                        <option value="TUNAI">Uang Tunai</option>
                    </select>
                </div>

                <!-- Bank Sumber -->
                <div id="wrapperBankSumber">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Rekening Bank Sumber</label>
                    <input type="text" id="payBankSumber" name="bank_sumber" class="form-control" placeholder="Contoh: BCA 8820192837 a/n PT WMS">
                </div>

                <!-- No Rekening Tujuan -->
                <div id="wrapperNoRekTujuan">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">No. Rekening Tujuan Vendor</label>
                    <input type="text" id="payNoRekTujuan" name="no_rekening_tujuan" class="form-control" placeholder="Contoh: Mandiri 1320019283 a/n Udin">
                </div>

                <!-- Jumlah Bayar -->
                <div style="grid-column: span 2;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Total Dibayarkan (Rp)</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--text-muted);">Rp</span>
                        <input type="number" id="payJumlah" name="jumlah_bayar" class="form-control" placeholder="0" min="1" step="any" required readonly style="padding-left: 38px; font-size: 15px; font-weight: 800; font-family: 'JetBrains Mono', monospace; background: var(--bg-muted);">
                    </div>
                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px;">Pembayaran ke pemasok selalu lunas penuh per surat jalan atau faktur.</div>
                </div>

                <div id="wrapperModeAlokasi" style="display: none;">
                    <input type="hidden" name="mode_alokasi" value="MANUAL">
                </div>
            </div>

            <!-- PREVIEW DAFTAR SURAT JALAN BELUM LUNAS -->
            <div id="wrapperUnpaidSj" style="margin-top: 14px; background: var(--bg-muted); border: 1px solid var(--border); border-radius: 8px; padding: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 12px; font-weight: 800; color: var(--text-main);">Pilih Surat Jalan yang Dibayar</span>
                    <span id="badgeTotalUnpaid" style="font-size: 11px; font-weight: 700; color: var(--warn-fg);">Sisa Utang: Rp 0</span>
                </div>

                <div id="containerUnpaidTable" style="max-height: 200px; overflow-y: auto;">
                    <div style="text-align: center; color: var(--text-muted); font-size: 12px; padding: 16px;">
                        Silakan pilih pemasok terlebih dahulu untuk memuat Surat Jalan yang belum lunas.
                    </div>
                </div>
            </div>

            <!-- Keterangan -->
            <div style="margin-top: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Keterangan / Catatan Pengeluaran</label>
                <textarea name="keterangan" id="payKeterangan" class="form-control" rows="2" placeholder="Catatan opsional (misal: Pelunasan faktur es balok / ikan tongkol)..." style="font-size: 12px;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; border-top: 1px solid var(--border); padding-top: 14px;">
                <button type="button" class="btn btn-outline" onclick="closePaymentModal()">Batal</button>
                <button type="submit" id="btnSubmitPay" class="btn btn-primary" style="font-weight: 700;">
                    <i data-lucide="check" style="width: 15px; height: 15px;"></i>
                    <span>Simpan Pembayaran</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 2: RIWAYAT PEMBAYARAN PER SURAT JALAN                              -->
<!-- ========================================================================= -->
<div id="modalRiwayatBayarSj" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 640px; margin: auto; padding: 24px; box-shadow: var(--shadow-lg); border-radius: 12px; background: var(--bg-card);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--text-main);">Riwayat Cicilan Pembayaran Surat Jalan</h3>
                <p id="histSjSubtitle" style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace;">-</p>
            </div>
            <button type="button" onclick="closeHistSjModal()" style="border: none; background: transparent; font-size: 20px; color: var(--text-muted); cursor: pointer; padding: 4px;">&times;</button>
        </div>

        <div id="histSjSummary" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; background: var(--bg-muted); padding: 12px; border-radius: 8px; border: 1px solid var(--border);">
            <div>
                <div style="font-size: 11px; color: var(--text-muted); font-weight: 700;">TOTAL NILAI PEMBELIAN</div>
                <div id="histSjTotalTagihan" style="font-size: 15px; font-weight: 800; color: var(--text-main); font-family: 'JetBrains Mono', monospace;">Rp 0</div>
            </div>
            <div>
                <div style="font-size: 11px; color: var(--text-muted); font-weight: 700;">SISA UTANG BERJALAN</div>
                <div id="histSjSisaUtang" style="font-size: 15px; font-weight: 800; color: var(--warn-fg); font-family: 'JetBrains Mono', monospace;">Rp 0</div>
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

        <div style="display: flex; justify-content: flex-end; margin-top: 16px; border-top: 1px solid var(--border); padding-top: 12px;">
            <button type="button" class="btn btn-outline" onclick="closeHistSjModal()">Tutup</button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 3: BUAT FAKTUR PEMBELIAN (OPERASIONAL / ASET / NON-STOK)           -->
<!-- ========================================================================= -->
<div id="modalCreatePurchaseInvoice" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 820px; margin: auto; padding: 24px; box-shadow: var(--shadow-lg); border-radius: 12px; background: var(--bg-card);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 18px;">
            <div>
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: var(--text-main);">Buat Faktur Operasional</h3>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted);">Catat tagihan biaya operasional atau aset non-stok</p>
            </div>
            <button type="button" onclick="closeCreateInvoiceModal()" style="border: none; background: transparent; font-size: 20px; color: var(--text-muted); cursor: pointer; padding: 4px;">&times;</button>
        </div>

        <form id="formCreatePurchaseInvoice" onsubmit="submitCreateInvoice(event)">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                <!-- Tipe Invoice & Debit COA -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Akun <span style="color: var(--bad-fg);">*</span></label>
                    <select id="invTipe" name="kode_akun_debit" class="form-control" required onchange="onInvoiceTypeChange(this.value)">
                        <?php if (!empty($akunFaktur['biaya'])): ?>
                        <optgroup label="Biaya">
                            <?php foreach ($akunFaktur['biaya'] as $ak): ?>
                                <option value="<?= $ak['kode'] ?>" data-tipe="OPERASIONAL" <?= $ak['kode'] === '6000' ? 'selected' : '' ?>><?= $ak['kode'] ?> - <?= htmlspecialchars($ak['nama']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                        <?php if (!empty($akunFaktur['aset'])): ?>
                        <optgroup label="Aset">
                            <?php foreach ($akunFaktur['aset'] as $ak): ?>
                                <option value="<?= $ak['kode'] ?>" data-tipe="ASET"><?= $ak['kode'] ?> - <?= htmlspecialchars($ak['nama']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Vendor / Penerima -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Vendor <span style="color: var(--bad-fg);">*</span></label>
                    <select id="invPemasokSelect" class="form-control" onchange="onVendorSelectChange(this.value)">
                        <option value="">-- Pilih Vendor --</option>
                        <?php if (!empty($vendorNonPemasokList)): ?>
                            <optgroup label="Vendor Non-Pemasok">
                                <?php foreach ($vendorNonPemasokList as $vnd): ?>
                                    <option value="<?= $vnd['id'] ?>" data-tipe="OPERASIONAL" data-nama="<?= htmlspecialchars($vnd['nama_vendor'], ENT_QUOTES) ?>">[<?= htmlspecialchars($vnd['kode_vendor']) ?>] <?= htmlspecialchars($vnd['nama_vendor']) ?> (<?= htmlspecialchars($vnd['kategori_vendor']) ?>)</option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <option value="OTHER">+ Vendor Lainnya...</option>
                    </select>
                    <input type="hidden" id="invPemasokId" name="pemasok_id" value="">
                    <input type="hidden" id="invTipeVendor" name="tipe_vendor" value="OPERASIONAL">
                    <input type="text" id="invNamaVendor" name="nama_vendor" class="form-control" placeholder="Ketik nama toko / vendor penyedia..." required style="margin-top: 6px; display: none;">
                </div>

                <!-- Tanggal Invoice -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Tanggal Faktur / Transaksi <span style="color: var(--bad-fg);">*</span></label>
                    <input type="date" id="invTanggal" name="tanggal_invoice" class="form-control" value="<?= date('Y-m-d') ?>" required oninput="hitungTempoFaktur()">
                </div>

                <!-- Jatuh Tempo -->
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Tanggal Jatuh Tempo <span style="color: var(--bad-fg);">*</span></label>
                    <select id="invTermin" class="form-control" style="margin-bottom: 6px;" onchange="hitungTempoFaktur()">
                        <?php foreach (\App\Services\AgingService::TERMIN as $hr => $lb): ?>
                            <option value="<?= $hr ?>" <?= $hr === 14 ? 'selected' : '' ?>><?= $lb ?></option>
                        <?php endforeach; ?>
                        <option value="">Atur tanggal sendiri</option>
                    </select>
                    <input type="date" id="invJatuhTempo" name="tanggal_jatuh_tempo" class="form-control" oninput="document.getElementById('invTermin').value = ''" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                </div>

                <!-- Nomor Referensi / Nota Vendor -->
                <div style="grid-column: span 2;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Nomor Nota Fisik / Bon Vendor / Surat Jalan</label>
                    <input type="text" id="invNoSj" name="no_surat_jalan" class="form-control" placeholder="Contoh: NOTA-ES-992 / BON-0482">
                </div>
            </div>

            <!-- TABEL LINE ITEMS DINAMIS -->
            <div style="margin-top: 14px; background: var(--bg-muted); border: 1px solid var(--border); border-radius: 8px; padding: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <div>
                        <span style="font-size: 12.5px; font-weight: 800; color: var(--text-main);">Rincian Item Biaya / Pembelian</span>
                        <div style="font-size: 11px; color: var(--text-muted);">Masukkan komponen pengeluaran, jumlah qty, dan harga satuan</div>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" onclick="addInvoiceItemRow()" style="font-size: 11.5px; padding: 4px 10px; font-weight: 700;">
                        <i data-lucide="plus" style="width: 13px; height: 13px; display: inline-block;"></i> Tambah Baris
                    </button>
                </div>

                <div class="table-responsive" style="max-height: 240px; overflow-y: auto;">
                    <table class="table-custom" style="font-size: 11.5px; width: 100%;">
                        <thead>
                            <tr style="background: var(--border);">
                                <th style="min-width: 220px;">Deskripsi Biaya / Barang</th>
                                <th style="width: 85px; text-align: center;">Qty</th>
                                <th style="width: 100px;">Satuan</th>
                                <th style="width: 140px; text-align: right;">Harga Satuan (Rp)</th>
                                <th style="width: 140px; text-align: right;">Subtotal (Rp)</th>
                                <th style="width: 45px; text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyInvoiceItems">
                            <!-- Injected by JS -->
                        </tbody>
                        <tfoot>
                            <tr style="background: var(--info-bg); font-weight: 800;">
                                <td colspan="4" style="text-align: right; text-transform: uppercase; font-size: 12px; color: var(--aktif-fg);">Total Tagihan Faktur:</td>
                                <td id="labelGrandTotalInvoice" style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 13px; color: var(--aktif-fg);">Rp 0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Keterangan -->
            <div style="margin-top: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Keterangan / Keperluan Operasional</label>
                <textarea name="keterangan" id="invKeterangan" class="form-control" rows="2" placeholder="Catatan opsional (misal: Pembelian es balok untuk pengawetan ikan tongkol masuk dari nelayan Karawang)..." style="font-size: 12px;"></textarea>
            </div>

            <!-- Datalist Master Tipe Biaya untuk Auto-suggest -->
            <datalist id="listTipeBiayaMaster">
                <?php foreach ($tipeBiayaList as $tb): ?>
                    <option value="<?= htmlspecialchars($tb['nama_biaya']) ?>" data-satuan="<?= htmlspecialchars($tb['satuan_default']) ?>" data-harga="<?= (float)$tb['estimasi_harga'] ?>" data-akun="<?= htmlspecialchars($tb['kode_akun']) ?>">
                        [<?= htmlspecialchars($tb['kode_akun']) ?>] <?= htmlspecialchars($tb['nama_akun']) ?> - Estimasi: Rp <?= number_format((float)$tb['estimasi_harga'], 0, ',', '.') ?>/<?= htmlspecialchars($tb['satuan_default']) ?>
                    </option>
                <?php endforeach; ?>
            </datalist>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; border-top: 1px solid var(--border); padding-top: 14px;">
                <button type="button" class="btn btn-outline" onclick="closeCreateInvoiceModal()">Batal</button>
                <button type="submit" id="btnSubmitCreateInvoice" class="btn btn-primary" style="font-weight: 700;">
                    <i data-lucide="check" style="width: 15px; height: 15px;"></i>
                    <span>Simpan Faktur</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 4: DETAIL LENGKAP FAKTUR PEMBELIAN (ITEMS, JURNAL, & HISTORI BAYAR)  -->
<!-- ========================================================================= -->
<div id="modalDetailPurchaseInvoice" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 760px; margin: auto; padding: 24px; box-shadow: var(--shadow-lg); border-radius: 12px; background: var(--bg-card);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 16px;">
            <div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <h3 id="detInvNo" style="margin: 0; font-size: 17px; font-weight: 800; color: var(--aktif-fg); font-family: 'JetBrains Mono', monospace;">-</h3>
                    <span id="detInvStatusBadge" style="display: inline-block; padding: 2px 8px; font-size: 11px; font-weight: 700; border-radius: 4px;">-</span>
                </div>
                <p id="detInvVendor" style="margin: 2px 0 0 0; font-size: 12.5px; color: var(--text-muted); font-weight: 700;">-</p>
            </div>
            <button type="button" onclick="closeDetailInvoiceModal()" style="border: none; background: transparent; font-size: 20px; color: var(--text-muted); cursor: pointer; padding: 4px;">&times;</button>
        </div>

        <!-- RINGKASAN METADATA & JURNAL COA -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
            <div style="background: var(--bg-muted); padding: 12px; border-radius: 8px; border: 1px solid var(--border); font-size: 12px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span style="color: var(--text-muted);">Tanggal Faktur:</span>
                    <strong id="detInvTgl">-</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span style="color: var(--text-muted);">Jatuh Tempo:</span>
                    <strong id="detInvTempo">-</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Ref. Surat Jalan:</span>
                    <strong id="detInvSj" style="font-family: 'JetBrains Mono', monospace;">-</strong>
                </div>
            </div>

            <div style="background: var(--bg-muted); padding: 12px; border-radius: 8px; border: 1px solid var(--border); font-size: 12px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span style="color: var(--text-muted);">Debit Akun:</span>
                    <strong id="detInvDebit" style="color: var(--aktif-fg);">-</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span style="color: var(--text-muted);">Kredit Akun:</span>
                    <strong id="detInvKredit" style="color: var(--warn-fg);">[2000] Utang Usaha</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Sisa Utang:</span>
                    <strong id="detInvSisa" style="color: var(--bad-fg); font-family: 'JetBrains Mono', monospace; font-size: 13px;">Rp 0</strong>
                </div>
            </div>
        </div>

        <!-- TABEL RINCIAN ITEM -->
        <div style="margin-bottom: 16px;">
            <div style="font-size: 12.5px; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">Rincian Barang / Biaya Faktur</div>
            <div class="table-responsive" style="max-height: 180px; overflow-y: auto;">
                <table class="table-custom" style="font-size: 11.5px; width: 100%;">
                    <thead>
                        <tr style="background: var(--bg-muted);">
                            <th style="width: 35px; text-align: center;">No</th>
                            <th>Deskripsi Item</th>
                            <th style="width: 80px; text-align: center;">Qty</th>
                            <th style="width: 80px;">Satuan</th>
                            <th style="width: 120px; text-align: right;">Harga Satuan</th>
                            <th style="width: 130px; text-align: right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="detInvItemsTbody">
                        <!-- Items -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TABEL RIWAYAT PEMBAYARAN KAS KELUAR -->
        <div>
            <div style="font-size: 12.5px; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">Riwayat Alokasi Pembayaran Kas Keluar</div>
            <div class="table-responsive" style="max-height: 160px; overflow-y: auto;">
                <table class="table-custom" style="font-size: 11.5px; width: 100%;">
                    <thead>
                        <tr style="background: var(--bg-muted);">
                            <th style="width: 35px; text-align: center;">No</th>
                            <th style="width: 140px;">No. Bukti Kas</th>
                            <th style="width: 95px;">Tgl Bayar</th>
                            <th>Metode / Bank</th>
                            <th style="width: 120px; text-align: right;">Nominal Bayar</th>
                            <th style="width: 120px; text-align: right;">Sisa Setelah</th>
                        </tr>
                    </thead>
                    <tbody id="detInvPaymentsTbody">
                        <!-- Payments -->
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 18px; border-top: 1px solid var(--border); padding-top: 14px;">
            <div id="detInvActionBayar">
                <!-- Tombol bayar jika belum lunas -->
            </div>
            <button type="button" class="btn btn-outline" onclick="closeDetailInvoiceModal()">Tutup</button>
        </div>
    </div>
</div>

<script>
let currentUnpaidSjList = [];
let currentModeAlokasi = 'MANUAL';
let targetSpecificSj = null;
let targetInvoiceId = null;

// =========================================================================
// HANDLER MODAL PEMBAYARAN KAS KELUAR
// =========================================================================
function openPaymentModal(pemasokId = null, pemasokNama = '', specificSj = null, specificSisa = null, invoiceId = null, noInvoice = '') {
    const modal = document.getElementById('modalCatatBayarPemasok');
    const selectPemasok = document.getElementById('payPemasokId');
    const inputJumlah = document.getElementById('payJumlah');
    const inputInvoiceId = document.getElementById('payInvoiceId');
    const bannerTarget = document.getElementById('bannerTargetInvoice');
    const bannerText = document.getElementById('bannerTargetInvoiceText');
    const wrapperMode = document.getElementById('wrapperModeAlokasi');

    document.getElementById('formCatatBayarPemasok').reset();
    document.getElementById('payTanggal').value = new Date().toISOString().split('T')[0];
    document.getElementById('payMetode').value = 'TRANSFER_BANK';
    toggleMetodeBayar('TRANSFER_BANK');

    targetSpecificSj = specificSj;
    targetInvoiceId = invoiceId;
    inputInvoiceId.value = invoiceId || '';

    if (invoiceId && noInvoice) {
        bannerTarget.style.display = 'block';
        bannerText.textContent = `${noInvoice} (${pemasokNama}) - Sisa Utang: Rp ${new Intl.NumberFormat('id-ID').format(specificSisa || 0)}`;
        document.getElementById('wrapperUnpaidSj').style.display = 'none';
        inputJumlah.value = specificSisa || 0;
    } else {
        bannerTarget.style.display = 'none';
        document.getElementById('wrapperUnpaidSj').style.display = 'block';
    }

    if (pemasokId && pemasokId > 0) {
        selectPemasok.value = pemasokId;
        if (!invoiceId) loadUnpaidSjForPemasok(pemasokId, specificSisa);
    } else {
        selectPemasok.value = '';
        renderUnpaidTable([]);
        if (specificSisa) inputJumlah.value = specificSisa;
    }

    modal.style.display = 'flex';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function openPaymentForInvoice(invId, vendorName, sisaUtang, noInvoice, pemasokId) {
    openPaymentModal(pemasokId > 0 ? pemasokId : null, vendorName, null, sisaUtang, invId, noInvoice);
}

function closePaymentModal() {
    document.getElementById('modalCatatBayarPemasok').style.display = 'none';
    document.getElementById('payInvoiceId').value = '';
    targetInvoiceId = null;
    targetSpecificSj = null;
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

            renderUnpaidTable(currentUnpaidSjList);
        } else {
            container.innerHTML = `<div style="text-align: center; color: var(--bad-fg); font-size: 12px; padding: 12px;">Gagal: ${data.message}</div>`;
        }
    } catch (err) {
        container.innerHTML = `<div style="text-align: center; color: var(--bad-fg); font-size: 12px; padding: 12px;">Terjadi kesalahan koneksi</div>`;
    }
}

// Render tabel daftar Surat Jalan terbuka (pilih dokumen, dibayar penuh)
function renderUnpaidTable(list) {
    const container = document.getElementById('containerUnpaidTable');

    if (!list || list.length === 0) {
        container.innerHTML = '<div style="text-align: center; color: var(--ok-fg); font-weight: 700; font-size: 12px; padding: 16px;">Tidak ada surat jalan yang terutang.</div>';
        recalcPayTotal();
        return;
    }

    let html = `
        <table class="table-custom" data-no-tools style="font-size: 11.5px; width: 100%;">
            <thead>
                <tr style="background: var(--border);">
                    <th style="padding: 6px 8px; width: 34px;"><input type="checkbox" id="chkPayAll" onchange="togglePayAll(this.checked)"></th>
                    <th style="padding: 6px 8px;">No. SJ</th>
                    <th style="padding: 6px 8px;">Tgl Terima</th>
                    <th style="text-align: right; padding: 6px 8px;">Dibayar</th>
                </tr>
            </thead>
            <tbody>
    `;

    list.forEach(sj => {
        const checked = targetSpecificSj ? (targetSpecificSj === sj.no_surat_jalan) : true;
        html += `
            <tr>
                <td style="padding: 6px 8px;">
                    <input type="checkbox" class="chk-pay-sj" ${checked ? 'checked' : ''} data-sj="${sj.no_surat_jalan}" data-sisa="${sj.sisa_utang}" onchange="recalcPayTotal()">
                </td>
                <td style="padding: 6px 8px; font-family: 'JetBrains Mono', monospace; font-weight: 700;">${sj.no_surat_jalan}</td>
                <td style="padding: 6px 8px; color: var(--text-muted);">${sj.tanggal_masuk.substring(0, 10)}</td>
                <td style="text-align: right; padding: 6px 8px; font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--warn-fg);">Rp ${new Intl.NumberFormat('id-ID').format(sj.sisa_utang)}</td>
            </tr>`;
    });

    html += `</tbody></table>`;
    container.innerHTML = html;
    recalcPayTotal();
}

function togglePayAll(on) {
    document.querySelectorAll('.chk-pay-sj').forEach(c => c.checked = on);
    recalcPayTotal();
}

// Total = jumlah sisa penuh semua surat jalan yang dicentang. Alokasi dikirim sebagai hidden input.
function recalcPayTotal() {
    if (targetInvoiceId) return;
    const form = document.getElementById('formCatatBayarPemasok');
    form.querySelectorAll('input.alok-hidden').forEach(i => i.remove());
    let total = 0;
    document.querySelectorAll('.chk-pay-sj').forEach(c => {
        const row = c.closest('tr');
        if (row) row.style.background = c.checked ? '#eff6ff' : '';
        if (!c.checked) return;
        const sisa = parseFloat(c.dataset.sisa) || 0;
        total += sisa;
        const h = document.createElement('input');
        h.type = 'hidden';
        h.className = 'alok-hidden';
        h.name = `alokasi_manual[${c.dataset.sj}]`;
        h.value = sisa;
        form.appendChild(h);
    });
    document.getElementById('payJumlah').value = total;
    const badge = document.getElementById('badgeTotalUnpaid');
    if (badge) badge.textContent = `Dibayar: Rp ${new Intl.NumberFormat('id-ID').format(total)}`;
    const all = document.getElementById('chkPayAll');
    const boxes = document.querySelectorAll('.chk-pay-sj');
    if (all) all.checked = boxes.length > 0 && [...boxes].every(c => c.checked);
}

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
                        <td style="font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--aktif-fg);">${h.no_bukti_keluar}</td>
                        <td>${h.tanggal_bayar.substring(0, 10)}</td>
                        <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 800; color: var(--ok-fg);">
                            Rp ${new Intl.NumberFormat('id-ID').format(h.nominal_alokasi)}
                        </td>
                        <td style="text-align: right; font-family: 'JetBrains Mono', monospace; color: var(--warn-fg);">
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
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: var(--bad-fg);">Gagal memuat riwayat cicilan.</td></tr>';
    }
}

function closeHistSjModal() {
    document.getElementById('modalRiwayatBayarSj').style.display = 'none';
}

// =========================================================================
// HANDLER MODAL FAKTUR PEMBELIAN OPERASIONAL (CREATE & DETAIL)
// =========================================================================
function openCreateInvoiceModal() {
    const modal = document.getElementById('modalCreatePurchaseInvoice');
    document.getElementById('formCreatePurchaseInvoice').reset();
    document.getElementById('invTanggal').value = new Date().toISOString().split('T')[0];
    
    // Set default jatuh tempo +14 hari
    const due = new Date();
    due.setDate(due.getDate() + 14);
    document.getElementById('invJatuhTempo').value = due.toISOString().split('T')[0];

    document.getElementById('invTipe').value = '6000';
    document.getElementById('invPemasokSelect').value = '';
    document.getElementById('invPemasokId').value = '';
    document.getElementById('invNamaVendor').style.display = 'none';
    document.getElementById('invNamaVendor').value = '';

    // Reset baris item dengan 1 baris kosong default
    const tbody = document.getElementById('tbodyInvoiceItems');
    tbody.innerHTML = '';
    addInvoiceItemRow();

    modal.style.display = 'flex';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeCreateInvoiceModal() {
    document.getElementById('modalCreatePurchaseInvoice').style.display = 'none';
}

function onInvoiceTypeChange(val) {
    // Label deskripsi otomatis update
}

function onVendorSelectChange(val) {
    const select = document.getElementById('invPemasokSelect');
    const inputNama = document.getElementById('invNamaVendor');
    const inputId = document.getElementById('invPemasokId');
    const inputTipe = document.getElementById('invTipeVendor');

    if (val === 'OTHER') {
        inputId.value = '0';
        if (inputTipe) inputTipe.value = 'OPERASIONAL';
        inputNama.value = '';
        inputNama.style.display = 'block';
        inputNama.focus();
    } else if (val) {
        inputId.value = val;
        const opt = select.options[select.selectedIndex];
        inputNama.value = opt.getAttribute('data-nama') || '';
        if (inputTipe) inputTipe.value = opt.getAttribute('data-tipe') || 'OPERASIONAL';
        inputNama.style.display = 'none';
    } else {
        inputId.value = '';
        if (inputTipe) inputTipe.value = 'OPERASIONAL';
        inputNama.value = '';
        inputNama.style.display = 'none';
    }
}

function onDeskripsiBiayaPick(input) {
    const val = input.value.trim().toLowerCase();
    const datalist = document.getElementById('listTipeBiayaMaster');
    if (!datalist) return;
    const opts = datalist.querySelectorAll('option');
    for (let opt of opts) {
        if (opt.value.trim().toLowerCase() === val) {
            const tr = input.closest('tr');
            if (tr) {
                const satInput = tr.querySelector('.item-satuan');
                const hrgInput = tr.querySelector('.item-harga');
                if (satInput && opt.dataset.satuan) satInput.value = opt.dataset.satuan;
                const akunSelect = document.getElementById('invTipe');
                if (akunSelect && opt.dataset.akun && akunSelect.querySelector('option[value="' + opt.dataset.akun + '"]')) akunSelect.value = opt.dataset.akun;
                if (hrgInput && opt.dataset.harga && parseFloat(opt.dataset.harga) > 0) {
                    hrgInput.value = opt.dataset.harga;
                    calcInvoiceTotals();
                }
            }
            break;
        }
    }
}

function hitungTempoFaktur() {
    const t = document.getElementById('invTermin');
    const tgl = document.getElementById('invTanggal').value;
    if (!t || t.value === '' || !tgl) return;
    const d = new Date(tgl + 'T00:00:00');
    d.setDate(d.getDate() + parseInt(t.value, 10));
    const pad = n => String(n).padStart(2, '0');
    document.getElementById('invJatuhTempo').value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
}

function addInvoiceItemRow(deskripsi = '', qty = '', satuan = '', harga = '') {
    const tbody = document.getElementById('tbodyInvoiceItems');
    const rowId = 'row_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
    const subtotal = (Number(qty) || 0) * (Number(harga) || 0);

    const tr = document.createElement('tr');
    tr.id = rowId;
    tr.className = 'item-row';
    tr.innerHTML = `
        <td style="padding: 6px 8px;">
            <input type="text" class="form-control item-deskripsi" list="listTipeBiayaMaster" value="${deskripsi}" placeholder="Contoh: Es Balok / Box Styrofoam..." required style="font-size: 11.5px; padding: 4px 8px;" oninput="onDeskripsiBiayaPick(this)">
        </td>
        <td style="padding: 6px 8px;">
            <input type="number" class="form-control item-qty" value="${qty}" min="0.01" step="any" required style="font-size: 11.5px; text-align: center; padding: 4px;" oninput="calcInvoiceTotals()">
        </td>
        <td style="padding: 6px 8px;">
            <input type="text" class="form-control item-satuan" value="${satuan}" placeholder="Balok/Pcs" required style="font-size: 11.5px; padding: 4px 8px;">
        </td>
        <td style="padding: 6px 8px;">
            <input type="number" class="form-control item-harga" value="${harga}" min="0" step="any" required style="font-size: 11.5px; text-align: right; font-family: 'JetBrains Mono', monospace; padding: 4px 8px;" oninput="calcInvoiceTotals()">
        </td>
        <td style="padding: 6px 8px; text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--aktif-fg);" class="item-subtotal-text">
            Rp ${new Intl.NumberFormat('id-ID').format(subtotal)}
        </td>
        <td style="text-align: center; padding: 6px 8px;">
            <button type="button" class="btn btn-outline btn-sm" onclick="removeInvoiceItemRow('${rowId}')" style="padding: 2px 6px; color: var(--bad-fg); border-color: var(--border-hover);">
                <i data-lucide="x" style="width: 12px; height: 12px;"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    calcInvoiceTotals();
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function removeInvoiceItemRow(rowId) {
    const row = document.getElementById(rowId);
    if (row) row.remove();
    calcInvoiceTotals();
}

function calcInvoiceTotals() {
    const rows = document.querySelectorAll('#tbodyInvoiceItems .item-row');
    let grandTotal = 0;

    rows.forEach(r => {
        const qty = parseFloat(r.querySelector('.item-qty').value) || 0;
        const harga = parseFloat(r.querySelector('.item-harga').value) || 0;
        const sub = Math.round(qty * harga * 100) / 100;
        grandTotal += sub;
        r.querySelector('.item-subtotal-text').textContent = `Rp ${new Intl.NumberFormat('id-ID').format(sub)}`;
    });

    document.getElementById('labelGrandTotalInvoice').textContent = `Rp ${new Intl.NumberFormat('id-ID').format(grandTotal)}`;
}

async function submitCreateInvoice(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitCreateInvoice');
    const akunSel = document.getElementById('invTipe');
    const kodeAkun = akunSel.value;
    const tipe = (akunSel.options[akunSel.selectedIndex] || {}).dataset ? (akunSel.options[akunSel.selectedIndex].dataset.tipe || 'OPERASIONAL') : 'OPERASIONAL';
    const pemasokId = document.getElementById('invPemasokId').value;
    const namaVendor = document.getElementById('invNamaVendor').value.trim();
    const tgl = document.getElementById('invTanggal').value;
    const tempo = document.getElementById('invJatuhTempo').value;
    const noSj = document.getElementById('invNoSj').value.trim();
    const keterangan = document.getElementById('invKeterangan').value.trim();

    if (!namaVendor) {
        alert('Nama vendor/toko wajib diisi.');
        return;
    }

    const rows = document.querySelectorAll('#tbodyInvoiceItems .item-row');
    if (rows.length === 0) {
        alert('Minimal harus ada 1 item biaya.');
        return;
    }

    const items = [];
    rows.forEach(r => {
        const deskripsi = r.querySelector('.item-deskripsi').value.trim();
        const qty = parseFloat(r.querySelector('.item-qty').value) || 0;
        const satuan = r.querySelector('.item-satuan').value.trim() || 'Pcs';
        const harga = parseFloat(r.querySelector('.item-harga').value) || 0;
        const subtotal = Math.round(qty * harga * 100) / 100;

        if (deskripsi && qty > 0) {
            items.push({
                deskripsi: deskripsi,
                qty: qty,
                satuan: satuan,
                harga_satuan: harga,
                subtotal: subtotal
            });
        }
    });

    if (items.length === 0) {
        alert('Item biaya tidak valid atau deskripsi belum diisi.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = 'Membukukan...';

    const formData = new FormData();
    formData.append('tipe_invoice', tipe);
    formData.append('kode_akun_debit', kodeAkun);
    formData.append('pemasok_id', pemasokId || 0);
    formData.append('nama_vendor', namaVendor);
    formData.append('tanggal_invoice', tgl);
    formData.append('tanggal_jatuh_tempo', tempo);
    formData.append('no_surat_jalan', noSj);
    formData.append('keterangan', keterangan);
    formData.append('items_json', JSON.stringify(items));

    try {
        const resp = await fetch('index.php?action=create_purchase_invoice', {
            method: 'POST',
            body: formData
        });
        const res = await resp.json();

        if (res.success) {
            alert(`Berhasil! ${res.message}`);
            closeCreateInvoiceModal();
            window.location.href = 'index.php?page=ap_aging&tab=invoices';
        } else {
            alert(`Gagal: ${res.message}`);
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="check" style="width: 15px; height: 15px;"></i><span>Simpan &amp; Bukukan Faktur Pembelian</span>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    } catch (err) {
        alert('Terjadi kesalahan jaringan saat membukukan faktur.');
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" style="width: 15px; height: 15px;"></i><span>Simpan &amp; Bukukan Faktur Pembelian</span>';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

// Modal Detail Faktur Pembelian
async function viewInvoiceDetail(id) {
    const modal = document.getElementById('modalDetailPurchaseInvoice');
    document.getElementById('detInvNo').textContent = 'Memuat...';
    document.getElementById('detInvStatusBadge').textContent = '...';
    document.getElementById('detInvVendor').textContent = '';
    document.getElementById('detInvItemsTbody').innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 16px; color: var(--text-muted);"><i data-lucide="loader-2" class="animate-spin" style="width: 15px; height: 15px; display: inline-block;"></i> Mengambil rincian...</td></tr>';
    document.getElementById('detInvPaymentsTbody').innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 16px; color: var(--text-muted);">Memuat histori kas keluar...</td></tr>';
    document.getElementById('detInvActionBayar').innerHTML = '';

    modal.style.display = 'flex';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    try {
        const resp = await fetch(`index.php?action=get_purchase_invoice_detail&id=${id}`);
        const res = await resp.json();

        if (res.success && res.invoice) {
            const inv = res.invoice;
            document.getElementById('detInvNo').textContent = inv.no_invoice;
            
            const badge = document.getElementById('detInvStatusBadge');
            if (inv.status_bayar === 'LUNAS') {
                badge.style.background = '#d1fae5';
                badge.style.color = '#065f46';
                badge.textContent = 'LUNAS';
            } else if (inv.status_bayar === 'SEBAGIAN') {
                badge.style.background = '#fef3c7';
                badge.style.color = '#92400e';
                badge.textContent = 'CICIL / SEBAGIAN';
            } else {
                badge.style.background = '#fee2e2';
                badge.style.color = '#991b1b';
                badge.textContent = 'BELUM BAYAR';
            }

            document.getElementById('detInvVendor').textContent = `Vendor: ${inv.nama_vendor}`;
            document.getElementById('detInvTgl').textContent = inv.tanggal_invoice.substring(0, 10);
            document.getElementById('detInvTempo').textContent = inv.tanggal_jatuh_tempo.substring(0, 10);
            document.getElementById('detInvSj').textContent = inv.no_surat_jalan || 'Non-SJ (Operasional)';
            document.getElementById('detInvDebit').textContent = `[${inv.kode_akun_debit}] ${inv.nama_akun_debit}`;
            document.getElementById('detInvKredit').textContent = `[${inv.kode_akun_kredit}] ${inv.nama_akun_kredit}`;
            document.getElementById('detInvSisa').textContent = `Rp ${new Intl.NumberFormat('id-ID').format(inv.sisa_utang)}`;

            // Render Items
            const itemsTbody = document.getElementById('detInvItemsTbody');
            if (inv.items && inv.items.length > 0) {
                let htmlIt = '';
                let no = 1;
                inv.items.forEach(it => {
                    htmlIt += `
                        <tr>
                            <td style="text-align: center; color: var(--text-muted);">${no++}</td>
                            <td><strong>${it.deskripsi}</strong></td>
                            <td style="text-align: center;">${it.qty}</td>
                            <td>${it.satuan}</td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace;">Rp ${new Intl.NumberFormat('id-ID').format(it.harga_satuan)}</td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--aktif-fg);">Rp ${new Intl.NumberFormat('id-ID').format(it.subtotal)}</td>
                        </tr>
                    `;
                });
                itemsTbody.innerHTML = htmlIt;
            } else {
                itemsTbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 14px; color: var(--text-muted);">Tidak ada rincian baris item.</td></tr>';
            }

            // Render Payments
            const payTbody = document.getElementById('detInvPaymentsTbody');
            if (inv.payments && inv.payments.length > 0) {
                let htmlPay = '';
                let noP = 1;
                inv.payments.forEach(p => {
                    htmlPay += `
                        <tr>
                            <td style="text-align: center; color: var(--text-muted);">${noP++}</td>
                            <td style="font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--aktif-fg);">${p.no_bukti_keluar}
                                ${p.status_pembayaran === 'BATAL'
                                    ? `<div><span style="display:inline-block;padding:1px 6px;background:var(--border);color:var(--text-muted);font-size:10.5px;border-radius:4px;font-family:inherit;">Dibatalkan</span></div><div style="font-size:10.5px;color:var(--text-muted);font-weight:500;font-family:inherit;">${p.alasan_batal || ''}</div>`
                                    : `<div><a href="javascript:void(0)" onclick="closeDetailInvoiceModal(); batalDokumen('batal_bayar_ap', { id: ${p.pembayaran_id} }, 'pembayaran ${p.no_bukti_keluar} (Rp ${new Intl.NumberFormat('id-ID').format(p.total_pembayaran)})')" style="font-size:11px;color:var(--bad-fg);font-weight:700;font-family:inherit;">Batalkan pembayaran</a></div>`}
                            </td>
                            <td>${p.tanggal_bayar.substring(0, 10)}</td>
                            <td>${p.metode_bayar} ${p.bank_sumber ? '• ' + p.bank_sumber : ''}</td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 800; color: ${p.status_pembayaran === 'BATAL' ? '#94a3b8; text-decoration: line-through' : '#047857'};">Rp ${new Intl.NumberFormat('id-ID').format(p.status_pembayaran === 'BATAL' ? p.nominal_asal : p.nominal_alokasi)}</td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; color: var(--warn-fg);">Rp ${new Intl.NumberFormat('id-ID').format(p.sisa_utang_setelah)}</td>
                        </tr>
                    `;
                });
                payTbody.innerHTML = htmlPay;
            } else {
                payTbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 14px; color: var(--text-muted);">Belum ada pencairan kas keluar untuk faktur ini.</td></tr>';
            }

            // Tombol Bayar
            if (parseFloat(inv.sisa_utang) > 0.01) {
                document.getElementById('detInvActionBayar').innerHTML = `
                    <button type="button" class="btn btn-primary" onclick="closeDetailInvoiceModal(); openPaymentForInvoice(${inv.id}, '${inv.nama_vendor.replace(/'/g, "\\'")}', ${inv.sisa_utang}, '${inv.no_invoice}', ${inv.pemasok_id || 0});" style="font-size: 12px; font-weight: 700;">
                        <i data-lucide="credit-card" style="width: 14px; height: 14px; display: inline-block;"></i> Bayar Faktur Ini (Sisa Rp ${new Intl.NumberFormat('id-ID').format(inv.sisa_utang)})
                    </button>
                `;
            }
            if (typeof lucide !== 'undefined') lucide.createIcons();

        } else {
            alert(res.message || 'Gagal memuat rincian faktur.');
            closeDetailInvoiceModal();
        }
    } catch (err) {
        alert('Terjadi kesalahan koneksi.');
        closeDetailInvoiceModal();
    }
}

function closeDetailInvoiceModal() {
    document.getElementById('modalDetailPurchaseInvoice').style.display = 'none';
}

// Tutup modal jika klik di luar area card
window.addEventListener('click', function(e) {
    const modal1 = document.getElementById('modalCatatBayarPemasok');
    const modal2 = document.getElementById('modalRiwayatBayarSj');
    const modal3 = document.getElementById('modalCreatePurchaseInvoice');
    const modal4 = document.getElementById('modalDetailPurchaseInvoice');
    if (e.target === modal1) closePaymentModal();
    if (e.target === modal2) closeHistSjModal();
    if (e.target === modal3) closeCreateInvoiceModal();
    if (e.target === modal4) closeDetailInvoiceModal();
});
</script>

<?php if ($btBoleh) { $btJenis = 'ap'; $btPihakId = $selectedPemasokId; include __DIR__ . '/layout/bayar_terpilih.php'; } ?>
<?php require_once __DIR__ . '/layout/footer.php'; ?>

