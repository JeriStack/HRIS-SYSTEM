<?php

/**
 * AnnouncementPriority Domain Value Object
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 */

declare(strict_types=1);

namespace App\Domain\Announcement\ValueObjects;

use InvalidArgumentException;

final class AnnouncementPriority
{
    public const NORMAL    = 'Normal';
    public const IMPORTANT = 'Important';
    public const URGENT    = 'Urgent';

    public const VALID_PRIORITIES = [
        self::NORMAL,
        self::IMPORTANT,
        self::URGENT,
    ];

    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if (!in_array($trimmed, self::VALID_PRIORITIES, true)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid announcement priority '%s'. Allowed: Normal, Important, Urgent.",
                $trimmed
            ));
        }

        $this->value = $trimmed;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function normal(): self
    {
        return new self(self::NORMAL);
    }

    public static function important(): self
    {
        return new self(self::IMPORTANT);
    }

    public static function urgent(): self
    {
        return new self(self::URGENT);
    }

    /**
     * @return list<string>
     */
    public static function allowedValues(): array
    {
        return self::VALID_PRIORITIES;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isUrgent(): bool
    {
        return $this->value === self::URGENT;
    }

    public function isImportant(): bool
    {
        return $this->value === self::IMPORTANT;
    }

    public function weight(): int
    {
        return match ($this->value) {
            self::URGENT    => 3,
            self::IMPORTANT => 2,
            default         => 1,
        };
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
