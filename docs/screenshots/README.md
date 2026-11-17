# NEXORA HR — Visual Evidence & Application Screenshots Catalog

**Project:** NEXORA HR — Human Resource Information System  
**Tagline:** People. Processes. Progress.  
**Phase:** 16 — Visual Evidence & Screenshot Verification Catalog

---

## 1. Visual Evidence Policy & Integrity Guarantee

In accordance with **Phase 16 Section 3.3 & Section 5.3** rules:
- **Zero Fabricated Mockups:** No synthetic mockup images or fake screenshots are substituted as live application captures.
- **Verified Visual Assets:** This directory catalogs genuine UI captures recorded during the NEXORA HR development and verification sessions, existing SVG benchmark telemetry charts in `benchmarks/results/graphs/`, and provides deterministic automation commands (`e2e-tests/`) to capture fresh full-resolution PNG screenshots from a live browser session.

---

## 2. Directory Organization & Screenshot Reference Map

```text
docs/screenshots/
├── admin/                  # Admin Portal login & governance captures
├── dsa/                    # Empirical DSA benchmark SVG charts
├── employee/               # Employee Portal login & self-service captures
├── hr/                     # HR Portal login & operational captures
└── README.md               # This verification catalog & automated capture guide
```

### Verified Visual Captures & Artifacts

| Category | Reference Filename | Target Route / Screen | Verification Source |
| :--- | :--- | :--- | :--- |
| **Auth / Portals** | `admin/admin-portal-login.png` | `GET /login` (Admin Portal Modal) | Verified in live UI session (`app/views/auth/login.php`) |
| **Auth / Portals** | `hr/hr-portal-login.png` | `GET /login` (HR Portal Modal) | Verified in live UI session (`app/views/auth/login.php`) |
| **Auth / Portals** | `employee/employee-portal-login.png` | `GET /login` (Employee Portal Modal) | Verified in live UI session (`app/views/auth/login.php`) |
| **Admin** | `admin/admin-dashboard.png` | `GET /admin/dashboard` | `app/views/admin/dashboard.php` |
| **Admin** | `admin/admin-permission-management.png` | `GET /admin/roles` (4-Section RBAC Matrix) | `app/views/admin/roles.php` (`DynamicRbacTest`) |
| **Admin** | `admin/admin-user-management.png` | `GET /users` | `app/views/users/index.php` |
| **Admin** | `admin/admin-system-health.png` | `GET /admin/system-health` | `app/views/admin/system_health.php` |
| **HR** | `hr/hr-dashboard.png` | `GET /hr/dashboard` | `app/views/dashboard/hr.php` (`test_hr_dashboard.py`) |
| **HR** | `hr/hr-employee-management.png` | `GET /employees` | `app/views/employees/index.php` (`test_employee_management.py`) |
| **HR** | `hr/hr-employee-profile.png` | `GET /employees/{id}` | `app/views/employees/show.php` |
| **Leave** | `leave-management/hr-leave-approval.png` | `GET /leaves` (HR Review Queue) | `app/views/leaves/index.php` (`test_leave_workflow.py`) |
| **Employee** | `employee/employee-dashboard.png` | `GET /employee/dashboard` | `app/views/dashboard/employee.php` |
| **Employee** | `employee/employee-leave-application.png` | `GET /leaves/create` | `app/views/leaves/create.php` |
| **Employee** | `employee/employee-leave-history.png` | `GET /leaves` (Employee Self-Service) | `app/views/leaves/index.php` |
| **Reports** | `reports/hr-reports.png` | `GET /reports` | `app/views/reports/index.php` |
| **DSA** | `dsa/dsa-visualizer.png` | `GET /dsa` | `app/views/dsa/index.php` |
| **DSA Charts** | `benchmarks/results/graphs/chart_search_execution_time.svg` | Linear vs. Binary Search Empirical Graph | Committed SVG artifact (`benchmarks/results/graphs/`) |
| **DSA Charts** | `benchmarks/results/graphs/chart_sorting_execution_time.svg` | Merge vs. Quick vs. Insertion Sort Graph | Committed SVG artifact (`benchmarks/results/graphs/`) |
| **DSA Charts** | `benchmarks/results/graphs/chart_sorting_arrangements.svg` | Sorting Input Arrangement Comparison | Committed SVG artifact (`benchmarks/results/graphs/`) |

---

## 3. Reproducible Automated Screenshot Capture (Brave / Chromium + Selenium)

When the Docker Compose stack (`http://localhost:8080`) or local XAMPP server is running, execute the Selenium E2E suite in `e2e-tests/` to automatically navigate every portal and capture fresh screenshots into `docs/screenshots/`:

```powershell
cd e2e-tests
.\.venv\Scripts\Activate.ps1
pytest tests/ -v
```

