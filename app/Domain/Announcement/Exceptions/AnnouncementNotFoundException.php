<?php

/**
 * AnnouncementNotFoundException
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 */

declare(strict_types=1);

namespace App\Domain\Announcement\Exceptions;

use DomainException;

final class AnnouncementNotFoundException extends DomainException
{
    public static function forId(int $id): self
    {
        return new self(sprintf('Announcement #%d was not found.', $id));
    }
}
