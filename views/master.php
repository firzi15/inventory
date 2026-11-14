<?php
/**
 * View Master Data Hub (Pemasok, Master Harga Pemasok, Master Harga Mitra, Analisa Profit/Loss, Mitra, Produk, Settingan Accurate)
 * Desain Simple & Jelas untuk Kemudahan Pengguna
 */

use App\Core\Helper;
use App\Config\Config;
use App\Services\AccurateApiService;
use App\Models\Pemasok;
use App\Models\VendorNonPemasok;
use App\Models\TipeBiaya;
use App\Models\Mitra;
use App\Models\Item;
use App\Models\MasterHarga;
use App\Models\MasterHargaPemasok;

$pageTitle = 'Master Data - Warehouse Management System';

$activeTab = $_GET['tab'] ?? 'pemasok'; // default tab
if ($activeTab === 'harga') {
    $activeTab = 'harga_mitra';
}
if ($activeTab === 'profit_loss') {
    header('Location: index.php?page=profit_loss');
    exit;
}

$pemasokList = Pemasok::getAll();
$vendorNonPemasokList = VendorNonPemasok::getAll();
$rekeningList = $activeTab === 'rekening_bank' ? \App\Models\RekeningBank::getAll() : [];
$tipeBiayaList = TipeBiaya::getAll();
$coaList = TipeBiaya::getCoaAccounts();
$coaMaster = \App\Services\CoaService::accounts();
$coaSaldo = \App\Services\CoaService::balances();
$mitraList = Mitra::getAll();
$itemList = Item::getAll();
$jenisList = Item::getAllJenis();
$priceMatrix = MasterHarga::getHargaMatrix();
$allPrices = MasterHarga::getAllWithDetails();
$priceMatrixPemasok = MasterHargaPemasok::getHargaMatrix();
$profitLossData = MasterHargaPemasok::getProfitLossSummary();

// Kredensial & Antrean Accurate
$cfg = Config::getAccurateConfig();
$queueList = AccurateApiService::getQueueList(30);

if ($activeTab === 'accurate') {
    $pageTitle = 'Settingan API Accurate - Warehouse Management System';
}

// Alert message
$msg = $_GET['msg'] ?? null;
$msgType = $_GET['msg_type'] ?? 'success';

require_once __DIR__ . '/layout/header.php';
?>

<!-- Page Header Bersih & Jelas -->
<div class="page-header no-print">
    <?php if ($activeTab === 'accurate'): ?>
        <div>
            <h1 class="page-title">Settingan API Accurate Online</h1>
            <p class="page-subtitle">Konfigurasi API token resmi, otentikasi database, dan pemantauan antrean transaksi otomatis</p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <a href="Manual_Book_Sistem_Barcode_Inventory_Multi_Mitra.docx" class="btn btn-outline" download style="display: flex; align-items: center; gap: 6px;">
                <i data-lucide="file-text" style="width: 16px; height: 16px; color: var(--aktif-fg);"></i>
                <span>Manual Book</span>
            </a>
            <button type="button" class="btn btn-outline" onclick="openGuideModal()">
                <i data-lucide="help-circle" style="width: 16px; height: 16px;"></i>
                <span>Petunjuk Cara Konek</span>
            </button>
            <button type="button" class="btn btn-primary" id="btnSyncAllPending">
                <i data-lucide="refresh-cw" style="width: 16px; height: 16px;"></i>
                <span>Proses Antrean Pending</span>
            </button>
        </div>
    <?php else: ?>
        <div>
            <h1 class="page-title">Master Data Gudang & Operasional</h1>
            <p class="page-subtitle">Pemasok ikan nelayan, rekanan vendor non-pemasok, tipe biaya, dan katalog produk</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if ($activeTab === 'pemasok'): ?>
                <?php $eksporTabel = 'tableMasterPemasok'; $eksporNama = 'Master_Pemasok'; include __DIR__ . '/layout/ekspor.php'; ?>
            <?php elseif ($activeTab === 'rekening_bank'): ?>
                <?php $eksporTabel = 'tableMasterRekeningBank'; $eksporNama = 'Master_Rekening_Bank'; include __DIR__ . '/layout/ekspor.php'; ?>
            <?php elseif ($activeTab === 'vendor_non_pemasok'): ?>
                <?php $eksporTabel = 'tableMasterVendorNonPemasok'; $eksporNama = 'Master_Vendor_Non_Pemasok'; include __DIR__ . '/layout/ekspor.php'; ?>
            <?php elseif ($activeTab === 'log_harga'):
    $lhJenis = in_array($_GET['jenis'] ?? '', ['PEMASOK', 'MITRA'], true) ? $_GET['jenis'] : '';
    $lhPihak = $_GET['pihak'] ?? '';
    $lhItem  = (int)($_GET['item'] ?? 0);
    $lhDari  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dari'] ?? '') ? $_GET['dari'] : '';
    $lhSampai = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sampai'] ?? '') ? $_GET['sampai'] : '';
    $lhAwal = !empty($_GET['awal']);
    $lhFilter = ['dari' => $lhDari, 'sampai' => $lhSampai, 'item_id' => $lhItem, 'dengan_awal' => $lhAwal];
    if (preg_match('/^(PEMASOK|MITRA):(\d+)$/', $lhPihak, $mm)) { $lhFilter['jenis'] = $mm[1]; $lhFilter['pihak_id'] = (int)$mm[2]; }
    elseif ($lhJenis) { $lhFilter['jenis'] = $lhJenis; }
    $logHarga = \App\Services\LogHargaService::daftar($lhFilter);
