<?php
namespace App\Models;

use App\Core\Database;
use App\Core\KodeGenerator;
use Exception;
use PDO;

/**
 * Master rekening bank perusahaan. Dipakai sebagai keterangan pada Transaksi Kas & Bank
 * dan Rekonsiliasi Bank. Kode dibuat otomatis (RB-01, RB-02, ...).
 */
class RekeningBank
{
    private static bool $ready = false;

    public static function ensureTable(): void
    {
        if (self::$ready) return;
        $db = Database::getConnection();
        if (!$db->query("SHOW TABLES LIKE 'rekening_bank'")->fetchColumn()) {
            if ($db->inTransaction()) throw new Exception('Tabel rekening bank belum ada. Muat ulang halaman.');
            $db->exec("
                CREATE TABLE IF NOT EXISTS rekening_bank (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    kode_rekening VARCHAR(20) NOT NULL UNIQUE,
                    nama_bank VARCHAR(100) NOT NULL,
                    no_rekening VARCHAR(50) NOT NULL,
                    atas_nama VARCHAR(150) DEFAULT NULL,
                    keterangan VARCHAR(255) DEFAULT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        self::$ready = true;
    }

    public static function getAll(): array
    {
        self::ensureTable();
        return Database::getConnection()->query("SELECT * FROM rekening_bank ORDER BY kode_rekening")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAktif(): array
    {
        self::ensureTable();
        return Database::getConnection()->query("SELECT * FROM rekening_bank WHERE is_active = 1 ORDER BY kode_rekening")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        self::ensureTable();
        $st = Database::getConnection()->prepare("SELECT * FROM rekening_bank WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Teks ringkas untuk ditampilkan, mis. "BCA 123456 a.n PT Contoh". */
    public static function label(array $r): string
    {
        return trim($r['nama_bank'] . ' ' . $r['no_rekening']) . ($r['atas_nama'] ? ' a.n ' . $r['atas_nama'] : '');
    }

    public static function simpan(array $d): string
    {
        self::ensureTable();
        $db = Database::getConnection();
        $id = (int)($d['id'] ?? 0);
        $bank = trim((string)($d['nama_bank'] ?? ''));
        $no = trim((string)($d['no_rekening'] ?? ''));
        if ($bank === '') throw new Exception('Nama bank wajib diisi.');
        if ($no === '') throw new Exception('Nomor rekening wajib diisi.');
        $atas = trim((string)($d['atas_nama'] ?? '')) ?: null;
        $ket = trim((string)($d['keterangan'] ?? '')) ?: null;

        $dup = $db->prepare("SELECT id FROM rekening_bank WHERE nama_bank = ? AND no_rekening = ? AND id <> ?");
        $dup->execute([$bank, $no, $id]);
        if ($dup->fetchColumn()) throw new Exception("Rekening {$bank} {$no} sudah terdaftar.");

        if ($id > 0) {
            if (!self::find($id)) throw new Exception('Rekening tidak ditemukan.');
            $db->prepare("UPDATE rekening_bank SET nama_bank = ?, no_rekening = ?, atas_nama = ?, keterangan = ? WHERE id = ?")
               ->execute([$bank, $no, $atas, $ket, $id]);
            return "Rekening {$bank} {$no} diperbarui.";
        }
        $kode = KodeGenerator::next('rekening_bank', 'kode_rekening', 'RB', 2);
        $db->prepare("INSERT INTO rekening_bank (kode_rekening, nama_bank, no_rekening, atas_nama, keterangan) VALUES (?,?,?,?,?)")
           ->execute([$kode, $bank, $no, $atas, $ket]);
        return "Rekening {$kode} ({$bank} {$no}) ditambahkan.";
    }

    public static function ubahStatus(int $id): string
    {
        $r = self::find($id);
        if (!$r) throw new Exception('Rekening tidak ditemukan.');
        $baru = $r['is_active'] ? 0 : 1;
        Database::getConnection()->prepare("UPDATE rekening_bank SET is_active = ? WHERE id = ?")->execute([$baru, $id]);
        return "Rekening {$r['kode_rekening']} " . ($baru ? 'diaktifkan.' : 'dinonaktifkan.');
    }

    public static function pemakaian(int $id): int
    {
        \App\Services\KasService::ensureTable();
        $st = Database::getConnection()->prepare("SELECT COUNT(*) FROM kas_transaksi WHERE rekening_id = ?");
        $st->execute([$id]);
        return (int)$st->fetchColumn();
    }

    public static function hapus(int $id): string
    {
        $r = self::find($id);
        if (!$r) throw new Exception('Rekening tidak ditemukan.');
        $n = self::pemakaian($id);
        if ($n > 0) throw new Exception("Rekening {$r['kode_rekening']} sudah dipakai pada {$n} transaksi kas. Nonaktifkan saja bila tidak dipakai lagi.");
        Database::getConnection()->prepare("DELETE FROM rekening_bank WHERE id = ?")->execute([$id]);
        return "Rekening {$r['kode_rekening']} dihapus.";
    }
}
