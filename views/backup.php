<?php
/**
 * Backup Data.
 * Satu backup = database + folder assets/uploads (bukti setoran). Kode ada di git, jadi tidak ikut.
 */

use App\Services\BackupService;

$pageTitle = 'Backup Data';

if (!BackupService::boleh($_SESSION['user_login']['role'] ?? '')) {
    require_once __DIR__ . '/layout/header.php';
    echo '<div class="content-wrapper"><div class="card" style="padding: 24px;">Anda tidak memiliki izin membuka halaman ini.</div></div>';
    require_once __DIR__ . '/layout/footer.php';
    return;
}

$galat = null;
$daftar = [];
$lokasi = '';
try {
    $lokasi = BackupService::folder();
    $daftar = BackupService::daftar();
} catch (Throwable $e) {
    $galat = $e->getMessage();
}
$terakhir = $daftar[0]['waktu'] ?? null;
$totalUkuran = array_sum(array_column($daftar, 'ukuran'));
$hariIni = $terakhir !== null && date('Y-m-d', $terakhir) === date('Y-m-d');
$umurJam = $terakhir !== null ? (int)floor((time() - $terakhir) / 3600) : null;
$bolehPulih = BackupService::bolehPulih($_SESSION['user_login']['role'] ?? '');
$autoInfo = BackupService::infoOtomatis();
$adaBulanIni = (bool)array_filter($daftar, fn($b) => substr($b['nama'], 7, 6) === date('Ym'));
$namaJenis = ['manual' => 'Manual', 'auto' => 'Otomatis', 'sebelumpulih' => 'Pengaman'];

require_once __DIR__ . '/layout/header.php';
?>

