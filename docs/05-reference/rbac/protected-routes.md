# Protected Routes & Endpoint Access Specifications
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 2.0  
**Status:** Approved Production Routing  

---

## 1. Route Registry & Access Matrix

All application routes, their HTTP methods, controllers, and authorization policies:

### A. Public & Authentication Routes
| Route | Method | Controller Action | Middleware Stack | Description |
| :--- | :---: | :--- | :--- | :--- |
| `/` | `GET` | `HomeController::index` | None | Landing Page introducing system & architecture |
| `/health` | `GET` | `HomeController::health` | None | Health check & system status |
| `/login` | `GET` | `AuthController::showLoginForm` | None | Authentication login view |
| `/login` | `POST` | `AuthController::login` | `CsrfMiddleware` | Process credentials & initialize session |
| `/logout` | `POST` | `AuthController::logout` | `AuthMiddleware`, `CsrfMiddleware` | Terminate session (State-changing) |
| `/logout` | `GET` | `AuthController::logout` | `AuthMiddleware` | Fallback browser logout |
| `/dashboard`| `GET` | `AuthController::dashboard` | `AuthMiddleware` | Role-directed dispatcher |

---

### B. Dedicated Administrator Routes (Administrator Only)
| Route | Method | Controller Action | Middleware Stack | Description |
| :--- | :---: | :--- | :--- | :--- |
| `/admin/dashboard` | `GET` | `AdminController::dashboard` | `AuthMiddleware`, `RoleMiddleware('admin')` | System metrics, user accounts summary, audit logs |
| `/admin/users` | `GET` | `UserController::index` | `AuthMiddleware`, `RoleMiddleware('admin')` | Directory of registered user accounts and roles |
| `/admin/users/{id}/edit` | `GET` | `UserController::edit` | `AuthMiddleware`, `RoleMiddleware('admin')` | Edit user role and account status form |
| `/admin/users/{id}/edit` | `POST` | `UserController::update` | `AuthMiddleware`, `RoleMiddleware('admin')`, `CsrfMiddleware` | Update user role and status (lockout-protected) |
| `/admin/support` | `GET` | `AdminSupportController::index` | `AuthMiddleware`, `RoleMiddleware('admin')`, `PermissionMiddleware('hr_support.admin_inbox')` | Admin Support Management Inbox & live DB metrics |
| `/admin/support/tickets/{ref}` | `GET` | `AdminSupportController::show` | `AuthMiddleware`, `RoleMiddleware('admin')`, `PermissionMiddleware('hr_support.admin_inbox')` | Ticket investigation, conversation & private internal notes |
| `/admin/support/tickets/{ref}/assign` | `POST` | `AdminSupportController::assign` | `AuthMiddleware`, `RoleMiddleware('admin')`, `CsrfMiddleware`, `PermissionMiddleware('hr_support.admin_manage')` | Assign or reassign ticket to active Administrator |
| `/admin/support/tickets/{ref}/triage` | `POST` | `AdminSupportController::updateTriage` | `AuthMiddleware`, `RoleMiddleware('admin')`, `CsrfMiddleware`, `PermissionMiddleware('hr_support.admin_manage')` | Update ticket priority, status, or resolution summary |
| `/admin/support/tickets/{ref}/respond` | `POST` | `AdminSupportController::respond` | `AuthMiddleware`, `RoleMiddleware('admin')`, `CsrfMiddleware`, `PermissionMiddleware('hr_support.admin_manage')` | Post HR-visible reply, request info, or private Admin internal note |
| `/admin/support/attachments/{id}` | `GET` | `AdminSupportController::downloadAttachment` | `AuthMiddleware`, `RoleMiddleware('admin')`, `PermissionMiddleware('hr_support.admin_inbox')` | Authorized Admin attachment download |

---

