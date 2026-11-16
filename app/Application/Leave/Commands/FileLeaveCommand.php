<?php

/**
 * FileLeaveCommand
 * HRIS-SYSTEM — Application Layer
 */

declare(strict_types=1);

namespace App\Application\Leave\Commands;

final class FileLeaveCommand
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public readonly int $employeeId,
        public readonly int $leaveTypeId,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly string $reason,
        public readonly ?string $entitlementUnit = null,
        public readonly ?array $metadata = null,
        public readonly ?int $policyVersionId = null,
        public readonly ?int $eventEntitlementId = null,
        public readonly ?int $overrideDays = null
    ) {
    }
}
