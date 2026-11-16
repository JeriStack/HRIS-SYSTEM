<?php

/**
 * User Authentication Security State Model
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Tracks backend risk-based step-up flags (password change, email change,
 * MFA modification, account recovery, suspicious events) independently of `users`
 * and without touching `employees`.
 */

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class UserAuthSecurityState extends Model
{
    private const TIMESTAMP_COLUMNS = [
        'password_changed_at',
        'email_changed_at',
        'mfa_changed_at',
        'last_recovery_at',
        'last_suspicious_event_at',
    ];

    /**
     * Fetch the security state row for a user, if present.
     */
    public function findByUserId(int $userId): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM user_auth_security_states WHERE user_id = :user_id LIMIT 1",
            ['user_id' => $userId]
        );
    }

    /**
     * Return step-up requirement reason if the next login must require email OTP.
     */
    public function getRequiredStepUpReason(int $userId): ?string
    {
        $row = $this->findByUserId($userId);
        if ($row === null || (int)($row['require_otp_next_login'] ?? 0) !== 1) {
            return null;
        }

        $reason = trim((string)($row['step_up_reason'] ?? ''));
        return $reason !== '' ? $reason : 'security_policy_step_up';
    }

    /**
     * Require fresh email OTP on the user's next login and record the causal event timestamp.
     */
    public function flagStepUpRequired(int $userId, string $reason, ?string $timestampColumn = null): void
    {
        if ($userId <= 0) {
            return;
        }

        $safeReason = substr(trim($reason) !== '' ? trim($reason) : 'security_policy_step_up', 0, 100);
        $tsColSql = '';
        $tsInsertCol = '';
        $tsInsertVal = '';

        if ($timestampColumn !== null && in_array($timestampColumn, self::TIMESTAMP_COLUMNS, true)) {
            $tsInsertCol = ", `{$timestampColumn}`";
            $tsInsertVal = ', NOW()';
            $tsColSql = ", `{$timestampColumn}` = NOW()";
        }

        $sql = "
            INSERT INTO user_auth_security_states (`user_id`, `require_otp_next_login`, `step_up_reason`{$tsInsertCol})
            VALUES (:user_id, 1, :reason{$tsInsertVal})
            ON DUPLICATE KEY UPDATE
                `require_otp_next_login` = 1,
                `step_up_reason` = VALUES(`step_up_reason`){$tsColSql}
        ";

        $this->execute($sql, [
            'user_id' => $userId,
            'reason'  => $safeReason,
        ]);
    }

    /**
     * Clear the one-shot next-login OTP step-up requirement once full OTP verification succeeds.
     */
    public function clearStepUpRequired(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $this->execute(
            "UPDATE user_auth_security_states
             SET require_otp_next_login = 0,
                 step_up_reason = NULL
             WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
    }
}
