<?php
/**
 * Reset data aktivitas untuk masa uji coba. Tidak ada di menu; dibuka langsung lewat /reset.php dan dilindungi kata sandi sendiri.
 * Menghapus transaksi dan catatan hasil pemakaian; data master tidak disentuh. Backup pengaman dibuat otomatis lebih dulu.
 * Hapus berkas ini (atau set KosongkanDataService::AKTIF = false) saat aplikasi sudah dipakai sungguhan.
 */

session_start();

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $file = __DIR__ . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require_once $file;
});

use App\Services\KosongkanDataService as KD;

// Hash dari kata sandi (bukan kata sandi aslinya)
const HASH_SANDI = '$2y$12$HP7UMtYchjpfZZrV7BWQGukjqYoyBkD.BCl26OS6H3ULzWoDF/N/O';
const BATAS_SALAH = 5;
const KUNCI_MENIT = 10;

function keluarJson(array $d, int $kode = 200): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($kode);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

if (!KD::AKTIF) {
    http_response_code(404);
    exit('Tidak tersedia.');
}

if (empty($_SESSION['reset_csrf'])) $_SESSION['reset_csrf'] = bin2hex(random_bytes(16));
$sudahMasuk = !empty($_SESSION['reset_ok']) && $_SESSION['reset_ok'] > time() - 3600;
$galatMasuk = '';

// Keluar
if (($_GET['keluar'] ?? '') === '1') {
    unset($_SESSION['reset_ok']);
    header('Location: reset.php');
    exit;
}

// Masuk dengan kata sandi
if (!$sudahMasuk && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['sandi'])) {
    $kunciSampai = (int)($_SESSION['reset_kunci'] ?? 0);
    if ($kunciSampai > time()) {
        $galatMasuk = 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.';
    } elseif (password_verify((string)$_POST['sandi'], HASH_SANDI)) {
        session_regenerate_id(true);
        $_SESSION['reset_ok'] = time();
        $_SESSION['reset_salah'] = 0;
        if (empty($_SESSION['reset_csrf'])) $_SESSION['reset_csrf'] = bin2hex(random_bytes(16));
        header('Location: reset.php');
        exit;
    } else {
        sleep(1);
        $_SESSION['reset_salah'] = (int)($_SESSION['reset_salah'] ?? 0) + 1;
        if ($_SESSION['reset_salah'] >= BATAS_SALAH) {
            $_SESSION['reset_kunci'] = time() + KUNCI_MENIT * 60;
            $_SESSION['reset_salah'] = 0;
            $galatMasuk = 'Terlalu banyak percobaan. Dikunci ' . KUNCI_MENIT . ' menit.';
        } else {
            $galatMasuk = 'Kata sandi salah.';
        }
    }
}

// Aksi kosongkan (hanya setelah masuk)
if ($sudahMasuk && ($_GET['aksi'] ?? '') === 'kosongkan') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') keluarJson(['success' => false, 'message' => 'Metode harus POST.'], 405);
    if (!hash_equals($_SESSION['reset_csrf'], (string)($_POST['csrf'] ?? ''))) keluarJson(['success' => false, 'message' => 'Sesi tidak valid, muat ulang halaman.'], 403);
    if (trim((string)($_POST['konfirmasi'] ?? '')) !== 'KOSONGKAN') keluarJson(['success' => false, 'message' => 'Konfirmasi tidak sesuai. Ketik KOSONGKAN untuk melanjutkan.']);
    try {
        $hasil = KD::kosongkan(0, ($_POST['hapus_audit'] ?? '0') === '1', ($_POST['hapus_upload'] ?? '1') === '1');
        keluarJson(['success' => true, 'message' => $hasil['pesan']]);
    } catch (Throwable $e) {
        keluarJson(['success' => false, 'message' => $e->getMessage()]);
    }
}

