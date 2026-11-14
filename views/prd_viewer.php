<?php
/**
 * Panduan Cara Pakai: dokumentasi satu halaman untuk operator, finance, dan admin.
 * Isi mengikuti menu dan aturan sistem yang berlaku sekarang. Dilengkapi pencarian dan bagan alur kerja.
 */

$pageTitle = 'Panduan Cara Pakai';

require_once __DIR__ . '/layout/header.php';
?>

<style>
.pj { display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 56px; max-width: 1080px; margin: 0 auto; padding: 8px 4px 80px; }
.pj-toc { position: sticky; top: 16px; align-self: start; font-size: 13px; max-height: calc(100vh - 32px); overflow-y: auto; overscroll-behavior: contain; padding-right: 8px; }
.pj-toc .pj-toc-head { margin: 18px 0 4px; font-weight: 700; color: var(--text-main); }
.pj-toc .pj-toc-head.pj-first { margin-top: 0; }
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

/* Pencarian */
.pj-cari { position: relative; margin: 0; }
.pj-cari input { width: 100%; padding: 8px 30px 8px 32px; border: 1.5px solid var(--border); border-radius: 8px; background: var(--bg-card); color: var(--text-main); font: inherit; font-size: 13px; }
.pj-cari input:focus { outline: none; border-color: var(--aktif-fg); }
.pj-cari .pj-cari-ikon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; display: flex; }
.pj-cari button { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); border: 0; background: none; color: var(--text-muted); cursor: pointer; font-size: 15px; line-height: 1; padding: 4px; display: none; }
.pj-cari-info { margin: 6px 0 14px; font-size: 12px; color: var(--text-muted); min-height: 16px; }
.pj-cari-kosong { display: none; margin: 40px 0; color: var(--text-muted); font-size: 14.5px; }
.pj-body mark.pj-hit { background: var(--warn-bg); color: inherit; border-radius: 2px; padding: 0 1px; }
.pj-sec.pj-sembunyi, .pj-intro.pj-sembunyi { display: none; }
.pj-searching .pj-sec h2 { margin-top: 28px; }

/* Bagan alur kerja */
.pj-fig { margin: 18px 0 22px; padding: 14px 16px 12px; border: 1px solid var(--border); border-radius: 10px; background: var(--bg-muted); }
.pj-fig .pj-fig-judul { margin: 0 0 12px; font-size: 12.5px; font-weight: 700; color: var(--text-muted); letter-spacing: .02em; text-transform: uppercase; }
.pj-fig .pj-lane-t { margin: 12px 0 6px; font-size: 12px; font-weight: 700; color: var(--text-main); }
.pj-fig .pj-lane-t:first-of-type { margin-top: 0; }
.pj-body .pj-flow { list-style: none; counter-reset: langkah; display: grid; grid-template-columns: repeat(var(--n, 4), minmax(0, 1fr)); gap: 24px; margin: 0 0 6px; padding: 0; }
.pj-body .pj-flow li { position: relative; margin: 0; padding: 9px 10px 9px 10px; border: 1.5px solid var(--border); border-radius: 8px; background: var(--bg-card); font-size: 12.5px; line-height: 1.4; counter-increment: langkah; min-width: 0; overflow-wrap: anywhere; }
.pj-body .pj-flow li::before { content: counter(langkah); display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; margin-bottom: 5px; border-radius: 50%; background: var(--aktif-bg); color: var(--aktif-fg); font-size: 11px; font-weight: 800; }
.pj-body .pj-flow li:not(:last-child)::after { content: "\2192"; position: absolute; right: -19px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 16px; font-weight: 700; }
.pj-body .pj-flow li.sistem { border-style: dashed; background: transparent; }
.pj-body .pj-flow li.sistem::before { background: var(--bg-muted); color: var(--text-muted); border: 1px dashed var(--border); }
.pj-body .pj-flow li strong { display: block; font-size: 12.5px; }
.pj-body .pj-flow li small { display: block; margin-top: 3px; color: var(--text-muted); font-size: 11.5px; line-height: 1.4; }
.pj-fig .pj-legend { margin: 8px 0 0; font-size: 11.5px; color: var(--text-muted); }

@media (max-width: 900px) {
    .pj { grid-template-columns: 1fr; gap: 24px; }
    .pj-toc { position: static; max-height: none; columns: 2; }
    .pj-toc .pj-toc-head { break-after: avoid; }
    .pj-toc .pj-cari, .pj-toc .pj-cari-info { column-span: all; }
}
@media (max-width: 700px) {
    .pj-body .pj-flow { grid-template-columns: 1fr; gap: 20px; }
    .pj-body .pj-flow li:not(:last-child)::after { content: "\2193"; right: auto; left: 50%; top: auto; bottom: -19px; transform: translateX(-50%); }
}
@media print {
    .pj { display: block; }
    .pj-toc { display: none; }
    .pj-body { max-width: none; }
    .pj-fig { break-inside: avoid; }
}
</style>

<div class="pj">

<nav class="pj-toc no-print" aria-label="Daftar isi">
    <div class="pj-cari">
        <span class="pj-cari-ikon"><i data-lucide="search" style="width: 15px; height: 15px;"></i></span>
        <input type="search" id="pjCari" placeholder="Cari di panduan..." autocomplete="off" aria-label="Cari di panduan">
        <button type="button" id="pjCariHapus" aria-label="Hapus pencarian">&#10005;</button>
    </div>
    <p class="pj-cari-info" id="pjCariInfo" aria-live="polite"></p>

    <div class="pj-toc-head pj-first">Mulai</div>
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
    <a href="#purchase-invoice">Payment Out</a>
    <a href="#ap-aging">AP Aging</a>
    <a href="#sales-invoice">Payment In</a>
    <a href="#ar-aging">AR Aging</a>
    <a href="#kas-bank">Transaksi Kas &amp; Bank</a>
    <a href="#rekonsiliasi">Rekonsiliasi Bank</a>
    <a href="#tutup-harian">Tutup Harian</a>
    <a href="#laba-rugi">Laba Rugi</a>
    <a href="#coa">COA</a>
    <a href="#book">Purchase Book</a>

    <div class="pj-toc-head">Lainnya</div>
    <a href="#pembatalan">Pembatalan dan koreksi</a>
    <a href="#cetak-sj">Surat Jalan dan cetak</a>
    <a href="#backup">Backup dan pemulihan</a>
    <a href="#ekspor">Filter, ekspor, dan tampilan</a>
    <a href="#masalah">Jika ada masalah</a>
</nav>

<article class="pj-body" id="pjBody">

<div class="pj-intro" id="pjIntro">
<h1>Panduan Cara Pakai</h1>
<p class="pj-lead">Cara memakai sistem dari barang datang sampai laporan keuangan. Nama menu ditulis persis seperti di menu navigasi. Sidebar tampil di sisi kiri. Klik tombol <strong>MENU</strong> di kiri atas untuk melipatnya agar konten memakai seluruh lebar layar, dan klik lagi untuk membukanya; pilihan itu diingat di browser. Di layar kecil, menu berupa laci yang dibuka dengan tombol yang sama. Pakai kolom <strong>Cari di panduan</strong> di sisi kiri (atau tekan tombol <strong>/</strong>) untuk menemukan topik dengan cepat.</p>
<p class="pj-meta">Diperbarui 14 November 2026</p>
</div>

<h2 id="alur" style="border-top: 0; padding-top: 0; margin-top: 0;">Alur kerja</h2>
<ol>
    <li><strong>Master data.</strong> Isi pemasok, harga beli per artikel, mitra, dan harga jual per artikel.</li>
    <li><strong>Penerimaan.</strong> Barang dari pemasok ditimbang per kemasan, barcode dicetak, lalu Surat Jalan penerimaan diselesaikan. Faktur pembelian terbit otomatis.</li>
    <li><strong>Bayar pemasok.</strong> Pembayaran dicatat dari Payment Out, per Surat Jalan, dibayar penuh.</li>
    <li><strong>Pengeluaran.</strong> Scan barcode kemasan yang dikirim ke mitra, selesaikan, cetak Surat Jalan keluar. Nota penjualan terbit otomatis.</li>
    <li><strong>Setoran mitra.</strong> Mitra membayar nota, boleh dicicil. Dicatat dari Payment In.</li>
    <li><strong>Rekonsiliasi Bank.</strong> Sekali sehari, total setoran hari itu dicocokkan dengan uang yang masuk di bank, dengan lampiran screenshot.</li>
    <li><strong>Tutup Harian.</strong> Setelah angkanya dicek, hari dikunci supaya tidak berubah lagi.</li>
    <li><strong>Laporan.</strong> Laba Rugi, COA, AP Aging, AR Aging, Purchase Book, dan Barang Datang.</li>
