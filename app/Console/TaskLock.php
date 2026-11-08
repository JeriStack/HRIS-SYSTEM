<?php

/**
 * Scheduled Task Overlap Protection (File-Based Locking)
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Prevents concurrent execution of the same scheduled task using non-blocking flock.
 */

declare(strict_types=1);

namespace App\Console;

class TaskLock
{
    private string $lockDir;
    /** @var array<string, resource> */
    private array $handles = [];

    public function __construct(?string $lockDir = null)
    {
        $this->lockDir = $lockDir ?? (dirname(__DIR__, 2) . '/storage/locks');

        if (!is_dir($this->lockDir)) {
            @mkdir($this->lockDir, 0755, true);
        }
    }

    /**
     * Attempt to acquire an exclusive lock for the task.
     *
     * @return bool True if lock was acquired, false if task is already running
     */
    public function acquire(string $taskName): bool
    {
        $lockFile = $this->getLockFilePath($taskName);
        $fp = @fopen($lockFile, 'c+');

        if ($fp === false) {
            return false;
        }

        // Non-blocking exclusive lock
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return false;
        }

        $this->handles[$taskName] = $fp;
        ftruncate($fp, 0);
        fwrite($fp, sprintf("PID: %d | Time: %s\n", getmypid(), date('c')));
        fflush($fp);

        return true;
    }

    /**
     * Release the lock for the given task.
     */
    public function release(string $taskName): void
    {
        if (isset($this->handles[$taskName])) {
            $fp = $this->handles[$taskName];
            flock($fp, LOCK_UN);
            fclose($fp);
            unset($this->handles[$taskName]);
        }
    }

    /**
     * Check if a task is currently locked (running).
     */
    public function isLocked(string $taskName): bool
    {
        $lockFile = $this->getLockFilePath($taskName);
        if (!file_exists($lockFile)) {
            return false;
        }

        $fp = @fopen($lockFile, 'r+');
        if ($fp === false) {
            return false;
        }

        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return true; // Another process holds the lock
        }

        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }

    private function getLockFilePath(string $taskName): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $taskName);
        return $this->lockDir . '/' . $safeName . '.lock';
    }

    public function __destruct()
    {
        foreach (array_keys($this->handles) as $taskName) {
            $this->release($taskName);
        }
    }
}