<div class="content-wrapper">

    <div class="page-header no-print">
        <div>
            <h1 class="page-title">Backup Data</h1>
            <p class="page-subtitle">Salinan database dan bukti setoran. Simpan satu salinan di luar komputer ini.</p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <?php if ($bolehPulih): ?>
            <input type="file" id="berkasUnggah" accept=".zip,.gz,.tar.gz" style="display: none;" onchange="unggahBackup(this)">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('berkasUnggah').click()" <?= $galat ? 'disabled' : '' ?>>
                <i data-lucide="upload" style="width: 16px; height: 16px;"></i><span>Unggah Backup</span>
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-primary" id="btnBackup" onclick="buatBackup()" <?= $galat ? 'disabled' : '' ?>>
                <i data-lucide="database-backup" style="width: 16px; height: 16px;"></i><span>Buat Backup Sekarang</span>
            </button>
        </div>
    </div>

    <?php if ($galat): ?>
        <div class="card" style="padding: 14px 18px; margin-bottom: 18px; border-left: 3px solid var(--bad-fg); color: var(--bad-fg);"><?= htmlspecialchars($galat) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-3 no-print" style="gap: 16px; margin-bottom: 20px;">
        <div class="kpi-card"><div class="kpi-body">
            <div class="kpi-label">Backup Terakhir</div>
            <div class="kpi-value" style="font-size: 18px;"><?= $terakhir ? date('d/m/Y H:i', $terakhir) : 'Belum ada' ?></div>
            <div class="kpi-sub" style="color: <?= $hariIni ? 'var(--ok-fg)' : 'var(--warn-fg)' ?>;">
                <?= $terakhir === null ? 'Buat backup pertama sekarang' : ($hariIni ? 'Sudah ada backup hari ini' : 'Belum ada backup hari ini (' . ($umurJam >= 48 ? floor($umurJam / 24) . ' hari' : $umurJam . ' jam') . ' lalu)') ?>
            </div>
        </div></div>
        <div class="kpi-card"><div class="kpi-body">
            <div class="kpi-label">Jumlah Backup</div>
            <div class="kpi-value"><?= count($daftar) ?></div>
            <div class="kpi-sub">disimpan <?= BackupService::SIMPAN_TERAKHIR ?> terakhir, yang lebih lama dihapus otomatis</div>
        </div></div>
        <div class="kpi-card"><div class="kpi-body">
            <div class="kpi-label">Total Ukuran</div>
            <div class="kpi-value"><?= BackupService::ukuranTeks((int)$totalUkuran) ?></div>
            <div class="kpi-sub" style="word-break: break-all;">folder: <?= htmlspecialchars($lokasi) ?></div>
        </div></div>
    </div>

    <?php if (($autoInfo['ok'] ?? true) === false): ?>
        <div class="card" style="padding: 14px 18px; margin-bottom: 18px; border-left: 3px solid var(--bad-fg); color: var(--bad-fg); font-size: 13px;">Backup otomatis terakhir gagal: <?= htmlspecialchars($autoInfo['pesan'] ?? '') ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="table-responsive">
            <table class="table-custom" id="tableBackup" data-no-tools>
                <thead>
                    <tr>
                        <th>Berkas</th>
                        <th>Jenis</th>
                        <th>Dibuat</th>
                        <th style="text-align: right;">Ukuran</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$daftar): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">Belum ada backup.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($daftar as $b): ?>
                    <tr>
                        <td style="font-family: 'JetBrains Mono', monospace; font-size: 12px;"><?= htmlspecialchars($b['nama']) ?></td>
                        <td><span class="badge"><?= $namaJenis[$b['jenis']] ?? 'Manual' ?></span></td>
                        <td><?= date('d/m/Y H:i', $b['waktu']) ?></td>
                        <td style="text-align: right;"><?= BackupService::ukuranTeks($b['ukuran']) ?></td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a class="btn btn-outline btn-sm" href="index.php?action=unduh_backup&nama=<?= urlencode($b['nama']) ?>">Unduh</a>
                            <?php if ($bolehPulih): ?>
                            <button type="button" class="btn btn-outline btn-sm" onclick='pulihkanBackup(<?= json_encode($b['nama']) ?>, <?= json_encode(date('d/m/Y H:i', $b['waktu'])) ?>)'>Pulihkan</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline btn-sm" style="color: var(--bad-fg);" onclick='hapusBackup(<?= json_encode($b['nama']) ?>)'>Hapus</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    async function kirimBackup(action, data, judulLoading) {
        const fd = new FormData();
        Object.keys(data).forEach(k => fd.append(k, data[k]));
        Swal.fire({ title: judulLoading, text: 'Mohon tunggu, jangan tutup halaman ini.', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
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

    async function unggahBackup(input) {
        if (!input.files.length) return;
        await kirimBackup('unggah_backup', { berkas: input.files[0] }, 'Mengunggah dan memeriksa backup...');
        input.value = '';
    }

    async function pulihkanBackup(nama, waktu) {
        const r = await Swal.fire({
            icon: 'warning',
            title: 'Pulihkan data dari backup ini?',
            html: '<div style="text-align:left;font-size:13px;line-height:1.6;">' +
                  '<div style="font-family:monospace;margin-bottom:8px;">' + nama + '<br>dibuat ' + waktu + '</div>' +
                  '<strong style="color:#b91c1c;">Semua transaksi sesudah waktu itu akan hilang</strong> dari database: stok, jurnal, utang, dan pengguna kembali seperti saat backup.<br>' +
                  'Sistem membuat backup pengaman otomatis dari kondisi sekarang sebelum menimpa.<br>' +
                  'Pastikan tidak ada pengguna lain yang sedang bekerja.<br><br>Ketik <strong>PULIHKAN</strong> untuk melanjutkan.</div>',
            input: 'text',
            inputPlaceholder: 'PULIHKAN',
            showCancelButton: true,
            confirmButtonText: 'Pulihkan Sekarang',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#b91c1c',
            preConfirm: v => {
                if ((v || '').trim() !== 'PULIHKAN') { Swal.showValidationMessage('Ketik PULIHKAN persis seperti itu'); return false; }
                return v.trim();
            }
        });
        if (r.isConfirmed) kirimBackup('pulihkan_backup', { nama: nama, konfirmasi: r.value }, 'Memulihkan data... jangan tutup halaman ini.');
    }

    function buatBackup() {
        kirimBackup('buat_backup', {}, 'Membuat backup...');
    }

    async function hapusBackup(nama) {
        const r = await Swal.fire({
            title: 'Hapus backup ini?',
            text: nama,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Hapus',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#b91c1c'
        });
        if (r.isConfirmed) kirimBackup('hapus_backup', { nama: nama }, 'Menghapus...');
    }
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
