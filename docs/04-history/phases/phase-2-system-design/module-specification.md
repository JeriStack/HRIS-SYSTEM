# Comprehensive Application Module Specifications
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 2 — System Analysis and Design  
**Date:** 2026-10-11  
**Status:** Approved for Baseline Design  

---

## 1. Overview of System Modules

The system is decomposed into 15 cohesive modules across functional domains, security, reporting, and custom algorithmic demonstration components.

---

## 2. Module Specifications

### Module 1: Authentication and Authorization (`MOD-AUTH`)
* **Purpose:** Manage identity verification, cryptographic password checks, session lifecycles, and role-based access gating.
* **Responsible Actors:** Administrator/HR, Employee, Unauthenticated Visitor.
* **Major Operations:**
  * `login()`: Verify credentials, hash validation, session regeneration, role dispatch.
  * `logout()`: Terminate active session, purge cookie, redirect.
  * `authorize()`: Validate permissions against target route prior to execution.
* **Inputs:** Username, plaintext password, CSRF token, session cookie.
* **Outputs:** Authenticated session, user role token, authorization redirect, or security error message.
* **Business Rules:** `BR-005` (1-to-1 account binding), `BR-008` (safe error messages).
* **Dependencies:** `core/Middleware.php`, `app/models/User.php`, `core/Database.php`.
* **Relevant Requirement IDs:** `FR-AUTH-001` through `FR-AUTH-007`, `NFR-SEC-001` through `NFR-SEC-006`.
* **Responsible Architectural Layer:** Application Layer (`AuthController`, `core/Middleware`).

---

### Module 2: Employee Management (`MOD-EMP`)
* **Purpose:** Manage workforce profiles, personal records, and employment statuses throughout the employee lifecycle.
* **Responsible Actors:** Administrator/HR.
* **Major Operations:**
  * `index()`: List active employees with pagination and status filters.
  * `create()` / `store()`: Register new employee and trigger annual balance initialization.
  * `edit()` / `update()`: Modify employee contact, department, or job data.
  * `deactivate()`: Perform soft-deactivation (`employment_status = 'Inactive'`).
  * `show()`: Render comprehensive profile details.
* **Inputs:** Employee number, first/middle/last name, email, phone, gender, birth date, department ID, position ID, date hired, employment status.
* **Outputs:** Form views, employee records, status updates, flash notifications.
* **Business Rules:** `BR-001` (unique employee number), `BR-002` (valid department/position), `BR-003` (soft deactivation).
* **Dependencies:** `MOD-ORG`, `MOD-LBAL`, `MOD-LOG`.
* **Relevant Requirement IDs:** `FR-EMP-001` through `FR-EMP-006`, `NFR-INT-002`, `NFR-INT-003`.
* **Responsible Architectural Layer:** Application Layer (`EmployeeController`), Data Access Layer (`Employee.php`).

---

### Module 3: Department Management (`MOD-DEPT`)
* **Purpose:** Maintain organizational departments (e.g., IT, Finance, Operations, HR).
* **Responsible Actors:** Administrator/HR.
* **Major Operations:** `index()`, `create()`, `store()`, `edit()`, `update()`, `destroy()`.
* **Inputs:** Department name, description.
* **Outputs:** Department list, selection dropdown options, validation notices.
* **Business Rules:** `BR-004` (restrict deletion if active employees or positions are linked).
* **Dependencies:** `MOD-LOG`.
* **Relevant Requirement IDs:** `FR-ORG-001`, `FR-ORG-003`.
* **Responsible Architectural Layer:** Application Layer (`DepartmentController`), Data Access Layer (`Department.php`).

---

