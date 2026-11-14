<?php
namespace App\Services;

use App\Core\Database;
use Exception;
use PDO;

/**
 * Tutup harian. Hari yang sudah ditutup tidak menerima jurnal baru, dan dokumen yang tanggalnya jatuh di hari itu
 * tidak bisa dibatalkan, sehingga angka hari itu tidak berubah lagi (mis. ketika harga master berubah besoknya).
 * Hanya hari yang sudah lewat yang bisa ditutup. Membuka kembali hanya oleh Admin, dengan alasan dan jejak audit.
 * Nama kelas dipertahankan (PeriodeService) agar semua titik penguncian yang sudah ada tetap berfungsi.
 */
class PeriodeService
{
    private const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    private const BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    private static bool $ready = false;
    private static ?array $tutupCache = null;

    public static function ensureTable(): void
    {
        if (self::$ready) return;
        $db = Database::getConnection();
        if (!$db->query("SHOW TABLES LIKE 'tutup_harian'")->fetchColumn()) {
            if ($db->inTransaction()) return;
            $db->exec("
                CREATE TABLE IF NOT EXISTS tutup_harian (
                    tanggal DATE NOT NULL PRIMARY KEY,
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
            // Penutupan bulanan versi lama (periode_tutup) dipindahkan menjadi penutupan per hari
            if ($db->query("SHOW TABLES LIKE 'periode_tutup'")->fetchColumn()) {
                foreach ($db->query("SELECT * FROM periode_tutup WHERE status = 'TUTUP'")->fetchAll(PDO::FETCH_ASSOC) as $m) {
                    $awal = $m['periode'] . '-01';
                    $akhir = date('Y-m-t', strtotime($awal));
                    $ins = $db->prepare("INSERT IGNORE INTO tutup_harian (tanggal, status, catatan, snapshot, ditutup_oleh, ditutup_at) VALUES (?, 'TUTUP', ?, NULL, ?, ?)");
                    for ($d = $awal; $d <= $akhir; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
                        $ins->execute([$d, 'Dipindahkan dari penutupan bulanan ' . $m['periode'], $m['ditutup_oleh'], $m['ditutup_at']]);
                    }
                }
            }
        }
        self::$ready = true;
    }

    public static function namaTanggal(string $tanggal): string
    {
        $t = strtotime($tanggal);
        return self::HARI[(int)date('w', $t)] . ', ' . date('d/m/Y', $t);
    }

    public static function namaBulan(string $bulan): string
    {
        [$y, $m] = explode('-', $bulan);
        return self::BULAN[(int)$m] . ' ' . $y;
    }

    private static function tutupSet(): array
    {
        if (self::$tutupCache !== null) return self::$tutupCache;
        self::ensureTable();
        $db = Database::getConnection();
        if (!$db->query("SHOW TABLES LIKE 'tutup_harian'")->fetchColumn()) return [];
        self::$tutupCache = array_flip($db->query("SELECT tanggal FROM tutup_harian WHERE status = 'TUTUP'")->fetchAll(PDO::FETCH_COLUMN));
        return self::$tutupCache;
    }

    public static function isTutup(string $tanggal): bool
    {
        return isset(self::tutupSet()[substr($tanggal, 0, 10)]);
    }

    /** Lempar Exception bila tanggal jatuh di hari yang sudah ditutup. */
    public static function pastikanTerbuka(string $tanggal, string $aksi = 'mengubah data'): void
    {
        if ($tanggal !== '' && self::isTutup($tanggal)) {
            throw new Exception('Tanggal ' . date('d/m/Y', strtotime($tanggal)) . " sudah ditutup, jadi tidak dapat {$aksi}. Admin dapat membukanya kembali di menu Tutup Harian.");
        }
    }

    private static function validTanggal(string $tanggal): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || strtotime($tanggal) === false) throw new Exception('Tanggal tidak valid.');
    }

    /** Tanggal jurnal paling awal (batas bawah daftar hari). */
    public static function tanggalAwal(): ?string
    {
        self::ensureTable();
        $a = Database::getConnection()->query("SELECT MIN(tanggal) FROM jurnal")->fetchColumn();
        return $a ?: null;
    }

