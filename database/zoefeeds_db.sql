-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 29, 2026 at 06:10 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `zoefeeds`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(200) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','suspended','pending') NOT NULL DEFAULT 'pending',
  `approved_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'super_admin id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `full_name`, `email`, `phone`, `password`, `status`, `approved_by`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@admin.com', '', '$2y$12$Tsrzev2akYrPGN60S4WKL.z/8KIE37GHpFttRKDH9ukwtQ2frqnvS', 'active', NULL, '2026-05-30 10:19:01', '2026-05-30 10:23:31');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `actor_type` enum('user','admin','vendor','super_admin','system') NOT NULL,
  `actor_id` int(10) UNSIGNED NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `actor_type`, `actor_id`, `action`, `description`, `entity_type`, `entity_id`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 'user', 1, 'register', 'New customer registered', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-26 06:49:30'),
(2, 'vendor', 1, 'register', 'New vendor application: SPOTWEB COM', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-26 06:50:18'),
(3, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-26 07:34:46'),
(4, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 08:12:44'),
(5, 'user', 1, 'login', 'User logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 08:17:53'),
(6, 'admin', 1, 'generate_codes', 'Generated 4 codes, batch: BATCH-20260627T092301-AF2DDB → vendor 1 (Vendor One)', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 08:23:02'),
(7, 'user', 1, 'logout', 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 08:25:13'),
(8, 'vendor', 2, 'register', 'New vendor application: SPOTWEB TECH', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 08:26:40'),
(9, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 08:26:52'),
(10, 'admin', 1, 'approve_vendor', 'Approved vendor: Victor Eze', 'vendor', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 08:27:20'),
(11, 'admin', 1, 'generate_codes', 'Generated 2 codes, batch: BATCH-20260627T092739-E0EDF3 → vendor 2 (Victor Eze)', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-27 08:27:40'),
(12, 'user', 2, 'register', 'New customer registered', NULL, NULL, '102.90.115.208', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-27 21:32:27'),
(13, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.115.208', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-27 21:39:47'),
(14, 'admin', 1, 'add_slide', 'Slide \'\' added', NULL, NULL, '102.90.115.208', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-27 22:05:45'),
(15, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.116.143', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-28 22:24:24'),
(16, 'admin', 1, 'add_slide', 'Slide \'\' added', NULL, NULL, '102.90.116.143', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-28 22:40:29'),
(17, 'admin', 1, 'add_slide', 'Slide \'\' added', NULL, NULL, '102.90.116.143', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-28 22:49:43'),
(18, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.116.143', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-28 22:52:56'),
(19, 'admin', 1, 'add_slide', 'Slide \'\' added', NULL, NULL, '102.90.116.143', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-28 22:53:15'),
(20, 'admin', 1, 'add_slide', 'Slide \'\' added', NULL, NULL, '102.90.116.143', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-28 22:55:25'),
(21, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.88.112.174', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-29 16:17:27'),
(22, 'user', 2, 'login', 'User logged in', NULL, NULL, '197.210.227.124', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-29 22:10:46'),
(23, 'user', 2, 'login', 'User logged in', NULL, NULL, '197.210.227.124', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-29 22:23:39'),
(24, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '197.210.227.124', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-29 22:30:33'),
(25, 'user', 2, 'login', 'User logged in', NULL, NULL, '197.210.227.124', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-30 14:33:28'),
(26, 'user', 2, 'login', 'User logged in', NULL, NULL, '197.210.227.124', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-30 14:35:08'),
(27, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '197.210.227.124', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-30 14:37:27'),
(28, 'user', 2, 'login', 'User logged in', NULL, NULL, '102.90.103.20', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-30 16:48:37'),
(29, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.103.20', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-30 16:50:27'),
(30, 'user', 1, 'login', 'User logged in', NULL, NULL, '102.90.101.135', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_7_12 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6.1 Mobile/15E148 Safari/604.1', '2026-07-01 13:37:22'),
(31, 'user', 3, 'register', 'New customer registered', NULL, NULL, '102.90.103.74', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1', '2026-07-01 17:19:43'),
(32, 'user', 2, 'login', 'User logged in', NULL, NULL, '102.90.99.13', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-01 18:13:54'),
(33, 'user', 2, 'login', 'User logged in', NULL, NULL, '102.90.99.13', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-01 18:17:28'),
(34, 'user', 2, 'logout', 'User logged out', NULL, NULL, '102.90.99.13', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-01 18:17:44'),
(35, 'user', 2, 'login', 'User logged in', NULL, NULL, '102.90.99.13', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-01 18:18:12'),
(36, 'user', 2, 'login', 'User logged in', NULL, NULL, '102.90.99.93', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-02 13:14:17'),
(37, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.99.93', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-02 13:21:06'),
(38, 'user', 2, 'login', 'User logged in', NULL, NULL, '102.90.115.158', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-02 20:48:33'),
(39, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.115.158', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) GSA/423.5.920392540 Mobile/15E148 Safari/604.1', '2026-07-02 20:51:07'),
(40, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.115.158', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) GSA/423.5.920392540 Mobile/15E148 Safari/604.1', '2026-07-02 20:52:25'),
(41, 'admin', 1, 'logout', 'Admin logged out', NULL, NULL, '102.90.115.158', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) GSA/423.5.920392540 Mobile/15E148 Safari/604.1', '2026-07-02 21:18:08'),
(42, 'user', 2, 'login', 'User logged in', NULL, NULL, '102.90.115.158', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-02 21:42:10'),
(43, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.115.158', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-02 22:32:22'),
(44, 'user', 2, 'login', 'User logged in', NULL, NULL, '197.210.55.78', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-04 22:03:05'),
(45, 'user', 2, 'login', 'User logged in', NULL, NULL, '102.90.99.218', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-05 19:12:15'),
(46, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.99.218', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-05 19:18:05'),
(47, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.115.9', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-06 16:12:36'),
(48, 'user', 2, 'login', 'User logged in', NULL, NULL, '102.90.115.9', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-06 16:15:10'),
(49, 'user', 2, 'login', 'User logged in', NULL, NULL, '102.90.115.9', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-06 17:57:38'),
(50, 'user', 2, 'logout', 'User logged out', NULL, NULL, '102.90.115.9', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-06 17:58:09'),
(51, 'vendor', 3, 'register', 'New vendor application: ZoeFeeds Services', NULL, NULL, '102.90.115.9', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-06 18:55:53'),
(52, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.115.9', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-06 19:13:00'),
(53, 'admin', 1, 'approve_vendor', 'Approved vendor: ZOEFEEDS CORDINATOR', 'vendor', 3, '102.90.115.9', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-06 19:14:13'),
(54, 'vendor', 3, 'login', 'Vendor logged in', NULL, NULL, '102.90.115.9', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-06 19:27:56'),
(55, 'user', 2, 'login', 'User logged in', NULL, NULL, '102.90.101.227', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-08 22:08:07'),
(56, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.101.227', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-08 22:12:59'),
(57, 'user', 2, 'login', 'User logged in', NULL, NULL, '102.90.81.196', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-09 19:16:02'),
(58, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.81.196', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-09 21:36:19'),
(59, 'admin', 1, 'generate_codes', 'Generated 10 codes, batch: BATCH-20260709T224054-2F3192 → vendor 3 (ZOEFEEDS CORDINATOR)', NULL, NULL, '102.90.81.196', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-09 21:40:54'),
(60, 'admin', 1, 'generate_codes', 'Generated 10 codes, batch: BATCH-20260709T224129-84305A → vendor 3 (ZOEFEEDS CORDINATOR)', NULL, NULL, '102.90.81.196', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-09 21:41:29'),
(61, 'vendor', 3, 'login', 'Vendor logged in', NULL, NULL, '102.90.81.196', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-09 21:42:53'),
(62, 'user', 2, 'login', 'User logged in', NULL, NULL, '102.90.81.196', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-09 21:45:57'),
(63, 'vendor', 3, 'login', 'Vendor logged in', NULL, NULL, '102.90.81.196', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-09 22:55:54'),
(64, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.81.196', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-09 23:02:53'),
(65, 'vendor', 3, 'login', 'Vendor logged in', NULL, NULL, '102.90.103.56', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-11 18:33:46'),
(66, 'vendor', 3, 'login', 'Vendor logged in', NULL, NULL, '102.88.113.229', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-11 18:37:20'),
(67, 'vendor', 3, 'logout', 'Vendor logged out', NULL, NULL, '102.88.113.229', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-11 18:41:30'),
(68, 'user', 4, 'register', 'New customer registered', NULL, NULL, '105.118.20.34', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-07-11 20:05:15'),
(69, 'vendor', 4, 'register', 'New vendor application: De AI Empire and Creativity World', NULL, NULL, '105.118.20.34', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-07-11 20:20:18'),
(70, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.103.56', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-11 20:50:39'),
(71, 'admin', 1, 'reject_vendor', 'Rejected vendor: Alaoma Benjamin', 'vendor', 4, '102.90.103.56', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-11 20:52:27'),
(72, 'admin', 1, 'create_draw', 'Draw \'Win Free 2GB data\' created', 'draw', 1, '102.90.103.56', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-11 21:05:38'),
(73, 'admin', 1, 'create_draw', 'Draw \'Win 2GB Free data\' created', 'draw', 2, '102.90.103.56', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-11 21:27:09'),
(74, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.103.56', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-11 21:28:38'),
(75, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.103.56', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-11 22:06:29'),
(76, 'admin', 1, 'create_draw', 'Draw \'Giveaway for testers\' created', 'draw', 3, '102.90.103.56', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-11 22:09:23'),
(77, 'admin', 1, 'create_draw', 'Draw \'Giveaway for testers\' created', 'draw', 4, '102.90.103.56', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-11 22:15:05'),
(78, 'vendor', 3, 'login', 'Vendor logged in', NULL, NULL, '102.90.103.56', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-11 22:30:53'),
(79, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.103.56', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-11 22:40:24'),
(80, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '105.112.212.98', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_7_12 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6.1 Mobile/15E148 Safari/604.1', '2026-07-12 10:58:20'),
(81, 'vendor', 3, 'login', 'Vendor logged in', NULL, NULL, '102.88.113.113', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-12 22:42:03'),
(82, 'user', 2, 'login', 'User logged in', NULL, NULL, '102.88.113.113', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-07-12 22:43:40'),
(83, 'admin', 1, 'delete_draw', 'Draw #1 deleted', 'draw', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:32:28'),
(84, 'admin', 1, 'delete_draw', 'Draw #3 deleted', 'draw', 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:32:31'),
(85, 'admin', 1, 'delete_draw', 'Draw #4 deleted', 'draw', 4, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:32:33'),
(86, 'admin', 1, 'delete_draw', 'Draw #2 deleted', 'draw', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:32:40'),
(87, 'admin', 1, 'create_draw', 'Draw \'Win 2GB Free data By Firstclass\' created', 'draw', 5, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:33:29'),
(88, 'user', 5, 'register', 'New customer registered', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:37:09'),
(89, 'vendor', 2, 'login', 'Vendor logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:39:10'),
(90, 'user', 5, 'redeem_code', 'Code redeemed: 089965742481458 (was: assigned)', 'code', 6, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:45:23'),
(91, 'admin', 1, 'activate_draw', 'Draw #5 manually activated', 'draw', 5, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:46:36'),
(92, 'user', 5, 'draw_entry', 'Entered 1 codes in draw 5', 'draw', 5, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:48:41'),
(93, 'admin', 1, 'end_draw', 'Draw #5 manually ended', 'draw', 5, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:50:27'),
(94, 'admin', 1, 'select_winner', 'Winner selected for draw #5 — user #5, code 009988998934538', 'draw', 5, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:58:17'),
(95, 'admin', 1, 'create_draw', 'Draw \'Testing\' created', 'draw', 6, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 16:01:45'),
(96, 'admin', 1, 'cancel_draw', 'Draw #6 cancelled', 'draw', 6, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 16:02:06'),
(97, 'admin', 1, 'create_draw', 'Draw \'Testing2\' created', 'draw', 7, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 16:02:38'),
(98, 'vendor', 2, 'logout', 'Vendor logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 16:04:08'),
(99, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 18:28:55'),
(100, 'admin', 1, 'logout', 'Admin logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 18:29:37'),
(101, 'user', 5, 'login', 'User logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 19:34:36'),
(102, 'user', 5, 'login', 'User logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 21:42:05'),
(103, 'user', 5, 'logout', 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-13 21:54:27'),
(104, 'vendor', 2, 'login', 'Vendor logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-13 21:55:02'),
(105, 'vendor', 2, 'login', 'Vendor logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 18:49:01'),
(106, 'vendor', 2, 'logout', 'Vendor logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 18:53:55'),
(107, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 18:57:36'),
(108, 'admin', 1, 'edit_draw', 'Draw #7 updated', 'draw', 7, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 19:00:45'),
(109, 'admin', 1, 'edit_draw', 'Draw #7 updated', 'draw', 7, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 19:01:02'),
(110, 'admin', 1, 'edit_draw', 'Draw #7 updated', 'draw', 7, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 19:02:25'),
(111, 'user', 6, 'register', 'New customer registered', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 19:15:23'),
(112, 'user', 6, 'redeem_code', 'Code redeemed: 726461454647767 (was: assigned)', 'code', 13, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 19:18:47'),
(113, 'user', 6, 'set_pin', 'Transfer PIN updated', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 19:20:09'),
(114, 'user', 6, 'transfer_code', 'Code 726461454647767 transferred to user 1', 'code', 13, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 19:20:36'),
(115, 'admin', 1, 'activate_draw', 'Draw #7 manually activated', 'draw', 7, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 19:22:09'),
(116, 'admin', 1, 'end_draw', 'Draw #7 manually ended', 'draw', 7, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 19:22:22'),
(117, 'admin', 1, 'create_draw', 'Draw \'Micheal\' created', 'draw', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 19:33:54'),
(118, 'admin', 1, 'activate_draw', 'Draw #8 manually activated', 'draw', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 19:34:07'),
(119, 'user', 6, 'login', 'User logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 10:58:46'),
(120, 'user', 6, 'redeem_code', 'Code redeemed: 160307114441870 (was: assigned)', 'code', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 11:17:43'),
(121, 'user', 6, 'redeem_code', 'Code redeemed: 528127292271057 (was: assigned)', 'code', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 11:18:05'),
(122, 'user', 6, 'enter_draw', 'User entered draw #8 with code #2', 'draw', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 11:18:21'),
(123, 'user', 6, 'enter_draw', 'User entered draw #8 with code #1', 'draw', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 11:18:36'),
(124, 'user', 6, 'login', 'User logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 16:06:37'),
(125, 'user', 6, 'logout', 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 16:22:29'),
(126, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 16:23:38'),
(127, 'user', 6, 'login', 'User logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 16:24:42'),
(128, 'user', 6, 'logout', 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 16:28:05'),
(129, 'user', 6, 'login', 'User logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 00:43:09'),
(130, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 00:44:10'),
(131, 'admin', 1, 'create_testimonial', 'Testimonial by \'Ezea Ugochukwu micheal\' created', 'testimonial', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 01:31:15'),
(132, 'admin', 1, 'create_testimonial', 'Testimonial by \'Kwame Mensah Owusu\' created', 'testimonial', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 01:33:36'),
(133, 'user', 6, 'redeem_code', 'Code redeemed: 978735191405297 (was: assigned)', 'code', 7, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 01:42:11'),
(134, 'user', 6, 'redeem_code', 'Code redeemed: 941829158049712 (was: assigned)', 'code', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 01:43:04'),
(135, 'user', 7, 'register', 'New customer registered', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 01:51:10'),
(136, 'user', 7, 'redeem_code', 'Code redeemed: 436252604981172 (was: assigned)', 'code', 25, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 01:51:29'),
(137, 'user', 7, 'set_pin', 'Transfer PIN updated', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 01:52:33'),
(138, 'user', 7, 'transfer_code', 'Code 436252604981172 transferred to user 6', 'code', 25, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 01:52:54'),
(139, 'user', 6, 'enter_draw', 'User entered draw #8 with code #25', 'draw', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 02:00:20'),
(140, 'user', 6, 'enter_draw', 'User entered draw #8 with code #8', 'draw', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 02:00:20'),
(141, 'user', 6, 'redeem_code', 'Code redeemed: 871374296782151 (was: assigned)', 'code', 20, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 02:12:55'),
(142, 'user', 6, 'redeem_code', 'Code redeemed: 600236169007592 (was: assigned)', 'code', 22, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 02:13:05'),
(143, 'user', 6, 'redeem_code', 'Code redeemed: 092894904869894 (was: assigned)', 'code', 23, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 02:13:14'),
(144, 'user', 6, 'enter_draw', 'User entered draw #8 with code #23', 'draw', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 02:13:37'),
(145, 'user', 6, 'enter_draw', 'User entered draw #8 with code #22', 'draw', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 02:13:37'),
(146, 'user', 6, 'enter_draw', 'User entered draw #8 with code #20', 'draw', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 02:13:37'),
(147, 'user', 6, 'login', 'User logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 12:35:19'),
(148, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 12:37:19'),
(149, 'admin', 1, 'end_draw', 'Draw #8 manually ended', 'draw', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 12:39:43'),
(150, 'user', 6, 'login', 'User logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 16:08:19');

-- --------------------------------------------------------

--
-- Table structure for table `codes`
--

CREATE TABLE `codes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` char(15) NOT NULL COMMENT '15-digit numeric raffle code',
  `status` enum('unassigned','assigned','distributed','redeemed','reserved','used','transferred') NOT NULL DEFAULT 'unassigned',
  `generated_by` int(10) UNSIGNED NOT NULL COMMENT 'admin id',
  `assigned_vendor` int(10) UNSIGNED DEFAULT NULL COMMENT 'vendors.id — the vendor this batch was assigned to',
  `current_owner` int(10) UNSIGNED DEFAULT NULL COMMENT 'user id',
  `batch_id` varchar(60) DEFAULT NULL COMMENT 'Bulk generation batch reference',
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_at` timestamp NULL DEFAULT NULL,
  `redeemed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `codes`
--

INSERT INTO `codes` (`id`, `code`, `status`, `generated_by`, `assigned_vendor`, `current_owner`, `batch_id`, `generated_at`, `assigned_at`, `redeemed_at`) VALUES
(1, '160307114441870', 'reserved', 1, 1, 6, 'BATCH-20260627T092301-AF2DDB', '2026-06-27 08:23:01', '2026-06-27 08:23:01', '2026-07-19 11:17:43'),
(2, '528127292271057', 'reserved', 1, 1, 6, 'BATCH-20260627T092301-AF2DDB', '2026-06-27 08:23:02', '2026-06-27 08:23:02', '2026-07-19 11:18:05'),
(3, '905606240792346', 'assigned', 1, 1, NULL, 'BATCH-20260627T092301-AF2DDB', '2026-06-27 08:23:02', '2026-06-27 08:23:02', NULL),
(4, '215278575689710', 'assigned', 1, 1, NULL, 'BATCH-20260627T092301-AF2DDB', '2026-06-27 08:23:02', '2026-06-27 08:23:02', NULL),
(5, '619653631613106', 'assigned', 1, 2, NULL, 'BATCH-20260627T092739-E0EDF3', '2026-06-27 08:27:39', '2026-06-27 08:27:39', NULL),
(6, '089965742481458', 'used', 1, 2, 5, 'BATCH-20260627T092739-E0EDF3', '2026-06-27 08:27:40', '2026-06-27 08:27:40', '2026-07-13 15:45:23'),
(7, '978735191405297', 'redeemed', 1, 3, 6, 'BATCH-20260709T224054-2F3192', '2026-07-09 21:40:54', '2026-07-09 19:40:54', '2026-07-24 01:42:11'),
(8, '941829158049712', 'reserved', 1, 3, 6, 'BATCH-20260709T224054-2F3192', '2026-07-09 21:40:54', '2026-07-09 19:40:54', '2026-07-24 01:43:04'),
(9, '669876681591754', 'assigned', 1, 3, NULL, 'BATCH-20260709T224054-2F3192', '2026-07-09 21:40:54', '2026-07-09 19:40:54', NULL),
(10, '446692225106604', 'assigned', 1, 3, NULL, 'BATCH-20260709T224054-2F3192', '2026-07-09 21:40:54', '2026-07-09 19:40:54', NULL),
(11, '874557071830376', 'assigned', 1, 3, NULL, 'BATCH-20260709T224054-2F3192', '2026-07-09 21:40:54', '2026-07-09 19:40:54', NULL),
(12, '435438094217290', 'assigned', 1, 3, NULL, 'BATCH-20260709T224054-2F3192', '2026-07-09 21:40:54', '2026-07-09 19:40:54', NULL),
(13, '726461454647767', 'redeemed', 1, 3, 1, 'BATCH-20260709T224054-2F3192', '2026-07-09 21:40:54', '2026-07-09 19:40:54', '2026-07-18 19:18:47'),
(14, '336502888394479', 'assigned', 1, 3, NULL, 'BATCH-20260709T224054-2F3192', '2026-07-09 21:40:54', '2026-07-09 19:40:54', NULL),
(15, '199397020144409', 'assigned', 1, 3, NULL, 'BATCH-20260709T224054-2F3192', '2026-07-09 21:40:54', '2026-07-09 19:40:54', NULL),
(16, '092747537350375', 'assigned', 1, 3, NULL, 'BATCH-20260709T224054-2F3192', '2026-07-09 21:40:54', '2026-07-09 19:40:54', NULL),
(17, '494479280887010', 'assigned', 1, 3, NULL, 'BATCH-20260709T224129-84305A', '2026-07-09 21:41:29', '2026-07-09 19:41:29', NULL),
(18, '498312260573548', 'assigned', 1, 3, NULL, 'BATCH-20260709T224129-84305A', '2026-07-09 21:41:29', '2026-07-09 19:41:29', NULL),
(19, '959999031540100', 'assigned', 1, 3, NULL, 'BATCH-20260709T224129-84305A', '2026-07-09 21:41:29', '2026-07-09 19:41:29', NULL),
(20, '871374296782151', 'reserved', 1, 3, 6, 'BATCH-20260709T224129-84305A', '2026-07-09 21:41:29', '2026-07-09 19:41:29', '2026-07-24 02:12:55'),
(21, '639270959979130', 'assigned', 1, 3, NULL, 'BATCH-20260709T224129-84305A', '2026-07-09 21:41:29', '2026-07-09 19:41:29', NULL),
(22, '600236169007592', 'reserved', 1, 3, 6, 'BATCH-20260709T224129-84305A', '2026-07-09 21:41:29', '2026-07-09 19:41:29', '2026-07-24 02:13:05'),
(23, '092894904869894', 'reserved', 1, 3, 6, 'BATCH-20260709T224129-84305A', '2026-07-09 21:41:29', '2026-07-09 19:41:29', '2026-07-24 02:13:14'),
(24, '860165121709599', 'assigned', 1, 3, NULL, 'BATCH-20260709T224129-84305A', '2026-07-09 21:41:29', '2026-07-09 19:41:29', NULL),
(25, '436252604981172', 'reserved', 1, 3, 6, 'BATCH-20260709T224129-84305A', '2026-07-09 21:41:29', '2026-07-09 19:41:29', '2026-07-24 01:51:29'),
(26, '387573720696205', 'assigned', 1, 3, NULL, 'BATCH-20260709T224129-84305A', '2026-07-09 21:41:29', '2026-07-09 19:41:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `code_redemptions`
--

CREATE TABLE `code_redemptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `vendor_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'vendors.id — vendor credited for this redemption',
  `redeemed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `code_redemptions`
