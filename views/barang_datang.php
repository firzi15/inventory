<?php
/**
 * Barang Datang (Incoming Stock): barang gudang yang diterima dari pemasok pada rentang tanggal,
 * dengan detail artikel dan total nilai pembelian. Sumber angka sama dengan Purchase Book.
 */

use App\Core\Helper;
use App\Services\BukuService;

$pageTitle = 'Barang Datang';

$dari   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dari'] ?? '') ? $_GET['dari'] : date('Y-m-01');
$sampai = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sampai'] ?? '') ? $_GET['sampai'] : date('Y-m-d');
$kategoriId = (int)($_GET['kategori'] ?? 0);
$itemId  = (int)($_GET['item'] ?? 0);
$pihakId = (int)($_GET['pihak'] ?? 0);
$status  = in_array($_GET['status'] ?? '', ['BELUM', 'LUNAS'], true) ? $_GET['status'] : '';
$dasar   = in_array($_GET['kelompok'] ?? '', ['pemasok', 'dokumen', 'kategori'], true) ? $_GET['kelompok'] : 'pemasok';
$stok    = in_array(strtoupper($_GET['stok'] ?? ''), ['AKTIF', 'CLOSED'], true) ? strtoupper($_GET['stok']) : '';
// Pengurutan lewat judul kolom (kosong = urutan bawaan: kategori, tanggal, artikel)
$kolomUrut = ['tanggal' => 'tanggal', 'pihak' => 'pihak', 'kode' => 'kode_item', 'artikel' => 'nama_item', 'kategori' => 'kategori', 'sj' => 'sj', 'faktur' => 'faktur',
              'kemasan' => 'kemasan', 'qty' => 'qty', 'harga' => 'harga', 'nilai' => 'nilai', 'bayar' => 'status_bayar', 'stok' => 'status_stok'];
$urut = array_key_exists($_GET['urut'] ?? '', $kolomUrut) ? $_GET['urut'] : '';
$arah = ($_GET['arah'] ?? 'naik') === 'turun' ? 'turun' : 'naik';
$kolomUk = ['kategori', 'kemasan', 'qty', 'nilai'];
$uk = in_array($_GET['uk'] ?? '', $kolomUk, true) ? $_GET['uk'] : 'kategori';
$ak = ($_GET['ak'] ?? 'naik') === 'turun' ? 'turun' : 'naik';

$rows = BukuService::barangDatang(['dari' => $dari, 'sampai' => $sampai, 'kategori_id' => $kategoriId, 'item_id' => $itemId, 'pihak_id' => $pihakId, 'status' => $status, 'stok' => $stok]);
$grup = BukuService::kelompokkan($rows, $dasar);
$totQty = array_sum(array_column($rows, 'qty'));
$totNilai = array_sum(array_column($rows, 'nilai'));
$totKemasan = array_sum(array_column($rows, 'kemasan'));
$jmlSj = count(array_unique(array_column($rows, 'sj')));
// TOTAL per jenis barang (kategori) dan GRAND TOTAL, dihitung dari seluruh data yang terfilter (bukan hanya halaman yang tampil)
$totKat = [];
foreach ($rows as $r0) {
    $totKat[$r0['kategori']] ??= ['qty' => 0.0, 'kemasan' => 0, 'nilai' => 0.0];
    $totKat[$r0['kategori']]['qty'] += $r0['qty'];
    $totKat[$r0['kategori']]['kemasan'] += $r0['kemasan'];
    $totKat[$r0['kategori']]['nilai'] += $r0['nilai'];
}
if ($uk === 'kategori') { $ak === 'turun' ? krsort($totKat, SORT_NATURAL | SORT_FLAG_CASE) : ksort($totKat, SORT_NATURAL | SORT_FLAG_CASE); }
else { uasort($totKat, fn($a, $b) => $ak === 'turun' ? $b[$uk] <=> $a[$uk] : $a[$uk] <=> $b[$uk]); }

