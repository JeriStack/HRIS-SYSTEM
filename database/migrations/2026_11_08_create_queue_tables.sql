-- =============================================================================
-- Migration: Create MySQL-Backed Queue and Failed Jobs Tables
-- Application: Employee HRIS & Leave Management System (NEXORA HR)
-- Concurrency-Safe Asynchronous Job Processing
-- =============================================================================

CREATE TABLE IF NOT EXISTS `queue_jobs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `queue` VARCHAR(64) NOT NULL DEFAULT 'default',
    `job_type` VARCHAR(128) NOT NULL,
    `payload` JSON NOT NULL,
    `status` ENUM('pending', 'reserved', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` INT UNSIGNED NOT NULL DEFAULT 3,
    `available_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reserved_at` DATETIME NULL DEFAULT NULL,
    `reserved_by` VARCHAR(128) NULL DEFAULT NULL,
    `last_error` TEXT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_queue_jobs_claim` (`queue`, `status`, `available_at`),
    INDEX `idx_queue_jobs_reserved` (`status`, `reserved_at`),
    INDEX `idx_queue_jobs_job_type` (`job_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `job_id` BIGINT UNSIGNED NOT NULL,
    `queue` VARCHAR(64) NOT NULL DEFAULT 'default',
    `job_type` VARCHAR(128) NOT NULL,
    `payload` JSON NOT NULL,
    `attempts` INT UNSIGNED NOT NULL,
    `exception` TEXT NOT NULL,
    `failed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_failed_jobs_job_id` (`job_id`),
    INDEX `idx_failed_jobs_queue_type` (`queue`, `job_type`),
    INDEX `idx_failed_jobs_failed_at` (`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

