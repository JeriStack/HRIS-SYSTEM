<?php

namespace Tests\Unit\DSA\Benchmarks;

use PHPUnit\Framework\TestCase;
use App\Dsa\Benchmarks\DatasetGenerator;
use App\Dsa\Arrays\ArrayUtils;

class DatasetGeneratorTest extends TestCase
{
    public function test_deterministic_generation_with_identical_seed(): void
    {
        $gen1 = new DatasetGenerator(42);
        $gen2 = new DatasetGenerator(42);

        $set1 = $gen1->generate(50, 'random');
        $set2 = $gen2->generate(50, 'random');

        $this->assertSame($set1, $set2, 'Identical random seeds must produce identical synthetic datasets.');
    }

    public function test_different_seeds_produce_different_order(): void
    {
        $gen1 = new DatasetGenerator(42);
        $gen2 = new DatasetGenerator(999);

        $set1 = $gen1->generate(50, 'random');
        $set2 = $gen2->generate(50, 'random');

        $this->assertNotSame($set1, $set2, 'Different seeds must produce different record arrangements.');
    }

    public function test_dataset_sizes_match_specifications(): void
    {
        $gen = new DatasetGenerator(123);

        foreach (DatasetGenerator::SIZES as $id => $size) {
            $data = $gen->generate($size, 'random');
            $this->assertCount($size, $data, "Dataset {$id} must contain exactly {$size} records.");
            
            // Check record fields
            $first = $data[0];
            $this->assertArrayHasKey('id', $first);
            $this->assertArrayHasKey('employee_no', $first);
            $this->assertArrayHasKey('name', $first);
            $this->assertArrayHasKey('email', $first);
            $this->assertArrayHasKey('department_id', $first);
            $this->assertArrayHasKey('salary', $first);
        }
    }

    public function test_sorted_arrangement_satisfies_is_sorted_invariant(): void
    {
        $gen = new DatasetGenerator(42);
        $data = $gen->generate(100, 'sorted', 'employee_no');

        $this->assertTrue(
            ArrayUtils::isSortedBy($data, 'employee_no', ArrayUtils::ASC),
            'Sorted arrangement must be strictly ascending by employee_no.'
        );
    }

    public function test_reverse_sorted_arrangement(): void
    {
        $gen = new DatasetGenerator(42);
        $data = $gen->generate(100, 'reverse_sorted', 'employee_no');

        $this->assertTrue(
            ArrayUtils::isSortedBy($data, 'employee_no', ArrayUtils::DESC),
            'Reverse sorted arrangement must be strictly descending by employee_no.'
        );
    }

    public function test_target_extraction_scenarios(): void
    {
        $gen = new DatasetGenerator(42);
        $data = $gen->generate(100, 'sorted', 'employee_no');

        $targetBeginning = DatasetGenerator::getTarget($data, 'beginning', 'employee_no');
        $this->assertSame($data[0]['employee_no'], $targetBeginning);

        $targetMiddle = DatasetGenerator::getTarget($data, 'middle', 'employee_no');
        $this->assertSame($data[50]['employee_no'], $targetMiddle);

        $targetEnd = DatasetGenerator::getTarget($data, 'end', 'employee_no');
        $this->assertSame($data[99]['employee_no'], $targetEnd);

        $targetMiss = DatasetGenerator::getTarget($data, 'miss', 'employee_no');
        $this->assertSame('EMP999999_MISSING', $targetMiss);
    }
}

