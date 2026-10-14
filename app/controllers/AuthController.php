<?php
/**
 * Authentication Controller
 * Manages login, logout, session lifecycle, and role-directed dashboard routing
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\User;

class AuthController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Render the authentication login view.
     * Route: GET /login
     */
    public function showLoginForm(): void
    {
        // If already authenticated, redirect immediately to dashboard
        if (isAuthenticated()) {
            $this->redirect('/dashboard');
            return;
        }

        $error = Session::getFlash('error');
        $success = Session::getFlash('success');
        $timeout = Session::getFlash('timeout');
        $oldUsername = Session::getFlash('old_username', '');

        $this->view('auth.login', [
            'pageTitle'   => 'Sign In - Employee HRIS & Leave Management System',
            'error'       => $error,
            'success'     => $success,
            'timeout'     => $timeout,
            'oldUsername' => $oldUsername,
            'csrfToken'   => csrf_token(),
        ], null); // Render standalone without outer layout
    }

    /**
     * Process authentication credentials.
     * Route: POST /login
     */
    public function login(): void
    {
        // 1. Validate CSRF Protection
        $submittedToken = $_POST['_csrf_token'] ?? null;
        if (!verify_csrf_token($submittedToken)) {
            Session::flash('error', 'Security token expired or invalid. Please try submitting again.');
            $this->redirect('/login');
            return;
        }

        // 2. Validate Required Inputs
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            Session::flash('error', 'Please provide both username and password.');
            Session::flash('old_username', $username);
            $this->redirect('/login');
            return;
        }

        // 3. Query User by Username using Prepared Statement
        $user = $this->userModel->findByUsername($username);

        // 4. Validate Account Existence and Status (Generic message prevents user enumeration)
        if (!$user || $user['status'] !== 'Active') {
            $this->userModel->logActivity(
                null,
                'AUTH_FAILED',
                "Failed authentication attempt for unknown or inactive user: {$username}",
                'USER',
                $username
            );

            Session::flash('error', 'Invalid username or password. Please verify your credentials and try again.');
            Session::flash('old_username', $username);
            $this->redirect('/login');
            return;
        }

        // 5. Validate Associated Employment Status if linked to employee
        if (!empty($user['employee_id']) && isset($user['employment_status']) && $user['employment_status'] !== 'Active') {
            $this->userModel->logActivity(
                (int)$user['id'],
                'AUTH_BLOCKED',
                "Login blocked for deactivated employee: {$user['employee_no']}",
                'USER',
                (string)$user['id']
            );

            Session::flash('error', 'Your employment account is inactive. Please contact Human Resources.');
            $this->redirect('/login');
            return;
        }

        // 6. Verify Password Hash using password_verify()
        if (!password_verify($password, $user['password'])) {
            $this->userModel->logActivity(
                (int)$user['id'],
                'AUTH_FAILED',
                "Password verification failed for user: {$username}",
                'USER',
                (string)$user['id']
            );

            Session::flash('error', 'Invalid username or password. Please verify your credentials and try again.');
            Session::flash('old_username', $username);
            $this->redirect('/login');
            return;
        }

        // 7. Transparent Password Rehashing if cost or algorithm updated
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $this->userModel->updatePasswordHash((int)$user['id'], $newHash);
        }

        // 8. Regenerate Session ID to Prevent Session Fixation
        Session::regenerate(true);

        // 9. Establish Authenticated Session Payload (Passwords and hashes are NEVER stored in session)
        $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $displayName = $fullName !== '' ? $fullName : $user['username'];

        Session::set('user', [
            'id'          => (int)$user['id'],
            'username'    => $user['username'],
            'role'        => strtolower($user['role']),
            'employee_id' => !empty($user['employee_id']) ? (int)$user['employee_id'] : null,
            'employee_no' => $user['employee_no'] ?? null,
            'name'        => $displayName,
            'email'       => $user['email'] ?? null,
            'department'  => $user['department_name'] ?? 'Unassigned',
            'position'    => $user['position_name'] ?? 'Unassigned',
            'login_time'  => time(),
        ]);
        Session::set('last_activity', time());

        // 10. Record Successful Authentication in Audit Log
        $this->userModel->logActivity(
            (int)$user['id'],
            'AUTH_LOGIN',
            "User {$user['username']} logged in successfully ({$user['role']})",
            'USER',
            (string)$user['id']
        );

        // 11. Role-directed Dispatch
        Session::flash('success', "Welcome back, {$displayName}!");
        $this->redirect('/dashboard');
    }

    /**
     * Terminate active authenticated session.
     * Route: POST /logout
     */
    public function logout(): void
    {
        // State-changing POST requests require CSRF token validation
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $token = $_POST['_csrf_token'] ?? null;
            if (!verify_csrf_token($token)) {
                Session::flash('error', 'Security token expired or invalid.');
                $this->redirect('/dashboard');
                return;
            }
        }

        $userId = currentUserId();
        $username = currentUser()['username'] ?? 'Unknown';

        if ($userId) {
            $this->userModel->logActivity(
                $userId,
                'AUTH_LOGOUT',
                "User {$username} signed out",
                'USER',
                (string)$userId
            );
        }

        // Invalidate session, clear data and destroy cookie
        Session::destroy();

        // Start fresh session to display logout feedback banner
        Session::start();
        Session::flash('success', 'You have been successfully signed out.');
        $this->redirect('/login');
    }

    /**
     * Role-directed dashboard router.
     * Route: GET /dashboard
     */
    public function dashboard(): void
    {
        $role = currentUserRole();

        if ($role === 'hr' || $role === 'admin') {
            $this->redirect('/hr/dashboard');
            return;
        }

        if ($role === 'employee') {
            $this->redirect('/employee/dashboard');
            return;
        }

        // Unknown role fail-closed
        http_response_code(403);
        $this->view('errors.403', [
            'pageTitle' => '403 Forbidden - Invalid Role Assignment',
            'message'   => 'Your user account does not have a recognized role assignment.'
        ], null);
    }
}
