# Server-Side Leave Validation Rules

## 1. Validation Matrix

All leave mutations are strictly validated on the server side via `LeaveController` and `LeaveModel`.

| Validation Check | Trigger Phase | Rule / Constraint | Failure Handling |
|---|---|---|---|
| **Authentication & Employee Link** | Filing & Cancellation | User must be logged in with a valid `employee_id` in `users`. | Redirect to login or flash error; abort. |
| **CSRF Token** | All POST Routes | Request must supply a valid `_csrf_token` matching the session token. | HTTP 403 Forbidden / Token Mismatch redirect. |
| **Active Leave Type** | Filing | Leave type exists and `status = 'Active'`. | Flash error: "Selected leave classification is inactive or does not exist." |
| **Date Sequence** | Filing | `start_date` and `end_date` must be valid `Y-m-d`; `end_date >= start_date`. | Flash error: "End date cannot precede start date." |
| **Non-Zero Working Days** | Filing | Computed working days using `LeaveCalculator` must be $> 0$. | Flash error: "Selected dates contain 0 working days (weekends only)." |
| **Balance Sufficiency** | Filing & Approval | `days <= remaining_days` for the leave classification. | Flash error: "Requested duration exceeds available quota." |
| **Pending Reservation** | Filing | $\text{days} \le (\text{remaining\_days} - \text{pending\_days})$. | Flash error: "Insufficient available balance: days already reserved in other pending filings." |
| **Overlap Detection** | Filing & Approval | `new_start <= existing_end AND new_end >= existing_start` for same employee on `Pending` or `Approved` requests. | Flash error: "Overlapping leave detected (#LR-XXX)." |
| **State Transition Validity** | Approval, Rejection, Cancellation | Target request must be in `Pending` status. | Flash error: "Cannot process request: already processed." |
| **Ownership Verification** | Cancellation & Detail View | Employees can only cancel or inspect requests where `employee_id === currentUser()['employee_id']`. | HTTP 403 Forbidden / Ownership Violation. |
| **Rejection Reason** | Rejection | Optional / defaults to "Request declined per HR department review." | Persisted in `leave_requests.rejection_reason`. |
