<div align="center">

<img src="../public/assets/img/nexora-logo-icon.png" alt="NEXORA HR Logo" width="110" />

# NEXORA HR Documentation Hub

**People. Processes. Progress.**

An enterprise-grade Human Resource Information System (HRIS) and Leave Management Platform engineered with native PHP 8.5, MySQL 8.0 (InnoDB), Ratchet WebSockets (:8090), and Docker Compose.

</div>

---

## Directory Structure Overview

```text
docs/
├── README.md                                   # Unified Documentation Portal
├── 01-core/                                    # Pillar 1: Product, Architecture, Governance & Module Specs
│   ├── PRD.md
│   ├── ARCHITECTURE.md
│   ├── DESIGN.md
│   ├── RULES.md
│   ├── TASK.md
│   ├── MEMORY.md
│   ├── USER-MANUAL.md
│   └── modules/
│       ├── announcements-management.md
│       ├── employee-help-and-support.md
│       ├── hr-admin-help-and-support.md
│       ├── public-help-and-password-recovery.md
│       └── smart-otp-trusted-devices.md
├── 02-technical/                               # Pillar 2: Technical Specifications, Diagrams & Security
│   ├── api-reference.md
│   ├── architectural-decisions.md
│   ├── dsa-documentation.md
│   ├── skeleton-screen-system.md
│   ├── system-architecture.md
│   ├── system-workflows.md
│   ├── uml-diagrams.md
│   ├── database/
│   │   ├── database-schema.md
│   │   └── erd.mmd
│   └── security/
│       ├── security-architecture.md
│       └── authentication-and-mfa-security.md
├── 03-operations/                              # Pillar 3: Deployment, DevOps, Observability & Runbooks
│   ├── troubleshooting.md
│   ├── deployment/
│   │   ├── deployment-guide.md
│   │   ├── installation-and-configuration-guide.md
│   │   └── production-readiness-checklist.md
│   ├── devops/
│   │   ├── backup-and-recovery.md
│   │   ├── ci-linting-automation.md
│   │   ├── cron-scheduler-and-queue-worker.md
│   │   ├── docker-nginx-architecture.md
│   │   ├── leave-accrual-operations.md
│   │   ├── local-development-setup.md
│   │   ├── modular-terraform-architecture.md
│   │   ├── terraform-ci.md
│   │   └── vps-provisioning.md
│   └── monitoring/
│       ├── README.md
│       ├── grafana-dashboards.md
│       ├── monitoring-architecture.md
│       ├── monitoring-troubleshooting.md
│       └── prometheus-setup.md
├── 04-quality-assurance/                       # Pillar 4: Testing Strategy, QA Results, Audits & Benchmarks
│   ├── functional-audit-report.md
│   ├── performance-test-plan-and-results.md
│   ├── test-plan-and-results.md
│   └── testing-strategy.md
├── 05-reference/                               # Pillar 5: Domain Glossary & Dynamic RBAC Reference
│   ├── glossary.md
│   └── rbac/
│       ├── authorization-architecture.md
│       ├── protected-routes.md
│       └── role-permission-matrix.md
├── 06-defense/                                 # Pillar 6: Project Defense & Presentation Package
│   ├── anticipated-questions.md
│   ├── demonstration-script.md
│   ├── presentation-outline.md
│   └── project-defense-guide.md
├── api/                                        # Runtime OpenAPI 3.0 Specs (Swagger UI & Docker)
│   ├── openapi.yaml
│   └── openapi.json
└── screenshots/                                # Verified UI Captures & Empirical DSA Benchmark Charts
    ├── README.md
    ├── admin/admin-portal-login.png
    ├── dsa/
    │   ├── chart_search_execution_time.svg
    │   ├── chart_sorting_arrangements.svg
    │   └── chart_sorting_execution_time.svg
    ├── employee/employee-portal-login.png
    └── hr/hr-portal-login.png
```

---

## 1. Core Product, Governance & Module Specifications (`01-core/`)

* **[Product Requirements (`PRD.md`)](01-core/PRD.md)** — Executive product vision, functional scope (`FR-001`–`FR-016`), leave business rules, NFRs, and Requirements Traceability Matrix (RTM).
* **[Three-Role User Manual (`USER-MANUAL.md`)](01-core/USER-MANUAL.md)** — Step-by-step user manual for Administrators (`admin`), HR Officers (`hr`), and Employees (`employee`).
* **[System Architecture (`ARCHITECTURE.md`)](01-core/ARCHITECTURE.md)** — Custom 4-tier MVC & DDD architecture, database locking (`SELECT ... FOR UPDATE`), Transactional Outbox, Cron Scheduler, and Queue Worker.
* **[Engineering & Security Rules (`RULES.md`)](01-core/RULES.md)** — Mandatory 3-role model (`admin`, `hr`, `employee`) under ADR-012, least-privilege governance, PSR-12 standards, and operational safety guardrails.
* **[UI/UX Design System (`DESIGN.md`)](01-core/DESIGN.md)** — Design tokens, Cobalt Blue / Slate palette, typography, spatial grid, responsive App Shell, and WCAG 2.2 AA accessibility guidelines.
* **[Development Roadmap (`TASK.md`)](01-core/TASK.md)** — Comprehensive phase breakdown, verified test metrics (`764` automated tests, `0` new PSR-12 violations), and release tracking.
* **[AI Agent Memory (`MEMORY.md`)](01-core/MEMORY.md)** — High-density context primer, key file paths, core business rules, and essential CLI commands.

