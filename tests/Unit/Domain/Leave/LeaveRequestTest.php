<?php

/**
 * LeaveRequest Domain Entity Unit Test
 * TDD Phase 4 - Leave Domain Boundaries
 */

declare(strict_types=1);

namespace Tests\Unit\Domain\Leave;

use PHPUnit\Framework\TestCase;
use App\Domain\Leave\Entities\LeaveRequest;
use App\Domain\Leave\ValueObjects\LeaveDateRange;
use App\Domain\Leave\ValueObjects\LeaveStatus;
use App\Domain\Leave\Exceptions\InvalidStatusTransitionException;
use InvalidArgumentException;
use DateTimeImmutable;

class LeaveRequestTest extends TestCase
{
    private LeaveDateRange $sampleRange;

    protected function setUp(): void
    {
        parent::setUp();
        // Monday Nov 2 to Wednesday Nov 4 (3 working days)
        $this->sampleRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');
    }

    public function test_can_create_new_pending_leave_request(): void
    {
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1, // e.g. Vacation
            dateRange: $this->sampleRange,
            reason: 'Annual family vacation'
        );

        $this->assertNull($request->id());
        $this->assertSame(10, $request->employeeId());
        $this->assertSame(1, $request->leaveTypeId());
        $this->assertSame(3, $request->numberOfDays());
        $this->assertSame('Annual family vacation', $request->reason());
        $this->assertTrue($request->status()->isPending());
        $this->assertTrue($request->isPending());
        $this->assertFalse($request->isApproved());
        $this->assertNull($request->approvedBy());
        $this->assertNull($request->approvedAt());
        $this->assertNull($request->rejectionReason());
    }

    public function test_throws_exception_if_reason_is_empty_on_creation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Leave reason cannot be empty');

        LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $this->sampleRange,
            reason: '   '
        );
    }

    public function test_throws_exception_if_ids_are_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Employee ID must be a positive integer');

        LeaveRequest::create(
            employeeId: 0,
            leaveTypeId: 1,
            dateRange: $this->sampleRange,
            reason: 'Personal errand'
        );
    }

    public function test_can_approve_pending_leave_request(): void
    {
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $this->sampleRange,
            reason: 'Vacation'
        );

        $approverId = 99;
        $approvedTime = new DateTimeImmutable('2026-10-31 09:00:00');

        $request->approve($approverId, $approvedTime);

        $this->assertTrue($request->status()->isApproved());
        $this->assertTrue($request->isApproved());
        $this->assertFalse($request->isPending());
        $this->assertSame(99, $request->approvedBy());
        $this->assertSame($approvedTime, $request->approvedAt());
    }

    public function test_cannot_approve_already_approved_leave_request(): void
    {
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $this->sampleRange,
            reason: 'Vacation'
        );
        $request->approve(99);

        $this->expectException(InvalidStatusTransitionException::class);
        $request->approve(99);
    }

    public function test_can_reject_pending_leave_request_with_reason(): void
    {
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $this->sampleRange,
            reason: 'Vacation'
        );

        $approverId = 99;
        $rejectedTime = new DateTimeImmutable('2026-10-31 10:00:00');

        $request->reject($approverId, 'Critical deployment during requested dates', $rejectedTime);

        $this->assertTrue($request->status()->isRejected());
        $this->assertTrue($request->isRejected());
        $this->assertFalse($request->isPending());
        $this->assertSame(99, $request->approvedBy());
        $this->assertSame('Critical deployment during requested dates', $request->rejectionReason());
        $this->assertSame($rejectedTime, $request->approvedAt());
    }

    public function test_cannot_reject_without_providing_rejection_reason(): void
    {
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $this->sampleRange,
            reason: 'Vacation'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rejection reason cannot be empty');

        $request->reject(99, '   ');
    }

    public function test_can_cancel_pending_leave_request(): void
    {
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $this->sampleRange,
            reason: 'Vacation'
        );

        $request->cancel();

        $this->assertTrue($request->status()->isCancelled());
        $this->assertTrue($request->isCancelled());
    }

    public function test_can_cancel_approved_leave_request(): void
    {
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $this->sampleRange,
            reason: 'Vacation'
        );
        $request->approve(99);

        // Cancel approved leave (e.g. employee emergency cancellation or HR revoking)
        $request->cancel();

        $this->assertTrue($request->status()->isCancelled());
        $this->assertTrue($request->isCancelled());
    }

    public function test_cannot_transition_from_rejected_terminal_state(): void
    {
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $this->sampleRange,
            reason: 'Vacation'
        );
        $request->reject(99, 'Not enough coverage');

        $this->expectException(InvalidStatusTransitionException::class);
        $request->cancel();
    }

    public function test_can_reconstitute_from_persistence(): void
    {
        $createdAt = new DateTimeImmutable('2026-10-30 08:00:00');
        $approvedAt = new DateTimeImmutable('2026-10-30 11:30:00');

        $request = LeaveRequest::reconstitute(
            id: 42,
            employeeId: 10,
            leaveTypeId: 2,
            dateRange: $this->sampleRange,
            status: LeaveStatus::approved(),
            reason: 'Medical appointment',
            rejectionReason: null,
            approvedBy: 5,
            approvedAt: $approvedAt,
            createdAt: $createdAt
        );

        $this->assertSame(42, $request->id());
        $this->assertSame(10, $request->employeeId());
        $this->assertSame(2, $request->leaveTypeId());
        $this->assertSame(3, $request->numberOfDays());
        $this->assertSame('Medical appointment', $request->reason());
        $this->assertTrue($request->status()->isApproved());
        $this->assertSame(5, $request->approvedBy());
        $this->assertSame($approvedAt, $request->approvedAt());
        $this->assertSame($createdAt, $request->createdAt());
    }

    public function test_detects_overlap_with_another_request(): void
    {
        $req1 = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: LeaveDateRange::fromStrings('2026-11-02', '2026-11-06'),
            reason: 'Trip 1'
        );

        $req2 = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: LeaveDateRange::fromStrings('2026-11-05', '2026-11-10'),
            reason: 'Trip 2'
        );

        $req3 = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: LeaveDateRange::fromStrings('2026-11-09', '2026-11-13'),
            reason: 'Trip 3'
        );

        $this->assertTrue($req1->overlapsWith($req2));
        $this->assertFalse($req1->overlapsWith($req3));
    }
}
