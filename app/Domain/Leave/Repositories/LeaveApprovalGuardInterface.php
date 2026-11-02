<?php

/**
 * LeaveApprovalGuardInterface
 * HRIS-SYSTEM — Domain-Driven Design (Leave Domain)
 *
 * Read port exposing the account/organization facts the approval use case must
 * re-validate at decision time but which live outside the Leave aggregate
 * (reviewer account, employee employment status, leave-type status). Lets the
 * single DDD approval path enforce every invariant the legacy model enforced,
 * without the use case reaching into other models directly.
 */

declare(strict_types=1);

namespace App\Domain\Leave\Repositories;

interface LeaveApprovalGuardInterface
{
    /**
     * The employee id linked to a reviewer user account, or null when the
     * reviewer is not an active HR account. Used to reject non-HR/inactive
     * reviewers and to enforce segregation of duties (no self-approval).
     */
    public function activeHrReviewerEmployeeId(int $reviewerUserId): ?int;

    /**
     * Whether an employee record exists and is currently Active.
     */
    public function isEmployeeActive(int $employeeId): bool;

    /**
     * Whether a leave type exists and is currently Active.
     */
    public function isLeaveTypeActive(int $leaveTypeId): bool;
}
