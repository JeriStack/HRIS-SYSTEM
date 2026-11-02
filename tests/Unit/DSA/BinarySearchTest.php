<?php

namespace Tests\Unit\DSA;

use App\Dsa\Algorithms\BinarySearch;
use App\Dsa\Algorithms\MergeSort;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BinarySearchTest extends TestCase
{
    public function test_first_middle_last_match_scalar(): void
    {
        $data = [10, 20, 30, 40, 50, 60, 70, 80, 90];
        $this->assertSame(0, BinarySearch::search($data, 10));
        $this->assertSame(4, BinarySearch::search($data, 50));
        $this->assertSame(8, BinarySearch::search($data, 90));
    }

    public function test_missing_target_returns_false(): void
    {
        $this->assertFalse(BinarySearch::search([10, 20, 30, 40, 50], 55));
        // Smaller than first and larger than last
        $this->assertFalse(BinarySearch::search([10, 20, 30], 5));
        $this->assertFalse(BinarySearch::search([10, 20, 30], 99));
    }

    public function test_empty_and_single_element(): void
    {
        $this->assertFalse(BinarySearch::search([], 5));
        $this->assertSame(0, BinarySearch::search([7], 7));
        $this->assertFalse(BinarySearch::search([7], 8));
    }

    public function test_numeric_string_keys(): void
    {
        $data = ['2', '10', '30', '200'];
        // Already in numeric order; binary search matches numerically
        $this->assertSame(1, BinarySearch::search($data, '10'));
        $this->assertSame(3, BinarySearch::search($data, 200));
    }

    public function test_record_lookup_after_sorting_by_field(): void
    {
        $records = [
            ['id' => 3, 'employee_no' => 'EMP-003'],
            ['id' => 1, 'employee_no' => 'EMP-001'],
            ['id' => 2, 'employee_no' => 'EMP-002'],
        ];
        $sorted = MergeSort::sort($records, 'employee_no', 'asc');
        $idx = BinarySearch::search($sorted, 'EMP-002', 'employee_no');
        $this->assertNotFalse($idx);
        $this->assertSame('EMP-002', $sorted[$idx]['employee_no']);
    }

    public function test_search_first_returns_lowest_index_on_duplicates(): void
    {
        $dupes = [['k' => 1], ['k' => 2], ['k' => 2], ['k' => 2], ['k' => 3]];
        $this->assertSame(1, BinarySearch::searchFirst($dupes, 2, 'k'));
        $this->assertSame(0, BinarySearch::searchFirst($dupes, 1, 'k'));
        $this->assertSame(4, BinarySearch::searchFirst($dupes, 3, 'k'));
        $this->assertFalse(BinarySearch::searchFirst($dupes, 9, 'k'));
    }

    public function test_descending_sorted_input(): void
    {
        $data = [90, 80, 70, 60, 50];
        $idx = BinarySearch::search($data, 70, null, 'desc');
        $this->assertSame(2, $idx);
        $this->assertFalse(BinarySearch::search($data, 55, null, 'desc'));
    }

    public function test_assert_sorted_rejects_unsorted_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BinarySearch::search([5, 1, 3, 2], 3, null, 'asc', true);
    }

    public function test_assert_sorted_allows_sorted_input(): void
    {
        $this->assertSame(2, BinarySearch::search([1, 2, 3, 4], 3, null, 'asc', true));
    }

    public function test_finds_every_value_in_sorted_array(): void
    {
        $data = range(1, 50);
        foreach ($data as $i => $value) {
            $this->assertSame($i, BinarySearch::search($data, $value));
        }
    }
}
