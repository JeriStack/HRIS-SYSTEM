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

    /** Max OTP codes issued (login + resend) per user within ISSUANCE_WINDOW (inbox-flooding protection). */
    public const MAX_ISSUANCES_PER_WINDOW = 5;
    public const ISSUANCE_WINDOW = 900; // 15 minutes

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
     * Server-side pepper secret (OTP_PEPPER_SECRET). Returns null when not configured,
     * in which case OTPs are hashed with password_hash() alone (legacy behaviour).
     */
    private static function pepperSecret(): ?string
    {
        $secret = getenv('OTP_PEPPER_SECRET');
        if ($secret === false || $secret === '') {
            $secret = $_ENV['OTP_PEPPER_SECRET'] ?? '';
        }
        $secret = (string)$secret;
        return strlen($secret) >= 16 ? $secret : null;
    }

    /**
     * Keyed HMAC-SHA256 of the OTP bound to the user ID. A stolen database alone is
     * insufficient to brute-force the 10^6 code space offline without the server secret.
     */
    public static function computePepper(int $userId, string $plainOtp): ?string
    {
        $secret = self::pepperSecret();
        if ($secret === null) {
            return null;
        }
        return hash_hmac('sha256', "{$userId}:{$plainOtp}", $secret);
    }

    /**
     * Hash an OTP for storage (peppered when a secret is configured).
     */
    public static function hashOtp(int $userId, string $plainOtp): string
    {
        $material = self::computePepper($userId, $plainOtp) ?? $plainOtp;
        return password_hash($material, PASSWORD_DEFAULT);
    }

    /**
     * Verify a submitted OTP against a stored hash. Accepts peppered hashes and,
     * for backward compatibility with challenges issued before the pepper existed, legacy hashes.
     */
    public static function verifyOtpHash(int $userId, string $submittedOtp, string $storedHash): bool
    {
        $peppered = self::computePepper($userId, $submittedOtp);
        if ($peppered !== null && password_verify($peppered, $storedHash)) {
            return true;
        }
        return password_verify($submittedOtp, $storedHash);
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
        // Window and retry are computed on the database clock so a timezone mismatch
        // between PHP and MySQL cannot make the rolling window silently fail.
        $window = self::LOCKOUT_WINDOW;
        $row = $this->fetchOne(
            "SELECT COALESCE(SUM(attempts), 0) AS failed,
                    GREATEST(0, {$window} - TIMESTAMPDIFF(SECOND, MIN(created_at), NOW())) AS retry_after
             FROM email_otps
             WHERE user_id = :user_id AND attempts > 0
               AND created_at > (NOW() - INTERVAL {$window} SECOND)",
            ['user_id' => $userId]
        );

        if (!$row || (int)$row['failed'] < self::LOCKOUT_THRESHOLD) {
            return ['locked' => false, 'retry_after' => 0];
        }

        return ['locked' => true, 'retry_after' => max(1, (int)$row['retry_after'])];
    }

    /**
     * Cap the number of OTP codes issued to a user in a rolling window, so repeated
     * login/resend cycles cannot flood the user's inbox.
     *
     * @param int $userId
     * @return array ['allowed' => bool, 'retry_after' => int seconds]
     */
    public function checkIssuanceRateLimit(int $userId): array
    {
        // DB-clock window (see checkLockout) to stay correct under PHP/MySQL timezone differences.
        $window = self::ISSUANCE_WINDOW;
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS issued,
                    GREATEST(0, {$window} - TIMESTAMPDIFF(SECOND, MIN(created_at), NOW())) AS retry_after
             FROM email_otps
             WHERE user_id = :user_id
               AND created_at > (NOW() - INTERVAL {$window} SECOND)",
            ['user_id' => $userId]
        );

        if (!$row || (int)$row['issued'] < self::MAX_ISSUANCES_PER_WINDOW) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        return ['allowed' => false, 'retry_after' => max(1, (int)$row['retry_after'])];
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
        $otpHash = self::hashOtp($userId, $plainOtp);
        $expiresAt = date('Y-m-d H:i:s', time() + $lifetimeSeconds);
        $ip = $ipAddress ?? (client_ip() ?? '127.0.0.1');
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

            // Let the database set created_at (NOW()) so issuance/lockout/cooldown windows,
            // which are evaluated on the DB clock, stay correct even if PHP and MySQL use
            // different timezones. expires_at remains an absolute instant computed from the
            // PHP lifetime and is compared only against PHP time in verifyOtp().
            $insertSql = "
                INSERT INTO email_otps (user_id, otp_hash, expires_at, attempts, created_at, ip_address, user_agent)
                VALUES (:user_id, :otp_hash, :expires_at, 0, NOW(), :ip_address, :user_agent)
            ";
            $this->execute($insertSql, [
                'user_id'    => $userId,
                'otp_hash'   => $otpHash,
                'expires_at' => $expiresAt,
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
        // Elapsed time is measured on the database clock to avoid PHP/MySQL timezone skew.
        $cooldownSeconds = max(1, $cooldownSeconds);
        $latest = $this->fetchOne(
            "SELECT GREATEST(0, :cooldown - TIMESTAMPDIFF(SECOND, created_at, NOW())) AS remaining
             FROM email_otps WHERE user_id = :user_id ORDER BY id DESC LIMIT 1",
            ['user_id' => $userId, 'cooldown' => $cooldownSeconds]
        );

        if (!$latest) {
            return ['allowed' => true, 'remaining_seconds' => 0];
        }

        $remaining = (int)$latest['remaining'];
        if ($remaining > 0) {
            return ['allowed' => false, 'remaining_seconds' => $remaining];
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

            // Verify submitted OTP against hashed value (peppered or legacy)
            if (self::verifyOtpHash($userId, $submittedOtp, (string)$challenge['otp_hash'])) {
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
     * Used when cancelling or abandoning pending authentication, or when account state changes.
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
