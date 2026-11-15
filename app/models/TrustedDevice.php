<?php

/**
 * Trusted Device Repository Model
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Manages selector + hashed-validator records in `trusted_devices`.
 * All expiration math uses the database clock (NOW()) to stay immune to
 * PHP vs MySQL timezone drift.
 */

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use PDO;

class TrustedDevice extends Model
{
    /**
     * Find a trusted device record by its public selector, evaluating expiry via MySQL clock.
     */
    public function findBySelector(string $selector): ?array
    {
        $sql = "
            SELECT
                td.*,
                UNIX_TIMESTAMP(td.expires_at) AS expires_at_ts,
                CASE WHEN td.expires_at <= NOW() THEN 1 ELSE 0 END AS is_expired
            FROM trusted_devices td
            WHERE td.selector = :selector
            LIMIT 1
        ";

        return $this->fetchOne($sql, ['selector' => $selector]);
    }

    /**
     * Find a single trusted device owned by the given user ID.
     */
    public function findByIdForUser(int $id, int $userId): ?array
    {
        $sql = "
            SELECT
                td.*,
                UNIX_TIMESTAMP(td.expires_at) AS expires_at_ts,
                CASE WHEN td.expires_at <= NOW() THEN 1 ELSE 0 END AS is_expired
            FROM trusted_devices td
            WHERE td.id = :id
              AND td.user_id = :user_id
            LIMIT 1
        ";

        return $this->fetchOne($sql, [
            'id'      => $id,
            'user_id' => $userId,
        ]);
    }

    /**
     * List trusted devices for a user (active first, then recent expired/revoked history).
     * Never returns `token_hash` or `selector` unless caller inspects safely; omits secret verifier hash.
     */
    public function listForUser(int $userId, int $limit = 25): array
    {
        $sql = "
            SELECT
                td.id,
                td.user_id,
                td.selector,
                td.device_name,
                td.browser_name,
                td.os_name,
                td.last_ip,
                td.trust_days,
                td.created_at,
                td.last_used_at,
                td.expires_at,
                td.revoked_at,
                td.revocation_reason,
                UNIX_TIMESTAMP(td.expires_at) AS expires_at_ts,
                CASE
                    WHEN td.revoked_at IS NOT NULL THEN 'revoked'
                    WHEN td.expires_at <= NOW() THEN 'expired'
                    ELSE 'active'
                END AS device_status
            FROM trusted_devices td
            WHERE td.user_id = :user_id
              AND (
                  td.revoked_at IS NULL
                  OR td.revoked_at >= (NOW() - INTERVAL 30 DAY)
              )
            ORDER BY
                (td.revoked_at IS NULL AND td.expires_at > NOW()) DESC,
                COALESCE(td.last_used_at, td.created_at) DESC,
                td.id DESC
            LIMIT :limit
        ";

        if ($this->db === null) {
            return [];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Count currently active (non-revoked, non-expired) trusted devices for a user.
     */
    public function countActiveForUser(int $userId): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM trusted_devices
            WHERE user_id = :user_id
              AND revoked_at IS NULL
              AND expires_at > NOW()
        ";

        return (int)$this->fetchColumn($sql, ['user_id' => $userId]);
    }

    /**
     * Insert a newly registered trusted browser record using MySQL clock expiration.
     *
     * @return array{id:int, expires_at:string, expires_at_ts:int}
     */
    public function createDevice(
        int $userId,
        string $selector,
        string $tokenHash,
        string $deviceName,
        ?string $browserName,
        ?string $osName,
        ?string $userAgent,
        ?string $ipAddress,
        int $trustDays
    ): array {
        $sql = "
            INSERT INTO trusted_devices (
                user_id,
                selector,
                token_hash,
                device_name,
                browser_name,
                os_name,
                user_agent,
                last_ip,
                trust_days,
                last_used_at,
                expires_at
            ) VALUES (
                :user_id,
                :selector,
                :token_hash,
                :device_name,
                :browser_name,
                :os_name,
                :user_agent,
                :last_ip,
                :trust_days,
                NOW(),
                NOW() + INTERVAL :expires_days DAY
            )
        ";

        if ($this->db === null) {
            throw new \RuntimeException('Database connection is not initialized.');
        }

        $safeTrustDays = max(1, $trustDays);
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':selector', $selector);
        $stmt->bindValue(':token_hash', $tokenHash);
        $stmt->bindValue(':device_name', substr($deviceName, 0, 150));
        $stmt->bindValue(':browser_name', $browserName !== null ? substr($browserName, 0, 64) : null);
        $stmt->bindValue(':os_name', $osName !== null ? substr($osName, 0, 64) : null);
        $stmt->bindValue(':user_agent', $userAgent !== null ? substr($userAgent, 0, 255) : null);
        $stmt->bindValue(':last_ip', $ipAddress !== null ? substr($ipAddress, 0, 45) : null);
        $stmt->bindValue(':trust_days', $safeTrustDays, PDO::PARAM_INT);
        $stmt->bindValue(':expires_days', $safeTrustDays, PDO::PARAM_INT);
        $stmt->execute();

        $id = (int)$this->lastInsertId();
        $row = $this->findByIdForUser($id, $userId);

        return [
            'id'            => $id,
            'expires_at'    => (string)($row['expires_at'] ?? ''),
            'expires_at_ts' => (int)($row['expires_at_ts'] ?? (time() + ($trustDays * 86400))),
        ];
    }

