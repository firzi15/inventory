<?php
/**
 * Rekonsiliasi Bank Harian.
 * Sehari satu kali: total setoran mitra hari itu (dari sistem) dicocokkan dengan total uang yang masuk di bank,
 * dengan lampiran screenshot / slip bank sebagai bukti audit.
 */

use App\Core\Helper;
use App\Models\RekonsiliasiBank;

$pageTitle = 'Rekonsiliasi Bank';

$dari   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dari'] ?? '') ? $_GET['dari'] : date('Y-m-01');
$sampai = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sampai'] ?? '') ? $_GET['sampai'] : date('Y-m-d');
$status = in_array($_GET['status'] ?? '', ['BELUM', 'SESUAI', 'SELISIH', 'BERUBAH'], true) ? $_GET['status'] : '';

$semuaHari = RekonsiliasiBank::daftarHari($dari, $sampai);
$kpi = RekonsiliasiBank::ringkas($semuaHari);
$hari = $status === '' ? $semuaHari : array_values(array_filter($semuaHari, fn($h) => $h['status_hari'] === $status));

$role = strtoupper($_SESSION['user_login']['role'] ?? '');
$bolehRekon = \App\Core\Akses::boleh($role, ['ADMIN', 'SUPERVISOR', 'FINANCE']);

$rp = fn($n) => Helper::formatRupiah($n);
$tgl = fn($d) => date('d/m/Y', strtotime($d));
$hariNama = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

$badge = [
    'BELUM'   => ['Belum direkonsiliasi', 'background: var(--warn-bg); color: var(--warn-fg);'],
    'SESUAI'  => ['Sesuai', 'background: var(--ok-bg); color: var(--ok-fg);'],
    'SELISIH' => ['Selisih', 'background: var(--bad-bg); color: var(--bad-fg);'],
    'BERUBAH' => ['Setoran berubah', 'background: var(--warn-bg); color: var(--warn-fg);'],
];

require_once __DIR__ . '/layout/header.php';
?>

