<?php

/**
 * Scheduled Task: Temporary File & Export Cleanup
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Runs daily at 02:00 to remove generated CSV reports and export artifacts older than 24 hours.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli' && !defined('SCHEDULER_IN_PROCESS')) {
    http_response_code(403);
    echo "Forbidden: CLI access only.\n";
    exit(1);
}

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$storageDir = dirname(__DIR__, 2) . '/storage/exports';
$cleanedFiles = 0;

try {
    if (is_dir($storageDir)) {
        $threshold = time() - 86400; // 24 hours
        $files = glob($storageDir . '/*.csv') ?: [];

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $threshold) {
                if (@unlink($file)) {
                    $cleanedFiles++;
                }
            }
        }
    }

    echo sprintf("[%s] [cleanup-temp-files] Removed %d expired export file(s).\n", date('Y-m-d H:i:s'), $cleanedFiles);
    return 0;
} catch (\Throwable $e) {
    error_log('[cleanup-temp-files error] ' . $e->getMessage());
    echo sprintf("[%s] [cleanup-temp-files ERROR] %s\n", date('Y-m-d H:i:s'), $e->getMessage());
    return 1;
}