// Halaman: baris data diurutkan seperti di tabel (kelompok, kategori, tanggal, artikel), lalu dipotong per halaman
$opsiPer = [25, 50, 100, 200];
$per = in_array((int)($_GET['per'] ?? 0), $opsiPer, true) ? (int)$_GET['per'] : 50;
$total = count($rows);
$halMax = max(1, (int)ceil($total / $per));
$hal = min($halMax, max(1, (int)($_GET['hal'] ?? 1)));
$urutan = [];
foreach ($grup as $namaG => $g) {
    $barisG = $g['rows'];
    if ($urut === '') {
        usort($barisG, fn($x, $y) => [$x['kategori'], $x['tanggal'], $x['nama_item']] <=> [$y['kategori'], $y['tanggal'], $y['nama_item']]);
    } else {
        $kk = $kolomUrut[$urut];
        usort($barisG, function ($x, $y) use ($kk, $arah) {
            $c = (is_int($x[$kk]) || is_float($x[$kk])) ? $x[$kk] <=> $y[$kk] : strcasecmp((string)$x[$kk], (string)$y[$kk]);
            if ($c === 0) $c = [$x['tanggal'], $x['nama_item']] <=> [$y['tanggal'], $y['nama_item']];
            return $arah === 'turun' ? -$c : $c;
        });
    }
    foreach ($barisG as $r0) $urutan[] = [$namaG, $r0];
}
$grupTampil = [];
foreach (array_slice($urutan, ($hal - 1) * $per, $per) as [$namaG, $r0]) $grupTampil[$namaG]['rows'][] = $r0;

$qs = function (array $x) use ($dari, $sampai, $kategoriId, $itemId, $pihakId, $status, $stok, $dasar, $per, $hal, $urut, $arah, $uk, $ak) {
    $q = array_merge(['page' => 'barang_datang', 'dari' => $dari, 'sampai' => $sampai, 'kategori' => $kategoriId ?: null, 'item' => $itemId ?: null,
                      'pihak' => $pihakId ?: null, 'status' => $status ?: null, 'stok' => $stok ?: null, 'urut' => $urut ?: null, 'arah' => $urut ? $arah : null, 'uk' => $uk !== 'kategori' || $ak !== 'naik' ? $uk : null, 'ak' => $uk !== 'kategori' || $ak !== 'naik' ? $ak : null, 'kelompok' => $dasar, 'per' => $per, 'hal' => $hal], $x);
    return http_build_query(array_filter($q, fn($v) => $v !== null && $v !== ''));
};

$kategoriList = BukuService::daftarKategori();
$artikelList = BukuService::daftarArtikel();
$pemasokList = BukuService::daftarPemasok();

$rp = fn($n) => Helper::formatRupiah($n);
$kg = fn($n) => number_format($n, 2, ',', '.') . ' kg';
$tgl = fn($d) => date('d/m/Y', strtotime($d));
$labelStatus = ['BELUM_BAYAR' => ['Belum dibayar', '#b45309', '#fef3c7'], 'SEBAGIAN' => ['Sebagian', '#c2410c', '#ffedd5'], 'LUNAS' => ['Lunas', '#15803d', '#dcfce7']];
$namaKelompok = ['pemasok' => 'Pemasok', 'dokumen' => 'Surat Jalan', 'kategori' => 'Kategori'][$dasar];
$thLink = function (string $pUrut, string $pArah, string $kolom, string $label, string $sekarang, string $arahSekarang, bool $kanan) use ($qs) {
    $baru = ($sekarang === $kolom && $arahSekarang === 'naik') ? 'turun' : 'naik';
    $aktif = $sekarang === $kolom;
    return '<th' . ($kanan ? ' style="text-align: right;"' : '') . '><a class="th-sort ' . ($aktif ? 'aktif' : '') . '" href="index.php?' . htmlspecialchars($qs([$pUrut => $kolom, $pArah => $baru, 'hal' => 1])) . '">' . $label
         . '<span class="panah">' . ($aktif ? ($arahSekarang === 'naik' ? '&#9650;' : '&#9660;') : '&#8645;') . '</span></a></th>';
};
$thU = fn(string $kolom, string $label, bool $kanan = false) => $thLink('urut', 'arah', $kolom, $label, $urut, $arah, $kanan);
$thK = fn(string $kolom, string $label, bool $kanan = false) => $thLink('uk', 'ak', $kolom, $label, $uk, $ak, $kanan);

