# NEXORA HR — Database Architecture & Schema Specification

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Version:** 3.3  
**Status:** Authoritative Database Reference  
**Date:** November 2026 (Dynamic RBAC & Admin Governance Migration)  
**Document Owner:** Database Administrator & Backend Architecture Team  

---

## 1. Architectural Overview & Configuration

NEXORA HR utilizes **MySQL 8.0 Community Server** configured with the **InnoDB** storage engine as its single source of truth. The database is architected for strict ACID transactional compliance, row-level locking concurrency control, high referential integrity, and complete isolation between production and testing environments.

### Core Configuration Parameters
* **Storage Engine:** `InnoDB` strictly enforced across all 22 tables.
* **Character Set & Collation:** `utf8mb4` with `utf8mb4_unicode_ci` collation supporting full Unicode text and international employee names.
* **Transaction Isolation Level:** `READ COMMITTED` or `REPEATABLE READ` (default) with explicit pessimistic locks (`SELECT ... FOR UPDATE`) for critical balance transactions.
* **Timezone Synchronization:** UTC normalized timestamps with application-level display timezone conversion.

---

## 2. Authoritative 22-Table Schema Catalog

### 2.1 Organizational & Workforce Tables

#### 1. `departments`
Stores organizational department units.
```sql
CREATE TABLE `departments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `department_name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_departments_name` UNIQUE (`department_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 2. `positions`
Stores job positions and organizational titles.
```sql
CREATE TABLE `positions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `position_name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_positions_name` UNIQUE (`position_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3. `employees`
Core workforce registry detailing personal, organizational, and employment status attributes.
```sql
CREATE TABLE `employees` (
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
```

#### 4. `users`
System user credentials, authentication attributes, and application roles.
```sql
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NULL,
    `role_id` INT UNSIGNED NULL,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'hr', 'employee') NOT NULL DEFAULT 'employee',
    `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_users_username` UNIQUE (`username`),
    CONSTRAINT `uq_users_email` UNIQUE (`email`),
    CONSTRAINT `uq_users_employee` UNIQUE (`employee_id`),
    CONSTRAINT `fk_users_employee` FOREIGN KEY (`employee_id`) 
        REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`)
        REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_role_id` (`role_id`),
    INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2.2 Leave Management Engine Tables

#### 5. `leave_types`
Catalog of system leave categories and annual base allocations.
```sql
CREATE TABLE `leave_types` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `leave_name` VARCHAR(50) NOT NULL,
    `description` TEXT NULL,
    `default_days` INT UNSIGNED NOT NULL DEFAULT 0,
    `requires_attachment` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_leave_types_name` UNIQUE (`leave_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 6. `leave_balances`
