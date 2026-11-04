# Minutes of Meeting (MoM) – Review Aplikasi Inventory Barcode Multi-Mitra

**Tanggal Dokumen:** 25 Oktober 2026  
**Penulis / Notulen:** Tim Pengembang Aplikasi  
**Status:** Diterima & Disepakati untuk Revisi Alur  

---

## 📌 Ringkasan Eksekutif (Executive Summary)

Demo *prototype* aplikasi telah dipresentasikan kepada klien dan secara umum diterima (**"sudah nyambung"** dengan kebutuhan operasional di lapangan). 

Namun demikian, terdapat koreksi fundamental pada **alur pengeluaran barang**:
> **Koreksi Kunci:**  
> Barang yang keluar dari gudang utama ke mitra/lapak adalah **beli putus** dan langsung dicatat sebagai **piutang (AR)**, bukan stok titipan / konsinyasi.  
> Oleh karena itu, stok gudang langsung berkurang, dan fokus pelacakan di sisi mitra adalah **AR Aging (umur piutang)** serta sinkronisasi akhir ke software akuntansi **Accurate**.

---

## ℹ️ Informasi Pertemuan (Meeting Info)

| Item | Keterangan |
| :--- | :--- |
| **Tanggal & Waktu** | Minggu, 25 Oktober 2026 (Pagi) |
| **Agenda** | Presentasi & Demo Prototype Aplikasi Inventory Barcode Multi-Mitra |
| **Pihak Klien** | Owner, Tim Finance / Accounting, Tim Operasional Gudang |
| **Pihak Pengembang** | Tim Pengembang Aplikasi (WMS & Integrasi) |
| **Tujuan MoM** | Mencatat koreksi pemahaman proses bisnis, daftar permintaan perubahan fitur, dan *roadmap* perbaikan alur aplikasi |

---

## 🔄 Koreksi Pemahaman Bisnis (Business Logic Correction)

Beberapa asumsi awal pada saat pembuatan *prototype* disesuaikan dengan fakta operasional riil klien di lapangan:

| Topik | Asumsi di Prototype Awal | Fakta Riil dari Klien | Dampak ke Desain Aplikasi |
| :--- | :--- | :--- | :--- |
| **Struktur Gudang** | Banyak gudang terpisah | **1 Gudang Pusat** + **7 Mitra (Lapak Pasar)** | Stok persediaan fisik hanya dikelola & diaudit di 1 gudang pusat |
| **Status Mitra** | Pihak luar / konsinyasi titip barang | Mitra milik sendiri, dijaga karyawan sendiri | Tetap diperlakukan sebagai pembeli dengan harga khusus/berbeda |
| **Kepemilikan Barang** | Masih tercatat sebagai stok gudang | **Beli putus**; begitu keluar fisik langsung jadi piutang mitra | Stok gudang langsung berkurang; yang dilacak di mitra adalah **Piutang (Rp)**, bukan stok barang |
| **Alur Barang** | Gudang → Mutasi antar mitra | **Pemasok → Gudang (Pengendapan) → Mitra**; tidak ada alur mutasi antar mitra | Menu "Mutasi" dihapus/diganti menjadi **"Pengeluaran ke Mitra"** |
| **Satuan Keluar** | Per barcode / kemasan dus | Gudang keluar utuh per dus; mitra mengecer per kg secara manual | Penjualan eceran di lapak mitra tidak dicatat di sistem WMS (tetap manual pasar) |
| **Kebijakan Harga** | Harga flat / ditentukan di awal | Dinamis, berbeda per mitra, mengikuti inflasi & fluktuasi pasar | Wajib master harga bertingkat (Harga Beli Pemasok & Harga Jual Mitra) |
| **Pembayaran Mitra** | Lunas per transaksi | Setoran tunai/cash bertahap dan sering parsial (*"netes"*) | Wajib memiliki modul **AR Aging per Nota** |
| **Stock Opname** | Belum ada alur baku | Saat ini manual setahun sekali (saat Lebaran) | Klien menginginkan **opname rutin periodik + closing bulanan** |
| **Operasional** | Jam kerja kantor normal | Operasional **24 Jam**, shift siang & malam, perputaran sangat cepat | Input data harus super cepat via scanner genggam, minim ketik manual |

---

