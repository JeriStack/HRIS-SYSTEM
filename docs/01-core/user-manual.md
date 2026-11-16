# NEXORA HR — Comprehensive Three-Role User Manual

**Project:** NEXORA HR — Human Resource Information System  
**Tagline:** People. Processes. Progress.  
**Version:** 3.4 (Phase 16 Authoritative Release)  
**Target Roles:** Administrator (`admin`), HR Officer (`hr`), Employee (`employee`)

---

## 1. System Access & Multi-Factor Authentication (All Roles)

### 1.1 Portal Selection, Sign-In & 2FA Verification
- **Purpose:** Authenticate securely into the role-designated NEXORA HR workspace using email/password credentials followed by mandatory 6-digit Email OTP (or RFC 6238 TOTP authenticator / backup code).
- **Required Access / Permission:** Active user account (`users.status = 'Active'`) and active linked employee status (if applicable); route `GET /login` -> `POST /login` -> `GET /verify-otp` -> `POST /verify-otp`.
- **Step-by-Step Instructions:**
  1. Navigate to `/login` (`Welcome to NEXORA HR — People. Processes. Progress.`).
  2. Select your role portal card on the landing screen:
     - **Admin Portal** (`Continue as Admin`) for system administrators (`role = 'admin'`).
     - **HR Portal** (`Continue as HR`) for human resource officers (`role = 'hr'`).
     - **Employee Portal** (`Continue as Employee`) for workforce self-service (`role = 'employee'`).
  3. In the accessible modal dialog (`role="dialog"`), enter your **Email Address** (`name="email"`) and **Password** (`name="password"`), then click **Sign In to [Admin/HR/Employee] Portal**.
  4. Retrieve your 6-digit one-time verification code sent to your registered email address (or local Mailpit inbox at `http://localhost:8025` in development).
  5. Enter the 6-digit code on `/verify-otp` and click **Verify & Sign In** (or switch to `/verify-totp` if TOTP authenticator is enabled on your account).
  6. To sign out at any time, click **Sign Out** in the sidebar/top navigation (`POST /logout`).
- **Expected Result:** Session ID is rotated (`Session::regenerate()`), a cryptographic `auth_fingerprint` is bound to your session, and you are redirected to your role's home dashboard (`/admin/dashboard`, `/hr/dashboard`, or `/employee/dashboard`).
- **Validation Messages & Possible Errors:**
  - *Invalid credentials:* `"Invalid email address or password."`
  - *Portal mismatch:* `"Access denied: Your account is not authorized for the [Selected Portal]. Please sign in through the [Correct Portal]."`
  - *Inactive/terminated account:* `"Your account is currently inactive. Please contact your HR Administrator."`
  - *Expired or invalid OTP:* `"Invalid or expired verification code. Please try again."`
  - *Rate limit exceeded:* `"Too many login attempts. Please wait 15 minutes before trying again."`
- **Screenshot Reference:** `docs/screenshots/admin/admin-portal-login.png`, `docs/screenshots/hr/hr-portal-login.png`, `docs/screenshots/employee/employee-portal-login.png`

---

## 2. Administrator Manual (`role = 'admin'`)

> **Security Governance Notice:** Public Administrator registration does not exist. Initial Administrator accounts are bootstrapped via `php bin/create_admin_user.php` or provisioned by an existing Administrator via Step-Up Authentication.

### 2.1 Administrator Governance Dashboard
- **Purpose:** Monitor system-wide user account metrics, role distribution, security events, and administrative quick actions.
- **Required Access / Permission:** `admin` role (`admin_dashboard` capability); route `GET /admin/dashboard`.
- **Step-by-Step Instructions:**
  1. Sign in via the **Admin Portal**.
  2. Review the summary KPI cards (Total System Users, Active Administrators, Active HR Officers, Active Employees, Recent Security Events).
  3. Use the quick-navigation cards or sidebar links to jump to **User Management**, **Roles & Permissions**, **Admin Provisioning**, **Security Events**, **System Health**, or **Audit Trail**.
- **Expected Result:** Real-time governance metrics render with zero layout shift (backed by the Skeleton Screen system during async refreshes).
- **Validation Messages & Possible Errors:** Accessing `/admin/dashboard` as `hr` or `employee` returns `HTTP 403 Forbidden`.
- **Screenshot Reference:** `docs/screenshots/admin/admin-dashboard.png`

