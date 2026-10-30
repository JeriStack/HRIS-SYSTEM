<?php
/**
 * Benchmark Runner Engine
 * Coordinates execution across dataset sizes, scenarios, timing, and correctness validation
 * Employee HRIS & Leave Management System — Phase 15
 */

namespace App\Dsa\Benchmarks;

use App\Dsa\Algorithms\LinearSearch;
use App\Dsa\Algorithms\BinarySearch;
use App\Dsa\Algorithms\BubbleSort;
use App\Dsa\Algorithms\SelectionSort;
use App\Dsa\Algorithms\InsertionSort;
use App\Dsa\Algorithms\MergeSort;
use App\Dsa\Algorithms\QuickSort;
use App\Dsa\Structures\HashTable;
use App\Dsa\Arrays\ArrayUtils;

class BenchmarkRunner
{
    private DatasetGenerator $generator;
    private array $sizes;
    private int $seed;
    /** @var callable|null */
    private $progressCallback = null;

    public function __construct(int $seed = 42, ?array $customSizes = null)
    {
        $this->seed = $seed;
        $this->generator = new DatasetGenerator($seed);
        $this->sizes = $customSizes ?? DatasetGenerator::SIZES;
    }

    public function setProgressCallback(?callable $callback): void
    {
        $this->progressCallback = $callback;
    }

    private function notify(string $message): void
    {
        if ($this->progressCallback !== null) {
            ($this->progressCallback)($message);
        }
    }

    /**
     * Run all search and sorting benchmarks across configured dataset sizes.
     *
     * @return list<BenchmarkResult>
     */
    public function runAll(): array
    {
        $results = [];

        $this->notify("Starting search benchmarks across " . count($this->sizes) . " dataset sizes...");
        $searchResults = $this->runSearchBenchmarks();
        $results = array_merge($results, $searchResults);

        $this->notify("Starting sorting benchmarks across " . count($this->sizes) . " dataset sizes...");
        $sortResults = $this->runSortBenchmarks();
        $results = array_merge($results, $sortResults);

        return $results;
    }

    // =========================================================================
    // SEARCH BENCHMARKS
    // =========================================================================

    public function runSearchBenchmarks(): array
    {
        $results = [];
        $scenarios = ['beginning', 'middle', 'end', 'miss'];

        foreach ($this->sizes as $datasetId => $size) {
            $this->notify("Search benchmarks on {$datasetId} (N={$size})...");

            // Generate deterministic dataset
            $randomDataset = $this->generator->generate($size, 'random', 'employee_no');
            $sortedDataset = $this->generator->generate($size, 'sorted', 'employee_no');

            // Construct Hash Table once
            $hashTable = new HashTable(max(32, (int)($size * 1.5)));
            foreach ($randomDataset as $emp) {
                $hashTable->set($emp['employee_no'], $emp);
            }

            foreach ($scenarios as $scenario) {
                // 1. Linear Search (on random dataset)
                $target = DatasetGenerator::getTarget($randomDataset, $scenario, 'employee_no');
                $results[] = $this->benchmarkLinearSearch($datasetId, $size, $randomDataset, $target, $scenario);

                // 2. Binary Search (strictly on sorted dataset)
                $sortedTarget = DatasetGenerator::getTarget($sortedDataset, $scenario, 'employee_no');
                $results[] = $this->benchmarkBinarySearch($datasetId, $size, $sortedDataset, $sortedTarget, $scenario);

                // 3. Hash Table Lookup (on populated hash table)
                $results[] = $this->benchmarkHashTable($datasetId, $size, $hashTable, $target, $scenario);
            }
        }

        return $results;
    }

