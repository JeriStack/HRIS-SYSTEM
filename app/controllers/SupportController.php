<?php

/**
 * SupportController
 * Employee-facing Support Center, General Support Request, Confidential Workplace Complaint,
 * Ticket Detail Thread, Reply/Reopen/Close actions, and Authorized Attachment Downloads.
 */

namespace App\Controllers;

use App\Models\Employee;
use App\Models\SupportTicket;
use App\Models\SupportTicketHandler;
use App\Services\SupportAccessService;
use App\Services\SupportAttachmentService;
use App\Services\SupportWorkflowService;
use Core\Controller;
use Core\Database;
use Core\Session;
use InvalidArgumentException;
use PDO;
use RuntimeException;

class SupportController extends Controller
{
    private PDO $pdo;
    private SupportTicket $tickets;
    private SupportTicketHandler $handlers;
    private SupportAccessService $access;
    private SupportAttachmentService $attachments;
    private SupportWorkflowService $workflow;
    private Employee $employees;
    private bool $terminateOnRedirect;

    /** @var array{url: string, status: int}|null */
    private ?array $lastRedirect = null;

    public function __construct(
        ?PDO $pdo = null,
        ?SupportWorkflowService $workflow = null,
        ?SupportAttachmentService $attachments = null,
        bool $terminateOnRedirect = true
    ) {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
        $this->tickets = new SupportTicket($this->pdo);
        $this->handlers = new SupportTicketHandler($this->pdo);
        $this->access = new SupportAccessService();
        $this->attachments = $attachments ?? new SupportAttachmentService();
        $this->workflow = $workflow ?? new SupportWorkflowService(
            $this->pdo,
            $this->tickets,
            $this->handlers,
            $this->access,
            $this->attachments
        );
        $this->employees = new Employee($this->pdo);
        $this->terminateOnRedirect = $terminateOnRedirect;
    }

    /**
     * Expose last navigation target when redirect termination is disabled in tests.
     *
     * @return array{url: string, status: int}|null
     */
    public function getLastRedirect(): ?array
    {
        return $this->lastRedirect;
    }

    /**
     * Employee Support Center hub: actions, support guidelines, filters, and My Support Requests.
     * Route: GET /support
     */
    public function index(): void
    {
        $user = currentUser();
        if (!$user || !$this->access->hasCapability($user, 'support.view_own', $this->pdo)) {
            $this->forbid('You do not have permission to view Help & Support requests.');
            return;
        }

        $empId = isset($user['employee_id']) && $user['employee_id'] !== null
            ? (int)$user['employee_id']
            : 0;

        $filters = [
            'search'       => trim((string)($_GET['search'] ?? '')),
            'category'     => trim((string)($_GET['category'] ?? '')),
            'status'       => trim((string)($_GET['status'] ?? '')),
            'request_type' => trim((string)($_GET['request_type'] ?? '')),
        ];

        $ticketRows = $empId > 0 ? $this->tickets->findForEmployeeList($empId, $filters) : [];
        foreach ($ticketRows as &$row) {
            $row['assigned_handler_display'] = $this->access->formatAssignedHandlerForEmployee($row);
        }
        unset($row);

        $openCount = $empId > 0 ? $this->tickets->countOpenByEmployee($empId) : 0;

        $this->view('support.index', [
            'pageTitle'               => 'Help & Support Center',
            'tickets'                 => $ticketRows,
            'openCount'               => $openCount,
            'hasLinkedEmployee'       => $empId > 0,
            'filters'                 => $filters,
            'categories'              => (array)config('support.support_categories', []),
            'complaintCategories'     => (array)config('support.complaint_categories', []),
            'statuses'                => (array)config('support.statuses', []),
            'reopenWindowDays'        => (int)config('support.reopen_window_days', 14),
            'escalationPolicySummary' => (string)config('support.escalation_policy_summary', ''),
        ], 'hr');
    }

    /**
     * Render General Support Request Submission form.
     * Route: GET /support/request
     */
    public function createRequestForm(): void
    {
        $user = currentUser();
        if (!$user || !$this->access->hasCapability($user, 'support.create', $this->pdo)) {
            $this->forbid('You do not have permission to submit support requests.');
            return;
        }

        $empId = isset($user['employee_id']) && $user['employee_id'] !== null
            ? (int)$user['employee_id']
            : 0;

        $old = Session::getFlash('support_old_input');
        if (!is_array($old)) {
            $old = [
                'category' => trim((string)($_GET['category'] ?? 'General Inquiry')),
            ];
        }

        $this->view('support.create_request', [
            'pageTitle'         => 'Submit Support Request',
            'hasLinkedEmployee' => $empId > 0,
            'categories'        => (array)config('support.support_categories', []),
            'priorities'        => (array)config('support.priorities', []),
            'maxBytes'          => $this->attachments->getMaxBytes(),
            'maxFiles'          => $this->attachments->getMaxFilesPerSubmission(),
            'old'               => $old,
        ], 'hr');
    }

