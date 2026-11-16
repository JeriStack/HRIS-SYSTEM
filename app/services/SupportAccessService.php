<?php

/**
 * SupportAccessService
 * Enforces ownership, confidential complaint boundaries within HR, Conflict-of-Interest (COI)
 * exclusions, escalation oversight, attachment authorization, and dashboard/audit privacy masking.
 */

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\SupportTicketHandler;
use PDO;

final class SupportAccessService
{
    /** @var callable(array<string, mixed>, string, PDO): bool|null */
    private $permissionChecker;

    /**
     * @param callable(array<string, mixed>, string, PDO): bool|null $permissionChecker
     */
    public function __construct(?callable $permissionChecker = null)
    {
        $this->permissionChecker = $permissionChecker;
    }

    /**
     * Determine whether a ticket is confidential (general confidential flag or workplace complaint).
     *
     * @param array<string, mixed> $ticket
     */
    public function isConfidentialTicket(array $ticket): bool
    {
        return !empty($ticket['is_confidential'])
            || (($ticket['request_type'] ?? '') === 'complaint')
            || (($ticket['category'] ?? '') === 'Workplace Complaint');
    }

    /**
     * Strictly enforce Conflict-of-Interest exclusion when viewer is the subject of a complaint.
     * Applies across ALL roles including HR and Administrators.
     *
     * @param array<string, mixed>|null $viewerUser
     * @param array<string, mixed>      $ticket
     */
    public function isConflicted(?array $viewerUser, array $ticket): bool
    {
        if (!$viewerUser) {
            return false;
        }

        $viewerEmpId = isset($viewerUser['employee_id']) && $viewerUser['employee_id'] !== null
            ? (int)$viewerUser['employee_id']
            : 0;
        $subjectEmpId = isset($ticket['subject_employee_id']) && $ticket['subject_employee_id'] !== null
            ? (int)$ticket['subject_employee_id']
            : 0;

        return $viewerEmpId > 0 && $subjectEmpId > 0 && $viewerEmpId === $subjectEmpId;
    }

    /**
     * Check whether a user holds an RBAC capability either via active session or DB role_permissions.
     *
     * @param array<string, mixed>|null $user
     */
    public function hasCapability(?array $user, string $permissionSlug, PDO $pdo): bool
    {
        if (!$user || ($user['status'] ?? 'Active') !== 'Active') {
            return false;
        }

        if ($this->permissionChecker !== null) {
            return (bool)($this->permissionChecker)($user, $permissionSlug, $pdo);
        }

        $sessionUid = (int)($_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? 0));
        if (
            function_exists('can')
            && $sessionUid > 0
            && $sessionUid === (int)($user['id'] ?? 0)
        ) {
            return can($permissionSlug);
        }

        $role = strtolower((string)($user['role'] ?? ''));
        if ($role === 'admin') {
            return \Core\PermissionRegistry::defaultRoleHas('admin', $permissionSlug);
        }

        $roleId = isset($user['role_id']) && $user['role_id'] !== null ? (int)$user['role_id'] : 0;
        if ($roleId > 0) {
            $stmt = $pdo->prepare(
                "SELECT 1
                 FROM role_permissions rp
                 INNER JOIN permissions p ON rp.permission_id = p.id
                 WHERE rp.role_id = :role_id AND p.slug = :slug
                 LIMIT 1"
            );
            $stmt->execute([
                'role_id' => $roleId,
                'slug'    => $permissionSlug,
            ]);
            if ($stmt->fetchColumn() !== false) {
                return true;
            }
        }

        if ($role !== '') {
            $stmt = $pdo->prepare(
                "SELECT 1
                 FROM role_permissions rp
                 INNER JOIN roles r ON rp.role_id = r.id
                 INNER JOIN permissions p ON rp.permission_id = p.id
                 WHERE r.slug = :role_slug AND p.slug = :slug
                 LIMIT 1"
            );
            $stmt->execute([
                'role_slug' => $role,
                'slug'      => $permissionSlug,
            ]);
            return $stmt->fetchColumn() !== false;
        }

