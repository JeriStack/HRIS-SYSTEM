<?php

/**
 * HrSupportAccessService
 * Enforces strict server-side RBAC, HR own-ticket isolation, Admin inbox clearance,
 * Admin-only internal note separation, and status transition / reopening policies
 * for the HR-to-Admin Help & Support module.
 */

namespace App\Services;

use Core\PermissionRegistry;
use PDO;

final class HrSupportAccessService
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
     * Evaluate permission via session RBAC helper, DB role_permissions, or registry defaults.
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

        $role = strtolower(trim((string)($user['role'] ?? '')));
        if ($role === 'admin') {
            return PermissionRegistry::defaultRoleHas('admin', $permissionSlug);
        }

        $sessionUid = (int)($_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? 0));
        if (function_exists('can') && $sessionUid > 0 && $sessionUid === (int)($user['id'] ?? 0)) {
            return can($permissionSlug);
        }

        if ($role !== '') {
            $roleId = isset($user['role_id']) && $user['role_id'] !== null ? (int)$user['role_id'] : 0;
            if ($roleId > 0) {
                $stmt = $pdo->prepare('
                    SELECT 1
                    FROM role_permissions rp
                    INNER JOIN permissions p ON rp.permission_id = p.id
                    WHERE rp.role_id = :role_id AND p.slug = :slug
                    LIMIT 1
                ');
                $stmt->execute(['role_id' => $roleId, 'slug' => $permissionSlug]);
                if ($stmt->fetchColumn() !== false) {
                    return true;
                }
            }

            $stmt = $pdo->prepare('
                SELECT 1
                FROM role_permissions rp
                INNER JOIN roles r ON rp.role_id = r.id
                INNER JOIN permissions p ON rp.permission_id = p.id
                WHERE r.slug = :role_slug AND p.slug = :slug
                LIMIT 1
            ');
            $stmt->execute(['role_slug' => $role, 'slug' => $permissionSlug]);
            if ($stmt->fetchColumn() !== false) {
                return true;
            }

            // Fallback when role_permissions hasn't been seeded yet for hr_support.*
            $countStmt = $pdo->query("SELECT COUNT(*) FROM permissions WHERE slug LIKE 'hr_support.%'");
            if ($countStmt && (int)$countStmt->fetchColumn() === 0) {
                return PermissionRegistry::defaultRoleHas($role, $permissionSlug);
            }
        }

        return false;
    }

    /**
     * Check whether an HR user owns and may inspect the given HR-to-Admin ticket.
     *
     * @param array<string, mixed>|null $viewerUser
     * @param array<string, mixed>      $ticket
     */
    public function canViewTicketAsHr(?array $viewerUser, array $ticket, PDO $pdo): bool
    {
        if (!$viewerUser || ($viewerUser['status'] ?? 'Active') !== 'Active') {
            return false;
        }

        if (strtolower((string)($viewerUser['role'] ?? '')) !== 'hr') {
            return false;
        }

        if (!$this->hasCapability($viewerUser, 'hr_support.view_own', $pdo)) {
            return false;
        }

        $viewerUid = (int)($viewerUser['id'] ?? 0);
        $ownerUid = (int)($ticket['submitted_by_user_id'] ?? 0);

        return $viewerUid > 0 && $ownerUid > 0 && $viewerUid === $ownerUid;
    }

    /**
     * Check whether an Administrator may inspect the HR-to-Admin ticket.
     *
     * @param array<string, mixed>|null $viewerUser
     */
    public function canViewTicketAsAdmin(?array $viewerUser, PDO $pdo): bool
    {
        if (!$viewerUser || ($viewerUser['status'] ?? 'Active') !== 'Active') {
            return false;
        }

        if (strtolower((string)($viewerUser['role'] ?? '')) !== 'admin') {
            return false;
        }

        return $this->hasCapability($viewerUser, 'hr_support.admin_inbox', $pdo);
    }

    /**
     * Strictly gate private internal notes & internal attachments to Admins only.
     *
     * @param array<string, mixed>|null $viewerUser
     */
    public function canViewInternalNotes(?array $viewerUser, PDO $pdo): bool
    {
        if (!$viewerUser || ($viewerUser['status'] ?? 'Active') !== 'Active') {
            return false;
        }

        if (strtolower((string)($viewerUser['role'] ?? '')) !== 'admin') {
            return false;
        }

        return $this->hasCapability($viewerUser, 'hr_support.internal_notes', $pdo);
    }

    /**
     * Authorize attachment download for either owning HR user or Admin inbox manager.
     * Enforces Admin-only protection on internal attachments.
     *
     * @param array<string, mixed>|null $viewerUser
     * @param array<string, mixed>      $attachmentRow
     */
    public function canDownloadAttachment(
        ?array $viewerUser,
        array $attachmentRow,
        PDO $pdo,
        string $surface = 'auto'
    ): bool {
        if (!$viewerUser || ($viewerUser['status'] ?? 'Active') !== 'Active') {
            return false;
        }

        if (!empty($attachmentRow['is_internal_only'])) {
            return $this->canViewInternalNotes($viewerUser, $pdo);
        }

        $ticketScope = [
            'submitted_by_user_id' => $attachmentRow['ticket_submitted_by_user_id'] ?? null,
        ];

        if ($surface === 'hr') {
            return $this->canViewTicketAsHr($viewerUser, $ticketScope, $pdo);
        }

        if ($surface === 'admin') {
            return $this->canViewTicketAsAdmin($viewerUser, $pdo);
        }

        return $this->canViewTicketAsHr($viewerUser, $ticketScope, $pdo)
            || $this->canViewTicketAsAdmin($viewerUser, $pdo);
    }

    /**
     * Check whether HR may post an additional follow-up reply.
     *
     * @param array<string, mixed> $ticket
     */
    public function canHrReply(array $ticket): bool
    {
        return in_array(
            (string)($ticket['status'] ?? ''),
            ['Submitted', 'Under Review', 'In Progress', 'Waiting for HR'],
            true
        );
    }

    /**
     * HR may confirm final closure when Admin has already marked the ticket Resolved.
     *
     * @param array<string, mixed> $ticket
     */
    public function canHrConfirmResolution(array $ticket): bool
    {
        return (string)($ticket['status'] ?? '') === 'Resolved';
    }

    /**
     * HR reopening policy: allowed strictly on Resolved tickets within the configured calendar-day window.
     * Closed tickets require Admin reopening.
     *
     * @param array<string, mixed> $ticket
     */
    public function canHrReopen(array $ticket, ?int $reopenWindowDays = null): bool
    {
        if ((string)($ticket['status'] ?? '') !== 'Resolved') {
            return false;
        }

        $windowDays = $reopenWindowDays ?? (int)config('hr_support.reopen_window_days', 14);
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
            return new \DateTimeImmutable('now') <= $deadline;
        } catch (\Throwable) {
            return false;
        }
    }
}
