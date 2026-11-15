<?php

/**
 * Leave Notification Domain Event Handler
 * HRIS-SYSTEM — Infrastructure Layer (Employee & Real-time Notifications)
 */

declare(strict_types=1);

namespace App\Infrastructure\Events\Handlers;

use App\Domain\Leave\Events\LeaveApprovedEvent;
use App\Domain\Leave\Events\LeaveRejectedEvent;
use App\Services\ConfidentialLeaveService;
use App\Services\LeaveNotificationService;
use Core\Database;
use Throwable;

class LeaveNotificationHandler
{
    private LeaveNotificationService $notificationService;

    public function __construct(?LeaveNotificationService $notificationService = null)
    {
        $this->notificationService = $notificationService ?? new LeaveNotificationService();
    }

    public function handleApproved(LeaveApprovedEvent $event): void
    {
        $isConf = $this->isConfidential($event->requestId, $event->leaveTypeId);
        $reqPayload = ['id' => $event->requestId, 'employee_id' => $event->employeeId];
        if ($isConf) {
            $reqPayload['is_confidential'] = 1;
        }
        $this->notificationService->leaveDecided(
            $reqPayload,
            'Approved',
            sprintf("Your leave request #LR-%03d was approved by HR.", $event->requestId),
            $isConf
        );
    }

    public function handleRejected(LeaveRejectedEvent $event): void
    {
        $isConf = $this->isConfidential($event->requestId, $event->leaveTypeId);
        $reqPayload = ['id' => $event->requestId, 'employee_id' => $event->employeeId];
        if ($isConf) {
            $reqPayload['is_confidential'] = 1;
        }
        $this->notificationService->leaveDecided(
            $reqPayload,
            'Rejected',
            sprintf(
                "Your leave request #LR-%03d was rejected. Reason: %s",
                $event->requestId,
                $event->rejectionReason
            ),
            $isConf
        );
    }

    private function isConfidential(int $requestId, int $leaveTypeId): bool
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
