<?php

/**
 * InvalidAnnouncementTransitionException
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 */

declare(strict_types=1);

namespace App\Domain\Announcement\Exceptions;

use DomainException;

final class InvalidAnnouncementTransitionException extends DomainException
{
    public static function fromTransition(string $from, string $to): self
    {
        return new self(sprintf(
            "Invalid announcement lifecycle transition from '%s' to '%s'.",
            $from,
            $to
        ));
    }
}
