<?php
/**
 * Transaksi Kas & Bank.
 * Biaya tunai/transfer di luar faktur, setoran modal, dan prive. Setiap transaksi menerbitkan jurnal otomatis
 * (sisi kas = akun 1000) dan bisa dibatalkan dengan alasan; pembatalan membuat jurnal pembalik.
 */

use App\Core\Helper;
use App\Models\RekeningBank;
use App\Services\KasService;

$pageTitle = 'Transaksi Kas & Bank';

$dari   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dari'] ?? '') ? $_GET['dari'] : date('Y-m-01');
$sampai = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sampai'] ?? '') ? $_GET['sampai'] : date('Y-m-d');
$jenis  = isset(KasService::JENIS[$_GET['jenis'] ?? '']) ? $_GET['jenis'] : '';
$status = in_array($_GET['status'] ?? '', ['AKTIF', 'BATAL'], true) ? $_GET['status'] : '';

$rows = KasService::daftar(['dari' => $dari, 'sampai' => $sampai, 'jenis' => $jenis, 'status' => $status]);
$sum = KasService::ringkas($rows);
$saldoJurnal = KasService::saldoJurnal();
$akunPilihan = KasService::akunPilihan();
$rekening = RekeningBank::getAktif();

$role = strtoupper($_SESSION['user_login']['role'] ?? '');
$bolehCatat = \App\Core\Akses::boleh($role, ['ADMIN', 'SUPERVISOR', 'FINANCE']);

$rp = fn($n) => Helper::formatRupiah($n);
$tgl = fn($d) => date('d/m/Y', strtotime($d));

require_once __DIR__ . '/layout/header.php';
?>

