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
use App\Services\LeaveApprovalQueueService;
use App\Application\Leave\Adapters\LeaveControllerAdapter;

class LeaveController extends Controller
{
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
        $this->leaveModel = $leaveModel ?? new Leave();
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
        $status = !empty($_GET['status']) ? trim((string)$_GET['status']) : null;
        $empId = !empty($_GET['emp_id']) ? (int)$_GET['emp_id'] : null;
        $deptId = !empty($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
        $startDate = !empty($_GET['start_date']) ? trim((string)$_GET['start_date']) : null;
        $endDate = !empty($_GET['end_date']) ? trim((string)$_GET['end_date']) : null;

        $requests = $this->leaveModel->getAllRequests($status, $empId, 200, $startDate, $endDate, $deptId);
        $departments = $this->departmentModel->getAll();

        // Build deterministic FIFO Pending Approval Queue using custom Queue data structure (Phase 11/13)
        $queueService = new LeaveApprovalQueueService($this->leaveModel);
        $approvalQueue = $queueService->buildPendingApprovalQueue($deptId, 100);
        $nextInLine = $queueService->getNextInLine($approvalQueue);
        $queueDepth = $approvalQueue->size();

        $this->view('leaves.index', [
            'pageTitle'      => 'Leave Requests Review Queue',
            'requests'       => $requests,
            'selectedStatus' => $status,
            'selectedDept'   => $deptId,
            'selectedEmpId'  => $empId,
            'startDate'      => $startDate,
            'endDate'        => $endDate,
            'departments'    => $departments,
            'queueDepth'     => $queueDepth,
            'nextInLine'     => $nextInLine,
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
            // Surface the specific reason (e.g. allocation below days already used) instead of a generic failure.
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

        $employee = $this->employeeModel->findById((int)$empId);
        if (!$employee || ($employee['employment_status'] ?? '') !== 'Active') {
            Session::flash('error', 'Unable to file leave: Your employment account is inactive. Please contact HR.');
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

        $employee = $this->employeeModel->findById((int)$empId);
        if (!$employee || ($employee['employment_status'] ?? '') !== 'Active') {
            Session::flash('error', 'Unable to submit leave request: Your employment account is inactive. Please contact HR.');
            redirect('/dashboard');
            return;
        }

        $leaveTypeId = (int)($_POST['leave_type_id'] ?? 0);
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        // Required-field check (friendly message); all business rules below are enforced
        // server-side inside the single transactional filing use case.
        if (!$leaveTypeId || empty($startDate) || empty($endDate) || empty($reason)) {
            Session::flash('error', 'All fields (Leave Classification, Start Date, End Date, and Reason) are required.');
            redirect('/leaves/apply');
            return;
        }

        // Resolve the leave-type name for the notification/activity text (does not gate filing;
        // the use case re-checks the type is active).
        $leaveType = $this->leaveModel->getLeaveTypeById($leaveTypeId);
        $leaveTypeName = $leaveType['leave_name'] ?? 'leave';

        // Delegate to the single authoritative filing path: one transaction with the balance
        // row locked FOR UPDATE, auto-initialization of missing balances, pending-day
        // reservation, overlap, cross-year and active employee/type checks. The HR
        // notification is written inside that same transaction via the callback.
        $result = $this->leaveAdapter->file(
            (int)$empId,
            $leaveTypeId,
            $startDate,
            $endDate,
            $reason,
            function (int $newId) use ($user, $leaveTypeName): void {
                $this->leaveNotifications->leaveSubmitted(
                    $newId,
                    "Employee {$user['username']} submitted a {$leaveTypeName} request #LR-" . str_pad((string)$newId, 3, '0', STR_PAD_LEFT) . "."
                );
            }
        );

        if (!$result->success) {
            Session::flash('error', $result->message);
            redirect('/leaves/apply');
            return;
        }

        $requestId = (int)($result->data['id'] ?? 0);
        $this->activityLog->log($user['id'], 'LEAVE_REQUESTED', "Submitted leave request #{$requestId} for {$leaveTypeName}", 'LeaveRequest', (string)$requestId);

        Session::flash('success', 'Leave request submitted successfully. Status is Pending HR review.');
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

        // Delegate to verified application use case via adapter
        $result = $this->leaveAdapter->approve($id, (int)$user['id']);

        if ($result->success) {
            $req = $this->leaveModel->getRequestById($id);
            if ($req) {
                $this->leaveNotifications->leaveDecided(
                    $req,
                    'Approved',
                    "Your leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . " ({$req['number_of_days']} days for {$req['leave_name']}) was approved by HR."
                );
            }
            $this->activityLog->log($user['id'], 'LEAVE_APPROVED', "Approved leave request #{$id}", 'LeaveRequest', (string)$id);
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
        if (!$user || strtolower((string)($user['role'] ?? '')) !== 'hr') {
            Session::flash('error', 'Forbidden: Only authorized HR personnel may approve leave requests.');
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
        if (!$user || strtolower((string)($user['role'] ?? '')) !== 'hr') {
            Session::flash('error', 'Forbidden: Only authorized HR personnel may reject leave requests.');
            redirect('/dashboard');
            return;
        }

        $rejectionReason = trim((string)($_POST['rejection_reason'] ?? ''));
        if (empty($rejectionReason)) {
            $rejectionReason = 'Request declined per HR department review.';
        }

        // Delegate to verified application use case via adapter
        $result = $this->leaveAdapter->reject($id, (int)$user['id'], $rejectionReason);

        if ($result->success) {
            $req = $this->leaveModel->getRequestById($id);
            if ($req) {
                $this->leaveNotifications->leaveDecided(
                    $req,
                    'Rejected',
                    "Your leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . " was rejected. Reason: {$rejectionReason}"
                );
            }
            $this->activityLog->log($user['id'], 'LEAVE_REJECTED', "Rejected leave request #{$id}. Reason: {$rejectionReason}", 'LeaveRequest', (string)$id);
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

        $isHr = ($userRole === 'hr');

        // Capture request state before cancellation so we can notify the owner and
        // describe the action accurately (an HR cancel may reverse an approved request).
        $req = $this->leaveModel->getRequestById($id);
        $wasApproved = $req && ($req['status'] ?? '') === 'Approved';

        // HR cancelling an already-approved request must supply a reason (decision F.2).
        $reason = $isHr ? trim((string)($_POST['cancellation_reason'] ?? '')) : null;

        $result = $this->leaveAdapter->cancel($id, $myEmpId, $isHr, $reason);

        if ($result->success) {
            $logReason = ($reason !== null && $reason !== '') ? " Reason: {$reason}" : '';
            $this->activityLog->log(
                $user['id'],
                'LEAVE_CANCELLED',
                "Cancelled leave request #{$id}" . ($wasApproved ? ' (approved leave reversed, balance restored).' : '.') . $logReason,
                'LeaveRequest',
                (string)$id
            );

            if ($userRole === 'employee') {
                // Notify HR that an employee withdrew a pending request
                $this->notificationModel->notifyRole(
                    'hr',
                    'Leave Request Cancelled',
                    "Employee {$user['username']} cancelled pending leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . ".",
                    'info'
                );
            } elseif ($req && !empty($req['employee_id'])) {
                // Notify the owning employee that HR cancelled their request
                $ownerUser = $this->userModel->findByEmployeeId((int)$req['employee_id']);
                if ($ownerUser && !empty($ownerUser['id'])) {
                    $detail = $wasApproved
                        ? "Your approved leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . " was cancelled by HR and the leave days were restored to your balance."
                        : "Your pending leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . " was cancelled by HR.";
                    if ($reason !== null && $reason !== '') {
                        $detail .= " Reason: {$reason}";
                    }
                    $this->notificationModel->create((int)$ownerUser['id'], 'Leave Request Cancelled', $detail, 'warning');
                }
            }

            Session::flash('info', $result->message);
        } else {
            Session::flash('error', $result->message);
        }

        if ($userRole === 'employee') {
            redirect('/employee/leave-requests');
        } else {
            redirect('/leaves');
        }
    }
}
