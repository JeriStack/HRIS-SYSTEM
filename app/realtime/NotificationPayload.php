<?php
/**
 * Generic Real-time Notification Payload Builder
 * Employee HRIS & Leave Management System
 *
 * Payloads intentionally contain no employee names, leave types, dates,
 * reasons, or rejection notes. Clients that need detail fetch it through the
 * authenticated HTTP endpoints, which apply the normal RBAC/ownership rules.
 */

namespace App\Realtime;

final class NotificationPayload
{
    private const TEMPLATES = [
        EventAuthorizer::LEAVE_CREATED => [
            'title'   => 'New leave request',
            'message' => 'A leave request %s is awaiting HR review.',
            'level'   => 'info',
        ],
        EventAuthorizer::LEAVE_APPROVED => [
            'title'   => 'Leave request approved',
            'message' => 'Your leave request %s was approved.',
            'level'   => 'success',
        ],
        EventAuthorizer::LEAVE_REJECTED => [
            'title'   => 'Leave request not approved',
            'message' => 'Your leave request %s was not approved. Open it for details.',
            'level'   => 'warning',
        ],
        EventAuthorizer::PROFILE_UPDATED => [
            'title'   => 'Profile updated',
            'message' => 'Your employee profile record was updated by Human Resources.',
            'level'   => 'info',
        ],
        EventAuthorizer::CORRECTION_REQUESTED => [
            'title'   => 'Profile correction request',
            'message' => 'A profile correction request %s is awaiting HR review.',
            'level'   => 'info',
        ],
        EventAuthorizer::CORRECTION_APPROVED => [
            'title'   => 'Profile correction approved',
            'message' => 'Your profile correction request %s was approved.',
            'level'   => 'success',
        ],
        EventAuthorizer::CORRECTION_REJECTED => [
            'title'   => 'Profile correction not approved',
            'message' => 'Your profile correction request %s was not approved. Open My Profile for details.',
            'level'   => 'warning',
        ],
        EventAuthorizer::SUPPORT_SUBMITTED => [
            'title'   => 'Support request received',
            'message' => 'A support request update %s was recorded in the Support Inbox.',
            'level'   => 'info',
        ],
        EventAuthorizer::SUPPORT_HR_RESPONDED => [
            'title'   => 'Support response posted',
            'message' => 'HR posted a response on support ticket %s.',
            'level'   => 'info',
        ],
        EventAuthorizer::SUPPORT_INFO_REQUESTED => [
            'title'   => 'Additional information requested',
            'message' => 'HR requested additional information on support ticket %s.',
            'level'   => 'warning',
        ],
        EventAuthorizer::SUPPORT_EMPLOYEE_REPLIED => [
            'title'   => 'Employee reply posted',
            'message' => 'An employee posted a reply on support ticket %s.',
            'level'   => 'info',
        ],
        EventAuthorizer::SUPPORT_STATUS_CHANGED => [
            'title'   => 'Support ticket status updated',
            'message' => 'The status of your support ticket %s was updated. Open Help & Support for details.',
            'level'   => 'info',
        ],
        EventAuthorizer::SUPPORT_ASSIGNED => [
            'title'   => 'Support ticket assigned',
            'message' => 'Support ticket %s has been assigned for handling.',
            'level'   => 'info',
        ],
        EventAuthorizer::SUPPORT_COMPLAINT_ALERT => [
            'title'   => 'Confidential complaint notice',
            'message' => 'A confidential workplace complaint record %s requires authorized review in NEXORA HR.',
            'level'   => 'warning',
        ],
        EventAuthorizer::HR_SUPPORT_SUBMITTED => [
            'title'   => 'New HR Support Ticket',
            'message' => 'An HR support ticket %s was submitted for Administrator review.',
            'level'   => 'info',
        ],
        EventAuthorizer::HR_SUPPORT_ADMIN_RESPONDED => [
            'title'   => 'Administrator Response Posted',
            'message' => 'An Administrator posted a response on HR support ticket %s.',
            'level'   => 'info',
        ],
        EventAuthorizer::HR_SUPPORT_INFO_REQUESTED => [
            'title'   => 'Additional Information Requested',
            'message' => 'An Administrator requested additional details on HR support ticket %s.',
            'level'   => 'warning',
        ],
        EventAuthorizer::HR_SUPPORT_HR_REPLIED => [
            'title'   => 'HR Follow-Up Received',
            'message' => 'HR posted a follow-up update on support ticket %s.',
            'level'   => 'info',
        ],
        EventAuthorizer::HR_SUPPORT_STATUS_CHANGED => [
            'title'   => 'HR Support Ticket Updated',
            'message' => 'The status of HR support ticket %s was updated.',
            'level'   => 'info',
        ],
        EventAuthorizer::HR_SUPPORT_ASSIGNED => [
            'title'   => 'HR Support Ticket Assigned',
            'message' => 'Assignment was updated for HR support ticket %s.',
            'level'   => 'info',
        ],
        EventAuthorizer::HR_SUPPORT_REOPENED => [
            'title'   => 'HR Ticket Reopen Requested',
            'message' => 'HR requested reopening of support ticket %s.',
            'level'   => 'warning',
        ],
        EventAuthorizer::ANNOUNCEMENT_PUBLISHED => [
            'title'   => 'New company announcement',
            'message' => 'Official announcement %s has been published. Open Announcements to read it.',
            'level'   => 'info',
        ],
        EventAuthorizer::ANNOUNCEMENT_URGENT => [
            'title'   => 'Urgent company announcement',
            'message' => 'Urgent advisory %s requires your attention in Announcements.',
            'level'   => 'warning',
        ],
    ];