$eksporNama = 'Barang_Datang_' . $dari . '_' . $sampai;
$eksporJudul = 'Barang Datang ' . $tgl($dari) . ' s/d ' . $tgl($sampai);

require_once __DIR__ . '/layout/header.php';
?>

<div class="content-wrapper">

    <div class="page-header no-print">
        <div>
            <h1 class="page-title">Barang Datang</h1>
            <p class="page-subtitle">Barang yang diterima gudang dari pemasok. Active: masih di gudang. Closed: sudah pindah ke mitra</p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <?php include __DIR__ . '/layout/ekspor.php'; ?>
        </div>
    </div>

    <div class="fp-preset no-print" style="margin-bottom: 12px;">
        <?php foreach (['' => 'Semua', 'AKTIF' => 'Active', 'CLOSED' => 'Closed'] as $kStok => $lStok): ?>
            <a class="fp-chip <?= $stok === $kStok ? 'aktif' : '' ?>" href="index.php?<?= $qs(['stok' => $kStok ?: null, 'hal' => 1]) ?>"><?= $lStok ?></a>
        <?php endforeach; ?>
    </div>

    <form method="GET" class="card no-print" style="padding: 14px 16px; margin-bottom: 18px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="page" value="barang_datang">
        <?php if ($stok): ?><input type="hidden" name="stok" value="<?= $stok ?>"><?php endif; ?>
        <?php if ($urut): ?><input type="hidden" name="urut" value="<?= $urut ?>"><input type="hidden" name="arah" value="<?= $arah ?>"><?php endif; ?>
        <div><label class="form-label">Dari</label><input type="date" name="dari" value="<?= $dari ?>" class="form-control"></div>
        <div><label class="form-label">Sampai</label><input type="date" name="sampai" value="<?= $sampai ?>" class="form-control"></div>
        <div style="min-width: 170px;">
            <label class="form-label">Kategori</label>
            <select name="kategori" class="form-control">
                <option value="0">Semua kategori</option>
                <?php foreach ($kategoriList as $k): ?><option value="<?= $k['id'] ?>" <?= $kategoriId === (int)$k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div style="min-width: 230px;">
            <label class="form-label">Artikel</label>
            <select name="item" class="form-control">
                <option value="0">Semua artikel</option>
                <?php foreach ($artikelList as $a): ?><option value="<?= $a['id'] ?>" <?= $itemId === (int)$a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['kode'] . ' - ' . $a['nama']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div style="min-width: 200px;">
            <label class="form-label">Pemasok</label>
            <select name="pihak" class="form-control">
                <option value="0">Semua pemasok</option>
                <?php foreach ($pemasokList as $p): ?><option value="<?= $p['id'] ?>" <?= $pihakId === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars('[' . $p['kode'] . '] ' . $p['nama']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div style="min-width: 160px;">
            <label class="form-label">Status Bayar</label>
            <select name="status" class="form-control">
                <option value="">Semua</option>
                <option value="BELUM" <?= $status === 'BELUM' ? 'selected' : '' ?>>Belum lunas</option>
                <option value="LUNAS" <?= $status === 'LUNAS' ? 'selected' : '' ?>>Lunas</option>
            </select>
        </div>
        <div style="min-width: 160px;">
            <label class="form-label">Kelompokkan per</label>
            <select name="kelompok" class="form-control">
                <option value="pemasok" <?= $dasar === 'pemasok' ? 'selected' : '' ?>>Pemasok</option>
                <option value="dokumen" <?= $dasar === 'dokumen' ? 'selected' : '' ?>>Surat Jalan</option>
                <option value="kategori" <?= $dasar === 'kategori' ? 'selected' : '' ?>>Kategori</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Tampilkan</button>
    </form>

    <div class="grid grid-cols-4 no-print" style="gap: 16px; margin-bottom: 18px;">
        <div class="kpi-card"><div class="kpi-body"><div class="kpi-label">Surat Jalan</div><div class="kpi-value"><?= $jmlSj ?></div></div></div>
        <div class="kpi-card"><div class="kpi-body"><div class="kpi-label">Kemasan</div><div class="kpi-value"><?= number_format($totKemasan, 0, ',', '.') ?></div></div></div>
        <div class="kpi-card"><div class="kpi-body"><div class="kpi-label">Total Qty Masuk</div><div class="kpi-value"><?= $kg($totQty) ?></div></div></div>
        <div class="kpi-card"><div class="kpi-body"><div class="kpi-label">Total Nilai Pembelian</div><div class="kpi-value"><?= $rp($totNilai) ?></div></div></div>
    </div>

    <?php if ($rows): ?>
    <div class="card" style="margin-bottom: 18px;">
        <div style="padding: 14px 20px; border-bottom: 1px solid var(--border); font-weight: 800;">Total per jenis barang</div>
        <div class="table-responsive" style="max-height: none;">
            <table class="table-custom" data-no-tools>
                <thead>
                    <tr>
                        <?= $thK('kategori', 'Jenis Barang') ?>
                        <?= $thK('kemasan', 'Kemasan', true) ?>
                        <?= $thK('qty', 'Qty Masuk', true) ?>
                        <?= $thK('nilai', 'Nilai', true) ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($totKat as $namaKat => $t): ?>
                        <tr>
                            <td style="font-weight: 600;">TOTAL <?= htmlspecialchars(mb_strtoupper($namaKat)) ?></td>
                            <td style="text-align: right;"><?= number_format($t['kemasan'], 0, ',', '.') ?></td>
                            <td style="text-align: right;"><?= $kg($t['qty']) ?></td>
                            <td style="text-align: right;"><?= $rp($t['nilai']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight: 800; background: var(--bg-muted);">
                        <td>GRAND TOTAL</td>
                        <td style="text-align: right;"><?= number_format($totKemasan, 0, ',', '.') ?></td>
                        <td style="text-align: right;"><?= $kg($totQty) ?></td>
                        <td style="text-align: right;"><?= $rp($totNilai) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="table-responsive" style="max-height: none;">
            <table class="table-custom" data-no-tools>
                <thead>
                    <tr>
                        <th style="width: 44px; text-align: center;">No</th>
                        <?= $thU('tanggal', 'Tanggal') ?>
                        <?= $thU('pihak', 'Pemasok') ?>
                        <?= $thU('kode', 'Kode') ?>
                        <?= $thU('artikel', 'Artikel') ?>
                        <?= $thU('kategori', 'Kategori') ?>
                        <?= $thU('sj', 'No. Surat Jalan') ?>
                        <?= $thU('faktur', 'No. Faktur') ?>
                        <?= $thU('kemasan', 'Kemasan', true) ?>
                        <?= $thU('qty', 'Qty Masuk', true) ?>
                        <?= $thU('harga', 'Harga/kg', true) ?>
                        <?= $thU('nilai', 'Nilai', true) ?>
                        <?= $thU('bayar', 'Status Bayar') ?>
                        <?= $thU('stok', 'Stok') ?>
                        <th class="no-print" style="width: 80px;">Rincian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="15" style="text-align: center; padding: 30px; color: var(--text-muted);">Tidak ada barang datang pada filter ini</td></tr>
                    <?php endif; ?>
                    <?php
                    $no = ($hal - 1) * $per;
                    foreach ($grupTampil as $nama => $g):
                        // dalam satu kelompok, barang diurutkan per kategori agar TOTAL tiap jenis barang berdampingan
                        $baris = $g['rows'];
                        usort($baris, fn($x, $y) => [$x['kategori'], $x['tanggal'], $x['nama_item']] <=> [$y['kategori'], $y['tanggal'], $y['nama_item']]);
                        $perKat = [];
                        foreach ($baris as $r) { $perKat[$r['kategori']][] = $r; }
                    ?>
                        <tr style="background: var(--bg-muted); font-weight: 800;">
                            <td colspan="15"><?= $namaKelompok ?>: <?= htmlspecialchars($nama) ?></td>
                        </tr>
                        <?php foreach ($perKat as $namaKat => $listKat):
                            $kq = array_sum(array_column($listKat, 'qty'));
                            $kk = array_sum(array_column($listKat, 'kemasan'));
                            $kn = array_sum(array_column($listKat, 'nilai'));
                            foreach ($listKat as $r):
                                $sb = $labelStatus[$r['status_bayar']] ?? null; ?>
                            <tr>
                                <td style="text-align: center; color: var(--text-muted);"><?= ++$no ?></td>
                                <td><?= $tgl($r['tanggal']) ?></td>
                                <td><?= htmlspecialchars($r['pihak']) ?></td>
                                <td><?= htmlspecialchars($r['kode_item']) ?></td>
                                <td style="font-weight: 600;"><?= htmlspecialchars($r['nama_item']) ?></td>
                                <td><?= htmlspecialchars($r['kategori']) ?></td>
                                <td><?= htmlspecialchars($r['sj']) ?></td>
                                <td><?= htmlspecialchars($r['faktur']) ?></td>
                                <td style="text-align: right;"><?= $r['kemasan'] ?></td>
                                <td style="text-align: right;"><?= $kg($r['qty']) ?></td>
                                <td style="text-align: right;"><?= $rp($r['harga']) ?></td>
                                <td style="text-align: right; font-weight: 700;"><?= $rp($r['nilai']) ?></td>
                                <td><?= $sb ? '<span class="badge" style="background: ' . $sb[2] . '; color: ' . $sb[1] . '; font-weight: 700;">' . $sb[0] . '</span>' : '-' ?></td>
                                <td>
                                    <?php if ($r['status_stok'] === 'AKTIF'): ?>
                                        <span style="font-weight: 600; color: var(--ok-fg);">Active</span>
                                        <span style="font-size: 12px; color: var(--text-muted);"> &middot; sisa <?= $kg($r['qty_gudang']) ?></span>
                                    <?php else: ?>
                                        <span style="font-weight: 600; color: var(--text-muted);">Closed</span>
                                        <?php if ($r['mitra_tujuan']): ?><span style="font-size: 12px; color: var(--text-muted);"> &middot; ke <?= htmlspecialchars($r['mitra_tujuan']) ?></span><?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="no-print"><button type="button" class="btn btn-outline btn-sm" onclick='rincianBox(<?= json_encode($r['sj']) ?>, <?= (int)$r['item_id'] ?>, <?= json_encode($r['nama_item']) ?>)'>Box</button></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="sj-halaman no-print">
            <div class="sj-halaman-info">Menampilkan <?= $total ? ($hal - 1) * $per + 1 : 0 ?>-<?= min($total, $hal * $per) ?> dari <?= $total ?> baris</div>
            <div class="sj-halaman-nomor">
                <?php
                $tautan = function ($h, $label, $aktif = false, $mati = false) use ($qs) {
                    if ($mati) return '<span class="sj-hal mati">' . $label . '</span>';
                    return '<a class="sj-hal ' . ($aktif ? 'aktif' : '') . '" href="index.php?' . $qs(['hal' => $h]) . '">' . $label . '</a>';
                };
                echo $tautan(1, '&laquo;', false, $hal <= 1) . $tautan($hal - 1, '&lsaquo;', false, $hal <= 1);
                $mulai = max(1, $hal - 2); $akhir = min($halMax, $hal + 2);
                if ($mulai > 1) echo $tautan(1, '1') . ($mulai > 2 ? '<span class="sj-hal mati">&hellip;</span>' : '');
                for ($i = $mulai; $i <= $akhir; $i++) echo $tautan($i, (string)$i, $i === $hal);
                if ($akhir < $halMax) echo ($akhir < $halMax - 1 ? '<span class="sj-hal mati">&hellip;</span>' : '') . $tautan($halMax, (string)$halMax);
                echo $tautan($hal + 1, '&rsaquo;', false, $hal >= $halMax) . $tautan($halMax, '&raquo;', false, $hal >= $halMax);
                ?>
            </div>
            <div class="sj-halaman-per"><label for="bdPer">Per halaman</label>
                <select id="bdPer" class="form-control" style="width: auto; display: inline-block;" onchange="location.href=this.value">
                    <?php foreach ($opsiPer as $o): ?><option value="index.php?<?= $qs(['per' => $o, 'hal' => 1]) ?>" <?= $o === $per ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

</div>

<!-- MODAL: RINCIAN PER BOX -->
<div id="modalBox" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 760px; margin: auto; padding: 24px; border-radius: 12px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <div>
                <h3 id="boxJudul" style="margin: 0; font-size: 16px; font-weight: 800;">Rincian Box</h3>
                <p id="boxSub" style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted);"></p>
            </div>
            <button type="button" onclick="document.getElementById('modalBox').style.display='none'" style="border: none; background: transparent; font-size: 22px; cursor: pointer; color: var(--text-muted);">&times;</button>
        </div>
        <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
            <table class="table-custom" data-no-tools style="font-size: 12.5px;">
                <thead><tr><th>ID Barcode</th><th style="text-align:right;">Bruto</th><th style="text-align:right;">Tara</th><th style="text-align:right;">Netto</th><th style="text-align:right;">Harga/kg</th><th style="text-align:right;">Nilai</th><th>Status</th></tr></thead>
                <tbody id="boxBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
const fmtRp = n => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n));
const fmtKg = n => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n) + ' kg';
const escH = s => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

