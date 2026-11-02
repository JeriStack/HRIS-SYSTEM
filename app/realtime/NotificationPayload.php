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

        if ($event === EventAuthorizer::PROFILE_UPDATED) {
            $reference = $entityId > 0 ? 'EMP-' . str_pad((string)$entityId, 3, '0', STR_PAD_LEFT) : null;
            $isHrRecipient = strtolower((string)($row['recipient_role'] ?? '')) === 'hr';
            if ($isHrRecipient) {
                $title = 'Employee record updated';
                $message = 'Employee record %s was updated.';
                $link = $entityId > 0 ? 'hr/employees/' . $entityId : 'hr/employees';
            } else {
                $link = 'employee/profile';
            }
        } else {
            $reference = $entityId > 0 ? 'LR-' . str_pad((string)$entityId, 3, '0', STR_PAD_LEFT) : null;
            $link = $entityId > 0 ? 'leaves/' . $entityId : 'notifications';
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

    public static function unreadCount(int $count): array
    {
        return [
            'type'  => EventAuthorizer::UNREAD_COUNT,
            'event' => EventAuthorizer::UNREAD_COUNT,
            'data'  => ['count' => max(0, $count)],
        ];
    }
}