### Module 4: Position Management (`MOD-POS`)
* **Purpose:** Maintain job positions linked hierarchically to specific departments.
* **Responsible Actors:** Administrator/HR.
* **Major Operations:** `index()`, `create()`, `store()`, `edit()`, `update()`, `destroy()`.
* **Inputs:** Position name, department ID, job description.
* **Outputs:** Position directory, dynamic position dropdowns filtered by department.
* **Business Rules:** `BR-002`, `BR-004` (restrict deletion if employees hold the position).
* **Dependencies:** `MOD-DEPT`, `MOD-LOG`.
* **Relevant Requirement IDs:** `FR-ORG-002`, `FR-ORG-003`.
* **Responsible Architectural Layer:** Application Layer (`PositionController`), Data Access Layer (`Position.php`).

---

### Module 5: Leave Type Management (`MOD-LTYPE`)
* **Purpose:** Define and configure organizational leave categories and standard allocations.
* **Responsible Actors:** Administrator/HR.
* **Major Operations:** `index()`, `create()`, `store()`, `edit()`, `update()`.
* **Inputs:** Leave name (Vacation, Sick, Emergency, Maternity, Paternity), description, default allocated days, status (Active/Inactive).
* **Outputs:** Configured leave categories, allocation presets.
* **Business Rules:** Cannot deactivate a leave type with active pending filings.
* **Dependencies:** `MOD-LOG`.
* **Relevant Requirement IDs:** `FR-LBAL-001`.
* **Responsible Architectural Layer:** Application Layer (`LeaveController`), Data Access Layer (`Leave.php`).

---

### Module 6: Leave Balance Management (`MOD-LBAL`)
* **Purpose:** Maintain annual leave balances (allocated, used, remaining) for each employee and leave type.
* **Responsible Actors:** Administrator/HR (manage/view all), Employee (view own).
* **Major Operations:**
  * `initializeBalances()`: Seed annual records for a fiscal year.
  * `getBalance()`: Query current balance (`remaining_days = allocated_days - used_days`).
  * `adjustBalance()`: Administrative manual balance adjustment with audit logging.
* **Inputs:** Employee ID, leave type ID, year, allocated days adjustment.
* **Outputs:** Balance summaries, entitlement cards.
* **Business Rules:** `BR-010` (annual scoping), `FR-LBAL-004` (`remaining = allocated - used`).
* **Dependencies:** `MOD-EMP`, `MOD-LTYPE`, `MOD-LOG`.
* **Relevant Requirement IDs:** `FR-LBAL-002`, `FR-LBAL-003`, `FR-LBAL-004`.
* **Responsible Architectural Layer:** Application Layer (`LeaveController`), Data Access Layer (`Leave.php`).

---

### Module 7: Leave Request and Validation (`MOD-LREQ`)
* **Purpose:** Facilitate employee leave filing with strict server-side validation.
* **Responsible Actors:** Employee.
* **Major Operations:**
  * `apply()`: Render leave application form with balance indicators.
  * `submit()`: Compute working days, validate date order, check sufficient balance, check collision with existing bookings.
* **Inputs:** Leave type ID, start date, end date, reason, CSRF token.
* **Outputs:** Validated pending leave request record, validation feedback.
* **Business Rules:** `BR-LEAVE-001` through `BR-LEAVE-007` (working days excl. weekends, start $\le$ end, no overlapping dates).
* **Dependencies:** `MOD-LBAL`, `MOD-NOTIF`, `MOD-LOG`.
* **Relevant Requirement IDs:** `FR-LEAVE-001` through `FR-LEAVE-006`.
* **Responsible Architectural Layer:** Application Layer (`LeaveController`), Data Access Layer (`Leave.php`).

---

### Module 8: Leave Approval, Rejection, and Cancellation (`MOD-LPROC`)
* **Purpose:** Execute state transitions on pending leave requests with ACID transaction guarantees.
* **Responsible Actors:** Administrator/HR (approve, reject), Employee (cancel own pending).
* **Major Operations:**
  * `approve()`: Re-verify balance and status inside PDO transaction, update status to `Approved`, deduct balance, write audit log, dispatch notification, commit.
  * `reject()`: Set status to `Rejected` without balance deduction; notify employee.
  * `cancel()`: Employee withdrawal of `Pending` requests; balance untouched.
