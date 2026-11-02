<?php

/**
 * CLI Command: Transactional Outbox Background Worker
 * HRIS-SYSTEM — Reliability and Production Readiness Upgrade (Phase 1)
 *
 * Usage:
 *   php bin/outbox-worker.php [options]
 *
 * Options:
 *   --once             Process currently pending events once and terminate
 *   --limit=25         Maximum number of events claimed per batch
 *   --interval=2       Seconds to sleep between idle polling iterations
 *   --lease=300        Lease duration in seconds before recovering abandoned claims
 *   --max-runtime=3600 Graceful worker exit threshold in seconds for process cycling
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden: This script must only be run via CLI.\n";
    exit(1);
}

require_once __DIR__ . '/../core/bootstrap.php';

use App\Domain\Common\EventDispatcherInterface;
use App\Domain\Common\OutboxRepositoryInterface;
use App\Infrastructure\Events\EventBootstrap;
use App\Infrastructure\Outbox\MySqlOutboxRepository;
use App\Infrastructure\Outbox\OutboxEventSerializer;

// Parse CLI Options
$options = getopt('', ['once', 'limit::', 'interval::', 'lease::', 'max-runtime::']);
$runOnce = isset($options['once']);
$limit = max(1, min(100, (int)($options['limit'] ?? 25)));
$pollInterval = max(1, (int)($options['interval'] ?? 2));
$leaseSeconds = max(30, (int)($options['lease'] ?? 300));
$maxRuntime = max(10, (int)($options['max-runtime'] ?? 3600));

$workerId = sprintf('%s:%d:%s', gethostname() ?: 'worker', getmypid(), bin2hex(random_bytes(4)));
$startTime = time();

echo "====================================================================\n";
echo " HRIS-SYSTEM — Transactional Outbox Worker\n";
echo " Worker ID:    {$workerId}\n";
echo " Mode:         " . ($runOnce ? "Single-Execution (--once)" : "Daemon Mode") . "\n";
echo " Batch Limit:  {$limit}\n";
echo " Started At:   " . date('Y-m-d H:i:s') . "\n";
echo "====================================================================\n\n";

$outboxRepository = new MySqlOutboxRepository();
$eventDispatcher = EventBootstrap::createConfiguredDispatcher();

$processedTotal = 0;
$failedTotal = 0;

$keepRunning = true;

// Graceful signal handling on POSIX systems
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    $signalHandler = function (int $signo) use (&$keepRunning, $workerId) {
        echo sprintf("[%s] [%s] Signal %d received. Initiating graceful shutdown...\n", date('Y-m-d H:i:s'), $workerId, $signo);
        $keepRunning = false;
    };
    pcntl_signal(SIGTERM, $signalHandler);
    pcntl_signal(SIGINT, $signalHandler);
}

while ($keepRunning) {
    // 1. Recover abandoned claims from crashed workers
    try {
        $recovered = $outboxRepository->recoverAbandonedClaims($leaseSeconds);
        if ($recovered > 0) {
            echo sprintf(
                "[%s] [%s] Recovered %d abandoned event claim(s) from expired leases.\n",
                date('Y-m-d H:i:s'),
                $workerId,
                $recovered
            );
        }
    } catch (Throwable $e) {
        echo sprintf(
            "[%s] [%s] [WARN] Reaper error recovering claims: %s\n",
            date('Y-m-d H:i:s'),
            $workerId,
            $e->getMessage()
        );
    }

    // 2. Atomically claim batch of pending events using FOR UPDATE SKIP LOCKED
    try {
        $events = $outboxRepository->claimPending($limit, $workerId);
    } catch (Throwable $e) {
        echo sprintf(
            "[%s] [%s] [ERROR] Database claim failure: %s\n",
            date('Y-m-d H:i:s'),
            $workerId,
            $e->getMessage()
        );
        sleep($pollInterval);
        continue;
    }

    if (empty($events)) {
        if ($runOnce) {
            echo sprintf("[%s] [%s] Outbox empty. Single run complete.\n", date('Y-m-d H:i:s'), $workerId);
            break;
        }

        // Memory & lifecycle safeguard
        if ((time() - $startTime) >= $maxRuntime) {
            echo sprintf("[%s] [%s] Max runtime reached (%ds). Exiting for process cycle.\n", date('Y-m-d H:i:s'), $workerId, $maxRuntime);
            break;
        }

        sleep($pollInterval);
        continue;
    }

    echo sprintf("[%s] [%s] Claimed %d pending outbox event(s).\n", date('Y-m-d H:i:s'), $workerId, count($events));

    // 3. Process claimed events outside database transaction
    foreach ($events as $eventData) {
        $id = (int)$eventData['id'];
        $eventId = (string)$eventData['event_id'];
        $eventType = (string)$eventData['event_type'];
        $attempts = (int)$eventData['attempts'];
        $maxAttempts = (int)$eventData['max_attempts'];
        $payload = (array)$eventData['payload'];

        $eventStart = microtime(true);

        try {
            // Reconstitute domain event contract
            $domainEvent = OutboxEventSerializer::deserialize($eventType, $payload);

            // Dispatch through configured domain event dispatcher
            $eventDispatcher->dispatch($domainEvent);

            // Mark event successfully processed
            $outboxRepository->markProcessed($id);
            $processedTotal++;

            $durationMs = round((microtime(true) - $eventStart) * 1000, 2);
            echo sprintf(
                "[%s] [%s] [SUCCESS] Event #%d (%s) processed in %0.2fms (UUID: %s)\n",
                date('Y-m-d H:i:s'),
                $workerId,
                $id,
                basename(str_replace('\\', '/', $eventType)),
                $durationMs,
                $eventId
            );
        } catch (Throwable $e) {
            $failedTotal++;
            $newAttempts = $attempts + 1;
            $isTerminal = $newAttempts >= $maxAttempts;

            // Bounded exponential backoff with randomized jitter
            $baseSeconds = 2;
            $exponentialDelay = min(3600, (int)(pow(2, $newAttempts) * $baseSeconds));
            $jitter = random_int(0, min(10, max(1, $exponentialDelay)));
            $retryDelaySeconds = $exponentialDelay + $jitter;

            $nextAttemptAt = (new DateTimeImmutable())->modify("+{$retryDelaySeconds} seconds");

            $outboxRepository->markFailed(
                $id,
                $e->getMessage(),
                (string)$e->getCode(),
                $nextAttemptAt,
                $isTerminal
            );

            echo sprintf(
                "[%s] [%s] [FAILURE] Event #%d (%s) attempt %d/%d failed: %s%s\n",
                date('Y-m-d H:i:s'),
                $workerId,
                $id,
                basename(str_replace('\\', '/', $eventType)),
                $newAttempts,
                $maxAttempts,
                $e->getMessage(),
                $isTerminal ? ' -> Marked FAILED (Terminal)' : " -> Rescheduled in {$retryDelaySeconds}s"
            );
        }
    }

    if ($runOnce) {
        break;
    }
}

echo sprintf(
    "\nWorker %s finished. Total Processed: %d, Total Failures Handled: %d\n",
    $workerId,
    $processedTotal,
    $failedTotal
);
exit(0);