</ol>
<p>Faktur, nota, dan jurnal tidak diketik manual untuk barang. Semuanya dibuat sistem dari penerimaan dan pengeluaran, sehingga angka di laporan selalu berasal dari satu sumber.</p>

<figure class="pj-fig">
    <p class="pj-fig-judul">Bagan: alur kerja dari master data sampai laporan</p>
    <p class="pj-lane-t">Barang masuk dan pembayaran pemasok</p>
    <ol class="pj-flow" style="--n: 4;">
        <li><strong>Master data</strong><small>Pemasok, harga beli, mitra, harga jual</small></li>
        <li><strong>Penerimaan &amp; Timbang</strong><small>Timbang tiap box, cetak barcode, selesaikan Surat Jalan</small></li>
        <li class="sistem"><strong>Faktur pembelian + stok masuk</strong><small>Dibuat sistem, harga beli dibekukan</small></li>
        <li><strong>Bayar pemasok</strong><small>Dari Payment Out, lunas per Surat Jalan</small></li>
    </ol>
    <p class="pj-lane-t">Barang keluar, setoran, dan penutupan</p>
    <ol class="pj-flow" style="--n: 5;">
        <li><strong>Pengeluaran</strong><small>Pilih mitra, scan barcode, selesaikan</small></li>
        <li class="sistem"><strong>Nota penjualan + jurnal</strong><small>Dibuat sistem, harga jual dibekukan</small></li>
        <li><strong>Setoran mitra</strong><small>Dari Payment In, boleh dicicil</small></li>
        <li><strong>Rekonsiliasi Bank</strong><small>Cocokkan setoran hari itu dengan bank</small></li>
        <li><strong>Tutup Harian</strong><small>Kunci angka hari itu</small></li>
    </ol>
    <p class="pj-legend">Kotak bergaris putus-putus dibuat otomatis oleh sistem. Laporan (Laba Rugi, COA, AP/AR Aging, Book) membaca jurnal kapan saja.</p>
</figure>

<h2 id="istilah">Istilah</h2>
<table>
    <tr><th>Istilah</th><th>Arti</th></tr>
    <tr><td>Pemasok</td><td>Pihak yang menjual barang ke kita. Kodenya diisi manual saat dibuat.</td></tr>
    <tr><td>Mitra</td><td>Pihak yang membeli barang dari gudang. Harga jualnya bisa berbeda untuk tiap mitra.</td></tr>
    <tr><td>Kemasan / box</td><td>Satu keranjang atau box fisik dengan satu barcode. Berat ditimbang per kemasan.</td></tr>
    <tr><td>Netto</td><td>Berat barang saja, bruto dikurangi tara.</td></tr>
    <tr><td>Surat Jalan (SJ) penerimaan</td><td>Satu kali kedatangan barang dari satu pemasok. Satu SJ menghasilkan satu faktur pembelian.</td></tr>
    <tr><td>Surat Jalan keluar</td><td>Satu kali pengiriman barang ke satu mitra. Nomornya menjadi nomor nota penjualan.</td></tr>
    <tr><td>Payment Out</td><td>Faktur pembelian: tagihan dari pemasok atau vendor yang harus kita bayar.</td></tr>
    <tr><td>Payment In</td><td>Nota penjualan: tagihan kita ke mitra.</td></tr>
    <tr><td>Setoran</td><td>Uang yang dibayarkan mitra untuk nota.</td></tr>
    <tr><td>HPP</td><td>Harga pokok barang yang terjual, memakai harga beli saat barang diterima.</td></tr>
    <tr><td>Jurnal</td><td>Catatan debit dan kredit yang dibuat sistem untuk setiap dokumen. Semua laporan keuangan dibaca dari jurnal.</td></tr>
    <tr><td>YTD</td><td>Year to date: dari 1 Januari tahun berjalan sampai hari ini.</td></tr>
</table>

<h2 id="peran">Peran pengguna</h2>
<table>
    <tr><th>Peran</th><th>Biasa mengerjakan</th></tr>
    <tr><td>Operator Inbound</td><td>Penerimaan dan timbang, cetak barcode dan Surat Jalan penerimaan.</td></tr>
    <tr><td>Operator Outbound</td><td>Scan Pengeluaran dan cetak Surat Jalan keluar.</td></tr>
    <tr><td>Finance</td><td>Payment Out, pembayaran pemasok, setoran mitra, Rekonsiliasi Bank, laporan.</td></tr>
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
    <li><strong>Harga dibekukan saat transaksi.</strong> Harga beli dibekukan saat barang diterima, harga jual saat barang keluar. Mengubah master harga tidak mengubah transaksi yang sudah terjadi, termasuk nilai persediaan, HPP, piutang, dan laba yang sudah terbit.</li>
    <li><strong>Pemasok dibayar penuh per Surat Jalan.</strong> Tidak ada cicilan ke pemasok.</li>
    <li><strong>Mitra boleh mencicil,</strong> tetapi total setoran tidak boleh melebihi sisa piutang mitra itu.</li>
    <li><strong>Kode dibuat otomatis</strong> untuk mitra, produk, kategori, vendor, dan tipe biaya. Hanya kode pemasok yang diisi manual.</li>
    <li><strong>Data master hanya bisa dihapus bila belum dipakai</strong> di menu lain. Kalau sudah dipakai, hapus dulu dokumen yang memakainya, atau nonaktifkan.</li>
    <li><strong>Dokumen tidak dihapus, tetapi dibatalkan</strong> dengan alasan. Keuangannya ikut dikoreksi. Lihat bagian Pembatalan dan koreksi.</li>
    <li><strong>Tanggal rekonsiliasi mengunci setoran.</strong> Setoran pada hari yang sudah direkonsiliasi tidak bisa dibatalkan sebelum rekonsiliasinya dibuka kembali.</li>
    <li><strong>Hari yang sudah ditutup terkunci.</strong> Hari berjalan boleh ditutup lebih awal, misalnya bila toko tutup sebelum tengah malam. Lihat Tutup Harian.</li>
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
    <tr><td>Log Harga</td><td>Riwayat setiap perubahan harga beli dan harga jual. Juga bisa dibuka dari Pengaturan &gt; Log.</td></tr>
    <tr><td>Settingan API Accurate</td><td>Koneksi ke Accurate Online dan antrean pengirimannya. Juga bisa dibuka dari Pengaturan &gt; Accurate Connector.</td></tr>
</table>
<h3>Mengubah harga</h3>
<ol>
    <li>Buka Harga Pemasok atau Harga Mitra.</li>
    <li>Ubah angka pada artikel yang dimaksud.</li>
    <li>Isi kolom alasan perubahan bila perlu, lalu simpan.</li>
</ol>
<p>Hanya harga yang benar-benar berubah yang masuk ke Log Harga: harga lama, harga baru, selisih, siapa yang mengubah, dan alasannya.</p>
<div class="pj-note"><p>Harga beli artikel dari pemasok harus sudah ada di Harga Pemasok sebelum barangnya diterima. Tanpa itu, penerimaan ditolak.</p></div>

