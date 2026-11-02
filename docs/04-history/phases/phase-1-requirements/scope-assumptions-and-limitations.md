# Project Scope, Assumptions, and Limitations
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 1 — Requirements and Project Initiation  
**Date:** 2026-10-11  
**Status:** Approved for Baseline Design  

---

## 1. Project Scope Specification

To guarantee project delivery within academic timelines, clear boundaries have been established between in-scope operational capabilities and out-of-scope enterprise features.

### 1.1 In-Scope Capabilities
* **Core Employee Management:** Centralized registry of employees, personal details, contact data, organizational placement, and lifecycle status (Active/Inactive).
* **Organization Hierarchy:** Management of organizational units (Departments) and job classifications (Positions).
* **Leave Management Lifecycle:** Configuration of leave policies, annual quotas, employee leave requests, date validation, working-day duration computation, and balance checks.
* **ACID Leave Approvals:** Transactional approval and rejection mechanics with balance synchronization.
* **Audit Trails & Security:** Comprehensive activity logs, session security, password hashing, and in-system notification alerts.
* **Reporting Module:** Structured summary reports for employees, leave consumption, and department statistics.
* **Custom DSA Implementation:** Native PHP implementations of:
  * Linear Search, Binary Search
  * Bubble Sort, Selection Sort, Insertion Sort, Merge Sort, Quick Sort
  * Array Wrapper, Singly Linked List, LIFO Stack, FIFO Queue, Chained Hash Table, Binary Search Tree (BST)
  * Recursive traversals and complexity analysis.
* **Performance Testing Laboratory:** Interactive benchmarking workbench measuring execution times, comparison counts, and memory footprints across variable sample sizes ($N = 10$ to $10,000$).

### 1.2 Explicitly Out-of-Scope Features
The following features are common in commercial HRIS software but are **strictly excluded** from this academic project:
* **Payroll & Compensation Management:** Salary computation, tax withholding, payslip generation, deductions, and banking integration.
* **Recruitment & Applicant Tracking (ATS):** Job postings, applicant resumes, interview scheduling, and candidate scoring.
* **Biometric Hardware Integration:** Physical RFID/fingerprint scanner hardware drivers or real-time attendance clock-in punch cards.
* **Government Regulatory Reporting:** Direct automated generation of localized tax or social insurance forms.
* **External Communication Gateways:** Third-party SMS gateways (e.g., Twilio) or SMTP email server integration. Notifications remain strictly internal/in-system.
* **Mobile Native Applications:** Native Android or iOS clients (the web UI will be responsive to mobile viewports instead).
* **Multi-Tier Approval Hierarchies:** Multi-level approval chains (e.g., Team Lead -> Manager -> Director). Approvals are handled directly by Administrator/HR.

---

## 2. Explicit Baseline Assumptions & Design Decisions

Ten key assumptions identified in the approved project plan provide the baseline for system implementation:

| Assumption ID | Area | Baseline Design Assumption | Impact / Required Confirmation |
| :--- | :--- | :--- | :--- |
| `ASM-001` | Working-Day Calendar | Leave duration counts Monday through Friday (excluding Saturdays and Sundays). | Public holidays are excluded from computation unless a dedicated holiday calendar table is later approved. |
| `ASM-002` | Delete Behavior | Deletions favor record preservation over cascading removal. Employees are soft-deactivated (`employment_status = 'Inactive'`). | MySQL queries filtering active employees must specify `WHERE employment_status = 'Active'`. |
| `ASM-003` | Organizational Deletion | Deleting departments or positions with associated employee records is prohibited. | Enforced by MySQL `ON DELETE RESTRICT` foreign keys. |
| `ASM-004` | Notification Delivery | Notifications are rendered via an in-system database feed (`notifications` table) with unread status badges. | Eliminates dependency on external email/SMS infrastructure. |
| `ASM-005` | Custom Queue Mechanics | The custom PHP `Queue` must use front/rear pointers or a circular array index. | Strictly avoids PHP `array_shift()`, ensuring educational validity and true $O(1)$ amortized queue operations. |
| `ASM-006` | Hash Table Collision Handling | The custom PHP `HashTable` must implement a manual hash function and explicit collision handling (separate chaining with linked buckets). | Prohibits simply wrapping native PHP associative arrays (`$arr[$key]`) and labeling it as a hash table. |
| `ASM-007` | Persistence vs. DSA Separation | MySQL is the sole persistent source of truth. Data is fetched via PDO into PHP memory, populated into custom DSA structures, processed algorithmically, and displayed. | MySQL and DSA are complementary: database manages durable state; PHP DSA manages in-memory logic. |
| `ASM-008` | Session Timeout | Inactivity session timeout is set to a default of 30 minutes. | Configured via `config/config.php` (`SESSION_LIFETIME = 1800`). |
| `ASM-009` | Role Hierarchy | Administrator and HR users share an identical administrative role privilege set in Phase 1 and 2. | Single role group (`admin`/`hr`) simplifies authorization logic without compromising future granular separation. |
| `ASM-010` | Benchmark Timing | High-resolution timer `hrtime(true)` will be used for microsecond accuracy in DSA performance tests. | Minimizes operating system clock drift during micro-benchmarks. |

---

## 3. Project Risks & Mitigation Strategies

```
+------------------------------------+---------------------------------------------------------------+
| RISK                               | MITIGATION STRATEGY                                           |
+------------------------------------+---------------------------------------------------------------+
| Concurrent leave approval race     | Enforce atomic PDO database transactions with row-level locks |
| condition causing balance overdraft| (SELECT ... FOR UPDATE) and recheck balance prior to commit.  |
+------------------------------------+---------------------------------------------------------------+
| PHP memory exhaustion during       | Set sensible upper bounds on benchmark dataset sizes (N=10k)  |
| 10,000-element benchmarks          | and configure memory limits appropriately (memory_limit=256M).|
+------------------------------------+---------------------------------------------------------------+
| Confusion between database sorting | Maintain strict physical separation: core CRUD uses standard  |
| and DSA sorting demonstrations     | PDO queries; DSA demonstrations reside in /app/dsa/ modules.  |
+------------------------------------+---------------------------------------------------------------+
| SQL Injection or CSRF vulnerabilities| Enforce parameterized PDO queries globally; generate and verify|
|                                    | CSRF tokens on all POST/PUT/DELETE forms.                     |
+------------------------------------+---------------------------------------------------------------+
```

---

## 4. Technical Constraints

1. **Host Environment:** Must operate locally on standard XAMPP stack (Apache 2.4+, PHP 8.1+, MySQL 8.0+ / MariaDB 10.4+).
2. **Framework Restrictions:** No heavy commercial PHP frameworks (e.g., Laravel, Symfony) are permitted. All routing, controllers, models, and views must be built natively in PHP to satisfy academic evaluation requirements.
3. **Frontend Independence:** Core functionality must operate using standard HTML5/CSS3 and native JavaScript without requiring npm build toolchains.