* **Inputs:** Request ID, action decision (`approve`/`reject`/`cancel`), reason/remarks.
* **Outputs:** Updated request state, balance deductions, audit records.
* **Business Rules:** `BR-LEAVE-008` to `BR-LEAVE-014` (atomic transaction, single deduction guarantee).
* **Dependencies:** `MOD-LBAL`, `MOD-NOTIF`, `MOD-LOG`.
* **Relevant Requirement IDs:** `FR-LEAVE-007`, `FR-LEAVE-008`, `FR-LEAVE-009`, `NFR-INT-001`.
* **Responsible Architectural Layer:** Application Layer (`LeaveController`), Data Access Layer (`Leave.php`, `core/Database.php`).

---

### Module 9: Leave History (`MOD-LHIST`)
* **Purpose:** Present chronological historical records of past and active leave applications.
* **Responsible Actors:** Administrator/HR (all employees), Employee (own records).
* **Major Operations:**
  * `employeeHistory()`: Filter leave requests for logged-in employee.
  * `globalHistory()`: Filter leave requests across all organizational units.
* **Inputs:** Filter parameters (status, leave type, date range).
* **Outputs:** Chronological tabular view; custom `LinkedList` representation.
* **Business Rules:** `BR-006` (strict horizontal isolation for employees).
* **Dependencies:** `MOD-LREQ`, `app/dsa/structures/LinkedList.php`.
* **Relevant Requirement IDs:** `FR-LEAVE-010`, `FR-DSA-007`.
* **Responsible Architectural Layer:** Application Layer (`LeaveController`), Presentation Layer (`views/leaves/history.php`).

---

### Module 10: Search and Sorting (`MOD-SRT`)
* **Purpose:** Provide in-memory employee filtering and ordering using custom PHP DSA implementations.
* **Responsible Actors:** Administrator/HR.
* **Major Operations:**
  * `search()`: Execute `LinearSearch` or `BinarySearch` (with pre-sorting) or `HashTable` lookup.
  * `sort()`: Execute `BubbleSort`, `SelectionSort`, `InsertionSort`, `MergeSort`, or `QuickSort` by ID, Name, Department, Date Hired.
* **Inputs:** Search key, target attribute, selected algorithm identifier.
* **Outputs:** Ordered/filtered employee collection, step comparison counters, execution duration.
* **Business Rules:** `DSA Restrictions` (no PHP `sort()` or MySQL `ORDER BY` for demonstrated DSA tasks).
* **Dependencies:** `MOD-EMP`, `app/dsa/algorithms/*`.
* **Relevant Requirement IDs:** `FR-EMP-006`, `FR-DSA-002`, `FR-DSA-003`, `FR-DSA-004`.
* **Responsible Architectural Layer:** Application Layer (`DsaController`), DSA Engine (`app/dsa/`).

---

### Module 11: Reports (`MOD-REP`)
* **Purpose:** Aggregate and generate workforce, leave utilization, and departmental statistical reports.
* **Responsible Actors:** Administrator/HR.
* **Major Operations:**
  * `employeeReport()`: Headcount and status breakdown.
  * `leaveReport()`: Leave utilization across types and date intervals.
  * `departmentReport()`: Departmental leave consumption and active headcount.
* **Inputs:** Date intervals, department filter, leave type filter.
* **Outputs:** Structured printable summary tables and aggregate statistics.
* **Business Rules:** Restricted to administrative access only.
* **Dependencies:** `MOD-EMP`, `MOD-ORG`, `MOD-LREQ`.
* **Relevant Requirement IDs:** `FR-REP-001`, `FR-REP-002`, `FR-REP-003`.
* **Responsible Architectural Layer:** Application Layer (`ReportController`), Presentation Layer (`views/reports/*`).

---

### Module 12: Notifications (`MOD-NOTIF`)
* **Purpose:** Generate, store, and display in-system notification alerts regarding workflow events.
* **Responsible Actors:** Administrator/HR, Employee.
* **Major Operations:**
  * `createNotification()`: Insert notification record (`user_id`, `message`, `type`, `is_read = 0`).
  * `getUnread()`: Query unread notification badge count.
  * `markAsRead()`: Update `is_read = 1`.
