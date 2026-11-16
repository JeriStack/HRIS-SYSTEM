-- =============================================================================
-- Migration: HR-to-Admin Help & Support Module + Least-Privilege Admin RBAC
-- Purpose:
--   1. Create dedicated, normalized HR-to-Admin support tables:
--      - `hr_support_tickets`
--      - `hr_support_ticket_messages` (supports Admin-only internal notes)
--      - `hr_support_ticket_attachments` (supports Admin internal-only files)
--      - `hr_support_ticket_assignments`
--      - `hr_support_ticket_history`
--   2. Backfill missing `notifications.idx_notifications_entity_event` index if absent
--   3. Register granular `hr_support.*` permissions & apply least-privilege role defaults
--   4. Preserve ALL existing user accounts, Employee Support (`SUP-*`), and Public (`AUTH-*`) data
-- Idempotent: safe to execute multiple times via bin/migrate.php.
-- Date: 2026-11-20
-- =============================================================================

-- 1. Ensure notifications entity/event composite index exists on upgraded DBs
SET @idx_notif_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'notifications'
      AND INDEX_NAME = 'idx_notifications_entity_event'
);
SET @sql_notif_idx := IF(
    @idx_notif_exists = 0,
    'ALTER TABLE `notifications` ADD INDEX `idx_notifications_entity_event` (`entity_id`, `event_type`)',
    'SELECT 1'
);
PREPARE stmt_notif_idx FROM @sql_notif_idx;
EXECUTE stmt_notif_idx;
DEALLOCATE PREPARE stmt_notif_idx;

