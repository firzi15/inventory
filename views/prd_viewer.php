<?php
/**
 * Panduan Cara Pakai: dokumentasi satu halaman untuk operator, finance, dan admin.
 * Isi mengikuti menu dan aturan sistem yang berlaku sekarang.
 */

$pageTitle = 'Panduan Cara Pakai';

require_once __DIR__ . '/layout/header.php';
?>

<style>
.pj { display: grid; grid-template-columns: 210px minmax(0, 1fr); gap: 56px; max-width: 1080px; margin: 0 auto; padding: 8px 4px 80px; }
.pj-toc { position: sticky; top: 16px; align-self: start; font-size: 13px; max-height: calc(100vh - 32px); overflow-y: auto; }
.pj-toc .pj-toc-head { margin: 18px 0 4px; font-weight: 700; color: var(--text-main); }
.pj-toc .pj-toc-head:first-child { margin-top: 0; }
.pj-toc a { display: block; padding: 3px 0; color: var(--text-muted); text-decoration: none; line-height: 1.4; }
.pj-toc a:hover { color: var(--text-main); text-decoration: underline; }
.pj-body { min-width: 0; max-width: 740px; color: var(--text-main); }
.pj-body h1 { font-size: 28px; line-height: 1.2; margin: 0 0 6px; letter-spacing: -0.01em; }
.pj-body .pj-lead { margin: 0 0 8px; color: var(--text-muted); font-size: 15px; line-height: 1.6; }
.pj-body .pj-meta { margin: 0 0 36px; color: var(--text-muted); font-size: 12.5px; }
.pj-body h2 { font-size: 21px; margin: 52px 0 12px; padding-top: 18px; border-top: 1px solid var(--border); scroll-margin-top: 16px; }
.pj-body h3 { font-size: 15.5px; margin: 26px 0 6px; scroll-margin-top: 16px; }
.pj-body p, .pj-body li { font-size: 14.5px; line-height: 1.72; }
.pj-body p { margin: 0 0 12px; }
.pj-body ol, .pj-body ul { margin: 0 0 14px; padding-left: 22px; }
.pj-body li { margin-bottom: 5px; }
.pj-body .pj-note { margin: 14px 0; padding: 1px 0 1px 14px; border-left: 3px solid var(--border); color: var(--text-muted); }
.pj-body .pj-note p { color: inherit; margin-bottom: 6px; }
.pj-body table { width: 100%; border-collapse: collapse; margin: 6px 0 18px; font-size: 13.5px; }
.pj-body th, .pj-body td { padding: 8px 12px 8px 0; border-bottom: 1px solid var(--border); text-align: left; vertical-align: top; line-height: 1.55; }
.pj-body th { font-weight: 700; color: var(--text-muted); font-size: 12.5px; }
.pj-body td:first-child { font-weight: 600; white-space: nowrap; padding-right: 20px; }
.pj-body .pj-menu { font-weight: 600; }
@media (max-width: 900px) {
    .pj { grid-template-columns: 1fr; gap: 24px; }
    .pj-toc { position: static; max-height: none; columns: 2; }
    .pj-toc .pj-toc-head { break-after: avoid; }
}
@media print {
    .pj { display: block; }
    .pj-toc { display: none; }
    .pj-body { max-width: none; }
}
</style>

<div class="pj">

<nav class="pj-toc no-print" aria-label="Daftar isi">
    <div class="pj-toc-head">Mulai</div>
    <a href="#alur">Alur kerja</a>
    <a href="#istilah">Istilah</a>
    <a href="#peran">Peran pengguna</a>
    <a href="#aturan">Aturan yang perlu diingat</a>

    <div class="pj-toc-head">Gudang</div>
    <a href="#master">Master data</a>
    <a href="#penerimaan">Penerimaan dan timbang</a>
    <a href="#riwayat-penerimaan">Riwayat penerimaan</a>
    <a href="#barang-datang">Barang datang</a>
    <a href="#stok">Stok dan opname</a>
    <a href="#pengeluaran">Pengeluaran ke mitra</a>

    <div class="pj-toc-head">Keuangan</div>
    <a href="#purchase-invoice">Purchase Invoice</a>
    <a href="#ap-aging">AP Aging</a>
    <a href="#sales-invoice">Sales Invoice</a>
    <a href="#ar-aging">AR Aging</a>
    <a href="#kas-bank">Transaksi Kas &amp; Bank</a>
    <a href="#tutup-periode">Tutup Periode</a>
    <a href="#rekonsiliasi">Rekonsiliasi Bank</a>
    <a href="#laba-rugi">Laba Rugi</a>
    <a href="#coa">COA</a>
    <a href="#book">Purchase Book dan Sales Book</a>

    <div class="pj-toc-head">Lainnya</div>
    <a href="#pembatalan">Pembatalan dan koreksi</a>
    <a href="#cetak-sj">Surat Jalan dan cetak</a>
    <a href="#ekspor">Ekspor dan filter</a>
    <a href="#masalah">Jika ada masalah</a>
</nav>

<article class="pj-body">

<h1>Panduan Cara Pakai</h1>
<p class="pj-lead">Cara memakai sistem dari barang datang sampai laporan keuangan. Nama menu ditulis persis seperti di menu navigasi. Sidebar tampil di sisi kiri. Klik tombol <strong>MENU</strong> di kiri atas untuk melipatnya agar konten memakai seluruh lebar layar, dan klik lagi untuk membukanya; pilihan itu diingat di browser. Di layar kecil, menu berupa laci yang dibuka dengan tombol yang sama.</p>
<p class="pj-meta">Diperbarui 14 November 2026</p>

