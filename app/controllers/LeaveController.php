<?php
/**
 * Leave Management Controller
 * Handles leave applications, approvals, balances, and status transitions
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\Leave;
use App\Models\Employee;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;

class LeaveController extends Controller
{
    private Leave $leaveModel;
    private Employee $employeeModel;
    private ActivityLog $activityLog;
    private Notification $notificationModel;
    private User $userModel;

    public function __construct()
    {
        $this->leaveModel = new Leave();
        $this->employeeModel = new Employee();
        $this->activityLog = new ActivityLog();
        $this->notificationModel = new Notification();
        $this->userModel = new User();
    }

    /**
     * Display Leave Review Queue (HR & Admin Access)
     * Route: GET /leaves
     */
    public function index(): void
    {
        $status = !empty($_GET['status']) ? trim((string)$_GET['status']) : null;
        $requests = $this->leaveModel->getAllRequests($status);

        $this->view('leaves.index', [
            'pageTitle'      => 'Leave Requests Review Queue',
            'requests'       => $requests,
            'selectedStatus' => $status,
            'user'           => currentUser(),
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

        $this->view('leaves.balances', [
            'pageTitle'    => 'Workforce Leave Balances',
            'balances'     => $balances,
            'selectedYear' => $year,
            'selectedDept' => $deptId,
            'user'         => currentUser(),
        ], 'hr');
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
        $balances = $this->leaveModel->getBalancesByEmployee($empId);

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
            Session::flash('error', 'All fields (Leave Type, Start Date, End Date, Reason) are required.');
            redirect('/leaves/apply');
            return;
        }

        // 2. Validation: Dates
        $start = strtotime($startDate);
        $end = strtotime($endDate);

        if (!$start || !$end || $start > $end) {
            Session::flash('error', 'Invalid date range: Start date must be before or equal to End date.');
            redirect('/leaves/apply');
            return;
        }

        // 3. Calculate business days (Mon-Fri)
        $days = 0;
        $cur = $start;
        while ($cur <= $end) {
            $w = (int)date('w', $cur);
            if ($w !== 0 && $w !== 6) { // Not Sunday or Saturday
                $days++;
            }
            $cur = strtotime('+1 day', $cur);
        }

        if ($days === 0) {
            Session::flash('error', 'Selected leave dates only cover weekends. Please select at least one working day.');
            redirect('/leaves/apply');
            return;
        }

        // 4. Validation: Check available balance
        $year = (int)date('Y', $start);
        $balances = $this->leaveModel->getBalancesByEmployee($empId, $year);
        $balanceRecord = null;
        foreach ($balances as $b) {
            if ((int)$b['leave_type_id'] === $leaveTypeId) {
                $balanceRecord = $b;
                break;
            }
        }

        if ($balanceRecord && $days > (int)$balanceRecord['remaining_days']) {
            Session::flash('error', "Requested duration ({$days} days) exceeds your available remaining balance of {$balanceRecord['remaining_days']} days.");
            redirect('/leaves/apply');
            return;
        }

        // 5. Create request in database
        $requestId = $this->leaveModel->createRequest([
            'employee_id'    => $empId,
            'leave_type_id'  => $leaveTypeId,
            'start_date'     => $startDate,
            'end_date'       => $endDate,
            'number_of_days' => $days,
            'reason'         => $reason,
        ]);

        $this->activityLog->log($user['id'], 'LEAVE_REQUESTED', "Submitted {$days}-day leave request #{$requestId}", 'LeaveRequest', (string)$requestId);

        // Dispatch in-system notification to HR and Admin
        $this->notificationModel->notifyRole('hr', 'New Leave Application', "Employee {$user['username']} submitted a {$days}-day leave request #{$requestId}.", 'info');
        $this->notificationModel->notifyRole('admin', 'New Leave Application', "Employee {$user['username']} submitted a {$days}-day leave request #{$requestId}.", 'info');

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
        $req = $this->leaveModel->getRequestById($id);
        $ok = $this->leaveModel->updateRequestStatus($id, 'Approved', (int)$user['id']);

        if ($ok) {
            $this->activityLog->log($user['id'], 'LEAVE_APPROVED', "Approved leave request #{$id}", 'LeaveRequest', (string)$id);
            
            // Dispatch notification to requester
            if ($req && !empty($req['employee_id'])) {
                $targetUser = $this->userModel->findByEmployeeId((int)$req['employee_id']);
                if ($targetUser) {
                    $this->notificationModel->create((int)$targetUser['id'], 'Leave Request Approved', "Your leave request #{$id} ({$req['number_of_days']} days) was approved by HR.", 'success');
                }
            }

            Session::flash('success', "Leave request #{$id} has been approved and leave balance updated.");
        } else {
            Session::flash('error', "Failed to approve leave request #{$id}. Request may already have been processed.");
        }

        redirect('/leaves');
    }

    /**
     * Reject Leave Request (HR & Admin Access)
     * Route: POST /leaves/{id}/reject
     */
    public function reject(int $id): void
    {
        $user = currentUser();
        $req = $this->leaveModel->getRequestById($id);
        $ok = $this->leaveModel->updateRequestStatus($id, 'Rejected', (int)$user['id']);

        if ($ok) {
            $this->activityLog->log($user['id'], 'LEAVE_REJECTED', "Rejected leave request #{$id}", 'LeaveRequest', (string)$id);

            // Dispatch notification to requester
            if ($req && !empty($req['employee_id'])) {
                $targetUser = $this->userModel->findByEmployeeId((int)$req['employee_id']);
                if ($targetUser) {
                    $this->notificationModel->create((int)$targetUser['id'], 'Leave Request Rejected', "Your leave request #{$id} ({$req['number_of_days']} days) was rejected by HR.", 'warning');
                }
            }

            Session::flash('warning', "Leave request #{$id} was rejected.");
        } else {
            Session::flash('error', "Failed to reject leave request #{$id}. Request may already have been processed.");
        }

        redirect('/leaves');
    }

    /**
     * Cancel own leave request (Employee Access)
     * Route: POST /leaves/{id}/cancel
     */
    public function cancel(int $id): void
    {
        $user = currentUser();
        $empId = $user['employee_id'] ?? null;

        if (!$empId) {
            Session::flash('error', 'Unauthorized action.');
            redirect('/dashboard');
            return;
        }

        $ok = $this->leaveModel->cancelRequest($id, (int)$empId);

        if ($ok) {
            $this->activityLog->log($user['id'], 'LEAVE_CANCELLED', "Cancelled leave request #{$id}", 'LeaveRequest', (string)$id);
            Session::flash('info', "Leave request #{$id} has been cancelled.");
        } else {
            Session::flash('error', "Unable to cancel request #{$id}. Only pending requests can be cancelled.");
        }

        redirect('/employee/dashboard');
    }
}
