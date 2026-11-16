<?php

/**
 * Risk-Based Authentication Evaluation Service
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Determines when an authenticated password + trusted-device presentation
 * must still perform additional Email OTP / step-up verification:
 * - Password reset / password change
 * - Email change
 * - MFA / OTP / recovery configuration changes
 * - Prior anomalous token replay or lost-device recovery
 * - Correlated multi-signal context drift on privileged (HR / Admin) accounts
 *
 * Security rule: Never reject or step up users solely due to an IP address change.
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\UserAuthSecurityState;

class AuthRiskService
{
    private UserAuthSecurityState $stateModel;
    private array $config;

    public function __construct(?UserAuthSecurityState $stateModel = null, ?array $config = null)
    {
        $this->stateModel = $stateModel ?? new UserAuthSecurityState();
        $this->config = $config ?? (array)config('auth_devices', []);
    }

    /**
     * Evaluate whether a login presenting a valid trusted device still requires Email OTP.
     *
     * @return array{require_otp:bool, reason:?string, revoke_trusted:bool}
     */
    public function evaluateLoginRisk(
        int $userId,
        string $role,
        ?array $deviceRow = null,
        ?string $currentUserAgent = null,
        ?string $currentIp = null
    ): array {
        // 1. Explicit backend security-state step-up triggers
        $flaggedReason = $this->stateModel->getRequiredStepUpReason($userId);
        if ($flaggedReason !== null) {
            return [
                'require_otp'    => true,
                'reason'         => $flaggedReason,
                'revoke_trusted' => false,
            ];
        }

        // 2. Stricter correlated multi-signal check for privileged HR / Admin accounts
        $strictHr = (bool)($this->config['stricter_hr_risk_checks'] ?? true);
        $normalizedRole = strtolower(trim($role));

        if ($strictHr && in_array($normalizedRole, ['hr', 'admin'], true) && $deviceRow !== null) {
            $storedBrowser = (string)($deviceRow['browser_name'] ?? '');
            $storedOs = (string)($deviceRow['os_name'] ?? '');
            $storedIp = (string)($deviceRow['last_ip'] ?? '');

            $currentParsed = TrustedDeviceService::parseUserAgent($currentUserAgent);
            $browserMismatch = $this->isSignificantValueMismatch($storedBrowser, $currentParsed['browser_name'], 'Unknown Browser');
            $osMismatch = $this->isSignificantValueMismatch($storedOs, $currentParsed['os_name'], 'Unknown OS');
            $networkShifted = $this->isDistinctNetworkPrefix($storedIp, (string)($currentIp ?? ''));

            // Require CORRELATED signals (never a lone IP change):
            // - Simultaneous OS + Browser mismatch (cannot be explained by browser update), OR
            // - Browser or OS family mismatch combined with a different network prefix.
            if (($browserMismatch && $osMismatch) || (($browserMismatch || $osMismatch) && $networkShifted)) {
                return [
                    'require_otp'    => true,
                    'reason'         => 'privileged_multi_signal_context_shift',
                    'revoke_trusted' => false,
                ];
            }
        }

        return [
            'require_otp'    => false,
            'reason'         => null,
            'revoke_trusted' => false,
        ];
    }

    public function flagPasswordChanged(int $userId): void
    {
        $this->stateModel->flagStepUpRequired($userId, 'password_changed', 'password_changed_at');
    }

    public function flagEmailChanged(int $userId): void
    {
        $this->stateModel->flagStepUpRequired($userId, 'email_changed', 'email_changed_at');
    }

    public function flagMfaChanged(int $userId): void
    {
        $this->stateModel->flagStepUpRequired($userId, 'mfa_configuration_changed', 'mfa_changed_at');
    }

    public function flagAccountStateChanged(int $userId): void
    {
        $this->stateModel->flagStepUpRequired($userId, 'account_role_or_status_changed', 'last_suspicious_event_at');
    }

    public function flagSuspiciousEvent(int $userId, string $reason = 'suspicious_auth_activity'): void
    {
        $this->stateModel->flagStepUpRequired($userId, $reason, 'last_suspicious_event_at');
    }

    public function flagRecoveryCompleted(int $userId): void
    {
        $this->stateModel->flagStepUpRequired($userId, 'trusted_device_recovery', 'last_recovery_at');
    }

    public function clearStepUpAfterVerifiedOtp(int $userId): void
    {
        $this->stateModel->clearStepUpRequired($userId);
    }

    /**
     * Map an internal step-up trigger key to a clear, user-safe explanation.
     */
    public static function describeStepUpReason(?string $reason): ?string
    {
        if ($reason === null || trim($reason) === '') {
            return null;
        }

        return match (trim($reason)) {
            'password_changed'                      => 'Additional Email OTP verification is required because your account password was recently changed.',
            'email_changed'                         => 'Additional Email OTP verification is required because your account email address was updated.',
            'mfa_configuration_changed'             => 'Additional Email OTP verification is required because your multi-factor security configuration changed.',
            'trusted_device_recovery'               => 'Lost-device recovery was completed on your account. Verify with Email OTP before trusting any new browser.',
            'validator_mismatch_suspected_replay'   => 'A suspicious or replayed browser token was automatically revoked. Verify with Email OTP to continue safely.',
            'concurrent_token_rotation_conflict'    => 'Your trusted browser token rotated in a parallel session. Please complete Email OTP verification once to resync.',
            'account_role_or_status_changed'        => 'Additional Email OTP verification is required due to a recent account role or status change.',
            'privileged_multi_signal_context_shift' => 'Additional Email OTP verification is required because your sign-in environment changed significantly for this privileged account.',
            default                                 => 'Additional Email OTP verification is required by your account security policy.',
        };
    }

    private function isSignificantValueMismatch(string $stored, string $current, string $unknownLabel): bool
    {
        $s = trim($stored);
        $c = trim($current);

        if ($s === '' || $c === '' || $s === $unknownLabel || $c === $unknownLabel) {
            return false;
        }

        return strcasecmp($s, $c) !== 0;
    }

    /**
     * Compare IPv4 /24 or IPv6 /48 prefixes as a soft secondary signal only.
     * Loopback addresses never count as a hostile network shift.
     */
    private function isDistinctNetworkPrefix(string $previousIp, string $currentIp): bool
    {
        $prev = trim($previousIp);
        $curr = trim($currentIp);
        if ($prev === '' || $curr === '' || $prev === $curr) {
            return false;
        }

        $loopbacks = ['127.0.0.1', '::1'];
        if (in_array($prev, $loopbacks, true) && in_array($curr, $loopbacks, true)) {
            return false;
        }

        if (filter_var($prev, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($curr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $pParts = explode('.', $prev);
            $cParts = explode('.', $curr);
            return "{$pParts[0]}.{$pParts[1]}.{$pParts[2]}" !== "{$cParts[0]}.{$cParts[1]}.{$cParts[2]}";
        }

        $binPrev = @inet_pton($prev);
        $binCurr = @inet_pton($curr);
        if ($binPrev !== false && $binCurr !== false && strlen($binPrev) === 16 && strlen($binCurr) === 16) {
            return substr($binPrev, 0, 6) !== substr($binCurr, 0, 6);
        }

        return false;
    }
}
