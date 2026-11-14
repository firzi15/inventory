/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.16-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: db_barcode_inventory
-- ------------------------------------------------------
-- Server version	10.11.16-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accurate_sync_queue`
--

DROP TABLE IF EXISTS `accurate_sync_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accurate_sync_queue` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `transaksi_id` bigint(20) NOT NULL,
  `endpoint` varchar(150) NOT NULL,
  `payload_json` longtext NOT NULL,
  `attempt_count` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 3,
  `last_attempt_at` datetime DEFAULT NULL,
  `status` enum('PENDING','PROCESSING','SUCCESS','FAILED') NOT NULL DEFAULT 'PENDING',
  `response_code` int(11) DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `transaksi_id` (`transaksi_id`),
  KEY `idx_sync_status` (`status`,`attempt_count`),
  CONSTRAINT `accurate_sync_queue_ibfk_1` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi_stok` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accurate_sync_queue`
--

LOCK TABLES `accurate_sync_queue` WRITE;
/*!40000 ALTER TABLE `accurate_sync_queue` DISABLE KEYS */;
/*!40000 ALTER TABLE `accurate_sync_queue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ap_alokasi_pembayaran`
--

DROP TABLE IF EXISTS `ap_alokasi_pembayaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ap_alokasi_pembayaran` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `pembayaran_id` bigint(20) NOT NULL,
  `no_surat_jalan` varchar(100) DEFAULT '',
  `invoice_id` bigint(20) DEFAULT NULL,
  `nominal_alokasi` decimal(14,2) NOT NULL DEFAULT 0.00,
  `sisa_utang_setelah` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ap_alokasi_bayar` (`pembayaran_id`),
  KEY `idx_ap_alokasi_sj` (`no_surat_jalan`),
  CONSTRAINT `fk_ap_alokasi_pembayaran` FOREIGN KEY (`pembayaran_id`) REFERENCES `ap_pembayaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ap_alokasi_pembayaran`
--

