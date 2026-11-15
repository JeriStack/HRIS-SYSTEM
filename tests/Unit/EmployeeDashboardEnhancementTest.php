<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\DashboardController;
use App\Controllers\NotificationController;
use App\Models\Leave;
use App\Models\Notification;
use Core\Session;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class EmployeeDashboardEnhancementTest extends TestCase
{
    private mixed $previousUser = null;
    private mixed $previousServer = null;
    private mixed $previousPost = null;
    private mixed $previousGet = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousUser = Session::get('user');
        $this->previousServer = $_SERVER;
        $this->previousPost = $_POST;
        $this->previousGet = $_GET;

        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $_SERVER['REQUEST_URI'] = '/employee/dashboard';
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        if ($this->previousUser === null) {
            unset($_SESSION['user']);
        } else {
            Session::set('user', $this->previousUser);
        }
        $_SERVER = $this->previousServer;
        $_POST = $this->previousPost;
        $_GET = $this->previousGet;
        parent::tearDown();
    }

    private function createSqliteLeaveSchema(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('
            CREATE TABLE leave_types (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                leave_name TEXT NOT NULL,
                description TEXT NULL
            );
            CREATE TABLE leave_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL,
                leave_type_id INTEGER NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                number_of_days INTEGER NOT NULL,
                reason TEXT NULL,
                rejection_reason TEXT NULL,
                status TEXT NOT NULL,
                approved_by INTEGER NULL,
                approved_at TEXT NULL,
                created_at TEXT NOT NULL
            );
        ');
        $pdo->exec("
            INSERT INTO leave_types (id, leave_name, description) VALUES
            (1, 'Vacation Leave', 'Annual vacation leave'),
            (2, 'Sick Leave', 'Medical leave'),
            (3, 'Emergency Leave', 'Urgent personal leave');
        ");

        return $pdo;
    }

    public function testRecentRequestsAreStrictlyScopedToEmployeeAndCappedAtThree(): void
    {
        $pdo = $this->createSqliteLeaveSchema();
        $pdo->exec("
            INSERT INTO leave_requests (id, employee_id, leave_type_id, start_date, end_date, number_of_days, reason, status, created_at) VALUES
            (1, 10, 1, '2026-10-01', '2026-10-02', 2, 'Old trip', 'Approved', '2026-09-01 09:00:00'),
            (2, 10, 2, '2026-10-10', '2026-10-10', 1, 'Checkup', 'Rejected', '2026-09-10 09:00:00'),
            (3, 10, 3, '2026-10-15', '2026-10-15', 1, 'Errand', 'Cancelled', '2026-09-15 09:00:00'),
            (4, 10, 1, '2026-11-20', '2026-11-22', 3, 'Family', 'Pending', '2026-11-01 10:00:00'),
            (5, 99, 1, '2026-11-25', '2026-11-26', 2, 'Other user secret', 'Pending', '2026-11-10 12:00:00');
        ");

        $model = new Leave($pdo);
        $rows = $model->getRecentByEmployee(10, 3);

        $this->assertCount(3, $rows);
        $this->assertSame([4, 3, 2], array_map(static fn(array $r): int => (int)$r['id'], $rows));
        $this->assertSame('2026-11-01 10:00:00', $rows[0]['created_at']);
    }

    public function testUpcomingApprovedLeaveExcludesNonApprovedPastAndOtherEmployeesSortedAscending(): void
    {
        $pdo = $this->createSqliteLeaveSchema();
        $pdo->exec("
            INSERT INTO leave_requests (id, employee_id, leave_type_id, start_date, end_date, number_of_days, reason, status, created_at) VALUES
            (1, 10, 1, '2026-11-10', '2026-11-11', 2, 'Past approved', 'Approved', '2026-10-01 09:00:00'),
            (2, 10, 2, '2026-12-20', '2026-12-22', 3, 'Dec later', 'Approved', '2026-10-02 09:00:00'),
            (3, 10, 1, '2026-11-18', '2026-11-19', 2, 'Nearest upcoming', 'Approved', '2026-10-03 09:00:00'),
            (4, 10, 3, '2026-11-15', '2026-11-15', 1, 'Today starts', 'Approved', '2026-10-04 09:00:00'),
            (5, 10, 1, '2026-12-01', '2026-12-03', 3, 'Pending request', 'Pending', '2026-10-05 09:00:00'),
            (6, 10, 1, '2026-11-16', '2026-11-17', 2, 'Rejected req', 'Rejected', '2026-10-06 09:00:00'),
            (7, 10, 1, '2026-11-17', '2026-11-17', 1, 'Cancelled req', 'Cancelled', '2026-10-07 09:00:00'),
            (8, 10, 1, '2026-12-28', '2026-12-30', 3, 'Fourth future', 'Approved', '2026-10-08 09:00:00'),
            (9, 77, 1, '2026-11-16', '2026-11-16', 1, 'Other emp', 'Approved', '2026-10-09 09:00:00');
        ");

        $model = new Leave($pdo);
        $rows = $model->getUpcomingApprovedByEmployee(10, '2026-11-15', 3);

        $this->assertCount(3, $rows);
        // Should be strictly Approved >= 2026-11-15 ordered ascending by nearest start_date
        $this->assertSame([4, 3, 2], array_map(static fn(array $r): int => (int)$r['id'], $rows));
        foreach ($rows as $row) {
            $this->assertSame('Approved', $row['status']);
        }
    }

    public function testResolveNotificationDestinationHandlesKnownAndUnsafeMetadata(): void
    {
        $approved = DashboardController::resolveNotificationDestination([
            'event_type' => 'leave_request.approved',
            'entity_id'  => 45,
        ]);
        $this->assertSame(['path' => '/leaves/45', 'label' => 'View request'], $approved);

        $rejected = DashboardController::resolveNotificationDestination([
            'event_type' => 'leave_request.rejected',
            'entity_id'  => '12',
        ]);
        $this->assertSame(['path' => '/leaves/12', 'label' => 'View request'], $rejected);

        $profile = DashboardController::resolveNotificationDestination([
            'event_type' => 'employee.profile_updated',
            'entity_id'  => 10,
        ]);
        $this->assertSame(['path' => '/employee/profile', 'label' => 'View profile'], $profile);

        $zeroEntity = DashboardController::resolveNotificationDestination([
            'event_type' => 'leave_request.approved',
            'entity_id'  => 0,
        ]);
        $this->assertNull($zeroEntity);

        $announcement = DashboardController::resolveNotificationDestination([
            'event_type' => null,
            'entity_id'  => null,
        ]);
        $this->assertNull($announcement);
    }

    public function testEmployeeDashboardIsolatesSectionFailuresAndUsesAccuratePendingTotal(): void
    {
        Session::set('user', [
            'id'          => 5,
            'employee_id' => 10,
            'username'    => 'jdoe',
            'name'        => 'Jane Doe',
            'employee_no' => 'EMP-010',
            'role'        => 'employee',
        ]);

        $stmtEmp = $this->createMock(PDOStatement::class);
        $stmtEmp->expects($this->once())
            ->method('execute')
            ->with(['emp_id' => 10])
            ->willReturn(true);
        $stmtEmp->expects($this->once())
            ->method('fetch')
            ->willReturn([
                'first_name'      => 'Jane',
                'last_name'       => 'Doe',
                'employee_no'     => 'EMP-010',
                'department_name' => 'Engineering',
                'position_name'   => 'Software Engineer',
            ]);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmtEmp);

        $notifMock = $this->createMock(Notification::class);
        $notifMock->expects($this->once())
            ->method('getByUser')
            ->with(5, 3)
            ->willReturn([
                [
                    'id'         => 101,
                    'title'      => 'Leave request approved',
                    'message'    => 'Vacation leave approved.',
                    'type'       => 'success',
                    'event_type' => 'leave_request.approved',
                    'entity_id'  => 33,
                    'is_read'    => 0,
                    'created_at' => '2026-11-15 08:30:00',
                ],
            ]);
        $notifMock->expects($this->once())
            ->method('getUnreadCount')
            ->with(5)
            ->willReturn(1);

        $leaveMock = $this->createMock(Leave::class);
        $leaveMock->expects($this->once())
            ->method('getBalancesByEmployee')
            ->willThrowException(new \RuntimeException('SQLSTATE[42S02]: Base table leave_balances not found'));
        $leaveMock->expects($this->once())
            ->method('countPendingByEmployee')
            ->with(10)
            ->willReturn(6);
        $leaveMock->expects($this->once())
            ->method('getRecentByEmployee')
            ->with(10, 3)
            ->willReturn([
                ['id' => 91, 'status' => 'Approved', 'leave_name' => 'Vacation Leave'],
                ['id' => 90, 'status' => 'Pending', 'leave_name' => 'Sick Leave'],
                ['id' => 89, 'status' => 'Rejected', 'leave_name' => 'Emergency Leave'],
            ]);
        $leaveMock->expects($this->once())
            ->method('getUpcomingApprovedByEmployee')
            ->willReturn([
                ['id' => 91, 'status' => 'Approved', 'start_date' => '2026-12-01'],
            ]);

        $captured = [];
        $controller = new class ($pdo, $notifMock, $leaveMock, $captured) extends DashboardController {
            /** @var array<string, mixed> */
            private array $store;

            /** @param array<string, mixed> $store */
            public function __construct(PDO $pdo, Notification $n, Leave $l, array &$store)
            {
                parent::__construct($pdo, $n, $l);
                $this->store = &$store;
            }

            protected function view(string $viewPath, array $data = [], ?string $layout = 'main'): void
            {
                $this->store = ['view' => $viewPath, 'data' => $data, 'layout' => $layout];
            }
        };

        $controller->employeeDashboard();

        $this->assertSame('dashboard.employee', $captured['view']);
        $data = $captured['data'];

        // Total pending comes from countPendingByEmployee (6), NOT sliced 3 rows (1 pending)
        $this->assertSame(6, $data['pendingRequestsCount']);
        $this->assertCount(3, $data['requests']);
        $this->assertCount(1, $data['upcomingApprovedLeaves']);
        $this->assertSame('/leaves/33', $data['recentNotifications'][0]['destination_path']);

        // Balances failure is isolated cleanly with no raw SQL details exposed
        $this->assertNotNull($data['sectionErrors']['balances']);
        $this->assertStringNotContainsString('SQLSTATE', $data['sectionErrors']['balances']);
        $this->assertNull($data['sectionErrors']['requests']);
        $this->assertNull($data['sectionErrors']['upcoming']);
        $this->assertNull($data['sectionErrors']['notifications']);
    }

    public function testNotificationMarkReadEnforcesOwnershipAndSafeRedirect(): void
    {
        Session::set('user', ['id' => 11, 'role' => 'employee']);

        $notifMock = $this->createMock(Notification::class);
        $notifMock->expects($this->once())
            ->method('belongsToUser')
            ->with(500, 11)
            ->willReturn(false);
        $notifMock->expects($this->never())
            ->method('markAsRead');

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $controller = new NotificationController($notifMock);

        ob_start();
        $controller->markRead(500);
        $jsonOutput = (string)ob_get_clean();

        $decoded = json_decode($jsonOutput, true);
        $this->assertIsArray($decoded);
        $this->assertSame('error', $decoded['status']);
        $this->assertSame(404, $decoded['code']);
    }

    public function testNotificationMarkReadUpdatesOwnedItemAndReturnsUpdatedUnreadCountJson(): void
    {
        Session::set('user', ['id' => 11, 'role' => 'employee']);

        $notifMock = $this->createMock(Notification::class);
        $notifMock->expects($this->once())->method('belongsToUser')->with(77, 11)->willReturn(true);
        $notifMock->expects($this->once())->method('markAsRead')->with(77, 11)->willReturn(true);
        $notifMock->expects($this->once())->method('getUnreadCount')->with(11)->willReturn(2);

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_POST['redirect_to'] = 'https://malicious.example.test/phish';
        $controller = new NotificationController($notifMock);

        ob_start();
        $controller->markRead(77);
        $jsonOutput = (string)ob_get_clean();

        $decoded = json_decode($jsonOutput, true);
        $this->assertSame('success', $decoded['status']);
        $this->assertSame(77, $decoded['data']['notification_id']);
        $this->assertSame(2, $decoded['data']['unread_count']);
    }

    public function testEmployeeDashboardViewRendersAllSectionsZeroAllocationAndErrorStatesCleanly(): void
    {
        $renderView = static function (array $vars): string {
            extract($vars, EXTR_SKIP);
            ob_start();
            require dirname(__DIR__, 2) . '/app/views/dashboard/employee.php';
            return (string)ob_get_clean();
        };

        $populatedHtml = $renderView([
            'user' => [
                'id'          => 5,
                'name'        => 'Alex <script>alert(1)</script> Reyes',
                'employee_no' => 'EMP-042',
                'position'    => 'QA Analyst',
                'department'  => 'Quality',
            ],
            'leaveCycleYear' => 2026,
            'pendingRequestsCount' => 4,
            'unreadNotificationsCount' => 1,
            'balances' => [
                ['leave_type_id' => 1, 'leave_name' => 'Vacation Leave', 'allocated_days' => 15, 'used_days' => 3, 'remaining_days' => 12],
                ['leave_type_id' => 2, 'leave_name' => 'Bereavement Leave', 'allocated_days' => 0, 'used_days' => 0, 'remaining_days' => 0],
                ['leave_type_id' => 3, 'leave_name' => 'Emergency Leave', 'allocated_days' => 3, 'used_days' => 4, 'remaining_days' => -1],
            ],
            'requests' => [
                ['id' => 10, 'leave_name' => 'Sick Leave', 'start_date' => '2026-11-18', 'end_date' => '2026-11-19', 'number_of_days' => 2, 'status' => 'Pending', 'created_at' => '2026-11-14 09:00:00'],
                ['id' => 9, 'leave_name' => 'Vacation Leave', 'start_date' => '2026-12-01', 'end_date' => '2026-12-03', 'number_of_days' => 3, 'status' => 'Approved', 'created_at' => '2026-11-10 09:00:00'],
                ['id' => 8, 'leave_name' => 'Emergency Leave', 'start_date' => '2026-10-05', 'end_date' => '2026-10-05', 'number_of_days' => 1, 'status' => 'Cancelled', 'created_at' => '2026-10-01 09:00:00'],
            ],
            'upcomingApprovedLeaves' => [
                ['id' => 9, 'leave_name' => 'Vacation Leave', 'start_date' => date('Y-m-d', strtotime('+3 days')), 'end_date' => date('Y-m-d', strtotime('+5 days')), 'number_of_days' => 3, 'status' => 'Approved'],
            ],
            'recentNotifications' => [
                ['id' => 50, 'title' => 'Leave Approved', 'message' => 'Your vacation leave was approved.', 'type' => 'success', 'is_read' => 0, 'created_at' => '2026-11-14 14:15:00', 'destination_path' => '/leaves/9', 'destination_label' => 'View request'],
                ['id' => 49, 'title' => 'HR Notice', 'message' => 'Holiday memo posted.', 'type' => 'info', 'is_read' => 1, 'created_at' => '2026-11-12 08:00:00', 'destination_path' => null, 'destination_label' => null],
            ],
            'sectionErrors' => [
                'profile' => null, 'balances' => null, 'requests' => null, 'upcoming' => null, 'notifications' => null,
            ],
        ]);

        // XSS escaped, strict order of headings verified
        $this->assertStringNotContainsString('<script>alert(1)</script>', $populatedHtml);
        $posBalances = strpos($populatedHtml, 'My Leave Balances');
        $posRequests = strpos($populatedHtml, 'Recent Leave Requests');
        $posUpcoming = strpos($populatedHtml, 'Upcoming Approved Leave');
        $posNotifs = strpos($populatedHtml, 'Recent Notifications');
        $this->assertNotFalse($posBalances);
        $this->assertTrue($posBalances < $posRequests && $posRequests < $posUpcoming && $posUpcoming < $posNotifs);

        // Zero-allocation & negative deficit guards rendered accurately
        $this->assertStringContainsString('Unallocated', $populatedHtml);
        $this->assertStringContainsString('Overdrawn', $populatedHtml);
        $this->assertStringContainsString('-1', $populatedHtml);
        $this->assertStringContainsString('4', $populatedHtml); // pendingRequestsCount = 4
        $this->assertStringContainsString('Nov 14, 2026', $populatedHtml); // submission date displayed
        $this->assertStringContainsString('js-dash-cancel-leave', $populatedHtml); // pending cancel present
        $this->assertStringContainsString('js-dash-mark-read-form', $populatedHtml);
        $this->assertStringNotContainsString('Balance information updated:', $populatedHtml);
        // Combined summary separates total from categories without claiming interchangeability
        $this->assertStringContainsString('Each leave category is tracked separately and does not transfer across types.', $populatedHtml);
        // Navigation links match existing HRIS routes
        $this->assertStringContainsString('/employee/leave-requests?status=Pending', $populatedHtml);
        $this->assertStringContainsString('/employee/leave-requests?status=Approved', $populatedHtml);
        $this->assertStringContainsString('/employee/leave-balances', $populatedHtml);
        $this->assertStringContainsString('/leaves/9', $populatedHtml);
        // Width on overdrawn category (4/3 = 133%) must be clamped to 100%
        $this->assertStringNotContainsString('width: 133%', $populatedHtml);
        // Unique HTML id attribute check (match leading whitespace before id=)
        preg_match_all('/\sid="([^"]+)"/', $populatedHtml, $idMatches);
        $this->assertNotEmpty($idMatches[1]);
        $this->assertCount(count(array_unique($idMatches[1])), $idMatches[1], 'Duplicate DOM IDs detected');
        // Summary KPI cards omit bottom footer strip for clean numeric focus
        $this->assertStringNotContainsString('emp-kpi-footer', $populatedHtml);

        // Section error isolation & empty states
        $errorHtml = $renderView([
            'user' => ['id' => 5, 'name' => 'Alex Reyes', 'employee_no' => 'EMP-042'],
            'leaveCycleYear' => 2026,
            'balances' => [],
            'requests' => [],
            'upcomingApprovedLeaves' => [],
            'recentNotifications' => [],
            'pendingRequestsCount' => 0,
            'unreadNotificationsCount' => 0,
            'sectionErrors' => [
                'profile' => null,
                'balances' => 'Unable to load leave balances right now.',
                'requests' => null,
                'upcoming' => null,
                'notifications' => 'Unable to load recent notifications right now.',
            ],
        ]);

        $this->assertStringContainsString('Leave Balances Temporarily Unavailable', $errorHtml);
        $this->assertStringContainsString('No Leave Requests Yet', $errorHtml);
        $this->assertStringContainsString('No Upcoming Approved Leave', $errorHtml);
        $this->assertStringContainsString('Notifications Temporarily Unavailable', $errorHtml);
    }

    public function testEmployeeCancelRequestOnlyCancelsOwnPendingRequests(): void
    {
        $pdo = $this->createSqliteLeaveSchema();
        $pdo->exec("
            INSERT INTO leave_requests (id, employee_id, leave_type_id, start_date, end_date, number_of_days, reason, status, created_at) VALUES
            (1, 10, 1, '2026-11-20', '2026-11-21', 2, 'Own pending', 'Pending', '2026-11-01 09:00:00'),
            (2, 10, 1, '2026-12-01', '2026-12-02', 2, 'Own approved', 'Approved', '2026-11-01 09:00:00'),
            (3, 99, 1, '2026-11-25', '2026-11-25', 1, 'Foreign pending', 'Pending', '2026-11-01 09:00:00');
        ");

        $model = new Leave($pdo);
        $this->assertTrue($model->cancelRequest(1, 10));
        $this->assertFalse($model->cancelRequest(2, 10), 'Approved leave cannot be cancelled via employee pending cancel.');
        $this->assertFalse($model->cancelRequest(3, 10), 'Foreign leave request cannot be cancelled.');

        $status1 = (string)$pdo->query('SELECT status FROM leave_requests WHERE id = 1')->fetchColumn();
        $status3 = (string)$pdo->query('SELECT status FROM leave_requests WHERE id = 3')->fetchColumn();
        $this->assertSame('Cancelled', $status1);
        $this->assertSame('Pending', $status3);
    }
}
