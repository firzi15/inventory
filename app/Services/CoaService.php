<?php
namespace App\Services;

use App\Core\Database;
use Exception;
use PDO;

/**
 * Master COA (tabel coa_akun) beserta saldo dan mutasi yang dibaca dari jurnal umum (JurnalService).
 * Delapan akun inti (is_system) dipakai posting otomatis dokumen dan tidak boleh dihapus / dipindah tipe.
 */
class CoaService
{
    public const TIPE = [
        'ASSET'     => ['Aset',       'Asset',            'Debit',  1000, 1999],
        'LIABILITY' => ['Kewajiban',  'Liability',        'Credit', 2000, 2999],
        'EQUITY'    => ['Ekuitas',    "Owner's Equity",   'Credit', 3000, 3999],
        'REVENUE'   => ['Pendapatan', 'Revenue',          'Credit', 4000, 4999],
        'COGS'      => ['HPP',        'Cost of Goods Sold', 'Debit', 5000, 5999],
        'EXPENSE'   => ['Beban',      'Expense',          'Debit',  6000, 9999],
    ];

    /** Akun bawaan sesuai kaidah akuntansi: [kode, nama, tipe, induk, system, saldo_normal(null=ikut tipe), keterangan] */
    private static function bawaan(): array
    {
        return [
            ['1000', 'Kas & Bank', 'ASSET', null, 1, null, 'Kas dan rekening bank'],
            ['1010', 'Kas Kecil', 'ASSET', '1000', 0, null, 'Kas tunai operasional harian'],
            ['1020', 'Bank', 'ASSET', '1000', 0, null, 'Rekening bank perusahaan'],
            ['1100', 'Piutang & Aset Lainnya', 'ASSET', null, 1, null, 'Piutang mitra dan aset non-stok'],
            ['1110', 'Piutang Karyawan', 'ASSET', '1100', 0, null, 'Kasbon / pinjaman karyawan'],
            ['1120', 'Uang Muka Pembelian', 'ASSET', '1100', 0, null, 'Pembayaran di muka ke pemasok / vendor'],
            ['1130', 'Biaya Dibayar Dimuka', 'ASSET', '1100', 0, null, 'Sewa, asuransi, dan lain-lain yang dibayar di muka'],
            ['1150', 'Peralatan & Mesin', 'ASSET', '1100', 0, null, 'Timbangan, mesin pendingin, peralatan gudang'],
            ['1160', 'Kendaraan', 'ASSET', '1100', 0, null, 'Truk dan kendaraan operasional'],
            ['1170', 'Bangunan & Gudang', 'ASSET', '1100', 0, null, 'Bangunan, gudang, cold storage'],
            ['1190', 'Akumulasi Penyusutan', 'ASSET', '1100', 0, 'Credit', 'Pengurang nilai aset tetap (akun kontra)'],
            ['1200', 'Inventory / Persediaan', 'ASSET', null, 1, null, 'Persediaan barang dagang'],
            ['2000', 'Utang', 'LIABILITY', null, 1, null, 'Utang usaha ke pemasok dan vendor'],
            ['2100', 'Utang Gaji', 'LIABILITY', '2000', 0, null, 'Gaji dan upah yang belum dibayar'],
            ['2200', 'Utang Pajak', 'LIABILITY', '2000', 0, null, 'PPN, PPh, dan pajak lain yang belum disetor'],
            ['2300', 'Pinjaman Bank', 'LIABILITY', '2000', 0, null, 'Pinjaman jangka pendek / panjang'],
            ['2400', 'Uang Muka Penjualan', 'LIABILITY', '2000', 0, null, 'Uang muka dari mitra sebelum barang keluar'],
            ['3000', 'Modal', 'EQUITY', null, 1, null, 'Modal pemilik'],
            ['3100', 'Laba Ditahan', 'EQUITY', '3000', 0, null, 'Akumulasi laba tahun lalu'],
            ['3200', 'Prive / Penarikan Pemilik', 'EQUITY', '3000', 0, 'Debit', 'Penarikan dana oleh pemilik (akun kontra)'],
            ['4000', 'Penjualan / Pendapatan', 'REVENUE', null, 1, null, 'Penjualan barang ke mitra'],
            ['4100', 'Pendapatan Jasa', 'REVENUE', '4000', 0, null, 'Pendapatan jasa lain di luar penjualan barang'],
            ['4200', 'Pendapatan Lain-lain', 'REVENUE', '4000', 0, null, 'Pendapatan di luar usaha utama'],
            ['5000', 'HPP', 'COGS', null, 1, null, 'Harga pokok barang yang terjual'],
            ['5100', 'Biaya Angkut Pembelian', 'COGS', '5000', 0, null, 'Ongkos angkut barang masuk'],
            ['5200', 'Selisih Persediaan', 'COGS', '5000', 0, null, 'Selisih hasil stok opname'],
            ['6000', 'Biaya Operasional', 'EXPENSE', null, 1, null, 'Biaya operasional umum'],
            ['6100', 'Gaji & Upah', 'EXPENSE', '6000', 0, null, 'Gaji, upah harian, lembur'],
            ['6200', 'Listrik, Air & Telepon', 'EXPENSE', '6000', 0, null, 'Utilitas gudang dan kantor'],
            ['6300', 'BBM & Transportasi', 'EXPENSE', '6000', 0, null, 'Solar, tol, parkir, ongkos kirim'],
            ['6400', 'Sewa', 'EXPENSE', '6000', 0, null, 'Sewa gudang, kendaraan, peralatan'],
            ['6500', 'Pemeliharaan & Perbaikan', 'EXPENSE', '6000', 0, null, 'Servis mesin pendingin, perbaikan gudang'],
            ['6600', 'Kemasan, Es & Pengawet', 'EXPENSE', '6000', 0, null, 'Es balok, box styrofoam, plastik, lakban'],
            ['6700', 'Penyusutan', 'EXPENSE', '6000', 0, null, 'Beban penyusutan aset tetap'],
            ['6800', 'ATK & Administrasi', 'EXPENSE', '6000', 0, null, 'Alat tulis, cetak, administrasi'],
            ['6900', 'Biaya Lain-lain', 'EXPENSE', '6000', 0, null, 'Biaya di luar kelompok lain'],
            ['6950', 'Biaya Bank & Administrasi Bank', 'EXPENSE', '6000', 0, null, 'Biaya transfer dan administrasi rekening'],
        ];
    }

