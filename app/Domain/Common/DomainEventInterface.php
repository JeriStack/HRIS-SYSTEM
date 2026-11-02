<?php

/**
 * Domain Event Interface
 * HRIS-SYSTEM — Domain Layer Common Contract
 */

declare(strict_types=1);

namespace App\Domain\Common;

use DateTimeImmutable;

interface DomainEventInterface
{
    public function occurredAt(): DateTimeImmutable;
}
