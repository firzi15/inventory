<?php
/**
 * Dokumen Resmi Surat Jalan Penerimaan Barang Masuk (Inbound Goods Receipt Note / GRN)
 * Format Siap Cetak A4 Portrait Standar Pergudangan
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

$barcodeSjSvg = BarcodeService::generateCode128Svg($sjData['no_surat_jalan'], 52, 1.3);
$labels = $sjData['all_labels'] ?? [];
$isComplete = ($sjData['belum_ditimbang'] === 0 && $sjData['total_pcs'] > 0);
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm 12mm 15mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Plus Jakarta Sans', Arial, sans-serif;
            background: #ffffff;
            color: #000000;
            margin: 0;
            padding: 24px;
            font-size: 12.5px;
            line-height: 1.45;
        }
        .sj-page {
            max-width: 840px;
            margin: 0 auto;
            background: #ffffff;
            padding: 28px 34px;
            border-radius: 0;
            box-shadow: none;
            border: 1px solid #000000;
            position: relative;
        }
        .header-company {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000000;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .logo-title h1 {
            margin: 0;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: -0.3px;
            text-transform: uppercase;
            color: #000000;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .logo-title p {
            margin: 3px 0 0;
            font-size: 11.5px;
            color: #333333;
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
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
            color: #000000;
            margin-top: 2px;
        }
        .doc-title {
            text-align: center;
            margin: 12px 0 16px;
        }
        .doc-title h2 {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #000000;
        }
        .doc-title .doc-subtitle {
            font-size: 11px;
            color: #333333;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        /* GRID INFORMASI PEMASOK DAN PENERIMAAN (MONOKROM) */
        .party-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 16px;
        }
        .party-card {
            border: 1px solid #000000;
            border-radius: 0;
            padding: 12px 14px;
            background: #ffffff;
        }
        .party-card.origin,
        .party-card.destination {
            border-color: #000000;
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
            display: flex;
            align-items: center;
            gap: 6px;
            color: #000000;
        }
        .party-card.origin .party-card-title,
        .party-card.destination .party-card-title {
            color: #000000;
            border-color: #000000;
        }
        
        .meta-row {
            display: flex;
            margin-bottom: 4px;
            font-size: 11.5px;
        }
        .meta-row:last-child { margin-bottom: 0; }
        .meta-label {
            width: 120px;
            color: #333333;
            font-weight: 600;
            flex-shrink: 0;
        }
        .meta-val {
            font-weight: 700;
            color: #000000;
            flex-grow: 1;
        }

        /* SUMMARY BOX PRODUK (MONOKROM) */
        .summary-banner {
            background: #ffffff;
            border: 1px solid #000000;
            border-radius: 0;
            padding: 12px 16px;
            margin-bottom: 16px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 12px;
            align-items: center;
        }
        .summary-item-title {
            font-size: 11px;
            color: #333333;
            text-transform: uppercase;
            font-weight: 700;
        }
        .summary-item-val {
            font-size: 15px;
            font-weight: 800;
            color: #000000;
            margin-top: 2px;
        }

        /* TABEL BARANG (MONOKROM) */
        .table-items {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
            margin: 14px 0;
        }
        .table-items th {
            background: #f0f0f0;
            border: 1px solid #000000;
            padding: 7px 9px;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 10.5px;
            color: #000000;
            letter-spacing: 0.3px;
        }
        .table-items td {
            border: 1px solid #000000;
            padding: 6px 9px;
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
            padding: 8px 9px;
            color: #000000;
        }

        .badge-status {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            color: #000000;
        }
        .badge-status.success,
        .badge-status.warning {
            background: none;
            color: #000000;
            border: none;
        }

        /* CATATAN */
        .note-box {
            background: #ffffff;
            border: 1px dashed #000000;
            border-radius: 0;
            padding: 10px 14px;
            margin-top: 14px;
            font-size: 11px;
            color: #000000;
        }
        .note-box strong {
            color: #000000;
        }

        /* TANDA TANGAN 3 PIHAK */
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-top: 28px;
            text-align: center;
            page-break-inside: avoid;
        }
        .sign-box {
            padding: 6px;
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
            color: #333333;
            margin-top: 2px;
        }

        /* ACTIONS HEADER (NO PRINT) */
        .print-actions {
            max-width: 840px;
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
            }
            .sj-page {
                box-shadow: none !important;
                padding: 0 !important;
                border: none !important;
                max-width: 100% !important;
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
            .party-card.origin, .party-card.destination {
                background: #ffffff !important;
                border: 1px solid #000000 !important;
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
        <a href="index.php?page=timbang_cetak&tab=scantimbang" class="btn-action btn-back">
            Kembali ke Stasiun Timbang
        </a>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()" class="btn-action btn-print">
                Cetak Dokumen Penerimaan (A4 / PDF)
            </button>
        </div>
    </div>

    <!-- Halaman Dokumen Cetak Surat Jalan Penerimaan -->
    <div class="sj-page">
        <!-- 1. Header Perusahaan & Barcode Surat Jalan -->
        <div class="header-company">
            <div class="logo-title">
                <h1>
                    <span>GUDANG MITRA</span>
                </h1>
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
            <div class="party-card origin">
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
                    <div class="meta-val"><?= htmlspecialchars($sjData['pic_pemasok'] ?? '-') ?> (<?= htmlspecialchars($sjData['telepon_pemasok'] ?? '-') ?>)</div>
                </div>
                <div class="meta-row">
                    <div class="meta-label">Alamat / Asal:</div>
                    <div class="meta-val" style="font-size: 11px;"><?= htmlspecialchars($sjData['alamat_pemasok'] ?? '-') ?></div>
                </div>
            </div>

            <!-- Kolom Kanan: Rincian Penerimaan Gudang -->
            <div class="party-card destination">
                <div class="party-card-title">
                    <span>DOKUMEN & PENERIMA GUDANG</span>
                </div>
                <div class="meta-row">
                    <div class="meta-label">No. Surat Jalan:</div>
                    <div class="meta-val"><strong style="color: #000000; font-family: 'JetBrains Mono', monospace;"><?= htmlspecialchars($sjData['no_surat_jalan']) ?></strong></div>
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
                    <div class="meta-label">Status Timbangan:</div>
                    <div class="meta-val">
                        <?php if ($isComplete): ?>
                            <span class="badge-status">LENGKAP (Semua Ditimbang)</span>
                        <?php else: ?>
                            <span class="badge-status">PARSIAL (<?= $sjData['belum_ditimbang'] ?> Belum)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Ringkasan Komoditas / Barang -->
        <div class="summary-banner">
            <div>
                <div class="summary-item-title">Komoditas / Nama Barang</div>
                <div class="summary-item-val"><?= htmlspecialchars($sjData['nama_item']) ?> <small style="font-size: 12px; color: #444444; font-weight: normal;">(<?= htmlspecialchars($sjData['nama_jenis']) ?>)</small></div>
            </div>
            <div style="text-align: center;">
                <div class="summary-item-title">Total Keranjang</div>
                <div class="summary-item-val"><?= number_format($sjData['total_pcs']) ?> Pcs</div>
            </div>
            <div style="text-align: center;">
                <div class="summary-item-title">Total Bobot Riil</div>
                <div class="summary-item-val" style="color: #000000;"><?= number_format($sjData['total_berat'], 2) ?> KG</div>
            </div>
            <div style="text-align: right;">
                <div class="summary-item-title">Rata-rata/Keranjang</div>
                <div class="summary-item-val"><?= number_format($sjData['avg_berat'], 2) ?> KG</div>
            </div>
        </div>

        <!-- 5. Tabel Detail Keranjang & Barcode -->
        <table class="table-items">
            <thead>
                <tr>
                    <th style="width: 38px; text-align: center;">No</th>
                    <th style="text-align: left;">ID Barcode Keranjang</th>
                    <th style="text-align: center; width: 130px;">Status Timbang</th>
                    <th style="text-align: center; width: 140px;">Waktu Timbang</th>
                    <th style="text-align: left; width: 140px;">Operator Timbang</th>
                    <th style="width: 100px; text-align: right;">Bobot Riil</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($labels)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 18px; color: #000000;">
                            Tidak ada rincian kemasan barcode untuk Surat Jalan ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = 1;
                    foreach ($labels as $lbl): 
                        $netto = (float)($lbl['berat_netto'] ?? 0);
                        $isWeighed = ($netto > 0);
                    ?>
                        <tr>
                            <td style="text-align: center; color: #000000;"><?= $no++ ?></td>
                            <td>
                                <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12px; color: #000000;">
                                    <?= htmlspecialchars($lbl['id_label']) ?>
                                </strong>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($isWeighed): ?>
                                    <span class="badge-status">Ditimbang</span>
                                <?php else: ?>
                                    <span class="badge-status">Belum Ditimbang</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; font-size: 11px; color: #000000;">
                                <?= $isWeighed ? date('d/m/y H:i', strtotime($lbl['updated_at'] ?? $lbl['created_at'])) : '-' ?>
                            </td>
                            <td style="font-size: 11px; color: #000000;">
                                <?= htmlspecialchars($lbl['operator_nama'] ?? 'Operator Gudang') ?>
                            </td>
                            <td style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; color: #000000;">
                                <?= $isWeighed ? number_format($netto, 2) . ' KG' : '0.00 KG' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align: right; text-transform: uppercase;">Total Penerimaan Bersih (Netto):</td>
                    <td style="text-align: center;"><?= number_format($sjData['sudah_ditimbang']) ?> / <?= number_format($sjData['total_pcs']) ?> Pcs</td>
                    <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 13px; color: #000000;">
                        <?= number_format($sjData['total_berat'], 2) ?> KG
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- 6. Catatan Syarat & Verifikasi Gudang -->
        <div class="note-box">
            <strong>Catatan & Pernyataan Penerimaan:</strong>
            Barang di atas telah diterima dalam kondisi fisik baik, jumlah keranjang fisik dan bobot riil timbangan telah dicocokkan dan diverifikasi menggunakan scanner barcode stasiun timbang Gudang Mitra.
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
                <div class="sign-role">Operator Inbound Gudang Mitra</div>
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
