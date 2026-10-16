<?php
/**
 * Application Bootstrap & Autoloader
 * Employee HRIS & Leave Management System
 */

// 1. PSR-4 Compliant Class Autoloader
spl_autoload_register(function (string $class) {
    // Prefix mappings
    $prefixes = [
        'Core\\'                => __DIR__ . '/',
        'App\\Controllers\\'    => __DIR__ . '/../app/controllers/',
        'App\\Models\\'         => __DIR__ . '/../app/models/',
        'App\\Services\\'       => __DIR__ . '/../app/services/',
        'App\\Dsa\\Algorithms\\' => __DIR__ . '/../app/dsa/algorithms/',
        'App\\Dsa\\Structures\\' => __DIR__ . '/../app/dsa/structures/',
        'App\\Dsa\\'            => __DIR__ . '/../app/dsa/',
        'App\\'                 => __DIR__ . '/../app/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }

    // Non-namespaced fallback
    $fallbackDirs = [
        __DIR__ . '/',
        __DIR__ . '/../app/controllers/',
        __DIR__ . '/../app/models/',
        __DIR__ . '/../app/services/',
    ];

    foreach ($fallbackDirs as $dir) {
        $file = $dir . $class . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// 1.5 Load Environment Variables (.env) if present
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$name, $val] = explode('=', $line, 2);
            $name = trim($name);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if (getenv($name) === false && !array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $val;
                putenv("{$name}={$val}");
            }
        }
    }
}

// 2. Load Core Functions Helper
require_once __DIR__ . '/functions.php';

// 3. Set Application Timezone
$timezone = config('config.timezone', 'Asia/Manila');
date_default_timezone_set($timezone);

// 4. Configure Development vs. Production Error Reporting
$environment = config('config.app_env', 'development');
if ($environment === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('log_errors', '1');
}

// 5. Initialize Secure Session Lifecycle
\Core\Session::start();

