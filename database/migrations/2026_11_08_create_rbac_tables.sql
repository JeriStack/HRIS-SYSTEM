-- =============================================================================
-- Migration: Dynamic Role-Based Access Control (RBAC) Architecture
-- Purpose:
--   1. Create `roles`: System roles ('admin', 'hr', 'employee') and extensible roles
--   2. Create `permissions`: Granular capability tokens grouped by operational module
--   3. Create `role_permissions`: Many-to-many relationship mapping roles to permissions
--   4. Create `permission_audit_logs`: Immutable audit trail for all permission modifications
--   5. Add `role_id` foreign key to `users` table while preserving existing `role` ENUM
--   6. Seed authoritative system roles, permissions, and initial default assignments
-- Idempotent: safe to run multiple times.
-- Date: 2026-11-08
-- =============================================================================

-- Step 1: Create `roles` table
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

-- Step 2: Seed System Roles
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`) VALUES
(1, 'Administrator', 'admin', 'Full system-wide administrative governance and security management', 1, NOW()),
(2, 'Human Resources', 'hr', 'Operational human resource and workforce operations management', 1, NOW()),
(3, 'Employee', 'employee', 'Employee personal self-service workspace and leave management', 1, NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- Step 3: Create `permissions` table
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

-- Step 4: Seed Authoritative Granular Permissions
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`) VALUES
-- User Administration
('View User Accounts', 'users.view', 'User Administration', 'View the user accounts directory and account metadata', NOW()),
('Create User Accounts', 'users.create', 'User Administration', 'Provision and create new user access accounts', NOW()),
('Update User Accounts', 'users.update', 'User Administration', 'Modify existing user credentials, emails, and profile links', NOW()),
('Deactivate User Accounts', 'users.deactivate', 'User Administration', 'Deactivate, reactivate, or lock user accounts', NOW()),
('View Roles & Permissions', 'roles.view', 'User Administration', 'View system roles and current capability assignments', NOW()),
('Manage Role Permissions', 'roles.manage', 'User Administration', 'Configure, grant, and revoke permissions for HR and Employee roles', NOW()),
('Admin Provisioning', 'admin.provision', 'User Administration', 'Inspect active administrators and bootstrap recovery guidance', NOW()),

-- Organization Management
('View Employee Directory', 'employees.view', 'Organization Management', 'View employee roster, departmental staff, and employment status', NOW()),
('Create Employee Records', 'employees.create', 'Organization Management', 'Onboard new personnel and create employee master records', NOW()),
('Update Employee Records', 'employees.update', 'Organization Management', 'Edit employee details, job titles, and departmental assignments', NOW()),
('Deactivate Employee Records', 'employees.deactivate', 'Organization Management', 'Soft-deactivate employee profiles upon resignation or termination', NOW()),
('View Departments', 'departments.view', 'Organization Management', 'Inspect departmental listings, codes, and staffing levels', NOW()),
('Manage Departments', 'departments.manage', 'Organization Management', 'Create, rename, and manage organizational departments', NOW()),
('View Job Positions', 'positions.view', 'Organization Management', 'Inspect organizational job titles and department assignments', NOW()),
('Manage Job Positions', 'positions.manage', 'Organization Management', 'Create, edit, and configure job position titles and requirements', NOW()),

-- Leave Administration
('View Leave Requests Queue', 'leave.view', 'Leave Administration', 'Inspect workforce leave filings, approval queue, and historical audits', NOW()),
('File Leave Requests', 'leave.create', 'Leave Administration', 'Submit leave requests into the review pipeline', NOW()),
('Approve Leave Requests', 'leave.approve', 'Leave Administration', 'Review and approve pending employee leave applications', NOW()),
('Reject Leave Requests', 'leave.reject', 'Leave Administration', 'Reject leave applications with mandatory auditable reasons', NOW()),
('Cancel Leave Requests', 'leave.cancel', 'Leave Administration', 'Cancel pending leave requests before processing', NOW()),
('Manage Leave Types', 'leave_types.manage', 'Leave Administration', 'Configure statutory leave classifications and default quotas', NOW()),
('View Leave Balances', 'leave_balances.view', 'Leave Administration', 'Inspect allocated, used, and remaining employee leave days', NOW()),
('Manage Leave Balances', 'leave_balances.manage', 'Leave Administration', 'Perform transaction-safe adjustments to leave quotas', NOW()),

