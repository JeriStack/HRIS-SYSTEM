<?php

/**
 * FileLeaveRequestUseCase Unit Test
 * TDD Phase 5 - Application Use Cases
 */

declare(strict_types=1);

namespace Tests\Unit\Application\Leave;

use PHPUnit\Framework\TestCase;
use App\Application\Leave\UseCases\FileLeaveRequestUseCase;
use App\Application\Leave\Commands\FileLeaveCommand;
use App\Domain\Leave\Entities\LeaveRequest;
use App\Domain\Leave\Entities\LeaveBalance;
use App\Domain\Leave\ValueObjects\LeaveDateRange;
use App\Domain\Leave\Exceptions\OverlappingLeaveException;
use App\Domain\Leave\Exceptions\InsufficientLeaveBalanceException;
use App\Domain\Leave\Exceptions\LeaveBalanceNotFoundException;
use Tests\Fakes\InMemoryTransactionManager;
use Tests\Fakes\InMemoryLeaveRequestRepository;
use Tests\Fakes\InMemoryLeaveBalanceRepository;

class FileLeaveRequestUseCaseTest extends TestCase
{
    private InMemoryTransactionManager $txManager;
    private InMemoryLeaveRequestRepository $requestRepo;
    private InMemoryLeaveBalanceRepository $balanceRepo;
    private FileLeaveRequestUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->txManager = new InMemoryTransactionManager();
        $this->requestRepo = new InMemoryLeaveRequestRepository();
        $this->balanceRepo = new InMemoryLeaveBalanceRepository();

