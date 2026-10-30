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
}
