<?php

/**
 * AnnouncementArchived Domain Event
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 */

declare(strict_types=1);

namespace App\Domain\Announcement\Events;

use App\Domain\Common\DomainEventInterface;
use DateTimeImmutable;

final class AnnouncementArchived implements DomainEventInterface
{
    public function __construct(
        public readonly int $announcementId,
        public readonly int $actorUserId,
        public readonly DateTimeImmutable $occurredAt
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
