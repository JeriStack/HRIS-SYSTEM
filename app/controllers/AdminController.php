<?php
/**
 * Administrator Dashboard Controller
 * Enforces Administrator-Only Access and provides system-wide operational metrics
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use Core\Controller;
use Core\Database;
use App\Models\User;
use PDO;

class AdminController extends Controller
{
    private PDO $pdo;
    private User $userModel;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->userModel = new User();
    }

    /**
     * System Administrator Dashboard
     * Route: GET /admin/dashboard
     * Access: Administrator Only
     */
    public function dashboard(): void
    {
        // 1. Employee status aggregates
        $stmtEmp = $this->pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN employment_status = 'Active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN employment_status = 'Inactive' THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN employment_status = 'Terminated' THEN 1 ELSE 0 END) as `terminated`
            FROM employees
        ");
        $empStats = $stmtEmp->fetch(PDO::FETCH_ASSOC) ?: [
            'total' => 0, 'active' => 0, 'inactive' => 0, 'terminated' => 0
        ];

        // 2. User account aggregates
        $stmtUsers = $this->pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
                SUM(CASE WHEN role = 'hr' THEN 1 ELSE 0 END) as hr_count,
                SUM(CASE WHEN role = 'employee' THEN 1 ELSE 0 END) as employee_count
            FROM users
        ");
        $userStats = $stmtUsers->fetch(PDO::FETCH_ASSOC) ?: [
            'total' => 0, 'active' => 0, 'inactive' => 0, 'admin_count' => 0, 'hr_count' => 0, 'employee_count' => 0
        ];

        // 3. Organization metrics
        $deptCount = (int)$this->pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
        $posCount = (int)$this->pdo->query("SELECT COUNT(*) FROM positions")->fetchColumn();

        // 4. Pending leave requests
        $pendingLeaves = (int)$this->pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'")->fetchColumn();

        // 5. Recent activity logs (audit trail)
        $recentLogs = $this->pdo->query("
            SELECT a.*, u.username, u.role
            FROM activity_logs a
            LEFT JOIN users u ON a.user_id = u.id
            ORDER BY a.id DESC
            LIMIT 8
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 6. Recent user accounts
        $recentUsers = $this->pdo->query("
            SELECT u.id, u.username, u.role, u.status, u.created_at, e.employee_no, e.first_name, e.last_name
            FROM users u
            LEFT JOIN employees e ON u.employee_id = e.id
            ORDER BY u.id DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        $metrics = [
            'employees'      => $empStats,
            'users'          => $userStats,
            'departments'    => $deptCount,
            'positions'      => $posCount,
            'pending_leaves' => $pendingLeaves,
        ];

        $this->view('dashboard.admin', [
            'pageTitle'    => 'Administrator Dashboard - Employee HRIS',
            'user'         => currentUser(),
            'metrics'      => $metrics,
            'recentLogs'   => $recentLogs,
            'recentUsers'  => $recentUsers,
        ], 'hr');
    }
}