### C. Dedicated HR Operational & HR Support Routes (HR Role Only)
| Route | Method | Controller Action | Middleware Stack | Description |
| :--- | :---: | :--- | :--- | :--- |
| `/hr/dashboard` | `GET` | `DashboardController::hrDashboard` | `AuthMiddleware`, `RoleMiddleware('hr')` | HR operational dashboard & leave overview |
| `/hr/help` | `GET` | `HrAdminSupportController::index` | `AuthMiddleware`, `RoleMiddleware('hr')`, `PermissionMiddleware('hr_support.view_own')` | HR Help & Support Center ("My Support Tickets") |
| `/hr/help/create` | `GET` | `HrAdminSupportController::create` | `AuthMiddleware`, `RoleMiddleware('hr')`, `PermissionMiddleware('hr_support.submit')` | Submit HR support ticket form |
| `/hr/help/tickets` | `POST` | `HrAdminSupportController::store` | `AuthMiddleware`, `RoleMiddleware('hr')`, `CsrfMiddleware`, `PermissionMiddleware('hr_support.submit')` | Create HR-to-Admin support ticket (`HR-SUP-YYYY-XXXXXXXX`) |
| `/hr/help/tickets/{ref}` | `GET` | `HrAdminSupportController::show` | `AuthMiddleware`, `RoleMiddleware('hr')`, `PermissionMiddleware('hr_support.view_own')` | Own ticket details & public conversation (internal notes stripped) |
| `/hr/help/tickets/{ref}/reply` | `POST` | `HrAdminSupportController::reply` | `AuthMiddleware`, `RoleMiddleware('hr')`, `CsrfMiddleware`, `PermissionMiddleware('hr_support.reply_own')` | Send follow-up reply & attachments |
| `/hr/help/tickets/{ref}/confirm-resolution` | `POST` | `HrAdminSupportController::confirmResolution` | `AuthMiddleware`, `RoleMiddleware('hr')`, `CsrfMiddleware`, `PermissionMiddleware('hr_support.reply_own')` | Confirm resolution (`Resolved -> Closed`) |
| `/hr/help/tickets/{ref}/reopen` | `POST` | `HrAdminSupportController::reopen` | `AuthMiddleware`, `RoleMiddleware('hr')`, `CsrfMiddleware`, `PermissionMiddleware('hr_support.reply_own')` | Request reopening of Resolved ticket within 14-day window |
| `/hr/help/attachments/{id}` | `GET` | `HrAdminSupportController::downloadAttachment` | `AuthMiddleware`, `RoleMiddleware('hr')`, `PermissionMiddleware('hr_support.view_own')` | Download own non-internal attachment |
| `/hr/announcements` | `GET` | `HrAnnouncementController::index` | `AuthMiddleware`, `RoleMiddleware('hr')`, `PermissionMiddleware('announcements.view_manage')` | HR Announcements Management list & summary metrics |
| `/hr/announcements/create` | `GET` | `HrAnnouncementController::create` | `AuthMiddleware`, `RoleMiddleware('hr')`, `PermissionMiddleware('announcements.create')` | Create announcement form |
| `/hr/announcements` | `POST` | `HrAnnouncementController::store` | `AuthMiddleware`, `RoleMiddleware('hr')`, `CsrfMiddleware`, `PermissionMiddleware('announcements.create')` | Store draft, schedule, or publish announcement |
| `/hr/announcements/{id}` | `GET` | `HrAnnouncementController::show` | `AuthMiddleware`, `RoleMiddleware('hr')`, `PermissionMiddleware('announcements.view_manage')` | View announcement details, analytics & audit history |
| `/hr/announcements/{id}/edit` | `GET` | `HrAnnouncementController::edit` | `AuthMiddleware`, `RoleMiddleware('hr')`, `PermissionMiddleware('announcements.edit')` | Edit announcement form |
| `/hr/announcements/{id}/update` | `POST` | `HrAnnouncementController::update` | `AuthMiddleware`, `RoleMiddleware('hr')`, `CsrfMiddleware`, `PermissionMiddleware('announcements.edit')` | Update announcement details & targets |
| `/hr/announcements/{id}/publish` | `POST` | `HrAnnouncementController::publish` | `AuthMiddleware`, `RoleMiddleware('hr')`, `CsrfMiddleware`, `PermissionMiddleware('announcements.publish')` | Immediately publish announcement |
| `/hr/announcements/{id}/schedule` | `POST` | `HrAnnouncementController::schedule` | `AuthMiddleware`, `RoleMiddleware('hr')`, `CsrfMiddleware`, `PermissionMiddleware('announcements.schedule')` | Schedule announcement for future publication |
| `/hr/announcements/{id}/archive` | `POST` | `HrAnnouncementController::archive` | `AuthMiddleware`, `RoleMiddleware('hr')`, `CsrfMiddleware`, `PermissionMiddleware('announcements.archive')` | Archive announcement |
| `/hr/announcements/{id}/delete` | `POST` | `HrAnnouncementController::deleteDraft` | `AuthMiddleware`, `RoleMiddleware('hr')`, `CsrfMiddleware`, `PermissionMiddleware('announcements.delete_draft')` | Permanently delete Draft announcement |
| `/hr/announcements/attachments/{id}` | `GET` | `HrAnnouncementController::downloadAttachment` | `AuthMiddleware`, `RoleMiddleware('hr')`, `PermissionMiddleware('announcements.view_manage')` | Download announcement attachment |

---

