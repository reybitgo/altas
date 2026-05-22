-- MySQL dump 10.13  Distrib 8.0.33, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: u938213108_altas_db
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `commissions`
--

DROP TABLE IF EXISTS `commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `type` enum('pairing','direct_referral','indirect_referral') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `source_user_id` int unsigned DEFAULT NULL,
  `level` tinyint unsigned DEFAULT NULL,
  `pairs_count` tinyint unsigned DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('credited','flushed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'credited',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_type` (`user_id`,`type`,`created_at`),
  KEY `idx_source` (`source_user_id`),
  KEY `idx_status` (`status`,`created_at`),
  CONSTRAINT `commissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `commissions_ibfk_2` FOREIGN KEY (`source_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commissions`
--

/*!40000 ALTER TABLE `commissions` DISABLE KEYS */;
INSERT INTO `commissions` VALUES (1,1,'direct_referral',500.00,2,NULL,NULL,'Direct referral bonus','credited','2026-04-19 12:24:29'),(2,1,'indirect_referral',300.00,2,1,NULL,'Unilevel Level 1 Bonus','credited','2026-04-19 12:24:29'),(3,2,'direct_referral',500.00,3,NULL,NULL,'Direct referral bonus','credited','2026-04-19 12:29:32'),(4,2,'indirect_referral',300.00,3,1,NULL,'Unilevel Level 1 Bonus','credited','2026-04-19 12:29:32'),(5,1,'indirect_referral',200.00,3,2,NULL,'Unilevel Level 2 Bonus','credited','2026-04-19 12:29:32'),(6,2,'pairing',2000.00,4,NULL,1,'1 pair(s) × ₱2,000.00','credited','2026-04-19 12:32:32'),(7,2,'direct_referral',500.00,4,NULL,NULL,'Direct referral bonus','credited','2026-04-19 12:32:32'),(8,2,'indirect_referral',300.00,4,1,NULL,'Unilevel Level 1 Bonus','credited','2026-04-19 12:32:32'),(9,1,'indirect_referral',200.00,4,2,NULL,'Unilevel Level 2 Bonus','credited','2026-04-19 12:32:32'),(10,3,'direct_referral',500.00,5,NULL,NULL,'Direct referral bonus','credited','2026-04-19 12:37:57'),(11,3,'indirect_referral',300.00,5,1,NULL,'Unilevel Level 1 Bonus','credited','2026-04-19 12:37:57'),(12,2,'indirect_referral',200.00,5,2,NULL,'Unilevel Level 2 Bonus','credited','2026-04-19 12:37:57'),(13,1,'indirect_referral',150.00,5,3,NULL,'Unilevel Level 3 Bonus','credited','2026-04-19 12:37:57'),(14,3,'pairing',2000.00,6,NULL,1,'1 pair(s) × ₱2,000.00','credited','2026-04-19 12:41:07'),(15,3,'direct_referral',500.00,6,NULL,NULL,'Direct referral bonus','credited','2026-04-19 12:41:07'),(16,3,'indirect_referral',300.00,6,1,NULL,'Unilevel Level 1 Bonus','credited','2026-04-19 12:41:07'),(17,2,'indirect_referral',200.00,6,2,NULL,'Unilevel Level 2 Bonus','credited','2026-04-19 12:41:07'),(18,1,'indirect_referral',150.00,6,3,NULL,'Unilevel Level 3 Bonus','credited','2026-04-19 12:41:07'),(19,2,'pairing',2000.00,7,NULL,1,'1 pair(s) × ₱2,000.00','credited','2026-04-19 12:45:09'),(20,4,'direct_referral',500.00,7,NULL,NULL,'Direct referral bonus','credited','2026-04-19 12:45:09'),(21,4,'indirect_referral',300.00,7,1,NULL,'Unilevel Level 1 Bonus','credited','2026-04-19 12:45:09'),(22,2,'indirect_referral',200.00,7,2,NULL,'Unilevel Level 2 Bonus','credited','2026-04-19 12:45:09'),(23,1,'indirect_referral',150.00,7,3,NULL,'Unilevel Level 3 Bonus','credited','2026-04-19 12:45:09'),(24,4,'pairing',2000.00,8,NULL,1,'1 pair(s) × ₱2,000.00','credited','2026-04-19 12:47:01'),(25,2,'pairing',2000.00,8,NULL,1,'1 pair(s) × ₱2,000.00','credited','2026-04-19 12:47:01'),(26,4,'direct_referral',500.00,8,NULL,NULL,'Direct referral bonus','credited','2026-04-19 12:47:01'),(27,4,'indirect_referral',300.00,8,1,NULL,'Unilevel Level 1 Bonus','credited','2026-04-19 12:47:01'),(28,2,'indirect_referral',200.00,8,2,NULL,'Unilevel Level 2 Bonus','credited','2026-04-19 12:47:01'),(29,1,'indirect_referral',150.00,8,3,NULL,'Unilevel Level 3 Bonus','credited','2026-04-19 12:47:01');
/*!40000 ALTER TABLE `commissions` ENABLE KEYS */;

--
-- Table structure for table `ewallet_ledger`
--

DROP TABLE IF EXISTS `ewallet_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ewallet_ledger` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `type` enum('credit','debit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reference_id` int unsigned DEFAULT NULL,
  `ref_type` enum('commission','payout') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `balance_after` decimal(14,2) NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`,`created_at`),
  CONSTRAINT `ewallet_ledger_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ewallet_ledger`
--

/*!40000 ALTER TABLE `ewallet_ledger` DISABLE KEYS */;
INSERT INTO `ewallet_ledger` VALUES (1,1,'credit',500.00,1,'commission',500.00,'Direct referral bonus','2026-04-19 12:24:29'),(2,1,'credit',300.00,2,'commission',800.00,'Unilevel Level 1 Bonus','2026-04-19 12:24:29'),(3,2,'credit',500.00,3,'commission',500.00,'Direct referral bonus','2026-04-19 12:29:32'),(4,2,'credit',300.00,4,'commission',800.00,'Unilevel Level 1 Bonus','2026-04-19 12:29:32'),(5,1,'credit',200.00,5,'commission',1000.00,'Unilevel Level 2 Bonus','2026-04-19 12:29:32'),(6,2,'credit',2000.00,6,'commission',2800.00,'Pairing bonus — 1 pair(s)','2026-04-19 12:32:32'),(7,2,'credit',500.00,7,'commission',3300.00,'Direct referral bonus','2026-04-19 12:32:32'),(8,2,'credit',300.00,8,'commission',3600.00,'Unilevel Level 1 Bonus','2026-04-19 12:32:32'),(9,1,'credit',200.00,9,'commission',1200.00,'Unilevel Level 2 Bonus','2026-04-19 12:32:32'),(10,3,'credit',500.00,10,'commission',500.00,'Direct referral bonus','2026-04-19 12:37:57'),(11,3,'credit',300.00,11,'commission',800.00,'Unilevel Level 1 Bonus','2026-04-19 12:37:57'),(12,2,'credit',200.00,12,'commission',3800.00,'Unilevel Level 2 Bonus','2026-04-19 12:37:57'),(13,1,'credit',150.00,13,'commission',1350.00,'Unilevel Level 3 Bonus','2026-04-19 12:37:57'),(14,3,'credit',2000.00,14,'commission',2800.00,'Pairing bonus — 1 pair(s)','2026-04-19 12:41:07'),(15,3,'credit',500.00,15,'commission',3300.00,'Direct referral bonus','2026-04-19 12:41:07'),(16,3,'credit',300.00,16,'commission',3600.00,'Unilevel Level 1 Bonus','2026-04-19 12:41:07'),(17,2,'credit',200.00,17,'commission',4000.00,'Unilevel Level 2 Bonus','2026-04-19 12:41:07'),(18,1,'credit',150.00,18,'commission',1500.00,'Unilevel Level 3 Bonus','2026-04-19 12:41:07'),(19,2,'credit',2000.00,19,'commission',6000.00,'Pairing bonus — 1 pair(s)','2026-04-19 12:45:09'),(20,4,'credit',500.00,20,'commission',500.00,'Direct referral bonus','2026-04-19 12:45:09'),(21,4,'credit',300.00,21,'commission',800.00,'Unilevel Level 1 Bonus','2026-04-19 12:45:09'),(22,2,'credit',200.00,22,'commission',6200.00,'Unilevel Level 2 Bonus','2026-04-19 12:45:09'),(23,1,'credit',150.00,23,'commission',1650.00,'Unilevel Level 3 Bonus','2026-04-19 12:45:09'),(24,4,'credit',2000.00,24,'commission',2800.00,'Pairing bonus — 1 pair(s)','2026-04-19 12:47:01'),(25,2,'credit',2000.00,25,'commission',8200.00,'Pairing bonus — 1 pair(s)','2026-04-19 12:47:01'),(26,4,'credit',500.00,26,'commission',3300.00,'Direct referral bonus','2026-04-19 12:47:01'),(27,4,'credit',300.00,27,'commission',3600.00,'Unilevel Level 1 Bonus','2026-04-19 12:47:01'),(28,2,'credit',200.00,28,'commission',8400.00,'Unilevel Level 2 Bonus','2026-04-19 12:47:01'),(29,1,'credit',150.00,29,'commission',1800.00,'Unilevel Level 3 Bonus','2026-04-19 12:47:01'),(30,2,'debit',500.00,1,'payout',7900.00,'Payout via GCash 09171234567','2026-04-20 07:44:35'),(31,2,'debit',500.00,3,'payout',7400.00,'Payout via MAYA 09281234567','2026-04-20 09:43:05'),(32,2,'debit',500.00,4,'payout',6900.00,'Payout via USDT TN8dqFnGBcP8sYcKEkMvHrwJqZ6kLmX9pQ','2026-04-21 11:07:53'),(33,2,'debit',500.00,5,'payout',6400.00,'Payout via GCASH 09171234567','2026-04-22 07:56:39'),(34,2,'debit',500.00,6,'payout',5900.00,'Payout via USDT TN8dqFnGBcP8sYcKEkMvHrwJqZ6kLmX9pQ','2026-04-22 22:24:05');
/*!40000 ALTER TABLE `ewallet_ledger` ENABLE KEYS */;

--
-- Table structure for table `package_indirect_levels`
--

DROP TABLE IF EXISTS `package_indirect_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `package_indirect_levels` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `package_id` int unsigned NOT NULL,
  `level` tinyint unsigned NOT NULL,
  `bonus` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pkg_level` (`package_id`,`level`),
  CONSTRAINT `package_indirect_levels_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `package_indirect_levels`
