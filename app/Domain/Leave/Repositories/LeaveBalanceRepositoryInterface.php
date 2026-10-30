<?php

/**
 * LeaveBalance Repository Interface
 * HRIS-SYSTEM — Domain-Driven Design (Leave Domain)
 */

declare(strict_types=1);

namespace App\Domain\Leave\Repositories;

use App\Domain\Leave\Entities\LeaveBalance;

interface LeaveBalanceRepositoryInterface
{
    /**
     * Find an employee's annual leave balance for a given leave category and calendar year.
     */
    public function findByEmployeeAndType(int $employeeId, int $leaveTypeId, int $year): ?LeaveBalance;

    /**
     * Find an employee's annual leave balance acquiring an exclusive row-level lock (FOR UPDATE).
     */
    public function findByEmployeeAndTypeForUpdate(int $employeeId, int $leaveTypeId, int $year): ?LeaveBalance;

    /**
     * Persist a leave balance entity (insert if id is null, update otherwise).
     * Returns the persistent ID.
     */
    public function save(LeaveBalance $balance): int;
}