        $this->useCase = new FileLeaveRequestUseCase(
            $this->requestRepo,
            $this->balanceRepo,
            $this->txManager
        );
    }

    public function test_successfully_files_new_leave_request(): void
    {
        // Allocate 15 days for year 2026
        $balance = LeaveBalance::create(
            employeeId: 10,
            leaveTypeId: 1,
            year: 2026,
            allocatedDays: 15
        );
        $this->balanceRepo->save($balance);

        $command = new FileLeaveCommand(
            employeeId: 10,
            leaveTypeId: 1,
            startDate: '2026-11-02',
            endDate: '2026-11-04', // 3 working days
            reason: 'Annual family holiday'
        );

        $requestId = $this->useCase->execute($command);

        $this->assertIsInt($requestId);
        $saved = $this->requestRepo->findById($requestId);
        $this->assertNotNull($saved);
        $this->assertSame(10, $saved->employeeId());
        $this->assertSame(1, $saved->leaveTypeId());
        $this->assertSame(3, $saved->numberOfDays());
        $this->assertSame('Annual family holiday', $saved->reason());
        $this->assertTrue($saved->isPending());
    }

    public function test_throws_exception_if_requested_dates_overlap_existing_leave(): void
    {
        // Existing leave from Nov 2 to Nov 6
        $existing = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: LeaveDateRange::fromStrings('2026-11-02', '2026-11-06'),
            reason: 'Existing trip'
        );
        $this->requestRepo->save($existing);

        $balance = LeaveBalance::create(10, 1, 2026, 15);
        $this->balanceRepo->save($balance);

        $this->expectException(OverlappingLeaveException::class);

        // Attempting to file overlapping range: Nov 5 to Nov 9
        $command = new FileLeaveCommand(
            employeeId: 10,
            leaveTypeId: 1,
            startDate: '2026-11-05',
            endDate: '2026-11-09',
            reason: 'Second trip'
        );

        $this->useCase->execute($command);
    }

    public function test_throws_exception_if_balance_is_insufficient_for_requested_days(): void
    {
        // Only 2 remaining days
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

        // Filing 3 working days (Nov 2 to Nov 4)
        $command = new FileLeaveCommand(
            employeeId: 10,
            leaveTypeId: 1,
            startDate: '2026-11-02',
            endDate: '2026-11-04',
            reason: 'Extended leave'
        );

        $this->useCase->execute($command);
    }

    public function test_throws_exception_if_no_leave_balance_exists_for_employee(): void
    {
        $this->expectException(LeaveBalanceNotFoundException::class);

        $command = new FileLeaveCommand(
            employeeId: 10,
            leaveTypeId: 5, // unallocated leave category
            startDate: '2026-11-02',
            endDate: '2026-11-04',
            reason: 'Unallocated category leave'
        );

        $this->useCase->execute($command);
    }

    // -----------------------------------------------------------------
    // LV-05/06/07: centralized filing rules via the filing guard
    // -----------------------------------------------------------------

    private function useCaseWithGuard(FakeLeaveFilingGuard $guard): FileLeaveRequestUseCase
    {
        return new FileLeaveRequestUseCase($this->requestRepo, $this->balanceRepo, $this->txManager, $guard);
    }

    public function test_cross_year_request_is_rejected_at_filing_time(): void
    {
        $this->balanceRepo->save(LeaveBalance::create(10, 1, 2026, 15));

        $this->expectException(\App\Domain\Leave\Exceptions\CrossYearLeaveException::class);

        // Dec 30 2026 -> Jan 1 2027 spans two leave years.
        $this->useCaseWithGuard(new FakeLeaveFilingGuard())->execute(new FileLeaveCommand(
            employeeId: 10,
            leaveTypeId: 1,
            startDate: '2026-12-30',
            endDate: '2027-01-01',
            reason: 'Year-end leave'
        ));
    }

    public function test_inactive_employee_cannot_file(): void
    {
        $this->balanceRepo->save(LeaveBalance::create(10, 1, 2026, 15));

        $this->expectException(\App\Domain\Leave\Exceptions\LeaveApprovalNotPermittedException::class);

        $this->useCaseWithGuard(new FakeLeaveFilingGuard(employeeActive: false))->execute(new FileLeaveCommand(
            employeeId: 10, leaveTypeId: 1, startDate: '2026-11-02', endDate: '2026-11-04', reason: 'x'
        ));
    }

    public function test_inactive_leave_type_cannot_be_filed_against(): void
    {
        $this->balanceRepo->save(LeaveBalance::create(10, 1, 2026, 15));

        $this->expectException(\App\Domain\Leave\Exceptions\LeaveApprovalNotPermittedException::class);

        $this->useCaseWithGuard(new FakeLeaveFilingGuard(leaveTypeActive: false))->execute(new FileLeaveCommand(
            employeeId: 10, leaveTypeId: 1, startDate: '2026-11-02', endDate: '2026-11-04', reason: 'x'
        ));
    }

    public function test_pending_reservations_reduce_available_balance(): void
    {
        // 15 remaining, but 14 days already committed to Pending requests: only 1 day free.
        $this->balanceRepo->save(LeaveBalance::create(10, 1, 2026, 15));
        $guard = new FakeLeaveFilingGuard(pendingReserved: 14);

        $this->expectException(InsufficientLeaveBalanceException::class);

        // Requesting 3 working days exceeds the 1 effectively-available day.
        $this->useCaseWithGuard($guard)->execute(new FileLeaveCommand(
            employeeId: 10, leaveTypeId: 1, startDate: '2026-11-02', endDate: '2026-11-04', reason: 'x'
        ));
    }

    public function test_auto_initialization_is_invoked_before_locking_balance(): void
    {
        // No balance pre-seeded; the guard "creates" it so the lookup then succeeds.
        $guard = new FakeLeaveFilingGuard();
        $guard->balanceRepo = $this->balanceRepo; // guard seeds the balance on ensureBalancesInitialized

        $requestId = $this->useCaseWithGuard($guard)->execute(new FileLeaveCommand(
            employeeId: 10, leaveTypeId: 1, startDate: '2026-11-02', endDate: '2026-11-04', reason: 'Auto-init'
        ));

        $this->assertTrue($guard->initCalled, 'ensureBalancesInitialized must run before the balance lookup.');
        $this->assertNotNull($this->requestRepo->findById($requestId));
    }

    public function test_in_transaction_callback_runs_after_persist(): void
    {
        $this->balanceRepo->save(LeaveBalance::create(10, 1, 2026, 15));
        $seen = null;

        $id = $this->useCase->withinTransaction(function (int $newId) use (&$seen): void {
            $seen = $newId;
        })->execute(new FileLeaveCommand(
            employeeId: 10, leaveTypeId: 1, startDate: '2026-11-02', endDate: '2026-11-04', reason: 'cb'
        ));

        $this->assertSame($id, $seen, 'The callback must receive the persisted request id.');
    }
}

/**
 * In-memory filing guard for unit tests. When $balanceRepo is set, it seeds a balance
 * on ensureBalancesInitialized to simulate transaction-safe auto-initialization.
 */
final class FakeLeaveFilingGuard implements \App\Domain\Leave\Repositories\LeaveFilingGuardInterface
{
    public bool $initCalled = false;
    public ?InMemoryLeaveBalanceRepository $balanceRepo = null;

    public function __construct(
        private readonly bool $employeeActive = true,
        private readonly bool $leaveTypeActive = true,
        private readonly int $pendingReserved = 0
    ) {
    }

    public function isEmployeeActive(int $employeeId): bool
    {
        return $this->employeeActive;
    }

    public function isLeaveTypeActive(int $leaveTypeId): bool
    {
        return $this->leaveTypeActive;
    }

    public function pendingReservedDays(int $employeeId, int $leaveTypeId, int $year): int
    {
        return $this->pendingReserved;
    }

    public function ensureBalancesInitialized(int $employeeId, int $year): void
    {
        $this->initCalled = true;
        if ($this->balanceRepo !== null && $this->balanceRepo->findByEmployeeAndType($employeeId, 1, $year) === null) {
            $this->balanceRepo->save(LeaveBalance::create($employeeId, 1, $year, 15));
        }
    }
}
