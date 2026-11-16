<?php

/**
 * AdminSupportController
 * Dedicated Administrator Support Management Inbox for triaging, assigning,
 * responding to HR support tickets, logging Admin-only internal investigation notes,
 * updating priority/statuses, resolving, closing, and reopening cases.
 */

namespace App\Controllers;

use Core\Session;
use InvalidArgumentException;
use RuntimeException;

class AdminSupportController extends HrSupportBaseController
{
    /**
     * Admin Support Management Inbox with live DB summary metrics and full search/filtering.
     * Route: GET /admin/support
     */
    public function index(): void
    {
        $user = currentUser();
        if (!$this->access->canViewTicketAsAdmin($user, $this->pdo)) {
            $this->forbid('Administrator support inbox authorization is required.');
            return;
        }

        $filters = [
            'search'            => trim((string)($_GET['search'] ?? '')),
            'status'            => trim((string)($_GET['status'] ?? '')),
            'priority'          => trim((string)($_GET['priority'] ?? '')),
            'category'          => trim((string)($_GET['category'] ?? '')),
            'date_from'         => trim((string)($_GET['date_from'] ?? '')),
            'date_to'           => trim((string)($_GET['date_to'] ?? '')),
            'assigned_admin_id' => trim((string)($_GET['assigned_admin_id'] ?? '')),
            'viewer_user_id'    => (int)$user['id'],
        ];

        $stats = $this->tickets->getAdminDashboardStats();
        $tickets = $this->tickets->findForAdminInbox($filters);
        $admins = $this->tickets->getAssignableAdmins();

        if (request_wants_json()) {
            $this->json([
                'status'  => 'success',
                'stats'   => $stats,
                'tickets' => $tickets,
            ]);
            return;
        }

        $this->view('admin.support.index', [
            'pageTitle'  => 'HR Support Management - Admin Portal',
            'stats'      => $stats,
            'tickets'    => $tickets,
            'filters'    => $filters,
            'admins'     => $admins,
            'categories' => (array)config('hr_support.categories', []),
            'priorities' => (array)config('hr_support.priorities', ['Low', 'Medium', 'High', 'Critical']),
            'statuses'   => (array)config('hr_support.statuses', []),
        ], 'hr');
    }

    /**
     * Complete Admin ticket investigation, conversation, internal notes, assignment & triage view.
     * Route: GET /admin/support/tickets/{ref}
     */
    public function show(string $ref): void
    {
        $user = currentUser();
        if (!$user) {
            $this->forbid('Authentication required.');
            return;
        }

        try {
            $bundle = $this->workflow->getTicketDetailsForAdmin($user, $ref);
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->handleWorkflowFailure($e, '/admin/support');
            return;
        }

        if (request_wants_json()) {
            $this->json(['status' => 'success'] + $bundle);
            return;
        }

        $ticket = $bundle['ticket'];

        $this->view('admin.support.show', [
            'pageTitle'            => $ticket['ticket_ref'] . ' - Support Management',
            'ticket'               => $ticket,
            'messages'             => $bundle['messages'],
            'attachments'          => $bundle['attachments'],
            'attachmentsByMessage' => $bundle['attachments_by_message'],
            'history'              => $bundle['history'],
            'assignments'          => $bundle['assignments'],
            'assignableAdmins'     => $bundle['assignable_admins'],
            'canManage'            => $bundle['can_manage'],
            'canInternalNotes'     => $bundle['can_internal_notes'],
            'allowedTransitions'   => $bundle['allowed_transitions'],
            'priorities'           => (array)config('hr_support.priorities', ['Low', 'Medium', 'High', 'Critical']),
            'maxFiles'             => (int)config('hr_support.max_attachments_per_submission', 3),
            'maxBytes'             => (int)config('hr_support.attachment_max_bytes', 5 * 1024 * 1024),
        ], 'hr');
    }

    /**
     * Assign or reassign a ticket to an authorized active Administrator.
     * Route: POST /admin/support/tickets/{ref}/assign
     */
    public function assign(string $ref): void
    {
        $user = currentUser();
        if (!$user) {
            $this->forbid('Authentication required.');
            return;
        }

        $rawAdminId = trim((string)($_POST['assigned_admin_id'] ?? ''));
        $targetAdminId = $rawAdminId !== '' ? (int)$rawAdminId : null;
        $notes = (string)($_POST['notes'] ?? '');

        try {
            $updated = $this->workflow->assignAdmin($user, $ref, $targetAdminId, $notes);
            if (request_wants_json()) {
                $this->json(['status' => 'success', 'ticket' => $updated]);
                return;
            }

            Session::flash('success', "Assignment updated for ticket {$updated['ticket_ref']}.");
            $this->navigate('/admin/support/tickets/' . rawurlencode((string)$updated['ticket_ref']));
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->handleWorkflowFailure($e, '/admin/support/tickets/' . rawurlencode($ref));
        }
    }

    /**
     * Update ticket priority, status, resolution summary, and optional HR-visible transition remark.
     * Route: POST /admin/support/tickets/{ref}/triage
     */
    public function updateTriage(string $ref): void
    {
        $user = currentUser();
        if (!$user) {
            $this->forbid('Authentication required.');
            return;
        }

        try {
            $updated = $this->workflow->updateTicketTriage($user, $ref, $_POST);
            if (request_wants_json()) {
                $this->json(['status' => 'success', 'ticket' => $updated]);
                return;
            }

            Session::flash('success', "Workflow settings updated for {$updated['ticket_ref']} ({$updated['status']}, {$updated['priority']}).");
            $this->navigate('/admin/support/tickets/' . rawurlencode((string)$updated['ticket_ref']));
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->handleWorkflowFailure($e, '/admin/support/tickets/' . rawurlencode($ref));
        }
    }

    /**
     * Post an HR-visible Admin reply, request additional info from HR, or add a private Admin-only internal note.
     * Route: POST /admin/support/tickets/{ref}/respond
     */
    public function respond(string $ref): void
    {
        $user = currentUser();
        if (!$user) {
            $this->forbid('Authentication required.');
            return;
        }

        $mode = (string)($_POST['response_mode'] ?? 'reply');
        $body = (string)($_POST['body'] ?? '');
        $targetStatus = isset($_POST['target_status']) ? (string)$_POST['target_status'] : null;

        try {
            $updated = $this->workflow->addAdminMessage(
                $user,
                $ref,
                $body,
                $mode,
                $_FILES['attachments'] ?? null,
                $targetStatus
            );

            if (request_wants_json()) {
                $this->json(['status' => 'success', 'ticket' => $updated]);
                return;
            }

            $flashMsg = match (strtolower(trim($mode))) {
                'internal_note', 'internal', 'note' => "Private internal note saved on {$updated['ticket_ref']} (hidden from HR).",
                'info_request', 'request_info'      => "Information request sent to HR on {$updated['ticket_ref']}.",
                default                             => "Response posted to {$updated['ticket_ref']} and HR notified.",
            };
            Session::flash('success', $flashMsg);
            $this->navigate('/admin/support/tickets/' . rawurlencode((string)$updated['ticket_ref']));
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->handleWorkflowFailure($e, '/admin/support/tickets/' . rawurlencode($ref));
        }
    }

    /**
     * Authorized download for HR support ticket attachments (including Admin-only internal files).
     * Route: GET /admin/support/attachments/{id}
     */
    public function downloadAttachment(int|string $id): void
    {
        $this->streamAttachmentDownload(currentUser(), (int)$id, 'admin');
    }
}
