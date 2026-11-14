-- =====================================================================
-- DATABASE SEEDER: DATA AWAL SISTEM BARCODE MULTI-MITRA
-- Memuat 7 Mitra, Kategori Barang, SKU dari Accurate, dan Stok Awal
-- =====================================================================

USE `db_barcode_inventory`;

-- 1. SEED PENGGUNA AWAL (Password default: '123456')
-- Hash bcrypt untuk '123456': $2y$10$vN0h284a7M71Gf1/E81e8ebm0zYQvUvL3dwhD43v2L1kK342tXb.S
INSERT INTO `users` (`id`, `username`, `password_hash`, `nama_lengkap`, `role`) VALUES
(1, 'nia', '$2y$10$vN0h284a7M71Gf1/E81e8ebm0zYQvUvL3dwhD43v2L1kK342tXb.S', 'Nia', 'ADMIN'),
(2, 'arif', '$2y$10$vN0h284a7M71Gf1/E81e8ebm0zYQvUvL3dwhD43v2L1kK342tXb.S', 'Arif', 'ARIF'),
(3, 'operator', '$2y$10$vN0h284a7M71Gf1/E81e8ebm0zYQvUvL3dwhD43v2L1kK342tXb.S', 'Operator Gudang', 'OPERATOR')
ON DUPLICATE KEY UPDATE `username`=VALUES(`username`), `nama_lengkap`=VALUES(`nama_lengkap`), `role`=VALUES(`role`);

-- 2. SEED 7 MITRA BISNIS (M01 - M07)
INSERT INTO `mitra` (`kode_mitra`, `nama_mitra`, `pic_nama`, `pic_telepon`, `kode_gudang_accurate`, `warna_identitas`) VALUES
('M01', 'PT Sumber Pangan Prima', 'Hendra Wijaya', '0812-3456-7891', 'GDG-M01-SUMBERPANGAN', '#2563eb'),
('M02', 'CV Berkah Ternak Mandiri', 'Ustadz Mansur', '0813-8822-1102', 'GDG-M02-BERKAHTERNAK', '#16a34a'),
('M03', 'PT Nusantara Cold Storage', 'Agus Setiawan', '0811-9988-7703', 'GDG-M03-NUSANTARA', '#d97706'),
('M04', 'PT Global Agri Sentosa', 'Dewi Lestari', '0821-4455-6604', 'GDG-M04-GLOBALAGRI', '#9333ea'),
('M05', 'CV Mitra Makmur Daging', 'Surya Saputra', '0852-1122-3305', 'GDG-M05-MITRAMAKMUR', '#0891b2'),
('M06', 'PT Indo Jaya Sejahtera', 'Kevin Hartono', '0818-7766-5506', 'GDG-M06-INDOJAYA', '#e11d48'),
('M07', 'Koperasi Bersama Peternak', 'Joko Widodo SE', '0878-3344-5507', 'GDG-M07-KOPERASI', '#4f46e5')
ON DUPLICATE KEY UPDATE `nama_mitra`=VALUES(`nama_mitra`);

-- 3. SEED JENIS BARANG (Kategori Produk Catch-Weight)
INSERT INTO `jenis_barang` (`kode_jenis`, `nama_jenis`, `satuan_stok`, `toleransi_min_kg`, `toleransi_maks_kg`, `keterangan`) VALUES
('JB01', 'Karkas Sapi Segar', 'kg', 150.00, 450.00, 'Daging karkas besar 1/4 atau 1/2 ekor'),
('JB02', 'Potongan Beku / Frozen Cuts', 'kg', 10.00, 35.00, 'Karton kemasan potongan primer beku (Sirloin, Ribeye, dll)'),
('JB03', 'Vakum Pack Daging Olahan', 'kg', 2.00, 10.00, 'Daging iris/giling dalam kemasan vakum pack khusus retail'),
('JB04', 'Jeroan & Produk Sampingan (Offal)', 'kg', 5.00, 25.00, 'Hati, paru, babat, lidah dalam karung/box berpendingin')
ON DUPLICATE KEY UPDATE `nama_jenis`=VALUES(`nama_jenis`);

