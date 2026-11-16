<?php

/**
 * AnnouncementPublicationSchedule Domain Value Object
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 *
 * Encapsulates scheduled publication timestamp, actual publication timestamp,
 * and optional expiration timestamp along with cross-timestamp invariants.
 */

declare(strict_types=1);

namespace App\Domain\Announcement\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

final class AnnouncementPublicationSchedule
{
    public function __construct(
        private readonly ?PublicationDate $scheduledAt = null,
        private readonly ?PublicationDate $publishedAt = null,
        private readonly ?ExpirationDate $expiresAt = null
    ) {
        if ($this->scheduledAt !== null && $this->expiresAt !== null) {
            if (!$this->expiresAt->isAfter($this->scheduledAt->value())) {
                throw new InvalidArgumentException('Expiration date must be strictly after the scheduled publication date.');
            }
        }

        if ($this->publishedAt !== null && $this->expiresAt !== null) {
            if (!$this->expiresAt->isAfter($this->publishedAt->value())) {
                throw new InvalidArgumentException('Expiration date must be strictly after the publication date.');
            }
        }
    }

    public function scheduledAt(): ?PublicationDate
    {
        return $this->scheduledAt;
    }

    public function publishedAt(): ?PublicationDate
    {
        return $this->publishedAt;
    }

    public function expiresAt(): ?ExpirationDate
    {
        return $this->expiresAt;
    }

    public function withScheduledAt(PublicationDate $scheduledAt, ?ExpirationDate $expiresAt = null): self
    {
        return new self(
            scheduledAt: $scheduledAt,
            publishedAt: $this->publishedAt,
            expiresAt: $expiresAt ?? $this->expiresAt
        );
    }

    public function withPublishedAt(PublicationDate $publishedAt, ?ExpirationDate $expiresAt = null): self
    {
        return new self(
            scheduledAt: $this->scheduledAt,
            publishedAt: $publishedAt,
            expiresAt: $expiresAt ?? $this->expiresAt
        );
    }

    public function withoutSchedule(?ExpirationDate $expiresAt = null): self
    {
        return new self(
            scheduledAt: null,
            publishedAt: $this->publishedAt,
            expiresAt: $expiresAt
        );
    }

    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $this->expiresAt !== null && $this->expiresAt->isExpiredAt($now);
    }
}
