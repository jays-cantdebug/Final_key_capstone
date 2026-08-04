-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: student_mental_health_system
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
-- Table structure for table `dass_responses`
--

DROP TABLE IF EXISTS `dass_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dass_responses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `assessment_id` bigint unsigned NOT NULL,
  `dass_question_id` bigint unsigned NOT NULL,
  `answer_value` tinyint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dass_responses_assessment_id_dass_question_id_unique` (`assessment_id`,`dass_question_id`),
  KEY `dass_responses_dass_question_id_foreign` (`dass_question_id`),
  CONSTRAINT `dass_responses_assessment_id_foreign` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dass_responses_dass_question_id_foreign` FOREIGN KEY (`dass_question_id`) REFERENCES `dass_questions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2459 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dass_responses`
--

LOCK TABLES `dass_responses` WRITE;
/*!40000 ALTER TABLE `dass_responses` DISABLE KEYS */;
INSERT INTO `dass_responses` VALUES (2375,115,1,1,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2376,115,2,1,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2377,115,3,2,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2378,115,4,1,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2379,115,5,2,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2380,115,6,1,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2381,115,7,2,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2382,115,8,1,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2383,115,9,2,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2384,115,10,1,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2385,115,11,1,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2386,115,12,2,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2387,115,13,1,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2388,115,14,2,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2389,115,15,1,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2390,115,16,2,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2391,115,17,1,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2392,115,18,2,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2393,115,19,1,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2394,115,20,2,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2395,115,21,1,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(2396,116,1,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2397,116,2,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2398,116,3,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2399,116,4,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2400,116,5,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2401,116,6,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2402,116,7,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2403,116,8,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2404,116,9,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2405,116,10,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2406,116,11,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2407,116,12,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2408,116,13,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2409,116,14,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2410,116,15,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2411,116,16,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2412,116,17,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2413,116,18,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2414,116,19,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2415,116,20,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2416,116,21,1,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(2417,117,1,1,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2418,117,2,1,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2419,117,3,1,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2420,117,4,1,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2421,117,5,2,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2422,117,6,1,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2423,117,7,2,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2424,117,8,1,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2425,117,9,2,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2426,117,10,1,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2427,117,11,1,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2428,117,12,2,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2429,117,13,1,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2430,117,14,2,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2431,117,15,1,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2432,117,16,1,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2433,117,17,2,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2434,117,18,1,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2435,117,19,1,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2436,117,20,2,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2437,117,21,1,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(2438,118,1,2,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2439,118,2,0,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2440,118,3,1,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2441,118,4,0,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2442,118,5,1,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2443,118,6,0,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2444,118,7,0,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2445,118,8,1,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2446,118,9,0,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2447,118,10,1,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2448,118,11,0,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2449,118,12,1,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2450,118,13,0,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2451,118,14,1,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2452,118,15,0,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2453,118,16,1,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2454,118,17,2,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2455,118,18,0,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2456,118,19,0,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2457,118,20,0,'2026-08-03 12:03:31','2026-08-03 12:03:31'),(2458,118,21,1,'2026-08-03 12:03:31','2026-08-03 12:03:31');
/*!40000 ALTER TABLE `dass_responses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dass_results`
--

DROP TABLE IF EXISTS `dass_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dass_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `assessment_id` bigint unsigned NOT NULL,
  `depression_raw_score` int unsigned NOT NULL,
  `anxiety_raw_score` int unsigned NOT NULL,
  `stress_raw_score` int unsigned NOT NULL,
  `depression_final_score` int unsigned NOT NULL,
  `anxiety_final_score` int unsigned NOT NULL,
  `stress_final_score` int unsigned NOT NULL,
  `depression_level` varchar(255) NOT NULL,
  `anxiety_level` varchar(255) NOT NULL,
  `stress_level` varchar(255) NOT NULL,
  `ai_provider` varchar(255) NOT NULL,
  `used_non_official_thresholds` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dass_results_assessment_id_unique` (`assessment_id`),
  CONSTRAINT `dass_results_assessment_id_foreign` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=119 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dass_results`
--

LOCK TABLES `dass_results` WRITE;
/*!40000 ALTER TABLE `dass_results` DISABLE KEYS */;
INSERT INTO `dass_results` VALUES (115,115,10,10,10,20,20,20,'Moderate','Extremely Severe','Moderate','claude',0,'2026-08-03 04:41:27','2026-08-03 04:41:27'),(116,116,7,7,7,14,14,14,'Moderate','Moderate','Normal','claude',0,'2026-08-03 05:08:31','2026-08-03 05:08:31'),(117,117,9,10,9,18,20,18,'Moderate','Extremely Severe','Mild','claude',0,'2026-08-03 05:26:41','2026-08-03 05:26:41'),(118,118,7,0,5,14,0,10,'Moderate','Normal','Normal','claude',0,'2026-08-03 12:03:31','2026-08-03 12:03:31');
/*!40000 ALTER TABLE `dass_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `counseling_sessions`
--

DROP TABLE IF EXISTS `counseling_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `counseling_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `assessment_id` bigint unsigned DEFAULT NULL,
  `counselor_id` bigint unsigned NOT NULL,
  `session_datetime` datetime NOT NULL,
  `session_notes` text NOT NULL,
  `session_status` varchar(255) NOT NULL DEFAULT 'Scheduled',
  `follow_up_required` tinyint(1) NOT NULL DEFAULT '0',
  `follow_up_date` date DEFAULT NULL,
  `confidentiality_level` varchar(255) NOT NULL DEFAULT 'Standard',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `counseling_sessions_student_id_foreign` (`student_id`),
  KEY `counseling_sessions_assessment_id_foreign` (`assessment_id`),
  KEY `counseling_sessions_counselor_id_foreign` (`counselor_id`),
  CONSTRAINT `counseling_sessions_assessment_id_foreign` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `counseling_sessions_counselor_id_foreign` FOREIGN KEY (`counselor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `counseling_sessions_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `counseling_sessions`
--

LOCK TABLES `counseling_sessions` WRITE;
/*!40000 ALTER TABLE `counseling_sessions` DISABLE KEYS */;
INSERT INTO `counseling_sessions` VALUES (26,146,115,3,'2026-08-04 08:02:00','Tommorow','Completed',0,NULL,'Restricted','2026-08-03 04:43:00','2026-08-03 04:44:34',NULL);
/*!40000 ALTER TABLE `counseling_sessions` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-04 16:55:06
