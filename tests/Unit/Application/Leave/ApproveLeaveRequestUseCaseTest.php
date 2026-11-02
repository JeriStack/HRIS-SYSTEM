<?php

/**
 * ApproveLeaveRequestUseCase Unit Test
 * TDD Phase 5 - Application Use Cases
 */

declare(strict_types=1);

namespace Tests\Unit\Application\Leave;

use PHPUnit\Framework\TestCase;
use App\Application\Leave\UseCases\ApproveLeaveRequestUseCase;
use App\Application\Leave\Commands\ApproveLeaveCommand;
use App\Domain\Leave\Entities\LeaveRequest;
use App\Domain\Leave\Entities\LeaveBalance;
use App\Domain\Leave\ValueObjects\LeaveDateRange;
use App\Domain\Leave\Exceptions\LeaveRequestNotFoundException;
use App\Domain\Leave\Exceptions\LeaveBalanceNotFoundException;
use App\Domain\Leave\Exceptions\InsufficientLeaveBalanceException;
use App\Domain\Leave\Exceptions\InvalidStatusTransitionException;
use Tests\Fakes\InMemoryTransactionManager;
use Tests\Fakes\InMemoryLeaveRequestRepository;
use Tests\Fakes\InMemoryLeaveBalanceRepository;
use DateTimeImmutable;

class ApproveLeaveRequestUseCaseTest extends TestCase
{
    private InMemoryTransactionManager $txManager;
    private InMemoryLeaveRequestRepository $requestRepo;
    private InMemoryLeaveBalanceRepository $balanceRepo;
    private ApproveLeaveRequestUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->txManager = new InMemoryTransactionManager();
        $this->requestRepo = new InMemoryLeaveRequestRepository();
        $this->balanceRepo = new InMemoryLeaveBalanceRepository();

