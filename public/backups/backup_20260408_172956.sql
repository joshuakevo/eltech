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
) ENGINE=InnoDB AUTO_INCREMENT=204 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'create','Users','Created new user','127.0.0.1','http://127.0.0.1:8000/users','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:02:48'),(2,1,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:03:03'),(3,3,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:03:24'),(4,3,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:03:24'),(5,3,'create','Clients','Created a new client','127.0.0.1','http://127.0.0.1:8000/clients','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:04:08'),(6,3,'create','Clients','Created a new client','127.0.0.1','http://127.0.0.1:8000/clients','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:04:40'),(7,3,'create','Savings','Opened savings account','127.0.0.1','http://127.0.0.1:8000/savings','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:05:04'),(8,3,'create','Savings','Savings deposit','127.0.0.1','http://127.0.0.1:8000/savings/1/deposit','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:05:30'),(9,3,'create','Savings','POST /savings/post-interest-bulk','127.0.0.1','http://127.0.0.1:8000/savings/post-interest-bulk','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:06:00'),(10,3,'create','Savings','POST /savings/post-interest-bulk','127.0.0.1','http://127.0.0.1:8000/savings/post-interest-bulk','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:12:31'),(11,3,'create','Savings','Opened savings account','127.0.0.1','http://127.0.0.1:8000/savings','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:17:11'),(12,3,'create','Savings','Savings deposit','127.0.0.1','http://127.0.0.1:8000/savings/2/deposit','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:17:45'),(13,3,'create','Savings','Savings deposit','127.0.0.1','http://127.0.0.1:8000/savings/2/deposit','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:18:06'),(14,3,'create','Savings','Savings deposit','127.0.0.1','http://127.0.0.1:8000/savings/2/deposit','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:18:22'),(15,3,'create','Savings','Savings deposit','127.0.0.1','http://127.0.0.1:8000/savings/2/deposit','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:18:42'),(16,3,'create','Savings','POST /savings/post-interest-bulk','127.0.0.1','http://127.0.0.1:8000/savings/post-interest-bulk','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:19:05'),(17,3,'create','Savings','POST /savings/post-interest-bulk','127.0.0.1','http://127.0.0.1:8000/savings/post-interest-bulk','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:20:38'),(18,3,'create','Savings','POST /savings/post-interest-bulk','127.0.0.1','http://127.0.0.1:8000/savings/post-interest-bulk','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 09:22:28'),(19,3,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 13:44:49'),(20,3,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 13:44:49'),(21,3,'create','Savings','Savings deposit','127.0.0.1','http://127.0.0.1:8000/savings/1/deposit','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 13:47:15'),(22,3,'create','HR','POST /employees','127.0.0.1','http://127.0.0.1:8000/employees','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 13:49:36'),(23,3,'update','HR','PUT /employees/1','127.0.0.1','http://127.0.0.1:8000/employees/1','PUT','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 13:50:04'),(24,3,'update','HR','PUT /employees/1','127.0.0.1','http://127.0.0.1:8000/employees/1','PUT','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 13:50:43'),(25,3,'update','HR','PUT /employees/1','127.0.0.1','http://127.0.0.1:8000/employees/1','PUT','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 13:51:08'),(26,3,'create','Payroll','POST /payroll','127.0.0.1','http://127.0.0.1:8000/payroll','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 13:54:30'),(27,3,'create','Payroll','POST /payroll/1/process','127.0.0.1','http://127.0.0.1:8000/payroll/1/process','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-25 13:54:58'),(28,3,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-02 07:41:25'),(29,3,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-02 07:41:25'),(30,1,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-02 10:35:17'),(31,1,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-02 10:35:17'),(32,1,'update','Clients','Updated client','127.0.0.1','http://127.0.0.1:8000/clients/2','PUT','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-02 10:36:19'),(33,1,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 09:20:31'),(34,1,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 09:20:31'),(35,1,'create',NULL,'Updated system settings','127.0.0.1','http://127.0.0.1:8000/settings','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 09:21:01'),(36,1,'create',NULL,'Ran data reconciliation','127.0.0.1','http://127.0.0.1:8000/settings/reconcile','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 09:21:07'),(37,1,'create','Loans','Created new loan','127.0.0.1','http://127.0.0.1:8000/loans','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 09:33:33'),(38,1,'create','Loans','Disbursed loan','127.0.0.1','http://127.0.0.1:8000/loans/1/disburse','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 09:34:37'),(39,1,'create','Loans','Recorded loan repayment','127.0.0.1','http://127.0.0.1:8000/loans/1/repay','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 09:39:31'),(40,1,'create','Loans','Recorded loan repayment','127.0.0.1','http://127.0.0.1:8000/loans/1/repay','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 10:03:13'),(41,1,'create',NULL,'Ran data reconciliation','127.0.0.1','http://127.0.0.1:8000/settings/reconcile','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 10:05:10'),(42,1,'create','Clients','Created a new client','127.0.0.1','http://127.0.0.1:8000/clients','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:31:56'),(43,1,'create',NULL,'POST /groups/1/members','127.0.0.1','http://127.0.0.1:8000/groups/1/members','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:33:12'),(44,1,'create',NULL,'POST /groups/1/members','127.0.0.1','http://127.0.0.1:8000/groups/1/members','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:33:55'),(45,1,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:35:59'),(46,4,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:36:19'),(47,4,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:36:19'),(48,4,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:36:45'),(49,1,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:37:02'),(50,1,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:37:02'),(51,1,'create',NULL,'POST /groups/1/members','127.0.0.1','http://127.0.0.1:8000/groups/1/members','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:37:56'),(52,1,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:38:12'),(53,NULL,'login_failed',NULL,'Failed login attempt for: leader@gmail.com','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:38:29'),(54,1,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:38:44'),(55,1,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:38:44'),(56,1,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:39:06'),(57,5,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:39:15'),(58,5,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:39:15'),(59,5,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:39:58'),(60,1,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:40:01'),(61,1,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:40:01'),(62,1,'create',NULL,'POST /groups/1/deposit','127.0.0.1','http://127.0.0.1:8000/groups/1/deposit','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:41:41'),(63,1,'create',NULL,'POST /groups/1/deposit','127.0.0.1','http://127.0.0.1:8000/groups/1/deposit','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:42:19'),(64,1,'create','Clients','Created a new client','127.0.0.1','http://127.0.0.1:8000/clients','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:43:45'),(65,1,'create',NULL,'POST /groups/1/withdraw','127.0.0.1','http://127.0.0.1:8000/groups/1/withdraw','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:46:43'),(66,1,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:46:58'),(67,NULL,'login_failed',NULL,'Failed login attempt for: leadrer@gmail.com','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:47:07'),(68,5,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:47:20'),(69,5,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 12:47:20'),(70,5,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 13:56:15'),(71,1,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 13:56:26'),(72,1,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 13:56:27'),(73,1,'create',NULL,'POST /groups','127.0.0.1','http://127.0.0.1:8000/groups','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:26:53'),(74,1,'create',NULL,'POST /groups/3/members','127.0.0.1','http://127.0.0.1:8000/groups/3/members','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:28:01'),(75,1,'create',NULL,'POST /groups/3/members','127.0.0.1','http://127.0.0.1:8000/groups/3/members','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:28:51'),(76,1,'create',NULL,'POST /groups/3/deposit','127.0.0.1','http://127.0.0.1:8000/groups/3/deposit','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:29:20'),(77,1,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:29:45'),(78,8,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:30:08'),(79,8,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:30:08'),(80,8,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:30:24'),(81,1,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:30:29'),(82,1,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:30:29'),(83,1,'update',NULL,'PUT /groups/3/members/4','127.0.0.1','http://127.0.0.1:8000/groups/3/members/4','PUT','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:30:51'),(84,1,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:31:05'),(85,7,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:31:19'),(86,7,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:31:19'),(87,7,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:31:57'),(88,1,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:32:05'),(89,1,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:32:05'),(90,1,'create','Loan Products','POST /loan-products','127.0.0.1','http://127.0.0.1:8000/loan-products','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:47:39'),(91,1,'create','Clients','Created a new client','127.0.0.1','http://127.0.0.1:8000/clients','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:48:30'),(92,1,'create','Loans','Created new loan','127.0.0.1','http://127.0.0.1:8000/loans','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:49:53'),(93,1,'create','Loans','Disbursed loan','127.0.0.1','http://127.0.0.1:8000/loans/2/disburse','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 14:52:49'),(94,1,'create','Loans','Disbursed loan','127.0.0.1','http://127.0.0.1:8000/loans/2/disburse','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 15:32:46'),(95,1,'create','Loans','Disbursed loan','127.0.0.1','http://127.0.0.1:8000/loans/2/disburse','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 15:39:07'),(96,1,'create','Loans','Created new loan','127.0.0.1','http://127.0.0.1:8000/loans','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-07 15:41:40'),(97,1,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 02:06:43'),(98,1,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 02:06:44'),(99,1,'create',NULL,'Updated system settings','127.0.0.1','http://127.0.0.1:8000/settings','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 02:33:59'),(100,1,'create',NULL,'Ran data reconciliation','127.0.0.1','http://127.0.0.1:8000/settings/reconcile','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 02:34:04'),(101,1,'update','Clients','Updated client','127.0.0.1','http://127.0.0.1:8000/clients/5','PUT','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 02:38:17'),(102,1,'update',NULL,'PUT /groups/3','127.0.0.1','http://127.0.0.1:8000/groups/3','PUT','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 05:18:56'),(103,1,'create','Clients','Created a new client','127.0.0.1','http://127.0.0.1:8000/clients','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 05:21:15'),(104,1,'create',NULL,'POST /groups','127.0.0.1','http://127.0.0.1:8000/groups','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 05:22:56'),(105,1,'create',NULL,'POST /groups/4/members','127.0.0.1','http://127.0.0.1:8000/groups/4/members','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 05:24:05'),(106,1,'create',NULL,'POST /groups/4/deposit','127.0.0.1','http://127.0.0.1:8000/groups/4/deposit','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 05:24:25'),(107,1,'create','Clients','Created a new client','127.0.0.1','http://127.0.0.1:8000/clients','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 05:32:27'),(108,1,'create',NULL,'POST /groups/5/members','127.0.0.1','http://127.0.0.1:8000/groups/5/members','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 05:35:39'),(109,1,'create',NULL,'POST /groups/5/deposit','127.0.0.1','http://127.0.0.1:8000/groups/5/deposit','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 05:36:10'),(110,1,'create',NULL,'POST /groups/5/members','127.0.0.1','http://127.0.0.1:8000/groups/5/members','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 05:36:35'),(111,1,'create',NULL,'POST /groups/5/deposit','127.0.0.1','http://127.0.0.1:8000/groups/5/deposit','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 05:36:54'),(112,1,'create',NULL,'POST /groups/5/members','127.0.0.1','http://127.0.0.1:8000/groups/5/members','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 05:38:02'),(113,1,'create',NULL,'POST /groups/5/deposit','127.0.0.1','http://127.0.0.1:8000/groups/5/deposit','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 05:38:22'),(114,1,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 05:38:33'),(115,9,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 05:38:49'),(116,9,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 05:38:49'),(117,9,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 06:33:10'),(118,1,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 06:33:14'),(119,1,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 06:33:14'),(120,1,'update','Clients','Updated client','127.0.0.1','http://127.0.0.1:8000/clients/2','PUT','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 07:04:34'),(121,1,'create','Clients','POST /clients/2/invite','127.0.0.1','http://127.0.0.1:8000/clients/2/invite','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 07:12:35'),(122,1,'create','Clients','POST /clients/2/invite','127.0.0.1','http://127.0.0.1:8000/clients/2/invite','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 07:14:38'),(123,1,'create','Clients','POST /clients/2/invite','127.0.0.1','http://127.0.0.1:8000/clients/2/invite','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 07:18:07'),(124,10,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 07:21:56'),(125,10,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 07:21:56'),(126,1,'update','Clients','Updated client','127.0.0.1','http://127.0.0.1:8000/clients/1','PUT','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 07:31:34'),(127,1,'create','Clients','POST /clients/1/invite','127.0.0.1','http://127.0.0.1:8000/clients/1/invite','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 07:31:43'),(128,1,'create','Clients','POST /clients/1/invite','127.0.0.1','http://127.0.0.1:8000/clients/1/invite','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 07:40:40'),(129,10,'create',NULL,'POST /password/reset','127.0.0.1','http://127.0.0.1:8000/password/reset','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 07:41:18'),(130,10,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 07:41:47'),(131,10,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 07:41:57'),(132,10,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 07:41:58'),(133,10,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 07:53:53'),(134,10,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 07:54:05'),(135,10,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 07:54:05'),(136,1,'create','Savings','Savings withdrawal','127.0.0.1','http://127.0.0.1:8000/savings/1/withdraw','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 08:24:47'),(137,1,'create','Savings','Savings withdrawal','127.0.0.1','http://127.0.0.1:8000/savings/2/withdraw','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 08:25:59'),(138,10,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:27:23'),(139,10,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:27:34'),(140,10,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:27:34'),(141,10,'create',NULL,'POST /client-portal/switch/2','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/2','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:30:39'),(142,10,'create',NULL,'POST /client-portal/switch/3','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/3','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:31:03'),(143,10,'create',NULL,'POST /client-portal/switch/4','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/4','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:31:15'),(144,10,'create',NULL,'POST /client-portal/switch/1','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/1','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:31:27'),(145,10,'create',NULL,'POST /client-portal/switch/2','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/2','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:31:41'),(146,10,'create',NULL,'POST /client-portal/switch/3','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/3','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:32:42'),(147,10,'create',NULL,'POST /client-portal/switch/4','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/4','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:32:55'),(148,10,'create',NULL,'POST /client-portal/switch/1','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/1','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:33:14'),(149,10,'create',NULL,'POST /client-portal/switch/3','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/3','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:37:13'),(150,10,'create',NULL,'POST /client-portal/switch/4','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/4','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:37:18'),(151,10,'create',NULL,'POST /client-portal/switch/2','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/2','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:37:22'),(152,10,'create',NULL,'POST /client-portal/switch/1','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/1','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:37:26'),(153,10,'create',NULL,'POST /client-portal/switch/4','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/4','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:38:35'),(154,10,'create',NULL,'POST /client-portal/switch/2','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/2','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:38:39'),(155,10,'create',NULL,'POST /client-portal/switch/1','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/1','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:38:42'),(156,10,'create',NULL,'POST /client-portal/switch/2','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/2','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:38:50'),(157,10,'create',NULL,'POST /client-portal/switch/3','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/3','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:38:59'),(158,10,'create',NULL,'POST /client-portal/switch/1','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/1','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:39:13'),(159,10,'create',NULL,'POST /client-portal/switch/2','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/2','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:39:35'),(160,10,'create',NULL,'POST /client-portal/switch/1','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/1','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:39:45'),(161,10,'create',NULL,'POST /client-portal/switch/2','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/2','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:40:03'),(162,10,'create',NULL,'POST /client-portal/switch/1','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/1','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:53:17'),(163,10,'create',NULL,'POST /client-portal/switch/3','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/3','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:54:06'),(164,10,'create',NULL,'POST /client-portal/switch/4','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/4','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:54:47'),(165,10,'create',NULL,'POST /client-portal/switch/2','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/2','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:55:01'),(166,10,'create',NULL,'POST /client-portal/switch/1','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/1','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:59:28'),(167,10,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:59:36'),(168,10,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:59:46'),(169,10,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 09:59:46'),(170,10,'create',NULL,'POST /client-portal/switch/2','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/2','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 10:00:00'),(171,10,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 10:00:06'),(172,10,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 10:00:15'),(173,10,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 10:00:16'),(174,10,'create',NULL,'POST /client-portal/switch/2','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/2','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 10:00:31'),(175,1,'create',NULL,'POST /groups','127.0.0.1','http://127.0.0.1:8000/groups','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 10:02:23'),(176,1,'create','Clients','Created a new client','127.0.0.1','http://127.0.0.1:8000/clients','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 10:10:26'),(177,1,'create',NULL,'POST /groups/7/members','127.0.0.1','http://127.0.0.1:8000/groups/7/members','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 10:16:54'),(178,1,'create',NULL,'POST /groups/7/members','127.0.0.1','http://127.0.0.1:8000/groups/7/members','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 10:17:40'),(179,1,'create',NULL,'POST /groups/7/members','127.0.0.1','http://127.0.0.1:8000/groups/7/members','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 10:18:20'),(180,1,'create',NULL,'POST /groups/7/deposit','127.0.0.1','http://127.0.0.1:8000/groups/7/deposit','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 10:19:14'),(181,10,'create',NULL,'POST /client-portal/switch/1','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/1','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 10:24:59'),(182,10,'create',NULL,'POST /client-portal/switch/2','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/2','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 10:25:13'),(183,1,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 11:03:48'),(184,11,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 11:03:58'),(185,11,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 11:03:58'),(186,11,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 11:07:34'),(187,1,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 11:07:37'),(188,1,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 11:07:37'),(189,1,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 11:07:53'),(190,12,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 11:08:14'),(191,12,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 11:08:14'),(192,12,'logout',NULL,'Logged out','127.0.0.1','http://127.0.0.1:8000/logout','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 11:16:37'),(193,1,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 11:16:40'),(194,1,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 11:16:40'),(195,1,'create','Clients','POST /clients/2/invite','127.0.0.1','http://127.0.0.1:8000/clients/2/invite','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 11:17:04'),(196,10,'create',NULL,'POST /password/reset','127.0.0.1','http://127.0.0.1:8000/password/reset','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 11:18:19'),(197,10,'create',NULL,'POST /client-portal/switch/1','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/1','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 11:18:34'),(198,10,'create',NULL,'POST /client-portal/switch/3','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/3','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 11:18:48'),(199,10,'create',NULL,'POST /client-portal/switch/4','127.0.0.1','http://127.0.0.1:8000/client-portal/switch/4','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-08 11:18:57'),(200,1,'login',NULL,'Logged in','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 14:12:52'),(201,1,'create',NULL,'POST /login','127.0.0.1','http://127.0.0.1:8000/login','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 14:12:52'),(202,1,'update',NULL,'PUT /groups/7','127.0.0.1','http://127.0.0.1:8000/groups/7','PUT','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 14:27:54'),(203,1,'create',NULL,'Ran data reconciliation','127.0.0.1','http://127.0.0.1:8000/settings/reconcile','POST','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-04-08 14:28:11');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
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
INSERT INTO `client_portal_users` VALUES (10,1),(10,2),(10,3),(10,4);
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (1,NULL,'CLT-000001','individual','Anne',NULL,'Avinyia',NULL,NULL,NULL,NULL,NULL,NULL,'Anne Avinyia','+256788176140',NULL,'kavinyia@gmail.com','Ntinda\r\nStretcher Road',NULL,NULL,'Ntinda',NULL,NULL,NULL,0,50000.00,0.00,'unpaid',NULL,NULL,NULL,NULL,NULL,3,'active','2026-02-01','2026-03-25 09:04:08','2026-04-08 07:31:34',NULL),(2,NULL,'CLT-000002','individual','Joshua',NULL,'Avinyia',NULL,NULL,NULL,NULL,NULL,NULL,'Joshua Avinyia','+256788176140',NULL,'kavinyia@gmail.com','Ntinda\r\nStretcher Road',NULL,NULL,'Ntinda','employed','savings',NULL,0,50000.00,0.00,'unpaid',NULL,NULL,NULL,NULL,NULL,3,'active','2026-01-01','2026-03-25 09:04:40','2026-04-08 07:04:34',NULL),(3,NULL,'CLT-000003','group','Safronite',NULL,'Group',NULL,NULL,NULL,NULL,NULL,NULL,'Safronite','0788176140',NULL,'kavinyia@gmail.com','Ntinda\r\nStretcher Road',NULL,NULL,NULL,NULL,NULL,NULL,0,250000.00,0.00,'unpaid',NULL,NULL,NULL,NULL,NULL,1,'active','2026-04-07','2026-04-07 12:31:55','2026-04-07 12:31:55',NULL),(4,NULL,'CLT-000004','group','BNI',NULL,'Group',NULL,NULL,NULL,NULL,NULL,NULL,'BNI','0788176140',NULL,'kavinyia@gmail.com','Ntinda\r\nStretcher Road',NULL,NULL,NULL,NULL,NULL,NULL,0,300000.00,0.00,'unpaid',NULL,NULL,NULL,NULL,NULL,1,'active','2026-04-07','2026-04-07 12:43:45','2026-04-07 12:43:45',NULL),(5,NULL,'CLT-000005','individual','James',NULL,'Opolot',NULL,NULL,NULL,NULL,NULL,NULL,'James Opolot',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,50000.00,0.00,'unpaid',NULL,NULL,NULL,NULL,NULL,1,'active','2026-04-07','2026-04-07 14:48:30','2026-04-08 02:38:17',NULL),(6,NULL,'CLT-000006','group','xxxxxxxxxxxx',NULL,'Group',NULL,NULL,NULL,NULL,NULL,NULL,'xxxxxxxxxxxx','0788176140',NULL,'xxx@gmail.com','Ntinda\r\nStretcher Road',NULL,NULL,NULL,NULL,NULL,NULL,0,0.00,0.00,'unpaid',NULL,NULL,NULL,NULL,NULL,1,'active',NULL,'2026-04-08 05:21:15','2026-04-08 05:21:15',NULL),(7,NULL,'CLT-000007','group','Women Of Purpose',NULL,'Group',NULL,NULL,NULL,NULL,NULL,NULL,'Women Of Purpose','0788176140',NULL,'woman@gmail.com','Ntinda\r\nStretcher Road',NULL,NULL,NULL,NULL,NULL,NULL,0,0.00,0.00,'unpaid',NULL,NULL,NULL,NULL,NULL,1,'active','2026-04-08','2026-04-08 05:32:27','2026-04-08 05:32:27',NULL),(8,NULL,'CLT-000008','group','Yukon Software Group',NULL,'Group',NULL,NULL,NULL,NULL,NULL,NULL,'Yukon Software Group','0788176140',NULL,'woman@gmail.com',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0.00,0.00,'unpaid',NULL,NULL,NULL,NULL,NULL,1,'active','2026-04-08','2026-04-08 10:10:26','2026-04-08 10:10:26',NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (1,'EMP-00001',2,'IT','TECH',700000.00,2,'active','No notes here',3,'2026-03-25 13:49:36','2026-03-25 13:51:08',NULL);
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fixed_deposits`
--

LOCK TABLES `fixed_deposits` WRITE;
/*!40000 ALTER TABLE `fixed_deposits` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `group_members`
--

LOCK TABLES `group_members` WRITE;
/*!40000 ALTER TABLE `group_members` DISABLE KEYS */;
INSERT INTO `group_members` VALUES (1,1,NULL,'Dee','0788176140',NULL,NULL,200000.00,0,4,'active',NULL,'2026-04-07 12:33:11','2026-04-07 12:46:43',NULL),(2,1,NULL,'feeeee','0788176140',NULL,NULL,200000.00,1,5,'active',NULL,'2026-04-07 12:33:54','2026-04-07 12:46:43',NULL),(3,1,NULL,'John','0788176140',NULL,NULL,200000.00,0,6,'active',NULL,'2026-04-07 12:37:56','2026-04-07 12:46:43',NULL),(4,3,NULL,'Kevin Avi','0788176140',NULL,'vggjjjj',300000.00,1,7,'active',1,'2026-04-07 14:28:01','2026-04-07 14:30:50',NULL),(5,3,NULL,'Debbie Avi','0788176140',NULL,'vvvgghuuu',200000.00,0,8,'active',1,'2026-04-07 14:28:51','2026-04-07 14:30:50',NULL),(6,4,NULL,'Joshua Avinyia','0788176140',NULL,'vvvgghuuu',2000000.00,0,NULL,'active',1,'2026-04-08 05:24:05','2026-04-08 05:24:25',NULL),(7,5,NULL,'Jane Opolot','0788176140',NULL,'cxcxxcxcx',300000.00,0,NULL,'active',1,'2026-04-08 05:35:39','2026-04-08 05:38:02',NULL),(8,5,NULL,'Ephraim Opolot','0788176140',NULL,NULL,400000.00,0,NULL,'active',1,'2026-04-08 05:36:35','2026-04-08 05:38:02',NULL),(9,5,NULL,'Alex Bob','0788176140',NULL,NULL,4000000.00,1,9,'active',1,'2026-04-08 05:38:02','2026-04-08 05:38:22',NULL),(10,7,NULL,'Mark Bright','0788176140',NULL,NULL,6666666.66,1,11,'active',1,'2026-04-08 10:16:54','2026-04-08 10:19:14',NULL),(11,7,NULL,'Tonny Bazirakye','0788176140',NULL,NULL,6666666.66,0,12,'active',1,'2026-04-08 10:17:39','2026-04-08 10:19:14',NULL),(12,7,NULL,'Michael Imakit','0788176140',NULL,NULL,6666666.68,0,13,'active',1,'2026-04-08 10:18:20','2026-04-08 10:19:14',NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `group_transactions`
--

LOCK TABLES `group_transactions` WRITE;
/*!40000 ALTER TABLE `group_transactions` DISABLE KEYS */;
INSERT INTO `group_transactions` VALUES (1,1,1,'deposit','individual',NULL,100000.00,0.00,100000.00,'2026-04-07','Group deposit',NULL,NULL,20,1,'2026-04-07 12:41:41','2026-04-07 12:41:41'),(2,1,1,'deposit','group_wide',NULL,300000.00,0.00,400000.00,'2026-04-07','Group deposit',NULL,NULL,21,1,'2026-04-07 12:42:19','2026-04-07 12:42:19'),(3,1,2,'deposit','group_wide',NULL,300000.00,0.00,300000.00,'2026-04-07','Group deposit',NULL,NULL,21,1,'2026-04-07 12:42:19','2026-04-07 12:42:19'),(4,1,3,'deposit','group_wide',NULL,300000.00,0.00,300000.00,'2026-04-07','Group deposit',NULL,NULL,21,1,'2026-04-07 12:42:19','2026-04-07 12:42:19'),(5,1,1,'withdrawal','group_wide','custom',200000.00,0.00,200000.00,'2026-04-07','Group withdrawal',NULL,NULL,22,1,'2026-04-07 12:46:43','2026-04-07 12:46:43'),(6,1,2,'withdrawal','group_wide','custom',100000.00,0.00,200000.00,'2026-04-07','Group withdrawal',NULL,NULL,22,1,'2026-04-07 12:46:43','2026-04-07 12:46:43'),(7,1,3,'withdrawal','group_wide','custom',100000.00,0.00,200000.00,'2026-04-07','Group withdrawal',NULL,NULL,22,1,'2026-04-07 12:46:43','2026-04-07 12:46:43'),(8,3,5,'deposit','custom',NULL,200000.00,0.00,200000.00,'2026-04-07',NULL,NULL,'Group deposit',23,1,'2026-04-07 14:29:20','2026-04-07 14:29:20'),(9,3,4,'deposit','custom',NULL,300000.00,0.00,300000.00,'2026-04-07',NULL,NULL,'Group deposit',23,1,'2026-04-07 14:29:20','2026-04-07 14:29:20'),(10,4,6,'deposit','individual',NULL,2000000.00,0.00,2000000.00,'2026-04-08',NULL,NULL,'Group deposit',27,1,'2026-04-08 05:24:25','2026-04-08 05:24:25'),(11,5,7,'deposit','equal_split',NULL,200000.00,0.00,200000.00,'2026-04-08',NULL,NULL,'Group-wide deposit',28,1,'2026-04-08 05:36:10','2026-04-08 05:36:10'),(12,5,8,'deposit','custom',NULL,400000.00,0.00,400000.00,'2026-04-08',NULL,NULL,'Group deposit',29,1,'2026-04-08 05:36:54','2026-04-08 05:36:54'),(13,5,7,'deposit','custom',NULL,100000.00,200000.00,300000.00,'2026-04-08',NULL,NULL,'Group deposit',29,1,'2026-04-08 05:36:54','2026-04-08 05:36:54'),(14,5,9,'deposit','individual',NULL,4000000.00,0.00,4000000.00,'2026-04-08',NULL,NULL,'Group deposit',30,1,'2026-04-08 05:38:22','2026-04-08 05:38:22'),(15,7,10,'deposit','equal_split',NULL,6666666.66,0.00,6666666.66,'2026-04-08',NULL,NULL,'Group-wide deposit',33,1,'2026-04-08 10:19:14','2026-04-08 10:19:14'),(16,7,11,'deposit','equal_split',NULL,6666666.66,0.00,6666666.66,'2026-04-08',NULL,NULL,'Group-wide deposit',33,1,'2026-04-08 10:19:14','2026-04-08 10:19:14'),(17,7,12,'deposit','equal_split',NULL,6666666.68,0.00,6666666.68,'2026-04-08',NULL,NULL,'Group-wide deposit',33,1,'2026-04-08 10:19:14','2026-04-08 10:19:14');
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `groups`
--

LOCK TABLES `groups` WRITE;
/*!40000 ALTER TABLE `groups` DISABLE KEYS */;
INSERT INTO `groups` VALUES (1,NULL,'GRP-000001','',NULL,250000.00,7.0000,NULL,'active',NULL,1,'2026-04-07 12:31:55','2026-04-07 12:31:55',NULL),(2,NULL,'GRP-000002','',NULL,300000.00,0.0000,NULL,'active',NULL,1,'2026-04-07 12:43:45','2026-04-07 12:43:45',NULL),(3,NULL,'GR00001','Avinyia Club House','2026-04-07',200000.00,7.0000,NULL,'active',NULL,1,'2026-04-07 14:26:53','2026-04-08 05:18:56',NULL),(4,NULL,'GR00002','vvvv','2026-04-08',240000.00,0.0000,NULL,'active',NULL,1,'2026-04-08 05:22:56','2026-04-08 05:22:56',NULL),(5,7,'GRP-000005','Women Of Purpose','2026-04-08',0.00,0.0000,NULL,'active',NULL,1,'2026-04-08 05:32:27','2026-04-08 05:32:27',NULL),(7,8,'GRP-000006','Yukon Software Group','2026-04-08',0.00,7.0000,NULL,'active',NULL,1,'2026-04-08 10:10:26','2026-04-08 14:27:54',NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_products`
--

LOCK TABLES `loan_products` WRITE;
/*!40000 ALTER TABLE `loan_products` DISABLE KEYS */;
INSERT INTO `loan_products` VALUES (1,'Normal Loans',33.6000,'flat',6,'monthly',0.0000,1000000.00,NULL,2,29,NULL,10,1,'2026-03-15 15:50:44','2026-03-15 15:50:44'),(2,'Quarterly Loan',24.0000,'reducing',51,'quarterly',0.0000,0.00,NULL,2,29,NULL,NULL,1,'2026-04-07 14:47:39','2026-04-07 14:47:39');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_repayments`
--

LOCK TABLES `loan_repayments` WRITE;
/*!40000 ALTER TABLE `loan_repayments` DISABLE KEYS */;
INSERT INTO `loan_repayments` VALUES (1,1,'2026-04-07',389333.00,333333.00,56000.00,0.00,'savings',NULL,1,17,NULL,'2026-04-07 09:39:31','2026-04-07 09:39:31'),(2,1,'2026-04-07',200000.00,166667.01,33332.99,0.00,'savings',NULL,1,19,NULL,'2026-04-07 10:03:13','2026-04-07 10:03:13');
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
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_schedules`
--

LOCK TABLES `loan_schedules` WRITE;
/*!40000 ALTER TABLE `loan_schedules` DISABLE KEYS */;
INSERT INTO `loan_schedules` VALUES (1,1,1,'2026-03-07',166666.67,28000.00,194666.67,833333.33,166666.67,28000.00,'paid','2026-04-07 09:34:37','2026-04-07 09:39:31'),(2,1,2,'2026-04-07',166666.67,28000.00,194666.67,666666.67,166666.67,28000.00,'paid','2026-04-07 09:34:37','2026-04-07 10:03:13'),(3,1,3,'2026-05-07',166666.67,28000.00,194666.67,500000.00,166666.67,28000.00,'paid','2026-04-07 09:34:37','2026-04-07 10:03:13'),(4,1,4,'2026-06-07',166666.67,28000.00,194666.67,333333.33,0.00,5332.99,'partial','2026-04-07 09:34:37','2026-04-07 10:03:13'),(5,1,5,'2026-07-07',166666.67,28000.00,194666.67,166666.67,0.00,0.00,'pending','2026-04-07 09:34:37','2026-04-07 09:34:37'),(6,1,6,'2026-08-07',166666.67,28000.00,194666.67,0.00,0.00,0.00,'pending','2026-04-07 09:34:37','2026-04-07 09:34:37'),(41,2,1,'2026-07-07',8861201.06,15000000.00,23861201.06,241138798.94,0.00,0.00,'pending','2026-04-07 15:39:07','2026-04-07 15:39:07'),(42,2,2,'2026-10-07',9392873.12,14468327.94,23861201.06,231745925.82,0.00,0.00,'pending','2026-04-07 15:39:07','2026-04-07 15:39:07'),(43,2,3,'2027-01-07',9956445.51,13904755.55,23861201.06,221789480.31,0.00,0.00,'pending','2026-04-07 15:39:07','2026-04-07 15:39:07'),(44,2,4,'2027-04-07',10553832.24,13307368.82,23861201.06,211235648.07,0.00,0.00,'pending','2026-04-07 15:39:07','2026-04-07 15:39:07'),(45,2,5,'2027-07-07',11187062.17,12674138.88,23861201.06,200048585.90,0.00,0.00,'pending','2026-04-07 15:39:07','2026-04-07 15:39:07'),(46,2,6,'2027-10-07',11858285.90,12002915.15,23861201.06,188190300.00,0.00,0.00,'pending','2026-04-07 15:39:07','2026-04-07 15:39:07'),(47,2,7,'2028-01-07',12569783.06,11291418.00,23861201.06,175620516.94,0.00,0.00,'pending','2026-04-07 15:39:07','2026-04-07 15:39:07'),(48,2,8,'2028-04-07',13323970.04,10537231.02,23861201.06,162296546.90,0.00,0.00,'pending','2026-04-07 15:39:07','2026-04-07 15:39:07'),(49,2,9,'2028-07-07',14123408.24,9737792.81,23861201.06,148173138.65,0.00,0.00,'pending','2026-04-07 15:39:07','2026-04-07 15:39:07'),(50,2,10,'2028-10-07',14970812.74,8890388.32,23861201.06,133202325.91,0.00,0.00,'pending','2026-04-07 15:39:07','2026-04-07 15:39:07'),(51,2,11,'2029-01-07',15869061.50,7992139.55,23861201.06,117333264.41,0.00,0.00,'pending','2026-04-07 15:39:07','2026-04-07 15:39:07'),(52,2,12,'2029-04-07',16821205.19,7039995.86,23861201.06,100512059.22,0.00,0.00,'pending','2026-04-07 15:39:07','2026-04-07 15:39:07'),(53,2,13,'2029-07-07',17830477.50,6030723.55,23861201.06,82681581.71,0.00,0.00,'pending','2026-04-07 15:39:07','2026-04-07 15:39:07'),(54,2,14,'2029-10-07',18900306.16,4960894.90,23861201.06,63781275.56,0.00,0.00,'pending','2026-04-07 15:39:07','2026-04-07 15:39:07'),(55,2,15,'2030-01-07',20034324.52,3826876.53,23861201.06,43746951.03,0.00,0.00,'pending','2026-04-07 15:39:07','2026-04-07 15:39:07'),(56,2,16,'2030-04-07',21236384.00,2624817.06,23861201.06,22510567.04,0.00,0.00,'pending','2026-04-07 15:39:07','2026-04-07 15:39:07'),(57,2,17,'2030-07-07',22510567.05,1350634.02,23861201.07,0.00,0.00,0.00,'pending','2026-04-07 15:39:07','2026-04-07 15:39:07');
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loans`
--

LOCK TABLES `loans` WRITE;
/*!40000 ALTER TABLE `loans` DISABLE KEYS */;
INSERT INTO `loans` VALUES (1,'LN-2026-00001',2,NULL,1,1000000.00,50000.00,0.00,'loan',30000.00,3.00,'loan',15000.00,1.50,'loan',NULL,33.6000,'flat',6,'monthly','2026-02-07','2026-08-07',499999.99,78667.01,0.00,'active',1,'2026-04-07 09:34:37',1,NULL,'2026-04-07 09:33:33','2026-04-07 10:03:13',NULL,NULL),(2,'LN-2026-00002',5,NULL,2,250000000.00,50000.00,0.00,'loan',3750000.00,1.50,'loan',3750000.00,1.50,'loan',NULL,24.0000,'reducing',51,'quarterly','2026-04-07','2030-07-07',250000000.00,155640417.96,0.00,'active',1,'2026-04-07 15:39:07',1,'lOAN','2026-04-07 14:49:53','2026-04-07 15:39:07',NULL,NULL),(3,'LN-2026-00003',5,NULL,2,250000000.00,0.00,0.00,'loan',0.00,1.50,'loan',0.00,1.50,'loan',NULL,24.0000,'reducing',69,'quarterly',NULL,NULL,0.00,0.00,0.00,'pending',NULL,NULL,1,NULL,'2026-04-07 15:41:40','2026-04-07 15:41:40',NULL,NULL);
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
  `status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_shares_share_number_unique` (`share_number`),
  KEY `member_shares_client_id_foreign` (`client_id`),
  KEY `member_shares_created_by_foreign` (`created_by`),
  CONSTRAINT `member_shares_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `member_shares_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `member_shares`
--

LOCK TABLES `member_shares` WRITE;
/*!40000 ALTER TABLE `member_shares` DISABLE KEYS */;
INSERT INTO `member_shares` VALUES (1,1,'SHR-2026-00001',100000.00,0.00,'unpaid',NULL,3,'2026-03-25 09:04:08','2026-03-25 09:04:08'),(2,2,'SHR-2026-00002',100000.00,0.00,'unpaid',NULL,3,'2026-03-25 09:04:40','2026-03-25 09:04:40'),(3,5,'SHR-2026-00003',100000.00,0.00,'unpaid',NULL,1,'2026-04-07 14:48:30','2026-04-07 14:48:30');
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
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2014_10_12_000000_create_users_table',1),(2,'2014_10_12_100000_create_password_resets_table',1),(3,'2019_08_19_000000_create_failed_jobs_table',1),(4,'2019_12_14_000001_create_personal_access_tokens_table',1),(5,'2024_01_01_000001_create_clients_table',1),(6,'2024_01_01_000002_create_accounts_table',1),(7,'2024_01_01_000003_create_transactions_table',1),(8,'2024_01_01_000004_create_transaction_lines_table',1),(9,'2024_01_01_000005_create_loan_products_table',1),(10,'2024_01_01_000006_create_loans_table',1),(11,'2024_01_01_000007_create_loan_schedules_table',1),(12,'2024_01_01_000008_create_loan_repayments_table',1),(13,'2024_01_01_000009_create_savings_products_table',1),(14,'2024_01_01_000010_create_savings_accounts_table',1),(15,'2024_01_01_000011_create_savings_transactions_table',1),(16,'2024_01_01_000012_create_fixed_deposit_products_table',1),(17,'2024_01_01_000013_create_fixed_deposits_table',1),(18,'2024_01_02_000001_create_branches_table',2),(19,'2024_01_02_000002_create_loan_guarantors_table',2),(20,'2024_01_02_000003_create_system_settings_table',2),(21,'2026_03_10_111812_create_permission_tables',2),(22,'2024_01_02_000004_add_columns_to_users_table',3),(23,'2026_03_10_175804_add_reversal_columns_to_transactions_table',4),(24,'2026_03_11_091053_add_savings_account_to_fixed_deposits_table',5),(25,'2026_03_11_130838_add_personal_fields_to_clients_table',6),(26,'2026_03_11_132055_add_membership_fields_to_clients_table',7),(27,'2026_03_12_094141_add_broken_status_to_fixed_deposits',8),(28,'2026_03_12_094351_add_broken_status_to_fixed_deposits',8),(29,'2026_03_12_115600_add_membership_fee_to_clients_table',9),(30,'2026_03_12_115612_create_member_shares_table',9),(31,'2026_03_12_115746_add_membership_fee_income_account',10),(32,'2026_03_12_131503_add_fees_to_loans_table',11),(33,'2026_03_12_131544_add_loan_fee_income_accounts',12),(34,'2026_03_13_000001_add_fee_rates_to_loans_table',13),(35,'2026_03_13_000002_add_loan_insurance_payable_account',13),(36,'2026_03_13_000003_add_application_fee_to_loans_table',14),(37,'2026_03_13_000004_add_per_fee_methods_to_loans_table',15),(38,'2026_03_13_100001_create_employees_table',16),(39,'2026_03_13_100002_create_payroll_runs_table',16),(40,'2026_03_13_100003_create_payroll_items_table',16),(41,'2026_03_13_100004_add_salary_expense_account',16),(42,'2026_03_13_100005_add_client_id_to_employees_table',17),(43,'2026_03_13_200001_add_joining_date_to_clients_table',18),(44,'2026_03_24_000001_create_audit_logs_table',19),(45,'2026_03_24_000002_add_last_interest_date_to_savings_accounts',20),(46,'2026_04_04_100001_create_groups_table',21),(47,'2026_04_04_100002_create_group_members_table',21),(48,'2026_04_04_100003_create_group_transactions_table',21),(49,'2026_04_07_100000_add_client_type_to_clients_table',21),(50,'2026_04_07_100002_add_group_gl_accounts',22),(51,'2026_04_07_100011_align_groups_tables_for_client_link',22),(52,'2026_04_07_200001_fix_groups_schema_to_original_plan',23),(53,'2026_04_07_173604_add_repayment_frequency_to_loan_tables',24),(54,'2026_04_08_000001_add_client_id_to_users_table',25),(55,'2026_04_08_000002_add_client_id_to_group_members_table',26),(56,'2026_04_08_000003_add_client_id_to_groups_table',27),(57,'2026_04_08_103658_create_client_portal_users_table',28);
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
INSERT INTO `model_has_roles` VALUES (1,'App\\Models\\User',1),(3,'App\\Models\\User',2),(3,'App\\Models\\User',3),(5,'App\\Models\\User',5),(5,'App\\Models\\User',7),(5,'App\\Models\\User',9),(5,'App\\Models\\User',11),(6,'App\\Models\\User',4),(6,'App\\Models\\User',6),(6,'App\\Models\\User',8),(6,'App\\Models\\User',12),(6,'App\\Models\\User',13),(7,'App\\Models\\User',10);
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_items`
--

LOCK TABLES `payroll_items` WRITE;
/*!40000 ALTER TABLE `payroll_items` DISABLE KEYS */;
INSERT INTO `payroll_items` VALUES (1,1,1,2,700000.00,0.00,0.00,700000.00,'2026-03-25 13:54:30','2026-03-25 13:54:30');
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_runs`
--

LOCK TABLES `payroll_runs` WRITE;
/*!40000 ALTER TABLE `payroll_runs` DISABLE KEYS */;
INSERT INTO `payroll_runs` VALUES (1,'PAY-202603-001',3,2026,NULL,700000.00,'processed',3,'2026-03-25 13:54:58',3,'2026-03-25 13:54:30','2026-03-25 13:54:58');
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
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'view dashboard','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(2,'view clients','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(3,'create clients','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(4,'edit clients','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(5,'delete clients','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(6,'view accounts','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(7,'create accounts','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(8,'edit accounts','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(9,'view transactions','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(10,'create transactions','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(11,'view loan-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(12,'create loan-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(13,'edit loan-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(14,'view loans','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(15,'create loans','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(16,'disburse loans','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(17,'repay loans','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(18,'view savings-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(19,'create savings-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(20,'edit savings-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(21,'view savings','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(22,'create savings','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(23,'deposit savings','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(24,'withdraw savings','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(25,'transfer savings','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(26,'view fd-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(27,'create fd-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(28,'edit fd-products','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(29,'view fixed-deposits','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(30,'create fixed-deposits','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(31,'mature fixed-deposits','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(32,'use teller','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(33,'view reports','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(34,'manage branches','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(35,'manage users','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(36,'manage settings','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(37,'manage backup','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(38,'view groups','web','2026-04-07 12:29:03','2026-04-07 12:29:03'),(39,'manage groups','web','2026-04-07 12:29:03','2026-04-07 12:29:03');
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
INSERT INTO `role_has_permissions` VALUES (1,1),(1,2),(1,3),(1,4),(2,1),(2,2),(2,3),(2,4),(3,1),(3,2),(3,3),(4,1),(4,2),(4,3),(5,1),(5,2),(5,3),(6,1),(6,2),(6,3),(6,4),(7,1),(7,2),(8,1),(8,2),(9,1),(9,2),(9,3),(10,1),(10,2),(10,3),(11,1),(11,2),(11,4),(12,1),(12,2),(13,1),(13,2),(14,1),(14,2),(14,3),(14,4),(15,1),(15,2),(15,3),(16,1),(16,2),(16,3),(17,1),(17,2),(17,3),(18,1),(18,2),(18,4),(19,1),(19,2),(20,1),(20,2),(21,1),(21,2),(21,3),(21,4),(22,1),(22,2),(22,3),(23,1),(23,2),(23,3),(24,1),(24,2),(24,3),(25,1),(25,2),(25,3),(26,1),(26,2),(26,4),(27,1),(27,2),(28,1),(28,2),(29,1),(29,2),(29,3),(29,4),(30,1),(30,2),(30,3),(31,1),(31,2),(31,3),(32,1),(32,2),(32,3),(33,1),(33,2),(33,3),(33,4),(34,1),(34,2),(35,1),(35,2),(36,1),(36,2),(37,1),(38,1),(38,2),(38,3),(38,4),(39,1),(39,2),(39,3);
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'super_admin','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(2,'admin','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(3,'cashier','web','2026-03-10 08:46:06','2026-03-10 08:46:06'),(4,'staff','web','2026-03-10 08:46:07','2026-03-10 08:46:07'),(5,'group_leader','web','2026-04-07 12:29:04','2026-04-07 12:29:04'),(6,'group_member','web','2026-04-07 12:29:04','2026-04-07 12:29:04'),(7,'client','web','2026-04-08 02:14:59','2026-04-08 02:14:59');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `savings_accounts`
--

LOCK TABLES `savings_accounts` WRITE;
/*!40000 ALTER TABLE `savings_accounts` DISABLE KEYS */;
INSERT INTO `savings_accounts` VALUES (1,1,NULL,2,'SAV2600001',5387121.42,'active','2026-02-01','2026-03-24',3,'2026-03-25 09:05:04','2026-04-08 08:24:47',NULL),(2,2,NULL,2,'SAV2600002',6437501.97,'active','2026-01-01','2026-03-24',3,'2026-03-25 09:17:11','2026-04-08 08:25:59',NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `savings_transactions`
--

LOCK TABLES `savings_transactions` WRITE;
/*!40000 ALTER TABLE `savings_transactions` DISABLE KEYS */;
INSERT INTO `savings_transactions` VALUES (1,1,'deposit',5000000.00,0.00,5000000.00,'2026-02-01','RCT-001','Cash Deposit',1,3,'2026-03-25 09:05:30','2026-03-25 09:05:30'),(2,1,'deposit',34246.58,5000000.00,5034246.58,'2026-02-25','INT-20260325120600','Interest credit (01 Feb 2026 – 25 Feb 2026)',2,3,'2026-03-25 09:06:00','2026-03-25 09:06:00'),(3,1,'deposit',72874.84,5034246.58,5107121.42,'2026-03-25','INT-20260325121231','Interest credit (01 Feb 2026 – 25 Mar 2026)',3,3,'2026-03-25 09:12:31','2026-03-25 09:12:31'),(4,2,'deposit',2000000.00,0.00,2000000.00,'2025-12-29','RCT-002','Cash Deposit',4,3,'2026-03-25 09:17:45','2026-03-25 09:17:45'),(5,2,'deposit',3000000.00,2000000.00,5000000.00,'2026-01-24','RCT-001','Cash Deposit',5,3,'2026-03-25 09:18:06','2026-03-25 09:18:06'),(6,2,'deposit',1000000.00,5000000.00,6000000.00,'2026-02-18','TXN-20260325-0006','Cash Deposit',6,3,'2026-03-25 09:18:22','2026-03-25 09:18:22'),(7,2,'deposit',500000.00,6000000.00,6500000.00,'2026-03-08','IT','Cash Deposit',7,3,'2026-03-25 09:18:42','2026-03-25 09:18:42'),(8,2,'deposit',16986.30,5000000.00,5016986.30,'2026-01-25','INT-20260325121905','Interest credit (29 Dec 2025 – 25 Jan 2026)',8,3,'2026-03-25 09:19:05','2026-03-25 09:19:05'),(9,1,'deposit',34255.96,5034246.58,5068502.54,'2026-02-25','INT-20260325122038','Interest credit (01 Feb 2026 – 25 Feb 2026)',9,3,'2026-03-25 09:20:38','2026-03-25 09:20:38'),(10,2,'deposit',44764.57,6000000.00,6044764.57,'2026-02-25','INT-20260325122038','Interest credit (26 Jan 2026 – 25 Feb 2026)',10,3,'2026-03-25 09:20:38','2026-03-25 09:20:38'),(11,1,'deposit',37493.03,5068502.54,5105995.57,'2026-03-24','INT-20260325122228','Interest credit (26 Feb 2026 – 24 Mar 2026)',11,3,'2026-03-25 09:22:28','2026-03-25 09:22:28'),(12,2,'deposit',46834.97,6500000.00,6546834.97,'2026-03-24','INT-20260325122228','Interest credit (26 Feb 2026 – 24 Mar 2026)',12,3,'2026-03-25 09:22:28','2026-03-25 09:22:28'),(13,1,'deposit',600000.00,5107121.42,5707121.42,'2026-03-25','RCT-002','Cash Deposit',13,3,'2026-03-25 13:47:15','2026-03-25 13:47:15'),(14,2,'deposit',700000.00,6546834.97,7246834.97,'2026-03-25',NULL,'Salary — PAY-202603-001 (March 2026)',NULL,3,'2026-03-25 13:54:58','2026-03-25 13:54:58'),(15,2,'withdrawal',389333.00,7246834.97,6857501.97,'2026-04-07','TXN-20260407-0002','Loan repayment - LN-2026-00001',16,1,'2026-04-07 09:39:31','2026-04-07 09:39:31'),(16,2,'withdrawal',200000.00,6857501.97,6657501.97,'2026-04-07','TXN-20260407-0004','Loan repayment - LN-2026-00001',18,1,'2026-04-07 10:03:13','2026-04-07 10:03:13'),(17,1,'withdrawal',320000.00,5707121.42,5387121.42,'2026-04-08','TXN-20260408-0005','Cash Withdrawal',31,1,'2026-04-08 08:24:47','2026-04-08 08:24:47'),(18,2,'withdrawal',220000.00,6657501.97,6437501.97,'2026-04-08','TXN-20260408-0006','Cash Withdrawal',32,1,'2026-04-08 08:25:59','2026-04-08 08:25:59');
/*!40000 ALTER TABLE `savings_transactions` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'org_name','Konnect Initiatives Ltd','general','Organisation Name','text','2026-03-10 08:19:32','2026-04-08 02:33:59'),(2,'org_address','123 Finance Street','general','Address','textarea','2026-03-10 08:19:32','2026-03-10 08:19:32'),(3,'org_phone','+254 700 000 000','general','Phone','text','2026-03-10 08:19:32','2026-03-10 08:19:32'),(4,'org_email','info@konnectinitiatives.com','general','Email','text','2026-03-10 08:19:32','2026-03-15 16:32:47'),(5,'currency','UGX','general','Currency Code','text','2026-03-10 08:19:32','2026-03-10 09:33:02'),(6,'currency_symbol','UGX','general','Currency Symbol','text','2026-03-10 08:19:32','2026-03-10 09:33:02'),(7,'decimal_places','0','general','Decimal Places','number','2026-03-10 08:19:32','2026-03-15 16:32:47'),(8,'financial_year_start','01-01','general','Financial Year Start (MM-DD)','text','2026-03-10 08:19:32','2026-03-10 08:19:32'),(9,'penalty_grace_days','0','loans','Penalty Grace Days','number','2026-03-10 08:19:32','2026-03-10 08:19:32'),(10,'backup_path','backups','system','Backup Storage Path','text','2026-03-10 08:19:32','2026-03-10 08:19:32'),(11,'max_loan_per_client','3','loans','Max Active Loans Per Client','number','2026-03-10 08:19:32','2026-03-10 08:19:32');
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
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction_lines_transaction_id_foreign` (`transaction_id`),
  KEY `transaction_lines_account_id_foreign` (`account_id`),
  CONSTRAINT `transaction_lines_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `transaction_lines_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaction_lines`
--

LOCK TABLES `transaction_lines` WRITE;
/*!40000 ALTER TABLE `transaction_lines` DISABLE KEYS */;
INSERT INTO `transaction_lines` VALUES (1,1,10,5000000.00,0.00,'Cash Deposit','2026-03-25 09:05:30','2026-03-25 09:05:30'),(2,1,21,0.00,5000000.00,'Savings liability - SAV2600001','2026-03-25 09:05:30','2026-03-25 09:05:30'),(3,2,10,34246.58,0.00,'Interest credit (01 Feb 2026 – 25 Feb 2026)','2026-03-25 09:06:00','2026-03-25 09:06:00'),(4,2,21,0.00,34246.58,'Savings liability - SAV2600001','2026-03-25 09:06:00','2026-03-25 09:06:00'),(5,3,10,72874.84,0.00,'Interest credit (01 Feb 2026 – 25 Mar 2026)','2026-03-25 09:12:31','2026-03-25 09:12:31'),(6,3,21,0.00,72874.84,'Savings liability - SAV2600001','2026-03-25 09:12:31','2026-03-25 09:12:31'),(7,4,10,2000000.00,0.00,'Cash Deposit','2026-03-25 09:17:45','2026-03-25 09:17:45'),(8,4,21,0.00,2000000.00,'Savings liability - SAV2600002','2026-03-25 09:17:45','2026-03-25 09:17:45'),(9,5,10,3000000.00,0.00,'Cash Deposit','2026-03-25 09:18:06','2026-03-25 09:18:06'),(10,5,21,0.00,3000000.00,'Savings liability - SAV2600002','2026-03-25 09:18:06','2026-03-25 09:18:06'),(11,6,10,1000000.00,0.00,'Cash Deposit','2026-03-25 09:18:22','2026-03-25 09:18:22'),(12,6,21,0.00,1000000.00,'Savings liability - SAV2600002','2026-03-25 09:18:22','2026-03-25 09:18:22'),(13,7,10,500000.00,0.00,'Cash Deposit','2026-03-25 09:18:42','2026-03-25 09:18:42'),(14,7,21,0.00,500000.00,'Savings liability - SAV2600002','2026-03-25 09:18:42','2026-03-25 09:18:42'),(15,8,10,16986.30,0.00,'Interest credit (29 Dec 2025 – 25 Jan 2026)','2026-03-25 09:19:05','2026-03-25 09:19:05'),(16,8,21,0.00,16986.30,'Savings liability - SAV2600002','2026-03-25 09:19:05','2026-03-25 09:19:05'),(17,9,10,34255.96,0.00,'Interest credit (01 Feb 2026 – 25 Feb 2026)','2026-03-25 09:20:38','2026-03-25 09:20:38'),(18,9,21,0.00,34255.96,'Savings liability - SAV2600001','2026-03-25 09:20:38','2026-03-25 09:20:38'),(19,10,10,44764.57,0.00,'Interest credit (26 Jan 2026 – 25 Feb 2026)','2026-03-25 09:20:38','2026-03-25 09:20:38'),(20,10,21,0.00,44764.57,'Savings liability - SAV2600002','2026-03-25 09:20:38','2026-03-25 09:20:38'),(21,11,10,37493.03,0.00,'Interest credit (26 Feb 2026 – 24 Mar 2026)','2026-03-25 09:22:28','2026-03-25 09:22:28'),(22,11,21,0.00,37493.03,'Savings liability - SAV2600001','2026-03-25 09:22:28','2026-03-25 09:22:28'),(23,12,10,46834.97,0.00,'Interest credit (26 Feb 2026 – 24 Mar 2026)','2026-03-25 09:22:28','2026-03-25 09:22:28'),(24,12,21,0.00,46834.97,'Savings liability - SAV2600002','2026-03-25 09:22:28','2026-03-25 09:22:28'),(25,13,10,600000.00,0.00,'Cash Deposit','2026-03-25 13:47:15','2026-03-25 13:47:15'),(26,13,21,0.00,600000.00,'Savings liability - SAV2600001','2026-03-25 13:47:15','2026-03-25 13:47:15'),(27,14,36,700000.00,0.00,'Salary expense — PAY-202603-001','2026-03-25 13:54:58','2026-03-25 13:54:58'),(28,14,21,0.00,700000.00,'Salary credited to savings — PAY-202603-001','2026-03-25 13:54:58','2026-03-25 13:54:58'),(29,15,2,1000000.00,0.00,'Loan receivable - LN-2026-00001','2026-04-07 09:34:37','2026-04-07 09:34:37'),(30,15,10,0.00,905000.00,'Cash disbursement (net of loan fees) - LN-2026-00001','2026-04-07 09:34:37','2026-04-07 09:34:37'),(31,15,49,0.00,50000.00,'Application fee - LN-2026-00001','2026-04-07 09:34:37','2026-04-07 09:34:37'),(32,15,46,0.00,30000.00,'Management fee - LN-2026-00001','2026-04-07 09:34:37','2026-04-07 09:34:37'),(33,15,48,0.00,15000.00,'Insurance payable - LN-2026-00001','2026-04-07 09:34:37','2026-04-07 09:34:37'),(34,16,21,389333.00,0.00,'Savings withdrawal - SAV2600002','2026-04-07 09:39:31','2026-04-07 09:39:31'),(35,16,10,0.00,389333.00,'Loan repayment - LN-2026-00001','2026-04-07 09:39:31','2026-04-07 09:39:31'),(36,17,10,389333.00,0.00,'Loan repayment - LN-2026-00001','2026-04-07 09:39:31','2026-04-07 09:39:31'),(37,17,2,0.00,333333.00,'Principal repayment - LN-2026-00001','2026-04-07 09:39:31','2026-04-07 09:39:31'),(38,17,29,0.00,56000.00,'Interest income - LN-2026-00001','2026-04-07 09:39:31','2026-04-07 09:39:31'),(39,18,21,200000.00,0.00,'Savings withdrawal - SAV2600002','2026-04-07 10:03:13','2026-04-07 10:03:13'),(40,18,10,0.00,200000.00,'Loan repayment - LN-2026-00001','2026-04-07 10:03:13','2026-04-07 10:03:13'),(41,19,10,200000.00,0.00,'Loan repayment - LN-2026-00001','2026-04-07 10:03:13','2026-04-07 10:03:13'),(42,19,2,0.00,166667.01,'Principal repayment - LN-2026-00001','2026-04-07 10:03:13','2026-04-07 10:03:13'),(43,19,29,0.00,33332.99,'Interest income - LN-2026-00001','2026-04-07 10:03:13','2026-04-07 10:03:13'),(44,20,10,100000.00,0.00,'Group deposit','2026-04-07 12:41:41','2026-04-07 12:41:41'),(45,20,50,0.00,100000.00,'Group member savings — Dee','2026-04-07 12:41:41','2026-04-07 12:41:41'),(46,21,10,900000.00,0.00,'Group deposit','2026-04-07 12:42:19','2026-04-07 12:42:19'),(47,21,50,0.00,900000.00,'Group member savings — pooled','2026-04-07 12:42:19','2026-04-07 12:42:19'),(48,22,50,400000.00,0.00,'Group savings payout','2026-04-07 12:46:43','2026-04-07 12:46:43'),(49,22,10,0.00,400000.00,'Group withdrawal','2026-04-07 12:46:43','2026-04-07 12:46:43'),(50,23,10,500000.00,0.00,'Group deposit','2026-04-07 14:29:20','2026-04-07 14:29:20'),(51,23,50,0.00,500000.00,'Group member savings','2026-04-07 14:29:20','2026-04-07 14:29:20'),(54,26,2,250000000.00,0.00,'Loan receivable - LN-2026-00002','2026-04-07 15:39:07','2026-04-07 15:39:07'),(55,26,10,0.00,242450000.00,'Cash disbursement (net of loan fees) - LN-2026-00002','2026-04-07 15:39:07','2026-04-07 15:39:07'),(56,26,49,0.00,50000.00,'Application fee - LN-2026-00002','2026-04-07 15:39:07','2026-04-07 15:39:07'),(57,26,46,0.00,3750000.00,'Management fee - LN-2026-00002','2026-04-07 15:39:07','2026-04-07 15:39:07'),(58,26,48,0.00,3750000.00,'Insurance payable - LN-2026-00002','2026-04-07 15:39:07','2026-04-07 15:39:07'),(59,27,10,2000000.00,0.00,'Group deposit','2026-04-08 05:24:25','2026-04-08 05:24:25'),(60,27,50,0.00,2000000.00,'Group member savings — Joshua Avinyia','2026-04-08 05:24:25','2026-04-08 05:24:25'),(61,28,10,200000.00,0.00,'Group-wide deposit','2026-04-08 05:36:10','2026-04-08 05:36:10'),(62,28,50,0.00,200000.00,'Group member savings — pooled','2026-04-08 05:36:10','2026-04-08 05:36:10'),(63,29,10,500000.00,0.00,'Group deposit','2026-04-08 05:36:54','2026-04-08 05:36:54'),(64,29,50,0.00,500000.00,'Group member savings','2026-04-08 05:36:54','2026-04-08 05:36:54'),(65,30,10,4000000.00,0.00,'Group deposit','2026-04-08 05:38:22','2026-04-08 05:38:22'),(66,30,50,0.00,4000000.00,'Group member savings — Alex Bob','2026-04-08 05:38:22','2026-04-08 05:38:22'),(67,31,21,300000.00,0.00,'Savings withdrawal - SAV2600001','2026-04-08 08:24:47','2026-04-08 08:24:47'),(68,31,10,0.00,300000.00,'Cash Withdrawal','2026-04-08 08:24:47','2026-04-08 08:24:47'),(69,31,21,20000.00,0.00,'Withdrawal fee','2026-04-08 08:24:47','2026-04-08 08:24:47'),(70,31,30,0.00,20000.00,'Withdrawal fee income','2026-04-08 08:24:47','2026-04-08 08:24:47'),(71,32,21,200000.00,0.00,'Savings withdrawal - SAV2600002','2026-04-08 08:25:59','2026-04-08 08:25:59'),(72,32,10,0.00,200000.00,'Cash Withdrawal','2026-04-08 08:25:59','2026-04-08 08:25:59'),(73,32,21,20000.00,0.00,'Withdrawal fee','2026-04-08 08:25:59','2026-04-08 08:25:59'),(74,32,30,0.00,20000.00,'Withdrawal fee income','2026-04-08 08:25:59','2026-04-08 08:25:59'),(75,33,10,20000000.00,0.00,'Group-wide deposit','2026-04-08 10:19:14','2026-04-08 10:19:14'),(76,33,50,0.00,20000000.00,'Group member savings — pooled','2026-04-08 10:19:14','2026-04-08 10:19:14');
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
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` VALUES (1,'2026-02-01','TXN-20260325-0001','Savings deposit - SAV2600001','savings',1,3,NULL,NULL,NULL,'2026-03-25 09:05:30','2026-03-25 09:05:30'),(2,'2026-02-25','TXN-20260325-0002','Savings deposit - SAV2600001','savings',1,3,NULL,NULL,NULL,'2026-03-25 09:06:00','2026-03-25 09:06:00'),(3,'2026-03-25','TXN-20260325-0003','Savings deposit - SAV2600001','savings',1,3,NULL,NULL,NULL,'2026-03-25 09:12:31','2026-03-25 09:12:31'),(4,'2025-12-29','TXN-20260325-0004','Savings deposit - SAV2600002','savings',2,3,NULL,NULL,NULL,'2026-03-25 09:17:45','2026-03-25 09:17:45'),(5,'2026-01-24','TXN-20260325-0005','Savings deposit - SAV2600002','savings',2,3,NULL,NULL,NULL,'2026-03-25 09:18:06','2026-03-25 09:18:06'),(6,'2026-02-18','TXN-20260325-0006','Savings deposit - SAV2600002','savings',2,3,NULL,NULL,NULL,'2026-03-25 09:18:22','2026-03-25 09:18:22'),(7,'2026-03-08','TXN-20260325-0007','Savings deposit - SAV2600002','savings',2,3,NULL,NULL,NULL,'2026-03-25 09:18:42','2026-03-25 09:18:42'),(8,'2026-01-25','TXN-20260325-0008','Savings deposit - SAV2600002','savings',2,3,NULL,NULL,NULL,'2026-03-25 09:19:05','2026-03-25 09:19:05'),(9,'2026-02-25','TXN-20260325-0009','Savings deposit - SAV2600001','savings',1,3,NULL,NULL,NULL,'2026-03-25 09:20:38','2026-03-25 09:20:38'),(10,'2026-02-25','TXN-20260325-0010','Savings deposit - SAV2600002','savings',2,3,NULL,NULL,NULL,'2026-03-25 09:20:38','2026-03-25 09:20:38'),(11,'2026-03-24','TXN-20260325-0011','Savings deposit - SAV2600001','savings',1,3,NULL,NULL,NULL,'2026-03-25 09:22:28','2026-03-25 09:22:28'),(12,'2026-03-24','TXN-20260325-0012','Savings deposit - SAV2600002','savings',2,3,NULL,NULL,NULL,'2026-03-25 09:22:28','2026-03-25 09:22:28'),(13,'2026-03-25','TXN-20260325-0013','Savings deposit - SAV2600001','savings',1,3,NULL,NULL,NULL,'2026-03-25 13:47:15','2026-03-25 13:47:15'),(14,'2026-03-25','TXN-20260325-0014','Payroll: PAY-202603-001 — March 2026','payroll',1,3,NULL,NULL,NULL,'2026-03-25 13:54:58','2026-03-25 13:54:58'),(15,'2026-02-07','TXN-20260407-0001','Loan disbursement: LN-2026-00001','loan',1,1,NULL,NULL,NULL,'2026-04-07 09:34:37','2026-04-07 09:34:37'),(16,'2026-04-07','TXN-20260407-0002','Savings withdrawal - SAV2600002','savings',2,1,NULL,NULL,NULL,'2026-04-07 09:39:31','2026-04-07 09:39:31'),(17,'2026-04-07','TXN-20260407-0003','Loan repayment: LN-2026-00001','loan',1,1,NULL,NULL,NULL,'2026-04-07 09:39:31','2026-04-07 09:39:31'),(18,'2026-04-07','TXN-20260407-0004','Savings withdrawal - SAV2600002','savings',2,1,NULL,NULL,NULL,'2026-04-07 10:03:13','2026-04-07 10:03:13'),(19,'2026-04-07','TXN-20260407-0005','Loan repayment: LN-2026-00001','loan',1,1,NULL,NULL,NULL,'2026-04-07 10:03:13','2026-04-07 10:03:13'),(20,'2026-04-07','TXN-20260407-0006','Group deposit — Safronite — Dee','groups',1,1,NULL,NULL,NULL,'2026-04-07 12:41:41','2026-04-07 12:41:41'),(21,'2026-04-07','TXN-20260407-0007','Group-wide deposit — Safronite','groups',1,1,NULL,NULL,NULL,'2026-04-07 12:42:19','2026-04-07 12:42:19'),(22,'2026-04-07','TXN-20260407-0008','Group-wide withdrawal — Safronite (custom)','groups',1,1,NULL,NULL,NULL,'2026-04-07 12:46:43','2026-04-07 12:46:43'),(23,'2026-04-07','TXN-20260407-0009','Group deposit (custom) — Club House','groups',3,1,NULL,NULL,NULL,'2026-04-07 14:29:20','2026-04-07 14:29:20'),(26,'2026-04-07','TXN-20260407-0010','Loan disbursement: LN-2026-00002','loan',2,1,NULL,NULL,NULL,'2026-04-07 15:39:07','2026-04-07 15:39:07'),(27,'2026-04-08','TXN-20260408-0001','Group deposit — vvvv — Joshua Avinyia','groups',4,1,NULL,NULL,NULL,'2026-04-08 05:24:25','2026-04-08 05:24:25'),(28,'2026-04-08','TXN-20260408-0002','Group-wide deposit — Women Of Purpose','groups',5,1,NULL,NULL,NULL,'2026-04-08 05:36:10','2026-04-08 05:36:10'),(29,'2026-04-08','TXN-20260408-0003','Group deposit (custom) — Women Of Purpose','groups',5,1,NULL,NULL,NULL,'2026-04-08 05:36:54','2026-04-08 05:36:54'),(30,'2026-04-08','TXN-20260408-0004','Group deposit — Women Of Purpose — Alex Bob','groups',5,1,NULL,NULL,NULL,'2026-04-08 05:38:22','2026-04-08 05:38:22'),(31,'2026-04-08','TXN-20260408-0005','Savings withdrawal - SAV2600001','savings',1,1,NULL,NULL,NULL,'2026-04-08 08:24:47','2026-04-08 08:24:47'),(32,'2026-04-08','TXN-20260408-0006','Savings withdrawal - SAV2600002','savings',2,1,NULL,NULL,NULL,'2026-04-08 08:25:59','2026-04-08 08:25:59'),(33,'2026-04-08','TXN-20260408-0007','Group-wide deposit — Yukon Software Group','groups',7,1,NULL,NULL,NULL,'2026-04-08 10:19:14','2026-04-08 10:19:14');
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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,NULL,NULL,'System Administrator',NULL,1,'admin@eltech.local',NULL,'$2y$10$we8mKIwDE7I9QwirFul5s./S1aYYjVEd1N9cbFb.VFuaHuYVZ0S5m',NULL,'2026-03-10 08:46:11','2026-03-10 08:46:11'),(3,NULL,NULL,'Anne Avinyia','0787109483',1,'avinyias@gmail.com',NULL,'$2y$10$4IlKp34a5NCKBoBA8pRmv.HAY9LS1NnPmBXwaDMZiOy0lX8epDtua',NULL,'2026-03-25 09:02:48','2026-03-25 09:02:48'),(4,NULL,NULL,'Dee',NULL,1,'dee@gmail.com',NULL,'$2y$10$1L0vsqBQ.hA4rN/ASD6yxuarX4v8S.kHP1y48ULgMXt5Tyica7ijG',NULL,'2026-04-07 12:33:12','2026-04-07 12:33:12'),(5,NULL,NULL,'feeeee',NULL,1,'leadrer@gmail.com',NULL,'$2y$10$SgBFwp1At1Ri1W4QgueiMeBO.0LXtft97kmsvXuuJBnnxg1FkR9nW',NULL,'2026-04-07 12:33:55','2026-04-07 12:33:55'),(6,NULL,NULL,'John',NULL,1,'john@gmail.com',NULL,'$2y$10$BJ8usc4NNBZ/0qoxwYBM5uVw3ZnCJll3FBPgrZH8WNUC7E6ZDfluS',NULL,'2026-04-07 12:37:56','2026-04-07 12:37:56'),(7,NULL,NULL,'Kevin Avi',NULL,1,'kevin@gmail.com',NULL,'$2y$10$QbH3RfOIVFjpRKq3sKh6HOSPwDuoW1ho73N/S7ef0KI00FClJq7ze',NULL,'2026-04-07 14:28:01','2026-04-07 14:28:01'),(8,NULL,NULL,'Debbie Avi',NULL,1,'debbie@gmail.com',NULL,'$2y$10$xpSmYyVqBXNJH4jus9qPfOKSGBG627FVciXLzhH4wHguwULZn5cnO',NULL,'2026-04-07 14:28:51','2026-04-07 14:28:51'),(9,NULL,NULL,'Alex Bob',NULL,1,'alex@gmail.com',NULL,'$2y$10$h7wVgYs3SGux.gMm46F1V.jBCUy5eiUDGGdijYkb0Tt0no6EH6YoK',NULL,'2026-04-08 05:38:02','2026-04-08 05:38:02'),(10,NULL,2,'Joshua Avinyia',NULL,1,'kavinyia@gmail.com',NULL,'$2y$10$qn7NB6K4DHO0/sLr3kj4cuCEwCxdhVqj86V/.i1Q0wEMl4b0/Khya','S6OML5b1zWbPTgkE9znrt26RFFFlrPfvuxbX0GHuLg4OxGyuN7JY4G0ERHVs','2026-04-08 07:12:31','2026-04-08 11:18:19'),(11,NULL,NULL,'Mark Bright',NULL,1,'mark@gmail.com',NULL,'$2y$10$thlzd4S2ZM/7EvbGIb7gGerTuNcCGiZueUCYfdcrSspFIWzugoGvK',NULL,'2026-04-08 10:16:54','2026-04-08 10:16:54'),(12,NULL,NULL,'Tonny Bazirakye',NULL,1,'tonny@gmail.com',NULL,'$2y$10$Tx0DPPApbhqSe.ziBE1MG.HiMS55aKtLzRlgs7vYhrdczJakqS69S',NULL,'2026-04-08 10:17:40','2026-04-08 10:17:40'),(13,NULL,NULL,'Michael Imakit',NULL,1,'mike@gmail.com',NULL,'$2y$10$5epJsCt2PthCkTMwwAZmrOur3hffsO0u8nh.FClQs./9zvcnpNUBm',NULL,'2026-04-08 10:18:20','2026-04-08 10:18:20');
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

-- Dump completed on 2026-04-08 20:29:58
