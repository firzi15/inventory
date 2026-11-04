/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.16-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: db_barcode_inventory
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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accurate_sync_queue`
--

LOCK TABLES `accurate_sync_queue` WRITE;
/*!40000 ALTER TABLE `accurate_sync_queue` DISABLE KEYS */;
INSERT INTO `accurate_sync_queue` VALUES
(2,2,'/accurate/api/item-adjustment/save.do','{\n    \"transDate\": \"24/10/2026\",\n    \"adjustmentType\": \"REDUCE\",\n    \"description\": \"Sinkronisasi Pengeluaran Barcode Gudang [MITRA 1] Ref: DO-TEST-OUT-20261024 (Total: 12.50 kg)\",\n    \"detailItem\": [\n        {\n            \"itemNo\": \"SKU-REBON-001\",\n            \"detailNotes\": \"Pengeluaran 1 kemasan barcode untuk MITRA 1\",\n            \"quantity\": 12.5,\n            \"adjustmentAccount\": \"510101\",\n            \"warehouseName\": \"GDG-M01-SUMBERPANGAN\"\n        }\n    ],\n    \"sourceSystem\": \"BARCODE_WES_V1\",\n    \"externalRefNumber\": \"TRX-REF-2\"\n}',0,3,NULL,'PENDING',NULL,NULL,NULL,'2026-10-24 05:58:55','2026-10-24 05:58:55'),
(5,5,'/accurate/api/item-adjustment/save.do','{\n    \"transDate\": \"24/10/2026\",\n    \"adjustmentType\": \"REDUCE\",\n    \"description\": \"Sinkronisasi Pengeluaran Barcode Gudang [MITRA 1] Ref: DO-OUT-20261024-001 (Total: 22.70 kg)\",\n    \"detailItem\": [\n        {\n            \"itemNo\": \"SKU-REBON-001\",\n            \"detailNotes\": \"Pengeluaran 1 kemasan barcode untuk MITRA 1\",\n            \"quantity\": 22.7,\n            \"adjustmentAccount\": \"510101\",\n            \"warehouseName\": \"GDG-M01-SUMBERPANGAN\"\n        }\n    ],\n    \"sourceSystem\": \"BARCODE_WES_V1\",\n    \"externalRefNumber\": \"TRX-REF-5\"\n}',0,3,NULL,'PENDING',NULL,NULL,NULL,'2026-10-24 15:54:04','2026-10-24 15:54:04'),
(9,9,'/accurate/api/item-adjustment/save.do','{\n    \"transDate\": \"25/10/2026\",\n    \"adjustmentType\": \"REDUCE\",\n    \"description\": \"Sinkronisasi Pengeluaran Barcode Gudang [MITRA 1] Ref: SJ-OUT-M01-261025-0001 (Total: 23.60 kg)\",\n    \"detailItem\": [\n        {\n            \"itemNo\": \"SKU-REBON-001\",\n            \"detailNotes\": \"Pengeluaran 1 kemasan barcode untuk MITRA 1\",\n            \"quantity\": 23.6,\n            \"adjustmentAccount\": \"510101\",\n            \"warehouseName\": \"GDG-M01-SUMBERPANGAN\"\n        }\n    ],\n    \"sourceSystem\": \"BARCODE_WES_V1\",\n    \"externalRefNumber\": \"TRX-REF-9\"\n}',0,3,NULL,'PENDING',NULL,NULL,NULL,'2026-10-25 02:19:12','2026-10-25 02:19:12'),
(10,10,'/accurate/api/item-adjustment/save.do','{\n    \"transDate\": \"25/10/2026\",\n    \"adjustmentType\": \"REDUCE\",\n    \"description\": \"Sinkronisasi Pengeluaran Barcode Gudang [NASRUL] Ref: SJ-MUT-20261025-001 (Total: 43.00 kg)\",\n    \"detailItem\": [\n        {\n            \"itemNo\": \"002IK-0002\",\n            \"detailNotes\": \"Pengeluaran 1 kemasan barcode untuk NASRUL\",\n            \"quantity\": 43,\n            \"adjustmentAccount\": \"510101\",\n            \"warehouseName\": \"GDG-M01-SUMBERPANGAN\"\n        }\n    ],\n    \"sourceSystem\": \"BARCODE_WES_V1\",\n    \"externalRefNumber\": \"TRX-REF-10\"\n}',0,3,NULL,'PENDING',NULL,NULL,NULL,'2026-10-25 07:50:58','2026-10-25 07:50:58'),
(11,11,'/accurate/api/item-adjustment/save.do','{\n    \"transDate\": \"25/10/2026\",\n    \"adjustmentType\": \"REDUCE\",\n    \"description\": \"Sinkronisasi Pengeluaran Barcode Gudang [NASRUL] Ref: SJ-OUT-M01-261025-0002 (Total: 43.00 kg)\",\n    \"detailItem\": [\n        {\n            \"itemNo\": \"002IK-0002\",\n            \"detailNotes\": \"Pengeluaran 1 kemasan barcode untuk NASRUL\",\n            \"quantity\": 43,\n            \"adjustmentAccount\": \"510101\",\n            \"warehouseName\": \"GDG-M01-SUMBERPANGAN\"\n        }\n    ],\n    \"sourceSystem\": \"BARCODE_WES_V1\",\n    \"externalRefNumber\": \"TRX-REF-11\"\n}',0,3,NULL,'PENDING',NULL,NULL,NULL,'2026-10-25 07:53:20','2026-10-25 07:53:20'),
(12,12,'/accurate/api/item-adjustment/save.do','{\n    \"transDate\": \"25/10/2026\",\n    \"adjustmentType\": \"REDUCE\",\n    \"description\": \"Sinkronisasi Pengeluaran Barcode Gudang [MITRA 2] Ref: SJ-MUT-20261025-001 (Total: 22.00 kg)\",\n    \"detailItem\": [\n        {\n            \"itemNo\": \"002IK-0002\",\n            \"detailNotes\": \"Pengeluaran 2 kemasan barcode untuk MITRA 2\",\n            \"quantity\": 22,\n            \"adjustmentAccount\": \"510101\",\n            \"warehouseName\": \"GDG-M02-BERKAHTERNAK\"\n        }\n    ],\n    \"sourceSystem\": \"BARCODE_WES_V1\",\n    \"externalRefNumber\": \"TRX-REF-12\"\n}',0,3,NULL,'PENDING',NULL,NULL,NULL,'2026-10-25 08:03:25','2026-10-25 08:03:25'),
(13,13,'/accurate/api/item-adjustment/save.do','{\n    \"transDate\": \"26/10/2026\",\n    \"adjustmentType\": \"REDUCE\",\n    \"description\": \"Sinkronisasi Pengeluaran Barcode Gudang [NASRUL] Ref: SJ-OUT-KH001-261026-0001 (Total: 0.00 kg)\",\n    \"detailItem\": [\n        {\n            \"itemNo\": \"002IK-0002\",\n            \"detailNotes\": \"Pengeluaran 1 kemasan barcode untuk NASRUL\",\n            \"quantity\": 0,\n            \"adjustmentAccount\": \"510101\",\n            \"warehouseName\": \"GDG-M01-SUMBERPANGAN\"\n        }\n    ],\n    \"sourceSystem\": \"BARCODE_WES_V1\",\n    \"externalRefNumber\": \"TRX-REF-13\"\n}',0,3,NULL,'PENDING',NULL,NULL,NULL,'2026-10-26 14:09:41','2026-10-26 14:09:41'),
(15,15,'/accurate/api/item-adjustment/save.do','{\n    \"transDate\": \"27/10/2026\",\n    \"adjustmentType\": \"REDUCE\",\n    \"description\": \"Sinkronisasi Pengeluaran Barcode Gudang [NASRUL] Ref: SJ-OUT-KH001-261027-0001 (Total: 24.70 kg)\",\n    \"detailItem\": [\n        {\n            \"itemNo\": \"002IK-0001\",\n            \"detailNotes\": \"Pengeluaran 1 kemasan barcode untuk NASRUL\",\n            \"quantity\": 24.7,\n            \"adjustmentAccount\": \"510101\",\n            \"warehouseName\": \"GDG-M01-SUMBERPANGAN\"\n        }\n    ],\n    \"sourceSystem\": \"BARCODE_WES_V1\",\n    \"externalRefNumber\": \"TRX-REF-15\"\n}',0,3,NULL,'PENDING',NULL,NULL,NULL,'2026-10-27 05:45:05','2026-10-27 05:45:05'),
(16,16,'/accurate/api/item-adjustment/save.do','{\n    \"transDate\": \"28/10/2026\",\n    \"adjustmentType\": \"REDUCE\",\n    \"description\": \"Sinkronisasi Pengeluaran Barcode Gudang [NASRUL] Ref: SJ-OUT-KH001-261028-0001 (Total: 15.00 kg)\",\n    \"detailItem\": [\n        {\n            \"itemNo\": \"001TR-0001\",\n            \"detailNotes\": \"Pengeluaran 2 kemasan barcode untuk NASRUL\",\n            \"quantity\": 15,\n            \"adjustmentAccount\": \"510101\",\n            \"warehouseName\": \"GDG-M01-SUMBERPANGAN\"\n        }\n    ],\n    \"sourceSystem\": \"BARCODE_WES_V1\",\n    \"externalRefNumber\": \"TRX-REF-16\"\n}',0,3,NULL,'PENDING',NULL,NULL,NULL,'2026-10-28 13:28:56','2026-10-28 13:28:56'),
(17,17,'/accurate/api/item-adjustment/save.do','{\n    \"transDate\": \"01/11/2026\",\n    \"adjustmentType\": \"REDUCE\",\n    \"description\": \"Sinkronisasi Pengeluaran Barcode Gudang [NASRUL] Ref: SJ-OUT-KH001-261101-0001 (Total: 2.00 kg)\",\n    \"detailItem\": [\n        {\n            \"itemNo\": \"SKU-REBON-001\",\n            \"detailNotes\": \"Pengeluaran 1 kemasan barcode untuk NASRUL\",\n            \"quantity\": 2,\n            \"adjustmentAccount\": \"510101\",\n            \"warehouseName\": \"GDG-M01-SUMBERPANGAN\"\n        }\n    ],\n    \"sourceSystem\": \"BARCODE_WES_V1\",\n    \"externalRefNumber\": \"TRX-REF-17\"\n}',0,3,NULL,'PENDING',NULL,NULL,NULL,'2026-11-01 03:12:24','2026-11-01 03:12:24'),
(18,18,'/accurate/api/item-adjustment/save.do','{\n    \"transDate\": \"01/11/2026\",\n    \"adjustmentType\": \"REDUCE\",\n    \"description\": \"Sinkronisasi Pengeluaran Barcode Gudang [NASRUL] Ref: SJ-OUT-KH001-261101-0002 (Total: 34.10 kg)\",\n    \"detailItem\": [\n        {\n            \"itemNo\": \"002IK-0002\",\n            \"detailNotes\": \"Pengeluaran 1 kemasan barcode untuk NASRUL\",\n            \"quantity\": 34.1,\n            \"adjustmentAccount\": \"510101\",\n            \"warehouseName\": \"GDG-M01-SUMBERPANGAN\"\n        }\n    ],\n    \"sourceSystem\": \"BARCODE_WES_V1\",\n    \"externalRefNumber\": \"TRX-REF-18\"\n}',0,3,NULL,'PENDING',NULL,NULL,NULL,'2026-11-01 08:47:15','2026-11-01 08:47:15'),
(19,19,'/accurate/api/item-adjustment/save.do','{\n    \"transDate\": \"01/11/2026\",\n    \"adjustmentType\": \"REDUCE\",\n    \"description\": \"Sinkronisasi Pengeluaran Barcode Gudang [NASRUL] Ref: SJ-OUT-BH01-261101-0001 (Total: 50.00 kg)\",\n    \"detailItem\": [\n        {\n            \"itemNo\": \"CB-SPR-JOS\",\n            \"detailNotes\": \"Pengeluaran 1 kemasan barcode untuk NASRUL\",\n            \"quantity\": 50,\n            \"adjustmentAccount\": \"510101\",\n            \"warehouseName\": \"GDG-M01-SUMBERPANGAN\"\n        }\n    ],\n    \"sourceSystem\": \"BARCODE_WES_V1\",\n    \"externalRefNumber\": \"TRX-REF-19\"\n}',0,3,NULL,'PENDING',NULL,NULL,NULL,'2026-11-01 08:48:33','2026-11-01 08:48:33');
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
  `no_surat_jalan` varchar(100) NOT NULL,
  `nominal_alokasi` decimal(14,2) NOT NULL DEFAULT 0.00,
  `sisa_utang_setelah` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ap_alokasi_bayar` (`pembayaran_id`),
  KEY `idx_ap_alokasi_sj` (`no_surat_jalan`),
  CONSTRAINT `fk_ap_alokasi_pembayaran` FOREIGN KEY (`pembayaran_id`) REFERENCES `ap_pembayaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ap_alokasi_pembayaran`
