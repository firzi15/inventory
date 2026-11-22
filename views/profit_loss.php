<?php
/**
 * Laba Rugi - seluruh angka dari jurnal (akrual).
 * 4000 Penjualan, 5000 HPP, 6000 Biaya Operasional.
 */

use App\Core\Helper;
use App\Services\CoaService;
use App\Services\JurnalService;
use App\Services\BukuService;

$pageTitle = 'Laba Rugi';

use App\Core\Periode;

$tab = ($_GET['tab'] ?? '') === 'laba_sj' || ($_GET['tab'] ?? '') === 'riwayat_sj' ? 'laba_sj' : 'ringkasan';

// Periode: bawaan bulan ini; bisa hari, minggu, bulan, YTD, atau tanggal bebas
[$dari, $sampai] = Periode::baca($_GET, 'bulan');

$namaBulan = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
              7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
$bulanPendek = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
$tglPanjang = fn(string $d): string => (int)date('j', strtotime($d)) . ' ' . $bulanPendek[(int)date('n', strtotime($d))] . ' ' . date('Y', strtotime($d));
$tglPendek  = fn(string $d): string => (int)date('j', strtotime($d)) . ' ' . $bulanPendek[(int)date('n', strtotime($d))];
$rentangLabel = fn(string $d, string $s): string => $d === $s ? $tglPanjang($d) : $tglPanjang($d) . ' – ' . $tglPanjang($s);
$labelPeriode = $rentangLabel($dari, $sampai);
$jumlahHari = (int)round((strtotime($sampai) - strtotime($dari)) / 86400) + 1;

$hitung = function (array $a): array {
    $jual = (float)$a['4000']; $hpp = (float)$a['5000']; $biaya = (float)$a['6000'];
    $kotor = $jual - $hpp;
    return ['jual' => $jual, 'hpp' => $hpp, 'kotor' => $kotor, 'biaya' => $biaya, 'bersih' => $kotor - $biaya];
};
$pct = fn(float $a, float $b): string => $b != 0.0 ? number_format($a / $b * 100, 1, ',', '.') . '%' : '-';
$rp = fn($n) => Helper::formatRupiah($n);
$warna = fn($n) => $n < 0 ? 'var(--bad-fg)' : 'inherit';
$linkAkun = fn(string $akun, string $d, string $s) => 'index.php?page=coa&akun=' . $akun . '&dari=' . $d . '&sampai=' . $s;

