-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- Host: pcm-rhdb-prod
-- Generation Time: Mar 07, 2025 at 09:26 AM
-- Server version: 10.4.11-MariaDB-1:10.4.11+maria~bionic
-- PHP Version: 7.4.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `whdc`
--
CREATE DATABASE IF NOT EXISTS `whdc` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `whdc`;

-- --------------------------------------------------------

--
-- Table structure for table `whdc`
--

CREATE TABLE IF NOT EXISTS `whdc` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'row id.',
  `tenant_name` varchar(32) NOT NULL,
  `subject` varchar(765) NOT NULL COMMENT 'subject line',
  `rem_address` varchar(16) NOT NULL,
  `json_base64` text NOT NULL COMMENT 'encoded json string',
  `logs_base64` text NOT NULL COMMENT 'encoded logs',
  `date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_index` (`tenant_name`),
  KEY `Subject` (`subject`),
  KEY `remote_ip_address` (`rem_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Actual whdc table';

-- --------------------------------------------------------

--
-- Table structure for table `whdc_tokens`
--

CREATE TABLE IF NOT EXISTS `whdc_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(32) NOT NULL COMMENT 'Tenant identifier',
  `token` text NOT NULL COMMENT 'Actual token',
  `email` varchar(256) NOT NULL COMMENT 'E-Mail',
  `date` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_name` (`name`),
  UNIQUE KEY `token` (`token`) USING HASH
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Token table';
