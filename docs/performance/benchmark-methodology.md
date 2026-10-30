# Benchmark Methodology and Evaluation Framework
## HRIS-SYSTEM — Performance Analysis and Optimization (Phase 15)

---

## 1. Executive Summary

This document establishes the scientific methodology, operational parameters, instrumentation strategy, and reproducibility guidelines for evaluating the Data Structures and Algorithms (DSA) implemented within the **HRIS-SYSTEM** (Human Resource Information System).

The primary goal of this evaluation is to empirically measure, analyze, and characterize the computational performance (time complexity, operational counts, and memory footprint) of custom search and sort algorithms across controlled dataset scales ($N = 10$ to $N = 10,000$) under varying input distributions.

---

## 2. Test Environment and System Specifications

All benchmarks were conducted directly within the host runtime environment under isolated CLI execution to eliminate network latency, HTTP overhead, and web-server buffering.

| Specification Parameter | Value / Description |
|---|---|
| **Operating System** | Windows 11 Enterprise (x64-based processor) |
| **PHP Runtime** | PHP 8.5.12 (cli) (Zend Engine v4.5.12) |
| **PHP Extensions** | Zend OPcache enabled; Xdebug / PCOV **disabled** during benchmark execution |
| **Memory Limit (`memory_limit`)** | 512 MB |
| **Time Measurement Resolution** | `hrtime(true)` Monotonic Clock (Nanosecond resolution) |
| **Execution Context** | Standalone CLI runner (`benchmarks/run.php`) |
| **Database State** | Detached from persistence layer; pure in-memory execution on synthetic models |
| **Code Isolation** | Dedicated `app/DSA/benchmarks/` namespace mirroring production algorithms |

---

## 3. Dataset Specifications and Generation Strategy

### 3.1 Dataset Sizes

The evaluation defines six standardized dataset scales representing realistic enterprise HRIS sizing:

| Dataset ID | Scale ($N$) | Operational Scenario |
|---|---|---|
| **DS-010** | 10 | Micro department, leadership council, or focused project team |
| **DS-100** | 100 | Small business, single business unit, or branch office |
| **DS-500** | 500 | Mid-sized enterprise division or large office site |
| **DS-1000** | 1,000 | Comprehensive enterprise company directory |
| **DS-5000** | 5,000 | Multi-subsidiary workforce or regional staff directory |
| **DS-10000** | 10,000 | Large enterprise conglomerate, historical archive, or bulk batch export |

### 3.2 Employee Record Schema

Each dataset consists of synthetic employee records matching the production schema of `App\Models\Employee`:

```json
{
  "id": 1042,
  "employee_number": "EMP-01042",
  "first_name": "Alexander",
  "last_name": "Wright",
  "email": "alexander.wright.1042@company.local",
  "department_name": "Engineering",
  "position_title": "Software Engineer",
  "hire_date": "2021-08-15",
  "status": "Active"
}
```

### 3.3 Random Seed and Determinism

To ensure 100% reproducibility across independent runs, all dataset generation uses PHP's deterministic pseudorandom number generator initialized with an explicit seed:
$$\text{Seed} = 42$$

The generator (`DatasetGenerator::seed(42)`) guarantees that identical employee names, attributes, and orderings are produced on every benchmark pass across test platforms.

### 3.4 Input Arrangements for Sorting

To evaluate best-case, average-case, and worst-case behaviors, datasets are arranged into four distinct topologies prior to sorting:

1. **Random (`random`):** Uniformly shuffled using deterministic Fisher-Yates / `mt_srand(42)` permutation. Represents typical ad-hoc search and filter queries.
2. **Sorted (`sorted`):** Pre-sorted in ascending lexicographical order by `last_name`. Evaluates best-case behavior for adaptive algorithms and worst-case for naive quicksort.
3. **Reverse Sorted (`reverse_sorted`):** Pre-sorted in strictly descending order by `last_name`. Represents inverse sorting queries and triggers maximal swap counts for insertion/bubble sorts.
4. **Nearly Sorted (`nearly_sorted`):** Pre-sorted with exactly 5% of adjacent elements randomly perturbed. Models real-world scenarios where an existing directory receives incremental additions.

### 3.5 Search Target Scenarios

Search algorithms are evaluated against four target locations:

1. **Beginning (`beginning`):** Target located at index $0$ of the dataset.
2. **Middle (`middle`):** Target located at index $\lfloor N / 2 \rfloor$ of the dataset.
3. **End (`end`):** Target located at index $N - 1$ of the dataset.
4. **Miss (`miss`):** Target with key `"ZZZ_NONEXISTENT_KEY"` guaranteed not to exist in the dataset.

---

## 4. Evaluated Algorithms

The benchmark evaluates the custom DSA implementations residing in `app/DSA/`:

