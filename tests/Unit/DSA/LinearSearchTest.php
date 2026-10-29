<?php

namespace Tests\Unit\DSA;

use App\Dsa\Algorithms\LinearSearch;
use PHPUnit\Framework\TestCase;

class LinearSearchTest extends TestCase
{
    /** @return list<array<string,mixed>> */
    private function fixture(): array
    {
        return [
            ['id' => 1, 'employee_no' => 'EMP-001', 'name' => 'Alex Santos', 'status' => 'Active'],
            ['id' => 2, 'employee_no' => 'EMP-002', 'name' => 'Jamie Reyes', 'status' => 'Inactive'],
            ['id' => 3, 'employee_no' => 'EMP-003', 'name' => 'Alexis Cruz', 'status' => 'Active'],
        ];
    }

    public function test_first_middle_last_match_scalar(): void
    {
        $data = [10, 20, 30, 40, 50];
        $this->assertSame(0, LinearSearch::search($data, 10));
        $this->assertSame(2, LinearSearch::search($data, 30));
        $this->assertSame(4, LinearSearch::search($data, 50));
    }

    public function test_missing_target_returns_false(): void
    {
        $this->assertFalse(LinearSearch::search([1, 2, 3], 99));
    }

    public function test_empty_dataset_returns_false(): void
    {
        $this->assertFalse(LinearSearch::search([], 1));
    }

    public function test_employee_number_lookup_by_field(): void
    {
        $idx = LinearSearch::search($this->fixture(), 'EMP-002', 'employee_no');
        $this->assertSame(1, $idx);
    }

    public function test_case_insensitive_exact_match(): void
    {
        $this->assertSame(0, LinearSearch::search($this->fixture(), 'emp-001', 'employee_no'));
    }

    public function test_missing_field_is_skipped_safely(): void
    {
        // Second record lacks key 'a'; it must be skipped, not error.
        $data = [['a' => 1], ['b' => 2]];
        $this->assertSame(0, LinearSearch::search($data, 1, 'a'));
        $this->assertFalse(LinearSearch::search($data, 2, 'a'));
    }

    public function test_search_all_returns_every_match(): void
    {
        $matches = LinearSearch::searchAll($this->fixture(), 'active', 'status');
        $this->assertCount(2, $matches);
        $this->assertSame([1, 3], array_column($matches, 'id'));
    }

    public function test_search_all_empty_when_none_match(): void
    {
        $this->assertSame([], LinearSearch::searchAll($this->fixture(), 'Terminated', 'status'));
    }

    public function test_search_contains_partial_case_insensitive(): void
    {
        // "alex" matches both "Alex Santos" and "Alexis Cruz"
        $matches = LinearSearch::searchContains($this->fixture(), 'alex', 'name');
        $this->assertCount(2, $matches);
        $this->assertSame([1, 3], array_column($matches, 'id'));
    }

    public function test_search_contains_empty_needle_returns_all(): void
    {
        $this->assertCount(3, LinearSearch::searchContains($this->fixture(), '', 'name'));
    }

    public function test_records_are_preserved_in_results(): void
    {
        $matches = LinearSearch::searchContains($this->fixture(), 'santos', 'name');
        $this->assertSame(
            ['id' => 1, 'employee_no' => 'EMP-001', 'name' => 'Alex Santos', 'status' => 'Active'],
            $matches[0]
        );
    }
}
