<?php

/**
 * Centralized Role-Based Access Control (RBAC) Permission Policy Service
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Implements strict, server-side permission resolution according to the baseline
 * capability matrix for Administrator, HR, and Employee, backed dynamically by
 * Core\AuthorizationService.
 */

namespace Core;

class Permission
{
    /**
     * Authoritative Capability Matrix (Default Fallback Baseline)
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

        // Workforce & Organization Operations (Restricted Oversight vs Full HR CRUD)
        'manage_employees'          => ['admin', 'hr'],
        'manage_departments'        => ['hr'],
        'manage_positions'          => ['hr'],
        'view_employee_records'     => ['admin', 'hr'],

        // Leave Governance & Balance Adjustments (HR Operational Ownership)
        'manage_leave_types'        => ['hr'],
        'review_leave_requests'     => ['hr'],
        'manage_leave_balances'     => ['hr'],

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
     * Check if a given role or user possesses a specific capability.
     * Deny-by-default (fails closed) if role is null, invalid, or capability is unrecognized.
     *
     * @param mixed $userOrRole Role string or user array
     * @param string $capability Granular permission slug or capability key
     * @return bool
     */
    public static function can(mixed $userOrRole, string $capability): bool
    {
        return AuthorizationService::getInstance()->can($userOrRole, $capability);
    }

    /**
     * Determine if a user or role possesses a specific role.
     */
    public static function hasRole(mixed $userOrRole, string $role): bool
    {
        return AuthorizationService::getInstance()->hasRole($userOrRole, $role);
    }

    /**
     * Retrieve all active permissions for a given role or user.
     *
     * @param mixed $userOrRole
     * @return array<string, bool>
     */
    public static function getPermissions(mixed $userOrRole): array
    {
        return AuthorizationService::getInstance()->getPermissions($userOrRole);
    }

    /**
     * Check if a user or role possesses any of the specified permissions.
     *
     * @param mixed $userOrRole
     * @param array<string> $permissions
     * @return bool
     */
    public static function hasAny(mixed $userOrRole, array $permissions): bool
    {
        return AuthorizationService::getInstance()->hasAnyPermission($userOrRole, $permissions);
    }

    /**
     * Check if a user or role possesses all of the specified permissions.
     *
     * @param mixed $userOrRole
     * @param array<string> $permissions
     * @return bool
     */
    public static function hasAll(mixed $userOrRole, array $permissions): bool
    {
        return AuthorizationService::getInstance()->hasAllPermissions($userOrRole, $permissions);
    }

    /**
     * Invalidate cached permissions for one or all roles.
     */
    public static function invalidateCache(?string $role = null): void
    {
        AuthorizationService::invalidateCache($role);
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
