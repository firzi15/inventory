<?php
/**
 * Halaman AR Aging (Piutang Usaha) & Alokasi Setoran Parsial Multi-Nota
 * Bagian dari Modul Finance & Kas Masuk WMS
 */

use App\Core\Helper;
use App\Models\Mitra;
use App\Models\Piutang;

$pageTitle = 'AR Aging & Piutang Mitra';
$activeTab = $_GET['tab'] ?? 'matriks';
if (!in_array($activeTab, ['matriks', 'nota', 'riwayat_setoran'])) {
    $activeTab = 'matriks';
}

$mitraList = Mitra::getAll();
$selectedMitraId = (int)($_GET['mitra_id'] ?? 0);
$tampil = 'mitra'; // matriks AR Aging selalu per mitra
$kpi = Piutang::getSummaryKpi($selectedMitraId);
$agingMitra = Piutang::getAgingSummaryPerMitra($selectedMitraId);

// Tampilan matriks per nota: hanya yang masih terutang, paling telat di atas
$agRows = [];
if ($activeTab === 'matriks' && $tampil === 'nota') {
    $notaAging = Piutang::getNotaList(['mitra_id' => $selectedMitraId, 'status' => 'BELUM_LUNAS']);
    usort($notaAging, fn($a, $b) => $b['hari_telat'] <=> $a['hari_telat'] ?: strcmp($a['created_at'], $b['created_at']));
    foreach ($notaAging as $n) {
        $agRows[] = ['no_dok' => $n['no_surat_jalan'], 'tanggal' => $n['created_at'], 'pihak' => '[' . $n['kode_mitra'] . '] ' . $n['nama_mitra'],
            'tempo' => $n['jatuh_tempo'], 'tagihan' => $n['total_nilai_jual'], 'bayar' => $n['total_bayar'], 'sisa' => $n['sisa_piutang'], 'telat' => $n['hari_telat'], 'rincian' => ['keluar', (int)$n['id']]];
    }
}

// Filter untuk Tab Nota
$selectedStatus  = trim($_GET['status'] ?? 'SEMUA');
$searchKeyword   = trim($_GET['search'] ?? '');

$notaList = Piutang::getNotaList([
    'mitra_id' => $selectedMitraId,
    'status'   => $selectedStatus,
    'search'   => $searchKeyword
]);

// Data untuk Tab Riwayat Setoran
$riwayatPayments = Piutang::getAllPayments([
    'mitra_id' => $selectedMitraId,
    'search'   => $searchKeyword
]);

// Setor gelondongan: checklist hanya muncul setelah mitra dipilih di filter
$btBoleh = $activeTab === 'nota' && $selectedMitraId > 0;
$btPihakNama = '';
foreach ($mitraList as $mBt) { if ((int)$mBt['id'] === $selectedMitraId) $btPihakNama = '[' . $mBt['kode_mitra'] . '] ' . $mBt['nama_mitra']; }

require_once __DIR__ . '/layout/header.php';
?>

