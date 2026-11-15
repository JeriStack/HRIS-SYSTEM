-- =============================================================================
-- Database Schema: Employee HRIS & Leave Management System
-- Technologies: MySQL 8.x / MariaDB 10.4+, InnoDB Storage Engine, utf8mb4
-- Documentation: docs/phase-3-database/database-design.md
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. Departments Table
-- Stores organizational functional units (e.g., Information Technology, Finance)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `department_name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_departments_name` UNIQUE (`department_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Positions Table
-- Stores distinct job classifications linked to a specific department
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `positions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `position_name` VARCHAR(100) NOT NULL,
    `department_id` INT UNSIGNED NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_positions_department` FOREIGN KEY (`department_id`) 
        REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `uq_positions_dept_pos` UNIQUE (`department_id`, `position_name`),
    INDEX `idx_positions_dept` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Employees Table
-- Core workforce registry holding personal, demographic, and job details
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employees` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_no` VARCHAR(30) NOT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `middle_name` VARCHAR(50) NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `personal_email` VARCHAR(100) NULL DEFAULT NULL,
    `phone` VARCHAR(25) NULL,
    `address` TEXT NULL,
    `birth_date` DATE NOT NULL,
    `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
    `civil_status` ENUM('Single', 'Married', 'Widowed', 'Separated', 'Divorced') NULL DEFAULT NULL,
    `department_id` INT UNSIGNED NOT NULL,
    `position_id` INT UNSIGNED NOT NULL,
    `date_hired` DATE NOT NULL,
    `employment_type` VARCHAR(50) NOT NULL DEFAULT 'Regular',
    `employment_status` ENUM('Active', 'Inactive', 'Terminated') NOT NULL DEFAULT 'Active',
    `profile_picture` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_employees_emp_no` UNIQUE (`employee_no`),
    CONSTRAINT `uq_employees_email` UNIQUE (`email`),
    CONSTRAINT `fk_employees_department` FOREIGN KEY (`department_id`) 
        REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_employees_position` FOREIGN KEY (`position_id`) 
        REFERENCES `positions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_employees_status` (`employment_status`),
    INDEX `idx_employees_employment_type` (`employment_type`),
    INDEX `idx_employees_dept` (`department_id`),
    INDEX `idx_employees_pos` (`position_id`),
    INDEX `idx_employees_name` (`last_name`, `first_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- -----------------------------------------------------------------------------
-- 3.5. Application Roles Table (RBAC)
-- System roles ('admin', 'hr', 'employee') and extensible organizational roles
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `slug` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NULL,
    `is_system` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_roles_slug` UNIQUE (`slug`),
    INDEX `idx_roles_system` (`is_system`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Users Table
-- System access credentials, password hashes, and assigned permission roles
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NULL,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'hr', 'employee') NOT NULL DEFAULT 'employee',
    `role_id` INT UNSIGNED NULL,
    `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_users_username` UNIQUE (`username`),
    CONSTRAINT `uq_users_email` UNIQUE (`email`),
    CONSTRAINT `uq_users_employee` UNIQUE (`employee_id`),
    CONSTRAINT `fk_users_employee` FOREIGN KEY (`employee_id`) 
        REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`)
        REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_role_id` (`role_id`),
    INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Leave Types Table
-- Configuration for allowable leave categories and default allocated quotas
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leave_types` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `leave_name` VARCHAR(100) NOT NULL,
    `leave_code` VARCHAR(30) NULL DEFAULT NULL,
    `description` TEXT NULL,
    `classification` ENUM('Statutory', 'Company') NOT NULL DEFAULT 'Company',
    `benefit_category` ENUM('StatutoryAnnual', 'StatutoryEvent', 'CompanyAnnual', 'CompanyEvent', 'Unpaid') NOT NULL DEFAULT 'CompanyAnnual',
    `entitlement_model` ENUM('annual', 'event_based', 'unpaid') NOT NULL DEFAULT 'annual',
    `legal_reference` VARCHAR(191) NULL DEFAULT NULL,
    `default_days` INT UNSIGNED NOT NULL DEFAULT 15,
    `default_unit` ENUM('working_days', 'calendar_days') NOT NULL DEFAULT 'working_days',
    `is_paid` TINYINT(1) NOT NULL DEFAULT 1,
    `is_confidential` TINYINT(1) NOT NULL DEFAULT 0,
    `statutory_protected` TINYINT(1) NOT NULL DEFAULT 0,
    `active_policy_version_id` INT UNSIGNED NULL DEFAULT NULL,
    `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_leave_types_name` UNIQUE (`leave_name`),
    CONSTRAINT `uq_leave_types_code` UNIQUE (`leave_code`),
    INDEX `idx_leave_types_model` (`classification`, `entitlement_model`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Leave Balances Table
-- Annual leave day quotas and consumption per employee per leave category
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leave_balances` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `leave_type_id` INT UNSIGNED NOT NULL,
    `year` INT UNSIGNED NOT NULL,
    `allocated_days` INT NOT NULL DEFAULT 0,
    `used_days` INT NOT NULL DEFAULT 0,
    `remaining_days` INT NOT NULL DEFAULT 0,
    `carried_over_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `converted_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `expired_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `policy_version_id` INT UNSIGNED NULL DEFAULT NULL,
    `sil_credited_from_vl` TINYINT(1) NOT NULL DEFAULT 0,
    `eligibility_status` ENUM('Available', 'PendingVerification', 'CreditedViaVL', 'Ineligible', 'NotAllocated') NOT NULL DEFAULT 'Available',
    `notes` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_leave_balances_employee` FOREIGN KEY (`employee_id`) 
        REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_leave_balances_type` FOREIGN KEY (`leave_type_id`) 
        REFERENCES `leave_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `uq_leave_balances_emp_type_year` UNIQUE (`employee_id`, `leave_type_id`, `year`),
    CONSTRAINT `chk_leave_balances_allocated` CHECK (`allocated_days` >= 0),
    CONSTRAINT `chk_leave_balances_used` CHECK (`used_days` >= 0),
    CONSTRAINT `chk_leave_balances_remaining` CHECK (`remaining_days` >= 0),
    INDEX `idx_leave_balances_emp_year` (`employee_id`, `year`),
    INDEX `idx_lb_policy_version` (`policy_version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Leave Requests Table
-- Lifecycle of employee leave filings, working/calendar day durations, and approvals
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leave_requests` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `leave_type_id` INT UNSIGNED NOT NULL,
    `policy_version_id` INT UNSIGNED NULL DEFAULT NULL,
    `event_entitlement_id` INT UNSIGNED NULL DEFAULT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `number_of_days` INT UNSIGNED NOT NULL,
    `entitlement_unit` ENUM('working_days', 'calendar_days') NOT NULL DEFAULT 'working_days',
    `is_paid` TINYINT(1) NOT NULL DEFAULT 1,
    `is_confidential` TINYINT(1) NOT NULL DEFAULT 0,
    `reason` TEXT NOT NULL,
    `application_metadata_json` JSON NULL DEFAULT NULL,
    `rejection_reason` TEXT NULL,
    `info_request_status` ENUM('None', 'Requested', 'Responded') NOT NULL DEFAULT 'None',
    `hr_info_request_note` TEXT NULL,
    `employee_info_response` TEXT NULL,
    `info_requested_by` INT UNSIGNED NULL DEFAULT NULL,
    `info_requested_at` DATETIME NULL DEFAULT NULL,
    `info_responded_at` DATETIME NULL DEFAULT NULL,
    `status` ENUM('Pending', 'Approved', 'Rejected', 'Cancelled') NOT NULL DEFAULT 'Pending',
    `approved_by` INT UNSIGNED NULL,
    `approved_at` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_leave_requests_employee` FOREIGN KEY (`employee_id`) 
        REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_leave_requests_type` FOREIGN KEY (`leave_type_id`) 
        REFERENCES `leave_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_leave_requests_approver` FOREIGN KEY (`approved_by`) 
        REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_lr_policy_version` FOREIGN KEY (`policy_version_id`)
        REFERENCES `leave_policy_versions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_lr_event_entitlement` FOREIGN KEY (`event_entitlement_id`)
        REFERENCES `leave_event_entitlements` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_leave_requests_dates` CHECK (`start_date` <= `end_date`),
    CONSTRAINT `chk_leave_requests_days` CHECK (`number_of_days` > 0),
    INDEX `idx_leave_requests_status` (`status`),
    INDEX `idx_leave_requests_emp_status` (`employee_id`, `status`),
    INDEX `idx_leave_requests_dates` (`start_date`, `end_date`),
    INDEX `idx_lr_policy_version` (`policy_version_id`),
    INDEX `idx_lr_event_entitlement` (`event_entitlement_id`),
    INDEX `idx_lr_confidential` (`is_confidential`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Activity Logs Table
-- Immutable audit log recording administrative and employee state changes
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_id` VARCHAR(64) NULL,
    `user_id` INT UNSIGNED NULL,
    `actor_role` VARCHAR(30) NULL,
    `action` VARCHAR(50) NOT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'general',
    `description` TEXT NOT NULL,
    `entity_type` VARCHAR(50) NULL,
    `entity_id` VARCHAR(50) NULL,
    `old_values` LONGTEXT NULL,
    `new_values` LONGTEXT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'SUCCESS',
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `request_id` VARCHAR(64) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_activity_logs_action` (`action`),
    INDEX `idx_activity_logs_category` (`category`),
    INDEX `idx_activity_logs_status` (`status`),
    INDEX `idx_activity_logs_event_id` (`event_id`),
    INDEX `idx_activity_logs_entity` (`entity_type`, `entity_id`),
    INDEX `idx_activity_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. (Approved Schema Extension) Notifications Table
-- Internal messaging queue for workflow updates (Section 23 of Project Plan)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info', 'success', 'warning', 'error') NOT NULL DEFAULT 'info',
    `event_type` VARCHAR(64) NULL DEFAULT NULL,
    `entity_id` INT UNSIGNED NULL DEFAULT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_notifications_user_read` (`user_id`, `is_read`),
    -- Leave approve/reject idempotency lookups (WHERE entity_id = ? AND event_type = ?).
    -- See database/migrations/2026_11_02_add_notifications_entity_event_index.sql
    INDEX `idx_notifications_entity_event` (`entity_id`, `event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. Email OTPs Table
-- Secure persistent storage for hashed Email One-Time Passwords (OTP)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_otps` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `otp_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `consumed_at` DATETIME NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    CONSTRAINT `fk_email_otps_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_email_otps_user_active` (`user_id`, `consumed_at`, `expires_at`),
    INDEX `idx_email_otps_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. Real-time (WebSocket) Connection Tickets
-- Short-lived, single-use tickets issued by the authenticated HTTP application.
-- Only SHA-256 hashes are stored. See database/migrations/2026_10_23_*.sql
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 12. Transactional Outbox Events
-- Durable outbox pattern for transactional event persistence & async dispatch.
-- See database/migrations/2026_10_31_create_outbox_events_table.sql
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 13. User TOTP Two-Factor Authentication
-- RFC 6238 time-based one-time password secrets and single-use recovery codes.
-- See database/migrations/2026_10_31_create_user_totp_table.sql
-- -----------------------------------------------------------------------------
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
    -- Matches the applied migration (2026_10_31_create_user_totp_table.sql): no ON UPDATE rule
    CONSTRAINT `fk_user_totp_users` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 14. RBAC Permissions Table
-- Granular system capabilities grouped by operational domain
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `module` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_permissions_slug` UNIQUE (`slug`),
    INDEX `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 15. RBAC Role-Permissions Join Table
-- Many-to-many relationship mapping roles to specific granular permissions
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_role_permissions` UNIQUE (`role_id`, `permission_id`),
    INDEX `idx_role_permissions_role` (`role_id`),
    INDEX `idx_role_permissions_perm` (`permission_id`),
    CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`)
        REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_role_permissions_perm` FOREIGN KEY (`permission_id`)
        REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 16. Permission Audit Logs Table
-- Immutable history capturing all role-permission modifications
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permission_audit_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `actor_user_id` INT UNSIGNED NULL,
    `target_role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NULL,
    `action` VARCHAR(50) NOT NULL,
    `old_value` TEXT NULL,
    `new_value` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_perm_audit_actor` (`actor_user_id`),
    INDEX `idx_perm_audit_role` (`target_role_id`),
    INDEX `idx_perm_audit_created` (`created_at`),
    CONSTRAINT `fk_perm_audit_actor` FOREIGN KEY (`actor_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_perm_audit_role` FOREIGN KEY (`target_role_id`)
        REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_perm_audit_perm` FOREIGN KEY (`permission_id`)
        REFERENCES `permissions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 17. Asynchronous Queue Jobs Table
-- MySQL-backed job queue for background asynchronous processing
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 18. Failed Queue Jobs Table
-- Dead-letter store for permanently failed asynchronous jobs
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 19. Employee Emergency Contacts Table
-- Normalized emergency contacts per employee (self-service editable)
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 20. Employment History Table
-- Chronological career progression records distinct from security activity logs
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 21. Profile Correction Requests Table
-- Employee-initiated correction requests for HR-controlled profile attributes
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 22. Leave Policy Versions Table
-- Non-retroactive versioned rules for Philippine statutory and company leave
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
-- 23. Employee Leave Qualifications Table
-- Verified Solo Parent IDs (SPIC), marriage records, and statutory exclusions
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
-- 24. Leave Event Entitlements Table
-- Per-event leave instances (Maternity, Paternity deliveries, SLW, VAWC, Bereavement)
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
-- 25. Leave Balance Transactions Ledger Table
-- Append-only ledger tracking allocations, deductions, SIL conversions, & expiry
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
-- 26. Leave Request Documents Table
-- Secure non-public supporting document metadata with SHA-256 checksums
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
-- 27. Leave Sensitive Access Logs Table
-- Audit log of confidential leave (RA 9262 / RA 9710) and document access
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
-- 28. Support Ticket Sequences Table
-- Atomic annual sequence counter for server-generated SUP-YYYY-NNNNN ticket refs
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `support_ticket_sequences` (
    `ticket_year` SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
    `last_sequence` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 29. Support Tickets & Workplace Complaints Table
-- Tracks general support requests and confidential workplace grievances
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `support_tickets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_ref` VARCHAR(30) NOT NULL,
    `employee_id` INT UNSIGNED NOT NULL,
    `submitted_by_user_id` INT UNSIGNED NOT NULL,
    `request_type` ENUM('support', 'complaint') NOT NULL DEFAULT 'support',
    `category` VARCHAR(80) NOT NULL,
    `complaint_category` VARCHAR(80) NULL DEFAULT NULL,
    `is_confidential` TINYINT(1) NOT NULL DEFAULT 0,
    `subject_employee_id` INT UNSIGNED NULL DEFAULT NULL,
    `subject_reference` VARCHAR(150) NULL DEFAULT NULL,
    `subject` VARCHAR(200) NOT NULL,
    `description` TEXT NOT NULL,
    `incident_date` DATE NULL DEFAULT NULL,
    `category_metadata_json` JSON NULL DEFAULT NULL,
    `priority` ENUM('Low', 'Medium', 'High', 'Urgent') NOT NULL DEFAULT 'Medium',
    `status` ENUM('Submitted', 'Under Review', 'Waiting for Employee', 'Resolved', 'Closed') NOT NULL DEFAULT 'Submitted',
    `assigned_to_user_id` INT UNSIGNED NULL DEFAULT NULL,
    `resolution_remarks` TEXT NULL DEFAULT NULL,
    `escalated_flag` TINYINT(1) NOT NULL DEFAULT 0,
    `escalation_reason` VARCHAR(255) NULL DEFAULT NULL,
    `resolved_at` DATETIME NULL DEFAULT NULL,
    `closed_at` DATETIME NULL DEFAULT NULL,
    `last_activity_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_st_ticket_ref` UNIQUE (`ticket_ref`),
    CONSTRAINT `fk_st_employee` FOREIGN KEY (`employee_id`)
        REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_st_submitted_by` FOREIGN KEY (`submitted_by_user_id`)
        REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_st_subject_employee` FOREIGN KEY (`subject_employee_id`)
        REFERENCES `employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_st_assigned_to` FOREIGN KEY (`assigned_to_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_st_emp_status` (`employee_id`, `status`, `last_activity_at`),
    INDEX `idx_st_type_conf_status` (`request_type`, `is_confidential`, `status`),
    INDEX `idx_st_assigned_status` (`assigned_to_user_id`, `status`),
    INDEX `idx_st_subject_emp` (`subject_employee_id`),
    INDEX `idx_st_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 30. Support Confidential Handlers Registry Table
-- User-scoped authorization within HR/Admin roles for confidential complaints
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `support_ticket_handlers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `can_handle_confidential` TINYINT(1) NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `designation_notes` VARCHAR(255) NULL DEFAULT NULL,
    `granted_by_user_id` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_sth_user` UNIQUE (`user_id`),
    CONSTRAINT `fk_sth_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sth_granted_by` FOREIGN KEY (`granted_by_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_sth_active` (`is_active`, `can_handle_confidential`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 31. Support Messages Table
-- Chronological conversation history separating employee-visible & internal HR notes
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `support_messages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT UNSIGNED NOT NULL,
    `sender_user_id` INT UNSIGNED NULL DEFAULT NULL,
    `sender_role` ENUM('employee', 'hr', 'admin', 'system') NOT NULL DEFAULT 'employee',
    `message_type` ENUM('initial_submission', 'hr_response', 'employee_reply', 'info_request', 'internal_note', 'status_change') NOT NULL,
    `is_internal_note` TINYINT(1) NOT NULL DEFAULT 0,
    `body` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sm_ticket` FOREIGN KEY (`ticket_id`)
        REFERENCES `support_tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sm_sender` FOREIGN KEY (`sender_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_sm_ticket_vis` (`ticket_id`, `is_internal_note`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 32. Support Attachments Table
-- Non-public uploaded supporting documents linked to tickets and messages
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `support_attachments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT UNSIGNED NOT NULL,
    `message_id` INT UNSIGNED NULL DEFAULT NULL,
    `uploaded_by_user_id` INT UNSIGNED NULL DEFAULT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `stored_filename` VARCHAR(255) NOT NULL,
    `storage_relative_path` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size_bytes` INT UNSIGNED NOT NULL,
    `sha256_checksum` CHAR(64) NOT NULL,
    `is_confidential` TINYINT(1) NOT NULL DEFAULT 0,
    `is_internal_only` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sa_ticket` FOREIGN KEY (`ticket_id`)
        REFERENCES `support_tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sa_message` FOREIGN KEY (`message_id`)
        REFERENCES `support_messages` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sa_uploader` FOREIGN KEY (`uploaded_by_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_sa_ticket` (`ticket_id`),
    INDEX `idx_sa_message` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 33. Support Status History Table
-- Complete audit trail of ticket lifecycle transitions and remarks
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `support_status_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT UNSIGNED NOT NULL,
    `from_status` VARCHAR(40) NULL DEFAULT NULL,
    `to_status` VARCHAR(40) NOT NULL,
    `changed_by_user_id` INT UNSIGNED NULL DEFAULT NULL,
    `remarks` TEXT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ssh_ticket` FOREIGN KEY (`ticket_id`)
        REFERENCES `support_tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ssh_actor` FOREIGN KEY (`changed_by_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_ssh_ticket` (`ticket_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 34. Support Assignments Table
-- Assignment, reassignment, and COI/policy escalation events
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `support_assignments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT UNSIGNED NOT NULL,
    `assigned_to_user_id` INT UNSIGNED NULL DEFAULT NULL,
    `assigned_by_user_id` INT UNSIGNED NULL DEFAULT NULL,
    `action` ENUM('assigned', 'reassigned', 'unassigned', 'escalated') NOT NULL DEFAULT 'assigned',
    `notes` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sas_ticket` FOREIGN KEY (`ticket_id`)
        REFERENCES `support_tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sas_assignee` FOREIGN KEY (`assigned_to_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sas_actor` FOREIGN KEY (`assigned_by_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_sas_ticket` (`ticket_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 35. Support Sensitive Access Logs Table
-- Audit trail of confidential complaint access attempts, COI blocks, & downloads
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `support_sensitive_access_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT UNSIGNED NULL DEFAULT NULL,
    `attachment_id` INT UNSIGNED NULL DEFAULT NULL,
    `accessor_user_id` INT UNSIGNED NULL DEFAULT NULL,
    `action_type` ENUM('view_ticket', 'view_complaint', 'download_attachment', 'assign_handler', 'update_status', 'export_masked', 'coi_blocked') NOT NULL,
    `access_granted` TINYINT(1) NOT NULL DEFAULT 1,
    `reason_code` VARCHAR(80) NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ssal_ticket` FOREIGN KEY (`ticket_id`)
        REFERENCES `support_tickets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_ssal_attachment` FOREIGN KEY (`attachment_id`)
        REFERENCES `support_attachments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_ssal_accessor` FOREIGN KEY (`accessor_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_ssal_ticket` (`ticket_id`, `created_at`),
    INDEX `idx_ssal_accessor` (`accessor_user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