    /**
     * Validate & persist a new support request, redirect to detail view with ticket_ref.
     * Route: POST /support/request
     */
    public function storeRequest(): void
    {
        $user = currentUser();
        if (!$user || !$this->access->hasCapability($user, 'support.create', $this->pdo)) {
            $this->forbid('You do not have permission to submit support requests.');
            return;
        }

        try {
            $created = $this->workflow->createSubmission($user, $_POST, $_FILES['attachments'] ?? null);
            Session::flash('success', "Support request {$created['ticket_ref']} submitted. You can track all updates below.");
            $this->navigate('/support/' . (int)$created['id']);
        } catch (InvalidArgumentException | RuntimeException $e) {
            if ((int)$e->getCode() === 403) {
                $this->forbid($e->getMessage());
                return;
            }
            Session::flash('error', $e->getMessage());
            Session::flash('support_old_input', [
                'category'        => (string)($_POST['category'] ?? ''),
                'priority'        => (string)($_POST['priority'] ?? ''),
                'subject'         => (string)($_POST['subject'] ?? ''),
                'description'     => (string)($_POST['description'] ?? ''),
                'affected_module' => (string)($_POST['affected_module'] ?? ''),
                'browser_device'  => (string)($_POST['browser_device'] ?? ''),
                'leave_ref'       => (string)($_POST['leave_ref'] ?? ''),
                'leave_period'    => (string)($_POST['leave_period'] ?? ''),
                'profile_field'   => (string)($_POST['profile_field'] ?? ''),
                'correction_ref'  => (string)($_POST['correction_ref'] ?? ''),
                'policy_topic'    => (string)($_POST['policy_topic'] ?? ''),
            ]);
            $this->navigate('/support/request');
        }
    }

    /**
     * Render Confidential Workplace Complaint Submission workflow.
     * Route: GET /support/complaint
     */
    public function createComplaintForm(): void
    {
        $user = currentUser();
        if (!$user || !$this->access->hasCapability($user, 'support.create', $this->pdo)) {
            $this->forbid('You do not have permission to submit a workplace complaint.');
            return;
        }

        $empId = isset($user['employee_id']) && $user['employee_id'] !== null
            ? (int)$user['employee_id']
            : 0;

        $allEmployees = $this->employees->getAll(null, 'Active');
        $subjectCandidates = array_values(array_filter(
            $allEmployees,
            static fn (array $row): bool => (int)($row['id'] ?? 0) !== $empId
        ));

        $old = Session::getFlash('complaint_old_input');
        if (!is_array($old)) {
            $old = [];
        }

        $this->view('support.create_complaint', [
            'pageTitle'               => 'Submit Confidential Workplace Complaint',
            'hasLinkedEmployee'       => $empId > 0,
            'complaintCategories'     => (array)config('support.complaint_categories', []),
            'priorities'              => (array)config('support.priorities', ['Medium', 'High', 'Urgent']),
            'subjectCandidates'       => $subjectCandidates,
            'escalationPolicySummary' => (string)config('support.escalation_policy_summary', ''),
            'maxBytes'                => $this->attachments->getMaxBytes(),
            'maxFiles'                => $this->attachments->getMaxFilesPerSubmission(),
            'old'                     => $old,
        ], 'hr');
    }

    /**
     * Validate & persist a confidential workplace complaint.
     * Route: POST /support/complaint
     */
    public function storeComplaint(): void
    {
        $user = currentUser();
        if (!$user || !$this->access->hasCapability($user, 'support.create', $this->pdo)) {
            $this->forbid('You do not have permission to submit a workplace complaint.');
            return;
        }

        $input = $_POST;
        $input['request_type'] = 'complaint';
        $input['category'] = 'Workplace Complaint';

        try {
            $created = $this->workflow->createSubmission($user, $input, $_FILES['attachments'] ?? null);
            Session::flash(
                'success',
                "Confidential complaint {$created['ticket_ref']} logged under restricted access controls."
            );
            $this->navigate('/support/' . (int)$created['id']);
        } catch (InvalidArgumentException | RuntimeException $e) {
            if ((int)$e->getCode() === 403) {
                $this->forbid($e->getMessage());
                return;
            }
            Session::flash('error', $e->getMessage());
            Session::flash('complaint_old_input', [
                'complaint_category'  => (string)($_POST['complaint_category'] ?? ''),
                'priority'            => (string)($_POST['priority'] ?? 'High'),
                'incident_date'       => (string)($_POST['incident_date'] ?? ''),
                'subject_employee_id' => (string)($_POST['subject_employee_id'] ?? ''),
                'subject_reference'   => (string)($_POST['subject_reference'] ?? ''),
                'subject'             => (string)($_POST['subject'] ?? ''),
                'description'         => (string)($_POST['description'] ?? ''),
            ]);
            $this->navigate('/support/complaint');
        }
    }

