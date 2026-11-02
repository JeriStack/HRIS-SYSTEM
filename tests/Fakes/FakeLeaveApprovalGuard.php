<?php

/**
 * In-memory LeaveApprovalGuard fake for unit testing.
 */

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Leave\Repositories\LeaveApprovalGuardInterface;

final class FakeLeaveApprovalGuard implements LeaveApprovalGuardInterface
{
    public function __construct(
        private readonly ?int $reviewerEmployeeId,
        private readonly bool $employeeActive = true,
        private readonly bool $leaveTypeActive = true
    ) {
    }

    public function activeHrReviewerEmployeeId(int $reviewerUserId): ?int
    {
        return $this->reviewerEmployeeId;
    }

    public function isEmployeeActive(int $employeeId): bool
    {
        return $this->employeeActive;
    }

    public function isLeaveTypeActive(int $leaveTypeId): bool
    {
        return $this->leaveTypeActive;
    }
}
