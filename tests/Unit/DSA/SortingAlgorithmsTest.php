<?php

namespace Tests\Unit\DSA;

use App\Dsa\Algorithms\BubbleSort;
use App\Dsa\Algorithms\InsertionSort;
use App\Dsa\Algorithms\MergeSort;
use App\Dsa\Algorithms\QuickSort;
use App\Dsa\Algorithms\SelectionSort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Shared contract tests run against all five custom sorting algorithms via a
 * data provider. Each is compared to a trusted PHP reference (sort/usort) used
 * ONLY to compute expected results, never to perform the algorithm's own work.
 */
class SortingAlgorithmsTest extends TestCase
{
    /**
     * @return array<string, array{0:class-string}>
     */
    public static function sortClassProvider(): array
    {
        return [
            'BubbleSort'    => [BubbleSort::class],
            'SelectionSort' => [SelectionSort::class],
            'InsertionSort' => [InsertionSort::class],
            'MergeSort'     => [MergeSort::class],
            'QuickSort'     => [QuickSort::class],
        ];
    }

    /** @param class-string $cls */
    #[DataProvider('sortClassProvider')]
    public function test_empty_array(string $cls): void
    {
        $this->assertSame([], $cls::sort([]));
    }

    /** @param class-string $cls */
    #[DataProvider('sortClassProvider')]
    public function test_single_element(string $cls): void
    {
        $this->assertSame([42], $cls::sort([42]));
    }

    /** @param class-string $cls */
    #[DataProvider('sortClassProvider')]
    public function test_already_sorted(string $cls): void
    {
        $input = range(1, 10);
        $this->assertSame(range(1, 10), $cls::sort($input));
    }

    /** @param class-string $cls */
    #[DataProvider('sortClassProvider')]
    public function test_reverse_sorted(string $cls): void
    {
        $this->assertSame([1, 2, 3, 4, 5], $cls::sort([5, 4, 3, 2, 1]));
    }

    /** @param class-string $cls */
    #[DataProvider('sortClassProvider')]
    public function test_duplicates(string $cls): void
    {
        $this->assertSame([1, 1, 3, 5, 5], $cls::sort([5, 1, 5, 1, 3]));
    }

    /** @param class-string $cls */
    #[DataProvider('sortClassProvider')]
    public function test_negative_and_positive_numbers(string $cls): void
    {
        $this->assertSame([-5, -1, 0, 2, 3], $cls::sort([3, -1, -5, 2, 0]));
    }

    /** @param class-string $cls */
    #[DataProvider('sortClassProvider')]
    public function test_string_comparison_case_insensitive(string $cls): void
    {
        // Case-insensitive ordering per the shared comparator
        $this->assertSame(['alpha', 'Bravo', 'charlie'], $cls::sort(['charlie', 'alpha', 'Bravo']));
    }

    /** @param class-string $cls */
    #[DataProvider('sortClassProvider')]
    public function test_ascending_matches_reference(string $cls): void
    {
        $input = [55, 12, 89, 42, 67, 3, 28, 99, 15, 42, 3];
        $reference = $input;
        sort($reference, SORT_NUMERIC);
        $this->assertSame($reference, $cls::sort($input));
    }

    /** @param class-string $cls */
    #[DataProvider('sortClassProvider')]
    public function test_descending_matches_reference(string $cls): void
    {
        $input = [55, 12, 89, 42, 67, 3, 28, 99, 15];
        $reference = $input;
        rsort($reference, SORT_NUMERIC);
        $this->assertSame($reference, $cls::sort($input, null, 'desc'));
    }

    /** @param class-string $cls */
    #[DataProvider('sortClassProvider')]
    public function test_input_array_is_not_mutated(string $cls): void
    {
        $input = [3, 1, 2];
        $cls::sort($input);
        $this->assertSame([3, 1, 2], $input, "{$cls} must not mutate the caller's array.");
    }

