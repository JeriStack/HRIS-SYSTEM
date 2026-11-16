# NEXORA HR Documentation Hub

**People. Processes. Progress.**

An enterprise-grade Human Resource Information System (HRIS) and Leave Management Platform engineered with native PHP 8.5, MySQL 8.0 (InnoDB), Ratchet WebSockets (:8090), and Docker Compose.

---

## Directory Structure Overview

```text
docs/
├── README.md                                   # Unified Documentation Index
├── 01-core/                                    # Pillar 1: Product, Architecture, Governance & Module Specs
│   ├── PRD.md
│   ├── ARCHITECTURE.md
│   ├── DESIGN.md
│   ├── RULES.md
│   ├── TASK.md
│   ├── MEMORY.md
│   ├── user-manual.md
│   └── modules/
│       ├── ANNOUNCEMENTS-MODULE-SPEC.md
│       ├── HELP-AND-SUPPORT-MODULE-SPEC.md
│       ├── HR-ADMIN-HELP-SUPPORT-SPEC.md
│       ├── PUBLIC-HELP-AND-PASSWORD-RECOVERY-SPEC.md
│       └── SMART-OTP-TRUSTED-DEVICES-SPEC.md
├── 02-technical/                               # Pillar 2: Technical Specifications, Diagrams & Security
│   ├── api/API.md
│   ├── architecture/
│   │   ├── system-architecture.md
│   │   └── skeleton-screen-system.md
│   ├── database/
│   │   ├── DATABASE.md
│   │   └── erd.mmd
│   ├── decisions/DECISIONS.md
│   ├── dsa/dsa-documentation.md
│   ├── security/
│   │   ├── SECURITY.md
│   │   └── authentication-and-mfa-security.md
│   ├── testing/TESTING.md
│   ├── uml/uml-diagrams.md
│   └── workflows/system-workflows.md
├── 03-operations/                              # Pillar 3: Deployment, DevOps, Observability & Audits
│   ├── audits/FULL_SYSTEM_FUNCTIONAL_AUDIT.md
│   ├── deployment/
│   │   ├── DEPLOYMENT.md
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
│   ├── monitoring/
│   │   ├── README.md
│   │   ├── monitoring-architecture.md
│   │   ├── prometheus-setup.md
│   │   ├── grafana-dashboards.md
│   │   └── monitoring-troubleshooting.md
│   └── troubleshooting/TROUBLESHOOTING.md
├── 04-quality-assurance/                       # Pillar 4: QA Test Plans & Empirical Benchmarks
│   ├── test-plan-and-results.md
│   └── performance-test-plan-and-results.md
├── 05-reference/                               # Pillar 5: Glossary & RBAC Reference Matrices
│   ├── glossary/GLOSSARY.md
│   └── rbac/
│       ├── authorization-architecture.md
│       ├── protected-routes.md
│       └── role-permission-matrix.md
├── 06-defense/                                 # Pillar 6: Project Defense & Presentation Package
│   ├── project-defense-guide.md
│   ├── anticipated-questions.md
│   ├── demonstration-script.md
│   └── presentation-outline.md
├── api/                                        # Machine-Readable OpenAPI 3.0 Specs (Swagger UI & Docker)
│   ├── openapi.yaml
│   └── openapi.json
└── screenshots/                                # Visual Evidence & Empirical DSA Benchmark Charts
    └── README.md
```

---

## 1. Core Product, Governance & Module Specifications (`01-core/`)

* **[Product Requirements (`PRD.md`)](01-core/PRD.md)** — Executive product vision, functional scope (`FR-001`–`FR-016`), leave business rules, NFRs, and Requirements Traceability Matrix (RTM).
* **[Three-Role User Manual (`user-manual.md`)](01-core/user-manual.md)** — Step-by-step user manual for Administrators (`admin`), HR Officers (`hr`), and Employees (`employee`).
* **[System Architecture (`ARCHITECTURE.md`)](01-core/ARCHITECTURE.md)** — Custom 4-tier MVC & DDD architecture, database locking (`SELECT ... FOR UPDATE`), Transactional Outbox, Cron Scheduler, and Queue Worker.
* **[Engineering & Security Rules (`RULES.md`)](01-core/RULES.md)** — Mandatory 3-role model (`admin`, `hr`, `employee`) under ADR-012, least-privilege governance, PSR-12 standards, and operational safety guardrails.
* **[UI/UX Design System (`DESIGN.md`)](01-core/DESIGN.md)** — Design tokens, Cobalt Blue / Slate palette, typography, spatial grid, responsive App Shell, and WCAG 2.1 AA accessibility guidelines.
* **[Development Roadmap (`TASK.md`)](01-core/TASK.md)** — Comprehensive phase breakdown, verified test metrics (`764` automated tests, `0` new PSR-12 violations), and release tracking.
* **[AI Agent Memory (`MEMORY.md`)](01-core/MEMORY.md)** — High-density context primer, key file paths, core business rules, and essential CLI commands.