<h2 id="alur" style="border-top: 0; padding-top: 0; margin-top: 0;">Alur kerja</h2>
<ol>
    <li><strong>Master data.</strong> Isi pemasok, harga beli per artikel, mitra, dan harga jual per artikel.</li>
    <li><strong>Penerimaan.</strong> Barang dari pemasok ditimbang per kemasan, barcode dicetak, lalu Surat Jalan penerimaan diselesaikan. Faktur pembelian terbit otomatis.</li>
    <li><strong>Bayar pemasok.</strong> Pembayaran dicatat dari Purchase Invoice, per Surat Jalan, dibayar penuh.</li>
    <li><strong>Pengeluaran.</strong> Scan barcode kemasan yang dikirim ke mitra, selesaikan, cetak Surat Jalan keluar. Nota penjualan terbit otomatis.</li>
    <li><strong>Setoran mitra.</strong> Mitra membayar nota, boleh dicicil. Dicatat dari Sales Invoice.</li>
    <li><strong>Rekonsiliasi Bank.</strong> Sekali sehari, total setoran hari itu dicocokkan dengan uang yang masuk di bank, dengan lampiran screenshot.</li>
    <li><strong>Laporan.</strong> Laba Rugi, COA, AP Aging, AR Aging, Purchase Book, Sales Book, dan Barang Datang.</li>
</ol>
<p>Faktur, nota, dan jurnal tidak diketik manual untuk barang. Semuanya dibuat sistem dari penerimaan dan pengeluaran, sehingga angka di laporan selalu berasal dari satu sumber.</p>

<h2 id="istilah">Istilah</h2>
<table>
    <tr><th>Istilah</th><th>Arti</th></tr>
    <tr><td>Pemasok</td><td>Pihak yang menjual barang ke kita. Kodenya diisi manual saat dibuat.</td></tr>
    <tr><td>Mitra</td><td>Pihak yang membeli barang dari gudang. Harga jualnya bisa berbeda untuk tiap mitra.</td></tr>
    <tr><td>Kemasan / box</td><td>Satu keranjang atau box fisik dengan satu barcode. Berat ditimbang per kemasan.</td></tr>
    <tr><td>Netto</td><td>Berat barang saja, bruto dikurangi tara.</td></tr>
    <tr><td>Surat Jalan (SJ) penerimaan</td><td>Satu kali kedatangan barang dari satu pemasok. Satu SJ menghasilkan satu faktur pembelian.</td></tr>
    <tr><td>Surat Jalan keluar</td><td>Satu kali pengiriman barang ke satu mitra. Nomornya menjadi nomor nota penjualan.</td></tr>
    <tr><td>Purchase Invoice</td><td>Faktur pembelian: tagihan dari pemasok atau vendor yang harus kita bayar.</td></tr>
    <tr><td>Sales Invoice</td><td>Nota penjualan: tagihan kita ke mitra.</td></tr>
    <tr><td>Setoran</td><td>Uang yang dibayarkan mitra untuk nota.</td></tr>
    <tr><td>HPP</td><td>Harga pokok barang yang terjual, memakai harga beli saat barang diterima.</td></tr>
</table>

<h2 id="peran">Peran pengguna</h2>
<table>
    <tr><th>Peran</th><th>Biasa mengerjakan</th></tr>
    <tr><td>Operator Inbound</td><td>Penerimaan dan timbang, cetak barcode dan Surat Jalan penerimaan.</td></tr>
    <tr><td>Operator Outbound</td><td>Scan Pengeluaran dan cetak Surat Jalan keluar.</td></tr>
    <tr><td>Finance</td><td>Purchase Invoice, pembayaran pemasok, setoran mitra, Rekonsiliasi Bank, laporan.</td></tr>
    <tr><td>Supervisor</td><td>Semua di atas, ditambah pembatalan dokumen.</td></tr>
    <tr><td>Admin</td><td>Semua, termasuk master data dan pengaturan.</td></tr>
</table>
<p>Hak yang dibatasi sistem:</p>
<table>
    <tr><th>Tindakan</th><th>Boleh dilakukan oleh</th></tr>
    <tr><td>Batalkan penerimaan atau pengeluaran</td><td>Admin, Supervisor</td></tr>
    <tr><td>Batalkan faktur manual, pembayaran pemasok, setoran mitra</td><td>Admin, Supervisor, Finance</td></tr>
    <tr><td>Rekonsiliasi Bank dan membukanya kembali</td><td>Admin, Supervisor, Finance</td></tr>
</table>

<h2 id="aturan">Aturan yang perlu diingat</h2>
<ul>
    <li><strong>Harga dibekukan saat transaksi.</strong> Harga beli dibekukan saat barang diterima, harga jual saat barang keluar. Mengubah master harga tidak mengubah transaksi yang sudah terjadi.</li>
    <li><strong>Pemasok dibayar penuh per Surat Jalan.</strong> Tidak ada cicilan ke pemasok.</li>
    <li><strong>Mitra boleh mencicil,</strong> tetapi total setoran tidak boleh melebihi sisa piutang mitra itu.</li>
    <li><strong>Kode dibuat otomatis</strong> untuk mitra, produk, kategori, vendor, dan tipe biaya. Hanya kode pemasok yang diisi manual.</li>
    <li><strong>Data master hanya bisa dihapus bila belum dipakai</strong> di menu lain. Kalau sudah dipakai, hapus dulu dokumen yang memakainya, atau nonaktifkan.</li>
    <li><strong>Dokumen tidak dihapus, tetapi dibatalkan</strong> dengan alasan. Keuangannya ikut dikoreksi. Lihat bagian Pembatalan dan koreksi.</li>
    <li><strong>Tanggal rekonsiliasi mengunci setoran.</strong> Setoran pada hari yang sudah direkonsiliasi tidak bisa dibatalkan sebelum rekonsiliasinya dibuka kembali.</li>
