# Database Relationship & Cardinality Specification
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 3 — Database Design and Setup  
**Database:** `hris_db`  
**Status:** Approved Baseline  

---

## 1. Relational Cardinality & Participation Matrix

The database schema models organizational and leave processes through 10 explicit foreign-key relationships. Each relationship is detailed below with its cardinality, optionality, business meaning, and referential constraints:

| Parent Table & Key | Child Table & Foreign Key | Cardinality | Parent Participation | Child Participation | Business Meaning |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `departments.id` | `positions.department_id` | $1 : N$ | Optional | Mandatory | A department may host zero or many positions; every position must belong to exactly one department. |
| `departments.id` | `employees.department_id` | $1 : N$ | Optional | Mandatory | A department may employ zero or many employees; an employee must be assigned to one department. |
| `positions.id` | `employees.position_id` | $1 : N$ | Optional | Mandatory | A position title may be held by zero or many employees; an employee holds one designated position. |
| `employees.id` | `users.employee_id` | $1 : 1$ | Optional | Optional | An employee may have at most one user account; a user account may link to at most one employee (standalone admin has NULL). |
| `employees.id` | `leave_balances.employee_id` | $1 : N$ | Optional | Mandatory | An employee holds multiple annual balance records (one per leave type per year). |
| `leave_types.id` | `leave_balances.leave_type_id` | $1 : N$ | Optional | Mandatory | A leave type categorizes multiple employee balance records. |
| `employees.id` | `leave_requests.employee_id` | $1 : N$ | Optional | Mandatory | An employee may submit multiple leave requests over time. |
| `leave_types.id` | `leave_requests.leave_type_id` | $1 : N$ | Optional | Mandatory | A leave type governs multiple submitted requests. |
| `users.id` | `leave_requests.approved_by` | $1 : N$ | Optional | Optional | An administrative user may approve/reject many leave requests; pending requests have `approved_by = NULL`. |
| `users.id` | `activity_logs.user_id` | $1 : N$ | Optional | Optional | A user generates many activity log entries; system-level automated events have `user_id = NULL`. |
| `users.id` | `notifications.user_id` | $1 : N$ | Optional | Mandatory | A user account receives multiple workflow notification alerts. |

---

## 2. In-Depth Relationship Analysis

### 2.1 Department to Position Linkage
* **Parent:** `departments` (PK: `id`)
* **Child:** `positions` (FK: `department_id`)
* **Constraint:** `CONSTRAINT fk_positions_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT ON UPDATE CASCADE`
* **Composite Uniqueness:** `UNIQUE KEY uq_positions_dept_pos (department_id, position_name)`
* **Business Rule:** Position titles are unique within their parent department. A department cannot be deleted if active or inactive job positions are linked to it.

### 2.2 Department & Position to Employee Linkage
* **Parent:** `departments`, `positions`
* **Child:** `employees`
* **Constraint:** Both foreign keys enforce `ON DELETE RESTRICT`.
* **Business Rule:** If an employee is currently assigned to a department or position, that organizational entity cannot be deleted. Departments must be reassigned or cleared prior to structural changes.

### 2.3 Employee to User Account Mapping (1-to-1)
* **Parent:** `employees` (PK: `id`)
* **Child:** `users` (FK: `employee_id`)
* **Constraint:** `CONSTRAINT uq_users_employee UNIQUE (employee_id)` enforces a strict $1:1$ invariant. No two user logins can be linked to the same employee.
* **Optionality:** `employee_id` is defined as nullable (`INT UNSIGNED NULL`) to allow system administrators (`admin`) to operate without requiring an underlying employee personnel file.

### 2.4 Composite Employee Leave Balances
* **Associative Entity:** `leave_balances` acts as a composite bridge connecting `employees` and `leave_types` across fiscal years.
* **Constraint:** `CONSTRAINT uq_leave_balances_emp_type_year UNIQUE (employee_id, leave_type_id, year)` guarantees that an employee receives exactly one balance ledger per leave category per fiscal year.

### 2.5 Leave Request Approval Linkage
* **Parent:** `users` (PK: `id`)
* **Child:** `leave_requests` (FK: `approved_by`)
* **Lifecycle:** When a request is filed, `approved_by` and `approved_at` are `NULL`. Upon approval or rejection, the authenticated administrative user's ID is recorded to preserve audit accountability.
