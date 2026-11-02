<?php

/**
 * Insufficient Leave Balance Domain Exception
 * HRIS-SYSTEM — Domain-Driven Design (Leave Domain)
 */

declare(strict_types=1);

namespace App\Domain\Leave\Exceptions;

use DomainException;

class InsufficientLeaveBalanceException extends DomainException
{
    public static function forDays(int $remainingDays, int $requiredDays): self
    {
        return new self(
            sprintf(
                "Insufficient leave balance: Request requires %d day(s), but only %d day(s) remain.",
                $requiredDays,
                $remainingDays
            )
        );
    }
}
