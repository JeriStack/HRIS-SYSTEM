<?php
/**
 * User Account & Role Management Controller
 * Enforces Administrator-Only Access for User Governance
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use Core\Permission;
use App\Models\User;
use App\Models\Employee;
use App\Services\AccountRevocationService;
use App\Services\StepUpAuthService;

class UserController extends Controller
{
    private User $userModel;
    private Employee $employeeModel;
    private AccountRevocationService $revocation;
    private StepUpAuthService $stepUp;

    public function __construct(
        ?User $userModel = null,
        ?Employee $employeeModel = null,
        ?AccountRevocationService $revocation = null,
        ?StepUpAuthService $stepUp = null
    ) {
        $this->userModel = $userModel ?? new User();
        $this->employeeModel = $employeeModel ?? new Employee();
        $this->revocation = $revocation ?? new AccountRevocationService();
        $this->stepUp = $stepUp ?? new StepUpAuthService($this->userModel);
    }

    /**
     * Display all system user accounts.
     * Route: GET /admin/users (and legacy /hr/users)
     * Access: Administrator Only
     */
    public function index(): void
    {
        $users = $this->userModel->getAllWithDetails();

        $stats = [
            'total'     => count($users),
            'active'    => count(array_filter($users, fn($u) => $u['status'] === 'Active')),
            'inactive'  => count(array_filter($users, fn($u) => $u['status'] === 'Inactive')),
            'admins'    => count(array_filter($users, fn($u) => $u['role'] === 'admin')),
            'hrs'       => count(array_filter($users, fn($u) => $u['role'] === 'hr')),
            'employees' => count(array_filter($users, fn($u) => $u['role'] === 'employee')),
        ];

        $this->view('users.index', [
            'pageTitle' => 'User Account & Role Management - Administrator Console',
            'user'      => currentUser(),
            'users'     => $users,
            'stats'     => $stats,
        ], 'hr');
    }

    /**
     * Render the account provisioning form for active employees.
     * Route: GET /admin/users/create
     * Access: Administrator Only
     */
    public function create(): void
    {
        $unlinkedEmployees = $this->employeeModel->getEmployeesWithoutUserAccount();
        $selectedEmpId = (int)($_GET['employee_id'] ?? 0);

        $this->view('users.create', [
            'pageTitle'         => 'Provision User Account - Administrator Console',
            'user'              => currentUser(),
            'unlinkedEmployees' => $unlinkedEmployees,
            'selectedEmpId'     => $selectedEmpId,
            'validRoles'        => Permission::VALID_ROLES,
            'errors'            => Session::getFlash('errors', []),
            'old'               => Session::getFlash('old', []),
            'stepUpRequired'    => !$this->stepUp->hasRecentVerification(),
        ], 'hr');
    }

    /**
     * Process provisioning of a new user account for an employee.
     * Route: POST /admin/users/create
     * Access: Administrator Only
     */
    public function store(): void
    {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $role = strtolower(trim((string)($_POST['role'] ?? 'employee')));
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        $errors = [];

        // 1. Validate employee
        $employee = null;
        if ($employeeId <= 0) {
            $errors['employee_id'] = 'Please select a valid employee record.';
        } else {
            $employee = $this->employeeModel->findById($employeeId);
            if (!$employee) {
                $errors['employee_id'] = 'The selected employee record does not exist.';
            } elseif ($employee['employment_status'] !== 'Active') {
                $errors['employee_id'] = 'Cannot provision an account for an inactive employee.';
            } elseif (!empty($employee['user_account_id'])) {
                $errors['employee_id'] = 'This employee already has an associated user account.';
            }
        }

        // 2. Validate role against strict allowlist (admin, hr, employee)
        if (!Permission::isValidRole($role)) {
            $errors['role'] = 'Invalid role assignment. Allowed roles are: admin, hr, employee.';
        }

        // 3. Validate password complexity (minimum 8 characters)
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long.';
        }
        if ($password !== $confirmPassword) {
            $errors['confirm_password'] = 'Password confirmation does not match.';
        }

        // 4. Validate employee email uniqueness
        $email = '';
        if ($employee) {
            $email = strtolower(trim((string)$employee['email']));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['employee_id'] = 'The selected employee does not have a valid email address.';
            } elseif ($this->userModel->emailExists($email)) {
                $errors['employee_id'] = 'The email address associated with this employee is already in use by another account.';
            }
        }

        // 4b. Sensitive-operation protection: recent MFA or current-password re-verification
        if (empty($errors)) {
            $stepUp = $this->stepUp->verify('USER_PROVISION', $_POST[StepUpAuthService::FIELD] ?? null);
            if (!$stepUp['ok']) {
                $errors[StepUpAuthService::FIELD] = $stepUp['error'];
            }
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            // Never round-trip passwords or tokens through the session flash store
            Session::flash('old', array_diff_key(
                $_POST,
                array_flip(['password', 'confirm_password', StepUpAuthService::FIELD, '_csrf_token'])
            ));
            $this->redirect('/admin/users/create' . ($employeeId > 0 ? '?employee_id=' . $employeeId : ''));
            return;
        }

        // 5. Generate username from employee_no
        $username = strtolower($employee['employee_no']);

        // 6. Create user account with secure password hash
        $newUserId = $this->userModel->create([
            'employee_id' => $employeeId,
            'username'    => $username,
            'email'       => $email,
            'password'    => $password, // hashed inside User::create()
            'role'        => $role,
            'status'      => 'Active',
        ]);

        // 7. Audit Logging
        $currentUserId = currentUserId();
        $this->userModel->logActivity(
            $currentUserId,
            'USER_PROVISIONED',
            "Provisioned new user account for {$employee['first_name']} {$employee['last_name']} ({$email}) with role '{$role}'",
            'users',
            (string)$newUserId,
            null,
            [
                'username' => $username,
                'email'    => $email,
                'role'     => $role,
                'status'   => 'Active',
            ]
        );

        Session::flash('success', "User account for {$employee['first_name']} {$employee['last_name']} ({$email}) was successfully provisioned.");
        $this->redirect('/admin/users');
    }

    /**
     * Render the role assignment and account edit form.
     * Route: GET /admin/users/{id}/edit
     * Access: Administrator Only
     */
    public function edit(int $id): void
    {
        $targetUser = $this->userModel->findById($id);

        if (!$targetUser) {
            Session::flash('error', 'The requested user account was not found.');
            $this->redirect('/admin/users');
            return;
        }

        $this->view('users.edit', [
            'pageTitle'      => "Edit User Account: {$targetUser['username']}",
            'user'           => currentUser(),
            'targetUser'     => $targetUser,
            'validRoles'     => Permission::VALID_ROLES,
            'stepUpRequired' => !$this->stepUp->hasRecentVerification(),
        ], 'hr');
    }

    /**
     * Process role and status updates for a user account.
     * Route: POST /admin/users/{id}/edit
     * Access: Administrator Only
     */
    public function update(int $id): void
    {
        $targetUser = $this->userModel->findById($id);

        if (!$targetUser) {
            Session::flash('error', 'User account not found.');
            $this->redirect('/admin/users');
            return;
        }

        $role = strtolower(trim((string)($_POST['role'] ?? '')));
        $status = trim((string)($_POST['status'] ?? ''));

        // 1. Validate role against strict allowlist (admin, hr, employee)
        if (!Permission::isValidRole($role)) {
            Session::flash('error', 'Invalid role selected. Allowed roles are: admin, hr, employee.');
            $this->redirect("/admin/users/{$id}/edit");
            return;
        }

        // 2. Validate status against strict allowlist
        $validStatuses = ['Active', 'Inactive'];
        if (!in_array($status, $validStatuses, true)) {
            Session::flash('error', 'Invalid account status. Allowed values are: Active, Inactive.');
            $this->redirect("/admin/users/{$id}/edit");
            return;
        }

        $currentUserId = currentUserId();

        // 3. Self-demotion / Self-deactivation Prevention
        if ($id === $currentUserId) {
            $currentRole = strtolower((string)$targetUser['role']);
            if ($currentRole === 'admin' && ($role !== 'admin' || $status !== 'Active')) {
                Session::flash('error', 'Security Policy Violation: You cannot demote or deactivate your own active Administrator account.');
                $this->redirect("/admin/users/{$id}/edit");
                return;
            }
            if ($currentRole === 'hr' && ($role !== 'hr' || $status !== 'Active')) {
                Session::flash('error', 'Security Policy Violation: You cannot demote or deactivate your own active HR management account.');
                $this->redirect("/admin/users/{$id}/edit");
                return;
            }
        }

        // 4. Last Active Admin Lockout Protection
        $isTargetActiveAdmin = ($targetUser['role'] === 'admin' && $targetUser['status'] === 'Active');
        $isAdminBeingDemotedOrDeactivated = ($role !== 'admin' || $status !== 'Active');

        if ($isTargetActiveAdmin && $isAdminBeingDemotedOrDeactivated) {
            $otherActiveAdmins = $this->userModel->countActiveAdmins($id);
            if ($otherActiveAdmins < 1) {
                Session::flash('error', 'System Lockout Protection: Cannot demote or deactivate the last remaining active Administrator in the system.');
                $this->redirect("/admin/users/{$id}/edit");
                return;
            }
        }

        // 4a. Last Active HR Lockout Protection
        $isTargetActiveHr = ($targetUser['role'] === 'hr' && $targetUser['status'] === 'Active');
        $isHrBeingDemotedOrDeactivated = ($role !== 'hr' || $status !== 'Active');

        if ($isTargetActiveHr && $isHrBeingDemotedOrDeactivated) {
            $otherActiveHrs = $this->userModel->countActiveHr($id);
            if ($otherActiveHrs < 1) {
                Session::flash('error', 'System Lockout Protection: Cannot demote or deactivate the last remaining active HR Officer in the system.');
                $this->redirect("/admin/users/{$id}/edit");
                return;
            }
        }

        $roleChanged = strtolower((string)$targetUser['role']) !== $role;
        $statusChanged = (string)$targetUser['status'] !== $status;

        // 4b. Sensitive-operation protection for role or status changes
        if ($roleChanged || $statusChanged) {
            $stepUp = $this->stepUp->verify('USER_ROLE_STATUS_UPDATE', $_POST[StepUpAuthService::FIELD] ?? null);
            if (!$stepUp['ok']) {
                Session::flash('error', $stepUp['error']);
                $this->redirect("/admin/users/{$id}/edit");
                return;
            }
        }

        // 5. Apply database update
        $updated = $this->userModel->updateRoleAndStatus($id, $role, $status);

        if (!$updated) {
            Session::flash('error', 'Failed to update user account in database. Please try again.');
            $this->redirect("/admin/users/{$id}/edit");
            return;
        }

        // 5b. Revoke out-of-band credentials; active sessions end on their next request
        if ($roleChanged || $status !== 'Active') {
            $this->revocation->revokeUser($id);
        }

        // 6. Record Audit Event in Activity Logs
        $details = "Updated user '{$targetUser['username']}' (ID: {$id}): Role [{$targetUser['role']} -> {$role}], Status [{$targetUser['status']} -> {$status}]";
        $this->userModel->logActivity(
            $currentUserId,
            'USER_ROLE_UPDATED',
            $details,
            'users',
            (string)$id,
            [
                'role'   => $targetUser['role'],
                'status' => $targetUser['status'],
            ],
            [
                'role'   => $role,
                'status' => $status,
            ],
            'SUCCESS'
        );

        Session::flash('success', "User '{$targetUser['username']}' updated successfully.");
        $this->redirect('/admin/users');
    }
}
