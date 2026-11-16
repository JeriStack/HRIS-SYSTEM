<?php

/**
 * HrAdminSupportController
 * HR-facing Help & Support Center for reporting technical issues, system errors,
 * access problems, and operational concerns directly to System Administrators.
 * Enforces strict HR ownership and hides Admin-only internal notes at all times.
 */

namespace App\Controllers;

use Core\Session;
use InvalidArgumentException;
use RuntimeException;

class HrAdminSupportController extends HrSupportBaseController
{
    /**
     * HR Help & Support Center ("My Support Tickets" + filters).
     * Route: GET /hr/help
     */
    public function index(): void
    {
        $user = currentUser();
        if (
            !$user
            || strtolower((string)($user['role'] ?? '')) !== 'hr'
            || !$this->access->hasCapability($user, 'hr_support.view_own', $this->pdo)
        ) {
            $this->forbid('Only authorized HR personnel may access the HR Help & Support Center.');
            return;
        }

        $filters = [
            'search'    => trim((string)($_GET['search'] ?? '')),
            'status'    => trim((string)($_GET['status'] ?? '')),
            'category'  => trim((string)($_GET['category'] ?? '')),
            'date_from' => trim((string)($_GET['date_from'] ?? '')),
            'date_to'   => trim((string)($_GET['date_to'] ?? '')),
        ];

        $userId = (int)$user['id'];
        $tickets = $this->tickets->findForHrUser($userId, $filters);
        $stats = $this->tickets->getHrUserSummaryStats($userId);

        if (request_wants_json()) {
            $this->json([
                'status'  => 'success',
                'stats'   => $stats,
                'tickets' => $tickets,
            ]);
            return;
        }

        $this->view('hr.admin_support.index', [
            'pageTitle'        => 'Help & Support (Admin) - HR Portal',
            'tickets'          => $tickets,
            'stats'            => $stats,
            'filters'          => $filters,
            'categories'       => (array)config('hr_support.categories', []),
            'statuses'         => (array)config('hr_support.statuses', []),
            'canSubmit'        => $this->access->hasCapability($user, 'hr_support.submit', $this->pdo),
            'reopenWindowDays' => (int)config('hr_support.reopen_window_days', 14),
        ], 'hr');
    }

    /**
     * Render structured HR-to-Admin ticket submission form.
     * Route: GET /hr/help/create
     */
    public function create(): void
    {
        $user = currentUser();
        if (
            !$user
            || strtolower((string)($user['role'] ?? '')) !== 'hr'
            || !$this->access->hasCapability($user, 'hr_support.submit', $this->pdo)
        ) {
            $this->forbid('Only authorized HR personnel may submit support tickets to Administrators.');
            return;
        }

        $old = Session::getFlash('old_input', []);
        if (!is_array($old)) {
            $old = [];
        }

        $this->view('hr.admin_support.create', [
            'pageTitle'  => 'Submit Support Ticket to Admin - HR Portal',
            'categories' => (array)config('hr_support.categories', []),
            'priorities' => (array)config('hr_support.priorities', ['Low', 'Medium', 'High', 'Critical']),
            'maxFiles'   => (int)config('hr_support.max_attachments_per_submission', 3),
            'maxBytes'   => (int)config('hr_support.attachment_max_bytes', 5 * 1024 * 1024),
            'old'        => $old,
        ], 'hr');
    }

    /**
     * Persist new HR-to-Admin support ticket and notify Administrators.
     * Route: POST /hr/help/tickets
     */
    public function store(): void
    {
        $user = currentUser();
        if (!$user) {
            $this->forbid('Authentication required.');
            return;
        }

        try {
            $created = $this->workflow->createTicket(
                $user,
                $_POST,
                $_FILES['attachments'] ?? null
            );

            if (request_wants_json()) {
                $this->json([
                    'status'     => 'success',
                    'ticket_ref' => $created['ticket_ref'],
                    'ticket'     => $created,
                ], 201);
                return;
            }

            Session::flash(
                'success',
                "Support ticket {$created['ticket_ref']} has been submitted to Administrators for investigation."
            );
            $this->navigate('/hr/help/tickets/' . rawurlencode((string)$created['ticket_ref']));
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->handleWorkflowFailure($e, '/hr/help/create', true);
        }
    }