</ul>

<h2 id="master">Master data</h2>
<p>Buka <span class="pj-menu">Master &amp; Pengaturan</span>. Setiap tab punya menu sendiri di sidebar.</p>
<table>
    <tr><th>Menu</th><th>Fungsi</th></tr>
    <tr><td>Pemasok Nelayan</td><td>Daftar pemasok. Kode pemasok diisi manual dan harus unik.</td></tr>
    <tr><td>Vendor Non-Pemasok</td><td>Vendor untuk biaya dan aset, misalnya toko es atau PLN. Dipilih saat membuat faktur operasional.</td></tr>
    <tr><td>Tipe Biaya Operasional</td><td>Jenis biaya beserta akun COA, satuan, dan estimasi tarifnya. Memilih tipe biaya di faktur ikut mengisi akunnya.</td></tr>
    <tr><td>Harga Pemasok</td><td>Harga beli per kg untuk tiap artikel dari tiap pemasok.</td></tr>
    <tr><td>Harga Mitra</td><td>Harga jual per kg untuk tiap artikel ke tiap mitra.</td></tr>
    <tr><td>Mitra Bisnis</td><td>Daftar mitra pembeli.</td></tr>
    <tr><td>Master Produk</td><td>Artikel dan kategorinya (cumi, ikan, teri, udang, dan seterusnya).</td></tr>
    <tr><td>Rekening Bank</td><td>Nama bank, nomor rekening, dan atas nama. Kode dibuat otomatis (RB-01, RB-02). Dipilih pada Transaksi Kas &amp; Bank. Rekening yang sudah dipakai tidak bisa dihapus, hanya dinonaktifkan.</td></tr>
    <tr><td>Akun COA</td><td>Daftar akun buku besar. Lihat bagian COA.</td></tr>
    <tr><td>Log Harga</td><td>Riwayat setiap perubahan harga beli dan harga jual.</td></tr>
    <tr><td>Settingan API Accurate</td><td>Koneksi ke Accurate Online dan antrean pengirimannya.</td></tr>
</table>
<h3>Mengubah harga</h3>
<ol>
    <li>Buka Harga Pemasok atau Harga Mitra.</li>
    <li>Ubah angka pada artikel yang dimaksud.</li>
    <li>Isi kolom alasan perubahan bila perlu, lalu simpan.</li>
</ol>
<p>Hanya harga yang benar-benar berubah yang masuk ke Log Harga: harga lama, harga baru, selisih, siapa yang mengubah, dan alasannya.</p>
<div class="pj-note"><p>Harga beli artikel dari pemasok harus sudah ada di Harga Pemasok sebelum barangnya diterima. Tanpa itu, penerimaan ditolak.</p></div>

<h2 id="penerimaan">Penerimaan dan timbang</h2>
<p>Menu: <span class="pj-menu">Penerimaan Barang &gt; Penerimaan &amp; Timbang</span>.</p>
<ol>
    <li>Pilih pemasok. Nomor Surat Jalan terisi otomatis.</li>
    <li>Pilih kategori dan artikel.</li>
    <li>Timbang satu kemasan, masukkan berat bersih, lalu klik <strong>Tambah Box</strong>. Tiap box mendapat barcode sendiri.</li>
    <li>Cetak barcode box dengan <strong>Cetak Barcode</strong> dan tempel di kemasannya.</li>
    <li>Ulangi untuk semua box. Ringkasan Surat Jalan di samping menunjukkan jumlah box dan total netto.</li>
    <li>Setelah semua box masuk, klik <strong>Cetak Surat Jalan</strong>. Ini menyelesaikan Surat Jalan penerimaan.</li>
</ol>
<p>Saat Surat Jalan diselesaikan, sistem membuat satu Purchase Invoice untuk Surat Jalan itu. Nilainya memakai harga beli di Harga Pemasok pada saat itu, dan tidak berubah lagi sesudahnya. Stok masuk ke persediaan.</p>
<p>Kalau barcode ingin dicetak lebih dulu dan ditimbang kemudian, pakai <strong>Cetak Barcode Keranjang Baru</strong> di halaman yang sama, lalu timbang dengan cara scan.</p>

<h2 id="riwayat-penerimaan">Riwayat penerimaan</h2>
<p>Menu: <span class="pj-menu">Penerimaan Barang &gt; Riwayat Penerimaan</span>. Berisi seluruh Surat Jalan penerimaan, beserta barcode dan beratnya.</p>
<h3>Membatalkan penerimaan</h3>
<p>Klik <strong>Batalkan</strong> pada Surat Jalan, isi alasan. Kemasan menjadi VOID, faktur pembelian menjadi Batal, dan jurnalnya dibalik. Syaratnya:</p>
<ul>
    <li>Tidak ada pembayaran ke pemasok untuk Surat Jalan itu. Kalau ada, batalkan pembayarannya dulu.</li>
    <li>Tidak ada kemasan yang sudah dikeluarkan ke mitra. Kalau ada, batalkan pengeluarannya dulu.</li>
</ul>

