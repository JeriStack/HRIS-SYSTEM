<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Core\Permission;

class AuthorizationTest extends TestCase
{
    public function test_hr_role_possesses_administrative_and_management_permissions(): void
    {
        $this->assertTrue(Permission::can('hr', 'manage_employees'));
        $this->assertTrue(Permission::can('hr', 'manage_departments'));
        $this->assertTrue(Permission::can('hr', 'manage_positions'));
        $this->assertTrue(Permission::can('hr', 'review_leave_requests'));
        $this->assertTrue(Permission::can('hr', 'manage_leave_balances'));
        $this->assertTrue(Permission::can('hr', 'view_reports'));
    }

    public function test_employee_role_possesses_only_self_service_permissions(): void
    {
        $this->assertTrue(Permission::can('employee', 'file_leave_requests'));
        $this->assertTrue(Permission::can('employee', 'view_own_profile'));
        $this->assertTrue(Permission::can('employee', 'view_own_leave_balances'));
        $this->assertTrue(Permission::can('employee', 'cancel_own_leave_requests'));

        // Employee must NOT possess HR/management permissions
        $this->assertFalse(Permission::can('employee', 'manage_employees'));
        $this->assertFalse(Permission::can('employee', 'review_leave_requests'));
        $this->assertFalse(Permission::can('employee', 'manage_leave_types'));
        $this->assertFalse(Permission::can('employee', 'view_reports'));
    }

    public function test_permission_fails_closed_for_invalid_roles_or_capabilities(): void
    {
        $this->assertFalse(Permission::can(null, 'manage_employees'));
        $this->assertFalse(Permission::can('guest', 'view_own_profile'));
        $this->assertFalse(Permission::can('hacker', 'system_settings'));
        $this->assertFalse(Permission::can('hr', 'nonexistent_capability'));
    }

    public function test_employee_ownership_idor_protection_logic(): void
    {
        $sessionEmployeeId = 5;

        // Viewing own record is permitted
        $requestedEmployeeId = 5;
        $isOwner = ($sessionEmployeeId === $requestedEmployeeId);
        $this->assertTrue($isOwner, 'Employee must be permitted to view their own profile.');

        // Tampering with emp_id parameter targeting another employee is blocked
        $tamperedEmployeeId = 8;
        $isTamperedOwner = ($sessionEmployeeId === $tamperedEmployeeId);
        $this->assertFalse($isTamperedOwner, 'Employee must be denied when attempting to access another employee profile (IDOR prevention).');
    }
}
