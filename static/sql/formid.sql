-- phpMyAdmin SQL Dump
-- version 4.8.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: 2018-08-15 19:06:05
-- 服务器版本： 5.7.22
-- PHP Version: 7.1.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `muggle`
--

-- --------------------------------------------------------

--
-- 表的结构 `B_wxapp_wechat_formid`
--

CREATE TABLE `A_wxapp_wechat_formid` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `formid` varchar(64) NOT NULL,
  `count` tinyint(3) UNSIGNED NOT NULL,
  `time_create` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `B_wxapp_wechat_formid`
--
ALTER TABLE `A_wxapp_wechat_formid`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `time_create` (`time_create`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `B_wxapp_wechat_formid`
--
ALTER TABLE `A_wxapp_wechat_formid`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;