<h2 id="barang-datang">Barang datang</h2>
<p>Menu: <span class="pj-menu">Penerimaan Barang &gt; Barang Datang</span>. Laporan barang yang diterima pada rentang tanggal, dengan total nilai pembelian.</p>
<ul>
    <li>Filter: tanggal, kategori, artikel, pemasok, status bayar (semua, belum lunas, lunas).</li>
    <li>Pilih <strong>Kelompokkan per</strong> pemasok, Surat Jalan, atau kategori.</li>
    <li>Setiap jenis barang diakhiri baris <strong>TOTAL</strong> (misalnya TOTAL CUMI). Di bawahnya ada TOTAL tiap jenis untuk seluruh data, lalu <strong>GRAND TOTAL</strong> untuk semua jenis.</li>
    <li>Tombol <strong>Box</strong> pada tiap baris membuka rincian per box: barcode, bruto, tara, netto, harga, dan nilai.</li>
</ul>
<p>Total laporan ini selalu sama dengan total Purchase Invoice stok.</p>

<h2 id="stok">Stok dan opname</h2>
<table>
    <tr><th>Menu</th><th>Fungsi</th></tr>
    <tr><td>Stok Barang Gudang &gt; Sisa Stok Gudang</td><td>Barang yang masih ada di gudang, difilter per tanggal, pemasok, dan artikel.</td></tr>
    <tr><td>Stok Barang Gudang &gt; Stock Opname</td><td>Mencocokkan barang fisik dengan data sistem. Kemasan yang belum di-scan berstatus Menunggu Scan Barcode.</td></tr>
    <tr><td>Saldo Stok Gudang</td><td>Rincian stok per mitra, matriks per kategori, dan nilai persediaan. Nilai dihitung dari harga beli yang dibekukan saat barang diterima (HPP), jadi tidak ikut berubah bila harga master diubah; estimasi nilai jual ditampilkan terpisah. Panel Kecocokan dengan Jurnal membandingkan nilai barang aktif dengan saldo akun 1200 dan menampilkan Surat Jalan yang nilainya tidak sama dengan fakturnya. Kartu Nilai Persediaan di Penerimaan &amp; Timbang menunjukkan nilai barang yang masih di gudang utama.</td></tr>
</table>

<h2 id="pengeluaran">Pengeluaran ke mitra</h2>
<p>Menu: <span class="pj-menu">Pengeluaran Barang ke Mitra &gt; Scan Pengeluaran</span>.</p>
<ol>
    <li>Pilih mitra penerima. Pemasok asal terisi otomatis dari barcode yang di-scan. Nomor Surat Jalan terisi otomatis.</li>
    <li>Scan barcode tiap kemasan yang dikirim. Daftar kemasan dan total netto bertambah di tabel.</li>
    <li>Periksa daftar, hapus baris yang salah scan bila ada.</li>
    <li>Klik <strong>Selesaikan Pengeluaran</strong>, lalu <strong>Cetak Surat Jalan</strong>.</li>
</ol>
<p>Surat Jalan keluar tidak menampilkan nama pemasok. Setelah pengeluaran selesai, sistem membuat nota penjualan ke mitra dengan harga jual dari Harga Mitra saat itu, dan mencatat HPP dari harga beli barang tersebut.</p>
<h3>Riwayat dan pembatalan</h3>
<p><span class="pj-menu">Riwayat Pengeluaran</span> menampilkan Surat Jalan keluar dalam dua tampilan: per Surat Jalan dan per barang. Pengeluaran bisa dibatalkan oleh Admin atau Supervisor dengan alasan, selama:</p>
<ul>
    <li>belum ada setoran mitra untuk nota itu (batalkan setorannya dulu), dan</li>
    <li>belum terkirim ke Accurate.</li>
</ul>
<p>Setelah dibatalkan, kemasan kembali ke stok gudang dan nota serta jurnalnya dibalik.</p>

<h2 id="purchase-invoice">Purchase Invoice</h2>
<p>Menu: <span class="pj-menu">Purchase Invoice &gt; Daftar Faktur</span>. Berisi dua jenis faktur.</p>
<ul>
    <li><strong>Faktur stok</strong> terbit otomatis dari penerimaan. Tidak diketik dan tidak bisa diubah.</li>
    <li><strong>Faktur operasional</strong> dibuat manual untuk biaya dan aset non-stok.</li>
</ul>
<h3>Membuat faktur operasional</h3>
<ol>
    <li>Klik <strong>Faktur Operasional</strong>.</li>
    <li>Pilih <strong>Akun</strong>. Pilihannya dikelompokkan Biaya dan Aset, diambil dari Akun COA yang aktif.</li>
    <li>Pilih vendor. Untuk vendor yang belum ada, pilih Vendor Lainnya dan ketik namanya.</li>
    <li>Isi tanggal faktur, lalu pilih termin (tunai, 7, 14, 30, 45, atau 60 hari). Jatuh tempo terisi otomatis dari tanggal faktur; bisa juga diketik sendiri.</li>
    <li>Isi baris item: deskripsi, qty, satuan, harga. Klik <strong>Tambah Baris</strong> bila ada lebih dari satu.</li>
    <li>Simpan Faktur.</li>
</ol>
<p>Akun yang dipilih langsung dipakai pada jurnal. Akun Kas, Persediaan, dan akun pendapatan tidak bisa dipilih di sini.</p>
<p>Faktur penerimaan barang terbit otomatis dengan jatuh tempo 7 hari dari tanggal faktur. Untuk mengubahnya, klik <strong>ubah</strong> di samping jatuh tempo faktur (Purchase Invoice) atau pada kolom Jatuh Tempo (AP Aging &gt; Surat Jalan Masuk). Yang boleh mengubah: Admin, Supervisor, dan Finance; perubahan tercatat di log audit.</p>
<h3>Membayar</h3>
<p>Klik <strong>Bayar</strong> pada faktur. Pembayaran untuk Surat Jalan pemasok harus melunasi seluruh sisa Surat Jalan yang dipilih; nominal tidak diketik sendiri.</p>