<div class="content-wrapper">

    <!-- PAGE HEADER -->
    <div class="page-header no-print">
        <div>
            <h1 class="page-title"><?= $activeTab === 'nota' ? 'Payment In' : 'AR Aging' ?></h1>
            <p class="page-subtitle"><?= $activeTab === 'nota' ? 'Nota penjualan ke mitra dan setoran' : 'Umur piutang dan setoran mitra' ?></p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <?php if ($activeTab === 'nota'): ?>
            <button type="button" class="btn btn-primary" onclick="openPaymentModal()" style="display: flex; align-items: center; gap: 6px; font-weight: 700;">
                <i data-lucide="plus-circle" style="width: 16px; height: 16px;"></i>
                <span>Catat Setoran</span>
            </button>
            <?php endif; ?>
            <?php $eksporNama = 'AR_Aging_' . $activeTab; $eksporJudul = ($activeTab === 'nota' ? 'Payment In' : 'AR Aging'); include __DIR__ . '/layout/ekspor.php'; ?>
        </div>
    </div>

    <!-- 4 KARTU KPI AR AGING KEUANGAN -->
    <div class="grid grid-cols-4 no-print" style="gap: 16px; margin-bottom: 22px;">
        <!-- Card 1: Total Piutang Terbit -->
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Total Nilai Tagihan</div>
                <div class="kpi-value"><?= Helper::formatRupiah($kpi['total_piutang_terbit']) ?></div>
                <div class="kpi-sub">Dari total <strong><?= number_format($kpi['total_nota']) ?></strong> nota pengeluaran</div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="receipt" style="width: 18px; height: 18px;"></i></div>
        </div>

        <!-- Card 2: Total Setoran Masuk -->
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Total Setoran Kas Terkumpul</div>
                <div class="kpi-value"><?= Helper::formatRupiah($kpi['total_setoran_masuk']) ?></div>
                <div class="kpi-sub">Tercatat <strong><?= number_format($kpi['total_transaksi_bayar']) ?></strong> kali setoran kas / bank</div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="wallet" style="width: 18px; height: 18px;"></i></div>
        </div>

        <!-- Card 3: Sisa Piutang Berjalan -->
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Sisa Piutang Belum Lunas</div>
                <div class="kpi-value"><?= Helper::formatRupiah($kpi['sisa_piutang_aktif']) ?></div>
                <div class="kpi-sub">Belum jatuh tempo: <strong><?= Helper::formatRupiah($kpi['aging_belum']) ?></strong></div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="hourglass" style="width: 18px; height: 18px;"></i></div>
        </div>

        <!-- Card 4: Piutang Lewat Jatuh Tempo -->
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Piutang Lewat Jatuh Tempo</div>
                <div class="kpi-value"><?= Helper::formatRupiah($kpi['total_overdue']) ?></div>
                <div class="kpi-sub">Telat &gt;30 hari: <strong><?= Helper::formatRupiah($kpi['aging_gt_30']) ?></strong></div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="alert-circle" style="width: 18px; height: 18px;"></i></div>
        </div>
    </div>


    <!-- ========================================================================= -->
    <!-- TAB 1: MATRIKS UMUR PIUTANG PER MITRA (AGING BUCKETS)                    -->
    <!-- ========================================================================= -->
    <?php if ($activeTab === 'matriks'): ?>
        <div class="card" style="padding: 20px;">
            <form method="GET" action="index.php" class="no-print" style="margin-bottom: 18px; display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                <input type="hidden" name="page" value="ar_aging">
                <input type="hidden" name="tab" value="matriks">
                <div style="flex: 1; min-width: 180px;">
                    <label class="form-label" style="font-size: 11.5px; font-weight: 700;">Filter Mitra:</label>
                    <select name="mitra_id" class="form-control" onchange="this.form.submit()" style="height: 38px;">
                        <option value="0">-- Semua Mitra Bisnis --</option>
                        <?php foreach ($mitraList as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $selectedMitraId === (int)$m['id'] ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($m['kode_mitra']) ?>] <?= htmlspecialchars($m['nama_mitra']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($selectedMitraId > 0): ?>
                    <div><a href="index.php?page=ar_aging&tab=matriks" class="btn btn-outline" style="height: 38px; display: inline-flex; align-items: center;">Reset Filter</a></div>
                <?php endif; ?>
            </form>
            <?php if ($tampil === 'nota'):
                $agLabelDok = 'No. Surat Jalan / Bon'; $agLabelPihak = 'Mitra'; $agLabelTagihan = 'Total Belanja'; $agLabelBayar = 'Total Setoran'; $agLabelSisa = 'Sisa Piutang';
                include __DIR__ . '/layout/aging_dokumen.php';
            else: ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 class="card-title" style="margin: 0; font-size: 16px;"><?= $selectedMitraId > 0 ? 'Umur Piutang Mitra Terpilih' : 'Rekapitulasi Umur Piutang Mitra Bisnis' ?></h2>
                    <p class="card-description" style="margin: 2px 0 0 0; font-size: 12.5px;">Klasifikasi sisa tagihan per mitra berdasarkan jatuh tempo nota: belum jatuh tempo, dan terlambat 1-7, 8-14, 15-30, atau lebih dari 30 hari.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-custom" data-halaman="25">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">No</th>
                            <th style="min-width: 180px;">Mitra Bisnis</th>
                            <th style="width: 100px; text-align: center;">Nota Terbuka</th>
                            <th style="text-align: right; min-width: 130px;">Total Belanja</th>
                            <th style="text-align: right; min-width: 130px;">Total Setoran</th>
                            <th style="text-align: right; min-width: 135px; background: var(--bg-muted);">Sisa Piutang</th>
                            <th style="text-align: right; min-width: 120px; color: var(--aktif-fg);">Belum Jatuh Tempo</th>
                            <th style="text-align: right; min-width: 110px; color: var(--ok-fg);">Telat 1 - 7 Hari</th>
                            <th style="text-align: right; min-width: 110px; color: var(--warn-fg);">Telat 8 - 14 Hari</th>
                            <th style="text-align: right; min-width: 110px; color: var(--warn-fg);">Telat 15 - 30 Hari</th>
                            <th style="text-align: right; min-width: 110px; color: var(--bad-fg);">Telat &gt; 30 Hari</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        $totBelanja = 0.0;
                        $totSetor = 0.0;
                        $totSisa = 0.0;
                        $totBelum = 0.0;
                        $tot0to7 = 0.0;
                        $tot8to14 = 0.0;
                        $tot15to30 = 0.0;
                        $totGt30 = 0.0;

                        foreach ($agingMitra as $m): 
                            $totBelanja += $m['total_belanja'];
                            $totSetor   += $m['total_setoran'];
                            $totSisa    += $m['sisa_piutang'];
                            $totBelum   += $m['aging_belum'];
                            $tot0to7    += $m['aging_1_7'];
                            $tot8to14   += $m['aging_8_14'];
                            $tot15to30  += $m['aging_15_30'];
                            $totGt30    += $m['aging_gt_30'];
                        ?>
                        <tr>
                            <td style="text-align: center; color: var(--text-muted);"><?= $no++ ?></td>
                            <td>
                                <strong style="color: var(--text-main);">[<?= htmlspecialchars($m['kode_mitra']) ?>] <?= htmlspecialchars($m['nama_mitra']) ?></strong>
                                <div style="font-size: 11px; color: var(--text-muted); margin-top: 1px;">
                                    PIC: <?= htmlspecialchars($m['pic_mitra'] ?: '-') ?> <?= !empty($m['telepon_mitra']) ? '• ' . htmlspecialchars($m['telepon_mitra']) : '' ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($m['nota_terbuka'] > 0): ?>
                                    <span style="font-weight: 800; color: var(--warn-fg);"><?= $m['nota_terbuka'] ?> Nota</span>
                                <?php else: ?>
                                    <span style="color: var(--ok-fg); font-weight: 700;">Lunas</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px;">
                                <?= Helper::formatRupiah($m['total_belanja']) ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--ok-fg);">
                                <?= Helper::formatRupiah($m['total_setoran']) ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 800; background: var(--bg-muted); color: <?= $m['sisa_piutang'] > 0 ? '#b45309' : '#047857' ?>;">
                                <?= Helper::formatRupiah($m['sisa_piutang']) ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12px; color: <?= $m['aging_belum'] > 0 ? '#0369a1' : '#94a3b8' ?>;">
                                <?= $m['aging_belum'] > 0 ? Helper::formatRupiah($m['aging_belum']) : '-' ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12px; color: <?= $m['aging_1_7'] > 0 ? '#15803d' : '#94a3b8' ?>;">
                                <?= $m['aging_1_7'] > 0 ? Helper::formatRupiah($m['aging_1_7']) : '-' ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12px; color: <?= $m['aging_8_14'] > 0 ? '#b45309' : '#94a3b8' ?>;">
                                <?= $m['aging_8_14'] > 0 ? Helper::formatRupiah($m['aging_8_14']) : '-' ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12px; color: <?= $m['aging_15_30'] > 0 ? '#c2410c' : '#94a3b8' ?>;">
                                <?= $m['aging_15_30'] > 0 ? Helper::formatRupiah($m['aging_15_30']) : '-' ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: <?= $m['aging_gt_30'] > 0 ? '800' : 'normal' ?>; color: <?= $m['aging_gt_30'] > 0 ? '#b91c1c' : '#94a3b8' ?>;">
                                <?= $m['aging_gt_30'] > 0 ? Helper::formatRupiah($m['aging_gt_30']) : '-' ?>
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
    <!-- TAB 2: PELACAKAN NOTA PIUTANG (LEVEL NOTA DOKUMEN)                       -->
    <!-- ========================================================================= -->
    <?php if ($activeTab === 'nota'): ?>
        <div class="card" style="padding: 20px;">
            <!-- Filter Bar -->
            <form method="GET" action="index.php" class="no-print" style="margin-bottom: 18px; display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                <input type="hidden" name="page" value="ar_aging">
                <input type="hidden" name="tab" value="nota">

                <div style="flex: 1; min-width: 180px;">
                    <label class="form-label" style="font-size: 11.5px; font-weight: 700;">Filter Mitra:</label>
                    <select name="mitra_id" class="form-control" onchange="this.form.submit()" style="height: 38px;">
                        <option value="0">-- Semua Mitra Bisnis --</option>
                        <?php foreach ($mitraList as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $selectedMitraId === (int)$m['id'] ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($m['kode_mitra']) ?>] <?= htmlspecialchars($m['nama_mitra']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="width: 180px;">
                    <label class="form-label" style="font-size: 11.5px; font-weight: 700;">Status Pembayaran:</label>
                    <select name="status" class="form-control" onchange="this.form.submit()" style="height: 38px;">
                        <option value="SEMUA" <?= $selectedStatus === 'SEMUA' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="BELUM_LUNAS" <?= $selectedStatus === 'BELUM_LUNAS' ? 'selected' : '' ?>>Semua Belum Lunas</option>
                        <option value="BELUM_BAYAR" <?= $selectedStatus === 'BELUM_BAYAR' ? 'selected' : '' ?>>Belum Ada Setoran</option>
                        <option value="PARSIAL" <?= $selectedStatus === 'PARSIAL' ? 'selected' : '' ?>>Bayar Parsial</option>
                        <option value="LUNAS" <?= $selectedStatus === 'LUNAS' ? 'selected' : '' ?>>Sudah Lunas</option>
                    </select>
                </div>

                <div style="flex: 1.5; min-width: 200px;">
                    <label class="form-label" style="font-size: 11.5px; font-weight: 700;">Cari Dokumen / Surat Jalan:</label>
                    <div style="display: flex; gap: 6px;">
                        <input type="text" name="search" class="form-control" placeholder="Ketik nomor surat jalan / bon..." value="<?= htmlspecialchars($searchKeyword) ?>" style="height: 38px;">
                        <button type="submit" class="btn btn-outline" style="height: 38px; padding: 0 14px;">Cari</button>
                    </div>
                </div>

                <?php if ($selectedMitraId > 0 || $selectedStatus !== 'SEMUA' || !empty($searchKeyword)): ?>
                    <div>
                        <a href="index.php?page=ar_aging&tab=nota" class="btn btn-outline" style="height: 38px; display: inline-flex; align-items: center;">Reset Filter</a>
                    </div>
                <?php endif; ?>
                <div>
                    <?php if ($btBoleh): ?>
                        <button type="button" id="btnBayarTerpilih" class="btn btn-primary" onclick="bukaBayarTerpilih()" disabled
                                style="height: 38px; padding: 0 14px; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px;" title="Centang dokumen di tabel, lalu klik untuk menyetor sekaligus">
                            <i data-lucide="wallet" style="width: 14px; height: 14px;"></i><span id="lblBayarTerpilih">Setor Terpilih</span>
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline" disabled title="Pilih mitra di filter dulu untuk menyetor beberapa dokumen sekaligus"
                                style="height: 38px; padding: 0 14px; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; opacity: .6;">
                            <i data-lucide="wallet" style="width: 14px; height: 14px;"></i><span>Setor Terpilih</span>
                        </button>
                    <?php endif; ?>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table-custom" data-halaman="25">
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center;">No</th>
                            <?php if ($btBoleh): ?><th class="no-print" style="width: 34px; text-align: center;"><input type="checkbox" id="cekSemuaBayar" onchange="pilihSemuaBayar(this.checked)" title="Pilih semua yang tampil"></th><?php endif; ?>
                            <th style="min-width: 150px;">No. Surat Jalan / Bon</th>
                            <th style="width: 115px;">Tanggal Terbit</th>
                            <th style="min-width: 160px;">Mitra Penerima</th>
                            <th style="text-align: right; width: 120px;">Total Belanja</th>
                            <th style="text-align: right; width: 120px;">Total Setoran</th>
                            <th style="text-align: right; width: 125px; background: var(--bg-muted);">Sisa Tagihan</th>
                            <th style="width: 130px; text-align: center;">Jatuh Tempo</th>
                            <th style="width: 110px; text-align: center;">Status</th>
                            <th class="no-print" style="width: 140px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notaList)): ?>
                            <tr>
                                <td colspan="<?= $btBoleh ? 11 : 10 ?>" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                    Tidak ada data nota piutang yang sesuai dengan filter yang dipilih.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $no = 1;
                            foreach ($notaList as $n): 
                                $telat = (int)$n['hari_telat'];
                                $bolehTempo = \App\Core\Akses::boleh(strtoupper($_SESSION['user_login']['role'] ?? ''), ['ADMIN', 'SUPERVISOR', 'FINANCE']);
                            ?>
                            <tr>
                                <td style="text-align: center; color: var(--text-muted);"><?= $no++ ?></td>
                                <?php if ($btBoleh): ?>
                                    <td class="no-print" style="text-align: center;">
                                        <?php if ((float)$n['sisa_piutang'] > 0.01): ?>
                                            <input type="checkbox" class="cek-bayar-ar" value="<?= (int)$n['id'] ?>" onchange="perbaruiTombolBayar()"
                                                   data-no="<?= htmlspecialchars($n['no_surat_jalan']) ?>" data-sisa="<?= (float)$n['sisa_piutang'] ?>"
                                                   data-tempo="<?= htmlspecialchars(substr((string)$n['jatuh_tempo'], 0, 10)) ?>" data-tgl="<?= htmlspecialchars((string)$n['created_at']) ?>">
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <strong class="rsj-link" onclick="bukaRincianSj('keluar', <?= (int)$n['id'] ?>)" title="Lihat rincian box" style="font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--text-main);">
                                        <?= htmlspecialchars($n['no_surat_jalan']) ?>
                                    </strong>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                        TRX: <?= htmlspecialchars($n['no_transaksi']) ?> • <?= Helper::formatKg($n['total_kg']) ?> Kg
                                    </div>
                                </td>
                                <td style="font-size: 12px; color: var(--text-main);">
                                    <?= date('d/m/Y', strtotime($n['created_at'])) ?>
                                    <div style="font-size: 10.5px; color: var(--text-muted);"><?= date('H:i', strtotime($n['created_at'])) ?> WIB</div>
                                </td>
                                <td>
                                    <strong style="color: var(--text-main);">[<?= htmlspecialchars($n['kode_mitra']) ?>] <?= htmlspecialchars($n['nama_mitra']) ?></strong>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($n['pic_nama'] ?: ($n['pic_mitra'] ?: '-')) ?></div>
                                </td>
                                <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px;">
                                    <?= Helper::formatRupiah($n['total_nilai_jual']) ?>
                                </td>
                                <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--ok-fg);">
                                    <?= Helper::formatRupiah($n['total_bayar']) ?>
                                    <?php if ($n['jml_kali_setor'] > 0): ?>
                                        <div style="font-size: 10.5px; color: var(--text-muted);"><?= $n['jml_kali_setor'] ?>x cicilan</div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 800; background: var(--bg-muted); color: <?= $n['sisa_piutang'] > 0 ? '#b45309' : '#047857' ?>;">
                                    <?= Helper::formatRupiah($n['sisa_piutang']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($n['sisa_piutang'] > 0): [$stLbl, $stCss] = \App\Services\AgingService::status($telat); ?>
                                        <div style="font-size: 12px; font-weight: 700;"><?= date('d/m/Y', strtotime($n['jatuh_tempo'])) ?></div>
                                        <span class="badge" style="<?= $stCss ?> font-size: 10.5px; font-weight: 700;"><?= $stLbl ?></span>
                                        <?php if ($bolehTempo): ?>
                                            <div class="no-print"><a href="javascript:void(0)" style="font-size: 11px;" onclick='ubahJatuhTempo("NOTA", <?= (int)$n['id'] ?>, <?= json_encode(substr($n['jatuh_tempo'], 0, 10)) ?>, <?= json_encode($n['no_surat_jalan']) ?>)'>ubah</a></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--ok-fg); font-weight: 700; font-size: 12px;">Selesai</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($n['status_bayar'] === 'LUNAS'): ?>
                                        <span style="font-weight: 800; font-size: 11px; color: var(--ok-fg); background: var(--ok-bg); padding: 2px 8px; border-radius: 4px;">LUNAS</span>
                                    <?php elseif ($n['status_bayar'] === 'PARSIAL'): ?>
                                        <span style="font-weight: 800; font-size: 11px; color: var(--warn-fg); background: var(--warn-bg); padding: 2px 8px; border-radius: 4px;">PARSIAL</span>
                                    <?php else: ?>
                                        <span style="font-weight: 800; font-size: 11px; color: var(--bad-fg); background: var(--bad-bg); padding: 2px 8px; border-radius: 4px;">BELUM BAYAR</span>
                                    <?php endif; ?>
                                </td>
                                <td class="no-print" style="text-align: center;">
                                    <div style="display: flex; gap: 4px; justify-content: center;">
                                        <button type="button" class="btn btn-outline btn-sm" onclick="viewNotaHistory(<?= $n['id'] ?>, '<?= htmlspecialchars($n['no_surat_jalan'], ENT_QUOTES) ?>', <?= $n['total_nilai_jual'] ?>, <?= $n['sisa_piutang'] ?>)" style="padding: 4px 8px; font-size: 11px;" title="Lihat riwayat cicilan setoran nota ini">
                                            Riwayat (<?= $n['jml_kali_setor'] ?>)
                                        </button>
                                        <?php if ($n['sisa_piutang'] > 0): ?>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="openPaymentModal(<?= $n['mitra_id'] ?>, '<?= htmlspecialchars($n['nama_mitra'], ENT_QUOTES) ?>', <?= $n['id'] ?>, <?= $n['sisa_piutang'] ?>)" style="padding: 4px 8px; font-size: 11px;" title="Bayar nota ini">
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
    <!-- TAB 3: BUKU KAS SETORAN MASUK (CASH RECEIPTS LOG)                         -->
    <!-- ========================================================================= -->
    <?php if ($activeTab === 'riwayat_setoran'): ?>
        <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 class="card-title" style="margin: 0; font-size: 16px;">Buku Kas Penerimaan Setoran Mitra</h2>
                    <p class="card-description" style="margin: 2px 0 0 0; font-size: 12.5px;">Riwayat seluruh transaksi uang masuk dari mitra dan alokasi nota yang ditutup</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-custom" data-halaman="25">
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center;">No</th>
                            <th style="width: 140px;">No. Bukti Setoran</th>
                            <th style="width: 110px;">Tanggal Setor</th>
                            <th style="min-width: 160px;">Mitra Penyetor</th>
                            <th style="width: 120px; text-align: center;">Metode Bayar</th>
                            <th style="text-align: right; width: 140px;">Jumlah Setoran</th>
                            <th style="min-width: 200px;">Nota yang Terpotong</th>
                            <th style="width: 130px;">Petugas Penerima</th>
                            <th style="min-width: 150px;">Keterangan / Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($riwayatPayments)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                    Belum ada transaksi setoran pembayaran yang dibukukan.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $no = 1;
                            $totKasMasuk = 0.0;
                            foreach ($riwayatPayments as $p): 
                                $jml = (float)$p['jumlah_bayar'];
                                $totKasMasuk += $jml;
                            ?>
                            <tr>
                                <td style="text-align: center; color: var(--text-muted);"><?= $no++ ?></td>
                                <td>
                                    <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--text-main);">
                                        <?= htmlspecialchars($p['no_bukti_bayar']) ?>
                                    </strong>
                                    <?php if (($p['status'] ?? 'AKTIF') === 'BATAL'): ?>
                                        <div><span style="display: inline-block; padding: 1px 6px; background: var(--border); color: var(--text-muted); font-size: 10.5px; border-radius: 4px;">Dibatalkan</span></div>
                                        <div style="font-size: 10.5px; color: var(--text-muted);"><?= htmlspecialchars($p['alasan_batal'] ?? '') ?></div>
                                    <?php endif; ?>
                                    <div style="font-size: 10.5px; color: var(--text-muted);"><?= date('H:i', strtotime($p['created_at'])) ?> WIB</div>
                                </td>
                                <td style="font-size: 12px; color: var(--text-main);">
                                    <?= date('d/m/Y', strtotime($p['tanggal_bayar'])) ?>
                                </td>
                                <td>
                                    <strong style="color: var(--text-main);">[<?= htmlspecialchars($p['kode_mitra']) ?>] <?= htmlspecialchars($p['nama_mitra']) ?></strong>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($p['metode_bayar'] === 'TUNAI'): ?>
                                        <span style="font-weight: 700; font-size: 11px; padding: 2px 8px; background: var(--info-bg); color: var(--aktif-fg); border-radius: 4px;">TUNAI</span>
                                    <?php else: ?>
                                        <span style="font-weight: 700; font-size: 11px; padding: 2px 8px; background: var(--info-bg); color: var(--aktif-fg); border-radius: 4px;">TRANSFER (<?= htmlspecialchars($p['bank_tujuan'] ?: 'BANK') ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 800; color: var(--ok-fg);">
                                    <?php if (($p['status'] ?? 'AKTIF') === 'BATAL'): ?>
                                        <span style="color: var(--text-muted); text-decoration: line-through;"><?= Helper::formatRupiah($p['jumlah_asal']) ?></span>
                                    <?php else: ?>
                                        <?= Helper::formatRupiah($jml) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($p['daftar_nota_terpotong'])): ?>
                                        <div style="font-family: 'JetBrains Mono', monospace; font-size: 11.5px; color: var(--text-main);">
                                            <?= htmlspecialchars($p['daftar_nota_terpotong']) ?>
                                        </div>
                                        <div style="font-size: 11px; color: var(--text-muted);">
                                            <?= $p['jml_nota_terpotong'] ?> nota terpotong
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 11px;">Saldo Deposit / Titipan</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 12px; color: var(--text-main);">
                                    <?= htmlspecialchars($p['nama_kasir'] ?: 'Petugas Finance') ?>
                                </td>
                                <td style="font-size: 12px; color: var(--text-muted);">
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
<!-- MODAL 1: FORM PENCATATAN SETORAN UANG MASUK (TUNAI / TRANSFER BANK)       -->
<!-- ========================================================================= -->
<div id="modalPayment" class="no-print" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--bg-card); border-radius: 8px; width: 100%; max-width: 680px; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--text-main);">Catat Setoran Pembayaran Mitra</h3>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted);">Membukukan uang setoran kas/bank dan memotong nota piutang secara otomatis atau manual</p>
            </div>
            <button type="button" onclick="closePaymentModal()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: var(--text-muted);">✕</button>
        </div>

        <form id="formRecordPayment" onsubmit="submitPayment(event)" style="padding: 20px;">
            <div class="grid grid-cols-2" style="gap: 14px; margin-bottom: 14px;">
                <!-- 1. Mitra Penyetor -->
                <div>
                    <label class="form-label" for="pay_mitra_id" style="font-weight: 700; font-size: 12px;">Mitra Pembayar *</label>
                    <select name="mitra_id" id="pay_mitra_id" class="form-control" required onchange="onMitraChangeForPayment()" style="height: 40px; font-weight: 700;">
                        <option value="">-- Pilih Mitra Bisnis --</option>
                        <?php foreach ($mitraList as $m): ?>
                            <option value="<?= $m['id'] ?>">[<?= htmlspecialchars($m['kode_mitra']) ?>] <?= htmlspecialchars($m['nama_mitra']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. Tanggal Setor -->
                <div>
                    <label class="form-label" for="pay_tanggal_bayar" style="font-weight: 700; font-size: 12px;">Tanggal Setor *</label>
                    <input type="date" name="tanggal_bayar" id="pay_tanggal_bayar" class="form-control" required value="<?= date('Y-m-d') ?>" style="height: 40px;">
                </div>
            </div>

            <div class="grid grid-cols-2" style="gap: 14px; margin-bottom: 14px;">
                <!-- 3. Metode Bayar -->
                <div>
                    <label class="form-label" style="font-weight: 700; font-size: 12px;">Metode Pembayaran *</label>
                    <select name="metode_bayar" id="pay_metode_bayar" class="form-control" onchange="toggleBankInput()" style="height: 40px;">
                        <option value="TUNAI">Setoran Tunai</option>
                        <option value="TRANSFER_BANK">Transfer Rekening Bank</option>
                    </select>
                </div>

                <!-- 4. Bank Tujuan (Jika Transfer) -->
                <div id="bankInputGroup" style="display: none;">
                    <label class="form-label" for="pay_bank_tujuan" style="font-weight: 700; font-size: 12px;">Rekening Bank Tujuan</label>
                    <input type="text" name="bank_tujuan" id="pay_bank_tujuan" class="form-control" placeholder="Contoh: BCA 123456789 a.n PT Logistik" style="height: 40px;">
                </div>
            </div>

            <!-- 5. Jumlah Uang yang Disetor -->
            <div style="margin-bottom: 16px;">
                <label class="form-label" for="pay_jumlah_bayar" style="font-weight: 800; font-size: 13px; color: var(--ok-fg);">
                    Jumlah Uang yang Disetorkan (Rp) *
                </label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 14px; top: 10px; font-weight: 800; color: var(--ok-fg); font-size: 16px;">Rp</span>
                    <input type="number" step="100" name="jumlah_bayar" id="pay_jumlah_bayar" class="form-control" required min="100" placeholder="0" oninput="calculateAllocationPreview()" style="height: 44px; padding-left: 45px; font-size: 18px; font-weight: 900; font-family: 'JetBrains Mono', monospace; color: var(--ok-fg);">
                </div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                    Contoh: Ketik <code>700000</code> untuk setoran Rp 700.000.
                </div>
            </div>

            <!-- 6. Mode Alokasi ke Nota -->
            <div style="background: var(--bg-muted); border: 1.5px solid var(--border); border-radius: 6px; padding: 12px; margin-bottom: 16px;">
                <label class="form-label" style="font-weight: 800; font-size: 12px; margin-bottom: 6px; display: block;">Aturan Alokasi ke Nota Terutang:</label>
                <div style="display: flex; gap: 16px;">
                    <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 12.5px; font-weight: 700;">
                        <input type="radio" name="mode_alokasi" value="FIFO" checked onchange="toggleAlokasiMode()">
                        <span>Otomatis FIFO</span>
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 12.5px; font-weight: 700;">
                        <input type="radio" name="mode_alokasi" value="MANUAL" onchange="toggleAlokasiMode()">
                        <span>Manual</span>
                    </label>
                </div>
            </div>

            <!-- 7. Tabel Daftar Nota Terutang & Live Simulation -->
            <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span style="font-size: 12px; font-weight: 800; color: var(--text-main);">Daftar Nota Terutang Mitra Ini:</span>
                    <span id="labelTotalSisaPiutangMitra" style="font-size: 11.5px; font-weight: 700; color: var(--warn-fg);">Pilih mitra terlebih dahulu</span>
                </div>

                <div id="wrapperNotaList" style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border); border-radius: 6px;">
                    <div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 12px;">
                        Pilih mitra di atas untuk menampilkan daftar nota pengeluaran yang belum lunas.
                    </div>
                </div>
            </div>

            <!-- 8. Catatan / Keterangan -->
            <div style="margin-bottom: 18px;">
                <label class="form-label" for="pay_keterangan" style="font-weight: 700; font-size: 12px;">Catatan Tambahan</label>
                <input type="text" name="keterangan" id="pay_keterangan" class="form-control" placeholder="Contoh: Titipan setoran via supir / toko pasar..." style="height: 38px;">
            </div>

            <!-- Tombol Aksi Form -->
            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid var(--border); padding-top: 16px;">
                <button type="button" class="btn btn-outline" onclick="closePaymentModal()">Batal</button>
                <button type="submit" id="btnSubmitPayment" class="btn btn-primary" style="font-weight: 800;">
                    Simpan & Bukukan Setoran
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 2: DETAIL RIWAYAT SETORAN PER NOTA                                  -->
<!-- ========================================================================= -->
<div id="modalHistory" class="no-print" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--bg-card); border-radius: 8px; width: 100%; max-width: 650px; max-height: 85vh; overflow-y: auto; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--text-main);" id="histModalTitle">Riwayat Setoran Nota</h3>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted);" id="histModalSubtitle">Rincian cicilan uang masuk yang memotong nota surat jalan ini</p>
            </div>
            <button type="button" onclick="closeHistoryModal()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: var(--text-muted);">✕</button>
        </div>

        <div style="padding: 20px;">
            <!-- Ringkasan Nota -->
            <div style="background: var(--bg-muted); border: 1px solid var(--border); border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div>
                    <span style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Total Nilai Belanja Nota:</span>
                    <div id="histBelanjaText" style="font-size: 16px; font-weight: 800; font-family: 'JetBrains Mono', monospace; color: var(--text-main);">Rp 0</div>
                </div>
                <div>
                    <span style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Sisa Tagihan Saat Ini:</span>
                    <div id="histSisaText" style="font-size: 16px; font-weight: 800; font-family: 'JetBrains Mono', monospace; color: var(--warn-fg);">Rp 0</div>
                </div>
            </div>

            <!-- Tabel Riwayat Cicilan -->
            <div id="histTableContainer">
                <div style="text-align: center; padding: 20px; color: var(--text-muted);">Memuat riwayat...</div>
            </div>
        </div>

        <div style="padding: 12px 20px; border-top: 1px solid var(--border); text-align: right;">
            <button type="button" class="btn btn-outline" onclick="closeHistoryModal()">Tutup</button>
        </div>
    </div>
