<?php
/**
 * Stasiun Penerimaan Barang (Inbound Batching - Shadcn UI)
 */

use App\Core\Helper;
use App\Models\Mitra;
use App\Models\Label;

$pageTitle = 'Penerimaan Barang - Shadcn WES';
$mitraList = Mitra::getAll();
$recentActive = Label::getRecent(12);

require_once __DIR__ . '/layout/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Penerimaan Barang</h1>
        <p class="page-subtitle">Label tercetak otomatis berstatus 'AKTIF' dan dikelompokkan ke sesi penerimaan per mitra</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="index.php?page=timbang_cetak" class="btn btn-primary">
            <i data-lucide="scale" style="width: 15px; height: 15px;"></i>
            <span>Stasiun Timbang & Cetak</span>
        </a>
    </div>
</div>

<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <div>
            <h2 class="card-title">
                <i data-lucide="package-check" style="width: 16px; height: 16px;"></i>
                <span>Kemasan Aktif Siap Disimpan ke Lokasi Gudang</span>
            </h2>
            <div class="card-description">Unit kemasan yang telah ditimbang dan memiliki barcode aktif</div>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <?php $eksporTabel = 'tablePenerimaanActive'; $eksporNama = 'Laporan_Penerimaan_Barang'; include __DIR__ . '/layout/ekspor.php'; ?>
            <span class="badge badge-success">Status: Aktif</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table-custom" id="tablePenerimaanActive">
            <thead>
                <tr>
                    <th>Barcode ID</th>
                    <th>Mitra</th>
                    <th>Nama Item / SKU</th>
                    <th>Kategori</th>
                    <th style="text-align: right;">Bruto / Tara</th>
                    <th style="text-align: right;">Netto Aktual</th>
                    <th>Batch</th>
                    <th>Waktu Masuk</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentActive as $lbl): ?>
                <tr>
                    <td><strong style="font-family: 'JetBrains Mono', monospace; font-size: 12px;"><?= htmlspecialchars($lbl['id_label']) ?></strong></td>
                    <td><span class="badge badge-secondary"><?= htmlspecialchars($lbl['kode_mitra']) ?></span></td>
                    <td><strong><?= htmlspecialchars($lbl['nama_item']) ?></strong></td>
                    <td><span class="badge badge-outline"><?= htmlspecialchars($lbl['nama_jenis']) ?></span></td>
                    <td style="text-align: right; font-size: 12px; color: hsl(var(--muted-foreground));">
                        <?= Helper::formatKg($lbl['berat_bruto']) ?> / <?= Helper::formatKg($lbl['berat_tara'], 3) ?>
                    </td>
                    <td style="text-align: right; font-weight: 700; font-size: 14px;">
                        <?= Helper::formatKg($lbl['berat_netto']) ?>
                    </td>
                    <td><code style="font-size: 11px;"><?= htmlspecialchars($lbl['batch_no']) ?></code></td>
                    <td style="font-size: 12px; color: hsl(var(--muted-foreground));"><?= Helper::formatDateTime($lbl['created_at']) ?></td>
                    <td><span class="badge badge-success">● Aktif</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
