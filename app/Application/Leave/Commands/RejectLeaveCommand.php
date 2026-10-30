<?php

/**
 * RejectLeaveCommand
 * HRIS-SYSTEM — Application Layer
 */

declare(strict_types=1);

namespace App\Application\Leave\Commands;

use DateTimeImmutable;

final class RejectLeaveCommand
{
    public function __construct(
        public readonly int $requestId,
        public readonly int $approverUserId,
        public readonly string $rejectionReason,
        public readonly ?DateTimeImmutable $rejectedAt = null
    ) {
    }
}
