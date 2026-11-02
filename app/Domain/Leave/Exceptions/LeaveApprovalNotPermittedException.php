<?php

/**
 * LeaveApprovalNotPermittedException
 * HRIS-SYSTEM — Domain-Driven Design (Leave Domain)
 *
 * Raised when an approval is blocked by an account/organization invariant that
 * lives outside the Leave aggregate: a non-HR or inactive reviewer, an attempt
 * to self-approve, an inactive employee or leave type, an approved-overlap, or
 * a request that spans more than one leave year.
 */

declare(strict_types=1);

namespace App\Domain\Leave\Exceptions;

use DomainException;

class LeaveApprovalNotPermittedException extends DomainException
{
    public static function reviewerNotActiveHr(): self
    {
        return new self('Approving user is not an active HR account.');
    }

    public static function selfApproval(): self
    {
        return new self('HR personnel cannot approve their own leave request.');
    }

    public static function employeeInactive(): self
    {
        return new self('Cannot approve request: the employee record is inactive or ineligible.');
    }

    public static function leaveTypeInactive(): self
    {
        return new self('Cannot approve request: the leave classification is invalid or inactive.');
    }

    public static function overlapsApproved(int $otherRequestId): self
    {
        return new self(
            sprintf('Cannot approve request: schedule overlaps with already approved leave request #%d.', $otherRequestId)
        );
    }

    public static function spansMultipleYears(int $startYear, int $endYear): self
    {
        return new self(
            sprintf('Cannot approve request: leave request spans multiple leave years (%d to %d).', $startYear, $endYear)
        );
    }
}
