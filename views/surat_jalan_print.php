<?php
/**
 * Dokumen Resmi Surat Jalan Pengeluaran Barang (Delivery Order / Outbound SJ)
 * Format Siap Cetak A4 Portrait & 3-Ply Paper
 * Mendukung 2 Mode:
 * 1. Mode Admin: Lengkap dengan Harga satuan (@Kg), Subtotal, dan Total Nilai Tagihan Piutang (Rp).
 * 2. Mode Mitra: Non-Harga (tanpa nominal rupiah), hanya menampilkan Satuan Kemasan (Dus/Keranjang) & Bobot Netto Fisik (Kg).
 */

use App\Core\Helper;
use App\Models\Transaksi;
use App\Services\BarcodeService;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$trx = Transaksi::getByIdWithDetails($id);

if (!$trx) {
    die("Dokumen Surat Jalan tidak ditemukan. ID Transaksi tidak valid.");
}

$items = $trx['items'] ?? [];
$noSuratJalan = !empty($trx['no_referensi_eksternal']) ? $trx['no_referensi_eksternal'] : (!empty($trx['no_bast']) ? $trx['no_bast'] : $trx['no_transaksi']);
$barcodeSjSvg = BarcodeService::generateCode128Svg($noSuratJalan, 60, 1.8);

// Pilihan Mode Cetak: 'admin' (ada harga), 'mitra' (non-harga, hanya satuan), 'keduanya' (cetak 2 lembar)
$mode = strtolower(trim($_GET['mode'] ?? 'admin'));
if (!in_array($mode, ['admin', 'mitra', 'keduanya'])) {
    $mode = 'admin';
}

/**
 * Fungsi Render Satu Lembar Halaman Surat Jalan (Admin / Mitra)
 */
