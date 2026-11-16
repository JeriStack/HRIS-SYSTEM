<?php

/**
 * HrSupportNotificationService
 * Sends in-app notifications (with WebSocket event routing) and redacted SMTP/Mailpit emails
 * for HR-to-Admin Help & Support workflows without leaking sensitive ticket text or internal notes.
 */

namespace App\Services;

use App\Models\HrSupportTicket;
use App\Models\Notification;
use App\Models\User;
use App\Realtime\EventAuthorizer;
use PDO;
use Throwable;

class HrSupportNotificationService
{
    private Notification $notifications;
    private User $users;
    private HrSupportTicket $tickets;
    private MailService $mailer;
    private PDO $pdo;

    /** @var array<int, string> */
    private array $dispatchErrors = [];

    public function __construct(
        ?Notification $notifications = null,
        ?User $users = null,
        ?HrSupportTicket $tickets = null,
        ?MailService $mailer = null,
        ?PDO $pdo = null
    ) {
        $resolvedPdo = $pdo ?? ($tickets?->getDb() ?? \Core\Database::getInstance()->getConnection());
        $this->pdo = $resolvedPdo;
        $this->tickets = $tickets ?? new HrSupportTicket($this->pdo);
        $this->notifications = $notifications ?? new Notification($this->pdo);
        $this->users = $users ?? new User($this->pdo);
        $this->mailer = $mailer ?? new MailService();
    }

    /**
     * Retrieve any non-blocking dispatch error messages captured during notification delivery.
     *
     * @return array<int, string>
     */
    public function getDispatchErrors(): array
    {
        return $this->dispatchErrors;
    }

    /**
     * Notify active Administrators when HR submits a new support ticket.
     *
     * @param array<string, mixed> $ticket
     */
    public function notifyTicketSubmitted(array $ticket): void
    {
        $ticketId = (int)($ticket['id'] ?? 0);
        $ticketRef = (string)($ticket['ticket_ref'] ?? '');
        $priority = (string)($ticket['priority'] ?? 'Medium');
        $category = (string)($ticket['category'] ?? 'Other');
        $submitterId = (int)($ticket['submitted_by_user_id'] ?? 0);

        $recipients = $this->resolveAdminRecipients($ticket, $submitterId, true);
        if ($recipients === []) {
            return;
        }

        $title = "New HR Support Ticket ({$ticketRef})";
        $message = "HR submitted support ticket {$ticketRef} [{$category} • {$priority}] for Administrator review.";
        $level = in_array($priority, ['Critical', 'High'], true) ? 'warning' : 'info';

        foreach ($recipients as $admin) {
            $adminId = (int)($admin['user_id'] ?? 0);
            if ($adminId <= 0) {
                continue;
            }

            $this->safeCreateNotification(
                $adminId,
                $title,
                $message,
                $level,
                EventAuthorizer::HR_SUPPORT_SUBMITTED,
                $ticketId
            );

            $this->safeSendRedactedEmail(
                $admin,
                "[HR Support] New Ticket {$ticketRef} ({$priority})",
                "New HR Support Ticket ({$ticketRef})",
                "An HR personnel member submitted support ticket <strong>" . e($ticketRef) . "</strong> requiring Administrator review.",
                $ticket,
                '/admin/support/tickets/' . rawurlencode($ticketRef),
                'Review Ticket in Admin Portal'
            );
        }
    }

    /**
     * Notify Administrators when HR sends a follow-up reply or requests reopening.
     *
     * @param array<string, mixed> $ticket
     */
    public function notifyHrReplied(array $ticket, bool $reopened = false): void
    {
        $ticketId = (int)($ticket['id'] ?? 0);
        $ticketRef = (string)($ticket['ticket_ref'] ?? '');
        $submitterId = (int)($ticket['submitted_by_user_id'] ?? 0);

        $recipients = $this->resolveAdminRecipients($ticket, $submitterId, false);
        if ($recipients === []) {
            return;
        }

        if ($reopened) {
            $title = "Ticket Reopen Requested ({$ticketRef})";
            $message = "HR requested reopening of support ticket {$ticketRef}. Open Admin Support Management to investigate.";
            $eventType = EventAuthorizer::HR_SUPPORT_REOPENED;
            $level = 'warning';
            $emailSubject = "[HR Support] Ticket Reopened: {$ticketRef}";
            $headline = "HR Reopened Ticket ({$ticketRef})";
            $introHtml = "HR requested reopening for support ticket <strong>" . e($ticketRef) . "</strong> because the issue requires further attention.";
        } else {
            $title = "HR Replied on Ticket ({$ticketRef})";
            $message = "HR posted a follow-up message on support ticket {$ticketRef}. Open Admin Support Management to review.";
            $eventType = EventAuthorizer::HR_SUPPORT_HR_REPLIED;
            $level = 'info';
            $emailSubject = "[HR Support] New HR Reply on {$ticketRef}";
            $headline = "New HR Follow-Up ({$ticketRef})";
            $introHtml = "HR posted a follow-up update on support ticket <strong>" . e($ticketRef) . "</strong>.";
        }

        foreach ($recipients as $admin) {
            $adminId = (int)($admin['user_id'] ?? 0);
            if ($adminId <= 0) {
                continue;
            }

            $this->safeCreateNotification($adminId, $title, $message, $level, $eventType, $ticketId);

            $this->safeSendRedactedEmail(
                $admin,
                $emailSubject,
                $headline,
                $introHtml,
                $ticket,
                '/admin/support/tickets/' . rawurlencode($ticketRef),
                'Open Ticket Thread'
            );
        }
    }

