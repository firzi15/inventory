# 📦 Sistem Barcode Inventory Multi-Mitra (Catch-Weight)
> **Warehouse Execution System (WES) Terintegrasi REST API Accurate Online**  
> Disusun untuk mendukung operasional pergudangan multi-mitra (7 Mitra) dengan variasi bobot kemasan (*catch-weight*).

---

## 🌟 Fitur Unggulan Kerangka Aplikasi

1. **Segregasi Multi-Mitra Otomatis**:
   - Pemisahan stok fisik mutlak untuk 7 Mitra (`M01` s/d `M07`).
   - Validasi ketat saat pengeluaran: sistem otomatis menolak barang jika nomor label barcode bukan milik mitra yang bersangkutan.
2. **Stasiun Timbang & Cetak Label (100 × 50 mm)**:
   - Terintegrasi langsung dengan timbangan digital via **Web Serial API (RS-232 / USB)**.
   - Pengecekan otomatis toleransi bobot minimum dan maksimum per jenis barang.
   - Cetak stiker thermal berformat industri (Barcode Code 128 + QR Code + Netto Besar).
3. **Penerimaan & Pengeluaran Berbasis Barcode**:
   - Input cepat menggunakan scanner barcode genggam (USB / Bluetooth).
   - Audio feedback synthesizer (nada *beep* sukses dan nada *buzz* saat error).
4. **Matriks Stok Multi-Mitra & Ekspor**:
   - Matriks pivot: 7 Mitra × Kategori Barang (Kilogram dan Jumlah Kemasan).
   - Ekspor 1-klik ke format CSV / Excel dan format siap cetak PDF.
5. **Integrasi REST API Accurate Online**:
   - Master barang tersinkronisasi dari Accurate Online.
   - Pemetaan: **1 Mitra = 1 Gudang di Accurate** (`GDG-M01` s/d `GDG-M07`).
   - Pengiriman otomatis transaksi pengeluaran ke Accurate via antrean *background (queue)* dengan *retry* 3× dan nomor referensi *idempotent*.
6. **Portal PRD Interaktif**:
   - Tampilan PRD interaktif dengan navigasi cepat, pencarian bab, dan simulasi penimbangan langsung di browser.

---

## 🚀 Panduan Menjalankan Aplikasi di XAMPP

### Persyaratan Sistem:
- Apache Web Server (Port 80)
- MySQL / MariaDB (Port 3306)
- PHP versi 8.0 atau lebih baru (Disarankan PHP 8.2)

### Langkah Setup & Instalasi:

1. **Jalankan Apache & MySQL** pada XAMPP Control Panel.
2. Buka browser dan jalankan script inisialisasi otomatis:
   ```
   http://localhost/project/setup.php
   ```
   *Script ini otomatis membuat database `db_barcode_inventory`, seluruh tabel skema, dan mengisi data awal 7 mitra serta sampel label.*
3. Buka dashboard utama aplikasi:
   ```
   http://localhost/project/index.php
   ```
4. Buka dokumen PRD interaktif:
   ```
   http://localhost/project/index.php?page=prd_viewer
   ```

---

## 📁 Struktur Kerangka Proyek (Scaffolding Architecture)

```
c:\xampp\htdocs\project\
├── DOKUMENTASI_SISTEM.md                        # Dokumentasi Lengkap & Panduan Operasional Sistem (v2.4)
├── DOKUMENTASI_CETAK_DAN_PENGELOMPOKAN_BARCODE.md # Dokumen Teknis Cetak Barcode A4 & Batch Grouping
├── PRD Sistem Barcode Inventory Multi-Mitra.md   # Spesifikasi Dokumen PRD Resmi
├── README.md                                    # Petunjuk Lengkap Kerangka Aplikasi
├── setup.php                                    # Inisialisasi Database 1-Klik
├── index.php                                    # Front Controller & Request Router
│
├── app/
│   ├── Config/
│   │   └── Config.php                           # Konfigurasi DB & Kredensial Accurate Online
│   ├── Core/
│   │   ├── Database.php                         # PDO Singleton Connection (MySQL + SQLite fallback)
│   │   ├── Helper.php                           # Formatter Angka, Kilogram, Badge & JSON
│   │   └── Router.php                           # Routing Helper
│   ├── Models/
│   │   ├── Mitra.php                            # Query Data 7 Mitra Bisnis
│   │   ├── Item.php                             # Master Item SKU & Jenis Barang
│   │   ├── Label.php                            # Barcode ID Generator & Lifecycle State
│   │   └── Transaksi.php                        # Validasi Transaksi & Outbound Scanning
│   └── Services/
│       ├── BarcodeService.php                   # Pure PHP Code 128 & QR Code SVG Generator
│       ├── AccurateApiService.php               # Handler REST API Accurate Online & Queue
│       └── StockService.php                     # Agregasi Matriks Stok 7 Mitra
│
├── database/
│   ├── schema.sql                               # DDL Skema Relasi Database MySQL
│   └── seed.sql                                 # Data Awal 7 Mitra, Kategori & Stok Sampel
│
├── views/
│   ├── layout/
│   │   ├── header.php                           # Top Navigation, Badge & Theme Switcher
│   │   └── footer.php                           # Footer & Driver Loader
│   ├── dashboard.php                            # Dashboard KPI, Stok 7 Mitra & Trx Terakhir
│   ├── timbang_cetak.php                        # Stasiun Timbang & Cetak Label 100x50 mm
│   ├── penerimaan.php                           # Inbound Kemasan Masuk
│   ├── pengeluaran.php                          # Outbound Scan & Validasi Kepemilikan Mitra
│   ├── stok_mitra.php                           # Matriks Stok 7 Mitra & Ekspor Excel/PDF
│   ├── stok_opname.php                          # Modul Stok Opname Digital
│   ├── accurate_sync.php                        # Pemantauan Antrean API Accurate Online
│   └── prd_viewer.php                           # Pembaca PRD Interaktif yang Elegan
│
└── assets/
    ├── css/
    │   ├── style.css                            # UI Design System (Responsive, Modern, Dark/Light)
    │   └── print-label.css                      # Format Cetak Khusus Thermal Label 100x50 mm
    └── js/
        ├── scale.js                             # Web Serial API Driver Timbangan Digital (RS-232/USB)
        └── scanner.js                           # Audio Feedback Synthesizer & Scanner Listener
```

---

## 🏷️ Format Nomor Seri Barcode

Format penomoran label didesain terstruktur:
$$\mathbf{[KodeMitra] - [KodeJenis] - [YYMMDD] - [NomorUrut]}$$

*Contoh:* `M03-JB02-261022-00045`
- `M03`: Mitra ke-3 (PT Nusantara Cold Storage)
- `JB02`: Jenis Barang ke-2 (Potongan Beku / Frozen Cuts)
- `261022`: Tanggal penimbangan (22 Oktober 2026)
- `00045`: Nomor urut kemasan ke-45 hari itu (reset harian per mitra)

---

## ⚖️ Pengujian Stasiun Timbang & Scanner Tanpa Hardware

Sistem ini telah dilengkapi simulator internal:
- **Timbangan**: Tombol shortcut `+24.35 kg`, `+15.20 kg`, dan `+5.05 kg` untuk simulasi instan, atau klik *"Sambungkan Timbangan"* untuk menghubungkan port USB/COM timbangan asli.
- **Scanner Barcode**: Kolom input mendukung scanner laser USB fisik maupun klik cepat pada daftar nomor sampel barcode.
- **Printer Thermal**: Klik *"Simpan & Cetak Label"*, browser akan langsung membuka pratinjau cetak berukuran tepat 100 × 50 mm.