<div class="content-wrapper">

    <div class="page-header no-print">
        <div>
            <h1 class="page-title">Rekonsiliasi Bank</h1>
            <p class="page-subtitle">Sehari sekali: total setoran mitra pada hari itu dicocokkan dengan uang yang masuk di bank, dengan lampiran screenshot bank</p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <?php $eksporNama = 'Rekonsiliasi_Bank'; $eksporJudul = 'Rekonsiliasi Bank ' . $tgl($dari) . ' s/d ' . $tgl($sampai); include __DIR__ . '/layout/ekspor.php'; ?>
        </div>
    </div>

    <?php $rekAktif = \App\Models\RekeningBank::getAktif(); ?>
    <div class="no-print" style="margin: -6px 0 16px; font-size: 13px; color: var(--text-muted);">
        <?php if ($rekAktif): ?>
            Rekening yang diaudit:
            <?php foreach ($rekAktif as $i => $rk): ?><?= $i ? ', ' : '' ?><strong style="color: var(--text-main);"><?= htmlspecialchars(\App\Models\RekeningBank::label($rk)) ?></strong><?php endforeach; ?>
        <?php else: ?>
            Belum ada rekening bank terdaftar. Tambahkan di Master &gt; Rekening Bank.
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-4 no-print" style="gap: 16px; margin-bottom: 20px;">
        <div class="kpi-card"><div class="kpi-body">
            <div class="kpi-label">Hari Belum Direkonsiliasi</div>
            <div class="kpi-value"><?= $kpi['belum'] ?></div>
            <div class="kpi-sub"><?= $rp($kpi['belum_nominal']) ?> belum diaudit</div>
        </div></div>
        <div class="kpi-card"><div class="kpi-body">
            <div class="kpi-label">Hari Sesuai</div>
            <div class="kpi-value"><?= $kpi['sesuai'] ?></div>
            <div class="kpi-sub">dari <?= $kpi['hari'] ?> hari ada setoran</div>
        </div></div>
        <div class="kpi-card"><div class="kpi-body">
            <div class="kpi-label">Hari Selisih / Berubah</div>
            <div class="kpi-value"><?= $kpi['selisih'] + $kpi['berubah'] ?></div>
            <div class="kpi-sub">Total selisih <?= $rp($kpi['total_selisih']) ?></div>
        </div></div>
        <div class="kpi-card"><div class="kpi-body">
            <div class="kpi-label">Total Setoran Sistem</div>
            <div class="kpi-value"><?= $rp($kpi['total_sistem']) ?></div>
            <div class="kpi-sub">Masuk bank <?= $rp($kpi['total_bank']) ?></div>
        </div></div>
    </div>

    <form method="GET" class="card no-print" style="padding: 14px 16px; margin-bottom: 18px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="page" value="rekonsiliasi_bank">
        <div>
            <label class="form-label">Dari</label>
            <input type="date" name="dari" value="<?= $dari ?>" class="form-control">
        </div>
        <div>
            <label class="form-label">Sampai</label>
            <input type="date" name="sampai" value="<?= $sampai ?>" class="form-control">
        </div>
        <div style="min-width: 210px;">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">Semua status</option>
                <?php foreach ($badge as $k => $b): ?>
                    <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $b[0] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Tampilkan</button>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table-custom" data-halaman="25">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th style="text-align: right;">Setoran</th>
                        <th style="text-align: right;">Tunai</th>
                        <th style="text-align: right;">Transfer</th>
                        <th style="text-align: right;">Total Sistem</th>
                        <th style="text-align: right;">Masuk Bank</th>
                        <th style="text-align: right;">Selisih</th>
                        <th>Status</th>
                        <th>Lampiran</th>
                        <th>Diaudit Oleh</th>
                        <th style="width: 150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$hari): ?>
                        <tr><td colspan="11" style="text-align: center; padding: 30px; color: var(--text-muted);">Tidak ada hari dengan setoran pada filter ini</td></tr>
                    <?php endif; ?>
                    <?php foreach ($hari as $h):
                        $r = $h['rekon'];
                        $b = $badge[$h['status_hari']]; ?>
                        <tr>
                            <td style="font-weight: 700;"><?= $hariNama[(int)date('w', strtotime($h['tanggal']))] ?>, <?= $tgl($h['tanggal']) ?></td>
                            <td style="text-align: right;"><?= $h['jumlah_setoran'] ?></td>
                            <td style="text-align: right;"><?= $rp($h['total_tunai']) ?></td>
                            <td style="text-align: right;"><?= $rp($h['total_transfer']) ?></td>
                            <td style="text-align: right; font-weight: 700;"><?= $rp($h['total_sistem']) ?></td>
                            <td style="text-align: right;"><?= $r ? $rp($r['total_bank']) : '-' ?></td>
                            <td style="text-align: right; color: <?= ($r && abs((float)$r['selisih']) >= 0.01) ? '#b91c1c' : 'inherit' ?>;"><?= $r ? $rp($r['selisih']) : '-' ?></td>
                            <td><span class="badge" style="<?= $b[1] ?> font-weight: 700;"><?= $b[0] ?></span></td>
                            <td><?= $r ? ($r['jumlah_bukti'] . ' file') : '-' ?></td>
                            <td style="font-size: 12.5px;"><?= $r ? htmlspecialchars((string)$r['verifikator']) . '<div style="color: var(--text-muted); font-size: 11.5px;">' . date('d/m/Y H:i', strtotime($r['verified_at'])) . '</div>' : '-' ?></td>
                            <td>
                                <?php if (!$r && $bolehRekon): ?>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="bukaRekon('<?= $h['tanggal'] ?>')">Rekonsiliasi</button>
                                <?php elseif ($r): ?>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="lihatRekon('<?= $h['tanggal'] ?>')">Lihat</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- MODAL: REKONSILIASI SATU HARI -->
<div id="modalRekon" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 640px; margin: auto; padding: 24px; border-radius: 12px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
            <div>
                <h3 id="rekonJudul" style="margin: 0; font-size: 17px; font-weight: 800;">Rekonsiliasi</h3>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted);">Cocokkan total setoran hari ini dengan uang yang masuk di bank</p>
            </div>
            <button type="button" onclick="tutupRekon()" style="border: none; background: transparent; font-size: 22px; cursor: pointer; color: var(--text-muted);">&times;</button>
        </div>

        <div class="table-responsive" style="max-height: 220px; overflow-y: auto; margin-bottom: 12px;">
            <table class="table-custom" data-no-tools data-no-export style="font-size: 12.5px;">
                <thead><tr><th>No. Bukti</th><th>Mitra</th><th>Metode</th><th style="text-align: right;">Nominal</th></tr></thead>
                <tbody id="rekonRincian"></tbody>
            </table>
        </div>

        <div id="rekonForm">
            <div style="display: flex; justify-content: space-between; font-weight: 800; margin-bottom: 12px;">
                <span>Total setoran sistem</span><span id="rekonTotalSistem">Rp 0</span>
            </div>
            <div class="form-group">
                <label class="form-label">Total uang masuk di bank (sesuai screenshot) <span style="color: var(--bad-fg);">*</span></label>
                <input type="number" id="rekonTotalBank" class="form-control" min="0" step="any" oninput="hitungSelisihRekon()" placeholder="Isi sesuai mutasi bank pada tanggal ini">
                <div id="rekonSelisih" style="font-size: 12.5px; margin-top: 6px; font-weight: 700;"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Lampiran screenshot / slip bank <span style="color: var(--bad-fg);">*</span></label>
                <input type="file" id="rekonFile" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px;">Boleh lebih dari satu file. JPG, PNG, WEBP, atau PDF, maksimal 8 MB per file.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Catatan <span id="rekonCatatanWajib" style="color: var(--bad-fg); display: none;">(wajib jika ada selisih)</span></label>
                <input type="text" id="rekonCatatan" class="form-control" placeholder="Contoh: selisih Rp 2.500 biaya admin transfer">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px;">
                <button type="button" class="btn btn-outline" onclick="tutupRekon()">Batal</button>
                <button type="button" id="rekonSimpan" class="btn btn-primary" onclick="simpanRekon()">Simpan Rekonsiliasi</button>
            </div>
        </div>

        <div id="rekonLihat" style="display: none;"></div>
    </div>
