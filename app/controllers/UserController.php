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

class UserController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Display all system user accounts.
     * Route: GET /hr/users (and legacy /admin/users)
     * Access: HR Only
     */
    public function index(): void
    {
        $users = $this->userModel->getAllWithDetails();

        $stats = [
            'total'    => count($users),
            'active'   => count(array_filter($users, fn($u) => $u['status'] === 'Active')),
            'inactive' => count(array_filter($users, fn($u) => $u['status'] === 'Inactive')),
            'hrs'      => count(array_filter($users, fn($u) => $u['role'] === 'hr')),
            'employees'=> count(array_filter($users, fn($u) => $u['role'] === 'employee')),
        ];

        $this->view('users.index', [
            'pageTitle' => 'User Account & Role Management - HR Portal',
            'user'      => currentUser(),
            'users'     => $users,
            'stats'     => $stats,
        ], 'hr');
    }

    /**
     * Render the role assignment and account edit form.
     * Route: GET /hr/users/{id}/edit
     * Access: HR Only
     */
    public function edit(int $id): void
    {
        $targetUser = $this->userModel->findById($id);

        if (!$targetUser) {
            Session::flash('error', 'The requested user account was not found.');
            $this->redirect('/hr/users');
            return;
        }

        $this->view('users.edit', [
            'pageTitle'  => "Edit User Account: {$targetUser['username']}",
            'user'       => currentUser(),
            'targetUser' => $targetUser,
            'validRoles' => Permission::VALID_ROLES,
        ], 'hr');
    }

    /**
     * Process role and status updates for a user account.
     * Route: POST /hr/users/{id}/edit
     * Access: HR Only
     */
    public function update(int $id): void
    {
        $targetUser = $this->userModel->findById($id);

        if (!$targetUser) {
            Session::flash('error', 'User account not found.');
            $this->redirect('/hr/users');
            return;
        }

        $role = strtolower(trim((string)($_POST['role'] ?? '')));
        $status = trim((string)($_POST['status'] ?? ''));

        // 1. Validate role against strict allowlist (hr, employee)
        if (!Permission::isValidRole($role)) {
            Session::flash('error', 'Invalid role selected. Allowed roles are: hr, employee.');
            $this->redirect("/hr/users/{$id}/edit");
            return;
        }

        // 2. Validate status against strict allowlist
        $validStatuses = ['Active', 'Inactive'];
        if (!in_array($status, $validStatuses, true)) {
            Session::flash('error', 'Invalid account status. Allowed values are: Active, Inactive.');
            $this->redirect("/hr/users/{$id}/edit");
            return;
        }

        $currentUserId = currentUserId();

        // 3. Self-demotion / Self-deactivation Prevention
        if ($id === $currentUserId) {
            if ($role !== 'hr') {
                Session::flash('error', 'Security Policy Violation: You cannot demote your own active HR management account.');
                $this->redirect("/hr/users/{$id}/edit");
                return;
            }
            if ($status !== 'Active') {
                Session::flash('error', 'Security Policy Violation: You cannot deactivate your own active HR management account.');
                $this->redirect("/hr/users/{$id}/edit");
                return;
            }
        }

        // 4. Last Active HR Lockout Protection
        $isTargetActiveHr = ($targetUser['role'] === 'hr' && $targetUser['status'] === 'Active');
        $isBeingDemotedOrDeactivated = ($role !== 'hr' || $status !== 'Active');

        if ($isTargetActiveHr && $isBeingDemotedOrDeactivated) {
            $otherActiveHrs = $this->userModel->countActiveHr($id);
            if ($otherActiveHrs < 1) {
                Session::flash('error', 'System Lockout Protection: Cannot demote or deactivate the last remaining active HR Officer in the system.');
                $this->redirect("/hr/users/{$id}/edit");
                return;
            }
        }

        // 5. Apply database update
        $updated = $this->userModel->updateRoleAndStatus($id, $role, $status);

        if (!$updated) {
            Session::flash('error', 'Failed to update user account in database. Please try again.');
            $this->redirect("/hr/users/{$id}/edit");
            return;
        }

        // 6. Record Audit Event in Activity Logs
        $details = "Updated user '{$targetUser['username']}' (ID: {$id}): Role [{$targetUser['role']} -> {$role}], Status [{$targetUser['status']} -> {$status}]";
        $this->userModel->logActivity(
            $currentUserId,
            'USER_ROLE_UPDATED',
            $details,
            'users',
            (string)$id
        );

        Session::flash('success', "User '{$targetUser['username']}' updated successfully.");
        $this->redirect('/hr/users');
    }
}