<div class="content-wrapper">

    <div class="page-header no-print">
        <div>
            <h1 class="page-title">Transaksi Kas &amp; Bank</h1>
            <p class="page-subtitle">Biaya tunai atau transfer di luar faktur, setoran modal, dan prive. Jurnal terbit otomatis.</p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <?php $eksporTabel = 'tableKas'; $eksporNama = 'Transaksi_Kas_Bank'; $eksporJudul = 'Transaksi Kas & Bank ' . $tgl($dari) . ' s/d ' . $tgl($sampai); include __DIR__ . '/layout/ekspor.php'; ?>
            <?php if ($bolehCatat): ?>
            <button type="button" class="btn btn-primary" onclick="bukaKas()">
                <i data-lucide="plus" style="width: 16px; height: 16px;"></i><span>Catat Transaksi</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-4 no-print" style="gap: 16px; margin-bottom: 20px;">
        <div class="kpi-card"><div class="kpi-body">
            <div class="kpi-label">Kas Masuk (modal)</div>
            <div class="kpi-value"><?= $rp($sum['masuk']) ?></div>
            <div class="kpi-sub">periode ini, tidak termasuk setoran mitra</div>
        </div></div>
        <div class="kpi-card"><div class="kpi-body">
            <div class="kpi-label">Kas Keluar (biaya + prive)</div>
            <div class="kpi-value"><?= $rp($sum['keluar']) ?></div>
            <div class="kpi-sub">periode ini</div>
        </div></div>
        <div class="kpi-card"><div class="kpi-body">
            <div class="kpi-label">Bersih Periode</div>
            <div class="kpi-value" style="color: <?= $sum['bersih'] < 0 ? '#b91c1c' : 'inherit' ?>;"><?= $rp($sum['bersih']) ?></div>
            <div class="kpi-sub">masuk dikurangi keluar</div>
        </div></div>
        <div class="kpi-card"><div class="kpi-body">
            <div class="kpi-label">Saldo Kas &amp; Bank (jurnal)</div>
            <div class="kpi-value" style="color: <?= $saldoJurnal < 0 ? '#b91c1c' : 'inherit' ?>;"><?= $rp($saldoJurnal) ?></div>
            <div class="kpi-sub">akun 1000, semua dokumen, mulai dari 0</div>
        </div></div>
    </div>

    <form method="GET" class="card no-print" style="padding: 14px 16px; margin-bottom: 18px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="page" value="kas_bank">
        <div>
            <label class="form-label">Dari</label>
            <input type="date" name="dari" value="<?= $dari ?>" class="form-control">
        </div>
        <div>
            <label class="form-label">Sampai</label>
            <input type="date" name="sampai" value="<?= $sampai ?>" class="form-control">
        </div>
        <div style="min-width: 170px;">
            <label class="form-label">Jenis</label>
            <select name="jenis" class="form-control">
                <option value="">Semua jenis</option>
                <?php foreach (KasService::JENIS as $k => $j): ?>
                    <option value="<?= $k ?>" <?= $jenis === $k ? 'selected' : '' ?>><?= $j[0] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="min-width: 150px;">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">Semua</option>
                <option value="AKTIF" <?= $status === 'AKTIF' ? 'selected' : '' ?>>Aktif</option>
                <option value="BATAL" <?= $status === 'BATAL' ? 'selected' : '' ?>>Dibatalkan</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Tampilkan</button>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table-custom" id="tableKas" data-no-tools>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>No. Bukti</th>
                        <th>Jenis</th>
                        <th>Akun</th>
                        <th>Keterangan</th>
                        <th>Cara / Rekening</th>
                        <th style="text-align: right;">Masuk</th>
                        <th style="text-align: right;">Keluar</th>
                        <th>Status</th>
                        <th class="no-print" style="width: 110px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="10" style="text-align: center; padding: 30px; color: var(--text-muted);">Belum ada transaksi kas pada filter ini</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r):
                        $masuk = KasService::JENIS[$r['jenis']][1] === 'masuk';
                        $batal = $r['status'] === 'BATAL'; ?>
                        <tr style="<?= $batal ? 'opacity: .6;' : '' ?>">
                            <td><?= $tgl($r['tanggal']) ?></td>
                            <td style="font-family: 'JetBrains Mono', monospace; font-weight: 700;"><?= htmlspecialchars($r['no_bukti']) ?></td>
                            <td><?= KasService::JENIS[$r['jenis']][0] ?></td>
                            <td><?= htmlspecialchars($r['kode_akun'] . ' ' . ($r['nama_akun'] ?? '')) ?></td>
                            <td style="max-width: 260px;">
                                <?= htmlspecialchars($r['keterangan']) ?>
                                <?php if ($r['no_referensi']): ?><div style="font-size: 11.5px; color: var(--text-muted);">Ref: <?= htmlspecialchars($r['no_referensi']) ?></div><?php endif; ?>
                                <?php if ($batal): ?><div style="font-size: 11.5px; color: var(--bad-fg);">Batal: <?= htmlspecialchars((string)$r['alasan_batal']) ?></div><?php endif; ?>
                            </td>
                            <td style="font-size: 12.5px;">
                                <?= $r['metode'] === 'TRANSFER' ? 'Transfer' : 'Tunai' ?>
                                <?php if ($r['nama_bank']): ?><div style="color: var(--text-muted);"><?= htmlspecialchars($r['nama_bank'] . ' ' . $r['no_rekening']) ?></div><?php endif; ?>
                            </td>
                            <td style="text-align: right;<?= $batal ? ' text-decoration: line-through;' : '' ?>"><?= $masuk ? $rp($r['nominal']) : '' ?></td>
                            <td style="text-align: right;<?= $batal ? ' text-decoration: line-through;' : '' ?>"><?= !$masuk ? $rp($r['nominal']) : '' ?></td>
                            <td><span class="badge" style="<?= $batal ? 'background: var(--bad-bg); color: var(--bad-fg);' : 'background: var(--ok-bg); color: var(--ok-fg);' ?> font-weight: 700;"><?= $batal ? 'Dibatalkan' : 'Aktif' ?></span></td>
                            <td class="no-print">
                                <?php if (!$batal && $bolehCatat): ?>
                                    <button type="button" class="btn btn-outline btn-sm" style="color: var(--bad-fg); border-color: rgba(220, 38, 38, .3);"
                                            onclick='batalDokumen("batal_kas", {id: <?= (int)$r['id'] ?>}, <?= json_encode('transaksi kas ' . $r['no_bukti']) ?>)'>Batalkan</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($bolehCatat): ?>
