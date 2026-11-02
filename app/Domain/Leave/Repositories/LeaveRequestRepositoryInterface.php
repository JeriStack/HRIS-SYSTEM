<?php

/**
 * LeaveRequest Repository Interface
 * HRIS-SYSTEM — Domain-Driven Design (Leave Domain)
 */

declare(strict_types=1);

namespace App\Domain\Leave\Repositories;

use App\Domain\Leave\Entities\LeaveRequest;
use App\Domain\Leave\ValueObjects\LeaveDateRange;

interface LeaveRequestRepositoryInterface
{
    /**
     * Retrieve a leave request by its primary ID.
     */
    public function findById(int $id): ?LeaveRequest;

    /**
     * Retrieve a leave request by its primary ID acquiring an exclusive row-level lock (FOR UPDATE).
     */
    public function findByIdForUpdate(int $id): ?LeaveRequest;

    /**
     * Persist a leave request entity (insert if id is null, update otherwise).
     * Returns the persistent ID.
     */
    public function save(LeaveRequest $request): int;

    /**
     * Find active (Pending or Approved) leave requests for an employee that overlap with the given range.
     *
     * @return LeaveRequest[]
     */
    public function findOverlapping(int $employeeId, LeaveDateRange $range, ?int $excludeRequestId = null): array;

    /**
     * Retrieve all leave requests filed by a specific employee.
     *
     * @return LeaveRequest[]
     */
    public function findByEmployee(int $employeeId): array;
}