-- Reports & Analytics
('View Reports & Analytics', 'reports.view', 'Reports & Analytics', 'View workforce headcount distributions and leave utilization reports', NOW()),
('Export Reports', 'reports.export', 'Reports & Analytics', 'Export reporting data to printable or downloadable formats', NOW()),

-- Security & Compliance
('View Activity Audit Logs', 'audit_logs.view', 'Security & Compliance', 'Search and inspect system-wide audit activity logs', NOW()),
('View Security Events', 'security_events.view', 'Security & Compliance', 'Monitor authentication attempts, OTP verifications, and lockout incidents', NOW()),
('View Authentication Settings', 'auth_settings.view', 'Security & Compliance', 'View session timeout limits, MFA enforcement, and password policies', NOW()),
('Manage Authentication Settings', 'auth_settings.manage', 'Security & Compliance', 'Configure authentication security controls and session duration', NOW()),

-- System Administration
('View General Settings', 'system_settings.view', 'System Administration', 'Inspect application configuration parameters and masked environment secrets', NOW()),
('Manage General Settings', 'system_settings.manage', 'System Administration', 'Update system-wide application settings and company profile', NOW()),
('View System Health', 'system_health.view', 'System Administration', 'Run genuine runtime diagnostic socket probes on DB, WS, and SMTP', NOW()),
('Manage Notification Settings', 'notifications.manage', 'System Administration', 'Inspect WebSocket broadcast policy and SMTP delivery parameters', NOW()),

-- Employee Self-Service
('View Own Profile', 'profile.view_own', 'Employee Self-Service', 'View personal employee record, contact details, and assigned position', NOW()),
('View Own Leave Requests', 'leave.view_own', 'Employee Self-Service', 'View personal filed leave request history and review statuses', NOW()),
('File Personal Leave', 'leave.file_own', 'Employee Self-Service', 'File a personal leave request against remaining balance', NOW()),
('Cancel Own Leave Request', 'leave.cancel_own', 'Employee Self-Service', 'Cancel a pending personal leave request before HR decision', NOW()),
('View Own Leave Balances', 'leave_balances.view_own', 'Employee Self-Service', 'View personal leave balances and quota remaining for current fiscal year', NOW()),

-- Academic & Universal
('Access DSA Laboratory', 'dsa_laboratory', 'Academic & Universal', 'Access interactive DSA benchmarks, algorithm traces, and memory visualizations', NOW()),
('Sign Out', 'logout', 'Academic & Universal', 'Safely terminate active session', NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `module` = VALUES(`module`), `description` = VALUES(`description`);

-- Step 5: Create `role_permissions` join table
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

-- Step 6: Seed Default Role Permissions
-- 1. Administrator (Role ID 1): Full access to ALL registered permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- 2. Human Resources (Role ID 2): Operational workforce & leave capabilities
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `permissions` WHERE `slug` IN (
    'employees.view', 'employees.create', 'employees.update', 'employees.deactivate',
    'departments.view', 'departments.manage', 'positions.view', 'positions.manage',
    'leave.view', 'leave.create', 'leave.approve', 'leave.reject', 'leave.cancel',
    'leave_types.manage', 'leave_balances.view', 'leave_balances.manage',
    'reports.view', 'reports.export', 'audit_logs.view', 'dsa_laboratory', 'logout'
);

-- 3. Employee (Role ID 3): Strict self-service capabilities
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions` WHERE `slug` IN (
    'profile.view_own', 'leave.view_own', 'leave.file_own', 'leave.cancel_own',
    'leave_balances.view_own', 'leave.create', 'leave.cancel', 'logout'
);

-- Step 7: Create `permission_audit_logs` table
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

-- Step 8: Safely add `role_id` to `users` table if not present
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'users' 
      AND COLUMN_NAME = 'role_id'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `users` ADD COLUMN `role_id` INT UNSIGNED NULL AFTER `role`, ADD INDEX `idx_users_role_id` (`role_id`), ADD CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE', 
    'SELECT "Column role_id already exists on users table"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 9: Backfill `users.role_id` based on existing `users.role` enum values
UPDATE `users` u
INNER JOIN `roles` r ON u.role = r.slug
SET u.role_id = r.id
WHERE u.role_id IS NULL;

