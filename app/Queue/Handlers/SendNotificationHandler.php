<?php

/**
 * Send Notification Queue Job Handler
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Asynchronously delivers in-app notifications with event-level deduplication.
 */

declare(strict_types=1);

namespace App\Queue\Handlers;

use App\Models\Notification;
use App\Queue\JobInterface;
use InvalidArgumentException;

class SendNotificationHandler implements JobInterface
{
    private ?Notification $notificationModel;

    public function __construct(?Notification $notificationModel = null)
    {
        $this->notificationModel = $notificationModel;
    }

    private function getNotificationModel(): Notification
    {
        return $this->notificationModel ??= new Notification();
    }

    public function handle(array $payload): void
    {
        $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
        $title = trim((string)($payload['title'] ?? ''));
        $message = trim((string)($payload['message'] ?? ''));
        $type = (string)($payload['type'] ?? 'info');
        $eventType = isset($payload['event_type']) ? (string)$payload['event_type'] : null;
        $entityId = isset($payload['entity_id']) ? (int)$payload['entity_id'] : null;

        if ($userId <= 0) {
            throw new InvalidArgumentException("SendNotificationHandler: Missing or invalid 'user_id'.");
        }

        if ($title === '') {
            throw new InvalidArgumentException("SendNotificationHandler: Missing 'title'.");
        }

        if ($message === '') {
            throw new InvalidArgumentException("SendNotificationHandler: Missing 'message'.");
        }

        $model = $this->getNotificationModel();

        // Idempotency check: avoid duplicate notifications for the same event and entity
        if ($eventType !== null && $entityId !== null && $entityId > 0) {
            if ($model->existsForEvent($userId, $eventType, $entityId)) {
                return; // Already recorded, safe no-op
            }
        }

        $model->create($userId, $title, $message, $type, $eventType, $entityId);
    }
}
