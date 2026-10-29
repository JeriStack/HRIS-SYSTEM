<?php
/**
 * Dashboard & Protected Resource Demonstrator Controller
 * Enforces role boundaries and employee record ownership verification
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use Core\Controller;
use Core\Database;
use PDO;

class DashboardController extends Controller
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Administrator & HR Dashboard
     * Route: GET /hr/dashboard
     * Access: Administrator / HR Only
     */
    public function hrDashboard(): void
    {
        // Fetch real aggregated metrics from the database
        $metrics = [
            'total_employees'  => (int)$this->pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status = 'Active'")->fetchColumn(),
            'total_departments' => (int)$this->pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn(),
            'pending_leaves'   => (int)$this->pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'")->fetchColumn(),
            'total_positions'  => (int)$this->pdo->query("SELECT COUNT(*) FROM positions")->fetchColumn(),
        ];

        // Fetch recent active employees
        $recentEmployees = $this->pdo->query("
            SELECT e.employee_no, e.first_name, e.last_name, e.email, d.department_name, p.position_name, e.date_hired
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE e.employment_status = 'Active'
            ORDER BY e.id DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        $this->view('dashboard.hr', [
            'pageTitle'       => 'HR Dashboard - Employee HRIS',
            'user'            => currentUser(),
            'metrics'         => $metrics,
            'recentEmployees' => $recentEmployees,
        ], 'hr');
    }

    /**
     * HR Employee Directory (Protected HR-Only Endpoint)
     * Route: GET /hr/employees
     * Access: Administrator / HR Only
     */
    public function hrEmployees(): void
    {
        $employees = $this->pdo->query("
            SELECT e.id, e.employee_no, e.first_name, e.last_name, e.email, d.department_name, p.position_name, e.employment_status
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            ORDER BY e.last_name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            $this->json([
                'status'    => 'success',
                'role'      => currentUserRole(),
                'count'     => count($employees),
                'employees' => $employees,
            ]);
            return;
        }

        // Output demonstrator view
        $this->view('dashboard.hr_employees', [
            'pageTitle' => 'Employee Directory (HR Access) - Employee HRIS',
            'user'      => currentUser(),
            'employees' => $employees,
        ], null);
    }

    /**
     * Employee Self-Service Dashboard
     * Route: GET /employee/dashboard
     * Access: Employee Only
     */
    public function employeeDashboard(): void
    {
        $user = currentUser();
        $employeeId = $user['employee_id'] ?? null;

        $balances = [];
        $pendingLeaves = [];

        if ($employeeId) {
            // Fetch employee's own leave balances
            $stmtBalances = $this->pdo->prepare("
                SELECT lb.allocated_days, lb.used_days, lb.remaining_days, lt.leave_name
                FROM leave_balances lb
                INNER JOIN leave_types lt ON lb.leave_type_id = lt.id
                WHERE lb.employee_id = :emp_id AND lb.year = YEAR(CURDATE())
            ");
            $stmtBalances->execute(['emp_id' => $employeeId]);
            $balances = $stmtBalances->fetchAll(PDO::FETCH_ASSOC);

            // Fetch employee's own recent leave requests
            $stmtLeaves = $this->pdo->prepare("
                SELECT lr.id, lr.start_date, lr.end_date, lr.number_of_days, lr.status, lr.reason, lt.leave_name
                FROM leave_requests lr
                INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
                WHERE lr.employee_id = :emp_id
                ORDER BY lr.id DESC
                LIMIT 5
            ");
            $stmtLeaves->execute(['emp_id' => $employeeId]);
            $pendingLeaves = $stmtLeaves->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->view('dashboard.employee', [
            'pageTitle'     => 'Employee Self-Service Portal - Employee HRIS',
            'user'          => $user,
            'balances'      => $balances,
            'pendingLeaves' => $pendingLeaves,
        ], 'hr');
    }

    /**
     * Employee Own Profile (Enforces Strict Ownership Verification)
     * Route: GET /employee/profile
     * Access: Employee Only (Restricted strictly to own record)
     */
    public function employeeProfile(): void
    {
        $user = currentUser();
        $myEmpId = $user['employee_id'] ?? null;

        // Security Check: Detect if user attempted to tamper with query parameter
        if (isset($_GET['emp_id'])) {
            $requestedEmpId = (int)$_GET['emp_id'];
            if ($requestedEmpId !== $myEmpId) {
                http_response_code(403);
                $this->view('errors.403', [
                    'pageTitle' => '403 Forbidden - Ownership Violation',
                    'message'   => 'Ownership Access Denied: You are not authorized to view another employee\'s private records (NFR-SEC-004).'
                ], null);
                return;
            }
        }

        // Fetch employee's own record from database
        $stmt = $this->pdo->prepare("
            SELECT e.*, d.department_name, p.position_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE e.id = :emp_id
            LIMIT 1
        ");
        $stmt->execute(['emp_id' => $myEmpId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            $this->json([
                'status'  => 'success',
                'profile' => $profile,
            ]);
            return;
        }

        $this->view('dashboard.employee_profile', [
            'pageTitle' => 'My Personal Profile - Employee HRIS',
            'user'      => $user,
            'profile'   => $profile,
        ], 'hr');
    }

    /**
     * Employee Own Leave Balances (Enforces Strict Ownership)
     * Route: GET /employee/leave-balances
     * Access: Employee Only
     */
    public function employeeLeaveBalances(): void
    {
        $user = currentUser();
        $myEmpId = $user['employee_id'] ?? null;

        // Ownership enforcement against parameter spoofing
        if (isset($_GET['emp_id']) && (int)$_GET['emp_id'] !== $myEmpId) {
            http_response_code(403);
            $this->view('errors.403', [
                'pageTitle' => '403 Forbidden - Ownership Violation',
                'message'   => 'Ownership Access Denied: You cannot inspect another employee\'s leave balances.'
            ], null);
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT lb.*, lt.leave_name, lt.description
            FROM leave_balances lb
            INNER JOIN leave_types lt ON lb.leave_type_id = lt.id
            WHERE lb.employee_id = :emp_id AND lb.year = YEAR(CURDATE())
        ");
        $stmt->execute(['emp_id' => $myEmpId]);
        $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Render full HTML view when requested by browser
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') && !isset($_GET['format'])) {
            $this->view('dashboard.employee_balances', [
                'pageTitle' => 'My Leave Balances - Employee Self-Service',
                'user'      => $user,
                'balances'  => $balances,
                'year'      => (int)date('Y'),
            ], 'hr');
            return;
        }

        $this->json([
            'status'      => 'success',
            'employee_no' => $user['employee_no'],
            'year'        => (int)date('Y'),
            'balances'    => $balances,
        ]);
    }

    /**
     * Employee Own Leave Requests (Enforces Strict Ownership)
     * Route: GET /employee/leave-requests
     * Access: Employee Only
     */
    public function employeeLeaveRequests(): void
    {
        $user = currentUser();
        $myEmpId = $user['employee_id'] ?? null;

        // Ownership enforcement against parameter spoofing
        if (isset($_GET['emp_id']) && (int)$_GET['emp_id'] !== $myEmpId) {
            http_response_code(403);
            $this->view('errors.403', [
                'pageTitle' => '403 Forbidden - Ownership Violation',
                'message'   => 'Ownership Access Denied: You cannot inspect another employee\'s leave requests.'
            ], null);
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT lr.*, lt.leave_name
            FROM leave_requests lr
            INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = :emp_id
            ORDER BY lr.id DESC
        ");
        $stmt->execute(['emp_id' => $myEmpId]);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Build chronological leave sequence using custom Linked List (Phase 11/13)
        $historyService = new \App\Services\LeaveHistorySequenceService();
        $historyChain = $myEmpId ? $historyService->buildEmployeeLeaveHistoryChain((int)$myEmpId, 25) : new \App\Dsa\Structures\LinkedList();
        $historyArray = $historyService->getHistoryArray($historyChain);

        // Render full HTML view when requested by browser
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') && !isset($_GET['format'])) {
            $this->view('dashboard.employee_requests', [
                'pageTitle'    => 'My Leave Applications - Employee Self-Service',
                'user'         => $user,
                'requests'     => $requests,
                'historyArray' => $historyArray,
            ], 'hr');
            return;
        }

        $this->json([
            'status'      => 'success',
            'employee_no' => $user['employee_no'],
            'requests'    => $requests,
        ]);
    }
}
