<?php

/**
 * HrSupportTicket Model
 * Persistence, search/filtering, Admin support KPI aggregations, isolated Admin internal notes,
 * private attachments, assignment logs, and immutable transition history for HR-to-Admin support.
 */

namespace App\Models;

use Core\Database;
use PDO;

class HrSupportTicket
{
    private const REF_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getDb(): PDO
    {
        return $this->pdo;
    }

    /**
     * Generate a unique, CSPRNG-backed non-guessable HR support reference (e.g. HR-SUP-2026-8F3K9C2M).
     */
    public function generateTicketRef(?int $year = null, ?PDO $txPdo = null): string
    {
        $db = $txPdo ?? $this->pdo;
        $ticketYear = $year ?? (int)date('Y');
        $prefix = strtoupper(trim((string)config('hr_support.ticket_prefix', 'HR-SUP')));
        $alphabetLen = strlen(self::REF_ALPHABET);

        for ($attempt = 0; $attempt < 15; $attempt++) {
            $bytes = random_bytes(8);
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::REF_ALPHABET[ord($bytes[$i]) % $alphabetLen];
            }

            $candidate = sprintf('%s-%04d-%s', $prefix, $ticketYear, $code);
            $stmt = $db->prepare('SELECT 1 FROM hr_support_tickets WHERE ticket_ref = :ref LIMIT 1');
            $stmt->execute(['ref' => $candidate]);
            if ($stmt->fetchColumn() === false) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Unable to allocate a unique HR support ticket reference.');
    }