## 🛠️ Daftar Perbaikan Aplikasi per Modul

Daftar kebutuhan perbaikan dikelompokkan berdasarkan skala prioritas:
- **Prioritas P1:** Wajib selesai sebelum tahap uji coba lapangan (*pilot testing*).
- **Prioritas P2:** Harus selesai sebelum *Go-Live*.
- **Prioritas P3:** Disiapkan untuk fase rilis berikutnya (*enhancement*).

| Modul | Temuan & Permintaan Klien | Rencana Perbaikan Sistem | Prioritas |
| :--- | :--- | :--- | :---: |
| **Alur & Menu Utama** | Menu Mutasi tidak diperlukan; barang ke mitra murni penjualan putus | Mengganti menu "Mutasi" menjadi **Pengeluaran ke Mitra**. Scan barcode langsung memotong stok gudang utama dan otomatis membuat nota piutang | **P1** |
| **Pengeluaran Barang** | Saat demo, operator harus memilih pemasok dulu sebelum scan; barcode dari pemasok lain ditolak | Scan fleksibel langsung tanpa pilih pemasok (pemasok otomatis terbaca dari label barcode); 1 surat jalan pengeluaran bisa berisi barang campuran dari berbagai pemasok | **P1** |
| **Pengeluaran Barang** | Risiko human error salah pilih mitra penerima (kasus bon 5 dus, fisik terkirim 7 dus) | Konfirmasi mitra sebelum simpan; jumlah dus hasil scan wajib cocok dengan nota; otomatis tolak barcode yang belum ditimbang atau sudah berstatus KELUAR | **P1** |
| **Cetak Label Barcode** | Nama pemasok tidak boleh dicetak pada fisik label (kompetitor bisa tahu sumber pasokan) | Hapus teks nama pemasok dari cetakan label fisik; data pemasok tetap tersimpan aman di database sistem & master | **P1** |
| **Cetak Label Barcode** | Perlu informasi tanggal turun barang & kode partai pada label | Tampilkan tanggal masuk (dicetak otomatis saat barang datang) dan kode partai (contoh pola manual klien: `25-10 P10`) | **P1** |
| **Cetak Label Barcode** | Berat netto harus terbaca jelas dan kontras oleh orang gudang & pasar | Perbesar ukuran font angka netto (Kg); ukuran label disesuaikan untuk printer thermal kecil atau format A4 potong | **P2** |
| **Penerimaan & Bobot** | Netto sudah tertulis di dus dari supplier; netto aktual bisa berbeda per dus walau produk sama (mis. teri 10 kg / 12 kg) | Validasi & input berat netto per barcode dus secara catch-weight (bukan flat per produk); alur kerja: Cetak Label → Tempel ke Dus → Timbang/Validasi di hari yang sama | **P1** |
| **Master Produk SKU** | Estimasi ±300 item produk; cumi saja ada 5 varian kode; kode supplier kerap tidak konsisten | Format kode baku: `[Prefix Kategori] + [Nomor Urut]`, misal `TR` (teri), `UD` (udang), `IK` (ikan) + `001`; kode wajib sama persis dengan master SKU di Accurate | **P1** |
| **Master Produk SKU** | Daftar master produk panjang dan sulit dibaca saat di-scroll | Header tabel dibekukan (*sticky freeze header*) saat halaman digulir ke bawah | **P3** |
| **Master Tarif Harga** | Harga jual ke setiap mitra berbeda-beda dan bergerak dinamis | Menyediakan master harga beli pemasok dan master harga jual per mitra, lengkap dengan riwayat perubahan tarif | **P1** |
| **Analisa Margin Keuntungan** | Owner memerlukan visibilitas laba kotor dari setiap pengambilan barang oleh mitra | Laporan analisa margin: `Harga Jual Mitra − Harga Beli Pemasok` per mitra, per produk SKU, dan per periode tanggal | **P2** |
| **Surat Jalan / BAST** | Nilai nominal rupiah belum tercantum di bukti pengeluaran | Menambahkan nominal harga satuan, total bobot kg, dan total tagihan Rp di bagian bawah surat jalan dengan font tebal; siap cetak format printer rangkap 3 (*3-ply*) | **P1** |
| **Laporan Pengeluaran** | Diperlukan jejak audit mutasi barang ke masing-masing lapak mitra | Laporan rekapitulasi pengeluaran per mitra per periode (bulan/tahun), jenis komoditas, bobot kg, dan total nominal Rp, serta fitur ekspor Excel/CSV | **P2** |
| **Stock Opname Digital** | Stok fisik gudang vs data sistem harus cocok; klien sudah terbiasa dengan kartu stok fisik (*bin card*) | Modul stock opname digital dengan scanner genggam, deteksi selisih fisik vs sistem, serta fitur closing bulanan (saldo akhir bulan menjadi saldo awal bulan berikutnya) | **P2** |
| **Retur & Pembatalan** | Barang sudah terlanjur tercatat keluar di sistem namun fisik batal terkirim | Fitur void / batal transaksi pengeluaran yang secara otomatis mengembalikan status barang ke gudang dan mengoreksi nilai piutang mitra | **P2** |
| **Prioritas Stok Lama (FIFO)** | Barang lama sering kali tertimbun tumpukan dus baru di gudang | Menampilkan indikator umur simpan stok (*aging days*); sistem memberikan notifikasi/peringatan lunak jika terdapat stok lebih lama yang belum dikeluarkan (FIFO) | **P2** |
| **Manajemen User** | Tahap awal operasional cukup membedakan 2 level wewenang | Role **Operator** (timbang, scan, terbit SJ) dan **Owner** (akses penuh & master harga). Opsi portal login mandiri per mitra (*self-service*) disiapkan untuk fase lanjutan | **P1** |
| **Lokasi Rak Gudang** | Fasilitas gudang fisik direncanakan akan diperluas | Masterisasi lokasi rak / palet penyimpanan berbasis scan barcode lokasi | **P3** |