    /**
     * Notify the submitting HR user when an Admin posts an HR-visible response or requests info.
     * Internal Admin notes NEVER invoke this method.
     *
     * @param array<string, mixed> $ticket
     */
    public function notifyAdminResponded(array $ticket, bool $requestedInfo = false, ?string $newStatus = null): void
    {
        $owner = $this->resolveActiveSubmitterUser($ticket);
        if ($owner === null) {
            return;
        }

        $ticketId = (int)($ticket['id'] ?? 0);
        $ticketRef = (string)($ticket['ticket_ref'] ?? '');
        $statusLabel = $newStatus !== null && $newStatus !== '' ? $newStatus : (string)($ticket['status'] ?? '');

        if ($requestedInfo || $statusLabel === 'Waiting for HR') {
            $title = "Information Requested ({$ticketRef})";
            $message = "An Administrator requested additional details on HR support ticket {$ticketRef}. Sign in to view the message.";
            $eventType = EventAuthorizer::HR_SUPPORT_INFO_REQUESTED;
            $level = 'warning';
            $emailSubject = "[HR Support] Additional Info Requested ({$ticketRef})";
            $headline = "Additional Information Requested";
            $introHtml = "An Administrator requested additional details on your HR support ticket <strong>" . e($ticketRef) . "</strong>.";
        } else {
            $title = "Admin Responded ({$ticketRef})";
            $message = "An Administrator posted a response on your HR support ticket {$ticketRef}. Sign in to view the update.";
            $eventType = EventAuthorizer::HR_SUPPORT_ADMIN_RESPONDED;
            $level = 'info';
            $emailSubject = "[HR Support] Administrator Response ({$ticketRef})";
            $headline = "New Administrator Response";
            $introHtml = "An Administrator posted an official response on your HR support ticket <strong>" . e($ticketRef) . "</strong>.";
        }

        $this->safeCreateNotification((int)$owner['user_id'], $title, $message, $level, $eventType, $ticketId);

        $this->safeSendRedactedEmail(
            $owner,
            $emailSubject,
            $headline,
            $introHtml,
            $ticket,
            '/hr/help/tickets/' . rawurlencode($ticketRef),
            'View Ticket in HR Support Center'
        );
    }

