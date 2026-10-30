<?php

/**
 * Invalid Status Transition Domain Exception
 * HRIS-SYSTEM — Domain-Driven Design (Leave Domain)
 */

declare(strict_types=1);

namespace App\Domain\Leave\Exceptions;

use DomainException;

class InvalidStatusTransitionException extends DomainException
{
    public static function fromTransition(string $from, string $to): self
    {
        return new self(
            sprintf("Cannot transition leave request status from '%s' to '%s'.", $from, $to)
        );
    }
}
