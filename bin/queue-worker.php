<?php

/**
 * CLI Entrypoint: MySQL Queue Worker Daemon
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Usage:
 *   php bin/queue-worker.php [options]
 *
 * Options:
 *   --queue=default      Queue name to process
 *   --once               Process available jobs once and terminate
 *   --limit=10           Maximum jobs claimed per batch
 *   --sleep=2            Seconds to sleep when queue is empty
 *   --lease=300          Seconds before considering a reserved job abandoned
 *   --max-runtime=3600   Seconds before cycling the worker process
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden: This script must only be run via CLI.\n";
    exit(1);
}

require_once __DIR__ . '/../core/bootstrap.php';

use App\Queue\QueueService;
use App\Queue\QueueWorker;

$options = getopt('', [
    'queue::',
    'once',
    'limit::',
    'sleep::',
    'lease::',
    'max-runtime::',
    'help',
]);

if (isset($options['help'])) {
    echo "Usage: php bin/queue-worker.php [options]\n\n";
    echo "Options:\n";
    echo "  --queue=default     Queue partition to consume (default: default)\n";
    echo "  --once              Execute pending jobs once and exit\n";
    echo "  --limit=10          Batch claim limit (default: 10)\n";
    echo "  --sleep=2           Sleep seconds when idle (default: 2)\n";
    echo "  --lease=300         Reservation expiration lease in seconds (default: 300)\n";
    echo "  --max-runtime=3600  Maximum worker execution duration before restart (default: 3600)\n";
    echo "  --help              Display this help message\n";
    exit(0);
}

$queueName = trim((string)($options['queue'] ?? 'default'));
if ($queueName === '') {
    $queueName = 'default';
}

try {
    $queueService = QueueService::getInstance();
} catch (\Throwable $e) {
    fwrite(STDERR, "Error: Database connection is not available: " . $e->getMessage() . "\n");
    exit(1);
}

$worker = new QueueWorker($queueService, $queueName);

$exitCode = $worker->run([
    'once'        => isset($options['once']),
    'limit'       => isset($options['limit']) ? (int)$options['limit'] : 10,
    'sleep'       => isset($options['sleep']) ? (int)$options['sleep'] : 2,
    'lease'       => isset($options['lease']) ? (int)$options['lease'] : 300,
    'max_runtime' => isset($options['max-runtime']) ? (int)$options['max-runtime'] : 3600,
]);

exit($exitCode);
