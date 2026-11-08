<?php

/**
 * Long-Lived MySQL Queue Worker Engine
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Runs the asynchronous job processing loop with concurrency-safe claims,
 * graceful signal handling, dead-letter recording, and abandoned job recovery.
 */

declare(strict_types=1);

namespace App\Queue;

use Throwable;

class QueueWorker
{
    private QueueService $queueService;
    private string $queue;
    private string $workerId;
    private bool $keepRunning = true;
    private int $processedCount = 0;
    private int $failedCount = 0;

    public function __construct(?QueueService $queueService = null, string $queue = 'default')
    {
        $this->queueService = $queueService ?? QueueService::getInstance();
        $this->queue = $queue;
        $this->workerId = sprintf('%s:%d:%s', gethostname() ?: 'worker', getmypid(), bin2hex(random_bytes(4)));
    }

    public function getWorkerId(): string
    {
        return $this->workerId;
    }

    /**
     * Start the worker execution loop.
     *
     * @param array{
     *     once?: bool,
     *     limit?: int,
     *     sleep?: int,
     *     lease?: int,
     *     max_runtime?: int
     * } $options
     */
    public function run(array $options = []): int
    {
        $runOnce = (bool)($options['once'] ?? false);
        $limit = max(1, min(100, (int)($options['limit'] ?? 10)));
        $sleepInterval = max(1, (int)($options['sleep'] ?? 2));
        $leaseSeconds = max(30, (int)($options['lease'] ?? 300));
        $maxRuntime = max(10, (int)($options['max_runtime'] ?? 3600));

        $startTime = time();
        $lastReaperTime = 0;

        $this->registerSignalHandlers();

        $this->log(sprintf("Starting Queue Worker on queue '%s' (Worker ID: %s)...", $this->queue, $this->workerId));

        while ($this->keepRunning) {
            // 1. Periodically recover abandoned claims from crashed workers (every 60s)
            $now = time();
            if (($now - $lastReaperTime) >= 60) {
                try {
                    $recovered = $this->queueService->recoverAbandonedJobs($leaseSeconds, $this->queue);
                    if ($recovered > 0) {
                        $this->log(sprintf("Recovered %d abandoned job(s) from expired lease.", $recovered));
                    }
                } catch (Throwable $e) {
                    $this->log('[WARN] Abandoned job recovery failure: ' . $e->getMessage());
                }
                $lastReaperTime = $now;
            }

            // 2. Atomically claim batch of pending jobs
            try {
                $jobs = $this->queueService->claimPending($limit, $this->workerId, $this->queue);
            } catch (Throwable $e) {
                $this->log('[ERROR] Failed to claim jobs: ' . $e->getMessage());
                sleep($sleepInterval);
                continue;
            }

            if (empty($jobs)) {
                if ($runOnce) {
                    $this->log('Queue is empty. Single execution completed.');
                    break;
                }

                // Check lifecycle safeguard to cycle worker process periodically
                if (($now - $startTime) >= $maxRuntime) {
                    $this->log(sprintf("Max runtime reached (%ds). Exiting for lifecycle process recycling.", $maxRuntime));
                    break;
                }

                sleep($sleepInterval);
                continue;
            }

            $this->log(sprintf("Claimed %d pending job(s) for processing.", count($jobs)));

            // 3. Process each job outside the claim transaction
            foreach ($jobs as $jobData) {
                if (!$this->keepRunning) {
                    break;
                }

                $this->processJob($jobData);
            }

            if ($runOnce) {
                break;
            }
        }

        $this->log(sprintf(
            "Worker stopped. Summary -> Processed: %d, Failures: %d",
            $this->processedCount,
            $this->failedCount
        ));

        return $this->failedCount > 0 ? 1 : 0;
    }

    /**
     * Process an individual claimed job safely with timing and error handling.
     *
     * @param array{
     *     id: int,
     *     queue: string,
     *     job_type: string,
     *     payload: array<string, mixed>,
     *     attempts: int,
     *     max_attempts: int
     * } $jobData
     */
    public function processJob(array $jobData): bool
    {
        $id = $jobData['id'];
        $jobType = $jobData['job_type'];
        $payload = $jobData['payload'];
        $attempts = $jobData['attempts'] + 1;
        $maxAttempts = $jobData['max_attempts'];

        $jobStart = microtime(true);

        try {
            // Resolve whitelisted handler
            $handler = JobRegistry::resolve($jobType);

            // Execute handler
            $handler->handle($payload);

            // Mark completed
            $this->queueService->markCompleted($id);
            $this->processedCount++;

            $durationMs = round((microtime(true) - $jobStart) * 1000, 2);
            $this->log(sprintf(
                "[SUCCESS] Job #%d (%s) attempt %d/%d processed in %0.2fms",
                $id,
                $jobType,
                $attempts,
                $maxAttempts,
                $durationMs
            ));

            return true;
        } catch (Throwable $e) {
            $this->failedCount++;
            $this->queueService->markFailed($id, $e);

            $this->log(sprintf(
                "[FAILURE] Job #%d (%s) attempt %d/%d failed: %s",
                $id,
                $jobType,
                $attempts,
                $maxAttempts,
                $e->getMessage()
            ));

            return false;
        }
    }

    /**
     * Register POSIX signal handlers for graceful container termination.
     */
    private function registerSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            $handler = function (int $signo): void {
                $this->log(sprintf("Signal %d received. Initiating graceful worker shutdown...", $signo));
                $this->keepRunning = false;
            };

            pcntl_signal(SIGTERM, $handler);
            pcntl_signal(SIGINT, $handler);
        }
    }

    private function log(string $message): void
    {
        echo sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $this->workerId, $message);
    }
}
