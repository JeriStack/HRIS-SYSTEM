<?php

/**
 * Profile Correction Request Model
 * Manages employee correction requests for HR-controlled profile records,
 * enforcing whitelist validation, strict ownership, and transactional HR approval.
 */

namespace App\Models;

use Core\AuditService;
use Core\Model;
use DateTimeImmutable;
use PDO;

class ProfileCorrectionRequest extends Model
{
    public const STATUS_PENDING   = 'Pending';
    public const STATUS_APPROVED  = 'Approved';
    public const STATUS_REJECTED  = 'Rejected';
    public const STATUS_CANCELLED = 'Cancelled';

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    public function __construct(?PDO $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            parent::__construct();
        }
    }

    /**
     * Retrieve whitelisted HR-controlled field metadata.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getAllowedFields(): array
    {
        return (array)config('profile.correction_fields', []);
    }

    /**
     * Resolve human-readable label for a correction field key.
     */
    public static function fieldLabel(string $fieldName): string
    {
        $fields = self::getAllowedFields();
        return (string)($fields[$fieldName]['label'] ?? ucwords(str_replace('_', ' ', $fieldName)));
    }

    /**
     * Retrieve correction requests submitted by one employee, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByEmployee(int $employeeId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = "
            SELECT
                r.*,
                ru.username AS reviewer_username,
                re.first_name AS reviewer_first_name,
                re.last_name AS reviewer_last_name
            FROM profile_correction_requests r
            LEFT JOIN users ru ON r.reviewed_by = ru.id
            LEFT JOIN employees re ON ru.employee_id = re.id
            WHERE r.employee_id = :emp_id
            ORDER BY r.created_at DESC, r.id DESC
            LIMIT {$limit}
        ";

        $rows = $this->fetchAll($sql, ['emp_id' => $employeeId]);
        return $this->enrichRowsWithDisplayValues($rows);
    }

    /**
     * Retrieve correction requests for HR review queue with optional status filter.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllForReview(?string $status = null, int $limit = 100): array
    {
        $limit = max(1, min(300, $limit));
        $params = [];
        $where = '';
        if ($status !== null && in_array($status, self::VALID_STATUSES, true)) {
            $where = 'WHERE r.status = :status';
            $params['status'] = $status;
        }

        $sql = "
            SELECT
                r.*,
                e.employee_no,
                e.first_name,
                e.middle_name,
                e.last_name,
                d.department_name,
                p.position_name,
                ru.username AS reviewer_username,
                re.first_name AS reviewer_first_name,
                re.last_name AS reviewer_last_name
            FROM profile_correction_requests r
            INNER JOIN employees e ON r.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN users ru ON r.reviewed_by = ru.id
            LEFT JOIN employees re ON ru.employee_id = re.id
            {$where}
            ORDER BY CASE WHEN r.status = 'Pending' THEN 0 ELSE 1 END ASC, r.created_at DESC, r.id DESC
            LIMIT {$limit}
        ";

        $rows = $this->fetchAll($sql, $params);
        return $this->enrichRowsWithDisplayValues($rows);
    }

    /**
     * Count pending profile correction requests for HR badges/metrics.
     */
    public function countPending(): int
    {
        try {
            return (int)$this->fetchColumn(
                "SELECT COUNT(*) FROM profile_correction_requests WHERE status = :status",
                ['status' => self::STATUS_PENDING]
            );
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Find single correction request by ID.
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                r.*,
                e.employee_no,
                e.first_name,
                e.middle_name,
                e.last_name,
                e.email AS employee_work_email,
                e.department_id AS employee_department_id,
                e.position_id AS employee_position_id,
                e.employment_type AS employee_employment_type,
                e.employment_status AS employee_employment_status,
                e.date_hired AS employee_date_hired
            FROM profile_correction_requests r
            INNER JOIN employees e ON r.employee_id = e.id
            WHERE r.id = :id
            LIMIT 1
        ";

        $row = $this->fetchOne($sql, ['id' => $id]);
        if ($row === null) {
            return null;
        }

        $enriched = $this->enrichRowsWithDisplayValues([$row]);
        return $enriched[0];
    }

    /**
     * Check whether employee already has an active Pending request for the exact field.
     */
    public function hasPendingDuplicate(int $employeeId, string $fieldName): bool
    {
        return (int)$this->fetchColumn(
            "SELECT COUNT(*) FROM profile_correction_requests
             WHERE employee_id = :emp_id AND field_name = :field_name AND status = :status",
            [
                'emp_id'     => $employeeId,
                'field_name' => $fieldName,
                'status'     => self::STATUS_PENDING,
            ]
        ) > 0;
    }

    /**
     * Validate a correction request submission against field metadata and target employee record.
     *
     * @param array<string, mixed> $employee Live employee record
     * @param array<string, mixed> $input Raw submission payload
     * @return array{ok: bool, data?: array<string, mixed>, errors: array<string, string>}
     */
    public function validateSubmission(array $employee, array $input): array
    {
        $fields = self::getAllowedFields();
        $fieldName = trim((string)($input['field_name'] ?? ''));
        $requestedValue = trim((string)($input['requested_value'] ?? ''));
        $reason = trim((string)($input['reason'] ?? ''));

        $errors = [];

        if (!isset($fields[$fieldName])) {
            $errors['field_name'] = 'Selected profile field is not eligible for self-service correction requests.';
            return ['ok' => false, 'errors' => $errors];
        }

        $meta = $fields[$fieldName];
        $required = (bool)($meta['required'] ?? true);

        if ($requestedValue === '' && $required) {
            $errors['requested_value'] = 'Requested correction value is required.';
        }

        if ($reason === '') {
            $errors['reason'] = 'Please explain the reason for this profile correction request.';
        } elseif (mb_strlen($reason) < 5) {
            $errors['reason'] = 'Reason must be at least 5 characters.';
        } elseif (mb_strlen($reason) > 1000) {
            $errors['reason'] = 'Reason cannot exceed 1,000 characters.';
        }

        if (empty($errors['requested_value'])) {
            $fieldError = $this->validateFieldValue($employee, $fieldName, $requestedValue, $meta);
            if ($fieldError !== null) {
                $errors['requested_value'] = $fieldError;
            }
        }

        $currentRaw = $employee[$fieldName] ?? null;
        $currentNormalized = $currentRaw !== null ? trim((string)$currentRaw) : '';
        if (empty($errors) && $currentNormalized === $requestedValue) {
            $errors['requested_value'] = 'Requested value matches your current record on file.';
        }

        $empId = (int)($employee['id'] ?? 0);
        if (empty($errors) && $empId > 0 && $this->hasPendingDuplicate($empId, $fieldName)) {
            $errors['field_name'] = 'You already have a pending correction request for ' . self::fieldLabel($fieldName) . '.';
        }

        if (!empty($errors)) {
            return ['ok' => false, 'errors' => $errors];
        }

        return [
            'ok'     => true,
            'data'   => [
                'employee_id'     => $empId,
                'field_name'      => $fieldName,
                'current_value'   => $currentNormalized !== '' ? $currentNormalized : null,
                'requested_value' => $requestedValue,
                'reason'          => $reason,
            ],
            'errors' => [],
        ];
    }

    /**
     * Persist a new Pending correction request.
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $sql = "
            INSERT INTO profile_correction_requests (
                employee_id,
                requested_by_user_id,
                field_name,
                current_value,
                requested_value,
                reason,
                status,
                created_at,
                updated_at
            ) VALUES (
                :employee_id,
                :requested_by_user_id,
                :field_name,
                :current_value,
                :requested_value,
                :reason,
                :status,
                :created_at,
                :updated_at
            )
        ";

        $this->execute($sql, [
            'employee_id'          => (int)$data['employee_id'],
            'requested_by_user_id' => (int)$data['requested_by_user_id'],
            'field_name'           => (string)$data['field_name'],
            'current_value'        => $data['current_value'] ?? null,
            'requested_value'      => (string)$data['requested_value'],
            'reason'               => (string)$data['reason'],
            'status'               => self::STATUS_PENDING,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        return (int)$this->lastInsertId();
    }

    /**
     * Allow an employee to cancel their own still-Pending correction request.
     */
    public function cancelByEmployee(int $requestId, int $employeeId): bool
    {
        $stmt = $this->query(
            "UPDATE profile_correction_requests
             SET status = :cancelled,
                 updated_at = :updated_at
             WHERE id = :id
               AND employee_id = :emp_id
               AND status = :pending",
            [
                'cancelled'  => self::STATUS_CANCELLED,
                'updated_at' => date('Y-m-d H:i:s'),
                'id'         => $requestId,
                'emp_id'     => $employeeId,
                'pending'    => self::STATUS_PENDING,
            ]
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * Atomically approve or reject a Pending profile correction request inside a DB transaction.
     * On approval, updates the employee record through validated field rules, appends an
     * EmploymentHistory record for organizational changes, logs audit details, and queues
     * an employee notification.
     *
     * @return array{ok: bool, error?: string, status?: string, request?: array<string, mixed>}
     */
    public function review(
        int $requestId,
        string $decision,
        int $reviewerUserId,
        ?string $decisionReason = null
    ): array {
        if (!in_array($decision, [self::STATUS_APPROVED, self::STATUS_REJECTED], true)) {
            return ['ok' => false, 'error' => 'Invalid correction review decision.'];
        }

        $reason = trim((string)$decisionReason);
        if ($decision === self::STATUS_REJECTED && $reason === '') {
            return ['ok' => false, 'error' => 'Please provide a reason when rejecting a correction request.'];
        }
        if (mb_strlen($reason) > 1000) {
            return ['ok' => false, 'error' => 'Decision notes cannot exceed 1,000 characters.'];
        }

        $ownTx = !$this->db->inTransaction();
        if ($ownTx) {
            $this->beginTransaction();
        }

        try {
            $request = $this->findById($requestId);
            if ($request === null) {
                if ($ownTx && $this->db->inTransaction()) {
                    $this->rollBack();
                }
                return ['ok' => false, 'error' => 'Profile correction request not found.'];
            }

            if ($request['status'] !== self::STATUS_PENDING) {
                if ($ownTx && $this->db->inTransaction()) {
                    $this->rollBack();
                }
                return ['ok' => false, 'error' => 'This correction request has already been processed (' . $request['status'] . ').'];
            }

            $employeeId = (int)$request['employee_id'];
            $fieldName = (string)$request['field_name'];
            $newValue = (string)$request['requested_value'];
            $allowedFields = self::getAllowedFields();

            if (!isset($allowedFields[$fieldName])) {
                if ($ownTx && $this->db->inTransaction()) {
                    $this->rollBack();
                }
                return ['ok' => false, 'error' => 'The requested profile field is no longer permitted for automated updates.'];
            }

            $empModel = new Employee($this->db);
            $liveEmployee = $empModel->findById($employeeId);
            if ($liveEmployee === null) {
                if ($ownTx && $this->db->inTransaction()) {
                    $this->rollBack();
                }
                return ['ok' => false, 'error' => 'Linked employee record was not found.'];
            }

            $now = date('Y-m-d H:i:s');
            $oldRawValue = $liveEmployee[$fieldName] ?? null;

            if ($decision === self::STATUS_APPROVED) {
                $fieldError = $this->validateFieldValue($liveEmployee, $fieldName, $newValue, $allowedFields[$fieldName]);
                if ($fieldError !== null) {
                    if ($ownTx && $this->db->inTransaction()) {
                        $this->rollBack();
                    }
                    return ['ok' => false, 'error' => $fieldError];
                }

                $persistedVal = $newValue === '' ? null : $newValue;
                if (in_array($fieldName, ['department_id', 'position_id'], true)) {
                    $persistedVal = (int)$newValue;
                }

                $empModel->applyCorrectionField($employeeId, $fieldName, $persistedVal);

                // Keep login email synchronized only when HR explicitly approves a Work Email change
                if ($fieldName === 'email' && $persistedVal !== null) {
                    $stmtUserSync = $this->db->prepare(
                        "UPDATE users SET email = :email WHERE employee_id = :emp_id"
                    );
                    $stmtUserSync->execute([
                        'email'  => strtolower((string)$persistedVal),
                        'emp_id' => $employeeId,
                    ]);
                }

                // Append immutable employment history record if organizational assignment/type changed
                $this->recordHistoryForApprovedCorrection(
                    $liveEmployee,
                    $fieldName,
                    $persistedVal,
                    $reviewerUserId,
                    $requestId,
                    (string)$request['reason']
                );
            }

            $updateStmt = $this->db->prepare(
                "UPDATE profile_correction_requests
                 SET status = :new_status,
                     reviewed_by = :reviewed_by,
                     reviewed_at = :reviewed_at,
                     decision_reason = :decision_reason,
                     updated_at = :updated_at
                 WHERE id = :id AND status = :pending_status"
            );
            $updateStmt->execute([
                'new_status'      => $decision,
                'reviewed_by'     => $reviewerUserId,
                'reviewed_at'     => $now,
                'decision_reason' => $reason !== '' ? $reason : null,
                'updated_at'      => $now,
                'id'              => $requestId,
                'pending_status'  => self::STATUS_PENDING,
            ]);

            if ($updateStmt->rowCount() !== 1) {
                if ($ownTx && $this->db->inTransaction()) {
                    $this->rollBack();
                }
                return ['ok' => false, 'error' => 'Concurrent update detected. Please refresh and try again.'];
            }

            // Record audit trail inside transaction safely
            $label = self::fieldLabel($fieldName);
            AuditService::getInstance($this->db)->record([
                'actor_user_id' => $reviewerUserId,
                'action'        => $decision === self::STATUS_APPROVED ? 'PROFILE_CORRECTION_APPROVED' : 'PROFILE_CORRECTION_REJECTED',
                'category'      => AuditService::CATEGORY_EMPLOYEE,
                'description'   => ($decision === self::STATUS_APPROVED ? 'Approved' : 'Rejected') . " profile correction #{$requestId} ({$label}) for {$liveEmployee['employee_no']}",
                'entity_type'   => 'PROFILE_CORRECTION_REQUEST',
                'entity_id'     => (string)$requestId,
                'old_values'    => [$fieldName => $oldRawValue],
                'new_values'    => $decision === self::STATUS_APPROVED
                    ? [$fieldName => $newValue, 'decision' => $decision]
                    : ['decision' => $decision, 'decision_reason' => $reason],
            ]);

            // Notify employee via in-app notifications safely (works cleanly in MySQL & SQLite)
            $this->dispatchDecisionNotification(
                (int)$request['requested_by_user_id'],
                $requestId,
                $fieldName,
                $decision,
                $reason
            );

            if ($ownTx) {
                $this->commit();
            }

            return [
                'ok'      => true,
                'status'  => $decision,
                'request' => $this->findById($requestId) ?? $request,
            ];
        } catch (\Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) {
                $this->rollBack();
            }
            error_log('[ProfileCorrectionRequest::review] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'An error occurred while processing the correction request.'];
        }
    }

    /**
     * Record an official EmploymentHistory milestone when an approved correction changes career fields.
     */
    private function recordHistoryForApprovedCorrection(
        array $liveEmployee,
        string $fieldName,
        mixed $newValue,
        int $reviewerUserId,
        int $requestId,
        string $requestReason
    ): void {
        if (!in_array($fieldName, ['department_id', 'position_id', 'employment_type', 'date_hired'], true)) {
            return;
        }

        $eventType = match ($fieldName) {
            'department_id'   => EmploymentHistory::EVENT_DEPT_TRANSFER,
            'position_id'     => EmploymentHistory::EVENT_POSITION_CHANGE,
            'employment_type' => EmploymentHistory::EVENT_TYPE_CHANGE,
            'date_hired'      => EmploymentHistory::EVENT_INITIAL_HIRING,
            default           => EmploymentHistory::EVENT_OTHER,
        };

        $historyModel = new EmploymentHistory($this->db);
        $historyModel->recordEvent([
            'employee_id'            => (int)$liveEmployee['id'],
            'event_type'             => $eventType,
            'effective_date'         => $fieldName === 'date_hired' ? (string)$newValue : date('Y-m-d'),
            'previous_department_id' => !empty($liveEmployee['department_id']) ? (int)$liveEmployee['department_id'] : null,
            'new_department_id'      => $fieldName === 'department_id'
                ? (int)$newValue
                : (!empty($liveEmployee['department_id']) ? (int)$liveEmployee['department_id'] : null),
            'previous_position_id'   => !empty($liveEmployee['position_id']) ? (int)$liveEmployee['position_id'] : null,
            'new_position_id'        => $fieldName === 'position_id'
                ? (int)$newValue
                : (!empty($liveEmployee['position_id']) ? (int)$liveEmployee['position_id'] : null),
            'employment_type'        => $fieldName === 'employment_type'
                ? (string)$newValue
                : ($liveEmployee['employment_type'] ?? null),
            'description'            => "Updated via approved correction request #{$requestId}: {$requestReason}",
            'recorded_by'            => $reviewerUserId,
        ]);
    }

    /**
     * Insert an in-system notification using parameterized SQL compatible with MySQL & SQLite.
     */
    private function dispatchDecisionNotification(
        int $recipientUserId,
        int $requestId,
        string $fieldName,
        string $decision,
        string $reason
    ): void {
        if ($recipientUserId <= 0) {
            return;
        }

        $label = self::fieldLabel($fieldName);
        $approved = $decision === self::STATUS_APPROVED;
        $title = $approved ? 'Profile Correction Approved' : 'Profile Correction Rejected';
        $message = $approved
            ? "Your profile correction request for {$label} has been approved and applied."
            : "Your profile correction request for {$label} was not approved. Reason: {$reason}";

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO notifications (user_id, title, message, type, event_type, entity_id, is_read, created_at)
                 VALUES (:user_id, :title, :message, :type, :event_type, :entity_id, 0, :created_at)"
            );
            $stmt->execute([
                'user_id'    => $recipientUserId,
                'title'      => $title,
                'message'    => $message,
                'type'       => $approved ? 'success' : 'warning',
                'event_type' => $approved
                    ? \App\Realtime\EventAuthorizer::CORRECTION_APPROVED
                    : \App\Realtime\EventAuthorizer::CORRECTION_REJECTED,
                'entity_id'  => $requestId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('[ProfileCorrectionRequest::dispatchDecisionNotification] ' . $e->getMessage());
        }
    }

    /**
     * Validate requested correction value against field-specific business & referential rules.
     *
     * @param array<string, mixed> $employee
     * @param array<string, mixed> $meta
     */
    private function validateFieldValue(array $employee, string $fieldName, string $value, array $meta): ?string
    {
        $maxLen = (int)($meta['maxLength'] ?? 0);
        if ($maxLen > 0 && mb_strlen($value) > $maxLen) {
            return self::fieldLabel($fieldName) . " cannot exceed {$maxLen} characters.";
        }

        if ($fieldName === 'email') {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return 'Please enter a valid work email address.';
            }
            $empModel = new Employee($this->db);
            if (!$empModel->isEmailUnique($value, (int)($employee['id'] ?? 0))) {
                return 'That email address is already assigned to another employee.';
            }
            return null;
        }

        if ($fieldName === 'birth_date' || $fieldName === 'date_hired') {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$parsed || $parsed->format('Y-m-d') !== $value) {
                return 'Please enter a valid date in YYYY-MM-DD format.';
            }
            $today = new DateTimeImmutable('today');
            if ($parsed > $today) {
                return self::fieldLabel($fieldName) . ' cannot be in the future.';
            }
            return null;
        }

        if (isset($meta['options']) && is_array($meta['options'])) {
            if (!in_array($value, $meta['options'], true)) {
                return 'Please select a valid option for ' . self::fieldLabel($fieldName) . '.';
            }
            return null;
        }

        if ($fieldName === 'department_id') {
            $deptId = (int)$value;
            $exists = $deptId > 0 && (int)$this->fetchColumn("SELECT COUNT(*) FROM departments WHERE id = :id", ['id' => $deptId]) > 0;
            if (!$exists) {
                return 'Please select a valid department.';
            }
            return null;
        }

        if ($fieldName === 'position_id') {
            $posId = (int)$value;
            $pos = $posId > 0
                ? $this->fetchOne("SELECT id, department_id FROM positions WHERE id = :id LIMIT 1", ['id' => $posId])
                : null;
            if (!$pos) {
                return 'Please select a valid job position.';
            }
            if (!empty($employee['department_id']) && (int)$pos['department_id'] !== (int)$employee['department_id']) {
                return 'Selected position belongs to a different department. Request a Department correction first or coordinate with HR.';
            }
            return null;
        }

        return null;
    }

    /**
     * Resolve human-readable display strings (e.g. department/position names) on fetched rows.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function enrichRowsWithDisplayValues(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $deptMap = [];
        $posMap = [];
        try {
            foreach ($this->fetchAll("SELECT id, department_name FROM departments") as $d) {
                $deptMap[(string)$d['id']] = (string)$d['department_name'];
            }
            foreach ($this->fetchAll("SELECT id, position_name FROM positions") as $p) {
                $posMap[(string)$p['id']] = (string)$p['position_name'];
            }
        } catch (\Throwable $e) {
            // Leave maps empty if lookup tables are unavailable
        }

        foreach ($rows as &$row) {
            $field = (string)($row['field_name'] ?? '');
            $row['field_label'] = self::fieldLabel($field);
            $row['current_display'] = $this->formatDisplay($field, $row['current_value'] ?? null, $deptMap, $posMap);
            $row['requested_display'] = $this->formatDisplay($field, $row['requested_value'] ?? null, $deptMap, $posMap);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, string> $deptMap
     * @param array<string, string> $posMap
     */
    private function formatDisplay(string $field, mixed $raw, array $deptMap, array $posMap): string
    {
        $val = trim((string)($raw ?? ''));
        if ($val === '') {
            return 'Not specified';
        }

        if ($field === 'department_id') {
            return $deptMap[$val] ?? "Department #{$val}";
        }
        if ($field === 'position_id') {
            return $posMap[$val] ?? "Position #{$val}";
        }
        if ($field === 'birth_date' || $field === 'date_hired') {
            return \App\Services\EmployeeTenureCalculator::formatDate($val, $val);
        }

        return $val;
    }
}