</div>

<script>
let currentUnpaidNotas = [];

function toggleBankInput() {
    const val = document.getElementById('pay_metode_bayar').value;
    document.getElementById('bankInputGroup').style.display = (val === 'TRANSFER_BANK') ? 'block' : 'none';
}

function openPaymentModal(prefillMitraId = 0, mitraName = '', prefillTrxId = 0, prefillAmount = 0) {
    document.getElementById('modalPayment').style.display = 'flex';
    document.getElementById('formRecordPayment').reset();
    document.getElementById('bankInputGroup').style.display = 'none';

    if (prefillMitraId > 0) {
        document.getElementById('pay_mitra_id').value = prefillMitraId;
        onMitraChangeForPayment(prefillTrxId, prefillAmount);
    } else {
        document.getElementById('wrapperNotaList').innerHTML = '<div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 12px;">Pilih mitra di atas untuk menampilkan daftar nota pengeluaran yang belum lunas.</div>';
        document.getElementById('labelTotalSisaPiutangMitra').textContent = 'Pilih mitra terlebih dahulu';
    }
}

function closePaymentModal() {
    document.getElementById('modalPayment').style.display = 'none';
}

async function onMitraChangeForPayment(focusTrxId = 0, focusAmount = 0) {
    const mitraId = document.getElementById('pay_mitra_id').value;
    const wrapper = document.getElementById('wrapperNotaList');
    const labelTotal = document.getElementById('labelTotalSisaPiutangMitra');

    if (!mitraId) {
        wrapper.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 12px;">Pilih mitra di atas untuk menampilkan daftar nota pengeluaran yang belum lunas.</div>';
        labelTotal.textContent = 'Pilih mitra terlebih dahulu';
        currentUnpaidNotas = [];
        return;
    }

    wrapper.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 12px;">Memuat daftar nota terutang...</div>';

    try {
        const resp = await fetch(`index.php?action=get_unpaid_notas_mitra&mitra_id=${mitraId}`);
        const data = await resp.json();

        if (data.success) {
            currentUnpaidNotas = data.list || [];
            renderUnpaidNotasTable(focusTrxId);

            let totalSisa = 0;
            currentUnpaidNotas.forEach(n => totalSisa += parseFloat(n.sisa_piutang));
            labelTotal.textContent = `Total Sisa Piutang: Rp ${formatNumberRp(totalSisa)}`;
            // Setoran tidak boleh melebihi total sisa piutang
            document.getElementById('pay_jumlah_bayar').max = totalSisa;

            if (focusAmount > 0) {
                document.getElementById('pay_jumlah_bayar').value = focusAmount;
                calculateAllocationPreview();
            }
        } else {
            wrapper.innerHTML = `<div style="padding: 20px; text-align: center; color: var(--danger); font-size: 12px;">Gagal: ${data.message}</div>`;
        }
    } catch (e) {
        wrapper.innerHTML = `<div style="padding: 20px; text-align: center; color: var(--danger); font-size: 12px;">Error: ${e.message}</div>`;
    }
}