</div>

<script>
let rekonTanggal = '', rekonTotalSistem = 0;
const rupiah = n => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n));
const esc = s => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

async function ambilDetail(tanggal) {
    const r = await fetch('index.php?action=detail_rekonsiliasi_harian&tanggal=' + encodeURIComponent(tanggal));
    const j = await r.json();
    if (!j.success) throw new Error(j.message || 'Gagal memuat data');
    return j;
}

function isiRincian(setoran) {
    let total = 0;
    document.getElementById('rekonRincian').innerHTML = setoran.map(s => {
        total += parseFloat(s.jumlah_bayar);
        return '<tr><td>' + esc(s.no_bukti_bayar) + '</td><td>' + esc(s.nama_mitra) + '</td><td>' + (s.metode_bayar === 'TUNAI' ? 'Tunai' : 'Transfer') +
               '</td><td style="text-align:right;">' + rupiah(s.jumlah_bayar) + '</td></tr>';
    }).join('') || '<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Tidak ada setoran aktif</td></tr>';
    return total;
}

async function bukaRekon(tanggal) {
    try {
        const d = await ambilDetail(tanggal);
        rekonTanggal = tanggal;
        document.getElementById('rekonJudul').textContent = 'Rekonsiliasi ' + tanggal.split('-').reverse().join('/');
        rekonTotalSistem = isiRincian(d.setoran);
        document.getElementById('rekonTotalSistem').textContent = rupiah(rekonTotalSistem);
        document.getElementById('rekonTotalBank').value = rekonTotalSistem;
        document.getElementById('rekonFile').value = '';
        document.getElementById('rekonCatatan').value = '';
        document.getElementById('rekonForm').style.display = '';
        document.getElementById('rekonLihat').style.display = 'none';
        hitungSelisihRekon();
        document.getElementById('modalRekon').style.display = 'flex';
    } catch (e) { Swal.fire({ icon: 'error', title: 'Gagal', text: e.message }); }
}

function hitungSelisihRekon() {
    const bank = parseFloat(document.getElementById('rekonTotalBank').value);
    const el = document.getElementById('rekonSelisih');
    if (isNaN(bank)) { el.textContent = ''; return; }
    const sel = Math.round((bank - rekonTotalSistem) * 100) / 100;
    document.getElementById('rekonCatatanWajib').style.display = Math.abs(sel) >= 0.01 ? 'inline' : 'none';
    el.style.color = Math.abs(sel) < 0.01 ? '#15803d' : '#b91c1c';
    el.textContent = Math.abs(sel) < 0.01 ? 'Sesuai dengan total sistem' : 'Selisih ' + (sel > 0 ? 'lebih ' : 'kurang ') + rupiah(Math.abs(sel)) + ' dari total sistem';
}

async function simpanRekon() {
    const files = document.getElementById('rekonFile').files;
    const bank = document.getElementById('rekonTotalBank').value;
    if (bank === '') { Swal.fire({ icon: 'warning', title: 'Total bank kosong', text: 'Isi total uang masuk di bank.' }); return; }
    if (!files.length) { Swal.fire({ icon: 'warning', title: 'Lampiran kosong', text: 'Lampirkan screenshot / slip bank.' }); return; }
    const fd = new FormData();
    fd.append('tanggal', rekonTanggal);
    fd.append('total_bank', bank);
    fd.append('catatan', document.getElementById('rekonCatatan').value);
    Array.from(files).forEach(f => fd.append('bukti[]', f));
    const btn = document.getElementById('rekonSimpan');
    btn.disabled = true;
    try {
        const r = await fetch('index.php?action=simpan_rekonsiliasi_harian', { method: 'POST', body: fd });
        const j = await r.json();
        if (j.success) {
            await Swal.fire({ icon: 'success', title: 'Tersimpan', text: j.message });
            window.location.reload();
        } else {
            Swal.fire({ icon: 'error', title: 'Belum bisa disimpan', text: j.message });
        }
    } catch (e) { Swal.fire({ icon: 'error', title: 'Kesalahan jaringan', text: 'Coba lagi beberapa saat.' }); }
    btn.disabled = false;
}

