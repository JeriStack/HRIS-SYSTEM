# NEXORA HR — Performance Test Plan, Empirical Benchmarks & Optimization Analysis

**Project:** NEXORA HR — Human Resource Information System  
**Tagline:** People. Processes. Progress.  
**Phase:** 16.3 — Performance Testing Deliverable  
**Measurement Timestamp:** `2026-11-09T18:31:49+08:00`  
**Runtime Environment:** Windows 11 / PHP 8.5.12 CLI (`hrtime(true)` nanosecond resolution) / MySQL 8.0 InnoDB

---

## 1. Performance Testing Objectives & Methodology

The Phase 16 performance evaluation measures actual, reproducible execution latencies across NEXORA HR's core computational paths, rendering engine, and in-memory DSA operations, supplemented by database index analysis on the 18 InnoDB tables in `database/schema.sql`.

### 1.1 Measurement Methodology
- **High-Resolution Timer:** All measurements use PHP's monotonic nanosecond clock (`hrtime(true)`), converted to milliseconds (`ms`).
- **Statistical Percentiles:** Each workload is executed across $50$ to $500$ iterations to capture:
  - **Average Latency (`avg_ms`)**
  - **Median / 50th Percentile (`p50_ms`)**
  - **95th Percentile Tail Latency (`p95_ms`)**
  - **Maximum Latency (`max_ms`)**
  - **Throughput (`ops_per_sec`)**
  - **Peak Memory Footprint (`memory_peak_mb`)**
- **Synthetic Workload:** $N = 1,000$ employee records (`EMP-0001` through `EMP-1000`) with randomized salary grades and department attributes.

---

## 2. Actual Measured Performance Results (`2026-11-09`)

*Peak PHP Runtime Memory across the entire benchmark suite:* **`4.00 MB`**

| Benchmark Scenario | Workload / Iterations | Average (`avg_ms`) | Median (`p50_ms`) | 95th % (`p95_ms`) | Max (`max_ms`) | Throughput (`ops/sec`) |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: |
| **RBAC Permission Evaluation** (`AuthorizationService::can()`) | 3 role checks $\times 500$ iterations | **0.0182 ms** | **0.0036 ms** | **0.0039 ms** | 7.0613 ms | **55,085.5 / sec** |
| **Leave Working-Day Calculation** (`LeaveCalculator::calculateWorkingDays`) | 26-day span $\times 500$ iterations | **0.0424 ms** | **0.0313 ms** | **0.0395 ms** | 2.4410 ms | **23,612.0 / sec** |
| **Skeleton Screen SSR Rendering** (`render_skeleton('table', 8x6)` + `stat-card`) | 2 components $\times 500$ iterations | **1.1269 ms** | **1.0936 ms** | **1.6704 ms** | 9.1021 ms | **887.4 / sec** |
| **Hash Table Lookup (`HashTable::get`)** | $N = 1,000$ records $\times 500$ iterations | **0.0012 ms** | **0.0009 ms** | **0.0014 ms** | 0.0420 ms | **864,304.2 / sec** |
| **Binary Search (`BinarySearch::search`)** | $N = 1,000$ records $\times 200$ iterations | **0.0672 ms** | **0.0529 ms** | **0.0809 ms** | 2.7757 ms | **14,874.6 / sec** |
| **Binary Search Tree Lookup (`BinarySearchTree::search`)** | $N = 1,000$ nodes $\times 200$ iterations | **0.9045 ms** | **0.8606 ms** | **1.1679 ms** | 3.3094 ms | **1,105.6 / sec** |
| **Linear Search (`LinearSearch::search`)** | $N = 1,000$ records $\times 200$ iterations | **1.0054 ms** | **0.9578 ms** | **1.2950 ms** | 5.4468 ms | **994.6 / sec** |
| **FIFO Queue Batch (`Queue::enqueue` + `dequeue`)** | $500$ pointer ops $\times 200$ iterations | **0.1476 ms** | **0.1353 ms** | **0.1838 ms** | 2.4930 ms | **6,774.7 / sec** |
| **Merge Sort (`MergeSort::sort`)** | $N = 1,000$ records $\times 50$ iterations | **10.2844 ms** | **9.6756 ms** | **15.1201 ms** | 15.8487 ms | **97.2 / sec** |
| **Quick Sort (`QuickSort::sort`)** | $N = 1,000$ records $\times 50$ iterations | **11.0540 ms** | **10.9178 ms** | **12.1786 ms** | 12.5235 ms | **90.5 / sec** |
| **Insertion Sort (`InsertionSort::sort`)** | $N = 250$ records $\times 50$ iterations | **15.2757 ms** | **15.0601 ms** | **17.4490 ms** | 17.9093 ms | **65.5 / sec** |
| **Selection Sort (`SelectionSort::sort`)** | $N = 250$ records $\times 50$ iterations | **27.3098 ms** | **26.8620 ms** | **30.1624 ms** | 33.7723 ms | **36.6 / sec** |
| **Bubble Sort (`Bubble Sort::sort`)** | $N = 250$ records $\times 50$ iterations | **39.2783 ms** | **29.7565 ms** | **67.8563 ms** | 74.7425 ms | **25.5 / sec** |

