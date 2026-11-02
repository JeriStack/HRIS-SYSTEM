# Screen Wireframe Specification: Employee Dashboard
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 4 — UI/UX Design  
**Route:** `GET /dashboard`  
**Access Guard:** `auth, role:employee`  
**Status:** Approved for Implementation  

---

## 1. Screen Layout & Wireframe

```
+-----------------------------------------------------------------------------------+
|  My Workspace | Welcome, David Cruz (Senior Software Engineer) [🔔 1] [David Cruz]|
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  [ EMPLOYEE WELCOME BANNER ]                                                      |
|  David J. Cruz • Senior Software Engineer • IT Department                         |
|  Employee No: EMP004 • Date Hired: Feb 01, 2021                                   |
|                                                                                   |
|  [ ANNUAL LEAVE BALANCES (FISCAL YEAR 2026) ]                                     |
|  +------------------------+ +------------------------+ +------------------------+ |
|  | 🏖️ VACATION LEAVE      | | 🏥 SICK LEAVE          | | 🚨 EMERGENCY LEAVE     | |
|  | 12 Days Remaining      | | 9 Days Remaining       | | 5 Days Remaining       | |
|  | Allocated: 15 | Used: 3| | Allocated: 10 | Used: 1| | Allocated: 5 | Used: 0 | |
|  | [████████████░░░] 80%  | | [█████████░░░░░] 90%   | | [██████████████] 100%  | |
|  +------------------------+ +------------------------+ +------------------------+ |
|                                                                                   |
|  +--------------------------------------------+ +------------------------------+  |
|  | 📝 RECENT LEAVE APPLICATIONS               | | 🔔 LATEST NOTIFICATIONS      |  |
|  |                                            | |                              |  |
|  | Type       Dates       Days  Status Action | | [✓] Vacation leave for       |  |
|  | Sick Leave Nov 9-10     2    PENDING [Cancel]| |     Aug 10-12 was APPROVED.  |  |
|  | Vacation   Aug 10-12    3    APPROVED  ---   | |     Processed by HR.         |  |
|  | Emergency  Oct 5        1    CANCELLED ---   | |                              |  |
|  |                                            | | [ View All Alerts (1 unread)]|  |
|  | [ ➕ File New Leave Application ]          | +------------------------------+  |
|  +--------------------------------------------+                                   |
+-----------------------------------------------------------------------------------+
```

---

## 2. Component Specifications

### 2.1 Employee Identity Header
* Pulls authenticated profile using `$_SESSION['user']['employee_id']`.
* Displays full legal name, employee number, department, position, and status badge.

### 2.2 Balance Progress Cards
* Displays allocated, used, and remaining days for all active leave types in current fiscal year.
* Progress bar calculates: `percentage = (remaining_days / allocated_days) * 100`.

### 2.3 Recent Applications Table
* Displays the employee's last 5 leave requests.
* **Self-Service Cancellation:** If `status == 'Pending'`, displays an active `[Cancel]` button. If `Approved`, `Rejected`, or `Cancelled`, action is disabled.

---

## 3. UI States

1. **Default State:** Live progress indicators and request history table.
2. **Empty Balance State:** If balance records have not been seeded for the new year, displays warning:
   ```
   [!] Annual leave allocations have not been initialized. Please contact Human Resources.
   ```
3. **Empty Requests State:** If no leave requests have been filed:
   ```
   [ℹ] You have not filed any leave applications yet. Click "File New Leave Application" to begin.
   ```
