<?php
/**
 * Halaman Rekonsiliasi Bukti Setoran Bank
 * Pencocokan nominal setoran dari piutang (AR) dengan lampiran foto bukti setor tunai / screenshot transfer bank.
 */

use App\Core\Helper;
use App\Models\Mitra;
use App\Models\RekonsiliasiBank;

$pageTitle = 'Rekonsiliasi Bukti Setoran Bank';

$mitraList = Mitra::getAll();

// Filter
$filterStatus = trim($_GET['status'] ?? '');
$filterMetode = trim($_GET['metode'] ?? '');
$filterMitra  = (int)($_GET['mitra_id'] ?? 0);
$filterStart  = trim($_GET['date_start'] ?? '');
$filterEnd    = trim($_GET['date_end'] ?? '');
$search       = trim($_GET['search'] ?? '');

$filterParams = [
    'status'       => $filterStatus,
    'metode_bayar' => $filterMetode,
    'mitra_id'     => $filterMitra,
    'date_start'   => $filterStart,
    'date_end'     => $filterEnd,
    'search'       => $search
];

$kpi = RekonsiliasiBank::getSummaryKpi($filterParams);
$rekonList = RekonsiliasiBank::getRekonsiliasiList($filterParams);

require_once __DIR__ . '/layout/header.php';
?>