    /** @param class-string $cls */
    #[DataProvider('sortClassProvider')]
    public function test_sorts_employee_records_by_employee_no(string $cls): void
    {
        $records = $this->employeeFixture();
        $sorted = $cls::sort($records, 'employee_no', 'asc');
        $this->assertSame(
            ['EMP-001', 'EMP-002', 'EMP-003', 'EMP-004'],
            array_column($sorted, 'employee_no')
        );
    }

    /** @param class-string $cls */
    #[DataProvider('sortClassProvider')]
    public function test_sorts_employee_records_by_last_name_desc(string $cls): void
    {
        $records = $this->employeeFixture();
        $sorted = $cls::sort($records, 'last_name', 'desc');
        $names = array_map('strtolower', array_column($sorted, 'last_name'));
        $sortedCopy = $names;
        rsort($sortedCopy, SORT_STRING);
        $this->assertSame($sortedCopy, $names);
    }

    /** @param class-string $cls */
    #[DataProvider('sortClassProvider')]
    public function test_no_records_lost_or_duplicated_and_records_intact(string $cls): void
    {
        $records = $this->employeeFixture();
        $sorted = $cls::sort($records, 'employee_no', 'asc');

        $this->assertCount(count($records), $sorted, 'No records may be lost.');

        // Every original record must still be present exactly once (by id)
        $originalIds = array_column($records, 'id');
        $sortedIds = array_column($sorted, 'id');
        sort($originalIds);
        sort($sortedIds);
        $this->assertSame($originalIds, $sortedIds, 'No records may be dropped or duplicated.');

        // Each record's full contents remain intact (not just the sort key)
        $byId = [];
        foreach ($records as $rec) {
            $byId[$rec['id']] = $rec;
        }
        foreach ($sorted as $rec) {
            $this->assertSame($byId[$rec['id']], $rec, 'Record contents must be preserved verbatim.');
        }
    }

    /** @param class-string $cls */
    #[DataProvider('sortClassProvider')]
    public function test_null_field_values_sort_last_ascending(string $cls): void
    {
        $records = [
            ['id' => 1, 'dept' => 'Finance'],
            ['id' => 2, 'dept' => null],
            ['id' => 3, 'dept' => 'Admin'],
        ];
        $sorted = $cls::sort($records, 'dept', 'asc');
        $this->assertSame([3, 1, 2], array_column($sorted, 'id'));
    }

    /** @param class-string $cls */
    #[DataProvider('sortClassProvider')]
    public function test_large_random_dataset_matches_reference(string $cls): void
    {
        mt_srand(12345);
        $input = [];
        for ($i = 0; $i < 300; $i++) {
            $input[] = mt_rand(0, 1000);
        }
        $reference = $input;
        sort($reference, SORT_NUMERIC);
        $this->assertSame($reference, $cls::sort($input));
    }

    public function test_merge_and_insertion_and_bubble_are_stable(): void
    {
        // Equal keys must keep input order for the stable algorithms.
        $records = [
            ['id' => 1, 'k' => 'a'],
            ['id' => 2, 'k' => 'a'],
            ['id' => 3, 'k' => 'a'],
        ];
        foreach ([MergeSort::class, InsertionSort::class, BubbleSort::class] as $cls) {
            $sorted = $cls::sort($records, 'k', 'asc');
            $this->assertSame([1, 2, 3], array_column($sorted, 'id'), "{$cls} must be stable.");
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function employeeFixture(): array
    {
        return [
            ['id' => 30, 'employee_no' => 'EMP-003', 'last_name' => 'Zamora'],
            ['id' => 10, 'employee_no' => 'EMP-001', 'last_name' => 'alvarez'],
            ['id' => 20, 'employee_no' => 'EMP-002', 'last_name' => 'Alvarez'],
            ['id' => 40, 'employee_no' => 'EMP-004', 'last_name' => 'Mendoza'],
        ];
    }
}
