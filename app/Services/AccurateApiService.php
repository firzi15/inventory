<?php
/**
 * Service Integrasi REST API Accurate Online
 * Menangani pembuatan antrean, pembentukan payload JSON, dan simulasi/eksekusi pengiriman.
 * Mendukung Penyesuaian Persediaan, Pemindahan Barang (Transfer Gudang), dan Faktur POS.
 */

namespace App\Services;

use Exception;
use App\Core\Database;
use App\Config\Config;

class AccurateApiService {
    /**
     * Masukkan transaksi pengeluaran umum ke antrean sinkronisasi Accurate
     */
    public static function enqueuePengeluaran(int $transaksiId, array $mitra, array $labels, float $totalKg, string $noReferensi): void {
        $db = Database::getConnection();

        $itemAggregates = self::aggregateItems($labels);

        $detailList = [];
        foreach ($itemAggregates as $sku => $agg) {
            $detailList[] = [
                'itemNo'            => $agg['itemNo'],
                'detailNotes'       => sprintf('Pengeluaran %d kemasan barcode untuk %s', $agg['packageCount'], $mitra['nama_mitra']),
                'quantity'          => round($agg['quantity'], 2),
                'adjustmentAccount' => '510101',
                'warehouseName'     => $mitra['kode_gudang_accurate']
            ];
        }

        $payload = [
            'transDate'          => date('d/m/Y'),
            'adjustmentType'     => 'REDUCE',
            'description'        => sprintf('Sinkronisasi Pengeluaran Barcode Gudang [%s] Ref: %s (Total: %.2f kg)', $mitra['nama_mitra'], $noReferensi, $totalKg),
            'detailItem'         => $detailList,
            'sourceSystem'       => 'BARCODE_WES_V1',
            'externalRefNumber'  => 'TRX-REF-' . $transaksiId
        ];

        self::saveQueue($transaksiId, '/accurate/api/item-adjustment/save.do', $payload);
    }

    /**
     * Enqueue Pemindahan Barang (Gudang Mitra -> Office/Outlet POS) ke Accurate Online
     * Menggunakan API Accurate: /accurate/api/item-transfer/save.do
     */
    public static function enqueueItemTransfer(int $transaksiId, array $mitra, array $labels, float $totalKg, string $noReferensi): void {
        $itemAggregates = self::aggregateItems($labels);

        $detailList = [];
        foreach ($itemAggregates as $sku => $agg) {
            $detailList[] = [
                'itemNo'      => $agg['itemNo'],
                'detailNotes' => sprintf('Mutasi %d kemasan dari Gudang %s ke Office Toko POS', $agg['packageCount'], $mitra['kode_mitra']),
                'quantity'    => round($agg['quantity'], 2),
                'unitName'    => 'KG'
            ];
        }

        $payload = [
            'transDate'          => date('d/m/Y'),
            'fromWarehouseName'  => $mitra['kode_gudang_accurate'], // Gudang Asal Mitra
            'toWarehouseName'    => 'GDG-OFFICE-POS',              // Gudang Tujuan Office POS
            'description'        => sprintf('Mutasi Barang Gudang ke Office POS [%s] Ref: %s (Total: %.2f kg)', $mitra['nama_mitra'], $noReferensi, $totalKg),
            'detailItem'         => $detailList,
            'sourceSystem'       => 'BARCODE_WES_V1',
            'externalRefNumber'  => 'TRX-TRF-' . $transaksiId
        ];

        self::saveQueue($transaksiId, '/accurate/api/item-transfer/save.do', $payload);
    }

