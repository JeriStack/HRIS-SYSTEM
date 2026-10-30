<?php
/**
 * Email OTP Model
 * Secure persistence, atomic verification, attempt tracking, and lifecycle management
 * Employee HRIS & Leave Management System
 */

namespace App\Models;

use Core\Model;
use PDO;
use Exception;

class EmailOtp extends Model
{
    public const MAX_ATTEMPTS = 5;
    public const OTP_LIFETIME = 300; // 5 minutes
    public const RESEND_COOLDOWN = 60; // 60 seconds

    /** Max failed guesses allowed across ALL challenges for a user within LOCKOUT_WINDOW. */
    public const LOCKOUT_THRESHOLD = 10;
    public const LOCKOUT_WINDOW = 900; // 15 minutes

    /** Absolute lifetime of the pending (post-password, pre-OTP) session state. Not extended by resends. */
    public const PENDING_SESSION_LIFETIME = 900; // 15 minutes

    /**
     * OTP lifetime expressed in whole minutes, for email templates.
     */
    public static function lifetimeMinutes(): int
    {
        return (int)ceil(self::OTP_LIFETIME / 60);
    }

    /**
     * Determine whether a user is temporarily locked out of OTP flows.
     * Failed attempts are summed across every challenge in the rolling window, so requesting
     * a fresh code (which resets the per-challenge counter) cannot be used to keep guessing.
     *
     * @param int $userId
     * @return array ['locked' => bool, 'retry_after' => int seconds]
     */
    public function checkLockout(int $userId): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::LOCKOUT_WINDOW);
        $row = $this->fetchOne(
            "SELECT COALESCE(SUM(attempts), 0) AS failed, MIN(created_at) AS oldest
             FROM email_otps
             WHERE user_id = :user_id AND attempts > 0 AND created_at > :cutoff",
            ['user_id' => $userId, 'cutoff' => $cutoff]
        );

        if (!$row || (int)$row['failed'] < self::LOCKOUT_THRESHOLD) {
            return ['locked' => false, 'retry_after' => 0];
        }

        $retryAfter = (strtotime((string)$row['oldest']) + self::LOCKOUT_WINDOW) - time();
        return ['locked' => true, 'retry_after' => max(1, $retryAfter)];
    }

    /**
     * Create a new secure OTP challenge for a user.
     * Inactive/previous unconsumed challenges for this user are atomically superseded.
     *
     * @param int $userId
     * @param string $plainOtp 6-digit numeric OTP
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param int $lifetimeSeconds Default 300 (5 minutes)
     * @return int Inserted challenge ID
     */
    public function createChallenge(
        int $userId,
        string $plainOtp,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        int $lifetimeSeconds = self::OTP_LIFETIME
    ): int {
        $otpHash = password_hash($plainOtp, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', time() + $lifetimeSeconds);
        $ip = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ua = substr($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'), 0, 255);

        // Opportunistic retention cleanup (~1% of issuances); failures must never block sign-in
        if (random_int(1, 100) === 1) {
            try {
                $this->cleanupOldChallenges();
            } catch (Exception $e) {
                error_log("[EmailOtp::cleanupOldChallenges Error] " . $e->getMessage());
            }
        }

        $this->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');

            // Supersede any existing pending challenges for this user
            $supersedeSql = "UPDATE email_otps SET consumed_at = :now WHERE user_id = :user_id AND consumed_at IS NULL";
            $this->execute($supersedeSql, [
                'now'     => $now,
                'user_id' => $userId,
            ]);

            // Insert fresh hashed challenge with PHP synchronized timestamp
            $insertSql = "
                INSERT INTO email_otps (user_id, otp_hash, expires_at, attempts, created_at, ip_address, user_agent)
                VALUES (:user_id, :otp_hash, :expires_at, 0, :created_at, :ip_address, :user_agent)
            ";
            $this->execute($insertSql, [
                'user_id'    => $userId,
                'otp_hash'   => $otpHash,
                'expires_at' => $expiresAt,
                'created_at' => $now,
                'ip_address' => $ip,
                'user_agent' => $ua,
            ]);

            $challengeId = (int)$this->lastInsertId();
            $this->commit();

            return $challengeId;
        } catch (Exception $e) {
            $this->rollBack();
            error_log("[EmailOtp::createChallenge Error] " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if a user is within the resend cooldown window (60 seconds).
     *
     * @param int $userId
     * @param int $cooldownSeconds
     * @return array ['allowed' => bool, 'remaining_seconds' => int]
     */
    public function checkResendCooldown(int $userId, int $cooldownSeconds = self::RESEND_COOLDOWN): array
    {
        $sql = "SELECT created_at FROM email_otps WHERE user_id = :user_id ORDER BY id DESC LIMIT 1";
        $latest = $this->fetchOne($sql, ['user_id' => $userId]);

        if (!$latest) {
            return ['allowed' => true, 'remaining_seconds' => 0];
        }

        $createdAt = strtotime($latest['created_at']);
        $elapsed = time() - $createdAt;

        if ($elapsed < $cooldownSeconds) {
            return [
                'allowed'           => false,
                'remaining_seconds' => $cooldownSeconds - $elapsed,
            ];
        }

        return ['allowed' => true, 'remaining_seconds' => 0];
    }

    /**
     * Atomically verify a submitted OTP against the active challenge using row locking.
     * Prevents race conditions, replay attacks, and brute force enumeration.
     *
     * @param int $userId
     * @param string $submittedOtp
     * @return array Status payload with flags: success, error, remaining_attempts, exhausted, expired
     */
    public function verifyOtp(int $userId, string $submittedOtp): array
    {
        $this->beginTransaction();
        try {
            // Lock the active challenge row for this user
            $sql = "
                SELECT id, user_id, otp_hash, expires_at, attempts, consumed_at
                FROM email_otps
                WHERE user_id = :user_id AND consumed_at IS NULL
                ORDER BY id DESC
                LIMIT 1
                FOR UPDATE
            ";

            $challenge = $this->fetchOne($sql, ['user_id' => $userId]);

            if (!$challenge) {
                $this->rollBack();
                return [
                    'success'   => false,
                    'error'     => 'No active verification code found. Please request a new code.',
                    'exhausted' => false,
                    'expired'   => false,
                ];
            }

            $now = date('Y-m-d H:i:s');

            // Check if already expired
            if (strtotime($challenge['expires_at']) <= time()) {
                // Invalidate expired challenge
                $this->execute("UPDATE email_otps SET consumed_at = :now WHERE id = :id", ['now' => $now, 'id' => $challenge['id']]);
                $this->commit();
                return [
                    'success'   => false,
                    'error'     => 'Verification code has expired. Please request a new code.',
                    'exhausted' => false,
                    'expired'   => true,
                ];
            }

            $currentAttempts = (int)$challenge['attempts'];

            // Check if attempt limit reached
            if ($currentAttempts >= self::MAX_ATTEMPTS) {
                $this->execute("UPDATE email_otps SET consumed_at = :now WHERE id = :id", ['now' => $now, 'id' => $challenge['id']]);
                $this->commit();
                return [
                    'success'   => false,
                    'error'     => 'Maximum verification attempts exceeded (5/5). Please request a new code.',
                    'exhausted' => true,
                    'expired'   => false,
                ];
            }

            // Verify submitted OTP against hashed value
            if (password_verify($submittedOtp, $challenge['otp_hash'])) {
                // Successfully verified: atomically consume the OTP
                $this->execute("UPDATE email_otps SET consumed_at = :now WHERE id = :id", ['now' => $now, 'id' => $challenge['id']]);
                $this->commit();

                return [
                    'success'      => true,
                    'challenge_id' => (int)$challenge['id'],
                ];
            }

            // Failed attempt: increment counter
            $newAttempts = $currentAttempts + 1;
            $remaining = self::MAX_ATTEMPTS - $newAttempts;

            if ($remaining <= 0) {
                // Invalidate challenge upon reaching maximum failed attempts
                $this->execute(
                    "UPDATE email_otps SET attempts = :attempts, consumed_at = :now WHERE id = :id",
                    ['attempts' => $newAttempts, 'now' => $now, 'id' => $challenge['id']]
                );
                $this->commit();

                return [
                    'success'            => false,
                    'error'              => 'Incorrect verification code. Maximum attempts reached. Please request a new code.',
                    'remaining_attempts' => 0,
                    'exhausted'          => true,
                    'expired'            => false,
                ];
            }

            $this->execute(
                "UPDATE email_otps SET attempts = :attempts WHERE id = :id",
                ['attempts' => $newAttempts, 'id' => $challenge['id']]
            );
            $this->commit();

            return [
                'success'            => false,
                'error'              => "Incorrect verification code. {$remaining} attempt(s) remaining.",
                'remaining_attempts' => $remaining,
                'exhausted'          => false,
                'expired'            => false,
            ];
        } catch (Exception $e) {
            $this->rollBack();
            error_log("[EmailOtp::verifyOtp Error] " . $e->getMessage());
            return [
                'success'   => false,
                'error'     => 'A system error occurred during verification. Please try again.',
                'exhausted' => false,
                'expired'   => false,
            ];
        }
    }

    /**
     * Invalidate all unconsumed challenges for a given user.
     * Used when cancelling or abandoning pending authentication.
     *
     * @param int $userId
     * @return bool
     */
    public function invalidateUserChallenges(int $userId): bool
    {
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE email_otps SET consumed_at = :now WHERE user_id = :user_id AND consumed_at IS NULL";
        return $this->execute($sql, ['now' => $now, 'user_id' => $userId]);
    }

    /**
     * Prune expired and consumed challenge records older than retention period (default 7 days).
     *
     * @param int $daysOld
     * @return bool
     */
    public function cleanupOldChallenges(int $daysOld = 7): bool
    {
        $sql = "DELETE FROM email_otps WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        return $this->execute($sql, ['days' => $daysOld]);
    }
}
