<?php

/**
 * LeaveBalance Domain Entity Unit Test
 * TDD Phase 4 - Leave Domain Boundaries
 */

declare(strict_types=1);

namespace Tests\Unit\Domain\Leave;

use PHPUnit\Framework\TestCase;
use App\Domain\Leave\Entities\LeaveBalance;
use App\Domain\Leave\Exceptions\InsufficientLeaveBalanceException;
use InvalidArgumentException;
use DomainException;

class LeaveBalanceTest extends TestCase
{
    public function test_can_create_new_leave_balance(): void
    {
        $balance = LeaveBalance::create(
            employeeId: 10,
            leaveTypeId: 1,
            year: 2026,
            allocatedDays: 15
        );

        $this->assertNull($balance->id());
        $this->assertSame(10, $balance->employeeId());
        $this->assertSame(1, $balance->leaveTypeId());
        $this->assertSame(2026, $balance->year());
        $this->assertSame(15, $balance->allocatedDays());
        $this->assertSame(0, $balance->usedDays());
        $this->assertSame(15, $balance->remainingDays());
    }

    public function test_can_reconstitute_leave_balance_from_persistence(): void
    {
        $balance = LeaveBalance::reconstitute(
            id: 101,
            employeeId: 10,
            leaveTypeId: 1,
            year: 2026,
            allocatedDays: 15,
            usedDays: 5,
            remainingDays: 10
        );

        $this->assertSame(101, $balance->id());
        $this->assertSame(15, $balance->allocatedDays());
        $this->assertSame(5, $balance->usedDays());
        $this->assertSame(10, $balance->remainingDays());
    }

    public function test_deducts_leave_days_successfully(): void
    {
        $balance = LeaveBalance::reconstitute(
            id: 1,
            employeeId: 10,
            leaveTypeId: 1,
            year: 2026,
            allocatedDays: 15,
            usedDays: 3,
            remainingDays: 12
        );

        $this->assertTrue($balance->hasSufficientBalance(5));

        $balance->deduct(5);

        $this->assertSame(8, $balance->usedDays());
        $this->assertSame(7, $balance->remainingDays());
        $this->assertSame(15, $balance->allocatedDays());
    }

    public function test_throws_exception_when_deducting_more_than_remaining_balance(): void
    {
        $balance = LeaveBalance::reconstitute(
            id: 1,
            employeeId: 10,
            leaveTypeId: 1,
            year: 2026,
            allocatedDays: 15,
            usedDays: 13,
            remainingDays: 2
        );

        $this->assertFalse($balance->hasSufficientBalance(3));

        $this->expectException(InsufficientLeaveBalanceException::class);
        $this->expectExceptionMessage('Insufficient leave balance');

        $balance->deduct(3);
    }

    public function test_refunds_leave_days_on_cancellation(): void
    {
        $balance = LeaveBalance::reconstitute(
            id: 1,
            employeeId: 10,
            leaveTypeId: 1,
            year: 2026,
            allocatedDays: 15,
            usedDays: 5,
            remainingDays: 10
        );

        $balance->refund(3);

        $this->assertSame(2, $balance->usedDays());
        $this->assertSame(13, $balance->remainingDays());
        $this->assertSame(15, $balance->allocatedDays());
    }

    public function test_throws_exception_when_refunding_more_than_used_days(): void
    {
        $balance = LeaveBalance::reconstitute(
            id: 1,
            employeeId: 10,
            leaveTypeId: 1,
            year: 2026,
            allocatedDays: 15,
            usedDays: 2,
            remainingDays: 13
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot refund more days than currently used');

        $balance->refund(3);
    }

    public function test_validates_invariants_on_reconstitution(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Balance invariant violation');

        // remaining (10) != allocated (15) - used (2)
        LeaveBalance::reconstitute(
            id: 1,
            employeeId: 10,
            leaveTypeId: 1,
            year: 2026,
            allocatedDays: 15,
            usedDays: 2,
            remainingDays: 10
        );
    }

    public function test_throws_exception_for_negative_allocated_days(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Allocated days cannot be negative');

        LeaveBalance::create(
            employeeId: 10,
            leaveTypeId: 1,
            year: 2026,
            allocatedDays: -1
        );
    }
}
