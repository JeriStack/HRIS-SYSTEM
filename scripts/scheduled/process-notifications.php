<?php

/**
 * Scheduled Task: Notification & Outbox Processing
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Runs every minute to claim and process pending outbox events.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli' && !defined('SCHEDULER_IN_PROCESS')) {
    http_response_code(403);
    echo "Forbidden: CLI access only.\n";
    exit(1);
}

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

use App\Domain\Common\EventDispatcherInterface;
use App\Infrastructure\Events\EventBootstrap;
use App\Infrastructure\Outbox\MySqlOutboxRepository;
use App\Infrastructure\Outbox\OutboxEventSerializer;

try {
    $outboxRepo = new MySqlOutboxRepository();
    $dispatcher = EventBootstrap::createConfiguredDispatcher();

    $workerId = 'scheduler:' . getmypid();
    $events = $outboxRepo->claimPending(25, $workerId);

    $processed = 0;
    foreach ($events as $eventData) {
        $id = (int)$eventData['id'];
        $eventType = (string)$eventData['event_type'];
        $payload = (array)$eventData['payload'];

        try {
            $domainEvent = OutboxEventSerializer::deserialize($eventType, $payload);
            $dispatcher->dispatch($domainEvent);
            $outboxRepo->markProcessed($id);
            $processed++;
        } catch (\Throwable $e) {
            $attempts = (int)$eventData['attempts'] + 1;
            $isTerminal = $attempts >= (int)$eventData['max_attempts'];
            $nextAttemptAt = (new DateTimeImmutable())->modify('+30 seconds');

            $outboxRepo->markFailed($id, $e->getMessage(), (string)$e->getCode(), $nextAttemptAt, $isTerminal);
        }
    }

    echo sprintf(
        "[%s] [process-notifications] Dispatched %d outbox event(s).\n",
        date('Y-m-d H:i:s'),
        $processed
    );

    // Also process a bounded batch of pending queue jobs (e.g. announcement notification distribution)
    try {
        $queueWorker = new \App\Queue\QueueWorker();
        $queueWorker->run(['once' => true, 'limit' => 25]);
    } catch (\Throwable $qe) {
        error_log('[process-notifications queue drain warning] ' . $qe->getMessage());
    }

    return 0;
} catch (\Throwable $e) {
    error_log('[process-notifications error] ' . $e->getMessage());
    echo sprintf("[%s] [process-notifications ERROR] %s\n", date('Y-m-d H:i:s'), $e->getMessage());
    return 1;
}

