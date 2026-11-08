<?php

/**
 * Scheduled Task: Daily Leave Reminders
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Runs daily at 08:00 to alert employees of leaves beginning the next day.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli' && !defined('SCHEDULER_IN_PROCESS')) {
    http_response_code(403);
    echo "Forbidden: CLI access only.\n";
    exit(1);
}

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

use App\Queue\Handlers\LeaveReminderHandler;

try {
    $handler = new LeaveReminderHandler();
    $handler->handle(['days_ahead' => 1]);

    echo sprintf("[%s] [send-leave-reminders] Leave reminders evaluated successfully.\n", date('Y-m-d H:i:s'));
    return 0;
} catch (\Throwable $e) {
    error_log('[send-leave-reminders error] ' . $e->getMessage());
    echo sprintf("[%s] [send-leave-reminders ERROR] %s\n", date('Y-m-d H:i:s'), $e->getMessage());
    return 1;
}

