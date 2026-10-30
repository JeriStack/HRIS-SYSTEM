<?php
/**
 * Authenticated WebSocket Identity
 * Employee HRIS & Leave Management System
 *
 * Built exclusively from server-side data (ticket row joined with the users
 * table). Nothing in here is ever taken from client-supplied message fields.
 */

namespace App\Realtime;

final class Identity
{
    public function __construct(
        public readonly int $userId,
        public readonly string $role,
        public readonly ?int $employeeId,
        public readonly string $sessionRef,
        public readonly int $sessionExpiresAt,
    ) {
    }

    public function isHr(): bool
    {
        return $this->role === 'hr';
    }

    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }
}
