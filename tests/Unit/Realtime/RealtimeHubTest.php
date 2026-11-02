<?php

namespace Tests\Unit\Realtime;

use App\Realtime\ChannelPolicy;
use App\Realtime\CloseCodes;
use App\Realtime\EventAuthorizer;
use App\Realtime\Logger;
use App\Realtime\RealtimeConfig;
use App\Realtime\RealtimeHub;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeConnection;
use Tests\Support\InMemoryRealtimeStore;

class RealtimeHubTest extends TestCase
{
    private const HR_ID = 2;
    private const HR2_ID = 3;
    private const EMP_A_ID = 7;
    private const EMP_A_EMPLOYEE = 70;
    private const EMP_B_ID = 8;
    private const EMP_B_EMPLOYEE = 80;

    private InMemoryRealtimeStore $store;
    private RealtimeHub $hub;
    private float $clock;
    private RealtimeConfig $config;

    protected function setUp(): void
    {
        $this->store = new InMemoryRealtimeStore();
        $this->store->addUser(self::HR_ID, 'hr', 'Active', 20);
        $this->store->addUser(self::HR2_ID, 'hr', 'Active', 30);
        $this->store->addUser(self::EMP_A_ID, 'employee', 'Active', self::EMP_A_EMPLOYEE);
        $this->store->addUser(self::EMP_B_ID, 'employee', 'Active', self::EMP_B_EMPLOYEE);
        $this->store->unread = [self::HR_ID => 4, self::HR2_ID => 1, self::EMP_A_ID => 2, self::EMP_B_ID => 9];

        $this->clock = (float)$this->store->now;
        $this->config = RealtimeConfig::fromEnvironment([
            'BASE_URL'                    => 'http://localhost:8080',
            'WS_MAX_CONNECTIONS_PER_USER' => '2',
            'WS_RATE_LIMIT_BURST'         => '5',
            'WS_RATE_LIMIT_PER_SECOND'    => '1',
            'WS_MAX_MESSAGE_BYTES'        => '512',
            'WS_AUTH_TIMEOUT'             => '5',
        ]);
        $this->hub = $this->makeHub();
    }

    private function makeHub(): RealtimeHub
    {
        return new RealtimeHub(
            $this->store,
            $this->config,
            new ChannelPolicy(),
            new EventAuthorizer(),
            Logger::silent(),
            fn (): float => $this->clock
        );
    }

    private function connectAs(int $userId, ?string $ticket = null): FakeConnection
    {
        $conn = new FakeConnection();
        $this->hub->onOpen($conn);
        $ticket ??= $this->store->issueTicket($userId);
        $this->hub->onMessage($conn, json_encode(['type' => 'auth', 'ticket' => $ticket]));
        return $conn;
    }

    private function leaveRow(int $id, int $recipientUserId, string $event, int $leaveId, bool $ownerMatches = false): array
    {
        $role = $this->store->users[$recipientUserId]['role'];
        return [
            'id'               => $id,
            'user_id'          => $recipientUserId,
            'type'             => 'info',
            'event_type'       => $event,
            'entity_id'        => $leaveId,
            'created_ts'       => $this->store->now,
            'recipient_role'   => $role,
            'recipient_status' => 'Active',
            'owner_matches'    => $ownerMatches ? 1 : 0,
        ];
    }

    // ------------------------------------------------------------------
    // Authentication
    // ------------------------------------------------------------------

    public function test_hr_connection_authenticates_with_server_side_identity(): void
    {
        $conn = $this->connectAs(self::HR_ID);

        $this->assertFalse($conn->closed);
        $ok = $conn->messagesOfType('auth.ok')[0] ?? null;
        $this->assertNotNull($ok);
        $this->assertSame('hr', $ok['role']);
        $this->assertSame(['user.' . self::HR_ID, 'role.hr'], $ok['channels']);

        $count = $conn->messagesOfType('notification.unread_count')[0] ?? null;
        $this->assertSame(4, $count['data']['count']);
    }

    public function test_employee_connection_authenticates_with_employee_channels_only(): void
    {
        $conn = $this->connectAs(self::EMP_A_ID);

        $ok = $conn->messagesOfType('auth.ok')[0];
        $this->assertSame('employee', $ok['role']);
        $this->assertSame(['user.' . self::EMP_A_ID, 'role.employee'], $ok['channels']);
        $this->assertNotContains('role.hr', $ok['channels']);
    }

