# Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL%20%2F%20MariaDB-orange.svg)](https://www.mysql.com/)
[![Architecture](https://img.shields.io/badge/Architecture-Custom%20MVC-green.svg)]()
[![Status](https://img.shields.io/badge/Status-Phases%201%20%26%202%20Complete-success.svg)]()

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
    [ 4. DATABASE LAYER: MySQL / MariaDB (InnoDB Engine, ACID Row Locks) ]
```

---

## 3. Specifications & Architecture Documentation Index

All baseline systems analysis, software requirements, and architectural design specifications for **Phase 1** and **Phase 2** have been compiled into the `docs/` repository directory:

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

### Cross-Phase Tracking
* **[Decision & Assumptions Log](docs/decision-log.md):** Unresolved items, proposed defaults, and confirmation statuses.
* **[Requirements Traceability Matrix (RTM)](docs/requirements-traceability.md):** Bidirectional traceability mapping requirements to modules, components, diagrams, and future test cases.

---

## 4. Authoritative Project Directory Tree

```
Employee-HRIS-Leave-Management-System/
├── .github/                       # Workflows and issue templates
├── config/                        # Application & database settings
│   ├── config.php
│   └── database.php
├── core/                          # Framework kernel
│   ├── Controller.php
│   ├── Database.php               # PDO database factory
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
├── docs/                          # Architectural and requirements documentation
│   ├── phase-1-requirements/      # Phase 1 specifications
│   └── phase-2-system-design/     # Phase 2 specifications & Mermaid diagrams
├── public/                        # Public document root
│   ├── assets/                    # CSS, JS, images
│   ├── .htaccess
│   └── index.php                  # Application entry point
├── .gitignore
├── LICENSE
└── README.md
```

---

## 5. Next Steps

With **Phase 1** and **Phase 2** fully documented and baselined:
* **Next Phase:** **Phase 3 — Database Design** (Creating `database.sql`, defining schemas, primary/foreign keys, indexes, and ERD).
