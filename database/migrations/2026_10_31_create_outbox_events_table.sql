-- ==============================================================================
-- Schema Migration: Create Transactional Outbox Events Table
-- HRIS-SYSTEM — Reliability and Production Readiness Upgrade (Phase 1)
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `outbox_events` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_id` VARCHAR(36) NOT NULL,
    `event_type` VARCHAR(191) NOT NULL,
    `aggregate_type` VARCHAR(100) NOT NULL,
    `aggregate_id` VARCHAR(100) NOT NULL,
    `payload` JSON NOT NULL,
    `status` ENUM('PENDING', 'PROCESSING', 'PROCESSED', 'FAILED') NOT NULL DEFAULT 'PENDING',
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` INT UNSIGNED NOT NULL DEFAULT 5,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `next_attempt_at` DATETIME(6) NULL DEFAULT CURRENT_TIMESTAMP(6),
    `processed_at` DATETIME(6) NULL,
    `claimed_at` DATETIME(6) NULL,
    `claimed_by` VARCHAR(191) NULL,
    `last_error` TEXT NULL,
    `last_error_code` VARCHAR(100) NULL,
    UNIQUE KEY `uq_outbox_event_id` (`event_id`),
    INDEX `idx_outbox_status_next_attempt` (`status`, `next_attempt_at`),
    INDEX `idx_outbox_claimed` (`status`, `claimed_at`),
    INDEX `idx_outbox_aggregate` (`aggregate_type`, `aggregate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

