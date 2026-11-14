<?php
/**
 * Dokumen Resmi Surat Jalan Penerimaan Barang Masuk (Inbound Goods Receipt Note / GRN)
 * Format Siap Cetak A4 Portrait & Continuous Paper Standar Pergudangan
 */

use App\Core\Helper;
use App\Models\Label;
use App\Services\BarcodeService;

$noSuratJalan = trim($_GET['no_surat_jalan'] ?? '');

if (empty($noSuratJalan)) {
    die("Dokumen Surat Jalan Penerimaan tidak ditemukan. Parameter nomor surat jalan wajib diisi.");
}

$sjData = Label::getSuratJalanDetailWithLabels($noSuratJalan);

if (!$sjData) {
    die("Data Penerimaan untuk Surat Jalan '<strong>" . htmlspecialchars($noSuratJalan) . "</strong>' tidak ditemukan di database.");
}

$barcodeSjSvg = BarcodeService::generateCode128Svg($sjData['no_surat_jalan'], 56, 1.6);
$labels = $sjData['all_labels'] ?? [];
$isComplete = ($sjData['total_pcs'] > 0);

// Hitung rekap per produk jika dalam 1 SJ terdapat banyak produk
$uniqueItems = [];
foreach ($labels as $lbl) {
    $itemId = $lbl['item_id'] ?? 0;
    if (!isset($uniqueItems[$itemId])) {
        $uniqueItems[$itemId] = [
            'nama_item' => $lbl['nama_item'] ?? '-',
            'nama_jenis' => $lbl['nama_jenis'] ?? '-',
            'total_pcs' => 0,
            'total_berat' => 0.0
        ];
    }
    $uniqueItems[$itemId]['total_pcs']++;
    $uniqueItems[$itemId]['total_berat'] += (float)($lbl['berat_netto'] ?? 0);
}
$isMultiItem = count($uniqueItems) > 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan Penerimaan - <?= htmlspecialchars($sjData['no_surat_jalan']) ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 14mm 10mm 14mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Plus Jakarta Sans', Arial, sans-serif;
            background: #ffffff;
            color: #000000;
            margin: 0;
            padding: 20px;
            font-size: 12px;
            line-height: 1.45;
        }
        .sj-page {
            width: 100%;
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            padding: 24px 28px;
            border: 1px solid #000000;
            position: relative;
        }
        .header-company {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000000;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .logo-title h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 900;
            letter-spacing: -0.3px;
            text-transform: uppercase;
            color: #000000;
        }
        .logo-title p {
            margin: 3px 0 0;
            font-size: 11px;
            color: #333333;
            font-weight: 500;
        }
        .barcode-header {
            text-align: right;
            max-width: 260px;
        }
        .barcode-header-svg {
            height: 38px;
            display: flex;
            justify-content: flex-end;
        }
        .barcode-header-svg svg {
            height: 38px;
            max-width: 240px;
        }
        .barcode-header-text {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.8px;
            color: #000000;
            margin-top: 2px;
        }
        .doc-title {
            text-align: center;
            margin: 10px 0 14px;
        }
        .doc-title h2 {
            margin: 0;
            font-size: 16px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #000000;
        }
        .doc-title .doc-subtitle {
            font-size: 11px;
            color: #444444;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        /* GRID INFORMASI PEMASOK DAN PENERIMAAN */
        .party-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 14px;
        }
        .party-card {
            border: 1px solid #000000;
            padding: 10px 14px;
            background: #ffffff;
        }
        .party-card-title {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding-bottom: 4px;
            margin-bottom: 8px;
            border-bottom: 1px solid #000000;
            color: #000000;
        }
        .meta-row {
            display: flex;
            margin-bottom: 4px;
            font-size: 11.5px;
        }
        .meta-row:last-child { margin-bottom: 0; }
        .meta-label {
            width: 125px;
            color: #333333;
            font-weight: 600;
            flex-shrink: 0;
        }
        .meta-val {
            font-weight: 700;
            color: #000000;
            flex-grow: 1;
        }

        /* SUMMARY BOX PRODUK & AKUMULASI */
        .summary-banner {
            background: #ffffff;
            border: 1px solid #000000;
            padding: 10px 14px;
            margin-bottom: 14px;
            display: grid;
            grid-template-columns: 2fr 1fr 1.2fr 1fr;
            gap: 12px;
            align-items: center;
        }
        .summary-item-title {
            font-size: 10.5px;
            color: #444444;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .summary-item-val {
            font-size: 14px;
            font-weight: 800;
            color: #000000;
            margin-top: 2px;
        }

        /* TABEL BARANG */
        .table-items {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
            margin: 12px 0;
        }
        .table-items th {
            background: #f0f0f0;
            border: 1px solid #000000;
            padding: 8px 10px;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 11px;
            color: #000000;
            letter-spacing: 0.3px;
        }
        .table-items td {
            border: 1px solid #000000;
            padding: 8px 10px;
            vertical-align: middle;
            color: #000000;
        }
        .table-items tr:nth-child(even) {
            background: #ffffff;
        }
        .table-items tfoot td {
            background: #f0f0f0;
            font-weight: 800;
            border: 1px solid #000000;
            border-top: 2px solid #000000;
            padding: 9px 10px;
            color: #000000;
        }

        /* CATATAN RESMI */
        .note-box {
            background: #ffffff;
            border: 1px dashed #000000;
            padding: 9px 12px;
            margin-top: 12px;
            font-size: 11px;
            color: #000000;
            line-height: 1.4;
        }

        /* TANDA TANGAN 3 PIHAK */
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-top: 24px;
            text-align: center;
            page-break-inside: avoid;
        }
        .sign-box {
            padding: 4px;
        }
        .sign-title {
            font-size: 11px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 58px;
            text-transform: uppercase;
        }
        .sign-name {
            font-weight: 800;
            font-size: 11.5px;
            border-top: 1px solid #000000;
            padding-top: 4px;
            color: #000000;
        }
        .sign-role {
            font-size: 10px;
            color: #444444;
            margin-top: 2px;
        }

        /* ACTIONS HEADER (NO PRINT) */
        .print-actions {
            max-width: 820px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 700;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid #000000;
        }
        .btn-print {
            background: #000000;
            color: #ffffff;
        }
        .btn-print:hover {
            background: #333333;
        }
        .btn-back {
            background: #ffffff;
            color: #000000;
            border-color: #000000;
        }
        .btn-back:hover {
            background: #f0f0f0;
        }

        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .sj-page {
                box-shadow: none !important;
                padding: 0 !important;
                border: none !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            .print-actions {
                display: none !important;
            }
            .table-items th {
                background: #f0f0f0 !important;
                border: 1px solid #000000 !important;
                color: #000000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .table-items td {
                border: 1px solid #000000 !important;
                color: #000000 !important;
            }
            .table-items tfoot td {
                background: #f0f0f0 !important;
                border: 1px solid #000000 !important;
                border-top: 2px solid #000000 !important;
                color: #000000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .party-card {
                border: 1px solid #000000 !important;
                background: #ffffff !important;
            }
            .party-card-title {
                color: #000000 !important;
                border-bottom: 1px solid #000000 !important;
            }
            .summary-banner {
                background: #ffffff !important;
                border: 1px solid #000000 !important;
            }
        }
    </style>
