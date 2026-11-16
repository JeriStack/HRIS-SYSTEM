<?php

/**
 * HrSupportWorkflowService
 * Concurrency-safe orchestration for HR-to-Admin Help & Support tickets:
 * creation, HR replies, resolution confirmation, reopened-window policy,
 * Admin triage/assignment/priority/status transitions, Admin-only internal notes,
 * private attachment handling, privacy-safe auditing, and non-blocking alerts.
 */

namespace App\Services;

use App\Models\HrSupportTicket;
use App\Models\User;
use Core\AuditService;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class HrSupportWorkflowService
{
    private HrSupportTicket $tickets;
    private HrSupportAccessService $access;
    private SupportAttachmentService $attachments;
    private HrSupportNotificationService $notifications;
    private User $users;
    private AuditService $audit;
    private PDO $pdo;

    public function __construct(
        ?PDO $pdo = null,
        ?HrSupportTicket $tickets = null,
        ?HrSupportAccessService $access = null,
        ?SupportAttachmentService $attachments = null,
        ?HrSupportNotificationService $notifications = null,
        ?User $users = null,
        ?AuditService $audit = null
    ) {
        if ($tickets !== null && $pdo === null) {
            $pdo = $tickets->getDb();
        }
        $this->tickets = $tickets ?? new HrSupportTicket($pdo);
        $this->pdo = $this->tickets->getDb();
        $this->access = $access ?? new HrSupportAccessService();
        $this->attachments = $attachments ?? new SupportAttachmentService(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'hr_support_attachments',
            null,
            (int)config('hr_support.attachment_max_bytes', 5 * 1024 * 1024),
            (int)config('hr_support.max_attachments_per_submission', 3)
        );
        $this->users = $users ?? new User($this->pdo);
        $this->notifications = $notifications ?? new HrSupportNotificationService(
            new \App\Models\Notification($this->pdo),
            $this->users,
            $this->tickets
        );
        $this->audit = $audit ?? AuditService::getInstance($this->pdo);
    }

    /**
     * Submit a new HR-to-Admin support ticket (strictly authorized for active HR users).
     *
     * @param array<string, mixed>      $actorUser
     * @param array<string, mixed>      $input
     * @param array<string, mixed>|null $filesInput
     * @return array<string, mixed>
     */
    public function createTicket(array $actorUser, array $input, ?array $filesInput = null): array
    {
        $userId = (int)($actorUser['id'] ?? 0);
        if ($userId <= 0 || ($actorUser['status'] ?? 'Active') !== 'Active') {
            throw new RuntimeException('Your user session is not active.', 403);
        }

        if (
            strtolower((string)($actorUser['role'] ?? '')) !== 'hr'
            || !$this->access->hasCapability($actorUser, 'hr_support.submit', $this->pdo)
        ) {
            throw new RuntimeException('Only authorized HR personnel may submit HR support tickets to Administrators.', 403);
        }

        $windowSec = (int)config('hr_support.submission_rate_limit_window', 900);
        $maxSubmissions = (int)config('hr_support.submission_rate_limit_max', 10);
        $rate = $this->users->countRecentActions($userId, 'HR_SUPPORT_TICKET_CREATED', $windowSec);
        if (($rate['count'] ?? 0) >= $maxSubmissions) {
            $this->recordAudit($actorUser, 'HR_SUPPORT_RATE_LIMITED', 'Rate limit reached for HR support ticket submission.', null, null, null, null, AuditService::STATUS_WARNING);
            throw new RuntimeException('You have reached the temporary submission limit. Please try again in a few minutes.', 429);
        }

        $categories = (array)config('hr_support.categories', []);
        $category = trim((string)($input['category'] ?? ''));
        if (!isset($categories[$category])) {
            throw new InvalidArgumentException('Please select a valid support issue category.', 422);
        }

        $priorities = (array)config('hr_support.priorities', ['Low', 'Medium', 'High', 'Critical']);
        $defaultPriority = (string)($categories[$category]['default_priority'] ?? 'Medium');
        $rawPriority = trim((string)($input['priority'] ?? ''));
        if ($rawPriority === '') {
            $priority = $defaultPriority;
        } elseif (!in_array($rawPriority, $priorities, true)) {
            throw new InvalidArgumentException('Please select a valid priority level (Low, Medium, High, or Critical).', 422);
        } else {
            $priority = $rawPriority;
        }

        $subject = trim((string)($input['subject'] ?? ''));
        if (mb_strlen($subject) < 5 || mb_strlen($subject) > 200) {
            throw new InvalidArgumentException('Subject must be between 5 and 200 characters.', 422);
        }

        $description = trim((string)($input['description'] ?? ''));
        if (mb_strlen($description) < 15 || mb_strlen($description) > 5000) {
            throw new InvalidArgumentException('Detailed description must be between 15 and 5,000 characters.', 422);
        }

        $normalizedFiles = SupportAttachmentService::normalizeFilesArray($filesInput);
        $validatedFiles = $this->attachments->validateBatch($normalizedFiles);

        $storedPaths = [];
        $startedTx = !$this->pdo->inTransaction();
        if ($startedTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $ticketRef = $this->tickets->generateTicketRef((int)date('Y'), $this->pdo);

            $ticketId = $this->tickets->createTicket([
                'ticket_ref'           => $ticketRef,
                'submitted_by_user_id' => $userId,
                'subject'              => $subject,
                'description'          => $description,
                'category'             => $category,
                'priority'             => $priority,
                'status'               => 'Submitted',
                'assigned_admin_id'    => null,
            ]);

            $messageId = $this->tickets->addMessage([
                'ticket_id'        => $ticketId,
                'sender_user_id'   => $userId,
                'sender_role'      => 'hr',
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
                        'message_id'            => $messageId,
                        'uploaded_by_user_id'   => $userId,
                        'original_filename'     => $fileMeta['original_filename'],
                        'stored_filename'       => $fileMeta['stored_filename'],
                        'storage_relative_path' => $fileMeta['storage_relative_path'],
                        'mime_type'             => $fileMeta['mime_type'],
                        'file_size_bytes'       => $fileMeta['file_size_bytes'],
                        'sha256_checksum'       => $fileMeta['sha256_checksum'],
                        'is_internal_only'      => 0,
                    ]);
                }
            }

            $this->tickets->addHistory(
                $ticketId,
                'created',
                null,
                'Submitted',
                null,
                $priority,
                $userId,
                'hr',
                'HR support ticket submitted to Administrator support queue.'
            );

            $created = $this->tickets->findById($ticketId) ?? [
                'id'                   => $ticketId,
                'ticket_ref'           => $ticketRef,
                'submitted_by_user_id' => $userId,
                'category'             => $category,
                'priority'             => $priority,
                'status'               => 'Submitted',
            ];

            if ($startedTx) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->attachments->rollbackStoredFiles($storedPaths);
            throw $e;
        }

        $this->recordAudit(
            $actorUser,
            'HR_SUPPORT_TICKET_CREATED',
            "Created HR support ticket {$created['ticket_ref']} ({$category}, {$priority}).",
            'hr_support_ticket',
            (string)$created['id'],
            null,
            [
                'ticket_ref'       => $created['ticket_ref'],
                'category'         => $category,
                'priority'         => $priority,
                'status'           => 'Submitted',
                'attachment_count' => count($storedPaths),
            ]
        );

        // Non-blocking notification dispatch AFTER commit so mail/alert failures never roll back persisted tickets
        $this->safeNotify(fn () => $this->notifications->notifyTicketSubmitted($created));

        return $created;
    }

    /**
     * Fetch HR-safe ticket detail bundle (explicitly strips all Admin-only internal notes & files).
     *
     * @param array<string, mixed> $actorUser
     * @return array<string, mixed>
     */
    public function getTicketDetailsForHr(array $actorUser, string $ticketRef): array
    {
        $ticket = $this->tickets->findByRef($ticketRef);
        if ($ticket === null) {
            throw new RuntimeException('HR support ticket not found.', 404);
        }

        if (!$this->access->canViewTicketAsHr($actorUser, $ticket, $this->pdo)) {
            $this->recordAudit(
                $actorUser,
                'HR_SUPPORT_ACCESS_DENIED',
                'Unauthorized attempt to view HR support ticket details.',
                'hr_support_ticket',
                (string)$ticket['id'],
                null,
                ['ticket_ref' => $ticket['ticket_ref']],
                AuditService::STATUS_FAILURE
            );
            throw new RuntimeException('You are not authorized to access this HR support ticket.', 403);
        }

        $ticketId = (int)$ticket['id'];
        // Always pass includeInternal = false for HR view
        $messages = $this->tickets->getMessages($ticketId, false);
        $attachments = $this->tickets->getAttachments($ticketId, false);
        $history = $this->tickets->getHistory($ticketId);

        $reopenDays = (int)config('hr_support.reopen_window_days', 14);

        return [
            'ticket'                 => $ticket,
            'messages'               => $messages,
            'attachments'            => $attachments,
            'attachments_by_message' => $this->groupAttachmentsByMessage($attachments),
            'history'                => $history,
            'can_reply'              => $this->access->canHrReply($ticket),
            'can_confirm_resolution' => $this->access->canHrConfirmResolution($ticket),
            'can_reopen'             => $this->access->canHrReopen($ticket, $reopenDays),
            'reopen_window_days'     => $reopenDays,
        ];
    }

    /**
     * Fetch Admin ticket management detail bundle, including internal notes when permitted.
     *
     * @param array<string, mixed> $actorUser
     * @return array<string, mixed>
     */
    public function getTicketDetailsForAdmin(array $actorUser, string $ticketRef): array
    {
        if (!$this->access->canViewTicketAsAdmin($actorUser, $this->pdo)) {
            throw new RuntimeException('Administrator support inbox authorization is required.', 403);
        }

        $ticket = $this->tickets->findByRef($ticketRef);
        if ($ticket === null) {
            throw new RuntimeException('HR support ticket not found.', 404);
        }

        $ticketId = (int)$ticket['id'];
        $includeInternal = $this->access->canViewInternalNotes($actorUser, $this->pdo);

        $messages = $this->tickets->getMessages($ticketId, $includeInternal);
        $attachments = $this->tickets->getAttachments($ticketId, $includeInternal);
        $history = $this->tickets->getHistory($ticketId);
        $assignments = $this->tickets->getAssignments($ticketId);
        $assignableAdmins = $this->tickets->getAssignableAdmins();

        $allowedTransitions = (array)(config('hr_support.admin_transitions', [])[(string)$ticket['status']] ?? []);

        return [
            'ticket'                 => $ticket,
            'messages'               => $messages,
            'attachments'            => $attachments,
            'attachments_by_message' => $this->groupAttachmentsByMessage($attachments),
            'history'                => $history,
            'assignments'            => $assignments,
            'assignable_admins'      => $assignableAdmins,
            'can_manage'             => $this->access->hasCapability($actorUser, 'hr_support.admin_manage', $this->pdo),
            'can_internal_notes'     => $includeInternal,
            'allowed_transitions'    => $allowedTransitions,
        ];
    }

    /**
     * Append an HR follow-up reply with optional attachments on own open ticket.
     *
     * @param array<string, mixed>      $actorUser
     * @param array<string, mixed>|null $filesInput
     * @return array<string, mixed>
     */
    public function addHrReply(
        array $actorUser,
        string $ticketRef,
        string $body,
        ?array $filesInput = null
    ): array {
        $userId = (int)($actorUser['id'] ?? 0);
        if (
            $userId <= 0
            || ($actorUser['status'] ?? 'Active') !== 'Active'
            || strtolower((string)($actorUser['role'] ?? '')) !== 'hr'
            || !$this->access->hasCapability($actorUser, 'hr_support.reply_own', $this->pdo)
        ) {
            throw new RuntimeException('You are not authorized to reply to HR support tickets.', 403);
        }

        $windowSec = (int)config('hr_support.reply_rate_limit_window', 900);
        $maxReplies = (int)config('hr_support.reply_rate_limit_max', 30);
        $rate = $this->users->countRecentActions($userId, 'HR_SUPPORT_HR_REPLIED', $windowSec);
        if (($rate['count'] ?? 0) >= $maxReplies) {
            throw new RuntimeException('Reply rate limit reached. Please wait a few minutes before sending another message.', 429);
        }

        $cleanBody = trim($body);
        if (mb_strlen($cleanBody) < 2 || mb_strlen($cleanBody) > 5000) {
            throw new InvalidArgumentException('Reply message must be between 2 and 5,000 characters.', 422);
        }

        $normalizedFiles = SupportAttachmentService::normalizeFilesArray($filesInput);
        $validatedFiles = $this->attachments->validateBatch($normalizedFiles);

        $storedPaths = [];
        $statusAutoAdvanced = null;
        $previousStatus = '';

        $startedTx = !$this->pdo->inTransaction();
        if ($startedTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $ticket = $this->tickets->findByRef($ticketRef, true);
            if ($ticket === null) {
                throw new RuntimeException('HR support ticket not found.', 404);
            }

            if (!$this->access->canViewTicketAsHr($actorUser, $ticket, $this->pdo)) {
                throw new RuntimeException('You can only reply to support tickets that you submitted.', 403);
            }

            if (!$this->access->canHrReply($ticket)) {
                throw new RuntimeException('Replies cannot be added while the ticket is ' . $ticket['status'] . '. Request reopening if the issue persists.', 409);
            }

            $ticketId = (int)$ticket['id'];
            $previousStatus = (string)$ticket['status'];

            $messageId = $this->tickets->addMessage([
                'ticket_id'        => $ticketId,
                'sender_user_id'   => $userId,
                'sender_role'      => 'hr',
                'message_type'     => 'hr_reply',
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
                        'uploaded_by_user_id'   => $userId,
                        'original_filename'     => $fileMeta['original_filename'],
                        'stored_filename'       => $fileMeta['stored_filename'],
                        'storage_relative_path' => $fileMeta['storage_relative_path'],
                        'mime_type'             => $fileMeta['mime_type'],
                        'file_size_bytes'       => $fileMeta['file_size_bytes'],
                        'sha256_checksum'       => $fileMeta['sha256_checksum'],
                        'is_internal_only'      => 0,
                    ]);
                }
            }

            if ($previousStatus === 'Waiting for HR') {
                $statusAutoAdvanced = !empty($ticket['assigned_admin_id']) ? 'In Progress' : 'Under Review';
                $this->tickets->updateTicketFields($ticketId, ['status' => $statusAutoAdvanced]);
                $this->tickets->addHistory(
                    $ticketId,
                    'status_changed',
                    $previousStatus,
                    $statusAutoAdvanced,
                    null,
                    null,
                    $userId,
                    'hr',
                    'HR provided requested follow-up information.'
                );
            } else {
                $this->tickets->updateTicketFields($ticketId, []);
            }

            $updated = $this->tickets->findById($ticketId) ?? $ticket;

            if ($startedTx) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->attachments->rollbackStoredFiles($storedPaths);
            throw $e;
        }

        $this->recordAudit(
            $actorUser,
            'HR_SUPPORT_HR_REPLIED',
            "HR replied on support ticket {$updated['ticket_ref']}.",
            'hr_support_ticket',
            (string)$updated['id'],
            $statusAutoAdvanced !== null ? ['status' => $previousStatus] : null,
            array_filter([
                'ticket_ref'       => $updated['ticket_ref'],
                'attachment_count' => count($storedPaths),
                'status'           => $statusAutoAdvanced,
            ], static fn ($v) => $v !== null)
        );

        $this->safeNotify(fn () => $this->notifications->notifyHrReplied($updated, false));

        return $updated;
    }

    /**
     * HR confirms resolution on a Resolved ticket, transitioning it cleanly to Closed.
     *
     * @param array<string, mixed> $actorUser
     * @return array<string, mixed>
     */
    public function confirmHrResolution(array $actorUser, string $ticketRef, string $note = ''): array
    {
        $userId = (int)($actorUser['id'] ?? 0);
        if (
            $userId <= 0
            || ($actorUser['status'] ?? 'Active') !== 'Active'
            || strtolower((string)($actorUser['role'] ?? '')) !== 'hr'
            || !$this->access->hasCapability($actorUser, 'hr_support.reply_own', $this->pdo)
        ) {
            throw new RuntimeException('You are not authorized to confirm resolution for HR support tickets.', 403);
        }

        $cleanNote = trim($note);
        if (mb_strlen($cleanNote) > 2000) {
            throw new InvalidArgumentException('Confirmation note must not exceed 2,000 characters.', 422);
        }

        $previousStatus = '';
        $startedTx = !$this->pdo->inTransaction();
        if ($startedTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $ticket = $this->tickets->findByRef($ticketRef, true);
            if ($ticket === null) {
                throw new RuntimeException('HR support ticket not found.', 404);
            }

            if (!$this->access->canViewTicketAsHr($actorUser, $ticket, $this->pdo)) {
                throw new RuntimeException('You can only confirm resolution on your own submitted tickets.', 403);
            }

            if (!$this->access->canHrConfirmResolution($ticket)) {
                throw new RuntimeException('Only tickets currently marked Resolved can be confirmed as Closed by HR.', 409);
            }

            $ticketId = (int)$ticket['id'];
            $previousStatus = (string)$ticket['status'];
            $now = date('Y-m-d H:i:s');

            $this->tickets->updateTicketFields($ticketId, [
                'status'    => 'Closed',
                'closed_at' => $now,
            ]);

            $confirmBody = $cleanNote !== ''
                ? $cleanNote
                : 'HR confirmed that the issue is resolved and closed this support ticket.';

            $this->tickets->addMessage([
                'ticket_id'        => $ticketId,
                'sender_user_id'   => $userId,
                'sender_role'      => 'hr',
                'message_type'     => 'resolution_confirmation',
                'is_internal_note' => 0,
                'body'             => $confirmBody,
            ]);

            $this->tickets->addHistory(
                $ticketId,
                'closed',
                $previousStatus,
                'Closed',
                null,
                null,
                $userId,
                'hr',
                'HR confirmed resolution and closed ticket.'
            );

            $updated = $this->tickets->findById($ticketId) ?? $ticket;

            if ($startedTx) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->recordAudit(
            $actorUser,
            'HR_SUPPORT_TICKET_CLOSED',
            "HR confirmed resolution and closed ticket {$updated['ticket_ref']}.",
            'hr_support_ticket',
            (string)$updated['id'],
            ['status' => $previousStatus],
            ['status' => 'Closed']
        );

        return $updated;
    }

    /**
     * HR requests reopening of a Resolved ticket within the configured policy window.
     * Closed tickets cannot be reopened by HR directly.
     *
     * @param array<string, mixed> $actorUser
     * @return array<string, mixed>
     */
    public function requestHrReopen(array $actorUser, string $ticketRef, string $reason): array
    {
        $userId = (int)($actorUser['id'] ?? 0);
        if (
            $userId <= 0
            || ($actorUser['status'] ?? 'Active') !== 'Active'
            || strtolower((string)($actorUser['role'] ?? '')) !== 'hr'
            || !$this->access->hasCapability($actorUser, 'hr_support.reply_own', $this->pdo)
        ) {
            throw new RuntimeException('You are not authorized to reopen HR support tickets.', 403);
        }

        $cleanReason = trim($reason);
        if (mb_strlen($cleanReason) < 10 || mb_strlen($cleanReason) > 2000) {
            throw new InvalidArgumentException('Please provide a reopen explanation between 10 and 2,000 characters.', 422);
        }

        $previousStatus = '';
        $newReopenCount = 1;

        $startedTx = !$this->pdo->inTransaction();
        if ($startedTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $ticket = $this->tickets->findByRef($ticketRef, true);
            if ($ticket === null) {
                throw new RuntimeException('HR support ticket not found.', 404);
            }

            if (!$this->access->canViewTicketAsHr($actorUser, $ticket, $this->pdo)) {
                throw new RuntimeException('You can only request reopening on your own submitted tickets.', 403);
            }

            $previousStatus = (string)$ticket['status'];
            if ($previousStatus === 'Closed') {
                throw new RuntimeException('Closed tickets cannot be reopened directly by HR. Please contact an Administrator or submit a new support ticket.', 409);
            }

            if (!$this->access->canHrReopen($ticket)) {
                throw new RuntimeException(
                    $previousStatus === 'Resolved'
                        ? 'The self-service reopening window for this resolved ticket has expired. Please contact an Administrator.'
                        : 'Only tickets marked Resolved may be reopened.',
                    409
                );
            }

            $ticketId = (int)$ticket['id'];
            $newReopenCount = (int)($ticket['reopen_count'] ?? 0) + 1;

            $this->tickets->updateTicketFields($ticketId, [
                'status'       => 'Under Review',
                'resolved_at'  => null,
                'closed_at'    => null,
                'reopen_count' => $newReopenCount,
            ]);

            $this->tickets->addMessage([
                'ticket_id'        => $ticketId,
                'sender_user_id'   => $userId,
                'sender_role'      => 'hr',
                'message_type'     => 'reopen_request',
                'is_internal_note' => 0,
                'body'             => $cleanReason,
            ]);

            $this->tickets->addHistory(
                $ticketId,
                'reopened',
                $previousStatus,
                'Under Review',
                null,
                null,
                $userId,
                'hr',
                'HR requested ticket reopening.'
            );

            $updated = $this->tickets->findById($ticketId) ?? $ticket;

            if ($startedTx) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->recordAudit(
            $actorUser,
            'HR_SUPPORT_TICKET_REOPENED',
            "HR requested reopening for ticket {$updated['ticket_ref']}.",
            'hr_support_ticket',
            (string)$updated['id'],
            ['status' => $previousStatus],
            ['status' => 'Under Review', 'reopen_count' => $newReopenCount]
        );

        $this->safeNotify(fn () => $this->notifications->notifyHrReplied($updated, true));

        return $updated;
    }

    /**
     * Admin assigns, reassigns, or unassigns a ticket cleanly against active Admin accounts only.
     *
     * @param array<string, mixed> $actorUser
     * @return array<string, mixed>
     */
    public function assignAdmin(
        array $actorUser,
        string $ticketRef,
        ?int $targetAdminId,
        string $notes = ''
    ): array {
        $this->assertAdminManage($actorUser);
        $actorUserId = (int)$actorUser['id'];
        $cleanNotes = mb_substr(trim($notes), 0, 255);

        $normalizedTarget = ($targetAdminId !== null && $targetAdminId > 0) ? $targetAdminId : null;
        if ($normalizedTarget !== null) {
            $targetUser = $this->users->findById($normalizedTarget);
            if (
                !$targetUser
                || strtolower((string)($targetUser['role'] ?? '')) !== 'admin'
                || ($targetUser['status'] ?? '') !== 'Active'
            ) {
                throw new InvalidArgumentException('Assigned handler must be an active Administrator account.', 422);
            }
        }

        $previousAdminId = null;
        $startedTx = !$this->pdo->inTransaction();
        if ($startedTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $ticket = $this->tickets->findByRef($ticketRef, true);
            if ($ticket === null) {
                throw new RuntimeException('HR support ticket not found.', 404);
            }

            $previousAdminId = isset($ticket['assigned_admin_id']) && $ticket['assigned_admin_id'] !== null
                ? (int)$ticket['assigned_admin_id']
                : null;

            if ($previousAdminId === $normalizedTarget) {
                if ($startedTx) {
                    $this->pdo->commit();
                }
                return $ticket;
            }

            $ticketId = (int)$ticket['id'];
            $action = match (true) {
                $normalizedTarget === null => 'unassigned',
                $previousAdminId === null  => 'assigned',
                default                    => 'reassigned',
            };

            $this->tickets->updateTicketFields($ticketId, [
                'assigned_admin_id' => $normalizedTarget,
            ]);

            $this->tickets->addAssignment(
                $ticketId,
                $normalizedTarget,
                $actorUserId,
                $action,
                $cleanNotes !== '' ? $cleanNotes : null
            );

            $remark = $normalizedTarget === null
                ? 'Ticket returned to unassigned Administrator queue.'
                : ($previousAdminId === null
                    ? 'Ticket assigned to Administrator #' . $normalizedTarget . '.'
                    : 'Ticket reassigned to Administrator #' . $normalizedTarget . '.');

            $this->tickets->addHistory(
                $ticketId,
                'assigned',
                null,
                null,
                null,
                null,
                $actorUserId,
                'admin',
                $remark
            );

            $updated = $this->tickets->findById($ticketId) ?? $ticket;

            if ($startedTx) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->recordAudit(
            $actorUser,
            'HR_SUPPORT_TICKET_ASSIGNED',
            "Updated assignment on HR support ticket {$updated['ticket_ref']}.",
            'hr_support_ticket',
            (string)$updated['id'],
            ['assigned_admin_id' => $previousAdminId],
            ['assigned_admin_id' => $normalizedTarget]
        );

        $this->safeNotify(fn () => $this->notifications->notifyTicketAssigned($updated, $previousAdminId, $normalizedTarget, $actorUserId));

        return $updated;
    }

    /**
     * Admin updates ticket priority, status (including resolve / close / reopen), and optional summary/public note.
     *
     * @param array<string, mixed> $actorUser
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function updateTicketTriage(array $actorUser, string $ticketRef, array $input): array
    {
        $this->assertAdminManage($actorUser);
        $actorUserId = (int)$actorUser['id'];

        $priorities = (array)config('hr_support.priorities', ['Low', 'Medium', 'High', 'Critical']);
        $statuses = (array)config('hr_support.statuses', ['Submitted', 'Under Review', 'In Progress', 'Waiting for HR', 'Resolved', 'Closed']);
        $transitions = (array)config('hr_support.admin_transitions', []);

        $requestedPriority = trim((string)($input['priority'] ?? ''));
        if ($requestedPriority !== '' && !in_array($requestedPriority, $priorities, true)) {
            throw new InvalidArgumentException('Invalid ticket priority selected.', 422);
        }

        $requestedStatus = trim((string)($input['status'] ?? ''));
        if ($requestedStatus !== '' && !in_array($requestedStatus, $statuses, true)) {
            throw new InvalidArgumentException('Invalid ticket status selected.', 422);
        }

        $remarks = trim((string)($input['remarks'] ?? ''));
        if (mb_strlen($remarks) > 2000) {
            throw new InvalidArgumentException('Remarks must not exceed 2,000 characters.', 422);
        }

        $resolutionSummary = isset($input['resolution_summary'])
            ? trim((string)$input['resolution_summary'])
            : null;
        if ($resolutionSummary !== null && mb_strlen($resolutionSummary) > 3000) {
            throw new InvalidArgumentException('Resolution summary must not exceed 3,000 characters.', 422);
        }

        $oldStatus = '';
        $oldPriority = '';
        $statusChanged = false;
        $priorityChanged = false;
        $isReopenTransition = false;

        $startedTx = !$this->pdo->inTransaction();
        if ($startedTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $ticket = $this->tickets->findByRef($ticketRef, true);
            if ($ticket === null) {
                throw new RuntimeException('HR support ticket not found.', 404);
            }

            $ticketId = (int)$ticket['id'];
            $oldStatus = (string)$ticket['status'];
            $oldPriority = (string)$ticket['priority'];

            $fieldsToUpdate = [];

            if ($requestedPriority !== '' && $requestedPriority !== $oldPriority) {
                $priorityChanged = true;
                $fieldsToUpdate['priority'] = $requestedPriority;
            }

            if ($requestedStatus !== '' && $requestedStatus !== $oldStatus) {
                $allowedTargets = (array)($transitions[$oldStatus] ?? []);
                if (!in_array($requestedStatus, $allowedTargets, true)) {
                    throw new RuntimeException("Transition from {$oldStatus} to {$requestedStatus} is not permitted.", 409);
                }

                $statusChanged = true;
                $fieldsToUpdate['status'] = $requestedStatus;
                $now = date('Y-m-d H:i:s');

                if ($requestedStatus === 'Resolved') {
                    $fieldsToUpdate['resolved_at'] = $now;
                    $fieldsToUpdate['closed_at'] = null;
                } elseif ($requestedStatus === 'Closed') {
                    $fieldsToUpdate['closed_at'] = $now;
                    if (empty($ticket['resolved_at'])) {
                        $fieldsToUpdate['resolved_at'] = $now;
                    }
                } elseif (in_array($oldStatus, ['Resolved', 'Closed'], true) && in_array($requestedStatus, ['Under Review', 'In Progress'], true)) {
                    $isReopenTransition = true;
                    $fieldsToUpdate['resolved_at'] = null;
                    $fieldsToUpdate['closed_at'] = null;
                    $fieldsToUpdate['reopen_count'] = (int)($ticket['reopen_count'] ?? 0) + 1;
                }
            }

            if ($resolutionSummary !== null && $resolutionSummary !== '') {
                $fieldsToUpdate['resolution_summary'] = $resolutionSummary;
            }

            if ($fieldsToUpdate === []) {
                if ($startedTx) {
                    $this->pdo->commit();
                }
                return $ticket;
            }

            $this->tickets->updateTicketFields($ticketId, $fieldsToUpdate);

            if ($priorityChanged) {
                $this->tickets->addHistory(
                    $ticketId,
                    'priority_changed',
                    null,
                    null,
                    $oldPriority,
                    $requestedPriority,
                    $actorUserId,
                    'admin',
                    $remarks !== '' ? $remarks : "Priority updated from {$oldPriority} to {$requestedPriority}."
                );
            }

            if ($statusChanged) {
                $eventKind = match (true) {
                    $isReopenTransition           => 'reopened',
                    $requestedStatus === 'Resolved' => 'resolved',
                    $requestedStatus === 'Closed'   => 'closed',
                    default                         => 'status_changed',
                };

                $this->tickets->addHistory(
                    $ticketId,
                    $eventKind,
                    $oldStatus,
                    $requestedStatus,
                    null,
                    null,
                    $actorUserId,
                    'admin',
                    $remarks !== '' ? $remarks : "Status updated from {$oldStatus} to {$requestedStatus}."
                );

                if ($remarks !== '') {
                    $this->tickets->addMessage([
                        'ticket_id'        => $ticketId,
                        'sender_user_id'   => $actorUserId,
                        'sender_role'      => 'admin',
                        'message_type'     => 'status_change',
                        'is_internal_note' => 0,
                        'body'             => $remarks,
                    ]);
                }
            }

            $updated = $this->tickets->findById($ticketId) ?? $ticket;

            if ($startedTx) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        if ($priorityChanged) {
            $this->recordAudit(
                $actorUser,
                'HR_SUPPORT_PRIORITY_CHANGED',
                "Changed priority on {$updated['ticket_ref']} from {$oldPriority} to {$requestedPriority}.",
                'hr_support_ticket',
                (string)$updated['id'],
                ['priority' => $oldPriority],
                ['priority' => $requestedPriority]
            );
        }

        if ($statusChanged) {
            $auditAction = match (true) {
                $isReopenTransition            => 'HR_SUPPORT_TICKET_REOPENED',
                $requestedStatus === 'Resolved' => 'HR_SUPPORT_TICKET_RESOLVED',
                $requestedStatus === 'Closed'   => 'HR_SUPPORT_TICKET_CLOSED',
                default                         => 'HR_SUPPORT_STATUS_CHANGED',
            };

            $this->recordAudit(
                $actorUser,
                $auditAction,
                "Updated status on {$updated['ticket_ref']} from {$oldStatus} to {$requestedStatus}.",
                'hr_support_ticket',
                (string)$updated['id'],
                ['status' => $oldStatus],
                ['status' => $requestedStatus]
            );

            $this->safeNotify(fn () => $this->notifications->notifyTicketStatusChanged($updated, $oldStatus, $requestedStatus));
        }

        return $updated;
    }

    /**
     * Admin adds an HR-visible reply, an information request, or a strictly Admin-only internal note.
     *
     * @param array<string, mixed>      $actorUser
     * @param array<string, mixed>|null $filesInput
     * @return array<string, mixed>
     */
    public function addAdminMessage(
        array $actorUser,
        string $ticketRef,
        string $body,
        string $mode = 'reply',
        ?array $filesInput = null,
        ?string $targetStatus = null
    ): array {
        $this->assertAdminManage($actorUser);
        $actorUserId = (int)$actorUser['id'];

        $normalizedMode = match (strtolower(trim($mode))) {
            'internal_note', 'internal', 'note' => 'internal_note',
            'info_request', 'request_info'      => 'info_request',
            default                             => 'reply',
        };

        $isInternal = $normalizedMode === 'internal_note';
        if ($isInternal && !$this->access->canViewInternalNotes($actorUser, $this->pdo)) {
            throw new RuntimeException('You do not have permission to post internal Administrator notes.', 403);
        }

        $cleanBody = trim($body);
        if (mb_strlen($cleanBody) < 2 || mb_strlen($cleanBody) > 5000) {
            throw new InvalidArgumentException('Message body must be between 2 and 5,000 characters.', 422);
        }

        $normalizedFiles = SupportAttachmentService::normalizeFilesArray($filesInput);
        $validatedFiles = $this->attachments->validateBatch($normalizedFiles);

        $storedPaths = [];
        $oldStatus = '';
        $newStatus = null;
        $messageId = 0;

        $startedTx = !$this->pdo->inTransaction();
        if ($startedTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $ticket = $this->tickets->findByRef($ticketRef, true);
            if ($ticket === null) {
                throw new RuntimeException('HR support ticket not found.', 404);
            }

            $ticketId = (int)$ticket['id'];
            $oldStatus = (string)$ticket['status'];

            $messageType = match ($normalizedMode) {
                'internal_note' => 'internal_note',
                'info_request'  => 'info_request',
                default         => 'admin_response',
            };

            $messageId = $this->tickets->addMessage([
                'ticket_id'        => $ticketId,
                'sender_user_id'   => $actorUserId,
                'sender_role'      => 'admin',
                'message_type'     => $messageType,
                'is_internal_note' => $isInternal ? 1 : 0,
                'body'             => $cleanBody,
            ]);

            if ($validatedFiles !== []) {
                $storedBatch = $this->attachments->storeBatch($ticketId, $validatedFiles);
                $storedPaths = array_column($storedBatch, 'storage_relative_path');
                foreach ($storedBatch as $fileMeta) {
                    $this->tickets->addAttachment([
                        'ticket_id'             => $ticketId,
                        'message_id'            => $messageId,
                        'uploaded_by_user_id'   => $actorUserId,
                        'original_filename'     => $fileMeta['original_filename'],
                        'stored_filename'       => $fileMeta['stored_filename'],
                        'storage_relative_path' => $fileMeta['storage_relative_path'],
                        'mime_type'             => $fileMeta['mime_type'],
                        'file_size_bytes'       => $fileMeta['file_size_bytes'],
                        'sha256_checksum'       => $fileMeta['sha256_checksum'],
                        'is_internal_only'      => $isInternal ? 1 : 0,
                    ]);
                }
            }

            if ($isInternal) {
                $this->tickets->updateTicketFields($ticketId, []);
            } else {
                $transitions = (array)config('hr_support.admin_transitions', []);
                $desiredStatus = $targetStatus !== null ? trim($targetStatus) : '';
                if ($normalizedMode === 'info_request' && $desiredStatus === '') {
                    $desiredStatus = 'Waiting for HR';
                } elseif ($desiredStatus === '' && $oldStatus === 'Submitted') {
                    $desiredStatus = 'Under Review';
                }

                if ($desiredStatus !== '' && $desiredStatus !== $oldStatus) {
                    $allowedTargets = (array)($transitions[$oldStatus] ?? []);
                    if (!in_array($desiredStatus, $allowedTargets, true)) {
                        throw new RuntimeException("Transition from {$oldStatus} to {$desiredStatus} is not permitted.", 409);
                    }
                    $newStatus = $desiredStatus;
                    $now = date('Y-m-d H:i:s');
                    $updateFields = ['status' => $newStatus];
                    if ($newStatus === 'Resolved') {
                        $updateFields['resolved_at'] = $now;
                        $updateFields['closed_at'] = null;
                    } elseif ($newStatus === 'Closed') {
                        $updateFields['closed_at'] = $now;
                        if (empty($ticket['resolved_at'])) {
                            $updateFields['resolved_at'] = $now;
                        }
                    } elseif (in_array($oldStatus, ['Resolved', 'Closed'], true) && in_array($newStatus, ['Under Review', 'In Progress'], true)) {
                        $updateFields['resolved_at'] = null;
                        $updateFields['closed_at'] = null;
                        $updateFields['reopen_count'] = (int)($ticket['reopen_count'] ?? 0) + 1;
                    }

                    $this->tickets->updateTicketFields($ticketId, $updateFields);

                    $eventKind = match ($newStatus) {
                        'Resolved' => 'resolved',
                        'Closed'   => 'closed',
                        default    => (in_array($oldStatus, ['Resolved', 'Closed'], true) ? 'reopened' : 'status_changed'),
                    };
                    $this->tickets->addHistory(
                        $ticketId,
                        $eventKind,
                        $oldStatus,
                        $newStatus,
                        null,
                        null,
                        $actorUserId,
                        'admin',
                        $normalizedMode === 'info_request'
                            ? 'Administrator requested additional details from HR.'
                            : "Administrator posted response and updated status to {$newStatus}."
                    );
                } else {
                    $this->tickets->updateTicketFields($ticketId, []);
                }
            }

            $updated = $this->tickets->findById($ticketId) ?? $ticket;

            if ($startedTx) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->attachments->rollbackStoredFiles($storedPaths);
            throw $e;
        }

        if ($isInternal) {
            // Never record internal note body in audit log payload or description
            $this->recordAudit(
                $actorUser,
                'HR_SUPPORT_INTERNAL_NOTE_ADDED',
                "Added private Admin internal note on ticket {$updated['ticket_ref']}.",
                'hr_support_ticket',
                (string)$updated['id'],
                null,
                [
                    'ticket_ref'       => $updated['ticket_ref'],
                    'message_id'       => $messageId,
                    'is_internal_note' => true,
                    'attachment_count' => count($storedPaths),
                ]
            );
        } else {
            $auditAction = $normalizedMode === 'info_request' ? 'HR_SUPPORT_INFO_REQUESTED' : 'HR_SUPPORT_ADMIN_RESPONDED';
            $this->recordAudit(
                $actorUser,
                $auditAction,
                "Administrator posted public ticket communication on {$updated['ticket_ref']}.",
                'hr_support_ticket',
                (string)$updated['id'],
                $newStatus !== null ? ['status' => $oldStatus] : null,
                array_filter([
                    'ticket_ref'       => $updated['ticket_ref'],
                    'message_id'       => $messageId,
                    'status'           => $newStatus,
                    'attachment_count' => count($storedPaths),
                ], static fn ($v) => $v !== null)
            );

            $this->safeNotify(fn () => $this->notifications->notifyAdminResponded(
                $updated,
                $normalizedMode === 'info_request',
                $newStatus
            ));
        }

        return $updated;
    }

    /**
     * Authorize attachment download for either HR submitter or Admin inbox manager,
     * rejecting internal-only attachments when requested by HR.
     *
     * @param array<string, mixed> $actorUser
     * @return array{attachment: array<string, mixed>, file_path: string}
     */
    public function authorizeAttachmentDownload(
        array $actorUser,
        int $attachmentId,
        string $surface = 'auto'
    ): array {
        $attachment = $this->tickets->findAttachmentWithTicket($attachmentId);
        if ($attachment === null) {
            throw new RuntimeException('Attachment record not found.', 404);
        }

        if (!$this->access->canDownloadAttachment($actorUser, $attachment, $this->pdo, $surface)) {
            $this->recordAudit(
                $actorUser,
                'HR_SUPPORT_ATTACHMENT_DENIED',
                'Rejected unauthorized HR support attachment download attempt.',
                'hr_support_attachment',
                (string)$attachmentId,
                null,
                ['ticket_ref' => $attachment['ticket_ref'] ?? null],
                AuditService::STATUS_FAILURE
            );
            throw new RuntimeException('You are not authorized to download this support attachment.', 403);
        }

        $filePath = $this->attachments->resolveAuthorizedFilePath((string)($attachment['storage_relative_path'] ?? ''));
        if ($filePath === null) {
            throw new RuntimeException('Requested attachment file is unavailable on storage.', 404);
        }

        return [
            'attachment' => $attachment,
            'file_path'  => $filePath,
        ];
    }

    /**
     * @param array<string, mixed> $actorUser
     */
    private function assertAdminManage(array $actorUser): void
    {
        $uid = (int)($actorUser['id'] ?? 0);
        if (
            $uid <= 0
            || ($actorUser['status'] ?? 'Active') !== 'Active'
            || strtolower((string)($actorUser['role'] ?? '')) !== 'admin'
            || !$this->access->hasCapability($actorUser, 'hr_support.admin_manage', $this->pdo)
        ) {
            throw new RuntimeException('Administrator support management authorization is required.', 403);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $attachments
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function groupAttachmentsByMessage(array $attachments): array
    {
        $grouped = [];
        foreach ($attachments as $att) {
            $mid = (int)($att['message_id'] ?? 0);
            $grouped[$mid][] = $att;
        }
        return $grouped;
    }

    private function safeNotify(callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            error_log('[HrSupportWorkflow] Non-blocking notification error: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed>      $actorUser
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    private function recordAudit(
        array $actorUser,
        string $action,
        string $description,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $status = AuditService::STATUS_SUCCESS
    ): void {
        try {
            $this->audit->record([
                'actor_user_id' => (int)($actorUser['id'] ?? 0) ?: null,
                'actor_role'    => strtolower((string)($actorUser['role'] ?? 'hr')),
                'action'        => $action,
                'category'      => AuditService::CATEGORY_SUPPORT,
                'description'   => $description,
                'entity_type'   => $entityType,
                'entity_id'     => $entityId,
                'old_values'    => $oldValues,
                'new_values'    => $newValues,
                'status'        => $status,
            ]);
        } catch (Throwable $e) {
            error_log('[HrSupportWorkflow] Audit write error: ' . $e->getMessage());
        }
    }
}