### D. Workforce & Organizational Management (Administrator & HR Access)
| Route | Method | Controller Action | Middleware Stack | Description |
| :--- | :---: | :--- | :--- | :--- |
| `/hr/employees` | `GET` | `EmployeeController::index` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | Employee Directory with search and filtering |
| `/hr/employees/create` | `GET` | `EmployeeController::create` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | Add new employee form |
| `/hr/employees` | `POST` | `EmployeeController::store` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])`, `CsrfMiddleware` | Store employee record |
| `/hr/employees/{id}` | `GET` | `EmployeeController::show` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | View employee profile |
| `/hr/employees/{id}/edit` | `GET` | `EmployeeController::edit` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | Edit employee form |
| `/hr/employees/{id}/edit` | `POST` | `EmployeeController::update` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])`, `CsrfMiddleware` | Update employee record |
| `/hr/employees/{id}/deactivate` | `POST` | `EmployeeController::deactivate` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])`, `CsrfMiddleware` | Soft-deactivate employee |
| `/hr/departments` | `GET` | `DepartmentController::index` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | Department directory |
| `/hr/departments/create` | `GET` | `DepartmentController::create` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | Create department form |
| `/hr/departments` | `POST` | `DepartmentController::store` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])`, `CsrfMiddleware` | Store department record |
| `/hr/departments/{id}/edit` | `GET` | `DepartmentController::edit` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | Edit department form |
| `/hr/departments/{id}/edit` | `POST` | `DepartmentController::update` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])`, `CsrfMiddleware` | Update department record |
| `/hr/positions` | `GET` | `PositionController::index` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | Job position directory |
| `/hr/positions/create` | `GET` | `PositionController::create` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | Create position form |
| `/hr/positions` | `POST` | `PositionController::store` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])`, `CsrfMiddleware` | Store position record |
| `/hr/positions/{id}/edit` | `GET` | `PositionController::edit` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | Edit position form |
| `/hr/positions/{id}/edit` | `POST` | `PositionController::update` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])`, `CsrfMiddleware` | Update position record |
| `/hr/api/positions-by-department/{id}` | `GET` | `PositionController::getByDepartment` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | Dynamic JSON endpoint for position dropdown |

---

### E. Dedicated Employee Self-Service Routes (Employee Only - Ownership Enforced)
| Route | Method | Controller Action | Middleware Stack | Description |
| :--- | :---: | :--- | :--- | :--- |
| `/employee/dashboard` | `GET` | `DashboardController::employeeDashboard` | `AuthMiddleware`, `RoleMiddleware('employee')` | Personal leave balances & pending requests |
| `/employee/profile` | `GET` | `DashboardController::employeeProfile` | `AuthMiddleware`, `RoleMiddleware('employee')` | Own personal demographic and job profile |
| `/employee/leave-balances` | `GET` | `DashboardController::employeeLeaveBalances` | `AuthMiddleware`, `RoleMiddleware('employee')` | Personal leave balance API (JSON) |
| `/employee/leave-requests` | `GET` | `DashboardController::employeeLeaveRequests` | `AuthMiddleware`, `RoleMiddleware('employee')` | Personal leave applications history (JSON) |
| `/employee/announcements` | `GET` | `EmployeeAnnouncementController::index` | `AuthMiddleware`, `RoleMiddleware('employee')`, `PermissionMiddleware('announcements.read')` | Targeted Employee Announcements feed |
| `/employee/announcements/{id}` | `GET` | `EmployeeAnnouncementController::show` | `AuthMiddleware`, `RoleMiddleware('employee')`, `PermissionMiddleware('announcements.read')` | Announcement detail view (auto-records read receipt) |
| `/employee/announcements/{id}/read` | `POST` | `EmployeeAnnouncementController::markRead` | `AuthMiddleware`, `RoleMiddleware('employee')`, `CsrfMiddleware`, `PermissionMiddleware('announcements.acknowledge')` | Record read/acknowledgment receipt |
| `/employee/announcements/attachments/{id}` | `GET` | `EmployeeAnnouncementController::downloadAttachment` | `AuthMiddleware`, `RoleMiddleware('employee')`, `PermissionMiddleware('announcements.read')` | Authorized targeted attachment download |

---

## 2. Access Response Standards

1. **Unauthenticated Access:** Returns HTTP 302 Redirect to `/login` for HTML requests; returns HTTP 401 Unauthorized for JSON requests.
2. **Unauthorized Role:** Returns HTTP 403 Forbidden with custom error view `app/views/errors/403.php`.
3. **Invalid CSRF Token:** Returns HTTP 302 Redirect to `/login` with flash error for browser forms; returns HTTP 403 Forbidden for JSON requests.
4. **Parameter Spoofing:** Returns HTTP 403 Forbidden with ownership violation notification.
