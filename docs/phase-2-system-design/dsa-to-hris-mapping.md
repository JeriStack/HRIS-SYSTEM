# Data Structures & Algorithms (DSA) to HRIS Mapping Specification
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 2 — System Analysis and Design  
**Date:** 2026-10-11  
**Status:** Approved for Baseline Design  

---

## 1. Architectural Concept: Database vs. Data Structures

A fundamental academic requirement of this project is to explicitly demonstrate Computer Science Data Structures and Algorithms implemented natively in PHP, rather than relying exclusively on MySQL query engines or PHP built-in functions.

```
+-----------------------------------------------------------------------------------+
|                        DATABASE VS. DATA STRUCTURES ROLE                          |
+-----------------------------------------------------------------------------------+
| 1. PERSISTENCE TIER (MySQL):                                                      |
|    - Acts as the durable, transactional source of truth.                          |
|    - Manages relational tables, foreign key constraints, and ACID properties.     |
|    - Records remain intact across server restarts and HTTP request lifecycles.    |
|                                                                                   |
|                                  | SELECT query via PDO                           |
|                                  v                                                |
| 2. IN-MEMORY ALGORITHMIC TIER (Custom PHP DSA):                                   |
|    - Loads raw records into application memory (heap).                            |
|    - Encapsulates entities in custom PHP objects (Node, BSTNode, Bucket, Queue).   |
|    - Executes custom sorting, searching, traversals, and LIFO/FIFO operations.    |
|    - Tracks operational step metrics (comparisons, swaps, execution times).       |
|                                                                                   |
|                                  | Presentation formatting                        |
|                                  v                                                |
| 3. USER INTERFACE (HTML5/CSS3 Views):                                             |
|    - Displays processed results and empirical benchmark comparison graphs.        |
+-----------------------------------------------------------------------------------+
```

---

## 2. Comprehensive DSA-to-HRIS Mapping Matrix

The table below outlines each planned custom structure and algorithm, its mapped use case, operations, theoretical complexities, test cases, and delivery phase:

