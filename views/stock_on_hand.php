<?php
/**
 * Stock On Hand: sisa barang di gudang per jenis dan per produk, beserta jumlah masuk dan keluar.
 * Sisa = box masih di gudang; Keluar = box sudah pindah ke mitra; Masuk = semua box yang diterima.
 */

use App\Core\Helper;
use App\Services\BukuService;
use App\Services\StockOnHandService;

$pageTitle = 'Stock On Hand';

$tglValid = fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v) ? $v : '';
$f = [
    'jenis_id'   => (int)($_GET['jenis'] ?? 0),
    'item_id'    => (int)($_GET['item'] ?? 0),
    'pemasok_id' => (int)($_GET['pemasok'] ?? 0),
    'dari'       => $tglValid($_GET['dari'] ?? ''),
    'sampai'     => $tglValid($_GET['sampai'] ?? ''),
    'tampil'     => in_array($_GET['tampil'] ?? '', ['sisa'], true) ? $_GET['tampil'] : '',
];
// Satu tabel; tampilannya dipilih lewat dropdown: per produk (bawaan) atau per jenis
$lihat = ($_GET['lihat'] ?? '') === 'jenis' ? 'jenis' : 'produk';

$produk  = StockOnHandService::perProduk($f);
$jenis   = StockOnHandService::perJenis($produk);
$tot     = StockOnHandService::total($produk);
$jenisList   = BukuService::daftarKategori();
$artikelList = BukuService::daftarArtikel();
$pemasokList = \App\Models\Pemasok::getAll();
$adaFilter = $f['jenis_id'] || $f['item_id'] || $f['pemasok_id'] || $f['dari'] || $f['sampai'] || $f['tampil'] || $lihat === 'jenis';

$kg  = fn($n) => Helper::formatKg($n);
$rp  = fn($n) => Helper::formatRupiah($n);
$box = fn($n) => number_format((int)$n, 0, ',', '.') . ' box';
$persen = $tot['masuk_box'] > 0 ? $tot['keluar_box'] / $tot['masuk_box'] * 100 : 0;
$linkJenis = function (int $id) use ($f) {
    return 'index.php?' . http_build_query(array_filter(['page' => 'stock_on_hand', 'lihat' => 'produk', 'jenis' => $id, 'pemasok' => $f['pemasok_id'] ?: null,
        'dari' => $f['dari'] ?: null, 'sampai' => $f['sampai'] ?: null, 'tampil' => $f['tampil'] ?: null]));
};

require_once __DIR__ . '/layout/header.php';
?>

<style>
    .soh-sisa { background: var(--bg-muted); font-weight: 800; }
    .soh-angka { text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px; white-space: nowrap; }
    .soh-redup { color: var(--text-muted); }
    .soh-grup th { text-align: center; border-bottom: 1px solid var(--border); }
</style>

