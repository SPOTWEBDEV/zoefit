-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 25, 2026 at 03:56 PM
-- Server version: 10.11.18-MariaDB-cll-lve
-- PHP Version: 8.4.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `zoefeeds_db`
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
(1, 'user', 1, 'logout', 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 10:13:57'),
(2, 'user', 1, 'register', 'New user registered', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 10:14:16'),
(3, 'user', 1, 'login', 'User logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 10:14:24'),
(4, 'user', 1, 'vendor_apply', 'Vendor application: SPOTWEB COM', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 10:16:34'),
(5, 'user', 1, 'logout', 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 10:22:21'),
(6, 'user', 2, 'register', 'New user registered', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 10:23:08'),
(7, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-30 10:24:07'),
(8, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-01 09:29:46'),
(9, 'user', 1, 'login', 'User logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-01 10:49:06'),
(10, 'admin', 1, 'approve_vendor', 'Approved vendor: Ezea Ugochukwu micheal', 'user', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-01 10:49:20'),
(11, 'user', 1, 'generate_api_keys', 'Vendor generated new API keys', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-01 10:50:02'),
(12, 'admin', 1, 'generate_codes', 'Generated 1 codes, batch: BATCH-20260601T115214-F8EDDD → assigned to vendor 1 (Ezea Ugochukwu micheal)', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-01 10:52:14'),
(13, 'user', 3, 'register', 'New user registered', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-01 10:53:20'),
(14, 'user', 3, 'login', 'User logged in', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-01 10:53:25'),
(15, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.102.142', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-02 16:17:06'),
(16, 'user', 4, 'register', 'New user registered', NULL, NULL, '102.90.102.142', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-02 17:02:20'),
(17, 'user', 4, 'login', 'User logged in', NULL, NULL, '102.90.102.142', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-02 17:02:55'),
(18, 'user', 4, 'update_profile', 'Profile updated', NULL, NULL, '102.90.102.142', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-02 17:04:38'),
(19, 'user', 4, 'update_profile', 'Profile updated', NULL, NULL, '102.90.102.142', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-02 17:05:40'),
(20, 'user', 4, 'login', 'User logged in', NULL, NULL, '102.90.103.223', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-03 21:07:43'),
(21, 'user', 4, 'set_pin', 'Transfer PIN updated', NULL, NULL, '102.90.103.223', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-03 21:15:20'),
(22, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.103.223', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-03 22:04:51'),
(23, 'user', 4, 'login', 'User logged in', NULL, NULL, '102.90.103.223', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-03 22:22:34'),
(24, 'user', 4, 'vendor_apply', 'Vendor application: Daniel services', NULL, NULL, '102.90.103.223', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-03 22:23:52'),
(25, 'user', 4, 'login', 'User logged in', NULL, NULL, '105.112.212.235', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-07 15:45:54'),
(26, 'user', 4, 'login', 'User logged in', NULL, NULL, '105.112.106.218', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-07 16:55:08'),
(27, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '105.112.106.226', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-08 00:21:57'),
(28, 'admin', 1, 'create_draw', 'Draw \'1 week Free Transport award\' created', 'draw', 1, '105.112.106.226', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-08 00:31:09'),
(29, 'user', 4, 'login', 'User logged in', NULL, NULL, '105.112.106.226', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-08 00:31:45'),
(30, 'admin', 1, 'pause_draw', 'Draw 1 pause-d', 'draw', 1, '105.112.106.226', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-08 00:37:15'),
(31, 'admin', 1, 'activate_draw', 'Draw 1 activate-d', 'draw', 1, '105.112.106.226', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-08 00:58:44'),
(32, 'admin', 1, 'edit_draw', 'Draw 1 updated', 'draw', 1, '105.112.106.226', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-08 00:59:52'),
(33, 'user', 4, 'login', 'User logged in', NULL, NULL, '105.112.217.56', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-10 20:27:34'),
(34, 'user', 5, 'register', 'New user registered', NULL, NULL, '105.115.8.201', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', '2026-06-11 14:10:05'),
(35, 'user', 5, 'login', 'User logged in', NULL, NULL, '105.115.8.201', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', '2026-06-11 14:11:11'),
(36, 'user', 4, 'login', 'User logged in', NULL, NULL, '102.88.114.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-18 07:08:48'),
(37, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.88.114.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-18 07:12:18'),
(38, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.88.114.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-18 07:20:04'),
(39, 'admin', 1, 'edit_draw', 'Draw 1 updated', 'draw', 1, '102.88.114.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-18 07:26:37'),
(40, 'user', 4, 'login', 'User logged in', NULL, NULL, '102.88.114.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-18 07:27:45'),
(41, 'admin', 1, 'approve_vendor_request', 'Vendor approved: Obinwa Daniel Mmerisinayahoshea', 'user', 4, '102.88.114.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-18 07:44:15'),
(42, 'user', 1, 'login', 'User logged in', NULL, NULL, '105.112.210.126', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-21 04:53:16'),
(43, 'user', 4, 'login', 'User logged in', NULL, NULL, '102.90.102.210', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-21 08:34:17'),
(44, 'user', 6, 'register', 'New user registered', NULL, NULL, '197.210.226.94', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-06-21 21:47:53'),
(45, 'user', 7, 'register', 'New user registered', NULL, NULL, '105.118.9.172', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-06-21 21:48:19'),
(46, 'user', 4, 'login', 'User logged in', NULL, NULL, '102.90.99.98', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-21 21:50:05'),
(47, 'user', 8, 'register', 'New user registered', NULL, NULL, '102.90.81.37', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-06-21 21:50:11'),
(48, 'user', 9, 'register', 'New user registered', NULL, NULL, '105.118.9.172', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-06-21 21:52:58'),
(49, 'user', 8, 'login', 'User logged in', NULL, NULL, '102.90.81.37', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-06-21 21:53:25'),
(50, 'user', 6, 'login', 'User logged in', NULL, NULL, '102.90.96.159', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-06-21 21:53:28'),
(51, 'user', 9, 'login', 'User logged in', NULL, NULL, '105.118.9.172', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-06-21 21:54:26'),
(52, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.99.98', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-21 21:57:34'),
(53, 'admin', 1, 'generate_codes', 'Generated 3 codes, batch: BATCH-20260621T231016-D540F1 → vendor 4', NULL, NULL, '102.90.99.98', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-21 22:10:16'),
(54, 'admin', 1, 'generate_codes', 'Generated 9 codes, batch: BATCH-20260621T231042-C3725A → vendor 4', NULL, NULL, '102.90.99.98', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-21 22:10:42'),
(55, 'user', 8, 'login', 'User logged in', NULL, NULL, '102.90.81.37', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-06-21 22:33:05'),
(56, 'user', 9, 'login', 'User logged in', NULL, NULL, '105.118.9.172', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-06-21 22:36:19'),
(57, 'user', 6, 'login', 'User logged in', NULL, NULL, '197.210.55.9', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-06-21 22:36:37'),
(58, 'user', 8, 'redeem_code', 'Code redeemed: 483770099264026 (was: assigned)', 'code', 13, '102.90.81.37', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-06-21 22:36:50'),
(59, 'user', 6, 'redeem_code', 'Code redeemed: 412821672608834 (was: assigned)', 'code', 7, '197.210.55.9', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-06-21 22:39:54'),
(60, 'user', 8, 'redeem_code', 'Code redeemed: 784804371908732 (was: assigned)', 'code', 12, '102.90.81.37', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-06-21 22:40:02'),
(61, 'user', 9, 'redeem_code', 'Code redeemed: 443807929056379 (was: assigned)', 'code', 10, '105.118.9.172', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-06-21 22:40:37'),
(62, 'user', 8, 'redeem_code', 'Code redeemed: 244781514184815 (was: assigned)', 'code', 11, '102.90.81.37', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-06-21 22:40:56'),
(63, 'user', 6, 'redeem_code', 'Code redeemed: 166964828463667 (was: assigned)', 'code', 5, '197.210.55.9', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-06-21 22:41:27'),
(64, 'user', 9, 'redeem_code', 'Code redeemed: 235702494688956 (was: assigned)', 'code', 9, '105.118.9.172', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-06-21 22:44:36'),
(65, 'user', 9, 'redeem_code', 'Code redeemed: 284864665166456 (was: assigned)', 'code', 8, '105.118.9.172', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-06-21 22:45:06'),
(66, 'user', 6, 'redeem_code', 'Code redeemed: 174315652370040 (was: assigned)', 'code', 6, '197.210.55.9', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-06-21 22:46:42'),
(67, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '105.112.106.142', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-06-21 22:50:05'),
(68, 'user', 10, 'register', 'New user registered', NULL, NULL, '102.90.82.234', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Mobile Safari/537.36', '2026-06-21 23:10:08'),
(69, 'user', 10, 'login', 'User logged in', NULL, NULL, '102.90.82.234', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Mobile Safari/537.36', '2026-06-21 23:11:18'),
(70, 'user', 10, 'update_profile', 'Profile updated', NULL, NULL, '102.90.82.234', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Mobile Safari/537.36', '2026-06-21 23:15:37'),
(71, 'user', 4, 'login', 'User logged in', NULL, NULL, '102.90.99.98', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-21 23:45:02'),
(72, 'user', 10, 'login', 'User logged in', NULL, NULL, '102.90.82.234', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Mobile Safari/537.36', '2026-06-21 23:58:27'),
(73, 'user', 10, 'redeem_code', 'Code redeemed: 947399346999177 (was: assigned)', 'code', 2, '102.90.82.234', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Mobile Safari/537.36', '2026-06-21 23:59:26'),
(74, 'user', 10, 'redeem_code', 'Code redeemed: 363534120536300 (was: assigned)', 'code', 3, '102.90.82.234', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Mobile Safari/537.36', '2026-06-22 00:00:37'),
(75, 'user', 10, 'redeem_code', 'Code redeemed: 155482336964843 (was: assigned)', 'code', 4, '102.90.82.234', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Mobile Safari/537.36', '2026-06-22 00:01:51'),
(76, 'user', 4, 'login', 'User logged in', NULL, NULL, '102.90.102.163', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-22 06:14:12'),
(77, 'user', 4, 'login', 'User logged in', NULL, NULL, '102.90.101.33', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-22 19:24:13'),
(78, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.101.33', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-22 19:25:43'),
(79, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.117.236', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-22 22:51:13'),
(80, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.90.117.236', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-22 22:53:09'),
(81, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.88.112.120', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/147.0.7727.99 Mobile/15E148 Safari/604.1', '2026-06-23 18:46:16'),
(82, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '197.210.54.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-25 01:34:24'),
(83, 'admin', 1, 'login', 'Admin logged in', NULL, NULL, '102.88.109.184', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_9 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) GSA/423.5.920392540 Mobile/15E148 Safari/604.1', '2026-06-25 09:46:39');

-- --------------------------------------------------------

--
-- Table structure for table `codes`
--

CREATE TABLE `codes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` char(15) NOT NULL COMMENT '15-digit numeric raffle code',
  `status` enum('unassigned','assigned','distributed','redeemed','reserved','used','transferred') NOT NULL DEFAULT 'unassigned',
  `generated_by` int(10) UNSIGNED NOT NULL COMMENT 'admin id',
  `assigned_vendor` int(10) UNSIGNED DEFAULT NULL COMMENT 'user id of vendor (is_vendor=1)',
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
(1, '940315429191439', 'assigned', 1, 1, NULL, 'BATCH-20260601T115214-F8EDDD', '2026-06-01 10:52:14', '2026-06-01 10:52:14', NULL),
(2, '947399346999177', 'redeemed', 1, 4, 10, 'BATCH-20260621T231016-D540F1', '2026-06-21 22:10:16', '2026-06-21 20:10:16', '2026-06-21 23:59:26'),
(3, '363534120536300', 'redeemed', 1, 4, 10, 'BATCH-20260621T231016-D540F1', '2026-06-21 22:10:16', '2026-06-21 20:10:16', '2026-06-22 00:00:37'),
(4, '155482336964843', 'redeemed', 1, 4, 10, 'BATCH-20260621T231016-D540F1', '2026-06-21 22:10:16', '2026-06-21 20:10:16', '2026-06-22 00:01:51'),
(5, '166964828463667', 'redeemed', 1, 4, 6, 'BATCH-20260621T231042-C3725A', '2026-06-21 22:10:42', '2026-06-21 20:10:42', '2026-06-21 22:41:27'),
(6, '174315652370040', 'redeemed', 1, 4, 6, 'BATCH-20260621T231042-C3725A', '2026-06-21 22:10:42', '2026-06-21 20:10:42', '2026-06-21 22:46:42'),
(7, '412821672608834', 'redeemed', 1, 4, 6, 'BATCH-20260621T231042-C3725A', '2026-06-21 22:10:42', '2026-06-21 20:10:42', '2026-06-21 22:39:54'),
(8, '284864665166456', 'redeemed', 1, 4, 9, 'BATCH-20260621T231042-C3725A', '2026-06-21 22:10:42', '2026-06-21 20:10:42', '2026-06-21 22:45:06'),
(9, '235702494688956', 'redeemed', 1, 4, 9, 'BATCH-20260621T231042-C3725A', '2026-06-21 22:10:42', '2026-06-21 20:10:42', '2026-06-21 22:44:36'),
(10, '443807929056379', 'redeemed', 1, 4, 9, 'BATCH-20260621T231042-C3725A', '2026-06-21 22:10:42', '2026-06-21 20:10:42', '2026-06-21 22:40:37'),
(11, '244781514184815', 'redeemed', 1, 4, 8, 'BATCH-20260621T231042-C3725A', '2026-06-21 22:10:42', '2026-06-21 20:10:42', '2026-06-21 22:40:56'),
(12, '784804371908732', 'redeemed', 1, 4, 8, 'BATCH-20260621T231042-C3725A', '2026-06-21 22:10:42', '2026-06-21 20:10:42', '2026-06-21 22:40:02'),
(13, '483770099264026', 'redeemed', 1, 4, 8, 'BATCH-20260621T231042-C3725A', '2026-06-21 22:10:42', '2026-06-21 20:10:42', '2026-06-21 22:36:50');

-- --------------------------------------------------------

--
-- Table structure for table `code_redemptions`
--

CREATE TABLE `code_redemptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `vendor_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'If vendor-credited',
  `redeemed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `code_redemptions`
--

INSERT INTO `code_redemptions` (`id`, `code_id`, `user_id`, `vendor_id`, `redeemed_at`) VALUES
(1, 13, 8, 4, '2026-06-21 22:36:50'),
(2, 7, 6, 4, '2026-06-21 22:39:54'),
(3, 12, 8, 4, '2026-06-21 22:40:02'),
(4, 10, 9, 4, '2026-06-21 22:40:37'),
(5, 11, 8, 4, '2026-06-21 22:40:56'),
(6, 5, 6, 4, '2026-06-21 22:41:27'),
(7, 9, 9, 4, '2026-06-21 22:44:36'),
(8, 8, 9, 4, '2026-06-21 22:45:06'),
(9, 6, 6, 4, '2026-06-21 22:46:42'),
(10, 2, 10, 4, '2026-06-21 23:59:26'),
(11, 3, 10, 4, '2026-06-22 00:00:37'),
(12, 4, 10, 4, '2026-06-22 00:01:51');

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
  `status` enum('pending','active','paused','completed','cancelled') NOT NULL DEFAULT 'pending',
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

INSERT INTO `draws` (`id`, `title`, `description`, `rules`, `prize_details`, `banner_image`, `category`, `status`, `start_date`, `end_date`, `winning_code`, `winner_user_id`, `finalized_by`, `finalized_at`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '1 Week Free Transport Award', 'This  particular draw is for One day residents, 1 week free Keke Transport from Precious junction to One day junction and from One day junction to Precious junction you will not pay T-fair!', 'Just  as stated in the general rules', 'Worth N14k ($10.30)', 'banner-6a339dad5bd3e.jpeg', 'Transport', 'active', '2026-06-18 08:30:00', '2026-06-30 08:30:00', NULL, NULL, NULL, NULL, 1, '2026-06-08 00:31:09', '2026-06-18 07:26:37');

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
  `matched_digits` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `tiebreaker_used` varchar(100) DEFAULT NULL,
  `announced_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 1, 'Application Received', 'Your vendor application is under review. We\'ll notify you within 24 hours.', 'vendor', 1, '2026-05-30 10:16:34'),
(2, 1, '🏪 Vendor Approved!', 'Your vendor application has been approved. Visit the Vendor Panel in your dashboard to get started.', 'vendor', 1, '2026-06-01 10:49:20'),
(3, 4, 'Application Received', 'Your vendor application is under review. We\'ll notify you within 24 hours.', 'vendor', 0, '2026-06-03 22:23:52'),
(4, 4, '🏪 Vendor Approved', 'Congratulations! Your vendor application has been approved. You can now distribute raffle codes.', 'vendor', 0, '2026-06-18 07:44:15'),
(5, 8, '🎟️ Code Redeemed', 'Code 483770099264026 has been added to your wallet successfully.', 'redemption', 0, '2026-06-21 22:36:50'),
(6, 6, '🎟️ Code Redeemed', 'Code 412821672608834 has been added to your wallet successfully.', 'redemption', 0, '2026-06-21 22:39:54'),
(7, 8, '🎟️ Code Redeemed', 'Code 784804371908732 has been added to your wallet successfully.', 'redemption', 0, '2026-06-21 22:40:02'),
(8, 9, '🎟️ Code Redeemed', 'Code 443807929056379 has been added to your wallet successfully.', 'redemption', 0, '2026-06-21 22:40:37'),
(9, 8, '🎟️ Code Redeemed', 'Code 244781514184815 has been added to your wallet successfully.', 'redemption', 0, '2026-06-21 22:40:56'),
(10, 6, '🎟️ Code Redeemed', 'Code 166964828463667 has been added to your wallet successfully.', 'redemption', 0, '2026-06-21 22:41:27'),
(11, 9, '🎟️ Code Redeemed', 'Code 235702494688956 has been added to your wallet successfully.', 'redemption', 0, '2026-06-21 22:44:36'),
(12, 9, '🎟️ Code Redeemed', 'Code 284864665166456 has been added to your wallet successfully.', 'redemption', 0, '2026-06-21 22:45:06'),
(13, 6, '🎟️ Code Redeemed', 'Code 174315652370040 has been added to your wallet successfully.', 'redemption', 0, '2026-06-21 22:46:42'),
(14, 10, '🎟️ Code Redeemed', 'Code 947399346999177 has been added to your wallet successfully.', 'redemption', 0, '2026-06-21 23:59:26'),
(15, 10, '🎟️ Code Redeemed', 'Code 363534120536300 has been added to your wallet successfully.', 'redemption', 0, '2026-06-22 00:00:37'),
(16, 10, '🎟️ Code Redeemed', 'Code 155482336964843 has been added to your wallet successfully.', 'redemption', 0, '2026-06-22 00:01:51');

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

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `title`, `description`, `icon`, `color_class`, `link_url`, `sort_order`, `status`, `created_by`, `created_at`) VALUES
(1, 'Raffle Draws', 'Enter live draws and win amazing prizes with your raffle codes.', '🎯', 'from-orange-500/20 to-red-500/10', NULL, 1, 'active', NULL, '2026-05-30 10:07:54'),
(2, 'Code Redemption', 'Instantly redeem your 15-digit raffle codes to your wallet.', '🎟️', 'from-blue-500/20 to-indigo-500/10', NULL, 2, 'active', NULL, '2026-05-30 10:07:54'),
(3, 'Code Transfer', 'Send your raffle codes to friends and family instantly.', '↔️', 'from-green-500/20 to-emerald-500/10', NULL, 3, 'active', NULL, '2026-05-30 10:07:54'),
(4, 'Vendor Network', 'Apply to become a ZoeFeeds vendor and distribute codes.', '🏪', 'from-purple-500/20 to-violet-500/10', NULL, 4, 'active', NULL, '2026-05-30 10:07:54'),
(5, 'Daily Rewards', 'Earn daily loyalty rewards just for being an active user.', '⭐', 'from-yellow-500/20 to-amber-500/10', NULL, 5, 'active', NULL, '2026-05-30 10:07:54'),
(6, 'Live Draws', 'Watch live draw reveals in real-time and see if you won.', '🔴', 'from-red-500/20 to-pink-500/10', NULL, 6, 'active', NULL, '2026-05-30 10:07:54');

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
(1, 8, 'credit', 'redemption', 1, 13, NULL, 'Code redeemed: 483770099264026', '2026-06-21 22:36:50'),
(2, 6, 'credit', 'redemption', 1, 7, NULL, 'Code redeemed: 412821672608834', '2026-06-21 22:39:54'),
(3, 8, 'credit', 'redemption', 1, 12, NULL, 'Code redeemed: 784804371908732', '2026-06-21 22:40:02'),
(4, 9, 'credit', 'redemption', 1, 10, NULL, 'Code redeemed: 443807929056379', '2026-06-21 22:40:37'),
(5, 8, 'credit', 'redemption', 1, 11, NULL, 'Code redeemed: 244781514184815', '2026-06-21 22:40:56'),
(6, 6, 'credit', 'redemption', 1, 5, NULL, 'Code redeemed: 166964828463667', '2026-06-21 22:41:27'),
(7, 9, 'credit', 'redemption', 1, 9, NULL, 'Code redeemed: 235702494688956', '2026-06-21 22:44:36'),
(8, 9, 'credit', 'redemption', 1, 8, NULL, 'Code redeemed: 284864665166456', '2026-06-21 22:45:06'),
(9, 6, 'credit', 'redemption', 1, 6, NULL, 'Code redeemed: 174315652370040', '2026-06-21 22:46:42'),
(10, 10, 'credit', 'redemption', 1, 2, NULL, 'Code redeemed: 947399346999177', '2026-06-21 23:59:26'),
(11, 10, 'credit', 'redemption', 1, 3, NULL, 'Code redeemed: 363534120536300', '2026-06-22 00:00:37'),
(12, 10, 'credit', 'redemption', 1, 4, NULL, 'Code redeemed: 155482336964843', '2026-06-22 00:01:51');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
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
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `phone`, `full_name`, `email`, `password`, `transfer_pin`, `balance`, `status`, `is_vendor`, `vendor_status`, `vendor_business_name`, `vendor_bio`, `vendor_code_balance`, `vendor_public_key`, `vendor_secret_key`, `vendor_applied_at`, `vendor_approved_at`, `vendor_approved_by`, `created_at`, `updated_at`) VALUES
(1, '2348108833188', 'Ezea Ugochukwu micheal', 'spotwebdev.com@gmail.com', '$2y$12$c7.d26Vf8GnXaNuhdVdbk.U6vyXIC23rmG89r7Z7uNBnordfcaFNu', NULL, 0, 'active', 1, 'active', 'SPOTWEB COM', NULL, 1, 'zf_pub_37cb4bc60701b111c85a8be1e2e39d32', '$2y$10$QfkEirgPzHZHWPgGCFjrAex6Mm5kILB5OOw2H3596rNKK0VYaMvLW', '2026-05-30 10:16:34', '2026-06-01 10:49:20', 1, '2026-05-30 10:14:16', '2026-06-01 10:52:14'),
(2, '2347047548913', 'admin', 'admin@admin.com', '$2y$12$Tsrzev2akYrPGN60S4WKL.z/8KIE37GHpFttRKDH9ukwtQ2frqnvS', NULL, 0, 'active', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-05-30 10:23:08', '2026-05-30 10:23:08'),
(3, '2347060507980', 'Mary Jenny', 'mary@gmail.com', '$2y$12$5Erwh4UqnC8OmkPzufST6OnboJAm0fMmtfBME0YuEWfeH2aUrghV.', NULL, 0, 'active', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-06-01 10:53:20', '2026-06-01 10:53:20'),
(4, '2348107492297', 'Obinwa Daniel Mmerisinayahoshea', 'mmerisinayahoshea@gmail.com', '$2y$12$TVTRoRsF2BSMXW5qRV4CvOsPAEG5D.hhI8t1PSOuNcoV76cqVwoa6', '$2y$10$cRXvWvkaCaWG9RazcdoLruYjsFXB4jGayt.b7WHZn7bWz5encgqMG', 0, 'active', 1, 'active', 'Daniel services', NULL, 0, NULL, NULL, '2026-06-03 22:23:52', '2026-06-18 07:44:15', 1, '2026-06-02 17:02:20', '2026-06-22 00:01:51'),
(5, '2349013497961', 'Favour Lawal', NULL, '$2y$12$CY0Z/knssgPNXQQrbkkMsunzZaWbVO.e.CIdPDyB99Sg2Z.FYiljG', NULL, 0, 'active', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-06-11 14:10:05', '2026-06-11 14:10:05'),
(6, '2349066180486', 'UGWU CHIAMAKA', 'chiamaka5487@gmail.com', '$2y$12$PkpJ8xsVd.swQO16gWW3..4Vrt5.j77gTrLFxlHZuer/z4PfUDONe', NULL, 3, 'active', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-06-21 21:47:53', '2026-06-21 22:46:42'),
(7, '2349012487408', 'AYOGU CYNTHIA', 'cynthiaayogu19@gmail.com', '$2y$12$dq3W5Vwz4gXUjwEkaX3ml.TbQWNrqwU1CJEeS.AazISyHhBjSK6vW', NULL, 0, 'active', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-06-21 21:48:19', '2026-06-21 21:48:19'),
(8, '2349135358946', 'Chidera Chukwur', 'chukwurachidera0@gmail.com', '$2y$12$dJ1TxOPrCxzZJ63iY8n/teOl8NYoIFcxBzxgtSVn4.ON0rbcEpowi', NULL, 3, 'active', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-06-21 21:50:11', '2026-06-21 22:40:56'),
(9, '2348119359883', 'AYOGU CYNTHIA', 'cynthiaayogu19@gmail.com', '$2y$12$tZiPmpQix9kTdvXgpd5vgO4aWqidyzNlnc1RRIjfxqBC.uNNePPMW', NULL, 3, 'active', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-06-21 21:52:58', '2026-06-21 22:45:06'),
(10, '2348101177811', 'Jachimma praise Uzochukwu', NULL, '$2y$12$RjPu38BaU2f9UC34zGSJt.TcNtD6wNNvCGX5PbYIYtugkLQuTRBcW', NULL, 3, 'active', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-06-21 23:10:08', '2026-06-22 00:01:51');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_applications`
--

CREATE TABLE `vendor_applications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
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

INSERT INTO `vendor_applications` (`id`, `user_id`, `business_name`, `reason`, `status`, `reviewed_by`, `review_note`, `applied_at`, `reviewed_at`) VALUES
(1, 1, 'SPOTWEB COM', 'i want to add my website', 'approved', 1, '', '2026-05-30 10:16:34', '2026-06-01 10:49:20'),
(2, 4, 'Daniel services', 'To distribute your products and increase your sales', 'approved', 1, NULL, '2026-06-03 22:23:52', '2026-06-18 07:44:15');

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
  ADD UNIQUE KEY `vendor_public_key` (`vendor_public_key`),
  ADD UNIQUE KEY `uq_vendor_public_key` (`vendor_public_key`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_is_vendor` (`is_vendor`,`vendor_status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `vendor_applications`
--
ALTER TABLE `vendor_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `codes`
--
ALTER TABLE `codes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `code_redemptions`
--
ALTER TABLE `code_redemptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `code_transfers`
--
ALTER TABLE `code_transfers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `draws`
--
ALTER TABLE `draws`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `draw_entries`
--
ALTER TABLE `draw_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `draw_reveal`
--
ALTER TABLE `draw_reveal`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `draw_winners`
--
ALTER TABLE `draw_winners`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `slides`
--
ALTER TABLE `slides`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `super_admins`
--
ALTER TABLE `super_admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `vendor_applications`
--
ALTER TABLE `vendor_applications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `vendor_applications`
--
ALTER TABLE `vendor_applications`
  ADD CONSTRAINT `vendor_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
