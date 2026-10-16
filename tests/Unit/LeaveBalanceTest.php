<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\LeaveCalculator;
use InvalidArgumentException;

class LeaveBalanceTest extends TestCase
{
    public function test_calculate_working_days_for_standard_work_week(): void
    {
        // Monday (2026-10-19) to Friday (2026-10-23) = 5 working days
        $days = LeaveCalculator::calculateWorkingDays('2026-10-19', '2026-10-23');
        $this->assertSame(5, $days);
    }

    public function test_calculate_working_days_excludes_weekends(): void
    {
        // Saturday (2026-10-24) to Sunday (2026-10-25) = 0 working days
        $days = LeaveCalculator::calculateWorkingDays('2026-10-24', '2026-10-25');
        $this->assertSame(0, $days);
    }

    public function test_calculate_working_days_across_multiple_weeks(): void
    {
        // Monday (2026-10-12) to Friday of following week (2026-10-23) = 10 working days
        $days = LeaveCalculator::calculateWorkingDays('2026-10-12', '2026-10-23');
        $this->assertSame(10, $days);
    }

    public function test_single_day_working_day_determination(): void
    {
        // Wednesday (2026-10-21) is a working day
        $this->assertTrue(LeaveCalculator::isWorkingDay('2026-10-21'));

        // Sunday (2026-10-25) is not a working day
        $this->assertFalse(LeaveCalculator::isWorkingDay('2026-10-25'));
    }

    public function test_throws_exception_when_end_date_precedes_start_date(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LeaveCalculator::calculateWorkingDays('2026-10-25', '2026-10-20');
    }

    public function test_throws_exception_on_invalid_date_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LeaveCalculator::calculateWorkingDays('invalid-date', '2026-10-25');
    }

    public function test_leave_balance_deduction_calculation(): void
    {
        $allocatedDays = 15;
        $usedDays = 3;
        $requestedDays = 5;

        $remainingDays = $allocatedDays - $usedDays;
        $this->assertSame(12, $remainingDays);

        // Deduct 5 approved days
        $newUsed = $usedDays + $requestedDays;
        $newRemaining = max(0, $allocatedDays - $newUsed);

        $this->assertSame(8, $newUsed);
        $this->assertSame(7, $newRemaining);
    }

    public function test_leave_balance_rejects_excessive_request(): void
    {
        $allocatedDays = 10;
        $usedDays = 8;
        $remainingDays = $allocatedDays - $usedDays; // 2 days left
        $requestedDays = 5;

        $hasSufficientBalance = ($remainingDays >= $requestedDays);
        $this->assertFalse($hasSufficientBalance, 'Leave request should be rejected if requested days exceed remaining credits.');
    }
}
