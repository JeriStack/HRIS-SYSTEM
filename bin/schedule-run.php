<?php

/**
 * CLI Entrypoint: Scheduled Tasks Runner (Cron Dispatcher)
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Invoked every minute by Linux cron (/etc/cron.d/hris-scheduler).
 * Evaluates all tasks registered in App\Console\Kernel and executes due tasks
 * with non-blocking flock overlap protection.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden: This script must only be run via CLI.\n";
    exit(1);
}

require_once __DIR__ . '/../core/bootstrap.php';

use App\Console\Kernel;

$kernel = new Kernel();
$summary = $kernel->runDueTasks();

if ($summary['total_due'] > 0) {
    echo sprintf(
        "[%s] [schedule-run] Due: %d | Executed: %d | Skipped: %d | Failed: %d\n",
        date('Y-m-d H:i:s'),
        $summary['total_due'],
        $summary['executed'],
        $summary['skipped'],
        $summary['failed']
    );
}

exit($summary['failed'] > 0 ? 1 : 0);
