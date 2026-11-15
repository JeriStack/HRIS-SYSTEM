<?php

/**
 * SupportNotificationService
 * Sends in-system & real-time notifications for support tickets and confidential complaints.
 * Strictly enforces COI exclusion and suppresses sensitive details for confidential complaints.
 */

namespace App\Services;

use App\Models\Notification;
use App\Models\SupportTicketHandler;
use App\Models\User;
use App\Realtime\EventAuthorizer;

class SupportNotificationService
{
    private Notification $notifications;
    private User $users;
    private SupportTicketHandler $handlers;

    public function __construct(
        ?Notification $notifications = null,
        ?User $users = null,
        ?SupportTicketHandler $handlers = null
    ) {
        $pdo = $handlers?->getDb();
        $this->notifications = $notifications ?? new Notification($pdo);
        $this->users = $users ?? new User($pdo);
        $this->handlers = $handlers ?? new SupportTicketHandler($pdo);
    }

    /**
     * Notify submitting employee and eligible non-conflicted HR/escalated handlers on creation.
     *
     * @param array<string, mixed> $ticket
     */
    public function notifyTicketSubmitted(array $ticket): void
    {
        $ticketId = (int)($ticket['id'] ?? 0);
        $ticketRef = (string)($ticket['ticket_ref'] ?? '');
        $isConf = $this->isConfidential($ticket);
        $subjectEmpId = isset($ticket['subject_employee_id']) && $ticket['subject_employee_id'] !== null
            ? (int)$ticket['subject_employee_id']
            : null;
        $submitterUserId = (int)($ticket['submitted_by_user_id'] ?? 0);

        // 1. Confirmation to submitting employee
        if ($submitterUserId > 0) {
            if ($isConf) {
                $this->notifications->create(
                    $submitterUserId,
                    "Confidential Complaint Logged ({$ticketRef})",
                    "Your confidential workplace complaint ({$ticketRef}) has been logged securely for restricted authorized review.",
                    'info',
                    EventAuthorizer::SUPPORT_SUBMITTED,
                    $ticketId
                );
            } else {
                $this->notifications->create(
                    $submitterUserId,
                    "Support Request Submitted ({$ticketRef})",
                    "Your request ({$ticketRef}) has been submitted and queued for HR review.",
                    'info',
                    EventAuthorizer::SUPPORT_SUBMITTED,
                    $ticketId
                );
            }
        }

        // 2. Alert non-conflicted eligible handlers ONLY (never expose sensitive complaint text)
        if ($isConf) {
            $eligibleHr = $this->handlers->getEligibleConfidentialHandlers($subjectEmpId, true);
            $recipientIds = [];

            foreach ($eligibleHr as $handler) {
                $uid = (int)($handler['user_id'] ?? 0);
                if ($uid > 0 && $uid !== $submitterUserId) {
                    $recipientIds[$uid] = true;
                }
            }

            // Include non-conflicted escalation admins when flagged for escalation
            if (!empty($ticket['escalated_flag'])) {
                foreach ($this->handlers->getEligibleEscalationAdmins($subjectEmpId) as $adminRow) {
                    $uid = (int)($adminRow['user_id'] ?? 0);
                    if ($uid > 0 && $uid !== $submitterUserId) {
                        $recipientIds[$uid] = true;
                    }
                }
            }

            $msg = !empty($ticket['escalated_flag'])
                ? "An escalated confidential workplace case ({$ticketRef}) requires authorized oversight. Log in to NEXORA HR to review."
                : "A new confidential workplace case ({$ticketRef}) requires authorized review. Log in to NEXORA HR to access.";

            foreach (array_keys($recipientIds) as $uid) {
                $this->notifications->create(
                    $uid,
                    "Confidential Case Alert ({$ticketRef})",
                    $msg,
                    'warning',
                    EventAuthorizer::SUPPORT_COMPLAINT_ALERT,
                    $ticketId
                );
            }
            return;
        }

        // Standard support requests: notify active non-conflicted HR staff (excluding submitter if HR)
        foreach ($this->handlers->getAssignableStandardHandlers($subjectEmpId) as $staff) {
            if (strtolower((string)($staff['role'] ?? '')) !== 'hr') {
                continue;
            }
            $uid = (int)($staff['user_id'] ?? 0);
            if ($uid <= 0 || $uid === $submitterUserId) {
                continue;
            }
            $this->notifications->create(
                $uid,
                "New Support Request ({$ticketRef})",
                "New {$ticket['category']} request ({$ticketRef}) submitted for HR review.",
                'info',
                EventAuthorizer::SUPPORT_SUBMITTED,
                $ticketId
            );
        }
    }

