<?php

/**
 * LeaveStatus Domain Value Object Unit Test
 * TDD Phase 4 - Leave Domain Boundaries
 */

declare(strict_types=1);

namespace Tests\Unit\Domain\Leave;

use PHPUnit\Framework\TestCase;
use App\Domain\Leave\ValueObjects\LeaveStatus;
use App\Domain\Leave\Exceptions\InvalidStatusTransitionException;
use InvalidArgumentException;

class LeaveStatusTest extends TestCase
{
    public function test_can_instantiate_valid_statuses(): void
    {
        $pending = LeaveStatus::pending();
        $this->assertSame('Pending', $pending->value());
        $this->assertTrue($pending->isPending());

        $approved = LeaveStatus::approved();
        $this->assertSame('Approved', $approved->value());
        $this->assertTrue($approved->isApproved());

        $rejected = LeaveStatus::rejected();
        $this->assertSame('Rejected', $rejected->value());
        $this->assertTrue($rejected->isRejected());

        $cancelled = LeaveStatus::cancelled();
        $this->assertSame('Cancelled', $cancelled->value());
        $this->assertTrue($cancelled->isCancelled());
    }

    public function test_from_string_factory(): void
    {
        $status = LeaveStatus::fromString('Pending');
        $this->assertTrue($status->isPending());

        $this->expectException(InvalidArgumentException::class);
        LeaveStatus::fromString('UnknownStatus');
    }

    public function test_pending_can_transition_to_approved(): void
    {
        $pending = LeaveStatus::pending();
        $approved = $pending->transitionTo(LeaveStatus::approved());

        $this->assertTrue($approved->isApproved());
    }

    public function test_pending_can_transition_to_rejected(): void
    {
        $pending = LeaveStatus::pending();
        $rejected = $pending->transitionTo(LeaveStatus::rejected());

        $this->assertTrue($rejected->isRejected());
    }

    public function test_pending_can_transition_to_cancelled(): void
    {
        $pending = LeaveStatus::pending();
        $cancelled = $pending->transitionTo(LeaveStatus::cancelled());

        $this->assertTrue($cancelled->isCancelled());
    }

    public function test_cannot_transition_from_rejected_to_approved(): void
    {
        $rejected = LeaveStatus::rejected();

        $this->expectException(InvalidStatusTransitionException::class);
        $rejected->transitionTo(LeaveStatus::approved());
    }

    public function test_cannot_transition_from_cancelled_to_approved(): void
    {
        $cancelled = LeaveStatus::cancelled();

        $this->expectException(InvalidStatusTransitionException::class);
        $cancelled->transitionTo(LeaveStatus::approved());
    }

    public function test_cannot_re_approve_an_already_approved_status(): void
    {
        $approved = LeaveStatus::approved();

        $this->expectException(InvalidStatusTransitionException::class);
        $approved->transitionTo(LeaveStatus::approved());
    }
}