<figure class="pj-fig">
    <p class="pj-fig-judul">Bagan: apa yang terjadi saat harga master diubah</p>
    <p class="pj-lane-t">Harga beli (Harga Pemasok)</p>
    <ol class="pj-flow" style="--n: 4;">
        <li><strong>Ubah harga beli</strong><small>Tercatat di Log Harga</small></li>
        <li><strong>Barang berikutnya diterima</strong><small>Memakai harga baru</small></li>
        <li class="sistem"><strong>Harga dibekukan di tiap box</strong><small>Box lama tetap pada harga lamanya</small></li>
        <li class="sistem"><strong>Persediaan, HPP, faktur</strong><small>Dihitung dari harga beku</small></li>
    </ol>
    <p class="pj-lane-t">Harga jual (Harga Mitra)</p>
    <ol class="pj-flow" style="--n: 4;">
        <li><strong>Ubah harga jual</strong><small>Tercatat di Log Harga</small></li>
        <li><strong>Pengeluaran berikutnya</strong><small>Memakai harga baru</small></li>
        <li class="sistem"><strong>Harga dibekukan di nota</strong><small>Nota lama tidak berubah</small></li>
        <li class="sistem"><strong>Piutang, penjualan, laba</strong><small>Dihitung dari nilai nota</small></li>
    </ol>
    <p class="pj-legend">Karena harga dibekukan, laporan bulan lalu tidak bergeser ketika harga master hari ini berubah.</p>
</figure>

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
<p>Saat Surat Jalan diselesaikan, sistem membuat satu Payment Out untuk Surat Jalan itu. Nilainya memakai harga beli di Harga Pemasok pada saat itu, dan tidak berubah lagi sesudahnya. Stok masuk ke persediaan.</p>
<p>Kalau barcode ingin dicetak lebih dulu dan ditimbang kemudian, pakai <strong>Cetak Barcode Keranjang Baru</strong> di halaman yang sama, lalu timbang dengan cara scan.</p>

<figure class="pj-fig">
    <p class="pj-fig-judul">Bagan: penerimaan dan timbang</p>
    <ol class="pj-flow" style="--n: 5;">
        <li><strong>Pilih pemasok</strong><small>No. Surat Jalan terisi otomatis</small></li>
        <li><strong>Pilih kategori dan artikel</strong><small>Harga beli pemasok harus sudah ada</small></li>
        <li><strong>Timbang, Tambah Box</strong><small>Ulangi untuk tiap box; barcode terbit</small></li>
        <li><strong>Cetak Surat Jalan</strong><small>Menyelesaikan Surat Jalan penerimaan</small></li>
        <li class="sistem"><strong>Faktur + stok masuk</strong><small>Payment Out dan jurnal terbit</small></li>
    </ol>
    <p class="pj-legend">Cetak Barcode dilakukan tiap box dan ditempel di kemasannya sebelum Surat Jalan diselesaikan.</p>
</figure>

<h2 id="riwayat-penerimaan">Riwayat penerimaan</h2>
<p>Menu: <span class="pj-menu">Penerimaan Barang &gt; Riwayat Penerimaan</span>. Berisi seluruh Surat Jalan penerimaan, beserta barcode dan beratnya.</p>
<h3>Membatalkan penerimaan</h3>
<p>Klik <strong>Batalkan</strong> pada Surat Jalan, isi alasan. Kemasan menjadi VOID, faktur pembelian menjadi Batal, dan jurnalnya dibalik. Syaratnya:</p>
<ul>
    <li>Tidak ada pembayaran ke pemasok untuk Surat Jalan itu. Kalau ada, batalkan pembayarannya dulu.</li>
    <li>Tidak ada kemasan yang sudah dikeluarkan ke mitra. Kalau ada, batalkan pengeluarannya dulu.</li>
</ul>

<h2 id="barang-datang">Barang datang</h2>
<p>Menu: <span class="pj-menu">Stok Barang Gudang &gt; Barang Datang</span> (Incoming Stock). Laporan barang yang diterima pada rentang tanggal, dengan total nilai pembelian.</p>
<ul>
    <li>Tombol <strong>Semua / Active / Closed</strong> di atas filter: <strong>Active</strong> berarti barang dari surat jalan itu masih ada di gudang (kolom Stok menunjukkan sisanya), <strong>Closed</strong> berarti seluruhnya sudah pindah ke mitra (kolom Stok menyebut mitranya). Untuk melihat stok yang masih ada di gudang, pilih Active dan atur tanggal Dari cukup lama ke belakang.</li>
    <li>Filter: tanggal, kategori, artikel, pemasok, status bayar (semua, belum lunas, lunas).</li>
    <li>Pilih <strong>Kelompokkan per</strong> pemasok, Surat Jalan, atau kategori.</li>
    <li>Kartu <strong>Total per jenis barang</strong> di atas tabel menampilkan TOTAL tiap jenis (misalnya TOTAL CUMI, TOTAL UDANG) dan <strong>GRAND TOTAL</strong> semua jenis. Angkanya dihitung dari seluruh data yang terfilter, bukan hanya halaman yang tampil.</li>
    <li>Tabel dibagi per halaman: 25, 50, 100, atau 200 baris (bawaan 50). Pindah halaman dan ubah urutan lewat bilah di bawah tabel.</li>
    <li>Tombol <strong>Box</strong> pada tiap baris membuka rincian per box: barcode, bruto, tara, netto, harga, dan nilai.</li>
</ul>
<p>Total laporan ini selalu sama dengan total Payment Out stok.</p>

<h2 id="stok">Stok dan opname</h2>
<table>
    <tr><th>Menu</th><th>Fungsi</th></tr>
    <tr><td>Stok Barang Gudang &gt; Barang Datang</td><td>Barang yang diterima di rentang tanggal, dengan filter Active (masih di gudang) dan Closed (sudah pindah ke mitra). Menggantikan menu Sisa Stok Gudang yang sudah dihapus.</td></tr>
    <tr><td>Stok Barang Gudang &gt; Purchase Book</td><td>Riwayat harga beli tiap produk dari pemasok. Lihat bagian Purchase Book.</td></tr>
    <tr><td>Stok Barang Gudang &gt; Stock Opname</td><td>Mencocokkan barang fisik dengan data sistem. Scan barcode tiap kemasan; yang cocok ditandai, dan hasilnya muncul sebagai notifikasi di pojok kanan atas. Kemasan yang belum di-scan berstatus Menunggu Scan Barcode.</td></tr>
    <tr><td>Saldo Stok Gudang</td><td>Rincian stok per mitra, matriks per kategori, dan nilai persediaan. Nilai dihitung dari harga beli yang dibekukan saat barang diterima (HPP), jadi tidak ikut berubah bila harga master diubah; estimasi nilai jual ditampilkan terpisah. Panel Kecocokan dengan Jurnal membandingkan nilai barang aktif dengan saldo akun 1200 dan menampilkan Surat Jalan yang nilainya tidak sama dengan fakturnya. Kartu Nilai Persediaan di Penerimaan &amp; Timbang menunjukkan nilai barang yang masih di gudang utama.</td></tr>
</table>

<h2 id="pengeluaran">Pengeluaran ke mitra</h2>
<p>Menu: <span class="pj-menu">Pengeluaran Barang ke Mitra &gt; Scan Pengeluaran</span>.</p>
<ol>
    <li>Pilih mitra penerima. Pemasok asal terisi otomatis dari barcode yang di-scan. Nomor Surat Jalan terisi otomatis. Pilih juga <strong>Jatuh Tempo Nota</strong> bila perlu.</li>
    <li>Scan barcode tiap kemasan yang dikirim. Daftar kemasan dan total netto bertambah di tabel.</li>
    <li>Periksa daftar, hapus baris yang salah scan bila ada.</li>
    <li>Klik <strong>Selesaikan Pengeluaran</strong>, lalu <strong>Cetak Surat Jalan</strong>.</li>
</ol>
<p><strong>Scan otomatis.</strong> Kursor langsung berada di kolom scan saat halaman dibuka dan kembali ke sana setelah mitra dipilih. Bila mitra sudah dipilih, barcode yang terbaca lengkap langsung diproses tanpa menekan Enter, jadi operator cukup mengarahkan scanner. Bila mitra belum dipilih, kolom tetap menunggu Enter karena tarif jual belum bisa dihitung. Keterangan di bawah kolom scan menunjukkan mode yang sedang aktif. Di HP atau tablet, keyboard layar tidak muncul sendiri; ketuk kolom scan bila ingin mengetik manual.</p>
<p>Hasil tiap scan (berhasil, sudah pernah discan, status tidak sesuai, dan sebagainya) muncul sebagai notifikasi kecil di pojok kanan atas dan hilang sendiri.</p>
<p>Surat Jalan keluar tidak menampilkan nama pemasok. Setelah pengeluaran selesai, sistem membuat nota penjualan ke mitra dengan harga jual dari Harga Mitra saat itu, dan mencatat HPP dari harga beli barang tersebut.</p>

