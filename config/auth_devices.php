<?php

/**
 * Smart OTP & Trusted Device Management Configuration
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Keeps "Remember This Device" (post-password OTP bypass lifetime) strictly
 * separate from ordinary PHP login-session timeouts configured in config/config.php.
 */

$parsePositiveInt = static function (string $envKey, int $default, int $min = 1, int $max = 90): int {
    $raw = getenv($envKey);
    if ($raw === false || trim($raw) === '' || !is_numeric($raw)) {
        return $default;
    }

    $value = (int)$raw;
    return max($min, min($max, $value));
};

$basePath = parse_url((string)(getenv('BASE_URL') ?: ''), PHP_URL_PATH);
$cookiePath = '/';
if (is_string($basePath) && trim($basePath) !== '' && trim($basePath) !== '/') {
    $cookiePath = '/' . trim($basePath, '/') . '/';
}

return [
    // Distinct cookie exclusively for post-password OTP bypass recognition.
    // Never merged with PHPSESSID or ordinary session persistence.
    'cookie_name' => (string)(getenv('TRUSTED_DEVICE_COOKIE_NAME') ?: 'nexora_td'),
    'cookie_path' => $cookiePath,
    'cookie_samesite' => 'Lax',

    // Fixed trust expiration policies per application role (never indefinite)
    'max_trust_days' => 90,
    'trust_days_by_role' => [
        'employee' => $parsePositiveInt('TRUSTED_DEVICE_DAYS_EMPLOYEE', 30, 1, 90),
        'hr'       => $parsePositiveInt('TRUSTED_DEVICE_DAYS_HR', 7, 1, 90),
        'admin'    => $parsePositiveInt('TRUSTED_DEVICE_DAYS_ADMIN', 7, 1, 90),
    ],

    // Bounds active remembered browsers per account to avoid unbounded growth
    'max_devices_per_user' => $parsePositiveInt('TRUSTED_DEVICE_MAX_PER_USER', 10, 1, 50),

    // Fixed expiration policy: never extend expiry silently on login rotation
    'fixed_expiration' => true,

    // Single-use validator rotation on every successful trusted-device login
    'rotate_on_use' => true,

    // Retention window (days) before expired/revoked device history is purged
    'revoked_retention_days' => $parsePositiveInt('TRUSTED_DEVICE_RETENTION_DAYS', 90, 7, 365),

    // Apply multi-signal risk checks more strictly for privileged HR/Admin accounts
    'stricter_hr_risk_checks' => getenv('TRUSTED_DEVICE_STRICT_HR_RISK') !== '0',

    // Policy note shared across security settings views and documentation
    'logout_revokes_trusted_device' => false,
    'session_persistence_mode'      => 'session_cookie_only',
];
