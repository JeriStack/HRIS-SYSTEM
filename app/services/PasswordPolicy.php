<?php

/**
 * Unified Password Policy & Hashing Utility
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Centralizes bcrypt cost-12 hashing, rehash checks, and password recovery
 * complexity validation so password creation, login rehashing, and self-service
 * password resets never drift across controllers or models.
 */

declare(strict_types=1);

namespace App\Services;

class PasswordPolicy
{
    public const HASH_ALGO = PASSWORD_BCRYPT;
    public const HASH_COST = 12;
    public const MIN_LENGTH = 8;
    public const MAX_BYTES = 72;

    /**
     * Hash a plaintext password using the uniform cost-12 bcrypt standard.
     */
    public static function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, self::HASH_ALGO, ['cost' => self::HASH_COST]);
    }

    /**
     * Determine whether an existing hash should be upgraded to cost-12 bcrypt.
     */
    public static function needsRehash(string $storedHash): bool
    {
        return password_needs_rehash($storedHash, self::HASH_ALGO, ['cost' => self::HASH_COST]);
    }

    /**
     * Return user-facing password rules aligned with validateForRecovery().
     *
     * @return array<int, array{key: string, label: string}>
     */
    public static function rulesSummary(): array
    {
        return [
            ['key' => 'length', 'label' => '8–72 characters'],
            ['key' => 'upper', 'label' => 'Uppercase letter (A–Z)'],
            ['key' => 'lower', 'label' => 'Lowercase letter (a–z)'],
            ['key' => 'digit', 'label' => 'Number (0–9)'],
            ['key' => 'special', 'label' => 'Special character (!@#$%&*)'],
            ['key' => 'match', 'label' => 'Matches confirmation'],
        ];
    }

    /**
     * Validate a recovery password candidate + confirmation.
     *
     * @param string $newPassword
     * @param string $confirmPassword
     * @param array<string, mixed> $accountContext Optional ['email' => ..., 'username' => ...]
     * @return array<int, string> Validation error messages (empty when compliant)
     */
    public static function validateForRecovery(
        string $newPassword,
        string $confirmPassword,
        array $accountContext = []
    ): array {
        $errors = [];

        if ($newPassword === '' || $confirmPassword === '') {
            $errors[] = 'Both password and confirmation fields are required.';
            return $errors;
        }

        $len = strlen($newPassword);
        if ($len < self::MIN_LENGTH) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if ($len > self::MAX_BYTES) {
            $errors[] = 'Password must not exceed 72 characters.';
        }

        if (!preg_match('/[A-Z]/', $newPassword)) {
            $errors[] = 'Password must contain at least one uppercase letter (A-Z).';
        }

        if (!preg_match('/[a-z]/', $newPassword)) {
            $errors[] = 'Password must contain at least one lowercase letter (a-z).';
        }

        if (!preg_match('/[0-9]/', $newPassword)) {
            $errors[] = 'Password must contain at least one number (0-9).';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $newPassword)) {
            $errors[] = 'Password must contain at least one special character.';
        }

        if (!hash_equals($newPassword, $confirmPassword)) {
            $errors[] = 'New password and confirmation do not match.';
        }

        $lowerCandidate = strtolower(trim($newPassword));
        $email = strtolower(trim((string)($accountContext['email'] ?? '')));
        $username = strtolower(trim((string)($accountContext['username'] ?? '')));
        $emailLocal = $email !== '' && str_contains($email, '@') ? explode('@', $email)[0] : '';

        if (
            ($email !== '' && $lowerCandidate === $email)
            || ($username !== '' && (
                $lowerCandidate === $username
                || (strlen($username) >= 4 && str_contains($lowerCandidate, $username))
            ))
            || ($emailLocal !== '' && (
                $lowerCandidate === $emailLocal
                || (strlen($emailLocal) >= 4 && str_contains($lowerCandidate, $emailLocal))
            ))
        ) {
            $errors[] = 'Choose a password that does not match or contain your email address or username.';
        }

        return $errors;
    }

    /**
     * Narrow timing side-channels when returning uniform anti-enumeration responses.
     */
    public static function dummyTimingEqualize(string $candidate = 'nexora-reset-timing'): void
    {
        if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING) {
            hash_hmac('sha256', $candidate, 'timing-guard');
            return;
        }

        hash_hmac('sha256', $candidate, 'timing-guard');
        try {
            usleep(random_int(15000, 35000));
        } catch (\Throwable) {
            usleep(20000);
        }
    }
}
