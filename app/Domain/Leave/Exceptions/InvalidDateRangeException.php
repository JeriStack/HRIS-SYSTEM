<?php

/**
 * Invalid Date Range Domain Exception
 * HRIS-SYSTEM — Domain-Driven Design (Leave Domain)
 */

declare(strict_types=1);

namespace App\Domain\Leave\Exceptions;

use DomainException;

class InvalidDateRangeException extends DomainException
{
    public static function endDatePrecedesStart(string $start, string $end): self
    {
        return new self(
            sprintf("Leave end date '%s' cannot precede start date '%s'.", $end, $start)
        );
    }

    public static function zeroWorkingDays(string $start, string $end): self
    {
        return new self(
            sprintf(
                "Leave date range from '%s' to '%s' contains zero business working days (weekends only).",
                $start,
                $end
            )
        );
    }

    public static function invalidFormat(string $date): self
    {
        return new self(
            sprintf("Invalid date string format '%s'. Expected 'Y-m-d'.", $date)
        );
    }
}