--

LOCK TABLES `ap_alokasi_pembayaran` WRITE;
/*!40000 ALTER TABLE `ap_alokasi_pembayaran` DISABLE KEYS */;
INSERT INTO `ap_alokasi_pembayaran` VALUES
(1,1,'SJ-HK-001-261025-0001',2973000.00,0.00,'2026-11-01 04:24:29'),
(2,1,'SJ-KH-001-261026-0001',1534000.00,0.00,'2026-11-01 04:24:29'),
(3,1,'SJ-KH-001-261026-0003',1260000.00,0.00,'2026-11-01 04:24:29'),
(4,1,'SJ-KH-001-261026-0004',1911000.00,0.00,'2026-11-01 04:24:29'),
(5,1,'SJ-KH-001-261027-0001',1446000.00,0.00,'2026-11-01 04:24:29'),
(6,1,'SJ-KH-001-261028-0009',630000.00,0.00,'2026-11-01 04:24:29'),
(7,1,'SJ-KH-001-261031-0001',325000.00,0.00,'2026-11-01 04:24:29'),
(8,1,'SJ-KH-001-261031-0002',65000.00,0.00,'2026-11-01 04:24:29');
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ap_pembayaran`
--

LOCK TABLES `ap_pembayaran` WRITE;
/*!40000 ALTER TABLE `ap_pembayaran` DISABLE KEYS */;
INSERT INTO `ap_pembayaran` VALUES
(1,'CD-20261101-0001',5,'2026-11-01','TUNAI',NULL,NULL,10144000.00,NULL,3,'2026-11-01 04:24:29');
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ar_alokasi_pembayaran`
--

