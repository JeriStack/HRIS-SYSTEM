<?php

namespace Tests\Unit\DSA;

use App\Dsa\Arrays\ArrayUtils;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ArrayUtilsTest extends TestCase
{
    public function test_value_of_returns_scalar_when_no_attribute(): void
    {
        $this->assertSame(42, ArrayUtils::valueOf(42, null));
    }

    public function test_value_of_returns_field_or_null_for_records(): void
    {
        $this->assertSame('EMP-001', ArrayUtils::valueOf(['employee_no' => 'EMP-001'], 'employee_no'));
        $this->assertNull(ArrayUtils::valueOf(['employee_no' => 'EMP-001'], 'missing'));
        $this->assertNull(ArrayUtils::valueOf('not-an-array', 'employee_no'));
    }

    public function test_numeric_values_compare_numerically(): void
    {
        $this->assertSame(-1, ArrayUtils::compareValues(2, 10));
        $this->assertSame(1, ArrayUtils::compareValues(10, 2));
        $this->assertSame(0, ArrayUtils::compareValues(5, 5));
        // Numeric strings compare numerically, not lexically
        $this->assertSame(-1, ArrayUtils::compareValues('2', '10'));
    }

    public function test_text_values_compare_case_insensitively(): void
    {
        $this->assertSame(0, ArrayUtils::compareValues('Alvarez', 'alvarez'));
        $this->assertTrue(ArrayUtils::compareValues('alpha', 'beta') < 0);
    }

    public function test_null_sorts_last_ascending_and_first_descending(): void
    {
        $this->assertSame(1, ArrayUtils::compareValues(null, 5));
        $this->assertSame(-1, ArrayUtils::compareValues(5, null));
        $this->assertSame(0, ArrayUtils::compareValues(null, null));
        // Direction applied through compare()
        $this->assertSame(1, ArrayUtils::compare(['k' => null], ['k' => 5], 'k', 'asc'));
        $this->assertSame(-1, ArrayUtils::compare(['k' => null], ['k' => 5], 'k', 'desc'));
    }

    public function test_direction_inverts_comparison(): void
    {
        $this->assertSame(-1, ArrayUtils::compare(2, 10, null, 'asc'));
        $this->assertSame(1, ArrayUtils::compare(2, 10, null, 'desc'));
    }

    public function test_values_match_numeric_and_text(): void
    {
        $this->assertTrue(ArrayUtils::valuesMatch(5, '5'));
        $this->assertTrue(ArrayUtils::valuesMatch('ABC', 'abc'));
        $this->assertFalse(ArrayUtils::valuesMatch('abc', 'abcd'));
        $this->assertTrue(ArrayUtils::valuesMatch(null, null));
        $this->assertFalse(ArrayUtils::valuesMatch(null, 1));
    }

    public function test_normalize_direction_validates(): void
    {
        $this->assertSame('asc', ArrayUtils::normalizeDirection('ASC'));
        $this->assertSame('desc', ArrayUtils::normalizeDirection(' desc '));
        $this->assertSame('asc', ArrayUtils::normalizeDirection(''));
        $this->expectException(InvalidArgumentException::class);
        ArrayUtils::normalizeDirection('sideways');
    }

    public function test_to_list_reindexes_sparse_arrays_without_reordering(): void
    {
        $sparse = [5 => 'a', 2 => 'b', 99 => 'c'];
        $this->assertSame(['a', 'b', 'c'], ArrayUtils::toList($sparse));
        $this->assertSame([], ArrayUtils::toList([]));
        $this->assertSame(['solo'], ArrayUtils::toList([7 => 'solo']));
    }

    public function test_assert_records_have_field_throws_on_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ArrayUtils::assertRecordsHaveField([['k' => 1], ['other' => 2]], 'k');
    }

    public function test_assert_records_have_field_passes_when_present(): void
    {
        ArrayUtils::assertRecordsHaveField([['k' => 1], ['k' => 2]], 'k');
        $this->assertTrue(true);
    }

    public function test_is_sorted_by_detects_order(): void
    {
        $this->assertTrue(ArrayUtils::isSortedBy([1, 2, 2, 3], null));
        $this->assertFalse(ArrayUtils::isSortedBy([3, 1], null));
        $this->assertTrue(ArrayUtils::isSortedBy([], null));
        $this->assertTrue(ArrayUtils::isSortedBy([['k' => 'a'], ['k' => 'b']], 'k'));
        $this->assertTrue(ArrayUtils::isSortedBy([3, 2, 1], null, 'desc'));
    }
}