<figure class="pj-fig">
    <p class="pj-fig-judul">Bagan: pengeluaran ke mitra</p>
    <ol class="pj-flow" style="--n: 5;">
        <li><strong>Pilih mitra penerima</strong><small>Wajib sebelum scan otomatis</small></li>
        <li><strong>Scan barcode kemasan</strong><small>Otomatis masuk daftar begitu terbaca</small></li>
        <li><strong>Periksa daftar</strong><small>Hapus baris yang salah scan</small></li>
        <li><strong>Selesaikan, cetak SJ</strong><small>Surat Jalan keluar dicetak</small></li>
        <li class="sistem"><strong>Nota + jurnal</strong><small>Piutang, penjualan, HPP, stok keluar</small></li>
    </ol>
    <p class="pj-legend">Barcode yang sudah discan di sesi yang sama atau berstatus bukan stok aktif ditolak dengan notifikasi.</p>
</figure>

<h3>Riwayat dan pembatalan</h3>
<p><span class="pj-menu">Riwayat Pengeluaran</span> menampilkan Surat Jalan keluar dalam dua tampilan: per Surat Jalan dan per barang. Pengeluaran bisa dibatalkan oleh Admin atau Supervisor dengan alasan, selama:</p>
<ul>
    <li>belum ada setoran mitra untuk nota itu (batalkan setorannya dulu), dan</li>
    <li>belum terkirim ke Accurate.</li>
</ul>
<p>Setelah dibatalkan, kemasan kembali ke stok gudang dan nota serta jurnalnya dibalik.</p>

<h2 id="purchase-invoice">Payment Out</h2>
<p>Menu: <span class="pj-menu">Payment Out &gt; Daftar Faktur</span>. Berisi dua jenis faktur.</p>
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
<p>Faktur penerimaan barang terbit otomatis dengan jatuh tempo 7 hari dari tanggal faktur. Untuk mengubahnya, klik <strong>ubah</strong> di samping jatuh tempo faktur (Payment Out) atau pada kolom Jatuh Tempo (AP Aging &gt; Surat Jalan Masuk). Yang boleh mengubah: Admin, Supervisor, dan Finance; perubahan tercatat di log audit.</p>
<h3>Membayar</h3>
<p>Klik <strong>Bayar</strong> pada faktur. Pembayaran untuk Surat Jalan pemasok harus melunasi seluruh sisa Surat Jalan yang dipilih; nominal tidak diketik sendiri.</p>

<figure class="pj-fig">
    <p class="pj-fig-judul">Bagan: utang ke pemasok dan vendor</p>
    <p class="pj-lane-t">Faktur stok (dari penerimaan)</p>
    <ol class="pj-flow" style="--n: 4;">
        <li><strong>Surat Jalan penerimaan selesai</strong></li>
        <li class="sistem"><strong>Faktur stok terbit</strong><small>Jatuh tempo 7 hari; utang 2000 naik</small></li>
        <li><strong>Bayar</strong><small>Lunas penuh per Surat Jalan</small></li>
        <li class="sistem"><strong>Utang berkurang</strong><small>Kas &amp; Bank 1000 berkurang</small></li>
    </ol>
    <p class="pj-lane-t">Faktur operasional (biaya atau aset)</p>
    <ol class="pj-flow" style="--n: 4;">
        <li><strong>Faktur Operasional</strong><small>Pilih akun, vendor, item</small></li>
        <li><strong>Atur termin</strong><small>Tunai atau 7 sampai 60 hari</small></li>
        <li class="sistem"><strong>Jurnal terbit</strong><small>Akun pilihan naik, utang naik</small></li>
        <li><strong>Bayar</strong><small>Utang berkurang</small></li>
    </ol>
</figure>

<h2 id="ap-aging">AP Aging</h2>
<p>Menu: <span class="pj-menu">AP Aging</span>. Hanya laporan, tidak ada input.</p>
<ul>
    <li><strong>Ringkasan per Pemasok:</strong> jumlah Surat Jalan yang masih terbuka, total tagihan, total dibayar, dan sisa utang tiap pemasok. Pemasok dibayar penuh per Surat Jalan, jadi tidak ada pembagian umur utang.</li>
    <li><strong>Surat Jalan Masuk:</strong> tiap Surat Jalan dengan total tagihan, dibayar, sisa utang, dan tanggal jatuh tempo fakturnya.</li>
    <li><strong>Riwayat Pembayaran:</strong> semua pembayaran ke pemasok. Pembayaran yang dibatalkan ditandai dan nominal aslinya dicoret.</li>
</ul>
<p>Semua input pembayaran dilakukan dari Payment Out. Pembagian umur menurut jatuh tempo (belum jatuh tempo, telat 1-7, 8-14, 15-30, lebih dari 30 hari) hanya ada di AR Aging untuk piutang mitra. Dokumen yang tidak punya jatuh tempo dianggap jatuh tempo di tanggal dokumennya.</p>

<h2 id="sales-invoice">Payment In</h2>
<p>Menu: <span class="pj-menu">Payment In &gt; Daftar Nota</span>. Nota terbit otomatis dari Pengeluaran.</p>
<p>Jatuh tempo nota ditentukan di layar Pengeluaran lewat pilihan <strong>Jatuh Tempo Nota</strong> (tunai, 7, 14, 30, 45, atau 60 hari dari tanggal nota; isian awal 14 hari). Nota lama yang dibuat sebelum fitur ini dianggap jatuh tempo di tanggal notanya. Untuk mengubahnya, klik <strong>ubah</strong> pada kolom Jatuh Tempo di AR Aging &gt; Nota (Admin, Supervisor, Finance).</p>
<h3>Mencatat setoran mitra</h3>
<ol>
    <li>Klik <strong>Catat Setoran</strong> (atau tombol setoran pada nota).</li>
    <li>Pilih mitra, tanggal, dan metode (tunai atau transfer bank).</li>
    <li>Isi jumlah. Boleh sebagian. Sistem menolak jumlah yang melebihi total sisa piutang mitra itu.</li>
    <li>Pilih pembagian: otomatis ke nota tertua lebih dulu, atau manual per nota.</li>
    <li>Simpan.</li>
</ol>

<figure class="pj-fig">
    <p class="pj-fig-judul">Bagan: piutang dan setoran mitra</p>
    <ol class="pj-flow" style="--n: 5;">
        <li class="sistem"><strong>Nota terbit</strong><small>Dari Pengeluaran; piutang 1100 naik</small></li>
        <li><strong>Catat Setoran</strong><small>Tunai atau transfer; boleh dicicil</small></li>
        <li class="sistem"><strong>Dibagi ke nota</strong><small>Otomatis nota tertua dulu, atau manual</small></li>
        <li class="sistem"><strong>Piutang berkurang</strong><small>Kas &amp; Bank 1000 bertambah</small></li>
        <li><strong>Rekonsiliasi Bank</strong><small>Cocokkan total setoran harian</small></li>
    </ol>
    <p class="pj-legend">Total setoran tidak boleh melebihi sisa piutang mitra.</p>
</figure>

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
<p>Kartu <strong>Saldo Kas &amp; Bank</strong> menunjukkan saldo akun 1000 menurut jurnal. Sistem mulai dari nol, jadi saldonya hanya mencerminkan transaksi sejak sistem dipakai. Untuk menyamakannya dengan saldo bank sebenarnya, catat selisih awalnya sebagai Setoran Modal. Tanpa saldo awal, saldo bisa tampil minus bila pembayaran keluar lebih besar daripada uang yang sudah tercatat masuk.</p>
<p>Transaksi salah tidak dihapus. Klik <strong>Batalkan</strong>, isi alasan, dan jurnalnya dibalik. Baris tetap tampil dengan status Dibatalkan. Yang boleh mencatat dan membatalkan: Admin, Supervisor, dan Finance.</p>

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

