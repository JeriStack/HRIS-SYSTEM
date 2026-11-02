<?php

/**
 * Outbox Repository Interface
 * HRIS-SYSTEM — Domain / Application Common Contract (Phase 1: Transactional Outbox)
 */

declare(strict_types=1);

namespace App\Domain\Common;

use DateTimeImmutable;

interface OutboxRepositoryInterface
{
    /**
     * Persist a domain event to the outbox within the active transaction boundary.
     */
    public function record(DomainEventInterface $event, string $aggregateType, string $aggregateId): void;

    /**
     * Atomically claim pending outbox events ready for processing using SKIP LOCKED.
     *
     * @param int $limit Max events to claim in single batch
     * @param string $claimedBy Worker identifier (e.g. hostname:pid)
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
    public function claimPending(int $limit, string $claimedBy): array;

    /**
     * Mark an outbox event as successfully processed.
     */
    public function markProcessed(int $id): void;

    /**
     * Record failure diagnostics and reschedule with backoff or mark as FAILED.
     */
    public function markFailed(
        int $id,
        string $errorMessage,
        ?string $errorCode,
        ?DateTimeImmutable $nextAttemptAt,
        bool $isTerminal
    ): void;

    /**
     * Recover abandoned processing claims left by crashed or terminated workers.
     *
     * @param int $leaseSeconds Lease expiration threshold in seconds (default 300)
     * @return int Count of reclaimed events reset to PENDING
     */
    public function recoverAbandonedClaims(int $leaseSeconds = 300): int;
}