function renderSuratJalanSheet(array $trx, array $items, string $noSuratJalan, string $barcodeSjSvg, string $viewType = 'admin'): void {
    $isAdmin = ($viewType === 'admin');
    $mitraId = (int)($trx['mitra_id'] ?? 0);
    $totalNetto = 0.0;
    $grandTotalJual = 0.0;

    foreach ($items as &$it) {
        $netto = (float)$it['berat_netto'];
        $totalNetto += $netto;
        
        $hrgMitra = (float)($it['harga_mitra'] ?? 0);
        if ($hrgMitra <= 0 && $mitraId > 0 && !empty($it['item_id'])) {
            $hrgMitra = (float)\App\Models\MasterHarga::getHargaByMitraAndItem($mitraId, (int)$it['item_id']);
        }
        $it['harga_mitra_calc'] = $hrgMitra;

        $totJual = (float)($it['total_jual'] ?? 0);
        if ($totJual <= 0) {
            $totJual = round($netto * $hrgMitra, 2);
        }
        $it['total_jual_calc'] = $totJual;
        $grandTotalJual += $totJual;
    }
    unset($it);

    if (!empty($trx['total_nilai_jual']) && (float)$trx['total_nilai_jual'] > 0) {
        $grandTotalJual = (float)$trx['total_nilai_jual'];
    }
    ?>
    <div class="sj-page <?= $isAdmin ? 'version-admin' : 'version-mitra' ?>">
        <!-- Indikator Rangkap Cetak (3-Ply Paper) -->
        <div class="ply-indicator" style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-weight: 800; font-size: 11px; color: #000000;">
                <?= $isAdmin ? 'SALINAN INTERNAL: ADMIN & FINANCE' : 'SALINAN PENERIMA: MITRA / EKSPEDISI' ?>
            </span>
            <span style="color: #444444;">
                <?= $isAdmin 
                    ? 'LEMBAR 1: FINANCE / PENAGIHAN (PUTIH) • LENGKAP HARGA & PIUTANG' 
                    : 'LEMBAR 2: MITRA PEMBELI (MERAH/KUNING) • NON-HARGA (FISIK & SATUAN)' ?>
            </span>
        </div>

        <!-- Header Perusahaan & Barcode Surat Jalan -->
        <div class="header-company">
            <div class="logo-title">
                <h1>AR_BAROKAH</h1>
                <p>Sistem Manajemen Inventory Multi-Mitra & Pengeluaran Catch-Weight</p>
            </div>
            <div class="barcode-header">
                <div class="barcode-header-svg">
                    <?= $barcodeSjSvg ?>
                </div>
                <div class="barcode-header-text"><?= htmlspecialchars($noSuratJalan) ?></div>
            </div>
        </div>

        <!-- Judul Dokumen -->
        <div class="doc-title">
            <h2>Surat Jalan Pengeluaran Barang</h2>
            <div class="doc-subtitle">
                <?= $isAdmin 
                    ? 'Delivery Order & Outbound Invoice Note (Versi Admin / Tagihan)' 
                    : 'Delivery Order / Outbound Dispatch Note (Versi Mitra / Bukti Serah Terima)' ?>
            </div>
        </div>

        <!-- Kartu 2 Kolom: Asal Pengeluaran Gudang (Kiri) & Tujuan Pengeluaran Mitra (Kanan) -->
        <div class="party-grid">
            <!-- Kolom 1: Asal Pengeluaran Barang (Gudang) -->
            <div class="party-card origin">
                <div class="party-card-title">
                    <span>Asal Pengeluaran (AR_BAROKAH)</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Lokasi Gudang:</span>
                    <span class="meta-val">AR_BAROKAH</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Diserahkan Oleh:</span>
                    <span class="meta-val"><?= htmlspecialchars($trx['nama_penyerah'] ?: ($trx['operator_pemroses'] ?: 'Petugas Gudang')) ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Status Verifikasi:</span>
                    <span class="meta-val" style="color: #000000; font-weight: 700;">Fisik & Timbangan Terverifikasi</span>
                </div>
            </div>

            <!-- Kolom 2: Tujuan Pengeluaran (Mitra Bisnis Penerima) -->
            <div class="party-card destination">
                <div class="party-card-title">
                    <span>Tujuan Pengeluaran (Mitra Bisnis Penerima)</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Mitra Penerima:</span>
                    <span class="meta-val">
                        <strong style="color: #000000; font-size: 13px;">
                            [<?= htmlspecialchars($trx['kode_mitra'] ?? 'MTR') ?>] <?= htmlspecialchars($trx['nama_mitra'] ?? 'Mitra Bisnis') ?>
                        </strong>
                    </span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">PIC / Penerima:</span>
                    <span class="meta-val">
                        <?= htmlspecialchars($trx['nama_penerima'] ?: ($trx['pic_mitra'] ?: 'Customer / Pembeli')) ?>
                    </span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">No. Surat Jalan:</span>
                    <span class="meta-val" style="font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 800; color: #000000;">
                        <?= htmlspecialchars($noSuratJalan) ?>
                    </span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Status Penjualan:</span>
                    <span class="meta-val" style="color: #000000; font-weight: 800;">
                        <?= $isAdmin ? 'Beli Putus (Piutang Dagang Mitra)' : 'Penyerahan Fisik Barang (Catch-Weight)' ?>
                    </span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">No. Transaksi TRX:</span>
                    <span class="meta-val" style="font-family: 'JetBrains Mono', monospace; color: #000000;"><?= htmlspecialchars($trx['no_transaksi']) ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Waktu Pengeluaran:</span>
                    <span class="meta-val"><?= date('d F Y, H:i', strtotime($trx['created_at'])) ?> WIB</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Catatan / Ekspedisi:</span>
                    <span class="meta-val"><?= htmlspecialchars($trx['keterangan'] ?: '-') ?></span>
                </div>
            </div>
        </div>

        <!-- Tabel Rincian Kemasan / Barcode -->
        <table class="table-items">
            <thead>
                <tr>
                    <th style="width: 30px; text-align: center;">No</th>
                    <th style="width: <?= $isAdmin ? '155px' : '175px' ?>;">ID Barcode Kemasan</th>
                    <th>Nama Barang / Produk SKU</th>
                    <th style="width: 85px;">Kategori</th>
                    <th style="width: <?= $isAdmin ? '85px' : '105px' ?>; text-align: right;">Netto (Kg)</th>
                    <?php if ($isAdmin): ?>
                        <th style="width: 105px; text-align: right;">Harga (@Kg)</th>
                        <th style="width: 125px; text-align: right;">Subtotal (Rp)</th>
                    <?php else: ?>
                        <th style="width: 125px; text-align: center;">Satuan Kemasan</th>
                    <?php endif; ?>
                    <th style="width: 105px;">SJ Asal</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach ($items as $item): 
                    $netto = (float)$item['berat_netto'];
                    $hrgMitra = $item['harga_mitra_calc'] ?? 0;
                    $subTotal = $item['total_jual_calc'] ?? 0;
                    $sjAsal = !empty($item['no_surat_jalan']) ? $item['no_surat_jalan'] : (!empty($item['batch_no']) ? $item['batch_no'] : '-');
                    
                    // Format Satuan untuk Mitra (hanya menampilkan satuan fisik, e.g. 1 Dus / 1 Keranjang)
                    $satuanName = !empty($item['satuan_accurate']) ? strtoupper($item['satuan_accurate']) : (!empty($item['satuan_stok']) ? strtoupper($item['satuan_stok']) : 'DUS');
                ?>
                <tr>
                    <td style="text-align: center; color: #000000; font-size: 11px;"><?= $no++ ?></td>
                    <td>
                        <strong style="font-family: 'JetBrains Mono', monospace; font-size: 11.5px; color: #000000;">
                            <?= htmlspecialchars($item['id_label']) ?>
                        </strong>
                    </td>
                    <td>
                        <strong style="color: #000000;"><?= htmlspecialchars($item['nama_item']) ?></strong>
                        <?php if (!empty($item['kode_item'])): ?>
                            <span style="font-size: 10px; color: #444444; font-family: 'JetBrains Mono', monospace;">(<?= htmlspecialchars($item['kode_item']) ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td style="color: #000000; font-size: 11px;"><?= htmlspecialchars($item['nama_jenis']) ?></td>
                    <td style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; font-size: 12px; color: #000000;">
                        <?= Helper::formatKg($netto) ?>
                    </td>
                    <?php if ($isAdmin): ?>
                        <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 11.5px; color: #000000;">
                            <?= $hrgMitra > 0 ? Helper::formatRupiah($hrgMitra) : '-' ?>
                        </td>
                        <td style="text-align: right; font-weight: 800; font-family: 'JetBrains Mono', monospace; font-size: 12px; color: #000000;">
                            <?= $subTotal > 0 ? Helper::formatRupiah($subTotal) : '-' ?>
                        </td>
                    <?php else: ?>
                        <!-- Versi Mitra: Menampilkan Satuan Kemasan Polos Tanpa Badge Warna -->
                        <td style="text-align: center; font-weight: 700; color: #000000;">
                            1 <?= htmlspecialchars($satuanName) ?>
                        </td>
                    <?php endif; ?>
                    <td style="font-family: 'JetBrains Mono', monospace; font-size: 10.5px; color: #000000;">
                        <?= htmlspecialchars($sjAsal) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align: right; text-transform: uppercase; font-size: 11px; color: #000000;">
                        Total Pengeluaran (<?= count($items) ?> Dus / Kemasan):
                    </td>
                    <td style="text-align: right; font-size: 13.5px; font-weight: 900; font-family: 'JetBrains Mono', monospace; color: #000000;">
                        <?= Helper::formatKg($totalNetto) ?>
                    </td>
                    <?php if ($isAdmin): ?>
                        <td></td>
                        <td style="text-align: right; font-size: 13.5px; font-weight: 900; font-family: 'JetBrains Mono', monospace; color: #000000;">
                            <?= Helper::formatRupiah($grandTotalJual) ?>
                        </td>
                    <?php else: ?>
                        <td style="text-align: center; font-size: 11.5px; font-weight: 800; color: #000000;">
                            <?= count($items) ?> Dus Utuh
                        </td>
                    <?php endif; ?>
                    <td style="text-align: center; font-size: 10px; color: #000000;">
                        -
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- GRAND SUMMARY BOX -->
        <div class="summary-grand-box" style="<?= !$isAdmin ? 'grid-template-columns: 1fr 1.3fr 1.4fr;' : '' ?>">
            <div class="summary-col">
                <div class="lbl">TOTAL DUS / KEMASAN</div>
                <div class="val"><?= count($items) ?> Dus</div>
                <div style="font-size: 10.5px; color: #444444; margin-top: 2px;">Terverifikasi Scan Outbound</div>
            </div>
            <div class="summary-col">
                <div class="lbl">TOTAL BOBOT FISIK BERSIH (NETTO)</div>
                <div class="val" style="color: #000000; font-size: 18px;"><?= Helper::formatKg($totalNetto) ?></div>
                <div style="font-size: 10.5px; color: #000000; margin-top: 2px; font-weight: 700;">100% Catch-Weight Riil</div>
            </div>
            <?php if ($isAdmin): ?>
                <!-- Versi Admin: Total Nilai Tagihan Piutang (Rp) -->
                <div class="summary-col highlight">
                    <div class="lbl">TOTAL NILAI TAGIHAN / PIUTANG MITRA (RP)</div>
                    <div class="val" style="font-size: 20px; color: #000000;"><?= Helper::formatRupiah($grandTotalJual) ?></div>
                    <div style="font-size: 10.5px; color: #000000; margin-top: 2px; font-weight: 800;">Beli Putus (Piutang Mitra)</div>
                </div>
            <?php else: ?>
                <!-- Versi Mitra: Non-Harga, Menampilkan Format Satuan Penyerahan -->
                <div class="summary-col highlight">
                    <div class="lbl" style="color: #000000;">SATUAN FISIK SERAH TERIMA</div>
                    <div class="val" style="font-size: 17px; color: #000000;">Kemasan Dus / Keranjang</div>
                    <div style="font-size: 10.5px; color: #000000; margin-top: 2px; font-weight: 700;">Non-Harga • Periksa Fisik & Timbangan</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Informasi Tambahan / Syarat Ketentuan -->
        <div style="font-size: 10.5px; color: #333333; margin-top: 8px; line-height: 1.5; border-top: 1px dashed #000000; padding-top: 8px;">
            <?php if ($isAdmin): ?>
                <div><strong>* SYARAT & KETENTUAN PENERIMAAN (BELI PUTUS):</strong> Barang yang tercantum pada surat jalan ini telah diserahterimakan dan sah dicatat sebagai <strong>Piutang Dagang Mitra Penerima</strong>. Pembeli wajib memeriksa kondisi fisik kemasan dan bobot timbangan pada saat serah terima.</div>
            <?php else: ?>
                <div><strong>* BUKTI SERAH TERIMA FISIK BARANG:</strong> Barang yang dikeluarkan telah diverifikasi fisik dan sistem melalui scan barcode per dus catch-weight. Mohon pihak mitra penerima memeriksa keutuhan dus dan bobot timbangan pada saat serah terima.</div>
            <?php endif; ?>
        </div>

        <!-- Kolom Tanda Tangan 3 Pihak -->
        <div class="signatures">
            <div class="sign-box">
                <div class="sign-title">Diserahkan Oleh (Gudang)</div>
                <div class="sign-name"><?= htmlspecialchars($trx['nama_penyerah'] ?: ($trx['operator_pemroses'] ?: 'Petugas Gudang')) ?></div>
                <div class="sign-role">Operator Outbound Gudang</div>
            </div>
            <div class="sign-box">
                <div class="sign-title">Pembawa / Sopir Ekspedisi</div>
                <div class="sign-name">( ........................................ )</div>
                <div class="sign-role">Driver / Ekspedisi Pengantar</div>
            </div>
            <div class="sign-box">
                <div class="sign-title">Diterima Oleh (Mitra Bisnis)</div>
                <div class="sign-name"><?= htmlspecialchars($trx['nama_penerima'] ?: ($trx['pic_mitra'] ?: 'Mitra Bisnis Penerima')) ?></div>
                <div class="sign-role">Pihak Mitra Bisnis Penerima</div>
            </div>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan Pengeluaran (<?= strtoupper($mode) ?>) - <?= htmlspecialchars($noSuratJalan) ?></title>
    
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
            size: 8.5in 11in; /* form bersambung 9 1/2 x 11 setelah strip berlubang dirobek = Letter Fanfold 8 1/2 x 11 */
            margin: 8mm 10mm 8mm 10mm;
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
            max-width: 8.5in;
            margin: 0 auto;
            background: #ffffff;
            padding: 28px 32px;
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
            margin-bottom: 14px;
        }
        .logo-title h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.3px;
            text-transform: uppercase;
            color: #000000;
        }
        .logo-title p {
            margin: 2px 0 0;
            font-size: 11.5px;
            color: #333333;
        }
        .barcode-header {
            text-align: right;
            max-width: 250px;
        }
        .barcode-header-svg {
            height: 38px;
            display: flex;
            justify-content: flex-end;
        }
        .barcode-header-svg svg {
            height: 38px;
            max-width: 230px;
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
            margin: 10px 0 14px;
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

        /* GRID INFORMASI ASAL STOK DAN TUJUAN (FULL MONOKROM) */
        .party-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 14px;
        }
        .party-card {
            border: 1px solid #000000;
            border-radius: 0;
            padding: 10px 12px;
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
            margin-bottom: 6px;
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
            margin-bottom: 3.5px;
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

        /* TABEL BARANG (FULL HITAM PUTIH) */
        .table-items {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
            margin: 12px 0;
        }
        .table-items th {
            background: #f0f0f0;
            border: 1px solid #000000;
            padding: 7px 8px;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 10.5px;
            color: #000000;
            letter-spacing: 0.3px;
        }
        .table-items td {
            border: 1px solid #000000;
            padding: 5.5px 8px;
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
            padding: 7px 8px;
            color: #000000;
        }

        /* GRAND SUMMARY BOX TOTAL KG & RUPIAH (FULL MONOKROM) */
        .summary-grand-box {
            border: 2px solid #000000;
            background: #ffffff;
            border-radius: 0;
            display: grid;
            grid-template-columns: 1fr 1.3fr 1.7fr;
            margin: 12px 0 10px;
            overflow: hidden;
        }
        .summary-col {
            padding: 9px 12px;
            border-right: 1px solid #000000;
            background: #ffffff;
        }
        .summary-col:last-child {
            border-right: none;
        }
        .summary-col.highlight {
            background: #ffffff;
            border-left: 1px solid #000000;
        }
        .summary-col .lbl {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            color: #000000;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .summary-col .val {
            font-family: 'JetBrains Mono', monospace;
            font-size: 16px;
            font-weight: 900;
            color: #000000;
        }
        .ply-indicator {
            font-size: 10.5px;
            font-weight: 700;
            color: #000000;
            text-align: right;
            margin-bottom: 8px;
            letter-spacing: 0.3px;
        }

        /* TANDA TANGAN 3 PIHAK */
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-top: 20px;
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
            margin-bottom: 50px;
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
            margin-top: 1px;
        }

        .table-items tfoot td { white-space: nowrap; }

        /* TOMBOL NAVIGASI SCREEN */
        .btn-print-bar {
            max-width: 8.5in;
            margin: 0 auto 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 12.5px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .btn-primary { background: #000000; color: #ffffff; border: 1px solid #000000; }
        .btn-primary:hover { background: #333333; }
        .btn-outline { background: #ffffff; border: 1px solid #000000; color: #000000; }
        .btn-outline:hover { background: #f0f0f0; }

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
            .btn-print-bar {
                display: none !important;
            }
            .page-break-divider {
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
            .party-card.origin,
            .party-card.destination {
                background: #ffffff !important;
                border: 1px solid #000000 !important;
            }
            .party-card-title {
                color: #000000 !important;
                border-bottom: 1px solid #000000 !important;
            }
            .summary-grand-box {
                border: 2px solid #000000 !important;
                background: #ffffff !important;
            }
            .summary-col {
                border-right: 1px solid #000000 !important;
                background: #ffffff !important;
            }
            .summary-col.highlight {
                background: #ffffff !important;
                border-left: 1px solid #000000 !important;
            }
        }
    </style>
</head>
<body>

    <!-- Bar Navigasi (Hanya muncul di Layar Monitor) -->
    <div class="btn-print-bar no-print">
        <a href="index.php?page=pengeluaran" class="btn btn-outline">
            Kembali ke Menu Pengeluaran
        </a>

        <!-- Switcher Pilihan Versi Cetak -->
        <div style="display: flex; gap: 4px; background: #ffffff; padding: 2px; border: 1px solid #000000; border-radius: 4px;">
            <a href="index.php?page=surat_jalan_print&id=<?= $id ?>&mode=admin" class="btn <?= $mode === 'admin' ? 'btn-primary' : 'btn-outline' ?>" style="padding: 5px 12px; font-size: 11.5px; height: 32px; border: none;" title="Cetak dengan rincian harga per kg dan total tagihan piutang">
                Versi Admin (Ada Harga)
            </a>
            <a href="index.php?page=surat_jalan_print&id=<?= $id ?>&mode=mitra" class="btn <?= $mode === 'mitra' ? 'btn-primary' : 'btn-outline' ?>" style="padding: 5px 12px; font-size: 11.5px; height: 32px; border: none;" title="Cetak untuk mitra: non-harga, hanya menampilkan satuan kemasan dus & bobot kg">
                Versi Mitra (Non-Harga, Hanya Satuan)
            </a>
            <a href="index.php?page=surat_jalan_print&id=<?= $id ?>&mode=keduanya" class="btn <?= $mode === 'keduanya' ? 'btn-primary' : 'btn-outline' ?>" style="padding: 5px 12px; font-size: 11.5px; height: 32px; border: none;" title="Cetak 2 lembar sekaligus: Lembar 1 Admin + Lembar 2 Mitra">
                Cetak Keduanya (Admin + Mitra)
            </a>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-primary" onclick="window.print()">
                Cetak <?= $mode === 'mitra' ? 'SJ Mitra' : ($mode === 'keduanya' ? 'Keduanya' : 'SJ Admin') ?>
            </button>
        </div>
    </div>

    <!-- Output Lembar Cetak Sesuai Mode Terpilih -->
    <?php
    if ($mode === 'admin') {
        renderSuratJalanSheet($trx, $items, $noSuratJalan, $barcodeSjSvg, 'admin');
    } elseif ($mode === 'mitra') {
        renderSuratJalanSheet($trx, $items, $noSuratJalan, $barcodeSjSvg, 'mitra');
    } elseif ($mode === 'keduanya') {
        renderSuratJalanSheet($trx, $items, $noSuratJalan, $barcodeSjSvg, 'admin');
        echo '<div class="page-break-divider no-print" style="margin: 25px auto; max-width: 8.5in; text-align: center; border-top: 2px dashed #000000; position: relative;"><span style="position: relative; top: -11px; background: #ffffff; padding: 0 12px; font-size: 11.5px; font-weight: 700; color: #000000; border: 1px solid #000000;">PEMBATAS HALAMAN: LEMBAR MITRA (NON-HARGA) BERIKUTNYA</span></div>';
        echo '<div style="page-break-after: always; break-after: page;"></div>';
        renderSuratJalanSheet($trx, $items, $noSuratJalan, $barcodeSjSvg, 'mitra');
    }
    ?>

</body>
</html>
