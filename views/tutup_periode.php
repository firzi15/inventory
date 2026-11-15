<?php
/**
 * Tutup Harian.
 * Hari yang ditutup tidak menerima jurnal baru, dan dokumen bertanggal di hari itu tidak bisa dibatalkan.
 * Angka hari itu disimpan saat ditutup. Hanya Admin yang dapat membuka kembali.
 */

use App\Core\Helper;
use App\Services\PeriodeService;

$pageTitle = 'Tutup Harian';

$daftarBulan = PeriodeService::daftarBulan();
$bulan = preg_match('/^\d{4}-\d{2}$/', $_GET['bulan'] ?? '') ? $_GET['bulan'] : date('Y-m');
if (!in_array($bulan, $daftarBulan, true)) $bulan = $daftarBulan[0] ?? date('Y-m');

$daftar = PeriodeService::daftarHari($bulan);
$role = strtoupper($_SESSION['user_login']['role'] ?? '');
$bolehTutup = \App\Core\Akses::boleh($role, ['ADMIN', 'FINANCE']);
$bolehBuka = \App\Core\Akses::boleh($role, ['ADMIN']);

$rp = fn($n) => Helper::formatRupiah($n);
$jumlahTutup = count(array_filter($daftar, fn($p) => $p['status'] === 'TUTUP'));
$kemarin = date('Y-m-d', strtotime('-1 day'));
$adaTerbuka = (bool)array_filter($daftar, fn($p) => $p['status'] !== 'TUTUP' && $p['bisa_tutup']);

require_once __DIR__ . '/layout/header.php';
?>

