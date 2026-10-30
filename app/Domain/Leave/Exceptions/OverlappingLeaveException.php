<?php

/**
 * Overlapping Leave Domain Exception
 * HRIS-SYSTEM — Domain-Driven Design (Leave Domain)
 */

declare(strict_types=1);

namespace App\Domain\Leave\Exceptions;

use DomainException;

class OverlappingLeaveException extends DomainException
{
    public static function forDates(string $startDate, string $endDate): self
    {
        return new self(
            sprintf(
                "Cannot file leave: The requested date range '%s' to '%s' overlaps with an existing leave filing.",
                $startDate,
                $endDate
            )
        );
    }
}