    /**
     * Enqueue Penjualan Langsung ke Pembeli (POS Accurate Online)
     * Menggunakan API Accurate: /accurate/api/sales-invoice/save.do
     */
    public static function enqueuePOS(int $transaksiId, array $mitra, array $labels, float $totalKg, string $noReferensi, string $namaPembeli): void {
        $itemAggregates = self::aggregateItems($labels);

        $detailList = [];
        foreach ($itemAggregates as $sku => $agg) {
            $detailList[] = [
                'itemNo'      => $agg['itemNo'],
                'detailNotes' => sprintf('Penjualan POS %d kemasan catch-weight (%s)', $agg['packageCount'], $mitra['nama_mitra']),
                'quantity'    => round($agg['quantity'], 2),
                'unitPrice'   => 120000.00, // Harga estimasi per kg (Accurate akan otomatis mengambil daftar harga)
                'warehouseName' => $mitra['kode_gudang_accurate']
            ];
        }

        $payload = [
            'transDate'          => date('d/m/Y'),
            'customerName'       => !empty($namaPembeli) ? $namaPembeli : 'PELANGGAN UMUM POS',
            'description'        => sprintf('Transaksi POS Kasir Kasir: %s Ref: %s (Total: %.2f kg)', $namaPembeli, $noReferensi, $totalKg),
            'detailItem'         => $detailList,
            'sourceSystem'       => 'BARCODE_POS_V1',
            'externalRefNumber'  => 'TRX-POS-' . $transaksiId
        ];

        self::saveQueue($transaksiId, '/accurate/api/sales-invoice/save.do', $payload);
    }

    private static function aggregateItems(array $labels): array {
        $aggregates = [];
        foreach ($labels as $lbl) {
            $sku = $lbl['kode_item'];
            if (!isset($aggregates[$sku])) {
                $aggregates[$sku] = [
                    'itemNo'       => $sku,
                    'itemName'     => $lbl['nama_item'],
                    'quantity'     => 0.0,
                    'unit'         => 'KG',
                    'packageCount' => 0
                ];
            }
            $aggregates[$sku]['quantity'] += (float)$lbl['berat_netto'];
            $aggregates[$sku]['packageCount']++;
        }
        return $aggregates;
    }

    private static function saveQueue(int $transaksiId, string $endpoint, array $payload): void {
        $db = Database::getConnection();
        $payloadJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $stmt = $db->prepare("
            INSERT INTO accurate_sync_queue (
                transaksi_id, endpoint, payload_json, status, attempt_count, max_attempts
            ) VALUES (?, ?, ?, 'PENDING', 0, 3)
        ");
        $stmt->execute([$transaksiId, $endpoint, $payloadJson]);
    }

    /**
     * Buat HMAC-SHA256 signature berformat Base64 untuk Accurate API Token
     */
    public static function generateSignature(string $timestamp, ?string $secret = null): string {
        $cfg = Config::getAccurateConfig();
        $key = $secret ?: ($cfg['signature_secret'] ?? Config::ACCURATE_SIGNATURE_SECRET);
        return base64_encode(hash_hmac('sha256', $timestamp, $key, true));
    }

    /**
     * Susun header autentikasi resmi Accurate Online API Token
     */
    public static function getApiHeaders(?string $sessionId = null, ?string $overrideToken = null): array {
        $cfg = Config::getAccurateConfig();
        $token = trim($overrideToken ?: ($cfg['api_token'] ?? ''));
        $timestamp = date('d/m/Y H:i:s');
        $signature = self::generateSignature($timestamp, $cfg['signature_secret'] ?? null);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
            'X-Api-Timestamp: ' . $timestamp,
            'X-Api-Signature: ' . $signature
        ];

        if (!empty($sessionId)) {
            $headers[] = 'X-Session-ID: ' . $sessionId;
        }

        return $headers;
    }

