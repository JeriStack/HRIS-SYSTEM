<?php

/**
 * LeaveDateRange Domain Value Object Unit Test
 * TDD Phase 4 - Leave Domain Boundaries
 */

declare(strict_types=1);

namespace Tests\Unit\Domain\Leave;

use PHPUnit\Framework\TestCase;
use App\Domain\Leave\ValueObjects\LeaveDateRange;
use App\Domain\Leave\Exceptions\InvalidDateRangeException;

class LeaveDateRangeTest extends TestCase
{
    public function test_can_create_valid_date_range(): void
    {
        // Monday to Wednesday: 3 working days
        $range = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');

        $this->assertSame('2026-11-02', $range->startDateString());
        $this->assertSame('2026-11-04', $range->endDateString());
        $this->assertSame(3, $range->workingDays());
    }

    public function test_single_working_day_range(): void
    {
        // A single Friday
        $range = LeaveDateRange::fromStrings('2026-11-06', '2026-11-06');

        $this->assertSame(1, $range->workingDays());
    }

    public function test_excludes_weekends_from_working_days(): void
    {
        // Thursday 2026-11-05 to Tuesday 2026-11-10 (Thu, Fri, [Sat, Sun], Mon, Tue = 4 working days)
        $range = LeaveDateRange::fromStrings('2026-11-05', '2026-11-10');

        $this->assertSame(4, $range->workingDays());
        $this->assertSame(6, $range->calendarDays());
    }

    public function test_throws_exception_when_end_date_precedes_start_date(): void
    {
        $this->expectException(InvalidDateRangeException::class);
        $this->expectExceptionMessage('cannot precede start date');

        LeaveDateRange::fromStrings('2026-11-10', '2026-11-05');
    }

    public function test_throws_exception_when_range_contains_zero_working_days(): void
    {
        // Saturday to Sunday (zero working days)
        $this->expectException(InvalidDateRangeException::class);
        $this->expectExceptionMessage('zero business working days');

        LeaveDateRange::fromStrings('2026-11-07', '2026-11-08');
    }

    public function test_detects_overlapping_ranges(): void
    {
        // Range 1: Nov 2 to Nov 6
        $range1 = LeaveDateRange::fromStrings('2026-11-02', '2026-11-06');

        // Range 2: Nov 5 to Nov 9 (Overlaps on Nov 5, 6)
        $range2 = LeaveDateRange::fromStrings('2026-11-05', '2026-11-09');

        // Range 3: Nov 9 to Nov 13 (Does not overlap with Range 1)
        $range3 = LeaveDateRange::fromStrings('2026-11-09', '2026-11-13');

        $this->assertTrue($range1->overlapsWith($range2));
        $this->assertTrue($range2->overlapsWith($range1));

        $this->assertFalse($range1->overlapsWith($range3));
        $this->assertFalse($range3->overlapsWith($range1));
    }
}