Annual quota tracking per employee, leave type, and calendar year.
```sql
CREATE TABLE `leave_balances` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `leave_type_id` INT UNSIGNED NOT NULL,
    `year` SMALLINT UNSIGNED NOT NULL,
    `allocated_days` DECIMAL(4,1) NOT NULL DEFAULT 0.0,
    `used_days` DECIMAL(4,1) NOT NULL DEFAULT 0.0,
    `remaining_days` DECIMAL(4,1) NOT NULL DEFAULT 0.0,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_balances_emp_type_year` UNIQUE (`employee_id`, `leave_type_id`, `year`),
    CONSTRAINT `fk_balances_employee` FOREIGN KEY (`employee_id`) 
        REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_balances_leave_type` FOREIGN KEY (`leave_type_id`) 
        REFERENCES `leave_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_balances_lookup` (`employee_id`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 7. `leave_requests`
Transactional leave applications submitted by employees.
```sql
CREATE TABLE `leave_requests` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `leave_type_id` INT UNSIGNED NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `number_of_days` DECIMAL(4,1) NOT NULL,
    `reason` TEXT NOT NULL,
    `status` ENUM('Pending', 'Approved', 'Rejected', 'Cancelled') NOT NULL DEFAULT 'Pending',
    `approved_by` INT UNSIGNED NULL,
    `approved_at` DATETIME NULL,
    `rejection_reason` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_requests_employee` FOREIGN KEY (`employee_id`) 
        REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_requests_leave_type` FOREIGN KEY (`leave_type_id`) 
        REFERENCES `leave_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_requests_approver` FOREIGN KEY (`approved_by`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_requests_status` (`status`),
    INDEX `idx_requests_emp_dates` (`employee_id`, `start_date`, `end_date`),
    INDEX `idx_requests_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2.3 Authentication, Security & Real-Time Tables

#### 8. `activity_logs`
Tamper-evident audit trail of all operational and security actions with before/after state diffs, categories, and correlation IDs.
```sql
CREATE TABLE `activity_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_id` VARCHAR(64) NULL UNIQUE,
    `user_id` INT UNSIGNED NULL,
    `actor_role` ENUM('admin', 'hr', 'employee') NULL,
    `action` VARCHAR(50) NOT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'general',
    `details` TEXT NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `status` ENUM('SUCCESS', 'FAILURE', 'WARNING') NOT NULL DEFAULT 'SUCCESS',
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `request_id` VARCHAR(64) NULL,
    `entity_type` VARCHAR(50) NULL,
    `entity_id` VARCHAR(50) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_logs_user_action` (`user_id`, `action`),
    INDEX `idx_logs_entity` (`entity_type`, `entity_id`),
    INDEX `idx_logs_created` (`created_at`),
    INDEX `idx_logs_event_id` (`event_id`),
    INDEX `idx_logs_category` (`category`),
    INDEX `idx_logs_status` (`status`),
    INDEX `idx_logs_actor_role` (`actor_role`),
    INDEX `idx_logs_request_id` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 9. `notifications`
In-app notification messages delivered to specific users.
```sql
CREATE TABLE `notifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `type` VARCHAR(30) NOT NULL DEFAULT 'info',
    `entity_type` VARCHAR(50) NULL,
    `entity_id` VARCHAR(50) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_notifications_user_read` (`user_id`, `is_read`),
    INDEX `idx_notifications_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 10. `email_otps`
Hashed one-time passwords for multi-factor authentication.
```sql
CREATE TABLE `email_otps` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `otp_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_email_otps_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_email_otps_user_expires` (`user_id`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 11. `realtime_tickets`
Single-use authentication tickets for the Ratchet WebSocket daemon.
```sql
CREATE TABLE `realtime_tickets` (
    `ticket` VARCHAR(64) PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `role` VARCHAR(30) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_realtime_tickets_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_realtime_tickets_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 12. `outbox_events`
Asynchronous Transactional Outbox event staging table.
```sql
CREATE TABLE `outbox_events` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_type` VARCHAR(100) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `status` ENUM('PENDING', 'PROCESSING', 'PUBLISHED', 'FAILED') NOT NULL DEFAULT 'PENDING',
    `retry_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed_at` DATETIME NULL,
    INDEX `idx_outbox_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 13. `user_totp`
RFC 6238 TOTP authenticator secret credentials.
```sql
CREATE TABLE `user_totp` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `secret` VARCHAR(255) NOT NULL,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_user_totp_user` UNIQUE (`user_id`),
    CONSTRAINT `fk_user_totp_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2.4 Role-Based Access Control (RBAC) Governance Tables

#### 14. `roles`
Stores core application roles (`admin`, `hr`, `employee`).
```sql
CREATE TABLE `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `slug` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NULL,
    `is_system` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_roles_slug` UNIQUE (`slug`),
    CONSTRAINT `uq_roles_name` UNIQUE (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 15. `permissions`
Granular application capability identifiers across 7 system modules.
```sql
CREATE TABLE `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `module` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_permissions_name` UNIQUE (`name`),
    INDEX `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 16. `role_permissions`
Many-to-many relationship mapping granular permissions to roles.
```sql
CREATE TABLE `role_permissions` (
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`)
        REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`)
        REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_role_permissions_permission` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 17. `permission_audit_logs`
Immutable audit log recording granular permission assignment and revocation history.
```sql
CREATE TABLE `permission_audit_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `actor_user_id` INT UNSIGNED NULL,
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `action` ENUM('granted', 'revoked') NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_permission_audit_actor` FOREIGN KEY (`actor_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_permission_audit_role` FOREIGN KEY (`role_id`)
        REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_permission_audit_permission` FOREIGN KEY (`permission_id`)
        REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_perm_audit_role` (`role_id`),
    INDEX `idx_perm_audit_actor` (`actor_user_id`),
    INDEX `idx_perm_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2.5 Background Queue & Employee Profile Self-Service Tables `[IMPLEMENTED]`

#### 18. `queue_jobs` & 19. `failed_jobs`
Database-backed asynchronous queue storage introduced by `2026_11_08_create_queue_tables.sql` (see `database/schema.sql` for full columns and indexes).

#### 20. `employee_emergency_contacts` `[IMPLEMENTED]`
Normalized emergency contacts linked per employee (`ON DELETE CASCADE`, self-service managed by the authenticated owner or HR).
```sql
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
```

#### 21. `employment_history` `[IMPLEMENTED]`
Chronological career milestone log (separate from `activity_logs` security audit events) recording hiring, promotions, department transfers, position changes, employment status changes, and employment type transitions.
```sql
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
```

#### 22. `profile_correction_requests` `[IMPLEMENTED]`
Structured approval queue for HR-controlled employee profile attributes (`first_name`, `middle_name`, `last_name`, `birth_date`, `gender`, `civil_status`, `email`, `department_id`, `position_id`, `employment_type`, `date_hired`), with row-level `FOR UPDATE` transactional review.
```sql
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
```

---

### 2.6 Philippine Statutory & Company Leave Management Tables `[IMPLEMENTED]`

Added by `database/migrations/2026_11_16_philippine_leave_management.sql` alongside extensions on existing tables:

* **`leave_types` extensions:** `leave_code` (`VARCHAR(30) UNIQUE`), `classification` (`ENUM('Statutory','Company')`), `benefit_category` (`ENUM('StatutoryAnnual','StatutoryEvent','CompanyAnnual','CompanyEvent','Unpaid')`), `entitlement_model` (`ENUM('annual','event_based','unpaid')`), `legal_reference` (`VARCHAR(191)`), `default_unit` (`ENUM('working_days','calendar_days')`), `is_paid` (`TINYINT(1)`), `is_confidential` (`TINYINT(1)`), `statutory_protected` (`TINYINT(1)`), and `active_policy_version_id` (`INT UNSIGNED NULL`).
* **`leave_balances` extensions:** `carried_over_days`, `converted_days`, `expired_days` (`DECIMAL(6,2)`), `policy_version_id` (`INT UNSIGNED NULL`), `sil_credited_from_vl` (`TINYINT(1)`), `eligibility_status` (`ENUM('Available','PendingVerification','CreditedViaVL','Ineligible','NotAllocated')`), and `notes` (`VARCHAR(255)`).
* **`leave_requests` extensions:** `policy_version_id` (`INT UNSIGNED NULL`), `event_entitlement_id` (`INT UNSIGNED NULL`), `entitlement_unit` (`ENUM('working_days','calendar_days')`), `is_paid` (`TINYINT(1)`), `is_confidential` (`TINYINT(1)`), `application_metadata_json` (`JSON`), `info_request_status` (`ENUM('None','Requested','Responded')`), `hr_info_request_note` (`TEXT`), `employee_info_response` (`TEXT`), `info_requested_by` (`INT UNSIGNED NULL`), `info_requested_at` (`DATETIME`), and `info_responded_at` (`DATETIME`).

#### 23. `leave_policy_versions` `[IMPLEMENTED]`
Versioned, non-retroactive rule snapshots linked per `leave_type_id` (`policy_version_id` pinned onto requests, balances, and events at filing/allocation time).
* Key columns: `id`, `leave_type_id`, `version_number`, `policy_code` (`VARCHAR(50)`), `effective_from`, `effective_to`, `approval_status` (`ENUM('Draft','PendingReview','Active','Superseded','Archived')`), `classification`, `entitlement_model`, `benefit_tier` (`ENUM('statutory_minimum','employer_enhanced','company_benefit','unpaid')`), `sector_scope` (`ENUM('all','private','public')`), `legal_reference`, `entitlement_days` (`DECIMAL(6,2)`), `entitlement_unit`, `is_paid`, `allocation_frequency` (`ENUM('annual','per_event','unlimited_unpaid')`), `carry_over_allowed`, `max_carry_over_days`, `cash_convertible`, `cash_conversion_max_days`, `min_service_months`, `service_window_months`, `counts_as_sil_equivalent`, `stack_with_equivalent`, `max_events_lifetime`, `max_extension_days`, `requires_documentation`, `document_requirements`, `requires_qualification`, `qualification_type`, `is_confidential`, `rules_json` (`JSON`), `change_notes`, `created_by`, `approved_by`, `approved_at`.

#### 24. `employee_leave_qualifications` `[IMPLEMENTED]`
HR-verified statutory eligibility records per employee.
* Key columns: `id`, `employee_id`, `qualification_type` (`ENUM('solo_parent_spic','paternity_marriage','sil_exclusion','maternity_eligibility','slw_eligibility','vawc_protection')`), `verification_status` (`ENUM('PendingVerification','Verified','Expired','Revoked','Rejected')`), `reference_no` (`VARCHAR(100)`), `valid_from`, `valid_to`, `sil_exempt_override` (`TINYINT(1)`), `exclusion_reason` (`VARCHAR(150)`), `supporting_notes`, `verified_by`, `verified_at`.

#### 25. `leave_event_entitlements` `[IMPLEMENTED]`
Event-scoped entitlement tracking for Maternity (RA 11210), Paternity (RA 8187, up to 4 deliveries), SLW (RA 9710), VAWC (RA 9262 + extensions), and Bereavement.
* Key columns: `id`, `employee_id`, `leave_type_id`, `policy_version_id`, `event_code` (`VARCHAR(40)`), `event_scenario` (`VARCHAR(60)`), `event_date`, `delivery_sequence` (`TINYINT UNSIGNED`), `spouse_name`, `relationship_type`, `entitled_days`, `transferred_out_days`, `unpaid_extension_days`, `used_days`, `pending_days`, `remaining_days` (`DECIMAL(6,2)`), `valid_from`, `valid_to`, `verification_status` (`ENUM('PendingVerification','Verified','Active','Completed','Expired','Cancelled')`), `is_confidential`, `metadata_json` (`JSON`), `created_by`, `verified_by`, `verified_at`.

#### 26. `leave_balance_transactions` `[IMPLEMENTED]`
Append-only ledger recording every balance and event mutation, including non-payroll SIL commutation references (`conversion_scope`, `conversion_reference`).
* Key columns: `id`, `employee_id`, `leave_type_id`, `leave_balance_id`, `leave_request_id`, `event_entitlement_id`, `policy_version_id`, `balance_year` (`SMALLINT UNSIGNED`), `transaction_type` (`ENUM('initial_allocation','annual_allocation','approved_deduction','balance_restoration','adjustment','carry_over','expiration','sil_conversion','event_allocation','unpaid_recorded')`), `days_delta`, `balance_before`, `balance_after`, `conversion_scope` (`ENUM('none','annual_unused','separation')`), `conversion_reference` (`VARCHAR(80)`), `remarks` (`VARCHAR(255)`), `is_confidential`, `actor_user_id`, `created_at`.

#### 27. `leave_request_documents` `[IMPLEMENTED]`
Metadata for protected supporting documents stored outside the web root with SHA-256 checksums and MIME verification.
```sql
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
```

#### 28. `leave_sensitive_access_logs` `[IMPLEMENTED]`
Immutable access audit trail for VAWC (RA 9262), SLW (RA 9710), protected document downloads, and statutory qualification verifications.
```sql
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
```

---

## 3. Concurrency Control & Row-Level Locking

Leave balance mutations execute inside atomic transactions using **pessimistic row-level locking**:

```php
$pdo->beginTransaction();
try {
    // 1. Lock leave request
    $stmtReq = $pdo->prepare("SELECT * FROM leave_requests WHERE id = :id FOR UPDATE");
    $stmtReq->execute(['id' => $requestId]);
    $request = $stmtReq->fetch(PDO::FETCH_ASSOC);

    // Validate request is still 'Pending'
    if ($request['status'] !== 'Pending') {
        throw new RuntimeException("Request has already been processed.");
    }

    // 2. Lock employee leave balance record
    $stmtBal = $pdo->prepare("
        SELECT * FROM leave_balances 
        WHERE employee_id = :emp_id AND leave_type_id = :type_id AND year = :year 
        FOR UPDATE
    ");
    $stmtBal->execute([
        'emp_id'  => $request['employee_id'],
        'type_id' => $request['leave_type_id'],
        'year'    => (int)date('Y', strtotime($request['start_date']))
    ]);
    $balance = $stmtBal->fetch(PDO::FETCH_ASSOC);

    // 3. Verify sufficient quota
    if ((float)$balance['remaining_days'] < (float)$request['number_of_days']) {
        throw new RuntimeException("Insufficient leave balance.");
    }

    // 4. Mutate status & balance atomically
    $pdo->prepare("UPDATE leave_requests SET status = 'Approved', approved_by = :approver, approved_at = NOW() WHERE id = :id")
        ->execute(['approver' => $currentUserId, 'id' => $requestId]);

    $pdo->prepare("UPDATE leave_balances SET used_days = used_days + :days, remaining_days = remaining_days - :days WHERE id = :id")
        ->execute(['days' => $request['number_of_days'], 'id' => $balance['id']]);

    // 5. Commit & release all locks
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

---

## 4. Migration Management & CLI Runner

Database migrations are tracked in the `migrations` table:
```sql
CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `migration` VARCHAR(255) NOT NULL UNIQUE,
    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

To run pending migrations:
```bash
php bin/migrate.php
```

All migrations must adhere to the following naming pattern:
`database/migrations/YYYY_MM_DD_descriptive_action_name.sql`

### 4.1 Migration History
* `database/migrations/2026_03_11_create_initial_schema.sql` — Initial 13-table schema definition.
* `database/migrations/2026_03_11_seed_leave_types.sql` — Default leave types seed (Vacation, Sick, Emergency).
* `database/migrations/2026_04_01_create_outbox_events.sql` — Outbox table for asynchronous WebSockets.
* `database/migrations/2026_04_15_create_user_totp.sql` — RFC 6238 TOTP table.
* `database/migrations/2026_11_06_add_admin_role_to_users.sql` — Modifies `users.role` ENUM to include `'admin'` (`ENUM('admin', 'hr', 'employee') NOT NULL DEFAULT 'employee'`). Zero table rewrite, non-destructive, and maintains default role as `'employee'`.
* `database/migrations/2026_11_08_create_rbac_tables.sql` — Creates `roles`, `permissions`, `role_permissions`, and `permission_audit_logs` tables; adds `role_id` foreign key to `users`; seeds 3 core system roles and 33 granular permissions.
* `database/migrations/2026_11_08_enhance_audit_trail_schema.sql` — Extends `activity_logs` with `event_id`, `actor_role`, `category`, `old_values`, `new_values`, `status`, and `request_id`.
* `database/migrations/2026_11_08_create_queue_tables.sql` — Creates `queue_jobs` and `failed_jobs` tables for concurrency-safe asynchronous background job processing.
* `database/migrations/2026_11_15_enhance_employee_profiles.sql` — Adds `civil_status`, `personal_email`, `employment_type`, and `profile_picture` (plus `idx_employees_employment_type`) to `employees`, and creates `employee_emergency_contacts`, `employment_history`, and `profile_correction_requests`.
* `database/migrations/2026_11_16_philippine_leave_management.sql` — Extends `leave_types`, `leave_balances`, and `leave_requests` for Philippine statutory and company leave rules; creates `leave_policy_versions`, `employee_leave_qualifications`, `leave_event_entitlements`, `leave_balance_transactions`, `leave_request_documents`, and `leave_sensitive_access_logs`; seeds canonical leave types/v1 policies, backfills ledger entries, and seeds `leave_policies.manage`, `leave_qualifications.verify`, and `leave_confidential.view`.


