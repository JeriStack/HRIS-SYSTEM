<?php

/**
 * Scheduled Tasks Console Kernel
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Orchestrates registered scheduled commands with cron evaluation and overlap protection.
 */

declare(strict_types=1);

namespace App\Console;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

class Kernel
{
    private TaskLock $locker;
    /** @var list<ScheduledTask> */
    private array $tasks = [];

    public function __construct(?TaskLock $locker = null)
    {
        $this->locker = $locker ?? new TaskLock();
        $this->registerSchedule();
    }

    /**
     * Define the application's scheduled task schedule.
     */
    protected function registerSchedule(): void
    {
        $basePath = dirname(__DIR__, 2);

        // 1. Expired session & OTP cleanup: every 15 minutes
        $this->schedule(
            'cleanup-sessions',
            '*/15 * * * *',
            $basePath . '/scripts/scheduled/cleanup-sessions.php',
            'Purges expired session artifacts, stale realtime tickets, and expired OTP records'
        );

        // 2. Notification & outbox processing: every minute
        $this->schedule(
            'process-notifications',
            '* * * * *',
            $basePath . '/scripts/scheduled/process-notifications.php',
            'Claims and dispatches pending transactional outbox events'
        );

        // 3. Upcoming leave reminders: daily at 08:00
        $this->schedule(
            'send-leave-reminders',
            '0 8 * * *',
            $basePath . '/scripts/scheduled/send-leave-reminders.php',
            'Sends daily reminders for approved leave requests commencing tomorrow'
        );

        // 4. Temporary file & report cleanup: daily at 02:00
        $this->schedule(
            'cleanup-temp-files',
            '0 2 * * *',
            $basePath . '/scripts/scheduled/cleanup-temp-files.php',
            'Deletes generated CSV report exports older than 24 hours'
        );
    }

    /**
     * Register a scheduled task.
     *
     * @param callable|string $action
     */
    public function schedule(string $name, string $cronExpression, $action, ?string $description = null): ScheduledTask
    {
        $task = new ScheduledTask($name, $cronExpression, $action, $description);
        $this->tasks[] = $task;
        return $task;
    }

    /**
     * Get all registered scheduled tasks.
     *
     * @return list<ScheduledTask>
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /**
     * Run all tasks due at the given time (defaults to current application timezone).
     *
     * @return array{total_due: int, executed: int, skipped: int, failed: int, results: array<string, array{success: bool, skipped: bool, duration_ms: float, exit_code: int, error: ?string}>}
     */
    public function runDueTasks(?DateTimeInterface $now = null): array
    {
        if ($now === null) {
            $tzName = (string)config('config.timezone', 'Asia/Manila');
            try {
                $tz = new DateTimeZone($tzName !== '' ? $tzName : 'Asia/Manila');
            } catch (\Throwable) {
                $tz = new DateTimeZone('Asia/Manila');
            }
            $now = new DateTimeImmutable('now', $tz);
        }

        $executed = 0;
        $skipped = 0;
        $failed = 0;
        $results = [];

        if (!defined('SCHEDULER_IN_PROCESS')) {
            define('SCHEDULER_IN_PROCESS', true);
        }

        foreach ($this->tasks as $task) {
            if ($task->isDue($now)) {
                $res = $task->run($this->locker);
                $results[$task->getName()] = $res;

                if ($res['skipped']) {
                    $skipped++;
                } elseif ($res['success']) {
                    $executed++;
                } else {
                    $failed++;
                }
            }
        }

        return [
            'total_due' => count($results),
            'executed'  => $executed,
            'skipped'   => $skipped,
            'failed'    => $failed,
            'results'   => $results,
        ];
    }
}
