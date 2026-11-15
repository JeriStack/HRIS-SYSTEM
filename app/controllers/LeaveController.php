<?php
/**
 * Leave Management Controller
 * Handles leave applications, approvals, rejections, cancellations, balances, and leave types
 * Employee HRIS & Leave Management System - Phase 8
 */

namespace App\Controllers;

use Core\Controller;
use Core\Database;
use Core\Session;
use App\Models\Leave;
use App\Models\Employee;
use App\Models\Department;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use App\Services\ConfidentialLeaveService;
use App\Services\LeaveCalculator;
use App\Services\LeaveDocumentService;
use App\Services\LeaveLedgerService;
use App\Services\LeaveNotificationService;
use App\Services\LeaveApprovalQueueService;
use App\Services\LeavePolicyEngine;
use App\Application\Leave\Adapters\LeaveControllerAdapter;
use PDO;

class LeaveController extends Controller
{
    private PDO $pdo;
    private Leave $leaveModel;
    private Employee $employeeModel;
    private Department $departmentModel;
    private ActivityLog $activityLog;
    private Notification $notificationModel;
    private User $userModel;
    private LeaveNotificationService $leaveNotifications;
    private LeaveControllerAdapter $leaveAdapter;

    public function __construct(
        ?Leave $leaveModel = null,
        ?LeaveControllerAdapter $leaveAdapter = null
    ) {
        $this->pdo = Database::getInstance()->getConnection();
        $this->leaveModel = $leaveModel ?? new Leave($this->pdo);
        $this->employeeModel = new Employee();
        $this->departmentModel = new Department();
        $this->activityLog = new ActivityLog();
        $this->notificationModel = new Notification();
        $this->userModel = new User();
        $this->leaveNotifications = new LeaveNotificationService($this->notificationModel, $this->userModel);
        $this->leaveAdapter = $leaveAdapter ?? new LeaveControllerAdapter();
    }