    /**
     * @param array{id:int|string, event_type:string, entity_id:int|string|null, created_ts:int|string|null, recipient_role?:string|null} $row
     */
    public static function fromRow(array $row): ?array
    {
        $event = (string)($row['event_type'] ?? '');
        if (!isset(self::TEMPLATES[$event])) {
            return null;
        }

        $template = self::TEMPLATES[$event];
        $entityId = isset($row['entity_id']) ? (int)$row['entity_id'] : 0;
        $createdTs = isset($row['created_ts']) ? (int)$row['created_ts'] : time();

        $title = $template['title'];
        $message = $template['message'];

        $role = strtolower((string)($row['recipient_role'] ?? ''));
        $hrSupportRef = !empty($row['hr_support_ref']) ? (string)$row['hr_support_ref'] : null;
        $link = self::linkFor($event, $entityId, $role, $hrSupportRef);

        if ($event === EventAuthorizer::PROFILE_UPDATED) {
            $reference = $entityId > 0 ? 'EMP-' . str_pad((string)$entityId, 3, '0', STR_PAD_LEFT) : null;
            if ($role === 'hr') {
                $title = 'Employee record updated';
                $message = 'Employee record %s was updated.';
            }
        } elseif (str_starts_with($event, 'hr_support.')) {
            $reference = $hrSupportRef;
        } elseif (
            $event === EventAuthorizer::CORRECTION_REQUESTED
            || $event === EventAuthorizer::CORRECTION_APPROVED
            || $event === EventAuthorizer::CORRECTION_REJECTED
        ) {
            $reference = $entityId > 0 ? 'PCR-' . str_pad((string)$entityId, 3, '0', STR_PAD_LEFT) : null;
        } elseif (str_starts_with($event, 'support_')) {
            $reference = $entityId > 0 ? 'SUP-' . str_pad((string)$entityId, 5, '0', STR_PAD_LEFT) : null;
        } elseif (str_starts_with($event, 'announcement.')) {
            $reference = $entityId > 0 ? 'ANN-' . str_pad((string)$entityId, 4, '0', STR_PAD_LEFT) : null;
        } else {
            $reference = $entityId > 0 ? 'LR-' . str_pad((string)$entityId, 3, '0', STR_PAD_LEFT) : null;
        }
        if (str_contains($message, '%s')) {
            $message = sprintf($message, $reference !== null ? "#{$reference}" : '');
        }

        return [
            'type'  => 'event',
            'event' => $event,
            'id'    => 'notification:' . (int)$row['id'],
            'data'  => [
                'notification_id' => (int)$row['id'],
                'title'           => $title,
                'message'         => $message,
                'level'           => $template['level'],
                'reference'       => $reference,
                'link'            => $link,
                'created_at'      => gmdate('Y-m-d\TH:i:s\Z', $createdTs),
            ],
        ];
    }

    /**
     * App-relative path (no leading slash) that a notification should open, or null
     * when the event has no dedicated page.
     */
    public static function linkFor(?string $event, int $entityId, string $recipientRole = '', ?string $ticketRef = null): ?string
    {
        $event = (string)$event;
        if (!isset(self::TEMPLATES[$event])) {
            return null;
        }

        if (str_starts_with($event, 'hr_support.')) {
            $base = strtolower($recipientRole) === 'admin' ? 'admin/support' : 'hr/help';
            return ($ticketRef !== null && $ticketRef !== '')
                ? $base . '/tickets/' . rawurlencode($ticketRef)
                : $base;
        }

        if ($event === EventAuthorizer::PROFILE_UPDATED) {
            if (strtolower($recipientRole) === 'hr') {
                return $entityId > 0 ? 'hr/employees/' . $entityId : 'hr/employees';
            }
            return 'employee/profile';
        }

        if (
            $event === EventAuthorizer::CORRECTION_REQUESTED
            || $event === EventAuthorizer::CORRECTION_APPROVED
            || $event === EventAuthorizer::CORRECTION_REJECTED
        ) {
            return $event === EventAuthorizer::CORRECTION_REQUESTED
                ? 'hr/correction-requests'
                : 'employee/profile#correction-requests';
        }

        if (str_starts_with($event, 'support_')) {
            $isStaff = in_array(strtolower($recipientRole), ['hr', 'admin'], true);
            $base = $isStaff ? 'hr/support' : 'support';
            return $entityId > 0 ? $base . '/' . $entityId : $base;
        }

        if (str_starts_with($event, 'announcement.')) {
            $base = strtolower($recipientRole) === 'hr' ? 'hr/announcements' : 'employee/announcements';
            return $entityId > 0 ? $base . '/' . $entityId : $base;
        }

        return $entityId > 0 ? 'leaves/' . $entityId : 'notifications';
    }

    public static function unreadCount(int $count): array
    {
        return [
            'type'  => EventAuthorizer::UNREAD_COUNT,
            'event' => EventAuthorizer::UNREAD_COUNT,
            'data'  => ['count' => max(0, $count)],
        ];
    }
}