--

INSERT INTO `code_redemptions` (`id`, `code_id`, `user_id`, `vendor_id`, `redeemed_at`) VALUES
(5, 6, 5, 2, '2026-07-13 15:45:23'),
(6, 13, 6, 3, '2026-07-18 19:18:47'),
(7, 1, 6, 1, '2026-07-19 11:17:43'),
(8, 2, 6, 1, '2026-07-19 11:18:05'),
(9, 7, 6, 3, '2026-07-24 01:42:11'),
(10, 8, 6, 3, '2026-07-24 01:43:04'),
(11, 25, 7, 3, '2026-07-24 01:51:29'),
(12, 20, 6, 3, '2026-07-24 02:12:55'),
(13, 22, 6, 3, '2026-07-24 02:13:05'),
(14, 23, 6, 3, '2026-07-24 02:13:14');

-- --------------------------------------------------------

--
-- Table structure for table `code_transfers`
--

CREATE TABLE `code_transfers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code_id` bigint(20) UNSIGNED NOT NULL,
  `from_user_id` int(10) UNSIGNED NOT NULL,
  `to_user_id` int(10) UNSIGNED NOT NULL,
  `transferred_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `code_transfers`
--

INSERT INTO `code_transfers` (`id`, `code_id`, `from_user_id`, `to_user_id`, `transferred_at`) VALUES
(1, 13, 6, 1, '2026-07-18 19:20:36'),
(2, 25, 7, 6, '2026-07-24 01:52:54');

-- --------------------------------------------------------

--
-- Table structure for table `cron_logs`
--

CREATE TABLE `cron_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `job_name` varchar(100) NOT NULL,
  `draws_found` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `draws_ok` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `draws_failed` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `detail` text DEFAULT NULL,
  `run_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cron_logs`