| DSA Concept | Planned PHP Implementation | HRIS or Laboratory Use Case | Required Operations | Expected Complexity (Time & Space) | Required Test Cases | Relevant Module | Planned Phase |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :---: |
| **Arrays (Wrapper)** | `ArrayWrapper.php` | In-memory employee collection holding database records. | `get($index)`<br/>`insert($item)`<br/>`delete($index)`<br/>`traverse()` | Time: Access $O(1)$, Search $O(N)$, Traversal $O(N)$<br/>Space: $O(N)$ | `TC-DSA-001`: Indexed lookup, append, out-of-bounds check | `MOD-EMP`, `MOD-DSALAB` | Phase 8 |
| **Linear Search** | `LinearSearch.php` | Baseline sequential search over unsorted employee directory by Name/ID. | `search($array, $key, $attribute)` | Time: Best $O(1)$, Avg $O(N)$, Worst $O(N)$<br/>Space: $O(1)$ | `TC-DSA-002`: Key at index 0, key in middle, key absent, empty array | `MOD-SRT`, `MOD-DSALAB` | Phase 8 |
| **Binary Search** | `BinarySearch.php` | High-efficiency lookup of employees by `employee_no` on a verified sorted dataset. | `search($sortedArray, $targetId)` | Time: Best $O(1)$, Avg $O(\log N)$, Worst $O(\log N)$<br/>Space: $O(1)$ | `TC-DSA-003`: First element, last element, middle element, non-existent key | `MOD-SRT`, `MOD-DSALAB` | Phase 8 |
| **Bubble Sort** | `BubbleSort.php` | Educational sorting benchmark for small employee datasets ($N \le 100$). | `sort(&$array, $attribute)` | Time: Best $O(N)$ (with flag), Avg $O(N^2)$, Worst $O(N^2)$<br/>Space: $O(1)$ | `TC-DSA-004`: Already sorted array, reverse sorted array, duplicate keys | `MOD-SRT`, `MOD-PERF` | Phase 8 |
| **Selection Sort**| `SelectionSort.php` | Sorting benchmark comparing minimum search passes vs bubble swaps. | `sort(&$array, $attribute)` | Time: Best $O(N^2)$, Avg $O(N^2)$, Worst $O(N^2)$<br/>Space: $O(1)$ | `TC-DSA-005`: Random dataset, identical elements, single element | `MOD-SRT`, `MOD-PERF` | Phase 8 |
| **Insertion Sort**| `InsertionSort.php` | Sorting nearly-sorted employee records (e.g. newly added hires). | `sort(&$array, $attribute)` | Time: Best $O(N)$, Avg $O(N^2)$, Worst $O(N^2)$<br/>Space: $O(1)$ | `TC-DSA-006`: Partially sorted records, reverse order | `MOD-SRT`, `MOD-PERF` | Phase 8 |
| **Merge Sort** | `MergeSort.php` | Guaranteed divide-and-conquer sorting for large workforce records ($N \ge 1,000$). | `sort($array)`<br/>`merge($left, $right)` | Time: Best $O(N \log N)$, Avg $O(N \log N)$, Worst $O(N \log N)$<br/>Space: $O(N)$ | `TC-DSA-007`: Large random dataset ($N=10k$), stable ordering | `MOD-SRT`, `MOD-PERF` | Phase 8 |
| **Quick Sort** | `QuickSort.php` | High-performance in-place sorting benchmark with partition analysis. | `sort(&$array, $low, $high)`<br/>`partition(&$array, $low, $high)` | Time: Best $O(N \log N)$, Avg $O(N \log N)$, Worst $O(N^2)$<br/>Space: $O(\log N)$ stack | `TC-DSA-008`: Pivot selection (Lomuto/Hoare), sorted input vulnerability | `MOD-SRT`, `MOD-PERF` | Phase 8 |
| **Stack (LIFO)** | `Stack.php` | Recent administrative activity history (newest action retrieved first). | `push($item)`<br/>`pop()`<br/>`peek()`<br/>`isEmpty()` | Time: All operations $O(1)$<br/>Space: $O(N)$ | `TC-DSA-009`: Push multiple, pop until empty, underflow exception | `MOD-LOG`, `MOD-DSALAB` | Phase 9 |
| **Queue (FIFO)** | `Queue.php` | Pending leave requests approval queue (first filed processed first). | `enqueue($item)`<br/>`dequeue()`<br/>`peek()`<br/>`isEmpty()`<br/>`size()` | Time: Enqueue $O(1)$, Dequeue $O(1)$<br/>Space: $O(N)$ | `TC-DSA-010`: Strict front/rear pointer index progression without `array_shift()` | `MOD-LPROC`, `MOD-DSALAB` | Phase 9 |
| **Linked List** | `LinkedList.php` | Singly linked chain of employee leave transaction history nodes. | `insertLast($data)`<br/>`delete($id)`<br/>`search($id)`<br/>`toArray()` | Time: Insert $O(1)$ (with tail) or $O(N)$, Search $O(N)$, Delete $O(N)$<br/>Space: $O(N)$ | `TC-DSA-011`: Insert at tail, head deletion, search missing node | `MOD-LHIST`, `MOD-DSALAB` | Phase 9 |
| **Hash Table** | `HashTable.php` | Instantaneous $O(1)$ employee lookup by `employee_no` using custom hashing. | `insert($key, $val)`<br/>`search($key)`<br/>`delete($key)`<br/>`hash($key)` | Time: Avg $O(1)$, Worst $O(N)$ (hash collisions)<br/>Space: $O(M + N)$ buckets | `TC-DSA-012`: Polynomial hash calculation, collision chain resolution | `MOD-EMP`, `MOD-DSALAB` | Phase 9 |
| **Binary Search Tree**| `BinarySearchTree.php` | Hierarchical employee directory ordered by ID with recursive traversals. | `insert($id, $data)`<br/>`search($id)`<br/>`delete($id)`<br/>`inOrder()` | Time: Avg $O(\log N)$, Worst $O(N)$ (degenerate tree)<br/>Space: $O(N)$ | `TC-DSA-013`: BST invariant verification, in-order output sorted order | `MOD-EMP`, `MOD-DSALAB` | Phase 9 |
| **Recursion** | `RecursionHelper.php` | Tree traversal algorithms and divide-and-conquer sorting implementations. | `inOrderTraversal($node)`<br/>`factorial($n)` | Time: $O(N)$ for tree traversal<br/>Space: $O(H)$ call stack depth | `TC-DSA-014`: Base case halting, deep recursion stack limits | `MOD-DSALAB` | Phase 9 |
| **Big-O Analysis** | Lab Visualizer | Benchmarking suite measuring execution time, comparisons, and memory. | `benchmark()`<br/>`collectMetrics()` | Empirical vs Theoretical complexity comparison | `TC-DSA-015`: Multi-scale benchmark run ($N = 10$ to $10,000$) | `MOD-PERF` | Phase 12 |

---

## 3. Strict Preconditioning & Separation Rules

### 3.1 Preconditioning Sorted Input for Binary Search
* **Requirement:** Binary search requires a strictly sorted array to function correctly.
* **Architecture Implementation:** When an employee search is triggered via Binary Search in the DSA module:
  1. Unsorted employee records are retrieved from the database without SQL `ORDER BY`.
  2. The custom `MergeSort` or `QuickSort` algorithm is explicitly executed on the in-memory array to sort records by `employee_no`.
  3. The sorted array is passed to `BinarySearch::search()`.
  4. The system logs both sorting time and searching time separately to demonstrate the full pipeline:
     $$\text{Total Time} = T_{\text{Sort}} (O(N \log N)) + T_{\text{BinarySearch}} (O(\log N))$$

### 3.2 Anti-Cheat & Academic Restriction Policy
To ensure defense credibility, the codebase strictly enforces:
1. **No `sort()` or `usort()`:** In `app/dsa/algorithms/`, sorting algorithms must manipulate array indices directly using manual nested loops and swap variables.
2. **No `array_search()`:** Searching in `app/dsa/algorithms/` must iterate linearly or calculate midpoints mathematically.
3. **No `array_shift()` for Queue:** In `app/dsa/structures/Queue.php`, removing an item must advance an internal `$front` index pointer or use a linked node reference rather than re-indexing native PHP arrays with `array_shift()` ($O(N)$ penalty).
4. **No Native Associative Array Substitution for Hash Table:** In `app/dsa/structures/HashTable.php`, the implementation must maintain an array of fixed bucket lists, compute a manual hash integer via polynomial rolling hash, compute index via modulo arithmetic (`$hash % $capacity`), and handle collisions via separate chaining using linked nodes.