        return false;
    }

    /**
     * Verify whether an HR/Admin user has BOTH role permission AND active user-level designation
     * to handle confidential workplace complaints.
     *
     * @param array<string, mixed>|null $viewerUser
     */
    public function canHandleConfidential(
        ?array $viewerUser,
        SupportTicketHandler $handlerModel,
        PDO $pdo
    ): bool {
        if (!$viewerUser || ($viewerUser['status'] ?? 'Active') !== 'Active') {
            return false;
        }

        $role = strtolower((string)($viewerUser['role'] ?? ''));
        if (!in_array($role, ['hr', 'admin'], true)) {
            return false;
        }

        if (!$this->hasCapability($viewerUser, 'support.confidential_handle', $pdo)) {
            return false;
        }

        $userId = (int)($viewerUser['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        return $handlerModel->isAuthorizedConfidentialHandler($userId);
    }

    /**
     * Build the SQL/inbox viewer context array for SupportTicket queries.
     *
     * @param array<string, mixed> $viewerUser
     * @return array<string, mixed>
     */
    public function buildViewerContext(
        array $viewerUser,
        SupportTicketHandler $handlerModel,
        PDO $pdo
    ): array {
        $role = strtolower((string)($viewerUser['role'] ?? ''));
        $empId = isset($viewerUser['employee_id']) && $viewerUser['employee_id'] !== null
            ? (int)$viewerUser['employee_id']
            : null;

        return [
            'user_id'                 => (int)($viewerUser['id'] ?? 0),
            'role'                    => $role,
            'employee_id'             => $empId,
            'is_admin'                => $role === 'admin',
            'has_standard_inbox'      => $this->hasCapability($viewerUser, 'support.inbox_view', $pdo),
            'can_handle_confidential' => $this->canHandleConfidential($viewerUser, $handlerModel, $pdo),
        ];
    }

    /**
     * Check whether an authenticated employee owns and may view a specific ticket.
     *
     * @param array<string, mixed>|null $viewerUser
     * @param array<string, mixed>      $ticket
     */
    public function canViewTicketAsEmployee(?array $viewerUser, array $ticket, ?PDO $pdo = null): bool
    {
        if (!$viewerUser || ($viewerUser['status'] ?? 'Active') !== 'Active') {
            return false;
        }

        $viewerEmpId = isset($viewerUser['employee_id']) && $viewerUser['employee_id'] !== null
            ? (int)$viewerUser['employee_id']
            : 0;
        $ticketEmpId = (int)($ticket['employee_id'] ?? 0);

        if ($viewerEmpId <= 0 || $ticketEmpId <= 0 || $viewerEmpId !== $ticketEmpId) {
            return false;
        }

        if ($this->isConflicted($viewerUser, $ticket)) {
            return false;
        }

        if ($pdo !== null && !$this->hasCapability($viewerUser, 'support.view_own', $pdo)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether an HR/Admin user may open a ticket in the HR Support Inbox.
     * Enforces COI first, then confidential handler designation or escalated Admin oversight.
     *
     * @param array<string, mixed>|null $viewerUser
     * @param array<string, mixed>      $ticket
     */
    public function canViewTicketAsHr(
        ?array $viewerUser,
        array $ticket,
        SupportTicketHandler $handlerModel,
        PDO $pdo
    ): bool {
        if (!$viewerUser || ($viewerUser['status'] ?? 'Active') !== 'Active') {
            return false;
        }

        $role = strtolower((string)($viewerUser['role'] ?? ''));
        if (!in_array($role, ['hr', 'admin'], true)) {
            return false;
        }

        // Strict COI check: subjects of a complaint are ALWAYS excluded (including HR and Admin).
        if ($this->isConflicted($viewerUser, $ticket)) {
            return false;
        }

        // Administrators hold oversight ONLY on COI-escalated cases (or if explicitly granted inbox capability)
        if ($role === 'admin') {
            if (!empty($ticket['escalated_flag']) && $this->hasCapability($viewerUser, 'support.manage_handlers', $pdo)) {
                return true;
            }
            if (!$this->hasCapability($viewerUser, 'support.inbox_view', $pdo)) {
                return false;
            }
        } elseif (!$this->hasCapability($viewerUser, 'support.inbox_view', $pdo)) {
            return false;
        }

        if (!$this->isConfidentialTicket($ticket)) {
            return true;
        }

        if ($this->canHandleConfidential($viewerUser, $handlerModel, $pdo)) {
            return true;
        }

        if ($role === 'admin' && !empty($ticket['escalated_flag'])) {
            return true;
        }

        return false;
    }

    /**
     * Verify whether an HR/Admin user may perform a specific triage/mutation action on a ticket.
     *
     * @param array<string, mixed>|null $viewerUser
     * @param array<string, mixed>      $ticket
     */
    public function canHandleTicketAsHr(
        ?array $viewerUser,
        array $ticket,
        string $requiredPermission,
        SupportTicketHandler $handlerModel,
        PDO $pdo
    ): bool {
        if (!$this->canViewTicketAsHr($viewerUser, $ticket, $handlerModel, $pdo)) {
            return false;
        }

        $role = strtolower((string)($viewerUser['role'] ?? ''));
        if ($role === 'admin' && !empty($ticket['escalated_flag']) && $this->hasCapability($viewerUser, 'support.manage_handlers', $pdo)) {
            return true;
        }

        return $this->hasCapability($viewerUser, $requiredPermission, $pdo);
    }

    /**
     * Determine whether a candidate assignee user may be assigned to handle a specific ticket.
     *
     * @param array<string, mixed> $candidateUser
     * @param array<string, mixed> $ticket
     */
    public function canReceiveAssignment(
        array $candidateUser,
        array $ticket,
        SupportTicketHandler $handlerModel
    ): bool {
        if (($candidateUser['status'] ?? '') !== 'Active') {
            return false;
        }

        $role = strtolower((string)($candidateUser['role'] ?? ''));
        if (!in_array($role, ['hr', 'admin'], true)) {
            return false;
        }

        if ($this->isConflicted($candidateUser, $ticket)) {
            return false;
        }

        if (!$this->isConfidentialTicket($ticket)) {
            return true;
        }

        $candidateId = (int)($candidateUser['id'] ?? 0);
        if ($candidateId > 0 && $handlerModel->isAuthorizedConfidentialHandler($candidateId)) {
            return true;
        }

        return $role === 'admin' && !empty($ticket['escalated_flag']);
    }

    /**
     * Authorize attachment download across employee owner and HR/Admin handlers.
     * Strictly blocks internal-only files from employees and enforces COI & confidential rules.
     *
     * @param array<string, mixed>|null $viewerUser
     * @param array<string, mixed>      $attachmentRow
     */
    public function canDownloadAttachment(
        ?array $viewerUser,
        array $attachmentRow,
        SupportTicketHandler $handlerModel,
        PDO $pdo,
        string $surface = 'auto'
    ): bool {
        if (!$viewerUser || ($viewerUser['status'] ?? 'Active') !== 'Active') {
            return false;
        }

        $ticketView = [
            'id'                  => $attachmentRow['ticket_id'] ?? null,
            'employee_id'         => $attachmentRow['ticket_employee_id'] ?? $attachmentRow['employee_id'] ?? null,
            'is_confidential'     => $attachmentRow['ticket_is_confidential'] ?? $attachmentRow['is_confidential'] ?? 0,
            'subject_employee_id' => $attachmentRow['ticket_subject_employee_id'] ?? $attachmentRow['subject_employee_id'] ?? null,
            'escalated_flag'      => $attachmentRow['ticket_escalated_flag'] ?? $attachmentRow['escalated_flag'] ?? 0,
            'request_type'        => $attachmentRow['ticket_request_type'] ?? $attachmentRow['request_type'] ?? 'support',
        ];

        if ($this->isConflicted($viewerUser, $ticketView)) {
            return false;
        }

        $isInternalOnly = !empty($attachmentRow['is_internal_only']);
        if ($isInternalOnly) {
            return $this->canViewTicketAsHr($viewerUser, $ticketView, $handlerModel, $pdo);
        }

        if ($surface === 'employee') {
            return $this->canViewTicketAsEmployee($viewerUser, $ticketView, $pdo);
        }

        if ($surface === 'hr') {
            return $this->canViewTicketAsHr($viewerUser, $ticketView, $handlerModel, $pdo);
        }

        return $this->canViewTicketAsEmployee($viewerUser, $ticketView, $pdo)
            || $this->canViewTicketAsHr($viewerUser, $ticketView, $handlerModel, $pdo);
    }

    /**
     * Check whether an employee may post a reply on the ticket's current status.
     *
     * @param array<string, mixed> $ticket
     */
    public function canEmployeeReply(array $ticket): bool
    {
        return in_array(
            (string)($ticket['status'] ?? ''),
            ['Submitted', 'Under Review', 'Waiting for Employee'],
            true
        );
    }

    /**
     * Check whether an employee may reopen a Resolved ticket within the configured policy window.
     *
     * @param array<string, mixed> $ticket
     */
    public function canEmployeeReopen(array $ticket, ?int $reopenWindowDays = null): bool
    {
        if ((string)($ticket['status'] ?? '') !== 'Resolved') {
            return false;
        }

        $windowDays = $reopenWindowDays ?? (int)config('support.reopen_window_days', 14);
        if ($windowDays <= 0) {
            return false;
        }

        $anchorRaw = (string)($ticket['resolved_at'] ?? $ticket['updated_at'] ?? $ticket['last_activity_at'] ?? '');
        if ($anchorRaw === '') {
            return false;
        }

        try {
            $resolvedAt = new \DateTimeImmutable($anchorRaw);
            $deadline = $resolvedAt->modify('+' . $windowDays . ' days');
            $now = new \DateTimeImmutable('now');
            return $now <= $deadline;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Employees may confirm closure ONLY when HR has already set the ticket to Resolved.
     *
     * @param array<string, mixed> $ticket
     */
    public function canEmployeeConfirmClose(array $ticket): bool
    {
        return (string)($ticket['status'] ?? '') === 'Resolved';
    }

    /**
     * Format assigned handler label for employee views with privacy discretion for confidential complaints.
     *
     * @param array<string, mixed> $ticket
     */
    public function formatAssignedHandlerForEmployee(array $ticket): string
    {
        if (empty($ticket['assigned_to_user_id'])) {
            return !empty($ticket['escalated_flag'])
                ? 'Escalated Oversight Review'
                : 'Pending Assignment';
        }

        if ($this->isConfidentialTicket($ticket)) {
            return 'Designated Confidential Officer';
        }

        $name = trim(((string)($ticket['assigned_first_name'] ?? '')) . ' ' . ((string)($ticket['assigned_last_name'] ?? '')));
        if ($name !== '') {
            return $name;
        }

        return (string)($ticket['assigned_to_username'] ?? 'HR Support Team');
    }

    /**
     * Strip sensitive details from confidential complaints before rendering dashboard widgets.
     *
     * @param array<string, mixed> $ticket
     * @return array<string, mixed>
     */
    public function maskForDashboard(array $ticket): array
    {
        $isConf = $this->isConfidentialTicket($ticket);
        $categoryLabel = $isConf ? 'Workplace Complaint' : (string)($ticket['category'] ?? 'General Inquiry');

        return [
            'id'               => (int)($ticket['id'] ?? 0),
            'ticket_ref'       => (string)($ticket['ticket_ref'] ?? ''),
            'request_type'     => (string)($ticket['request_type'] ?? 'support'),
            'category'         => $categoryLabel,
            'is_confidential'  => $isConf ? 1 : 0,
            'display_subject'  => $isConf ? 'Confidential Workplace Concern' : (string)($ticket['subject'] ?? ''),
            'status'           => (string)($ticket['status'] ?? 'Submitted'),
            'last_activity_at' => (string)($ticket['last_activity_at'] ?? $ticket['created_at'] ?? ''),
        ];
    }

    /**
     * Record a sensitive access grant/denial row without disrupting caller control flow.
     */
    public function recordSensitiveAccess(
        SupportTicket $ticketModel,
        ?int $ticketId,
        ?int $attachmentId,
        ?int $accessorUserId,
        string $actionType,
        bool $granted,
        ?string $reasonCode = null
    ): void {
        try {
            $ticketModel->addSensitiveAccessLog([
                'ticket_id'        => $ticketId,
                'attachment_id'    => $attachmentId,
                'accessor_user_id' => $accessorUserId,
                'action_type'      => $actionType,
                'access_granted'   => $granted ? 1 : 0,
                'reason_code'      => $reasonCode,
                'ip_address'       => function_exists('client_ip') ? client_ip() : ($_SERVER['REMOTE_ADDR'] ?? null),
                'user_agent'       => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (\Throwable $e) {
            error_log('Support sensitive access logging skipped: ' . $e->getMessage());
        }
    }
}