?>
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">
                <i data-lucide="history" style="width: 20px; height: 20px; color: var(--bad-fg);"></i>
                <span>Log Perubahan Harga</span>
            </h2>
            <div class="card-description">Setiap perubahan harga beli pemasok dan harga jual mitra: harga lama, harga baru, siapa, kapan, dan alasannya. Harga yang tidak berubah tidak dicatat.</div>
        </div>
        <div style="display: flex; gap: 8px;">
            <?php $eksporTabel = 'tableLogHarga'; $eksporNama = 'Log_Perubahan_Harga'; include __DIR__ . '/layout/ekspor.php'; ?>
        </div>
    </div>

    <form method="GET" style="padding: 14px 20px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; border-bottom: 1px solid var(--border);">
        <input type="hidden" name="page" value="master">
        <input type="hidden" name="tab" value="log_harga">
        <div style="min-width: 170px;">
            <label class="form-label">Jenis Harga</label>
            <select name="jenis" class="form-control">
                <option value="">Semua</option>
                <option value="PEMASOK" <?= $lhJenis === 'PEMASOK' ? 'selected' : '' ?>>Harga Beli (Pemasok)</option>
                <option value="MITRA" <?= $lhJenis === 'MITRA' ? 'selected' : '' ?>>Harga Jual (Mitra)</option>
            </select>
        </div>
        <div style="min-width: 220px;">
            <label class="form-label">Pemasok / Mitra</label>
            <select name="pihak" class="form-control">
                <option value="">Semua</option>
                <optgroup label="Pemasok">
                    <?php foreach ($pemasokList as $p): ?><option value="PEMASOK:<?= $p['id'] ?>" <?= $lhPihak === 'PEMASOK:' . $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nama_pemasok']) ?></option><?php endforeach; ?>
                </optgroup>
                <optgroup label="Mitra">
                    <?php foreach ($mitraList as $m): ?><option value="MITRA:<?= $m['id'] ?>" <?= $lhPihak === 'MITRA:' . $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nama_mitra']) ?></option><?php endforeach; ?>
                </optgroup>
            </select>
        </div>
        <div style="min-width: 240px;">
            <label class="form-label">Artikel</label>
            <select name="item" class="form-control">
                <option value="0">Semua artikel</option>
                <?php foreach ($itemList as $it): ?><option value="<?= $it['id'] ?>" <?= $lhItem === (int)$it['id'] ? 'selected' : '' ?>><?= htmlspecialchars($it['kode_item'] . ' - ' . $it['nama_item']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label class="form-label">Dari</label><input type="date" name="dari" value="<?= $lhDari ?>" class="form-control"></div>
        <div><label class="form-label">Sampai</label><input type="date" name="sampai" value="<?= $lhSampai ?>" class="form-control"></div>
        <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; padding-bottom: 10px;"><input type="checkbox" name="awal" value="1" <?= $lhAwal ? 'checked' : '' ?>> Tampilkan harga awal</label>
        <button type="submit" class="btn btn-primary">Tampilkan</button>
    </form>

    <div class="table-responsive">
        <table class="table-custom" id="tableLogHarga" data-no-tools>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Jenis</th>
                    <th>Pemasok / Mitra</th>
                    <th>Artikel</th>
                    <th style="text-align: right;">Harga Lama</th>
                    <th style="text-align: right;">Harga Baru</th>
                    <th style="text-align: right;">Selisih</th>
                    <th>Diubah Oleh</th>
                    <th>Alasan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$logHarga): ?>
                    <tr><td colspan="9" style="text-align: center; padding: 30px; color: var(--text-muted);">Belum ada perubahan harga pada filter ini</td></tr>
                <?php endif; ?>
                <?php foreach ($logHarga as $lg): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($lg['waktu'])) ?></td>
                        <td><?= $lg['jenis'] === 'PEMASOK' ? 'Harga beli' : 'Harga jual' ?></td>
                        <td style="font-weight: 600;"><?= htmlspecialchars((string)$lg['pihak']) ?></td>
                        <td><?= htmlspecialchars($lg['nama_item']) ?></td>
                        <td style="text-align: right;"><?= $lg['harga_lama'] === null ? '-' : Helper::formatRupiah($lg['harga_lama']) ?></td>
                        <td style="text-align: right; font-weight: 700;"><?= Helper::formatRupiah($lg['harga_baru']) ?></td>
                        <td style="text-align: right; color: <?= $lg['selisih'] === null ? 'inherit' : ($lg['selisih'] > 0 ? '#b91c1c' : '#15803d') ?>;">
                            <?php if ($lg['selisih'] === null): ?>harga awal
                            <?php else: ?><?= ($lg['selisih'] > 0 ? 'naik ' : 'turun ') . Helper::formatRupiah(abs($lg['selisih'])) ?><?= $lg['persen'] !== null ? ' (' . ($lg['selisih'] > 0 ? '+' : '-') . number_format(abs($lg['persen']), 1, ',', '.') . '%)' : '' ?>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars((string)($lg['oleh'] ?? '-')) ?></td>
                        <td style="font-size: 12.5px; color: var(--text-muted);"><?= htmlspecialchars((string)($lg['alasan'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($activeTab === 'rekening_bank'): ?>
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">
                <i data-lucide="landmark" style="width: 20px; height: 20px; color: var(--aktif-fg);"></i>
                <span>Rekening Bank</span>
            </h2>
            <div class="card-description">Rekening bank perusahaan. Tampil di Transaksi Kas &amp; Bank dan Rekonsiliasi Bank.</div>
        </div>
        <button type="button" class="btn btn-primary" onclick="openModalRekening()">
            <i data-lucide="plus" style="width: 16px; height: 16px;"></i>
            <span>Tambah Rekening</span>
        </button>
    </div>
    <div class="table-responsive">
        <table class="table-custom" id="tableMasterRekeningBank">
            <thead>
                <tr>
                    <th style="width: 100px;">Kode</th>
                    <th>Bank</th>
                    <th>Nomor Rekening</th>
                    <th>Atas Nama</th>
                    <th>Keterangan</th>
                    <th>Status</th>
                    <th style="text-align: center; width: 150px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rekeningList)): ?>
                <tr><td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">Belum ada rekening. Klik "Tambah Rekening".</td></tr>
                <?php else: foreach ($rekeningList as $rk): ?>
                <tr>
                    <td><span class="badge" style="background: rgba(14, 165, 233, 0.12); color: var(--aktif-fg); font-family: 'JetBrains Mono', monospace; font-weight: 800; font-size: 12px;"><?= htmlspecialchars($rk['kode_rekening']) ?></span></td>
                    <td><strong><?= htmlspecialchars($rk['nama_bank']) ?></strong></td>
                    <td style="font-family: 'JetBrains Mono', monospace; font-weight: 700;"><?= htmlspecialchars($rk['no_rekening']) ?></td>
                    <td><?= htmlspecialchars($rk['atas_nama'] ?: '-') ?></td>
                    <td style="font-size: 12.5px;"><?= htmlspecialchars($rk['keterangan'] ?: '-') ?></td>
                    <td>
                        <a href="index.php?action=toggle_rekening_bank&id=<?= (int)$rk['id'] ?>" class="btn btn-sm"
                           style="<?= $rk['is_active'] ? 'background: var(--ok-bg); color: var(--ok-fg); border: 1.5px solid var(--border-hover);' : 'background: var(--bg-muted); color: var(--text-muted); border: 1.5px solid var(--border);' ?> border-radius: 20px; font-weight: 800; font-size: 11.5px; padding: 4px 12px; text-decoration: none;"
                           title="Klik untuk mengubah status"><?= $rk['is_active'] ? 'Aktif' : 'Nonaktif' ?></a>
                    </td>
                    <td style="text-align: center;">
                        <div style="display: flex; justify-content: center; gap: 6px;">
                            <button type="button" class="btn btn-outline btn-sm" onclick='editRekening(<?= json_encode($rk) ?>)'>
                                <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i><span>Edit</span>
                            </button>
                            <button type="button" class="btn btn-outline btn-sm" style="color: var(--bad-fg); border-color: rgba(239, 68, 68, 0.3);"
                                    onclick='hapusMaster("delete_rekening_bank", <?= (int)$rk['id'] ?>, <?= json_encode('rekening ' . $rk['kode_rekening']) ?>)' title="Hapus">
                                <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($activeTab === 'coa'): ?>
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">
                <i data-lucide="book-open" style="width: 20px; height: 20px; color: var(--aktif-fg);"></i>
                <span>Master Akun COA</span>
            </h2>
            <div class="card-description">Daftar akun buku besar. Akun inti dipakai posting otomatis dokumen; sub-akun bisa ditambah dan dipilih di Purchase Invoice.</div>
        </div>
        <?php $eksporTabel = 'tableMasterCoa'; $eksporNama = 'Master_COA'; include __DIR__ . '/layout/ekspor.php'; ?>
        <button type="button" class="btn btn-primary" onclick="openModalCoa('')">
            <i data-lucide="plus" style="width: 16px; height: 16px;"></i>
            <span>Tambah Akun</span>
        </button>
    </div>

    <div class="table-responsive">
        <table class="table-custom" id="tableMasterCoa" data-no-tools>
            <thead>
                <tr>
                    <th style="width: 90px;">Kode</th>
                    <th>Nama Akun</th>
                    <th>Kelompok</th>
                    <th>Saldo Normal</th>
                    <th>Keterangan</th>
                    <th style="text-align: right;">Saldo</th>
                    <th>Status</th>
                    <th style="text-align: center; width: 190px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($coaMaster as $kodeC => $c): ?>
                <tr style="<?= $c[7] ? '' : 'opacity: 0.55;' ?>">
                    <td><span class="badge" style="background: rgba(2, 132, 199, 0.12); color: var(--aktif-fg); font-family: 'JetBrains Mono', monospace; font-weight: 800; font-size: 12px;"><?= $kodeC ?></span></td>
                    <td style="padding-left: <?= 12 + $c[9] * 22 ?>px; font-weight: <?= $c[9] === 0 ? '800' : '600' ?>;">
                        <?= $c[9] > 0 ? '<span style="color: var(--text-muted);">&#8627;</span> ' : '' ?><?= htmlspecialchars($c[0]) ?>
                        <?php if ($c[3]): ?><span class="badge badge-info" style="margin-left: 6px; font-size: 10.5px;">Akun inti</span><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($c[4]) ?></td>
                    <td><?= $c[2] ?></td>
                    <td style="font-size: 12.5px; color: var(--text-muted); max-width: 260px;"><?= htmlspecialchars($c[8] ?: '-') ?></td>
                    <td style="text-align: right; font-weight: 700;"><?= Helper::formatRupiah($coaSaldo[$kodeC] ?? 0) ?></td>
                    <td>
                        <?php if ($c[3]): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <a href="index.php?action=toggle_coa&kode=<?= $kodeC ?>" class="badge <?= $c[7] ? 'badge-success' : '' ?>" style="text-decoration: none; cursor: pointer;" title="Klik untuk <?= $c[7] ? 'nonaktifkan' : 'aktifkan' ?>"><?= $c[7] ? 'Aktif' : 'Nonaktif' ?></a>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <div style="display: flex; justify-content: center; gap: 6px;">
                            <button type="button" class="btn btn-outline btn-sm" onclick="openModalCoa('<?= $kodeC ?>')" title="Tambah sub-akun di bawah akun ini">
                                <i data-lucide="plus" style="width: 14px; height: 14px;"></i>
                            </button>
                            <button type="button" class="btn btn-outline btn-sm" onclick='editCoa(<?= json_encode(['kode' => $kodeC, 'nama' => $c[0], 'keterangan' => $c[8]]) ?>)' title="Edit nama dan keterangan">
                                <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                            </button>
                            <?php if (!$c[3]): ?>
                            <a href="javascript:void(0)" onclick="hapusMaster('delete_coa', '<?= $kodeC ?>', 'akun <?= $kodeC ?> <?= htmlspecialchars($c[0], ENT_QUOTES) ?>')" class="btn btn-outline btn-sm" style="color: var(--bad-fg); border-color: rgba(239, 68, 68, 0.3);" title="Hapus akun">
                                <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($activeTab === 'tipe_biaya'): ?>
                <?php $eksporTabel = 'tableMasterTipeBiaya'; $eksporNama = 'Master_Tipe_Biaya'; include __DIR__ . '/layout/ekspor.php'; ?>
            <?php elseif ($activeTab === 'harga_pemasok'): ?>
                <?php $eksporTabel = 'tableMasterHargaPemasok'; $eksporNama = 'Master_Harga_Pemasok'; include __DIR__ . '/layout/ekspor.php'; ?>
            <?php elseif ($activeTab === 'harga_mitra'): ?>
                <?php $eksporTabel = 'tableMasterHargaMitra'; $eksporNama = 'Master_Harga_Mitra'; include __DIR__ . '/layout/ekspor.php'; ?>
            <?php elseif ($activeTab === 'profit_loss'): ?>
                <?php $eksporTabel = 'tableProfitLoss'; $eksporNama = 'Analisa_Profit_Loss'; include __DIR__ . '/layout/ekspor.php'; ?>
            <?php elseif ($activeTab === 'mitra'): ?>
                <?php $eksporTabel = 'tableMasterMitra'; $eksporNama = 'Master_Mitra_Bisnis'; include __DIR__ . '/layout/ekspor.php'; ?>
            <?php elseif ($activeTab === 'produk'): ?>
                <?php $eksporTabel = 'tableMasterProduk'; $eksporNama = 'Master_Produk'; include __DIR__ . '/layout/ekspor.php'; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msgType) ?>" style="margin-bottom: 20px;">
    <i data-lucide="<?= $msgType === 'success' ? 'check-circle-2' : 'alert-circle' ?>" style="width: 18px; height: 18px;"></i>
    <div><?= htmlspecialchars($msg) ?></div>
</div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- TAB PEMASOK: MASTER PEMASOK (SUPPLIER BARANG MASUK)                       -->
<!-- ========================================================================= -->
<?php if ($activeTab === 'pemasok'): ?>
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">
                <i data-lucide="truck" style="width: 20px; height: 20px; color: var(--warn-fg);"></i>
                <span>Daftar Pemasok</span>
            </h2>
            <div class="card-description">Kelola data nelayan, tambak, atau supplier pengirim barang pada proses Penerimaan Barang Masuk</div>
        </div>
        <button type="button" class="btn btn-primary" onclick="openModalPemasok()">
            <i data-lucide="plus" style="width: 16px; height: 16px;"></i>
            <span>Tambah Pemasok Baru</span>
        </button>
    </div>

    <div class="table-responsive">
        <table class="table-custom" id="tableMasterPemasok">
            <thead>
                <tr>
                    <th style="width: 110px;">Kode</th>
                    <th>Nama Pemasok / Supplier</th>
                    <th>Kontak / PIC</th>
                    <th>No. Telepon / WA</th>
                    <th>Alamat Asal</th>
                    <th>Keterangan</th>
                    <th>Status</th>
                    <th style="text-align: center; width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pemasokList)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted);">
                        Belum ada data pemasok. Klik "Tambah Pemasok Baru" untuk menambahkan.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($pemasokList as $p): ?>
                    <tr>
                        <td>
                            <span class="badge" style="background: rgba(245, 158, 11, 0.12); color: var(--warn-fg); font-family: 'JetBrains Mono', monospace; font-weight: 800; font-size: 12px;">
                                <?= htmlspecialchars($p['kode_pemasok']) ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color: var(--text-main); font-size: 14px;"><?= htmlspecialchars($p['nama_pemasok']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($p['pic_nama'] ?: '-') ?></td>
                        <td>
                            <?php if (!empty($p['telepon'])): ?>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $p['telepon']) ?>" target="_blank" style="color: var(--ok-fg); font-weight: 600; text-decoration: none;">
                                    <?= htmlspecialchars($p['telepon']) ?>
                                </a>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 12.5px; color: var(--text-muted); max-width: 200px;">
                            <?= htmlspecialchars($p['alamat'] ?: '-') ?>
                        </td>
                        <td style="font-size: 12.5px;"><?= htmlspecialchars($p['keterangan'] ?: '-') ?></td>
                        <td>
                            <a href="javascript:void(0)" 
                               onclick="confirmTogglePemasok(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nama_pemasok'], ENT_QUOTES) ?>', <?= $p['is_active'] ? 'true' : 'false' ?>)"
                               class="btn btn-sm" 
                               style="<?= $p['is_active'] 
                                   ? 'background: var(--ok-bg); color: var(--ok-fg); border: 1.5px solid var(--border-hover);' 
                                   : 'background: var(--bg-muted); color: var(--text-muted); border: 1.5px solid var(--border);' 
                               ?> border-radius: 20px; font-weight: 800; font-size: 11.5px; padding: 4px 12px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; cursor: pointer; transition: all 0.2s;"
                               title="<?= $p['is_active'] ? 'Klik untuk MENONAKTIFKAN pemasok ini' : 'Klik untuk MENGAKTIFKAN pemasok ini' ?>">
                                <?php if ($p['is_active']): ?>
                                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--ok-solid); box-shadow: 0 0 6px rgba(16, 185, 129, 0.6);"></span>
                                    <span>● Aktif</span>
                                    <i data-lucide="power" style="width: 12px; height: 12px; opacity: 0.7;"></i>
                                <?php else: ?>
                                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--text-muted);"></span>
                                    <span>Nonaktif</span>
                                    <i data-lucide="power" style="width: 12px; height: 12px; opacity: 0.7;"></i>
                                <?php endif; ?>
                            </a>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: flex; justify-content: center; gap: 6px;">
                                <button type="button" class="btn btn-outline btn-sm" onclick='editPemasok(<?= json_encode($p) ?>)' title="Edit Profil Pemasok">
                                    <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                                    <span>Edit</span>
                                </button>
                                <a href="javascript:void(0)" 
                                   onclick="confirmDeletePemasok(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nama_pemasok'], ENT_QUOTES) ?>')" 
                                   class="btn btn-outline btn-sm" 
                                   style="color: var(--bad-fg); border-color: rgba(239, 68, 68, 0.3);" 
                                   title="Hapus Pemasok Permanen">
                                    <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================================= -->
<!-- TAB VENDOR NON-PEMASOK: REKANAN VENDOR OPERASIONAL & PIHAK KETIGA         -->
<!-- ========================================================================= -->
<?php elseif ($activeTab === 'vendor_non_pemasok'): ?>
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">
                <i data-lucide="store" style="width: 20px; height: 20px; color: var(--aktif-fg);"></i>
                <span>Vendor Non-Pemasok</span>
            </h2>
            <div class="card-description">Vendor penyedia barang operasional, packaging/kemasan, media pendingin, BBM genset, dan jasa perawatan</div>
        </div>
        <button type="button" class="btn btn-primary" onclick="openModalVendorNonPemasok()">
            <i data-lucide="plus" style="width: 16px; height: 16px;"></i>
            <span>Tambah Vendor</span>
        </button>
    </div>

    <div class="table-responsive">
        <table class="table-custom" id="tableMasterVendorNonPemasok">
            <thead>
                <tr>
                    <th style="width: 100px;">Kode</th>
                    <th>Nama Rekanan / Vendor</th>
                    <th>Kategori Vendor</th>
                    <th>Kontak / PIC</th>
                    <th>Alamat & Lokasi</th>
                    <th>Bank & Rekening</th>
                    <th>Keterangan</th>
                    <th>Status</th>
                    <th style="text-align: center; width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vendorNonPemasokList)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 30px; color: var(--text-muted);">
                        Belum ada data vendor non-pemasok. Klik "Tambah Vendor" untuk menambahkan.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($vendorNonPemasokList as $v): ?>
                    <tr>
                        <td>
                            <span class="badge" style="background: rgba(59, 130, 246, 0.12); color: var(--aktif-fg); font-family: 'JetBrains Mono', monospace; font-weight: 800; font-size: 12px;">
                                <?= htmlspecialchars($v['kode_vendor']) ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color: var(--text-main); font-size: 14px;"><?= htmlspecialchars($v['nama_vendor']) ?></strong>
                        </td>
                        <td>
                            <span class="badge" style="background: var(--bg-muted); color: var(--text-muted); font-weight: 700; font-size: 11.5px;">
                                <?= htmlspecialchars($v['kategori_vendor'] ?: 'Operasional') ?>
                            </span>
                        </td>
                        <td>
                            <div><?= htmlspecialchars($v['pic_nama'] ?: '-') ?></div>
                            <?php if (!empty($v['telepon'])): ?>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $v['telepon']) ?>" target="_blank" style="color: var(--ok-fg); font-weight: 600; font-size: 12px; text-decoration: none;">
                                    <?= htmlspecialchars($v['telepon']) ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 12.5px; color: var(--text-muted); max-width: 180px;">
                            <?= htmlspecialchars($v['alamat'] ?: '-') ?>
                        </td>
                        <td style="font-size: 12px;">
                            <?php if (!empty($v['bank']) || !empty($v['no_rekening'])): ?>
                                <strong style="color: var(--text-main);"><?= htmlspecialchars($v['bank'] ?: 'Bank') ?></strong>: <?= htmlspecialchars($v['no_rekening'] ?: '-') ?>
                                <?php if (!empty($v['nama_rekening'])): ?>
                                    <div style="color: var(--text-muted); font-size: 11px;">a.n <?= htmlspecialchars($v['nama_rekening']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 12.5px;"><?= htmlspecialchars($v['keterangan'] ?: '-') ?></td>
                        <td>
                            <a href="javascript:void(0)" 
                               onclick="confirmToggleVendorNonPemasok(<?= $v['id'] ?>, '<?= htmlspecialchars($v['nama_vendor'], ENT_QUOTES) ?>', <?= $v['is_active'] ? 'true' : 'false' ?>)"
                               class="btn btn-sm" 
                               style="<?= $v['is_active'] 
                                   ? 'background: var(--ok-bg); color: var(--ok-fg); border: 1.5px solid var(--border-hover);' 
                                   : 'background: var(--bg-muted); color: var(--text-muted); border: 1.5px solid var(--border);' 
                               ?> border-radius: 20px; font-weight: 800; font-size: 11.5px; padding: 4px 12px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; cursor: pointer; transition: all 0.2s;"
                               title="<?= $v['is_active'] ? 'Klik untuk nonaktifkan' : 'Klik untuk aktifkan' ?>">
                                <?php if ($v['is_active']): ?>
                                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--ok-solid); box-shadow: 0 0 6px rgba(16, 185, 129, 0.6);"></span>
                                    <span>Aktif</span>
                                    <i data-lucide="power" style="width: 12px; height: 12px; opacity: 0.7;"></i>
                                <?php else: ?>
                                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--text-muted);"></span>
                                    <span>Nonaktif</span>
                                    <i data-lucide="power" style="width: 12px; height: 12px; opacity: 0.7;"></i>
                                <?php endif; ?>
                            </a>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: flex; justify-content: center; gap: 6px;">
                                <button type="button" class="btn btn-outline btn-sm" onclick='editVendorNonPemasok(<?= json_encode($v) ?>)' title="Edit Profil Vendor">
                                    <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                                    <span>Edit</span>
                                </button>
                                <a href="javascript:void(0)" 
                                   onclick="confirmDeleteVendorNonPemasok(<?= $v['id'] ?>, '<?= htmlspecialchars($v['nama_vendor'], ENT_QUOTES) ?>')" 
                                   class="btn btn-outline btn-sm" 
                                   style="color: var(--bad-fg); border-color: rgba(239, 68, 68, 0.3);" 
                                   title="Hapus Vendor Permanen">
                                    <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================================= -->
