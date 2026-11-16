<?php

/**
 * Scheduled Task: Announcement Scheduled Publication & Expiration Processing
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Runs every minute via App\Console\Kernel to idempotently:
 * 1. Publish due Scheduled announcements (`scheduled_at <= NOW()`).
 * 2. Expire due Published announcements (`expires_at <= NOW()`).
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli' && !defined('SCHEDULER_IN_PROCESS')) {
    http_response_code(403);
    echo "Forbidden: CLI access only.\n";
    exit(1);
}

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

use App\Application\Announcement\Adapters\AnnouncementControllerAdapter;

try {
    $tzName = (string)config('config.timezone', 'Asia/Manila');
    try {
        $tz = new DateTimeZone($tzName !== '' ? $tzName : 'Asia/Manila');
    } catch (\Throwable) {
        $tz = new DateTimeZone('Asia/Manila');
    }

    $now = new DateTimeImmutable('now', $tz);
    $adapter = new AnnouncementControllerAdapter();
    $summary = $adapter->processDueLifecycleTransitions($now, 50);

    echo sprintf(
        "[%s] [process-announcements] Published: %d, Expired: %d, Failed: %d\n",
        $now->format('Y-m-d H:i:s'),
        $summary['published'],
        $summary['expired'],
        $summary['failed']
    );

    return $summary['failed'] > 0 ? 1 : 0;
} catch (\Throwable $e) {
    error_log('[process-announcements error] ' . $e->getMessage());
    echo sprintf("[%s] [process-announcements ERROR] %s\n", date('Y-m-d H:i:s'), $e->getMessage());
    return 1;
}
