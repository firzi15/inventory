<?php
/**
 * Surat Jalan Pengeluaran Barang (Delivery Order).
 * Dirancang untuk form bersambung 3 rangkap di printer dot matrix: lebar cetak 8,5 inci (di antara strip berlubang),
 * tinggi satu lembar 5,5 inci (garis sobekan ke garis sobekan). Bila barangnya banyak, tabel dilanjutkan ke lembar berikutnya;
 * total dan tanda tangan ada di lembar terakhir. ?kertas=penuh memakai lembar 11 inci.
 *
 * Dua mode:
 * - form (bawaan): tanpa harga, satu kali cetak tembus semua rangkap.
 * - internal: dengan harga satuan, subtotal, dan total tagihan piutang; hanya Admin / Supervisor / Finance.
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

$mode = strtolower(trim($_GET['mode'] ?? 'form'));
if ($mode === 'admin') $mode = 'internal';
if (!in_array($mode, ['form', 'internal'], true)) $mode = 'form';
$bolehInternal = in_array(strtoupper($_SESSION['user_login']['role'] ?? ''), ['ADMIN', 'SUPERVISOR', 'FINANCE'], true);
$mode = 'internal';
$isAdmin = true; // satu versi: harga dan total selalu tampil

// Hitung netto dan harga
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
    if ($totJual <= 0) $totJual = round($netto * $hrgMitra, 2);
    $it['total_jual_calc'] = $totJual;
    $grandTotalJual += $totJual;
}
unset($it);
if (!empty($trx['total_nilai_jual']) && (float)$trx['total_nilai_jual'] > 0) $grandTotalJual = (float)$trx['total_nilai_jual'];

// Rincian serah terima dirangkum per produk (bukan per dus); bila produknya banyak, tabel bersambung ke lembar berikutnya
$grup = [];
foreach ($items as $it) {
    $j = trim((string)($it['nama_item'] ?? ''));
    if ($j === '') $j = 'Tanpa nama';
    $jj = trim((string)($it['nama_jenis'] ?? ''));
    if ($jj !== '') $j .= ' (' . $jj . ')';
    if (!isset($grup[$j])) $grup[$j] = ['jenis' => $j, 'dus' => 0, 'netto' => 0.0, 'sub' => 0.0];
    $grup[$j]['dus']++;
    $grup[$j]['netto'] += (float)$it['berat_netto'];
    $grup[$j]['sub'] += (float)$it['total_jual_calc'];
}
$grup = array_values($grup);

$penyerah = $trx['nama_penyerah'] ?: ($trx['operator_pemroses'] ?: 'Petugas Gudang');
$penerima = $trx['nama_penerima'] ?: ($trx['pic_mitra'] ?: 'Mitra Bisnis Penerima');
$catatan = trim((string)($trx['keterangan'] ?? ''));
$adaCatatan = $catatan !== '' && $catatan !== '-';

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
$kepalaAwalMm = ($lembarTinggi ? 60.0 : 33.0) + ($adaCatatan ? 4.0 : 0);

$lembar = [];
$i = 0; $n = count($grup); $ke = 0;
while (true) {
    $kepala = $ke === 0 ? $kepalaAwalMm : $kepalaLanjutMm;
    $kapPenuh = max(1, (int)floor(($tinggiSheet - $dasarMm - $kepala - $kepalaTabelMm) / $barisMm));
    $kapAkhir = max(0, (int)floor(($tinggiSheet - $dasarMm - $kepala - $kepalaTabelMm - $kakiMm) / $barisMm));
    $sisa = $n - $i;
    if ($sisa <= $kapAkhir) { $lembar[] = ['baris' => array_slice($grup, $i), 'awal' => $i, 'akhir' => true]; break; }
    $ambil = min($kapPenuh, $sisa);
    $lembar[] = ['baris' => array_slice($grup, $i, $ambil), 'awal' => $i, 'akhir' => false];
    $i += $ambil; $ke++;
}
$jumlahLembar = count($lembar);
$jmlKolom = $isAdmin ? 7 : 7;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan Pengeluaran (<?= strtoupper($mode) ?>) - <?= htmlspecialchars($noSuratJalan) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/apple-touch-icon.png">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <style>
        /* Huruf sistem, hitam pekat, garis tebal: hasil paling jelas di printer dot matrix */
        @page { size: <?= $lebarMm ?>mm <?= $tinggiMm ?>mm; margin: <?= $mAtas ?>mm <?= $mKanan ?>mm <?= $mBawah ?>mm <?= $mKiri ?>mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; background: #ffffff; color: #000000; margin: 0; padding: 20px; font-size: 12px; line-height: 1.3; }
        .mono { font-family: 'Courier New', Courier, monospace; }

        .btn-print-bar { max-width: <?= $lebarSheet ?>mm; margin: 0 auto 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 4px; font-weight: 400; font-size: 12.5px; cursor: pointer; text-decoration: none; font-family: inherit; }
        .btn-primary { background: #000000; color: #ffffff; border: 1px solid #000000; }
        .btn-outline { background: #ffffff; border: 1px solid #000000; color: #000000; }
        .info-kertas { font-size: 12px; color: #444444; }

        .sheet { width: <?= $lebarSheet ?>mm; max-width: 100%; height: <?= round($tinggiSheet, 1) ?>mm; margin: 0 auto 14px; background: #ffffff; border: 1px solid #999999; position: relative; overflow: hidden; }
        .sheet:last-child { margin-bottom: 0; }
        /* Garis dasar di ujung bawah setiap lembar: kepala cetak dot matrix harus berjalan sampai batas bawah kertas,
           sehingga kertas berhenti tepat di garis sobekan, bukan di tengah lembar */
        .dasar { position: absolute; left: 0; right: 0; bottom: 0; border-top: 1.5px dashed #000000; padding-top: 1px; display: flex; justify-content: space-between; font-size: 9.5px; font-weight: 400; line-height: 1.2; }

        .kop { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #000000; padding-bottom: 3px; margin-bottom: 4px; }
        .kop .nama { font-size: 17px; font-weight: 400; line-height: 1.1; }
        .kop .judul { font-size: 14px; font-weight: 400; text-transform: uppercase; line-height: 1.15; text-align: center; }
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

        table.rincian { width: 100%; border-collapse: collapse; font-size: 12px; table-layout: fixed; }
        table.rincian th { border: 1.5px solid #000000; padding: 2px 5px; font-weight: 400; text-transform: uppercase; font-size: 10.5px; height: <?= $kepalaTabelMm ?>mm; }
        table.rincian td { border: 1.5px solid #000000; padding: 0 5px; height: <?= $barisMm ?>mm; vertical-align: middle; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 400; }
        table.rincian tr.total td { font-weight: 400; font-size: 12.5px; border-top: 2.5px solid #000000; }
        .kanan { text-align: right; }
        .tengah { text-align: center; }

        .catatan { font-size: 10.5px; font-weight: 400; margin-top: 3px; }
        .ttd { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-top: 3px; text-align: center; }
        .ttd .jdl { font-size: 11px; font-weight: 400; text-transform: uppercase; height: 13mm; }
        .ttd .nama { font-size: 11.5px; font-weight: 400; border-top: 1.5px solid #000000; padding-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ttd .ket { font-size: 10px; font-weight: 400; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .kaki { position: absolute; left: 0; right: 0; bottom: <?= $dasarMm - 0.5 ?>mm; }

        @media print {
            body { padding: 0 !important; margin: 0 !important; }
            .btn-print-bar { display: none !important; }
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
        table.rincian th { font-size: 11.5px; padding: 3px 7px; }
        table.rincian td { font-size: 12.5px; padding: 0 6px; }
        table.rincian th { padding: 3px 6px; }
        table.rincian tr.total td { font-size: 13px; }
        table.rincian td.mono { font-size: 11.5px; }
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
        .btn-print-bar { display: none !important; }
        .sheet { border: none !important; margin: 0 0 10mm !important; width: 100% !important; }
    </style>
    <?php endif; ?>
</head>
<body>

    <div class="btn-print-bar no-print">
        <a href="index.php?page=surat_jalan" class="btn btn-outline">Kembali ke Surat Jalan</a>


        <span class="info-kertas">Kertas <?= $labelKertas ?>, <?= $jumlahLembar ?> lembar<?= $jumlahLembar > 1 ? ' (tabel bersambung)' : '' ?></span>
        <button type="button" class="btn btn-primary" onclick="window.print()">Cetak Surat Jalan</button>
    </div>

<?php foreach ($lembar as $idx => $lb): $pertama = $idx === 0; $nomor = $lb['awal'] + 1; ?>
    <div class="sheet">
        <?php if ($pertama): ?>
        <div class="kop">
            <div class="kiri">
            <div class="nama">AR_BAROKAH</div>
            <div class="judul">Surat Jalan Pengeluaran Barang<?php if ($jumlahLembar > 1): ?><small>Lembar 1/<?= $jumlahLembar ?></small><?php endif; ?></div>
            </div>
            <div class="kode">
                <div class="no mono"><?= htmlspecialchars($noSuratJalan) ?></div>
            </div>
        </div>

        <div class="info">
            <div class="kotak">
                <div class="jdl">Asal Pengeluaran (AR_BAROKAH)</div>
                <div class="baris"><div class="l">Diserahkan Oleh</div><div class="v"><?= htmlspecialchars($penyerah) ?></div></div>
                <div class="baris"><div class="l">Waktu Keluar</div><div class="v"><?= date('d/m/Y H:i', strtotime($trx['created_at'])) ?> WIB</div></div>
                <div class="baris"><div class="l">Status</div><div class="v"><?= $isAdmin ? 'Beli Putus (Piutang Mitra)' : 'Penyerahan Fisik Barang' ?></div></div>
                <?php if ($adaCatatan): ?><div class="baris"><div class="l">Catatan</div><div class="v"><?= htmlspecialchars($catatan) ?></div></div><?php endif; ?>
            </div>
            <div class="kotak">
                <div class="jdl">Tujuan (Mitra Penerima)</div>
                <div class="baris"><div class="l">Mitra Penerima</div><div class="v">[<?= htmlspecialchars($trx['kode_mitra'] ?? 'MTR') ?>] <?= htmlspecialchars($trx['nama_mitra'] ?? 'Mitra Bisnis') ?></div></div>
                <div class="baris"><div class="l">PIC / Penerima</div><div class="v"><?= htmlspecialchars($trx['nama_penerima'] ?: ($trx['pic_mitra'] ?: 'Customer / Pembeli')) ?></div></div>
                <div class="baris"><div class="l">No. Surat Jalan</div><div class="v mono"><?= htmlspecialchars($noSuratJalan) ?></div></div>
                <div class="baris"><div class="l">No. Transaksi</div><div class="v mono"><?= htmlspecialchars($trx['no_transaksi']) ?></div></div>
            </div>
        </div>
        <?php else: ?>
        <div class="kop-lanjut">
            <span>AR_BAROKAH · Surat Jalan Pengeluaran (lanjutan)</span>
            <span class="mono"><?= htmlspecialchars($noSuratJalan) ?> · Lembar <?= $idx + 1 ?>/<?= $jumlahLembar ?></span>
        </div>
        <?php endif; ?>

        <table class="rincian">
            <colgroup>
                <col style="width: 34px;">
                <col>
                <col style="width: 80px;">
                <col style="width: 110px;">
                <col style="width: 110px;">
                <col style="width: 130px;">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th style="text-align: left;">Nama Barang</th>
                    <th class="kanan">Jumlah</th>
                    <th class="kanan">Netto</th>
                    <th class="kanan">Harga/Kg</th>
                    <th class="kanan">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$grup && $lb['akhir']): ?>
                    <tr><td colspan="6" class="tengah">Tidak ada rincian barang untuk surat jalan ini.</td></tr>
                <?php endif; ?>
                <?php foreach ($lb['baris'] as $k => $g):
                    $hrg = $g['netto'] > 0 ? $g['sub'] / $g['netto'] : 0;
                ?>
                    <tr>
                        <td class="tengah"><?= $nomor + $k ?></td>
                        <td><?= htmlspecialchars($g['jenis']) ?></td>
                        <td class="mono kanan"><?= $g['dus'] ?> dus</td>
                        <td class="mono kanan"><?= Helper::formatKg($g['netto']) ?></td>
                        <td class="mono kanan"><?= $hrg > 0 ? Helper::formatRupiah($hrg) : '-' ?></td>
                        <td class="mono kanan"><?= $g['sub'] > 0 ? Helper::formatRupiah($g['sub']) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($lb['akhir']): ?>
                    <tr class="total">
                        <td colspan="2" class="kanan" style="text-transform: uppercase;">Total</td>
                        <td class="mono kanan"><?= count($items) ?> dus</td>
                        <td class="mono kanan"><?= Helper::formatKg($totalNetto) ?></td>
                        <td></td>
                        <td class="mono kanan"><?= Helper::formatRupiah($grandTotalJual) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($lb['akhir']): ?>
        <div class="kaki">
        <div class="catatan"><?= $isAdmin
            ? 'Dicatat sebagai Piutang Dagang Mitra Penerima. Penerima wajib memeriksa kondisi dus dan bobot timbangan.'
            : 'Barang sudah diverifikasi per dus lewat scan barcode. Mohon periksa keutuhan dus dan bobot saat serah terima.' ?></div>
        <div class="ttd">
            <div><div class="jdl">Diserahkan Oleh (Gudang)</div><div class="nama">( <?= htmlspecialchars($penyerah) ?> )</div><div class="ket">Operator Outbound Gudang</div></div>
            <div><div class="jdl">Pembawa / Sopir Ekspedisi</div><div class="nama">( ........................................ )</div><div class="ket">Driver / Ekspedisi Pengantar</div></div>
            <div><div class="jdl">Diterima Oleh (Mitra Bisnis)</div><div class="nama">( <?= htmlspecialchars($penerima) ?> )</div><div class="ket">Pihak Mitra Bisnis Penerima</div></div>
        </div>
        </div>
        <?php else: ?>
        <div class="catatan" style="text-align: right;">bersambung ke lembar <?= $idx + 2 ?> ...</div>
        <?php endif; ?>
        <div class="dasar"><span><?= htmlspecialchars($noSuratJalan) ?></span><span>Lembar <?= $idx + 1 ?> dari <?= $jumlahLembar ?></span></div>
    </div>
<?php endforeach; ?>

</body>
</html>