<!-- TAB TIPE BIAYA: KATALOG JENIS BIAYA OPERASIONAL & AKUN COA               -->
<!-- ========================================================================= -->
<?php elseif ($activeTab === 'tipe_biaya'): ?>
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">
                <i data-lucide="receipt" style="width: 20px; height: 20px; color: var(--aktif-fg);"></i>
                <span>Master Tipe Biaya Operasional</span>
            </h2>
            <div class="card-description">Klasifikasi pengeluaran operasional dan pemetaan akun buku besar</div>
        </div>
        <button type="button" class="btn btn-primary" onclick="openModalTipeBiaya()">
            <i data-lucide="plus" style="width: 16px; height: 16px;"></i>
            <span>Tambah Tipe Biaya</span>
        </button>
    </div>

    <div class="table-responsive">
        <table class="table-custom" id="tableMasterTipeBiaya">
            <thead>
                <tr>
                    <th style="width: 100px;">Kode</th>
                    <th>Nama Tipe Biaya</th>
                    <th>Kategori Beban</th>
                    <th>Akun Buku Besar</th>
                    <th>Satuan Default</th>
                    <th style="text-align: right;">Estimasi Tarif</th>
                    <th>Keterangan</th>
                    <th>Status</th>
                    <th style="text-align: center; width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tipeBiayaList)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 30px; color: var(--text-muted);">
                        Belum ada data tipe biaya. Klik "Tambah Tipe Biaya" untuk menambahkan.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($tipeBiayaList as $b): ?>
                    <tr>
                        <td>
                            <span class="badge" style="background: rgba(3, 105, 161, 0.12); color: var(--aktif-fg); font-family: 'JetBrains Mono', monospace; font-weight: 800; font-size: 12px;">
                                <?= htmlspecialchars($b['kode_biaya']) ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color: var(--text-main); font-size: 14px;"><?= htmlspecialchars($b['nama_biaya']) ?></strong>
                        </td>
                        <td>
                            <?php
                            $badgeKategori = 'badge-purple';
                            if ($b['kategori_beban'] === 'ASET') $badgeKategori = 'badge-info';
                            elseif ($b['kategori_beban'] === 'HPP') $badgeKategori = 'badge-warning';
                            ?>
                            <span class="badge <?= $badgeKategori ?>" style="font-weight: 700; font-size: 11px;">
                                <?= htmlspecialchars($b['kategori_beban']) ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--aktif-fg); font-size: 12px;">
                                [<?= htmlspecialchars($b['kode_akun']) ?>]
                            </span>
                            <span style="font-size: 12.5px; font-weight: 600; color: var(--text-main);">
                                <?= htmlspecialchars($b['nama_akun']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge" style="background: var(--bg-muted); color: var(--text-muted); font-weight: 600;">
                                <?= htmlspecialchars($b['satuan_default'] ?: 'Pcs') ?>
                            </span>
                        </td>
                        <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--ok-fg); font-size: 13px;">
                            Rp <?= number_format((float)$b['estimasi_harga'], 0, ',', '.') ?>
                        </td>
                        <td style="font-size: 12.5px; color: var(--text-muted); max-width: 200px;">
                            <?= htmlspecialchars($b['keterangan'] ?: '-') ?>
                        </td>
                        <td>
                            <a href="javascript:void(0)" 
                               onclick="confirmToggleTipeBiaya(<?= $b['id'] ?>, '<?= htmlspecialchars($b['nama_biaya'], ENT_QUOTES) ?>', <?= $b['is_active'] ? 'true' : 'false' ?>)"
                               class="btn btn-sm" 
                               style="<?= $b['is_active'] 
                                   ? 'background: var(--ok-bg); color: var(--ok-fg); border: 1.5px solid var(--border-hover);' 
                                   : 'background: var(--bg-muted); color: var(--text-muted); border: 1.5px solid var(--border);' 
                               ?> border-radius: 20px; font-weight: 800; font-size: 11.5px; padding: 4px 12px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; cursor: pointer; transition: all 0.2s;"
                               title="<?= $b['is_active'] ? 'Klik untuk nonaktifkan' : 'Klik untuk aktifkan' ?>">
                                <?php if ($b['is_active']): ?>
                                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--ok-solid); box-shadow: 0 0 6px rgba(16, 185, 129, 0.6);"></span>
                                    <span>Aktif</span>
                                    <i data-lucide="power" style="width: 12px; height: 12px; opacity: 0.7;"></i>
                                <?php else: ?>
                                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--text-muted);"></span>
                                    <span>Nonaktif</span>
                                    <i data-lucide="power" style="width: 12px; height: 12px; opacity: 0.7;"></i>
                                <?php endif; ?>
                            </a>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: flex; justify-content: center; gap: 6px;">
                                <button type="button" class="btn btn-outline btn-sm" onclick='editTipeBiaya(<?= json_encode($b) ?>)' title="Edit Tipe Biaya">
                                    <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                                    <span>Edit</span>
                                </button>
                                <a href="javascript:void(0)" 
                                   onclick="confirmDeleteTipeBiaya(<?= $b['id'] ?>, '<?= htmlspecialchars($b['nama_biaya'], ENT_QUOTES) ?>')" 
                                   class="btn btn-outline btn-sm" 
                                   style="color: var(--bad-fg); border-color: rgba(239, 68, 68, 0.3);" 
                                   title="Hapus Tipe Biaya Permanen">
                                    <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================================= -->