<h2 id="ap-aging">AP Aging</h2>
<p>Menu: <span class="pj-menu">AP Aging</span>. Hanya laporan, tidak ada input.</p>
<ul>
    <li><strong>Ringkasan per Pemasok:</strong> total tagihan, dibayar, sisa utang, dan pembagiannya menurut jatuh tempo: belum jatuh tempo, telat 1-7, 8-14, 15-30, dan lebih dari 30 hari.</li>
    <li><strong>Surat Jalan Masuk:</strong> tiap Surat Jalan dengan tanggal jatuh tempo dan keterangan sisa hari atau hari terlambat.</li>
    <li><strong>Riwayat Pembayaran:</strong> semua pembayaran ke pemasok. Pembayaran yang dibatalkan ditandai dan nominal aslinya dicoret.</li>
</ul>
<p>Semua input pembayaran dilakukan dari Purchase Invoice. Umur dihitung dari jatuh tempo, bukan dari tanggal barang masuk. Dokumen yang tidak punya jatuh tempo dianggap jatuh tempo di tanggal dokumennya.</p>

<h2 id="sales-invoice">Sales Invoice</h2>
<p>Menu: <span class="pj-menu">Sales Invoice &gt; Daftar Nota</span>. Nota terbit otomatis dari Pengeluaran.</p>
<p>Jatuh tempo nota ditentukan di layar Pengeluaran lewat pilihan <strong>Jatuh Tempo Nota</strong> (tunai, 7, 14, 30, 45, atau 60 hari dari tanggal nota; isian awal 14 hari). Nota lama yang dibuat sebelum fitur ini dianggap jatuh tempo di tanggal notanya. Untuk mengubahnya, klik <strong>ubah</strong> pada kolom Jatuh Tempo di AR Aging &gt; Nota (Admin, Supervisor, Finance).</p>
<h3>Mencatat setoran mitra</h3>
<ol>
    <li>Klik <strong>Catat Setoran</strong> (atau tombol setoran pada nota).</li>
    <li>Pilih mitra, tanggal, dan metode (tunai atau transfer bank).</li>
    <li>Isi jumlah. Boleh sebagian. Sistem menolak jumlah yang melebihi total sisa piutang mitra itu.</li>
    <li>Pilih pembagian: otomatis ke nota tertua lebih dulu, atau manual per nota.</li>
    <li>Simpan.</li>
</ol>

<h2 id="ar-aging">AR Aging</h2>
<p>Menu: <span class="pj-menu">AR Aging</span>. Hanya laporan.</p>
<ul>
    <li><strong>Ringkasan per Mitra:</strong> total belanja, total setoran, sisa piutang, dan pembagiannya menurut jatuh tempo (belum jatuh tempo, telat 1-7, 8-14, 15-30, lebih dari 30 hari).</li>
    <li><strong>Nota:</strong> tiap nota dengan jatuh tempo dan keterangan sisa hari atau hari terlambat.</li>
    <li><strong>Riwayat Setoran:</strong> semua setoran mitra, termasuk yang dibatalkan.</li>
</ul>

<h2 id="kas-bank">Transaksi Kas &amp; Bank</h2>
<p>Menu: <span class="pj-menu">Transaksi Kas &amp; Bank</span>. Dipakai untuk uang yang keluar atau masuk di luar faktur pemasok dan nota mitra. Pembayaran faktur pemasok dan setoran mitra tetap dicatat di menu masing-masing, jangan dicatat lagi di sini.</p>
<table>
    <tr><th>Jenis</th><th>Kas</th><th>Akun lawan</th><th>Masuk Laba Rugi</th></tr>
    <tr><td>Biaya</td><td>Berkurang</td><td>Akun biaya (kelompok 6000 ke atas)</td><td>Ya, sebagai biaya operasional</td></tr>
    <tr><td>Setoran Modal</td><td>Bertambah</td><td>Akun modal (3000)</td><td>Tidak</td></tr>
    <tr><td>Prive</td><td>Berkurang</td><td>Prive / Penarikan Pemilik (3200)</td><td>Tidak</td></tr>
</table>
<ol>
    <li>Klik <strong>Catat Transaksi</strong>, pilih jenis dan tanggal (tidak boleh di masa depan).</li>
    <li>Pilih akun lawan, isi nominal dan keterangan. No. referensi boleh diisi dengan nomor nota atau bukti transfer.</li>
    <li>Pilih cara: tunai, atau transfer bank. Untuk transfer, pilih rekening yang sudah ada di Master &gt; Rekening Bank.</li>
    <li>Simpan. Nomor bukti dibuat otomatis (KAS-tanggal-nomor) dan jurnal terbit saat itu juga.</li>
</ol>
<p>Kartu <strong>Saldo Kas &amp; Bank</strong> menunjukkan saldo akun 1000 menurut jurnal. Sistem mulai dari nol, jadi saldonya hanya mencerminkan transaksi sejak sistem dipakai. Untuk menyamakannya dengan saldo bank sebenarnya, catat selisih awalnya sebagai Setoran Modal.</p>
<p>Transaksi salah tidak dihapus. Klik <strong>Batalkan</strong>, isi alasan, dan jurnalnya dibalik. Baris tetap tampil dengan status Dibatalkan. Yang boleh mencatat dan membatalkan: Admin, Supervisor, dan Finance.</p>

