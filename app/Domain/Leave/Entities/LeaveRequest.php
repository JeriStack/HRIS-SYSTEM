<?php

/**
 * LeaveRequest Domain Entity
 * HRIS-SYSTEM — Domain-Driven Design (Leave Domain)
 */

declare(strict_types=1);

namespace App\Domain\Leave\Entities;

use App\Domain\Leave\ValueObjects\LeaveDateRange;
use App\Domain\Leave\ValueObjects\LeaveStatus;
use App\Domain\Leave\Exceptions\InvalidStatusTransitionException;
use InvalidArgumentException;
use DateTimeImmutable;

class LeaveRequest
{
    private ?int $id;
    private int $employeeId;
    private int $leaveTypeId;
    private LeaveDateRange $dateRange;
    private LeaveStatus $status;
    private string $reason;
    private ?string $rejectionReason;
    private ?int $approvedBy;
    private ?DateTimeImmutable $approvedAt;
    private ?DateTimeImmutable $createdAt;

    private function __construct(
        ?int $id,
        int $employeeId,
        int $leaveTypeId,
        LeaveDateRange $dateRange,
        LeaveStatus $status,
        string $reason,
        ?string $rejectionReason = null,
        ?int $approvedBy = null,
        ?DateTimeImmutable $approvedAt = null,
        ?DateTimeImmutable $createdAt = null
    ) {
        if ($employeeId <= 0) {
            throw new InvalidArgumentException('Employee ID must be a positive integer.');
        }

        if ($leaveTypeId <= 0) {
            throw new InvalidArgumentException('Leave Type ID must be a positive integer.');
        }

        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw new InvalidArgumentException('Leave reason cannot be empty.');
        }

        $this->id = $id;
        $this->employeeId = $employeeId;
        $this->leaveTypeId = $leaveTypeId;
        $this->dateRange = $dateRange;
        $this->status = $status;
        $this->reason = $trimmedReason;
        $this->rejectionReason = $rejectionReason !== null ? trim($rejectionReason) : null;
        $this->approvedBy = $approvedBy;
        $this->approvedAt = $approvedAt;
        $this->createdAt = $createdAt;
    }

    /**
     * Factory to instantiate a brand-new pending leave filing.
     */
    public static function create(
        int $employeeId,
        int $leaveTypeId,
        LeaveDateRange $dateRange,
        string $reason
    ): self {
        return new self(
            id: null,
            employeeId: $employeeId,
            leaveTypeId: $leaveTypeId,
            dateRange: $dateRange,
            status: LeaveStatus::pending(),
            reason: $reason,
            rejectionReason: null,
            approvedBy: null,
            approvedAt: null,
            createdAt: new DateTimeImmutable()
        );
    }

    /**
     * Reconstitute an existing entity from persistent storage.
     */
    public static function reconstitute(
        int $id,
        int $employeeId,
        int $leaveTypeId,
        LeaveDateRange $dateRange,
        LeaveStatus $status,
        string $reason,
        ?string $rejectionReason = null,
        ?int $approvedBy = null,
        ?DateTimeImmutable $approvedAt = null,
        ?DateTimeImmutable $createdAt = null
    ): self {
        return new self(
            id: $id,
            employeeId: $employeeId,
            leaveTypeId: $leaveTypeId,
            dateRange: $dateRange,
            status: $status,
            reason: $reason,
            rejectionReason: $rejectionReason,
            approvedBy: $approvedBy,
            approvedAt: $approvedAt,
            createdAt: $createdAt
        );
    }

    // --- State Transitions ---

    public function approve(int $approverUserId, ?DateTimeImmutable $approvedAt = null): void
    {
        $targetStatus = LeaveStatus::approved();

        if (!$this->status->canTransitionTo($targetStatus)) {
            throw InvalidStatusTransitionException::fromTransition($this->status->value(), $targetStatus->value());
        }

        if ($approverUserId <= 0) {
            throw new InvalidArgumentException('Approver User ID must be a positive integer.');
        }

        $this->status = $targetStatus;
        $this->approvedBy = $approverUserId;
        $this->approvedAt = $approvedAt ?? new DateTimeImmutable();
    }

    public function reject(int $approverUserId, string $rejectionReason, ?DateTimeImmutable $rejectedAt = null): void
    {
        $targetStatus = LeaveStatus::rejected();

        if (!$this->status->canTransitionTo($targetStatus)) {
            throw InvalidStatusTransitionException::fromTransition($this->status->value(), $targetStatus->value());
        }

        $trimmedRejectionReason = trim($rejectionReason);
        if ($trimmedRejectionReason === '') {
            throw new InvalidArgumentException('Rejection reason cannot be empty.');
        }

        if ($approverUserId <= 0) {
            throw new InvalidArgumentException('Approver User ID must be a positive integer.');
        }

        $this->status = $targetStatus;
        $this->rejectionReason = $trimmedRejectionReason;
        $this->approvedBy = $approverUserId;
        $this->approvedAt = $rejectedAt ?? new DateTimeImmutable();
    }

    public function cancel(): void
    {
        $targetStatus = LeaveStatus::cancelled();

        if (!$this->status->canTransitionTo($targetStatus)) {
            throw InvalidStatusTransitionException::fromTransition($this->status->value(), $targetStatus->value());
        }

        $this->status = $targetStatus;
    }

    // --- Getters and Invariant Checks ---

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

    public function dateRange(): LeaveDateRange
    {
        return $this->dateRange;
    }

    public function numberOfDays(): int
    {
        return $this->dateRange->workingDays();
    }

    public function status(): LeaveStatus
    {
        return $this->status;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function approvedBy(): ?int
    {
        return $this->approvedBy;
    }

    public function approvedAt(): ?DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isApproved(): bool
    {
        return $this->status->isApproved();
    }

    public function isRejected(): bool
    {
        return $this->status->isRejected();
    }

    public function isCancelled(): bool
    {
        return $this->status->isCancelled();
    }

    public function overlapsWith(self $other): bool
    {
        return $this->dateRange->overlapsWith($other->dateRange());
    }
}
