# Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL%20%2F%20MariaDB-orange.svg)](https://www.mysql.com/)
[![Architecture](https://img.shields.io/badge/Architecture-Custom%20MVC-green.svg)]()
[![Status](https://img.shields.io/badge/Status-Phases%201%2C%202%2C%203%20%26%204%20Complete-success.svg)]()

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

## 5. Authoritative Project Directory Tree

```
Employee-HRIS-Leave-Management-System/
├── .github/                       # Workflows and issue templates
├── config/                        # Application & database settings
│   ├── config.php                 # App name, timezone, session lifetime
│   └── database.php               # PDO database connection settings
├── core/                          # Framework kernel
│   ├── Controller.php
│   ├── Database.php               # Centralized PDO database factory
│   ├── Middleware.php             # Security & role guards
│   ├── Model.php                  # Base model & transactions
│   ├── Router.php                 # Front controller router
│   └── functions.php              # Global helper functions
├── app/
│   ├── controllers/               # MVC Controllers
│   ├── models/                    # Domain entity models
│   ├── dsa/                       # Custom In-Memory DSA Engine
│   │   ├── algorithms/            # Linear/Binary Search, Bubble/Merge/QuickSort
│   │   └── structures/            # ArrayWrapper, Stack, Queue, LinkedList, HashTable, BST
│   └── views/                     # Layouts, partials, and view templates
├── database/                      # Database scripts
│   ├── README.md                  # Database setup instructions
│   ├── schema.sql                 # DDL schema definitions
│   ├── seed.sql                   # Synthetic demo seed data
│   └── test_data.sql              # Constraint verification tests
├── docs/                          # Architectural documentation
│   ├── decision-log.md            # Decisions & assumptions log
│   ├── requirements-traceability.md # Requirements Traceability Matrix
│   ├── phase-1-requirements/      # Phase 1 specifications
│   ├── phase-2-system-design/     # Phase 2 specifications & diagrams
│   ├── phase-3-database/          # Phase 3 database specifications & ERD
│   └── phase-4-ui-ux/             # Phase 4 design system & wireframes
├── public/                        # Public document root
│   ├── assets/                    # CSS, JS, images
│   ├── .htaccess
│   └── index.php                  # Application entry point
├── .gitignore
├── LICENSE
└── README.md
```

---

## 6. Next Steps

With **Phase 1**, **Phase 2**, **Phase 3**, and **Phase 4** verified and complete:
* **Next Phase:** **Phase 5 — Authentication and Security Engine** (Implementing `core/Database.php`, `core/Router.php`, `core/Middleware.php`, and `AuthController.php`).
