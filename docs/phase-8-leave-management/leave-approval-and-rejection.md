# Leave Approval and Rejection Workflows

## 1. HR Review Queue

Authorized HR and Administrator users access the centralized leave review queue at `GET /leaves` (with aliases `GET /hr/leave-requests` and `GET /hr/leave-requests/pending`).

### Features:
- Filter by status tabs: All, Pending, Approved, Rejected, Cancelled.
- Filter by Department, Employee ID, and Inclusive Schedule (`start_date`, `end_date`).
- Fast actions: Direct Approve button and Reject button (with prompt modal for reason).
- Deep inspection: Clickable Ref link (`#LR-XXX`) opens `/leaves/{id}` displaying requester profile, leave description, notes, and the employee's current quota utilization.

---

## 2. Approval Procedure (`POST /leaves/{id}/approve`)

1. Verify user role is `hr` or `admin`.
2. Retrieve request record. Verify existence and confirm `status === 'Pending'`.
3. In a transaction:
   - Re-check overlap to prevent concurrent approval conflicts.
   - Update `leave_requests` table:
     ```sql
     UPDATE leave_requests
     SET status = 'Approved', approved_by = :reviewer, approved_at = NOW()
     WHERE id = :id AND status = 'Pending';
     ```
   - Atomically increment `used_days` and update `remaining_days` in `leave_balances`:
     ```sql
     UPDATE leave_balances
     SET used_days = used_days + :days,
         remaining_days = GREATEST(0, allocated_days - used_days)
     WHERE employee_id = :emp_id AND leave_type_id = :lt_id AND year = :year;
     ```
4. Commit transaction.
5. Create immutable audit entry in `activity_logs` (`LEAVE_APPROVED`).
6. Dispatch notification to employee (`Leave Request Approved`).
7. Flash success message and redirect.

---

## 3. Rejection Procedure (`POST /leaves/{id}/reject`)

1. Verify user role is `hr` or `admin`.
2. Retrieve request record. Verify existence and confirm `status === 'Pending'`.
3. Read `rejection_reason` from POST body. If empty, apply standard default: `"Request declined per HR department review."`
4. In a transaction:
   - Update `leave_requests` table:
     ```sql
     UPDATE leave_requests
     SET status = 'Rejected', approved_by = :reviewer, approved_at = NOW(), rejection_reason = :reason
     WHERE id = :id AND status = 'Pending';
     ```
   - **No deduction** to `leave_balances`.
5. Commit transaction.
6. Create audit entry in `activity_logs` (`LEAVE_REJECTED`).
7. Dispatch notification to employee with the recorded justification.
8. Flash warning message and redirect.