    private function benchmarkLinearSearch(string $datasetId, int $size, array $dataset, mixed $target, string $scenario): BenchmarkResult
    {
        $reps = $this->getRepetitions('LinearSearch', $size);
        $durations = [];

        // Warm-up
        LinearSearch::search($dataset, $target, 'employee_no');

        $isCorrect = true;
        $memBefore = memory_get_usage();

        for ($r = 0; $r < $reps; $r++) {
            $t0 = hrtime(true);
            $idx = LinearSearch::search($dataset, $target, 'employee_no');
            $t1 = hrtime(true);

            $durations[] = (float)($t1 - $t0);

            if ($scenario === 'miss') {
                if ($idx !== false) $isCorrect = false;
            } else {
                if ($idx === false || ($dataset[$idx]['employee_no'] ?? '') !== $target) $isCorrect = false;
            }
        }

        $memPeak = memory_get_peak_usage() - $memBefore;

        // Instrumented run for exact operation counts
        $counter = new OperationCounter();
        InstrumentedAlgorithms::linearSearch($dataset, $target, 'employee_no', $counter);

        return $this->createResult([
            'category'          => 'search',
            'algorithm'         => 'LinearSearch',
            'dataset_id'        => $datasetId,
            'dataset_size'      => $size,
            'arrangement'       => 'random',
            'scenario'          => $scenario,
            'repetitions'       => $reps,
            'durations'         => $durations,
            'counter'           => $counter,
            'memory_peak_bytes' => max(0, $memPeak),
            'is_correct'        => $isCorrect,
        ]);
    }

    private function benchmarkBinarySearch(string $datasetId, int $size, array $sortedDataset, mixed $target, string $scenario): BenchmarkResult
    {
        $reps = $this->getRepetitions('BinarySearch', $size);
        $durations = [];

        // Warm-up
        BinarySearch::search($sortedDataset, $target, 'employee_no', ArrayUtils::ASC, false);

        $isCorrect = true;
        $memBefore = memory_get_usage();

        for ($r = 0; $r < $reps; $r++) {
            $t0 = hrtime(true);
            $idx = BinarySearch::search($sortedDataset, $target, 'employee_no', ArrayUtils::ASC, false);
            $t1 = hrtime(true);

            $durations[] = (float)($t1 - $t0);

            if ($scenario === 'miss') {
                if ($idx !== false) $isCorrect = false;
            } else {
                if ($idx === false || ($sortedDataset[$idx]['employee_no'] ?? '') !== $target) $isCorrect = false;
            }
        }

        $memPeak = memory_get_peak_usage() - $memBefore;

        // Instrumented run
        $counter = new OperationCounter();
        InstrumentedAlgorithms::binarySearch($sortedDataset, $target, 'employee_no', $counter, ArrayUtils::ASC);

        return $this->createResult([
            'category'          => 'search',
            'algorithm'         => 'BinarySearch',
            'dataset_id'        => $datasetId,
            'dataset_size'      => $size,
            'arrangement'       => 'sorted',
            'scenario'          => $scenario,
            'repetitions'       => $reps,
            'durations'         => $durations,
            'counter'           => $counter,
            'memory_peak_bytes' => max(0, $memPeak),
            'is_correct'        => $isCorrect,
        ]);
    }

    private function benchmarkHashTable(string $datasetId, int $size, HashTable $table, mixed $target, string $scenario): BenchmarkResult
    {
        $reps = $this->getRepetitions('HashTable', $size);
        $durations = [];
        $key = (string)$target;

        // Warm-up
        $table->get($key);

        $isCorrect = true;
        $memBefore = memory_get_usage();

        for ($r = 0; $r < $reps; $r++) {
            $t0 = hrtime(true);
            $record = $table->get($key);
            $t1 = hrtime(true);

            $durations[] = (float)($t1 - $t0);

            if ($scenario === 'miss') {
                if ($record !== null) $isCorrect = false;
            } else {
                if ($record === null || ($record['employee_no'] ?? '') !== $key) $isCorrect = false;
            }
        }

        $memPeak = memory_get_peak_usage() - $memBefore;

        // Instrumented run
        $counter = new OperationCounter();
        InstrumentedAlgorithms::hashTableLookup($table, $key, $counter);

        return $this->createResult([
            'category'          => 'search',
            'algorithm'         => 'HashTableLookup',
            'dataset_id'        => $datasetId,
            'dataset_size'      => $size,
            'arrangement'       => 'random',
            'scenario'          => $scenario,
            'repetitions'       => $reps,
            'durations'         => $durations,
            'counter'           => $counter,
            'memory_peak_bytes' => max(0, $memPeak),
            'is_correct'        => $isCorrect,
        ]);
    }

