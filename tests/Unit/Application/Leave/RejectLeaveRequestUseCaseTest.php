<?php

/**
 * RejectLeaveRequestUseCase Unit Test
 * TDD Phase 5 - Application Use Cases
 */

declare(strict_types=1);

namespace Tests\Unit\Application\Leave;

use PHPUnit\Framework\TestCase;
use App\Application\Leave\UseCases\RejectLeaveRequestUseCase;
use App\Application\Leave\Commands\RejectLeaveCommand;
use App\Domain\Leave\Entities\LeaveRequest;
use App\Domain\Leave\ValueObjects\LeaveDateRange;
use App\Domain\Leave\Exceptions\LeaveRequestNotFoundException;
use App\Domain\Leave\Exceptions\InvalidStatusTransitionException;
use Tests\Fakes\InMemoryTransactionManager;
use Tests\Fakes\InMemoryLeaveRequestRepository;
use InvalidArgumentException;
use DateTimeImmutable;

class RejectLeaveRequestUseCaseTest extends TestCase
{
    private InMemoryTransactionManager $txManager;
    private InMemoryLeaveRequestRepository $requestRepo;
    private RejectLeaveRequestUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->txManager = new InMemoryTransactionManager();
        $this->requestRepo = new InMemoryLeaveRequestRepository();

        $this->useCase = new RejectLeaveRequestUseCase(
            $this->requestRepo,
            $this->txManager
        );
    }

    public function test_successfully_rejects_pending_leave_request(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $dateRange,
            reason: 'Vacation'
        );
        $requestId = $this->requestRepo->save($request);

        $command = new RejectLeaveCommand(
            requestId: $requestId,
            approverUserId: 99,
            rejectionReason: 'Team is understaffed during this sprint',
            rejectedAt: new DateTimeImmutable('2026-10-31 11:00:00')
        );

        $this->useCase->execute($command);

        $updated = $this->requestRepo->findById($requestId);
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isRejected());
        $this->assertSame(99, $updated->approvedBy());
        $this->assertSame('Team is understaffed during this sprint', $updated->rejectionReason());
        $this->assertTrue($this->requestRepo->wasLocked($requestId));
    }

    public function test_throws_exception_if_request_not_found(): void
    {
        $this->expectException(LeaveRequestNotFoundException::class);

        $command = new RejectLeaveCommand(
            requestId: 9999,
            approverUserId: 99,
            rejectionReason: 'Not found'
        );

        $this->useCase->execute($command);
    }

    public function test_throws_exception_if_rejection_reason_is_empty(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $dateRange,
            reason: 'Vacation'
        );
        $requestId = $this->requestRepo->save($request);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rejection reason cannot be empty');

        $command = new RejectLeaveCommand(
            requestId: $requestId,
            approverUserId: 99,
            rejectionReason: '   '
        );

        $this->useCase->execute($command);
    }

    public function test_throws_exception_if_request_already_approved(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $dateRange,
            reason: 'Vacation'
        );
        $request->approve(99);
        $requestId = $this->requestRepo->save($request);

        $this->expectException(InvalidStatusTransitionException::class);

        $command = new RejectLeaveCommand(
            requestId: $requestId,
            approverUserId: 99,
            rejectionReason: 'Too late'
        );

        $this->useCase->execute($command);
    }
}