### Feature & Module Specifications (`01-core/modules/`)
* **[HR Announcements Management Spec](01-core/modules/ANNOUNCEMENTS-MODULE-SPEC.md)** — DDD bounded context, lifecycle state machine (`Draft`, `Scheduled`, `Published`, `Expired`, `Archived`), audience targeting, and read/acknowledgment receipts.
* **[Employee Help & Support & Complaint Spec](01-core/modules/HELP-AND-SUPPORT-MODULE-SPEC.md)** — Employee-to-HR general support (`SUP-YYYY-NNNNN`), permission-bounded confidential workplace complaints, and COI exclusions.
* **[Admin Least-Privilege & HR-to-Admin Help & Support Spec](01-core/modules/HR-ADMIN-HELP-SUPPORT-SPEC.md)** — Least-privilege 3-role separation, HR-to-Admin technical support (`HR-SUP-YYYY-XXXXXXXX`), Admin Support Management Inbox, and private Admin internal notes.
* **[Smart OTP & Trusted Devices Spec](01-core/modules/SMART-OTP-TRUSTED-DEVICES-SPEC.md)** — Token/cookie cryptography, single-use rotation, risk-based step-up rules, and self-service `/account/security` design.
* **[Public Help, Password Recovery & Login Assistance Spec](01-core/modules/PUBLIC-HELP-AND-PASSWORD-RECOVERY-SPEC.md)** — Pre-login Help Hub, anti-enumeration Forgot/Reset Password lifecycle, and tokenized `AUTH-YYYY-NNNNN` login support intake & redacted tracker.

---

## 2. Technical References, UML, Workflows & DSA (`02-technical/`)

* **[System Architecture & Components](02-technical/architecture/system-architecture.md)** — Verified container architecture, Clean Architecture domain layer, and request/data flows.
* **[Skeleton Screen System](02-technical/architecture/skeleton-screen-system.md)** — Accessible SSR + CSR shimmer placeholder architecture (`SkeletonRenderer` and `skeleton.js`).
* **[Database Documentation](02-technical/database/DATABASE.md)** & **[Editable Mermaid ERD](02-technical/database/erd.mmd)** — 49-table InnoDB schema catalog, foreign key constraints, composite indexes, and migration history.
* **[UML Diagrams Catalog](02-technical/uml/uml-diagrams.md)** — System Context, Use Case, Class, Sequence, Activity, State Machine, Component, and Deployment diagrams.
* **[System Workflows & Flowcharts](02-technical/workflows/system-workflows.md)** — End-to-end flowcharts for Authentication/MFA, Admin Governance, HR Operations, Employee Self-Service, Leave Processing, Announcements, Outbox/Queue, and Tamper-Evident Audit Trail.
* **[DSA Documentation & Flowcharts](02-technical/dsa/dsa-documentation.md)** — Custom Data Structures & Algorithms specifications, pseudocode, complexity analysis, and empirical benchmarks.
* **[Security Architecture](02-technical/security/SECURITY.md)** & **[Authentication & MFA Security](02-technical/security/authentication-and-mfa-security.md)** — Authentication lifecycle, Email OTP & TOTP MFA, Smart OTP trusted devices, CSRF, and sensitive HR data protection.
* **[Testing Strategy](02-technical/testing/TESTING.md)** — PHPUnit (`764` tests), PHPStan, PHPCS baseline check, Selenium E2E suite, and `hris_test` database isolation.
* **[REST API v1 Reference](02-technical/api/API.md)** — REST API v1 endpoints catalog and machine-readable OpenAPI 3.0 specifications ([`api/openapi.yaml`](api/openapi.yaml), [`api/openapi.json`](api/openapi.json)).
* **[Architectural Decision Records](02-technical/decisions/DECISIONS.md)** — Approved technical and business-rule decisions (`ADR-001` through `ADR-013`).