// ---------- Detail Laba (tab laba_sj): filter, pengelompokan, pengurutan
$fpExtra = ''; $fpLinkExtra = [];
if ($tab === 'laba_sj') {
    $dlMitra = (int)($_GET['mitra'] ?? 0);
    $dlJenis = (int)($_GET['jenis'] ?? 0);
    $dlItem = (int)($_GET['item'] ?? 0);
    $dlPemasok = (int)($_GET['pemasok'] ?? 0);
    $dlCari = trim((string)($_GET['cari'] ?? ''));
    $opsiKelompok = ['sj' => 'Surat Jalan', 'rincian' => 'Surat Jalan per Produk', 'jenis' => 'Jenis Barang', 'produk' => 'Produk', 'mitra' => 'Mitra', 'pemasok' => 'Pemasok', 'tanggal' => 'Tanggal'];
    $dlKel = array_key_exists($_GET['kelompok'] ?? '', $opsiKelompok) ? $_GET['kelompok'] : 'sj';
    // kolom pengenal tiap kelompok: [kolom, judul]
    $kolomLabel = [
        'sj'      => [['sj', 'Surat Jalan'], ['tanggal', 'Tanggal'], ['mitra', 'Mitra']],
        'rincian' => [['tanggal', 'Tanggal'], ['sj', 'Surat Jalan'], ['mitra', 'Mitra'], ['produk', 'Produk'], ['jenis', 'Jenis'], ['pemasok', 'Pemasok']],
        'jenis'   => [['jenis', 'Jenis Barang']],
        'produk'  => [['produk', 'Produk'], ['jenis', 'Jenis']],
        'mitra'   => [['mitra', 'Mitra']],
        'pemasok' => [['pemasok', 'Pemasok']],
        'tanggal' => [['tanggal', 'Tanggal']],
    ][$dlKel];
    $kolomAngka = ['kemasan' => 'Kemasan', 'qty' => 'Qty', 'jual' => 'Penjualan', 'hpp' => 'HPP', 'laba' => 'Laba', 'margin' => 'Margin'];
    $kunciUrutSah = array_merge(array_column($kolomLabel, 0), array_keys($kolomAngka));
    $bawaanUrut = in_array($dlKel, ['sj', 'rincian', 'tanggal'], true) ? ['tanggal', 'turun'] : ['laba', 'turun'];
    $dlUrut = in_array($_GET['urut'] ?? '', $kunciUrutSah, true) ? $_GET['urut'] : $bawaanUrut[0];
    $dlArah = isset($_GET['urut']) ? (($_GET['arah'] ?? 'naik') === 'turun' ? 'turun' : 'naik') : $bawaanUrut[1];

    $baris = JurnalService::detailLaba(['dari' => $dari, 'sampai' => $sampai, 'mitra_id' => $dlMitra, 'jenis_id' => $dlJenis,
                                        'item_id' => $dlItem, 'pemasok_id' => $dlPemasok, 'cari' => $dlCari]);
    $kunciGrup = ['sj' => 'trx_id', 'jenis' => 'jenis', 'produk' => 'item_id', 'mitra' => 'mitra_id', 'pemasok' => 'pemasok_id', 'tanggal' => 'tanggal'];
    $hasilDl = []; $dlSjSet = [];
    foreach ($baris as $i => $r) {
        $dlSjSet[$r['trx_id']] = true;
        $k = $dlKel === 'rincian' ? (string)$i : (string)$r[$kunciGrup[$dlKel]];
        if (!isset($hasilDl[$k])) {
            $hasilDl[$k] = ['kemasan' => 0, 'qty' => 0.0, 'jual' => 0.0, 'hpp' => 0.0];
            foreach ($kolomLabel as [$kol]) $hasilDl[$k][$kol] = $r[$kol];
            // kunci baris untuk modal rincian per produk (pms/jenis -1 = kosong)
            $pmsK = $r['pemasok_id'] !== null ? (int)$r['pemasok_id'] : -1;
            $jenisK = $r['jenis_id'] !== null ? (int)$r['jenis_id'] : -1;
            $hasilDl[$k]['_kunci'] = [
                'sj' => ['trx' => (int)$r['trx_id']],
                'rincian' => ['trx' => (int)$r['trx_id'], 'item' => (int)$r['item_id'], 'pms' => $pmsK],
                'jenis' => ['jenis' => $jenisK],
                'produk' => ['item' => (int)$r['item_id']],
                'mitra' => ['mitra' => (int)$r['mitra_id']],
                'pemasok' => ['pms' => $pmsK],
                'tanggal' => ['tgl' => $r['tanggal']],
            ][$dlKel];
            $hasilDl[$k]['_judul'] = implode(' · ', array_map(fn($kl) => $kl[0] === 'tanggal' ? $tglPanjang((string)$r['tanggal']) : (string)$r[$kl[0]], $kolomLabel));
        }
        $hasilDl[$k]['kemasan'] += $r['kemasan']; $hasilDl[$k]['qty'] += $r['qty'];
        $hasilDl[$k]['jual'] += $r['jual']; $hasilDl[$k]['hpp'] += $r['hpp'];
    }
    $dlTotal = ['kemasan' => 0, 'qty' => 0.0, 'jual' => 0.0, 'hpp' => 0.0];
    foreach ($hasilDl as &$g) {
        $g['laba'] = $g['jual'] - $g['hpp'];
        $g['margin'] = $g['jual'] != 0.0 ? $g['laba'] / $g['jual'] * 100 : 0.0;
        foreach (['kemasan', 'qty', 'jual', 'hpp'] as $m) $dlTotal[$m] += $g[$m];
    }
    unset($g);
    $dlTotal['laba'] = $dlTotal['jual'] - $dlTotal['hpp'];
    $dlTotal['margin'] = $dlTotal['jual'] != 0.0 ? $dlTotal['laba'] / $dlTotal['jual'] * 100 : 0.0;
    $hasilDl = array_values($hasilDl);
    usort($hasilDl, function ($a, $b) use ($dlUrut, $dlArah) {
        $x = $a[$dlUrut]; $y = $b[$dlUrut];
        $c = (is_int($x) || is_float($x)) ? $x <=> $y : strcasecmp((string)$x, (string)$y);
        return $dlArah === 'turun' ? -$c : $c;
    });
    $dlAdaSj = in_array($dlKel, ['sj', 'rincian'], true); // baris mewakili satu surat jalan: tampilkan tombol ke halaman Surat Jalan
    $dlPer = 50;
    $dlTotalBaris = count($hasilDl);
    $dlHalMax = max(1, (int)ceil($dlTotalBaris / $dlPer));
    $dlHal = min($dlHalMax, max(1, (int)($_GET['hal'] ?? 1)));
    $dlTampil = array_slice($hasilDl, ($dlHal - 1) * $dlPer, $dlPer);

    // tautan pilihan cepat periode mempertahankan filter lain
    $fpLinkExtra = array_filter(['mitra' => $dlMitra ?: null, 'jenis' => $dlJenis ?: null, 'item' => $dlItem ?: null, 'pemasok' => $dlPemasok ?: null,
                                 'cari' => $dlCari ?: null, 'kelompok' => $dlKel !== 'sj' ? $dlKel : null, 'urut' => $_GET['urut'] ?? null, 'arah' => $_GET['arah'] ?? null],
                                fn($v) => $v !== null && $v !== '');
    $opt = function (array $daftar, int $dipilih, string $semua, string $kodeKey = '') {
        $h = '<option value="0">' . $semua . '</option>';
        foreach ($daftar as $d) {
            $teks = ($kodeKey !== '' && !empty($d[$kodeKey]) ? '[' . $d[$kodeKey] . '] ' : '') . $d['nama'];
            $h .= '<option value="' . (int)$d['id'] . '"' . ((int)$d['id'] === $dipilih ? ' selected' : '') . '>' . htmlspecialchars($teks) . '</option>';
        }
        return $h;
    };
    ob_start(); ?>
        <div style="flex: 1; min-width: 200px;">
            <label class="form-label">Cari surat jalan</label>
            <input type="text" name="cari" value="<?= htmlspecialchars($dlCari) ?>" class="form-control" placeholder="No. surat jalan atau transaksi">
        </div>
        <div><label class="form-label">Mitra</label><select name="mitra" class="form-control"><?= $opt(BukuService::daftarMitra(), $dlMitra, 'Semua mitra', 'kode') ?></select></div>
        <div><label class="form-label">Jenis Barang</label><select name="jenis" class="form-control"><?= $opt(BukuService::daftarKategori(), $dlJenis, 'Semua jenis') ?></select></div>
        <div><label class="form-label">Produk</label><select name="item" class="form-control"><?= $opt(BukuService::daftarArtikel(), $dlItem, 'Semua produk', 'kode') ?></select></div>
        <div><label class="form-label">Pemasok</label><select name="pemasok" class="form-control"><?= $opt(BukuService::daftarPemasok(), $dlPemasok, 'Semua pemasok', 'kode') ?></select></div>
        <div>
            <label class="form-label">Kelompokkan per</label>
            <select name="kelompok" class="form-control">
                <?php foreach ($opsiKelompok as $kk => $ll): ?><option value="<?= $kk ?>" <?= $dlKel === $kk ? 'selected' : '' ?>><?= $ll ?></option><?php endforeach; ?>
            </select>
        </div>
    <?php $fpExtra = ob_get_clean();
}