<h2 id="tutup-periode">Tutup Periode</h2>
<p>Menu: <span class="pj-menu">Laba Rugi &gt; Tutup Periode</span>. Dipakai setiap akhir bulan setelah angkanya dicek, supaya laporan bulan itu tidak berubah lagi.</p>
<ol>
    <li>Pastikan semua hari bersetoran bulan itu sudah direkonsiliasi, dan semua faktur dan kas bulan itu sudah tercatat.</li>
    <li>Klik <strong>Tutup periode</strong> pada bulan yang dimaksud. Sistem menampilkan hasil pemeriksaan: jurnal seimbang, rekonsiliasi harian, dan kecocokan persediaan dengan jurnal.</li>
    <li>Pemeriksaan bertanda silang menghalangi penutupan. Yang bertanda seru hanya peringatan; sebaiknya dibereskan dulu.</li>
    <li>Isi catatan bila perlu, lalu konfirmasi. Penjualan, HPP, biaya, laba bersih, dan saldo kas, piutang, persediaan, dan utang akhir bulan disimpan.</li>
</ol>
<p>Hanya bulan yang sudah lewat yang bisa ditutup, dan hanya oleh Admin atau Finance. Setelah ditutup, faktur, pembayaran, setoran, transaksi kas, dan rekonsiliasi bertanggal di bulan itu ditolak, dan dokumen bertanggal di bulan itu tidak bisa dibatalkan. Transaksi bulan berjalan tidak terpengaruh.</p>
<p>Untuk koreksi, Admin klik <strong>Buka kembali</strong>, mengisi alasan (tercatat di log audit), melakukan koreksi, lalu menutup periodenya lagi. Pembatalan tetap dicatat pada tanggal pembatalannya, jadi koreksinya muncul di bulan berjalan dan angka bulan yang sudah ditutup tidak bergeser.</p>

<h2 id="rekonsiliasi">Rekonsiliasi Bank</h2>
<p>Menu: <span class="pj-menu">Rekonsiliasi Bank</span>. Dilakukan sekali sehari untuk mengaudit uang masuk pada hari itu.</p>
<p>Halaman menampilkan satu baris per hari yang punya setoran: jumlah setoran, total tunai, total transfer, dan total menurut sistem.</p>
<ol>
    <li>Klik <strong>Rekonsiliasi</strong> pada tanggal yang akan diaudit. Rincian setoran hari itu tampil per mitra.</li>
    <li>Isi <strong>Total uang masuk di bank</strong> sesuai mutasi atau screenshot bank.</li>
    <li>Lampirkan satu atau lebih screenshot atau slip bank (JPG, PNG, WEBP, atau PDF, maksimal 8 MB per file).</li>
    <li>Jika angkanya berbeda dari total sistem, isi catatan penyebabnya.</li>
    <li>Simpan.</li>
</ol>
<table>
    <tr><th>Status</th><th>Arti</th></tr>
    <tr><td>Belum direkonsiliasi</td><td>Hari itu belum diaudit.</td></tr>
    <tr><td>Sesuai</td><td>Total bank sama dengan total sistem.</td></tr>
    <tr><td>Selisih</td><td>Total bank berbeda. Selisih dan catatannya tersimpan.</td></tr>
    <tr><td>Setoran berubah</td><td>Setelah direkonsiliasi, total setoran hari itu berubah (misalnya ada setoran baru). Audit ulang.</td></tr>
</table>
<p>Setoran pada hari yang sudah direkonsiliasi terkunci. Untuk mengubah atau membatalkannya, buka <strong>Lihat</strong> pada tanggal itu, klik <strong>Buka kembali</strong>, isi alasan, lalu lakukan perubahan dan rekonsiliasi ulang. Pembukaan kembali tercatat di log audit.</p>

<h2 id="laba-rugi">Laba Rugi</h2>
<p>Menu: <span class="pj-menu">Laba Rugi &gt; Laba Rugi</span>. Semua angka dibaca dari jurnal dengan dasar akrual: penjualan diakui saat barang keluar, bukan saat uang masuk.</p>
<ul>
    <li>Tabel Januari sampai Desember plus total tahun: Penjualan, HPP, Laba Kotor, Biaya Operasional, Laba Bersih, dan marginnya.</li>
    <li>Di bawah tiap kelompok ada baris per akun COA. Klik angka akun untuk melihat mutasinya pada bulan itu.</li>
    <li>Bulan yang dipilih dibandingkan dengan bulan sebelumnya.</li>
    <li>Rincian penjualan dan HPP per mitra, serta biaya per vendor.</li>
    <li>Baris pemeriksaan di bawah tabel membandingkan laba bersih dengan saldo akun di COA. Seharusnya selalu tertulis Cocok.</li>
</ul>
<p><span class="pj-menu">Laba per Surat Jalan</span> menampilkan penjualan, HPP, laba, dan margin tiap Surat Jalan keluar.</p>
<div class="pj-note"><p>Pembatalan dicatat pada tanggal pembatalan, bukan tanggal dokumen aslinya. Penjualan yang dibatalkan di bulan berikutnya mengoreksi bulan berikutnya itu.</p></div>

<h2 id="coa">COA</h2>
<p>Menu: <span class="pj-menu">Laba Rugi &gt; COA</span> untuk melihat saldo, dan <span class="pj-menu">Master &amp; Pengaturan &gt; Akun COA</span> untuk mengelola akun.</p>
<ul>
    <li>Saldo setiap akun dihitung dari jurnal. Klik kode akun untuk melihat mutasinya.</li>
    <li>Di bawah tabel ada pemeriksaan jurnal seimbang: total debit sama dengan total kredit.</li>
    <li>Ada delapan akun inti: 1000 Kas &amp; Bank, 1100 Piutang &amp; Aset Lainnya, 1200 Inventory, 2000 Utang, 3000 Modal, 4000 Penjualan, 5000 HPP, 6000 Biaya Operasional. Akun inti dipakai posting otomatis, tidak bisa dihapus atau dinonaktifkan.</li>
    <li>Sub-akun ditambah dengan tombol tambah pada akun induknya. Kode 4 digit di rentang kelompoknya, misalnya Beban 6000 sampai 9999.</li>
    <li>Akun yang sudah dipakai di jurnal atau faktur tidak bisa dihapus, hanya dinonaktifkan.</li>
