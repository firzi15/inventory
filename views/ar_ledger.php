<?php
/**
 * AR Ledger: saldo awal, debit (nota baru), kredit (setoran), dan saldo akhir piutang per mitra dalam satu periode.
 */

use App\Core\Helper;
use App\Core\Periode;
use App\Services\ArLedgerService;

$pageTitle = 'AR Ledger';

[$dari, $sampai] = Periode::baca($_GET, 'bulan');
$mitraId = (int)($_GET['mitra'] ?? 0);

$rows = ArLedgerService::perMitra($dari, $sampai, $mitraId);
$tot  = ArLedgerService::total($rows);
$mitraList = \App\Models\Mitra::getAll();

$rp  = fn($n) => Helper::formatRupiah($n);
$tgl = fn($d) => date('d/m/Y', strtotime($d));
$labelPeriode = $tgl($dari) . ' – ' . $tgl($sampai);
$warna = fn($n) => $n < 0 ? 'var(--bad-fg)' : 'inherit';

require_once __DIR__ . '/layout/header.php';
?>

<style>
    .arl-angka { text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 12.5px; white-space: nowrap; }
    .arl-akhir { background: var(--bg-muted); font-weight: 800; }
</style>

<div class="content-wrapper">

    <div class="page-header no-print">
        <div>
            <h1 class="page-title">AR Ledger</h1>
            <p class="page-subtitle">Saldo awal, debit, kredit, dan saldo akhir piutang per mitra &middot; <?= $labelPeriode ?></p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <?php $eksporNama = 'AR_Ledger_' . $dari . '_' . $sampai; $eksporJudul = 'AR Ledger ' . $labelPeriode; include __DIR__ . '/layout/ekspor.php'; ?>
        </div>
    </div>

    <?php
    ob_start(); ?>
        <label style="display: flex; align-items: center; gap: 8px;">Mitra
            <select name="mitra" class="form-control" style="width: auto; min-width: 220px;" onchange="this.form.submit()">
                <option value="0">Semua mitra</option>
                <?php foreach ($mitraList as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= $mitraId === (int)$m['id'] ? 'selected' : '' ?>>[<?= htmlspecialchars($m['kode_mitra']) ?>] <?= htmlspecialchars($m['nama_mitra']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php
    $fpExtra = ob_get_clean();
    $fpDari = $dari; $fpSampai = $sampai; $fpHidden = ['page' => 'ar_ledger']; $fpSemua = false;
    $fpLinkExtra = $mitraId > 0 ? ['mitra' => $mitraId] : [];
    include __DIR__ . '/layout/filter_periode.php';
    ?>

    <!-- KARTU RINGKASAN PERIODE -->
    <div class="grid grid-cols-4 no-print" style="gap: 16px; margin: 18px 0 22px;">
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Saldo Awal</div>
                <div class="kpi-value"><?= $rp($tot['awal']) ?></div>
                <div class="kpi-sub">sisa piutang sebelum <?= $tgl($dari) ?></div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="history" style="width: 18px; height: 18px;"></i></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Debit (Piutang Baru)</div>
                <div class="kpi-value"><?= $rp($tot['debit']) ?></div>
                <div class="kpi-sub"><?= number_format($tot['jml_nota']) ?> nota pengeluaran</div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="file-plus" style="width: 18px; height: 18px;"></i></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Kredit (Setoran)</div>
                <div class="kpi-value"><?= $rp($tot['kredit']) ?></div>
                <div class="kpi-sub"><?= number_format($tot['jml_setor']) ?> setoran mitra</div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="wallet" style="width: 18px; height: 18px;"></i></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-body">
                <div class="kpi-label">Saldo Akhir</div>
                <div class="kpi-value"><?= $rp($tot['akhir']) ?></div>
                <div class="kpi-sub">sisa piutang per <?= $tgl($sampai) ?></div>
            </div>
            <div class="kpi-icon-pod"><i data-lucide="scale" style="width: 18px; height: 18px;"></i></div>
        </div>
    </div>

    <div class="card" style="padding: 20px;">
        <div class="table-responsive">
            <table class="table-custom" id="tabelArLedger">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">No</th>
                        <th style="min-width: 200px;">Mitra</th>
                        <th style="text-align: right;">Saldo Awal</th>
                        <th style="text-align: right;">Debit</th>
                        <th style="text-align: right;">Kredit</th>
                        <th class="arl-akhir" style="text-align: right;">Saldo Akhir</th>
                        <th class="no-print" style="width: 90px; text-align: center;">Kartu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 28px; color: var(--text-muted);">Tidak ada mitra</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $i => $r): ?>
                        <tr>
                            <td style="text-align: center; color: var(--text-muted);"><?= $i + 1 ?></td>
                            <td><strong style="color: var(--text-main);">[<?= htmlspecialchars($r['kode_mitra']) ?>] <?= htmlspecialchars($r['nama_mitra']) ?></strong></td>
                            <td class="arl-angka" style="color: <?= $warna($r['awal']) ?>;"><?= $rp($r['awal']) ?></td>
                            <td class="arl-angka"><?= $rp($r['debit']) ?></td>
                            <td class="arl-angka" style="color: var(--ok-fg);"><?= $rp($r['kredit']) ?></td>
                            <td class="arl-angka arl-akhir" style="color: <?= $warna($r['akhir']) ?>;"><?= $rp($r['akhir']) ?></td>
                            <td class="no-print" style="text-align: center;">
                                <button type="button" class="btn btn-outline btn-sm" style="padding: 3px 9px; font-size: 11px;"
                                        onclick='kartuPiutang(<?= (int)$r['mitra_id'] ?>, <?= json_encode('[' . $r['kode_mitra'] . '] ' . $r['nama_mitra']) ?>)'>Kartu</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($rows): ?>
                <tfoot>
                    <tr style="font-weight: 800; background: var(--bg-muted);">
                        <td></td>
                        <td>TOTAL</td>
                        <td class="arl-angka"><?= $rp($tot['awal']) ?></td>
                        <td class="arl-angka"><?= $rp($tot['debit']) ?></td>
                        <td class="arl-angka"><?= $rp($tot['kredit']) ?></td>
                        <td class="arl-angka"><?= $rp($tot['akhir']) ?></td>
                        <td class="no-print"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

</div>

<!-- MODAL: KARTU PIUTANG SATU MITRA -->
<div id="modalKartu" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 900px; margin: auto; padding: 24px; border-radius: 12px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <div>
                <h3 id="kartuJudul" style="margin: 0; font-size: 16px; font-weight: 800;">Kartu Piutang</h3>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted);">Periode <?= $labelPeriode ?></p>
            </div>
            <button type="button" onclick="document.getElementById('modalKartu').style.display='none'" style="border: none; background: transparent; font-size: 22px; cursor: pointer; color: var(--text-muted);">&times;</button>
        </div>
        <div class="table-responsive" style="max-height: 460px; overflow-y: auto;">
            <table class="table-custom" data-no-tools style="font-size: 12.5px;">
                <thead><tr><th>Tanggal</th><th>Dokumen</th><th>Keterangan</th><th style="text-align:right;">Debit</th><th style="text-align:right;">Kredit</th><th style="text-align:right;">Saldo</th></tr></thead>
                <tbody id="kartuBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
const arlPeriode = <?= json_encode(['dari' => $dari, 'sampai' => $sampai]) ?>;
const rpArl = n => (n < 0 ? '-' : '') + 'Rp ' + new Intl.NumberFormat('en-US').format(Math.abs(Math.round(n)));
const escArl = s => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const tglArl = s => { const d = String(s).slice(0, 10).split('-'); return d[2] + '/' + d[1] + '/' + d[0]; };

async function kartuPiutang(mitraId, nama) {
    try {
        const q = new URLSearchParams({ action: 'ar_ledger_kartu', mitra: mitraId, dari: arlPeriode.dari, sampai: arlPeriode.sampai });
        const r = await fetch('index.php?' + q.toString());
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Gagal memuat kartu piutang');
        const k = j.kartu;
        let tD = 0, tK = 0;
        let html = '<tr style="background:var(--bg-muted);"><td>' + tglArl(arlPeriode.dari) + '</td><td colspan="4" style="font-weight:700;">Saldo awal</td><td style="text-align:right;font-weight:700;">' + rpArl(k.awal) + '</td></tr>';
        html += k.baris.map(b => {
            tD += b.debit; tK += b.kredit;
            const jenis = b.jenis === 'NOTA' ? '<span style="font-size:10.5px;font-weight:700;color:var(--aktif-fg);">NOTA</span> ' : '<span style="font-size:10.5px;font-weight:700;color:var(--ok-fg);">SETORAN</span> ';
            return '<tr><td>' + tglArl(b.tanggal) + '</td><td>' + jenis + escArl(b.dokumen) + '</td><td>' + escArl(b.keterangan) + '</td>' +
                   '<td style="text-align:right;">' + (b.debit ? rpArl(b.debit) : '-') + '</td><td style="text-align:right;color:var(--ok-fg);">' + (b.kredit ? rpArl(b.kredit) : '-') + '</td>' +
                   '<td style="text-align:right;font-weight:700;">' + rpArl(b.saldo) + '</td></tr>';
        }).join('');
        if (!k.baris.length) html += '<tr><td colspan="6" style="text-align:center;padding:16px;color:var(--text-muted);">Tidak ada nota atau setoran pada periode ini</td></tr>';
        html += '<tr style="font-weight:800;background:var(--bg-muted);"><td colspan="3">Saldo akhir</td><td style="text-align:right;">' + rpArl(tD) + '</td><td style="text-align:right;">' + rpArl(tK) + '</td><td style="text-align:right;">' + rpArl(k.akhir) + '</td></tr>';
        document.getElementById('kartuBody').innerHTML = html;
        document.getElementById('kartuJudul').textContent = 'Kartu Piutang ' + nama;
        document.getElementById('modalKartu').style.display = 'flex';
    } catch (e) { Swal.fire({ icon: 'error', title: 'Gagal', text: e.message }); }
}
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