<figure class="pj-fig">
    <p class="pj-fig-judul">Bagan: rekonsiliasi bank harian</p>
    <ol class="pj-flow" style="--n: 5;">
        <li><strong>Pilih tanggal</strong><small>Hari yang punya setoran</small></li>
        <li><strong>Isi total uang masuk di bank</strong><small>Sesuai mutasi bank</small></li>
        <li><strong>Lampirkan bukti</strong><small>Screenshot atau slip, maks. 8 MB</small></li>
        <li class="sistem"><strong>Sistem membandingkan</strong><small>Sesuai, atau Selisih (isi catatan)</small></li>
        <li class="sistem"><strong>Setoran hari itu terkunci</strong><small>Buka kembali dengan alasan bila perlu</small></li>
    </ol>
</figure>

<h2 id="tutup-harian">Tutup Harian</h2>
<p>Menu: <span class="pj-menu">Laba Rugi &gt; Tutup Harian</span>. Dipakai tiap hari (atau tiap beberapa hari) setelah angkanya dicek, supaya angka hari itu tidak berubah lagi, termasuk bila harga master berubah keesokan harinya.</p>
<ol>
    <li>Pastikan setoran hari itu sudah direkonsiliasi dan semua faktur dan kas hari itu sudah tercatat.</li>
    <li>Pilih bulan di bagian atas, lalu klik <strong>Tutup hari</strong> pada tanggal yang dimaksud. Sistem menampilkan hasil pemeriksaan: jurnal hari itu seimbang, status rekonsiliasi bank hari itu, dan kecocokan persediaan dengan jurnal.</li>
    <li>Pemeriksaan bertanda silang menghalangi penutupan. Yang bertanda seru hanya peringatan; sebaiknya dibereskan dulu.</li>
    <li>Isi catatan bila perlu, lalu konfirmasi. Penjualan, HPP, biaya, laba bersih hari itu, dan saldo kas, piutang, persediaan, dan utang akhir hari disimpan.</li>
</ol>
<p>Tombol <strong>Tutup semua sampai kemarin</strong> menutup sekaligus semua hari yang masih terbuka sejak transaksi pertama. Hari yang jurnalnya tidak seimbang dilewati dan disebutkan dalam pesan hasilnya.</p>
<p><strong>Hari ini boleh ditutup lebih awal</strong>, misalnya bila toko tutup sebelum jam 12 malam; tombol <strong>Tutup hari</strong> tersedia di baris hari ini dan sistem menampilkan peringatan bahwa hari belum berakhir. Tanggal yang belum tiba tidak bisa ditutup. Penutupan hanya oleh Admin atau Finance. Setelah sebuah hari ditutup, faktur, pembayaran, setoran, transaksi kas, dan rekonsiliasi bertanggal hari itu ditolak, dan dokumen bertanggal hari itu tidak bisa dibatalkan. Transaksi hari lain tidak terpengaruh.</p>
<p>Untuk koreksi, Admin klik <strong>Buka kembali</strong> pada harinya, mengisi alasan (tercatat di log audit), melakukan koreksi, lalu menutup harinya lagi. Pembatalan tetap dicatat pada tanggal pembatalannya, jadi koreksinya muncul di hari berjalan dan angka hari yang sudah ditutup tidak bergeser.</p>

<figure class="pj-fig">
    <p class="pj-fig-judul">Bagan: tutup harian</p>
    <p class="pj-lane-t">Menutup hari</p>
    <ol class="pj-flow" style="--n: 4;">
        <li><strong>Cek dulu</strong><small>Setoran sudah direkonsiliasi, faktur dan kas tercatat</small></li>
        <li><strong>Klik Tutup hari</strong><small>Hari ini pun boleh bila toko tutup lebih awal</small></li>
        <li class="sistem"><strong>Pemeriksaan</strong><small>Silang menghalangi; seru hanya peringatan</small></li>
        <li class="sistem"><strong>Hari terkunci</strong><small>Angka disimpan; transaksi bertanggal hari itu ditolak</small></li>
    </ol>
    <p class="pj-lane-t">Koreksi hari yang sudah ditutup (Admin)</p>
    <ol class="pj-flow" style="--n: 3;">
        <li><strong>Buka kembali</strong><small>Isi alasan; tercatat di log audit</small></li>
        <li><strong>Lakukan koreksi</strong><small>Batalkan atau catat ulang dokumennya</small></li>
        <li><strong>Tutup lagi</strong><small>Hari terkunci kembali</small></li>
    </ol>
</figure>

<h2 id="laba-rugi">Laba Rugi</h2>
<p>Menu: <span class="pj-menu">Laba Rugi &gt; Laba Rugi</span>. Semua angka dibaca dari jurnal dengan dasar akrual: penjualan diakui saat barang keluar, bukan saat uang masuk.</p>
<p><strong>Memilih periode.</strong> Di atas halaman ada filter periode. Klik pilihan cepat <strong>Hari ini</strong>, <strong>Minggu ini</strong> (Senin sampai Minggu), <strong>Bulan ini</strong>, atau <strong>Tahun ini (YTD)</strong>, atau isi tanggal <strong>Mulai</strong> dan <strong>Sampai</strong> sendiri lalu klik <strong>Terapkan</strong>. Bawaannya bulan ini.</p>
<ul>
    <li>Tabel menampilkan Penjualan, HPP, Laba Kotor, Biaya Operasional, Laba Bersih, dan marginnya untuk periode yang dipilih, plus kolom Total.</li>
    <li>Rincian kolom menyesuaikan panjang periode: per hari bila 31 hari atau kurang, per minggu bila sampai 93 hari, dan per bulan bila lebih panjang.</li>
    <li>Di bawah tiap kelompok ada baris per akun COA. Klik angka akun untuk melihat mutasinya pada kolom itu.</li>
    <li>Periode dibandingkan dengan periode sebelumnya yang panjangnya sama (misalnya 1 sampai 14 November dengan 18 sampai 31 Oktober).</li>
    <li>Rincian penjualan dan HPP per mitra, serta biaya per vendor, untuk periode yang sama.</li>
    <li>Baris pemeriksaan di bawah tabel membandingkan laba bersih dengan saldo akun di COA. Seharusnya selalu tertulis Cocok.</li>
</ul>
<p><span class="pj-menu">Laba Rugi &gt; Detail Laba</span> menampilkan penjualan, HPP, laba, dan margin dari pengeluaran barang pada periode yang dipilih, dengan filter periode yang sama. Selain periode, data bisa disaring per <strong>mitra</strong>, <strong>jenis barang</strong>, <strong>produk</strong>, <strong>pemasok</strong>, atau nomor surat jalan. Pilih <strong>Kelompokkan per</strong> untuk memecah laba per <strong>Surat Jalan</strong>, <strong>Surat Jalan per Produk</strong>, <strong>Jenis Barang</strong>, <strong>Produk</strong>, <strong>Mitra</strong>, <strong>Pemasok</strong>, atau <strong>Tanggal</strong>. Klik judul kolom untuk mengurutkan. Kartu di atas tabel menunjukkan total penjualan, HPP, laba, dan margin dari seluruh data yang terfilter. Pengeluaran yang sudah dibatalkan tidak ikut dihitung.</p>
<div class="pj-note"><p>Pembatalan dicatat pada tanggal pembatalan, bukan tanggal dokumen aslinya. Penjualan yang dibatalkan di periode berikutnya mengoreksi periode berikutnya itu.</p></div>

<figure class="pj-fig">
    <p class="pj-fig-judul">Bagan: dari transaksi ke Laba Rugi</p>
    <ol class="pj-flow" style="--n: 4;">
        <li><strong>Pengeluaran, faktur, kas</strong><small>Dokumen yang dicatat di menu masing-masing</small></li>
        <li class="sistem"><strong>Jurnal</strong><small>Penjualan 4000, HPP 5000, biaya 6000 dan seterusnya</small></li>
        <li><strong>Pilih periode</strong><small>Hari, minggu, bulan, YTD, atau tanggal bebas</small></li>
        <li class="sistem"><strong>Laba Rugi</strong><small>Total, rincian kolom, pembanding, dan cek COA</small></li>
    </ol>
</figure>

