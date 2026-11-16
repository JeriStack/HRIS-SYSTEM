<?php

/**
 * Cron Scheduler & Overlap Protection Test Suite
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Verifies:
 * 1. Scheduled task registration in Kernel.
 * 2. Cron expression parsing and due evaluation across minutes, hours, days.
 * 3. TaskLock non-blocking mutual exclusion (overlap prevention).
 * 4. Task execution timing, error capture, and exit codes.
 * 5. Timezone awareness.
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Kernel;
use App\Console\ScheduledTask;
use App\Console\TaskLock;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class CronSchedulerTest extends TestCase
{
    private string $tempLockDir;

    protected function setUp(): void
    {
        $this->tempLockDir = sys_get_temp_dir() . '/hris_test_locks_' . bin2hex(random_bytes(4));
        if (!is_dir($this->tempLockDir)) {
            mkdir($this->tempLockDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up temporary locks
        if (is_dir($this->tempLockDir)) {
            $files = glob($this->tempLockDir . '/*.lock') ?: [];
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->tempLockDir);
        }
    }

    public function testKernelRegistersInitialOperationalTasks(): void
    {
        $locker = new TaskLock($this->tempLockDir);
        $kernel = new Kernel($locker);
        $tasks = $kernel->getTasks();

        $this->assertNotEmpty($tasks);

        $taskNames = array_map(fn(ScheduledTask $t) => $t->getName(), $tasks);

        $this->assertContains('cleanup-sessions', $taskNames);
        $this->assertContains('process-notifications', $taskNames);
        $this->assertContains('process-announcements', $taskNames);
        $this->assertContains('send-leave-reminders', $taskNames);
        $this->assertContains('cleanup-temp-files', $taskNames);
    }

    public function testCronExpressionEvaluation(): void
    {
        $taskEveryMin = new ScheduledTask('every_min', '* * * * *', fn() => 0);
        $taskEvery15 = new ScheduledTask('every_15', '*/15 * * * *', fn() => 0);
        $taskDaily8am = new ScheduledTask('daily_8am', '0 8 * * *', fn() => 0);

        $tz = new DateTimeZone('Asia/Manila');

        // Test at 08:00
        $time0800 = new DateTimeImmutable('2026-11-08 08:00:00', $tz);
        $this->assertTrue($taskEveryMin->isDue($time0800));
        $this->assertTrue($taskEvery15->isDue($time0800));
        $this->assertTrue($taskDaily8am->isDue($time0800));

        // Test at 08:14
        $time0814 = new DateTimeImmutable('2026-11-08 08:14:00', $tz);
        $this->assertTrue($taskEveryMin->isDue($time0814));
        $this->assertFalse($taskEvery15->isDue($time0814), '08:14 is not a multiple of 15');
        $this->assertFalse($taskDaily8am->isDue($time0814));

        // Test at 08:15
        $time0815 = new DateTimeImmutable('2026-11-08 08:15:00', $tz);
        $this->assertTrue($taskEvery15->isDue($time0815), '08:15 is a multiple of 15');
    }

    public function testTaskLockPreventsOverlappingExecution(): void
    {
        $locker1 = new TaskLock($this->tempLockDir);
        $locker2 = new TaskLock($this->tempLockDir);

        $taskName = 'test_long_running_job';

        $this->assertTrue($locker1->acquire($taskName), 'First worker should acquire the lock');
        $this->assertTrue($locker1->isLocked($taskName), 'Task should be reported as locked');

        // Second worker tries to acquire simultaneously
        $this->assertFalse($locker2->acquire($taskName), 'Second worker must be prevented from acquiring active lock');

        // Release first lock
        $locker1->release($taskName);

        // Second worker can now acquire
        $this->assertTrue($locker2->acquire($taskName), 'Second worker can acquire after release');
        $locker2->release($taskName);
    }

    public function testTaskRunCapturesDurationAndHandlesExceptions(): void
    {
        $locker = new TaskLock($this->tempLockDir);

        // Successful task
        $successfulTask = new ScheduledTask('success_task', '* * * * *', function () {
            usleep(5000); // 5ms
            return 0;
        });

        $resSuccess = $successfulTask->run($locker);
        $this->assertTrue($resSuccess['success']);
        $this->assertFalse($resSuccess['skipped']);
        $this->assertSame(0, $resSuccess['exit_code']);
        $this->assertGreaterThan(0.0, $resSuccess['duration_ms']);
        $this->assertNull($resSuccess['error']);

        // Failing task throwing exception
        $failingTask = new ScheduledTask('failing_task', '* * * * *', function () {
            throw new \RuntimeException('Simulated scheduled task crash');
        });

        $resFail = $failingTask->run($locker);
        $this->assertFalse($resFail['success']);
        $this->assertSame(1, $resFail['exit_code']);
        $this->assertSame('Simulated scheduled task crash', $resFail['error']);
    }

    public function testScheduledTaskSkipsWhenLockIsHeld(): void
    {
        $locker = new TaskLock($this->tempLockDir);
        $task = new ScheduledTask('overlap_task', '* * * * *', fn() => 0);

        // Pre-acquire lock externally
        $locker->acquire('overlap_task');

        $res = $task->run($locker);
        $this->assertTrue($res['skipped'], 'Task must be skipped when lock is already held');
        $this->assertStringContainsString('still in progress', $res['error'] ?? '');

        $locker->release('overlap_task');
    }

    public function testKernelRunDueExecutesOnlyDueTasks(): void
    {
        $locker = new TaskLock($this->tempLockDir);
        $kernel = new Kernel($locker);

        $executedTasks = [];

        $kernel->schedule('every_minute_probe', '* * * * *', function () use (&$executedTasks) {
            $executedTasks[] = 'every_minute_probe';
            return 0;
        });

        $kernel->schedule('midnight_probe', '0 0 * * *', function () use (&$executedTasks) {
            $executedTasks[] = 'midnight_probe';
            return 0;
        });

        // Execute at 14:30
        $tz = new DateTimeZone('Asia/Manila');
        $time1430 = new DateTimeImmutable('2026-11-08 14:30:00', $tz);

        $results = $kernel->runDueTasks($time1430);

        $this->assertContains('every_minute_probe', $executedTasks);
        $this->assertNotContains('midnight_probe', $executedTasks, 'Midnight probe must not execute at 14:30');
    }

    public function testCronExpressionSyntaxRangesAndSteps(): void
    {
        $taskRange = new ScheduledTask('range_task', '1-5 * * * *', fn() => 0);
        $taskSteps = new ScheduledTask('step_task', '*/10 * * * *', fn() => 0);
        $taskList = new ScheduledTask('list_task', '0,30 * * * *', fn() => 0);

        $tz = new DateTimeZone('Asia/Manila');

        // Minute 3
        $timeMin3 = new DateTimeImmutable('2026-11-08 10:03:00', $tz);
        $this->assertTrue($taskRange->isDue($timeMin3));
        $this->assertFalse($taskSteps->isDue($timeMin3));
        $this->assertFalse($taskList->isDue($timeMin3));

        // Minute 20
        $timeMin20 = new DateTimeImmutable('2026-11-08 10:20:00', $tz);
        $this->assertFalse($taskRange->isDue($timeMin20));
        $this->assertTrue($taskSteps->isDue($timeMin20));
        $this->assertFalse($taskList->isDue($timeMin20));

        // Minute 30
        $timeMin30 = new DateTimeImmutable('2026-11-08 10:30:00', $tz);
        $this->assertFalse($taskRange->isDue($timeMin30));
        $this->assertTrue($taskSteps->isDue($timeMin30));
        $this->assertTrue($taskList->isDue($timeMin30));
    }

    public function testScheduledTaskMetadata(): void
    {
        $task = new ScheduledTask('test_meta', '0 0 * * *', fn() => 0, 'Runs at midnight');

        $this->assertSame('test_meta', $task->getName());
        $this->assertSame('0 0 * * *', $task->getCronExpression());
        $this->assertSame('Runs at midnight', $task->getDescription());
    }
}
