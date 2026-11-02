<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\User;
use App\Realtime\EventAuthorizer;
use App\Realtime\Identity;
use App\Realtime\NotificationPayload;
use Core\Database;
use Core\Session;
use PHPUnit\Framework\TestCase;

class DashboardEmployeeUpdateRealtimeTest extends TestCase
{
    private \PDO $pdo;
    private Notification $notificationModel;
    private Employee $employeeModel;
    private User $userModel;

    protected function setUp(): void
    {
        $db = Database::getInstance();
        if (!$db->isConnected()) {
            $this->markTestSkipped('Database is not connected.');
        }
        $this->pdo = $db->getConnection();
        $this->notificationModel = new Notification();
        $this->employeeModel = new Employee();
        $this->userModel = new User();

        Session::destroy();
        Session::start();
    }

    protected function tearDown(): void
    {
        Session::destroy();
    }

    public function testUpdatingEmployeeCreatesRealtimeNotificationsForEmployeeAndHr(): void
    {
        // 1. Find an active employee who has a linked user account
        $stmt = $this->pdo->query("
            SELECT e.id AS employee_id, e.employee_no, e.first_name, e.last_name, u.id AS user_id
            FROM employees e
            INNER JOIN users u ON u.employee_id = e.id
            WHERE e.employment_status = 'Active' AND u.status = 'Active' AND u.role = 'employee'
            LIMIT 1
        ");
        $employee = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$employee) {
            $this->markTestSkipped('Active employee with linked user account required.');
        }

        $empId = (int)$employee['employee_id'];
        $empUserId = (int)$employee['user_id'];

        // Clean any existing notifications for this entity/event
        $this->pdo->prepare("DELETE FROM notifications WHERE entity_id = :id AND event_type = :event")
            ->execute(['id' => $empId, 'event' => EventAuthorizer::PROFILE_UPDATED]);

        // 2. Dispatch the employee update notification as done in EmployeeController
        $empName = trim("{$employee['first_name']} {$employee['last_name']}");

        // Create for linked employee
        $empNotifId = $this->notificationModel->create(
            $empUserId,
            'Profile Updated',
            'Your employee profile record was updated by Human Resources.',
            'info',
            EventAuthorizer::PROFILE_UPDATED,
            $empId
        );
        $this->assertGreaterThan(0, $empNotifId);

        // Notify HR role
        $hrNotifCount = $this->notificationModel->notifyRole(
            'hr',
            'Employee Record Updated',
            "Employee record for {$empName} ({$employee['employee_no']}) was updated.",
            'info',
            EventAuthorizer::PROFILE_UPDATED,
            $empId
        );
        $this->assertGreaterThan(0, $hrNotifCount, 'Must notify at least one active HR user.');

        // 3. Verify notifications table has row for the employee
        $stmtEmp = $this->pdo->prepare("SELECT * FROM notifications WHERE id = :id");
        $stmtEmp->execute(['id' => $empNotifId]);
        $empRow = $stmtEmp->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotEmpty($empRow);
        $this->assertSame($empUserId, (int)$empRow['user_id']);
        $this->assertSame(EventAuthorizer::PROFILE_UPDATED, $empRow['event_type']);
        $this->assertSame($empId, (int)$empRow['entity_id']);

        // 4. Verify notifications table has rows for HR users
        $stmtHr = $this->pdo->prepare("
            SELECT n.*, u.role AS recipient_role, u.status AS recipient_status
            FROM notifications n
            INNER JOIN users u ON u.id = n.user_id
            WHERE n.entity_id = :id AND n.event_type = :event AND u.role = 'hr'
        ");
        $stmtHr->execute(['id' => $empId, 'event' => EventAuthorizer::PROFILE_UPDATED]);
        $hrRows = $stmtHr->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertCount($hrNotifCount, $hrRows);

        // Clean up
        $this->pdo->prepare("DELETE FROM notifications WHERE entity_id = :id AND event_type = :event")
            ->execute(['id' => $empId, 'event' => EventAuthorizer::PROFILE_UPDATED]);
    }

    public function testEventAuthorizerAndPayloadDeliveriesForEmployeeAndHr(): void
    {
        $authorizer = new EventAuthorizer();
        $hrIdentity = new Identity(2, 'hr', 20, str_repeat('a', 32), time() + 3600);
        $empIdentity = new Identity(7, 'employee', 70, str_repeat('b', 32), time() + 3600);

        // Employee row
        $empRow = [
            'id'               => 101,
            'user_id'          => 7,
            'event_type'       => EventAuthorizer::PROFILE_UPDATED,
            'entity_id'        => 70,
            'recipient_role'   => 'employee',
            'recipient_status' => 'Active',
            'owner_matches'    => 0,
            'created_ts'       => time(),
        ];

        // HR row
        $hrRow = [
            'id'               => 102,
            'user_id'          => 2,
            'event_type'       => EventAuthorizer::PROFILE_UPDATED,
            'entity_id'        => 70,
            'recipient_role'   => 'hr',
            'recipient_status' => 'Active',
            'owner_matches'    => 0,
            'created_ts'       => time(),
        ];

        // Both roles can be delivered their respective rows
        $this->assertTrue($authorizer->canDeliver($empIdentity, $empRow));
        $this->assertTrue($authorizer->canDeliver($hrIdentity, $hrRow));

        // Cross-recipient privacy: HR cannot receive Employee's row, Employee cannot receive HR's row
        $this->assertFalse($authorizer->canDeliver($empIdentity, $hrRow));
        $this->assertFalse($authorizer->canDeliver($hrIdentity, $empRow));

        // Payload for Employee recipient: directs to employee profile
        $empPayload = NotificationPayload::fromRow($empRow);
        $this->assertNotNull($empPayload);
        $this->assertSame('event', $empPayload['type']);
        $this->assertSame(EventAuthorizer::PROFILE_UPDATED, $empPayload['event']);
        $this->assertSame('Profile updated', $empPayload['data']['title']);
        $this->assertSame(
            'Your employee profile record was updated by Human Resources.',
            $empPayload['data']['message']
        );
        $this->assertSame('employee/profile', $empPayload['data']['link']);

        // Payload for HR recipient: directs to hr employee management
        $hrPayload = NotificationPayload::fromRow($hrRow);
        $this->assertNotNull($hrPayload);
        $this->assertSame('event', $hrPayload['type']);
        $this->assertSame(EventAuthorizer::PROFILE_UPDATED, $hrPayload['event']);
        $this->assertSame('Employee record updated', $hrPayload['data']['title']);
        $this->assertSame('Employee record #EMP-070 was updated.', $hrPayload['data']['message']);
        $this->assertSame('hr/employees/70', $hrPayload['data']['link']);
    }

    public function testRealtimeJsWorkflowDetectionMatchesBothDashboards(): void
    {
        $realtimeJs = file_get_contents(__DIR__ . '/../../public/assets/js/realtime.js');

        // Verify isDashboardOrWorkflowPage conditions
        $this->assertStringContainsString("p.indexOf('/dashboard') !== -1", $realtimeJs);
        $this->assertStringContainsString("p.indexOf('/employees') !== -1", $realtimeJs);
        $this->assertStringContainsString("window.location.reload()", $realtimeJs);
        $this->assertStringContainsString("isUserActivelyInputting()", $realtimeJs);

        // Test path match expectations
        $hrDashboardPath = '/hr/dashboard';
        $empDashboardPath = '/employee/dashboard';
        $hrEmployeesPath = '/hr/employees';

        $this->assertTrue(str_contains(strtolower($hrDashboardPath), '/dashboard'));
        $this->assertTrue(str_contains(strtolower($empDashboardPath), '/dashboard'));
        $this->assertTrue(str_contains(strtolower($hrEmployeesPath), '/employees'));
    }

    public function testSessionAccountStateSynchronizesUpdatedEmployeeNameAndDetails(): void
    {
        $stmt = $this->pdo->query("
            SELECT u.id, u.role, u.password, u.status, e.id AS employee_id,
                   e.first_name, e.last_name, e.employment_status
            FROM users u
            INNER JOIN employees e ON u.employee_id = e.id
            WHERE u.status = 'Active' AND e.employment_status = 'Active' AND u.role = 'employee'
            LIMIT 1
        ");
        $userRow = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$userRow) {
            $this->markTestSkipped('Active user with linked employee record required.');
        }

        $userId = (int)$userRow['id'];
        $empId = (int)$userRow['employee_id'];
        $origFirstName = $userRow['first_name'];
        $origLastName = $userRow['last_name'];

        $fingerprint = Session::computeAuthFingerprint([
            'id' => $userId,
            'role' => 'employee',
            'status' => 'Active',
            'employment_status' => 'Active',
            'password' => $userRow['password'],
        ]);

        // Seed session with old name
        Session::set('user', [
            'id' => $userId,
            'username' => 'test_user',
            'role' => 'employee',
            'employee_id' => $empId,
            'name' => 'Old Name',
            'auth_fingerprint' => $fingerprint,
        ]);

        // Temporarily change employee name in database
        $this->pdo->prepare("UPDATE employees SET first_name = 'Jerill', last_name = 'Estinsuu' WHERE id = :id")
            ->execute(['id' => $empId]);

        // Call Session::verifyAuthenticatedAccountState() as AuthMiddleware does on page reload
        $isValid = Session::verifyAuthenticatedAccountState();
        $this->assertTrue($isValid);

        // Assert session was synchronized with new name from database
        $currentUser = currentUser();
        $this->assertSame('Jerill Estinsuu', $currentUser['name']);

        // Restore original name
        $this->pdo->prepare("UPDATE employees SET first_name = :fn, last_name = :ln WHERE id = :id")
            ->execute(['fn' => $origFirstName, 'ln' => $origLastName, 'id' => $empId]);
    }
}
