<?php

/**
 * Dynamic Database-Driven RBAC & Admin Permission Governance Test Suite
 *
 * Verifies:
 * 1. Authoritative Permission Registry integrity & capability alias mappings.
 * 2. Centralized AuthorizationService evaluate rules and deny-by-default behavior.
 * 3. System-protected Admin immutable authority.
 * 4. Transaction-safe role permission updates & validation safeguards.
 * 5. Prompt cache invalidation upon configuration updates.
 * 6. Global helper functions (`can()`, `has_role()`).
 */

namespace Tests\Feature;

use App\Controllers\AdminController;
use Core\AuthorizationService;
use Core\Permission;
use Core\PermissionRegistry;
use Core\Session;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DynamicRbacTest extends TestCase
{
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        Session::destroy();
        Session::start();
        AuthorizationService::invalidateCache();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        Session::remove('user');
        Session::destroy();
        AuthorizationService::invalidateCache();
    }

    public function test_permission_registry_defines_three_system_roles(): void
    {
        $roles = PermissionRegistry::ROLES;

        $this->assertArrayHasKey('admin', $roles);
        $this->assertArrayHasKey('hr', $roles);
        $this->assertArrayHasKey('employee', $roles);
        $this->assertCount(3, $roles, 'System must support strictly 3 system roles.');

        $this->assertTrue($roles['admin']['is_system']);
        $this->assertTrue($roles['hr']['is_system']);
        $this->assertTrue($roles['employee']['is_system']);
    }

    public function test_permission_registry_validates_and_normalizes_slugs(): void
    {
        $this->assertTrue(PermissionRegistry::isValidPermission('users.view'));
        $this->assertTrue(PermissionRegistry::isValidPermission('leave.approve'));
        $this->assertTrue(PermissionRegistry::isValidPermission('profile.view_own'));
        $this->assertTrue(PermissionRegistry::isValidPermission('manage_users')); // Alias
        $this->assertTrue(PermissionRegistry::isValidPermission('review_leave_requests')); // Alias

        $this->assertFalse(PermissionRegistry::isValidPermission('arbitrary.injected.token'));
        $this->assertFalse(PermissionRegistry::isValidPermission(''));

        $this->assertSame('users.view', PermissionRegistry::normalize('manage_users'));
        $this->assertSame('leave.approve', PermissionRegistry::normalize('review_leave_requests'));
        $this->assertSame('leave.approve', PermissionRegistry::normalize('leave.approve'));
    }

    public function test_admin_has_full_authority_across_all_registered_permissions(): void
    {
        $auth = AuthorizationService::getInstance();

        foreach (array_keys(PermissionRegistry::PERMISSIONS) as $slug) {
            // Role-specific dashboard views maintain mutual exclusion
            if (in_array($slug, ['hr_dashboard', 'employee_dashboard'], true)) {
                continue;
            }
            $this->assertTrue(
                $auth->can('admin', $slug),
                "Admin must possess registered capability: {$slug}"
            );
        }

        // Admin cannot access employee self-service dashboard or HR operational dashboard
        $this->assertFalse($auth->can('admin', 'hr_dashboard'));
        $this->assertFalse($auth->can('admin', 'employee_dashboard'));
        $this->assertTrue($auth->can('admin', 'admin_dashboard'));
    }

    public function test_hr_possesses_workforce_operations_and_is_denied_admin_governance(): void
    {
        $auth = AuthorizationService::getInstance();

        // Operational Workforce Permissions
        $this->assertTrue($auth->can('hr', 'employees.view'));
        $this->assertTrue($auth->can('hr', 'employees.create'));
        $this->assertTrue($auth->can('hr', 'departments.view'));
        $this->assertTrue($auth->can('hr', 'positions.view'));
        $this->assertTrue($auth->can('hr', 'leave.view'));
        $this->assertTrue($auth->can('hr', 'leave.approve'));
        $this->assertTrue($auth->can('hr', 'leave.reject'));
        $this->assertTrue($auth->can('hr', 'leave_balances.manage'));
        $this->assertTrue($auth->can('hr', 'reports.view'));
        $this->assertTrue($auth->can('hr', 'audit_logs.view'));
        $this->assertTrue($auth->can('hr', 'hr_dashboard'));

        // Denied Administrative Governance
        $this->assertFalse($auth->can('hr', 'users.create'));
        $this->assertFalse($auth->can('hr', 'users.deactivate'));
        $this->assertFalse($auth->can('hr', 'roles.manage'));
        $this->assertFalse($auth->can('hr', 'admin.provision'));
        $this->assertFalse($auth->can('hr', 'system_settings.manage'));
        $this->assertFalse($auth->can('hr', 'admin_dashboard'));
    }

    public function test_employee_is_isolated_to_self_service_operations(): void
    {
        $auth = AuthorizationService::getInstance();

        // Self-Service Permissions
        $this->assertTrue($auth->can('employee', 'profile.view_own'));
        $this->assertTrue($auth->can('employee', 'leave.view_own'));
        $this->assertTrue($auth->can('employee', 'leave.file_own'));
        $this->assertTrue($auth->can('employee', 'leave.cancel_own'));
        $this->assertTrue($auth->can('employee', 'leave_balances.view_own'));
        $this->assertTrue($auth->can('employee', 'employee_dashboard'));

        // Denied Workforce Management & Governance
        $this->assertFalse($auth->can('employee', 'employees.view'));
        $this->assertFalse($auth->can('employee', 'employees.create'));
        $this->assertFalse($auth->can('employee', 'leave.approve'));
        $this->assertFalse($auth->can('employee', 'leave.reject'));
        $this->assertFalse($auth->can('employee', 'leave_balances.manage'));
        $this->assertFalse($auth->can('employee', 'users.view'));
        $this->assertFalse($auth->can('employee', 'reports.view'));
        $this->assertFalse($auth->can('employee', 'admin_dashboard'));
        $this->assertFalse($auth->can('employee', 'hr_dashboard'));
    }

    public function test_deny_by_default_on_unknown_tokens_and_invalid_roles(): void
    {
        $auth = AuthorizationService::getInstance();

        $this->assertFalse($auth->can(null, 'employees.view'));
        $this->assertFalse($auth->can('', 'employees.view'));
        $this->assertFalse($auth->can('guest', 'profile.view_own'));
        $this->assertFalse($auth->can('superadmin', 'users.view'));
        $this->assertFalse($auth->can('hacker', 'system_settings.view'));
        $this->assertFalse($auth->can('admin', 'unknown.malicious.permission'));
        $this->assertFalse($auth->can('hr', 'unknown.permission'));
        $this->assertFalse($auth->can('employee', ''));
    }

    public function test_immutable_admin_protection_prevents_modifying_admin_role(): void
    {
        $auth = AuthorizationService::getInstance();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/admin.*immutable/i');

        $auth->updateRolePermissions(
            actorUserId: 1,
            roleSlug: 'admin',
            permissionSlugs: ['users.view'],
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit Test'
        );
    }

    public function test_update_role_permissions_rejects_unknown_permission_identifiers(): void
    {
        $auth = AuthorizationService::getInstance();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unrecognized permission/i');

        $auth->updateRolePermissions(
            actorUserId: 1,
            roleSlug: 'hr',
            permissionSlugs: ['employees.view', 'malicious.injected.token'],
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit Test'
        );
    }

    public function test_permission_service_has_any_and_has_all(): void
    {
        $auth = AuthorizationService::getInstance();

        $this->assertTrue($auth->hasAnyPermission('hr', ['users.create', 'employees.view']));
        $this->assertFalse($auth->hasAnyPermission('hr', ['users.create', 'users.deactivate']));

        $this->assertTrue($auth->hasAllPermissions('hr', ['employees.view', 'leave.approve']));
        $this->assertFalse($auth->hasAllPermissions('hr', ['employees.view', 'users.create']));
    }

    public function test_prompt_cache_invalidation(): void
    {
        $auth = AuthorizationService::getInstance();

        $perms1 = $auth->getPermissions('hr');
        $this->assertNotEmpty($perms1);

        AuthorizationService::invalidateCache('hr');
        $perms2 = $auth->getPermissions('hr');
        $this->assertNotEmpty($perms2);

        AuthorizationService::invalidateCache();
        $perms3 = $auth->getPermissions('employee');
        $this->assertNotEmpty($perms3);
    }

    public function test_global_helper_functions_can_and_has_role(): void
    {
        Session::set('user', ['id' => 9991, 'role' => 'admin', 'status' => 'Active']);

        $this->assertTrue(has_role('admin'));
        $this->assertFalse(has_role('hr'));
        $this->assertFalse(has_role('employee'));

        $this->assertTrue(can('users.view'));
        $this->assertTrue(can('leave.approve'));

        Session::set('user', ['id' => 2, 'role' => 'hr', 'status' => 'Active']);
        $this->assertTrue(has_role('hr'));
        $this->assertFalse(has_role('admin'));
        $this->assertTrue(can('employees.view'));
        $this->assertFalse(can('users.create'));
    }

    public function test_admin_controller_structure_for_dynamic_rbac(): void
    {
        $controller = new AdminController();
        $reflection = new ReflectionClass($controller);

        $this->assertTrue($reflection->hasMethod('roles'), 'AdminController must provide roles matrix action.');
        $this->assertTrue($reflection->hasMethod('updatePermissions'), 'AdminController must provide updatePermissions action.');
    }

    public function test_admin_roles_view_file_exists_and_contains_four_governance_sections(): void
    {
        $viewPath = __DIR__ . '/../../app/views/admin/roles.php';
        $this->assertFileExists($viewPath, 'admin/roles.php view file must exist.');

        $content = (string)file_get_contents($viewPath);

        // Section 1: Roles Overview
        $this->assertStringContainsString('Roles Overview', $content);
        $this->assertStringContainsString('System Roles Overview', $content);
        $this->assertStringContainsString('SYSTEM PROTECTED', $content);

        // Section 2: Permission Matrix
        $this->assertStringContainsString('Permission Matrix', $content);
        $this->assertStringContainsString('perm-toggle-switch', $content);
        $this->assertStringContainsString('saveConfirmModal', $content);

        // Section 3: User Access Management
        $this->assertStringContainsString('User Access Management', $content);
        $this->assertStringContainsString('userAccessModal', $content);
        $this->assertStringContainsString('userAccessTable', $content);

        // Section 4: Permission Audit History
        $this->assertStringContainsString('Permission Audit History', $content);
        $this->assertStringContainsString('auditDetailModal', $content);
        $this->assertStringContainsString('auditTable', $content);
    }
}
