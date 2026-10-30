<?php

/**
 * MySQL Outbox Repository Implementation
 * HRIS-SYSTEM — Infrastructure Layer (Phase 1: Transactional Outbox)
 *
 * Implements concurrency-safe, lock-free batch claiming using MySQL 8.0 `FOR UPDATE SKIP LOCKED`.
 */

declare(strict_types=1);

namespace App\Infrastructure\Outbox;

use App\Domain\Common\DomainEventInterface;
use App\Domain\Common\OutboxRepositoryInterface;
use Core\Database;
use DateTimeImmutable;
use PDO;
use Throwable;

class MySqlOutboxRepository implements OutboxRepositoryInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    public function record(DomainEventInterface $event, string $aggregateType, string $aggregateId): void
    {
        $eventId = $this->generateUuidV4();
        $eventType = get_class($event);
        $payload = json_encode(OutboxEventSerializer::serialize($event), JSON_THROW_ON_ERROR);

        $stmt = $this->pdo->prepare("
            INSERT INTO outbox_events (
                event_id,
                event_type,
                aggregate_type,
                aggregate_id,
                payload,
                status,
                attempts,
                max_attempts,
                created_at,
                next_attempt_at
            ) VALUES (
                :event_id,
                :event_type,
                :aggregate_type,
                :aggregate_id,
                :payload,
                'PENDING',
                0,
                5,
                NOW(6),
                NOW(6)
            )
        ");

        $stmt->execute([
            'event_id'       => $eventId,
            'event_type'     => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'payload'        => $payload,
        ]);
    }

    /**
     * Atomically claim pending outbox events ready for processing using SKIP LOCKED.
     * Multiple workers can call this simultaneously without blocking each other.
     *
     * @return list<array{
     *     id: int,
     *     event_id: string,
     *     event_type: string,
     *     aggregate_type: string,
     *     aggregate_id: string,
     *     payload: array<string, mixed>,
     *     attempts: int,
     *     max_attempts: int
     * }>
     */
    public function claimPending(int $limit, string $claimedBy): array
    {
        if ($limit <= 0) {
            return [];
        }

        $wasInTransaction = $this->pdo->inTransaction();

        if (!$wasInTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $stmtSelect = $this->pdo->prepare("
                SELECT id, event_id, event_type, aggregate_type, aggregate_id, payload, attempts, max_attempts
                FROM outbox_events
                WHERE status = 'PENDING'
                  AND (next_attempt_at IS NULL OR next_attempt_at <= NOW(6))
                ORDER BY id ASC
                LIMIT :lim
                FOR UPDATE SKIP LOCKED
            ");
            $stmtSelect->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmtSelect->execute();

            $rows = $stmtSelect->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                if (!$wasInTransaction) {
                    $this->pdo->commit();
                }
                return [];
            }

            $ids = array_map(fn(array $row): int => (int)$row['id'], $rows);
            $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));

            $stmtUpdate = $this->pdo->prepare("
                UPDATE outbox_events
                SET status = 'PROCESSING',
                    claimed_at = NOW(6),
                    claimed_by = ?
                WHERE id IN ({$inPlaceholders})
            ");

            $stmtUpdate->execute(array_merge([$claimedBy], $ids));

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
                    'id'             => (int)$row['id'],
                    'event_id'       => (string)$row['event_id'],
                    'event_type'     => (string)$row['event_type'],
                    'aggregate_type' => (string)$row['aggregate_type'],
                    'aggregate_id'   => (string)$row['aggregate_id'],
                    'payload'        => $payload,
                    'attempts'       => (int)$row['attempts'],
                    'max_attempts'   => (int)$row['max_attempts'],
                ];
            }

            return $claimed;
        } catch (Throwable $e) {
            if (!$wasInTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("[MySqlOutboxRepository::claimPending Error] " . $e->getMessage());
            return [];
        }
    }

    public function markProcessed(int $id): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE outbox_events
            SET status = 'PROCESSED',
                processed_at = NOW(6),
                claimed_by = NULL
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
    }

    public function markFailed(
        int $id,
        string $errorMessage,
        ?string $errorCode,
        ?DateTimeImmutable $nextAttemptAt,
        bool $isTerminal
    ): void {
        $cleanError = substr($errorMessage, 0, 2000);
        $cleanCode = $errorCode !== null ? substr($errorCode, 0, 100) : null;

        if ($isTerminal) {
            $stmt = $this->pdo->prepare("
                UPDATE outbox_events
                SET status = 'FAILED',
                    attempts = attempts + 1,
                    last_error = :err,
                    last_error_code = :code,
                    claimed_by = NULL
                WHERE id = :id
            ");
            $stmt->execute([
                'id'   => $id,
                'err'  => $cleanError,
                'code' => $cleanCode,
            ]);
        } else {
            $nextStr = $nextAttemptAt !== null
                ? $nextAttemptAt->format('Y-m-d H:i:s.u')
                : date('Y-m-d H:i:s.u', time() + 30);

            $stmt = $this->pdo->prepare("
                UPDATE outbox_events
                SET status = 'PENDING',
                    attempts = attempts + 1,
                    next_attempt_at = :next,
                    last_error = :err,
                    last_error_code = :code,
                    claimed_by = NULL
                WHERE id = :id
            ");
            $stmt->execute([
                'id'   => $id,
                'next' => $nextStr,
                'err'  => $cleanError,
                'code' => $cleanCode,
            ]);
        }
    }

    public function recoverAbandonedClaims(int $leaseSeconds = 300): int
    {
        $lease = max(30, $leaseSeconds);

        $stmt = $this->pdo->prepare("
            UPDATE outbox_events
            SET status = 'PENDING',
                attempts = attempts + 1,
                claimed_by = NULL,
                next_attempt_at = NOW(6),
                last_error = 'Claim lease expired; recovered by worker reaper'
            WHERE status = 'PROCESSING'
              AND claimed_at < DATE_SUB(NOW(6), INTERVAL :lease SECOND)
        ");
        $stmt->bindValue(':lease', $lease, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    private function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // Version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // RFC 4122 variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
