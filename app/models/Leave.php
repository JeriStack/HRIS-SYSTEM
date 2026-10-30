<?php
/**
 * Leave Management Model
 * Handles leave requests, transaction-safe approvals, leave balances, leave types, and overlap detection
 * Employee HRIS & Leave Management System - Phase 8 & Phase 9
 */

namespace App\Models;

use Core\Database;
use App\Services\LeaveCalculator;
use App\Realtime\EventAuthorizer;
use PDO;
use Exception;
use RuntimeException;

class Leave
{
    private PDO $pdo;
    private ?string $lastError = null;
    private ?string $lastErrorCode = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    /**
     * Get the last safe user-facing error message from a failed operation.
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Get the last machine-readable error code from a failed operation.
     */
    public function getLastErrorCode(): ?string
    {
        return $this->lastErrorCode;
    }

    /**
     * Retrieve leave requests with joined employee, department, and leave type data.
     * Supports filtering by status, employee, date range, and department.
     */
    public function getAllRequests(
        ?string $status = null, 
        ?int $empId = null, 
        ?int $limit = 100,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $deptId = null
    ): array {
        $sql = "
            SELECT lr.*, 
                   e.employee_no, e.first_name, e.last_name, e.email,
                   d.department_name,
                   lt.leave_name, lt.description as leave_description,
                   u.username as reviewer_username
            FROM leave_requests lr
            INNER JOIN employees e ON lr.employee_id = e.id
            INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN users u ON lr.approved_by = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($status && in_array($status, ['Pending', 'Approved', 'Rejected', 'Cancelled'], true)) {
            $sql .= " AND lr.status = :status";
            $params['status'] = $status;
        }

        if ($empId) {
            $sql .= " AND lr.employee_id = :emp_id";
            $params['emp_id'] = $empId;
        }

        if ($startDate) {
            $sql .= " AND lr.end_date >= :start_filter";
            $params['start_filter'] = $startDate;
        }

        if ($endDate) {
            $sql .= " AND lr.start_date <= :end_filter";
            $params['end_filter'] = $endDate;
        }

        if ($deptId) {
            $sql .= " AND e.department_id = :dept_id";
            $params['dept_id'] = $deptId;
        }

        $sql .= " ORDER BY lr.id DESC";

        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a single leave request by ID with complete relationship data.
     */
    public function getRequestById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT lr.*, 
                   e.employee_no, e.first_name, e.last_name, e.email,
                   d.department_name,
                   lt.leave_name, lt.description as leave_description,
                   u.username as reviewer_username
            FROM leave_requests lr
            INNER JOIN employees e ON lr.employee_id = e.id
            INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN users u ON lr.approved_by = u.id
            WHERE lr.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get all active leave types (for employee dropdowns).
     */
    public function getLeaveTypes(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM leave_types WHERE status = 'Active' ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all leave types (both Active and Inactive) for HR administration with usage statistics.
     */
    public function getAllLeaveTypes(?string $status = null): array
    {
        $sql = "
            SELECT lt.*, 
                   (SELECT COUNT(*) FROM leave_requests WHERE leave_type_id = lt.id) as request_count,
                   (SELECT COUNT(*) FROM leave_balances WHERE leave_type_id = lt.id) as balance_count
            FROM leave_types lt
            WHERE 1=1
        ";
        $params = [];
        if ($status && in_array($status, ['Active', 'Inactive'], true)) {
            $sql .= " AND lt.status = :status";
            $params['status'] = $status;
        }
        $sql .= " ORDER BY lt.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find leave type by ID.
     */
    public function getLeaveTypeById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM leave_types WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Check if a leave type name is unique across records (excluding self on update).
     */
    public function isLeaveTypeNameUnique(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM leave_types WHERE LOWER(leave_name) = LOWER(:name)";
        $params = ['name' => trim($name)];
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() === false;
    }

    /**
     * Create a new leave type configuration.
     */
    public function createLeaveType(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO leave_types (leave_name, description, default_days, status)
            VALUES (:name, :desc, :days, :status)
        ");
        $stmt->execute([
            'name'   => trim($data['leave_name']),
            'desc'   => !empty($data['description']) ? trim($data['description']) : null,
            'days'   => (int)($data['default_days'] ?? 15),
            'status' => in_array($data['status'] ?? 'Active', ['Active', 'Inactive'], true) ? $data['status'] : 'Active',
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update an existing leave type configuration.
     */
    public function updateLeaveType(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE leave_types
            SET leave_name = :name,
                description = :desc,
                default_days = :days,
                status = :status
            WHERE id = :id
        ");
        return $stmt->execute([
            'name'   => trim($data['leave_name']),
            'desc'   => !empty($data['description']) ? trim($data['description']) : null,
            'days'   => (int)($data['default_days'] ?? 15),
            'status' => in_array($data['status'] ?? 'Active', ['Active', 'Inactive'], true) ? $data['status'] : 'Active',
            'id'     => $id,
        ]);
    }

    /**
     * Toggle leave type status between Active and Inactive (Soft deactivate/activate).
     * Preserves all historical leave requests and existing balances.
     */
    public function toggleLeaveTypeStatus(int $id): bool
    {
        $type = $this->getLeaveTypeById($id);
        if (!$type) {
            return false;
        }
        $newStatus = ($type['status'] === 'Active') ? 'Inactive' : 'Active';
        $stmt = $this->pdo->prepare("UPDATE leave_types SET status = :status WHERE id = :id");
        return $stmt->execute(['status' => $newStatus, 'id' => $id]);
    }

    /**
     * Detect overlapping leave requests for the same employee.
     * Evaluates inclusive dates: (new_start <= existing_end AND new_end >= existing_start).
     * Considers Pending and Approved requests; ignores Rejected and Cancelled.
     */
    public function getOverlappingRequest(
        int $employeeId, 
        string $startDate, 
        string $endDate, 
        ?int $excludeRequestId = null
    ): ?array {
        $sql = "
            SELECT lr.*, lt.leave_name
            FROM leave_requests lr
            INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = :emp_id
              AND lr.status IN ('Pending', 'Approved')
              AND lr.start_date <= :new_end
              AND lr.end_date >= :new_start
        ";
        $params = [
            'emp_id'    => $employeeId,
            'new_start' => $startDate,
            'new_end'   => $endDate,
        ];

        if ($excludeRequestId) {
            $sql .= " AND lr.id != :exclude_id";
            $params['exclude_id'] = $excludeRequestId;
        }

        $sql .= " ORDER BY lr.id DESC LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Check if an overlapping leave request exists for an employee.
     */
    public function hasOverlap(
        int $employeeId, 
        string $startDate, 
        string $endDate, 
        ?int $excludeRequestId = null
    ): bool {
        return $this->getOverlappingRequest($employeeId, $startDate, $endDate, $excludeRequestId) !== null;
    }

    /**
     * Get single leave balance record.
     */
    public function getBalance(int $empId, int $leaveTypeId, int $year): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM leave_balances
            WHERE employee_id = :emp_id AND leave_type_id = :lt_id AND year = :year
            LIMIT 1
        ");
        $stmt->execute(['emp_id' => $empId, 'lt_id' => $leaveTypeId, 'year' => $year]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get leave balances for a specific employee and year.
     */
    public function getBalancesByEmployee(int $empId, ?int $year = null): array
    {
        $year = $year ?: (int)date('Y');
        $stmt = $this->pdo->prepare("
            SELECT lb.*, lt.leave_name, lt.description
            FROM leave_balances lb
            INNER JOIN leave_types lt ON lb.leave_type_id = lt.id
            WHERE lb.employee_id = :emp_id AND lb.year = :year
            ORDER BY lt.id ASC
        ");
        $stmt->execute(['emp_id' => $empId, 'year' => $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get pending requested days for a specific leave type and year (for reservation checks).
     */
    public function getPendingDays(int $employeeId, int $leaveTypeId, int $year): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(number_of_days), 0) as total_pending
            FROM leave_requests
            WHERE employee_id = :emp_id
              AND leave_type_id = :lt_id
              AND YEAR(start_date) = :year
              AND status = 'Pending'
        ");
        $stmt->execute([
            'emp_id' => $employeeId,
            'lt_id'  => $leaveTypeId,
            'year'   => $year,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total_pending'] ?? 0);
    }

    /**
     * Initialize missing balance quotas for an employee for a specific year.
     * Inserts default quotas from active leave types.
     */
    public function initializeBalancesForEmployee(int $employeeId, int $year): int
    {
        $leaveTypes = $this->getLeaveTypes();
        $count = 0;
        foreach ($leaveTypes as $lt) {
            $existing = $this->getBalance($employeeId, (int)$lt['id'], $year);
            if (!$existing) {
                $days = (int)$lt['default_days'];
                $stmt = $this->pdo->prepare("
                    INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, remaining_days)
                    VALUES (:emp_id, :lt_id, :year, :alloc, 0, :rem)
                ");
                $stmt->execute([
                    'emp_id' => $employeeId,
                    'lt_id'  => (int)$lt['id'],
                    'year'   => $year,
                    'alloc'  => $days,
                    'rem'    => $days,
                ]);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Adjust an employee's allocated leave days for a specific leave type and year.
     * Recomputes remaining_days = GREATEST(0, allocated_days - used_days).
     */
    public function adjustBalance(int $employeeId, int $leaveTypeId, int $year, int $allocatedDays): bool
    {
        $existing = $this->getBalance($employeeId, $leaveTypeId, $year);
        if ($existing) {
            $used = (int)$existing['used_days'];
            $newRemaining = max(0, $allocatedDays - $used);
            $stmt = $this->pdo->prepare("
                UPDATE leave_balances
                SET allocated_days = :alloc,
                    remaining_days = :rem
                WHERE id = :id
            ");
            return $stmt->execute([
                'alloc' => $allocatedDays,
                'rem'   => $newRemaining,
                'id'    => (int)$existing['id'],
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, remaining_days)
                VALUES (:emp_id, :lt_id, :year, :alloc, 0, :rem)
            ");
            return $stmt->execute([
                'emp_id' => $employeeId,
                'lt_id'  => $leaveTypeId,
                'year'   => $year,
                'alloc'  => $allocatedDays,
                'rem'    => $allocatedDays,
            ]);
        }
    }

    /**
     * Get all workforce balances across departments.
     */
    public function getAllBalances(?int $year = null, ?int $deptId = null): array
    {
        $year = $year ?: (int)date('Y');
        $sql = "
            SELECT lb.*, e.employee_no, e.first_name, e.last_name, d.department_name, lt.leave_name
            FROM leave_balances lb
            INNER JOIN employees e ON lb.employee_id = e.id
            INNER JOIN leave_types lt ON lb.leave_type_id = lt.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE lb.year = :year
        ";
        $params = ['year' => $year];

        if ($deptId) {
            $sql .= " AND e.department_id = :dept_id";
            $params['dept_id'] = $deptId;
        }

        $sql .= " ORDER BY e.last_name ASC, lt.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new leave request with authoritative calculated days.
     *
     * When $withinTransaction is given, the insert and the callback (e.g. writing
     * HR notifications) run in one transaction: both commit or neither does.
     *
     * @param callable(int $requestId): void|null $withinTransaction
     * @throws \Throwable when the transactional path fails (after rollback)
     */
    public function createRequest(array $data, ?callable $withinTransaction = null): int
    {
        if ($withinTransaction === null) {
            return $this->insertRequest($data);
        }

        $this->pdo->beginTransaction();
        try {
            $requestId = $this->insertRequest($data);
            $withinTransaction($requestId);
            $this->pdo->commit();
            return $requestId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Insert a Pending leave request row.
     */
    private function insertRequest(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, number_of_days, reason, status)
            VALUES (:employee_id, :leave_type_id, :start_date, :end_date, :number_of_days, :reason, 'Pending')
        ");
        $stmt->execute([
            'employee_id'    => (int)$data['employee_id'],
            'leave_type_id'  => (int)$data['leave_type_id'],
            'start_date'     => $data['start_date'],
            'end_date'       => $data['end_date'],
            'number_of_days' => (int)$data['number_of_days'],
            'reason'         => trim($data['reason']),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Approve a leave request in a single atomic ACID database transaction.
     *
     * Transaction sequence:
     *  1. Verify & lock the acting HR user account (Active, role = 'hr').
     *  2. Load & lock the leave request row (SELECT ... FOR UPDATE).
     *  3. Verify the request exists and its status is still 'Pending'.
     *  4. Load & lock the employee row; verify Active status & prevent self-approval.
     *  5. Load & lock the leave type row; verify Active status.
     *  6. Revalidate request dates, working day duration, leave-year boundary, and overlap.
     *  7. Lock & re-check the applicable leave balance row (remaining_days >= number_of_days).
     *  8. Update leave_requests status to 'Approved' (assert rowCount() === 1).
     *  9. Update leave_balances.used_days (assert rowCount() === 1).
     * 10. Update leave_balances.remaining_days and verify balance invariant.
     * 11. Create the required activity_logs audit record.
     * 12. Create the required persistent notification record for the employee.
     * 13. Commit the transaction.
     * 14. Trigger optional post-commit notification delivery (failures logged, never roll back).
     *
     * @param int $id Leave request ID
     * @param int $reviewerUserId Authenticated HR user ID
     * @param callable(array $request, string $status): void|null $withinTransaction Optional pre-commit callback
     * @param callable(string $step, array $context): void|null $stepHook Optional hook invoked after each write step
     * @param callable(array $result): void|null $postCommit Optional post-commit delivery callback
     * @return array{ok: bool, code: string, http_status: int, message: string, data?: array}
     */
    public function approveRequest(
        int $id,
        int $reviewerUserId,
        ?callable $withinTransaction = null,
        ?callable $stepHook = null,
        ?callable $postCommit = null
    ): array {
        $this->lastError = null;
        $this->lastErrorCode = null;

        if ($id <= 0) {
            return $this->failResult('request_not_found', 404, "Leave request #{$id} does not exist.");
        }

        if ($reviewerUserId <= 0) {
            return $this->failResult('unauthorized_reviewer', 403, 'A valid authenticated HR reviewer is required.');
        }

        if ($this->pdo->inTransaction()) {
            return $this->failResult('nested_transaction', 500, 'Cannot start leave approval inside an already active database transaction.');
        }

        try {
            if (!$this->pdo->beginTransaction()) {
                return $this->failResult('transaction_start_failed', 500, 'Unable to start database transaction.');
            }
        } catch (\Throwable $e) {
            error_log('[Leave::approveRequest] Failed to begin transaction: ' . $e->getMessage());
            return $this->failResult('transaction_start_failed', 500, 'Unable to start database transaction.');
        }

        try {
            // 1. Verify and lock acting HR reviewer account
            $stmtReviewer = $this->pdo->prepare("
                SELECT id, employee_id, username, role, status
                FROM users
                WHERE id = :uid
                LIMIT 1
                FOR UPDATE
            ");
            $stmtReviewer->execute(['uid' => $reviewerUserId]);
            $reviewer = $stmtReviewer->fetch(PDO::FETCH_ASSOC);

            if (
                !$reviewer
                || ($reviewer['status'] ?? '') !== 'Active'
                || strtolower((string)($reviewer['role'] ?? '')) !== 'hr'
            ) {
                $this->safeRollBack();
                return $this->failResult('unauthorized_reviewer', 403, 'Approving user is not an active HR account.');
            }

            // 2. Load and lock the leave request row
            $stmtReq = $this->pdo->prepare("
                SELECT *
                FROM leave_requests
                WHERE id = :id
                LIMIT 1
                FOR UPDATE
            ");
            $stmtReq->execute(['id' => $id]);
            $lockedRequest = $stmtReq->fetch(PDO::FETCH_ASSOC);

            if (!$lockedRequest) {
                $this->safeRollBack();
                return $this->failResult('request_not_found', 404, "Leave request #{$id} does not exist.");
            }

            // 3. Verify request status is still Pending
            if (($lockedRequest['status'] ?? '') !== 'Pending') {
                $currentStatus = (string)($lockedRequest['status'] ?? 'Unknown');
                $this->safeRollBack();
                return $this->failResult(
                    'not_pending',
                    400,
                    "Cannot approve request #{$id}: Request has already been processed (Current Status: {$currentStatus})."
                );
            }

            $empId = (int)$lockedRequest['employee_id'];
            $leaveTypeId = (int)$lockedRequest['leave_type_id'];
            $days = (int)$lockedRequest['number_of_days'];
            $startDate = (string)$lockedRequest['start_date'];
            $endDate = (string)$lockedRequest['end_date'];

            // Segregation of duties: prevent self-approval
            if (!empty($reviewer['employee_id']) && (int)$reviewer['employee_id'] === $empId) {
                $this->safeRollBack();
                return $this->failResult('self_approval_forbidden', 403, 'HR personnel cannot approve their own leave request.');
            }

            // 4. Load and lock employee record; verify active eligibility
            $stmtEmp = $this->pdo->prepare("
                SELECT id, employee_no, first_name, last_name, email, department_id, employment_status
                FROM employees
                WHERE id = :emp_id
                LIMIT 1
                FOR UPDATE
            ");
            $stmtEmp->execute(['emp_id' => $empId]);
            $employee = $stmtEmp->fetch(PDO::FETCH_ASSOC);

            if (!$employee || ($employee['employment_status'] ?? '') !== 'Active') {
                $this->safeRollBack();
                return $this->failResult(
                    'employee_ineligible',
                    400,
                    "Cannot approve request #{$id}: Employee record is inactive or ineligible."
                );
            }

            // Verify employee's linked user account exists and is Active (required for persistent notification)
            $stmtOwnerUser = $this->pdo->prepare("
                SELECT id, username, status
                FROM users
                WHERE employee_id = :emp_id
                LIMIT 1
                FOR UPDATE
            ");
            $stmtOwnerUser->execute(['emp_id' => $empId]);
            $ownerUser = $stmtOwnerUser->fetch(PDO::FETCH_ASSOC);

            if ($ownerUser && ($ownerUser['status'] ?? '') !== 'Active') {
                $this->safeRollBack();
                return $this->failResult(
                    'employee_ineligible',
                    400,
                    "Cannot approve request #{$id}: Employee user account is inactive."
                );
            }

            // 5. Load and lock leave type; verify Active status
            $stmtType = $this->pdo->prepare("
                SELECT id, leave_name, description, default_days, status
                FROM leave_types
                WHERE id = :lt_id
                LIMIT 1
                FOR UPDATE
            ");
            $stmtType->execute(['lt_id' => $leaveTypeId]);
            $leaveType = $stmtType->fetch(PDO::FETCH_ASSOC);

            if (!$leaveType || ($leaveType['status'] ?? '') !== 'Active') {
                $this->safeRollBack();
                return $this->failResult(
                    'leave_type_invalid',
                    400,
                    "Cannot approve request #{$id}: Leave classification is invalid or inactive."
                );
            }

            // 6. Revalidate dates, duration, leave-year boundaries, and overlap
            $rangeValidation = LeaveCalculator::validateDateRange($startDate, $endDate);
            if (!$rangeValidation['valid'] || (int)$rangeValidation['working_days'] <= 0) {
                $this->safeRollBack();
                return $this->failResult(
                    'invalid_dates',
                    400,
                    $rangeValidation['error'] ?? 'Leave request contains an invalid date range or zero working days.'
                );
            }

            if ($days <= 0 || $days !== (int)$rangeValidation['working_days']) {
                $this->safeRollBack();
                return $this->failResult(
                    'invalid_duration',
                    400,
                    "Cannot approve request #{$id}: Recorded duration ({$days} days) does not match calculated working days ({$rangeValidation['working_days']} days)."
                );
            }

            $startYear = (int)substr($startDate, 0, 4);
            $endYear = (int)substr($endDate, 0, 4);
            if ($startYear !== $endYear) {
                $this->safeRollBack();
                return $this->failResult(
                    'cross_year_boundary',
                    400,
                    "Cannot approve request #{$id}: Leave request spans multiple leave years ({$startYear} to {$endYear})."
                );
            }
            $year = $startYear;

            // Lock and check for overlapping Approved requests for the same employee
            $stmtOverlap = $this->pdo->prepare("
                SELECT id, start_date, end_date, status
                FROM leave_requests
                WHERE employee_id = :emp_id
                  AND id != :exclude_id
                  AND status = 'Approved'
                  AND start_date <= :new_end
                  AND end_date >= :new_start
                LIMIT 1
                FOR UPDATE
            ");
            $stmtOverlap->execute([
                'emp_id'     => $empId,
                'exclude_id' => $id,
                'new_start'  => $startDate,
                'new_end'    => $endDate,
            ]);
            $overlappingApproved = $stmtOverlap->fetch(PDO::FETCH_ASSOC);
            if ($overlappingApproved) {
                $this->safeRollBack();
                return $this->failResult(
                    'date_overlap',
                    400,
                    "Cannot approve request #{$id}: Schedule overlaps with already approved leave request #{$overlappingApproved['id']}."
                );
            }

            // 7. Lock and re-check the applicable leave balance
            $stmtLockBal = $this->pdo->prepare("
                SELECT *
                FROM leave_balances
                WHERE employee_id = :emp_id AND leave_type_id = :lt_id AND year = :year
                LIMIT 1
                FOR UPDATE
            ");
            $stmtLockBal->execute([
                'emp_id' => $empId,
                'lt_id'  => $leaveTypeId,
                'year'   => $year,
            ]);
            $balance = $stmtLockBal->fetch(PDO::FETCH_ASSOC);

            if (!$balance) {
                // Auto-initialize annual balance inside the transaction only when the employee has no
                // balance rows at all for that year (consistent with LeaveController::store policy).
                $stmtYearCount = $this->pdo->prepare("
                    SELECT COUNT(*) FROM leave_balances WHERE employee_id = :emp_id AND year = :year
                ");
                $stmtYearCount->execute(['emp_id' => $empId, 'year' => $year]);
                $existingYearCount = (int)$stmtYearCount->fetchColumn();

                if ($existingYearCount === 0 && (int)$leaveType['default_days'] > 0) {
                    $defaultDays = (int)$leaveType['default_days'];
                    $stmtInitBal = $this->pdo->prepare("
                        INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, remaining_days)
                        VALUES (:emp_id, :lt_id, :year, :alloc, 0, :rem)
                    ");
                    $stmtInitBal->execute([
                        'emp_id' => $empId,
                        'lt_id'  => $leaveTypeId,
                        'year'   => $year,
                        'alloc'  => $defaultDays,
                        'rem'    => $defaultDays,
                    ]);

                    $stmtLockBal->execute([
                        'emp_id' => $empId,
                        'lt_id'  => $leaveTypeId,
                        'year'   => $year,
                    ]);
                    $balance = $stmtLockBal->fetch(PDO::FETCH_ASSOC);
                }
            }

            if (!$balance) {
                $this->safeRollBack();
                return $this->failResult(
                    'balance_not_found',
                    400,
                    "Cannot approve request #{$id}: No leave balance account exists for {$leaveType['leave_name']} in {$year}."
                );
            }

            $balanceId = (int)$balance['id'];
            $allocatedDays = (int)$balance['allocated_days'];
            $previousUsedDays = (int)$balance['used_days'];
            $previousRemainingDays = (int)$balance['remaining_days'];

            if (
                $previousRemainingDays < $days
                || ($allocatedDays - $previousUsedDays) < $days
                || ($previousUsedDays + $days) > $allocatedDays
            ) {
                $this->safeRollBack();
                return $this->failResult(
                    'insufficient_balance',
                    400,
                    "Insufficient leave balance: Request requires {$days} day(s), but only {$previousRemainingDays} day(s) remain."
                );
            }

            // Enrich request array for downstream callbacks/notifications
            $enrichedRequest = array_merge($lockedRequest, [
                'employee_no'       => $employee['employee_no'],
                'first_name'        => $employee['first_name'],
                'last_name'         => $employee['last_name'],
                'email'             => $employee['email'],
                'leave_name'        => $leaveType['leave_name'],
                'leave_description' => $leaveType['description'],
                'reviewer_username' => $reviewer['username'],
            ]);

            // 8. Update the leave request status to Approved
            $stmtUpdateReq = $this->pdo->prepare("
                UPDATE leave_requests
                SET status = 'Approved',
                    approved_by = :approved_by,
                    approved_at = NOW(),
                    rejection_reason = NULL
                WHERE id = :id AND status = 'Pending'
            ");
            $stmtUpdateReq->execute([
                'approved_by' => $reviewerUserId,
                'id'          => $id,
            ]);

            if ($stmtUpdateReq->rowCount() !== 1) {
                throw new RuntimeException('Failed to update leave request status to Approved.');
            }

            if ($stepHook !== null) {
                $stepHook('after_request_status_update', ['request_id' => $id, 'balance_id' => $balanceId]);
            }

            // 9. Record used leave days
            $stmtUpdateUsed = $this->pdo->prepare("
                UPDATE leave_balances
                SET used_days = used_days + :days
                WHERE id = :balance_id
                  AND used_days = :expected_used
                  AND (allocated_days - (used_days + :days_check)) >= 0
            ");
            $stmtUpdateUsed->execute([
                'days'          => $days,
                'balance_id'    => $balanceId,
                'expected_used' => $previousUsedDays,
                'days_check'    => $days,
            ]);

            if ($stmtUpdateUsed->rowCount() !== 1) {
                throw new RuntimeException('Failed to record used leave days in leave_balances.');
            }

            if ($stepHook !== null) {
                $stepHook('after_used_days_update', ['request_id' => $id, 'balance_id' => $balanceId]);
            }

            // 10. Update the remaining leave balance
            $stmtUpdateRem = $this->pdo->prepare("
                UPDATE leave_balances
                SET remaining_days = remaining_days - :days
                WHERE id = :balance_id
                  AND remaining_days = :expected_rem
                  AND remaining_days >= :days_check
                  AND (remaining_days - :days_calc) = (allocated_days - used_days)
            ");
            $stmtUpdateRem->execute([
                'days'         => $days,
                'balance_id'   => $balanceId,
                'expected_rem' => $previousRemainingDays,
                'days_check'   => $days,
                'days_calc'    => $days,
            ]);

            if ($stmtUpdateRem->rowCount() !== 1) {
                throw new RuntimeException('Failed to update remaining leave balance in leave_balances.');
            }

            // Verify updated balance invariants inside the transaction
            $stmtVerifyBal = $this->pdo->prepare("
                SELECT allocated_days, used_days, remaining_days
                FROM leave_balances
                WHERE id = :balance_id
                LIMIT 1
            ");
            $stmtVerifyBal->execute(['balance_id' => $balanceId]);
            $updatedBalance = $stmtVerifyBal->fetch(PDO::FETCH_ASSOC);

            if (
                !$updatedBalance
                || (int)$updatedBalance['used_days'] !== ($previousUsedDays + $days)
                || (int)$updatedBalance['remaining_days'] !== ($previousRemainingDays - $days)
                || (int)$updatedBalance['remaining_days'] < 0
            ) {
                throw new RuntimeException('Leave balance invariant verification failed after update.');
            }

            if ($stepHook !== null) {
                $stepHook('after_remaining_balance_update', ['request_id' => $id, 'balance_id' => $balanceId]);
            }

            // 11. Create required activity log inside the transaction
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 255);
            $logDescription = "Approved leave request #{$id} (Pending -> Approved, {$days} days of {$leaveType['leave_name']}) for {$employee['first_name']} {$employee['last_name']} (Employee #{$empId})";

            $stmtLog = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description, entity_type, entity_id, ip_address, user_agent, created_at)
                VALUES (:user_id, 'LEAVE_APPROVED', :description, 'LeaveRequest', :entity_id, :ip_address, :user_agent, NOW())
            ");
            $stmtLog->execute([
                'user_id'     => $reviewerUserId,
                'description' => $logDescription,
                'entity_id'   => (string)$id,
                'ip_address'  => $ip,
                'user_agent'  => $agent,
            ]);

            $activityLogId = (int)$this->pdo->lastInsertId();
            if ($stmtLog->rowCount() !== 1 || $activityLogId <= 0) {
                throw new RuntimeException('Failed to create activity log entry for leave approval.');
            }

            if ($stepHook !== null) {
                $stepHook('after_activity_log_insert', ['request_id' => $id, 'activity_log_id' => $activityLogId]);
            }

            // 12. Create required persistent notification inside the transaction
            $stmtNotifBefore = $this->pdo->prepare("
                SELECT COUNT(*) FROM notifications
                WHERE entity_id = :req_id AND event_type = :evt
            ");
            $stmtNotifBefore->execute([
                'req_id' => $id,
                'evt'    => EventAuthorizer::LEAVE_APPROVED,
            ]);
            $notifCountBefore = (int)$stmtNotifBefore->fetchColumn();

            if ($withinTransaction !== null) {
                $withinTransaction($enrichedRequest, 'Approved');
            }

            $stmtNotifAfter = $this->pdo->prepare("
                SELECT id, user_id FROM notifications
                WHERE entity_id = :req_id AND event_type = :evt
                ORDER BY id DESC
            ");
            $stmtNotifAfter->execute([
                'req_id' => $id,
                'evt'    => EventAuthorizer::LEAVE_APPROVED,
            ]);
            $notifRowsAfter = $stmtNotifAfter->fetchAll(PDO::FETCH_ASSOC);

            if (count($notifRowsAfter) > $notifCountBefore) {
                $notificationId = (int)$notifRowsAfter[0]['id'];
                if ($ownerUser && (int)$notifRowsAfter[0]['user_id'] !== (int)$ownerUser['id']) {
                    throw new RuntimeException('Approval notification was written for a non-owning user.');
                }
            } else {
                if (!$ownerUser || ($ownerUser['status'] ?? '') !== 'Active') {
                    throw new RuntimeException('Cannot create approval notification: Employee does not have an active user account.');
                }

                $notifMessage = "Your leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT)
                    . " ({$days} days for {$leaveType['leave_name']}, {$startDate} to {$endDate}) was approved by HR.";

                $stmtNotif = $this->pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type, event_type, entity_id, is_read, created_at)
                    VALUES (:user_id, 'Leave Request Approved', :message, 'success', :event_type, :entity_id, 0, NOW())
                ");
                $stmtNotif->execute([
                    'user_id'    => (int)$ownerUser['id'],
                    'message'    => $notifMessage,
                    'event_type' => EventAuthorizer::LEAVE_APPROVED,
                    'entity_id'  => $id,
                ]);

                $notificationId = (int)$this->pdo->lastInsertId();
                if ($stmtNotif->rowCount() !== 1 || $notificationId <= 0) {
                    throw new RuntimeException('Failed to create persistent notification for leave approval.');
                }
            }

            if ($stepHook !== null) {
                $stepHook('after_notification_insert', ['request_id' => $id, 'notification_id' => $notificationId]);
            }

            // 13. Commit transaction after all required operations succeed
            if (!$this->pdo->commit()) {
                throw new RuntimeException('Database transaction commit returned false.');
            }

            $resultData = [
                'request_id'       => $id,
                'employee_id'      => $empId,
                'leave_type_id'    => $leaveTypeId,
                'year'             => $year,
                'approved_days'    => $days,
                'used_days'        => (int)$updatedBalance['used_days'],
                'remaining_days'   => (int)$updatedBalance['remaining_days'],
                'activity_log_id'  => $activityLogId,
                'notification_id'  => $notificationId,
                'approved_by'      => $reviewerUserId,
            ];

            // 14. Post-commit notification delivery (never rolls back committed approval)
            if ($postCommit !== null) {
                try {
                    $postCommit($resultData);
                } catch (\Throwable $deliveryError) {
                    error_log('[Leave::approveRequest] Post-commit notification delivery failed for request #' . $id . ': ' . $deliveryError->getMessage());
                }
            }

            return [
                'ok'          => true,
                'code'        => 'approved',
                'http_status' => 200,
                'message'     => "Leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . " approved and employee balance updated.",
                'data'        => $resultData,
            ];
        } catch (\Throwable $e) {
            $this->safeRollBack();
            error_log('Leave status update error: ' . $e->getMessage());
            return $this->failResult(
                'transaction_failed',
                500,
                "Failed to approve leave request #{$id} due to a processing error. No changes were saved."
            );
        }
    }

    /**
     * Reject a pending leave request in an atomic transaction.
     * Persists rejection reason, audit log, and employee notification without deducting leave balance.
     *
     * @param callable(array $request, string $status): void|null $withinTransaction
     * @return array{ok: bool, code: string, http_status: int, message: string, data?: array}
     */
    public function rejectRequest(
        int $id,
        int $reviewerUserId,
        ?string $rejectionReason = null,
        ?callable $withinTransaction = null
    ): array {
        $this->lastError = null;
        $this->lastErrorCode = null;

        if ($id <= 0) {
            return $this->failResult('request_not_found', 404, "Leave request #{$id} does not exist.");
        }

        if ($reviewerUserId <= 0) {
            return $this->failResult('unauthorized_reviewer', 403, 'A valid authenticated HR reviewer is required.');
        }

        if ($this->pdo->inTransaction()) {
            return $this->failResult('nested_transaction', 500, 'Cannot start leave rejection inside an already active database transaction.');
        }

        try {
            if (!$this->pdo->beginTransaction()) {
                return $this->failResult('transaction_start_failed', 500, 'Unable to start database transaction.');
            }
        } catch (\Throwable $e) {
            error_log('[Leave::rejectRequest] Failed to begin transaction: ' . $e->getMessage());
            return $this->failResult('transaction_start_failed', 500, 'Unable to start database transaction.');
        }

        try {
            $stmtReviewer = $this->pdo->prepare("
                SELECT id, employee_id, username, role, status
                FROM users
                WHERE id = :uid
                LIMIT 1
                FOR UPDATE
            ");
            $stmtReviewer->execute(['uid' => $reviewerUserId]);
            $reviewer = $stmtReviewer->fetch(PDO::FETCH_ASSOC);

            if (
                !$reviewer
                || ($reviewer['status'] ?? '') !== 'Active'
                || strtolower((string)($reviewer['role'] ?? '')) !== 'hr'
            ) {
                $this->safeRollBack();
                return $this->failResult('unauthorized_reviewer', 403, 'Reviewing user is not an active HR account.');
            }

            $stmtReq = $this->pdo->prepare("
                SELECT lr.*, e.employee_no, e.first_name, e.last_name, e.email, lt.leave_name, lt.description AS leave_description
                FROM leave_requests lr
                INNER JOIN employees e ON lr.employee_id = e.id
                INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
                WHERE lr.id = :id
                LIMIT 1
                FOR UPDATE
            ");
            $stmtReq->execute(['id' => $id]);
            $request = $stmtReq->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                $this->safeRollBack();
                return $this->failResult('request_not_found', 404, "Leave request #{$id} does not exist.");
            }

            if (($request['status'] ?? '') !== 'Pending') {
                $currentStatus = (string)($request['status'] ?? 'Unknown');
                $this->safeRollBack();
                return $this->failResult(
                    'not_pending',
                    400,
                    "Cannot reject request #{$id}: Request has already been processed (Current Status: {$currentStatus})."
                );
            }

            $cleanReason = (!empty($rejectionReason) && trim($rejectionReason) !== '')
                ? trim($rejectionReason)
                : null;

            $stmt = $this->pdo->prepare("
                UPDATE leave_requests
                SET status = 'Rejected',
                    approved_by = :approved_by,
                    approved_at = NOW(),
                    rejection_reason = :rejection_reason
                WHERE id = :id AND status = 'Pending'
            ");
            $stmt->execute([
                'approved_by'      => $reviewerUserId,
                'rejection_reason' => $cleanReason,
                'id'               => $id,
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Failed to update leave request status to Rejected.');
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 255);
            $reasonDesc = $cleanReason ?? 'No reason specified';
            $logDescription = "Rejected leave request #{$id} for {$request['first_name']} {$request['last_name']}. Reason: {$reasonDesc}";

            $stmtLog = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description, entity_type, entity_id, ip_address, user_agent, created_at)
                VALUES (:user_id, 'LEAVE_REJECTED', :description, 'LeaveRequest', :entity_id, :ip_address, :user_agent, NOW())
            ");
            $stmtLog->execute([
                'user_id'     => $reviewerUserId,
                'description' => $logDescription,
                'entity_id'   => (string)$id,
                'ip_address'  => $ip,
                'user_agent'  => $agent,
            ]);

            if ($stmtLog->rowCount() !== 1) {
                throw new RuntimeException('Failed to create activity log entry for leave rejection.');
            }

            $stmtNotifBefore = $this->pdo->prepare("
                SELECT COUNT(*) FROM notifications
                WHERE entity_id = :req_id AND event_type = :evt
            ");
            $stmtNotifBefore->execute([
                'req_id' => $id,
                'evt'    => EventAuthorizer::LEAVE_REJECTED,
            ]);
            $notifCountBefore = (int)$stmtNotifBefore->fetchColumn();

            if ($withinTransaction !== null) {
                $withinTransaction($request, 'Rejected');
            }

            $stmtNotifAfter = $this->pdo->prepare("
                SELECT COUNT(*) FROM notifications
                WHERE entity_id = :req_id AND event_type = :evt
            ");
            $stmtNotifAfter->execute([
                'req_id' => $id,
                'evt'    => EventAuthorizer::LEAVE_REJECTED,
            ]);
            $notifCountAfter = (int)$stmtNotifAfter->fetchColumn();

            if ($notifCountAfter <= $notifCountBefore) {
                $stmtOwnerUser = $this->pdo->prepare("
                    SELECT id FROM users WHERE employee_id = :emp_id AND status = 'Active' LIMIT 1
                ");
                $stmtOwnerUser->execute(['emp_id' => (int)$request['employee_id']]);
                $ownerUserId = (int)$stmtOwnerUser->fetchColumn();

                if ($ownerUserId > 0) {
                    $notifMessage = "Your leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT)
                        . " was rejected." . ($cleanReason ? " Reason: {$cleanReason}" : '');
                    $stmtNotif = $this->pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type, event_type, entity_id, is_read, created_at)
                        VALUES (:user_id, 'Leave Request Rejected', :message, 'warning', :event_type, :entity_id, 0, NOW())
                    ");
                    $stmtNotif->execute([
                        'user_id'    => $ownerUserId,
                        'message'    => $notifMessage,
                        'event_type' => EventAuthorizer::LEAVE_REJECTED,
                        'entity_id'  => $id,
                    ]);
                }
            }

            if (!$this->pdo->commit()) {
                throw new RuntimeException('Database transaction commit returned false.');
            }

            return [
                'ok'          => true,
                'code'        => 'rejected',
                'http_status' => 200,
                'message'     => "Leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . " was rejected. Entitlement remains untouched.",
            ];
        } catch (\Throwable $e) {
            $this->safeRollBack();
            error_log('Leave status update error: ' . $e->getMessage());
            return $this->failResult('transaction_failed', 500, "Failed to reject leave request #{$id}.");
        }
    }

    /**
     * Update request status (Approve or Reject) in an atomic transaction.
     * Delegates to approveRequest() or rejectRequest() so all callers get
     * full row locking, balance re-verification, audit logging, and notifications.
     *
     * @param callable(array $request, string $status): void|null $withinTransaction
     *        Runs before COMMIT; throwing rolls back the whole decision.
     */
    public function updateRequestStatus(
        int $id, 
        string $status, 
        int $reviewerUserId, 
        ?string $rejectionReason = null,
        ?callable $withinTransaction = null
    ): bool {
        if ($status === 'Approved') {
            $res = $this->approveRequest($id, $reviewerUserId, $withinTransaction);
            return $res['ok'];
        }

        if ($status === 'Rejected') {
            $res = $this->rejectRequest($id, $reviewerUserId, $rejectionReason, $withinTransaction);
            return $res['ok'];
        }

        $this->failResult('invalid_status', 400, "Unsupported leave status transition '{$status}'.");
        return false;
    }

    /**
     * Safely roll back an active transaction without masking the original error.
     */
    private function safeRollBack(): void
    {
        try {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        } catch (\Throwable $rbError) {
            error_log('[Leave::safeRollBack] Rollback error: ' . $rbError->getMessage());
        }
    }

    /**
     * Build a standardized failure result array and record lastError / lastErrorCode.
     *
     * @return array{ok: false, code: string, http_status: int, message: string}
     */
    private function failResult(string $code, int $httpStatus, string $message): array
    {
        $this->lastErrorCode = $code;
        $this->lastError = $message;

        return [
            'ok'          => false,
            'code'        => $code,
            'http_status' => $httpStatus,
            'message'     => $message,
        ];
    }

    /**
     * Cancel an employee's own pending leave request.
     * Rejects attempts to cancel non-pending or other employees' requests.
     */
    public function cancelRequest(int $id, int $empId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE leave_requests
            SET status = 'Cancelled'
            WHERE id = :id AND employee_id = :emp_id AND status = 'Pending'
        ");
        $stmt->execute(['id' => $id, 'emp_id' => $empId]);
        return $stmt->rowCount() > 0;
    }
}
