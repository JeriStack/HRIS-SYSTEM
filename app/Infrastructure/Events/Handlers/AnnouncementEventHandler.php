<?php

/**
 * Announcement Domain Event Handler
 * HRIS-SYSTEM — Infrastructure Layer (Notifications, Queue & Audit Trail)
 */

declare(strict_types=1);

namespace App\Infrastructure\Events\Handlers;

use App\Domain\Announcement\Events\AnnouncementArchived;
use App\Domain\Announcement\Events\AnnouncementCreated;
use App\Domain\Announcement\Events\AnnouncementExpired;
use App\Domain\Announcement\Events\AnnouncementPublished;
use App\Domain\Announcement\Events\AnnouncementRead;
use App\Domain\Announcement\Events\AnnouncementScheduled;
use App\Domain\Announcement\Events\AnnouncementUpdated;
use App\Models\ActivityLog;
use App\Queue\JobRegistry;
use App\Queue\QueueService;
use Core\AuditService;
use Throwable;

class AnnouncementEventHandler
{
    private ActivityLog $activityLog;
    private AuditService $auditService;
    private QueueService $queueService;

    public function __construct(
        ?ActivityLog $activityLog = null,
        ?AuditService $auditService = null,
        ?QueueService $queueService = null
    ) {
        $this->activityLog = $activityLog ?? new ActivityLog();
        $this->auditService = $auditService ?? AuditService::getInstance();
        $this->queueService = $queueService ?? QueueService::getInstance();
    }

    public function handleCreated(AnnouncementCreated $event): void
    {
        $action = $event->status === 'Draft' ? 'ANNOUNCEMENT_DRAFT_SAVED' : 'ANNOUNCEMENT_CREATED';
        if ($this->activityLog->existsForEntity($action, 'Announcement', (string)$event->announcementId)) {
            return;
        }

        $this->auditService->record([
            'action'        => $action,
            'category'      => AuditService::CATEGORY_ANNOUNCEMENT,
            'description'   => sprintf('Created announcement #%d (%s)', $event->announcementId, $event->status),
            'actor_user_id' => $event->authorUserId,
            'entity_type'   => 'Announcement',
            'entity_id'     => (string)$event->announcementId,
            'new_values'    => ['status' => $event->status],
        ]);
    }

    public function handleUpdated(AnnouncementUpdated $event): void
    {
        $this->auditService->record([
            'action'        => 'ANNOUNCEMENT_EDITED',
            'category'      => AuditService::CATEGORY_ANNOUNCEMENT,
            'description'   => sprintf('Updated announcement #%d (Status: %s)', $event->announcementId, $event->status),
            'actor_user_id' => $event->actorUserId,
            'entity_type'   => 'Announcement',
            'entity_id'     => (string)$event->announcementId,
            'new_values'    => ['status' => $event->status],
        ]);
    }

    public function handleScheduled(AnnouncementScheduled $event): void
    {
        $this->auditService->record([
            'action'        => 'ANNOUNCEMENT_SCHEDULED',
            'category'      => AuditService::CATEGORY_ANNOUNCEMENT,
            'description'   => sprintf('Scheduled announcement #%d for %s', $event->announcementId, $event->scheduledAt),
            'actor_user_id' => $event->actorUserId,
            'entity_type'   => 'Announcement',
            'entity_id'     => (string)$event->announcementId,
            'new_values'    => [
                'status'       => 'Scheduled',
                'scheduled_at' => $event->scheduledAt,
            ],
        ]);
    }

    public function handlePublished(AnnouncementPublished $event): void
    {
        if (!$this->activityLog->existsForEntity('ANNOUNCEMENT_PUBLISHED', 'Announcement', (string)$event->announcementId)) {
            $this->auditService->record([
                'action'        => 'ANNOUNCEMENT_PUBLISHED',
                'category'      => AuditService::CATEGORY_ANNOUNCEMENT,
                'description'   => sprintf(
                    'Published announcement #%d (Priority: %s, Published At: %s)',
                    $event->announcementId,
                    $event->priority,
                    $event->publishedAt
                ),
                'actor_user_id' => $event->actorUserId > 0 ? $event->actorUserId : null,
                'entity_type'   => 'Announcement',
                'entity_id'     => (string)$event->announcementId,
                'new_values'    => [
                    'status'       => 'Published',
                    'priority'     => $event->priority,
                    'published_at' => $event->publishedAt,
                ],
            ]);
        }

        // Submit notification distribution work to the existing Queue Worker (Phase 8.2 & 8.4).
        // Failures in notification queueing or delivery must never reverse announcement publication.
        try {
            $batchSize = (int)config('announcements.notification_batch_size', 100);
            $this->queueService->push(JobRegistry::TYPE_DISTRIBUTE_ANNOUNCEMENT, [
                'announcement_id' => $event->announcementId,
                'offset'          => 0,
                'batch_size'      => $batchSize,
            ]);
        } catch (Throwable $e) {
            error_log('[AnnouncementEventHandler::handlePublished Queue Error] ' . $e->getMessage());
        }
    }

    public function handleArchived(AnnouncementArchived $event): void
    {
        if ($this->activityLog->existsForEntity('ANNOUNCEMENT_ARCHIVED', 'Announcement', (string)$event->announcementId)) {
            return;
        }

        $this->auditService->record([
            'action'        => 'ANNOUNCEMENT_ARCHIVED',
            'category'      => AuditService::CATEGORY_ANNOUNCEMENT,
            'description'   => sprintf('Archived announcement #%d', $event->announcementId),
            'actor_user_id' => $event->actorUserId > 0 ? $event->actorUserId : null,
            'entity_type'   => 'Announcement',
            'entity_id'     => (string)$event->announcementId,
            'new_values'    => ['status' => 'Archived'],
        ]);
    }

    public function handleExpired(AnnouncementExpired $event): void
    {
        if ($this->activityLog->existsForEntity('ANNOUNCEMENT_EXPIRED', 'Announcement', (string)$event->announcementId)) {
            return;
        }

        $this->auditService->record([
            'action'        => 'ANNOUNCEMENT_EXPIRED',
            'category'      => AuditService::CATEGORY_ANNOUNCEMENT,
            'description'   => sprintf('Announcement #%d transitioned to Expired', $event->announcementId),
            'actor_user_id' => null,
            'actor_role'    => 'system',
            'entity_type'   => 'Announcement',
            'entity_id'     => (string)$event->announcementId,
            'new_values'    => ['status' => 'Expired'],
        ]);
    }

    public function handleRead(AnnouncementRead $event): void
    {
        // Lightweight non-sensitive audit record for first read receipt
        $this->auditService->record([
            'action'      => 'ANNOUNCEMENT_READ',
            'category'    => AuditService::CATEGORY_ANNOUNCEMENT,
            'description' => sprintf('Employee #%d read announcement #%d', $event->employeeId, $event->announcementId),
            'entity_type' => 'Announcement',
            'entity_id'   => (string)$event->announcementId,
            'new_values'  => ['employee_id' => $event->employeeId],
        ]);
    }
}
