<?php

/**
 * Password Recovery Service
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Implements anti-enumeration password recovery for active Admin, HR, and
 * Employee accounts:
 * - Uniform generic response + timing mitigation for unknown/inactive/throttled inputs
 * - Dual per-email and per-IP rolling rate limiting
 * - CSPRNG selector.verifier reset tokens (HMAC-SHA256 at rest, 15-minute TTL)
 * - Atomic single-use consumption + password hash upgrade (bcrypt cost 12)
 * - Mandatory revocation of trusted devices, WebSocket tickets, pending OTPs,
 *   and active sessions + next-login Email OTP step-up enforcement
 * - Synchronous PHPMailer/Mailpit delivery of reset links & security completion notices
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailOtp;
use App\Models\Notification;
use App\Models\PasswordReset;
use App\Models\RealtimeTicket;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Realtime\EventAuthorizer;
use Core\AuditService;
use Throwable;

class PasswordRecoveryService
{
    public const GENERIC_ACCEPTANCE_MESSAGE = 'If an account matches the information provided, password recovery instructions will be sent to the registered email address.';
    public const INVALID_TOKEN_MESSAGE = 'This password reset link is invalid, has expired, or has already been used. Please request a new password reset link.';

    public const DEFAULT_TTL_MINUTES = 15;
    public const RATE_WINDOW_SECONDS = 900;
    public const MAX_REQUESTS_PER_EMAIL = 3;
    public const MAX_REQUESTS_PER_IP = 10;

    private User $users;
    private PasswordReset $resets;
    private MailService $mailer;
    private TrustedDevice $trustedDevices;
    private EmailOtp $emailOtps;
    private RealtimeTicket $realtimeTickets;
    private AuthRiskService $riskService;
    private Notification $notifications;

    private int $tokenTtlMinutes;
    private int $rateWindowSeconds;
    private int $maxRequestsPerEmail;
    private int $maxRequestsPerIp;

    public function __construct(
        ?User $users = null,
        ?PasswordReset $resets = null,
        ?MailService $mailer = null,
        ?TrustedDevice $trustedDevices = null,
        ?EmailOtp $emailOtps = null,
        ?RealtimeTicket $realtimeTickets = null,
        ?AuthRiskService $riskService = null,
        ?Notification $notifications = null,
        int $tokenTtlMinutes = self::DEFAULT_TTL_MINUTES,
        int $rateWindowSeconds = self::RATE_WINDOW_SECONDS,
        int $maxRequestsPerEmail = self::MAX_REQUESTS_PER_EMAIL,
        int $maxRequestsPerIp = self::MAX_REQUESTS_PER_IP
    ) {
        $this->users = $users ?? new User();
        $this->resets = $resets ?? new PasswordReset();
        $this->mailer = $mailer ?? new MailService();
        $this->trustedDevices = $trustedDevices ?? new TrustedDevice();
        $this->emailOtps = $emailOtps ?? new EmailOtp();
        $this->realtimeTickets = $realtimeTickets ?? new RealtimeTicket();
        $this->riskService = $riskService ?? new AuthRiskService();
        $this->notifications = $notifications ?? new Notification();

        $this->tokenTtlMinutes = max(1, min(60, $tokenTtlMinutes));
        $this->rateWindowSeconds = max(60, $rateWindowSeconds);
        $this->maxRequestsPerEmail = max(1, $maxRequestsPerEmail);
        $this->maxRequestsPerIp = max(1, $maxRequestsPerIp);
    }

    /**
     * Handle a forgot-password submission without leaking account existence or status.
     *
     * @return array{ok: bool, status: string, message: string, email_dispatched: bool}
     */
    public function requestReset(string $rawEmail, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $normalizedEmail = strtolower(trim($rawEmail));
        if ($normalizedEmail === '' || !filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) || strlen($normalizedEmail) > 150) {
            return [
                'ok'               => false,
                'status'           => 'validation_error',
                'message'          => 'Please enter a valid registered email address.',
                'email_dispatched' => false,
            ];
        }

        $clientIp = $ipAddress !== null && trim($ipAddress) !== '' ? trim($ipAddress) : client_ip();
        $emailKey = login_identifier_key($normalizedEmail);
        $masked = mask_email($normalizedEmail);

        // Enforce rate limits before issuing tokens, always returning the same generic message
        $emailLimit = $this->resets->countRecentRequestsForEmailKey($emailKey, $this->rateWindowSeconds);
        $ipLimit = $clientIp !== '' ? $this->resets->countRecentRequestsForIp($clientIp, $this->rateWindowSeconds) : ['count' => 0, 'retry_after' => 0];

        $this->recordAuditEvent(
            null,
            'PASSWORD_RESET_REQUESTED',
            "Password recovery requested for {$masked}",
            'PASSWORD_RESET',
            $emailKey,
            'SUCCESS',
            $clientIp,
            $userAgent
        );

        if ($emailLimit['count'] >= $this->maxRequestsPerEmail || $ipLimit['count'] >= $this->maxRequestsPerIp) {
            $this->recordAuditEvent(
                null,
                'PASSWORD_RESET_THROTTLED',
                "Password recovery request throttled by rate limiter for {$masked}",
                'PASSWORD_RESET',
                $emailKey,
                'WARNING',
                $clientIp,
                $userAgent
            );
            PasswordPolicy::dummyTimingEqualize($normalizedEmail);
            return [
                'ok'               => true,
                'status'           => 'throttled',
                'message'          => self::GENERIC_ACCEPTANCE_MESSAGE,
                'email_dispatched' => false,
            ];
        }

        $user = $this->users->findByEmail($normalizedEmail);
        if (!$this->isAccountEligible($user)) {
            $this->recordAuditEvent(
                $user !== null ? (int)$user['id'] : null,
                'PASSWORD_RESET_IGNORED',
                "Password recovery request not eligible or unknown account for {$masked}",
                'PASSWORD_RESET',
                $user !== null ? (string)$user['id'] : $emailKey,
                'WARNING',
                $clientIp,
                $userAgent
            );
            PasswordPolicy::dummyTimingEqualize($normalizedEmail);
            return [
                'ok'               => true,
                'status'           => 'accepted',
                'message'          => self::GENERIC_ACCEPTANCE_MESSAGE,
                'email_dispatched' => false,
            ];
        }

        $userId = (int)$user['id'];
        $recipientEmail = strtolower(trim((string)($user['email'] ?? $normalizedEmail)));
        $recipientName = $this->formatDisplayName($user);

        // Issue CSPRNG selector.verifier pair and invalidate older active reset tokens
        $selector = bin2hex(random_bytes(16));
        $verifier = bin2hex(random_bytes(32));
        $tokenHash = $this->hashVerifier($verifier);

        $resetId = $this->resets->runInTransaction(function () use (
            $userId,
            $selector,
            $tokenHash,
            $recipientEmail,
            $clientIp,
            $userAgent
        ): int {
            $this->resets->invalidateActiveForUser($userId);
            return $this->resets->createToken(
                $userId,
                $selector,
                $tokenHash,
                $recipientEmail,
                $clientIp,
                $userAgent,
                $this->tokenTtlMinutes
            );
        });

        $resetUrl = absolute_url('/reset-password', ['token' => $selector . '.' . $verifier]);
        $sent = $this->sendResetEmail($recipientEmail, $recipientName, $resetUrl, $this->tokenTtlMinutes);

        $this->recordAuditEvent(
            $userId,
            $sent ? 'PASSWORD_RESET_ISSUED' : 'PASSWORD_RESET_EMAIL_FAILED',
            $sent
                ? "Password reset link issued for user ID {$userId} (" . mask_email($recipientEmail) . ')'
                : "Password reset link generated but email delivery failed for user ID {$userId}",
            'PASSWORD_RESET',
            (string)$resetId,
            $sent ? 'SUCCESS' : 'WARNING',
            $clientIp,
            $userAgent
        );

        return [
            'ok'               => true,
            'status'           => 'accepted',
            'message'          => self::GENERIC_ACCEPTANCE_MESSAGE,
            'email_dispatched' => $sent,
        ];
    }

    /**
     * Verify a raw reset token string (`selector.verifier`) without consuming it.
     *
     * @return array{valid: bool, reason: ?string, message: string, reset: ?array, user: ?array}
     */
    public function validateResetToken(string $rawToken): array
    {
        $parsed = $this->parseRawToken($rawToken);
        if ($parsed === null) {
            return $this->invalidTokenResult('malformed');
        }

        ['selector' => $selector, 'verifier' => $verifier] = $parsed;
        $row = $this->resets->findBySelector($selector);
        if ($row === null) {
            return $this->invalidTokenResult('not_found');
        }

        if (!empty($row['consumed_at'])) {
            return $this->invalidTokenResult('already_used');
        }

        if (!empty($row['invalidated_at'])) {
            return $this->invalidTokenResult('superseded');
        }

        if ((int)($row['is_expired'] ?? 1) === 1) {
            return $this->invalidTokenResult('expired');
        }

        $expectedHash = (string)($row['token_hash'] ?? '');
        $actualHash = $this->hashVerifier($verifier);
        if ($expectedHash === '' || !hash_equals($expectedHash, $actualHash)) {
            return $this->invalidTokenResult('hash_mismatch');
        }

        $user = $this->users->findByIdWithAuthState((int)$row['user_id']);
        if (!$this->isAccountEligible($user)) {
            return $this->invalidTokenResult('account_ineligible');
        }

        return [
            'valid'   => true,
            'reason'  => null,
            'message' => '',
            'reset'   => $row,
            'user'    => $user,
        ];
    }

    /**
     * Validate password strength, consume token atomically, update password hash,
     * revoke all prior trust/sessions/OTPs, enforce step-up OTP on next login, and notify.
     *
     * @return array{ok: bool, status: string, message: string, errors: array<int, string>}
     */
    public function completeReset(
        string $rawToken,
        string $newPassword,
        string $confirmPassword,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $clientIp = $ipAddress !== null && trim($ipAddress) !== '' ? trim($ipAddress) : client_ip();

        $validation = $this->validateResetToken($rawToken);
        if (!$validation['valid'] || $validation['reset'] === null || $validation['user'] === null) {
            $this->recordAuditEvent(
                $validation['reset']['user_id'] ?? null ? (int)$validation['reset']['user_id'] : null,
                'PASSWORD_RESET_TOKEN_REJECTED',
                'Rejected password reset completion attempt (' . ($validation['reason'] ?? 'invalid') . ')',
                'PASSWORD_RESET',
                null,
                'WARNING',
                $clientIp,
                $userAgent
            );

            return [
                'ok'      => false,
                'status'  => 'invalid_token',
                'message' => self::INVALID_TOKEN_MESSAGE,
                'errors'  => [],
            ];
        }

        $reset = $validation['reset'];
        $user = $validation['user'];
        $userId = (int)$user['id'];

        $policyErrors = PasswordPolicy::validateForRecovery($newPassword, $confirmPassword, [
            'email'    => (string)($user['email'] ?? ''),
            'username' => (string)($user['username'] ?? ''),
        ]);

        if ($policyErrors !== []) {
            return [
                'ok'      => false,
                'status'  => 'validation_error',
                'message' => $policyErrors[0],
                'errors'  => $policyErrors,
            ];
        }

        $newHash = PasswordPolicy::hash($newPassword);

        $consumed = $this->resets->runInTransaction(function () use ($reset, $userId, $newHash): bool {
            if (!$this->resets->consumeAtomically((int)$reset['id'])) {
                return false;
            }
            $this->users->updatePasswordHash($userId, $newHash);
            $this->resets->invalidateActiveForUser($userId);
            return true;
        });

        if (!$consumed) {
            return [
                'ok'      => false,
                'status'  => 'invalid_token',
                'message' => self::INVALID_TOKEN_MESSAGE,
                'errors'  => [],
            ];
        }

        // Enforce security revocation & post-recovery OTP verification
        $revokedDevices = 0;
        try {
            $revokedDevices = $this->trustedDevices->revokeAllForUser($userId, 'password_reset_completed');
        } catch (Throwable $e) {
            error_log('[PasswordRecovery] Failed revoking trusted devices: ' . get_class($e));
        }

        try {
            $this->realtimeTickets->revokeAllForUser($userId);
        } catch (Throwable $e) {
            error_log('[PasswordRecovery] Failed revoking WS tickets: ' . get_class($e));
        }

        try {
            $this->emailOtps->invalidateUserChallenges($userId);
        } catch (Throwable $e) {
            error_log('[PasswordRecovery] Failed invalidating OTPs: ' . get_class($e));
        }

        try {
            $this->riskService->flagPasswordChanged($userId);
        } catch (Throwable $e) {
            error_log('[PasswordRecovery] Failed flagging password changed: ' . get_class($e));
        }

        $this->notifyPasswordChanged($user, $revokedDevices, $clientIp);

        $this->recordAuditEvent(
            $userId,
            'PASSWORD_RESET_COMPLETED',
            "Password reset completed; revoked {$revokedDevices} trusted device(s) and invalidated active sessions.",
            'USER',
            (string)$userId,
            'SUCCESS',
            $clientIp,
            $userAgent
        );

        return [
            'ok'      => true,
            'status'  => 'completed',
            'message' => 'Your password has been updated. All previously trusted browsers and active sessions have been signed out. Please sign in with your new password and complete Email OTP verification.',
            'errors'  => [],
        ];
    }

    private function isAccountEligible(?array $user): bool
    {
        if ($user === null) {
            return false;
        }

        $role = strtolower(trim((string)($user['role'] ?? '')));
        if (!in_array($role, ['admin', 'hr', 'employee'], true)) {
            return false;
        }

        if (trim((string)($user['status'] ?? '')) !== 'Active') {
            return false;
        }

        if (isset($user['employment_status']) && trim((string)$user['employment_status']) !== '') {
            if (trim((string)$user['employment_status']) !== 'Active') {
                return false;
            }
        }

        $email = trim((string)($user['email'] ?? ''));
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @return array{selector: string, verifier: string}|null
     */
    private function parseRawToken(string $rawToken): ?array
    {
        $trimmed = trim($rawToken);
        if ($trimmed === '' || !str_contains($trimmed, '.')) {
            return null;
        }

        $parts = explode('.', $trimmed, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$selector, $verifier] = $parts;
        if (strlen($selector) !== 32 || strlen($verifier) !== 64) {
            return null;
        }

        if (!ctype_xdigit($selector) || !ctype_xdigit($verifier)) {
            return null;
        }

        return [
            'selector' => strtolower($selector),
            'verifier' => strtolower($verifier),
        ];
    }

    private function hashVerifier(string $verifier): string
    {
        return hash_hmac('sha256', $verifier, $this->resolvePepper());
    }

    private function resolvePepper(): string
    {
        foreach (['PASSWORD_RESET_PEPPER', 'TRUSTED_DEVICE_PEPPER', 'OTP_PEPPER_SECRET', 'APP_KEY'] as $key) {
            $val = getenv($key);
            if ($val === false || trim($val) === '') {
                $val = $_ENV[$key] ?? '';
            }
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        return 'nexora-hr-password-reset-local-fallback';
    }

    private function formatDisplayName(array $user): string
    {
        $fullName = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        $name = trim((string)($user['username'] ?? 'User'));
        return $name !== '' ? $name : 'User';
    }

    /**
     * @return array{valid: bool, reason: ?string, message: string, reset: ?array, user: ?array}
     */
    private function invalidTokenResult(string $reason): array
    {
        return [
            'valid'   => false,
            'reason'  => $reason,
            'message' => self::INVALID_TOKEN_MESSAGE,
            'reset'   => null,
            'user'    => null,
        ];
    }

    private function sendResetEmail(string $email, string $name, string $resetUrl, int $ttlMinutes): bool
    {
        $appName = e((string)config('config.app_name', 'NEXORA HR'));
        $safeName = e($name);
        $safeUrl = e($resetUrl);
        $year = date('Y');
        $subject = 'Password Reset Instructions - ' . config('config.app_name', 'NEXORA HR');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Password Reset Instructions</title></head>
<body style="margin:0;padding:24px;background:#F1F5F9;font-family:Arial,Helvetica,sans-serif;color:#0F172A;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;margin:0 auto;background:#FFFFFF;border:1px solid #CBD5E1;border-radius:10px;overflow:hidden;">
    <tr>
      <td align="center" style="background:#0F2C59;padding:20px 24px;color:#FFFFFF;text-align:center;">
        <div style="font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#93C5FD;font-weight:700;text-align:center;">{$appName} Account Recovery</div>
        <h1 style="margin:6px 0 0;font-size:18px;font-weight:700;color:#FFFFFF;text-align:center;">Reset Your Password</h1>
      </td>
    </tr>
    <tr>
      <td style="padding:24px;font-size:14px;line-height:1.6;">
        <p style="margin:0 0 12px;">Hello {$safeName},</p>
        <p style="margin:0 0 16px;text-align:justify;hyphens:auto;">We received a request to reset your {$appName} account password. Use the button below to choose a new password. This link is valid for <strong>{$ttlMinutes} minutes</strong> and can only be used once.</p>
        <p style="margin:20px 0;">
          <a href="{$safeUrl}" style="display:inline-block;padding:12px 20px;background:#1D4ED8;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:6px;">Reset Password</a>
        </p>
        <p style="margin:0 0 12px;font-size:12px;color:#475569;">If the button does not work, copy and paste this link into your browser:<br><span style="word-break:break-all;color:#1D4ED8;">{$safeUrl}</span></p>
        <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:12px 14px;font-size:13px;color:#334155;text-align:justify;hyphens:auto;">
          If you did not request a password reset, you can safely ignore this message. Your existing password, active sessions, and trusted devices remain unchanged until you complete a reset.
        </div>
      </td>
    </tr>
    <tr>
      <td style="padding:14px 24px;background:#F8FAFC;border-top:1px solid #E2E8F0;font-size:12px;color:#64748B;">
        Automated security notice from {$appName} &copy; {$year}. Never share reset links, passwords, or OTP codes.
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        $altBody = "Hello {$name},\n\n"
            . "Use this single-use link within {$ttlMinutes} minutes to reset your NEXORA HR password:\n"
            . "{$resetUrl}\n\n"
            . "If you did not request this, ignore this email. Never share reset links, passwords, or OTP codes.\n";

        try {
            return $this->mailer->sendMail($email, $name, $subject, $html, $altBody);
        } catch (Throwable $e) {
            error_log('[PasswordRecovery] Reset email error: ' . get_class($e));
            return false;
        }
    }

    private function notifyPasswordChanged(array $user, int $revokedDevices, ?string $ipAddress): void
    {
        $userId = (int)($user['id'] ?? 0);
        $email = strtolower(trim((string)($user['email'] ?? '')));
        $name = $this->formatDisplayName($user);
        $appName = e((string)config('config.app_name', 'NEXORA HR'));
        $safeName = e($name);
        $year = date('Y');

        if ($userId > 0) {
            try {
                $this->notifications->create(
                    $userId,
                    'Password Reset Completed',
                    'Your account password was changed via self-service recovery. All active sessions and trusted browsers (' . $revokedDevices . ') were signed out. Email OTP verification will be required at next sign-in.',
                    'warning',
                    EventAuthorizer::SECURITY_DEVICE_ALERT,
                    $userId
                );
            } catch (Throwable $e) {
                error_log('[PasswordRecovery] In-app alert failed: ' . get_class($e));
            }
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $ipLine = $ipAddress ? ' Request IP context: <code>' . e($ipAddress) . '</code>.' : '';
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Password Changed</title></head>
<body style="margin:0;padding:24px;background:#F1F5F9;font-family:Arial,Helvetica,sans-serif;color:#0F172A;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;margin:0 auto;background:#FFFFFF;border:1px solid #CBD5E1;border-radius:10px;overflow:hidden;">
    <tr>
      <td style="background:#0F2C59;padding:20px 24px;color:#FFFFFF;">
        <div style="font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#93C5FD;font-weight:700;">{$appName} Security Notice</div>
        <h1 style="margin:6px 0 0;font-size:18px;font-weight:700;color:#FFFFFF;">Your Password Has Been Reset</h1>
      </td>
    </tr>
    <tr>
      <td style="padding:24px;font-size:14px;line-height:1.6;">
        <p style="margin:0 0 12px;">Hello {$safeName},</p>
        <p style="margin:0 0 14px;">Your {$appName} password was updated successfully. For your security, <strong>all previously trusted browsers ({$revokedDevices}) and active sessions were signed out</strong>, and your next sign-in will require fresh Email OTP verification.{$ipLine}</p>
        <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:6px;padding:12px 14px;font-size:13px;color:#991B1B;">
          If you did not perform this password reset, contact Human Resources or your IT administrator immediately via the Public Support portal.
        </div>
      </td>
    </tr>
    <tr>
      <td style="padding:14px 24px;background:#F8FAFC;border-top:1px solid #E2E8F0;font-size:12px;color:#64748B;">
        Automated security notice from {$appName} &copy; {$year}. Never reply with passwords or codes.
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        $altBody = "Hello {$name},\n\n"
            . "Your NEXORA HR password was updated. All previously trusted devices ({$revokedDevices}) and active sessions were revoked, "
            . "and fresh Email OTP verification will be required at next login.\n\n"
            . "If you did not initiate this change, report the incident to Human Resources immediately.\n";

        try {
            $this->mailer->sendMail($email, $name, 'Security Notice: Password Reset Completed', $html, $altBody);
        } catch (Throwable $e) {
            error_log('[PasswordRecovery] Completion email failed: ' . get_class($e));
        }
    }

    private function recordAuditEvent(
        ?int $userId,
        string $action,
        string $description,
        string $entityType,
        ?string $entityId,
        string $status,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        try {
            AuditService::getInstance()->record([
                'user_id'     => $userId,
                'action'      => $action,
                'category'    => AuditService::CATEGORY_AUTH,
                'description' => $description,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'status'      => $status,
                'ip_address'  => $ipAddress ?? client_ip(),
                'user_agent'  => $userAgent !== null ? substr($userAgent, 0, 255) : null,
            ]);
        } catch (Throwable) {
            try {
                $this->users->logActivity(
                    $userId,
                    $action,
                    $description,
                    $entityType,
                    $entityId,
                    null,
                    null,
                    $status
                );
            } catch (Throwable) {
                // Fail-safe logging
            }
        }
    }
}