    /**
     * Notify HR when Admin changes ticket status (acknowledges, moves In Progress, requests info, resolves, closes, or reopens).
     *
     * @param array<string, mixed> $ticket
     */
    public function notifyTicketStatusChanged(array $ticket, string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        $owner = $this->resolveActiveSubmitterUser($ticket);
        if ($owner === null) {
            return;
        }

        $ticketId = (int)($ticket['id'] ?? 0);
        $ticketRef = (string)($ticket['ticket_ref'] ?? '');

        $isAcknowledgement = ($oldStatus === 'Submitted' && $newStatus === 'Under Review');
        if ($isAcknowledgement) {
            $title = "Ticket Acknowledged ({$ticketRef})";
            $message = "An Administrator acknowledged ticket {$ticketRef} and moved it to Under Review.";
            $eventType = EventAuthorizer::HR_SUPPORT_STATUS_CHANGED;
            $level = 'info';
            $emailSubject = "[HR Support] Ticket Acknowledged ({$ticketRef})";
            $headline = "Ticket Acknowledged by Administrator";
            $introHtml = "Your HR support ticket <strong>" . e($ticketRef) . "</strong> has been acknowledged and is now marked <strong>Under Review</strong>.";
        } elseif ($newStatus === 'Waiting for HR') {
            $title = "Waiting for HR ({$ticketRef})";
            $message = "Ticket {$ticketRef} requires additional information from HR before work can continue.";
            $eventType = EventAuthorizer::HR_SUPPORT_INFO_REQUESTED;
            $level = 'warning';
            $emailSubject = "[HR Support] Action Needed on {$ticketRef}";
            $headline = "Ticket Awaiting Your Response";
            $introHtml = "Your HR support ticket <strong>" . e($ticketRef) . "</strong> has been updated to <strong>Waiting for HR</strong>.";
        } elseif ($newStatus === 'Resolved') {
            $title = "Ticket Resolved ({$ticketRef})";
            $message = "Ticket {$ticketRef} has been marked Resolved by an Administrator. Please verify and confirm resolution.";
            $eventType = EventAuthorizer::HR_SUPPORT_STATUS_CHANGED;
            $level = 'success';
            $emailSubject = "[HR Support] Ticket Resolved ({$ticketRef})";
            $headline = "Support Ticket Marked Resolved";
            $introHtml = "Your HR support ticket <strong>" . e($ticketRef) . "</strong> has been marked <strong>Resolved</strong>. Please sign in to verify and confirm closure.";
        } elseif ($newStatus === 'Closed') {
            $title = "Ticket Closed ({$ticketRef})";
            $message = "Ticket {$ticketRef} is now Closed.";
            $eventType = EventAuthorizer::HR_SUPPORT_STATUS_CHANGED;
            $level = 'success';
            $emailSubject = "[HR Support] Ticket Closed ({$ticketRef})";
            $headline = "Support Ticket Closed";
            $introHtml = "Your HR support ticket <strong>" . e($ticketRef) . "</strong> has been marked <strong>Closed</strong>.";
        } else {
            $title = "Ticket Status: {$newStatus} ({$ticketRef})";
            $message = "Your HR support ticket {$ticketRef} status changed from {$oldStatus} to {$newStatus}.";
            $eventType = EventAuthorizer::HR_SUPPORT_STATUS_CHANGED;
            $level = 'info';
            $emailSubject = "[HR Support] Status Updated: {$newStatus} ({$ticketRef})";
            $headline = "Ticket Status Updated: {$newStatus}";
            $introHtml = "Your HR support ticket <strong>" . e($ticketRef) . "</strong> transitioned from <strong>" . e($oldStatus) . "</strong> to <strong>" . e($newStatus) . "</strong>.";
        }

        $this->safeCreateNotification((int)$owner['user_id'], $title, $message, $level, $eventType, $ticketId);

        $this->safeSendRedactedEmail(
            $owner,
            $emailSubject,
            $headline,
            $introHtml,
            $ticket,
            '/hr/help/tickets/' . rawurlencode($ticketRef),
            'Open Ticket Details'
        );
    }

    /**
     * Notify the newly assigned Administrator and the HR submitter when assignment changes.
     *
     * @param array<string, mixed> $ticket
     */
    public function notifyTicketAssigned(
        array $ticket,
        ?int $previousAdminId,
        ?int $newAdminId,
        int $actorUserId = 0
    ): void {
        if ($previousAdminId === $newAdminId) {
            return;
        }

        $ticketId = (int)($ticket['id'] ?? 0);
        $ticketRef = (string)($ticket['ticket_ref'] ?? '');
        $assigneeLabel = 'Unassigned';

        if ($newAdminId !== null && $newAdminId > 0) {
            $assignee = $this->resolveActiveUserRow($newAdminId);
            if ($assignee !== null) {
                $assigneeLabel = $assignee['display_name'];
                if ($newAdminId !== $actorUserId) {
                    $this->safeCreateNotification(
                        $newAdminId,
                        "Ticket Assigned ({$ticketRef})",
                        "You have been assigned HR support ticket {$ticketRef}. Sign in to investigate.",
                        'info',
                        EventAuthorizer::HR_SUPPORT_ASSIGNED,
                        $ticketId
                    );

                    $this->safeSendRedactedEmail(
                        $assignee,
                        "[HR Support] Ticket Assigned to You ({$ticketRef})",
                        "HR Support Ticket Assigned ({$ticketRef})",
                        "You have been assigned to handle HR support ticket <strong>" . e($ticketRef) . "</strong>.",
                        $ticket,
                        '/admin/support/tickets/' . rawurlencode($ticketRef),
                        'Open Assigned Ticket'
                    );
                }
            }
        }

        $owner = $this->resolveActiveSubmitterUser($ticket);
        if ($owner !== null) {
            $hrMessage = ($newAdminId !== null && $newAdminId > 0)
                ? "Your HR support ticket {$ticketRef} has been assigned to an Administrator ({$assigneeLabel})."
                : "Assignment on your HR support ticket {$ticketRef} returned to the Administrator queue.";

            $this->safeCreateNotification(
                (int)$owner['user_id'],
                "Ticket Assignment Updated ({$ticketRef})",
                $hrMessage,
                'info',
                EventAuthorizer::HR_SUPPORT_ASSIGNED,
                $ticketId
            );

            $hrIntro = ($newAdminId !== null && $newAdminId > 0)
                ? "Your HR support ticket <strong>" . e($ticketRef) . "</strong> has been assigned to Administrator <strong>" . e($assigneeLabel) . "</strong>."
                : "Your HR support ticket <strong>" . e($ticketRef) . "</strong> is currently queued for Administrator assignment.";

            $this->safeSendRedactedEmail(
                $owner,
                "[HR Support] Assignment Update ({$ticketRef})",
                "Ticket Assignment Updated",
                $hrIntro,
                $ticket,
                '/hr/help/tickets/' . rawurlencode($ticketRef),
                'View Ticket Status'
            );
        }
    }

