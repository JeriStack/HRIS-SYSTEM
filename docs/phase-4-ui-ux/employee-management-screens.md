# Screen Wireframe Specification: Employee Management Screens
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 4 — UI/UX Design  
**Routes:** `GET /employees` | `GET /employees/create` | `GET /employees/{id}/edit`  
**Access Guard:** `auth, role:admin,hr`  
**Status:** Approved for Implementation  

---

## 1. Employee Directory Screen (`/employees`)

```
+-----------------------------------------------------------------------------------+
|  Employee Management | Master Workforce Registry          [ ➕ Register Employee ]|
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  [ SEARCH & FILTER CONTROLS ]                                                     |
|  Search: [ EMP004 / David Cruz...     ]  Dept: [ All Departments ▼ ]              |
|  Status: [ Active Only ▼ ]               Sort: [ Last Name (A-Z) ▼ ]  [ Filter ]  |
|                                                                                   |
|  +-----------------------------------------------------------------------------+  |
|  | EMP NO  FULL NAME       DEPARTMENT  POSITION       STATUS   HIRED      ACTIONS|  |
|  |-----------------------------------------------------------------------------|  |
|  | EMP004  David J. Cruz   IT          Senior Dev     Active   2021-02-01 [V][E][D]|  |
|  | EMP005  Maria C. Santos IT          Junior Dev     Active   2022-08-15 [V][E][D]|  |
|  | EMP002  Marcus A. Tan   HR          HR Director    Active   2019-03-01 [V][E][D]|  |
|  | EMP008  Ricardo Mercado Marketing   Specialist     Inactive 2021-05-15 [V][E]   |  |
|  +-----------------------------------------------------------------------------+  |
|  Showing 1 to 4 of 8 total employees             [ < Previous ] [ 1 ] [ Next > ]  |
+-----------------------------------------------------------------------------------+
```

* **Action Icons:** `[V]` = View Profile, `[E]` = Edit Details, `[D]` = Deactivate Employee.

---

## 2. Add / Edit Employee Form (`/employees/create`, `/employees/{id}/edit`)

```
+-----------------------------------------------------------------------------------+
|  Register New Employee | Complete all required personnel fields                   |
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  [ SECTION 1: PERSONAL & DEMOGRAPHIC INFORMATION ]                                |
|  First Name *                   Middle Name                   Last Name *         |
|  [ David                     ]  [ James                    ]  [ Cruz           ]  |
|                                                                                   |
|  Date of Birth *                Gender *                                          |
|  [ 1990-02-18                ]  [ Male                  ▼ ]                       |
|                                                                                   |
|  [ SECTION 2: CONTACT INFORMATION ]                                               |
|  Corporate Email *              Phone Number                                      |
|  [ david.cruz@company.local  ]  [ +63 920 444 5555         ]                      |
|  Residential Address                                                              |
|  [ 321 BGC High Street, Taguig City                                             ] |
|                                                                                   |
|  [ SECTION 3: EMPLOYMENT & ORGANIZATIONAL PLACEMENT ]                             |
|  Employee Number (Unique) *     Department *                  Position *          |
|  [ EMP004                    ]  [ Information Technology ▼ ]  [ Senior Dev     ▼ ]|
|                                                                                   |
|  Date Hired *                   Employment Status *                               |
|  [ 2021-02-01                ]  [ Active                ▼ ]                       |
|                                                                                   |
|  [ Save Employee Record ]       [ Cancel / Return to Directory ]                  |
+-----------------------------------------------------------------------------------+
```

---

## 3. Employee Deactivation Modal Dialog

When HR clicks the `[D]` button, a modal prevents destructive errors while reinforcing soft-deletion semantics:

```
+-------------------------------------------------------------------+
|  ⚠️ Deactivate Employee Confirmation                              |
+-------------------------------------------------------------------+
|                                                                   |
|  Are you sure you want to deactivate David Cruz (EMP004)?         |
|                                                                   |
|  Important System Notice:                                         |
|  • This action updates the employee status to INACTIVE.           |
|  • All historical leave applications, balances, and audit logs    |
|    will be safely PRESERVED for company records.                  |
|  • The employee will be immediately BLOCKED from logging into     |
|    the self-service portal or submitting new leave requests.      |
|                                                                   |
|  [ Confirm Deactivation ]              [ Cancel ]                 |
+-------------------------------------------------------------------+
```
