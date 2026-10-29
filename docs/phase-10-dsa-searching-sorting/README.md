# Phase 10 — DSA Searching & Sorting

Custom searching and sorting algorithms, integrated into the HR Employee Directory, with a single shared comparison contract and PHPUnit coverage.

## 1. Big-O summary

| Algorithm | Best | Average | Worst | Typical auxiliary space | Stable | Needs sorted input | Mutates input |
|---|---:|---:|---:|---:|:--:|:--:|:--:|
| Linear Search | O(1) | O(n) | O(n) | O(1) (O(k) for multi-match) | n/a | no | no |
| Binary Search | O(1) | O(log n) | O(log n) | O(1) iterative | n/a | **yes** | no |
| Bubble Sort | O(n) early-exit | O(n²) | O(n²) | O(1)* | yes | no | no |
| Selection Sort | O(n²) | O(n²) | O(n²) | O(1)* | no | no | no |
| Insertion Sort | O(n) | O(n²) | O(n²) | O(1)* | yes | no | no |
| Merge Sort | O(n log n) | O(n log n) | O(n log n) | O(n) | yes | no | no |
| Quick Sort | O(n log n) | O(n log n) | O(n²) | O(log n) recursion** | no | no | no |

\* These sorts are O(1) in comparison/swap space but return a **new** array (an O(n) output copy), because the project's contract is non-mutating.
\*\* Quick Sort uses median-of-three pivoting and always recurses into the smaller partition first, bounding stack depth to O(log n). Worst case stays O(n²) time on adversarial input.

This table describes these conventional implementations. Big-O is an asymptotic guide, **not** a guarantee of real-world response time: constant factors, PHP array-copy costs, and dataset size all matter. For the HRIS directory, a single indexed SQL query is almost always faster end-to-end than loading rows into PHP to sort them; the DSA path exists to demonstrate the algorithms on a bounded dataset, not to replace SQL.

## 2. How each algorithm works and when to use it

- **Linear Search** — sequential scan. Works on unsorted data and supports partial, case-insensitive substring matching (`searchContains`) and multi-match (`searchAll`). Best when data is unsorted or you need every match. No preprocessing.
- **Binary Search** — halves a **sorted** range each step. `search()` returns one match; `searchFirst()` returns the lowest index among duplicates. An optional `assertSorted` flag makes it reject unverified input rather than return wrong results. Requires an O(n log n) sort first if the data is not already ordered, so it is not automatically faster end-to-end than one linear scan.
- **Bubble Sort** — swaps adjacent out-of-order elements; early-exits on a clean pass. Educational; good only for tiny or nearly-sorted data.
- **Selection Sort** — repeatedly selects the next element by order and places it. Minimal swaps, always O(n²) comparisons. Not stable.
- **Insertion Sort** — builds a sorted prefix by shifting. Excellent on nearly-sorted data (O(n) best case). Stable.
- **Merge Sort** — divide and conquer with a hand-written merge. Guaranteed O(n log n), stable, but O(n) extra memory. The directory's binary-search pipeline uses it to pre-order data.
- **Quick Sort** — median-of-three partitioning, in-place on an internal copy. Fast average case, O(log n) stack; worst case O(n²).

## 3. Shared comparison contract (`App\Dsa\Arrays\ArrayUtils`)

Every search and sort routes through one comparator so behavior is identical:

- **Numeric vs text:** if both values are numeric (int, float, or numeric string) they compare numerically; otherwise as case-insensitive, trimmed strings. So a purely numeric field won't put `"10"` before `"2"`, while `"EMP-10"` vs `"EMP-2"` compares as text.
- **Null policy:** nulls sort **last** in ascending order (first in descending). Equality matching treats `null` as equal only to `null`.
- **Direction:** `asc` or `desc`, validated; any other value throws `InvalidArgumentException`.
- **Record integrity:** only the chosen field is read for comparison. Full records are returned unchanged; employee IDs are never coerced for the caller. Sorts return a new, re-indexed array and never mutate the input.
- **Equal keys:** Merge, Insertion, and Bubble are stable (equal keys keep input order). Selection and Quick are not.

## 4. Employee Directory integration

**Where:** `App\Services\EmployeeDirectoryService` is the single bridge. `EmployeeController::index()` (route `GET /hr/employees`, HR-only) loads the authorized, SQL-filtered dataset, then—when a sort control is used—passes it to the service, which runs the chosen custom algorithm and returns records plus run metadata (algorithm, field, direction, row count, elapsed ms). The directory view renders a sort-field / direction / algorithm selector and shows which algorithm executed.

