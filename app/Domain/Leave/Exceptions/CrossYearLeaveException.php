<?php

/**
 * CrossYearLeaveException
 * HRIS-SYSTEM — Domain-Driven Design (Leave Domain)
 *
 * Raised when a leave request spans more than one calendar year. Annual leave
 * quotas are tracked per year, so a single request must resolve to one year.
 */

declare(strict_types=1);

namespace App\Domain\Leave\Exceptions;

use DomainException;

class CrossYearLeaveException extends DomainException
{
    public static function forYears(int $startYear, int $endYear): self
    {
        return new self(
            sprintf(
                'Leave request spans multiple leave years (%d to %d). Please file one request per year.',
                $startYear,
                $endYear
            )
        );
    }
}
