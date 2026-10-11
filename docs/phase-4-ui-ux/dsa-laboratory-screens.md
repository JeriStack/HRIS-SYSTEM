# Screen Wireframe Specification: DSA Laboratory & Visualizer
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 4 — UI/UX Design  
**Routes:** `GET /dsa/playground` | `GET /dsa/visualizer`  
**Access Guard:** `auth, role:admin,hr`  
**Status:** Approved for Implementation  

---

## 1. Interactive Algorithm Playground (`/dsa/playground`)

```
+-----------------------------------------------------------------------------------+
|  DSA Laboratory | Interactive Data Structures Playground                          |
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  [ DATA STRUCTURE SELECTOR TABS ]                                                 |
|  [ Stack (LIFO) ] | [ Queue (FIFO) ] | [ Linked List ] | [ Hash Table ] | [ BST ] |
|                                                                                   |
|  +-----------------------------------------------------------------------------+  |
|  | ACTIVE DEMO: CUSTOM BINARY SEARCH TREE (EMPLOYEE DIRECTORY)                 |  |
|  |                                                                             |  |
|  | Controls:                                                                   |  |
|  | Key / Emp No: [ EMP050 ]  Data: [ Eleanor Vance ]                            |  |
|  | [ Insert Node ]  [ Search Key ]  [ In-Order Traversal ]  [ Reset Tree ]      |  |
|  |                                                                             |  |
|  | In-Memory Tree Visualization:                                               |  |
|  |                               EMP050                                        |  |
|  |                               /    \                                        |  |
|  |                          EMP025    EMP075                                   |  |
|  |                          /    \    /    \                                   |  |
|  |                      EMP010 EMP040 EMP060 EMP090                            |  |
|  |                                                                             |  |
|  | Traversal Output:                                                           |  |
|  | [In-Order]: EMP010 -> EMP025 -> EMP040 -> EMP050 -> EMP060 -> EMP075 -> EMP090|  |
|  | Complexity: Search Avg: O(log N) | Worst: O(N) | Traversal: O(N)             |  |
|  +-----------------------------------------------------------------------------+  |
+-----------------------------------------------------------------------------------+
```

---

## 2. Multi-Scale Benchmark Visualizer (`/dsa/visualizer`)

```
+-----------------------------------------------------------------------------------+
|  DSA Laboratory | Algorithm Performance Benchmarks                                |
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  [ BENCHMARK SUITE CONTROLS ]                                                     |
|  Category: (o) Sorting Algorithms    ( ) Search Algorithms                        |
|  Dataset Scales: [x] N=10  [x] N=100  [x] N=500  [x] N=1,000  [x] N=5,000  [ ] 10k|
|  [ ⚡ Execute Empirical Benchmark Suite ]                                          |
|                                                                                   |
|  +-----------------------------------------------------------------------------+  |
|  | 📊 SORTING EXECUTION BENCHMARK RESULTS MATRIX                                |  |
|  |-----------------------------------------------------------------------------|  |
|  | ALGORITHM    THEORY    N=10      N=100     N=500     N=1,000     N=5,000    |  |
|  | Bubble Sort  O(N²)     0.02 ms   1.15 ms   28.4 ms   114.2 ms    2,840 ms   |  |
|  | Selection    O(N²)     0.01 ms   0.98 ms   24.1 ms    98.3 ms    2,410 ms   |  |
|  | Insertion    O(N²)     0.01 ms   0.85 ms   21.2 ms    85.4 ms    2,120 ms   |  |
|  | Merge Sort   O(N logN) 0.01 ms   0.12 ms    0.68 ms    1.42 ms      8.15 ms |  |
|  | Quick Sort   O(N logN) 0.01 ms   0.09 ms    0.51 ms    1.10 ms      6.20 ms |  |
|  +-----------------------------------------------------------------------------+  |
|                                                                                   |
|  [ COMPARATIVE EXECUTION TIME CURVE ]                                             |
|  Execution Time (ms)                                                              |
|   3000 |                              * Bubble Sort (Quadratic Curve O(N²))       |
|   2000 |                             /                                            |
|   1000 |                            /                                             |
|      0 +---------------------------===---* Merge & Quick Sort (Linearithmic O(NlogN)|
|        N=10      N=100     N=500   N=1000   N=5000                                |
+-----------------------------------------------------------------------------------+
```
