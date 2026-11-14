<?php
/**
 * Dokumen Resmi Berita Acara Serah Terima (BAST) Siap Cetak (A4 Format)
 */

use App\Core\Helper;
use App\Models\Transaksi;
use App\Services\BarcodeService;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$trx = Transaksi::getByIdWithDetails($id);

if (!$trx) {
    die("Dokumen BAST tidak ditemukan. ID Transaksi tidak valid.");
}

$items = $trx['items'] ?? [];
$barcodeBast = $trx['no_bast'] ?? $trx['no_transaksi'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak BAST - <?= htmlspecialchars($trx['no_bast'] ?? $trx['no_transaksi']) ?></title>
    
    <!-- Favicon Berwarna -->
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/apple-touch-icon.png">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }
        body {
            font-family: 'Plus Jakarta Sans', Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            margin: 0;
            padding: 30px;
            font-size: 13px;
            line-height: 1.5;
        }
        .bast-page {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
        }
        .header-company {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px double #0f172a;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }
        .logo-title h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }
        .logo-title p {
            margin: 2px 0 0;
            font-size: 12px;
            color: #64748b;
        }
        .doc-title {
            text-align: center;
            margin: 20px 0;
        }
        .doc-title h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            text-decoration: underline;
            letter-spacing: 0.5px;
        }
        .doc-title .doc-number {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            margin-top: 4px;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            font-size: 12.5px;
        }
        .meta-row {
            display: flex;
            margin-bottom: 6px;
        }
        .meta-row:last-child { margin-bottom: 0; }
        .meta-label {
            width: 130px;
            color: #64748b;
            font-weight: 600;
            flex-shrink: 0;
        }
        .meta-val {
            font-weight: 700;
            color: #0f172a;
        }
        .party-box {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        .party-card {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 12px;
            background: #ffffff;
        }
        .party-card-title {
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            color: #0284c7;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .table-items {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin: 20px 0;
        }
        .table-items th {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            color: #334155;
        }
        .table-items td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
        }
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-top: 40px;
            text-align: center;
            page-break-inside: avoid;
        }
        .sign-box {
            padding: 10px;
        }
        .sign-title {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 65px;
        }
        .sign-name {
            font-weight: 800;
            font-size: 12.5px;
            border-top: 1px solid #0f172a;
            padding-top: 4px;
        }
        .sign-role {
            font-size: 11px;
            color: #64748b;
        }
        .btn-print-bar {
            max-width: 800px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        .btn-primary { background: #0284c7; color: white; }
        .btn-outline { background: white; border: 1px solid #cbd5e1; color: #0f172a; }
        @media print {
            body { background: white; padding: 0; }
            .bast-page { box-shadow: none; padding: 0; border: none; }
            .btn-print-bar { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="btn-print-bar">
        <a href="index.php?page=mutasi" class="btn btn-outline">← Kembali ke Sistem</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">🖨️ Cetak Berita Acara (BAST)</button>
    </div>

    <div class="bast-page">
        <!-- Header Perusahaan -->
        <div class="header-company">
            <div class="logo-title">
                <h1>AR_BAROKAH</h1>
                <p>Warehouse Execution System & Cold Chain Logistics Multi-Mitra</p>
                <p style="font-size: 11px;">Kawasan Industri Pergudangan Terpadu • Telp: (021) 8899-7722</p>
            </div>
            <div style="text-align: right;">
                <div style="width: 140px; margin-left: auto;">
                    <?= BarcodeService::generateCode128Svg($barcodeBast, 50, 1.8) ?>
                </div>
                <div style="font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 700; margin-top: 2px;">
                    <?= htmlspecialchars($barcodeBast) ?>
                </div>
            </div>
        </div>

        <!-- Judul Dokumen -->
        <div class="doc-title">
            <h2>BERITA ACARA SERAH TERIMA BARANG (BAST)</h2>
            <div class="doc-number">Nomor: <?= htmlspecialchars($trx['no_bast'] ?? $trx['no_transaksi']) ?></div>
        </div>

        <!-- Meta Grid -->
        <div class="meta-grid">
            <div>
                <div class="meta-row">
                    <span class="meta-label">Tanggal Serah Terima:</span>
                    <span class="meta-val"><?= Helper::formatDateTime($trx['created_at']) ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Mitra Pemilik:</span>
                    <span class="meta-val">[<?= htmlspecialchars($trx['kode_mitra']) ?>] <?= htmlspecialchars($trx['nama_mitra']) ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Gudang Accurate:</span>
                    <span class="meta-val"><?= htmlspecialchars($trx['kode_gudang_accurate']) ?></span>
                </div>
            </div>
            <div>
                <div class="meta-row">
                    <span class="meta-label">Alur Pergerakan:</span>
                    <span class="meta-val">
                        <?php 
                        $alurText = match($trx['alur_pergerakan']) {
                            'PEMASOK_KE_MITRA'    => '🚚 Pemasok / Nelayan ➔ Mitra Bisnis',
                            'MUTASI_ANTAR_MITRA'  => '🔄 Pemindahan Antar-Mitra Bisnis',
                            'PENGEPUL_KE_GUDANG'  => '🚚 Pemasok / Nelayan ➔ Mitra Bisnis',
                            'GUDANG_KE_OFFICE'    => '🔄 Mitra Bisnis ➔ Office / Outlet POS',
                            'KE_PEMBELI_POS'      => '🛒 Penjualan ke Pembeli (POS Accurate)',
                            default               => 'Pemindahan Stok Internal'
                        };
                        echo htmlspecialchars($alurText);
                        ?>
                    </span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">No. Referensi / DO:</span>
                    <span class="meta-val"><?= htmlspecialchars($trx['no_referensi_eksternal'] ?? '-') ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">No. Transaksi Sistem:</span>
                    <span class="meta-val"><code><?= htmlspecialchars($trx['no_transaksi']) ?></code></span>
                </div>
            </div>
        </div>

        <!-- Pihak Serah Terima -->
        <div class="party-box">
            <div class="party-card">
                <div class="party-card-title">PIHAK I (YANG MENYERAHKAN)</div>
                <div style="font-weight: 700; font-size: 13px;"><?= htmlspecialchars($trx['nama_penyerah'] ?? 'Pihak Penyerah') ?></div>
                <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">
                    Asal: <strong><?= htmlspecialchars($trx['nama_pemasok'] ?? ($trx['nama_mitra_asal'] ?? ($trx['nama_mitra'] ?? 'Pemasok / Mitra Asal'))) ?></strong>
                </div>
            </div>
            <div class="party-card">
                <div class="party-card-title">PIHAK II (YANG MENERIMA)</div>
                <div style="font-weight: 700; font-size: 13px; color: #10b981;"><?= htmlspecialchars($trx['nama_penerima'] ?? 'Pihak Penerima') ?></div>
                <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">
                    Tujuan: <strong><?= htmlspecialchars($trx['nama_mitra_tujuan'] ?? ($trx['nama_mitra'] ?? 'Mitra Tujuan')) ?></strong>
                    <br><span style="font-size: 11px;">Operator: <?= htmlspecialchars($trx['operator_pemroses'] ?? '-') ?> (<?= htmlspecialchars($trx['role_operator'] ?? 'User') ?>)</span>
                </div>
            </div>
        </div>

        <p style="margin: 0 0 10px; font-size: 12px; color: #334155;">
            Pada hari ini telah dilakukan serah terima fisik barang catch-weight sesuai dengan rincian barcode label berikut:
        </p>

        <!-- Tabel Rincian Kemasan Barcode -->
        <table class="table-items">
            <thead>
                <tr>
                    <th style="width: 30px; text-align: center;">No</th>
                    <th>ID Barcode Kemasan</th>
                    <th>Nama Barang / Produk</th>
                    <th>Kategori</th>
                    <th>Batch</th>
                    <th style="text-align: right;">Netto Aktual</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $index = 1;
                $totNetto = 0.0;
                foreach ($items as $it): 
                    $totNetto += (float)$it['berat_netto'];
                ?>
                <tr>
                    <td style="text-align: center;"><?= $index++ ?></td>
                    <td><strong style="font-family: 'JetBrains Mono', monospace;"><?= htmlspecialchars($it['id_label']) ?></strong></td>
                    <td><?= htmlspecialchars($it['nama_item']) ?> <span style="color: #64748b; font-size: 11px;">(<?= htmlspecialchars($it['kode_item']) ?>)</span></td>
                    <td><?= htmlspecialchars($it['nama_jenis']) ?></td>
                    <td><code><?= htmlspecialchars($it['batch_no']) ?></code></td>
                    <td style="text-align: right; font-weight: 700;"><?= Helper::formatKg($it['berat_netto']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f8fafc; font-weight: 800;">
                    <td colspan="4" style="text-align: right; text-transform: uppercase;">TOTAL SERAH TERIMA</td>
                    <td style="text-align: center;"><?= count($items) ?> Kemasan</td>
                    <td style="text-align: right; font-size: 13.5px; color: #10b981;"><?= Helper::formatKg($totNetto) ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Catatan Serah Terima -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; font-size: 12px; margin-bottom: 20px;">
            <strong>Catatan / Kondisi Fisik:</strong>
            <span style="color: #475569; margin-left: 6px;">
                <?= htmlspecialchars($trx['catatan_serah_terima'] ?: 'Kondisi barang, berat netto, dan kemasan telah diperiksa serta diterima dalam keadaan baik.') ?>
            </span>
        </div>

        <!-- Tanda Tangan 3 Pihak -->
        <div class="signatures">
            <div class="sign-box">
                <div class="sign-title">Pihak Yang Menyerahkan,</div>
                <div class="sign-name"><?= htmlspecialchars($trx['nama_penyerah'] ?? 'Penyerah') ?></div>
                <div class="sign-role">Pihak I / Pengirim</div>
            </div>
            <div class="sign-box">
                <div class="sign-title">Pihak Yang Menerima,</div>
                <div class="sign-name"><?= htmlspecialchars($trx['nama_penerima'] ?? 'Penerima') ?></div>
                <div class="sign-role">User Login Penerima</div>
            </div>
            <div class="sign-box">
                <div class="sign-title">Mengetahui / Menyetujui,</div>
                <div class="sign-name">Bambang Supriyanto</div>
                <div class="sign-role">Supervisor Gudang</div>
            </div>
        </div>

        <div style="text-align: center; font-size: 10.5px; color: #94a3b8; margin-top: 40px; border-top: 1px dashed #cbd5e1; padding-top: 8px;">
            Dokumen ini dicetak otomatis dari Sistem Barcode Inventory Multi-Mitra terintegrasi Accurate Online • Dicetak pada: <?= date('d/m/Y H:i:s') ?>
        </div>
    </div>
</body>
</html>
