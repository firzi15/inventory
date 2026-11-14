<?php
/**
 * Backup data.
 * Isi satu backup: dump database (semua tabel, termasuk jurnal dan log audit), folder assets/uploads
 * (screenshot dan slip bukti setoran), serta berkas konfigurasi .env dan akses Accurate (folder config/ di dalam arsip).
 * Kode program tidak ikut karena sudah ada di git. Karena berisi kata sandi dan token, simpan backup di tempat yang aman.
 *
 * Hasil: satu berkas backup_YYYYMMDD_HHMMSS[_auto|_sebelumpulih].zip (atau .tar.gz bila ekstensi zip PHP belum aktif)
 * di folder backup di luar folder web (bawaan: sejajar dengan folder htdocs, atau env BACKUP_DIR).
 *
 * Jenis: manual (tombol), auto (otomatis sekali tiap bulan), sebelumpulih (dibuat otomatis sebelum memulihkan).
 */

namespace App\Services;

use App\Config\Config;
use App\Core\Database;

class BackupService
{
    /** Jumlah backup yang disimpan per jenis; yang lebih lama dihapus otomatis. */
    public const SIMPAN = ['manual' => 30, 'auto' => 24, 'sebelumpulih' => 10];
    public const SIMPAN_TERAKHIR = 30;
    private const POLA = '/^backup_\d{8}_\d{6}(_auto|_sebelumpulih)?\.(zip|tar\.gz)$/';

    /** Peran yang boleh membuat, mengunduh, dan menghapus backup. Sementara semua peran; isi ['ADMIN'] untuk membatasi. */
    public const PERAN_BOLEH = null;
    /** Peran yang boleh memulihkan dan mengunggah backup. Sementara semua peran; isi ['ADMIN'] saat pembagian akses dibuat, karena memulihkan menimpa seluruh data. */
    public const PERAN_PULIH = null;

    public static function boleh(string $role): bool
    {
        return self::PERAN_BOLEH === null || in_array(strtoupper($role), self::PERAN_BOLEH, true);
    }

    public static function bolehPulih(string $role): bool
    {
        return self::PERAN_PULIH === null || in_array(strtoupper($role), self::PERAN_PULIH, true);
    }

