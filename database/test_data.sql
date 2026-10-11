-- =============================================================================
-- Database Verification & Constraint Test Suite
-- Database: Employee HRIS & Leave Management System
-- Purpose: Diagnostic SQL queries to verify relational integrity, negative test cases,
--          and edge case validation against MySQL / MariaDB.
-- Documentation: docs/phase-3-database/constraints-and-indexes.md
-- =============================================================================

-- -----------------------------------------------------------------------------
-- SECTION 1: POSITIVE INTEGRITY TESTS (Valid Relational Joins)
-- -----------------------------------------------------------------------------

-- Test 1.1: Verify Employee to Department and Position Linkage
SELECT 
    e.employee_no,
    CONCAT(e.first_name, ' ', e.last_name) AS full_name,
    d.department_name,
    p.position_name,
    e.employment_status
FROM employees e
JOIN departments d ON e.department_id = d.id
JOIN positions p ON e.position_id = p.id;

-- Test 1.2: Verify Employee Annual Leave Balances Integrity (Allocated = Used + Remaining)
SELECT 
    e.employee_no,
    lt.leave_name,
    lb.year,
    lb.allocated_days,
    lb.used_days,
    lb.remaining_days,
    CASE 
        WHEN lb.allocated_days = (lb.used_days + lb.remaining_days) THEN 'PASS'
        ELSE 'FAIL - Balance Inconsistency'
    END AS formula_check
FROM leave_balances lb
JOIN employees e ON lb.employee_id = e.id
JOIN leave_types lt ON lb.leave_type_id = lt.id;

-- Test 1.3: Verify Pending Leave Request Queue (FIFO Ordering)
SELECT 
    lr.id AS request_id,
    e.employee_no,
    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
    lt.leave_name,
    lr.start_date,
    lr.end_date,
    lr.number_of_days,
    lr.status,
    lr.created_at
FROM leave_requests lr
JOIN employees e ON lr.employee_id = e.id
JOIN leave_types lt ON lr.leave_type_id = lt.id
WHERE lr.status = 'Pending'
ORDER BY lr.created_at ASC;


-- -----------------------------------------------------------------------------
-- SECTION 2: NEGATIVE TESTS (Deliberate Constraint Violations)
-- NOTE: Each statement below is designed to trigger a specific MySQL constraint
-- error. To execute individually in phpMyAdmin or CLI, test one block at a time.
-- -----------------------------------------------------------------------------

-- NEGATIVE TEST 2.1: Duplicate Employee Number (Expected: ERROR 1062 - Duplicate entry)
-- INSERT INTO `employees` (`employee_no`, `first_name`, `last_name`, `email`, `birth_date`, `gender`, `department_id`, `position_id`, `date_hired`)
-- VALUES ('EMP001', 'Test', 'Duplicate', 'unique.email@company.local', '1990-01-01', 'Male', 1, 1, '2026-01-01');

-- NEGATIVE TEST 2.2: Duplicate Username (Expected: ERROR 1062 - Duplicate entry)
-- INSERT INTO `users` (`employee_id`, `username`, `password`, `role`)
-- VALUES (NULL, 'admin', 'dummy_hash', 'admin');

-- NEGATIVE TEST 2.3: Duplicate Annual Leave Balance Record (Expected: ERROR 1062 - Duplicate entry)
-- INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`, `remaining_days`)
-- VALUES (4, 1, 2026, 15, 0, 15);

-- NEGATIVE TEST 2.4: Invalid Foreign Key Department (Expected: ERROR 1452 - Cannot add or update child row)
-- INSERT INTO `positions` (`position_name`, `department_id`)
-- VALUES ('Ghost Position', 99999);

-- NEGATIVE TEST 2.5: Invalid Date Range (start_date > end_date) (Expected: CHECK constraint chk_leave_requests_dates)
-- INSERT INTO `leave_requests` (`employee_id`, `leave_type_id`, `start_date`, `end_date`, `number_of_days`, `reason`, `status`)
-- VALUES (4, 1, '2026-12-10', '2026-12-05', 5, 'Chronologically backward dates test', 'Pending');

-- NEGATIVE TEST 2.6: Restrict Deletion of Department with Linked Positions (Expected: ERROR 1451)
-- DELETE FROM `departments` WHERE `id` = 3;

-- NEGATIVE TEST 2.7: Restrict Deletion of Employee with Active Leave Balances (Expected: ERROR 1451)
-- DELETE FROM `employees` WHERE `id` = 4;