### 2.2 User Account Management & Role Assignment
- **Purpose:** Provision user accounts for employees, assign system roles (`admin`, `hr`, `employee`), and activate/deactivate user access.
- **Required Access / Permission:** `users.view`, `users.create`, `users.edit_role`, `users.activate`, `users.deactivate` (`manage_users`, `assign_roles`); routes `GET /users`, `POST /users/store`, `POST /users/{id}/update`.
- **Step-by-Step Instructions:**
  1. Navigate to **User Accounts** (`/users`).
  2. **To provision a user account:** Select an unlinked employee from the dropdown, assign a username, email, initial password, and role (`admin`, `hr`, or `employee`), enter your **Current Password** (if prompted by Step-Up Authentication), and click **Create User Account**.
  3. **To update a user's role or status:** Locate the user in the table, change the **Role** (`admin`, `hr`, `employee`) or **Status** (`Active`, `Inactive`), confirm your current password if Step-Up Authentication is required, and submit.
- **Expected Result:** `users.role`, `users.role_id`, and `users.status` are updated in MySQL; if deactivated or role-changed, `AccountRevocationService` immediately revokes the user's active WebSocket tickets and invalidates their live session on their next request; an audit entry (`USER_UPDATED` / `USER_CREATED`) is recorded.
- **Validation Messages & Possible Errors:**
  - *Self-lockout / self-demotion guard:* `"Security violation: You cannot deactivate or demote your own currently active account."`
  - *Last active Admin / HR guard:* `"Cannot deactivate or demote the last active Administrator / HR account."`
  - *Step-Up required:* `"Please confirm your current password to authorize this sensitive administrative action."`
- **Screenshot Reference:** `docs/screenshots/admin/admin-user-management.png`

### 2.3 Dynamic Role & Permission Matrix Management
- **Purpose:** Inspect the three system roles (`admin`, `hr`, `employee`), toggle granular permissions for `hr` and `employee`, manage user access assignments, and review permission audit history.
- **Required Access / Permission:** `roles.view`, `roles.manage`, `permissions.view`, `permissions.assign`; routes `GET /admin/roles`, `POST /admin/roles/permissions`.
- **Step-by-Step Instructions:**
  1. Navigate to **Roles & Permissions** (`/admin/roles`).
  2. Review **Section 1: System Roles Overview** (shows `SYSTEM PROTECTED` badge on Administrator).
  3. In **Section 2: Permission Matrix**, select the target role tab (`HR` or `Employee`; `Admin` toggles are locked/immutable).
  4. Toggle individual capability switches (`perm-toggle-switch`), click **Save Permission Changes**, review the diff in the confirmation modal (`#saveConfirmModal`), and confirm.
  5. Inspect **Section 3: User Access Management** and **Section 4: Permission Audit History** (`#auditTable` / `#auditDetailModal`) to verify the recorded before/after permission diff.
- **Expected Result:** `AuthorizationService::updateRolePermissions()` atomically updates `role_permissions` inside a database transaction, invalidates the in-memory permission cache, and writes a hash-chained `RBAC_PERMISSIONS_UPDATED` audit log.
- **Validation Messages & Possible Errors:**
  - *Attempting to modify Admin role:* `"The 'admin' role is system-protected and immutable."`
  - *Unrecognized permission slug:* `"Unrecognized permission identifier: [slug]"`
- **Screenshot Reference:** `docs/screenshots/admin/admin-permission-management.png`

### 2.4 Dedicated Administrator Provisioning
- **Purpose:** Provision a new Administrator account through a dedicated, step-up verified governance workflow.
- **Required Access / Permission:** `admin.provision`; routes `GET /admin/provisioning`, `POST /admin/provisioning`.
- **Step-by-Step Instructions:**
  1. Navigate to **Admin Provisioning** (`/admin/provisioning`).
  2. Enter the new administrator's **Username**, **Email Address**, **Temporary Password**, and your own **Current Administrator Password** for step-up verification.
  3. Click **Provision Administrator**.
