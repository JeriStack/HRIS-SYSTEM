<?php

/**
 * FileLeaveCommand
 * HRIS-SYSTEM — Application Layer
 */

declare(strict_types=1);

namespace App\Application\Leave\Commands;

final class FileLeaveCommand
{
    public function __construct(
        public readonly int $employeeId,
        public readonly int $leaveTypeId,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly string $reason
    ) {
    }
}