async function rincianBox(sj, itemId, nama) {
    try {
        const r = await fetch('index.php?action=rincian_box_penerimaan&sj=' + encodeURIComponent(sj) + '&item=' + itemId);
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Gagal memuat');
        let net = 0, nilai = 0;
        document.getElementById('boxBody').innerHTML = j.box.map(b => {
            net += parseFloat(b.berat_netto); nilai += parseFloat(b.nilai);
            return '<tr><td>' + escH(b.id_label) + '</td><td style="text-align:right;">' + fmtKg(parseFloat(b.berat_bruto)) + '</td><td style="text-align:right;">' + fmtKg(parseFloat(b.berat_tara)) +
                   '</td><td style="text-align:right;">' + fmtKg(parseFloat(b.berat_netto)) + '</td><td style="text-align:right;">' + fmtRp(parseFloat(b.harga_beli_per_kg)) +
                   '</td><td style="text-align:right;">' + fmtRp(parseFloat(b.nilai)) + '</td><td>' + escH(b.status) + '</td></tr>';
        }).join('') + '<tr style="font-weight:800;background:var(--bg-muted);"><td colspan="3">Total ' + j.box.length + ' box</td><td style="text-align:right;">' + fmtKg(net) + '</td><td></td><td style="text-align:right;">' + fmtRp(nilai) + '</td><td></td></tr>';
        document.getElementById('boxJudul').textContent = nama;
        document.getElementById('boxSub').textContent = 'Surat Jalan ' + sj;
        document.getElementById('modalBox').style.display = 'flex';
    } catch (e) { Swal.fire({ icon: 'error', title: 'Gagal', text: e.message }); }
}
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
