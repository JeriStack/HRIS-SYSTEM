<?php

/**
 * Leave Domain Events Unit Test
 * TDD Workstream 3 & 7 - Domain Event Model
 */

declare(strict_types=1);

namespace Tests\Unit\Domain\Leave;

use PHPUnit\Framework\TestCase;
use App\Domain\Leave\Entities\LeaveRequest;
use App\Domain\Leave\ValueObjects\LeaveDateRange;
use App\Domain\Leave\Events\LeaveApprovedEvent;
use App\Domain\Leave\Events\LeaveRejectedEvent;
use App\Domain\Leave\Exceptions\InvalidStatusTransitionException;
use DateTimeImmutable;

class LeaveEventTest extends TestCase
{
    private LeaveDateRange $range;

    protected function setUp(): void
    {
        parent::setUp();
        $this->range = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');
    }

    public function test_leave_approved_event_is_immutable_and_holds_event_data(): void
    {
        $timestamp = new DateTimeImmutable('2026-10-31 10:00:00');
        $event = new LeaveApprovedEvent(
            requestId: 101,
            employeeId: 5,
            leaveTypeId: 1,
            numberOfDays: 3,
            approverUserId: 99,
            occurredAt: $timestamp
        );

        $this->assertSame(101, $event->requestId);
        $this->assertSame(5, $event->employeeId);
        $this->assertSame(1, $event->leaveTypeId);
        $this->assertSame(3, $event->numberOfDays);
        $this->assertSame(99, $event->approverUserId);
        $this->assertSame($timestamp, $event->occurredAt());
    }

    public function test_leave_rejected_event_is_immutable_and_holds_rejection_reason(): void
    {
        $timestamp = new DateTimeImmutable('2026-10-31 10:30:00');
        $event = new LeaveRejectedEvent(
            requestId: 102,
            employeeId: 5,
            leaveTypeId: 1,
            numberOfDays: 3,
            approverUserId: 99,
            rejectionReason: 'Team is understaffed',
            occurredAt: $timestamp
        );

        $this->assertSame(102, $event->requestId);
        $this->assertSame('Team is understaffed', $event->rejectionReason);
        $this->assertSame($timestamp, $event->occurredAt());
    }

    public function test_approving_leave_request_records_leave_approved_event(): void
    {
        $request = LeaveRequest::reconstitute(
            id: 101,
            employeeId: 5,
            leaveTypeId: 1,
            dateRange: $this->range,
            status: \App\Domain\Leave\ValueObjects\LeaveStatus::pending(),
            reason: 'Annual leave'
        );

        $approvedTime = new DateTimeImmutable('2026-10-31 11:00:00');
        $request->approve(99, $approvedTime);

        $events = $request->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(LeaveApprovedEvent::class, $events[0]);
        $this->assertSame(101, $events[0]->requestId);
        $this->assertSame(5, $events[0]->employeeId);
        $this->assertSame(99, $events[0]->approverUserId);
        $this->assertSame($approvedTime, $events[0]->occurredAt());

        // Subsequent release must be empty (events are consumed)
        $this->assertEmpty($request->releaseEvents());
    }

    public function test_rejecting_leave_request_records_leave_rejected_event(): void
    {
        $request = LeaveRequest::reconstitute(
            id: 102,
            employeeId: 5,
            leaveTypeId: 1,
            dateRange: $this->range,
            status: \App\Domain\Leave\ValueObjects\LeaveStatus::pending(),
            reason: 'Annual leave'
        );

        $rejectedTime = new DateTimeImmutable('2026-10-31 11:15:00');
        $request->reject(99, 'Scheduling conflict', $rejectedTime);

        $events = $request->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(LeaveRejectedEvent::class, $events[0]);
        $this->assertSame(102, $events[0]->requestId);
        $this->assertSame('Scheduling conflict', $events[0]->rejectionReason);
        $this->assertSame($rejectedTime, $events[0]->occurredAt());
    }

    public function test_invalid_transition_throws_and_records_no_event(): void
    {
        $request = LeaveRequest::reconstitute(
            id: 103,
            employeeId: 5,
            leaveTypeId: 1,
            dateRange: $this->range,
            status: \App\Domain\Leave\ValueObjects\LeaveStatus::approved(),
            reason: 'Already approved'
        );

        $this->expectException(InvalidStatusTransitionException::class);

        try {
            // Attempting to re-approve already approved request
            $request->approve(99);
        } finally {
            $this->assertEmpty($request->releaseEvents());
        }
    }
}