LOCK TABLES `ap_alokasi_pembayaran` WRITE;
/*!40000 ALTER TABLE `ap_alokasi_pembayaran` DISABLE KEYS */;
/*!40000 ALTER TABLE `ap_alokasi_pembayaran` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ap_pembayaran`
--

DROP TABLE IF EXISTS `ap_pembayaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ap_pembayaran` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `no_bukti_keluar` varchar(50) NOT NULL,
  `pemasok_id` int(11) NOT NULL,
  `tanggal_bayar` date NOT NULL,
  `metode_bayar` enum('TRANSFER_BANK','TUNAI') NOT NULL DEFAULT 'TRANSFER_BANK',
  `bank_sumber` varchar(100) DEFAULT NULL,
  `no_rekening_tujuan` varchar(100) DEFAULT NULL,
  `jumlah_bayar` decimal(14,2) NOT NULL DEFAULT 0.00,
  `keterangan` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_bukti_keluar` (`no_bukti_keluar`),
  KEY `idx_ap_pembayaran_pemasok` (`pemasok_id`),
  KEY `idx_ap_pembayaran_tanggal` (`tanggal_bayar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ap_pembayaran`
--

LOCK TABLES `ap_pembayaran` WRITE;
/*!40000 ALTER TABLE `ap_pembayaran` DISABLE KEYS */;
/*!40000 ALTER TABLE `ap_pembayaran` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ar_alokasi_pembayaran`
--

DROP TABLE IF EXISTS `ar_alokasi_pembayaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ar_alokasi_pembayaran` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `pembayaran_id` bigint(20) NOT NULL,
  `transaksi_stok_id` bigint(20) NOT NULL,
  `nominal_alokasi` decimal(14,2) NOT NULL DEFAULT 0.00,
  `sisa_piutang_setelah` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ar_alokasi_bayar` (`pembayaran_id`),
  KEY `idx_ar_alokasi_trx` (`transaksi_stok_id`),
  CONSTRAINT `fk_alokasi_pembayaran` FOREIGN KEY (`pembayaran_id`) REFERENCES `ar_pembayaran` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alokasi_transaksi` FOREIGN KEY (`transaksi_stok_id`) REFERENCES `transaksi_stok` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ar_alokasi_pembayaran`
--

LOCK TABLES `ar_alokasi_pembayaran` WRITE;
/*!40000 ALTER TABLE `ar_alokasi_pembayaran` DISABLE KEYS */;
/*!40000 ALTER TABLE `ar_alokasi_pembayaran` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ar_pembayaran`
--

DROP TABLE IF EXISTS `ar_pembayaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ar_pembayaran` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `no_bukti_bayar` varchar(50) NOT NULL,
  `mitra_id` int(11) NOT NULL,
  `tanggal_bayar` date NOT NULL,
  `metode_bayar` enum('TUNAI','TRANSFER_BANK') NOT NULL DEFAULT 'TUNAI',
  `bank_tujuan` varchar(100) DEFAULT NULL,
  `jumlah_bayar` decimal(14,2) NOT NULL DEFAULT 0.00,
  `keterangan` text DEFAULT NULL,
  `status_rekonsiliasi` enum('PENDING','MATCHED','SELISIH') NOT NULL DEFAULT 'PENDING',
  `nominal_aktual` decimal(14,2) DEFAULT NULL,
  `selisih_nominal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `bukti_transfer` varchar(255) DEFAULT NULL,
  `catatan_rekonsiliasi` varchar(255) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_bukti_bayar` (`no_bukti_bayar`),
  KEY `idx_ar_pembayaran_mitra` (`mitra_id`),
  KEY `idx_ar_pembayaran_tanggal` (`tanggal_bayar`),
  KEY `idx_ar_rekon_status` (`status_rekonsiliasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ar_pembayaran`
--

LOCK TABLES `ar_pembayaran` WRITE;
/*!40000 ALTER TABLE `ar_pembayaran` DISABLE KEYS */;
/*!40000 ALTER TABLE `ar_pembayaran` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action_name` varchar(50) NOT NULL,
  `target_table` varchar(50) NOT NULL,
  `target_id` varchar(100) NOT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_action` (`action_name`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_mutasi`
--

DROP TABLE IF EXISTS `bank_mutasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_mutasi` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `bank_rekening_id` int(11) NOT NULL,
  `tanggal_mutasi` date NOT NULL,
  `tipe_mutasi` enum('CR','DB') NOT NULL DEFAULT 'CR',
  `nominal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `deskripsi` varchar(255) NOT NULL,
  `no_referensi` varchar(100) DEFAULT NULL,
  `status_rekonsiliasi` enum('UNMATCHED','MATCHED','SELISIH') NOT NULL DEFAULT 'UNMATCHED',
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bm_rekening` (`bank_rekening_id`),
  KEY `idx_bm_tanggal` (`tanggal_mutasi`),
  KEY `idx_bm_status` (`status_rekonsiliasi`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_mutasi`
--

LOCK TABLES `bank_mutasi` WRITE;
/*!40000 ALTER TABLE `bank_mutasi` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_mutasi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_rekening`
--

DROP TABLE IF EXISTS `bank_rekening`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_rekening` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_bank` varchar(20) NOT NULL,
  `nama_bank` varchar(100) NOT NULL,
  `no_rekening` varchar(50) NOT NULL,
  `atas_nama` varchar(100) NOT NULL,
  `saldo_berjalan` decimal(14,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_rekening`
--

LOCK TABLES `bank_rekening` WRITE;
/*!40000 ALTER TABLE `bank_rekening` DISABLE KEYS */;
INSERT INTO `bank_rekening` VALUES
(1,'BCA','Bank Central Asia (BCA)','8820-1928-37','PT WMS INVENTORY PUSAT',15000000.00,1,'2026-11-01 04:35:33'),
(2,'MANDIRI','Bank Mandiri','132-00-9182371-2','PT WMS INVENTORY PUSAT',8500000.00,1,'2026-11-01 04:35:33'),
(3,'BRI','Bank Rakyat Indonesia (BRI)','0341-01-002918-50-3','PT WMS INVENTORY PUSAT',5000000.00,1,'2026-11-01 04:35:33');
/*!40000 ALTER TABLE `bank_rekening` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_rekonsiliasi`
--

DROP TABLE IF EXISTS `bank_rekonsiliasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_rekonsiliasi` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `mutasi_id` bigint(20) NOT NULL,
  `ar_pembayaran_id` bigint(20) NOT NULL,
  `tanggal_rekonsiliasi` date NOT NULL,
  `nominal_matched` decimal(14,2) NOT NULL DEFAULT 0.00,
  `selisih` decimal(14,2) NOT NULL DEFAULT 0.00,
  `catatan` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_br_mutasi` (`mutasi_id`),
  KEY `idx_br_ar` (`ar_pembayaran_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_rekonsiliasi`
--

LOCK TABLES `bank_rekonsiliasi` WRITE;
/*!40000 ALTER TABLE `bank_rekonsiliasi` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_rekonsiliasi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gudang_lokasi`
--

DROP TABLE IF EXISTS `gudang_lokasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gudang_lokasi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_lokasi` varchar(50) NOT NULL,
  `nama_lokasi` varchar(100) NOT NULL,
  `gudang_induk` varchar(100) NOT NULL DEFAULT 'Gudang Utama',
  `suhu_ruang` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_lokasi` (`kode_lokasi`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gudang_lokasi`
--

LOCK TABLES `gudang_lokasi` WRITE;
/*!40000 ALTER TABLE `gudang_lokasi` DISABLE KEYS */;
INSERT INTO `gudang_lokasi` VALUES
(1,'GDG-COLD-A01','Cold Storage Kamar 1 - Rak A1','Cold Storage Utama','-18°C s/d -22°C (Frozen)',1,'2026-10-22 09:21:48'),
(2,'GDG-COLD-A02','Cold Storage Kamar 1 - Rak A2','Cold Storage Utama','-18°C s/d -22°C (Frozen)',1,'2026-10-22 09:21:48'),
(3,'GDG-COLD-B01','Cold Storage Kamar 2 - Rak B1','Cold Storage Utama','-18°C s/d -22°C (Frozen)',1,'2026-10-22 09:21:48'),
(4,'GDG-CHILL-C01','Chilled Room 1 - Area Segar C1','Gudang Chilled','0°C s/d 4°C (Chilled)',1,'2026-10-22 09:21:48'),
(5,'GDG-STAGE-OUT','Staging Area Pengiriman Keluar','Gudang Utama','10°C s/d 15°C (Transit)',1,'2026-10-22 09:21:48'),
(6,'GDG-OFFICE-POS','Office & Display Toko POS','Office Utama','AC Ruang (20°C - 24°C)',1,'2026-10-22 10:02:44'),
(7,'SRC-PENGEPUL-01','Pengepul / Peternak Mitra Jawa Barat','Pemasok Pengepul','Area Penerimaan Luar',1,'2026-10-22 10:02:44'),
(8,'DEST-CUSTOMER-POS','Pelanggan / Kasir POS Accurate','POS Customer','Langsung Serah Terima',1,'2026-10-22 10:02:44');
/*!40000 ALTER TABLE `gudang_lokasi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `items`
--

DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_item` varchar(50) NOT NULL,
  `nama_item` varchar(200) NOT NULL,
  `jenis_id` int(11) NOT NULL,
  `berat_standar_kg` decimal(8,2) DEFAULT NULL,
  `default_tara_kg` decimal(6,3) NOT NULL DEFAULT 0.500,
  `satuan_accurate` varchar(20) DEFAULT 'KG',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_item` (`kode_item`),
  KEY `jenis_id` (`jenis_id`),
  CONSTRAINT `items_ibfk_1` FOREIGN KEY (`jenis_id`) REFERENCES `jenis_barang` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=155 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `items`
--

LOCK TABLES `items` WRITE;
/*!40000 ALTER TABLE `items` DISABLE KEYS */;
INSERT INTO `items` VALUES
(24,'CM-001','CB SPR JOS',2,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(25,'CM-002','BK M',2,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(26,'IK-001','SMG',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(27,'IK-002','LAYANG R',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(28,'IK-003','KAPAS MURNI',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(29,'IK-004','KAPASAN RB',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(30,'IK-005','LAYANG K 1 N',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(31,'TR-001','TOGE JOZ 1508',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(32,'TR-002','GL',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(33,'TR-003','BONTIO',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(34,'TR-004','BODSI',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(35,'IK-006','KAPAS ASIN',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(36,'TR-005','JKK PREMIUM',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(37,'IK-007','TB SPR',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(38,'IK-008','BANDENG',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(39,'IK-009','DENDENG MANIS',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(40,'IK-010','JAMBAL',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(41,'IK-011','TAWES',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(42,'IK-012','KBBM POL',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(43,'TR-006','TN JINB CIPARAGE',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(44,'IK-013','LAYUR COP',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(45,'IK-014','LINCI KSR',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(46,'IK-015','AMIGO',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(47,'TR-007','TOUGE LAUT ASLI',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(48,'IK-016','TB COP',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(49,'IK-017','BWBB',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(50,'IK-018','GBS SPR AA',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(51,'TR-008','JKK SPR',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(52,'TR-009','JK TOP',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(53,'IK-019','BNBB',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(54,'TR-010','JK B',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(55,'UD-001','RBN ABN 2',1,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(56,'TR-011','JK 0708',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(57,'TR-012','JKK BLH',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(58,'TR-013','TOUGE JOS 1508',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(59,'IK-020','JAPU RBS',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(60,'IK-021','KALAPAN SPR JOS',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(61,'IK-022','TAWAR BUNGA',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(62,'TR-014','BLH PANIMBANG',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(63,'IK-023','TERI BATU',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(64,'IK-024','KB RBS',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(65,'IK-025','PALA BATU',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(66,'UD-002','RBN GJAH',1,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(67,'TR-015','JK KING',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(68,'TR-016','GLAGAH',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(69,'TR-017','JK',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(70,'TR-018','JKK JOZ',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(71,'IK-026','SELAR B-3',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(72,'IK-027','TWR BUNGA EXPORT',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(73,'TR-019','TN JMB JSP',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(74,'TR-020','TAWAR KATAK',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(75,'TR-021','ACMK SPR',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(76,'UD-003','RBN VIP',1,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(77,'TR-022','TWR HTM',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(78,'IK-028','BILIS TWR',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(79,'CM-003','JARUM K',2,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(80,'IK-029','KBB M VIP',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(81,'IK-030','BHBB',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(82,'TR-023','BLH MINI SPR',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(83,'IK-031','SMG TWR',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(84,'IK-032','PATAU',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(85,'IK-033','HRK',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(86,'UD-004','UDANG TWR',1,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(87,'IK-034','B.A SPR',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(88,'TR-024','TERI BUNGA HALUS',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(89,'CM-004','JARUM *',2,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(90,'TR-025','BONTIO PATEN 2207',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(91,'TR-026','JK SPR',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(92,'TR-027','CMKK PREMIUM',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(93,'IK-035','CUCUT JARI',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(94,'TR-028','TAWAR MURNI',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(95,'TR-029','BILIS R',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(96,'IK-036','PETEK',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(97,'IK-037','SMG BULAT',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(98,'IK-038','KBB 1 TOP',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(99,'TR-030','TOUGE CANTIK 1508',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(100,'TR-031','TOUGE JOZ',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(101,'IK-039','BNBB SPR JSP',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(102,'IK-040','HEPPI JUMBO',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(103,'IK-041','SR NORMAL CANTIK',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(104,'TR-032','TWR HLS 2508',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(105,'TR-033','JK BLH X BARU',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(106,'IK-042','BNBB POL A',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(107,'TR-034','TN TOUGE 2707',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(108,'UD-005','RBN ABN 1',1,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(109,'TR-035','JK B PANIMBANG',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(110,'IK-043','K 8 KING',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(111,'TR-036','SAMGE',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(112,'TR-037','TN VIP JMB 2605141',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(113,'IK-044','JAER',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(114,'TR-038','TN 2607117',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(115,'IK-045','TOUGE CANTIK 2607102',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(116,'IK-046','SSB JMB',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(117,'IK-047','B.A COP SPR',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(118,'IK-048','TERI KEMBANG',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(119,'IK-049','RBN ASH BLW',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(120,'IK-050','TR KATAK',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(121,'IK-051','KBBM ****',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(122,'IK-052','DDTB',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(123,'IK-053','LY RBS SPR',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(124,'UD-006','RBN JRK RK',1,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(125,'IK-054','SAMGE BULAT',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(126,'TR-039','TWR HLS TERI HITAM',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(127,'IK-055','SERO CANTIK',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(128,'IK-056','GBS B',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(129,'IK-057','YUBUT',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(130,'IK-058','BK SPR KING 2608113',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(131,'TR-040','TERI BUNGA TWR SPR',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(132,'IK-059','BWT',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(133,'IK-060','CMKK PREMIUM 260761',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(134,'IK-061','SEPAT RAWA',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(135,'TR-041','JKK 260432',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(136,'IK-062','SEPAT BB',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(137,'IK-063','KAPAS TWR SEMI',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(138,'IK-064','GLAGAH SPR',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(139,'UD-007','RBN ABN 3',1,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(140,'IK-065','KALAPAN KING',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(141,'TR-042','TN TOUGE A22',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(142,'IK-066','KBBM JMB',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(143,'TR-043','BONTIO JOS 2208',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(144,'IK-067','BN JMB F 1 R',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(145,'CM-005','BK SPR KING 104',2,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(146,'IK-068','NINIS R',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(147,'UD-008','KAS RBN',1,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(148,'IK-069','KBB JENDRAL',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(149,'TR-044','TN SL SPR',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(150,'TR-045','TN XL',4,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(151,'UD-009','RBN JERUK',1,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(152,'IK-070','TAWAR GLAGAH',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(153,'IK-071','TMBB JMB',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51'),
(154,'IK-072','HRT 0509',3,NULL,0.500,'KG',1,'2026-11-06 17:21:51');
/*!40000 ALTER TABLE `items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jenis_barang`
--

DROP TABLE IF EXISTS `jenis_barang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jenis_barang` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_jenis` varchar(20) NOT NULL,
  `nama_jenis` varchar(100) NOT NULL,
  `satuan_stok` varchar(10) NOT NULL DEFAULT 'kg',
  `toleransi_min_kg` decimal(8,2) NOT NULL DEFAULT 1.00,
  `toleransi_maks_kg` decimal(8,2) NOT NULL DEFAULT 50.00,
  `keterangan` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_jenis` (`kode_jenis`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jenis_barang`
--

LOCK TABLES `jenis_barang` WRITE;
/*!40000 ALTER TABLE `jenis_barang` DISABLE KEYS */;
INSERT INTO `jenis_barang` VALUES
(1,'PRD01','Udang','kg',150.00,450.00,'Daging karkas besar 1/4 atau 1/2 ekor',1,'2026-10-22 09:21:48'),
(2,'PRD02','Cumi','kg',10.00,35.00,'Karton kemasan potongan primer beku (Sirloin, Ribeye, dll)',1,'2026-10-22 09:21:48'),
(3,'PRD03','IKAN','kg',2.00,10.00,'Daging iris/giling dalam kemasan vakum pack khusus retail',1,'2026-10-22 09:21:48'),
(4,'PRD04','TERI','kg',5.00,25.00,'Hati, paru, babat, lidah dalam karung/box berpendingin',1,'2026-10-22 09:21:48');
/*!40000 ALTER TABLE `jenis_barang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `labels`
--

DROP TABLE IF EXISTS `labels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `labels` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_label` varchar(50) NOT NULL,
  `mitra_id` int(11) DEFAULT NULL,
  `pemasok_id` int(11) DEFAULT NULL,
  `item_id` int(11) NOT NULL,
  `lokasi_id` int(11) DEFAULT NULL,
  `berat_bruto` decimal(8,2) NOT NULL,
  `berat_tara` decimal(6,3) NOT NULL,
  `berat_netto` decimal(8,2) NOT NULL,
  `harga_per_kg` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_nilai` decimal(14,2) NOT NULL DEFAULT 0.00,
  `batch_no` varchar(50) NOT NULL,
  `no_surat_jalan` varchar(100) DEFAULT NULL,
  `tgl_produksi` date DEFAULT NULL,
  `tgl_kadaluarsa` date DEFAULT NULL,
  `status` enum('AKTIF','KELUAR','VOID','DISESUAIKAN') NOT NULL DEFAULT 'AKTIF',
  `operator_timbang_id` int(11) DEFAULT NULL,
  `catatan` varchar(255) DEFAULT NULL,
  `alasan_void` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_timbang_selesai` tinyint(1) NOT NULL DEFAULT 0,
  `last_opname_at` datetime DEFAULT NULL,
  `opname_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_label` (`id_label`),
  KEY `lokasi_id` (`lokasi_id`),
  KEY `operator_timbang_id` (`operator_timbang_id`),
  KEY `idx_label_status_mitra` (`mitra_id`,`status`),
  KEY `idx_label_item` (`item_id`),
  KEY `idx_label_barcode` (`id_label`),
  KEY `idx_label_pemasok` (`pemasok_id`),
  CONSTRAINT `labels_ibfk_1` FOREIGN KEY (`mitra_id`) REFERENCES `mitra` (`id`),
  CONSTRAINT `labels_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `labels_ibfk_4` FOREIGN KEY (`operator_timbang_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `labels`
--

LOCK TABLES `labels` WRITE;
/*!40000 ALTER TABLE `labels` DISABLE KEYS */;
INSERT INTO `labels` VALUES
(1,'BH01-PRD03-261107-00001',NULL,8,46,NULL,5.00,0.000,5.00,0.00,0.00,'SJ-BH-01-261107-0001','SJ-BH-01-261107-0001','2026-11-07',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-07 18:16:19','2026-11-07 18:16:19',1,NULL,NULL),
(4,'BH01-PRD03-261107-00002',NULL,8,81,NULL,6.00,0.000,6.00,0.00,0.00,'SJ-BH-01-261107-0002','SJ-BH-01-261107-0002','2026-11-07',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-07 18:58:17','2026-11-08 00:51:22',1,'2026-11-08 01:51:22',3),
(5,'BH01-PRD03-261107-00003',NULL,8,87,NULL,6.00,0.000,6.00,0.00,0.00,'SJ-BH-01-261107-0002','SJ-BH-01-261107-0002','2026-11-07',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-07 18:58:26','2026-11-08 00:51:20',1,'2026-11-08 01:51:20',3),
(6,'BH01-PRD01-261107-00004',NULL,8,108,NULL,7.00,0.000,7.00,0.00,0.00,'SJ-BH-01-261107-0003','SJ-BH-01-261107-0003','2026-11-07',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-07 19:15:59','2026-11-07 19:15:59',1,NULL,NULL),
(10,'BH01-PRD02-261107-00005',NULL,8,145,NULL,6.00,0.000,6.00,0.00,0.00,'SJ-BH-01-261107-0004','SJ-BH-01-261107-0004','2026-11-07',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-07 19:19:35','2026-11-07 19:19:35',1,NULL,NULL),
(11,'BH01-PRD02-261107-00006',NULL,8,145,NULL,6.00,0.000,6.00,0.00,0.00,'SJ-BH-01-261107-0004','SJ-BH-01-261107-0004','2026-11-07',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-07 19:19:43','2026-11-07 19:19:43',1,NULL,NULL),
(12,'BH01-PRD01-261107-00007',NULL,8,55,NULL,5.00,0.000,5.00,0.00,0.00,'SJ-BH-01-261107-0005','SJ-BH-01-261107-0005','2026-11-07',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-07 19:26:19','2026-11-07 19:26:19',1,NULL,NULL),
(13,'BH01-PRD01-261107-00008',NULL,8,55,NULL,4.00,0.000,4.00,0.00,0.00,'SJ-BH-01-261107-0005','SJ-BH-01-261107-0005','2026-11-07',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-07 19:26:21','2026-11-07 19:26:21',1,NULL,NULL),
(14,'BH01-PRD02-261107-00009',NULL,8,145,NULL,60.00,0.000,60.00,0.00,0.00,'SJ-BH-01-261107-0006','SJ-BH-01-261107-0006','2026-11-07',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-07 19:29:30','2026-11-07 19:29:30',1,NULL,NULL),
(15,'BH01-PRD01-261107-00010',NULL,8,55,NULL,4.00,0.000,4.00,0.00,0.00,'SJ-BH-01-261107-0007','SJ-BH-01-261107-0007','2026-11-07',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-07 19:36:49','2026-11-07 19:36:49',1,NULL,NULL),
(16,'BH01-PRD01-261107-00011',NULL,8,55,NULL,5.00,0.000,5.00,0.00,0.00,'SJ-BH-01-261107-0007','SJ-BH-01-261107-0007','2026-11-07',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-07 19:36:53','2026-11-07 19:47:01',1,'2026-11-07 20:47:01',1),
(17,'BH01-PRD04-261108-00001',NULL,8,95,NULL,10.00,0.000,10.00,0.00,0.00,'SJ-BH-01-261108-0001','SJ-BH-01-261108-0001','2026-11-08',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-08 00:53:04','2026-11-08 00:53:04',1,NULL,NULL),
(18,'BH01-PRD04-261108-00002',NULL,8,62,NULL,3.00,0.000,3.00,0.00,0.00,'SJ-BH-01-261108-0001','SJ-BH-01-261108-0001','2026-11-08',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-08 00:53:09','2026-11-08 00:53:09',1,NULL,NULL),
(19,'BH01-PRD02-261108-00003',NULL,8,89,NULL,5.00,0.000,5.00,0.00,0.00,'SJ-BH-01-261108-0002','SJ-BH-01-261108-0002','2026-11-08',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-08 01:17:50','2026-11-08 01:19:43',1,'2026-11-08 02:19:43',3),
(20,'BH01-PRD01-261108-00004',NULL,8,147,NULL,20.00,0.000,20.00,0.00,0.00,'SJ-BH-01-261108-0003','SJ-BH-01-261108-0003','2026-11-08',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-08 01:20:59','2026-11-08 01:20:59',1,NULL,NULL),
(21,'BH01-PRD01-261108-00005',NULL,8,108,NULL,44.00,0.000,44.00,0.00,0.00,'SJ-BH-01-261108-0004','SJ-BH-01-261108-0004','2026-11-08',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-08 01:26:52','2026-11-08 01:26:52',1,NULL,NULL),
(22,'BH01-PRD02-261108-00006',NULL,8,25,NULL,4.00,0.000,4.00,0.00,0.00,'SJ-BH-01-261108-0005','SJ-BH-01-261108-0005','2026-11-08',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-08 01:31:45','2026-11-08 01:31:45',1,NULL,NULL),
(23,'BH01-PRD02-261108-00007',NULL,8,89,NULL,15.00,0.000,15.00,0.00,0.00,'SJ-BH-01-261108-0006','SJ-BH-01-261108-0006','2026-11-08',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-08 06:48:27','2026-11-08 07:14:53',1,'2026-11-08 08:14:53',3),
(24,'BH01-PRD02-261108-00008',NULL,8,145,NULL,32.00,0.000,32.00,0.00,0.00,'SJ-BH-01-261108-0006','SJ-BH-01-261108-0006','2026-11-08',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-08 06:48:33','2026-11-08 07:14:52',1,'2026-11-08 08:14:52',3),
(25,'BH01-PRD02-261108-00009',NULL,8,25,NULL,50.00,0.000,50.00,0.00,0.00,'SJ-BH-01-261108-0007','SJ-BH-01-261108-0007','2026-11-08',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-08 07:07:36','2026-11-08 07:07:36',1,NULL,NULL),
(26,'BH01-PRD01-261108-00010',NULL,8,147,NULL,40.00,0.000,40.00,0.00,0.00,'SJ-BH-01-261108-0008','SJ-BH-01-261108-0008','2026-11-08',NULL,'AKTIF',3,'Penerimaan Timbang Langsung',NULL,'2026-11-08 09:33:37','2026-11-08 09:33:37',1,NULL,NULL);
/*!40000 ALTER TABLE `labels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `master_harga`
--

DROP TABLE IF EXISTS `master_harga`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `master_harga` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mitra_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `harga_per_kg` decimal(12,2) NOT NULL DEFAULT 0.00,
  `catatan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mitra_item` (`mitra_id`,`item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1187 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `master_harga`
--

LOCK TABLES `master_harga` WRITE;
/*!40000 ALTER TABLE `master_harga` DISABLE KEYS */;
INSERT INTO `master_harga` VALUES
(401,1,24,86000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(402,2,24,86000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(403,3,24,86000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(404,1,25,77000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(405,2,25,77000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(406,3,25,77000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(407,1,26,36000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(408,2,26,36000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(409,3,26,36000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(410,1,27,67000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(411,2,27,67000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(412,3,27,67000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(413,1,28,97000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(414,2,28,97000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(415,3,28,97000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(416,1,29,75000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(417,2,29,75000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(418,3,29,75000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(419,1,30,48000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(420,2,30,48000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(421,3,30,48000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(422,1,31,83000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(423,2,31,83000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(424,3,31,83000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(425,1,32,90000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(426,2,32,90000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(427,3,32,90000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(428,1,33,63000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(429,2,33,63000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(430,3,33,63000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(431,1,34,88000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(432,2,34,88000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(433,3,34,88000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(434,1,35,80000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(435,2,35,80000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(436,3,35,80000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(437,1,36,47000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(438,2,36,47000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(439,3,36,47000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(440,1,37,22000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(441,2,37,22000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(442,3,37,22000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(443,1,38,43000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(444,2,38,43000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(445,3,38,43000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(446,1,39,46000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(447,2,39,46000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(448,3,39,46000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(449,1,40,65000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(450,2,40,65000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(451,3,40,65000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(452,1,41,45000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(453,2,41,45000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(454,3,41,45000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(455,1,42,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(456,2,42,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(457,3,42,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(458,1,43,107000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(459,2,43,107000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(460,3,43,107000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(461,1,44,25000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(462,2,44,25000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(463,3,44,25000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(464,1,45,60000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(465,2,45,60000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(466,3,45,60000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(467,1,46,130000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(468,2,46,130000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(469,3,46,130000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(470,1,47,84000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(471,2,47,84000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(472,3,47,84000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(473,1,48,22000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(474,2,48,22000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(475,3,48,22000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(476,1,49,48000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(477,2,49,48000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(478,3,49,48000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(479,1,50,107000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(480,2,50,107000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(481,3,50,107000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(482,1,51,44000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(483,2,51,44000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(484,3,51,44000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(485,1,52,34000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(486,2,52,34000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(487,3,52,34000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(488,1,53,48000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(489,2,53,48000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(490,3,53,48000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(491,1,54,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(492,2,54,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(493,3,54,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(494,1,55,25000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(495,2,55,25000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(496,3,55,25000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(497,1,56,34000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(498,2,56,34000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(499,3,56,34000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(500,1,57,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(501,2,57,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(502,3,57,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(503,1,58,83000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(504,2,58,83000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(505,3,58,83000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(506,1,59,26000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(507,2,59,26000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(508,3,59,26000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(509,1,60,178000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(510,2,60,178000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(511,3,60,178000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(512,1,61,74000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(513,2,61,74000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(514,3,61,74000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(515,1,62,76000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(516,2,62,76000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(517,3,62,76000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(518,1,63,63000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(519,2,63,63000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(520,3,63,63000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(521,1,64,26000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(522,2,64,26000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(523,3,64,26000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(524,1,65,47000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(525,2,65,47000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(526,3,65,47000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(527,1,66,35000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(528,2,66,35000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(529,3,66,35000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(530,1,67,34000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(531,2,67,34000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(532,3,67,34000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(533,1,68,82000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(534,2,68,82000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(535,3,68,82000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(536,1,69,34000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(537,2,69,34000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(538,3,69,34000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(539,1,70,44000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(540,2,70,44000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(541,3,70,44000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(542,1,71,33000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(543,2,71,33000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(544,3,71,33000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(545,1,72,74000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(546,2,72,74000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(547,3,72,74000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(548,1,73,118000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(549,2,73,118000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(550,3,73,118000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(551,1,74,93000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(552,2,74,93000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(553,3,74,93000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(554,1,75,35000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(555,2,75,35000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(556,3,75,35000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(557,1,76,28000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(558,2,76,28000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(559,3,76,28000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(560,1,77,62000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(561,2,77,62000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(562,3,77,62000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(563,1,78,50000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(564,2,78,50000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(565,3,78,50000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(566,1,79,67000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(567,2,79,67000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(568,3,79,67000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(569,1,80,43000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(570,2,80,43000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(571,3,80,43000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(572,1,81,53000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(573,2,81,53000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(574,3,81,53000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(575,1,82,72000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(576,2,82,72000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(577,3,82,72000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(578,1,83,48000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(579,2,83,48000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(580,3,83,48000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(581,1,84,60000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(582,2,84,60000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(583,3,84,60000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(584,1,85,90000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(585,2,85,90000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(586,3,85,90000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(587,1,86,60000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(588,2,86,60000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(589,3,86,60000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(590,1,87,55000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(591,2,87,55000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(592,3,87,55000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(593,1,88,58000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(594,2,88,58000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(595,3,88,58000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(596,1,89,87000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(597,2,89,87000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(598,3,89,87000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(599,1,90,77000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(600,2,90,77000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(601,3,90,77000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(602,1,91,34000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(603,2,91,34000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(604,3,91,34000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(605,1,92,52000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(606,2,92,52000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(607,3,92,52000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(608,1,93,56000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(609,2,93,56000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(610,3,93,56000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(611,1,94,97000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(612,2,94,97000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(613,3,94,97000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(614,1,95,26000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(615,2,95,26000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(616,3,95,26000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(617,1,96,26000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(618,2,96,26000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(619,3,96,26000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(620,1,97,47000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(621,2,97,47000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(622,3,97,47000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(623,1,98,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(624,2,98,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(625,3,98,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(626,1,99,115000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(627,2,99,115000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(628,3,99,115000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(629,1,100,83000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(630,2,100,83000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(631,3,100,83000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(632,1,101,53000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(633,2,101,53000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(634,3,101,53000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(635,1,102,35000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(636,2,102,35000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(637,3,102,35000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(638,1,103,36000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(639,2,103,36000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(640,3,103,36000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(641,1,104,62000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(642,2,104,62000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(643,3,104,62000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(644,1,105,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(645,2,105,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(646,3,105,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(647,1,106,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(648,2,106,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(649,3,106,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(650,1,107,98000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(651,2,107,98000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(652,3,107,98000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(653,1,108,25000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(654,2,108,25000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(655,3,108,25000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(656,1,109,74000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(657,2,109,74000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(658,3,109,74000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(659,1,110,178000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(660,2,110,178000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(661,3,110,178000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(662,1,111,36000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(663,2,111,36000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(664,3,111,36000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(665,1,112,88000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(666,2,112,88000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(667,3,112,88000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(668,1,113,27000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(669,2,113,27000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(670,3,113,27000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(671,1,114,105000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(672,2,114,105000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(673,3,114,105000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(674,1,115,109000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(675,2,115,109000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(676,3,115,109000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(677,1,116,100000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(678,2,116,100000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(679,3,116,100000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(680,1,117,57000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(681,2,117,57000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(682,3,117,57000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(683,1,118,74000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(684,2,118,74000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(685,3,118,74000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(686,1,119,32000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(687,2,119,32000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(688,3,119,32000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(689,1,120,80000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(690,2,120,80000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(691,3,120,80000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(692,1,121,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(693,2,121,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(694,3,121,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(695,1,122,46000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(696,2,122,46000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(697,3,122,46000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(698,1,123,67000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(699,2,123,67000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(700,3,123,67000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(701,1,124,35000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(702,2,124,35000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(703,3,124,35000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(704,1,125,47000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(705,2,125,47000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(706,3,125,47000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(707,1,126,63000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(708,2,126,63000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(709,3,126,63000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(710,1,127,37000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(711,2,127,37000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(712,3,127,37000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(713,1,128,78000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(714,2,128,78000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(715,3,128,78000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(716,1,129,60000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(717,2,129,60000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(718,3,129,60000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(719,1,130,82000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(720,2,130,82000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(721,3,130,82000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(722,1,131,74000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(723,2,131,74000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(724,3,131,74000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(725,1,132,50000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(726,2,132,50000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(727,3,132,50000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(728,1,133,52000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(729,2,133,52000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(730,3,133,52000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(731,1,134,44000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(732,2,134,44000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(733,3,134,44000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(734,1,135,41000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(735,2,135,41000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(736,3,135,41000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(737,1,136,78000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(738,2,136,78000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(739,3,136,78000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(740,1,137,77000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(741,2,137,77000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(742,3,137,77000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(743,1,138,80000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(744,2,138,80000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(745,3,138,80000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(746,1,139,24000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(747,2,139,24000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(748,3,139,24000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(749,1,140,175000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(750,2,140,175000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(751,3,140,175000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(752,1,141,120000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(753,2,141,120000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(754,3,141,120000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(755,1,142,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(756,2,142,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(757,3,142,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(758,1,143,70000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(759,2,143,70000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(760,3,143,70000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(761,1,144,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(762,2,144,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(763,3,144,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(764,1,145,82000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(765,2,145,82000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(766,3,145,82000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(767,1,146,26000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(768,2,146,26000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(769,3,146,26000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(770,1,147,36000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(771,2,147,36000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(772,3,147,36000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(773,1,148,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(774,2,148,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(775,3,148,54000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(776,1,149,75000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(777,2,149,75000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(778,3,149,75000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(779,1,150,118000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(780,2,150,118000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(781,3,150,118000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(782,1,151,35000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(783,2,151,35000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(784,3,151,35000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(785,1,152,80000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(786,2,152,80000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(787,3,152,80000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(788,1,153,100000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(789,2,153,100000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(790,3,153,100000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(791,1,154,88000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(792,2,154,88000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09'),
(793,3,154,88000.00,NULL,'2026-11-06 17:21:51','2026-11-08 06:48:09');
/*!40000 ALTER TABLE `master_harga` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `master_harga_pemasok`
--

DROP TABLE IF EXISTS `master_harga_pemasok`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `master_harga_pemasok` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pemasok_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `jenis_id` int(11) DEFAULT NULL,
  `harga_per_kg` decimal(12,2) NOT NULL DEFAULT 0.00,
  `catatan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pemasok_item` (`pemasok_id`,`item_id`),
  KEY `idx_mhp_pemasok` (`pemasok_id`),
  KEY `idx_mhp_jenis` (`jenis_id`),
  KEY `fk_mhp_item` (`item_id`),
  CONSTRAINT `fk_mhp_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1288 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `master_harga_pemasok`
--

LOCK TABLES `master_harga_pemasok` WRITE;
/*!40000 ALTER TABLE `master_harga_pemasok` DISABLE KEYS */;
INSERT INTO `master_harga_pemasok` VALUES
(109,5,24,2,68000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(110,7,24,2,72000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(111,8,24,2,67000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(112,5,25,2,59000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(113,7,25,2,59000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(114,8,25,2,63000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(115,5,26,3,30000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(116,7,26,3,30000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(117,8,26,3,30000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(118,5,27,3,54000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(119,7,27,3,55000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(120,8,27,3,55000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(121,5,28,3,80000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(122,7,28,3,80000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(123,8,28,3,78000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(124,5,29,3,58000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(125,7,29,3,60000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(126,8,29,3,61000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(127,5,30,3,37000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(128,7,30,3,38000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(129,8,30,3,40000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(130,5,31,4,68000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(131,7,31,4,64000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(132,8,31,4,67000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(133,5,32,4,71000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(134,7,32,4,73000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(135,8,32,4,73000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(136,5,33,4,49000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(137,7,33,4,51000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(138,8,33,4,50000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(139,5,34,4,72000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(140,7,34,4,73000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(141,8,34,4,69000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(142,5,35,3,65000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(143,7,35,3,61000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(144,8,35,3,64000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(145,5,36,4,36000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(146,7,36,4,37000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(147,8,36,4,38000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(148,5,37,3,17000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(149,7,37,3,18000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(150,8,37,3,18000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(151,5,38,3,35000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(152,7,38,3,35000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(153,8,38,3,34000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(154,5,39,3,37000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(155,7,39,3,36000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(156,8,39,3,35000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(157,5,40,3,54000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(158,7,40,3,50000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(159,8,40,3,51000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(160,5,41,3,37000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(161,7,41,3,37000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(162,8,41,3,36000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(163,5,42,3,45000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(164,7,42,3,45000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(165,8,42,3,43000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(166,5,43,4,86000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(167,7,43,4,83000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(168,8,43,4,84000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(169,5,44,3,20000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(170,7,44,3,19000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(171,8,44,3,21000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(172,5,45,3,49000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(173,7,45,3,49000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(174,8,45,3,49000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(175,5,46,3,104000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(176,7,46,3,102000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(177,8,46,3,106000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(178,5,47,4,69000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(179,7,47,4,66000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(180,8,47,4,66000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(181,5,48,3,17000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(182,7,48,3,18000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(183,8,48,3,17000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(184,5,49,3,40000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(185,7,49,3,40000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(186,8,49,3,40000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(187,5,50,3,84000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(188,7,50,3,85000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(189,8,50,3,82000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(190,5,51,4,36000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(191,7,51,4,35000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(192,8,51,4,35000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(193,5,52,4,27000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(194,7,52,4,27000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(195,8,52,4,26000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(196,5,53,3,40000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(197,7,53,3,38000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(198,8,53,3,38000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(199,5,54,4,45000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(200,7,54,4,42000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(201,8,54,4,42000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(202,5,55,1,19000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(203,7,55,1,20000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(204,8,55,1,20000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(205,5,56,4,26000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(206,7,56,4,27000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(207,8,56,4,28000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(208,5,57,4,42000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(209,7,57,4,42000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(210,8,57,4,44000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(211,5,58,4,64000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(212,7,58,4,65000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(213,8,58,4,64000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(214,5,59,3,21000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(215,7,59,3,20000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(216,8,59,3,21000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(217,5,60,3,148000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(218,7,60,3,142000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(219,8,60,3,142000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(220,5,61,3,57000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(221,7,61,3,60000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(222,8,61,3,62000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(223,5,62,4,63000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(224,7,62,4,63000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(225,8,62,4,62000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(226,5,63,3,49000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(227,7,63,3,50000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(228,8,63,3,48000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(229,5,64,3,21000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(230,7,64,3,21000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(231,8,64,3,20000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(232,5,65,3,36000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(233,7,65,3,37000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(234,8,65,3,38000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(235,5,66,1,27000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(236,7,66,1,27000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(237,8,66,1,29000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(238,5,67,4,27000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(239,7,67,4,27000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(240,8,67,4,28000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(241,5,68,4,66000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(242,7,68,4,64000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(243,8,68,4,66000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(244,5,69,4,28000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(245,7,69,4,28000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(246,8,69,4,26000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(247,5,70,4,36000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(248,7,70,4,37000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(249,8,70,4,35000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(250,5,71,3,26000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(251,7,71,3,26000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(252,8,71,3,27000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(253,5,72,3,58000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(254,7,72,3,58000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(255,8,72,3,59000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(256,5,73,4,94000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(257,7,73,4,93000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(258,8,73,4,91000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(259,5,74,4,71000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(260,7,74,4,72000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(261,8,74,4,73000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(262,5,75,4,29000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(263,7,75,4,28000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(264,8,75,4,28000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(265,5,76,1,22000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(266,7,76,1,22000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(267,8,76,1,23000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(268,5,77,4,48000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(269,7,77,4,52000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(270,8,77,4,48000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(271,5,78,3,42000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(272,7,78,3,40000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(273,8,78,3,40000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(274,5,79,2,55000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(275,7,79,2,55000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(276,8,79,2,55000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(277,5,80,3,34000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(278,7,80,3,36000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(279,8,80,3,34000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(280,5,81,3,41000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(281,7,81,3,41000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(282,8,81,3,41000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(283,5,82,4,56000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(284,7,82,4,57000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(285,8,82,4,60000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(286,5,83,3,38000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(287,7,83,3,38000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(288,8,83,3,37000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(289,5,84,3,47000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(290,7,84,3,49000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(291,8,84,3,47000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(292,5,85,3,73000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(293,7,85,3,72000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(294,8,85,3,71000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(295,5,86,1,49000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(296,7,86,1,46000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(297,8,86,1,49000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(298,5,87,3,44000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(299,7,87,3,45000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(300,8,87,3,43000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(301,5,88,4,48000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(302,7,88,4,48000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(303,8,88,4,45000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(304,5,89,2,68000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(305,7,89,2,72000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(306,8,89,2,70000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(307,5,90,4,63000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(308,7,90,4,63000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(309,8,90,4,64000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(310,5,91,4,28000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(311,7,91,4,26000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(312,8,91,4,26000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(313,5,92,4,41000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(314,7,92,4,42000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(315,8,92,4,43000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(316,5,93,3,43000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(317,7,93,3,43000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(318,8,93,3,44000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(319,5,94,4,74000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(320,7,94,4,79000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(321,8,94,4,80000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(322,5,95,4,21000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(323,7,95,4,20000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(324,8,95,4,22000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(325,5,96,3,21000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(326,7,96,3,20000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(327,8,96,3,20000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(328,5,97,3,36000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(329,7,97,3,37000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(330,8,97,3,39000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(331,5,98,3,43000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(332,7,98,3,44000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(333,8,98,3,44000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(334,5,99,4,90000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(335,7,99,4,91000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(336,8,99,4,94000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(337,5,100,4,65000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(338,7,100,4,64000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(339,8,100,4,67000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(340,5,101,3,44000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(341,7,101,3,44000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(342,8,101,3,41000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(343,5,102,3,29000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(344,7,102,3,29000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(345,8,102,3,29000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(346,5,103,3,30000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(347,7,103,3,30000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(348,8,103,3,29000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(349,5,104,4,51000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(350,7,104,4,51000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(351,8,104,4,52000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(352,5,105,4,42000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(353,7,105,4,41000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(354,8,105,4,44000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(355,5,106,3,45000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(356,7,106,3,42000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(357,8,106,3,44000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(358,5,107,4,76000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(359,7,107,4,80000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(360,8,107,4,75000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(361,5,108,1,20000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(362,7,108,1,20000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(363,8,108,1,20000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(364,5,109,4,59000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(365,7,109,4,59000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(366,8,109,4,57000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(367,5,110,3,145000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(368,7,110,3,148000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(369,8,110,3,138000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(370,5,111,4,29000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(371,7,111,4,29000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(372,8,111,4,29000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(373,5,112,4,73000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(374,7,112,4,68000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(375,8,112,4,73000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(376,5,113,3,21000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(377,7,113,3,21000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(378,8,113,3,22000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(379,5,114,4,87000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(380,7,114,4,81000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(381,8,114,4,86000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(382,5,115,3,88000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(383,7,115,3,89000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(384,8,115,3,88000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(385,5,116,3,80000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(386,7,116,3,78000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(387,8,116,3,79000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(388,5,117,3,47000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(389,7,117,3,44000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(390,8,117,3,47000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(391,5,118,3,59000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(392,7,118,3,59000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(393,8,118,3,62000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(394,5,119,3,27000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(395,7,119,3,25000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(396,8,119,3,26000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(397,5,120,3,66000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(398,7,120,3,63000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(399,8,120,3,67000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(400,5,121,3,42000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(401,7,121,3,43000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(402,8,121,3,43000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(403,5,122,3,38000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(404,7,122,3,38000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(405,8,122,3,38000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(406,5,123,3,55000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(407,7,123,3,54000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(408,8,123,3,51000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(409,5,124,1,28000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(410,7,124,1,28000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(411,8,124,1,28000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(412,5,125,3,36000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(413,7,125,3,37000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(414,8,125,3,36000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(415,5,126,4,51000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(416,7,126,4,52000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(417,8,126,4,52000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(418,5,127,3,29000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(419,7,127,3,29000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(420,8,127,3,30000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(421,5,128,3,63000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(422,7,128,3,61000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(423,8,128,3,65000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(424,5,129,3,47000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(425,7,129,3,49000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(426,8,129,3,49000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(427,5,130,3,64000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(428,7,130,3,68000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(429,8,130,3,68000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(430,5,131,4,60000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(431,7,131,4,60000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(432,8,131,4,59000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(433,5,132,3,41000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(434,7,132,3,39000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(435,8,132,3,39000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(436,5,133,3,43000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(437,7,133,3,42000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(438,8,133,3,42000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(439,5,134,3,35000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(440,7,134,3,34000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(441,8,134,3,36000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(442,5,135,4,33000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(443,7,135,4,33000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(444,8,135,4,31000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(445,5,136,3,60000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(446,7,136,3,60000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(447,8,136,3,62000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(448,5,137,3,61000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(449,7,137,3,61000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(450,8,137,3,62000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(451,5,138,3,67000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(452,7,138,3,67000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(453,8,138,3,66000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(454,5,139,1,18000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(455,7,139,1,19000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(456,8,139,1,19000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(457,5,140,3,134000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(458,7,140,3,143000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(459,8,140,3,144000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(460,5,141,4,97000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(461,7,141,4,100000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(462,8,141,4,95000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(463,5,142,3,43000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(464,7,142,3,43000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(465,8,142,3,42000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(466,5,143,4,57000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(467,7,143,4,54000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(468,8,143,4,54000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(469,5,144,3,44000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(470,7,144,3,42000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(471,8,144,3,44000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(472,5,145,2,65000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(473,7,145,2,68000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(474,8,145,2,63000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(475,5,146,3,21000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(476,7,146,3,22000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(477,8,146,3,21000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(478,5,147,1,29000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(479,7,147,1,29000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(480,8,147,1,29000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(481,5,148,3,42000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(482,7,148,3,44000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(483,8,148,3,44000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(484,5,149,4,58000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(485,7,149,4,59000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(486,8,149,4,59000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(487,5,150,4,92000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(488,7,150,4,95000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(489,8,150,4,92000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(490,5,151,1,27000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(491,7,151,1,28000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(492,8,151,1,27000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(493,5,152,3,61000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(494,7,152,3,66000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(495,8,152,3,65000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(496,5,153,3,79000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(497,7,153,3,81000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(498,8,153,3,82000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53'),
(499,5,154,3,73000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(500,7,154,3,71000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:54'),
(501,8,154,3,70000.00,NULL,'2026-11-07 16:05:18','2026-11-08 06:47:53');
/*!40000 ALTER TABLE `master_harga_pemasok` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `master_harga_pemasok_old_jenis`
--

DROP TABLE IF EXISTS `master_harga_pemasok_old_jenis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `master_harga_pemasok_old_jenis` (
  `id` int(11) NOT NULL DEFAULT 0,
  `pemasok_id` int(11) NOT NULL,
  `jenis_id` int(11) NOT NULL,
  `harga_per_kg` decimal(12,2) NOT NULL DEFAULT 0.00,
  `catatan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `master_harga_pemasok_old_jenis`
--

LOCK TABLES `master_harga_pemasok_old_jenis` WRITE;
/*!40000 ALTER TABLE `master_harga_pemasok_old_jenis` DISABLE KEYS */;
/*!40000 ALTER TABLE `master_harga_pemasok_old_jenis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `master_tipe_biaya`
--

DROP TABLE IF EXISTS `master_tipe_biaya`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `master_tipe_biaya` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_biaya` varchar(30) NOT NULL,
  `nama_biaya` varchar(150) NOT NULL,
  `kategori_beban` varchar(50) NOT NULL DEFAULT 'OPERASIONAL',
  `kode_akun` varchar(20) NOT NULL DEFAULT '6000',
  `nama_akun` varchar(100) NOT NULL DEFAULT 'Biaya Operasional',
  `satuan_default` varchar(30) DEFAULT 'Pcs',
  `estimasi_harga` decimal(14,2) NOT NULL DEFAULT 0.00,
  `keterangan` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_biaya` (`kode_biaya`),
  KEY `idx_kode_biaya` (`kode_biaya`),
  KEY `idx_kode_akun` (`kode_akun`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `master_tipe_biaya`
--

LOCK TABLES `master_tipe_biaya` WRITE;
/*!40000 ALTER TABLE `master_tipe_biaya` DISABLE KEYS */;
INSERT INTO `master_tipe_biaya` VALUES
(1,'BY-01','Es Balok & Media Pengawet','OPERASIONAL','6000','Biaya Operasional','Balok',30000.00,'Pengawetan tangkapan ikan segar di gudang penerimaan',1,'2026-11-04 18:27:56','2026-11-04 18:27:56'),
(2,'BY-02','Box Styrofoam & Lakban Kemasan','OPERASIONAL','6000','Biaya Operasional','Box',45000.00,'Kemasan wadah pengiriman ikan ke mitra bisnis',1,'2026-11-04 18:27:56','2026-11-04 18:27:56'),
(3,'BY-03','Bahan Bakar Solar Genset Gudang','OPERASIONAL','6000','Biaya Operasional','Liter',14500.00,'Bahan bakar genset pendingin cold storage',1,'2026-11-04 18:27:56','2026-11-04 18:27:56'),
(4,'BY-04','Transport & Ongkos Angkut Truk','OPERASIONAL','6000','Biaya Operasional','Trip',350000.00,'Sewa truk pendingin pengantaran barang ke mitra',1,'2026-11-04 18:27:56','2026-11-04 18:27:56'),
(5,'BY-05','Perawatan & Servis Mesin Pendingin','OPERASIONAL','6000','Biaya Operasional','Paket',250000.00,'Pemeliharaan chiller dan freon cold storage',1,'2026-11-04 18:27:56','2026-11-04 18:27:56'),
(6,'BY-06','Plastik HD & Karung Pengemas','OPERASIONAL','6000','Biaya Operasional','Roll',75000.00,'Plastik pembungkus ikan tongkol, cumi, dan rebon',1,'2026-11-04 18:27:56','2026-11-04 18:27:56'),
(7,'BY-07','Peralatan Timbangan & Cold Storage','ASET','1100','Piutang & Aset Lainnya','Unit',1500000.00,'Pembelian aset timbangan digital dan keranjang container',1,'2026-11-04 18:27:56','2026-11-04 18:27:56'),
(8,'BY-08','Test Sewa Forklift Gudang Updated','OPERASIONAL','6000','Biaya Operasional','Shift',600000.00,NULL,1,'2026-11-05 02:11:45','2026-11-05 02:11:45');
/*!40000 ALTER TABLE `master_tipe_biaya` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mitra`
--

DROP TABLE IF EXISTS `mitra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mitra` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_mitra` varchar(10) NOT NULL,
  `nama_mitra` varchar(150) NOT NULL,
  `pic_nama` varchar(100) DEFAULT NULL,
  `pic_telepon` varchar(30) DEFAULT NULL,
  `kode_gudang_accurate` varchar(50) NOT NULL,
  `warna_identitas` varchar(20) DEFAULT '#3b82f6',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_mitra` (`kode_mitra`),
  KEY `idx_mitra_kode` (`kode_mitra`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mitra`
--

LOCK TABLES `mitra` WRITE;
/*!40000 ALTER TABLE `mitra` DISABLE KEYS */;
INSERT INTO `mitra` VALUES
(1,'M01','NASRUL BANTAR GEBANG','NASRUL','0812-3456-7891','GDG-M01-SUMBERPANGAN','#2563eb',1,'2026-10-22 09:21:48'),
(2,'M02','MITRA 2','Ustadz Mansur','0813-8822-1102','GDG-M02-BERKAHTERNAK','#16a34a',1,'2026-10-22 09:21:48'),
(3,'M03','MITRA 3','Agus Setiawan','0811-9988-7703','GDG-M03-NUSANTARA','#d97706',1,'2026-10-22 09:21:48');
/*!40000 ALTER TABLE `mitra` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pemasok`
--

DROP TABLE IF EXISTS `pemasok`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pemasok` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_pemasok` varchar(20) NOT NULL,
  `nama_pemasok` varchar(150) NOT NULL,
  `pic_nama` varchar(100) DEFAULT NULL,
  `telepon` varchar(30) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_pemasok` (`kode_pemasok`),
  KEY `idx_pemasok_kode` (`kode_pemasok`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pemasok`
--

LOCK TABLES `pemasok` WRITE;
/*!40000 ALTER TABLE `pemasok` DISABLE KEYS */;
INSERT INTO `pemasok` VALUES
(5,'KH-001','UDIN','UDIN','0856483363648','KARAWANG',NULL,1,'2026-10-25 07:18:22','2026-10-25 07:21:32'),
(7,'KH-002','ABDUL','ABDUL','085797833302','BEKASI',NULL,1,'2026-10-26 15:25:34','2026-10-26 15:25:34'),
(8,'BH-01','KARAWANG','ERWIN','085695066874','KARAWANG',NULL,1,'2026-11-01 08:11:57','2026-11-06 17:10:13');
/*!40000 ALTER TABLE `pemasok` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_invoice_items`
--

DROP TABLE IF EXISTS `purchase_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_invoice_items` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint(20) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `deskripsi` varchar(255) NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `satuan` varchar(20) NOT NULL DEFAULT 'KG',
  `harga_satuan` decimal(14,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pii_invoice` (`invoice_id`),
  CONSTRAINT `purchase_invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `purchase_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_invoice_items`
--

LOCK TABLES `purchase_invoice_items` WRITE;
/*!40000 ALTER TABLE `purchase_invoice_items` DISABLE KEYS */;
INSERT INTO `purchase_invoice_items` VALUES
(1,1,81,'BHBB (1 Box)',6.00,'KG',41000.00,246000.00,'2026-11-07 18:59:00'),
(2,1,87,'B.A SPR (1 Box)',6.00,'KG',43000.00,258000.00,'2026-11-07 18:59:00'),
(3,2,89,'JARUM * (1 Box)',5.00,'KG',70000.00,350000.00,'2026-11-08 01:19:09'),
(4,3,89,'JARUM * (1 Box)',15.00,'KG',70000.00,1050000.00,'2026-11-08 06:49:03'),
(5,3,145,'BK SPR KING 104 (1 Box)',32.00,'KG',63000.00,2016000.00,'2026-11-08 06:49:03'),
(6,4,25,'BK M (1 Box)',50.00,'KG',63000.00,3150000.00,'2026-11-08 07:07:43');
/*!40000 ALTER TABLE `purchase_invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_invoices`
--

DROP TABLE IF EXISTS `purchase_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_invoices` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `no_invoice` varchar(100) NOT NULL,
  `tipe_invoice` enum('STOK','OPERASIONAL','ASET','LAINNYA') NOT NULL DEFAULT 'STOK',
  `pemasok_id` int(11) DEFAULT NULL,
  `nama_vendor` varchar(150) NOT NULL,
  `no_surat_jalan` varchar(100) DEFAULT NULL,
  `tanggal_invoice` date NOT NULL,
  `tanggal_jatuh_tempo` date NOT NULL,
  `kode_akun_debit` varchar(10) NOT NULL DEFAULT '1200',
  `nama_akun_debit` varchar(100) NOT NULL DEFAULT 'Inventory / Persediaan',
  `kode_akun_kredit` varchar(10) NOT NULL DEFAULT '2000',
  `nama_akun_kredit` varchar(100) NOT NULL DEFAULT 'Utang',
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `pajak_ppn` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_nominal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_bayar` decimal(14,2) NOT NULL DEFAULT 0.00,
  `sisa_utang` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status_bayar` enum('BELUM_BAYAR','SEBAGIAN','LUNAS') NOT NULL DEFAULT 'BELUM_BAYAR',
  `keterangan` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_invoice` (`no_invoice`),
  KEY `idx_pi_pemasok` (`pemasok_id`),
  KEY `idx_pi_no_sj` (`no_surat_jalan`),
  KEY `idx_pi_status` (`status_bayar`),
  KEY `idx_pi_tgl` (`tanggal_invoice`),
  KEY `idx_pi_tipe` (`tipe_invoice`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_invoices`
--

LOCK TABLES `purchase_invoices` WRITE;
/*!40000 ALTER TABLE `purchase_invoices` DISABLE KEYS */;
INSERT INTO `purchase_invoices` VALUES
(1,'INV-SJ-BH-01-261107-0002','STOK',8,'KARAWANG','SJ-BH-01-261107-0002','2026-11-08','2026-11-15','1200','Inventory / Persediaan','2000','Utang',504000.00,0.00,504000.00,0.00,504000.00,'BELUM_BAYAR','Penerimaan otomatis Surat Jalan SJ-BH-01-261107-0002',NULL,'2026-11-07 18:59:00','2026-11-07 18:59:00'),
(2,'INV-SJ-BH-01-261108-0002','STOK',8,'KARAWANG','SJ-BH-01-261108-0002','2026-11-08','2026-11-15','1200','Inventory / Persediaan','2000','Utang',350000.00,0.00,350000.00,0.00,350000.00,'BELUM_BAYAR','Penerimaan otomatis Surat Jalan SJ-BH-01-261108-0002',NULL,'2026-11-08 01:19:09','2026-11-08 01:19:09'),
(3,'INV-SJ-BH-01-261108-0006','STOK',8,'KARAWANG','SJ-BH-01-261108-0006','2026-11-08','2026-11-15','1200','Inventory / Persediaan','2000','Utang',3066000.00,0.00,3066000.00,0.00,3066000.00,'BELUM_BAYAR','Penerimaan otomatis Surat Jalan SJ-BH-01-261108-0006',NULL,'2026-11-08 06:49:03','2026-11-08 06:49:03'),
(4,'INV-SJ-BH-01-261108-0007','STOK',8,'KARAWANG','SJ-BH-01-261108-0007','2026-11-08','2026-11-15','1200','Inventory / Persediaan','2000','Utang',3150000.00,0.00,3150000.00,0.00,3150000.00,'BELUM_BAYAR','Penerimaan otomatis Surat Jalan SJ-BH-01-261108-0007',NULL,'2026-11-08 07:07:43','2026-11-08 07:07:43');
/*!40000 ALTER TABLE `purchase_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `target_setoran_harian`
--

DROP TABLE IF EXISTS `target_setoran_harian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `target_setoran_harian` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `mitra_id` int(11) NOT NULL,
  `tanggal_target` date NOT NULL,
  `target_nominal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `catatan` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mitra_tanggal` (`mitra_id`,`tanggal_target`),
  KEY `idx_tsh_tanggal` (`tanggal_target`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `target_setoran_harian`
--

LOCK TABLES `target_setoran_harian` WRITE;
/*!40000 ALTER TABLE `target_setoran_harian` DISABLE KEYS */;
/*!40000 ALTER TABLE `target_setoran_harian` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaksi_stok`
--

DROP TABLE IF EXISTS `transaksi_stok`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaksi_stok` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `no_transaksi` varchar(50) NOT NULL,
  `no_bast` varchar(100) DEFAULT NULL,
  `jenis_transaksi` enum('PENERIMAAN','PENGELUARAN','MUTASI','OPNAME','PENYESUAIAN') NOT NULL,
  `alur_pergerakan` varchar(50) NOT NULL,
  `mitra_id` int(11) NOT NULL,
  `pemasok_id` int(11) DEFAULT NULL,
  `mitra_asal_id` int(11) DEFAULT NULL,
  `mitra_tujuan_id` int(11) DEFAULT NULL,
  `total_kg` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_label` int(11) NOT NULL DEFAULT 0,
  `total_nilai_beli` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_nilai_jual` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_profit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `user_id` int(11) NOT NULL,
  `nama_penyerah` varchar(150) DEFAULT NULL,
  `nama_penerima` varchar(150) DEFAULT NULL,
  `user_penerima_id` int(11) DEFAULT NULL,
  `lokasi_asal_id` int(11) DEFAULT NULL,
  `lokasi_tujuan_id` int(11) DEFAULT NULL,
  `no_referensi_eksternal` varchar(100) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `catatan_serah_terima` text DEFAULT NULL,
  `status_accurate` enum('PENDING','SENT','FAILED','SKIPPED') NOT NULL DEFAULT 'PENDING',
  `no_dokumen_accurate` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_transaksi` (`no_transaksi`),
  KEY `user_id` (`user_id`),
  KEY `idx_trx_mitra_jenis` (`mitra_id`,`jenis_transaksi`),
  KEY `idx_trx_accurate_status` (`status_accurate`),
  CONSTRAINT `transaksi_stok_ibfk_1` FOREIGN KEY (`mitra_id`) REFERENCES `mitra` (`id`),
  CONSTRAINT `transaksi_stok_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaksi_stok`
--

LOCK TABLES `transaksi_stok` WRITE;
/*!40000 ALTER TABLE `transaksi_stok` DISABLE KEYS */;
/*!40000 ALTER TABLE `transaksi_stok` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaksi_stok_detail`
--

DROP TABLE IF EXISTS `transaksi_stok_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaksi_stok_detail` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `transaksi_id` bigint(20) NOT NULL,
  `label_id` bigint(20) NOT NULL,
  `berat_netto` decimal(8,2) NOT NULL,
  `harga_pemasok` decimal(12,2) NOT NULL DEFAULT 0.00,
  `harga_mitra` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_beli` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_jual` decimal(14,2) NOT NULL DEFAULT 0.00,
  `profit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_transaksi_label` (`transaksi_id`,`label_id`),
  KEY `label_id` (`label_id`),
  CONSTRAINT `transaksi_stok_detail_ibfk_1` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi_stok` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transaksi_stok_detail_ibfk_2` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaksi_stok_detail`
--

LOCK TABLES `transaksi_stok_detail` WRITE;
/*!40000 ALTER TABLE `transaksi_stok_detail` DISABLE KEYS */;
/*!40000 ALTER TABLE `transaksi_stok_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('ADMIN','SUPERVISOR','OPERATOR_INBOUND','OPERATOR_OUTBOUND','FINANCE') NOT NULL DEFAULT 'OPERATOR_INBOUND',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'admin','$2y$10$k1a4j.49z3PZ2L78D0b5kO0pIe1B3d8a7kLmN0pQrStUvWxYzAbCd','Administrator Sistem','ADMIN',1,'2026-10-22 09:21:48','2026-10-22 09:21:48'),
(2,'spv_gudang','$2y$10$k1a4j.49z3PZ2L78D0b5kO0pIe1B3d8a7kLmN0pQrStUvWxYzAbCd','Bambang Supriyanto (SPV)','SUPERVISOR',1,'2026-10-22 09:21:48','2026-10-22 09:21:48'),
(3,'opr_timbang','$2y$10$k1a4j.49z3PZ2L78D0b5kO0pIe1B3d8a7kLmN0pQrStUvWxYzAbCd','Arif Operator Penerimaan','OPERATOR_INBOUND',1,'2026-10-22 09:21:48','2026-10-31 16:02:40'),
(4,'opr_kirim','$2y$10$k1a4j.49z3PZ2L78D0b5kO0pIe1B3d8a7kLmN0pQrStUvWxYzAbCd','Dani Operator Pengeluaran','OPERATOR_OUTBOUND',1,'2026-10-22 09:21:48','2026-10-22 09:21:48'),
(5,'finance_acc','$2y$10$k1a4j.49z3PZ2L78D0b5kO0pIe1B3d8a7kLmN0pQrStUvWxYzAbCd','Siti Rahma','FINANCE',1,'2026-10-22 09:21:48','2026-10-22 09:21:48');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vendor_non_pemasok`
--

DROP TABLE IF EXISTS `vendor_non_pemasok`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_non_pemasok` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `kode_vendor` varchar(30) NOT NULL,
  `nama_vendor` varchar(150) NOT NULL,
  `kategori_vendor` varchar(100) DEFAULT 'OPERASIONAL',
  `pic_nama` varchar(100) DEFAULT NULL,
  `telepon` varchar(50) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `bank` varchar(50) DEFAULT NULL,
  `no_rekening` varchar(50) DEFAULT NULL,
  `nama_rekening` varchar(100) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_vendor` (`kode_vendor`),
  KEY `idx_kode_vendor` (`kode_vendor`),
  KEY `idx_nama_vendor` (`nama_vendor`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vendor_non_pemasok`
--

LOCK TABLES `vendor_non_pemasok` WRITE;
/*!40000 ALTER TABLE `vendor_non_pemasok` DISABLE KEYS */;
INSERT INTO `vendor_non_pemasok` VALUES
(1,'VND-01','Toko Es Balok Muara Barokah','Es Balok & Media Pendingin','Haji Rahmat','081234567891','Muara Baru Blok C No. 12','BCA','8820192837','Rahmat Hidayat','Penyedia es balok utama untuk pendinginan ikan masuk',1,'2026-11-04 18:27:56','2026-11-04 18:27:56'),
(2,'VND-02','CV Sumber Styrofoam & Plastik','Kemasan & Packaging','Budi Santoso','081398765432','Jl. Industri No. 45 Karawang','Mandiri','1320019283746','CV Sumber Styrofoam','Penyedia box styrofoam 50kg dan plastik kemasan',1,'2026-11-04 18:27:56','2026-11-04 18:27:56'),
(3,'VND-03','SPBU Pertamina 34-41302 Karawang','Bahan Bakar & Energi','Hendra','082111223344','Jl. Raya Karawang Timur','BRI','012301002345501','PT Pertamina Retail','Bahan bakar solar genset cadangan cold storage',1,'2026-11-04 18:27:56','2026-11-04 18:27:56'),
(4,'VND-04','Test Vendor Ekspedisi Kilat Updated','Logistik & Ekspedisi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-05 02:10:42','2026-11-05 02:10:42');
/*!40000 ALTER TABLE `vendor_non_pemasok` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-08 16:57:49

-- Jurnal umum (double entry). Dibuat otomatis oleh dokumen; tidak diubah, koreksi dengan jurnal pembalik.
CREATE TABLE IF NOT EXISTS `jurnal` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `no_jurnal` VARCHAR(40) NOT NULL,
    `tanggal` DATE NOT NULL,
    `sumber_tipe` VARCHAR(30) NOT NULL,
    `sumber_id` BIGINT NOT NULL,
    `sumber_ref` VARCHAR(100) DEFAULT NULL,
    `keterangan` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('POSTED','DIBALIK') NOT NULL DEFAULT 'POSTED',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_sumber` (`sumber_tipe`, `sumber_id`),
    KEY `idx_tanggal` (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `jurnal_baris` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `jurnal_id` BIGINT NOT NULL,
    `kode_akun` VARCHAR(10) NOT NULL,
    `debit` DECIMAL(16,2) NOT NULL DEFAULT 0,
    `kredit` DECIMAL(16,2) NOT NULL DEFAULT 0,
    KEY `idx_jurnal` (`jurnal_id`),
    KEY `idx_akun` (`kode_akun`),
    CONSTRAINT `fk_jurnal_baris` FOREIGN KEY (`jurnal_id`) REFERENCES `jurnal`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Biaya beli per label dibekukan saat penerimaan (harga_per_kg dipakai harga jual saat pengeluaran)
ALTER TABLE `labels` ADD COLUMN IF NOT EXISTS `harga_beli_per_kg` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `harga_per_kg`;
