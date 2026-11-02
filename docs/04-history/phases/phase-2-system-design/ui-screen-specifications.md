# UI Screen & Wireframe Specifications
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 2 — System Analysis and Design  
**Date:** 2026-10-11  
**Status:** Approved for Baseline Design  

---

## 1. Design System & Global UI Guidelines

* **Aesthetic Philosophy:** Clean, modern, high-contrast enterprise design featuring a deep slate primary palette (`#1E293B`, `#0F172A`), indigo accent branding (`#4F46E5`), and semantic status colors.
* **Semantic Feedback Tokens:**
  * **Success:** Emerald green banner (`#10B981` border/bg tint) with checkmark icon.
  * **Warning:** Amber yellow badge/banner (`#F59E0B`) with alert triangle icon.
  * **Error / Validation:** Crimson red banner (`#EF4444`) with exclamation icon.
  * **Info:** Sky blue notification box (`#0EA5E9`).
* **Responsive Layout:** CSS Grid and Flexbox layouts guaranteeing full responsiveness across desktop viewports ($1920\text{ px}, 1366\text{ px}$) and mobile screens ($\ge 375\text{ px}$).

---

## 2. Major Screen Wireframe Specifications

### Screen 1: Login View (`/login`)
* **Purpose:** Single point of entry for user credential authentication.
* **Target Users:** All unauthenticated users (`ACT-ADMIN`, `ACT-EMP`).
* **Layout & Components:**
  * Centered card container with HRIS branding and logo.
  * Username text field with icon.
  * Password text field with toggleable visibility.
  * Hidden CSRF token field (`_csrf_token`).
  * "Sign In" primary action button with loading spinner state.
* **UI States:**
  * *Default:* Clean input fields with auto-focus on username.
  * *Validation Error:* Red outline on invalid field; banner: "Please provide both username and password."
  * *Authentication Error:* Red notification banner: "Invalid username or password" (no username enumeration).
  * *Session Expired:* Yellow notification banner: "Your session has expired. Please log in again."

---

### Screen 2: Administrator / HR Dashboard (`/admin/dashboard`)
* **Purpose:** Executive overview of organizational workforce metrics and pending queues.
* **Target Users:** Administrator/HR (`ACT-ADMIN`).
* **Layout & Components:**
  * **Top Metrics Grid (4 Stat Cards):** Total Employees, Active Headcount, Pending Leaves in Queue, Employees Currently on Leave.
  * **Recent Activity Widget (Custom LIFO Stack Demonstration):** List showing the top 5 most recent system actions popped from the in-memory Stack.
  * **Quick Action Buttons:** "Add Employee", "Process Leave Queue", "View DSA Lab".
* **UI States:**
  * *Default:* Populated cards and live activity feed.
  * *Empty State:* "No recent activities recorded" in audit widget.

---

### Screen 3: Employee Self-Service Dashboard (`/dashboard`)
* **Purpose:** Overview of personal leave status and rapid action hub for employees.
* **Target Users:** Regular Employee (`ACT-EMP`).
* **Layout & Components:**
  * **Welcome Header:** Employee name, employee number, and current job title.
  * **Leave Balances Card Grid:** Visual progress rings/bars showing Allocated, Used, and Remaining days for Vacation, Sick, and Emergency leaves.
  * **Active Request Status Card:** Shows status badge of latest filed request (`Pending`, `Approved`, `Rejected`).
  * **Quick Action Button:** Prominent "+ Apply for Leave" button leading to `/leaves/apply`.
* **UI States:**
  * *Empty State:* "You have no active or pending leave applications."

---

### Screen 4: Employee Directory & Form (`/employees`, `/employees/create`, `/employees/{id}/edit`)
* **Purpose:** View workforce directory and administer employee onboarding/editing.
* **Target Users:** Administrator/HR (`ACT-ADMIN`).
* **Directory Layout:**
  * Top filter bar: Search input (integrated with custom `LinearSearch` / `BinarySearch`), Department dropdown, Status filter (Active/Inactive).
  * Data Table: Columns for `Employee No`, `Full Name`, `Department`, `Position`, `Status`, `Date Hired`, and `Actions` (View, Edit, Deactivate).
* **Form Layout (Create / Edit):**
  * Section 1 (Personal): First Name, Middle Name, Last Name, Birth Date, Gender.
  * Section 2 (Contact): Email, Phone Number, Home Address.
  * Section 3 (Employment): Employee No (unique), Department (dynamic dropdown), Position (filtered by department), Date Hired, Employment Status.
* **UI States:**
  * *Validation Failure:* Highlighted input fields with inline error text (e.g., "Employee number already exists").
  * *Deactivation Confirmation:* Modal pop-up: "Are you sure you want to deactivate [Employee Name]? This preserves historical records but blocks future login and leave filings."

---

### Screen 5: Department & Position Management (`/departments`, `/positions`)
* **Purpose:** Maintain organizational hierarchy.
* **Target Users:** Administrator/HR (`ACT-ADMIN`).
* **Layout:**
  * Left pane: Form to create or edit a department/position.
  * Right pane: Data table listing existing entities with assigned employee count badges.
* **UI States:**
  * *Deletion Blocked:* Red modal alert: "Cannot delete department: 12 active employees are currently assigned to it."

---

### Screen 6: Leave Type & Balance Management (`/leaves/types`, `/leaves/balances`)
* **Purpose:** Manage global leave types and annual employee balance quotas.
* **Target Users:** Administrator/HR (`ACT-ADMIN`).
* **Layout:**
  * Leave type configuration cards showing default annual days (e.g., Vacation = 15 days, Sick = 10 days).
  * Employee balance matrix table: Searchable list of all employees with columns for each leave category's used and remaining quotas for the current year.

---