    /**
     * Open HR-visible ticket conversation and lifecycle history.
     * Internal Admin notes are strictly excluded at service & SQL layer.
     * Route: GET /hr/help/tickets/{ref}
     */
    public function show(string $ref): void
    {
        $user = currentUser();
        if (!$user) {
            $this->forbid('Authentication required.');
            return;
        }

        try {
            $bundle = $this->workflow->getTicketDetailsForHr($user, $ref);
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->handleWorkflowFailure($e, '/hr/help');
            return;
        }

        if (request_wants_json()) {
            $this->json(['status' => 'success'] + $bundle);
            return;
        }

        $ticket = $bundle['ticket'];
        $canInteract = $this->access->hasCapability($user, 'hr_support.reply_own', $this->pdo);

        $this->view('hr.admin_support.show', [
            'pageTitle'            => $ticket['ticket_ref'] . ' - HR Help & Support',
            'ticket'               => $ticket,
            'messages'             => $bundle['messages'],
            'attachments'          => $bundle['attachments'],
            'attachmentsByMessage' => $bundle['attachments_by_message'],
            'history'              => $bundle['history'],
            'canReply'             => $canInteract && $bundle['can_reply'],
            'canConfirmResolution' => $canInteract && $bundle['can_confirm_resolution'],
            'canReopen'            => $canInteract && $bundle['can_reopen'],
            'reopenWindowDays'     => $bundle['reopen_window_days'],
            'reopenPolicySummary'  => sprintf(
                (string)config('hr_support.reopen_policy_summary', ''),
                (int)$bundle['reopen_window_days']
            ),
            'maxFiles'             => (int)config('hr_support.max_attachments_per_submission', 3),
            'maxBytes'             => (int)config('hr_support.attachment_max_bytes', 5 * 1024 * 1024),
        ], 'hr');
    }

    /**
     * Send additional follow-up information and optional attachments on own ticket.
     * Route: POST /hr/help/tickets/{ref}/reply
     */
    public function reply(string $ref): void
    {
        $user = currentUser();
        if (!$user) {
            $this->forbid('Authentication required.');
            return;
        }

        try {
            $updated = $this->workflow->addHrReply(
                $user,
                $ref,
                (string)($_POST['body'] ?? ''),
                $_FILES['attachments'] ?? null
            );

            if (request_wants_json()) {
                $this->json(['status' => 'success', 'ticket' => $updated]);
                return;
            }

            Session::flash('success', "Your reply on {$updated['ticket_ref']} has been sent to Administrators.");
            $this->navigate('/hr/help/tickets/' . rawurlencode((string)$updated['ticket_ref']));
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->handleWorkflowFailure($e, '/hr/help/tickets/' . rawurlencode($ref));
        }
    }

    /**
     * Confirm resolution on a Resolved ticket, transitioning it to Closed.
     * Route: POST /hr/help/tickets/{ref}/confirm-resolution
     */
    public function confirmResolution(string $ref): void
    {
        $user = currentUser();
        if (!$user) {
            $this->forbid('Authentication required.');
            return;
        }

        try {
            $updated = $this->workflow->confirmHrResolution(
                $user,
                $ref,
                (string)($_POST['note'] ?? '')
            );

            if (request_wants_json()) {
                $this->json(['status' => 'success', 'ticket' => $updated]);
                return;
            }

            Session::flash('success', "Resolution confirmed. Ticket {$updated['ticket_ref']} is now Closed.");
            $this->navigate('/hr/help/tickets/' . rawurlencode((string)$updated['ticket_ref']));
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->handleWorkflowFailure($e, '/hr/help/tickets/' . rawurlencode($ref));
        }
    }

    /**
     * Request reopening of an unresolved Resolved ticket within policy limits.
     * Route: POST /hr/help/tickets/{ref}/reopen
     */
    public function reopen(string $ref): void
    {
        $user = currentUser();
        if (!$user) {
            $this->forbid('Authentication required.');
            return;
        }

        try {
            $updated = $this->workflow->requestHrReopen(
                $user,
                $ref,
                (string)($_POST['reason'] ?? '')
            );

            if (request_wants_json()) {
                $this->json(['status' => 'success', 'ticket' => $updated]);
                return;
            }

            Session::flash('success', "Ticket {$updated['ticket_ref']} has been reopened for Administrator follow-up.");
            $this->navigate('/hr/help/tickets/' . rawurlencode((string)$updated['ticket_ref']));
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->handleWorkflowFailure($e, '/hr/help/tickets/' . rawurlencode($ref));
        }
    }

    /**
     * Authorized download for non-internal attachments on own HR support tickets.
     * Route: GET /hr/help/attachments/{id}
     */
    public function downloadAttachment(int|string $id): void
    {
        $this->streamAttachmentDownload(currentUser(), (int)$id, 'hr');
    }
}
