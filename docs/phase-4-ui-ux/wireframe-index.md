# Complete Wireframe & Screen Specification Index
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 4 — UI/UX Design  
**Status:** Approved for Frontend Implementation  

---

## 1. Master Wireframe Directory

The table below catalogs all 14 screens specified for the HRIS and Leave Management application:

| Screen ID | Screen Title | Target Actor | Route | Specification Document | Key Components & Design Highlights |
| :---: | :--- | :--- | :--- | :--- | :--- |
| `SCR-00` | Public Landing Page | Public / Unauthenticated | `GET /` | [`landing-page-design.md`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/phase-4-ui-ux/landing-page-design.md) | Hero with interactive browser mockup, 6 core feature cards, 3-step workflow, DSA pipeline showcase, role comparison. |
| `SCR-01` | User Login Screen | Unauthenticated | `GET /login` | [`login-screen.md`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/phase-4-ui-ux/login-screen.md) | Single-card layout, show/hide password, generic error obfuscation, CSRF protection. |
| `SCR-02` | Admin / HR Dashboard | `admin`, `hr` | `GET /admin/dashboard` | [`hr-dashboard.md`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/phase-4-ui-ux/hr-dashboard.md) | 4 executive metric cards, recent activity LIFO Stack widget, quick actions hub. |
| `SCR-03` | Employee Dashboard | `employee` | `GET /dashboard` | [`employee-dashboard.md`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/phase-4-ui-ux/employee-dashboard.md) | Personal summary banner, leave balance progress rings/bars, active request status card. |
| `SCR-04` | Employee Directory | `admin`, `hr` | `GET /employees` | [`employee-management-screens.md`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/phase-4-ui-ux/employee-management-screens.md) | Searchable workforce table with department/status filters, pagination, view/edit/deactivate actions. |
| `SCR-05` | Add / Edit Employee Form | `admin`, `hr` | `GET /employees/create` | [`employee-management-screens.md`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/phase-4-ui-ux/employee-management-screens.md) | 3-section responsive form (personal, contact, employment), validation states, auto-balance initialization notice. |
| `SCR-06` | Employee Deactivation Modal | `admin`, `hr` | `POST /employees/{id}/deactivate` | [`employee-management-screens.md`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/phase-4-ui-ux/employee-management-screens.md) | Explanatory modal ensuring soft-deactivation understanding (`employment_status = 'Inactive'`). |
| `SCR-07` | Department Management | `admin`, `hr` | `GET /departments` | [`department-and-position-screens.md`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/phase-4-ui-ux/department-and-position-screens.md) | Split pane layout (create form left, list right), employee count badges, delete restriction alerts. |
| `SCR-08` | Job Position Management | `admin`, `hr` | `GET /positions` | [`department-and-position-screens.md`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/phase-4-ui-ux/department-and-position-screens.md) | Department-filtered position creation and directory, referential check dialog. |
| `SCR-09` | File Leave Request Form | `employee` | `GET /leaves/apply` | [`leave-management-screens.md`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/phase-4-ui-ux/leave-management-screens.md) | Real-time working-day duration calculator, live balance indicators, overlap collision alerts. |
| `SCR-10` | Leave Approval Queue | `admin`, `hr` | `GET /leaves/manage` | [`leave-management-screens.md`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/phase-4-ui-ux/leave-management-screens.md) | Visual FIFO Queue pipeline header, top active item review card, transaction-safe approval action. |
| `SCR-11` | Leave History & Cancellation | `employee`, `admin` | `GET /leaves/history` | [`leave-management-screens.md`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/phase-4-ui-ux/leave-management-screens.md) | Chronological leave records, status badges, self-cancellation modal for pending filings. |
| `SCR-12` | Reports & Analytics | `admin`, `hr` | `GET /reports/*` | [`reports-screen.md`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/phase-4-ui-ux/reports-screen.md) | Workforce, leave utilization, and departmental reports with print-optimized stylesheets. |
| `SCR-13` | System Activity Logs | `admin`, `hr` | `GET /activity-logs` | [`activity-logs-screen.md`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/phase-4-ui-ux/activity-logs-screen.md) | Immutable audit table with action filters and detailed event inspection modal dialog. |
| `SCR-14` | DSA Playground & Benchmarks | `admin`, `hr` | `GET /dsa/*` | [`dsa-laboratory-screens.md`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/phase-4-ui-ux/dsa-laboratory-screens.md) | Interactive data structure workbench (BST, Stack, Queue) and multi-scale sorting/search execution visualizer. |

---

## 2. Cross-Cutting Design Guides

* **[Landing Page Design Guide](landing-page-design.md):** Public welcome page, hero product preview, and workflow architecture.
* **[Design System Specification](design-system.md):** Color tokens, typography hierarchy, elevation, button variants, and tables.
* **[Navigation & Persistent Sidebar](navigation-and-sidebar.md):** Desktop sidebar and mobile off-canvas drawer layouts.
* **[Responsive & Accessibility Standards](responsive-and-accessibility.md):** Breakpoints, touch targets ($\ge 44\text{ px}$), WCAG 2.1 AA contrast ($> 4.5:1$).
* **[Feedback & Confirmation States](feedback-and-confirmation-states.md):** Alert banners and consequential action dialogs.