    public function test_messages_before_authentication_are_rejected(): void
    {
        $conn = new FakeConnection();
        $this->hub->onOpen($conn);
        $this->hub->onMessage($conn, json_encode(['type' => 'subscribe', 'channel' => 'role.hr']));

        $this->assertTrue($conn->closed);
        $this->assertSame(CloseCodes::AUTH_FAILED, $conn->closeCode);
        $this->assertSame([], $conn->messagesOfType('auth.ok'));
    }

    public function test_silent_unauthenticated_connection_times_out(): void
    {
        $conn = new FakeConnection();
        $this->hub->onOpen($conn);

        $this->clock += 4;
        $this->hub->tick();
        $this->assertFalse($conn->closed, 'Must stay open inside the auth window.');

        $this->clock += 2;
        $this->hub->tick();
        $this->assertTrue($conn->closed);
        $this->assertSame(CloseCodes::AUTH_TIMEOUT, $conn->closeCode);
    }

    public function test_malformed_and_unknown_tickets_are_rejected(): void
    {
        foreach (['', 'not-hex', str_repeat('g', 64), str_repeat('a', 63), bin2hex(random_bytes(32))] as $badTicket) {
            $conn = $this->connectAs(self::HR_ID, $badTicket);
            $this->assertTrue($conn->closed, "Ticket '{$badTicket}' must be rejected.");
            $this->assertSame(CloseCodes::AUTH_FAILED, $conn->closeCode);
            $this->assertSame('invalid_ticket', $conn->messagesOfType('auth.error')[0]['code']);
        }
    }

    public function test_expired_ticket_is_rejected(): void
    {
        $ticket = $this->store->issueTicket(self::HR_ID, ttl: 30);
        $this->store->now += 31;
        $this->clock += 31;

        $conn = $this->connectAs(self::HR_ID, $ticket);
        $this->assertSame(CloseCodes::AUTH_FAILED, $conn->closeCode);
    }

    public function test_ticket_for_expired_session_is_rejected(): void
    {
        $ticket = $this->store->issueTicket(self::HR_ID, sessionExpiresAt: $this->store->now - 1);
        $conn = $this->connectAs(self::HR_ID, $ticket);
        $this->assertSame(CloseCodes::AUTH_FAILED, $conn->closeCode);
    }

    public function test_ticket_is_single_use(): void
    {
        $ticket = $this->store->issueTicket(self::EMP_A_ID);

        $first = $this->connectAs(self::EMP_A_ID, $ticket);
        $this->assertFalse($first->closed);

        $replay = $this->connectAs(self::EMP_A_ID, $ticket);
        $this->assertTrue($replay->closed, 'A captured ticket must not be replayable.');
        $this->assertSame(CloseCodes::AUTH_FAILED, $replay->closeCode);
    }

