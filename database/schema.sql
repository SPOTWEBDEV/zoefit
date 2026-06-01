
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE             = '';
SET time_zone            = '+00:00';
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `zoefeeds`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `zoefeeds`;

-- ============================================================
-- 1. USERS  (regular users AND vendors share this table)
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`                    INT UNSIGNED    NOT NULL AUTO_INCREMENT,

  -- Core identity
  `phone`                 VARCHAR(20)     NOT NULL  COMMENT 'Normalized: 234XXXXXXXXXX',
  `full_name`             VARCHAR(150)    NOT NULL,
  `email`                 VARCHAR(200)    DEFAULT NULL,
  `password`              VARCHAR(255)    NOT NULL,
  `transfer_pin`          VARCHAR(255)    DEFAULT NULL  COMMENT 'bcrypt-hashed 4-digit PIN',

  -- Wallet
  `balance`               INT UNSIGNED    NOT NULL DEFAULT 0  COMMENT 'Active redeemed code count',

  -- Account status
  `status`                ENUM('active','suspended','banned') NOT NULL DEFAULT 'active',

  -- ── VENDOR FIELDS (NULL when user is not a vendor) ──────
  `is_vendor`             TINYINT(1)      NOT NULL DEFAULT 0,
  `vendor_status`         ENUM('pending','active','suspended','rejected') DEFAULT NULL,
  `vendor_business_name`  VARCHAR(200)    DEFAULT NULL,
  `vendor_bio`            TEXT            DEFAULT NULL,
  `vendor_code_balance`   INT UNSIGNED    NOT NULL DEFAULT 0  COMMENT 'Codes available to distribute',
  `vendor_public_key`     VARCHAR(80)     DEFAULT NULL  COMMENT 'zf_pub_... used in API headers',
  `vendor_secret_key`     VARCHAR(255)    DEFAULT NULL  COMMENT 'bcrypt hash of zf_sec_...',
  `vendor_applied_at`     TIMESTAMP       NULL DEFAULT NULL,
  `vendor_approved_at`    TIMESTAMP       NULL DEFAULT NULL,
  `vendor_approved_by`    INT UNSIGNED    DEFAULT NULL  COMMENT 'admin.id who approved',

  -- Timestamps
  `created_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE  KEY `uq_users_phone`             (`phone`),
  UNIQUE  KEY `uq_users_vendor_public_key` (`vendor_public_key`),
  KEY `idx_users_status`                   (`status`),
  KEY `idx_users_is_vendor`                (`is_vendor`, `vendor_status`),
  KEY `idx_users_created`                  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 2. ADMINS
-- ============================================================
CREATE TABLE IF NOT EXISTS `admins` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `full_name`   VARCHAR(150)    NOT NULL,
  `email`       VARCHAR(200)    NOT NULL,
  `phone`       VARCHAR(20)     NOT NULL,
  `password`    VARCHAR(255)    NOT NULL,
  `status`      ENUM('active','suspended','pending') NOT NULL DEFAULT 'pending',
  `approved_by` INT UNSIGNED    DEFAULT NULL  COMMENT 'super_admins.id',
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_email` (`email`),
  KEY `idx_admins_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 3. SUPER ADMINS
-- ============================================================
CREATE TABLE IF NOT EXISTS `super_admins` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `full_name`  VARCHAR(150)  NOT NULL,
  `email`      VARCHAR(200)  NOT NULL,
  `password`   VARCHAR(255)  NOT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_super_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 4. VENDOR APPLICATIONS  (history of every apply)
