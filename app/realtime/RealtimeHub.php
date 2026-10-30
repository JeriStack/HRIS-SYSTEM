<?php
/**
 * HRIS Real-time Hub (Ratchet MessageComponent)
 * Employee HRIS & Leave Management System
 *
 * Protocol (JSON text frames, client -> server):
 *   {"type":"auth","ticket":"<64 hex>"}            first and only allowed message before auth
 *   {"type":"ping"}
 *   {"type":"subscribe","channel":"user.12"}        only channels derived from the verified identity
 *   {"type":"unsubscribe","channel":"role.hr"}
 *   {"type":"notification.unread_count"}           request own unread count
 *
 * Server -> client: auth.ok, auth.error, event, notification.unread_count,
 * subscribed, unsubscribed, pong, error, session.expired.
 *
 * Identity, role and channels are always derived from the server-side ticket
 * lookup. Client-supplied ids, emails or roles are never read.
 */

namespace App\Realtime;

use App\Realtime\Contracts\RealtimeStore;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\RFC6455\Messaging\Frame;
use Throwable;

final class RealtimeHub implements MessageComponentInterface
{
    private const MAX_STRIKES = 3;

    /** @var array<int, ClientState> */
    private array $clients = [];

    /** @var array<int, array<int,true>> userId => [connectionId => true] */
    private array $byUser = [];

    /** @var callable(): float */
    private $clock;