**Flow:**
```
GET /hr/employees?...&q=<term>&search_algo=<linear|binary>&search_field=<field>&sort=<field>&dir=<asc|desc>&algo=<bubble|selection|insertion|merge|quick>
  -> AuthMiddleware + RoleMiddleware(hr)
  -> If custom search: Employee::getAll(null, status, dept) loads authorized scope; EmployeeDirectoryService::search() filters via LinearSearch/BinarySearch
  -> If default search: Employee::getAll(search, status, dept) filters in SQL
  -> If sort requested: EmployeeDirectoryService::sort() validates and orders in-memory via ArrayUtils
  -> view renders sorted/filtered rows + search/sort badges with elapsed execution time
```

**Search pipeline (service `search()`):**
- `linear` → `LinearSearch::searchContains()` for partial, case-insensitive matching over the unsorted dataset.
- `binary` → `MergeSort::sort()` by the field first (preprocessing cost recorded separately in `sort_ms`), then `BinarySearch::searchFirst()` plus a contiguous sweep for exact-match duplicates. This is the documented "sort then binary search" pipeline from `docs/phase-2-system-design/dsa-to-hris-mapping.md` §3.1.

**Proof the custom algorithms are genuinely used:** the controller never falls back to `sort()`/`usort()` when a valid DSA selector is supplied; `EmployeeDirectoryServiceTest` asserts each algorithm produces the correct order and that binary search records a non-null pre-sort time. The algorithm cores contain no `sort`, `usort`, or `array_search` (anti-cheat rule §3.2).

## 5. Security

- **Authorization unchanged:** `/hr/employees` stays `AuthMiddleware` + `RoleMiddleware(['hr'])`. Employees have no directory route.
- **Allowlists, not reflection:** sort/search fields, directions, and algorithms are validated against explicit maps. The client sends a short selector key (e.g. `merge`); the server resolves it to a class. No class or method name ever comes from client input, so there is no arbitrary-instantiation risk.
- **Field exposure:** only fields the HR directory already displays are sortable/searchable. DSA does not widen data access.
- **Output escaping:** the view escapes all rendered values with `e()`.
- **Invalid input:** an unrecognized sort selector falls back to the default SQL ordering with a flash notice; the service throws `InvalidArgumentException` for out-of-allowlist values, which the controller guards against before calling it.

## 6. Performance & dataset safety

- **Bounded dataset:** `EmployeeDirectoryService::MAX_DSA_DATASET = 2000`. In-PHP DSA processes at most that many rows; beyond it the dataset is capped and the UI flags it. This prevents memory/CPU exhaustion from O(n²) sorts or large array copies.
- **Authorized rows only:** SQL `WHERE` filters run first, so DSA never loads the entire table just to demonstrate an algorithm.
- **No queries in loops:** records are fetched once; comparisons are pure in-memory.
- **Trade-offs:** for a large workforce, keep using SQL `ORDER BY` + `LIMIT` (indexed, streamed, low memory). The DSA path trades end-to-end speed for a transparent, teachable algorithm run on a safe, bounded dataset. Migration path if data grows: push sorting/searching back to SQL, or page the dataset before applying DSA.

## 7. Files

Created:
- `app/dsa/arrays/ArrayUtils.php` — shared comparison/validation contract.
- `app/services/EmployeeDirectoryService.php` — directory DSA bridge + allowlists + dataset cap.
- `tests/Unit/DSA/ArrayUtilsTest.php`, `SortingAlgorithmsTest.php`, `LinearSearchTest.php`, `BinarySearchTest.php`, `EmployeeDirectoryServiceTest.php`.
- `docs/phase-10-dsa-searching-sorting/README.md` (this file).

Changed:
- `app/dsa/algorithms/{BubbleSort,SelectionSort,InsertionSort,MergeSort,QuickSort}.php` — added `$attribute` + `$direction`, routed through `ArrayUtils`; QuickSort median-of-three + bounded recursion. Existing scalar `sort($arr)` calls remain valid.
- `app/dsa/algorithms/{LinearSearch,BinarySearch}.php` — routed through `ArrayUtils`; added `searchAll`/`searchContains` and `searchFirst` + sorted-input guard.
- `app/controllers/EmployeeController.php` — optional validated sort params.
- `app/views/employees/index.php` — sort controls + active-algorithm indicator.
- `core/bootstrap.php`, `composer.json` — register `App\Dsa\Arrays\` namespace.

## 8. How to test

```powershell
# Container (authoritative runtime)
docker compose exec app php vendor/bin/phpunit tests/Unit/DSA      # DSA unit + integration
docker compose exec app php vendor/bin/phpunit                     # full suite
docker compose exec app php scratch/test_dsa_suite.php             # legacy scalar DSA suite

# Manual UI check: sign in as HR, open /hr/employees, choose a Sort field,
# direction, and algorithm, click Apply Sort. The badge shows which algorithm
# ran, over how many rows, and in how many ms.
```

Backward compatibility: the pre-existing scalar call signatures (`BubbleSort::sort($arr)`, `BinarySearch::search($arr, $t)`) still work, so `/dsa` and `scratch/test_dsa_suite.php` are unaffected.
