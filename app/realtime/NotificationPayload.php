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
    ];

    /**
     * @param array{id:int|string, event_type:string, entity_id:int|string|null, created_ts:int|string|null} $row
     */
    public static function fromRow(array $row): ?array
    {
        $event = (string)($row['event_type'] ?? '');
        if (!isset(self::TEMPLATES[$event])) {
            return null;
        }

        $template = self::TEMPLATES[$event];
        $entityId = isset($row['entity_id']) ? (int)$row['entity_id'] : 0;
        $reference = $entityId > 0 ? 'LR-' . str_pad((string)$entityId, 3, '0', STR_PAD_LEFT) : null;
        $createdTs = isset($row['created_ts']) ? (int)$row['created_ts'] : time();

        return [
            'type'  => 'event',
            'event' => $event,
            'id'    => 'notification:' . (int)$row['id'],
            'data'  => [
                'notification_id' => (int)$row['id'],
                'title'           => $template['title'],
                'message'         => sprintf($template['message'], $reference !== null ? "#{$reference}" : ''),
                'level'           => $template['level'],
                'reference'       => $reference,
                'link'            => $entityId > 0 ? 'leaves/' . $entityId : 'notifications',
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