    public function __construct(
        private readonly RealtimeStore $store,
        private readonly RealtimeConfig $config,
        private readonly ChannelPolicy $channelPolicy,
        private readonly EventAuthorizer $authorizer,
        private readonly Logger $logger,
        ?callable $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): float => microtime(true);
    }

    // ---------------------------------------------------------------------
    // Ratchet lifecycle
    // ---------------------------------------------------------------------

    public function onOpen(ConnectionInterface $conn): void
    {
        $id = spl_object_id($conn);
        $now = $this->now();
        $ip = isset($conn->hrisClientIp) ? (string)$conn->hrisClientIp : (isset($conn->remoteAddress) ? (string)$conn->remoteAddress : 'unknown');

        $this->clients[$id] = new ClientState(
            id: $id,
            connection: $conn,
            ip: $ip,
            openedAt: $now,
            bucket: new TokenBucket($this->config->rateLimitBurst, $this->config->rateLimitPerSecond, $now),
        );

        $this->logger->debug('ws.open', ['conn' => $id, 'ip' => $ip]);
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $state = $this->clients[spl_object_id($from)] ?? null;
        if ($state === null || $state->closing) {
            return;
        }

        $raw = (string)$msg;

        if (strlen($raw) > $this->config->maxMessageBytes) {
            $this->closeClient($state, CloseCodes::MESSAGE_TOO_BIG, 'message_too_large');
            return;
        }

        if (!$state->bucket->allow($this->now())) {
            $this->closeClient($state, CloseCodes::POLICY_VIOLATION, 'rate_limited');
            return;
        }

        $data = json_decode($raw, true, 4);
        if (!is_array($data) || !isset($data['type']) || !is_string($data['type'])) {
            $this->strike($state, 'bad_request');
            return;
        }

        $type = $data['type'];

        if (!$state->isAuthenticated()) {
            if ($type !== 'auth') {
                $this->send($state, ['type' => 'auth.error', 'code' => 'unauthenticated']);
                $this->closeClient($state, CloseCodes::AUTH_FAILED, 'unauthenticated');
                return;
            }
            $this->authenticate($state, $data);
            return;
        }

        switch ($type) {
            case 'ping':
                $this->send($state, ['type' => 'pong', 'server_time' => time()]);
                break;

            case 'subscribe':
                $this->subscribe($state, $data['channel'] ?? null);
                break;

            case 'unsubscribe':
                $channel = $data['channel'] ?? null;
                if (!is_string($channel) || !isset($state->channels[$channel])) {
                    $this->strike($state, 'bad_request');
                    break;
                }
                unset($state->channels[$channel]);
                $this->send($state, ['type' => 'unsubscribed', 'channel' => $channel]);
                break;

            case EventAuthorizer::UNREAD_COUNT:
                $this->sendUnreadCount($state);
                break;

            case 'auth':
                $this->send($state, ['type' => 'error', 'code' => 'already_authenticated']);
                break;

            default:
                $this->strike($state, 'unknown_type');
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $id = spl_object_id($conn);
        $state = $this->clients[$id] ?? null;
        if ($state === null) {
            return;
        }

        unset($this->clients[$id]);
        if ($state->identity !== null) {
            unset($this->byUser[$state->identity->userId][$id]);
            if (empty($this->byUser[$state->identity->userId])) {
                unset($this->byUser[$state->identity->userId]);
            }
        }

        $this->logger->debug('ws.closed', ['conn' => $id, 'user' => $state->identity?->userId]);
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $state = $this->clients[spl_object_id($conn)] ?? null;
        $this->logger->warning('ws.error', ['conn' => spl_object_id($conn), 'error' => get_class($e)]);

        if ($state !== null) {
            $this->closeClient($state, CloseCodes::SERVER_ERROR, 'server_error');
        } else {
            $conn->close();
        }
    }

    // ---------------------------------------------------------------------
    // Periodic maintenance (driven by event-loop timers in Server)
    // ---------------------------------------------------------------------

    /**
     * Enforce the auth timeout and session deadlines.
     */
    public function tick(): void
    {
        $now = $this->now();

        foreach ($this->clients as $state) {
            if ($state->closing) {
                continue;
            }

            if (!$state->isAuthenticated()) {
                if ($now - $state->openedAt >= $this->config->authTimeout) {
                    $this->closeClient($state, CloseCodes::AUTH_TIMEOUT, 'auth_timeout');
                }
                continue;
            }

            if ($now >= $state->identity->sessionExpiresAt) {
                $this->send($state, ['type' => 'session.expired']);
                $this->closeClient($state, CloseCodes::SESSION_EXPIRED, 'session_expired');
            }
        }
    }

    /**
     * Close connections whose session was revoked (logout), whose account
     * was deactivated, or whose role changed since the ticket was issued.
     */
    public function revalidate(): void
    {
        $sessions = [];
        foreach ($this->clients as $id => $state) {
            if ($state->identity !== null && !$state->closing) {
                $sessions[$id] = [
                    'user_id'     => $state->identity->userId,
                    'role'        => $state->identity->role,
                    'session_ref' => $state->identity->sessionRef,
                ];
            }
        }

        if ($sessions === []) {
            return;
        }

        try {
            $invalid = $this->store->findInvalidSessions($sessions);
        } catch (Throwable $e) {
            $this->logger->error('ws.revalidate_failed', ['error' => get_class($e)]);
            return;
        }

        foreach ($invalid as $id => $reason) {
            $state = $this->clients[$id] ?? null;
            if ($state === null) {
                continue;
            }

            $this->logger->info('ws.session_invalidated', ['conn' => $id, 'user' => $state->identity?->userId, 'reason' => $reason]);

            if ($reason === 'role_changed') {
                $this->closeClient($state, CloseCodes::REAUTH_REQUIRED, 'reauth_required');
            } else {
                $this->closeClient($state, CloseCodes::SESSION_REVOKED, $reason === 'inactive' ? 'account_inactive' : 'session_revoked');
            }
        }
    }

    /**
     * Push committed notification rows to authorized connections and refresh
     * each affected user's unread count.
     *
     * @param array<int, array<string,mixed>> $rows
     * @return int Number of event frames delivered
     */
    public function dispatch(array $rows): int
    {
        $delivered = 0;
        $affectedUsers = [];

        foreach ($rows as $row) {
            $userId = (int)($row['user_id'] ?? 0);
            if (empty($this->byUser[$userId])) {
                continue;
            }

            $affectedUsers[$userId] = true;
            $payload = NotificationPayload::fromRow($row);
            if ($payload === null) {
                continue; // Non-realtime notification: only the unread count is refreshed
            }

            $channel = ChannelPolicy::userChannel($userId);
            foreach (array_keys($this->byUser[$userId]) as $connectionId) {
                $state = $this->clients[$connectionId] ?? null;
                if ($state === null || $state->closing || $state->identity === null || !isset($state->channels[$channel])) {
                    continue;
                }
                if (!$this->authorizer->canDeliver($state->identity, $row)) {
                    $this->logger->warning('ws.delivery_denied', ['conn' => $connectionId, 'user' => $userId, 'event' => (string)($row['event_type'] ?? '')]);
                    continue;
                }
                $this->send($state, $payload);
                $delivered++;
            }
        }

        foreach (array_keys($affectedUsers) as $userId) {
            $this->broadcastUnreadCount($userId);
        }

        if ($delivered > 0) {
            $this->logger->info('ws.dispatched', ['events' => $delivered, 'users' => count($affectedUsers)]);
        }

        return $delivered;
    }

    /**
     * @return array{connections:int, authenticated:int, users:int}
     */
    public function stats(): array
    {
        $authenticated = 0;
        foreach ($this->clients as $state) {
            if ($state->isAuthenticated()) {
                $authenticated++;
            }
        }

        return [
            'connections'   => count($this->clients),
            'authenticated' => $authenticated,
            'users'         => count($this->byUser),
        ];
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    private function authenticate(ClientState $state, array $data): void
    {
        $ticket = $data['ticket'] ?? null;
        if (!is_string($ticket) || !preg_match('/^[a-f0-9]{64}$/D', $ticket)) {
            $this->rejectAuth($state, 'invalid_ticket');
            return;
        }

        try {
            $identity = $this->store->consumeTicket(hash('sha256', $ticket));
        } catch (Throwable $e) {
            $this->logger->error('ws.auth_store_error', ['conn' => $state->id, 'error' => get_class($e)]);
            $this->send($state, ['type' => 'auth.error', 'code' => 'unavailable']);
            $this->closeClient($state, CloseCodes::SERVER_ERROR, 'unavailable');
            return;
        }

        if ($identity === null) {
            $this->rejectAuth($state, 'invalid_ticket');
            return;
        }

        if ($this->now() >= $identity->sessionExpiresAt) {
            $this->rejectAuth($state, 'session_expired');
            return;
        }

        if (count($this->byUser[$identity->userId] ?? []) >= $this->config->maxConnectionsPerUser) {
            $this->logger->warning('ws.user_limit', ['conn' => $state->id, 'user' => $identity->userId]);
            $this->send($state, ['type' => 'auth.error', 'code' => 'too_many_connections']);
            $this->closeClient($state, CloseCodes::TOO_MANY, 'too_many_connections');
            return;
        }

        $state->identity = $identity;
        foreach ($this->channelPolicy->channelsFor($identity) as $channel) {
            $state->channels[$channel] = true;
        }
        $this->byUser[$identity->userId][$state->id] = true;

        $this->logger->info('ws.authenticated', ['conn' => $state->id, 'user' => $identity->userId, 'role' => $identity->role]);

        $this->send($state, [
            'type'               => 'auth.ok',
            'role'               => $identity->role,
            'channels'           => array_keys($state->channels),
            'session_expires_in' => max(0, $identity->sessionExpiresAt - time()),
        ]);
        $this->sendUnreadCount($state);
    }

    private function rejectAuth(ClientState $state, string $code): void
    {
        $this->logger->info('ws.auth_rejected', ['conn' => $state->id, 'ip' => $state->ip, 'reason' => $code]);
        $this->send($state, ['type' => 'auth.error', 'code' => $code]);
        $this->closeClient($state, CloseCodes::AUTH_FAILED, $code);
    }

    private function subscribe(ClientState $state, mixed $channel): void
    {
        if (!is_string($channel) || $channel === '' || strlen($channel) > 64) {
            $this->strike($state, 'bad_request');
            return;
        }

        if (!$this->channelPolicy->canSubscribe($state->identity, $channel)) {
            $this->logger->warning('ws.subscribe_denied', ['conn' => $state->id, 'user' => $state->identity->userId]);
            $this->send($state, ['type' => 'error', 'code' => 'forbidden', 'channel' => $channel]);
            return;
        }

        $state->channels[$channel] = true;
        $this->send($state, ['type' => 'subscribed', 'channel' => $channel]);
    }

    private function sendUnreadCount(ClientState $state): void
    {
        if ($state->identity === null) {
            return;
        }

        try {
            $count = $this->store->unreadCount($state->identity->userId);
        } catch (Throwable $e) {
            $this->logger->error('ws.unread_failed', ['conn' => $state->id, 'error' => get_class($e)]);
            return;
        }

        $this->send($state, NotificationPayload::unreadCount($count));
    }

    private function broadcastUnreadCount(int $userId): void
    {
        $targets = [];
        $channel = ChannelPolicy::userChannel($userId);
        foreach (array_keys($this->byUser[$userId] ?? []) as $connectionId) {
            $state = $this->clients[$connectionId] ?? null;
            if ($state !== null && !$state->closing && isset($state->channels[$channel])) {
                $targets[] = $state;
            }
        }
        if ($targets === []) {
            return;
        }

        try {
            $count = $this->store->unreadCount($userId);
        } catch (Throwable $e) {
            $this->logger->error('ws.unread_failed', ['user' => $userId, 'error' => get_class($e)]);
            return;
        }

        $payload = NotificationPayload::unreadCount($count);
        foreach ($targets as $state) {
            $this->send($state, $payload);
        }
    }

    private function strike(ClientState $state, string $code): void
    {
        $state->strikes++;
        if ($state->strikes >= self::MAX_STRIKES) {
            $this->closeClient($state, CloseCodes::POLICY_VIOLATION, 'too_many_invalid_messages');
            return;
        }
        $this->send($state, ['type' => 'error', 'code' => $code]);
    }

    private function send(ClientState $state, array $payload): void
    {
        if ($state->closing) {
            return;
        }
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            $state->connection->send($json);
        }
    }

    private function closeClient(ClientState $state, int $code, string $reason): void
    {
        if ($state->closing) {
            return;
        }
        $state->closing = true;

        $this->logger->info('ws.close', ['conn' => $state->id, 'user' => $state->identity?->userId, 'code' => $code, 'reason' => $reason]);

        // Close frame payload: 2-byte status code + short UTF-8 reason (<=123 bytes)
        $state->connection->close(new Frame(pack('n', $code) . substr($reason, 0, 120), true, Frame::OP_CLOSE));
    }

    private function now(): float
    {
        return ($this->clock)();
    }
}
