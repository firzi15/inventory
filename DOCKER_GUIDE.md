# 🐳 Panduan Penggunaan Docker - Barcode Inventory Multi-Mitra

Proyek ini telah dikonfigurasi penuh agar siap dijalankan menggunakan **Docker** dan **Docker Compose** dengan struktur multi-kontainer:
1. **`app`**: Web server Apache + PHP 8.5 (ekstensi PDO MySQL, BCMath, OPcache) beserta klien MariaDB untuk fitur Backup data.
2. **`db`**: Database MariaDB 10.11 (otomatis menjalankan schema & seed data awal dari `database/schema.sql`).
3. **`phpmyadmin`**: UI web pengelola database MySQL/MariaDB.

---

## 🚀 1. Cara Menjalankan (Quick Start)

Buka terminal (PowerShell / CMD / Git Bash) di folder project:

```bash
docker compose up -d
```

> **Catatan:** Pada saat pertama kali dijalankan, Docker akan mendownload base image dan menginisialisasi database otomatis. Tunggu sekitar 1–2 menit.

---

## 🌐 2. Alamat Akses Layanan

Setelah kontainer berjalan:

| Layanan | URL / Host | Kredensial Default |
|---|---|---|
| **Aplikasi Barcode Inventory** | [http://localhost:8085](http://localhost:8085) | Login via sistem |
| **phpMyAdmin** | [http://localhost:8082](http://localhost:8082) | User: `root` / Pass: `secret123` |
| **Akses Database Eksternal** *(DBeaver, Navicat, HeidiSQL)* | `localhost:3307` | User: `root` / Pass: `secret123` / DB: `db_barcode_inventory` |

*(Port `3307` sengaja digunakan untuk database agar **tidak bentrok** dengan MySQL XAMPP lokal Anda di port `3306`)*.

---

## 🛠️ 3. Perintah Berguna

### Memeriksa Status Kontainer:
```bash
docker compose ps
```

### Melihat Log Realtime:
```bash
# Semua kontainer:
docker compose logs -f

# Hanya kontainer aplikasi:
docker compose logs -f app

# Hanya kontainer database:
docker compose logs -f db
```

### Menghentikan Kontainer:
```bash
docker compose down
```

### Menghentikan dan Mereset Data Database Bersih:
```bash
docker compose down -v
```

### Membangun Ulang Image (Jika ada penambahan ekstensi di Dockerfile):
```bash
docker compose up -d --build
```

---

## 💾 Backup data di Docker

Menu **Pengaturan > Backup** berjalan penuh di Docker. File backup disimpan di volume `backup_data` (folder `/var/backups/inventory` di dalam container), jadi tetap ada saat container dibuat ulang. Perintah `docker compose down -v` ikut menghapus volume ini. Unduh backup penting lebih dulu dari menu Backup sebelum memakai `-v`.

---

## ⚙️ 4. Pengaturan Lingkungan (.env)

Pengaturan port dan kredensial dapat disesuaikan pada file [`.env`](.env):

```ini
APP_PORT=8085
PMA_PORT=8082
DB_EXTERNAL_PORT=3307

DB_HOST=db
DB_PORT=3306
DB_NAME=db_barcode_inventory
DB_USER=root
DB_PASS=secret123
```

---

## 🔄 5. Kompatibilitas dengan XAMPP Lokal

Pengaturan ini **100% kompatibel ganda**:
- Jika dijalankan di **XAMPP lokal**, sistem otomatis mendeteksi koneksi default `127.0.0.1:3306`.
- Jika dijalankan di **Docker**, sistem otomatis membaca environment variable `DB_HOST=db`, `DB_PORT=3306`, dll.
- Anda dapat berpindah antara XAMPP lokal dan Docker kapan saja tanpa perlu mengubah baris kode.

