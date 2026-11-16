<?php

/**
 * LeaveBalance Domain Entity
 * HRIS-SYSTEM — Domain-Driven Design (Leave Domain)
 */

declare(strict_types=1);

namespace App\Domain\Leave\Entities;

use App\Domain\Leave\Exceptions\InsufficientLeaveBalanceException;
use InvalidArgumentException;
use DomainException;

class LeaveBalance
{
    private ?int $id;
    private int $employeeId;
    private int $leaveTypeId;
    private int $year;
    private int $allocatedDays;
    private int $usedDays;
    private int $remainingDays;
    private int $convertedDays;
    private int $expiredDays;

    private function __construct(
        ?int $id,
        int $employeeId,
        int $leaveTypeId,
        int $year,
        int $allocatedDays,
        int $usedDays,
        int $remainingDays,
        int $convertedDays = 0,
        int $expiredDays = 0
    ) {
        if ($employeeId <= 0) {
            throw new InvalidArgumentException('Employee ID must be a positive integer.');
        }

        if ($leaveTypeId <= 0) {
            throw new InvalidArgumentException('Leave Type ID must be a positive integer.');
        }

        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException(sprintf("Invalid calendar year '%d'.", $year));
        }

        if ($allocatedDays < 0) {
            throw new InvalidArgumentException('Allocated days cannot be negative.');
        }

        if ($usedDays < 0) {
            throw new InvalidArgumentException('Used days cannot be negative.');
        }

        if ($remainingDays < 0) {
            throw new InvalidArgumentException('Remaining days cannot be negative.');
        }

        if ($remainingDays !== ($allocatedDays - $usedDays - $convertedDays - $expiredDays)) {
            throw new DomainException(
                sprintf(
                    "Balance invariant violation: Remaining days (%d) must equal " .
                    "allocated days (%d) minus used (%d), converted (%d), and expired (%d).",
                    $remainingDays,
                    $allocatedDays,
                    $usedDays,
                    $convertedDays,
                    $expiredDays
                )
            );
        }

        $this->id = $id;
        $this->employeeId = $employeeId;
        $this->leaveTypeId = $leaveTypeId;
        $this->year = $year;
        $this->allocatedDays = $allocatedDays;
        $this->usedDays = $usedDays;
        $this->remainingDays = $remainingDays;
        $this->convertedDays = $convertedDays;
        $this->expiredDays = $expiredDays;
    }

    /**
     * Factory to initialize a fresh annual quota allocation for an employee.
     */
    public static function create(
        int $employeeId,
        int $leaveTypeId,
        int $year,
        int $allocatedDays
    ): self {
        return new self(
            id: null,
            employeeId: $employeeId,
            leaveTypeId: $leaveTypeId,
            year: $year,
            allocatedDays: $allocatedDays,
            usedDays: 0,
            remainingDays: $allocatedDays
        );
    }

    /**
     * Reconstitute an existing balance entity from persistence.
     */
    public static function reconstitute(
        int $id,
        int $employeeId,
        int $leaveTypeId,
        int $year,
        int $allocatedDays,
        int $usedDays,
        int $remainingDays,
        int $convertedDays = 0,
        int $expiredDays = 0
    ): self {
        return new self(
            id: $id,
            employeeId: $employeeId,
            leaveTypeId: $leaveTypeId,
            year: $year,
            allocatedDays: $allocatedDays,
            usedDays: $usedDays,
            remainingDays: $remainingDays,
            convertedDays: $convertedDays,
            expiredDays: $expiredDays
        );
    }

    // --- Invariants & Operations ---

    public function hasSufficientBalance(int $days): bool
    {
        return $days > 0 && $this->remainingDays >= $days;
    }

    public function deduct(int $days): void
    {
        if ($days <= 0) {
            throw new InvalidArgumentException('Days to deduct must be a positive integer.');
        }

        if (!$this->hasSufficientBalance($days)) {
            throw InsufficientLeaveBalanceException::forDays($this->remainingDays, $days);
        }

        $this->usedDays += $days;
        $this->remainingDays -= $days;
    }

    public function refund(int $days): void
    {
        if ($days <= 0) {
            throw new InvalidArgumentException('Days to refund must be a positive integer.');
        }

        if ($this->usedDays < $days) {
            throw new DomainException(
                sprintf("Cannot refund more days than currently used: requested %d, used %d.", $days, $this->usedDays)
            );
        }

        $this->usedDays -= $days;
        $this->remainingDays += $days;
    }

    public function reallocate(int $newAllocatedDays): void
    {
        if ($newAllocatedDays < 0) {
            throw new InvalidArgumentException('Allocated days cannot be negative.');
        }

        if ($newAllocatedDays < $this->usedDays) {
            throw new DomainException(
                sprintf(
                    "New allocation (%d) cannot be less than already consumed days (%d).",
                    $newAllocatedDays,
                    $this->usedDays
                )
            );
        }

        $this->allocatedDays = $newAllocatedDays;
        $this->remainingDays = $newAllocatedDays - $this->usedDays;
    }

    // --- Getters ---

    public function id(): ?int
    {
        return $this->id;
    }

    public function employeeId(): int
    {
        return $this->employeeId;
    }

    public function leaveTypeId(): int
    {
        return $this->leaveTypeId;
    }

    public function year(): int
    {
        return $this->year;
    }

    public function allocatedDays(): int
    {
        return $this->allocatedDays;
    }

    public function usedDays(): int
    {
        return $this->usedDays;
    }

    public function remainingDays(): int
    {
        return $this->remainingDays;
    }

    /**
     * Adjust remaining days directly (e.g., cash conversion or expiration) while
     * keeping entity invariant synchronized when deducted days came out of remaining.
     */
    public function deductWithoutUsedIncrement(int $days): void
    {
        if ($days <= 0 || !$this->hasSufficientBalance($days)) {
            throw InsufficientLeaveBalanceException::forDays($this->remainingDays, $days);
        }
        $this->allocatedDays = max(0, $this->allocatedDays - $days);
        $this->remainingDays -= $days;
    }
}