    /**
     * Notify employee when HR posts a visible response or requests additional information.
     *
     * @param array<string, mixed> $ticket
     */
    public function notifyHrPublicResponse(array $ticket, bool $isInfoRequest = false): void
    {
        $owner = $this->resolveActiveOwnerUser($ticket);
        if (!$owner) {
            return;
        }

        $ticketId = (int)($ticket['id'] ?? 0);
        $ticketRef = (string)($ticket['ticket_ref'] ?? '');
        $isConf = $this->isConfidential($ticket);
        $eventType = $isInfoRequest ? EventAuthorizer::SUPPORT_INFO_REQUESTED : EventAuthorizer::SUPPORT_HR_RESPONDED;

        if ($isInfoRequest) {
            $title = $isConf ? "Information Requested ({$ticketRef})" : "Action Needed: Information Requested ({$ticketRef})";
            $message = $isConf
                ? "Additional information has been requested on your confidential case ({$ticketRef}). Log in to NEXORA HR to respond."
                : "HR requested additional information on your support request ({$ticketRef}).";
            $type = 'warning';
        } else {
            $title = $isConf ? "Case Update Posted ({$ticketRef})" : "New HR Response ({$ticketRef})";
            $message = $isConf
                ? "An authorized handler posted a response on your confidential case ({$ticketRef}). Log in to NEXORA HR to read."
                : "HR posted a new response on your support request ({$ticketRef}).";
            $type = 'info';
        }

        $this->notifications->create((int)$owner['id'], $title, $message, $type, $eventType, $ticketId);
    }

    /**
     * Notify employee when ticket status changes, resolves, or closes.
     *
     * @param array<string, mixed> $ticket
     */
    public function notifyStatusChanged(array $ticket, string $newStatus): void
    {
        $owner = $this->resolveActiveOwnerUser($ticket);
        if (!$owner) {
            return;
        }

        $ticketId = (int)($ticket['id'] ?? 0);
        $ticketRef = (string)($ticket['ticket_ref'] ?? '');
        $isConf = $this->isConfidential($ticket);
        $eventType = $newStatus === 'Waiting for Employee'
            ? EventAuthorizer::SUPPORT_INFO_REQUESTED
            : EventAuthorizer::SUPPORT_STATUS_CHANGED;

        $type = match ($newStatus) {
            'Resolved', 'Closed'   => 'success',
            'Waiting for Employee' => 'warning',
            default                => 'info',
        };

        if ($isConf) {
            $title = "Case Status Updated ({$ticketRef})";
            $message = "Your confidential case ({$ticketRef}) status is now {$newStatus}. Log in to NEXORA HR to review.";
        } else {
            $title = "Support Status: {$newStatus} ({$ticketRef})";
            $message = "Your support request ({$ticketRef}) status changed to {$newStatus}.";
        }

        $this->notifications->create((int)$owner['id'], $title, $message, $type, $eventType, $ticketId);
    }

    /**
     * Notify assigned/eligible non-conflicted HR handler when the employee replies or reopens a ticket.
     *
     * @param array<string, mixed> $ticket
     */
    public function notifyEmployeeReplied(array $ticket, bool $reopened = false): void
    {
        $ticketId = (int)($ticket['id'] ?? 0);
        $ticketRef = (string)($ticket['ticket_ref'] ?? '');
        $isConf = $this->isConfidential($ticket);
        $subjectEmpId = isset($ticket['subject_employee_id']) && $ticket['subject_employee_id'] !== null
            ? (int)$ticket['subject_employee_id']
            : null;
        $submitterUserId = (int)($ticket['submitted_by_user_id'] ?? 0);

        $recipients = $this->resolveHrAttentionRecipients($ticket, $subjectEmpId, $submitterUserId);
        if ($recipients === []) {
            return;
        }

        $title = $isConf
            ? "Confidential Case Reply ({$ticketRef})"
            : ($reopened ? "Support Request Reopened ({$ticketRef})" : "Employee Replied ({$ticketRef})");

        $message = $isConf
            ? "New employee follow-up recorded on confidential case ({$ticketRef}). Log in to NEXORA HR to review."
            : ($reopened
                ? "Employee reopened support request ({$ticketRef}) for follow-up review."
                : "Employee replied to support request ({$ticketRef}).");

        $eventType = $isConf ? EventAuthorizer::SUPPORT_COMPLAINT_ALERT : EventAuthorizer::SUPPORT_EMPLOYEE_REPLIED;

        foreach ($recipients as $uid) {
            $this->notifications->create($uid, $title, $message, 'info', $eventType, $ticketId);
        }
    }

