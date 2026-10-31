<?php

/**
 * User TOTP Model
 * HRIS-SYSTEM — Persistence & Security Model (Phase 2: TOTP Authentication)
 */

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use DateTimeImmutable;
use PDO;

class UserTotp extends Model
{
    public const MAX_FAILED_ATTEMPTS = 5;
    public const LOCKOUT_SECONDS = 900; // 15 minutes

    public function findByUserId(int $userId): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM user_totp WHERE user_id = :user_id LIMIT 1",
            ['user_id' => $userId]
        );
    }

    public function isTotpEnabled(int $userId): bool
    {
        $row = $this->fetchOne(
            "SELECT is_enabled FROM user_totp WHERE user_id = :user_id LIMIT 1",
            ['user_id' => $userId]
        );

        return $row !== null && (int)$row['is_enabled'] === 1;
    }

    public function enrollSecret(int $userId, string $encryptedSecret, array $hashedRecoveryCodes): void
    {
        $existing = $this->findByUserId($userId);
        $codesJson = json_encode($hashedRecoveryCodes, JSON_THROW_ON_ERROR);

        if ($existing) {
            $this->execute(
                "UPDATE user_totp
                 SET encrypted_secret = :secret,
                     recovery_codes = :codes,
                     is_enabled = 0,
                     confirmed_at = NULL,
                     failed_attempts = 0,
                     locked_until = NULL
                 WHERE user_id = :user_id",
                [
                    'secret'  => $encryptedSecret,
                    'codes'   => $codesJson,
                    'user_id' => $userId,
                ]
            );
        } else {
            $this->execute(
                "INSERT INTO user_totp (user_id, encrypted_secret, recovery_codes, is_enabled, failed_attempts)
                 VALUES (:user_id, :secret, :codes, 0, 0)",
                [
                    'user_id' => $userId,
                    'secret'  => $encryptedSecret,
                    'codes'   => $codesJson,
                ]
            );
        }
    }

    public function confirmEnrollment(int $userId, int $initialTimestep): void
    {
        $this->execute(
            "UPDATE user_totp
             SET is_enabled = 1,
                 confirmed_at = NOW(),
                 last_used_timestep = :ts,
                 failed_attempts = 0,
                 locked_until = NULL
             WHERE user_id = :user_id",
            [
                'user_id' => $userId,
                'ts'      => $initialTimestep,
            ]
        );
    }

    public function updateLastUsedTimestep(int $userId, int $timestep): void
    {
        $this->execute(
            "UPDATE user_totp
             SET last_used_timestep = :ts,
                 failed_attempts = 0,
                 locked_until = NULL
             WHERE user_id = :user_id",
            [
                'user_id' => $userId,
                'ts'      => $timestep,
            ]
        );
    }

    public function updateRecoveryCodes(int $userId, array $remainingHashes): void
    {
        $codesJson = json_encode($remainingHashes, JSON_THROW_ON_ERROR);

        $this->execute(
            "UPDATE user_totp
             SET recovery_codes = :codes,
                 failed_attempts = 0,
                 locked_until = NULL
             WHERE user_id = :user_id",
            [
                'user_id' => $userId,
                'codes'   => $codesJson,
            ]
        );
    }

    public function disableTotp(int $userId): void
    {
        $this->execute(
            "UPDATE user_totp
             SET is_enabled = 0,
                 confirmed_at = NULL
             WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
    }

    public function recordFailedAttempt(int $userId): int
    {
        $user = $this->findByUserId($userId);
        if (!$user) {
            return 1;
        }

        $attempts = (int)$user['failed_attempts'] + 1;
        $lockUntil = null;

        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + self::LOCKOUT_SECONDS);
        }

        $this->execute(
            "UPDATE user_totp
             SET failed_attempts = :attempts,
                 locked_until = :lock_until
             WHERE user_id = :user_id",
            [
                'attempts'   => $attempts,
                'lock_until' => $lockUntil,
                'user_id'    => $userId,
            ]
        );

        return $attempts;
    }

    public function resetFailedAttempts(int $userId): void
    {
        $this->execute(
            "UPDATE user_totp
             SET failed_attempts = 0,
                 locked_until = NULL
             WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
    }

    /**
     * @return array{locked: bool, retry_after: int}
     */
    public function checkLockout(int $userId): array
    {
        $user = $this->findByUserId($userId);
        if (!$user || empty($user['locked_until'])) {
            return ['locked' => false, 'retry_after' => 0];
        }

        $lockExpires = strtotime((string)$user['locked_until']);
        $now = time();

        if ($lockExpires > $now) {
            return ['locked' => true, 'retry_after' => ($lockExpires - $now)];
        }

        // Expired lockout
        $this->resetFailedAttempts($userId);
        return ['locked' => false, 'retry_after' => 0];
    }
}