    /**
     * Pemeriksaan sebelum menutup satu hari. Tiap item: [level (blok|peringatan|ok), teks].
     * $persediaan: hasil StockService::rekonsiliasiPersediaan() bila sudah dihitung (dipakai ulang).
     */
    public static function cek(string $tanggal, ?array $persediaan = null): array
    {
        self::validTanggal($tanggal);
        $db = Database::getConnection();
        $hasil = [];

        $st = $db->prepare("SELECT COALESCE(SUM(b.debit), 0) d, COALESCE(SUM(b.kredit), 0) k FROM jurnal_baris b JOIN jurnal j ON j.id = b.jurnal_id WHERE j.tanggal = ?");
        $st->execute([$tanggal]);
        $t = $st->fetch(PDO::FETCH_ASSOC);
        if (abs((float)$t['d'] - (float)$t['k']) > 0.01) {
            $hasil[] = ['blok', 'Jurnal hari ini tidak seimbang (debit ' . number_format($t['d'], 0, ',', '.') . ' vs kredit ' . number_format($t['k'], 0, ',', '.') . '). Periksa sebelum menutup.'];
        } else {
            $hasil[] = ['ok', 'Jurnal hari ini seimbang.'];
        }

        if ($tanggal >= date('Y-m-d')) {
            $hasil[] = ['blok', 'Hari ini belum berakhir. Tanggal ini baru bisa ditutup mulai besok.'];
        }

        $hari = \App\Models\RekonsiliasiBank::daftarHari($tanggal, $tanggal);
        if (!$hari) {
            $hasil[] = ['ok', 'Tidak ada setoran mitra pada hari ini.'];
        } else {
            $s = $hari[0]['status_hari'];
            if ($s === 'SESUAI') $hasil[] = ['ok', 'Rekonsiliasi bank hari ini sesuai.'];
            elseif ($s === 'BELUM') $hasil[] = ['peringatan', 'Setoran hari ini belum direkonsiliasi dengan bank.'];
            else $hasil[] = ['peringatan', $s === 'SELISIH' ? 'Rekonsiliasi bank hari ini berstatus selisih.' : 'Setoran berubah setelah direkonsiliasi; audit ulang.'];
        }

        $per = $persediaan ?? StockService::rekonsiliasiPersediaan();
        if (!$per['cocok']) $hasil[] = ['peringatan', 'Nilai barang di gudang berbeda dari saldo Inventory di jurnal sebesar Rp ' . number_format(abs($per['selisih']), 0, ',', '.') . '.'];
        else $hasil[] = ['ok', 'Nilai persediaan cocok dengan jurnal.'];

        $awal = self::tanggalAwal();
        if ($awal && $awal < $tanggal) {
            $belum = 0;
            for ($d = $awal; $d < $tanggal && $belum < 1; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
                if (!isset(self::tutupSet()[$d])) $belum++;
            }
            if ($belum > 0) $hasil[] = ['peringatan', 'Ada hari sebelumnya yang belum ditutup. Sebaiknya tutup berurutan, atau gunakan Tutup semua sampai tanggal ini.'];
        }
        return $hasil;
    }

    /** Angka ringkas satu hari dari jurnal. */
    public static function ringkas(string $tanggal): array
    {
        $l = JurnalService::labaRugiRentang($tanggal, $tanggal);
        $pen = (float)($l['4000'] ?? 0);
        $hpp = (float)($l['5000'] ?? 0);
        $biaya = (float)($l['6000'] ?? 0);
        return ['penjualan' => $pen, 'hpp' => $hpp, 'biaya' => $biaya, 'laba' => $pen - $hpp - $biaya];
    }

    /** Saldo akun neraca sampai akhir hari itu. */
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

    /** Bulan-bulan yang punya hari untuk ditampilkan (dari jurnal pertama sampai bulan berjalan), terbaru dulu. */
    public static function daftarBulan(): array
    {
        $awal = self::tanggalAwal();
        $akhir = date('Y-m');
        $out = [];
        for ($b = $awal ? substr($awal, 0, 7) : $akhir; $b <= $akhir; $b = date('Y-m', strtotime($b . '-01 +1 month'))) $out[] = $b;
        return array_reverse($out);
    }

    /** Daftar hari dalam satu bulan (sampai hari ini), terbaru dulu. */
    public static function daftarHari(string $bulan): array
    {
        self::ensureTable();
        $db = Database::getConnection();
        $awal = $bulan . '-01';
        $akhir = min(date('Y-m-t', strtotime($awal)), date('Y-m-d'));
        $lower = self::tanggalAwal();
        $st = $db->prepare("SELECT * FROM tutup_harian WHERE tanggal BETWEEN ? AND ?");
        $st->execute([$awal, $akhir]);
        $status = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $status[$r['tanggal']] = $r;
        $jml = $db->prepare("SELECT tanggal, COUNT(*) n FROM jurnal WHERE tanggal BETWEEN ? AND ? GROUP BY tanggal");
        $jml->execute([$awal, $akhir]);
        $aktif = $jml->fetchAll(PDO::FETCH_KEY_PAIR);

        $out = [];
        for ($d = $awal; $d <= $akhir; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            if ($lower && $d < $lower) continue;
            $s = $status[$d] ?? null;
            $tutup = $s && $s['status'] === 'TUTUP';
            $ringkas = ($tutup && $s['snapshot']) ? (json_decode($s['snapshot'], true)['ringkas'] ?? self::ringkas($d)) : self::ringkas($d);
            $out[] = [
                'tanggal'  => $d,
                'nama'     => self::namaTanggal($d),
                'status'   => $s['status'] ?? 'TERBUKA',
                'bisa_tutup' => $d < date('Y-m-d'),
                'transaksi' => (int)($aktif[$d] ?? 0),
                'ringkas'  => $ringkas,
                'baris'    => $s,
            ];
        }
        return array_reverse($out);
    }