require_once __DIR__ . '/layout/header.php';
?>

<div class="content-wrapper">

    <div class="page-header no-print">
        <div>
            <h1 class="page-title"><?= $tab === 'laba_sj' ? 'Detail Laba' : 'Laba Rugi' ?></h1>
            <p class="page-subtitle"><?= $tab === 'laba_sj' ? 'Laba tiap pengeluaran barang, bisa dipecah per surat jalan, jenis, produk, mitra, atau pemasok' : 'Dihitung dari jurnal: penjualan diakui saat barang keluar, HPP memakai harga beli yang sudah dibekukan' ?></p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
        <?php $eksporNama = ($tab === 'laba_sj' ? 'Detail_Laba' : 'Laba_Rugi') . '_' . $dari . '_' . $sampai; $eksporJudul = ($tab === 'laba_sj' ? 'Detail Laba ' : 'Laba Rugi ') . $labelPeriode; include __DIR__ . '/layout/ekspor.php'; ?>
        </div>
    </div>

    <?php
    $fpDari = $dari; $fpSampai = $sampai; $fpHidden = ['page' => 'profit_loss', 'tab' => $tab]; $fpSemua = false;
    include __DIR__ . '/layout/filter_periode.php';
    ?>

    <?php if ($tab === 'ringkasan'):
        // Kolom rincian: per hari (<= 31 hari), per minggu (<= 93 hari), selain itu per bulan
        $granul = $jumlahHari <= 31 ? 'hari' : ($jumlahHari <= 93 ? 'minggu' : 'bulan');
        $kolom = []; // [label, dari, sampai]
        if ($granul === 'hari') {
            for ($t = strtotime($dari); $t <= strtotime($sampai); $t = strtotime('+1 day', $t)) {
                $d = date('Y-m-d', $t);
                $kolom[] = [$tglPendek($d), $d, $d];
            }
        } elseif ($granul === 'minggu') {
            $awal = $dari;
            while ($awal <= $sampai) {
                $akhirMinggu = date('Y-m-d', strtotime('sunday this week', strtotime($awal)));
                $akhir = min($akhirMinggu, $sampai);
                $kolom[] = [$tglPendek($awal) . ($awal === $akhir ? '' : '–' . $tglPendek($akhir)), $awal, $akhir];
                $awal = date('Y-m-d', strtotime($akhir . ' +1 day'));
            }
        } else {
            $awal = $dari;
            while ($awal <= $sampai) {
                $akhir = min(date('Y-m-t', strtotime($awal)), $sampai);
                $kolom[] = [$bulanPendek[(int)date('n', strtotime($awal))] . ' ' . date('Y', strtotime($awal)), $awal, $akhir];
                $awal = date('Y-m-d', strtotime($akhir . ' +1 day'));
            }
        }
        $banyakKolom = count($kolom);

        $akunHarian = JurnalService::labaRugiAkunHarian($dari, $sampai);
        $coaAkun = CoaService::accounts();
        $tipeGrup = ['REVENUE' => '4000', 'COGS' => '5000', 'EXPENSE' => '6000'];
        // nilai per akun per kolom
        $nilaiAkun = []; $hasilKolom = array_fill(0, $banyakKolom, ['4000' => 0.0, '5000' => 0.0, '6000' => 0.0]);
        foreach ($akunHarian as $kodeAk => $perTgl) {
            if (!isset($coaAkun[$kodeAk], $tipeGrup[$coaAkun[$kodeAk][6]])) continue;
            $g = $tipeGrup[$coaAkun[$kodeAk][6]];
            foreach ($perTgl as $tgl => $v) {
                foreach ($kolom as $i => [, $kd, $ks]) {
                    if ($tgl >= $kd && $tgl <= $ks) {
                        $nilaiAkun[$kodeAk][$i] = ($nilaiAkun[$kodeAk][$i] ?? 0.0) + $v;
                        $hasilKolom[$i][$g] += $v;
                        break;
                    }
                }
            }
        }
        $hasilBulan = array_map($hitung, $hasilKolom);
        $hasilYtd = $hitung(JurnalService::labaRugiRentang($dari, $sampai));

        // Pemeriksaan terhadap COA (seluruh periode)
        $semua = JurnalService::labaRugiRentang('1900-01-01', '2999-12-31');
        $hs = $hitung($semua);
        $saldoCoa = CoaService::balances();
        $grupCoa = ['REVENUE' => 0.0, 'COGS' => 0.0, 'EXPENSE' => 0.0];
        foreach ($coaAkun as $kodeC => $c) {
            if (!isset($grupCoa[$c[6]])) continue;
            $grupCoa[$c[6]] += ($c[2] === CoaService::TIPE[$c[6]][2] ? 1 : -1) * $saldoCoa[$kodeC];
        }
        $labaCoa = $grupCoa['REVENUE'] - $grupCoa['COGS'] - $grupCoa['EXPENSE'];
        $cocok = abs($labaCoa - $hs['bersih']) < 1;

        $detailMitra  = JurnalService::detailPerMitra($dari, $sampai);
        $detailVendor = JurnalService::detailPerVendor($dari, $sampai);
        $labelRincian = ['hari' => 'per hari', 'minggu' => 'per minggu', 'bulan' => 'per bulan'][$granul];
    ?>

    <div class="card" style="margin-bottom: 20px;">
        <div style="padding: 14px 20px; border-bottom: 1px solid var(--border); font-weight: 800;">Laba Rugi <?= $labelPeriode ?><?= $banyakKolom > 1 ? ' &middot; rincian ' . $labelRincian : '' ?></div>
        <div class="table-responsive">
            <table class="table-custom" data-no-tools style="min-width: <?= 230 + ($banyakKolom > 1 ? $banyakKolom * 110 : 0) + 130 ?>px;">
                <thead>
                    <tr>
                        <th style="min-width: 190px;">Keterangan</th>
                        <?php if ($banyakKolom > 1): foreach ($kolom as [$lbl]): ?>
                            <th style="text-align: right;"><?= htmlspecialchars($lbl) ?></th>
                        <?php endforeach; endif; ?>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $baris = [
                        ['Penjualan', 'jual', '4000', false],
                        ['HPP', 'hpp', '5000', false],
                        ['Laba Kotor', 'kotor', null, true],
                        ['Biaya Operasional', 'biaya', '6000', false],
                        ['Laba Bersih', 'bersih', null, true],
                    ];
                    foreach ($baris as [$label, $key, $grup, $tebal]): ?>
                        <tr style="<?= $tebal ? 'background: var(--bg-muted);' : '' ?> font-weight: 800;">
                            <td><?= $label ?></td>
                            <?php if ($banyakKolom > 1): foreach ($hasilBulan as $h): ?>
                                <td style="text-align: right; color: <?= $warna($h[$key]) ?>;"><?= $h[$key] != 0 ? $rp($h[$key]) : '-' ?></td>
                            <?php endforeach; endif; ?>
                            <td style="text-align: right; color: <?= $warna($hasilYtd[$key]) ?>;"><?= $hasilYtd[$key] != 0 ? $rp($hasilYtd[$key]) : '-' ?></td>
                        </tr>
                        <?php if ($grup):
                            foreach ($nilaiAkun as $kodeAk => $perKolom):
                                if ($tipeGrup[$coaAkun[$kodeAk][6]] !== $grup) continue; ?>
                            <tr style="font-size: 12.5px;">
                                <td style="padding-left: 28px; font-weight: 500;"><?= $kodeAk ?> &nbsp;<?= htmlspecialchars($coaAkun[$kodeAk][0]) ?></td>
                                <?php if ($banyakKolom > 1): foreach ($kolom as $i => [, $kd, $ks]):
                                    $v = $perKolom[$i] ?? 0.0; ?>
                                    <td style="text-align: right; color: <?= $warna($v) ?>;">
                                        <?php if ($v != 0): ?><a href="<?= $linkAkun($kodeAk, $kd, $ks) ?>" style="color: inherit;"><?= $rp($v) ?></a><?php else: ?>-<?php endif; ?>
                                    </td>
                                <?php endforeach; endif;
                                $tot = array_sum($perKolom); ?>
                                <td style="text-align: right; font-weight: 700; color: <?= $warna($tot) ?>;">
                                    <?php if ($tot != 0): ?><a href="<?= $linkAkun($kodeAk, $dari, $sampai) ?>" style="color: inherit;"><?= $rp($tot) ?></a><?php else: ?>-<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        <?php if ($key === 'kotor' || $key === 'bersih'): ?>
                            <tr style="font-size: 12px; color: var(--text-muted);">
                                <td style="padding-left: 28px;">Margin <?= $key === 'kotor' ? 'kotor' : 'bersih' ?></td>
                                <?php if ($banyakKolom > 1): foreach ($hasilBulan as $h): ?>
                                    <td style="text-align: right;"><?= $pct($h[$key], $h['jual']) ?></td>
                                <?php endforeach; endif; ?>
                                <td style="text-align: right;"><?= $pct($hasilYtd[$key], $hasilYtd['jual']) ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding: 12px 20px; border-top: 1px solid var(--border); font-size: 12.5px; display: flex; gap: 18px; flex-wrap: wrap; align-items: center;">
            <span style="font-weight: 700; color: <?= $cocok ? 'var(--ok-fg)' : 'var(--bad-fg)' ?>;">
                <?= $cocok ? 'Cocok dengan COA' : 'Tidak cocok dengan COA' ?>
            </span>
            <span style="color: var(--text-muted);">
                Laba bersih seluruh periode <?= $rp($hs['bersih']) ?> = saldo COA akun Pendapatan &minus; HPP &minus; Beban (<?= $rp($labaCoa) ?>)
            </span>
        </div>
    </div>

    <?php
        // Pembanding: periode sebelumnya dengan panjang yang sama
        $laluSampai = date('Y-m-d', strtotime($dari . ' -1 day'));
        $laluDari = date('Y-m-d', strtotime($laluSampai . ' -' . ($jumlahHari - 1) . ' days'));
        $ini = $hasilYtd;
        $lalu = $hitung(JurnalService::labaRugiRentang($laluDari, $laluSampai));
        $banding = [['Penjualan', 'jual'], ['HPP', 'hpp'], ['Laba Kotor', 'kotor'], ['Biaya Operasional', 'biaya'], ['Laba Bersih', 'bersih']];
    ?>
    <div class="card" style="margin-bottom: 20px;">
        <div style="padding: 14px 20px; border-bottom: 1px solid var(--border); font-weight: 800;">
            Dibanding periode sebelumnya (<?= $rentangLabel($laluDari, $laluSampai) ?>)
        </div>
        <div class="table-responsive">
            <table class="table-custom" data-no-tools>
                <thead>
                    <tr>
                        <th>Keterangan</th>
                        <th style="text-align: right;">Periode ini</th>
                        <th style="text-align: right;">Periode sebelumnya</th>
                        <th style="text-align: right;">Selisih</th>
                        <th style="text-align: right;">Perubahan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($banding as [$label, $key]):
                        $sel = $ini[$key] - $lalu[$key];
                        $tebal = in_array($key, ['kotor', 'bersih'], true); ?>
                        <tr style="<?= $tebal ? 'font-weight: 800; background: var(--bg-muted);' : '' ?>">
                            <td><?= $label ?></td>
                            <td style="text-align: right;"><?= $rp($ini[$key]) ?></td>
                            <td style="text-align: right;"><?= $rp($lalu[$key]) ?></td>
                            <td style="text-align: right; color: <?= $warna($sel) ?>;"><?= $rp($sel) ?></td>
                            <td style="text-align: right;"><?= $lalu[$key] != 0 ? number_format($sel / abs($lalu[$key]) * 100, 1, ',', '.') . '%' : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 20px;">
        <div class="card">
            <div style="padding: 14px 20px; border-bottom: 1px solid var(--border); font-weight: 800;">Penjualan dan HPP per mitra &middot; <?= $labelPeriode ?></div>
            <div class="table-responsive">
                <table class="table-custom" data-no-tools>
                    <thead>
                        <tr>
                            <th>Mitra</th>
                            <th style="text-align: right;">Penjualan</th>
                            <th style="text-align: right;">HPP</th>
                            <th style="text-align: right;">Laba Kotor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$detailMitra): ?>
                            <tr><td colspan="4" style="text-align: center; padding: 24px; color: var(--text-muted);">Belum ada penjualan pada periode ini</td></tr>
                        <?php endif;
                        $tj = $th = 0.0;
                        foreach ($detailMitra as $nama => $v):
                            $tj += $v['4000']; $th += $v['5000']; $lk = $v['4000'] - $v['5000']; ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($nama) ?></td>
                                <td style="text-align: right;"><?= $rp($v['4000']) ?></td>
                                <td style="text-align: right;"><?= $rp($v['5000']) ?></td>
                                <td style="text-align: right; color: <?= $warna($lk) ?>;"><?= $rp($lk) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($detailMitra): ?>
                            <tr style="font-weight: 800; background: var(--bg-muted);">
                                <td>Total</td>
                                <td style="text-align: right;"><?= $rp($tj) ?></td>
                                <td style="text-align: right;"><?= $rp($th) ?></td>
                                <td style="text-align: right;"><?= $rp($tj - $th) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div style="padding: 14px 20px; border-bottom: 1px solid var(--border); font-weight: 800;">Biaya operasional per vendor &middot; <?= $labelPeriode ?></div>
            <div class="table-responsive">
                <table class="table-custom" data-no-tools>
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th style="text-align: right;">Biaya</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$detailVendor): ?>
                            <tr><td colspan="2" style="text-align: center; padding: 24px; color: var(--text-muted);">Belum ada biaya operasional pada periode ini</td></tr>
                        <?php endif; ?>
                        <?php foreach ($detailVendor as $nama => $v): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($nama) ?></td>
                                <td style="text-align: right;"><?= $rp($v) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($detailVendor): ?>
                            <tr style="font-weight: 800; background: var(--bg-muted);">
                                <td>Total</td>
                                <td style="text-align: right;"><?= $rp(array_sum($detailVendor)) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php else:
        // judul kolom yang bisa diklik; tautan mempertahankan semua filter
        $thDl = function (string $kolom, string $label, bool $kanan = false) use ($dlUrut, $dlArah) {
            $baru = ($dlUrut === $kolom && $dlArah === 'naik') ? 'turun' : 'naik';
            $q = array_merge($_GET, ['urut' => $kolom, 'arah' => $baru, 'hal' => 1]);
            $aktif = $dlUrut === $kolom;
            return '<th' . ($kanan ? ' style="text-align: right;"' : '') . '><a class="th-sort ' . ($aktif ? 'aktif' : '') . '" href="index.php?' . htmlspecialchars(http_build_query($q)) . '">' . $label
                 . '<span class="panah">' . ($aktif ? ($dlArah === 'naik' ? '&#9650;' : '&#9660;') : '&#8645;') . '</span></a></th>';
        };
    ?>

    <div class="grid grid-cols-4 no-print" style="gap: 16px; margin-bottom: 18px;">
        <div class="kpi-card"><div class="kpi-body"><div class="kpi-label">Penjualan</div><div class="kpi-value"><?= $rp($dlTotal['jual']) ?></div><div class="kpi-sub"><?= count($dlSjSet) ?> surat jalan &middot; <?= number_format($dlTotal['kemasan'], 0, ',', '.') ?> kemasan</div></div></div>
        <div class="kpi-card"><div class="kpi-body"><div class="kpi-label">HPP</div><div class="kpi-value"><?= $rp($dlTotal['hpp']) ?></div><div class="kpi-sub"><?= number_format($dlTotal['qty'], 2, ',', '.') ?> kg</div></div></div>
        <div class="kpi-card"><div class="kpi-body"><div class="kpi-label">Laba</div><div class="kpi-value" style="color: <?= $warna($dlTotal['laba']) ?>;"><?= $rp($dlTotal['laba']) ?></div></div></div>
        <div class="kpi-card"><div class="kpi-body"><div class="kpi-label">Margin</div><div class="kpi-value"><?= number_format($dlTotal['margin'], 1, ',', '.') ?>%</div></div></div>
    </div>

    <div class="card">
        <div class="table-responsive" style="max-height: none;">
            <table class="table-custom" data-no-tools>
                <thead>
                    <tr>
                        <?php foreach ($kolomLabel as [$kol, $jdl]) echo $thDl($kol, $jdl); ?>
                        <?php foreach ($kolomAngka as $kol => $jdl) echo $thDl($kol, $jdl, true); ?>
                        <?php if ($dlAdaSj): ?><th class="no-print" style="width: 70px; text-align: center;">SJ</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$dlTampil): ?>
                        <tr><td colspan="<?= count($kolomLabel) + count($kolomAngka) + ($dlAdaSj ? 1 : 0) ?>" style="text-align: center; padding: 28px; color: var(--text-muted);">Tidak ada pengeluaran barang pada filter ini</td></tr>
                    <?php endif; ?>
                    <?php foreach ($dlTampil as $g): ?>
                        <tr class="dl-baris" style="cursor: pointer;" title="Klik untuk melihat rincian per produk"
                            onclick='rincianLaba(<?= htmlspecialchars(json_encode($g['_kunci']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($g['_judul']), ENT_QUOTES) ?>)'>
                            <?php foreach ($kolomLabel as $iK => [$kol]):
                                $v = $g[$kol]; ?>
                                <td style="<?= $iK === 0 ? 'font-weight: 600;' : '' ?>"><?= $kol === 'tanggal' ? $tglPanjang((string)$v) : htmlspecialchars((string)$v) ?></td>
                            <?php endforeach; ?>
                            <td style="text-align: right;"><?= number_format($g['kemasan'], 0, ',', '.') ?></td>
                            <td style="text-align: right;"><?= number_format($g['qty'], 2, ',', '.') ?> kg</td>
                            <td style="text-align: right;"><?= $rp($g['jual']) ?></td>
                            <td style="text-align: right;"><?= $rp($g['hpp']) ?></td>
                            <td style="text-align: right; font-weight: 700; color: <?= $warna($g['laba']) ?>;"><?= $rp($g['laba']) ?></td>
                            <td style="text-align: right;"><?= number_format($g['margin'], 1, ',', '.') ?>%</td>
                            <?php if ($dlAdaSj):
                                $urlSj = 'index.php?' . http_build_query(['page' => 'surat_jalan', 'tab' => 'pengeluaran', 'dari' => $g['tanggal'], 'sampai' => $g['tanggal'], 'cari' => $g['sj']]); ?>
                                <td class="no-print" style="text-align: center;">
                                    <a href="<?= htmlspecialchars($urlSj) ?>" class="btn btn-outline btn-sm" onclick="event.stopPropagation()"
                                       title="Buka surat jalan <?= htmlspecialchars($g['sj']) ?>" style="padding: 3px 8px; display: inline-flex; align-items: center; gap: 4px;">
                                        <i data-lucide="external-link" style="width: 13px; height: 13px;"></i><span style="font-size: 11px;">Buka</span>
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        require_once __DIR__ . '/layout/pager_riwayat.php';
        $dlUrlDasar = 'index.php?' . http_build_query(array_diff_key($_GET, ['hal' => 1]));
        renderPagerRiwayat($dlHal, $dlHalMax, 'Menampilkan ' . ($dlTotalBaris ? ($dlHal - 1) * $dlPer + 1 : 0) . '-' . min($dlTotalBaris, $dlHal * $dlPer) . ' dari ' . number_format($dlTotalBaris) . ' baris', $dlUrlDasar, 'hal');
        ?>
    </div>

    <?php endif; ?>

