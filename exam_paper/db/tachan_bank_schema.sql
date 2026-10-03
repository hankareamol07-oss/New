-- tachan_bank: 2026-27 annual-plan (तचन) topics std 1-8; paper/homework/HPC/YouTube link to it via (std, tachan_subject, tachan_seq).
SET NAMES utf8mb4;
/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `tachan_bank` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `std` int(11) NOT NULL,
  `subject` varchar(120) NOT NULL,
  `dn` int(11) NOT NULL,
  `month_no` int(11) NOT NULL,
  `day` int(11) NOT NULL,
  `topic` varchar(255) NOT NULL,
  `tl_activity` text DEFAULT NULL,
  `eval_tool` text DEFAULT NULL,
  `materials` text DEFAULT NULL,
  `learning_outcome` text DEFAULT NULL,
  `homework` text DEFAULT NULL,
  `topic_status` varchar(48) DEFAULT NULL,
  `seq` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `k_std_sub` (`std`,`subject`,`dn`),
  KEY `k_seq` (`std`,`subject`,`seq`)
) ENGINE=InnoDB AUTO_INCREMENT=21482 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
