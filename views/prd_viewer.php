<?php
/**
 * Interactive PRD Documentation Viewer (Vibrant & Eyecatching Edition)
 */

$pageTitle = 'Dokumen PRD Interaktif - Sistem Barcode Inventory Multi-Mitra';

require_once __DIR__ . '/layout/header.php';
?>

<!-- Breadcrumbs Vibrant -->
<div class="no-print" style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--text-muted); margin-bottom: 14px; font-weight: 600;">
    <span style="color: var(--primary);">Dokumentasi Resmi</span>
    <span>/</span>
    <span>Spesifikasi Produk (PRD)</span>
    <span>/</span>
    <span style="color: var(--text-main); font-weight: 800;">Barcode Multi-Mitra (Catch-Weight)</span>
</div>

<!-- Page Title Header -->
<div class="page-header no-print">
    <div>
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
            <span class="badge badge-default">PRD Resmi v1.1.0</span>
            <span class="badge badge-success">✓ Siap Diimplementasikan</span>
            <span class="badge badge-info">REST API Accurate Online</span>
        </div>
        <h1 class="page-title" style="font-size: 28px;">Sistem Barcode Inventory Multi-Mitra</h1>
        <p class="page-subtitle" style="font-size: 15px; max-width: 860px;">
            Spesifikasi Warehouse Execution System (WES) untuk penanganan barang dengan bobot bervariasi (<em>catch-weight</em>) milik 7 mitra bisnis dan sinkronisasi otomatis ke Accurate Online.
        </p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="Manual_Book_Sistem_Barcode_Inventory_Multi_Mitra.docx" class="btn btn-outline" download style="color: #2563eb; border-color: #2563eb;">
            <i data-lucide="file-text" style="width: 16px; height: 16px;"></i>
            <span>Unduh Manual Book (.docx)</span>
        </a>
        <button type="button" class="btn btn-outline" onclick="window.print()">
            <i data-lucide="printer" style="width: 16px; height: 16px;"></i>
            <span>Cetak PDF</span>
        </button>
        <a href="index.php?page=dashboard" class="btn btn-primary">
            <i data-lucide="arrow-right" style="width: 16px; height: 16px;"></i>
            <span>Buka Dashboard</span>
        </a>
    </div>
</div>