</div>

<?php if ($tab === 'laba_sj'): ?>
<style>.dl-baris:hover td { background: var(--bg-muted); }</style>
<!-- MODAL: RINCIAN PER PRODUK (box di balik satu baris Detail Laba) -->
<div id="modalRincianLaba" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 1100px; margin: auto; padding: 24px; border-radius: 12px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px;">
            <div>
                <h3 id="rlJudul" style="margin: 0; font-size: 16px; font-weight: 800;">Rincian per Produk</h3>
                <p id="rlSub" style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted);"></p>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="eksporRincianLaba()" style="display: inline-flex; align-items: center; gap: 5px; color: var(--ok-fg); border-color: var(--border-hover); background: var(--ok-bg);" title="Ekspor rincian ke Excel">
                    <i data-lucide="file-spreadsheet" style="width: 14px; height: 14px;"></i><span>Excel</span>
                </button>
                <button type="button" onclick="document.getElementById('modalRincianLaba').style.display='none'" style="border: none; background: transparent; font-size: 22px; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>
        </div>
        <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
            <table class="table-custom" data-no-tools style="font-size: 12.5px;">
                <thead><tr>
                    <th>Barcode</th><th>Surat Jalan Keluar</th><th>Pemasok &middot; SJ Masuk</th>
                    <th style="text-align:right;">Netto</th><th style="text-align:right;">Harga Beli/kg</th><th style="text-align:right;">Harga Jual/kg</th>
                    <th style="text-align:right;">HPP</th><th style="text-align:right;">Penjualan</th><th style="text-align:right;">Laba</th>
                </tr></thead>
                <tbody id="rlBody"></tbody>
            </table>
        </div>
        <!-- Tabel datar per box khusus ekspor Excel (tidak ditampilkan) -->
        <table id="rlEkspor" data-no-tools style="display: none;">
            <thead><tr>
                <th>Produk</th><th>Jenis</th><th>Barcode</th><th>Surat Jalan Keluar</th><th>Tanggal</th><th>Mitra</th><th>Pemasok</th><th>SJ Masuk</th>
                <th>Netto (kg)</th><th>Harga Beli/kg</th><th>Harga Jual/kg</th><th>HPP</th><th>Penjualan</th><th>Laba</th>
            </tr></thead>
            <tbody></tbody>
            <tfoot></tfoot>
        </table>
    </div>
