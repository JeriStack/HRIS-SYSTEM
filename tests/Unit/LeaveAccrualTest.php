<?php

/**
 * LeaveAccrualTest
 * HRIS-SYSTEM — Unit Test for Automated Leave Accrual & Allocation
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Domain\Leave\Entities\LeaveBalance;
use DomainException;
use InvalidArgumentException;

class LeaveAccrualTest extends TestCase
{
    public function test_initial_leave_accrual_sets_zero_used_and_full_remaining_days(): void
    {
        $employeeId = 42;
        $leaveTypeId = 1;
        $year = 2026;
        $allocatedDays = 15;

        $balance = LeaveBalance::create(
            employeeId: $employeeId,
            leaveTypeId: $leaveTypeId,
            year: $year,
            allocatedDays: $allocatedDays
        );

        $this->assertNull($balance->id());
        $this->assertSame($employeeId, $balance->employeeId());
        $this->assertSame($leaveTypeId, $balance->leaveTypeId());
        $this->assertSame($year, $balance->year());
        $this->assertSame(15, $balance->allocatedDays());
        $this->assertSame(0, $balance->usedDays());
        $this->assertSame(15, $balance->remainingDays());
    }

    public function test_accrual_rejects_negative_allocation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Allocated days cannot be negative.');

        LeaveBalance::create(
            employeeId: 10,
            leaveTypeId: 2,
            year: 2026,
            allocatedDays: -5
        );
    }

    public function test_accrual_rejects_invalid_employee_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Employee ID must be a positive integer.');

        LeaveBalance::create(
            employeeId: 0,
            leaveTypeId: 1,
            year: 2026,
            allocatedDays: 10
        );
    }

    public function test_accrual_rejects_invalid_year(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid calendar year '1999'.");

        LeaveBalance::create(
            employeeId: 1,
            leaveTypeId: 1,
            year: 1999,
            allocatedDays: 10
        );
    }

    public function test_accrual_idempotency_simulation(): void
    {
        // Simulate batch accrual engine behavior
        $activeEmployees = [
            ['id' => 1, 'status' => 'Active'],
            ['id' => 2, 'status' => 'Active'],
            ['id' => 3, 'status' => 'Inactive'],
        ];

        $leaveTypes = [
            ['id' => 1, 'name' => 'Vacation Leave', 'default_days' => 15, 'status' => 'Active'],
            ['id' => 2, 'name' => 'Sick Leave', 'default_days' => 10, 'status' => 'Active'],
            ['id' => 3, 'name' => 'Legacy Leave', 'default_days' => 5, 'status' => 'Inactive'],
        ];

        $existingBalances = [
            '1_1_2026' => LeaveBalance::reconstitute(1, 1, 1, 2026, 15, 3, 12),
        ];

        $created = [];
        $skipped = [];

        foreach ($activeEmployees as $emp) {
            if ($emp['status'] !== 'Active') {
                continue;
            }

            foreach ($leaveTypes as $type) {
                if ($type['status'] !== 'Active') {
                    continue;
                }

                $key = "{$emp['id']}_{$type['id']}_2026";
                if (isset($existingBalances[$key])) {
                    $skipped[] = $key;
                    continue;
                }

                $created[$key] = LeaveBalance::create(
                    employeeId: $emp['id'],
                    leaveTypeId: $type['id'],
                    year: 2026,
                    allocatedDays: $type['default_days']
                );
            }
        }

        // Employee 1, Type 1 was already initialized -> must be skipped
        $this->assertContains('1_1_2026', $skipped);
        $this->assertArrayNotHasKey('1_1_2026', $created);

        // Employee 1, Type 2 must be created
        $this->assertArrayHasKey('1_2_2026', $created);
        $this->assertSame(10, $created['1_2_2026']->allocatedDays());

        // Employee 2, Type 1 & 2 must be created
        $this->assertArrayHasKey('2_1_2026', $created);
        $this->assertArrayHasKey('2_2_2026', $created);

        // Inactive employee 3 must have zero created records
        $this->assertArrayNotHasKey('3_1_2026', $created);
        $this->assertArrayNotHasKey('3_2_2026', $created);

        // Inactive leave type 3 must have zero created records
        $this->assertArrayNotHasKey('1_3_2026', $created);
        $this->assertArrayNotHasKey('2_3_2026', $created);
    }
}
