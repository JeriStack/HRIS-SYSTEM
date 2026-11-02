<?php

namespace Tests\Unit\DSA\Benchmarks;

use PHPUnit\Framework\TestCase;
use App\Dsa\Benchmarks\DatasetGenerator;
use App\Dsa\Benchmarks\InstrumentedAlgorithms;
use App\Dsa\Benchmarks\OperationCounter;
use App\Dsa\Benchmarks\BenchmarkResult;
use App\Dsa\Arrays\ArrayUtils;

class BenchmarkExecutionTest extends TestCase
{
    public function test_instrumented_linear_search_operation_counting(): void
    {
        $gen = new DatasetGenerator(42);
        $data = $gen->generate(10, 'random', 'employee_no');

        // Target at index 0 requires 1 comparison
        $counter = new OperationCounter();
        $targetFirst = $data[0]['employee_no'];
        $idx = InstrumentedAlgorithms::linearSearch($data, $targetFirst, 'employee_no', $counter);
        $this->assertSame(0, $idx);
        $this->assertSame(1, $counter->comparisons);

        // Missing target requires 10 comparisons
        $counter->reset();
        $idxMiss = InstrumentedAlgorithms::linearSearch($data, 'NONEXISTENT', 'employee_no', $counter);
        $this->assertFalse($idxMiss);
        $this->assertSame(10, $counter->comparisons);
    }

    public function test_instrumented_binary_search_operation_counting(): void
    {
        $gen = new DatasetGenerator(42);
        $data = $gen->generate(100, 'sorted', 'employee_no');

        $counter = new OperationCounter();
        $target = $data[50]['employee_no'];
        $idx = InstrumentedAlgorithms::binarySearch($data, $target, 'employee_no', $counter);

        $this->assertSame(50, $idx);
        // In array of 100 items, binary search requires <= 2 * ceil(log2(100)) ~ 14 comparisons
        $this->assertLessThanOrEqual(16, $counter->comparisons);
    }

    public function test_instrumented_sorting_algorithms_correctness_and_counts(): void
    {
        $gen = new DatasetGenerator(42);
        $data = $gen->generate(20, 'random', 'employee_no');

        // 1. BubbleSort
        $counter = new OperationCounter();
        $bubbleSorted = InstrumentedAlgorithms::bubbleSort($data, 'employee_no', $counter);
        $this->assertTrue(ArrayUtils::isSortedBy($bubbleSorted, 'employee_no', ArrayUtils::ASC));
        $this->assertGreaterThan(0, $counter->comparisons);

        // 2. SelectionSort
        $counter->reset();
        $selectionSorted = InstrumentedAlgorithms::selectionSort($data, 'employee_no', $counter);
        $this->assertTrue(ArrayUtils::isSortedBy($selectionSorted, 'employee_no', ArrayUtils::ASC));
        $this->assertGreaterThan(0, $counter->comparisons);

        // 3. InsertionSort
        $counter->reset();
        $insertionSorted = InstrumentedAlgorithms::insertionSort($data, 'employee_no', $counter);
        $this->assertTrue(ArrayUtils::isSortedBy($insertionSorted, 'employee_no', ArrayUtils::ASC));
        $this->assertGreaterThan(0, $counter->comparisons);

        // 4. MergeSort
        $counter->reset();
        $mergeSorted = InstrumentedAlgorithms::mergeSort($data, 'employee_no', $counter);
        $this->assertTrue(ArrayUtils::isSortedBy($mergeSorted, 'employee_no', ArrayUtils::ASC));
        $this->assertGreaterThan(0, $counter->moves);

        // 5. QuickSort
        $counter->reset();
        $quickSorted = InstrumentedAlgorithms::quickSort($data, 'employee_no', $counter);
        $this->assertTrue(ArrayUtils::isSortedBy($quickSorted, 'employee_no', ArrayUtils::ASC));
        $this->assertGreaterThan(0, $counter->partitions);
    }

    public function test_benchmark_result_statistical_methods(): void
    {
        $result = new BenchmarkResult([
            'algorithm'      => 'QuickSort',
            'dataset_id'     => 'DS-100',
            'dataset_size'   => 100,
            'median_time_ns' => 2_500_000.0, // 2.5 ms
        ]);

        $this->assertSame(2.5, $result->getMedianTimeMs());
        $this->assertSame(2500.0, $result->getMedianTimeUs());
    }
}