---

## 3. Operations, Deployment, DevOps & Observability (`03-operations/`)

* **[Installation & Configuration Guide](03-operations/deployment/installation-and-configuration-guide.md)** — Docker Compose and native XAMPP/Linux installation, migrations, and CLI Admin provisioning (`bin/create_admin_user.php`).
* **[Deployment Runbook](03-operations/deployment/DEPLOYMENT.md)** — Multi-container Docker Compose and Ubuntu 24.04 VPS production deployment instructions.
* **[Production Readiness Checklist](03-operations/deployment/production-readiness-checklist.md)** — 18-point production readiness and security verification matrix.
* **[Full-System Functional Audit Report](03-operations/audits/FULL_SYSTEM_FUNCTIONAL_AUDIT.md)** — Complete functional, security, database, and regression audit report.
* **[DevOps Runbooks](03-operations/devops/)** — [Cron Scheduler & Queue Worker](03-operations/devops/cron-scheduler-and-queue-worker.md), [Docker & Nginx Architecture](03-operations/devops/docker-nginx-architecture.md), [CI Linting Automation](03-operations/devops/ci-linting-automation.md), [Modular Terraform Architecture](03-operations/devops/modular-terraform-architecture.md), [Terraform CI](03-operations/devops/terraform-ci.md), [VPS Provisioning](03-operations/devops/vps-provisioning.md), [Leave Accrual Operations](03-operations/devops/leave-accrual-operations.md), [Local Development Setup](03-operations/devops/local-development-setup.md), and [Backup & Recovery](03-operations/devops/backup-and-recovery.md).
* **[Observability & Monitoring Stack](03-operations/monitoring/README.md)** — [Monitoring Architecture](03-operations/monitoring/monitoring-architecture.md), [Prometheus Setup](03-operations/monitoring/prometheus-setup.md), [Grafana Dashboards](03-operations/monitoring/grafana-dashboards.md), and [Monitoring Troubleshooting](03-operations/monitoring/monitoring-troubleshooting.md).
* **[Troubleshooting Runbook](03-operations/troubleshooting/TROUBLESHOOTING.md)** — Diagnostics for containers, MySQL connections, OTP limits, session expirations, and locks.

---

## 4. Quality Assurance & Performance Testing (`04-quality-assurance/`)

* **[QA Test Plan & Verified Results](04-quality-assurance/test-plan-and-results.md)** — Test coverage and verification results across PHPUnit, PHPStan, and PSR-12 quality gates.
* **[Performance Test Plan & Results](04-quality-assurance/performance-test-plan-and-results.md)** — Reproducible latency percentiles (`avg`, `p50`, `p95`, `ops/sec`) and InnoDB index verification.

---

## 5. Domain Glossary & RBAC Reference (`05-reference/`)

* **[Domain Glossary](05-reference/glossary/GLOSSARY.md)** — Standardized HRIS, statutory leave, and technical terminology.
* **[RBAC Permission Matrix](05-reference/rbac/role-permission-matrix.md)** — Dynamic 44-permission registry across `admin`, `hr`, and `employee` roles.
* **[Protected Routes Matrix](05-reference/rbac/protected-routes.md)** — HTTP route registry and middleware enforcement specifications.
* **[Authorization Architecture](05-reference/rbac/authorization-architecture.md)** — Server-side `AuthorizationService`, `PermissionMiddleware`, and `permission_audit_logs` design.

---

## 6. Project Defense, Presentation & Visual Evidence (`06-defense/` & `screenshots/`)

* **[Project Defense Guide](06-defense/project-defense-guide.md)** — Background, problem statement, objectives, scope, and architectural deep-dives for the defense panel.
* **[Anticipated Panel Questions & Answers](06-defense/anticipated-questions.md)** — Comprehensive Q&A bank grounded in verified source code.
* **[Live Demonstration Script](06-defense/demonstration-script.md)** — 15-step live defense walkthrough with roles, routes, expected results, and backup plans.
* **[Final Presentation Outline & Speaker Notes](06-defense/presentation-outline.md)** — 22-slide master presentation deck with speaker notes.
* **[Screenshots & Visual Evidence Catalog](screenshots/README.md)** — Verified UI captures and empirical DSA benchmark SVG graphs.
