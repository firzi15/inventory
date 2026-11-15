<?php
/**
 * Surat Jalan Penerimaan Barang Masuk (Goods Receipt Note).
 * Dirancang untuk form bersambung 3 rangkap yang dicetak di printer dot matrix:
 * lebar cetak 8,5 inci (di antara strip berlubang) dan tinggi satu lembar 5,5 inci (garis sobekan ke garis sobekan).
 * Bila barangnya banyak, tabel dilanjutkan ke lembar berikutnya; total dan tanda tangan ada di lembar terakhir.
 * ?kertas=penuh memakai lembar 11 inci.
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
    die("Data Penerimaan untuk Surat Jalan '" . htmlspecialchars($noSuratJalan) . "' tidak ditemukan di database.");
}
$semuaBatal = !empty($sjData['all_labels']);
foreach (($sjData['all_labels'] ?? []) as $lbCek) { if (($lbCek['status'] ?? '') !== 'VOID') { $semuaBatal = false; break; } }
if ($semuaBatal) {
    die("Penerimaan untuk surat jalan '" . htmlspecialchars($noSuratJalan) . "' sudah dibatalkan dan tidak dapat dicetak.");
}

$barcodeSjSvg = BarcodeService::generateCode128Svg($sjData['no_surat_jalan'], 56, 1.6);
$labels = $sjData['all_labels'] ?? [];

// Rekap per produk bila dalam 1 SJ ada beberapa produk
$uniqueItems = [];
foreach ($labels as $lbl) {
    $itemId = $lbl['item_id'] ?? 0;
    if (!isset($uniqueItems[$itemId])) {
        $uniqueItems[$itemId] = ['nama_item' => $lbl['nama_item'] ?? '-', 'nama_jenis' => $lbl['nama_jenis'] ?? '-', 'total_pcs' => 0, 'total_berat' => 0.0];
    }
    $uniqueItems[$itemId]['total_pcs']++;
    $uniqueItems[$itemId]['total_berat'] += (float)($lbl['berat_netto'] ?? 0);
    $uniqueItems[$itemId]['total_nilai'] = ($uniqueItems[$itemId]['total_nilai'] ?? 0.0) + (float)($lbl['total_nilai'] ?? 0);
    $grandNilai = ($grandNilai ?? 0.0) + (float)($lbl['total_nilai'] ?? 0);
}
$grandNilai = $grandNilai ?? 0.0;
$rp = fn($n) => 'Rp ' . number_format((float)$n, 0, ',', '.');
$isMultiItem = count($uniqueItems) > 1;

// Ukuran lembar dan pembagian baris per lembar (satuan mm)
// Ukuran kertas: setengah form 9½ x 5½ inci (bawaan), A4 penuh, atau Letter 11 inci
$kertas = strtolower($_GET['kertas'] ?? 'setengah');
if (!in_array($kertas, ['a4', 'setengah', 'penuh'], true)) $kertas = 'setengah';
[$lebarMm, $tinggiMm, $mAtas, $mSisi] = ['a4' => [210, 297, 8, 10], 'setengah' => [241.3, 139.7, 12, 20.6], 'penuh' => [215.9, 279.4, 8, 10]][$kertas];
$lembarTinggi = $tinggiMm > 200;
$labelKertas = ['a4' => 'A4 (210 x 297 mm)', 'setengah' => 'Setengah form (9½ x 5½ inci)', 'penuh' => 'Letter 8½ x 11 inci'][$kertas];
// Setengah form: margin atas lebih besar (nama perusahaan tidak terpotong), isi digeser ke kiri; margin bawah tipis
$mBawah = $kertas === 'setengah' ? 4 : $mAtas;
$mKiri = $kertas === 'setengah' ? 3 : $mSisi;
$mKanan = $kertas === 'setengah' ? $lebarMm - 184 - 3 : $mSisi;
$tinggiSheet = $tinggiMm - $mAtas - $mBawah - 2;
$lebarSheet = $lebarMm - $mKiri - $mKanan;
$dasarMm = $lembarTinggi ? 8.0 : 5.0; // ruang untuk garis dasar lembar
$barisMm = $lembarTinggi ? 7.4 : 4.9; $kepalaTabelMm = $lembarTinggi ? 8.0 : 5.2; $kakiMm = $lembarTinggi ? 52.0 : 41.0; $kepalaLanjutMm = $lembarTinggi ? 12.0 : 7.0;
$kepalaAwalMm = ($lembarTinggi ? 60.0 : 42.0) + ($isMultiItem ? 4.2 * (min(count($uniqueItems), 4) - 1) : 0);

if ($isMultiItem) $kakiMm += count($uniqueItems) * $barisMm; // baris subtotal per produk di lembar terakhir
$lembar = [];
$i = 0; $n = count($labels); $ke = 0;
while (true) {
    $kepala = $ke === 0 ? $kepalaAwalMm : $kepalaLanjutMm;
    $kapPenuh = max(1, (int)floor(($tinggiSheet - $dasarMm - $kepala - $kepalaTabelMm) / $barisMm));
    $kapAkhir = max(0, (int)floor(($tinggiSheet - $dasarMm - $kepala - $kepalaTabelMm - $kakiMm) / $barisMm));
    $sisa = $n - $i;
    if ($sisa <= $kapAkhir) { $lembar[] = ['baris' => array_slice($labels, $i), 'awal' => $i, 'akhir' => true]; break; }
    $ambil = min($kapPenuh, $sisa);
    $lembar[] = ['baris' => array_slice($labels, $i, $ambil), 'awal' => $i, 'akhir' => false];
    $i += $ambil; $ke++;
}
$jumlahLembar = count($lembar);
// Petugas penerima = akun yang menimbang/menerima barang ini; bila tidak tercatat, akun yang sedang login
$petugas = '';
foreach ($labels as $lbl) { $op = trim((string)($lbl['operator_nama'] ?? '')); if ($op !== '' && $op !== 'Operator Penerimaan') { $petugas = $op; break; } }
if ($petugas === '') $petugas = $_SESSION['user_login']['nama_lengkap'] ?? 'Petugas Penerimaan Gudang';
$picPemasok = ($sjData['pic_pemasok'] ?? '-') !== '-' ? $sjData['pic_pemasok'] : 'Sopir Pemasok';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan Penerimaan - <?= htmlspecialchars($sjData['no_surat_jalan']) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <style>
        /* Huruf sistem, hitam pekat, garis tebal: hasil paling jelas di printer dot matrix */
        @page { size: <?= $lebarMm ?>mm <?= $tinggiMm ?>mm; margin: <?= $mAtas ?>mm <?= $mKanan ?>mm <?= $mBawah ?>mm <?= $mKiri ?>mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; background: #ffffff; color: #000000; margin: 0; padding: 20px; font-size: 12px; line-height: 1.3; }
        .mono { font-family: 'Courier New', Courier, monospace; }

        .print-actions { max-width: <?= $lebarSheet ?>mm; margin: 0 auto 14px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .print-actions .info { font-size: 12px; color: #444444; }
        .btn-action { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-family: inherit; font-size: 12.5px; font-weight: 400; border-radius: 4px; cursor: pointer; text-decoration: none; border: 1px solid #000000; }
        .btn-print { background: #000000; color: #ffffff; }
        .btn-back { background: #ffffff; color: #000000; }

        .sheet { width: <?= $lebarSheet ?>mm; max-width: 100%; height: <?= round($tinggiSheet, 1) ?>mm; margin: 0 auto 14px; background: #ffffff; border: 1px solid #999999; padding: 0; position: relative; overflow: hidden; }
        .sheet:last-child { margin-bottom: 0; }
        /* Garis dasar di ujung bawah setiap lembar: kepala cetak dot matrix harus berjalan sampai batas bawah kertas,
           sehingga kertas berhenti tepat di garis sobekan, bukan di tengah lembar */
        .dasar { position: absolute; left: 0; right: 0; bottom: 0; border-top: 1.5px dashed #000000; padding-top: 1px; display: flex; justify-content: space-between; font-size: 9.5px; font-weight: 400; line-height: 1.2; }

        .kop { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #000000; padding-bottom: 3px; margin-bottom: 4px; }
        .kop .nama { font-size: 17px; font-weight: 400; line-height: 1.1; }
        .kop .judul { font-size: 14px; font-weight: 400; text-transform: uppercase; line-height: 1.15; }
        .kop .judul small { display: block; font-size: 10.5px; font-weight: 400; text-transform: none; }
        .kop .kiri { flex: 1; min-width: 0; }
        .kop .kiri .judul { text-align: left; }
        .kop .kode { text-align: right; }
        .kop .kode svg { height: 26px; max-width: 230px; display: block; margin-left: auto; }
        .kop .kode .no { font-size: 11px; margin-top: 1px; }
        .kop-lanjut { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 2px solid #000000; padding-bottom: 3px; margin-bottom: 4px; font-weight: 400; font-size: 13px; }

        .info { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 6px; margin-bottom: 4px; }
        .info > .kotak { min-width: 0; }
        .kotak { border: 1.5px solid #000000; padding: 3px 7px; }
        .kotak .jdl { font-size: 11px; font-weight: 400; text-transform: uppercase; border-bottom: 1.5px solid #000000; margin-bottom: 2px; padding-bottom: 1px; }
        .baris { display: flex; font-size: 11.5px; line-height: 1.3; }
        .baris .l { width: 118px; flex-shrink: 0; font-weight: 400; }
        .baris .v { font-weight: 400; flex-grow: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .ringkas { border: 1.5px solid #000000; padding: 3px 7px; margin-bottom: 4px; display: grid; grid-template-columns: 2.2fr 0.9fr 1.1fr 1.3fr; gap: 8px; align-items: center; }
        .ringkas .t { font-size: 10px; font-weight: 400; text-transform: uppercase; }
        .ringkas .n { font-size: 13px; font-weight: 400; line-height: 1.2; }
        .ringkas .n.kecil { font-size: 11.5px; line-height: 1.25; }

        table.rincian { width: 100%; border-collapse: collapse; font-size: 12px; }
        table.rincian th { border: 1.5px solid #000000; padding: 2px 6px; font-weight: 400; text-transform: uppercase; font-size: 11px; height: <?= $kepalaTabelMm ?>mm; }
        table.rincian td { border: 1.5px solid #000000; padding: 0 6px; height: <?= $barisMm ?>mm; vertical-align: middle; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 400; }
        table.rincian td.nama { max-width: 190px; }
        table.rincian tr.total td { font-weight: 400; font-size: 12.5px; border-top: 2.5px solid #000000; }

        .catatan { font-size: 10.5px; font-weight: 400; margin-top: 3px; }
        .ttd { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-top: 3px; text-align: center; }
        .ttd .jdl { font-size: 11px; font-weight: 400; text-transform: uppercase; height: 13mm; }
        .ttd .nama { font-size: 11.5px; font-weight: 400; border-top: 1.5px solid #000000; padding-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ttd .ket { font-size: 10px; font-weight: 400; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .kaki { position: absolute; left: 0; right: 0; bottom: <?= $dasarMm - 0.5 ?>mm; }

        @media print {
            body { padding: 0 !important; margin: 0 !important; }
            .print-actions { display: none !important; }
            .sheet { border: none !important; margin: 0 !important; width: 100% !important; page-break-after: always; break-after: page; }
            .sheet:last-child { page-break-after: auto; break-after: auto; }
        }
    </style>
    <?php if ($lembarTinggi): // lembar tinggi (A4/Letter): huruf dan jarak diperbesar agar mengisi lembar dan lebih jelas di dot matrix ?>
    <style>
        .kop .nama { font-size: 22px; }
        .kop .kiri { flex: 1; min-width: 0; }
        .kop .judul { font-size: 15px; text-align: left; padding: 2px 0 0; }
        .kop .judul small { margin-top: 1px; }
        .kop .judul small { font-size: 11.5px; }
        .kop .kode svg { height: 34px; max-width: 200px; }
        .kop .kode .no { font-size: 12px; }
        .kop, .kop-lanjut { padding-bottom: 5px; margin-bottom: 7px; }
        .kop-lanjut { font-size: 14px; }
        .info { gap: 8px; margin-bottom: 7px; }
        .kotak { padding: 5px 10px; }
        .kotak .jdl { font-size: 12.5px; margin-bottom: 4px; padding-bottom: 2px; }
        .baris { font-size: 13px; line-height: 1.45; }
        .baris .l { width: 126px; }
        .baris .v { white-space: normal; overflow: visible; overflow-wrap: anywhere; }
        .ringkas { padding: 6px 10px; margin-bottom: 7px; }
        .ringkas .t { font-size: 11px; }
        .ringkas .n { font-size: 15px; }
        .ringkas .n.kecil { font-size: 13px; line-height: 1.35; }
        table.rincian { font-size: 13.5px; }
        table.rincian th { font-size: 12px; padding: 3px 7px; }
        table.rincian td { font-size: 13.5px; padding: 0 7px; }
        table.rincian tr.total td { font-size: 14px; }
        .catatan { font-size: 11.5px; margin-top: 6px; }
        .ttd { margin-top: 6px; gap: 18px; }
        .ttd .jdl { font-size: 12.5px; height: 21mm; }
        .ttd .nama { font-size: 13px; }
        .ttd .ket { font-size: 11px; }
    </style>
    <?php endif; ?>
    <?php if (!$lembarTinggi): // setengah A4: isi dipadatkan, tanpa huruf tebal (printer dot matrix) ?>
    <style>
        body { font-size: 10.5px; line-height: 1.25; }
        .kop { padding-bottom: 2px; margin-bottom: 3px; border-bottom-width: 1px; }
        .kop .nama { font-size: 14px; }
        .kop .judul { font-size: 11.5px; }
        .kop .judul small { font-size: 9.5px; }
        .kop .kode svg { height: 20px; max-width: 190px; }
        .kop .kode .no { font-size: 10px; }
        .kop-lanjut { font-size: 11px; border-bottom-width: 1px; padding-bottom: 2px; margin-bottom: 3px; }
        .info { gap: 5px; margin-bottom: 3px; }
        .kotak, .ringkas { border-width: 1px; padding: 2px 6px; }
        .kotak .jdl { font-size: 10px; border-bottom-width: 1px; margin-bottom: 1px; padding-bottom: 0; }
        .baris { font-size: 10.5px; line-height: 1.25; }
        .baris .l { width: 96px; }
        .ringkas { margin-bottom: 3px; }
        .ringkas .t { font-size: 9px; }
        .ringkas .n { font-size: 11px; }
        .ringkas .n.kecil { font-size: 10px; line-height: 1.2; }
        table.rincian { font-size: 10.5px; }
        table.rincian th, table.rincian td { border-width: 1px; }
        table.rincian th { font-size: 9.5px; padding: 1px 4px; }
        table.rincian td { font-size: 10.5px; padding: 0 4px; }
        table.rincian td.mono { font-size: 10px; }
        table.rincian tr.total td { font-size: 10.5px; border-top-width: 1.5px; }
        .catatan { font-size: 9.5px; margin-top: 2px; }
        .ttd { margin-top: 2px; gap: 10px; }
        .ttd .jdl { font-size: 10px; height: 25mm; }
        .ttd .nama { font-size: 10.5px; border-top-width: 1px; padding-top: 1px; }
        .ttd .ket { display: none; }
        .dasar { font-size: 8.5px; border-top-width: 1px; }
    </style>
    <?php endif; ?>
    <?php if (!empty($_GET['embed'])): // mode pratinjau (iframe di menu Surat Jalan): tanpa tombol, selebar kertas ?>
    <style media="screen">
        html, body { background: #ffffff !important; }
        body { padding: <?= $mAtas ?>mm <?= $mKanan ?>mm <?= $mBawah ?>mm <?= $mKiri ?>mm !important; margin: 0 !important; width: <?= $lebarMm ?>mm; }
        .print-actions { display: none !important; }
        .sheet { border: none !important; margin: 0 0 10mm !important; width: 100% !important; }
    </style>
    <?php endif; ?>
    <?php if (!$lembarTinggi): ?>
    <style>
        /* Satu jenis huruf untuk seluruh dokumen (printer dot matrix): font monospace tercetak putus-putus,
           jadi ID barcode, angka, dan nomor surat jalan memakai huruf dan ukuran yang sama dengan nama barang */
        .mono, table.rincian td.mono, .baris .v.mono, .kop .kode .no { font-family: Arial, Helvetica, sans-serif !important; letter-spacing: 0; }
        table.rincian td.mono, table.rincian td { font-size: 10.5px !important; }
        .kop .kode .no { font-size: 11px; }
    </style>
    <?php else: ?>
    <style>.mono, table.rincian td.mono, .baris .v.mono, .kop .kode .no { font-family: Arial, Helvetica, sans-serif !important; }</style>
    <?php endif; ?>
</head>
<body>

    <div class="print-actions">
        <a href="index.php?page=surat_jalan&tab=penerimaan" class="btn-action btn-back">← Kembali ke Surat Jalan</a>
        <span class="info">Kertas: <?= $labelKertas ?>, <?= $jumlahLembar ?> lembar<?= $jumlahLembar > 1 ? ' (tabel bersambung)' : '' ?></span>
        <button onclick="window.print()" class="btn-action btn-print">Cetak Dokumen Penerimaan</button>
    </div>

<?php foreach ($lembar as $idx => $lb): $pertama = $idx === 0; $nomor = $lb['awal'] + 1; ?>
    <div class="sheet">
        <?php if ($pertama): ?>
        <div class="kop">
            <div class="kiri">
            <div class="nama">AR_BAROKAH</div>
            <div class="judul">Surat Jalan Penerimaan Barang Masuk<small>Goods Receipt Note · Berita Acara Penerimaan Keranjang<?= $jumlahLembar > 1 ? ' · Lembar 1/' . $jumlahLembar : '' ?></small></div>
            </div>
            <div class="kode">
                <div class="no mono"><?= htmlspecialchars($sjData['no_surat_jalan']) ?></div>
            </div>
        </div>

        <div class="info">
            <div class="kotak">
                <div class="jdl">Pihak Pengirim (Pemasok)</div>
                <div class="baris"><div class="l">Kode Pemasok</div><div class="v mono"><?= htmlspecialchars($sjData['kode_pemasok']) ?></div></div>
                <div class="baris"><div class="l">Nama Pemasok</div><div class="v"><?= htmlspecialchars($sjData['nama_pemasok']) ?></div></div>
                <div class="baris"><div class="l">PIC / Kontak</div><div class="v"><?= htmlspecialchars(($sjData['pic_pemasok'] ?? '-') !== '-' ? $sjData['pic_pemasok'] : 'Sopir / Ekspedisi') ?> (<?= htmlspecialchars($sjData['telepon_pemasok'] ?? '-') ?>)</div></div>
                <div class="baris"><div class="l">Alamat / Asal</div><div class="v"><?= htmlspecialchars($sjData['alamat_pemasok'] ?? '-') ?></div></div>
            </div>
            <div class="kotak">
                <div class="jdl">Dokumen &amp; Penerima Gudang</div>
                <div class="baris"><div class="l">No. Surat Jalan</div><div class="v mono"><?= htmlspecialchars($sjData['no_surat_jalan']) ?></div></div>
                <div class="baris"><div class="l">Tanggal Masuk</div><div class="v"><?= date('d/m/Y H:i', strtotime($sjData['created_at'])) ?> WIB</div></div>
                <div class="baris"><div class="l">Petugas Penerima</div><div class="v"><?= htmlspecialchars($petugas) ?></div></div>
                <div class="baris"><div class="l">Status</div><div class="v">LENGKAP (Sudah Ditimbang)</div></div>
            </div>
        </div>

        <div class="ringkas">
            <div>
                <div class="t"><?= $isMultiItem ? 'Komoditas (' . count($uniqueItems) . ' produk)' : 'Komoditas / Nama Barang' ?></div>
                <?php if ($isMultiItem): ?>
                    <div class="n kecil">
                        <?php foreach (array_slice($uniqueItems, 0, 4) as $ui): ?>
                            <div><?= htmlspecialchars($ui['nama_item']) ?>: <?= $ui['total_pcs'] ?> box, <?= number_format($ui['total_berat'], 2) ?> KG</div>
                        <?php endforeach; ?>
                        <?php if (count($uniqueItems) > 4): ?><div>dan <?= count($uniqueItems) - 4 ?> produk lain</div><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="n"><?= htmlspecialchars($sjData['nama_item']) ?> <span style="font-size: 11px; font-weight: 400;">(<?= htmlspecialchars($sjData['nama_jenis']) ?>)</span></div>
                <?php endif; ?>
            </div>
            <div style="text-align: center;"><div class="t">Total Keranjang</div><div class="n"><?= number_format($sjData['total_pcs']) ?> Box</div></div>
            <div style="text-align: center;"><div class="t">Total Bobot Riil</div><div class="n mono"><?= number_format($sjData['total_berat'], 2) ?> KG</div></div>
            <div style="text-align: right;"><div class="t">Total Nominal</div><div class="n mono"><?= $rp($grandNilai) ?></div></div>
        </div>
        <?php else: ?>
        <div class="kop-lanjut">
            <span>AR_BAROKAH · Surat Jalan Penerimaan (lanjutan)</span>
            <span class="mono"><?= htmlspecialchars($sjData['no_surat_jalan']) ?> · Lembar <?= $idx + 1 ?>/<?= $jumlahLembar ?></span>
        </div>
        <?php endif; ?>

        <table class="rincian">
            <thead>
                <tr>
                    <th style="width: 34px;">No</th>
                    <th style="width: 190px; text-align: left;">ID Barcode Kemasan</th>
                    <th style="text-align: left;">Nama Barang / Produk</th>
                    <th style="width: 92px; text-align: right;">Bobot Riil</th>
                    <th style="width: 88px; text-align: right;">Harga/Kg</th>
                    <th style="width: 104px; text-align: right;">Nominal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$labels && $lb['akhir']): ?>
                    <tr><td colspan="6" style="text-align: center;">Tidak ada rincian kemasan barcode untuk Surat Jalan ini.</td></tr>
                <?php endif; ?>
                <?php foreach ($lb['baris'] as $k => $r): ?>
                    <tr>
                        <td style="text-align: center;"><?= $nomor + $k ?></td>
                        <td class="mono"><?= htmlspecialchars($r['id_label']) ?></td>
                        <td class="nama"><?= htmlspecialchars($r['nama_item'] ?? '-') ?><?= !empty($r['nama_jenis']) ? ' (' . htmlspecialchars($r['nama_jenis']) . ')' : '' ?></td>
                        <td class="mono" style="text-align: right;"><?= number_format((float)($r['berat_netto'] ?? 0), 2) ?> KG</td>
                        <td class="mono" style="text-align: right;"><?= (float)($r['harga_beli_per_kg'] ?? 0) > 0 ? $rp($r['harga_beli_per_kg']) : '-' ?></td>
                        <td class="mono" style="text-align: right;"><?= (float)($r['total_nilai'] ?? 0) > 0 ? $rp($r['total_nilai']) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($lb['akhir']): ?>
                    <?php if ($isMultiItem): foreach ($uniqueItems as $ui): ?>
                    <tr>
                        <td colspan="3" style="text-align: right;">Subtotal <?= htmlspecialchars($ui['nama_item']) ?> (<?= htmlspecialchars($ui['nama_jenis']) ?>) &middot; <?= number_format($ui['total_pcs']) ?> Box</td>
                        <td class="mono" style="text-align: right;"><?= number_format($ui['total_berat'], 2) ?> KG</td>
                        <td class="mono" style="text-align: right;"><?= $ui['total_berat'] > 0 ? $rp($ui['total_nilai'] / $ui['total_berat']) : '-' ?></td>
                        <td class="mono" style="text-align: right;"><?= $rp($ui['total_nilai']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    <tr class="total">
                        <td colspan="3" style="text-align: right; text-transform: uppercase;">Grand Total &middot; <?= number_format($sjData['total_pcs']) ?> Box</td>
                        <td class="mono" style="text-align: right;"><?= number_format($sjData['total_berat'], 2) ?> KG</td>
                        <td></td>
                        <td class="mono" style="text-align: right;"><?= $rp($grandNilai) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($lb['akhir']): ?>
        <div class="kaki">
        <div class="catatan">Diterima di gudang AR_BAROKAH dalam kondisi baik; jumlah dan bobot sudah dicocokkan dengan scanner.</div>
        <div class="ttd">
            <div><div class="jdl">Pihak Pengirim / Sopir</div><div class="nama">( <?= htmlspecialchars($picPemasok) ?> )</div><div class="ket"><?= htmlspecialchars($sjData['nama_pemasok']) ?></div></div>
            <div><div class="jdl">Petugas Penerima Gudang</div><div class="nama">( <?= htmlspecialchars($petugas) ?> )</div><div class="ket">Operator Inbound AR_BAROKAH</div></div>
            <div><div class="jdl">Mengetahui / Supervisor</div><div class="nama">( ........................................ )</div><div class="ket">Kepala Gudang / Logistik</div></div>
        </div>
        </div>
        <?php else: ?>
        <div class="catatan" style="text-align: right;">bersambung ke lembar <?= $idx + 2 ?> ...</div>
        <?php endif; ?>
        <div class="dasar"><span><?= htmlspecialchars($sjData['no_surat_jalan']) ?></span><span>Lembar <?= $idx + 1 ?> dari <?= $jumlahLembar ?></span></div>
    </div>
<?php endforeach; ?>

</body>
</html>
