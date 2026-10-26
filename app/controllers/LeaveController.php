<?php
/**
 * Leave Management Controller
 * Handles leave applications, approvals, rejections, cancellations, balances, and leave types
 * Employee HRIS & Leave Management System - Phase 8
 */

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\Leave;
use App\Models\Employee;
use App\Models\Department;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use App\Services\LeaveCalculator;
use App\Services\LeaveNotificationService;

class LeaveController extends Controller
{
    private Leave $leaveModel;
    private Employee $employeeModel;
    private Department $departmentModel;
    private ActivityLog $activityLog;
    private Notification $notificationModel;
    private User $userModel;
    private LeaveNotificationService $leaveNotifications;

    public function __construct()
    {
        $this->leaveModel = new Leave();
        $this->employeeModel = new Employee();
        $this->departmentModel = new Department();
        $this->activityLog = new ActivityLog();
        $this->notificationModel = new Notification();
        $this->userModel = new User();
        $this->leaveNotifications = new LeaveNotificationService($this->notificationModel, $this->userModel);
    }

    /**
     * Display Leave Review Queue (HR & Admin Access)
     * Route: GET /leaves
     */
    public function index(): void
    {
        $status = !empty($_GET['status']) ? trim((string)$_GET['status']) : null;
        $empId = !empty($_GET['emp_id']) ? (int)$_GET['emp_id'] : null;
        $deptId = !empty($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
        $startDate = !empty($_GET['start_date']) ? trim((string)$_GET['start_date']) : null;
        $endDate = !empty($_GET['end_date']) ? trim((string)$_GET['end_date']) : null;

        $requests = $this->leaveModel->getAllRequests($status, $empId, 200, $startDate, $endDate, $deptId);
        $departments = $this->departmentModel->getAll();

        $this->view('leaves.index', [
            'pageTitle'      => 'Leave Requests Review Queue',
            'requests'       => $requests,
            'selectedStatus' => $status,
            'selectedDept'   => $deptId,
            'selectedEmpId'  => $empId,
            'startDate'      => $startDate,
            'endDate'        => $endDate,
            'departments'    => $departments,
            'user'           => currentUser(),
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
            http_response_code(403);
            $this->view('errors.403', [
                'pageTitle' => '403 Forbidden - Ownership Violation',
                'message'   => 'You are not authorized to view another employee\'s leave request.'
            ], null);
            return;
        }

        // Fetch requester's balances for context
        $balances = $this->leaveModel->getBalancesByEmployee((int)$req['employee_id'], (int)date('Y', strtotime($req['start_date'])));

        $this->view('leaves.show', [
            'pageTitle' => 'Leave Request #LR-' . str_pad((string)$req['id'], 3, '0', STR_PAD_LEFT),
            'req'       => $req,
            'balances'  => $balances,
            'user'      => $user,
        ], 'hr');
    }

    /**
     * Display Workforce Leave Balances Directory (HR & Admin Access)
     * Route: GET /leaves/balances
     */
    public function balances(): void
    {
        $year = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $deptId = !empty($_GET['dept']) ? (int)$_GET['dept'] : null;

        $balances = $this->leaveModel->getAllBalances($year, $deptId);
        $departments = $this->departmentModel->getAll();
        $leaveTypes = $this->leaveModel->getAllLeaveTypes();

        $this->view('leaves.balances', [
            'pageTitle'    => 'Workforce Leave Balances',
            'balances'     => $balances,
            'departments'  => $departments,
            'leaveTypes'   => $leaveTypes,
            'selectedYear' => $year,
            'selectedDept' => $deptId,
            'user'         => currentUser(),
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

        $ok = $this->leaveModel->adjustBalance($empId, $leaveTypeId, $year, $allocatedDays);

        if ($ok) {
            $this->activityLog->log(
                $user['id'],
                'LEAVE_BALANCE_ADJUSTED',
                "Adjusted leave balance for Employee #{$empId}, Leave Type #{$leaveTypeId}, Year {$year} to {$allocatedDays} days. Reason: {$reason}",
                'LeaveBalance',
                (string)$empId
            );
            Session::flash('success', "Leave balance successfully updated to {$allocatedDays} allocated days for year {$year}.");
        } else {
            Session::flash('error', "Failed to adjust leave balance for employee #{$empId}.");
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

        $count = $this->leaveModel->initializeBalancesForEmployee($empId, $year);

        $this->activityLog->log(
            $user['id'],
            'LEAVE_BALANCE_INITIALIZED',
            "Initialized {$count} default leave quota(s) for Employee #{$empId} for year {$year}",
            'LeaveBalance',
            (string)$empId
        );

        Session::flash('success', "Initialized {$count} leave balance quota(s) for Employee #{$empId} for {$year}.");
        redirect('/leaves/balances?year=' . $year);
    }

    /**
     * Leave Type Management Directory (HR & Admin Access)
     * Route: GET /hr/leave-types
     */
    public function types(): void
    {
        $status = !empty($_GET['status']) ? trim((string)$_GET['status']) : null;
        $leaveTypes = $this->leaveModel->getAllLeaveTypes($status);

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
        $status = trim((string)($_POST['status'] ?? 'Active'));

        // Validation
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

        if ($defaultDays < 1 || $defaultDays > 365) {
            Session::flash('error', 'Default annual quota must be between 1 and 365 days.');
            redirect('/hr/leave-types/create');
            return;
        }

        if (!in_array($status, ['Active', 'Inactive'], true)) {
            $status = 'Active';
        }

        $id = $this->leaveModel->createLeaveType([
            'leave_name'   => $name,
            'description'  => $description,
            'default_days' => $defaultDays,
            'status'       => $status,
        ]);

        $this->activityLog->log($user['id'], 'LEAVE_TYPE_CREATED', "Created leave classification '{$name}' with default quota of {$defaultDays} days", 'LeaveType', (string)$id);
        Session::flash('success', "Leave classification '{$name}' created successfully.");
        redirect('/hr/leave-types');
    }

    /**
     * Show Edit Leave Type Form (HR & Admin Access)
     * Route: GET /hr/leave-types/{id}/edit
     */
    public function editType(int $id): void
    {
        $type = $this->leaveModel->getLeaveTypeById($id);
        if (!$type) {
            Session::flash('error', "Leave classification #{$id} was not found.");
            redirect('/hr/leave-types');
            return;
        }

        $this->view('leave_types.edit', [
            'pageTitle' => "Edit Leave Classification - {$type['leave_name']}",
            'type'      => $type,
            'user'      => currentUser(),
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
        $status = trim((string)($_POST['status'] ?? 'Active'));

        // Validation
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

        if ($defaultDays < 1 || $defaultDays > 365) {
            Session::flash('error', 'Default annual quota must be between 1 and 365 days.');
            redirect("/hr/leave-types/{$id}/edit");
            return;
        }

        if (!in_array($status, ['Active', 'Inactive'], true)) {
            $status = 'Active';
        }

        $this->leaveModel->updateLeaveType($id, [
            'leave_name'   => $name,
            'description'  => $description,
            'default_days' => $defaultDays,
            'status'       => $status,
        ]);

        $this->activityLog->log($user['id'], 'LEAVE_TYPE_UPDATED', "Updated leave classification #{$id} ('{$name}')", 'LeaveType', (string)$id);
        Session::flash('success', "Leave classification '{$name}' updated successfully.");
        redirect('/hr/leave-types');
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

        $leaveTypes = $this->leaveModel->getLeaveTypes();
        $balances = $this->leaveModel->getBalancesByEmployee((int)$empId);

        $this->view('leaves.apply', [
            'pageTitle'  => 'File Leave Request',
            'leaveTypes' => $leaveTypes,
            'balances'   => $balances,
            'user'       => $user,
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

        $leaveTypeId = (int)($_POST['leave_type_id'] ?? 0);
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        // 1. Validation: Required fields
        if (!$leaveTypeId || empty($startDate) || empty($endDate) || empty($reason)) {
            Session::flash('error', 'All fields (Leave Classification, Start Date, End Date, and Reason) are required.');
            redirect('/leaves/apply');
            return;
        }

        // 2. Validate Leave Type exists and is Active
        $leaveType = $this->leaveModel->getLeaveTypeById($leaveTypeId);
        if (!$leaveType || $leaveType['status'] !== 'Active') {
            Session::flash('error', 'Selected leave classification is either inactive or does not exist.');
            redirect('/leaves/apply');
            return;
        }

        // 3. Calculate and validate business working days (Mon-Fri) using LeaveCalculator
        $rangeValidation = LeaveCalculator::validateDateRange($startDate, $endDate);
        if (!$rangeValidation['valid']) {
            Session::flash('error', $rangeValidation['error'] ?? 'Invalid date range specified.');
            redirect('/leaves/apply');
            return;
        }

        $days = $rangeValidation['working_days'];
        if ($days === 0) {
            Session::flash('error', 'Selected date range contains 0 working days (e.g. falls entirely on weekends). Please select eligible working days.');
            redirect('/leaves/apply');
            return;
        }

        // 4. Overlap Detection: Check against employee's existing Pending and Approved requests
        $overlapping = $this->leaveModel->getOverlappingRequest((int)$empId, $startDate, $endDate);
        if ($overlapping) {
            $existingStart = date('M d, Y', strtotime($overlapping['start_date']));
            $existingEnd = date('M d, Y', strtotime($overlapping['end_date']));
            Session::flash('error', "Overlapping Leave Detected: You already have a {$overlapping['status']} {$overlapping['leave_name']} request from {$existingStart} to {$existingEnd} (#LR-" . str_pad((string)$overlapping['id'], 3, '0', STR_PAD_LEFT) . "). Overlapping filings are prohibited.");
            redirect('/leaves/apply');
            return;
        }

        // 5. Validation: Check available balance and pending reservations
        $year = (int)date('Y', strtotime($startDate));
        $balanceRecord = $this->leaveModel->getBalance((int)$empId, $leaveTypeId, $year);

        if (!$balanceRecord) {
            // Auto-initialize balance if not yet created for this year
            $this->leaveModel->initializeBalancesForEmployee((int)$empId, $year);
            $balanceRecord = $this->leaveModel->getBalance((int)$empId, $leaveTypeId, $year);
        }

        $availableRemaining = $balanceRecord ? (int)$balanceRecord['remaining_days'] : 0;
        $pendingDays = $this->leaveModel->getPendingDays((int)$empId, $leaveTypeId, $year);
        $effectiveRemaining = max(0, $availableRemaining - $pendingDays);

        if ($days > $availableRemaining) {
            Session::flash('error', "Requested duration ({$days} working days) exceeds your remaining quota of {$availableRemaining} days for {$leaveType['leave_name']}.");
            redirect('/leaves/apply');
            return;
        }

        if ($days > $effectiveRemaining) {
            Session::flash('error', "Insufficient Available Balance: You have {$availableRemaining} remaining days, but {$pendingDays} day(s) are already reserved in other pending applications. You can only request up to {$effectiveRemaining} more day(s).");
            redirect('/leaves/apply');
            return;
        }

        // 6. Create request and HR notifications atomically (real-time events
        //    are emitted by the WebSocket service only after this commits)
        try {
            $requestId = $this->leaveModel->createRequest([
                'employee_id'    => (int)$empId,
                'leave_type_id'  => $leaveTypeId,
                'start_date'     => $startDate,
                'end_date'       => $endDate,
                'number_of_days' => $days,
                'reason'         => $reason,
            ], function (int $newId) use ($user, $days): void {
                $this->leaveNotifications->leaveSubmitted(
                    $newId,
                    "Employee {$user['username']} submitted a {$days}-day leave request #LR-" . str_pad((string)$newId, 3, '0', STR_PAD_LEFT) . "."
                );
            });
        } catch (\Throwable $e) {
            error_log('[LeaveController::store] Leave submission failed: ' . $e->getMessage());
            Session::flash('error', 'Unable to submit your leave request right now. Please try again.');
            redirect('/leaves/apply');
            return;
        }

        $this->activityLog->log($user['id'], 'LEAVE_REQUESTED', "Submitted {$days}-day leave request #{$requestId} for {$leaveType['leave_name']}", 'LeaveRequest', (string)$requestId);

        Session::flash('success', "Leave request for {$days} working day(s) submitted successfully. Status is Pending HR review.");
        redirect('/employee/dashboard');
    }

    /**
     * Approve Leave Request (HR & Admin Access)
     * Route: POST /leaves/{id}/approve
     */
    public function approve(int $id): void
    {
        $user = currentUser();
        if (!$user || strtolower((string)($user['role'] ?? '')) !== 'hr') {
            Session::flash('error', 'Forbidden: Only authorized HR personnel may approve leave requests.');
            redirect('/dashboard');
            return;
        }

        // Execute full atomic approval transaction (status, balance, activity log, persistent notification)
        $result = $this->leaveModel->approveRequest(
            $id,
            (int)$user['id'],
            function (array $request) use ($id): void {
                if (!$this->leaveNotifications->leaveDecided(
                    $request,
                    'Approved',
                    "Your leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . " ({$request['number_of_days']} days for {$request['leave_name']}) was approved by HR."
                )) {
                    throw new \RuntimeException('Failed to create approval notification for employee.');
                }
            }
        );

        if ($result['ok']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        redirect('/leaves');
    }

    /**
     * Reject Leave Request with Reason (HR & Admin Access)
     * Route: POST /leaves/{id}/reject
     */
    public function reject(int $id): void
    {
        $user = currentUser();
        if (!$user || strtolower((string)($user['role'] ?? '')) !== 'hr') {
            Session::flash('error', 'Forbidden: Only authorized HR personnel may reject leave requests.');
            redirect('/dashboard');
            return;
        }

        $rejectionReason = trim((string)($_POST['rejection_reason'] ?? ''));
        if (empty($rejectionReason)) {
            $rejectionReason = 'Request declined per HR department review.';
        }

        // Execute atomic rejection transaction (status, activity log, persistent notification)
        $result = $this->leaveModel->rejectRequest(
            $id,
            (int)$user['id'],
            $rejectionReason,
            function (array $request) use ($id, $rejectionReason): void {
                if (!$this->leaveNotifications->leaveDecided(
                    $request,
                    'Rejected',
                    "Your leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . " was rejected. Reason: {$rejectionReason}"
                )) {
                    throw new \RuntimeException('Failed to create rejection notification for employee.');
                }
            }
        );

        if ($result['ok']) {
            Session::flash('warning', $result['message']);
        } else {
            Session::flash('error', $result['message']);
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

        $req = $this->leaveModel->getRequestById($id);
        if (!$req) {
            Session::flash('error', "Leave request #{$id} not found.");
            redirect('/dashboard');
            return;
        }

        // Ownership enforcement for employees
        if ($userRole === 'employee' && (int)$req['employee_id'] !== $myEmpId) {
            Session::flash('error', 'Unauthorized action: You cannot cancel another employee\'s leave request.');
            redirect('/employee/dashboard');
            return;
        }

        if ($req['status'] !== 'Pending') {
            Session::flash('error', "Cannot cancel request #{$id}: Only Pending requests can be cancelled (Current status: {$req['status']}).");
            if ($userRole === 'employee') {
                redirect('/employee/leave-requests');
            } else {
                redirect('/leaves');
            }
            return;
        }

        $ok = $this->leaveModel->cancelRequest($id, (int)$req['employee_id']);

        if ($ok) {
            $this->activityLog->log($user['id'], 'LEAVE_CANCELLED', "Cancelled leave request #{$id}", 'LeaveRequest', (string)$id);

            // Notify HR if cancelled by employee
            if ($userRole === 'employee') {
                $this->notificationModel->notifyRole(
                    'hr',
                    'Leave Request Cancelled',
                    "Employee {$user['username']} cancelled pending leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . ".",
                    'info'
                );
            }

            Session::flash('info', "Leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . " has been cancelled.");
        } else {
            Session::flash('error', "Failed to cancel request #{$id}.");
        }

        if ($userRole === 'employee') {
            redirect('/employee/leave-requests');
        } else {
            redirect('/leaves');
        }
    }
}
