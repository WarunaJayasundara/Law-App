-- Adds the admin-editable settings store (SMS message template) and its
-- permission. Safe to run more than once. Only needed for databases created
-- before this change; a fresh install gets the same objects from
-- nithi_docket.sql.
--   mysql -u <user> -p nithi_docket < database/migrations/2026_09_19_settings.sql

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(64) NOT NULL PRIMARY KEY,
  `setting_value` TEXT NOT NULL,
  `updated_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `permissions` (`name`, `description`)
VALUES ('settings.manage', 'Edit system settings such as the SMS message template');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.name = 'settings.manage' WHERE r.name = 'admin';
