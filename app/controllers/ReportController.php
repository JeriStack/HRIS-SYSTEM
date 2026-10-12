<?php
/**
 * Reports & Organizational Analytics Controller
 * Provides operational workforce summaries and leave analytics
 * Employee HRIS & Leave Management System
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
        $totalLeavesApproved = (int)$this->pdo->query("SELECT SUM(number_of_days) FROM leave_requests WHERE status = 'Approved'")->fetchColumn();

        $this->view('reports.index', [
            'pageTitle'           => 'Workforce Reports & Analytics',
            'deptDistribution'    => $deptDistribution,
            'leaveSummary'        => $leaveSummary,
            'totalEmployees'      => $totalEmployees,
            'totalLeavesApproved' => $totalLeavesApproved,
            'user'                => currentUser(),
        ], 'hr');
    }
}
