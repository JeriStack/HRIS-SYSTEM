<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\RealtimeTicket;
use App\Services\LeaveNotificationService;
use Core\Database;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\WsTestClient;

/**
 * Live end-to-end test against the running WebSocket service.
 * Skipped unless WS_E2E_URL is set. Run it with:
 *
 *   make test-realtime
 *
 * phpunit.xml forces DB_DATABASE=hris_test, but the always-on ealtime service
 * reads hris_db and cannot see the tickets these tests create. The make target
 * (scripts/test-realtime-e2e.sh) starts a throwaway realtime server on the test
 * database and points WS_E2E_URL at it.
 */
#[Group('realtime-e2e')]
class RealtimeEndToEndTest extends TestCase
{
    private string $url;
    private string $origin;
    private PDO $pdo;
    private RealtimeTicket $tickets;

    /** @var array<string, array{id:int, employee_id:?int}> */
    private array $users = [];
    private int $leaveTypeId;

    /** @var string[] */
    private array $refs = [];
    /** @var int[] */
    private array $leaveIds = [];
    /** @var WsTestClient[] */
    private array $clients = [];

    protected function setUp(): void
    {
        $url = getenv('WS_E2E_URL');
        if (!$url) {
            $this->markTestSkipped('Set WS_E2E_URL to run live WebSocket end-to-end tests.');
        }
        $this->url = $url;
        $this->origin = getenv('WS_E2E_ORIGIN') ?: 'http://localhost:8080';

        $db = Database::getInstance();
        if (!$db->isConnected()) {
            $this->markTestSkipped('Database not connected.');
        }
        $this->pdo = $db->getConnection();
        $this->tickets = new RealtimeTicket();

        $hr = $this->pdo->query("SELECT id, employee_id FROM users WHERE role='hr' AND status='Active' ORDER BY id LIMIT 1")->fetch();
        $emps = $this->pdo->query("SELECT id, employee_id FROM users WHERE role='employee' AND status='Active' AND employee_id IS NOT NULL ORDER BY id LIMIT 2")->fetchAll();
        $this->leaveTypeId = (int)$this->pdo->query("SELECT id FROM leave_types WHERE status='Active' ORDER BY id LIMIT 1")->fetchColumn();
        if (!$hr || count($emps) < 2 || !$this->leaveTypeId) {
            $this->markTestSkipped('Seed data required.');
        }
        $this->users = ['hr' => $hr, 'empA' => $emps[0], 'empB' => $emps[1]];
    }

    protected function tearDown(): void
    {
        foreach ($this->clients as $client) {
            $client->close();
        }
        if (!isset($this->pdo)) {
            return;
        }
        foreach ($this->leaveIds as $id) {
            $this->pdo->prepare("DELETE FROM activity_logs WHERE entity_id = ? AND entity_type = 'LeaveRequest'")->execute([(string)$id]);
            $this->pdo->prepare("DELETE FROM notifications WHERE entity_id = ? AND event_type LIKE 'leave\\_request.%'")->execute([$id]);
            $this->pdo->prepare("DELETE FROM leave_requests WHERE id = ?")->execute([$id]);
        }
        if (!empty($this->users['empA']['employee_id'])) {
            $this->pdo->prepare("DELETE FROM leave_balances WHERE employee_id = ? AND year = 2099")->execute([(int)$this->users['empA']['employee_id']]);
        }
        foreach ($this->refs as $ref) {
            $this->pdo->prepare("DELETE FROM realtime_tickets WHERE session_ref = ?")->execute([$ref]);
        }
    }

    private function ticketFor(string $who, ?string &$ref = null): string
    {
        $ref = $this->refs[] = bin2hex(random_bytes(16));
        return $this->tickets->issue((int)$this->users[$who]['id'], $ref, time() + 600, 30, '127.0.0.1');
    }