### 4.1 Searching Algorithms
- **Linear Search (`LinearSearch`):** Sequential scan comparing each element until a match or end-of-list is reached.
- **Binary Search (`BinarySearch`):** Divide-and-conquer search operating on pre-sorted arrays, halving search space on each comparison.
- **Hash Table (`HashTable`):** Custom associative array structure utilizing DJB2 polynomial rolling hashing and open addressing with linear probing.

### 4.2 Sorting Algorithms
- **Bubble Sort (`BubbleSort`):** Iterative pairwise comparison and swap with early termination optimization (`$swapped === false`).
- **Selection Sort (`SelectionSort`):** In-place minimum element selection with single swap per outer pass.
- **Insertion Sort (`InsertionSort`):** Adaptive incremental insertion shifting elements backwards into sorted sub-arrays.
- **Merge Sort (`MergeSort`):** Divide-and-conquer recursive partitioning and linear merging guaranteeing $O(N \log N)$ stability.
- **Quick Sort (`QuickSort`):** In-place Lomuto partitioning utilizing a median-of-three pivot selection strategy to prevent $O(N^2)$ degeneration on sorted inputs.

---

## 5. Instrumentation and Metrics Collection

### 5.1 High-Resolution Monotonic Timing

Timing is measured using PHP's native `hrtime(true)` which interfaces directly with the hardware performance counter (e.g., RDTSC on x86_64). Unlike `microtime()`, `hrtime()` is strictly monotonic and immune to system NTP clock adjustments:

$$\Delta t = \text{hrtime}(true)_{\text{end}} - \text{hrtime}(true)_{\text{start}} \quad (\text{nanoseconds})$$

All raw nanoseconds are converted to microseconds ($\mu s$) and milliseconds ($ms$) with 4 decimal places of precision.

### 5.2 Adaptive Repetitions and Statistical Sampling

To eliminate transient OS scheduling noise while maintaining tractable benchmark durations on large quadratic workloads:
- For $N \le 100$: 1 warm-up run + 10 measured repetitions.
- For $N = 500, 1000$: 1 warm-up run + 5 measured repetitions.
- For $N \ge 5000$: Adaptive execution — fast $O(N \log N)$ algorithms execute repeated passes; quadratic $O(N^2)$ algorithms execute a single dedicated instrumented pass that records exact time, memory, and operational counts.

Reported statistical metrics include:
- **Min Duration ($\mu s$ / $ms$)**
- **Median Duration ($\mu s$ / $ms$)**
- **Mean Duration ($\mu s$ / $ms$)**
- **Max Duration ($\mu s$ / $ms$)**
- **Standard Deviation ($\sigma$)**

### 5.3 Operation Counting

The `OperationCounter` object tracks atomic algorithmic operations without distorting algorithmic structure:
- **Comparisons (`$comparisons`):** Every invocation of `ArrayUtils::compareValues()` or key comparison.
- **Swaps (`$swaps`):** Every two-element exchange operation.
- **Movements (`$movements`):** Every array element shift, assignment, or auxiliary merge copy.
- **Probes (`$probes`):** Every hash table bucket inspection during collision resolution.
- **Partitions (`$partitions`):** Every subarray division in QuickSort.
- **Recursion Depth (`$maxRecursionDepth`):** Maximum call stack depth in recursive routines.

### 5.4 Memory Measurement

Memory tracking measures:
- **Allocated Memory Delta (`memory_delta_bytes`):** `memory_get_usage()` post-execution minus pre-execution.
- **Peak Memory Delta (`peak_memory_delta_bytes`):** Peak memory consumed during algorithmic execution minus baseline usage.

### 5.5 Correctness Verification

Every benchmark execution enforces strict post-execution assertions:
1. **Sorted Order:** `ArrayUtils::isSortedBy($sorted, 'last_name', 'asc') === true`.
2. **Multiset Preservation:** The sorted array contains the exact multiset count of IDs as the input array (no duplicates introduced, no records dropped).
3. **Search Verification:** The returned index or element matches the known target ID or `null`/`-1` for miss scenarios.

---

## 6. Output Artifacts and Traceability

Benchmark execution produces four unified output formats in `benchmarks/results/`:
1. `benchmarks/results/raw/benchmark_results.json` — Complete serialized metrics, configuration, and environment metadata.
2. `benchmarks/results/raw/benchmark_results.csv` — Flat tabular CSV format for external analysis and charting.
3. `benchmarks/results/tables/benchmark_tables.md` — Formatted Markdown tables categorized by dataset size, search scenario, and sorting arrangement.
4. `benchmarks/results/graphs/*.svg` — High-fidelity standalone vector charts visualizing search scaling, sorting execution times, and arrangement impact.

