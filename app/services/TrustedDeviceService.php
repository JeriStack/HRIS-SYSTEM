<?php

/**
 * Trusted Device Authentication Service
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Implements cryptographic selector + hashed validator credentials for Smart OTP
 * ("Remember This Device") with fixed role-based expirations, constant-time
 * validator matching, atomic single-use rotation, fail-closed replay defense,
 * and self-service revocation / recovery workflows.
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use Core\AuditService;
use Throwable;

class TrustedDeviceService
{
    private const TOKEN_REGEX = '/^([a-f0-9]{32})\.([a-f0-9]{64})$/D';

    private TrustedDevice $devices;
    private AuthRiskService $riskService;
    private AuthSecurityNotificationService $notifier;
    private User $users;
    private array $config;

    /** @var array<string, mixed>|null Last cookie write descriptor for inspection in unit tests */
    private ?array $lastCookieAction = null;

    public function __construct(
        ?TrustedDevice $devices = null,
        ?AuthRiskService $riskService = null,
        ?AuthSecurityNotificationService $notifier = null,
        ?User $users = null,
        ?array $config = null
    ) {
        $this->devices = $devices ?? new TrustedDevice();
        $this->riskService = $riskService ?? new AuthRiskService();
        $this->notifier = $notifier ?? new AuthSecurityNotificationService();
        $this->users = $users ?? new User();
        $this->config = $config ?? (array)config('auth_devices', []);
    }

    public function getCookieName(): string
    {
        return (string)($this->config['cookie_name'] ?? 'nexora_td');
    }

    /**
     * Resolve configured trust duration (in days) for an application role.
     * Bounded strictly by `max_trust_days` so credentials never live indefinitely.
     */
    public function trustDaysForRole(string $role): int
    {
        $normalizedRole = strtolower(trim($role));
        $defaults = ['employee' => 30, 'hr' => 7, 'admin' => 7];
        $roleMap = (array)($this->config['trust_days_by_role'] ?? $defaults);
        $maxDays = max(1, (int)($this->config['max_trust_days'] ?? 90));

        $days = (int)($roleMap[$normalizedRole] ?? ($defaults[$normalizedRole] ?? 7));
        return max(1, min($maxDays, $days));
    }

    /**
     * Read the raw trusted-device cookie value from the HTTP request.
     */
    public function readCookieFromRequest(): ?string
    {
        $name = $this->getCookieName();
        $raw = $_COOKIE[$name] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        return trim($raw);
    }

    /**
     * Parse a selector.validator cookie payload cleanly.
     *
     * @return array{selector:string, validator:string}|null
     */
    public static function parseToken(?string $rawToken): ?array
    {
        if (!is_string($rawToken)) {
            return null;
        }

        if (preg_match(self::TOKEN_REGEX, $rawToken, $matches) !== 1) {
            return null;
        }

        return [
            'selector'  => $matches[1],
            'validator' => $matches[2],
        ];
    }

    /**
     * Evaluate whether the incoming trusted-device cookie allows skipping Email OTP
     * after password verification succeeds.
     *
     * Atomically rotates the secret validator when trusted. Fails closed to OTP on
     * any mismatch, expiry, revocation, risk trigger, or concurrent rotation conflict.
     *
     * @return array{
     *     trusted:bool,
     *     require_otp:bool,
     *     reason:string,
     *     device_id:?int,
     *     device_name:?string,
     *     expires_at:?string,
     *     expires_at_ts:?int,
     *     new_raw_token:?string,
     *     clear_cookie:bool,
     *     step_up_reason:?string
     * }
     */
    public function evaluateAndRotateOnLogin(
        array $user,
        ?string $rawCookie,
        ?string $userAgent = null,
        ?string $ipAddress = null
    ): array {
        $userId = (int)($user['id'] ?? 0);
        $role = strtolower((string)($user['role'] ?? 'employee'));

        // Always evaluate backend security-state step-up first even before touching cookie
        $stateRisk = $this->riskService->evaluateLoginRisk($userId, $role, null, $userAgent, $ipAddress);

        if ($rawCookie === null || trim($rawCookie) === '') {
            return $this->failResult(
                $stateRisk['require_otp'] ? 'risk_step_up' : 'no_cookie',
                false,
                $stateRisk['reason']
            );
        }

        $parsed = self::parseToken($rawCookie);
        if ($parsed === null) {
            $this->logDeviceEvent($userId, 'AUTH_TRUSTED_DEVICE_REJECTED', 'Malformed trusted-device cookie rejected; OTP challenge required.', null, $ipAddress, $userAgent, 'WARNING');
            return $this->failResult('malformed_cookie', true, $stateRisk['reason']);
        }

        $device = $this->devices->findBySelector($parsed['selector']);
        if ($device === null) {
            return $this->failResult('unknown_selector', true, $stateRisk['reason']);
        }

        // Shared-browser protection: if cookie belongs to another account, do NOT expose/revoke their device;
        // simply deny OTP bypass for this account.
        if ((int)($device['user_id'] ?? 0) !== $userId) {
            $this->logDeviceEvent($userId, 'AUTH_TRUSTED_DEVICE_REJECTED', 'Trusted-device cookie belongs to a different account; OTP challenge required.', null, $ipAddress, $userAgent, 'WARNING');
            return $this->failResult('user_mismatch', false, $stateRisk['reason']);
        }

        $deviceId = (int)$device['id'];
        $deviceName = (string)($device['device_name'] ?? 'Trusted Browser');

        if (!empty($device['revoked_at'])) {
            $this->logDeviceEvent($userId, 'AUTH_TRUSTED_DEVICE_REVOKED_REJECTED', "Attempted login with revoked trusted device ({$deviceName}).", $deviceId, $ipAddress, $userAgent, 'WARNING');
            return $this->failResult('revoked', true, $stateRisk['reason']);
        }

        if ((int)($device['is_expired'] ?? 1) === 1) {
            $this->logDeviceEvent($userId, 'AUTH_TRUSTED_DEVICE_EXPIRED', "Trusted device ({$deviceName}) expired; OTP verification required.", $deviceId, $ipAddress, $userAgent, 'INFO');
            return $this->failResult('expired', true, $stateRisk['reason']);
        }

        $presentedHash = $this->hashValidator($parsed['validator']);
        $storedHash = (string)($device['token_hash'] ?? '');
        if ($storedHash === '' || !hash_equals($storedHash, $presentedHash)) {
            // Suspected replay or cookie forgery on an active selector: revoke device & force step-up!
            $this->devices->markAnomalyAndRevoke($deviceId, 'validator_mismatch_suspected_replay');
            $this->riskService->flagSuspiciousEvent($userId, 'validator_mismatch_suspected_replay');
            $this->logDeviceEvent($userId, 'AUTH_TRUSTED_DEVICE_ANOMALY', "Validator mismatch on trusted device ({$deviceName}); credential revoked and step-up triggered.", $deviceId, $ipAddress, $userAgent, 'FAILURE');
            $this->notifier->notifyAnomalyDetected($user, $deviceName, $ipAddress);

            return $this->failResult('token_mismatch', true, 'validator_mismatch_suspected_replay');
        }

        // Now evaluate full risk policy including multi-signal context checks for HR/Admin
        $fullRisk = $this->riskService->evaluateLoginRisk($userId, $role, $device, $userAgent, $ipAddress);
        if ($fullRisk['require_otp']) {
            $reason = (string)($fullRisk['reason'] ?? 'security_policy_step_up');
            $this->logDeviceEvent($userId, 'AUTH_TRUSTED_DEVICE_STEP_UP', "Additional OTP verification required despite trusted device ({$deviceName}); trigger: {$reason}.", $deviceId, $ipAddress, $userAgent, 'WARNING');
            return $this->failResult('risk_step_up', false, $reason);
        }

        $parsedUa = self::parseUserAgent($userAgent);
        $newToken = null;
        $rotateOnUse = (bool)($this->config['rotate_on_use'] ?? true);

        if ($rotateOnUse) {
            $newValidator = bin2hex(random_bytes(32));
            $newHash = $this->hashValidator($newValidator);

            $rotated = $this->devices->rotateValidatorAtomically(
                $deviceId,
                $storedHash,
                $newHash,
                $ipAddress,
                $userAgent,
                $parsedUa['browser_name'],
                $parsedUa['os_name'],
                $parsedUa['device_name']
            );

            if (!$rotated) {
                // Concurrent request consumed the old validator or device was simultaneously revoked/expired
                $this->logDeviceEvent($userId, 'AUTH_TRUSTED_DEVICE_STEP_UP', "Trusted device rotation conflict on ({$deviceName}); failing closed to Email OTP.", $deviceId, $ipAddress, $userAgent, 'WARNING');
                return $this->failResult('rotation_conflict', false, 'concurrent_token_rotation_conflict');
            }

            $newToken = "{$parsed['selector']}.{$newValidator}";
            $this->emitCookie($newToken, (int)$device['expires_at_ts']);
        }

        $this->logDeviceEvent($userId, 'AUTH_TRUSTED_DEVICE_LOGIN', "Trusted device recognized ({$deviceName}); Email OTP skipped per active trust policy.", $deviceId, $ipAddress, $userAgent, 'SUCCESS');

        return [
            'trusted'        => true,
            'require_otp'    => false,
            'reason'         => 'trusted_rotated',
            'device_id'      => $deviceId,
            'device_name'    => $deviceName,
            'expires_at'     => (string)$device['expires_at'],
            'expires_at_ts'  => (int)$device['expires_at_ts'],
            'new_raw_token'  => $newToken,
            'clear_cookie'   => false,
            'step_up_reason' => null,
        ];
    }

    /**
     * Register the current browser as a trusted device ONLY after OTP succeeds.
     *
     * @return array{device_id:int, device_name:string, trust_days:int, expires_at:string, expires_at_ts:int, raw_token:string}
     */
    public function registerBrowserAfterOtp(
        array $user,
        ?string $existingRawCookie = null,
        ?string $userAgent = null,
        ?string $ipAddress = null
    ): array {
        $userId = (int)($user['id'] ?? 0);
        $role = strtolower((string)($user['role'] ?? 'employee'));
        $trustDays = $this->trustDaysForRole($role);

        // If this browser previously held a cookie owned by the same user, supersede it cleanly
        $existingParsed = self::parseToken($existingRawCookie);
        if ($existingParsed !== null) {
            $this->devices->revokeBySelectorForUser(
                $existingParsed['selector'],
                $userId,
                'replaced_by_new_registration'
            );
        }

        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $tokenHash = $this->hashValidator($validator);
        $parsedUa = self::parseUserAgent($userAgent);

        $created = $this->devices->createDevice(
            $userId,
            $selector,
            $tokenHash,
            $parsedUa['device_name'],
            $parsedUa['browser_name'],
            $parsedUa['os_name'],
            $userAgent,
            $ipAddress,
            $trustDays
        );

        $maxDevices = max(1, (int)($this->config['max_devices_per_user'] ?? 10));
        $this->devices->evictOldestActiveOverLimit($userId, $maxDevices);

        $rawToken = "{$selector}.{$validator}";
        $expiresTs = (int)$created['expires_at_ts'];
        $this->emitCookie($rawToken, $expiresTs);

        $expiresDisplay = date('M j, Y', $expiresTs);
        $this->logDeviceEvent(
            $userId,
            'AUTH_TRUSTED_DEVICE_CREATED',
            "Registered trusted device ({$parsedUa['device_name']}) valid for {$trustDays} day(s) until {$created['expires_at']}.",
            (int)$created['id'],
            $ipAddress,
            $userAgent,
            'SUCCESS'
        );

        $this->notifier->notifyDeviceRegistered($user, $parsedUa['device_name'], $expiresDisplay, $ipAddress);

        return [
            'device_id'     => (int)$created['id'],
            'device_name'   => $parsedUa['device_name'],
            'trust_days'    => $trustDays,
            'expires_at'    => (string)$created['expires_at'],
            'expires_at_ts' => $expiresTs,
            'raw_token'     => $rawToken,
        ];
    }

    /**
     * Revoke a single trusted device owned by the authenticated user.
     */
    public function revokeDeviceForUser(
        array $user,
        int $deviceId,
        ?string $currentRawCookie = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): bool {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0 || $deviceId <= 0) {
            return false;
        }

        $target = $this->devices->findByIdForUser($deviceId, $userId);
        if ($target === null || !empty($target['revoked_at'])) {
            return false;
        }

        $revoked = $this->devices->revokeByIdForUser($deviceId, $userId, 'user_revoked');
        if (!$revoked) {
            return false;
        }

        // Clear browser cookie only if the revoked device matches the caller's current browser cookie
        if ($this->isCurrentBrowserSelector($currentRawCookie, (string)$target['selector'])) {
            $this->expireCookie();
        }

        $deviceName = (string)($target['device_name'] ?? 'Trusted Browser');
        $this->logDeviceEvent(
            $userId,
            'AUTH_TRUSTED_DEVICE_REVOKED',
            "User revoked trusted device ({$deviceName}).",
            $deviceId,
            $ipAddress,
            $userAgent,
            'SUCCESS'
        );
        $this->notifier->notifyDeviceRevoked($user, $deviceName, 'Revoked in Account Security settings');

        return true;
    }

    /**
     * Revoke all active trusted devices for a user immediately.
     */
    public function revokeAllDevicesForUser(
        array $user,
        string $reason = 'user_revoked_all',
        bool $clearCurrentCookie = true,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): int {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return 0;
        }

        $count = $this->devices->revokeAllForUser($userId, $reason);
        if ($clearCurrentCookie) {
            $this->expireCookie();
        }

        $this->logDeviceEvent(
            $userId,
            'AUTH_TRUSTED_DEVICE_REVOKED_ALL',
            "Revoked all active trusted devices ({$count}) on account. Reason: {$reason}.",
            null,
            $ipAddress,
            $userAgent,
            'SUCCESS'
        );
        $this->notifier->notifyAllDevicesRevoked($user, $count, $reason);

        return $count;
    }

    /**
     * Complete a lost/compromised trusted-device recovery action after verifying identity:
     * revokes all trusted devices, enforces fresh OTP before trusting a replacement browser,
     * and notifies the account owner.
     */
    public function recoverLostDevices(
        array $user,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): int {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return 0;
        }

        $revokedCount = $this->devices->revokeAllForUser($userId, 'lost_device_recovery');
        $this->riskService->flagRecoveryCompleted($userId);
        $this->expireCookie();

        $this->logDeviceEvent(
            $userId,
            'AUTH_TRUSTED_DEVICE_RECOVERY',
            "Completed trusted-device account recovery: revoked {$revokedCount} credential(s) and required fresh OTP step-up.",
            null,
            $ipAddress,
            $userAgent,
            'SUCCESS'
        );
        $this->notifier->notifyRecoveryCompleted($user, $revokedCount, $ipAddress);

        return $revokedCount;
    }

    /**
     * List devices enriched with `is_current_device` boolean for safe display in UI.
     */
    public function listDevicesForDisplay(int $userId, ?string $currentRawCookie = null): array
    {
        $rows = $this->devices->listForUser($userId);
        foreach ($rows as &$row) {
            $row['is_current_device'] = $this->isCurrentBrowserSelector(
                $currentRawCookie,
                (string)($row['selector'] ?? '')
            );
            // Strip selector token prefix from UI payloads; UI operates exclusively via numeric row ID + CSRF
            unset($row['selector']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Check whether the raw browser cookie's selector matches the given DB selector.
     */
    public function isCurrentBrowserSelector(?string $rawCookie, string $selector): bool
    {
        $parsed = self::parseToken($rawCookie);
        if ($parsed === null || $selector === '') {
            return false;
        }

        return hash_equals($selector, $parsed['selector']);
    }

    /**
     * Emit secure trusted-device browser cookie (host-only, HttpOnly, SameSite=Lax, Secure on HTTPS).
     */
    public function emitCookie(string $rawToken, int $expiresTimestamp): void
    {
        $name = $this->getCookieName();
        $path = (string)($this->config['cookie_path'] ?? '/');
        $samesite = (string)($this->config['cookie_samesite'] ?? 'Lax');
        $isHttps = self::isHttpsRequest();

        $options = [
            'expires'  => $expiresTimestamp,
            'path'     => $path,
            'domain'   => '', // Strictly host-only
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => $samesite,
        ];

        $this->lastCookieAction = [
            'action'  => 'set',
            'name'    => $name,
            'options' => $options,
        ];

        $_COOKIE[$name] = $rawToken;

        if (!headers_sent() && (!defined('PHPUNIT_RUNNING') || !PHPUNIT_RUNNING)) {
            setcookie($name, $rawToken, $options);
        }
    }

    /**
     * Expire the browser's trusted-device cookie immediately.
     */
    public function expireCookie(): void
    {
        $name = $this->getCookieName();
        $path = (string)($this->config['cookie_path'] ?? '/');
        $samesite = (string)($this->config['cookie_samesite'] ?? 'Lax');
        $isHttps = self::isHttpsRequest();

        $options = [
            'expires'  => time() - 86400,
            'path'     => $path,
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => $samesite,
        ];

        $this->lastCookieAction = [
            'action'  => 'clear',
            'name'    => $name,
            'options' => $options,
        ];

        unset($_COOKIE[$name]);

        if (!headers_sent() && (!defined('PHPUNIT_RUNNING') || !PHPUNIT_RUNNING)) {
            setcookie($name, '', $options);
        }
    }

    /**
     * Expose the last cookie issuance/clear metadata for security unit tests.
     * Note: raw token value is intentionally NOT stored in lastCookieAction.
     */
    public function getLastCookieAction(): ?array
    {
        return $this->lastCookieAction;
    }

    /**
     * Compute the stored database hash for a secret validator.
     */
    public function hashValidator(string $validator): string
    {
        $pepper = $this->resolvePepper();
        if ($pepper !== '') {
            return hash_hmac('sha256', $validator, $pepper);
        }

        return hash('sha256', $validator);
    }

    /**
     * Parse browser and OS descriptions for human-readable UI display.
     * Never used as primary authentication proof.
     *
     * @return array{device_name:string, browser_name:string, os_name:string}
     */
    public static function parseUserAgent(?string $userAgent): array
    {
        $ua = trim((string)($userAgent ?? ''));
        if ($ua === '') {
            return [
                'browser_name' => 'Unknown Browser',
                'os_name'      => 'Unknown OS',
                'device_name'  => 'Web Browser',
            ];
        }

        $browser = 'Unknown Browser';
        if (stripos($ua, 'Edg/') !== false || stripos($ua, 'Edge/') !== false) {
            $browser = 'Microsoft Edge';
        } elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) {
            $browser = 'Opera';
        } elseif (stripos($ua, 'Brave') !== false) {
            $browser = 'Brave';
        } elseif (stripos($ua, 'Firefox/') !== false || stripos($ua, 'FxiOS/') !== false) {
            $browser = 'Mozilla Firefox';
        } elseif (stripos($ua, 'Chrome/') !== false || stripos($ua, 'CriOS/') !== false) {
            $browser = 'Google Chrome';
        } elseif (stripos($ua, 'Safari/') !== false) {
            $browser = 'Apple Safari';
        }

        $os = 'Unknown OS';
        if (stripos($ua, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (stripos($ua, 'Android') !== false) {
            $os = 'Android';
        } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false || stripos($ua, 'iPod') !== false) {
            $os = 'iOS';
        } elseif (stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS X') !== false) {
            $os = 'macOS';
        } elseif (stripos($ua, 'CrOS') !== false) {
            $os = 'ChromeOS';
        } elseif (stripos($ua, 'Linux') !== false) {
            $os = 'Linux';
        }

        $deviceName = ($browser !== 'Unknown Browser' || $os !== 'Unknown OS')
            ? "{$browser} on {$os}"
            : 'Web Browser';

        return [
            'browser_name' => $browser,
            'os_name'      => $os,
            'device_name'  => $deviceName,
        ];
    }

    public static function isHttpsRequest(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443) {
            return true;
        }
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        $fromTrustedProxy = is_string($remote)
            && $remote !== ''
            && function_exists('trusted_proxy_ranges')
            && class_exists(\App\Realtime\IpRange::class)
            && \App\Realtime\IpRange::matchesAny($remote, trusted_proxy_ranges());

        return $fromTrustedProxy
            && strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    private function resolvePepper(): string
    {
        foreach (['TRUSTED_DEVICE_PEPPER', 'OTP_PEPPER_SECRET'] as $key) {
            $val = getenv($key);
            if ($val === false || trim($val) === '') {
                $val = $_ENV[$key] ?? '';
            }
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        return '';
    }

    private function failResult(string $reason, bool $clearCookie, ?string $stepUpReason = null): array
    {
        if ($clearCookie) {
            $this->expireCookie();
        }

        return [
            'trusted'        => false,
            'require_otp'    => true,
            'reason'         => $reason,
            'device_id'      => null,
            'device_name'    => null,
            'expires_at'     => null,
            'expires_at_ts'  => null,
            'new_raw_token'  => null,
            'clear_cookie'   => $clearCookie,
            'step_up_reason' => $stepUpReason,
        ];
    }

    private function logDeviceEvent(
        int $userId,
        string $action,
        string $description,
        ?int $deviceId,
        ?string $ipAddress,
        ?string $userAgent,
        string $status
    ): void {
        try {
            $audit = AuditService::getInstance();
            $audit->record([
                'user_id'     => $userId > 0 ? $userId : null,
                'action'      => $action,
                'category'    => AuditService::CATEGORY_AUTH,
                'description' => $description,
                'entity_type' => 'trusted_devices',
                'entity_id'   => $deviceId !== null ? (string)$deviceId : (string)$userId,
                'status'      => $status,
                'ip_address'  => $ipAddress ?? client_ip(),
                'user_agent'  => $userAgent !== null ? substr($userAgent, 0, 255) : null,
            ]);
        } catch (Throwable) {
            // Never block auth on secondary audit failure; fallback to User::logActivity
            try {
                $this->users->logActivity(
                    $userId > 0 ? $userId : null,
                    $action,
                    $description,
                    'trusted_devices',
                    $deviceId !== null ? (string)$deviceId : (string)$userId,
                    null,
                    null,
                    $status
                );
            } catch (Throwable) {
                // Silent fail-safe
            }
        }
    }
}