    /**
     * Display employee-owned ticket details, non-internal response thread, and actions.
     * Route: GET /support/{id}
     */
    public function show(int|string $id): void
    {
        $ticketId = (int)$id;
        $user = currentUser();
        $ticket = $ticketId > 0 ? $this->tickets->findById($ticketId) : null;

        if (!$ticket) {
            $this->notFound('Support request not found.');
            return;
        }

        if (!$user || !$this->access->canViewTicketAsEmployee($user, $ticket, $this->pdo)) {
            $this->access->recordSensitiveAccess(
                $this->tickets,
                $ticketId,
                null,
                $user ? (int)($user['id'] ?? 0) : null,
                $this->access->isConfidentialTicket($ticket) ? 'view_complaint' : 'view_ticket',
                false,
                'employee_ownership_denied'
            );
            $this->forbid('You are not authorized to view this support request.');
            return;
        }

        // Strict employee boundary: internal notes and internal-only attachments are NEVER loaded
        $messages = $this->tickets->getMessages($ticketId, false);
        $attachments = $this->tickets->getAttachments($ticketId, false);
        $statusHistory = $this->tickets->getStatusHistory($ticketId);

        $this->view('support.show', [
            'pageTitle'               => $ticket['ticket_ref'] . ' - Support Request Details',
            'ticket'                  => $ticket,
            'isConfidential'          => $this->access->isConfidentialTicket($ticket),
            'assignedHandlerDisplay'  => $this->access->formatAssignedHandlerForEmployee($ticket),
            'messages'                => $messages,
            'attachments'             => $attachments,
            'statusHistory'           => $statusHistory,
            'canReply'                => $this->access->canEmployeeReply($ticket) && $this->access->hasCapability($user, 'support.reply_own', $this->pdo),
            'canReopen'               => $this->access->canEmployeeReopen($ticket) && $this->access->hasCapability($user, 'support.reply_own', $this->pdo),
            'canConfirmClose'         => $this->access->canEmployeeConfirmClose($ticket) && $this->access->hasCapability($user, 'support.reply_own', $this->pdo),
            'reopenWindowDays'        => (int)config('support.reopen_window_days', 14),
            'maxBytes'                => $this->attachments->getMaxBytes(),
            'maxFiles'                => $this->attachments->getMaxFilesPerSubmission(),
            'escalationPolicySummary' => (string)config('support.escalation_policy_summary', ''),
        ], 'hr');
    }

    /**
     * Post an employee reply (+ optional additional evidence documents).
     * Route: POST /support/{id}/reply
     */
    public function reply(int|string $id): void
    {
        $ticketId = (int)$id;
        $user = currentUser();
        if (!$user) {
            $this->forbid('Authentication required.');
            return;
        }

        try {
            $updated = $this->workflow->employeeReply(
                $user,
                $ticketId,
                (string)($_POST['body'] ?? ''),
                $_FILES['attachments'] ?? null
            );
            Session::flash('success', "Your reply has been added to {$updated['ticket_ref']}.");
            $this->navigate('/support/' . $ticketId);
        } catch (InvalidArgumentException | RuntimeException $e) {
            if ((int)$e->getCode() === 404) {
                $this->notFound($e->getMessage());
                return;
            }
            if ((int)$e->getCode() === 403) {
                $this->forbid($e->getMessage());
                return;
            }
            Session::flash('error', $e->getMessage());
            $this->navigate('/support/' . $ticketId);
        }
    }

    /**
     * Reopen a Resolved support request within the configured reopen window.
     * Route: POST /support/{id}/reopen
     */
    public function reopen(int|string $id): void
    {
        $ticketId = (int)$id;
        $user = currentUser();
        if (!$user) {
            $this->forbid('Authentication required.');
            return;
        }

        try {
            $updated = $this->workflow->employeeReopen($user, $ticketId, (string)($_POST['reason'] ?? ''));
            Session::flash('success', "Request {$updated['ticket_ref']} has been reopened for HR review.");
            $this->navigate('/support/' . $ticketId);
        } catch (InvalidArgumentException | RuntimeException $e) {
            if ((int)$e->getCode() === 404) {
                $this->notFound($e->getMessage());
                return;
            }
            if ((int)$e->getCode() === 403) {
                $this->forbid($e->getMessage());
                return;
            }
            Session::flash('error', $e->getMessage());
            $this->navigate('/support/' . $ticketId);
        }
    }

