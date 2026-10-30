<?php

/**
 * Leave Balance Not Found Domain Exception
 * HRIS-SYSTEM — Domain-Driven Design (Leave Domain)
 */

declare(strict_types=1);

namespace App\Domain\Leave\Exceptions;

use DomainException;

class LeaveBalanceNotFoundException extends DomainException
{
    public static function forEmployee(int $employeeId, int $leaveTypeId, int $year): self
    {
        return new self(
            sprintf(
                "Leave balance record for employee ID %d, leave type ID %d, and year %d was not found.",
                $employeeId,
                $leaveTypeId,
                $year
            )
        );
    }
}
