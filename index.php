<?php
/**
 * Front Controller & Router Aplikasi
 * Sistem Barcode Inventory Multi-Mitra (Catch-Weight)
 * Mendukung Pergerakan Barang (Pengepul -> Gudang -> Office -> Pembeli POS) & BAST
 */

session_start();

// Inisialisasi User Login default jika session belum terisi
if (!isset($_SESSION['user_login']) || strpos($_SESSION['user_login']['nama_lengkap'] ?? '', 'Rizky') !== false) {
    $_SESSION['user_login'] = [
        'id' => 3,
        'username' => 'opr_timbang',
        'nama_lengkap' => 'Arif Operator Penerimaan',
        'role' => 'OPERATOR_INBOUND'
    ];
}

// PSR-4 Autoloader Sederhana untuk namespace App\
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Core\Helper;
use App\Models\Label;
use App\Models\Mitra;
use App\Models\Item;
use App\Models\MasterHarga;
use App\Models\MasterHargaPemasok;
use App\Models\Transaksi;
use App\Services\BarcodeService;
use App\Services\AccurateApiService;

// 1. PENANGANAN REQUEST AJAX / API ACTION
$action = $_GET['action'] ?? null;

if ($action) {
    try {
        switch ($action) {
            // A. Simpan Label Barcode Baru (Stasiun Timbang)
            case 'save_label':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception("Metode request harus POST");
                }
                $createdLabel = Label::create($_POST);
                
                $createdLabel['barcode_svg'] = BarcodeService::generateCode128Svg($createdLabel['id_label'], 65, 1.6);
                $createdLabel['qr_svg'] = BarcodeService::generateQrSvg($createdLabel['id_label'], 120);

                Helper::jsonResponse([
                    'success' => true,
                    'message' => 'Label kemasan berhasil dibuat',
                    'label'   => $createdLabel
                ]);
                break;

            // B. Verifikasi Barcode saat Discan (Stasiun Pengeluaran / Opname)
            case 'verify_label':
                $barcode = trim($_GET['barcode'] ?? '');
                if (empty($barcode)) {
                    throw new Exception("Parameter barcode wajib diisi");
                }
                $label = Label::findByBarcode($barcode);
                if (!$label) {
                    Helper::jsonResponse([
                        'success' => false,
                        'message' => "Barcode '{$barcode}' tidak terdaftar di sistem!"
                    ], 404);
                }

                // Hitung estimasi harga beli pemasok & harga jual mitra jika parameter diberikan
                $pmsId = (int)($_GET['pemasok_id'] ?? ($label['pemasok_id'] ?? 0));
                $mtrId = (int)($_GET['mitra_id'] ?? 0);
                $itemId = (int)($label['item_id'] ?? 0);

                $hargaPemasok = ($pmsId > 0 && $itemId > 0) ? MasterHargaPemasok::getHargaByPemasokAndItem($pmsId, $itemId) : 0.00;
                $hargaMitra   = ($mtrId > 0 && $itemId > 0) ? MasterHarga::getHargaByMitraAndItem($mtrId, $itemId) : 0.00;
                $netto = (float)($label['berat_netto'] ?? 0);

                $subBeli = round($netto * $hargaPemasok, 2);
                $subJual = round($netto * $hargaMitra, 2);
                $profit  = round($subJual - $subBeli, 2);

                $label['harga_pemasok'] = $hargaPemasok;
                $label['harga_mitra']   = $hargaMitra;
                $label['subtotal_beli'] = $subBeli;
                $label['subtotal_jual'] = $subJual;
                $label['profit']        = $profit;

                // Cek peringatan FIFO: apakah ada stok sejenis yang mengendap lebih lama
                $fifoInfo = null;
                if ($pmsId > 0 && $itemId > 0 && !empty($label['created_at'])) {
                    $fifoInfo = Label::checkFifoOlderStock($pmsId, $itemId, $label['created_at'], $label['id_label']);
                }

                Helper::jsonResponse([
                    'success'   => true,
                    'label'     => $label,
                    'fifo_info' => $fifoInfo
                ]);
                break;

            // B1.1. Mengambil Rekomendasi Antrean FIFO (Kemasan Aktif Terlama) untuk Pemasok
            case 'get_pemasok_fifo_stock':
                $pemasokId = (int)($_GET['pemasok_id'] ?? 0);
                $itemId    = !empty($_GET['item_id']) ? (int)$_GET['item_id'] : null;
                if ($pemasokId <= 0) {
                    throw new Exception("Pemasok ID tidak valid");
                }
                $fifoList = Label::getFifoActiveList($pemasokId, $itemId, 20);
                Helper::jsonResponse([
                    'success'   => true,
                    'fifo_list' => $fifoList,
                    'total'     => count($fifoList)
                ]);
                break;

            // B2. Mengambil Riwayat Timeline / Siklus Hidup Barcode (Pemasok -> Gudang -> Pengeluaran ke Mitra)
            case 'get_barcode_timeline':
                $barcode = trim($_GET['barcode'] ?? ($_POST['barcode'] ?? ''));
                if (empty($barcode)) {
                    throw new Exception("Parameter barcode wajib diisi");
                }
                $timeline = Label::getBarcodeTimeline($barcode);
                Helper::jsonResponse([
                    'success' => true,
                    'barcode' => $barcode,
                    'timeline' => $timeline
                ]);
                break;

            // B3. Verifikasi & Simpan Fisik Stok Opname ke Database Langsung
            case 'opname_verify':
                $barcode = trim($_POST['barcode'] ?? ($_GET['barcode'] ?? ''));
                if (empty($barcode)) {
                    throw new Exception("Parameter barcode wajib diisi");
                }
                $userId = $_SESSION['user']['id'] ?? 3;
                $res = Label::opnameVerify($barcode, $userId);
                Helper::jsonResponse($res, $res['success'] ? 200 : 400);
                break;

            // B3. Mengambil Informasi Stok Riil di Gudang untuk Pemasok Tertentu (Saat Pemasok Dipilih di Pengeluaran)
            case 'get_pemasok_warehouse_stock':
                $pemasokId = (int)($_GET['pemasok_id'] ?? 0);
                $mitraId   = (int)($_GET['mitra_id'] ?? 0);
                if ($pemasokId <= 0) {
                    throw new Exception("Pemasok ID tidak valid");
                }
                $stockData = \App\Services\StockService::getStockGudangPerPemasok($pemasokId, $mitraId ?: null);
                Helper::jsonResponse([
                    'success'    => true,
                    'pemasok_id' => $pemasokId,
                    'stock'      => $stockData
                ]);
                break;

            // C. Dapatkan Nomor Surat Jalan Pengeluaran Berikutnya secara Otomatis
            case 'get_next_outbound_sj':
                $pemasokId = (int)($_GET['pemasok_id'] ?? 0);
                $mitraId   = (int)($_GET['mitra_id'] ?? 0);
                $kode = trim($_GET['kode'] ?? ($_GET['kode_pemasok'] ?? ($_GET['kode_mitra'] ?? '')));

                if (empty($kode) && $pemasokId > 0) {
                    $p = \App\Models\Pemasok::findById($pemasokId);
                    if ($p && !empty($p['kode_pemasok'])) $kode = $p['kode_pemasok'];
                }
                if (empty($kode) && $mitraId > 0) {
                    $m = Mitra::findById($mitraId);
                    if ($m && !empty($m['kode_mitra'])) $kode = $m['kode_mitra'];
                }

                $nextSj = Transaksi::generateNextSuratJalanKeluar($kode ?: 'OUT');
                Helper::jsonResponse([
                    'success' => true,
                    'no_surat_jalan' => $nextSj,
                    'kode' => $kode
                ]);
                break;

            // C2. Ambil Detail Rincian Surat Jalan Pengeluaran (Modal Popup)
            case 'get_outbound_detail':
                $id = (int)($_GET['id'] ?? 0);
                if ($id <= 0) throw new Exception("ID transaksi tidak valid");
                $trx = Transaksi::getByIdWithDetails($id);
                if (!$trx) throw new Exception("Data transaksi pengeluaran tidak ditemukan");
                Helper::jsonResponse([
                    'success' => true,
                    'transaksi' => $trx
                ]);
                break;

            // C3. Proses Pengeluaran Barang (Outbound: Dari Stok Pemasok ke Mitra)
            case 'process_outbound':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception("Metode request harus POST");
                }
                $rawInput = file_get_contents('php://input');
                $payload = json_decode($rawInput, true);

                if (!$payload) throw new Exception("Payload JSON tidak valid");

                $pemasokId    = (int)($payload['pemasok_id'] ?? 0);
                $mitraId      = (int)($payload['mitra_id'] ?? 0);
                $noReferensi  = trim($payload['no_referensi'] ?? '');
                $namaPenerima = trim($payload['nama_penerima'] ?? ($payload['tujuan'] ?? ''));
                $keterangan   = trim($payload['keterangan'] ?? '');
                $barcodes     = (array)($payload['barcodes'] ?? []);
                $userId       = (int)($_SESSION['user_login']['id'] ?? 4);

                if ($pemasokId <= 0) {
                    throw new Exception("Pemasok asal stok wajib dipilih!");
                }
                if ($mitraId <= 0) {
                    throw new Exception("Mitra penerima barang wajib dipilih!");
                }

                $result = Transaksi::processPengeluaran($pemasokId, $mitraId, $barcodes, $noReferensi, $keterangan, $userId, $namaPenerima);
                Helper::jsonResponse($result);
                break;

            // D. Proses Mutasi / Pergerakan Barang & Berita Acara Serah Terima (BAST)
            case 'process_mutasi':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception("Metode request harus POST");
                }
                $rawInput = file_get_contents('php://input');
                $payload = json_decode($rawInput, true);

                if (!$payload) throw new Exception("Payload JSON tidak valid");

                // Inject user login ID
                $payload['user_id'] = (int)($_SESSION['user_login']['id'] ?? 3);

                $result = Transaksi::processMutasi($payload);
                Helper::jsonResponse($result);
                break;

            // E. Ganti User Login Simulasi
            case 'switch_user':
                $targetUserId = (int)($_GET['user_id'] ?? 3);
                $usersMap = [
                    1 => ['id' => 1, 'username' => 'admin', 'nama_lengkap' => 'Administrator Sistem', 'role' => 'ADMIN'],
                    2 => ['id' => 2, 'username' => 'spv_gudang', 'nama_lengkap' => 'Bambang Supriyanto (SPV)', 'role' => 'SUPERVISOR'],
                    3 => ['id' => 3, 'username' => 'opr_timbang', 'nama_lengkap' => 'Arif Operator Penerimaan', 'role' => 'OPERATOR_INBOUND'],
                    4 => ['id' => 4, 'username' => 'opr_kirim', 'nama_lengkap' => 'Dani Operator Outbound & Kasir POS', 'role' => 'OPERATOR_OUTBOUND']
                ];
                if (isset($usersMap[$targetUserId])) {
                    $_SESSION['user_login'] = $usersMap[$targetUserId];
                }
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php?page=pengeluaran'));
                exit;

            // F. Retry Antrean Sinkronisasi Accurate Online
            case 'retry_sync':
                $queueId = (int)($_GET['queue_id'] ?? 0);
                if ($queueId <= 0) throw new Exception("ID Antrean tidak valid");

                $syncResult = AccurateApiService::processQueueItem($queueId);
                Helper::jsonResponse($syncResult);
                break;

            // F2. Tes Koneksi API Token Accurate Online & Ambil Daftar Database
            case 'test_accurate_api':
                $token = trim($_POST['api_token'] ?? $_GET['api_token'] ?? '');
                $result = AccurateApiService::fetchDatabases($token ?: null);
                Helper::jsonResponse($result);
                break;

            // F3. Simpan Konfigurasi Token & Database Accurate Online
            case 'save_accurate_config':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                $apiToken = trim($_POST['api_token'] ?? '');
                $dbId     = trim($_POST['db_id'] ?? '');
                $dbAlias  = trim($_POST['db_alias'] ?? '');

                $updateData = ['api_token' => $apiToken];
                if (!empty($dbId)) {
                    $updateData['db_id'] = $dbId;
                    $updateData['db_alias'] = $dbAlias;
                }
                Config::saveAccurateConfig($updateData);

                if (!empty($apiToken) && !empty($dbId)) {
                    $openRes = AccurateApiService::openDatabase($dbId, $apiToken, $dbAlias);
                    Helper::jsonResponse($openRes);
                } else {
                    Helper::jsonResponse(['success' => true, 'message' => 'Konfigurasi Accurate berhasil disimpan.']);
                }
                break;

            // F4. Buka Database Tertentu di Accurate Online
            case 'open_accurate_db':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                $dbId    = trim($_POST['db_id'] ?? '');
                $dbAlias = trim($_POST['db_alias'] ?? '');
                $token   = trim($_POST['api_token'] ?? '');
                if (empty($dbId)) throw new Exception("ID Database tidak boleh kosong");

                $openRes = AccurateApiService::openDatabase($dbId, $token ?: null, $dbAlias);
                Helper::jsonResponse($openRes);
                break;

            // G. Generate Nomor Surat Jalan Otomatis
            case 'gen_surat_jalan':
                $kode = trim($_GET['kode'] ?? 'PMS-01');
                $noSj = Label::generateNextSuratJalan($kode);
                Helper::jsonResponse([
                    'success' => true,
                    'no_surat_jalan' => $noSj
                ]);
                break;

            // G1. Simpan / Update Master Pemasok
            case 'save_pemasok':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    \App\Models\Pemasok::update($id, $_POST);
                    $msg = "Data Pemasok " . htmlspecialchars($_POST['nama_pemasok']) . " berhasil diperbarui";
                } else {
                    \App\Models\Pemasok::create($_POST);
                    $msg = "Pemasok baru " . htmlspecialchars($_POST['nama_pemasok']) . " berhasil ditambahkan";
                }
                header('Location: index.php?page=master&tab=pemasok&msg=' . urlencode($msg) . '&msg_type=success');
                exit;

            // G2. Toggle Status Aktif / Nonaktif Master Pemasok
            case 'toggle_pemasok_status':
                $id = (int)($_REQUEST['id'] ?? 0);
                if ($id <= 0) throw new Exception("ID Pemasok tidak valid");
                $pemasok = \App\Models\Pemasok::findById($id);
                if (!$pemasok) throw new Exception("Data pemasok tidak ditemukan");
                $newStatus = \App\Models\Pemasok::toggleStatus($id);
                $statusText = $newStatus == 1 ? 'diaktifkan (Aktif)' : 'dinonaktifkan (Nonaktif)';
                $msg = "Status pemasok [" . htmlspecialchars($pemasok['kode_pemasok']) . "] " . htmlspecialchars($pemasok['nama_pemasok']) . " berhasil " . $statusText;
                header('Location: index.php?page=master&tab=pemasok&msg=' . urlencode($msg) . '&msg_type=success');
                exit;

            // G3. Hapus Master Pemasok (Validasi Penerimaan Barang)
            case 'delete_pemasok':
                $id = (int)($_GET['id'] ?? 0);
                if ($id <= 0) throw new Exception("ID Pemasok tidak valid");
                $pemasok = \App\Models\Pemasok::findById($id);
                if (!$pemasok) throw new Exception("Data pemasok tidak ditemukan");
                try {
                    \App\Models\Pemasok::delete($id);
                    $msg = "Pemasok [" . htmlspecialchars($pemasok['kode_pemasok']) . "] " . htmlspecialchars($pemasok['nama_pemasok']) . " berhasil dihapus secara permanen.";
                    $msgType = 'success';
                } catch (\Exception $e) {
                    $msg = $e->getMessage();
                    $msgType = 'danger';
                }
                header('Location: index.php?page=master&tab=pemasok&msg=' . urlencode($msg) . '&msg_type=' . $msgType);
                exit;

            // H. Simpan / Update Master Mitra
            case 'save_mitra':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    Mitra::update($id, $_POST);
                    $msg = "Data Mitra " . htmlspecialchars($_POST['kode_mitra']) . " berhasil diperbarui";
                } else {
                    Mitra::create($_POST);
                    $msg = "Mitra baru " . htmlspecialchars($_POST['kode_mitra']) . " berhasil ditambahkan";
                }
                header('Location: index.php?page=master&tab=mitra&msg=' . urlencode($msg));
                exit;

            // I. Simpan / Update Master Produk (Item SKU)
            case 'save_item':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    Item::update($id, $_POST);
                    $msg = "Produk SKU " . htmlspecialchars($_POST['kode_item']) . " berhasil diperbarui";
                } else {
                    Item::create($_POST);
                    $msg = "Produk SKU baru " . htmlspecialchars($_POST['kode_item']) . " berhasil ditambahkan";
                }
                header('Location: index.php?page=master&tab=produk&msg=' . urlencode($msg));
                exit;

            // J1. Simpan Bulk Perubahan Master Harga Pemasok (Beli per Produk SKU)
            case 'save_bulk_harga_pemasok':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                $hargasPemasok = (array)($_POST['harga_pemasok'] ?? []);
                $savedCount = 0;
                foreach ($hargasPemasok as $pId => $itemPrices) {
                    foreach ($itemPrices as $itId => $val) {
                        \App\Models\MasterHargaPemasok::saveHarga((int)$pId, (int)$itId, (float)$val);
                        $savedCount++;
                    }
                }
                $msg = "Berhasil memperbarui {$savedCount} tarif harga beli master pemasok per produk SKU";
                header('Location: index.php?page=master&tab=harga_pemasok&msg=' . urlencode($msg));
                exit;

            // J2. Simpan Bulk Perubahan Master Harga Mitra (Jual ke 7 Mitra)
            case 'save_bulk_harga':
            case 'save_bulk_harga_mitra':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                $hargas = (array)($_POST['harga'] ?? []);
                $savedCount = 0;
                foreach ($hargas as $mId => $itemPrices) {
                    foreach ($itemPrices as $itId => $val) {
                        MasterHarga::saveHarga((int)$mId, (int)$itId, (float)$val);
                        $savedCount++;
                    }
                }
                $msg = "Berhasil memperbarui {$savedCount} daftar master harga mitra bisnis (harga jual)";
                header('Location: index.php?page=master&tab=harga_mitra&msg=' . urlencode($msg));
                exit;

            // K. Simpan / Update Master Kategori (Jenis Barang)
            case 'save_jenis':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                Item::saveJenis($_POST);
                $msg = "Kategori / Jenis Produk " . htmlspecialchars($_POST['nama_jenis']) . " berhasil disimpan";
                header('Location: index.php?page=master&tab=produk&msg=' . urlencode($msg));
                exit;

            // L. Simpan / Update Master Lokasi Gudang (Deprecated - dialihkan ke Mitra)
            case 'save_lokasi':
            case 'delete_lokasi':
                header('Location: index.php?page=master&tab=mitra&msg=' . urlencode("Master lokasi gudang telah dihapus, acuan saat ini menggunakan 7 Mitra Bisnis"));
                exit;

            // N. Pembuatan Barcode Massal / Batch Sesuai Quantity (Tanpa Berat Awal)
            case 'generate_bulk_barcodes':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                $pemasokId= (int)($_POST['pemasok_id'] ?? 0);
                $mitraId  = (int)($_POST['mitra_id'] ?? 0);
                $itemId   = (int)($_POST['item_id'] ?? 0);
                if ($itemId <= 0) {
                    throw new Exception("Nama Barang / Produk wajib dipilih.");
                }
                $rawQty   = (int)($_POST['quantity_barcode'] ?? 1);
                if ($rawQty <= 0) {
                    throw new Exception("Jumlah barcode harus berupa bilangan positif lebih dari 0 dan tidak boleh bernilai minus.");
                }
                $qty      = min(200, $rawQty);
                $berat    = isset($_POST['berat_per_label']) && (float)$_POST['berat_per_label'] > 0 ? (float)$_POST['berat_per_label'] : 0.00;
                $noSj     = trim($_POST['no_surat_jalan'] ?? '');

                // Ambil kode pemasok untuk format penomoran
                $pmsKode = 'PMS-01';
                if ($pemasokId > 0) {
                    $pmsObj = \App\Models\Pemasok::findById($pemasokId);
                    if ($pmsObj && !empty($pmsObj['kode_pemasok'])) {
                        $pmsKode = $pmsObj['kode_pemasok'];
                    }
                }

                // PENCEGAHAN NOMOR SURAT JALAN DOUBLE:
                // Jika no_surat_jalan kosong atau sudah pernah ada di database, otomatis generate nomor unik baru
                $db = \App\Core\Database::getConnection();
                if (empty($noSj)) {
                    $noSj = \App\Models\Label::generateNextSuratJalan($pmsKode);
                } else {
                    $stmtCheckSj = $db->prepare("SELECT COUNT(*) FROM labels WHERE no_surat_jalan = ?");
                    $stmtCheckSj->execute([$noSj]);
                    if ((int)$stmtCheckSj->fetchColumn() > 0) {
                        $noSj = \App\Models\Label::generateNextSuratJalan($pmsKode);
                    }
                }

                $generatedList = \App\Models\Label::createBulk([
                    'pemasok_id' => $pemasokId,
                    'mitra_id'   => $mitraId,
                    'item_id'    => $itemId,
                    'quantity'   => $qty,
                    'berat_netto' => $berat,
                    'no_surat_jalan' => $noSj,
                    'lokasi_id'  => null
                ]);

                // Nomor surat jalan berikutnya yang continue untuk pemasok ini
                $pmsKode = 'PMS-01';
                if ($pemasokId > 0) {
                    $pmsObj = \App\Models\Pemasok::findById($pemasokId);
                    if ($pmsObj && !empty($pmsObj['kode_pemasok'])) {
                        $pmsKode = $pmsObj['kode_pemasok'];
                    }
                }
                $nextNoSj = \App\Models\Label::generateNextSuratJalan($pmsKode);

                Helper::jsonResponse([
                    'success' => true,
                    'message' => "Berhasil membuat {$qty} barcode kemasan siap cetak",
                    'labels'  => $generatedList,
                    'next_surat_jalan' => $nextNoSj
                ]);
                exit;

            // O. Update Bobot Riil Hasil Scan Barcode & Timbang
            case 'update_weight':
            case 'update_berat':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                $barcode = trim($_POST['barcode'] ?? '');
                $beratNetto = (float)($_POST['berat_netto'] ?? 0);
                $userId = (int)($_SESSION['user_login']['id'] ?? 3);

                if (empty($barcode)) throw new Exception("Barcode wajib diisi");
                if ($beratNetto <= 0) throw new Exception("Bobot timbangan harus lebih besar dari 0 KG");

                $updated = \App\Models\Label::updateWeight($barcode, $beratNetto, $userId);
                Helper::jsonResponse([
                    'success' => true,
                    'message' => "Bobot barcode {$barcode} berhasil diperbarui menjadi {$beratNetto} KG",
                    'label'   => $updated
                ]);
            // P. Mengambil data barcode untuk cetak ulang lembar (Kelompok SJ, Duplikasi 10 Pcs 1 Lembar, atau Barcode Terpilih)
            case 'get_labels_for_reprint':
                $type = trim($_GET['type'] ?? ($_POST['type'] ?? ''));
                $labels = [];

                if ($type === 'single_duplicate_10') {
                    $barcode = trim($_GET['barcode'] ?? ($_POST['barcode'] ?? ''));
                    if (empty($barcode)) throw new Exception("Barcode wajib diisi");
                    $labels = \App\Models\Label::getSingleDuplicate10($barcode);
                } elseif ($type === 'group_sj') {
                    $noSj = trim($_GET['no_surat_jalan'] ?? ($_POST['no_surat_jalan'] ?? ''));
                    $status = trim($_GET['status'] ?? ($_POST['status'] ?? 'auto_unweighed'));
                    if (empty($noSj)) throw new Exception("Nomor Surat Jalan wajib diisi");
                    $labels = \App\Models\Label::getBySuratJalan($noSj, $status);
                } elseif ($type === 'selected_ids' || $type === 'single') {
                    $raw = $_POST['barcodes'] ?? ($_GET['barcodes'] ?? ($_POST['ids'] ?? ($_GET['ids'] ?? ($_POST['barcode'] ?? ($_GET['barcode'] ?? [])))));
                    if (is_string($raw)) {
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded)) {
                            $raw = $decoded;
                        } else {
                            $raw = array_filter(array_map('trim', explode(',', $raw)));
                        }
                    } elseif (!is_array($raw)) {
                        $raw = [$raw];
                    }
                    $labels = \App\Models\Label::getByBarcodes((array)$raw);
                } else {
                    throw new Exception("Tipe cetak ulang tidak valid");
                }

                Helper::jsonResponse([
                    'success' => true,
                    'count'   => count($labels),
                    'labels'  => $labels
                ]);
                exit;

            // Q. Ambil detail Surat Jalan & Daftar Barcode Belum Ditimbang (Stasiun Timbang)
            case 'get_sj_weigh_detail':
                $noSj = trim($_GET['no_surat_jalan'] ?? ($_POST['no_surat_jalan'] ?? ''));
                if (empty($noSj)) throw new Exception("Nomor Surat Jalan wajib diisi");
                $sjDetail = \App\Models\Label::getSuratJalanDetailWithLabels($noSj);
                if (!$sjDetail) throw new Exception("Surat Jalan '{$noSj}' tidak ditemukan di sistem");
                Helper::jsonResponse([
                    'success' => true,
                    'data'    => $sjDetail
                ]);
                exit;

            // R. Ambil daftar Surat Jalan aktif untuk dropdown Stasiun Timbang
            case 'get_active_sj_list':
                $sjList = \App\Models\Label::getSuratJalanListForWeighing();
                Helper::jsonResponse([
                    'success' => true,
                    'list'    => $sjList
                ]);
                exit;

            // S. Tandai sesi penimbangan Surat Jalan selesai oleh operator
            case 'complete_weighing_sj':
                $noSj = trim($_POST['no_surat_jalan'] ?? ($_GET['no_surat_jalan'] ?? ''));
                if (empty($noSj)) throw new Exception("Nomor Surat Jalan wajib diisi");

                $sjDetail = \App\Models\Label::getSuratJalanDetailWithLabels($noSj);
                if (!$sjDetail) throw new Exception("Surat Jalan '{$noSj}' tidak ditemukan di sistem");

                if ($sjDetail['sudah_ditimbang'] <= 0) {
                    throw new Exception("Belum ada keranjang yang ditimbang untuk Surat Jalan ini. Silakan scan barcode dan isi berat timbangan terlebih dahulu.");
                }

                \App\Models\Label::markSuratJalanTimbangSelesai($noSj);

                Helper::jsonResponse([
                    'success' => true,
                    'message' => "Proses timbang untuk Surat Jalan '{$noSj}' telah dinyatakan SELESAI.",
                    'data'    => \App\Models\Label::getSuratJalanDetailWithLabels($noSj)
                ]);
                exit;

            // T. Ambil Daftar Nota Terutang per Mitra (Untuk Form Setoran AR)
            case 'get_unpaid_notas_mitra':
                $mitraId = (int)($_GET['mitra_id'] ?? 0);
                if ($mitraId <= 0) {
                    throw new Exception("Mitra ID wajib ditentukan");
                }
                $unpaid = \App\Models\Piutang::getUnpaidNotasByMitra($mitraId);
                Helper::jsonResponse([
                    'success' => true,
                    'list'    => $unpaid
                ]);
                exit;

            // U. Bukukan Setoran Pembayaran AR (Kas / Bank)
            case 'save_ar_payment':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception("Metode request harus POST");
                }
                $userId = (int)($_SESSION['user_login']['id'] ?? 1);
                $res = \App\Models\Piutang::recordPayment($_POST, $userId);
                Helper::jsonResponse([
                    'success' => true,
                    'message' => "Setoran pembayaran berhasil dibukukan dengan nomor bukti {$res['no_bukti_bayar']}.",
                    'data'    => $res
                ]);
                exit;

            // V. Riwayat Cicilan / Setoran per Nota Tertentu
            case 'get_nota_payment_history':
                $trxId = (int)($_GET['transaksi_stok_id'] ?? 0);
                if ($trxId <= 0) {
                    throw new Exception("ID transaksi nota tidak valid");
                }
                $history = \App\Models\Piutang::getPaymentHistoryByNota($trxId);
                Helper::jsonResponse([
                    'success' => true,
                    'history' => $history
                ]);
                exit;

            // W. Ambil Daftar Surat Jalan Terutang per Pemasok (Untuk Form Bayar Utang AP)
            case 'get_unpaid_sj_pemasok':
                $pemasokId = (int)($_GET['pemasok_id'] ?? 0);
                if ($pemasokId <= 0) {
                    throw new Exception("Pemasok ID wajib ditentukan");
                }
                $unpaid = \App\Models\Utang::getUnpaidSjByPemasok($pemasokId);
                Helper::jsonResponse([
                    'success' => true,
                    'list'    => $unpaid
                ]);
                exit;

            // X. Bukukan Pembayaran Utang AP ke Pemasok (Kas / Bank)
            case 'save_ap_payment':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception("Metode request harus POST");
                }
                $userId = (int)($_SESSION['user_login']['id'] ?? 1);
                $res = \App\Models\Utang::recordPayment($_POST, $userId);
                Helper::jsonResponse([
                    'success' => true,
                    'message' => "Pembayaran utang ke pemasok berhasil dibukukan dengan nomor bukti {$res['no_bukti_keluar']}.",
                    'data'    => $res
                ]);
                exit;

            // Y. Riwayat Pembayaran / Cicilan per Surat Jalan Tertentu
            case 'get_sj_payment_history':
                $noSj = trim($_GET['no_surat_jalan'] ?? '');
                if (empty($noSj)) {
                    throw new Exception("Nomor Surat Jalan wajib ditentukan");
                }
                $history = \App\Models\Utang::getPaymentHistoryBySj($noSj);
                Helper::jsonResponse([
                    'success' => true,
                    'history' => $history
                ]);
                exit;

            // Z. Upload Bukti Rekonsiliasi Setoran (Screenshot M-Banking / Slip Struk Setor Tunai CDM)
            case 'upload_bukti_rekonsiliasi':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception("Metode request harus POST");
                }
                $userId = (int)($_SESSION['user_login']['id'] ?? 1);
                $arId   = (int)($_POST['ar_id'] ?? 0);
                $ket    = trim($_POST['catatan'] ?? '');

                if ($arId <= 0) {
                    throw new Exception("ID setoran tidak valid.");
                }

                if (empty($_FILES['file_bukti']['name']) || $_FILES['file_bukti']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("File gambar bukti setoran / screenshot wajib diunggah.");
                }

                $uploadDir = __DIR__ . '/assets/uploads/bukti_setoran/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $ext = strtolower(pathinfo($_FILES['file_bukti']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'])) {
                    throw new Exception("Format bukti harus berupa gambar (JPG, PNG, WEBP) atau PDF.");
                }

                $fileName = 'BUKTI_SETOR_' . $arId . '_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
                $destPath = $uploadDir . $fileName;

                if (!move_uploaded_file($_FILES['file_bukti']['tmp_name'], $destPath)) {
                    throw new Exception("Gagal menyimpan file bukti ke server.");
                }

                $filePath = 'assets/uploads/bukti_setoran/' . $fileName;
                \App\Models\RekonsiliasiBank::attachBukti($arId, $filePath, $ket, $userId);

                Helper::jsonResponse([
                    'success'   => true,
                    'message'   => "Bukti setoran berhasil dilampirkan. Status dinyatakan MATCHED.",
                    'file_path' => $filePath
                ]);
                exit;

            // AA. Hapus Bukti Rekonsiliasi (Kembalikan ke PENDING)
            case 'remove_bukti_rekonsiliasi':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception("Metode request harus POST");
                }
                $arId = (int)($_POST['ar_id'] ?? 0);
                if ($arId <= 0) {
                    throw new Exception("ID setoran tidak valid.");
                }
                \App\Models\RekonsiliasiBank::removeBukti($arId);
                Helper::jsonResponse([
                    'success' => true,
                    'message' => "Bukti setoran dihapus. Status kembali menjadi PENDING."
                ]);
                exit;

            default:
                Helper::jsonResponse(['success' => false, 'message' => 'Action tidak dikenali'], 400);
        }
    } catch (Throwable $e) {
        Helper::jsonResponse([
            'success' => false,
            'message' => $e->getMessage()
        ], 400);
    }
    exit;
}