---

## 3. Database Indexing & Query Performance Verification

Every high-frequency query path in `app/models/` and `app/Infrastructure/Persistence/Leave/` is backed by dedicated single-column or composite B-Tree indexes in `database/schema.sql`:

| Query Workflow | Target Table | Index Name & Columns | Concurrency / Performance Impact |
| :--- | :--- | :--- | :--- |
| **Login & Account State Verification** | `users` | `uq_users_email (email)`, `uq_users_username (username)`, `idx_users_role (role)`, `idx_users_status (status)` | $O(\log n)$ index seek on every authenticated request during `Session::verifyAuthenticatedAccountState()`. |
| **Employee Directory & Search** | `employees` | `uq_employees_emp_no (employee_no)`, `idx_employees_name (last_name, first_name)`, `idx_employees_dept (department_id)`, `idx_employees_status (employment_status)` | Prevents full-table scans when filtering by department, employment status, or sorting by name. |
| **Leave Overlap Detection** | `leave_requests` | `idx_requests_emp_dates (employee_id, start_date, end_date)`, `idx_requests_status (status)` | Composite index covers `(employee_id, start_date, end_date)` range checks during `MySqlLeaveFilingGuard` overlap validation. |
| **Pessimistic Row Locking on Balance Deduction** | `leave_balances` | `uq_balances_emp_type_year (employee_id, leave_type_id, year)` | Unique composite index ensures `SELECT ... FOR UPDATE` locks only the single target balance row rather than gap-locking the entire table. |
| **WebSocket Outbox Polling** | `realtime_outbox` | `idx_realtime_outbox_available (delivered_at, available_at, locked_at)` | Allows the Ratchet WebSocket poller (`PdoRealtimeStore`) to claim undelivered events in $< 1\text{ ms}$. |
| **Background Queue Worker Leasing** | `jobs` | `idx_jobs_queue_available (queue, reserved_at, available_at)` | Enables `QueueWorker` to lease ready jobs without scanning completed or future-scheduled rows. |
| **Audit Trail Filtering & Hash Chain** | `activity_logs` | `idx_logs_created (created_at)`, `idx_logs_category (category)`, `idx_logs_user_action (user_id, action)`, `idx_logs_entity (entity_type, entity_id)` | Accelerates security event filtering and hash-chain verification queries. |

---

## 4. Observations, Bottleneck Analysis & Recommendations

1. **In-Memory RBAC Cache Eliminates Per-Check SQL Overhead:**
   - The first call to `AuthorizationService::can()` populates the in-memory `rolePermissionCache` (`max_ms = 7.06 ms`), after which subsequent checks execute in **`0.0036 ms` median (`55,085 ops/sec`)**.
2. **Search Complexity Advantage ($O(1)$ & $O(\log n)$ vs. $O(n)$):**
   - At $N = 1,000$ employees, `HashTable::get()` (`0.0009 ms` median) is **>1,000x faster** than `LinearSearch` (`0.9578 ms` median), and `BinarySearch` (`0.0529 ms` median) is **18x faster** than `LinearSearch`.
3. **Sorting Scalability ($O(n \log n)$ vs. $O(n^2)$):**
   - `MergeSort` (`9.68 ms` median for $N = 1,000$) and `QuickSort` (`10.92 ms` median for $N = 1,000$) outperform $O(n^2)$ `BubbleSort` (`29.76 ms` median on only $N = 250$ records) by more than an order of magnitude on larger datasets.
4. **Asynchronous Offloading Protects HTTP Response Time:**
   - SMTP email transmission (`500 ms – 2,500 ms` over external TLS relays) is offloaded to `jobs` (`SendEmailHandler`) for leave decisions and notifications, keeping HTTP controller response times fast and deterministic.