    /**
     * Atomically rotate the stored validator hash after verifying the expected current hash.
     * Prevents token replay and concurrent race conditions by matching `token_hash = :expected_hash`.
     * Does NOT extend `expires_at` (fixed expiration policy).
     */
    public function rotateValidatorAtomically(
        int $deviceId,
        string $expectedTokenHash,
        string $newTokenHash,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $browserName,
        ?string $osName,
        ?string $deviceName
    ): bool {
        if ($this->db === null) {
            return false;
        }

        $sql = "
            UPDATE trusted_devices
            SET
                token_hash = :new_hash,
                last_used_at = NOW(),
                last_ip = :last_ip,
                user_agent = COALESCE(:user_agent, user_agent),
                browser_name = COALESCE(:browser_name, browser_name),
                os_name = COALESCE(:os_name, os_name),
                device_name = COALESCE(:device_name, device_name)
            WHERE id = :id
              AND token_hash = :expected_hash
              AND revoked_at IS NULL
              AND expires_at > NOW()
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'new_hash'      => $newTokenHash,
            'last_ip'       => $ipAddress !== null ? substr($ipAddress, 0, 45) : null,
            'user_agent'    => $userAgent !== null ? substr($userAgent, 0, 255) : null,
            'browser_name'  => $browserName !== null ? substr($browserName, 0, 64) : null,
            'os_name'       => $osName !== null ? substr($osName, 0, 64) : null,
            'device_name'   => $deviceName !== null ? substr($deviceName, 0, 150) : null,
            'id'            => $deviceId,
            'expected_hash' => $expectedTokenHash,
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Revoke a single trusted device owned by `$userId` immediately.
     */
    public function revokeByIdForUser(int $id, int $userId, string $reason = 'user_revoked'): bool
    {
        if ($this->db === null) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE trusted_devices
            SET revoked_at = NOW(),
                revocation_reason = :reason
            WHERE id = :id
              AND user_id = :user_id
              AND revoked_at IS NULL
        ");
        $stmt->execute([
            'reason'  => substr($reason, 0, 100),
            'id'      => $id,
            'user_id' => $userId,
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Revoke an existing record matching selector + owner (used on replacement registration).
     */
    public function revokeBySelectorForUser(string $selector, int $userId, string $reason = 'replaced_by_new_registration'): bool
    {
        if ($this->db === null) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE trusted_devices
            SET revoked_at = NOW(),
                revocation_reason = :reason
            WHERE selector = :selector
              AND user_id = :user_id
              AND revoked_at IS NULL
        ");
        $stmt->execute([
            'reason'   => substr($reason, 0, 100),
            'selector' => $selector,
            'user_id'  => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Revoke all active trusted devices for a user immediately.
     */
    public function revokeAllForUser(int $userId, string $reason = 'user_revoked_all', ?int $exceptDeviceId = null): int
    {
        if ($this->db === null) {
            return 0;
        }

        if ($exceptDeviceId !== null && $exceptDeviceId > 0) {
            $stmt = $this->db->prepare("
                UPDATE trusted_devices
                SET revoked_at = NOW(),
                    revocation_reason = :reason
                WHERE user_id = :user_id
                  AND revoked_at IS NULL
                  AND id <> :except_id
            ");
            $stmt->bindValue(':reason', substr($reason, 0, 100));
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':except_id', $exceptDeviceId, PDO::PARAM_INT);
        } else {
            $stmt = $this->db->prepare("
                UPDATE trusted_devices
                SET revoked_at = NOW(),
                    revocation_reason = :reason
                WHERE user_id = :user_id
                  AND revoked_at IS NULL
            ");
            $stmt->bindValue(':reason', substr($reason, 0, 100));
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Revoke a specific device row upon suspected token replay / validator mismatch.
     */
    public function markAnomalyAndRevoke(int $deviceId, string $reason = 'validator_mismatch_suspected_replay'): bool
    {
        if ($this->db === null) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE trusted_devices
            SET revoked_at = NOW(),
                revocation_reason = :reason
            WHERE id = :id
              AND revoked_at IS NULL
        ");
        $stmt->execute([
            'reason' => substr($reason, 0, 100),
            'id'     => $deviceId,
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Enforce per-user device quota by revoking the oldest active device(s) beyond `$maxAllowed`.
     */
    public function evictOldestActiveOverLimit(int $userId, int $maxAllowed): int
    {
        if ($this->db === null || $maxAllowed < 1) {
            return 0;
        }

        $active = $this->fetchAll(
            "SELECT id
             FROM trusted_devices
             WHERE user_id = :user_id
               AND revoked_at IS NULL
               AND expires_at > NOW()
             ORDER BY COALESCE(last_used_at, created_at) DESC, id DESC",
            ['user_id' => $userId]
        );

        if (count($active) <= $maxAllowed) {
            return 0;
        }

        $toEvictIds = array_map(
            static fn(array $row): int => (int)$row['id'],
            array_slice($active, $maxAllowed)
        );

        $evicted = 0;
        foreach ($toEvictIds as $deviceId) {
            if ($this->revokeByIdForUser($deviceId, $userId, 'device_limit_exceeded')) {
                $evicted++;
            }
        }

        return $evicted;
    }

    /**
     * Delete expired or revoked device records older than retention window.
     */
    public function pruneExpiredAndOldRevoked(int $retentionDays = 90): int
    {
        if ($this->db === null) {
            return 0;
        }

        $safeRetention = max(7, $retentionDays);
        $stmt = $this->db->prepare("
            DELETE FROM trusted_devices
            WHERE (revoked_at IS NOT NULL AND revoked_at < (NOW() - INTERVAL :revoked_days DAY))
               OR (expires_at < (NOW() - INTERVAL :expired_days DAY))
        ");
        $stmt->bindValue(':revoked_days', $safeRetention, PDO::PARAM_INT);
        $stmt->bindValue(':expired_days', $safeRetention, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }
}