<div class="content-wrapper">

    <div class="page-header no-print">
        <div>
            <h1 class="page-title">Stock On Hand</h1>
            <p class="page-subtitle">Sisa barang di gudang per jenis dan produk, beserta jumlah masuk dan yang sudah keluar ke mitra</p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <?php $eksporNama = 'Stock_On_Hand_' . date('Ymd'); $eksporJudul = 'Stock On Hand ' . date('d/m/Y'); include __DIR__ . '/layout/ekspor.php'; ?>
        </div>
    </div>

    <!-- KARTU RINGKASAN (ikut filter) -->
    <div class="grid grid-cols-4 no-print" style="gap: 16px; margin-bottom: 22px;">
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Sisa di Gudang</div>
                <div class="kpi-value"><?= $box($tot['sisa_box']) ?></div>
                <div class="kpi-sub"><?= $kg($tot['sisa_kg']) ?> &bull; nilai beli <?= $rp($tot['sisa_nilai']) ?></div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="warehouse" style="width: 18px; height: 18px;"></i></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Masuk</div>
                <div class="kpi-value"><?= $box($tot['masuk_box']) ?></div>
                <div class="kpi-sub"><?= $kg($tot['masuk_kg']) ?> diterima dari pemasok</div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="package-plus" style="width: 18px; height: 18px;"></i></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Keluar ke Mitra</div>
                <div class="kpi-value"><?= $box($tot['keluar_box']) ?></div>
                <div class="kpi-sub"><?= $kg($tot['keluar_kg']) ?> &bull; <?= number_format($persen, 1, ',', '.') ?>% box dari yang masuk</div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="truck" style="width: 18px; height: 18px;"></i></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Produk Masih Ada Sisa</div>
                <div class="kpi-value"><?= number_format($tot['produk_sisa']) ?></div>
                <div class="kpi-sub">dari <?= number_format($tot['produk']) ?> produk pada filter ini</div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="boxes" style="width: 18px; height: 18px;"></i></div>
        </div>
    </div>

    <!-- FILTER -->
    <form method="GET" action="index.php" class="card no-print" style="padding: 14px 16px; margin-bottom: 18px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="page" value="stock_on_hand">
        <div style="min-width: 150px;">
            <label class="form-label">Tampilan</label>
            <select name="lihat" class="form-control" onchange="this.form.submit()">
                <option value="produk" <?= $lihat === 'produk' ? 'selected' : '' ?>>Per Produk</option>
                <option value="jenis" <?= $lihat === 'jenis' ? 'selected' : '' ?>>Per Jenis</option>
            </select>
        </div>
        <div style="min-width: 160px;">
            <label class="form-label">Jenis</label>
            <select name="jenis" class="form-control" onchange="this.form.submit()">
                <option value="0">Semua jenis</option>
                <?php foreach ($jenisList as $j): ?><option value="<?= $j['id'] ?>" <?= $f['jenis_id'] === (int)$j['id'] ? 'selected' : '' ?>><?= htmlspecialchars($j['nama']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div style="min-width: 220px; flex: 1;">
            <label class="form-label">Produk</label>
            <select name="item" class="form-control" onchange="this.form.submit()">
                <option value="0">Semua produk</option>
                <?php foreach ($artikelList as $a): ?><option value="<?= $a['id'] ?>" <?= $f['item_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['nama'] . ' (' . $a['kode'] . ')') ?></option><?php endforeach; ?>
            </select>
        </div>
        <div style="min-width: 170px;">
            <label class="form-label">Tampilkan</label>
            <select name="tampil" class="form-control" onchange="this.form.submit()">
                <option value="" <?= $f['tampil'] === '' ? 'selected' : '' ?>>Semua produk</option>
                <option value="sisa" <?= $f['tampil'] === 'sisa' ? 'selected' : '' ?>>Masih ada sisa</option>
            </select>
        </div>
        <div style="min-width: 170px;">
            <label class="form-label">Pemasok</label>
            <select name="pemasok" class="form-control" onchange="this.form.submit()">
                <option value="0">Semua pemasok</option>
                <?php foreach ($pemasokList as $pm): ?><option value="<?= $pm['id'] ?>" <?= $f['pemasok_id'] === (int)$pm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pm['nama_pemasok']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label class="form-label">Masuk dari</label><input type="date" name="dari" value="<?= $f['dari'] ?>" class="form-control"></div>
        <div><label class="form-label">Sampai</label><input type="date" name="sampai" value="<?= $f['sampai'] ?>" class="form-control"></div>
        <div style="display: flex; gap: 6px;">
            <button type="submit" class="btn btn-primary"><i data-lucide="filter" style="width: 14px; height: 14px;"></i><span>Filter</span></button>
            <?php if ($adaFilter): ?><a href="index.php?page=stock_on_hand" class="btn btn-outline">Reset</a><?php endif; ?>
        </div>
    </form>

    <div class="card" style="padding: 20px;">
    <?php if ($lihat === 'jenis'): ?>
        <!-- TAMPILAN PER JENIS -->
        <div class="table-responsive">
            <table class="table-custom" id="tabelJenis">
                <thead>
                    <tr class="soh-grup">
                        <th rowspan="2" style="text-align: left;">Jenis</th>
                        <th colspan="2" class="soh-sisa">Sisa di Gudang</th>
                        <th colspan="2">Masuk</th>
                        <th colspan="2">Keluar</th>
                        <th rowspan="2" style="text-align: right;">Nilai Sisa</th>
                        <th rowspan="2" style="text-align: right;">Produk Ada Sisa</th>
                    </tr>
                    <tr>
                        <th class="soh-sisa" style="text-align: right;">Box</th><th class="soh-sisa" style="text-align: right;">Kg</th>
                        <th style="text-align: right;">Box</th><th style="text-align: right;">Kg</th>
                        <th style="text-align: right;">Box</th><th style="text-align: right;">Kg</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$jenis): ?>
                        <tr><td colspan="9" style="text-align: center; padding: 28px; color: var(--text-muted);">Tidak ada barang pada filter ini</td></tr>
                    <?php endif; ?>
                    <?php foreach ($jenis as $j): ?>
                        <tr>
                            <td style="font-weight: 700;"><a href="<?= htmlspecialchars($linkJenis($j['jenis_id'])) ?>"><?= htmlspecialchars($j['jenis']) ?></a></td>
                            <td class="soh-angka soh-sisa"><?= number_format($j['sisa_box']) ?></td>
                            <td class="soh-angka soh-sisa"><?= $kg($j['sisa_kg']) ?></td>
                            <td class="soh-angka"><?= number_format($j['masuk_box']) ?></td>
                            <td class="soh-angka"><?= $kg($j['masuk_kg']) ?></td>
                            <td class="soh-angka"><?= number_format($j['keluar_box']) ?></td>
                            <td class="soh-angka"><?= $kg($j['keluar_kg']) ?></td>
                            <td class="soh-angka"><?= $rp($j['sisa_nilai']) ?></td>
                            <td class="soh-angka"><?= $j['produk_sisa'] ?> / <?= $j['produk'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($jenis): ?>
                <tfoot>
                    <tr style="font-weight: 800; background: var(--bg-muted);">
                        <td>TOTAL</td>
                        <td class="soh-angka"><?= number_format($tot['sisa_box']) ?></td>
                        <td class="soh-angka"><?= $kg($tot['sisa_kg']) ?></td>
                        <td class="soh-angka"><?= number_format($tot['masuk_box']) ?></td>
                        <td class="soh-angka"><?= $kg($tot['masuk_kg']) ?></td>
                        <td class="soh-angka"><?= number_format($tot['keluar_box']) ?></td>
                        <td class="soh-angka"><?= $kg($tot['keluar_kg']) ?></td>
                        <td class="soh-angka"><?= $rp($tot['sisa_nilai']) ?></td>
                        <td class="soh-angka"><?= $tot['produk_sisa'] ?> / <?= $tot['produk'] ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

    <?php else: ?>
        <!-- TAMPILAN PER PRODUK -->
        <div class="table-responsive">
            <table class="table-custom" id="tabelProduk" data-halaman="25">
                <thead>
                    <tr>
                        <th style="width: 36px; text-align: center;">No</th>
                        <th style="min-width: 180px;">Produk</th>
                        <th class="soh-sisa" style="text-align: right;">Sisa Box</th>
                        <th class="soh-sisa" style="text-align: right;">Sisa Kg</th>
                        <th style="text-align: right;">Masuk Box</th>
                        <th style="text-align: right;">Masuk Kg</th>
                        <th style="text-align: right;">Keluar Box</th>
                        <th style="text-align: right;">Keluar Kg</th>
                        <th style="text-align: right;">Nilai Sisa</th>
                        <th style="text-align: right;" title="Umur box tertua yang masih di gudang">Tertua</th>
                        <th class="no-print" style="text-align: center;">Rincian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$produk): ?>
                        <tr><td colspan="11" style="text-align: center; padding: 28px; color: var(--text-muted);">Tidak ada produk pada filter ini</td></tr>
                    <?php endif; ?>
                    <?php foreach ($produk as $i => $r): $habis = $r['sisa_box'] <= 0; ?>
                        <tr>
                            <td style="text-align: center; color: var(--text-muted);"><?= $i + 1 ?></td>
                            <td>
                                <strong style="color: var(--text-main);"><?= htmlspecialchars($r['nama_item']) ?></strong>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($r['kode_item']) ?></div>
                            </td>
                            <td class="soh-angka soh-sisa <?= $habis ? 'soh-redup' : '' ?>"><?= number_format($r['sisa_box']) ?></td>
                            <td class="soh-angka soh-sisa <?= $habis ? 'soh-redup' : '' ?>"><?= $kg($r['sisa_kg']) ?></td>
                            <td class="soh-angka"><?= number_format($r['masuk_box']) ?></td>
                            <td class="soh-angka"><?= $kg($r['masuk_kg']) ?></td>
                            <td class="soh-angka"><?= number_format($r['keluar_box']) ?></td>
                            <td class="soh-angka"><?= $kg($r['keluar_kg']) ?></td>
                            <td class="soh-angka"><?= $habis ? '-' : $rp($r['sisa_nilai']) ?></td>
                            <td class="soh-angka" data-sort="<?= $r['umur_tertua'] ?? -1 ?>"><?= $r['umur_tertua'] === null ? '-' : $r['umur_tertua'] . ' hari' ?></td>
                            <td class="no-print" style="text-align: center;">
                                <button type="button" class="btn btn-outline btn-sm" style="padding: 3px 9px; font-size: 11px;"
                                        onclick='rincianProduk(<?= (int)$r['item_id'] ?>, <?= json_encode($r['nama_item']) ?>)'>Box</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    </div>

</div>

<!-- MODAL: RINCIAN PER BOX SATU PRODUK -->
<div id="modalSoh" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 980px; margin: auto; padding: 24px; border-radius: 12px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 12px;">
            <div>
                <h3 id="sohJudul" style="margin: 0; font-size: 16px; font-weight: 800;">Rincian Box</h3>
                <p id="sohSub" style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted);"></p>
            </div>
            <div style="display: flex; gap: 6px; align-items: center;">
                <select id="sohPosisi" class="form-control" style="width: auto; font-size: 12.5px;" onchange="tampilRincian()">
                    <option value="">Semua box</option>
                    <option value="SISA">Masih di gudang</option>
                    <option value="KELUAR">Sudah keluar</option>
                </select>
                <button type="button" onclick="document.getElementById('modalSoh').style.display='none'" style="border: none; background: transparent; font-size: 22px; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>
        </div>
        <div class="table-responsive" style="max-height: 440px; overflow-y: auto;">
            <table class="table-custom" data-no-tools style="font-size: 12.5px;">
                <thead><tr><th>Barcode</th><th>SJ Masuk</th><th>Pemasok</th><th>Tgl Masuk</th><th style="text-align:right;">Umur</th><th style="text-align:right;">Netto</th><th>Posisi</th></tr></thead>
                <tbody id="sohBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