* **Inputs:** Target user ID, notification text, notification category.
* **Outputs:** Notification dropdown feed, alert badges.
* **Business Rules:** `ASM-004` (in-system notifications only; no external SMS/email).
* **Dependencies:** `MOD-AUTH`, `app/models/Notification.php`.
* **Relevant Requirement IDs:** `FR-NOTIF-001`, `FR-NOTIF-002`.
* **Responsible Architectural Layer:** Application Layer (`NotificationController`), Data Access Layer (`Notification.php`).

---

### Module 13: Activity Logs (`MOD-LOG`)
* **Purpose:** Maintain an immutable audit trail of all sensitive and state-changing actions.
* **Responsible Actors:** Administrator/HR (viewing).
* **Major Operations:**
  * `logAction()`: Record user ID, action verb, target entity, entity ID, description, IP, User Agent.
  * `index()`: Display chronological audit trail with pagination and filters.
  * `recentStack()`: Populate custom LIFO `Stack` to display recent administrative actions.
* **Inputs:** Actor metadata, action name, target record details.
* **Outputs:** Tabular audit table; top-N recent actions from Stack.
* **Business Rules:** `BR-009` (mandatory auditing of all state changes).
* **Dependencies:** `MOD-AUTH`, `app/dsa/structures/Stack.php`.
* **Relevant Requirement IDs:** `FR-LOG-001`, `FR-DSA-005`.
* **Responsible Architectural Layer:** Application Layer (`ActivityLogController`), Data Access Layer (`ActivityLog.php`).

---

### Module 14: DSA Laboratory (`MOD-DSALAB`)
* **Purpose:** Provide an interactive educational workbench to inspect and visualize data structures and algorithms in action.
* **Responsible Actors:** Administrator/HR, Academic Evaluators.
* **Major Operations:**
  * `playground()`: Interactive controls to run individual structures (BST insert/traverse, Stack push/pop, Queue enqueue/dequeue, HashTable collision resolution).
  * `visualizer()`: Step-by-step trace of sorting and searching passes.
* **Inputs:** Test keys, operation triggers.
* **Outputs:** Graphical visualizer of tree nodes, queue states, stack frames, hash buckets.
* **Business Rules:** Fully isolated custom PHP implementations.
* **Dependencies:** `app/dsa/*`.
* **Relevant Requirement IDs:** `FR-DSA-001` through `FR-DSA-0011`.
* **Responsible Architectural Layer:** Application Layer (`DsaController`), Presentation Layer (`views/dsa/*`).

---

### Module 15: Performance Testing and Evaluation (`MOD-PERF`)
* **Purpose:** Benchmark execution times and operational complexities across varying dataset scales.
* **Responsible Actors:** Administrator/HR, Academic Evaluators.
* **Major Operations:**
  * `generateBenchmarkData()`: Create in-memory synthetic employee datasets ($N = 10, 100, 500, 1000, 5000, 10000$).
  * `benchmarkSearch()`: Comparative timing of Linear vs Binary vs Hash Table search.
  * `benchmarkSort()`: Comparative timing and swap counting of Bubble, Selection, Insertion, Merge, and Quick Sort.
* **Inputs:** Sample scale $N$, iteration count.
* **Outputs:** Tabular execution matrix ($\mu s$, comparisons, swaps, memory delta) and comparison graphs.
* **Business Rules:** Preload data to eliminate disk/DB I/O skew; use `hrtime(true)`.
* **Dependencies:** `MOD-DSALAB`.
* **Relevant Requirement IDs:** `FR-PERF-001` through `FR-PERF-004`, `NFR-PERF-001`, `NFR-PERF-002`.
* **Responsible Architectural Layer:** Application Layer (`DsaController`), DSA Engine (`app/dsa/`).
