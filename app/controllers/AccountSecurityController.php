<?php

/**
 * Account Security & Trusted Device Management Controller
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Self-service security center accessible to all authenticated roles
 * (`admin`, `hr`, `employee`) under strict server-side identity scoping
 * (`currentUserId()`). Manages trusted browser listing, single/bulk
 * revocation, lost-device recovery, and authentication security telemetry.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Services\AuthRiskService;
use App\Services\TrustedDeviceService;
use Core\Controller;
use Core\Database;
use Core\Session;
use PDO;
use Throwable;

class AccountSecurityController extends Controller
{
    private User $userModel;
    private TrustedDeviceService $trustedDevices;
    private AuthRiskService $riskService;

    public function __construct(
        ?User $userModel = null,
        ?TrustedDeviceService $trustedDevices = null,
        ?AuthRiskService $riskService = null
    ) {
        $this->userModel = $userModel ?? new User();
        $this->trustedDevices = $trustedDevices ?? new TrustedDeviceService();
        $this->riskService = $riskService ?? new AuthRiskService();
    }

    /**
     * Render the authenticated user's Security & Trusted Devices page.
     * Route: GET /account/security
     */
    public function index(): void
    {
        $userId = currentUserId();
        if (!$userId) {
            $this->redirect('/login');
            return;
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            Session::flash('error', 'Account record could not be loaded.');
            $this->redirect('/dashboard');
            return;
        }

        $role = strtolower((string)($user['role'] ?? 'employee'));
        $rawCookie = $this->trustedDevices->readCookieFromRequest();
        $devices = $this->trustedDevices->listDevicesForDisplay($userId, $rawCookie);
        $activeCount = count(array_filter(
            $devices,
            static fn(array $d): bool => ($d['device_status'] ?? '') === 'active'
        ));
        $currentDevice = null;
        foreach ($devices as $device) {
            if (!empty($device['is_current_device']) && ($device['device_status'] ?? '') === 'active') {
                $currentDevice = $device;
                break;
            }
        }

        $trustDays = $this->trustedDevices->trustDaysForRole($role);
        $deviceConfig = (array)config('auth_devices', []);
        $stepUpReason = (new \App\Models\UserAuthSecurityState())->getRequiredStepUpReason($userId);
        $securityState = (new \App\Models\UserAuthSecurityState())->findByUserId($userId);
        $recentEvents = $this->getRecentUserAuthEvents($userId);
        $sessionUser = currentUser() ?? [];

        $this->view('account.security', [
            'pageTitle'        => 'Security & Trusted Devices',
            'user'             => $user,
            'role'             => $role,
            'devices'          => $devices,
            'activeCount'      => $activeCount,
            'currentDevice'    => $currentDevice,
            'trustDays'        => $trustDays,
            'employeeDays'     => (int)($deviceConfig['trust_days_by_role']['employee'] ?? 30),
            'hrDays'           => (int)($deviceConfig['trust_days_by_role']['hr'] ?? 7),
            'maxDevices'       => (int)($deviceConfig['max_devices_per_user'] ?? 10),
            'stepUpReason'     => $stepUpReason,
            'securityState'    => $securityState,
            'recentEvents'     => $recentEvents,
            'loginAuthMethod'  => (string)($sessionUser['login_auth_method'] ?? 'email_otp'),
            'csrfToken'        => csrf_token(),
        ], 'app');
    }

    /**
     * Revoke a single trusted device belonging to the authenticated user.
     * Route: POST /account/security/devices/{id}/revoke
     */
    public function revokeDevice(string $id): void
    {
        $userId = currentUserId();
        if (!$userId) {
            $this->denyUnauthenticated();
            return;
        }

        if (!ctype_digit($id) || (int)$id <= 0) {
            if (request_wants_json()) {
                $this->json(['status' => 'error', 'message' => 'Invalid device identifier.'], 422);
                return;
            }
            Session::flash('error', 'Invalid trusted device identifier.');
            $this->redirect($this->resolveReturnTarget());
            return;
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            $this->denyUnauthenticated();
            return;
        }

        $revoked = $this->trustedDevices->revokeDeviceForUser(
            $user,
            (int)$id,
            $this->trustedDevices->readCookieFromRequest(),
            client_ip(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        if (!$revoked) {
            if (request_wants_json()) {
                $this->json(['status' => 'error', 'message' => 'Trusted device not found or already revoked.'], 404);
                return;
            }
            Session::flash('error', 'Trusted device could not be revoked or was not found on your account.');
            $this->redirect($this->resolveReturnTarget());
            return;
        }

        if (request_wants_json()) {
            $this->json([
                'status'  => 'success',
                'message' => 'Trusted device revoked immediately. Email OTP will be required on that browser next sign-in.',
            ]);
            return;
        }

        Session::flash('success', 'Trusted device revoked immediately. Email OTP will be required on that browser at next sign-in.');
        $this->redirect($this->resolveReturnTarget());
    }

    /**
     * Revoke all trusted devices belonging to the authenticated user.
     * Route: POST /account/security/devices/revoke-all
     */
    public function revokeAllDevices(): void
    {
        $userId = currentUserId();
        if (!$userId) {
            $this->denyUnauthenticated();
            return;
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            $this->denyUnauthenticated();
            return;
        }

        $count = $this->trustedDevices->revokeAllDevicesForUser(
            $user,
            'user_revoked_all',
            true,
            client_ip(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        $msg = $count > 0
            ? "Revoked all {$count} active trusted device(s). Email OTP will be required on every browser next sign-in."
            : 'No active trusted devices remained on your account; browser trust state cleared.';

        if (request_wants_json()) {
            $this->json(['status' => 'success', 'revoked_count' => $count, 'message' => $msg]);
            return;
        }

        Session::flash('success', $msg);
        $this->redirect($this->resolveReturnTarget());
    }

    /**
     * Lost / compromised device recovery workflow.
     * Always verifies current account password explicitly before revoking all devices
     * and enforcing fresh Email OTP verification before any replacement device is trusted.
     * Route: POST /account/security/recover
     */
    public function recoverLostDevices(): void
    {
        $userId = currentUserId();
        if (!$userId) {
            $this->denyUnauthenticated();
            return;
        }

        $authUser = $this->userModel->findByIdWithAuthState($userId);
        if (!$authUser) {
            $this->denyUnauthenticated();
            return;
        }

        $submittedPassword = (string)($_POST['current_password'] ?? '');
        if ($submittedPassword === '') {
            Session::flash('error', 'Please enter your current password to authorize lost-device recovery.');
            $this->redirect('/account/security#recovery');
            return;
        }

        // Enforce failure lockout consistency via User::countRecentActions
        $recentFailures = $this->userModel->countRecentActions($userId, 'STEP_UP_FAILED', 900);
        if ((int)($recentFailures['count'] ?? 0) >= 5) {
            Session::flash('error', 'Too many failed identity confirmation attempts. Please wait 15 minutes before retrying recovery.');
            $this->redirect('/account/security#recovery');
            return;
        }

        if (!password_verify($submittedPassword, (string)($authUser['password'] ?? ''))) {
            $this->userModel->logActivity(
                $userId,
                'STEP_UP_FAILED',
                'Failed password confirmation during trusted-device recovery attempt.',
                'trusted_devices',
                (string)$userId,
                null,
                null,
                'FAILURE'
            );
            Session::flash('error', 'Incorrect password. Lost-device recovery was not authorized.');
            $this->redirect('/account/security#recovery');
            return;
        }

        $revokedCount = $this->trustedDevices->recoverLostDevices(
            $authUser,
            client_ip(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        if (request_wants_json()) {
            $this->json([
                'status'        => 'success',
                'revoked_count' => $revokedCount,
                'message'       => 'Recovery completed. All trusted devices revoked and fresh Email OTP required on next login.',
            ]);
            return;
        }

        Session::flash(
            'success',
            "Recovery completed: {$revokedCount} trusted device(s) revoked immediately. Fresh Email OTP verification is required before registering any replacement browser."
        );
        $this->redirect('/account/security');
    }

    /**
     * Fetch recent safe authentication & trusted-device activity metadata for the owner only.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getRecentUserAuthEvents(int $userId): array
    {
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare("
                SELECT action, description, status, ip_address, created_at
                FROM activity_logs
                WHERE user_id = :user_id
                  AND (
                      action LIKE 'AUTH_%'
                      OR action LIKE 'OTP_%'
                      OR action LIKE 'STEP_UP_%'
                      OR action = 'ADMIN_ACCOUNT_UPDATED'
                  )
                ORDER BY created_at DESC, id DESC
                LIMIT 15
            ");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function resolveReturnTarget(): string
    {
        $target = trim((string)($_POST['return_to'] ?? '/account/security'));
        $allowed = [
            '/account/security',
            '/employee/profile',
            '/employee/profile#security-devices',
            '/admin/account',
            '/admin/account#security-devices',
        ];

        return in_array($target, $allowed, true) ? $target : '/account/security';
    }

    private function denyUnauthenticated(): void
    {
        if (request_wants_json()) {
            $this->json(['status' => 'error', 'message' => 'Authentication required.'], 401);
            return;
        }
        $this->redirect('/login');
    }
}
