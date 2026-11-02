# Phase 9 — Transaction-Safe Leave Processing

## 1. Overview & Critical Invariant

Phase 9 enforces atomic, ACID-compliant leave approval processing in the Employee HRIS & Leave Management System so that all database mutations required by an approval either succeed together or roll back completely.

**Critical Invariant:**
> An approved leave request (`leave_requests.status = 'Approved'`) can never be persisted without its corresponding leave-balance deduction (`leave_balances.used_days` and `leave_balances.remaining_days`), immutable audit entry (`activity_logs`), and persistent employee notification (`notifications`).

All participating tables (`users`, `employees`, `leave_types`, `leave_requests`, `leave_balances`, `activity_logs`, `notifications`) use the MySQL 8.0 **InnoDB** storage engine and share the same transactional `PDO` connection inside [`App\Models\Leave::approveRequest()`](../../../../app/models/Leave.php).

---

## 2. Transaction Boundary & Execution Order

When an HR user approves a leave request via `POST /leaves/{id}/approve` ([`LeaveController::approve()`](../../../../app/controllers/LeaveController.php)) or `POST /api/v1/hr/leaves/{id}/approve` ([`Api\LeaveController::approve()`](../../../../app/controllers/Api/LeaveController.php)), the controller authorizes the session role and delegates the entire workflow to `Leave::approveRequest()`:

```text
HR APPROVES REQUEST
        |
        v
AUTHORIZE HR ACTION (Middleware + Controller + DB Verification)
        |
        v
BEGIN TRANSACTION ($pdo->beginTransaction())
        |
        v
1. LOCK & VERIFY REVIEWER (SELECT ... FROM users WHERE id = :uid FOR UPDATE)
   - Must be Active and role = 'hr'
        |
        v
2. LOAD & LOCK LEAVE REQUEST (SELECT ... FROM leave_requests WHERE id = :id FOR UPDATE)
   - Must exist
   - Must have status = 'Pending'
   - Reviewer employee_id must not equal request employee_id (no self-approval)
        |
        v
3. LOAD & LOCK EMPLOYEE & OWNER USER (SELECT ... FROM employees / users FOR UPDATE)
   - Employee must exist and have employment_status = 'Active'
   - Linked owner user account must be Active
        |
        v
4. LOAD & LOCK LEAVE TYPE (SELECT ... FROM leave_types WHERE id = :lt_id FOR UPDATE)
   - Must exist and have status = 'Active'
        |
        v
5. REVALIDATE BUSINESS RULES & OVERLAP
   - Validate start_date <= end_date and single leave-year boundary
   - Recompute working days via LeaveCalculator::validateDateRange() and match number_of_days > 0
   - Lock and verify no overlapping Approved leave request exists for the employee
        |
        v
6. LOCK & RE-CHECK LEAVE BALANCE (SELECT ... FROM leave_balances ... FOR UPDATE)
   - Verify balance row exists for (employee_id, leave_type_id, year)
   - Verify remaining_days >= number_of_days and (allocated_days - used_days) >= number_of_days
        |
        v
7. UPDATE LEAVE REQUEST TO APPROVED
   - Conditional update: WHERE id = :id AND status = 'Pending'
   - Verify rowCount() === 1
        |
        v
8. UPDATE USED LEAVE DAYS
   - Conditional update: SET used_days = used_days + :days WHERE id = :balance_id ...
   - Verify rowCount() === 1
        |
        v
9. UPDATE REMAINING LEAVE BALANCE
   - Conditional update: SET remaining_days = remaining_days - :days WHERE id = :balance_id ...
   - Verify rowCount() === 1 and verify post-update invariant:
     used_days = prev_used + days, remaining_days = prev_remaining - days >= 0
        |
        v
10. CREATE ACTIVITY LOG
   - INSERT INTO activity_logs (action = 'LEAVE_APPROVED', entity_type = 'LeaveRequest', ...)
   - Verify rowCount() === 1
        |
        v
11. CREATE PERSISTENT NOTIFICATION
   - INSERT INTO notifications (user_id = owner_user_id, event_type = 'leave_request.approved', ...)
   - Verify notification row exists and belongs to the owning employee's user account
        |
        v
12. COMMIT TRANSACTION ($pdo->commit())
        |
        v
13. POST-COMMIT NOTIFICATION DELIVERY
   - Committed notification row acts as the transactional outbox entry polled by the
     WebSocket service (`NotificationOutbox` / `PdoRealtimeStore`) only after COMMIT.
   - Optional post-commit callbacks execute inside an isolated try/catch block so external
     delivery errors never roll back or re-run an already committed approval.
```

---

## 3. Concurrency Protection & Deadlock Prevention

1. **Deterministic Locking Order:**
   Every approval transaction acquires InnoDB row-level locks (`SELECT ... FOR UPDATE`) in a strict, deterministic table order:
   1. `users` (acting HR reviewer)
   2. `leave_requests` (target leave request row)
   3. `employees` (requesting employee row)
   4. `users` (requesting employee's user account)
   5. `leave_types` (leave classification row)
   6. `leave_requests` (overlapping `Approved` check for the same employee)
   7. `leave_balances` (target `(employee_id, leave_type_id, year)` row)
2. **Idempotency & Duplicate Submission Guard:**
   - The second concurrent or repeated request blocks on `SELECT * FROM leave_requests WHERE id = :id FOR UPDATE` until the first transaction commits.
   - Once the first transaction commits, the second transaction sees `status = 'Approved'` (not `'Pending'`), immediately rolls back, and returns HTTP `400` (`not_pending`) without modifying `leave_balances`, `activity_logs`, or `notifications`.
   - As defense-in-depth, the `UPDATE leave_requests ... WHERE id = :id AND status = 'Pending'` statement also asserts `$stmt->rowCount() === 1`.
3. **Concurrent Balance Overdraft Protection:**
   - If two HR users concurrently approve two *different* pending requests for the same employee and leave type, both transactions serialize on `SELECT * FROM leave_balances WHERE employee_id = :emp_id AND leave_type_id = :lt_id AND year = :year FOR UPDATE`.
   - The second transaction re-reads the updated `used_days` and `remaining_days` after acquiring the lock and rejects the approval if `remaining_days < number_of_days`.

---

## 4. Error Handling & Post-Commit Delivery Semantics

- **Pre-Commit Failures:**
  - Any validation failure or database error before `commit()` invokes `safeRollBack()`, which safely rolls back the active PDO transaction inside its own `try/catch` so rollback errors never mask the original failure.
  - Detailed diagnostics are logged server-side via `error_log()`; user-facing Web and JSON API responses receive only safe, consistent error messages without leaking SQL queries, stack traces, or credentials.
- **Post-Commit Delivery (Transactional Outbox Pattern):**
  - Persistent notifications are written to the `notifications` table (`event_type = 'leave_request.approved'`, `entity_id = <leave_id>`) **inside** the approval transaction.
  - The Ratchet WebSocket service (`bin/realtime-server.php`) polls `notifications` via a separate MySQL connection (`PdoRealtimeStore::fetchNotificationsAfter()`), guaranteeing that real-time events are emitted **only after commit** and never for rolled-back transactions.
  - If the WebSocket server is temporarily offline or a post-commit delivery callback throws an exception, the committed approval and balance deduction remain intact, and the employee still sees the persistent notification in `notifications` (and re-syncs via `GET /api/v1/notifications` on reconnect).