LOCK TABLES `ar_alokasi_pembayaran` WRITE;
/*!40000 ALTER TABLE `ar_alokasi_pembayaran` DISABLE KEYS */;
INSERT INTO `ar_alokasi_pembayaran` VALUES
(4,3,16,1000000.00,500000.00,'2026-11-01 03:44:26'),
(5,4,16,500000.00,0.00,'2026-11-01 03:45:34'),
(6,5,17,100.00,169900.00,'2026-11-01 03:59:50'),
(7,6,17,169000.00,900.00,'2026-11-01 04:00:24'),
(8,7,17,900.00,0.00,'2026-11-01 04:00:40');
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ar_pembayaran`
--

LOCK TABLES `ar_pembayaran` WRITE;
/*!40000 ALTER TABLE `ar_pembayaran` DISABLE KEYS */;
INSERT INTO `ar_pembayaran` VALUES
(3,'CR-20261101-0001',1,'2026-11-01','TUNAI',NULL,1000000.00,NULL,'PENDING',NULL,0.00,NULL,NULL,NULL,NULL,3,'2026-11-01 03:44:26'),
(4,'CR-20261101-0002',1,'2026-11-01','TUNAI',NULL,500000.00,NULL,'PENDING',NULL,0.00,NULL,NULL,NULL,NULL,3,'2026-11-01 03:45:34'),
(5,'CR-20261202-0001',1,'2026-12-02','TUNAI',NULL,100.00,NULL,'PENDING',NULL,0.00,NULL,NULL,NULL,NULL,3,'2026-11-01 03:59:50'),
(6,'CR-20261201-0001',1,'2026-12-01','TUNAI',NULL,169000.00,NULL,'PENDING',NULL,0.00,NULL,NULL,NULL,NULL,3,'2026-11-01 04:00:24'),
(7,'CR-20261202-0002',1,'2026-12-02','TUNAI',NULL,900.00,NULL,'MATCHED',900.00,0.00,'assets/uploads/bukti_setoran/BUKTI_SETOR_7_20261101075614_f804b5.png',NULL,'2026-11-01 13:56:14',3,3,'2026-11-01 04:00:40');
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
(1,'GDG-COLD-A01','Cold Storage Kamar 1 - Rak A1','Cold Storage Utama','-18┬░C s/d -22┬░C (Frozen)',1,'2026-10-22 09:21:48'),
(2,'GDG-COLD-A02','Cold Storage Kamar 1 - Rak A2','Cold Storage Utama','-18┬░C s/d -22┬░C (Frozen)',1,'2026-10-22 09:21:48'),
(3,'GDG-COLD-B01','Cold Storage Kamar 2 - Rak B1','Cold Storage Utama','-18┬░C s/d -22┬░C (Frozen)',1,'2026-10-22 09:21:48'),
(4,'GDG-CHILL-C01','Chilled Room 1 - Area Segar C1','Gudang Chilled','0┬░C s/d 4┬░C (Chilled)',1,'2026-10-22 09:21:48'),
(5,'GDG-STAGE-OUT','Staging Area Pengiriman Keluar','Gudang Utama','10┬░C s/d 15┬░C (Transit)',1,'2026-10-22 09:21:48'),
(6,'GDG-OFFICE-POS','Office & Display Toko POS','Office Utama','AC Ruang (20┬░C - 24┬░C)',1,'2026-10-22 10:02:44'),
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
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `items`
--

LOCK TABLES `items` WRITE;
/*!40000 ALTER TABLE `items` DISABLE KEYS */;
INSERT INTO `items` VALUES
(9,'SKU-UDANG-001','Udang',1,NULL,0.000,'KG',1,'2026-10-22 11:31:49'),
(10,'SKU-CUMI-001','Cumi',2,NULL,0.000,'KG',1,'2026-10-22 11:31:49'),
(11,'SKU-IKAN-001','Ikan',3,NULL,0.000,'KG',1,'2026-10-22 11:31:49'),
(12,'SKU-TERI-001','TERI JENGKI',4,NULL,0.000,'KG',1,'2026-10-22 11:31:49'),
(13,'SKU-REBON-001','Rebon',1,NULL,0.000,'KG',1,'2026-10-22 11:31:49'),
(14,'001TR-0001','TERI MEDAN',4,NULL,0.000,'KG',1,'2026-10-25 07:00:43'),
(19,'001TR-0002','TERI BONTIO SPR',4,NULL,0.000,'KG',1,'2026-10-25 07:09:47'),
(20,'002IK-0001','GABUS KECIL',3,NULL,0.000,'KG',1,'2026-10-25 07:09:58'),
(21,'002IK-0002','HRKK',3,NULL,0.000,'KG',1,'2026-10-25 07:12:17'),
(22,'KBB-JMB','PEDA MERAH',3,NULL,0.000,'KG',1,'2026-11-01 08:22:42'),
(23,'CB-SPR-JOS','CUMI',2,NULL,0.000,'KG',1,'2026-11-01 08:33:39');
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
) ENGINE=InnoDB AUTO_INCREMENT=409 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `labels`
--

LOCK TABLES `labels` WRITE;
/*!40000 ALTER TABLE `labels` DISABLE KEYS */;
INSERT INTO `labels` VALUES
(242,'HK001-PRD03-261025-00001',1,5,21,NULL,34.10,0.000,34.10,35000.00,1193500.00,'SJ-HK-001-261025-0001','SJ-HK-001-261025-0001','2026-10-25',NULL,'KELUAR',2,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-25 07:19:00','2026-11-01 09:05:40',0,'2026-10-31 12:25:54',1),
(243,'HK001-PRD03-261025-00002',2,5,21,NULL,10.00,0.000,10.00,0.00,0.00,'SJ-HK-001-261025-0001','SJ-HK-001-261025-0001','2026-10-25',NULL,'AKTIF',2,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-25 07:19:00','2026-10-25 08:03:25',0,NULL,NULL),
(244,'HK001-PRD03-261025-00003',2,5,21,NULL,12.00,0.000,12.00,0.00,0.00,'SJ-HK-001-261025-0001','SJ-HK-001-261025-0001','2026-10-25',NULL,'AKTIF',2,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-25 07:19:00','2026-10-25 08:03:25',0,NULL,NULL),
(245,'HK001-PRD03-261025-00004',NULL,5,21,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-HK-001-261025-0001','SJ-HK-001-261025-0001','2026-10-25',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-25 07:19:00','2026-10-25 07:19:00',0,NULL,NULL),
(246,'HK001-PRD03-261025-00005',NULL,5,21,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-HK-001-261025-0001','SJ-HK-001-261025-0001','2026-10-25',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-25 07:19:00','2026-10-25 07:19:00',0,NULL,NULL),
(247,'HK001-PRD03-261025-00006',NULL,5,21,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-HK-001-261025-0001','SJ-HK-001-261025-0001','2026-10-25',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-25 07:19:00','2026-10-25 07:19:00',0,NULL,NULL),
(248,'HK001-PRD03-261025-00007',NULL,5,21,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-HK-001-261025-0001','SJ-HK-001-261025-0001','2026-10-25',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-25 07:19:00','2026-10-25 07:19:00',0,NULL,NULL),
(249,'HK001-PRD03-261025-00008',NULL,5,21,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-HK-001-261025-0001','SJ-HK-001-261025-0001','2026-10-25',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-25 07:19:00','2026-10-25 07:19:00',0,NULL,NULL),
(250,'HK001-PRD03-261025-00009',1,5,21,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-HK-001-261025-0001','SJ-HK-001-261025-0001','2026-10-25',NULL,'KELUAR',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-25 07:19:01','2026-10-26 14:09:41',0,NULL,NULL),
(251,'HK001-PRD03-261025-00010',1,5,21,NULL,43.00,0.000,43.00,0.00,0.00,'SJ-HK-001-261025-0001','SJ-HK-001-261025-0001','2026-10-25',NULL,'KELUAR',2,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-25 07:19:01','2026-10-25 07:53:20',0,NULL,NULL),
(252,'KH001-PRD01-261026-00001',NULL,5,9,NULL,23.60,0.000,23.60,0.00,0.00,'SJ-KH-001-261026-0001','SJ-KH-001-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (1 pcs)',NULL,'2026-10-26 15:02:41','2026-10-27 02:17:55',1,NULL,NULL),
(253,'KH001-PRD04-261026-00001',NULL,5,19,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0002','SJ-KH-001-261026-0002','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (1 pcs)',NULL,'2026-10-26 15:16:37','2026-10-26 15:16:37',0,NULL,NULL),
(254,'KH001-PRD04-261026-00002',NULL,5,19,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0002','SJ-KH-001-261026-0002','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (1 pcs)',NULL,'2026-10-26 15:17:10','2026-10-26 15:17:10',0,NULL,NULL),
(255,'KH001-PRD04-261026-00003',NULL,5,19,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0002','SJ-KH-001-261026-0002','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (1 pcs)',NULL,'2026-10-26 15:17:29','2026-10-26 15:17:29',0,NULL,NULL),
(258,'KH001-PRD03-261026-00001',NULL,5,21,NULL,42.00,0.000,42.00,0.00,0.00,'SJ-KH-001-261026-0003','SJ-KH-001-261026-0003','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (1 pcs)',NULL,'2026-10-26 15:21:30','2026-10-27 02:04:34',1,NULL,NULL),
(259,'KH001-PRD01-261026-00002',NULL,5,9,NULL,29.40,0.000,29.40,0.00,0.00,'SJ-KH-001-261026-0004','SJ-KH-001-261026-0004','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (1 pcs)',NULL,'2026-10-26 15:51:34','2026-10-27 03:11:23',1,NULL,NULL),
(260,'PMS01-PRD01-261026-00001',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 15:53:30','2026-10-27 02:35:43',0,NULL,NULL),
(261,'PMS01-PRD01-261026-00002',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 15:53:30','2026-10-27 02:35:43',0,NULL,NULL),
(262,'PMS01-PRD01-261026-00003',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 15:53:30','2026-10-27 02:35:43',0,NULL,NULL),
(263,'PMS01-PRD01-261026-00004',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 15:53:30','2026-10-27 02:35:43',0,NULL,NULL),
(264,'PMS01-PRD01-261026-00005',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 15:53:30','2026-10-27 02:35:43',0,NULL,NULL),
(265,'PMS01-PRD01-261026-00006',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 15:53:30','2026-10-27 02:35:43',0,NULL,NULL),
(266,'PMS01-PRD01-261026-00007',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 15:53:30','2026-10-27 02:35:43',0,NULL,NULL),
(267,'PMS01-PRD01-261026-00008',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 15:53:30','2026-10-27 02:35:43',0,NULL,NULL),
(268,'PMS01-PRD01-261026-00009',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 15:53:30','2026-10-27 02:35:43',0,NULL,NULL),
(269,'PMS01-PRD01-261026-00010',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 15:53:30','2026-10-27 02:35:43',0,NULL,NULL),
(270,'PMS01-PRD01-261026-00011',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 16:07:31','2026-10-27 02:35:43',0,NULL,NULL),
(271,'PMS01-PRD01-261026-00012',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 16:07:31','2026-10-27 02:35:43',0,NULL,NULL),
(272,'PMS01-PRD01-261026-00013',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 16:07:31','2026-10-27 02:35:43',0,NULL,NULL),
(273,'PMS01-PRD01-261026-00014',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 16:07:31','2026-10-27 02:35:43',0,NULL,NULL),
(274,'PMS01-PRD01-261026-00015',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 16:07:31','2026-10-27 02:35:43',0,NULL,NULL),
(275,'PMS01-PRD01-261026-00016',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 16:07:31','2026-10-27 02:35:43',0,NULL,NULL),
(276,'PMS01-PRD01-261026-00017',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 16:07:31','2026-10-27 02:35:43',0,NULL,NULL),
(277,'PMS01-PRD01-261026-00018',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 16:07:31','2026-10-27 02:35:43',0,NULL,NULL),
(278,'PMS01-PRD01-261026-00019',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 16:07:31','2026-10-27 02:35:43',0,NULL,NULL),
(279,'PMS01-PRD01-261026-00020',NULL,1,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261026-0004','SJ-PMS-01-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-26 16:07:31','2026-10-27 02:35:43',0,NULL,NULL),
(280,'KH002-PRD01-261026-00001',NULL,7,9,NULL,23.00,0.000,23.00,0.00,0.00,'SJ-KH-002-261026-0001','SJ-KH-002-261026-0001','2026-10-26',NULL,'AKTIF',3,'Batch Barcode Penerimaan (1 pcs)',NULL,'2026-10-26 16:08:24','2026-10-27 02:04:34',1,NULL,NULL),
(281,'KH001-PRD03-261027-00001',NULL,5,20,NULL,23.50,0.000,23.50,0.00,0.00,'SJ-KH-001-261027-0001','SJ-KH-001-261027-0001','2026-10-27',NULL,'AKTIF',3,'Batch Barcode Penerimaan (2 pcs)',NULL,'2026-10-27 05:38:57','2026-10-27 05:40:23',1,NULL,NULL),
(282,'KH001-PRD03-261027-00002',1,5,20,NULL,24.70,0.000,24.70,0.00,0.00,'SJ-KH-001-261027-0001','SJ-KH-001-261027-0001','2026-10-27',NULL,'KELUAR',3,'Batch Barcode Penerimaan (2 pcs)',NULL,'2026-10-27 05:38:57','2026-10-27 05:45:05',1,NULL,NULL),
(283,'KH001-PRD01-261028-00001',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0001','SJ-KH-001-261028-0001','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 05:14:41','2026-10-28 05:14:41',0,NULL,NULL),
(284,'KH001-PRD01-261028-00002',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0001','SJ-KH-001-261028-0001','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 05:14:41','2026-10-28 05:14:41',0,NULL,NULL),
(285,'KH001-PRD01-261028-00003',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0001','SJ-KH-001-261028-0001','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 05:14:41','2026-10-28 05:14:41',0,NULL,NULL),
(286,'KH001-PRD01-261028-00004',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0001','SJ-KH-001-261028-0001','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 05:14:41','2026-10-28 05:14:41',0,NULL,NULL),
(287,'KH001-PRD01-261028-00005',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0001','SJ-KH-001-261028-0001','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 05:14:41','2026-10-28 05:14:41',0,NULL,NULL),
(288,'KH001-PRD01-261028-00006',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0001','SJ-KH-001-261028-0001','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 05:14:41','2026-10-28 05:14:41',0,NULL,NULL),
(289,'KH001-PRD01-261028-00007',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0001','SJ-KH-001-261028-0001','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 05:14:41','2026-10-28 05:14:41',0,NULL,NULL),
(290,'KH001-PRD01-261028-00008',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0001','SJ-KH-001-261028-0001','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 05:14:41','2026-10-28 05:14:41',0,NULL,NULL),
(291,'KH001-PRD01-261028-00009',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0001','SJ-KH-001-261028-0001','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 05:14:41','2026-10-28 05:14:41',0,NULL,NULL),
(292,'KH001-PRD01-261028-00010',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0001','SJ-KH-001-261028-0001','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 05:14:41','2026-10-28 05:14:41',0,NULL,NULL),
(293,'KH001-PRD01-261028-00011',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0002','SJ-KH-001-261028-0002','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:37:58','2026-10-28 11:37:58',0,NULL,NULL),
(294,'KH001-PRD01-261028-00012',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0002','SJ-KH-001-261028-0002','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:37:58','2026-10-28 11:37:58',0,NULL,NULL),
(295,'KH001-PRD01-261028-00013',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0002','SJ-KH-001-261028-0002','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:37:58','2026-10-28 11:37:58',0,NULL,NULL),
(296,'KH001-PRD01-261028-00014',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0002','SJ-KH-001-261028-0002','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:37:58','2026-10-28 11:37:58',0,NULL,NULL),
(297,'KH001-PRD01-261028-00015',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0002','SJ-KH-001-261028-0002','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:37:58','2026-10-28 11:37:58',0,NULL,NULL),
(298,'KH001-PRD01-261028-00016',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0002','SJ-KH-001-261028-0002','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:37:58','2026-10-28 11:37:58',0,NULL,NULL),
(299,'KH001-PRD01-261028-00017',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0002','SJ-KH-001-261028-0002','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:37:58','2026-10-28 11:37:58',0,NULL,NULL),
(300,'KH001-PRD01-261028-00018',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0002','SJ-KH-001-261028-0002','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:37:58','2026-10-28 11:37:58',0,NULL,NULL),
(301,'KH001-PRD01-261028-00019',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0002','SJ-KH-001-261028-0002','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:37:58','2026-10-28 11:37:58',0,NULL,NULL),
(302,'KH001-PRD01-261028-00020',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0002','SJ-KH-001-261028-0002','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:37:58','2026-10-28 11:37:58',0,NULL,NULL),
(303,'KH001-PRD01-261028-00021',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0003','SJ-KH-001-261028-0003','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:44:39','2026-10-28 11:44:39',0,NULL,NULL),
(304,'KH001-PRD01-261028-00022',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0003','SJ-KH-001-261028-0003','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:44:39','2026-10-28 11:44:39',0,NULL,NULL),
(305,'KH001-PRD01-261028-00023',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0003','SJ-KH-001-261028-0003','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:44:39','2026-10-28 11:44:39',0,NULL,NULL),
(306,'KH001-PRD01-261028-00024',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0003','SJ-KH-001-261028-0003','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:44:39','2026-10-28 11:44:39',0,NULL,NULL),
(307,'KH001-PRD01-261028-00025',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0003','SJ-KH-001-261028-0003','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:44:39','2026-10-28 11:44:39',0,NULL,NULL),
(308,'KH001-PRD01-261028-00026',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0003','SJ-KH-001-261028-0003','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:44:39','2026-10-28 11:44:39',0,NULL,NULL),
(309,'KH001-PRD01-261028-00027',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0003','SJ-KH-001-261028-0003','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:44:39','2026-10-28 11:44:39',0,NULL,NULL),
(310,'KH001-PRD01-261028-00028',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0003','SJ-KH-001-261028-0003','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:44:39','2026-10-28 11:44:39',0,NULL,NULL),
(311,'KH001-PRD01-261028-00029',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0003','SJ-KH-001-261028-0003','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:44:39','2026-10-28 11:44:39',0,NULL,NULL),
(312,'KH001-PRD01-261028-00030',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0003','SJ-KH-001-261028-0003','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:44:39','2026-10-28 11:44:39',0,NULL,NULL),
(313,'KH001-PRD01-261028-00031',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0004','SJ-KH-001-261028-0004','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:23','2026-10-28 11:45:23',0,NULL,NULL),
(314,'KH001-PRD01-261028-00032',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0004','SJ-KH-001-261028-0004','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:23','2026-10-28 11:45:23',0,NULL,NULL),
(315,'KH001-PRD01-261028-00033',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0004','SJ-KH-001-261028-0004','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:23','2026-10-28 11:45:23',0,NULL,NULL),
(316,'KH001-PRD01-261028-00034',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0004','SJ-KH-001-261028-0004','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:23','2026-10-28 11:45:23',0,NULL,NULL),
(317,'KH001-PRD01-261028-00035',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0004','SJ-KH-001-261028-0004','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:23','2026-10-28 11:45:23',0,NULL,NULL),
(318,'KH001-PRD01-261028-00036',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0004','SJ-KH-001-261028-0004','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:23','2026-10-28 11:45:23',0,NULL,NULL),
(319,'KH001-PRD01-261028-00037',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0004','SJ-KH-001-261028-0004','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:23','2026-10-28 11:45:23',0,NULL,NULL),
(320,'KH001-PRD01-261028-00038',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0004','SJ-KH-001-261028-0004','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:23','2026-10-28 11:45:23',0,NULL,NULL),
(321,'KH001-PRD01-261028-00039',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0004','SJ-KH-001-261028-0004','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:23','2026-10-28 11:45:23',0,NULL,NULL),
(322,'KH001-PRD01-261028-00040',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0004','SJ-KH-001-261028-0004','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:23','2026-10-28 11:45:23',0,NULL,NULL),
(323,'KH001-PRD02-261028-00001',NULL,5,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0005','SJ-KH-001-261028-0005','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:42','2026-10-28 11:45:42',0,NULL,NULL),
(324,'KH001-PRD02-261028-00002',NULL,5,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0005','SJ-KH-001-261028-0005','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:42','2026-10-28 11:45:42',0,NULL,NULL),
(325,'KH001-PRD02-261028-00003',NULL,5,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0005','SJ-KH-001-261028-0005','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:42','2026-10-28 11:45:42',0,NULL,NULL),
(326,'KH001-PRD02-261028-00004',NULL,5,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0005','SJ-KH-001-261028-0005','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:42','2026-10-28 11:45:42',0,NULL,NULL),
(327,'KH001-PRD02-261028-00005',NULL,5,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0005','SJ-KH-001-261028-0005','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:42','2026-10-28 11:45:42',0,NULL,NULL),
(328,'KH001-PRD02-261028-00006',NULL,5,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0005','SJ-KH-001-261028-0005','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:42','2026-10-28 11:45:42',0,NULL,NULL),
(329,'KH001-PRD02-261028-00007',NULL,5,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0005','SJ-KH-001-261028-0005','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:42','2026-10-28 11:45:42',0,NULL,NULL),
(330,'KH001-PRD02-261028-00008',NULL,5,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0005','SJ-KH-001-261028-0005','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:42','2026-10-28 11:45:42',0,NULL,NULL),
(331,'KH001-PRD02-261028-00009',NULL,5,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0005','SJ-KH-001-261028-0005','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:42','2026-10-28 11:45:42',0,NULL,NULL),
(332,'KH001-PRD02-261028-00010',NULL,5,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0005','SJ-KH-001-261028-0005','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:45:42','2026-10-28 11:45:42',0,NULL,NULL),
(333,'KH001-PRD01-261028-00041',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0006','SJ-KH-001-261028-0006','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:40','2026-10-28 11:46:40',0,NULL,NULL),
(334,'KH001-PRD01-261028-00042',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0006','SJ-KH-001-261028-0006','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:40','2026-10-28 11:46:40',0,NULL,NULL),
(335,'KH001-PRD01-261028-00043',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0006','SJ-KH-001-261028-0006','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:40','2026-10-28 11:46:40',0,NULL,NULL),
(336,'KH001-PRD01-261028-00044',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0006','SJ-KH-001-261028-0006','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:40','2026-10-28 11:46:40',0,NULL,NULL),
(337,'KH001-PRD01-261028-00045',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0006','SJ-KH-001-261028-0006','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:40','2026-10-28 11:46:40',0,NULL,NULL),
(338,'KH001-PRD01-261028-00046',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0006','SJ-KH-001-261028-0006','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:40','2026-10-28 11:46:40',0,NULL,NULL),
(339,'KH001-PRD01-261028-00047',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0006','SJ-KH-001-261028-0006','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:40','2026-10-28 11:46:40',0,NULL,NULL),
(340,'KH001-PRD01-261028-00048',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0006','SJ-KH-001-261028-0006','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:40','2026-10-28 11:46:40',0,NULL,NULL),
(341,'KH001-PRD01-261028-00049',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0006','SJ-KH-001-261028-0006','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:40','2026-10-28 11:46:40',0,NULL,NULL),
(342,'KH001-PRD01-261028-00050',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0006','SJ-KH-001-261028-0006','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:40','2026-10-28 11:46:40',0,NULL,NULL),
(343,'KH001-PRD01-261028-00051',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0007','SJ-KH-001-261028-0007','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:51','2026-10-28 11:46:51',0,NULL,NULL),
(344,'KH001-PRD01-261028-00052',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0007','SJ-KH-001-261028-0007','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:51','2026-10-28 11:46:51',0,NULL,NULL),
(345,'KH001-PRD01-261028-00053',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0007','SJ-KH-001-261028-0007','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:51','2026-10-28 11:46:51',0,NULL,NULL),
(346,'KH001-PRD01-261028-00054',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0007','SJ-KH-001-261028-0007','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:51','2026-10-28 11:46:51',0,NULL,NULL),
(347,'KH001-PRD01-261028-00055',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0007','SJ-KH-001-261028-0007','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:51','2026-10-28 11:46:51',0,NULL,NULL),
(348,'KH001-PRD01-261028-00056',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0007','SJ-KH-001-261028-0007','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:51','2026-10-28 11:46:51',0,NULL,NULL),
(349,'KH001-PRD01-261028-00057',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0007','SJ-KH-001-261028-0007','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:51','2026-10-28 11:46:51',0,NULL,NULL),
(350,'KH001-PRD01-261028-00058',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0007','SJ-KH-001-261028-0007','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:51','2026-10-28 11:46:51',0,NULL,NULL),
(351,'KH001-PRD01-261028-00059',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0007','SJ-KH-001-261028-0007','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:51','2026-10-28 11:46:51',0,NULL,NULL),
(352,'KH001-PRD01-261028-00060',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0007','SJ-KH-001-261028-0007','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-10-28 11:46:51','2026-10-28 11:46:51',0,NULL,NULL),
(353,'KH001-PRD01-261028-00061',NULL,5,13,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261028-0008','SJ-KH-001-261028-0008','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (1 pcs)',NULL,'2026-10-28 11:50:26','2026-10-28 11:50:26',0,NULL,NULL),
(354,'KH002-PRD02-261028-00001',NULL,7,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-002-261028-0001','SJ-KH-002-261028-0001','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (2 pcs)',NULL,'2026-10-28 11:52:38','2026-10-28 11:52:38',0,NULL,NULL),
(355,'KH002-PRD02-261028-00002',NULL,7,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-002-261028-0001','SJ-KH-002-261028-0001','2026-10-28',NULL,'AKTIF',3,'Batch Barcode Penerimaan (2 pcs)',NULL,'2026-10-28 11:52:38','2026-10-28 11:52:38',0,NULL,NULL),
(356,'KH001-PRD04-261028-00001',1,5,14,NULL,5.00,0.000,5.00,100000.00,500000.00,'SJ-KH-001-261028-0009','SJ-KH-001-261028-0009','2026-10-28',NULL,'KELUAR',3,'Batch Barcode Penerimaan (2 pcs)',NULL,'2026-10-28 11:59:09','2026-10-28 13:28:56',0,NULL,NULL),
(357,'KH001-PRD04-261028-00002',1,5,14,NULL,10.00,0.000,10.00,100000.00,1000000.00,'SJ-KH-001-261028-0009','SJ-KH-001-261028-0009','2026-10-28',NULL,'KELUAR',3,'Batch Barcode Penerimaan (2 pcs)',NULL,'2026-10-28 11:59:10','2026-10-28 13:28:56',0,NULL,NULL),
(358,'KH001-PRD01-261031-00001',1,5,13,NULL,2.00,0.000,2.00,85000.00,170000.00,'SJ-KH-001-261031-0001','SJ-KH-001-261031-0001','2026-10-31',NULL,'KELUAR',3,'Batch Barcode Penerimaan (2 pcs)',NULL,'2026-10-31 07:24:14','2026-11-01 03:12:24',1,NULL,NULL),
(359,'KH001-PRD01-261031-00002',NULL,5,13,NULL,3.00,0.000,3.00,0.00,0.00,'SJ-KH-001-261031-0001','SJ-KH-001-261031-0001','2026-10-31',NULL,'AKTIF',3,'Batch Barcode Penerimaan (2 pcs)',NULL,'2026-10-31 07:24:14','2026-10-31 07:26:15',1,NULL,NULL),
(360,'KH001-PRD01-261031-00003',NULL,5,13,NULL,1.00,0.000,1.00,0.00,0.00,'SJ-KH-001-261031-0002','SJ-KH-001-261031-0002','2026-10-31',NULL,'AKTIF',3,'Batch Barcode Penerimaan (1 pcs)',NULL,'2026-10-31 07:27:13','2026-10-31 07:28:36',1,NULL,NULL),
(361,'KH001-PRD04-261101-00001',NULL,5,19,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261101-0001','SJ-KH-001-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (8 pcs)',NULL,'2026-11-01 08:07:22','2026-11-01 08:07:22',0,NULL,NULL),
(362,'KH001-PRD04-261101-00002',NULL,5,19,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261101-0001','SJ-KH-001-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (8 pcs)',NULL,'2026-11-01 08:07:22','2026-11-01 08:07:22',0,NULL,NULL),
(363,'KH001-PRD04-261101-00003',NULL,5,19,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261101-0001','SJ-KH-001-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (8 pcs)',NULL,'2026-11-01 08:07:22','2026-11-01 08:07:22',0,NULL,NULL),
(364,'KH001-PRD04-261101-00004',NULL,5,19,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261101-0001','SJ-KH-001-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (8 pcs)',NULL,'2026-11-01 08:07:22','2026-11-01 08:07:22',0,NULL,NULL),
(365,'KH001-PRD04-261101-00005',NULL,5,19,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261101-0001','SJ-KH-001-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (8 pcs)',NULL,'2026-11-01 08:07:22','2026-11-01 08:07:22',0,NULL,NULL),
(366,'KH001-PRD04-261101-00006',NULL,5,19,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261101-0001','SJ-KH-001-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (8 pcs)',NULL,'2026-11-01 08:07:22','2026-11-01 08:07:22',0,NULL,NULL),
(367,'KH001-PRD04-261101-00007',NULL,5,19,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261101-0001','SJ-KH-001-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (8 pcs)',NULL,'2026-11-01 08:07:22','2026-11-01 08:07:22',0,NULL,NULL),
(368,'KH001-PRD04-261101-00008',NULL,5,19,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-KH-001-261101-0001','SJ-KH-001-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (8 pcs)',NULL,'2026-11-01 08:07:22','2026-11-01 08:07:22',0,NULL,NULL),
(369,'BH01-PRD02-261101-00001',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0001','SJ-BH-01-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:19:26','2026-11-01 08:19:26',0,NULL,NULL),
(370,'BH01-PRD02-261101-00002',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0001','SJ-BH-01-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:19:26','2026-11-01 08:19:26',0,NULL,NULL),
(371,'BH01-PRD02-261101-00003',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0001','SJ-BH-01-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:19:26','2026-11-01 08:19:26',0,NULL,NULL),
(372,'BH01-PRD02-261101-00004',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0001','SJ-BH-01-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:19:26','2026-11-01 08:19:26',0,NULL,NULL),
(373,'BH01-PRD02-261101-00005',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0001','SJ-BH-01-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:19:26','2026-11-01 08:19:26',0,NULL,NULL),
(374,'BH01-PRD02-261101-00006',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0001','SJ-BH-01-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:19:26','2026-11-01 08:19:26',0,NULL,NULL),
(375,'BH01-PRD02-261101-00007',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0001','SJ-BH-01-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:19:26','2026-11-01 08:19:26',0,NULL,NULL),
(376,'BH01-PRD02-261101-00008',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0001','SJ-BH-01-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:19:26','2026-11-01 08:19:26',0,NULL,NULL),
(377,'BH01-PRD02-261101-00009',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0001','SJ-BH-01-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:19:26','2026-11-01 08:19:26',0,NULL,NULL),
(378,'BH01-PRD02-261101-00010',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0001','SJ-BH-01-261101-0001','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:19:26','2026-11-01 08:19:26',0,NULL,NULL),
(379,'BH01-PRD03-261101-00001',NULL,8,22,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0002','SJ-BH-01-261101-0002','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:23:16','2026-11-01 08:23:16',0,NULL,NULL),
(380,'BH01-PRD03-261101-00002',NULL,8,22,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0002','SJ-BH-01-261101-0002','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:23:16','2026-11-01 08:23:16',0,NULL,NULL),
(381,'BH01-PRD03-261101-00003',NULL,8,22,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0002','SJ-BH-01-261101-0002','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:23:16','2026-11-01 08:23:16',0,NULL,NULL),
(382,'BH01-PRD03-261101-00004',NULL,8,22,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0002','SJ-BH-01-261101-0002','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:23:16','2026-11-01 08:23:16',0,NULL,NULL),
(383,'BH01-PRD03-261101-00005',NULL,8,22,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0002','SJ-BH-01-261101-0002','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:23:16','2026-11-01 08:23:16',0,NULL,NULL),
(384,'BH01-PRD03-261101-00006',NULL,8,22,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0002','SJ-BH-01-261101-0002','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:23:16','2026-11-01 08:23:16',0,NULL,NULL),
(385,'BH01-PRD03-261101-00007',NULL,8,22,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0002','SJ-BH-01-261101-0002','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:23:16','2026-11-01 08:23:16',0,NULL,NULL),
(386,'BH01-PRD03-261101-00008',NULL,8,22,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0002','SJ-BH-01-261101-0002','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:23:16','2026-11-01 08:23:16',0,NULL,NULL),
(387,'BH01-PRD03-261101-00009',NULL,8,22,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0002','SJ-BH-01-261101-0002','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:23:16','2026-11-01 08:23:16',0,NULL,NULL),
(388,'BH01-PRD03-261101-00010',NULL,8,22,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0002','SJ-BH-01-261101-0002','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:23:16','2026-11-01 08:23:16',0,NULL,NULL),
(389,'BH01-PRD02-261101-00011',NULL,8,23,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0003','SJ-BH-01-261101-0003','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:37:49','2026-11-01 08:37:49',0,NULL,NULL),
(390,'BH01-PRD02-261101-00012',NULL,8,23,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0003','SJ-BH-01-261101-0003','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:37:49','2026-11-01 08:37:49',0,NULL,NULL),
(391,'BH01-PRD02-261101-00013',NULL,8,23,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0003','SJ-BH-01-261101-0003','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:37:49','2026-11-01 08:37:49',0,NULL,NULL),
(392,'BH01-PRD02-261101-00014',NULL,8,23,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0003','SJ-BH-01-261101-0003','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:37:49','2026-11-01 08:37:49',0,NULL,NULL),
(393,'BH01-PRD02-261101-00015',NULL,8,23,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0003','SJ-BH-01-261101-0003','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:37:49','2026-11-01 08:37:49',0,NULL,NULL),
(394,'BH01-PRD02-261101-00016',NULL,8,23,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0003','SJ-BH-01-261101-0003','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:37:49','2026-11-01 08:37:49',0,NULL,NULL),
(395,'BH01-PRD02-261101-00017',NULL,8,23,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0003','SJ-BH-01-261101-0003','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:37:49','2026-11-01 08:37:49',0,NULL,NULL),
(396,'BH01-PRD02-261101-00018',NULL,8,23,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0003','SJ-BH-01-261101-0003','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:37:49','2026-11-01 08:37:49',0,NULL,NULL),
(397,'BH01-PRD02-261101-00019',NULL,8,23,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261101-0003','SJ-BH-01-261101-0003','2026-11-01',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:37:49','2026-11-01 08:37:49',0,NULL,NULL),
(398,'BH01-PRD02-261101-00020',1,8,23,NULL,50.00,0.000,50.00,100000.00,5000000.00,'SJ-BH-01-261101-0003','SJ-BH-01-261101-0003','2026-11-01',NULL,'KELUAR',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-01 08:37:49','2026-11-01 09:05:40',0,NULL,NULL),
(399,'BH01-PRD02-261103-00001',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261103-0001','SJ-BH-01-261103-0001','2026-11-03',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-03 13:20:35','2026-11-03 13:20:35',0,NULL,NULL),
(400,'BH01-PRD02-261103-00002',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261103-0001','SJ-BH-01-261103-0001','2026-11-03',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-03 13:20:35','2026-11-03 13:20:35',0,NULL,NULL),
(401,'BH01-PRD02-261103-00003',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261103-0001','SJ-BH-01-261103-0001','2026-11-03',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-03 13:20:35','2026-11-03 13:20:35',0,NULL,NULL),
(402,'BH01-PRD02-261103-00004',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261103-0001','SJ-BH-01-261103-0001','2026-11-03',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-03 13:20:35','2026-11-03 13:20:35',0,NULL,NULL),
(403,'BH01-PRD02-261103-00005',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261103-0001','SJ-BH-01-261103-0001','2026-11-03',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-03 13:20:35','2026-11-03 13:20:35',0,NULL,NULL),
(404,'BH01-PRD02-261103-00006',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261103-0001','SJ-BH-01-261103-0001','2026-11-03',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-03 13:20:35','2026-11-03 13:20:35',0,NULL,NULL),
(405,'BH01-PRD02-261103-00007',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261103-0001','SJ-BH-01-261103-0001','2026-11-03',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-03 13:20:35','2026-11-03 13:20:35',0,NULL,NULL),
(406,'BH01-PRD02-261103-00008',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261103-0001','SJ-BH-01-261103-0001','2026-11-03',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-03 13:20:35','2026-11-03 13:20:35',0,NULL,NULL),
(407,'BH01-PRD02-261103-00009',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261103-0001','SJ-BH-01-261103-0001','2026-11-03',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-03 13:20:35','2026-11-03 13:20:35',0,NULL,NULL),
(408,'BH01-PRD02-261103-00010',NULL,8,10,NULL,0.00,0.000,0.00,0.00,0.00,'SJ-BH-01-261103-0001','SJ-BH-01-261103-0001','2026-11-03',NULL,'AKTIF',3,'Batch Barcode Penerimaan (10 pcs)',NULL,'2026-11-03 13:20:35','2026-11-03 13:20:35',0,NULL,NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=401 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `master_harga`
--

LOCK TABLES `master_harga` WRITE;
/*!40000 ALTER TABLE `master_harga` DISABLE KEYS */;
INSERT INTO `master_harga` VALUES
(57,1,9,85000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(58,1,10,75000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(59,1,11,45000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(60,1,12,60000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(61,1,13,85000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(62,2,9,89500.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(63,2,10,79000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(64,2,11,47500.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(65,2,12,63000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(66,2,13,85000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(67,3,9,81500.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(68,3,10,72000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(69,3,11,43000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(70,3,12,57500.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(71,3,13,85000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(72,4,9,86500.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(73,4,10,76500.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(74,4,11,46000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(75,4,12,61000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(76,4,13,85000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(77,5,9,92000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(78,5,10,81000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(79,5,11,48500.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(80,5,12,65000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(81,5,13,85000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(82,6,9,80000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(83,6,10,70500.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(84,6,11,42500.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(85,6,12,56500.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:06'),
(86,6,13,85000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:05'),
(87,7,9,88500.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:06'),
(88,7,10,78000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:06'),
(89,7,11,47000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:06'),
(90,7,12,62500.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:06'),
(91,7,13,85000.00,NULL,'2026-10-22 11:31:49','2026-10-27 05:48:06'),
(127,1,1,35600.00,NULL,'2026-10-23 15:44:06','2026-10-23 15:44:06'),
(201,1,20,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(202,1,21,35000.00,NULL,'2026-10-25 07:24:04','2026-11-01 09:05:00'),
(204,1,19,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(206,1,14,100000.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(210,2,20,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(211,2,21,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(213,2,19,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(215,2,14,95000.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(219,3,20,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(220,3,21,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(222,3,19,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(224,3,14,90000.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(228,4,20,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(229,4,21,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(231,4,19,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(233,4,14,105000.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(237,5,20,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(238,5,21,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(240,5,19,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(242,5,14,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(246,6,20,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(247,6,21,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:05'),
(249,6,19,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:06'),
(251,6,14,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:06'),
(255,7,20,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:06'),
(256,7,21,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:06'),
(258,7,19,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:06'),
(260,7,14,0.00,NULL,'2026-10-25 07:24:04','2026-10-27 05:48:06'),
(387,1,22,45000.00,NULL,'2026-11-01 09:04:32','2026-11-01 09:04:32'),
(388,2,22,45000.00,NULL,'2026-11-01 09:04:32','2026-11-01 09:04:32'),
(389,3,22,45000.00,NULL,'2026-11-01 09:04:32','2026-11-01 09:04:32'),
(390,4,22,45000.00,NULL,'2026-11-01 09:04:32','2026-11-01 09:04:32'),
(391,5,22,45000.00,NULL,'2026-11-01 09:04:32','2026-11-01 09:04:32'),
(392,6,22,45000.00,NULL,'2026-11-01 09:04:32','2026-11-01 09:04:32'),
(393,7,22,45000.00,NULL,'2026-11-01 09:04:32','2026-11-01 09:04:32'),
(394,1,23,100000.00,NULL,'2026-11-01 09:04:32','2026-11-01 09:04:32'),
(395,2,23,100000.00,NULL,'2026-11-01 09:04:32','2026-11-01 09:04:32'),
(396,3,23,100000.00,NULL,'2026-11-01 09:04:32','2026-11-01 09:04:32'),
(397,4,23,100000.00,NULL,'2026-11-01 09:04:32','2026-11-01 09:04:32'),
(398,5,23,100000.00,NULL,'2026-11-01 09:04:32','2026-11-01 09:04:32'),
(399,6,23,100000.00,NULL,'2026-11-01 09:04:32','2026-11-01 09:04:32'),
(400,7,23,100000.00,NULL,'2026-11-01 09:04:32','2026-11-01 09:04:32');
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
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `master_harga_pemasok`
--

LOCK TABLES `master_harga_pemasok` WRITE;
/*!40000 ALTER TABLE `master_harga_pemasok` DISABLE KEYS */;
INSERT INTO `master_harga_pemasok` VALUES
(37,5,9,1,65000.00,NULL,'2026-10-26 14:23:48','2026-11-01 08:15:56'),
(38,5,10,2,55000.00,NULL,'2026-10-26 14:23:48','2026-11-01 08:15:56'),
(39,5,11,3,30000.00,NULL,'2026-10-26 14:23:48','2026-11-01 08:15:56'),
(40,5,12,4,42000.00,NULL,'2026-10-26 14:23:48','2026-11-01 08:15:56'),
(41,5,13,1,65000.00,NULL,'2026-10-26 14:23:48','2026-11-01 08:15:56'),
(42,5,14,4,42000.00,NULL,'2026-10-26 14:23:48','2026-11-01 08:15:56'),
(43,5,19,4,42000.00,NULL,'2026-10-26 14:23:48','2026-11-01 08:15:56'),
(44,5,20,3,30000.00,NULL,'2026-10-26 14:23:48','2026-11-01 08:15:56'),
(45,5,21,3,30000.00,NULL,'2026-10-26 14:23:48','2026-11-01 08:15:56'),
(55,7,13,1,64500.00,NULL,'2026-10-26 15:55:24','2026-11-01 08:15:56'),
(56,7,9,1,64500.00,NULL,'2026-10-26 15:55:24','2026-11-01 08:15:56'),
(57,7,10,2,5500.00,NULL,'2026-10-26 15:55:24','2026-11-01 08:15:56'),
(58,7,20,3,25000.00,NULL,'2026-10-26 15:55:24','2026-11-01 08:15:56'),
(59,7,21,3,27000.00,NULL,'2026-10-26 15:55:24','2026-11-01 08:15:56'),
(60,7,11,3,28500.00,NULL,'2026-10-26 15:55:24','2026-11-01 08:15:56'),
(61,7,19,4,41500.00,NULL,'2026-10-26 15:55:24','2026-11-01 08:15:56'),
(62,7,12,4,41200.00,NULL,'2026-10-26 15:55:24','2026-11-01 08:15:56'),
(63,7,14,4,42500.00,NULL,'2026-10-26 15:55:24','2026-11-01 08:15:56'),
(82,8,13,1,75000.00,NULL,'2026-11-01 08:15:56','2026-11-01 08:15:56'),
(83,8,9,1,95000.00,NULL,'2026-11-01 08:15:56','2026-11-01 08:15:56'),
(84,8,10,2,90000.00,NULL,'2026-11-01 08:15:56','2026-11-01 08:15:56'),
(85,8,20,3,65000.00,NULL,'2026-11-01 08:15:56','2026-11-01 08:15:56'),
(86,8,21,3,70000.00,NULL,'2026-11-01 08:15:56','2026-11-01 08:15:56'),
(87,8,11,3,55000.00,NULL,'2026-11-01 08:15:56','2026-11-01 08:15:56'),
(88,8,19,4,70000.00,NULL,'2026-11-01 08:15:56','2026-11-01 08:15:56'),
(89,8,12,4,60000.00,NULL,'2026-11-01 08:15:56','2026-11-01 08:15:56'),
(90,8,14,4,95000.00,NULL,'2026-11-01 08:15:56','2026-11-01 08:15:56');
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
INSERT INTO `master_harga_pemasok_old_jenis` VALUES
(17,5,1,65000.00,'Harga Beli Standar UDIN - Udang','2026-10-26 13:47:39','2026-10-26 13:47:39'),
(18,5,2,55000.00,'Harga Beli Standar UDIN - Cumi','2026-10-26 13:47:39','2026-10-26 13:47:39'),
(19,5,3,30000.00,'Harga Beli Standar UDIN - IKAN','2026-10-26 13:47:39','2026-10-26 13:47:39'),
(20,5,4,42000.00,'Harga Beli Standar UDIN - TERI','2026-10-26 13:47:39','2026-10-26 13:47:39');
/*!40000 ALTER TABLE `master_harga_pemasok_old_jenis` ENABLE KEYS */;
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
(1,'M01','NASRUL','NASRUL','0812-3456-7891','GDG-M01-SUMBERPANGAN','#2563eb',1,'2026-10-22 09:21:48'),
(2,'M02','MITRA 2','Ustadz Mansur','0813-8822-1102','GDG-M02-BERKAHTERNAK','#16a34a',1,'2026-10-22 09:21:48'),
(3,'M03','MITRA 3','Agus Setiawan','0811-9988-7703','GDG-M03-NUSANTARA','#d97706',1,'2026-10-22 09:21:48'),
(4,'M04','MITRA 4','Dewi Lestari','0821-4455-6604','GDG-M04-GLOBALAGRI','#9333ea',1,'2026-10-22 09:21:48'),
(5,'M05','MITRA 5','Surya Saputra','0852-1122-3305','GDG-M05-MITRAMAKMUR','#0891b2',1,'2026-10-22 09:21:48'),
(6,'M06','MITRA 6','Kevin Hartono','0818-7766-5506','GDG-M06-INDOJAYA','#e11d48',1,'2026-10-22 09:21:48'),
(7,'M07','MITRA 7','Joko Widodo SE','0878-3344-5507','GDG-M07-KOPERASI','#4f46e5',1,'2026-10-22 09:21:48');
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
(8,'BH-01','karawang','erwin','085695066874','karawang',NULL,1,'2026-11-01 08:11:57','2026-11-01 08:11:57');
/*!40000 ALTER TABLE `pemasok` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaksi_stok`
--

