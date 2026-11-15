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
    private ?\App\Models\Notification $notificationModel;
    private ?\App\Models\Leave $leaveModel;

    public function __construct(
        ?PDO $pdo = null,
        ?\App\Models\Notification $notificationModel = null,
        ?\App\Models\Leave $leaveModel = null
    ) {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
        $this->notificationModel = $notificationModel;
        $this->leaveModel = $leaveModel;
    }

    private function getNotificationModel(): \App\Models\Notification
    {
        if ($this->notificationModel === null) {
            $this->notificationModel = new \App\Models\Notification($this->pdo);
        }
        return $this->notificationModel;
    }

    private function getLeaveModel(): \App\Models\Leave
    {
        if ($this->leaveModel === null) {
            $this->leaveModel = new \App\Models\Leave($this->pdo);
        }
        return $this->leaveModel;
    }

    /**
     * Resolve a safe internal destination path and action label from notification metadata.
     *
     * @param array<string, mixed> $notification
     * @return array{path: string, label: string}|null
     */
    public static function resolveNotificationDestination(array $notification): ?array
    {
        $eventType = trim((string)($notification['event_type'] ?? ''));
        $entityId = isset($notification['entity_id']) ? (int)$notification['entity_id'] : 0;

        $leaveEvents = [
            \App\Realtime\EventAuthorizer::LEAVE_CREATED,
            \App\Realtime\EventAuthorizer::LEAVE_APPROVED,
            \App\Realtime\EventAuthorizer::LEAVE_REJECTED,
        ];
        if (in_array($eventType, $leaveEvents, true) && $entityId > 0) {
            return [
                'path'  => '/leaves/' . $entityId,
                'label' => 'View request',
            ];
        }

        if ($eventType === \App\Realtime\EventAuthorizer::PROFILE_UPDATED) {
            return [
                'path'  => '/employee/profile',
                'label' => 'View profile',
            ];
        }

        return null;
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
        $user = currentUser() ?: [];
        $employeeId = !empty($user['employee_id']) ? (int)$user['employee_id'] : 0;
        $userId = !empty($user['id']) ? (int)$user['id'] : 0;
        $leaveCycleYear = (int)date('Y');
        $todayDate = date('Y-m-d');

        $balances = [];
        $recentRequests = [];
        $upcomingApprovedLeaves = [];
        $recentNotifications = [];
        $unreadNotificationsCount = 0;
        $pendingRequestsCount = 0;
        $sectionErrors = [
            'profile'       => null,
            'balances'      => null,
            'requests'      => null,
            'upcoming'      => null,
            'notifications' => null,
        ];

        if ($userId > 0) {
            try {
                $notifModel = $this->getNotificationModel();
                $rawNotifications = $notifModel->getByUser($userId, 3);
                foreach ($rawNotifications as $notif) {
                    $dest = self::resolveNotificationDestination($notif);
                    $notif['destination_path'] = $dest['path'] ?? null;
                    $notif['destination_label'] = $dest['label'] ?? null;
                    $recentNotifications[] = $notif;
                }
                $unreadNotificationsCount = max(0, $notifModel->getUnreadCount($userId));
            } catch (\Throwable $e) {
                error_log('[EmployeeDashboard::notifications] ' . $e->getMessage());
                $recentNotifications = [];
                $unreadNotificationsCount = 0;
                $sectionErrors['notifications'] = 'Unable to load recent notifications right now.';
            }
        }

        if ($employeeId > 0) {
            // 1. Synchronize latest employee identity fields
            try {
                $stmtEmp = $this->pdo->prepare("
                    SELECT e.first_name, e.last_name, e.employee_no, d.department_name, p.position_name
                    FROM employees e
                    LEFT JOIN departments d ON e.department_id = d.id
                    LEFT JOIN positions p ON e.position_id = p.id
                    WHERE e.id = :emp_id
                    LIMIT 1
                ");
                $stmtEmp->execute(['emp_id' => $employeeId]);
                $empInfo = $stmtEmp->fetch(PDO::FETCH_ASSOC);
                if ($empInfo) {
                    $fullName = trim(($empInfo['first_name'] ?? '') . ' ' . ($empInfo['last_name'] ?? ''));
                    if ($fullName !== '') {
                        $user['name'] = $fullName;
                    }
                    if (!empty($empInfo['employee_no'])) {
                        $user['employee_no'] = $empInfo['employee_no'];
                    }
                    if (!empty($empInfo['department_name'])) {
                        $user['department'] = $empInfo['department_name'];
                    }
                    if (!empty($empInfo['position_name'])) {
                        $user['position'] = $empInfo['position_name'];
                    }
                }
            } catch (\Throwable $e) {
                error_log('[EmployeeDashboard::profile] ' . $e->getMessage());
                $sectionErrors['profile'] = 'Profile details may be out of date.';
            }

            $leaveModel = $this->getLeaveModel();

            // 2. Fetch employee's current-cycle leave balances
            try {
                $balances = $leaveModel->getBalancesByEmployee($employeeId, $leaveCycleYear);
            } catch (\Throwable $e) {
                error_log('[EmployeeDashboard::balances] ' . $e->getMessage());
                $balances = [];
                $sectionErrors['balances'] = 'Unable to load leave balances right now.';
            }

            // 3. Fetch accurate total pending count across all employee requests
            try {
                $pendingRequestsCount = max(0, $leaveModel->countPendingByEmployee($employeeId));
            } catch (\Throwable $e) {
                error_log('[EmployeeDashboard::pendingCount] ' . $e->getMessage());
                $pendingRequestsCount = 0;
            }

            // 4. Fetch latest 3 leave requests belonging strictly to this employee
            try {
                $recentRequests = $leaveModel->getRecentByEmployee($employeeId, 3);
            } catch (\Throwable $e) {
                error_log('[EmployeeDashboard::requests] ' . $e->getMessage());
                $recentRequests = [];
                $sectionErrors['requests'] = 'Unable to load recent leave requests right now.';
            }

            // 5. Fetch upcoming approved leave requests sorted by nearest start date
            try {
                $upcomingApprovedLeaves = $leaveModel->getUpcomingApprovedByEmployee($employeeId, $todayDate, 3);
            } catch (\Throwable $e) {
                error_log('[EmployeeDashboard::upcoming] ' . $e->getMessage());
                $upcomingApprovedLeaves = [];
                $sectionErrors['upcoming'] = 'Unable to load upcoming approved leave right now.';
            }
        }

        $this->view('dashboard.employee', [
            'pageTitle'                => 'Employee Dashboard',
            'user'                     => $user,
            'leaveCycleYear'           => $leaveCycleYear,
            'balances'                 => $balances,
            'requests'                 => $recentRequests,
            'pendingLeaves'            => $recentRequests,
            'upcomingApprovedLeaves'   => $upcomingApprovedLeaves,
            'pendingRequestsCount'     => $pendingRequestsCount,
            'recentNotifications'      => $recentNotifications,
            'unreadNotificationsCount' => $unreadNotificationsCount,
            'sectionErrors'            => $sectionErrors,
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
            'pageTitle' => 'My Profile',
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
                'pageTitle' => 'My Leave Balances',
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

        // Allowlisted initial client filter (e.g. ?status=Pending from dashboard KPI link)
        $rawStatus = trim((string)($_GET['status'] ?? 'all'));
        $allowedStatuses = ['all', 'Pending', 'Approved', 'Rejected', 'Cancelled'];
        $initialStatus = in_array($rawStatus, $allowedStatuses, true) ? $rawStatus : 'all';

        // Render full HTML view when requested by browser
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') && !isset($_GET['format'])) {
            $this->view('dashboard.employee_requests', [
                'pageTitle'     => 'My Leave Requests',
                'user'          => $user,
                'requests'      => $requests,
                'historyArray'  => $historyArray,
                'initialStatus' => $initialStatus,
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