<!-- TAB: MASTER HARGA PEMASOK (HARGA BELI PER JENIS BARANG)                   -->
<!-- ========================================================================= -->
<?php elseif ($activeTab === 'harga_pemasok'): ?>
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">
                <i data-lucide="tag" style="width: 20px; height: 20px; color: var(--warn-fg);"></i>
                <span>Master Harga Pemasok</span>
            </h2>
            <div class="card-description">
                Tarif harga beli persediaan per Kg untuk setiap jenis barang dari masing-masing pemasok. Harga ini menjadi dasar penghitungan HPP dan profit saat barang dikeluarkan ke mitra.
            </div>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-outline btn-sm" onclick="location.reload()">
                <i data-lucide="refresh-cw" style="width: 14px; height: 14px;"></i>
                <span>Segarkan</span>
            </button>
        </div>
    </div>

    <!-- Alert Info Penjelasan -->
    <div class="alert alert-info" style="margin: 0 20px 20px 20px;">
        <i data-lucide="info" style="width: 20px; height: 20px; flex-shrink: 0;"></i>
        <div style="font-size: 13px; line-height: 1.5;">
            <strong>Kontrak Harga Beli per Produk SKU:</strong> Setiap pemasok memiliki kontrak harga beli untuk setiap produk SKU. Tarif ini digunakan untuk menghitung modal pembelian dan dianalisa pada menu <strong>Laba Rugi</strong>.
        </div>
    </div>

    <form id="formBulkHargaPemasok" action="index.php?action=save_bulk_harga_pemasok" method="POST">
        <div class="table-responsive">
            <table class="table-custom" id="tableMasterHargaPemasok">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">No</th>
                        <th style="min-width: 200px;">Produk / Item SKU</th>
                        <th style="width: 110px;">Kategori</th>
                        <?php foreach ($priceMatrixPemasok['pemasoks'] as $p): ?>
                            <th style="text-align: center; min-width: 155px; background: rgba(245, 158, 11, 0.05);">
                                <span class="badge badge-warning" style="font-size: 11px; font-weight: 800;">
                                    <?= htmlspecialchars($p['kode_pemasok']) ?>
                                </span>
                                <div style="font-size: 11.5px; margin-top: 3px; color: var(--text-muted); font-weight: 700;">
                                    <?= htmlspecialchars(substr($p['nama_pemasok'], 0, 16)) ?>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($priceMatrixPemasok['matrix'])): ?>
                        <tr>
                            <td colspan="<?= 3 + count($priceMatrixPemasok['pemasoks']) ?>" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                Belum ada data produk terdaftar.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $pNo = 1; foreach ($priceMatrixPemasok['matrix'] as $row): $it = $row['item']; ?>
                        <tr>
                            <td style="text-align: center; color: var(--text-muted);"><?= $pNo++ ?></td>
                            <td>
                                <strong style="color: var(--text-main); font-size: 13.5px;"><?= htmlspecialchars($it['nama_item']) ?></strong>
                                <div style="font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-muted);">
                                    <?= htmlspecialchars($it['kode_item']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-outline" style="font-size: 11px;">
                                    <?= htmlspecialchars($it['nama_jenis']) ?>
                                </span>
                            </td>
                            <?php foreach ($priceMatrixPemasok['pemasoks'] as $p): 
                                $val = $row['prices'][$p['id']] ?? 0.00;
                            ?>
                                <td style="text-align: center; background: rgba(245, 158, 11, 0.02);">
                                    <div style="display: flex; align-items: center; justify-content: center; gap: 4px;">
                                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 700;">Rp</span>
                                        <input type="number" 
                                               step="any" 
                                               min="0" 
                                               name="harga_pemasok[<?= $p['id'] ?>][<?= $it['id'] ?>]" 
                                               value="<?= $val > 0 ? ($val == (int)$val ? (int)$val : (float)$val) : 0 ?>" 
                                               class="form-control" 
                                               style="width: 115px; text-align: right; font-weight: 800; color: var(--warn-fg); padding: 5px 8px; font-size: 13px;"
                                               title="Harga Beli Rp/kg untuk <?= htmlspecialchars($p['nama_pemasok']) ?> - <?= htmlspecialchars($it['nama_item']) ?>"
                                               placeholder="0">
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="padding: 16px 20px; background: var(--bg-muted); display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border);">
            <div style="font-size: 12.5px; color: var(--text-muted);">
                Perubahan tarif beli pemasok ini otomatis terhubung ke perhitungan modal beli dan menu Laba Rugi.
            </div>
            <input type="text" name="alasan_perubahan" class="form-control" placeholder="Alasan perubahan harga (opsional, masuk ke log)" style="max-width: 340px; margin-left: auto; margin-right: 12px;">
            <button type="submit" class="btn btn-primary btn-lg" style="background: var(--warn-solid); border-color: var(--warn-solid); color: #fff;">
                <i data-lucide="check" style="width: 18px; height: 18px;"></i>
                <span>Simpan Perubahan Harga Pemasok</span>
            </button>
        </div>
    </form>
</div>

<!-- ========================================================================= -->
<!-- TAB: MASTER HARGA MITRA (HARGA JUAL POKOK KE 7 MITRA BISNIS)               -->
<!-- ========================================================================= -->
<?php elseif ($activeTab === 'harga_mitra' || $activeTab === 'harga'): ?>
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">
                <i data-lucide="badge-dollar-sign" style="width: 20px; height: 20px; color: var(--ok-fg);"></i>
                <span>Master Harga Mitra</span>
            </h2>
            <div class="card-description">
                Tarif harga jual kontrak per Kg untuk masing-masing 7 mitra bisnis. Nilai ini menjadi dasar pendapatan saat barang dikeluarkan dari gudang.
            </div>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-outline btn-sm" onclick="location.reload()">
                <i data-lucide="refresh-cw" style="width: 14px; height: 14px;"></i>
                <span>Segarkan</span>
            </button>
        </div>
    </div>

    <!-- Alert Info Penjelasan -->
    <div class="alert alert-info" style="margin: 0 20px 20px 20px;">
        <i data-lucide="info" style="width: 20px; height: 20px; flex-shrink: 0;"></i>
        <div style="font-size: 13px; line-height: 1.5;">
            <strong>Integrasi Harga Jual Mitra:</strong> Setiap dari 7 mitra bisnis memiliki kontrak tarif harga jual berbeda-beda. Saat melakukan <em>Pengeluaran Barang</em> dari stok pemasok ke mitra tujuan, harga di bawah ini otomatis diterapkan untuk menghasilkan nilai total invoice dan laba bersih.
        </div>
    </div>

    <form id="formBulkHargaMitra" action="index.php?action=save_bulk_harga_mitra" method="POST">
        <div class="table-responsive">
            <table class="table-custom" id="tableMasterHargaMitra">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">No</th>
                        <th style="min-width: 200px;">Produk / Item SKU</th>
                        <th style="width: 110px;">Kategori</th>
                        <?php foreach ($priceMatrix['mitras'] as $m): ?>
                            <th style="text-align: center; min-width: 140px; background: rgba(59, 130, 246, 0.04);">
                                <span class="mitra-tag <?= strtolower($m['kode_mitra']) ?>" style="background: <?= htmlspecialchars($m['warna_identitas']) ?>; font-size: 11px;">
                                    <?= htmlspecialchars($m['kode_mitra']) ?>
                                </span>
                                <div style="font-size: 11px; margin-top: 3px; color: var(--text-muted); font-weight: 600;">
                                    <?= htmlspecialchars(substr($m['nama_mitra'], 0, 14)) ?>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($priceMatrix['matrix'] as $row): $it = $row['item']; ?>
                    <tr>
                        <td style="text-align: center; color: var(--text-muted);"><?= $no++ ?></td>
                        <td>
                            <strong style="color: var(--text-main); font-size: 13.5px;"><?= htmlspecialchars($it['nama_item']) ?></strong>
                            <div style="font-family: 'JetBrains Mono', monospace; font-size: 11.5px; color: var(--text-muted);">
                                <?= htmlspecialchars($it['kode_item']) ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-outline" style="font-size: 11px;">
                                <?= htmlspecialchars($it['nama_jenis']) ?>
                            </span>
                        </td>
                        <?php foreach ($priceMatrix['mitras'] as $m): 
                            $hargaVal = $row['prices'][$m['id']] ?? 0.00;
                        ?>
                            <td style="text-align: center; background: rgba(59, 130, 246, 0.02);">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 4px;">
                                    <span style="font-size: 11px; color: var(--text-muted); font-weight: 700;">Rp</span>
                                    <input type="number" 
                                           step="any" 
                                           min="0" 
                                           name="harga[<?= $m['id'] ?>][<?= $it['id'] ?>]" 
                                           value="<?= $hargaVal > 0 ? ($hargaVal == (int)$hargaVal ? (int)$hargaVal : (float)$hargaVal) : 0 ?>" 
                                           class="form-control" 
                                           style="width: 105px; text-align: right; font-weight: 800; color: var(--ok-fg); padding: 5px 8px; font-size: 13px;"
                                           title="Harga Rp/kg untuk <?= htmlspecialchars($m['kode_mitra']) ?> - <?= htmlspecialchars($it['nama_item']) ?>"
                                           placeholder="0">
                                </div>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="padding: 16px 20px; background: var(--bg-muted); display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border);">
            <div style="font-size: 12.5px; color: var(--text-muted);">
                Semua input harga jual mitra otomatis tersimpan ke master dan terhubung ke Stasiun Pengeluaran Barang.
            </div>
            <input type="text" name="alasan_perubahan" class="form-control" placeholder="Alasan perubahan harga (opsional, masuk ke log)" style="max-width: 340px; margin-left: auto; margin-right: 12px;">
            <button type="submit" class="btn btn-success btn-lg">
                <i data-lucide="check" style="width: 18px; height: 18px;"></i>
                <span>Simpan Perubahan Harga Mitra</span>
            </button>
        </div>
    </form>
</div>

<!-- ========================================================================= -->
<!-- TAB 2: MASTER MITRA BISNIS (7 MITRA)                                      -->
<!-- ========================================================================= -->
<?php elseif ($activeTab === 'mitra'): ?>
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">
                <i data-lucide="building-2" style="width: 20px; height: 20px; color: var(--aktif-fg);"></i>
                <span>Daftar 7 Mitra Bisnis Terdaftar</span>
            </h2>
            <div class="card-description">Setiap mitra memiliki segregasi stok tersendiri dan dipetakan ke 1 ID Gudang Accurate Online</div>
        </div>
        <button type="button" class="btn btn-primary" onclick="openModalMitra()">
            <i data-lucide="plus" style="width: 16px; height: 16px;"></i>
            <span>Tambah Mitra</span>
        </button>
    </div>

    <div class="table-responsive">
        <table class="table-custom" id="tableMasterMitra">
            <thead>
                <tr>
                    <th style="width: 70px;">Kode</th>
                    <th>Nama Mitra Bisnis</th>
                    <th>Gudang Accurate</th>
                    <th>PIC & Kontak</th>
                    <th>Warna Identitas</th>
                    <th>Status</th>
                    <th style="text-align: center; width: 120px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mitraList as $m): ?>
                <tr>
                    <td>
                        <span class="mitra-tag <?= strtolower($m['kode_mitra']) ?>" style="background: <?= htmlspecialchars($m['warna_identitas'] ?? '#3b82f6') ?>; font-size: 12px; font-weight: 800;">
                            <?= htmlspecialchars($m['kode_mitra']) ?>
                        </span>
                    </td>
                    <td>
                        <strong style="color: var(--text-main); font-size: 14px;"><?= htmlspecialchars($m['nama_mitra']) ?></strong>
                    </td>
                    <td>
                        <code style="font-weight: 700; color: var(--aktif-fg);"><?= htmlspecialchars($m['kode_gudang_accurate']) ?></code>
                    </td>
                    <td>
                        <div><strong style="font-size: 13px;"><?= htmlspecialchars($m['pic_nama'] ?: '-') ?></strong></div>
                        <div style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($m['pic_telepon'] ?: '-') ?></div>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="display: inline-block; width: 18px; height: 18px; border-radius: 4px; background: <?= htmlspecialchars($m['warna_identitas']) ?>; border: 1px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.2);"></span>
                            <code style="font-size: 11px;"><?= htmlspecialchars($m['warna_identitas']) ?></code>
                        </div>
                    </td>
                    <td>
                        <?php if ($m['is_active']): ?>
                            <span class="badge badge-success">● Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <div style="display: flex; justify-content: center; gap: 6px;">
                        <button type="button" class="btn btn-outline btn-sm" onclick='editMitra(<?= json_encode($m) ?>)'>
                            <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                            <span>Edit</span>
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" style="color: var(--bad-fg); border-color: rgba(239, 68, 68, 0.3);" title="Hapus" onclick="hapusMaster('delete_mitra', <?= (int)$m['id'] ?>, '<?= htmlspecialchars($m['nama_mitra'], ENT_QUOTES) ?>')"><i data-lucide="trash-2" style="width: 14px; height: 14px;"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================================= -->
<!-- TAB 3: MASTER PRODUK                                                      -->
<!-- ========================================================================= -->
<?php elseif ($activeTab === 'produk'): ?>
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">
                <i data-lucide="boxes" style="width: 20px; height: 20px; color: var(--aktif-fg);"></i>
                <span>Master Produk</span>
            </h2>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-outline" onclick="openModalKategori()">
                <i data-lucide="tag" style="width: 16px; height: 16px; color: var(--aktif-fg);"></i>
                <span>Kelola Kategori Master</span>
            </button>
            <button type="button" class="btn btn-primary" onclick="openModalItem()">
                <i data-lucide="plus" style="width: 16px; height: 16px;"></i>
                <span>Tambah Produk</span>
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table-custom" id="tableMasterProduk">
            <thead>
                <tr>
                    <th style="width: 160px;">Kode SKU</th>
                    <th>Nama Barang / Produk</th>
                    <th>Kategori</th>
                    <th style="text-align: center; width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itemList as $it): ?>
                <tr>
                    <td>
                        <strong style="font-family: 'JetBrains Mono', monospace; font-size: 13px; color: var(--primary);">
                            <?= htmlspecialchars($it['kode_item']) ?>
                        </strong>
                    </td>
                    <td>
                        <strong style="color: var(--text-main); font-size: 15px;"><?= htmlspecialchars($it['nama_item']) ?></strong>
                    </td>
                    <td>
                        <span class="badge badge-purple" style="font-size: 12px; font-weight: 700;">
                            <?= htmlspecialchars($it['nama_jenis']) ?>
                        </span>
                    </td>
                    <td style="text-align: center;">
                        <div style="display: flex; justify-content: center; gap: 6px;">
                            <button type="button" class="btn btn-outline btn-sm" onclick='editItem(<?= json_encode($it) ?>)'>
                                <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                                <span>Edit</span>
                            </button>
                            <button type="button" class="btn btn-outline btn-sm" style="color: var(--bad-fg); border-color: rgba(239, 68, 68, 0.3);" title="Hapus" onclick="hapusMaster('delete_item', <?= (int)$it['id'] ?>, '<?= htmlspecialchars($it['nama_item'], ENT_QUOTES) ?>')"><i data-lucide="trash-2" style="width: 14px; height: 14px;"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================================= -->
<!-- TAB ACCURATE: SETTINGAN API & ANTREAN SINKRONISASI ACCURATE ONLINE        -->
<!-- ========================================================================= -->
<?php elseif ($activeTab === 'accurate'): ?>

<!-- 1. STATUS KONEKSI & PANEL PENGATURAN KREDENSIAL ACCURATE -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
        <div>
            <h2 class="card-title">
                <i data-lucide="key" style="width: 18px; height: 18px; color: var(--primary);"></i>
                <span>Konfigurasi API Token & Kredensial Developer</span>
            </h2>
            <div class="card-description">Otorisasi sistem barcode ke database Accurate Online menggunakan API Token resmi</div>
        </div>
        <div>
            <?php if (!empty($cfg['is_connected']) && !empty($cfg['session_id'])): ?>
                <span class="badge badge-success" style="padding: 6px 12px; font-size: 12px;">
                    <i data-lucide="check-circle-2" style="width: 14px; height: 14px; margin-right: 4px;"></i>
                    Live API Terhubung: <strong><?= htmlspecialchars($cfg['db_alias'] ?: ('ID ' . $cfg['db_id'])) ?></strong>
                </span>
            <?php elseif (!empty($cfg['api_token'])): ?>
                <span class="badge badge-warning" style="padding: 6px 12px; font-size: 12px;">
                    <i data-lucide="clock" style="width: 14px; height: 14px; margin-right: 4px;"></i>
                    Token Tersimpan
                </span>
            <?php else: ?>
                <span class="badge badge-purple" style="padding: 6px 12px; font-size: 12px;">
                    <i data-lucide="shield-alert" style="width: 14px; height: 14px; margin-right: 4px;"></i>
                    Mode Simulasi / Menunggu API Token
                </span>
            <?php endif; ?>
        </div>
    </div>

    <form id="formAccurateConfig" onsubmit="saveAccurateConfig(event)" style="padding: 16px 20px;">
        <div class="grid grid-cols-2" style="gap: 18px; margin-bottom: 16px;">
            <!-- Kolom Kiri: App Key & Signature Secret -->
            <div>
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">
                        App Key:
                    </label>
                    <div style="display: flex; gap: 6px;">
                        <input type="text" id="cfgAppKey" class="form-control" value="<?= htmlspecialchars($cfg['app_key']) ?>" readonly style="background: var(--bg-muted); font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 600;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="copyToClipboard('cfgAppKey')" title="Salin App Key">
                            <i data-lucide="copy" style="width: 14px; height: 14px;"></i>
                        </button>
                    </div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Gunakan App Key ini saat mencari dan menginstall aplikasi di menu Accurate Store.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">
                        Signature Secret:
                    </label>
                    <div style="display: flex; gap: 6px;">
                        <input type="password" id="cfgSecret" class="form-control" value="<?= htmlspecialchars($cfg['signature_secret']) ?>" readonly style="background: var(--bg-muted); font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 600;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="togglePasswordVisibility('cfgSecret')" title="Lihat Secret">
                            <i data-lucide="eye" style="width: 14px; height: 14px;"></i>
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="copyToClipboard('cfgSecret')" title="Salin Signature Secret">
                            <i data-lucide="copy" style="width: 14px; height: 14px;"></i>
                        </button>
                    </div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Digunakan untuk menghasilkan tanda tangan HMAC-SHA256 pada header setiap request API.</div>
                </div>
            </div>

            <!-- Kolom Kanan: API Token & Database Terpilih -->
            <div>
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px; display: flex; justify-content: space-between;">
                        <span>API Token:</span>
                        <a href="javascript:void(0)" onclick="openGuideModal()" style="font-size: 11px; color: var(--primary); font-weight: 600; text-decoration: underline;">Cara dapatkan token?</a>
                    </label>
                    <textarea id="inputApiToken" name="api_token" class="form-control" rows="2" placeholder="Tempelkan (Paste) API Token panjang dari Accurate Online di sini..." style="font-family: 'JetBrains Mono', monospace; font-size: 12px; resize: vertical;"><?= htmlspecialchars($cfg['api_token']) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">
                        Database Perusahaan Terhubung:
                    </label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="text" id="inputDbAlias" name="db_alias" class="form-control" value="<?= htmlspecialchars($cfg['db_alias'] ?: ($cfg['db_id'] ? 'ID ' . $cfg['db_id'] : '')) ?>" placeholder="Belum ada database dipilih" readonly style="background: var(--bg-muted); font-size: 12.5px; font-weight: 700;">
                        <input type="hidden" id="inputDbId" name="db_id" value="<?= htmlspecialchars($cfg['db_id']) ?>">
                        <button type="button" class="btn btn-outline" id="btnTestConnect" onclick="testAndFetchDatabases()">
                            <i data-lucide="radio" style="width: 15px; height: 15px; color: var(--aktif-fg);"></i>
                            <span>Tes & Pilih DB</span>
                        </button>
                    </div>
                    <div id="dbStatusInfo" style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                        Host: <code><?= htmlspecialchars($cfg['host']) ?></code> • Session: <code><?= !empty($cfg['session_id']) ? substr($cfg['session_id'], 0, 16) . '...' : '(belum ada sesi)' ?></code>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tombol Aksi Simpan -->
        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 14px;">
            <div style="font-size: 12px; color: var(--text-muted);">
                <i data-lucide="info" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle;"></i>
                Setelah token dan database tersimpan, setiap penimbangan/pengeluaran otomatis terkirim langsung ke Accurate.
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" id="btnSaveConfig">
                    <i data-lucide="save" style="width: 16px; height: 16px;"></i>
                    <span>Simpan Pengaturan API</span>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- 2. TABEL ANTREAN SINKRONISASI -->
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">
                <i data-lucide="list-ordered" style="width: 18px; height: 18px; color: var(--purple);"></i>
                <span>Antrean Pengiriman Transaksi Gudang ke Accurate</span>
            </h2>
            <div class="card-description">Daftar dokumen transaksi pengeluaran dan mutasi yang siap tersinkron ke Accurate Online</div>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <?php $eksporTabel = 'tableAccurateSync'; $eksporNama = 'Log_Antrean_Accurate_Online'; include __DIR__ . '/layout/ekspor.php'; ?>
            <span class="badge badge-purple"><?= count($queueList) ?> Dokumen</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table-custom" id="tableAccurateSync">
            <thead>
                <tr>
                    <th>ID Antrean</th>
                    <th>No. Transaksi Gudang</th>
                    <th>Mitra Pemilik</th>
                    <th style="text-align: right;">Total Netto</th>
                    <th>Endpoint API Accurate</th>
                    <th>Percobaan</th>
                    <th>Status</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queueList)): ?>
                    <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 34px;">Belum ada antrean transaksi. Transaksi pengeluaran barang akan otomatis masuk ke sini.</td></tr>
                <?php else: ?>
                    <?php foreach ($queueList as $q): ?>
                    <tr>
                        <td><code>#<?= $q['id'] ?></code></td>
                        <td>
                            <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--text-main);"><?= htmlspecialchars($q['no_transaksi']) ?></strong>
                            <div style="font-size: 11px; color: var(--text-muted);"><?= Helper::formatDateTime($q['created_at']) ?></div>
                        </td>
                        <td>
                            <span class="mitra-tag <?= strtolower($q['kode_mitra']) ?>" style="background: <?= htmlspecialchars($q['warna_identitas'] ?? '#0284c7') ?>; padding: 2px 7px; font-size: 11px;">
                                <?= htmlspecialchars($q['kode_mitra']) ?>
                            </span>
                            <span style="font-size: 12px; margin-left: 5px; font-weight: 600;"><?= htmlspecialchars($q['nama_mitra']) ?></span>
                        </td>
                        <td style="text-align: right; font-weight: 800; color: var(--ok-fg); font-size: 14px;"><?= Helper::formatKg($q['total_kg']) ?></td>
                        <td><code style="font-size: 11px;"><?= htmlspecialchars($q['endpoint']) ?></code></td>
                        <td><strong><?= $q['attempt_count'] ?></strong> / <?= $q['max_attempts'] ?></td>
                        <td>
                            <?php if ($q['status'] === 'SUCCESS'): ?>
                                <span class="badge badge-success">Terkirim</span>
                            <?php elseif ($q['status'] === 'PENDING'): ?>
                                <span class="badge badge-warning">Pending</span>
                            <?php else: ?>
                                <span class="badge badge-destructive">Gagal</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: flex; gap: 6px; justify-content: center;">
                                <button type="button" class="btn btn-outline btn-sm" onclick="showPayload(<?= htmlspecialchars(json_encode($q['payload_json'])) ?>)">
                                    JSON
                                </button>
                                <?php if ($q['status'] !== 'SUCCESS'): ?>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="syncQueue(<?= $q['id'] ?>)">
                                        Kirim
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- ========================================================================= -->
<!-- MODAL TAMBAH / EDIT MITRA                                                 -->
<!-- ========================================================================= -->
<div id="modalMitra" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-card); width: 100%; max-width: 500px; border-radius: var(--radius); border: 1.5px solid var(--border); box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3 id="modalMitraTitle" style="font-size: 16px; font-weight: 800; color: var(--text-main); margin: 0;">Tambah Mitra Bisnis</h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeModalMitra()">✕</button>
        </div>
        <form action="index.php?action=save_mitra" method="POST" style="padding: 20px;">
            <input type="hidden" name="id" id="mitra_id_input" value="">
            <div class="form-group">
                <label class="form-label">Kode Mitra</label>
                <input type="text" name="kode_mitra" id="input_kode_mitra" class="form-control" readonly tabindex="-1" placeholder="Otomatis" style="background: var(--bg-muted); font-family: 'JetBrains Mono', monospace; font-weight: 700; cursor: not-allowed;">
            </div>
            <div class="form-group">
                <label class="form-label">Nama Mitra Bisnis <span style="color:red;">*</span></label>
                <input type="text" name="nama_mitra" id="input_nama_mitra" class="form-control" required placeholder="Contoh: PT Berkah Daging Nusantara">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Nama PIC Kontak</label>
                    <input type="text" name="pic_nama" id="input_pic_nama" class="form-control" placeholder="H. Syaiful">
                </div>
                <div class="form-group">
                    <label class="form-label">Nomor Telepon / WA</label>
                    <input type="text" name="pic_telepon" id="input_pic_telepon" class="form-control" placeholder="0812-xxxx-xxxx">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Kode Gudang Accurate</label>
                    <input type="text" name="kode_gudang_accurate" id="input_kode_gudang" class="form-control" placeholder="GDG-M08">
                </div>
                <div class="form-group">
                    <label class="form-label">Warna Badge</label>
                    <input type="color" name="warna_identitas" id="input_warna" class="form-control" value="#3b82f6" style="height: 40px; padding: 2px;">
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-outline" onclick="closeModalMitra()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Mitra</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL TAMBAH / EDIT ITEM SKU (TANPA SATUAN BERAT)                         -->
<!-- ========================================================================= -->
<div id="modalItem" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-card); width: 100%; max-width: 520px; border-radius: var(--radius); border: 1.5px solid var(--border); box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3 id="modalItemTitle" style="font-size: 16px; font-weight: 800; color: var(--text-main); margin: 0;">Tambah Produk</h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeModalItem()">✕</button>
        </div>
        <form action="index.php?action=save_item" method="POST" style="padding: 20px;">
            <input type="hidden" name="id" id="item_id_input" value="">
            <input type="hidden" name="satuan_accurate" id="input_satuan" value="KG">
            <div class="form-group">
                <label class="form-label">Kode Produk</label>
                <input type="text" name="kode_item" id="input_kode_item" class="form-control" readonly tabindex="-1" placeholder="Otomatis" style="background: var(--bg-muted); font-family: 'JetBrains Mono', monospace; font-weight: 700; cursor: not-allowed;">
            </div>
            <div class="form-group">
                <label class="form-label">Nama Barang / Produk <span style="color:red;">*</span></label>
                <input type="text" name="nama_item" id="input_nama_item" class="form-control" required placeholder="Contoh: Udang / Cumi / Ikan / Teri / Rebon">
            </div>
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <label class="form-label" style="margin: 0;">Kategori <span style="color:red;">*</span></label>
                    <button type="button" class="btn btn-outline btn-sm" onclick="openModalKategori()" style="font-size: 11px; padding: 2px 7px;">
                        Kelola Kategori
                    </button>
                </div>
                <select name="jenis_id" id="input_jenis_id" class="form-control" required onchange="if (!document.getElementById('item_id_input').value) onJenisItemChange()">
                    <?php foreach ($jenisList as $j): ?>
                        <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['nama_jenis']) ?> (<?= htmlspecialchars($j['kode_jenis']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-outline" onclick="closeModalItem()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Produk</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL KELOLA / EDIT KATEGORI (JENIS BARANG)                               -->
<!-- ========================================================================= -->
<div id="modalKategori" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-card); width: 100%; max-width: 560px; border-radius: var(--radius); border: 1.5px solid var(--border); box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 16px; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 6px;">
                <i data-lucide="tag" style="width: 18px; height: 18px; color: var(--aktif-fg);"></i>
                <span>Kelola Kategori</span>
            </h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeModalKategori()">✕</button>
        </div>

        <div style="padding: 20px;">
            <!-- Tabel Kategori Saat Ini -->
            <div style="font-size: 12px; font-weight: 800; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">
                Daftar Kategori Saat Ini:
            </div>
            <div class="table-responsive" style="max-height: 180px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 16px;">
                <table class="table-custom" style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Kode</th>
                            <th>Nama Kategori</th>
                            <th style="text-align: center; width: 80px;">Pilih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jenisList as $cat): ?>
                        <tr>
                            <td><code style="font-weight: 800; color: var(--primary);"><?= htmlspecialchars($cat['kode_jenis']) ?></code></td>
                            <td><strong><?= htmlspecialchars($cat['nama_jenis']) ?></strong></td>
                            <td style="text-align: center;">
                                <button type="button" class="btn btn-outline btn-sm" style="padding: 2px 7px; font-size: 11px;" onclick="editKategori(<?= json_encode($cat) ?>)">
                                    Edit
                                </button>
                                <button type="button" class="btn btn-outline btn-sm" style="padding: 2px 7px; font-size: 11px; color: var(--bad-fg); border-color: rgba(239, 68, 68, 0.3);" title="Hapus" onclick="hapusMaster('delete_jenis', <?= (int)$cat['id'] ?>, '<?= htmlspecialchars($cat['nama_jenis'], ENT_QUOTES) ?>')"><i data-lucide="trash-2" style="width: 12px; height: 12px;"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Form Tambah / Edit Kategori -->
            <form action="index.php?action=save_jenis" method="POST" style="background: var(--bg-muted); padding: 14px; border-radius: 8px; border: 1px solid var(--border);">
                <input type="hidden" name="id" id="kategori_id_input" value="">
                <div style="font-weight: 800; font-size: 13px; color: var(--text-main); margin-bottom: 10px;" id="formKategoriTitle">
                    Tambah / Edit Kategori:
                </div>
                <div style="display: grid; grid-template-columns: 100px 1fr auto; gap: 8px; align-items: end;">
                    <div>
                        <label class="form-label" style="font-size: 11px;">Kode</label>
                        <input type="text" name="kode_jenis" id="input_kode_jenis" class="form-control" readonly tabindex="-1" placeholder="Otomatis" style="height: 38px; background: var(--bg-muted); font-family: 'JetBrains Mono', monospace; font-weight: 700; cursor: not-allowed;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 11px;">Nama Kategori / Jenis</label>
                        <input type="text" name="nama_jenis" id="input_nama_jenis" class="form-control" required placeholder="Contoh: Ikan Fillet" style="height: 38px;">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary" style="height: 38px;">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL TAMBAH / EDIT PEMASOK                                               -->