    // =========================================================================
    // SORTING BENCHMARKS
    // =========================================================================

    public function runSortBenchmarks(): array
    {
        $results = [];
        $algorithms = ['BubbleSort', 'SelectionSort', 'InsertionSort', 'MergeSort', 'QuickSort'];
        $arrangements = ['random', 'sorted', 'reverse_sorted', 'nearly_sorted'];

        foreach ($this->sizes as $datasetId => $size) {
            foreach ($arrangements as $arrangement) {
                // Generate pristine dataset copy
                $dataset = $this->generator->generate($size, $arrangement, 'employee_no');

                foreach ($algorithms as $algo) {
                    $this->notify("Sorting: {$algo} on {$datasetId} (N={$size}, arrangement={$arrangement})...");
                    $results[] = $this->benchmarkSortAlgorithm($algo, $datasetId, $size, $dataset, $arrangement);
                }
            }
        }

        return $results;
    }

    private function benchmarkSortAlgorithm(string $algorithm, string $datasetId, int $size, array $dataset, string $arrangement): BenchmarkResult
    {
        $isQuadratic = in_array($algorithm, ['BubbleSort', 'SelectionSort', 'InsertionSort'], true);

        // For large datasets (N >= 5000) on quadratic algorithms, avoid redundant 50M-iteration passes:
        // A single instrumented pass simultaneously collects high-resolution duration, correctness, and exact operations.
        if ($size >= 5000 && $isQuadratic && in_array($arrangement, ['random', 'reverse_sorted'], true)) {
            $input = $dataset;
            $counter = new OperationCounter();
            $memBefore = memory_get_usage();

            $t0 = hrtime(true);
            $sorted = self::dispatchInstrumentedSort($algorithm, $input, $counter);
            $t1 = hrtime(true);

            $durations = [(float)($t1 - $t0)];
            $isCorrect = (count($sorted) === count($dataset)) && ArrayUtils::isSortedBy($sorted, 'employee_no', ArrayUtils::ASC);
            $memPeak = memory_get_peak_usage() - $memBefore;

            return $this->createResult([
                'category'          => 'sort',
                'algorithm'         => $algorithm,
                'dataset_id'        => $datasetId,
                'dataset_size'      => $size,
                'arrangement'       => $arrangement,
                'scenario'          => 'n/a',
                'repetitions'       => 1,
                'durations'         => $durations,
                'counter'           => $counter,
                'memory_peak_bytes' => max(0, $memPeak),
                'is_correct'        => $isCorrect,
            ]);
        }

        $reps = $this->getRepetitions($algorithm, $size);
        $durations = [];
        $isCorrect = true;

        // Warm-up (1 run) for smaller or fast runs
        if ($size <= 1000 || !$isQuadratic) {
            self::dispatchSort($algorithm, $dataset);
        }

        $memBefore = memory_get_usage();

        for ($r = 0; $r < $reps; $r++) {
            // Fresh copy for each repetition
            $input = $dataset;

            $t0 = hrtime(true);
            $sorted = self::dispatchSort($algorithm, $input);
            $t1 = hrtime(true);

            $durations[] = (float)($t1 - $t0);

            // Correctness check on first repetition
            if ($r === 0) {
                $isCorrect = (count($sorted) === count($dataset)) && ArrayUtils::isSortedBy($sorted, 'employee_no', ArrayUtils::ASC);
            }
        }

        $memPeak = memory_get_peak_usage() - $memBefore;

        // Instrumented run for exact operation counts
        $counter = new OperationCounter();
        self::dispatchInstrumentedSort($algorithm, $dataset, $counter);

        return $this->createResult([
            'category'          => 'sort',
            'algorithm'         => $algorithm,
            'dataset_id'        => $datasetId,
            'dataset_size'      => $size,
            'arrangement'       => $arrangement,
            'scenario'          => 'n/a',
            'repetitions'       => $reps,
            'durations'         => $durations,
            'counter'           => $counter,
            'memory_peak_bytes' => max(0, $memPeak),
            'is_correct'        => $isCorrect,
        ]);
    }