- **Expected Result:** New user record with `role = 'admin'` and `role_id = 1` is created, and `ADMIN_PROVISIONED` is recorded in the audit trail.
- **Validation Messages & Possible Errors:**
  - *Weak password:* `"Password must be at least 8 characters long."`
  - *Duplicate username/email:* `"Username or email is already registered."`
- **Screenshot Reference:** `docs/screenshots/admin/admin-provisioning.png`

### 2.5 System Health, Security Events & My Account Settings
- **Purpose:** Monitor live infrastructure probes, review security events, configure authentication/notification policies, and manage personal administrator credentials.
- **Required Access / Permission:** `system_settings.view`, `system_settings.manage`, `audit_logs.view`; routes `GET /admin/system-health`, `GET /admin/security-events`, `GET /admin/auth-settings`, `GET /admin/general-settings`, `GET /admin/notification-settings`, `GET /admin/account`, `POST /admin/account`.
- **Step-by-Step Instructions:**
  1. Open **System Health** (`/admin/system-health`) to inspect live MySQL PDO latency, Ratchet WebSocket port `:8090` socket status, SMTP reachability, and PHP runtime memory metrics.
  2. Open **Security Events** (`/admin/security-events`) to inspect failed logins, OTP lockouts, and portal mismatches.
  3. Open **My Account Settings** (`/admin/account`) to update your administrator email address or password (requires entering your **Current Password**).
- **Expected Result:** Updating credentials on `/admin/account` updates `users`, refreshes your session `auth_fingerprint` so you remain signed in, rotates your session ID, and logs `ADMIN_ACCOUNT_UPDATED`.

### 2.6 HR Support Management (`/admin/support`)
- **Purpose:** Triage, investigate, assign, and resolve technical issues, system errors, and access problems submitted by HR personnel (`HR-SUP-YYYY-XXXXXXXX`), and record private Admin-only investigation notes.
- **Required Access / Permission:** `admin` role (`hr_support.admin_inbox`, `hr_support.admin_manage`, `hr_support.internal_notes`); routes `GET /admin/support`, `GET /admin/support/tickets/{ref}`, `POST /admin/support/tickets/{ref}/assign`, `POST /admin/support/tickets/{ref}/triage`, `POST /admin/support/tickets/{ref}/respond`, `GET /admin/support/attachments/{id}`.
- **Step-by-Step Instructions:**
  1. Navigate to **Support Management** (`/admin/support`) from the Admin Sidebar or System Overview.
  2. Review the 6 live database KPI cards (`Total Open Tickets`, `Newly Submitted`, `Critical & High Priority`, `Awaiting HR Response`, `In Progress`, `Recently Resolved`).
  3. Filter by status, priority, category, date range, or assigned administrator, and click **Manage** on a ticket.
  4. Use **Assign Administrator** to assign the ticket to an active Admin user, **Status, Priority & Resolution** to transition statuses (`Submitted`, `Under Review`, `In Progress`, `Waiting for HR`, `Resolved`, `Closed`), or **Respond to HR or Add Internal Admin Note** to send an HR-visible reply, request info, or save a private internal note (hidden from HR).
- **Expected Result:** Status transitions, assignments, responses, and redacted internal note metadata are logged in `activity_logs`; HR receives in-app, WebSocket, and redacted SMTP/Mailpit notifications (internal notes never notify or appear to HR).
- **Screenshot Reference:** `docs/screenshots/admin/admin-system-health.png`, `docs/screenshots/admin/admin-security-events.png`

---

## 3. HR Officer Manual (`role = 'hr'`)

### 3.1 HR Operational Dashboard
- **Purpose:** View real-time workforce headcount, active vs. on-leave employees, pending leave approval queue count, department distribution charts, and recent activity feed.
- **Required Access / Permission:** `hr_dashboard` (`role = 'hr'`); route `GET /hr/dashboard`.
- **Step-by-Step Instructions:**
  1. Sign in via the **HR Portal**.
  2. Review the KPI summary cards (Total Workforce, Active Employees, Pending Leave Requests, Approved Leaves Today).
  3. Inspect the **Pending Leave Approval Queue** widget and **Department Distribution** analytics.
- **Expected Result:** Dashboard loads operational workforce metrics and subscribes to the `role.hr` WebSocket channel for live updates when employees file or cancel leave requests.
- **Screenshot Reference:** `docs/screenshots/hr/hr-dashboard.png`

