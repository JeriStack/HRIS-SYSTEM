-- =============================================================================
-- Migration: 2026_11_08_enhance_audit_trail_schema.sql
-- Description: Enhance activity_logs schema for comprehensive audit trail and security activity monitoring
-- =============================================================================

-- 1. Add event_id column
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'activity_logs' 
      AND COLUMN_NAME = 'event_id'
);
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `activity_logs` ADD COLUMN `event_id` VARCHAR(64) NULL AFTER `id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Add actor_role column
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'activity_logs' 
      AND COLUMN_NAME = 'actor_role'
);
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `activity_logs` ADD COLUMN `actor_role` VARCHAR(30) NULL AFTER `user_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Add category column
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'activity_logs' 
      AND COLUMN_NAME = 'category'
);
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `activity_logs` ADD COLUMN `category` VARCHAR(50) NOT NULL DEFAULT \'general\' AFTER `action`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Add old_values column
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'activity_logs' 
      AND COLUMN_NAME = 'old_values'
);
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `activity_logs` ADD COLUMN `old_values` LONGTEXT NULL AFTER `entity_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. Add new_values column
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'activity_logs' 
      AND COLUMN_NAME = 'new_values'
);
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `activity_logs` ADD COLUMN `new_values` LONGTEXT NULL AFTER `old_values`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6. Add status column
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'activity_logs' 
      AND COLUMN_NAME = 'status'
);
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `activity_logs` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT \'SUCCESS\' AFTER `new_values`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 7. Add request_id column
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'activity_logs' 
      AND COLUMN_NAME = 'request_id'
);
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `activity_logs` ADD COLUMN `request_id` VARCHAR(64) NULL AFTER `user_agent`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 8. Add index on event_id
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'activity_logs' 
      AND INDEX_NAME = 'idx_activity_logs_event_id'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `activity_logs` ADD INDEX `idx_activity_logs_event_id` (`event_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 9. Add index on category
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'activity_logs' 
      AND INDEX_NAME = 'idx_activity_logs_category'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `activity_logs` ADD INDEX `idx_activity_logs_category` (`category`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 10. Add index on status
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'activity_logs' 
      AND INDEX_NAME = 'idx_activity_logs_status'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `activity_logs` ADD INDEX `idx_activity_logs_status` (`status`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 11. Add composite index on entity_type, entity_id
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'activity_logs' 
      AND INDEX_NAME = 'idx_activity_logs_entity'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `activity_logs` ADD INDEX `idx_activity_logs_entity` (`entity_type`, `entity_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

