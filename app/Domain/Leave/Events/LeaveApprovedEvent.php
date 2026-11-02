<?php

/**
 * LeaveApprovedEvent Domain Event
 * HRIS-SYSTEM — Domain-Driven Design (Leave Domain)
 */

declare(strict_types=1);

namespace App\Domain\Leave\Events;

use App\Domain\Common\DomainEventInterface;
use DateTimeImmutable;

final class LeaveApprovedEvent implements DomainEventInterface
{
    public function __construct(
        public readonly int $requestId,
        public readonly int $employeeId,
        public readonly int $leaveTypeId,
        public readonly int $numberOfDays,
        public readonly int $approverUserId,
        public readonly DateTimeImmutable $occurredAt
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