### Feature & Module Specifications (`01-core/modules/`)
* **[HR Announcements Management (`announcements-management.md`)](01-core/modules/announcements-management.md)** — DDD bounded context, lifecycle state machine (`Draft`, `Scheduled`, `Published`, `Expired`, `Archived`), audience targeting, and read/acknowledgment receipts.
* **[Employee Help & Support & Complaints (`employee-help-and-support.md`)](01-core/modules/employee-help-and-support.md)** — Employee-to-HR general support (`SUP-YYYY-NNNNN`), permission-bounded confidential workplace complaints, and COI exclusions.
* **[Admin Least-Privilege & HR-to-Admin Support (`hr-admin-help-and-support.md`)](01-core/modules/hr-admin-help-and-support.md)** — Least-privilege 3-role separation, HR-to-Admin technical support (`HR-SUP-YYYY-XXXXXXXX`), Admin Support Management Inbox, and private Admin internal notes.
* **[Smart OTP & Trusted Devices (`smart-otp-trusted-devices.md`)](01-core/modules/smart-otp-trusted-devices.md)** — Token/cookie cryptography, single-use rotation, risk-based step-up rules, and self-service `/account/security` design.
* **[Public Help, Password Recovery & Login Assistance (`public-help-and-password-recovery.md`)](01-core/modules/public-help-and-password-recovery.md)** — Pre-login Help Hub, anti-enumeration Forgot/Reset Password lifecycle, and tokenized `AUTH-YYYY-NNNNN` login support intake & redacted tracker.

---

## 2. Technical Specifications, Diagrams & Security (`02-technical/`)

* **[System Architecture & Components (`system-architecture.md`)](02-technical/system-architecture.md)** — Verified container architecture, Clean Architecture domain layer, and request/data flows.
* **[Skeleton Screen System (`skeleton-screen-system.md`)](02-technical/skeleton-screen-system.md)** — Accessible SSR + CSR shimmer placeholder architecture (`SkeletonRenderer` and `skeleton.js`).
* **[Database Schema (`database/database-schema.md`)](02-technical/database/database-schema.md)** & **[Editable Mermaid ERD (`database/erd.mmd`)](02-technical/database/erd.mmd)** — 49-table InnoDB schema catalog, foreign key constraints, composite indexes, and migration history.
* **[UML Diagrams Catalog (`uml-diagrams.md`)](02-technical/uml-diagrams.md)** — System Context, Use Case, Class, Sequence, Activity, State Machine, Component, and Deployment diagrams.
* **[System Workflows & Flowcharts (`system-workflows.md`)](02-technical/system-workflows.md)** — End-to-end flowcharts for Authentication/MFA, Admin Governance, HR Operations, Employee Self-Service, Leave Processing, Announcements, Outbox/Queue, and Tamper-Evident Audit Trail.
* **[DSA Documentation & Flowcharts (`dsa-documentation.md`)](02-technical/dsa-documentation.md)** — Custom Data Structures & Algorithms specifications, pseudocode, complexity analysis, and empirical benchmarks.
* **[Security Architecture (`security/security-architecture.md`)](02-technical/security/security-architecture.md)** & **[Authentication & MFA Security (`security/authentication-and-mfa-security.md`)](02-technical/security/authentication-and-mfa-security.md)** — Authentication lifecycle, Email OTP & TOTP MFA, Smart OTP trusted devices, CSRF, and sensitive HR data protection.
* **[REST API v1 Reference (`api-reference.md`)](02-technical/api-reference.md)** — REST API v1 endpoints catalog and machine-readable OpenAPI 3.0 specifications ([`api/openapi.yaml`](api/openapi.yaml), [`api/openapi.json`](api/openapi.json)).
* **[Architectural Decision Records (`architectural-decisions.md`)](02-technical/architectural-decisions.md)** — Approved technical and business-rule decisions (`ADR-001` through `ADR-013`).

---

## 3. Operations, Deployment, DevOps & Observability (`03-operations/`)

