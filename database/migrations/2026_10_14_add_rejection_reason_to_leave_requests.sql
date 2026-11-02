-- Non-destructive migration: Add rejection_reason column to leave_requests table
-- Phase 8: Complete Leave Management System

SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'leave_requests' 
      AND COLUMN_NAME = 'rejection_reason'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `leave_requests` ADD COLUMN `rejection_reason` TEXT NULL AFTER `reason`', 
    'SELECT "Column rejection_reason already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