    /**
     * Insert a new HR-to-Admin ticket row.
     *
     * @param array<string, mixed> $data
     */
    public function createTicket(array $data): int
    {
        $sql = '
            INSERT INTO hr_support_tickets (
                ticket_ref,
                submitted_by_user_id,
                subject,
                description,
                category,
                priority,
                status,
                assigned_admin_id,
                last_activity_at
            ) VALUES (
                :ticket_ref,
                :submitted_by_user_id,
                :subject,
                :description,
                :category,
                :priority,
                :status,
                :assigned_admin_id,
                NOW()
            )
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'ticket_ref'           => $data['ticket_ref'],
            'submitted_by_user_id' => (int)$data['submitted_by_user_id'],
            'subject'              => $data['subject'],
            'description'          => $data['description'],
            'category'             => $data['category'],
            'priority'             => $data['priority'] ?? 'Medium',
            'status'               => $data['status'] ?? 'Submitted',
            'assigned_admin_id'    => $data['assigned_admin_id'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Fetch a single ticket by internal database ID with submitter & assignee display names.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $ticketId, bool $forUpdate = false): ?array
    {
        return $this->findOneBy('t.id = :val', ['val' => $ticketId], $forUpdate);
    }

    /**
     * Fetch a single ticket by unique tracking reference (`HR-SUP-YYYY-XXXXXXXX`).
     *
     * @return array<string, mixed>|null
     */
    public function findByRef(string $ticketRef, bool $forUpdate = false): ?array
    {
        $cleanRef = strtoupper(trim($ticketRef));
        if ($cleanRef === '') {
            return null;
        }
        return $this->findOneBy('t.ticket_ref = :val', ['val' => $cleanRef], $forUpdate);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function findOneBy(string $whereClause, array $params, bool $forUpdate): ?array
    {
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $sql = "
            SELECT
                t.*,
                su.username AS submitter_username,
                COALESCE(su.email, se.email) AS submitter_email,
                se.first_name AS submitter_first_name,
                se.last_name AS submitter_last_name,
                se.employee_no AS submitter_employee_no,
                au.username AS assigned_admin_username,
                COALESCE(au.email, ae.email) AS assigned_admin_email,
                ae.first_name AS assigned_first_name,
                ae.last_name AS assigned_last_name
            FROM hr_support_tickets t
            INNER JOIN users su ON t.submitted_by_user_id = su.id
            LEFT JOIN employees se ON su.employee_id = se.id
            LEFT JOIN users au ON t.assigned_admin_id = au.id
            LEFT JOIN employees ae ON au.employee_id = ae.id
            WHERE {$whereClause}
            LIMIT 1{$lock}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Resolve `ticket_ref` by internal ID for safe URL rendering in notifications.
     */
    public function findRefById(int $ticketId): ?string
    {
        if ($ticketId <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT ticket_ref FROM hr_support_tickets WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $ticketId]);
        $val = $stmt->fetchColumn();
        return is_string($val) && $val !== '' ? $val : null;
    }

    /**
     * Fetch tickets owned by the given HR user (My Support Tickets).
     * Never exposes internal Admin-only notes.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function findForHrUser(int $userId, array $filters = []): array
    {
        if ($userId <= 0) {
            return [];
        }

        $conditions = ['t.submitted_by_user_id = :uid'];
        $params = ['uid' => $userId];
        $this->applySharedFilters($conditions, $params, $filters);

        return $this->queryTicketList($conditions, $params, (int)($filters['limit'] ?? 200));
    }

    /**
     * Fetch HR-to-Admin support tickets for Admin Support Management Inbox with full search/filtering.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function findForAdminInbox(array $filters = []): array
    {
        $conditions = ['1=1'];
        $params = [];
        $this->applySharedFilters($conditions, $params, $filters);

        $priority = trim((string)($filters['priority'] ?? ''));
        if ($priority !== '') {
            $conditions[] = 't.priority = :priority';
            $params['priority'] = $priority;
        }

        $assigned = trim((string)($filters['assigned_admin_id'] ?? ''));
        if ($assigned === 'unassigned') {
            $conditions[] = 't.assigned_admin_id IS NULL';
        } elseif ($assigned === 'mine' && !empty($filters['viewer_user_id'])) {
            $conditions[] = 't.assigned_admin_id = :mine_admin_id';
            $params['mine_admin_id'] = (int)$filters['viewer_user_id'];
        } elseif (ctype_digit($assigned) && (int)$assigned > 0) {
            $conditions[] = 't.assigned_admin_id = :assigned_admin_id';
            $params['assigned_admin_id'] = (int)$assigned;
        }

        return $this->queryTicketList($conditions, $params, (int)($filters['limit'] ?? 250));
    }

    /**
     * @param array<int, string>   $conditions
     * @param array<string, mixed> $params
     * @param array<string, mixed> $filters
     */
    private function applySharedFilters(array &$conditions, array &$params, array $filters): void
    {
        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(t.ticket_ref LIKE :q1 OR t.subject LIKE :q2 OR su.username LIKE :q3)';
            $like = '%' . $search . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $conditions[] = 't.status = :status';
            $params['status'] = $status;
        }

        $category = trim((string)($filters['category'] ?? ''));
        if ($category !== '') {
            $conditions[] = 't.category = :category';
            $params['category'] = $category;
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $conditions[] = 'DATE(t.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $conditions[] = 'DATE(t.created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }
    }

    /**
     * @param array<int, string>   $conditions
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function queryTicketList(array $conditions, array $params, int $limit): array
    {
        $where = implode(' AND ', $conditions);
        $safeLimit = max(1, min(500, $limit));

        $sql = "
            SELECT
                t.*,
                su.username AS submitter_username,
                se.first_name AS submitter_first_name,
                se.last_name AS submitter_last_name,
                au.username AS assigned_admin_username,
                ae.first_name AS assigned_first_name,
                ae.last_name AS assigned_last_name
            FROM hr_support_tickets t
            INNER JOIN users su ON t.submitted_by_user_id = su.id
            LEFT JOIN employees se ON su.employee_id = se.id
            LEFT JOIN users au ON t.assigned_admin_id = au.id
            LEFT JOIN employees ae ON au.employee_id = ae.id
            WHERE {$where}
            ORDER BY
                CASE WHEN t.status IN ('Submitted', 'Under Review', 'In Progress', 'Waiting for HR') THEN 0 ELSE 1 END,
                FIELD(t.priority, 'Critical', 'High', 'Medium', 'Low'),
                t.last_activity_at DESC,
                t.id DESC
            LIMIT {$safeLimit}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Real database metrics for the Admin Dashboard & Support Inbox header.
     *
     * @return array{
     *     total_open: int,
     *     newly_submitted: int,
     *     critical_and_high: int,
     *     awaiting_hr: int,
     *     in_progress: int,
     *     recently_resolved: int
     * }
     */
    public function getAdminDashboardStats(): array
    {
        $sql = "
            SELECT
                SUM(CASE WHEN status IN ('Submitted', 'Under Review', 'In Progress', 'Waiting for HR') THEN 1 ELSE 0 END) AS total_open,
                SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) AS newly_submitted,
                SUM(CASE WHEN status IN ('Submitted', 'Under Review', 'In Progress', 'Waiting for HR') AND priority IN ('High', 'Critical') THEN 1 ELSE 0 END) AS critical_and_high,
                SUM(CASE WHEN status = 'Waiting for HR' THEN 1 ELSE 0 END) AS awaiting_hr,
                SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN status IN ('Resolved', 'Closed') AND COALESCE(resolved_at, closed_at, updated_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS recently_resolved
            FROM hr_support_tickets
        ";

        $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total_open'        => (int)($row['total_open'] ?? 0),
            'newly_submitted'   => (int)($row['newly_submitted'] ?? 0),
            'critical_and_high' => (int)($row['critical_and_high'] ?? 0),
            'awaiting_hr'       => (int)($row['awaiting_hr'] ?? 0),
            'in_progress'       => (int)($row['in_progress'] ?? 0),
            'recently_resolved' => (int)($row['recently_resolved'] ?? 0),
        ];
    }

    /**
     * Real summary counts for an individual HR user's submitted tickets.
     *
     * @return array{total: int, open: int, waiting_for_hr: int, resolved: int}
     */
    public function getHrUserSummaryStats(int $userId): array
    {
        if ($userId <= 0) {
            return ['total' => 0, 'open' => 0, 'waiting_for_hr' => 0, 'resolved' => 0];
        }

        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ('Submitted', 'Under Review', 'In Progress', 'Waiting for HR') THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN status = 'Waiting for HR' THEN 1 ELSE 0 END) AS waiting_count,
                SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_count
            FROM hr_support_tickets
            WHERE submitted_by_user_id = :uid
        ");
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total'          => (int)($row['total'] ?? 0),
            'open'           => (int)($row['open_count'] ?? 0),
            'waiting_for_hr' => (int)($row['waiting_count'] ?? 0),
            'resolved'       => (int)($row['resolved_count'] ?? 0),
        ];
    }

    /**
     * Insert conversation message or Admin-only internal note.
     *
     * @param array<string, mixed> $data
     */
    public function addMessage(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO hr_support_ticket_messages (
                ticket_id,
                sender_user_id,
                sender_role,
                message_type,
                is_internal_note,
                body
            ) VALUES (
                :ticket_id,
                :sender_user_id,
                :sender_role,
                :message_type,
                :is_internal_note,
                :body
            )
        ');

        $stmt->execute([
            'ticket_id'        => (int)$data['ticket_id'],
            'sender_user_id'   => $data['sender_user_id'] ?? null,
            'sender_role'      => $data['sender_role'] ?? 'hr',
            'message_type'     => $data['message_type'],
            'is_internal_note' => !empty($data['is_internal_note']) ? 1 : 0,
            'body'             => $data['body'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Fetch chronological conversation thread.
     * Strictly filters out Admin internal notes (`is_internal_note = 0`) unless `$includeInternal` is true.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMessages(int $ticketId, bool $includeInternal = false): array
    {
        $visClause = $includeInternal ? '' : 'AND m.is_internal_note = 0';
        $sql = "
            SELECT
                m.*,
                u.username AS sender_username,
                e.first_name AS sender_first_name,
                e.last_name AS sender_last_name
            FROM hr_support_ticket_messages m
            LEFT JOIN users u ON m.sender_user_id = u.id
            LEFT JOIN employees e ON u.employee_id = e.id
            WHERE m.ticket_id = :tid {$visClause}
            ORDER BY m.created_at ASC, m.id ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tid' => $ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert attachment metadata row.
     *
     * @param array<string, mixed> $data
     */
    public function addAttachment(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO hr_support_ticket_attachments (
                ticket_id,
                message_id,
                uploaded_by_user_id,
                original_filename,
                stored_filename,
                storage_relative_path,
                mime_type,
                file_size_bytes,
                sha256_checksum,
                is_internal_only
            ) VALUES (
                :ticket_id,
                :message_id,
                :uploaded_by_user_id,
                :original_filename,
                :stored_filename,
                :storage_relative_path,
                :mime_type,
                :file_size_bytes,
                :sha256_checksum,
                :is_internal_only
            )
        ');

        $stmt->execute([
            'ticket_id'             => (int)$data['ticket_id'],
            'message_id'            => $data['message_id'] ?? null,
            'uploaded_by_user_id'   => $data['uploaded_by_user_id'] ?? null,
            'original_filename'     => $data['original_filename'],
            'stored_filename'       => $data['stored_filename'],
            'storage_relative_path' => $data['storage_relative_path'],
            'mime_type'             => $data['mime_type'],
            'file_size_bytes'       => (int)$data['file_size_bytes'],
            'sha256_checksum'       => $data['sha256_checksum'],
            'is_internal_only'      => !empty($data['is_internal_only']) ? 1 : 0,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Fetch ticket attachments; strictly omits internal-only files when `$includeInternal` is false.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAttachments(int $ticketId, bool $includeInternal = false): array
    {
        $visClause = $includeInternal ? '' : 'AND a.is_internal_only = 0';
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.username AS uploaded_by_username
            FROM hr_support_ticket_attachments a
            LEFT JOIN users u ON a.uploaded_by_user_id = u.id
            WHERE a.ticket_id = :tid {$visClause}
            ORDER BY a.created_at ASC, a.id ASC
        ");
        $stmt->execute(['tid' => $ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lookup single attachment joined with its owning ticket scope.
     *
     * @return array<string, mixed>|null
     */
    public function findAttachmentWithTicket(int $attachmentId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT
                a.*,
                t.ticket_ref,
                t.submitted_by_user_id AS ticket_submitted_by_user_id,
                t.assigned_admin_id AS ticket_assigned_admin_id,
                t.status AS ticket_status
            FROM hr_support_ticket_attachments a
            INNER JOIN hr_support_tickets t ON a.ticket_id = t.id
            WHERE a.id = :aid
            LIMIT 1
        ');
        $stmt->execute(['aid' => $attachmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Append immutable assignment log entry.
     */
    public function addAssignment(
        int $ticketId,
        ?int $assignedToAdminId,
        ?int $assignedByUserId,
        string $action,
        ?string $notes = null
    ): int {
        $stmt = $this->pdo->prepare('
            INSERT INTO hr_support_ticket_assignments (
                ticket_id,
                assigned_to_admin_id,
                assigned_by_user_id,
                action,
                notes
            ) VALUES (
                :ticket_id,
                :assigned_to_admin_id,
                :assigned_by_user_id,
                :action,
                :notes
            )
        ');
        $stmt->execute([
            'ticket_id'            => $ticketId,
            'assigned_to_admin_id' => $assignedToAdminId,
            'assigned_by_user_id'  => $assignedByUserId,
            'action'               => $action,
            'notes'                => $notes,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Append immutable ticket lifecycle transition history row.
     */
    public function addHistory(
        int $ticketId,
        string $eventKind,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $fromPriority,
        ?string $toPriority,
        ?int $actorUserId,
        string $actorRole,
        ?string $remarks = null
    ): int {
        $stmt = $this->pdo->prepare('
            INSERT INTO hr_support_ticket_history (
                ticket_id,
                event_kind,
                from_status,
                to_status,
                from_priority,
                to_priority,
                changed_by_user_id,
                actor_role,
                remarks
            ) VALUES (
                :ticket_id,
                :event_kind,
                :from_status,
                :to_status,
                :from_priority,
                :to_priority,
                :changed_by_user_id,
                :actor_role,
                :remarks
            )
        ');
        $stmt->execute([
            'ticket_id'          => $ticketId,
            'event_kind'         => $eventKind,
            'from_status'        => $fromStatus,
            'to_status'          => $toStatus,
            'from_priority'      => $fromPriority,
            'to_priority'        => $toPriority,
            'changed_by_user_id' => $actorUserId,
            'actor_role'         => $actorRole,
            'remarks'            => $remarks,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(int $ticketId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT h.*, u.username AS actor_username, e.first_name AS actor_first_name, e.last_name AS actor_last_name
            FROM hr_support_ticket_history h
            LEFT JOIN users u ON h.changed_by_user_id = u.id
            LEFT JOIN employees e ON u.employee_id = e.id
            WHERE h.ticket_id = :tid
            ORDER BY h.created_at ASC, h.id ASC
        ');
        $stmt->execute(['tid' => $ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAssignments(int $ticketId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT
                a.*,
                tu.username AS assignee_username,
                au.username AS actor_username
            FROM hr_support_ticket_assignments a
            LEFT JOIN users tu ON a.assigned_to_admin_id = tu.id
            LEFT JOIN users au ON a.assigned_by_user_id = au.id
            WHERE a.ticket_id = :tid
            ORDER BY a.created_at DESC, a.id DESC
        ');
        $stmt->execute(['tid' => $ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * List active administrators eligible for ticket assignment.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAssignableAdmins(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                u.id AS user_id,
                u.username,
                COALESCE(u.email, e.email) AS email,
                e.first_name,
                e.last_name
            FROM users u
            LEFT JOIN employees e ON u.employee_id = e.id
            WHERE u.role = 'admin' AND u.status = 'Active'
            ORDER BY u.id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update allowed ticket attributes and bump last_activity_at.
     *
     * @param array<string, mixed> $fields
     */
    public function updateTicketFields(int $ticketId, array $fields): bool
    {
        $allowed = [
            'priority',
            'status',
            'assigned_admin_id',
            'resolution_summary',
            'reopen_count',
            'resolved_at',
            'closed_at',
        ];

        $sets = ['last_activity_at = NOW()'];
        $params = ['id' => $ticketId];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $fields)) {
                $sets[] = "{$col} = :{$col}";
                $params[$col] = $fields[$col];
            }
        }

        $sql = 'UPDATE hr_support_tickets SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
}