    private static function simpanTutup(string $tanggal, string $catatan, int $userId): void
    {
        $snap = json_encode(['ringkas' => self::ringkas($tanggal), 'saldo_akhir' => self::saldoAkhir($tanggal)]);
        Database::getConnection()->prepare("INSERT INTO tutup_harian (tanggal, status, catatan, snapshot, ditutup_oleh, ditutup_at)
                      VALUES (?, 'TUTUP', ?, ?, ?, NOW())
                      ON DUPLICATE KEY UPDATE status = 'TUTUP', catatan = VALUES(catatan), snapshot = VALUES(snapshot),
                          ditutup_oleh = VALUES(ditutup_oleh), ditutup_at = NOW()")
           ->execute([$tanggal, trim($catatan) ?: null, $snap, $userId ?: null]);
    }

    public static function tutup(string $tanggal, string $catatan, int $userId): string
    {
        self::ensureTable();
        self::validTanggal($tanggal);
        if (self::isTutup($tanggal)) throw new Exception('Tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' sudah ditutup.');
        foreach (self::cek($tanggal) as [$lv, $teks]) {
            if ($lv === 'blok') throw new Exception($teks);
        }
        self::simpanTutup($tanggal, $catatan, $userId);
        self::$tutupCache = null;
        self::audit($userId, 'TUTUP_HARIAN', $tanggal, trim($catatan) ?: 'Hari ditutup');
        return 'Tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' ditutup.';
    }

    /** Tutup semua hari yang masih terbuka dari jurnal pertama sampai $sampai. Hari yang jurnalnya tidak seimbang dilewati. */
    public static function tutupSampai(string $sampai, string $catatan, int $userId): string
    {
        self::ensureTable();
        self::validTanggal($sampai);
        if ($sampai >= date('Y-m-d')) throw new Exception('Hanya hari yang sudah lewat yang bisa ditutup. Pilih tanggal sampai kemarin.');
        $awal = self::tanggalAwal();
        if (!$awal || $awal > $sampai) throw new Exception('Tidak ada hari yang perlu ditutup.');

        $db = Database::getConnection();
        $st = $db->prepare("SELECT j.tanggal, ABS(SUM(b.debit) - SUM(b.kredit)) selisih FROM jurnal j JOIN jurnal_baris b ON b.jurnal_id = j.id WHERE j.tanggal BETWEEN ? AND ? GROUP BY j.tanggal");
        $st->execute([$awal, $sampai]);
        $tidakSeimbang = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) if ((float)$r['selisih'] > 0.01) $tidakSeimbang[$r['tanggal']] = true;

        $tutup = 0; $lewati = [];
        for ($d = $awal; $d <= $sampai; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            if (self::isTutup($d)) continue;
            if (isset($tidakSeimbang[$d])) { $lewati[] = date('d/m/Y', strtotime($d)); continue; }
            self::simpanTutup($d, $catatan, $userId);
            self::$tutupCache = null;
            $tutup++;
        }
        self::audit($userId, 'TUTUP_HARIAN', $sampai, "Tutup massal sampai {$sampai}: {$tutup} hari" . ($catatan !== '' ? ' - ' . trim($catatan) : ''));
        $pesan = "{$tutup} hari ditutup sampai " . date('d/m/Y', strtotime($sampai)) . '.';
        if ($lewati) $pesan .= ' Dilewati karena jurnalnya tidak seimbang: ' . implode(', ', $lewati) . '.';
        return $pesan;
    }

    public static function buka(string $tanggal, string $alasan, int $userId): string
    {
        self::ensureTable();
        self::validTanggal($tanggal);
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 3) throw new Exception('Alasan membuka kembali wajib diisi.');
        if (!self::isTutup($tanggal)) throw new Exception('Tanggal ini tidak dalam keadaan tertutup.');
        Database::getConnection()->prepare("UPDATE tutup_harian SET status = 'BUKA', dibuka_oleh = ?, dibuka_at = NOW(), alasan_buka = ? WHERE tanggal = ?")
            ->execute([$userId ?: null, mb_substr($alasan, 0, 255), $tanggal]);
        self::$tutupCache = null;
        self::audit($userId, 'BUKA_HARIAN', $tanggal, $alasan);
        return 'Tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' dibuka kembali.';
    }

    private static function audit(int $userId, string $aksi, string $tanggal, string $alasan): void
    {
        Database::getConnection()->prepare("INSERT INTO audit_logs (user_id, action_name, target_table, target_id, reason, ip_address) VALUES (?,?,?,?,?,?)")
            ->execute([$userId ?: null, $aksi, 'tutup_harian', $tanggal, mb_substr($alasan, 0, 255), $_SERVER['REMOTE_ADDR'] ?? 'cli']);
    }
}