</ul>

<h2 id="book">Purchase Book dan Sales Book</h2>
<p>Menu: <span class="pj-menu">Purchase Invoice &gt; Purchase Book</span> dan <span class="pj-menu">Sales Invoice &gt; Sales Book</span>. Dipakai untuk membandingkan harga dari waktu ke waktu.</p>
<ul>
    <li>Purchase Book: harga beli per artikel dari tiap pemasok. Sales Book: harga jual per artikel ke tiap mitra, beserta HPP dan margin.</li>
    <li>Tiap baris menampilkan selisih harga terhadap transaksi sebelumnya untuk artikel dan pihak yang sama.</li>
    <li>Pilih satu artikel untuk melihat grafik tren harga dan tabel perbandingan antar pemasok atau mitra.</li>
    <li><strong>Urutkan</strong> berdasarkan tanggal, pemasok atau mitra, harga, atau nama artikel, naik atau turun. Urutan per pemasok, mitra, atau artikel menampilkan baris TOTAL tiap kelompok, dan semuanya diakhiri GRAND TOTAL.</li>
    <li>Di bagian bawah ada log perubahan harga master untuk filter yang sama.</li>
</ul>

<h2 id="pembatalan">Pembatalan dan koreksi</h2>
<p>Dokumen yang sudah terbit tidak diedit atau dihapus. Kesalahan dikoreksi dengan pembatalan, yang membuat jurnal pembalik. Alasan wajib diisi dan tercatat.</p>
<table>
    <tr><th>Yang dibatalkan</th><th>Di mana</th><th>Syarat</th></tr>
    <tr><td>Penerimaan</td><td>Riwayat Penerimaan</td><td>Tidak ada pembayaran pemasok dan tidak ada kemasan yang sudah keluar.</td></tr>
    <tr><td>Pengeluaran</td><td>Riwayat Pengeluaran</td><td>Tidak ada setoran mitra untuk nota itu dan belum terkirim ke Accurate.</td></tr>
    <tr><td>Faktur operasional</td><td>Daftar Faktur</td><td>Belum ada pembayaran. Faktur stok dibatalkan lewat Riwayat Penerimaan.</td></tr>
    <tr><td>Pembayaran pemasok</td><td>Rincian faktur (daftar pembayarannya)</td><td>Tidak ada syarat tambahan.</td></tr>
    <tr><td>Setoran mitra</td><td>Riwayat setoran pada nota (Daftar Nota)</td><td>Hari setorannya belum direkonsiliasi, atau rekonsiliasinya sudah dibuka kembali.</td></tr>
    <tr><td>Transaksi kas</td><td>Transaksi Kas &amp; Bank</td><td>Tidak ada syarat tambahan.</td></tr>
</table>
<p>Semua pembatalan ditolak bila tanggal dokumennya jatuh di bulan yang sudah ditutup (lihat Tutup Periode).</p>
<p>Urutan membatalkan mengikuti arah sebaliknya: setoran atau pembayaran dibatalkan lebih dulu, baru dokumen di bawahnya (pengeluaran atau penerimaan).</p>

<h2 id="cetak-sj">Mencetak surat jalan</h2>
<p>Menu: <span class="pj-menu">Surat Jalan</span>. Dua tab: Pengeluaran ke Mitra dan Penerimaan dari Pemasok. Daftar bisa difilter menurut tanggal, mitra atau pemasok, status, dan kata kunci, lalu dicetak atau dicetak ulang kapan saja. Surat jalan yang dibatalkan tetap tampil tetapi tidak bisa dicetak. Tombol <strong>Tabel</strong> dan <strong>Grid</strong> di kanan atas mengganti tampilan daftar menjadi baris tabel atau kartu; pilihannya diingat di browser itu. Ekspor Excel dan PDF tetap memuat semua baris di kedua tampilan.</p>
<p>Tombol <strong>Preview PDF</strong> membuka pratinjau surat jalan seukuran kertas 8&frac12; x 11 inci, persis seperti hasil cetak. Pada pengeluaran, pratinjau bisa berpindah antara Form (tanpa harga) dan Versi Internal bagi yang berhak. Dari pratinjau, klik <strong>Cetak / Simpan PDF</strong>, lalu pilih &quot;Simpan sebagai PDF&quot; sebagai tujuan untuk mendapatkan file PDF. Pada tampilan Grid, tiap kartu menampilkan thumbnail dokumennya; klik thumbnail untuk membuka pratinjau.</p>
<p>Daftar dibagi per halaman (tabel 25 baris, grid 12 kartu; bisa diganti lewat Per halaman di bawah daftar). Pada tampilan Tabel, klik judul kolom untuk mengurutkan seluruh hasil, klik lagi untuk membalik arah; pada tampilan Grid, urutan dipilih lewat Urutkan dan Arah di bagian filter. Ekspor Excel dan PDF memuat baris pada halaman yang tampil; untuk mengekspor lebih banyak, pilih 100 atau 500 baris per halaman.</p>
<table>
    <tr><th>Tombol</th><th>Isi</th><th>Untuk</th></tr>
    <tr><td>Cetak Form (rangkap 3)</td><td>Tanpa harga: kemasan, netto, asal surat jalan, dan kolom tanda tangan. Satu halaman per dokumen pendek.</td><td>Form bersambung rangkap 3. Satu kali cetak tembus ke semua lapis, jadi cetak satu kali saja.</td></tr>
    <tr><td>Versi Internal</td><td>Lengkap dengan harga, subtotal, dan total piutang mitra.</td><td>Arsip kita di kertas biasa. Hanya tampil untuk Admin, Supervisor, dan Finance; operator tidak melihat tombol maupun kolom nilai.</td></tr>
