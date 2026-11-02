<?php

/**
 * Event Bootstrap & Handler Wiring
 * HRIS-SYSTEM — Infrastructure Layer
 */

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Domain\Leave\Events\LeaveApprovedEvent;
use App\Domain\Leave\Events\LeaveRejectedEvent;
use App\Infrastructure\Events\Handlers\LeaveAuditHandler;
use App\Infrastructure\Events\Handlers\LeaveNotificationHandler;

class EventBootstrap
{
    public static function createConfiguredDispatcher(
        ?LeaveAuditHandler $auditHandler = null,
        ?LeaveNotificationHandler $notificationHandler = null
    ): EventDispatcher {
        $dispatcher = new EventDispatcher();
        $audit = $auditHandler ?? new LeaveAuditHandler();
        $notification = $notificationHandler ?? new LeaveNotificationHandler();

        $dispatcher->subscribe(LeaveApprovedEvent::class, [$audit, 'handleApproved']);
        $dispatcher->subscribe(LeaveApprovedEvent::class, [$notification, 'handleApproved']);

        $dispatcher->subscribe(LeaveRejectedEvent::class, [$audit, 'handleRejected']);
        $dispatcher->subscribe(LeaveRejectedEvent::class, [$notification, 'handleRejected']);

        return $dispatcher;
    }
}
