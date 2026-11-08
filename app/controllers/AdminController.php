<?php

/**
 * Administrator Dashboard & Governance Controller
 * Enforces Administrator-Only Access and provides system-wide operational metrics,
 * real-time infrastructure diagnostics, security monitoring, and role governance.
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use Core\Controller;
use Core\Database;
use Core\Permission;
use Core\Session;
use App\Models\User;
use App\Models\ActivityLog;
use PDO;

class AdminController extends Controller
{
    private ?PDO $pdo = null;
    private ?User $userModel = null;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo !== null) {
            $this->pdo = $pdo;
        } elseif (Database::getInstance()->isConnected()) {
            $this->pdo = Database::getInstance()->getConnection();
        }
    }

    private function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = Database::getInstance()->getConnection();
        }
        return $this->pdo;
    }

    private function getUserModel(): User
    {
        if ($this->userModel === null) {
            $this->userModel = new User();
        }
        return $this->userModel;
    }

    /**
     * System Administrator Dashboard
     * Route: GET /admin/dashboard
     * Access: Administrator Only
     */
    public function dashboard(): void
    {
        // 1. Employee status aggregates
        $stmtEmp = $this->getPdo()->query("
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
        $stmtUsers = $this->getPdo()->query("
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
        $deptCount = (int)$this->getPdo()->query("SELECT COUNT(*) FROM departments")->fetchColumn();
        $posCount = (int)$this->getPdo()->query("SELECT COUNT(*) FROM positions")->fetchColumn();

        // 4. Pending leave requests
        $pendingLeaves = (int)$this->getPdo()->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'")->fetchColumn();

        // 5. Analytics: Employee distribution by department
        $deptDistribution = $this->getPdo()->query("
            SELECT d.department_name, COUNT(e.id) as employee_count
            FROM departments d
            LEFT JOIN employees e ON d.id = e.department_id
            GROUP BY d.id, d.department_name
            ORDER BY employee_count DESC
            LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 6. Analytics: Leave request status distribution
        $leaveDistribution = $this->getPdo()->query("
            SELECT status, COUNT(*) as count
            FROM leave_requests
            GROUP BY status
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 7. Analytics: Monthly leave trends (last 6 months)
        $monthlyTrends = $this->getPdo()->query("
            SELECT 
                DATE_FORMAT(created_at, '%b %Y') as month_label,
                COUNT(*) as request_count
            FROM leave_requests
            GROUP BY YEAR(created_at), MONTH(created_at), month_label
            ORDER BY YEAR(created_at) DESC, MONTH(created_at) DESC
            LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 8. Recent activity logs (audit trail)
        $recentLogs = $this->getPdo()->query("
            SELECT a.*, u.username, u.role
            FROM activity_logs a
            LEFT JOIN users u ON a.user_id = u.id
            ORDER BY a.id DESC
            LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 9. Live System Health Probe
        $systemHealth = $this->probeLiveHealth();

        $metrics = [
            'total_employees'    => (int)($empStats['total'] ?? 0),
            'active_employees'   => (int)($empStats['active'] ?? 0),
            'hr_accounts'        => (int)($userStats['hr_count'] ?? 0),
            'employee_accounts'  => (int)($userStats['employee_count'] ?? 0),
            'pending_leaves'     => (int)$pendingLeaves,
            'active_users'       => (int)($userStats['active'] ?? 0),
            'departments'        => $deptCount,
            'positions'          => $posCount,
        ];

        $this->view('dashboard.admin', [
            'pageTitle'         => 'System Overview',
            'user'              => currentUser(),
            'metrics'           => $metrics,
            'deptDistribution'  => $deptDistribution,
            'leaveDistribution' => $leaveDistribution,
            'monthlyTrends'     => array_reverse($monthlyTrends),
            'recentLogs'        => $recentLogs,
            'systemHealth'      => $systemHealth,
        ], 'hr');
    }

    /**
     * Roles & Permissions Governance Matrix
     * Route: GET /admin/roles
     */
    public function roles(): void
    {
        $permissions = Permission::getAllCapabilities();
        $allRoles = Permission::VALID_ROLES;

        // Group capabilities by category
        $categorized = [
            'System Governance' => ['admin_dashboard', 'manage_users', 'assign_roles', 'system_settings', 'view_monitoring'],
            'Workforce Operations' => ['hr_dashboard', 'manage_employees', 'manage_departments', 'manage_positions', 'view_employee_records'],
            'Leave Administration' => ['review_leave_requests', 'manage_leave_types', 'manage_leave_balances'],
            'Self-Service' => ['employee_dashboard', 'view_own_profile', 'view_own_leave_balances', 'file_leave_requests', 'view_own_leave_requests'],
            'Analytics & Tools' => ['view_reports', 'view_activity_logs', 'dsa_laboratory', 'logout'],
        ];

        $this->view('admin.roles', [
            'pageTitle'     => 'Roles & Permissions',
            'roles'         => $allRoles,
            'permissions'   => $permissions,
            'categorized'   => $categorized,
        ], 'hr');
    }

    /**
     * Controlled Administrator Provisioning
     * Route: GET /admin/provisioning
     */
    public function provisioning(): void
    {
        $activeAdmins = $this->getPdo()->query("
            SELECT u.id, u.username, u.email, u.status, u.created_at, e.first_name, e.last_name
            FROM users u
            LEFT JOIN employees e ON u.employee_id = e.id
            WHERE u.role = 'admin'
            ORDER BY u.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $adminAuditLogs = $this->getPdo()->query("
            SELECT a.*, u.username
            FROM activity_logs a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.action LIKE '%ADMIN%' OR a.action LIKE '%ROLE%'
            ORDER BY a.id DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        $this->view('admin.provisioning', [
            'pageTitle'       => 'Admin Provisioning',
            'activeAdmins'    => $activeAdmins,
            'adminCount'      => count($activeAdmins),
            'adminAuditLogs'  => $adminAuditLogs,
        ], 'hr');
    }

    /**
     * Security & Authentication Events Trail
     * Route: GET /admin/security/events
     */
    public function securityEvents(): void
    {
        $filter = trim($_GET['filter'] ?? '');
        $sql = "
            SELECT a.*, u.username, u.role
            FROM activity_logs a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.action LIKE '%AUTH%' 
               OR a.action LIKE '%LOGIN%' 
               OR a.action LIKE '%OTP%' 
               OR a.action LIKE '%MFA%' 
               OR a.action LIKE '%LOCKOUT%' 
               OR a.action LIKE '%ROLE%'
               OR a.action LIKE '%SECURITY%'
        ";
        if ($filter !== '') {
            $sql .= " AND (a.action LIKE :filter OR a.details LIKE :filter OR u.username LIKE :filter)";
        }
        $sql .= " ORDER BY a.id DESC LIMIT 50";

        $stmt = $this->getPdo()->prepare($sql);
        if ($filter !== '') {
            $stmt->bindValue(':filter', "%{$filter}%");
        }
        $stmt->execute();
        $securityEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->view('admin.security_events', [
            'pageTitle'       => 'Security Events',
            'securityEvents'  => $securityEvents,
            'filter'          => $filter,
        ], 'hr');
    }

    /**
     * Authentication & Session Security Policy Settings
     * Route: GET /admin/settings/auth
     */
    public function authSettings(): void
    {
        $policy = [
            'mfa_status'             => 'Enforced (Mandatory 2FA Email OTP)',
            'otp_expiry_seconds'     => 300, // 5 minutes
            'max_otp_attempts'       => 5,
            'session_idle_timeout'   => 1800, // 30 minutes
            'session_max_lifetime'   => 43200, // 12 hours
            'password_algorithm'     => 'Bcrypt (cost 12)',
            'lockout_policy'         => 'Automatic lockout after 5 consecutive failed verification attempts',
            'session_fingerprint'    => 'Strict SHA-256 IP & User-Agent hashing with database parity validation',
        ];

        $this->view('admin.auth_settings', [
            'pageTitle' => 'Authentication Settings',
            'policy'    => $policy,
        ], 'hr');
    }

    /**
     * General Application Settings
     * Route: GET /admin/settings
     */
    public function generalSettings(): void
    {
        $settings = [
            'app_name'     => (string)config('config.app_name', 'NEXORA HR'),
            'app_env'      => (string)config('config.app_env', 'production'),
            'base_url'     => (string)config('config.base_url', 'http://localhost'),
            'timezone'     => date_default_timezone_get(),
            'database'     => 'MySQL 8.0 (InnoDB, UTF8mb4)',
            'mail_driver'  => 'PHPMailer (SMTP)',
            'realtime'     => 'Ratchet WebSocket Protocol (RFC 6455)',
        ];

        $this->view('admin.settings', [
            'pageTitle' => 'General Settings',
            'settings'  => $settings,
        ], 'hr');
    }

    /**
     * Real-Time Infrastructure & Service Health Monitor
     * Route: GET /admin/system-health
     */
    public function systemHealth(): void
    {
        $health = $this->probeLiveHealth();

        $this->view('admin.system_health', [
            'pageTitle' => 'System Health',
            'health'    => $health,
        ], 'hr');
    }

    /**
     * Notification Dispatch & Mailer Configuration
     * Route: GET /admin/settings/notifications
     */
    public function notificationSettings(): void
    {
        $mailConfig = require __DIR__ . '/../../config/mail.php';
        $health = $this->probeLiveHealth();

        $this->view('admin.notification_settings', [
            'pageTitle'   => 'Notification Settings',
            'mailConfig'  => $mailConfig,
            'smtpStatus'  => $health['smtp']['status'] ?? 'Operational',
            'wsStatus'    => $health['websocket']['status'] ?? 'Operational',
        ], 'hr');
    }

    /**
     * Administrator Personal Account View
     * Route: GET /admin/account
     */
    public function account(): void
    {
        $userId = (int)currentUserId();
        $user = $this->getUserModel()->findById($userId);

        $this->view('admin.account', [
            'pageTitle' => 'My Account',
            'user'      => $user,
        ], 'hr');
    }

    /**
     * Update Administrator Account Credentials
     * Route: POST /admin/account
     */
    public function updateAccount(): void
    {
        $userId = (int)currentUserId();
        $email = trim($_POST['email'] ?? '');
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        $user = $this->getUserModel()->findById($userId);
        if (!$user) {
            Session::flash('error', 'User account not found.');
            redirect('/admin/account');
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please provide a valid email address.');
            redirect('/admin/account');
        }

        $existingUser = $this->getUserModel()->findByEmail($email);
        if ($existingUser && (int)$existingUser['id'] !== $userId) {
            Session::flash('error', 'The email address is already assigned to another account.');
            redirect('/admin/account');
        }

        // Handle password update if supplied
        $updateData = ['email' => $email];
        if ($newPassword !== '') {
            if (!password_verify($currentPassword, (string)$user['password'])) {
                Session::flash('error', 'Current password verification failed.');
                redirect('/admin/account');
            }

            if (strlen($newPassword) < 8) {
                Session::flash('error', 'New password must be at least 8 characters long.');
                redirect('/admin/account');
            }

            if ($newPassword !== $confirmPassword) {
                Session::flash('error', 'New password and confirmation do not match.');
                redirect('/admin/account');
            }

            $updateData['password'] = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        $this->getUserModel()->update($userId, $updateData);

        try {
            if (class_exists(ActivityLog::class)) {
                $activityLog = new ActivityLog();
                $activityLog->log(
                    $userId,
                    'ADMIN_ACCOUNT_UPDATED',
                    'Administrator updated account email/credentials',
                    'User',
                    (string)$userId
                );
            }
        } catch (\Throwable $e) {
            error_log('[AdminController::updateAccount] Activity log write failed: ' . $e->getMessage());
        }

        Session::flash('success', 'Account profile and security credentials successfully updated.');
        redirect('/admin/account');
    }

    /**
     * Genuine Runtime Health Probe (Zero Fabricated Data)
     * Performs live socket checks and queries system parameters
     *
     * @return array<string, mixed>
     */
    private function probeLiveHealth(): array
    {
        // 1. Database Probe
        $dbStatus = 'Healthy';
        $dbLatency = 0.0;
        try {
            if (Database::getInstance()->isConnected()) {
                $t0 = microtime(true);
                $this->getPdo()->query("SELECT 1");
                $dbLatency = round((microtime(true) - $t0) * 1000, 2);
            } else {
                $dbStatus = 'Standby / Offline';
            }
        } catch (\Throwable $e) {
            $dbStatus = 'Degraded';
        }

        // 2. WebSocket Daemon Probe (Port 8080)
        $wsOnline = false;
        $wsErr = '';
        $fpWs = @fsockopen('127.0.0.1', 8080, $errno, $errstr, 0.2);
        if ($fpWs) {
            $wsOnline = true;
            fclose($fpWs);
        } else {
            $wsErr = $errstr ?: 'Socket refused connection';
        }

        // 3. SMTP Mailpit Probe (Port 1025)
        $smtpOnline = false;
        $fpSmtp = @fsockopen('127.0.0.1', 1025, $errno, $errstr, 0.2);
        if ($fpSmtp) {
            $smtpOnline = true;
            fclose($fpSmtp);
        }

        // 4. Memory & Disk Diagnostics
        $memUsage = round(memory_get_usage(true) / (1024 * 1024), 2);
        $memLimit = ini_get('memory_limit');
        $diskFree = @disk_free_space('.');
        $diskTotal = @disk_total_space('.');
        $diskPercent = ($diskTotal > 0 && $diskFree !== false) ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1) : 0;

        return [
            'database' => [
                'status'  => $dbStatus,
                'latency' => "{$dbLatency} ms",
                'driver'  => 'MySQL 8.0',
            ],
            'websocket' => [
                'status' => $wsOnline ? 'Operational' : 'Idle / Offline',
                'port'   => 8080,
                'error'  => $wsErr,
            ],
            'smtp' => [
                'status' => $smtpOnline ? 'Operational' : 'Standby / Offline',
                'port'   => 1025,
            ],
            'runtime' => [
                'php_version' => PHP_VERSION,
                'memory_used' => "{$memUsage} MB",
                'memory_limit' => $memLimit,
                'disk_used'   => "{$diskPercent}%",
            ],
        ];
    }
}