--

/*!40000 ALTER TABLE `package_indirect_levels` DISABLE KEYS */;
INSERT INTO `package_indirect_levels` VALUES (1,1,1,300.00),(2,1,2,200.00),(3,1,3,150.00),(4,1,4,100.00),(5,1,5,100.00),(6,1,6,50.00),(7,1,7,50.00),(8,1,8,50.00),(9,1,9,50.00),(10,1,10,50.00);
/*!40000 ALTER TABLE `package_indirect_levels` ENABLE KEYS */;

--
-- Table structure for table `packages`
--

DROP TABLE IF EXISTS `packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `packages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entry_fee` decimal(12,2) NOT NULL,
  `pairing_bonus` decimal(12,2) NOT NULL,
  `daily_pair_cap` tinyint unsigned NOT NULL DEFAULT '3',
  `direct_ref_bonus` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `packages`
--

/*!40000 ALTER TABLE `packages` DISABLE KEYS */;
INSERT INTO `packages` VALUES (1,'Starter',10000.00,2000.00,3,500.00,'active','2026-04-19 12:21:05');
/*!40000 ALTER TABLE `packages` ENABLE KEYS */;

--
-- Table structure for table `payout_requests`
--

DROP TABLE IF EXISTS `payout_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payout_requests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payout_method` enum('gcash','maya','usdt') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gcash',
  `payout_account` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `service_fee_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `service_fee_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `usdt_rate` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `usdt_gas_fee` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `usdt_amount` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `status` enum('pending','approved','rejected','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `admin_note` text COLLATE utf8mb4_unicode_ci,
  `processed_by` int unsigned DEFAULT NULL,
  `requested_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `processed_by` (`processed_by`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_status` (`status`,`requested_at`),
  CONSTRAINT `payout_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `payout_requests_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payout_requests`
--

/*!40000 ALTER TABLE `payout_requests` DISABLE KEYS */;
INSERT INTO `payout_requests` VALUES (1,2,500.00,'gcash','',0.00,0.00,0.0000,0.0000,0.0000,'completed','',1,'2026-04-20 07:33:37','2026-04-20 07:44:35'),(2,2,500.00,'gcash','09171234567',0.00,0.00,0.0000,0.0000,0.0000,'rejected','Test rejection',1,'2026-04-20 08:13:55','2026-04-20 08:14:53'),(3,2,500.00,'maya','09281234567',0.00,0.00,0.0000,0.0000,0.0000,'completed','Sent via Maya',1,'2026-04-20 09:30:17','2026-04-20 09:43:05'),(4,2,500.00,'usdt','TN8dqFnGBcP8sYcKEkMvHrwJqZ6kLmX9pQ',0.00,0.00,0.0000,0.0000,0.0000,'completed','',1,'2026-04-20 09:50:29','2026-04-21 11:07:53'),(5,2,500.00,'gcash','09171234567',0.00,0.00,60.0900,0.0000,0.0000,'completed','Sent via GCash',1,'2026-04-22 07:30:20','2026-04-22 07:56:39'),(6,2,500.00,'usdt','TN8dqFnGBcP8sYcKEkMvHrwJqZ6kLmX9pQ',5.00,25.00,60.2000,0.7801,7.1103,'completed','USDT transferred on chain',1,'2026-04-22 20:20:03','2026-04-22 22:24:05'),(7,2,500.00,'gcash','09990000000',5.00,25.00,60.5800,0.0000,0.0000,'pending',NULL,NULL,'2026-04-23 21:03:51',NULL);
/*!40000 ALTER TABLE `payout_requests` ENABLE KEYS */;

--
-- Table structure for table `reg_codes`
--

DROP TABLE IF EXISTS `reg_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reg_codes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `package_id` int unsigned NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `status` enum('unused','used','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unused',
  `used_by` int unsigned DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `package_id` (`package_id`),
  KEY `used_by` (`used_by`),
  KEY `created_by` (`created_by`),
  KEY `idx_status` (`status`),
  CONSTRAINT `reg_codes_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`),
  CONSTRAINT `reg_codes_ibfk_2` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reg_codes_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reg_codes`
--

/*!40000 ALTER TABLE `reg_codes` DISABLE KEYS */;
INSERT INTO `reg_codes` VALUES (1,'W4ZX-GPU3-46VW',1,10000.00,'used',2,1,'2026-04-19 12:24:29',NULL,'2026-04-19 12:21:05'),(2,'J9CQ-3CZA-VLQK',1,10000.00,'used',3,1,'2026-04-19 12:29:32',NULL,'2026-04-19 12:21:05'),(3,'L3ZD-WNJC-9BQF',1,10000.00,'used',4,1,'2026-04-19 12:32:32',NULL,'2026-04-19 12:21:05'),(4,'RXR7-NERP-J5X3',1,10000.00,'used',5,1,'2026-04-19 12:37:57',NULL,'2026-04-19 12:21:05'),(5,'58E6-YN97-YHWE',1,10000.00,'used',6,1,'2026-04-19 12:41:07',NULL,'2026-04-19 12:21:05'),(6,'QV2V-B2EC-MUT8',1,10000.00,'used',7,1,'2026-04-19 12:45:09',NULL,'2026-04-19 12:43:42'),(7,'2AN2-384K-2SGC',1,10000.00,'used',8,1,'2026-04-19 12:47:01',NULL,'2026-04-19 12:43:42'),(8,'YTQB-8UEW-ZPUD',1,10000.00,'unused',NULL,1,NULL,NULL,'2026-04-19 12:43:42'),(9,'UNAA-56SP-95PK',1,10000.00,'unused',NULL,1,NULL,NULL,'2026-04-19 12:43:42'),(10,'K8PN-KLNT-6AXZ',1,10000.00,'unused',NULL,1,NULL,NULL,'2026-04-19 12:43:42'),(11,'5CKV-KVDW-5GXE',1,10000.00,'unused',NULL,1,NULL,NULL,'2026-04-19 12:43:42'),(12,'Z944-8SC6-ENGA',1,10000.00,'unused',NULL,1,NULL,NULL,'2026-04-19 12:43:42'),(13,'7TYA-4C3X-5857',1,10000.00,'unused',NULL,1,NULL,NULL,'2026-04-19 12:43:42'),(14,'5Z8F-BWVU-7GB6',1,10000.00,'unused',NULL,1,NULL,NULL,'2026-04-19 12:43:42'),(15,'J5ZM-Z7RA-YPD7',1,10000.00,'unused',NULL,1,NULL,NULL,'2026-04-19 12:43:42');
/*!40000 ALTER TABLE `reg_codes` ENABLE KEYS */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `key_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('contact_email','support@mlm.local','2026-04-19 03:24:18'),('gcash_enabled','1','2026-04-23 21:02:10'),('last_reset','2026-04-20 11:08:40','2026-04-20 03:08:40'),('maintenance_mode','0','2026-04-19 03:24:18'),('maya_enabled','1','2026-04-23 21:02:10'),('min_payout','500','2026-04-19 03:24:18'),('service_fee_gcash','5','2026-04-23 17:25:17'),('service_fee_maya','5','2026-04-21 18:44:47'),('service_fee_usdt','5','2026-04-21 18:44:47'),('site_name','Altas Farm','2026-04-19 03:24:18'),('site_tagline','Build Your Network. Grow Your Income.','2026-04-19 03:24:18'),('usdt_gas_fee','0.7801','2026-04-22 19:52:08');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('member','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `package_id` int unsigned DEFAULT NULL,
  `reg_code_id` int unsigned DEFAULT NULL,
  `sponsor_id` int unsigned DEFAULT NULL,
  `binary_parent_id` int unsigned DEFAULT NULL,
  `binary_position` enum('left','right') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `left_count` int unsigned NOT NULL DEFAULT '0',
  `right_count` int unsigned NOT NULL DEFAULT '0',
  `pairs_paid` int unsigned NOT NULL DEFAULT '0',
  `pairs_flushed` int unsigned NOT NULL DEFAULT '0',
  `pairs_paid_today` int unsigned NOT NULL DEFAULT '0',
  `full_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gcash_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `maya_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usdt_address` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `photo` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ewallet_balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','suspended','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `package_id` (`package_id`),
  KEY `reg_code_id` (`reg_code_id`),
  KEY `idx_sponsor` (`sponsor_id`),
  KEY `idx_binary_parent` (`binary_parent_id`,`binary_position`),
  KEY `idx_role_status` (`role`,`status`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`sponsor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`binary_parent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_ibfk_3` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_ibfk_4` FOREIGN KEY (`reg_code_id`) REFERENCES `reg_codes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$12$h3j0mO9NbtMyLg6EsC4M6eGy6buk0zanOgPmFBIgaI8V5/CUbaYqq','admin',NULL,NULL,NULL,NULL,NULL,7,0,0,0,0,'System Administrator','admin@mlm.local','','','','TN8dqFnGBcP8sYcKEkMvHrwJqZ6kLmX9pQ','',NULL,1800.00,'active','2026-04-19 03:24:17','2026-05-20 23:48:46'),(2,'altas1','$2y$12$1pOri/qy4ZREWunaa5v2neimz8DMsXeuC8bkgnwRchSktFicnjPJq','member',1,1,1,1,'left',3,3,3,0,0,'','','','09171234567','','TN8dqFnGBcP8sYcKEkMvHrwJqZ6kLmX9pQ','',NULL,5900.00,'active','2026-04-19 12:24:29','2026-04-23 20:59:34'),(3,'altas2','$2y$12$1gJ9GMcYEeTOyfmQ1x.mVe5H5fWTshEHZcWVPakEb6n6PJmO912H.','member',1,2,2,2,'left',1,1,1,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,3600.00,'active','2026-04-19 12:29:32',NULL),(4,'altas3','$2y$12$qeokLFzXBiaHgcKBoAAY5.bBgzZ/66OtzsKvm/W2qcn3i3hWi2wdu','member',1,3,2,2,'right',1,1,1,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,3600.00,'active','2026-04-19 12:32:32',NULL),(5,'altas4','$2y$12$vwpteGgdAubDS/qa.Rdv3.I6x5bM0NYsw77dwflbwYZ6Qnluo8jX2','member',1,4,3,3,'left',0,0,0,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.00,'active','2026-04-19 12:37:57',NULL),(6,'altas5','$2y$12$zCWGknr7PuYeXCBMZpv5Wu7z2/ap2OvgfSH3YfWPFhOzUB5p8jtAu','member',1,5,3,3,'right',0,0,0,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.00,'active','2026-04-19 12:41:07',NULL),(7,'altas6','$2y$12$mQKkbdh0JtChjxuF6DFK7uQD.kUmpSJXqU5u7klkexKdFXXZvMwJW','member',1,6,4,4,'left',0,0,0,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.00,'active','2026-04-19 12:45:09',NULL),(8,'altas7','$2y$12$EJM9SS78XsX2jteGm6A6ne3wag6B9qLdN2ed.TQ.xefbtCol2zz4.','member',1,7,4,4,'right',0,0,0,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.00,'active','2026-04-19 12:47:01',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;

--
-- Dumping routines for database 'u938213108_altas_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-22  9:24:39
