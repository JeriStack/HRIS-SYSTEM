# Screen Wireframe Specification: Department & Position Screens
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 4 — UI/UX Design  
**Routes:** `GET /departments` | `GET /positions`  
**Access Guard:** `auth, role:admin,hr`  
**Status:** Approved for Implementation  

---

## 1. Department Management View (`/departments`)

```
+-----------------------------------------------------------------------------------+
|  Department Management | Organizational Divisions                                 |
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  +------------------------------+ +--------------------------------------------+  |
|  | ➕ CREATE NEW DEPARTMENT     | | 📋 ORGANIZATIONAL DEPARTMENTS LIST         |  |
|  |                              | |                                            |  |
|  | Department Name *            | | NAME                EMPLOYEES   ACTIONS    |  |
|  | [ Human Resources         ]  | | Executive Mgmt      1 Staff     [Edit] [Del]   |  |
|  |                              | | Human Resources     2 Staff     [Edit] [Del]   |  |
|  | Description                  | | Information Tech    3 Staff     [Edit] [Del]   |  |
|  | [ Talent acquisition and...| | Finance & Acctg     1 Staff     [Edit] [Del]   |  |
|  |                            ] | Marketing & Sales   1 Staff     [Edit] [Del]   |  |
|  |                              | |                                            |  |
|  | [ Create Department ]       | | Total: 5 Departments registered            |  |
|  +------------------------------+ +--------------------------------------------+  |
+-----------------------------------------------------------------------------------+
```

---

## 2. Position Management View (`/positions`)

```
+-----------------------------------------------------------------------------------+
|  Job Position Management | Organizational Job Classifications                    |
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  +------------------------------+ +--------------------------------------------+  |
|  | ➕ CREATE JOB POSITION       | | 📋 JOB POSITIONS DIRECTORY                 |  |
|  |                              | | Filter by Dept: [ All Departments ▼ ]      |  |
|  | Department *                 | |                                            |  |
|  | [ Information Technology ▼ ] | | TITLE               DEPARTMENT    ACTIONS  |  |
|  |                              | | Senior Engineer     Info Tech     [E] [D]  |  |
|  | Position Title *             | | Junior Engineer     Info Tech     [E] [D]  |  |
|  | [ QA Automation Engineer  ]  | | Systems Admin       Info Tech     [E] [D]  |  |
|  |                              | | HR Director         Human Res     [E] [D]  |  |
|  | Description                  | | HR Officer          Human Res     [E] [D]  |  |
|  | [ Test automation lead...  ] | | Finance Manager     Finance       [E] [D]  |  |
|  |                              | |                                            |  |
|  | [ Save Job Position ]        | | Total: 9 Positions defined                 |  |
|  +------------------------------+ +--------------------------------------------+  |
+-----------------------------------------------------------------------------------+
```

---

## 3. UI Dependency & Referential Integrity Feedback

When an administrator attempts to delete a department or position that has linked active or inactive employees, the UI immediately blocks the action with a clear warning:

```
+-------------------------------------------------------------------+
|  🛑 Deletion Blocked: Referential Integrity Protected             |
+-------------------------------------------------------------------+
|                                                                   |
|  Cannot delete department "Information Technology".               |
|                                                                   |
|  System Conflict Reason:                                          |
|  • 3 active employees and 3 job positions are currently assigned  |
|    to this department.                                            |
|                                                                   |
|  Required Action:                                                 |
|  Please reassign all employees and associated job positions to    |
|  another department before attempting deletion.                   |
|                                                                   |
|  [ Understand & Close ]                                           |
+-------------------------------------------------------------------+
```
