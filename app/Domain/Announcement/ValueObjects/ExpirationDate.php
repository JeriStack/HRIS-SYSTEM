<?php

/**
 * ExpirationDate Domain Value Object
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 */

declare(strict_types=1);

namespace App\Domain\Announcement\ValueObjects;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

final class ExpirationDate
{
    private DateTimeImmutable $dateTime;

    public function __construct(DateTimeImmutable $dateTime)
    {
        $this->dateTime = $dateTime;
    }

    public static function fromString(string $raw, ?DateTimeZone $tz = null): self
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Expiration date cannot be empty.');
        }

        try {
            $dt = $tz !== null ? new DateTimeImmutable($trimmed, $tz) : new DateTimeImmutable($trimmed);
        } catch (Throwable) {
            throw new InvalidArgumentException(sprintf("Invalid expiration date format '%s'.", $trimmed));
        }

        return new self($dt);
    }

    public static function fromDateTime(DateTimeImmutable $dateTime): self
    {
        return new self($dateTime);
    }

    public function value(): DateTimeImmutable
    {
        return $this->dateTime;
    }

    public function toDatabaseString(): string
    {
        return $this->dateTime->format('Y-m-d H:i:s');
    }

    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $this->dateTime->getTimestamp() <= $now->getTimestamp();
    }

    public function isAfter(DateTimeImmutable $reference): bool
    {
        return $this->dateTime->getTimestamp() > $reference->getTimestamp();
    }

    public function __toString(): string
    {
        return $this->toDatabaseString();
    }
}
