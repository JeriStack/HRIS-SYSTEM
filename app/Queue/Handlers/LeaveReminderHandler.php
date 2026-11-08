<?php

/**
 * Leave Reminder Queue Job Handler
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Sends reminders for upcoming approved leaves starting within the reminder window.
 */

declare(strict_types=1);

namespace App\Queue\Handlers;

use App\Models\Notification;
use App\Models\User;
use App\Queue\JobInterface;
use Core\Database;
use PDO;

class LeaveReminderHandler implements JobInterface
{
    private PDO $db;
    private Notification $notifications;
    private User $users;

    public function __construct(?PDO $db = null, ?Notification $notifications = null, ?User $users = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->notifications = $notifications ?? new Notification();
        $this->users = $users ?? new User();
    }

    public function handle(array $payload): void
    {
        $daysAhead = max(1, (int)($payload['days_ahead'] ?? 1));
        $targetDate = date('Y-m-d', strtotime("+{$daysAhead} days"));

        // Query approved leaves starting on targetDate
        $stmt = $this->db->prepare("
            SELECT lr.id, lr.employee_id, lr.start_date, lr.end_date, lr.number_of_days,
                   lt.leave_name, e.first_name, e.last_name
            FROM leave_requests lr
            JOIN leave_types lt ON lt.id = lr.leave_type_id
            JOIN employees e ON e.id = lr.employee_id
            WHERE lr.status = 'Approved'
              AND lr.start_date = :target_date
        ");
        $stmt->execute(['target_date' => $targetDate]);
        $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $eventType = 'leave_request.reminder';

        foreach ($leaves as $leave) {
            $reqId = (int)$leave['id'];
            $empId = (int)$leave['employee_id'];

            $user = $this->users->findByEmployeeId($empId);
            if (!$user || ($user['status'] ?? 'Active') !== 'Active') {
                continue;
            }

            $userId = (int)$user['id'];

            // Idempotency: skip if reminder already sent for this request ID
            if ($this->notifications->existsForEvent($userId, $eventType, $reqId)) {
                continue;
            }

            $msg = sprintf(
                "Reminder: Your scheduled leave '%s' starts tomorrow (%s) for %s day(s).",
                $leave['leave_name'],
                $leave['start_date'],
                $leave['number_of_days']
            );

            $this->notifications->create(
                $userId,
                'Upcoming Leave Reminder',
                $msg,
                'info',
                $eventType,
                $reqId
            );
        }
    }
}
