<?php

/**
 * LeaveDateRange Domain Value Object
 * HRIS-SYSTEM — Domain-Driven Design (Leave Domain)
 */

declare(strict_types=1);

namespace App\Domain\Leave\ValueObjects;

use App\Domain\Leave\Exceptions\InvalidDateRangeException;
use DateTimeImmutable;
use DateInterval;

final class LeaveDateRange
{
    private DateTimeImmutable $startDate;
    private DateTimeImmutable $endDate;
    private int $workingDays;
    private int $calendarDays;

    private function __construct(DateTimeImmutable $startDate, DateTimeImmutable $endDate)
    {
        $startDate = $startDate->setTime(0, 0, 0);
        $endDate   = $endDate->setTime(0, 0, 0);

        if ($endDate < $startDate) {
            throw InvalidDateRangeException::endDatePrecedesStart(
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );
        }

        $this->startDate = $startDate;
        $this->endDate   = $endDate;

        // Calculate calendar days (inclusive)
        $diff = $startDate->diff($endDate);
        $this->calendarDays = (int) $diff->days + 1;

        // Calculate working days (Monday-Friday inclusive)
        $this->workingDays = $this->calculateWorkingDays($startDate, $endDate);

        if ($this->workingDays === 0) {
            throw InvalidDateRangeException::zeroWorkingDays(
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );
        }
    }

    public static function fromDates(DateTimeImmutable $startDate, DateTimeImmutable $endDate): self
    {
        return new self($startDate, $endDate);
    }

    public static function fromStrings(string $startDate, string $endDate): self
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', trim($startDate));
        $end   = DateTimeImmutable::createFromFormat('!Y-m-d', trim($endDate));

        if ($start === false) {
            throw InvalidDateRangeException::invalidFormat($startDate);
        }

        if ($end === false) {
            throw InvalidDateRangeException::invalidFormat($endDate);
        }

        return new self($start, $end);
    }

    public function startDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    public function startDateString(): string
    {
        return $this->startDate->format('Y-m-d');
    }

    public function endDateString(): string
    {
        return $this->endDate->format('Y-m-d');
    }

    public function workingDays(): int
    {
        return $this->workingDays;
    }

    public function calendarDays(): int
    {
        return $this->calendarDays;
    }

    public function overlapsWith(self $other): bool
    {
        return $this->startDate <= $other->endDate && $this->endDate >= $other->startDate;
    }

    public function equals(self $other): bool
    {
        return $this->startDateString() === $other->startDateString()
            && $this->endDateString() === $other->endDateString();
    }

    private function calculateWorkingDays(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        $current = $start;
        $workingDays = 0;
        $oneDay = new DateInterval('P1D');

        while ($current <= $end) {
            $dayOfWeek = (int) $current->format('N'); // 1 = Mon, 7 = Sun
            if ($dayOfWeek <= 5) {
                $workingDays++;
            }
            $current = $current->add($oneDay);
        }

        return $workingDays;
    }
}