    private function open(?string $origin = null): WsTestClient
    {
        return $this->clients[] = WsTestClient::connect($this->url, $origin ?? $this->origin);
    }

    private function authed(string $who, ?string &$ref = null): WsTestClient
    {
        $client = $this->open();
        $this->assertSame(101, $client->handshakeStatus);
        $client->sendJson(['type' => 'auth', 'ticket' => $this->ticketFor($who, $ref)]);
        $ok = $client->receiveUntil(fn ($m) => in_array($m['type'] ?? '', ['auth.ok', 'auth.error'], true), 5);
        $this->assertSame('auth.ok', $ok['type'] ?? null, "{$who} must authenticate");
        $count = $client->receiveUntil(fn ($m) => ($m['type'] ?? '') === 'notification.unread_count', 3);
        $this->assertNotNull($count);
        return $client;
    }

    private function events(WsTestClient $client, float $seconds): array
    {
        return array_values(array_filter($client->collect($seconds), fn ($m) => ($m['type'] ?? '') === 'event'));
    }

    private function newLeave(int $employeeId, string $start, string $end, ?callable $cb = null): int
    {
        return $this->leaveIds[] = (new Leave())->createRequest([
            'employee_id' => $employeeId, 'leave_type_id' => $this->leaveTypeId,
            'start_date' => $start, 'end_date' => $end, 'number_of_days' => 2,
            'reason' => 'Automated realtime E2E test',
        ], $cb);
    }

    public function test_handshake_and_authentication_rules(): void
    {
        $badOrigin = $this->open('http://evil.example');
        $this->assertSame(403, $badOrigin->handshakeStatus);

        $noAuth = $this->open();
        $this->assertSame(101, $noAuth->handshakeStatus);
        $this->assertSame(4408, $noAuth->waitForClose(8), 'Silent sockets are closed after the auth timeout.');

        $invalid = $this->open();
        $invalid->sendJson(['type' => 'auth', 'ticket' => bin2hex(random_bytes(32))]);
        $this->assertSame(4401, $invalid->waitForClose(5));

        $ticket = $this->ticketFor('hr');
        $first = $this->open();
        $first->sendJson(['type' => 'auth', 'ticket' => $ticket]);
        $this->assertSame('auth.ok', $first->receiveUntil(fn ($m) => str_starts_with($m['type'] ?? '', 'auth.'))['type']);
        $replay = $this->open();
        $replay->sendJson(['type' => 'auth', 'ticket' => $ticket]);
        $this->assertSame(4401, $replay->waitForClose(5), 'Tickets are single-use.');

        $malformed = $this->authed('empA');
        $malformed->sendText('{oops');
        $this->assertSame('bad_request', $malformed->receiveUntil(fn ($m) => ($m['type'] ?? '') === 'error')['code']);
        $malformed->sendJson(['type' => 'subscribe', 'channel' => 'user.' . $this->users['empB']['id']]);
        $this->assertSame('forbidden', $malformed->receiveUntil(fn ($m) => ($m['type'] ?? '') === 'error')['code']);
        $malformed->sendJson(['type' => 'subscribe', 'channel' => 'role.hr']);
        $this->assertSame('forbidden', $malformed->receiveUntil(fn ($m) => ($m['type'] ?? '') === 'error')['code']);
        $malformed->sendJson(['type' => 'ping']);
        $this->assertNotNull($malformed->receiveUntil(fn ($m) => ($m['type'] ?? '') === 'pong'));
    }