$galat = null;
$r = ['grup' => [], 'master' => [], 'audit' => 0, 'upload' => 0];
if ($sudahMasuk) {
    try { $r = KD::ringkas(); } catch (Throwable $e) { $galat = $e->getMessage(); }
}
$totalAktivitas = array_sum(array_column($r['grup'], 'total'));
$v = @filemtime(__DIR__ . '/assets/css/style.css') ?: 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <script>try { var t = localStorage.getItem('theme'); if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t); } catch (e) {}</script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Reset Data Aktivitas</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= $v ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { padding: 24px 16px; }
        .wadah { max-width: 1000px; margin: 0 auto; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start; }
        @media (max-width: 820px) { .grid-2 { grid-template-columns: 1fr; } }
        .atas { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; gap: 12px; flex-wrap: wrap; }
        .kotak-masuk { max-width: 380px; margin: 12vh auto 0; }
        .rinci { font-size: 11px; color: var(--text-muted); word-break: break-word; }
    </style>
</head>
<body>
<div class="wadah">
<?php if (!$sudahMasuk): ?>
    <form method="POST" class="card kotak-masuk" style="padding: 22px;" autocomplete="off">
        <h1 class="page-title" style="font-size: 18px; margin-bottom: 4px;">Reset Data Aktivitas</h1>
        <p class="page-subtitle" style="margin-bottom: 14px;">Halaman khusus uji coba. Masukkan kata sandi.</p>
        <?php if ($galatMasuk): ?><div style="color: var(--bad-fg); font-size: 13px; margin-bottom: 10px;"><?= h($galatMasuk) ?></div><?php endif; ?>
        <input type="password" name="sandi" class="form-control" placeholder="Kata sandi" autofocus required style="margin-bottom: 12px;">
        <button type="submit" class="btn btn-primary" style="width: 100%;">Masuk</button>
    </form>