</table>
<p>Layar Pengeluaran langsung membuka versi form (tanpa harga) setelah barang dikeluarkan. Surat Jalan Penerimaan tidak memuat harga dan hanya punya satu versi cetak.</p>
<p>Kertas yang dipakai adalah form bersambung 9&frac12; x 11 inci. Setelah strip berlubang dirobek, kertasnya 8&frac12; x 11 inci, dan itulah ukuran halaman yang dipakai sistem. Dokumen panjang berlanjut ke lembar berikutnya dengan judul tabel berulang.</p>
<h3>Dialog cetak browser (Chrome)</h3>
<table>
    <tr><th>Pengaturan</th><th>Nilai</th></tr>
    <tr><td>Tujuan</td><td>Printer form bersambung (bukan Simpan sebagai PDF)</td></tr>
    <tr><td>Salinan</td><td>1</td></tr>
    <tr><td>Ukuran kertas</td><td>Letter Fanfold 8 1/2 x 11 in</td></tr>
    <tr><td>Halaman per lembar</td><td>1</td></tr>
    <tr><td>Margin</td><td>Default</td></tr>
    <tr><td>Skala</td><td>Kustom, 100</td></tr>
    <tr><td>Header dan footer</td><td>Tidak dicentang</td></tr>
    <tr><td>Grafis latar belakang</td><td>Tidak perlu</td></tr>
</table>
<h3>Pengaturan komputer dan printer</h3>
<ol>
    <li>Windows: Settings &gt; Printers &amp; scanners &gt; pilih printer &gt; Printing preferences. Pilih ukuran kertas <strong>Letter Fanfold 8 1/2 x 11 in</strong>, sumber kertas tractor atau continuous, orientasi Portrait.</li>
    <li>Di properti yang sama, cari pengaturan ketebalan kertas atau multi-part dan pilih tebal bila memakai form rangkap, supaya hasil tembus.</li>
    <li>Jangan mengaktifkan &quot;page length&quot; lain selain 11 inci. Panjang form di driver, di panel printer, dan di kertas harus sama, yaitu 11 inci.</li>
    <li>Di printer dot matrix, pasang form dengan tepi atas sejajar kepala cetak, lalu atur posisi Top of Form dari panel printer (tombol Tear Off/Micro Feed) sebelum mencetak pertama kali. Jangan menggeser kertas dengan tangan sesudahnya.</li>
    <li>Cetak satu lembar uji. Bila tulisan terlalu mepet ke atas atau kiri, beri tahu agar margin halaman disesuaikan.</li>
</ol>
<div class="pj-note"><p>Bila satu dokumen pendek memakai dua form, penyebabnya hampir selalu panjang halaman di printer atau driver tidak 11 inci, atau posisi Top of Form bergeser. Pratinjau Chrome menampilkan &quot;1 lembar kertas&quot; untuk dokumen satu halaman.</p></div>

<h2 id="ekspor">Ekspor dan filter</h2>
<ul>
    <li>Halaman laporan punya tombol <strong>Excel</strong> dan <strong>PDF</strong>. Excel membuat satu sheet per tabel di halaman, dengan nilai Rupiah sebagai angka. PDF membuka dialog cetak; pilih Simpan sebagai PDF.</li>
    <li>Yang diekspor hanya yang tampil: baris yang tersaring tidak ikut, dan kolom Aksi tidak disertakan.</li>
    <li>Semua pilihan dropdown bisa dicari dengan mengetik jika daftarnya panjang. Panah atas/bawah dan Enter bekerja di dalam dropdown.</li>
</ul>

<h2 id="masalah">Jika ada masalah</h2>
<table>
    <tr><th>Gejala</th><th>Penyebab dan tindakan</th></tr>
    <tr><td>Penerimaan ditolak karena harga</td><td>Harga beli artikel itu dari pemasok belum diisi. Isi di Master &gt; Harga Pemasok.</td></tr>
    <tr><td>Tidak bisa membatalkan penerimaan</td><td>Masih ada pembayaran pemasok atau kemasan yang sudah keluar. Batalkan dulu yang di bawahnya.</td></tr>
    <tr><td>Tidak bisa membatalkan setoran</td><td>Hari setoran itu sudah direkonsiliasi. Buka kembali rekonsiliasinya.</td></tr>
    <tr><td>Setoran ditolak</td><td>Jumlahnya melebihi sisa piutang mitra.</td></tr>
    <tr><td>Tidak bisa menghapus master</td><td>Masih dipakai di menu lain. Pesan error menyebutkan di mana. Hapus atau batalkan dokumen itu dulu, atau nonaktifkan.</td></tr>
    <tr><td>Laba Rugi tertulis Tidak cocok dengan COA</td><td>Ada jurnal yang tidak terbaca di salah satu sisi. Hubungi admin dan jangan dipakai untuk laporan sebelum dicek.</td></tr>
    <tr><td>Rekonsiliasi tertulis Setoran berubah</td><td>Ada setoran baru atau perubahan setelah hari itu diaudit. Buka kembali dan rekonsiliasi ulang.</td></tr>
</table>

</article>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
