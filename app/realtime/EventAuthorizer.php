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
    public const LEAVE_CREATED         = 'leave_request.created';
    public const LEAVE_APPROVED        = 'leave_request.approved';
    public const LEAVE_REJECTED        = 'leave_request.rejected';
    public const PROFILE_UPDATED       = 'employee.profile_updated';
    public const CORRECTION_REQUESTED  = 'profile_correction.requested';
    public const CORRECTION_APPROVED   = 'profile_correction.approved';
    public const CORRECTION_REJECTED   = 'profile_correction.rejected';
    public const SUPPORT_SUBMITTED     = 'support_ticket.submitted';
    public const SUPPORT_HR_RESPONDED  = 'support_ticket.hr_responded';
    public const SUPPORT_INFO_REQUESTED = 'support_ticket.info_requested';
    public const SUPPORT_EMPLOYEE_REPLIED = 'support_ticket.employee_replied';
    public const SUPPORT_STATUS_CHANGED = 'support_ticket.status_changed';
    public const SUPPORT_ASSIGNED       = 'support_ticket.assigned';
    public const SUPPORT_COMPLAINT_ALERT = 'support_complaint.confidential';
    public const HR_SUPPORT_SUBMITTED      = 'hr_support.submitted';
    public const HR_SUPPORT_ADMIN_RESPONDED = 'hr_support.admin_responded';
    public const HR_SUPPORT_INFO_REQUESTED = 'hr_support.info_requested';
    public const HR_SUPPORT_HR_REPLIED     = 'hr_support.hr_replied';
    public const HR_SUPPORT_STATUS_CHANGED = 'hr_support.status_changed';
    public const HR_SUPPORT_ASSIGNED       = 'hr_support.assigned';
    public const HR_SUPPORT_REOPENED       = 'hr_support.reopened';
    public const SECURITY_DEVICE_ALERT   = 'security_device.alert';
    public const UNREAD_COUNT          = 'notification.unread_count';

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
            // HR review queue events are for HR and Admin users
            self::LEAVE_CREATED => $recipient->isHr() || $recipient->isAdmin(),
            // Decisions are only for the employee who owns the leave request
            self::LEAVE_APPROVED, self::LEAVE_REJECTED => (int)($row['owner_matches'] ?? 0) === 1,
            // Profile updates are delivered to the recipient employee
            self::PROFILE_UPDATED => true,
            // Profile correction queue events are for HR/Admin; decisions for recipient employee
            self::CORRECTION_REQUESTED => $recipient->isHr() || $recipient->isAdmin(),
            self::CORRECTION_APPROVED, self::CORRECTION_REJECTED => true,
            self::SUPPORT_SUBMITTED, self::SUPPORT_EMPLOYEE_REPLIED => ($recipient->isHr() || $recipient->isAdmin() || $recipient->isEmployee())
                && (int)($row['support_coi_blocked'] ?? 0) === 0,
            self::SUPPORT_HR_RESPONDED, self::SUPPORT_INFO_REQUESTED, self::SUPPORT_STATUS_CHANGED => !array_key_exists('support_owner_matches', $row)
                || (int)$row['support_owner_matches'] === 1,
            self::SUPPORT_ASSIGNED => ($recipient->isHr() || $recipient->isAdmin())
                && (int)($row['support_coi_blocked'] ?? 0) === 0,
            self::SUPPORT_COMPLAINT_ALERT => ($recipient->isHr() || $recipient->isAdmin())
                && (int)($row['support_coi_blocked'] ?? 0) === 0
                && (!array_key_exists('support_confidential_authorized', $row) || (int)$row['support_confidential_authorized'] === 1),
            self::HR_SUPPORT_SUBMITTED, self::HR_SUPPORT_HR_REPLIED, self::HR_SUPPORT_REOPENED => $recipient->isAdmin(),
            self::HR_SUPPORT_ADMIN_RESPONDED, self::HR_SUPPORT_INFO_REQUESTED, self::HR_SUPPORT_STATUS_CHANGED => $recipient->isHr()
                && (!array_key_exists('hr_support_owner_matches', $row) || (int)$row['hr_support_owner_matches'] === 1),
            self::HR_SUPPORT_ASSIGNED => $recipient->isAdmin()
                || ($recipient->isHr() && (!array_key_exists('hr_support_owner_matches', $row) || (int)$row['hr_support_owner_matches'] === 1)),
            self::SECURITY_DEVICE_ALERT => true,
            default => false,
        };
    }
}
