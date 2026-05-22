-- ZOEFEEDS DATABASE SCHEMA
-- Complete production schema with indexes and foreign keys

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `zoefeeds` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `zoefeeds`;

-- =============================================
-- USERS TABLE
-- =============================================
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `phone` VARCHAR(20) NOT NULL COMMENT 'Normalized: 234XXXXXXXXXX',
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(200) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `transfer_pin` VARCHAR(255) DEFAULT NULL COMMENT 'Hashed 4-digit PIN',
  `balance` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of active codes',
  `status` ENUM('active','suspended','banned') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_phone` (`phone`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ADMINS TABLE
-- =============================================
CREATE TABLE `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(200) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `status` ENUM('active','suspended','pending') NOT NULL DEFAULT 'pending',
  `approved_by` INT UNSIGNED DEFAULT NULL COMMENT 'super_admin id',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- SUPER ADMINS TABLE
-- =============================================
CREATE TABLE `super_admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(200) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- VENDORS TABLE
-- =============================================
CREATE TABLE `vendors` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(150) NOT NULL,
  `business_name` VARCHAR(200) DEFAULT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(200) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `code_balance` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Codes in vendor inventory',
  `status` ENUM('active','suspended','pending') NOT NULL DEFAULT 'pending',
  `created_by` INT UNSIGNED DEFAULT NULL COMMENT 'admin id',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_phone` (`phone`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- CODES TABLE
-- =============================================
CREATE TABLE `codes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` CHAR(15) NOT NULL COMMENT '15-digit numeric raffle code',
  `status` ENUM('unassigned','assigned','distributed','redeemed','reserved','used','transferred') NOT NULL DEFAULT 'unassigned',
  `generated_by` INT UNSIGNED NOT NULL COMMENT 'admin id',
  `assigned_vendor` INT UNSIGNED DEFAULT NULL COMMENT 'vendor id',
  `current_owner` INT UNSIGNED DEFAULT NULL COMMENT 'user id',
  `batch_id` VARCHAR(50) DEFAULT NULL COMMENT 'Bulk generation batch reference',
  `generated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_at` TIMESTAMP NULL DEFAULT NULL,
  `redeemed_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code` (`code`),
  KEY `idx_status` (`status`),
  KEY `idx_vendor` (`assigned_vendor`),
  KEY `idx_owner` (`current_owner`),
  KEY `idx_batch` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- CODE REDEMPTIONS
-- =============================================
CREATE TABLE `code_redemptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `vendor_id` INT UNSIGNED DEFAULT NULL COMMENT 'If vendor-credited',
  `redeemed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_code` (`code_id`),
  KEY `idx_user` (`user_id`),
  FOREIGN KEY (`code_id`) REFERENCES `codes`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- CODE TRANSFERS
-- =============================================
CREATE TABLE `code_transfers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code_id` BIGINT UNSIGNED NOT NULL,
  `from_user_id` INT UNSIGNED NOT NULL,
  `to_user_id` INT UNSIGNED NOT NULL,
  `transferred_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_code` (`code_id`),
  KEY `idx_from` (`from_user_id`),
  KEY `idx_to` (`to_user_id`),
  FOREIGN KEY (`code_id`) REFERENCES `codes`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`to_user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- DRAWS TABLE
-- =============================================
CREATE TABLE `draws` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `rules` TEXT DEFAULT NULL,
  `prize_details` TEXT DEFAULT NULL,
  `banner_image` VARCHAR(255) DEFAULT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending','active','paused','completed','cancelled') NOT NULL DEFAULT 'pending',
  `start_date` DATETIME NOT NULL,
  `end_date` DATETIME NOT NULL,
  `winning_code` CHAR(15) DEFAULT NULL,
  `winner_user_id` INT UNSIGNED DEFAULT NULL,
  `finalized_by` INT UNSIGNED DEFAULT NULL COMMENT 'admin id',
  `finalized_at` TIMESTAMP NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL COMMENT 'admin id',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- DRAW ENTRIES
-- =============================================
CREATE TABLE `draw_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `draw_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `code_id` BIGINT UNSIGNED NOT NULL,
  `entered_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_draw_code` (`draw_id`,`code_id`),
  KEY `idx_draw` (`draw_id`),
  KEY `idx_user` (`user_id`),
  FOREIGN KEY (`draw_id`) REFERENCES `draws`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`code_id`) REFERENCES `codes`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- DRAW WINNERS
-- =============================================
CREATE TABLE `draw_winners` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `draw_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `winning_code` CHAR(15) NOT NULL,
  `matched_digits` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `tiebreaker_used` VARCHAR(100) DEFAULT NULL,
  `announced_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_draw` (`draw_id`),
  KEY `idx_user` (`user_id`),
  FOREIGN KEY (`draw_id`) REFERENCES `draws`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- AUDIT LOGS
-- =============================================
CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_type` ENUM('user','admin','vendor','super_admin','system') NOT NULL,
  `actor_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `entity_type` VARCHAR(50) DEFAULT NULL,
  `entity_id` BIGINT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_actor` (`actor_type`,`actor_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- SLIDES / BANNERS
-- =============================================
CREATE TABLE `slides` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) DEFAULT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_order` (`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- NOTIFICATIONS
-- =============================================
CREATE TABLE `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info','success','warning','draw','transfer','redemption') NOT NULL DEFAULT 'info',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`is_read`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TRANSACTIONS
-- =============================================
CREATE TABLE `transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` ENUM('credit','debit') NOT NULL,
  `category` ENUM('redemption','transfer_in','transfer_out','draw_entry','vendor_credit','draw_deduction') NOT NULL,
  `amount` INT NOT NULL DEFAULT 1 COMMENT 'Number of codes',
  `code_id` BIGINT UNSIGNED DEFAULT NULL,
  `reference_id` BIGINT UNSIGNED DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_type` (`type`,`category`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- DRAW DIGIT REVEAL (Live draw matching)
-- =============================================
CREATE TABLE `draw_reveal` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `draw_id` INT UNSIGNED NOT NULL,
  `revealed_digits` VARCHAR(15) NOT NULL DEFAULT '' COMMENT 'Digits revealed so far',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_draw` (`draw_id`),
  FOREIGN KEY (`draw_id`) REFERENCES `draws`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- DEFAULT SUPER ADMIN (password: SuperAdmin@123)
-- =============================================
INSERT INTO `super_admins` (`full_name`, `email`, `password`) VALUES
('Super Administrator', 'superadmin@zoefeeds.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