    private static bool $ready = false;

    /** Buat tabel dan isi akun bawaan (aman diulang; akun yang sudah ada tidak diubah). Jalankan di luar transaksi. */
    public static function ensureTable(): void
    {
        if (self::$ready) return;
        $db = Database::getConnection();
        $ada = $db->query("SHOW TABLES LIKE 'coa_akun'")->fetchColumn();
        if (!$ada) {
            if ($db->inTransaction()) throw new Exception('Tabel COA belum ada. Muat ulang halaman.');
            $db->exec("
                CREATE TABLE coa_akun (
                    kode VARCHAR(10) NOT NULL PRIMARY KEY,
                    nama VARCHAR(100) NOT NULL,
                    tipe ENUM('ASSET','LIABILITY','EQUITY','REVENUE','COGS','EXPENSE') NOT NULL,
                    saldo_normal ENUM('Debit','Credit') NOT NULL,
                    induk VARCHAR(10) DEFAULT NULL,
                    is_system TINYINT(1) NOT NULL DEFAULT 0,
                    aktif TINYINT(1) NOT NULL DEFAULT 1,
                    keterangan VARCHAR(255) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_tipe (tipe)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        if (!$db->inTransaction()) {
            $ins = $db->prepare("INSERT IGNORE INTO coa_akun (kode, nama, tipe, saldo_normal, induk, is_system, keterangan) VALUES (?,?,?,?,?,?,?)");
            foreach (self::bawaan() as [$kode, $nama, $tipe, $induk, $sys, $normal, $ket]) {
                $ins->execute([$kode, $nama, $tipe, $normal ?? self::TIPE[$tipe][2], $induk, $sys, $ket]);
            }
        }
        self::$ready = true;
    }

    private static function semuaRow(): array
    {
        self::ensureTable();
        return Database::getConnection()->query("SELECT * FROM coa_akun ORDER BY kode")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Struktur akun: kode => [nama, type, sign, system, grup, induk, tipe, aktif, keterangan, depth]
     * Urutan mengikuti kode (induk selalu sebelum anaknya).
     */
    public static function accounts(): array
    {
        $rows = self::semuaRow();
        $by = [];
        foreach ($rows as $r) $by[$r['kode']] = $r;
        $out = [];
        foreach ($rows as $r) {
            $depth = 0;
            for ($p = $r['induk']; $p && isset($by[$p]) && $depth < 6; $p = $by[$p]['induk']) $depth++;
            $t = self::TIPE[$r['tipe']];
            $out[$r['kode']] = [$r['nama'], $t[1], $r['saldo_normal'], (bool)$r['is_system'], $t[0], $r['induk'],
                                $r['tipe'], (bool)$r['aktif'], (string)$r['keterangan'], $depth];
        }
        return $out;
    }

    public static function cari(string $kode): ?array
    {
        self::ensureTable();
        $st = Database::getConnection()->prepare("SELECT * FROM coa_akun WHERE kode = ?");
        $st->execute([$kode]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Akun yang boleh dipilih di faktur pembelian manual: ['biaya' => [...], 'aset' => [...]] (hanya aktif). */
    public static function akunFaktur(): array
    {
        $biaya = $aset = [];
        foreach (self::semuaRow() as $r) {
            if (!$r['aktif']) continue;
            $item = ['kode' => $r['kode'], 'nama' => $r['nama'], 'tipe' => $r['tipe']];
            if ($r['tipe'] === 'EXPENSE' || $r['tipe'] === 'COGS') $biaya[] = $item;
            elseif ($r['tipe'] === 'ASSET' && !in_array($r['kode'], ['1000', '1200'], true)) $aset[] = $item;
        }
        return ['biaya' => $biaya, 'aset' => $aset];
    }

    /** Validasi akun debit untuk faktur manual; kembalikan baris akun atau lempar Exception. */
    public static function akunFakturValid(string $kode): array
    {
        $r = self::cari($kode);
        if (!$r || !$r['aktif']) throw new Exception("Akun {$kode} tidak ditemukan atau nonaktif.");
        $boleh = $r['tipe'] === 'EXPENSE' || $r['tipe'] === 'COGS'
            || ($r['tipe'] === 'ASSET' && !in_array($kode, ['1000', '1200'], true));
        if (!$boleh) throw new Exception("Akun {$kode} {$r['nama']} tidak dapat dipakai pada faktur pembelian.");
        return $r;
    }

    /** Dokumen yang menulis jurnal ke akun (keterangan di halaman) */
    public static function sources(): array
    {
        $inti = [
            '1000' => 'Setoran mitra, pembayaran ke pemasok, dan Transaksi Kas & Bank (biaya, modal, prive)',
            '1100' => 'Nota Pengeluaran, setoran mitra, dan faktur aset',
            '1200' => 'Faktur Penerimaan (masuk) dan Pengeluaran (keluar)',
            '2000' => 'Purchase Invoice dan pembayaran ke pemasok',
            '3000' => 'Setoran modal dan prive dari Transaksi Kas & Bank',
            '4000' => 'Nilai jual Pengeluaran ke mitra',
            '5000' => 'Nilai beli barang pada Pengeluaran ke mitra',
            '6000' => 'Faktur Operasional dan Lainnya',
        ];
        $out = [];
        foreach (self::semuaRow() as $r) {
            $out[$r['kode']] = $inti[$r['kode']] ?? ($r['keterangan'] ?: 'Dipilih pada faktur pembelian manual');
        }
        return $out;
    }

    /** Saldo semua akun menurut saldo normalnya (positif = searah sign). */
    public static function balances(): array
    {
        $tot = JurnalService::totalsPerAkun();
        $out = [];
        foreach (self::accounts() as $kode => $a) {
            [$d, $k] = $tot[$kode] ?? [0.0, 0.0];
            $out[$kode] = $a[2] === 'Debit' ? $d - $k : $k - $d;
        }
        return $out;
    }

    /** Total debit dan kredit seluruh jurnal (harus sama). */
    public static function keseimbangan(): array
    {
        $d = $k = 0.0;
        foreach (JurnalService::totalsPerAkun() as [$ad, $ak]) { $d += $ad; $k += $ak; }
        return ['debit' => $d, 'kredit' => $k, 'seimbang' => abs($d - $k) < 0.01];
    }

    // ------------------------------------------------------------------
    // Pengelolaan master
    // ------------------------------------------------------------------

    /** Pemakaian akun: jumlah baris jurnal, faktur, tipe biaya, dan sub-akun yang memakainya. */
    public static function pemakaian(string $kode): array
    {
        self::ensureTable();
        $db = Database::getConnection();
        $q = function (string $sql) use ($db, $kode): int {
            $st = $db->prepare($sql);
            $st->execute([$kode]);
            return (int)$st->fetchColumn();
        };
        return [
            'jurnal' => $q("SELECT COUNT(*) FROM jurnal_baris WHERE kode_akun = ?"),
            'faktur' => $q("SELECT COUNT(*) FROM purchase_invoices WHERE kode_akun_debit = ?"),
            'tipe_biaya' => $q("SELECT COUNT(*) FROM master_tipe_biaya WHERE kode_akun = ?"),
            'anak' => $q("SELECT COUNT(*) FROM coa_akun WHERE induk = ?"),
        ];
    }

    /** Saran kode berikutnya di bawah suatu induk (kelipatan 10 setelah anak terakhir). */
    public static function sarankanKode(string $induk): string
    {
        $p = self::cari($induk);
        if (!$p) return '';
        $db = Database::getConnection();
        $st = $db->prepare("SELECT MAX(CAST(kode AS UNSIGNED)) FROM coa_akun WHERE induk = ?");
        $st->execute([$induk]);
        $maks = (int)$st->fetchColumn();
        $baru = $maks > 0 ? $maks + 10 : (int)$induk + 10;
        $batas = self::TIPE[$p['tipe']][4];
        while ($baru <= $batas && self::cari((string)$baru)) $baru += 10;
        return $baru <= $batas ? (string)$baru : '';
    }

    public static function simpan(array $d, bool $baru): string
    {
        self::ensureTable();
        $db = Database::getConnection();
        $kode = trim((string)($d['kode'] ?? ''));
        $nama = trim((string)($d['nama'] ?? ''));
        $ket = trim((string)($d['keterangan'] ?? ''));
        if ($nama === '') throw new Exception('Nama akun wajib diisi.');

        if ($baru) {
            if (!preg_match('/^\d{4}$/', $kode)) throw new Exception('Kode akun harus 4 digit angka.');
            if (self::cari($kode)) throw new Exception("Kode akun {$kode} sudah dipakai.");
            $induk = trim((string)($d['induk'] ?? ''));
            if ($induk === '') throw new Exception('Pilih akun induk.');
            $p = self::cari($induk);
            if (!$p) throw new Exception('Akun induk tidak ditemukan.');
            $tipe = $p['tipe'];
            [, , , $min, $maks] = self::TIPE[$tipe];
            if ((int)$kode < $min || (int)$kode > $maks) {
                throw new Exception("Kode untuk akun " . self::TIPE[$tipe][0] . " harus di rentang {$min}-{$maks}.");
            }
            $normal = in_array($d['saldo_normal'] ?? '', ['Debit', 'Credit'], true) ? $d['saldo_normal'] : self::TIPE[$tipe][2];
            $db->prepare("INSERT INTO coa_akun (kode, nama, tipe, saldo_normal, induk, is_system, aktif, keterangan) VALUES (?,?,?,?,?,0,1,?)")
               ->execute([$kode, $nama, $tipe, $normal, $induk, $ket ?: null]);
            return "Akun {$kode} {$nama} ditambahkan.";
        }

        $r = self::cari($kode);
        if (!$r) throw new Exception('Akun tidak ditemukan.');
        // nama dan keterangan boleh diubah; kode, tipe, dan induk tetap supaya jurnal lama tidak berubah makna
        $db->prepare("UPDATE coa_akun SET nama = ?, keterangan = ? WHERE kode = ?")->execute([$nama, $ket ?: null, $kode]);
        $db->prepare("UPDATE purchase_invoices SET nama_akun_debit = ? WHERE kode_akun_debit = ? AND status_bayar <> 'BATAL'")->execute([$nama, $kode]);
        $db->prepare("UPDATE master_tipe_biaya SET nama_akun = ? WHERE kode_akun = ?")->execute([$nama, $kode]);
        return "Akun {$kode} diperbarui.";
    }

    public static function ubahStatus(string $kode): string
    {
        $r = self::cari($kode);
        if (!$r) throw new Exception('Akun tidak ditemukan.');
        if ($r['is_system']) throw new Exception("Akun {$kode} dipakai posting otomatis dan tidak dapat dinonaktifkan.");
        $baru = $r['aktif'] ? 0 : 1;
        Database::getConnection()->prepare("UPDATE coa_akun SET aktif = ? WHERE kode = ?")->execute([$baru, $kode]);
        return "Akun {$kode} " . ($baru ? 'diaktifkan.' : 'dinonaktifkan.');
    }

    public static function hapus(string $kode): string
    {
        $r = self::cari($kode);
        if (!$r) throw new Exception('Akun tidak ditemukan.');
        if ($r['is_system']) throw new Exception("Akun {$kode} adalah akun inti dan tidak dapat dihapus.");
        $p = self::pemakaian($kode);
        if ($p['anak'] > 0) throw new Exception("Akun {$kode} masih punya sub-akun. Hapus sub-akunnya dulu.");
        if ($p['jurnal'] > 0 || $p['faktur'] > 0) throw new Exception("Akun {$kode} sudah dipakai di jurnal / faktur, jadi tidak dapat dihapus. Nonaktifkan saja.");
        if ($p['tipe_biaya'] > 0) throw new Exception("Akun {$kode} masih dipakai Master Tipe Biaya. Ubah akun di Tipe Biaya dulu.");
        Database::getConnection()->prepare("DELETE FROM coa_akun WHERE kode = ?")->execute([$kode]);
        return "Akun {$kode} {$r['nama']} dihapus.";
    }

    /** Mutasi satu akun: [tanggal, dokumen, ket, debit, kredit] */
    public static function mutasi(string $kode, int $limit = 300, ?string $dari = null, ?string $sampai = null): array
    {
        $rows = [];
        foreach (JurnalService::mutasiAkun($kode, $limit, $dari, $sampai) as $r) {
            $ket = $r['keterangan'] . ($r['status'] === 'DIBALIK' ? ' (dibalik)' : '');
            $rows[] = [
                'tanggal' => $r['tanggal'],
                'dokumen' => $r['sumber_ref'] ?: $r['no_jurnal'],
                'ket'     => $ket,
                'debit'   => (float)$r['debit'],
                'kredit'  => (float)$r['kredit'],
            ];
        }
        return $rows;
    }
}
