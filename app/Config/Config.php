<?php
/**
 * Konfigurasi Sistem Barcode Inventory Multi-Mitra
 */

namespace App\Config;

class Config {
    // Info Aplikasi
    public const APP_NAME = 'Barcode Inventory Multi-Mitra';
    public const APP_VERSION = '1.1.0';
    public const APP_ORGANIZATION = 'Logistik Bersama Multi-Mitra';

    // Konfigurasi Database (Default XAMPP: root tanpa password)
    public const DB_DRIVER   = 'mysql'; // 'mysql' atau 'sqlite'
    public const DB_HOST     = '127.0.0.1';
    public const DB_PORT     = '3306';
    public const DB_NAME     = 'db_barcode_inventory';
    public const DB_USER     = 'root';
    public const DB_PASS     = '';
    public const DB_SQLITE_PATH = __DIR__ . '/../../database/inventory.sqlite';

    // Konfigurasi Accurate Online REST API (Developer App Key & Signature Secret)
    public const ACCURATE_APP_KEY          = 'f33bdc72-f56f-4393-83f7-2b5ef042dc13';
    public const ACCURATE_SIGNATURE_SECRET = 't6AhrqoZ6tBQ2uh8UwBOLzPXEOWK2aEOEqplnWORjCn7PZ94i5hPkc9aXiZNHKAr';
    public const ACCURATE_ACCOUNT_URL      = 'https://account.accurate.id';
    public const ACCURATE_API_BASE_URL     = 'https://zeus.accurate.id/accurate/api';
    public const ACCURATE_REDIRECT_URI     = 'http://localhost/project/index.php?page=accurate_auth';
    public const ACCURATE_CONFIG_FILE      = __DIR__ . '/accurate_config.json';

    // Konfigurasi Legacy/OAuth
    public const ACCURATE_CLIENT_ID        = self::ACCURATE_APP_KEY;
    public const ACCURATE_CLIENT_SECRET    = self::ACCURATE_SIGNATURE_SECRET;
    public const ACCURATE_DEFAULT_DB_ID    = 'ACC_DB_01';

    public static function getAccurateConfig(): array {
        $default = [
            'app_key'          => self::ACCURATE_APP_KEY,
            'signature_secret' => self::ACCURATE_SIGNATURE_SECRET,
            'api_token'        => '',
            'db_id'            => '',
            'db_alias'         => '',
            'session_id'       => '',
            'host'             => 'https://zeus.accurate.id',
            'is_connected'     => false,
            'last_sync'        => null
        ];

        if (file_exists(self::ACCURATE_CONFIG_FILE)) {
            $content = file_get_contents(self::ACCURATE_CONFIG_FILE);
            $data = json_decode($content, true);
            if (is_array($data)) {
                return array_merge($default, $data);
            }
        }
        return $default;
    }

    public static function saveAccurateConfig(array $newData): array {
        $current = self::getAccurateConfig();
        $merged = array_merge($current, $newData);
        file_put_contents(self::ACCURATE_CONFIG_FILE, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $merged;
    }

    // Pengaturan Stasiun Timbang & Printer Barcode
    public const THERMAL_LABEL_WIDTH_MM  = 100;
    public const THERMAL_LABEL_HEIGHT_MM = 50;
    public const SCALE_DEFAULT_BAUDRATE  = 9600; // RS-232 baud rate standar timbangan
    public const SCALE_DEFAULT_PARITY    = 'none';

    // Format Penomoran Label: [KodeMitra]-[KodeJenis]-[YYMMDD]-[Urut 5 digit]
    public const LABEL_FORMAT = '{MITRA}-{JENIS}-{YYMMDD}-{SEQ}';

    public static function getBaseUrl(): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '/project/index.php';
        $dir = rtrim(dirname($script), '/\\');
        return $protocol . $host . ($dir === '' ? '' : $dir);
    }
}
