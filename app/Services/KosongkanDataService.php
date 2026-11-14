<?php
/**
 * Kosongkan data aktivitas, untuk masa uji coba.
 * Yang dihapus: transaksi dan catatan yang dihasilkan pemakaian (penerimaan, label, pengeluaran, faktur, pembayaran,
 * kas dan bank, jurnal, tutup harian, antrian Accurate, serta file bukti setoran).
 * Yang TIDAK disentuh: data master (pemasok, mitra, produk, harga, COA, rekening bank, pengguna, gudang, dst.).
 * Sebelum menghapus, dibuat backup pengaman otomatis; bila backup gagal, tidak ada yang dihapus.
 */

namespace App\Services;

use App\Core\Database;

class KosongkanDataService
{
    /** Setel false setelah aplikasi dipakai sungguhan (go-live) agar data tidak bisa dihapus lewat menu ini. */
    public const AKTIF = true;

    /** Kelompok tabel aktivitas yang dikosongkan. */
    public const GRUP = [
        'stok'   => ['Penerimaan, label barcode, dan pengeluaran', ['labels', 'transaksi_stok_detail', 'transaksi_stok']],
        'faktur' => ['Faktur pembelian dan pembayaran utang/piutang', ['purchase_invoice_items', 'purchase_invoices', 'ap_alokasi_pembayaran', 'ap_pembayaran', 'ar_alokasi_pembayaran', 'ar_pembayaran']],
        'kas'    => ['Kas, bank, rekonsiliasi, dan target setoran', ['kas_transaksi', 'bank_rekonsiliasi', 'bank_mutasi', 'rekonsiliasi_harian_bukti', 'rekonsiliasi_harian', 'target_setoran_harian']],
        'jurnal' => ['Jurnal akuntansi', ['jurnal_baris', 'jurnal']],
        'tutup'  => ['Tutup harian', ['tutup_harian', 'periode_tutup']],
        'sync'   => ['Antrian kirim ke Accurate', ['accurate_sync_queue']],
    ];

    /** Pilihan tambahan (tidak aktif bawaan untuk log audit). */
    public const OPSI_AUDIT = ['audit_logs'];

    /** Data master yang tidak disentuh, untuk ditampilkan sebagai jaminan. */
    public const MASTER = [
        'pemasok' => 'Pemasok nelayan', 'vendor_non_pemasok' => 'Vendor non-pemasok', 'mitra' => 'Mitra bisnis',
        'items' => 'Produk', 'jenis_barang' => 'Kategori produk', 'master_harga_pemasok' => 'Harga pemasok', 'master_harga' => 'Harga mitra',
        'master_tipe_biaya' => 'Tipe biaya', 'coa_akun' => 'Akun COA', 'bank_rekening' => 'Bank mitra', 'rekening_bank' => 'Rekening bank',
        'gudang_lokasi' => 'Lokasi gudang', 'users' => 'Pengguna', 'log_harga' => 'Log perubahan harga',
    ];

    private static function ada(\PDO $db, string $tabel): bool
    {
        $st = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $st->execute([$tabel]);
        return (int)$st->fetchColumn() > 0;
    }

    private static function hitung(\PDO $db, string $tabel): int
    {
        return self::ada($db, $tabel) ? (int)$db->query("SELECT COUNT(*) FROM `{$tabel}`")->fetchColumn() : 0;
    }

    private static function berkasUpload(): array
    {
        $dir = BackupService::folderUpload();
        $hasil = [];
        if (!is_dir($dir)) return $hasil;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) if ($f->isFile() && $f->getFilename() !== '.gitkeep') $hasil[] = $f->getPathname();
        return $hasil;
    }

    /** Jumlah baris saat ini per kelompok, per master, dan file upload. */
    public static function ringkas(): array
    {
        $db = Database::getConnection();
        $grup = [];
        foreach (self::GRUP as $kode => [$label, $tabel]) {
            $rinci = [];
            $total = 0;
            foreach ($tabel as $t) { $n = self::hitung($db, $t); $rinci[$t] = $n; $total += $n; }
            $grup[$kode] = ['label' => $label, 'tabel' => $rinci, 'total' => $total];
        }
        $master = [];
        foreach (self::MASTER as $t => $label) $master[$t] = ['label' => $label, 'jumlah' => self::hitung($db, $t)];
        return [
            'grup' => $grup,
            'master' => $master,
            'audit' => self::hitung($db, 'audit_logs'),
            'upload' => count(self::berkasUpload()),
        ];
    }

    /**
     * @param bool $hapusAudit hapus juga log audit
     * @param bool $hapusUpload hapus juga file bukti setoran
     * @return array{pesan:string,pengaman:string,dihapus:int}
     */
    public static function kosongkan(int $userId, bool $hapusAudit, bool $hapusUpload): array
    {
        if (!self::AKTIF) throw new \Exception('Fitur kosongkan data dimatikan.');
        if (Database::getDriver() !== 'mysql') throw new \Exception('Hanya untuk database MySQL.');
        @set_time_limit(300);

        // 1. Backup pengaman dulu; gagal berarti berhenti
        $pengaman = BackupService::buat($userId, 'sebelumpulih');

        // 2. Kosongkan tabel (TRUNCATE juga mengembalikan nomor urut ke awal)
        $db = Database::getConnection();
        $tabel = [];
        foreach (self::GRUP as [, $daftar]) foreach ($daftar as $t) $tabel[] = $t;
        if ($hapusAudit) foreach (self::OPSI_AUDIT as $t) $tabel[] = $t;

        $dihapus = 0;
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($tabel as $t) {
                if (!self::ada($db, $t)) continue;
                $dihapus += (int)$db->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
                $db->exec("TRUNCATE TABLE `{$t}`");
            }
            if (self::ada($db, 'bank_rekening')) $db->exec('UPDATE bank_rekening SET saldo_berjalan = 0');
        } finally {
            $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        // 3. File bukti setoran
        $berkas = 0;
        if ($hapusUpload) {
            foreach (self::berkasUpload() as $f) if (@unlink($f)) $berkas++;
        }

        BackupService::audit($userId, 'KOSONGKAN_DATA', 'data_aktivitas',
            "Data aktivitas dikosongkan ({$dihapus} baris" . ($hapusUpload ? ", {$berkas} file bukti" : '') . ($hapusAudit ? ', log audit ikut dihapus' : '') . "); pengaman: {$pengaman['nama']}");
        return [
            'pesan' => "Data aktivitas dikosongkan ({$dihapus} baris" . ($hapusUpload ? ", {$berkas} file bukti" : '') . "). Data master tidak diubah. Backup pengaman: {$pengaman['nama']}.",
            'pengaman' => $pengaman['nama'],
            'dihapus' => $dihapus,
        ];
    }
}
