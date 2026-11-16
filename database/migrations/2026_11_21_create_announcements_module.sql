-- =============================================================================
-- Migration: HR Announcements Management System Module & Granular RBAC
-- Purpose:
--   1. Create dedicated, normalized Announcements tables:
--      - `announcements`
--      - `announcement_targets`
--      - `announcement_reads` (with UNIQUE(`announcement_id`, `employee_id`))
--      - `announcement_attachments`
--   2. Register granular `announcements.*` permissions & apply least-privilege role defaults
--   3. Preserve ALL existing user accounts, employee records, departments, and support data
-- Idempotent: safe to execute multiple times via bin/migrate.php.
-- Date: 2026-11-21
-- =============================================================================

-- 1. Announcements Aggregate Table
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `content` TEXT NOT NULL,
    `category` VARCHAR(80) NOT NULL,
    `priority` ENUM('Normal', 'Important', 'Urgent') NOT NULL DEFAULT 'Normal',
    `status` ENUM('Draft', 'Scheduled', 'Published', 'Archived', 'Expired') NOT NULL DEFAULT 'Draft',
    `author_id` INT UNSIGNED NOT NULL,
    `published_at` DATETIME NULL DEFAULT NULL,
    `scheduled_at` DATETIME NULL DEFAULT NULL,
    `expires_at` DATETIME NULL DEFAULT NULL,
    `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `archived_at` DATETIME NULL DEFAULT NULL,
    CONSTRAINT `fk_ann_author` FOREIGN KEY (`author_id`)
        REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_ann_status_sched` (`status`, `scheduled_at`),
    INDEX `idx_ann_status_exp` (`status`, `expires_at`),
    INDEX `idx_ann_feed_order` (`status`, `is_pinned`, `priority`, `published_at`),
    INDEX `idx_ann_author_status` (`author_id`, `status`, `created_at`),
    INDEX `idx_ann_category_status` (`category`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Announcement Audience Targets Table
CREATE TABLE IF NOT EXISTS `announcement_targets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `announcement_id` INT UNSIGNED NOT NULL,
    `target_type` ENUM('all', 'department') NOT NULL DEFAULT 'all',
    `department_id` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_annt_announcement` FOREIGN KEY (`announcement_id`)
        REFERENCES `announcements` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_annt_department` FOREIGN KEY (`department_id`)
        REFERENCES `departments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_annt_ann_type_dept` (`announcement_id`, `target_type`, `department_id`),
    INDEX `idx_annt_dept` (`target_type`, `department_id`, `announcement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Announcement Read Receipts Table
CREATE TABLE IF NOT EXISTS `announcement_reads` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `announcement_id` INT UNSIGNED NOT NULL,
    `employee_id` INT UNSIGNED NOT NULL,
    `read_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_ann_reads_ann_emp` UNIQUE (`announcement_id`, `employee_id`),
    CONSTRAINT `fk_annr_announcement` FOREIGN KEY (`announcement_id`)
        REFERENCES `announcements` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_annr_employee` FOREIGN KEY (`employee_id`)
        REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_annr_emp` (`employee_id`, `announcement_id`),
    INDEX `idx_annr_ann_read_at` (`announcement_id`, `read_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Announcement Attachments Table (Private Non-Public Storage)
CREATE TABLE IF NOT EXISTS `announcement_attachments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `announcement_id` INT UNSIGNED NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `stored_filename` VARCHAR(255) NOT NULL,
    `storage_relative_path` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size` INT UNSIGNED NOT NULL,
    `sha256_checksum` CHAR(64) NOT NULL DEFAULT '',
    `uploaded_by` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_anna_announcement` FOREIGN KEY (`announcement_id`)
        REFERENCES `announcements` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_anna_uploader` FOREIGN KEY (`uploaded_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_anna_ann` (`announcement_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Register Granular Announcements Permissions (3-Role Least-Privilege Matrix)
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`) VALUES
('View Announcements Management', 'announcements.view', 'Announcements Management', 'Access the HR Announcements Management dashboard and inspect organizational announcements', NOW()),
('Create Announcements & Drafts', 'announcements.create', 'Announcements Management', 'Create announcements and save draft announcements', NOW()),
('Edit Own Announcements', 'announcements.edit_own', 'Announcements Management', 'Edit own draft, scheduled, or published announcements', NOW()),
('Publish Announcements', 'announcements.publish', 'Announcements Management', 'Publish announcements immediately to targeted employees', NOW()),
('Schedule Announcements', 'announcements.schedule', 'Announcements Management', 'Schedule or cancel scheduled publication for announcements', NOW()),
('Archive Announcements', 'announcements.archive', 'Announcements Management', 'Archive announcements to remove them from active feeds while preserving audit history', NOW()),
('Delete Draft Announcements', 'announcements.delete_draft', 'Announcements Management', 'Delete unpublished draft announcements', NOW()),
('View Own Announcement Statistics', 'announcements.stats_own', 'Announcements Management', 'View read statistics and targeted read receipts for own authored announcements', NOW()),
('Manage All HR Announcements', 'announcements.manage_all', 'Announcements Management', 'Modify, publish, schedule, or archive announcements created by other HR personnel', NOW()),
('View All Announcement Statistics', 'announcements.stats_all', 'Announcements Management', 'View read statistics and read receipts for announcements created by any HR author', NOW()),
('View Employee Announcement Feed', 'announcements.feed', 'Employee Self-Service', 'View targeted published announcements, record read receipts, and download attachments', NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `module` = VALUES(`module`), `description` = VALUES(`description`);

-- Grant default HR announcement management permissions to 'hr'
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
INNER JOIN `permissions` p ON p.slug IN (
    'announcements.view',
    'announcements.create',
    'announcements.edit_own',
    'announcements.publish',
    'announcements.schedule',
    'announcements.archive',
    'announcements.delete_draft',
    'announcements.stats_own'
)
WHERE r.slug = 'hr';

-- Grant Employee announcement feed permission to 'employee'
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
INNER JOIN `permissions` p ON p.slug IN (
    'announcements.feed'
)
WHERE r.slug = 'employee';
