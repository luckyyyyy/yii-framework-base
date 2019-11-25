-- phpMyAdmin SQL Dump
-- version 4.7.5
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: 2018-04-23 14:31:33
-- 服务器版本： 5.7.20
-- PHP Version: 7.1.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `muggle`
--

-- --------------------------------------------------------

--
-- 表的结构 `shorturl`
--

CREATE TABLE `shorturl` (
  `id` VARCHAR(16) NOT NULL DEFAULT '',
  `url` VARCHAR(2048) NOT NULL DEFAULT '',
  `time_create` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `count` int(11) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