<div class="prd-container">
    <!-- Sidebar Dokumentasi Vibrant -->
    <aside class="prd-sidebar no-print">
        <div style="margin-bottom: 18px;">
            <input type="text" id="prdSearch" class="form-control" placeholder="🔍 Cari bab PRD..." style="height: 38px; font-size: 13px;">
        </div>

        <div style="margin-bottom: 14px;">
            <div class="prd-sidebar-title">🎯 Getting Started</div>
            <ul class="prd-sidebar-links">
                <li><a href="#bab1" class="prd-sidebar-link"><i data-lucide="sparkles" style="width: 14px; height: 14px; color: var(--primary);"></i> 1. Ringkasan Eksekutif</a></li>
                <li><a href="#bab2" class="prd-sidebar-link"><i data-lucide="check-circle-2" style="width: 14px; height: 14px; color: #10b981;"></i> 2. Masalah & Solusi</a></li>
                <li><a href="#bab3" class="prd-sidebar-link"><i data-lucide="target" style="width: 14px; height: 14px; color: #f59e0b;"></i> 3. Ruang Lingkup v1</a></li>
            </ul>
        </div>

        <div style="margin-bottom: 14px;">
            <div class="prd-sidebar-title">🏗️ Arsitektur Sistem</div>
            <ul class="prd-sidebar-links">
                <li><a href="#bab4" class="prd-sidebar-link"><i data-lucide="users" style="width: 14px; height: 14px; color: #8b5cf6;"></i> 4. Pengguna & RBAC</a></li>
                <li><a href="#bab5" class="prd-sidebar-link"><i data-lucide="database" style="width: 14px; height: 14px; color: #0ea5e9;"></i> 5. Struktur Data & ERD</a></li>
                <li><a href="#bab6" class="prd-sidebar-link"><i data-lucide="tag" style="width: 14px; height: 14px; color: #ec4899;"></i> 6. Format Barcode 100x50mm</a></li>
                <li><a href="#bab7" class="prd-sidebar-link"><i data-lucide="refresh-cw" style="width: 14px; height: 14px; color: #10b981;"></i> 7. Integrasi API Accurate</a></li>
            </ul>
        </div>

        <div style="margin-bottom: 14px;">
            <div class="prd-sidebar-title">⚙️ Operasional & Alur Mutasi</div>
            <ul class="prd-sidebar-links">
                <li><a href="#bab7b" class="prd-sidebar-link"><i data-lucide="route" style="width: 14px; height: 14px; color: #8b5cf6;"></i> 7b. Mutasi, POS & BAST</a></li>
                <li><a href="#bab8" class="prd-sidebar-link"><i data-lucide="shield-check" style="width: 14px; height: 14px; color: #0284c7;"></i> 8. Non-Fungsional</a></li>
                <li><a href="#bab9" class="prd-sidebar-link"><i data-lucide="milestone" style="width: 14px; height: 14px; color: #d97706;"></i> 9. Roadmap Bertahap</a></li>
                <li><a href="#bab10" class="prd-sidebar-link"><i data-lucide="list-checks" style="width: 14px; height: 14px; color: #ef4444;"></i> 10. Checklist Kesiapan</a></li>
            </ul>
        </div>

        <div style="padding: 14px; border-radius: var(--radius); border: 1.5px solid var(--border); background: var(--bg-muted); font-size: 12.5px; margin-top: 14px;">
            <div style="font-weight: 800; color: var(--text-main); margin-bottom: 4px;">🛠️ Kerangka Aplikasi:</div>
            <div style="color: var(--text-muted); line-height: 1.5;">Seluruh modul PHP & skema database telah aktif dan siap digunakan.</div>
            <a href="setup.php" class="btn btn-outline btn-sm" style="width: 100%; margin-top: 10px; font-weight: 700;">
                Inisialisasi Database
            </a>
        </div>
    </aside>

    <!-- Konten Utama PRD -->
    <article class="prd-content">
        <!-- Bab 1 -->
        <section id="bab1">
            <h2><i data-lucide="sparkles" style="color: var(--primary);"></i> 1. Ringkasan Eksekutif & Latar Belakang</h2>
            <p>
                Sistem Barcode Inventory Multi-Mitra dirancang khusus untuk memecahkan kendala pengelolaan stok fisik barang pergudangan dingin (*cold storage* / pergudangan umum) yang memiliki bobot berbeda-beda pada setiap kemasannya (<strong>catch-weight</strong>) dan dimiliki secara terpisah oleh <strong>7 Mitra Bisnis</strong>.
            </p>
            <p>
                Setiap satu kemasan fisik barang diidentifikasi secara unik oleh <strong>satu label barcode</strong> yang merekam data berat aktual timbangan (*netto*), kepemilikan mitra, jenis barang, nomor batch, dan tanggal masuk.
            </p>

            <div class="alert alert-info" style="margin: 22px 0;">
                <i data-lucide="info" style="width: 22px; height: 22px; flex-shrink: 0; margin-top: 2px;"></i>
                <div>
                    <div style="font-weight: 800; font-size: 14.5px; margin-bottom: 4px;">Tiga Fondasi Utama Sistem:</div>
                    <ul style="margin-left: 20px; font-size: 13.5px; line-height: 1.7;">
                        <li><strong>Segregasi Total Multi-Mitra:</strong> Stok terpisah mutlak per 7 mitra (M01 s/d M07) tanpa risiko salah kirim antar mitra.</li>
                        <li><strong>Akurasi Catch-Weight:</strong> Timbangan digital terhubung via USB/RS-232 menghapus kesalahan pengetikan manual (*zero typo*).</li>
                        <li><strong>Sinkronisasi Accurate Otomatis:</strong> Dokumen transaksi terkirim otomatis ke REST API Accurate Online tanpa perlu input ulang.</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Bab 2 -->
        <section id="bab2">
            <h2><i data-lucide="check-circle-2" style="color: #10b981;"></i> 2. Matriks Permasalahan vs Solusi</h2>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="width: 40px;">No</th>
                            <th>Kondisi Masalah Saat Ini</th>
                            <th>Dampak Operasional</th>
                            <th>Solusi dalam Sistem Barcode v1</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge badge-secondary">01</span></td>
                            <td>Barang milik 7 mitra disimpan bercampur di gudang.</td>
                            <td>Sulit mengetahui saldo pasti per mitra; risiko salah kirim barang milik mitra lain.</td>
                            <td>
                                <span class="badge badge-success">✓ Segregasi Digital</span>
                                <div style="font-size: 12.5px; margin-top: 4px; color: var(--text-muted);">Setiap barcode terikat mutlak ke 1 mitra dan divalidasi saat scan keluar.</div>
                            </td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-secondary">02</span></td>
                            <td>Berat kemasan bervariasi (*catch-weight*), dicatat manual di kertas.</td>
                            <td>Salah hitung berat total; klaim selisih kilogram dari pelanggan / mitra.</td>
                            <td>
                                <span class="badge badge-info">✓ Auto-Capture Timbangan</span>
                                <div style="font-size: 12.5px; margin-top: 4px; color: var(--text-muted);">Pembacaan otomatis timbangan digital (resolusi 0.01 kg). Netto = Bruto − Tara.</div>
                            </td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-secondary">03</span></td>
                            <td>Data stok dicatat dua kali (di gudang lalu diinput ulang ke Accurate).</td>
                            <td>Saldo pembukuan Accurate selalu selisih dengan stok fisik riil di gudang.</td>
                            <td>
                                <span class="badge badge-purple">✓ Integrasi API Accurate</span>
                                <div style="font-size: 12.5px; margin-top: 4px; color: var(--text-muted);">Pengiriman otomatis per transaksi via background queue. Tanpa input ganda.</div>
                            </td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-secondary">04</span></td>
                            <td>Laporan saldo ke mitra memakan waktu lama.</td>
                            <td>Admin lembur merekap Excel manual setiap ada permintaan data dari mitra.</td>
                            <td>
                                <span class="badge badge-warning">✓ Ekspor 1-Klik</span>
                                <div style="font-size: 12.5px; margin-top: 4px; color: var(--text-muted);">Matriks stok real-time siap ekspor ke Excel/PDF dalam hitungan detik.</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Bab 3 -->
        <section id="bab3">
            <h2><i data-lucide="target" style="color: #f59e0b;"></i> 3. Ruang Lingkup Proyek (Scope of Work)</h2>
            <div class="grid grid-cols-2" style="margin: 20px 0;">
                <div style="border: 2px solid #10b981; border-radius: var(--radius); padding: 20px; background: rgba(16, 185, 129, 0.04);">
                    <div style="font-weight: 800; font-size: 15px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                        <span class="badge badge-success">Masuk Lingkup</span>
                        <span>Fase 1 (Wajib / P1)</span>
                    </div>
                    <ul style="margin-left: 20px; font-size: 13.5px; line-height: 1.8; color: var(--text-main);">
                        <li>Master 7 Mitra, Jenis Barang, Item SKU (tarik dari Accurate).</li>
                        <li>Stasiun Timbang & Cetak Label Thermal 100 × 50 mm.</li>
                        <li>Penerimaan barang (Inbound) via cetak label otomatis aktif.</li>
                        <li>Pengeluaran barang (Outbound) dengan validasi scan anti-tertukar.</li>
                        <li>Stok Opname digital untuk deteksi label missing & selisih bobot.</li>
                        <li>Laporan Matriks Stok Multi-Mitra (Kg & Kemasan) dan Ekspor Excel.</li>
                        <li>Antrean Sinkronisasi REST API Accurate Online.</li>
                    </ul>
                </div>

                <div style="border: 2px dashed #94a3b8; border-radius: var(--radius); padding: 20px; background: var(--bg-muted);">
                    <div style="font-weight: 800; font-size: 15px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                        <span class="badge badge-secondary">Di Luar Lingkup</span>
                        <span>Fase 2 (Rilis Lanjutan)</span>
                    </div>
                    <ul style="margin-left: 20px; font-size: 13.5px; line-height: 1.8; color: var(--text-muted);">
                        <li>Modul Pembelian & Penjualan penuh (tetap di Accurate Online).</li>
                        <li>Penagihan / Invoice biaya sewa gudang ke mitra.</li>
                        <li>Portal login mandiri (*client portal*) untuk mitra eksternal.</li>
                        <li>Aplikasi native iOS/Android (Fase 1 menggunakan web responsif).</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Bab 4 -->
        <section id="bab4">
            <h2><i data-lucide="users" style="color: #8b5cf6;"></i> 4. Pengguna & Hak Akses (User Roles)</h2>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Peran (Role)</th>
                            <th>Tugas Utama</th>
                            <th>Hak Akses Menu</th>
                            <th>Perangkat Utama</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Admin Sistem</strong></td>
                            <td>Kelola user, master mitra, koneksi API Accurate, konfigurasi sistem.</td>
                            <td><span class="badge badge-default">Semua Menu</span></td>
                            <td>PC Desktop</td>
                        </tr>
                        <tr>
                            <td><strong>Supervisor Gudang</strong></td>
                            <td>Otorisasi toleransi berat abnormal, persetujuan void label, validasi opname.</td>
                            <td><span class="badge badge-purple">Approval & Transaksi</span></td>
                            <td>PC / Tablet</td>
                        </tr>
                        <tr>
                            <td><strong>Operator Penerimaan</strong></td>
                            <td>Menimbang kemasan, cetak label barcode, terima barang masuk.</td>
                            <td><span class="badge badge-info">Timbang & Penerimaan</span></td>
                            <td>PC Stasiun Timbang</td>
                        </tr>
                        <tr>
                            <td><strong>Operator Pengeluaran</strong></td>
                            <td>Scan barcode untuk pengiriman keluar, validasi barang, mutasi rak.</td>
                            <td><span class="badge badge-success">Pengeluaran & Opname</span></td>
                            <td>Handheld Android / HP</td>
                        </tr>
                        <tr>
                            <td><strong>Admin Keuangan</strong></td>
                            <td>Pantau status sinkron Accurate, kirim ulang antrean yang tertunda.</td>
                            <td><span class="badge badge-warning">Laporan & Antrean API</span></td>
                            <td>PC Desktop</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Bab 5 -->
        <section id="bab5">
            <h2><i data-lucide="database" style="color: #0ea5e9;"></i> 5. Konsep & Struktur Data Utama</h2>
            <p>
                Formula utama sistem: <strong>1 Kemasan Fisik = 1 Label Barcode Unik</strong>. Stok dihitung dalam dual metric:
            </p>
            <div class="grid grid-cols-2" style="margin: 18px 0;">
                <div style="border: 2px solid rgba(14, 165, 233, 0.4); border-radius: var(--radius); padding: 18px; background: rgba(14, 165, 233, 0.05);">
                    <div style="font-size: 11.5px; font-weight: 800; text-transform: uppercase; color: #0284c7;">Satuan Utama</div>
                    <div style="font-size: 24px; font-weight: 900; color: #0284c7; margin: 4px 0;">Kilogram (Kg)</div>
                    <div style="font-size: 12.5px; color: var(--text-muted);">Resolusi 0.01 kg (Netto = Bruto − Tara)</div>
                </div>
                <div style="border: 2px solid rgba(16, 185, 129, 0.4); border-radius: var(--radius); padding: 18px; background: rgba(16, 185, 129, 0.05);">
                    <div style="font-size: 11.5px; font-weight: 800; text-transform: uppercase; color: #10b981;">Satuan Bantu</div>
                    <div style="font-size: 24px; font-weight: 900; color: #10b981; margin: 4px 0;">Jumlah Kemasan (Pcs)</div>
                    <div style="font-size: 12.5px; color: var(--text-muted);">Pencocokan unit fisik saat stok opname</div>
                </div>
            </div>
        </section>

        <!-- Bab 6 -->
        <section id="bab6">
            <h2><i data-lucide="tag" style="color: #ec4899;"></i> 6. Desain Barcode & Label Thermal (100 × 50 mm)</h2>
            <p>
                Label barcode dicetak menggunakan printer thermal stiker ukuran standar industri 100 × 50 mm. Simbologi menggunakan <strong>Code 128 (Auto)</strong> yang terbaca cepat di semua barcode scanner 1D dan 2D.
            </p>

            <div style="background: rgba(79, 70, 229, 0.08); border: 2px solid rgba(79, 70, 229, 0.3); border-radius: var(--radius); padding: 18px; margin: 18px 0;">
                <div style="font-size: 12px; color: var(--primary); font-weight: 800; text-transform: uppercase;">FORMAT PENOMORAN BARCODE ID:</div>
                <div style="font-family: 'JetBrains Mono', monospace; font-size: 18px; font-weight: 800; color: var(--text-main); margin: 6px 0;">
                    [KodeMitra]-[KodeJenis]-[YYMMDD]-[Urut 5 digit]
                </div>
                <div style="font-size: 13px; color: var(--text-muted); margin-top: 6px;">
                    Contoh: <code>M03-JB02-261022-00045</code> (Mitra 03 • Frozen Cuts • Tgl 22-10-2026 • Urut #45)
                </div>
            </div>

            <div style="margin: 22px 0;">
                <a href="index.php?page=timbang_cetak" class="btn btn-primary btn-lg">
                    <i data-lucide="printer" style="width: 18px; height: 18px;"></i>
                    <span>Buka Stasiun Cetak Label Thermal 100x50 mm</span>
                </a>
            </div>
        </section>

        <!-- Bab 7 -->
        <section id="bab7">
            <h2><i data-lucide="refresh-cw" style="color: #10b981;"></i> 7. Integrasi REST API Accurate Online</h2>
            <p>
                Sistem barcode mengagregasi kemasan per transaksi (per SKU, per mitra, total kg) sebelum mengirim payload ke Accurate Online agar pembukuan akuntansi tetap ringkas tanpa ribuan baris label individu.
            </p>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Entitas Sistem Barcode</th>
                            <th>Entitas Accurate Online</th>
                            <th>Arah Data</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Master Item / SKU</td>
                            <td>Barang & Jasa (<code>item/list.do</code>)</td>
                            <td>Accurate ➔ Barcode</td>
                            <td>Accurate sebagai master data SKU tunggal</td>
                        </tr>
                        <tr>
                            <td>Mitra Bisnis (7 Mitra)</td>
                            <td>Gudang per Mitra (<code>GDG-M01</code> s/d <code>GDG-M07</code>)</td>
                            <td>Terkonfigurasi Sekali</td>
                            <td>1 Mitra = 1 Gudang di Accurate</td>
                        </tr>
                        <tr>
                            <td>Pengeluaran Barang</td>
                            <td>Penyesuaian Persediaan (Pengurangan)</td>
                            <td>Barcode ➔ Accurate</td>
                            <td>Kirim total Kg per SKU via Background Queue</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Bab 7b: Mutasi & POS Accurate -->
        <section id="bab7b">
            <h2><i data-lucide="route" style="color: #8b5cf6;"></i> 7b. Mutasi Pergerakan Barang & Integrasi POS Accurate</h2>
            <p>
                Sistem mengadopsi <strong>Alur Pergerakan Fisik Multi-Tahap (Custody Transfer)</strong> yang menghubungkan sumber bahan baku, gudang penyimpanan 7 mitra, ruang kasir display office, hingga pembeli langsung kasir POS:
            </p>

            <div class="grid grid-cols-2" style="margin: 20px 0; gap: 16px;">
                <div style="border: 1.5px solid #10b981; border-radius: var(--radius); padding: 18px; background: rgba(16, 185, 129, 0.05);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                        <span class="badge badge-success">Alur 01</span>
                        <strong style="color: #10b981;">Pengepul ➔ Gudang 7 Mitra</strong>
                    </div>
                    <div style="font-size: 13px; color: var(--text-muted); line-height: 1.6;">
                        Penerimaan bahan mentah/karkas dari peternak/pengepul. Ditimbang catch-weight dan dilabeli barcode unik untuk masuk ke stok mitra.
                    </div>
                </div>

                <div style="border: 1.5px solid #8b5cf6; border-radius: var(--radius); padding: 18px; background: rgba(139, 92, 246, 0.05);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                        <span class="badge badge-purple">Alur 02</span>
                        <strong style="color: #8b5cf6;">Gudang Mitra ➔ Office POS</strong>
                    </div>
                    <div style="font-size: 13px; color: var(--text-muted); line-height: 1.6;">
                        Mutasi persediaan ke display toko office. Tersinkron otomatis ke endpoint Accurate <code>item-transfer/save.do</code> (Pindah Barang).
                    </div>
                </div>

                <div style="border: 1.5px solid #f59e0b; border-radius: var(--radius); padding: 18px; background: rgba(245, 158, 11, 0.05);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                        <span class="badge badge-warning">Alur 03</span>
                        <strong style="color: #d97706;">Office POS ➔ Pembeli Langsung</strong>
                    </div>
                    <div style="font-size: 13px; color: var(--text-muted); line-height: 1.6;">
                        Penjualan kasir POS. Scan barcode kemasan, otomatis membukukan Faktur Penjualan <code>sales-invoice/save.do</code> di Accurate Online.
                    </div>
                </div>

                <div style="border: 1.5px solid #0ea5e9; border-radius: var(--radius); padding: 18px; background: rgba(14, 165, 233, 0.05);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                        <span class="badge badge-info">Alur 04</span>
                        <strong style="color: #0284c7;">Mutasi Antar Rak / Gudang</strong>
                    </div>
                    <div style="font-size: 13px; color: var(--text-muted); line-height: 1.6;">
                        Reposisi fisik antar rak atau perpindahan kamar pendingin (cold storage) tanpa mengubah kepemilikan mitra.
                    </div>
                </div>
            </div>

            <div style="border: 2px solid rgba(139, 92, 246, 0.3); border-radius: var(--radius); padding: 20px; background: var(--bg-card); margin: 20px 0;">
                <h3 style="font-size: 16px; font-weight: 800; color: #8b5cf6; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="file-check-2" style="width: 20px; height: 20px;"></i>
                    Berita Acara Serah Terima (BAST) Mengikat User Login Aktif
                </h3>
                <p style="font-size: 13.5px; color: var(--text-muted); line-height: 1.7;">
                    Setiap mutasi barang mencatat pihak penyerah dan penerima secara resmi. Pihak penerima <strong>terkunci secara otomatis berdasarkan sesi User Login</strong> yang sedang aktif, menghasilkan dokumen resmi berformat A4 dengan nomor BAST unik (<code>BAST-YYMMDD-XXXX</code>), tabel paket barang, bobot netto catch-weight, serta 3 kolom tanda tangan legal.
                </p>
                <div style="display: flex; gap: 12px; margin-top: 14px;">
                    <a href="index.php?page=mutasi" class="btn btn-primary">
                        <i data-lucide="route" style="width: 16px; height: 16px;"></i>
                        <span>Buka Stasiun Mutasi & BAST</span>
                    </a>
                    <a href="index.php?page=bast_print&id=1" target="_blank" class="btn btn-outline">
                        <i data-lucide="printer" style="width: 16px; height: 16px;"></i>
                        <span>Lihat Contoh Cetak BAST A4</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- Bab 8 & 9 -->
        <section id="bab8">
            <h2><i data-lucide="shield-check" style="color: #0284c7;"></i> 8. Kebutuhan Non-Fungsional</h2>
            <div class="grid grid-cols-2" style="margin: 18px 0;">
                <div style="border: 1.5px solid var(--border); border-radius: var(--radius); padding: 18px; background: var(--bg-card);">
                    <div style="font-weight: 800; font-size: 14px; margin-bottom: 8px; color: var(--primary);">⚡ Kecepatan Respon</div>
                    <ul style="font-size: 13px; color: var(--text-muted); margin-left: 20px; line-height: 1.8;">
                        <li>Respon Scan Barcode &lt; 0.8 detik</li>
                        <li>Proses Timbang hingga Cetak &lt; 2.5 detik</li>
                        <li>Audio Feedback Beep langsung dari Web Audio API</li>
                    </ul>
                </div>
                <div style="border: 1.5px solid var(--border); border-radius: var(--radius); padding: 18px; background: var(--bg-card);">
                    <div style="font-weight: 800; font-size: 14px; margin-bottom: 8px; color: #10b981;">🛡️ Keandalan Offline</div>
                    <ul style="font-size: 13px; color: var(--text-muted); margin-left: 20px; line-height: 1.8;">
                        <li>Gudang tetap menimbang & mencetak saat internet putus</li>
                        <li>Buffer queue menampung transaksi secara lokal</li>
                        <li>Retry otomatis 3× saat koneksi Accurate pulih</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Bab 10 -->
        <section id="bab10">
            <h2><i data-lucide="list-checks" style="color: #ef4444;"></i> 10. Checklist Kesiapan & Pertanyaan Terbuka</h2>
            <div style="border: 1.5px solid var(--border); border-radius: var(--radius); padding: 18px; background: var(--bg-card);">
                <ul style="font-size: 13.5px; color: var(--text-muted); line-height: 2.2; margin-left: 20px;">
                    <li>[✓] <strong style="color: var(--text-main);">Format Barcode:</strong> Code 128 (<code>[Mitra]-[Jenis]-[YYMMDD]-[Urut]</code>) - <em>Disetujui</em></li>
                    <li>[✓] <strong style="color: var(--text-main);">Ukuran Fisik Label:</strong> 100 × 50 mm Thermal - <em>Disetujui</em></li>
                    <li>[ ] <strong style="color: var(--text-main);">Pemetaan Gudang Accurate:</strong> Konfirmasi nama kode gudang 7 Mitra bersama tim akuntansi.</li>
                    <li>[ ] <strong style="color: var(--text-main);">Pengadaan Hardware:</strong> Printer thermal 4 inci (TSC/Zebra) & Timbangan serial/USB.</li>
                </ul>
            </div>
        </section>
    </article>
</div>

<script>
    const searchInput = document.getElementById('prdSearch');
    const sidebarLinks = document.querySelectorAll('.prd-sidebar-link');

    searchInput.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        sidebarLinks.forEach(link => {
            const matches = link.innerText.toLowerCase().includes(q);
            link.parentElement.style.display = matches ? '' : 'none';
        });
    });
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
