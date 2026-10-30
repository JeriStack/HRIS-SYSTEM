<?php

/**
 * CancelLeaveRequestUseCase Unit Test
 * TDD Phase 5 - Application Use Cases
 */

declare(strict_types=1);

namespace Tests\Unit\Application\Leave;

use PHPUnit\Framework\TestCase;
use App\Application\Leave\UseCases\CancelLeaveRequestUseCase;
use App\Application\Leave\Commands\CancelLeaveCommand;
use App\Domain\Leave\Entities\LeaveRequest;
use App\Domain\Leave\Entities\LeaveBalance;
use App\Domain\Leave\ValueObjects\LeaveDateRange;
use App\Domain\Leave\Exceptions\LeaveRequestNotFoundException;
use App\Domain\Leave\Exceptions\InvalidStatusTransitionException;
use Tests\Fakes\InMemoryTransactionManager;
use Tests\Fakes\InMemoryLeaveRequestRepository;
use Tests\Fakes\InMemoryLeaveBalanceRepository;
use DomainException;

class CancelLeaveRequestUseCaseTest extends TestCase
{
    private InMemoryTransactionManager $txManager;
    private InMemoryLeaveRequestRepository $requestRepo;
    private InMemoryLeaveBalanceRepository $balanceRepo;
    private CancelLeaveRequestUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->txManager = new InMemoryTransactionManager();
        $this->requestRepo = new InMemoryLeaveRequestRepository();
        $this->balanceRepo = new InMemoryLeaveBalanceRepository();

        $this->useCase = new CancelLeaveRequestUseCase(
            $this->requestRepo,
            $this->balanceRepo,
            $this->txManager
        );
    }

    public function test_employee_can_cancel_their_own_pending_leave_request(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04'); // 3 days
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $dateRange,
            reason: 'Vacation'
        );
        $requestId = $this->requestRepo->save($request);

        $command = new CancelLeaveCommand(
            requestId: $requestId,
            requestingUserId: 10,
            isHrOrAdmin: false
        );

        $this->useCase->execute($command);

        $updated = $this->requestRepo->findById($requestId);
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isCancelled());
    }

    public function test_cancelling_approved_leave_request_refunds_balance(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04'); // 3 days
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $dateRange,
            reason: 'Vacation'
        );
        $request->approve(99);
        $requestId = $this->requestRepo->save($request);

        // Balance was previously deducted: 3 used, 12 remaining
        $balance = LeaveBalance::reconstitute(
            id: 1,
            employeeId: 10,
            leaveTypeId: 1,
            year: 2026,
            allocatedDays: 15,
            usedDays: 3,
            remainingDays: 12
        );
        $this->balanceRepo->save($balance);

        $command = new CancelLeaveCommand(
            requestId: $requestId,
            requestingUserId: 99,
            isHrOrAdmin: true
        );

        $this->useCase->execute($command);

        $updatedReq = $this->requestRepo->findById($requestId);
        $this->assertNotNull($updatedReq);
        $this->assertTrue($updatedReq->isCancelled());

        $updatedBal = $this->balanceRepo->findByEmployeeAndType(10, 1, 2026);
        $this->assertNotNull($updatedBal);
        $this->assertSame(0, $updatedBal->usedDays());
        $this->assertSame(15, $updatedBal->remainingDays());
    }

    public function test_throws_exception_if_unauthorized_user_tries_to_cancel(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $dateRange,
            reason: 'Vacation'
        );
        $requestId = $this->requestRepo->save($request);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unauthorized: Employees can only cancel their own leave requests');

        // Requesting user is employee 20, not 10, and not HR/Admin
        $command = new CancelLeaveCommand(
            requestId: $requestId,
            requestingUserId: 20,
            isHrOrAdmin: false
        );

        $this->useCase->execute($command);
    }

    public function test_throws_exception_if_request_not_found(): void
    {
        $this->expectException(LeaveRequestNotFoundException::class);

        $command = new CancelLeaveCommand(
            requestId: 9999,
            requestingUserId: 10,
            isHrOrAdmin: false
        );

        $this->useCase->execute($command);
    }

    public function test_throws_exception_if_request_is_already_rejected(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $dateRange,
            reason: 'Vacation'
        );
        $request->reject(99, 'Understaffed');
        $requestId = $this->requestRepo->save($request);

        $this->expectException(InvalidStatusTransitionException::class);

        $command = new CancelLeaveCommand(
            requestId: $requestId,
            requestingUserId: 10,
            isHrOrAdmin: false
        );

        $this->useCase->execute($command);
    }
}