-- 2. HR-to-Admin Support Tickets Table
CREATE TABLE IF NOT EXISTS `hr_support_tickets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_ref` VARCHAR(32) NOT NULL COMMENT 'Unique non-guessable tracking reference (e.g. HR-SUP-2026-8F3K9C2M)',
    `submitted_by_user_id` INT UNSIGNED NOT NULL,
    `subject` VARCHAR(200) NOT NULL,
    `description` TEXT NOT NULL,
    `category` VARCHAR(80) NOT NULL,
    `priority` ENUM('Low', 'Medium', 'High', 'Critical') NOT NULL DEFAULT 'Medium',
    `status` ENUM('Submitted', 'Under Review', 'In Progress', 'Waiting for HR', 'Resolved', 'Closed') NOT NULL DEFAULT 'Submitted',
    `assigned_admin_id` INT UNSIGNED NULL DEFAULT NULL,
    `resolution_summary` TEXT NULL DEFAULT NULL,
    `reopen_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `last_activity_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_at` DATETIME NULL DEFAULT NULL,
    `closed_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_hst_admin_ticket_ref` UNIQUE (`ticket_ref`),
    CONSTRAINT `fk_hst_admin_submitted_by` FOREIGN KEY (`submitted_by_user_id`)
        REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_hst_admin_assigned_to` FOREIGN KEY (`assigned_admin_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_hst_submitter_status` (`submitted_by_user_id`, `status`, `last_activity_at`),
    INDEX `idx_hst_status_prio_activity` (`status`, `priority`, `last_activity_at`),
    INDEX `idx_hst_assigned_status` (`assigned_admin_id`, `status`),
    INDEX `idx_hst_category_status` (`category`, `status`),
    INDEX `idx_hst_created` (`created_at`),
    INDEX `idx_hst_resolved` (`resolved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. HR-to-Admin Ticket Conversation Messages & Isolated Internal Notes
CREATE TABLE IF NOT EXISTS `hr_support_ticket_messages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT UNSIGNED NOT NULL,
    `sender_user_id` INT UNSIGNED NULL DEFAULT NULL,
    `sender_role` ENUM('hr', 'admin', 'system') NOT NULL DEFAULT 'hr',
    `message_type` ENUM('initial_submission', 'hr_reply', 'admin_response', 'info_request', 'internal_note', 'reopen_request', 'resolution_confirmation', 'status_change') NOT NULL,
    `is_internal_note` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Admin-only confidential note; never returned to HR',
    `body` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_hstm_ticket` FOREIGN KEY (`ticket_id`)
        REFERENCES `hr_support_tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_hstm_sender` FOREIGN KEY (`sender_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_hstm_ticket_vis` (`ticket_id`, `is_internal_note`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. HR-to-Admin Ticket Attachments Table (Private Storage)
CREATE TABLE IF NOT EXISTS `hr_support_ticket_attachments` (
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
    `is_internal_only` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Attached to Admin-only internal note',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_hsta_ticket` FOREIGN KEY (`ticket_id`)
        REFERENCES `hr_support_tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_hsta_message` FOREIGN KEY (`message_id`)
        REFERENCES `hr_support_ticket_messages` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_hsta_uploader` FOREIGN KEY (`uploaded_by_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_hsta_ticket` (`ticket_id`),
    INDEX `idx_hsta_message` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. HR-to-Admin Ticket Assignment Audit Table
CREATE TABLE IF NOT EXISTS `hr_support_ticket_assignments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT UNSIGNED NOT NULL,
    `assigned_to_admin_id` INT UNSIGNED NULL DEFAULT NULL,
    `assigned_by_user_id` INT UNSIGNED NULL DEFAULT NULL,
    `action` ENUM('assigned', 'reassigned', 'unassigned') NOT NULL DEFAULT 'assigned',
    `notes` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_hsta_assign_ticket` FOREIGN KEY (`ticket_id`)
        REFERENCES `hr_support_tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_hsta_assign_admin` FOREIGN KEY (`assigned_to_admin_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_hsta_assign_actor` FOREIGN KEY (`assigned_by_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_hst_assign_ticket` (`ticket_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. HR-to-Admin Ticket Status & Priority Transition History
CREATE TABLE IF NOT EXISTS `hr_support_ticket_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT UNSIGNED NOT NULL,
    `event_kind` ENUM('created', 'status_changed', 'priority_changed', 'assigned', 'reopened', 'resolved', 'closed') NOT NULL DEFAULT 'status_changed',
    `from_status` VARCHAR(40) NULL DEFAULT NULL,
    `to_status` VARCHAR(40) NULL DEFAULT NULL,
    `from_priority` VARCHAR(20) NULL DEFAULT NULL,
    `to_priority` VARCHAR(20) NULL DEFAULT NULL,
    `changed_by_user_id` INT UNSIGNED NULL DEFAULT NULL,
    `actor_role` ENUM('hr', 'admin', 'system') NOT NULL DEFAULT 'hr',
    `remarks` TEXT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_hsth_ticket` FOREIGN KEY (`ticket_id`)
        REFERENCES `hr_support_tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_hsth_actor` FOREIGN KEY (`changed_by_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_hsth_ticket` (`ticket_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Register Granular HR-to-Admin Support Permissions (3-Role Least-Privilege Matrix)
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`) VALUES
('Submit HR Support Tickets', 'hr_support.submit', 'HR Help & Support', 'Submit technical and operational support tickets directly to Administrators', NOW()),
('View Own HR Support Tickets', 'hr_support.view_own', 'HR Help & Support', 'View own HR-to-Admin support tickets, public status history, and Admin replies', NOW()),
('Reply & Reopen Own HR Support Tickets', 'hr_support.reply_own', 'HR Help & Support', 'Send follow-up replies, attach files, confirm resolution, or request reopen on own HR tickets', NOW()),
('View Admin Support Inbox', 'hr_support.admin_inbox', 'HR Help & Support', 'Inspect all HR-submitted support tickets and support KPI summary metrics', NOW()),
('Manage HR Support Tickets', 'hr_support.admin_manage', 'HR Help & Support', 'Assign administrators, update priorities/statuses, reply to HR, resolve, close, and reopen tickets', NOW()),
('Manage Admin Internal Notes', 'hr_support.internal_notes', 'HR Help & Support', 'Create and read private Admin-only internal investigation notes and attachments', NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `module` = VALUES(`module`), `description` = VALUES(`description`);

-- Grant Admin-only HR support management permissions to 'admin'
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
INNER JOIN `permissions` p ON p.slug IN (
    'hr_support.admin_inbox',
    'hr_support.admin_manage',
    'hr_support.internal_notes'
)
WHERE r.slug = 'admin';

-- Grant HR-only submission & own-ticket management permissions to 'hr'
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
INNER JOIN `permissions` p ON p.slug IN (
    'hr_support.submit',
    'hr_support.view_own',
    'hr_support.reply_own'
)
WHERE r.slug = 'hr';

-- Enforce Admin least-privilege separation in DB role_permissions:
-- Remove confidential HR leave docs, HR write operations, Employee /support self-service,
-- ordinary Employee inbox triage, and HR-to-Admin submission from Admin default DB rows.
DELETE rp
FROM `role_permissions` rp
INNER JOIN `roles` r ON r.id = rp.role_id
INNER JOIN `permissions` p ON p.id = rp.permission_id
WHERE r.slug = 'admin'
  AND p.slug IN (
      'employees.create',
      'employees.update',
      'employees.deactivate',
      'departments.manage',
      'positions.manage',
      'leave.approve',
      'leave.reject',
      'leave.create',
      'leave.cancel',
      'leave_types.manage',
      'leave_balances.manage',
      'leave_policies.manage',
      'leave_qualifications.verify',
      'leave_confidential.view',
      'profile.view_own',
      'leave.view_own',
      'leave.file_own',
      'leave.cancel_own',
      'leave_balances.view_own',
      'support.view_own',
      'support.create',
      'support.reply_own',
      'support.inbox_view',
      'support.respond',
      'support.manage_status',
      'support.assign',
      'support.confidential_handle',
      'hr_support.submit',
      'hr_support.view_own',
      'hr_support.reply_own'
  );