    public static function folder(): string
    {
        $env = getenv('BACKUP_DIR');
        $dir = ($env !== false && $env !== '') ? $env : dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'backup_db';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \Exception('Folder backup tidak bisa dibuat: ' . $dir);
        }
        return $dir;
    }

    public static function folderUpload(): string
    {
        $env = getenv('UPLOAD_DIR');
        if ($env !== false && $env !== '') return rtrim($env, '\/');
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads';
    }

    public static function namaValid(string $nama): bool
    {
        return (bool)preg_match(self::POLA, $nama);
    }

    /** 'manual', 'auto', atau 'sebelumpulih' */
    public static function jenis(string $nama): string
    {
        if (strpos($nama, '_auto.') !== false) return 'auto';
        if (strpos($nama, '_sebelumpulih.') !== false) return 'sebelumpulih';
        return 'manual';
    }

    private static function cariKlien(string $nama): string
    {
        $env = getenv($nama === 'mysql' ? 'MYSQL_PATH' : 'MYSQLDUMP_PATH');
        $kandidat = [];
        if ($env !== false && $env !== '') $kandidat[] = $env;
        foreach ([dirname(__DIR__, 4) . '/mysql/bin', 'C:/xampp/mysql/bin', '/usr/bin', '/usr/local/bin', '/opt/lampp/bin'] as $d) {
            $kandidat[] = "$d/$nama.exe";
            $kandidat[] = "$d/$nama";
        }
        foreach ($kandidat as $k) if (is_file($k)) return $k;
        return $nama; // terakhir: cari di PATH
    }

    /** Berkas konfigurasi yang ikut backup: nama di arsip => lokasi di komputer ini. */
    private static function berkasKonfig(): array
    {
        return [
            'config/.env' => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env',
            'config/accurate_config.json' => Config::ACCURATE_CONFIG_FILE,
        ];
    }

    private static function envKlien(): ?array
    {
        return Config::getDbPass() !== '' ? array_merge(getenv() ?: [], ['MYSQL_PWD' => Config::getDbPass()]) : null;
    }

    /** @return array{nama:string,ukuran:int,upload:int,pesan:string} */
    public static function buat(int $userId, string $jenis = 'manual'): array
    {
        if (Database::getDriver() !== 'mysql') throw new \Exception('Backup otomatis hanya untuk database MySQL.');
        if (!function_exists('proc_open')) throw new \Exception('Fungsi proc_open dimatikan di PHP, backup tidak bisa dijalankan.');
        @set_time_limit(300);

        $dir = self::folder();
        $stamp = date('Ymd_His');
        $akhiran = ['manual' => '', 'auto' => '_auto', 'sebelumpulih' => '_sebelumpulih'][$jenis] ?? '';
        $sql = $dir . DIRECTORY_SEPARATOR . "tmp_database_{$stamp}{$akhiran}.sql";

        $cmd = [self::cariKlien('mysqldump'), '--single-transaction', '--routines', '--triggers', '--default-character-set=utf8mb4',
            '--host=' . Config::getDbHost(), '--port=' . Config::getDbPort(), '--user=' . Config::getDbUser(),
            '--result-file=' . $sql, Config::getDbName()];
        $p = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, self::envKlien());
        if (!is_resource($p)) throw new \Exception('mysqldump tidak bisa dijalankan. Isi env MYSQLDUMP_PATH dengan lokasi mysqldump.');
        stream_get_contents($pipes[1]);
        $err = trim((string)stream_get_contents($pipes[2]));
        fclose($pipes[1]); fclose($pipes[2]);
        $kode = proc_close($p);

        $isi = is_file($sql) ? (string)file_get_contents($sql, false, null, max(0, filesize($sql) - 200)) : '';
        if ($kode !== 0 || !is_file($sql) || stripos($isi, 'Dump completed') === false) {
            @unlink($sql);
            throw new \Exception('Dump database gagal' . ($err !== '' ? ': ' . $err : '.'));
        }

        $upload = self::folderUpload();
        $berkas = [];
        if (is_dir($upload)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($upload, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) if ($f->isFile()) $berkas[] = $f->getPathname();
        }
        $konfig = [];
        foreach (self::berkasKonfig() as $namaArsip => $lokasi) if (is_file($lokasi)) $konfig[$namaArsip] = $lokasi;
        $info = "Backup Inventory System\nDibuat: " . date('Y-m-d H:i:s') . "\nJenis: {$jenis}\nDatabase: " . Config::getDbName()
            . "\nBerkas upload: " . count($berkas) . "\nBerkas konfigurasi: " . implode(', ', array_keys($konfig))
            . "\nPERINGATAN: arsip ini berisi kata sandi dan token (config/), simpan di tempat aman."
            . "\nPulihkan: dari menu Pengaturan > Backup, atau impor database.sql ke MySQL dan salin folder uploads/ ke assets/uploads/\n";

        $akar = str_replace('\\', '/', rtrim($upload, '\\/')) . '/';
        try {
            if (class_exists('ZipArchive')) {
                $nama = "backup_{$stamp}{$akhiran}.zip";
                $z = new \ZipArchive();
                if ($z->open($dir . DIRECTORY_SEPARATOR . $nama, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) throw new \Exception('Berkas zip tidak bisa dibuat.');
                $z->addFile($sql, 'database.sql');
                $z->addFromString('info.txt', $info);
                foreach ($berkas as $b) $z->addFile($b, 'uploads/' . substr(str_replace('\\', '/', $b), strlen($akar)));
                foreach ($konfig as $namaArsip => $lokasi) $z->addFile($lokasi, $namaArsip);
                if (!$z->close()) throw new \Exception('Berkas zip gagal ditulis.');
            } else {
                $nama = "backup_{$stamp}{$akhiran}.tar.gz";
                $tar = $dir . DIRECTORY_SEPARATOR . "tmp_backup_{$stamp}{$akhiran}.tar";
                $t = new \PharData($tar);
                $t->addFile($sql, 'database.sql');
                $t->addFromString('info.txt', $info);
                foreach ($berkas as $b) $t->addFile($b, 'uploads/' . substr(str_replace('\\', '/', $b), strlen($akar)));
                foreach ($konfig as $namaArsip => $lokasi) $t->addFile($lokasi, $namaArsip);
                $t->compress(\Phar::GZ, 'tar.gz');
                unset($t);
                $hasil = $dir . DIRECTORY_SEPARATOR . "tmp_backup_{$stamp}{$akhiran}.tar.gz";
                @unlink($tar);
                if (!rename($hasil, $dir . DIRECTORY_SEPARATOR . $nama)) throw new \Exception('Berkas backup gagal disimpan.');
            }
        } finally {
            @unlink($sql);
        }

        $ukuran = (int)filesize($dir . DIRECTORY_SEPARATOR . $nama);
        $label = ['manual' => 'Backup dibuat', 'auto' => 'Backup otomatis bulanan dibuat', 'sebelumpulih' => 'Backup pengaman dibuat sebelum memulihkan'][$jenis] ?? 'Backup dibuat';
        self::audit($userId, 'BACKUP_DATA', $nama, $label . ' (' . self::ukuranTeks($ukuran) . ', ' . count($berkas) . ' berkas upload)');
        self::rapikan($dir);
        return ['nama' => $nama, 'ukuran' => $ukuran, 'upload' => count($berkas), 'pesan' => "Backup {$nama} selesai (" . self::ukuranTeks($ukuran) . ')'];
    }

    /** Daftar backup, terbaru dulu. */
    public static function daftar(): array
    {
        $dir = self::folder();
        $hasil = [];
        foreach (scandir($dir) ?: [] as $f) {
            if (!self::namaValid($f)) continue;
            $path = $dir . DIRECTORY_SEPARATOR . $f;
            $hasil[] = ['nama' => $f, 'ukuran' => (int)filesize($path), 'waktu' => (int)filemtime($path), 'jenis' => self::jenis($f)];
        }
        usort($hasil, fn($a, $b) => strcmp($b['nama'], $a['nama']));
        return $hasil;
    }

    public static function path(string $nama): string
    {
        if (!self::namaValid($nama)) throw new \Exception('Nama berkas backup tidak valid.');
        $p = self::folder() . DIRECTORY_SEPARATOR . $nama;
        if (!is_file($p)) throw new \Exception('Berkas backup tidak ditemukan.');
        return $p;
    }

    public static function hapus(string $nama, int $userId): string
    {
        $p = self::path($nama);
        if (!@unlink($p)) throw new \Exception('Berkas backup gagal dihapus.');
        self::audit($userId, 'HAPUS_BACKUP', $nama, 'Berkas backup dihapus');
        return "Backup {$nama} dihapus.";
    }

    // ------------------------------------------------------------------ Pulihkan

    private static function hapusPohon(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        @rmdir($dir);
    }

    /** Ekstrak isi backup ke folder sementara dan kembalikan path-nya. */
    private static function ekstrak(string $arsip): string
    {
        $tmp = self::folder() . DIRECTORY_SEPARATOR . 'tmp_pulih_' . date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 6);
        if (!@mkdir($tmp, 0775, true)) throw new \Exception('Folder sementara tidak bisa dibuat.');
        try {
            if (substr($arsip, -4) === '.zip') {
                if (!class_exists('ZipArchive')) throw new \Exception('Ekstensi zip PHP belum aktif, berkas .zip tidak bisa dibuka.');
                $z = new \ZipArchive();
                if ($z->open($arsip) !== true) throw new \Exception('Berkas zip rusak atau tidak bisa dibuka.');
                $z->extractTo($tmp);
                $z->close();
            } else {
                (new \PharData($arsip))->extractTo($tmp, null, true);
            }
        } catch (\Throwable $e) {
            self::hapusPohon($tmp);
            throw new \Exception('Berkas rusak atau bukan hasil backup aplikasi ini.');
        }
        return $tmp;
    }

    /** Periksa isi backup tanpa mengubah apa pun: harus ada database.sql yang utuh. */
    private static function periksaIsi(string $tmp): string
    {
        $sql = $tmp . DIRECTORY_SEPARATOR . 'database.sql';
        if (!is_file($sql) || filesize($sql) < 100) throw new \Exception('Berkas backup tidak berisi database.sql yang valid.');
        $ujung = (string)file_get_contents($sql, false, null, max(0, filesize($sql) - 300));
        $awal = (string)file_get_contents($sql, false, null, 0, 600);
        if (stripos($ujung, 'Dump completed') === false || stripos($awal, 'dump') === false) {
            throw new \Exception('database.sql tidak utuh (bukan hasil backup yang lengkap).');
        }
        return $sql;
    }

    /**
     * Pulihkan database dan bukti setoran dari satu backup. Sebelumnya dibuat backup pengaman otomatis.
     * @return array{pesan:string,pengaman:string}
     */
    public static function pulihkan(string $nama, int $userId): array
    {
        $arsip = self::path($nama);
        @set_time_limit(600);

        // 1. Periksa dulu backupnya utuh, sebelum menyentuh data saat ini
        $tmp = self::ekstrak($arsip);
        try {
            $sql = self::periksaIsi($tmp);

            // 2. Backup pengaman dari kondisi sekarang
            $pengaman = self::buat($userId, 'sebelumpulih');

            // 3. Impor database
            $cmd = [self::cariKlien('mysql'), '--host=' . Config::getDbHost(), '--port=' . Config::getDbPort(), '--user=' . Config::getDbUser(),
                '--default-character-set=utf8mb4', Config::getDbName()];
            $p = @proc_open($cmd, [0 => ['file', $sql, 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, self::envKlien());
            if (!is_resource($p)) throw new \Exception('Klien mysql tidak bisa dijalankan. Isi env MYSQL_PATH dengan lokasi mysql. Data belum diubah.');
            stream_get_contents($pipes[1]);
            $err = trim((string)stream_get_contents($pipes[2]));
            fclose($pipes[1]); fclose($pipes[2]);
            if (proc_close($p) !== 0) {
                throw new \Exception('Impor database gagal' . ($err !== '' ? ': ' . $err : '.') . ' Backup pengaman: ' . $pengaman['nama'] . '.');
            }

            // 4. Kembalikan bukti setoran (menimpa berkas bernama sama, tidak menghapus berkas lain)
            $jumlah = 0;
            $sumber = $tmp . DIRECTORY_SEPARATOR . 'uploads';
            if (is_dir($sumber)) {
                $tujuan = self::folderUpload();
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sumber, \FilesystemIterator::SKIP_DOTS));
                $akarSumber = str_replace('\\', '/', $sumber) . '/';
                foreach ($it as $f) {
                    if (!$f->isFile()) continue;
                    $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen($akarSumber));
                    if ($rel === '' || strpos($rel, '..') !== false) continue;
                    $dest = $tujuan . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                    if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0775, true);
                    if (@copy($f->getPathname(), $dest)) $jumlah++;
                }
            }

            // 5. Berkas konfigurasi: akses Accurate dikembalikan; .env hanya dibuat bila belum ada
            //    (agar sandi database di komputer ini tidak tertimpa dengan yang dari komputer lain)
            $konfigPulih = [];
            foreach (self::berkasKonfig() as $namaArsip => $lokasi) {
                $sumberKonfig = $tmp . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $namaArsip);
                if (!is_file($sumberKonfig)) continue;
                if ($namaArsip === 'config/.env' && is_file($lokasi)) continue;
                if (@copy($sumberKonfig, $lokasi)) $konfigPulih[] = basename($lokasi);
            }
        } finally {
            self::hapusPohon($tmp);
        }

        // Dicatat setelah impor, supaya jejaknya ada di database hasil pulihan
        self::audit($userId, 'PULIHKAN_BACKUP', $nama, "Database dipulihkan dari {$nama}; {$jumlah} berkas upload dikembalikan" . ($konfigPulih ? '; konfigurasi: ' . implode(', ', $konfigPulih) : '') . "; pengaman: {$pengaman['nama']}");
        return ['pesan' => "Data dipulihkan dari {$nama}. Backup kondisi sebelumnya disimpan sebagai {$pengaman['nama']}.", 'pengaman' => $pengaman['nama']];
    }

    /** Simpan backup dari komputer lain ke daftar (untuk memulihkan di komputer baru). */
    public static function unggah(string $tmpFile, string $namaAsli, int $userId): string
    {
        $ext = preg_match('/\.tar\.gz$/i', $namaAsli) ? 'tar.gz' : (preg_match('/\.zip$/i', $namaAsli) ? 'zip' : '');
        if ($ext === '') throw new \Exception('Format berkas harus .zip atau .tar.gz hasil backup aplikasi ini.');
        if ($ext === 'zip' && !class_exists('ZipArchive')) throw new \Exception('Ekstensi zip PHP belum aktif, berkas .zip tidak bisa diproses.');

        $dir = self::folder();
        // Pertahankan nama bila sudah berformat backup, selain itu beri nama baru
        $nama = self::namaValid($namaAsli) ? $namaAsli : '';
        $t = time();
        while ($nama === '' || is_file($dir . DIRECTORY_SEPARATOR . $nama)) {
            $nama = 'backup_' . date('Ymd_His', $t++) . '.' . $ext;
        }
        $tujuan = $dir . DIRECTORY_SEPARATOR . $nama;
        if (!@move_uploaded_file($tmpFile, $tujuan) && !@rename($tmpFile, $tujuan)) throw new \Exception('Berkas gagal disimpan.');

        try {
            $tmp = self::ekstrak($tujuan);
            try { self::periksaIsi($tmp); } finally { self::hapusPohon($tmp); }
        } catch (\Throwable $e) {
            @unlink($tujuan);
            throw new \Exception('Berkas ditolak: ' . $e->getMessage());
        }
        self::audit($userId, 'UNGGAH_BACKUP', $nama, 'Backup diunggah (' . self::ukuranTeks((int)filesize($tujuan)) . ')');
        return $nama;
    }

    // ------------------------------------------------------------------ Otomatis bulanan

    private static function statusOtomatis(): array
    {
        $f = self::folder() . DIRECTORY_SEPARATOR . '.auto_status.json';
        $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
        return is_array($j) ? $j : [];
    }

    public static function infoOtomatis(): array
    {
        return self::statusOtomatis();
    }

    /**
     * Buat backup otomatis bila bulan ini belum ada backup sama sekali. Dipanggil ringan di akhir tiap halaman
     * (setelah halaman terkirim); pemeriksaan hanya membaca nama berkas. Kegagalan dicoba lagi paling cepat 6 jam kemudian.
     */
    public static function otomatisBulanan(): void
    {
        try {
            $dir = self::folder();
            $bulan = date('Ym');
            foreach (scandir($dir) ?: [] as $f) {
                if (self::namaValid($f) && substr($f, 7, 6) === $bulan) return; // bulan ini sudah ada backup
            }
            $st = self::statusOtomatis();
            if (($st['ok'] ?? true) === false && time() - (int)($st['waktu'] ?? 0) < 6 * 3600) return;

            $kunci = @fopen($dir . DIRECTORY_SEPARATOR . '.auto_lock', 'c');
            if (!$kunci || !flock($kunci, LOCK_EX | LOCK_NB)) return; // sedang dikerjakan permintaan lain
            try {
                foreach (scandir($dir) ?: [] as $f) if (self::namaValid($f) && substr($f, 7, 6) === $bulan) return;
                try {
                    $h = self::buat(0, 'auto');
                    $st = ['ok' => true, 'waktu' => time(), 'pesan' => $h['pesan']];
                } catch (\Throwable $e) {
                    $st = ['ok' => false, 'waktu' => time(), 'pesan' => $e->getMessage()];
                }
                @file_put_contents($dir . DIRECTORY_SEPARATOR . '.auto_status.json', json_encode($st));
            } finally {
                flock($kunci, LOCK_UN);
                fclose($kunci);
            }
        } catch (\Throwable $e) { /* backup otomatis tidak boleh mengganggu halaman */ }
    }

    // ------------------------------------------------------------------

    public static function audit(int $userId, string $aksi, string $nama, string $alasan): void
    {
        try {
            Database::getConnection()->prepare("INSERT INTO audit_logs (user_id, action_name, target_table, target_id, reason, ip_address) VALUES (?,?,?,?,?,?)")
                ->execute([$userId ?: null, $aksi, 'backup', $nama, $alasan, $_SERVER['REMOTE_ADDR'] ?? 'cli']);
        } catch (\Throwable $e) { /* log audit tidak boleh menggagalkan backup */ }
    }

    private static function rapikan(string $dir): void
    {
        $per = [];
        foreach (self::daftar() as $b) $per[$b['jenis']][] = $b['nama'];
        foreach ($per as $jenis => $daftar) {
            foreach (array_slice($daftar, self::SIMPAN[$jenis] ?? 30) as $lama) @unlink($dir . DIRECTORY_SEPARATOR . $lama);
        }
    }

    public static function ukuranTeks(int $b): string
    {
        if ($b >= 1048576) return number_format($b / 1048576, 1, ',', '.') . ' MB';
        return number_format(max(1, $b) / 1024, 0, ',', '.') . ' KB';
    }

    /** Waktu backup terakhir (unix) atau null. */
    public static function terakhir(): ?int
    {
        $d = self::daftar();
        return $d ? $d[0]['waktu'] : null;
    }
}
