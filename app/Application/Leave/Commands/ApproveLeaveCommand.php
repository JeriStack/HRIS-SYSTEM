<?php

/**
 * ApproveLeaveCommand
 * HRIS-SYSTEM — Application Layer
 */

declare(strict_types=1);

namespace App\Application\Leave\Commands;

use DateTimeImmutable;

final class ApproveLeaveCommand
{
    public function __construct(
        public readonly int $requestId,
        public readonly int $approverUserId,
        public readonly ?DateTimeImmutable $approvedAt = null
    ) {
    }
}
