<?php
/**
 * Leave Management Model
 * Handles leave requests, approvals, leave balances, and leave types
 * Employee HRIS & Leave Management System
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
     */
    public function getAllRequests(?string $status = null, ?int $empId = null, ?int $limit = 100): array
    {
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

        $sql .= " ORDER BY lr.id DESC";

        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a single leave request by ID.
     */
    public function getRequestById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT lr.*, 
                   e.employee_no, e.first_name, e.last_name, e.email,
                   d.department_name,
                   lt.leave_name,
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
     * Get all active leave types.
     */
    public function getLeaveTypes(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM leave_types WHERE status = 'Active' ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
     * Create a new leave request.
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
     */
    public function updateRequestStatus(int $id, string $status, int $reviewerUserId): bool
    {
        if (!in_array($status, ['Approved', 'Rejected', 'Cancelled'], true)) {
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
            // 1. Update leave request record
            $stmt = $this->pdo->prepare("
                UPDATE leave_requests
                SET status = :status,
                    approved_by = :approved_by,
                    approved_at = NOW()
                WHERE id = :id AND status = 'Pending'
            ");
            $stmt->execute([
                'status'      => $status,
                'approved_by' => $reviewerUserId,
                'id'          => $id,
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
                        remaining_days = GREATEST(0, allocated_days - (used_days + :days_rem))
                    WHERE employee_id = :emp_id AND leave_type_id = :lt_id AND year = :year
                ");
                $stmtBal->execute([
                    'days'        => $days,
                    'days_rem'    => $days,
                    'emp_id'      => $empId,
                    'lt_id'       => $leaveTypeId,
                    'year'        => $year,
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
     */
    public function cancelRequest(int $id, int $empId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE leave_requests
            SET status = 'Cancelled'
            WHERE id = :id AND employee_id = :emp_id AND status = 'Pending'
        ");
        return $stmt->execute(['id' => $id, 'emp_id' => $empId]);
    }
}