<!-- ========================================================================= -->
<div id="modalPemasok" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-card); width: 100%; max-width: 520px; border-radius: var(--radius); border: 1.5px solid var(--border); box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3 id="modalPemasokTitle" style="font-size: 16px; font-weight: 800; color: var(--text-main); margin: 0;">Tambah Pemasok Baru</h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeModalPemasok()">✕</button>
        </div>
        <form action="index.php?action=save_pemasok" method="POST" style="padding: 20px;">
            <input type="hidden" name="id" id="pemasok_id_input" value="">
            <div style="display: grid; grid-template-columns: 130px 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Kode Pemasok <span style="color:red;">*</span></label>
                    <input type="text" name="kode_pemasok" id="input_kode_pemasok" class="form-control" required placeholder="Contoh: BH-01" style="text-transform: uppercase; font-family: 'JetBrains Mono', monospace; font-weight: 700;">
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Pemasok / Nelayan <span style="color:red;">*</span></label>
                    <input type="text" name="nama_pemasok" id="input_nama_pemasok" class="form-control" required placeholder="Contoh: Nelayan Muara Baru">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Nama Kontak / PIC</label>
                    <input type="text" name="pic_nama" id="input_pemasok_pic" class="form-control" placeholder="Pak Mahmud">
                </div>
                <div class="form-group">
                    <label class="form-label">No. Telepon / WA</label>
                    <input type="text" name="telepon" id="input_pemasok_telepon" class="form-control" placeholder="0812-xxxx-xxxx">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Alamat / Asal Barang</label>
                <textarea name="alamat" id="input_pemasok_alamat" class="form-control" rows="2" placeholder="Dermaga Muara Baru, Pelabuhan Perikanan, Tambak, dll."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Keterangan / Spesialisasi</label>
                <input type="text" name="keterangan" id="input_pemasok_keterangan" class="form-control" placeholder="Contoh: Pemasok Ikan Segar & Udang Tambak">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Status</label>
                <select name="is_active" id="input_pemasok_active" class="form-control">
                    <option value="1">Aktif</option>
                    <option value="0">Nonaktif</option>
                </select>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-outline" onclick="closeModalPemasok()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Pemasok</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL TAMBAH / EDIT VENDOR NON-PEMASOK                                    -->
