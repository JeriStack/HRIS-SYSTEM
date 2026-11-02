-- ==============================================================================
-- Schema Migration: Create User TOTP (RFC 6238) Authentication Table
-- HRIS-SYSTEM — Reliability and Production Readiness Upgrade (Phase 2)
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `user_totp` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `encrypted_secret` VARCHAR(500) NOT NULL,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `confirmed_at` DATETIME NULL,
    `last_used_timestep` BIGINT UNSIGNED NULL,
    `recovery_codes` JSON NULL,
    `failed_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_totp_user_id` (`user_id`),
    CONSTRAINT `fk_user_totp_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

