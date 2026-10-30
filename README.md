# Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL%20%2F%20MariaDB-orange.svg)](https://www.mysql.com/)
[![Architecture](https://img.shields.io/badge/Architecture-Custom%20MVC%20(2--Role%20RBAC)-green.svg)]()
[![Status](https://img.shields.io/badge/Status-Production%20Ready%20%26%20100%25%20Tested-success.svg)]()
[![Tests](https://img.shields.io/badge/Tests-288%20%2F%20288%20Passing-brightgreen.svg)]()

## 1. Project Overview

The **Employee HRIS & Leave Management System** is a web-based enterprise application developed using native PHP and MySQL/MariaDB, engineered with a dual-purpose mandate:

1. **Operational HR Software:** Deliver a complete, reliable, and secure Human Resource Information System that manages centralized workforce records, departments, positions, leave entitlements, transactional leave approval workflows, audit trails, and reporting under a clean **2-Role Architecture (Human Resources & Employee Self-Service)**.
2. **Academic DSA Laboratory:** Serve as a dedicated demonstration workbench for Computer Science concepts. The system implements custom Data Structures and Algorithms (DSA) natively in PHP (without relying on PHP built-in functions or MySQL query engines for demonstrated algorithmic behaviors), benchmarking performance across varying dataset scales ($N = 10$ to $10,000$).

---

## 2. System Architecture

The project adheres to a clean **4-Tier Layered Architecture** with strict server-side Role-Based Access Control (RBAC):

```
    [ USERS: HR Management (hr) | Employee Self-Service (employee) ]
                                   |
                                   v
    [ 1. PRESENTATION LAYER: Accessible Semantic Views, CSS Design Tokens, Responsive Sidebar, JS ]
                                   |
                                   v
    [ 2. APPLICATION LAYER: Controllers, Router (Dual-Base URL Support), RBAC Middlewares, In-Memory DSA Engine ]
                                   |
                                   v
    [ 3. DATA ACCESS LAYER: Centralized PDO Factory, Base Model, ACID Transactions, Row Locking ]
                                   |
                                   v
    [ 4. DATABASE LAYER: MySQL / MariaDB (hris_db: InnoDB Engine, Foreign Key Integrity, B-Tree Indexes) ]
```

---

## 3. Specifications & Architecture Documentation Index

All software specifications, database designs, wireframes, security audits, and architectural documentation are compiled in the `docs/` repository directory:

### Phase 1 — Requirements and Project Initiation
* **[Software Requirements Specification (SRS)](docs/phase-1-requirements/requirements-document.md):** Complete functional (`FR-AUTH`, `FR-EMP`, `FR-ORG`, `FR-LEAVE`, `FR-DSA`, `FR-PERF`) and non-functional requirements.
* **[User Roles and Permissions](docs/phase-1-requirements/user-roles-and-permissions.md):** 2-Role permission matrix (HR Management vs Employee Self-Service) and server-side authorization guards.
* **[Business Rules & Leave Policies](docs/phase-1-requirements/business-rules-and-leave-policies.md):** Working-day formulas (excluding weekends), collision checks, and ACID balance deduction rules.
* **[Project Scope, Assumptions & Limitations](docs/phase-1-requirements/scope-assumptions-and-limitations.md):** In-scope vs out-of-scope boundaries and baseline architectural assumptions.
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
* **[Comprehensive Data Dictionary](docs/phase-3-database/data-dictionary.md):** Complete column-level data dictionary for all 9 tables.
* **[Entity Relationship Diagram (ERD)](docs/phase-3-database/erd.mmd):** Complete Mermaid ERD with relationships, primary keys, foreign keys, and cardinalities.
* **[Relationship Specification](docs/phase-3-database/relationship-specification.md):** 1:1 and 1:N cardinality, participation, and referential behavior.
* **[Constraints & Indexes Specification](docs/phase-3-database/constraints-and-indexes.md):** Primary, unique, check constraints, and B-Tree indexing strategy.
* **[Referential Integrity & Cascade Policy](docs/phase-3-database/referential-integrity.md):** Evaluation of `ON DELETE RESTRICT`, soft-deletion, and audit preservation.
* **[Database Verification Report](docs/phase-3-database/database-verification-report.md):** Empirical verification results from MySQL execution and constraint testing.

### Phase 4 — UI/UX Design System
* **[UI/UX Design System Specification](docs/phase-4-ui-ux/design-system.md):** Design tokens, deep slate palette, semantic status colors, typography scale, buttons, forms, tables.
* **[Navigation & Sidebar Specification](docs/phase-4-ui-ux/navigation-and-sidebar.md):** Persistent desktop sidebar and mobile off-canvas drawer navigation.
* **[Login Screen Specification](docs/phase-4-ui-ux/login-screen.md):** Form layout, visibility toggle, error obfuscation, quick-fill buttons, and CSRF protection.
* **[HR Management Dashboard](docs/phase-4-ui-ux/hr-dashboard.md):** Operational metric cards, daily workflows hub, and workforce addition streams.
* **[Employee Self-Service Dashboard](docs/phase-4-ui-ux/employee-dashboard.md):** Real-time leave balance gauges, recent request timeline, quick leave application actions.
* **[Workforce Directory & Registration Screens](docs/phase-4-ui-ux/employee-management-screens.md):** Filterable table, multi-section demographic forms, soft deactivation modals.
* **[Department & Position Screens](docs/phase-4-ui-ux/department-and-position-screens.md):** CRUD layouts, employee count badges, department-scoped position linking.
* **[Leave Review & Balances Screens](docs/phase-4-ui-ux/leave-management-screens.md):** FIFO queue review cards, deduction previews, transactional approval modals.
* **[Workforce Reports & Analytics](docs/phase-4-ui-ux/reports-screen.md):** Headcount summaries, leave utilization analytics, printable summary layouts.
* **[In-System Notification Center](docs/phase-4-ui-ux/notifications-screen.md):** Unread badges, categorized workflow alerts, and mark-all-read actions.
* **[System Security Activity Trail](docs/phase-4-ui-ux/activity-logs-screen.md):** Immutable audit table and security event inspection.
* **[DSA Laboratory & Benchmarks](docs/phase-4-ui-ux/dsa-laboratory-screens.md):** Interactive algorithm benchmark graphs, structures visualizer, and step counters.
* **[Accessibility & Responsive Guidelines](docs/phase-4-ui-ux/responsive-and-accessibility.md):** Touch target sizing ($\ge 44\text{ px}$), WCAG 2.1 AA color contrast compliance.

### Phase 8 — Complete Leave Management System
* **[Phase 8 Overview & Architecture](docs/phase-8-leave-management/README.md):** Architectural boundaries, component layout, and verification summary.
* **[Leave Classification Management](docs/phase-8-leave-management/leave-type-management.md):** Schema, unique constraints, and soft deactivation preservation.
* **[Leave Balance Management](docs/phase-8-leave-management/leave-balance-management.md):** Tracking quotas, atomic deduction on approval, and authorized adjustments.
* **[Leave Filing Workflow](docs/phase-8-leave-management/leave-filing-workflow.md):** Employee application lifecycle, validation sequence, and reservation checks.
* **[Leave-Day Calculation Service](docs/phase-8-leave-management/leave-day-calculation.md):** Monday–Friday working day calculation rules, boundary handling, and test cases.
* **[Server-Side Validation Rules](docs/phase-8-leave-management/leave-validation-rules.md):** Comprehensive validation matrix, CSRF protection, and balance checks.
* **[Leave Status Transitions](docs/phase-8-leave-management/leave-status-transitions.md):** Lifecycle state machine, permitted transitions, and race condition defenses.
* **[Leave Approval & Rejection](docs/phase-8-leave-management/leave-approval-and-rejection.md):** Review queue operations, atomic balance updates, and rejection reason capture.
* **[Leave Cancellation & History](docs/phase-8-leave-management/leave-cancellation-and-history.md):** Self-service cancellation, privacy isolation, and organization-wide history.
* **[Routes & Permission Matrix](docs/phase-8-leave-management/leave-routes-and-permissions.md):** Full route inventory, HTTP methods, and role access guards.
* **[Automated Test Execution Report](docs/phase-8-leave-management/leave-test-report.md):** Complete test execution report covering 86 Phase 8 automated tests.
* **[Database Migration Notes](docs/phase-8-leave-management/migration-notes.md):** Non-destructive schema migration documentation for `rejection_reason`.

### Security, RBAC & Deployment Audits
* **[System Repair & Remediation Log](docs/audits/repair-log.md):** Complete record of audited defects, root cause analyses, implemented fixes, and empirical test pass evidence.
* **[Technical Audit Report](docs/audits/technical-audit-report.md):** Architectural review across 10 evaluation categories.
* **[Project Progress Report](docs/audits/project-progress-report.md):** Cumulative roadmap completion tracking.
* **[Role-Based Authorization Architecture](docs/rbac/authorization-architecture.md):** Specification of the fail-closed 2-role permissions matrix.
* **[Production Deployment Guide](docs/deployment/production-deployment.md):** Linux/Apache server deployment checklist, TLS hardening, and environment variables.

---

## 4. Key System Features & Highlights

### 1. Simplified 2-Role System Architecture
* **HR Officers (`hr`)**: Hold authoritative governance over all operational workforce records (Employees, Departments, Job Positions), leave request reviews, annual balances, reports, audit trails, and **User Account & Role Governance (`/hr/users`)**.
* **Employees (`employee`)**: Access dedicated self-service dashboards, personal profiles, real-time leave balances, and submission/cancellation of personal leave applications.
* **Strict Ownership Enforcement (NFR-SEC-004)**: Employees can never view or modify another employee's records (`?emp_id=X` query spoofing is rejected with HTTP 403 Forbidden).
* **Last-Active HR Lockout Protection**: The system prevents demoting or deactivating the last remaining active HR Officer account.

### 2. In-System Notification Center
* Event-driven alerts automatically generated upon leave submission, manager approvals, rejections, and cancellations.
* Dynamic unread counter badges integrated into the navigation navbar and sidebar.
* Bulk mark-all-as-read and individual read-state transitions secured with CSRF verification.

### 3. Dual-Base URL Router Engine
* Core router (`core/Router.php`) automatically resolves incoming request paths whether accessed directly through root proxy (`http://localhost:3000/.../HRIS-SYSTEM/`) or via public subfolder (`http://localhost/.../HRIS-SYSTEM/public/`), completely eliminating 404 proxy routing issues.

### 4. Native Data Structures & Algorithms Laboratory
* Built natively in PHP without built-in library dependencies:
  * **Structures:** `Stack` (LIFO activity widgets), `Queue` (FIFO leave review queue), `HashTable`, `BinarySearchTree` (BST).
  * **Algorithms:** `BubbleSort`, `MergeSort`, `QuickSort`, `BinarySearch`.
  * **Live Benchmarking:** Interactive runtime execution comparison across varying dataset scales ($N = 10, 100, 1000, 5000$).

---

## 5. Authoritative Project Directory Tree

```
Employee-HRIS-Leave-Management-System/
├── .github/                       # GitHub workflows and issue templates
├── config/                        # Application & database settings
│   ├── .htaccess                  # Strict directory security denial
│   ├── config.php                 # Core configuration (base URL, timeouts, app environment)
│   └── database.php               # Database connection settings & PDO flags
├── core/                          # Framework kernel & security guards
│   ├── .htaccess                  # Direct PHP file execution denial
│   ├── AuthMiddleware.php         # Authentication gatekeeper middleware
│   ├── CsrfMiddleware.php         # Timing-safe CSRF token verification middleware
│   ├── Controller.php             # Base controller with view and json rendering helpers
│   ├── Database.php               # Centralized PDO database singleton
│   ├── GuestMiddleware.php        # Guest redirection middleware for login
│   ├── Middleware.php             # Abstract middleware contract
│   ├── Model.php                  # Base database model with transaction & logging helpers
│   ├── Permission.php             # Centralized 2-Role RBAC Capability Matrix allowlist
│   ├── RoleMiddleware.php         # Role-Based Access Control gatekeeper
│   ├── Router.php                 # Dual-base HTTP router (regex capture, 404/405 fallbacks)
│   ├── Session.php                # Hardened session manager (HttpOnly, SameSite, flash messages)
│   ├── bootstrap.php              # Global bootstrap & class autoloader
│   └── functions.php              # Global helper functions (auth, csrf, url, asset, e)
├── app/
│   ├── .htaccess                  # Application directory access denial
│   ├── controllers/               # MVC Controllers
│   │   ├── ActivityLogController.php # Audit trail controller
│   │   ├── AuthController.php     # Login, logout, session lifecycle, dashboard dispatcher
│   │   ├── DashboardController.php# HR and Employee dashboard & self-service views
│   │   ├── DepartmentController.php # Department directory, creation, and updates
│   │   ├── DsaController.php      # DSA laboratory & benchmark visualizer
│   │   ├── EmployeeController.php # Workforce directory, profile, updates, soft deactivation
│   │   ├── HomeController.php     # Public landing page & system health checks
│   │   ├── LeaveController.php    # Leave applications, reviews, balance adjustments
│   │   ├── NotificationController.php # In-system notification center actions
│   │   ├── PositionController.php # Job classification directory, dynamic cascade API
│   │   ├── ReportController.php   # Workforce & leave utilization analytics reports
│   │   └── UserController.php     # User account governance & role assignments
│   ├── models/                    # Domain entity models
│   │   ├── ActivityLog.php        # Audit trail logging
│   │   ├── Department.php         # Department queries & uniqueness checks
│   │   ├── Employee.php           # Employee queries, search algorithms, filters
│   │   ├── Leave.php              # Leave balance calculations & ACID transaction approvals
│   │   ├── Notification.php       # In-system notifications model
│   │   ├── Position.php           # Department-scoped position queries
│   │   └── User.php               # User credentials, BCRYPT verification, lockout guards
│   ├── dsa/                       # Custom In-Memory DSA Engine
│   │   ├── algorithms/            # BubbleSort, MergeSort, QuickSort, BinarySearch
│   │   └── structures/            # Stack, Queue, BinarySearchTree
│   └── views/                     # Layouts, partials, and view templates
│       ├── activity_logs/         # Audit activity trail views
│       ├── auth/                  # Authentication login views
│       ├── dashboard/             # Dedicated HR & Employee dashboard views
│       ├── departments/           # Department management views
│       ├── dsa/                   # DSA Laboratory & benchmark playground
│       ├── employees/             # Employee directory, creation, and edit views
│       ├── errors/                # Custom 403, 404, and 500 error views
│       ├── home/                  # Public enterprise landing page
│       ├── layouts/               # Master view layouts (hr.php, app.php)
│       ├── leaves/                # Leave review queue, balances, and filing forms
│       ├── notifications/         # Notification center inbox view
│       ├── partials/              # Sidebar, navbar, alerts reusable components
│       ├── positions/             # Job positions management views
│       ├── reports/               # Printable workforce & leave reports
│       └── users/                 # User accounts & role management views
├── database/                      # Database scripts & schemas
│   ├── .htaccess                  # SQL dump access protection
│   ├── README.md                  # Database setup instructions
│   ├── schema.sql                 # DDL schema definitions (9 tables, InnoDB, foreign keys)
│   └── seed.sql                   # Synthetic demo seed data with BCRYPT credentials
├── docs/                          # Comprehensive project documentation
│   ├── audits/                    # Repair log, test reports, progress reports
│   ├── deployment/                # Production setup, backup/restore, security guides
│   ├── phase-1-requirements/      # Phase 1 SRS & policies
│   ├── phase-2-system-design/     # Phase 2 architecture & diagrams
│   ├── phase-3-database/          # Phase 3 data dictionaries & ERD
│   ├── phase-4-ui-ux/             # Phase 4 design system & wireframes
│   ├── phase-6-authentication/    # Phase 6 authentication design
│   ├── phase-7-core-hris/         # Phase 7 core HRIS specifications
│   └── rbac/                      # Phase RBAC matrices & security test reports
├── public/                        # Public web server document root
│   ├── assets/                    # Compiled CSS design tokens, JS modules, images
│   ├── .htaccess                  # Front controller mod_rewrite & security rules
│   └── index.php                  # Application entry point
├── scratch/                       # Automated test verification suites
│   ├── test_rbac_suite.php        # 2-Role RBAC & Authorization suite (44 tests)
│   ├── test_deployment_ui.php     # Deployment & Live UI Workflow suite (42 tests)
│   ├── test_phase7_crud.php       # Core HRIS Model & Validation suite (51 tests)
│   └── test_phase7_http.php       # Core HRIS Live HTTP Integration suite (34 tests)
├── .env.example                   # Environment variable template
├── .gitignore                     # Git exclusion rules
├── LICENSE                        # Project license
└── README.md                      # Project documentation master
```

---

## 6. Production Authentication & Real Account Provisioning

The application uses an enterprise-grade **Email Address + Password → Email OTP Verification → Role-Based Dashboard** flow. All demonstration login buttons, hardcoded demo credentials (`hr01`, `emp004`), quick-login shortcuts, and authentication bypasses have been completely removed.

### Authentication Flow

1. **Step 1: Email & Password Submission** (`/login`)
   - Users submit their registered email address and password.
   - Server-side email normalization (`trim` and lowercase), CSRF verification, rate limiting, and password verification (`password_verify`).
   - Account must be `active` and assigned to one of the two supported roles: `hr` or `employee`.
2. **Step 2: Cryptographic OTP Dispatch** (`/login/otp`)
   - A cryptographically secure 6-digit numeric OTP is generated (`random_int(100000, 999999)`).
   - Only a SHA-256 hash of the OTP is persisted in `email_otps`. The plaintext code is never stored in the database.
   - The OTP is dispatched via PHPMailer SMTP to the account's registered email address.
   - Strict security controls: 5-minute expiry, max 5 failed attempts per challenge, 60-second resend cooldown.
3. **Step 3: Role-Based Dashboard Entry**
   - Upon successful verification, the challenge is consumed atomically, the session ID is regenerated (`session_regenerate_id(true)`), and the user is redirected to their dedicated dashboard:
     - **HR Officers (`hr`)** → `/hr/dashboard`
     - **Employees (`employee`)** → `/employee/dashboard`

---

### Provisioning Accounts

No public registration exists. Accounts are provisioned through strictly controlled mechanisms:

#### 1. Provisioning the First HR Account (CLI Bootstrapper)
For fresh installations or provisioning privileged HR users without accessing the web UI:
```bash
# Interactive mode (prompts for email, password, and username)
php bin/create_hr_user.php

# Non-interactive CLI flags
php bin/create_hr_user.php --email="hr.admin@company.com" --password="StrongPassword123!" --username="hr.admin"
```

#### 2. Provisioning Employee and HR Accounts (HR Portal)
Authenticated HR Officers can provision portal accounts for active workforce members:
1. Navigate to **HR Operational Portal → User Accounts (`/hr/users`)**.
2. Click **"+ Provision User Account"** or select **"Provision Portal Account"** on any employee record (`/hr/employees/{id}`).
3. Select the unlinked employee, verify their official email address, assign the role (`hr` or `employee`), and set a secure initial password.

---

### Pre-Seeded Development Accounts (Local Testing)

For local development and testing, accounts are registered to actual employee profile emails:

| Registered Email | Assigned Role | Associated Employee | Dedicated Dashboard | Permitted Scope |
| :--- | :--- | :--- | :--- | :--- |
| `marcus.tan@company.local` | `hr` | Marcus Tan (EMP002) | `/hr/dashboard` | HR Management: Workforce, Departments, Positions, Leaves, User Provisioning (`/hr/users`), Reports, Audit Trail |
| `sofia.reyes@company.local` | `hr` | Sofia Reyes (EMP003) | `/hr/dashboard` | HR Management: Workforce, Departments, Positions, Leaves, User Provisioning (`/hr/users`), Reports, Audit Trail |
| `david.cruz@company.local` | `employee` | David Cruz (EMP004) | `/employee/dashboard` | Employee Self-Service: Personal Profile, Balances, Leave Requests, My Applications |
| `maria.santos@company.local` | `employee` | Maria Santos (EMP005) | `/employee/dashboard` | Employee Self-Service: Personal Profile, Balances, Leave Requests, My Applications |
| `juan.delacruz@company.local` | `employee` | Juan Dela Cruz (EMP006) | `/employee/dashboard` | Employee Self-Service: Personal Profile, Balances, Leave Requests, My Applications |
| `beatrice.gomez@company.local` | `employee` | Beatrice Gomez (EMP007) | `/employee/dashboard` | Employee Self-Service: Personal Profile, Balances, Leave Requests, My Applications |

> [!NOTE]
> During local testing without an active external SMTP relay, enable mock mode (`MAIL_MOCK=true`) in `.env` or inspect OTPs generated during automated test suites. To test real email delivery with Gmail or custom SMTP, run `php bin/test_smtp.php`.


---

## 7. Quick Start & Local Setup Guide

You can run the Employee HRIS & Leave Management System either via **Docker (Recommended)** or via **Local XAMPP**.

### Option A: Docker Containerized Setup with Nginx & Monitoring (Recommended)

The system includes a pre-configured, production-grade 8-container stack featuring:
- **Nginx Reverse Proxy** at the edge ([http://localhost:8080](http://localhost:8080))
- **PHP 8.5.12 + Apache Application Server** (`php:8.5.12-apache-trixie`, shielded internally behind Nginx)
- **MySQL 8.0 Database** with persistent storage volume ([localhost:3307](http://localhost:3307))
- **phpMyAdmin** for browser database management ([http://localhost:8081](http://localhost:8081))
- **Prometheus** time-series metrics collection ([http://localhost:9090](http://localhost:9090))
- **Grafana** performance & health visualization dashboards ([http://localhost:3100](http://localhost:3100))
- **Specialized Monitoring Exporters:** `nginx-exporter` and `mysqld-exporter`
- **Real-time WebSocket service** (`realtime`, Ratchet) reachable only through Nginx at `ws://localhost:8080/ws`. Run `docker compose exec app php bin/migrate.php` once after pulling. See [docs/realtime/README.md](docs/realtime/README.md).

#### 1. Prerequisites
* [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed and running.

#### 2. Start the Stack
```bash
docker compose up -d
```

#### 3. Access the Services
* **HRIS Application:** [http://localhost:8080](http://localhost:8080)
* **phpMyAdmin (DB Management):** [http://localhost:8081](http://localhost:8081)
* **Prometheus Targets & Metrics:** [http://localhost:9090](http://localhost:9090)
* **Grafana Dashboards:** [http://localhost:3100](http://localhost:3100) *(User: `admin` / Pass: `admin`)*
* **MySQL Database Host Port:** `localhost:3307` (avoids conflict with local XAMPP port 3306)

For comprehensive container documentation, commands, architecture, and exporter metrics, see [docs/docker-monitoring/README.md](docs/docker-monitoring/README.md).

---

### Option B: Local XAMPP Setup

#### 1. Prerequisites
* **PHP 8.1+** with `pdo_mysql`, `mbstring`, `session`, `curl` extensions enabled
* **MySQL 8.0+** or **MariaDB 10.4+** (XAMPP recommended)
* Apache Web Server with `mod_rewrite` enabled

#### 2. Database Installation
1. Start Apache and MySQL in your XAMPP Control Panel.
2. Create the target database:
   ```sql
   CREATE DATABASE IF NOT EXISTS `hris_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Import the database schema:
   ```bash
   mysql -u root -p hris_db < database/schema.sql
   ```
4. Seed the initial development accounts and workforce records:
   ```bash
   mysql -u root -p hris_db < database/seed.sql
   ```

#### 3. Environment Configuration
Create your local environment file by copying `.env.example`:
```bash
cp .env.example .env
```
Ensure your database credentials in `config/database.php` or `.env` match your local environment:
```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=hris_db
DB_USER=root
DB_PASS=
```

#### 4. Running the Application
Access the application through your browser:
* **Direct Apache URL:** `http://localhost/GitHub_Clone_File_Projects/HRIS-SYSTEM/public/`
* **Root Alias URL:** `http://localhost/GitHub_Clone_File_Projects/HRIS-SYSTEM/`
* **Browser-Sync Development Proxy:** `http://localhost:3000/GitHub_Clone_File_Projects/HRIS-SYSTEM/`

---

## 8. REST API Documentation & Swagger UI

The HRIS enterprise system features an official **OpenAPI 3.0** REST API layer under `/api/v1` along with **Swagger UI** for interactive exploration and client integration.

### Accessing Swagger UI

| Interface | URL | Description |
| :--- | :--- | :--- |
| **Docker Compose Swagger UI** | `http://localhost:8083` | Dedicated container (`hris_swagger_ui`) mounting `docs/api/openapi.yaml` |
| **Application Embedded Swagger UI** | `http://localhost:8080/docs` | Built-in Swagger UI portal (active in `development` environment) |
| **Alternative App Docs Route** | `http://localhost:8080/api/docs` | Standard `/api/docs` route alias |
| **OpenAPI YAML Specification** | `http://localhost:8080/api/docs/openapi.yaml` | Raw OpenAPI 3.0.0 YAML schema definition |
| **OpenAPI JSON Specification** | `http://localhost:8080/api/docs/openapi.json` | Raw OpenAPI 3.0.0 JSON schema definition |

> **Production Security Note:** Swagger UI and specification inspection endpoints are environment-gated and disabled in `production` unless explicitly authorized.

### Regenerating OpenAPI Specifications

Source documentation annotations are maintained using native **PHP 8 attributes** (`OpenApi\Attributes`) directly within controllers in `app/controllers/Api/`.

To regenerate `docs/api/openapi.yaml` and `docs/api/openapi.json`:

```bash
# Option 1: Using Composer script (generates both YAML and JSON)
composer swagger:generate

# Option 2: Using the CLI generator script directly
php bin/generate_docs.php

# Option 3: Using the zircote/swagger-php binary directly
vendor/bin/openapi app -o docs/api/openapi.yaml
vendor/bin/openapi app -f json -o docs/api/openapi.json
```

### API Endpoint Groups & Tags

The API specification is organized into 9 structured functional tags:

1. **`Authentication`**:
   - `GET /api/v1/auth/csrf`: Retrieve active session CSRF token for testing and client headers.
   - `POST /api/v1/auth/login`: Stage 1 password challenge; dispatches 6-digit email OTP.
   - `POST /api/v1/auth/verify-otp`: Stage 2 OTP verification; establishes session cookie (`PHPSESSID`).
   - `POST /api/v1/auth/resend-otp`: Resend fresh OTP (subject to 60-second rate-limiting cooldown).
   - `POST /api/v1/auth/logout`: Invalidate and destroy active authenticated session.
2. **`Account and Profile`**:
   - `GET /api/v1/profile`: Retrieve authenticated user identity, assigned role, and linked employee record.
   - `GET /api/v1/notifications`: List personal notifications and unread alert counts.
   - `POST /api/v1/notifications/{id}/read`: Mark notification as read.
   - `POST /api/v1/notifications/mark-all-read`: Mark all notifications as read.
3. **`Employee Management`** *(HR Role Only)*:
   - `GET /api/v1/employees`: Search and list workforce directory with department and status filters.
   - `GET /api/v1/employees/{id}`: Detailed individual employee record inspection.
   - `POST /api/v1/employees`: Register new workforce member (enforces unique employee number and email).
   - `PUT /api/v1/employees/{id}`: Update employee demographic and departmental alignment.
   - `POST /api/v1/employees/{id}/deactivate`: Soft-deactivate employee while preserving historical audit trails.
4. **`Department Management`**:
   - `GET /api/v1/departments`: List organizational departments with position counts and headcount.
   - `GET /api/v1/departments/{id}`: Department profile details.
   - `POST /api/v1/departments`: Create new department *(HR only)*.
   - `PUT /api/v1/departments/{id}`: Update department *(HR only)*.
5. **`Position Management`**:
   - `GET /api/v1/positions`: List job positions with optional department filter.
   - `GET /api/v1/positions/{id}`: Job position classification details.
   - `POST /api/v1/positions`: Create position classification *(HR only)*.
   - `PUT /api/v1/positions/{id}`: Update position classification *(HR only)*.
   - `GET /hr/api/positions-by-department/{id}`: Operational dynamic dropdown feed.
6. **`Leave Types and Balances`**:
   - `GET /api/v1/leave-types`: Active leave policy classifications for employee submission.
   - `GET /api/v1/hr/leave-types`: All leave classifications with usage statistics *(HR only)*.
   - `POST /api/v1/hr/leave-types`: Create leave policy classification *(HR only)*.
   - `GET /api/v1/leave-balances/my`: Personal leave balances for current calendar year.
   - `GET /api/v1/hr/leave-balances`: Organization-wide leave balances *(HR only)*.
   - `POST /api/v1/hr/leave-balances/adjust`: Adjust employee allocated leave days *(HR only)*.
7. **`Leave Requests`**:
   - `GET /api/v1/leaves/my`: Personal leave submission history (enforces strict employee ownership).
   - `POST /api/v1/leaves`: Submit new leave application (calculates Mon–Fri working days, validates quota and overlap).
   - `GET /api/v1/leaves/{id}`: View leave request (restricted to submitting employee or HR).
   - `POST /api/v1/leaves/{id}/cancel`: Cancel pending leave request (owner employee only).
8. **`HR Approvals`** *(HR Role Only)*:
   - `GET /api/v1/hr/leaves`: Review queue with status, employee, department, and date range filters.
   - `POST /api/v1/hr/leaves/{id}/approve`: Transaction-safe approval with balance quota deduction and collision defense.
   - `POST /api/v1/hr/leaves/{id}/reject`: Rejection with administrative reason recording.
9. **`Health and Diagnostics`**:
   - `GET /api/v1/health`: Clean operational health check without sensitive environment or database credentials.
   - `GET /health`: Operational telemetry endpoint.

### Testing Endpoints via Swagger UI or cURL

1. **Obtain CSRF Token:**
   ```bash
   curl -s -c cookies.txt http://localhost:8080/api/v1/auth/csrf
   ```
2. **Initiate Login (Stage 1):**
   ```bash
   curl -s -b cookies.txt -c cookies.txt -X POST http://localhost:8080/api/v1/auth/login \
     -H "Content-Type: application/json" \
     -d '{"email":"your-email@company.local","password":"YourPassword"}'
   ```
3. **Verify OTP (Stage 2):**
   ```bash
   curl -s -b cookies.txt -c cookies.txt -X POST http://localhost:8080/api/v1/auth/verify-otp \
     -H "Content-Type: application/json" \
     -d '{"otp":"123456"}'
   ```
4. **Access Protected Endpoints:**
   ```bash
   curl -s -b cookies.txt http://localhost:8080/api/v1/profile
   ```
   For state-changing operations (`POST`, `PUT`, `DELETE`), pass the CSRF token in the `X-CSRF-Token` header.

---

## 9. Automated Test Execution & Quality Assurance

The system includes 4 automated test suites validating authentication, authorization, CRUD operations, HTTP routes, ownership enforcement, and deployment workflows:

```bash
# 1. Run Complete 2-Role RBAC & Authorization Suite (44 tests)
php scratch/test_rbac_suite.php

# 2. Run Deployment Readiness & Live UI Workflow Suite (42 tests)
php scratch/test_deployment_ui.php

# 3. Run Core HRIS Model & Validation Test Suite (51 tests)
php scratch/test_phase7_crud.php

# 4. Run Core HRIS Live HTTP Integration Suite (34 tests)
php scratch/test_phase7_http.php
```

### Cumulative Test Results Summary

```text
========================================================================================
Suite 1: test_phase7_crud.php      -> 51 Passed, 0 Failed (100% Pass Rate)
Suite 2: test_phase7_http.php      -> 34 Passed, 0 Failed (100% Pass Rate)
Suite 3: test_deployment_ui.php    -> 42 Passed, 0 Failed (100% Pass Rate)
Suite 4: test_rbac_suite.php       -> 44 Passed, 0 Failed (100% Pass Rate)
TOTAL AUTOMATED ASSERTIONS         -> 171 Passed, 0 Failed (100% Pass Rate)
========================================================================================
```

---

## 9. Security & Governance Policies

* **Two-Factor Email OTP Authentication:** Login requires the user's registered email address and password, followed by a time-limited 6-digit numeric OTP dispatched via PHPMailer SMTP with row-locked atomic consumption, 5-minute expiry, and 60-second resend cooldown.
* **Account Provisioning & Onboarding:** Controlled onboarding via authenticated HR management (`/hr/users/create`) and restricted initial setup CLI tool (`bin/create_hr_user.php`). All demo accounts, shortcuts, and bypasses are removed.
* **Authentication:** Parameterized PDO queries against SQL injection, native BCRYPT password hashing (`password_hash`, `password_verify`).
* **Session Hardening:** Strict session regeneration on privilege escalation (`session_regenerate_id(true)`), `HttpOnly` and `SameSite=Lax` cookies, 30-minute inactivity timeout.
* **Access Control:** Centralized `Core\Permission` policy and server-side `RoleMiddleware` enforcing strict boundary isolation between HR and Employee dashboards.
* **Ownership Verification (NFR-SEC-004):** Employee profile, balance, and request inspection queries verify session identity to prevent IDOR and parameter tampering attacks.
* **CSRF Mitigation:** Timing-safe `hash_equals()` validation on all state-changing requests (`POST`).
* **Lockout Protection:** Prevention of self-demotion or self-deactivation for active HR management accounts, with guaranteed protection of the last remaining active HR Officer.
* **Audit Logging:** Immutable audit records inserted into `activity_logs` for all authentication events, user updates, and workforce mutations.
