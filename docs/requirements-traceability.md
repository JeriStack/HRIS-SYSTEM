# Requirements Traceability Matrix (RTM)
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Cross-Phase Tracking (Phase 1 & Phase 2)  
**Date:** 2026-10-11  
**Status:** Approved for Baseline Design  

---

## 1. Overview and Purpose

The **Requirements Traceability Matrix (RTM)** establishes bidirectional traceability between system requirements, architecture components, application modules, workflow diagrams, use cases, and downstream verification test cases.

---

## 2. Functional Requirements Traceability

| Requirement ID | Module | Architectural Layer & Responsible Class | Use Case ID | Design Diagram / Workflow | Verification Test Case ID |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `FR-AUTH-001` (Login) | `MOD-AUTH` | App Layer: `AuthController`, `core/Router` | `UC-AUTH-01` | `authentication-workflow.mmd` | `TC-001`, `TC-002` |
| `FR-AUTH-002` (Password Hash) | `MOD-AUTH` | App Layer: `app/models/User.php` | `UC-AUTH-01` | `authentication-workflow.mmd` | `TC-001`, `TC-SEC-01` |
| `FR-AUTH-003` (Session Regen) | `MOD-AUTH` | App Layer: `AuthController` | `UC-AUTH-01` | `authentication-workflow.mmd` | `TC-SEC-02` |
| `FR-AUTH-004` (Timeout) | `MOD-AUTH` | App Layer: `core/Middleware.php` | `UC-AUTH-01` | `authentication-workflow.mmd` | `TC-SEC-03` |
| `FR-AUTH-005` (Logout) | `MOD-AUTH` | App Layer: `AuthController` | `UC-AUTH-02` | `authentication-workflow.mmd` | `TC-001` |
| `FR-AUTH-006` (RBAC) | `MOD-AUTH` | App Layer: `core/Middleware.php` | `UC-AUTH-01` | `system-flowchart.mmd` | `TC-SEC-04` |
| `FR-AUTH-007` (Access Denial) | `MOD-AUTH` | App Layer: `core/Middleware.php` | `UC-AUTH-01` | `system-flowchart.mmd` | `TC-SEC-05` |
| `FR-EMP-001` (Add Employee) | `MOD-EMP` | App/Data Layer: `EmployeeController`, `Employee.php` | `UC-EMP-01` | `employee-workflows.mmd` | `TC-003` |
| `FR-EMP-002` (Unique Emp No) | `MOD-EMP` | Data Layer: `Employee.php`, MySQL `UNIQUE` | `UC-EMP-01` | `employee-workflows.mmd` | `TC-003`, `TC-INT-01` |
| `FR-EMP-003` (Edit Employee) | `MOD-EMP` | App/Data Layer: `EmployeeController`, `Employee.php` | `UC-EMP-01` | `employee-workflows.mmd` | `TC-004` |
| `FR-EMP-004` (Soft Deactivate)| `MOD-EMP` | App/Data Layer: `EmployeeController`, `Employee.php` | `UC-EMP-02` | `employee-workflows.mmd` | `TC-005` |
| `FR-EMP-005` (Profile View) | `MOD-EMP` | App/Pres Layer: `EmployeeController`, `show.php` | `UC-EMP-01` | `employee-workflows.mmd` | `TC-FUNC-01` |
| `FR-EMP-006` (Directory View)| `MOD-EMP` | App/Pres Layer: `EmployeeController`, `directory.php`| `UC-EMP-03` | `employee-workflows.mmd` | `TC-FUNC-02` |
| `FR-ORG-001` (Dept CRUD) | `MOD-DEPT` | App/Data Layer: `DepartmentController`, `Department.php`| `UC-ORG-01` | `architecture-diagram.mmd` | `TC-FUNC-03` |
| `FR-ORG-002` (Position CRUD) | `MOD-POS` | App/Data Layer: `PositionController`, `Position.php` | `UC-ORG-01` | `architecture-diagram.mmd` | `TC-FUNC-04` |
| `FR-ORG-003` (Org Integrity) | `MOD-ORG` | Data Layer: MySQL `FOREIGN KEY ON DELETE RESTRICT` | `UC-ORG-01` | `architecture-diagram.mmd` | `TC-INT-02` |
| `FR-LBAL-001` (Leave Types) | `MOD-LTYPE` | App/Data Layer: `LeaveController`, `Leave.php` | `UC-LEAVE-01`| `leave-management-workflow.mmd` | `TC-FUNC-05` |
| `FR-LBAL-002` (Seed Balances)| `MOD-LBAL` | App/Data Layer: `Leave.php`, `Employee.php` | `UC-EMP-01` | `leave-management-workflow.mmd` | `TC-FUNC-06` |
| `FR-LBAL-003` (Balance View) | `MOD-LBAL` | App/Pres Layer: `LeaveController`, `balance.php` | `UC-LEAVE-01`| `employee-workflows.mmd` | `TC-FUNC-07` |
| `FR-LBAL-004` (Balance Formula)| `MOD-LBAL`| App/Data Layer: `Leave.php` | `UC-LEAVE-02`| `leave-management-workflow.mmd` | `TC-FUNC-08` |
| `FR-LEAVE-001` (Leave Form) | `MOD-LREQ` | App/Pres Layer: `LeaveController`, `apply.php` | `UC-LEAVE-01`| `leave-management-workflow.mmd` | `TC-006` |
| `FR-LEAVE-002` (Date Validation)| `MOD-LREQ`| App Layer: `LeaveValidationService` | `UC-LEAVE-01`| `leave-management-workflow.mmd` | `TC-006` |
| `FR-LEAVE-003` (Workday Calc)| `MOD-LREQ` | App Layer: `LeaveValidationService` | `UC-LEAVE-01`| `leave-management-workflow.mmd` | `TC-006` |
| `FR-LEAVE-004` (Balance Check)| `MOD-LREQ`| App Layer: `LeaveValidationService` | `UC-LEAVE-01`| `leave-management-workflow.mmd` | `TC-007` |
| `FR-LEAVE-005` (Overlap Check)| `MOD-LREQ`| App/Data Layer: `Leave.php` | `UC-LEAVE-01`| `leave-management-workflow.mmd` | `TC-008` |
| `FR-LEAVE-006` (Initial Status)| `MOD-LREQ`| App/Data Layer: `Leave.php` | `UC-LEAVE-01`| `leave-management-workflow.mmd` | `TC-006` |
| `FR-LEAVE-007` (Cancellation)| `MOD-LPROC`| App/Data Layer: `LeaveController`, `Leave.php` | `UC-LEAVE-04`| `leave-management-workflow.mmd` | `TC-010` |
| `FR-LEAVE-008` (ACID Approval)| `MOD-LPROC`| App/Data Layer: `LeaveController`, `Database.php` | `UC-LEAVE-02`| `leave-management-workflow.mmd` | `TC-009` |
| `FR-LEAVE-009` (Rejection) | `MOD-LPROC`| App/Data Layer: `LeaveController`, `Leave.php` | `UC-LEAVE-03`| `leave-management-workflow.mmd` | `TC-FUNC-09` |
| `FR-LEAVE-010` (History) | `MOD-LHIST`| App/Pres Layer: `LeaveController`, `history.php` | `UC-LEAVE-01`| `leave-management-workflow.mmd` | `TC-FUNC-10` |
| `FR-REP-001` (Employee Report)| `MOD-REP` | App/Pres Layer: `ReportController`, `employee.php`| `UC-REP-01` | `system-flowchart.mmd` | `TC-FUNC-11` |
| `FR-REP-002` (Leave Report) | `MOD-REP` | App/Pres Layer: `ReportController`, `leave.php` | `UC-REP-01` | `system-flowchart.mmd` | `TC-FUNC-12` |
| `FR-REP-003` (Dept Report) | `MOD-REP` | App/Pres Layer: `ReportController`, `dept.php` | `UC-REP-01` | `system-flowchart.mmd` | `TC-FUNC-13` |
| `FR-NOTIF-001` (In-Sys Notif)| `MOD-NOTIF`| App/Data Layer: `Notification.php` | `UC-NOTIF-01`| `leave-management-workflow.mmd` | `TC-FUNC-14` |
| `FR-LOG-001` (Audit Logs) | `MOD-LOG` | App/Data Layer: `ActivityLog.php` | `UC-LOG-01` | `system-flowchart.mmd` | `TC-FUNC-15` |
| `FR-DSA-001` (Array Wrapper) | `MOD-DSALAB`| DSA Engine: `app/dsa/structures/ArrayWrapper.php`| `UC-DSA-01` | `dsa-to-hris-mapping.md` | `TC-DSA-001` |
| `FR-DSA-002` (Linear Search) | `MOD-SRT` | DSA Engine: `app/dsa/algorithms/LinearSearch.php`| `UC-EMP-03` | `dsa-to-hris-mapping.md` | `TC-DSA-001` |
| `FR-DSA-003` (Binary Search) | `MOD-SRT` | DSA Engine: `app/dsa/algorithms/BinarySearch.php`| `UC-EMP-03` | `dsa-to-hris-mapping.md` | `TC-DSA-002` |
| `FR-DSA-004` (Sorting Algos) | `MOD-SRT` | DSA Engine: `BubbleSort`, `Selection`, `Merge`, `Quick`| `UC-EMP-03`| `dsa-to-hris-mapping.md` | `TC-DSA-003` |
| `FR-DSA-005` (Stack LIFO) | `MOD-LOG` | DSA Engine: `app/dsa/structures/Stack.php` | `UC-LOG-01` | `dsa-to-hris-mapping.md` | `TC-DSA-005` |
| `FR-DSA-006` (Queue FIFO) | `MOD-LPROC`| DSA Engine: `app/dsa/structures/Queue.php` | `UC-LEAVE-02`| `leave-management-workflow.mmd` | `TC-DSA-004` |
| `FR-DSA-007` (Linked List) | `MOD-LHIST`| DSA Engine: `app/dsa/structures/LinkedList.php` | `UC-LEAVE-01`| `dsa-to-hris-mapping.md` | `TC-DSA-006` |
| `FR-DSA-008` (Hash Table) | `MOD-EMP` | DSA Engine: `app/dsa/structures/HashTable.php` | `UC-EMP-03` | `dsa-to-hris-mapping.md` | `TC-DSA-007` |
| `FR-DSA-009` (BST) | `MOD-EMP` | DSA Engine: `app/dsa/structures/BST.php` | `UC-EMP-03` | `dsa-to-hris-mapping.md` | `TC-DSA-008` |
| `FR-PERF-001` (Benchmarks) | `MOD-PERF` | App/DSA: `DsaController`, `views/dsa/visualizer.php`| `UC-DSA-01`| `dsa-to-hris-mapping.md` | `TC-PERF-01` |

