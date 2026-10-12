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
        // Dashboard Boundaries (Strictly Isolated)
        'admin_dashboard'           => ['hr'],
        'hr_dashboard'              => ['hr'],
        'employee_dashboard'        => ['employee'],

        // Administrative & User Governance (Managed by HR)
        'manage_users'              => ['hr'],
        'assign_roles'              => ['hr'],
        'system_settings'           => ['hr'],

        // Workforce & Organization Operations
        'manage_employees'          => ['hr'],
        'manage_departments'        => ['hr'],
        'manage_positions'          => ['hr'],
        'view_employee_records'     => ['hr'],

        // Leave Governance & Balance Adjustments
        'manage_leave_types'        => ['hr'],
        'review_leave_requests'     => ['hr'],
        'manage_leave_balances'     => ['hr'],

        // Employee Self-Service Operations (Ownership-Enforced)
        'view_own_profile'          => ['employee'],
        'view_own_leave_balances'   => ['employee'],
        'file_leave_requests'       => ['employee'],
        'view_own_leave_requests'   => ['employee'],
        'cancel_own_leave_requests' => ['employee'],

        // Analytics, Audit & Research
        'view_reports'              => ['hr'],
        'view_activity_logs'        => ['hr'],
        'dsa_laboratory'            => ['hr'],

        // Universal Session Operations
        'logout'                    => ['hr', 'employee'],
    ];

    /**
     * Exact valid role allowlist (2-Role Model: HR and Employee).
     */
    public const VALID_ROLES = ['hr', 'employee'];

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
