<?php

/**
 * TransactionSafeEventPublicationTest
 * TDD Workstream 5 - Transaction-Safe Event Publication
 */

declare(strict_types=1);

namespace Tests\Unit\Application\Leave;

use PHPUnit\Framework\TestCase;
use App\Application\Leave\UseCases\ApproveLeaveRequestUseCase;
use App\Application\Leave\UseCases\RejectLeaveRequestUseCase;
use App\Application\Leave\Commands\ApproveLeaveCommand;
use App\Application\Leave\Commands\RejectLeaveCommand;
use App\Domain\Leave\Entities\LeaveRequest;
use App\Domain\Leave\Entities\LeaveBalance;
use App\Domain\Leave\ValueObjects\LeaveDateRange;
use App\Domain\Leave\Events\LeaveApprovedEvent;
use App\Domain\Leave\Events\LeaveRejectedEvent;
use App\Domain\Leave\Exceptions\InsufficientLeaveBalanceException;
use App\Infrastructure\Events\EventDispatcher;
use Tests\Fakes\InMemoryTransactionManager;
use Tests\Fakes\InMemoryLeaveRequestRepository;
use Tests\Fakes\InMemoryLeaveBalanceRepository;
use InvalidArgumentException;
use RuntimeException;
use DateTimeImmutable;

class TransactionSafeEventPublicationTest extends TestCase
{
    private InMemoryTransactionManager $txManager;
    private InMemoryLeaveRequestRepository $requestRepo;
    private InMemoryLeaveBalanceRepository $balanceRepo;
    private EventDispatcher $dispatcher;
    /** @var object[] */
    private array $dispatchedEvents = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->txManager = new InMemoryTransactionManager();
        $this->requestRepo = new InMemoryLeaveRequestRepository();
        $this->balanceRepo = new InMemoryLeaveBalanceRepository();
        $this->dispatcher = new EventDispatcher();
        $this->dispatchedEvents = [];

        $this->dispatcher->subscribe(LeaveApprovedEvent::class, function (LeaveApprovedEvent $e) {
            $this->dispatchedEvents[] = $e;
        });
        $this->dispatcher->subscribe(LeaveRejectedEvent::class, function (LeaveRejectedEvent $e) {
            $this->dispatchedEvents[] = $e;
        });
    }

    public function test_successful_approval_dispatches_event_after_commit(): void
    {
        $useCase = new ApproveLeaveRequestUseCase(
            $this->requestRepo,
            $this->balanceRepo,
            $this->txManager,
            $this->dispatcher
        );

        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04'); // 3 days
        $request = LeaveRequest::create(10, 1, $dateRange, 'Vacation');
        $requestId = $this->requestRepo->save($request);

        $balance = LeaveBalance::create(10, 1, 2026, 15);
        $this->balanceRepo->save($balance);

        $useCase->execute(new ApproveLeaveCommand($requestId, 99));

        $this->assertCount(1, $this->dispatchedEvents);
        $this->assertInstanceOf(LeaveApprovedEvent::class, $this->dispatchedEvents[0]);
        $this->assertSame($requestId, $this->dispatchedEvents[0]->requestId);
    }

    public function test_failed_approval_due_to_insufficient_balance_dispatches_zero_events(): void
    {
        $useCase = new ApproveLeaveRequestUseCase(
            $this->requestRepo,
            $this->balanceRepo,
            $this->txManager,
            $this->dispatcher
        );

        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-06'); // 5 days
        $request = LeaveRequest::create(10, 1, $dateRange, 'Vacation');
        $requestId = $this->requestRepo->save($request);

        // Only 2 days remaining
        $balance = LeaveBalance::reconstitute(1, 10, 1, 2026, 10, 8, 2);
        $this->balanceRepo->save($balance);

        try {
            $useCase->execute(new ApproveLeaveCommand($requestId, 99));
            $this->fail('Expected InsufficientLeaveBalanceException');
        } catch (InsufficientLeaveBalanceException $e) {
            // Assert ZERO events were dispatched
            $this->assertEmpty($this->dispatchedEvents);
        }
    }

    public function test_simulated_transaction_rollback_dispatches_zero_events(): void
    {
        // Transaction manager that throws during execution (simulating rollback)
        $failingTxManager = new class implements \App\Domain\Common\TransactionManagerInterface {
            public function transactional(callable $operation)
            {
                $operation();
                throw new RuntimeException('Database deadlock detected - rolled back');
            }
        };

        $useCase = new ApproveLeaveRequestUseCase(
            $this->requestRepo,
            $this->balanceRepo,
            $failingTxManager,
            $this->dispatcher
        );

        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');
        $request = LeaveRequest::create(10, 1, $dateRange, 'Vacation');
        $requestId = $this->requestRepo->save($request);

        $balance = LeaveBalance::create(10, 1, 2026, 15);
        $this->balanceRepo->save($balance);

        try {
            $useCase->execute(new ApproveLeaveCommand($requestId, 99));
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            // CRITICAL: Even if entity state was modified in memory, zero events dispatched!
            $this->assertEmpty($this->dispatchedEvents);
        }
    }

    public function test_successful_rejection_dispatches_event_after_commit(): void
    {
        $useCase = new RejectLeaveRequestUseCase(
            $this->requestRepo,
            $this->txManager,
            $this->dispatcher
        );

        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');
        $request = LeaveRequest::create(10, 1, $dateRange, 'Vacation');
        $requestId = $this->requestRepo->save($request);

        $useCase->execute(new RejectLeaveCommand($requestId, 99, 'Peak workload sprint'));

        $this->assertCount(1, $this->dispatchedEvents);
        $this->assertInstanceOf(LeaveRejectedEvent::class, $this->dispatchedEvents[0]);
        $this->assertSame('Peak workload sprint', $this->dispatchedEvents[0]->rejectionReason);
    }

    public function test_failed_rejection_dispatches_zero_events(): void
    {
        $useCase = new RejectLeaveRequestUseCase(
            $this->requestRepo,
            $this->txManager,
            $this->dispatcher
        );

        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');
        $request = LeaveRequest::create(10, 1, $dateRange, 'Vacation');
        $requestId = $this->requestRepo->save($request);

        try {
            $useCase->execute(new RejectLeaveCommand($requestId, 99, '   '));
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertEmpty($this->dispatchedEvents);
        }
    }
}
