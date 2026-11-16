<?php

/**
 * Public Authentication Support Service
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Manages pre-login public support intake (AUTH-YYYY-NNNNN), safe attachment
 * verification, secret tokenized ticket tracking, and unauthenticated follow-up
 * replies cleanly separated from confidential workplace complaints.
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\PublicSupportTrackingToken;
use App\Models\SupportTicket;
use App\Models\User;
use Core\AuditService;
use Core\Database;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class PublicAuthSupportService
{
    public const INVALID_TRACKING_MESSAGE = 'The tracking token is invalid or expired. Please open the full tracking link sent to your confirmation email.';

    private PDO $pdo;
    private SupportTicket $tickets;
    private PublicSupportTrackingToken $trackingTokens;
    private User $users;
    private SupportAttachmentService $attachments;
    private SupportNotificationService $notifications;
    private MailService $mailer;

    public function __construct(
        ?PDO $pdo = null,
        ?SupportTicket $tickets = null,
        ?PublicSupportTrackingToken $trackingTokens = null,
        ?User $users = null,
        ?SupportAttachmentService $attachments = null,
        ?SupportNotificationService $notifications = null,
        ?MailService $mailer = null
    ) {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
        $this->tickets = $tickets ?? new SupportTicket($this->pdo);
        $this->trackingTokens = $trackingTokens ?? new PublicSupportTrackingToken($this->pdo);
        $this->users = $users ?? new User($this->pdo);
        $this->attachments = $attachments ?? new SupportAttachmentService();
        $this->notifications = $notifications ?? new SupportNotificationService(null, $this->users);
        $this->mailer = $mailer ?? new MailService();
    }

    /**
     * Submit an unauthenticated login/authentication issue ticket (AUTH-YYYY-NNNNN).
     *
     * @param array<string, mixed>      $input
     * @param array<string, mixed>|null $filesInput
     * @return array{ticket: array<string, mixed>, ticket_ref: string, email_sent: bool}
     */
    public function submitPublicRequest(
        array $input,
        ?array $filesInput = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        // Basic anti-bot honeypot guard
        $honeypot = trim((string)($input['company_website'] ?? $input['website'] ?? ''));
        if ($honeypot !== '') {
            throw new InvalidArgumentException('Your request could not be processed. Please clear browser autofill fields and try again.');
        }

        $requesterName = trim((string)($input['requester_name'] ?? ''));
        $requesterEmail = strtolower(trim((string)($input['requester_email'] ?? '')));
        $category = trim((string)($input['category'] ?? ''));
        $subject = trim((string)($input['subject'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $issueDatetime = trim((string)($input['issue_datetime'] ?? ''));
        $clientEnv = trim((string)($input['client_env'] ?? ''));
        $priorityInput = trim((string)($input['priority'] ?? ''));

        if (mb_strlen($requesterName) < 2 || mb_strlen($requesterName) > 120) {
            throw new InvalidArgumentException('Please enter your full name (between 2 and 120 characters).');
        }

        if ($requesterEmail === '' || !filter_var($requesterEmail, FILTER_VALIDATE_EMAIL) || strlen($requesterEmail) > 150) {
            throw new InvalidArgumentException('Please enter a valid contact email address.');
        }

        $categories = (array)config('support.public_auth_categories', []);
        if ($category === '' || !isset($categories[$category])) {
            throw new InvalidArgumentException('Please select a valid login assistance category.');
        }

        if (mb_strlen($subject) < 5 || mb_strlen($subject) > 200) {
            throw new InvalidArgumentException('Subject must be between 5 and 200 characters.');
        }

        if (mb_strlen($description) < 15 || mb_strlen($description) > 5000) {
            throw new InvalidArgumentException('Please describe the issue in detail (between 15 and 5,000 characters).');
        }

        $allowedPriorities = (array)config('support.priorities', ['Low', 'Medium', 'High', 'Urgent']);
        $defaultPriority = (string)($categories[$category]['default_priority'] ?? 'Medium');
        $priority = in_array($priorityInput, $allowedPriorities, true) ? $priorityInput : $defaultPriority;

        $cleanIssueDatetime = $issueDatetime !== '' ? mb_substr($issueDatetime, 0, 100) : null;
        if ($clientEnv === '' && $userAgent !== null && trim($userAgent) !== '') {
            $parsed = TrustedDeviceService::parseUserAgent($userAgent);
            $clientEnv = $parsed['device_name'];
        }
        $cleanClientEnv = $clientEnv !== '' ? mb_substr($clientEnv, 0, 255) : null;

        $clientIp = $ipAddress !== null && trim($ipAddress) !== '' ? trim($ipAddress) : client_ip();
        $this->enforceSubmissionRateLimit($requesterEmail, $clientIp);

        $normalizedFiles = SupportAttachmentService::normalizeFilesArray($filesInput);
        $validatedFiles = $this->attachments->validateBatch($normalizedFiles);

        // Internal account match ONLY for HR triage context — NEVER disclosed on public views/emails.
        $matchedUser = $this->users->findByEmail($requesterEmail);
        $matchedUserId = $matchedUser ? (int)$matchedUser['id'] : null;

        $selector = bin2hex(random_bytes(16));
        $verifier = bin2hex(random_bytes(32));
        $tokenHash = $this->hashVerifier($verifier);
        $ttlDays = (int)config('support.public_tracking_token_ttl_days', 30);

        $metadataJson = json_encode([
            'channel'           => 'public_auth',
            'issue_datetime'    => $cleanIssueDatetime,
            'client_env'        => $cleanClientEnv,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $storedPaths = [];
        $this->pdo->beginTransaction();

        try {
            $year = (int)date('Y');
            $ticketRef = $this->tickets->generateNextTicketRef($year, $this->pdo, true);

            $ticketId = $this->tickets->create([
                'ticket_ref'             => $ticketRef,
                'employee_id'            => null,
                'submitted_by_user_id'   => null,
                'support_channel'        => 'public_auth',
                'is_public_ticket'       => 1,
                'requester_name'         => $requesterName,
                'requester_email'        => $requesterEmail,
                'matched_user_id'        => $matchedUserId,
                'public_issue_datetime'  => $cleanIssueDatetime,
                'public_client_env'      => $cleanClientEnv,
                'request_type'           => 'support',
                'category'               => $category,
                'complaint_category'     => null,
                'is_confidential'        => 0,
                'subject_employee_id'    => null,
                'subject_reference'      => null,
                'subject'                => $subject,
                'description'            => $description,
                'incident_date'          => null,
                'category_metadata_json' => $metadataJson !== false ? $metadataJson : null,
                'priority'               => $priority,
                'status'                 => 'Submitted',
                'assigned_to_user_id'    => null,
                'escalated_flag'         => 0,
                'escalation_reason'      => null,
            ]);

            $initialMessageId = $this->tickets->addMessage([
                'ticket_id'        => $ticketId,
                'sender_user_id'   => null,
                'sender_role'      => 'public_requester',
                'message_type'     => 'initial_submission',
                'is_internal_note' => 0,
                'body'             => $description,
            ]);

            if ($validatedFiles !== []) {
                $storedBatch = $this->attachments->storeBatch($ticketId, $validatedFiles);
                $storedPaths = array_column($storedBatch, 'storage_relative_path');

                foreach ($storedBatch as $fileMeta) {
                    $this->tickets->addAttachment([
                        'ticket_id'             => $ticketId,
                        'message_id'            => $initialMessageId,
                        'uploaded_by_user_id'   => null,
                        'original_filename'     => $fileMeta['original_filename'],
                        'stored_filename'       => $fileMeta['stored_filename'],
                        'storage_relative_path' => $fileMeta['storage_relative_path'],
                        'mime_type'             => $fileMeta['mime_type'],
                        'file_size_bytes'       => $fileMeta['file_size_bytes'],
                        'sha256_checksum'       => $fileMeta['sha256_checksum'],
                        'is_confidential'       => 0,
                        'is_internal_only'      => 0,
                    ]);
                }
            }

            $this->tickets->addStatusHistory([
                'ticket_id'          => $ticketId,
                'from_status'        => null,
                'to_status'          => 'Submitted',
                'changed_by_user_id' => null,
                'remarks'            => 'Submitted via Public Login & Authentication Assistance portal.',
            ]);

            $this->trackingTokens->createToken($ticketId, $selector, $tokenHash, $ttlDays);

            $createdTicket = $this->tickets->findById($ticketId) ?? [
                'id'               => $ticketId,
                'ticket_ref'       => $ticketRef,
                'category'         => $category,
                'is_confidential'  => 0,
                'is_public_ticket' => 1,
                'support_channel'  => 'public_auth',
                'requester_name'   => $requesterName,
                'requester_email'  => $requesterEmail,
            ];

            $this->notifications->notifyTicketSubmitted($createdTicket);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->attachments->rollbackStoredFiles($storedPaths);
            throw $e;
        }

        $rawTrackingToken = $selector . '.' . $verifier;
        $trackUrl = absolute_url('/auth/support/track', ['token' => $rawTrackingToken]);
        $emailSent = $this->sendConfirmationEmail(
            $requesterEmail,
            $requesterName,
            $createdTicket['ticket_ref'],
            $category,
            $subject,
            $trackUrl,
            $ttlDays
        );

        $this->recordAuditEvent(
            'PUBLIC_SUPPORT_SUBMITTED',
            "Public support ticket {$createdTicket['ticket_ref']} submitted ({$category}) by " . mask_email($requesterEmail),
            'PUBLIC_SUPPORT',
            login_identifier_key($requesterEmail),
            'SUCCESS',
            $clientIp,
            $userAgent
        );

        return [
            'ticket'     => $createdTicket,
            'ticket_ref' => (string)$createdTicket['ticket_ref'],
            'email_sent' => $emailSent,
        ];
    }

    /**
     * Resolve and redact a public support ticket using a secret tracking token.
     * Excludes internal HR notes, internal-only attachments, assignee identity,
     * and any internal employee/account linkage.
     *
     * @return array{valid: bool, message: string, ticket: ?array<string, mixed>, messages: array<int, array<string, mixed>>, attachments: array<int, array<string, mixed>>}
     */
    public function resolvePublicTrackingView(string $rawToken): array
    {
        $tokenRow = $this->verifyTrackingToken($rawToken);
        if ($tokenRow === null) {
            return [
                'valid'       => false,
                'message'     => self::INVALID_TRACKING_MESSAGE,
                'ticket'      => null,
                'messages'    => [],
                'attachments' => [],
            ];
        }

        $ticketId = (int)$tokenRow['ticket_id'];
        $ticket = $this->tickets->findById($ticketId);
        if ($ticket === null || empty($ticket['is_public_ticket'])) {
            return [
                'valid'       => false,
                'message'     => self::INVALID_TRACKING_MESSAGE,
                'ticket'      => null,
                'messages'    => [],
                'attachments' => [],
            ];
        }

        try {
            $this->trackingTokens->touchLastViewed((int)$tokenRow['id']);
        } catch (Throwable) {
            // Non-blocking telemetry
        }

        $safeTicket = [
            'ticket_ref'            => (string)$ticket['ticket_ref'],
            'category'              => (string)$ticket['category'],
            'subject'               => (string)$ticket['subject'],
            'description'           => (string)$ticket['description'],
            'priority'              => (string)$ticket['priority'],
            'status'                => (string)$ticket['status'],
            'display_status'        => (string)$ticket['status'] === 'Waiting for Employee'
                ? 'Waiting for Requester'
                : (string)$ticket['status'],
            'resolution_remarks'    => $ticket['resolution_remarks'] ?? null,
            'public_issue_datetime' => $ticket['public_issue_datetime'] ?? null,
            'created_at'            => $ticket['created_at'] ?? null,
            'last_activity_at'      => $ticket['last_activity_at'] ?? null,
            'resolved_at'           => $ticket['resolved_at'] ?? null,
            'closed_at'             => $ticket['closed_at'] ?? null,
        ];

        $rawMessages = $this->tickets->getMessages($ticketId, false);
        $safeMessages = [];
        foreach ($rawMessages as $msg) {
            if (!empty($msg['is_internal_note'])) {
                continue;
            }
            $role = strtolower(trim((string)($msg['sender_role'] ?? '')));
            $senderLabel = match ($role) {
                'public_requester', 'employee' => 'You',
                'hr', 'admin'                  => 'HR Support Team',
                default                        => 'System Update',
            };
            $safeMessages[] = [
                'id'           => (int)$msg['id'],
                'sender_role'  => $role,
                'sender_label' => $senderLabel,
                'message_type' => (string)$msg['message_type'],
                'body'         => (string)$msg['body'],
                'created_at'   => (string)$msg['created_at'],
            ];
        }

        $rawAttachments = $this->tickets->getAttachments($ticketId, false);
        $safeAttachments = [];
        foreach ($rawAttachments as $att) {
            if (!empty($att['is_internal_only'])) {
                continue;
            }
            $safeAttachments[] = [
                'id'                => (int)$att['id'],
                'original_filename' => (string)$att['original_filename'],
                'mime_type'         => (string)$att['mime_type'],
                'file_size_bytes'   => (int)$att['file_size_bytes'],
                'created_at'        => (string)$att['created_at'],
            ];
        }

        return [
            'valid'       => true,
            'message'     => '',
            'ticket'      => $safeTicket,
            'messages'    => $safeMessages,
            'attachments' => $safeAttachments,
        ];
    }

    /**
     * Allow an unauthenticated requester holding a valid tracking token to post a follow-up reply.
     *
     * @return array<string, mixed>
     */
    public function submitPublicFollowUpReply(
        string $rawToken,
        string $body,
        ?array $filesInput = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $tokenRow = $this->verifyTrackingToken($rawToken);
        if ($tokenRow === null) {
            throw new RuntimeException(self::INVALID_TRACKING_MESSAGE, 403);
        }

        $ticketId = (int)$tokenRow['ticket_id'];
        $ticket = $this->tickets->findById($ticketId);
        if ($ticket === null || empty($ticket['is_public_ticket'])) {
            throw new RuntimeException('Support ticket not found.', 404);
        }

        if ((string)$ticket['status'] === 'Closed') {
            throw new InvalidArgumentException('Closed public support tickets cannot accept replies. Please submit a new login assistance request.');
        }

        $cleanBody = trim($body);
        if (mb_strlen($cleanBody) < 2 || mb_strlen($cleanBody) > 5000) {
            throw new InvalidArgumentException('Reply message must be between 2 and 5,000 characters.');
        }

        $normalizedFiles = SupportAttachmentService::normalizeFilesArray($filesInput);
        $validatedFiles = $this->attachments->validateBatch($normalizedFiles);

        $currentStatus = (string)$ticket['status'];
        $newStatus = $currentStatus;
        $reopened = false;
        if ($currentStatus === 'Waiting for Employee') {
            $newStatus = 'Under Review';
        } elseif ($currentStatus === 'Resolved') {
            $newStatus = 'Under Review';
            $reopened = true;
        }

        $storedPaths = [];
        $this->pdo->beginTransaction();

        try {
            $messageId = $this->tickets->addMessage([
                'ticket_id'        => $ticketId,
                'sender_user_id'   => null,
                'sender_role'      => 'public_requester',
                'message_type'     => 'employee_reply',
                'is_internal_note' => 0,
                'body'             => $cleanBody,
            ]);

            if ($validatedFiles !== []) {
                $storedBatch = $this->attachments->storeBatch($ticketId, $validatedFiles);
                $storedPaths = array_column($storedBatch, 'storage_relative_path');

                foreach ($storedBatch as $fileMeta) {
                    $this->tickets->addAttachment([
                        'ticket_id'             => $ticketId,
                        'message_id'            => $messageId,
                        'uploaded_by_user_id'   => null,
                        'original_filename'     => $fileMeta['original_filename'],
                        'stored_filename'       => $fileMeta['stored_filename'],
                        'storage_relative_path' => $fileMeta['storage_relative_path'],
                        'mime_type'             => $fileMeta['mime_type'],
                        'file_size_bytes'       => $fileMeta['file_size_bytes'],
                        'sha256_checksum'       => $fileMeta['sha256_checksum'],
                        'is_confidential'       => 0,
                        'is_internal_only'      => 0,
                    ]);
                }
            }

            $updates = ['last_activity_at' => date('Y-m-d H:i:s')];
            if ($newStatus !== $currentStatus) {
                $updates['status'] = $newStatus;
                if ($reopened) {
                    $updates['resolved_at'] = null;
                    $updates['closed_at'] = null;
                }
                $this->tickets->addStatusHistory([
                    'ticket_id'          => $ticketId,
                    'from_status'        => $currentStatus,
                    'to_status'          => $newStatus,
                    'changed_by_user_id' => null,
                    'remarks'            => $reopened
                        ? 'Requester reopened public ticket via tracking link.'
                        : 'Requester submitted follow-up reply via public tracking link.',
                ]);
            }

            $this->tickets->updateTicketFields($ticketId, $updates);
            $updated = $this->tickets->findById($ticketId) ?? $ticket;
            $this->notifications->notifyEmployeeReplied($updated, $reopened);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->attachments->rollbackStoredFiles($storedPaths);
            throw $e;
        }

        $this->recordAuditEvent(
            'PUBLIC_SUPPORT_REPLY',
            "Public requester reply posted on {$updated['ticket_ref']}.",
            'PUBLIC_SUPPORT',
            (string)$updated['ticket_ref'],
            'SUCCESS',
            $ipAddress ?? client_ip(),
            $userAgent
        );

        return $updated;
    }

    /**
     * Validate tracking token and resolve an attachment path if non-internal.
     *
     * @return array{attachment: array<string, mixed>, file_path: string}
     */
    public function resolvePublicAttachmentDownload(int $attachmentId, string $rawToken): array
    {
        $tokenRow = $this->verifyTrackingToken($rawToken);
        if ($tokenRow === null) {
            throw new RuntimeException(self::INVALID_TRACKING_MESSAGE, 403);
        }

        $attachment = $this->tickets->findAttachmentWithTicket($attachmentId);
        if (!$attachment) {
            throw new RuntimeException('Attachment not found.', 404);
        }

        if (
            (int)$attachment['ticket_id'] !== (int)$tokenRow['ticket_id']
            || empty($attachment['ticket_is_public_ticket'])
            || !empty($attachment['is_internal_only'])
        ) {
            throw new RuntimeException('You are not authorized to download this attachment.', 403);
        }

        $path = $this->attachments->resolveAuthorizedFilePath((string)($attachment['storage_relative_path'] ?? ''));
        if ($path === null) {
            throw new RuntimeException('Requested file is unavailable.', 404);
        }

        return [
            'attachment' => $attachment,
            'file_path'  => $path,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function verifyTrackingToken(string $rawToken): ?array
    {
        $trimmed = trim($rawToken);
        if ($trimmed === '' || !str_contains($trimmed, '.')) {
            return null;
        }

        $parts = explode('.', $trimmed, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$selector, $verifier] = $parts;
        if (strlen($selector) !== 32 || strlen($verifier) !== 64 || !ctype_xdigit($selector) || !ctype_xdigit($verifier)) {
            return null;
        }

        $row = $this->trackingTokens->findBySelector(strtolower($selector));
        if ($row === null || !empty($row['revoked_at']) || (int)($row['is_expired'] ?? 1) === 1 || empty($row['is_public_ticket'])) {
            return null;
        }

        $expectedHash = (string)($row['token_hash'] ?? '');
        $actualHash = $this->hashVerifier(strtolower($verifier));
        if ($expectedHash === '' || !hash_equals($expectedHash, $actualHash)) {
            return null;
        }

        return $row;
    }

    private function enforceSubmissionRateLimit(string $normalizedEmail, string $ipAddress): void
    {
        $window = max(60, (int)config('support.public_submission_rate_limit_window', 900));
        $maxIp = max(1, (int)config('support.public_submission_rate_limit_max_ip', 5));
        $maxEmail = max(1, (int)config('support.public_submission_rate_limit_max_email', 3));

        $emailKey = login_identifier_key($normalizedEmail);
        $emailStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM activity_logs
             WHERE action = 'PUBLIC_SUPPORT_SUBMITTED'
               AND entity_type = 'PUBLIC_SUPPORT'
               AND entity_id = :ident
               AND created_at > (NOW() - INTERVAL {$window} SECOND)"
        );
        $emailStmt->execute(['ident' => $emailKey]);
        if ((int)$emailStmt->fetchColumn() >= $maxEmail) {
            throw new RuntimeException(
                'You have reached the recent submission limit for public support requests. Please wait a few minutes or check your confirmation email tracking link.',
                429
            );
        }

        if ($ipAddress !== '') {
            $ipStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM activity_logs
                 WHERE action = 'PUBLIC_SUPPORT_SUBMITTED'
                   AND entity_type = 'PUBLIC_SUPPORT'
                   AND ip_address = :ip
                   AND created_at > (NOW() - INTERVAL {$window} SECOND)"
            );
            $ipStmt->execute(['ip' => $ipAddress]);
            if ((int)$ipStmt->fetchColumn() >= $maxIp) {
                throw new RuntimeException(
                    'Too many public support submissions from this network right now. Please try again in a few minutes.',
                    429
                );
            }
        }
    }

    private function hashVerifier(string $verifier): string
    {
        return hash_hmac('sha256', $verifier, $this->resolvePepper());
    }

    private function resolvePepper(): string
    {
        foreach (['PUBLIC_SUPPORT_PEPPER', 'PASSWORD_RESET_PEPPER', 'TRUSTED_DEVICE_PEPPER', 'OTP_PEPPER_SECRET', 'APP_KEY'] as $key) {
            $val = getenv($key);
            if ($val === false || trim($val) === '') {
                $val = $_ENV[$key] ?? '';
            }
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        return 'nexora-hr-public-support-local-fallback';
    }

    private function sendConfirmationEmail(
        string $email,
        string $name,
        string $ticketRef,
        string $category,
        string $subjectTitle,
        string $trackUrl,
        int $ttlDays
    ): bool {
        $appName = e((string)config('config.app_name', 'NEXORA HR'));
        $safeName = e($name);
        $safeRef = e($ticketRef);
        $safeCat = e($category);
        $safeSubject = e($subjectTitle);
        $safeUrl = e($trackUrl);
        $year = date('Y');
        $emailSubject = "Login Assistance Request Logged ({$ticketRef}) - " . config('config.app_name', 'NEXORA HR');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Support Request Logged</title></head>
<body style="margin:0;padding:24px;background:#F1F5F9;font-family:Arial,Helvetica,sans-serif;color:#0F172A;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;margin:0 auto;background:#FFFFFF;border:1px solid #CBD5E1;border-radius:10px;overflow:hidden;">
    <tr>
      <td style="background:#0F2C59;padding:20px 24px;color:#FFFFFF;">
        <div style="font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#93C5FD;font-weight:700;">{$appName} Login Support</div>
        <h1 style="margin:6px 0 0;font-size:18px;font-weight:700;color:#FFFFFF;">Request Logged: {$safeRef}</h1>
      </td>
    </tr>
    <tr>
      <td style="padding:24px;font-size:14px;line-height:1.6;">
        <p style="margin:0 0 12px;">Hello {$safeName},</p>
        <p style="margin:0 0 14px;">Our HR &amp; IT Support team received your pre-login assistance request. Keep your reference and private tracking link below for checking status updates or replying safely.</p>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 16px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;">
          <tr><td style="padding:10px 14px;font-size:13px;"><strong>Ticket Reference:</strong> {$safeRef}<br><strong>Category:</strong> {$safeCat}<br><strong>Subject:</strong> {$safeSubject}</td></tr>
        </table>
        <p style="margin:18px 0;">
          <a href="{$safeUrl}" style="display:inline-block;padding:12px 20px;background:#1D4ED8;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:6px;">Track Support Request</a>
        </p>
        <p style="margin:0 0 12px;font-size:12px;color:#475569;">Direct tracking link (valid {$ttlDays} days):<br><span style="word-break:break-all;color:#1D4ED8;">{$safeUrl}</span></p>
        <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:6px;padding:10px 12px;font-size:12px;color:#92400E;">
          Security reminder: NEXORA HR support staff will never ask you for your password, OTP verification code, or password reset links.
        </div>
      </td>
    </tr>
    <tr>
      <td style="padding:14px 24px;background:#F8FAFC;border-top:1px solid #E2E8F0;font-size:12px;color:#64748B;">
        Automated confirmation from {$appName} &copy; {$year}. Keep your private tracking link safe.
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        $altBody = "Hello {$name},\n\n"
            . "Your pre-login support request has been logged.\n"
            . "Ticket Reference: {$ticketRef}\n"
            . "Category: {$category}\n"
            . "Subject: {$subjectTitle}\n\n"
            . "Use your private tracking link below (valid {$ttlDays} days):\n{$trackUrl}\n\n"
            . "Security reminder: HR will never ask for your password or OTP codes.\n";

        try {
            return $this->mailer->sendMail($email, $name, $emailSubject, $html, $altBody);
        } catch (Throwable $e) {
            error_log('[PublicAuthSupport] Confirmation email failed: ' . get_class($e));
            return false;
        }
    }

    private function recordAuditEvent(
        string $action,
        string $description,
        string $entityType,
        string $entityId,
        string $status,
        string $ipAddress,
        ?string $userAgent
    ): void {
        try {
            AuditService::getInstance()->record([
                'user_id'     => null,
                'action'      => $action,
                'category'    => AuditService::CATEGORY_SUPPORT,
                'description' => $description,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'status'      => $status,
                'ip_address'  => $ipAddress,
                'user_agent'  => $userAgent !== null ? substr($userAgent, 0, 255) : null,
            ]);
        } catch (Throwable) {
            try {
                $this->users->logActivity(null, $action, $description, $entityType, $entityId, null, null, $status);
            } catch (Throwable) {
                // Fail-safe logging
            }
        }
    }
}
