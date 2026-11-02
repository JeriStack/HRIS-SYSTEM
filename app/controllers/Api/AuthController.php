<?php
/**
 * API Authentication Controller
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers\Api;

use Core\Session;
use App\Models\User;
use App\Models\EmailOtp;
use App\Services\MailService;
use App\Services\RealtimeTicketService;
use OpenApi\Attributes as OA;

class AuthController extends ApiController
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

    #[OA\Get(
        path: "/api/v1/auth/csrf",
        summary: "Retrieve active CSRF token",
        description: "Returns the active session CSRF token for API clients, SPAs, and Swagger UI testing. Use this token in the 'X-CSRF-Token' header on all POST, PUT, DELETE, and PATCH operations.",
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 200,
                description: "CSRF token successfully retrieved",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "CSRF token retrieved successfully."),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "csrf_token", type: "string", example: "a1b2c3d4e5f60718293a4b5c6d7e8f90"),
                                new OA\Property(property: "header_name", type: "string", example: "X-CSRF-Token")
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function csrf(): void
    {
        $this->success([
            'csrf_token'  => csrf_token(),
            'header_name' => 'X-CSRF-Token',
        ], 'CSRF token retrieved successfully.');
    }

    #[OA\Post(
        path: "/api/v1/auth/login",
        summary: "Primary credential login (Step 1: Password challenge)",
        description: "Validates user email and password against secure password hashes. On success, generates a cryptographically secure 6-digit numeric OTP with a 5-minute expiry, sends it via email, and establishes a pending challenge session state. The user is NOT authenticated until Step 2 (/api/v1/auth/verify-otp) completes.",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Registered account email and password credentials",
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "employee@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "UserSecretPass2026!")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Primary credentials verified; Email OTP challenge dispatched",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "A 6-digit verification code has been dispatched to your registered email address."),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "step", type: "string", example: "otp_required"),
                                new OA\Property(property: "email", type: "string", example: "employee@example.com"),
                                new OA\Property(property: "expires_in_seconds", type: "integer", example: 300)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Validation failure (missing required inputs or invalid email format)",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "error"),
                        new OA\Property(property: "code", type: "integer", example: 400),
                        new OA\Property(property: "message", type: "string", example: "Please provide both a valid email address and password.")
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Invalid credentials or inactive account",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "error"),
                        new OA\Property(property: "code", type: "integer", example: 401),
                        new OA\Property(property: "message", type: "string", example: "Invalid email address or password.")
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: "Account deactivated or unauthorized role",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "error"),
                        new OA\Property(property: "code", type: "integer", example: 403),
                        new OA\Property(property: "message", type: "string", example: "Access is restricted to active HR and Employee accounts.")
                    ]
                )
            )
        ]
    )]
    public function login(): void
    {
        $input = $this->getJsonInput();
        $rawEmail = trim((string)($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');

        if ($rawEmail === '' || $password === '' || !filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please provide both a valid email address and password.', 400);
        }

        $email = strtolower($rawEmail);

        // Primary login rate limiting (per account identifier and per client IP)
        $rateLimit = $this->userModel->checkLoginRateLimit($email, client_ip());
        if ($rateLimit['limited']) {
            $this->userModel->logActivity(null, 'AUTH_RATE_LIMITED', 'API sign-in temporarily blocked after repeated failed attempts', 'USER', login_identifier_key($email));
            $minutes = max(1, (int)ceil($rateLimit['retry_after'] / 60));
            $this->error("Too many failed sign-in attempts. Please try again in about {$minutes} minute(s).", 429);
        }
        $user = $this->userModel->findByEmail($email);

        if (!$user || $user['status'] !== 'Active') {
            // Spend comparable CPU time as a real password check to blunt timing-based enumeration
            password_hash($password, PASSWORD_DEFAULT);
            $this->userModel->logActivity(null, 'AUTH_FAILED', 'Failed API login for unknown or inactive account: ' . mask_email($email), 'USER', login_identifier_key($email));
            $this->error('Invalid email address or password.', 401);
        }

        // Password is verified BEFORE any account-state disclosure
        if (!password_verify($password, $user['password'])) {
            $this->userModel->logActivity((int)$user['id'], 'AUTH_FAILED', 'API password verification failed for: ' . mask_email($email), 'USER', (string)$user['id']);
            $this->error('Invalid email address or password.', 401);
        }

        if (!empty($user['employee_id']) && isset($user['employment_status']) && $user['employment_status'] !== 'Active') {
            $this->userModel->logActivity((int)$user['id'], 'AUTH_BLOCKED', 'API login blocked for inactive employee: ' . mask_email($email), 'USER', (string)$user['id']);
            $this->error('Your employment account is inactive. Please contact Human Resources.', 403);
        }

        $role = strtolower((string)$user['role']);
        if ($role !== 'hr' && $role !== 'employee') {
            $this->error('Access is restricted to active HR and Employee accounts.', 403);
        }

        // Rehash password if cost or algorithm updated
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $this->userModel->updatePasswordHash((int)$user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        $recipientEmail = strtolower(trim((string)($user['email'] ?? '')));
        if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $this->userModel->logActivity((int)$user['id'], 'AUTH_OTP_MISSING_EMAIL', "API login blocked: No valid email address configured for user ID: {$user['id']}", 'USER', (string)$user['id']);
            $this->error('Your account does not have a valid registered email address. Please contact HR.', 403);
        }

        // Refuse to issue new codes while the account is locked out from repeated failed guesses
        $lockout = $this->otpModel->checkLockout((int)$user['id']);
        if ($lockout['locked']) {
            $this->userModel->logActivity((int)$user['id'], 'AUTH_OTP_LOCKED_OUT', "API login blocked: OTP lockout active for user ID: {$user['id']}", 'USER', (string)$user['id']);
            $this->error($this->lockoutMessage($lockout['retry_after']), 429);
        }

        $issuance = $this->otpModel->checkIssuanceRateLimit((int)$user['id']);
        if (!$issuance['allowed']) {
            $minutes = max(1, (int)ceil($issuance['retry_after'] / 60));
            $this->error("Too many verification codes were requested. Please try again in about {$minutes} minute(s).", 429);
        }

        $plainOtp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->otpModel->createChallenge((int)$user['id'], $plainOtp);

        $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $displayName = $fullName !== '' ? $fullName : ($user['username'] ?? $recipientEmail);

        $emailSent = $this->mailService->sendOtpEmail($recipientEmail, $displayName, $plainOtp, EmailOtp::lifetimeMinutes());

        if (!$emailSent) {
            $this->otpModel->invalidateUserChallenges((int)$user['id']);
            $this->clearPendingState();
            $this->error('Unable to dispatch verification code via email. Please check mail service configuration.', 500);
        }

        // Regenerate the session ID so a pre-seeded/fixated session ID cannot ride into the OTP step
        Session::regenerate(true);
        $this->clearPendingState();
        Session::set('pending_otp_user_id', (int)$user['id']);
        Session::set('pending_otp_email', $recipientEmail);
        Session::set('pending_otp_name', $displayName);
        Session::set('pending_otp_time', time());

        $this->userModel->logActivity((int)$user['id'], 'AUTH_OTP_DISPATCHED', 'API login OTP dispatched to registered email for user: ' . mask_email($recipientEmail), 'USER', (string)$user['id']);

        $this->success([
            'step'               => 'otp_required',
            'email'              => $recipientEmail,
            'expires_in_seconds' => 300,
        ], 'A 6-digit verification code has been dispatched to your registered email address.');
    }

    #[OA\Post(
        path: "/api/v1/auth/verify-otp",
        summary: "Verify Email OTP (Step 2: Two-factor verification)",
        description: "Validates the 6-digit numeric OTP challenge. On success, regenerates session ID, establishes authenticated user session cookie (PHPSESSID), and returns user profile data.",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            description: "6-digit numeric OTP code received via email",
            content: new OA\JsonContent(
                required: ["otp"],
                properties: [
                    new OA\Property(property: "otp", type: "string", pattern: "^[0-9]{6}$", example: "123456")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Authentication successful; session established",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Authentication successful. Session established."),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "user_id", type: "integer", example: 1),
                                new OA\Property(property: "username", type: "string", example: "hr_admin"),
                                new OA\Property(property: "role", type: "string", example: "hr"),
                                new OA\Property(property: "name", type: "string", example: "HR Administrator"),
                                new OA\Property(property: "email", type: "string", example: "hr@company.local"),
                                new OA\Property(property: "csrf_token", type: "string", example: "d4e5f6a1b2c30718293a4b5c6d7e8f90")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Invalid OTP format or missing pending login session",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "error"),
                        new OA\Property(property: "code", type: "integer", example: 400),
                        new OA\Property(property: "message", type: "string", example: "Please enter a valid 6-digit numeric verification code.")
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Incorrect or expired OTP verification code",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "error"),
                        new OA\Property(property: "code", type: "integer", example: 401),
                        new OA\Property(property: "message", type: "string", example: "Invalid or expired verification code.")
                    ]
                )
            )
        ]
    )]
    public function verifyOtp(): void
    {
        $pendingUserId = (int)Session::get('pending_otp_user_id');
        if (!$pendingUserId) {
            $this->error('No pending authentication request found. Please complete Step 1 login first.', 400);
        }

        if ($this->pendingSessionExpired()) {
            $this->otpModel->invalidateUserChallenges($pendingUserId);
            $this->clearPendingState();
            $this->error('Your verification session has expired. Please initiate sign-in again.', 401);
        }

        $lockout = $this->otpModel->checkLockout($pendingUserId);
        if ($lockout['locked']) {
            $this->otpModel->invalidateUserChallenges($pendingUserId);
            $this->clearPendingState();
            $this->error($this->lockoutMessage($lockout['retry_after']), 429);
        }

        $input = $this->getJsonInput();
        $submittedOtp = trim((string)($input['otp'] ?? ''));

        if (!preg_match('/^[0-9]{6}$/', $submittedOtp)) {
            $this->error('Please enter a valid 6-digit numeric verification code.', 400);
        }

        $result = $this->otpModel->verifyOtp($pendingUserId, $submittedOtp);

        if (!$result['success']) {
            if (!empty($result['exhausted'])) {
                $this->clearPendingState();
                $this->userModel->logActivity($pendingUserId, 'AUTH_OTP_EXHAUSTED', "API OTP verification exhausted maximum attempts", 'USER', (string)$pendingUserId);
                $this->error('Maximum verification attempts exceeded. Please initiate sign-in again.', 401);
            }
            $this->userModel->logActivity($pendingUserId, 'AUTH_OTP_FAILED', "API incorrect or invalid OTP submitted (remaining attempts on challenge: " . ($result['remaining_attempts'] ?? 'n/a') . ")", 'USER', (string)$pendingUserId);
            $this->error($result['error'] ?? 'Invalid or expired verification code.', 401);
        }

        $user = $this->userModel->findById($pendingUserId);
        if (!$user || $user['status'] !== 'Active') {
            $this->clearPendingState();
            $this->error('Your account is inactive or no longer available.', 403);
        }

        $role = strtolower((string)$user['role']);
        if ($role !== 'hr' && $role !== 'employee') {
            $this->clearPendingState();
            $this->error('Access is restricted to active HR and Employee accounts.', 403);
        }

        if (!empty($user['employee_id']) && isset($user['employment_status']) && $user['employment_status'] !== 'Active') {
            $this->clearPendingState();
            $this->error('Your employment account is inactive. Please contact Human Resources.', 403);
        }

        // Bind the session to the current security-relevant account state
        $authState = $this->userModel->findByIdWithAuthState((int)$user['id']);
        $authFingerprint = $authState ? Session::computeAuthFingerprint($authState) : null;

        Session::regenerate(true);
        $this->clearPendingState();

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
            'last_mfa_verified_at' => time(),
            'auth_fingerprint'     => $authFingerprint,
        ]);
        Session::set('last_activity', time());

        $this->userModel->logActivity((int)$user['id'], 'AUTH_LOGIN', "User {$user['username']} completed API 2FA login ({$role})", 'USER', (string)$user['id']);

        $this->success([
            'user_id'    => (int)$user['id'],
            'username'   => $user['username'],
            'role'       => $role,
            'name'       => $displayName,
            'email'      => $user['email'] ?? null,
            'csrf_token' => csrf_token(),
        ], 'Authentication successful. Session established.');
    }

    #[OA\Post(
        path: "/api/v1/auth/resend-otp",
        summary: "Resend fresh Email OTP challenge",
        description: "Issues a fresh 6-digit numeric OTP challenge for the current pending login session, subject to a 60-second rate-limiting cooldown window.",
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Fresh OTP dispatched successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "A fresh verification code has been dispatched to your email.")
                    ]
                )
            ),
            new OA\Response(
                response: 429,
                description: "Cooldown active (too many requests)",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "error"),
                        new OA\Property(property: "code", type: "integer", example: 429),
                        new OA\Property(property: "message", type: "string", example: "Please wait 45 seconds before requesting another verification code.")
                    ]
                )
            )
        ]
    )]
    public function resendOtp(): void
    {
        $pendingUserId = (int)Session::get('pending_otp_user_id');
        if (!$pendingUserId) {
            $this->error('No pending sign-in request found.', 400);
        }

        if ($this->pendingSessionExpired()) {
            $this->otpModel->invalidateUserChallenges($pendingUserId);
            $this->clearPendingState();
            $this->error('Your verification session has expired. Please initiate sign-in again.', 401);
        }

        $lockout = $this->otpModel->checkLockout($pendingUserId);
        if ($lockout['locked']) {
            $this->otpModel->invalidateUserChallenges($pendingUserId);
            $this->clearPendingState();
            $this->error($this->lockoutMessage($lockout['retry_after']), 429);
        }

        $cooldown = $this->otpModel->checkResendCooldown($pendingUserId, EmailOtp::RESEND_COOLDOWN);
        if (!$cooldown['allowed']) {
            $this->error("Please wait {$cooldown['remaining_seconds']} seconds before requesting another verification code.", 429);
        }

        $user = $this->userModel->findById($pendingUserId);
        if (!$user) {
            $this->clearPendingState();
            $this->error('User account not found.', 400);
        }

        // Re-validate account state so a deactivated account cannot keep receiving codes
        $role = strtolower((string)$user['role']);
        if ($user['status'] !== 'Active' || ($role !== 'hr' && $role !== 'employee')) {
            $this->otpModel->invalidateUserChallenges($pendingUserId);
            $this->clearPendingState();
            $this->error('Your account is inactive or no longer available.', 403);
        }

        $recipientEmail = strtolower(trim((string)($user['email'] ?? '')));
        if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error('Your account does not have a valid registered email address. Please contact HR.', 403);
        }

        $issuance = $this->otpModel->checkIssuanceRateLimit($pendingUserId);
        if (!$issuance['allowed']) {
            $minutes = max(1, (int)ceil($issuance['retry_after'] / 60));
            $this->error("Too many verification codes were requested. Please try again in about {$minutes} minute(s).", 429);
        }

        $plainOtp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->otpModel->createChallenge($pendingUserId, $plainOtp);

        $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $displayName = $fullName !== '' ? $fullName : ($user['username'] ?? $recipientEmail);

        $emailSent = $this->mailService->sendOtpEmail($recipientEmail, $displayName, $plainOtp, EmailOtp::lifetimeMinutes());

        if (!$emailSent) {
            // Do not leave a live, never-delivered challenge behind
            $this->otpModel->invalidateUserChallenges($pendingUserId);
            $this->error('Unable to dispatch verification code via email. Please check mail service configuration.', 500);
        }

        $this->userModel->logActivity($pendingUserId, 'AUTH_OTP_RESENT', 'API OTP resent to registered email: ' . mask_email($recipientEmail), 'USER', (string)$pendingUserId);

        $this->success([
            'email'              => $recipientEmail,
            'expires_in_seconds' => EmailOtp::OTP_LIFETIME,
        ], 'A fresh verification code has been dispatched to your email.');
    }

    #[OA\Post(
        path: "/api/v1/auth/logout",
        summary: "Terminate authenticated session",
        description: "Destroys the current authenticated PHP session and clears session cookies.",
        tags: ["Authentication"],
        security: [
            ["cookieAuth" => []],
            ["csrfToken" => []]
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Session successfully terminated",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Signed out successfully.")
                    ]
                )
            )
        ]
    )]
    public function logout(): void
    {
        $userId = currentUserId();
        if ($userId) {
            $this->userModel->logActivity($userId, 'AUTH_LOGOUT', "User signed out via API", 'USER', (string)$userId);
        }
        RealtimeTicketService::revokeCurrentSession();
        Session::destroy();
        $this->success(null, 'Signed out successfully.');
    }

    private function clearPendingState(): void
    {
        Session::remove('pending_otp_user_id');
        Session::remove('pending_otp_email');
        Session::remove('pending_otp_name');
        Session::remove('pending_otp_time');
    }

    /**
     * Whether the pending (post-password) session state has outlived its absolute lifetime.
     * Resending a code does NOT extend this window; the client must re-submit credentials.
     */
    private function pendingSessionExpired(): bool
    {
        $startedAt = (int)Session::get('pending_otp_time', 0);
        return $startedAt <= 0 || (time() - $startedAt) > EmailOtp::PENDING_SESSION_LIFETIME;
    }

    private function lockoutMessage(int $retryAfterSeconds): string
    {
        $minutes = max(1, (int)ceil($retryAfterSeconds / 60));
        return "Too many incorrect verification codes. Please try again in about {$minutes} minute(s).";
    }
}
