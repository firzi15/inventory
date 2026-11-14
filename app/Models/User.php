<?php
/**
 * Model User & Manajemen Akses Login
 * Sistem Barcode Inventory Multi-Mitra
 */

namespace App\Models;

use App\Core\Database;
use PDO;
use Throwable;

class User {
    public const ROLE_ADMIN = 'ADMIN';
    public const ROLE_ARIF = 'SUPERVISOR';
    public const ROLE_OPERATOR = 'OPERATOR_OUTBOUND';

    /**
     * Pastikan tabel users ada dan data akun default (Nia, Arif, Operator) tersedia
     */
    public static function syncDefaultUsers(): void {
        try {
            $db = Database::getConnection();
            $driver = Database::getDriver();

            // 1. Buat tabel jika belum ada (kompatibel MySQL & SQLite)
            if ($driver === 'sqlite') {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS users (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        username TEXT UNIQUE NOT NULL,
                        password_hash TEXT NOT NULL,
                        nama_lengkap TEXT NOT NULL,
                        role TEXT NOT NULL DEFAULT 'OPERATOR',
                        is_active INTEGER NOT NULL DEFAULT 1,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )
                ");
            } else {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `users` (
                        `id` INT(11) NOT NULL AUTO_INCREMENT,
                        `username` VARCHAR(50) NOT NULL,
                        `password_hash` VARCHAR(255) NOT NULL,
                        `nama_lengkap` VARCHAR(100) NOT NULL,
                        `role` VARCHAR(50) NOT NULL DEFAULT 'OPERATOR',
                        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `uniq_username` (`username`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }

            // 2. Daftar 3 Akun Utama Sesuai Kebutuhan:
            // 1) Nia (Admin)
            // 2) Arif
            // 3) Operator
            $defaultAccounts = [
                1 => [
                    'id'           => 1,
                    'username'     => 'nia',
                    'nama_lengkap' => 'Nia',
                    'role'         => 'ADMIN',
                    'password'     => 'Admin@2026'
                ],
                2 => [
                    'id'           => 2,
                    'username'     => 'arif',
                    'nama_lengkap' => 'Arif',
                    'role'         => 'SUPERVISOR',
                    'password'     => 'Founder@2026'
                ],
                3 => [
                    'id'           => 3,
                    'username'     => 'operator',
                    'nama_lengkap' => 'Operator Gudang',
                    'role'         => 'OPERATOR_OUTBOUND',
                    'password'     => 'Operator@2026'
                ]
            ];

            foreach ($defaultAccounts as $acc) {
                // Cek berdasarkan Username (UNIQUE)
                $stmt = $db->prepare("SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1");
                $stmt->execute([':username' => $acc['username']]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    // Update profil agar sinkron dengan role dan nama yang diminta
                    $updateStmt = $db->prepare("
                        UPDATE users 
                        SET username = :username, 
                            nama_lengkap = :nama_lengkap, 
                            role = :role, 
                            is_active = 1
                        WHERE id = :id
                    ");
                    $updateStmt->execute([
                        ':username'     => $acc['username'],
                        ':nama_lengkap' => $acc['nama_lengkap'],
                        ':role'         => $acc['role'],
                        ':id'           => $existing['id']
                    ]);

                    // Pastikan password sesuai kredensial yang ditetapkan
                    if (empty($existing['password_hash']) || !password_verify($acc['password'], $existing['password_hash'])) {
                        $pwStmt = $db->prepare("UPDATE users SET password_hash = :h WHERE id = :id");
                        $pwStmt->execute([':h' => password_hash($acc['password'], PASSWORD_BCRYPT), ':id' => $existing['id']]);
                    }
                } else {
                    // Insert baru jika belum ada
                    $insertStmt = $db->prepare("
                        INSERT INTO users (id, username, password_hash, nama_lengkap, role, is_active)
                        VALUES (:id, :username, :password_hash, :nama_lengkap, :role, 1)
                    ");
                    $insertStmt->execute([
                        ':id'           => $acc['id'],
                        ':username'     => $acc['username'],
                        ':password_hash'=> password_hash($acc['password'], PASSWORD_BCRYPT),
                        ':nama_lengkap' => $acc['nama_lengkap'],
                        ':role'         => $acc['role']
                    ]);
                }
            }
        } catch (Throwable $e) {
            error_log("Error User::syncDefaultUsers: " . $e->getMessage());
        }
    }

    /**
     * Dapatkan semua user aktif untuk pilihan switch/quick login
     */
    public static function getAllActive(): array {
        try {
            self::syncDefaultUsers();
            $db = Database::getConnection();
            $stmt = $db->query("SELECT id, username, nama_lengkap, role, is_active FROM users WHERE is_active = 1 ORDER BY id ASC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                return $rows;
            }
        } catch (Throwable $e) {
            error_log("Error User::getAllActive: " . $e->getMessage());
        }

        // Fallback jika database belum siap
        return [
            ['id' => 1, 'username' => 'nia', 'nama_lengkap' => 'Nia', 'role' => 'ADMIN', 'is_active' => 1],
            ['id' => 2, 'username' => 'arif', 'nama_lengkap' => 'Arif', 'role' => 'SUPERVISOR', 'is_active' => 1],
            ['id' => 3, 'username' => 'operator', 'nama_lengkap' => 'Operator Gudang', 'role' => 'OPERATOR_OUTBOUND', 'is_active' => 1],
        ];
    }

    /**
     * Cari user berdasarkan username (case-insensitive & mendukung alias)
     */
    public static function findByUsername(string $username): ?array {
        $username = strtolower(trim($username));
        if ($username === '') return null;

        // Alias mapping
        if ($username === 'admin') $username = 'nia';
        if ($username === 'opr_timbang' || $username === 'opr_kirim' || $username === 'opr') $username = 'operator';

        try {
            self::syncDefaultUsers();
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(username) = :u AND is_active = 1 LIMIT 1");
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) return $user;
        } catch (Throwable $e) {
            error_log("Error User::findByUsername: " . $e->getMessage());
        }

        // Fallback array
        $map = [
            'nia'      => ['id' => 1, 'username' => 'nia', 'nama_lengkap' => 'Nia', 'role' => 'ADMIN', 'password_hash' => ''],
            'arif'     => ['id' => 2, 'username' => 'arif', 'nama_lengkap' => 'Arif', 'role' => 'SUPERVISOR', 'password_hash' => ''],
            'operator' => ['id' => 3, 'username' => 'operator', 'nama_lengkap' => 'Operator Gudang', 'role' => 'OPERATOR_OUTBOUND', 'password_hash' => ''],
        ];

        return $map[$username] ?? null;
    }

    /**
     * Cari user berdasarkan ID
     */
    public static function findById(int $id): ?array {
        try {
            self::syncDefaultUsers();
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM users WHERE id = :id AND is_active = 1 LIMIT 1");
            $stmt->execute([':id' => $id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) return $user;
        } catch (Throwable $e) {
            error_log("Error User::findById: " . $e->getMessage());
        }

        $all = self::getAllActive();
        foreach ($all as $u) {
            if ((int)$u['id'] === $id) return $u;
        }
        return null;
    }

    /**
     * Autentikasi user dengan username dan password
     */
    public static function authenticate(string $username, string $password): ?array {
        $user = self::findByUsername($username);
        if (!$user) {
            return null;
        }

        $password = trim($password);

        // Jika password kosong dan bukan quick login, tolak
        if ($password === '') {
            return null;
        }

        $hash = $user['password_hash'] ?? '';
        if (empty($hash) || !password_verify($password, $hash)) {
            return null;
        }

        // Hapus password hash dari session payload
        unset($user['password_hash']);
        return $user;
    }
}