    public function test_leave_events_are_delivered_after_commit_with_role_isolation(): void
    {
        $hr = $this->authed('hr');
        $empA = $this->authed('empA');
        $empB = $this->authed('empB');
        $service = new LeaveNotificationService();
        $empAEmployee = (int)$this->users['empA']['employee_id'];

        // 1. Submission inside an open transaction: nothing may be pushed before COMMIT
        $this->pdo->beginTransaction();
        $leaveId = $this->leaveIds[] = (new Leave())->createRequest([
            'employee_id' => $empAEmployee, 'leave_type_id' => $this->leaveTypeId,
            'start_date' => '2099-04-06', 'end_date' => '2099-04-07', 'number_of_days' => 2,
            'reason' => 'Automated realtime E2E test',
        ]);
        $service->leaveSubmitted($leaveId, 'E2E submission');
        $this->assertSame([], $this->events($hr, 2.5), 'No event before commit.');
        $this->pdo->commit();

        $created = $hr->receiveUntil(fn ($m) => ($m['event'] ?? '') === 'leave_request.created' && ($m['data']['reference'] ?? '') === 'LR-' . str_pad((string)$leaveId, 3, '0', STR_PAD_LEFT), 5);
        $this->assertNotNull($created, 'HR receives leave_request.created after commit.');
        $this->assertArrayNotHasKey('reason', $created['data']);

        // 2. Rolled-back submission: never pushed
        $this->pdo->beginTransaction();
        $ghostId = (new Leave())->createRequest([
            'employee_id' => $empAEmployee, 'leave_type_id' => $this->leaveTypeId,
            'start_date' => '2099-05-04', 'end_date' => '2099-05-05', 'number_of_days' => 2,
            'reason' => 'Automated realtime E2E test',
        ]);
        $service->leaveSubmitted($ghostId, 'E2E ghost');
        $this->pdo->rollBack();

        // 3. Decisions for empA's requests
        $approved = (new Leave())->updateRequestStatus($leaveId, 'Approved', (int)$this->users['hr']['id'], null,
            fn (array $req, string $status) => $service->leaveDecided($req, $status, 'E2E approved'));
        $this->assertTrue($approved);

        $second = $this->newLeave($empAEmployee, '2099-04-13', '2099-04-14',
            fn (int $id) => $service->leaveSubmitted($id, 'E2E submission 2'));
        $rejected = (new Leave())->updateRequestStatus($second, 'Rejected', (int)$this->users['hr']['id'], 'E2E', 
            fn (array $req, string $status) => $service->leaveDecided($req, $status, 'E2E rejected'));
        $this->assertTrue($rejected);

        $empAEvents = array_column($this->events($empA, 4), 'event');
        $this->assertContains('leave_request.approved', $empAEvents);
        $this->assertContains('leave_request.rejected', $empAEvents);
        $this->assertNotContains('leave_request.created', $empAEvents, 'Employees never receive HR queue events.');

        $hrEvents = $this->events($hr, 1.5);
        $hrNames = array_column($hrEvents, 'event');
        $this->assertNotContains('leave_request.approved', $hrNames);
        $this->assertNotContains('leave_request.rejected', $hrNames);
        $ghostRef = 'LR-' . str_pad((string)$ghostId, 3, '0', STR_PAD_LEFT);
        $this->assertNotContains($ghostRef, array_map(fn ($e) => $e['data']['reference'] ?? '', $hrEvents), 'Rolled-back requests are never announced.');

        $this->assertSame([], $this->events($empB, 1.5), 'Another employee receives nothing.');

        // 4. Unread count is the user's own DB count
        $empA->sendJson(['type' => 'notification.unread_count']);
        $count = $empA->receiveUntil(fn ($m) => ($m['type'] ?? '') === 'notification.unread_count', 3);
        $expected = (int)$this->pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = " . (int)$this->users['empA']['id'] . " AND is_read = 0")->fetchColumn();
        $this->assertSame($expected, $count['data']['count']);
    }

    public function test_logout_revocation_closes_live_socket(): void
    {
        $ref = null;
        $emp = $this->authed('empA', $ref);
        $other = $this->authed('empB');

        $this->tickets->revokeSession($ref);

        $this->assertSame(4003, $emp->waitForClose(25), 'Revoked sessions are closed by the revalidation sweep.');
        $this->assertFalse($other->closed, 'Other sessions stay connected.');
    }
}
