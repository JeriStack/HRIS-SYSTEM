<?php

/**
 * Event Bootstrap & Handler Wiring
 * HRIS-SYSTEM — Infrastructure Layer
 */

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Domain\Announcement\Events\AnnouncementArchived;
use App\Domain\Announcement\Events\AnnouncementCreated;
use App\Domain\Announcement\Events\AnnouncementExpired;
use App\Domain\Announcement\Events\AnnouncementPublished;
use App\Domain\Announcement\Events\AnnouncementRead;
use App\Domain\Announcement\Events\AnnouncementScheduled;
use App\Domain\Announcement\Events\AnnouncementUpdated;
use App\Domain\Leave\Events\LeaveApprovedEvent;
use App\Domain\Leave\Events\LeaveRejectedEvent;
use App\Infrastructure\Events\Handlers\AnnouncementEventHandler;
use App\Infrastructure\Events\Handlers\LeaveAuditHandler;
use App\Infrastructure\Events\Handlers\LeaveNotificationHandler;

class EventBootstrap
{
    public static function createConfiguredDispatcher(
        ?LeaveAuditHandler $auditHandler = null,
        ?LeaveNotificationHandler $notificationHandler = null,
        ?AnnouncementEventHandler $announcementHandler = null
    ): EventDispatcher {
        $dispatcher = new EventDispatcher();
        $audit = $auditHandler ?? new LeaveAuditHandler();
        $notification = $notificationHandler ?? new LeaveNotificationHandler();
        $announcement = $announcementHandler ?? new AnnouncementEventHandler();

        $dispatcher->subscribe(LeaveApprovedEvent::class, [$audit, 'handleApproved']);
        $dispatcher->subscribe(LeaveApprovedEvent::class, [$notification, 'handleApproved']);

        $dispatcher->subscribe(LeaveRejectedEvent::class, [$audit, 'handleRejected']);
        $dispatcher->subscribe(LeaveRejectedEvent::class, [$notification, 'handleRejected']);

        $dispatcher->subscribe(AnnouncementCreated::class, [$announcement, 'handleCreated']);
        $dispatcher->subscribe(AnnouncementUpdated::class, [$announcement, 'handleUpdated']);
        $dispatcher->subscribe(AnnouncementScheduled::class, [$announcement, 'handleScheduled']);
        $dispatcher->subscribe(AnnouncementPublished::class, [$announcement, 'handlePublished']);
        $dispatcher->subscribe(AnnouncementArchived::class, [$announcement, 'handleArchived']);
        $dispatcher->subscribe(AnnouncementExpired::class, [$announcement, 'handleExpired']);
        $dispatcher->subscribe(AnnouncementRead::class, [$announcement, 'handleRead']);

        return $dispatcher;
    }
}
