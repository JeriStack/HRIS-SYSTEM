# Relational Database Design Specification
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 3 — Database Design and Setup  
**Database Name:** `hris_db`  
**Storage Engine:** InnoDB  
**Character Set & Collation:** `utf8mb4` / `utf8mb4_unicode_ci`  
**Status:** Implemented & Verified in MySQL  

---

## 1. Architectural Strategy & Design Rationale

The database architecture is engineered to provide a robust, ACID-compliant relational foundation that supports operational HR operations while serving as the durable source of truth for in-memory Computer Science Data Structures & Algorithms (DSA).

```
+---------------------------------------------------------------------------------+
|                       DATABASE LAYER DESIGN PRINCIPLES                          |
+---------------------------------------------------------------------------------+
| 1. Third Normal Form (3NF): Eliminates data redundancy and update anomalies.    |
| 2. Strict Referential Integrity: Enforces foreign key relationships with        |
|    ON DELETE RESTRICT to preserve historical employment and leave records.      |
| 3. Soft-Deactivation: Prohibits destructive cascading deletes of employees.    |
| 4. ACID Leave Transactions: Employs InnoDB row locks (SELECT ... FOR UPDATE)     |
|    to guarantee atomic consistency during approval balance deductions.          |
| 5. Clear Persistence Boundary: MySQL stores durable state; custom PHP DSA       |
|    structures manage in-memory search, sort, and queue representations.         |
+---------------------------------------------------------------------------------+
```

---

## 2. Core Relational Schema Overview

The database is built on the approved **eight core tables**, plus one explicitly documented **schema extension** for in-system notifications:

| # | Table Name | Business Purpose | Key Integrity Constraints |
| :-: | :--- | :--- | :--- |
| 1 | `departments` | Organizational divisions / departments | `UNIQUE (department_name)` |
| 2 | `positions` | Job titles and functional roles within departments | `FK (department_id)`, `UNIQUE (department_id, position_name)` |
| 3 | `employees` | Centralized workforce master directory | `UNIQUE (employee_no)`, `UNIQUE (email)`, `FK (dept, pos)` |
| 4 | `users` | System login credentials, hashed passwords, access roles | `UNIQUE (username)`, `UNIQUE (employee_id)`, `FK (employee_id)` |
| 5 | `leave_types` | Leave classifications and baseline annual allocations | `UNIQUE (leave_name)` |
| 6 | `leave_balances` | Annual quota tracking per employee per leave type | `FK (emp, type)`, `UNIQUE (emp, type, year)`, `CHECK (>= 0)` |
| 7 | `leave_requests` | Lifecycle of filed leave applications | `FK (emp, type, approver)`, `CHECK (start <= end, days > 0)` |
| 8 | `activity_logs` | Immutable audit trail for security and state changes | `FK (user_id ON DELETE SET NULL)` |
| * | `notifications` | *(Approved Extension)* In-system alerts feed | `FK (user_id ON DELETE CASCADE)` |

---

## 3. Storage Engine & Internationalization Standards

* **Engine:** `InnoDB` is enforced across all tables to support transactional rollbacks (`BEGIN TRANSACTION`, `COMMIT`, `ROLLBACK`), foreign-key constraints, and row-level locking.
* **Encoding:** `utf8mb4` with `utf8mb4_unicode_ci` provides complete international character support, including Asian characters, Spanish orthography, and accented names common in workforce registries.

---

## 4. Separation of Persistence and Data Structures

A fundamental requirement of this academic software project is distinguishing between persistent storage and application-level algorithmic demonstrations:

```
        MySQL Relational Storage (hris_db)
                         |
                         | PDO Prepared Query ($stmt->fetchAll())
                         v
        PHP In-Memory Heap
                         |
                         +---> ArrayWrapper & LinkedList (Employee / Leave History)
                         +---> Stack (Recent Activity Audit Logs)
                         +---> Queue (FIFO Pending Leave Approval Pipeline)
                         +---> HashTable (Polynomial Hashed O(1) Employee Lookup)
                         +---> BinarySearchTree (Ordered Directory & In-Order Traversal)
                         +---> Sorting Engines (Bubble, Selection, Insertion, Merge, Quick)
```

MySQL ensures that data persists permanently across requests, while custom PHP classes implement fundamental Computer Science structures directly in application memory.