### 3.2 Employee Record Management (Directory, Search, Create, Edit, Deactivate)
- **Purpose:** Maintain the master employee registry, search/filter personnel, onboard new employees (with optional user account and automatic leave balance initialization), and update employment status.
- **Required Access / Permission:** `employees.view`, `employees.create`, `employees.edit`, `employees.deactivate` (`manage_employees`); routes `GET /employees`, `GET /employees/create`, `POST /employees/store`, `GET /employees/{id}`, `GET /employees/{id}/edit`, `POST /employees/{id}/update`, `POST /employees/{id}/deactivate`.
- **Step-by-Step Instructions:**
  1. Navigate to **Employees** (`/employees`).
  2. **Search & Filter:** Use the search bar (powered by `LinearSearch` / `BinarySearch` / `HashTable` lookup) or filter by **Department** and **Employment Status** (`Active`, `Inactive`, `Terminated`).
  3. **Add Employee:** Click **+ Add Employee** (`/employees/create`), fill in Employee No, First/Middle/Last Name, Email, Phone, Birth Date, Gender, Department, Position, Date Hired, and Employment Status, then click **Save Employee**.
  4. **Edit Employee:** Click **Edit** on any employee row (`/employees/{id}/edit`), modify fields, and click **Update Employee**.
  5. **Deactivate Employee:** Click **Deactivate** (`POST /employees/{id}/deactivate`) to transition the employee to `Inactive` and automatically suspend their linked user login.
- **Expected Result:** Employee record is persisted in `employees`, annual `leave_balances` are initialized for new hires, linked user accounts are synchronized, and `EMPLOYEE_CREATED` / `EMPLOYEE_UPDATED` is logged.
- **Validation Messages & Possible Errors:**
  - *Duplicate Employee No or Email:* `"Employee number or email address is already in use."`
  - *Position/Department mismatch:* `"The selected position does not belong to the chosen department."`
  - *Invalid dates:* `"Birth date must indicate an age of at least 18 years prior to date hired."`
- **Screenshot Reference:** `docs/screenshots/hr/hr-employee-management.png`, `docs/screenshots/hr/hr-employee-profile.png`

### 3.3 Department & Job Position Management
- **Purpose:** Configure organizational departments and job positions mapped to departments.
- **Required Access / Permission:** `departments.view`, `departments.create`, `departments.edit`, `departments.delete`, `positions.view`, `positions.create`, `positions.edit`, `positions.delete`; routes `/departments*` and `/positions*`.
- **Step-by-Step Instructions:**
  1. Navigate to **Departments** (`/departments`) or **Positions** (`/positions`).
  2. Create, edit, or delete organizational units using the action buttons.
- **Expected Result:** Changes persist in `departments` / `positions` and are logged in `activity_logs`.
- **Validation Messages & Possible Errors:**
  - *Referential integrity guard:* `"Cannot delete department/position because active employees or positions are currently assigned to it."`

### 3.4 Leave Request Review, FIFO Queue Processing, Approval & Rejection
- **Purpose:** Review employee leave applications, process the oldest pending request first via the FIFO Approval Queue, or individually approve, reject, or cancel requests with transaction-safe row locking.
- **Required Access / Permission:** `leave.view`, `leave.approve`, `leave.reject`, `leave_balances.manage`, `leave_types.manage`; routes `GET /leaves`, `POST /leaves/queue/next`, `POST /leaves/{id}/approve`, `POST /leaves/{id}/reject`, `POST /leaves/{id}/cancel`, `GET /leaves/balances`, `GET /leaves/types`.
- **Step-by-Step Instructions:**
  1. Navigate to **Leave Management** (`/leaves`).
  2. **FIFO Queue Processing:** Click **Process Next in Queue (FIFO)** (`POST /leaves/queue/next`) to automatically dequeue and approve the oldest pending leave request (`LeaveApprovalQueueService`).
  3. **Individual Approval:** Click **Approve** (`POST /leaves/{id}/approve`) on a `Pending` request.
  4. **Individual Rejection:** Click **Reject** (`POST /leaves/{id}/reject`), enter a clear **Rejection Reason**, and confirm.
  5. **Reversing an Approved Leave:** On an `Approved` request, click **Cancel**, provide a mandatory **Cancellation Reason**, and submit (`POST /leaves/{id}/cancel`).
