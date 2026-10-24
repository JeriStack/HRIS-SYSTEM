-- =============================================================================
-- Migration: Real-time (WebSocket) Notification Support
-- Purpose:
--   1. Add nullable event metadata to the existing `notifications` table so the
--      WebSocket service can map committed rows to authorized real-time events.
--      Existing rows are preserved (columns are NULL for historical data).
--   2. Create `realtime_tickets`: short-lived, single-use WebSocket connection
--      tickets issued by the authenticated HTTP application. Only SHA-256 hashes
--      of tickets are stored.
-- Idempotent: safe to run more than once.
-- Date: 2026-10-23
-- =============================================================================

-- Step 1: notifications.event_type
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'event_type'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `notifications` ADD COLUMN `event_type` VARCHAR(64) NULL DEFAULT NULL AFTER `type`',
    'SELECT "Column notifications.event_type already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: notifications.entity_id (e.g. leave request id the event refers to)
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'entity_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `notifications` ADD COLUMN `entity_id` INT UNSIGNED NULL DEFAULT NULL AFTER `event_type`',
    'SELECT "Column notifications.entity_id already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: realtime_tickets
CREATE TABLE IF NOT EXISTS `realtime_tickets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `token_hash` CHAR(64) NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `session_ref` CHAR(32) NOT NULL,
    `session_expires_at` DATETIME NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL DEFAULT NULL,
    `revoked_at` DATETIME NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_realtime_tickets_hash` UNIQUE (`token_hash`),
    CONSTRAINT `fk_realtime_tickets_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_realtime_tickets_session` (`session_ref`, `revoked_at`),
    INDEX `idx_realtime_tickets_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