    private static function dispatchSort(string $algorithm, array $items): array
    {
        return match ($algorithm) {
            'BubbleSort'    => BubbleSort::sort($items, 'employee_no', ArrayUtils::ASC),
            'SelectionSort' => SelectionSort::sort($items, 'employee_no', ArrayUtils::ASC),
            'InsertionSort' => InsertionSort::sort($items, 'employee_no', ArrayUtils::ASC),
            'MergeSort'     => MergeSort::sort($items, 'employee_no', ArrayUtils::ASC),
            'QuickSort'     => QuickSort::sort($items, 'employee_no', ArrayUtils::ASC),
            default         => $items,
        };
    }

    private static function dispatchInstrumentedSort(string $algorithm, array $items, OperationCounter $counter): array
    {
        return match ($algorithm) {
            'BubbleSort'    => InstrumentedAlgorithms::bubbleSort($items, 'employee_no', $counter, ArrayUtils::ASC),
            'SelectionSort' => InstrumentedAlgorithms::selectionSort($items, 'employee_no', $counter, ArrayUtils::ASC),
            'InsertionSort' => InstrumentedAlgorithms::insertionSort($items, 'employee_no', $counter, ArrayUtils::ASC),
            'MergeSort'     => InstrumentedAlgorithms::mergeSort($items, 'employee_no', $counter, ArrayUtils::ASC),
            'QuickSort'     => InstrumentedAlgorithms::quickSort($items, 'employee_no', $counter, ArrayUtils::ASC),
            default         => $items,
        };
    }

    /**
     * Compute adaptive repetition counts balancing statistical confidence and execution runtime.
     */
    private function getRepetitions(string $algorithm, int $size): int
    {
        if (in_array($algorithm, ['BinarySearch', 'HashTable', 'HashTableLookup'], true)) {
            return 50;
        }

        if ($algorithm === 'LinearSearch') {
            return ($size <= 500) ? 50 : 20;
        }

        // Sorting algorithms
        if (in_array($algorithm, ['MergeSort', 'QuickSort'], true)) {
            if ($size <= 100) return 30;
            if ($size <= 1000) return 15;
            return 5;
        }

        // Quadratic sorting algorithms (BubbleSort, SelectionSort, InsertionSort)
        if ($size <= 100) return 20;
        if ($size <= 500) return 10;
        if ($size <= 1000) return 5;
        if ($size <= 5000) return 1;
        return 1; // N = 10,000
    }

    /**
     * Build BenchmarkResult from collected statistical samples.
     */
    private function createResult(array $params): BenchmarkResult
    {
        $durations = $params['durations'];
        sort($durations);
        $count = count($durations);

        $min = $durations[0];
        $max = $durations[$count - 1];
        $sum = array_sum($durations);
        $mean = $sum / $count;

        // Median
        $mid = intdiv($count, 2);
        $median = ($count % 2 === 0) ? (($durations[$mid - 1] + $durations[$mid]) / 2.0) : $durations[$mid];

        // Standard Deviation
        $variance = 0.0;
        foreach ($durations as $d) {
            $variance += pow($d - $mean, 2);
        }
        $stdDev = ($count > 1) ? sqrt($variance / ($count - 1)) : 0.0;

        /** @var OperationCounter $counter */
        $counter = $params['counter'];

        return new BenchmarkResult([
            'run_id'                 => uniqid('bm_', true),
            'timestamp'              => date('c'),
            'category'               => $params['category'],
            'algorithm'              => $params['algorithm'],
            'dataset_id'             => $params['dataset_id'],
            'dataset_size'           => $params['dataset_size'],
            'arrangement'            => $params['arrangement'],
            'scenario'               => $params['scenario'],
            'repetitions'            => $params['repetitions'],
            'min_time_ns'            => $min,
            'median_time_ns'         => $median,
            'mean_time_ns'           => $mean,
            'max_time_ns'            => $max,
            'std_dev_ns'             => $stdDev,
            'comparisons'            => $counter->comparisons,
            'swaps'                  => $counter->swaps,
            'moves'                  => $counter->moves,
            'probes'                 => $counter->probes,
            'memory_peak_bytes'      => $params['memory_peak_bytes'],
            'memory_allocated_bytes' => memory_get_usage(),
            'is_correct'             => $params['is_correct'],
        ]);
    }
}