<h2 id="coa">COA</h2>
<p>Menu: <span class="pj-menu">Laba Rugi &gt; COA</span> untuk melihat saldo, dan <span class="pj-menu">Master &amp; Pengaturan &gt; Akun COA</span> untuk mengelola akun.</p>
<ul>
    <li>Saldo setiap akun dihitung dari jurnal. Klik kode akun untuk melihat mutasinya.</li>
    <li><strong>Filter periode.</strong> Bawaannya <strong>Semua</strong> (saldo keseluruhan). Pilih Hari ini, Minggu ini, Bulan ini, Tahun ini (YTD), atau isi tanggal Mulai dan Sampai, maka tabel menampilkan <strong>Saldo Awal</strong> (sebelum tanggal mulai), <strong>Debit</strong> dan <strong>Kredit</strong> pada periode, dan <strong>Saldo Akhir</strong> (sampai tanggal akhir). Di halaman rincian akun, daftar mutasi ikut dibatasi periode itu.</li>
    <li>Di bawah tabel ada pemeriksaan jurnal seimbang: total debit sama dengan total kredit.</li>
    <li>Ada delapan akun induk: 1000 Kas &amp; Bank, 1100 Piutang &amp; Aset Lainnya, 1200 Inventory, 2000 Utang, 3000 Modal, 4000 Penjualan, 5000 HPP, 6000 Biaya Operasional. Akun ini dipakai posting otomatis, tidak bisa dihapus atau dinonaktifkan (tombol hapusnya berwarna abu-abu).</li>
    <li>Sub-akun tidak disediakan bawaan; diinput sendiri sesuai kebutuhan dengan tombol tambah (+) pada akun induknya. Kode 4 digit di rentang kelompoknya, misalnya Beban 6000 sampai 9999.</li>
    <li>Akun yang sudah dipakai di jurnal atau faktur tidak bisa dihapus, hanya dinonaktifkan.</li>
</ul>
<p>Saldo bisa tampil minus, misalnya Kas &amp; Bank, bila uang keluar sudah tercatat tetapi saldo awal atau modal belum. Lihat Transaksi Kas &amp; Bank.</p>

<h2 id="book">Purchase Book</h2>
<p>Menu: <span class="pj-menu">Stok Barang Gudang &gt; Purchase Book</span>. Dipakai untuk melihat harga beli dari waktu ke waktu.</p>
<ul>
    <li>Purchase Book: tiap pembelian barang dari pemasok beserta harganya, misalnya cumi dari pemasok A pada 1 Januari Rp 100.000/kg dan pada 10 Januari Rp 150.000/kg, jadi kenaikan harga terlihat per order. Pilih satu artikel untuk melihat riwayatnya dan perbandingan antar pemasok.</li>
    <li>Tiap baris menampilkan selisih harga terhadap transaksi sebelumnya untuk artikel dan pemasok yang sama.</li>
    <li>Pilih satu artikel untuk melihat grafik tren harga dan tabel perbandingan antar pemasok.</li>
    <li><strong>Klik judul kolom</strong> untuk mengurutkan tabel (klik sekali naik, klik lagi turun). Berlaku juga di Barang Datang, termasuk tabel Total per jenis barang. Selisih Harga: harga pembelian ini dibanding pembelian sebelumnya untuk artikel dan pemasok yang sama (naik atau turun, dalam rupiah dan persen); kosong (-) bila itu pembelian pertama.</li>
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
<p>Semua pembatalan ditolak bila tanggal dokumennya jatuh di hari yang sudah ditutup (lihat Tutup Harian).</p>
<p>Urutan membatalkan mengikuti arah sebaliknya: setoran atau pembayaran dibatalkan lebih dulu, baru dokumen di bawahnya (pengeluaran atau penerimaan).</p>

<figure class="pj-fig">
    <p class="pj-fig-judul">Bagan: urutan membatalkan dokumen</p>
    <p class="pj-lane-t">Salah di barang keluar</p>
    <ol class="pj-flow" style="--n: 4;">
        <li><strong>Batalkan setoran mitra</strong><small>Hari setoran belum direkonsiliasi, atau buka kembali</small></li>
        <li><strong>Batalkan pengeluaran</strong><small>Riwayat Pengeluaran; Admin atau Supervisor</small></li>
        <li class="sistem"><strong>Jurnal dibalik</strong><small>Nota dan piutang dikoreksi</small></li>
        <li class="sistem"><strong>Kemasan kembali ke stok</strong><small>Bisa discan lagi</small></li>
    </ol>
    <p class="pj-lane-t">Salah di barang masuk</p>
    <ol class="pj-flow" style="--n: 4;">
        <li><strong>Batalkan pengeluaran</strong><small>Bila box sudah keluar ke mitra</small></li>
        <li><strong>Batalkan pembayaran pemasok</strong><small>Bila Surat Jalan sudah dibayar</small></li>
        <li><strong>Batalkan penerimaan</strong><small>Riwayat Penerimaan; Admin atau Supervisor</small></li>
        <li class="sistem"><strong>Box VOID, faktur Batal</strong><small>Jurnal dibalik</small></li>
    </ol>
    <p class="pj-legend">Hari yang sudah ditutup menolak pembatalan; buka kembali harinya lebih dulu (Admin).</p>
</figure>

<h2 id="cetak-sj">Mencetak surat jalan</h2>
<p>Menu: <span class="pj-menu">Surat Jalan</span>. Dua tab: Pengeluaran ke Mitra dan Penerimaan dari Pemasok. Daftar bisa difilter menurut tanggal, mitra atau pemasok, status, dan kata kunci, lalu dicetak atau dicetak ulang kapan saja. Surat jalan yang dibatalkan tetap tampil tetapi tidak bisa dicetak. Tombol <strong>Tabel</strong> dan <strong>Grid</strong> di kanan atas mengganti tampilan daftar menjadi baris tabel atau kartu; pilihannya diingat di browser itu.</p>
<p>Tombol <strong>Preview PDF</strong> membuka pratinjau surat jalan seukuran kertas A4, persis seperti hasil cetak. Pada pengeluaran, pratinjau bisa berpindah antara Form (tanpa harga) dan Versi Internal bagi yang berhak. Dari pratinjau, klik <strong>Cetak / Simpan PDF</strong>, lalu pilih &quot;Simpan sebagai PDF&quot; sebagai tujuan untuk mendapatkan file PDF. Pada tampilan Grid, tiap kartu menampilkan thumbnail dokumennya; klik thumbnail untuk membuka pratinjau.</p>
<p>Daftar dibagi per halaman (tabel 25 baris, grid 12 kartu; bisa diganti lewat Per halaman di bawah daftar). Pada tampilan Tabel, klik judul kolom untuk mengurutkan seluruh hasil, klik lagi untuk membalik arah; pada tampilan Grid, urutan dipilih lewat Urutkan dan Arah di bagian filter. Ekspor Excel dan PDF memuat baris pada halaman yang tampil; untuk mengekspor lebih banyak, pilih 100 atau 500 baris per halaman.</p>
<table>
    <tr><th>Tombol</th><th>Isi</th><th>Untuk</th></tr>
    <tr><td>Cetak Form (rangkap 3)</td><td>Tanpa harga: kemasan, netto, asal surat jalan, dan kolom tanda tangan.</td><td>Kertas A4 bersambung rangkap 3 (3 ply). Satu kali cetak tembus ke semua lapis, jadi cetak satu kali saja.</td></tr>
    <tr><td>Versi Internal</td><td>Lengkap dengan harga, subtotal, dan total piutang mitra.</td><td>Arsip kita di kertas biasa. Hanya tampil untuk Admin, Supervisor, dan Finance; operator tidak melihat tombol maupun kolom nilai.</td></tr>
</table>
<p>Layar Pengeluaran langsung membuka versi form (tanpa harga) setelah barang dikeluarkan. Surat Jalan Penerimaan tidak memuat harga dan hanya punya satu versi cetak.</p>
<p>Kertas yang dipakai adalah A4 rangkap 3 (3 ply). Nama perusahaan ada di bagian paling atas dan judul surat tepat di bawahnya. Dokumen panjang berlanjut ke lembar berikutnya dengan judul tabel berulang. Di dasar setiap halaman ada garis putus-putus; garis ini membuat kepala cetak berjalan sampai batas akhir halaman, sehingga printer tidak berhenti di baris terakhir isi dan kertas tidak robek saat ditarik.</p>

