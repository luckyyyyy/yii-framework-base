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
-- 表的结构 `funcode`
--

CREATE TABLE `funcode` (
  `id` varchar(120) NOT NULL,
  `type` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `create_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `bind_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `time_create` int(10) UNSIGNED NOT NULL,
  `time_expire` int(10) UNSIGNED NOT NULL,
  `count` int(10) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `funcode_log`
--

CREATE TABLE `funcode_log` (
  `id` varchar(120) NOT NULL,
  `orig_data` varchar(3000) DEFAULT '',
  `ip` varchar(120) NOT NULL,
  `identity_id` int(10) UNSIGNED NOT NULL,
  `time_use` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `funcode`
--
ALTER TABLE `funcode`
  ADD PRIMARY KEY (`id`),
  ADD KEY `create_id` (`create_id`),
  ADD KEY `bind_id` (`bind_id`) USING BTREE;

--
-- Indexes for table `funcode_log`
--
ALTER TABLE `funcode_log`
  ADD PRIMARY KEY (`id`,`identity_id`) USING BTREE,
  ADD KEY `id` (`id`) USING BTREE;
COMMIT;