---

## 💼 Kebutuhan Finance, Accounting & Integrasi Accurate

Sisi operasional gudang (WMS) dinilai klien sudah sangat memadai. **Titik paling kritis keberhasilan sistem berada pada integrasi pembukuan**. 

> **Alur Arsitektur:**  
> Seluruh transaksi operasional diselesaikan terlebih dahulu di dalam WMS, kemudian data yang telah tervalidasi dikirim (*sync*) ke Accurate sebagai pencatatan final.

1. **AR Aging per Mitra (Piutang Usaha):** Setiap nota pengeluaran memiliki nilai total, riwayat setoran (bisa dicicil berkali-kali), dan sisa piutang.  
   *Contoh kasus:* Total belanja Rp20.000.000, setor tunai Rp12.000.000, sisa piutang Rp8.000.000 terpantau dengan umur jatuh tempo piutangnya.
2. **Alokasi Setoran Parsial:** Menentukan aturan pemotongan nota saat setoran borongan dilakukan mitra (misal setor Rp700.000 untuk melunasi sebagian dari 3 nota terpisah @Rp1.000.000).
3. **AP Aging Pemasok (Utang Usaha):** Pemantauan kewajiban tagihan pembayaran atas penerimaan barang dari supplier/pemasok.
4. **Konektivitas Purchasing, Inventory & P&L:** Ketiga pilar harus sinkron sehingga rekonsiliasi akhir bulan menghasilkan angka yang identik.
5. **Rekonsiliasi Bank:** Pencocokan setoran uang tunai dari lapak mitra dengan mutasi rekening bank perusahaan; target setoran tunai dikelola harian.
6. **Otorisasi & Approval Owner:** Pengeluaran dana pembelian barang ke vendor memerlukan otorisasi token bank owner; tim admin hanya menyiapkan draf invoice tagihan.
7. **Biaya Operasional Harian Gudang:** Pencatatan biaya lain-lain di luar modal barang (misal upah buruh bongkar muat harian).
8. **Kalkulasi Pajak (PPN):** Penyesuaian kalkulasi PPN jika diperlukan pelaporan faktur pajak formal.
9. **Standarisasi SKU Produk:** Master produk di WMS dan Accurate wajib menggunakan kode SKU yang sama persis.
10. **Kesiapan Endpoint API Accurate:** Menunggu kepastian ketersediaan endpoint API dari tim Accurate pusat untuk modul Produk, Stok, Penjualan, dan Piutang.

---

## ❓ Pertanyaan Terbuka & Konfirmasi Bisnis (Pending Items)

