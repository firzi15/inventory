<?php
namespace App\Services;

use App\Core\Database;
use App\Core\KodeGenerator;
use Exception;
use PDO;

/**
 * Transaksi Kas & Bank: pengeluaran biaya, setoran modal, dan prive yang tidak lewat faktur atau nota.
 * Sisi kas selalu akun 1000 (Kas & Bank), sama seperti setoran mitra dan pembayaran pemasok,
 * sehingga saldo kas di COA satu angka. Dokumen tidak dihapus; pembatalan membuat jurnal pembalik.
 */
class KasService
{
    public const KAS = '1000';

    public const JENIS = [
        'BIAYA' => ['Biaya', 'keluar', 'Biaya dibayar tunai atau transfer di luar faktur'],
        'MODAL' => ['Setoran Modal', 'masuk', 'Dana masuk dari pemilik'],
        'PRIVE' => ['Prive', 'keluar', 'Penarikan dana oleh pemilik'],
    ];

    private static bool $ready = false;

    public static function ensureTable(): void
    {
        if (self::$ready) return;
        $db = Database::getConnection();
        if (!$db->query("SHOW TABLES LIKE 'kas_transaksi'")->fetchColumn()) {
            if ($db->inTransaction()) throw new Exception('Tabel kas belum ada. Muat ulang halaman.');
            $db->exec("
                CREATE TABLE IF NOT EXISTS kas_transaksi (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    no_bukti VARCHAR(30) NOT NULL UNIQUE,
                    tanggal DATE NOT NULL,
                    jenis ENUM('BIAYA','MODAL','PRIVE') NOT NULL,
                    kode_akun VARCHAR(10) NOT NULL,
                    nominal DECIMAL(16,2) NOT NULL,
                    metode ENUM('TUNAI','TRANSFER') NOT NULL DEFAULT 'TUNAI',
                    rekening_id INT DEFAULT NULL,
                    no_referensi VARCHAR(80) DEFAULT NULL,
                    keterangan VARCHAR(255) NOT NULL,
                    status ENUM('AKTIF','BATAL') NOT NULL DEFAULT 'AKTIF',
                    alasan_batal VARCHAR(255) DEFAULT NULL,
                    dibatalkan_at DATETIME DEFAULT NULL,
                    dibatalkan_oleh INT DEFAULT NULL,
                    user_id INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_tanggal (tanggal),
                    KEY idx_rekening (rekening_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        self::$ready = true;
    }

    /** Akun lawan yang boleh dipilih per jenis (hanya yang aktif). */
    public static function akunPilihan(): array
    {
        $out = ['BIAYA' => [], 'MODAL' => [], 'PRIVE' => []];
        foreach (CoaService::accounts() as $kode => $a) {
            if (!$a[7]) continue;
            $item = ['kode' => (string)$kode, 'nama' => $a[0], 'depth' => $a[9]];
            if ($a[6] === 'EXPENSE') $out['BIAYA'][] = $item;
            elseif ($a[6] === 'EQUITY' && $a[2] === 'Credit') $out['MODAL'][] = $item;
            elseif ($a[6] === 'EQUITY' && $a[2] === 'Debit') $out['PRIVE'][] = $item;
        }
        return $out;
    }

    /** Catat satu transaksi dan jurnalnya. Mengembalikan baris yang tersimpan. */
    public static function catat(array $d, int $userId): array
    {
        self::ensureTable();
        \App\Models\RekeningBank::ensureTable();
        $db = Database::getConnection();

        $jenis = (string)($d['jenis'] ?? '');
        if (!isset(self::JENIS[$jenis])) throw new Exception('Jenis transaksi tidak valid.');
        $tanggal = (string)($d['tanggal'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || strtotime($tanggal) === false) throw new Exception('Tanggal tidak valid.');
        if ($tanggal > date('Y-m-d')) throw new Exception('Tanggal tidak boleh di masa depan.');
        PeriodeService::pastikanTerbuka($tanggal, 'mencatat transaksi kas');
        $nominal = round((float)($d['nominal'] ?? 0), 2);
        if ($nominal <= 0) throw new Exception('Nominal harus lebih dari 0.');
        $ket = trim((string)($d['keterangan'] ?? ''));
        if (mb_strlen($ket) < 3) throw new Exception('Keterangan wajib diisi.');

        $kode = (string)($d['kode_akun'] ?? '');
        $valid = array_column(self::akunPilihan()[$jenis], 'kode');
        if (!in_array($kode, $valid, true)) throw new Exception('Akun tidak sesuai dengan jenis transaksi.');

        $metode = ($d['metode'] ?? '') === 'TRANSFER' ? 'TRANSFER' : 'TUNAI';
        $rek = (int)($d['rekening_id'] ?? 0) ?: null;
        if ($metode === 'TRANSFER' && !$rek) throw new Exception('Pilih rekening bank untuk transaksi transfer.');
        if ($rek) {
            $r = \App\Models\RekeningBank::find($rek);
            if (!$r || !$r['is_active']) throw new Exception('Rekening bank tidak ditemukan atau nonaktif.');
        }
        $ref = trim((string)($d['no_referensi'] ?? '')) ?: null;

        JurnalService::ensureTables();
        $db->beginTransaction();
        try {
            $no = KodeGenerator::next('kas_transaksi', 'no_bukti', 'KAS-' . date('Ymd', strtotime($tanggal)), 3, '-');
            $db->prepare("INSERT INTO kas_transaksi (no_bukti, tanggal, jenis, kode_akun, nominal, metode, rekening_id, no_referensi, keterangan, user_id) VALUES (?,?,?,?,?,?,?,?,?,?)")
               ->execute([$no, $tanggal, $jenis, $kode, $nominal, $metode, $rek, $ref, $ket, $userId ?: null]);
            $id = (int)$db->lastInsertId();

            // BIAYA & PRIVE: kas keluar. MODAL: kas masuk.
            $baris = self::JENIS[$jenis][1] === 'masuk'
                ? [[self::KAS, $nominal, 0], [$kode, 0, $nominal]]
                : [[$kode, $nominal, 0], [self::KAS, 0, $nominal]];
            JurnalService::post($tanggal, 'KAS', $id, $no, self::JENIS[$jenis][0] . ': ' . $ket, $baris);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        return ['id' => $id, 'no_bukti' => $no, 'nominal' => $nominal, 'jenis' => $jenis];
    }

    /** Daftar transaksi. $f: dari, sampai, jenis, status. */
    public static function daftar(array $f = []): array
    {
        self::ensureTable();
        \App\Models\RekeningBank::ensureTable();
        $w = ['1=1'];
        $p = [];
        if (!empty($f['dari'])) { $w[] = 'k.tanggal >= ?'; $p[] = $f['dari']; }
        if (!empty($f['sampai'])) { $w[] = 'k.tanggal <= ?'; $p[] = $f['sampai']; }
        if (!empty($f['jenis']) && isset(self::JENIS[$f['jenis']])) { $w[] = 'k.jenis = ?'; $p[] = $f['jenis']; }
        if (!empty($f['status'])) { $w[] = 'k.status = ?'; $p[] = $f['status']; }
        $st = Database::getConnection()->prepare("
            SELECT k.*, c.nama AS nama_akun, r.nama_bank, r.no_rekening, u.nama_lengkap AS petugas
            FROM kas_transaksi k
            LEFT JOIN coa_akun c ON c.kode = k.kode_akun
            LEFT JOIN rekening_bank r ON r.id = k.rekening_id
            LEFT JOIN users u ON u.id = k.user_id
            WHERE " . implode(' AND ', $w) . "
            ORDER BY k.tanggal DESC, k.id DESC
        ");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Total masuk / keluar dari transaksi aktif pada daftar. */
    public static function ringkas(array $rows): array
    {
        $masuk = $keluar = 0.0;
        foreach ($rows as $r) {
            if ($r['status'] !== 'AKTIF') continue;
            if (self::JENIS[$r['jenis']][1] === 'masuk') $masuk += (float)$r['nominal'];
            else $keluar += (float)$r['nominal'];
        }
        return ['masuk' => $masuk, 'keluar' => $keluar, 'bersih' => $masuk - $keluar];
    }

    /** Saldo akun Kas & Bank menurut jurnal (semua dokumen, bukan hanya transaksi kas). */
    public static function saldoJurnal(): float
    {
        $t = JurnalService::totalsPerAkun()[self::KAS] ?? [0.0, 0.0];
        return (float)$t[0] - (float)$t[1];
    }
}
