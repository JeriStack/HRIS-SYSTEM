# Leave Filing Workflow

## 1. Sequence Overview

The leave application process executes the following linear sequence on the server:

```text
Employee User
     ↓
Select Leave Classification (Must be Active)
     ↓
Select Inclusive Schedule (Start Date, End Date)
     ↓
Live Client-Side Day Feedback
     ↓
Submit Form (POST /leaves/apply + _csrf_token)
     ↓
[SERVER] Authenticate Session & Resolve employee_id
     ↓
[SERVER] Verify Leave Type exists and is Active
     ↓
[SERVER] Validate Dates with LeaveCalculator (reject end < start)
     ↓
[SERVER] Calculate Working Days (exclude Sat & Sun; reject 0 days)
     ↓
[SERVER] Check Balance Sufficiency & Pending Reservations
     ↓
[SERVER] Execute Overlap Detection Query
     ↓
[SERVER] Insert Record into leave_requests (status = 'Pending')
     ↓
[SERVER] Record Event in activity_logs
     ↓
[SERVER] Dispatch In-System Notifications to HR & Admin
     ↓
Flash Confirmation & Redirect to Dashboard
```

---

## 2. Server-Side Enforcement (Anti-Tampering)

1. **Employee Identity Resolution:**
   The employee ID is strictly resolved from the authenticated user session (`currentUser()['employee_id']`). Any submitted `employee_id` in `$_POST` or `$_GET` is completely ignored to prevent parameter spoofing.
2. **Authoritative Day Calculation:**
   The client calculates days for user experience, but the server recalculates working days authoritatively via `LeaveCalculator::calculateWorkingDays($startDate, $endDate)`. Any client-submitted `number_of_days` is ignored.
3. **Initial Status Assignment:**
   The server hardcodes `status = 'Pending'` on the SQL insert. Client requests cannot bypass the pending state.

---

## 3. UI/UX Feedback

- **Dynamic Working Day Calculation:** As start and end dates change, JavaScript automatically computes Monday–Friday working days, excluding Saturdays and Sundays.
- **Quota Exceeded Warning:** The frontend compares calculated days with the selected type's available days (`data-balance` attribute) and warns the user in real time.
- **Invalid Date Range Warning:** If the start date is later than the end date, an inline error notice informs the user before submission.
