-- =============================================================================
-- Migration: Smart OTP Authentication & Trusted Device Management
-- Purpose:
--   1. Create `trusted_devices`: cryptographic selector + hashed validator
--      persistence for Remember This Device (fixed expiry, single-use rotation,
--      immediate revocation, and descriptive metadata).
--   2. Create `user_auth_security_states`: per-user risk-based step-up &
--      recovery gate (password reset, email change, MFA changes, anomalies)
--      without altering the employee directory schema.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `trusted_devices` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `selector` CHAR(32) NOT NULL COMMENT 'Public 128-bit hex token identifier',
    `token_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 or HMAC-SHA256 verifier of secret validator',
    `device_name` VARCHAR(150) NOT NULL COMMENT 'Human-readable browser and OS label for display',
    `browser_name` VARCHAR(64) NULL DEFAULT NULL,
    `os_name` VARCHAR(64) NULL DEFAULT NULL,
    `user_agent` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Descriptive UA metadata; never proves identity',
    `last_ip` VARCHAR(45) NULL DEFAULT NULL COMMENT 'Contextual IP metadata; never primary trust proof',
    `trust_days` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used_at` DATETIME NULL DEFAULT NULL,
    `expires_at` DATETIME NOT NULL,
    `revoked_at` DATETIME NULL DEFAULT NULL,
    `revocation_reason` VARCHAR(100) NULL DEFAULT NULL,
    CONSTRAINT `fk_trusted_devices_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY `uq_trusted_devices_selector` (`selector`),
    INDEX `idx_trusted_devices_user_active` (`user_id`, `revoked_at`, `expires_at`),
    INDEX `idx_trusted_devices_expires` (`expires_at`),
    INDEX `idx_trusted_devices_last_used` (`user_id`, `last_used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_auth_security_states` (
    `user_id` INT UNSIGNED PRIMARY KEY,
    `require_otp_next_login` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Forces fresh email OTP verification on next login even for trusted browsers',
    `step_up_reason` VARCHAR(100) NULL DEFAULT NULL,
    `password_changed_at` DATETIME NULL DEFAULT NULL,
    `email_changed_at` DATETIME NULL DEFAULT NULL,
    `mfa_changed_at` DATETIME NULL DEFAULT NULL,
    `last_recovery_at` DATETIME NULL DEFAULT NULL,
    `last_suspicious_event_at` DATETIME NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_user_auth_security_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
