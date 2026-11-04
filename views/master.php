<?php
/**
 * View Master Data Hub (Pemasok, Master Harga Pemasok, Master Harga Mitra, Analisa Profit/Loss, Mitra, Produk, Settingan Accurate)
 * Desain Simple & Jelas untuk Kemudahan Pengguna
 */

use App\Core\Helper;
use App\Config\Config;
use App\Services\AccurateApiService;
use App\Models\Pemasok;
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
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                <span class="badge badge-purple">Integrasi Sistem</span>
                <span class="badge badge-info">Accurate Online REST API</span>
                <?php if (!empty($cfg['is_connected']) && !empty($cfg['session_id'])): ?>
                    <span class="badge badge-success">✓ Live Terhubung: <?= htmlspecialchars($cfg['db_alias'] ?: ('ID ' . $cfg['db_id'])) ?></span>
                <?php else: ?>
                    <span class="badge badge-warning">⏳ Belum Terhubung</span>
                <?php endif; ?>
            </div>
            <h1 class="page-title">Settingan API Accurate Online</h1>
            <p class="page-subtitle">Konfigurasi API token resmi, otentikasi database, dan pemantauan antrean transaksi otomatis</p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <a href="Manual_Book_Sistem_Barcode_Inventory_Multi_Mitra.docx" class="btn btn-outline" download style="display: flex; align-items: center; gap: 6px;">
                <i data-lucide="file-text" style="width: 16px; height: 16px; color: #2563eb;"></i>
                <span>Manual Book (.docx)</span>
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
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                <span class="badge badge-purple">Master Data</span>
                <span class="badge badge-warning"><?= count($pemasokList) ?> Pemasok</span>
                <span class="badge badge-info">7 Mitra Bisnis</span>
                <span class="badge badge-success">Katalog Produk</span>
            </div>
            <h1 class="page-title">Master Data Gudang</h1>
            <p class="page-subtitle">Kelola data pemasok barang, master harga pemasok, master harga mitra, dan analisa profit/loss</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if ($activeTab === 'pemasok'): ?>
                <button type="button" class="btn btn-outline" onclick="exportTableToExcel('tableMasterPemasok', 'Master_Pemasok')">
                    <i data-lucide="file-spreadsheet" style="width: 16px; height: 16px; color: #10b981;"></i>
                    <span>Ekspor Excel (.xlsx)</span>
                </button>
                <button type="button" class="btn btn-primary" onclick="openModalPemasok()">
                    <i data-lucide="plus-circle" style="width: 16px; height: 16px;"></i>
                    <span>Tambah Pemasok Baru</span>
                </button>
            <?php elseif ($activeTab === 'harga_pemasok'): ?>
                <button type="button" class="btn btn-outline" onclick="exportTableToExcel('tableMasterHargaPemasok', 'Master_Harga_Pemasok')">
                    <i data-lucide="file-spreadsheet" style="width: 16px; height: 16px; color: #10b981;"></i>
                    <span>Ekspor Excel (.xlsx)</span>
                </button>
                <button type="button" class="btn btn-primary" style="background: #f59e0b; border-color: #f59e0b; color: #fff;" onclick="document.getElementById('formBulkHargaPemasok').submit()">
                    <i data-lucide="save" style="width: 16px; height: 16px;"></i>
                    <span>Simpan Harga Pemasok</span>
                </button>
            <?php elseif ($activeTab === 'harga_mitra'): ?>
                <button type="button" class="btn btn-outline" onclick="exportTableToExcel('tableMasterHargaMitra', 'Master_Harga_Mitra')">
                    <i data-lucide="file-spreadsheet" style="width: 16px; height: 16px; color: #10b981;"></i>
                    <span>Ekspor Excel (.xlsx)</span>
                </button>
                <button type="button" class="btn btn-success" onclick="document.getElementById('formBulkHargaMitra').submit()">
                    <i data-lucide="save" style="width: 16px; height: 16px;"></i>
                    <span>Simpan Harga Mitra</span>
                </button>
            <?php elseif ($activeTab === 'profit_loss'): ?>
                <button type="button" class="btn btn-outline" onclick="exportTableToExcel('tableProfitLoss', 'Analisa_Profit_Loss')">
                    <i data-lucide="file-spreadsheet" style="width: 16px; height: 16px; color: #10b981;"></i>
                    <span>Ekspor Excel (.xlsx)</span>
                </button>
            <?php elseif ($activeTab === 'mitra'): ?>
                <button type="button" class="btn btn-outline" onclick="exportTableToExcel('tableMasterMitra', 'Master_Mitra_Bisnis')">
                    <i data-lucide="file-spreadsheet" style="width: 16px; height: 16px; color: #10b981;"></i>
                    <span>Ekspor Excel (.xlsx)</span>
                </button>
                <button type="button" class="btn btn-primary" onclick="openModalMitra()">
                    <i data-lucide="plus-circle" style="width: 16px; height: 16px;"></i>
                    <span>Tambah Mitra Baru</span>
                </button>
            <?php elseif ($activeTab === 'produk'): ?>
                <button type="button" class="btn btn-outline" onclick="exportTableToExcel('tableMasterProduk', 'Master_Produk')">
                    <i data-lucide="file-spreadsheet" style="width: 16px; height: 16px; color: #10b981;"></i>
                    <span>Ekspor Excel (.xlsx)</span>
                </button>
                <button type="button" class="btn btn-primary" onclick="openModalItem()">
                    <i data-lucide="plus-circle" style="width: 16px; height: 16px;"></i>
                    <span>Tambah Produk</span>
                </button>
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