    /**
     * Resolve active Admin recipients. On new ticket creation notify all active Admins (or assigned Admin if pre-assigned).
     *
     * @param array<string, mixed> $ticket
     * @return array<int, array{user_id:int, email:string, display_name:string}>
     */
    private function resolveAdminRecipients(array $ticket, int $excludeUserId, bool $notifyAllWhenNew): array
    {
        $assignedId = (int)($ticket['assigned_admin_id'] ?? 0);
        if (!$notifyAllWhenNew && $assignedId > 0 && $assignedId !== $excludeUserId) {
            $assigned = $this->resolveActiveUserRow($assignedId);
            if ($assigned !== null) {
                return [$assigned];
            }
        }

        $recipients = [];
        foreach ($this->tickets->getAssignableAdmins() as $row) {
            $uid = (int)($row['user_id'] ?? 0);
            if ($uid <= 0 || $uid === $excludeUserId) {
                continue;
            }
            $recipients[$uid] = [
                'user_id'      => $uid,
                'email'        => strtolower(trim((string)($row['email'] ?? ''))),
                'display_name' => $this->formatDisplayName($row),
            ];
        }

        return array_values($recipients);
    }

    /**
     * @param array<string, mixed> $ticket
     * @return array{user_id:int, email:string, display_name:string}|null
     */
    private function resolveActiveSubmitterUser(array $ticket): ?array
    {
        $submitterId = (int)($ticket['submitted_by_user_id'] ?? 0);
        if ($submitterId <= 0) {
            return null;
        }

        return $this->resolveActiveUserRow($submitterId);
    }

