# Screen Wireframe Specification: Reports Screens
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 4 — UI/UX Design  
**Routes:** `GET /reports/employee` | `GET /reports/leave` | `GET /reports/department`  
**Access Guard:** `auth, role:admin,hr`  
**Status:** Approved for Implementation  

---

## 1. Executive Leave Utilization Report (`/reports/leave`)

```
+-----------------------------------------------------------------------------------+
|  Reports & Analytics | Annual Leave Utilization Report            [ 🖨️ Print Report]|
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  [ FILTER CONTROLS ]                                                              |
|  Year: [ 2026 ▼ ]   Dept: [ All Departments ▼ ]   Type: [ All Types ▼ ]  [Filter] |
|                                                                                   |
|  [ SUMMARY AGGREGATE METRICS ]                                                    |
|  +--------------------+ +--------------------+ +--------------------+             |
|  | TOTAL DAYS GRANTED | | TOTAL DAYS TAKEN   | | OVERALL UTILIZATION|             |
|  |     180 Days       | |     16 Days        | |       8.89%        |             |
|  +--------------------+ +--------------------+ +--------------------+             |
|                                                                                   |
|  +-----------------------------------------------------------------------------+  |
|  | EMPLOYEE   DEPT   VACATION (USE/REM) SICK (USE/REM) EMERGENCY TOTAL CONSUMED|  |
|  |-----------------------------------------------------------------------------|  |
|  | David Cruz IT     3 / 12 Days        1 / 9 Days    0 / 5 Days 4 Days Taken  |  |
|  | Maria S.   IT     5 / 10 Days        0 / 10 Days   0 / 5 Days 5 Days Taken  |  |
|  | Juan DC    IT     0 / 15 Days        2 / 8 Days    0 / 5 Days 2 Days Taken  |  |
|  | Beatrice G Finance 4 / 11 Days       0 / 10 Days   1 / 4 Days 5 Days Taken  |  |
|  +-----------------------------------------------------------------------------+  |
|  * Based on verified MySQL leave_balances ledger records.                         |
+-----------------------------------------------------------------------------------+
```

---

## 2. Workforce Demographics & Department Reports

* **Employee Report (`/reports/employee`):** Headcount distribution by department, job position, gender demographics, active vs. inactive ratio, and date-hired tenure analysis.
* **Department Report (`/reports/department`):** Aggregate headcount, total budgeted positions, filled vs. vacant slots, and total leave consumption per operational unit.
* **Export Policy:** Standard native browser print stylesheet (`@media print`) hides navigation sidebars and headers, producing a clean PDF or physical paper report.