-- ============================================================
CREATE TABLE IF NOT EXISTS `vendor_applications` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED  NOT NULL,
  `business_name` VARCHAR(200)  DEFAULT NULL,
  `reason`        TEXT          DEFAULT NULL,
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by`   INT UNSIGNED  DEFAULT NULL  COMMENT 'admins.id',
  `review_note`   TEXT          DEFAULT NULL,
  `applied_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at`   TIMESTAMP     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_va_user`   (`user_id`),
  KEY `idx_va_status` (`status`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 5. CODES
-- ============================================================
CREATE TABLE IF NOT EXISTS `codes` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`            CHAR(15)        NOT NULL  COMMENT 'Exactly 15 numeric digits',
  `status`          ENUM('unassigned','assigned','distributed','redeemed','reserved','used','transferred')
                    NOT NULL DEFAULT 'unassigned',
  `generated_by`    INT UNSIGNED    NOT NULL  COMMENT 'admins.id',
  `assigned_vendor` INT UNSIGNED    DEFAULT NULL  COMMENT 'users.id where is_vendor=1',
  `current_owner`   INT UNSIGNED    DEFAULT NULL  COMMENT 'users.id',
  `batch_id`        VARCHAR(60)     DEFAULT NULL  COMMENT 'e.g. BATCH-20260523-AB12CD',
  `generated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_at`     TIMESTAMP       NULL DEFAULT NULL,
  `redeemed_at`     TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_codes_code`        (`code`),
  KEY `idx_codes_status`            (`status`),
  KEY `idx_codes_batch`             (`batch_id`),
  KEY `idx_codes_vendor`            (`assigned_vendor`),
  KEY `idx_codes_owner`             (`current_owner`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 6. CODE REDEMPTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `code_redemptions` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code_id`     BIGINT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED    NOT NULL,
  `vendor_id`   INT UNSIGNED    DEFAULT NULL  COMMENT 'If vendor-issued',
  `redeemed_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cr_code`   (`code_id`),
  KEY `idx_cr_user`   (`user_id`),
  KEY `idx_cr_vendor` (`vendor_id`),
  FOREIGN KEY (`code_id`) REFERENCES `codes`(`id`)  ON DELETE RESTRICT,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 7. CODE TRANSFERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `code_transfers` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code_id`        BIGINT UNSIGNED NOT NULL,
  `from_user_id`   INT UNSIGNED    NOT NULL,
  `to_user_id`     INT UNSIGNED    NOT NULL,
  `transferred_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ct_code` (`code_id`),
  KEY `idx_ct_from` (`from_user_id`),
  KEY `idx_ct_to`   (`to_user_id`),
  FOREIGN KEY (`code_id`)      REFERENCES `codes`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`to_user_id`)   REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 8. DRAWS
-- ============================================================
CREATE TABLE IF NOT EXISTS `draws` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `title`           VARCHAR(200)    NOT NULL,
  `description`     TEXT            DEFAULT NULL,
  `rules`           TEXT            DEFAULT NULL,
  `prize_details`   TEXT            DEFAULT NULL,
  `banner_image`    VARCHAR(255)    DEFAULT NULL,
  `category`        VARCHAR(100)    DEFAULT NULL,
  `status`          ENUM('pending','active','paused','completed','cancelled')
                    NOT NULL DEFAULT 'pending',
  `start_date`      DATETIME        NOT NULL,
  `end_date`        DATETIME        NOT NULL,
  `winning_code`    CHAR(15)        DEFAULT NULL,
  `winner_user_id`  INT UNSIGNED    DEFAULT NULL,
  `finalized_by`    INT UNSIGNED    DEFAULT NULL  COMMENT 'admins.id',
  `finalized_at`    TIMESTAMP       NULL DEFAULT NULL,
  `created_by`      INT UNSIGNED    NOT NULL  COMMENT 'admins.id',
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_draws_status` (`status`),
  KEY `idx_draws_dates`  (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 9. DRAW ENTRIES
-- ============================================================
CREATE TABLE IF NOT EXISTS `draw_entries` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `draw_id`    INT UNSIGNED    NOT NULL,
  `user_id`    INT UNSIGNED    NOT NULL,
  `code_id`    BIGINT UNSIGNED NOT NULL,
  `entered_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_de_draw_code`  (`draw_id`, `code_id`),
  KEY `idx_de_draw`             (`draw_id`),
  KEY `idx_de_user`             (`user_id`),
  FOREIGN KEY (`draw_id`) REFERENCES `draws`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`code_id`) REFERENCES `codes`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 10. DRAW WINNERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `draw_winners` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `draw_id`          INT UNSIGNED  NOT NULL,
  `user_id`          INT UNSIGNED  NOT NULL,
  `winning_code`     CHAR(15)      NOT NULL,
  `user_code`        CHAR(15)      NOT NULL  COMMENT 'The user code that matched best',
  `matched_digits`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `tiebreaker_used`  VARCHAR(100)  DEFAULT NULL,
  `announced_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dw_draw`  (`draw_id`),
  KEY `idx_dw_user`         (`user_id`),
  FOREIGN KEY (`draw_id`) REFERENCES `draws`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 11. DRAW DIGIT REVEAL  (live draw engine state)
-- ============================================================
CREATE TABLE IF NOT EXISTS `draw_reveal` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `draw_id`          INT UNSIGNED  NOT NULL,
  `revealed_digits`  VARCHAR(15)   NOT NULL DEFAULT '',
  `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dr_draw` (`draw_id`),
  FOREIGN KEY (`draw_id`) REFERENCES `draws`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 12. TRANSACTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `transactions` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED    NOT NULL,
  `type`         ENUM('credit','debit') NOT NULL,
  `category`     ENUM('redemption','transfer_in','transfer_out','draw_entry',
                      'vendor_credit','draw_deduction') NOT NULL,
  `amount`       INT             NOT NULL DEFAULT 1  COMMENT 'Code count',
  `code_id`      BIGINT UNSIGNED DEFAULT NULL,
  `reference_id` BIGINT UNSIGNED DEFAULT NULL,
  `description`  TEXT            DEFAULT NULL,
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_txn_user`     (`user_id`),
  KEY `idx_txn_type`     (`type`, `category`),
  KEY `idx_txn_created`  (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 13. NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED    NOT NULL,
  `title`      VARCHAR(200)    NOT NULL,
  `message`    TEXT            NOT NULL,
  `type`       ENUM('info','success','warning','draw','transfer','redemption','vendor')
               NOT NULL DEFAULT 'info',
  `is_read`    TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user_read` (`user_id`, `is_read`),
  KEY `idx_notif_created`   (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 14. SLIDES / BANNERS  (homepage slideshow)
-- ============================================================
CREATE TABLE IF NOT EXISTS `slides` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(200)  DEFAULT NULL,
  `image_path` VARCHAR(255)  NOT NULL,
  `link_url`   VARCHAR(500)  DEFAULT NULL,
  `sort_order` INT           NOT NULL DEFAULT 0,
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` INT UNSIGNED  DEFAULT NULL  COMMENT 'admins.id',
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slides_status_order` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 15. SERVICES  (user services page)
-- ============================================================
CREATE TABLE IF NOT EXISTS `services` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(200)  NOT NULL,
  `description` TEXT          DEFAULT NULL,
  `icon`        VARCHAR(20)   DEFAULT NULL  COMMENT 'Emoji or icon identifier',
  `color_class` VARCHAR(120)  DEFAULT NULL  COMMENT 'Tailwind gradient classes',
  `link_url`    VARCHAR(500)  DEFAULT NULL,
  `sort_order`  INT           NOT NULL DEFAULT 0,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_by`  INT UNSIGNED  DEFAULT NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_services_status` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 16. AUDIT LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_type`  ENUM('user','admin','vendor','super_admin','system') NOT NULL,
  `actor_id`    INT UNSIGNED    NOT NULL,
  `action`      VARCHAR(120)    NOT NULL,
  `description` TEXT            DEFAULT NULL,
  `entity_type` VARCHAR(60)     DEFAULT NULL,
  `entity_id`   BIGINT UNSIGNED DEFAULT NULL,
  `ip_address`  VARCHAR(45)     DEFAULT NULL,
  `user_agent`  TEXT            DEFAULT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_al_actor`   (`actor_type`, `actor_id`),
  KEY `idx_al_action`  (`action`),
  KEY `idx_al_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 17. API LOGS  (vendor API request tracking)
-- ============================================================
CREATE TABLE IF NOT EXISTS `api_logs` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_id`    INT UNSIGNED    NOT NULL  COMMENT 'users.id',
  `endpoint`     VARCHAR(200)    NOT NULL,
  `method`       VARCHAR(10)     NOT NULL DEFAULT 'POST',
  `request_body` TEXT            DEFAULT NULL,
  `response_code`SMALLINT        NOT NULL DEFAULT 200,
  `ip_address`   VARCHAR(45)     DEFAULT NULL,
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_apil_vendor`  (`vendor_id`),
  KEY `idx_apil_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 18. SETTINGS  (key-value platform config)
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `key`         VARCHAR(100)  NOT NULL,
  `value`       TEXT          DEFAULT NULL,
  `label`       VARCHAR(200)  DEFAULT NULL,
  `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
-- SEED DATA
-- ============================================================

-- Default Super Admin  (password: SuperAdmin@123)
INSERT INTO `super_admins` (`full_name`, `email`, `password`) VALUES
('Super Administrator', 'superadmin@zoefeeds.com',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE `email`=`email`;

-- Default Platform Settings
INSERT IGNORE INTO `settings` (`key`,`value`,`label`) VALUES
('site_name',        'ZoeFeeds',                    'Platform Name'),
('site_tagline',     'Loyalty Reward & Raffle Platform', 'Tagline'),
('maintenance_mode', '0',                           'Maintenance Mode (1=on)'),
('allow_register',   '1',                           'Allow New Registrations'),
('transfer_fee',     '0',                           'Transfer Fee (codes)'),
('max_draw_entries', '100',                         'Max codes per user per draw'),
('api_rate_limit',   '60',                          'API requests per minute per vendor');

-- Default Services
INSERT IGNORE INTO `services` (`id`,`title`,`description`,`icon`,`color_class`,`sort_order`) VALUES
(1,'Raffle Draws',    'Enter live draws and win prizes with your raffle codes.',       '🎯','from-orange-500/20 to-red-500/10',    1),
(2,'Code Redemption', 'Instantly add your 15-digit raffle code to your wallet.',      '🎟️','from-blue-500/20 to-indigo-500/10',  2),
(3,'Code Transfer',   'Send raffle codes to any registered user by phone number.',    '↔️','from-green-500/20 to-emerald-500/10',3),
(4,'Vendor Network',  'Apply to become a ZoeFeeds vendor and distribute codes.',      '🏪','from-purple-500/20 to-violet-500/10',4),
(5,'Daily Rewards',   'Earn loyalty rewards for consistent platform activity.',       '⭐','from-yellow-500/20 to-amber-500/10', 5),
(6,'Live Draws',      'Watch digits revealed in real-time during live draw events.', '🔴','from-red-500/20 to-pink-500/10',     6);


-- ============================================================
-- UPGRADE MIGRATION  (run if upgrading from v1/v2 schema)
-- Uncomment only the lines for columns that don't yet exist.
-- ============================================================
/*
ALTER TABLE `users`
  ADD COLUMN `is_vendor`             TINYINT(1)   NOT NULL DEFAULT 0          AFTER `status`,
  ADD COLUMN `vendor_status`         ENUM('pending','active','suspended','rejected') DEFAULT NULL AFTER `is_vendor`,
  ADD COLUMN `vendor_business_name`  VARCHAR(200) DEFAULT NULL                AFTER `vendor_status`,
  ADD COLUMN `vendor_bio`            TEXT         DEFAULT NULL                AFTER `vendor_business_name`,
  ADD COLUMN `vendor_code_balance`   INT UNSIGNED NOT NULL DEFAULT 0          AFTER `vendor_bio`,
  ADD COLUMN `vendor_public_key`     VARCHAR(80)  DEFAULT NULL                AFTER `vendor_code_balance`,
  ADD COLUMN `vendor_secret_key`     VARCHAR(255) DEFAULT NULL                AFTER `vendor_public_key`,
  ADD COLUMN `vendor_applied_at`     TIMESTAMP NULL DEFAULT NULL              AFTER `vendor_secret_key`,
  ADD COLUMN `vendor_approved_at`    TIMESTAMP NULL DEFAULT NULL              AFTER `vendor_applied_at`,
  ADD COLUMN `vendor_approved_by`    INT UNSIGNED DEFAULT NULL                AFTER `vendor_approved_at`,
  ADD UNIQUE KEY `uq_users_vendor_public_key` (`vendor_public_key`),
  ADD KEY `idx_users_is_vendor` (`is_vendor`, `vendor_status`);

ALTER TABLE `draw_winners`
  ADD COLUMN `user_code` CHAR(15) NOT NULL DEFAULT '' AFTER `winning_code`;

CREATE TABLE IF NOT EXISTS `vendor_applications` ( ... );  -- see full definition above
CREATE TABLE IF NOT EXISTS `api_logs`             ( ... );  -- see full definition above
CREATE TABLE IF NOT EXISTS `settings`             ( ... );  -- see full definition above
*/
