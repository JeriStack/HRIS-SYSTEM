# Screen Wireframe Specification: Administrator / HR Dashboard
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 4 — UI/UX Design  
**Route:** `GET /admin/dashboard`  
**Access Guard:** `auth, role:admin,hr`  
**Status:** Approved for Implementation  

---

## 1. Screen Layout & Wireframe

```
+-----------------------------------------------------------------------------------+
|  HR Dashboard | Welcome back, Eleanor Vance (Admin)       [🔔 3] [Eleanor Vance ▼]|
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  [ TOP EXECUTIVE METRICS CARDS ]                                                  |
|  +----------------+ +----------------+ +----------------+ +--------------------+  |
|  | 👥 TOTAL STAFF | | 🟢 ACTIVE HEAD | | ⏳ PENDING QUEUE| | 🏖️ CURRENTLY ON   |  |
|  |       120      | |       115      | |        8       | |       4 LEAVES     |  |
|  | +2 this month  | | 5 Inactive     | | FIFO Queue Top | | Returning soon   |  |
|  +----------------+ +----------------+ +----------------+ +--------------------+  |
|                                                                                   |
|  +--------------------------------------------+ +------------------------------+  |
|  | ⚡ QUICK ACTIONS HUB                       | | 📜 RECENT SYSTEM ACTIVITY    |  |
|  |                                            | |    (Custom LIFO Stack Demo)  |  |
|  | [ ➕ Register New Employee ]               | |                              |  |
|  | [ ⏳ Process Pending Leave Queue (8) ]     | | 1. Approved Leave LR004 (Top)|  |
|  | [ 📊 Generate Monthly Workforce Report ]   | | 2. Updated Employee EMP003   |  |
|  | [ 🧪 Open DSA Laboratory Benchmarks ]      | | 3. Registered Employee EMP008|  |
|  +--------------------------------------------+ | 4. Leave Filed: Maria Santos |  |
|                                                 | 5. System Initialized        |  |
|  +--------------------------------------------+ |                              |  |
|  | 📋 PENDING LEAVE PREVIEW (FIFO ORDER)      | | [ View Full Audit Logs -> ]  |  |
|  | Employee   Type       Days   Dates   Action| +------------------------------+  |
|  | Maria S.   Vacation    5     Nov 2-6 [Rev] |                                   |
|  | David C.   Sick        2     Nov 9-10[Rev] |                                   |
|  +--------------------------------------------+                                   |
+-----------------------------------------------------------------------------------+
```

---

## 2. Component Specifications

### 2.1 Executive Metrics Grid
1. **Total Staff Card:** Evaluated via `SELECT COUNT(*) FROM employees`.
2. **Active Headcount Card:** Evaluated via `SELECT COUNT(*) FROM employees WHERE employment_status = 'Active'`.
3. **Pending Leave Queue Card:** Evaluated via `SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'`. Clicking links directly to `/leaves/manage`.
4. **Currently On Leave Card:** Evaluated via `SELECT COUNT(*) FROM leave_requests WHERE status = 'Approved' AND CURRENT_DATE BETWEEN start_date AND end_date`.

### 2.2 Recent System Activity Widget (Custom LIFO Stack)
* **Algorithmic Integration:** Loads recent entries from `activity_logs` into the custom PHP `Stack` class (`app/dsa/structures/Stack.php`).
* **Display:** Pops the top 5 entries to demonstrate Last-In, First-Out (LIFO) audit behavior in application memory.

### 2.3 Quick Actions Hub
* Four prominent trigger buttons leading directly to key administrative workflows.

---

## 3. UI States & Exception Handling

1. **Default State:** Live metric counters, populated activity stack, and pending queue table.
2. **Empty Queue State:** If pending leaves $= 0$, table renders:
   ```
   [✓] All leave applications have been reviewed. The approval queue is currently empty.
   ```
3. **Loading State:** Skeleton shimmer loaders on metric cards during data retrieval.
4. **Database Failure State:** Red alert banner:
   ```
   [!] Database Connection Error: Unable to retrieve real-time metrics. Please contact system administrator.
   ```
