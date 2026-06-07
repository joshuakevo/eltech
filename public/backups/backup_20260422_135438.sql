-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: eltech_finance
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

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
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_code` varchar(255) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `account_type` enum('asset','liability','equity','revenue','expense') NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accounts_account_code_unique` (`account_code`),
  KEY `accounts_parent_id_foreign` (`parent_id`),
  CONSTRAINT `accounts_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounts`
--

LOCK TABLES `accounts` WRITE;
/*!40000 ALTER TABLE `accounts` DISABLE KEYS */;
INSERT INTO `accounts` VALUES (1,'1000','Current Assets','asset',NULL,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(2,'1100','Loan Receivables','asset',NULL,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(3,'1200','Fixed Assets','asset',NULL,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(4,'1300','Other Assets','asset',NULL,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(5,'2000','Current Liabilities','liability',NULL,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(6,'2100','Long-Term Liabilities','liability',NULL,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(7,'3000','Equity','equity',NULL,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(8,'4000','Revenue','revenue',NULL,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(9,'5000','Expenses','expense',NULL,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(10,'1001','Cash on Hand','asset',1,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(11,'1002','Cash at Bank','asset',1,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(12,'1003','Mobile Money Wallet','asset',1,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(13,'1101','Loans Receivable — General','asset',2,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(14,'1102','Loans Receivable — Business','asset',2,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(15,'1103','Loans Receivable — Emergency','asset',2,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(16,'1201','Office Equipment','asset',3,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(17,'1202','Computers & IT Equipment','asset',3,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(18,'1203','Furniture & Fittings','asset',3,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(19,'1301','Prepaid Expenses','asset',4,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(20,'1302','Accrued Income','asset',4,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(21,'2001','Member Savings (General)','liability',5,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(22,'2002','Fixed Deposit Liabilities','liability',5,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(23,'2003','Interest Payable (FD)','liability',5,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(24,'2004','Accrued Expenses','liability',5,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(25,'2101','Borrowings','liability',6,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(26,'3001','Share Capital','equity',7,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(27,'3002','Retained Earnings','equity',7,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(28,'3003','Statutory Reserve Fund','equity',7,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(29,'4001','Interest Income — General Loans','revenue',8,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(30,'4002','Interest Income — Business Loans','revenue',8,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(31,'4003','Interest Income — Emergency Loans','revenue',8,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(32,'4004','Penalty Income','revenue',8,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(33,'4005','Processing Fees','revenue',8,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(34,'4006','Withdrawal Fee Income','revenue',8,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(35,'4007','Other Income','revenue',8,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(36,'5001','Salary Expense','expense',9,1,NULL,'2026-03-10 07:41:56','2026-03-13 06:51:56'),(37,'5002','Interest Expense — Fixed Deposits','expense',9,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(38,'5003','Staff Salaries','expense',9,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(39,'5004','Rent & Utilities','expense',9,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(40,'5005','Office Supplies','expense',9,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(41,'5006','Bad Debt Provision','expense',9,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(42,'5007','Depreciation','expense',9,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(43,'5008','Bank Charges','expense',9,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(44,'5009','Miscellaneous Expenses','expense',9,1,NULL,'2026-03-10 07:41:56','2026-03-10 07:41:56'),(45,'4008','Membership Fee Income','revenue',8,1,NULL,'2026-03-12 08:58:12','2026-03-12 08:58:12'),(46,'4009','Loan Management Fee Income','revenue',8,1,NULL,'2026-03-12 10:16:13','2026-03-12 10:16:13'),(47,'4010','Loan Insurance Fee Income','revenue',8,1,NULL,'2026-03-12 10:16:13','2026-03-12 10:16:13'),(48,'2010','Loan Insurance Payable','liability',5,1,NULL,'2026-03-13 06:02:01','2026-03-13 06:02:01'),(49,'4011','Loan Application Fee Income','revenue',8,1,NULL,'2026-03-13 06:16:55','2026-03-13 06:16:55'),(50,'2005','Group Member Savings','liability',5,1,'Liability for group member sub-balances','2026-04-07 12:28:50','2026-04-07 12:28:50'),(51,'5010','Interest Expense — Groups','expense',9,1,'Interest credited to group member balances','2026-04-07 12:28:50','2026-04-07 12:28:50');
/*!40000 ALTER TABLE `accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(30) NOT NULL,
  `module` varchar(60) DEFAULT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `method` varchar(10) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,NULL,'login_failed',NULL,'Failed login attempt for: kavinyia@gmail.com','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 08:52:52'),(2,1,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 08:54:37'),(3,1,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 08:54:37'),(4,1,'create','Clients','Created a new client','127.0.0.1','http://127.0.0.1:8000/clients','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:01:20'),(5,1,'create','Clients','Created a new client','127.0.0.1','http://127.0.0.1:8000/clients','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:01:20'),(6,1,'create','Clients','Created a new client','127.0.0.1','http://127.0.0.1:8000/clients','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:01:21'),(7,1,'create','Clients','Created a new client','127.0.0.1','http://127.0.0.1:8000/clients','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:01:21'),(8,1,'create','Clients','Created a new client','127.0.0.1','http://127.0.0.1:8000/clients','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:01:22'),(9,1,'create','Clients','Created a new client','127.0.0.1','http://127.0.0.1:8000/clients','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:05:05'),(10,1,'create','Savings','Opened savings account','127.0.0.1','http://127.0.0.1:8000/savings','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:05:31'),(11,1,'create','Savings','Opened savings account','127.0.0.1','http://127.0.0.1:8000/savings','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:05:32'),(12,1,'create','Savings','Opened savings account','127.0.0.1','http://127.0.0.1:8000/savings','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:05:32'),(13,1,'create','Savings','Opened savings account','127.0.0.1','http://127.0.0.1:8000/savings','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:05:33'),(14,1,'create','Savings','Opened savings account','127.0.0.1','http://127.0.0.1:8000/savings','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:05:33'),(15,1,'create','Savings','Opened savings account','127.0.0.1','http://127.0.0.1:8000/savings','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:05:34'),(16,1,'create','Savings','Opened savings account','127.0.0.1','http://127.0.0.1:8000/savings','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:05:34'),(17,1,'create','Savings','Opened savings account','127.0.0.1','http://127.0.0.1:8000/savings','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:05:35'),(18,1,'create','Savings','Opened savings account','127.0.0.1','http://127.0.0.1:8000/savings','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:09:35'),(19,1,'create','Loans','Created new loan','127.0.0.1','http://127.0.0.1:8000/loans','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:10:10'),(20,1,'delete','Loans','Deleted loan','127.0.0.1','http://127.0.0.1:8000/loans/1','DELETE','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:10:27'),(21,1,'create','Fixed Deposits','Created fixed deposit','127.0.0.1','http://127.0.0.1:8000/fixed-deposits','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:11:09'),(22,1,'delete','Clients','Deleted client','127.0.0.1','http://127.0.0.1:8000/clients/6','DELETE','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:16:57'),(23,1,'delete','Clients','Deleted client','127.0.0.1','http://127.0.0.1:8000/clients/5','DELETE','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:17:08'),(24,1,'delete','Clients','Deleted client','127.0.0.1','http://127.0.0.1:8000/clients/3','DELETE','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:17:15'),(25,1,'delete','Clients','Deleted client','127.0.0.1','http://127.0.0.1:8000/clients/4','DELETE','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:17:22'),(26,1,'delete','Clients','Deleted client','127.0.0.1','http://127.0.0.1:8000/clients/1','DELETE','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:17:29'),(27,1,'delete','Clients','Deleted client','127.0.0.1','http://127.0.0.1:8000/clients/2','DELETE','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:17:36'),(28,1,'update',NULL,'PUT /roles/2','127.0.0.1','http://127.0.0.1:8000/roles/2','PUT','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:18:05'),(29,1,'update',NULL,'PUT /roles/3','127.0.0.1','http://127.0.0.1:8000/roles/3','PUT','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 09:18:16'),(30,1,'create','Clients','Created a new client','127.0.0.1','http://127.0.0.1:8000/clients','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 10:32:06'),(31,1,'update',NULL,'PUT /groups/1','127.0.0.1','http://127.0.0.1:8000/groups/1','PUT','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 10:32:31'),(32,1,'update',NULL,'PUT /groups/1','127.0.0.1','http://127.0.0.1:8000/groups/1','PUT','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','2026-04-22 10:38:25');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `branches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `manager_name` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `branches_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES (1,'Ntinda','NK','Ntinda','0788176140','kavinyia@gmail.com','Deborah Ochieng',1,'2026-04-09 19:07:57','2026-04-09 19:07:57');
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `client_portal_users`
--

DROP TABLE IF EXISTS `client_portal_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_portal_users` (
  `user_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`client_id`),
  KEY `client_portal_users_client_id_foreign` (`client_id`),
  CONSTRAINT `client_portal_users_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `client_portal_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `client_portal_users`
--

LOCK TABLES `client_portal_users` WRITE;
/*!40000 ALTER TABLE `client_portal_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `client_portal_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `client_number` varchar(255) NOT NULL,
  `client_type` varchar(20) NOT NULL DEFAULT 'individual',
  `first_name` varchar(255) DEFAULT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `marital_status` enum('single','married','divorced','widowed') DEFAULT NULL,
  `nationality` varchar(255) DEFAULT NULL,
  `id_number` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `alt_phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `district` varchar(255) DEFAULT NULL,
  `village` varchar(255) DEFAULT NULL,
  `postal_address` varchar(255) DEFAULT NULL,
  `employment_status` enum('employed','self_employed','business_owner','farmer','student','unemployed') DEFAULT NULL,
  `purpose_of_joining` varchar(255) DEFAULT NULL,
  `expected_monthly_savings` decimal(12,2) DEFAULT NULL,
  `loan_interest` tinyint(1) DEFAULT NULL,
  `membership_fee` decimal(15,2) NOT NULL DEFAULT 50000.00,
  `membership_fee_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `membership_fee_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `next_of_kin_name` varchar(255) DEFAULT NULL,
  `next_of_kin_relationship` varchar(255) DEFAULT NULL,
  `next_of_kin_phone` varchar(255) DEFAULT NULL,
  `next_of_kin_address` varchar(255) DEFAULT NULL,
  `preferred_communication` enum('sms','email','whatsapp','phone_call') DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `status` enum('active','inactive','blacklisted') NOT NULL DEFAULT 'active',
  `joining_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clients_client_number_unique` (`client_number`),
  KEY `clients_branch_id_foreign` (`branch_id`),
  CONSTRAINT `clients_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (1,NULL,'CLT-000001','individual','Kevin',NULL,'Avinyia',NULL,NULL,NULL,NULL,NULL,NULL,'Kevin Avinyia',NULL,NULL,'avinyiak@gmail.com',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,50000.00,0.00,'unpaid',NULL,NULL,NULL,NULL,NULL,1,'active','2026-04-22','2026-04-22 09:01:20','2026-04-22 09:17:29','2026-04-22 09:17:29'),(2,NULL,'CLT-000002','individual','Kevin',NULL,'Avinyia',NULL,NULL,NULL,NULL,NULL,NULL,'Kevin Avinyia',NULL,NULL,'avinyiak@gmail.com',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,50000.00,0.00,'unpaid',NULL,NULL,NULL,NULL,NULL,1,'active','2026-04-22','2026-04-22 09:01:20','2026-04-22 09:17:36','2026-04-22 09:17:36'),(3,NULL,'CLT-000003','individual','Kevin',NULL,'Avinyia',NULL,NULL,NULL,NULL,NULL,NULL,'Kevin Avinyia',NULL,NULL,'avinyiak@gmail.com',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,50000.00,0.00,'unpaid',NULL,NULL,NULL,NULL,NULL,1,'active','2026-04-22','2026-04-22 09:01:21','2026-04-22 09:17:15','2026-04-22 09:17:15'),(4,NULL,'CLT-000004','individual','Kevin',NULL,'Avinyia',NULL,NULL,NULL,NULL,NULL,NULL,'Kevin Avinyia',NULL,NULL,'avinyiak@gmail.com',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,50000.00,0.00,'unpaid',NULL,NULL,NULL,NULL,NULL,1,'active','2026-04-22','2026-04-22 09:01:21','2026-04-22 09:17:22','2026-04-22 09:17:22'),(5,NULL,'CLT-000005','individual','Kevin',NULL,'Avinyia',NULL,NULL,NULL,NULL,NULL,NULL,'Kevin Avinyia',NULL,NULL,'avinyiak@gmail.com',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,50000.00,0.00,'unpaid',NULL,NULL,NULL,NULL,NULL,1,'active','2026-04-22','2026-04-22 09:01:22','2026-04-22 09:17:08','2026-04-22 09:17:08'),(6,NULL,'CLT-000006','individual','Joshua',NULL,'Avinyia',NULL,NULL,NULL,NULL,NULL,NULL,'Joshua Avinyia',NULL,NULL,'kavinyia@gmail.com',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,50000.00,0.00,'unpaid',NULL,NULL,NULL,NULL,NULL,1,'active','2026-04-22','2026-04-22 09:05:05','2026-04-22 09:16:57','2026-04-22 09:16:57'),(7,NULL,'CLT-000007','group','Safronite',NULL,'Group',NULL,NULL,NULL,NULL,NULL,NULL,'Safronite Club House',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.00,0.00,'unpaid',NULL,NULL,NULL,NULL,NULL,1,'active',NULL,'2026-04-22 10:32:06','2026-04-22 10:38:25',NULL);
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_number` varchar(20) NOT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `basic_salary` decimal(15,2) NOT NULL DEFAULT 0.00,
  `savings_account_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employees_employee_number_unique` (`employee_number`),
  KEY `employees_savings_account_id_foreign` (`savings_account_id`),
  KEY `employees_created_by_foreign` (`created_by`),
  KEY `employees_client_id_foreign` (`client_id`),
  CONSTRAINT `employees_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_savings_account_id_foreign` FOREIGN KEY (`savings_account_id`) REFERENCES `savings_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `financial_periods`
--

DROP TABLE IF EXISTS `financial_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `financial_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `year` smallint(6) NOT NULL,
  `month` tinyint(4) NOT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `notes` text DEFAULT NULL,
  `closed_by` bigint(20) unsigned DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `reopened_by` bigint(20) unsigned DEFAULT NULL,
  `reopened_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_periods_year_month_unique` (`year`,`month`),
  KEY `financial_periods_closed_by_foreign` (`closed_by`),
  KEY `financial_periods_reopened_by_foreign` (`reopened_by`),
  CONSTRAINT `financial_periods_closed_by_foreign` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `financial_periods_reopened_by_foreign` FOREIGN KEY (`reopened_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financial_periods`
--

LOCK TABLES `financial_periods` WRITE;
/*!40000 ALTER TABLE `financial_periods` DISABLE KEYS */;
INSERT INTO `financial_periods` VALUES (1,2026,1,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 09:33:36','2026-04-09 09:33:36'),(2,2026,2,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 09:33:36','2026-04-09 09:33:36'),(3,2026,3,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 09:33:36','2026-04-09 09:33:36'),(4,2026,4,'open',NULL,1,'2026-04-09 09:34:15',1,'2026-04-09 09:35:40','2026-04-09 09:33:36','2026-04-09 09:35:40'),(5,2026,5,'open',NULL,1,'2026-04-09 09:43:51',1,'2026-04-09 09:43:58','2026-04-09 09:33:36','2026-04-09 09:43:58'),(6,2026,6,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 09:33:36','2026-04-09 09:33:36'),(7,2026,7,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 09:33:36','2026-04-09 09:33:36'),(8,2026,8,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 09:33:36','2026-04-09 09:33:36'),(9,2026,9,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 09:33:36','2026-04-09 09:33:36'),(10,2026,10,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 09:33:36','2026-04-09 09:33:36'),(11,2026,11,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 09:33:36','2026-04-09 09:33:36'),(12,2026,12,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 09:33:36','2026-04-09 09:33:36'),(13,2025,1,'open','Reopened as part of year reopen for 2025.',1,'2026-04-09 09:35:57',1,'2026-04-09 19:17:55','2026-04-09 09:35:17','2026-04-09 19:17:55'),(14,2025,2,'open','Reopened as part of year reopen for 2025.',1,'2026-04-09 09:35:57',1,'2026-04-09 19:17:55','2026-04-09 09:35:17','2026-04-09 19:17:55'),(15,2025,3,'open','Reopened as part of year reopen for 2025.',1,'2026-04-09 09:35:57',1,'2026-04-09 19:17:55','2026-04-09 09:35:17','2026-04-09 19:17:55'),(16,2025,4,'open','Reopened as part of year reopen for 2025.',1,'2026-04-09 09:35:57',1,'2026-04-09 19:17:55','2026-04-09 09:35:17','2026-04-09 19:17:55'),(17,2025,5,'open','Reopened as part of year reopen for 2025.',1,'2026-04-09 09:35:57',1,'2026-04-09 19:17:55','2026-04-09 09:35:17','2026-04-09 19:17:55'),(18,2025,6,'open','Reopened as part of year reopen for 2025.',1,'2026-04-09 09:35:57',1,'2026-04-09 19:17:55','2026-04-09 09:35:17','2026-04-09 19:17:55'),(19,2025,7,'open','Reopened as part of year reopen for 2025.',1,'2026-04-09 09:35:57',1,'2026-04-09 19:17:55','2026-04-09 09:35:17','2026-04-09 19:17:55'),(20,2025,8,'open','Reopened as part of year reopen for 2025.',1,'2026-04-09 09:35:57',1,'2026-04-09 19:17:55','2026-04-09 09:35:17','2026-04-09 19:17:55'),(21,2025,9,'open','Reopened as part of year reopen for 2025.',1,'2026-04-09 09:35:57',1,'2026-04-09 19:17:55','2026-04-09 09:35:17','2026-04-09 19:17:55'),(22,2025,10,'open','Reopened as part of year reopen for 2025.',1,'2026-04-09 09:35:57',1,'2026-04-09 19:17:55','2026-04-09 09:35:17','2026-04-09 19:17:55'),(23,2025,11,'open','Reopened as part of year reopen for 2025.',1,'2026-04-09 09:35:57',1,'2026-04-09 19:17:55','2026-04-09 09:35:17','2026-04-09 19:17:55'),(24,2025,12,'open','Reopened as part of year reopen for 2025.',1,'2026-04-09 09:35:57',1,'2026-04-09 19:17:55','2026-04-09 09:35:17','2026-04-09 19:17:55'),(25,2024,1,'closed','Closed as part of year-end close for 2024.',1,'2026-04-09 09:36:13',NULL,NULL,'2026-04-09 09:35:27','2026-04-09 09:36:13'),(26,2024,2,'closed','Closed as part of year-end close for 2024.',1,'2026-04-09 09:36:13',NULL,NULL,'2026-04-09 09:35:27','2026-04-09 09:36:13'),(27,2024,3,'closed','Closed as part of year-end close for 2024.',1,'2026-04-09 09:36:13',NULL,NULL,'2026-04-09 09:35:27','2026-04-09 09:36:13'),(28,2024,4,'closed','Closed as part of year-end close for 2024.',1,'2026-04-09 09:36:13',NULL,NULL,'2026-04-09 09:35:27','2026-04-09 09:36:13'),(29,2024,5,'closed','Closed as part of year-end close for 2024.',1,'2026-04-09 09:36:13',NULL,NULL,'2026-04-09 09:35:27','2026-04-09 09:36:13'),(30,2024,6,'closed','Closed as part of year-end close for 2024.',1,'2026-04-09 09:36:13',NULL,NULL,'2026-04-09 09:35:27','2026-04-09 09:36:13'),(31,2024,7,'closed','Closed as part of year-end close for 2024.',1,'2026-04-09 09:36:13',NULL,NULL,'2026-04-09 09:35:27','2026-04-09 09:36:13'),(32,2024,8,'closed','Closed as part of year-end close for 2024.',1,'2026-04-09 09:36:13',NULL,NULL,'2026-04-09 09:35:27','2026-04-09 09:36:13'),(33,2024,9,'closed','Closed as part of year-end close for 2024.',1,'2026-04-09 09:36:13',NULL,NULL,'2026-04-09 09:35:27','2026-04-09 09:36:13'),(34,2024,10,'closed','Closed as part of year-end close for 2024.',1,'2026-04-09 09:36:13',NULL,NULL,'2026-04-09 09:35:27','2026-04-09 09:36:13'),(35,2024,11,'closed','Closed as part of year-end close for 2024.',1,'2026-04-09 09:36:13',NULL,NULL,'2026-04-09 09:35:27','2026-04-09 09:36:13'),(36,2024,12,'closed','Closed as part of year-end close for 2024.',1,'2026-04-09 09:36:13',NULL,NULL,'2026-04-09 09:35:27','2026-04-09 09:36:13'),(37,2027,1,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:31:27','2026-04-09 10:31:27'),(38,2027,2,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:31:27','2026-04-09 10:31:27'),(39,2027,3,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:31:27','2026-04-09 10:31:27'),(40,2027,4,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:31:27','2026-04-09 10:31:27'),(41,2027,5,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:31:27','2026-04-09 10:31:27'),(42,2027,6,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:31:27','2026-04-09 10:31:27'),(43,2027,7,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:31:27','2026-04-09 10:31:27'),(44,2027,8,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:31:27','2026-04-09 10:31:27'),(45,2027,9,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:31:27','2026-04-09 10:31:27'),(46,2027,10,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:31:27','2026-04-09 10:31:27'),(47,2027,11,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:31:27','2026-04-09 10:31:27'),(48,2027,12,'open',NULL,NULL,NULL,NULL,NULL,'2026-04-09 10:31:27','2026-04-09 10:31:27');
/*!40000 ALTER TABLE `financial_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fixed_deposit_products`
--

DROP TABLE IF EXISTS `fixed_deposit_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fixed_deposit_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `interest_rate` decimal(8,4) NOT NULL,
  `term_months` int(10) unsigned NOT NULL,
  `min_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `max_amount` decimal(15,2) DEFAULT NULL,
  `deposit_liability_account_id` bigint(20) unsigned DEFAULT NULL,
  `interest_expense_account_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fixed_deposit_products_deposit_liability_account_id_foreign` (`deposit_liability_account_id`),
  KEY `fixed_deposit_products_interest_expense_account_id_foreign` (`interest_expense_account_id`),
  CONSTRAINT `fixed_deposit_products_deposit_liability_account_id_foreign` FOREIGN KEY (`deposit_liability_account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fixed_deposit_products_interest_expense_account_id_foreign` FOREIGN KEY (`interest_expense_account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fixed_deposit_products`
--

LOCK TABLES `fixed_deposit_products` WRITE;
/*!40000 ALTER TABLE `fixed_deposit_products` DISABLE KEYS */;
INSERT INTO `fixed_deposit_products` VALUES (1,'Money Placement',12.0000,12,1000000.00,NULL,22,37,1,'2026-03-15 16:02:54','2026-03-15 16:02:54');
/*!40000 ALTER TABLE `fixed_deposit_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fixed_deposits`
--

DROP TABLE IF EXISTS `fixed_deposits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fixed_deposits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned NOT NULL,
  `savings_account_id` bigint(20) unsigned DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `deposit_number` varchar(255) NOT NULL,
  `principal` decimal(15,2) NOT NULL,
  `interest_rate` decimal(8,4) NOT NULL,
  `term_months` int(10) unsigned NOT NULL,
  `start_date` date NOT NULL,
  `maturity_date` date NOT NULL,
  `interest_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `maturity_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `accrued_interest` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','matured','closed','broken') NOT NULL DEFAULT 'active',
  `closed_date` date DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fixed_deposits_deposit_number_unique` (`deposit_number`),
  KEY `fixed_deposits_client_id_foreign` (`client_id`),
  KEY `fixed_deposits_product_id_foreign` (`product_id`),
  KEY `fixed_deposits_created_by_foreign` (`created_by`),
  KEY `fixed_deposits_branch_id_foreign` (`branch_id`),
  KEY `fixed_deposits_savings_account_id_foreign` (`savings_account_id`),
  CONSTRAINT `fixed_deposits_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fixed_deposits_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fixed_deposits_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fixed_deposits_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `fixed_deposit_products` (`id`),
  CONSTRAINT `fixed_deposits_savings_account_id_foreign` FOREIGN KEY (`savings_account_id`) REFERENCES `savings_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fixed_deposits`
--

LOCK TABLES `fixed_deposits` WRITE;
/*!40000 ALTER TABLE `fixed_deposits` DISABLE KEYS */;
INSERT INTO `fixed_deposits` VALUES (1,6,NULL,NULL,1,'FD-2026-00001',1000000.00,12.0000,12,'2026-04-22','2027-04-22',120000.00,1120000.00,0.00,'active',NULL,1,'2026-04-22 09:11:09','2026-04-22 09:11:09',NULL);
/*!40000 ALTER TABLE `fixed_deposits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `group_members`
--

DROP TABLE IF EXISTS `group_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `group_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `national_id` varchar(255) DEFAULT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_leader` tinyint(1) NOT NULL DEFAULT 0,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_members_group_id_foreign` (`group_id`),
  KEY `group_members_user_id_foreign` (`user_id`),
  KEY `group_members_created_by_foreign` (`created_by`),
  KEY `group_members_client_id_foreign` (`client_id`),
  CONSTRAINT `group_members_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `group_members_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `group_members_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `group_members`
--

LOCK TABLES `group_members` WRITE;
/*!40000 ALTER TABLE `group_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `group_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `group_transactions`
--

DROP TABLE IF EXISTS `group_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `group_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) unsigned NOT NULL,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(30) NOT NULL,
  `posting_type` varchar(255) NOT NULL DEFAULT 'individual',
  `withdrawal_mode` varchar(20) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_before` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance_after` decimal(15,2) NOT NULL DEFAULT 0.00,
  `transaction_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `journal_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_transactions_group_id_foreign` (`group_id`),
  KEY `group_transactions_journal_transaction_id_foreign` (`journal_transaction_id`),
  KEY `group_transactions_created_by_foreign` (`created_by`),
  KEY `group_transactions_member_id_foreign` (`member_id`),
  CONSTRAINT `group_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `group_transactions_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_transactions_journal_transaction_id_foreign` FOREIGN KEY (`journal_transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `group_transactions_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `group_members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `group_transactions`
--

LOCK TABLES `group_transactions` WRITE;
/*!40000 ALTER TABLE `group_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `group_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `groups`
--

DROP TABLE IF EXISTS `groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `group_number` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `registration_date` date DEFAULT NULL,
  `membership_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
  `monthly_interest_rate` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `gl_account_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `groups_group_number_unique` (`group_number`),
  KEY `groups_created_by_foreign` (`created_by`),
  KEY `groups_gl_account_id_foreign` (`gl_account_id`),
  KEY `groups_client_id_foreign` (`client_id`),
  CONSTRAINT `groups_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `groups_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `groups_gl_account_id_foreign` FOREIGN KEY (`gl_account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `groups`
--

LOCK TABLES `groups` WRITE;
/*!40000 ALTER TABLE `groups` DISABLE KEYS */;
INSERT INTO `groups` VALUES (1,7,'GRP-000001','Safronite Club House','2026-04-22',0.00,0.0000,NULL,'active',NULL,1,'2026-04-22 10:32:06','2026-04-22 10:38:25',NULL);
/*!40000 ALTER TABLE `groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_guarantors`
--

DROP TABLE IF EXISTS `loan_guarantors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loan_guarantors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `id_number` varchar(255) DEFAULT NULL,
  `relationship` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `employer` varchar(255) DEFAULT NULL,
  `monthly_income` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loan_guarantors_loan_id_foreign` (`loan_id`),
  KEY `loan_guarantors_client_id_foreign` (`client_id`),
  CONSTRAINT `loan_guarantors_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `loan_guarantors_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_guarantors`
--

LOCK TABLES `loan_guarantors` WRITE;
/*!40000 ALTER TABLE `loan_guarantors` DISABLE KEYS */;
/*!40000 ALTER TABLE `loan_guarantors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_products`
--

DROP TABLE IF EXISTS `loan_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loan_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `interest_rate` decimal(8,4) NOT NULL,
  `interest_method` enum('flat','reducing') NOT NULL,
  `term_months` int(10) unsigned NOT NULL,
  `repayment_frequency` enum('monthly','quarterly') NOT NULL DEFAULT 'monthly',
  `penalty_rate` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `min_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `max_amount` decimal(15,2) DEFAULT NULL,
  `receivable_account_id` bigint(20) unsigned DEFAULT NULL,
  `interest_income_account_id` bigint(20) unsigned DEFAULT NULL,
  `penalty_income_account_id` bigint(20) unsigned DEFAULT NULL,
  `disbursement_account_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loan_products_receivable_account_id_foreign` (`receivable_account_id`),
  KEY `loan_products_interest_income_account_id_foreign` (`interest_income_account_id`),
  KEY `loan_products_penalty_income_account_id_foreign` (`penalty_income_account_id`),
  KEY `loan_products_disbursement_account_id_foreign` (`disbursement_account_id`),
  CONSTRAINT `loan_products_disbursement_account_id_foreign` FOREIGN KEY (`disbursement_account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `loan_products_interest_income_account_id_foreign` FOREIGN KEY (`interest_income_account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `loan_products_penalty_income_account_id_foreign` FOREIGN KEY (`penalty_income_account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `loan_products_receivable_account_id_foreign` FOREIGN KEY (`receivable_account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_products`
--

LOCK TABLES `loan_products` WRITE;
/*!40000 ALTER TABLE `loan_products` DISABLE KEYS */;
INSERT INTO `loan_products` VALUES (1,'Normal Loans',33.6000,'flat',6,'monthly',0.0000,1000000.00,NULL,2,29,NULL,10,1,'2026-03-15 15:50:44','2026-03-15 15:50:44'),(2,'Quarterly Loan',24.0000,'reducing',51,'quarterly',0.0000,0.00,NULL,2,29,NULL,NULL,1,'2026-04-07 14:47:39','2026-04-07 14:47:39'),(3,'Normal Loans',33.0000,'reducing',8,'monthly',0.0000,0.00,NULL,NULL,NULL,NULL,NULL,1,'2026-04-09 15:46:57','2026-04-09 15:47:53'),(4,'Penalty Loan',33.0000,'flat',6,'monthly',0.5000,0.00,NULL,NULL,NULL,NULL,NULL,1,'2026-04-10 06:01:37','2026-04-10 06:01:37');
/*!40000 ALTER TABLE `loan_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_repayments`
--

DROP TABLE IF EXISTS `loan_repayments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loan_repayments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` bigint(20) unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `principal_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `penalty_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(255) NOT NULL DEFAULT 'cash',
  `reference` varchar(255) DEFAULT NULL,
  `received_by` bigint(20) unsigned DEFAULT NULL,
  `transaction_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loan_repayments_loan_id_foreign` (`loan_id`),
  KEY `loan_repayments_received_by_foreign` (`received_by`),
  KEY `loan_repayments_transaction_id_foreign` (`transaction_id`),
  CONSTRAINT `loan_repayments_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`),
  CONSTRAINT `loan_repayments_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `loan_repayments_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_repayments`
--

LOCK TABLES `loan_repayments` WRITE;
/*!40000 ALTER TABLE `loan_repayments` DISABLE KEYS */;
/*!40000 ALTER TABLE `loan_repayments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_schedules`
--

DROP TABLE IF EXISTS `loan_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loan_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` bigint(20) unsigned NOT NULL,
  `installment_no` int(10) unsigned NOT NULL,
  `due_date` date NOT NULL,
  `principal_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance_after` decimal(15,2) NOT NULL DEFAULT 0.00,
  `principal_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','paid','partial','overdue') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loan_schedules_loan_id_foreign` (`loan_id`),
  CONSTRAINT `loan_schedules_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_schedules`
--

LOCK TABLES `loan_schedules` WRITE;
/*!40000 ALTER TABLE `loan_schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `loan_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loans`
--

DROP TABLE IF EXISTS `loans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `loan_number` varchar(255) NOT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `loan_product_id` bigint(20) unsigned NOT NULL,
  `principal` decimal(15,2) NOT NULL,
  `application_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
  `application_fee_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `application_fee_method` enum('loan','savings') NOT NULL DEFAULT 'loan',
  `management_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
  `management_fee_rate` decimal(5,2) NOT NULL DEFAULT 1.50,
  `management_fee_method` enum('loan','savings') NOT NULL DEFAULT 'loan',
  `insurance_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
  `insurance_fee_rate` decimal(5,2) NOT NULL DEFAULT 1.50,
  `insurance_fee_method` enum('loan','savings') NOT NULL DEFAULT 'loan',
  `fee_deduction_method` enum('loan','savings') DEFAULT NULL,
  `interest_rate` decimal(8,4) NOT NULL,
  `interest_method` enum('flat','reducing') NOT NULL,
  `term_months` int(10) unsigned NOT NULL,
  `repayment_frequency` enum('monthly','quarterly') NOT NULL DEFAULT 'monthly',
  `disbursement_date` date DEFAULT NULL,
  `maturity_date` date DEFAULT NULL,
  `outstanding_principal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `outstanding_interest` decimal(15,2) NOT NULL DEFAULT 0.00,
  `outstanding_penalty` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','active','closed','defaulted') NOT NULL DEFAULT 'pending',
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `fee_savings_account_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loans_loan_number_unique` (`loan_number`),
  KEY `loans_client_id_foreign` (`client_id`),
  KEY `loans_loan_product_id_foreign` (`loan_product_id`),
  KEY `loans_approved_by_foreign` (`approved_by`),
  KEY `loans_created_by_foreign` (`created_by`),
  KEY `loans_branch_id_foreign` (`branch_id`),
  KEY `loans_fee_savings_account_id_foreign` (`fee_savings_account_id`),
  CONSTRAINT `loans_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `loans_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `loans_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `loans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `loans_fee_savings_account_id_foreign` FOREIGN KEY (`fee_savings_account_id`) REFERENCES `savings_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `loans_loan_product_id_foreign` FOREIGN KEY (`loan_product_id`) REFERENCES `loan_products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loans`
--

LOCK TABLES `loans` WRITE;
/*!40000 ALTER TABLE `loans` DISABLE KEYS */;
INSERT INTO `loans` VALUES (1,'LN-2026-00001',6,NULL,1,3000000.00,0.00,0.00,'loan',0.00,1.50,'loan',0.00,1.50,'loan',NULL,33.6000,'flat',6,'monthly',NULL,NULL,0.00,0.00,0.00,'pending',NULL,NULL,1,NULL,'2026-04-22 09:10:10','2026-04-22 09:10:27','2026-04-22 09:10:27',NULL);
/*!40000 ALTER TABLE `loans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `member_shares`
--

DROP TABLE IF EXISTS `member_shares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `member_shares` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned NOT NULL,
  `share_number` varchar(255) NOT NULL,
  `share_value` decimal(15,2) NOT NULL DEFAULT 100000.00,
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('unpaid','partial','paid','liquidated') NOT NULL DEFAULT 'unpaid',
  `liquidated_at` date DEFAULT NULL,
  `liquidation_notes` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `liquidated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_shares_share_number_unique` (`share_number`),
  KEY `member_shares_client_id_foreign` (`client_id`),
  KEY `member_shares_created_by_foreign` (`created_by`),
  KEY `member_shares_liquidated_by_foreign` (`liquidated_by`),
  CONSTRAINT `member_shares_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `member_shares_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `member_shares_liquidated_by_foreign` FOREIGN KEY (`liquidated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `member_shares`
--

LOCK TABLES `member_shares` WRITE;
/*!40000 ALTER TABLE `member_shares` DISABLE KEYS */;
INSERT INTO `member_shares` VALUES (1,1,'SHR-2026-00001',100000.00,0.00,'unpaid',NULL,NULL,NULL,1,'2026-04-22 09:01:20','2026-04-22 09:01:20',NULL),(2,2,'SHR-2026-00002',100000.00,0.00,'unpaid',NULL,NULL,NULL,1,'2026-04-22 09:01:20','2026-04-22 09:01:20',NULL),(3,3,'SHR-2026-00003',100000.00,0.00,'unpaid',NULL,NULL,NULL,1,'2026-04-22 09:01:21','2026-04-22 09:01:21',NULL),(4,4,'SHR-2026-00004',100000.00,0.00,'unpaid',NULL,NULL,NULL,1,'2026-04-22 09:01:21','2026-04-22 09:01:21',NULL),(5,5,'SHR-2026-00005',100000.00,0.00,'unpaid',NULL,NULL,NULL,1,'2026-04-22 09:01:22','2026-04-22 09:01:22',NULL),(6,6,'SHR-2026-00006',100000.00,0.00,'unpaid',NULL,NULL,NULL,1,'2026-04-22 09:05:05','2026-04-22 09:05:05',NULL);
/*!40000 ALTER TABLE `member_shares` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2014_10_12_000000_create_users_table',1),(2,'2014_10_12_100000_create_password_resets_table',1),(3,'2019_08_19_000000_create_failed_jobs_table',1),(4,'2019_12_14_000001_create_personal_access_tokens_table',1),(5,'2024_01_01_000001_create_clients_table',1),(6,'2024_01_01_000002_create_accounts_table',1),(7,'2024_01_01_000003_create_transactions_table',1),(8,'2024_01_01_000004_create_transaction_lines_table',1),(9,'2024_01_01_000005_create_loan_products_table',1),(10,'2024_01_01_000006_create_loans_table',1),(11,'2024_01_01_000007_create_loan_schedules_table',1),(12,'2024_01_01_000008_create_loan_repayments_table',1),(13,'2024_01_01_000009_create_savings_products_table',1),(14,'2024_01_01_000010_create_savings_accounts_table',1),(15,'2024_01_01_000011_create_savings_transactions_table',1),(16,'2024_01_01_000012_create_fixed_deposit_products_table',1),(17,'2024_01_01_000013_create_fixed_deposits_table',1),(18,'2024_01_02_000001_create_branches_table',2),(19,'2024_01_02_000002_create_loan_guarantors_table',2),(20,'2024_01_02_000003_create_system_settings_table',2),(21,'2026_03_10_111812_create_permission_tables',2),(22,'2024_01_02_000004_add_columns_to_users_table',3),(23,'2026_03_10_175804_add_reversal_columns_to_transactions_table',4),(24,'2026_03_11_091053_add_savings_account_to_fixed_deposits_table',5),(25,'2026_03_11_130838_add_personal_fields_to_clients_table',6),(26,'2026_03_11_132055_add_membership_fields_to_clients_table',7),(27,'2026_03_12_094141_add_broken_status_to_fixed_deposits',8),(28,'2026_03_12_094351_add_broken_status_to_fixed_deposits',8),(29,'2026_03_12_115600_add_membership_fee_to_clients_table',9),(30,'2026_03_12_115612_create_member_shares_table',9),(31,'2026_03_12_115746_add_membership_fee_income_account',10),(32,'2026_03_12_131503_add_fees_to_loans_table',11),(33,'2026_03_12_131544_add_loan_fee_income_accounts',12),(34,'2026_03_13_000001_add_fee_rates_to_loans_table',13),(35,'2026_03_13_000002_add_loan_insurance_payable_account',13),(36,'2026_03_13_000003_add_application_fee_to_loans_table',14),(37,'2026_03_13_000004_add_per_fee_methods_to_loans_table',15),(38,'2026_03_13_100001_create_employees_table',16),(39,'2026_03_13_100002_create_payroll_runs_table',16),(40,'2026_03_13_100003_create_payroll_items_table',16),(41,'2026_03_13_100004_add_salary_expense_account',16),(42,'2026_03_13_100005_add_client_id_to_employees_table',17),(43,'2026_03_13_200001_add_joining_date_to_clients_table',18),(44,'2026_03_24_000001_create_audit_logs_table',19),(45,'2026_03_24_000002_add_last_interest_date_to_savings_accounts',20),(46,'2026_04_04_100001_create_groups_table',21),(47,'2026_04_04_100002_create_group_members_table',21),(48,'2026_04_04_100003_create_group_transactions_table',21),(49,'2026_04_07_100000_add_client_type_to_clients_table',21),(50,'2026_04_07_100002_add_group_gl_accounts',22),(51,'2026_04_07_100011_align_groups_tables_for_client_link',22),(52,'2026_04_07_200001_fix_groups_schema_to_original_plan',23),(53,'2026_04_07_173604_add_repayment_frequency_to_loan_tables',24),(54,'2026_04_08_000001_add_client_id_to_users_table',25),(55,'2026_04_08_000002_add_client_id_to_group_members_table',26),(56,'2026_04_08_000003_add_client_id_to_groups_table',27),(57,'2026_04_08_103658_create_client_portal_users_table',28),(59,'2026_04_09_102732_create_financial_periods_table',29),(60,'2026_04_12_082440_add_liquidation_to_member_shares',30),(61,'2026_04_12_082650_create_share_transactions_table',30),(62,'2026_04_12_084217_add_shares_module_setting',31),(63,'2026_04_12_122207_add_membership_fee_module_setting',32),(64,'2026_04_12_131746_add_client_id_to_transaction_lines',33),(65,'2026_04_12_131924_add_client_id_to_transaction_lines',33),(66,'2026_04_12_172144_add_amount_paid_before_to_share_transactions_table',34);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES (1,'App\\Models\\User',1),(3,'App\\Models\\User',3),(3,'App\\Models\\User',14),(5,'App\\Models\\User',16),(5,'App\\Models\\User',18),(6,'App\\Models\\User',14),(6,'App\\Models\\User',15),(7,'App\\Models\\User',15),(7,'App\\Models\\User',17);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES ('avinyiak@gmail.com','$2y$10$kZMfNcMBlHuPoHR32MQU0OH9o80yWgfpb.zXEfXQVqJ8Wq0yg6OZm','2026-04-10 03:23:37'),('kavinyia@gmail.com','$2y$10$YEV80xdku.PtpKpC.Pu1.uP1nMeeyF/vtbxlR879GWMsmMBlfUSnu','2026-04-10 03:29:19');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_items`
--

DROP TABLE IF EXISTS `payroll_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payroll_run_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `savings_account_id` bigint(20) unsigned DEFAULT NULL,
  `basic_salary` decimal(15,2) NOT NULL DEFAULT 0.00,
  `allowances` decimal(15,2) NOT NULL DEFAULT 0.00,
  `deductions` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_salary` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payroll_items_payroll_run_id_foreign` (`payroll_run_id`),
  KEY `payroll_items_employee_id_foreign` (`employee_id`),
  KEY `payroll_items_savings_account_id_foreign` (`savings_account_id`),
  CONSTRAINT `payroll_items_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_items_payroll_run_id_foreign` FOREIGN KEY (`payroll_run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_items_savings_account_id_foreign` FOREIGN KEY (`savings_account_id`) REFERENCES `savings_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_items`
--

LOCK TABLES `payroll_items` WRITE;
/*!40000 ALTER TABLE `payroll_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `payroll_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_runs`
--

DROP TABLE IF EXISTS `payroll_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_number` varchar(20) NOT NULL,
  `period_month` tinyint(3) unsigned NOT NULL,
  `period_year` smallint(5) unsigned NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `total_gross` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','processed') NOT NULL DEFAULT 'draft',
  `processed_by` bigint(20) unsigned DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_runs_run_number_unique` (`run_number`),
  KEY `payroll_runs_processed_by_foreign` (`processed_by`),
  KEY `payroll_runs_created_by_foreign` (`created_by`),
  CONSTRAINT `payroll_runs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_runs_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_runs`
--

LOCK TABLES `payroll_runs` WRITE;
/*!40000 ALTER TABLE `payroll_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `payroll_runs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'view dashboard','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(2,'view clients','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(3,'create clients','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(4,'edit clients','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(5,'delete clients','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(6,'view accounts','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(7,'create accounts','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(8,'edit accounts','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(9,'view transactions','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(10,'create transactions','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(11,'view loan-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(12,'create loan-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(13,'edit loan-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(14,'view loans','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(15,'create loans','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(16,'disburse loans','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(17,'repay loans','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(18,'view savings-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(19,'create savings-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(20,'edit savings-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(21,'view savings','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(22,'create savings','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(23,'deposit savings','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(24,'withdraw savings','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(25,'transfer savings','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(26,'view fd-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(27,'create fd-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(28,'edit fd-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(29,'view fixed-deposits','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(30,'create fixed-deposits','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(31,'mature fixed-deposits','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(32,'use teller','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(33,'view reports','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(34,'manage branches','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(35,'manage users','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(36,'manage settings','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(37,'manage backup','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(38,'view groups','web','2026-04-07 12:29:03','2026-04-07 12:29:03'),(39,'manage groups','web','2026-04-07 12:29:03','2026-04-07 12:29:03'),(40,'reverse transactions','web','2026-04-12 04:16:48','2026-04-12 04:16:48'),(41,'view employees','web','2026-04-12 04:49:26','2026-04-12 04:49:26'),(42,'create employees','web','2026-04-12 04:49:26','2026-04-12 04:49:26'),(43,'edit employees','web','2026-04-12 04:49:26','2026-04-12 04:49:26'),(44,'view payroll','web','2026-04-12 04:49:26','2026-04-12 04:49:26'),(45,'create payroll','web','2026-04-12 04:49:26','2026-04-12 04:49:26'),(46,'process payroll','web','2026-04-12 04:49:26','2026-04-12 04:49:26'),(47,'delete payroll','web','2026-04-12 04:49:26','2026-04-12 04:49:26'),(48,'manage shares','web','2026-04-12 04:49:26','2026-04-12 04:49:26');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
INSERT INTO `role_has_permissions` VALUES (1,1),(1,2),(1,3),(1,4),(2,1),(2,2),(2,3),(2,4),(3,1),(3,2),(3,3),(4,1),(4,2),(4,3),(5,1),(6,1),(6,2),(6,3),(6,4),(7,1),(7,2),(8,1),(8,2),(9,1),(9,2),(9,3),(10,1),(10,2),(10,3),(11,1),(11,2),(11,4),(12,1),(12,2),(13,1),(13,2),(14,1),(14,2),(14,3),(14,4),(15,1),(15,2),(15,3),(16,1),(16,2),(16,3),(17,1),(17,2),(17,3),(18,1),(18,2),(18,4),(19,1),(19,2),(20,1),(20,2),(21,1),(21,2),(21,3),(21,4),(22,1),(22,2),(22,3),(23,1),(23,2),(23,3),(24,1),(24,2),(24,3),(25,1),(25,2),(25,3),(26,1),(26,2),(26,4),(27,1),(27,2),(28,1),(28,2),(29,1),(29,2),(29,3),(29,4),(30,1),(30,2),(30,3),(31,1),(31,2),(31,3),(32,1),(32,2),(32,3),(33,1),(33,2),(33,3),(33,4),(34,1),(34,2),(35,1),(35,2),(36,1),(36,2),(37,1),(38,1),(38,2),(38,3),(38,4),(39,1),(39,2),(39,3),(40,1),(40,2),(40,3),(41,1),(41,2),(41,3),(41,4),(42,1),(42,2),(43,1),(43,2),(44,1),(44,2),(45,1),(45,2),(46,1),(46,2),(47,1),(47,2),(48,1),(48,2),(48,3);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'super_admin','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(2,'admin','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(3,'cashier','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(4,'staff','web','2026-03-10 08:46:07','2026-03-10 08:46:07'),(5,'group_leader','web','2026-04-07 12:29:04','2026-04-07 12:29:04'),(6,'group_member','web','2026-04-07 12:29:04','2026-04-07 12:29:04'),(7,'client','web','2026-04-08 02:14:59','2026-04-08 02:14:59'),(8,'hr_manager','web','2026-04-12 04:44:48','2026-04-12 04:44:48');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `savings_accounts`
--

DROP TABLE IF EXISTS `savings_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `savings_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `account_number` varchar(255) NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','dormant','closed') NOT NULL DEFAULT 'active',
  `opened_date` date DEFAULT NULL,
  `last_interest_date` date DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `savings_accounts_account_number_unique` (`account_number`),
  KEY `savings_accounts_client_id_foreign` (`client_id`),
  KEY `savings_accounts_product_id_foreign` (`product_id`),
  KEY `savings_accounts_created_by_foreign` (`created_by`),
  KEY `savings_accounts_branch_id_foreign` (`branch_id`),
  CONSTRAINT `savings_accounts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `savings_accounts_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `savings_accounts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `savings_accounts_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `savings_products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `savings_accounts`
--

LOCK TABLES `savings_accounts` WRITE;
/*!40000 ALTER TABLE `savings_accounts` DISABLE KEYS */;
INSERT INTO `savings_accounts` VALUES (1,6,NULL,1,'SAV2600001',0.00,'active','2026-04-22',NULL,1,'2026-04-22 09:05:31','2026-04-22 09:05:31',NULL),(2,6,NULL,1,'SAV2600002',0.00,'active','2026-04-22',NULL,1,'2026-04-22 09:05:32','2026-04-22 09:05:32',NULL),(3,6,NULL,1,'SAV2600003',0.00,'active','2026-04-22',NULL,1,'2026-04-22 09:05:32','2026-04-22 09:05:32',NULL),(4,6,NULL,1,'SAV2600004',0.00,'active','2026-04-22',NULL,1,'2026-04-22 09:05:33','2026-04-22 09:05:33',NULL),(5,6,NULL,1,'SAV2600005',0.00,'active','2026-04-22',NULL,1,'2026-04-22 09:05:33','2026-04-22 09:05:33',NULL),(6,6,NULL,1,'SAV2600006',0.00,'active','2026-04-22',NULL,1,'2026-04-22 09:05:34','2026-04-22 09:05:34',NULL),(7,6,NULL,1,'SAV2600007',0.00,'active','2026-04-22',NULL,1,'2026-04-22 09:05:34','2026-04-22 09:05:34',NULL),(8,6,NULL,1,'SAV2600008',0.00,'active','2026-04-22',NULL,1,'2026-04-22 09:05:35','2026-04-22 09:05:35',NULL),(9,1,NULL,1,'SAV2600009',0.00,'active','2026-04-22',NULL,1,'2026-04-22 09:09:35','2026-04-22 09:09:35',NULL);
/*!40000 ALTER TABLE `savings_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `savings_products`
--

DROP TABLE IF EXISTS `savings_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `savings_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `minimum_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `withdrawal_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_rate` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `interest_frequency` enum('daily','monthly','quarterly','annually') NOT NULL DEFAULT 'monthly',
  `savings_liability_account_id` bigint(20) unsigned DEFAULT NULL,
  `interest_expense_account_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `savings_products_savings_liability_account_id_foreign` (`savings_liability_account_id`),
  KEY `savings_products_interest_expense_account_id_foreign` (`interest_expense_account_id`),
  CONSTRAINT `savings_products_interest_expense_account_id_foreign` FOREIGN KEY (`interest_expense_account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `savings_products_savings_liability_account_id_foreign` FOREIGN KEY (`savings_liability_account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `savings_products`
--

LOCK TABLES `savings_products` WRITE;
/*!40000 ALTER TABLE `savings_products` DISABLE KEYS */;
INSERT INTO `savings_products` VALUES (1,'Ordinary Savings',100000.00,10000.00,10.0000,'monthly',21,NULL,1,'2026-03-15 15:47:32','2026-03-25 08:44:02'),(2,'Interest Wallet',100000.00,20000.00,10.0000,'monthly',21,NULL,1,'2026-03-25 08:25:45','2026-03-25 08:49:22');
/*!40000 ALTER TABLE `savings_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `savings_transactions`
--

DROP TABLE IF EXISTS `savings_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `savings_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `savings_account_id` bigint(20) unsigned NOT NULL,
  `transaction_type` enum('deposit','withdrawal','transfer','loan_repayment','interest','fee') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_before` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance_after` decimal(15,2) NOT NULL DEFAULT 0.00,
  `transaction_date` date NOT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `transaction_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `savings_transactions_savings_account_id_foreign` (`savings_account_id`),
  KEY `savings_transactions_transaction_id_foreign` (`transaction_id`),
  KEY `savings_transactions_created_by_foreign` (`created_by`),
  CONSTRAINT `savings_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `savings_transactions_savings_account_id_foreign` FOREIGN KEY (`savings_account_id`) REFERENCES `savings_accounts` (`id`),
  CONSTRAINT `savings_transactions_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `savings_transactions`
--

LOCK TABLES `savings_transactions` WRITE;
/*!40000 ALTER TABLE `savings_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `savings_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `share_transactions`
--

DROP TABLE IF EXISTS `share_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `share_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `share_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `type` enum('payment','revaluation','liquidation') NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `old_value` decimal(15,2) DEFAULT NULL,
  `new_value` decimal(15,2) DEFAULT NULL,
  `amount_paid_before` decimal(15,2) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `payment_method` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `journal_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `share_transactions_share_id_foreign` (`share_id`),
  KEY `share_transactions_client_id_foreign` (`client_id`),
  KEY `share_transactions_journal_transaction_id_foreign` (`journal_transaction_id`),
  KEY `share_transactions_created_by_foreign` (`created_by`),
  CONSTRAINT `share_transactions_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `share_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `share_transactions_journal_transaction_id_foreign` FOREIGN KEY (`journal_transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `share_transactions_share_id_foreign` FOREIGN KEY (`share_id`) REFERENCES `member_shares` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `share_transactions`
--

LOCK TABLES `share_transactions` WRITE;
/*!40000 ALTER TABLE `share_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `share_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `group` varchar(255) NOT NULL DEFAULT 'general',
  `label` varchar(255) DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'text',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_settings_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'org_name','Konnect Initiatives Ltd','general','Organisation Name','text','2026-03-10 08:19:32','2026-04-12 05:04:49'),(2,'org_address','123 Finance Street','general','Address','textarea','2026-03-10 08:19:32','2026-03-10 08:19:32'),(3,'org_phone','+254 700 000 000','general','Phone','text','2026-03-10 08:19:32','2026-03-10 08:19:32'),(4,'org_email','info@konnectinitiatives.com','general','Email','text','2026-03-10 08:19:32','2026-03-15 16:32:47'),(5,'currency','UGX','general','Currency Code','text','2026-03-10 08:19:32','2026-03-10 09:33:02'),(6,'currency_symbol','UGX','general','Currency Symbol','text','2026-03-10 08:19:32','2026-03-10 09:33:02'),(7,'decimal_places','0','general','Decimal Places','number','2026-03-10 08:19:32','2026-03-15 16:32:47'),(8,'financial_year_start','01-01','general','Financial Year Start (MM-DD)','text','2026-03-10 08:19:32','2026-03-10 08:19:32'),(9,'penalty_grace_days','0','loans','Penalty Grace Days','number','2026-03-10 08:19:32','2026-03-10 08:19:32'),(10,'backup_path','backups','system','Backup Storage Path','text','2026-03-10 08:19:32','2026-03-10 08:19:32'),(11,'max_loan_per_client','3','loans','Max Active Loans Per Client','number','2026-03-10 08:19:32','2026-03-10 08:19:32'),(12,'mail_from_name','Konnect Initiatives Ltd','mail','Mail Sender Name','text','2026-04-09 02:55:29','2026-04-09 02:56:43'),(13,'mail_from_address','statement@konnectinitiatives.com','mail','Mail From Address','text','2026-04-09 02:55:29','2026-04-09 02:55:29'),(14,'org_logo','','general','Organisation Logo','file','2026-04-12 05:02:56','2026-04-12 05:08:12'),(15,'shares_module_enabled','0','modules','Member Shares Module','boolean','2026-04-12 09:08:53','2026-04-12 14:44:22'),(16,'membership_fee_module_enabled','0','modules','Membership Fee Module','boolean','2026-04-12 09:22:28','2026-04-12 09:37:58');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaction_lines`
--

DROP TABLE IF EXISTS `transaction_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaction_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` bigint(20) unsigned NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction_lines_transaction_id_foreign` (`transaction_id`),
  KEY `transaction_lines_account_id_foreign` (`account_id`),
  KEY `transaction_lines_client_id_foreign` (`client_id`),
  CONSTRAINT `transaction_lines_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `transaction_lines_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transaction_lines_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaction_lines`
--

LOCK TABLES `transaction_lines` WRITE;
/*!40000 ALTER TABLE `transaction_lines` DISABLE KEYS */;
INSERT INTO `transaction_lines` VALUES (1,1,10,NULL,1000000.00,0.00,'FD receipt - FD-2026-00001','2026-04-22 09:11:09','2026-04-22 09:11:09'),(2,1,22,NULL,0.00,1000000.00,'FD liability - FD-2026-00001','2026-04-22 09:11:09','2026-04-22 09:11:09');
/*!40000 ALTER TABLE `transaction_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `reference` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `module` varchar(255) DEFAULT NULL,
  `module_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `reversal_of` bigint(20) unsigned DEFAULT NULL,
  `reversed_by` bigint(20) unsigned DEFAULT NULL,
  `reversal_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transactions_reference_unique` (`reference`),
  KEY `transactions_created_by_foreign` (`created_by`),
  KEY `transactions_reversal_of_foreign` (`reversal_of`),
  KEY `transactions_reversed_by_foreign` (`reversed_by`),
  CONSTRAINT `transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_reversal_of_foreign` FOREIGN KEY (`reversal_of`) REFERENCES `transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_reversed_by_foreign` FOREIGN KEY (`reversed_by`) REFERENCES `transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` VALUES (1,'2026-04-22','TXN-20260422-0001','Fixed deposit creation - FD-2026-00001','fixed_deposit',1,1,NULL,NULL,NULL,'2026-04-22 09:11:09','2026-04-22 09:11:09');
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_branch_id_foreign` (`branch_id`),
  KEY `users_client_id_foreign` (`client_id`),
  CONSTRAINT `users_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,NULL,NULL,'System Administrator',NULL,1,'admin@eltech.local',NULL,'$2y$10$we8mKIwDE7I9QwirFul5s./S1aYYjVEd1N9cbFb.VFuaHuYVZ0S5m','vlI7dp71ZIW8NSjllfFgiz5iatlgm1018kdZrZ9Bg8gpkvhF9cpWhIomUH9t','2026-03-10 08:46:11','2026-03-10 08:46:11');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-22 13:54:40