    /**
     * Display Leave Review Queue (HR & Admin Access)
     * Route: GET /leaves
     */
    public function index(): void
    {
        $user = currentUser();
        $status = !empty($_GET['status']) ? trim((string)$_GET['status']) : null;
        $empId = !empty($_GET['emp_id']) ? (int)$_GET['emp_id'] : null;
        $deptId = !empty($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
        $startDate = !empty($_GET['start_date']) ? trim((string)$_GET['start_date']) : null;
        $endDate = !empty($_GET['end_date']) ? trim((string)$_GET['end_date']) : null;

        $rawRequests = $this->leaveModel->getAllRequests($status, $empId, 200, $startDate, $endDate, $deptId);
        $requests = array_map(
            fn (array $row): array => ConfidentialLeaveService::maskRequestForViewer($row, $user, $this->pdo),
            $rawRequests
        );
        $departments = $this->departmentModel->getAll();

        // Build deterministic FIFO Pending Approval Queue using custom Queue data structure (Phase 11/13)
        $queueService = new LeaveApprovalQueueService($this->leaveModel);
        $approvalQueue = $queueService->buildPendingApprovalQueue($deptId, 100);
        $nextInLine = $queueService->getNextInLine($approvalQueue);
        if (is_array($nextInLine)) {
            $nextInLine = ConfidentialLeaveService::maskRequestForViewer($nextInLine, $user, $this->pdo);
        }
        $queueDepth = $approvalQueue->size();
        $canViewConfidential = $user !== null && ConfidentialLeaveService::userHasConfidentialPermission($user, $this->pdo);

        $this->view('leaves.index', [
            'pageTitle'           => 'Leave Requests Review Queue',
            'requests'            => $requests,
            'selectedStatus'      => $status,
            'selectedDept'        => $deptId,
            'selectedEmpId'       => $empId,
            'startDate'           => $startDate,
            'endDate'             => $endDate,
            'departments'         => $departments,
            'queueDepth'          => $queueDepth,
            'nextInLine'          => $nextInLine,
            'canViewConfidential' => $canViewConfidential,
            'user'                => $user,
        ], 'hr');
    }

    /**
     * View Details of a Single Leave Request
     * Route: GET /leaves/{id}
     * Access: HR, Admin, or the Owner Employee
     */
    public function show(int $id): void
    {
        $user = currentUser();
        $req = $this->leaveModel->getRequestById($id);

        if (!$req) {
            http_response_code(404);
            $this->view('errors.404', [
                'pageTitle' => '404 Not Found - Leave Request',
                'message'   => "Leave request #{$id} does not exist."
            ], null);
            return;
        }

        $userRole = strtolower($user['role'] ?? 'employee');
        $myEmpId = (int)($user['employee_id'] ?? 0);

        // Security check: If employee, they can only view their own request
        if ($userRole === 'employee' && (int)$req['employee_id'] !== $myEmpId) {
            if (ConfidentialLeaveService::isConfidentialRequest($req)) {
                ConfidentialLeaveService::logSensitiveAccess(
                    $this->pdo,
                    $id,
                    null,
                    (int)($user['id'] ?? 0) ?: null,
                    'view_request',
                    false,
                    client_ip(),
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                );
            }
            http_response_code(403);
            $this->view('errors.403', [
                'pageTitle' => '403 Forbidden - Ownership Violation',
                'message'   => 'You are not authorized to view another employee\'s leave request.'
            ], null);
            return;
        }

        $isConfidential = ConfidentialLeaveService::isConfidentialRequest($req);
        $canViewUnmasked = ConfidentialLeaveService::canViewUnmasked($user, $req, $this->pdo);

        if ($isConfidential) {
            ConfidentialLeaveService::logSensitiveAccess(
                $this->pdo,
                $id,
                null,
                (int)($user['id'] ?? 0) ?: null,
                'view_request',
                $canViewUnmasked,
                client_ip(),
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );
            $req = ConfidentialLeaveService::maskRequestForViewer($req, $user, $this->pdo);
        }

        // Fetch requester's balances & documents for context
        $balances = $this->leaveModel->getBalancesByEmployee((int)$req['employee_id'], (int)date('Y', strtotime($req['start_date'])));
        $docService = new LeaveDocumentService();
        $documents = $canViewUnmasked ? $docService->getDocumentsForRequest($this->pdo, $id) : [];

        $policySnapshot = null;
        if (!empty($req['policy_version_id'])) {
            $pStmt = $this->pdo->prepare("SELECT * FROM leave_policy_versions WHERE id = :pid LIMIT 1");
            $pStmt->execute(['pid' => (int)$req['policy_version_id']]);
            $policySnapshot = $pStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $meta = [];
        if ($canViewUnmasked && !empty($req['application_metadata_json'])) {
            $decoded = json_decode((string)$req['application_metadata_json'], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $this->view('leaves.show', [
            'pageTitle'       => 'Leave Request #LR-' . str_pad((string)$req['id'], 3, '0', STR_PAD_LEFT),
            'req'             => $req,
            'balances'        => $balances,
            'documents'       => $documents,
            'policySnapshot'  => $policySnapshot,
            'requestMetadata' => $meta,
            'isConfidential'  => $isConfidential,
            'canViewUnmasked' => $canViewUnmasked,
            'maxDocBytes'     => $docService->getMaxBytes(),
            'user'            => $user,
        ], 'hr');
    }

    /**
     * Securely download or preview an uploaded leave supporting document.
     * Route: GET /leaves/documents/{id}
     */
    public function downloadDocument(int $id): void
    {
        $user = currentUser();
        $docService = new LeaveDocumentService();
        $res = $docService->resolveAuthorizedDownload(
            $this->pdo,
            $id,
            $user,
            client_ip(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        if (empty($res['ok'])) {
            $status = (int)($res['status'] ?? 403);
            http_response_code($status);
            $template = $status === 404 ? 'errors.404' : 'errors.403';
            $this->view($template, [
                'pageTitle' => $status . ' Access Restricted',
                'message'   => (string)($res['error'] ?? 'You are not authorized to access this document.'),
            ], null);
            return;
        }

        $path = (string)$res['path'];
        $disposition = !empty($_GET['download']) ? 'attachment' : 'inline';
        $safeFilename = str_replace('"', '', (string)$res['download_name']);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . (string)$res['mime']);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . $disposition . '; filename="' . $safeFilename . '"');
        header('Content-Length: ' . (string)filesize($path));
        header('Cache-Control: private, no-store, max-age=0');
        readfile($path);
        exit;
    }

    /**
     * Display Workforce Leave Balances Directory (HR & Admin Access)
     * Route: GET /leaves/balances
     */
    public function balances(): void
    {
        $user = currentUser();
        $year = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $deptId = !empty($_GET['dept']) ? (int)$_GET['dept'] : null;

        $balances = $this->leaveModel->getAllBalances($year, $deptId);
        $departments = $this->departmentModel->getAll();
        $leaveTypes = $this->leaveModel->getTypesWithActivePolicies(false);
        $employees = $this->employeeModel->getAll();

        $canViewConf = $user !== null && ConfidentialLeaveService::userHasConfidentialPermission($user, $this->pdo);
        $silType = null;
        foreach ($leaveTypes as $lt) {
            if (strtoupper((string)($lt['leave_code'] ?? '')) === 'SIL') {
                $silType = $lt;
                break;
            }
        }

        $recentLedger = [];
        try {
            $lStmt = $this->pdo->prepare("
                SELECT lbt.*, e.employee_no, e.first_name, e.last_name, lt.leave_name, lt.leave_code
                FROM leave_balance_transactions lbt
                INNER JOIN employees e ON lbt.employee_id = e.id
                INNER JOIN leave_types lt ON lbt.leave_type_id = lt.id
                WHERE lbt.balance_year = :yr
                ORDER BY lbt.id DESC
                LIMIT 60
            ");
            $lStmt->execute(['yr' => $year]);
            $rows = $lStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $isConf = !empty($r['is_confidential']) || in_array(strtoupper((string)($r['leave_code'] ?? '')), ['VAWC', 'SLW'], true);
                if ($isConf && !$canViewConf) {
                    $r['leave_name'] = 'Statutory Confidential Leave';
                    $r['remarks'] = 'Restricted confidential leave transaction.';
                }
                $recentLedger[] = $r;
            }
        } catch (\Throwable) {
            $recentLedger = [];
        }

        $this->view('leaves.balances', [
            'pageTitle'    => 'Workforce Leave Balances',
            'balances'     => $balances,
            'departments'  => $departments,
            'leaveTypes'   => $leaveTypes,
            'employees'    => $employees,
            'silType'      => $silType,
            'recentLedger' => $recentLedger,
            'selectedYear' => $year,
            'selectedDept' => $deptId,
            'user'         => $user,
        ], 'hr');
    }

    /**
     * Adjust an Employee's Leave Balance Quota (HR & Admin Access)
     * Route: POST /hr/leaves/balances/adjust
     */
    public function adjustBalance(): void
    {
        $user = currentUser();
        $empId = (int)($_POST['employee_id'] ?? 0);
        $leaveTypeId = (int)($_POST['leave_type_id'] ?? 0);
        $year = (int)($_POST['year'] ?? date('Y'));
        $allocatedDays = isset($_POST['allocated_days']) ? (int)$_POST['allocated_days'] : -1;
        $reason = trim((string)($_POST['reason'] ?? ''));

        if ($empId <= 0 || $leaveTypeId <= 0 || $allocatedDays < 0 || empty($reason)) {
            Session::flash('error', 'All adjustment fields (Employee, Leave Type, Year, Non-negative Allocated Days, and Reason) are required.');
            redirect('/leaves/balances?year=' . $year);
            return;
        }

        $ok = $this->leaveModel->adjustBalance($empId, $leaveTypeId, $year, $allocatedDays, (int)$user['id'], $reason);

        if ($ok) {
            $this->activityLog->log(
                $user['id'],
                'LEAVE_BALANCE_ADJUSTED',
                "Adjusted leave balance for Employee #{$empId}, Leave Type #{$leaveTypeId}, Year {$year} to {$allocatedDays} days. Reason: {$reason}",
                'LeaveBalance',
                (string)$empId,
                null,
                [
                    'employee_id'    => $empId,
                    'leave_type_id'  => $leaveTypeId,
                    'year'           => $year,
                    'allocated_days' => $allocatedDays,
                    'reason'         => $reason,
                ],
                'SUCCESS',
                \Core\AuditService::CATEGORY_LEAVE
            );
            Session::flash('success', "Leave balance successfully updated to {$allocatedDays} allocated days for year {$year}.");
        } else {
            $detail = $this->leaveModel->getLastError() ?? "Failed to adjust leave balance for employee #{$empId}.";
            Session::flash('error', $detail);
        }

        redirect('/leaves/balances?year=' . $year);
    }

    /**
     * Initialize Leave Balances for an Employee for a Specific Year (HR & Admin Access)
     * Route: POST /hr/leaves/balances/initialize
     */
    public function initializeBalances(): void
    {
        $user = currentUser();
        $empId = (int)($_POST['employee_id'] ?? 0);
        $year = (int)($_POST['year'] ?? date('Y'));

        if ($empId <= 0 || $year < 2000 || $year > 2100) {
            Session::flash('error', 'Valid Employee and Year are required.');
            redirect('/leaves/balances');
            return;
        }

        $count = $this->leaveModel->initializeBalancesForEmployee($empId, $year, (int)$user['id']);

        $this->activityLog->log(
            $user['id'],
            'LEAVE_BALANCE_INITIALIZED',
            "Initialized {$count} eligible leave quota(s) for Employee #{$empId} for year {$year}",
            'LeaveBalance',
            (string)$empId
        );

        Session::flash('success', "Initialized {$count} eligible leave balance quota(s) for Employee #{$empId} for {$year}.");
        redirect('/leaves/balances?year=' . $year);
    }

    /**
     * Record Service Incentive Leave (SIL) cash conversion reference (annual or separation).
     * Note: Payroll amount computation is outside HRIS scope; stores days & HR reference only.
     * Route: POST /hr/leaves/sil-conversion
     */
    public function recordSilConversion(): void
    {
        $user = currentUser();
        $empId = (int)($_POST['employee_id'] ?? 0);
        $leaveTypeId = (int)($_POST['leave_type_id'] ?? 0);
        $year = (int)($_POST['year'] ?? date('Y'));
        $days = (int)($_POST['days'] ?? 0);
        $scope = trim((string)($_POST['conversion_scope'] ?? 'annual_unused'));
        $reference = trim((string)($_POST['conversion_reference'] ?? ''));
        $remarks = trim((string)($_POST['remarks'] ?? ''));

        if ($empId <= 0 || $leaveTypeId <= 0 || $days <= 0 || $reference === '') {
            Session::flash('error', 'Employee, SIL type, positive conversion days, and HR reference code are required.');
            redirect('/leaves/balances?year=' . $year);
            return;
        }

        try {
            $ledger = new LeaveLedgerService($this->pdo);
            $result = $ledger->recordSilConversion(
                $empId,
                $leaveTypeId,
                $year,
                $days,
                $scope,
                $reference,
                $remarks,
                (int)$user['id']
            );

            $this->activityLog->log(
                $user['id'],
                'SIL_CONVERSION_RECORDED',
                "Recorded {$days}-day SIL ({$scope}) cash-conversion ref {$reference} for Employee #{$empId} ({$year}).",
                'LeaveBalance',
                (string)$empId
            );

            Session::flash(
                'success',
                "SIL conversion recorded ({$days} day(s), ref: {$reference}). Remaining SIL balance: {$result['remaining_after']} day(s)."
            );
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        redirect('/leaves/balances?year=' . $year);
    }

    /**
     * HR Verification Console for Statutory Qualifications (SPIC, Paternity Marital Proof, SIL Exclusions)
     * Route: GET /hr/leave-qualifications
     */
    public function qualifications(): void
    {
        $status = !empty($_GET['status']) ? trim((string)$_GET['status']) : null;
        $qtype = !empty($_GET['type']) ? trim((string)$_GET['type']) : null;

        $qualifications = $this->leaveModel->getAllQualificationsForHr($status, $qtype);
        $employees = $this->employeeModel->getAll();

        $this->view('leaves.qualifications', [
            'pageTitle'      => 'Statutory Leave Qualifications & SPIC Verification',
            'qualifications' => $qualifications,
            'employees'      => $employees,
            'selectedStatus' => $status,
            'selectedType'   => $qtype,
            'user'           => currentUser(),
        ], 'hr');
    }

    /**
     * Save or Verify an Employee Statutory Leave Qualification (HR & Admin Access)
     * Route: POST /hr/leave-qualifications/verify
     */
    public function verifyQualification(): void
    {
        $user = currentUser();
        $empId = (int)($_POST['employee_id'] ?? 0);
        if ($empId <= 0) {
            Session::flash('error', 'Please select a valid employee.');
            redirect('/hr/leave-qualifications');
            return;
        }

        $qtype = trim((string)($_POST['qualification_type'] ?? 'solo_parent_spic'));
        $vstatus = trim((string)($_POST['verification_status'] ?? 'Verified'));

        $qualId = $this->leaveModel->upsertQualification([
            'id'                  => !empty($_POST['id']) ? (int)$_POST['id'] : null,
            'employee_id'         => $empId,
            'qualification_type'  => $qtype,
            'verification_status' => $vstatus,
            'reference_no'        => trim((string)($_POST['reference_no'] ?? '')),
            'valid_from'          => trim((string)($_POST['valid_from'] ?? '')),
            'valid_to'            => trim((string)($_POST['valid_to'] ?? '')),
            'sil_exempt_override' => !empty($_POST['sil_exempt_override']) ? 1 : 0,
            'exclusion_reason'    => trim((string)($_POST['exclusion_reason'] ?? '')),
            'supporting_notes'    => trim((string)($_POST['supporting_notes'] ?? '')),
        ], (int)$user['id']);

        if ($qualId > 0) {
            $this->activityLog->log(
                $user['id'],
                'LEAVE_QUALIFICATION_UPDATED',
                "Updated {$qtype} qualification #{$qualId} to {$vstatus} for Employee #{$empId}",
                'LeaveQualification',
                (string)$qualId
            );
            Session::flash('success', "Statutory qualification #{$qualId} ({$vstatus}) saved successfully.");
        } else {
            Session::flash('error', 'Unable to save statutory leave qualification record.');
        }

        redirect('/hr/leave-qualifications');
    }

    /**
     * Leave Type Management Directory (HR & Admin Access)
     * Route: GET /hr/leave-types
     */
    public function types(): void
    {
        $status = !empty($_GET['status']) ? trim((string)$_GET['status']) : null;
        $leaveTypes = $this->leaveModel->getTypesWithActivePolicies($status === 'Active');
        if ($status === 'Inactive') {
            $leaveTypes = array_values(array_filter(
                $leaveTypes,
                static fn (array $t): bool => ($t['status'] ?? '') === 'Inactive'
            ));
        }

        $this->view('leave_types.index', [
            'pageTitle'      => 'Leave Classifications & Policy Management',
            'leaveTypes'     => $leaveTypes,
            'selectedStatus' => $status,
            'user'           => currentUser(),
        ], 'hr');
    }

    /**
     * Show Create Leave Type Form (HR & Admin Access)
     * Route: GET /hr/leave-types/create
     */
    public function createType(): void
    {
        $this->view('leave_types.create', [
            'pageTitle' => 'Create Leave Classification',
            'user'      => currentUser(),
        ], 'hr');
    }

    /**
     * Store New Leave Type (HR & Admin Access)
     * Route: POST /hr/leave-types
     */
    public function storeType(): void
    {
        $user = currentUser();
        $name = trim((string)($_POST['leave_name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $defaultDays = (int)($_POST['default_days'] ?? 0);
        $rawStatus = trim((string)($_POST['status'] ?? 'Active'));
        $status = in_array($rawStatus, ['Active', 'Inactive'], true) ? $rawStatus : 'Active';
        $model = trim((string)($_POST['entitlement_model'] ?? 'annual'));
        $minDays = ($model === 'unpaid') ? 0 : 1;

        if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
            Session::flash('error', 'Leave classification name must be between 2 and 100 characters.');
            redirect('/hr/leave-types/create');
            return;
        }

        if (!$this->leaveModel->isLeaveTypeNameUnique($name)) {
            Session::flash('error', "A leave type named '{$name}' already exists. Please specify a unique name.");
            redirect('/hr/leave-types/create');
            return;
        }

        if ($defaultDays < $minDays || $defaultDays > 365) {
            Session::flash('error', "Default entitlement quota must be between {$minDays} and 365 days.");
            redirect('/hr/leave-types/create');
            return;
        }

        $id = $this->leaveModel->createLeaveType([
            'leave_name'               => $name,
            'leave_code'               => trim((string)($_POST['leave_code'] ?? '')),
            'description'              => $description,
            'classification'           => trim((string)($_POST['classification'] ?? 'Company')),
            'benefit_category'         => trim((string)($_POST['benefit_category'] ?? '')),
            'entitlement_model'        => $model,
            'legal_reference'          => trim((string)($_POST['legal_reference'] ?? '')),
            'default_days'             => $defaultDays,
            'default_unit'             => trim((string)($_POST['default_unit'] ?? 'working_days')),
            'is_paid'                  => !empty($_POST['is_paid']) ? 1 : 0,
            'is_confidential'          => !empty($_POST['is_confidential']) ? 1 : 0,
            'statutory_protected'      => !empty($_POST['statutory_protected']) ? 1 : 0,
            'min_service_months'       => (int)($_POST['min_service_months'] ?? 0),
            'counts_as_sil_equivalent' => !empty($_POST['counts_as_sil_equivalent']) ? 1 : 0,
            'cash_convertible'         => !empty($_POST['cash_convertible']) ? 1 : 0,
            'requires_documentation'   => !empty($_POST['requires_documentation']) ? 1 : 0,
            'document_requirements'    => trim((string)($_POST['document_requirements'] ?? '')),
            'effective_from'           => trim((string)($_POST['effective_from'] ?? date('Y-m-d'))),
            'status'                   => $status,
            'actor_user_id'            => (int)$user['id'],
        ]);

        $this->activityLog->log($user['id'], 'LEAVE_TYPE_CREATED', "Created leave classification '{$name}' with default quota of {$defaultDays} days", 'LeaveType', (string)$id);
        Session::flash('success', "Leave classification '{$name}' created successfully.");
        redirect('/hr/leave-types');
    }

    /**
     * Show Edit Leave Type & Policy Versions Form (HR & Admin Access)
     * Route: GET /hr/leave-types/{id}/edit
     */
    public function editType(int $id): void
    {
        $engine = new LeavePolicyEngine($this->pdo);
        $type = $engine->resolveLeaveTypeWithPolicy($id, date('Y-m-d')) ?? $this->leaveModel->getLeaveTypeById($id);
        if (!$type) {
            Session::flash('error', "Leave classification #{$id} was not found.");
            redirect('/hr/leave-types');
            return;
        }

        $policyVersions = $this->leaveModel->getPoliciesForType($id);

        $this->view('leave_types.edit', [
            'pageTitle'      => "Edit Leave Classification - {$type['leave_name']}",
            'type'           => $type,
            'policyVersions' => $policyVersions,
            'user'           => currentUser(),
        ], 'hr');
    }

    /**
     * Update Leave Type (HR & Admin Access)
     * Route: POST /hr/leave-types/{id}/edit
     */
    public function updateType(int $id): void
    {
        $user = currentUser();
        $type = $this->leaveModel->getLeaveTypeById($id);
        if (!$type) {
            Session::flash('error', "Leave classification #{$id} not found.");
            redirect('/hr/leave-types');
            return;
        }

        $name = trim((string)($_POST['leave_name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $defaultDays = (int)($_POST['default_days'] ?? 0);
        $rawStatus = trim((string)($_POST['status'] ?? 'Active'));
        $status = in_array($rawStatus, ['Active', 'Inactive'], true) ? $rawStatus : 'Active';
        $model = trim((string)($_POST['entitlement_model'] ?? ($type['entitlement_model'] ?? 'annual')));
        $minDays = ($model === 'unpaid') ? 0 : 1;

        if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
            Session::flash('error', 'Leave classification name must be between 2 and 100 characters.');
            redirect("/hr/leave-types/{$id}/edit");
            return;
        }

        if (!$this->leaveModel->isLeaveTypeNameUnique($name, $id)) {
            Session::flash('error', "Another leave type is already named '{$name}'. Please specify a unique name.");
            redirect("/hr/leave-types/{$id}/edit");
            return;
        }

        if ($defaultDays < $minDays || $defaultDays > 365) {
            Session::flash('error', "Default entitlement quota must be between {$minDays} and 365 days.");
            redirect("/hr/leave-types/{$id}/edit");
            return;
        }

        $this->leaveModel->updateLeaveType($id, [
            'leave_name'          => $name,
            'leave_code'          => trim((string)($_POST['leave_code'] ?? ($type['leave_code'] ?? ''))),
            'description'         => $description,
            'classification'      => trim((string)($_POST['classification'] ?? ($type['classification'] ?? 'Company'))),
            'benefit_category'    => trim((string)($_POST['benefit_category'] ?? ($type['benefit_category'] ?? 'CompanyAnnual'))),
            'entitlement_model'   => $model,
            'legal_reference'     => trim((string)($_POST['legal_reference'] ?? ($type['legal_reference'] ?? ''))),
            'default_days'        => $defaultDays,
            'default_unit'        => trim((string)($_POST['default_unit'] ?? ($type['default_unit'] ?? 'working_days'))),
            'is_paid'             => $model === 'unpaid' ? 0 : (isset($_POST['is_paid']) ? 1 : (int)($type['is_paid'] ?? 1)),
            'is_confidential'     => !empty($_POST['is_confidential']) ? 1 : 0,
            'statutory_protected' => !empty($_POST['statutory_protected']) ? 1 : 0,
            'status'              => $status,
            'actor_user_id'       => (int)$user['id'],
        ]);

        $this->activityLog->log($user['id'], 'LEAVE_TYPE_UPDATED', "Updated leave classification #{$id} ('{$name}')", 'LeaveType', (string)$id);
        Session::flash('success', "Leave classification '{$name}' updated successfully.");
        redirect("/hr/leave-types/{$id}/edit");
    }

    /**
     * Create a new non-retroactive policy version for a leave classification.
     * Route: POST /hr/leave-types/{id}/policies
     */
    public function storePolicyVersion(int $id): void
    {
        $user = currentUser();
        $type = $this->leaveModel->getLeaveTypeById($id);
        if (!$type) {
            Session::flash('error', "Leave classification #{$id} not found.");
            redirect('/hr/leave-types');
            return;
        }

        $activateNow = !empty($_POST['activate_immediately']);
        $policyId = $this->leaveModel->createPolicyVersion($id, [
            'effective_from'           => trim((string)($_POST['effective_from'] ?? date('Y-m-d'))),
            'effective_to'             => trim((string)($_POST['effective_to'] ?? '')),
            'approval_status'          => trim((string)($_POST['approval_status'] ?? 'Draft')),
            'benefit_tier'             => trim((string)($_POST['benefit_tier'] ?? '')),
            'sector_scope'             => trim((string)($_POST['sector_scope'] ?? 'all')),
            'legal_reference'          => trim((string)($_POST['legal_reference'] ?? ($type['legal_reference'] ?? ''))),
            'entitlement_days'         => (float)($_POST['entitlement_days'] ?? $type['default_days']),
            'entitlement_unit'         => trim((string)($_POST['entitlement_unit'] ?? ($type['default_unit'] ?? 'working_days'))),
            'min_service_months'       => (int)($_POST['min_service_months'] ?? 0),
            'carry_over_allowed'       => !empty($_POST['carry_over_allowed']) ? 1 : 0,
            'max_carry_over_days'      => (float)($_POST['max_carry_over_days'] ?? 0),
            'cash_convertible'         => !empty($_POST['cash_convertible']) ? 1 : 0,
            'cash_conversion_max_days' => (float)($_POST['cash_conversion_max_days'] ?? 0),
            'counts_as_sil_equivalent' => !empty($_POST['counts_as_sil_equivalent']) ? 1 : 0,
            'stack_with_equivalent'    => !empty($_POST['stack_with_equivalent']) ? 1 : 0,
            'requires_documentation'   => !empty($_POST['requires_documentation']) ? 1 : 0,
            'document_requirements'    => trim((string)($_POST['document_requirements'] ?? '')),
            'requires_qualification'   => !empty($_POST['requires_qualification']) ? 1 : 0,
            'qualification_type'       => trim((string)($_POST['qualification_type'] ?? '')),
            'change_notes'             => trim((string)($_POST['change_notes'] ?? 'Policy revision submitted via HR Leave Configuration.')),
        ], (int)$user['id'], $activateNow);

        if ($policyId > 0) {
            $this->activityLog->log(
                $user['id'],
                'LEAVE_POLICY_VERSION_CREATED',
                "Created policy version #{$policyId} for '{$type['leave_name']}'" . ($activateNow ? ' and activated it.' : ' as Draft/PendingReview.'),
                'LeavePolicyVersion',
                (string)$policyId
            );
            Session::flash('success', $activateNow
                ? 'New policy version created and activated. Prior balances and submitted requests remain unaffected.'
                : 'New draft/pending policy version saved without changing active rules.');
        } else {
            Session::flash('error', 'Failed to create policy version.');
        }

        redirect("/hr/leave-types/{$id}/edit");
    }

    /**
     * Activate a Draft/PendingReview policy version (supersedes previous Active version).
     * Route: POST /hr/leave-policies/{id}/activate
     */
    public function activatePolicy(int $policyId): void
    {
        $user = currentUser();
        $stmt = $this->pdo->prepare("SELECT * FROM leave_policy_versions WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $policyId]);
        $policy = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$policy) {
            Session::flash('error', "Policy version #{$policyId} was not found.");
            redirect('/hr/leave-types');
            return;
        }

        $ok = $this->leaveModel->activatePolicyVersion($policyId, (int)$user['id']);
        if ($ok) {
            $this->activityLog->log(
                $user['id'],
                'LEAVE_POLICY_ACTIVATED',
                "Activated policy version {$policy['policy_code']} (#{$policyId}) without retroactive mutation.",
                'LeavePolicyVersion',
                (string)$policyId
            );
            Session::flash('success', "Policy version {$policy['policy_code']} is now Active (historical records preserved).");
        } else {
            Session::flash('error', "Unable to activate policy version #{$policyId}.");
        }

        redirect('/hr/leave-types/' . (int)$policy['leave_type_id'] . '/edit');
    }

    /**
     * Toggle Leave Type Active / Inactive Status (Soft Deactivation / Reactivation)
     * Route: POST /hr/leave-types/{id}/toggle-status
     */
    public function toggleTypeStatus(int $id): void
    {
        $user = currentUser();
        $type = $this->leaveModel->getLeaveTypeById($id);

        if (!$type) {
            Session::flash('error', "Leave classification #{$id} not found.");
            redirect('/hr/leave-types');
            return;
        }

        $this->leaveModel->toggleLeaveTypeStatus($id);
        $newStatus = ($type['status'] === 'Active') ? 'Inactive' : 'Active';

        $this->activityLog->log(
            $user['id'],
            'LEAVE_TYPE_STATUS_TOGGLED',
            "Changed status of leave classification '{$type['leave_name']}' to {$newStatus}",
            'LeaveType',
            (string)$id
        );

        Session::flash('success', "Leave classification '{$type['leave_name']}' is now {$newStatus}.");
        redirect('/hr/leave-types');
    }

    /**
     * Show File Leave Request Form (Employee Access)
     * Route: GET /leaves/apply
     */
    public function apply(): void
    {
        $user = currentUser();
        $empId = $user['employee_id'] ?? null;

        if (!$empId) {
            Session::flash('error', 'No linked employee record found for your account. Please contact HR.');
            redirect('/dashboard');
            return;
        }

        $employee = $this->employeeModel->findById((int)$empId);
        if (!$employee || ($employee['employment_status'] ?? '') !== 'Active') {
            Session::flash('error', 'Unable to file leave: Your employment account is inactive. Please contact HR.');
            redirect('/dashboard');
            return;
        }

        $year = (int)date('Y');
        $policyEngine = new LeavePolicyEngine($this->pdo);
        $rawTypes = $policyEngine->getAllTypesWithActivePolicies(true, date('Y-m-d'));
        $balances = $this->leaveModel->getBalancesByEmployee((int)$empId, $year);
        $balanceMap = [];
        foreach ($balances as $bal) {
            $balanceMap[(int)$bal['leave_type_id']] = $bal;
        }

        $leaveTypes = [];
        foreach ($rawTypes as $lt) {
            $ltId = (int)$lt['id'];
            $elig = $policyEngine->evaluateEmployeeTypeEligibility($employee, $lt, $year, date('Y-m-d'));
            if (empty($elig['eligible'])) {
                continue;
            }
            $lt['eligibility'] = $elig;
            $lt['balance_remaining'] = isset($balanceMap[$ltId]) ? (int)$balanceMap[$ltId]['remaining_days'] : null;
            $lt['balance_allocated'] = isset($balanceMap[$ltId]) ? (int)$balanceMap[$ltId]['allocated_days'] : null;
            if (strtoupper((string)($lt['leave_code'] ?? '')) === 'PATERNITY') {
                $lt['deliveries_used'] = $policyEngine->countPaternityDeliveries((int)$empId, $ltId);
            }
            $leaveTypes[] = $lt;
        }

        $docService = new LeaveDocumentService();
        $qualifications = $this->leaveModel->getQualificationsByEmployee((int)$empId);

        $this->view('leaves.apply', [
            'pageTitle'      => 'File Leave Request',
            'leaveTypes'     => $leaveTypes,
            'balances'       => $balances,
            'employee'       => $employee,
            'qualifications' => $qualifications,
            'maxDocBytes'    => $docService->getMaxBytes(),
            'user'           => $user,
        ], 'hr');
    }

    /**
     * Process Leave Request Submission (Employee Access)
     * Route: POST /leaves/apply
     */
    public function store(): void
    {
        $user = currentUser();
        $empId = $user['employee_id'] ?? null;

        if (!$empId) {
            Session::flash('error', 'Unable to submit leave: No linked employee record.');
            redirect('/dashboard');
            return;
        }

        $employee = $this->employeeModel->findById((int)$empId);
        if (!$employee || ($employee['employment_status'] ?? '') !== 'Active') {
            Session::flash('error', 'Unable to submit leave request: Your employment account is inactive. Please contact HR.');
            redirect('/dashboard');
            return;
        }

        $leaveTypeId = (int)($_POST['leave_type_id'] ?? 0);
        $startDate = trim((string)($_POST['start_date'] ?? ''));
        $endDate = trim((string)($_POST['end_date'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        if (!$leaveTypeId || empty($startDate) || empty($endDate) || empty($reason)) {
            Session::flash('error', 'All required fields (Leave Classification, Start Date, End Date, and Reason) must be completed.');
            redirect('/leaves/apply');
            return;
        }

        $policyEngine = new LeavePolicyEngine($this->pdo);
        $typePolicy = $policyEngine->resolveLeaveTypeWithPolicy($leaveTypeId, $startDate) ?? $this->leaveModel->getLeaveTypeById($leaveTypeId);
        $leaveTypeName = $typePolicy['leave_name'] ?? 'leave';
        $leaveCode = strtoupper(trim((string)($typePolicy['leave_code'] ?? '')));
        $isConfidential = !empty($typePolicy['is_confidential']) || ConfidentialLeaveService::isConfidentialType($typePolicy ?: []);

        // Validate optional or required uploaded supporting document BEFORE mutating DB
        $docService = new LeaveDocumentService();
        $uploadedFile = $_FILES['supporting_document'] ?? null;
        $hasUpload = is_array($uploadedFile) && (int)($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hasUpload) {
            $docCheck = $docService->validateUploadedFile($uploadedFile);
            if (empty($docCheck['ok'])) {
                Session::flash('error', (string)($docCheck['error'] ?? 'Supporting document upload failed validation.'));
                redirect('/leaves/apply');
                return;
            }
        }

        $meta = $this->extractFilingMetadata($_POST, $leaveCode, $hasUpload);
        $onFiledMessage = ConfidentialLeaveService::safeNotificationMessage(
            $isConfidential,
            0,
            "Employee {$user['username']} submitted a {$leaveTypeName} request."
        );

        $result = $this->leaveAdapter->file(
            (int)$empId,
            $leaveTypeId,
            $startDate,
            $endDate,
            $reason,
            function (int $newId) use ($user, $leaveTypeName, $isConfidential, $onFiledMessage): void {
                $msg = $isConfidential
                    ? ConfidentialLeaveService::safeNotificationMessage($isConfidential, $newId, $onFiledMessage)
                    : "Employee {$user['username']} submitted a {$leaveTypeName} request #LR-" . str_pad((string)$newId, 3, '0', STR_PAD_LEFT) . ".";
                $this->leaveNotifications->leaveSubmitted($newId, $msg, $isConfidential);
            },
            null,
            $meta
        );

        if (!$result->success) {
            Session::flash('error', $result->message);
            redirect('/leaves/apply');
            return;
        }

        $requestId = (int)($result->data['id'] ?? 0);

        if ($hasUpload && $requestId > 0) {
            $docCat = trim((string)($_POST['document_category'] ?? 'supporting_document'));
            $storeRes = $docService->storeForRequest(
                $this->pdo,
                $requestId,
                (int)$empId,
                $uploadedFile,
                $docCat !== '' ? $docCat : 'supporting_document',
                (int)$user['id'],
                $isConfidential
            );
            if (empty($storeRes['ok'])) {
                error_log('[LeaveController::store] Supporting document storage failed after request #' . $requestId . ': ' . ($storeRes['error'] ?? 'unknown'));
            }
        }

        $activityDesc = ConfidentialLeaveService::safeActivityDescription(
            $isConfidential,
            $requestId,
            'Submitted',
            "Submitted leave request #{$requestId} for {$leaveTypeName}"
        );
        $this->activityLog->log($user['id'], 'LEAVE_REQUESTED', $activityDesc, 'LeaveRequest', (string)$requestId);

        Session::flash('success', 'Leave request #LR-' . str_pad((string)$requestId, 3, '0', STR_PAD_LEFT) . ' submitted successfully. Status is Pending HR review.');
        redirect('/employee/leave-requests');
    }

    /**
     * HR Request Additional Information / Clarification without Rejecting
     * Route: POST /leaves/{id}/request-info
     */
    public function requestInfo(int $id): void
    {
        $user = currentUser();
        if (!$user || !in_array(strtolower((string)($user['role'] ?? '')), ['admin', 'hr'], true)) {
            Session::flash('error', 'Forbidden: HR or Administrator role is required.');
            redirect('/dashboard');
            return;
        }

        $note = trim((string)($_POST['info_request_note'] ?? ''));
        $res = $this->leaveModel->requestAdditionalInfo($id, (int)$user['id'], $note);

        if ($res['ok']) {
            $req = $this->leaveModel->getRequestById($id);
            $isConf = $req && ConfidentialLeaveService::isConfidentialRequest($req);
            $desc = ConfidentialLeaveService::safeActivityDescription(
                $isConf,
                $id,
                'Requested clarification for',
                "Requested additional information on leave request #{$id}"
            );
            $this->activityLog->log($user['id'], 'LEAVE_INFO_REQUESTED', $desc, 'LeaveRequest', (string)$id);
            Session::flash('info', $res['message']);
        } else {
            Session::flash('error', $res['message']);
        }

        redirect('/leaves/' . $id);
    }

    /**
     * Employee Responds to HR Additional Information Request (with optional attachment)
     * Route: POST /leaves/{id}/respond-info
     */
    public function respondInfo(int $id): void
    {
        $user = currentUser();
        $myEmpId = (int)($user['employee_id'] ?? 0);
        $req = $this->leaveModel->getRequestById($id);

        if (!$req || $myEmpId <= 0 || (int)$req['employee_id'] !== $myEmpId) {
            http_response_code(403);
            Session::flash('error', 'You are not authorized to respond to this leave request.');
            redirect('/employee/leave-requests');
            return;
        }

        $responseNote = trim((string)($_POST['employee_info_response'] ?? ''));
        $docService = new LeaveDocumentService();
        $uploadedFile = $_FILES['supporting_document'] ?? null;
        $hasUpload = is_array($uploadedFile) && (int)($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hasUpload) {
            $docCheck = $docService->validateUploadedFile($uploadedFile);
            if (empty($docCheck['ok'])) {
                Session::flash('error', (string)($docCheck['error'] ?? 'Document validation failed.'));
                redirect('/leaves/' . $id);
                return;
            }
        }

        $res = $this->leaveModel->respondToInfoRequest($id, $myEmpId, $responseNote, (int)$user['id']);
        $isConf = ConfidentialLeaveService::isConfidentialRequest($req);

        if ($hasUpload) {
            $docService->storeForRequest(
                $this->pdo,
                $id,
                $myEmpId,
                $uploadedFile,
                'clarification_attachment',
                (int)$user['id'],
                $isConf
            );
        }

        if ($res['ok']) {
            $hrMsg = $isConf
                ? 'An employee uploaded a clarification response on confidential leave request #' . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . '.'
                : "Employee {$user['username']} responded to your clarification request on #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . ".";
            $this->notificationModel->notifyRole('hr', 'Leave Clarification Received', $hrMsg, 'info');
            Session::flash('success', $res['message']);
        } else {
            Session::flash('error', $res['message']);
        }

        redirect('/leaves/' . $id);
    }

    /**
     * Approve Leave Request (HR & Admin Access)
     * Route: POST /leaves/{id}/approve
     */
    public function approve(int $id): void
    {
        $user = currentUser();
        if (!$user || !in_array(strtolower((string)($user['role'] ?? '')), ['admin', 'hr'], true)) {
            Session::flash('error', 'Forbidden: Only authorized HR or Administrator personnel may approve leave requests.');
            redirect('/dashboard');
            return;
        }

        $result = $this->leaveAdapter->approve($id, (int)$user['id']);

        if ($result->success) {
            $req = $this->leaveModel->getRequestById($id);
            $isConf = $req ? ConfidentialLeaveService::isConfidentialRequest($req) : false;
            if ($req) {
                $defaultNotif = "Your leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . " ({$req['number_of_days']} days for {$req['leave_name']}) was approved by HR.";
                $this->leaveNotifications->leaveDecided(
                    $req,
                    'Approved',
                    ConfidentialLeaveService::safeNotificationMessage($isConf, $id, $defaultNotif),
                    $isConf
                );
            }
            $logDesc = ConfidentialLeaveService::safeActivityDescription(
                $isConf,
                $id,
                'Approved',
                "Approved leave request #{$id}"
            );
            $this->activityLog->log(
                $user['id'],
                'LEAVE_APPROVED',
                $logDesc,
                'LeaveRequest',
                (string)$id,
                ['status' => 'Pending'],
                ['status' => 'Approved'],
                'SUCCESS',
                \Core\AuditService::CATEGORY_LEAVE
            );
            Session::flash('success', $result->message);
        } else {
            Session::flash('error', $result->message);
        }

        redirect('/leaves');
    }

    /**
     * Process Next Pending Leave Request in FIFO Queue Order (HR & Admin Access)
     * Route: POST /leaves/queue/next
     */
    public function processNextQueue(): void
    {
        $user = currentUser();
        if (!$user || !in_array(strtolower((string)($user['role'] ?? '')), ['admin', 'hr'], true)) {
            Session::flash('error', 'Forbidden: Only authorized HR or Administrator personnel may approve leave requests.');
            redirect('/dashboard');
            return;
        }

        $deptId = !empty($_POST['dept_id']) ? (int)$_POST['dept_id'] : null;
        $queueService = new LeaveApprovalQueueService($this->leaveModel);
        $queue = $queueService->buildPendingApprovalQueue($deptId, 50);

        if ($queue->isEmpty()) {
            Session::flash('info', 'No pending leave requests in the queue to process.');
            redirect('/leaves?status=Pending');
            return;
        }

        $result = $queueService->processNextApproval($queue, (int)$user['id']);

        if ($result['success']) {
            Session::flash('success', "FIFO Queue Success: {$result['message']}");
        } else {
            Session::flash('error', "FIFO Queue Processing Error: {$result['message']}");
        }

        redirect('/leaves?status=Pending');
    }

    /**
     * Reject Leave Request with Reason (HR & Admin Access)
     * Route: POST /leaves/{id}/reject
     */
    public function reject(int $id): void
    {
        $user = currentUser();
        if (!$user || !in_array(strtolower((string)($user['role'] ?? '')), ['admin', 'hr'], true)) {
            Session::flash('error', 'Forbidden: Only authorized HR or Administrator personnel may reject leave requests.');
            redirect('/dashboard');
            return;
        }

        $rejectionReason = trim((string)($_POST['rejection_reason'] ?? ''));
        if (empty($rejectionReason)) {
            $rejectionReason = 'Request declined per HR department review.';
        }

        $result = $this->leaveAdapter->reject($id, (int)$user['id'], $rejectionReason);

        if ($result->success) {
            $req = $this->leaveModel->getRequestById($id);
            $isConf = $req ? ConfidentialLeaveService::isConfidentialRequest($req) : false;
            if ($req) {
                $defaultNotif = "Your leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . " was rejected. Reason: {$rejectionReason}";
                $this->leaveNotifications->leaveDecided(
                    $req,
                    'Rejected',
                    ConfidentialLeaveService::safeNotificationMessage($isConf, $id, $defaultNotif),
                    $isConf
                );
            }
            $logDesc = ConfidentialLeaveService::safeActivityDescription(
                $isConf,
                $id,
                'Rejected',
                "Rejected leave request #{$id}. Reason: {$rejectionReason}"
            );
            $this->activityLog->log(
                $user['id'],
                'LEAVE_REJECTED',
                $logDesc,
                'LeaveRequest',
                (string)$id,
                ['status' => 'Pending'],
                $isConf ? ['status' => 'Rejected'] : ['status' => 'Rejected', 'reason' => $rejectionReason],
                'SUCCESS',
                \Core\AuditService::CATEGORY_LEAVE
            );
            Session::flash('warning', $result->message);
        } else {
            Session::flash('error', $result->message);
        }

        redirect('/leaves');
    }

    /**
     * Cancel an Employee's Own Pending Leave Request
     * Route: POST /leaves/{id}/cancel
     * Access: Employee (must own record) or HR/Admin
     */
    public function cancel(int $id): void
    {
        $user = currentUser();
        $userRole = strtolower($user['role'] ?? 'employee');
        $myEmpId = (int)($user['employee_id'] ?? 0);

        $isHr = in_array($userRole, ['admin', 'hr'], true);

        $req = $this->leaveModel->getRequestById($id);
        $wasApproved = $req && ($req['status'] ?? '') === 'Approved';
        $isConf = $req && ConfidentialLeaveService::isConfidentialRequest($req);

        $reason = $isHr ? trim((string)($_POST['cancellation_reason'] ?? '')) : null;

        $result = $this->leaveAdapter->cancel($id, $myEmpId, $isHr, $reason);

        if ($result->success) {
            $logReason = ($reason !== null && $reason !== '') ? " Reason: {$reason}" : '';
            $defaultDesc = "Cancelled leave request #{$id}" . ($wasApproved ? ' (approved leave reversed, balance restored).' : '.') . $logReason;
            $logDesc = ConfidentialLeaveService::safeActivityDescription($isConf, $id, 'Cancelled', $defaultDesc);

            $this->activityLog->log(
                $user['id'],
                'LEAVE_CANCELLED',
                $logDesc,
                'LeaveRequest',
                (string)$id,
                ['status' => $wasApproved ? 'Approved' : 'Pending'],
                $isConf ? ['status' => 'Cancelled'] : ['status' => 'Cancelled', 'reason' => $reason],
                'SUCCESS',
                \Core\AuditService::CATEGORY_LEAVE
            );

            if ($userRole === 'employee') {
                $hrMsg = $isConf
                    ? 'An employee withdrew pending leave request #LR-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . '.'
                    : "Employee {$user['username']} cancelled pending leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . ".";
                $this->notificationModel->notifyRole(
                    'hr',
                    ConfidentialLeaveService::safeNotificationTitle($isConf, 'Leave Request Cancelled'),
                    $hrMsg,
                    'info'
                );
            } elseif ($req && !empty($req['employee_id'])) {
                $ownerUser = $this->userModel->findByEmployeeId((int)$req['employee_id']);
                if ($ownerUser && !empty($ownerUser['id'])) {
                    $detail = $wasApproved
                        ? "Your approved leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . " was cancelled by HR and the leave days were restored to your balance."
                        : "Your pending leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . " was cancelled by HR.";
                    if ($reason !== null && $reason !== '') {
                        $detail .= " Reason: {$reason}";
                    }
                    $this->notificationModel->create(
                        (int)$ownerUser['id'],
                        ConfidentialLeaveService::safeNotificationTitle($isConf, 'Leave Request Cancelled'),
                        ConfidentialLeaveService::safeNotificationMessage($isConf, $id, $detail),
                        'warning'
                    );
                }
            }

            Session::flash('info', $result->message);
        } else {
            Session::flash('error', $result->message);
        }

        if ($userRole === 'employee') {
            $requestedRedirect = trim((string)($_POST['redirect_to'] ?? ''));
            $allowedRedirects = ['/employee/leave-requests', '/employee/dashboard'];
            $target = in_array($requestedRedirect, $allowedRedirects, true)
                ? $requestedRedirect
                : '/employee/leave-requests';
            redirect($target);
        } else {
            redirect('/leaves');
        }
    }

    /**
     * Extract structured statutory/company metadata cleanly from submission POST.
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function extractFilingMetadata(array $post, string $leaveCode, bool $hasUpload): array
    {
        $meta = [
            '_strict_form' => true,
            '_has_doc'     => $hasUpload,
        ];

        $eventDate = trim((string)($post['event_date'] ?? ''));
        if ($eventDate !== '') {
            $meta['event_date'] = $eventDate;
        }

        if ($leaveCode === 'MATERNITY') {
            $meta['maternity_scenario'] = trim((string)($post['maternity_scenario'] ?? 'live_childbirth'));
            $meta['unpaid_extension_days'] = max(0, min(30, (int)($post['unpaid_extension_days'] ?? 0)));
            $meta['transfer_to_father_days'] = max(0, min(7, (int)($post['transfer_to_father_days'] ?? 0)));
            $motherRef = trim((string)($post['mother_allocation_ref'] ?? ''));
            if ($motherRef !== '') {
                $meta['mother_allocation_ref'] = substr($motherRef, 0, 120);
            }
        } elseif ($leaveCode === 'PATERNITY') {
            $seq = isset($post['delivery_sequence']) && $post['delivery_sequence'] !== ''
                ? (int)$post['delivery_sequence']
                : null;
            if ($seq !== null) {
                $meta['delivery_sequence'] = $seq;
            }
            $spouse = trim((string)($post['spouse_name'] ?? ''));
            if ($spouse !== '') {
                $meta['spouse_name'] = substr($spouse, 0, 120);
            }
        } elseif ($leaveCode === 'SLW') {
            $physicianRef = trim((string)($post['physician_cert_ref'] ?? ''));
            if ($physicianRef !== '') {
                $meta['physician_cert_ref'] = substr($physicianRef, 0, 120);
            }
            if (!empty($post['has_physician_cert'])) {
                $meta['has_physician_cert'] = true;
            }
        } elseif ($leaveCode === 'VAWC') {
            $poRef = trim((string)($post['protection_order_ref'] ?? ''));
            if ($poRef !== '') {
                $meta['protection_order_ref'] = substr($poRef, 0, 120);
            }
            $certRef = trim((string)($post['certification_ref'] ?? ''));
            if ($certRef !== '') {
                $meta['certification_ref'] = substr($certRef, 0, 120);
            }
            $meta['vawc_extension_days'] = max(0, (int)($post['vawc_extension_days'] ?? 0));
        } elseif ($leaveCode === 'BEREAVEMENT') {
            $rel = trim((string)($post['relationship_type'] ?? ''));
            if ($rel !== '') {
                $meta['relationship_type'] = $rel;
            }
            $deceased = trim((string)($post['deceased_name'] ?? ''));
            if ($deceased !== '') {
                $meta['deceased_name'] = substr($deceased, 0, 120);
            }
        }

        return $meta;
    }
}
