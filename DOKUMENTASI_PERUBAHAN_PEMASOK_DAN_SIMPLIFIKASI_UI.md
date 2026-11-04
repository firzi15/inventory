# Dokumentasi Perubahan: Master Pemasok, Alokasi Mitra, & Penyederhanaan Tampilan

Dokumen ini mencatat pembaruan sistem sesuai arahan pengguna:
1. **Penggantian Mitra dengan Pemasok pada Menu Penerimaan**.
2. **Pembuatan Master Pemasok (CRUD Lengkap)**.
3. **Penyelarasan Mitra untuk Pemindahan Stok (Mutasi Kepemilikan)**.
4. **Penyederhanaan Tampilan Web Aplikasi agar Ramah Orang Awam**.

---

## 1. Alur Bisnis Baru: Pemasok vs Mitra

Sebelumnya, barang masuk di stasiun Penerimaan langsung diikat ke Mitra Bisnis. Pada alur baru yang disempurnakan:
```mermaid
graph LR
    A["🚚 Pemasok (Supplier/Nelayan/Tambak)"] -->|"Menu Penerimaan: Buat Barcode & Timbang Berat"| B["📦 Stok Inbound di Gudang (Belum Terikat Mitra)"]
    B -->|"Menu Pemindahan Stok: Alokasi / Mutasi Kepemilikan"| C["🏢 Mitra Bisnis Terpilih (M01, M02, dst.)"]
    C -->|"Menu Pengeluaran / POS"| D["🛒 Penjualan ke Pembeli / Kasir POS"]
```

1. **Penerimaan Barang**:
   - Di menu Penerimaan, pengirim barang dicatat sebagai **Pemasok** (Supplier).
   - Stiker barcode massal keranjang digenerate dengan prefix kode Pemasok (`PMS01-...`) dan No. Surat Jalan Pemasok (`SJ-PMS-01-...`).
   - Bobot riil timbangan ditimbang dan dicatat.
2. **Pemindahan Stok (Mutasi)**:
   - Operator memindahkan/mengalokasikan stok dari Pemasok ke **Mitra Bisnis** tertentu (`mitra_id`).
   - Diterbitkan dokumen **Berita Acara Serah Terima (BAST)** resmi.
   - Tercatat histori mutasi dan sinkronisasi ke modul Gudang Accurate Online.

---

## 2. Struktur Database & Model Baru

### A. Tabel `pemasok`
Tabel baru yang menyimpan data rekanan pemasok:
```sql
CREATE TABLE IF NOT EXISTS pemasok (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_pemasok VARCHAR(20) NOT NULL UNIQUE,
    nama_pemasok VARCHAR(150) NOT NULL,
    pic_nama VARCHAR(100) NULL,
    telepon VARCHAR(50) NULL,
    alamat TEXT NULL,
    keterangan VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Data Awal (Seeder)**:
- `PMS-01`: Nelayan Muara Baru (PIC: Pak Mahmud)
- `PMS-02`: Tambak Bahari Nusantara (PIC: H. Sudirman)
- `PMS-03`: PT Sumber Laut Mandiri (PIC: Ibu Ratna)
- `PMS-04`: Pengepul Mina Jaya (PIC: Mas Joko)

### B. Perubahan Tabel `labels`
- Kolom baru `pemasok_id INT NULL` (relasi ke `pemasok.id`).
- Kolom `mitra_id` diubah menjadi `NULLABLE` (agar saat penerimaan awal barang belum wajib diikat ke mitra sebelum tahap pemindahan stok).

### C. Model `App\Models\Pemasok`
Berisi method:
- `getAll()`, `getAllActive()`, `findById()`, `findByKode()`
- `create($data)`
- `update($id, $data)`
- `delete($id)`
- `generateNextKode()` (otomatis menghasilkan format `PMS-01`, `PMS-02`, dst.)

---

## 3. Fitur Master Pemasok (Master Data Hub)

Dapat diakses di menu **Master Data** -> Tab **🚚 Master Pemasok (Supplier)**:
- **Tabel Interaktif**: Menampilkan Kode Pemasok, Nama Pemasok, PIC, Nomor Telepon, Alamat, Status, dan Tombol Aksi (Edit & Hapus).
- **Modal Tambah / Edit Pemasok**: Formulir ramah pengguna dengan validasi otomatis dan kode otomatis `PMS-XX`.
- **Fitur Ekspor**: Tombol ekspor ke format Excel (`.xlsx`).

---

## 4. Penyederhanaan Tampilan (Simple & Ramah Orang Awam)

Tampilan antarmuka telah didesain ulang agar mudah digunakan oleh operator gudang pemula tanpa kebingungan:
1. **Bahasa Indonesia Sehari-hari**: Menghilangkan istilah asing rumit seperti *"Pre-Printed Roll"*, *"Catch-Weight Precision"*, *"Inbound Session"*. Digantikan dengan istilah umum:
   - *"Buat & Cetak Barcode Keranjang"*
   - *"Timbang Berat Barang"*
   - *"Arahkan Scanner ke Barcode"*
   - *"Simpan Berat (Tekan Enter)"*
2. **Pilihan Jumlah Cetak Lebih Jelas**:
   - Pilihan cepat 10 Pcs (1 Lembar), 20 Pcs (2 Lembar), 30 Pcs (3 Lembar), 50 Pcs (5 Lembar), dan 5 Pcs (½ Lembar) dengan instruksi jelas bahwa 1 lembar A4 berisi 10 stiker.
3. **Filter Penerimaan Sederhana**:
   - Filter Pemasok, Status Timbang (Sudah / Belum Ditimbang), dan Lokasi Gudang.
   - Pilihan beralih tampilan: `📦 Per Surat Jalan` (langsung cetak 1 lembar) atau `📋 Rincian Satuan (1 Pcs)`.
4. **Stasiun Penimbangan Intuitif**:
   - Kotak pemindaian langsung memunculkan informasi Pemasok & Nama Produk setelah barcode discan.
   - Tombol simpan besar yang langsung merespons tombol Enter keyboard.
