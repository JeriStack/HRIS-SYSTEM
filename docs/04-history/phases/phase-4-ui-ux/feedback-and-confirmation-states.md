# UI Feedback & Confirmation Dialog Specifications
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 4 — UI/UX Design  
**Status:** Approved for Frontend Implementation  

---

## 1. Visual Notification & Alert Feedback Banners

Feedback banners provide immediate, unambiguous post-action feedback rendered at the top of the content canvas.

```
+-----------------------------------------------------------------------------------+
|  [✓] SUCCESS ALERT BANNER                                                         |
|  Border: 1px solid #10B981 | Bg: #ECFDF5 | Text: #065F46                          |
|  "Employee record for David Cruz (EMP004) was successfully updated."              |
+-----------------------------------------------------------------------------------+

+-----------------------------------------------------------------------------------+
|  [!] WARNING ALERT BANNER                                                         |
|  Border: 1px solid #F59E0B | Bg: #FFFBEB | Text: #92400E                          |
|  "You have 3 leave requests currently pending administrative review."             |
+-----------------------------------------------------------------------------------+

+-----------------------------------------------------------------------------------+
|  [✕] ERROR ALERT BANNER                                                           |
|  Border: 1px solid #EF4444 | Bg: #FEF2F2 | Text: #991B1B                          |
|  "Submission Failed: An overlapping leave request already exists for these dates."|
+-----------------------------------------------------------------------------------+
```

---

## 2. Modal Confirmation Protocols

Modal confirmation dialogs are strictly reserved for **consequential or irreversible operations** to prevent user fatigue while protecting operational workforce data.

### Confirmation Policy Rules:
1. **Never use for routine navigation or non-destructive edits.**
2. **Mandatory for:**
   * Employee soft deactivation (`/employees/{id}/deactivate`).
   * Transactional leave approval (`/leaves/{id}/approve`).
   * Leave application rejection (`/leaves/{id}/reject`).
   * Employee self-service pending leave cancellation (`/leaves/{id}/cancel`).
   * Department or position deletion attempts.

### 2.1 Consequential Action Dialog Wireframes

#### Dialog A: Pending Leave Cancellation (Employee Self-Service)
```
+-------------------------------------------------------------------+
|  ⚠️ Cancel Leave Request Confirmation                             |
+-------------------------------------------------------------------+
|                                                                   |
|  Are you sure you want to withdraw your pending leave request?    |
|                                                                   |
|  Request Details:                                                 |
|  • Leave Type: Vacation Leave (5 Days)                            |
|  • Dates: November 02, 2026 -> November 06, 2026                  |
|                                                                   |
|  Notice:                                                          |
|  Your remaining leave balance is unaffected and remains 12 days.  |
|  This application will be removed from the HR review queue.       |
|                                                                   |
|  [ Confirm Cancellation ]              [ Keep Request ]           |
+-------------------------------------------------------------------+
```

#### Dialog B: Leave Rejection with Reason Prompt (HR Administration)
```
+-------------------------------------------------------------------+
|  ⚠️ Reject Leave Request                                          |
+-------------------------------------------------------------------+
|                                                                   |
|  Reject Vacation Leave application for Maria Santos (EMP005)?     |
|                                                                   |
|  Administrative Reason / Remarks (Optional):                      |
|  +-------------------------------------------------------------+  |
|  | Peak project deployment window; requested to reschedule...  |  |
|  +-------------------------------------------------------------+  |
|                                                                   |
|  Notice:                                                          |
|  No leave balance will be deducted from the employee. A rejection |
|  notification will be dispatched to Maria Santos immediately.     |
|                                                                   |
|  [ Confirm Rejection ]                 [ Cancel ]                 |
+-------------------------------------------------------------------+
```