</div>
<script>
const rlFilter = <?= json_encode(['dari' => $dari, 'sampai' => $sampai, 'mitra' => $dlMitra, 'jenis' => $dlJenis, 'item' => $dlItem, 'pemasok' => $dlPemasok, 'cari' => $dlCari]) ?>;
const rlRp = n => (n < 0 ? '-' : '') + 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.abs(Math.round(n)));
const rlKg = n => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n) + ' kg';
const rlEsc = s => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const rlTgl = s => { const d = String(s).slice(0, 10).split('-'); return d[2] + '/' + d[1] + '/' + d[0]; };
const rlWarna = n => n < 0 ? 'var(--bad-fg)' : 'inherit';
const rlAngka = n => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
let rlNamaEkspor = 'Rincian_Laba';
const rlMargin = (laba, jual) => jual ? (laba / jual * 100).toFixed(1).replace('.', ',') + '%' : '-';

async function rincianLaba(kunci, judul) {
    try {
        const q = new URLSearchParams({ action: 'detail_laba_box' });
        Object.entries(rlFilter).forEach(([k, v]) => { if (v !== '' && v !== 0 && v !== null) q.set(k, v); });
        Object.entries(kunci).forEach(([k, v]) => q.set('r_' + k, v));
        const r = await fetch('index.php?' + q.toString());
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Gagal memuat rincian');
        const per = {};
        j.box.forEach(b => { (per[b.produk] = per[b.produk] || { jenis: b.jenis, box: [] }).box.push(b); });
        let tKg = 0, tBeli = 0, tJual = 0, tBox = 0, html = '';
        Object.keys(per).forEach(nama => {
            const g = per[nama];
            let kg = 0, beli = 0, jual = 0;
            g.box.forEach(b => { kg += b.berat_netto; beli += b.total_beli; jual += b.total_jual; });
            tKg += kg; tBeli += beli; tJual += jual; tBox += g.box.length;
            html += '<tr style="background:var(--bg-muted);font-weight:800;"><td colspan="3">' + rlEsc(nama) + ' <span style="font-weight:600;color:var(--text-muted);">&middot; ' + rlEsc(g.jenis) + ' &middot; ' + g.box.length + ' box &middot; margin ' + rlMargin(jual - beli, jual) + '</span></td>' +
                    '<td style="text-align:right;">' + rlKg(kg) + '</td><td></td><td></td><td style="text-align:right;">' + rlRp(beli) + '</td><td style="text-align:right;">' + rlRp(jual) + '</td>' +
                    '<td style="text-align:right;color:' + rlWarna(jual - beli) + ';">' + rlRp(jual - beli) + '</td></tr>';
            html += g.box.map(b => '<tr><td style="font-family:monospace;">' + rlEsc(b.id_label) + '</td>' +
                    '<td>' + rlEsc(b.sj) + '<div style="font-size:11px;color:var(--text-muted);">' + rlTgl(b.tanggal) + ' &middot; ' + rlEsc(b.mitra) + '</div></td>' +
                    '<td>' + rlEsc(b.pemasok) + '<div style="font-size:11px;color:var(--text-muted);">' + rlEsc(b.sj_masuk) + '</div></td>' +
                    '<td style="text-align:right;">' + rlKg(b.berat_netto) + '</td><td style="text-align:right;">' + rlRp(b.harga_pemasok) + '</td><td style="text-align:right;">' + rlRp(b.harga_mitra) + '</td>' +
                    '<td style="text-align:right;">' + rlRp(b.total_beli) + '</td><td style="text-align:right;">' + rlRp(b.total_jual) + '</td>' +
                    '<td style="text-align:right;font-weight:700;color:' + rlWarna(b.profit) + ';">' + rlRp(b.profit) + '</td></tr>').join('');
        });
        if (!j.box.length) html = '<tr><td colspan="9" style="text-align:center;padding:20px;color:var(--text-muted);">Tidak ada box</td></tr>';
        else html += '<tr style="font-weight:800;border-top:2px solid var(--border);"><td colspan="3">TOTAL ' + Object.keys(per).length + ' produk &middot; ' + tBox + ' box</td><td style="text-align:right;">' + rlKg(tKg) + '</td><td></td><td></td>' +
                     '<td style="text-align:right;">' + rlRp(tBeli) + '</td><td style="text-align:right;">' + rlRp(tJual) + '</td><td style="text-align:right;color:' + rlWarna(tJual - tBeli) + ';">' + rlRp(tJual - tBeli) + '</td></tr>';
        document.getElementById('rlBody').innerHTML = html;
        // tabel ekspor: satu baris per box, kolom terpisah
        const td = v => '<td>' + rlEsc(v) + '</td>';
        const ek = document.getElementById('rlEkspor');
        ek.tBodies[0].innerHTML = j.box.map(b => '<tr>' + [b.produk, b.jenis, b.id_label, b.sj, rlTgl(b.tanggal), b.mitra, b.pemasok, b.sj_masuk,
            rlAngka(b.berat_netto), rlRp(b.harga_pemasok), rlRp(b.harga_mitra), rlRp(b.total_beli), rlRp(b.total_jual), rlRp(b.profit)].map(td).join('') + '</tr>').join('');
        ek.tFoot.innerHTML = j.box.length ? '<tr>' + ['TOTAL', '', tBox + ' box', '', '', '', '', '', rlAngka(tKg), '', '', rlRp(tBeli), rlRp(tJual), rlRp(tJual - tBeli)].map(td).join('') + '</tr>' : '';
        rlNamaEkspor = 'Rincian_Laba_' + judul.split(' · ')[0].replace(/[^\w-]+/g, '_');
        document.getElementById('rlJudul').textContent = judul;
        document.getElementById('rlSub').textContent = 'Rincian per produk · laba ' + rlRp(tJual - tBeli) + ' · margin ' + rlMargin(tJual - tBeli, tJual);
        document.getElementById('modalRincianLaba').style.display = 'flex';
    } catch (e) { Swal.fire({ icon: 'error', title: 'Gagal', text: e.message }); }
}

function eksporRincianLaba() {
    window.eksporExcel(rlNamaEkspor, 'rlEkspor');
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