const sohFilter = <?= json_encode(['pemasok' => $f['pemasok_id'], 'dari' => $f['dari'], 'sampai' => $f['sampai']]) ?>;
const fmtKgSoh = n => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n) + ' kg';
const escSoh = s => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const tglSoh = s => { if (!s) return '-'; const d = s.slice(0, 10).split('-'); return d[2] + '/' + d[1] + '/' + d[0]; };
let sohBox = [];

async function rincianProduk(itemId, nama) {
    try {
        const q = new URLSearchParams({ action: 'stock_on_hand_rincian', item: itemId, pemasok: sohFilter.pemasok || '', dari: sohFilter.dari || '', sampai: sohFilter.sampai || '' });
        const r = await fetch('index.php?' + q.toString());
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Gagal memuat rincian');
        sohBox = j.box;
        document.getElementById('sohJudul').textContent = nama;
        document.getElementById('sohPosisi').value = '';
        tampilRincian();
        document.getElementById('modalSoh').style.display = 'flex';
    } catch (e) { Swal.fire({ icon: 'error', title: 'Gagal', text: e.message }); }
}

function tampilRincian() {
    const pos = document.getElementById('sohPosisi').value;
    const data = pos ? sohBox.filter(b => b.posisi === pos) : sohBox;
    let kgSisa = 0, kgKeluar = 0, nSisa = 0, nKeluar = 0;
    sohBox.forEach(b => { if (b.posisi === 'SISA') { nSisa++; kgSisa += parseFloat(b.berat_netto); } else { nKeluar++; kgKeluar += parseFloat(b.berat_netto); } });
    document.getElementById('sohSub').textContent = 'Sisa ' + nSisa + ' box (' + fmtKgSoh(kgSisa) + ')  •  Keluar ' + nKeluar + ' box (' + fmtKgSoh(kgKeluar) + ')';
    document.getElementById('sohBody').innerHTML = data.length ? data.map(b => {
        const posisi = b.posisi === 'SISA'
            ? '<span style="font-weight:700;color:var(--ok-fg);">Di gudang</span>'
            : '<span style="font-weight:700;color:var(--text-muted);">Keluar</span><div style="font-size:11px;color:var(--text-muted);">' +
              escSoh(b.nama_mitra || '-') + (b.no_sj_keluar ? ' &middot; ' + escSoh(b.no_sj_keluar) : '') + (b.tgl_keluar ? ' &middot; ' + tglSoh(b.tgl_keluar) : '') + '</div>';
        return '<tr><td style="font-family:\'JetBrains Mono\',monospace;">' + escSoh(b.id_label) + '</td><td>' + escSoh(b.no_surat_jalan) + '</td><td>' + escSoh(b.pemasok) +
               '</td><td>' + tglSoh(b.tgl_masuk) + '</td><td style="text-align:right;">' + escSoh(b.umur) + ' hr</td><td style="text-align:right;">' + fmtKgSoh(parseFloat(b.berat_netto)) +
               '</td><td>' + posisi + '</td></tr>';
    }).join('') : '<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text-muted);">Tidak ada box</td></tr>';
}
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
