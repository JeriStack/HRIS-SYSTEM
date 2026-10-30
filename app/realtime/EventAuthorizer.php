<?php
/**
 * Server-side Real-time Event Authorization
 * Employee HRIS & Leave Management System
 *
 * Every committed notification row is re-authorized against the live recipient
 * identity before it is pushed. The row fields consumed here come from the
 * database (see PdoRealtimeStore::fetchNotificationsAfter), never from clients.
 */

namespace App\Realtime;

final class EventAuthorizer
{
    public const LEAVE_CREATED      = 'leave_request.created';
    public const LEAVE_APPROVED     = 'leave_request.approved';
    public const LEAVE_REJECTED     = 'leave_request.rejected';
    public const PROFILE_UPDATED    = 'employee.profile_updated';
    public const UNREAD_COUNT       = 'notification.unread_count';

    public const LEAVE_EVENTS = [
        self::LEAVE_CREATED,
        self::LEAVE_APPROVED,
        self::LEAVE_REJECTED,
    ];

    /**
     * Decide whether a notification event may be delivered to a connection.
     *
     * @param array{user_id:int|string, event_type:?string, recipient_role:?string,
     *              recipient_status:?string, owner_matches:int|string|null} $row
     */
    public function canDeliver(Identity $recipient, array $row): bool
    {
        // 1. Notifications are private: only the row owner can ever receive it
        if ((int)($row['user_id'] ?? 0) !== $recipient->userId) {
            return false;
        }

        // 2. Recipient account must still be active with the role it connected with
        if (($row['recipient_status'] ?? null) !== 'Active') {
            return false;
        }
        if (strtolower((string)($row['recipient_role'] ?? '')) !== $recipient->role) {
            return false;
        }

        // 3. Event-specific rules
        return match ($row['event_type'] ?? null) {
            // HR review queue events are for HR users only
            self::LEAVE_CREATED => $recipient->isHr(),
            // Decisions are only for the employee who owns the leave request
            self::LEAVE_APPROVED, self::LEAVE_REJECTED => (int)($row['owner_matches'] ?? 0) === 1,
            // Profile updates are delivered to the recipient employee
            self::PROFILE_UPDATED => true,
            default => false,
        };
    }
}
