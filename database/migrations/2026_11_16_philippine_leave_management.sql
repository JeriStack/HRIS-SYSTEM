-- =============================================================================
-- Migration: Philippine Statutory & Company Leave Management Enhancement
-- Date: 2026-11-16
-- Description:
--   1. Extends leave_types, leave_balances, and leave_requests for Philippine
--      statutory and company leave classifications, working/calendar units,
--      confidential handling (RA 9262 VAWC / RA 9710 SLW), and clarification flow.
--   2. Creates leave_policy_versions, employee_leave_qualifications,
--      leave_event_entitlements, leave_balance_transactions (immutable ledger),
--      leave_request_documents, and leave_sensitive_access_logs.
--   3. Classifies existing leave types and idempotently seeds Philippine
--      statutory/company leave types, V1 policies, ledger backfill, and RBAC
--      permissions (leave_policies.manage, leave_qualifications.verify,
--      leave_confidential.view).
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Extend leave_types with classification, benefit category & policy link
-- -----------------------------------------------------------------------------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_types' AND COLUMN_NAME = 'leave_code');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_types` ADD COLUMN `leave_code` VARCHAR(30) NULL DEFAULT NULL AFTER `leave_name`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_types' AND COLUMN_NAME = 'classification');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_types` ADD COLUMN `classification` ENUM(''Statutory'', ''Company'') NOT NULL DEFAULT ''Company'' AFTER `description`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_types' AND COLUMN_NAME = 'benefit_category');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_types` ADD COLUMN `benefit_category` ENUM(''StatutoryAnnual'', ''StatutoryEvent'', ''CompanyAnnual'', ''CompanyEvent'', ''Unpaid'') NOT NULL DEFAULT ''CompanyAnnual'' AFTER `classification`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_types' AND COLUMN_NAME = 'entitlement_model');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_types` ADD COLUMN `entitlement_model` ENUM(''annual'', ''event_based'', ''unpaid'') NOT NULL DEFAULT ''annual'' AFTER `benefit_category`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_types' AND COLUMN_NAME = 'legal_reference');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_types` ADD COLUMN `legal_reference` VARCHAR(191) NULL DEFAULT NULL AFTER `entitlement_model`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_types' AND COLUMN_NAME = 'default_unit');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_types` ADD COLUMN `default_unit` ENUM(''working_days'', ''calendar_days'') NOT NULL DEFAULT ''working_days'' AFTER `default_days`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_types' AND COLUMN_NAME = 'is_paid');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_types` ADD COLUMN `is_paid` TINYINT(1) NOT NULL DEFAULT 1 AFTER `default_unit`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_types' AND COLUMN_NAME = 'is_confidential');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_types` ADD COLUMN `is_confidential` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_paid`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_types' AND COLUMN_NAME = 'statutory_protected');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_types` ADD COLUMN `statutory_protected` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_confidential`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_types' AND COLUMN_NAME = 'active_policy_version_id');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_types` ADD COLUMN `active_policy_version_id` INT UNSIGNED NULL DEFAULT NULL AFTER `statutory_protected`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_types' AND INDEX_NAME = 'uq_leave_types_code');
SET @sql := IF(@idx = 0, 'ALTER TABLE `leave_types` ADD CONSTRAINT `uq_leave_types_code` UNIQUE (`leave_code`)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_types' AND INDEX_NAME = 'idx_leave_types_model');
SET @sql := IF(@idx = 0, 'ALTER TABLE `leave_types` ADD INDEX `idx_leave_types_model` (`classification`, `entitlement_model`, `status`)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- -----------------------------------------------------------------------------
-- 2. Extend leave_balances with carry-over, SIL conversion, expiration & notes
-- -----------------------------------------------------------------------------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_balances' AND COLUMN_NAME = 'carried_over_days');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_balances` ADD COLUMN `carried_over_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER `remaining_days`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_balances' AND COLUMN_NAME = 'converted_days');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_balances` ADD COLUMN `converted_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER `carried_over_days`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_balances' AND COLUMN_NAME = 'expired_days');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_balances` ADD COLUMN `expired_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER `converted_days`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_balances' AND COLUMN_NAME = 'policy_version_id');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_balances` ADD COLUMN `policy_version_id` INT UNSIGNED NULL DEFAULT NULL AFTER `expired_days`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_balances' AND COLUMN_NAME = 'sil_credited_from_vl');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_balances` ADD COLUMN `sil_credited_from_vl` TINYINT(1) NOT NULL DEFAULT 0 AFTER `policy_version_id`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_balances' AND COLUMN_NAME = 'eligibility_status');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_balances` ADD COLUMN `eligibility_status` ENUM(''Available'', ''PendingVerification'', ''CreditedViaVL'', ''Ineligible'', ''NotAllocated'') NOT NULL DEFAULT ''Available'' AFTER `sil_credited_from_vl`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_balances' AND COLUMN_NAME = 'notes');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_balances` ADD COLUMN `notes` VARCHAR(255) NULL DEFAULT NULL AFTER `eligibility_status`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_balances' AND INDEX_NAME = 'idx_lb_policy_version');
SET @sql := IF(@idx = 0, 'ALTER TABLE `leave_balances` ADD INDEX `idx_lb_policy_version` (`policy_version_id`)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- -----------------------------------------------------------------------------
-- 3. Create leave_policy_versions (non-retroactive versioned rules)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leave_policy_versions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `leave_type_id` INT UNSIGNED NOT NULL,
    `version_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `policy_code` VARCHAR(50) NOT NULL,
    `effective_from` DATE NOT NULL,
    `effective_to` DATE NULL DEFAULT NULL,
    `approval_status` ENUM('Draft', 'PendingReview', 'Active', 'Superseded', 'Archived') NOT NULL DEFAULT 'Draft',
    `classification` ENUM('Statutory', 'Company') NOT NULL DEFAULT 'Company',
    `entitlement_model` ENUM('annual', 'event_based', 'unpaid') NOT NULL DEFAULT 'annual',
    `benefit_tier` ENUM('statutory_minimum', 'employer_enhanced', 'company_benefit', 'unpaid') NOT NULL DEFAULT 'company_benefit',
    `sector_scope` ENUM('all', 'private', 'public') NOT NULL DEFAULT 'all',
    `legal_reference` VARCHAR(191) NULL DEFAULT NULL,
    `entitlement_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `entitlement_unit` ENUM('working_days', 'calendar_days') NOT NULL DEFAULT 'working_days',
    `is_paid` TINYINT(1) NOT NULL DEFAULT 1,
    `allocation_frequency` ENUM('annual', 'per_event', 'unlimited_unpaid') NOT NULL DEFAULT 'annual',
    `carry_over_allowed` TINYINT(1) NOT NULL DEFAULT 0,
    `max_carry_over_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `cash_convertible` TINYINT(1) NOT NULL DEFAULT 0,
    `cash_conversion_max_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `min_service_months` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `service_window_months` SMALLINT UNSIGNED NULL DEFAULT NULL,
    `counts_as_sil_equivalent` TINYINT(1) NOT NULL DEFAULT 0,
    `stack_with_equivalent` TINYINT(1) NOT NULL DEFAULT 0,
    `max_events_lifetime` SMALLINT UNSIGNED NULL DEFAULT NULL,
    `max_extension_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `requires_documentation` TINYINT(1) NOT NULL DEFAULT 0,
    `document_requirements` TEXT NULL,
    `requires_qualification` TINYINT(1) NOT NULL DEFAULT 0,
    `qualification_type` VARCHAR(40) NULL DEFAULT NULL,
    `is_confidential` TINYINT(1) NOT NULL DEFAULT 0,
    `rules_json` JSON NULL DEFAULT NULL,
    `change_notes` TEXT NULL,
    `created_by` INT UNSIGNED NULL DEFAULT NULL,
    `approved_by` INT UNSIGNED NULL DEFAULT NULL,
    `approved_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_policy_type_version` UNIQUE (`leave_type_id`, `version_number`),
    CONSTRAINT `fk_policy_versions_type` FOREIGN KEY (`leave_type_id`)
        REFERENCES `leave_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_policy_versions_created_by` FOREIGN KEY (`created_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_policy_versions_approved_by` FOREIGN KEY (`approved_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_policy_type_status` (`leave_type_id`, `approval_status`),
    INDEX `idx_policy_effective_window` (`effective_from`, `effective_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Create employee_leave_qualifications (SPIC, Paternity, SIL exclusions, etc.)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employee_leave_qualifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `qualification_type` ENUM('solo_parent_spic', 'paternity_marriage', 'sil_exclusion', 'maternity_eligibility', 'slw_eligibility', 'vawc_protection') NOT NULL,
    `verification_status` ENUM('PendingVerification', 'Verified', 'Expired', 'Revoked', 'Rejected') NOT NULL DEFAULT 'PendingVerification',
    `reference_no` VARCHAR(100) NULL DEFAULT NULL,
    `valid_from` DATE NULL DEFAULT NULL,
    `valid_to` DATE NULL DEFAULT NULL,
    `sil_exempt_override` TINYINT(1) NOT NULL DEFAULT 0,
    `exclusion_reason` VARCHAR(150) NULL DEFAULT NULL,
    `supporting_notes` TEXT NULL,
    `verified_by` INT UNSIGNED NULL DEFAULT NULL,
    `verified_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_emp_qual_employee` FOREIGN KEY (`employee_id`)
        REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_emp_qual_verifier` FOREIGN KEY (`verified_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_emp_qual_lookup` (`employee_id`, `qualification_type`, `verification_status`, `valid_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Create leave_event_entitlements (Maternity, Paternity, SLW, VAWC, Bereavement)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leave_event_entitlements` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `leave_type_id` INT UNSIGNED NOT NULL,
    `policy_version_id` INT UNSIGNED NULL DEFAULT NULL,
    `event_code` VARCHAR(40) NOT NULL,
    `event_scenario` VARCHAR(60) NOT NULL,
    `event_date` DATE NOT NULL,
    `delivery_sequence` TINYINT UNSIGNED NULL DEFAULT NULL,
    `spouse_name` VARCHAR(120) NULL DEFAULT NULL,
    `relationship_type` VARCHAR(60) NULL DEFAULT NULL,
    `entitled_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `transferred_out_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `unpaid_extension_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `used_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `pending_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `remaining_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `valid_from` DATE NOT NULL,
    `valid_to` DATE NULL DEFAULT NULL,
    `verification_status` ENUM('PendingVerification', 'Verified', 'Active', 'Completed', 'Expired', 'Cancelled') NOT NULL DEFAULT 'PendingVerification',
    `is_confidential` TINYINT(1) NOT NULL DEFAULT 0,
    `metadata_json` JSON NULL DEFAULT NULL,
    `created_by` INT UNSIGNED NULL DEFAULT NULL,
    `verified_by` INT UNSIGNED NULL DEFAULT NULL,
    `verified_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_event_entitlement_code` UNIQUE (`employee_id`, `event_code`),
    CONSTRAINT `fk_event_ent_employee` FOREIGN KEY (`employee_id`)
        REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_event_ent_leave_type` FOREIGN KEY (`leave_type_id`)
        REFERENCES `leave_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_event_ent_policy` FOREIGN KEY (`policy_version_id`)
        REFERENCES `leave_policy_versions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_event_ent_created_by` FOREIGN KEY (`created_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_event_ent_verified_by` FOREIGN KEY (`verified_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_event_ent_lookup` (`employee_id`, `leave_type_id`, `verification_status`),
    INDEX `idx_event_ent_delivery` (`employee_id`, `leave_type_id`, `delivery_sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Extend leave_requests with policy snapshot, event link, confidential & info flow
-- -----------------------------------------------------------------------------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'policy_version_id');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_requests` ADD COLUMN `policy_version_id` INT UNSIGNED NULL DEFAULT NULL AFTER `leave_type_id`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'event_entitlement_id');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_requests` ADD COLUMN `event_entitlement_id` INT UNSIGNED NULL DEFAULT NULL AFTER `policy_version_id`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'entitlement_unit');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_requests` ADD COLUMN `entitlement_unit` ENUM(''working_days'', ''calendar_days'') NOT NULL DEFAULT ''working_days'' AFTER `number_of_days`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'is_paid');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_requests` ADD COLUMN `is_paid` TINYINT(1) NOT NULL DEFAULT 1 AFTER `entitlement_unit`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'is_confidential');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_requests` ADD COLUMN `is_confidential` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_paid`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'application_metadata_json');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_requests` ADD COLUMN `application_metadata_json` JSON NULL DEFAULT NULL AFTER `reason`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'info_request_status');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_requests` ADD COLUMN `info_request_status` ENUM(''None'', ''Requested'', ''Responded'') NOT NULL DEFAULT ''None'' AFTER `rejection_reason`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'hr_info_request_note');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_requests` ADD COLUMN `hr_info_request_note` TEXT NULL AFTER `info_request_status`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'employee_info_response');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_requests` ADD COLUMN `employee_info_response` TEXT NULL AFTER `hr_info_request_note`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'info_requested_by');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_requests` ADD COLUMN `info_requested_by` INT UNSIGNED NULL DEFAULT NULL AFTER `employee_info_response`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'info_requested_at');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_requests` ADD COLUMN `info_requested_at` DATETIME NULL DEFAULT NULL AFTER `info_requested_by`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'info_responded_at');
SET @sql := IF(@col = 0, 'ALTER TABLE `leave_requests` ADD COLUMN `info_responded_at` DATETIME NULL DEFAULT NULL AFTER `info_requested_at`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND INDEX_NAME = 'idx_lr_policy_version');
SET @sql := IF(@idx = 0, 'ALTER TABLE `leave_requests` ADD INDEX `idx_lr_policy_version` (`policy_version_id`)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND INDEX_NAME = 'idx_lr_event_entitlement');
SET @sql := IF(@idx = 0, 'ALTER TABLE `leave_requests` ADD INDEX `idx_lr_event_entitlement` (`event_entitlement_id`)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND INDEX_NAME = 'idx_lr_confidential');
SET @sql := IF(@idx = 0, 'ALTER TABLE `leave_requests` ADD INDEX `idx_lr_confidential` (`is_confidential`)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND CONSTRAINT_NAME = 'fk_lr_policy_version');
SET @sql := IF(@fk = 0, 'ALTER TABLE `leave_requests` ADD CONSTRAINT `fk_lr_policy_version` FOREIGN KEY (`policy_version_id`) REFERENCES `leave_policy_versions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND CONSTRAINT_NAME = 'fk_lr_event_entitlement');
SET @sql := IF(@fk = 0, 'ALTER TABLE `leave_requests` ADD CONSTRAINT `fk_lr_event_entitlement` FOREIGN KEY (`event_entitlement_id`) REFERENCES `leave_event_entitlements` (`id`) ON DELETE SET NULL ON UPDATE CASCADE', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- -----------------------------------------------------------------------------
-- 7. Create leave_balance_transactions (Immutable Ledger)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leave_balance_transactions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `leave_type_id` INT UNSIGNED NOT NULL,
    `leave_balance_id` INT UNSIGNED NULL DEFAULT NULL,
    `leave_request_id` INT UNSIGNED NULL DEFAULT NULL,
    `event_entitlement_id` INT UNSIGNED NULL DEFAULT NULL,
    `policy_version_id` INT UNSIGNED NULL DEFAULT NULL,
    `balance_year` SMALLINT UNSIGNED NOT NULL,
    `transaction_type` ENUM('initial_allocation', 'annual_allocation', 'approved_deduction', 'balance_restoration', 'adjustment', 'carry_over', 'expiration', 'sil_conversion', 'event_allocation', 'unpaid_recorded') NOT NULL,
    `days_delta` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `balance_before` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `balance_after` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `conversion_scope` ENUM('none', 'annual_unused', 'separation') NOT NULL DEFAULT 'none',
    `conversion_reference` VARCHAR(80) NULL DEFAULT NULL,
    `remarks` VARCHAR(255) NOT NULL,
    `is_confidential` TINYINT(1) NOT NULL DEFAULT 0,
    `actor_user_id` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_lbt_employee` FOREIGN KEY (`employee_id`)
        REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_lbt_leave_type` FOREIGN KEY (`leave_type_id`)
        REFERENCES `leave_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_lbt_leave_balance` FOREIGN KEY (`leave_balance_id`)
        REFERENCES `leave_balances` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_lbt_leave_request` FOREIGN KEY (`leave_request_id`)
        REFERENCES `leave_requests` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_lbt_event_entitlement` FOREIGN KEY (`event_entitlement_id`)
        REFERENCES `leave_event_entitlements` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_lbt_policy_version` FOREIGN KEY (`policy_version_id`)
        REFERENCES `leave_policy_versions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_lbt_actor_user` FOREIGN KEY (`actor_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_lbt_emp_year` (`employee_id`, `balance_year`, `created_at`),
    INDEX `idx_lbt_type_year` (`leave_type_id`, `balance_year`),
    INDEX `idx_lbt_request` (`leave_request_id`),
    INDEX `idx_lbt_tx_type` (`transaction_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Create leave_request_documents (Protected non-public uploads metadata)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leave_request_documents` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `leave_request_id` INT UNSIGNED NOT NULL,
    `employee_id` INT UNSIGNED NOT NULL,
    `uploaded_by_user_id` INT UNSIGNED NULL DEFAULT NULL,
    `document_category` VARCHAR(50) NOT NULL DEFAULT 'supporting_document',
    `original_filename` VARCHAR(255) NOT NULL,
    `stored_filename` VARCHAR(255) NOT NULL,
    `storage_relative_path` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size_bytes` INT UNSIGNED NOT NULL,
    `sha256_checksum` CHAR(64) NOT NULL,
    `is_confidential` TINYINT(1) NOT NULL DEFAULT 0,
    `review_status` ENUM('PendingReview', 'Accepted', 'NeedsClarification', 'Rejected') NOT NULL DEFAULT 'PendingReview',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_lrd_request` FOREIGN KEY (`leave_request_id`)
        REFERENCES `leave_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_lrd_employee` FOREIGN KEY (`employee_id`)
        REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_lrd_uploader` FOREIGN KEY (`uploaded_by_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_lrd_request` (`leave_request_id`),
    INDEX `idx_lrd_employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. Create leave_sensitive_access_logs (VAWC/SLW & document access audit)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leave_sensitive_access_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `leave_request_id` INT UNSIGNED NULL DEFAULT NULL,
    `document_id` INT UNSIGNED NULL DEFAULT NULL,
    `accessor_user_id` INT UNSIGNED NULL DEFAULT NULL,
    `action_type` ENUM('view_request', 'view_confidential_reason', 'download_document', 'export_redacted', 'verify_qualification') NOT NULL,
    `access_granted` TINYINT(1) NOT NULL DEFAULT 1,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_lsal_request` FOREIGN KEY (`leave_request_id`)
        REFERENCES `leave_requests` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_lsal_document` FOREIGN KEY (`document_id`)
        REFERENCES `leave_request_documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_lsal_accessor` FOREIGN KEY (`accessor_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_lsal_request` (`leave_request_id`, `created_at`),
    INDEX `idx_lsal_accessor` (`accessor_user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. Classify existing seeded leave types (#1-#5) idempotently
-- -----------------------------------------------------------------------------
UPDATE `leave_types`
SET `leave_code` = COALESCE(`leave_code`, 'VL'),
    `classification` = 'Company',
    `benefit_category` = 'CompanyAnnual',
    `entitlement_model` = 'annual',
    `legal_reference` = COALESCE(`legal_reference`, 'Company Policy (Satisfies Labor Code Art. 95 SIL equivalent when >= 5 paid days)'),
    `default_unit` = 'working_days',
    `is_paid` = 1,
    `is_confidential` = 0,
    `statutory_protected` = 0
WHERE `leave_name` = 'Vacation Leave';

UPDATE `leave_types`
SET `leave_code` = COALESCE(`leave_code`, 'SL'),
    `classification` = 'Company',
    `benefit_category` = 'CompanyAnnual',
    `entitlement_model` = 'annual',
    `legal_reference` = COALESCE(`legal_reference`, 'Company Policy'),
    `default_unit` = 'working_days',
    `is_paid` = 1,
    `is_confidential` = 0,
    `statutory_protected` = 0
WHERE `leave_name` = 'Sick Leave';

UPDATE `leave_types`
SET `leave_code` = COALESCE(`leave_code`, 'EL'),
    `classification` = 'Company',
    `benefit_category` = 'CompanyAnnual',
    `entitlement_model` = 'annual',
    `legal_reference` = COALESCE(`legal_reference`, 'Company Policy (Distinct from RA 8972/11861 Solo Parent Leave)'),
    `default_unit` = 'working_days',
    `is_paid` = 1,
    `is_confidential` = 0,
    `statutory_protected` = 0
WHERE `leave_name` = 'Emergency Leave';

UPDATE `leave_types`
SET `leave_code` = COALESCE(`leave_code`, 'MATERNITY'),
    `classification` = 'Statutory',
    `benefit_category` = 'StatutoryEvent',
    `entitlement_model` = 'event_based',
    `legal_reference` = COALESCE(`legal_reference`, 'RA 11210 (Expanded Maternity Leave Law)'),
    `default_days` = 105,
    `default_unit` = 'calendar_days',
    `is_paid` = 1,
    `is_confidential` = 0,
    `statutory_protected` = 1
WHERE `leave_name` = 'Maternity Leave';

UPDATE `leave_types`
SET `leave_code` = COALESCE(`leave_code`, 'PATERNITY'),
    `classification` = 'Statutory',
    `benefit_category` = 'StatutoryEvent',
    `entitlement_model` = 'event_based',
    `legal_reference` = COALESCE(`legal_reference`, 'RA 8187 (Paternity Leave Act, First 4 Deliveries of Lawful Spouse)'),
    `default_days` = 7,
    `default_unit` = 'working_days',
    `is_paid` = 1,
    `is_confidential` = 0,
    `statutory_protected` = 1
WHERE `leave_name` = 'Paternity Leave';

-- -----------------------------------------------------------------------------
-- 11. Ensure canonical base & Philippine Statutory/Company leave types exist
-- -----------------------------------------------------------------------------
INSERT INTO `leave_types` (`id`, `leave_name`, `leave_code`, `description`, `classification`, `benefit_category`, `entitlement_model`, `legal_reference`, `default_days`, `default_unit`, `is_paid`, `is_confidential`, `statutory_protected`, `status`)
SELECT 1, 'Vacation Leave', 'VL', 'Annual rest and personal vacation leave', 'Company', 'CompanyAnnual', 'annual', 'Company Policy (Satisfies Labor Code Art. 95 SIL equivalent when >= 5 paid days)', 15, 'working_days', 1, 0, 0, 'Active'
WHERE NOT EXISTS (SELECT 1 FROM `leave_types` WHERE `id` = 1 OR `leave_code` = 'VL' OR `leave_name` = 'Vacation Leave');

INSERT INTO `leave_types` (`id`, `leave_name`, `leave_code`, `description`, `classification`, `benefit_category`, `entitlement_model`, `legal_reference`, `default_days`, `default_unit`, `is_paid`, `is_confidential`, `statutory_protected`, `status`)
SELECT 2, 'Sick Leave', 'SL', 'Medical rest due to illness, injury, or clinical consult', 'Company', 'CompanyAnnual', 'annual', 'Company Policy', 10, 'working_days', 1, 0, 0, 'Active'
WHERE NOT EXISTS (SELECT 1 FROM `leave_types` WHERE `id` = 2 OR `leave_code` = 'SL' OR `leave_name` = 'Sick Leave');

INSERT INTO `leave_types` (`id`, `leave_name`, `leave_code`, `description`, `classification`, `benefit_category`, `entitlement_model`, `legal_reference`, `default_days`, `default_unit`, `is_paid`, `is_confidential`, `statutory_protected`, `status`)
SELECT 3, 'Emergency Leave', 'EL', 'Unplanned personal emergencies or urgent family matters', 'Company', 'CompanyAnnual', 'annual', 'Company Policy (Distinct from RA 8972/11861 Solo Parent Leave)', 5, 'working_days', 1, 0, 0, 'Active'
WHERE NOT EXISTS (SELECT 1 FROM `leave_types` WHERE `id` = 3 OR `leave_code` = 'EL' OR `leave_name` = 'Emergency Leave');

INSERT INTO `leave_types` (`id`, `leave_name`, `leave_code`, `description`, `classification`, `benefit_category`, `entitlement_model`, `legal_reference`, `default_days`, `default_unit`, `is_paid`, `is_confidential`, `statutory_protected`, `status`)
SELECT 4, 'Maternity Leave', 'MATERNITY', 'Statutory expanded maternal leave (105d live birth, 120d solo parent, 60d miscarriage/ETP)', 'Statutory', 'StatutoryEvent', 'event_based', 'RA 11210 (Expanded Maternity Leave Law)', 105, 'calendar_days', 1, 0, 1, 'Active'
WHERE NOT EXISTS (SELECT 1 FROM `leave_types` WHERE `id` = 4 OR `leave_code` = 'MATERNITY' OR `leave_name` = 'Maternity Leave');

INSERT INTO `leave_types` (`id`, `leave_name`, `leave_code`, `description`, `classification`, `benefit_category`, `entitlement_model`, `legal_reference`, `default_days`, `default_unit`, `is_paid`, `is_confidential`, `statutory_protected`, `status`)
SELECT 5, 'Paternity Leave', 'PATERNITY', 'Statutory paternal leave for up to first 4 deliveries of lawful spouse', 'Statutory', 'StatutoryEvent', 'event_based', 'RA 8187 (Paternity Leave Act, First 4 Deliveries of Lawful Spouse)', 7, 'working_days', 1, 0, 1, 'Active'
WHERE NOT EXISTS (SELECT 1 FROM `leave_types` WHERE `id` = 5 OR `leave_code` = 'PATERNITY' OR `leave_name` = 'Paternity Leave');

INSERT INTO `leave_types` (`leave_name`, `leave_code`, `description`, `classification`, `benefit_category`, `entitlement_model`, `legal_reference`, `default_days`, `default_unit`, `is_paid`, `is_confidential`, `statutory_protected`, `status`)
SELECT 'Service Incentive Leave', 'SIL', 'Five (5) paid working days after at least 1 year of service under Article 95 of the Labor Code (unless satisfied by equivalent paid vacation leave).', 'Statutory', 'StatutoryAnnual', 'annual', 'Labor Code Article 95', 5, 'working_days', 1, 0, 1, 'Active'
WHERE NOT EXISTS (SELECT 1 FROM `leave_types` WHERE `leave_code` = 'SIL' OR `leave_name` = 'Service Incentive Leave');

INSERT INTO `leave_types` (`leave_name`, `leave_code`, `description`, `classification`, `benefit_category`, `entitlement_model`, `legal_reference`, `default_days`, `default_unit`, `is_paid`, `is_confidential`, `statutory_protected`, `status`)
SELECT 'Parental Leave for Solo Parents', 'SOLO_PARENT', 'Up to seven (7) paid working days per year for qualified Solo Parents with at least 6 months of service and a valid Solo Parent ID Card (SPIC).', 'Statutory', 'StatutoryAnnual', 'annual', 'RA 8972 as amended by RA 11861', 7, 'working_days', 1, 0, 1, 'Active'
WHERE NOT EXISTS (SELECT 1 FROM `leave_types` WHERE `leave_code` = 'SOLO_PARENT' OR `leave_name` = 'Parental Leave for Solo Parents');

INSERT INTO `leave_types` (`leave_name`, `leave_code`, `description`, `classification`, `benefit_category`, `entitlement_model`, `legal_reference`, `default_days`, `default_unit`, `is_paid`, `is_confidential`, `statutory_protected`, `status`)
SELECT 'Special Leave for Women', 'SLW', 'Up to two (2) months (60 calendar days) full pay following surgery caused by gynecological disorders for female employees with at least 6 months aggregate service in the last 12 months.', 'Statutory', 'StatutoryEvent', 'event_based', 'RA 9710 Section 18 (Magna Carta of Women)', 60, 'calendar_days', 1, 1, 1, 'Active'
WHERE NOT EXISTS (SELECT 1 FROM `leave_types` WHERE `leave_code` = 'SLW' OR `leave_name` = 'Special Leave for Women');

INSERT INTO `leave_types` (`leave_name`, `leave_code`, `description`, `classification`, `benefit_category`, `entitlement_model`, `legal_reference`, `default_days`, `default_unit`, `is_paid`, `is_confidential`, `statutory_protected`, `status`)
SELECT 'VAWC Leave', 'VAWC', 'Up to ten (10) paid working days (extendable by protection order) for victims of violence against women and their children under strict confidentiality.', 'Statutory', 'StatutoryEvent', 'event_based', 'RA 9262 Section 43 (Anti-VAWC Act)', 10, 'working_days', 1, 1, 1, 'Active'
WHERE NOT EXISTS (SELECT 1 FROM `leave_types` WHERE `leave_code` = 'VAWC' OR `leave_name` = 'VAWC Leave');

INSERT INTO `leave_types` (`leave_name`, `leave_code`, `description`, `classification`, `benefit_category`, `entitlement_model`, `legal_reference`, `default_days`, `default_unit`, `is_paid`, `is_confidential`, `statutory_protected`, `status`)
SELECT 'Bereavement Leave', 'BEREAVEMENT', 'Compassionate leave upon the death of an immediate family member per company policy.', 'Company', 'CompanyEvent', 'event_based', 'Company Bereavement Policy', 3, 'working_days', 1, 0, 0, 'Active'
WHERE NOT EXISTS (SELECT 1 FROM `leave_types` WHERE `leave_code` = 'BEREAVEMENT' OR `leave_name` = 'Bereavement Leave');

INSERT INTO `leave_types` (`leave_name`, `leave_code`, `description`, `classification`, `benefit_category`, `entitlement_model`, `legal_reference`, `default_days`, `default_unit`, `is_paid`, `is_confidential`, `statutory_protected`, `status`)
SELECT 'Leave Without Pay', 'LWOP', 'Unpaid authorized absence tracked separately without deducting paid leave credits.', 'Company', 'Unpaid', 'unpaid', 'Company Unpaid Leave Policy', 0, 'working_days', 0, 0, 0, 'Active'
WHERE NOT EXISTS (SELECT 1 FROM `leave_types` WHERE `leave_code` = 'LWOP' OR `leave_name` = 'Leave Without Pay');

-- -----------------------------------------------------------------------------
-- 12. Seed V1 Active Policy Versions for all canonical leave types
-- -----------------------------------------------------------------------------
INSERT INTO `leave_policy_versions` (
    `leave_type_id`, `version_number`, `policy_code`, `effective_from`, `approval_status`,
    `classification`, `entitlement_model`, `benefit_tier`, `sector_scope`, `legal_reference`,
    `entitlement_days`, `entitlement_unit`, `is_paid`, `allocation_frequency`,
    `carry_over_allowed`, `max_carry_over_days`, `cash_convertible`, `cash_conversion_max_days`,
    `min_service_months`, `service_window_months`, `counts_as_sil_equivalent`, `stack_with_equivalent`,
    `max_events_lifetime`, `max_extension_days`, `requires_documentation`, `document_requirements`,
    `requires_qualification`, `qualification_type`, `is_confidential`, `rules_json`, `change_notes`
)
SELECT
    lt.id,
    1,
    CONCAT(COALESCE(lt.leave_code, CONCAT('LT', lt.id)), '-V1'),
    '2025-01-01',
    'Active',
    lt.classification,
    lt.entitlement_model,
    CASE
        WHEN lt.leave_code IN ('SIL', 'SOLO_PARENT', 'MATERNITY', 'PATERNITY', 'SLW', 'VAWC') THEN 'statutory_minimum'
        WHEN lt.entitlement_model = 'unpaid' THEN 'unpaid'
        ELSE 'company_benefit'
    END,
    'all',
    lt.legal_reference,
    lt.default_days,
    lt.default_unit,
    lt.is_paid,
    CASE
        WHEN lt.entitlement_model = 'event_based' THEN 'per_event'
        WHEN lt.entitlement_model = 'unpaid' THEN 'unlimited_unpaid'
        ELSE 'annual'
    END,
    0,
    0.00,
    CASE WHEN lt.leave_code = 'SIL' THEN 1 ELSE 0 END,
    CASE WHEN lt.leave_code = 'SIL' THEN 5.00 ELSE 0.00 END,
    CASE
        WHEN lt.leave_code = 'SIL' THEN 12
        WHEN lt.leave_code IN ('SOLO_PARENT', 'SLW') THEN 6
        ELSE 0
    END,
    CASE WHEN lt.leave_code = 'SLW' THEN 12 ELSE NULL END,
    CASE WHEN lt.leave_code = 'VL' THEN 1 ELSE 0 END,
    0,
    CASE WHEN lt.leave_code = 'PATERNITY' THEN 4 ELSE NULL END,
    CASE
        WHEN lt.leave_code = 'MATERNITY' THEN 30.00
        WHEN lt.leave_code = 'VAWC' THEN 30.00
        ELSE 0.00
    END,
    CASE WHEN lt.leave_code IN ('SOLO_PARENT', 'MATERNITY', 'PATERNITY', 'SLW', 'VAWC', 'BEREAVEMENT') THEN 1 ELSE 0 END,
    CASE lt.leave_code
        WHEN 'SIL' THEN 'No supporting document required beyond employment service verification.'
        WHEN 'SOLO_PARENT' THEN 'Valid DSWD/LGU Solo Parent Identification Card (SPIC) verified by HR.'
        WHEN 'MATERNITY' THEN 'Obstetric medical certificate / ultrasound / birth certificate or miscarriage clinical certification.'
        WHEN 'PATERNITY' THEN 'Marriage certificate / cohabitation proof and notification of spouse pregnancy/delivery.'
        WHEN 'SLW' THEN 'Certified gynecological surgeon medical certificate indicating surgery and recommended recuperation.'
        WHEN 'VAWC' THEN 'Barangay Protection Order (BPO), Temporary/Permanent Protection Order (TPO/PPO), or prosecutor/clerk/medical certification.'
        WHEN 'BEREAVEMENT' THEN 'Death certificate or official notice establishing immediate family relationship.'
        WHEN 'SL' THEN 'Medical certificate recommended for absences exceeding 2 consecutive days per company policy.'
        ELSE 'Optional supporting documentation.'
    END,
    CASE WHEN lt.leave_code IN ('SOLO_PARENT', 'PATERNITY', 'SIL') THEN 1 ELSE 0 END,
    CASE lt.leave_code
        WHEN 'SOLO_PARENT' THEN 'solo_parent_spic'
        WHEN 'PATERNITY' THEN 'paternity_marriage'
        WHEN 'SIL' THEN 'sil_exclusion'
        ELSE NULL
    END,
    lt.is_confidential,
    CASE lt.leave_code
        WHEN 'MATERNITY' THEN JSON_OBJECT('live_childbirth_days', 105, 'solo_parent_bonus_days', 15, 'miscarriage_days', 60, 'unpaid_extension_max_days', 30, 'father_transfer_max_days', 7, 'allow_cross_year', true)
        WHEN 'SOLO_PARENT' THEN JSON_OBJECT('min_service_months', 6, 'require_valid_spic', true, 'allow_carry_over', false, 'distinct_from_emergency', true)
        WHEN 'SIL' THEN JSON_OBJECT('min_service_months', 12, 'credit_against_vl_gte_days', 5, 'supports_annual_conversion', true, 'supports_separation_conversion', true)
        WHEN 'PATERNITY' THEN JSON_OBJECT('max_deliveries_lifetime', 4, 'require_lawful_spouse', true, 'prevent_duplicate_delivery', true, 'separate_from_ra11210_transfer', true)
        WHEN 'SLW' THEN JSON_OBJECT('max_days', 60, 'min_aggregate_service_months', 6, 'service_window_months', 12, 'mask_medical_diagnosis', true)
        WHEN 'VAWC' THEN JSON_OBJECT('base_days', 10, 'allow_protection_order_extension', true, 'strict_confidentiality', true)
        WHEN 'BEREAVEMENT' THEN JSON_OBJECT('allowed_relationships', JSON_ARRAY('Spouse', 'Child', 'Parent', 'Sibling', 'Grandparent', 'Parent-in-Law'))
        ELSE NULL
    END,
    'Initial canonical policy baseline (2026 Philippine Statutory & Company Leave Enhancement)'
FROM `leave_types` lt
WHERE NOT EXISTS (
    SELECT 1 FROM `leave_policy_versions` lpv
    WHERE lpv.leave_type_id = lt.id AND lpv.version_number = 1
);

-- Link active_policy_version_id on leave_types where unset
UPDATE `leave_types` lt
INNER JOIN `leave_policy_versions` lpv
    ON lpv.leave_type_id = lt.id AND lpv.approval_status = 'Active'
SET lt.active_policy_version_id = lpv.id
WHERE lt.active_policy_version_id IS NULL;

-- Backfill policy_version_id on existing leave_balances
UPDATE `leave_balances` lb
INNER JOIN `leave_types` lt ON lt.id = lb.leave_type_id
SET lb.policy_version_id = lt.active_policy_version_id
WHERE lb.policy_version_id IS NULL AND lt.active_policy_version_id IS NOT NULL;

-- Backfill policy_version_id and entitlement_unit/is_paid/is_confidential on existing leave_requests
UPDATE `leave_requests` lr
INNER JOIN `leave_types` lt ON lt.id = lr.leave_type_id
SET lr.policy_version_id = COALESCE(lr.policy_version_id, lt.active_policy_version_id),
    lr.entitlement_unit = lt.default_unit,
    lr.is_paid = lt.is_paid,
    lr.is_confidential = lt.is_confidential
WHERE lr.policy_version_id IS NULL;

-- -----------------------------------------------------------------------------
-- 13. Backfill Immutable Ledger entries for existing leave_balances
-- -----------------------------------------------------------------------------
INSERT INTO `leave_balance_transactions` (
    `employee_id`, `leave_type_id`, `leave_balance_id`, `policy_version_id`,
    `balance_year`, `transaction_type`, `days_delta`, `balance_before`, `balance_after`,
    `conversion_scope`, `remarks`, `is_confidential`
)
SELECT
    lb.employee_id,
    lb.leave_type_id,
    lb.id,
    lb.policy_version_id,
    lb.year,
    'initial_allocation',
    lb.allocated_days,
    0.00,
    lb.allocated_days,
    'none',
    CONCAT('Initial annual allocation backfill for year ', lb.year),
    0
FROM `leave_balances` lb
WHERE NOT EXISTS (
    SELECT 1 FROM `leave_balance_transactions` lbt
    WHERE lbt.leave_balance_id = lb.id AND lbt.transaction_type = 'initial_allocation'
);

INSERT INTO `leave_balance_transactions` (
    `employee_id`, `leave_type_id`, `leave_balance_id`, `policy_version_id`,
    `balance_year`, `transaction_type`, `days_delta`, `balance_before`, `balance_after`,
    `conversion_scope`, `remarks`, `is_confidential`
)
SELECT
    lb.employee_id,
    lb.leave_type_id,
    lb.id,
    lb.policy_version_id,
    lb.year,
    'approved_deduction',
    -1 * CAST(lb.used_days AS DECIMAL(6,2)),
    lb.allocated_days,
    lb.remaining_days,
    'none',
    CONCAT('Historical utilized balance reconciliation for year ', lb.year),
    0
FROM `leave_balances` lb
WHERE lb.used_days > 0
  AND NOT EXISTS (
    SELECT 1 FROM `leave_balance_transactions` lbt
    WHERE lbt.leave_balance_id = lb.id AND lbt.transaction_type = 'approved_deduction'
);

-- -----------------------------------------------------------------------------
-- 14. Seed new Leave Administration RBAC permissions (Admin & HR only)
-- -----------------------------------------------------------------------------
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`) VALUES
('Manage Leave Policies', 'leave_policies.manage', 'Leave Administration', 'Create, review, and activate versioned statutory and company leave policies'),
('Verify Leave Qualifications', 'leave_qualifications.verify', 'Leave Administration', 'Verify Solo Parent ID Cards (SPIC), paternity marital records, and SIL exclusions'),
('View Confidential Leave Records', 'leave_confidential.view', 'Leave Administration', 'View unmasked VAWC (RA 9262) and Special Leave for Women (RA 9710) details and documents')
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `module` = VALUES(`module`),
    `description` = VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
INNER JOIN `permissions` p ON p.slug IN ('leave_policies.manage', 'leave_qualifications.verify', 'leave_confidential.view')
WHERE r.slug IN ('admin', 'hr');
