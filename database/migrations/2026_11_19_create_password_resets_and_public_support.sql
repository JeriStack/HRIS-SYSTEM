-- =============================================================================
-- Migration: Public Help & Support, Password Recovery, and Login Assistance
-- Purpose:
--   1. Create `password_resets`: single-use selector + HMAC-hashed verifier
--      tokens with 15-minute expiration, explicit invalidation, and IP/email
--      request telemetry.
--   2. Create `public_support_ticket_sequences`: race-free AUTH-YYYY-NNNNN
--      ticket reference counter separated from internal SUP-* sequences.
--   3. Create `public_support_tracking_tokens`: unauthenticated secret tracking
--      tokens (selector + HMAC hash) for redacted public ticket lookup.
--   4. Extend `support_tickets` to allow null employee/user ownership on
--      pre-login `public_auth` tickets and store requester context cleanly.
--   5. Extend `support_messages.sender_role` with `public_requester`.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Password Reset Tokens
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `selector` CHAR(32) NOT NULL COMMENT 'Public 128-bit hex token identifier',
    `token_hash` CHAR(64) NOT NULL COMMENT 'HMAC-SHA256 digest of secret validator',
    `requested_email` VARCHAR(150) NOT NULL,
    `requested_ip` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` VARCHAR(255) NULL DEFAULT NULL,
    `expires_at` DATETIME NOT NULL,
    `consumed_at` DATETIME NULL DEFAULT NULL,
    `invalidated_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY `uq_password_resets_selector` (`selector`),
    INDEX `idx_password_resets_user_active` (`user_id`, `consumed_at`, `invalidated_at`, `expires_at`),
    INDEX `idx_password_resets_email_created` (`requested_email`, `created_at`),
    INDEX `idx_password_resets_ip_created` (`requested_ip`, `created_at`),
    INDEX `idx_password_resets_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Public Auth Support Ticket Sequences (AUTH-YYYY-NNNNN)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `public_support_ticket_sequences` (
    `ticket_year` SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
    `last_sequence` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Public Support Ticket Tracking Tokens
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `public_support_tracking_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT UNSIGNED NOT NULL,
    `selector` CHAR(32) NOT NULL COMMENT 'Public 128-bit hex token identifier',
    `token_hash` CHAR(64) NOT NULL COMMENT 'HMAC-SHA256 digest of secret tracking validator',
    `expires_at` DATETIME NOT NULL,
    `revoked_at` DATETIME NULL DEFAULT NULL,
    `last_viewed_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pstt_ticket` FOREIGN KEY (`ticket_id`)
        REFERENCES `support_tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY `uq_pstt_selector` (`selector`),
    INDEX `idx_pstt_ticket_active` (`ticket_id`, `revoked_at`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Extend support_tickets for nullable ownership & public login issue metadata
-- -----------------------------------------------------------------------------
SET @emp_nullable = (
    SELECT IS_NULLABLE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'support_tickets'
      AND COLUMN_NAME = 'employee_id'
);
SET @sql = IF(@emp_nullable = 'NO',
    'ALTER TABLE `support_tickets` MODIFY COLUMN `employee_id` INT UNSIGNED NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sub_nullable = (
    SELECT IS_NULLABLE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'support_tickets'
      AND COLUMN_NAME = 'submitted_by_user_id'
);
SET @sql = IF(@sub_nullable = 'NO',
    'ALTER TABLE `support_tickets` MODIFY COLUMN `submitted_by_user_id` INT UNSIGNED NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'support_tickets' AND COLUMN_NAME = 'support_channel'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `support_tickets` ADD COLUMN `support_channel` ENUM(\'employee_portal\', \'public_auth\') NOT NULL DEFAULT \'employee_portal\' AFTER `submitted_by_user_id`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'support_tickets' AND COLUMN_NAME = 'is_public_ticket'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `support_tickets` ADD COLUMN `is_public_ticket` TINYINT(1) NOT NULL DEFAULT 0 AFTER `support_channel`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'support_tickets' AND COLUMN_NAME = 'requester_name'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `support_tickets` ADD COLUMN `requester_name` VARCHAR(120) NULL DEFAULT NULL AFTER `is_public_ticket`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'support_tickets' AND COLUMN_NAME = 'requester_email'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `support_tickets` ADD COLUMN `requester_email` VARCHAR(150) NULL DEFAULT NULL AFTER `requester_name`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'support_tickets' AND COLUMN_NAME = 'matched_user_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `support_tickets` ADD COLUMN `matched_user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `requester_email`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'support_tickets' AND COLUMN_NAME = 'public_issue_datetime'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `support_tickets` ADD COLUMN `public_issue_datetime` VARCHAR(100) NULL DEFAULT NULL AFTER `matched_user_id`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'support_tickets' AND COLUMN_NAME = 'public_client_env'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `support_tickets` ADD COLUMN `public_client_env` VARCHAR(255) NULL DEFAULT NULL AFTER `public_issue_datetime`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'support_tickets'
      AND CONSTRAINT_NAME = 'fk_st_matched_user'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `support_tickets` ADD CONSTRAINT `fk_st_matched_user` FOREIGN KEY (`matched_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'support_tickets' AND INDEX_NAME = 'idx_st_public_channel'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `support_tickets` ADD INDEX `idx_st_public_channel` (`support_channel`, `is_public_ticket`, `status`, `last_activity_at`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'support_tickets' AND INDEX_NAME = 'idx_st_requester_email'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `support_tickets` ADD INDEX `idx_st_requester_email` (`requester_email`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 5. Extend support_messages.sender_role to distinguish unauthenticated requester
-- -----------------------------------------------------------------------------
SET @sm_enum = (
    SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'support_messages'
      AND COLUMN_NAME = 'sender_role'
);
SET @sql = IF(LOCATE('public_requester', @sm_enum) = 0,
    'ALTER TABLE `support_messages` MODIFY COLUMN `sender_role` ENUM(\'employee\', \'public_requester\', \'hr\', \'admin\', \'system\') NOT NULL DEFAULT \'employee\'',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
