<?php

/**
 * DistributeAnnouncementNotificationsHandler
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Asynchronously distributes in-system notifications in bounded batches
 * to targeted active employees when an announcement is published.
 * Enforces per-(user_id, event_type, entity_id) idempotency and never sends bulk emails.
 */

declare(strict_types=1);

namespace App\Queue\Handlers;

use App\Infrastructure\Persistence\Announcement\MySqlAnnouncementRepository;
use App\Models\Notification;
use App\Queue\JobInterface;
use App\Queue\JobRegistry;
use App\Queue\QueueService;
use InvalidArgumentException;

final class DistributeAnnouncementNotificationsHandler implements JobInterface
{
    private ?MySqlAnnouncementRepository $repository;
    private ?Notification $notificationModel;
    private ?QueueService $queueService;

    public function __construct(
        ?MySqlAnnouncementRepository $repository = null,
        ?Notification $notificationModel = null,
        ?QueueService $queueService = null
    ) {
        $this->repository = $repository;
        $this->notificationModel = $notificationModel;
        $this->queueService = $queueService;
    }

    public function handle(array $payload): void
    {
        $announcementId = isset($payload['announcement_id']) ? (int)$payload['announcement_id'] : 0;
        if ($announcementId <= 0) {
            throw new InvalidArgumentException("DistributeAnnouncementNotificationsHandler: Missing or invalid 'announcement_id'.");
        }

        $offset = max(0, (int)($payload['offset'] ?? 0));
        $defaultBatch = (int)config('announcements.notification_batch_size', 100);
        $batchSize = max(1, min(500, (int)($payload['batch_size'] ?? $defaultBatch)));

        $repo = $this->repository ?? new MySqlAnnouncementRepository();
        $announcement = $repo->findById($announcementId);

        // If the announcement was deleted, archived, or is no longer active/published, safely no-op.
        if ($announcement === null || !$announcement->isVisibleInEmployeeFeed()) {
            return;
        }

        $userIds = $repo->findTargetedUserIdsBatch($announcementId, $offset, $batchSize);
        if (empty($userIds)) {
            return;
        }

        $notifModel = $this->notificationModel ?? new Notification();
        $isUrgent = $announcement->priority()->isUrgent();
        $isImportant = $announcement->priority()->isImportant();

        $eventType = $isUrgent ? 'announcement.urgent' : 'announcement.published';
        $notifLevel = $isUrgent ? 'warning' : ($isImportant ? 'info' : 'info');
        $prefix = $isUrgent ? '[Urgent] ' : ($isImportant ? '[Important] ' : '');
        $title = mb_substr($prefix . 'Announcement: ' . $announcement->title()->value(), 0, 180);
        $message = sprintf(
            'New %s announcement published: "%s". Open Announcements to read the full notice.',
            $announcement->category()->value(),
            $announcement->title()->value()
        );

        foreach ($userIds as $userId) {
            if ($userId <= 0) {
                continue;
            }
            // Idempotency guard: never create duplicate notifications for the same announcement & user
            if ($notifModel->existsForEvent($userId, 'announcement.published', $announcementId)) {
                continue;
            }
            if ($notifModel->existsForEvent($userId, 'announcement.urgent', $announcementId)) {
                continue;
            }

            $notifModel->create(
                $userId,
                $title,
                $message,
                $notifLevel,
                $eventType,
                $announcementId
            );
        }

        // If a full batch was returned, enqueue the next batch continuation job
        if (count($userIds) === $batchSize) {
            $queue = $this->queueService ?? QueueService::getInstance();
            $queue->push(JobRegistry::TYPE_DISTRIBUTE_ANNOUNCEMENT, [
                'announcement_id' => $announcementId,
                'offset'          => $offset + $batchSize,
                'batch_size'      => $batchSize,
            ]);
        }
    }
}
