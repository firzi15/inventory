<?php
namespace App\Services;

use App\Core\Database;
use Exception;
use PDO;

/**
 * Tutup periode (per bulan). Periode yang sudah ditutup tidak menerima jurnal baru dan dokumen yang
 * tanggalnya jatuh di bulan itu tidak bisa dibatalkan, sehingga laporan bulan itu tidak berubah lagi.
 * Hanya bulan yang sudah lewat yang bisa ditutup. Membuka kembali hanya oleh Admin, dengan alasan dan jejak audit.
 */
class PeriodeService
{
    private const BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    private static bool $ready = false;
    private static ?array $tutupCache = null;

    public static function ensureTable(): void
    {
        if (self::$ready) return;
        $db = Database::getConnection();
        if (!$db->query("SHOW TABLES LIKE 'periode_tutup'")->fetchColumn()) {
            if ($db->inTransaction()) return;
            $db->exec("
                CREATE TABLE IF NOT EXISTS periode_tutup (
                    periode CHAR(7) NOT NULL PRIMARY KEY,
                    status ENUM('TUTUP','BUKA') NOT NULL DEFAULT 'TUTUP',
                    catatan VARCHAR(255) DEFAULT NULL,
                    snapshot TEXT DEFAULT NULL,
                    ditutup_oleh INT DEFAULT NULL,
                    ditutup_at DATETIME DEFAULT NULL,
                    dibuka_oleh INT DEFAULT NULL,
                    dibuka_at DATETIME DEFAULT NULL,
                    alasan_buka VARCHAR(255) DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        self::$ready = true;
    }

    public static function namaPeriode(string $periode): string
    {
        [$y, $m] = explode('-', $periode);
        return self::BULAN[(int)$m] . ' ' . $y;
    }

    public static function rentang(string $periode): array
    {
        $dari = $periode . '-01';
        return [$dari, date('Y-m-t', strtotime($dari))];
    }

    private static function tutupSet(): array
    {
        if (self::$tutupCache !== null) return self::$tutupCache;
        self::ensureTable();
        $db = Database::getConnection();
        if (!$db->query("SHOW TABLES LIKE 'periode_tutup'")->fetchColumn()) return [];
        self::$tutupCache = array_flip($db->query("SELECT periode FROM periode_tutup WHERE status = 'TUTUP'")->fetchAll(PDO::FETCH_COLUMN));
        return self::$tutupCache;
    }

    public static function isTutup(string $tanggal): bool
    {
        return isset(self::tutupSet()[substr($tanggal, 0, 7)]);
    }

    /** Lempar Exception bila tanggal jatuh di periode yang sudah ditutup. */
    public static function pastikanTerbuka(string $tanggal, string $aksi = 'mengubah data'): void
    {
        if ($tanggal !== '' && self::isTutup($tanggal)) {
            $p = substr($tanggal, 0, 7);
            throw new Exception('Periode ' . self::namaPeriode($p) . " sudah ditutup, jadi tidak dapat {$aksi}. Admin dapat membukanya kembali di menu Tutup Periode.");
        }
    }

    /** Pemeriksaan sebelum menutup. Tiap item: [level (blok|peringatan|ok), teks]. */
    public static function cek(string $periode): array
    {
        [$dari, $sampai] = self::rentang($periode);
        $db = Database::getConnection();
        $hasil = [];

        $st = $db->prepare("SELECT COALESCE(SUM(b.debit), 0) d, COALESCE(SUM(b.kredit), 0) k FROM jurnal_baris b JOIN jurnal j ON j.id = b.jurnal_id WHERE j.tanggal BETWEEN ? AND ?");
        $st->execute([$dari, $sampai]);
        $t = $st->fetch(PDO::FETCH_ASSOC);
        if (abs((float)$t['d'] - (float)$t['k']) > 0.01) {
            $hasil[] = ['blok', 'Jurnal bulan ini tidak seimbang (debit ' . number_format($t['d'], 0, ',', '.') . ' vs kredit ' . number_format($t['k'], 0, ',', '.') . '). Periksa sebelum menutup.'];
        } else {
            $hasil[] = ['ok', 'Jurnal bulan ini seimbang.'];
        }

        if ($sampai >= date('Y-m-d')) {
            $hasil[] = ['blok', 'Bulan ini belum berakhir. Periode baru bisa ditutup setelah tanggal ' . date('d/m/Y', strtotime($sampai)) . '.'];
        }

        $hari = \App\Models\RekonsiliasiBank::daftarHari($dari, $sampai);
        $belum = count(array_filter($hari, fn($h) => $h['status_hari'] === 'BELUM'));
        $bermasalah = count(array_filter($hari, fn($h) => in_array($h['status_hari'], ['SELISIH', 'BERUBAH'], true)));
        if ($belum > 0) $hasil[] = ['peringatan', "{$belum} hari yang ada setoran belum direkonsiliasi dengan bank."];
        if ($bermasalah > 0) $hasil[] = ['peringatan', "{$bermasalah} hari rekonsiliasi berstatus selisih atau setoran berubah."];
        if ($belum === 0 && $bermasalah === 0) $hasil[] = ['ok', 'Semua hari bersetoran sudah direkonsiliasi dan sesuai.'];

        $per = StockService::rekonsiliasiPersediaan();
        if (!$per['cocok']) $hasil[] = ['peringatan', 'Nilai barang di gudang berbeda dari saldo Inventory di jurnal sebesar Rp ' . number_format(abs($per['selisih']), 0, ',', '.') . '.'];
        else $hasil[] = ['ok', 'Nilai persediaan cocok dengan jurnal.'];

        $sebelum = $db->prepare("SELECT MIN(tanggal) FROM jurnal");
        $sebelum->execute();
        $awal = $sebelum->fetchColumn();
        if ($awal) {
            for ($p = substr($awal, 0, 7); $p < $periode; $p = date('Y-m', strtotime($p . '-01 +1 month'))) {
                if (!isset(self::tutupSet()[$p])) {
                    $hasil[] = ['peringatan', 'Periode ' . self::namaPeriode($p) . ' belum ditutup. Sebaiknya tutup berurutan.'];
                    break;
                }
            }
        }
        return $hasil;
    }

    /** Angka ringkas satu bulan dari jurnal. */
    public static function ringkas(string $periode): array
    {
        [$dari, $sampai] = self::rentang($periode);
        $l = JurnalService::labaRugiRentang($dari, $sampai);
        $pen = (float)($l['4000'] ?? 0);
        $hpp = (float)($l['5000'] ?? 0);
        $biaya = (float)($l['6000'] ?? 0);
        return ['penjualan' => $pen, 'hpp' => $hpp, 'biaya' => $biaya, 'laba' => $pen - $hpp - $biaya];
    }

    /** Saldo akun neraca sampai akhir bulan. */
    private static function saldoAkhir(string $sampai): array
    {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT b.kode_akun, SUM(b.debit) d, SUM(b.kredit) k FROM jurnal_baris b JOIN jurnal j ON j.id = b.jurnal_id WHERE j.tanggal <= ? AND b.kode_akun IN ('1000','1100','1200','2000') GROUP BY b.kode_akun");
        $st->execute([$sampai]);
        $o = ['1000' => 0.0, '1100' => 0.0, '1200' => 0.0, '2000' => 0.0];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $o[$r['kode_akun']] = $r['kode_akun'] === '2000' ? (float)$r['k'] - (float)$r['d'] : (float)$r['d'] - (float)$r['k'];
        }
        return ['kas_bank' => $o['1000'], 'piutang' => $o['1100'], 'persediaan' => $o['1200'], 'utang' => $o['2000']];
    }

    /** Daftar bulan dari bulan jurnal pertama sampai bulan berjalan. */
    public static function daftar(): array
    {
        self::ensureTable();
        $db = Database::getConnection();
        $awal = $db->query("SELECT MIN(tanggal) FROM jurnal")->fetchColumn();
        $status = [];
        foreach ($db->query("SELECT * FROM periode_tutup")->fetchAll(PDO::FETCH_ASSOC) as $r) $status[$r['periode']] = $r;

        $out = [];
        $akhir = date('Y-m');
        for ($p = $awal ? substr($awal, 0, 7) : $akhir; $p <= $akhir; $p = date('Y-m', strtotime($p . '-01 +1 month'))) {
            $s = $status[$p] ?? null;
            [, $sampai] = self::rentang($p);
            $ringkas = ($s && $s['status'] === 'TUTUP' && $s['snapshot']) ? (json_decode($s['snapshot'], true)['ringkas'] ?? self::ringkas($p)) : self::ringkas($p);
            $out[] = [
                'periode'  => $p,
                'nama'     => self::namaPeriode($p),
                'status'   => $s['status'] ?? 'TERBUKA',
                'bisa_tutup' => $sampai < date('Y-m-d'),
                'ringkas'  => $ringkas,
                'baris'    => $s,
            ];
        }
        return array_reverse($out);
    }

    public static function tutup(string $periode, string $catatan, int $userId): string
    {
        self::ensureTable();
        if (!preg_match('/^\d{4}-\d{2}$/', $periode)) throw new Exception('Periode tidak valid.');
        if (self::isTutup($periode . '-01')) throw new Exception('Periode ' . self::namaPeriode($periode) . ' sudah ditutup.');
        foreach (self::cek($periode) as [$lv, $teks]) {
            if ($lv === 'blok') throw new Exception($teks);
        }
        [, $sampai] = self::rentang($periode);
        $snap = json_encode(['ringkas' => self::ringkas($periode), 'saldo_akhir' => self::saldoAkhir($sampai)]);
        $db = Database::getConnection();
        $db->prepare("INSERT INTO periode_tutup (periode, status, catatan, snapshot, ditutup_oleh, ditutup_at)
                      VALUES (?, 'TUTUP', ?, ?, ?, NOW())
                      ON DUPLICATE KEY UPDATE status = 'TUTUP', catatan = VALUES(catatan), snapshot = VALUES(snapshot),
                          ditutup_oleh = VALUES(ditutup_oleh), ditutup_at = NOW()")
           ->execute([$periode, trim($catatan) ?: null, $snap, $userId ?: null]);
        self::$tutupCache = null;
        self::audit($userId, 'TUTUP_PERIODE', $periode, trim($catatan) ?: 'Periode ditutup');
        return 'Periode ' . self::namaPeriode($periode) . ' ditutup.';
    }

    public static function buka(string $periode, string $alasan, int $userId): string
    {
        self::ensureTable();
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 3) throw new Exception('Alasan membuka kembali wajib diisi.');
        if (!self::isTutup($periode . '-01')) throw new Exception('Periode ini tidak dalam keadaan tertutup.');
        Database::getConnection()->prepare("UPDATE periode_tutup SET status = 'BUKA', dibuka_oleh = ?, dibuka_at = NOW(), alasan_buka = ? WHERE periode = ?")
            ->execute([$userId ?: null, mb_substr($alasan, 0, 255), $periode]);
        self::$tutupCache = null;
        self::audit($userId, 'BUKA_PERIODE', $periode, $alasan);
        return 'Periode ' . self::namaPeriode($periode) . ' dibuka kembali.';
    }

    private static function audit(int $userId, string $aksi, string $periode, string $alasan): void
    {
        Database::getConnection()->prepare("INSERT INTO audit_logs (user_id, action_name, target_table, target_id, reason, ip_address) VALUES (?,?,?,?,?,?)")
            ->execute([$userId ?: null, $aksi, 'periode_tutup', $periode, mb_substr($alasan, 0, 255), $_SERVER['REMOTE_ADDR'] ?? 'cli']);
    }
}