        $this->useCase = new ApproveLeaveRequestUseCase(
            $this->requestRepo,
            $this->balanceRepo,
            $this->txManager
        );
    }

    public function test_successfully_approves_pending_leave_request_and_deducts_balance(): void
    {
        // 1. Arrange: 3 working days (Nov 2 to Nov 4, 2026)
        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $dateRange,
            reason: 'Annual Leave'
        );
        $requestId = $this->requestRepo->save($request);

        // Balance: 15 allocated, 0 used, 15 remaining for year 2026
        $balance = LeaveBalance::create(
            employeeId: 10,
            leaveTypeId: 1,
            year: 2026,
            allocatedDays: 15
        );
        $this->balanceRepo->save($balance);

        $command = new ApproveLeaveCommand(
            requestId: $requestId,
            approverUserId: 99,
            approvedAt: new DateTimeImmutable('2026-10-31 10:00:00')
        );

        // 2. Act
        $this->useCase->execute($command);

        // 3. Assert
        $updatedRequest = $this->requestRepo->findById($requestId);
        $this->assertNotNull($updatedRequest);
        $this->assertTrue($updatedRequest->isApproved());
        $this->assertSame(99, $updatedRequest->approvedBy());

        $updatedBalance = $this->balanceRepo->findByEmployeeAndType(10, 1, 2026);
        $this->assertNotNull($updatedBalance);
        $this->assertSame(3, $updatedBalance->usedDays());
        $this->assertSame(12, $updatedBalance->remainingDays());

        // Assert transactional row locks were acquired
        $this->assertTrue($this->requestRepo->wasLocked($requestId));
        $this->assertTrue($this->balanceRepo->wasLocked(10, 1, 2026));
        $this->assertTrue($this->txManager->wasExecuted());
    }

    public function test_throws_exception_if_leave_request_not_found(): void
    {
        $this->expectException(LeaveRequestNotFoundException::class);

        $command = new ApproveLeaveCommand(
            requestId: 9999,
            approverUserId: 99
        );

        $this->useCase->execute($command);
    }

    public function test_throws_exception_if_leave_balance_record_not_found(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 2,
            dateRange: $dateRange,
            reason: 'Sick leave'
        );
        $requestId = $this->requestRepo->save($request);

        $this->expectException(LeaveBalanceNotFoundException::class);

        $command = new ApproveLeaveCommand(
            requestId: $requestId,
            approverUserId: 99
        );

        $this->useCase->execute($command);
    }

    public function test_throws_exception_if_balance_is_insufficient(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-06'); // 5 days
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $dateRange,
            reason: 'Vacation'
        );
        $requestId = $this->requestRepo->save($request);

        // Only 2 days remaining
        $balance = LeaveBalance::reconstitute(
            id: 1,
            employeeId: 10,
            leaveTypeId: 1,
            year: 2026,
            allocatedDays: 10,
            usedDays: 8,
            remainingDays: 2
        );
        $this->balanceRepo->save($balance);

        $this->expectException(InsufficientLeaveBalanceException::class);

        $command = new ApproveLeaveCommand(
            requestId: $requestId,
            approverUserId: 99
        );

        $this->useCase->execute($command);
    }

    public function test_throws_exception_if_request_is_not_pending(): void
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

        $balance = LeaveBalance::create(10, 1, 2026, 15);
        $this->balanceRepo->save($balance);

        $this->expectException(InvalidStatusTransitionException::class);

        $command = new ApproveLeaveCommand(
            requestId: $requestId,
            approverUserId: 99
        );

        $this->useCase->execute($command);
    }

    // -----------------------------------------------------------------
    // LV-01: account/organization re-checks via the approval guard
    // -----------------------------------------------------------------

    private function useCaseWithGuard(\App\Domain\Leave\Repositories\LeaveApprovalGuardInterface $guard): ApproveLeaveRequestUseCase
    {
        return new ApproveLeaveRequestUseCase(
            $this->requestRepo,
            $this->balanceRepo,
            $this->txManager,
            null,
            null,
            $guard
        );
    }

    private function seedPendingWithBalance(int $employeeId = 10, int $leaveTypeId = 1): int
    {
        $request = LeaveRequest::create($employeeId, $leaveTypeId, LeaveDateRange::fromStrings('2026-11-02', '2026-11-04'), 'Annual Leave');
        $id = $this->requestRepo->save($request);
        $this->balanceRepo->save(LeaveBalance::create($employeeId, $leaveTypeId, 2026, 15));
        return $id;
    }

    public function test_guard_blocks_approval_by_non_hr_or_inactive_reviewer(): void
    {
        $id = $this->seedPendingWithBalance();
        $guard = new FakeLeaveApprovalGuard(reviewerEmployeeId: null); // not active HR

        $this->expectException(\App\Domain\Leave\Exceptions\LeaveApprovalNotPermittedException::class);
        $this->useCaseWithGuard($guard)->execute(new ApproveLeaveCommand($id, 99));
    }

    public function test_guard_blocks_self_approval(): void
    {
        $id = $this->seedPendingWithBalance(employeeId: 10);
        // Reviewer is an active HR user whose own employee record is #10 (the requester).
        $guard = new FakeLeaveApprovalGuard(reviewerEmployeeId: 10);

        $this->expectException(\App\Domain\Leave\Exceptions\LeaveApprovalNotPermittedException::class);
        $this->expectExceptionMessage('own leave');
        $this->useCaseWithGuard($guard)->execute(new ApproveLeaveCommand($id, 99));
    }

    public function test_guard_blocks_approval_for_inactive_employee(): void
    {
        $id = $this->seedPendingWithBalance();
        $guard = new FakeLeaveApprovalGuard(reviewerEmployeeId: 500, employeeActive: false);

        $this->expectException(\App\Domain\Leave\Exceptions\LeaveApprovalNotPermittedException::class);
        $this->useCaseWithGuard($guard)->execute(new ApproveLeaveCommand($id, 99));
    }

    public function test_guard_blocks_approval_for_inactive_leave_type(): void
    {
        $id = $this->seedPendingWithBalance();
        $guard = new FakeLeaveApprovalGuard(reviewerEmployeeId: 500, leaveTypeActive: false);

        $this->expectException(\App\Domain\Leave\Exceptions\LeaveApprovalNotPermittedException::class);
        $this->useCaseWithGuard($guard)->execute(new ApproveLeaveCommand($id, 99));
    }

    public function test_guard_allows_approval_for_active_hr_reviewer_of_another_employee(): void
    {
        $id = $this->seedPendingWithBalance(employeeId: 10);
        $guard = new FakeLeaveApprovalGuard(reviewerEmployeeId: 500); // active HR, different employee

        $this->useCaseWithGuard($guard)->execute(new ApproveLeaveCommand($id, 99));

        $updated = $this->requestRepo->findById($id);
        $this->assertTrue($updated->isApproved());
        $this->assertSame(12, $this->balanceRepo->findByEmployeeAndType(10, 1, 2026)->remainingDays());
    }

    public function test_cross_year_request_is_rejected_before_balance_deduction(): void
    {
        // Dec 30 2026 to Jan 1 2027 spans two leave years.
        $request = LeaveRequest::create(10, 1, LeaveDateRange::fromStrings('2026-12-30', '2027-01-01'), 'Year-end');
        $id = $this->requestRepo->save($request);
        $this->balanceRepo->save(LeaveBalance::create(10, 1, 2026, 15));

        $this->expectException(\App\Domain\Leave\Exceptions\LeaveApprovalNotPermittedException::class);
        $this->expectExceptionMessage('multiple leave years');
        $this->useCaseWithGuard(new FakeLeaveApprovalGuard(reviewerEmployeeId: 500))
            ->execute(new ApproveLeaveCommand($id, 99));
    }

    public function test_overlap_with_already_approved_request_is_rejected(): void
    {
        // An approved request already occupies Nov 2 to Nov 4.
        $approved = LeaveRequest::create(10, 1, LeaveDateRange::fromStrings('2026-11-02', '2026-11-04'), 'First');
        $approved->approve(99);
        $this->requestRepo->save($approved);

        // A second pending request overlaps it.
        $pending = LeaveRequest::create(10, 1, LeaveDateRange::fromStrings('2026-11-03', '2026-11-05'), 'Second');
        $pendingId = $this->requestRepo->save($pending);
        $this->balanceRepo->save(LeaveBalance::create(10, 1, 2026, 15));

        $this->expectException(\App\Domain\Leave\Exceptions\LeaveApprovalNotPermittedException::class);
        $this->expectExceptionMessage('overlaps');
        $this->useCaseWithGuard(new FakeLeaveApprovalGuard(reviewerEmployeeId: 500))
            ->execute(new ApproveLeaveCommand($pendingId, 99));
    }
}

/**
 * Minimal in-memory approval guard for unit tests.
 */
final class FakeLeaveApprovalGuard implements \App\Domain\Leave\Repositories\LeaveApprovalGuardInterface
{
    public function __construct(
        private readonly ?int $reviewerEmployeeId,
        private readonly bool $employeeActive = true,
        private readonly bool $leaveTypeActive = true
    ) {
    }

    public function activeHrReviewerEmployeeId(int $reviewerUserId): ?int
    {
        return $this->reviewerEmployeeId;
    }

    public function isEmployeeActive(int $employeeId): bool
    {
        return $this->employeeActive;
    }

    public function isLeaveTypeActive(int $leaveTypeId): bool
    {
        return $this->leaveTypeActive;
    }
}