</head>
<body>

    <!-- Tombol Aksi di Layar Komputer (Tidak Ikut Tercetak) -->
    <div class="print-actions">
        <a href="index.php?page=timbang_cetak&tab=riwayat_penerimaan" class="btn-action btn-back">
            ← Kembali ke Riwayat Penerimaan
        </a>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()" class="btn-action btn-print">
                🖨️ Cetak Dokumen Penerimaan
            </button>
        </div>
    </div>

    <!-- Halaman Dokumen Cetak Surat Jalan Penerimaan -->
    <div class="sj-page">
        <!-- 1. Header Perusahaan & Barcode Surat Jalan -->
        <div class="header-company">
            <div class="logo-title">
                <h1>AR_BAROKAH</h1>
                <p>Sistem Manajemen Pergudangan & Stasiun Timbang Masuk (Inbound Receiving)</p>
            </div>
            <div class="barcode-header">
                <div class="barcode-header-svg">
                    <?= $barcodeSjSvg ?>
                </div>
                <div class="barcode-header-text"><?= htmlspecialchars($sjData['no_surat_jalan']) ?></div>
            </div>
        </div>

        <!-- 2. Judul Dokumen Resmi -->
        <div class="doc-title">
            <h2>SURAT JALAN PENERIMAAN BARANG MASUK</h2>
            <div class="doc-subtitle">Goods Receipt Note (GRN) • Berita Acara Penerimaan Keranjang</div>
        </div>

        <!-- 3. Grid Informasi Pemasok & Penerimaan -->
        <div class="party-grid">
            <!-- Kolom Kiri: Pemasok Asal Pengirim -->
            <div class="party-card">
                <div class="party-card-title">
                    <span>PIHAK PENGIRIM (PEMASOK)</span>
                </div>
                <div class="meta-row">
                    <div class="meta-label">Kode Pemasok:</div>
                    <div class="meta-val"><code><?= htmlspecialchars($sjData['kode_pemasok']) ?></code></div>
                </div>
                <div class="meta-row">
                    <div class="meta-label">Nama Pemasok:</div>
                    <div class="meta-val"><?= htmlspecialchars($sjData['nama_pemasok']) ?></div>
                </div>
                <div class="meta-row">
                    <div class="meta-label">PIC / Kontak:</div>
                    <div class="meta-val"><?= htmlspecialchars($sjData['pic_pemasok'] !== '-' ? $sjData['pic_pemasok'] : 'Sopir / Ekspedisi') ?> (<?= htmlspecialchars($sjData['telepon_pemasok'] ?? '-') ?>)</div>
                </div>
                <div class="meta-row">
                    <div class="meta-label">Alamat / Asal:</div>
                    <div class="meta-val"><?= htmlspecialchars($sjData['alamat_pemasok'] ?? '-') ?></div>
                </div>
            </div>

            <!-- Kolom Kanan: Rincian Penerimaan Gudang -->
            <div class="party-card">
                <div class="party-card-title">
                    <span>DOKUMEN & PENERIMA GUDANG</span>
                </div>
                <div class="meta-row">
                    <div class="meta-label">No. Surat Jalan:</div>
                    <div class="meta-val"><strong style="color: #000000; font-family: 'JetBrains Mono', monospace; font-size: 12.5px;"><?= htmlspecialchars($sjData['no_surat_jalan']) ?></strong></div>
                </div>
                <div class="meta-row">
                    <div class="meta-label">Tanggal Masuk:</div>
                    <div class="meta-val"><?= date('d F Y - H:i', strtotime($sjData['created_at'])) ?> WIB</div>
                </div>
                <div class="meta-row">
                    <div class="meta-label">Petugas Penerima:</div>
                    <div class="meta-val"><?= htmlspecialchars($_SESSION['user_login']['nama_lengkap'] ?? 'Petugas Penerimaan Gudang') ?></div>
                </div>
                <div class="meta-row">
                    <div class="meta-label">Status Penerimaan:</div>
                    <div class="meta-val">
                        <span style="font-weight: 800; color: #000000;">LENGKAP (Sudah Ditimbang)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Ringkasan Komoditas / Barang -->
        <div class="summary-banner" style="<?= $isMultiItem ? 'grid-template-columns: 2.2fr 1fr 1.2fr 1fr;' : '' ?>">
            <div>
                <div class="summary-item-title"><?= $isMultiItem ? 'Komoditas (Multi-Produk)' : 'Komoditas / Nama Barang' ?></div>
                <div class="summary-item-val" style="font-size: <?= $isMultiItem ? '12.5px' : '14px' ?>;">
                    <?php if ($isMultiItem): ?>
                        <div style="font-weight: 800; margin-bottom: 2px;"><?= count($uniqueItems) ?> Macam Komoditas Produk:</div>
                        <div style="font-size: 11px; font-weight: normal; color: #333333; line-height: 1.4;">
                            <?php foreach ($uniqueItems as $ui): ?>
                                <div>• <strong><?= htmlspecialchars($ui['nama_item']) ?></strong>: <?= $ui['total_pcs'] ?> Box (<?= number_format($ui['total_berat'], 2) ?> KG)</div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <strong><?= htmlspecialchars($sjData['nama_item']) ?></strong> <span style="font-size: 11.5px; color: #444444; font-weight: normal;">(<?= htmlspecialchars($sjData['nama_jenis']) ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="text-align: center;">
                <div class="summary-item-title">Total Keranjang</div>
                <div class="summary-item-val"><?= number_format($sjData['total_pcs']) ?> Pcs</div>
            </div>
            <div style="text-align: center;">
                <div class="summary-item-title">Total Bobot Riil</div>
                <div class="summary-item-val" style="font-family: 'JetBrains Mono', monospace;"><?= number_format($sjData['total_berat'], 2) ?> KG</div>
            </div>
            <div style="text-align: right;">
                <div class="summary-item-title">Rata-rata/Box</div>
                <div class="summary-item-val" style="font-family: 'JetBrains Mono', monospace;"><?= number_format($sjData['avg_berat'], 2) ?> KG</div>
            </div>
        </div>

        <!-- 5. Tabel Detail Keranjang & Barcode -->
        <table class="table-items">
            <thead>
                <tr>
                    <th style="width: 36px; text-align: center;">No</th>
                    <th style="width: 220px; text-align: left;">ID Barcode Kemasan</th>
                    <th style="text-align: left;">Nama Barang / Produk</th>
                    <th style="width: 140px; text-align: center;">Waktu Timbang</th>
                    <th style="width: 120px; text-align: right;">Bobot Riil (Kg)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($labels)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 18px; color: #000000;">
                            Tidak ada rincian kemasan barcode untuk Surat Jalan ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = 1;
                    foreach ($labels as $lbl): 
                        $netto = (float)($lbl['berat_netto'] ?? 0);
                    ?>
                        <tr>
                            <td style="text-align: center;"><?= $no++ ?></td>
                            <td style="white-space: nowrap;">
                                <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12px; letter-spacing: 0.3px;">
                                    <?= htmlspecialchars($lbl['id_label']) ?>
                                </strong>
                            </td>
                            <td>
                                <strong style="font-size: 12px;"><?= htmlspecialchars($lbl['nama_item'] ?? '-') ?></strong>
                                <?php if (!empty($lbl['nama_jenis'])): ?>
                                    <span style="font-size: 11px; color: #555555; margin-left: 4px;">(<?= htmlspecialchars($lbl['nama_jenis']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; font-size: 11px;">
                                <?= date('d/m/Y H:i', strtotime($lbl['updated_at'] ?? $lbl['created_at'])) ?>
                            </td>
                            <td style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; font-size: 12.5px;">
                                <?= number_format($netto, 2) . ' KG' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align: right; text-transform: uppercase; font-size: 11.5px; font-weight: 800;">
                        Total Penerimaan Bersih (Netto):
                    </td>
                    <td style="text-align: center; font-weight: 800; font-family: 'JetBrains Mono', monospace; font-size: 12px;">
                        <?= number_format($sjData['total_pcs']) ?> Pcs
                    </td>
                    <td style="text-align: right; font-weight: 900; font-family: 'JetBrains Mono', monospace; font-size: 13.5px;">
                        <?= number_format($sjData['total_berat'], 2) ?> KG
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- 6. Catatan Syarat & Verifikasi Gudang -->
        <div class="note-box">
            <strong>Catatan & Bukti Penerimaan:</strong>
            Seluruh keranjang di atas telah diterima di gudang AR_BAROKAH dalam kondisi fisik baik, jumlah keranjang fisik dan bobot riil timbangan telah dicocokkan dan diverifikasi menggunakan scanner barcode sistem stasiun timbang.
        </div>

        <!-- 7. Tanda Tangan 3 Pihak Resmi -->
        <div class="signatures">
            <div class="sign-box">
                <div class="sign-title">Pihak Pengirim / Sopir</div>
                <div class="sign-name">( <?= htmlspecialchars($sjData['pic_pemasok'] !== '-' ? $sjData['pic_pemasok'] : 'Sopir Pemasok') ?> )</div>
                <div class="sign-role"><?= htmlspecialchars($sjData['nama_pemasok']) ?></div>
            </div>
            <div class="sign-box">
                <div class="sign-title">Petugas Penerima Gudang</div>
                <div class="sign-name">( <?= htmlspecialchars($_SESSION['user_login']['nama_lengkap'] ?? 'Petugas Timbang') ?> )</div>
                <div class="sign-role">Operator Inbound AR_BAROKAH</div>
            </div>
            <div class="sign-box">
                <div class="sign-title">Mengetahui / Supervisor</div>
                <div class="sign-name">( ........................................ )</div>
                <div class="sign-role">Kepala Gudang / Logistik</div>
            </div>
        </div>
    </div>

</body>
</html>