async function lihatRekon(tanggal) {
    try {
        const d = await ambilDetail(tanggal);
        const r = d.rekon;
        rekonTanggal = tanggal;
        document.getElementById('rekonJudul').textContent = 'Rekonsiliasi ' + tanggal.split('-').reverse().join('/');
        const totalSekarang = isiRincian(d.setoran);
        const berubah = Math.abs(totalSekarang - parseFloat(r.total_sistem)) > 0.009;
        const bukti = r.bukti.map(b => {
            const pdf = /\.pdf$/i.test(b.file_path);
            return '<a href="' + esc(b.file_path) + '" target="_blank" rel="noopener" style="display:inline-block;margin:0 8px 8px 0;border:1px solid var(--border);border-radius:8px;overflow:hidden;width:110px;text-align:center;font-size:11px;">' +
                   (pdf ? '<div style="padding:30px 0;">PDF</div>' : '<img src="' + esc(b.file_path) + '" style="width:110px;height:90px;object-fit:cover;display:block;">') +
                   '<div style="padding:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(b.nama_asli) + '</div></a>';
        }).join('');
        const sel = parseFloat(r.selisih);
        document.getElementById('rekonLihat').innerHTML =
            (berubah ? '<div style="background:var(--warn-bg);color:var(--warn-fg);padding:10px 12px;border-radius:8px;font-size:12.5px;font-weight:700;margin-bottom:12px;">Total setoran sekarang ' + rupiah(totalSekarang) + ' berbeda dari saat direkonsiliasi (' + rupiah(r.total_sistem) + '). Buka kembali rekonsiliasi untuk mengauditnya ulang.</div>' : '') +
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 20px;font-size:13px;margin-bottom:12px;">' +
            '<div>Total sistem<br><strong>' + rupiah(r.total_sistem) + '</strong></div>' +
            '<div>Masuk bank<br><strong>' + rupiah(r.total_bank) + '</strong></div>' +
            '<div>Selisih<br><strong style="color:' + (Math.abs(sel) < 0.01 ? '#15803d' : '#b91c1c') + ';">' + rupiah(sel) + '</strong></div>' +
            '<div>Diaudit<br><strong>' + esc(r.verifikator) + '</strong> &middot; ' + esc(r.verified_at) + '</div></div>' +
            (r.catatan ? '<div style="font-size:13px;margin-bottom:12px;"><strong>Catatan:</strong> ' + esc(r.catatan) + '</div>' : '') +
            '<div style="font-weight:700;font-size:12.5px;margin-bottom:6px;">Lampiran bank</div><div>' + bukti + '</div>' +
            <?php if ($bolehRekon): ?>'<div style="display:flex;justify-content:flex-end;margin-top:14px;"><button type="button" class="btn btn-outline" style="color:var(--bad-fg);border-color:var(--border-hover);" onclick="bukaKembaliRekon()">Buka kembali</button></div>'<?php else: ?>''<?php endif; ?>;
        document.getElementById('rekonForm').style.display = 'none';
        document.getElementById('rekonLihat').style.display = '';
        document.getElementById('modalRekon').style.display = 'flex';
    } catch (e) { Swal.fire({ icon: 'error', title: 'Gagal', text: e.message }); }
}

async function bukaKembaliRekon() {
    const h = await Swal.fire({
        title: 'Buka kembali rekonsiliasi?',
        html: '<div style="text-align:left;font-size:13px;">Hasil audit tanggal ini dihapus dan setoran pada tanggal itu kembali bisa diubah atau dibatalkan. Tindakan ini tercatat.</div>',
        input: 'textarea', inputLabel: 'Alasan', showCancelButton: true,
        confirmButtonText: 'Buka Kembali', cancelButtonText: 'Batal', confirmButtonColor: '#dc2626', icon: 'warning',
        preConfirm: v => { if (!v || v.trim().length < 3) { Swal.showValidationMessage('Alasan wajib diisi'); return false; } return v.trim(); }
    });
    if (!h.isConfirmed) return;
    const fd = new FormData();
    fd.append('tanggal', rekonTanggal);
    fd.append('alasan', h.value);
    try {
        const r = await fetch('index.php?action=buka_rekonsiliasi_harian', { method: 'POST', body: fd });
        const j = await r.json();
        if (j.success) { await Swal.fire({ icon: 'success', title: 'Dibuka kembali', text: j.message }); window.location.reload(); }
        else Swal.fire({ icon: 'error', title: 'Gagal', text: j.message });
    } catch (e) { Swal.fire({ icon: 'error', title: 'Kesalahan jaringan', text: 'Coba lagi beberapa saat.' }); }
}

function tutupRekon() { document.getElementById('modalRekon').style.display = 'none'; }
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
