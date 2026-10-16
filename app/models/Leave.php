<?php
/**
 * Leave Management Model
 * Handles leave requests, approvals, leave balances, leave types, and overlap detection
 * Employee HRIS & Leave Management System - Phase 8
 */

namespace App\Models;

use Core\Database;
use PDO;
use Exception;

class Leave
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
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
     */
    public function createRequest(array $data): int
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
     * Update request status (Approve or Reject) in an atomic transaction.
     * If approved, updates leave balance and verifies overlap.
     * If rejected, persists rejection reason and does not deduct balance.
     */
    public function updateRequestStatus(
        int $id, 
        string $status, 
        int $reviewerUserId, 
        ?string $rejectionReason = null
    ): bool {
        if (!in_array($status, ['Approved', 'Rejected'], true)) {
            return false;
        }

        $request = $this->getRequestById($id);
        if (!$request) {
            return false;
        }

        // Prevent modifying already processed request
        if ($request['status'] !== 'Pending') {
            return false;
        }

        $this->pdo->beginTransaction();

        try {
            // If approving, re-verify overlap against other approved requests
            if ($status === 'Approved') {
                $overlap = $this->getOverlappingRequest(
                    (int)$request['employee_id'],
                    $request['start_date'],
                    $request['end_date'],
                    $id
                );
                // If there's an already approved overlapping request, block approval
                if ($overlap && $overlap['status'] === 'Approved') {
                    $this->pdo->rollBack();
                    return false;
                }
            }

            // 1. Update leave request record
            $stmt = $this->pdo->prepare("
                UPDATE leave_requests
                SET status = :status,
                    approved_by = :approved_by,
                    approved_at = NOW(),
                    rejection_reason = :rejection_reason
                WHERE id = :id AND status = 'Pending'
            ");
            $stmt->execute([
                'status'           => $status,
                'approved_by'      => $reviewerUserId,
                'rejection_reason' => ($status === 'Rejected') ? (!empty($rejectionReason) ? trim($rejectionReason) : null) : null,
                'id'               => $id,
            ]);

            // 2. If approved, update employee leave balance
            if ($status === 'Approved') {
                $days = (int)$request['number_of_days'];
                $empId = (int)$request['employee_id'];
                $leaveTypeId = (int)$request['leave_type_id'];
                $year = (int)date('Y', strtotime($request['start_date']));

                $stmtBal = $this->pdo->prepare("
                    UPDATE leave_balances
                    SET used_days = used_days + :days,
                        remaining_days = GREATEST(0, allocated_days - used_days)
                    WHERE employee_id = :emp_id AND leave_type_id = :lt_id AND year = :year
                ");
                $stmtBal->execute([
                    'days'   => $days,
                    'emp_id' => $empId,
                    'lt_id'  => $leaveTypeId,
                    'year'   => $year,
                ]);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Leave status update error: " . $e->getMessage());
            return false;
        }
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