<?php else: ?>
    <div class="atas">
        <div>
            <h1 class="page-title">Reset Data Aktivitas</h1>
            <p class="page-subtitle">Untuk masa uji coba: hapus semua transaksi dan mulai lagi dari nol. Data master tidak disentuh.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="index.php" class="btn btn-outline">Ke aplikasi</a>
            <a href="reset.php?keluar=1" class="btn btn-outline">Keluar</a>
        </div>
    </div>

    <?php if ($galat): ?>
        <div class="card" style="padding: 14px 18px; margin-bottom: 18px; border-left: 3px solid var(--bad-fg); color: var(--bad-fg);"><?= h($galat) ?></div>
    <?php endif; ?>

    <div class="card" style="padding: 14px 18px; margin-bottom: 18px; font-size: 13px; line-height: 1.6; border-left: 3px solid var(--bad-fg);"><div>
        <strong style="color: var(--bad-fg);">Penghapusan ini tidak bisa dibatalkan dari layar ini.</strong>
        Sebelum menghapus, sistem membuat backup pengaman otomatis, jadi data tetap bisa dipulihkan lewat Pengaturan &gt; Backup di aplikasi.
        Nomor surat jalan, nomor transaksi, dan nomor bukti kembali mulai dari awal. Pastikan tidak ada pengguna lain yang sedang bekerja.
        <br>Saat aplikasi sudah dipakai sungguhan, hapus berkas <code>reset.php</code> atau ubah <code>KosongkanDataService::AKTIF</code> menjadi <code>false</code>.
    </div></div>

    <div class="grid-2">
        <div class="card">
            <div class="card-header"><div><h2 class="card-title"><span>Yang akan dihapus</span></h2>
                <div class="card-description"><?= number_format($totalAktivitas, 0, ',', '.') ?> baris data aktivitas</div></div></div>
            <div class="table-responsive">
                <table class="table-custom" data-no-tools>
                    <thead><tr><th>Kelompok</th><th style="text-align: right;">Baris</th></tr></thead>
                    <tbody>
                        <?php foreach ($r['grup'] as $g): ?>
                        <tr>
                            <td style="white-space: normal;"><strong><?= h($g['label']) ?></strong>
                                <div class="rinci"><?= h(implode(', ', array_keys($g['tabel']))) ?></div></td>
                            <td style="text-align: right;"><?= number_format($g['total'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="padding: 12px 0 4px; display: flex; flex-direction: column; gap: 8px; font-size: 13px;">
                <label style="display: flex; gap: 8px; align-items: flex-start;">
                    <input type="checkbox" id="opsiUpload" checked style="margin-top: 3px;">
                    <span>Hapus juga file bukti setoran dan screenshot <span style="color: var(--text-muted);">(<?= (int)$r['upload'] ?> file)</span></span>
                </label>
                <label style="display: flex; gap: 8px; align-items: flex-start;">
                    <input type="checkbox" id="opsiAudit" style="margin-top: 3px;">
                    <span>Hapus juga log audit <span style="color: var(--text-muted);">(<?= (int)$r['audit'] ?> baris; sebaiknya dibiarkan agar jejak backup dan pemulihan tetap ada)</span></span>
                </label>
            </div>
            <div style="padding: 10px 0 4px;">
                <button type="button" class="btn btn-primary" style="background: var(--bad-fg);" onclick="kosongkan()" <?= $galat ? 'disabled' : '' ?>>Kosongkan Data Aktivitas</button>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h2 class="card-title"><span>Yang tidak disentuh (master)</span></h2>
                <div class="card-description">Tetap utuh setelah dikosongkan</div></div></div>
            <div class="table-responsive">
                <table class="table-custom" data-no-tools>
                    <thead><tr><th>Data master</th><th style="text-align: right;">Baris</th></tr></thead>
                    <tbody>
                        <?php foreach ($r['master'] as $m): ?>
                        <tr><td><?= h($m['label']) ?></td><td style="text-align: right;"><?= number_format($m['jumlah'], 0, ',', '.') ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="padding: 10px 0 4px; font-size: 12px; color: var(--text-muted);">Saldo berjalan rekening bank mitra ikut dikembalikan ke 0 karena berasal dari transaksi.</div>
        </div>
    </div>

    <script>
        async function kosongkan() {
            const r = await Swal.fire({
                icon: 'warning',
                title: 'Kosongkan semua data aktivitas?',
                html: '<div style="text-align:left;font-size:13px;line-height:1.6;">' +
                      'Penerimaan, label barcode, pengeluaran, faktur, pembayaran, kas, bank, jurnal, dan tutup harian akan dihapus. Data master tetap.<br>' +
                      'Backup pengaman dibuat otomatis lebih dulu.<br><br>Ketik <strong>KOSONGKAN</strong> untuk melanjutkan.</div>',
                input: 'text',
                inputPlaceholder: 'KOSONGKAN',
                showCancelButton: true,
                confirmButtonText: 'Kosongkan Sekarang',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#b91c1c',
                preConfirm: v => {
                    if ((v || '').trim() !== 'KOSONGKAN') { Swal.showValidationMessage('Ketik KOSONGKAN persis seperti itu'); return false; }
                    return v.trim();
                }
            });
            if (!r.isConfirmed) return;

            const fd = new FormData();
            fd.append('konfirmasi', r.value);
            fd.append('csrf', <?= json_encode($_SESSION['reset_csrf']) ?>);
            fd.append('hapus_upload', document.getElementById('opsiUpload').checked ? '1' : '0');
            fd.append('hapus_audit', document.getElementById('opsiAudit').checked ? '1' : '0');
            Swal.fire({ title: 'Mengosongkan data...', text: 'Membuat backup pengaman lalu menghapus. Jangan tutup halaman ini.', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            try {
                const resp = await fetch('reset.php?aksi=kosongkan', { method: 'POST', body: fd });
                const res = await resp.json();
                if (res.success) {
                    await Swal.fire({ icon: 'success', title: 'Selesai', text: res.message });
                    window.location.reload();
                } else {
                    Swal.fire({ icon: 'error', title: 'Tidak dapat diproses', text: res.message });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Kesalahan jaringan', text: 'Coba lagi beberapa saat.' });
            }
        }
    </script>
<?php endif; ?>
</div>
</body>
</html>
