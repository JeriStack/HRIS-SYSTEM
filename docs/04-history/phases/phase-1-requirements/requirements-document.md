# Software Requirements Specification (SRS)
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 1 — Requirements and Project Initiation  
**Project Name:** Employee-HRIS-Leave-Management-System  
**Date:** 2026-10-11  
**Status:** Approved for Baseline Design  

---

## 1. Executive Summary & Project Purpose

The **Employee HRIS & Leave Management System** is a web-based enterprise application built using native PHP and MySQL/MariaDB, engineered with a dual-purpose mandate:

1. **Operational Software Purpose:** Deliver a robust, reliable, and user-friendly Human Resource Information System (HRIS) and Leave Management portal that manages core workforce records, departments, positions, leave entitlements, transactional leave approval workflows, audit trails, and reporting.
2. **Academic DSA Purpose:** Serve as an educational demonstration laboratory for fundamental Computer Science concepts. The system implements custom Data Structures and Algorithms (DSA) natively in PHP (without relying on PHP built-in functions or MySQL query engines for demonstrated algorithmic behaviors), benchmarking performance across varying dataset scales.

```
+---------------------------------------------------------------------------------+
|                                 DUAL OBJECTIVE                                  |
+----------------------------------------+----------------------------------------+
|           OPERATIONAL HRIS             |             ACADEMIC DSA               |
|  - Centralized Employee Directory      |  - Custom PHP Data Structures          |
|  - Organization Hierarchy Management   |  - Custom Search & Sort Algorithms     |
|  - Leave Filing & Tracking             |  - Algorithm Performance Laboratory    |
|  - ACID-Compliant Leave Approval       |  - Big-O Complexity Comparisons        |
|  - Activity Audit Trails & Alerts      |  - Memory & Comparison Step Tracking   |
+----------------------------------------+----------------------------------------+
```

---

## 2. Primary System Objectives

* **Centralize Workforce Records:** Maintain comprehensive employee profiles including personal, contact, departmental, positional, and employment status data.
* **Organizational Structure:** Maintain parent departments and assigned job positions with referential integrity.
* **Leave Lifecycle Management:** Track multi-type leave policies, allocate annual balances, process leave filings, and validate availability.
* **Transactional Approval Pipeline:** Enforce strict ACID consistency during leave approvals so request statuses and remaining balances remain synchronized without race conditions.
* **Historical Audit Trails:** Record every state-altering administrative and employee operation for compliance and traceability.
* **In-Memory Algorithmic Processing:** Demonstrate in-memory data structures (Arrays, Linked Lists, Stacks, Queues, Hash Tables, Binary Search Trees) and algorithms (Linear Search, Binary Search, Bubble Sort, Selection Sort, Insertion Sort, Merge Sort, Quick Sort, Recursive Traversals) on real HR datasets.
* **Empirical Benchmarking:** Measure and graph execution time, comparisons, swaps, and memory overhead across input sizes ($N = 10, 100, 500, 1000, 5000, 10000$).
* **Role-Based Security:** Prevent unauthorized privilege escalation, enforce strict session boundaries, prevent Cross-Site Request Forgery (CSRF), and sanitize all user input and output.

---

## 3. Functional Requirements (FR)

All functional requirements are assigned stable, unique identifiers according to functional domain.

### 3.1 Authentication and Access Control (`FR-AUTH`)

| ID | Title | Description | Priority |
| :--- | :--- | :--- | :--- |
| `FR-AUTH-001` | User Login | Authenticate users via unique username and password. | Critical |
| `FR-AUTH-002` | Password Verification | Verify credentials against passwords hashed with `password_hash()` using `BCRYPT` or `ARGON2ID`. Plaintext storage is strictly prohibited. | Critical |
| `FR-AUTH-003` | Session Regeneration | Regenerate PHP session identifier (`session_regenerate_id(true)`) immediately upon successful credential validation to mitigate session fixation attacks. | High |
| `FR-AUTH-004` | Session Timeout | Invalidate inactive sessions automatically after a defined inactivity threshold (default: 30 minutes). | High |
| `FR-AUTH-005` | User Logout | Explicitly clear `$_SESSION` data and destroy the session cookie upon user logout. | Critical |
| `FR-AUTH-006` | Role-Based Access Control | Enforce server-side role verification (`admin`, `hr`, `employee`) prior to executing controller actions or rendering protected views. | Critical |
| `FR-AUTH-007` | Unauthorized Access Denial | Block unauthorized route access and redirect with an appropriate security notice without disclosing system internals. | High |