<div class="content-wrapper">

    <div class="page-header no-print">
        <div>
            <h1 class="page-title">Tutup Harian</h1>
            <p class="page-subtitle">Kunci angka tiap hari setelah dicek. Hari yang ditutup tidak menerima transaksi atau pembatalan baru.</p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <?php $eksporTabel = 'tablePeriode'; $eksporNama = 'Tutup_Harian_' . $bulan; $eksporJudul = 'Status Tutup Harian ' . PeriodeService::namaBulan($bulan); include __DIR__ . '/layout/ekspor.php'; ?>
            <?php if ($bolehTutup): ?>
                <button type="button" class="btn btn-primary" onclick='tutupSampai(<?= json_encode($kemarin) ?>)'>Tutup semua sampai kemarin</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="padding: 14px 18px; margin-bottom: 18px; font-size: 13px; color: var(--text-muted); line-height: 1.6;">
        Hari ini boleh ditutup lebih awal, misalnya bila toko tutup sebelum tengah malam. Setelah ditutup, transaksi bertanggal di hari itu
        (faktur, pembayaran, setoran, kas, rekonsiliasi) ditolak, dan dokumen bertanggal di hari itu tidak bisa dibatalkan.
        Perubahan harga master di hari berikutnya tidak mengubah angka hari yang sudah ditutup. Untuk koreksi, Admin membuka kembali harinya dengan alasan yang tercatat di log audit.
    </div>

    <form method="GET" class="card no-print" style="padding: 12px 16px; margin-bottom: 18px; display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="page" value="tutup_periode">
        <div style="min-width: 200px;">
            <label class="form-label">Bulan</label>
            <select name="bulan" class="form-control" onchange="this.form.submit()">
                <?php foreach ($daftarBulan as $b): ?>
                    <option value="<?= $b ?>" <?= $b === $bulan ? 'selected' : '' ?>><?= PeriodeService::namaBulan($b) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="font-size: 13px; color: var(--text-muted); padding-bottom: 10px;">
            <strong style="color: var(--text-main);"><?= $jumlahTutup ?></strong> dari <?= count($daftar) ?> hari pada bulan ini sudah ditutup
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table-custom" id="tablePeriode" data-no-tools>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th style="text-align: right;">Jurnal</th>
                        <th style="text-align: right;">Penjualan</th>
                        <th style="text-align: right;">HPP</th>
                        <th style="text-align: right;">Biaya</th>
                        <th style="text-align: right;">Laba Bersih</th>
                        <th>Keterangan</th>
                        <th class="no-print" style="width: 150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$daftar): ?>
                        <tr><td colspan="9" style="text-align: center; padding: 30px; color: var(--text-muted);">Belum ada hari pada bulan ini</td></tr>
                    <?php endif; ?>
                    <?php foreach ($daftar as $p):
                        $r = $p['ringkas']; $b = $p['baris'];
                        $tutup = $p['status'] === 'TUTUP'; ?>
                        <tr style="<?= $p['transaksi'] === 0 && !$tutup ? 'color: var(--text-muted);' : '' ?>">
                            <td style="font-weight: 700;"><?= htmlspecialchars($p['nama']) ?></td>
                            <td>
                                <?php if ($tutup): ?>
                                    <span class="badge" style="background: var(--border); color: var(--text-main); font-weight: 700;">Ditutup</span>
                                <?php elseif ($p['status'] === 'BUKA'): ?>
                                    <span class="badge" style="background: var(--warn-bg); color: var(--warn-fg); font-weight: 700;">Dibuka kembali</span>
                                <?php else: ?>
                                    <span class="badge" style="background: var(--ok-bg); color: var(--ok-fg); font-weight: 700;">Terbuka</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;"><?= $p['transaksi'] ?></td>
                            <td style="text-align: right;"><?= $rp($r['penjualan']) ?></td>
                            <td style="text-align: right;"><?= $rp($r['hpp']) ?></td>
                            <td style="text-align: right;"><?= $rp($r['biaya']) ?></td>
                            <td style="text-align: right; font-weight: 700; color: <?= $r['laba'] < 0 ? '#b91c1c' : 'inherit' ?>;"><?= $rp($r['laba']) ?></td>
                            <td style="font-size: 12.5px; max-width: 280px;">
                                <?php if ($tutup && $b): ?>
                                    Ditutup <?= date('d/m/Y H:i', strtotime($b['ditutup_at'])) ?>
                                    <?php if ($b['catatan']): ?><div style="color: var(--text-muted);"><?= htmlspecialchars($b['catatan']) ?></div><?php endif; ?>
                                <?php elseif ($p['status'] === 'BUKA' && $b): ?>
                                    Dibuka <?= date('d/m/Y H:i', strtotime($b['dibuka_at'])) ?>
                                    <div style="color: var(--text-muted);">Alasan: <?= htmlspecialchars((string)$b['alasan_buka']) ?></div>
                                <?php elseif ($p['tanggal'] === date('Y-m-d')): ?>
                                    <span style="color: var(--text-muted);">Hari ini</span>
                                <?php endif; ?>
                            </td>
                            <td class="no-print">
                                <?php if ($tutup && $bolehBuka): ?>
                                    <button type="button" class="btn btn-outline btn-sm" onclick='bukaHari(<?= json_encode($p['tanggal']) ?>, <?= json_encode($p['nama']) ?>)'>Buka kembali</button>
                                <?php elseif (!$tutup && $p['bisa_tutup'] && $bolehTutup): ?>
                                    <button type="button" class="btn btn-primary btn-sm" onclick='tutupHari(<?= json_encode($p['tanggal']) ?>)'>Tutup hari</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function esc(t) { return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;'); }
    function tglId(iso) { var p = iso.split('-'); return p[2] + '/' + p[1] + '/' + p[0]; }

    async function kirimTutup(action, data) {
        const fd = new FormData();
        Object.keys(data).forEach(k => fd.append(k, data[k]));
        try {
            const resp = await fetch('index.php?action=' + action, { method: 'POST', body: fd });
            const res = await resp.json();
            if (res.success) {
                await Swal.fire({ icon: 'success', title: 'Berhasil', text: res.message });
                window.location.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Tidak dapat diproses', text: res.message });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Kesalahan jaringan', text: 'Coba lagi beberapa saat.' });
        }
    }

    async function tutupHari(tanggal) {
        let info;
        try {
            const resp = await fetch('index.php?action=cek_periode&tanggal=' + encodeURIComponent(tanggal));
            info = await resp.json();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Kesalahan jaringan', text: 'Coba lagi beberapa saat.' });
            return;
        }
        if (!info || !info.success) {
            Swal.fire({ icon: 'error', title: 'Gagal memeriksa', text: (info && info.message) || '' });
            return;
        }
        const ikon = { ok: '&#10003;', peringatan: '!', blok: '&#10005;' };
        const warna = { ok: '#15803d', peringatan: '#b45309', blok: '#b91c1c' };
        const adaBlok = info.cek.some(c => c[0] === 'blok');
        const adaPeringatan = info.cek.some(c => c[0] === 'peringatan');
        const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');
        let html = '<div style="text-align:left;font-size:13px;">';
        html += '<div style="margin-bottom:10px;">Laba bersih ' + esc(info.nama) + ': <strong>' + rp(info.ringkas.laba) + '</strong> (penjualan ' + rp(info.ringkas.penjualan) + ')</div>';
        info.cek.forEach(c => {
            html += '<div style="display:flex;gap:8px;margin:5px 0;color:' + warna[c[0]] + ';"><span style="font-weight:800;width:14px;">' + ikon[c[0]] + '</span><span>' + esc(c[1]) + '</span></div>';
        });
        if (adaPeringatan && !adaBlok) html += '<div style="margin-top:10px;font-size:12px;color:var(--text-muted);">Peringatan tidak menghalangi penutupan, tetapi sebaiknya dibereskan lebih dulu.</div>';
        html += '</div>';

        const r = await Swal.fire({
            title: 'Tutup ' + info.nama + '?',
            html: html,
            input: adaBlok ? undefined : 'text',
            inputPlaceholder: 'Catatan (opsional)',
            showCancelButton: true,
            showConfirmButton: !adaBlok,
            confirmButtonText: 'Tutup Hari',
            cancelButtonText: adaBlok ? 'Kembali' : 'Batal',
            width: 560
        });
        if (r.isConfirmed) kirimTutup('tutup_periode', { tanggal: tanggal, catatan: r.value || '' });
    }

    async function tutupSampai(tanggal) {
        const r = await Swal.fire({
            title: 'Tutup semua hari sampai ' + tglId(tanggal) + '?',
            html: '<div style="text-align:left;font-size:13px;color:var(--text-muted);">Semua hari yang masih terbuka dari transaksi pertama sampai tanggal itu ditutup sekaligus. Hari yang jurnalnya tidak seimbang dilewati. Pastikan setoran dan rekonsiliasi hari-hari itu sudah beres.</div>',
            input: 'text',
            inputPlaceholder: 'Catatan (opsional)',
            showCancelButton: true,
            confirmButtonText: 'Tutup Semua',
            cancelButtonText: 'Batal'
        });
        if (r.isConfirmed) kirimTutup('tutup_sampai', { tanggal: tanggal, catatan: r.value || '' });
    }

    async function bukaHari(tanggal, nama) {
        const r = await Swal.fire({
            title: 'Buka kembali ' + nama + '?',
            html: '<div style="text-align:left;font-size:13px;color:var(--text-muted);">Transaksi dan pembatalan bertanggal hari ini akan diizinkan lagi. Angka hari ini bisa berubah. Tutup kembali setelah koreksi selesai.</div>',
            input: 'textarea',
            inputLabel: 'Alasan membuka kembali',
            showCancelButton: true,
            confirmButtonText: 'Buka Kembali',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc2626',
            preConfirm: v => {
                if (!v || v.trim().length < 3) { Swal.showValidationMessage('Alasan wajib diisi'); return false; }
                return v.trim();
            }
        });
        if (r.isConfirmed) kirimTutup('buka_periode', { tanggal: tanggal, alasan: r.value });
    }
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