### Screen 7: Leave Request Filing Form (`/leaves/apply`)
* **Purpose:** Self-service leave application filing.
* **Target Users:** Employee (`ACT-EMP`).
* **Layout & Components:**
  * Live Balance Sidebar: Displays currently remaining days for each leave category.
  * Leave Type Dropdown (Vacation, Sick, Emergency, etc.).
  * Date Pickers: Start Date and End Date.
  * Dynamic Duration Counter: Automatically computes and displays working days (excluding weekends) via JavaScript as dates are chosen.
  * Reason / Justification text area.
  * Submit Button ("Submit Application").
* **UI States:**
  * *Invalid Dates:* Red banner: "Start date must be earlier than or equal to End date."
  * *Zero Working Days:* Yellow notice: "Selected date range contains only weekend days (0 working days)."
  * *Insufficient Balance:* Red banner: "Requested 5 days exceeds your remaining balance of 3 days."
  * *Date Overlap Conflict:* Red banner: "An approved or pending leave application already exists between [Date A] and [Date B]."

---

### Screen 8: Leave Request Review & Approval Queue (`/leaves/manage`)
* **Purpose:** Administrative queue to process pending leave applications.
* **Target Users:** Administrator/HR (`ACT-ADMIN`).
* **Layout & Components:**
  * **Queue Visualization Header:** Renders the Custom PHP `Queue` FIFO pipeline showing request order (`LR-001 -> LR-002 -> LR-003`).
  * **Active Item Review Card (Front of Queue):** Highlights the next request to be processed with employee profile snippet, requested dates, duration, reason, and current leave balance.
  * **Action Controls:** "Approve (Transaction-Safe)" (Green button) and "Reject" (Red button with reason prompt).
  * **Secondary Table:** Remaining queued requests in chronological order.
* **UI States:**
  * *Approval Concurrency Error:* Red modal: "Transaction Aborted: Leave balance changed or request already processed."
  * *Empty Queue:* Card stating: "All leave requests have been processed. Queue is currently empty."

---

### Screen 9: Leave History (`/leaves/history`, `/leaves/history/all`)
* **Purpose:** Chronological record of historical and ongoing leave applications.
* **Target Users:** Employee (`/leaves/history` - own records); Administrator/HR (`/leaves/history/all` - all records).
* **Layout:**
  * Filter bar: Status filter (`All`, `Pending`, `Approved`, `Rejected`, `Cancelled`), Year selector.
  * Table: `Request ID`, `Leave Type`, `Start Date`, `End Date`, `Days`, `Reason`, `Status Badge`, `Processed Date`, `Action` (Cancel button for pending items).
* **UI States:**
  * *Cancel Confirmation Modal:* "Are you sure you want to cancel this pending request? This action cannot be undone."

---

### Screen 10: Reports Module (`/reports/employee`, `/reports/leave`, `/reports/department`)
* **Purpose:** Analytical insights and summary reporting.
* **Target Users:** Administrator/HR (`ACT-ADMIN`).
* **Layout:**
  * Date range and filter controls with "Generate Report" and "Print / Export" buttons.
  * Summary Cards: Key aggregated statistics.
  * Structured printable report table with totals and percentage breakdowns.

---

### Screen 11: System Activity Logs (`/activity-logs`)
* **Purpose:** Security and audit investigation.
* **Target Users:** Administrator/HR (`ACT-ADMIN`).
* **Layout:**
  * Tabular audit trail: `Timestamp`, `User / Actor`, `Action Verb`, `Target Entity`, `Entity ID`, `Description`, `IP Address`, `User Agent`.
  * Search and action filter dropdown.

---

### Screen 12: Notification Center (`/notifications`)
* **Purpose:** User inbox for system alerts and status updates.
* **Target Users:** All authenticated users (`ACT-ADMIN`, `ACT-EMP`).
* **Layout:**
  * Feed of notification cards with status icon, title, message text, and relative timestamp (e.g., "10 minutes ago").
  * Unread items highlighted with blue accent; "Mark all as read" header button.

---

### Screen 13: DSA Laboratory Playground (`/dsa/playground`)
* **Purpose:** Interactive exploration of custom PHP data structures.
* **Target Users:** Administrator/HR, Academic Evaluators.
* **Layout & Components:**
  * **Tab Selector:** [Stack] | [Queue] | [Linked List] | [Hash Table] | [Binary Search Tree].
  * **Interactive Controls:** Input key/value field with action buttons (`Push`/`Pop`, `Enqueue`/`Dequeue`, `Insert`/`Search`/`Delete`, `In-Order Traverse`).
  * **Visual State Container:** Real-time visual rendering of node linkages, front/rear queue pointers, stack frames, hash buckets, and tree branches.

---

### Screen 14: DSA Performance Visualizer & Benchmarks (`/dsa/visualizer`)
* **Purpose:** Multi-scale benchmarking and empirical Big-O comparison.
* **Target Users:** Administrator/HR, Academic Evaluators.
* **Layout & Components:**
  * **Control Panel:** Select Test Category (Search Benchmark: Linear vs Binary vs Hash Table; Sort Benchmark: Bubble vs Selection vs Insertion vs Merge vs Quick Sort).
  * **Dataset Scale Checkboxes:** $N = 10, 100, 500, 1000, 5000, 10000$.
  * **Run Benchmark Button:** Triggers backend execution with high-resolution timing.
  * **Results Presentation:**
    * Tabular Matrix: Algorithm, Input Size $N$, Execution Time ($\mu s$), Comparison Count, Swap Count, Memory Delta ($\text{KB}$).
    * Comparison Graph: Rendered execution time curves comparing $O(N)$ vs $O(\log N)$ vs $O(1)$ and $O(N^2)$ vs $O(N \log N)$.