<div class="content-wrapper">

    <!-- PAGE HEADER -->
    <div class="page-header no-print">
        <div>
            <h1 class="page-title">Rekonsiliasi Bukti Setoran Bank</h1>
            <p class="page-subtitle">Pencocokan nominal setoran dari AR dengan lampiran foto bukti setor tunai (struk ATM CDM / teller) atau screenshot transfer bank</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button type="button" class="btn btn-outline" onclick="window.print()" style="display: flex; align-items: center; gap: 6px;">
                <i data-lucide="printer" style="width: 16px; height: 16px;"></i>
                <span>Cetak Rekap</span>
            </button>
        </div>
    </div>

    <!-- 3 KARTU METRIK RINGKASAN REKONSILIASI -->
    <div class="grid grid-cols-3 no-print" style="gap: 16px; margin-bottom: 22px;">
        <!-- Card 1: Total Setoran Tercatat di AR -->
        <div class="card" style="padding: 16px; border-left: 4px solid #0284c7; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Total Setoran di AR</span>
                <div style="background: rgba(2, 132, 199, 0.12); color: #0284c7; padding: 6px; border-radius: 8px;">
                    <i data-lucide="receipt" style="width: 17px; height: 17px;"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: #0369a1; line-height: 1.1;">
                <?= Helper::formatRupiah($kpi['total_nominal']) ?>
            </div>
            <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 6px;">
                Dari total <strong><?= number_format($kpi['total_setoran']) ?></strong> transaksi setoran lapak mitra
            </div>
        </div>

        <!-- Card 2: Sudah Ada Bukti / Matched -->
        <div class="card" style="padding: 16px; border-left: 4px solid #10b981; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Sudah Ada Bukti (Matched)</span>
                <div style="background: rgba(16, 185, 129, 0.12); color: #10b981; padding: 6px; border-radius: 8px;">
                    <i data-lucide="check-check" style="width: 17px; height: 17px;"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: #047857; line-height: 1.1;">
                <?= Helper::formatRupiah($kpi['nominal_matched']) ?>
            </div>
            <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 6px;">
                <strong><?= $kpi['count_matched'] ?></strong> setoran telah terverifikasi dengan lampiran bukti setor
            </div>
        </div>

        <!-- Card 3: Belum Ada Bukti / Pending -->
        <div class="card" style="padding: 16px; border-left: 4px solid #f59e0b; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Belum Ada Bukti (Pending)</span>
                <div style="background: rgba(245, 158, 11, 0.12); color: #f59e0b; padding: 6px; border-radius: 8px;">
                    <i data-lucide="clock" style="width: 17px; height: 17px;"></i>
                </div>
            </div>
            <div style="font-size: 22px; font-weight: 900; color: #b45309; line-height: 1.1;">
                <?= Helper::formatRupiah($kpi['nominal_pending']) ?>
            </div>
            <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 6px;">
                <strong><?= $kpi['count_pending'] ?></strong> setoran menunggu lampiran slip / screenshot dari admin
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="card no-print" style="padding: 16px; margin-bottom: 18px;">
        <form method="GET" action="index.php" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="page" value="rekonsiliasi_bank">

            <div style="width: 180px;">
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">Status Rekonsiliasi</label>
                <select name="status" class="form-control" style="font-size: 12px;" onchange="this.form.submit()">
                    <option value="" <?= empty($filterStatus) ? 'selected' : '' ?>>Semua Status</option>
                    <option value="PENDING" <?= $filterStatus === 'PENDING' ? 'selected' : '' ?>>Pending (Belum Ada Bukti)</option>
                    <option value="MATCHED" <?= $filterStatus === 'MATCHED' ? 'selected' : '' ?>>Matched (Sudah Ada Bukti)</option>
                </select>
            </div>

            <div style="width: 160px;">
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">Metode Bayar</label>
                <select name="metode" class="form-control" style="font-size: 12px;" onchange="this.form.submit()">
                    <option value="" <?= empty($filterMetode) ? 'selected' : '' ?>>Semua Metode</option>
                    <option value="TUNAI" <?= $filterMetode === 'TUNAI' ? 'selected' : '' ?>>TUNAI (Cash)</option>
                    <option value="TRANSFER_BANK" <?= $filterMetode === 'TRANSFER_BANK' ? 'selected' : '' ?>>TRANSFER BANK</option>
                </select>
            </div>

            <div style="flex: 1; min-width: 170px;">
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">Lapak / Mitra</label>
                <select name="mitra_id" class="form-control" style="font-size: 12px;" onchange="this.form.submit()">
                    <option value="0">-- Seluruh Mitra --</option>
                    <?php foreach ($mitraList as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $filterMitra === (int)$m['id'] ? 'selected' : '' ?>>
                            [<?= htmlspecialchars($m['kode_mitra']) ?>] <?= htmlspecialchars($m['nama_mitra']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex: 1.2; min-width: 180px;">
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">Cari No Bukti / Catatan</label>
                <input type="text" name="search" class="form-control" placeholder="No Bukti / Catatan..." value="<?= htmlspecialchars($search) ?>" style="font-size: 12px;">
            </div>

            <div style="display: flex; gap: 6px;">
                <button type="submit" class="btn btn-primary" style="font-size: 12px; padding: 8px 14px;">
                    <i data-lucide="filter" style="width: 14px; height: 14px;"></i>
                    <span>Filter</span>
                </button>
                <?php if (!empty($filterStatus) || !empty($filterMetode) || $filterMitra > 0 || !empty($search)): ?>
                    <a href="index.php?page=rekonsiliasi_bank" class="btn btn-outline" style="font-size: 12px; padding: 8px 12px;">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- TABEL UTAMA PENCATATAN REKONSILIASI BUKTI SETORAN -->
    <div class="card" style="padding: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
            <div>
                <h2 class="card-title" style="margin: 0; font-size: 16px;">Daftar Setoran AR &amp; Lampiran Bukti Setor Bank</h2>
                <p class="card-description" style="margin: 2px 0 0 0; font-size: 12.5px;">Nominal payment ditarik otomatis dari AR. Admin cukup melampirkan foto struk setor tunai / screenshot transfer agar transaksi dinyatakan MATCHED.</p>
            </div>
            <div style="font-size: 12px; color: var(--text-muted);">
                Total <strong><?= count($rekonList) ?></strong> transaksi setoran
            </div>
        </div>

        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th style="width: 35px; text-align: center;">No</th>
                        <th style="min-width: 130px;">No. Bukti AR</th>
                        <th style="width: 95px;">Tgl Setor</th>
                        <th style="min-width: 150px;">Lapak Mitra</th>
                        <th style="width: 120px; text-align: center;">Metode Bayar</th>
                        <th style="text-align: right; min-width: 135px;">Nominal Setoran AR</th>
                        <th style="min-width: 170px;">Lampiran Bukti Setor</th>
                        <th style="width: 110px; text-align: center;">Status</th>
                        <th style="width: 130px;">Admin Verifikator</th>
                        <th class="no-print" style="width: 130px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rekonList)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 36px; color: var(--text-muted);">
                                Tidak ada data setoran AR yang ditemukan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = 1;
                        foreach ($rekonList as $r): 
                            $isMatched = ($r['status_rekonsiliasi'] === 'MATCHED');
                            $hasBukti  = !empty($r['bukti_gambar']);
                        ?>
                        <tr style="<?= !$isMatched ? 'background: #fffdf5;' : '' ?>">
                            <td style="text-align: center; color: var(--text-muted);"><?= $no++ ?></td>
                            <td>
                                <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: #1e3a8a;">
                                    <?= htmlspecialchars($r['no_bukti_bayar']) ?>
                                </strong>
                                <?php if (!empty($r['daftar_nota_terpotong'])): ?>
                                    <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 2px;">
                                        SJ: <?= htmlspecialchars($r['daftar_nota_terpotong']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 12px;">
                                <?= date('d/m/Y', strtotime($r['tanggal_bayar'])) ?>
                            </td>
                            <td>
                                <strong style="color: var(--text-main); font-size: 12.5px;">[<?= htmlspecialchars($r['kode_mitra']) ?>] <?= htmlspecialchars($r['nama_mitra']) ?></strong>
                                <div style="font-size: 10.5px; color: var(--text-muted);">
                                    Penyetor: <?= htmlspecialchars($r['nama_kasir'] ?: 'Kasir Pasar') ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($r['metode_bayar'] === 'TUNAI'): ?>
                                    <span style="display: inline-block; padding: 2px 8px; background: #ecfdf5; color: #047857; font-weight: 800; font-size: 11px; border-radius: 4px;">
                                        💵 TUNAI / CASH
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-block; padding: 2px 8px; background: #e0f2fe; color: #0369a1; font-weight: 800; font-size: 11px; border-radius: 4px;">
                                        🏦 TRANSFER
                                    </span>
                                    <?php if (!empty($r['bank_tujuan'])): ?>
                                        <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;"><?= htmlspecialchars($r['bank_tujuan']) ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 13.5px; font-weight: 900; color: #047857;">
                                <?= Helper::formatRupiah($r['nominal_ar']) ?>
                            </td>
                            <td>
                                <?php if ($hasBukti): ?>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <button type="button" class="btn btn-outline btn-sm" onclick="viewBukti('<?= htmlspecialchars($r['bukti_gambar']) ?>', '<?= htmlspecialchars($r['no_bukti_bayar']) ?>', '<?= htmlspecialchars($r['nama_mitra'], ENT_QUOTES) ?>', <?= $r['nominal_ar'] ?>)" style="padding: 3px 8px; font-size: 11px; color: #0284c7; display: inline-flex; align-items: center; gap: 4px;">
                                            <i data-lucide="image" style="width: 13px; height: 13px;"></i>
                                            <span>Lihat Bukti</span>
                                        </button>
                                    </div>
                                    <?php if (!empty($r['catatan_rekonsiliasi'])): ?>
                                        <div style="font-size: 10.5px; color: #64748b; margin-top: 3px; font-style: italic;">
                                            "<?= htmlspecialchars($r['catatan_rekonsiliasi']) ?>"
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #b45309; font-size: 11.5px; font-weight: 600;">
                                        ⚠️ Belum dilampirkan
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($isMatched): ?>
                                    <span style="display: inline-block; padding: 3px 10px; background: #d1fae5; color: #065f46; font-weight: 800; font-size: 11px; border-radius: 4px;">
                                        ✓ MATCHED
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-block; padding: 3px 10px; background: #fef3c7; color: #b45309; font-weight: 800; font-size: 11px; border-radius: 4px;">
                                        PENDING
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 11px; color: var(--text-muted);">
                                <?php if ($isMatched && !empty($r['nama_verifikator'])): ?>
                                    <strong style="color: #334155;"><?= htmlspecialchars($r['nama_verifikator']) ?></strong>
                                    <div style="font-size: 10px;"><?= date('d/m/Y H:i', strtotime($r['verified_at'])) ?></div>
                                <?php else: ?>
                                    <span>-</span>
                                <?php endif; ?>
                            </td>
                            <td class="no-print" style="text-align: center;">
                                <?php if (!$isMatched): ?>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="openModalUploadBukti(<?= $r['id'] ?>, '<?= htmlspecialchars($r['no_bukti_bayar']) ?>', '<?= htmlspecialchars($r['nama_mitra'], ENT_QUOTES) ?>', <?= $r['nominal_ar'] ?>, '<?= htmlspecialchars($r['metode_bayar']) ?>')" style="padding: 4px 10px; font-size: 11px; font-weight: 700; background: #0284c7;" title="Lampirkan foto slip setor / screenshot">
                                        <i data-lucide="upload" style="width: 12px; height: 12px;"></i>
                                        <span>Attach Bukti</span>
                                    </button>
                                <?php else: ?>
                                    <div style="display: flex; gap: 4px; justify-content: center;">
                                        <button type="button" class="btn btn-outline btn-sm" onclick="openModalUploadBukti(<?= $r['id'] ?>, '<?= htmlspecialchars($r['no_bukti_bayar']) ?>', '<?= htmlspecialchars($r['nama_mitra'], ENT_QUOTES) ?>', <?= $r['nominal_ar'] ?>, '<?= htmlspecialchars($r['metode_bayar']) ?>')" style="padding: 3px 8px; font-size: 10.5px;" title="Ganti foto bukti">
                                            Ganti
                                        </button>
                                        <button type="button" class="btn btn-outline btn-sm" onclick="hapusBukti(<?= $r['id'] ?>)" style="padding: 3px 6px; font-size: 10.5px; color: #ef4444;" title="Hapus bukti (kembalikan ke Pending)">
                                            Hapus
                                        </button>
                                    </div>
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

<!-- ========================================================================= -->
<!-- MODAL: ATTACH / UPLOAD BUKTI SETORAN (SLIP / SCREENSHOT)                  -->
<!-- ========================================================================= -->
<div id="modalUploadBukti" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 520px; margin: auto; padding: 24px; box-shadow: var(--shadow-lg); border-radius: 12px; background: #fff;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #1e293b;">Lampirkan Bukti Setoran Bank</h3>
                <p id="uploadSubtitle" style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted);">-</p>
            </div>
            <button type="button" onclick="closeModalUploadBukti()" style="border: none; background: transparent; font-size: 20px; color: #64748b; cursor: pointer; padding: 4px;">&times;</button>
        </div>

        <form id="formUploadBukti" onsubmit="submitUploadBukti(event)" enctype="multipart/form-data">
            <input type="hidden" id="uploadArId" name="ar_id">

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Nominal Setoran AR:</div>
                        <div id="uploadNominalDisplay" style="font-size: 20px; font-weight: 900; color: #047857; font-family: 'JetBrains Mono', monospace; margin-top: 2px;">
                            Rp 0
                        </div>
                    </div>
                    <div id="uploadMetodeBadge" style="font-size: 11px; font-weight: 800;"></div>
                </div>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                    Foto Struk CDM / Slip Teller / Screenshot M-Banking <span style="color: #ef4444;">*</span>
                </label>
                <input type="file" id="uploadFile" name="file_bukti" class="form-control" accept="image/*,.pdf" required style="font-size: 12px; padding: 8px;" onchange="previewUploadImage(this)">
                <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 3px;">
                    Format: Foto kamera/screenshot (JPG, PNG, WEBP) atau file PDF
                </div>
                <!-- Image Preview Container -->
                <div id="uploadPreviewBox" style="display: none; margin-top: 10px; text-align: center; background: #000; border-radius: 6px; padding: 6px;">
                    <img id="uploadPreviewImg" src="" style="max-height: 220px; max-width: 100%; border-radius: 4px;">
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">Catatan Tambahan (Opsional)</label>
                <input type="text" id="uploadCatatan" name="catatan" class="form-control" placeholder="Contoh: Setor tunai mesin ATM BCA jam 11:30..." style="font-size: 12px;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                <button type="button" class="btn btn-outline" onclick="closeModalUploadBukti()">Batal</button>
                <button type="submit" id="btnSubmitUpload" class="btn btn-primary" style="font-weight: 700; background: #0284c7;">
                    <i data-lucide="check" style="width: 15px; height: 15px;"></i>
                    <span>Simpan &amp; Nyatakan Matched</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: PREVIEW GAMBAR BUKTI (LIGHTBOX POPUP)                              -->
<!-- ========================================================================= -->
<div id="modalViewBukti" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 10000; align-items: center; justify-content: center; padding: 20px;">
    <div style="max-width: 600px; width: 100%; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: var(--shadow-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 18px; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
            <div>
                <strong id="viewBuktiTitle" style="font-size: 13.5px; color: #1e293b;">Bukti Setoran</strong>
                <div id="viewBuktiSubtitle" style="font-size: 11px; color: var(--text-muted); margin-top: 1px;">-</div>
            </div>
            <button type="button" onclick="closeModalViewBukti()" style="border: none; background: transparent; font-size: 22px; color: #64748b; cursor: pointer; padding: 4px;">&times;</button>
        </div>
        <div style="padding: 16px; text-align: center; background: #0f172a; max-height: 520px; overflow-y: auto;">
            <img id="viewBuktiImg" src="" style="max-height: 480px; max-width: 100%; border-radius: 4px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.5);">
        </div>
        <div style="padding: 10px 18px; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <a id="viewBuktiDownload" href="" target="_blank" download class="btn btn-outline btn-sm" style="font-size: 11px;">
                Unduh Gambar
            </a>
            <button type="button" class="btn btn-primary btn-sm" onclick="closeModalViewBukti()">Tutup</button>
        </div>
    </div>
</div>

<script>
function openModalUploadBukti(arId, noBukti, namaMitra, nominalAr, metode) {
    document.getElementById('formUploadBukti').reset();
    document.getElementById('uploadArId').value = arId;
    document.getElementById('uploadSubtitle').textContent = `Setoran: ${noBukti} • ${namaMitra}`;
    document.getElementById('uploadNominalDisplay').textContent = `Rp ${new Intl.NumberFormat('id-ID').format(nominalAr)}`;
    document.getElementById('uploadPreviewBox').style.display = 'none';

    const badge = document.getElementById('uploadMetodeBadge');
    if (metode === 'TUNAI') {
        badge.innerHTML = '<span style="padding: 2px 8px; background: #ecfdf5; color: #047857; border-radius: 4px;">💵 TUNAI / CASH</span>';
    } else {
        badge.innerHTML = '<span style="padding: 2px 8px; background: #e0f2fe; color: #0369a1; border-radius: 4px;">🏦 TRANSFER BANK</span>';
    }

    document.getElementById('modalUploadBukti').style.display = 'flex';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeModalUploadBukti() {
    document.getElementById('modalUploadBukti').style.display = 'none';
}

function previewUploadImage(input) {
    const box = document.getElementById('uploadPreviewBox');
    const img = document.getElementById('uploadPreviewImg');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                box.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            box.style.display = 'none';
        }
    }
}

async function submitUploadBukti(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitUpload');
    const form = document.getElementById('formUploadBukti');
    const formData = new FormData(form);

    btn.disabled = true;
    btn.innerHTML = '<span>Mengunggah...</span>';

    try {
        const resp = await fetch('index.php?action=upload_bukti_rekonsiliasi', {
            method: 'POST',
            body: formData
        });
        const res = await resp.json();

        if (res.success) {
            alert(res.message);
            closeModalUploadBukti();
            window.location.reload();
        } else {
            alert('Gagal: ' + res.message);
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="check" style="width: 15px; height: 15px;"></i><span>Simpan &amp; Nyatakan Matched</span>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    } catch (err) {
        alert('Terjadi kesalahan jaringan');
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" style="width: 15px; height: 15px;"></i><span>Simpan &amp; Nyatakan Matched</span>';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

function viewBukti(filePath, noBukti, namaMitra, nominal) {
    document.getElementById('viewBuktiTitle').textContent = `Bukti Setoran: ${noBukti}`;
    document.getElementById('viewBuktiSubtitle').textContent = `${namaMitra} • Rp ${new Intl.NumberFormat('id-ID').format(nominal)}`;
    document.getElementById('viewBuktiImg').src = filePath;
    document.getElementById('viewBuktiDownload').href = filePath;
    document.getElementById('modalViewBukti').style.display = 'flex';
}

function closeModalViewBukti() {
    document.getElementById('modalViewBukti').style.display = 'none';
}

async function hapusBukti(arId) {
    if (!confirm('Apakah Anda yakin ingin menghapus bukti setoran ini? Status transaksi akan kembali menjadi PENDING.')) return;

    const fd = new FormData();
    fd.append('ar_id', arId);

    try {
        const resp = await fetch('index.php?action=remove_bukti_rekonsiliasi', {
            method: 'POST',
            body: fd
        });
        const res = await resp.json();

        if (res.success) {
            alert(res.message);
            window.location.reload();
        } else {
            alert('Gagal: ' + res.message);
        }
    } catch (err) {
        alert('Terjadi kesalahan jaringan');
    }
}

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('modalUploadBukti')) closeModalUploadBukti();
    if (e.target === document.getElementById('modalViewBukti')) closeModalViewBukti();
});
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
