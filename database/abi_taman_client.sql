/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.16-MariaDB, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: abi_taman
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
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `activity_type` varchar(80) NOT NULL,
  `description` text NOT NULL,
  `reference_type` varchar(80) DEFAULT NULL,
  `reference_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `additional_cost_types`
--

DROP TABLE IF EXISTS `additional_cost_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `additional_cost_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `default_price` bigint(20) unsigned NOT NULL DEFAULT 0,
  `unit` varchar(50) NOT NULL DEFAULT 'item',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `additional_cost_types`
--

LOCK TABLES `additional_cost_types` WRITE;
/*!40000 ALTER TABLE `additional_cost_types` DISABLE KEYS */;
INSERT INTO `additional_cost_types` VALUES
(1,'Transportasi','Biaya mobilisasi tim dan material ke lokasi.',200000,'trip','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(2,'Angkut puing','Pengangkutan sisa bongkaran atau sampah taman.',250000,'rit','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(3,'Sewa alat','Sewa alat kerja tambahan.',150000,'hari','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(4,'Akses lokasi sulit','Tambahan biaya untuk akses sempit, bertingkat, atau jauh dari parkir.',300000,'lokasi','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(5,'Pembersihan Lahan dan Pembongkaran lahan','Pembersihan dan pembongkaran area sebelum pekerjaan taman.',0,'item','active','2026-07-27 17:39:37','2026-08-01 16:05:42');
/*!40000 ALTER TABLE `additional_cost_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_status_histories`
--

DROP TABLE IF EXISTS `booking_status_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_status_histories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `old_status` varchar(80) DEFAULT NULL,
  `new_status` varchar(80) NOT NULL,
  `changed_by` int(10) unsigned DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `changed_by` (`changed_by`),
  CONSTRAINT `booking_status_histories_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_status_histories_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_status_histories`
--

