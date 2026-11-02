<?php

/**
 * Transactional Outbox Unit Tests
 * HRIS-SYSTEM — Test Suite (Phase 1: Transactional Outbox)
 */

declare(strict_types=1);

namespace Tests\Unit\Outbox;

use App\Application\Leave\Commands\ApproveLeaveCommand;
use App\Application\Leave\Commands\RejectLeaveCommand;
use App\Application\Leave\UseCases\ApproveLeaveRequestUseCase;
use App\Application\Leave\UseCases\RejectLeaveRequestUseCase;
use App\Domain\Leave\Entities\LeaveBalance;
use App\Domain\Leave\Entities\LeaveRequest;
use App\Domain\Leave\Events\LeaveApprovedEvent;
use App\Domain\Leave\Events\LeaveRejectedEvent;
use App\Domain\Leave\ValueObjects\LeaveDateRange;
use App\Domain\Leave\ValueObjects\LeaveStatus;
use App\Infrastructure\Outbox\OutboxEventSerializer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryLeaveBalanceRepository;
use Tests\Fakes\InMemoryLeaveRequestRepository;
use Tests\Fakes\InMemoryOutboxRepository;
use Tests\Fakes\InMemoryTransactionManager;

class TransactionalOutboxUnitTest extends TestCase
{
    public function test_event_serialization_and_deserialization(): void
    {
        $now = new DateTimeImmutable('2026-10-31 10:00:00');
        $approvedEvent = new LeaveApprovedEvent(101, 42, 1, 3, 5, $now);

        $payload = OutboxEventSerializer::serialize($approvedEvent);
        $this->assertSame(101, $payload['requestId']);
        $this->assertSame(42, $payload['employeeId']);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('email', $payload); // Verifies minimal PII

        $reconstituted = OutboxEventSerializer::deserialize(LeaveApprovedEvent::class, $payload);
        $this->assertInstanceOf(LeaveApprovedEvent::class, $reconstituted);
        $this->assertSame(101, $reconstituted->requestId);
        $this->assertSame(42, $reconstituted->employeeId);
        $this->assertSame($now->format(DateTimeImmutable::ATOM), $reconstituted->occurredAt()->format(DateTimeImmutable::ATOM));
    }

    public function test_approval_use_case_records_outbox_event_in_transaction(): void
    {
        $reqRepo = new InMemoryLeaveRequestRepository();
        $balRepo = new InMemoryLeaveBalanceRepository();
        $txManager = new InMemoryTransactionManager();
        $outboxRepo = new InMemoryOutboxRepository();

        $start = new DateTimeImmutable('2026-11-02');
        $end = new DateTimeImmutable('2026-11-04');
        $dateRange = LeaveDateRange::fromDates($start, $end);
        $request = LeaveRequest::reconstitute(1, 1, 1, $dateRange, LeaveStatus::pending(), 'Vacation');
        $reqRepo->save($request);

        $balance = LeaveBalance::reconstitute(1, 1, 1, 2026, 15, 0, 15);
        $balRepo->save($balance);

        $useCase = new ApproveLeaveRequestUseCase(
            $reqRepo,
            $balRepo,
            $txManager,
            null,
            $outboxRepo
        );

        $useCase->execute(new ApproveLeaveCommand(1, 99));

        $this->assertCount(1, $outboxRepo->events);
        $event = $outboxRepo->events[0];
        $this->assertSame('PENDING', $event['status']);
        $this->assertSame(LeaveApprovedEvent::class, $event['event_type']);
        $this->assertSame('LeaveRequest', $event['aggregate_type']);
        $this->assertSame('1', $event['aggregate_id']);
        $this->assertSame(1, $event['payload']['requestId']);
    }

    public function test_rejection_use_case_records_outbox_event_in_transaction(): void
    {
        $reqRepo = new InMemoryLeaveRequestRepository();
        $txManager = new InMemoryTransactionManager();
        $outboxRepo = new InMemoryOutboxRepository();

        $start = new DateTimeImmutable('2026-11-02');
        $end = new DateTimeImmutable('2026-11-04');
        $dateRange = LeaveDateRange::fromDates($start, $end);
        $request = LeaveRequest::reconstitute(2, 1, 1, $dateRange, LeaveStatus::pending(), 'Vacation');
        $reqRepo->save($request);

        $useCase = new RejectLeaveRequestUseCase(
            $reqRepo,
            $txManager,
            null,
            $outboxRepo
        );

        $useCase->execute(new RejectLeaveCommand(2, 99, 'Scheduling conflict'));

        $this->assertCount(1, $outboxRepo->events);
        $event = $outboxRepo->events[0];
        $this->assertSame('PENDING', $event['status']);
        $this->assertSame(LeaveRejectedEvent::class, $event['event_type']);
        $this->assertSame('Scheduling conflict', $event['payload']['rejectionReason']);
    }

    public function test_outbox_claim_process_and_retry_lifecycle(): void
    {
        $outboxRepo = new InMemoryOutboxRepository();
        $event = new LeaveApprovedEvent(10, 5, 1, 2, 99, new DateTimeImmutable());
        $outboxRepo->record($event, 'LeaveRequest', '10');

        $this->assertCount(1, $outboxRepo->events);
        $this->assertSame('PENDING', $outboxRepo->events[0]['status']);

        // 1. Claim
        $claimed = $outboxRepo->claimPending(10, 'worker-1');
        $this->assertCount(1, $claimed);
        $this->assertSame('PROCESSING', $outboxRepo->events[0]['status']);
        $this->assertSame('worker-1', $outboxRepo->events[0]['claimed_by']);

        // 2. Second worker cannot claim already processing event
        $claimedSecond = $outboxRepo->claimPending(10, 'worker-2');
        $this->assertEmpty($claimedSecond);

        // 3. Simulated transient failure -> reschedule
        $retryAt = (new DateTimeImmutable())->modify('+30 seconds');
        $outboxRepo->markFailed($claimed[0]['id'], 'SMTP Connection timeout', 'SMTP_TIMEOUT', $retryAt, false);
        $this->assertSame('PENDING', $outboxRepo->events[0]['status']);
        $this->assertSame(1, $outboxRepo->events[0]['attempts']);
        $this->assertNull($outboxRepo->events[0]['claimed_by']);

        // 4. Mark terminal failure after max attempts
        $outboxRepo->markFailed($claimed[0]['id'], 'Permanent rejection', 'FATAL_ERR', null, true);
        $this->assertSame('FAILED', $outboxRepo->events[0]['status']);
    }

    public function test_reaper_recovers_abandoned_claims(): void
    {
        $outboxRepo = new InMemoryOutboxRepository();
        $event = new LeaveApprovedEvent(15, 5, 1, 2, 99, new DateTimeImmutable());
        $outboxRepo->record($event, 'LeaveRequest', '15');

        // Claim by crashed worker
        $outboxRepo->claimPending(10, 'crashed-worker');
        $this->assertSame('PROCESSING', $outboxRepo->events[0]['status']);

        // Simulate passage of time beyond lease
        $outboxRepo->events[0]['claimed_at'] = (new DateTimeImmutable())->modify('-400 seconds');

        $recovered = $outboxRepo->recoverAbandonedClaims(300);
        $this->assertSame(1, $recovered);
        $this->assertSame('PENDING', $outboxRepo->events[0]['status']);
        $this->assertNull($outboxRepo->events[0]['claimed_by']);
    }
}
