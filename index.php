<?php
/**
 * Front Controller & Router Aplikasi
 * Sistem Barcode Inventory Multi-Mitra (Catch-Weight)
 * Mendukung Pergerakan Barang (Pengepul -> Gudang -> Office -> Pembeli POS) & BAST
 */

session_start();

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
use App\Models\User;
use App\Models\Label;
use App\Models\Mitra;
use App\Models\Item;
use App\Models\MasterHarga;
use App\Models\MasterHargaPemasok;
use App\Models\VendorNonPemasok;
use App\Models\TipeBiaya;
use App\Models\Transaksi;
use App\Services\BarcodeService;
use App\Services\AccurateApiService;

// Pastikan tabel jurnal ada sebelum transaksi apa pun berjalan (DDL tidak boleh di tengah transaksi)
try { \App\Core\Migrasi::jalankan(); \App\Services\JurnalService::ensureTables(); } catch (\Throwable $e) {}

// 1. MANAJEMEN AUTENTIKASI & AKSI LOGIN / LOGOUT
$action = $_GET['action'] ?? null;
$reqPage = $_GET['page'] ?? null;

// Aksi Login (POST form / AJAX)
if ($action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $user = User::authenticate($username, $password);
    if ($user) {
        $_SESSION['user_login'] = [
            'id'           => (int)$user['id'],
            'username'     => $user['username'],
            'nama_lengkap' => $user['nama_lengkap'],
            'role'         => $user['role']
        ];
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            Helper::jsonResponse(['success' => true, 'message' => 'Login berhasil', 'redirect' => 'index.php?page=dashboard']);
        } else {
            header('Location: index.php?page=dashboard');
            exit;
        }
    } else {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            Helper::jsonResponse(['success' => false, 'message' => 'Username atau password salah!'], 401);
        } else {
            header('Location: index.php?page=login&error=' . urlencode('Username atau password tidak sesuai!'));
            exit;
        }
    }
}

// Aksi Logout
if ($action === 'logout') {
    unset($_SESSION['user_login']);
    session_destroy();
    header('Location: index.php?page=login&msg=' . urlencode('Anda telah berhasil keluar dari sistem.'));
    exit;
}

// Pemeriksaan status login — session lama yang di-set otomatis oleh kode sebelumnya WAJIB login ulang
$validUsernames = ['nia', 'arif', 'operator'];
$isLoggedIn = !empty($_SESSION['user_login'])
    && !empty($_SESSION['user_login']['id'])
    && !empty($_SESSION['user_login']['username'])
    && in_array($_SESSION['user_login']['username'], $validUsernames, true);

// Sinkronisasi data user aktif di session jika sudah login
if ($isLoggedIn) {
    $currentUserData = User::findById((int)$_SESSION['user_login']['id']);
    if ($currentUserData) {
        $_SESSION['user_login']['nama_lengkap'] = $currentUserData['nama_lengkap'];
        $_SESSION['user_login']['username'] = $currentUserData['username'];
        $_SESSION['user_login']['role'] = $currentUserData['role'];
    }
}

// Jika belum login, batasi akses
if (!$isLoggedIn) {
    if ($action) {
        // Panggilan API AJAX saat sesi habis
        Helper::jsonResponse([
            'success' => false,
            'message' => 'Sesi login Anda tidak aktif. Silakan login kembali.'
        ], 401);
        exit;
    }
    
    // Akses via browser: arahkan ke halaman login
    if ($reqPage !== 'login') {
        header('Location: index.php?page=login');
        exit;
    }
} else {
    // Jika sudah login dan membuka halaman login, lempar ke dashboard
    if ($reqPage === 'login') {
        header('Location: index.php?page=dashboard');
        exit;
    }
}

