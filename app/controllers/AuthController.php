<?php
/**
 * Authentication Controller
 * Manages login, two-factor Email OTP verification, logout, session lifecycle,
 * and strict role-directed dashboard routing (HR and Employee only)
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\User;
use App\Models\EmailOtp;
use App\Services\MailService;
use App\Services\RealtimeTicketService;

class AuthController extends Controller
{
    private User $userModel;
    private EmailOtp $otpModel;
    private MailService $mailService;

    public function __construct(
        ?User $userModel = null,
        ?EmailOtp $otpModel = null,
        ?MailService $mailService = null
    ) {
        $this->userModel   = $userModel ?? new User();
        $this->otpModel    = $otpModel ?? new EmailOtp();
        $this->mailService = $mailService ?? new MailService();
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
        $oldEmail = Session::getFlash('old_email', '');

        $this->view('auth.login', [
            'pageTitle'   => 'Sign In - Employee HRIS & Leave Management System',
            'error'       => $error,
            'success'     => $success,
            'timeout'     => $timeout,
            'oldEmail'    => $oldEmail,
            'csrfToken'   => csrf_token(),
        ], null); // Render standalone without outer layout
    }

    /**
     * Process authentication credentials and initiate Email OTP challenge.
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
        $rawEmail = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($rawEmail === '' || $password === '') {
            Session::flash('error', 'Please provide both email address and password.');
            Session::flash('old_email', $rawEmail);
            $this->redirect('/login');
            return;
        }

        // 3. Validate Email Address Format
        if (!filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please provide a valid email address.');
            Session::flash('old_email', $rawEmail);
            $this->redirect('/login');
            return;
        }

        $email = strtolower($rawEmail);

        // 4. Query User by Normalized Registered Email Address
        $user = $this->userModel->findByEmail($email);

        // 5. Validate Account Existence and Status (Generic message prevents account enumeration)
        if (!$user || $user['status'] !== 'Active') {
            // Spend comparable CPU time as a real password check to blunt timing-based enumeration
            password_hash($password, PASSWORD_DEFAULT);

            $this->userModel->logActivity(
                null,
                'AUTH_FAILED',
                "Failed authentication attempt for unknown or inactive email: {$email}",
                'USER',
                $email
            );

            Session::flash('error', 'Invalid email address or password. Please verify your credentials and try again.');
            Session::flash('old_email', $rawEmail);
            $this->redirect('/login');
            return;
        }

        // 6. Verify Password Hash using password_verify()
        // (Performed BEFORE any account-state disclosure so status is never revealed to unauthenticated callers)
        if (!password_verify($password, $user['password'])) {
            $this->userModel->logActivity(
                (int)$user['id'],
                'AUTH_FAILED',
                "Password verification failed for email: {$email}",
                'USER',
                (string)$user['id']
            );

            Session::flash('error', 'Invalid email address or password. Please verify your credentials and try again.');
            Session::flash('old_email', $rawEmail);
            $this->redirect('/login');
            return;
        }

        // 7. Validate Associated Employment Status if linked to employee
        if (!empty($user['employee_id']) && isset($user['employment_status']) && $user['employment_status'] !== 'Active') {
            $this->userModel->logActivity(
                (int)$user['id'],
                'AUTH_BLOCKED',
                "Login blocked for deactivated employee: {$user['employee_no']} ({$email})",
                'USER',
                (string)$user['id']
            );

            Session::flash('error', 'Your employment account is inactive. Please contact Human Resources.');
            $this->redirect('/login');
            return;
        }

        // 8. Confirm Strict Application Role: exactly 'hr' or 'employee'
        $role = strtolower((string)$user['role']);
        if ($role !== 'hr' && $role !== 'employee') {
            $this->userModel->logActivity(
                (int)$user['id'],
                'AUTH_BLOCKED_ROLE',
                "Login blocked: Unsupported role '{$user['role']}' attempted authentication",
                'USER',
                (string)$user['id']
            );

            Session::flash('error', 'Unauthorized role assignment. Access is restricted to HR and Employee accounts.');
            $this->redirect('/login');
            return;
        }

        // 9. Transparent Password Rehashing if cost or algorithm updated
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $this->userModel->updatePasswordHash((int)$user['id'], $newHash);
        }

        // 10. Verify Recipient Email from Database Account Record
        $recipientEmail = strtolower(trim((string)($user['email'] ?? '')));
        if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $this->userModel->logActivity(
                (int)$user['id'],
                'AUTH_OTP_MISSING_EMAIL',
                "Login blocked: No valid email address configured for user ID: {$user['id']}",
                'USER',
                (string)$user['id']
            );

            Session::flash('error', 'Your account does not have a valid registered email address. Please contact HR.');
            $this->redirect('/login');
            return;
        }

        // 10b. Refuse to issue new codes while the account is locked out from repeated failed guesses
        $lockout = $this->otpModel->checkLockout((int)$user['id']);
        if ($lockout['locked']) {
            $this->userModel->logActivity(
                (int)$user['id'],
                'AUTH_OTP_LOCKED_OUT',
                "Login blocked: OTP lockout active for user ID: {$user['id']}",
                'USER',
                (string)$user['id']
            );

            Session::flash('error', $this->lockoutMessage($lockout['retry_after']));
            $this->redirect('/login');
            return;
        }

        // 11. Generate Cryptographically Secure 6-Digit Numeric OTP
        $plainOtp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // 12. Persist Hashed OTP Challenge (Atomically supersedes any previous pending challenge)
        $this->otpModel->createChallenge((int)$user['id'], $plainOtp);

        // 13. Dispatch Email via PHPMailer SMTP Service
        $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $displayName = $fullName !== '' ? $fullName : ($user['username'] ?? $recipientEmail);

        $emailSent = $this->mailService->sendOtpEmail($recipientEmail, $displayName, $plainOtp, EmailOtp::lifetimeMinutes());

        if (!$emailSent) {
            // Invalidate challenge and clear state if delivery failed
            $this->otpModel->invalidateUserChallenges((int)$user['id']);
            $this->clearPendingOtpSession();

            $this->userModel->logActivity(
                (int)$user['id'],
                'AUTH_OTP_DELIVERY_FAILED',
                "Failed to dispatch OTP email to {$recipientEmail}: " . ($this->mailService->getLastError() ?? 'SMTP error'),
                'USER',
                (string)$user['id']
            );

            Session::flash('error', 'Unable to dispatch verification code via email. Please verify SMTP settings or try again.');
            $this->redirect('/login');
            return;
        }

        // 14. Establish Pending Challenge State in Session (User is NOT authenticated yet)
        // Regenerate the session ID so a pre-seeded/fixated session ID cannot ride into the OTP step
        Session::regenerate(true);
        $this->clearPendingOtpSession();
        Session::set('pending_otp_user_id', (int)$user['id']);
        Session::set('pending_otp_email', $recipientEmail);
        Session::set('pending_otp_name', $displayName);
        Session::set('pending_otp_time', time());

        $this->userModel->logActivity(
            (int)$user['id'],
            'AUTH_OTP_DISPATCHED',
            "OTP verification code sent to registered email for user: {$recipientEmail}",
            'USER',
            (string)$user['id']
        );

        Session::flash('success', 'A 6-digit verification code has been sent to your email.');
        $this->redirect('/verify-otp');
    }

    /**
     * Render the Email OTP verification view.
     * Route: GET /verify-otp
     */
    public function showVerifyOtpForm(): void
    {
        // If already authenticated, redirect to dashboard
        if (isAuthenticated()) {
            $this->redirect('/dashboard');
            return;
        }

        $pendingUserId = (int)Session::get('pending_otp_user_id');
        if (!$pendingUserId) {
            Session::flash('error', 'No pending sign-in request found. Please enter your credentials first.');
            $this->redirect('/login');
            return;
        }

        if ($this->pendingSessionExpired()) {
            $this->expirePendingSession($pendingUserId);
            $this->redirect('/login');
            return;
        }

        $error = Session::getFlash('error');
        $success = Session::getFlash('success');
        $rawEmail = (string)Session::get('pending_otp_email', '');
        $maskedEmail = $this->maskEmail($rawEmail);

        // Check remaining cooldown seconds for resend action
        $cooldown = $this->otpModel->checkResendCooldown($pendingUserId);
        $resendCooldownRemaining = $cooldown['allowed'] ? 0 : $cooldown['remaining_seconds'];

        $this->view('auth.verify_otp', [
            'pageTitle'               => 'Verify Sign-In OTP - Employee HRIS & Leave Management System',
            'error'                   => $error,
            'success'                 => $success,
            'maskedEmail'             => $maskedEmail,
            'resendCooldownRemaining' => $resendCooldownRemaining,
            'csrfToken'               => csrf_token(),
        ], null);
    }

    /**
     * Verify submitted Email OTP and establish authenticated session.
     * Route: POST /verify-otp
     */
    public function verifyOtp(): void
    {
        // 1. Validate CSRF Protection
        $submittedToken = $_POST['_csrf_token'] ?? null;
        if (!verify_csrf_token($submittedToken)) {
            Session::flash('error', 'Security token expired or invalid. Please try again.');
            $this->redirect('/verify-otp');
            return;
        }

        // 2. Validate Pending Challenge Session State
        $pendingUserId = (int)Session::get('pending_otp_user_id');
        if (!$pendingUserId) {
            Session::flash('error', 'No pending sign-in request found. Please enter your credentials first.');
            $this->redirect('/login');
            return;
        }

        if ($this->pendingSessionExpired()) {
            $this->expirePendingSession($pendingUserId);
            $this->redirect('/login');
            return;
        }

        // 2b. Enforce cross-challenge lockout (cannot be reset by requesting a fresh code)
        $lockout = $this->otpModel->checkLockout($pendingUserId);
        if ($lockout['locked']) {
            $this->otpModel->invalidateUserChallenges($pendingUserId);
            $this->clearPendingOtpSession();
            Session::flash('error', $this->lockoutMessage($lockout['retry_after']));
            $this->redirect('/login');
            return;
        }

        // 3. Validate Submitted OTP Format (6 numeric digits)
        $submittedOtp = trim((string)($_POST['otp'] ?? ''));
        if (!preg_match('/^[0-9]{6}$/', $submittedOtp)) {
            Session::flash('error', 'Please enter a valid 6-digit numeric verification code.');
            $this->redirect('/verify-otp');
            return;
        }

        // 4. Verify OTP Atomically in Database
        $result = $this->otpModel->verifyOtp($pendingUserId, $submittedOtp);

        if (!$result['success']) {
            if (!empty($result['exhausted'])) {
                // Invalidate pending session on exhaustion
                $this->clearPendingOtpSession();
                $this->userModel->logActivity(
                    $pendingUserId,
                    'AUTH_OTP_EXHAUSTED',
                    "User OTP verification exhausted maximum failed attempts (5/5)",
                    'USER',
                    (string)$pendingUserId
                );

                Session::flash('error', $result['error'] ?? 'Maximum attempts exceeded. Please sign in again.');
                $this->redirect('/login');
                return;
            }

            $this->userModel->logActivity(
                $pendingUserId,
                'AUTH_OTP_FAILED',
                "Incorrect or invalid OTP submitted (remaining attempts on challenge: " . ($result['remaining_attempts'] ?? 'n/a') . ")",
                'USER',
                (string)$pendingUserId
            );

            Session::flash('error', $result['error'] ?? 'Invalid verification code.');
            $this->redirect('/verify-otp');
            return;
        }

        // 5. Re-fetch user record to verify account is still active and valid
        $user = $this->userModel->findById($pendingUserId);
        if (!$user || $user['status'] !== 'Active') {
            $this->clearPendingOtpSession();
            Session::flash('error', 'Your account is inactive or no longer available.');
            $this->redirect('/login');
            return;
        }

        // 6. Enforce Strict Application Roles: Only HR and Employee are permitted
        $role = strtolower((string)$user['role']);
        if ($role !== 'hr' && $role !== 'employee') {
            $this->clearPendingOtpSession();
            $this->userModel->logActivity(
                (int)$user['id'],
                'AUTH_BLOCKED_ROLE',
                "Login blocked: Unsupported role '{$user['role']}' attempted login",
                'USER',
                (string)$user['id']
            );

            Session::flash('error', 'Unauthorized role assignment. Access is restricted to HR and Employee accounts.');
            $this->redirect('/login');
            return;
        }

        // 7. Regenerate Session ID to Prevent Session Fixation
        Session::regenerate(true);

        // 8. Clear Pending Challenge Data
        $this->clearPendingOtpSession();

        // 9. Establish Authenticated Session Payload
        $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $displayName = $fullName !== '' ? $fullName : $user['username'];

        Session::set('user', [
            'id'          => (int)$user['id'],
            'username'    => $user['username'],
            'role'        => $role,
            'employee_id' => !empty($user['employee_id']) ? (int)$user['employee_id'] : null,
            'employee_no' => $user['employee_no'] ?? null,
            'name'        => $displayName,
            'email'       => $user['email'] ?? null,
            'department'  => $user['department_name'] ?? 'Unassigned',
            'position'    => $user['position_name'] ?? 'Unassigned',
            'login_time'  => time(),
        ]);
        Session::set('last_activity', time());

        // 10. Record Successful 2FA Login in Audit Log
        $this->userModel->logActivity(
            (int)$user['id'],
            'AUTH_LOGIN',
            "User {$user['username']} completed Email OTP authentication successfully ({$role})",
            'USER',
            (string)$user['id']
        );

        // 11. Role-Directed Dispatch
        Session::flash('success', "Welcome back, {$displayName}!");
        $this->redirect('/dashboard');
    }

    /**
     * Resend fresh Email OTP subject to 60-second cooldown.
     * Route: POST /verify-otp/resend
     */
    public function resendOtp(): void
    {
        // 1. Validate CSRF Protection
        $submittedToken = $_POST['_csrf_token'] ?? null;
        if (!verify_csrf_token($submittedToken)) {
            Session::flash('error', 'Security token expired or invalid.');
            $this->redirect('/verify-otp');
            return;
        }

        // 2. Validate Pending Challenge Session State
        $pendingUserId = (int)Session::get('pending_otp_user_id');
        if (!$pendingUserId) {
            Session::flash('error', 'No pending sign-in request found.');
            $this->redirect('/login');
            return;
        }

        if ($this->pendingSessionExpired()) {
            $this->expirePendingSession($pendingUserId);
            $this->redirect('/login');
            return;
        }

        // 2b. Enforce cross-challenge lockout
        $lockout = $this->otpModel->checkLockout($pendingUserId);
        if ($lockout['locked']) {
            $this->otpModel->invalidateUserChallenges($pendingUserId);
            $this->clearPendingOtpSession();
            Session::flash('error', $this->lockoutMessage($lockout['retry_after']));
            $this->redirect('/login');
            return;
        }

        // 3. Enforce Server-Side Resend Cooldown (60 seconds)
        $cooldown = $this->otpModel->checkResendCooldown($pendingUserId);
        if (!$cooldown['allowed']) {
            Session::flash('error', "Please wait {$cooldown['remaining_seconds']} second(s) before requesting a new code.");
            $this->redirect('/verify-otp');
            return;
        }

        // 4. Retrieve Fresh Recipient Details
        $user = $this->userModel->findById($pendingUserId);
        if (!$user || empty($user['email'])) {
            Session::flash('error', 'Unable to resend verification code. Account email unavailable.');
            $this->redirect('/verify-otp');
            return;
        }

        // Re-validate account state so a deactivated account cannot keep receiving codes
        $resendRole = strtolower((string)$user['role']);
        if ($user['status'] !== 'Active' || ($resendRole !== 'hr' && $resendRole !== 'employee')) {
            $this->otpModel->invalidateUserChallenges($pendingUserId);
            $this->clearPendingOtpSession();
            Session::flash('error', 'Your account is inactive or no longer available.');
            $this->redirect('/login');
            return;
        }

        $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $displayName = $fullName !== '' ? $fullName : $user['username'];

        // 5. Generate and Persist New OTP
        $plainOtp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->otpModel->createChallenge($pendingUserId, $plainOtp);

        // 6. Dispatch via PHPMailer
        $emailSent = $this->mailService->sendOtpEmail($user['email'], $displayName, $plainOtp, EmailOtp::lifetimeMinutes());
        if (!$emailSent) {
            // Do not leave a live, never-delivered challenge behind
            $this->otpModel->invalidateUserChallenges($pendingUserId);
            Session::flash('error', 'Failed to dispatch replacement code via email. Please check SMTP settings or try again.');
            $this->redirect('/verify-otp');
            return;
        }

        $this->userModel->logActivity(
            $pendingUserId,
            'AUTH_OTP_RESENT',
            "Replacement OTP code dispatched to user email",
            'USER',
            (string)$pendingUserId
        );

        Session::flash('success', 'A replacement 6-digit verification code has been dispatched to your email.');
        $this->redirect('/verify-otp');
    }

    /**
     * Cancel or abandon pending sign-in verification.
     * Route: POST /verify-otp/cancel
     */
    public function cancelOtp(): void
    {
        $pendingUserId = (int)Session::get('pending_otp_user_id');
        if ($pendingUserId) {
            $this->otpModel->invalidateUserChallenges($pendingUserId);
        }
        $this->clearPendingOtpSession();

        Session::flash('success', 'Sign-in verification was cancelled.');
        $this->redirect('/login');
    }

    /**
     * Clear all pending OTP state variables from session.
     */
    private function clearPendingOtpSession(): void
    {
        Session::remove('pending_otp_user_id');
        Session::remove('pending_otp_email');
        Session::remove('pending_otp_name');
        Session::remove('pending_otp_time');
    }

    /**
     * Whether the pending (post-password) session state has outlived its absolute lifetime.
     * Resending a code does NOT extend this window; the user must re-enter credentials.
     */
    private function pendingSessionExpired(): bool
    {
        $startedAt = (int)Session::get('pending_otp_time', 0);
        return $startedAt <= 0 || (time() - $startedAt) > EmailOtp::PENDING_SESSION_LIFETIME;
    }

    /**
     * Tear down an expired pending session and flash the explanation.
     */
    private function expirePendingSession(int $userId): void
    {
        $this->otpModel->invalidateUserChallenges($userId);
        $this->clearPendingOtpSession();
        Session::flash('error', 'Your verification session has expired. Please sign in again.');
    }

    /**
     * Human-readable lockout notice.
     */
    private function lockoutMessage(int $retryAfterSeconds): string
    {
        $minutes = max(1, (int)ceil($retryAfterSeconds / 60));
        return "Too many incorrect verification codes. Please try again in about {$minutes} minute(s).";
    }

    /**
     * Mask email address for safe UI display (e.g. m***n@company.local).
     */
    private function maskEmail(?string $email): string
    {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'your registered email';
        }

        [$name, $domain] = explode('@', $email, 2);
        $len = strlen($name);

        if ($len <= 2) {
            $maskedName = substr($name, 0, 1) . '*';
        } else {
            $maskedName = substr($name, 0, 1) . str_repeat('*', min(4, $len - 2)) . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
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

        // Revoke real-time (WebSocket) tickets so open sockets are closed
        RealtimeTicketService::revokeCurrentSession();

        // Invalidate session, clear data and destroy cookie
        Session::destroy();

        // Start fresh session to display logout feedback banner
        Session::start();
        Session::flash('success', 'You have been successfully signed out.');
        $this->redirect('/login');
    }

    /**
     * Role-directed dashboard router.
     * Strict 2-role model: HR and Employee only.
     * Route: GET /dashboard
     */
    public function dashboard(): void
    {
        $role = currentUserRole();

        if ($role === 'hr') {
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