* **[Installation & Configuration Guide (`deployment/installation-and-configuration-guide.md`)](03-operations/deployment/installation-and-configuration-guide.md)** — Docker Compose and native XAMPP/Linux installation, migrations, and CLI Admin provisioning (`bin/create_admin_user.php`).
* **[Deployment Runbook (`deployment/deployment-guide.md`)](03-operations/deployment/deployment-guide.md)** — Multi-container Docker Compose and Ubuntu 24.04 VPS production deployment instructions.
* **[Production Readiness Checklist (`deployment/production-readiness-checklist.md`)](03-operations/deployment/production-readiness-checklist.md)** — 18-point production readiness and security verification matrix.
* **[DevOps Runbooks (`devops/`)](03-operations/devops/)** — [Cron Scheduler & Queue Worker](03-operations/devops/cron-scheduler-and-queue-worker.md), [Docker & Nginx Architecture](03-operations/devops/docker-nginx-architecture.md), [CI Linting Automation](03-operations/devops/ci-linting-automation.md), [Modular Terraform Architecture](03-operations/devops/modular-terraform-architecture.md), [Terraform CI](03-operations/devops/terraform-ci.md), [VPS Provisioning](03-operations/devops/vps-provisioning.md), [Leave Accrual Operations](03-operations/devops/leave-accrual-operations.md), [Local Development Setup](03-operations/devops/local-development-setup.md), and [Backup & Recovery](03-operations/devops/backup-and-recovery.md).
* **[Observability & Monitoring Stack (`monitoring/README.md`)](03-operations/monitoring/README.md)** — [Monitoring Architecture](03-operations/monitoring/monitoring-architecture.md), [Prometheus Setup](03-operations/monitoring/prometheus-setup.md), [Grafana Dashboards](03-operations/monitoring/grafana-dashboards.md), and [Monitoring Troubleshooting](03-operations/monitoring/monitoring-troubleshooting.md).
* **[Troubleshooting Runbook (`troubleshooting.md`)](03-operations/troubleshooting.md)** — Diagnostics for containers, MySQL connections, OTP limits, session expirations, and locks.

---

## 4. Quality Assurance, Testing Strategy, Audits & Benchmarks (`04-quality-assurance/`)

* **[Testing Strategy & Quality Gates (`testing-strategy.md`)](04-quality-assurance/testing-strategy.md)** — PHPUnit (`764` tests), PHPStan, PHPCS baseline check, Selenium E2E suite, and `hris_test` database isolation.
* **[QA Test Plan & Verified Results (`test-plan-and-results.md`)](04-quality-assurance/test-plan-and-results.md)** — Test coverage and verification results across PHPUnit, PHPStan, and PSR-12 quality gates.
* **[Performance Test Plan & Results (`performance-test-plan-and-results.md`)](04-quality-assurance/performance-test-plan-and-results.md)** — Reproducible latency percentiles (`avg`, `p50`, `p95`, `ops/sec`) and InnoDB index verification.
* **[Full-System Functional Audit Report (`functional-audit-report.md`)](04-quality-assurance/functional-audit-report.md)** — Complete functional, security, database, and regression audit report.

---

## 5. Domain Glossary & RBAC Reference (`05-reference/`)

* **[Domain Glossary (`glossary.md`)](05-reference/glossary.md)** — Standardized HRIS, statutory leave, and technical terminology.
* **[RBAC Permission Matrix (`rbac/role-permission-matrix.md`)](05-reference/rbac/role-permission-matrix.md)** — Dynamic 44-permission registry across `admin`, `hr`, and `employee` roles.
* **[Protected Routes Matrix (`rbac/protected-routes.md`)](05-reference/rbac/protected-routes.md)** — HTTP route registry and middleware enforcement specifications.
* **[Authorization Architecture (`rbac/authorization-architecture.md`)](05-reference/rbac/authorization-architecture.md)** — Server-side `AuthorizationService`, `PermissionMiddleware`, and `permission_audit_logs` design.

---

## 6. Project Defense, Presentation & Visual Evidence (`06-defense/` & `screenshots/`)

* **[Project Defense Guide (`project-defense-guide.md`)](06-defense/project-defense-guide.md)** — Background, problem statement, objectives, scope, and architectural deep-dives for the defense panel.
* **[Anticipated Panel Questions & Answers (`anticipated-questions.md`)](06-defense/anticipated-questions.md)** — Comprehensive Q&A bank grounded in verified source code.
* **[Live Demonstration Script (`demonstration-script.md`)](06-defense/demonstration-script.md)** — 15-step live defense walkthrough with roles, routes, expected results, and backup plans.
* **[Final Presentation Outline & Speaker Notes (`presentation-outline.md`)](06-defense/presentation-outline.md)** — 22-slide master presentation deck with speaker notes.
* **[Screenshots & Visual Evidence Catalog (`screenshots/README.md`)](screenshots/README.md)** — Verified UI captures and empirical DSA benchmark SVG graphs.
