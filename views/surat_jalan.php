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

// Urutan dan halaman
$kunciUrut = array_keys(SuratJalanService::URUT_KELUAR);
$urut = in_array($_GET['urut'] ?? '', $kunciUrut, true) ? $_GET['urut'] : 'tanggal';
$arah = ($_GET['arah'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$opsiPer = $tampil === 'grid' ? [12, 24, 48, 96] : [10, 25, 50, 100, 500];
$per = in_array((int)($_GET['per'] ?? 0), $opsiPer, true) ? (int)$_GET['per'] : ($tampil === 'grid' ? 12 : 25);
$hal = max(1, (int)($_GET['hal'] ?? 1));

$f = ['dari' => $dari, 'sampai' => $sampai, 'cari' => $cari, 'status' => $status, 'urut' => $urut, 'arah' => $arah, 'limit' => $per, 'offset' => ($hal - 1) * $per];
if ($tab === 'pengeluaran') {
    $f['mitra_id'] = $pihakId;
    $hasil = SuratJalanService::pengeluaran($f);
    $daftarPihak = Mitra::getAll();
    $labelPihak = 'Mitra';
} else {
    $f['pemasok_id'] = $pihakId;
    $hasil = SuratJalanService::penerimaan($f);
    $daftarPihak = Pemasok::getAll();
    $labelPihak = 'Pemasok';
}
$total = $hasil['total'];
$halMax = max(1, (int)ceil($total / $per));
if ($hal > $halMax) {
    // halaman di luar jangkauan (mis. filter dipersempit): muat ulang halaman terakhir
    $hal = $halMax;
    $f['offset'] = ($hal - 1) * $per;
    $hasil = $tab === 'pengeluaran' ? SuratJalanService::pengeluaran($f) : SuratJalanService::penerimaan($f);
}
$rows = $hasil['rows'];

$rp = fn($n) => Helper::formatRupiah($n);
$tgl = fn($d) => date('d/m/Y H:i', strtotime($d));
// Alamat halaman ini dengan semua pilihan saat ini; $x menimpa (nilai null = dibuang)
$qs = function (array $x) use ($tab, $dari, $sampai, $tampil, $pihakId, $status, $cari, $per, $urut, $arah, $hal) {
    $q = array_merge(['page' => 'surat_jalan', 'tab' => $tab, 'dari' => $dari, 'sampai' => $sampai, 'tampil' => $tampil,
                      'pihak' => $pihakId ?: null, 'status' => $status ?: null, 'cari' => $cari ?: null,
                      'per' => $per, 'urut' => $urut, 'arah' => $arah, 'hal' => $hal], $x);
    return http_build_query(array_filter($q, fn($v) => $v !== null && $v !== ''));
};

// Judul kolom yang bisa diurutkan (klik lagi untuk membalik arah)
$th = function (string $kunci, string $label, string $gaya = '') use ($qs, $urut, $arah) {
    $aktif = $urut === $kunci;
    $baru = ($aktif && $arah === 'asc') ? 'desc' : 'asc';
    echo '<th style="' . $gaya . '"><a class="sj-sort ' . ($aktif ? 'aktif ' . $arah : '') . '" href="index.php?' . $qs(['urut' => $kunci, 'arah' => $baru, 'hal' => 1]) . '"'
       . ' title="Urutkan menurut ' . htmlspecialchars($label, ENT_QUOTES) . '">' . htmlspecialchars($label) . '</a></th>';
};

// Bilah halaman: keterangan jumlah, pilihan baris per halaman, dan nomor halaman
$bilahHalaman = function () use ($qs, $total, $per, $hal, $halMax, $opsiPer) {
    $dari = $total ? ($hal - 1) * $per + 1 : 0;
    $sampai = min($total, $hal * $per);
    echo '<div class="sj-halaman no-print"><div class="sj-halaman-info">Menampilkan ' . $dari . '-' . $sampai . ' dari ' . $total . ' surat jalan</div>';
    echo '<div class="sj-halaman-nomor">';
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
    echo '</div><div class="sj-halaman-per"><label for="sjPer">Per halaman</label> <select id="sjPer" class="form-control" style="width: auto; display: inline-block;" onchange="location.href=this.value">';
    foreach ($opsiPer as $o) {
        echo '<option value="index.php?' . $qs(['per' => $o, 'hal' => 1]) . '"' . ($o === $per ? ' selected' : '') . '>' . $o . '</option>';
    }
    echo '</select></div></div>';
};

// Atribut data untuk tombol / thumbnail pratinjau
$attrPreview = function (array $x) use ($bolehInternal) {
    return 'data-form="' . htmlspecialchars($x['urlForm'] . '&embed=1', ENT_QUOTES) . '"'
         . ' data-internal="' . htmlspecialchars(($bolehInternal && $x['urlInternal']) ? $x['urlInternal'] . '&embed=1' : '', ENT_QUOTES) . '"'
         . ' data-judul="' . htmlspecialchars($x['no'] . ' - ' . $x['pihak'], ENT_QUOTES) . '"';
};

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
            <a href="index.php?<?= $qs(['tab' => 'pengeluaran', 'pihak' => null, 'hal' => 1]) ?>" class="btn <?= $tab === 'pengeluaran' ? 'btn-primary' : 'btn-outline' ?>">Pengeluaran ke Mitra</a>
            <a href="index.php?<?= $qs(['tab' => 'penerimaan', 'pihak' => null, 'hal' => 1]) ?>" class="btn <?= $tab === 'penerimaan' ? 'btn-primary' : 'btn-outline' ?>">Penerimaan dari Pemasok</a>
        </div>
        <div style="display: flex; gap: 6px;" role="group" aria-label="Tampilan">
            <a href="index.php?<?= $qs(['tampil' => 'tabel', 'per' => null, 'hal' => 1]) ?>" class="btn <?= $tampil === 'tabel' ? 'btn-primary' : 'btn-outline' ?>" onclick="try{localStorage.setItem('sjTampil','tabel')}catch(e){}" style="display: inline-flex; align-items: center; gap: 6px;">
                <i data-lucide="table" style="width: 15px; height: 15px;"></i><span>Tabel</span>
            </a>
            <a href="index.php?<?= $qs(['tampil' => 'grid', 'per' => null, 'hal' => 1]) ?>" class="btn <?= $tampil === 'grid' ? 'btn-primary' : 'btn-outline' ?>" onclick="try{localStorage.setItem('sjTampil','grid')}catch(e){}" style="display: inline-flex; align-items: center; gap: 6px;">
                <i data-lucide="layout-grid" style="width: 15px; height: 15px;"></i><span>Grid</span>
            </a>
        </div>
    </div>

    <form method="GET" class="card no-print" style="padding: 14px 16px; margin-bottom: 18px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="page" value="surat_jalan">
        <input type="hidden" name="tab" value="<?= $tab ?>">
        <input type="hidden" name="tampil" value="<?= $tampil ?>">
        <input type="hidden" name="per" value="<?= $per ?>">
        <?php if ($tampil === 'tabel'): ?>
            <input type="hidden" name="urut" value="<?= $urut ?>">
            <input type="hidden" name="arah" value="<?= $arah ?>">
        <?php endif; ?>
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
        <?php if ($tampil === 'grid'): ?>
        <div style="min-width: 160px;">
            <label class="form-label">Urutkan</label>
            <select name="urut" class="form-control">
                <?php foreach (['tanggal' => 'Tanggal', 'no' => 'No. surat jalan', 'pihak' => $labelPihak, 'box' => 'Jumlah box', 'kg' => 'Netto', 'nilai' => 'Nilai', 'status' => 'Status'] as $k => $lb): ?>
                    <option value="<?= $k ?>" <?= $urut === $k ? 'selected' : '' ?>><?= $lb ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="min-width: 130px;">
            <label class="form-label">Arah</label>
            <select name="arah" class="form-control">
                <option value="desc" <?= $arah === 'desc' ? 'selected' : '' ?>>Terbaru / terbesar</option>
                <option value="asc" <?= $arah === 'asc' ? 'selected' : '' ?>>Terlama / terkecil</option>
            </select>
        </div>
        <?php endif; ?>
        <div style="flex: 1; min-width: 180px;">
            <label class="form-label">Cari</label>
            <input type="text" name="cari" value="<?= htmlspecialchars($cari) ?>" class="form-control" placeholder="No. surat jalan atau nama">
        </div>
        <button type="submit" class="btn btn-primary">Tampilkan</button>
    </form>

    <style>
        .sj-sort { color: inherit; text-decoration: none; white-space: nowrap; cursor: pointer; }
        .sj-sort::after { content: '\2195'; opacity: .35; margin-left: 5px; font-size: 11px; }
        .sj-sort.aktif::after { opacity: 1; }
        .sj-sort.aktif.asc::after { content: '\25B2'; }
        .sj-sort.aktif.desc::after { content: '\25BC'; }
        .sj-halaman { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 4px; margin-bottom: 8px; }
        .sj-halaman-info { font-size: 12.5px; color: var(--text-muted); }
        .sj-halaman-nomor { display: flex; gap: 4px; align-items: center; }
        .sj-hal { min-width: 32px; height: 32px; padding: 0 8px; display: inline-flex; align-items: center; justify-content: center; border: 1.5px solid var(--border); border-radius: 6px; font-size: 13px; font-weight: 700; color: var(--text-main); text-decoration: none; background: var(--bg-card); }
        a.sj-hal:hover { border-color: var(--primary); }
        .sj-hal.aktif { background: var(--primary); border-color: var(--primary); color: #fff; }
        .sj-hal.mati { opacity: .45; cursor: default; }
        .sj-halaman-per { font-size: 12.5px; color: var(--text-muted); }
    </style>
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
        .sj-thumb { position: relative; width: 100%; aspect-ratio: 794 / 580; overflow: hidden; background: var(--bg-card); border: 1px solid var(--border); border-radius: 6px; cursor: zoom-in; }
        .sj-thumb iframe { position: absolute; left: 0; top: 0; width: 794px; height: 1123px; border: 0; transform-origin: 0 0; pointer-events: none; background: var(--bg-card); }
        .sj-thumb-kosong { display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 12px; background: var(--bg-muted); cursor: default; }
    </style>
    <div class="sj-grid">
        <?php if (!$rows): ?>
            <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 30px; color: var(--text-muted);">Tidak ada surat jalan pada filter ini</div>
        <?php endif; ?>
        <?php foreach ($rows as $r): $x = $baris($r); ?>
            <div class="sj-kartu <?= $x['batal'] ? 'batal' : '' ?>">
                <?php if (!$x['batal']): ?>
                    <div class="sj-thumb" title="Klik untuk pratinjau" data-src="<?= htmlspecialchars($x['urlForm'] . '&embed=1', ENT_QUOTES) ?>" onclick="bukaPreview(this)" <?= $attrPreview($x) ?>>
                        <iframe tabindex="-1" scrolling="no" aria-hidden="true"></iframe>
                    </div>
                <?php else: ?>
                    <div class="sj-thumb sj-thumb-kosong">Dibatalkan</div>
                <?php endif; ?>
                <div class="sj-kartu-atas">
                    <div class="sj-no"><?= htmlspecialchars($x['no']) ?></div>
                    <span class="badge" style="<?= $x['batal'] ? 'background: var(--bad-bg); color: var(--bad-fg);' : 'background: var(--ok-bg); color: var(--ok-fg);' ?> font-weight: 700; white-space: nowrap;"><?= $x['batal'] ? 'Dibatalkan' : 'Berlaku' ?></span>
                </div>
                <div>
                    <div class="sj-pihak"><?= htmlspecialchars($x['pihak']) ?></div>
                    <div class="sj-muted"><?= $tgl($x['tgl']) ?></div>
                    <?php if ($x['batal'] && $x['alasan']): ?><div class="sj-muted" style="color: var(--bad-fg);">Batal: <?= htmlspecialchars($x['alasan']) ?></div><?php endif; ?>
                </div>
                <div class="sj-stat" style="<?= $bolehInternal ? '' : 'grid-template-columns: repeat(2, 1fr);' ?>">
                    <div>Box<strong><?= $x['box'] ?></strong></div>
                    <div>Netto<strong><?= Helper::formatKg($x['kg']) ?></strong></div>
                    <?php if ($bolehInternal): ?><div><?= $tab === 'pengeluaran' ? 'Nilai jual' : 'Nilai beli' ?><strong><?= $rp($x['nilai']) ?></strong></div><?php endif; ?>
                </div>
                <div class="sj-aksi no-print">
                    <?php if (!$x['batal']): ?>
                        <button type="button" class="btn btn-primary btn-sm" onclick="bukaPreview(this)" <?= $attrPreview($x) ?>>Preview PDF</button>
                        <a href="<?= $x['urlForm'] ?>" target="_blank" class="btn btn-outline btn-sm">Cetak Form</a>
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
    <?php if ($tampil === 'grid') $bilahHalaman(); ?>
    <?php endif; ?>

    <div class="card" style="<?= $tampil === 'grid' ? 'display: none;' : '' ?>">
        <div class="table-responsive">
            <table class="table-custom" id="tableSuratJalan" data-no-tools>
                <thead>
                    <tr>
                        <?php $th('tanggal', 'Tanggal'); $th('no', 'No. Surat Jalan'); $th('pihak', $labelPihak); $th('box', 'Box', 'text-align: right;'); $th('kg', 'Netto (kg)', 'text-align: right;'); ?>
                        <?php if ($bolehInternal) $th('nilai', $tab === 'pengeluaran' ? 'Nilai Jual' : 'Nilai Beli', 'text-align: right;'); ?>
                        <?php $th('status', 'Status'); ?>
                        <th class="no-print" style="width: 330px;">Cetak</th>
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
                                <span class="badge" style="<?= $batal ? 'background: var(--bad-bg); color: var(--bad-fg);' : 'background: var(--ok-bg); color: var(--ok-fg);' ?> font-weight: 700;"><?= $batal ? 'Dibatalkan' : 'Berlaku' ?></span>
                                <?php if ($batal && $tab === 'pengeluaran' && $r['alasan_batal']): ?><div style="font-size: 11.5px; color: var(--text-muted);"><?= htmlspecialchars($r['alasan_batal']) ?></div><?php endif; ?>
                            </td>
                            <td class="no-print">
                                <?php if (!$batal): ?>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="bukaPreview(this)" <?= $attrPreview($x) ?>>Preview PDF</button>
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
        <?php if ($tampil === 'tabel') $bilahHalaman(); ?>
    </div>
</div>

<div id="pvModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 999; align-items: center; justify-content: center; backdrop-filter: blur(3px);" onclick="if (event.target === this) tutupPreview()">
    <div style="background: var(--bg-card); width: min(900px, 96vw); height: 94vh; border-radius: var(--radius); border: 1.5px solid var(--border); box-shadow: var(--shadow-lg); display: flex; flex-direction: column; overflow: hidden;">
        <div style="padding: 12px 16px; border-bottom: 1px solid var(--border); display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <div id="pvJudul" style="font-weight: 800; font-size: 14px; flex: 1; min-width: 160px; word-break: break-all;"></div>
            <div id="pvVersi" style="display: none; gap: 4px;">
                <button type="button" class="btn btn-sm" id="pvBtnForm" onclick="pvGanti('form')">Form (tanpa harga)</button>
                <button type="button" class="btn btn-sm" id="pvBtnInternal" onclick="pvGanti('internal')">Versi Internal</button>
            </div>
            <button type="button" class="btn btn-primary btn-sm" onclick="pvCetak()">Cetak / Simpan PDF</button>
            <a id="pvTab" href="#" target="_blank" class="btn btn-outline btn-sm">Buka di tab baru</a>
            <button type="button" class="btn btn-outline btn-sm" onclick="tutupPreview()" aria-label="Tutup">&#10005;</button>
        </div>
        <div id="pvArea" style="flex: 1; overflow: auto; background: var(--text-muted); padding: 16px;">
            <div id="pvKertas" style="margin: 0 auto; position: relative;">
                <iframe id="pvFrame" style="width: 794px; border: 0; background: #ffffff; box-shadow: 0 2px 12px rgba(0,0,0,.4); transform-origin: 0 0; display: block;" title="Pratinjau surat jalan"></iframe>
            </div>
        </div>
        <div style="padding: 8px 16px; font-size: 12px; color: var(--text-muted); border-top: 1px solid var(--border);">
            Pratinjau persis seperti hasil cetak di kertas 8&frac12; x 11 inci. Untuk menyimpan PDF, klik Cetak lalu pilih &quot;Simpan sebagai PDF&quot; sebagai tujuan.
        </div>
    </div>
</div>

<script>
    // ---- Pratinjau surat jalan (iframe halaman cetak mode embed) ----
    var PV_W = 794;
    var pvData = { form: '', internal: '' };

    function bukaPreview(el) {
        pvData.form = el.getAttribute('data-form') || '';
        pvData.internal = el.getAttribute('data-internal') || '';
        document.getElementById('pvJudul').textContent = el.getAttribute('data-judul') || 'Surat Jalan';
        document.getElementById('pvVersi').style.display = pvData.internal ? 'flex' : 'none';
        document.getElementById('pvModal').style.display = 'flex';
        pvGanti('form');
    }

    function pvGanti(v) {
        var url = pvData[v] || pvData.form;
        document.getElementById('pvBtnForm').className = 'btn btn-sm ' + (v === 'form' ? 'btn-primary' : 'btn-outline');
        document.getElementById('pvBtnInternal').className = 'btn btn-sm ' + (v === 'internal' ? 'btn-primary' : 'btn-outline');
        document.getElementById('pvTab').href = url.replace(/&embed=1/, '');
        var f = document.getElementById('pvFrame');
        f.style.height = '1123px';
        f.onload = pvUkur;
        f.src = url;
    }

    // Tinggi iframe mengikuti isi dokumen; kertas diperkecil bila layar lebih sempit dari 8,5 inci
    function pvUkur() {
        var f = document.getElementById('pvFrame');
        try {
            var h = Math.max(1123, f.contentDocument.documentElement.scrollHeight);
            f.style.height = h + 'px';
            var lebar = document.getElementById('pvArea').clientWidth - 32;
            var k = Math.min(1, lebar / PV_W);
            f.style.transform = 'scale(' + k + ')';
            var kertas = document.getElementById('pvKertas');
            kertas.style.width = (PV_W * k) + 'px';
            kertas.style.height = (h * k) + 'px';
        } catch (e) {}
    }

    function pvCetak() {
        var f = document.getElementById('pvFrame');
        try { f.contentWindow.focus(); f.contentWindow.print(); } catch (e) { window.open(document.getElementById('pvTab').href, '_blank'); }
    }

    function tutupPreview() {
        document.getElementById('pvModal').style.display = 'none';
        document.getElementById('pvFrame').src = 'about:blank';
    }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') tutupPreview(); });
    window.addEventListener('resize', function () { if (document.getElementById('pvModal').style.display === 'flex') pvUkur(); });

    // ---- Thumbnail di tampilan grid: dimuat saat kartu terlihat, diskalakan sesuai lebar kartu ----
    (function () {
        var thumbs = document.querySelectorAll('.sj-thumb[data-src]');
        if (!thumbs.length) return;
        function skala(t) {
            var f = t.querySelector('iframe');
            if (f) f.style.transform = 'scale(' + (t.clientWidth / PV_W) + ')';
        }
        function muat(t) {
            var f = t.querySelector('iframe');
            if (f && !f.getAttribute('src')) f.setAttribute('src', t.getAttribute('data-src'));
        }
        thumbs.forEach(skala);
        window.addEventListener('resize', function () { thumbs.forEach(skala); });
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (es) {
                es.forEach(function (e) { if (e.isIntersecting) { muat(e.target); io.unobserve(e.target); } });
            }, { rootMargin: '300px' });
            thumbs.forEach(function (t) { io.observe(t); });
        } else {
            thumbs.forEach(muat);
        }
    })();

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