- **Expected Result:**
  - On **Approval**: `leave_requests` and `leave_balances` rows are locked with `SELECT ... FOR UPDATE`; `used_days` increases and `remaining_days` decreases atomically; status becomes `Approved`; a real-time WebSocket toast and asynchronous email (`send_email` queue job) notify the employee.
  - On **Rejection**: Status becomes `Rejected` with the recorded reason; balance remains untouched; employee is notified.
  - On **Approved Leave Cancellation**: Deducted days are refunded (`used_days -= N`, `remaining_days += N`) and status becomes `Cancelled`.
- **Validation Messages & Possible Errors:**
  - *Self-approval blocked (Segregation of Duties):* `"Segregation of duties violation: You cannot approve or reject your own leave request."`
  - *Insufficient balance at approval time:* `"Cannot approve leave request: Employee has insufficient remaining leave balance."`
  - *Already processed:* `"Only Pending leave requests can be approved or rejected."`
- **Screenshot Reference:** `docs/screenshots/leave-management/hr-leave-approval.png`

### 3.5 Workforce & Leave Reports, Audit Trail & DSA Laboratory
- **Purpose:** Analyze workforce and leave metrics, export formula-sanitized CSV reports, inspect the tamper-evident audit trail, and run interactive DSA visualizations.
- **Required Access / Permission:** `reports.view`, `reports.export`, `audit_logs.view`, `dsa.view`; routes `GET /reports`, `GET /reports/export`, `GET /activity-logs`, `GET /dsa`.
- **Step-by-Step Instructions:**
  1. Open **Reports & Analytics** (`/reports`), filter by date range, department, or leave status, and click **Export CSV** (`/reports/export`) to download a spreadsheet-safe CSV report.
  2. Open **Audit Trail** (`/activity-logs`) to filter security and operational events and verify SHA-256 hash-chain integrity.
  3. Open **DSA Laboratory** (`/dsa`) to run Searching, Sorting, Stack, Queue, Linked List, Hash Table, and Binary Search Tree demonstrations on live HRIS data.
- **Screenshot Reference:** `docs/screenshots/reports/hr-reports.png`, `docs/screenshots/dsa/dsa-visualizer.png`

### 3.6 Help & Support Center for Reporting Issues to Administrators (`/hr/help`)
- **Purpose:** Report technical errors, login/access issues, leave/employee module defects, or data discrepancies directly to System Administrators, track own submitted tickets, reply with supporting screenshots, confirm resolution, or request reopening.
- **Required Access / Permission:** `hr` role (`hr_support.submit`, `hr_support.view_own`, `hr_support.reply_own`); routes `GET /hr/help`, `GET /hr/help/create`, `POST /hr/help/tickets`, `GET /hr/help/tickets/{ref}`, `POST /hr/help/tickets/{ref}/reply`, `POST /hr/help/tickets/{ref}/confirm-resolution`, `POST /hr/help/tickets/{ref}/reopen`, `GET /hr/help/attachments/{id}`.
- **Step-by-Step Instructions:**
  1. Navigate to **Help & Support (Admin)** (`/hr/help`) from the HR Sidebar or Daily HR Workflows.
  2. Click **Submit Support Ticket** (`/hr/help/create`), enter **Subject**, **Issue Category**, **Priority** (`Low`, `Medium`, `High`, `Critical`), **Detailed Description**, and optional screenshots/files (`PNG`, `JPG`, `WEBP`, `PDF`, `TXT`, up to 3 files, max 5 MB each), review the confirmation dialog, and click **Confirm & Submit**.
  3. Open any ticket (`HR-SUP-YYYY-XXXXXXXX`) from **My Support Tickets** to view public history and Admin replies, post follow-up messages, **Confirm Resolution** (`Resolved -> Closed`), or **Request Reopen** (`Resolved -> Under Review` within 14 days).
- **Expected Result:** A unique `HR-SUP-YYYY-XXXXXXXX` ticket is persisted, Administrators are notified, and private Admin internal notes are never exposed to HR.

---

## 4. Employee Manual (`role = 'employee'`)

