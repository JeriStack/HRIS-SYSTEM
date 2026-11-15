-- =============================================================================
-- Migration: 2026_11_15_enhance_employee_profiles.sql
-- Description: Enhance employees table and add normalized tables for emergency
--              contacts, employment history progression, and profile correction
--              requests.
-- Idempotent: Safe to re-run without destroying existing records.
-- =============================================================================

-- 1. Add civil_status column to employees
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'employees'
      AND COLUMN_NAME = 'civil_status'
);
SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE `employees` ADD COLUMN `civil_status` ENUM(\'Single\', \'Married\', \'Widowed\', \'Separated\', \'Divorced\') NULL DEFAULT NULL AFTER `gender`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Add personal_email column to employees (separate from work/login email)
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'employees'
      AND COLUMN_NAME = 'personal_email'
);
SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE `employees` ADD COLUMN `personal_email` VARCHAR(100) NULL DEFAULT NULL AFTER `email`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Add employment_type column to employees
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'employees'
      AND COLUMN_NAME = 'employment_type'
);
SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE `employees` ADD COLUMN `employment_type` VARCHAR(50) NOT NULL DEFAULT \'Regular\' AFTER `date_hired`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Add profile_picture column to employees (stores randomized safe filename token)
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'employees'
      AND COLUMN_NAME = 'profile_picture'
);
SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE `employees` ADD COLUMN `profile_picture` VARCHAR(255) NULL DEFAULT NULL AFTER `employment_status`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Index on employment_type
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'employees'
      AND INDEX_NAME = 'idx_employees_employment_type'
);
SET @sql = IF(
    @idx_exists = 0,
    'ALTER TABLE `employees` ADD INDEX `idx_employees_employment_type` (`employment_type`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Create normalized employee_emergency_contacts table
CREATE TABLE IF NOT EXISTS `employee_emergency_contacts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `full_name` VARCHAR(120) NOT NULL,
    `relationship` VARCHAR(60) NOT NULL,
    `contact_number` VARCHAR(30) NOT NULL,
    `alternative_contact_number` VARCHAR(30) NULL DEFAULT NULL,
    `address` TEXT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_emergency_contacts_employee` FOREIGN KEY (`employee_id`)
        REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_emergency_contacts_emp` (`employee_id`, `is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Create normalized employment_history timeline table (distinct from activity_logs)
CREATE TABLE IF NOT EXISTS `employment_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `effective_date` DATE NOT NULL,
    `previous_department_id` INT UNSIGNED NULL DEFAULT NULL,
    `new_department_id` INT UNSIGNED NULL DEFAULT NULL,
    `previous_position_id` INT UNSIGNED NULL DEFAULT NULL,
    `new_position_id` INT UNSIGNED NULL DEFAULT NULL,
    `employment_type` VARCHAR(50) NULL DEFAULT NULL,
    `description` TEXT NULL,
    `recorded_by` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_emp_history_employee` FOREIGN KEY (`employee_id`)
        REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_emp_history_prev_dept` FOREIGN KEY (`previous_department_id`)
        REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_emp_history_new_dept` FOREIGN KEY (`new_department_id`)
        REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_emp_history_prev_pos` FOREIGN KEY (`previous_position_id`)
        REFERENCES `positions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_emp_history_new_pos` FOREIGN KEY (`new_position_id`)
        REFERENCES `positions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_emp_history_recorded_by` FOREIGN KEY (`recorded_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_emp_history_timeline` (`employee_id`, `effective_date`, `id`),
    INDEX `idx_emp_history_event_type` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Create profile_correction_requests workflow table
CREATE TABLE IF NOT EXISTS `profile_correction_requests` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `requested_by_user_id` INT UNSIGNED NOT NULL,
    `field_name` VARCHAR(50) NOT NULL,
    `current_value` TEXT NULL,
    `requested_value` TEXT NOT NULL,
    `reason` TEXT NOT NULL,
    `status` ENUM('Pending', 'Approved', 'Rejected', 'Cancelled') NOT NULL DEFAULT 'Pending',
    `reviewed_by` INT UNSIGNED NULL DEFAULT NULL,
    `reviewed_at` DATETIME NULL DEFAULT NULL,
    `decision_reason` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pcr_employee` FOREIGN KEY (`employee_id`)
        REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pcr_requested_by` FOREIGN KEY (`requested_by_user_id`)
        REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pcr_reviewed_by` FOREIGN KEY (`reviewed_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_pcr_emp_status` (`employee_id`, `status`, `created_at`),
    INDEX `idx_pcr_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Manual Rollback Reference (Execute only under DBA supervision):
--   DROP TABLE IF EXISTS `profile_correction_requests`;
--   DROP TABLE IF EXISTS `employment_history`;
--   DROP TABLE IF EXISTS `employee_emergency_contacts`;
--   ALTER TABLE `employees`
--       DROP INDEX `idx_employees_employment_type`,
--       DROP COLUMN `profile_picture`,
--       DROP COLUMN `employment_type`,
--       DROP COLUMN `personal_email`,
--       DROP COLUMN `civil_status`;
-- =============================================================================
