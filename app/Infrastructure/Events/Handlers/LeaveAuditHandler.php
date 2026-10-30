<?php

/**
 * Leave Audit Domain Event Handler
 * HRIS-SYSTEM — Infrastructure Layer (Audit Logging)
 */

declare(strict_types=1);

namespace App\Infrastructure\Events\Handlers;

use App\Domain\Leave\Events\LeaveApprovedEvent;
use App\Domain\Leave\Events\LeaveRejectedEvent;
use App\Models\ActivityLog;

class LeaveAuditHandler
{
    private ActivityLog $activityLog;

    public function __construct(?ActivityLog $activityLog = null)
    {
        $this->activityLog = $activityLog ?? new ActivityLog();
    }

    public function handleApproved(LeaveApprovedEvent $event): void
    {
        $this->activityLog->log(
            $event->approverUserId,
            'LEAVE_APPROVED',
            sprintf("Approved leave request #%d", $event->requestId),
            'LeaveRequest',
            (string)$event->requestId
        );
    }

    public function handleRejected(LeaveRejectedEvent $event): void
    {
        $this->activityLog->log(
            $event->approverUserId,
            'LEAVE_REJECTED',
            sprintf("Rejected leave request #%d. Reason: %s", $event->requestId, $event->rejectionReason),
            'LeaveRequest',
            (string)$event->requestId
        );
    }
}
