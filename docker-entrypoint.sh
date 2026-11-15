#!/bin/bash
set -e

echo "=========================================================="
echo " Starting Barcode Inventory Multi-Mitra System in Docker "
echo "=========================================================="

# Pastikan direktori writable
mkdir -p /var/www/html/database /var/www/html/app/Config
mkdir -p /var/www/html/assets/uploads /var/backups/inventory
chmod -R 777 /var/www/html/database /var/www/html/app/Config /var/www/html/assets/uploads 2>/dev/null || true
chown -R www-data:www-data /var/backups/inventory 2>/dev/null || true

# Tunggu service database jika DB_HOST dikonfigurasi
if [ -n "$DB_HOST" ]; then
    echo "[Docker Init] Menunggu koneksi MySQL/MariaDB di $DB_HOST:${DB_PORT:-3306}..."
    max_tries=30
    count=0
    until php -r '
        $h = getenv("DB_HOST");
        $p = getenv("DB_PORT") ?: 3306;
        $conn = @fsockopen($h, (int)$p, $errno, $errstr, 2);
        if ($conn) { fclose($conn); exit(0); }
        exit(1);
    ' 2>/dev/null; do
        count=$((count+1))
        if [ $count -gt $max_tries ]; then
            echo "[Docker Init] Peringatan: Tidak dapat terhubung ke MySQL setelah $max_tries detik."
            break
        fi
        sleep 1
    done
    echo "[Docker Init] Koneksi MySQL/MariaDB berhasil terhubung!"

    # Inisialisasi otomatis jika tabel belum ada
    php -r '
        require_once "/var/www/html/app/Config/Config.php";
        require_once "/var/www/html/app/Core/Database.php";
        try {
            $pdo = \App\Core\Database::getConnection();
            $stmt = $pdo->query("SHOW TABLES LIKE \"labels\"");
            if ($stmt->rowCount() == 0) {
                echo "[Docker Init] Database masih kosong. Menjalankan skema awal dari setup.php..." . PHP_EOL;
                require_once "/var/www/html/setup.php";
            } else {
                echo "[Docker Init] Database sudah terisi dan siap digunakan." . PHP_EOL;
            }
        } catch (\Throwable $e) {
            echo "[Docker Init] Catatan cek database: " . $e->getMessage() . PHP_EOL;
        }
    ' || true
fi

echo "[Docker Init] Menjalankan Apache HTTP Server..."
exec "$@"

