<?php

/**
 * Scheduled Task Representation
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Handles cron expression parsing, execution timing, overlap prevention, and error capture.
 */

declare(strict_types=1);

namespace App\Console;

use DateTimeInterface;
use Throwable;

class ScheduledTask
{
    private string $name;
    private string $cronExpression;
    /** @var callable|string */
    private $action;
    private bool $withoutOverlapping = true;
    private ?string $description = null;

    /**
     * @param callable|string $action PHP callable or absolute path to script
     */
    public function __construct(string $name, string $cronExpression, $action, ?string $description = null)
    {
        $this->name = $name;
        $this->cronExpression = trim($cronExpression);
        $this->action = $action;
        $this->description = $description;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCronExpression(): string
    {
        return $this->cronExpression;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function withoutOverlapping(bool $enable = true): self
    {
        $this->withoutOverlapping = $enable;
        return $this;
    }

    /**
     * Determine if this task is due to run at the given timestamp.
     */
    public function isDue(DateTimeInterface $currentTime): bool
    {
        $parts = preg_split('/\s+/', $this->cronExpression);
        if ($parts === false || count($parts) !== 5) {
            return false;
        }

        [$minRule, $hourRule, $dayRule, $monthRule, $weekdayRule] = $parts;

        $min = (int)$currentTime->format('i');
        $hour = (int)$currentTime->format('G');
        $day = (int)$currentTime->format('j');
        $month = (int)$currentTime->format('n');
        $weekday = (int)$currentTime->format('w'); // 0 (Sunday) to 6 (Saturday)

        return $this->matchesCronPart($minRule, $min)
            && $this->matchesCronPart($hourRule, $hour)
            && $this->matchesCronPart($dayRule, $day)
            && $this->matchesCronPart($monthRule, $month)
            && $this->matchesCronPart($weekdayRule, $weekday);
    }

    /**
     * Execute the scheduled task.
     *
     * @return array{success: bool, skipped: bool, duration_ms: float, exit_code: int, error: ?string}
     */
    public function run(TaskLock $locker): array
    {
        if ($this->withoutOverlapping) {
            if (!$locker->acquire($this->name)) {
                return [
                    'success'     => true,
                    'skipped'     => true,
                    'duration_ms' => 0.0,
                    'exit_code'   => 0,
                    'error'       => 'Skipped: previous execution still in progress',
                ];
            }
        }

        $startTime = microtime(true);
        $exitCode = 0;
        $error = null;

        try {
            if (is_callable($this->action)) {
                $result = ($this->action)();
                if (is_int($result)) {
                    $exitCode = $result;
                }
            } elseif (is_string($this->action) && file_exists($this->action)) {
                // Execute PHP script in-process
                $scriptPath = $this->action;
                $executeClosure = function () use ($scriptPath) {
                    return require $scriptPath;
                };
                $result = $executeClosure();
                if (is_int($result)) {
                    $exitCode = $result;
                }
            } else {
                throw new \RuntimeException(sprintf("Invalid action configured for scheduled task '%s'", $this->name));
            }
        } catch (Throwable $e) {
            $exitCode = 1;
            $error = $e->getMessage();
        } finally {
            if ($this->withoutOverlapping) {
                $locker->release($this->name);
            }
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'success'     => $exitCode === 0,
            'skipped'     => false,
            'duration_ms' => $durationMs,
            'exit_code'   => $exitCode,
            'error'       => $error,
        ];
    }

    /**
     * Match a cron field against the current value.
     */
    private function matchesCronPart(string $pattern, int $value): bool
    {
        if ($pattern === '*') {
            return true;
        }

        // Handle step values (e.g. */15)
        if (str_starts_with($pattern, '*/')) {
            $step = (int)substr($pattern, 2);
            return $step > 0 && ($value % $step) === 0;
        }

        // Handle comma-separated lists (e.g. 1,15,30)
        if (str_contains($pattern, ',')) {
            $items = explode(',', $pattern);
            foreach ($items as $item) {
                if ((int)trim($item) === $value) {
                    return true;
                }
            }
            return false;
        }

        // Handle ranges (e.g. 1-5)
        if (str_contains($pattern, '-')) {
            [$start, $end] = explode('-', $pattern, 2);
            return $value >= (int)$start && $value <= (int)$end;
        }

        // Exact match
        return (int)$pattern === $value;
    }
}