LOCK TABLES `transaksi_stok` WRITE;
/*!40000 ALTER TABLE `transaksi_stok` DISABLE KEYS */;
INSERT INTO `transaksi_stok` VALUES
(2,'TRX-OUT-261024-0001',NULL,'PENGELUARAN','MUTASI_LOKASI',1,NULL,NULL,NULL,12.50,1,0.00,0.00,0.00,4,NULL,NULL,NULL,NULL,NULL,'DO-TEST-OUT-20261024','Kirim ke Restoran Customer POS',NULL,'PENDING',NULL,'2026-10-24 05:58:55'),
(5,'TRX-OUT-261024-0002',NULL,'PENGELUARAN','MUTASI_LOKASI',1,NULL,NULL,NULL,22.70,1,0.00,0.00,0.00,2,NULL,NULL,NULL,NULL,NULL,'DO-OUT-20261024-001','Cabang Bekasi',NULL,'PENDING',NULL,'2026-10-24 15:54:04'),
(9,'TRX-OUT-261025-0001','SJ-OUT-M01-261025-0001','PENGELUARAN','PENGELUARAN_GUDANG',1,NULL,1,NULL,23.60,1,0.00,0.00,0.00,4,'Dani Operator Pengeluaran','PT Sumber Rejeki Retail Surabaya',NULL,NULL,NULL,'SJ-OUT-M01-261025-0001','Truk Ekspedisi B 9999 XYZ / Driver Budi',NULL,'PENDING',NULL,'2026-10-25 02:19:12'),
(10,'TRX-ALOK-261025-0001','BAST-261025-0001','MUTASI','PEMASOK_KE_MITRA',1,5,NULL,1,43.00,1,0.00,0.00,0.00,2,'Supriyadi (Perwakilan Pemasok / Driver)','Bambang Supriyanto (SPV)',2,NULL,NULL,'SJ-MUT-20261025-001','Pemindahan Stok: PEMASOK_KE_MITRA','Kemasan utuh tersegel rapi, suhu cold storage terjaga.','PENDING',NULL,'2026-10-25 07:50:58'),
(11,'TRX-OUT-261025-0002','SJ-OUT-M01-261025-0002','PENGELUARAN','PENGELUARAN_GUDANG',1,NULL,1,NULL,43.00,1,0.00,0.00,0.00,3,'Rizky Operator Penerimaan','NASRUL',NULL,NULL,NULL,'SJ-OUT-M01-261025-0002','',NULL,'PENDING',NULL,'2026-10-25 07:53:20'),
(12,'TRX-ALOK-261025-0002','BAST-261025-0002','MUTASI','PEMASOK_KE_MITRA',2,5,NULL,2,22.00,2,0.00,0.00,0.00,2,'Supriyadi (Perwakilan Pemasok / Driver)','Bambang Supriyanto (SPV)',2,NULL,NULL,'SJ-MUT-20261025-001','Pemindahan Stok: PEMASOK_KE_MITRA','Kemasan utuh tersegel rapi, suhu cold storage terjaga.','PENDING',NULL,'2026-10-25 08:03:25'),
(13,'TRX-OUT-261026-0001','SJ-OUT-KH001-261026-0001','PENGELUARAN','GUDANG_KE_MITRA',1,5,NULL,1,0.00,1,0.00,0.00,0.00,4,'Dani Operator Pengeluaran','Toko Uji Coba Mitra',NULL,NULL,NULL,'SJ-OUT-KH001-261026-0001','Uji Coba Pengeluaran Antigravity',NULL,'PENDING',NULL,'2026-10-26 14:09:41'),
(15,'TRX-OUT-261027-0001','SJ-OUT-KH001-261027-0001','PENGELUARAN','GUDANG_KE_MITRA',1,5,NULL,1,24.70,1,741000.00,0.00,-741000.00,3,'Rizky Operator Penerimaan','faisal',NULL,NULL,NULL,'SJ-OUT-KH001-261027-0001','diambil atas nam afahmi',NULL,'PENDING',NULL,'2026-10-27 05:45:05'),
(16,'TRX-OUT-261028-0001','SJ-OUT-KH001-261028-0001','PENGELUARAN','GUDANG_KE_MITRA',1,5,NULL,1,15.00,2,630000.00,1500000.00,870000.00,3,'Rizky Operator Penerimaan','Arif',NULL,NULL,NULL,'SJ-OUT-KH001-261028-0001','B8338SYK',NULL,'PENDING',NULL,'2026-10-28 13:28:56'),
(17,'TRX-OUT-261101-0001','SJ-OUT-KH001-261101-0001','PENGELUARAN','GUDANG_KE_MITRA',1,5,NULL,1,2.00,1,130000.00,170000.00,40000.00,3,'Arif Operator Penerimaan','Wahyu',NULL,NULL,NULL,'SJ-OUT-KH001-261101-0001','B345SYK',NULL,'PENDING',NULL,'2026-11-01 03:12:24'),
(18,'TRX-OUT-261101-0002','SJ-OUT-KH001-261101-0002','PENGELUARAN','GUDANG_KE_MITRA',1,5,NULL,1,34.10,1,1023000.00,1193500.00,170500.00,3,'Arif Operator Penerimaan','Udin',NULL,NULL,NULL,'SJ-OUT-KH001-261101-0002','SYK',NULL,'PENDING',NULL,'2026-11-01 08:47:15'),
(19,'TRX-OUT-261101-0003','SJ-OUT-BH01-261101-0001','PENGELUARAN','GUDANG_KE_MITRA',1,8,NULL,1,50.00,1,4500000.00,5000000.00,500000.00,3,'Arif Operator Penerimaan','Udin',NULL,NULL,NULL,'SJ-OUT-BH01-261101-0001','SYK',NULL,'PENDING',NULL,'2026-11-01 08:48:33');
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
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaksi_stok_detail`
--

LOCK TABLES `transaksi_stok_detail` WRITE;
/*!40000 ALTER TABLE `transaksi_stok_detail` DISABLE KEYS */;
INSERT INTO `transaksi_stok_detail` VALUES
(18,10,251,43.00,0.00,0.00,0.00,0.00,0.00,'2026-10-25 07:50:58'),
(19,11,251,43.00,0.00,0.00,0.00,0.00,0.00,'2026-10-25 07:53:20'),
(20,12,244,12.00,0.00,0.00,0.00,0.00,0.00,'2026-10-25 08:03:25'),
(21,12,243,10.00,0.00,0.00,0.00,0.00,0.00,'2026-10-25 08:03:25'),
(22,13,250,0.00,30000.00,0.00,0.00,0.00,0.00,'2026-10-26 14:09:41'),
(24,15,282,24.70,30000.00,0.00,741000.00,0.00,-741000.00,'2026-10-27 05:45:05'),
(25,16,356,5.00,42000.00,100000.00,210000.00,500000.00,290000.00,'2026-10-28 13:28:56'),
(26,16,357,10.00,42000.00,100000.00,420000.00,1000000.00,580000.00,'2026-10-28 13:28:56'),
(27,17,358,2.00,65000.00,85000.00,130000.00,170000.00,40000.00,'2026-11-01 03:12:24'),
(28,18,242,34.10,30000.00,35000.00,1023000.00,1193500.00,170500.00,'2026-11-01 08:47:15'),
(29,19,398,50.00,90000.00,100000.00,4500000.00,5000000.00,500000.00,'2026-11-01 08:48:33');
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
(5,'finance_acc','$2y$10$k1a4j.49z3PZ2L78D0b5kO0pIe1B3d8a7kLmN0pQrStUvWxYzAbCd','Siti Rahma (Finance)','FINANCE',1,'2026-10-22 09:21:48','2026-10-22 09:21:48');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'db_barcode_inventory'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-04  9:18:15