function renderUnpaidNotasTable(focusTrxId = 0) {
    const wrapper = document.getElementById('wrapperNotaList');
    if (currentUnpaidNotas.length === 0) {
        wrapper.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--ok-fg); font-weight: 700; font-size: 12px;">Mitra ini tidak memiliki nota piutang yang tertunggak (Lunas). Uang setoran akan dibukukan sebagai saldo deposit.</div>';
        return;
    }

    const isManual = document.querySelector('input[name="mode_alokasi"]:checked').value === 'MANUAL';

    let html = `
        <table style="width: 100%; border-collapse: collapse; font-size: 11.5px;">
            <thead>
                <tr style="background: var(--bg-muted); border-bottom: 1px solid var(--border); text-align: left;">
                    <th style="padding: 6px 8px; width: 25px; text-align: center;">#</th>
                    <th style="padding: 6px 8px;">No. Surat Jalan</th>
                    <th style="padding: 6px 8px; width: 80px;">Umur</th>
                    <th style="padding: 6px 8px; text-align: right; width: 105px;">Sisa Tagihan</th>
                    <th style="padding: 6px 8px; text-align: right; width: 130px;">Alokasi Potong</th>
                </tr>
            </thead>
            <tbody>
    `;

    currentUnpaidNotas.forEach((n, idx) => {
        const isFocused = (focusTrxId > 0 && parseInt(n.id) === focusTrxId);
        html += `
            <tr style="border-bottom: 1px solid var(--border); ${isFocused ? 'background: var(--warn-bg);' : ''}">
                <td style="padding: 6px 8px; text-align: center; color: var(--text-muted);">${idx + 1}</td>
                <td style="padding: 6px 8px;">
                    <strong style="font-family: 'JetBrains Mono', monospace; font-size: 11.5px;">${escapeHtml(n.no_surat_jalan)}</strong>
                    <div style="font-size: 10.5px; color: var(--text-muted);">${n.created_at.substring(0, 10)} • Total: Rp ${formatNumberRp(n.total_nilai_jual)}</div>
                </td>
                <td style="padding: 6px 8px; font-weight: 700; color: ${n.umur_hari > 14 ? '#b91c1c' : '#b45309'};">
                    ${n.umur_hari} Hari
                </td>
                <td style="padding: 6px 8px; text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 800; color: var(--warn-fg);">
                    Rp ${formatNumberRp(n.sisa_piutang)}
                </td>
                <td style="padding: 6px 8px; text-align: right;">
                    ${isManual 
                        ? `<input type="number" step="100" class="form-control input-alok-manual" name="alokasi_manual[${n.id}]" data-trx-id="${n.id}" data-max="${n.sisa_piutang}" placeholder="0" style="height: 28px; font-size: 11px; text-align: right; font-family: 'JetBrains Mono', monospace;" oninput="validateManualAllocation()">`
                        : `<span id="preview_alok_${n.id}" style="font-family: 'JetBrains Mono', monospace; font-weight: 800; color: var(--ok-fg);">Rp 0</span>`
                    }
                </td>
            </tr>
        `;
    });

    html += '</tbody></table>';
    wrapper.innerHTML = html;
}

