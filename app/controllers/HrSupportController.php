<?php

/**
 * HrSupportController
 * HR Support Inbox, Confidential Complaint Review (strictly permission & handler-scoped),
 * Handler Assignment/Reassignment, Internal HR Notes, Status & Priority Management, and Handler Governance.
 */

namespace App\Controllers;

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

class HrSupportController extends Controller
{
    private PDO $pdo;
    private SupportTicket $tickets;
    private SupportTicketHandler $handlers;
    private SupportAccessService $access;
    private SupportAttachmentService $attachments;
    private SupportWorkflowService $workflow;
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
     * Render HR Support Inbox with confidentiality-scoped KPI summary and filters.
     * Route: GET /hr/support
     */
    public function index(): void
    {
        $user = currentUser();
        $role = strtolower((string)($user['role'] ?? ''));
        $canViewInbox = $role === 'hr'
            ? $this->access->hasCapability($user, 'support.inbox_view', $this->pdo)
            : ($role === 'admin' && $this->access->hasCapability($user, 'support.manage_handlers', $this->pdo));
        if (!$user || !$canViewInbox) {
            $this->forbid('You do not have permission to access the HR Support Inbox.');
            return;
        }

        $viewerContext = $this->access->buildViewerContext($user, $this->handlers, $this->pdo);
        $filters = [
            'search'   => trim((string)($_GET['search'] ?? '')),
            'category' => trim((string)($_GET['category'] ?? '')),
            'status'   => trim((string)($_GET['status'] ?? '')),
            'priority' => trim((string)($_GET['priority'] ?? '')),
            'scope'    => trim((string)($_GET['scope'] ?? '')),
        ];

        $stats = $this->tickets->getHrOverviewStats($viewerContext);
        $inboxTickets = $this->tickets->findForHrInbox($viewerContext, $filters);

        $this->view('hr.support.index', [
            'pageTitle'             => 'HR Support Inbox',
            'stats'                 => $stats,
            'tickets'               => $inboxTickets,
            'filters'               => $filters,
            'canHandleConfidential' => !empty($viewerContext['can_handle_confidential']),
            'canManageHandlers'     => $this->access->hasCapability($user, 'support.manage_handlers', $this->pdo),
            'categories'            => array_merge(
                (array)config('support.support_categories', []),
                (array)config('support.public_auth_categories', [])
            ),
            'statuses'              => (array)config('support.statuses', []),
            'priorities'            => (array)config('support.priorities', []),
        ], 'hr');
    }

    /**
     * Display HR/Admin Support Case Management view with internal notes, history, and COI guards.
     * Route: GET /hr/support/{id}
     */
    public function show(int|string $id): void
    {
        $ticketId = (int)$id;
        $user = currentUser();
        $ticket = $ticketId > 0 ? $this->tickets->findById($ticketId) : null;

        if (!$ticket) {
            $this->notFound('Support ticket not found.');
            return;
        }

        if (!$user || !$this->access->canViewTicketAsHr($user, $ticket, $this->handlers, $this->pdo)) {
            $isConflicted = $this->access->isConflicted($user, $ticket);
            $reason = $isConflicted ? 'coi_blocked' : 'unauthorized_handler';
            $actionType = $isConflicted ? 'coi_blocked' : ($this->access->isConfidentialTicket($ticket) ? 'view_complaint' : 'view_ticket');
            $this->access->recordSensitiveAccess(
                $this->tickets,
                $ticketId,
                null,
                $user ? (int)($user['id'] ?? 0) : null,
                $actionType,
                false,
                $reason
            );
            $this->forbid('Access denied: you do not have clearance or conflict-of-interest eligibility for this case.');
            return;
        }

        $isConfidential = $this->access->isConfidentialTicket($ticket);
        if ($isConfidential) {
            $this->access->recordSensitiveAccess(
                $this->tickets,
                $ticketId,
                null,
                (int)$user['id'],
                'view_complaint',
                true,
                'hr_inbox_detail'
            );
        }

        $messages = $this->tickets->getMessages($ticketId, true);
        $attachments = $this->tickets->getAttachments($ticketId, true);
        $statusHistory = $this->tickets->getStatusHistory($ticketId);
        $assignments = $this->tickets->getAssignments($ticketId);

        $subjectEmpId = isset($ticket['subject_employee_id']) && $ticket['subject_employee_id'] !== null
            ? (int)$ticket['subject_employee_id']
            : null;

        $eligibleHandlers = $isConfidential
            ? $this->handlers->getEligibleConfidentialHandlers($subjectEmpId, empty($ticket['escalated_flag']))
            : $this->handlers->getAssignableStandardHandlers($subjectEmpId);

        $transitionsMap = (array)config('support.hr_transitions', []);
        $allowedNextStatuses = (array)($transitionsMap[(string)$ticket['status']] ?? []);

        $this->view('hr.support.show', [
            'pageTitle'               => $ticket['ticket_ref'] . ' - HR Case Triage',
            'ticket'                  => $ticket,
            'isConfidential'          => $isConfidential,
            'messages'                => $messages,
            'attachments'             => $attachments,
            'statusHistory'           => $statusHistory,
            'assignments'             => $assignments,
            'eligibleHandlers'        => $eligibleHandlers,
            'allowedNextStatuses'     => $allowedNextStatuses,
            'priorities'              => (array)config('support.priorities', []),
            'canRespond'              => !empty($ticket['escalated_flag']) && strtolower((string)($user['role'] ?? '')) === 'admin'
                ? $this->access->hasCapability($user, 'support.manage_handlers', $this->pdo)
                : $this->access->hasCapability($user, 'support.respond', $this->pdo),
            'canAssign'               => !empty($ticket['escalated_flag']) && strtolower((string)($user['role'] ?? '')) === 'admin'
                ? $this->access->hasCapability($user, 'support.manage_handlers', $this->pdo)
                : $this->access->hasCapability($user, 'support.assign', $this->pdo),
            'canManageStatus'         => !empty($ticket['escalated_flag']) && strtolower((string)($user['role'] ?? '')) === 'admin'
                ? $this->access->hasCapability($user, 'support.manage_handlers', $this->pdo)
                : $this->access->hasCapability($user, 'support.manage_status', $this->pdo),
            'maxBytes'                => $this->attachments->getMaxBytes(),
            'maxFiles'                => $this->attachments->getMaxFilesPerSubmission(),
            'escalationPolicySummary' => (string)config('support.escalation_policy_summary', ''),
        ], 'hr');
    }

