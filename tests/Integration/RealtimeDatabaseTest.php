<?php

namespace Tests\Integration;

use App\Models\Leave;
use App\Models\Notification;
use App\Models\RealtimeTicket;
use App\Models\User;
use App\Realtime\EventAuthorizer;
use App\Realtime\Identity;
use App\Realtime\NotificationOutbox;
use App\Realtime\PdoRealtimeStore;
use App\Realtime\RealtimeConfig;
use App\Services\LeaveNotificationService;
use App\Services\RealtimeTicketService;
use Core\Database;
use Core\Session;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Exercises the real MySQL schema: ticket issuance/consumption, revocation,
 * commit-only visibility of outbox rows, and transactional leave notifications.
 * Every row created here is removed in tearDown(); existing data is untouched.
 */
class RealtimeDatabaseTest extends TestCase
{
    private PDO $pdo;
    private PdoRealtimeStore $store;

    /** @var array{hr:array, employee:array, other:array} */
    private array $fixture;
    private int $leaveTypeId;

    /** @var string[] */
    private array $sessionRefs = [];
    /** @var int[] */
    private array $leaveIds = [];
    /** @var int[] */
    private array $notificationIds = [];

    protected function setUp(): void
    {
        $db = Database::getInstance();
        if (!$db->isConnected()) {
            $this->markTestSkipped('Database is not connected in this environment.');
        }
        $this->pdo = $db->getConnection();

        $tables = $this->pdo->query("SHOW TABLES LIKE 'realtime_tickets'")->fetchAll();
        $columns = $this->pdo->query("SHOW COLUMNS FROM notifications LIKE 'event_type'")->fetchAll();
        if (!$tables || !$columns) {
            $this->markTestSkipped('Realtime migration not applied (run php bin/migrate.php).');
        }

        $hr = $this->pdo->query("SELECT id, employee_id FROM users WHERE role = 'hr' AND status = 'Active' ORDER BY id LIMIT 1")->fetch();
        $employees = $this->pdo->query(
            "SELECT id, employee_id FROM users WHERE role = 'employee' AND status = 'Active' AND employee_id IS NOT NULL ORDER BY id LIMIT 2"
        )->fetchAll();
        $leaveType = $this->pdo->query("SELECT id FROM leave_types WHERE status = 'Active' ORDER BY id LIMIT 1")->fetchColumn();

        if (!$hr || count($employees) < 2 || !$leaveType) {
            $this->markTestSkipped('Seed data with one HR user, two employee users and an active leave type is required.');
        }

        $this->fixture = ['hr' => $hr, 'employee' => $employees[0], 'other' => $employees[1]];
        $this->leaveTypeId = (int)$leaveType;
        $this->store = PdoRealtimeStore::fromEnvironment();

        Session::destroy();
        Session::start();
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        foreach ($this->leaveIds as $leaveId) {
            $this->pdo->prepare("DELETE FROM activity_logs WHERE entity_id = ? AND entity_type = 'LeaveRequest'")->execute([(string)$leaveId]);
            $this->pdo->prepare("DELETE FROM notifications WHERE entity_id = ? AND event_type LIKE 'leave\\_request.%'")->execute([$leaveId]);
            $this->pdo->prepare("DELETE FROM leave_requests WHERE id = ?")->execute([$leaveId]);
        }
        foreach ($this->notificationIds as $id) {
            $this->pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$id]);
        }
        $ref = Session::get(RealtimeTicketService::SESSION_REF_KEY);
        if (is_string($ref)) {
            $this->sessionRefs[] = $ref;
        }
        foreach (array_unique($this->sessionRefs) as $ref) {
            $this->pdo->prepare("DELETE FROM realtime_tickets WHERE session_ref = ?")->execute([$ref]);
        }

        Session::destroy();
    }

    private function newRef(): string
    {
        return $this->sessionRefs[] = bin2hex(random_bytes(16));
    }

    private function loginAs(array $user, string $role, ?int $lastActivity = null): void
    {
        Session::set('user', [
            'id'          => (int)$user['id'],
            'username'    => 'realtime-test',
            'role'        => $role,
            'employee_id' => $user['employee_id'] !== null ? (int)$user['employee_id'] : null,
            'name'        => 'Realtime Test',
            'login_time'  => time(),
        ]);
        Session::set('last_activity', $lastActivity ?? time());
    }

    private function createPendingLeave(int $employeeId): int
    {
        $id = (new Leave())->createRequest([
            'employee_id'    => $employeeId,
            'leave_type_id'  => $this->leaveTypeId,
            'start_date'     => '2099-01-05',
            'end_date'       => '2099-01-06',
            'number_of_days' => 2,
            'reason'         => 'Automated realtime integration test',
        ]);
        return $this->leaveIds[] = $id;
    }

    // ------------------------------------------------------------------
    // Tickets
    // ------------------------------------------------------------------

    public function test_ticket_is_single_use_and_identity_comes_from_users_table(): void
    {
        $model = new RealtimeTicket();
        $raw = $model->issue((int)$this->fixture['employee']['id'], $this->newRef(), time() + 600, 30, '127.0.0.1');

        $stored = $this->pdo->query("SELECT token_hash FROM realtime_tickets WHERE token_hash = " . $this->pdo->quote(hash('sha256', $raw)))->fetchColumn();
        $this->assertSame(hash('sha256', $raw), $stored, 'Only the SHA-256 hash is persisted.');
        $this->assertSame(0, (int)$this->pdo->query("SELECT COUNT(*) FROM realtime_tickets WHERE token_hash = " . $this->pdo->quote($raw))->fetchColumn());

        $identity = $this->store->consumeTicket(hash('sha256', $raw));
        $this->assertInstanceOf(Identity::class, $identity);
        $this->assertSame((int)$this->fixture['employee']['id'], $identity->userId);
        $this->assertSame('employee', $identity->role);

        $this->assertNull($this->store->consumeTicket(hash('sha256', $raw)), 'Second use must fail.');
    }

    public function test_expired_and_session_expired_tickets_are_rejected(): void
    {
        $model = new RealtimeTicket();
        $expired = $model->issue((int)$this->fixture['hr']['id'], $this->newRef(), time() + 600, -1, null);
        $deadSession = $model->issue((int)$this->fixture['hr']['id'], $this->newRef(), time() - 5, 30, null);

        $this->assertNull($this->store->consumeTicket(hash('sha256', $expired)));
        $this->assertNull($this->store->consumeTicket(hash('sha256', $deadSession)));
        $this->assertNull($this->store->consumeTicket(hash('sha256', 'not-a-ticket')));
    }

    public function test_logout_revocation_is_detected_for_live_connections(): void
    {
        $model = new RealtimeTicket();
        $ref = $this->newRef();
        $raw = $model->issue((int)$this->fixture['employee']['id'], $ref, time() + 600, 30, null);
        $identity = $this->store->consumeTicket(hash('sha256', $raw));
        $this->assertNotNull($identity);

        $sessions = [1 => ['user_id' => $identity->userId, 'role' => $identity->role, 'session_ref' => $ref]];
        $this->assertSame([], $this->store->findInvalidSessions($sessions));

        $this->assertGreaterThan(0, $model->revokeSession($ref));
        $this->assertSame([1 => 'revoked'], $this->store->findInvalidSessions($sessions));

        $roleChanged = [2 => ['user_id' => $identity->userId, 'role' => 'hr', 'session_ref' => $this->newRef()]];
        $model->issue($identity->userId, $roleChanged[2]['session_ref'], time() + 600, 30, null);
        $this->assertSame([2 => 'role_changed'], $this->store->findInvalidSessions($roleChanged));
    }

    public function test_ticket_service_requires_fully_authenticated_active_session(): void
    {
        $config = RealtimeConfig::fromEnvironment(['BASE_URL' => 'http://localhost:8080', 'WS_TICKETS_PER_MINUTE' => '2']);
        $service = new RealtimeTicketService(null, null, $config);

        // Pending OTP (password verified, OTP not yet) is NOT authenticated
        Session::set('pending_otp_user_id', (int)$this->fixture['employee']['id']);
        $this->assertSame(401, $service->issueForCurrentSession()['status']);

        // Session role disagreeing with the database is refused
        $this->loginAs($this->fixture['employee'], 'hr');
        $this->assertSame(403, $service->issueForCurrentSession()['status']);

        // Valid employee session
        $this->loginAs($this->fixture['employee'], 'employee');
        $first = $service->issueForCurrentSession();
        $this->assertTrue($first['ok']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first['ticket']);
        // The service derives the browser URL from the live application config
        // (config.base_url), not the injected test config, so assert against the
        // same source rather than a hardcoded host that CI does not provide.
        $expectedUrl = $config->browserUrl((string)config('config.base_url', ''));
        $this->assertSame($expectedUrl, $first['url']);
        $this->assertStringEndsWith('/ws', $first['url']);

        // Ticket identity resolves to the session user
        $identity = $this->store->consumeTicket(hash('sha256', $first['ticket']));
        $this->assertSame((int)$this->fixture['employee']['id'], $identity->userId);

        // Rate limit per browser session
        $this->assertTrue($service->issueForCurrentSession()['ok']);
        $this->assertSame(429, $service->issueForCurrentSession()['status']);
    }

    public function test_ticket_service_refuses_inactive_session(): void
    {
        $service = new RealtimeTicketService(null, null, RealtimeConfig::fromEnvironment(['BASE_URL' => 'http://localhost:8080']));
        $this->loginAs($this->fixture['hr'], 'hr', time() - 7200);
        $this->assertSame(401, $service->issueForCurrentSession()['status']);
    }

    // ------------------------------------------------------------------
    // Outbox: commit-only visibility
    // ------------------------------------------------------------------

    public function test_notifications_are_visible_to_the_socket_service_only_after_commit(): void
    {
        $outbox = new NotificationOutbox($this->store);
        $outbox->initialize();
        $notifications = new Notification();

        // Rolled back: never visible
        $this->pdo->beginTransaction();
        $rolledBackId = $notifications->create((int)$this->fixture['hr']['id'], 'Test', 'Rolled back', 'info', 'test.realtime');
        $this->assertSame([], $outbox->poll(), 'Uncommitted row must be invisible.');
        $this->pdo->rollBack();
        $this->assertSame([], $outbox->poll(), 'Rolled-back row must never be emitted.');

        // Committed: visible exactly once
        $this->pdo->beginTransaction();
        $committedId = $this->notificationIds[] = $notifications->create((int)$this->fixture['hr']['id'], 'Test', 'Committed', 'info', 'test.realtime');
        $this->assertSame([], $outbox->poll());
        $this->pdo->commit();

        $rows = $outbox->poll();
        $this->assertSame([$committedId], array_map('intval', array_column($rows, 'id')));
        $this->assertNotContains($rolledBackId, array_map('intval', array_column($rows, 'id')));
        $this->assertSame([], $outbox->poll());
    }

    // ------------------------------------------------------------------
    // Transactional leave notifications
    // ------------------------------------------------------------------

    public function test_leave_submission_notifies_hr_atomically(): void
    {
        $outbox = new NotificationOutbox($this->store);
        $outbox->initialize();
        $service = new LeaveNotificationService();
        $employeeId = (int)$this->fixture['employee']['employee_id'];
        $activeHr = (int)$this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'hr' AND status = 'Active'")->fetchColumn();

        $leaveId = $this->leaveIds[] = (new Leave())->createRequest([
            'employee_id' => $employeeId, 'leave_type_id' => $this->leaveTypeId,
            'start_date' => '2099-02-02', 'end_date' => '2099-02-03', 'number_of_days' => 2,
            'reason' => 'Automated realtime integration test',
        ], fn (int $id) => $service->leaveSubmitted($id, "Test submission #{$id}"));

        $rows = array_values(array_filter($outbox->poll(), fn ($r) => (int)$r['entity_id'] === $leaveId));
        $this->assertCount($activeHr, $rows, 'One committed notification per active HR user.');
        $authorizer = new EventAuthorizer();
        foreach ($rows as $row) {
            $this->assertSame(EventAuthorizer::LEAVE_CREATED, $row['event_type']);
            $this->assertSame('hr', $row['recipient_role']);
            $hrIdentity = new Identity((int)$row['user_id'], 'hr', null, str_repeat('a', 32), time() + 60);
            $this->assertTrue($authorizer->canDeliver($hrIdentity, $row));
            $employeeIdentity = new Identity((int)$this->fixture['employee']['id'], 'employee', $employeeId, str_repeat('a', 32), time() + 60);
            $this->assertFalse($authorizer->canDeliver($employeeIdentity, $row));
        }
    }

    public function test_failed_submission_leaves_no_request_and_no_notification(): void
    {
        $before = (int)$this->pdo->query("SELECT COALESCE(MAX(id),0) FROM leave_requests")->fetchColumn();

        try {
            (new Leave())->createRequest([
                'employee_id' => (int)$this->fixture['employee']['employee_id'], 'leave_type_id' => $this->leaveTypeId,
                'start_date' => '2099-03-02', 'end_date' => '2099-03-03', 'number_of_days' => 2,
                'reason' => 'Automated realtime integration test',
            ], function (int $id): void {
                $this->leaveIds[] = $id; // cleanup safety if rollback failed
                throw new RuntimeException('notification write failed');
            });
            $this->fail('Exception must propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('notification write failed', $e->getMessage());
        }

        $after = (int)$this->pdo->query("SELECT COUNT(*) FROM leave_requests WHERE id > {$before}")->fetchColumn();
        $this->assertSame(0, $after, 'Leave request must be rolled back with its notifications.');
    }

    public function test_decision_notifies_only_owner_after_commit(): void
    {
        $owner = $this->fixture['employee'];
        $leaveId = $this->createPendingLeave((int)$owner['employee_id']);

        $outbox = new NotificationOutbox($this->store);
        $outbox->initialize();
        $service = new LeaveNotificationService();

        $ok = (new Leave())->updateRequestStatus($leaveId, 'Rejected', (int)$this->fixture['hr']['id'], 'Test rejection',
            fn (array $req, string $status) => $service->leaveDecided($req, $status, 'Rejected in test'));
        $this->assertTrue($ok);

        $rows = array_values(array_filter($outbox->poll(), fn ($r) => (int)$r['entity_id'] === $leaveId));
        $this->assertCount(1, $rows);
        $this->assertSame(EventAuthorizer::LEAVE_REJECTED, $rows[0]['event_type']);
        $this->assertSame((int)$owner['id'], (int)$rows[0]['user_id']);
        $this->assertSame(1, (int)$rows[0]['owner_matches']);

        $authorizer = new EventAuthorizer();
        $ownerIdentity = new Identity((int)$owner['id'], 'employee', (int)$owner['employee_id'], str_repeat('a', 32), time() + 60);
        $otherIdentity = new Identity((int)$this->fixture['other']['id'], 'employee', (int)$this->fixture['other']['employee_id'], str_repeat('a', 32), time() + 60);
        $this->assertTrue($authorizer->canDeliver($ownerIdentity, $rows[0]));
        $this->assertFalse($authorizer->canDeliver($otherIdentity, $rows[0]));

        // Re-processing an already decided request must fail and emit nothing
        $again = (new Leave())->updateRequestStatus($leaveId, 'Approved', (int)$this->fixture['hr']['id'], null,
            fn (array $req, string $status) => $service->leaveDecided($req, $status, 'Should never be written'));
        $this->assertFalse($again);
        $this->assertSame([], array_values(array_filter($outbox->poll(), fn ($r) => (int)$r['entity_id'] === $leaveId)));
    }

    public function test_failed_approval_rolls_back_status_balance_and_notification(): void
    {
        $owner = $this->fixture['employee'];
        $leaveId = $this->createPendingLeave((int)$owner['employee_id']);
        $balanceBefore = $this->pdo->query(
            "SELECT COALESCE(SUM(used_days),0) FROM leave_balances WHERE employee_id = " . (int)$owner['employee_id']
        )->fetchColumn();

        $outbox = new NotificationOutbox($this->store);
        $outbox->initialize();
        $service = new LeaveNotificationService();

        $ok = (new Leave())->updateRequestStatus($leaveId, 'Approved', (int)$this->fixture['hr']['id'], null,
            function (array $req, string $status) use ($service): void {
                $service->leaveDecided($req, $status, 'Approved in test');
                throw new RuntimeException('simulated failure after notification insert');
            });

        $this->assertFalse($ok);
        $this->assertSame('Pending', $this->pdo->query("SELECT status FROM leave_requests WHERE id = {$leaveId}")->fetchColumn());
        $this->assertSame($balanceBefore, $this->pdo->query(
            "SELECT COALESCE(SUM(used_days),0) FROM leave_balances WHERE employee_id = " . (int)$owner['employee_id']
        )->fetchColumn());
        $this->assertSame([], array_values(array_filter($outbox->poll(), fn ($r) => (int)$r['entity_id'] === $leaveId)),
            'A rolled-back approval must not produce a success notification.');
        $this->assertSame(0, (int)$this->pdo->query(
            "SELECT COUNT(*) FROM notifications WHERE entity_id = {$leaveId} AND event_type = 'leave_request.approved'"
        )->fetchColumn());
    }

    public function test_unread_count_is_per_user(): void
    {
        $notifications = new Notification();
        $hrId = (int)$this->fixture['hr']['id'];
        $empId = (int)$this->fixture['employee']['id'];

        $hrBefore = $this->store->unreadCount($hrId);
        $empBefore = $this->store->unreadCount($empId);

        $this->notificationIds[] = $notifications->create($empId, 'Test', 'Unread scoping', 'info');

        $this->assertSame($empBefore + 1, $this->store->unreadCount($empId));
        $this->assertSame($hrBefore, $this->store->unreadCount($hrId));
    }

    public function test_user_model_is_still_usable(): void
    {
        // Guards against regressions in the shared PDO after transactional tests
        $this->assertNotNull((new User())->findById((int)$this->fixture['hr']['id']));
    }
}
