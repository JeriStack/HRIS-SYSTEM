<?php

/**
 * LeaveFilingGuardInterface
 * HRIS-SYSTEM — Domain-Driven Design (Leave Domain)
 *
 * Read/support port for the facts a leave filing must honour that live outside the
 * Leave aggregate: the filer's employment status, the leave type's active status,
 * already-reserved pending days, and transaction-safe balance initialization.
 * Lets FileLeaveRequestUseCase be the single filing path for both web and API while
 * keeping persistence details in the infrastructure layer.
 */

declare(strict_types=1);

namespace App\Domain\Leave\Repositories;

interface LeaveFilingGuardInterface
{
    /** Whether an employee record exists and is currently Active. */
    public function isEmployeeActive(int $employeeId): bool;

    /** Whether a leave type exists and is currently Active. */
    public function isLeaveTypeActive(int $leaveTypeId): bool;

    /**
     * Total days already committed to Pending requests for this employee/type/year.
     * Used to reserve pending days so concurrent filings cannot over-commit a balance.
     */
    public function pendingReservedDays(int $employeeId, int $leaveTypeId, int $year): int;

    /**
     * Ensure the employee has leave-balance rows for the given year, creating any
     * missing ones from the active leave types' default allocations using the project's
     * existing initialization logic. Must be idempotent and safe to call inside the
     * filing transaction (no duplicate rows under concurrent requests).
     */
    public function ensureBalancesInitialized(int $employeeId, int $year): void;
}