### 3.2 Employee Management (`FR-EMP`)

| ID | Title | Description | Priority |
| :--- | :--- | :--- | :--- |
| `FR-EMP-001` | Employee Registration | Allow Administrator/HR to record new employees with mandatory fields: employee number, name, email, phone, gender, birth date, department, position, date hired, and employment status. | Critical |
| `FR-EMP-002` | Unique Employee Number | Ensure system rejects duplicate employee numbers with clear validation feedback. | Critical |
| `FR-EMP-003` | Employee Record Editing | Allow Administrator/HR to update personal and employment details of existing employees. | High |
| `FR-EMP-004` | Employee Soft Deactivation | Prevent permanent record deletion; mark employee status as `Inactive` or `Terminated` to preserve historical integrity in leave records and audit logs. | Critical |
| `FR-EMP-005` | Employee Profile View | Render full profile details for Administrator/HR, and allow Employees to view only their personal profile. | High |
| `FR-EMP-006` | Employee Directory View | Provide a searchable, filterable directory listing active employees. | High |

### 3.3 Organization Management (`FR-ORG`)

| ID | Title | Description | Priority |
| :--- | :--- | :--- | :--- |
| `FR-ORG-001` | Department CRUD | Allow Administrator/HR to create, view, update, and manage departments (e.g., IT, Human Resources, Finance). | High |
| `FR-ORG-002` | Position CRUD | Allow Administrator/HR to create, view, update, and manage job positions linked to specific departments. | High |
| `FR-ORG-003` | Referential Integrity | Prevent deletion of departments or positions that have actively assigned employees. | High |

### 3.4 Leave Type & Balance Management (`FR-LBAL`)

| ID | Title | Description | Priority |
| :--- | :--- | :--- | :--- |
| `FR-LBAL-001` | Leave Type Management | Allow Administrator/HR to create and configure leave types (Vacation Leave, Sick Leave, Emergency Leave, Maternity Leave, Paternity Leave) and default allocations. | High |
| `FR-LBAL-002` | Leave Balance Initialization | Allocate annual leave balance records per employee per active leave type for the fiscal year. | High |
| `FR-LBAL-003` | Balance Inquiry | Allow employees to view their allocated, used, and remaining leave days for each leave category. | High |
| `FR-LBAL-004` | Balance Integrity | Enforce `remaining_days = allocated_days - used_days` dynamically and within persistent balance records. | Critical |

### 3.5 Leave Filing & Workflow (`FR-LEAVE`)

| ID | Title | Description | Priority |
| :--- | :--- | :--- | :--- |
| `FR-LEAVE-001` | Leave Filing Form | Allow employees to submit a leave request specifying leave type, start date, end date, and reason. | Critical |
| `FR-LEAVE-002` | Server-Side Date Validation | Validate that start date $\le$ end date and both dates are valid calendar dates. | Critical |
| `FR-LEAVE-003` | Working Days Calculation | Compute duration excluding Saturdays and Sundays (Monday–Friday calendar). | High |
| `FR-LEAVE-004` | Balance Sufficiency Check | Reject leave submissions when requested duration exceeds remaining balance for the selected category. | Critical |
| `FR-LEAVE-005` | Overlapping Leave Prevention | Prevent submission if requested date interval intersects with existing `Pending` or `Approved` leave requests for the same employee. | Critical |
| `FR-LEAVE-006` | Initial Status Assignment | Record newly submitted valid requests with an initial status of `Pending`. | High |
| `FR-LEAVE-007` | Leave Cancellation | Allow employees to cancel their own leave requests provided the status is still `Pending`. Rejected or approved requests cannot be cancelled. | High |
| `FR-LEAVE-008` | Transactional Leave Approval | Allow Administrator/HR to approve pending requests inside a strict database transaction (`BEGIN TRANSACTION` -> atomic status update, balance deduction, audit log, notification -> `COMMIT`). Rollback on failure. | Critical |
| `FR-LEAVE-009` | Leave Rejection | Allow Administrator/HR to reject pending requests with an optional reason; status changes to `Rejected` without balance deductions. | High |
| `FR-LEAVE-010` | Leave History View | Provide employees and HR an audit log of past and current leave requests with approval timestamps and approver details. | High |