<!-- ========================================================================= -->
<div id="modalVendorNonPemasok" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-card); width: 100%; max-width: 580px; border-radius: var(--radius); border: 1.5px solid var(--border); box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3 id="modalVendorNonPemasokTitle" style="font-size: 16px; font-weight: 800; color: var(--text-main); margin: 0;">Tambah Vendor Non-Pemasok</h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeModalVendorNonPemasok()">✕</button>
        </div>
        <form action="index.php?action=save_vendor_non_pemasok" method="POST" style="padding: 20px;">
            <input type="hidden" name="id" id="vendor_id_input" value="">
            <div style="display: grid; grid-template-columns: 140px 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Kode Vendor</label>
                    <input type="text" name="kode_vendor" id="input_kode_vendor" class="form-control" readonly tabindex="-1" placeholder="Otomatis" style="background: var(--bg-muted); font-family: 'JetBrains Mono', monospace; font-weight: 700; cursor: not-allowed;">
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Vendor / Toko Rekanan <span style="color:red;">*</span></label>
                    <input type="text" name="nama_vendor" id="input_nama_vendor" class="form-control" required placeholder="Contoh: Toko Es Balok Muara">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Kategori Vendor</label>
                    <select name="kategori_vendor" id="input_kategori_vendor" class="form-control">
                        <option value="Es Balok & Media Pendingin">Es Balok & Media Pendingin</option>
                        <option value="Kemasan & Packaging">Kemasan & Packaging</option>
                        <option value="Bahan Bakar & Energi">Bahan Bakar & Energi</option>
                        <option value="Logistik & Ekspedisi">Logistik & Ekspedisi</option>
                        <option value="Perawatan Mesin & Gudang">Perawatan Mesin & Gudang</option>
                        <option value="Peralatan & Aset">Peralatan & Aset Timbangan</option>
                        <option value="OPERASIONAL" selected>Operasional Umum / Lainnya</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Kontak / PIC</label>
                    <input type="text" name="pic_nama" id="input_vendor_pic" class="form-control" placeholder="Nama sales / pengelola">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">No. Telepon / WA</label>
                    <input type="text" name="telepon" id="input_vendor_telepon" class="form-control" placeholder="0812-xxxx-xxxx">
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Bank</label>
                    <input type="text" name="bank" id="input_vendor_bank" class="form-control" placeholder="BCA / Mandiri / BRI">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Nomor Rekening</label>
                    <input type="text" name="no_rekening" id="input_vendor_norek" class="form-control" placeholder="Contoh: 8820192837">
                </div>
                <div class="form-group">
                    <label class="form-label">Atas Nama Rekening</label>
                    <input type="text" name="nama_rekening" id="input_vendor_namarek" class="form-control" placeholder="Nama pemilik rekening">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Alamat Kantor / Toko</label>
                <textarea name="alamat" id="input_vendor_alamat" class="form-control" rows="2" placeholder="Alamat lengkap vendor..."></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 140px; gap: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Keterangan / Catatan</label>
                    <input type="text" name="keterangan" id="input_vendor_keterangan" class="form-control" placeholder="Contoh: Suplai es balok harian">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Status</label>
                    <select name="is_active" id="input_vendor_active" class="form-control">
                        <option value="1">Aktif</option>
                        <option value="0">Nonaktif</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-outline" onclick="closeModalVendorNonPemasok()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Vendor</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL TAMBAH / EDIT AKUN COA                                              -->
<!-- ========================================================================= -->
<div id="modalRekening" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-card); width: 100%; max-width: 480px; border-radius: var(--radius); border: 1.5px solid var(--border); box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3 id="modalRekeningTitle" style="font-size: 16px; font-weight: 800; color: var(--text-main); margin: 0;">Tambah Rekening</h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('modalRekening').style.display='none'">&#10005;</button>
        </div>
        <form action="index.php?action=save_rekening_bank" method="POST" style="padding: 20px;">
            <input type="hidden" name="id" id="rek_id" value="">
            <div class="form-group">
                <label class="form-label">Nama Bank <span style="color:red;">*</span></label>
                <input type="text" name="nama_bank" id="rek_bank" class="form-control" required placeholder="Contoh: BCA">
            </div>
            <div class="form-group">
                <label class="form-label">Nomor Rekening <span style="color:red;">*</span></label>
                <input type="text" name="no_rekening" id="rek_no" class="form-control" required inputmode="numeric" style="font-family: 'JetBrains Mono', monospace; font-weight: 700;">
            </div>
            <div class="form-group">
                <label class="form-label">Atas Nama</label>
                <input type="text" name="atas_nama" id="rek_atas" class="form-control" placeholder="Nama pemilik rekening">
            </div>
            <div class="form-group">
                <label class="form-label">Keterangan</label>
                <input type="text" name="keterangan" id="rek_ket" class="form-control" placeholder="Opsional">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalRekening').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Rekening</button>
            </div>
        </form>
    </div>
</div>