### 4.1 Employee Self-Service Dashboard & Personal Profile
- **Purpose:** View personal leave balance summaries, recent leave request statuses, unread notifications, and employment profile details.
- **Required Access / Permission:** `employee_dashboard`, `profile.view_own`; routes `GET /employee/dashboard`, `GET /profile`.
- **Step-by-Step Instructions:**
  1. Sign in via the **Employee Portal** to land on `/employee/dashboard`.
  2. Click **My Profile** (`/profile`) or `/employees/{your_employee_id}` to inspect your personal, department, position, and employment details.
- **Expected Result:** Displays only your own employee record and leave metrics.
- **Validation Messages & Possible Errors:**
  - *IDOR Protection:* Attempting to visit `/employees/{other_id}` returns `HTTP 403 Forbidden` (`"Forbidden: You may only view your own employee record."`).
- **Screenshot Reference:** `docs/screenshots/employee/employee-dashboard.png`, `docs/screenshots/employee/employee-profile.png`

### 4.2 Submitting a Leave Application
- **Purpose:** File a new leave request with automatic Monday–Friday business-day calculation and instant overlap/balance validation.
- **Required Access / Permission:** `leave.file_own`, `leave_balances.view_own`; routes `GET /leaves/create`, `POST /leaves/store`, `GET /leaves/calculate-days`.
- **Step-by-Step Instructions:**
  1. Navigate to **Apply for Leave** (`/leaves/create`).
  2. Select a **Leave Type** (e.g., Vacation Leave, Sick Leave, Emergency Leave).
  3. Choose your **Start Date** and **End Date** (the UI previews the calculated working days excluding weekends via `/leaves/calculate-days`).
  4. Enter a detailed **Reason** for your leave and click **Submit Leave Application**.
- **Expected Result:** Request is saved with `status = 'Pending'`, a `LeaveRequestSubmitted` event is written to `realtime_outbox`, HR and Admin officers receive an instant WebSocket notification, and you are redirected to `/leaves` with a confirmation message.
- **Validation Messages & Possible Errors:**
  - *Weekend-only range:* `"The selected date range contains 0 working days (weekends are excluded)."`
  - *Overlapping dates:* `"You already have a Pending or Approved leave request that overlaps with the selected dates."`
  - *Insufficient balance:* `"Insufficient leave balance. You have X remaining days for this leave type."`
  - *Cross-year boundary:* `"Leave requests must start and end within the same calendar year."`
- **Screenshot Reference:** `docs/screenshots/employee/employee-leave-application.png`

### 4.3 Viewing Leave Balances, Request History & Cancelling Pending Leave
- **Purpose:** Track allocated, used, and remaining leave quotas, monitor request decisions, and withdraw pending applications before HR review.
- **Required Access / Permission:** `leave.view_own`, `leave.cancel_own`, `leave_balances.view_own`; routes `GET /leaves`, `GET /leaves/balances`, `POST /leaves/{id}/cancel`.
- **Step-by-Step Instructions:**
  1. Navigate to **My Leave Balances** (`/leaves/balances`) to view your current year's quota breakdown (`Allocated`, `Used`, `Remaining`) for each leave type.
  2. Navigate to **My Leave History** (`/leaves`) to view all past and active applications, including reviewer timestamps and rejection reasons.
  3. To withdraw a `Pending` request, click **Cancel Request** (`POST /leaves/{id}/cancel`) next to the pending row.
- **Expected Result:** Pending request transitions to `Cancelled` immediately and HR is notified.
- **Validation Messages & Possible Errors:**
  - *Cancelling non-pending request:* `"Employees may only cancel their own Pending leave requests."`
- **Screenshot Reference:** `docs/screenshots/employee/employee-leave-history.png`, `docs/screenshots/employee/employee-leave-balances.png`

### 4.4 Real-Time Notifications Center
- **Purpose:** Receive instant toast notifications when HR approves or rejects a leave request and manage unread notification history.
- **Required Access / Permission:** Authenticated user (`GET /notifications`, `POST /notifications/{id}/read`, `POST /notifications/read-all`).
- **Step-by-Step Instructions:**
  1. Watch the notification bell badge in the top navigation bar update in real time over WebSocket (`/ws`).
  2. Click the notification bell or open `/notifications` to read full messages and click **Mark All as Read**.
- **Expected Result:** `notifications.is_read` is updated to `1` and the unread badge counter resets to `0`.

