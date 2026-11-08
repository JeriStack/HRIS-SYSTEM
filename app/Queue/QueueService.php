<?php

/**
 * MySQL-Backed Centralized Queue Service
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Implements concurrency-safe job dispatching, atomic claiming with SKIP LOCKED,
 * exponential backoff, dead-letter failed job tracking, and abandoned reservation recovery.
 */

declare(strict_types=1);

namespace App\Queue;

use Core\AuditService;
use Core\Database;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use Throwable;

class QueueService
{
    private static ?self $instance = null;
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    public static function getInstance(?PDO $pdo = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($pdo);
        } elseif ($pdo !== null) {
            self::$instance->pdo = $pdo;
        }

        return self::$instance;
    }

    /**
     * Dispatch an asynchronous job to the MySQL queue.
     *
     * @param array<string, mixed> $payload
     */
    public function push(
        string $jobType,
        array $payload = [],
        string $queue = 'default',
        int $delaySeconds = 0,
        int $maxAttempts = 3
    ): int {
        $normalizedType = trim($jobType);

        if (!JobRegistry::isRegistered($normalizedType)) {
            throw new InvalidArgumentException(sprintf(
                "Cannot dispatch unregistered job type '%s'. Job rejected by registry policy.",
                $normalizedType
            ));
        }

        // Automatic recursive redaction of sensitive credentials (passwords, tokens, OTPs)
        $cleanPayload = AuditService::redact($payload);
        $encodedPayload = json_encode($cleanPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $delay = max(0, $delaySeconds);
        $attemptsLimit = max(1, min(20, $maxAttempts));

        $stmt = $this->pdo->prepare("
            INSERT INTO queue_jobs (
                queue,
                job_type,
                payload,
                status,
                attempts,
                max_attempts,
                available_at,
                created_at,
                updated_at
            ) VALUES (
                :queue,
                :job_type,
                :payload,
                'pending',
                0,
                :max_attempts,
                DATE_ADD(NOW(), INTERVAL :delay SECOND),
                NOW(),
                NOW()
            )
        ");

        $stmt->bindValue(':queue', $queue, PDO::PARAM_STR);
        $stmt->bindValue(':job_type', $normalizedType, PDO::PARAM_STR);
        $stmt->bindValue(':payload', $encodedPayload, PDO::PARAM_STR);
        $stmt->bindValue(':max_attempts', $attemptsLimit, PDO::PARAM_INT);
        $stmt->bindValue(':delay', $delay, PDO::PARAM_INT);
        $stmt->execute();

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Atomically claim a batch of eligible pending jobs using MySQL 8.0 `FOR UPDATE SKIP LOCKED`.
     *
     * @return list<array{
     *     id: int,
     *     queue: string,
     *     job_type: string,
     *     payload: array<string, mixed>,
     *     attempts: int,
     *     max_attempts: int
     * }>
     */
    public function claimPending(int $limit = 10, string $workerId = '', string $queue = 'default'): array
    {
        $batchLimit = max(1, min(100, $limit));
        $wasInTransaction = $this->pdo->inTransaction();

        if (!$wasInTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, queue, job_type, payload, attempts, max_attempts
                FROM queue_jobs
                WHERE queue = :queue
                  AND status = 'pending'
                  AND available_at <= NOW()
                ORDER BY id ASC
                LIMIT :lim
                FOR UPDATE SKIP LOCKED
            ");
            $stmt->bindValue(':queue', $queue, PDO::PARAM_STR);
            $stmt->bindValue(':lim', $batchLimit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                if (!$wasInTransaction) {
                    $this->pdo->commit();
                }
                return [];
            }

            $ids = array_map(fn(array $r): int => (int)$r['id'], $rows);
            $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));

            $updateStmt = $this->pdo->prepare("
                UPDATE queue_jobs
                SET status = 'reserved',
                    reserved_at = NOW(),
                    reserved_by = ?
                WHERE id IN ({$inPlaceholders})
            ");
            $updateStmt->execute(array_merge([$workerId !== '' ? $workerId : 'worker'], $ids));

            if (!$wasInTransaction) {
                $this->pdo->commit();
            }

            $claimed = [];
            foreach ($rows as $row) {
                /** @var array<string, mixed> $payload */
                $payload = is_string($row['payload'])
                    ? (json_decode($row['payload'], true) ?: [])
                    : (array)$row['payload'];

                $claimed[] = [
                    'id'           => (int)$row['id'],
                    'queue'        => (string)$row['queue'],
                    'job_type'     => (string)$row['job_type'],
                    'payload'      => $payload,
                    'attempts'     => (int)$row['attempts'],
                    'max_attempts' => (int)$row['max_attempts'],
                ];
            }

            return $claimed;
        } catch (Throwable $e) {
            if (!$wasInTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[QueueService::claimPending Error] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark a job as successfully completed.
     */
    public function markCompleted(int $jobId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE queue_jobs
            SET status = 'completed',
                completed_at = NOW(),
                reserved_by = NULL
            WHERE id = :id
        ");
        $stmt->execute(['id' => $jobId]);
    }

    /**
     * Handle job execution failure with exponential backoff or dead-letter transition.
     */
    public function markFailed(int $jobId, Throwable $e, int $delaySeconds = 0): void
    {
        $stmtSelect = $this->pdo->prepare("
            SELECT queue, job_type, payload, attempts, max_attempts
            FROM queue_jobs
            WHERE id = :id
        ");
        $stmtSelect->execute(['id' => $jobId]);
        $job = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            return;
        }

        $attempts = (int)$job['attempts'] + 1;
        $maxAttempts = (int)$job['max_attempts'];
        $errorMessage = substr($e->getMessage(), 0, 5000);

        if ($attempts >= $maxAttempts) {
            // Terminal failure -> Transition to failed_jobs dead-letter table
            $wasInTransaction = $this->pdo->inTransaction();
            if (!$wasInTransaction) {
                $this->pdo->beginTransaction();
            }

            try {
                $stmtFailed = $this->pdo->prepare("
                    INSERT INTO failed_jobs (
                        job_id, queue, job_type, payload, attempts, exception, failed_at
                    ) VALUES (
                        :job_id, :queue, :job_type, :payload, :attempts, :exception, NOW()
                    )
                ");
                $stmtFailed->execute([
                    'job_id'    => $jobId,
                    'queue'     => $job['queue'],
                    'job_type'  => $job['job_type'],
                    'payload'   => $job['payload'],
                    'attempts'  => $attempts,
                    'exception' => $errorMessage,
                ]);

                $stmtUpdate = $this->pdo->prepare("
                    UPDATE queue_jobs
                    SET status = 'failed',
                        attempts = :attempts,
                        last_error = :err,
                        reserved_by = NULL
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    'id'       => $jobId,
                    'attempts' => $attempts,
                    'err'      => $errorMessage,
                ]);

                if (!$wasInTransaction) {
                    $this->pdo->commit();
                }
            } catch (Throwable $dbEx) {
                if (!$wasInTransaction && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                error_log('[QueueService::markFailed Terminal Error] ' . $dbEx->getMessage());
            }
        } else {
            // Retryable failure -> calculate exponential backoff with jitter
            $baseDelay = $delaySeconds > 0 ? $delaySeconds : min(3600, (int)(pow(2, $attempts) * 5));
            $jitter = random_int(0, min(10, max(1, $baseDelay)));
            $retryDelay = $baseDelay + $jitter;

            $stmtRetry = $this->pdo->prepare("
                UPDATE queue_jobs
                SET status = 'pending',
                    attempts = :attempts,
                    available_at = DATE_ADD(NOW(), INTERVAL :delay SECOND),
                    reserved_at = NULL,
                    reserved_by = NULL,
                    last_error = :err
                WHERE id = :id
            ");
            $stmtRetry->execute([
                'id'       => $jobId,
                'attempts' => $attempts,
                'delay'    => $retryDelay,
                'err'      => $errorMessage,
            ]);
        }
    }

    /**
     * Recover abandoned job claims from crashed workers.
     *
     * @param int $leaseSeconds Max seconds a job can stay in 'reserved' state before recovery
     */
    public function recoverAbandonedJobs(int $leaseSeconds = 300, string $queue = 'default'): int
    {
        $lease = max(30, $leaseSeconds);

        $stmt = $this->pdo->prepare("
            UPDATE queue_jobs
            SET status = 'pending',
                reserved_at = NULL,
                reserved_by = NULL
            WHERE queue = :queue
              AND status = 'reserved'
              AND reserved_at <= DATE_SUB(NOW(), INTERVAL :lease SECOND)
        ");
        $stmt->bindValue(':queue', $queue, PDO::PARAM_STR);
        $stmt->bindValue(':lease', $lease, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Retry a dead-letter job from failed_jobs by recreating it in queue_jobs.
     */
    public function retryFailedJob(int $failedJobId): int
    {
        $stmtSelect = $this->pdo->prepare("
            SELECT id, job_id, queue, job_type, payload
            FROM failed_jobs
            WHERE id = :id
        ");
        $stmtSelect->execute(['id' => $failedJobId]);
        $failedJob = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        if (!$failedJob) {
            throw new InvalidArgumentException("Failed job #{$failedJobId} not found.");
        }

        $payload = json_decode((string)$failedJob['payload'], true) ?: [];

        $wasInTransaction = $this->pdo->inTransaction();
        if (!$wasInTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $newJobId = $this->push(
                (string)$failedJob['job_type'],
                $payload,
                (string)$failedJob['queue']
            );

            $stmtDelete = $this->pdo->prepare("DELETE FROM failed_jobs WHERE id = :id");
            $stmtDelete->execute(['id' => $failedJobId]);

            if (!$wasInTransaction) {
                $this->pdo->commit();
            }

            return $newJobId;
        } catch (Throwable $e) {
            if (!$wasInTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Retrieve real database-backed operational metrics for the queue.
     *
     * @return array<string, int>
     */
    public function getMetrics(string $queue = 'default'): array
    {
        $defaults = [
            'pending'          => 0,
            'reserved'         => 0,
            'completed'        => 0,
            'failed'           => 0,
            'total_failed'     => 0,
            'oldest_pending_s' => 0,
        ];

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) AS reserved,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
                    TIMESTAMPDIFF(SECOND, MIN(CASE WHEN status = 'pending' THEN created_at END), NOW()) AS oldest_pending_s
                FROM queue_jobs
                WHERE queue = :queue
            ");
            $stmt->execute(['queue' => $queue]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmtFailed = $this->pdo->prepare("SELECT COUNT(*) FROM failed_jobs WHERE queue = :queue");
            $stmtFailed->execute(['queue' => $queue]);
            $totalFailed = (int)$stmtFailed->fetchColumn();

            if ($row) {
                return [
                    'pending'          => (int)($row['pending'] ?? 0),
                    'reserved'         => (int)($row['reserved'] ?? 0),
                    'completed'        => (int)($row['completed'] ?? 0),
                    'failed'           => (int)($row['failed'] ?? 0),
                    'total_failed'     => $totalFailed,
                    'oldest_pending_s' => max(0, (int)($row['oldest_pending_s'] ?? 0)),
                ];
            }
        } catch (Throwable $e) {
            error_log('[QueueService::getMetrics Error] ' . $e->getMessage());
        }

        return $defaults;
    }
}
