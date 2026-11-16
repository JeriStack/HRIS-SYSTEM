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
    private MailService $mailer;

    public function __construct(
        ?Notification $notifications = null,
        ?User $users = null,
        ?SupportTicketHandler $handlers = null,
        ?MailService $mailer = null
    ) {
        $pdo = $handlers?->getDb();
        $this->notifications = $notifications ?? new Notification($pdo);
        $this->users = $users ?? new User($pdo);
        $this->handlers = $handlers ?? new SupportTicketHandler($pdo);
        $this->mailer = $mailer ?? new MailService();
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

        // Standard & public login support requests: notify active non-conflicted HR staff (excluding submitter if HR)
        $isPublicTicket = $this->isPublicTicket($ticket);
        foreach ($this->handlers->getAssignableStandardHandlers($subjectEmpId) as $staff) {
            if (strtolower((string)($staff['role'] ?? '')) !== 'hr') {
                continue;
            }
            $uid = (int)($staff['user_id'] ?? 0);
            if ($uid <= 0 || $uid === $submitterUserId) {
                continue;
            }
            $title = $isPublicTicket
                ? "Public Login Support Request ({$ticketRef})"
                : "New Support Request ({$ticketRef})";
            $message = $isPublicTicket
                ? "New pre-login {$ticket['category']} ticket ({$ticketRef}) submitted via Public Support for HR review."
                : "New {$ticket['category']} request ({$ticketRef}) submitted for HR review.";

            $this->notifications->create(
                $uid,
                $title,
                $message,
                'info',
                EventAuthorizer::SUPPORT_SUBMITTED,
                $ticketId
            );
        }
    }

    /**
     * Notify employee (or email unauthenticated public requester) when HR posts a visible response
     * or requests additional information.
     *
     * @param array<string, mixed> $ticket
     */
    public function notifyHrPublicResponse(array $ticket, bool $isInfoRequest = false, ?string $publicMessageExcerpt = null): void
    {
        if ($this->isPublicTicket($ticket)) {
            $ticketRef = (string)($ticket['ticket_ref'] ?? '');
            $subjectLine = $isInfoRequest
                ? "Action Needed on Your NEXORA HR Support Request ({$ticketRef})"
                : "HR Support Update on Your Request ({$ticketRef})";
            $headline = $isInfoRequest
                ? 'Additional Information Requested'
                : 'New Response from HR Support';
            $intro = $isInfoRequest
                ? "An HR Support specialist requested additional information on your login assistance ticket <strong>" . e($ticketRef) . '</strong>.'
                : "An HR Support specialist posted a response on your login assistance ticket <strong>" . e($ticketRef) . '</strong>.';
            $this->sendPublicRequesterUpdateEmail($ticket, $subjectLine, $headline, $intro, $publicMessageExcerpt);
            return;
        }

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
     * Notify employee (or email public requester) when ticket status changes, resolves, or closes.
     *
     * @param array<string, mixed> $ticket
     */
    public function notifyStatusChanged(array $ticket, string $newStatus, ?string $publicRemarks = null): void
    {
        if ($this->isPublicTicket($ticket)) {
            $ticketRef = (string)($ticket['ticket_ref'] ?? '');
            $displayStatus = $newStatus === 'Waiting for Employee' ? 'Waiting for Requester' : $newStatus;
            $this->sendPublicRequesterUpdateEmail(
                $ticket,
                "Support Ticket Status Updated: {$displayStatus} ({$ticketRef})",
                "Ticket Status Updated: {$displayStatus}",
                "Your login assistance request <strong>" . e($ticketRef) . '</strong> is now marked as <strong>' . e($displayStatus) . '</strong>.',
                $publicRemarks
            );
            return;
        }

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

    /**
     * @param array<string, mixed> $ticket
     */
    private function isPublicTicket(array $ticket): bool
    {
        return !empty($ticket['is_public_ticket']) || (($ticket['support_channel'] ?? '') === 'public_auth');
    }

    /**
     * Notify public requester synchronously by email without revealing internal notes, handlers, or account details.
     *
     * @param array<string, mixed> $ticket
     */
    private function sendPublicRequesterUpdateEmail(
        array $ticket,
        string $emailSubject,
        string $headline,
        string $introHtml,
        ?string $publicExcerpt = null
    ): void {
        $email = strtolower(trim((string)($ticket['requester_email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $name = trim((string)($ticket['requester_name'] ?? 'Requester'));
        if ($name === '') {
            $name = 'Requester';
        }

        $appName = e((string)config('config.app_name', 'NEXORA HR'));
        $safeName = e($name);
        $safeHeadline = e($headline);
        $ticketRef = e((string)($ticket['ticket_ref'] ?? ''));
        $trackHubUrl = e(absolute_url('/auth/support/track'));
        $year = date('Y');

        $excerptBlock = '';
        $excerptText = '';
        if ($publicExcerpt !== null && trim($publicExcerpt) !== '') {
            $cleanExcerpt = mb_substr(trim($publicExcerpt), 0, 1200);
            $excerptBlock = '<div style="margin:14px 0;padding:12px 14px;background:#F8FAFC;border:1px solid #CBD5E1;border-radius:6px;font-size:13px;color:#0F172A;">'
                . '<div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#475569;margin-bottom:6px;">Message Summary</div>'
                . nl2br(e($cleanExcerpt))
                . '</div>';
            $excerptText = "\n\nUpdate Summary:\n{$cleanExcerpt}";
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>{$safeHeadline}</title></head>
<body style="margin:0;padding:24px;background:#F1F5F9;font-family:Arial,Helvetica,sans-serif;color:#0F172A;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;margin:0 auto;background:#FFFFFF;border:1px solid #CBD5E1;border-radius:10px;overflow:hidden;">
    <tr>
      <td style="background:#0F2C59;padding:20px 24px;color:#FFFFFF;">
        <div style="font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#93C5FD;font-weight:700;">{$appName} Login Support</div>
        <h1 style="margin:6px 0 0;font-size:18px;font-weight:700;color:#FFFFFF;">{$safeHeadline}</h1>
      </td>
    </tr>
    <tr>
      <td style="padding:24px;font-size:14px;line-height:1.6;">
        <p style="margin:0 0 12px;">Hello {$safeName},</p>
        <p style="margin:0 0 12px;">{$introHtml}</p>
        {$excerptBlock}
        <p style="margin:14px 0 0;font-size:13px;color:#334155;">Open the private tracking link included in your original confirmation email (or enter your tracking token at <a href="{$trackHubUrl}" style="color:#1D4ED8;">Public Support Tracker</a>) to review your ticket thread and post a reply.</p>
      </td>
    </tr>
    <tr>
      <td style="padding:14px 24px;background:#F8FAFC;border-top:1px solid #E2E8F0;font-size:12px;color:#64748B;">
        Automated update for ticket <strong>{$ticketRef}</strong> from {$appName} &copy; {$year}. HR will never ask for your password or OTP code.
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        $altBody = "Hello {$name},\n\n"
            . strip_tags($introHtml)
            . $excerptText
            . "\n\nOpen the private tracking link from your confirmation email or visit " . absolute_url('/auth/support/track') . " with your token.\n"
            . "HR will never ask for your password or OTP code.\n";

        try {
            $this->mailer->sendMail($email, $name, $emailSubject, $html, $altBody);
        } catch (\Throwable $e) {
            error_log('[SupportNotification] Public requester email failed: ' . get_class($e));
        }
    }
}