--

INSERT INTO `cron_logs` (`id`, `job_name`, `draws_found`, `draws_ok`, `draws_failed`, `detail`, `run_at`) VALUES
(1, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 20:46:26'),
(2, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 20:47:27'),
(3, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 20:48:27'),
(4, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 20:49:26'),
(5, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 20:50:31'),
(6, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 20:51:28'),
(7, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 20:52:26'),
(8, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 20:53:27'),
(9, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 20:54:27'),
(10, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 20:55:56'),
(11, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 20:56:27'),
(12, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 20:57:27'),
(13, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 20:58:26'),
(14, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 20:59:27'),
(15, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:00:05'),
(16, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:01:27'),
(17, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:02:26'),
(18, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:03:27'),
(19, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:04:26'),
(20, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:05:48'),
(21, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:06:32'),
(22, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:07:51'),
(23, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:08:28'),
(24, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:09:27'),
(25, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:10:56'),
(26, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:11:26'),
(27, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:12:27'),
(28, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:13:27'),
(29, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:14:26'),
(30, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:15:02'),
(31, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:16:26'),
(32, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:17:27'),
(33, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:18:32'),
(34, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:19:27'),
(35, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:20:56'),
(36, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:21:42'),
(37, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:22:30'),
(38, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:23:26'),
(39, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:24:31'),
(40, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:25:57'),
(41, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:26:27'),
(42, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:27:27'),
(43, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:28:27'),
(44, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:29:27'),
(45, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:30:01'),
(46, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:31:26'),
(47, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:32:27'),
(48, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:33:27'),
(49, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:34:27'),
(50, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:35:56'),
(51, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:36:31'),
(52, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:37:27'),
(53, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:38:41'),
(54, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:39:29'),
(55, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:40:57'),
(56, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:41:26'),
(57, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:42:32'),
(58, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:43:27'),
(59, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:44:27'),
(60, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:45:01'),
(61, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:46:27'),
(62, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:47:26'),
(63, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:48:31'),
(64, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:49:27'),
(65, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:50:56'),
(66, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:51:26'),
(67, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:52:27'),
(68, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:53:27'),
(69, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:54:32'),
(70, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:55:57'),
(71, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:56:26'),
(72, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:57:26'),
(73, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:58:27'),
(74, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 21:59:26'),
(75, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:00:02'),
(76, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:01:26'),
(77, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:02:27'),
(78, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:03:27'),
(79, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:04:26'),
(80, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:05:56'),
(81, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:06:27'),
(82, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:07:27'),
(83, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:08:27'),
(84, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:09:27'),
(85, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:10:57'),
(86, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:11:28'),
(87, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:12:27'),
(88, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:13:27'),
(89, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:14:27'),
(90, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:15:01'),
(91, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:16:26'),
(92, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:17:27'),
(93, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:18:27'),
(94, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:19:26'),
(95, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:20:57'),
(96, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:21:26'),
(97, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:22:26'),
(98, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:23:26'),
(99, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:24:32'),
(100, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:25:56'),
(101, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:26:27'),
(102, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:27:27'),
(103, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:28:26'),
(104, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:29:27'),
(105, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:30:02'),
(106, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:31:27'),
(107, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:32:27'),
(108, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:33:27'),
(109, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:34:26'),
(110, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:35:56'),
(111, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:36:36'),
(112, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:37:26'),
(113, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:38:27'),
(114, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:39:27'),
(115, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:40:56'),
(116, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:41:26'),
(117, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:42:27'),
(118, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:43:27'),
(119, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:44:26'),
(120, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:45:02'),
(121, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:46:26'),
(122, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:47:26'),
(123, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:48:27'),
(124, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:49:27'),
(125, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:50:57'),
(126, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:51:27'),
(127, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:52:27'),
(128, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:53:26'),
(129, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:54:28'),
(130, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:55:57'),
(131, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:56:26'),
(132, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:57:26'),
(133, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:58:27'),
(134, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 22:59:27'),
(135, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:00:02'),
(136, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:01:27'),
(137, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:02:27'),
(138, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:03:27'),
(139, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:04:26'),
(140, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:05:56'),
(141, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:06:27'),
(142, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:07:27'),
(143, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:08:27'),
(144, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:09:27'),
(145, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:10:57'),
(146, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:11:26'),
(147, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:12:27'),
(148, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:13:26'),
(149, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:14:26'),
(150, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:15:02'),
(151, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:16:26'),
(152, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:17:27'),
(153, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:18:31'),
(154, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:19:26'),
(155, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:20:57'),
(156, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:21:27'),
(157, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:22:27'),
(158, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:23:27'),
(159, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:24:27'),
(160, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:25:56'),
(161, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:26:27'),
(162, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:27:26'),
(163, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:28:26'),
(164, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:29:26'),
(165, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:30:02'),
(166, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:31:26'),
(167, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:32:27'),
(168, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:33:26'),
(169, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:34:26'),
(170, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:35:57'),
(171, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:36:27'),
(172, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:37:26'),
(173, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:38:27'),
(174, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:39:26'),
(175, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:40:57'),
(176, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:41:26'),
(177, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:42:27'),
(178, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:43:27'),
(179, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:44:26'),
(180, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:45:01'),
(181, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:46:27'),
(182, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:47:28'),
(183, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:48:27'),
(184, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:49:27'),
(185, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:50:56'),
(186, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:51:26'),
(187, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:52:32'),
(188, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:53:27'),
(189, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:54:27'),
(190, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:55:56'),
(191, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:56:27'),
(192, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:57:27'),
(193, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:58:27'),
(194, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-09 23:59:26'),
(195, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:00:02'),
(196, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:01:26'),
(197, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:02:26'),
(198, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:03:27'),
(199, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:04:26'),
(200, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:05:57'),
(201, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:06:27'),
(202, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:07:26'),
(203, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:08:26'),
(204, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:09:26'),
(205, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:10:56'),
(206, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:11:27'),
(207, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:12:27'),
(208, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:13:27'),
(209, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:14:27'),
(210, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:15:01'),
(211, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:16:26'),
(212, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:17:27'),
(213, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:18:27'),
(214, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:19:26'),
(215, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:20:57'),
(216, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:21:26'),
(217, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:22:28'),
(218, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:23:27'),
(219, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:24:27'),
(220, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:25:56'),
(221, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:26:27'),
(222, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:27:27'),
(223, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:28:27'),
(224, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:29:26'),
(225, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:30:03'),
(226, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:31:26'),
(227, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:32:27'),
(228, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:33:27'),
(229, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:34:27'),
(230, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:35:56'),
(231, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:36:26'),
(232, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:37:27'),
(233, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:38:27'),
(234, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:39:26'),
(235, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:40:56'),
(236, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:41:27'),
(237, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:42:27'),
(238, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:43:27'),
(239, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:44:26'),
(240, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:45:01'),
(241, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:46:27'),
(242, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:47:26'),
(243, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:48:27'),
(244, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:49:26'),
(245, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:50:56'),
(246, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:51:27'),
(247, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:52:42'),
(248, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:53:27'),
(249, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:54:27'),
(250, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:55:59'),
(251, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:56:26'),
(252, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:57:26'),
(253, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:58:27'),
(254, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 00:59:26'),
(255, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:00:01'),
(256, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:01:27'),
(257, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:02:27'),
(258, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:03:27'),
(259, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:04:27'),
(260, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:05:57'),
(261, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:06:26'),
(262, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:07:26'),
(263, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:08:27'),
(264, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:09:27'),
(265, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:10:57'),
(266, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:11:27'),
(267, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:12:27'),
(268, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:13:27'),
(269, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:14:26'),
(270, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:15:01'),
(271, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:16:26'),
(272, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:17:26'),
(273, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:18:27'),
(274, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:19:26'),
(275, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:20:56'),
(276, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:21:26'),
(277, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:22:27'),
(278, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:23:26'),
(279, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:24:27'),
(280, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:25:57'),
(281, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:26:26'),
(282, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:27:26'),
(283, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:28:26'),
(284, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:29:27'),
(285, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:30:01'),
(286, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:31:27'),
(287, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:32:27'),
(288, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:33:26'),
(289, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:34:40'),
(290, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:35:56'),
(291, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:36:27'),
(292, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:37:27'),
(293, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:38:26'),
(294, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:39:26'),
(295, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:40:56'),
(296, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:41:26'),
(297, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:42:27'),
(298, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:43:26'),
(299, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:44:27'),
(300, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:45:02'),
(301, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:46:26'),
(302, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:47:27'),
(303, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:48:26'),
(304, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:49:27'),
(305, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:50:56'),
(306, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:51:26'),
(307, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:52:26'),
(308, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:53:27'),
(309, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:54:31'),
(310, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:55:56'),
(311, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:56:27'),
(312, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:57:28'),
(313, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:58:31'),
(314, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 01:59:29'),
(315, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:00:01'),
(316, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:01:26'),
(317, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:02:27'),
(318, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:03:27'),
(319, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:04:29'),
(320, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:05:57'),
(321, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:06:26'),
(322, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:07:26'),
(323, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:08:27'),
(324, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:09:27'),
(325, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:10:01'),
(326, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:11:26'),
(327, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:12:27'),
(328, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:13:26'),
(329, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:14:27'),
(330, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:15:02'),
(331, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:16:40'),
(332, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:17:27'),
(333, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:18:27'),
(334, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:19:26'),
(335, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:20:57'),
(336, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:21:26'),
(337, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:22:26'),
(338, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:23:27'),
(339, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:24:27'),
(340, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:25:57'),
(341, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:26:27'),
(342, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:27:26'),
(343, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:28:27'),
(344, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:29:26'),
(345, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:30:01'),
(346, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:31:26'),
(347, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:32:27'),
(348, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:33:26'),
(349, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:34:27'),
(350, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:35:57'),
(351, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:36:38'),
(352, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:37:34'),
(353, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:38:27'),
(354, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:39:26'),
(355, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:40:57'),
(356, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:41:27'),
(357, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:42:27'),
(358, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:43:27'),
(359, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:44:26'),
(360, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:45:01'),
(361, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:46:26'),
(362, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:47:27'),
(363, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:48:31'),
(364, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:49:27'),
(365, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:50:57'),
(366, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:51:26'),
(367, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:52:27'),
(368, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:53:27'),
(369, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:54:26'),
(370, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:55:56'),
(371, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:56:27'),
(372, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:57:36'),
(373, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:58:32'),
(374, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 02:59:27'),
(375, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:00:01'),
(376, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:01:27'),
(377, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:02:27'),
(378, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:03:27'),
(379, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:04:26'),
(380, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:05:56'),
(381, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:06:27'),
(382, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:07:26'),
(383, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:08:26'),
(384, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:09:27'),
(385, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:10:57'),
(386, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:11:26'),
(387, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:12:27'),
(388, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:13:26'),
(389, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:14:27'),
(390, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:15:01'),
(391, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:16:27'),
(392, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:17:41'),
(393, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:18:27'),
(394, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:19:27'),
(395, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:20:57'),
(396, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:21:26'),
(397, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:22:27'),
(398, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:23:27'),
(399, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:24:31'),
(400, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:25:57'),
(401, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:26:26'),
(402, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:27:27'),
(403, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:28:26'),
(404, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:29:26'),
(405, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:30:02'),
(406, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:31:26'),
(407, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:32:26'),
(408, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:33:27'),
(409, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:34:26'),
(410, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:35:56'),
(411, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:36:26'),
(412, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:37:27'),
(413, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:38:27'),
(414, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:39:26'),
(415, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:40:57'),
(416, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:41:26'),
(417, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:42:32'),
(418, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:43:26'),
(419, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:44:27'),
(420, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:45:01'),
(421, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:46:26'),
(422, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:47:27'),
(423, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:48:31'),
(424, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:49:27'),
(425, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:50:57'),
(426, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:51:27'),
(427, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:52:27'),
(428, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:53:27'),
(429, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:54:27'),
(430, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:55:57'),
(431, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:56:27'),
(432, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:57:27'),
(433, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:58:27'),
(434, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 03:59:27'),
(435, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:00:02'),
(436, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:01:26'),
(437, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:02:27'),
(438, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:03:27'),
(439, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:04:27'),
(440, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:05:57'),
(441, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:06:26'),
(442, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:07:26'),
(443, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:08:27'),
(444, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:09:27'),
(445, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:10:57'),
(446, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:11:26'),
(447, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:12:27'),
(448, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:13:27'),
(449, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:14:26'),
(450, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:15:01'),
(451, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:16:27'),
(452, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:17:26'),
(453, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:18:26'),
(454, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:19:26'),
(455, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:20:57'),
(456, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:21:27'),
(457, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:22:27'),
(458, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:23:26'),
(459, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:24:27'),
(460, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:25:57'),
(461, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:26:26'),
(462, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:27:27'),
(463, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:28:27'),
(464, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:29:26'),
(465, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:30:03'),
(466, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:31:27'),
(467, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:32:26'),
(468, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:33:27'),
(469, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:34:27'),
(470, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:35:57'),
(471, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:36:26'),
(472, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:37:27'),
(473, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:38:27'),
(474, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:39:26'),
(475, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:40:57'),
(476, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:41:27'),
(477, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:42:27'),
(478, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:43:27'),
(479, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:44:27'),
(480, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:45:01'),
(481, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:46:26'),
(482, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:47:26'),
(483, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:48:26'),
(484, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:49:26'),
(485, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:50:56'),
(486, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:51:27'),
(487, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:52:27'),
(488, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:53:27'),
(489, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:54:27'),
(490, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:55:57'),
(491, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:56:28'),
(492, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:57:27'),
(493, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:58:27'),
(494, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 04:59:37'),
(495, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:00:06'),
(496, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:01:27'),
(497, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:02:27'),
(498, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:03:31'),
(499, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:04:27'),
(500, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:05:56'),
(501, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:06:27'),
(502, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:07:26'),
(503, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:08:27'),
(504, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:09:27'),
(505, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:10:57'),
(506, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:11:26'),
(507, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:12:27'),
(508, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:13:26'),
(509, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:14:26'),
(510, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:15:01'),
(511, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:16:26'),
(512, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:17:26'),
(513, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:18:27'),
(514, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:19:27'),
(515, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:20:56'),
(516, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:21:27'),
(517, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:22:27'),
(518, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:23:27'),
(519, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:24:27'),
(520, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:25:56'),
(521, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:26:28'),
(522, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:27:26'),
(523, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:28:26'),
(524, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:29:27'),
(525, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:30:02'),
(526, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:31:26'),
(527, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:32:27'),
(528, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:33:26'),
(529, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:34:27'),
(530, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:35:57'),
(531, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:36:31'),
(532, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:37:27'),
(533, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:38:26'),
(534, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:39:27'),
(535, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:40:56'),
(536, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:41:26'),
(537, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:42:27'),
(538, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:43:26'),
(539, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:44:26'),
(540, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:45:01'),
(541, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:46:26'),
(542, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:47:27'),
(543, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:48:27'),
(544, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:49:28'),
(545, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:50:56'),
(546, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:51:26'),
(547, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:52:27'),
(548, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:53:27'),
(549, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:54:27'),
(550, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:55:56'),
(551, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:56:27'),
(552, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:57:26'),
(553, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:58:27'),
(554, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 05:59:34'),
(555, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:00:02'),
(556, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:01:27'),
(557, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:02:27'),
(558, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:03:27'),
(559, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:04:27'),
(560, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:05:57'),
(561, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:06:27'),
(562, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:07:26'),
(563, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:08:27'),
(564, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:09:27'),
(565, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:10:57'),
(566, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:11:26'),
(567, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:12:27'),
(568, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:13:27'),
(569, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:14:27'),
(570, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:15:01'),
(571, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:16:27'),
(572, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:17:43'),
(573, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:18:27'),
(574, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:19:27'),
(575, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:20:57'),
(576, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:21:26'),
(577, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:22:26'),
(578, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:23:26'),
(579, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:24:27'),
(580, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:25:56'),
(581, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:26:27'),
(582, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:27:26'),
(583, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:28:27'),
(584, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:29:27'),
(585, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:30:01'),
(586, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:31:26'),
(587, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:32:27'),
(588, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:33:27'),
(589, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:34:27'),
(590, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:35:54'),
(591, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:36:26'),
(592, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:37:26'),
(593, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:38:27'),
(594, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:39:27'),
(595, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:40:02'),
(596, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:41:27'),
(597, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:42:27'),
(598, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:43:27'),
(599, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:44:26'),
(600, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:45:02'),
(601, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:46:27'),
(602, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:47:27'),
(603, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:48:31'),
(604, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:49:26'),
(605, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:50:56'),
(606, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:51:27'),
(607, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:52:27'),
(608, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:53:39'),
(609, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:54:27'),
(610, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:55:56'),
(611, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:56:27'),
(612, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:57:26'),
(613, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:58:27'),
(614, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 06:59:27'),
(615, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:00:02'),
(616, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:01:26'),
(617, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:02:26'),
(618, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:03:27'),
(619, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:04:27'),
(620, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:05:57'),
(621, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:06:26'),
(622, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:07:26'),
(623, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:08:27'),
(624, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:09:27'),
(625, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:10:56'),
(626, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:11:28'),
(627, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:12:26'),
(628, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:13:27'),
(629, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:14:27'),
(630, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:15:56'),
(631, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:16:27'),
(632, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:17:27'),
(633, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:18:27'),
(634, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:19:26'),
(635, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:20:57'),
(636, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:21:26'),
(637, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:22:27'),
(638, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:23:27'),
(639, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:24:31'),
(640, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:25:56'),
(641, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:26:27'),
(642, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:27:27'),
(643, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:28:27'),
(644, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:29:26'),
(645, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:30:01'),
(646, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:31:33'),
(647, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:32:26'),
(648, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:33:27'),
(649, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:34:27'),
(650, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:35:57'),
(651, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:36:26'),
(652, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:37:27'),
(653, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:38:27'),
(654, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:39:27'),
(655, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:40:01'),
(656, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:41:27'),
(657, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:42:31'),
(658, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:43:26'),
(659, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:44:26'),
(660, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:45:02'),
(661, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:46:26'),
(662, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:47:27'),
(663, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:48:27'),
(664, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:49:26'),
(665, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:50:31'),
(666, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:51:26'),
(667, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:52:27'),
(668, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:53:27'),
(669, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:54:31'),
(670, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:55:56'),
(671, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:56:26'),
(672, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:57:27'),
(673, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:58:27'),
(674, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 07:59:26'),
(675, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:00:02'),
(676, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:01:26'),
(677, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:02:27'),
(678, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:03:27'),
(679, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:04:28'),
(680, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:05:57'),
(681, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:06:31'),
(682, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:07:27'),
(683, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:08:27'),
(684, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:09:27'),
(685, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:10:57'),
(686, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:11:27'),
(687, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:12:27'),
(688, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:13:27'),
(689, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:14:28'),
(690, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:15:01'),
(691, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:16:26'),
(692, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:17:27'),
(693, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:18:27'),
(694, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:19:27'),
(695, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:20:56'),
(696, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:21:28'),
(697, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:22:27'),
(698, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:23:27'),
(699, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:24:27'),
(700, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:25:57'),
(701, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:26:27'),
(702, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:27:31'),
(703, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:28:26'),
(704, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:29:27'),
(705, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:30:02'),
(706, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:31:26'),
(707, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:32:27'),
(708, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:33:26'),
(709, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:34:27'),
(710, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:35:57'),
(711, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:36:27'),
(712, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:37:27'),
(713, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:38:26'),
(714, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:39:27'),
(715, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:40:01'),
(716, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:41:27'),
(717, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:42:43'),
(718, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:43:35'),
(719, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:44:28'),
(720, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:45:02'),
(721, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:46:26'),
(722, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:47:26'),
(723, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:48:28'),
(724, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:49:27');
INSERT INTO `cron_logs` (`id`, `job_name`, `draws_found`, `draws_ok`, `draws_failed`, `detail`, `run_at`) VALUES
(725, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:50:57'),
(726, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:51:26'),
(727, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:52:27'),
(728, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:53:27'),
(729, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:54:27'),
(730, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:55:56'),
(731, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:56:28'),
(732, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:57:29'),
(733, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:58:27'),
(734, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 08:59:27'),
(735, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:00:03'),
(736, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:01:26'),
(737, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:02:27'),
(738, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:03:27'),
(739, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:04:27'),
(740, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:05:56'),
(741, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:06:27'),
(742, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:07:27'),
(743, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:08:27'),
(744, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:09:27'),
(745, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:10:32'),
(746, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:11:27'),
(747, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:12:27'),
(748, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:13:29'),
(749, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:14:27'),
(750, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:15:02'),
(751, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:16:28'),
(752, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:17:27'),
(753, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:18:27'),
(754, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:19:27'),
(755, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:20:57'),
(756, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:21:28'),
(757, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:22:27'),
(758, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:23:26'),
(759, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:24:27'),
(760, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:25:56'),
(761, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:26:31'),
(762, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:27:31'),
(763, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:28:27'),
(764, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:29:27'),
(765, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:30:02'),
(766, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:31:33'),
(767, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:32:27'),
(768, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:33:27'),
(769, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:34:26'),
(770, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:35:56'),
(771, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:36:31'),
(772, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:37:28'),
(773, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:38:27'),
(774, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:39:27'),
(775, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:40:57'),
(776, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:41:31'),
(777, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:42:26'),
(778, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:43:26'),
(779, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:44:27'),
(780, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:45:01'),
(781, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:46:27'),
(782, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:47:26'),
(783, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:48:32'),
(784, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:49:27'),
(785, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:50:56'),
(786, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:51:29'),
(787, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:52:28'),
(788, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:53:26'),
(789, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:54:31'),
(790, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:55:57'),
(791, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:56:26'),
(792, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:57:26'),
(793, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:58:26'),
(794, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 09:59:27'),
(795, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 10:00:01'),
(796, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 10:01:27'),
(797, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 10:02:27'),
(798, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 10:03:27'),
(799, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 10:04:26'),
(800, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 10:05:57'),
(801, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 10:06:28'),
(802, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 10:07:27'),
(803, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 10:08:27'),
(804, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 10:09:27'),
(805, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 10:10:56'),
(806, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 10:11:27'),
(807, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 10:12:27'),
(808, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 10:13:28'),
(809, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 10:14:31'),
(810, 'finalize-expired-draws', 0, 0, 0, NULL, '2026-07-10 10:15:02');

-- --------------------------------------------------------

--
-- Table structure for table `draws`
--

CREATE TABLE `draws` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `rules` text DEFAULT NULL,
  `prize_details` text DEFAULT NULL,
  `banner_image` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('pending','active','ended','completed','cancelled') NOT NULL DEFAULT 'pending',
  `activated_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'admin id who manually activated, NULL if cron activated',
  `activated_at` timestamp NULL DEFAULT NULL,
  `ended_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'admin id who manually ended, NULL if cron ended',
  `ended_at` timestamp NULL DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `winning_code` char(15) DEFAULT NULL,
  `winner_user_id` int(10) UNSIGNED DEFAULT NULL,
  `finalized_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'admin id',
  `finalized_at` timestamp NULL DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL COMMENT 'admin id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `draws`
--

INSERT INTO `draws` (`id`, `title`, `description`, `rules`, `prize_details`, `banner_image`, `category`, `status`, `activated_by`, `activated_at`, `ended_by`, `ended_at`, `start_date`, `end_date`, `winning_code`, `winner_user_id`, `finalized_by`, `finalized_at`, `created_by`, `created_at`, `updated_at`) VALUES
(5, 'Win 2GB Free data By Firstclass', '', '', '', NULL, 'Daily Patronage', 'completed', 1, '2026-07-13 15:46:36', 1, '2026-07-13 15:50:27', '2026-07-13 16:33:00', '2026-07-15 16:33:00', '009988998934538', 5, 1, '2026-07-13 15:58:11', 1, '2026-07-13 15:33:29', '2026-07-13 15:58:11'),
(6, 'Testing', '', '', '', NULL, 'Daily Patronage', 'cancelled', NULL, NULL, NULL, NULL, '2026-07-13 17:01:00', '2026-07-15 17:01:00', NULL, NULL, NULL, NULL, 1, '2026-07-13 16:01:45', '2026-07-13 16:02:06'),
(7, 'Testing23457', '', '', '', NULL, 'Dashboard Loyalty', 'ended', 1, '2026-07-18 19:22:09', 1, '2026-07-18 19:22:22', '2026-07-13 17:02:00', '2026-07-13 20:02:00', NULL, NULL, NULL, NULL, 1, '2026-07-13 16:02:38', '2026-07-18 19:22:22'),
(8, 'Micheal', '', 'you must be above 18', '', NULL, 'Government Ticket', 'ended', 1, '2026-07-18 19:34:07', 1, '2026-07-29 12:39:43', '2026-07-19 20:33:00', '2026-07-21 20:33:00', NULL, NULL, NULL, NULL, 1, '2026-07-18 19:33:54', '2026-07-29 12:39:43');

-- --------------------------------------------------------

--
-- Table structure for table `draw_entries`
--

CREATE TABLE `draw_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `draw_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `code_id` bigint(20) UNSIGNED NOT NULL,
  `entered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `draw_entries`
--

INSERT INTO `draw_entries` (`id`, `draw_id`, `user_id`, `code_id`, `entered_at`) VALUES
(1, 5, 5, 6, '2026-07-13 15:48:41'),
(2, 8, 6, 2, '2026-07-19 11:18:21'),
(3, 8, 6, 1, '2026-07-19 11:18:36'),
(4, 8, 6, 25, '2026-07-24 02:00:20'),
(5, 8, 6, 8, '2026-07-24 02:00:20'),
(6, 8, 6, 23, '2026-07-24 02:13:37'),
(7, 8, 5, 22, '2026-07-24 02:13:37'),
(8, 8, 6, 20, '2026-07-24 02:13:37');

-- --------------------------------------------------------

--
-- Table structure for table `draw_rankings`
--

CREATE TABLE `draw_rankings` (
  `id` int(10) UNSIGNED NOT NULL,
  `draw_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `rank_position` tinyint(3) UNSIGNED NOT NULL COMMENT '1=gold 2=silver 3=bronze',
  `user_code` char(15) NOT NULL,
  `matched_digits` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `entries_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `tiebreaker` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `draw_rankings`
--

INSERT INTO `draw_rankings` (`id`, `draw_id`, `user_id`, `rank_position`, `user_code`, `matched_digits`, `entries_count`, `tiebreaker`, `created_at`) VALUES
(1, 5, 0, 1, '089965742481458', 4, 1, NULL, '2026-07-13 15:58:11');

-- --------------------------------------------------------

--
-- Table structure for table `draw_reveal`
--

CREATE TABLE `draw_reveal` (
  `id` int(10) UNSIGNED NOT NULL,
  `draw_id` int(10) UNSIGNED NOT NULL,
  `revealed_digits` varchar(15) NOT NULL DEFAULT '',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `draw_winners`
--

CREATE TABLE `draw_winners` (
  `id` int(10) UNSIGNED NOT NULL,
  `draw_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `winning_code` char(15) NOT NULL,
  `user_code` char(15) DEFAULT NULL COMMENT 'Winner best matching code',
  `matched_digits` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `tiebreaker_used` varchar(100) DEFAULT NULL,
  `announced_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `draw_winners`
--

INSERT INTO `draw_winners` (`id`, `draw_id`, `user_id`, `winning_code`, `user_code`, `matched_digits`, `tiebreaker_used`, `announced_at`) VALUES
(1, 5, 5, '009988998934538', '089965742481458', 4, NULL, '2026-07-13 15:58:11');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','draw','transfer','redemption','vendor') NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, '👋 Welcome to ZoeFeeds!', 'Your account has been created. Redeem your first raffle code to get started!', 'info', 1, '2026-06-26 06:49:30'),
(2, 2, '👋 Welcome to ZoeFeeds!', 'Your account has been created. Redeem your first raffle code to get started!', 'info', 1, '2026-06-27 21:32:27'),
(3, 3, '👋 Welcome to ZoeFeeds!', 'Your account has been created. Redeem your first raffle code to get started!', 'info', 0, '2026-07-01 17:19:43'),
(4, 4, '👋 Welcome to ZoeFeeds!', 'Your account has been created. Redeem your first raffle code to get started!', 'info', 1, '2026-07-11 20:05:15'),
(5, 5, '👋 Welcome to ZoeFeeds!', 'Your account has been created. Redeem your first raffle code to get started!', 'info', 1, '2026-07-13 15:37:09'),
(6, 5, '🎟️ Code Redeemed', 'Code 089965742481458 has been added to your wallet successfully.', 'redemption', 1, '2026-07-13 15:45:23'),
(7, 5, 'Draw Entry', 'Entered 1 code(s) into: Win 2GB Free data By Firstclass', 'draw', 1, '2026-07-13 15:48:41'),
(8, 5, '🏆 You Won! — Win 2GB Free data By Firstclass', 'Congratulations Amarachi! Your code matched 4/15 digits of the winning number 009988998934538. Contact admin to claim your prize.', 'draw', 1, '2026-07-13 15:58:11'),
(9, 6, '👋 Welcome to ZoeFeeds!', 'Your account has been created. Redeem your first raffle code to get started!', 'info', 1, '2026-07-18 19:15:23'),
(10, 6, '🎟️ Code Redeemed', 'Code 726461454647767 has been added to your wallet successfully.', 'redemption', 1, '2026-07-18 19:18:47'),
(11, 1, 'Code Received', 'You received a code from Chidi Chizzy.', 'transfer', 0, '2026-07-18 19:20:36'),
(12, 6, '🎟️ Code Redeemed', 'Code 160307114441870 has been added to your wallet successfully.', 'redemption', 1, '2026-07-19 11:17:43'),
(13, 6, '🎟️ Code Redeemed', 'Code 528127292271057 has been added to your wallet successfully.', 'redemption', 1, '2026-07-19 11:18:05'),
(14, 6, '🎯 Entered Draw', 'Your code has been entered into \"Micheal\"', 'draw', 1, '2026-07-19 11:18:21'),
(15, 6, '🎯 Entered Draw', 'Your code has been entered into \"Micheal\"', 'draw', 1, '2026-07-19 11:18:36'),
(16, 6, '🎟️ Code Redeemed', 'Code 978735191405297 has been added to your wallet successfully.', 'redemption', 1, '2026-07-24 01:42:11'),
(17, 6, '🎟️ Code Redeemed', 'Code 941829158049712 has been added to your wallet successfully.', 'redemption', 1, '2026-07-24 01:43:04'),
(18, 7, '👋 Welcome to ZoeFeeds!', 'Your account has been created. Redeem your first raffle code to get started!', 'info', 1, '2026-07-24 01:51:10'),
(19, 7, '🎟️ Code Redeemed', 'Code 436252604981172 has been added to your wallet successfully.', 'redemption', 1, '2026-07-24 01:51:29'),
(20, 6, 'Code Received', 'You received a code from okechukwu chiamaka.', 'transfer', 1, '2026-07-24 01:52:54'),
(21, 6, '🎯 Entered Draw', 'You entered \"Micheal\" with 2 codes.', 'draw', 0, '2026-07-24 02:00:20'),
(22, 6, '🎟️ Code Redeemed', 'Code 871374296782151 has been added to your wallet successfully.', 'redemption', 0, '2026-07-24 02:12:55'),
(23, 6, '🎟️ Code Redeemed', 'Code 600236169007592 has been added to your wallet successfully.', 'redemption', 0, '2026-07-24 02:13:05'),
(24, 6, '🎟️ Code Redeemed', 'Code 092894904869894 has been added to your wallet successfully.', 'redemption', 0, '2026-07-24 02:13:14'),
(25, 6, '🎯 Entered Draw', 'You entered \"Micheal\" with 3 codes.', 'draw', 0, '2026-07-24 02:13:37');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_type` enum('user','vendor') NOT NULL DEFAULT 'user',
  `record_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(200) NOT NULL,
  `pin_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_type`, `record_id`, `email`, `pin_hash`, `expires_at`, `used`, `created_at`) VALUES
(1, 'user', 1, 'spotwebdev.com@gmail.com', '$2y$10$mLXdYo/cc1MImFlui/oxleWGzH85gkVSDvnjGkCdsKcMoWEsbhcxC', '2026-06-29 07:19:23', 1, '2026-06-29 06:04:23'),
(2, 'user', 1, 'spotwebdev.com@gmail.com', '$2y$10$QyiKhLbbwJfARLFE0hdY8urcVnVLeOXksKICnKz7MkycCnGkTUvBy', '2026-06-29 07:23:09', 0, '2026-06-29 06:08:09'),
(3, 'user', 2, 'mmerisinayahoshea@gmail.com', '$2y$10$T1vEEgOdzSNuDmFPt9VhB.0XcVKkycrkN2JVcHhyXsgo0zs7RRZCG', '2026-06-29 23:27:05', 0, '2026-06-29 22:12:05');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(10) DEFAULT NULL COMMENT 'Emoji or icon code',
  `color_class` varchar(100) DEFAULT NULL COMMENT 'CSS gradient classes',
  `link_url` varchar(500) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `slides`
--

CREATE TABLE `slides` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `link_url` varchar(500) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `slides`
--

INSERT INTO `slides` (`id`, `title`, `image_path`, `link_url`, `sort_order`, `status`, `created_by`, `created_at`) VALUES
(1, '', 'slide-6a40493972a80.jpeg', '', 0, 'active', 1, '2026-06-27 22:05:45'),
(2, '', 'slide-6a41a2dd1accd.png', '', 0, 'active', 1, '2026-06-28 22:40:29'),
(3, '', 'slide-6a41a50754d59.jpeg', '', 0, 'active', 1, '2026-06-28 22:49:43'),
(4, '', 'slide-6a41a5dbd0cfa.jpeg', '', 0, 'active', 1, '2026-06-28 22:53:15');

-- --------------------------------------------------------

--
-- Table structure for table `super_admins`
--

CREATE TABLE `super_admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(200) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `super_admins`
--

INSERT INTO `super_admins` (`id`, `full_name`, `email`, `password`, `created_at`) VALUES
(1, 'Super Administrator', 'superadmin@zoefeeds.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-05-30 10:07:53');

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `role_title` varchar(150) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `content` text NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `display_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `testimonials`
--

INSERT INTO `testimonials` (`id`, `user_id`, `full_name`, `role_title`, `photo`, `rating`, `content`, `status`, `display_order`, `created_by`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Ezea Ugochukwu micheal', 'New York', NULL, 4, '\"Got my codes through an eligible purchase, entered the draw, and won. The notification came same day. Easy process and everything was verified properly.', 'active', 2, 1, '2026-07-24 01:31:15', '2026-07-24 01:31:15'),
(2, NULL, 'Kwame Mensah Owusu', 'Enugu Nigeria', 'testimonial-6a62c0f07cd44.png', 5, 'Got my codes through an eligible purchase, entered the draw, and won. The notification came same day. Easy process and everything was verified properly.', 'active', 0, 1, '2026-07-24 01:33:36', '2026-07-24 01:33:36');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `category` enum('redemption','transfer_in','transfer_out','draw_entry','vendor_credit','draw_deduction') NOT NULL,
  `amount` int(11) NOT NULL DEFAULT 1 COMMENT 'Number of codes',
  `code_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `type`, `category`, `amount`, `code_id`, `reference_id`, `description`, `created_at`) VALUES
(5, 5, 'credit', 'redemption', 1, 6, NULL, 'Code redeemed: 089965742481458', '2026-07-13 15:45:23'),
(6, 5, 'debit', 'draw_entry', 1, 6, NULL, 'Draw entry: Win 2GB Free data By Firstclass', '2026-07-13 15:48:41'),
(7, 6, 'credit', 'redemption', 1, 13, NULL, 'Code redeemed: 726461454647767', '2026-07-18 19:18:47'),
(8, 6, 'debit', 'transfer_out', 1, 13, NULL, 'Transfer to: Ezea Ugochukwu micheal', '2026-07-18 19:20:36'),
(9, 1, 'credit', 'transfer_in', 1, 13, NULL, 'Transfer from: Chidi Chizzy', '2026-07-18 19:20:36'),
(10, 6, 'credit', 'redemption', 1, 1, NULL, 'Code redeemed: 160307114441870', '2026-07-19 11:17:43'),
(11, 6, 'credit', 'redemption', 1, 2, NULL, 'Code redeemed: 528127292271057', '2026-07-19 11:18:05'),
(12, 6, 'credit', 'redemption', 1, 7, NULL, 'Code redeemed: 978735191405297', '2026-07-24 01:42:11'),
(13, 6, 'credit', 'redemption', 1, 8, NULL, 'Code redeemed: 941829158049712', '2026-07-24 01:43:04'),
(14, 7, 'credit', 'redemption', 1, 25, NULL, 'Code redeemed: 436252604981172', '2026-07-24 01:51:29'),
(15, 7, 'debit', 'transfer_out', 1, 25, NULL, 'Transfer to: Chidi Chizzy', '2026-07-24 01:52:54'),
(16, 6, 'credit', 'transfer_in', 1, 25, NULL, 'Transfer from: okechukwu chiamaka', '2026-07-24 01:52:54'),
(17, 6, 'credit', 'redemption', 1, 20, NULL, 'Code redeemed: 871374296782151', '2026-07-24 02:12:55'),
(18, 6, 'credit', 'redemption', 1, 22, NULL, 'Code redeemed: 600236169007592', '2026-07-24 02:13:05'),
(19, 6, 'credit', 'redemption', 1, 23, NULL, 'Code redeemed: 092894904869894', '2026-07-24 02:13:14');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `uuid` char(36) NOT NULL,
  `phone` varchar(20) NOT NULL COMMENT 'Normalized: 234XXXXXXXXXX',
  `full_name` varchar(150) NOT NULL,
  `email` varchar(200) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `transfer_pin` varchar(255) DEFAULT NULL COMMENT 'Hashed 4-digit PIN',
  `balance` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of active codes',
  `status` enum('active','suspended','banned') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `uuid`, `phone`, `full_name`, `email`, `password`, `transfer_pin`, `balance`, `status`, `created_at`, `updated_at`) VALUES
(1, 'fd7ed54a-838f-11f1-86d1-5065f3b89ebb', '2348108833188', 'Ezea Ugochukwu micheal', 'spotwebdev.com@gmail.com', '$2y$12$WnUi1pQIHuBJJysdY27SvuW/X9ScnBgpSMf9A6Q4xoiuaOj5KT4OO', NULL, 1, 'active', '2026-06-26 06:49:30', '2026-07-19 16:36:29'),
(2, 'fd7ee3b7-838f-11f1-86d1-5065f3b89ebb', '2348107492297', 'Obinwa Daniel Nmerisinayahoshea', 'mmerisinayahoshea@gmail.com', '$2y$12$qO4XZkFbtH5Y6IRhwJxHC.yuC6yWmaxd.OZNH8g0zgupltT3B/uWS', NULL, 0, 'active', '2026-06-27 21:32:27', '2026-07-19 16:36:29'),
(3, 'fd7ee4bd-838f-11f1-86d1-5065f3b89ebb', '2347075848486', 'Micheal okechukwi', 'michealokechukwu3555@gmail.com', '$2y$12$mLzah7Mte/KrkElJaepZhe6vJrDzddv.j3IDgnlpvdXR1wbm6ZklK', NULL, 0, 'active', '2026-07-01 17:19:43', '2026-07-19 16:36:29'),
(4, 'fd7ee555-838f-11f1-86d1-5065f3b89ebb', '2348148860514', 'Alaoma Chinemeze Benjamin', 'alaomabenjamin180@gmail.com', '$2y$12$u5gzgCENxRVugv.fqAm6au801Z8oiQhhim5dINsogYNDkznsbgAGO', NULL, 0, 'active', '2026-07-11 20:05:15', '2026-07-19 16:36:29'),
(5, 'fd7ee5e1-838f-11f1-86d1-5065f3b89ebb', '2347060507980', 'Amarachi', 'amarachi@gmail.com', '$2y$12$dseu.CgtorlUakG84QKgDORSyNZvwrmon7WlPaSyaEFfvrClkqnFy', NULL, 0, 'active', '2026-07-13 15:37:09', '2026-07-19 16:36:29'),
(6, 'fd7ee677-838f-11f1-86d1-5065f3b89ebb', '2347047548914', 'Chidi Chizzy', 'chizzy@gmail.com', '$2y$12$DQq0Se0Qgsn7t3GWFQ41JunJc9e2bz9vCQ7oFK8O.bAmbWDgNBPxS', '$2y$10$ZDKOfNAhPKkXD3u9//2O8eZC.Wu7qcI8V4LaOqOV4zmoo5sS9EHkG', 8, 'active', '2026-07-18 19:15:23', '2026-07-24 02:13:14'),
(7, '8149f287-7344-478a-9b35-9f832116b8be', '2348128833188', 'okechukwu chiamaka', 'okechukwu@gmail.com', '$2y$12$F.VMQRQO41kK8O/BI5wlVORlLgjBZSaR5ctiNr8Jn8.PZboApi2Ui', '$2y$10$Sd9Z7uCmCXDbPlGFLfOIDu8oTKskLhdk4hJJa.Q0DVemuVY.XJSkG', 0, 'active', '2026-07-24 01:51:10', '2026-07-24 01:52:54');

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int(10) UNSIGNED NOT NULL,
  `phone` varchar(20) NOT NULL COMMENT 'Normalized: 234XXXXXXXXXX — must differ from users.phone',
  `full_name` varchar(150) NOT NULL,
  `email` varchar(200) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `business_name` varchar(200) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `reason` text DEFAULT NULL COMMENT 'Application reason',
  `status` enum('pending','active','suspended','rejected') NOT NULL DEFAULT 'pending',
  `code_balance` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Codes currently held by vendor',
  `public_key` varchar(64) DEFAULT NULL,
  `secret_key` varchar(128) DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'admin id',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`id`, `phone`, `full_name`, `email`, `password`, `business_name`, `bio`, `reason`, `status`, `code_balance`, `public_key`, `secret_key`, `approved_by`, `applied_at`, `approved_at`, `created_at`, `updated_at`) VALUES
(1, '2347047548913', 'Vendor One', 'giftchinenyenwa1@gmail.com', '$2y$12$KcpLEduijCF/FAKZpolEM.DlssZIqSuZ1NIqUYBkCXgBipMhESmUC', 'SPOTWEB COM', NULL, '', 'active', 2, NULL, NULL, NULL, '2026-06-26 06:50:18', NULL, '2026-06-26 06:50:18', '2026-07-19 11:18:05'),
(2, '2347047548912', 'Victor Eze', 'victor@gmail.com', '$2y$12$zSL5b78RIyMfSEkREEFP6ut0xiidRB2b3oVDpZ3K55ObJRL70UwIS', 'SPOTWEB TECH', NULL, '', 'active', 1, 'zf_pub_7d0d6101e5655ee3f2d48edbf8af8b7a', '$2y$10$UOEnZn8g8eebwyTd3pUS/.S5RAtTqOJvcVKfpPtBz2lGLIHqm61zW', 1, '2026-06-27 08:26:40', '2026-06-27 08:27:20', '2026-06-27 08:26:40', '2026-07-13 15:45:23'),
(3, '2348082977719', 'ZOEFEEDS CORDINATOR', 'zoefeedsofficial@gmail.com', '$2y$12$sMXizx622fE2H82VM8NLse2NGT7P8Tx3fWC4SjFJvm/n8OVgdk0.a', 'ZoeFeeds Services', NULL, 'I retails zoefeeds products and gift the codes to my customers and spread the gift code in my area and I also want to share my gifted codes for free to whoever I want to gift it as just a gift!', 'active', 13, 'zf_pub_47c89a582a06016f9feb82953f6dbb1e', '$2y$10$WaKNNnheMt6Iu1ECs6Tcr.R2H2uunXk0oU/J8eSdrc5odyvb6Biwi', 1, '2026-07-06 18:55:53', '2026-07-06 19:14:13', '2026-07-06 18:55:53', '2026-07-24 02:13:14'),
(4, '2349028157337', 'Alaoma Benjamin', 'alaomabenjamin801@gmail.com', '$2y$12$k/fw7DPbVRZ6Mf4NyQ9aZuDBNplKN73O7uadZQZW8TnI9TofkCBjq', 'De AI Empire and Creativity World', NULL, 'I usually run a small online business and want to recommend the app to them so that they can be using it to do every of their online buying like Electricity, Data, Airtime...e.t.c', 'rejected', 0, 'zf_pub_11d27e4e98592df6da8183afdfe32325', '$2y$10$7gUSxbq9yiA732gVF5g1bupZsLs3.bI1.sXW0YrW6LFBOnALUxkIW', NULL, '2026-07-11 20:20:18', NULL, '2026-07-11 20:20:18', '2026-07-11 20:52:27');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_applications`
--

CREATE TABLE `vendor_applications` (
  `id` int(10) UNSIGNED NOT NULL,
  `vendor_id` int(10) UNSIGNED NOT NULL COMMENT 'vendors.id — the vendor who submitted this application',
  `business_name` varchar(200) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `review_note` text DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendor_applications`
--

INSERT INTO `vendor_applications` (`id`, `vendor_id`, `business_name`, `reason`, `status`, `reviewed_by`, `review_note`, `applied_at`, `reviewed_at`) VALUES
(3, 2, 'SPOTWEB TECH', '', 'approved', 1, 'Your Request is granted', '2026-06-27 08:26:40', '2026-06-27 08:27:20'),
(4, 3, 'ZoeFeeds Services', 'I retails zoefeeds products and gift the codes to my customers and spread the gift code in my area and I also want to share my gifted codes for free to whoever I want to gift it as just a gift!', 'approved', 1, 'We are we 😂', '2026-07-06 18:55:53', '2026-07-06 19:14:13'),
(5, 4, 'De AI Empire and Creativity World', 'I usually run a small online business and want to recommend the app to them so that they can be using it to do every of their online buying like Electricity, Data, Airtime...e.t.c', 'rejected', 1, 'Not yet sir we will put some things in place before we start this project you are talking about sir, thank you sir AI', '2026-07-11 20:20:18', '2026-07-11 20:52:27');

-- --------------------------------------------------------

--
-- Table structure for table `_users_backup_before_split`
--

CREATE TABLE `_users_backup_before_split` (
  `id` int(10) UNSIGNED NOT NULL,
  `phone` varchar(20) NOT NULL COMMENT 'Normalized: 234XXXXXXXXXX',
  `full_name` varchar(150) NOT NULL,
  `email` varchar(200) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `transfer_pin` varchar(255) DEFAULT NULL COMMENT 'Hashed 4-digit PIN',
  `balance` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of active codes',
  `status` enum('active','suspended','banned') NOT NULL DEFAULT 'active',
  `is_vendor` tinyint(1) NOT NULL DEFAULT 0,
  `vendor_status` enum('pending','active','suspended','rejected') DEFAULT NULL,
  `vendor_business_name` varchar(200) DEFAULT NULL,
  `vendor_bio` text DEFAULT NULL,
  `vendor_code_balance` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `vendor_public_key` varchar(64) DEFAULT NULL,
  `vendor_secret_key` varchar(128) DEFAULT NULL,
  `vendor_applied_at` timestamp NULL DEFAULT NULL,
  `vendor_approved_at` timestamp NULL DEFAULT NULL,
  `vendor_approved_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'admin id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email` (`email`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_actor` (`actor_type`,`actor_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `codes`
--
ALTER TABLE `codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_code` (`code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_vendor` (`assigned_vendor`),
  ADD KEY `idx_owner` (`current_owner`),
  ADD KEY `idx_batch` (`batch_id`);

--
-- Indexes for table `code_redemptions`
--
ALTER TABLE `code_redemptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_code` (`code_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `code_transfers`
--
ALTER TABLE `code_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_code` (`code_id`),
  ADD KEY `idx_from` (`from_user_id`),
  ADD KEY `idx_to` (`to_user_id`);

--
-- Indexes for table `cron_logs`
--
ALTER TABLE `cron_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job` (`job_name`),
  ADD KEY `idx_run` (`run_at`);

--
-- Indexes for table `draws`
--
ALTER TABLE `draws`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Indexes for table `draw_entries`
--
ALTER TABLE `draw_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_draw_code` (`draw_id`,`code_id`),
  ADD KEY `idx_draw` (`draw_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `code_id` (`code_id`);

--
-- Indexes for table `draw_rankings`
--
ALTER TABLE `draw_rankings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_draw_rank` (`draw_id`,`rank_position`),
  ADD KEY `idx_draw` (`draw_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `draw_reveal`
--
ALTER TABLE `draw_reveal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_draw` (`draw_id`);

--
-- Indexes for table `draw_winners`
--
ALTER TABLE `draw_winners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_draw` (`draw_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`,`sort_order`);

--
-- Indexes for table `slides`
--
ALTER TABLE `slides`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status_order` (`status`,`sort_order`);

--
-- Indexes for table `super_admins`
--
ALTER TABLE `super_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email` (`email`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_testimonial_user` (`user_id`),
  ADD KEY `fk_testimonial_admin` (`created_by`),
  ADD KEY `idx_testimonial_status` (`status`),
  ADD KEY `idx_testimonial_order` (`display_order`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_type` (`type`,`category`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_phone` (`phone`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vendor_phone` (`phone`),
  ADD UNIQUE KEY `uq_vendor_public_key` (`public_key`),
  ADD KEY `idx_vendor_status` (`status`),
  ADD KEY `idx_vendor_created` (`created_at`);

--
-- Indexes for table `vendor_applications`
--
ALTER TABLE `vendor_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_vendor` (`vendor_id`);

--
-- Indexes for table `_users_backup_before_split`
--
ALTER TABLE `_users_backup_before_split`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_phone` (`phone`),
  ADD UNIQUE KEY `vendor_public_key` (`vendor_public_key`),
  ADD UNIQUE KEY `uq_vendor_public_key` (`vendor_public_key`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_is_vendor` (`is_vendor`,`vendor_status`),
  ADD KEY `idx_created` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=151;

--
-- AUTO_INCREMENT for table `codes`
--
ALTER TABLE `codes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `code_redemptions`
--
ALTER TABLE `code_redemptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `code_transfers`
--
ALTER TABLE `code_transfers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cron_logs`
--
ALTER TABLE `cron_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=811;

--
-- AUTO_INCREMENT for table `draws`
--
ALTER TABLE `draws`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `draw_entries`
--
ALTER TABLE `draw_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `draw_rankings`
--
ALTER TABLE `draw_rankings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `draw_reveal`
--
ALTER TABLE `draw_reveal`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `draw_winners`
--
ALTER TABLE `draw_winners`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `slides`
--
ALTER TABLE `slides`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `super_admins`
--
ALTER TABLE `super_admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `vendor_applications`
--
ALTER TABLE `vendor_applications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `_users_backup_before_split`
--
ALTER TABLE `_users_backup_before_split`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `code_redemptions`
--
ALTER TABLE `code_redemptions`
  ADD CONSTRAINT `code_redemptions_ibfk_1` FOREIGN KEY (`code_id`) REFERENCES `codes` (`id`),
  ADD CONSTRAINT `code_redemptions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `code_transfers`
--
ALTER TABLE `code_transfers`
  ADD CONSTRAINT `code_transfers_ibfk_1` FOREIGN KEY (`code_id`) REFERENCES `codes` (`id`),
  ADD CONSTRAINT `code_transfers_ibfk_2` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `code_transfers_ibfk_3` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `draw_entries`
--
ALTER TABLE `draw_entries`
  ADD CONSTRAINT `draw_entries_ibfk_1` FOREIGN KEY (`draw_id`) REFERENCES `draws` (`id`),
  ADD CONSTRAINT `draw_entries_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `draw_entries_ibfk_3` FOREIGN KEY (`code_id`) REFERENCES `codes` (`id`);

--
-- Constraints for table `draw_reveal`
--
ALTER TABLE `draw_reveal`
  ADD CONSTRAINT `draw_reveal_ibfk_1` FOREIGN KEY (`draw_id`) REFERENCES `draws` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `draw_winners`
--
ALTER TABLE `draw_winners`
  ADD CONSTRAINT `draw_winners_ibfk_1` FOREIGN KEY (`draw_id`) REFERENCES `draws` (`id`),
  ADD CONSTRAINT `draw_winners_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD CONSTRAINT `fk_testimonial_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_testimonial_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `vendor_applications`
--
ALTER TABLE `vendor_applications`
  ADD CONSTRAINT `vendor_applications_fk_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
