<?php
/**
 * Persistence boundary for the WebSocket service.
 * MySQL is the source of truth; the WebSocket process only reads committed
 * data, consumes tickets, and purges stale tickets.
 */

namespace App\Realtime\Contracts;

use App\Realtime\Identity;

interface RealtimeStore
{
    /**
     * Atomically consume a single-use ticket (by SHA-256 hash) and resolve the
     * identity from the users table. Returns null for unknown, expired,
     * revoked, reused tickets or inactive accounts.
     */
    public function consumeTicket(string $tokenHash): ?Identity;

    /**
     * Check live connections against current DB state.
     *
     * @param array<int, array{user_id:int, role:string, session_ref:string}> $sessions keyed by connection id
     * @return array<int, string> connection id => reason ('revoked'|'inactive'|'role_changed') for invalid ones
     */
    public function findInvalidSessions(array $sessions): array;

    public function unreadCount(int $userId): int;

    public function latestNotificationId(): int;

    /**
     * Committed notification rows with id > $afterId, joined with recipient state.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchNotificationsAfter(int $afterId, int $limit): array;

    public function purgeTickets(int $olderThanSeconds): int;

    /**
     * Throws if the database or required tables are unavailable.
     */
    public function assertReady(): void;
}
