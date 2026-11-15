<?php
/**
 * Dashboard & Protected Resource Demonstrator Controller
 * Enforces role boundaries and employee record ownership verification
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use Core\Controller;
use Core\Database;
use Core\Session;
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

        if ($eventType === \App\Realtime\EventAuthorizer::CORRECTION_REQUESTED) {
            return [
                'path'  => '/hr/correction-requests',
                'label' => 'Review request',
            ];
        }

        if (
            $eventType === \App\Realtime\EventAuthorizer::CORRECTION_APPROVED
            || $eventType === \App\Realtime\EventAuthorizer::CORRECTION_REJECTED
        ) {
            return [
                'path'  => '/employee/profile#correction-requests',
                'label' => 'View request',
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

        $employeeRecord = ['id' => $employeeId];

        if ($employeeId > 0) {
            // 1. Synchronize latest employee identity fields
            try {
                $stmtEmp = $this->pdo->prepare("
                    SELECT e.*, d.department_name, p.position_name
                    FROM employees e
                    LEFT JOIN departments d ON e.department_id = d.id
                    LEFT JOIN positions p ON e.position_id = p.id
                    WHERE e.id = :emp_id
                    LIMIT 1
                ");
                $stmtEmp->execute(['emp_id' => $employeeId]);
                $empInfo = $stmtEmp->fetch(PDO::FETCH_ASSOC);
                if (is_array($empInfo)) {
                    $employeeRecord = $empInfo;
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
                $balances = $this->filterVisibleEmployeeBalances($employeeRecord, $balances, $leaveCycleYear, $todayDate);
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

        if (is_array($profile)) {
            $sessionUser = Session::get('user');
            if (is_array($sessionUser)) {
                $sessionUser['profile_picture'] = $profile['profile_picture'] ?? null;
                Session::set('user', $sessionUser);
                $user = $sessionUser;
            }
        }

        $empIdInt = is_array($profile) && !empty($profile['id']) ? (int)$profile['id'] : (int)$myEmpId;
        $tenure = \App\Services\EmployeeTenureCalculator::calculate(is_array($profile) ? ($profile['date_hired'] ?? null) : null);
        $avatarUrl = \App\Services\ProfilePictureService::avatarUrl(
            $empIdInt > 0 ? $empIdInt : null,
            is_array($profile) ? ($profile['profile_picture'] ?? null) : null
        );

        $emergencyContacts = [];
        try {
            if ($empIdInt > 0) {
                $emergencyContacts = (new \App\Models\EmergencyContact($this->pdo))->getByEmployee($empIdInt);
            }
        } catch (\Throwable $e) {
            error_log('[DashboardController::employeeProfile] Emergency contacts query skipped: ' . $e->getMessage());
        }

        $employmentHistory = [];
        $lastPromotion = null;
        try {
            if ($empIdInt > 0) {
                $historyModel = new \App\Models\EmploymentHistory($this->pdo);
                $employmentHistory = $historyModel->getByEmployee($empIdInt);
                $lastPromotion = $historyModel->getLastPromotion($empIdInt);
            }
        } catch (\Throwable $e) {
            error_log('[DashboardController::employeeProfile] Employment history query skipped: ' . $e->getMessage());
        }

        $correctionRequests = [];
        try {
            if ($empIdInt > 0) {
                $correctionRequests = (new \App\Models\ProfileCorrectionRequest($this->pdo))->getByEmployee($empIdInt);
            }
        } catch (\Throwable $e) {
            error_log('[DashboardController::employeeProfile] Correction requests query skipped: ' . $e->getMessage());
        }

        $departments = [];
        $positions = [];
        try {
            $departments = $this->pdo->query("SELECT id, department_name FROM departments ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $positions = $this->pdo->query("SELECT id, position_name, department_id FROM positions ORDER BY position_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[DashboardController::employeeProfile] Org lookup query skipped: ' . $e->getMessage());
        }

        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            $this->json([
                'status'  => 'success',
                'profile' => $profile,
                'tenure'  => $tenure,
            ]);
            return;
        }

        $formErrors = Session::getFlash('profile_form_errors', []);
        $formOld    = Session::getFlash('profile_form_old', []);
        $activeForm = Session::getFlash('profile_active_form', '');

        $this->view('dashboard.employee_profile', [
            'pageTitle'               => 'My Profile',
            'user'                    => $user,
            'profile'                 => $profile ?: [],
            'avatarUrl'               => $avatarUrl,
            'avatarMaxBytes'          => (int)config('profile.avatar_max_bytes', 5 * 1024 * 1024),
            'tenure'                  => $tenure,
            'lastPromotion'           => $lastPromotion,
            'emergencyContacts'       => $emergencyContacts,
            'employmentHistory'       => $employmentHistory,
            'correctionRequests'      => $correctionRequests,
            'allowedCorrectionFields' => \App\Models\ProfileCorrectionRequest::getAllowedFields(),
            'departments'             => $departments,
            'positions'               => $positions,
            'civilStatuses'           => (array)config('profile.civil_statuses', []),
            'employmentTypes'         => (array)config('profile.employment_types', []),
            'relationships'           => \App\Models\EmergencyContact::VALID_RELATIONSHIPS,
            'formErrors'              => is_array($formErrors) ? $formErrors : [],
            'formOld'                 => is_array($formOld) ? $formOld : [],
            'activeForm'              => is_string($activeForm) ? $activeForm : '',
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
        $myEmpId = (int)($user['employee_id'] ?? 0);

        // Ownership enforcement against parameter spoofing
        if (isset($_GET['emp_id']) && (int)$_GET['emp_id'] !== $myEmpId) {
            http_response_code(403);
            $this->view('errors.403', [
                'pageTitle' => '403 Forbidden - Ownership Violation',
                'message'   => 'Ownership Access Denied: You cannot inspect another employee\'s leave balances.'
            ], null);
            return;
        }

        $year = !empty($_GET['year']) ? max(2000, min(2100, (int)$_GET['year'])) : (int)date('Y');
        $leaveModel = $this->getLeaveModel();
        $balances = $leaveModel->getBalancesByEmployee($myEmpId, $year);

        $balanceByTypeId = [];
        foreach ($balances as $bal) {
            $balanceByTypeId[(int)$bal['leave_type_id']] = $bal;
        }

        // Load employee profile for tenure/gender/SIL/SPIC eligibility rules
        $empStmt = $this->pdo->prepare("SELECT * FROM employees WHERE id = :id LIMIT 1");
        $empStmt->execute(['id' => $myEmpId]);
        $employee = $empStmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => $myEmpId];

        // Pending days grouped per leave type for accurate Available vs Pending vs Used separation
        $pendingByType = [];
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);
        $pStmt = $this->pdo->prepare("
            SELECT leave_type_id, COALESCE(SUM(number_of_days), 0) AS pending_days
            FROM leave_requests
            WHERE employee_id = :emp_id
              AND status = 'Pending'
              AND start_date BETWEEN :ystart AND :yend
            GROUP BY leave_type_id
        ");
        $pStmt->execute([
            'emp_id' => $myEmpId,
            'ystart' => $yearStart,
            'yend'   => $yearEnd,
        ]);
        foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $pRow) {
            $pendingByType[(int)$pRow['leave_type_id']] = (int)round((float)$pRow['pending_days']);
        }

        $policyEngine = new \App\Services\LeavePolicyEngine($this->pdo);
        $allTypePolicies = $policyEngine->getAllTypesWithActivePolicies(true, date('Y-m-d'));

        $annualBenefits = [];
        $statutoryEvents = [];
        $companyEvents = [];
        $unpaidBenefits = [];
        $totalAllocatedPaid = 0;
        $totalUsedPaid = 0;
        $totalPendingPaid = 0;
        $totalAvailablePaid = 0;
        $totalConvertedPaid = 0;

        foreach ($allTypePolicies as $tp) {
            $ltId = (int)$tp['id'];
            $model = (string)($tp['entitlement_model'] ?? 'annual');
            $code = strtoupper(trim((string)($tp['leave_code'] ?? '')));
            $bal = $balanceByTypeId[$ltId] ?? null;
            $pendingDays = $pendingByType[$ltId] ?? 0;
            $elig = $policyEngine->evaluateEmployeeTypeEligibility($employee, $tp, $year, date('Y-m-d'));

            if ($model === 'annual') {
                $alloc = $bal !== null ? (int)$bal['allocated_days'] : 0;
                $used = $bal !== null ? (int)$bal['used_days'] : 0;
                $converted = $bal !== null ? (int)round((float)($bal['converted_days'] ?? 0)) : 0;
                $expired = $bal !== null ? (int)round((float)($bal['expired_days'] ?? 0)) : 0;
                $remaining = $bal !== null ? (int)$bal['remaining_days'] : 0;

                if (empty($elig['eligible']) && $used === 0 && $pendingDays === 0 && $converted === 0) {
                    continue;
                }

                $statusLabel = (string)($elig['status'] ?? 'Available');
                if ($elig['eligible']) {
                    if ($bal === null) {
                        $statusLabel = \App\Services\LeavePolicyEngine::STATUS_NOT_ALLOCATED;
                    } elseif ($remaining <= 0) {
                        $statusLabel = \App\Services\LeavePolicyEngine::STATUS_FULLY_USED;
                    } elseif ($alloc > 0 && $remaining <= max(1, (int)floor($alloc * 0.25))) {
                        $statusLabel = \App\Services\LeavePolicyEngine::STATUS_LOW_BALANCE;
                    } else {
                        $statusLabel = \App\Services\LeavePolicyEngine::STATUS_AVAILABLE;
                    }
                }

                $entry = [
                    'type'           => $tp,
                    'balance'        => $bal,
                    'allocated_days' => $alloc,
                    'used_days'      => $used,
                    'pending_days'   => $pendingDays,
                    'converted_days' => $converted,
                    'expired_days'   => $expired,
                    'remaining_days' => $remaining,
                    'eligibility'    => $elig,
                    'status_label'   => $statusLabel,
                ];
                $annualBenefits[] = $entry;

                if (!empty($tp['is_paid'])) {
                    $totalAllocatedPaid += $alloc;
                    $totalUsedPaid += $used;
                    $totalPendingPaid += $pendingDays;
                    $totalAvailablePaid += $remaining;
                    $totalConvertedPaid += $converted;
                }
            } elseif ($model === 'event_based') {
                if (empty($elig['eligible'])) {
                    continue;
                }
                $item = [
                    'type'         => $tp,
                    'pending_days' => $pendingDays,
                    'eligibility'  => $elig,
                    'paternity_deliveries_used' => $code === 'PATERNITY' ? $policyEngine->countPaternityDeliveries($myEmpId, $ltId) : null,
                ];
                if (($tp['classification'] ?? 'Company') === 'Statutory') {
                    $statutoryEvents[] = $item;
                } else {
                    $companyEvents[] = $item;
                }
            } elseif ($model === 'unpaid') {
                $unpaidBenefits[] = [
                    'type'         => $tp,
                    'pending_days' => $pendingDays,
                    'eligibility'  => $elig,
                ];
            }
        }

        $eventEntitlements = $leaveModel->getEventEntitlementsByEmployee($myEmpId);
        $qualifications = $leaveModel->getQualificationsByEmployee($myEmpId);
        $unpaidSummary = $leaveModel->getUnpaidLeaveSummaryByEmployee($myEmpId, $year);

        $ledgerService = new \App\Services\LeaveLedgerService($this->pdo);
        $ledgerTransactions = $ledgerService->getHistoryByEmployee($myEmpId, $year, true, 50);

        $pendingRequestsAll = $leaveModel->getAllRequests('Pending', $myEmpId, 50);
        $pendingRequestsPreview = array_slice($pendingRequestsAll, 0, 3);

        // Render full HTML view when requested by browser
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') && !isset($_GET['format'])) {
            $this->view('dashboard.employee_balances', [
                'pageTitle'              => 'My Leave Balances',
                'user'                   => $user,
                'employee'               => $employee,
                'balances'               => $balances,
                'year'                   => $year,
                'annualBenefits'         => $annualBenefits,
                'statutoryEvents'        => $statutoryEvents,
                'companyEvents'          => $companyEvents,
                'unpaidBenefits'         => $unpaidBenefits,
                'eventEntitlements'      => $eventEntitlements,
                'qualifications'         => $qualifications,
                'unpaidSummary'          => $unpaidSummary,
                'ledgerTransactions'     => $ledgerTransactions,
                'pendingRequestsPreview' => $pendingRequestsPreview,
                'pendingRequestsTotal'   => count($pendingRequestsAll),
                'allTypePolicies'        => $allTypePolicies,
                'overviewMetrics'        => [
                    'allocated_paid' => $totalAllocatedPaid,
                    'used_paid'      => $totalUsedPaid,
                    'pending_paid'   => $totalPendingPaid,
                    'available_paid' => $totalAvailablePaid,
                    'converted_paid' => $totalConvertedPaid,
                ],
            ], 'hr');
            return;
        }

        $this->json([
            'status'             => 'success',
            'employee_no'        => $user['employee_no'] ?? null,
            'year'               => $year,
            'balances'           => $this->filterVisibleEmployeeBalances($employee, $balances, $year, date('Y-m-d')),
            'unpaid_summary'     => $unpaidSummary,
            'event_entitlements' => $eventEntitlements,
            'ledger'             => $ledgerTransactions,
        ]);
    }

    /**
     * Filter an employee's balance rows to leave types currently applicable to them.
     * Hides VL-offset SIL, unverified Solo Parent, and other ineligible zero-usage rows.
     *
     * @param array<string, mixed> $employee
     * @param array<int, array<string, mixed>> $balances
     * @return array<int, array<string, mixed>>
     */
    private function filterVisibleEmployeeBalances(
        array $employee,
        array $balances,
        int $year,
        string $asOfDate
    ): array {
        if ($balances === []) {
            return [];
        }

        $policyMap = [];
        try {
            $policyEngine = new \App\Services\LeavePolicyEngine($this->pdo);
            foreach ($policyEngine->getAllTypesWithActivePolicies(false, $asOfDate) as $tp) {
                $policyMap[(int)$tp['id']] = $tp;
            }
        } catch (\Throwable $e) {
            error_log('[DashboardController::filterVisibleEmployeeBalances] ' . $e->getMessage());
            $policyEngine = null;
        }

        $visible = [];
        foreach ($balances as $bal) {
            $used = (int)($bal['used_days'] ?? 0);
            $converted = (int)round((float)($bal['converted_days'] ?? 0));
            if ($used > 0 || $converted > 0) {
                $visible[] = $bal;
                continue;
            }

            $rowStatus = (string)($bal['eligibility_status'] ?? 'Available');
            $silOffset = !empty($bal['sil_credited_from_vl']) || $rowStatus === 'CreditedViaVL';
            if ($silOffset || in_array($rowStatus, ['PendingVerification', 'Ineligible'], true)) {
                continue;
            }

            $ltId = (int)($bal['leave_type_id'] ?? 0);
            if ($policyEngine !== null && isset($policyMap[$ltId])) {
                $tp = $policyMap[$ltId];
                if (($tp['status'] ?? 'Active') !== 'Active') {
                    continue;
                }
                $elig = $policyEngine->evaluateEmployeeTypeEligibility($employee, $tp, $year, $asOfDate);
                if (empty($elig['eligible'])) {
                    continue;
                }
            }

            $visible[] = $bal;
        }

        return $visible;
    }

    /**
     * Employee Own Leave Requests (Enforces Strict Ownership)
     * Route: GET /employee/leave-requests
     * Access: Employee Only
     */
    public function employeeLeaveRequests(): void
    {
        $user = currentUser();
        $myEmpId = (int)($user['employee_id'] ?? 0);

        // Ownership enforcement against parameter spoofing
        if (isset($_GET['emp_id']) && (int)$_GET['emp_id'] !== $myEmpId) {
            http_response_code(403);
            $this->view('errors.403', [
                'pageTitle' => '403 Forbidden - Ownership Violation',
                'message'   => 'Ownership Access Denied: You cannot inspect another employee\'s leave requests.'
            ], null);
            return;
        }

        $leaveModel = $this->getLeaveModel();
        $requests = $leaveModel->getAllRequests(null, $myEmpId, 200);

        // Build chronological leave sequence using custom Linked List (Phase 11/13)
        $historyService = new \App\Services\LeaveHistorySequenceService();
        $historyChain = $myEmpId ? $historyService->buildEmployeeLeaveHistoryChain($myEmpId, 25) : new \App\Dsa\Structures\LinkedList();
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
            'employee_no' => $user['employee_no'] ?? null,
            'requests'    => $requests,
        ]);
    }
}
