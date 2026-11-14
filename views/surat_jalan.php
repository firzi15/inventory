<?php
/**
 * Surat Jalan: daftar surat jalan pengeluaran (ke mitra) dan penerimaan (dari pemasok).
 * Cetak Form = form rangkap 3 tanpa harga (satu kali cetak tembus semua lapis).
 * Versi Internal = versi kita dengan harga dan piutang, hanya untuk Admin / Supervisor / Finance.
 */

use App\Core\Helper;
use App\Models\Mitra;
use App\Models\Pemasok;
use App\Services\SuratJalanService;

$pageTitle = 'Surat Jalan';

$tab    = ($_GET['tab'] ?? 'pengeluaran') === 'penerimaan' ? 'penerimaan' : 'pengeluaran';
$dari   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dari'] ?? '') ? $_GET['dari'] : date('Y-m-01');
$sampai = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sampai'] ?? '') ? $_GET['sampai'] : date('Y-m-d');
$cari   = trim($_GET['cari'] ?? '');
$status = in_array($_GET['status'] ?? '', ['AKTIF', 'BATAL'], true) ? $_GET['status'] : '';
$pihakId = (int)($_GET['pihak'] ?? 0);
$tampil = ($_GET['tampil'] ?? 'tabel') === 'grid' ? 'grid' : 'tabel';

$role = strtoupper($_SESSION['user_login']['role'] ?? '');
$bolehInternal = in_array($role, ['ADMIN', 'SUPERVISOR', 'FINANCE'], true);

$f = ['dari' => $dari, 'sampai' => $sampai, 'cari' => $cari, 'status' => $status];
if ($tab === 'pengeluaran') {
    $f['mitra_id'] = $pihakId;
    $rows = SuratJalanService::pengeluaran($f);
    $daftarPihak = Mitra::getAll();
    $labelPihak = 'Mitra';
} else {
    $f['pemasok_id'] = $pihakId;
    $rows = SuratJalanService::penerimaan($f);
    $daftarPihak = Pemasok::getAll();
    $labelPihak = 'Pemasok';
}

$rp = fn($n) => Helper::formatRupiah($n);
$tgl = fn($d) => date('d/m/Y H:i', strtotime($d));
$qs = fn(array $x) => http_build_query(array_merge(['page' => 'surat_jalan', 'dari' => $dari, 'sampai' => $sampai, 'tampil' => $tampil], $x));

// Satu baris data untuk tabel dan kartu
$baris = function (array $r) use ($tab) {
    if ($tab === 'pengeluaran') {
        return [
            'batal' => $r['jenis_transaksi'] === 'PENGELUARAN_BATAL',
            'no' => $r['no_sj'], 'pihak' => trim(($r['kode_mitra'] ?? '') . ' ' . ($r['nama_mitra'] ?? '')),
            'tgl' => $r['created_at'], 'box' => (int)$r['total_label'], 'kg' => (float)$r['total_kg'], 'nilai' => (float)$r['total_nilai_jual'],
            'alasan' => $r['alasan_batal'] ?? '',
            'urlForm' => 'index.php?page=surat_jalan_print&id=' . (int)$r['id'] . '&mode=form',
            'urlInternal' => 'index.php?page=surat_jalan_print&id=' . (int)$r['id'] . '&mode=internal',
        ];
    }
    return [
        'batal' => (bool)$r['batal'],
        'no' => $r['no_surat_jalan'], 'pihak' => trim($r['kode_pemasok'] . ' ' . $r['nama_pemasok']),
        'tgl' => $r['tanggal'], 'box' => (int)$r['box'], 'kg' => (float)$r['kg'], 'nilai' => (float)$r['nilai'],
        'alasan' => '',
        'urlForm' => 'index.php?page=surat_jalan_penerimaan_print&no_surat_jalan=' . urlencode($r['no_surat_jalan']),
        'urlInternal' => '',
    ];
};

require_once __DIR__ . '/layout/header.php';
?>

