<?php

/**
 * Leave Request Not Found Domain Exception
 * HRIS-SYSTEM — Domain-Driven Design (Leave Domain)
 */

declare(strict_types=1);

namespace App\Domain\Leave\Exceptions;

use DomainException;

class LeaveRequestNotFoundException extends DomainException
{
    public static function forId(int $id): self
    {
        return new self(sprintf("Leave request with ID %d was not found.", $id));
    }
}
