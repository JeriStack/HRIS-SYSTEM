<?php

/**
 * AnnouncementRead Domain Event
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 */

declare(strict_types=1);

namespace App\Domain\Announcement\Events;

use App\Domain\Common\DomainEventInterface;
use DateTimeImmutable;

final class AnnouncementRead implements DomainEventInterface
{
    public function __construct(
        public readonly int $announcementId,
        public readonly int $employeeId,
        public readonly DateTimeImmutable $occurredAt
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
