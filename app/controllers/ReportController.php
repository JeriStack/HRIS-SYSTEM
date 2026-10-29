<?php
/**
 * Reports & Organizational Analytics Controller
 * Provides operational workforce summaries, employee directories, and leave analytics
 * Employee HRIS & Leave Management System — Phase 13
 */

namespace App\Controllers;

use Core\Controller;
use Core\Database;
use PDO;

class ReportController extends Controller
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Display Unified Reports Hub (HR & Admin Access)
     * Route: GET /reports
     */
    public function index(): void
    {
        // 1. Department workforce distribution
        $deptDistribution = $this->pdo->query("
            SELECT d.id, d.department_name, 
                   COUNT(e.id) as employee_count,
                   SUM(CASE WHEN e.employment_status = 'Active' THEN 1 ELSE 0 END) as active_count,
                   SUM(CASE WHEN e.employment_status != 'Active' THEN 1 ELSE 0 END) as inactive_count
            FROM departments d
            LEFT JOIN employees e ON d.id = e.department_id
            GROUP BY d.id, d.department_name
            ORDER BY employee_count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 2. Leave utilization summary by leave type
        $leaveSummary = $this->pdo->query("
            SELECT lt.id, lt.leave_name, lt.default_days,
                   COUNT(lr.id) as total_requests,
                   SUM(CASE WHEN lr.status = 'Approved' THEN lr.number_of_days ELSE 0 END) as approved_days,
                   SUM(CASE WHEN lr.status = 'Pending' THEN 1 ELSE 0 END) as pending_requests
            FROM leave_types lt
            LEFT JOIN leave_requests lr ON lt.id = lr.leave_type_id
            GROUP BY lt.id, lt.leave_name, lt.default_days
            ORDER BY lt.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 3. Totals
        $totalEmployees = (int)$this->pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status = 'Active'")->fetchColumn();
        $totalLeavesApproved = (int)$this->pdo->query("SELECT COALESCE(SUM(number_of_days), 0) FROM leave_requests WHERE status = 'Approved'")->fetchColumn();

        $this->view('reports.index', [
            'pageTitle'           => 'Workforce Reports & Analytics',
            'activeReportTab'     => 'overview',
            'deptDistribution'    => $deptDistribution,
            'leaveSummary'        => $leaveSummary,
            'totalEmployees'      => $totalEmployees,
            'totalLeavesApproved' => $totalLeavesApproved,
            'user'                => currentUser(),
        ], 'hr');
    }

    /**
     * Employee Directory & Headcount Report (HR & Admin Access)
     * Route: GET /reports/employees
     */
    public function employees(): void
    {
        $deptId    = !empty($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
        $posId     = !empty($_GET['pos_id']) ? (int)$_GET['pos_id'] : null;
        $status    = !empty($_GET['status']) ? trim((string)$_GET['status']) : null;
        $search    = !empty($_GET['q']) ? trim((string)$_GET['q']) : null;
        $startDate = !empty($_GET['start_date']) ? trim((string)$_GET['start_date']) : null;
        $endDate   = !empty($_GET['end_date']) ? trim((string)$_GET['end_date']) : null;

        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        // Build filtered query with parameters
        $where = ["1=1"];
        $params = [];

        if (!empty($deptId)) {
            $where[] = "e.department_id = :dept_id";
            $params['dept_id'] = $deptId;
        }

        if (!empty($posId)) {
            $where[] = "e.position_id = :pos_id";
            $params['pos_id'] = $posId;
        }

        if (!empty($status)) {
            $where[] = "e.employment_status = :status";
            $params['status'] = $status;
        }

        if (!empty($search)) {
            $where[] = "(e.first_name LIKE :q OR e.last_name LIKE :q OR e.employee_no LIKE :q OR e.email LIKE :q)";
            $params['q'] = "%{$search}%";
        }

        if (!empty($startDate)) {
            $where[] = "e.date_hired >= :start_date";
            $params['start_date'] = $startDate;
        }

        if (!empty($endDate)) {
            $where[] = "e.date_hired <= :end_date";
            $params['end_date'] = $endDate;
        }

        $whereClause = implode(" AND ", $where);

        // Count total matching
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM employees e WHERE {$whereClause}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $limit));

        // Fetch paginated rows
        $querySql = "
            SELECT e.*, d.department_name, p.position_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE {$whereClause}
            ORDER BY e.id DESC
            LIMIT :offset, :limit
        ";
        $stmt = $this->pdo->prepare($querySql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":{$k}", $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Summary Aggregates
        $statusCounts = $this->pdo->query("
            SELECT employment_status, COUNT(*) as count 
            FROM employees 
            GROUP BY employment_status
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        $departments = $this->pdo->query("SELECT id, department_name FROM departments ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $positions   = $this->pdo->query("SELECT id, position_name FROM positions ORDER BY position_name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $this->view('reports.employee', [
            'pageTitle'       => 'Employee Workforce Report',
            'activeReportTab' => 'employees',
            'employees'       => $employees,
            'departments'     => $departments,
            'positions'       => $positions,
            'statusCounts'    => $statusCounts,
            'totalRows'       => $totalRows,
            'currentPage'     => $page,
            'totalPages'      => $totalPages,
            'filters'         => [
                'dept_id'    => $deptId,
                'pos_id'     => $posId,
                'status'     => $status,
                'q'          => $search,
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ],
            'user'            => currentUser(),
        ], 'hr');
    }

    /**
     * Leave Applications & Utilization Report (HR & Admin Access)
     * Route: GET /reports/leaves
     */
    public function leaves(): void
    {
        $status      = !empty($_GET['status']) ? trim((string)$_GET['status']) : null;
        $leaveTypeId = !empty($_GET['leave_type_id']) ? (int)$_GET['leave_type_id'] : null;
        $deptId      = !empty($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
        $empId       = !empty($_GET['emp_id']) ? (int)$_GET['emp_id'] : null;
        $startDate   = !empty($_GET['start_date']) ? trim((string)$_GET['start_date']) : null;
        $endDate     = !empty($_GET['end_date']) ? trim((string)$_GET['end_date']) : null;

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $where = ["1=1"];
        $params = [];

        if (!empty($status)) {
            $where[] = "lr.status = :status";
            $params['status'] = $status;
        }

        if (!empty($leaveTypeId)) {
            $where[] = "lr.leave_type_id = :leave_type_id";
            $params['leave_type_id'] = $leaveTypeId;
        }

        if (!empty($deptId)) {
            $where[] = "e.department_id = :dept_id";
            $params['dept_id'] = $deptId;
        }

        if (!empty($empId)) {
            $where[] = "lr.employee_id = :emp_id";
            $params['emp_id'] = $empId;
        }

        if (!empty($startDate)) {
            $where[] = "lr.start_date >= :start_date";
            $params['start_date'] = $startDate;
        }

        if (!empty($endDate)) {
            $where[] = "lr.end_date <= :end_date";
            $params['end_date'] = $endDate;
        }

        $whereClause = implode(" AND ", $where);

        // Count total matching
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM leave_requests lr
            INNER JOIN employees e ON lr.employee_id = e.id
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $limit));

        // Fetch records
        $querySql = "
            SELECT lr.*, lt.leave_name, e.employee_no, e.first_name, e.last_name, d.department_name,
                   u.username as reviewer_username
            FROM leave_requests lr
            INNER JOIN employees e ON lr.employee_id = e.id
            INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN users u ON lr.approved_by = u.id
            WHERE {$whereClause}
            ORDER BY lr.id DESC
            LIMIT :offset, :limit
        ";
        $stmt = $this->pdo->prepare($querySql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":{$k}", $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $leaveRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Overall Utilization Aggregates
        $aggregates = $this->pdo->query("
            SELECT 
                COUNT(*) as total_count,
                SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'Approved' THEN number_of_days ELSE 0 END) as approved_days,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'Pending' THEN number_of_days ELSE 0 END) as pending_days,
                SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count,
                SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_count
            FROM leave_requests
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        // Utilization breakdown by leave classification
        $typeUtilization = $this->pdo->query("
            SELECT lt.id, lt.leave_name, lt.default_days,
                   COUNT(lr.id) as request_count,
                   COALESCE(SUM(CASE WHEN lr.status = 'Approved' THEN lr.number_of_days ELSE 0 END), 0) as approved_days
            FROM leave_types lt
            LEFT JOIN leave_requests lr ON lt.id = lr.leave_type_id
            GROUP BY lt.id, lt.leave_name, lt.default_days
            ORDER BY lt.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $leaveTypes  = $this->pdo->query("SELECT id, leave_name FROM leave_types ORDER BY leave_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $departments = $this->pdo->query("SELECT id, department_name FROM departments ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $this->view('reports.leave', [
            'pageTitle'       => 'Leave Utilization Report',
            'activeReportTab' => 'leaves',
            'leaveRecords'    => $leaveRecords,
            'aggregates'      => $aggregates,
            'typeUtilization' => $typeUtilization,
            'leaveTypes'      => $leaveTypes,
            'departments'     => $departments,
            'totalRows'       => $totalRows,
            'currentPage'     => $page,
            'totalPages'      => $totalPages,
            'filters'         => [
                'status'        => $status,
                'leave_type_id' => $leaveTypeId,
                'dept_id'       => $deptId,
                'emp_id'        => $empId,
                'start_date'    => $startDate,
                'end_date'      => $endDate,
            ],
            'user'            => currentUser(),
        ], 'hr');
    }

    /**
     * Department Directory & Distribution Report (HR & Admin Access)
     * Route: GET /reports/departments
     */
    public function departments(): void
    {
        // 1. Department Summary with Headcount and Leave Utilization
        $departmentSummaries = $this->pdo->query("
            SELECT 
                d.id, 
                d.department_name, 
                d.description,
                COUNT(DISTINCT e.id) as total_employees,
                SUM(CASE WHEN e.employment_status = 'Active' THEN 1 ELSE 0 END) as active_employees,
                SUM(CASE WHEN e.employment_status != 'Active' AND e.id IS NOT NULL THEN 1 ELSE 0 END) as inactive_employees,
                COUNT(DISTINCT p.id) as total_positions,
                COUNT(DISTINCT lr.id) as total_leave_requests,
                COALESCE(SUM(CASE WHEN lr.status = 'Approved' THEN lr.number_of_days ELSE 0 END), 0) as approved_leave_days
            FROM departments d
            LEFT JOIN employees e ON d.id = e.department_id
            LEFT JOIN positions p ON d.id = p.department_id
            LEFT JOIN leave_requests lr ON e.id = lr.employee_id
            GROUP BY d.id, d.department_name, d.description
            ORDER BY total_employees DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 2. Organization Totals
        $totalHeadcount = (int)$this->pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
        $totalActive    = (int)$this->pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status = 'Active'")->fetchColumn();
        $totalInactive  = $totalHeadcount - $totalActive;
        $totalLeaveDays = (int)$this->pdo->query("SELECT COALESCE(SUM(number_of_days), 0) FROM leave_requests WHERE status = 'Approved'")->fetchColumn();

        $this->view('reports.department', [
            'pageTitle'           => 'Department Workforce Report',
            'activeReportTab'     => 'departments',
            'departmentSummaries' => $departmentSummaries,
            'totalHeadcount'      => $totalHeadcount,
            'totalActive'         => $totalActive,
            'totalInactive'       => $totalInactive,
            'totalLeaveDays'      => $totalLeaveDays,
            'user'                => currentUser(),
        ], 'hr');
    }
}