    /**
     * Confirm final closure ONLY after HR has marked the ticket Resolved.
     * Route: POST /support/{id}/close
     */
    public function confirmClose(int|string $id): void
    {
        $ticketId = (int)$id;
        $user = currentUser();
        if (!$user) {
            $this->forbid('Authentication required.');
            return;
        }

        try {
            $updated = $this->workflow->employeeConfirmClose($user, $ticketId, $_POST['remarks'] ?? null);
            Session::flash('success', "Request {$updated['ticket_ref']} is now Closed.");
            $this->navigate('/support/' . $ticketId);
        } catch (InvalidArgumentException | RuntimeException $e) {
            if ((int)$e->getCode() === 404) {
                $this->notFound($e->getMessage());
                return;
            }
            if ((int)$e->getCode() === 403) {
                $this->forbid($e->getMessage());
                return;
            }
            Session::flash('error', $e->getMessage());
            $this->navigate('/support/' . $ticketId);
        }
    }

    /**
     * Serve a non-public support attachment exclusively to authorized ticket owners or permitted handlers.
     * Route: GET /support/attachments/{id}
     */
    public function downloadAttachment(int|string $id): void
    {
        $attachmentId = (int)$id;
        $user = currentUser();
        $attachment = $attachmentId > 0 ? $this->tickets->findAttachmentWithTicket($attachmentId) : null;

        if (!$attachment) {
            $this->notFound('Requested attachment could not be found.');
            return;
        }

        $ticketId = (int)$attachment['ticket_id'];
        $ticketScope = [
            'id'                  => $ticketId,
            'employee_id'         => $attachment['ticket_employee_id'] ?? null,
            'is_confidential'     => $attachment['ticket_is_confidential'] ?? 0,
            'subject_employee_id' => $attachment['ticket_subject_employee_id'] ?? null,
            'escalated_flag'      => $attachment['ticket_escalated_flag'] ?? 0,
            'request_type'        => $attachment['ticket_request_type'] ?? 'support',
        ];

        if (!$user || !$this->access->canDownloadAttachment($user, $attachment, $this->handlers, $this->pdo, 'auto')) {
            $reason = $this->access->isConflicted($user, $ticketScope) ? 'coi_blocked' : 'unauthorized_attachment';
            $actionType = $reason === 'coi_blocked' ? 'coi_blocked' : 'download_attachment';
            $this->access->recordSensitiveAccess(
                $this->tickets,
                $ticketId,
                $attachmentId,
                $user ? (int)($user['id'] ?? 0) : null,
                $actionType,
                false,
                $reason
            );
            $this->forbid('You are not authorized to download this support attachment.');
            return;
        }

        $filePath = $this->attachments->resolveAuthorizedFilePath((string)($attachment['storage_relative_path'] ?? ''));
        if ($filePath === null) {
            $this->notFound('Attachment file is unavailable in secure storage.');
            return;
        }

        if (!empty($attachment['ticket_is_confidential'])) {
            $this->access->recordSensitiveAccess(
                $this->tickets,
                $ticketId,
                $attachmentId,
                (int)$user['id'],
                'download_attachment',
                true,
                'authorized_download'
            );
        }

        $safeDownloadName = str_replace(['"', "\r", "\n"], '', (string)($attachment['original_filename'] ?? 'attachment'));
        $mimeType = (string)($attachment['mime_type'] ?? 'application/octet-stream');

        if (!headers_sent()) {
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . (string)filesize($filePath));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Content-Disposition: attachment; filename="' . $safeDownloadName . '"');
        }

        readfile($filePath);
    }

    private function forbid(string $message): void
    {
        if (!headers_sent()) {
            http_response_code(403);
        }

        if (request_wants_json()) {
            $this->json(['status' => 'error', 'message' => $message], 403);
            return;
        }

        $this->view('errors.403', [
            'pageTitle' => '403 Forbidden - Access Restricted',
            'message'   => $message,
        ], null);
    }

    private function notFound(string $message): void
    {
        if (!headers_sent()) {
            http_response_code(404);
        }

        if (request_wants_json()) {
            $this->json(['status' => 'error', 'message' => $message], 404);
            return;
        }

        $this->view('errors.404', [
            'pageTitle' => '404 Not Found',
            'message'   => $message,
        ], null);
    }

    private function navigate(string $path, int $statusCode = 302): void
    {
        $this->lastRedirect = ['url' => $path, 'status' => $statusCode];
        if ($this->terminateOnRedirect) {
            $this->redirect($path, $statusCode);
        }
    }
}
