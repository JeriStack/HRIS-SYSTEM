<?php

/**
 * Password Reset Token Model
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Manages CSPRNG selector + HMAC-SHA256 hashed verifier password reset records
 * with strict MySQL-clock expiration, atomic single-use consumption, and prior
 * token invalidation.
 */

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class PasswordReset extends Model
{
    /**
     * Invalidate any active, unconsumed reset tokens previously issued for a user.
     */
    public function invalidateActiveForUser(int $userId): int
    {
        $stmt = $this->query(
            "UPDATE password_resets
             SET invalidated_at = NOW()
             WHERE user_id = :user_id
               AND consumed_at IS NULL
               AND invalidated_at IS NULL",
            ['user_id' => $userId]
        );

        return $stmt->rowCount();
    }

    /**
     * Persist a newly generated password reset token using MySQL's clock for expiration.
     */
    public function createToken(
        int $userId,
        string $selector,
        string $tokenHash,
        string $requestedEmail,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        int $ttlMinutes = 15
    ): int {
        $safeTtl = max(1, min(60, $ttlMinutes));
        $safeEmail = substr(strtolower(trim($requestedEmail)), 0, 150);
        $safeIp = $ipAddress !== null && trim($ipAddress) !== '' ? substr(trim($ipAddress), 0, 45) : null;
        $safeUa = $userAgent !== null && trim($userAgent) !== '' ? substr(trim($userAgent), 0, 255) : null;

        $this->execute(
            "INSERT INTO password_resets
                (user_id, selector, token_hash, requested_email, requested_ip, user_agent, expires_at)
             VALUES
                (:user_id, :selector, :token_hash, :requested_email, :requested_ip, :user_agent, DATE_ADD(NOW(), INTERVAL {$safeTtl} MINUTE))",
            [
                'user_id'         => $userId,
                'selector'        => $selector,
                'token_hash'      => $tokenHash,
                'requested_email' => $safeEmail,
                'requested_ip'    => $safeIp,
                'user_agent'      => $safeUa,
            ]
        );

        return (int)$this->lastInsertId();
    }

    /**
     * Look up a password reset record by its public hex selector along with live expiration status.
     */
    public function findBySelector(string $selector): ?array
    {
        return $this->fetchOne(
            "SELECT
                pr.*,
                UNIX_TIMESTAMP(pr.expires_at) AS expires_at_ts,
                (pr.expires_at <= NOW()) AS is_expired
             FROM password_resets pr
             WHERE pr.selector = :selector
             LIMIT 1",
            ['selector' => $selector]
        );
    }

    /**
     * Atomically consume a valid reset token exactly once.
     * Returns false if consumed, invalidated, or expired concurrently.
     */
    public function consumeAtomically(int $resetId): bool
    {
        $stmt = $this->query(
            "UPDATE password_resets
             SET consumed_at = NOW()
             WHERE id = :id
               AND consumed_at IS NULL
               AND invalidated_at IS NULL
               AND expires_at > NOW()",
            ['id' => $resetId]
        );

        return $stmt->rowCount() === 1;
    }

    /**
     * Count recent password recovery attempts recorded for an account/email key.
     *
     * @return array{count: int, retry_after: int}
     */
    public function countRecentRequestsForEmailKey(string $emailIdentifierKey, int $windowSeconds = 900): array
    {
        $window = max(60, $windowSeconds);
        $row = $this->fetchOne(
            "SELECT
                COUNT(*) AS total,
                GREATEST(0, {$window} - TIMESTAMPDIFF(SECOND, MIN(created_at), NOW())) AS retry_after
             FROM activity_logs
             WHERE action = 'PASSWORD_RESET_REQUESTED'
               AND entity_type = 'PASSWORD_RESET'
               AND entity_id = :ident
               AND created_at > (NOW() - INTERVAL {$window} SECOND)",
            ['ident' => $emailIdentifierKey]
        );

        return [
            'count'       => (int)($row['total'] ?? 0),
            'retry_after' => (int)($row['retry_after'] ?? 0),
        ];
    }

    /**
     * Count recent password recovery attempts from a source IP address.
     *
     * @return array{count: int, retry_after: int}
     */
    public function countRecentRequestsForIp(string $ipAddress, int $windowSeconds = 900): array
    {
        $window = max(60, $windowSeconds);
        $row = $this->fetchOne(
            "SELECT
                COUNT(*) AS total,
                GREATEST(0, {$window} - TIMESTAMPDIFF(SECOND, MIN(created_at), NOW())) AS retry_after
             FROM activity_logs
             WHERE action = 'PASSWORD_RESET_REQUESTED'
               AND entity_type = 'PASSWORD_RESET'
               AND ip_address = :ip
               AND created_at > (NOW() - INTERVAL {$window} SECOND)",
            ['ip' => $ipAddress]
        );

        return [
            'count'       => (int)($row['total'] ?? 0),
            'retry_after' => (int)($row['retry_after'] ?? 0),
        ];
    }

    /**
     * Execute work inside an atomic database transaction sharing the model's PDO connection.
     */
    public function runInTransaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback();
            if ($this->db !== null && $this->db->inTransaction()) {
                $this->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if ($this->db !== null && $this->db->inTransaction()) {
                $this->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Purge aged consumed, invalidated, or expired token history beyond retention.
     */
    public function cleanupOldTokens(int $retentionDays = 7): int
    {
        $days = max(1, min(365, $retentionDays));
        $stmt = $this->query(
            "DELETE FROM password_resets
             WHERE expires_at < (NOW() - INTERVAL {$days} DAY)
                OR (consumed_at IS NOT NULL AND consumed_at < (NOW() - INTERVAL {$days} DAY))
                OR (invalidated_at IS NOT NULL AND invalidated_at < (NOW() - INTERVAL {$days} DAY))"
        );

        return $stmt->rowCount();
    }
}
