<?php
namespace App\Services;

use App\Core\Database;
use Exception;
use PDO;

/**
 * Jurnal umum (double entry). Setiap dokumen menulis jurnalnya sendiri satu kali.
 * Jurnal yang sudah terbit tidak diubah atau dihapus; koreksi dengan jurnal pembalik.
 * Pemanggilan post*() aman dilakukan di dalam transaksi database yang sedang berjalan.
 */
class JurnalService
{
    private static bool $ready = false;

    public static function ensureTables(): void
    {
        if (self::$ready) return;
        $db = Database::getConnection();
        // CREATE TABLE menyebabkan commit implisit; jangan dijalankan jika tabel sudah ada atau sedang dalam transaksi
        $ada = $db->query("SHOW TABLES LIKE 'jurnal_baris'")->fetchColumn() && $db->query("SHOW TABLES LIKE 'jurnal'")->fetchColumn();
        if ($ada) {
            self::$ready = true;
            return;
        }
        if ($db->inTransaction()) {
            throw new Exception('Tabel jurnal belum ada. Muat ulang halaman agar tabel dibuat sebelum transaksi.');
        }
        $db->exec("
            CREATE TABLE IF NOT EXISTS jurnal (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                no_jurnal VARCHAR(40) NOT NULL,
                tanggal DATE NOT NULL,
                sumber_tipe VARCHAR(30) NOT NULL,
                sumber_id BIGINT NOT NULL,
                sumber_ref VARCHAR(100) DEFAULT NULL,
                keterangan VARCHAR(255) DEFAULT NULL,
                status ENUM('POSTED','DIBALIK') NOT NULL DEFAULT 'POSTED',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sumber (sumber_tipe, sumber_id),
                KEY idx_tanggal (tanggal)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS jurnal_baris (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                jurnal_id BIGINT NOT NULL,
                kode_akun VARCHAR(10) NOT NULL,
                debit DECIMAL(16,2) NOT NULL DEFAULT 0,
                kredit DECIMAL(16,2) NOT NULL DEFAULT 0,
                KEY idx_jurnal (jurnal_id),
                KEY idx_akun (kode_akun),
                CONSTRAINT fk_jurnal_baris FOREIGN KEY (jurnal_id) REFERENCES jurnal(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        self::$ready = true;
    }

    /**
     * Tulis satu jurnal. Idempotent per (sumber_tipe, sumber_id).
     * $baris: [[kode_akun, debit, kredit], ...]; total debit harus sama dengan total kredit.
     */
    public static function post(string $tanggal, string $tipe, int $id, string $ref, string $ket, array $baris): ?int
    {
        self::ensureTables();
        $db = Database::getConnection();

        $baris = array_values(array_filter($baris, fn($b) => round((float)$b[1], 2) > 0 || round((float)$b[2], 2) > 0));
        if (!$baris) return null;

        $d = $k = 0.0;
        foreach ($baris as $b) { $d += (float)$b[1]; $k += (float)$b[2]; }
        if (abs($d - $k) > 0.01) {
            throw new Exception("Jurnal {$tipe} #{$id} tidak seimbang (debit " . number_format($d, 2) . " vs kredit " . number_format($k, 2) . ").");
        }

        $cek = $db->prepare("SELECT id FROM jurnal WHERE sumber_tipe = ? AND sumber_id = ?");
        $cek->execute([$tipe, $id]);
        if ($row = $cek->fetch(PDO::FETCH_ASSOC)) return (int)$row['id'];

        $no = 'JV-' . date('Ymd', strtotime($tanggal)) . '-' . strtoupper(substr(md5($tipe . $id), 0, 6));
        $db->prepare("INSERT INTO jurnal (no_jurnal, tanggal, sumber_tipe, sumber_id, sumber_ref, keterangan) VALUES (?,?,?,?,?,?)")
           ->execute([$no, $tanggal, $tipe, $id, $ref, $ket]);
        $jid = (int)$db->lastInsertId();

        $ins = $db->prepare("INSERT INTO jurnal_baris (jurnal_id, kode_akun, debit, kredit) VALUES (?,?,?,?)");
        foreach ($baris as $b) {
            $ins->execute([$jid, $b[0], round((float)$b[1], 2), round((float)$b[2], 2)]);
        }
        return $jid;
    }

    /** Jurnal pembalik: debit dan kredit ditukar, jurnal asal ditandai DIBALIK. */
    public static function reverse(string $tipe, int $id, string $alasan): ?int
    {
        self::ensureTables();
        $db = Database::getConnection();
        $st = $db->prepare("SELECT * FROM jurnal WHERE sumber_tipe = ? AND sumber_id = ? AND status = 'POSTED'");
        $st->execute([$tipe, $id]);
        $j = $st->fetch(PDO::FETCH_ASSOC);
        if (!$j) return null;

        $bs = $db->prepare("SELECT kode_akun, debit, kredit FROM jurnal_baris WHERE jurnal_id = ?");
        $bs->execute([$j['id']]);
        $baris = array_map(fn($b) => [$b['kode_akun'], $b['kredit'], $b['debit']], $bs->fetchAll(PDO::FETCH_ASSOC));

        $jid = self::post(date('Y-m-d'), $tipe . '_BALIK', $id, (string)$j['sumber_ref'], 'Pembalik: ' . $alasan, $baris);
        $db->prepare("UPDATE jurnal SET status = 'DIBALIK' WHERE id = ?")->execute([$j['id']]);
        return $jid;
    }

    // ------------------------------------------------------------------
    // Aturan jurnal per dokumen
    // ------------------------------------------------------------------

    public static function postPurchaseInvoice(int $invoiceId): ?int
    {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT * FROM purchase_invoices WHERE id = ?");
        $st->execute([$invoiceId]);
        $inv = $st->fetch(PDO::FETCH_ASSOC);
        if (!$inv) return null;

        $debit = match ($inv['tipe_invoice']) {
            'STOK'  => '1200',
            'ASET'  => '1100',
            default => '6000',
        };
        // faktur manual: pakai akun yang dipilih di faktur bila masih terdaftar di master COA
        if ($inv['tipe_invoice'] !== 'STOK' && !empty($inv['kode_akun_debit']) && CoaService::cari((string)$inv['kode_akun_debit'])) {
            $debit = (string)$inv['kode_akun_debit'];
        }
        $n = (float)$inv['total_nominal'];
        return self::post($inv['tanggal_invoice'], 'PURCHASE_INV', $invoiceId, $inv['no_invoice'],
            'Faktur ' . ($inv['nama_vendor'] ?: $inv['no_invoice']),
            [[$debit, $n, 0], ['2000', 0, $n]]);
    }

    public static function postApPayment(int $pembayaranId): ?int
    {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT p.*, s.nama_pemasok FROM ap_pembayaran p LEFT JOIN pemasok s ON s.id = p.pemasok_id WHERE p.id = ?");
        $st->execute([$pembayaranId]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) return null;
        $n = (float)$p['jumlah_bayar'];
        return self::post($p['tanggal_bayar'], 'AP_BAYAR', $pembayaranId, $p['no_bukti_keluar'],
            'Bayar ' . ($p['nama_pemasok'] ?? ''), [['2000', $n, 0], ['1000', 0, $n]]);
    }

    public static function postArPayment(int $pembayaranId): ?int
    {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT p.*, m.nama_mitra FROM ar_pembayaran p LEFT JOIN mitra m ON m.id = p.mitra_id WHERE p.id = ?");
        $st->execute([$pembayaranId]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) return null;
        $n = (float)$p['jumlah_bayar'];
        return self::post($p['tanggal_bayar'], 'AR_SETOR', $pembayaranId, $p['no_bukti_bayar'],
            'Setoran ' . ($p['nama_mitra'] ?? ''), [['1000', $n, 0], ['1100', 0, $n]]);
    }

    public static function postPengeluaran(int $transaksiId): ?int
    {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT t.*, m.nama_mitra FROM transaksi_stok t LEFT JOIN mitra m ON m.id = t.mitra_id WHERE t.id = ? AND t.jenis_transaksi = 'PENGELUARAN'");
        $st->execute([$transaksiId]);
        $t = $st->fetch(PDO::FETCH_ASSOC);
        if (!$t) return null;
        $jual = (float)$t['total_nilai_jual'];
        $beli = (float)$t['total_nilai_beli'];
        return self::post(substr((string)$t['created_at'], 0, 10), 'PENGELUARAN', $transaksiId, $t['no_transaksi'],
            'Pengeluaran ke ' . ($t['nama_mitra'] ?? ''),
            [['1100', $jual, 0], ['4000', 0, $jual], ['5000', $beli, 0], ['1200', 0, $beli]]);
    }

    /** Isi jurnal untuk dokumen lama yang belum punya jurnal (aman dijalankan berulang). */
    public static function backfill(): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $hasil = ['purchase' => 0, 'ap_bayar' => 0, 'ar_setor' => 0, 'pengeluaran' => 0];
        $map = [
            ['purchase',    "SELECT id FROM purchase_invoices", 'postPurchaseInvoice', 'PURCHASE_INV'],
            ['ap_bayar',    "SELECT id FROM ap_pembayaran", 'postApPayment', 'AP_BAYAR'],
            ['ar_setor',    "SELECT id FROM ar_pembayaran", 'postArPayment', 'AR_SETOR'],
            ['pengeluaran', "SELECT id FROM transaksi_stok WHERE jenis_transaksi = 'PENGELUARAN'", 'postPengeluaran', 'PENGELUARAN'],
        ];
        foreach ($map as [$key, $sql, $fn, $tipe]) {
            foreach ($db->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $ada = $db->prepare("SELECT 1 FROM jurnal WHERE sumber_tipe = ? AND sumber_id = ?");
                $ada->execute([$tipe, $id]);
                if ($ada->fetchColumn()) continue;
                if (self::$fn((int)$id)) $hasil[$key]++;
            }
        }
        return $hasil;
    }

    // ------------------------------------------------------------------
    // Pembacaan (dipakai COA)
    // ------------------------------------------------------------------

    /** Total debit dan kredit per akun: [kode => [debit, kredit]] */
    public static function totalsPerAkun(): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $out = [];
        foreach ($db->query("SELECT kode_akun, SUM(debit) d, SUM(kredit) k FROM jurnal_baris GROUP BY kode_akun") as $r) {
            $out[$r['kode_akun']] = [(float)$r['d'], (float)$r['k']];
        }
        return $out;
    }

    public static function mutasiAkun(string $kode, int $limit = 300, ?string $dari = null, ?string $sampai = null): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $sql = "
            SELECT j.tanggal, j.no_jurnal, j.sumber_ref, j.keterangan, j.status, b.debit, b.kredit
            FROM jurnal_baris b JOIN jurnal j ON j.id = b.jurnal_id
            WHERE b.kode_akun = ?";
        $p = [$kode];
        if ($dari !== null && $sampai !== null) {
            $sql .= " AND j.tanggal BETWEEN ? AND ?";
            array_push($p, $dari, $sampai);
        }
        $sql .= " ORDER BY j.tanggal DESC, j.id DESC LIMIT " . (int)$limit;
        $st = $db->prepare($sql);
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------------
    // Pembacaan Laba Rugi: 4000 Penjualan (kredit), 5000 HPP dan 6000 Biaya (debit)
    // Nilai dikembalikan dalam saldo normalnya (positif = sesuai sifat akun).
    // ------------------------------------------------------------------

    private static function netto(string $kode, float $d, float $k): float
    {
        return $kode === '4000' ? $k - $d : $d - $k;
    }

    private const GRUP = ['REVENUE' => '4000', 'COGS' => '5000', 'EXPENSE' => '6000'];

    /** [bulan 1-12 => ['4000'=>, '5000'=>, '6000'=>]] untuk satu tahun; 4000 = semua akun Pendapatan, 5000 = HPP, 6000 = Beban */
    public static function labaRugiBulanan(int $tahun): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $out = [];
        for ($m = 1; $m <= 12; $m++) $out[$m] = ['4000' => 0.0, '5000' => 0.0, '6000' => 0.0];
        $st = $db->prepare("
            SELECT MONTH(j.tanggal) m, c.tipe, SUM(b.debit) d, SUM(b.kredit) k
            FROM jurnal_baris b JOIN jurnal j ON j.id = b.jurnal_id JOIN coa_akun c ON c.kode = b.kode_akun
            WHERE YEAR(j.tanggal) = ? AND c.tipe IN ('REVENUE','COGS','EXPENSE')
            GROUP BY MONTH(j.tanggal), c.tipe");
        $st->execute([$tahun]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $g = self::GRUP[$r['tipe']];
            $out[(int)$r['m']][$g] = self::netto($g, (float)$r['d'], (float)$r['k']);
        }
        return $out;
    }

    /** [kode akun => [bulan 1-12 => nilai]] untuk akun Pendapatan, HPP, dan Beban pada satu tahun */
    public static function labaRugiAkunBulanan(int $tahun): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $st = $db->prepare("
            SELECT MONTH(j.tanggal) m, b.kode_akun, c.tipe, SUM(b.debit) d, SUM(b.kredit) k
            FROM jurnal_baris b JOIN jurnal j ON j.id = b.jurnal_id JOIN coa_akun c ON c.kode = b.kode_akun
            WHERE YEAR(j.tanggal) = ? AND c.tipe IN ('REVENUE','COGS','EXPENSE')
            GROUP BY MONTH(j.tanggal), b.kode_akun, c.tipe
            ORDER BY b.kode_akun");
        $st->execute([$tahun]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $g = self::GRUP[$r['tipe']];
            $out[$r['kode_akun']][(int)$r['m']] = self::netto($g, (float)$r['d'], (float)$r['k']);
        }
        return $out;
    }

    /** ['4000'=>, '5000'=>, '6000'=>] untuk rentang tanggal */
    public static function labaRugiRentang(string $dari, string $sampai): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $out = ['4000' => 0.0, '5000' => 0.0, '6000' => 0.0];
        $st = $db->prepare("
            SELECT c.tipe, SUM(b.debit) d, SUM(b.kredit) k
            FROM jurnal_baris b JOIN jurnal j ON j.id = b.jurnal_id JOIN coa_akun c ON c.kode = b.kode_akun
            WHERE j.tanggal BETWEEN ? AND ? AND c.tipe IN ('REVENUE','COGS','EXPENSE')
            GROUP BY c.tipe");
        $st->execute([$dari, $sampai]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $g = self::GRUP[$r['tipe']];
            $out[$g] = self::netto($g, (float)$r['d'], (float)$r['k']);
        }
        return $out;
    }

    /** Penjualan dan HPP per mitra (dari jurnal pengeluaran beserta pembaliknya). */
    public static function detailPerMitra(string $dari, string $sampai): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $st = $db->prepare("
            SELECT COALESCE(m.nama_mitra, '(tanpa mitra)') nama, b.kode_akun, SUM(b.debit) d, SUM(b.kredit) k
            FROM jurnal_baris b
            JOIN jurnal j ON j.id = b.jurnal_id
            JOIN transaksi_stok t ON t.id = j.sumber_id
            LEFT JOIN mitra m ON m.id = t.mitra_id
            WHERE j.sumber_tipe IN ('PENGELUARAN','PENGELUARAN_BALIK')
              AND b.kode_akun IN ('4000','5000') AND j.tanggal BETWEEN ? AND ?
            GROUP BY m.id, m.nama_mitra, b.kode_akun");
        $st->execute([$dari, $sampai]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[$r['nama']] ??= ['4000' => 0.0, '5000' => 0.0];
            $out[$r['nama']][$r['kode_akun']] += self::netto($r['kode_akun'], (float)$r['d'], (float)$r['k']);
        }
        ksort($out);
        return $out;
    }

    /** Biaya operasional (6000) per vendor, dari faktur beserta pembaliknya. */
    public static function detailPerVendor(string $dari, string $sampai): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $st = $db->prepare("
            SELECT COALESCE(NULLIF(TRIM(pi.nama_vendor), ''), '(tanpa vendor)') nama, SUM(b.debit) d, SUM(b.kredit) k
            FROM jurnal_baris b
            JOIN jurnal j ON j.id = b.jurnal_id
            JOIN purchase_invoices pi ON pi.id = j.sumber_id
            JOIN coa_akun c ON c.kode = b.kode_akun AND c.tipe IN ('EXPENSE','COGS')
            WHERE j.sumber_tipe IN ('PURCHASE_INV','PURCHASE_INV_BALIK')
              AND j.tanggal BETWEEN ? AND ?
            GROUP BY nama");
        $st->execute([$dari, $sampai]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[$r['nama']] = self::netto('6000', (float)$r['d'], (float)$r['k']);
        }
        ksort($out);
        return $out;
    }

    /** Laba per surat jalan keluar, memakai jurnal pengeluaran (harga jual dan HPP yang sudah dibekukan). */
    public static function labaPerSuratJalan(string $dari, string $sampai): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $st = $db->prepare("
            SELECT t.id, t.no_transaksi, t.jenis_transaksi, j.tanggal, COALESCE(m.nama_mitra, '-') nama_mitra,
                   SUM(CASE WHEN b.kode_akun = '4000' THEN b.kredit - b.debit ELSE 0 END) penjualan,
                   SUM(CASE WHEN b.kode_akun = '5000' THEN b.debit - b.kredit ELSE 0 END) hpp
            FROM jurnal j
            JOIN jurnal_baris b ON b.jurnal_id = j.id
            JOIN transaksi_stok t ON t.id = j.sumber_id
            LEFT JOIN mitra m ON m.id = t.mitra_id
            WHERE j.sumber_tipe = 'PENGELUARAN' AND j.tanggal BETWEEN ? AND ?
            GROUP BY t.id, t.no_transaksi, t.jenis_transaksi, j.tanggal, m.nama_mitra
            ORDER BY j.tanggal DESC, t.id DESC");
        $st->execute([$dari, $sampai]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Tahun-tahun yang punya jurnal, ditambah tahun berjalan. */
    public static function tahunTersedia(): array
    {
        self::ensureTables();
        $db = Database::getConnection();
        $th = array_map('intval', $db->query("SELECT DISTINCT YEAR(tanggal) FROM jurnal")->fetchAll(PDO::FETCH_COLUMN));
        $th[] = (int)date('Y');
        $th = array_unique($th);
        rsort($th);
        return $th;
    }
}
