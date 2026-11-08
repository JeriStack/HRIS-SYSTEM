<?php
/**
 * Leave Workflow Notification Service
 * Employee HRIS & Leave Management System
 *
 * Writes notification rows (the persistent record and the real-time outbox).
 * Call these methods from inside the Leave model's transaction callbacks so
 * notifications commit or roll back together with the leave change; the
 * WebSocket service only ever sees committed rows.
 */

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Realtime\EventAuthorizer;

class LeaveNotificationService
{
    private ?Notification $notifications;
    private ?User $users;

    public function __construct(?Notification $notifications = null, ?User $users = null)
    {
        $this->notifications = $notifications;
        $this->users = $users;
    }

    /**
     * Notify every active HR user that a leave request was submitted.
     *
     * @return int Number of HR recipients
     */
    public function leaveSubmitted(int $requestId, string $message): int
    {
        return $this->notifications()->notifyRole(
            'hr',
            'New Leave Application',
            $message,
            'info',
            EventAuthorizer::LEAVE_CREATED,
            $requestId
        );
    }

    /**
     * Notify only the employee who owns the leave request about HR's decision.
     *
     * @param array $request Leave request row (requires id, employee_id)
     * @param string $status 'Approved' or 'Rejected'
     * @return bool True when a notification row was written
     */
    public function leaveDecided(array $request, string $status, string $message): bool
    {
        $employeeId = (int)($request['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            return false;
        }

        $owner = $this->users()->findByEmployeeId($employeeId);
        if (!$owner || ($owner['status'] ?? 'Active') !== 'Active') {
            return false;
        }

        $approved = $status === 'Approved';
        $eventType = $approved ? EventAuthorizer::LEAVE_APPROVED : EventAuthorizer::LEAVE_REJECTED;
        $requestId = (int)($request['id'] ?? 0);

        // Idempotency: the controller writes this notification immediately, and the
        // transactional outbox worker may later deliver the same domain event. Writing
        // it only once keeps the employee from seeing duplicate decision notifications.
        if ($requestId > 0 && $this->notifications()->existsForEvent((int)$owner['id'], $eventType, $requestId)) {
            return false;
        }

        $notificationId = $this->notifications()->create(
            (int)$owner['id'],
            $approved ? 'Leave Request Approved' : 'Leave Request Rejected',
            $message,
            $approved ? 'success' : 'warning',
            $approved ? EventAuthorizer::LEAVE_APPROVED : EventAuthorizer::LEAVE_REJECTED,
            (int)$request['id']
        );

        // Queue asynchronous email notification to the employee if an email address exists
        if (!empty($owner['email']) && class_exists(\App\Queue\QueueService::class)) {
            try {
                \App\Queue\QueueService::getInstance()->push(
                    \App\Queue\JobRegistry::TYPE_SEND_EMAIL,
                    [
                        'to'      => $owner['email'],
                        'subject' => $approved ? 'Leave Request Approved' : 'Leave Request Decision Update',
                        'body'    => '<p>Hello ' . htmlspecialchars((string)($owner['name'] ?? $owner['username']), ENT_QUOTES, 'UTF-8') . ',</p><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>',
                        'name'    => (string)($owner['name'] ?? $owner['username'] ?? ''),
                    ]
                );
            } catch (\Throwable $mailEx) {
                error_log('[LeaveNotificationService] Asynchronous email queue non-fatal: ' . $mailEx->getMessage());
            }
        }

        return $notificationId > 0;
    }

    private function notifications(): Notification
    {
        return $this->notifications ??= new Notification();
    }

    private function users(): User
    {
        return $this->users ??= new User();
    }
}
