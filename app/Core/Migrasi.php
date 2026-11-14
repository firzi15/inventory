<?php
namespace App\Core;

/**
 * Migrasi skema ringan dan idempotent. Dijalankan di awal aplikasi (di luar transaksi)
 * karena ALTER TABLE menyebabkan commit implisit.
 */
class Migrasi
{
    private static bool $done = false;

    public static function jalankan(): void
    {
        if (self::$done) return;
        $db = Database::getConnection();

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

        self::$done = true;
    }

    private static function kolom(\PDO $db, string $tabel, string $kolom, string $definisi): void
    {
        $ada = $db->query("SHOW COLUMNS FROM `$tabel` LIKE '$kolom'")->fetch();
        if (!$ada) {
            $db->exec("ALTER TABLE `$tabel` ADD COLUMN `$kolom` $definisi");
        }
    }
}
