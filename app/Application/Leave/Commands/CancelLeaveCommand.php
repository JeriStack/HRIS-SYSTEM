<?php

/**
 * CancelLeaveCommand
 * HRIS-SYSTEM — Application Layer
 */

declare(strict_types=1);

namespace App\Application\Leave\Commands;

final class CancelLeaveCommand
{
    public function __construct(
        public readonly int $requestId,
        public readonly int $requestingUserId,
        public readonly bool $isHrOrAdmin = false,
        public readonly ?string $reason = null
    ) {
    }
}