    public function test_revoked_ticket_and_inactive_account_are_rejected(): void
    {
        $ticket = $this->store->issueTicket(self::EMP_A_ID, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $this->store->revokeSession('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $this->assertSame(CloseCodes::AUTH_FAILED, $this->connectAs(self::EMP_A_ID, $ticket)->closeCode);

        $this->store->users[self::EMP_B_ID]['status'] = 'Inactive';
        $this->assertSame(CloseCodes::AUTH_FAILED, $this->connectAs(self::EMP_B_ID)->closeCode);
    }

    public function test_client_supplied_identity_fields_cannot_impersonate(): void
    {
        $ticket = $this->store->issueTicket(self::EMP_A_ID);
        $conn = new FakeConnection();
        $this->hub->onOpen($conn);
        $this->hub->onMessage($conn, json_encode([
            'type'    => 'auth',
            'ticket'  => $ticket,
            'user_id' => self::HR_ID,
            'role'    => 'hr',
            'email'   => 'hr@example.test',
        ]));

        $ok = $conn->messagesOfType('auth.ok')[0];
        $this->assertSame('employee', $ok['role']);
        $this->assertSame(['user.' . self::EMP_A_ID, 'role.employee'], $ok['channels']);
        $this->assertSame(2, $conn->messagesOfType('notification.unread_count')[0]['data']['count'], 'Unread count must be the ticket owner\'s, not the claimed user\'s.');
    }

    public function test_per_user_connection_limit(): void
    {
        $this->connectAs(self::HR_ID);
        $this->connectAs(self::HR_ID);
        $third = $this->connectAs(self::HR_ID);

        $this->assertSame(CloseCodes::TOO_MANY, $third->closeCode);
    }

    public function test_reconnect_with_fresh_ticket_after_disconnect(): void
    {
        $first = $this->connectAs(self::EMP_A_ID);
        $this->hub->onClose($first);

        $second = $this->connectAs(self::EMP_A_ID);
        $this->assertFalse($second->closed);
        $this->assertCount(1, $second->messagesOfType('auth.ok'));
        $this->assertSame(1, $this->hub->stats()['authenticated']);
    }

    public function test_second_auth_message_is_refused(): void
    {
        $conn = $this->connectAs(self::EMP_A_ID);
        $this->hub->onMessage($conn, json_encode(['type' => 'auth', 'ticket' => $this->store->issueTicket(self::HR_ID)]));

        $this->assertSame('already_authenticated', $conn->lastMessage()['code']);
        $this->assertSame('employee', $conn->messagesOfType('auth.ok')[0]['role']);
    }

    // ------------------------------------------------------------------
    // Channel authorization
    // ------------------------------------------------------------------

    public function test_employee_cannot_subscribe_to_other_private_or_hr_channels(): void
    {
        $conn = $this->connectAs(self::EMP_A_ID);
        $conn->clear();

        foreach (['user.' . self::EMP_B_ID, 'user.' . self::HR_ID, 'role.hr', 'role.admin', 'user.*', 'user.8 '] as $channel) {
            $this->clock += 1; // stay under the message rate limit
            $this->hub->onMessage($conn, json_encode(['type' => 'subscribe', 'channel' => $channel]));
            $last = $conn->lastMessage();
            $this->assertSame('error', $last['type'], "Subscribe to {$channel} must fail.");
            $this->assertSame('forbidden', $last['code']);
        }
        $this->assertFalse($conn->closed);

        // Prove nothing leaked: an HR-wide event for HR users never reaches the employee
        $this->hub->dispatch([$this->leaveRow(500, self::HR_ID, EventAuthorizer::LEAVE_CREATED, 41)]);
        $this->assertSame([], $conn->messagesOfType('event'));
    }

    public function test_own_channel_subscription_is_allowed(): void
    {
        $conn = $this->connectAs(self::EMP_A_ID);
        $this->hub->onMessage($conn, json_encode(['type' => 'subscribe', 'channel' => 'user.' . self::EMP_A_ID]));
        $this->assertSame('subscribed', $conn->lastMessage()['type']);
    }

    public function test_hr_cannot_subscribe_to_employee_private_channel(): void
    {
        $conn = $this->connectAs(self::HR_ID);
        $this->hub->onMessage($conn, json_encode(['type' => 'subscribe', 'channel' => 'user.' . self::EMP_A_ID]));
        $this->assertSame('forbidden', $conn->lastMessage()['code']);
    }

    // ------------------------------------------------------------------
    // Event isolation
    // ------------------------------------------------------------------

    public function test_leave_created_reaches_hr_users_only(): void
    {
        $hr = $this->connectAs(self::HR_ID);
        $hr2 = $this->connectAs(self::HR2_ID);
        $empA = $this->connectAs(self::EMP_A_ID);
        $empB = $this->connectAs(self::EMP_B_ID);

        // LeaveNotificationService writes one row per active HR user
        $delivered = $this->hub->dispatch([
            $this->leaveRow(101, self::HR_ID, EventAuthorizer::LEAVE_CREATED, 41),
            $this->leaveRow(102, self::HR2_ID, EventAuthorizer::LEAVE_CREATED, 41),
        ]);

        $this->assertSame(2, $delivered);
        $this->assertSame('leave_request.created', $hr->messagesOfType('event')[0]['event']);
        $this->assertSame('notification:101', $hr->messagesOfType('event')[0]['id']);
        $this->assertSame('notification:102', $hr2->messagesOfType('event')[0]['id']);
        $this->assertCount(1, $hr->messagesOfType('event'), 'HR must not receive the other HR user\'s copy.');
        $this->assertSame([], $empA->messagesOfType('event'));
        $this->assertSame([], $empB->messagesOfType('event'));
    }

    public function test_decision_reaches_only_the_owning_employee(): void
    {
        $hr = $this->connectAs(self::HR_ID);
        $empA = $this->connectAs(self::EMP_A_ID);
        $empB = $this->connectAs(self::EMP_B_ID);

        $this->hub->dispatch([$this->leaveRow(201, self::EMP_A_ID, EventAuthorizer::LEAVE_APPROVED, 41, ownerMatches: true)]);
        $this->hub->dispatch([$this->leaveRow(202, self::EMP_A_ID, EventAuthorizer::LEAVE_REJECTED, 42, ownerMatches: true)]);

        $events = array_column($empA->messagesOfType('event'), 'event');
        $this->assertSame(['leave_request.approved', 'leave_request.rejected'], $events);
        $this->assertSame([], $empB->messagesOfType('event'));
        $this->assertSame([], $hr->messagesOfType('event'));
    }

    public function test_misaddressed_or_unowned_rows_are_never_delivered(): void
    {
        $empA = $this->connectAs(self::EMP_A_ID);

        // Decision row whose leave request is not owned by the recipient
        $this->hub->dispatch([$this->leaveRow(301, self::EMP_A_ID, EventAuthorizer::LEAVE_APPROVED, 99, ownerMatches: false)]);
        // HR-queue event addressed to an employee account
        $this->hub->dispatch([$this->leaveRow(302, self::EMP_A_ID, EventAuthorizer::LEAVE_CREATED, 41)]);
        // Unknown event type
        $row = $this->leaveRow(303, self::EMP_A_ID, 'payroll.leaked', 41, ownerMatches: true);
        $this->hub->dispatch([$row]);

        $this->assertSame([], $empA->messagesOfType('event'));
    }

    public function test_event_payload_is_generic(): void
    {
        $empA = $this->connectAs(self::EMP_A_ID);
        $this->hub->dispatch([$this->leaveRow(401, self::EMP_A_ID, EventAuthorizer::LEAVE_REJECTED, 7, ownerMatches: true)]);

        $event = $empA->messagesOfType('event')[0];
        $this->assertSame(['type', 'event', 'id', 'data'], array_keys($event));
        $this->assertSame(
            ['notification_id', 'title', 'message', 'level', 'reference', 'link', 'created_at'],
            array_keys($event['data'])
        );
        $this->assertSame('LR-007', $event['data']['reference']);
        $this->assertSame('leaves/7', $event['data']['link']);
        $this->assertStringNotContainsStringIgnoringCase('reason:', $event['data']['message']);
    }

    public function test_unsubscribed_connection_stops_receiving_events(): void
    {
        $empA = $this->connectAs(self::EMP_A_ID);
        $this->hub->onMessage($empA, json_encode(['type' => 'unsubscribe', 'channel' => 'user.' . self::EMP_A_ID]));
        $this->hub->dispatch([$this->leaveRow(501, self::EMP_A_ID, EventAuthorizer::LEAVE_APPROVED, 41, ownerMatches: true)]);
        $this->assertSame([], $empA->messagesOfType('event'));
    }

    // ------------------------------------------------------------------
    // Unread counts
    // ------------------------------------------------------------------

    public function test_unread_count_is_scoped_to_the_authenticated_user(): void
    {
        $empA = $this->connectAs(self::EMP_A_ID);
        $empB = $this->connectAs(self::EMP_B_ID);
        $empA->clear();
        $empB->clear();

        $this->hub->onMessage($empA, json_encode(['type' => 'notification.unread_count', 'user_id' => self::EMP_B_ID]));
        $this->assertSame(2, $empA->lastMessage()['data']['count']);
        $this->assertSame([], $empB->messages());

        // A new row for A refreshes only A's count
        $this->store->unread[self::EMP_A_ID] = 3;
        $this->hub->dispatch([$this->leaveRow(601, self::EMP_A_ID, EventAuthorizer::LEAVE_APPROVED, 41, ownerMatches: true)]);
        $this->assertSame(3, $empA->messagesOfType('notification.unread_count')[1]['data']['count']);
        $this->assertSame([], $empB->messages());
    }

    // ------------------------------------------------------------------
    // Abuse handling
    // ------------------------------------------------------------------

    public function test_malformed_messages_get_errors_then_close(): void
    {
        $conn = $this->connectAs(self::EMP_A_ID);
        $conn->clear();

        $this->hub->onMessage($conn, '{not json');
        $this->assertSame('bad_request', $conn->lastMessage()['code']);
        $this->hub->onMessage($conn, json_encode(['no_type' => true]));
        $this->assertSame('bad_request', $conn->lastMessage()['code']);
        $this->assertFalse($conn->closed);

        $this->hub->onMessage($conn, json_encode(['type' => 'drop_tables']));
        $this->assertTrue($conn->closed);
        $this->assertSame(CloseCodes::POLICY_VIOLATION, $conn->closeCode);
    }

    public function test_deeply_nested_json_is_rejected(): void
    {
        $conn = $this->connectAs(self::EMP_A_ID);
        $conn->clear();
        $this->hub->onMessage($conn, '{"type":"ping","a":{"b":{"c":{"d":{"e":1}}}}}');
        $this->assertSame('bad_request', $conn->lastMessage()['code']);
    }

    public function test_oversized_message_closes_connection(): void
    {
        $conn = $this->connectAs(self::EMP_A_ID);
        $this->hub->onMessage($conn, json_encode(['type' => 'ping', 'pad' => str_repeat('x', 600)]));
        $this->assertSame(CloseCodes::MESSAGE_TOO_BIG, $conn->closeCode);
    }

    public function test_message_flood_is_rate_limited(): void
    {
        $conn = $this->connectAs(self::EMP_A_ID); // auth consumed 1 of 5 tokens
        for ($i = 0; $i < 4; $i++) {
            $this->hub->onMessage($conn, '{"type":"ping"}');
        }
        $this->assertFalse($conn->closed);

        $this->hub->onMessage($conn, '{"type":"ping"}');
        $this->assertSame(CloseCodes::POLICY_VIOLATION, $conn->closeCode);
        $this->assertSame('rate_limited', $conn->closeReason);
    }

    public function test_messages_after_close_are_ignored(): void
    {
        $conn = $this->connectAs(self::EMP_A_ID);
        $this->hub->onMessage($conn, str_repeat('x', 1000)); // closes
        $count = count($conn->sent);
        $this->hub->onMessage($conn, '{"type":"ping"}');
        $this->assertCount($count, $conn->sent);
    }

    // ------------------------------------------------------------------
    // Session lifecycle
    // ------------------------------------------------------------------

    public function test_connection_closes_at_session_deadline(): void
    {
        $ticket = $this->store->issueTicket(self::EMP_A_ID, sessionExpiresAt: $this->store->now + 60);
        $conn = $this->connectAs(self::EMP_A_ID, $ticket);

        $this->clock += 59;
        $this->hub->tick();
        $this->assertFalse($conn->closed);

        $this->clock += 1;
        $this->hub->tick();
        $this->assertSame(CloseCodes::SESSION_EXPIRED, $conn->closeCode);
        $this->assertNotEmpty($conn->messagesOfType('session.expired'));
    }

    public function test_logout_deactivation_and_role_change_close_connections(): void
    {
        $ref = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $loggedOut = $this->connectAs(self::EMP_A_ID, $this->store->issueTicket(self::EMP_A_ID, $ref));
        $deactivated = $this->connectAs(self::EMP_B_ID);
        $demoted = $this->connectAs(self::HR_ID);
        $untouched = $this->connectAs(self::HR2_ID);

        $this->store->revokeSession($ref);
        $this->store->users[self::EMP_B_ID]['status'] = 'Inactive';
        $this->store->users[self::HR_ID]['role'] = 'employee';

        $this->hub->revalidate();

        $this->assertSame(CloseCodes::SESSION_REVOKED, $loggedOut->closeCode);
        $this->assertSame(CloseCodes::SESSION_REVOKED, $deactivated->closeCode);
        $this->assertSame('account_inactive', $deactivated->closeReason);
        $this->assertSame(CloseCodes::REAUTH_REQUIRED, $demoted->closeCode);
        $this->assertFalse($untouched->closed);
    }

    public function test_closed_connections_receive_no_events(): void
    {
        $empA = $this->connectAs(self::EMP_A_ID);
        $this->hub->onClose($empA);
        $empA->clear();

        $this->hub->dispatch([$this->leaveRow(701, self::EMP_A_ID, EventAuthorizer::LEAVE_APPROVED, 41, ownerMatches: true)]);
        $this->assertSame([], $empA->messages());
        $this->assertSame(0, $this->hub->stats()['connections']);
    }
}