<div id="modalKas" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-card); width: 100%; max-width: 520px; max-height: 92vh; overflow-y: auto; border-radius: var(--radius); border: 1.5px solid var(--border); box-shadow: var(--shadow-lg);">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 16px; font-weight: 800; color: var(--text-main); margin: 0;">Catat Transaksi Kas</h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="tutupKas()">&#10005;</button>
        </div>
        <form id="formKas" style="padding: 20px;" onsubmit="return simpanKas(event)">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Jenis <span style="color:red;">*</span></label>
                    <select name="jenis" id="kasJenis" class="form-control" onchange="isiAkunKas()">
                        <?php foreach (KasService::JENIS as $k => $j): ?>
                            <option value="<?= $k ?>"><?= $j[0] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal <span style="color:red;">*</span></label>
                    <input type="date" name="tanggal" id="kasTanggal" class="form-control" required max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" id="kasAkunLabel">Akun biaya <span style="color:red;">*</span></label>
                <select name="kode_akun" id="kasAkun" class="form-control" required></select>
                <div id="kasHint" style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px;"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Nominal (Rp) <span style="color:red;">*</span></label>
                <input type="number" name="nominal" id="kasNominal" class="form-control" required min="1" step="1" inputmode="numeric" placeholder="0">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Cara</label>
                    <select name="metode" id="kasMetode" class="form-control" onchange="aturRekeningKas()">
                        <option value="TUNAI">Tunai</option>
                        <option value="TRANSFER">Transfer bank</option>
                    </select>
                </div>
                <div class="form-group" id="kasRekWrap">
                    <label class="form-label">Rekening</label>
                    <select name="rekening_id" id="kasRek" class="form-control">
                        <option value="">Pilih rekening</option>
                        <?php foreach ($rekening as $rk): ?>
                            <option value="<?= (int)$rk['id'] ?>"><?= htmlspecialchars(RekeningBank::label($rk)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$rekening): ?><div style="font-size: 11.5px; color: var(--warn-fg); margin-top: 4px;">Belum ada rekening. Tambahkan di Master &gt; Rekening Bank.</div><?php endif; ?>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">No. Referensi</label>
                <input type="text" name="no_referensi" class="form-control" placeholder="No. nota / bukti transfer (opsional)">
            </div>
            <div class="form-group">
                <label class="form-label">Keterangan <span style="color:red;">*</span></label>
                <input type="text" name="keterangan" id="kasKet" class="form-control" required maxlength="255" placeholder="Contoh: Bayar listrik gudang November">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px;">
                <button type="button" class="btn btn-outline" onclick="tutupKas()">Batal</button>
                <button type="submit" class="btn btn-primary" id="kasSubmit">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    const KAS_AKUN = <?= json_encode($akunPilihan) ?>;
    const KAS_HINT = {
        BIAYA: 'Kas berkurang, biaya bertambah (masuk Laba Rugi).',
        MODAL: 'Kas bertambah, modal pemilik bertambah. Tidak masuk Laba Rugi.',
        PRIVE: 'Kas berkurang, prive bertambah. Tidak masuk Laba Rugi.'
    };
    const KAS_LABEL = { BIAYA: 'Akun biaya', MODAL: 'Akun modal', PRIVE: 'Akun prive' };

    function isiAkunKas() {
        const j = document.getElementById('kasJenis').value;
        const sel = document.getElementById('kasAkun');
        sel.innerHTML = '';
        (KAS_AKUN[j] || []).forEach(a => {
            const o = document.createElement('option');
            o.value = a.kode;
            o.textContent = a.kode + ' - ' + a.nama;
            sel.appendChild(o);
        });
        document.getElementById('kasAkunLabel').innerHTML = KAS_LABEL[j] + ' <span style="color:red;">*</span>';
        document.getElementById('kasHint').textContent = KAS_HINT[j];
        if (window.SelectUI && window.SelectUI.refresh) window.SelectUI.refresh(sel);
    }
    function aturRekeningKas() {
        const transfer = document.getElementById('kasMetode').value === 'TRANSFER';
        const sel = document.getElementById('kasRek');
        if (transfer && !sel.value && sel.options.length === 2) sel.selectedIndex = 1;
        document.getElementById('kasRekWrap').style.opacity = transfer ? '1' : '.75';
    }
    function bukaKas() {
        document.getElementById('formKas').reset();
        document.getElementById('kasTanggal').value = '<?= date('Y-m-d') ?>';
        isiAkunKas();
        aturRekeningKas();
        document.getElementById('modalKas').style.display = 'flex';
    }
    function tutupKas() { document.getElementById('modalKas').style.display = 'none'; }

    async function simpanKas(e) {
        e.preventDefault();
        const btn = document.getElementById('kasSubmit');
        btn.disabled = true;
        try {
            const resp = await fetch('index.php?action=simpan_kas', { method: 'POST', body: new FormData(document.getElementById('formKas')) });
            const res = await resp.json();
            if (res.success) {
                await Swal.fire({ icon: 'success', title: 'Tersimpan', text: res.message });
                window.location.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal menyimpan', text: res.message });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Kesalahan jaringan', text: 'Coba lagi beberapa saat.' });
        }
        btn.disabled = false;
        return false;
    }
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