    /**
     * @return array{user_id:int, email:string, display_name:string}|null
     */
    private function resolveActiveUserRow(int $userId): ?array
    {
        $user = $this->users->findById($userId);
        if (!$user || ($user['status'] ?? '') !== 'Active') {
            return null;
        }

        return [
            'user_id'      => (int)$user['id'],
            'email'        => strtolower(trim((string)($user['email'] ?? ''))),
            'display_name' => $this->formatDisplayName($user),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function formatDisplayName(array $row): string
    {
        $full = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        if ($full !== '') {
            return $full;
        }
        $username = trim((string)($row['username'] ?? ''));
        return $username !== '' ? $username : 'User';
    }

    private function safeCreateNotification(
        int $userId,
        string $title,
        string $message,
        string $type,
        string $eventType,
        int $ticketId
    ): void {
        try {
            $this->notifications->create($userId, $title, $message, $type, $eventType, $ticketId);
        } catch (Throwable $e) {
            $msg = 'Notification DB write failed: ' . $e->getMessage();
            $this->dispatchErrors[] = $msg;
            error_log('[HrSupportNotification] ' . $msg);
        }
    }

    /**
     * Dispatch a redacted email notification containing ONLY reference, workflow metadata enums, and an authenticated portal link.
     *
     * @param array{user_id:int, email:string, display_name:string} $recipient
     * @param array<string, mixed>                                  $ticket
     */
    private function safeSendRedactedEmail(
        array $recipient,
        string $emailSubject,
        string $headline,
        string $introHtml,
        array $ticket,
        string $relativeUrl,
        string $ctaLabel
    ): void {
        $email = strtolower(trim((string)($recipient['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $recipientName = trim((string)($recipient['display_name'] ?? 'Team Member')) ?: 'Team Member';
        $appName = e((string)config('config.app_name', 'NEXORA HR'));
        $ticketRef = e((string)($ticket['ticket_ref'] ?? ''));
        $category = e((string)($ticket['category'] ?? 'Other'));
        $priority = e((string)($ticket['priority'] ?? 'Medium'));
        $status = e((string)($ticket['status'] ?? 'Submitted'));
        $portalUrl = e(absolute_url($relativeUrl));
        $rawPortalUrl = absolute_url($relativeUrl);
        $safeHeadline = e($headline);
        $safeRecipientName = e($recipientName);
        $safeCta = e($ctaLabel);
        $year = date('Y');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>{$safeHeadline}</title></head>
<body style="margin:0;padding:24px;background:#F1F5F9;font-family:Arial,Helvetica,sans-serif;color:#0F172A;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;margin:0 auto;background:#FFFFFF;border:1px solid #CBD5E1;border-radius:10px;overflow:hidden;">
    <tr>
      <td style="background:#0F2C59;padding:20px 24px;color:#FFFFFF;">
        <div style="font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#93C5FD;font-weight:700;">{$appName} • HR Support Channel</div>
        <h1 style="margin:6px 0 0;font-size:18px;font-weight:700;color:#FFFFFF;">{$safeHeadline}</h1>
      </td>
    </tr>
    <tr>
      <td style="padding:24px;font-size:14px;line-height:1.6;">
        <p style="margin:0 0 12px;">Hello {$safeRecipientName},</p>
        <p style="margin:0 0 16px;">{$introHtml}</p>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 18px;background:#F8FAFC;border:1px solid #CBD5E1;border-radius:6px;font-size:13px;">
          <tr><td style="padding:8px 12px;color:#475569;width:130px;">Ticket Reference</td><td style="padding:8px 12px;font-weight:700;color:#0F172A;">{$ticketRef}</td></tr>
          <tr><td style="padding:8px 12px;color:#475569;border-top:1px solid #E2E8F0;">Category</td><td style="padding:8px 12px;color:#0F172A;border-top:1px solid #E2E8F0;">{$category}</td></tr>
          <tr><td style="padding:8px 12px;color:#475569;border-top:1px solid #E2E8F0;">Priority</td><td style="padding:8px 12px;color:#0F172A;border-top:1px solid #E2E8F0;">{$priority}</td></tr>
          <tr><td style="padding:8px 12px;color:#475569;border-top:1px solid #E2E8F0;">Status</td><td style="padding:8px 12px;font-weight:700;color:#0F172A;border-top:1px solid #E2E8F0;">{$status}</td></tr>
        </table>
        <p style="margin:0 0 18px;">
          <a href="{$portalUrl}" style="display:inline-block;padding:11px 18px;background:#2563EB;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:6px;">{$safeCta}</a>
        </p>
        <p style="margin:0;font-size:12px;color:#475569;">For privacy and security, ticket descriptions, responses, and internal notes are omitted from emails. Sign in to view full details: <a href="{$portalUrl}" style="color:#1D4ED8;">{$portalUrl}</a></p>
      </td>
    </tr>
    <tr>
      <td style="padding:14px 24px;background:#F8FAFC;border-top:1px solid #E2E8F0;font-size:12px;color:#64748B;">
        Automated notification for <strong>{$ticketRef}</strong> from {$appName} &copy; {$year}.
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        $altBody = "Hello {$recipientName},\n\n"
            . strip_tags($introHtml) . "\n\n"
            . "Ticket Reference: " . ($ticket['ticket_ref'] ?? '') . "\n"
            . "Category: " . ($ticket['category'] ?? '') . "\n"
            . "Priority: " . ($ticket['priority'] ?? '') . "\n"
            . "Status: " . ($ticket['status'] ?? '') . "\n\n"
            . "Open Ticket: {$rawPortalUrl}\n\n"
            . "For security, ticket descriptions and conversation messages are omitted from emails.\n";

        try {
            $sent = $this->mailer->sendMail($email, $recipientName, $emailSubject, $html, $altBody);
            if (!$sent) {
                $err = $this->mailer->getLastError() ?: 'MailService returned false';
                $this->dispatchErrors[] = $err;
                error_log('[HrSupportNotification] Email dispatch did not complete: ' . $err);
            }
        } catch (Throwable $e) {
            $msg = 'Email dispatch threw ' . get_class($e) . ': ' . $e->getMessage();
            $this->dispatchErrors[] = $msg;
            error_log('[HrSupportNotification] ' . $msg);
        }
    }
}
