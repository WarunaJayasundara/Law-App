-- Nithi Docket — Deed Registry Tracker
-- MySQL 8.x schema. Run once against an empty database.

CREATE DATABASE IF NOT EXISTS `nithi_docket` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nithi_docket`;

-- ---------------------------------------------------------------------
-- RBAC
-- ---------------------------------------------------------------------

CREATE TABLE `roles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `description` VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE `permissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(80) NOT NULL UNIQUE,
  `description` VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE `role_permissions` (
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `username` VARCHAR(60) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `failed_login_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` DATETIME NULL,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Parties
-- ---------------------------------------------------------------------

CREATE TABLE `buyers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(150) NOT NULL,
  `nic` VARCHAR(20) NOT NULL,
  `mobile` VARCHAR(20) NULL,
  `email` VARCHAR(150) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_buyer_nic` (`nic`),
  INDEX `idx_buyer_name` (`full_name`)
) ENGINE=InnoDB;

CREATE TABLE `sellers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(150) NOT NULL,
  `nic` VARCHAR(20) NOT NULL,
  `mobile` VARCHAR(20) NULL,
  `email` VARCHAR(150) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_seller_nic` (`nic`),
  INDEX `idx_seller_name` (`full_name`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Deeds + registration workflow
-- ---------------------------------------------------------------------

CREATE TABLE `deeds` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `deed_number` VARCHAR(60) NOT NULL,
  `category` ENUM('Transfer Deed','Lease Deed','Mortgage Deed','Gift Deed','Power of Attorney','Other') NOT NULL,
  `buyer_id` INT UNSIGNED NOT NULL,
  `seller_id` INT UNSIGNED NOT NULL,
  `folio_number` VARCHAR(60) NULL,
  `amount` DECIMAL(14,2) NULL,
  `value` DECIMAL(14,2) NULL,
  `other_document_numbers` TEXT NULL,
  `status` ENUM('Submitted','Reviewed','Received') NOT NULL DEFAULT 'Submitted',
  `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT UNSIGNED NOT NULL,
  `updated_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_deed_number` (`deed_number`),
  INDEX `idx_deed_status` (`status`),
  INDEX `idx_deed_category` (`category`),
  INDEX `idx_deed_folio` (`folio_number`),
  FOREIGN KEY (`buyer_id`) REFERENCES `buyers`(`id`),
  FOREIGN KEY (`seller_id`) REFERENCES `sellers`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

CREATE TABLE `registration_details` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `deed_id` INT UNSIGNED NOT NULL UNIQUE,
  `reviewed` TINYINT(1) NOT NULL DEFAULT 0,
  `reviewed_at` DATETIME NULL,
  `reviewed_by` INT UNSIGNED NULL,
  `received` TINYINT(1) NOT NULL DEFAULT 0,
  `received_at` DATETIME NULL,
  `received_by` INT UNSIGNED NULL,
  `register_date` DATE NULL,
  `day_book_number` VARCHAR(60) NULL,
  `new_folio_number` VARCHAR(60) NULL,
  `notification_sent` TINYINT(1) NOT NULL DEFAULT 0,
  `notification_sent_at` DATETIME NULL,
  `notes` TEXT NULL,
  `updated_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`deed_id`) REFERENCES `deeds`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`received_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Notifications (Firebase Cloud Messaging)
-- ---------------------------------------------------------------------

CREATE TABLE `fcm_tokens` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `device_type` ENUM('web','android','ios') NOT NULL DEFAULT 'web',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_token` (`token`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `target_type` ENUM('topic','user') NOT NULL DEFAULT 'topic',
  `target_value` VARCHAR(100) NOT NULL,
  `related_deed_id` INT UNSIGNED NULL,
  `sent_by` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','sent','failed','partial') NOT NULL DEFAULT 'pending',
  `error_message` TEXT NULL,
  `recipient_count` INT UNSIGNED NULL,
  `sent_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`related_deed_id`) REFERENCES `deeds`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`sent_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

CREATE TABLE `notification_recipients` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `notification_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `delivery_status` ENUM('pending','delivered','failed') NOT NULL DEFAULT 'pending',
  `delivered_at` DATETIME NULL,
  `error_message` TEXT NULL,
  FOREIGN KEY (`notification_id`) REFERENCES `notifications`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Audit trail
-- ---------------------------------------------------------------------

CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(60) NOT NULL,
  `entity_id` INT UNSIGNED NULL,
  `old_values` JSON NULL,
  `new_values` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
  INDEX `idx_audit_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Settings (admin-editable, e.g. the "deed received" SMS wording)
-- ---------------------------------------------------------------------

CREATE TABLE `settings` (
  `setting_key` VARCHAR(64) NOT NULL PRIMARY KEY,
  `setting_value` TEXT NOT NULL,
  `updated_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Seed data (roles/permissions only — no customer data)
-- ---------------------------------------------------------------------

INSERT INTO `roles` (`name`, `description`) VALUES
  ('admin', 'Full access: users, deeds, notifications, audit logs, settings'),
  ('staff', 'Day-to-day deed entry and registration tracking');

INSERT INTO `permissions` (`name`, `description`) VALUES
  ('deeds.view', 'View deeds'),
  ('deeds.create', 'Create deeds'),
  ('deeds.update', 'Update deed and registration details'),
  ('deeds.archive', 'Archive/delete deeds'),
  ('notifications.send', 'Send push notifications'),
  ('notifications.view', 'View notification history'),
  ('users.manage', 'Manage users and roles'),
  ('audit.view', 'View audit logs'),
  ('settings.manage', 'Edit system settings such as the SMS message template');

-- admin: every permission
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT id FROM roles WHERE name = 'admin'), id FROM permissions;

-- staff: operational subset (no user management, no audit log access)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT id FROM roles WHERE name = 'staff'), id FROM permissions
WHERE name IN ('deeds.view','deeds.create','deeds.update','notifications.send','notifications.view');
