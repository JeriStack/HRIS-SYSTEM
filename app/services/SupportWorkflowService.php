<?php

/**
 * SupportWorkflowService
 * Orchestrates ticket/complaint creation, employee replies, reopen/closure rules,
 * HR triage/assignment/status updates, internal note isolation, and privacy-safe auditing.
 */

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\SupportTicketHandler;
use App\Models\User;
use Core\AuditService;
use InvalidArgumentException;
use PDO;
use RuntimeException;

class SupportWorkflowService
{
    private SupportTicket $tickets;
    private SupportTicketHandler $handlers;
    private SupportAccessService $access;
    private SupportAttachmentService $attachments;
    private SupportNotificationService $notifications;
    private User $users;
    private AuditService $audit;
    private PDO $pdo;

    public function __construct(
        ?PDO $pdo = null,
        ?SupportTicket $tickets = null,
        ?SupportTicketHandler $handlers = null,
        ?SupportAccessService $access = null,
        ?SupportAttachmentService $attachments = null,
        ?SupportNotificationService $notifications = null,
        ?User $users = null,
        ?AuditService $audit = null
    ) {
        if ($tickets !== null && $pdo === null) {
            $pdo = $tickets->getDb();
        }
        $this->tickets = $tickets ?? new SupportTicket($pdo);
        $this->pdo = $this->tickets->getDb();
        $this->handlers = $handlers ?? new SupportTicketHandler($this->pdo);
        $this->access = $access ?? new SupportAccessService();
        $this->attachments = $attachments ?? new SupportAttachmentService();
        $this->notifications = $notifications ?? new SupportNotificationService(
            new \App\Models\Notification($this->pdo),
            new User($this->pdo),
            $this->handlers
        );
        $this->users = $users ?? new User($this->pdo);
        $this->audit = $audit ?? AuditService::getInstance($this->pdo);
    }

    /**
     * Create a general support request or confidential workplace complaint.
     * Derives employee identity strictly from the authenticated user session.
     *
     * @param array<string, mixed> $actorUser
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $filesInput
     * @return array<string, mixed> Created ticket row
     */
    public function createSubmission(array $actorUser, array $input, ?array $filesInput = null): array
    {
        $userId = (int)($actorUser['id'] ?? 0);
        $employeeId = isset($actorUser['employee_id']) && $actorUser['employee_id'] !== null
            ? (int)$actorUser['employee_id']
            : 0;

        if ($userId <= 0 || ($actorUser['status'] ?? 'Active') !== 'Active') {
            throw new RuntimeException('Your user session is not active.', 403);
        }

        if ($employeeId <= 0) {
            throw new InvalidArgumentException('An active employee profile linked to your account is required to submit a request.');
        }

        if (!$this->access->hasCapability($actorUser, 'support.create', $this->pdo)) {
            throw new RuntimeException('You do not have permission to submit support requests.', 403);
        }

        // Prevent client-side employee_id spoofing
        if (isset($input['employee_id']) && trim((string)$input['employee_id']) !== '' && (int)$input['employee_id'] !== $employeeId) {
            $this->recordAudit($userId, $actorUser, 'SUPPORT_SPOOF_ATTEMPT', 'Rejected attempt to submit ticket under another employee ID.', null, null, null, 'failed');
            throw new RuntimeException('You cannot submit a support request or complaint on behalf of another employee ID.', 403);
        }

        // Rate limit submissions cleanly via DB audit window
        $windowSec = (int)config('support.submission_rate_limit_window', 900);
        $maxActions = (int)config('support.submission_rate_limit_max', 10);
        $rate = $this->users->countRecentActions($userId, 'SUPPORT_TICKET_SUBMITTED', $windowSec);
        $complaintRate = $this->users->countRecentActions($userId, 'COMPLAINT_SUBMITTED', $windowSec);
        if (($rate['count'] + $complaintRate['count']) >= $maxActions) {
            throw new RuntimeException('You have reached the temporary submission limit. Please try again shortly.', 429);
        }

        $requestedType = trim((string)($input['request_type'] ?? 'support'));
        $categories = (array)config('support.support_categories', []);
        $category = trim((string)($input['category'] ?? ''));

        if ($requestedType === 'complaint' || $category === 'Workplace Complaint') {
            $requestType = 'complaint';
            $category = 'Workplace Complaint';
        } else {
            $requestType = 'support';
            if (!isset($categories[$category])) {
                throw new InvalidArgumentException('Please select a valid support request category.');
            }
        }

        $isConfidential = $requestType === 'complaint'
            || !empty($categories[$category]['forces_confidential']);

        $complaintCategory = null;
        $incidentDate = null;
        $subjectEmployeeId = null;
        $subjectReference = null;

        if ($requestType === 'complaint') {
            $complaintCategories = (array)config('support.complaint_categories', []);
            $complaintCategory = trim((string)($input['complaint_category'] ?? ''));
            if (!isset($complaintCategories[$complaintCategory])) {
                throw new InvalidArgumentException('Please select a valid workplace complaint category.');
            }

            if (empty($input['identity_acknowledgment'])) {
                throw new InvalidArgumentException('Please confirm the confidentiality and non-anonymous reporting notice before submitting.');
            }

            $rawDate = trim((string)($input['incident_date'] ?? ''));
            if ($rawDate !== '') {
                $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate);
                if (!$parsed || $parsed->format('Y-m-d') !== $rawDate) {
                    throw new InvalidArgumentException('Please provide a valid incident date (YYYY-MM-DD).');
                }
                if ($parsed > new \DateTimeImmutable('today')) {
                    throw new InvalidArgumentException('Incident date cannot be in the future.');
                }
                $incidentDate = $rawDate;
            }

            $rawSubjectEmp = trim((string)($input['subject_employee_id'] ?? ''));
            if ($rawSubjectEmp !== '') {
                $subjectEmployeeId = (int)$rawSubjectEmp;
                if ($subjectEmployeeId <= 0) {
                    throw new InvalidArgumentException('Invalid involved employee selection.');
                }
                if ($subjectEmployeeId === $employeeId) {
                    throw new InvalidArgumentException('You cannot name yourself as the subject of a workplace complaint.');
                }
                $empCheck = $this->pdo->prepare("SELECT id FROM employees WHERE id = :id LIMIT 1");
                $empCheck->execute(['id' => $subjectEmployeeId]);
                if ($empCheck->fetchColumn() === false) {
                    throw new InvalidArgumentException('Selected subject employee record was not found.');
                }
            }

            $rawSubjectRef = trim((string)($input['subject_reference'] ?? ''));
            if ($rawSubjectRef !== '') {
                $subjectReference = mb_substr($rawSubjectRef, 0, 150);
            }
        }

