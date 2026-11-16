<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Realtime\EventAuthorizer;
use App\Realtime\Identity;
use App\Realtime\NotificationPayload;
use App\Services\HrSupportAccessService;
use Core\AuthorizationService;
use Core\PermissionRegistry;
use Core\Session;
use PDO;
use PHPUnit\Framework\TestCase;

class HrSupportAccessUnitTest extends TestCase
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

    public function test_config_defines_exact_categories_priorities_statuses_and_transitions(): void
    {
        $cfg = require dirname(__DIR__, 2) . '/config/hr_support.php';

        $this->assertSame('HR-SUP', $cfg['ticket_prefix']);
        $this->assertSame(['Low', 'Medium', 'High', 'Critical'], $cfg['priorities']);
        $this->assertSame([
            'Submitted',
            'Under Review',
            'In Progress',
            'Waiting for HR',
            'Resolved',
            'Closed',
        ], $cfg['statuses']);

        $expectedCategories = [
            'System Error',
            'Login and Authentication',
            'Account and Access Problems',
            'Dashboard or UI Issue',
            'Employee Management Issue',
            'Leave Management Issue',
            'Notification or Email Issue',
            'Performance or System Availability',
            'Data Discrepancy',
            'Other',
        ];
        $this->assertSame($expectedCategories, array_keys($cfg['categories']));
        $this->assertArrayHasKey('Closed', $cfg['admin_transitions']);
        $this->assertSame(['Under Review', 'In Progress'], $cfg['admin_transitions']['Closed']);
    }

    public function test_permission_registry_enforces_three_role_separation_for_hr_support(): void
    {
        $this->assertTrue(PermissionRegistry::defaultRoleHas('hr', 'hr_support.submit'));
        $this->assertTrue(PermissionRegistry::defaultRoleHas('hr', 'hr_support.view_own'));
        $this->assertTrue(PermissionRegistry::defaultRoleHas('hr', 'hr_support.reply_own'));
        $this->assertFalse(PermissionRegistry::defaultRoleHas('hr', 'hr_support.admin_inbox'));
        $this->assertFalse(PermissionRegistry::defaultRoleHas('hr', 'hr_support.admin_manage'));
        $this->assertFalse(PermissionRegistry::defaultRoleHas('hr', 'hr_support.internal_notes'));

        $this->assertTrue(PermissionRegistry::defaultRoleHas('admin', 'hr_support.admin_inbox'));
        $this->assertTrue(PermissionRegistry::defaultRoleHas('admin', 'hr_support.admin_manage'));
        $this->assertTrue(PermissionRegistry::defaultRoleHas('admin', 'hr_support.internal_notes'));
        $this->assertFalse(PermissionRegistry::defaultRoleHas('admin', 'hr_support.submit'));
        $this->assertFalse(PermissionRegistry::defaultRoleHas('admin', 'hr_support.view_own'));
        $this->assertFalse(PermissionRegistry::defaultRoleHas('admin', 'support.view_own'));
        $this->assertFalse(PermissionRegistry::defaultRoleHas('admin', 'leave_confidential.view'));

        $this->assertFalse(PermissionRegistry::defaultRoleHas('employee', 'hr_support.submit'));
        $this->assertFalse(PermissionRegistry::defaultRoleHas('employee', 'hr_support.admin_inbox'));
    }

    public function test_access_service_enforces_ownership_internal_notes_and_reopen_window(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $checker = static fn (array $u, string $slug): bool => PermissionRegistry::defaultRoleHas((string)$u['role'], $slug);
        $access = new HrSupportAccessService($checker);

        $hrOwner = ['id' => 10, 'role' => 'hr', 'status' => 'Active'];
        $hrOther = ['id' => 11, 'role' => 'hr', 'status' => 'Active'];
        $admin = ['id' => 1, 'role' => 'admin', 'status' => 'Active'];
        $employee = ['id' => 20, 'role' => 'employee', 'status' => 'Active'];

        $ticket = [
            'id'                   => 55,
            'ticket_ref'           => 'HR-SUP-2026-AB23CD45',
            'submitted_by_user_id' => 10,
            'status'               => 'Resolved',
            'resolved_at'          => (new \DateTimeImmutable('-2 days'))->format('Y-m-d H:i:s'),
        ];

        $this->assertTrue($access->canViewTicketAsHr($hrOwner, $ticket, $pdo));
        $this->assertFalse($access->canViewTicketAsHr($hrOther, $ticket, $pdo));
        $this->assertFalse($access->canViewTicketAsHr($admin, $ticket, $pdo));
        $this->assertFalse($access->canViewTicketAsHr($employee, $ticket, $pdo));

        $this->assertTrue($access->canViewTicketAsAdmin($admin, $pdo));
        $this->assertFalse($access->canViewTicketAsAdmin($hrOwner, $pdo));
        $this->assertFalse($access->canViewInternalNotes($hrOwner, $pdo));
        $this->assertTrue($access->canViewInternalNotes($admin, $pdo));

        // Internal-only attachments belong solely to Admins even when attached to HR's ticket
        $internalAtt = ['is_internal_only' => 1, 'ticket_submitted_by_user_id' => 10];
        $publicAtt = ['is_internal_only' => 0, 'ticket_submitted_by_user_id' => 10];
        $this->assertFalse($access->canDownloadAttachment($hrOwner, $internalAtt, $pdo, 'hr'));
        $this->assertTrue($access->canDownloadAttachment($hrOwner, $publicAtt, $pdo, 'hr'));
        $this->assertTrue($access->canDownloadAttachment($admin, $internalAtt, $pdo, 'admin'));

        // Reopen policy: allowed on Resolved within 14d, forbidden after 14d or when Closed
        $this->assertTrue($access->canHrReopen($ticket, 14));
        $expiredTicket = $ticket;
        $expiredTicket['resolved_at'] = (new \DateTimeImmutable('-20 days'))->format('Y-m-d H:i:s');
        $this->assertFalse($access->canHrReopen($expiredTicket, 14));

        $closedTicket = $ticket;
        $closedTicket['status'] = 'Closed';
        $this->assertFalse($access->canHrReopen($closedTicket, 14));
        $this->assertFalse($access->canHrConfirmResolution($closedTicket));
        $this->assertTrue($access->canHrConfirmResolution($ticket));
    }

    public function test_realtime_authorizer_and_payload_keep_hr_support_strictly_scoped(): void
    {
        $authorizer = new EventAuthorizer();
        $adminIdentity = new Identity(1, 'admin', null, 'sess_a', time() + 3600);
        $hrIdentity = new Identity(2, 'hr', 102, 'sess_h', time() + 3600);
        $empIdentity = new Identity(3, 'employee', 103, 'sess_e', time() + 3600);

        $this->assertTrue($authorizer->canDeliver($adminIdentity, [
            'user_id'          => 1,
            'event_type'       => EventAuthorizer::HR_SUPPORT_SUBMITTED,
            'recipient_role'   => 'admin',
            'recipient_status' => 'Active',
        ]));
        $this->assertFalse($authorizer->canDeliver($hrIdentity, [
            'user_id'          => 2,
            'event_type'       => EventAuthorizer::HR_SUPPORT_SUBMITTED,
            'recipient_role'   => 'hr',
            'recipient_status' => 'Active',
        ]));
        $this->assertTrue($authorizer->canDeliver($hrIdentity, [
            'user_id'                  => 2,
            'event_type'               => EventAuthorizer::HR_SUPPORT_ADMIN_RESPONDED,
            'recipient_role'           => 'hr',
            'recipient_status'         => 'Active',
            'hr_support_owner_matches' => 1,
        ]));
        $this->assertFalse($authorizer->canDeliver($hrIdentity, [
            'user_id'                  => 2,
            'event_type'               => EventAuthorizer::HR_SUPPORT_ADMIN_RESPONDED,
            'recipient_role'           => 'hr',
            'recipient_status'         => 'Active',
            'hr_support_owner_matches' => 0,
        ]));
        $this->assertFalse($authorizer->canDeliver($empIdentity, [
            'user_id'          => 3,
            'event_type'       => EventAuthorizer::HR_SUPPORT_ADMIN_RESPONDED,
            'recipient_role'   => 'employee',
            'recipient_status' => 'Active',
        ]));

        $adminPayload = NotificationPayload::fromRow([
            'id'             => 10,
            'event_type'     => EventAuthorizer::HR_SUPPORT_SUBMITTED,
            'entity_id'      => 7,
            'hr_support_ref' => 'HR-SUP-2026-AB23CD45',
            'recipient_role' => 'admin',
            'created_ts'     => time(),
        ]);
        $this->assertNotNull($adminPayload);
        $this->assertSame('admin/support/tickets/HR-SUP-2026-AB23CD45', $adminPayload['data']['link']);

        $hrPayload = NotificationPayload::fromRow([
            'id'             => 11,
            'event_type'     => EventAuthorizer::HR_SUPPORT_INFO_REQUESTED,
            'entity_id'      => 7,
            'hr_support_ref' => 'HR-SUP-2026-AB23CD45',
            'recipient_role' => 'hr',
            'created_ts'     => time(),
        ]);
        $this->assertNotNull($hrPayload);
        $this->assertSame('hr/help/tickets/HR-SUP-2026-AB23CD45', $hrPayload['data']['link']);

        Session::set('user', ['id' => 1, 'role' => 'admin', 'status' => 'Active']);
        $admDest = \App\Controllers\DashboardController::resolveNotificationDestination([
            'event_type' => EventAuthorizer::HR_SUPPORT_SUBMITTED,
            'entity_id'  => 7,
            'title'      => 'New HR Support Ticket (HR-SUP-2026-AB23CD45)',
        ]);
        $this->assertNotNull($admDest);
        $this->assertSame('/admin/support/tickets/HR-SUP-2026-AB23CD45', $admDest['path']);

        Session::set('user', ['id' => 2, 'role' => 'hr', 'status' => 'Active']);
        $hrDest = \App\Controllers\DashboardController::resolveNotificationDestination([
            'event_type' => EventAuthorizer::HR_SUPPORT_ADMIN_RESPONDED,
            'entity_id'  => 7,
            'title'      => 'Admin Responded (HR-SUP-2026-AB23CD45)',
        ]);
        $this->assertNotNull($hrDest);
        $this->assertSame('/hr/help/tickets/HR-SUP-2026-AB23CD45', $hrDest['path']);
    }

    public function test_route_definitions_enforce_csrf_and_least_privilege_middleware(): void
    {
        $routesSource = (string)file_get_contents(dirname(__DIR__, 2) . '/public/index.php');

        $expectedFragments = [
            "\$router->get('/hr/help'",
            "\$router->post('/hr/help/tickets'",
            "\$router->get('/hr/help/tickets/{ref}'",
            "\$router->post('/hr/help/tickets/{ref}/reply'",
            "\$router->post('/hr/help/tickets/{ref}/confirm-resolution'",
            "\$router->post('/hr/help/tickets/{ref}/reopen'",
            "\$router->get('/hr/help/attachments/{id}'",
            "\$router->get('/admin/support'",
            "\$router->get('/admin/support/tickets/{ref}'",
            "\$router->post('/admin/support/tickets/{ref}/assign'",
            "\$router->post('/admin/support/tickets/{ref}/triage'",
            "\$router->post('/admin/support/tickets/{ref}/respond'",
            "\$router->get('/admin/support/attachments/{id}'",
            "new \\Core\\PermissionMiddleware('hr_support.submit')",
            "new \\Core\\PermissionMiddleware('hr_support.admin_manage')",
        ];

        foreach ($expectedFragments as $fragment) {
            $this->assertStringContainsString($fragment, $routesSource);
        }
    }
}