<!-- Tab Navigasi Master Data & Pengaturan -->
<div style="display: flex; gap: 8px; margin-bottom: 22px; border-bottom: 2px solid var(--border); padding-bottom: 8px; flex-wrap: wrap;">
    <a href="index.php?page=master&tab=pemasok" class="btn <?= $activeTab === 'pemasok' ? 'btn-primary' : 'btn-outline' ?>" style="border-radius: 10px; font-weight: 700;">
        <i data-lucide="truck" style="width: 16px; height: 16px; color: #f59e0b;"></i>
        <span>🚚 Master Pemasok</span>
    </a>
    <a href="index.php?page=master&tab=harga_pemasok" class="btn <?= $activeTab === 'harga_pemasok' ? 'btn-primary' : 'btn-outline' ?>" style="border-radius: 10px; font-weight: 700;">
        <i data-lucide="tag" style="width: 16px; height: 16px; color: #f59e0b;"></i>
        <span>🏷️ Master Harga Pemasok (per Produk)</span>
    </a>
    <a href="index.php?page=master&tab=harga_mitra" class="btn <?= $activeTab === 'harga_mitra' ? 'btn-primary' : 'btn-outline' ?>" style="border-radius: 10px; font-weight: 700;">
        <i data-lucide="badge-dollar-sign" style="width: 16px; height: 16px; color: #10b981;"></i>
        <span>💰 Master Harga Mitra</span>
    </a>
    <a href="index.php?page=master&tab=mitra" class="btn <?= $activeTab === 'mitra' ? 'btn-primary' : 'btn-outline' ?>" style="border-radius: 10px; font-weight: 700;">
        <i data-lucide="building-2" style="width: 16px; height: 16px; color: #3b82f6;"></i>
        <span>🏢 Master Mitra Bisnis</span>
    </a>
    <a href="index.php?page=master&tab=produk" class="btn <?= $activeTab === 'produk' ? 'btn-primary' : 'btn-outline' ?>" style="border-radius: 10px; font-weight: 700;">
        <i data-lucide="boxes" style="width: 16px; height: 16px; color: #8b5cf6;"></i>
        <span>📦 Master Produk</span>
    </a>
    <a href="index.php?page=master&tab=accurate" class="btn <?= $activeTab === 'accurate' ? 'btn-primary' : 'btn-outline' ?>" style="border-radius: 10px; font-weight: 700;">
        <i data-lucide="key" style="width: 16px; height: 16px; color: #0284c7;"></i>
        <span>⚙️ Settingan API Accurate</span>
    </a>
    <a href="index.php?page=profit_loss" class="btn btn-outline" style="border-radius: 10px; font-weight: 800; color: #059669; border-color: #86efac; background: #f0fdf4; margin-left: auto;">
        <i data-lucide="trending-up" style="width: 16px; height: 16px; color: #059669;"></i>
        <span>📈 Buka Menu: Profit and Loss ➔</span>
    </a>