    /**
     * Post an HR response, info request, or strictly internal HR note.
     * Route: POST /hr/support/{id}/respond
     */
    public function respond(int|string $id): void
    {
        $ticketId = (int)$id;
        $user = currentUser();
        if (!$user) {
            $this->forbid('Authentication required.');
            return;
        }

        try {
            $updated = $this->workflow->hrRespond($user, $ticketId, $_POST, $_FILES['attachments'] ?? null);
            $mode = (string)($_POST['response_mode'] ?? 'public_reply');
            $msg = match ($mode) {
                'internal_note' => "Internal HR note saved on {$updated['ticket_ref']} (not visible to employee).",
                'request_info'  => "Additional information request sent on {$updated['ticket_ref']}.",
                default         => "Response posted to {$updated['ticket_ref']}.",
            };
            Session::flash('success', $msg);
            $this->navigate('/hr/support/' . $ticketId);
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
            $this->navigate('/hr/support/' . $ticketId);
        }
    }

    /**
     * Assign or reassign a ticket handler with COI and confidential clearance verification.
     * Route: POST /hr/support/{id}/assign
     */
    public function assign(int|string $id): void
    {
        $ticketId = (int)$id;
        $user = currentUser();
        if (!$user) {
            $this->forbid('Authentication required.');
            return;
        }

        try {
            $targetUserId = !empty($_POST['assigned_to_user_id']) ? (int)$_POST['assigned_to_user_id'] : null;
            $updated = $this->workflow->assignHandler($user, $ticketId, $targetUserId, $_POST['notes'] ?? null);
            Session::flash('success', "Assignment updated for {$updated['ticket_ref']}.");
            $this->navigate('/hr/support/' . $ticketId);
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
            $this->navigate('/hr/support/' . $ticketId);
        }
    }

    /**
     * Update ticket workflow status, priority, and resolution remarks.
     * Route: POST /hr/support/{id}/status
     */
    public function updateStatus(int|string $id): void
    {
        $ticketId = (int)$id;
        $user = currentUser();
        if (!$user) {
            $this->forbid('Authentication required.');
            return;
        }

        try {
            $updated = $this->workflow->updateStatusAndPriority($user, $ticketId, $_POST);
            Session::flash('success', "Status and priority updated for {$updated['ticket_ref']}.");
            $this->navigate('/hr/support/' . $ticketId);
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
            $this->navigate('/hr/support/' . $ticketId);
        }
    }

    /**
     * Render designated confidential complaint handler governance screen.
     * Route: GET /hr/support/handlers
     */
    public function handlers(): void
    {
        $user = currentUser();
        if (!$user || !$this->access->hasCapability($user, 'support.manage_handlers', $this->pdo)) {
            $this->forbid('Only authorized Administrators may manage designated confidential complaint handlers.');
            return;
        }

        $roster = $this->handlers->getHandlerRosterWithStatus();

        $this->view('hr.support.handlers', [
            'pageTitle'               => 'Confidential Complaint Handler Governance',
            'roster'                  => $roster,
            'escalationPolicySummary' => (string)config('support.escalation_policy_summary', ''),
        ], 'hr');
    }

    /**
     * Grant or revoke confidential complaint handler designation for an HR/Admin user.
     * Route: POST /hr/support/handlers
     */
    public function updateHandler(): void
    {
        $user = currentUser();
        if (!$user || !$this->access->hasCapability($user, 'support.manage_handlers', $this->pdo)) {
            $this->forbid('Only authorized Administrators may update confidential complaint handler designations.');
            return;
        }

        try {
            $targetUserId = (int)($_POST['target_user_id'] ?? 0);
            $action = trim((string)($_POST['action'] ?? 'grant'));
            $notes = trim((string)($_POST['designation_notes'] ?? ''));

            if (!in_array($action, ['grant', 'revoke'], true) || $targetUserId <= 0) {
                throw new InvalidArgumentException('Invalid handler governance request.');
            }

            $this->workflow->updateHandlerDesignation($user, $targetUserId, $action === 'grant', $notes);
            Session::flash(
                'success',
                $action === 'grant'
                    ? 'Confidential complaint handler clearance granted.'
                    : 'Confidential complaint handler clearance revoked.'
            );
            $this->navigate('/hr/support/handlers');
        } catch (InvalidArgumentException | RuntimeException $e) {
            if ((int)$e->getCode() === 403) {
                $this->forbid($e->getMessage());
                return;
            }
            Session::flash('error', $e->getMessage());
            $this->navigate('/hr/support/handlers');
        }
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
