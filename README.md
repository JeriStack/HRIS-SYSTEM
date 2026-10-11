# Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL%20%2F%20MariaDB-orange.svg)](https://www.mysql.com/)
[![Architecture](https://img.shields.io/badge/Architecture-Custom%20MVC-green.svg)]()
[![Status](https://img.shields.io/badge/Status-Phases%201%20through%207%20Complete-success.svg)]()

## 1. Project Overview

The **Employee HRIS & Leave Management System** is a web-based enterprise application developed using native PHP and MySQL/MariaDB, engineered with a dual-purpose mandate:

1. **Operational HR Software:** Deliver a complete, reliable, and secure Human Resource Information System that manages centralized workforce records, departments, positions, leave entitlements, transactional leave approval workflows, audit trails, and reporting.
2. **Academic DSA Laboratory:** Serve as a dedicated demonstration workbench for Computer Science concepts. The system implements custom Data Structures and Algorithms (DSA) natively in PHP (without relying on PHP built-in functions or MySQL query engines for demonstrated algorithmic behaviors), benchmarking performance across varying dataset scales ($N = 10$ to $10,000$).

---

## 2. System Architecture

The project adheres to a clean **4-Tier Layered Architecture** with strict separation of concerns:

```
    [ USERS: Administrator / HR | Employee ]
                       |
                       v
    [ 1. PRESENTATION LAYER: Views, Layouts, CSS Design System, JS ]
                       |
                       v
    [ 2. APPLICATION LAYER: Controllers, Router, Middleware, In-Memory DSA Engine ]
                       |
                       v
    [ 3. DATA ACCESS LAYER: Centralized PDO Factory, Base Model, Transactions ]
                       |
                       v
    [ 4. DATABASE LAYER: MySQL / MariaDB (hris_db: InnoDB Engine, ACID Row Locks) ]
```

---

## 3. Specifications & Architecture Documentation Index

All software specifications, database designs, wireframes, and architectural documentation are compiled in the `docs/` repository directory:

### Phase 1 — Requirements and Project Initiation
* **[Software Requirements Specification (SRS)](docs/phase-1-requirements/requirements-document.md):** Complete functional (`FR-AUTH`, `FR-EMP`, `FR-ORG`, `FR-LEAVE`, `FR-DSA`, `FR-PERF`) and non-functional requirements.
* **[User Roles and Permissions](docs/phase-1-requirements/user-roles-and-permissions.md):** Administrator/HR vs Employee permission matrices and server-side authorization guards.
* **[Business Rules & Leave Policies](docs/phase-1-requirements/business-rules-and-leave-policies.md):** Workforce rules, working-day formulas, collision checks, and ACID transaction rules.
* **[Project Scope, Assumptions & Limitations](docs/phase-1-requirements/scope-assumptions-and-limitations.md):** In-scope vs out-of-scope boundaries and 10 baseline architectural assumptions.
* **[Team Responsibilities & RACI Matrix](docs/phase-1-requirements/team-responsibilities.md):** Role breakdowns (`ROLE-COORD` to `ROLE-QA`) and allocation schedules.
* **[Initial Backlog & GitHub Setup](docs/phase-1-requirements/initial-backlog.md):** Prioritized backlog (`BL-001` to `BL-030`) and GitHub labels/Kanban setup instructions.

### Phase 2 — System Analysis and Design
* **[System Use Cases](docs/phase-2-system-design/use-cases.md):** Detailed use-case specifications (`UC-AUTH`, `UC-EMP`, `UC-LEAVE`, `UC-DSA`).
* **[Use Case Diagram](docs/phase-2-system-design/use-case-diagram.mmd):** Visual Mermaid diagram illustrating system boundaries and actor associations.
* **[System Flowchart](docs/phase-2-system-design/system-flowchart.mmd):** End-to-end flowchart tracing user navigation, server-side validation, and database transactions.
* **[Layered System Architecture](docs/phase-2-system-design/architecture.md):** In-depth technical specification of Presentation, Application, Data Access, and Database layers.
* **[Architecture Diagram](docs/phase-2-system-design/architecture-diagram.mmd):** Visual Mermaid diagram of subsystem boundaries and data flows.
* **[Application Module Specifications](docs/phase-2-system-design/module-specification.md):** Comprehensive breakdown of all 15 system modules.
* **[Authentication & Authorization Workflow](docs/phase-2-system-design/authentication-workflow.mmd):** Sequence diagram of login, password hashing, session regeneration, timeout, and role gating.
* **[Employee & HR Workflows](docs/phase-2-system-design/employee-workflows.mmd):** Workflow diagrams for employee self-service and HR administration.
* **[Leave Management Workflow](docs/phase-2-system-design/leave-management-workflow.mmd):** Sequence diagram of filing, validation, FIFO queueing, and transaction-safe approval.
* **[DSA to HRIS Mapping Specification](docs/phase-2-system-design/dsa-to-hris-mapping.md):** Detailed mapping of custom PHP structures and sorting/searching algorithms to HRIS operations.
* **[Application Navigation Map](docs/phase-2-system-design/navigation-map.md):** Site tree and route access matrix for both role tiers.
* **[UI Screen & Wireframe Specifications](docs/phase-2-system-design/ui-screen-specifications.md):** Layout specifications for all 14 major views and states.

### Phase 3 — Database Design and Setup
* **[Database Design Specification](docs/phase-3-database/database-design.md):** Relational schema architecture, InnoDB engine, utf8mb4 collation, 3NF normalization.
* **[Comprehensive Data Dictionary](docs/phase-3-database/data-dictionary.md):** Complete column-level data dictionary for all tables.
* **[Entity Relationship Diagram (ERD)](docs/phase-3-database/erd.mmd):** Complete Mermaid ERD with relationships, primary keys, foreign keys, and cardinalities.
* **[Relationship Specification](docs/phase-3-database/relationship-specification.md):** 1:1 and 1:N cardinality, participation, and referential behavior.
* **[Constraints & Indexes Specification](docs/phase-3-database/constraints-and-indexes.md):** Primary, unique, check constraints, and B-Tree indexing strategy.
* **[Referential Integrity & Cascade Policy](docs/phase-3-database/referential-integrity.md):** Evaluation of `ON DELETE RESTRICT`, soft-deletion, and audit preservation.
* **[Database Verification Report](docs/phase-3-database/database-verification-report.md):** Empirical verification results from MySQL execution and constraint testing.

### Phase 4 — UI/UX Design
* **[UI/UX Design System Specification](docs/phase-4-ui-ux/design-system.md):** Deep slate palette, semantic status colors, typography scale, buttons, forms, tables.
* **[Navigation & Sidebar Specification](docs/phase-4-ui-ux/navigation-and-sidebar.md):** Persistent desktop sidebar and mobile off-canvas drawer navigation.
* **[Login Screen Wireframe](docs/phase-4-ui-ux/login-screen.md):** Form layout, visibility toggle, error obfuscation, and CSRF protection.
* **[Administrator / HR Dashboard](docs/phase-4-ui-ux/hr-dashboard.md):** Metric cards, recent activity LIFO Stack widget, quick action hubs.
* **[Employee Dashboard](docs/phase-4-ui-ux/employee-dashboard.md):** Balance progress indicators, active request status card, quick actions.
* **[Employee Management Screens](docs/phase-4-ui-ux/employee-management-screens.md):** Searchable directory table, 3-section registration form, deactivation modal.
* **[Department & Position Screens](docs/phase-4-ui-ux/department-and-position-screens.md):** Split pane CRUD layouts, employee count badges, dependency alerts.
* **[Leave Management Screens](docs/phase-4-ui-ux/leave-management-screens.md):** Working-day calculation form, FIFO queue review card, transactional approval modal.
* **[Reports Screens](docs/phase-4-ui-ux/reports-screen.md):** Workforce, leave utilization, and departmental printable reports.
* **[Notifications Screen](docs/phase-4-ui-ux/notifications-screen.md):** In-system notification inbox and unread status indicators.
* **[System Activity Logs](docs/phase-4-ui-ux/activity-logs-screen.md):** Immutable audit table and detailed event inspection modal.
* **[DSA Laboratory & Visualizer](docs/phase-4-ui-ux/dsa-laboratory-screens.md):** Interactive data structures playground and multi-scale benchmark visualizer.
* **[Responsive & Accessibility Standards](docs/phase-4-ui-ux/responsive-and-accessibility.md):** Viewport breakpoints, touch target sizing ($\ge 44\text{ px}$), WCAG 2.1 AA.
* **[Feedback & Confirmation States](docs/phase-4-ui-ux/feedback-and-confirmation-states.md):** Banner alerts and consequential action modals.
* **[Master Wireframe Index](docs/phase-4-ui-ux/wireframe-index.md):** Cross-reference directory of all 14 screens.

### Phase 6 — Authentication and Authorization
* **[Authentication Architecture & Design](docs/phase-6-authentication/authentication-design.md):** Complete authentication workflow, native BCRYPT hashing, session regeneration, timeout, and audit logging.
* **[System Authorization Matrix](docs/phase-6-authentication/authorization-matrix.md):** Administrator/HR vs Employee permissions, route gating, ownership verification, and fail-closed policies.
* **[Session Security & CSRF Guidelines](docs/phase-6-authentication/session-security.md):** Cookie hardening (`HttpOnly`, `SameSite=Lax`), inactivity timeout, and timing-safe CSRF validation.
* **[Security Test & Verification Report](docs/phase-6-authentication/authentication-test-report.md):** Empirical results from automated HTTP test suite (`11 / 11 tests passed`).

### Phase 7 — Core HRIS Development
* **[Employee Management Specification](docs/phase-7-core-hris/employee-management.md):** Workforce registry, directory, profile layouts, registration, updates, and soft-deactivation.
* **[Department Management Specification](docs/phase-7-core-hris/department-management.md):** Organizational unit hierarchy, global case-insensitive uniqueness, and position/staff aggregations.
* **[Position Management Specification](docs/phase-7-core-hris/position-management.md):** Department-scoped job classification titles, dynamic cascade filtering, and API endpoint.
* **[Server-Side Validation Rules Matrix](docs/phase-7-core-hris/validation-rules.md):** Comprehensive validation constraints, sanitize pipelines, and error response standards.
* **[Authorization & Routing Architecture](docs/phase-7-core-hris/authorization-and-routing.md):** Complete catalog of protected HR routes, middleware stacks, and parameterized matching rules.
* **[CRUD & Integration Test Report](docs/phase-7-core-hris/crud-test-report.md):** Full empirical report of 91 automated unit, model, and live HTTP tests (`91 / 91 passed`).

### Cross-Phase Tracking
* **[Decision & Assumptions Log](docs/decision-log.md):** Registry of 11 architectural baseline decisions and open items.
* **[Requirements Traceability Matrix (RTM)](docs/requirements-traceability.md):** Bidirectional matrix mapping requirements to modules, database entities, UI screens, and test cases.

---

## 4. Local Database Setup Instructions

The database scripts are organized in the [`database/`](database/) directory:
* [`database/schema.sql`](database/schema.sql) — DDL table definitions, foreign keys, and indexes.
* [`database/seed.sql`](database/seed.sql) — Synthetic baseline dataset with pre-hashed credentials.
* [`database/test_data.sql`](database/test_data.sql) — Constraint verification and negative test queries.
* [`database/README.md`](database/README.md) — Comprehensive database installation and maintenance guide.

### Quick Import via MySQL CLI
```powershell
# 1. Create development database
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS hris_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Import Schema DDL
C:\xampp\mysql\bin\mysql.exe -u root hris_db < database/schema.sql

# 3. Import Seed Data
C:\xampp\mysql\bin\mysql.exe -u root hris_db < database/seed.sql
```

### Seed Development Accounts
* **Administrator:** `admin` / `Admin@123`
* **HR Officer:** `hr01` / `Admin@123`
* **Employee:** `emp004` / `Employee@123`

---

## 5. Phase 5 — Project Foundation & Core Framework Setup

The native PHP MVC core framework is active and verified:

### Prerequisites
* **PHP:** PHP 8.1 or higher (verified on PHP 8.5+ with `pdo_mysql`, `mbstring`, `json` extensions).
* **Web Server:** Apache HTTP Server with `mod_rewrite` enabled (standard in XAMPP).
* **Database:** MySQL 8.x or MariaDB with `hris_db` schema imported.

### Step-by-Step Local Setup Instructions

1. **Clone & Location:**
   Place the project inside your web server root (e.g., `c:\xampp\htdocs\GitHub_Clone_File_Projects\HRIS-SYSTEM`).

2. **Start Services in XAMPP:**
   Open the XAMPP Control Panel and start **Apache** and **MySQL**.

3. **Initialize Database:**
   Import the schema and baseline test seed into MySQL:
   ```bash
   mysql -u root -p hris_db < database/schema.sql
   mysql -u root -p hris_db < database/seed.sql
   ```

4. **Verify Database Configuration:**
   Inspect `config/database.php` or copy `.env.example` to `.env`. Ensure host (`127.0.0.1`), database (`hris_db`), username (`root`), and password match your environment.

5. **Verify Framework & Foundation Routes in Browser:**
   Open your browser to:
   * **Public Landing Page:** `http://localhost/GitHub_Clone_File_Projects/HRIS-SYSTEM/public/`
   * **Framework Health Diagnostic (JSON):** `http://localhost/GitHub_Clone_File_Projects/HRIS-SYSTEM/public/health`
   * **Interactive Prototype Suite:** `http://localhost/GitHub_Clone_File_Projects/HRIS-SYSTEM/public/prototype/index.html`

### Foundation Health Check Response
Requesting `GET /health` returns live framework status:
```json
{
    "status": "ok",
    "timestamp": "2026-10-11T11:27:50+08:00",
    "environment": "development",
    "php_version": "8.5.9",
    "framework": "Native PHP MVC Foundation (Phase 5)",
    "database": {
        "driver": "mysql",
        "database": "hris_db",
        "connected": true,
        "ping": "success",
        "latency_ms": 0.22
    },
    "routing": {
        "status": "operational",
        "router": "Core\\Router"
    }
}
```

---

## 6. Authoritative Project Directory Tree

```
Employee-HRIS-Leave-Management-System/
├── .github/                       # Workflows and issue templates
├── config/                        # Application & database settings
│   ├── config.php                 # App name, timezone, session lifetime (1800s)
│   └── database.php               # PDO database connection settings
├── core/                          # Framework kernel & security guards
│   ├── bootstrap.php              # PSR-4 autoloader, functions, timezone, session bootstrap
│   ├── Session.php                # Hardened session manager (Strict, HttpOnly, SameSite, CSRF)
│   ├── AuthMiddleware.php         # Authentication gatekeeper middleware
│   ├── RoleMiddleware.php         # Role-Based Access Control (RBAC) middleware
│   ├── GuestMiddleware.php        # Guest redirection middleware
│   ├── CsrfMiddleware.php         # Cross-Site Request Forgery verification middleware
│   ├── Controller.php             # Base controller with view and json helpers
│   ├── Database.php               # Centralized PDO database singleton
│   ├── Middleware.php             # Abstract middleware contract
│   ├── Model.php                  # Base model with query & transaction helpers
│   ├── Router.php                 # Native HTTP router (GET/POST, regex, 404/405)
│   └── functions.php              # Global helper functions (auth, csrf, e, url, asset)
├── app/
│   ├── controllers/               # MVC Controllers
│   │   ├── AuthController.php     # Login, logout, session lifecycle, dashboard router
│   │   ├── DashboardController.php# Protected HR and Employee dashboard actions
│   │   ├── DepartmentController.php # Phase 7: Department directory, creation, and updates
│   │   ├── EmployeeController.php # Phase 7: Workforce directory, registration, profile, deactivation
│   │   ├── HomeController.php     # Public landing & health check controller
│   │   └── PositionController.php # Phase 7: Job position directory, department linking, dynamic API
│   ├── models/                    # Domain entity models
│   │   ├── Department.php         # Phase 7: Department CRUD, uniqueness, headcount aggregations
│   │   ├── Employee.php           # Phase 7: Workforce CRUD, search, soft deactivation, metrics
│   │   ├── Position.php           # Phase 7: Job positions, dept-scoped uniqueness, department API
│   │   └── User.php               # User credentials, BCRYPT verification, audit logging
│   ├── dsa/                       # Custom In-Memory DSA Engine
│   │   ├── algorithms/            # Linear/Binary Search, Bubble/Merge/QuickSort
│   │   └── structures/            # ArrayWrapper, Stack, Queue, LinkedList, HashTable, BST
│   └── views/                     # Layouts, partials, and view templates
│       ├── auth/login.php         # Phase 6 authentication screen (SCR-01)
│       ├── departments/           # Phase 7 Department views (index, create, edit)
│       ├── employees/             # Phase 7 Employee views (index, create, edit, show)
│       ├── errors/403.php         # 403 Forbidden Access Denied view
│       ├── dashboard/             # Role-specific dashboard views
│       ├── home/index.php         # Public landing page template
│       ├── layouts/               # Master layouts (main.php, hr.php)
│       ├── partials/              # Reusable partials (sidebar.php, navbar.php, alerts.php)
│       └── positions/             # Phase 7 Position views (index, create, edit)
├── database/                      # Database scripts
│   ├── README.md                  # Database setup instructions
│   ├── schema.sql                 # DDL schema definitions (9 tables, InnoDB)
│   ├── seed.sql                   # Synthetic demo seed data with BCRYPT credentials
│   └── test_data.sql              # Constraint verification tests
├── docs/                          # Architectural documentation
│   ├── decision-log.md            # Decisions & assumptions log
│   ├── requirements-traceability.md # Requirements Traceability Matrix
│   ├── phase-1-requirements/      # Phase 1 specifications
│   ├── phase-2-system-design/     # Phase 2 specifications & diagrams
│   ├── phase-3-database/          # Phase 3 database specifications & ERD
│   ├── phase-4-ui-ux/             # Phase 4 design system & wireframes
│   ├── phase-6-authentication/    # Phase 6 authentication & security specifications
│   └── phase-7-core-hris/         # Phase 7 employee, department, position, validation, test reports
├── public/                        # Public document root
│   ├── assets/                    # CSS, JS, images
│   ├── uploads/                   # Upload storage (.gitkeep)
│   ├── .htaccess                  # Apache mod_rewrite & security rules
│   └── index.php                  # Front controller entry point
├── .env.example                   # Environment variable template
├── .gitignore                     # Git exclusion rules
├── LICENSE
└── README.md
```

---

## 7. Demonstration Accounts & Local Testing

The database (`database/seed.sql`) includes pre-hashed development accounts configured for local testing:

| Username | Password | Role | Assigned Employee | Access Permissions |
| :--- | :--- | :--- | :--- | :--- |
| `admin` | `Admin@123` | `admin` | Eleanor Vance | Full Administrative HR Access (`/hr/*`) |
| `hr01` | `Admin@123` | `hr` | Marcus Tan | Full HR Portal Access (`/hr/*`) |
| `emp004` | `Employee@123` | `employee` | David Cruz | Employee Self-Service Only (`/employee/*`) |
| `emp005` | `Employee@123` | `employee` | Maria Santos | Employee Self-Service Only (`/employee/*`) |
| `emp006` | `Employee@123` | `employee` | Juan Dela Cruz | Employee Self-Service Only (`/employee/*`) |

### Verification of Role Separation & Core HRIS:
1. **Login as HR Admin:** Sign in with `admin` / `Admin@123` $\rightarrow$ automatically redirected to `/hr/dashboard`.
2. **Access Employee Directory:** Navigate to `http://localhost/.../public/hr/employees` $\rightarrow$ full search, status filters, profile viewing, and soft deactivation controls.
3. **Manage Departments:** Navigate to `http://localhost/.../public/hr/departments` $\rightarrow$ view and create departments with uniqueness enforcement.
4. **Manage Job Positions:** Navigate to `http://localhost/.../public/hr/positions` $\rightarrow$ view and create positions associated with parent departments.
5. **Login as Employee:** Sign in with `emp004` / `Employee@123` $\rightarrow$ automatically redirected to `/employee/dashboard`.
6. **Test Boundary Check:** While logged in as `emp004`, manually navigate to `http://localhost/.../public/hr/employees` $\rightarrow$ **HTTP 403 Forbidden** is enforced server-side.
7. **Test State Mutation Check:** While logged in as `emp004`, submit POST to `/hr/employees/1/deactivate` $\rightarrow$ **HTTP 403 Forbidden** is enforced server-side.

---

## 8. Security Baseline & Milestone Status

* **Authentication & Password Hashing:** Native `password_hash()` and `password_verify()` (BCRYPT, cost 12).
* **Session Protections:** `session_regenerate_id(true)`, `HttpOnly`, `SameSite=Lax`, strict mode, 30-minute inactivity timeout.
* **Access Control:** Server-side `AuthMiddleware` and `RoleMiddleware` protecting all administrative routes (`/hr/*`).
* **CSRF Mitigation:** Timing-safe `hash_equals()` validation on state-changing requests (`POST`).
* **Audit Logging:** Real-time event recording in `activity_logs` for workforce creations, modifications, deactivations, departments, and positions.
* **Phases 1 through 7 Status:** **100% Complete & Verified (91/91 automated tests passing).**
* **Next Milestone:** **Phase 8 — Data Structures and Algorithms (DSA) Implementation & Search/Sort Demonstrations.**

