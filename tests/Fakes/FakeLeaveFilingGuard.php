<?php

/**
 * In-memory LeaveFilingGuard fake for unit testing.
 *
 * When $balanceRepo is set, ensureBalancesInitialized() seeds a balance to
 * simulate transaction-safe auto-initialization during filing.
 */

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Leave\Repositories\LeaveFilingGuardInterface;
use App\Domain\Leave\Entities\LeaveBalance;

final class FakeLeaveFilingGuard implements LeaveFilingGuardInterface
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