        $subject = trim((string)($input['subject'] ?? ''));
        if (mb_strlen($subject) < 5 || mb_strlen($subject) > 200) {
            throw new InvalidArgumentException('Subject must be between 5 and 200 characters.');
        }

        $description = trim((string)($input['description'] ?? ''));
        if (mb_strlen($description) < 15 || mb_strlen($description) > 5000) {
            throw new InvalidArgumentException('Detailed description must be between 15 and 5,000 characters.');
        }

        $priorities = (array)config('support.priorities', ['Low', 'Medium', 'High', 'Urgent']);
        $defaultPriority = (string)($categories[$category]['default_priority'] ?? 'Medium');
        $priority = trim((string)($input['priority'] ?? $defaultPriority));
        if ($requestType === 'complaint' && $priority === '') {
            $priority = 'High';
        }
        if (!in_array($priority, $priorities, true)) {
            $priority = $defaultPriority;
        }

        $metadata = $this->extractCategoryMetadata($category, $input);
        $metadataJson = $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;

        // Validate uploads before opening the DB write transaction
        $normalizedFiles = SupportAttachmentService::normalizeFilesArray($filesInput);
        $validatedFiles = $this->attachments->validateBatch($normalizedFiles);

        // Determine complaint escalation if no non-conflicted authorized HR confidential handler exists
        $escalatedFlag = 0;
        $escalationReason = null;
        if ($isConfidential) {
            $eligibleHr = $this->handlers->getEligibleConfidentialHandlers($subjectEmployeeId, true);
            if ($eligibleHr === []) {
                $escalatedFlag = 1;
                $escalationReason = 'No non-conflicted authorized HR confidential handler available; escalated for Administrator oversight.';
            }
        }

        $storedPaths = [];
        $this->pdo->beginTransaction();

