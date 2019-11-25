-- MySQL dump 10.13  Distrib 5.7.20, for osx10.13 (x86_64)
--
-- Host: localhost    Database: muggle
-- ------------------------------------------------------
-- Server version	5.7.20

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `address`
--

DROP TABLE IF EXISTS `address`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `address` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `identity_id` int(10) unsigned NOT NULL,
  `name` varchar(30) NOT NULL DEFAULT '',
  `phone` varchar(50) NOT NULL DEFAULT '',
  `city` varchar(100) NOT NULL DEFAULT '',
  `address` varchar(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `identity_id` (`identity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin` (
  `id` int(11) NOT NULL DEFAULT '0',
  `flag` int(11) NOT NULL DEFAULT '0',
  `time_create` int(10) unsigned NOT NULL DEFAULT '0',
  `time_expire` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_behavior_log`
--

DROP TABLE IF EXISTS `admin_behavior_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_behavior_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `identity_id` int(10) unsigned NOT NULL,
  `url` varchar(200) NOT NULL DEFAULT '',
  `method` varchar(10) NOT NULL DEFAULT '',
  `status` smallint(5) unsigned NOT NULL DEFAULT '0',
  `states` text,
  `ip` varchar(100) NOT NULL DEFAULT '',
  `agent` varchar(255) NOT NULL DEFAULT '',
  `time` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `identity_id` (`identity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_login_log`
--

DROP TABLE IF EXISTS `admin_login_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_login_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `identity_id` int(10) unsigned NOT NULL,
  `method` varchar(30) NOT NULL DEFAULT '',
  `states` text,
  `ip` varchar(100) NOT NULL DEFAULT '',
  `agent` varchar(255) NOT NULL DEFAULT '',
  `time` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `identity_id` (`identity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `anwang_keyword`
--

DROP TABLE IF EXISTS `anwang_keyword`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `anwang_keyword` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `is_match` enum('Y','N') NOT NULL DEFAULT 'N',
  `keyword` varchar(128) CHARACTER SET utf8mb4 NOT NULL,
  `message` text CHARACTER SET utf8mb4 NOT NULL,
  `count` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `keyword` (`keyword`),
  KEY `match` (`is_match`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `anwang_user`
--

DROP TABLE IF EXISTS `anwang_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `anwang_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(128) NOT NULL DEFAULT '0',
  `flag` int(11) NOT NULL DEFAULT '0',
  `is_follow` enum('Y','N') NOT NULL DEFAULT 'N',
  `time_join` int(10) unsigned NOT NULL DEFAULT '0',
  `time_cancel` int(10) unsigned NOT NULL DEFAULT '0',
  `time_subscribe` int(10) unsigned NOT NULL DEFAULT '0',
  `time_active` int(10) unsigned NOT NULL DEFAULT '0',
  `time_active_menu` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `openid` (`openid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `appmarket`
--

DROP TABLE IF EXISTS `appmarket`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appmarket` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `android` varchar(250) NOT NULL DEFAULT '',
  `ios` varchar(250) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `attachment`
--

DROP TABLE IF EXISTS `attachment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attachment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `identity_id` int(10) unsigned NOT NULL DEFAULT '0',
  `type` varchar(10) NOT NULL DEFAULT '0',
  `name` varchar(200) NOT NULL DEFAULT '',
  `size` int(10) unsigned NOT NULL DEFAULT '0',
  `path` varchar(200) NOT NULL DEFAULT '',
  `mime` varchar(50) NOT NULL DEFAULT '',
  `time_create` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `identity_id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `block`
--

DROP TABLE IF EXISTS `block`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `block` (
  `id` int(11) NOT NULL DEFAULT '0',
  `flag` int(11) NOT NULL DEFAULT '0',
  `time_create` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `A_keyword`
--

DROP TABLE IF EXISTS `A_keyword`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `A_keyword` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `is_match` enum('Y','N') NOT NULL DEFAULT 'N',
  `keyword` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 NOT NULL,
  `count` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `keyword` (`keyword`),
  KEY `match` (`is_match`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `A_user`
--

DROP TABLE IF EXISTS `A_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `A_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(128) NOT NULL DEFAULT '0',
  `flag` int(11) NOT NULL DEFAULT '0',
  `is_follow` enum('Y','N') NOT NULL DEFAULT 'N',
  `time_join` int(10) unsigned NOT NULL DEFAULT '0',
  `time_cancel` int(10) unsigned NOT NULL DEFAULT '0',
  `time_subscribe` int(10) unsigned NOT NULL DEFAULT '0',
  `time_active` int(10) unsigned NOT NULL DEFAULT '0',
  `time_active_menu` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `openid` (`openid`) USING BTREE,
  KEY `is_follow` (`is_follow`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `A_wechat_qrcode`
--

DROP TABLE IF EXISTS `A_wechat_qrcode`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `A_wechat_qrcode` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `data` text NOT NULL,
  `type` varchar(32) NOT NULL DEFAULT '',
  `ticket` varchar(128) NOT NULL DEFAULT '',
  `url` varchar(128) NOT NULL DEFAULT '',
  `time_expire` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `identity`
--

DROP TABLE IF EXISTS `identity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `identity` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `unionid` varchar(100) DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 DEFAULT '',
  `password` varchar(128) DEFAULT NULL,
  `avatar` varchar(300) NOT NULL DEFAULT '',
  `flag` int(11) NOT NULL DEFAULT '0',
  `time_join` int(10) unsigned NOT NULL DEFAULT '0',
  `time_active` int(10) unsigned NOT NULL DEFAULT '0',
  `time_deny` int(10) unsigned NOT NULL DEFAULT '0',
  `location_lon` float NOT NULL DEFAULT '0',
  `location_lat` float NOT NULL DEFAULT '0',
  `agent` varchar(255) DEFAULT NULL,
  `ip` varchar(100) DEFAULT NULL,
  `states` text CHARACTER SET utf8mb4,
  `point` int(11) NOT NULL DEFAULT '0',
  `phone` varchar(50) DEFAULT NULL,
  `gender` tinyint(4) NOT NULL DEFAULT '0',
  `constellation` tinyint(4) NOT NULL DEFAULT '0',
  `city` varchar(100) CHARACTER SET utf8mb4 NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unionid` (`unionid`) USING BTREE,
  UNIQUE KEY `phone` (`phone`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operation_user`
--

DROP TABLE IF EXISTS `operation_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `operation_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(128) NOT NULL DEFAULT '0',
  `flag` int(11) NOT NULL DEFAULT '0',
  `is_follow` enum('Y','N') NOT NULL DEFAULT 'N',
  `time_join` int(10) unsigned NOT NULL DEFAULT '0',
  `time_cancel` int(10) unsigned NOT NULL DEFAULT '0',
  `time_subscribe` int(10) unsigned NOT NULL DEFAULT '0',
  `time_active` int(10) unsigned NOT NULL DEFAULT '0',
  `time_active_menu` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `openid` (`openid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `testing_activity`
--

DROP TABLE IF EXISTS `testing_activity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testing_activity` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `flag` int(11) NOT NULL DEFAULT '0',
  `title` varchar(250) CHARACTER SET utf8mb4 NOT NULL DEFAULT '',
  `description` text CHARACTER SET utf8mb4,
  `banner` text,
  `start` int(10) unsigned NOT NULL DEFAULT '0',
  `end` int(10) unsigned NOT NULL DEFAULT '0',
  `max_rand` int(10) unsigned NOT NULL DEFAULT '0',
  `count_comment` int(10) unsigned NOT NULL DEFAULT '0',
  `count_favorite` int(10) unsigned NOT NULL DEFAULT '0',
  `count_user` int(10) unsigned NOT NULL DEFAULT '0',
  `extra` varchar(3000) CHARACTER SET utf8mb4 NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `start` (`start`),
  KEY `end` (`end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `testing_award`
--

DROP TABLE IF EXISTS `testing_award`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testing_award` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` int(10) unsigned NOT NULL,
  `name` varchar(250) NOT NULL,
  `count` int(10) unsigned NOT NULL DEFAULT '0',
  `flag` int(11) NOT NULL DEFAULT '0',
  `extra` varchar(1000) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `activity_id` (`activity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `testing_award_log`
--

DROP TABLE IF EXISTS `testing_award_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testing_award_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identity_id` int(10) unsigned NOT NULL,
  `activity_id` int(10) unsigned NOT NULL,
  `award_id` int(10) unsigned NOT NULL,
  `count_activity` int(10) unsigned NOT NULL DEFAULT '0',
  `time_create` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `activity_id` (`activity_id`),
  KEY `identity_id` (`identity_id`),
  KEY `award_id` (`award_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `testing_comment`
--

DROP TABLE IF EXISTS `testing_comment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testing_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `identity_id` int(10) unsigned NOT NULL,
  `activity_id` int(10) unsigned NOT NULL,
  `content` varchar(1024) CHARACTER SET utf8mb4 NOT NULL DEFAULT '',
  `reply_id` int(10) unsigned NOT NULL DEFAULT '0',
  `time_create` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `activity_id` (`activity_id`) USING BTREE,
  KEY `reply_id` (`reply_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `testing_favorite`
--

DROP TABLE IF EXISTS `testing_favorite`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testing_favorite` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `identity_id` int(10) unsigned NOT NULL,
  `activity_id` int(10) unsigned NOT NULL,
  `time_create` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `identity_id_2` (`identity_id`,`activity_id`),
  KEY `identity_id` (`identity_id`),
  KEY `activity_id` (`activity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `testing_setting`
--

DROP TABLE IF EXISTS `testing_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testing_setting` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` tinyint(3) unsigned NOT NULL,
  `activity_id` int(10) unsigned NOT NULL,
  `extra` varchar(3000) CHARACTER SET utf8mb4 NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `testing_survey_log`
--

DROP TABLE IF EXISTS `testing_survey_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testing_survey_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` int(10) unsigned NOT NULL,
  `identity_id` int(10) unsigned NOT NULL,
  `data` text,
  `time_create` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `activity_id` (`activity_id`),
  KEY `identity_id` (`identity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `testing_survey_quest`
--

DROP TABLE IF EXISTS `testing_survey_quest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testing_survey_quest` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` int(10) unsigned NOT NULL,
  `type` tinyint(3) unsigned NOT NULL,
  `name` varchar(300) NOT NULL,
  `count` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `activity_id` (`activity_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `testing_survey_select`
--

DROP TABLE IF EXISTS `testing_survey_select`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testing_survey_select` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` int(10) unsigned NOT NULL,
  `name` varchar(300) CHARACTER SET utf8mb4 NOT NULL DEFAULT '',
  `count` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `quest_id` (`quest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `testing_user`
--

DROP TABLE IF EXISTS `testing_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testing_user` (
  `identity_id` int(10) unsigned NOT NULL,
  `activity_id` int(10) unsigned NOT NULL,
  `flag` int(11) NOT NULL DEFAULT '0',
  `point` int(10) unsigned NOT NULL DEFAULT '0',
  `referral_id` int(10) unsigned NOT NULL DEFAULT '0',
  `count_activity` int(10) unsigned NOT NULL DEFAULT '0',
  `time_create` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`identity_id`, `activity_id`),
  KEY `identity_id` (`identity_id`),
  KEY `is_follow` (`is_follow`),
  KEY `activity_id` (`activity_id`) USING BTREE,
  KEY `referral_id` (`referral_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `token`
--

DROP TABLE IF EXISTS `token`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `token` (
  `id` varchar(64) NOT NULL,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `push_id` varchar(100) NOT NULL DEFAULT '',
  `ip` varchar(100) NOT NULL DEFAULT '',
  `os_name` varchar(50) NOT NULL DEFAULT '',
  `os_version` varchar(50) NOT NULL DEFAULT '',
  `device` varchar(50) DEFAULT NULL,
  `states` text CHARACTER SET utf8mb4,
  `time_create` int(10) unsigned NOT NULL DEFAULT '0',
  `time_update` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `B_keyword`
--

DROP TABLE IF EXISTS `B_keyword`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `B_keyword` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `is_match` enum('Y','N') NOT NULL DEFAULT 'N',
  `keyword` varchar(128) CHARACTER SET utf8mb4 NOT NULL,
  `message` text CHARACTER SET utf8mb4 NOT NULL,
  `count` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `keyword` (`keyword`),
  KEY `match` (`is_match`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `B_user`
--

DROP TABLE IF EXISTS `B_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `B_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(128) NOT NULL DEFAULT '0',
  `flag` int(11) NOT NULL DEFAULT '0',
  `is_follow` enum('Y','N') NOT NULL DEFAULT 'N',
  `time_join` int(10) unsigned NOT NULL DEFAULT '0',
  `time_cancel` int(10) unsigned NOT NULL DEFAULT '0',
  `time_subscribe` int(10) unsigned NOT NULL DEFAULT '0',
  `time_active` int(10) unsigned NOT NULL DEFAULT '0',
  `time_active_menu` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `openid` (`openid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `B_wechat_qrcode`
--

DROP TABLE IF EXISTS `B_wechat_qrcode`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `B_wechat_qrcode` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) NOT NULL DEFAULT '',
  `data` text NOT NULL,
  `type` varchar(32) NOT NULL DEFAULT '',
  `ticket` varchar(128) NOT NULL DEFAULT '',
  `url` varchar(128) NOT NULL DEFAULT '',
  `time_expire` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `bash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2018-01-23 19:32:43
