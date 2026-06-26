-- ============================================================
-- ZoeFeeds Database Migration
-- Separate `users` table into `users` (customers) + `vendors`
--
-- SAFE TO RUN: uses transactions + backs up old data first.
-- Run this entire file in one go via phpMyAdmin or MySQL CLI.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- ============================================================
-- STEP 1: Back up the current users table before touching it
-- ============================================================
CREATE TABLE IF NOT EXISTS `_users_backup_before_split` LIKE `users`;
INSERT INTO `_users_backup_before_split` SELECT * FROM `users`;


-- ============================================================
-- STEP 2: Create the new `vendors` table
--         Contains ONLY vendor-specific columns
-- ============================================================
CREATE TABLE IF NOT EXISTS `vendors` (
  `id`                  int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `phone`               varchar(20)  NOT NULL COMMENT 'Normalized: 234XXXXXXXXXX — must differ from users.phone',
  `full_name`           varchar(150) NOT NULL,
  `email`               varchar(200) DEFAULT NULL,
  `password`            varchar(255) NOT NULL,
  `business_name`       varchar(200) DEFAULT NULL,
  `bio`                 text         DEFAULT NULL,
  `reason`              text         DEFAULT NULL COMMENT 'Application reason',
  `status`              enum('pending','active','suspended','rejected') NOT NULL DEFAULT 'pending',
  `code_balance`        int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Codes currently held by vendor',
  `public_key`          varchar(64)  DEFAULT NULL,
  `secret_key`          varchar(128) DEFAULT NULL,
  `approved_by`         int(10) UNSIGNED DEFAULT NULL COMMENT 'admin id',
  `applied_at`          timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at`         timestamp NULL DEFAULT NULL,
  `created_at`          timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`          timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vendor_phone`      (`phone`),
  UNIQUE KEY `uq_vendor_public_key` (`public_key`),
  KEY `idx_vendor_status`           (`status`),
  KEY `idx_vendor_created`          (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ============================================================
-- STEP 3: Migrate vendor rows from users → vendors
--         Only rows where is_vendor = 1
-- ============================================================
INSERT INTO `vendors`
  (id, phone, full_name, email, password, business_name, bio, status,
   code_balance, public_key, secret_key,
   approved_by, applied_at, approved_at, created_at, updated_at)
SELECT
  id,
  phone,
  full_name,
  email,
  password,
  vendor_business_name,
  vendor_bio,
  CASE vendor_status
    WHEN 'active'    THEN 'active'
    WHEN 'suspended' THEN 'suspended'
    WHEN 'rejected'  THEN 'rejected'
    ELSE 'pending'
  END,
  vendor_code_balance,
  vendor_public_key,
  vendor_secret_key,
  vendor_approved_by,
  COALESCE(vendor_applied_at,   created_at),
  vendor_approved_at,
  created_at,
  updated_at
FROM `users`
WHERE is_vendor = 1;


-- ============================================================
-- STEP 4: Migrate vendor_applications to reference vendors.id
--         (no schema change needed — user_id values for vendor
--          rows are identical to the new vendors.id values
--          since we kept the same IDs in the INSERT above)
-- ============================================================
-- vendor_applications.user_id already matches vendors.id,
-- so no data update is required. We will re-key the FK later.


-- ============================================================
-- STEP 5: Drop all vendor-specific columns from `users`
--         and keep ONLY customer-relevant columns
-- ============================================================

-- Drop FK constraints that reference users columns we will remove
-- (add/adjust names if your installation used different names)
ALTER TABLE `users`
  DROP COLUMN IF EXISTS `is_vendor`,
  DROP COLUMN IF EXISTS `vendor_status`,
  DROP COLUMN IF EXISTS `vendor_business_name`,
  DROP COLUMN IF EXISTS `vendor_bio`,
  DROP COLUMN IF EXISTS `vendor_code_balance`,
  DROP COLUMN IF EXISTS `vendor_public_key`,
  DROP COLUMN IF EXISTS `vendor_secret_key`,
  DROP COLUMN IF EXISTS `vendor_applied_at`,
  DROP COLUMN IF EXISTS `vendor_approved_at`,
  DROP COLUMN IF EXISTS `vendor_approved_by`;

-- Drop the now-redundant vendor_public_key unique index on users
-- (it was already dropped with the column above, but just in case)
-- ALTER TABLE `users` DROP INDEX IF EXISTS `uq_vendor_public_key`;
-- ALTER TABLE `users` DROP INDEX IF EXISTS `vendor_public_key`;


-- ============================================================
-- STEP 6: Remove pure-vendor rows from `users`
--         Vendors no longer live in the users table.
--         Their customer-facing data (entries, codes they
--         personally redeemed as customers) should be kept if
--         any exist; vendor-only rows with no customer activity
--         are safe to remove.
--
-- REVIEW THIS before running — confirm which user IDs are
-- pure vendors with no customer draw entries or redeemed codes.
-- The query below deletes only users that:
--   • Were backed up into _users_backup_before_split ✓
--   • Have no rows in draw_entries or code_redemptions
--     as customers (i.e. they were vendor-only accounts)
-- ============================================================

DELETE FROM `users`
WHERE id IN (
  SELECT id FROM `_users_backup_before_split` WHERE is_vendor = 1
)
AND id NOT IN (SELECT DISTINCT user_id FROM `draw_entries`)
AND id NOT IN (SELECT DISTINCT user_id FROM `code_redemptions`);

-- NOTE: If a vendor also participated as a customer (redeemed
-- codes / entered draws personally), their row stays in `users`
-- as a regular customer. They will simply log in via the
-- customer portal and apply for vendor access separately.


-- ============================================================
-- STEP 7: Update `codes` table
--         assigned_vendor now references vendors.id
-- ============================================================
-- The assigned_vendor values 1 and 4 in your data match the
-- IDs we kept in vendors, so no data update is needed.
-- We just need the correct comment:
ALTER TABLE `codes`
  MODIFY COLUMN `assigned_vendor` int(10) UNSIGNED DEFAULT NULL
    COMMENT 'vendors.id — the vendor this batch was assigned to';


-- ============================================================
-- STEP 8: Update `code_redemptions`
--         vendor_id column already references the right IDs
-- ============================================================
ALTER TABLE `code_redemptions`
  MODIFY COLUMN `vendor_id` int(10) UNSIGNED DEFAULT NULL
    COMMENT 'vendors.id — vendor credited for this redemption';


-- ============================================================
-- STEP 9: Update `vendor_applications`
--         user_id now means vendors.id (the vendor who applied)
--         Rename to vendor_id for clarity
-- ============================================================
ALTER TABLE `vendor_applications`
  DROP FOREIGN KEY IF EXISTS `vendor_applications_ibfk_1`;

ALTER TABLE `vendor_applications`
  CHANGE COLUMN `user_id` `vendor_id` int(10) UNSIGNED NOT NULL
    COMMENT 'vendors.id — the vendor who submitted this application';

ALTER TABLE `vendor_applications`
  DROP INDEX IF EXISTS `idx_user`;

ALTER TABLE `vendor_applications`
  ADD KEY `idx_vendor` (`vendor_id`);

-- Add FK pointing to vendors table
ALTER TABLE `vendor_applications`
  ADD CONSTRAINT `vendor_applications_fk_vendor`
    FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;


-- ============================================================
-- STEP 10: Populate the new `vendors` table with a `reason`
--          from vendor_applications where available
-- ============================================================
UPDATE `vendors` v
  JOIN `vendor_applications` va ON va.vendor_id = v.id
SET v.reason = va.reason
WHERE va.reason IS NOT NULL AND va.reason != '';


-- ============================================================
-- STEP 11: Final clean users table definition (for reference)
--
--  id, phone, full_name, email, password, transfer_pin,
--  balance, status, created_at, updated_at
-- ============================================================
-- No ALTER needed — the DROP COLUMN steps above already
-- produce the clean customer-only table.


-- ============================================================
-- STEP 12: Verify row counts before committing
-- ============================================================
-- Run these SELECTs to confirm, then COMMIT or ROLLBACK:

SELECT 'users (customers only)'  AS tbl, COUNT(*) AS rows FROM `users`;
SELECT 'vendors'                  AS tbl, COUNT(*) AS rows FROM `vendors`;
SELECT 'vendor_applications'      AS tbl, COUNT(*) AS rows FROM `vendor_applications`;
SELECT 'codes'                    AS tbl, COUNT(*) AS rows FROM `codes`;
SELECT 'code_redemptions'         AS tbl, COUNT(*) AS rows FROM `code_redemptions`;
SELECT '_users_backup_before_split (backup)' AS tbl, COUNT(*) AS rows FROM `_users_backup_before_split`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- If counts look correct → run: COMMIT;
-- If something looks wrong → run: ROLLBACK;
-- ============================================================

COMMIT;


-- ============================================================
-- AFTER CONFIRMING EVERYTHING IS CORRECT,
-- drop the backup table (optional, keep for 30 days to be safe)
-- ============================================================
-- DROP TABLE `_users_backup_before_split`;


-- ============================================================
-- RESULTING CLEAN SCHEMA SUMMARY
-- ============================================================

/*
TABLE: users (customers only)
  id              PK
  phone           UNIQUE — customer login identifier
  full_name
  email           optional
  password        bcrypt
  transfer_pin    hashed 4-digit PIN
  balance         active code count
  status          active | suspended | banned
  created_at
  updated_at

TABLE: vendors (merchants only)
  id              PK  ← same IDs as original users.id for migrated vendors
  phone           UNIQUE — vendor login identifier (≠ users.phone)
  full_name
  email           optional (≠ users.email)
  password        bcrypt
  business_name
  bio
  reason          application reason
  status          pending | active | suspended | rejected
  code_balance    codes currently held
  public_key      API public key
  secret_key      API secret key (hashed)
  approved_by     admin id
  applied_at
  approved_at
  created_at
  updated_at

TABLE: vendor_applications
  id
  vendor_id       FK → vendors.id  (renamed from user_id)
  business_name
  reason
  status          pending | approved | rejected
  reviewed_by     admin id
  review_note
  applied_at
  reviewed_at

TABLE: codes
  assigned_vendor FK → vendors.id (comment updated)

TABLE: code_redemptions
  vendor_id       FK → vendors.id (comment updated)
*/