function toggleAlokasiMode() {
    renderUnpaidNotasTable();
    calculateAllocationPreview();
}

function calculateAllocationPreview() {
    const isManual = document.querySelector('input[name="mode_alokasi"]:checked').value === 'MANUAL';
    if (isManual) return;

    let uangSetoran = parseFloat(document.getElementById('pay_jumlah_bayar').value) || 0;

    currentUnpaidNotas.forEach(n => {
        const previewEl = document.getElementById(`preview_alok_${n.id}`);
        if (!previewEl) return;

        const sisaNota = parseFloat(n.sisa_piutang);
        const potong = Math.min(uangSetoran, sisaNota);

        if (potong > 0) {
            previewEl.textContent = `Rp ${formatNumberRp(potong)}`;
            previewEl.style.color = '#047857';
        } else {
            previewEl.textContent = `Rp 0`;
            previewEl.style.color = '#94a3b8';
        }

        uangSetoran -= potong;
    });
}

function validateManualAllocation() {
    // Memastikan total alokasi manual tidak melebihi uang setoran
    let sumManual = 0;
    const inputs = document.querySelectorAll('.input-alok-manual');
    inputs.forEach(inp => {
        const val = parseFloat(inp.value) || 0;
        sumManual += val;
    });

    const setoranTotal = parseFloat(document.getElementById('pay_jumlah_bayar').value) || 0;
    if (sumManual > setoranTotal) {
        document.getElementById('btnSubmitPayment').disabled = true;
        document.getElementById('btnSubmitPayment').textContent = `Kelebihan Alokasi: Rp ${formatNumberRp(sumManual - setoranTotal)}`;
    } else {
        document.getElementById('btnSubmitPayment').disabled = false;
        document.getElementById('btnSubmitPayment').textContent = 'Simpan & Bukukan Setoran';
    }
}

