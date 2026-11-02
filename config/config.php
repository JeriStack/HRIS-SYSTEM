<?php
/**
 * Application Configuration
 * Employee HRIS & Leave Management System
 */

$baseUrl = getenv('BASE_URL');

if (empty($baseUrl)) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $publicPath = str_replace('\\', '/', dirname($scriptName));
    $publicPath = $publicPath === '/' ? '' : rtrim($publicPath, '/');
    $baseUrl = $scheme . '://' . $host . $publicPath;
}

return [
    'app_name'                     => getenv('APP_NAME') ?: 'NEXORA HR',
    // Fail safe: an unset APP_ENV is treated as production (no error details, no API docs).
    // Set APP_ENV=development explicitly for local debugging.
    'app_env'                      => getenv('APP_ENV') ?: 'production', // 'development', 'testing' or 'production'
    'base_url'                     => $baseUrl,
    'timezone'                     => getenv('TIMEZONE') ?: 'Asia/Manila',
    'session_lifetime'             => (int)(getenv('SESSION_LIFETIME') ?: 1800), // 30 minutes inactivity timeout
    'session_absolute_lifetime'    => (int)(getenv('SESSION_ABSOLUTE_LIFETIME') ?: 43200), // 12 hours absolute timeout
    'step_up_window'               => (int)(getenv('STEP_UP_WINDOW') ?: 900), // 15 minutes step-up window
    'login_rate_limit_max_account' => (int)(getenv('LOGIN_RATE_LIMIT_MAX_ACCOUNT') ?: 5),
    'login_rate_limit_max_ip'      => (int)(getenv('LOGIN_RATE_LIMIT_MAX_IP') ?: 15),
    'login_rate_limit_window'      => (int)(getenv('LOGIN_RATE_LIMIT_WINDOW') ?: 900), // 15 minutes
    'csrf_token_name'              => '_csrf_token',
    'dsa_benchmark'                => [
        'dataset_sizes' => [10, 100, 500, 1000, 5000, 10000],
        'default_size'  => 100
    ]
];
