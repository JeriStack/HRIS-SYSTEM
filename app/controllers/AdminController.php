<?php

/**
 * Administrator Dashboard & Governance Controller
 * Enforces Administrator-Only Access and provides system-wide operational metrics,
 * real-time infrastructure diagnostics, security monitoring, and role governance.
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use App\Models\ActivityLog;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\AuthRiskService;
use App\Services\AuthSecurityNotificationService;
use Core\AuthorizationService;
use Core\Controller;
use Core\Database;
use Core\Permission;
use Core\PermissionRegistry;
use Core\Session;
use InvalidArgumentException;
use PDO;
use Throwable;

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

        // 10. Live HR-to-Admin Support Management Summary & Recent Queue
        $hrSupportStats = [
            'total_open'        => 0,
            'newly_submitted'   => 0,
            'critical_and_high' => 0,
            'awaiting_hr'       => 0,
            'in_progress'       => 0,
            'recently_resolved' => 0,
        ];
        $recentHrSupportTickets = [];
        try {
            $hrSupportModel = new \App\Models\HrSupportTicket($this->getPdo());
            $hrSupportStats = $hrSupportModel->getAdminDashboardStats();
            $recentHrSupportTickets = $hrSupportModel->findForAdminInbox(['limit' => 5]);
        } catch (\Throwable $e) {
            error_log('[AdminController::hrSupport] ' . $e->getMessage());
        }

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
            'pageTitle'              => 'System Overview',
            'user'                   => currentUser(),
            'metrics'                => $metrics,
            'deptDistribution'       => $deptDistribution,
            'leaveDistribution'      => $leaveDistribution,
            'monthlyTrends'          => array_reverse($monthlyTrends),
            'recentLogs'             => $recentLogs,
            'systemHealth'           => $systemHealth,
            'hrSupportStats'         => $hrSupportStats,
            'recentHrSupportTickets' => $recentHrSupportTickets,
        ], 'hr');
    }

    /**
     * Dynamic Roles & Permissions Governance Matrix
     * Route: GET /admin/roles
     */
    public function roles(): void
    {
        $authService = AuthorizationService::getInstance();
        $rolesOverview = $authService->getRolesOverview();
        $groupedPermissions = PermissionRegistry::getPermissionsByModule();
        $rolePermissionsMap = $authService->getRolePermissionsMap();

        // Query user accounts with profile and organizational metadata for User Access Management
        $userAccessList = [];
        $recentAuditLogs = [];
        $pdo = $this->getPdo();
        if ($pdo !== null) {
            try {
                $stmtUsers = $pdo->query("
                    SELECT 
                        u.id,
                        u.username,
                        u.email,
                        u.role,
                        u.status,
                        u.created_at,
                        e.first_name,
                        e.last_name,
                        e.employee_no,
                        d.department_name,
                        pos.position_name
                    FROM users u
                    LEFT JOIN employees e ON u.employee_id = e.id
                    LEFT JOIN departments d ON e.department_id = d.id
                    LEFT JOIN positions pos ON e.position_id = pos.id
                    ORDER BY u.id ASC
                ");
                $userAccessList = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable) {
                $userAccessList = [];
            }

            try {
                $stmt = $pdo->query("
                    SELECT 
                        pal.*,
                        u.username AS actor_username,
                        r.name AS target_role_name,
                        r.slug AS target_role_slug,
                        p.name AS permission_name,
                        p.slug AS permission_slug,
                        p.module AS permission_module
                    FROM permission_audit_logs pal
                    LEFT JOIN users u ON pal.actor_user_id = u.id
                    LEFT JOIN roles r ON pal.target_role_id = r.id
                    LEFT JOIN permissions p ON pal.permission_id = p.id
                    ORDER BY pal.id DESC
                    LIMIT 50
                ");
                $recentAuditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable) {
                $recentAuditLogs = [];
            }
        }

        $this->view('admin.roles', [
            'pageTitle'          => 'Roles & Permissions',
            'rolesOverview'      => $rolesOverview,
            'groupedPermissions' => $groupedPermissions,
            'rolePermissionsMap' => $rolePermissionsMap,
            'recentAuditLogs'    => $recentAuditLogs,
            'userAccessList'     => $userAccessList,
            // Retain backward-compatible view variables
            'roles'              => Permission::VALID_ROLES,
            'permissions'        => Permission::getAllCapabilities(),
            'categorized'        => [
                'System Governance'    => ['admin_dashboard', 'manage_users', 'assign_roles', 'system_settings', 'view_monitoring'],
                'Workforce Operations' => ['hr_dashboard', 'manage_employees', 'manage_departments', 'manage_positions', 'view_employee_records'],
                'Leave Administration' => ['review_leave_requests', 'manage_leave_types', 'manage_leave_balances'],
                'Self-Service'         => ['employee_dashboard', 'view_own_profile', 'view_own_leave_balances', 'file_leave_requests', 'view_own_leave_requests'],
                'Analytics & Tools'    => ['view_reports', 'view_activity_logs', 'dsa_laboratory', 'logout'],
            ],
        ], 'hr');
    }

    /**
     * Update Role Permissions
     * Route: POST /admin/roles/permissions
     */
    public function updatePermissions(): void
    {
        $roleSlug = strtolower(trim((string)($_POST['role'] ?? '')));
        $submittedPermissions = (array)($_POST['permissions'] ?? []);

        if ($roleSlug === '') {
            Session::flash('error', 'Target role must be specified.');
            redirect('/admin/roles?tab=matrix');
            return;
        }

        if ($roleSlug === PermissionRegistry::ROLE_ADMIN) {
            Session::flash('error', 'The Administrator role has permanent immutable authority and cannot be modified.');
            redirect('/admin/roles?tab=matrix');
            return;
        }

        try {
            $actorId = (int)currentUserId();
            $ip = client_ip() ?? '127.0.0.1';
            $agent = (string)($_SERVER['HTTP_USER_AGENT'] ?? 'Web');

            AuthorizationService::getInstance()->updateRolePermissions(
                $actorId,
                $roleSlug,
                $submittedPermissions,
                $ip,
                $agent
            );

            $roleName = $roleSlug === 'hr' ? 'Human Resources' : 'Employee';
            Session::flash('success', "Permissions for {$roleName} have been updated successfully and applied in real-time.");
        } catch (InvalidArgumentException $e) {
            Session::flash('error', 'Validation error: ' . $e->getMessage());
        } catch (Throwable $e) {
            error_log('[AdminController::updatePermissions] ' . $e->getMessage());
            Session::flash('error', 'Failed to update permissions. Database transaction was safely rolled back.');
        }

        redirect('/admin/roles?tab=matrix&role=' . urlencode($roleSlug));
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
            $sql .= " AND (a.action LIKE :filter OR a.description LIKE :filter OR u.username LIKE :filter)";
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
        $deviceConfig = (array)config('auth_devices', []);
        $roleDays = (array)($deviceConfig['trust_days_by_role'] ?? ['employee' => 30, 'hr' => 7, 'admin' => 7]);
        $empDays = (int)($roleDays['employee'] ?? 30);
        $hrDays = (int)($roleDays['hr'] ?? 7);
        $adminDays = (int)($roleDays['admin'] ?? 7);

        $policy = [
            'mfa_status'             => 'Enforced (Smart Email OTP + Cryptographic Trusted Devices)',
            'otp_expiry_seconds'     => (int)config('otp.expiry_seconds', 300),
            'max_otp_attempts'       => 5,
            'session_idle_timeout'   => 1800, // 30 minutes
            'session_max_lifetime'   => (int)config('session_max_lifetime', 43200), // 12 hours
            'password_algorithm'     => 'Bcrypt (cost 12)',
            'lockout_policy'         => 'Automatic lockout after 5 consecutive failed verification attempts',
            'session_fingerprint'    => 'SHA-256 auth-state parity fingerprint (role, status, password hash, employment status)',
            'trusted_device_policy'  => "Fixed expiration ({$empDays}d Employee, {$hrDays}d HR, {$adminDays}d Admin) with selector+validator rotation",
            'trusted_cookie_name'    => (string)($deviceConfig['cookie_name'] ?? 'nexora_td'),
            'logout_behavior'        => 'Sign-out destroys PHP session and WebSocket tickets only; trusted browsers stay remembered until revoked or expired',
            'step_up_triggers'       => 'Password change, email change, MFA change, account recovery, replay anomaly, or privileged multi-signal drift',
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

        $wantsJson = (($_GET['format'] ?? '') === 'json')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
        if ($wantsJson) {
            if (!headers_sent()) {
                header('Cache-Control: no-store, no-cache, must-revalidate, private');
                header('X-Content-Type-Options: nosniff');
            }
            json_response([
                'status' => 'success',
                'code'   => 200,
                'data'   => $health,
            ], 200);
        }

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

        $user = $this->getUserModel()->findByIdWithAuthState($userId);
        if (!$user) {
            Session::flash('error', 'User account not found.');
            $this->redirect('/admin/account');
            return;
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please provide a valid email address.');
            $this->redirect('/admin/account');
            return;
        }

        $existingUser = $this->getUserModel()->findByEmail($email);
        if ($existingUser && (int)$existingUser['id'] !== $userId) {
            Session::flash('error', 'The email address is already assigned to another account.');
            $this->redirect('/admin/account');
            return;
        }

        // Handle password update if supplied
        $emailChanged = strcasecmp($email, (string)($user['email'] ?? '')) !== 0;
        $passwordChanged = $newPassword !== '';
        $updateData = ['email' => $email];
        if ($passwordChanged) {
            if (!password_verify($currentPassword, (string)($user['password'] ?? ''))) {
                Session::flash('error', 'Current password verification failed.');
                $this->redirect('/admin/account');
                return;
            }

            if (strlen($newPassword) < 8) {
                Session::flash('error', 'New password must be at least 8 characters long.');
                $this->redirect('/admin/account');
                return;
            }

            if ($newPassword !== $confirmPassword) {
                Session::flash('error', 'New password and confirmation do not match.');
                $this->redirect('/admin/account');
                return;
            }

            $updateData['password'] = \App\Services\PasswordPolicy::hash($newPassword);
        }

        $this->getUserModel()->update($userId, $updateData);

        // Enforce security state transitions: password change revokes all trusted devices & requires fresh OTP;
        // email change requires OTP verification on next login even from an otherwise trusted browser.
        $freshUser = $this->getUserModel()->findByIdWithAuthState($userId);
        if ($passwordChanged || $emailChanged) {
            try {
                $riskService = new AuthRiskService();
                if ($passwordChanged) {
                    $trustedDeviceModel = new TrustedDevice($this->getPdo());
                    $revokedCount = $trustedDeviceModel->revokeAllForUser($userId, 'password_changed');
                    $riskService->flagPasswordChanged($userId);

                    if ($revokedCount > 0) {
                        $notifier = new AuthSecurityNotificationService();
                        $notifier->notifyAllDevicesRevoked(
                            $freshUser ?? $user,
                            $revokedCount,
                            'Administrator account password changed'
                        );
                    }
                }
                if ($emailChanged) {
                    $riskService->flagEmailChanged($userId);
                }
            } catch (Throwable $e) {
                error_log('[AdminController::updateAccount] Trusted device/risk update failed: ' . $e->getMessage());
            }
        }

        // Refresh session email and auth fingerprint so Session::verifyAuthenticatedAccountState() stays in sync
        $sessionUser = Session::get('user');
        if (is_array($sessionUser) && is_array($freshUser)) {
            $sessionUser['email'] = (string)($freshUser['email'] ?? $email);
            $sessionUser['auth_fingerprint'] = Session::computeAuthFingerprint($freshUser);
            Session::set('user', $sessionUser);
            if ($passwordChanged) {
                Session::regenerate();
            }
        }

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
        $this->redirect('/admin/account');
    }

    /**
     * Genuine Runtime Health Probe (Zero Fabricated Data)
     * Performs live socket checks and queries system parameters
     *
     * @return array<string, mixed>
     */
    private function probeLiveHealth(): array
    {
        $inDocker = file_exists('/.dockerenv');

        // 1. Database Probe (with live version introspection)
        $dbStatus = 'Healthy';
        $dbLatency = 0.0;
        $dbDriver = 'MySQL 8.0';
        try {
            if (Database::getInstance()->isConnected()) {
                $t0 = microtime(true);
                $ver = $this->getPdo()->query("SELECT VERSION()")->fetchColumn();
                $dbLatency = round((microtime(true) - $t0) * 1000, 2);
                if (is_string($ver) && $ver !== '') {
                    $dbDriver = 'MySQL ' . explode('-', $ver)[0];
                }
            } else {
                $dbStatus = 'Standby / Offline';
            }
        } catch (\Throwable $e) {
            $dbStatus = 'Degraded';
        }

        // 2. WebSocket Daemon Probe (uses real WS_PORT and Docker service name when in container)
        $wsHost = getenv('WS_HEALTH_HOST') ?: ($inDocker ? 'realtime' : '127.0.0.1');
        $wsPort = (int)(getenv('WS_PORT') ?: 8090);
        $wsProbe = $this->probeTcpEndpoint($wsHost, $wsPort, 0.25);

        // 3. SMTP Probe (uses configured mail host & port)
        $mailCfg = config('mail', []);
        $smtpHost = (string)($mailCfg['host'] ?? ($inDocker ? 'mailpit' : '127.0.0.1'));
        $smtpPort = (int)($mailCfg['port'] ?? 1025);
        $smtpProbe = $this->probeTcpEndpoint($smtpHost, $smtpPort, 0.25);
        $isMailpit = in_array(strtolower($smtpHost), ['mailpit', '127.0.0.1', 'localhost'], true) && $smtpPort === 1025;

        // 4. Observability Stack Probes (Prometheus & Grafana)
        $promHost = $inDocker ? 'prometheus' : '127.0.0.1';
        $promPort = (int)(getenv('PROMETHEUS_PORT') ?: 9090);
        $promProbe = $this->probeTcpEndpoint($promHost, $promPort, 0.2);

        $grafanaHost = $inDocker ? 'grafana' : '127.0.0.1';
        $grafanaPort = $inDocker ? 3000 : (int)(getenv('GRAFANA_PORT') ?: 3100);
        $grafanaProbe = $this->probeTcpEndpoint($grafanaHost, $grafanaPort, 0.2);

        // 5. Memory & Disk Diagnostics
        $memUsage = round(memory_get_usage(true) / (1024 * 1024), 2);
        $memPeak = round(memory_get_peak_usage(true) / (1024 * 1024), 2);
        $memLimit = (string)ini_get('memory_limit');
        $diskFree = @disk_free_space('.');
        $diskTotal = @disk_total_space('.');
        $diskPercent = ($diskTotal > 0 && $diskFree !== false) ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1) : 0.0;
        $diskStatus = $diskPercent >= 90.0 ? 'Critical' : ($diskPercent >= 80.0 ? 'Warning' : 'Storage OK');

        // 6. Queue & Asynchronous Worker Telemetry
        $queueMetrics = [
            'pending' => 0, 'reserved' => 0, 'completed' => 0, 'failed' => 0, 'total_failed' => 0, 'oldest_pending_s' => 0
        ];
        if (class_exists(\App\Queue\QueueService::class)) {
            try {
                $queueMetrics = \App\Queue\QueueService::getInstance()->getMetrics('default');
            } catch (\Throwable) {
                // Non-fatal telemetry fallback
            }
        }

        // 7. Live Console Scheduler Task Introspection
        $schedulerTasks = [];
        if (class_exists(\App\Console\Kernel::class)) {
            try {
                foreach ((new \App\Console\Kernel())->getTasks() as $task) {
                    $schedulerTasks[] = $task->getName();
                }
            } catch (\Throwable) {
                // Non-fatal fallback
            }
        }
        $schedulerTaskCount = count($schedulerTasks);

        return [
            'checked_at' => date('Y-m-d H:i:s'),
            'database' => [
                'status'  => $dbStatus,
                'latency' => "{$dbLatency} ms",
                'driver'  => $dbDriver,
            ],
            'queue' => [
                'status'           => ($queueMetrics['failed'] > 0 || $queueMetrics['total_failed'] > 10) ? 'Attention Required' : 'Operational',
                'pending'          => $queueMetrics['pending'],
                'reserved'         => $queueMetrics['reserved'],
                'completed'        => $queueMetrics['completed'],
                'failed'           => $queueMetrics['failed'],
                'dead_letter'      => $queueMetrics['total_failed'],
                'oldest_pending_s' => $queueMetrics['oldest_pending_s'],
            ],
            'scheduler' => [
                'status'       => $schedulerTaskCount > 0 ? 'Operational' : 'Standby',
                'active_tasks' => $schedulerTaskCount,
                'tasks'        => $schedulerTasks,
            ],
            'websocket' => [
                'status' => $wsProbe['online'] ? 'Operational' : 'Idle / Offline',
                'host'   => $wsHost,
                'port'   => $wsPort,
                'error'  => $wsProbe['error'],
            ],
            'smtp' => [
                'status'     => $smtpProbe['online'] ? 'Operational' : 'Standby / Offline',
                'host'       => $smtpHost,
                'port'       => $smtpPort,
                'is_mailpit' => $isMailpit,
                'error'      => $smtpProbe['error'],
            ],
            'monitoring' => [
                'prometheus' => [
                    'status' => $promProbe['online'] ? 'Operational' : 'Standby / Offline',
                    'host'   => $promHost,
                    'port'   => $promPort,
                ],
                'grafana' => [
                    'status' => $grafanaProbe['online'] ? 'Operational' : 'Standby / Offline',
                    'host'   => $grafanaHost,
                    'port'   => $grafanaPort,
                ],
            ],
            'runtime' => [
                'php_version'  => PHP_VERSION,
                'memory_used'  => "{$memUsage} MB",
                'memory_peak'  => "{$memPeak} MB",
                'memory_limit' => $memLimit,
                'disk_used'    => "{$diskPercent}%",
                'disk_status'  => $diskStatus,
            ],
        ];
    }

    /**
     * Lightweight non-blocking TCP socket reachability probe.
     *
     * @return array{online: bool, error: string}
     */
    private function probeTcpEndpoint(string $host, int $port, float $timeoutSeconds = 0.25): array
    {
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeoutSeconds);
        if ($fp !== false) {
            fclose($fp);
            return ['online' => true, 'error' => ''];
        }

        return ['online' => false, 'error' => $errstr !== '' ? $errstr : 'Socket refused connection'];
    }
}
