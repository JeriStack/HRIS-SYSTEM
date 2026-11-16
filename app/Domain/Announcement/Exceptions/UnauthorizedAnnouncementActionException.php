<?php

/**
 * UnauthorizedAnnouncementActionException
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 */

declare(strict_types=1);

namespace App\Domain\Announcement\Exceptions;

use DomainException;

final class UnauthorizedAnnouncementActionException extends DomainException
{
    public static function forRole(string $role, string $action): self
    {
        return new self(sprintf(
            "Role '%s' is not authorized to perform announcement action '%s'.",
            $role,
            $action
        ));
    }

    public static function notAuthorOrAuthorizedManager(): self
    {
        return new self('You are not authorized to modify or view private statistics for an announcement created by another HR officer.');
    }

    public static function employeeNotInTargetAudience(): self
    {
        return new self('This announcement is not available or is not addressed to your department.');
    }
}