async function submitPayment(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitPayment');
    const form = document.getElementById('formRecordPayment');
    const formData = new FormData(form);

    btn.disabled = true;
    btn.textContent = 'Memproses...';

    try {
        const resp = await fetch('index.php?action=save_ar_payment', {
            method: 'POST',
            body: formData
        });
        const data = await resp.json();

        if (data.success) {
            alert(`${data.message}\nNomor Bukti: ${data.data.no_bukti_bayar}\nNota Terpotong: ${data.data.allocated_count} nota`);
            closePaymentModal();
            window.location.reload();
        } else {
            alert(`Gagal membukukan setoran: ${data.message}`);
            btn.disabled = false;
            btn.textContent = 'Simpan & Bukukan Setoran';
        }
    } catch (err) {
        alert(`Terjadi kesalahan sistem: ${err.message}`);
        btn.disabled = false;
        btn.textContent = 'Simpan & Bukukan Setoran';
    }
}

async function viewNotaHistory(trxId, noSj, totalBelanja, sisaPiutang) {
    document.getElementById('modalHistory').style.display = 'flex';
    document.getElementById('histModalTitle').textContent = `Riwayat Setoran Nota: ${noSj}`;
    document.getElementById('histBelanjaText').textContent = `Rp ${formatNumberRp(totalBelanja)}`;
    document.getElementById('histSisaText').textContent = `Rp ${formatNumberRp(sisaPiutang)}`;

    const container = document.getElementById('histTableContainer');
    container.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--text-muted);">Memuat riwayat cicilan...</div>';

    try {
        const resp = await fetch(`index.php?action=get_nota_payment_history&transaksi_stok_id=${trxId}`);
        const data = await resp.json();

        if (data.success && data.history.length > 0) {
            let html = `
                <table style="width: 100%; border-collapse: collapse; font-size: 11.5px;">
                    <thead>
                        <tr style="background: var(--bg-muted); border-bottom: 1px solid var(--border); text-align: left;">
                            <th style="padding: 6px 8px; width: 30px; text-align: center;">#</th>
                            <th style="padding: 6px 8px;">No. Bukti Bayar</th>
                            <th style="padding: 6px 8px; width: 95px;">Tgl Setor</th>
                            <th style="padding: 6px 8px; width: 85px;">Metode</th>
                            <th style="padding: 6px 8px; text-align: right; width: 115px;">Cicilan Masuk</th>
                            <th style="padding: 6px 8px; text-align: right; width: 115px;">Sisa Setelah</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.history.forEach((h, idx) => {
                html += `
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 6px 8px; text-align: center; color: var(--text-muted);">${idx + 1}</td>
                        <td style="padding: 6px 8px;">
                            <strong style="font-family: 'JetBrains Mono', monospace;">${escapeHtml(h.no_bukti_bayar)}</strong>
                            <div style="font-size: 10.5px; color: var(--text-muted);">${escapeHtml(h.nama_kasir || 'Kasir')}</div>
                            ${h.status_pembayaran === 'BATAL'
                                ? `<div><span style="display:inline-block;padding:1px 6px;background:var(--border);color:var(--text-muted);font-size:10.5px;border-radius:4px;">Dibatalkan</span></div><div style="font-size:10.5px;color:var(--text-muted);">${escapeHtml(h.alasan_batal || '')}</div>`
                                : `<div><a href="javascript:void(0)" onclick="closeHistoryModal(); batalDokumen('batal_setoran_ar', { id: ${h.pembayaran_id} }, 'setoran ${escapeHtml(h.no_bukti_bayar)} (Rp ${formatNumberRp(h.total_setoran_induk)})')" style="font-size:11px;color:var(--bad-fg);font-weight:700;">Batalkan setoran</a></div>`}
                        </td>
                        <td style="padding: 6px 8px;">${h.tanggal_bayar}</td>
                        <td style="padding: 6px 8px;">${escapeHtml(h.metode_bayar)}</td>
                        <td style="padding: 6px 8px; text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 800; color: ${h.status_pembayaran === 'BATAL' ? '#94a3b8; text-decoration: line-through' : '#047857'};">
                            Rp ${formatNumberRp(h.status_pembayaran === 'BATAL' ? h.nominal_asal : h.nominal_alokasi)}
                        </td>
                        <td style="padding: 6px 8px; text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--warn-fg);">
                            Rp ${formatNumberRp(h.sisa_piutang_setelah)}
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div style="text-align: center; padding: 25px; color: var(--text-muted);">Belum ada setoran cicilan yang tercatat untuk nota ini.</div>';
        }
    } catch (e) {
        container.innerHTML = `<div style="text-align: center; padding: 20px; color: var(--danger);">Gagal memuat: ${e.message}</div>`;
    }
}

function closeHistoryModal() {
    document.getElementById('modalHistory').style.display = 'none';
}

function formatNumberRp(num) {
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(num || 0);
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>

<?php if ($btBoleh) { $btJenis = 'ar'; $btPihakId = $selectedMitraId; include __DIR__ . '/layout/bayar_terpilih.php'; } ?>
<?php require __DIR__ . '/layout/rincian_sj.php'; ?>
<?php require_once __DIR__ . '/layout/footer.php'; ?>
