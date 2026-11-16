-- =============================================================================
-- Migration: Employee Help & Support and Complaint Management Module
-- Purpose:
--   1. `support_ticket_sequences`: Atomic annual sequence generation (SUP-YYYY-NNNNN)
--   2. `support_tickets`: General support requests & confidential workplace complaints
--   3. `support_ticket_handlers`: User-scoped designated confidential complaint handlers
--   4. `support_messages`: Chronological threaded messages & isolated internal HR notes
--   5. `support_attachments`: Non-public attachment metadata & SHA-256 checksums
--   6. `support_status_history`: Auditable ticket lifecycle state transitions
--   7. `support_assignments`: Assignment, reassignment, and escalation history
--   8. `support_sensitive_access_logs`: Privacy audit log for confidential access & COI blocks
--   9. Idempotent RBAC permissions & default role assignments (3-role model)
-- Idempotent: safe to run multiple times.
-- Date: 2026-11-17
-- =============================================================================

-- 1. Atomic Yearly Sequence Table for Server-Generated Ticket References
CREATE TABLE IF NOT EXISTS `support_ticket_sequences` (
    `ticket_year` SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
    `last_sequence` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Support Tickets & Workplace Complaints Table
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

-- 3. Designated Confidential Complaint Handlers (Within existing HR / Admin roles)
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

-- 4. Threaded Conversation Messages & Isolated Internal Notes Table
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

-- 5. Secure Non-Public Support Attachments Table
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

-- 6. Immutable Support Status Transition History Table
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

-- 7. Support Assignment & Escalation Log Table
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

-- 8. Support Sensitive Access & Privacy Audit Log Table
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

-- 9. Register Granular Permissions for Help & Support (Strict 3-Role RBAC Compliant)
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`) VALUES
('View Own Support Requests', 'support.view_own', 'Help & Support', 'View personal support tickets, complaints, and non-internal replies', NOW()),
('Submit Support & Complaints', 'support.create', 'Help & Support', 'Submit general support requests and confidential workplace complaints', NOW()),
('Reply to Own Support Requests', 'support.reply_own', 'Help & Support', 'Reply to own tickets, upload attachments, and reopen within policy', NOW()),
('View HR Support Inbox', 'support.inbox_view', 'Help & Support', 'Access the HR Support Inbox for general employee support requests', NOW()),
('Respond to Support Requests', 'support.respond', 'Help & Support', 'Post employee-visible replies, information requests, and internal notes', NOW()),
('Manage Support Ticket Status', 'support.manage_status', 'Help & Support', 'Transition ticket workflow statuses, priorities, and resolution remarks', NOW()),
('Assign Support Handlers', 'support.assign', 'Help & Support', 'Assign or reassign support tickets and eligible complaints to HR handlers', NOW()),
('Handle Confidential Complaints', 'support.confidential_handle', 'Help & Support', 'Eligible (when explicitly designated) to view and handle confidential workplace complaints', NOW()),
('Manage Confidential Handlers', 'support.manage_handlers', 'Help & Support', 'Authorize or revoke designated HR handlers for confidential workplace complaints', NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `module` = VALUES(`module`), `description` = VALUES(`description`);

-- Grant all permissions to Admin role
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
INNER JOIN `permissions` p ON p.slug IN (
    'support.view_own',
    'support.create',
    'support.reply_own',
    'support.inbox_view',
    'support.respond',
    'support.manage_status',
    'support.assign',
    'support.confidential_handle',
    'support.manage_handlers'
)
WHERE r.slug = 'admin';

-- Grant standard HR Support Inbox permissions + confidential eligibility capability to HR role
-- Note: User-level designation in `support_ticket_handlers` is STILL required per-user
-- so not all HR accounts receive automatic access to confidential complaints.
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
INNER JOIN `permissions` p ON p.slug IN (
    'support.inbox_view',
    'support.respond',
    'support.manage_status',
    'support.assign',
    'support.confidential_handle'
)
WHERE r.slug = 'hr';

-- Grant self-service support permissions to Employee role
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
INNER JOIN `permissions` p ON p.slug IN (
    'support.view_own',
    'support.create',
    'support.reply_own'
)
WHERE r.slug = 'employee';

-- 10. Designate primary HR Director (`hr01`) as default authorized confidential handler
-- Intentionally excludes secondary HR accounts (`hr02`) so confidential boundaries
-- are enforced out of the box within the single `hr` role.
INSERT INTO `support_ticket_handlers` (`user_id`, `can_handle_confidential`, `is_active`, `designation_notes`, `created_at`)
SELECT u.id, 1, 1, 'Primary designated HR confidential complaint officer', NOW()
FROM `users` u
WHERE u.username = 'hr01' AND u.role = 'hr'
LIMIT 1
ON DUPLICATE KEY UPDATE `is_active` = VALUES(`is_active`);