-- 4. SEED MASTER ITEM / SKU (Mewakili data master Accurate Online)
INSERT INTO `items` (`kode_item`, `nama_item`, `jenis_id`, `berat_standar_kg`, `default_tara_kg`, `satuan_accurate`) VALUES
('ACC-BEEF-001', 'Sirloin Cut Beku Grade A', 2, 25.00, 0.650, 'KG'),
('ACC-BEEF-002', 'Tenderloin Vacuum Pack', 3, 5.00, 0.200, 'KG'),
('ACC-BEEF-003', 'Rib Eye Roll Australia', 2, 22.50, 0.600, 'KG'),
('ACC-BEEF-004', 'Sandung Lamur / Brisket', 2, 24.00, 0.650, 'KG'),
('ACC-BEEF-005', 'Daging Giling 80/20 Beku', 2, 20.00, 0.500, 'KG'),
('ACC-OFFAL-001', 'Hati Sapi Segar (Liver Box)', 4, 15.00, 0.400, 'KG'),
('ACC-OFFAL-002', 'Babat Putih Siap Olah', 4, 12.00, 0.350, 'KG'),
('ACC-KARKAS-001', 'Karkas Belakang (Hindquarter)', 1, 180.00, 1.200, 'KG')
ON DUPLICATE KEY UPDATE `nama_item`=VALUES(`nama_item`);

-- 5. SEED LOKASI GUDANG & RAK
INSERT INTO `gudang_lokasi` (`kode_lokasi`, `nama_lokasi`, `gudang_induk`, `suhu_ruang`) VALUES
('GDG-COLD-A01', 'Cold Storage Kamar 1 - Rak A1', 'Cold Storage Utama', '-18°C s/d -22°C (Frozen)'),
('GDG-COLD-A02', 'Cold Storage Kamar 1 - Rak A2', 'Cold Storage Utama', '-18°C s/d -22°C (Frozen)'),
('GDG-COLD-B01', 'Cold Storage Kamar 2 - Rak B1', 'Cold Storage Utama', '-18°C s/d -22°C (Frozen)'),
('GDG-CHILL-C01', 'Chilled Room 1 - Area Segar C1', 'Gudang Chilled', '0°C s/d 4°C (Chilled)'),
('GDG-STAGE-OUT', 'Staging Area Pengiriman Keluar', 'Gudang Utama', '10°C s/d 15°C (Transit)')
ON DUPLICATE KEY UPDATE `nama_lokasi`=VALUES(`nama_lokasi`);

-- 6. SEED SAMPEL LABEL BARCODE (Untuk demonstrasi stok real-time)
-- Format: [KodeMitra]-[KodeJenis]-[YYMMDD]-[Urut 5 digit]
INSERT INTO `labels` (`id_label`, `mitra_id`, `item_id`, `lokasi_id`, `berat_bruto`, `berat_tara`, `berat_netto`, `batch_no`, `tgl_produksi`, `status`, `operator_timbang_id`) VALUES
('M01-JB02-261022-00001', 1, 1, 1, 25.10, 0.650, 24.45, 'BATCH-20261022-A', '2026-10-22', 'AKTIF', 3),
('M01-JB02-261022-00002', 1, 1, 1, 24.80, 0.650, 24.15, 'BATCH-20261022-A', '2026-10-22', 'AKTIF', 3),
('M01-JB02-261022-00003', 1, 3, 1, 22.90, 0.600, 22.30, 'BATCH-20261022-A', '2026-10-22', 'AKTIF', 3),
('M02-JB02-261022-00001', 2, 4, 2, 24.70, 0.650, 24.05, 'BATCH-20261022-B', '2026-10-22', 'AKTIF', 3),
('M02-JB02-261022-00002', 2, 4, 2, 25.30, 0.650, 24.65, 'BATCH-20261022-B', '2026-10-22', 'AKTIF', 3),
('M03-JB02-261022-00001', 3, 1, 3, 26.00, 0.650, 25.35, 'BATCH-20261022-C', '2026-10-22', 'AKTIF', 3),
('M03-JB03-261022-00002', 3, 2, 4, 5.25, 0.200, 5.05, 'BATCH-20261022-C', '2026-10-22', 'AKTIF', 3),
('M04-JB04-261022-00001', 4, 6, 4, 15.60, 0.400, 15.20, 'BATCH-20261022-D', '2026-10-22', 'AKTIF', 3),
('M05-JB02-261022-00001', 5, 5, 1, 20.80, 0.500, 20.30, 'BATCH-20261022-E', '2026-10-22', 'AKTIF', 3),
('M06-JB02-261022-00001', 6, 3, 2, 23.40, 0.600, 22.80, 'BATCH-20261022-F', '2026-10-22', 'AKTIF', 3),
('M07-JB01-261022-00001', 7, 8, 4, 185.00, 1.200, 183.80, 'BATCH-20261022-G', '2026-10-22', 'AKTIF', 3)
ON DUPLICATE KEY UPDATE `berat_netto`=VALUES(`berat_netto`);