// 2. PENANGANAN REQUEST AJAX / API ACTION (USER TERAUTENTIKASI)
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
                $terminHari   = isset($payload['termin_hari']) && $payload['termin_hari'] !== '' ? max(0, (int)$payload['termin_hari']) : null;
                $barcodes     = (array)($payload['barcodes'] ?? []);
                $userId       = (int)($_SESSION['user_login']['id'] ?? 4);

                if ($pemasokId <= 0) {
                    throw new Exception("Pemasok asal stok wajib dipilih!");
                }
                if ($mitraId <= 0) {
                    throw new Exception("Mitra penerima barang wajib dipilih!");
                }

                $result = Transaksi::processPengeluaran($pemasokId, $mitraId, $barcodes, $noReferensi, $keterangan, $userId, $namaPenerima, $terminHari);
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

            // G4. Simpan / Update Vendor Non-Pemasok (Operasional)
            case 'save_vendor_non_pemasok':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    \App\Models\VendorNonPemasok::update($id, $_POST);
                    $msg = "Data Vendor " . htmlspecialchars($_POST['nama_vendor']) . " berhasil diperbarui";
                } else {
                    \App\Models\VendorNonPemasok::create($_POST);
                    $msg = "Vendor baru " . htmlspecialchars($_POST['nama_vendor']) . " berhasil ditambahkan";
                }
                header('Location: index.php?page=master&tab=vendor_non_pemasok&msg=' . urlencode($msg) . '&msg_type=success');
                exit;

            // G5. Toggle Status Aktif / Nonaktif Vendor Non-Pemasok
            case 'toggle_vendor_non_pemasok_status':
                $id = (int)($_REQUEST['id'] ?? 0);
                if ($id <= 0) throw new Exception("ID Vendor tidak valid");
                $vendor = \App\Models\VendorNonPemasok::findById($id);
                if (!$vendor) throw new Exception("Data vendor tidak ditemukan");
                $newStatus = \App\Models\VendorNonPemasok::toggleStatus($id);
                $statusText = $newStatus == 1 ? 'diaktifkan (Aktif)' : 'dinonaktifkan (Nonaktif)';
                $msg = "Status vendor [" . htmlspecialchars($vendor['kode_vendor']) . "] " . htmlspecialchars($vendor['nama_vendor']) . " berhasil " . $statusText;
                header('Location: index.php?page=master&tab=vendor_non_pemasok&msg=' . urlencode($msg) . '&msg_type=success');
                exit;

            // G6. Hapus Vendor Non-Pemasok
            case 'delete_vendor_non_pemasok':
                $id = (int)($_GET['id'] ?? 0);
                if ($id <= 0) throw new Exception("ID Vendor tidak valid");
                $vendor = \App\Models\VendorNonPemasok::findById($id);
                if (!$vendor) throw new Exception("Data vendor tidak ditemukan");
                try {
                    \App\Models\VendorNonPemasok::delete($id);
                    $msg = "Vendor [" . htmlspecialchars($vendor['kode_vendor']) . "] " . htmlspecialchars($vendor['nama_vendor']) . " berhasil dihapus secara permanen.";
                    $msgType = 'success';
                } catch (\Exception $e) {
                    $msg = $e->getMessage();
                    $msgType = 'danger';
                }
                header('Location: index.php?page=master&tab=vendor_non_pemasok&msg=' . urlencode($msg) . '&msg_type=' . $msgType);
                exit;

            // G6b. Master Rekening Bank: simpan / aktif-nonaktif / hapus
            case 'save_rekening_bank':
            case 'toggle_rekening_bank':
            case 'delete_rekening_bank':
                try {
                    if ($action === 'save_rekening_bank') {
                        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                        $msg = \App\Models\RekeningBank::simpan($_POST);
                    } elseif ($action === 'toggle_rekening_bank') {
                        $msg = \App\Models\RekeningBank::ubahStatus((int)($_GET['id'] ?? 0));
                    } else {
                        $msg = \App\Models\RekeningBank::hapus((int)($_GET['id'] ?? 0));
                    }
                    $msgType = 'success';
                } catch (\Exception $e) {
                    $msg = $e->getMessage();
                    $msgType = 'danger';
                }
                header('Location: index.php?page=master&tab=rekening_bank&msg=' . urlencode($msg) . '&msg_type=' . $msgType);
                exit;

            // G6c. Master COA: simpan / aktif-nonaktif / hapus
            case 'save_coa':
            case 'toggle_coa':
            case 'delete_coa':
                try {
                    if ($action === 'save_coa') {
                        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                        $msg = \App\Services\CoaService::simpan($_POST, empty($_POST['edit']));
                    } elseif ($action === 'toggle_coa') {
                        $msg = \App\Services\CoaService::ubahStatus((string)($_GET['kode'] ?? $_GET['id'] ?? ''));
                    } else {
                        $msg = \App\Services\CoaService::hapus((string)($_GET['kode'] ?? $_GET['id'] ?? ''));
                    }
                    $msgType = 'success';
                } catch (\Exception $e) {
                    $msg = $e->getMessage();
                    $msgType = 'danger';
                }
                header('Location: index.php?page=master&tab=coa&msg=' . urlencode($msg) . '&msg_type=' . $msgType);
                exit;

            // G7. Simpan / Update Tipe Biaya Operasional
            case 'save_tipe_biaya':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    \App\Models\TipeBiaya::update($id, $_POST);
                    $msg = "Tipe Biaya " . htmlspecialchars($_POST['nama_biaya']) . " berhasil diperbarui";
                } else {
                    \App\Models\TipeBiaya::create($_POST);
                    $msg = "Tipe Biaya baru " . htmlspecialchars($_POST['nama_biaya']) . " berhasil ditambahkan";
                }
                header('Location: index.php?page=master&tab=tipe_biaya&msg=' . urlencode($msg) . '&msg_type=success');
                exit;

            // G8. Toggle Status Aktif / Nonaktif Tipe Biaya
            case 'toggle_tipe_biaya_status':
                $id = (int)($_REQUEST['id'] ?? 0);
                if ($id <= 0) throw new Exception("ID Tipe Biaya tidak valid");
                $biaya = \App\Models\TipeBiaya::findById($id);
                if (!$biaya) throw new Exception("Data tipe biaya tidak ditemukan");
                $newStatus = \App\Models\TipeBiaya::toggleStatus($id);
                $statusText = $newStatus == 1 ? 'diaktifkan (Aktif)' : 'dinonaktifkan (Nonaktif)';
                $msg = "Status tipe biaya [" . htmlspecialchars($biaya['kode_biaya']) . "] " . htmlspecialchars($biaya['nama_biaya']) . " berhasil " . $statusText;
                header('Location: index.php?page=master&tab=tipe_biaya&msg=' . urlencode($msg) . '&msg_type=success');
                exit;

            // G9. Hapus Tipe Biaya
            case 'delete_tipe_biaya':
                $id = (int)($_GET['id'] ?? 0);
                if ($id <= 0) throw new Exception("ID Tipe Biaya tidak valid");
                $biaya = \App\Models\TipeBiaya::findById($id);
                if (!$biaya) throw new Exception("Data tipe biaya tidak ditemukan");
                try {
                    \App\Models\TipeBiaya::delete($id);
                    $msg = "Tipe Biaya [" . htmlspecialchars($biaya['kode_biaya']) . "] " . htmlspecialchars($biaya['nama_biaya']) . " berhasil dihapus secara permanen.";
                    $msgType = 'success';
                } catch (\Exception $e) {
                    $msg = $e->getMessage();
                    $msgType = 'danger';
                }
                header('Location: index.php?page=master&tab=tipe_biaya&msg=' . urlencode($msg) . '&msg_type=' . $msgType);
                exit;

            // G10. API Data Vendor & Biaya untuk Dropdown JSON
            case 'get_vendor_non_pemasok_json':
                $vendors = \App\Models\VendorNonPemasok::getAllActive();
                Helper::jsonResponse([
                    'success' => true,
                    'data' => $vendors
                ]);
                break;

            case 'get_tipe_biaya_json':
                $biayas = \App\Models\TipeBiaya::getAllActive();
                Helper::jsonResponse([
                    'success' => true,
                    'data' => $biayas
                ]);
                break;

            // H. Simpan / Update Master Mitra
            case 'save_mitra':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    Mitra::update($id, $_POST);
                    $msg = "Data Mitra " . htmlspecialchars($_POST['nama_mitra'] ?? '') . " berhasil diperbarui";
                } else {
                    $newId = Mitra::create($_POST);
                    $msg = "Mitra baru " . htmlspecialchars(Mitra::findById($newId)['kode_mitra'] ?? '') . " berhasil ditambahkan";
                }
                header('Location: index.php?page=master&tab=mitra&msg=' . urlencode($msg));
                exit;

            // I. Simpan / Update Master Produk (Item SKU)
            case 'save_item':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    Item::update($id, $_POST);
                    $msg = "Produk " . htmlspecialchars($_POST['nama_item'] ?? '') . " berhasil diperbarui";
                } else {
                    $newId = Item::create($_POST);
                    $msg = "Produk baru " . htmlspecialchars(Item::findById($newId)['kode_item'] ?? '') . " berhasil ditambahkan";
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
                        \App\Models\MasterHargaPemasok::saveHarga((int)$pId, (int)$itId, (float)$val, null, $_POST['alasan_perubahan'] ?? null);
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
                        MasterHarga::saveHarga((int)$mId, (int)$itId, (float)$val, null, $_POST['alasan_perubahan'] ?? null);
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

            // O1. Simpan 1 Box Penerimaan Baru Langsung dengan Bobot Riil (Alur Satu Pintu)
            case 'save_weighed_box':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                $pemasokId  = (int)($_POST['pemasok_id'] ?? 0);
                $itemId     = (int)($_POST['item_id'] ?? 0);
                $beratNetto = (float)($_POST['berat_netto'] ?? 0);
                $noSj       = trim($_POST['no_surat_jalan'] ?? '');
                $userId     = (int)($_SESSION['user_login']['id'] ?? 3);

                if ($pemasokId <= 0) throw new Exception("Pemasok wajib dipilih.");
                if ($itemId <= 0) throw new Exception("Nama Barang / Produk wajib dipilih.");
                if ($beratNetto <= 0) throw new Exception("Bobot timbangan harus lebih besar dari 0 KG.");
                if (empty($noSj)) throw new Exception("Nomor Surat Jalan wajib diisi.");

                $label = \App\Models\Label::createWeighedBox([
                    'pemasok_id'     => $pemasokId,
                    'item_id'        => $itemId,
                    'berat_netto'    => $beratNetto,
                    'no_surat_jalan' => $noSj,
                    'operator_id'    => $userId
                ]);

                $sessionSummary = \App\Models\Label::getInboundSessionSummary($noSj);

                Helper::jsonResponse([
                    'success'         => true,
                    'message'         => "Box {$label['nama_item']} ({$beratNetto} KG) berhasil disimpan dan siap dicetak.",
                    'label'           => $label,
                    'session_summary' => $sessionSummary
                ]);
                exit;

            // O2. Ambil Ringkasan Sesi Penerimaan Aktif per Surat Jalan
            case 'get_inbound_session_summary':
                $noSj = trim($_GET['no_surat_jalan'] ?? ($_POST['no_surat_jalan'] ?? ''));
                if (empty($noSj)) throw new Exception("Nomor Surat Jalan wajib diisi.");
                $summary = \App\Models\Label::getInboundSessionSummary($noSj);
                Helper::jsonResponse([
                    'success' => true,
                    'summary' => $summary
                ]);
                exit;

            // O3. Selesaikan Sesi Surat Jalan Penerimaan Ini
            case 'finish_inbound_session':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                $noSj = trim($_POST['no_surat_jalan'] ?? '');
                $pemasokId = (int)($_POST['pemasok_id'] ?? 0);
                if (empty($noSj)) throw new Exception("Nomor Surat Jalan wajib diisi.");

                \App\Models\Label::markSuratJalanTimbangSelesai($noSj);

                // Auto-create / sync Purchase Invoice resmi (Akun 1200 Persediaan -> Akun 2000 Utang)
                $invoiceId = \App\Models\PurchaseInvoice::createFromSuratJalan($noSj, $pemasokId);

                $pmsKode = 'PMS-01';
                if ($pemasokId > 0) {
                    $pmsObj = \App\Models\Pemasok::findById($pemasokId);
                    if ($pmsObj && !empty($pmsObj['kode_pemasok'])) {
                        $pmsKode = $pmsObj['kode_pemasok'];
                    }
                }
                $nextNoSj = \App\Models\Label::generateNextSuratJalan($pmsKode);

                Helper::jsonResponse([
                    'success'          => true,
                    'message'          => "Sesi Surat Jalan {$noSj} telah diselesaikan.",
                    'no_surat_jalan'   => $noSj,
                    'invoice_id'       => $invoiceId,
                    'next_surat_jalan' => $nextNoSj,
                    'print_bast_url'   => "index.php?page=surat_jalan_penerimaan_print&no_surat_jalan=" . urlencode($noSj)
                ]);
                exit;

            // PI1. Buat Purchase Invoice Manual (Biaya Operasional / Non-Stok)
            case 'create_purchase_invoice':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode request harus POST");
                $userId = (int)($_SESSION['user_login']['id'] ?? 1);
                
                $data = $_POST;
                $data['created_by'] = $userId;
                
                // Parse items jika dikirim dalam bentuk JSON atau array
                if (!empty($_POST['items_json'])) {
                    $parsed = json_decode($_POST['items_json'], true);
                    if (is_array($parsed)) {
                        $data['items'] = $parsed;
                    }
                }

                $newInvoiceId = \App\Models\PurchaseInvoice::createManual($data);
                $newInv = \App\Models\PurchaseInvoice::findById($newInvoiceId);

                Helper::jsonResponse([
                    'success'    => true,
                    'message'    => "Purchase Invoice {$newInv['no_invoice']} ({$newInv['nama_vendor']}) berhasil dibukukan.",
                    'invoice_id' => $newInvoiceId,
                    'invoice'    => $newInv
                ]);
                exit;

            // PI2. Detail Lengkap Purchase Invoice untuk Modal
            case 'get_purchase_invoice_detail':
                $invId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
                if ($invId <= 0) throw new Exception("ID Invoice tidak valid.");
                $inv = \App\Models\PurchaseInvoice::findById($invId);
                if (!$inv) throw new Exception("Purchase Invoice tidak ditemukan.");

                Helper::jsonResponse([
                    'success' => true,
                    'invoice' => $inv
                ]);
                exit;

            // PB1. Pembatalan dokumen (jurnal keuangan ikut dibalik). Hanya ADMIN / SUPERVISOR; faktur manual juga FINANCE.
            case 'batal_pengeluaran':
            case 'batal_penerimaan':
            case 'batal_purchase_invoice':
            case 'batal_bayar_ap':
            case 'batal_setoran_ar':
            case 'batal_kas':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode request harus POST");
                $roleBatal = strtoupper($_SESSION['user_login']['role'] ?? '');
                $boleh = ['ADMIN', 'SUPERVISOR'];
                if (in_array($action, ['batal_purchase_invoice', 'batal_bayar_ap', 'batal_setoran_ar', 'batal_kas'], true)) $boleh[] = 'FINANCE';
                if (!in_array($roleBatal, $boleh, true)) {
                    throw new Exception("Akun Anda tidak memiliki izin untuk membatalkan dokumen ini.");
                }
                $uidBatal = (int)($_SESSION['user_login']['id'] ?? 0);
                $alasanBatal = trim($_POST['alasan'] ?? '');
                if ($action === 'batal_kas') {
                    $hasilBatal = \App\Services\PembatalanService::batalKas((int)($_POST['id'] ?? 0), $alasanBatal, $uidBatal);
                    $pesanBatal = "Transaksi kas {$hasilBatal['no_bukti']} dibatalkan dan saldo kas dikoreksi.";
                } elseif ($action === 'batal_pengeluaran') {
                    $hasilBatal = \App\Services\PembatalanService::batalPengeluaran((int)($_POST['id'] ?? 0), $alasanBatal, $uidBatal);
                    $pesanBatal = "Surat jalan keluar {$hasilBatal['no_transaksi']} dibatalkan. Barang kembali ke stok gudang dan piutang mitra dikoreksi.";
                } elseif ($action === 'batal_penerimaan') {
                    $hasilBatal = \App\Services\PembatalanService::batalPenerimaan(trim($_POST['no_surat_jalan'] ?? ''), $alasanBatal, $uidBatal);
                    $pesanBatal = "Penerimaan {$hasilBatal['no_surat_jalan']} dibatalkan ({$hasilBatal['jumlah_keranjang']} keranjang). Stok, faktur, dan utang dikoreksi.";
                } elseif ($action === 'batal_bayar_ap') {
                    $hasilBatal = \App\Services\PembatalanService::batalPembayaranAp((int)($_POST['id'] ?? 0), $alasanBatal, $uidBatal);
                    $pesanBatal = "Pembayaran {$hasilBatal['no_bukti']} dibatalkan. Faktur kembali terutang dan kas dikoreksi.";
                } elseif ($action === 'batal_setoran_ar') {
                    $hasilBatal = \App\Services\PembatalanService::batalSetoranAr((int)($_POST['id'] ?? 0), $alasanBatal, $uidBatal);
                    $pesanBatal = "Setoran {$hasilBatal['no_bukti']} dibatalkan. Nota kembali terutang dan kas dikoreksi.";
                } else {
                    $hasilBatal = \App\Services\PembatalanService::batalPurchaseInvoice((int)($_POST['id'] ?? 0), $alasanBatal, $uidBatal);
                    $pesanBatal = "Faktur {$hasilBatal['no_invoice']} dibatalkan dan utang dikoreksi.";
                }
                Helper::jsonResponse(['success' => true, 'message' => $pesanBatal]);
                exit;

            // G2a. Hapus Master Mitra / Produk / Kategori (hanya jika tidak dipakai menu lain)
            case 'delete_mitra':
            case 'delete_item':
            case 'delete_jenis':
                $idDel = (int)($_GET['id'] ?? 0);
                $tabDel = $action === 'delete_mitra' ? 'mitra' : 'produk';
                try {
                    if ($idDel <= 0) throw new Exception("ID tidak valid");
                    if ($action === 'delete_mitra') {
                        Mitra::delete($idDel);
                        $msg = "Mitra berhasil dihapus.";
                    } elseif ($action === 'delete_item') {
                        Item::delete($idDel);
                        $msg = "Produk berhasil dihapus.";
                    } else {
                        Item::deleteJenis($idDel);
                        $msg = "Kategori berhasil dihapus.";
                    }
                    $msgType = 'success';
                } catch (\Exception $e) {
                    $msg = $e->getMessage();
                    $msgType = 'danger';
                }
                header('Location: index.php?page=master&tab=' . $tabDel . '&msg=' . urlencode($msg) . '&msg_type=' . $msgType);
                exit;

            // PI3. Hapus Purchase Invoice Manual (Hanya jika belum ada pembayaran)
            case 'delete_purchase_invoice':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode request harus POST");
                $invId = (int)($_POST['id'] ?? 0);
                if ($invId <= 0) throw new Exception("ID Invoice tidak valid.");

                $inv = \App\Models\PurchaseInvoice::findById($invId);
                if (!$inv) throw new Exception("Invoice tidak ditemukan.");
                if ($inv['tipe_invoice'] === 'STOK') {
                    throw new Exception("Invoice stok barang tidak dapat dihapus manual, terikat dengan Surat Jalan {$inv['no_surat_jalan']}.");
                }
                if ((float)$inv['total_bayar'] > 0) {
                    throw new Exception("Invoice sudah memiliki riwayat pembayaran kas keluar dan tidak dapat dihapus.");
                }

                $db = \App\Core\Database::getConnection();
                \App\Services\JurnalService::reverse('PURCHASE_INV', $invId, 'Faktur ' . $inv['no_invoice'] . ' dihapus');
                $db->prepare("DELETE FROM purchase_invoices WHERE id = ?")->execute([$invId]);

                Helper::jsonResponse([
                    'success' => true,
                    'message' => "Purchase Invoice {$inv['no_invoice']} berhasil dihapus."
                ]);
                exit;

            // O4. Hapus 1 Box Penerimaan yang Salah Input di Sesi Aktif
            case 'delete_inbound_box':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode harus POST");
                $labelId = (int)($_POST['label_id'] ?? 0);
                $barcode = trim($_POST['barcode'] ?? '');
                $noSj = trim($_POST['no_surat_jalan'] ?? '');

                $db = \App\Core\Database::getConnection();
                if ($labelId > 0) {
                    $stmt = $db->prepare("DELETE FROM labels WHERE id = ?");
                    $stmt->execute([$labelId]);
                } elseif (!empty($barcode)) {
                    $stmt = $db->prepare("DELETE FROM labels WHERE id_label = ?");
                    $stmt->execute([$barcode]);
                } else {
                    throw new Exception("ID Label / Barcode wajib ditentukan");
                }

                $summary = !empty($noSj) ? \App\Models\Label::getInboundSessionSummary($noSj) : null;
                Helper::jsonResponse([
                    'success' => true,
                    'message' => "Box barcode {$barcode} berhasil dihapus dari sesi.",
                    'session_summary' => $summary
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

                if ($type === 'single_duplicate_10' || $type === 'single_duplicate') {
                    $barcode = trim($_GET['barcode'] ?? ($_POST['barcode'] ?? ''));
                    $qty = (int)($_GET['qty'] ?? ($_POST['qty'] ?? ($type === 'single_duplicate_10' ? 10 : 1)));
                    if (empty($barcode)) throw new Exception("Barcode wajib diisi");
                    $labels = \App\Models\Label::getSingleDuplicate($barcode, $qty);
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

            // Rincian per box untuk satu Surat Jalan dan artikel (laporan Barang Datang)
            case 'rincian_box_penerimaan':
                $sjBox = trim($_GET['sj'] ?? '');
                $itemBox = (int)($_GET['item'] ?? 0);
                if ($sjBox === '' || $itemBox <= 0) throw new Exception("Surat Jalan atau artikel tidak valid.");
                Helper::jsonResponse(['success' => true, 'box' => \App\Services\BukuService::rincianBox($sjBox, $itemBox)]);
                exit;

            // Z0. Detail rekonsiliasi satu hari (rincian setoran + rekonsiliasi bila sudah ada)
            case 'detail_rekonsiliasi_harian':
                $tglDet = trim($_GET['tanggal'] ?? '');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglDet)) throw new Exception("Tanggal tidak valid.");
                Helper::jsonResponse([
                    'success' => true,
                    'setoran' => \App\Models\RekonsiliasiBank::setoranTanggal($tglDet),
                    'rekon'   => \App\Models\RekonsiliasiBank::ambil($tglDet)
                ]);
                exit;

            // T. Ubah jatuh tempo faktur pemasok / nota mitra (ADMIN, SUPERVISOR, FINANCE)
            case 'ubah_jatuh_tempo':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode request harus POST");
                if (!in_array(strtoupper($_SESSION['user_login']['role'] ?? ''), ['ADMIN', 'SUPERVISOR', 'FINANCE'], true)) {
                    Helper::jsonResponse(['success' => false, 'message' => 'Hanya Admin, Supervisor, atau Finance yang dapat mengubah jatuh tempo.'], 403);
                    exit;
                }
                $pesanTempo = \App\Services\AgingService::ubahJatuhTempo((string)($_POST['jenis'] ?? ''), (int)($_POST['id'] ?? 0), (string)($_POST['tanggal'] ?? ''), (int)($_SESSION['user_login']['id'] ?? 0));
                Helper::jsonResponse(['success' => true, 'message' => $pesanTempo]);
                exit;

            // TP. Tutup harian: pemeriksaan (semua peran login), tutup (ADMIN/FINANCE), buka kembali (ADMIN)
            case 'cek_periode':
                $tCek = (string)($_GET['tanggal'] ?? '');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tCek) || strtotime($tCek) === false) throw new Exception('Tanggal tidak valid.');
                Helper::jsonResponse(['success' => true, 'nama' => \App\Services\PeriodeService::namaTanggal($tCek), 'cek' => \App\Services\PeriodeService::cek($tCek), 'ringkas' => \App\Services\PeriodeService::ringkas($tCek)]);
                exit;
            case 'tutup_periode':
            case 'tutup_sampai':
            case 'buka_periode':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode request harus POST");
                $rolePer = strtoupper($_SESSION['user_login']['role'] ?? '');
                $bolehPer = $action === 'buka_periode' ? ['ADMIN'] : ['ADMIN', 'FINANCE'];
                if (!in_array($rolePer, $bolehPer, true)) {
                    Helper::jsonResponse(['success' => false, 'message' => $action === 'buka_periode' ? 'Hanya Admin yang dapat membuka kembali hari yang sudah ditutup.' : 'Hanya Admin atau Finance yang dapat menutup hari.'], 403);
                    exit;
                }
                $uidPer = (int)($_SESSION['user_login']['id'] ?? 0);
                $tglPer = (string)($_POST['tanggal'] ?? '');
                if ($action === 'buka_periode') $pesanPer = \App\Services\PeriodeService::buka($tglPer, (string)($_POST['alasan'] ?? ''), $uidPer);
                elseif ($action === 'tutup_sampai') $pesanPer = \App\Services\PeriodeService::tutupSampai($tglPer, (string)($_POST['catatan'] ?? ''), $uidPer);
                else $pesanPer = \App\Services\PeriodeService::tutup($tglPer, (string)($_POST['catatan'] ?? ''), $uidPer);
                Helper::jsonResponse(['success' => true, 'message' => $pesanPer]);
                exit;

            // K. Transaksi Kas & Bank: catat biaya / setoran modal / prive (ADMIN, SUPERVISOR, FINANCE)
            case 'simpan_kas':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode request harus POST");
                $roleKas = strtoupper($_SESSION['user_login']['role'] ?? '');
                if (!in_array($roleKas, ['ADMIN', 'SUPERVISOR', 'FINANCE'], true)) {
                    Helper::jsonResponse(['success' => false, 'message' => 'Hanya Admin, Supervisor, atau Finance yang dapat mencatat transaksi kas.'], 403);
                    exit;
                }
                $hasilKas = \App\Services\KasService::catat($_POST, (int)($_SESSION['user_login']['id'] ?? 0));
                Helper::jsonResponse(['success' => true, 'message' => "Transaksi {$hasilKas['no_bukti']} tersimpan dan jurnalnya terbit."]);
                exit;

            // Z. Rekonsiliasi Bank Harian: total setoran satu hari dicocokkan dengan uang masuk di bank + lampiran screenshot
            case 'simpan_rekonsiliasi_harian':
            case 'buka_rekonsiliasi_harian':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Metode request harus POST");
                $roleRek = strtoupper($_SESSION['user_login']['role'] ?? '');
                if (!in_array($roleRek, ['ADMIN', 'SUPERVISOR', 'FINANCE'], true)) {
                    Helper::jsonResponse(['success' => false, 'message' => 'Hanya Admin, Supervisor, atau Finance yang dapat melakukan rekonsiliasi.'], 403);
                    exit;
                }
                $userId = (int)($_SESSION['user_login']['id'] ?? 1);
                $tglRek = trim($_POST['tanggal'] ?? '');

                if ($action === 'buka_rekonsiliasi_harian') {
                    \App\Models\RekonsiliasiBank::bukaKembali($tglRek, (string)($_POST['alasan'] ?? ''), $userId);
                    Helper::jsonResponse(['success' => true, 'message' => "Rekonsiliasi tanggal {$tglRek} dibuka kembali. Setoran pada tanggal itu kembali bisa diubah."]);
                    exit;
                }

                $files = [];
                $berkas = $_FILES['bukti'] ?? null;
                if ($berkas && is_array($berkas['name'])) {
                    foreach ($berkas['name'] as $i => $nm) {
                        if ($nm === '' || $berkas['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                        if ($berkas['error'][$i] !== UPLOAD_ERR_OK) throw new Exception("Gagal mengunggah file {$nm}.");
                        $ext = strtolower(pathinfo($nm, PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) throw new Exception("File {$nm}: format harus JPG, PNG, WEBP, atau PDF.");
                        if ($berkas['size'][$i] > 8 * 1024 * 1024) throw new Exception("File {$nm} lebih dari 8 MB.");
                        $files[] = [$berkas['tmp_name'][$i], $nm, $ext];
                    }
                }
                if (!$files) throw new Exception("Lampirkan minimal satu screenshot / slip bank.");

                $uploadDir = __DIR__ . '/assets/uploads/bukti_setoran/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $tersimpan = [];
                foreach ($files as $n => [$tmp, $asli, $ext]) {
                    $fileName = 'REKON_' . str_replace('-', '', $tglRek) . '_' . date('YmdHis') . '_' . $n . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
                    if (!move_uploaded_file($tmp, $uploadDir . $fileName)) throw new Exception("Gagal menyimpan file {$asli} ke server.");
                    $tersimpan[] = ['assets/uploads/bukti_setoran/' . $fileName, $asli];
                }
                try {
                    $hasilRek = \App\Models\RekonsiliasiBank::simpan($tglRek, (float)($_POST['total_bank'] ?? 0), (string)($_POST['catatan'] ?? ''), $tersimpan, $userId);
                } catch (Throwable $e) {
                    foreach ($tersimpan as [$path]) @unlink(__DIR__ . '/' . $path);
                    throw $e;
                }
                Helper::jsonResponse([
                    'success' => true,
                    'message' => $hasilRek['status'] === 'SESUAI'
                        ? "Rekonsiliasi tanggal {$tglRek} sesuai."
                        : "Rekonsiliasi tanggal {$tglRek} disimpan dengan selisih " . Helper::formatRupiah($hasilRek['selisih']) . "."
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
    'login'                        => __DIR__ . '/views/login.php',
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
    'kas_bank'                     => __DIR__ . '/views/kas_bank.php',
    'surat_jalan'                  => __DIR__ . '/views/surat_jalan.php',
    'tutup_periode'                => __DIR__ . '/views/tutup_periode.php',
    'profit_loss'                  => __DIR__ . '/views/profit_loss.php',
    'coa'                          => __DIR__ . '/views/coa.php',
    'purchase_book'                => __DIR__ . '/views/book.php',
    'barang_datang'                => __DIR__ . '/views/barang_datang.php',
    'sales_book'                   => __DIR__ . '/views/book.php',
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