<figure class="pj-fig">
    <p class="pj-fig-judul">Bagan: mencetak surat jalan</p>
    <ol class="pj-flow" style="--n: 5;">
        <li><strong>Buka menu Surat Jalan</strong><small>Pilih tab dan filter daftar</small></li>
        <li><strong>Preview PDF</strong><small>Cek tampilan seukuran A4</small></li>
        <li><strong>Pilih versi</strong><small>Form rangkap 3, atau Versi Internal (berhak saja)</small></li>
        <li><strong>Cetak</strong><small>Dialog cetak browser, kertas A4</small></li>
        <li><strong>Printer form bersambung</strong><small>Tractor atau Manual Feed</small></li>
    </ol>
</figure>

<h3>Dialog cetak browser (Chrome)</h3>
<table>
    <tr><th>Pengaturan</th><th>Nilai</th></tr>
    <tr><td>Tujuan</td><td>Printer form bersambung (bukan Simpan sebagai PDF)</td></tr>
    <tr><td>Salinan</td><td>1</td></tr>
    <tr><td>Ukuran kertas</td><td>A4</td></tr>
    <tr><td>Halaman per lembar</td><td>1</td></tr>
    <tr><td>Margin</td><td>Default</td></tr>
    <tr><td>Skala</td><td>Kustom, 100</td></tr>
    <tr><td>Header dan footer</td><td>Tidak dicentang</td></tr>
    <tr><td>Grafis latar belakang</td><td>Tidak perlu</td></tr>
</table>
<h3>Pengaturan komputer dan printer</h3>
<ol>
    <li>Windows: Settings &gt; Printers &amp; scanners &gt; pilih printer &gt; Printing preferences. Pilih ukuran kertas <strong>A4</strong>, sumber kertas <strong>Tractor</strong> (atau Manual Feed bila printer hanya punya itu), orientasi Portrait.</li>
    <li>Di properti yang sama, cari pengaturan ketebalan kertas atau multi-part dan pilih tebal bila memakai form rangkap, supaya hasil tembus.</li>
    <li>Panjang halaman di driver, di panel printer, dan di kertas harus sama, yaitu A4 (297 mm atau sekitar 11,7 inci). Jangan memakai &quot;page length&quot; lain.</li>
    <li>Di printer dot matrix, pasang form dengan tepi atas sejajar kepala cetak, lalu atur posisi Top of Form dari panel printer (tombol Tear Off/Micro Feed) sebelum mencetak pertama kali. Jangan menggeser kertas dengan tangan sesudahnya.</li>
    <li>Cetak satu lembar uji. Bila tulisan terlalu mepet ke atas atau kiri, beri tahu agar margin halaman disesuaikan.</li>
</ol>
<div class="pj-note"><p>Bila satu dokumen pendek memakai dua form, penyebabnya hampir selalu panjang halaman di printer atau driver tidak sama dengan ukuran A4, atau posisi Top of Form bergeser. Bila printer berhenti di baris terakhir isi dan tidak lanjut ke batas bawah kertas, periksa pengaturan page length dan auto tear-off di panel printer.</p></div>

<h2 id="backup">Backup dan pemulihan</h2>
<p>Menu: <span class="pj-menu">Pengaturan &gt; Backup</span>. Menyimpan salinan database, bukti setoran (file yang diunggah), dan berkas konfigurasi (.env dan pengaturan Accurate) dalam satu berkas. Simpan satu salinan di luar komputer ini.</p>
<ul>
    <li><strong>Buat Backup Sekarang</strong> membuat salinan manual. Kartu di atas menunjukkan backup terakhir, jumlah backup, total ukuran, dan folder penyimpanannya.</li>
    <li><strong>Otomatis tiap bulan.</strong> Sistem membuat backup sendiri sekali sebulan. Bila gagal, kartu merah menampilkan penyebabnya dan sistem mencoba lagi beberapa jam kemudian.</li>
    <li><strong>Unduh</strong> menyimpan berkas ke komputer yang dipakai. <strong>Unggah Backup</strong> memasukkan berkas backup dari luar supaya bisa dipulihkan.</li>
    <li><strong>Pulihkan</strong> mengembalikan data seperti saat backup dibuat. Semua transaksi sesudah waktu itu hilang dari database. Sistem lebih dulu membuat backup pengaman otomatis dari kondisi sekarang, dan Anda harus mengetik <strong>PULIHKAN</strong> untuk melanjutkan. Pastikan tidak ada pengguna lain yang sedang bekerja.</li>
    <li>Backup lama dihapus otomatis: 30 backup manual terakhir, 24 backup otomatis, dan 10 backup pengaman.</li>
    <li>Untuk sementara menu Backup dan Pulihkan terbuka untuk semua peran. Pembagian hak aksesnya akan diatur kemudian.</li>
</ul>

<figure class="pj-fig">
    <p class="pj-fig-judul">Bagan: backup dan pemulihan</p>
    <p class="pj-lane-t">Membuat backup</p>
    <ol class="pj-flow" style="--n: 4;">
        <li><strong>Buat Backup Sekarang</strong><small>Atau otomatis sekali sebulan</small></li>
        <li class="sistem"><strong>Berkas backup dibuat</strong><small>Database, bukti setoran, .env dan Accurate</small></li>
        <li class="sistem"><strong>Backup lama dirapikan</strong><small>Hanya yang terbaru disimpan</small></li>
        <li><strong>Unduh salinan</strong><small>Simpan di luar komputer ini</small></li>
    </ol>
    <p class="pj-lane-t">Memulihkan data</p>
    <ol class="pj-flow" style="--n: 4;">
        <li><strong>Pilih backup</strong><small>Dari daftar, atau Unggah Backup</small></li>
        <li><strong>Ketik PULIHKAN</strong><small>Konfirmasi di dialog peringatan</small></li>
        <li class="sistem"><strong>Backup pengaman</strong><small>Kondisi sekarang disimpan dulu</small></li>
        <li class="sistem"><strong>Data dikembalikan</strong><small>Seperti saat backup dibuat</small></li>
    </ol>
</figure>

<h2 id="ekspor">Filter, ekspor, dan tampilan</h2>
<ul>
    <li><strong>Filter periode.</strong> Halaman Laba Rugi, Detail Laba, dan COA punya bilah filter periode: Hari ini, Minggu ini, Bulan ini, Tahun ini (YTD), atau tanggal Mulai dan Sampai bebas lalu <strong>Terapkan</strong>. Pilihan yang sama dengan tanggal yang tampil menyala sebagai penanda.</li>
    <li>Halaman laporan punya tombol <strong>Excel</strong> dan <strong>PDF</strong>. Excel membuat satu sheet per tabel di halaman, dengan nilai Rupiah sebagai angka. PDF membuka dialog cetak; pilih Simpan sebagai PDF.</li>
    <li>Yang diekspor hanya yang tampil: baris yang tersaring tidak ikut, dan kolom Aksi tidak disertakan. Pada tabel yang dibagi per halaman, yang diekspor adalah halaman yang sedang tampil.</li>
    <li><strong>Tabel yang lebar</strong> tidak membungkus teks menjadi bertumpuk; geser ke samping untuk melihat kolom lainnya.</li>
    <li><strong>Ikon salin.</strong> Di samping ID barcode pada tabel ada ikon kecil; klik untuk menyalin ID ke clipboard, ikonnya berubah menjadi ceklis sebagai tanda berhasil.</li>
    <li><strong>Notifikasi</strong> berupa kotak kecil di pojok kanan atas yang hilang sendiri. Pesan peringatan dan kesalahan bertahan lebih lama; klik tanda silang untuk menutupnya.</li>
    <li><strong>Mode Tampilan</strong> di bawah sidebar mengganti tema terang dan gelap; pilihannya diingat di browser.</li>
    <li>Semua pilihan dropdown bisa dicari dengan mengetik jika daftarnya panjang. Panah atas/bawah dan Enter bekerja di dalam dropdown.</li>
</ul>