</div>

<!-- ========================================================================= -->
<!-- TAB PEMASOK: MASTER PEMASOK (SUPPLIER BARANG MASUK)                       -->
<!-- ========================================================================= -->
<?php if ($activeTab === 'pemasok'): ?>
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">
                <i data-lucide="truck" style="width: 20px; height: 20px; color: #f59e0b;"></i>
                <span>Daftar Pemasok (Supplier Barang Masuk)</span>
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
                            <span class="badge" style="background: rgba(245, 158, 11, 0.12); color: #d97706; font-family: 'JetBrains Mono', monospace; font-weight: 800; font-size: 12px;">
                                <?= htmlspecialchars($p['kode_pemasok']) ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color: var(--text-main); font-size: 14px;"><?= htmlspecialchars($p['nama_pemasok']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($p['pic_nama'] ?: '-') ?></td>
                        <td>
                            <?php if (!empty($p['telepon'])): ?>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $p['telepon']) ?>" target="_blank" style="color: #10b981; font-weight: 600; text-decoration: none;">
                                    📞 <?= htmlspecialchars($p['telepon']) ?>
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
                                   ? 'background: #ecfdf5; color: #047857; border: 1.5px solid #a7f3d0;' 
                                   : 'background: #f8fafc; color: #64748b; border: 1.5px solid #cbd5e1;' 
                               ?> border-radius: 20px; font-weight: 800; font-size: 11.5px; padding: 4px 12px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; cursor: pointer; transition: all 0.2s;"
                               title="<?= $p['is_active'] ? 'Klik untuk MENONAKTIFKAN pemasok ini' : 'Klik untuk MENGAKTIFKAN pemasok ini' ?>">
                                <?php if ($p['is_active']): ?>
                                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981; box-shadow: 0 0 6px rgba(16, 185, 129, 0.6);"></span>
                                    <span>● Aktif</span>
                                    <i data-lucide="power" style="width: 12px; height: 12px; opacity: 0.7;"></i>
                                <?php else: ?>
                                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #94a3b8;"></span>
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
                                   style="color: #ef4444; border-color: rgba(239, 68, 68, 0.3);" 
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
<!-- TAB: MASTER HARGA PEMASOK (HARGA BELI PER JENIS BARANG)                   -->
<!-- ========================================================================= -->
<?php elseif ($activeTab === 'harga_pemasok'): ?>
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">
                <i data-lucide="tag" style="width: 20px; height: 20px; color: #f59e0b;"></i>
                <span>Master Harga Pemasok (Harga Beli per Jenis Barang)</span>
            </h2>
            <div class="card-description">
                Tarif harga beli persediaan per Kg untuk setiap jenis barang dari masing-masing pemasok (supplier). Harga ini menjadi dasar penghitungan HPP dan profit saat barang dikeluarkan ke mitra.
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
            <strong>💡 Kontrak Harga Beli per Produk SKU:</strong> Setiap pemasok memiliki kontrak harga beli untuk setiap produk SKU (Udang, Cumi, Ikan, Teri Jengki, Rebon, HRKK, dll). Tarif ini digunakan untuk menghitung modal pembelian dan dianalisa pada menu <strong>Profit and Loss</strong>.
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
                                               style="width: 115px; text-align: right; font-weight: 800; color: #d97706; padding: 5px 8px; font-size: 13px;"
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
                Perubahan tarif beli pemasok ini otomatis terhubung ke perhitungan modal beli dan menu Profit and Loss.
            </div>
            <button type="submit" class="btn btn-primary btn-lg" style="background: #f59e0b; border-color: #f59e0b; color: #fff;">
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
                <i data-lucide="badge-dollar-sign" style="width: 20px; height: 20px; color: #10b981;"></i>
                <span>Master Harga Mitra (Harga Jual ke 7 Mitra)</span>
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
            <strong>💡 Integrasi Harga Jual Mitra:</strong> Setiap dari 7 mitra bisnis memiliki kontrak tarif harga jual berbeda-beda. Saat melakukan <em>Pengeluaran Barang</em> dari stok pemasok ke mitra tujuan, harga di bawah ini otomatis diterapkan untuk menghasilkan nilai total invoice dan laba bersih.
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
                                           style="width: 105px; text-align: right; font-weight: 800; color: #10b981; padding: 5px 8px; font-size: 13px;"
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
                <i data-lucide="building-2" style="width: 20px; height: 20px; color: #3b82f6;"></i>
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
                        <code style="font-weight: 700; color: #0284c7;"><?= htmlspecialchars($m['kode_gudang_accurate']) ?></code>
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
                        <button type="button" class="btn btn-outline btn-sm" onclick='editMitra(<?= json_encode($m) ?>)'>
                            <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                            <span>Edit</span>
                        </button>
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
                <i data-lucide="boxes" style="width: 20px; height: 20px; color: #8b5cf6;"></i>
                <span>Master Produk</span>
            </h2>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-outline" onclick="openModalKategori()">
                <i data-lucide="tag" style="width: 16px; height: 16px; color: #8b5cf6;"></i>
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
                    <th>Kategori / Jenis Master</th>
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
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span class="badge badge-purple" style="font-size: 12px; font-weight: 700;">
                                <?= htmlspecialchars($it['nama_jenis']) ?>
                            </span>
                            <button type="button" class="btn btn-outline btn-sm" style="padding: 2px 6px; font-size: 10px;" onclick="editKategoriById(<?= $it['jenis_id'] ?>, '<?= htmlspecialchars($it['nama_jenis'], ENT_QUOTES) ?>', '<?= htmlspecialchars($it['kode_jenis'], ENT_QUOTES) ?>')" title="Edit Kategori Ini">
                                ✏️
                            </button>
                        </div>
                    </td>
                    <td style="text-align: center;">
                        <div style="display: flex; justify-content: center; gap: 6px;">
                            <button type="button" class="btn btn-outline btn-sm" onclick='editItem(<?= json_encode($it) ?>)'>
                                <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                                <span>Edit</span>
                            </button>
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
                    ✓ Live API Terhubung: <strong><?= htmlspecialchars($cfg['db_alias'] ?: ('ID ' . $cfg['db_id'])) ?></strong>
                </span>
            <?php elseif (!empty($cfg['api_token'])): ?>
                <span class="badge badge-warning" style="padding: 6px 12px; font-size: 12px;">
                    <i data-lucide="clock" style="width: 14px; height: 14px; margin-right: 4px;"></i>
                    ⏳ Token Tersimpan (Silakan Pilih Database)
                </span>
            <?php else: ?>
                <span class="badge badge-purple" style="padding: 6px 12px; font-size: 12px;">
                    <i data-lucide="shield-alert" style="width: 14px; height: 14px; margin-right: 4px;"></i>
                    ⚙ Mode Simulasi / Menunggu API Token
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
                        App Key (Developer ID):
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
                        <span>API Token (Dihasilkan dari Accurate Store):</span>
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
                            <i data-lucide="radio" style="width: 15px; height: 15px; color: #0ea5e9;"></i>
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
            <button type="button" class="btn btn-outline btn-sm" onclick="exportTableToExcel('tableAccurateSync', 'Log_Antrean_Accurate_Online')">
                <i data-lucide="file-spreadsheet" style="width: 14px; height: 14px; color: #10b981;"></i>
                <span>Ekspor Excel (.xlsx)</span>
            </button>
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
                            <span class="mitra-tag <?= strtolower($q['kode_mitra']) ?>" style="background: <?= htmlspecialchars($q['warna_identitas'] ?? '#4f46e5') ?>; padding: 2px 7px; font-size: 11px;">
                                <?= htmlspecialchars($q['kode_mitra']) ?>
                            </span>
                            <span style="font-size: 12px; margin-left: 5px; font-weight: 600;"><?= htmlspecialchars($q['nama_mitra']) ?></span>
                        </td>
                        <td style="text-align: right; font-weight: 800; color: #10b981; font-size: 14px;"><?= Helper::formatKg($q['total_kg']) ?></td>
                        <td><code style="font-size: 11px;"><?= htmlspecialchars($q['endpoint']) ?></code></td>
                        <td><strong><?= $q['attempt_count'] ?></strong> / <?= $q['max_attempts'] ?></td>
                        <td>
                            <?php if ($q['status'] === 'SUCCESS'): ?>
                                <span class="badge badge-success">✓ Terkirim</span>
                            <?php elseif ($q['status'] === 'PENDING'): ?>
                                <span class="badge badge-warning">⏳ Pending</span>
                            <?php else: ?>
                                <span class="badge badge-destructive">⚠ Gagal</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: flex; gap: 6px; justify-content: center;">
                                <button type="button" class="btn btn-outline btn-sm" onclick="showPayload(<?= htmlspecialchars(json_encode($q['payload_json'])) ?>)">
                                    🔍 JSON
                                </button>
                                <?php if ($q['status'] !== 'SUCCESS'): ?>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="syncQueue(<?= $q['id'] ?>)">
                                        ⚡ Kirim
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
                <label class="form-label">Kode Mitra (M01 - M07) <span style="color:red;">*</span></label>
                <input type="text" name="kode_mitra" id="input_kode_mitra" class="form-control" required placeholder="Contoh: M08">
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
                <label class="form-label">Kode SKU / Item Accurate <span style="color:red;">*</span></label>
                <input type="text" name="kode_item" id="input_kode_item" class="form-control" required placeholder="Contoh: SKU-UDANG-001">
            </div>
            <div class="form-group">
                <label class="form-label">Nama Barang / Produk <span style="color:red;">*</span></label>
                <input type="text" name="nama_item" id="input_nama_item" class="form-control" required placeholder="Contoh: Udang / Cumi / Ikan / Teri / Rebon">
            </div>
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <label class="form-label" style="margin: 0;">Kategori / Jenis Master <span style="color:red;">*</span></label>
                    <button type="button" class="btn btn-outline btn-sm" onclick="openModalKategori()" style="font-size: 11px; padding: 2px 7px;">
                        + Kelola Kategori
                    </button>
                </div>
                <select name="jenis_id" id="input_jenis_id" class="form-control" required>
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
                <i data-lucide="tag" style="width: 18px; height: 18px; color: #8b5cf6;"></i>
                <span>Kelola Kategori / Jenis Master</span>
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
                                    ✏️ Edit
                                </button>
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
                        <input type="text" name="kode_jenis" id="input_kode_jenis" class="form-control" required placeholder="JB05" style="height: 38px; text-transform: uppercase;">
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
                    <input type="text" name="kode_pemasok" id="input_kode_pemasok" class="form-control" required placeholder="PMS-01" style="text-transform: uppercase; font-family: 'JetBrains Mono', monospace; font-weight: 700;">
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
                <div style="background: #10b981; color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0;">5</div>
                <div>
                    <strong>Tempel (Paste) Token ke Sistem Gudang</strong>
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


    // Mitra Modal Handling
    function openModalMitra() {
        document.getElementById('modalMitraTitle').innerText = 'Tambah Mitra Bisnis Baru';
        document.getElementById('mitra_id_input').value = '';
        document.getElementById('input_kode_mitra').value = '';
        document.getElementById('input_kode_mitra').readOnly = false;
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
        document.getElementById('input_kode_mitra').readOnly = true;
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
    function openModalItem() {
        document.getElementById('modalItemTitle').innerText = 'Tambah Produk';
        document.getElementById('item_id_input').value = '';
        document.getElementById('input_kode_item').value = '';
        document.getElementById('input_kode_item').readOnly = false;
        document.getElementById('input_nama_item').value = '';
        document.getElementById('input_satuan').value = 'KG';
        document.getElementById('modalItem').style.display = 'flex';
    }

    function editItem(it) {
        document.getElementById('modalItemTitle').innerText = 'Edit Produk: ' + it.nama_item;
        document.getElementById('item_id_input').value = it.id;
        document.getElementById('input_kode_item').value = it.kode_item;
        document.getElementById('input_kode_item').readOnly = true;
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
        document.getElementById('input_kode_jenis').value = '';
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
                    <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; padding: 10px; border-radius: 6px; margin-top: 10px; font-size: 12.5px;">
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
                        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #dc2626; padding: 16px; border-radius: 8px;">
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
                container.innerHTML = `<div style="color: #ef4444; padding: 16px;">Terjadi kesalahan: ${escapeHtml(e.message)}</div>`;
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