### 3.6 Reporting & Notifications (`FR-REP` & `FR-NOTIF`)

| ID | Title | Description | Priority |
| :--- | :--- | :--- | :--- |
| `FR-REP-001` | Employee Workforce Report | Generate summary reports of employees grouped by department, position, and status. | Medium |
| `FR-REP-002` | Leave Utilization Report | Generate reports on leave requests by date range, department, leave type, and approval status. | Medium |
| `FR-REP-003` | Department Summary Report | Tabulate headcount, active leaves, and total leave consumption per department. | Medium |
| `FR-NOTIF-001` | In-System Workflow Notifications | Trigger in-system notifications upon leave filing (to HR) and upon approval/rejection (to Employee). | Medium |
| `FR-NOTIF-002` | Notification Feed | Display unread notification indicators and historical notifications in user dashboards. | Medium |
| `FR-LOG-001` | Audit Activity Logging | Record all critical actions (`LOGIN`, `LOGOUT`, `EMPLOYEE_CREATE`, `LEAVE_APPROVE`, etc.) with actor ID, entity, IP address, user agent, and timestamp. | High |

### 3.7 Data Structures and Algorithms Laboratory (`FR-DSA`)

| ID | Title | Description | Priority |
| :--- | :--- | :--- | :--- |
| `FR-DSA-001` | Custom Array Wrapper | Implement `ArrayWrapper` supporting sequential storage, indexed access $O(1)$, search $O(N)$, insertion, and deletion. | High |
| `FR-DSA-002` | Custom Linear Search | Implement `LinearSearch` over in-memory employee collections ($O(N)$ baseline). | High |
| `FR-DSA-003` | Custom Binary Search | Implement `BinarySearch` on sorted employee dataset by ID ($O(\log N)$). Explicitly precondition input via custom sorting. | High |
| `FR-DSA-004` | Custom Sorting Algorithms | Implement custom `BubbleSort` ($O(N^2)$), `SelectionSort` ($O(N^2)$), `InsertionSort` ($O(N^2)$), `MergeSort` ($O(N \log N)$), and `QuickSort` ($O(N \log N)$ avg). | High |
| `FR-DSA-005` | Custom LIFO Stack | Implement `Stack` (`push`, `pop`, `peek`, `isEmpty`) to manage and present recent activity history. | High |
| `FR-DSA-006` | Custom FIFO Queue | Implement `Queue` (`enqueue`, `dequeue`, `peek`, `isEmpty`, `size`) with pointer/circular indexing (no `array_shift()`) for pending leave approval processing. | High |
| `FR-DSA-007` | Custom Linked List | Implement singly `LinkedList` (`Node`: data, next; `insert`, `delete`, `search`, `display`) representing employee leave history. | High |
| `FR-DSA-008` | Custom Hash Table | Implement `HashTable` with manual hash code generation, bucket arrays, and explicit collision handling (chaining) for $O(1)$ average employee lookup. | High |
| `FR-DSA-009` | Custom Binary Search Tree | Implement `BinarySearchTree` (`insert`, `search`, `delete`, recursive `inOrder`, `preOrder`, `postOrder`) for ordered employee directory lookup. | High |
| `FR-DSA-0010` | Recursive Traversals | Demonstrate recursion with explicit base case and recursive step in BST traversal and factorial helpers. | High |
| `FR-DSA-0011` | DSA Playground & Visualizer | Provide interactive UI pages allowing users to select algorithms, input parameters, view step counters, and compare metrics. | High |

### 3.8 Performance Benchmarking Laboratory (`FR-PERF`)

| ID | Title | Description | Priority |
| :--- | :--- | :--- | :--- |
| `FR-PERF-001` | Controlled Dataset Generation | Generate synthetic employee datasets of size $N \in \{10, 100, 500, 1000, 5000, 10000\}$. | High |
| `FR-PERF-002` | Search Benchmark | Measure execution time ($\mu s$/$ms$), comparison steps, and memory for Linear Search, Binary Search, and Hash Table Search across dataset scales. | High |
| `FR-PERF-003` | Sort Benchmark | Measure execution time, comparison count, and swap count for Bubble, Selection, Insertion, Merge, and Quick Sort. | High |
| `FR-PERF-004` | Metric Visualization | Present benchmark results in structured comparison tables and comparative visualization charts. | High |

