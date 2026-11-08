<?php
/**
 * Centralized Role-Based Access Control (RBAC) Permission Policy Service
 * Employee HRIS & Leave Management System
 *
 * Implements strict, server-side permission resolution according to the baseline
 * capability matrix for Administrator, HR, and Employee.
 */

namespace Core;

class Permission
{
    /**
     * Authoritative Capability Matrix
     * Maps named capability tokens to exact permitted roles.
     */
    private const CAPABILITY_ROLES = [
        // Dashboard Boundaries
        'admin_dashboard'           => ['admin'],
        'hr_dashboard'              => ['hr'],
        'employee_dashboard'        => ['employee'],

        // Administrative & User Governance (Admin Only)
        'manage_users'              => ['admin'],
        'assign_roles'              => ['admin'],
        'system_settings'           => ['admin'],
        'view_monitoring'           => ['admin'],

        // Workforce & Organization Operations (Admin & HR)
        'manage_employees'          => ['admin', 'hr'],
        'manage_departments'        => ['admin', 'hr'],
        'manage_positions'          => ['admin', 'hr'],
        'view_employee_records'     => ['admin', 'hr'],

        // Leave Governance & Balance Adjustments (Admin & HR)
        'manage_leave_types'        => ['admin', 'hr'],
        'review_leave_requests'     => ['admin', 'hr'],
        'manage_leave_balances'     => ['admin', 'hr'],

        // Employee Self-Service Operations (Ownership-Enforced)
        'view_own_profile'          => ['employee'],
        'view_own_leave_balances'   => ['employee'],
        'file_leave_requests'       => ['employee'],
        'view_own_leave_requests'   => ['employee'],
        'cancel_own_leave_requests' => ['employee'],

        // Analytics, Audit & Research (Admin & HR)
        'view_reports'              => ['admin', 'hr'],
        'view_activity_logs'        => ['admin', 'hr'],
        'dsa_laboratory'            => ['admin', 'hr'],

        // Universal Session Operations
        'logout'                    => ['admin', 'hr', 'employee'],
    ];

    /**
     * Exact valid role allowlist (3-Role Model: Admin, HR, and Employee).
     */
    public const VALID_ROLES = ['admin', 'hr', 'employee'];

    /**
     * Public alias for registered capability matrix.
     */
    public const ROLE_CAPABILITIES = self::CAPABILITY_ROLES;

    /**
     * Check if a given role possesses a specific capability.
     * Deny-by-default (fails closed) if role is null, invalid, or capability is unrecognized.
     *
     * @param string|null $role
     * @param string $capability
     * @return bool
     */
    public static function can(?string $role, string $capability): bool
    {
        if ($role === null || $role === '') {
            return false;
        }

        $normalizedRole = strtolower(trim($role));
        if (!in_array($normalizedRole, self::VALID_ROLES, true)) {
            return false;
        }

        if (!array_key_exists($capability, self::CAPABILITY_ROLES)) {
            return false;
        }

        return in_array($normalizedRole, self::CAPABILITY_ROLES[$capability], true);
    }

    /**
     * Retrieve the list of allowed roles for a given capability.
     *
     * @param string $capability
     * @return array
     */
    public static function getAllowedRoles(string $capability): array
    {
        return self::CAPABILITY_ROLES[$capability] ?? [];
    }

    /**
     * Return all capabilities registered in the authorization matrix.
     *
     * @return array
     */
    public static function getAllCapabilities(): array
    {
        return self::CAPABILITY_ROLES;
    }

    /**
     * Determine if a role string is a valid application role.
     *
     * @param string $role
     * @return bool
     */
    public static function isValidRole(string $role): bool
    {
        return in_array(strtolower(trim($role)), self::VALID_ROLES, true);
    }
}
