<?php

/**
 * Leave Notification Domain Event Handler
 * HRIS-SYSTEM — Infrastructure Layer (Employee & Real-time Notifications)
 */

declare(strict_types=1);

namespace App\Infrastructure\Events\Handlers;

use App\Domain\Leave\Events\LeaveApprovedEvent;
use App\Domain\Leave\Events\LeaveRejectedEvent;
use App\Services\LeaveNotificationService;

class LeaveNotificationHandler
{
    private LeaveNotificationService $notificationService;

    public function __construct(?LeaveNotificationService $notificationService = null)
    {
        $this->notificationService = $notificationService ?? new LeaveNotificationService();
    }

    public function handleApproved(LeaveApprovedEvent $event): void
    {
        $this->notificationService->leaveDecided(
            ['id' => $event->requestId, 'employee_id' => $event->employeeId],
            'Approved',
            sprintf("Your leave request #LR-%03d was approved by HR.", $event->requestId)
        );
    }

    public function handleRejected(LeaveRejectedEvent $event): void
    {
        $this->notificationService->leaveDecided(
            ['id' => $event->requestId, 'employee_id' => $event->employeeId],
            'Rejected',
            sprintf(
                "Your leave request #LR-%03d was rejected. Reason: %s",
                $event->requestId,
                $event->rejectionReason
            )
        );
    }
}
