<?php

/**
 * InvalidAnnouncementTargetException
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 */

declare(strict_types=1);

namespace App\Domain\Announcement\Exceptions;

use DomainException;

final class InvalidAnnouncementTargetException extends DomainException
{
    public static function emptyTargets(): self
    {
        return new self('An announcement must specify a valid target audience (all active employees or at least one valid department).');
    }

    public static function invalidDepartmentId(int $departmentId): self
    {
        return new self(sprintf('Department ID #%d does not exist or is invalid.', $departmentId));
    }
}
