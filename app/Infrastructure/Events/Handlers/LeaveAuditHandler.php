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
use App\Services\ConfidentialLeaveService;
use Core\Database;
use Throwable;

class LeaveAuditHandler
{
    private ActivityLog $activityLog;

    public function __construct(?ActivityLog $activityLog = null)
    {
        $this->activityLog = $activityLog ?? new ActivityLog();
    }

    public function handleApproved(LeaveApprovedEvent $event): void
    {
        // Idempotent: the controller records this audit row immediately; a later outbox
        // re-delivery of the same event must not add a duplicate LEAVE_APPROVED entry.
        if ($this->activityLog->existsForEntity('LEAVE_APPROVED', 'LeaveRequest', (string)$event->requestId)) {
            return;
        }

        $isConfidential = $this->isRequestConfidential($event->requestId, $event->leaveTypeId);
        $desc = ConfidentialLeaveService::safeActivityDescription(
            $isConfidential,
            $event->requestId,
            'Approved',
            sprintf("Approved leave request #%d", $event->requestId)
        );

        $this->activityLog->log(
            $event->approverUserId,
            'LEAVE_APPROVED',
            $desc,
            'LeaveRequest',
            (string)$event->requestId
        );
    }

    public function handleRejected(LeaveRejectedEvent $event): void
    {
        if ($this->activityLog->existsForEntity('LEAVE_REJECTED', 'LeaveRequest', (string)$event->requestId)) {
            return;
        }

        $isConfidential = $this->isRequestConfidential($event->requestId, $event->leaveTypeId);
        $desc = ConfidentialLeaveService::safeActivityDescription(
            $isConfidential,
            $event->requestId,
            'Rejected',
            sprintf("Rejected leave request #%d. Reason: %s", $event->requestId, $event->rejectionReason)
        );

        $this->activityLog->log(
            $event->approverUserId,
            'LEAVE_REJECTED',
            $desc,
            'LeaveRequest',
            (string)$event->requestId
        );
    }

    private function isRequestConfidential(int $requestId, int $leaveTypeId): bool
    {
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare("
                SELECT COALESCE(lr.is_confidential, 0) AS req_conf,
                       COALESCE(lt.is_confidential, 0) AS type_conf,
                       lt.leave_code
                FROM leave_types lt
                LEFT JOIN leave_requests lr ON lr.id = :rid
                WHERE lt.id = :ltid
                LIMIT 1
            ");
            $stmt->execute(['rid' => $requestId, 'ltid' => $leaveTypeId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return false;
            }
            return !empty($row['req_conf']) || !empty($row['type_conf']) || ConfidentialLeaveService::isConfidentialType($row);
        } catch (Throwable) {
            return false;
        }
    }
}