---

## 4. Non-Functional Requirements (NFR)

All non-functional requirements are assigned stable, unique identifiers.

### 4.1 Security Requirements (`NFR-SEC`)

* `NFR-SEC-001` **(Mandatory):** Passwords must never be stored in plain text. Standard `password_hash()` with BCRYPT/ARGON2ID and `password_verify()` must be used.
* `NFR-SEC-002` **(Mandatory):** All SQL execution must use PDO prepared statements with bound parameters (`execute([':key' => $val])`). Direct SQL string interpolation is strictly prohibited.
* `NFR-SEC-003` **(Mandatory):** Cross-Site Request Forgery (CSRF) tokens must be generated per session and validated on all state-changing POST/PUT/DELETE requests.
* `NFR-SEC-004` **(Mandatory):** All user-provided output rendered into HTML views must be escaped via `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')` to prevent Cross-Site Scripting (XSS).
* `NFR-SEC-005` **(Mandatory):** Authentication status and role authorizations must be validated server-side on every protected endpoint.
* `NFR-SEC-006` **(Mandatory):** Generic error messages must be shown to users upon authentication failure (e.g., "Invalid username or password") to avoid username enumeration.

### 4.2 Data Integrity & Reliability (`NFR-INT` & `NFR-REL`)

* `NFR-INT-001` **(Mandatory):** Leave approval workflows must execute inside atomic database transactions (`beginTransaction()`, `commit()`, `rollBack()`).
* `NFR-INT-002` **(Mandatory):** Relational integrity must be enforced via foreign keys with `ON DELETE RESTRICT` for primary entity relations (Employees, Departments, Positions, Leave Types).
* `NFR-INT-003` **(Mandatory):** Unique constraints must be maintained for `employees.employee_no`, `users.username`, and `employees.email`.
* `NFR-REL-001` **(Mandatory):** Database failures and unexpected exceptions must be caught gracefully, logged to server error logs, and presented to users with human-readable error banners.

### 4.3 Performance Requirements (`NFR-PERF`)

* `NFR-PERF-001` **(Mandatory):** Benchmark testing must isolate algorithm execution from I/O and database latency by preloading test data into memory prior to starting timers.
* `NFR-PERF-002` **(Mandatory):** Execution timers must use PHP high-resolution timing (`hrtime(true)`) to measure microsecond/nanosecond performance without clock skew.
* `NFR-PERF-003` **(Proposed Target):** Standard CRUD pages and directory listings for $N \le 1,000$ records should render in $< 200\text{ ms}$ under local XAMPP Apache/PHP 8.x environments.

### 4.4 Usability & Accessibility (`NFR-USA`)

* `NFR-USA-001` **(Mandatory):** Standardized visual feedback must be provided for all user interactions: Green alerts for success, Yellow for warnings, Red for validation/system errors.
* `NFR-USA-002` **(Mandatory):** Destructive or irreversible state changes (e.g., rejecting a leave, deactivating an employee) must require modal or dialog confirmation.
* `NFR-USA-003` **(Mandatory):** The application interface must be responsive and usable on standard desktop resolutions ($1920\times1080$, $1366\times768$) and mobile viewports ($\ge 375\text{ px}$).

### 4.5 Maintainability & Portability (`NFR-MNT` & `NFR-PRT`)

* `NFR-MNT-001` **(Mandatory):** Clean separation of concerns following a Model-View-Controller (MVC) architecture with dedicated directories for `core/`, `app/controllers/`, `app/models/`, `app/dsa/`, and `app/views/`.
* `NFR-MNT-002` **(Mandatory):** DSA implementations must be decoupled from UI code and stored in dedicated namespaces/classes within `app/dsa/structures/` and `app/dsa/algorithms/`.
* `NFR-PRT-001` **(Mandatory):** The application must run seamlessly on standard PHP 8.x + MySQL 8.x / MariaDB running under XAMPP on Windows without requiring proprietary external compilation tools.

---

## 5. Verification Checklist

* [x] Dual software and academic objectives documented.
* [x] Unique requirement IDs assigned across all functional modules.
* [x] Non-functional requirements established across security, integrity, usability, and maintainability.
* [x] Distinction between mandatory requirements and proposed performance targets explicitly noted.
* [x] Custom DSA academic requirements delineated from database persistence.
