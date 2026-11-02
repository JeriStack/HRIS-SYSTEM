<?php

/**
 * Step-Up Authentication Service (Sensitive-Operation Protection)
 * Employee HRIS & Leave Management System
 *
 * Sensitive operations (provisioning accounts, changing roles/status,
 * deactivating employees) require a recent strong authentication. A request
 * passes when either:
 *   1. The user completed MFA or a step-up re-verification within the
 *      configured window (config.step_up_window, default 15 minutes), or
 *   2. The request supplies the acting user's current password, which is
 *      verified server-side (and then refreshes the step-up timestamp).
 *
 * Browser-supplied flags are never trusted; timestamps live in the server session.
 */

namespace App\Services;

use App\Models\User;
use Core\Session;

class StepUpAuthService
{
    public const FIELD = 'current_password';

    /** Failed step-up confirmations allowed per user within FAILURE_WINDOW before locking. */
    public const MAX_FAILURES = 5;
    public const FAILURE_WINDOW = 900; // 15 minutes

    private ?User $users;

    public function __construct(?User $users = null)
    {
        $this->users = $users;
    }

    /**
     * Configured step-up window in seconds.
     */
    public static function window(): int
    {
        return max(60, (int)config('config.step_up_window', 900));
    }

    /**
     * Whether the current session holds a recent MFA / step-up verification.
     */
    public function hasRecentVerification(): bool
    {
        $user = currentUser();
        if (empty($user['id'])) {
            return false;
        }

        $verifiedAt = max(
            (int)($user['last_mfa_verified_at'] ?? 0),
            (int)($user['last_step_up_at'] ?? 0)
        );

        return $verifiedAt > 0 && (time() - $verifiedAt) <= self::window();
    }

    /**
     * Enforce step-up verification for a sensitive operation.
     *
     * @param string $operation Short audit label (e.g. 'USER_ROLE_UPDATE')
     * @param string|null $submittedPassword Current password supplied with the request
     * @return array ['ok' => bool, 'error' => string|null]
     */
    public function verify(string $operation, ?string $submittedPassword): array
    {
        $user = currentUser();
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'Authentication required.'];
        }

        if ($this->hasRecentVerification()) {
            return ['ok' => true, 'error' => null];
        }

        // Throttle password guessing through the step-up prompt (e.g. from a hijacked session)
        $recentFailures = $this->users()->countRecentActions($userId, 'STEP_UP_FAILED', self::FAILURE_WINDOW);
        if ($recentFailures['count'] >= self::MAX_FAILURES) {
            $retryAfter = max(1, (int)$recentFailures['retry_after']);
            $minutes = max(1, (int)ceil($retryAfter / 60));

            $this->users()->logActivity(
                $userId,
                'STEP_UP_LOCKED',
                "Step-up re-verification temporarily locked for sensitive operation: {$operation}",
                'USER',
                (string)$userId
            );

            return [
                'ok'    => false,
                'error' => "Too many incorrect password confirmations. Please try again in about {$minutes} minute(s).",
            ];
        }

        $password = (string)($submittedPassword ?? '');
        if ($password === '') {
            return [
                'ok'    => false,
                'error' => 'For your security, please confirm your current password to complete this action.',
            ];
        }

        $record = $this->users()->findByIdWithAuthState($userId);
        if (!$record || !password_verify($password, (string)$record['password'])) {
            $this->users()->logActivity(
                $userId,
                'STEP_UP_FAILED',
                "Step-up re-verification failed for sensitive operation: {$operation}",
                'USER',
                (string)$userId
            );
            return ['ok' => false, 'error' => 'Password confirmation failed. The action was not performed.'];
        }

        // Refresh the server-side step-up timestamp
        $sessionUser = Session::get('user', []);
        if (is_array($sessionUser)) {
            $sessionUser['last_step_up_at'] = time();
            Session::set('user', $sessionUser);
        }

        $this->users()->logActivity(
            $userId,
            'STEP_UP_VERIFIED',
            "Step-up re-verification succeeded for sensitive operation: {$operation}",
            'USER',
            (string)$userId
        );

        return ['ok' => true, 'error' => null];
    }

    private function users(): User
    {
        return $this->users ??= new User();
    }
}
