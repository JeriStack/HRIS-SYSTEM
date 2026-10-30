# HRIS-SYSTEM — DSA Benchmarking Framework
## Comprehensive Performance Analysis and Empirical Complexity Evaluation

---

## 1. Overview

The **HRIS-SYSTEM Benchmarking Framework** provides automated, reproducible, nanosecond-precision performance testing and operation counting for custom Data Structures and Algorithms (DSA) implemented within the HRIS application.

It empirically evaluates:
- **Search Algorithms:** Linear Search, Binary Search, Hash Table (`HashTable`).
- **Sorting Algorithms:** Bubble Sort, Selection Sort, Insertion Sort, Merge Sort, Quick Sort.
- **Scales:** $N = 10, 100, 500, 1,000, 5,000, 10,000$ synthetic employee records.
- **Input Topologies:** Random, Sorted, Reverse Sorted, Nearly Sorted.
- **Search Target Scenarios:** Beginning, Middle, End, Miss.
- **Operational Metrics:** Precise comparison counts, element swaps, array movements/assignments, hash bucket probes, recursion depth, monotonic CPU time (`hrtime(true)`), and memory footprint.

---

## 2. Directory Architecture

```
benchmarks/
├── README.md                      # This documentation guide
├── run.php                        # Executable CLI benchmark entry point
└── results/
    ├── raw/
    │   ├── benchmark_results.json # Full JSON structured results with environment metadata
    │   └── benchmark_results.csv  # Flat tabular CSV for spreadsheets and data tools
    ├── tables/
    │   └── benchmark_tables.md    # Markdown tables for documentation and reports
    └── graphs/
        ├── chart_search_execution_time.svg   # Vector plot: Search latency vs Dataset scale
        ├── chart_sorting_execution_time.svg  # Vector plot: Sorting execution time comparison
        └── chart_sorting_arrangements.svg    # Vector plot: Impact of input topology
```

Core benchmarking engine components reside in `app/DSA/benchmarks/`:
- `DatasetGenerator.php`: Seeded, deterministic synthetic employee generator.
- `OperationCounter.php`: Atomic operation counter passed by reference.
- `InstrumentedAlgorithms.php`: Faithful instrumented algorithmic implementations.
- `BenchmarkResult.php`: Value object calculating statistical medians, means, and std deviations.
- `BenchmarkRunner.php`: Orchestration engine with adaptive repetitions and monotonic timing.
- `BenchmarkReporter.php`: Multi-format exporter for JSON, CSV, Markdown, and SVG.

---

## 3. Running Benchmarks

### 3.1 Quick Start (Full Suite)

To execute the entire benchmark suite across all dataset sizes ($N=10$ to $N=10,000$), searching scenarios, and sorting topologies:

```bash
php benchmarks/run.php
```

### 3.2 CLI Options and Flags

The CLI runner supports flexible flags for targeted profiling:

| Flag | Description | Default | Example |
|---|---|---|---|
| `--mode=<mode>` | Filter workload: `all`, `search`, or `sort` | `all` | `php benchmarks/run.php --mode=search` |
| `--sizes=<sizes>` | Comma-separated dataset scales to evaluate | `10,100,500,1000,5000,10000` | `php benchmarks/run.php --sizes=10,100,500` |
| `--seed=<int>` | PRNG seed for deterministic dataset generation | `42` | `php benchmarks/run.php --seed=12345` |
| `--output=<dir>` | Target output directory for results and charts | `benchmarks/results` | `php benchmarks/run.php --output=custom_results` |
| `--help`, `-h` | Display help and usage instructions | — | `php benchmarks/run.php --help` |

### 3.3 Targeted Examples

**Run only searching benchmarks on smaller datasets for fast feedback:**
```bash
php benchmarks/run.php --mode=search --sizes=10,100,500,1000
```

**Run only sorting benchmarks on small and medium datasets:**
```bash
php benchmarks/run.php --mode=sort --sizes=100,500,1000
```

---

## 4. Automated Testing

The benchmark suite includes comprehensive PHPUnit tests in `tests/Unit/DSA/Benchmarks/`:

```bash
php vendor/bin/phpunit tests/Unit/DSA/Benchmarks/
```

Test coverage verifies:
1. `DatasetGeneratorTest`:
   - Determinism given identical seeds.
   - Correct generation of all 6 dataset sizes.
   - Schema conformance with `App\Models\Employee`.
   - Verification of `sorted`, `reverse_sorted`, and `nearly_sorted` topological orderings.
   - Target extraction for `beginning`, `middle`, `end`, and guaranteed `miss`.
2. `BenchmarkExecutionTest`:
   - Execution correctness for all search algorithms with operation counting.
   - Execution correctness and sorted order assertions for all 5 sorting algorithms.
   - Metric calculation verification in `BenchmarkResult` (median, mean, min, max, stdDev).
   - Multi-format exporter verification (JSON, CSV, Markdown, SVG).

---

## 5. Metrics and Evaluation Standards

- **Comparisons:** Measured every time two employee keys are evaluated via `ArrayUtils::compareValues()`.
- **Swaps:** Measured whenever two array elements are exchanged in-place.
- **Movements:** Measured whenever array elements are shifted, assigned, or copied to auxiliary arrays.
- **Probes:** Measured whenever an index in `HashTable` is inspected during lookup or collision resolution.
- **Timing:** High-resolution monotonic clock using PHP's native `hrtime(true)` (nanosecond precision).
- **Correctness Assurance:** Every sorting run is asserted post-execution using `ArrayUtils::isSortedBy()` and multiset identity checks to guarantee zero dropped or corrupted records.

