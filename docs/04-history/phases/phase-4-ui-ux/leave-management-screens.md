# Screen Wireframe Specification: Leave Management Screens
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 4 — UI/UX Design  
**Routes:** `GET /leaves/apply` | `GET /leaves/manage` | `GET /leaves/balance` | `GET /leaves/history`  
**Status:** Approved for Implementation  

---

## 1. File Leave Application Form (`/leaves/apply`)

```
+-----------------------------------------------------------------------------------+
|  Apply for Leave | Submit a new workforce leave application                       |
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  +--------------------------------------------+ +------------------------------+  |
|  | 📝 LEAVE APPLICATION FORM                  | | 💳 CURRENT REMAINING BALANCE |  |
|  |                                            | |                              |  |
|  | Leave Category *                           | | Vacation Leave:  12 Days     |  |
|  | [ Vacation Leave (12 days remaining)    ▼ ]| | Sick Leave:       9 Days     |  |
|  |                                            | | Emergency Leave:  5 Days     |  |
|  | Start Date *           End Date *          | |                              |  |
|  | [ 2026-11-02       ]   [ 2026-11-06      ] | | [ℹ] Duration excludes        |  |
|  |                                            | |     Saturdays and Sundays.   |  |
|  | Calculated Working Duration                | +------------------------------+  |
|  | [ 5 Working Days (Excludes Weekends)     ] |                                   |
|  |                                            |                                   |
|  | Reason / Justification *                   |                                   |
|  | [ Family annual vacation trip out of town. |                                   |
|  |   All tasks handed over to David Cruz.   ] |                                   |
|  |                                            |                                   |
|  | [ Submit Application ]   [ Cancel ]        |                                   |
|  +--------------------------------------------+                                   |
+-----------------------------------------------------------------------------------+
```

### Form Dynamic Validation Feedback Banners:
* **Overlapping Dates Conflict:**
  ```
  [!] Date Collision Error: You already have a pending/approved leave application between 2026-11-02 and 2026-11-06.
  ```
* **Insufficient Balance:**
  ```
  [!] Insufficient Balance: You requested 5 days, but only 3 days remain in this category.
  ```
* **Inverted Dates:**
  ```
  [!] Invalid Date Sequence: Start date must be earlier than or equal to End date.
  ```

---

## 2. Leave Request Review & FIFO Queue Screen (`/leaves/manage`)

```
+-----------------------------------------------------------------------------------+
|  Leave Review Center | Administrative Processing Queue                            |
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  [ CUSTOM PHP QUEUE PIPELINE (FIFO DEMONSTRATION) ]                               |
|  FRONT -> [ LR001: Maria Santos ] -> [ LR002: David Cruz ] -> [ LR003: Juan DC ]   |
|                                                                                   |
|  +-----------------------------------------------------------------------------+  |
|  | ⭐ CURRENT ACTIVE REQUEST IN REVIEW (FRONT OF QUEUE)                         |  |
|  |                                                                             |  |
|  | Employee: Maria C. Santos (EMP005)       Department: Information Technology |  |
|  | Position: Junior Software Engineer       Status: Active                     |  |
|  | Leave Type: Vacation Leave               Current Balance: 10 Days Remaining |  |
|  | Dates: Nov 02, 2026 -> Nov 06, 2026      Duration: 5 Working Days           |  |
|  | Reason: "Family vacation trip out of town"                                  |  |
|  |                                                                             |  |
|  | [ ✓ Approve Request (Transaction-Safe) ]   [ ✕ Reject Request ]             |  |
|  +-----------------------------------------------------------------------------+  |
|                                                                                   |
|  [ SUBSEQUENT QUEUED REQUESTS ]                                                   |
|  ID     EMPLOYEE       TYPE       DAYS   DATES          SUBMITTED     ACTIONS    |
|  LR002  David Cruz     Sick       2      Nov 09-10      Oct 02 11:30  [Review]   |
|  LR003  Juan Dela Cruz Vacation   3      Nov 16-18      Oct 03 14:00  [Review]   |
+-----------------------------------------------------------------------------------+
```

---

## 3. Leave Approval Confirmation & Transaction Protocol

Clicking `[✓ Approve Request]` initiates the atomic database transaction modal:

```
+-------------------------------------------------------------------+
|  🔒 Confirm Transaction-Safe Leave Approval                       |
+-------------------------------------------------------------------+
|                                                                   |
|  Approve 5 days Vacation Leave for Maria Santos (EMP005)?         |
|                                                                   |
|  Database Impact:                                                 |
|  • Request LR001 status will update to APPROVED.                  |
|  • Used balance will increment from 5 -> 10 days.                 |
|  • Remaining balance will decrement from 10 -> 5 days.            |
|  • System audit log & employee notification will be created.      |
|                                                                   |
|  [ Execute Approval Transaction ]      [ Cancel ]                 |
+-------------------------------------------------------------------+
```