        try {
            $ticketRef = $this->tickets->generateNextTicketRef((int)date('Y'), $this->pdo);

            $ticketId = $this->tickets->create([
                'ticket_ref'             => $ticketRef,
                'employee_id'            => $employeeId,
                'submitted_by_user_id'   => $userId,
                'request_type'           => $requestType,
                'category'               => $category,
                'complaint_category'     => $complaintCategory,
                'is_confidential'        => $isConfidential ? 1 : 0,
                'subject_employee_id'    => $subjectEmployeeId,
                'subject_reference'      => $subjectReference,
                'subject'                => $subject,
                'description'            => $description,
                'incident_date'          => $incidentDate,
                'category_metadata_json' => $metadataJson,
                'priority'               => $priority,
                'status'                 => 'Submitted',
                'assigned_to_user_id'    => null,
                'escalated_flag'         => $escalatedFlag,
                'escalation_reason'      => $escalationReason,
            ]);

            $messageId = $this->tickets->addMessage([
                'ticket_id'        => $ticketId,
                'sender_user_id'   => $userId,
                'sender_role'      => 'employee',
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
                        'is_confidential'       => $isConfidential ? 1 : 0,
                        'is_internal_only'      => 0,
                    ]);
                }
            }

            $this->tickets->addStatusHistory([
                'ticket_id'          => $ticketId,
                'from_status'        => null,
                'to_status'          => 'Submitted',
                'changed_by_user_id' => $userId,
                'remarks'            => $isConfidential
                    ? 'Confidential workplace complaint logged.'
                    : 'Support request submitted by employee.',
            ]);

            if ($escalatedFlag === 1) {
                $this->tickets->addAssignment([
                    'ticket_id'           => $ticketId,
                    'assigned_to_user_id' => null,
                    'assigned_by_user_id' => $userId,
                    'action'              => 'escalated',
                    'notes'               => $escalationReason,
                ]);
            }

            $created = $this->tickets->findById($ticketId) ?? [
                'id'                   => $ticketId,
                'ticket_ref'           => $ticketRef,
                'employee_id'          => $employeeId,
                'submitted_by_user_id' => $userId,
                'request_type'         => $requestType,
                'category'             => $category,
                'is_confidential'      => $isConfidential ? 1 : 0,
                'subject_employee_id'  => $subjectEmployeeId,
                'escalated_flag'       => $escalatedFlag,
            ];

            $this->notifications->notifyTicketSubmitted($created);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->attachments->rollbackStoredFiles($storedPaths);
            throw $e;
        }

        // Record privacy-aware audit log (never log complaint narratives/subjects/identities in confidential audits)
        $action = $isConfidential ? 'COMPLAINT_SUBMITTED' : 'SUPPORT_TICKET_SUBMITTED';
        $auditMeta = $isConfidential
            ? [
                'ticket_ref'      => $created['ticket_ref'],
                'request_type'    => 'complaint',
                'is_confidential' => true,
                'escalated'       => $escalatedFlag === 1,
            ]
            : [
                'ticket_ref' => $created['ticket_ref'],
                'category'   => $category,
                'priority'   => $priority,
            ];

        $this->recordAudit(
            $userId,
            $actorUser,
            $action,
            $isConfidential
                ? "Confidential workplace complaint logged ({$created['ticket_ref']})."
                : "Submitted support request {$created['ticket_ref']} ({$category}).",
            (int)$created['id'],
            null,
            $auditMeta
        );

        return $created;
    }

    /**
     * Post an employee follow-up reply (with optional supporting files).
     * Automatically transitions `Waiting for Employee` -> `Under Review`.
     *
     * @param array<string, mixed>      $actorUser
     * @param array<string, mixed>|null $filesInput
     * @return array<string, mixed>
     */
    public function employeeReply(
        array $actorUser,
        int $ticketId,
        string $body,
        ?array $filesInput = null
    ): array {
        $ticket = $this->tickets->findById($ticketId);
        if (!$ticket) {
            throw new RuntimeException('Support request not found.', 404);
        }

        if (
            !$this->access->canViewTicketAsEmployee($actorUser, $ticket, $this->pdo)
            || !$this->access->hasCapability($actorUser, 'support.reply_own', $this->pdo)
        ) {
            throw new RuntimeException('You are not authorized to reply to this support request.', 403);
        }

        if (!$this->access->canEmployeeReply($ticket)) {
            throw new InvalidArgumentException('Replies can only be added while the request is active. Resolved requests must be reopened within policy.');
        }

        $cleanBody = trim($body);
        if (mb_strlen($cleanBody) < 2 || mb_strlen($cleanBody) > 5000) {
            throw new InvalidArgumentException('Reply message must be between 2 and 5,000 characters.');
        }

        $normalizedFiles = SupportAttachmentService::normalizeFilesArray($filesInput);
        $validatedFiles = $this->attachments->validateBatch($normalizedFiles);
        $isConf = $this->access->isConfidentialTicket($ticket);
        $userId = (int)$actorUser['id'];
        $currentStatus = (string)$ticket['status'];
        $transitionToReview = $currentStatus === 'Waiting for Employee';
        $storedPaths = [];

        $this->pdo->beginTransaction();
        try {
            $messageId = $this->tickets->addMessage([
                'ticket_id'        => $ticketId,
                'sender_user_id'   => $userId,
                'sender_role'      => 'employee',
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
                        'uploaded_by_user_id'   => $userId,
                        'original_filename'     => $fileMeta['original_filename'],
                        'stored_filename'       => $fileMeta['stored_filename'],
                        'storage_relative_path' => $fileMeta['storage_relative_path'],
                        'mime_type'             => $fileMeta['mime_type'],
                        'file_size_bytes'       => $fileMeta['file_size_bytes'],
                        'sha256_checksum'       => $fileMeta['sha256_checksum'],
                        'is_confidential'       => $isConf ? 1 : 0,
                        'is_internal_only'      => 0,
                    ]);
                }
            }

            $updates = ['last_activity_at' => date('Y-m-d H:i:s')];
            if ($transitionToReview) {
                $updates['status'] = 'Under Review';
                $this->tickets->addStatusHistory([
                    'ticket_id'          => $ticketId,
                    'from_status'        => $currentStatus,
                    'to_status'          => 'Under Review',
                    'changed_by_user_id' => $userId,
                    'remarks'            => 'Employee provided requested information.',
                ]);
            }

            $this->tickets->updateTicketFields($ticketId, $updates);
            $updated = $this->tickets->findById($ticketId) ?? $ticket;
            $this->notifications->notifyEmployeeReplied($updated, false);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->attachments->rollbackStoredFiles($storedPaths);
            throw $e;
        }

        $this->recordAudit(
            $userId,
            $actorUser,
            $isConf ? 'COMPLAINT_EMPLOYEE_REPLIED' : 'SUPPORT_EMPLOYEE_REPLIED',
            "Employee replied on {$updated['ticket_ref']}.",
            $ticketId,
            $transitionToReview ? ['status' => $currentStatus] : null,
            ['ticket_ref' => $updated['ticket_ref'], 'status' => $updated['status']]
        );

        return $updated;
    }

    /**
     * Reopen a Resolved ticket within the configured calendar-day policy window.
     *
     * @param array<string, mixed> $actorUser
     * @return array<string, mixed>
     */
    public function employeeReopen(array $actorUser, int $ticketId, string $reason): array
    {
        $ticket = $this->tickets->findById($ticketId);
        if (!$ticket) {
            throw new RuntimeException('Support request not found.', 404);
        }

        if (
            !$this->access->canViewTicketAsEmployee($actorUser, $ticket, $this->pdo)
            || !$this->access->hasCapability($actorUser, 'support.reply_own', $this->pdo)
        ) {
            throw new RuntimeException('You are not authorized to reopen this support request.', 403);
        }

        if (!$this->access->canEmployeeReopen($ticket)) {
            $days = (int)config('support.reopen_window_days', 14);
            throw new RuntimeException(
                "Only Resolved requests within {$days} days may be reopened by employees. Please submit a new request or contact HR.",
                403
            );
        }

        $cleanReason = trim($reason);
        if (mb_strlen($cleanReason) < 5 || mb_strlen($cleanReason) > 2000) {
            throw new InvalidArgumentException('Please explain why you are reopening this request (5 to 2,000 characters).');
        }

        $userId = (int)$actorUser['id'];
        $isConf = $this->access->isConfidentialTicket($ticket);

        $this->pdo->beginTransaction();
        try {
            $this->tickets->updateTicketFields($ticketId, [
                'status'           => 'Under Review',
                'resolved_at'      => null,
                'last_activity_at' => date('Y-m-d H:i:s'),
            ]);

            $this->tickets->addStatusHistory([
                'ticket_id'          => $ticketId,
                'from_status'        => 'Resolved',
                'to_status'          => 'Under Review',
                'changed_by_user_id' => $userId,
                'remarks'            => $isConf ? 'Reopened by employee within policy window.' : $cleanReason,
            ]);

            $this->tickets->addMessage([
                'ticket_id'        => $ticketId,
                'sender_user_id'   => $userId,
                'sender_role'      => 'employee',
                'message_type'     => 'employee_reply',
                'is_internal_note' => 0,
                'body'             => "Reopened request: " . $cleanReason,
            ]);

            $updated = $this->tickets->findById($ticketId) ?? $ticket;
            $this->notifications->notifyEmployeeReplied($updated, true);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->recordAudit(
            $userId,
            $actorUser,
            'SUPPORT_TICKET_REOPENED',
            "Ticket {$updated['ticket_ref']} reopened within policy window.",
            $ticketId,
            ['status' => 'Resolved'],
            ['status' => 'Under Review']
        );

        return $updated;
    }

    /**
     * Allow an employee to confirm final closure ONLY when the ticket is already Resolved.
     *
     * @param array<string, mixed> $actorUser
     * @return array<string, mixed>
     */
    public function employeeConfirmClose(array $actorUser, int $ticketId, ?string $remarks = null): array
    {
        $ticket = $this->tickets->findById($ticketId);
        if (!$ticket) {
            throw new RuntimeException('Support request not found.', 404);
        }

        if (
            !$this->access->canViewTicketAsEmployee($actorUser, $ticket, $this->pdo)
            || !$this->access->hasCapability($actorUser, 'support.reply_own', $this->pdo)
        ) {
            throw new RuntimeException('You are not authorized to close this support request.', 403);
        }

        if (!$this->access->canEmployeeConfirmClose($ticket)) {
            throw new RuntimeException('Employees cannot directly close or resolve active requests until HR marks them as Resolved.', 403);
        }

        $userId = (int)$actorUser['id'];
        $cleanRemarks = trim((string)$remarks);
        $note = $cleanRemarks !== '' ? mb_substr($cleanRemarks, 0, 1000) : 'Closure confirmed by employee.';
        $now = date('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $this->tickets->updateTicketFields($ticketId, [
                'status'           => 'Closed',
                'closed_at'        => $now,
                'last_activity_at' => $now,
            ]);

            $this->tickets->addStatusHistory([
                'ticket_id'          => $ticketId,
                'from_status'        => 'Resolved',
                'to_status'          => 'Closed',
                'changed_by_user_id' => $userId,
                'remarks'            => $note,
            ]);

            $this->tickets->addMessage([
                'ticket_id'        => $ticketId,
                'sender_user_id'   => $userId,
                'sender_role'      => 'employee',
                'message_type'     => 'status_change',
                'is_internal_note' => 0,
                'body'             => $note,
            ]);

            $updated = $this->tickets->findById($ticketId) ?? $ticket;
            $this->notifications->notifyStatusChanged($updated, 'Closed');
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->recordAudit(
            $userId,
            $actorUser,
            'SUPPORT_TICKET_CLOSED',
            "Ticket {$updated['ticket_ref']} confirmed closed by employee.",
            $ticketId,
            ['status' => 'Resolved'],
            ['status' => 'Closed']
        );

        return $updated;
    }

    /**
     * Post an HR response, additional information request, or strictly isolated internal HR note.
     *
     * @param array<string, mixed>      $actorUser
     * @param array<string, mixed>      $input
     * @param array<string, mixed>|null $filesInput
     * @return array<string, mixed>
     */
    public function hrRespond(array $actorUser, int $ticketId, array $input, ?array $filesInput = null): array
    {
        $ticket = $this->tickets->findById($ticketId);
        if (!$ticket) {
            throw new RuntimeException('Support ticket not found.', 404);
        }

        if (!$this->access->canHandleTicketAsHr($actorUser, $ticket, 'support.respond', $this->handlers, $this->pdo)) {
            $reason = $this->access->isConflicted($actorUser, $ticket) ? 'coi_blocked' : 'unauthorized_handler';
            $actionType = $reason === 'coi_blocked' ? 'coi_blocked' : ($this->access->isConfidentialTicket($ticket) ? 'view_complaint' : 'view_ticket');
            $this->access->recordSensitiveAccess($this->tickets, $ticketId, null, (int)($actorUser['id'] ?? 0), $actionType, false, $reason);
            throw new RuntimeException('You are not authorized to respond to this support ticket.', 403);
        }

        $mode = trim((string)($input['response_mode'] ?? 'public_reply'));
        if (!in_array($mode, ['public_reply', 'request_info', 'internal_note'], true)) {
            throw new InvalidArgumentException('Invalid HR response mode selected.');
        }

        $body = trim((string)($input['body'] ?? ''));
        if (mb_strlen($body) < 2 || mb_strlen($body) > 5000) {
            throw new InvalidArgumentException('Message body must be between 2 and 5,000 characters.');
        }

        $normalizedFiles = SupportAttachmentService::normalizeFilesArray($filesInput);
        $validatedFiles = $this->attachments->validateBatch($normalizedFiles);

        $isInternal = $mode === 'internal_note';
        $messageType = match ($mode) {
            'internal_note' => 'internal_note',
            'request_info'  => 'info_request',
            default         => 'hr_response',
        };
        $senderRole = strtolower((string)($actorUser['role'] ?? 'hr')) === 'admin' ? 'admin' : 'hr';
        $userId = (int)$actorUser['id'];
        $isConf = $this->access->isConfidentialTicket($ticket);
        $currentStatus = (string)$ticket['status'];
        $newStatus = $currentStatus;

        if ($mode === 'request_info' && in_array($currentStatus, ['Submitted', 'Under Review'], true)) {
            $newStatus = 'Waiting for Employee';
        } elseif ($mode === 'public_reply' && $currentStatus === 'Submitted') {
            $newStatus = 'Under Review';
        }

        $storedPaths = [];
        $this->pdo->beginTransaction();

        try {
            $messageId = $this->tickets->addMessage([
                'ticket_id'        => $ticketId,
                'sender_user_id'   => $userId,
                'sender_role'      => $senderRole,
                'message_type'     => $messageType,
                'is_internal_note' => $isInternal ? 1 : 0,
                'body'             => $body,
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
                        'is_confidential'       => $isConf ? 1 : 0,
                        'is_internal_only'      => $isInternal ? 1 : 0,
                    ]);
                }
            }

            $updates = ['last_activity_at' => date('Y-m-d H:i:s')];
            if ($newStatus !== $currentStatus) {
                $updates['status'] = $newStatus;
                $isPublicTicket = !empty($ticket['is_public_ticket']) || (($ticket['support_channel'] ?? '') === 'public_auth');
                $targetLabel = $isPublicTicket ? 'requester' : 'employee';
                $this->tickets->addStatusHistory([
                    'ticket_id'          => $ticketId,
                    'from_status'        => $currentStatus,
                    'to_status'          => $newStatus,
                    'changed_by_user_id' => $userId,
                    'remarks'            => $mode === 'request_info'
                        ? "Additional information requested from {$targetLabel}."
                        : "HR began review and responded to {$targetLabel}.",
                ]);
            }

            $this->tickets->updateTicketFields($ticketId, $updates);
            $updated = $this->tickets->findById($ticketId) ?? $ticket;

            $this->pdo->commit();

            if (!$isInternal) {
                $this->notifications->notifyHrPublicResponse($updated, $mode === 'request_info', $body);
            }
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->attachments->rollbackStoredFiles($storedPaths);
            throw $e;
        }

        if ($isConf) {
            $this->access->recordSensitiveAccess($this->tickets, $ticketId, null, $userId, 'view_complaint', true, $mode);
        }

        $this->recordAudit(
            $userId,
            $actorUser,
            $isInternal ? 'SUPPORT_INTERNAL_NOTE_ADDED' : ($mode === 'request_info' ? 'SUPPORT_INFO_REQUESTED' : 'SUPPORT_HR_RESPONDED'),
            "HR action ({$mode}) recorded on {$updated['ticket_ref']}.",
            $ticketId,
            ['status' => $currentStatus],
            ['ticket_ref' => $updated['ticket_ref'], 'status' => $updated['status'], 'is_internal_note' => $isInternal]
        );

        return $updated;
    }

    /**
     * Assign or reassign a support ticket/complaint to an authorized, non-conflicted handler.
     *
     * @param array<string, mixed> $actorUser
     * @return array<string, mixed>
     */
    public function assignHandler(array $actorUser, int $ticketId, ?int $targetUserId, ?string $notes = null): array
    {
        $ticket = $this->tickets->findById($ticketId);
        if (!$ticket) {
            throw new RuntimeException('Support ticket not found.', 404);
        }

        if (!$this->access->canHandleTicketAsHr($actorUser, $ticket, 'support.assign', $this->handlers, $this->pdo)) {
            $reason = $this->access->isConflicted($actorUser, $ticket) ? 'coi_blocked' : 'unauthorized_assign';
            $actionType = $reason === 'coi_blocked' ? 'coi_blocked' : 'assign_handler';
            $this->access->recordSensitiveAccess($this->tickets, $ticketId, null, (int)($actorUser['id'] ?? 0), $actionType, false, $reason);
            throw new RuntimeException('You are not authorized to assign handlers for this ticket.', 403);
        }

        $previousAssigneeId = isset($ticket['assigned_to_user_id']) && $ticket['assigned_to_user_id'] !== null
            ? (int)$ticket['assigned_to_user_id']
            : null;
        $cleanNotes = trim((string)$notes);
        $cleanNotes = $cleanNotes !== '' ? mb_substr($cleanNotes, 0, 255) : null;

        $assigneeId = ($targetUserId !== null && $targetUserId > 0) ? $targetUserId : null;
        if ($assigneeId !== null) {
            $candidate = $this->handlers->findCandidateUser($assigneeId);
            if (!$candidate) {
                throw new InvalidArgumentException('Selected handler account could not be found.');
            }
            if (!$this->access->canReceiveAssignment($candidate, $ticket, $this->handlers)) {
                if ($this->access->isConflicted($candidate, $ticket)) {
                    $this->access->recordSensitiveAccess($this->tickets, $ticketId, null, (int)$actorUser['id'], 'coi_blocked', false, 'coi_assignee_rejected');
                    throw new InvalidArgumentException('Conflict of interest: the subject of a complaint cannot be assigned to handle it.');
                }
                throw new InvalidArgumentException('The selected user does not hold active confidential complaint clearance.');
            }
        }

        $action = $assigneeId === null
            ? 'unassigned'
            : ($previousAssigneeId !== null && $previousAssigneeId !== $assigneeId ? 'reassigned' : 'assigned');

        $actorId = (int)$actorUser['id'];
        $currentStatus = (string)$ticket['status'];
        $moveToReview = $assigneeId !== null && $currentStatus === 'Submitted';

        $this->pdo->beginTransaction();
        try {
            $updates = [
                'assigned_to_user_id' => $assigneeId,
                'last_activity_at'    => date('Y-m-d H:i:s'),
            ];
            if ($moveToReview) {
                $updates['status'] = 'Under Review';
                $this->tickets->addStatusHistory([
                    'ticket_id'          => $ticketId,
                    'from_status'        => $currentStatus,
                    'to_status'          => 'Under Review',
                    'changed_by_user_id' => $actorId,
                    'remarks'            => 'Handler assigned and review initiated.',
                ]);
            }

            $this->tickets->updateTicketFields($ticketId, $updates);
            $this->tickets->addAssignment([
                'ticket_id'           => $ticketId,
                'assigned_to_user_id' => $assigneeId,
                'assigned_by_user_id' => $actorId,
                'action'              => $action,
                'notes'               => $cleanNotes,
            ]);

            $updated = $this->tickets->findById($ticketId) ?? $ticket;
            $this->pdo->commit();

            if ($assigneeId !== null) {
                $this->notifications->notifyHandlerAssigned($updated, $assigneeId);
            }
            if ($moveToReview) {
                $this->notifications->notifyStatusChanged($updated, 'Under Review');
            }
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->access->recordSensitiveAccess($this->tickets, $ticketId, null, $actorId, 'assign_handler', true, $action);
        $this->recordAudit(
            $actorId,
            $actorUser,
            'SUPPORT_HANDLER_ASSIGNED',
            "Ticket {$updated['ticket_ref']} {$action}.",
            $ticketId,
            ['assigned_to_user_id' => $previousAssigneeId],
            ['assigned_to_user_id' => $assigneeId, 'action' => $action]
        );

        return $updated;
    }

    /**
     * Update ticket workflow status, priority, and resolution remarks under HR permissions.
     *
     * @param array<string, mixed> $actorUser
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function updateStatusAndPriority(array $actorUser, int $ticketId, array $input): array
    {
        $ticket = $this->tickets->findById($ticketId);
        if (!$ticket) {
            throw new RuntimeException('Support ticket not found.', 404);
        }

        if (!$this->access->canHandleTicketAsHr($actorUser, $ticket, 'support.manage_status', $this->handlers, $this->pdo)) {
            $reason = $this->access->isConflicted($actorUser, $ticket) ? 'coi_blocked' : 'unauthorized_status_update';
            $actionType = $reason === 'coi_blocked' ? 'coi_blocked' : 'update_status';
            $this->access->recordSensitiveAccess($this->tickets, $ticketId, null, (int)($actorUser['id'] ?? 0), $actionType, false, $reason);
            throw new RuntimeException('You are not authorized to update the status of this ticket.', 403);
        }

        $currentStatus = (string)$ticket['status'];
        $currentPriority = (string)$ticket['priority'];
        $newStatus = trim((string)($input['status'] ?? $currentStatus));
        $newPriority = trim((string)($input['priority'] ?? $currentPriority));
        $remarks = trim((string)($input['resolution_remarks'] ?? ''));

        $priorities = (array)config('support.priorities', ['Low', 'Medium', 'High', 'Urgent']);
        if (!in_array($newPriority, $priorities, true)) {
            throw new InvalidArgumentException('Invalid ticket priority value.');
        }

        $allowedMap = (array)config('support.hr_transitions', []);
        $statusChanged = $newStatus !== $currentStatus;
        if ($statusChanged) {
            $permitted = (array)($allowedMap[$currentStatus] ?? []);
            if (!in_array($newStatus, $permitted, true)) {
                throw new InvalidArgumentException("Invalid workflow transition from {$currentStatus} to {$newStatus}.");
            }
        }

        if (in_array($newStatus, ['Resolved', 'Closed'], true) && $statusChanged) {
            $existingRemarks = trim((string)($ticket['resolution_remarks'] ?? ''));
            if (mb_strlen($remarks) < 5 && mb_strlen($existingRemarks) < 5) {
                throw new InvalidArgumentException('Please enter resolution remarks (at least 5 characters) before resolving or closing.');
            }
        }

        $now = date('Y-m-d H:i:s');
        $actorId = (int)$actorUser['id'];
        $isConf = $this->access->isConfidentialTicket($ticket);
        $updates = [
            'priority'         => $newPriority,
            'last_activity_at' => $now,
        ];

        if ($remarks !== '') {
            $updates['resolution_remarks'] = mb_substr($remarks, 0, 5000);
        }

        if ($statusChanged) {
            $updates['status'] = $newStatus;
            if ($newStatus === 'Resolved') {
                $updates['resolved_at'] = $now;
            } elseif ($newStatus === 'Closed') {
                $updates['closed_at'] = $now;
                if (empty($ticket['resolved_at'])) {
                    $updates['resolved_at'] = $now;
                }
            } elseif ($newStatus === 'Under Review') {
                $updates['resolved_at'] = null;
                $updates['closed_at'] = null;
            }
        }

        $this->pdo->beginTransaction();
        try {
            $this->tickets->updateTicketFields($ticketId, $updates);

            if ($statusChanged) {
                $historyRemark = $remarks !== ''
                    ? mb_substr($remarks, 0, 2000)
                    : "Status changed from {$currentStatus} to {$newStatus}.";
                $this->tickets->addStatusHistory([
                    'ticket_id'          => $ticketId,
                    'from_status'        => $currentStatus,
                    'to_status'          => $newStatus,
                    'changed_by_user_id' => $actorId,
                    'remarks'            => $historyRemark,
                ]);

                $senderRole = strtolower((string)($actorUser['role'] ?? 'hr')) === 'admin' ? 'admin' : 'hr';
                $this->tickets->addMessage([
                    'ticket_id'        => $ticketId,
                    'sender_user_id'   => $actorId,
                    'sender_role'      => $senderRole,
                    'message_type'     => 'status_change',
                    'is_internal_note' => 0,
                    'body'             => "Status updated to {$newStatus}: " . $historyRemark,
                ]);
            }

            $updated = $this->tickets->findById($ticketId) ?? $ticket;
            $this->pdo->commit();

            if ($statusChanged) {
                $this->notifications->notifyStatusChanged($updated, $newStatus, $remarks !== '' ? $remarks : null);
            }
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        if ($isConf) {
            $this->access->recordSensitiveAccess($this->tickets, $ticketId, null, $actorId, 'update_status', true, $newStatus);
        }

        $this->recordAudit(
            $actorId,
            $actorUser,
            'SUPPORT_STATUS_UPDATED',
            "Ticket {$updated['ticket_ref']} updated ({$currentStatus} -> {$newStatus}, priority {$newPriority}).",
            $ticketId,
            ['status' => $currentStatus, 'priority' => $currentPriority],
            ['status' => $newStatus, 'priority' => $newPriority]
        );

        return $updated;
    }

    /**
     * Authorize or revoke user-level confidential complaint handling designation.
     *
     * @param array<string, mixed> $actorUser
     */
    public function updateHandlerDesignation(
        array $actorUser,
        int $targetUserId,
        bool $active,
        string $notes
    ): bool {
        if (!$this->access->hasCapability($actorUser, 'support.manage_handlers', $this->pdo)) {
            throw new RuntimeException('You are not authorized to manage confidential complaint handlers.', 403);
        }

        $target = $this->handlers->findCandidateUser($targetUserId);
        if (!$target) {
            throw new InvalidArgumentException('Target user account not found.');
        }

        if (!in_array(strtolower((string)$target['role']), ['hr', 'admin'], true)) {
            throw new InvalidArgumentException('Only HR or Administrator accounts may be designated as confidential complaint handlers.');
        }

        if ($active && ($target['status'] ?? '') !== 'Active') {
            throw new InvalidArgumentException('Inactive user accounts cannot be granted active confidential handler clearance.');
        }

        $actorId = (int)$actorUser['id'];
        $ok = $this->handlers->upsertHandlerDesignation($targetUserId, $active, trim($notes), $actorId);

        $this->recordAudit(
            $actorId,
            $actorUser,
            $active ? 'SUPPORT_HANDLER_DESIGNATED' : 'SUPPORT_HANDLER_REVOKED',
            sprintf(
                '%s confidential complaint handler clearance for user #%d (%s).',
                $active ? 'Granted' : 'Revoked',
                $targetUserId,
                $target['username']
            ),
            $targetUserId,
            null,
            ['target_user_id' => $targetUserId, 'is_active' => $active]
        );

        return $ok;
    }

    /**
     * Extract non-empty category-specific supplementary metadata cleanly.
     *
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function extractCategoryMetadata(string $category, array $input): array
    {
        $fieldsByCategory = [
            'Technical Issue'             => ['affected_module', 'browser_device'],
            'Leave Concern'               => ['leave_ref', 'leave_period'],
            'Employee Profile Correction' => ['profile_field', 'correction_ref'],
            'Policy Clarification'        => ['policy_topic'],
        ];

        $allowedKeys = $fieldsByCategory[$category] ?? [];
        $result = [];
        foreach ($allowedKeys as $key) {
            $val = trim((string)($input[$key] ?? ''));
            if ($val !== '') {
                $result[$key] = mb_substr($val, 0, 150);
            }
        }

        return $result;
    }

    /**
     * Record privacy-safe AuditService entry cleanly without breaking the primary workflow.
     *
     * @param array<string, mixed>      $actorUser
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    private function recordAudit(
        int $userId,
        array $actorUser,
        string $action,
        string $description,
        ?int $entityId,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $status = 'success'
    ): void {
        try {
            $this->audit->record([
                'user_id'     => $userId,
                'actor_role'  => (string)($actorUser['role'] ?? 'employee'),
                'action'      => $action,
                'category'    => AuditService::CATEGORY_SUPPORT,
                'description' => $description,
                'entity_type' => 'support_tickets',
                'entity_id'   => $entityId,
                'old_values'  => $oldValues,
                'new_values'  => $newValues,
                'status'      => $status,
                'ip_address'  => function_exists('client_ip') ? client_ip() : ($_SERVER['REMOTE_ADDR'] ?? null),
                'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (\Throwable $e) {
            error_log('Support audit recording skipped: ' . $e->getMessage());
        }
    }
}
