<?php

/**
 * AnnouncementCategory Domain Value Object
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 */

declare(strict_types=1);

namespace App\Domain\Announcement\ValueObjects;

use InvalidArgumentException;

final class AnnouncementCategory
{
    public const GENERAL_ANNOUNCEMENT  = 'General Announcement';
    public const COMPANY_UPDATES       = 'Company Updates';
    public const HR_POLICY_UPDATES     = 'HR Policy Updates';
    public const LEAVE_AND_BENEFITS    = 'Leave and Benefits';
    public const EVENTS_AND_ACTIVITIES = 'Events and Activities';
    public const OFFICE_OPERATIONS     = 'Office Operations';
    public const EMERGENCY_ADVISORY    = 'Emergency Advisory';
    public const OTHER                 = 'Other';

    public const VALID_CATEGORIES = [
        self::GENERAL_ANNOUNCEMENT,
        self::COMPANY_UPDATES,
        self::HR_POLICY_UPDATES,
        self::LEAVE_AND_BENEFITS,
        self::EVENTS_AND_ACTIVITIES,
        self::OFFICE_OPERATIONS,
        self::EMERGENCY_ADVISORY,
        self::OTHER,
    ];

    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if (!in_array($trimmed, self::VALID_CATEGORIES, true)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid announcement category '%s'.",
                $trimmed
            ));
        }

        $this->value = $trimmed;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * @return list<string>
     */
    public static function allowedValues(): array
    {
        return self::VALID_CATEGORIES;
    }

    public function value(): string
    {
        return $this->value;
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
