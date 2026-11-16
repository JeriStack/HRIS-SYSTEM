<?php

/**
 * AnnouncementTitle Domain Value Object
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 */

declare(strict_types=1);

namespace App\Domain\Announcement\ValueObjects;

use InvalidArgumentException;

final class AnnouncementTitle
{
    public const MIN_LENGTH = 3;
    public const MAX_LENGTH = 200;

    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Announcement title is required and cannot be empty.');
        }

        $len = function_exists('mb_strlen') ? mb_strlen($trimmed, 'UTF-8') : strlen($trimmed);
        if ($len < self::MIN_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Announcement title must be at least %d characters long.',
                self::MIN_LENGTH
            ));
        }

        if ($len > self::MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Announcement title cannot exceed %d characters.',
                self::MAX_LENGTH
            ));
        }

        $this->value = $trimmed;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
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