// 2. ROUTING HALAMAN TAMPILAN (VIEWS)
$page = $_GET['page'] ?? 'dashboard';

if ($page === 'mutasi') {
    // Menu mutasi barang telah dihapus, alur kerja langsung dari stok gudang pemasok ke mitra di Pengeluaran
    header('Location: index.php?page=pengeluaran');
    exit;
} elseif ($page === 'master_mitra') {
    $_GET['tab'] = 'mitra';
    $page = 'master';
} elseif ($page === 'master_produk') {
    $_GET['tab'] = 'produk';
    $page = 'master';
} elseif ($page === 'master_harga_pemasok') {
    $_GET['tab'] = 'harga_pemasok';
    $page = 'master';
} elseif ($page === 'master_harga' || $page === 'master_harga_mitra') {
    $_GET['tab'] = 'harga_mitra';
    $page = 'master';
} elseif ($page === 'accurate_sync') {
    $_GET['tab'] = 'accurate';
    $page = 'master';
} elseif ($page === 'estimasi_keuntungan' || $page === 'profit') {
    $page = 'profit_loss';
}

$allowedPages = [
    'dashboard'                    => __DIR__ . '/views/dashboard.php',
    'master'                       => __DIR__ . '/views/master.php',
    'timbang_cetak'                => __DIR__ . '/views/timbang_cetak.php',
    'penerimaan'                   => __DIR__ . '/views/penerimaan.php',
    'mutasi'                       => __DIR__ . '/views/mutasi.php',
    'bast_print'                   => __DIR__ . '/views/bast_print.php',
    'surat_jalan_print'            => __DIR__ . '/views/surat_jalan_print.php',
    'surat_jalan_penerimaan_print' => __DIR__ . '/views/surat_jalan_penerimaan_print.php',
    'pengeluaran'                  => __DIR__ . '/views/pengeluaran.php',
    'ar_aging'                     => __DIR__ . '/views/ar_aging.php',
    'ap_aging'                     => __DIR__ . '/views/ap_aging.php',
    'rekonsiliasi_bank'            => __DIR__ . '/views/rekonsiliasi_bank.php',
    'profit_loss'                  => __DIR__ . '/views/profit_loss.php',
    'stok_mitra'                   => __DIR__ . '/views/stok_mitra.php',
    'stok_opname'                  => __DIR__ . '/views/stok_opname.php',
    'accurate_sync'                => __DIR__ . '/views/accurate_sync.php',
    'prd_viewer'                   => __DIR__ . '/views/prd_viewer.php'
];

if (array_key_exists($page, $allowedPages)) {
    require_once $allowedPages[$page];
} else {
    // 404 Fallback
    $pageTitle = 'Halaman Tidak Ditemukan';
    require_once __DIR__ . '/views/layout/header.php';
    echo '<div style="text-align: center; padding: 60px 20px;">
            <div style="font-size: 48px; margin-bottom: 12px;">🔍 404</div>
            <h2>Halaman Tidak Ditemukan</h2>
            <p style="color: var(--text-muted); margin: 8px 0 20px;">Halaman yang Anda tuju tidak tersedia dalam navigasi sistem.</p>
            <a href="index.php?page=dashboard" class="btn btn-primary">Kembali ke Dashboard</a>
          </div>';
    require_once __DIR__ . '/views/layout/footer.php';
}