---

## 3. Non-Functional Requirements Traceability

| NFR ID | Category | Implementation & Enforcement Point | Verification Strategy |
| :--- | :--- | :--- | :--- |
| `NFR-SEC-001` | Passwords | `password_hash()` in `User.php` | Unit test checking non-plaintext DB hashes |
| `NFR-SEC-002` | Prepared Statements | PDO parameter binding in `core/Model.php` | Code review and automated AST query scanner |
| `NFR-SEC-003` | CSRF Security | `Middleware::validateCsrf()` on all POST/PUT/DELETE | Automated POST test without CSRF token |
| `NFR-SEC-004` | XSS Escaping | `htmlspecialchars()` in `core/functions.php` | Test rendering HTML/script tag inputs |
| `NFR-SEC-005` | RBAC Enforcement | `Middleware::authorize()` in controller dispatch | Test Employee user invoking `/admin/...` |
| `NFR-INT-001` | ACID Transactions | `$pdo->beginTransaction()` in `LeaveController::approve()` | Simulation of mid-approval PDO exception |
| `NFR-INT-002` | Foreign Keys | `ON DELETE RESTRICT` constraints in MySQL schema | Integration test attempting deletion of parent |
| `NFR-PERF-001`| In-Memory Timing | In-memory synthetic dataset loading before timer start | Benchmark inspection in DSA Lab |
| `NFR-PERF-002`| High-Res Timer | Native PHP `hrtime(true)` | Verification of nanosecond return value |
| `NFR-USA-001` | Feedback State | CSS classes `.alert-success`, `.alert-error` | Visual UI testing on form submissions |
| `NFR-USA-002` | Destructive Modal | Native JS modal dialogs with explicit confirm button | UI test on employee deactivation button |
| `NFR-MNT-001` | MVC Separation | Clean separation in `core/`, `app/`, `public/` directories | Structural linting and code architecture audit |