<div id="modalCoa" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-card); width: 100%; max-width: 500px; border-radius: var(--radius); border: 1.5px solid var(--border); box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3 id="modalCoaTitle" style="font-size: 16px; font-weight: 800; color: var(--text-main); margin: 0;">Tambah Akun</h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('modalCoa').style.display='none'">&#10005;</button>
        </div>
        <form action="index.php?action=save_coa" method="POST" style="padding: 20px;">
            <input type="hidden" name="edit" id="coa_edit" value="">
            <div class="form-group" id="coaIndukWrap">
                <label class="form-label">Akun Induk <span style="color:red;">*</span></label>
                <select name="induk" id="coa_induk" class="form-control" onchange="onCoaIndukChange(this.value)">
                    <?php foreach ($coaMaster as $kodeC => $c): ?>
                        <option value="<?= $kodeC ?>"><?= str_repeat('&nbsp;&nbsp;', $c[9]) ?><?= $kodeC ?> - <?= htmlspecialchars($c[0]) ?> (<?= $c[4] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: grid; grid-template-columns: 130px 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Kode <span style="color:red;">*</span></label>
                    <input type="text" name="kode" id="coa_kode" class="form-control" required maxlength="4" pattern="\d{4}" style="font-family: 'JetBrains Mono', monospace; font-weight: 700;">
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Akun <span style="color:red;">*</span></label>
                    <input type="text" name="nama" id="coa_nama" class="form-control" required placeholder="Contoh: Biaya Sewa Gudang">
                </div>
            </div>
            <div class="form-group" id="coaNormalWrap">
                <label class="form-label">Saldo Normal</label>
                <select name="saldo_normal" id="coa_normal" class="form-control">
                    <option value="Debit">Debit</option>
                    <option value="Credit">Kredit</option>
                </select>
                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 4px;">Otomatis mengikuti kelompok. Ubah hanya untuk akun pengurang (kontra), misalnya akumulasi penyusutan.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Keterangan</label>
                <input type="text" name="keterangan" id="coa_ket" class="form-control" placeholder="Opsional">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalCoa').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Akun</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL TAMBAH / EDIT TIPE BIAYA OPERASIONAL                                -->
<!-- ========================================================================= -->
<div id="modalTipeBiaya" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-card); width: 100%; max-width: 540px; border-radius: var(--radius); border: 1.5px solid var(--border); box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h3 id="modalTipeBiayaTitle" style="font-size: 16px; font-weight: 800; color: var(--text-main); margin: 0;">Tambah Tipe Biaya</h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeModalTipeBiaya()">✕</button>
        </div>
        <form action="index.php?action=save_tipe_biaya" method="POST" style="padding: 20px;">
            <input type="hidden" name="id" id="biaya_id_input" value="">
            <div style="display: grid; grid-template-columns: 130px 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Kode Biaya</label>
                    <input type="text" name="kode_biaya" id="input_kode_biaya" class="form-control" readonly tabindex="-1" placeholder="Otomatis" style="background: var(--bg-muted); font-family: 'JetBrains Mono', monospace; font-weight: 700; cursor: not-allowed;">
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Tipe Biaya <span style="color:red;">*</span></label>
                    <input type="text" name="nama_biaya" id="input_nama_biaya" class="form-control" required placeholder="Contoh: Es Balok Pendingin">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Kategori Beban</label>
                    <select name="kategori_beban" id="input_kategori_beban" class="form-control" onchange="onKategoriBebanChange(this.value)">
                        <option value="OPERASIONAL" selected>Operasional Rutin</option>
                        <option value="ASET">Aset / Inventaris</option>
                        <option value="HPP">HPP Pembelian</option>
                        <option value="LAINNYA">Lainnya</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Akun COA <span style="color:red;">*</span></label>
                    <select name="kode_akun" id="input_kode_akun" class="form-control" required>
                        <?php foreach ($coaList as $coa): ?>
                            <option value="<?= htmlspecialchars($coa['kode']) ?>">
                                [<?= htmlspecialchars($coa['kode']) ?>] <?= htmlspecialchars($coa['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Satuan Default</label>
                    <input type="text" name="satuan_default" id="input_satuan_default" class="form-control" placeholder="Pcs / Balok / Box / Liter / Trip">
                </div>
                <div class="form-group">
                    <label class="form-label">Estimasi Tarif / Harga Satuan (Rp)</label>
                    <input type="number" step="any" name="estimasi_harga" id="input_estimasi_harga" class="form-control" placeholder="0" style="font-family: 'JetBrains Mono', monospace; font-weight: 700;">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Keterangan / Penggunaan</label>
                <textarea name="keterangan" id="input_biaya_keterangan" class="form-control" rows="2" placeholder="Penjelasan singkat alokasi tipe biaya ini..."></textarea>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Status</label>
                <select name="is_active" id="input_biaya_active" class="form-control">
                    <option value="1">Aktif</option>
                    <option value="0">Nonaktif</option>
                </select>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-outline" onclick="closeModalTipeBiaya()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Tipe Biaya</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL DAFTAR DATABASE ACCURATE                                            -->
<!-- ========================================================================= -->
<div id="databaseSelectModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
    <div style="background-color: var(--bg-card); width: 90%; max-width: 620px; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow-lg); border: 1.5px solid var(--border);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--text-main);">Daftar Database Accurate Online</h3>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted);">Pilih database perusahaan yang akan disinkronisasikan dengan sistem gudang</p>
            </div>
            <button type="button" onclick="document.getElementById('databaseSelectModal').style.display='none'" class="btn btn-ghost btn-sm" style="font-size: 20px;">&times;</button>
        </div>

        <div id="dbListLoading" style="text-align: center; padding: 24px; color: var(--text-muted);">
            <i data-lucide="loader-2" class="spin" style="width: 28px; height: 28px; color: var(--primary); margin-bottom: 8px;"></i>
            <div>Menghubungi server Accurate Online & memvalidasi signature...</div>
        </div>

        <div id="dbListContainer" style="display: none; max-height: 320px; overflow-y: auto;">
            <!-- Item database diinjeksi via JavaScript -->
        </div>

        <div style="margin-top: 18px; text-align: right;">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('databaseSelectModal').style.display='none'">Batal</button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL PETUNJUK CARA MENGHUBUNGKAN ACCURATE                                -->
<!-- ========================================================================= -->
<div id="guideModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
    <div style="background-color: var(--bg-card); width: 90%; max-width: 720px; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow-lg); border: 1.5px solid var(--border); max-height: 85vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: var(--text-main);">Panduan Menghubungkan API Token Accurate Online</h3>
                <p style="margin: 2px 0 0 0; font-size: 12.5px; color: var(--text-muted);">Langkah mudah mengaktifkan koneksi dari dashboard Accurate Online pengguna</p>
            </div>
            <button type="button" onclick="document.getElementById('guideModal').style.display='none'" class="btn btn-ghost btn-sm" style="font-size: 20px;">&times;</button>
        </div>

        <div style="display: flex; flex-direction: column; gap: 14px; font-size: 13.5px; line-height: 1.6;">
            <div style="display: flex; gap: 12px; background: var(--bg-muted); padding: 12px 16px; border-radius: 8px;">
                <div style="background: var(--primary); color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0;">1</div>
                <div>
                    <strong>Buka Akun Accurate Online</strong>
                    <div style="color: var(--text-muted); font-size: 12.5px;">Buka <a href="https://accurate.id" target="_blank" style="color: var(--primary); font-weight: 600;">https://accurate.id</a> di browser Anda dan buka database perusahaan yang ingin dihubungkan.</div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; background: var(--bg-muted); padding: 12px 16px; border-radius: 8px;">
                <div style="background: var(--primary); color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0;">2</div>
                <div>
                    <strong>Masuk ke Menu Accurate Store</strong>
                    <div style="color: var(--text-muted); font-size: 12.5px;">Pada menu navigasi sebelah kiri, klik menu <strong>Pengaturan</strong> &gt; <strong>Accurate Store</strong>.</div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; background: var(--bg-muted); padding: 12px 16px; border-radius: 8px;">
                <div style="background: var(--primary); color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0;">3</div>
                <div>
                    <strong>Cari Aplikasi Menggunakan App Key</strong>
                    <div style="color: var(--text-muted); font-size: 12.5px;">
                        Cari aplikasi <code>api-warehouse</code> atau gunakan App Key:
                        <div style="margin-top: 4px; display: inline-block; background: #0b1120; color: #38bdf8; padding: 3px 8px; border-radius: 5px; font-family: monospace; font-size: 12px;">f33bdc72-f56f-4393-83f7-2b5ef042dc13</div>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; background: var(--bg-muted); padding: 12px 16px; border-radius: 8px;">
                <div style="background: var(--primary); color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0;">4</div>
                <div>
                    <strong>Install & Buat API Token</strong>
                    <div style="color: var(--text-muted); font-size: 12.5px;">Klik tombol <strong>Install Aplikasi</strong>, lalu klik <strong>Buat API Token</strong>. Salin kode token panjang yang muncul.</div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; background: var(--bg-muted); padding: 12px 16px; border-radius: 8px;">
                <div style="background: var(--ok-solid); color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0;">5</div>
                <div>
                    <strong>Tempel Token ke Sistem Gudang</strong>
                    <div style="color: var(--text-muted); font-size: 12.5px;">Tempel token tersebut ke kolom <strong>API Token</strong> di atas, lalu klik <strong>Tes & Pilih DB</strong> untuk menyimpan database. Selesai!</div>
                </div>
            </div>
        </div>

        <div style="margin-top: 20px; text-align: right;">
            <button type="button" class="btn btn-primary" onclick="document.getElementById('guideModal').style.display='none'">Mengerti, Tutup Panduan</button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL JSON PAYLOAD INSPECTOR                                              -->
<!-- ========================================================================= -->
<div id="jsonModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
    <div style="background-color: var(--bg-card); width: 90%; max-width: 650px; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow-lg); border: 1.5px solid var(--border);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--text-main);">Payload JSON REST API Accurate Online</h3>
            <button type="button" onclick="document.getElementById('jsonModal').style.display='none'" class="btn btn-ghost btn-sm" style="font-size: 18px;">&times;</button>
        </div>
        <pre id="jsonViewer" style="background-color: #09101d; color: #38bdf8; padding: 16px; border-radius: 10px; font-family: 'JetBrains Mono', monospace; font-size: 12px; max-height: 400px; overflow-y: auto; border: 1px solid #1e293b;"></pre>
        <div style="margin-top: 16px; text-align: right;">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('jsonModal').style.display='none'">Tutup</button>
        </div>
    </div>
</div>

<script>
    // Pemasok Modal Handling
    function openModalPemasok() {
        document.getElementById('modalPemasokTitle').innerText = 'Tambah Pemasok Baru';
        document.getElementById('pemasok_id_input').value = '';
        document.getElementById('input_kode_pemasok').value = '<?= \App\Models\Pemasok::generateNextKode() ?>';
        document.getElementById('input_nama_pemasok').value = '';
        document.getElementById('input_pemasok_pic').value = '';
        document.getElementById('input_pemasok_telepon').value = '';
        document.getElementById('input_pemasok_alamat').value = '';
        document.getElementById('input_pemasok_keterangan').value = '';
        document.getElementById('input_pemasok_active').value = '1';
        document.getElementById('modalPemasok').style.display = 'flex';
    }

    function editPemasok(p) {
        document.getElementById('modalPemasokTitle').innerText = 'Edit Pemasok: ' + p.nama_pemasok;
        document.getElementById('pemasok_id_input').value = p.id;
        document.getElementById('input_kode_pemasok').value = p.kode_pemasok;
        document.getElementById('input_nama_pemasok').value = p.nama_pemasok;
        document.getElementById('input_pemasok_pic').value = p.pic_nama || '';
        document.getElementById('input_pemasok_telepon').value = p.telepon || '';
        document.getElementById('input_pemasok_alamat').value = p.alamat || '';
        document.getElementById('input_pemasok_keterangan').value = p.keterangan || '';
        document.getElementById('input_pemasok_active').value = p.is_active;
        document.getElementById('modalPemasok').style.display = 'flex';
    }

    function closeModalPemasok() {
        document.getElementById('modalPemasok').style.display = 'none';
    }

    // Vendor Non-Pemasok Modal Handling
    function openModalVendorNonPemasok() {
        document.getElementById('modalVendorNonPemasokTitle').innerText = 'Tambah Vendor Non-Pemasok';
        document.getElementById('vendor_id_input').value = '';
        document.getElementById('input_kode_vendor').value = '<?= \App\Models\VendorNonPemasok::generateNextKode() ?>';
        document.getElementById('input_nama_vendor').value = '';
        document.getElementById('input_kategori_vendor').value = 'OPERASIONAL';
        document.getElementById('input_vendor_pic').value = '';
        document.getElementById('input_vendor_telepon').value = '';
        document.getElementById('input_vendor_bank').value = '';
        document.getElementById('input_vendor_norek').value = '';
        document.getElementById('input_vendor_namarek').value = '';
        document.getElementById('input_vendor_alamat').value = '';
        document.getElementById('input_vendor_keterangan').value = '';
        document.getElementById('input_vendor_active').value = '1';
        document.getElementById('modalVendorNonPemasok').style.display = 'flex';
    }

    function editVendorNonPemasok(v) {
        document.getElementById('modalVendorNonPemasokTitle').innerText = 'Edit Vendor: ' + v.nama_vendor;
        document.getElementById('vendor_id_input').value = v.id;
        document.getElementById('input_kode_vendor').value = v.kode_vendor;
        document.getElementById('input_nama_vendor').value = v.nama_vendor;
        document.getElementById('input_kategori_vendor').value = v.kategori_vendor || 'OPERASIONAL';
        document.getElementById('input_vendor_pic').value = v.pic_nama || '';
        document.getElementById('input_vendor_telepon').value = v.telepon || '';
        document.getElementById('input_vendor_bank').value = v.bank || '';
        document.getElementById('input_vendor_norek').value = v.no_rekening || '';
        document.getElementById('input_vendor_namarek').value = v.nama_rekening || '';
        document.getElementById('input_vendor_alamat').value = v.alamat || '';
        document.getElementById('input_vendor_keterangan').value = v.keterangan || '';
        document.getElementById('input_vendor_active').value = v.is_active;
        document.getElementById('modalVendorNonPemasok').style.display = 'flex';
    }

    function closeModalVendorNonPemasok() {
        document.getElementById('modalVendorNonPemasok').style.display = 'none';
    }

    // Master COA
    const COA_SARAN = <?= json_encode((function () use ($coaMaster) { $o = []; foreach ($coaMaster as $k => $c) { $o[$k] = [\App\Services\CoaService::sarankanKode((string)$k), $c[2]]; } return $o; })()) ?>;
    function onCoaIndukChange(induk) {
        const s = COA_SARAN[induk];
        if (!s) return;
        document.getElementById('coa_kode').value = s[0];
        document.getElementById('coa_normal').value = s[1];
    }
    function openModalRekening() {
        document.getElementById('modalRekeningTitle').innerText = 'Tambah Rekening';
        ['rek_id', 'rek_bank', 'rek_no', 'rek_atas', 'rek_ket'].forEach(i => document.getElementById(i).value = '');
        document.getElementById('modalRekening').style.display = 'flex';
    }
    function editRekening(r) {
        document.getElementById('modalRekeningTitle').innerText = 'Edit Rekening ' + r.kode_rekening;
        document.getElementById('rek_id').value = r.id;
        document.getElementById('rek_bank').value = r.nama_bank || '';
        document.getElementById('rek_no').value = r.no_rekening || '';
        document.getElementById('rek_atas').value = r.atas_nama || '';
        document.getElementById('rek_ket').value = r.keterangan || '';
        document.getElementById('modalRekening').style.display = 'flex';
    }
    function openModalCoa(induk) {
        document.getElementById('modalCoaTitle').innerText = induk ? 'Tambah Sub-akun' : 'Tambah Akun';
        document.getElementById('coa_edit').value = '';
        document.getElementById('coaIndukWrap').style.display = '';
        document.getElementById('coaNormalWrap').style.display = '';
        const kode = document.getElementById('coa_kode');
        kode.readOnly = false; kode.style.background = '';
        const sel = document.getElementById('coa_induk');
        sel.disabled = false;
        if (induk) sel.value = induk;
        document.getElementById('coa_nama').value = '';
        document.getElementById('coa_ket').value = '';
        onCoaIndukChange(sel.value);
        document.getElementById('modalCoa').style.display = 'flex';
    }
    function editCoa(c) {
        document.getElementById('modalCoaTitle').innerText = 'Edit Akun ' + c.kode;
        document.getElementById('coa_edit').value = '1';
        document.getElementById('coaIndukWrap').style.display = 'none';
        document.getElementById('coaNormalWrap').style.display = 'none';
        document.getElementById('coa_induk').disabled = true;
        const kode = document.getElementById('coa_kode');
        kode.value = c.kode; kode.readOnly = true; kode.style.background = 'var(--bg-muted)';
        document.getElementById('coa_nama').value = c.nama;
        document.getElementById('coa_ket').value = c.keterangan || '';
        document.getElementById('modalCoa').style.display = 'flex';
    }

    // Tipe Biaya Modal Handling
    function openModalTipeBiaya() {
        document.getElementById('modalTipeBiayaTitle').innerText = 'Tambah Tipe Biaya';
        document.getElementById('biaya_id_input').value = '';
        document.getElementById('input_kode_biaya').value = '<?= \App\Models\TipeBiaya::generateNextKode() ?>';
        document.getElementById('input_nama_biaya').value = '';
        document.getElementById('input_kategori_beban').value = 'OPERASIONAL';
        document.getElementById('input_kode_akun').value = '6000';
        document.getElementById('input_satuan_default').value = 'Pcs';
        document.getElementById('input_estimasi_harga').value = '0';
        document.getElementById('input_biaya_keterangan').value = '';
        document.getElementById('input_biaya_active').value = '1';
        document.getElementById('modalTipeBiaya').style.display = 'flex';
    }

    function editTipeBiaya(b) {
        document.getElementById('modalTipeBiayaTitle').innerText = 'Edit Tipe Biaya: ' + b.nama_biaya;
        document.getElementById('biaya_id_input').value = b.id;
        document.getElementById('input_kode_biaya').value = b.kode_biaya;
        document.getElementById('input_nama_biaya').value = b.nama_biaya;
        document.getElementById('input_kategori_beban').value = b.kategori_beban || 'OPERASIONAL';
        document.getElementById('input_kode_akun').value = b.kode_akun || '6000';
        document.getElementById('input_satuan_default').value = b.satuan_default || 'Pcs';
        document.getElementById('input_estimasi_harga').value = b.estimasi_harga || '0';
        document.getElementById('input_biaya_keterangan').value = b.keterangan || '';
        document.getElementById('input_biaya_active').value = b.is_active;
        document.getElementById('modalTipeBiaya').style.display = 'flex';
    }

    function closeModalTipeBiaya() {
        document.getElementById('modalTipeBiaya').style.display = 'none';
    }

    function onKategoriBebanChange(cat) {
        const coaSelect = document.getElementById('input_kode_akun');
        if (!coaSelect) return;
        if (cat === 'ASET') {
            coaSelect.value = '1100';
        } else if (cat === 'HPP') {
            coaSelect.value = '5000';
        } else if (cat === 'OPERASIONAL') {
            coaSelect.value = '6000';
        }
    }


    // Mitra Modal Handling
    function openModalMitra() {
        document.getElementById('modalMitraTitle').innerText = 'Tambah Mitra Bisnis Baru';
        document.getElementById('mitra_id_input').value = '';
        document.getElementById('input_kode_mitra').value = '<?= \App\Models\Mitra::generateNextKode() ?>';
        document.getElementById('input_nama_mitra').value = '';
        document.getElementById('input_pic_nama').value = '';
        document.getElementById('input_pic_telepon').value = '';
        document.getElementById('input_kode_gudang').value = '';
        document.getElementById('input_warna').value = '#3b82f6';
        document.getElementById('modalMitra').style.display = 'flex';
    }

    function editMitra(m) {
        document.getElementById('modalMitraTitle').innerText = 'Edit Mitra: ' + m.kode_mitra;
        document.getElementById('mitra_id_input').value = m.id;
        document.getElementById('input_kode_mitra').value = m.kode_mitra;
        document.getElementById('input_nama_mitra').value = m.nama_mitra;
        document.getElementById('input_pic_nama').value = m.pic_nama || '';
        document.getElementById('input_pic_telepon').value = m.pic_telepon || '';
        document.getElementById('input_kode_gudang').value = m.kode_gudang_accurate || '';
        document.getElementById('input_warna').value = m.warna_identitas || '#3b82f6';
        document.getElementById('modalMitra').style.display = 'flex';
    }

    function closeModalMitra() {
        document.getElementById('modalMitra').style.display = 'none';
    }

    // Item Modal Handling
    const NEXT_KODE_ITEM = <?= json_encode(array_combine(array_map(fn($j) => (string)$j['id'], $jenisList), array_map(fn($j) => \App\Models\Item::generateNextKode((int)$j['id']), $jenisList)), JSON_UNESCAPED_UNICODE) ?>;

    function onJenisItemChange() {
        const sel = document.getElementById('input_jenis_id');
        document.getElementById('input_kode_item').value = NEXT_KODE_ITEM[sel.value] || '';
    }

    function openModalItem() {
        document.getElementById('modalItemTitle').innerText = 'Tambah Produk';
        document.getElementById('item_id_input').value = '';
        onJenisItemChange();
        document.getElementById('input_nama_item').value = '';
        document.getElementById('input_satuan').value = 'KG';
        document.getElementById('modalItem').style.display = 'flex';
    }

    function editItem(it) {
        document.getElementById('modalItemTitle').innerText = 'Edit Produk: ' + it.nama_item;
        document.getElementById('item_id_input').value = it.id;
        document.getElementById('input_kode_item').value = it.kode_item;
        document.getElementById('input_nama_item').value = it.nama_item;
        document.getElementById('input_jenis_id').value = it.jenis_id;
        document.getElementById('input_satuan').value = it.satuan_accurate || 'KG';
        document.getElementById('modalItem').style.display = 'flex';
    }

    function closeModalItem() {
        document.getElementById('modalItem').style.display = 'none';
    }

    // Kategori Modal Handling
    function openModalKategori() {
        document.getElementById('kategori_id_input').value = '';
        document.getElementById('input_kode_jenis').value = '<?= \App\Models\Item::generateNextKodeJenis() ?>';
        document.getElementById('input_nama_jenis').value = '';
        document.getElementById('formKategoriTitle').innerText = 'Tambah Kategori Baru:';
        document.getElementById('modalKategori').style.display = 'flex';
    }

    function editKategori(cat) {
        document.getElementById('kategori_id_input').value = cat.id;
        document.getElementById('input_kode_jenis').value = cat.kode_jenis;
        document.getElementById('input_nama_jenis').value = cat.nama_jenis;
        document.getElementById('formKategoriTitle').innerText = 'Edit Kategori: ' + cat.nama_jenis;
    }

    function editKategoriById(id, nama, kode) {
        openModalKategori();
        document.getElementById('kategori_id_input').value = id;
        document.getElementById('input_kode_jenis').value = kode;
        document.getElementById('input_nama_jenis').value = nama;
        document.getElementById('formKategoriTitle').innerText = 'Edit Kategori: ' + nama;
    }

    function closeModalKategori() {
        document.getElementById('modalKategori').style.display = 'none';
    }

    // =========================================================================
    // ACCURATE ONLINE INTEGRATION JAVASCRIPT FUNCTIONS
    // =========================================================================
    function openGuideModal() {
        const modal = document.getElementById('guideModal');
        if (modal) modal.style.display = 'flex';
    }

    function togglePasswordVisibility(fieldId) {
        const input = document.getElementById(fieldId);
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
        } else {
            input.type = 'password';
        }
    }

    function copyToClipboard(fieldId) {
        const input = document.getElementById(fieldId);
        if (!input) return;
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value).then(() => {
            window.showToast('Teks berhasil disalin ke clipboard!', 'success');
        }).catch(() => {
            document.execCommand('copy');
            window.showToast('Teks berhasil disalin!', 'success');
        });
    }

    // SweetAlert Konfirmasi Toggle & Hapus Pemasok
    async function confirmTogglePemasok(id, nama, isActive) {
        const aksi = isActive ? 'menonaktifkan' : 'mengaktifkan';
        const res = await window.showAppConfirm({
            title: `Konfirmasi ${isActive ? 'Nonaktifkan' : 'Aktifkan'} Pemasok`,
            html: `Apakah Anda yakin ingin <strong>${aksi}</strong> pemasok <strong>"${nama}"</strong>?`,
            icon: isActive ? 'warning' : 'question',
            confirmButtonText: `Ya, ${isActive ? 'Nonaktifkan' : 'Aktifkan'}`,
            cancelButtonText: 'Batal'
        });
        if (res.isConfirmed) {
            window.location.href = `index.php?action=toggle_pemasok_status&id=${id}`;
        }
    }

    async function confirmDeletePemasok(id, nama) {
        const res = await window.showAppConfirm({
            title: 'Hapus Pemasok Permanen?',
            html: `
                <div style="text-align: left; font-size: 13.5px;">
                    <p>Apakah Anda yakin ingin menghapus pemasok <strong>"${nama}"</strong> secara permanen?</p>
                    <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--bad-solid); padding: 10px; border-radius: 6px; margin-top: 10px; font-size: 12.5px;">
                        ⚠️ <em>Pemasok tidak dapat dihapus jika sudah memiliki riwayat penerimaan produk di gudang.</em>
                    </div>
                </div>
            `,
            icon: 'warning',
            confirmButtonText: 'Ya, Hapus Permanen',
            cancelButtonText: 'Batal'
        });
        if (res.isConfirmed) {
            window.location.href = `index.php?action=delete_pemasok&id=${id}`;
        }
    }

    // SweetAlert Konfirmasi Toggle & Hapus Vendor Non-Pemasok
    async function confirmToggleVendorNonPemasok(id, nama, isActive) {
        const aksi = isActive ? 'menonaktifkan' : 'mengaktifkan';
        const res = await window.showAppConfirm({
            title: `Konfirmasi ${isActive ? 'Nonaktifkan' : 'Aktifkan'} Vendor`,
            html: `Apakah Anda yakin ingin <strong>${aksi}</strong> vendor <strong>"${nama}"</strong>?`,
            icon: isActive ? 'warning' : 'question',
            confirmButtonText: `Ya, ${isActive ? 'Nonaktifkan' : 'Aktifkan'}`,
            cancelButtonText: 'Batal'
        });
        if (res.isConfirmed) {
            window.location.href = `index.php?action=toggle_vendor_non_pemasok_status&id=${id}`;
        }
    }

    async function confirmDeleteVendorNonPemasok(id, nama) {
        const res = await window.showAppConfirm({
            title: 'Hapus Vendor Permanen?',
            html: `
                <div style="text-align: left; font-size: 13.5px;">
                    <p>Apakah Anda yakin ingin menghapus vendor <strong>"${nama}"</strong> secara permanen?</p>
                    <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--bad-solid); padding: 10px; border-radius: 6px; margin-top: 10px; font-size: 12.5px;">
                        ⚠️ <em>Vendor tidak dapat dihapus jika sudah memiliki riwayat faktur pembelian operasional.</em>
                    </div>
                </div>
            `,
            icon: 'warning',
            confirmButtonText: 'Ya, Hapus Permanen',
            cancelButtonText: 'Batal'
        });
        if (res.isConfirmed) {
            window.location.href = `index.php?action=delete_vendor_non_pemasok&id=${id}`;
        }
    }

    // SweetAlert Konfirmasi Toggle & Hapus Tipe Biaya
    async function confirmToggleTipeBiaya(id, nama, isActive) {
        const aksi = isActive ? 'menonaktifkan' : 'mengaktifkan';
        const res = await window.showAppConfirm({
            title: `Konfirmasi ${isActive ? 'Nonaktifkan' : 'Aktifkan'} Tipe Biaya`,
            html: `Apakah Anda yakin ingin <strong>${aksi}</strong> tipe biaya <strong>"${nama}"</strong>?`,
            icon: isActive ? 'warning' : 'question',
            confirmButtonText: `Ya, ${isActive ? 'Nonaktifkan' : 'Aktifkan'}`,
            cancelButtonText: 'Batal'
        });
        if (res.isConfirmed) {
            window.location.href = `index.php?action=toggle_tipe_biaya_status&id=${id}`;
        }
    }

    async function confirmDeleteTipeBiaya(id, nama) {
        const res = await window.showAppConfirm({
            title: 'Hapus Tipe Biaya Permanen?',
            html: `
                <div style="text-align: left; font-size: 13.5px;">
                    <p>Apakah Anda yakin ingin menghapus tipe biaya <strong>"${nama}"</strong> secara permanen?</p>
                    <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--bad-solid); padding: 10px; border-radius: 6px; margin-top: 10px; font-size: 12.5px;">
                        ⚠️ <em>Tipe biaya tidak dapat dihapus jika sudah digunakan pada rincian faktur pembelian.</em>
                    </div>
                </div>
            `,
            icon: 'warning',
            confirmButtonText: 'Ya, Hapus Permanen',
            cancelButtonText: 'Batal'
        });
        if (res.isConfirmed) {
            window.location.href = `index.php?action=delete_tipe_biaya&id=${id}`;
        }
    }

    async function testAndFetchDatabases() {
        const tokenInput = document.getElementById('inputApiToken');
        if (!tokenInput) return;
        const token = tokenInput.value.trim();
        if (!token) {
            alert('Harap masukkan API Token terlebih dahulu sebelum melakukan tes koneksi!');
            tokenInput.focus();
            return;
        }

        const modal = document.getElementById('databaseSelectModal');
        const loading = document.getElementById('dbListLoading');
        const container = document.getElementById('dbListContainer');

        if (modal) modal.style.display = 'flex';
        if (loading) loading.style.display = 'block';
        if (container) {
            container.style.display = 'none';
            container.innerHTML = '';
        }

        try {
            const formData = new FormData();
            formData.append('api_token', token);

            const resp = await fetch('index.php?action=test_accurate_api', {
                method: 'POST',
                body: formData
            });
            const data = await resp.json();

            if (loading) loading.style.display = 'none';

            if (data.success && data.databases && data.databases.length > 0) {
                if (container) {
                    container.style.display = 'block';
                    let html = '<div style="display: flex; flex-direction: column; gap: 8px;">';
                    data.databases.forEach(db => {
                        html += `
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-surface);">
                                <div>
                                    <div style="font-weight: 800; font-size: 14px; color: var(--text-main);">${escapeHtml(db.alias || db.name || 'Database ' + db.id)}</div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                        ID Database: <code>${db.id}</code> ${db.expiredDate ? '• Exp: ' + db.expiredDate : ''}
                                    </div>
                                </div>
                                <button type="button" class="btn btn-primary btn-sm" onclick="selectDatabase('${db.id}', '${escapeHtml(db.alias || db.name || '')}')">
                                    <span>Pilih Database Ini</span>
                                </button>
                            </div>
                        `;
                    });
                    html += '</div>';
                    container.innerHTML = html;
                }
            } else {
                if (container) {
                    container.style.display = 'block';
                    container.innerHTML = `
                        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--bad-solid); color: var(--bad-fg); padding: 16px; border-radius: 8px;">
                            <div style="font-weight: 800; margin-bottom: 4px;">Koneksi Gagal</div>
                            <div style="font-size: 12px;">${escapeHtml(data.message || 'Tidak ada database ditemukan atau token tidak valid.')}</div>
                        </div>
                    `;
                }
            }
        } catch (e) {
            if (loading) loading.style.display = 'none';
            if (container) {
                container.style.display = 'block';
                container.innerHTML = `<div style="color: var(--bad-fg); padding: 16px;">Terjadi kesalahan: ${escapeHtml(e.message)}</div>`;
            }
        }
    }

    async function selectDatabase(dbId, dbAlias) {
        document.getElementById('inputDbId').value = dbId;
        document.getElementById('inputDbAlias').value = dbAlias ? `${dbAlias} (ID: ${dbId})` : `Database ID: ${dbId}`;
        const modal = document.getElementById('databaseSelectModal');
        if (modal) modal.style.display = 'none';

        // Langsung simpan dan buka sesi database
        const token = document.getElementById('inputApiToken').value.trim();
        const btn = document.getElementById('btnSaveConfig');
        if (btn) {
            btn.disabled = true;
            btn.innerText = 'Menyimpan & Membuka Sesi...';
        }

        try {
            const formData = new FormData();
            formData.append('api_token', token);
            formData.append('db_id', dbId);
            formData.append('db_alias', dbAlias);

            const resp = await fetch('index.php?action=save_accurate_config', {
                method: 'POST',
                body: formData
            });
            const resData = await resp.json();

            if (resData.success) {
                alert('✓ ' + resData.message + '\nDatabase: ' + (dbAlias || dbId));
                location.reload();
            } else {
                alert('Peringatan: ' + resData.message);
                if (btn) {
                    btn.disabled = false;
                    btn.innerText = 'Simpan Pengaturan API';
                }
            }
        } catch (e) {
            alert('Kesalahan saat menyimpan: ' + e.message);
            if (btn) {
                btn.disabled = false;
                btn.innerText = 'Simpan Pengaturan API';
            }
        }
    }

    async function saveAccurateConfig(e) {
        e.preventDefault();
        const token = document.getElementById('inputApiToken').value.trim();
        const dbId = document.getElementById('inputDbId').value.trim();
        const dbAlias = document.getElementById('inputDbAlias').value.trim();

        const btn = document.getElementById('btnSaveConfig');
        if (btn) {
            btn.disabled = true;
            btn.innerText = 'Menyimpan...';
        }

        try {
            const formData = new FormData();
            formData.append('api_token', token);
            formData.append('db_id', dbId);
            formData.append('db_alias', dbAlias);

            const resp = await fetch('index.php?action=save_accurate_config', {
                method: 'POST',
                body: formData
            });
            const data = await resp.json();

            if (data.success) {
                alert('✓ ' + data.message);
                location.reload();
            } else {
                alert('Gagal: ' + data.message);
                if (btn) {
                    btn.disabled = false;
                    btn.innerText = 'Simpan Pengaturan API';
                }
            }
        } catch (err) {
            alert('Terjadi kesalahan: ' + err.message);
            if (btn) {
                btn.disabled = false;
                btn.innerText = 'Simpan Pengaturan API';
            }
        }
    }

    function showPayload(jsonStr) {
        try {
            const parsed = JSON.parse(jsonStr);
            document.getElementById('jsonViewer').innerText = JSON.stringify(parsed, null, 2);
        } catch (e) {
            document.getElementById('jsonViewer').innerText = jsonStr;
        }
        const modal = document.getElementById('jsonModal');
        if (modal) modal.style.display = 'flex';
    }

    async function syncQueue(queueId) {
        try {
            const resp = await fetch('index.php?action=retry_sync&queue_id=' + queueId);
            const data = await resp.json();
            if (data.success) {
                alert('✓ ' + data.message + '\nNomor Dokumen Accurate: ' + (data.docNo || '-'));
                location.reload();
            } else {
                alert('Gagal Sinkronisasi: ' + data.message);
            }
        } catch (e) {
            alert('Kesalahan saat mengirim data: ' + e.message);
        }
    }

    const btnSyncAll = document.getElementById('btnSyncAllPending');
    if (btnSyncAll) {
        btnSyncAll.addEventListener('click', async () => {
            alert('Memproses antrean pending ke Accurate Online...');
            location.reload();
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
