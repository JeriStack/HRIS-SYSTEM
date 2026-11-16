<?php

/**
 * AnnouncementStatus Domain Value Object
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 */

declare(strict_types=1);

namespace App\Domain\Announcement\ValueObjects;

use App\Domain\Announcement\Exceptions\InvalidAnnouncementTransitionException;
use InvalidArgumentException;

final class AnnouncementStatus
{
    public const DRAFT     = 'Draft';
    public const SCHEDULED = 'Scheduled';
    public const PUBLISHED = 'Published';
    public const ARCHIVED  = 'Archived';
    public const EXPIRED   = 'Expired';

    public const VALID_STATUSES = [
        self::DRAFT,
        self::SCHEDULED,
        self::PUBLISHED,
        self::ARCHIVED,
        self::EXPIRED,
    ];

    /**
     * Controlled lifecycle transition map (Phase 3.4 & Phase 4).
     */
    private const ALLOWED_TRANSITIONS = [
        self::DRAFT     => [self::DRAFT, self::SCHEDULED, self::PUBLISHED, self::ARCHIVED],
        self::SCHEDULED => [self::SCHEDULED, self::DRAFT, self::PUBLISHED, self::ARCHIVED],
        self::PUBLISHED => [self::PUBLISHED, self::EXPIRED, self::ARCHIVED],
        self::EXPIRED   => [self::EXPIRED, self::PUBLISHED, self::ARCHIVED],
        self::ARCHIVED  => [self::ARCHIVED],
    ];

    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if (!in_array($trimmed, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid announcement status '%s'.",
                $trimmed
            ));
        }

        $this->value = $trimmed;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function draft(): self
    {
        return new self(self::DRAFT);
    }

    public static function scheduled(): self
    {
        return new self(self::SCHEDULED);
    }

    public static function published(): self
    {
        return new self(self::PUBLISHED);
    }

    public static function archived(): self
    {
        return new self(self::ARCHIVED);
    }

    public static function expired(): self
    {
        return new self(self::EXPIRED);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isDraft(): bool
    {
        return $this->value === self::DRAFT;
    }

    public function isScheduled(): bool
    {
        return $this->value === self::SCHEDULED;
    }

    public function isPublished(): bool
    {
        return $this->value === self::PUBLISHED;
    }

    public function isArchived(): bool
    {
        return $this->value === self::ARCHIVED;
    }

    public function isExpired(): bool
    {
        return $this->value === self::EXPIRED;
    }

    public function canTransitionTo(self $target): bool
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->value] ?? [];
        return in_array($target->value(), $allowed, true);
    }

    public function transitionTo(self $target): self
    {
        if (!$this->canTransitionTo($target)) {
            throw InvalidAnnouncementTransitionException::fromTransition($this->value, $target->value());
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
