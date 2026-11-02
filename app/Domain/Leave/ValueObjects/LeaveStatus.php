<?php

/**
 * LeaveStatus Domain Value Object
 * HRIS-SYSTEM — Domain-Driven Design (Leave Domain)
 */

declare(strict_types=1);

namespace App\Domain\Leave\ValueObjects;

use App\Domain\Leave\Exceptions\InvalidStatusTransitionException;
use InvalidArgumentException;

final class LeaveStatus
{
    public const PENDING   = 'Pending';
    public const APPROVED  = 'Approved';
    public const REJECTED  = 'Rejected';
    public const CANCELLED = 'Cancelled';

    private const VALID_STATUSES = [
        self::PENDING,
        self::APPROVED,
        self::REJECTED,
        self::CANCELLED,
    ];

    /**
     * Permitted state transition map: [ currentStatus => [ allowedTargetStatuses ] ]
     */
    private const ALLOWED_TRANSITIONS = [
        self::PENDING   => [self::APPROVED, self::REJECTED, self::CANCELLED],
        self::APPROVED  => [self::CANCELLED], // Approved requests can only be cancelled by HR / policy
        self::REJECTED  => [],                // Terminal state
        self::CANCELLED => [],                // Terminal state
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(sprintf("Invalid leave status '%s'.", $value));
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function approved(): self
    {
        return new self(self::APPROVED);
    }

    public static function rejected(): self
    {
        return new self(self::REJECTED);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isPending(): bool
    {
        return $this->value === self::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->value === self::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->value === self::REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->value === self::CANCELLED;
    }

    public function canTransitionTo(self $target): bool
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->value] ?? [];
        return in_array($target->value(), $allowed, true);
    }

    public function transitionTo(self $target): self
    {
        if (!$this->canTransitionTo($target)) {
            throw InvalidStatusTransitionException::fromTransition($this->value, $target->value());
        }

        return $target;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
