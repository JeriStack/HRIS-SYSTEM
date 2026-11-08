<?php

namespace Tests\Feature;

use App\Models\User;
use App\Realtime\ChannelPolicy;
use App\Realtime\EventAuthorizer;
use App\Realtime\Identity;
use Core\Database;
use Core\Permission;
use Core\RoleMiddleware;
use Core\Session;
use PHPUnit\Framework\TestCase;

/**
 * Three-Role RBAC & System-Wide Admin Governance Test Suite
 *
 * Verifies the 3-role security model:
 * 1. Admin: Full system-wide governance, user account management, role assignment, monitoring.
 * 2. HR: Workforce operations, leave review queue, reporting.
 * 3. Employee: Self-service workspace.
 */
class AdminRoleRbacTest extends TestCase
{
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        Session::destroy();
        Session::start();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        Session::remove('user');
        Session::destroy();
    }

    public function test_three_role_allowlist_is_enforced(): void
    {
        $validRoles = Permission::VALID_ROLES;

        $this->assertContains('admin', $validRoles);
        $this->assertContains('hr', $validRoles);
        $this->assertContains('employee', $validRoles);
        $this->assertCount(3, $validRoles, 'System must support strictly 3 roles: admin, hr, employee.');

        $this->assertTrue(Permission::isValidRole('admin'));
        $this->assertTrue(Permission::isValidRole('hr'));
        $this->assertTrue(Permission::isValidRole('employee'));
        $this->assertFalse(Permission::isValidRole('superadmin'));
        $this->assertFalse(Permission::isValidRole('auditor'));
        $this->assertFalse(Permission::isValidRole(''));
    }

    public function test_admin_role_capabilities_matrix(): void
    {
        // Admin specific capabilities
        $this->assertTrue(Permission::can('admin', 'admin_dashboard'));
        $this->assertTrue(Permission::can('admin', 'manage_users'));
        $this->assertTrue(Permission::can('admin', 'assign_roles'));
        $this->assertTrue(Permission::can('admin', 'system_settings'));
        $this->assertTrue(Permission::can('admin', 'view_monitoring'));

        // Shared workforce & governance capabilities
        $this->assertTrue(Permission::can('admin', 'manage_employees'));
        $this->assertTrue(Permission::can('admin', 'manage_departments'));
        $this->assertTrue(Permission::can('admin', 'manage_positions'));
        $this->assertTrue(Permission::can('admin', 'manage_leave_types'));
        $this->assertTrue(Permission::can('admin', 'review_leave_requests'));
        $this->assertTrue(Permission::can('admin', 'manage_leave_balances'));
        $this->assertTrue(Permission::can('admin', 'view_reports'));
        $this->assertTrue(Permission::can('admin', 'view_activity_logs'));
        $this->assertTrue(Permission::can('admin', 'dsa_laboratory'));
        $this->assertTrue(Permission::can('admin', 'logout'));

        // Admin does NOT have HR or Employee specific dashboard views
        $this->assertFalse(Permission::can('admin', 'hr_dashboard'));
        $this->assertFalse(Permission::can('admin', 'employee_dashboard'));
    }

    public function test_hr_role_forbidden_from_admin_governance(): void
    {
        $this->assertFalse(Permission::can('hr', 'admin_dashboard'));
        $this->assertFalse(Permission::can('hr', 'manage_users'));
        $this->assertFalse(Permission::can('hr', 'assign_roles'));
        $this->assertFalse(Permission::can('hr', 'system_settings'));
        $this->assertFalse(Permission::can('hr', 'view_monitoring'));

        // HR retains operational capabilities
        $this->assertTrue(Permission::can('hr', 'hr_dashboard'));
        $this->assertTrue(Permission::can('hr', 'manage_employees'));
        $this->assertTrue(Permission::can('hr', 'review_leave_requests'));
        $this->assertTrue(Permission::can('hr', 'view_reports'));
    }

    public function test_employee_role_strictly_isolated_to_self_service(): void
    {
        $this->assertFalse(Permission::can('employee', 'admin_dashboard'));
        $this->assertFalse(Permission::can('employee', 'manage_users'));
        $this->assertFalse(Permission::can('employee', 'assign_roles'));
        $this->assertFalse(Permission::can('employee', 'hr_dashboard'));
        $this->assertFalse(Permission::can('employee', 'manage_employees'));
        $this->assertFalse(Permission::can('employee', 'review_leave_requests'));

        // Employee self-service capabilities
        $this->assertTrue(Permission::can('employee', 'employee_dashboard'));
        $this->assertTrue(Permission::can('employee', 'view_own_profile'));
        $this->assertTrue(Permission::can('employee', 'view_own_leave_balances'));
        $this->assertTrue(Permission::can('employee', 'file_leave_requests'));
        $this->assertTrue(Permission::can('employee', 'view_own_leave_requests'));
    }

    public function test_role_middleware_enforces_admin_access(): void
    {
        $adminOnly = new RoleMiddleware(['admin']);
        $workforceRoles = new RoleMiddleware(['admin', 'hr']);

        // Admin allowed
        Session::set('user', ['id' => 9991, 'role' => 'admin', 'status' => 'Active']);
        $this->assertTrue($adminOnly->handle());
        $this->assertTrue($workforceRoles->handle());

        // HR allowed on workforce but denied on adminOnly
        // (In seeded database, user ID 2 is an active HR account)
        Session::set('user', ['id' => 2, 'role' => 'hr', 'status' => 'Active']);
        $this->assertTrue($workforceRoles->handle());
        $this->assertFalse($adminOnly->handle());

        // Employee denied on both
        // (In seeded database, user ID 4 is an active Employee account)
        Session::set('user', ['id' => 4, 'role' => 'employee', 'status' => 'Active']);
        $this->assertFalse($adminOnly->handle());
        $this->assertFalse($workforceRoles->handle());

        // Unauthenticated visitor denied on both
        Session::remove('user');
        $this->assertFalse($adminOnly->handle());
        $this->assertFalse($workforceRoles->handle());
    }

    public function test_realtime_identity_and_policy_for_admin(): void
    {
        $adminIdentity = new Identity(
            userId: 1,
            role: 'admin',
            employeeId: null,
            sessionRef: 'sess_ref_123',
            sessionExpiresAt: time() + 3600
        );

        $this->assertTrue($adminIdentity->isAdmin());
        $this->assertFalse($adminIdentity->isHr());
        $this->assertFalse($adminIdentity->isEmployee());

        $policy = new ChannelPolicy();
        $channels = $policy->channelsFor($adminIdentity);

        $this->assertContains('user.1', $channels);
        $this->assertContains(ChannelPolicy::ROLE_ADMIN, $channels);
        $this->assertContains(ChannelPolicy::ROLE_HR, $channels);

        $this->assertTrue($policy->canSubscribe($adminIdentity, 'user.1'));
        $this->assertTrue($policy->canSubscribe($adminIdentity, ChannelPolicy::ROLE_ADMIN));
        $this->assertTrue($policy->canSubscribe($adminIdentity, ChannelPolicy::ROLE_HR));
        $this->assertFalse($policy->canSubscribe($adminIdentity, ChannelPolicy::ROLE_EMPLOYEE));
        $this->assertFalse($policy->canSubscribe($adminIdentity, 'role.unauthorized'));
    }

    public function test_event_authorizer_delivers_leave_events_to_admin(): void
    {
        $adminIdentity = new Identity(
            userId: 1,
            role: 'admin',
            employeeId: null,
            sessionRef: 'sess_ref_123',
            sessionExpiresAt: time() + 3600
        );

        $authorizer = new EventAuthorizer();

        $row = [
            'user_id' => 1,
            'event_type' => EventAuthorizer::LEAVE_CREATED,
            'recipient_role' => 'admin',
            'recipient_status' => 'Active',
            'owner_matches' => 0,
        ];

        $this->assertTrue($authorizer->canDeliver($adminIdentity, $row));
    }

    public function test_session_state_verification_for_admin(): void
    {
        $adminUser = [
            'id' => 1,
            'username' => 'admin.nexora',
            'email' => 'admin@nexora.local',
            'role' => 'admin',
            'status' => 'Active',
            'employment_status' => 'Active',
            'department_name' => 'IT & Security',
            'position_name' => 'System Administrator',
            'login_time' => time(),
            'last_mfa_verified_at' => time(),
            'auth_fingerprint' => hash('sha256', 'fingerprint_seed'),
        ];

        Session::set('user', $adminUser);
        Session::set('last_activity', time());

        $this->assertSame('admin', currentUserRole());
        $this->assertSame(1, currentUserId());
        $this->assertTrue(isAuthenticated());
    }
}
