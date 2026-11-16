<?php

/**
 * AnnouncementReadReceipt Domain Entity
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 */

declare(strict_types=1);

namespace App\Domain\Announcement\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

final class AnnouncementReadReceipt
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $announcementId,
        private readonly int $employeeId,
        private readonly DateTimeImmutable $readAt
    ) {
        if ($this->announcementId <= 0) {
            throw new InvalidArgumentException('Announcement ID must be a positive integer.');
        }
        if ($this->employeeId <= 0) {
            throw new InvalidArgumentException('Employee ID must be a positive integer.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function announcementId(): int
    {
        return $this->announcementId;
    }

    public function employeeId(): int
    {
        return $this->employeeId;
    }

    public function readAt(): DateTimeImmutable
    {
        return $this->readAt;
    }
}