    /**
     * Notify a handler when a support ticket or confidential complaint is assigned to them.
     *
     * @param array<string, mixed> $ticket
     */
    public function notifyHandlerAssigned(array $ticket, int $assigneeUserId): void
    {
        if ($assigneeUserId <= 0) {
            return;
        }

        $ticketId = (int)($ticket['id'] ?? 0);
        $ticketRef = (string)($ticket['ticket_ref'] ?? '');
        $isConf = $this->isConfidential($ticket);

        $title = $isConf ? "Confidential Case Assigned ({$ticketRef})" : "Support Ticket Assigned ({$ticketRef})";
        $message = $isConf
            ? "You have been assigned a confidential workplace case ({$ticketRef}). Log in to NEXORA HR to handle."
            : "You have been assigned support ticket {$ticketRef} ({$ticket['category']}).";
        $eventType = $isConf ? EventAuthorizer::SUPPORT_COMPLAINT_ALERT : EventAuthorizer::SUPPORT_ASSIGNED;

        $this->notifications->create($assigneeUserId, $title, $message, 'warning', $eventType, $ticketId);
    }

    /**
     * @param array<string, mixed> $ticket
     * @return array<string, mixed>|null
     */
    private function resolveActiveOwnerUser(array $ticket): ?array
    {
        $employeeId = (int)($ticket['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            return null;
        }

        $owner = $this->users->findByEmployeeId($employeeId);
        if (!$owner || ($owner['status'] ?? 'Active') !== 'Active') {
            return null;
        }

        return $owner;
    }

    /**
     * Resolve authorized non-conflicted HR recipients for follow-up updates.
     *
     * @param array<string, mixed> $ticket
     * @return array<int, int>
     */
    private function resolveHrAttentionRecipients(array $ticket, ?int $subjectEmpId, int $excludeUserId): array
    {
        $assignedId = (int)($ticket['assigned_to_user_id'] ?? 0);
        if ($assignedId > 0 && $assignedId !== $excludeUserId) {
            $assignedUser = $this->handlers->findCandidateUser($assignedId);
            if ($assignedUser && ($assignedUser['status'] ?? '') === 'Active') {
                $assignedEmpId = isset($assignedUser['employee_id']) && $assignedUser['employee_id'] !== null
                    ? (int)$assignedUser['employee_id']
                    : 0;
                $conflicted = $subjectEmpId !== null && $assignedEmpId > 0 && $assignedEmpId === $subjectEmpId;
                if (!$conflicted) {
                    if (!$this->isConfidential($ticket) || $this->handlers->isAuthorizedConfidentialHandler($assignedId) || (!empty($ticket['escalated_flag']) && $assignedUser['role'] === 'admin')) {
                        return [$assignedId];
                    }
                }
            }
        }

        $ids = [];
        if ($this->isConfidential($ticket)) {
            foreach ($this->handlers->getEligibleConfidentialHandlers($subjectEmpId, true) as $row) {
                $uid = (int)($row['user_id'] ?? 0);
                if ($uid > 0 && $uid !== $excludeUserId) {
                    $ids[$uid] = true;
                }
            }
            if ($ids === [] || !empty($ticket['escalated_flag'])) {
                foreach ($this->handlers->getEligibleEscalationAdmins($subjectEmpId) as $row) {
                    $uid = (int)($row['user_id'] ?? 0);
                    if ($uid > 0 && $uid !== $excludeUserId) {
                        $ids[$uid] = true;
                    }
                }
            }
        } else {
            foreach ($this->handlers->getAssignableStandardHandlers($subjectEmpId) as $row) {
                if (strtolower((string)($row['role'] ?? '')) !== 'hr') {
                    continue;
                }
                $uid = (int)($row['user_id'] ?? 0);
                if ($uid > 0 && $uid !== $excludeUserId) {
                    $ids[$uid] = true;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function isConfidential(array $ticket): bool
    {
        return !empty($ticket['is_confidential']) || (($ticket['request_type'] ?? '') === 'complaint');
    }
}