    /**
     * Mengambil daftar database dari akun Accurate Online yang terikat dengan API Token
     */
    public static function fetchDatabases(?string $overrideToken = null): array {
        $cfg = Config::getAccurateConfig();
        $token = trim($overrideToken ?: ($cfg['api_token'] ?? ''));

        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'API Token belum diisi. Silakan masukkan API Token dari Accurate Store.'
            ];
        }

        $url = rtrim(Config::ACCURATE_ACCOUNT_URL, '/') . '/api/db-list.do';
        $headers = self::getApiHeaders(null, $token);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success' => false,
                'message' => 'Gagal menghubungi server Accurate: ' . $curlError
            ];
        }

        $json = json_decode($response, true);
        if ($httpCode === 200 && isset($json['s']) && $json['s'] === true) {
            $databases = $json['d'] ?? [];
            return [
                'success' => true,
                'databases' => $databases,
                'message' => sprintf('Berhasil terhubung ke Accurate Online! Ditemukan %d database.', count($databases))
            ];
        }

        $errMsg = $json['d']['message'] ?? $json['d'] ?? $json['error_description'] ?? 'Autentikasi gagal atau API Token tidak valid.';
        if (is_array($errMsg)) $errMsg = json_encode($errMsg);
        return [
            'success' => false,
            'http_code' => $httpCode,
            'message' => 'Gagal mengambil database Accurate: ' . $errMsg,
            'raw' => $response
        ];
    }

    /**
     * Buka database Accurate Online dan simpan Session ID serta Host
     */
    public static function openDatabase(int|string $dbId, ?string $overrideToken = null, ?string $dbAlias = null): array {
        $cfg = Config::getAccurateConfig();
        $token = trim($overrideToken ?: ($cfg['api_token'] ?? ''));

        if (empty($token)) {
            return ['success' => false, 'message' => 'API Token tidak ditemukan'];
        }

        $url = rtrim(Config::ACCURATE_ACCOUNT_URL, '/') . '/api/open-db.do?id=' . urlencode((string)$dbId);
        $headers = self::getApiHeaders(null, $token);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'message' => 'Koneksi timeout: ' . $curlError];
        }

        $json = json_decode($response, true);
        if ($httpCode === 200 && isset($json['s']) && $json['s'] === true) {
            $session = $json['d']['session'] ?? '';
            $host = $json['d']['host'] ?? ($cfg['host'] ?? 'https://zeus.accurate.id');
            $user = $json['d']['user']['name'] ?? 'Accurate User';

            Config::saveAccurateConfig([
                'api_token'    => $token,
                'db_id'        => (string)$dbId,
                'db_alias'     => $dbAlias ?: ($cfg['db_alias'] ?: 'Database ' . $dbId),
                'session_id'   => $session,
                'host'         => $host,
                'is_connected' => true,
                'last_sync'    => date('Y-m-d H:i:s')
            ]);

            return [
                'success' => true,
                'session' => $session,
                'host'    => $host,
                'user'    => $user,
                'message' => 'Database Accurate berhasil dibuka dan sesi aktif tersimpan!'
            ];
        }

        $errMsg = $json['d']['message'] ?? $json['d'] ?? 'Gagal membuka database Accurate';
        if (is_array($errMsg)) $errMsg = json_encode($errMsg);
        return [
            'success' => false,
            'message' => 'Gagal membuka database Accurate: ' . $errMsg
        ];
    }

    /**
     * Memastikan sesi database aktif sebelum eksekusi API transaksi
     */
    public static function ensureSession(): array {
        $cfg = Config::getAccurateConfig();
        if (empty($cfg['api_token']) || empty($cfg['db_id'])) {
            return ['active' => false, 'message' => 'API Token atau Database ID belum disetel'];
        }

        if (!empty($cfg['session_id']) && !empty($cfg['host'])) {
            return [
                'active'  => true,
                'session' => $cfg['session_id'],
                'host'    => $cfg['host'],
                'token'   => $cfg['api_token']
            ];
        }

        // Buka database jika session belum ada
        $res = self::openDatabase($cfg['db_id'], $cfg['api_token'], $cfg['db_alias'] ?? '');
        if ($res['success']) {
            return [
                'active'  => true,
                'session' => $res['session'],
                'host'    => $res['host'],
                'token'   => $cfg['api_token']
            ];
        }

        return ['active' => false, 'message' => $res['message']];
    }

    /**
     * Jalankan proses sinkronisasi untuk sebuah antrean (Mendukung Live API & Simulasi)
     */
    public static function processQueueItem(int $queueId): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT q.*, t.no_transaksi FROM accurate_sync_queue q JOIN transaksi_stok t ON q.transaksi_id = t.id WHERE q.id = ?");
        $stmt->execute([$queueId]);
        $queueItem = $stmt->fetch();

        if (!$queueItem) {
            throw new Exception("Antrean tidak ditemukan");
        }

        $newAttempt = $queueItem['attempt_count'] + 1;
        $cfg = Config::getAccurateConfig();
        $isLiveConfigured = !empty($cfg['api_token']) && !empty($cfg['db_id']);

        if ($isLiveConfigured) {
            $sess = self::ensureSession();
            if (!$sess['active']) {
                throw new Exception("Koneksi Accurate tidak siap: " . $sess['message']);
            }

            $host = rtrim($sess['host'], '/');
            $url = $host . $queueItem['endpoint'];
            $headers = self::getApiHeaders($sess['session'], $sess['token']);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $queueItem['payload_json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 25);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $parsed = json_decode($responseBody, true);

            // Jika sesi invalid atau kedaluwarsa, coba segarkan sesi sekali lagi
            if (($httpCode === 401 || (isset($parsed['s']) && $parsed['s'] === false && str_contains(strtolower($responseBody), 'session'))) && $newAttempt < $queueItem['max_attempts']) {
                $reopen = self::openDatabase($cfg['db_id'], $cfg['api_token'], $cfg['db_alias'] ?? '');
                if ($reopen['success']) {
                    $headers = self::getApiHeaders($reopen['session'], $cfg['api_token']);
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, rtrim($reopen['host'], '/') . $queueItem['endpoint']);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $queueItem['payload_json']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    $responseBody = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);
                    $parsed = json_decode($responseBody, true);
                }
            }

            $isSuccess = ($httpCode >= 200 && $httpCode < 300 && isset($parsed['s']) && $parsed['s'] === true);
        } else {
            // SIMULASI SANDBOX ACCURATE ONLINE
            $prefix = match(true) {
                str_contains($queueItem['endpoint'], 'sales-invoice') => 'ACC.POS.',
                str_contains($queueItem['endpoint'], 'item-transfer')  => 'ACC.TRF.',
                default                                                => 'ACC.ADJ.'
            };
            $simulatedAccurateDoc = $prefix . date('Ymd') . '.' . sprintf('%04d', $queueItem['transaksi_id']);
            $httpCode = 200;
            $responseBody = json_encode([
                's' => true,
                'd' => [
                    'id' => 88120 + $queueItem['id'],
                    'number' => $simulatedAccurateDoc,
                    'transDate' => date('d/m/Y'),
                    'message' => 'Simulasi REST API Accurate Online Sukses'
                ]
            ], JSON_PRETTY_PRINT);
            $isSuccess = true;
            $curlError = null;
        }

        if ($isSuccess) {
            $parsed = json_decode($responseBody, true);
            $docNo = $parsed['d']['number'] ?? ('ACC-' . time());

            $stmtUp = $db->prepare("
                UPDATE accurate_sync_queue 
                SET status = 'SUCCESS', attempt_count = ?, response_code = ?, response_body = ?, last_attempt_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmtUp->execute([$newAttempt, $httpCode, $responseBody, $queueId]);

            $stmtTrx = $db->prepare("UPDATE transaksi_stok SET status_accurate = 'SENT', no_dokumen_accurate = ? WHERE id = ?");
            $stmtTrx->execute([$docNo, $queueItem['transaksi_id']]);

            return ['success' => true, 'docNo' => $docNo, 'message' => 'Tersinkronisasi ke Accurate Online: ' . $docNo];
        } else {
            $status = ($newAttempt >= $queueItem['max_attempts']) ? 'FAILED' : 'PENDING';
            $errMsg = $curlError ?? ($parsed['d']['message'] ?? $parsed['error_description'] ?? 'Gagal menghubungi server Accurate');
            if (is_array($errMsg)) $errMsg = json_encode($errMsg);

            $stmtFail = $db->prepare("
                UPDATE accurate_sync_queue 
                SET status = ?, attempt_count = ?, response_code = ?, response_body = ?, error_message = ?, last_attempt_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmtFail->execute([$status, $newAttempt, $httpCode, $responseBody, $errMsg, $queueId]);

            return ['success' => false, 'message' => 'Gagal sinkronisasi Accurate: ' . $errMsg];
        }
    }

    public static function getQueueList(int $limit = 20): array {
        $db = Database::getConnection();
        $sql = "
            SELECT 
                q.*,
                t.no_transaksi, t.jenis_transaksi, t.alur_pergerakan, t.no_bast, t.total_kg, t.total_label,
                m.kode_mitra, m.nama_mitra, m.warna_identitas
            FROM accurate_sync_queue q
            JOIN transaksi_stok t ON q.transaksi_id = t.id
            JOIN mitra m ON t.mitra_id = m.id
            ORDER BY q.id DESC
            LIMIT ?
        ";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
