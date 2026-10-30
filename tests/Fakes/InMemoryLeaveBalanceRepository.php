<?php

/**
 * In-Memory LeaveBalance Repository Fake for Unit Testing
 */

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Leave\Entities\LeaveBalance;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;

class InMemoryLeaveBalanceRepository implements LeaveBalanceRepositoryInterface
{
    /** @var array<string, LeaveBalance> */
    private array $storage = [];
    private int $autoIncrement = 1;
    private array $lockedKeys = [];

    private function key(int $empId, int $leaveTypeId, int $year): string
    {
        return sprintf("%d-%d-%d", $empId, $leaveTypeId, $year);
    }

    public function findByEmployeeAndType(int $employeeId, int $leaveTypeId, int $year): ?LeaveBalance
    {
        return $this->storage[$this->key($employeeId, $leaveTypeId, $year)] ?? null;
    }

    public function findByEmployeeAndTypeForUpdate(int $employeeId, int $leaveTypeId, int $year): ?LeaveBalance
    {
        $k = $this->key($employeeId, $leaveTypeId, $year);
        $this->lockedKeys[] = $k;
        return $this->storage[$k] ?? null;
    }

    public function save(LeaveBalance $balance): int
    {
        $id = $balance->id();
        if ($id === null) {
            $id = $this->autoIncrement++;
            $balance = LeaveBalance::reconstitute(
                id: $id,
                employeeId: $balance->employeeId(),
                leaveTypeId: $balance->leaveTypeId(),
                year: $balance->year(),
                allocatedDays: $balance->allocatedDays(),
                usedDays: $balance->usedDays(),
                remainingDays: $balance->remainingDays()
            );
        }

        $this->storage[$this->key($balance->employeeId(), $balance->leaveTypeId(), $balance->year())] = $balance;
        return $id;
    }

    public function wasLocked(int $empId, int $leaveTypeId, int $year): bool
    {
        return in_array($this->key($empId, $leaveTypeId, $year), $this->lockedKeys, true);
    }
}
