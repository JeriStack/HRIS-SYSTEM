<?php

/**
 * SupportTicket Model
 * Data access for support tickets, confidential complaints, threads, attachments, and histories.
 */

namespace App\Models;

use Core\Model;
use PDO;

class SupportTicket extends Model
{
    /** @var array<int, string> */
    private const UPDATABLE_FIELDS = [
        'status',
        'priority',
        'assigned_to_user_id',
        'resolution_remarks',
        'escalated_flag',
        'escalation_reason',
        'resolved_at',
        'closed_at',
        'last_activity_at',
    ];

    public function __construct(?PDO $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            parent::__construct();
        }
    }

    public function getDb(): PDO
    {
        return $this->db;
    }

    /**
     * Atomically generate the next yearly server-side ticket reference
     * (SUP-YYYY-NNNNN for authenticated portal tickets, AUTH-YYYY-NNNNN for public auth issues).
     */
    public function generateNextTicketRef(int $year, ?PDO $txPdo = null, bool $isPublic = false): string
    {
        $pdo = $txPdo ?? $this->db;
        $startedTx = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTx = true;
        }

        $seqTable = $isPublic ? 'public_support_ticket_sequences' : 'support_ticket_sequences';
        $prefix = $isPublic
            ? (string)config('support.public_ticket_prefix', 'AUTH')
            : (string)config('support.ticket_prefix', 'SUP');

        try {
            $insertStmt = $pdo->prepare(
                "INSERT IGNORE INTO {$seqTable} (ticket_year, last_sequence) VALUES (:yr, 0)"
            );
            $insertStmt->execute(['yr' => $year]);

            $lockStmt = $pdo->prepare(
                "SELECT last_sequence FROM {$seqTable} WHERE ticket_year = :yr FOR UPDATE"
            );
            $lockStmt->execute(['yr' => $year]);
            $currentSeq = (int)$lockStmt->fetchColumn();
            $nextSeq = $currentSeq + 1;

            $updStmt = $pdo->prepare(
                "UPDATE {$seqTable} SET last_sequence = :seq WHERE ticket_year = :yr"
            );
            $updStmt->execute([
                'seq' => $nextSeq,
                'yr'  => $year,
            ]);

            if ($startedTx) {
                $pdo->commit();
            }

            return sprintf('%s-%04d-%05d', $prefix, $year, $nextSeq);
        } catch (\Throwable $e) {
            if ($startedTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Insert a new support ticket, confidential complaint, or public auth assistance record.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO support_tickets (
                ticket_ref,
                employee_id,
                submitted_by_user_id,
                support_channel,
                is_public_ticket,
                requester_name,
                requester_email,
                matched_user_id,
                public_issue_datetime,
                public_client_env,
                request_type,
                category,
                complaint_category,
                is_confidential,
                subject_employee_id,
                subject_reference,
                subject,
                description,
                incident_date,
                category_metadata_json,
                priority,
                status,
                assigned_to_user_id,
                escalated_flag,
                escalation_reason,
                last_activity_at
            ) VALUES (
                :ticket_ref,
                :employee_id,
                :submitted_by_user_id,
                :support_channel,
                :is_public_ticket,
                :requester_name,
                :requester_email,
                :matched_user_id,
                :public_issue_datetime,
                :public_client_env,
                :request_type,
                :category,
                :complaint_category,
                :is_confidential,
                :subject_employee_id,
                :subject_reference,
                :subject,
                :description,
                :incident_date,
                :category_metadata_json,
                :priority,
                :status,
                :assigned_to_user_id,
                :escalated_flag,
                :escalation_reason,
                NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'ticket_ref'             => $data['ticket_ref'],
            'employee_id'            => !empty($data['employee_id']) ? (int)$data['employee_id'] : null,
            'submitted_by_user_id'   => !empty($data['submitted_by_user_id']) ? (int)$data['submitted_by_user_id'] : null,
            'support_channel'        => ($data['support_channel'] ?? 'employee_portal') === 'public_auth' ? 'public_auth' : 'employee_portal',
            'is_public_ticket'       => !empty($data['is_public_ticket']) ? 1 : 0,
            'requester_name'         => $data['requester_name'] ?? null,
            'requester_email'        => $data['requester_email'] ?? null,
            'matched_user_id'        => !empty($data['matched_user_id']) ? (int)$data['matched_user_id'] : null,
            'public_issue_datetime'  => $data['public_issue_datetime'] ?? null,
            'public_client_env'      => $data['public_client_env'] ?? null,
            'request_type'           => $data['request_type'],
            'category'               => $data['category'],
            'complaint_category'     => $data['complaint_category'] ?? null,
            'is_confidential'        => !empty($data['is_confidential']) ? 1 : 0,
            'subject_employee_id'    => $data['subject_employee_id'] ?? null,
            'subject_reference'      => $data['subject_reference'] ?? null,
            'subject'                => $data['subject'],
            'description'            => $data['description'],
            'incident_date'          => $data['incident_date'] ?? null,
            'category_metadata_json' => $data['category_metadata_json'] ?? null,
            'priority'               => $data['priority'] ?? 'Medium',
            'status'                 => $data['status'] ?? 'Submitted',
            'assigned_to_user_id'    => $data['assigned_to_user_id'] ?? null,
            'escalated_flag'         => !empty($data['escalated_flag']) ? 1 : 0,
            'escalation_reason'      => $data['escalation_reason'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Retrieve a single support ticket with submitter, assignee, and optional subject context.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $ticketId): ?array
    {
        $sql = "
            SELECT
                t.*,
                e.employee_no,
                e.first_name,
                e.last_name,
                e.email AS employee_email,
                d.department_name,
                su.username AS submitted_by_username,
                au.username AS assigned_to_username,
                au.role AS assigned_to_role,
                ae.first_name AS assigned_first_name,
                ae.last_name AS assigned_last_name,
                ae.employee_no AS assigned_employee_no,
                se.employee_no AS subject_employee_no,
                se.first_name AS subject_first_name,
                se.last_name AS subject_last_name
            FROM support_tickets t
            LEFT JOIN employees e ON t.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN users su ON t.submitted_by_user_id = su.id
            LEFT JOIN users au ON t.assigned_to_user_id = au.id
            LEFT JOIN employees ae ON au.employee_id = ae.id
            LEFT JOIN employees se ON t.subject_employee_id = se.id
            WHERE t.id = :ticket_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['ticket_id' => $ticketId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Retrieve a ticket by server-generated reference string.
     *
     * @return array<string, mixed>|null
     */
    public function findByRef(string $ticketRef): ?array
    {
        $sql = "SELECT id FROM support_tickets WHERE ticket_ref = :ref LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['ref' => $ticketRef]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            return null;
        }
        return $this->findById((int)$id);
    }

    /**
     * List tickets owned strictly by the authenticated employee.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function findForEmployeeList(int $employeeId, array $filters = []): array
    {
        $conditions = ["t.employee_id = :emp_id"];
        $params = ['emp_id' => $employeeId];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $term = '%' . $search . '%';
            $conditions[] = "(t.ticket_ref LIKE :s1 OR t.subject LIKE :s2 OR t.category LIKE :s3 OR COALESCE(t.complaint_category, '') LIKE :s4)";
            $params['s1'] = $term;
            $params['s2'] = $term;
            $params['s3'] = $term;
            $params['s4'] = $term;
        }

        $category = trim((string)($filters['category'] ?? ''));
        if ($category !== '') {
            $conditions[] = "t.category = :cat";
            $params['cat'] = $category;
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $conditions[] = "t.status = :status";
            $params['status'] = $status;
        }

        $requestType = trim((string)($filters['request_type'] ?? ''));
        if (in_array($requestType, ['support', 'complaint'], true)) {
            $conditions[] = "t.request_type = :req_type";
            $params['req_type'] = $requestType;
        }

        $where = implode(' AND ', $conditions);
        $limitSql = '';
        if (isset($filters['limit']) && (int)$filters['limit'] > 0) {
            $limitVal = min(100, (int)$filters['limit']);
            $limitSql = " LIMIT {$limitVal}";
        }

        $sql = "
            SELECT
                t.id,
                t.ticket_ref,
                t.employee_id,
                t.request_type,
                t.category,
                t.complaint_category,
                t.is_confidential,
                t.subject,
                t.priority,
                t.status,
                t.assigned_to_user_id,
                t.escalated_flag,
                t.resolved_at,
                t.closed_at,
                t.last_activity_at,
                t.created_at,
                t.updated_at,
                au.username AS assigned_to_username,
                au.role AS assigned_to_role,
                ae.first_name AS assigned_first_name,
                ae.last_name AS assigned_last_name
            FROM support_tickets t
            LEFT JOIN users au ON t.assigned_to_user_id = au.id
            LEFT JOIN employees ae ON au.employee_id = ae.id
            WHERE {$where}
            ORDER BY t.last_activity_at DESC, t.id DESC
            {$limitSql}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count currently open requests owned by an employee.
     */
    public function countOpenByEmployee(int $employeeId): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM support_tickets
            WHERE employee_id = :emp_id
              AND status IN ('Submitted', 'Under Review', 'Waiting for Employee')
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['emp_id' => $employeeId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Fetch up to N recent summary rows for the compact Employee Dashboard widget.
     * Deliberately excludes sensitive complaint narrative columns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentByEmployee(int $employeeId, int $limit = 3): array
    {
        $safeLimit = max(1, min(10, $limit));
        $sql = "
            SELECT
                t.id,
                t.ticket_ref,
                t.request_type,
                t.category,
                t.complaint_category,
                t.is_confidential,
                t.subject,
                t.priority,
                t.status,
                t.last_activity_at,
                t.created_at
            FROM support_tickets t
            WHERE t.employee_id = :emp_id
            ORDER BY t.last_activity_at DESC, t.id DESC
            LIMIT {$safeLimit}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['emp_id' => $employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * List accessible tickets for HR/Admin Support Inbox, enforcing confidentiality and COI filtering.
     *
     * @param array<string, mixed> $viewerContext
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function findForHrInbox(array $viewerContext, array $filters = []): array
    {
        $params = [];
        $conditions = $this->buildHrVisibilityConditions($viewerContext, $params, 'inbox');

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $term = '%' . $search . '%';
            $conditions[] = "(t.ticket_ref LIKE :s1 OR t.subject LIKE :s2 OR COALESCE(e.employee_no, '') LIKE :s3 OR COALESCE(CONCAT(e.first_name, ' ', e.last_name), '') LIKE :s4 OR t.category LIKE :s5 OR COALESCE(t.requester_name, '') LIKE :s6 OR COALESCE(t.requester_email, '') LIKE :s7)";
            $params['s1'] = $term;
            $params['s2'] = $term;
            $params['s3'] = $term;
            $params['s4'] = $term;
            $params['s5'] = $term;
            $params['s6'] = $term;
            $params['s7'] = $term;
        }

        $category = trim((string)($filters['category'] ?? ''));
        if ($category !== '') {
            $conditions[] = "t.category = :cat";
            $params['cat'] = $category;
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $conditions[] = "t.status = :status";
            $params['status'] = $status;
        }

        $priority = trim((string)($filters['priority'] ?? ''));
        if ($priority !== '') {
            $conditions[] = "t.priority = :priority";
            $params['priority'] = $priority;
        }

        $scope = trim((string)($filters['scope'] ?? ''));
        if ($scope === 'confidential') {
            $conditions[] = "t.is_confidential = 1";
        } elseif ($scope === 'general') {
            $conditions[] = "t.is_confidential = 0";
        } elseif ($scope === 'public_auth') {
            $conditions[] = "t.is_public_ticket = 1";
        } elseif ($scope === 'internal') {
            $conditions[] = "t.is_public_ticket = 0";
        } elseif ($scope === 'unassigned') {
            $conditions[] = "t.assigned_to_user_id IS NULL AND t.status IN ('Submitted', 'Under Review', 'Waiting for Employee')";
        } elseif ($scope === 'mine' && !empty($viewerContext['user_id'])) {
            $conditions[] = "t.assigned_to_user_id = :mine_uid";
            $params['mine_uid'] = (int)$viewerContext['user_id'];
        }

        $where = implode(' AND ', $conditions);
        $sql = "
            SELECT
                t.id,
                t.ticket_ref,
                t.employee_id,
                t.support_channel,
                t.is_public_ticket,
                t.requester_name,
                t.requester_email,
                t.public_issue_datetime,
                t.public_client_env,
                t.request_type,
                t.category,
                t.complaint_category,
                t.is_confidential,
                t.subject_employee_id,
                t.subject,
                t.priority,
                t.status,
                t.assigned_to_user_id,
                t.escalated_flag,
                t.last_activity_at,
                t.created_at,
                e.employee_no,
                e.first_name,
                e.last_name,
                d.department_name,
                au.username AS assigned_to_username,
                ae.first_name AS assigned_first_name,
                ae.last_name AS assigned_last_name
            FROM support_tickets t
            LEFT JOIN employees e ON t.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN users au ON t.assigned_to_user_id = au.id
            LEFT JOIN employees ae ON au.employee_id = ae.id
            WHERE {$where}
            ORDER BY
                CASE WHEN t.status IN ('Submitted', 'Under Review', 'Waiting for Employee') THEN 0 ELSE 1 END,
                FIELD(t.priority, 'Urgent', 'High', 'Medium', 'Low'),
                t.last_activity_at DESC,
                t.id DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compute HR Support Inbox overview metrics strictly scoped to the viewer's clearance and COI.
     *
     * @param array<string, mixed> $viewerContext
     * @return array<string, mixed>
     */
    public function getHrOverviewStats(array $viewerContext): array
    {
        $params = [];
        $conditions = $this->buildHrVisibilityConditions($viewerContext, $params, 'stats');
        $where = implode(' AND ', $conditions);

        $sql = "
            SELECT
                SUM(CASE WHEN t.status IN ('Submitted', 'Under Review', 'Waiting for Employee') THEN 1 ELSE 0 END) AS total_open,
                SUM(CASE WHEN t.status = 'Submitted' THEN 1 ELSE 0 END) AS new_submissions,
                SUM(CASE WHEN t.status = 'Under Review' THEN 1 ELSE 0 END) AS under_review,
                SUM(CASE WHEN t.status = 'Waiting for Employee' THEN 1 ELSE 0 END) AS waiting_for_employee,
                SUM(CASE WHEN t.status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_count,
                SUM(CASE WHEN t.is_confidential = 1 AND t.status IN ('Submitted', 'Under Review', 'Waiting for Employee') THEN 1 ELSE 0 END) AS confidential_attention
            FROM support_tickets t
            WHERE {$where}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $canHandleConf = !empty($viewerContext['can_handle_confidential']);
        $isAdmin = !empty($viewerContext['is_admin']);
        $confCount = (int)($row['confidential_attention'] ?? 0);

        return [
            'total_open'               => (int)($row['total_open'] ?? 0),
            'new_submissions'          => (int)($row['new_submissions'] ?? 0),
            'under_review'             => (int)($row['under_review'] ?? 0),
            'waiting_for_employee'     => (int)($row['waiting_for_employee'] ?? 0),
            'resolved_count'           => (int)($row['resolved_count'] ?? 0),
            'confidential_attention'   => ($canHandleConf || ($isAdmin && $confCount > 0)) ? $confCount : 0,
            'show_confidential_metric' => $canHandleConf || ($isAdmin && $confCount > 0),
        ];
    }

    /**
     * Build SQL visibility clauses enforcing confidential handler designation and COI exclusion.
     *
     * @param array<string, mixed> $viewerContext
     * @param array<string, mixed> $params
     * @return array<int, string>
     */
    private function buildHrVisibilityConditions(array $viewerContext, array &$params, string $prefix): array
    {
        $conditions = ["1=1"];

        $viewerEmpId = isset($viewerContext['employee_id']) && $viewerContext['employee_id'] !== null
            ? (int)$viewerContext['employee_id']
            : null;

        if ($viewerEmpId !== null && $viewerEmpId > 0) {
            $paramKey = $prefix . '_coi_emp';
            $conditions[] = "(t.subject_employee_id IS NULL OR t.subject_employee_id != :{$paramKey})";
            $params[$paramKey] = $viewerEmpId;
        }

        $canHandleConf = !empty($viewerContext['can_handle_confidential']);
        $isAdmin = !empty($viewerContext['is_admin']);
        $hasStandardInbox = array_key_exists('has_standard_inbox', $viewerContext)
            ? !empty($viewerContext['has_standard_inbox'])
            : true;

        if ($isAdmin && !$hasStandardInbox && !$canHandleConf) {
            $conditions[] = "t.escalated_flag = 1";
        } elseif (!$canHandleConf) {
            if ($isAdmin) {
                $conditions[] = "(t.is_confidential = 0 OR t.escalated_flag = 1)";
            } else {
                $conditions[] = "t.is_confidential = 0";
            }
        }

        return $conditions;
    }

    /**
     * Insert a threaded conversation message or internal HR note.
     *
     * @param array<string, mixed> $data
     */
    public function addMessage(array $data): int
    {
        $sql = "
            INSERT INTO support_messages (
                ticket_id,
                sender_user_id,
                sender_role,
                message_type,
                is_internal_note,
                body,
                created_at
            ) VALUES (
                :ticket_id,
                :sender_user_id,
                :sender_role,
                :message_type,
                :is_internal_note,
                :body,
                NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'ticket_id'        => (int)$data['ticket_id'],
            'sender_user_id'   => $data['sender_user_id'] ?? null,
            'sender_role'      => $data['sender_role'] ?? 'employee',
            'message_type'     => $data['message_type'],
            'is_internal_note' => !empty($data['is_internal_note']) ? 1 : 0,
            'body'             => $data['body'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Fetch chronological conversation history; strictly excludes internal HR notes unless authorized.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMessages(int $ticketId, bool $includeInternal = false): array
    {
        $internalClause = $includeInternal ? '' : ' AND m.is_internal_note = 0';
        $sql = "
            SELECT
                m.id,
                m.ticket_id,
                m.sender_user_id,
                m.sender_role,
                m.message_type,
                m.is_internal_note,
                m.body,
                m.created_at,
                u.username AS sender_username,
                e.first_name AS sender_first_name,
                e.last_name AS sender_last_name
            FROM support_messages m
            LEFT JOIN users u ON m.sender_user_id = u.id
            LEFT JOIN employees e ON u.employee_id = e.id
            WHERE m.ticket_id = :ticket_id {$internalClause}
            ORDER BY m.created_at ASC, m.id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert an attachment metadata record.
     *
     * @param array<string, mixed> $data
     */
    public function addAttachment(array $data): int
    {
        $sql = "
            INSERT INTO support_attachments (
                ticket_id,
                message_id,
                uploaded_by_user_id,
                original_filename,
                stored_filename,
                storage_relative_path,
                mime_type,
                file_size_bytes,
                sha256_checksum,
                is_confidential,
                is_internal_only,
                created_at
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
                :is_confidential,
                :is_internal_only,
                NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
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
            'is_confidential'       => !empty($data['is_confidential']) ? 1 : 0,
            'is_internal_only'      => !empty($data['is_internal_only']) ? 1 : 0,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Fetch ticket attachments; hides HR internal-only documents from employees.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAttachments(int $ticketId, bool $includeInternalOnly = false): array
    {
        $internalClause = $includeInternalOnly ? '' : ' AND a.is_internal_only = 0';
        $sql = "
            SELECT
                a.*,
                u.username AS uploader_username,
                e.first_name AS uploader_first_name,
                e.last_name AS uploader_last_name
            FROM support_attachments a
            LEFT JOIN users u ON a.uploaded_by_user_id = u.id
            LEFT JOIN employees e ON u.employee_id = e.id
            WHERE a.ticket_id = :ticket_id {$internalClause}
            ORDER BY a.created_at ASC, a.id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve a single attachment together with parent ticket access metadata.
     *
     * @return array<string, mixed>|null
     */
    public function findAttachmentWithTicket(int $attachmentId): ?array
    {
        $sql = "
            SELECT
                a.*,
                t.ticket_ref,
                t.employee_id AS ticket_employee_id,
                t.is_public_ticket AS ticket_is_public_ticket,
                t.support_channel AS ticket_support_channel,
                t.is_confidential AS ticket_is_confidential,
                t.subject_employee_id AS ticket_subject_employee_id,
                t.escalated_flag AS ticket_escalated_flag,
                t.request_type AS ticket_request_type,
                t.status AS ticket_status
            FROM support_attachments a
            INNER JOIN support_tickets t ON a.ticket_id = t.id
            WHERE a.id = :att_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['att_id' => $attachmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Append a status transition entry to the immutable ticket history.
     *
     * @param array<string, mixed> $data
     */
    public function addStatusHistory(array $data): int
    {
        $sql = "
            INSERT INTO support_status_history (
                ticket_id,
                from_status,
                to_status,
                changed_by_user_id,
                remarks,
                created_at
            ) VALUES (
                :ticket_id,
                :from_status,
                :to_status,
                :changed_by_user_id,
                :remarks,
                NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'ticket_id'          => (int)$data['ticket_id'],
            'from_status'        => $data['from_status'] ?? null,
            'to_status'          => $data['to_status'],
            'changed_by_user_id' => $data['changed_by_user_id'] ?? null,
            'remarks'            => $data['remarks'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Retrieve full status transition log for a ticket.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStatusHistory(int $ticketId): array
    {
        $sql = "
            SELECT
                h.*,
                u.username AS actor_username,
                u.role AS actor_role,
                e.first_name AS actor_first_name,
                e.last_name AS actor_last_name
            FROM support_status_history h
            LEFT JOIN users u ON h.changed_by_user_id = u.id
            LEFT JOIN employees e ON u.employee_id = e.id
            WHERE h.ticket_id = :ticket_id
            ORDER BY h.created_at ASC, h.id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record a handler assignment, reassignment, unassignment, or escalation event.
     *
     * @param array<string, mixed> $data
     */
    public function addAssignment(array $data): int
    {
        $sql = "
            INSERT INTO support_assignments (
                ticket_id,
                assigned_to_user_id,
                assigned_by_user_id,
                action,
                notes,
                created_at
            ) VALUES (
                :ticket_id,
                :assigned_to_user_id,
                :assigned_by_user_id,
                :action,
                :notes,
                NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'ticket_id'           => (int)$data['ticket_id'],
            'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
            'assigned_by_user_id' => $data['assigned_by_user_id'] ?? null,
            'action'              => $data['action'] ?? 'assigned',
            'notes'               => $data['notes'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Retrieve assignment and escalation history for a ticket.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAssignments(int $ticketId): array
    {
        $sql = "
            SELECT
                a.*,
                tu.username AS assignee_username,
                te.first_name AS assignee_first_name,
                te.last_name AS assignee_last_name,
                bu.username AS actor_username,
                be.first_name AS actor_first_name,
                be.last_name AS actor_last_name
            FROM support_assignments a
            LEFT JOIN users tu ON a.assigned_to_user_id = tu.id
            LEFT JOIN employees te ON tu.employee_id = te.id
            LEFT JOIN users bu ON a.assigned_by_user_id = bu.id
            LEFT JOIN employees be ON bu.employee_id = be.id
            WHERE a.ticket_id = :ticket_id
            ORDER BY a.created_at ASC, a.id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update whitelisted ticket state fields safely.
     *
     * @param array<string, mixed> $fields
     */
    public function updateTicketFields(int $ticketId, array $fields): bool
    {
        $setClauses = [];
        $params = ['ticket_id' => $ticketId];

        foreach ($fields as $column => $value) {
            if (!in_array($column, self::UPDATABLE_FIELDS, true)) {
                continue;
            }
            $setClauses[] = "`{$column}` = :{$column}";
            $params[$column] = $value;
        }

        if ($setClauses === []) {
            return false;
        }

        $sql = "UPDATE support_tickets SET " . implode(', ', $setClauses) . ", updated_at = NOW() WHERE id = :ticket_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Record a sensitive access or COI denial entry for confidential governance auditing.
     *
     * @param array<string, mixed> $data
     */
    public function addSensitiveAccessLog(array $data): int
    {
        $sql = "
            INSERT INTO support_sensitive_access_logs (
                ticket_id,
                attachment_id,
                accessor_user_id,
                action_type,
                access_granted,
                reason_code,
                ip_address,
                user_agent,
                created_at
            ) VALUES (
                :ticket_id,
                :attachment_id,
                :accessor_user_id,
                :action_type,
                :access_granted,
                :reason_code,
                :ip_address,
                :user_agent,
                NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'ticket_id'        => $data['ticket_id'] ?? null,
            'attachment_id'    => $data['attachment_id'] ?? null,
            'accessor_user_id' => $data['accessor_user_id'] ?? null,
            'action_type'      => $data['action_type'],
            'access_granted'   => !empty($data['access_granted']) ? 1 : 0,
            'reason_code'      => $data['reason_code'] ?? null,
            'ip_address'       => $data['ip_address'] ?? null,
            'user_agent'       => isset($data['user_agent']) ? mb_substr((string)$data['user_agent'], 0, 255) : null,
        ]);

        return (int)$this->db->lastInsertId();
    }
}
