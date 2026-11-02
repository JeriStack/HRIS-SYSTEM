# Screen Wireframe Specification: System Activity Logs
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 4 — UI/UX Design  
**Route:** `GET /activity-logs`  
**Access Guard:** `auth, role:admin,hr`  
**Status:** Approved for Implementation  

---

## 1. Screen Layout & Wireframe

```
+-----------------------------------------------------------------------------------+
|  System Audit Trail | Immutable Security & State Activity Logs                    |
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  [ AUDIT LOG FILTERS ]                                                            |
|  Action: [ All Actions ▼ ]  Entity: [ All Entities ▼ ]  User: [ All Users ▼ ]     |
|  Date Range: [ 2026-10-01 ] to [ 2026-10-11 ]                     [ Filter Logs ] |
|                                                                                   |
|  +-----------------------------------------------------------------------------+  |
|  | TIMESTAMP        USER      ACTION          ENTITY      IP ADDRESS  DETAILS  |  |
|  |-----------------------------------------------------------------------------|  |
|  | Oct 11 09:40:22  admin     LOGIN           users       127.0.0.1   [View]   |  |
|  | Oct 11 09:15:00  hr01      APPROVE_LEAVE   leave_req:4 127.0.0.1   [View]   |  |
|  | Oct 05 16:00:00  hr01      REJECT_LEAVE    leave_req:6 127.0.0.1   [View]   |  |
|  | Oct 01 09:15:00  emp005    LEAVE_FILED     leave_req:1 127.0.0.1   [View]   |  |
|  | Oct 01 08:30:00  admin     EMPLOYEE_CREATE employee:8  127.0.0.1   [View]   |  |
|  +-----------------------------------------------------------------------------+  |
|  Showing 1 to 5 of 5 total audit records           [ < Prev ] [ 1 ] [ Next > ]    |
+-----------------------------------------------------------------------------------+
```

---

## 2. Modal Inspection Dialog

Clicking `[View]` renders complete audit metadata while preserving secret obfuscation (`NFR-SEC-001`, `NFR-SEC-006`):

```
+-------------------------------------------------------------------+
|  🔍 Activity Log Event Details: Log ID #4                         |
+-------------------------------------------------------------------+
|                                                                   |
|  Timestamp:        2026-10-11 09:15:00 (UTC+8)                    |
|  Actor Account:    hr01 (Marcus Tan, HR Director)                 |
|  Action Verb:      APPROVE_LEAVE                                  |
|  Target Entity:    leave_requests (Record ID: 4)                  |
|  IP Address:       127.0.0.1                                      |
|  User-Agent:       Mozilla/5.0 (Windows NT 10.0; Win64; x64)...   |
|                                                                   |
|  Event Description:                                               |
|  "Approved 3 days Vacation Leave for EMP004 (David Cruz). Balance |
|   deducted: used_days updated from 0 to 3."                       |
|                                                                   |
|  [ Close Details ]                                                |
+-------------------------------------------------------------------+
```
