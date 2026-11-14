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

$rows = BukuService::barangDatang(['dari' => $dari, 'sampai' => $sampai, 'kategori_id' => $kategoriId, 'item_id' => $itemId, 'pihak_id' => $pihakId, 'status' => $status]);
$grup = BukuService::kelompokkan($rows, $dasar);
$totQty = array_sum(array_column($rows, 'qty'));
$totNilai = array_sum(array_column($rows, 'nilai'));
$totKemasan = array_sum(array_column($rows, 'kemasan'));
$jmlSj = count(array_unique(array_column($rows, 'sj')));

$kategoriList = BukuService::daftarKategori();
$artikelList = BukuService::daftarArtikel();
$pemasokList = BukuService::daftarPemasok();

$rp = fn($n) => Helper::formatRupiah($n);
$kg = fn($n) => number_format($n, 2, ',', '.') . ' kg';
$tgl = fn($d) => date('d/m/Y', strtotime($d));
$labelStatus = ['BELUM_BAYAR' => ['Belum dibayar', '#b45309', '#fef3c7'], 'SEBAGIAN' => ['Sebagian', '#c2410c', '#ffedd5'], 'LUNAS' => ['Lunas', '#15803d', '#dcfce7']];
$namaKelompok = ['pemasok' => 'Pemasok', 'dokumen' => 'Surat Jalan', 'kategori' => 'Kategori'][$dasar];

$eksporNama = 'Barang_Datang_' . $dari . '_' . $sampai;
$eksporJudul = 'Barang Datang ' . $tgl($dari) . ' s/d ' . $tgl($sampai);

require_once __DIR__ . '/layout/header.php';
?>

<div class="content-wrapper">

    <div class="page-header no-print">
        <div>
            <h1 class="page-title">Barang Datang</h1>
            <p class="page-subtitle">Barang yang diterima gudang dari pemasok, lengkap dengan detail artikel dan total nilai pembelian</p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <?php include __DIR__ . '/layout/ekspor.php'; ?>
        </div>
    </div>

    <form method="GET" class="card no-print" style="padding: 14px 16px; margin-bottom: 18px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="page" value="barang_datang">
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

    <div class="card">
        <div class="table-responsive">
            <table class="table-custom" data-no-tools>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Pemasok</th>
                        <th>Kode</th>
                        <th>Artikel</th>
                        <th>Kategori</th>
                        <th>No. Surat Jalan</th>
                        <th>No. Faktur</th>
                        <th style="text-align: right;">Kemasan</th>
                        <th style="text-align: right;">Qty Masuk</th>
                        <th style="text-align: right;">Harga/kg</th>
                        <th style="text-align: right;">Nilai</th>
                        <th>Status Bayar</th>
                        <th class="no-print" style="width: 80px;">Rincian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="13" style="text-align: center; padding: 30px; color: var(--text-muted);">Tidak ada barang datang pada filter ini</td></tr>
                    <?php endif; ?>
                    <?php
                    // total per kategori (jenis barang) di seluruh data, untuk blok TOTAL sebelum GRAND TOTAL
                    $totKat = [];
                    foreach ($rows as $r) {
                        $totKat[$r['kategori']] ??= ['qty' => 0.0, 'kemasan' => 0, 'nilai' => 0.0];
                        $totKat[$r['kategori']]['qty'] += $r['qty'];
                        $totKat[$r['kategori']]['kemasan'] += $r['kemasan'];
                        $totKat[$r['kategori']]['nilai'] += $r['nilai'];
                    }
                    ksort($totKat);
                    foreach ($grup as $nama => $g):
                        // dalam satu kelompok, barang diurutkan per kategori agar TOTAL tiap jenis barang berdampingan
                        $baris = $g['rows'];
                        usort($baris, fn($x, $y) => [$x['kategori'], $x['tanggal'], $x['nama_item']] <=> [$y['kategori'], $y['tanggal'], $y['nama_item']]);
                        $perKat = [];
                        foreach ($baris as $r) { $perKat[$r['kategori']][] = $r; }
                    ?>
                        <tr style="background: var(--bg-muted); font-weight: 800;">
                            <td colspan="13"><?= $namaKelompok ?>: <?= htmlspecialchars($nama) ?></td>
                        </tr>
                        <?php foreach ($perKat as $namaKat => $listKat):
                            $kq = array_sum(array_column($listKat, 'qty'));
                            $kk = array_sum(array_column($listKat, 'kemasan'));
                            $kn = array_sum(array_column($listKat, 'nilai'));
                            foreach ($listKat as $r):
                                $sb = $labelStatus[$r['status_bayar']] ?? null; ?>
                            <tr>
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
                                <td class="no-print"><button type="button" class="btn btn-outline btn-sm" onclick='rincianBox(<?= json_encode($r['sj']) ?>, <?= (int)$r['item_id'] ?>, <?= json_encode($r['nama_item']) ?>)'>Box</button></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr style="font-weight: 700; background: rgba(79, 70, 229, 0.06);">
                                <td colspan="7" style="text-align: right;">TOTAL <?= htmlspecialchars(mb_strtoupper($namaKat)) ?></td>
                                <td style="text-align: right;"><?= $kk ?></td>
                                <td style="text-align: right;"><?= $kg($kq) ?></td>
                                <td></td>
                                <td style="text-align: right;"><?= $rp($kn) ?></td>
                                <td colspan="2"></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($perKat) > 1): ?>
                        <tr style="font-weight: 800;">
                            <td colspan="7" style="text-align: right;">SUBTOTAL <?= htmlspecialchars(mb_strtoupper($nama)) ?></td>
                            <td style="text-align: right;"><?= $g['kemasan'] ?></td>
                            <td style="text-align: right;"><?= $kg($g['qty']) ?></td>
                            <td></td>
                            <td style="text-align: right;"><?= $rp($g['nilai']) ?></td>
                            <td colspan="2"></td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($rows): ?>
                        <?php foreach ($totKat as $namaKat => $t): ?>
                        <tr style="font-weight: 700; background: rgba(79, 70, 229, 0.06);">
                            <td colspan="7" style="text-align: right;">TOTAL <?= htmlspecialchars(mb_strtoupper($namaKat)) ?> (semua)</td>
                            <td style="text-align: right;"><?= $t['kemasan'] ?></td>
                            <td style="text-align: right;"><?= $kg($t['qty']) ?></td>
                            <td></td>
                            <td style="text-align: right;"><?= $rp($t['nilai']) ?></td>
                            <td colspan="2"></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="font-weight: 800; background: var(--primary); color: #fff;">
                            <td colspan="7" style="text-align: right;">GRAND TOTAL</td>
                            <td style="text-align: right;"><?= $totKemasan ?></td>
                            <td style="text-align: right;"><?= $kg($totQty) ?></td>
                            <td></td>
                            <td style="text-align: right;"><?= $rp($totNilai) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