LOCK TABLES `booking_status_histories` WRITE;
/*!40000 ALTER TABLE `booking_status_histories` DISABLE KEYS */;
/*!40000 ALTER TABLE `booking_status_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_code` varchar(30) NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `service_id` int(10) unsigned DEFAULT NULL,
  `is_custom_service` tinyint(1) NOT NULL DEFAULT 0,
  `custom_service_description` text DEFAULT NULL,
  `requested_survey_date` date NOT NULL,
  `actual_survey_date` date DEFAULT NULL,
  `actual_survey_time` time DEFAULT NULL,
  `location_address` text NOT NULL,
  `estimated_area` decimal(12,2) DEFAULT NULL,
  `actual_area` decimal(12,2) DEFAULT NULL,
  `customer_notes` text DEFAULT NULL,
  `reference_photo` varchar(255) DEFAULT NULL,
  `status` enum('Menunggu Konfirmasi','Survey Dijadwalkan','Survey Berlangsung','Survey Selesai','Estimasi Dibuat','Menunggu Persetujuan Customer','Estimasi Disetujui','Estimasi Ditolak','Dibatalkan','Dalam Pengerjaan','Revisi Estimasi','Pekerjaan Selesai','Invoice Final Dibuat','Menunggu Pembayaran','Lunas','Selesai') NOT NULL DEFAULT 'Menunggu Konfirmasi',
  `survey_fee_amount` bigint(20) unsigned NOT NULL DEFAULT 100000,
  `survey_fee_payment_status` enum('Tidak Ada','Belum Dibayar','Sudah Dibayar') NOT NULL DEFAULT 'Tidak Ada',
  `assigned_pekerja_lapangan_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_code` (`booking_code`),
  KEY `customer_id` (`customer_id`),
  KEY `service_id` (`service_id`),
  KEY `created_by` (`created_by`),
  KEY `assigned_pekerja_lapangan_id` (`assigned_pekerja_lapangan_id`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bookings_ibfk_4` FOREIGN KEY (`assigned_pekerja_lapangan_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bookings_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estimate_items`
--

DROP TABLE IF EXISTS `estimate_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estimate_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `estimate_id` int(10) unsigned NOT NULL,
  `item_type` enum('material','labor','additional') NOT NULL,
  `item_name` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(50) DEFAULT NULL,
  `unit_price` bigint(20) unsigned NOT NULL DEFAULT 0,
  `subtotal` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `estimate_id` (`estimate_id`),
  CONSTRAINT `estimate_items_ibfk_1` FOREIGN KEY (`estimate_id`) REFERENCES `estimates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estimate_items`
--

LOCK TABLES `estimate_items` WRITE;
/*!40000 ALTER TABLE `estimate_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `estimate_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estimate_revision_items`
--

DROP TABLE IF EXISTS `estimate_revision_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estimate_revision_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `revision_id` int(10) unsigned NOT NULL,
  `item_name` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(50) DEFAULT NULL,
  `unit_price` bigint(20) unsigned NOT NULL DEFAULT 0,
  `subtotal` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `revision_id` (`revision_id`),
  CONSTRAINT `estimate_revision_items_ibfk_1` FOREIGN KEY (`revision_id`) REFERENCES `estimate_revisions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estimate_revision_items`
--

LOCK TABLES `estimate_revision_items` WRITE;
/*!40000 ALTER TABLE `estimate_revision_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `estimate_revision_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estimate_revisions`
--

DROP TABLE IF EXISTS `estimate_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estimate_revisions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `revision_code` varchar(30) NOT NULL,
  `estimate_id` int(10) unsigned NOT NULL,
  `booking_id` int(10) unsigned NOT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `reason` text NOT NULL,
  `old_total` bigint(20) unsigned NOT NULL DEFAULT 0,
  `additional_total` bigint(20) unsigned NOT NULL DEFAULT 0,
  `new_total` bigint(20) unsigned NOT NULL DEFAULT 0,
  `status` enum('Draft','Dikirim','Disetujui','Ditolak') NOT NULL DEFAULT 'Draft',
  `customer_response_note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `revision_code` (`revision_code`),
  KEY `estimate_id` (`estimate_id`),
  KEY `booking_id` (`booking_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `estimate_revisions_ibfk_1` FOREIGN KEY (`estimate_id`) REFERENCES `estimates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `estimate_revisions_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `estimate_revisions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estimate_revisions`
--

LOCK TABLES `estimate_revisions` WRITE;
/*!40000 ALTER TABLE `estimate_revisions` DISABLE KEYS */;
/*!40000 ALTER TABLE `estimate_revisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estimates`
--

DROP TABLE IF EXISTS `estimates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estimates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `estimate_code` varchar(30) NOT NULL,
  `booking_id` int(10) unsigned NOT NULL,
  `survey_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `area_size` decimal(12,2) NOT NULL,
  `block_count` int(10) unsigned NOT NULL,
  `material_total` bigint(20) unsigned NOT NULL DEFAULT 0,
  `labor_total` bigint(20) unsigned NOT NULL DEFAULT 0,
  `additional_total` bigint(20) unsigned NOT NULL DEFAULT 0,
  `grand_total` bigint(20) unsigned NOT NULL DEFAULT 0,
  `estimated_days` int(10) unsigned NOT NULL DEFAULT 1,
  `worker_count` int(10) unsigned NOT NULL DEFAULT 1,
  `status` enum('Draft','Dikirim','Disetujui','Ditolak','Direvisi') NOT NULL DEFAULT 'Draft',
  `customer_response_note` text DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `estimate_code` (`estimate_code`),
  KEY `booking_id` (`booking_id`),
  KEY `survey_id` (`survey_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `estimates_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `estimates_ibfk_2` FOREIGN KEY (`survey_id`) REFERENCES `surveys` (`id`) ON DELETE SET NULL,
  CONSTRAINT `estimates_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estimates`
--

LOCK TABLES `estimates` WRITE;
/*!40000 ALTER TABLE `estimates` DISABLE KEYS */;
/*!40000 ALTER TABLE `estimates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `final_invoice_items`
--

DROP TABLE IF EXISTS `final_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `final_invoice_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `final_invoice_id` int(10) unsigned NOT NULL,
  `item_type` enum('material','labor','additional','revision') NOT NULL,
  `item_name` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(50) DEFAULT NULL,
  `unit_price` bigint(20) unsigned NOT NULL DEFAULT 0,
  `subtotal` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `final_invoice_id` (`final_invoice_id`),
  CONSTRAINT `final_invoice_items_ibfk_1` FOREIGN KEY (`final_invoice_id`) REFERENCES `final_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `final_invoice_items`
--

LOCK TABLES `final_invoice_items` WRITE;
/*!40000 ALTER TABLE `final_invoice_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `final_invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `final_invoices`
--

DROP TABLE IF EXISTS `final_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `final_invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_code` varchar(30) NOT NULL,
  `booking_id` int(10) unsigned NOT NULL,
  `estimate_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `subtotal_estimate` bigint(20) unsigned NOT NULL DEFAULT 0,
  `revision_total` bigint(20) unsigned NOT NULL DEFAULT 0,
  `final_total` bigint(20) unsigned NOT NULL DEFAULT 0,
  `work_days_actual` int(10) unsigned NOT NULL DEFAULT 1,
  `worker_count_actual` int(10) unsigned NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `payment_status` enum('Belum Dibayar','Lunas') NOT NULL DEFAULT 'Belum Dibayar',
  `status` enum('Draft','Dikirim','Lunas') NOT NULL DEFAULT 'Draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_code` (`invoice_code`),
  KEY `booking_id` (`booking_id`),
  KEY `estimate_id` (`estimate_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `final_invoices_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `final_invoices_ibfk_2` FOREIGN KEY (`estimate_id`) REFERENCES `estimates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `final_invoices_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `final_invoices`
--

LOCK TABLES `final_invoices` WRITE;
/*!40000 ALTER TABLE `final_invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `final_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `labor_rates`
--

DROP TABLE IF EXISTS `labor_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `labor_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `daily_rate` bigint(20) unsigned NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `labor_rates`
--

LOCK TABLES `labor_rates` WRITE;
/*!40000 ALTER TABLE `labor_rates` DISABLE KEYS */;
INSERT INTO `labor_rates` VALUES
(1,'Tukang taman umum','Tenaga kerja lapangan untuk pemasangan dan perapian taman.',130000,'active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(2,'Mandor lapangan','Koordinator pekerjaan lapangan dan quality check.',180000,'active','2026-07-27 17:39:37','2026-08-01 16:05:42');
/*!40000 ALTER TABLE `labor_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `materials`
--

DROP TABLE IF EXISTS `materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `materials` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `material_code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `unit` varchar(30) NOT NULL,
  `price_per_unit` bigint(20) unsigned NOT NULL DEFAULT 0,
  `default_qty_per_10m` decimal(12,2) NOT NULL DEFAULT 0.00,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_code` (`material_code`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `materials`
--

LOCK TABLES `materials` WRITE;
/*!40000 ALTER TABLE `materials` DISABLE KEYS */;
INSERT INTO `materials` VALUES
(1,'MAT-2026-001','Love grass','pb',7500,341.00,'assets/img/materials/material-tanaman-love-grass.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(2,'MAT-2026-002','Mawar merah','pb',22000,18.00,'assets/img/materials/material-tanaman-mawar-merah.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(3,'MAT-2026-003','Nusa indah T 1m','tan',175000,3.00,'assets/img/materials/material-tanaman-nusa-indah-t-m.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(4,'MAT-2026-004','Pacing pentol T.30','pb',6000,700.00,'assets/img/materials/material-tanaman-pacing-pentol-t.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(5,'MAT-2026-005','Pandan Kuning','pb',5000,450.00,'assets/img/materials/material-tanaman-pandna-kuning.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(6,'MAT-2026-006','Pasir abu','m3',420000,2.00,'assets/img/materials/material-tanaman-pasir-abu.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(7,'MAT-2026-007','Pinus','tan',600000,9.00,'assets/img/materials/material-tanaman-pinus.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(8,'MAT-2026-008','plamboyan merah','tan',650000,4.00,'assets/img/materials/material-tanaman-plamboyan-merah.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(9,'MAT-2026-009','Plumbago','pb',5500,36.00,'assets/img/materials/material-tanaman-plumbago.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(10,'MAT-2026-010','Pucuk merah T.40cm','phn',13500,152.00,'assets/img/materials/material-tanaman-pucuk-merah-t-cm.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(11,'MAT-2026-011','Pupuk kandang','krg',15000,30.00,'assets/img/materials/material-tanaman-pupuk-kandang.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(12,'MAT-2026-012','Pupuk organik','kg',25000,2.00,'assets/img/materials/material-pupuk-organik.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(13,'MAT-2026-013','red tipping','pb',8000,310.00,'assets/img/materials/material-tanaman-red-tipping.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(14,'MAT-2026-014','Rombusa Putih','pb',5000,300.00,'assets/img/materials/material-tanaman-rombusa-putih.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(15,'MAT-2026-015','Loropetalum','pb',7500,228.00,'assets/img/materials/material-tanaman-loropetalum.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(16,'MAT-2026-016','Ruellia Ungu','pb',4000,540.00,'assets/img/materials/material-tanaman-ruellia-ungu.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(17,'MAT-2026-017','Rumput gajah biasa','m2',22500,150.00,'assets/img/materials/material-tanaman-rumput-gajah-biasa.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(18,'MAT-2026-018','Rumput gajah mini','m2',22500,1755.00,'assets/img/materials/material-rumput-gajah-mini.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(19,'MAT-2026-019','Sansivera runcing','pb',10000,37.00,'assets/img/materials/material-tanaman-sansivera-runcing.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(20,'MAT-2026-020','Sawo kecik','tan',475000,13.00,'assets/img/materials/material-tanaman-sawo-kecik.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(21,'MAT-2026-021','Sensivera','pb',12000,245.00,NULL,'active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(22,'MAT-2026-022','Sirih gading','pb',8500,68.00,'assets/img/materials/material-tanaman-sirih-gading.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(23,'MAT-2026-023','Soka jambon','pb',6000,17.00,'assets/img/materials/material-tanaman-soka-jambon.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(24,'MAT-2026-024','Soka singapur','pb',6000,64.00,'assets/img/materials/material-tanaman-soka-singapur.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(25,'MAT-2026-025','Soka super ball','pb',6000,96.00,'assets/img/materials/material-tanaman-soka-super-ball.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(26,'MAT-2026-026','Sutra bombay lokal','pb',5500,144.00,'assets/img/materials/material-tanaman-sutra-bombay-lokal.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(27,'MAT-2026-027','Tabebuya lokal','tan',400000,20.00,NULL,'active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(28,'MAT-2026-028','Taiwan beauty','pb',5000,72.00,'assets/img/materials/material-tanaman-taiwan-beauty.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(29,'MAT-2026-029','Tan lili brazil','pb',5000,440.00,'assets/img/materials/material-tanaman-tan-lili-brazil.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(30,'MAT-2026-030','Tanah taman','karung',100000,2.00,'assets/img/materials/material-tanah-taman.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(31,'MAT-2026-031','Tanaman hias kecil','pot',45000,6.00,'assets/img/materials/material-tanaman-hias-kecil.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42');
/*!40000 ALTER TABLE `materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `role_target` enum('customer','pekerja_lapangan','staff','boss') DEFAULT NULL,
  `title` varchar(180) NOT NULL,
  `message` text DEFAULT NULL,
  `reference_type` varchar(80) DEFAULT NULL,
  `reference_id` int(10) unsigned DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `payment_type` enum('Survey Fee','Invoice Final') NOT NULL,
  `amount` bigint(20) unsigned NOT NULL DEFAULT 0,
  `payment_status` enum('Belum Dibayar','Lunas') NOT NULL DEFAULT 'Belum Dibayar',
  `marked_by` int(10) unsigned DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `note` text DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `marked_by` (`marked_by`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `final_invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_id` (`booking_id`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedules`
--

DROP TABLE IF EXISTS `schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `schedule_code` varchar(30) NOT NULL,
  `booking_id` int(10) unsigned NOT NULL,
  `pekerja_lapangan_id` int(10) unsigned NOT NULL,
  `schedule_type` enum('Survey','Pengerjaan','Follow Up','Revisi') NOT NULL DEFAULT 'Survey',
  `schedule_date` date NOT NULL,
  `schedule_time` time NOT NULL,
  `location` text DEFAULT NULL,
  `status` enum('Terjadwal','Berlangsung','Selesai','Dijadwalkan Ulang','Dibatalkan') NOT NULL DEFAULT 'Terjadwal',
  `notes` text DEFAULT NULL,
  `reschedule_reason` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `schedule_code` (`schedule_code`),
  KEY `booking_id` (`booking_id`),
  KEY `created_by` (`created_by`),
  KEY `pekerja_lapangan_id` (`pekerja_lapangan_id`),
  CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`pekerja_lapangan_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `schedules_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedules`
--

LOCK TABLES `schedules` WRITE;
/*!40000 ALTER TABLE `schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_categories`
--

DROP TABLE IF EXISTS `service_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `slug` varchar(170) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_categories`
--

LOCK TABLES `service_categories` WRITE;
/*!40000 ALTER TABLE `service_categories` DISABLE KEYS */;
INSERT INTO `service_categories` VALUES
(1,'Pembuatan Taman','pembuatan-taman','Layanan pembuatan taman baru untuk rumah, kantor, dan area komersial.',NULL,'active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(2,'Perawatan Taman','perawatan-taman','Perawatan rutin agar taman tetap rapi, sehat, dan bersih.',NULL,'active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(3,'Renovasi Taman','renovasi-taman','Perbaikan dan peremajaan taman lama.',NULL,'active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(4,'Desain taman','desain-taman','Layanan untuk mendesain taman yang diinginkan client',NULL,'active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(5,'Persiapan lahan','persiapan-lahan','Layanan Persiapan lahan ABI Taman.',NULL,'active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(6,'Penanaman','penanaman','Layanan Penanaman ABI Taman.',NULL,'active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(7,'Pemasangan dekorasi taman','pemasangan-dekorasi-taman','Layanan Pemasangan dekorasi taman ABI Taman.',NULL,'active','2026-07-27 17:39:37','2026-08-01 16:05:42');
/*!40000 ALTER TABLE `service_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned DEFAULT NULL,
  `service_code` varchar(30) NOT NULL,
  `name` varchar(180) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `short_description` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `base_price_per_10m` bigint(20) unsigned NOT NULL DEFAULT 0,
  `base_duration_days_per_10m` int(10) unsigned NOT NULL DEFAULT 1,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_code` (`service_code`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `services_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES
(1,1,'SRV-2026-001','Taman Minimalis','taman-minimalis','Pembuatan taman minimalis untuk rumah modern.','Pekerjaan mencakup penataan area, pemasangan media tanam, rumput, tanaman hias, batu dekoratif, dan finishing sesuai hasil survey.',0,1,'assets/img/services/taman-minimalis.svg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(2,1,'SRV-2026-002','Taman Kering','taman-kering','Taman rendah perawatan dengan batu dan tanaman tahan panas.','Cocok untuk area depan rumah, teras, dan area yang butuh perawatan minim.',0,1,'assets/img/services/layanan-taman-kering.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(3,2,'SRV-2026-003','Perawatan Taman Bulanan','perawatan-taman-bulanan','Perawatan berkala untuk taman rumah atau kantor.','Pekerjaan dapat mencakup pemangkasan, pembersihan, pemupukan, dan perapian taman.',0,1,'assets/img/services/20260727205613-917e7c29c0d2.jpeg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(4,3,'SRV-2026-004','Renovasi Taman Rumah','renovasi-taman-rumah','Perbaikan taman lama agar terlihat baru dan sehat.','Pekerjaan mencakup pembongkaran ringan, penggantian tanaman, perbaikan media tanam, dan penataan ulang.',0,2,NULL,'active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(5,5,'SRV-2026-005','Perapihan, Pembersihan dan pengolahan tanah','perapihan-pembersihan-dan-pengolahan-tanah','Persiapan lahan agar bersih, rapi, dan siap ditanami.','Mencakup pembongkaran ringan, penggantian tanaman mati, perbaikan media tanam, penataan ulang desain, serta penambahan elemen dekoratif bila diperlukan.',0,1,NULL,'active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(6,6,'SRV-2026-006','Penanaman Tanaman','penanaman-tanaman','Jasa penanaman berbagai jenis tanaman hias maupun pohon.','Pekerjaan meliputi pemilihan posisi tanaman, penanaman, pemberian pupuk awal, penyiraman, dan penataan agar sesuai konsep taman.',0,1,'assets/img/services/20260727211820-5070ae8305aa.webp','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(7,7,'SRV-2026-007','Pemasangan Dekorasi Taman','pemasangan-dekorasi-taman','Menambah nilai estetika taman dengan berbagai aksesori.','Meliputi pemasangan batu alam, stepping stone, lampu taman, pot, gazebo kecil, air mancur, kolam mini, dan ornamen lainnya.',0,1,NULL,'active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(8,1,'SRV-2026-008','Taman Tropis','taman-tropis','Taman dengan nuansa hijau, rindang, dan alami.','Menggunakan tanaman tropis seperti palem, pisang kipas, heliconia, philodendron, monstera, dan rumput sehingga menciptakan suasana sejuk.',0,2,'assets/img/services/layanan-taman-tropis.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(9,1,'SRV-2026-009','Taman basah','taman-basah','Taman dengan elemen air sebagai daya tarik utama.','Mencakup pembangunan kolam ikan, air mancur, waterfall, sistem sirkulasi air, tanaman air, dan pencahayaan taman.',0,2,'assets/img/services/layanan-taman-basah.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42'),
(10,1,'SRV-2026-010','Taman vertikal','taman-vertikal','Solusi taman untuk lahan sempit menggunakan dinding.','Meliputi pemasangan rangka, panel tanam, sistem irigasi, media tanam, dan tanaman hias sehingga dinding menjadi lebih hijau.',0,2,'assets/img/services/layanan-taman-vertikal.jpg','active','2026-07-27 17:39:37','2026-08-01 16:05:42');
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES
(1,'app_name','ABI Taman Service','2026-07-27 17:39:37','2026-07-27 17:39:37'),
(2,'company_name','ABI Taman','2026-07-27 17:39:37','2026-07-27 17:39:37'),
(3,'company_email','hello@abitaman.id','2026-07-27 17:39:37','2026-07-27 17:39:37'),
(4,'company_phone','081290002211','2026-07-27 17:39:37','2026-07-27 17:39:37'),
(5,'company_address','Jl. Mawar Raya No. 10, Tangerang Selatan','2026-07-27 17:39:37','2026-07-27 17:39:37'),
(6,'survey_fee_amount','100000','2026-07-27 17:39:37','2026-07-27 17:39:37');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `survey_photos`
--

DROP TABLE IF EXISTS `survey_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `survey_photos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `survey_id` int(10) unsigned DEFAULT NULL,
  `booking_id` int(10) unsigned NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `photo_type` enum('before','progress','after') NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `survey_id` (`survey_id`),
  KEY `booking_id` (`booking_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `survey_photos_ibfk_1` FOREIGN KEY (`survey_id`) REFERENCES `surveys` (`id`) ON DELETE CASCADE,
  CONSTRAINT `survey_photos_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `survey_photos_ibfk_3` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `survey_photos`
--

LOCK TABLES `survey_photos` WRITE;
/*!40000 ALTER TABLE `survey_photos` DISABLE KEYS */;
/*!40000 ALTER TABLE `survey_photos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `surveys`
--

DROP TABLE IF EXISTS `surveys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `surveys` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `pekerja_lapangan_id` int(10) unsigned NOT NULL,
  `actual_area` decimal(12,2) NOT NULL,
  `location_condition` text NOT NULL,
  `access_condition` text NOT NULL,
  `obstacle_notes` text DEFAULT NULL,
  `estimated_work_days` int(10) unsigned NOT NULL DEFAULT 1,
  `estimated_worker_count` int(10) unsigned NOT NULL DEFAULT 1,
  `survey_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `pekerja_lapangan_id` (`pekerja_lapangan_id`),
  CONSTRAINT `surveys_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `surveys_ibfk_2` FOREIGN KEY (`pekerja_lapangan_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `surveys`
--

LOCK TABLES `surveys` WRITE;
/*!40000 ALTER TABLE `surveys` DISABLE KEYS */;
/*!40000 ALTER TABLE `surveys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('customer','pekerja_lapangan','staff','boss') NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'Udin','udin@abitaman.id','$2y$12$OIPVrO9xuHd2xQ3NAoDHVuXdeznEOZdj1g8ECndweL5Q3PRYFbAlW','staff','0812-9000-2211','Jl. Mawar Raya, Tangerang Selatan',NULL,'active',NULL,'2026-05-09 18:24:17','2026-08-01 16:05:42'),
(2,'Riski','riski@abitaman.id','$2y$12$iVrIclWl1Q7U/MqR35IA4O7zzKBzBez0gVTrOuhmR.PQ2enn5jRiC','pekerja_lapangan','0813-2200-0011','Pamulang, Tangerang Selatan',NULL,'active',NULL,'2026-05-09 18:24:17','2026-08-01 16:05:42'),
(3,'Abi','abi@abitaman.id','$2y$12$97UgtyT/t3KJSbAWRZ2Uo.xBmXHYHFhTJqd0bTp.FWPPOF4uHcLbW','boss','0821-4400-5566','BSD, Tangerang Selatan',NULL,'active',NULL,'2026-05-09 18:24:18','2026-08-01 16:05:42'),
(4,'Yoga','yoga@email.com','$2y$12$/Z0vx./Hi.sKXHkUbPn/3.pohGv9eNWw4nBbcc58.wjFQsV4TRkI2','customer','0812-3311-7806','Ciputat, Tangerang Selatan',NULL,'active',NULL,'2026-05-09 18:24:18','2026-08-01 16:05:42');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_progress`
--

DROP TABLE IF EXISTS `work_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_progress` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `pekerja_lapangan_id` int(10) unsigned NOT NULL,
  `progress_percent` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `title` varchar(180) NOT NULL,
  `note` text DEFAULT NULL,
  `obstacle_note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `pekerja_lapangan_id` (`pekerja_lapangan_id`),
  CONSTRAINT `work_progress_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `work_progress_ibfk_2` FOREIGN KEY (`pekerja_lapangan_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_progress`
--

LOCK TABLES `work_progress` WRITE;
/*!40000 ALTER TABLE `work_progress` DISABLE KEYS */;
/*!40000 ALTER TABLE `work_progress` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'abi_taman'
--

--
-- Dumping routines for database 'abi_taman'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-01 16:05:57