Beberapa poin hasil diskusi memerlukan keputusan final dari pihak manajemen klien sebelum penulisan kode modul akuntansi:

1. **Pemberlakuan Tarif Harga Baru:** Apakah kenaikan harga baru menunggu stok lama habis terlebih dahulu, atau seluruh stok fisik lama langsung mengikuti tarif harga baru?
2. **Perpindahan Barang Antar Lapak:** Klien menyebutkan bahwa antar-mitra terkadang saling membantu menjualkan barang yang kurang laku. Apakah proses ini wajib dicatat di sistem atau diselesaikan secara internal antar-lapak di luar aplikasi?
3. **Format Kode Produk SKU Final:** Apakah disepakati pola kategori 2 huruf + 3/4 digit nomor urut (`TR001`, `UD002`), atau menggunakan format campuran lain yang sempat disebut (`002-IKN`, `BONJO-SPR`)?
4. **Format Kode Partai pada Label:** Apakah menggunakan format manual klien (`[Tanggal]-[Bulan] P[Jumlah Dus]`, contoh: `25-10 P10`)?
5. **Metode Alokasi Setoran Pembayaran:** Jika mitra menyetor uang, apakah sistem otomatis melunasi nota tertua lebih dahulu (*FIFO payment*), atau kasir memilih nota secara manual?
6. **Periode Closing Buku:** Apakah disepakati siklus closing bulanan (rekomendasi tim pengembang) atau tetap tahunan saat hari raya Lebaran?
7. **Infrastruktur Hardware & Server:** Pemilihan server lokal (*tower PC* kantor) vs *cloud server*, serta pengadaan unit scanner barcode dan printer thermal tambahan.
8. **Akses API Accurate:** Menunggu detail kredensial dan endpoint sandbox/production dari tim accounting klien.

---

## 📅 Action Items & Timeline Target

**Target Penyelesaian Aplikasi Final:** Pertengahan November 2026 (Estimasi: **15 November 2026**)

| No | Rencana Tindak Lanjut (*Action Item*) | Penanggung Jawab (PIC) | Target Waktu |
| :-: | :--- | :--- | :--- |
| 1 | Mengubah alur mutasi menjadi Pengeluaran ke Mitra (beli putus + piutang) | Tim Pengembang | ±1 Hari |
| 2 | Menyiapkan instalasi aplikasi di server untuk uji coba (*pilot*) + buku panduan (*manual book*) | Tim Pengembang | Besok |
| 3 | Mengerjakan perbaikan prioritas P1 dan P2 pada modul WMS | Tim Pengembang | ±1 Minggu |
| 4 | Membedah detail kebutuhan accounting (AR/AP aging, rekon bank, P&L) bersama tim finance | Tim Pengembang + Finance Klien | Minggu Depan |
| 5 | Mengecek ketersediaan & dokumentasi endpoint API Accurate | Finance Klien + Tim Pengembang | Minggu Depan |
| 6 | Pengiriman data master final: master item, kode SKU, dan daftar harga beli/jual | Pihak Klien | Secepatnya |
| 7 | Rekap stok opname fisik terakhir (stok opname per 15 Oktober + mutasi berjalan) | Tim Gudang Klien | Secepatnya |
| 8 | Migrasi & asistensi upload data saldo awal ke database aplikasi | Tim Pengembang → Operator | Saat Data Diterima |
| 9 | Pengadaan & *setup* perangkat keras: PC Server, UPS, minimal 2 Barcode Scanner, Printer Label, dan Printer Surat Jalan | Pihak Klien | Sebelum Go-Live |
| 10 | Pelatihan (*training*) operator gudang dan kunjungan observasi lapangan | Tim Pengembang | Minggu Depan |
| 11 | Konfirmasi tertulis atas daftar pertanyaan terbuka | Pihak Klien | Sebelum Coding P1 Selesai |

---

## 📷 Dokumentasi Pertemuan

![Dokumentasi Meeting Review Aplikasi Inventory Barcode Multi-Mitra - 25 Oktober 2026](assets/img/mom_meeting_25_okt_2026.jpeg)  
*Foto bersama perwakilan Tim Pengembang Aplikasi dan Manajemen/Klien usai sesi presentasi demo prototype (25 Oktober 2026).*