<h2 id="masalah">Jika ada masalah</h2>
<table>
    <tr><th>Gejala</th><th>Penyebab dan tindakan</th></tr>
    <tr><td>Penerimaan ditolak karena harga</td><td>Harga beli artikel itu dari pemasok belum diisi. Isi di Master &gt; Harga Pemasok.</td></tr>
    <tr><td>Scan di Pengeluaran tidak langsung masuk</td><td>Mitra penerima belum dipilih (scan otomatis hanya aktif bila mitra sudah dipilih), atau barcode belum terbaca lengkap. Pilih mitra, atau tekan Enter.</td></tr>
    <tr><td>Tarif jual tampil Rp 0</td><td>Harga jual artikel itu untuk mitra terpilih belum diisi di Master &gt; Harga Mitra. Isi dulu lalu scan ulang atau ganti mitra.</td></tr>
    <tr><td>Tidak bisa membatalkan penerimaan</td><td>Masih ada pembayaran pemasok atau kemasan yang sudah keluar. Batalkan dulu yang di bawahnya.</td></tr>
    <tr><td>Tidak bisa membatalkan setoran</td><td>Hari setoran itu sudah direkonsiliasi. Buka kembali rekonsiliasinya.</td></tr>
    <tr><td>Dokumen tidak bisa dibatalkan atau transaksi ditolak</td><td>Tanggalnya jatuh di hari yang sudah ditutup. Admin perlu membuka kembali hari itu di Tutup Harian.</td></tr>
    <tr><td>Setoran ditolak</td><td>Jumlahnya melebihi sisa piutang mitra.</td></tr>
    <tr><td>Saldo Kas &amp; Bank minus</td><td>Uang keluar sudah dicatat tetapi saldo awal atau modal belum. Catat selisih awalnya sebagai Setoran Modal di Transaksi Kas &amp; Bank.</td></tr>
    <tr><td>Tidak bisa menghapus master</td><td>Masih dipakai di menu lain. Pesan error menyebutkan di mana. Hapus atau batalkan dokumen itu dulu, atau nonaktifkan.</td></tr>
    <tr><td>Laba Rugi tertulis Tidak cocok dengan COA</td><td>Ada jurnal yang tidak terbaca di salah satu sisi. Hubungi admin dan jangan dipakai untuk laporan sebelum dicek.</td></tr>
    <tr><td>Rekonsiliasi tertulis Setoran berubah</td><td>Ada setoran baru atau perubahan setelah hari itu diaudit. Buka kembali dan rekonsiliasi ulang.</td></tr>
    <tr><td>Cetak surat jalan memakai dua lembar atau printer berhenti di baris terakhir</td><td>Periksa panjang halaman (A4) dan posisi Top of Form di printer, driver, dan kertas. Lihat Mencetak surat jalan.</td></tr>
</table>

<p class="pj-cari-kosong" id="pjCariKosong">Tidak ada bagian panduan yang cocok. Coba kata kunci lain, misalnya nama menu atau istilah singkat.</p>

</article>
</div>

<script>
(function () {
    const body = document.getElementById('pjBody');
    const input = document.getElementById('pjCari');
    const info = document.getElementById('pjCariInfo');
    const hapus = document.getElementById('pjCariHapus');
    const kosong = document.getElementById('pjCariKosong');
    const toc = document.querySelector('.pj-toc');
    if (!body || !input) return;

    // Bungkus tiap h2 beserta isinya menjadi satu bagian agar bisa disaring.
    const intro = document.getElementById('pjIntro');
    const bagian = [];
    let kini = null;
    Array.from(body.children).forEach(function (el) {
        if (el === intro || el === kosong) return;
        if (el.tagName === 'H2') {
            kini = document.createElement('section');
            kini.className = 'pj-sec';
            body.insertBefore(kini, el);
            bagian.push({ el: kini, id: el.id, teks: '' });
        }
        if (kini) kini.appendChild(el);
    });
    bagian.forEach(function (b) { b.teks = b.el.textContent.toLowerCase().replace(/\s+/g, ' '); });

    const tocLink = {};
    toc.querySelectorAll('a[href^="#"]').forEach(function (a) { tocLink[a.getAttribute('href').slice(1)] = a; });
    const tocHead = Array.from(toc.querySelectorAll('.pj-toc-head'));

    function bersihkanSorot() {
        body.querySelectorAll('mark.pj-hit').forEach(function (m) {
            const p = m.parentNode;
            p.replaceChild(document.createTextNode(m.textContent), m);
            p.normalize();
        });
    }

    function sorot(akar, kata) {
        const re = new RegExp('(' + kata.map(function (k) { return k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }).join('|') + ')', 'gi');
        const walker = document.createTreeWalker(akar, NodeFilter.SHOW_TEXT, {
            acceptNode: function (n) {
                if (!n.nodeValue.trim()) return NodeFilter.FILTER_REJECT;
                const t = n.parentNode.nodeName;
                return (t === 'SCRIPT' || t === 'STYLE' || t === 'MARK') ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
            }
        });
        const simpul = [];
        while (walker.nextNode()) simpul.push(walker.currentNode);
        let jumlah = 0;
        simpul.forEach(function (n) {
            const s = n.nodeValue;
            re.lastIndex = 0;
            if (!re.test(s)) return;
            re.lastIndex = 0;
            const frag = document.createDocumentFragment();
            let akhir = 0, m;
            while ((m = re.exec(s)) !== null) {
                if (m.index > akhir) frag.appendChild(document.createTextNode(s.slice(akhir, m.index)));
                const mk = document.createElement('mark');
                mk.className = 'pj-hit';
                mk.textContent = m[0];
                frag.appendChild(mk);
                jumlah++;
                akhir = m.index + m[0].length;
                if (m[0].length === 0) re.lastIndex++;
            }
            if (akhir < s.length) frag.appendChild(document.createTextNode(s.slice(akhir)));
            n.parentNode.replaceChild(frag, n);
        });
        return jumlah;
    }

    function cari() {
        const q = input.value.trim().toLowerCase().replace(/\s+/g, ' ');
        hapus.style.display = input.value ? 'block' : 'none';
        bersihkanSorot();

        if (q.length < 2) {
            body.classList.remove('pj-searching');
            intro.classList.remove('pj-sembunyi');
            bagian.forEach(function (b) { b.el.classList.remove('pj-sembunyi'); if (tocLink[b.id]) tocLink[b.id].style.display = ''; });
            tocHead.forEach(function (h) { h.style.display = ''; });
            kosong.style.display = 'none';
            info.textContent = q.length === 1 ? 'Ketik minimal 2 huruf.' : '';
            return;
        }

        const kata = q.split(' ').filter(Boolean);
        body.classList.add('pj-searching');
        intro.classList.add('pj-sembunyi');
        let cocok = 0, sorotan = 0, pertama = null;
        bagian.forEach(function (b) {
            const ok = kata.every(function (k) { return b.teks.indexOf(k) !== -1; });
            b.el.classList.toggle('pj-sembunyi', !ok);
            if (tocLink[b.id]) tocLink[b.id].style.display = ok ? '' : 'none';
            if (ok) { cocok++; sorotan += sorot(b.el, kata); if (!pertama) pertama = b.el; }
        });
        // sembunyikan judul kelompok daftar isi bila tak ada tautan yang tampil di bawahnya
        tocHead.forEach(function (h) {
            let n = h.nextElementSibling, ada = false;
            while (n && !n.classList.contains('pj-toc-head')) {
                if (n.tagName === 'A' && n.style.display !== 'none') ada = true;
                n = n.nextElementSibling;
            }
            h.style.display = ada ? '' : 'none';
        });
        kosong.style.display = cocok ? 'none' : 'block';
        info.textContent = cocok ? cocok + ' bagian cocok, ' + sorotan + ' kata ditandai' : 'Tidak ada hasil';
        if (pertama) pertama.scrollIntoView({ block: 'start' });
    }

    let timer = null;
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(cari, 160); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { input.value = ''; cari(); input.blur(); }
    });
    hapus.addEventListener('click', function () { input.value = ''; cari(); input.focus(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
            const t = (document.activeElement || {}).tagName;
            if (t === 'INPUT' || t === 'TEXTAREA' || t === 'SELECT') return;
            e.preventDefault();
            input.focus();
            input.select();
        }
    });
})();
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
