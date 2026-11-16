<?php

/**
 * Router for the PHP built-in server used by the E2E CI job.
 *
 * Serves static files from public/ directly, sends everything else to
 * public/index.php, and appends one line per PHP request to the timing log
 * (path from PHP_REQUEST_TIMING_LOG). The log is uploaded with the E2E
 * artifacts so a slow request can be identified after a failed run.
 *
 * Usage: php -S 127.0.0.1:8080 -t public/ scripts/ci/php-server-router.php
 */

$requestStart = microtime(true);
$publicDir = realpath(__DIR__ . '/../../public');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path !== '/' && is_file($publicDir . $path)) {
    return false;
}

$timingLog = getenv('PHP_REQUEST_TIMING_LOG');
if (is_string($timingLog) && $timingLog !== '') {
    register_shutdown_function(static function () use ($requestStart, $timingLog): void {
        $line = sprintf(
            "%s pid=%d %8.1fms %d %s %s\n",
            date('H:i:s'),
            getmypid(),
            (microtime(true) - $requestStart) * 1000,
            http_response_code(),
            $_SERVER['REQUEST_METHOD'] ?? '-',
            $_SERVER['REQUEST_URI'] ?? '-'
        );
        file_put_contents($timingLog, $line, FILE_APPEND | LOCK_EX);
    });
}

chdir($publicDir);
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicDir . '/index.php';
require $publicDir . '/index.php';
