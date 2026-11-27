<?php
namespace App\Core;

/**
 * Migrasi skema ringan dan idempotent. Dijalankan di awal aplikasi (di luar transaksi)
 * karena ALTER TABLE menyebabkan commit implisit.
 */
class Migrasi
{
    private static bool $done = false;

    /** Naikkan angka ini setiap menambah langkah migrasi baru supaya semua server menjalankannya sekali lagi. */
    private const VERSI = 2; // v2: akun COA 2100 Uang Muka Pelanggan (titipan setoran mitra)

    private static function berkasPenanda(): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migrasi_' . md5(__DIR__ . '|' . \App\Config\Config::getDbName()) . '_v' . self::VERSI . '.ok';
    }

    /**
     * Migrasi memeriksa skema lewat puluhan SHOW COLUMNS / SHOW TABLES (lambat, ratusan ms) dan sebelumnya
     * dijalankan di SETIAP request. Sekarang cukup sekali: sesudahnya ada berkas penanda, dan tiap request hanya
     * memastikan kolom/tabel hasil migrasi masih ada (SELECT ... LIMIT 0, cepat). Bila database diganti dengan
     * versi lama, probe gagal dan migrasi penuh berjalan lagi.
     */
    private static function sudahMutakhir(\PDO $db): bool
    {
        if (!is_file(self::berkasPenanda())) return false;
        try {
            $db->query("SELECT alasan_batal, dibatalkan_at, dibatalkan_oleh, termin_hari, tanggal_jatuh_tempo FROM transaksi_stok LIMIT 0");
            $db->query("SELECT status, jumlah_asal FROM ap_pembayaran LIMIT 0");
            $db->query("SELECT status, jumlah_asal FROM ar_pembayaran LIMIT 0");
            $db->query("SELECT id FROM log_harga LIMIT 0");
            if (!$db->query("SELECT 1 FROM coa_akun WHERE kode = '2100'")->fetchColumn()) return false;
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function jalankan(): void
    {
        if (self::$done) return;
        $db = Database::getConnection();
        if (self::sudahMutakhir($db)) { self::$done = true; return; }

        // Pengeluaran yang dibatalkan: jenis berubah ke PENGELUARAN_BATAL sehingga otomatis keluar dari
        // semua laporan yang memfilter jenis_transaksi = 'PENGELUARAN'; baris tetap ada sebagai jejak.
        $tipe = (string)$db->query("SHOW COLUMNS FROM transaksi_stok LIKE 'jenis_transaksi'")->fetch(\PDO::FETCH_ASSOC)['Type'];
        if (strpos($tipe, 'PENGELUARAN_BATAL') === false) {
            $db->exec("ALTER TABLE transaksi_stok MODIFY jenis_transaksi ENUM('PENERIMAAN','PENGELUARAN','MUTASI','OPNAME','PENYESUAIAN','PENGELUARAN_BATAL') NOT NULL");
        }
        self::kolom($db, 'transaksi_stok', 'alasan_batal', "VARCHAR(255) NULL");
        self::kolom($db, 'transaksi_stok', 'dibatalkan_at', "DATETIME NULL");
        self::kolom($db, 'transaksi_stok', 'dibatalkan_oleh', "INT NULL");

        $st = (string)$db->query("SHOW COLUMNS FROM purchase_invoices LIKE 'status_bayar'")->fetch(\PDO::FETCH_ASSOC)['Type'];
        if (strpos($st, 'BATAL') === false) {
            $db->exec("ALTER TABLE purchase_invoices MODIFY status_bayar ENUM('BELUM_BAYAR','SEBAGIAN','LUNAS','BATAL') NOT NULL DEFAULT 'BELUM_BAYAR'");
        }
        self::kolom($db, 'purchase_invoices', 'alasan_batal', "VARCHAR(255) NULL");
        self::kolom($db, 'purchase_invoices', 'dibatalkan_at', "DATETIME NULL");

        // Pembatalan pembayaran: jumlah_bayar / nominal_alokasi dinolkan agar semua laporan yang menjumlahkannya
        // otomatis benar; nilai aslinya disimpan di jumlah_asal / nominal_asal sebagai jejak.
        foreach (['ap_pembayaran', 'ar_pembayaran'] as $t) {
            self::kolom($db, $t, 'status', "ENUM('AKTIF','BATAL') NOT NULL DEFAULT 'AKTIF'");
            self::kolom($db, $t, 'jumlah_asal', "DECIMAL(14,2) NULL");
            self::kolom($db, $t, 'alasan_batal', "VARCHAR(255) NULL");
            self::kolom($db, $t, 'dibatalkan_at', "DATETIME NULL");
            self::kolom($db, $t, 'dibatalkan_oleh', "INT NULL");
        }
        self::kolom($db, 'ap_alokasi_pembayaran', 'nominal_asal', "DECIMAL(14,2) NULL");
        self::kolom($db, 'ar_alokasi_pembayaran', 'nominal_asal', "DECIMAL(14,2) NULL");

        \App\Services\CoaService::ensureTable();
        \App\Models\RekonsiliasiBank::ensureTables();
        \App\Services\LogHargaService::ensureTables();
        \App\Models\RekeningBank::ensureTable();
        // Jatuh tempo nota penjualan; NULL dianggap jatuh tempo pada tanggal nota
        self::kolom($db, 'transaksi_stok', 'termin_hari', "INT NULL");
        self::kolom($db, 'transaksi_stok', 'tanggal_jatuh_tempo', "DATE NULL");
        \App\Services\KasService::ensureTable();
        \App\Services\PeriodeService::ensureTable();

        self::$done = true;
        @file_put_contents(self::berkasPenanda(), date('c'));
    }

    private static function kolom(\PDO $db, string $tabel, string $kolom, string $definisi): void
    {
        $ada = $db->query("SHOW COLUMNS FROM `$tabel` LIKE '$kolom'")->fetch();
        if (!$ada) {
            $db->exec("ALTER TABLE `$tabel` ADD COLUMN `$kolom` $definisi");
        }
    }
}
