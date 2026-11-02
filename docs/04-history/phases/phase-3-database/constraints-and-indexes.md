# Database Constraints & Indexes Specification
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 3 — Database Design and Setup  
**Database:** `hris_db`  
**Status:** Approved Baseline  

---

## 1. Primary & Unique Integrity Constraints

To guarantee uncompromised data integrity at the storage engine level, the schema implements declarative constraints on all critical identifiers:

| Constraint Name | Target Table | Target Columns | Constraint Type | Enforcement Rationale |
| :--- | :--- | :--- | :---: | :--- |
| `PRIMARY` | All tables | `id` | `PRIMARY KEY` | Surrogate unsigned auto-incrementing identifier providing stable row addressing. |
| `uq_departments_name` | `departments` | `department_name` | `UNIQUE` | Prevents redundant department creation (e.g. duplicate "IT"). |
| `uq_positions_dept_pos` | `positions` | `department_id, position_name` | `UNIQUE` | Enforces that position titles are unique within their specific department. |
| `uq_employees_emp_no` | `employees` | `employee_no` | `UNIQUE` | Guarantees that corporate employee IDs (e.g., `EMP001`) cannot be duplicated. |
| `uq_employees_email` | `employees` | `email` | `UNIQUE` | Prevents multiple personnel records from sharing the same email address. |
| `uq_users_username` | `users` | `username` | `UNIQUE` | Enforces uniqueness of user login credentials. |
| `uq_users_employee` | `users` | `employee_id` | `UNIQUE` | Enforces 1-to-1 mapping between employee profiles and user logins. |
| `uq_leave_types_name` | `leave_types` | `leave_name` | `UNIQUE` | Prevents redundant leave category titles. |
| `uq_leave_balances_emp_type_year` | `leave_balances` | `employee_id, leave_type_id, year` | `UNIQUE` | Guarantees exactly one quota record per employee, leave type, and fiscal year. |

---

## 2. Check Constraints (`CHECK`)

MySQL 8.0+ and MariaDB 10.4+ evaluate table check constraints to prevent mathematically invalid values:

| Constraint Name | Target Table | Expression | Business Validation Purpose |
| :--- | :--- | :--- | :--- |
| `chk_leave_balances_allocated` | `leave_balances` | `allocated_days >= 0` | Prohibits negative leave quota allocations. |
| `chk_leave_balances_used` | `leave_balances` | `used_days >= 0` | Prohibits negative leave usage figures. |
| `chk_leave_balances_remaining` | `leave_balances` | `remaining_days >= 0` | Prevents leave overdrafts at the database level. |
| `chk_leave_requests_dates` | `leave_requests` | `start_date <= end_date` | Rejects inverted or chronological backwards leave date ranges. |
| `chk_leave_requests_days` | `leave_requests` | `number_of_days > 0` | Enforces that filed leaves represent a positive working day duration. |

---

## 3. Indexing Strategy & Query Optimization

Indexes are established based on anticipated query access patterns (filtering, sorting, foreign key joins, and in-memory algorithm preloading):

| Index Name | Table | Indexed Columns | Index Type | Target Query Pattern / Performance Impact |
| :--- | :--- | :--- | :---: | :--- |
| `idx_positions_dept` | `positions` | `department_id` | B-Tree | Optimizes foreign key joins when fetching positions for a department. |
| `idx_employees_status` | `employees` | `employment_status` | B-Tree | Optimizes directory filters (`WHERE employment_status = 'Active'`). |
| `idx_employees_dept` | `employees` | `department_id` | B-Tree | Speeds up departmental workforce queries and reports. |
| `idx_employees_pos` | `employees` | `position_id` | B-Tree | Accelerates joins between employees and their job titles. |
| `idx_employees_name` | `employees` | `last_name, first_name` | B-Tree Composite | Optimizes alphabetical directory listings and search queries. |
| `idx_users_role` | `users` | `role` | B-Tree | Speeds up role-based user filtering and administrative lookups. |
| `idx_users_status` | `users` | `status` | B-Tree | Speeds up credential status verification upon login. |
| `idx_leave_balances_emp_year` | `leave_balances` | `employee_id, year` | B-Tree Composite | Optimizes employee portal balance queries (`WHERE emp = ? AND year = ?`). |
| `idx_leave_requests_status` | `leave_requests` | `status` | B-Tree | Speeds up queue retrieval for HR approval (`WHERE status = 'Pending'`). |
| `idx_leave_requests_emp_status` | `leave_requests` | `employee_id, status` | B-Tree Composite | Accelerates employee leave history filtering by lifecycle status. |
| `idx_leave_requests_dates` | `leave_requests` | `start_date, end_date` | B-Tree Composite | Accelerates leave date collision detection during filing validation. |
| `idx_activity_logs_action` | `activity_logs` | `action` | B-Tree | Optimizes audit log filtering by specific action verbs. |
| `idx_activity_logs_created` | `activity_logs` | `created_at` | B-Tree | Speeds up chronological activity log ordering and stack population. |
| `idx_notifications_user_read` | `notifications` | `user_id, is_read` | B-Tree Composite | Speeds up dashboard unread alert queries (`WHERE user = ? AND is_read = 0`). |
