<?php

namespace Tests\Support;

use App\Realtime\Contracts\RealtimeStore;
use App\Realtime\Identity;

/**
 * Deterministic RealtimeStore double mirroring PdoRealtimeStore semantics:
 * single-use, expiring, revocable tickets and DB-derived identities.
 */
class InMemoryRealtimeStore implements RealtimeStore
{
    /** @var array<int, array{role:string, status:string, employee_id:?int}> */
    public array $users = [];

    /** @var array<string, array{user_id:int, session_ref:string, session_expires_at:int, expires_at:int, used:bool, revoked:bool}> */
    public array $tickets = [];

    /** @var array<int, int> userId => unread count */
    public array $unread = [];

    /** @var array<int, array<string,mixed>> */
    public array $notifications = [];

    public int $now;

    public function __construct()
    {
        $this->now = time();
    }

    public function addUser(int $id, string $role, string $status = 'Active', ?int $employeeId = null): void
    {
        $this->users[$id] = ['role' => $role, 'status' => $status, 'employee_id' => $employeeId];
    }

    /**
     * Issue a ticket and return the raw value (like RealtimeTicket::issue()).
     */
    public function issueTicket(int $userId, string $sessionRef = '', int $ttl = 30, ?int $sessionExpiresAt = null): string
    {
        $raw = bin2hex(random_bytes(32));
        $this->tickets[hash('sha256', $raw)] = [
            'user_id'            => $userId,
            'session_ref'        => $sessionRef !== '' ? $sessionRef : bin2hex(random_bytes(16)),
            'session_expires_at' => $sessionExpiresAt ?? ($this->now + 1800),
            'expires_at'         => $this->now + $ttl,
            'used'               => false,
            'revoked'            => false,
        ];
        return $raw;
    }

    public function revokeSession(string $sessionRef): void
    {
        foreach ($this->tickets as &$ticket) {
            if ($ticket['session_ref'] === $sessionRef) {
                $ticket['revoked'] = true;
            }
        }
        unset($ticket);
    }

    public function sessionRefOf(string $rawTicket): string
    {
        return $this->tickets[hash('sha256', $rawTicket)]['session_ref'];
    }

    public function consumeTicket(string $tokenHash): ?Identity
    {
        $ticket = $this->tickets[$tokenHash] ?? null;
        if ($ticket === null || $ticket['used'] || $ticket['revoked']
            || $ticket['expires_at'] <= $this->now || $ticket['session_expires_at'] <= $this->now) {
            return null;
        }
        $this->tickets[$tokenHash]['used'] = true;

        $user = $this->users[$ticket['user_id']] ?? null;
        if ($user === null || $user['status'] !== 'Active' || !in_array($user['role'], ['hr', 'employee'], true)) {
            return null;
        }

        return new Identity($ticket['user_id'], $user['role'], $user['employee_id'], $ticket['session_ref'], $ticket['session_expires_at']);
    }

    public function findInvalidSessions(array $sessions): array
    {
        $invalid = [];
        foreach ($sessions as $connectionId => $session) {
            $refTickets = array_filter($this->tickets, static fn ($t) => $t['session_ref'] === $session['session_ref']);
            $revoked = $refTickets === [] || (bool)array_filter($refTickets, static fn ($t) => $t['revoked']);
            $user = $this->users[$session['user_id']] ?? null;

            if ($revoked) {
                $invalid[$connectionId] = 'revoked';
            } elseif ($user === null || $user['status'] !== 'Active') {
                $invalid[$connectionId] = 'inactive';
            } elseif ($user['role'] !== $session['role']) {
                $invalid[$connectionId] = 'role_changed';
            }
        }
        return $invalid;
    }

    public function unreadCount(int $userId): int
    {
        return $this->unread[$userId] ?? 0;
    }

    public function latestNotificationId(): int
    {
        return $this->notifications ? max(array_map(static fn ($n) => (int)$n['id'], $this->notifications)) : 0;
    }

    public function fetchNotificationsAfter(int $afterId, int $limit): array
    {
        $rows = array_values(array_filter($this->notifications, static fn ($n) => (int)$n['id'] > $afterId));
        usort($rows, static fn ($a, $b) => (int)$a['id'] <=> (int)$b['id']);
        return array_slice($rows, 0, $limit);
    }

    public function purgeTickets(int $olderThanSeconds): int
    {
        return 0;
    }

    public function assertReady(): void
    {
    }
}
