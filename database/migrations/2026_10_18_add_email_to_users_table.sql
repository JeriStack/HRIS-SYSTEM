-- =============================================================================
-- Migration: Add Email Column to Users Table
-- Purpose: Support direct unique email authentication on the users table
--          while preserving the foreign key relationship with employees.
-- Date: 2026-10-18
-- =============================================================================

-- Step 1: Add email column if it does not already exist
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'users' 
      AND COLUMN_NAME = 'email'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `users` ADD COLUMN `email` VARCHAR(100) NULL AFTER `employee_id`', 
    'SELECT "Column email already exists on users table"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Backfill email from associated employees table (normalized to lowercase)
UPDATE `users` u
INNER JOIN `employees` e ON u.employee_id = e.id
SET u.email = LOWER(TRIM(e.email))
WHERE u.email IS NULL OR u.email = '';

-- Step 3: Add unique constraint on users.email if not already present
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'users' 
      AND INDEX_NAME = 'uq_users_email'
);

SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE `users` ADD CONSTRAINT `uq_users_email` UNIQUE (`email`)', 
    'SELECT "Index uq_users_email already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
