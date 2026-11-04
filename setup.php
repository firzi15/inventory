<?php
/**
 * Setup & Database Initialization Script
 * Sistem Barcode Inventory Multi-Mitra
 * Dapat diakses via Browser: http://localhost/project/setup.php
 * Atau via Terminal: C:\xampp\php\php.exe setup.php
 */

require_once __DIR__ . '/app/Config/Config.php';

use App\Config\Config;

$isCli = (php_sapi_name() === 'cli');

function logMsg(string $msg, string $type = 'info', bool $isCli = false): void {
    if ($isCli) {
        $prefix = match ($type) {
            'success' => '[SUCCESS] ',
            'error'   => '[ERROR] ',
            'warn'    => '[WARN] ',
            default   => '[INFO] '
        };
        echo $prefix . $msg . PHP_EOL;
    }
}

$logs = [];
$success = false;

try {
    // 1. Coba koneksi ke MySQL Server
    $logs[] = ['type' => 'info', 'text' => "Menghubungkan ke MySQL di " . Config::DB_HOST . ":" . Config::DB_PORT . "..."];
    
    $rootPdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', Config::DB_HOST, Config::DB_PORT),
        Config::DB_USER,
        Config::DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 2. Buat Database jika belum ada
    $dbName = Config::DB_NAME;
    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $logs[] = ['type' => 'success', 'text' => "Database `{$dbName}` siap digunakan."];

    // 3. Sambungkan ke Database tersebut
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', Config::DB_HOST, Config::DB_PORT, $dbName),
        Config::DB_USER,
        Config::DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 4. Eksekusi Skema Tabel (schema.sql)
    $schemaSql = file_get_contents(__DIR__ . '/database/schema.sql');
    // Hilangkan baris CREATE DATABASE / USE agar kompatibel
    $statements = array_filter(array_map('trim', explode(';', $schemaSql)));
    foreach ($statements as $stmt) {
        if (!empty($stmt) && !str_starts_with(strtoupper($stmt), 'CREATE DATABASE') && !str_starts_with(strtoupper($stmt), 'USE ')) {
            $pdo->exec($stmt);
        }
    }
    $logs[] = ['type' => 'success', 'text' => "Skema tabel berhasil diimpor (users, mitra, jenis_barang, items, labels, dll)."];

    // 5. Eksekusi Data Awal (seed.sql)
    $seedSql = file_get_contents(__DIR__ . '/database/seed.sql');
    $seedStatements = array_filter(array_map('trim', explode(';', $seedSql)));
    foreach ($seedStatements as $stmt) {
        if (!empty($stmt) && !str_starts_with(strtoupper($stmt), 'USE ')) {
            $pdo->exec($stmt);
        }
    }
    $logs[] = ['type' => 'success', 'text' => "Data seeder berhasil diimpor: 7 Mitra (M01-M07), master barang, dan sampel label aktif."];

    $success = true;
} catch (Throwable $e) {
    $logs[] = ['type' => 'warn', 'text' => "MySQL Error: " . $e->getMessage() . ". Mencoba inisialisasi SQLite..."];

    try {
        $sqliteDir = dirname(Config::DB_SQLITE_PATH);
        if (!is_dir($sqliteDir)) mkdir($sqliteDir, 0777, true);
        
        $sqlitePdo = new PDO('sqlite:' . Config::DB_SQLITE_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Buat tabel SQLite
        $sqlitePdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                nama_lengkap TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'OPERATOR_INBOUND',
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS mitra (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                kode_mitra TEXT UNIQUE NOT NULL,
                nama_mitra TEXT NOT NULL,
                pic_nama TEXT,
                pic_telepon TEXT,
                kode_gudang_accurate TEXT NOT NULL,
                warna_identitas TEXT DEFAULT '#3b82f6',
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS jenis_barang (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                kode_jenis TEXT UNIQUE NOT NULL,
                nama_jenis TEXT NOT NULL,
                satuan_stok TEXT NOT NULL DEFAULT 'kg',
                toleransi_min_kg REAL NOT NULL DEFAULT 1.00,
                toleransi_maks_kg REAL NOT NULL DEFAULT 50.00,
                keterangan TEXT,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                kode_item TEXT UNIQUE NOT NULL,
                nama_item TEXT NOT NULL,
                jenis_id INTEGER NOT NULL,
                berat_standar_kg REAL,
                default_tara_kg REAL NOT NULL DEFAULT 0.500,
                satuan_accurate TEXT DEFAULT 'KG',
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS gudang_lokasi (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                kode_lokasi TEXT UNIQUE NOT NULL,
                nama_lokasi TEXT NOT NULL,
                gudang_induk TEXT NOT NULL DEFAULT 'Gudang Utama',
                suhu_ruang TEXT,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS labels (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_label TEXT UNIQUE NOT NULL,
                mitra_id INTEGER NOT NULL,
                item_id INTEGER NOT NULL,
                lokasi_id INTEGER,
                berat_bruto REAL NOT NULL,
                berat_tara REAL NOT NULL,
                berat_netto REAL NOT NULL,
                batch_no TEXT NOT NULL,
                tgl_produksi DATE,
                tgl_kadaluarsa DATE,
                status TEXT NOT NULL DEFAULT 'AKTIF',
                operator_timbang_id INTEGER,
                catatan TEXT,
                alasan_void TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS transaksi_stok (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                no_transaksi TEXT UNIQUE NOT NULL,
                jenis_transaksi TEXT NOT NULL,
                mitra_id INTEGER NOT NULL,
                total_kg REAL NOT NULL DEFAULT 0.00,
                total_label INTEGER NOT NULL DEFAULT 0,
                user_id INTEGER NOT NULL,
                lokasi_asal_id INTEGER,
                lokasi_tujuan_id INTEGER,
                no_referensi_eksternal TEXT,
                keterangan TEXT,
                status_accurate TEXT NOT NULL DEFAULT 'PENDING',
                no_dokumen_accurate TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS transaksi_stok_detail (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                transaksi_id INTEGER NOT NULL,
                label_id INTEGER NOT NULL,
                berat_netto REAL NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS accurate_sync_queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                transaksi_id INTEGER NOT NULL,
                endpoint TEXT NOT NULL,
                payload_json TEXT NOT NULL,
                attempt_count INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 3,
                last_attempt_at DATETIME,
                status TEXT NOT NULL DEFAULT 'PENDING',
                response_code INTEGER,
                response_body TEXT,
                error_message TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action_name TEXT NOT NULL,
                target_table TEXT NOT NULL,
                target_id TEXT NOT NULL,
                reason TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Seed data ke SQLite jika tabel mitra masih kosong
        $count = (int)$sqlitePdo->query("SELECT COUNT(*) FROM mitra")->fetchColumn();
        if ($count === 0) {
            $sqlitePdo->exec("
                INSERT INTO users (username, password_hash, nama_lengkap, role) VALUES
                ('admin', 'admin123', 'Administrator Sistem', 'ADMIN'),
                ('spv_gudang', 'admin123', 'Bambang Supriyanto (SPV)', 'SUPERVISOR'),
                ('opr_timbang', 'admin123', 'Rizky Operator Penerimaan', 'OPERATOR_INBOUND'),
                ('opr_kirim', 'admin123', 'Dani Operator Pengeluaran', 'OPERATOR_OUTBOUND');

                INSERT INTO mitra (kode_mitra, nama_mitra, pic_nama, kode_gudang_accurate, warna_identitas) VALUES
                ('M01', 'PT Sumber Pangan Prima', 'Hendra Wijaya', 'GDG-M01-SUMBERPANGAN', '#2563eb'),
                ('M02', 'CV Berkah Ternak Mandiri', 'Ustadz Mansur', 'GDG-M02-BERKAHTERNAK', '#16a34a'),
                ('M03', 'PT Nusantara Cold Storage', 'Agus Setiawan', 'GDG-M03-NUSANTARA', '#d97706'),
                ('M04', 'PT Global Agri Sentosa', 'Dewi Lestari', 'GDG-M04-GLOBALAGRI', '#9333ea'),
                ('M05', 'CV Mitra Makmur Daging', 'Surya Saputra', 'GDG-M05-MITRAMAKMUR', '#0891b2'),
                ('M06', 'PT Indo Jaya Sejahtera', 'Kevin Hartono', 'GDG-M06-INDOJAYA', '#e11d48'),
                ('M07', 'Koperasi Bersama Peternak', 'Joko Widodo SE', 'GDG-M07-KOPERASI', '#4f46e5');

                INSERT INTO jenis_barang (kode_jenis, nama_jenis, satuan_stok, toleransi_min_kg, toleransi_maks_kg) VALUES
                ('JB01', 'Karkas Sapi Segar', 'kg', 150.00, 450.00),
                ('JB02', 'Potongan Beku / Frozen Cuts', 'kg', 10.00, 35.00),
                ('JB03', 'Vakum Pack Daging Olahan', 'kg', 2.00, 10.00),
                ('JB04', 'Jeroan (Offal)', 'kg', 5.00, 25.00);

                INSERT INTO items (kode_item, nama_item, jenis_id, default_tara_kg) VALUES
                ('ACC-BEEF-001', 'Sirloin Cut Beku Grade A', 2, 0.650),
                ('ACC-BEEF-002', 'Tenderloin Vacuum Pack', 3, 0.200),
                ('ACC-BEEF-003', 'Rib Eye Roll Australia', 2, 0.600),
                ('ACC-BEEF-004', 'Sandung Lamur / Brisket', 2, 0.650),
                ('ACC-OFFAL-001', 'Hati Sapi Segar (Liver Box)', 4, 0.400);

                INSERT INTO gudang_lokasi (kode_lokasi, nama_lokasi) VALUES
                ('GDG-COLD-A01', 'Cold Storage Kamar 1 - Rak A1'),
                ('GDG-COLD-B01', 'Cold Storage Kamar 2 - Rak B1');

                INSERT INTO labels (id_label, mitra_id, item_id, berat_bruto, berat_tara, berat_netto, batch_no, status) VALUES
                ('M01-JB02-261022-00001', 1, 1, 25.10, 0.650, 24.45, 'BATCH-20261022-A', 'AKTIF'),
                ('M01-JB02-261022-00002', 1, 1, 24.80, 0.650, 24.15, 'BATCH-20261022-A', 'AKTIF'),
                ('M02-JB02-261022-00001', 2, 4, 24.70, 0.650, 24.05, 'BATCH-20261022-B', 'AKTIF'),
                ('M03-JB02-261022-00001', 3, 1, 26.00, 0.650, 25.35, 'BATCH-20261022-C', 'AKTIF');
            ");
        }

        $logs[] = ['type' => 'success', 'text' => "Fallback SQLite berhasil diinisialisasi (" . Config::DB_SQLITE_PATH . ")."];
        $success = true;
    } catch (Throwable $eSqlite) {
        $logs[] = ['type' => 'error', 'text' => "Gagal inisialisasi SQLite: " . $eSqlite->getMessage()];
    }
}

if ($isCli) {
    foreach ($logs as $l) {
        logMsg($l['text'], $l['type'], true);
    }
    exit($success ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inisialisasi Database - Sistem Barcode Multi-Mitra</title>
    
    <!-- Favicon Berwarna -->
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/apple-touch-icon.png">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg: #0f172a;
            --card: #1e293b;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            box-sizing: border-box;
        }
        .container {
            width: 100%;
            max-width: 680px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        .header {
            text-align: center;
            margin-bottom: 24px;
        }
        .header h1 {
            margin: 0 0 8px 0;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
        }
        .log-box {
            background: #090d16;
            border-radius: 10px;
            padding: 16px;
            border: 1px solid #1e293b;
            font-family: monospace;
            font-size: 13px;
            line-height: 1.6;
            margin: 20px 0;
            max-height: 280px;
            overflow-y: auto;
        }
        .log-item {
            display: flex;
            gap: 8px;
            margin-bottom: 6px;
        }
        .log-item.info { color: #38bdf8; }
        .log-item.success { color: #4ade80; }
        .log-item.warn { color: #facc15; }
        .log-item.error { color: #f87171; }
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .btn {
            flex: 1;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background: var(--primary);
            color: #ffffff;
        }
        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }
        .btn-outline {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border);
        }
        .btn-outline:hover {
            background: var(--border);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="font-size: 40px; margin-bottom: 12px;">📦 🏷️</div>
            <h1>Inisialisasi Sistem Barcode Inventory</h1>
            <p>Setup Skema Database, 7 Mitra Bisnis, dan Master Data Awal</p>
        </div>

        <div class="log-box">
            <?php foreach ($logs as $l): ?>
                <div class="log-item <?= htmlspecialchars($l['type']) ?>">
                    <span><?= $l['type'] === 'success' ? '✓' : ($l['type'] === 'error' ? '✕' : 'ℹ') ?></span>
                    <span><?= htmlspecialchars($l['text']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($success): ?>
            <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid var(--success); color: #34d399; padding: 12px 16px; border-radius: 8px; font-size: 14px; text-align: center;">
                🎉 <strong>Berhasil!</strong> Database dan master data 7 mitra siap digunakan.
            </div>
            <div class="btn-group">
                <a href="index.php" class="btn btn-primary">Buka Dashboard Aplikasi →</a>
                <a href="index.php?page=prd_viewer" class="btn btn-outline">Lihat Tampilan PRD Interaktif</a>
            </div>
        <?php else: ?>
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #f87171; padding: 12px 16px; border-radius: 8px; font-size: 14px; text-align: center;">
                ⚠️ Terjadi kendala saat setup. Silakan periksa kredensial database di <code>app/Config/Config.php</code>.
            </div>
            <div class="btn-group">
                <a href="setup.php" class="btn btn-primary">Coba Lagi</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
