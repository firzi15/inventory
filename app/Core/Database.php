<?php
/**
 * Database Singleton Connection Handler
 */

namespace App\Core;

use PDO;
use PDOException;
use App\Config\Config;

class Database {
    private static ?PDO $instance = null;
    private static string $driverUsed = 'mysql';

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }

    public static function getDriver(): string {
        return self::$driverUsed;
    }

    private static function connect(): void {
        $host = Config::getDbHost();
        $port = Config::getDbPort();
        $dbname = Config::getDbName();
        $user = Config::getDbUser();
        $pass = Config::getDbPass();

        try {
            // Coba koneksi MySQL terlebih dahulu
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $host,
                $port,
                $dbname
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            self::$instance = new PDO($dsn, $user, $pass, $options);
            self::$driverUsed = 'mysql';
        } catch (PDOException $e) {
            // Jika Access Denied (1045) di host lokal, coba coba fallback password kosong (standar XAMPP)
            if ($e->getCode() == 1045 && ($host === '127.0.0.1' || $host === 'localhost')) {
                try {
                    self::$instance = new PDO($dsn, $user, '', $options);
                    self::$driverUsed = 'mysql';
                    return;
                } catch (\Throwable $ex2) {
                    // Lanjut ke penanganan selanjutnya
                }
            }

            // Jika database belum dibuat di MySQL, coba koneksi tanpa dbname lalu buat db
            if ($e->getCode() == 1049 || str_contains($e->getMessage(), 'Unknown database')) {
                try {
                    $initPdo = new PDO(
                        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
                        $user,
                        $pass
                    );
                    $initPdo->exec("CREATE DATABASE IF NOT EXISTS `" . $dbname . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    self::$instance = new PDO($dsn, $user, $pass, $options);
                    self::$driverUsed = 'mysql';
                    return;
                } catch (\Throwable $ex) {
                    // Fallback to SQLite
                }
            }

            // Fallback ke SQLite jika MySQL gagal
            try {
                $sqliteDir = dirname(Config::DB_SQLITE_PATH);
                if (!is_dir($sqliteDir)) {
                    mkdir($sqliteDir, 0777, true);
                }
                $sqliteDsn = 'sqlite:' . Config::DB_SQLITE_PATH;
                self::$instance = new PDO($sqliteDsn, null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                self::$driverUsed = 'sqlite';
            } catch (PDOException $sqliteEx) {
                die("Koneksi Database Gagal: " . $e->getMessage() . " | Fallback SQLite Gagal: " . $sqliteEx->getMessage());
            }
        }
    }
}
