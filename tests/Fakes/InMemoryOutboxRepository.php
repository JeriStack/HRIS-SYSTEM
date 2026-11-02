<?php

/**
 * In-Memory Outbox Repository Test Double
 * HRIS-SYSTEM — Test Suite Support
 */

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Common\DomainEventInterface;
use App\Domain\Common\OutboxRepositoryInterface;
use App\Infrastructure\Outbox\OutboxEventSerializer;
use DateTimeImmutable;

class InMemoryOutboxRepository implements OutboxRepositoryInterface
{
    /** @var list<array{
     *     id: int,
     *     event_id: string,
     *     event_type: string,
     *     aggregate_type: string,
     *     aggregate_id: string,
     *     payload: array<string, mixed>,
     *     status: string,
     *     attempts: int,
     *     max_attempts: int,
     *     claimed_at: ?DateTimeImmutable,
     *     claimed_by: ?string,
     *     next_attempt_at: ?DateTimeImmutable,
     *     processed_at: ?DateTimeImmutable,
     *     last_error: ?string,
     *     last_error_code: ?string
     * }> */
    public array $events = [];

    private int $nextId = 1;

    public function record(DomainEventInterface $event, string $aggregateType, string $aggregateId): void
    {
        $this->events[] = [
            'id'              => $this->nextId++,
            'event_id'        => 'evt-' . bin2hex(random_bytes(6)),
            'event_type'      => get_class($event),
            'aggregate_type'  => $aggregateType,
            'aggregate_id'    => $aggregateId,
            'payload'         => OutboxEventSerializer::serialize($event),
            'status'          => 'PENDING',
            'attempts'        => 0,
            'max_attempts'    => 5,
            'claimed_at'      => null,
            'claimed_by'      => null,
            'next_attempt_at' => new DateTimeImmutable(),
            'processed_at'    => null,
            'last_error'      => null,
            'last_error_code' => null,
        ];
    }

    public function claimPending(int $limit, string $claimedBy): array
    {
        $claimed = [];
        $now = new DateTimeImmutable();

        foreach ($this->events as &$evt) {
            if (count($claimed) >= $limit) {
                break;
            }

            if ($evt['status'] === 'PENDING' && ($evt['next_attempt_at'] === null || $evt['next_attempt_at'] <= $now)) {
                $evt['status'] = 'PROCESSING';
                $evt['claimed_at'] = $now;
                $evt['claimed_by'] = $claimedBy;

                $claimed[] = [
                    'id'             => $evt['id'],
                    'event_id'       => $evt['event_id'],
                    'event_type'     => $evt['event_type'],
                    'aggregate_type' => $evt['aggregate_type'],
                    'aggregate_id'   => $evt['aggregate_id'],
                    'payload'        => $evt['payload'],
                    'attempts'       => $evt['attempts'],
                    'max_attempts'   => $evt['max_attempts'],
                ];
            }
        }

        return $claimed;
    }

    public function markProcessed(int $id): void
    {
        foreach ($this->events as &$evt) {
            if ($evt['id'] === $id) {
                $evt['status'] = 'PROCESSED';
                $evt['processed_at'] = new DateTimeImmutable();
                $evt['claimed_by'] = null;
                return;
            }
        }
    }

    public function markFailed(
        int $id,
        string $errorMessage,
        ?string $errorCode,
        ?DateTimeImmutable $nextAttemptAt,
        bool $isTerminal
    ): void {
        foreach ($this->events as &$evt) {
            if ($evt['id'] === $id) {
                $evt['attempts']++;
                $evt['last_error'] = $errorMessage;
                $evt['last_error_code'] = $errorCode;
                $evt['claimed_by'] = null;
                $evt['status'] = $isTerminal ? 'FAILED' : 'PENDING';
                $evt['next_attempt_at'] = $nextAttemptAt;
                return;
            }
        }
    }

    public function recoverAbandonedClaims(int $leaseSeconds = 300): int
    {
        $count = 0;
        $cutoff = (new DateTimeImmutable())->modify("-{$leaseSeconds} seconds");

        foreach ($this->events as &$evt) {
            if ($evt['status'] === 'PROCESSING' && $evt['claimed_at'] !== null && $evt['claimed_at'] < $cutoff) {
                $evt['status'] = 'PENDING';
                $evt['attempts']++;
                $evt['claimed_by'] = null;
                $evt['last_error'] = 'Claim lease expired; recovered by worker reaper';
                $count++;
            }
        }

        return $count;
    }
}