<div class="content-wrapper">

    <div class="page-header no-print">
        <div>
            <h1 class="page-title">Surat Jalan</h1>
            <p class="page-subtitle">Cetak atau cetak ulang surat jalan. Form rangkap 3 tanpa harga; versi internal dengan harga untuk arsip kita.</p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <?php $eksporTabel = 'tableSuratJalan'; $eksporNama = 'Surat_Jalan_' . ucfirst($tab); $eksporJudul = 'Surat Jalan ' . ucfirst($tab) . ' ' . date('d/m/Y', strtotime($dari)) . ' s/d ' . date('d/m/Y', strtotime($sampai)); include __DIR__ . '/layout/ekspor.php'; ?>
        </div>
    </div>

    <div class="no-print" style="display: flex; gap: 6px; margin-bottom: 14px; justify-content: space-between; flex-wrap: wrap;">
        <div style="display: flex; gap: 6px;">
            <a href="index.php?<?= $qs(['tab' => 'pengeluaran']) ?>" class="btn <?= $tab === 'pengeluaran' ? 'btn-primary' : 'btn-outline' ?>">Pengeluaran ke Mitra</a>
            <a href="index.php?<?= $qs(['tab' => 'penerimaan']) ?>" class="btn <?= $tab === 'penerimaan' ? 'btn-primary' : 'btn-outline' ?>">Penerimaan dari Pemasok</a>
        </div>
        <div style="display: flex; gap: 6px;" role="group" aria-label="Tampilan">
            <a href="index.php?<?= $qs(['tab' => $tab, 'tampil' => 'tabel']) ?>" class="btn <?= $tampil === 'tabel' ? 'btn-primary' : 'btn-outline' ?>" onclick="try{localStorage.setItem('sjTampil','tabel')}catch(e){}" style="display: inline-flex; align-items: center; gap: 6px;">
                <i data-lucide="table" style="width: 15px; height: 15px;"></i><span>Tabel</span>
            </a>
            <a href="index.php?<?= $qs(['tab' => $tab, 'tampil' => 'grid']) ?>" class="btn <?= $tampil === 'grid' ? 'btn-primary' : 'btn-outline' ?>" onclick="try{localStorage.setItem('sjTampil','grid')}catch(e){}" style="display: inline-flex; align-items: center; gap: 6px;">
                <i data-lucide="layout-grid" style="width: 15px; height: 15px;"></i><span>Grid</span>
            </a>
        </div>
    </div>

    <form method="GET" class="card no-print" style="padding: 14px 16px; margin-bottom: 18px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="page" value="surat_jalan">
        <input type="hidden" name="tab" value="<?= $tab ?>">
        <input type="hidden" name="tampil" value="<?= $tampil ?>">
        <div>
            <label class="form-label">Dari</label>
            <input type="date" name="dari" value="<?= $dari ?>" class="form-control">
        </div>
        <div>
            <label class="form-label">Sampai</label>
            <input type="date" name="sampai" value="<?= $sampai ?>" class="form-control">
        </div>
        <div style="min-width: 200px;">
            <label class="form-label"><?= $labelPihak ?></label>
            <select name="pihak" class="form-control">
                <option value="0">Semua <?= strtolower($labelPihak) ?></option>
                <?php foreach ($daftarPihak as $x):
                    $nama = $tab === 'pengeluaran' ? ($x['nama_mitra'] ?? '') : ($x['nama_pemasok'] ?? '');
                    $kode = $tab === 'pengeluaran' ? ($x['kode_mitra'] ?? '') : ($x['kode_pemasok'] ?? ''); ?>
                    <option value="<?= (int)$x['id'] ?>" <?= $pihakId === (int)$x['id'] ? 'selected' : '' ?>><?= htmlspecialchars($kode . ' - ' . $nama) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="min-width: 150px;">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">Semua</option>
                <option value="AKTIF" <?= $status === 'AKTIF' ? 'selected' : '' ?>>Berlaku</option>
                <option value="BATAL" <?= $status === 'BATAL' ? 'selected' : '' ?>>Dibatalkan</option>
            </select>
        </div>
        <div style="flex: 1; min-width: 180px;">
            <label class="form-label">Cari</label>
            <input type="text" name="cari" value="<?= htmlspecialchars($cari) ?>" class="form-control" placeholder="No. surat jalan atau nama">
        </div>
        <button type="submit" class="btn btn-primary">Tampilkan</button>
    </form>

    <?php if ($tampil === 'grid'): ?>
    <style>
        .sj-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 14px; margin-bottom: 18px; }
        .sj-kartu { background: var(--bg-card); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; }
        .sj-kartu.batal { opacity: .6; }
        .sj-kartu-atas { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
        .sj-no { font-family: 'JetBrains Mono', monospace; font-weight: 800; font-size: 13px; word-break: break-all; }
        .sj-pihak { font-weight: 700; font-size: 14px; }
        .sj-muted { color: var(--text-muted); font-size: 12px; }
        .sj-stat { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); padding: 8px 0; }
        .sj-stat div { font-size: 11px; color: var(--text-muted); }
        .sj-stat strong { display: block; font-size: 14px; color: var(--text-main); margin-top: 1px; }
        .sj-aksi { display: flex; gap: 6px; flex-wrap: wrap; margin-top: auto; }
    </style>
    <div class="sj-grid">
        <?php if (!$rows): ?>
            <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 30px; color: var(--text-muted);">Tidak ada surat jalan pada filter ini</div>
        <?php endif; ?>
        <?php foreach ($rows as $r): $x = $baris($r); ?>
            <div class="sj-kartu <?= $x['batal'] ? 'batal' : '' ?>">
                <div class="sj-kartu-atas">
                    <div class="sj-no"><?= htmlspecialchars($x['no']) ?></div>
                    <span class="badge" style="<?= $x['batal'] ? 'background: #fee2e2; color: #b91c1c;' : 'background: #dcfce7; color: #15803d;' ?> font-weight: 700; white-space: nowrap;"><?= $x['batal'] ? 'Dibatalkan' : 'Berlaku' ?></span>
                </div>
                <div>
                    <div class="sj-pihak"><?= htmlspecialchars($x['pihak']) ?></div>
                    <div class="sj-muted"><?= $tgl($x['tgl']) ?></div>
                    <?php if ($x['batal'] && $x['alasan']): ?><div class="sj-muted" style="color: #b91c1c;">Batal: <?= htmlspecialchars($x['alasan']) ?></div><?php endif; ?>
                </div>
                <div class="sj-stat" style="<?= $bolehInternal ? '' : 'grid-template-columns: repeat(2, 1fr);' ?>">
                    <div>Box<strong><?= $x['box'] ?></strong></div>
                    <div>Netto<strong><?= Helper::formatKg($x['kg']) ?></strong></div>
                    <?php if ($bolehInternal): ?><div><?= $tab === 'pengeluaran' ? 'Nilai jual' : 'Nilai beli' ?><strong><?= $rp($x['nilai']) ?></strong></div><?php endif; ?>
                </div>
                <div class="sj-aksi no-print">
                    <?php if (!$x['batal']): ?>
                        <a href="<?= $x['urlForm'] ?>" target="_blank" class="btn btn-primary btn-sm">Cetak Form<?= $tab === 'pengeluaran' ? ' (rangkap 3)' : '' ?></a>
                        <?php if ($bolehInternal && $x['urlInternal']): ?>
                            <a href="<?= $x['urlInternal'] ?>" target="_blank" class="btn btn-outline btn-sm">Versi Internal</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="sj-muted">Tidak dapat dicetak</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="card" style="<?= $tampil === 'grid' ? 'display: none;' : '' ?>">
        <div class="table-responsive">
            <table class="table-custom" id="tableSuratJalan" data-no-tools>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>No. Surat Jalan</th>
                        <th><?= $labelPihak ?></th>
                        <th style="text-align: right;">Box</th>
                        <th style="text-align: right;">Netto (kg)</th>
                        <?php if ($bolehInternal): ?><th style="text-align: right;"><?= $tab === 'pengeluaran' ? 'Nilai Jual' : 'Nilai Beli' ?></th><?php endif; ?>
                        <th>Status</th>
                        <th class="no-print" style="width: 260px;">Cetak</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted);">Tidak ada surat jalan pada filter ini</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r):
                        $x = $baris($r);
                        $batal = $x['batal']; $no = $x['no']; $pihak = $x['pihak']; $tglDok = $x['tgl']; $box = $x['box']; $kg = $x['kg']; $nilai = $x['nilai'];
                        $urlForm = $x['urlForm']; $urlInternal = $x['urlInternal']; ?>
                        <tr style="<?= $batal ? 'opacity: .6;' : '' ?>">
                            <td><?= $tgl($tglDok) ?></td>
                            <td style="font-family: 'JetBrains Mono', monospace; font-weight: 700;"><?= htmlspecialchars($no) ?></td>
                            <td><?= htmlspecialchars($pihak) ?></td>
                            <td style="text-align: right;"><?= $box ?></td>
                            <td style="text-align: right;"><?= Helper::formatKg($kg) ?></td>
                            <?php if ($bolehInternal): ?><td style="text-align: right;"><?= $rp($nilai) ?></td><?php endif; ?>
                            <td>
                                <span class="badge" style="<?= $batal ? 'background: #fee2e2; color: #b91c1c;' : 'background: #dcfce7; color: #15803d;' ?> font-weight: 700;"><?= $batal ? 'Dibatalkan' : 'Berlaku' ?></span>
                                <?php if ($batal && $tab === 'pengeluaran' && $r['alasan_batal']): ?><div style="font-size: 11.5px; color: var(--text-muted);"><?= htmlspecialchars($r['alasan_batal']) ?></div><?php endif; ?>
                            </td>
                            <td class="no-print">
                                <?php if (!$batal): ?>
                                    <a href="<?= $urlForm ?>" target="_blank" class="btn btn-outline btn-sm">Cetak Form<?= $tab === 'pengeluaran' ? ' (rangkap 3)' : '' ?></a>
                                    <?php if ($bolehInternal && $urlInternal): ?>
                                        <a href="<?= $urlInternal ?>" target="_blank" class="btn btn-outline btn-sm">Versi Internal</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($rows) >= SuratJalanService::batas()): ?>
            <div style="padding: 10px 16px; font-size: 12.5px; color: #b45309;">Hanya <?= SuratJalanService::batas() ?> surat jalan terbaru yang ditampilkan. Persempit rentang tanggal untuk melihat yang lain.</div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Ingat pilihan tampilan (tabel / grid) di browser ini
    (function () {
        try {
            if (location.search.indexOf('tampil=') === -1 && localStorage.getItem('sjTampil') === 'grid') {
                location.replace(location.href + (location.search ? '&' : '?') + 'tampil=grid');
            }
        } catch (e) {}
    })();
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
