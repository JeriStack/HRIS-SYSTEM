<?php

/**
 * Authoritative Application Permission Registry
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Defines all registered granular permission tokens, their parent modules,
 * default role policies, and bidirectional aliases with system capabilities.
 */

namespace Core;

final class PermissionRegistry
{
    /**
     * System-defined immutable roles.
     */
    public const ROLE_ADMIN = 'admin';
    public const ROLE_HR = 'hr';
    public const ROLE_EMPLOYEE = 'employee';

    public const ROLES = [
        self::ROLE_ADMIN => [
            'name'        => 'Administrator',
            'slug'        => self::ROLE_ADMIN,
            'description' => 'Full system-wide administrative governance and security management',
            'is_system'   => true,
        ],
        self::ROLE_HR => [
            'name'        => 'Human Resources',
            'slug'        => self::ROLE_HR,
            'description' => 'Operational human resource and workforce operations management',
            'is_system'   => true,
        ],
        self::ROLE_EMPLOYEE => [
            'name'        => 'Employee',
            'slug'        => self::ROLE_EMPLOYEE,
            'description' => 'Employee personal self-service workspace and leave management',
            'is_system'   => true,
        ],
    ];

    /**
     * Authoritative registry of all granular permissions.
     * Each permission specifies:
     * - name: Human-readable display label
     * - module: Functional grouping for the matrix view
     * - description: Clear explanation of operational capability
     * - default_roles: Initial default assignments
     */
    public const PERMISSIONS = [
        // -------------------------------------------------------------
        // Module: User Administration
        // -------------------------------------------------------------
        'users.view' => [
            'name'          => 'View User Accounts',
            'module'        => 'User Administration',
            'description'   => 'View the user accounts directory and account metadata',
            'default_roles' => [self::ROLE_ADMIN],
        ],
        'users.create' => [
            'name'          => 'Create User Accounts',
            'module'        => 'User Administration',
            'description'   => 'Provision and create new user access accounts',
            'default_roles' => [self::ROLE_ADMIN],
        ],
        'users.update' => [
            'name'          => 'Update User Accounts',
            'module'        => 'User Administration',
            'description'   => 'Modify existing user credentials, emails, and profile links',
            'default_roles' => [self::ROLE_ADMIN],
        ],
        'users.deactivate' => [
            'name'          => 'Deactivate User Accounts',
            'module'        => 'User Administration',
            'description'   => 'Deactivate, reactivate, or lock user accounts',
            'default_roles' => [self::ROLE_ADMIN],
        ],
        'roles.view' => [
            'name'          => 'View Roles & Permissions',
            'module'        => 'User Administration',
            'description'   => 'View system roles and current capability assignments',
            'default_roles' => [self::ROLE_ADMIN],
        ],
        'roles.manage' => [
            'name'          => 'Manage Role Permissions',
            'module'        => 'User Administration',
            'description'   => 'Configure, grant, and revoke permissions for HR and Employee roles',
            'default_roles' => [self::ROLE_ADMIN],
        ],
        'admin.provision' => [
            'name'          => 'Admin Provisioning',
            'module'        => 'User Administration',
            'description'   => 'Inspect active administrators and bootstrap recovery guidance',
            'default_roles' => [self::ROLE_ADMIN],
        ],

        // -------------------------------------------------------------
        // Module: Organization Management
        // -------------------------------------------------------------
        'employees.view' => [
            'name'          => 'View Employee Directory',
            'module'        => 'Organization Management',
            'description'   => 'View employee roster, departmental staff, and employment status',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],
        'employees.create' => [
            'name'          => 'Create Employee Records',
            'module'        => 'Organization Management',
            'description'   => 'Onboard new personnel and create employee master records',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],
        'employees.update' => [
            'name'          => 'Update Employee Records',
            'module'        => 'Organization Management',
            'description'   => 'Edit employee details, job titles, and departmental assignments',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],
        'employees.deactivate' => [
            'name'          => 'Deactivate Employee Records',
            'module'        => 'Organization Management',
            'description'   => 'Soft-deactivate employee profiles upon resignation or termination',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],
        'departments.view' => [
            'name'          => 'View Departments',
            'module'        => 'Organization Management',
            'description'   => 'Inspect departmental listings, codes, and staffing levels',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],
        'departments.manage' => [
            'name'          => 'Manage Departments',
            'module'        => 'Organization Management',
            'description'   => 'Create, rename, and manage organizational departments',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],
        'positions.view' => [
            'name'          => 'View Job Positions',
            'module'        => 'Organization Management',
            'description'   => 'Inspect organizational job titles and department assignments',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],
        'positions.manage' => [
            'name'          => 'Manage Job Positions',
            'module'        => 'Organization Management',
            'description'   => 'Create, edit, and configure job position titles and requirements',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],

        // -------------------------------------------------------------
        // Module: Leave Administration
        // -------------------------------------------------------------
        'leave.view' => [
            'name'          => 'View Leave Requests Queue',
            'module'        => 'Leave Administration',
            'description'   => 'Inspect workforce leave filings, approval queue, and historical audits',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],
        'leave.create' => [
            'name'          => 'File Leave Requests',
            'module'        => 'Leave Administration',
            'description'   => 'Submit leave requests into the review pipeline',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR, self::ROLE_EMPLOYEE],
        ],
        'leave.approve' => [
            'name'          => 'Approve Leave Requests',
            'module'        => 'Leave Administration',
            'description'   => 'Review and approve pending employee leave applications',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],
        'leave.reject' => [
            'name'          => 'Reject Leave Requests',
            'module'        => 'Leave Administration',
            'description'   => 'Reject leave applications with mandatory auditable reasons',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],
        'leave.cancel' => [
            'name'          => 'Cancel Leave Requests',
            'module'        => 'Leave Administration',
            'description'   => 'Cancel pending leave requests before processing',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR, self::ROLE_EMPLOYEE],
        ],
        'leave_types.manage' => [
            'name'          => 'Manage Leave Types',
            'module'        => 'Leave Administration',
            'description'   => 'Configure statutory leave classifications and default quotas',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],
        'leave_balances.view' => [
            'name'          => 'View Leave Balances',
            'module'        => 'Leave Administration',
            'description'   => 'Inspect allocated, used, and remaining employee leave days',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],
        'leave_balances.manage' => [
            'name'          => 'Manage Leave Balances',
            'module'        => 'Leave Administration',
            'description'   => 'Perform transaction-safe adjustments to leave quotas',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],

        // -------------------------------------------------------------
        // Module: Reports & Analytics
        // -------------------------------------------------------------
        'reports.view' => [
            'name'          => 'View Reports & Analytics',
            'module'        => 'Reports & Analytics',
            'description'   => 'View workforce headcount distributions and leave utilization reports',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],
        'reports.export' => [
            'name'          => 'Export Reports',
            'module'        => 'Reports & Analytics',
            'description'   => 'Export reporting data to printable or downloadable formats',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],

        // -------------------------------------------------------------
        // Module: Security & Compliance
        // -------------------------------------------------------------
        'audit_logs.view' => [
            'name'          => 'View Activity Audit Logs',
            'module'        => 'Security & Compliance',
            'description'   => 'Search and inspect system-wide audit activity logs',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],
        'security_events.view' => [
            'name'          => 'View Security Events',
            'module'        => 'Security & Compliance',
            'description'   => 'Monitor authentication attempts, OTP verifications, and lockout incidents',
            'default_roles' => [self::ROLE_ADMIN],
        ],
        'auth_settings.view' => [
            'name'          => 'View Authentication Settings',
            'module'        => 'Security & Compliance',
            'description'   => 'View session timeout limits, MFA enforcement, and password policies',
            'default_roles' => [self::ROLE_ADMIN],
        ],
        'auth_settings.manage' => [
            'name'          => 'Manage Authentication Settings',
            'module'        => 'Security & Compliance',
            'description'   => 'Configure authentication security controls and session duration',
            'default_roles' => [self::ROLE_ADMIN],
        ],

        // -------------------------------------------------------------
        // Module: System Administration
        // -------------------------------------------------------------
        'system_settings.view' => [
            'name'          => 'View General Settings',
            'module'        => 'System Administration',
            'description'   => 'Inspect application configuration parameters and masked environment secrets',
            'default_roles' => [self::ROLE_ADMIN],
        ],
        'system_settings.manage' => [
            'name'          => 'Manage General Settings',
            'module'        => 'System Administration',
            'description'   => 'Update system-wide application settings and company profile',
            'default_roles' => [self::ROLE_ADMIN],
        ],
        'system_health.view' => [
            'name'          => 'View System Health',
            'module'        => 'System Administration',
            'description'   => 'Run genuine runtime diagnostic socket probes on DB, WS, and SMTP',
            'default_roles' => [self::ROLE_ADMIN],
        ],
        'notifications.manage' => [
            'name'          => 'Manage Notification Settings',
            'module'        => 'System Administration',
            'description'   => 'Inspect WebSocket broadcast policy and SMTP delivery parameters',
            'default_roles' => [self::ROLE_ADMIN],
        ],

        // -------------------------------------------------------------
        // Module: Employee Self-Service (Ownership Scoped)
        // -------------------------------------------------------------
        'profile.view_own' => [
            'name'          => 'View Own Profile',
            'module'        => 'Employee Self-Service',
            'description'   => 'View personal employee record, contact details, and assigned position',
            'default_roles' => [self::ROLE_EMPLOYEE],
        ],
        'leave.view_own' => [
            'name'          => 'View Own Leave Requests',
            'module'        => 'Employee Self-Service',
            'description'   => 'View personal filed leave request history and review statuses',
            'default_roles' => [self::ROLE_EMPLOYEE],
        ],
        'leave.file_own' => [
            'name'          => 'File Personal Leave',
            'module'        => 'Employee Self-Service',
            'description'   => 'File a personal leave request against remaining balance',
            'default_roles' => [self::ROLE_EMPLOYEE],
        ],
        'leave.cancel_own' => [
            'name'          => 'Cancel Own Leave Request',
            'module'        => 'Employee Self-Service',
            'description'   => 'Cancel a pending personal leave request before HR decision',
            'default_roles' => [self::ROLE_EMPLOYEE],
        ],
        'leave_balances.view_own' => [
            'name'          => 'View Own Leave Balances',
            'module'        => 'Employee Self-Service',
            'description'   => 'View personal leave balances and quota remaining for the current fiscal year',
            'default_roles' => [self::ROLE_EMPLOYEE],
        ],

        // -------------------------------------------------------------
        // Module: Academic & Universal
        // -------------------------------------------------------------
        'dsa_laboratory' => [
            'name'          => 'Access DSA Laboratory',
            'module'        => 'Academic & Universal',
            'description'   => 'Access interactive DSA benchmarks, algorithm traces, and memory visualizations',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR],
        ],
        'logout' => [
            'name'          => 'Sign Out',
            'module'        => 'Academic & Universal',
            'description'   => 'Safely terminate active session',
            'default_roles' => [self::ROLE_ADMIN, self::ROLE_HR, self::ROLE_EMPLOYEE],
        ],
    ];

    /**
     * Backward-compatible capability alias mappings.
     * Maps established capability tokens to primary granular permission slugs.
     */
    public const CAPABILITY_ALIASES = [
        'admin_dashboard'           => 'roles.view',
        'hr_dashboard'              => 'employees.view',
        'employee_dashboard'        => 'profile.view_own',
        'manage_users'              => 'users.view',
        'assign_roles'              => 'roles.manage',
        'manage_employees'          => 'employees.view',
        'view_employee_records'     => 'employees.view',
        'manage_departments'        => 'departments.manage',
        'manage_positions'          => 'positions.manage',
        'manage_leave_types'        => 'leave_types.manage',
        'review_leave_requests'     => 'leave.approve',
        'manage_leave_balances'     => 'leave_balances.manage',
        'view_reports'              => 'reports.view',
        'view_activity_logs'        => 'audit_logs.view',
        'view_monitoring'           => 'system_health.view',
        'system_settings'           => 'system_settings.view',
        'view_own_profile'          => 'profile.view_own',
        'view_own_leave_balances'   => 'leave_balances.view_own',
        'file_leave_requests'       => 'leave.file_own',
        'view_own_leave_requests'   => 'leave.view_own',
        'cancel_own_leave_requests' => 'leave.cancel_own',
    ];

    /**
     * Check if a permission slug is a valid registered permission.
     */
    public static function isValidPermission(string $slug): bool
    {
        return isset(self::PERMISSIONS[$slug]) || isset(self::CAPABILITY_ALIASES[$slug]);
    }

    /**
     * Normalize an incoming capability or permission token to its canonical slug.
     */
    public static function normalize(string $token): string
    {
        $trimmed = trim($token);
        return self::CAPABILITY_ALIASES[$trimmed] ?? $trimmed;
    }

    /**
     * Group registered permissions by module for UI display.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function getPermissionsByModule(): array
    {
        $grouped = [];
        foreach (self::PERMISSIONS as $slug => $meta) {
            $module = $meta['module'];
            $grouped[$module][$slug] = $meta;
        }
        return $grouped;
    }

    /**
     * Retrieve default role capability map for offline/bootstrap fallbacks.
     *
     * @return array<string, array<string>>
     */
    public static function getDefaultRoleCapabilities(): array
    {
        $mapping = [];
        foreach (self::PERMISSIONS as $slug => $meta) {
            $mapping[$slug] = $meta['default_roles'];
        }
        // Include capability alias keys in mapping so existing tests pass seamlessly
        foreach (self::CAPABILITY_ALIASES as $alias => $targetSlug) {
            if (isset(self::PERMISSIONS[$targetSlug])) {
                $mapping[$alias] = self::PERMISSIONS[$targetSlug]['default_roles'];
            }
        }

        // Special handling for dashboard keys to maintain mutual exclusion:
        $mapping['admin_dashboard'] = [self::ROLE_ADMIN];
        $mapping['hr_dashboard'] = [self::ROLE_HR];
        $mapping['employee_dashboard'] = [self::ROLE_EMPLOYEE];

        return $mapping;
    }
}
