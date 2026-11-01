# HRIS-SYSTEM — Complete Project Documentation

> **Human Resource Information & Leave Management System with Native Data Structures, Real-Time WebSockets, and End-to-End Automation**

---

## Table of Contents

- [1. Project Overview](#1-project-overview)
  - [1.1 Mission and Dual Mandate](#11-mission-and-dual-mandate)
  - [1.2 Technology Stack Matrix](#12-technology-stack-matrix)
  - [1.3 Key System Capabilities](#13-key-system-capabilities)
- [2. System Requirements](#2-system-requirements)
  - [2.1 Functional Requirements (FR)](#21-functional-requirements-fr)
  - [2.2 Non-Functional Requirements (NFR)](#22-non-functional-requirements-nfr)
  - [2.3 Explicit Project Boundaries and Scope Limitations](#23-explicit-project-boundaries-and-scope-limitations)
- [3. Architecture and Design](#3-architecture-and-design)
  - [3.1 4-Tier Layered Architecture](#31-4-tier-layered-architecture)
  - [3.2 Edge Gateway & Container Serving Topology](#32-edge-gateway--container-serving-topology)
  - [3.3 Subsystem Interaction and Workflow Diagrams](#33-subsystem-interaction-and-workflow-diagrams)
- [4. Repository Structure](#4-repository-structure)
  - [4.1 Authoritative Directory Layout](#41-authoritative-directory-layout)
  - [4.2 Core Application Namespaces and Autoloading](#42-core-application-namespaces-and-autoloading)
- [5. Development Environment Setup](#5-development-environment-setup)
  - [5.1 Host System Prerequisites (Windows 11 / Linux)](#51-host-system-prerequisites-windows-11--linux)
  - [5.2 Option A: Containerized Docker Environment (Recommended)](#52-option-a-containerized-docker-environment-recommended)
  - [5.3 Option B: Local XAMPP / Native PHP Setup](#53-option-b-local-xampp--native-php-setup)
  - [5.4 Environment Configuration Reference (.env)](#54-environment-configuration-reference-env)
- [6. PHP and Composer](#6-php-and-composer)
  - [6.1 PHP Runtime Baseline (8.2 - 8.5)](#61-php-runtime-baseline-82---85)
  - [6.2 Required PHP Extensions](#62-required-php-extensions)
  - [6.3 Composer Dependency Management](#63-composer-dependency-management)
  - [6.4 Autoloader and Optimization Standards](#64-autoloader-and-optimization-standards)
- [7. Database Design and Migrations](#7-database-design-and-migrations)
  - [7.1 Database Engine Standards & Encoding](#71-database-engine-standards--encoding)
  - [7.2 Schema Architecture (13 Tables)](#72-schema-architecture-13-tables)
  - [7.3 Referential Integrity and Cascade Policies](#73-referential-integrity-and-cascade-policies)
  - [7.4 Migration Lifecycle and DDL Versioning](#74-migration-lifecycle-and-ddl-versioning)
- [8. Authentication and Email OTP](#8-authentication-and-email-otp)
  - [8.1 Two-Stage Authentication Protocol](#81-two-stage-authentication-protocol)
  - [8.2 Cryptographic OTP Generation and Storage](#82-cryptographic-otp-generation-and-storage)
  - [8.3 Rate Limiting, Expiration, and Lockout Defense](#83-rate-limiting-expiration-and-lockout-defense)
  - [8.4 PHPMailer Configuration and Local Mailpit Capture](#84-phpmailer-configuration-and-local-mailpit-capture)
  - [8.5 User Onboarding and Provisioning Workflows](#85-user-onboarding-and-provisioning-workflows)
  - [8.6 Pre-Seeded Development Accounts](#86-pre-seeded-development-accounts)
- [9. Authorization and RBAC](#9-authorization-and-rbac)
  - [9.1 2-Role Permission Matrix (HR vs Employee)](#91-2-role-permission-matrix-hr-vs-employee)
  - [9.2 Server-Side Route Guard Architecture](#92-server-side-route-guard-architecture)
  - [9.3 Strict Ownership Verification (IDOR Protection)](#93-strict-ownership-verification-idor-protection)
  - [9.4 Last-Active HR Lockout Prevention](#94-last-active-hr-lockout-prevention)
- [10. Employee Management](#10-employee-management)
  - [10.1 Workforce Registry Architecture](#101-workforce-registry-architecture)
  - [10.2 Server-Side Validation Rules](#102-server-side-validation-rules)
  - [10.3 Soft-Deactivation Lifecycle](#103-soft-deactivation-lifecycle)
  - [10.4 REST API Endpoints for Employees](#104-rest-api-endpoints-for-employees)
- [11. Department and Position Management](#11-department-and-position-management)
  - [11.1 Organizational Structure Data Model](#111-organizational-structure-data-model)
  - [11.2 Department-Scoped Positions & Cascade Dropdown API](#112-department-scoped-positions--cascade-dropdown-api)
  - [11.3 Structural Deletion Guards](#113-structural-deletion-guards)
  - [11.4 REST API Endpoints for Organization](#114-rest-api-endpoints-for-organization)
- [12. Leave Management](#12-leave-management)
  - [12.1 Leave Classification and Quota Allocations](#121-leave-classification-and-quota-allocations)
  - [12.2 Working-Day Calculation Engine (Mon–Fri Rules)](#122-working-day-calculation-engine-monfri-rules)
  - [12.3 Application Filing Lifecycle & Overlap Prevention](#123-application-filing-lifecycle--overlap-prevention)
  - [12.4 FIFO Review Queue & ACID Approval Transactions](#124-fifo-review-queue--acid-approval-transactions)
  - [12.5 Self-Service Cancellation & Rejection Auditing](#125-self-service-cancellation--rejection-auditing)
  - [12.6 REST API Endpoints for Leaves](#126-rest-api-endpoints-for-leaves)
- [13. Domain-Driven Design (DDD)](#13-domain-driven-design-ddd)
  - [13.1 Aggregates, Entities, and Value Objects](#131-aggregates-entities-and-value-objects)
  - [13.2 Domain Events (`LeaveApprovedEvent`, `LeaveRejectedEvent`)](#132-domain-events-leaveapprovedevent-leaverejectedevent)
  - [13.3 Transactional Outbox Pattern and Background Worker](#133-transactional-outbox-pattern-and-background-worker)
- [14. Data Structures and Algorithms (DSA)](#14-data-structures-and-algorithms-dsa)
  - [14.1 Academic Demonstration Framework](#141-academic-demonstration-framework)
  - [14.2 Custom In-Memory Data Structures](#142-custom-in-memory-data-structures)
  - [14.3 Custom Sorting and Searching Algorithms](#143-custom-sorting-and-searching-algorithms)
  - [14.4 Big-O Algorithmic Complexity Reference](#144-big-o-algorithmic-complexity-reference)
  - [14.5 Interactive Laboratory & Benchmarking Engine](#145-interactive-laboratory--benchmarking-engine)
- [15. Backend Testing with PHPUnit](#15-backend-testing-with-phpunit)
  - [15.1 Testing Architecture (357 Tests, 1399 Assertions)](#151-testing-architecture-357-tests-1399-assertions)
  - [15.2 Test Suites Breakdown (Unit, Integration, Feature)](#152-test-suites-breakdown-unit-integration-feature)
  - [15.3 Executing Backend Test Suites](#153-executing-backend-test-suites)
- [16. UI Testing with Selenium and pytest](#16-ui-testing-with-selenium-and-pytest)
  - [16.1 UI Automation Framework Architecture](#161-ui-automation-framework-architecture)
  - [16.2 Page Object Model (POM) Design](#162-page-object-model-pom-design)
  - [16.3 2FA Authentication Orchestration](#163-2fa-authentication-orchestration)
  - [16.4 Pytest Configuration and Execution Commands](#164-pytest-configuration-and-execution-commands)
- [17. Brave Browser Configuration](#17-brave-browser-configuration)
  - [17.1 Brave as Primary Local Browser (Windows 11)](#171-brave-as-primary-local-browser-windows-11)
  - [17.2 Executable Auto-Discovery Mechanism](#172-executable-auto-discovery-mechanism)
  - [17.3 Temporary Profile Sandboxing and Isolation](#173-temporary-profile-sandboxing-and-isolation)
  - [17.4 ChromeDriver Compatibility and Selenium Manager](#174-chromedriver-compatibility-and-selenium-manager)
- [18. Docker and Container Infrastructure](#18-docker-and-container-infrastructure)
  - [18.1 Multi-Container Compose Stack](#181-multi-container-compose-stack)
  - [18.2 Port Bindings and Security Isolation](#182-port-bindings-and-security-isolation)
  - [18.3 Persistent Volumes and Data Retention](#183-persistent-volumes-and-data-retention)
  - [18.4 Dedicated E2E Test Stack (`docker-compose.e2e.yml`)](#184-dedicated-e2e-test-stack-docker-composee2eyml)
- [19. Nginx Configuration](#19-nginx-configuration)
  - [19.1 Edge Reverse Proxy Architecture](#191-edge-reverse-proxy-architecture)
  - [19.2 WebSocket Protocol Upgrade Configuration (`/ws`)](#192-websocket-protocol-upgrade-configuration-ws)
  - [19.3 Security Headers and Resource Denial Rules](#193-security-headers-and-resource-denial-rules)
- [20. Monitoring and Observability](#20-monitoring-and-observability)
  - [20.1 Prometheus Architecture and Scrape Targets](#201-prometheus-architecture-and-scrape-targets)
  - [20.2 Specialized Exporters (`mysqld-exporter`, `nginx-exporter`)](#202-specialized-exporters-mysqld-exporter-nginx-exporter)
  - [20.3 Grafana Dashboards and Visualizations](#203-grafana-dashboards-and-visualizations)
  - [20.4 System Health & Telemetry Endpoints](#204-system-health--telemetry-endpoints)
- [21. Terraform and Infrastructure as Code](#21-terraform-and-infrastructure-as-code)
  - [21.1 Infrastructure as Code Architecture](#211-infrastructure-as-code-architecture)
  - [21.2 Modular Layout (`network`, `database`, `app`, `monitoring`)](#212-modular-layout-network-database-app-monitoring)
  - [21.3 Multi-Environment Provisioning (`dev`, `prod`)](#213-multi-environment-provisioning-dev-prod)
  - [21.4 State Tracking, Locking, and Drift Detection](#214-state-tracking-locking-and-drift-detection)
- [22. GitHub Actions and CI/CD](#22-github-actions-and-cicd)
  - [22.1 CI/CD Workflows Directory](#221-cicd-workflows-directory)
  - [22.2 PHPUnit & Static Analysis Pipeline (`unit-tests.yml`)](#222-phpunit--static-analysis-pipeline-unit-testsyml)
  - [22.3 Selenium E2E Test Suite Pipeline (`e2e-tests.yml`)](#223-selenium-e2e-test-suite-pipeline-e2e-testsyml)
  - [22.4 Terraform Validation Pipeline (`terraform.yml`)](#224-terraform-validation-pipeline-terraformyml)
  - [22.5 Docker Build & Hadolint Pipeline (`docker.yml`)](#225-docker-build--hadolint-pipeline-dockeryml)
- [23. Security Guidelines](#23-security-guidelines)
  - [23.1 Password Hashing and Storage Standards](#231-password-hashing-and-storage-standards)
  - [23.2 Session Hardening and Inactivity Deadlines](#232-session-hardening-and-inactivity-deadlines)
  - [23.3 Cross-Site Request Forgery (CSRF) Mitigation](#233-cross-site-request-forgery-csrf-mitigation)
  - [23.4 SQL Injection and XSS Defenses](#234-sql-injection-and-xss-defenses)
  - [23.5 Zero Backdoors & Hardcoded Credential Protections](#235-zero-backdoors--hardcoded-credential-protections)
- [24. Logging and Notifications](#24-logging-and-notifications)
  - [24.1 Immutable Activity Audit Trail](#241-immutable-activity-audit-trail)
  - [24.2 In-System Notification Center](#242-in-system-notification-center)
  - [24.3 Real-Time WebSocket Push Architecture (Ratchet)](#243-real-time-websocket-push-architecture-ratchet)
- [25. Database Backup and Recovery](#25-database-backup-and-recovery)
  - [25.1 Daily Logical Backup Automation (`backup-db.sh`)](#251-daily-logical-backup-automation-backup-dbsh)
  - [25.2 SHA-256 Checksum Validation & Retention Standards](#252-sha-256-checksum-validation--retention-standards)
  - [25.3 Disaster Recovery and Restoration (`restore-db.sh`)](#253-disaster-recovery-and-restoration-restore-dbsh)
  - [25.4 Point-in-Time Recovery (PITR) with Binary Logs](#254-point-in-time-recovery-pitr-with-binary-logs)
- [26. Development Workflow](#26-development-workflow)
  - [26.1 Branching Convention (`HRIS-*`)](#261-branching-convention-hris-)
  - [26.2 Jira Issue Synchronization](#262-jira-issue-synchronization)
  - [26.3 Pull Request Protocol and Review Criteria](#263-pull-request-protocol-and-review-criteria)
- [27. Coding Standards](#27-coding-standards)
  - [27.1 PSR-12 Coding Style Compliance](#271-psr-12-coding-style-compliance)
  - [27.2 Static Analysis Standards (PHPStan Level 5+)](#272-static-analysis-standards-phpstan-level-5)
  - [27.3 Formatting and Shell Script Linter (Actionlint / Shellcheck)](#273-formatting-and-shell-script-linter-actionlint--shellcheck)
- [28. Troubleshooting](#28-troubleshooting)
  - [28.1 Web Gateway & HTTP 502 Bad Gateway](#281-web-gateway--http-502-bad-gateway)
  - [28.2 MySQL Database Connection Errors](#282-mysql-database-connection-errors)
  - [28.3 Port Binding Conflicts (3306, 3307, 8080)](#283-port-binding-conflicts-3306-3307-8080)
  - [28.4 Email OTP Delivery and Timezone Synchronization](#284-email-otp-delivery-and-timezone-synchronization)
  - [28.5 Selenium Renderer Timeouts & Stale Reference Resolution](#285-selenium-renderer-timeouts--stale-reference-resolution)
  - [28.6 Brave Browser Driver Resolution Issues](#286-brave-browser-driver-resolution-issues)
- [29. Deployment and Operations](#29-deployment-and-operations)
  - [29.1 Production Readiness Checklist](#291-production-readiness-checklist)
  - [29.2 Nginx & Apache Server Hardening](#292-nginx--apache-server-hardening)
  - [29.3 Automated Smoke Testing (`smoke-test.sh`)](#293-automated-smoke-testing-smoke-testsh)
  - [29.4 Future VPS Provisioning Roadmap](#294-future-vps-provisioning-roadmap)
- [30. Known Limitations](#30-known-limitations)
  - [30.1 Out-of-Scope Enterprise Modules](#301-out-of-scope-enterprise-modules)
  - [30.2 Working-Day Calendar Constraints](#302-working-day-calendar-constraints)
  - [30.3 Single-Tier Approval Chains](#303-single-tier-approval-chains)
- [31. Future Improvements](#31-future-improvements)
  - [31.1 Dynamic Holiday Management Integration](#311-dynamic-holiday-management-integration)
  - [31.2 Hardware TOTP Authenticator Support (RFC 6238)](#312-hardware-totp-authenticator-support-rfc-6238)
  - [31.3 Multi-Tier Hierarchical Approval Workflows](#313-multi-tier-hierarchical-approval-workflows)

---

## 1. Project Overview

### 1.1 Mission and Dual Mandate

The **Employee HRIS & Leave Management System** is an enterprise-grade web application engineered in native PHP and MySQL/MariaDB with a distinct dual mandate:

1. **Operational Enterprise HRIS:** Delivers a complete, reliable, and secure human resource management platform. It governs centralized workforce records, departments, job positions, annual leave entitlements, transactional leave approval workflows, audit trails, and reporting under a strict **2-Role Architecture (Human Resources & Employee Self-Service)**.
2. **Academic Computer Science Laboratory:** Serves as an interactive workbench for demonstrating fundamental Data Structures and Algorithms (DSA). All demonstrated algorithms (Bubble Sort, Selection Sort, Insertion Sort, Merge Sort, Quick Sort, Binary Search, Linear Search) and data structures (Array Wrapper, Singly Linked List, LIFO Stack, FIFO Queue, Chained Hash Table, Binary Search Tree) are implemented **natively in PHP**, without relying on built-in PHP sorting/searching utilities or SQL database processing.

```
+-----------------------------------------------------------------------------------+
|                                 HRIS-SYSTEM DUAL MANDATE                          |
+-----------------------------------------------------------------------------------+
|  OPERATIONAL HUMAN RESOURCE PLATFORM     |     ACADEMIC COMPUTER SCIENCE ENGINE   |
|  - 2-Role RBAC (HR & Employee)           |     - Custom In-Memory Algorithms      |
|  - 2FA Email OTP Verification            |     - Custom In-Memory Data Structures |
|  - Working-Day Calculation (Mon–Fri)     |     - Benchmarking Suite (N=10 to 10k) |
|  - ACID Transactional Leave Processing   |     - Big-O Complexity Instrumentation |
|  - Real-Time Push Notifications (/ws)    |     - Educational Visualizer Workbench |
+-----------------------------------------------------------------------------------+
```

### 1.2 Technology Stack Matrix

| Layer | Technology | Version | Purpose |
|---|---|---|---|
| **Language Runtime** | PHP | 8.5.12 (CLI & Apache) | Core backend, MVC framework, DSA engine, Ratchet WebSocket server |
| **Relational Database** | MySQL Community Server | 8.0.46 | Relational persistence, ACID transactions, row-level locking |
| **Edge Reverse Proxy** | Nginx | 1.25 (Alpine) | Edge proxy, SSL/TLS, port 8080 binding, `/ws` WebSocket gateway |
| **Web Server** | Apache HTTP Server | 2.4 (embedded in PHP) | Internal web server, `.htaccess` URL rewriting (`mod_rewrite`) |
| **Real-Time WebSockets**| Ratchet (`cboden/ratchet`) | 0.4.4 | Asynchronous WebSocket push server (`bin/realtime-server.php`) |
| **Transactional Email** | PHPMailer | 7.1.0 | SMTP delivery for Two-Factor Email OTP verification |
| **Local SMTP Capture** | Mailpit | Latest | Ephemeral local mail server with REST API (`:8025`, `:1025`) |
| **Monitoring** | Prometheus | v2.51.0 | Time-series metrics collection (`:9090`) |
| **Visualization** | Grafana | 10.4.1 | Operational performance dashboards (`:3100`) |
| **Exporters** | `mysqld-exporter`, `nginx-exporter`| Latest | Metric scrapers for MySQL database and Nginx edge proxy |
| **API Documentation** | OpenAPI 3.0 / Swagger UI | v5.17.14 | Interactive REST API exploration portal (`:8083` and `/docs`) |
| **Infrastructure as Code**| Terraform | 1.16.6 | Declarative infrastructure lifecycle management (`kreuzwerker/docker`) |
| **Backend Testing** | PHPUnit | 13.4.5 | Unit, Integration, and Feature test automation (357 tests) |
| **Static Analysis** | PHPStan & PHP_CodeSniffer | Level 5+ / PSR-12 | Code quality, type safety, and formatting standards |
| **UI Automation** | Selenium WebDriver 4 & pytest | 4.25+ / 8.4+ | Automated browser UI testing with Brave Browser integration |
| **Primary Local Browser**| Brave Browser | Latest (Windows 11) | Chromium-based default browser for local UI automation |
| **CI/CD Platform** | GitHub Actions | Ubuntu 24.04 runners | Automated build, lint, PHPUnit, and Selenium E2E execution |

### 1.3 Key System Capabilities

- **Strict 2-Role Authorization:** Dedicated user portals for Human Resources (`hr`) and Employees (`employee`).
- **Two-Factor Authentication (2FA):** Mandatory 6-digit numeric Email OTP with atomic single-use row-locking and rate-limiting defense.
- **Accurate Leave Accrual & Deduction:** Monday–Friday business day calculation engine excluding weekends, preventing concurrent approval balance overdrafts via pessimistic row locks (`SELECT ... FOR UPDATE`).
- **Real-Time Notification Pipeline:** Ratchet-powered WebSockets backed by a Transactional Outbox pattern.
- **Native DSA Workbench:** Interactive algorithm benchmarking measuring runtime, memory usage, and operation counts across varying dataset sizes.
- **Brave Browser Integration:** First-class local Selenium automation targeting Brave Browser on Windows 11 with isolated temporary profile sandboxing.

---

## 2. System Requirements

### 2.1 Functional Requirements (FR)

| Code | Functional Area | Detailed Specification |
|---|---|---|
| `FR-AUTH-001` | Authentication | Authenticate users via registered email address and password with BCRYPT hash validation. |
| `FR-AUTH-002` | Two-Factor OTP | Generate cryptographically secure 6-digit OTP, deliver via SMTP, enforce 5-minute expiry and 60s resend cooldown. |
| `FR-AUTH-003` | Session Management | Regenerate session IDs upon login; terminate sessions after 30 minutes of inactivity. |
| `FR-EMP-001` | Employee Directory | Display searchable workforce directory with department, position, and status filtering. |
| `FR-EMP-002` | Employee Creation | Provision new employee records with validation of unique employee numbers and corporate emails. |
| `FR-EMP-003` | Lifecycle Status | Enforce soft-deactivation (`employment_status = 'Inactive'`). Prohibit destructive SQL cascading deletes. |
| `FR-ORG-001` | Departments | Manage organizational departments with unique names and employee headcount statistics. |
| `FR-ORG-002` | Positions | Manage job positions scoped to specific departments; expose dynamic JSON cascade API for dropdowns. |
| `FR-LEAVE-001`| Leave Types | Maintain leave classifications (Vacation, Sick, Emergency, Maternity, Paternity) with default day quotas. |
| `FR-LEAVE-002`| Day Calculation | Calculate leave durations counting exclusively Monday through Friday, omitting Saturdays and Sundays. |
| `FR-LEAVE-003`| Application Filing | Permit employees to file leave requests subject to date validity, quota availability, and overlap detection. |
| `FR-LEAVE-004`| FIFO Review Queue | Queue pending applications for HR review in First-In-First-Out (FIFO) chronological submission order. |
| `FR-LEAVE-005`| ACID Approval | Atomically deduct leave balance upon HR approval; record rejection reasons on declined requests. |
| `FR-LEAVE-006`| Self-Service Cancellation| Enable employees to cancel their own `Pending` requests prior to administrative review. |
| `FR-DSA-001` | In-Memory Structures | Provide custom native implementations of Stack, Queue, HashTable, LinkedList, and BinarySearchTree. |
| `FR-DSA-002` | In-Memory Algorithms | Provide custom native implementations of Bubble, Selection, Insertion, Merge, Quick sorts, and Binary/Linear search. |
| `FR-PERF-001`| Benchmarking | Benchmark sorting and searching routines across dataset sizes $N \in \{10, 100, 500, 1000, 5000, 10000\}$. |

### 2.2 Non-Functional Requirements (NFR)

- **`NFR-SEC-001` (Password Security):** Passwords hashed via BCRYPT with a cost factor of 12 (`PASSWORD_BCRYPT`).
- **`NFR-SEC-002` (CSRF Protection):** All state-changing requests (`POST`, `PUT`, `DELETE`) require a valid cryptographically secure token validated via timing-safe `hash_equals()`.
- **`NFR-SEC-003` (Cookie Hardening):** Session cookies configured with `HttpOnly = true`, `SameSite = Lax`, and conditional `Secure`.
- **`NFR-SEC-004` (Ownership Enforcement):** Zero horizontal privilege escalation (IDOR defense). Employees cannot access or modify records belonging to other employees.
- **`NFR-SEC-005` (Zero Backdoors):** No hardcoded demo credentials, test-only bypass routes, or authentication shortcuts.
- **`NFR-PERF-001` (Page Load Latency):** Server response time for standard views under 200ms on local environments.
- **`NFR-REL-001` (Data Integrity):** Foreign key constraints enforced with `ON DELETE RESTRICT` on historical employee and leave records.
- **`NFR-REL-002` (RPO / RTO):** Recovery Point Objective $\le 1$ hour; Recovery Time Objective $\le 30$ minutes.

### 2.3 Explicit Project Boundaries and Scope Limitations

The following capabilities are **explicitly out of scope**:
1. **Payroll & Compensation:** Salary disbursement, tax withholding, and bank remittances are excluded.
2. **Recruitment & ATS:** Candidate resume parsing and interview scheduling are excluded.
3. **Biometric Hardware:** Physical fingerprint/RFID hardware drivers are not integrated.
4. **Multi-Tier Approvals:** Approval hierarchy is strictly single-stage (handled directly by HR Officers).
5. **Mobile Native Apps:** Mobile support is delivered via responsive CSS viewports, not native mobile packages.

---

## 3. Architecture and Design

### 3.1 4-Tier Layered Architecture

The application adheres to a clean **4-Tier Layered Architecture** with strict server-side Role-Based Access Control:

```
[ ACTORS: HR Officer (`hr`) | Employee (`employee`) ]
                       |
                       v
[ 1. PRESENTATION LAYER ]
  - Responsive Semantic HTML5 / Vanilla CSS Design Tokens
  - Accessible Forms (WCAG 2.1 AA contrast, touch targets >= 44px)
  - JavaScript Realtime Client (WebSocket auto-reconnect & badge updates)
                       |
                       v
[ 2. APPLICATION & ROUTING LAYER ]
  - Dual-Base Router (`core/Router.php`) resolving direct or subpath URLs
  - Middleware Pipeline: `AuthMiddleware`, `RoleMiddleware`, `CsrfMiddleware`
  - RESTful API Controllers (`app/controllers/Api/*`)
  - In-Memory DSA Engine (`app/dsa/*`)
                       |
                       v
[ 3. DOMAIN & DATA ACCESS LAYER ]
  - Domain Use Cases & Aggregates (`app/Application/*`, `app/Domain/*`)
  - Centralized PDO Factory (`core/Database.php`)
  - Transaction Management (`PdoTransactionManager`)
  - Pessimistic Row Locking (`SELECT ... FOR UPDATE`)
                       |
                       v
[ 4. DATABASE & PERSISTENCE LAYER ]
  - MySQL 8.0 Community Server (InnoDB Engine, utf8mb4)
  - 13 Normalized Tables in 3NF with Referential Integrity
```

### 3.2 Edge Gateway & Container Serving Topology

The containerized stack runs on Docker Desktop via user-defined bridge network `hris_network` (`172.20.0.0/16`):

```
Client HTTP Request (Browser) ──► Nginx Edge Proxy (Port 8080)
                                        │
                                        ├── location / ──────► Apache 2.4 + PHP 8.5 (app:80)
                                        │                           │
                                        │                           ├── DocumentRoot: public/index.php
                                        │                           └── PDO Connection ──► MySQL (db:3306)
                                        │
                                        └── location = /ws ──► Ratchet WebSocket Server (realtime:8090)
                                                                    │
                                                                    └── Outbox Polling ──► MySQL (db:3306)
```

- **Nginx (`hris_nginx`):** Listens on port `8080` (public entrance). Proxies standard HTTP traffic to `app:80` and upgrades `/ws` to `realtime:8090`. Port `8082` is private for metrics.
- **PHP Application (`hris_app`):** Runs `php:8.5.12-apache-trixie`. Port 80 is **internal only** to prevent proxy bypass.
- **Realtime Service (`hris_realtime`):** Runs `bin/realtime-server.php` under CLI. Port 8090 is internal only.
- **Database (`hris_db`):** Runs `mysql:8.0`. Published to host on port `3307` to avoid colliding with local XAMPP on port 3306.

### 3.3 Subsystem Interaction and Workflow Diagrams

#### Leave Request & Approval Lifecycle

```
Employee                      Application Server                      HR Officer
   │                                  │                                   │
   │── 1. File Leave (POST /leaves) ─►│                                   │
   │   (Validates quota, working days)│                                   │
   │                                  │── 2. Enqueue in FIFO Review Queue │
   │                                  │   (Status: Pending)               │
   │                                  │                                   │
   │                                  │◄── 3. Open Queue (/hr/leaves) ────│
   │                                  │                                   │
   │                                  │◄── 4. Approve (POST .../approve) ─│
   │                                  │   (Pessimistic Row Lock,          │
   │                                  │    Deduct Annual Quota,           │
   │                                  │    Outbox Event Recorded)         │
   │                                  │                                   │
   │◄── 5. Push Notification (/ws) ───│──────────────────────────────────►│
   │   (Unread Badge Incremented)     │                                   │
```

---

## 4. Repository Structure

### 4.1 Authoritative Directory Layout

```text
HRIS-SYSTEM/
│
├── app/                            # Application Business Logic (MVC & DDD)
│   ├── Application/                # DDD Use Cases (Leave approvals, rejections, filing)
│   ├── Domain/                     # Domain Entities, Events, and Repository Contracts
│   ├── Infrastructure/             # Database Repositories & In-Memory Event Dispatcher
│   ├── controllers/                # MVC Web Controllers
│   │   ├── Api/                    # OpenAPI REST API Controllers (`app/controllers/Api/`)
│   │   ├── ActivityLogController.php # Immutable audit trail management
│   │   ├── AuthController.php      # 2FA Login, OTP validation, and session lifecycle
│   │   ├── DashboardController.php # HR & Employee dashboard dispatcher
│   │   ├── DepartmentController.php# Department directory & creation
│   │   ├── DsaController.php       # DSA Laboratory & benchmark UI
│   │   ├── EmployeeController.php  # Workforce directory, creation, soft deactivation
│   │   ├── HomeController.php      # Public landing page & health checks
│   │   ├── LeaveController.php     # Leave application, review queue, adjustments
│   │   ├── NotificationController.php# In-system notification center actions
│   │   ├── PositionController.php  # Job positions & cascade dropdown API
│   │   ├── RealtimeController.php  # Single-use WebSocket ticket issuance
│   │   ├── ReportController.php    # Workforce & leave analytics reports
│   │   └── UserController.php      # User account governance & role provisioning
│   ├── models/                     # Active Record Domain Models
│   │   ├── ActivityLog.php         # Audit trail logging model
│   │   ├── Department.php          # Department model
│   │   ├── EmailOtp.php            # Two-factor OTP persistence model
│   │   ├── Employee.php            # Workforce records model
│   │   ├── Leave.php               # Leave requests & transactional balance deduction
│   │   ├── Notification.php        # In-system notifications model
│   │   ├── Position.php            # Job positions model
│   │   ├── RealtimeTicket.php      # WebSocket single-use authorization ticket
│   │   └── User.php                # Authentication accounts model
│   ├── services/                   # Application Domain Services
│   │   ├── EmployeeDirectoryService.php # Custom DSA search/sort adapters
│   │   ├── LeaveCalculationService.php  # Monday–Friday working-day calculation
│   │   ├── LeaveNotificationService.php # Event notification factory
│   │   ├── RealtimeTicketService.php    # Single-use ticket issuer
│   │   └── TotpService.php              # RFC 6238 TOTP engine foundation
│   ├── dsa/                        # Custom Native PHP DSA Engine
│   │   ├── algorithms/             # Sorting and searching algorithms
│   │   ├── arrays/                 # Array utility operations
│   │   ├── benchmarks/             # Benchmarking runners and counters
│   │   └── structures/             # Stack, Queue, HashTable, LinkedList, BST
│   ├── realtime/                   # Ratchet WebSocket Server Subsystem
│   └── views/                      # Semantic HTML5 Layouts, Partials, and Views
│
├── bin/                            # Operational CLI Executables
│   ├── create_hr_user.php          # Non-interactive / interactive HR bootstrapper
│   ├── generate_docs.php           # Swagger OpenAPI specification generator
│   ├── migrate.php                 # Database migration runner
│   ├── outbox-worker.php           # Transactional outbox polling worker
│   ├── realtime-server.php         # Ratchet WebSocket server daemon
│   └── test_smtp.php               # Interactive SMTP diagnostic utility
│
├── config/                         # Configuration Files
│   ├── config.php                  # Base URL, environment, session timeouts
│   ├── database.php                # Database credentials & PDO configuration
│   └── mail.php                    # PHPMailer SMTP connection settings
│
├── core/                           # Custom MVC Micro-framework Kernel
│   ├── AuthMiddleware.php          # Session gatekeeper middleware
│   ├── Controller.php              # Base controller with view and JSON helpers
│   ├── CsrfMiddleware.php          # Timing-safe CSRF verification
│   ├── Database.php                # Centralized PDO connection factory
│   ├── Model.php                   # Base database model
│   ├── Permission.php              # Centralized 2-Role RBAC Capability Matrix
│   ├── RoleMiddleware.php          # Role-Based Access Control gatekeeper
│   ├── Router.php                  # Dual-base HTTP router engine
│   ├── Session.php                 # Hardened session manager
│   └── functions.php               # Global helpers (`auth()`, `csrf_field()`, `url()`, `e()`)
│
├── database/                       # Database Baseline & Migrations
│   ├── migrations/                 # Incremental SQL migration scripts
│   ├── schema.sql                  # Complete schema baseline (13 tables)
│   └── seed.sql                    # Seed data with BCRYPT credentials
│
├── docker/                         # Docker Container Definitions
│   ├── nginx/                      # Nginx edge configuration (`default.conf`)
│   ├── php/                        # PHP custom ini settings
│   └── prometheus/                 # Prometheus scrape configurations
│
├── docs/                           # Consolidated Historical Specs & OpenAPI Assets
│   └── api/                        # Generated OpenAPI 3.0 YAML & JSON specifications
│
├── e2e-tests/                      # Dedicated Selenium WebDriver UI Automation Framework
│   ├── config/                     # Test settings and Brave/Chrome browser factory
│   ├── pages/                      # Page Object Model classes
│   ├── tests/                      # Pytest UI automation test suites
│   ├── utils/                      # 2FA OTP database synchronization helpers
│   ├── reports/                    # Generated HTML test execution reports (gitignored)
│   ├── screenshots/                # Failure evidence screenshots (gitignored)
│   ├── conftest.py                 # Shared pytest fixtures & driver lifecycle hooks
│   ├── pytest.ini                  # Pytest execution markers & configuration
│   └── requirements.txt            # Python dependencies (Selenium, pytest, pymysql)
│
├── public/                         # Public Document Root
│   ├── assets/                     # CSS design tokens, JS modules, images
│   ├── .htaccess                   # Apache URL rewriting & security rules
│   └── index.php                   # Front Controller application entry point
│
├── scripts/                        # Operational DevOps Shell Scripts
│   ├── backup/backup-db.sh         # Non-locking logical database backup
│   ├── deploy/smoke-test.sh        # Deployment smoke test verification
│   └── restore/restore-db.sh       # Disaster recovery database restoration
│
├── terraform/                      # Modular Infrastructure as Code (IaC)
│   ├── environments/               # `dev` and `prod` environment declarations
│   └── modules/                    # `network`, `database`, `app`, `monitoring` modules
│
├── tests/                          # Backend PHPUnit Test Suites (357 tests)
│   ├── Unit/                       # Isolated unit tests
│   ├── Integration/                # Database integration tests
│   ├── Feature/                    # Full HTTP controller and route tests
│   └── bootstrap.php               # PHPUnit test bootstrapper
│
├── docker-compose.yml              # Primary 8-container local development stack
├── docker-compose.prod.yml         # Production container configuration
├── docker-compose.e2e.yml          # Containerized Selenium Grid & Mailpit test stack
├── Dockerfile                      # Application container build image
├── composer.json                   # PHP package dependencies & PSR-4 autoloading
├── phpunit.xml                     # PHPUnit configuration & testsuite mappings
└── README.md                       # Project entry point and quick start guide
```

### 4.2 Core Application Namespaces and Autoloading

Configured in [`composer.json`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/composer.json) under `autoload`:

```json
{
    "autoload": {
        "psr-4": {
            "Core\\": "core/",
            "App\\Controllers\\": "app/controllers/",
            "App\\Models\\": "app/models/",
            "App\\Services\\": "app/services/",
            "App\\Realtime\\": "app/realtime/",
            "App\\Dsa\\Algorithms\\": "app/dsa/algorithms/",
            "App\\Dsa\\Structures\\": "app/dsa/structures/",
            "App\\Dsa\\Arrays\\": "app/dsa/arrays/",
            "App\\Dsa\\": "app/dsa/",
            "App\\": "app/"
        },
        "classmap": [
            "app/",
            "core/"
        ],
        "files": [
            "core/functions.php"
        ]
    }
}
```

---

## 5. Development Environment Setup

### 5.1 Host System Prerequisites (Windows 11 / Linux)

- **Operating System:** Windows 11 (64-bit) or Linux (Ubuntu 22.04+)
- **Virtualization:** WSL2 enabled with Docker Desktop $\ge 4.20$
- **Git:** Git for Windows / Linux
- **Python:** Python 3.10+ (for Selenium E2E test suites)
- **Primary Browser:** Brave Browser installed (default location: `%LOCALAPPDATA%\BraveSoftware\Brave-Browser\Application\brave.exe`)

### 5.2 Option A: Containerized Docker Environment (Recommended)

The system includes a pre-configured multi-container stack.

#### 1. Start Containers
```powershell
# From the repository root:
docker compose up -d
```

#### 2. Access Operational Interfaces
- **HRIS Application:** [http://localhost:8080](http://localhost:8080)
- **phpMyAdmin (Database GUI):** [http://localhost:8081](http://localhost:8081)
- **Swagger REST API Documentation:** [http://localhost:8083](http://localhost:8083) or [http://localhost:8080/docs](http://localhost:8080/docs)
- **Prometheus Telemetry:** [http://localhost:9090](http://localhost:9090)
- **Grafana Monitoring:** [http://localhost:3100](http://localhost:3100) *(User: `admin` / Password: `admin`)*
- **Database Host Port:** `localhost:3307` (avoids conflict with local XAMPP on port 3306)

#### 3. Run Initial Database Migrations (Inside Container)
```powershell
docker compose exec app php bin/migrate.php
```

### 5.3 Option B: Local XAMPP / Native PHP Setup

#### 1. Configure Web Server and Database
1. Launch Apache and MySQL from the XAMPP Control Panel.
2. Create the database:
   ```sql
   CREATE DATABASE IF NOT EXISTS `hris_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Import the schema and seed data:
   ```powershell
   mysql -u root -p hris_db < database/schema.sql
   mysql -u root -p hris_db < database/seed.sql
   ```

#### 2. Install PHP Dependencies
```powershell
composer install
```

#### 3. Access Local URL
- **Direct Apache Route:** `http://localhost/GitHub_Clone_File_Projects/HRIS-SYSTEM/public/`
- **Root Alias Route:** `http://localhost/GitHub_Clone_File_Projects/HRIS-SYSTEM/`

### 5.4 Environment Configuration Reference (.env)

| Variable | Default Value | Description |
|---|---|---|
| `APP_ENV` | `development` | Environment mode (`development`, `testing`, `production`) |
| `BASE_URL` | `http://localhost:8080` | Publicly reachable application URL |
| `DB_HOST` | `127.0.0.1` | MySQL database host (`db` inside Docker network) |
| `DB_PORT` | `3307` | MySQL database port (`3306` inside Docker, `3307` on host) |
| `DB_DATABASE` | `hris_db` | Database name |
| `DB_USERNAME` | `hris_user` | Database user account |
| `DB_PASSWORD` | `hris_password` | Database password |
| `MAIL_HOST` | `mailpit` | SMTP host for OTP dispatch (`127.0.0.1` on host) |
| `MAIL_PORT` | `1025` | SMTP port (Mailpit inbound port) |
| `MAIL_MOCK` | `false` | When `true`, logs OTPs to disk without sending real mail |
| `WS_ENABLED` | `true` | Enables Ratchet WebSocket push notifications |
| `WS_PUBLIC_URL`| `ws://localhost:8080/ws` | Public WebSocket endpoint routed through Nginx |

---

## 6. PHP and Composer

### 6.1 PHP Runtime Baseline (8.2 - 8.5)

The application code is strictly verified across modern PHP releases (`^8.2 || ^8.3 || ^8.4 || ^8.5`). The primary container environment runs official **PHP 8.5.12**.

### 6.2 Required PHP Extensions

- `pdo`, `pdo_mysql`: Database connectivity and prepared statement execution.
- `mbstring`: Multibyte string operations and UTF-8 validation.
- `session`: Hardened session management and state handling.
- `curl`: Outgoing HTTP requests and telemetry verification.
- `json`: JSON encoding and decoding for APIs and WebSockets.
- `openssl`: Cryptographic tokens and OTP generation.
- `ctype`, `fileinfo`: Input validation and mime-type verification.

### 6.3 Composer Dependency Management

Declared packages in [`composer.json`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/composer.json):

```json
{
    "require": {
        "php": "^8.2 || ^8.3 || ^8.4 || ^8.5",
        "phpmailer/phpmailer": "^7.1",
        "vlucas/phpdotenv": "^5.7",
        "zircote/swagger-php": "^6.16",
        "cboden/ratchet": "0.4.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^13.4",
        "squizlabs/php_codesniffer": "^4.0",
        "phpstan/phpstan": "^2.3"
    }
}
```

### 6.4 Autoloader and Optimization Standards

For local development:
```powershell
composer dump-autoload
```

For production builds:
```powershell
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
```

---

## 7. Database Design and Migrations

### 7.1 Database Engine Standards & Encoding

- **Storage Engine:** `InnoDB` across all 13 tables, guaranteeing ACID compliance, foreign key constraints, and pessimistic row locking (`SELECT ... FOR UPDATE`).
- **Character Set & Collation:** `utf8mb4` with `utf8mb4_unicode_ci` collation, providing full Unicode coverage for diverse names and international accents.

### 7.2 Schema Architecture (13 Tables)

```
+--------------------+        +--------------------+
|    departments     |        |     positions      |
|--------------------|        |--------------------|
| id (PK)            |<---1:N-| id (PK)            |
| department_name (U)|        | department_id (FK) |
| description        |        | position_name      |
+--------------------+        +--------------------+
          │                              │
         1:N                            1:N
          ▼                              ▼
+--------------------------------------------------+
|                    employees                     |
|--------------------------------------------------|
| id (PK)                                          |
| employee_no (U), email (U), first_name, last_name|
| department_id (FK), position_id (FK)             |
| employment_status ('Active', 'Inactive')         |
+--------------------------------------------------+
          │
         1:1
          ▼
+--------------------+        +--------------------+
|       users        |        |     email_otps     |
|--------------------|        |--------------------|
| id (PK)            |<---1:N-| id (PK)            |
| employee_id (FK, U)|        | user_id (FK)       |
| username (U), email|        | otp_hash, attempts |
| role ('hr','emp')  |        | expires_at         |
+--------------------+        +--------------------+
          │                              │
         1:N                            1:N
          ▼                              ▼
+--------------------+        +--------------------+
|   leave_requests   |        |   leave_balances   |
|--------------------|        |--------------------|
| id (PK)            |        | id (PK)            |
| employee_id (FK)   |        | employee_id (FK)   |
| leave_type_id (FK) |        | leave_type_id (FK) |
| number_of_days     |        | year, allocated    |
| status             |        | used, remaining    |
+--------------------+        +--------------------+
```

1. **`departments`:** Organizational units (`id`, `department_name`, `description`, `created_at`).
2. **`positions`:** Job classifications linked to departments (`id`, `position_name`, `department_id`, `description`, `created_at`). Unique on `(department_id, position_name)`.
3. **`employees`:** Centralized master directory (`id`, `employee_no`, `first_name`, `middle_name`, `last_name`, `email`, `phone`, `address`, `birth_date`, `gender`, `department_id`, `position_id`, `date_hired`, `employment_status`, `created_at`).
4. **`users`:** Authentication accounts (`id`, `employee_id`, `username`, `email`, `password`, `role`, `status`, `created_at`). `role` is strictly enum `'hr'` or `'employee'`.
5. **`leave_types`:** Leave classifications (`id`, `leave_name`, `description`, `default_days`, `status`, `created_at`).
6. **`leave_balances`:** Annual quota allocations (`id`, `employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`, `remaining_days`, `created_at`). Unique on `(employee_id, leave_type_id, year)`.
7. **`leave_requests`:** Lifecycle of filed applications (`id`, `employee_id`, `leave_type_id`, `start_date`, `end_date`, `number_of_days`, `reason`, `status`, `rejection_reason`, `approved_by`, `approved_at`, `created_at`).
8. **`activity_logs`:** Immutable audit records (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`).
9. **`notifications`:** In-system workflow alerts (`id`, `user_id`, `title`, `message`, `is_read`, `created_at`, `event_type`, `entity_id`).
10. **`email_otps`:** Two-factor authentication challenges (`id`, `user_id`, `otp_hash`, `expires_at`, `is_used`, `attempts`, `created_at`).
11. **`realtime_tickets`:** Single-use WebSocket tickets (`id`, `ticket_hash`, `user_id`, `role`, `created_at`, `expires_at`, `used_at`).
12. **`outbox_events`:** Transactional outbox table (`id`, `event_name`, `payload`, `status`, `created_at`, `processed_at`).
13. **`user_totp`:** RFC 6238 TOTP encrypted secrets (`id`, `user_id`, `secret`, `is_enabled`, `created_at`).

### 7.3 Referential Integrity and Cascade Policies

- **`ON DELETE RESTRICT`:** Enforced on employee foreign keys in `leave_requests` and `leave_balances`. Active or historical employee records cannot be deleted if associated with operational records.
- **Soft-Deactivation Pattern:** Workforce deactivation sets `employment_status = 'Inactive'`. Hard deletion is prohibited.
- **`ON DELETE SET NULL`:** Used on `activity_logs.user_id` to preserve audit trails even if a user account is removed.

### 7.4 Migration Lifecycle and DDL Versioning

Migrations are located in [`database/migrations/`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/database/migrations/) as timestamped SQL scripts.

Execute migrations:
```powershell
php bin/migrate.php
```

Or execute against Docker:
```powershell
docker compose exec app php bin/migrate.php
```

---

## 8. Authentication and Email OTP

### 8.1 Two-Stage Authentication Protocol

```
Step 1: Credential Challenge (/login)
  ├── Input: Corporate Email + Password
  ├── Verification: Email normalization, rate limiting, BCRYPT verification
  └── State: Credentials validated; temporary pre-auth session initialized

Step 2: OTP Dispatch & Challenge Screen (/login/otp)
  ├── Code Generation: random_int(100000, 999999)
  ├── Cryptographic Hash: SHA-256 stored in email_otps (Plaintext NEVER stored)
  ├── Email Dispatch: Delivered via PHPMailer SMTP (or captured in Mailpit)
  └── User Input: Split 6-digit input box with auto-focus and auto-submit

Step 3: Verification & Dashboard Dispatch
  ├── Validation: Atomic row lock, hash match, expiry check (< 300s), attempts < 5
  ├── Session Regeneration: session_regenerate_id(true) to prevent fixation
  └── Dispatch:
        ├── HR Role ('hr') ──────► /hr/dashboard
        └── Employee Role ('employee') ──► /employee/dashboard
```

### 8.2 Cryptographic OTP Generation and Storage

The OTP plaintext is generated using PHP's cryptographically secure pseudo-random integer generator:
```php
$otp = (string)random_int(100000, 999999);
$otpHash = hash('sha256', $otp);
```
Only `$otpHash` is inserted into `email_otps`. An attacker with database read access cannot compromise active 2FA challenges.

### 8.3 Rate Limiting, Expiration, and Lockout Defense

- **Expiration TTL:** OTP challenges expire after 5 minutes (300 seconds).
- **Max Verification Attempts:** A challenge allows at most 5 attempts before becoming invalidated.
- **Resend Cooldown:** Users must wait 60 seconds before requesting a fresh code.
- **Account Lockout:** 10 consecutive verification failures within 15 minutes triggers a temporary IP and account challenge lockout.

### 8.4 PHPMailer Configuration and Local Mailpit Capture

Configured in [`config/mail.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/config/mail.php). In development and container stacks, emails are routed to **Mailpit** on port `1025` and inspected via the Mailpit web UI on port `8025`.

Test SMTP connectivity:
```powershell
php bin/test_smtp.php
```

### 8.5 User Onboarding and Provisioning Workflows

There is **no public self-registration**. Accounts are provisioned through two authorized methods:

#### Method 1: Privileged HR Account CLI Bootstrapper
```powershell
# Interactive mode:
php bin/create_hr_user.php

# Non-interactive CLI flags:
php bin/create_hr_user.php --email="hr.admin@company.com" --password="StrongPassword123!" --username="hr.admin"
```

#### Method 2: HR Portal Provisioning
1. An authenticated HR Officer visits **User Accounts (`/hr/users`)**.
2. Clicks **"+ Provision User Account"** or selects an unlinked employee record from `/hr/employees/{id}`.
3. Selects the employee, assigns the role (`hr` or `employee`), and sets the initial password.

### 8.6 Pre-Seeded Development Accounts

| Email | Role | Associated Employee | Dashboard URL | Permitted Scope |
|---|---|---|---|---|
| `marcus.tan@company.local` | `hr` | Marcus Tan (`EMP002`) | `/hr/dashboard` | Full workforce management, leaves, accounts, reports |
| `sofia.reyes@company.local` | `hr` | Sofia Reyes (`EMP003`) | `/hr/dashboard` | Full workforce management, leaves, accounts, reports |
| `david.cruz@company.local` | `employee` | David Cruz (`EMP004`) | `/employee/dashboard` | Personal leave filing, balances, profile |
| `maria.santos@company.local`| `employee` | Maria Santos (`EMP005`)| `/employee/dashboard` | Personal leave filing, balances, profile |
| `juan.delacruz@company.local`| `employee`| Juan Dela Cruz (`EMP006`)| `/employee/dashboard` | Personal leave filing, balances, profile |
| `beatrice.gomez@company.local`| `employee`| Beatrice Gomez (`EMP007`)| `/employee/dashboard` | Personal leave filing, balances, profile |

*Default seed password across all accounts:* `Admin@123` (HR) / `Employee@123` (Employee).

---

## 9. Authorization and RBAC

### 9.1 2-Role Permission Matrix (HR vs Employee)

The system enforces a clean **2-Role Architecture**. The legacy `admin` role has been formally unified into `hr`.

| Capability / Route | Human Resources (`hr`) | Employee Self-Service (`employee`) |
|---|:---:|:---:|
| View HR Operational Dashboard (`/hr/dashboard`) | ✅ Allowed | ❌ 403 Forbidden |
| View Employee Self-Service Dashboard (`/employee/dashboard`) | ❌ Redirected | ✅ Allowed |
| Workforce Directory & Profile Inspection (`/hr/employees/*`) | ✅ Allowed | ❌ 403 Forbidden |
| Register / Edit / Deactivate Employee (`/hr/employees/*`) | ✅ Allowed | ❌ 403 Forbidden |
| Department & Position Management (`/hr/departments`, `/hr/positions`)| ✅ Allowed | ❌ 403 Forbidden |
| User Account Provisioning (`/hr/users/*`) | ✅ Allowed | ❌ 403 Forbidden |
| View Organization-Wide Reports (`/reports/*`) | ✅ Allowed | ❌ 403 Forbidden |
| View System Activity Audit Trail (`/activity-logs`) | ✅ Allowed | ❌ 403 Forbidden |
| View Organization Leave Review Queue (`/hr/leaves/*`) | ✅ Allowed | ❌ 403 Forbidden |
| Approve / Reject Leave Requests (`/hr/leaves/{id}/*`) | ✅ Allowed | ❌ 403 Forbidden |
| File Personal Leave Request (`/leaves/apply`) | ❌ (Use HR Adjust) | ✅ Allowed |
| View Personal Leave History (`/leaves/my`) | ❌ Dedicated | ✅ Allowed |
| Cancel Own Pending Leave Request (`/leaves/{id}/cancel`) | ❌ N/A | ✅ Allowed (Owner Only) |
| Real-Time Notification Center (`/notifications`) | ✅ Allowed | ✅ Allowed |

### 9.2 Server-Side Route Guard Architecture

Authorization is enforced server-side before controller dispatch:

```php
// core/RoleMiddleware.php
public function handle(array $allowedRoles): void
{
    $user = Auth::user();
    if (!$user || !in_array($user['role'], $allowedRoles, true)) {
        http_response_code(403);
        require __DIR__ . '/../app/views/errors/403.php';
        exit;
    }
}
```

Routes declared in [`public/index.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/public/index.php) chain `AuthMiddleware` and `RoleMiddleware`:
```php
$router->get('/hr/employees', [EmployeeController::class, 'index'], [AuthMiddleware::class, RoleMiddleware::hr()]);
$router->get('/employee/dashboard', [DashboardController::class, 'employee'], [AuthMiddleware::class, RoleMiddleware::employee()]);
```

### 9.3 Strict Ownership Verification (IDOR Protection)

In compliance with `NFR-SEC-004`, controllers verify entity ownership against `Auth::id()`:
- When viewing a leave application (`GET /leaves/{id}`), an employee can only view the record if `leave_requests.employee_id == Auth::user()['employee_id']`.
- Parameter tampering attempts (e.g., submitting `?emp_id=999`) are rejected with `HTTP 403 Forbidden`.

### 9.4 Last-Active HR Lockout Prevention

The user controller prevents deactivating or demoting the last active HR account:
```php
if ($targetUser['role'] === 'hr' && $action === 'deactivate') {
    $activeHrCount = $this->userModel->countActiveByRole('hr');
    if ($activeHrCount <= 1) {
        throw new DomainException("Cannot deactivate the sole remaining active HR administrator.");
    }
}
```

---

## 10. Employee Management

### 10.1 Workforce Registry Architecture

Employees are managed via `/hr/employees`. The interface provides:
- Search bar querying employee number, first name, last name, and corporate email.
- Department and status (`Active`, `Inactive`) filter dropdowns.
- Custom DSA sorting controls (Quick Sort, Merge Sort) and search algorithms (Linear Search, Binary Search).
- Detailed employee profile view with demographic details, organizational hierarchy, and historical leave requests.

### 10.2 Server-Side Validation Rules

Managed by `App\Services\EmployeeValidator`:
- `employee_no`: Required, alphanumeric (`EMP\d{3,6}`), globally unique in `employees`.
- `first_name`, `last_name`: Required, string, 2 to 50 characters, letters, spaces, hyphens only.
- `email`: Required, valid corporate email format, unique across `employees`.
- `birth_date`: Required, valid ISO date (`YYYY-MM-DD`), employee age must be $\ge 18$ years.
- `gender`: Required, enum: `Male`, `Female`, `Other`.
- `department_id`: Required, must exist in `departments`.
- `position_id`: Required, must exist in `positions` and belong to the selected `department_id`.
- `date_hired`: Required, valid ISO date (`YYYY-MM-DD`).

### 10.3 Soft-Deactivation Lifecycle

When an employee departs:
1. HR clicks **"Deactivate Employee"** from the directory or profile.
2. A confirmation modal explains that the record is soft-deactivated.
3. `employment_status` is updated to `'Inactive'`.
4. The linked user account in `users` is updated to `'Inactive'`, immediately invalidating active sessions.
5. All historical leave records, balances, and audit trails remain preserved.

### 10.4 REST API Endpoints for Employees

- `GET /api/v1/employees`: Search and list employees (supports `q`, `dept`, `status`).
- `GET /api/v1/employees/{id}`: Inspect single employee record.
- `POST /api/v1/employees`: Register new employee record.
- `PUT /api/v1/employees/{id}`: Update employee demographic and placement information.
- `POST /api/v1/employees/{id}/deactivate`: Soft-deactivate employee record.

---

## 11. Department and Position Management

### 11.1 Organizational Structure Data Model

- **`departments`:** Top-level operational divisions (e.g., *Human Resources*, *Information Technology*, *Finance & Accounting*).
- **`positions`:** Specific job classifications belonging to a parent department (e.g., *HR Officer*, *Senior Software Engineer*).

### 11.2 Department-Scoped Positions & Cascade Dropdown API

On employee registration and edit forms, selecting a department automatically filters the position dropdown via an asynchronous endpoint:
- **Endpoint:** `GET /hr/api/positions-by-department/{department_id}`
- **Response Format:**
  ```json
  [
    {"id": 4, "position_name": "Senior Software Engineer"},
    {"id": 5, "position_name": "Junior Software Engineer"}
  ]
  ```

### 11.3 Structural Deletion Guards

Deleting a department or position that has active or historical employee associations is rejected with a validation error (`NFR-REL-001`), enforced by MySQL `ON DELETE RESTRICT`.

### 11.4 REST API Endpoints for Organization

- `GET /api/v1/departments`: List departments with active position and employee counts.
- `POST /api/v1/departments`: Create department *(HR only)*.
- `PUT /api/v1/departments/{id}`: Update department *(HR only)*.
- `GET /api/v1/positions`: List job positions.
- `POST /api/v1/positions`: Create position *(HR only)*.
- `PUT /api/v1/positions/{id}`: Update position *(HR only)*.

---

## 12. Leave Management

### 12.1 Leave Classification and Quota Allocations

Standard statutory and company leave classifications:
1. **Vacation Leave:** 15 allocated days per fiscal year.
2. **Sick Leave:** 10 allocated days per fiscal year.
3. **Emergency Leave:** 5 allocated days per fiscal year.
4. **Maternity Leave:** 60 allocated days (female employees).
5. **Paternity Leave:** 7 allocated days (male employees).

### 12.2 Working-Day Calculation Engine (Mon–Fri Rules)

Implemented in `App\Services\LeaveCalculationService`:
- Computes leave duration by iterating from `start_date` through `end_date`.
- Increments the day count **only if** the day is Monday through Friday (`date('N') <= 5`).
- **Saturdays and Sundays are strictly excluded.**
- A leave filed from Friday to Monday evaluates to **2 working days** (Friday + Monday).

```php
public static function calculateWorkingDays(string $startDate, string $endDate): int
{
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $workingDays = 0;
    while ($start <= $end) {
        if ((int)$start->format('N') <= 5) {
            $workingDays++;
        }
        $start->modify('+1 day');
    }
    return $workingDays;
}
```

### 12.3 Application Filing Lifecycle & Overlap Prevention

When an employee files a leave application (`/leaves/apply`):
1. **Date Order Check:** `start_date` must precede or equal `end_date`.
2. **Future Date Check:** Leave requests cannot be filed for past dates.
3. **Working Days Verification:** Calculated working days must be $\ge 1$.
4. **Quota Availability:** Remaining leave balance must be $\ge$ calculated working days.
5. **Date Overlap Collision:** Validates that the requested date range does not collide with existing `Pending` or `Approved` leave requests for that employee:
   ```sql
   SELECT COUNT(*) FROM leave_requests 
   WHERE employee_id = :emp_id 
     AND status IN ('Pending', 'Approved')
     AND start_date <= :end_date 
     AND end_date >= :start_date
   ```

### 12.4 FIFO Review Queue & ACID Approval Transactions

1. Applications are queued for HR review (`/hr/leaves`) in **First-In-First-Out (FIFO)** order by `created_at ASC`.
2. Upon approval, `App\Application\Leave\UseCases\ApproveLeaveRequestUseCase` executes an atomic ACID transaction:
   - Acquires pessimistic row locks:
     ```sql
     SELECT * FROM leave_requests WHERE id = :id FOR UPDATE;
     SELECT * FROM leave_balances WHERE employee_id = :emp AND leave_type_id = :type AND year = :year FOR UPDATE;
     ```
   - Re-verifies that request status is still `Pending` and remaining quota is still sufficient.
   - Updates `leave_requests.status = 'Approved'`, records `approved_by` and `approved_at`.
   - Deducts balance: `used_days = used_days + N`, `remaining_days = remaining_days - N`.
   - Records an event in `outbox_events` and commits.

### 12.5 Self-Service Cancellation & Rejection Auditing

- **Cancellation:** Employees can cancel their own `Pending` requests via `POST /leaves/{id}/cancel`.
- **Rejection:** When declining a request, HR must submit a mandatory `rejection_reason`.

### 12.6 REST API Endpoints for Leaves

- `GET /api/v1/leave-types`: Active leave classifications.
- `GET /api/v1/leave-balances/my`: Authenticated employee's current annual leave balances.
- `GET /api/v1/hr/leave-balances`: Organization-wide leave balances *(HR only)*.
- `POST /api/v1/hr/leave-balances/adjust`: Administrative quota adjustments *(HR only)*.
- `GET /api/v1/leaves/my`: Authenticated employee's filed leave history.
- `POST /api/v1/leaves`: Submit new leave application.
- `POST /api/v1/leaves/{id}/cancel`: Cancel pending leave request.
- `GET /api/v1/hr/leaves`: Organization review queue *(HR only)*.
- `POST /api/v1/hr/leaves/{id}/approve`: Transactional leave approval *(HR only)*.
- `POST /api/v1/hr/leaves/{id}/reject`: Transactional leave rejection *(HR only)*.

---

## 13. Domain-Driven Design (DDD)

### 13.1 Aggregates, Entities, and Value Objects

Organized in `app/Domain/`:
- **`LeaveRequest` Aggregate Root:** Enforces business invariants regarding status transitions (`Pending` $\to$ `Approved` / `Rejected` / `Cancelled`).
- **`LeaveBalance` Entity:** Encapsulates quota invariants (remaining days cannot drop below zero).
- **Value Objects:** `DateRange`, `LeaveDays`, `RejectionReason`, `EmployeeNumber`.

### 13.2 Domain Events (`LeaveApprovedEvent`, `LeaveRejectedEvent`)

State mutations release immutable domain events implementing `App\Domain\Common\DomainEventInterface`:
- `LeaveApprovedEvent`: Properties: `requestId`, `employeeId`, `approverUserId`, `occurredAt`.
- `LeaveRejectedEvent`: Properties: `requestId`, `employeeId`, `approverUserId`, `rejectionReason`, `occurredAt`.

### 13.3 Transactional Outbox Pattern and Background Worker

To prevent distributed failure between MySQL database transactions and external notification systems (WebSockets / SMTP):
1. Domain events are serialized into JSON and written to the `outbox_events` table **in the same database transaction** as the business mutation.
2. The CLI worker daemon `bin/outbox-worker.php` polls `outbox_events` where `status = 'pending'`:
   ```powershell
   php bin/outbox-worker.php
   ```
3. Dispatches notifications to the Ratchet WebSocket server and marks outbox records as `processed`.

---

## 14. Data Structures and Algorithms (DSA)

### 14.1 Academic Demonstration Framework

The system includes custom implementations of classic data structures and algorithms written **natively in PHP** without relying on built-in language shortcuts (such as `sort()`, `array_shift()`, or SQL clauses):
- High-resolution timing via `hrtime(true)` (nanosecond precision).
- Operation counting tracking comparisons, swaps, and recursive calls via `App\Dsa\Benchmarks\OperationCounter`.

### 14.2 Custom In-Memory Data Structures

Located in [`app/dsa/structures/`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/dsa/structures/):
1. **`ArrayWrapper`:** Encapsulates fixed-capacity sequential indexing, linear scans, and element shifts.
2. **`LinkedList`:** Singly linked list of nodes (`Node`) with pointer references (`$next`), demonstrating traversal and dynamic insertion.
3. **`Stack`:** Last-In-First-Out (LIFO) stack demonstrating push, pop, and peek operations. Used in the UI for the recent user activity stream.
4. **`Queue`:** First-In-First-Out (FIFO) queue utilizing front and rear pointer indices to achieve true amortized $O(1)$ enqueue and dequeue operations without PHP `array_shift()` re-indexing overhead. Used in the leave review queue.
5. **`HashTable`:** Associative key-value store implementing polynomial rolling hash codes and **separate chaining** via linked bucket lists to demonstrate collision resolution.
6. **`BinarySearchTree`:** Node-based binary tree (`$left`, `$right`) supporting insert, search, and in-order, pre-order, and post-order traversals.

### 14.3 Custom Sorting and Searching Algorithms

Located in [`app/dsa/algorithms/`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/dsa/algorithms/):
- **Bubble Sort:** Iterative adjacent element comparisons and swaps with early termination flag.
- **Selection Sort:** Minimum element selection and placement into sorted prefix.
- **Insertion Sort:** Incremental placement into sorted sub-array.
- **Merge Sort:** Divide-and-conquer recursive partitioning and merging.
- **Quick Sort:** In-place partitioning using median-of-three or pivot selection.
- **Linear Search:** Sequential scanning through elements ($O(N)$).
- **Binary Search:** Iterative / recursive interval halving on sorted data ($O(\log N)$).

### 14.4 Big-O Algorithmic Complexity Reference

| Algorithm / Structure | Best Time | Average Time | Worst Time | Space Complexity |
|---|:---:|:---:|:---:|:---:|
| **Bubble Sort** | $O(N)$ | $O(N^2)$ | $O(N^2)$ | $O(1)$ |
| **Selection Sort** | $O(N^2)$ | $O(N^2)$ | $O(N^2)$ | $O(1)$ |
| **Insertion Sort** | $O(N)$ | $O(N^2)$ | $O(N^2)$ | $O(1)$ |
| **Merge Sort** | $O(N \log N)$ | $O(N \log N)$ | $O(N \log N)$ | $O(N)$ |
| **Quick Sort** | $O(N \log N)$ | $O(N \log N)$ | $O(N^2)$ | $O(\log N)$ |
| **Linear Search** | $O(1)$ | $O(N)$ | $O(N)$ | $O(1)$ |
| **Binary Search** | $O(1)$ | $O(\log N)$ | $O(\log N)$ | $O(1)$ |
| **Queue (Enqueue/Dequeue)** | $O(1)$ | $O(1)$ | $O(1)$ | $O(N)$ |
| **Stack (Push/Pop)** | $O(1)$ | $O(1)$ | $O(1)$ | $O(N)$ |
| **Hash Table (Lookup)** | $O(1)$ | $O(1)$ | $O(N)$ | $O(N)$ |
| **BST (Search/Insert)** | $O(\log N)$ | $O(\log N)$ | $O(N)$ | $O(N)$ |

### 14.5 Interactive Laboratory & Benchmarking Engine

- **Route:** `/dsa`
- **Benchmarking CLI:** `php benchmarks/run.php`
- Synthetically generates random employee datasets ($N = 10, 100, 500, 1000, 5000, 10000$) and executes comparative sort/search sweeps, outputting Markdown comparison tables and JSON benchmark files.

---

## 15. Backend Testing with PHPUnit

### 15.1 Testing Architecture (357 Tests, 1399 Assertions)

The backend testing suite is decoupled from the UI automation layer and runs on PHPUnit 13.

### 15.2 Test Suites Breakdown (Unit, Integration, Feature)

Defined in [`phpunit.xml`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/phpunit.xml):
- **`Unit` (`tests/Unit/`):** Pure in-memory unit tests covering DSA structures, algorithms, working day calculation, employee validation, and event dispatching.
- **`Integration` (`tests/Integration/`):** Real database interaction tests validating transactional leave balance deduction, row locks, outbox persistence, and OTP hashing.
- **`Feature` (`tests/Feature/`):** Full HTTP controller lifecycle tests verifying CSRF tokens, login flows, 2-role RBAC enforcement, and REST API endpoints.

### 15.3 Executing Backend Test Suites

```powershell
# Run the complete test suite:
vendor/bin/phpunit

# Run Unit tests only:
vendor/bin/phpunit --testsuite Unit

# Run Integration tests only:
vendor/bin/phpunit --testsuite Integration

# Run Feature tests only:
vendor/bin/phpunit --testsuite Feature
```

---

## 16. UI Testing with Selenium and pytest

### 16.1 UI Automation Framework Architecture

Located in [`e2e-tests/`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/e2e-tests/):
- **Engine:** Python 3.11+ with Selenium WebDriver 4 and pytest.
- **Primary Browser:** Brave Browser on Windows 11.
- **Design Pattern:** Page Object Model (POM).
- **Decoupling:** Completely isolated from the PHP application codebase; interacts solely over HTTP/HTML.

### 16.2 Page Object Model (POM) Design

Located in [`e2e-tests/pages/`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/e2e-tests/pages/):
- [`BasePage`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/e2e-tests/pages/base_page.py): Explicit wait abstractions (`find_visible`, `click`, `type`, `find_all` with transition retries).
- [`LoginPage`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/e2e-tests/pages/login_page.py): Email/password inputs, error banners, password visibility toggle.
- [`OtpPage`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/e2e-tests/pages/otp_page.py): Split 6-digit input handling, resend cooldown.
- [`HrDashboardPage`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/e2e-tests/pages/hr_dashboard_page.py): HR navigation, metrics overview cards.
- [`EmployeeDashboardPage`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/e2e-tests/pages/employee_dashboard_page.py): Leave balance cards, personal request stream.
- [`EmployeeManagementPage`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/e2e-tests/pages/employee_management_page.py): Search filtering, code badges counting, employee registration modal.
- [`DepartmentPage`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/e2e-tests/pages/department_page.py): Department directory, position alignment.
- [`LeaveManagementPage`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/e2e-tests/pages/leave_management_page.py): Leave filing form, review queue, approval action.

### 16.3 2FA Authentication Orchestration

Implemented in [`e2e-tests/utils/auth_helper.py`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/e2e-tests/utils/auth_helper.py):
- Connects directly to the test MySQL database to retrieve the active OTP challenge for the logging-in user.
- Employs application-aligned timestamps (`DATE_ADD(created_at, INTERVAL 15 MINUTE)`) to eliminate UTC/Asia:Manila container clock drift.
- Zero backdoors: Uses genuine UI inputs and OTP challenge verification.

### 16.4 Pytest Configuration and Execution Commands

```powershell
# Navigate to e2e directory:
cd e2e-tests

# Activate Python virtual environment:
.\.venv\Scripts\Activate.ps1

# Run smoke tests in headless mode (Brave):
pytest tests/ -m smoke --headless

# Run tests in interactive live mode (watch Brave open and click):
pytest tests/test_login.py

# Run all 29 UI tests with HTML report:
pytest tests/ -v --html=reports/report.html --self-contained-html
```

---

## 17. Brave Browser Configuration

### 17.1 Brave as Primary Local Browser (Windows 11)

Brave Browser is the user's default browser and is configured as the **primary local browser** for Windows 11 Selenium automation.

### 17.2 Executable Auto-Discovery Mechanism

Implemented in [`e2e-tests/config/browser_factory.py`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/e2e-tests/config/browser_factory.py):
The factory discovers the Brave binary by inspecting:
1. `BRAVE_BINARY` environment variable override.
2. `%LOCALAPPDATA%\BraveSoftware\Brave-Browser\Application\brave.exe`
3. `C:\Program Files\BraveSoftware\Brave-Browser\Application\brave.exe`
4. `C:\Program Files (x86)\BraveSoftware\Brave-Browser\Application\brave.exe`
5. Linux binary paths (`/usr/bin/brave-browser`).

### 17.3 Temporary Profile Sandboxing and Isolation

To prevent automation from interfering with the user's personal bookmarks, cookies, saved passwords, or extensions:
- Every test launches with a fresh temporary profile created via `tempfile.mkdtemp(prefix="brave_test_profile_")`.
- Passed to the browser via `--user-data-dir`.
- On test teardown, the temporary directory is automatically purged.

### 17.4 ChromeDriver Compatibility and Selenium Manager

Selenium Manager automatically resolves the exact compatible ChromeDriver binary corresponding to the installed Brave Chromium engine version, eliminating manual driver downloads.

---

## 18. Docker and Container Infrastructure

### 18.1 Multi-Container Compose Stack

Configured in [`docker-compose.yml`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docker-compose.yml):
- **`hris_nginx`:** Edge reverse proxy and WebSocket router.
- **`hris_app`:** Apache 2.4 + PHP 8.5 application server.
- **`hris_realtime`:** Ratchet WebSocket push daemon.
- **`hris_db`:** MySQL 8.0 database.
- **`hris_phpmyadmin`:** Web-based database management.
- **`hris_prometheus`:** Metric collection server.
- **`hris_grafana`:** Visual telemetry dashboards.
- **`hris_mysqld_exporter`:** MySQL performance exporter.
- **`hris_nginx_exporter`:** Nginx connection and request exporter.
- **`hris_swagger_ui`:** Dedicated Swagger UI container.

### 18.2 Port Bindings and Security Isolation

| Service | Container Name | Internal Port | Host Port Binding |
|---|---|:---:|:---:|
| Nginx Edge Proxy | `hris_nginx` | `80` | `8080:80` |
| PHP Application | `hris_app` | `80` | *None (Internal only)* |
| Realtime WebSocket | `hris_realtime`| `8090` | *None (Internal only)* |
| MySQL Database | `hris_db` | `3306` | `3307:3306` |
| phpMyAdmin | `hris_phpmyadmin`| `80` | `8081:80` |
| Swagger UI | `hris_swagger_ui`| `8080` | `8083:8080` |
| Prometheus | `hris_prometheus`| `9090` | `9090:9090` |
| Grafana | `hris_grafana` | `3000` | `3100:3000` |

### 18.3 Persistent Volumes and Data Retention

Database data is stored in the Docker named volume `hris_db_data`, surviving container restarts and upgrades. Destructive commands like `docker compose down -v` are strictly prohibited.

### 18.4 Dedicated E2E Test Stack (`docker-compose.e2e.yml`)

Provides an isolated Selenium Standalone Chrome container (`:4444`, `:7900`) and Mailpit server (`:8025`, `:1025`) for isolated containerized UI testing without host dependencies.

---

## 19. Nginx Configuration

### 19.1 Edge Reverse Proxy Architecture

Configured in [`docker/nginx/default.conf`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docker/nginx/default.conf):
- Absorbs client connections on port 8080.
- Enforces HTTP 1.1 keepalives to upstream Apache (`app:80`).
- Serves private telemetry on port 8082 (`/stub_status`).

### 19.2 WebSocket Protocol Upgrade Configuration (`/ws`)

Routes live notifications directly to Ratchet:
```nginx
location = /ws {
    proxy_pass http://realtime:8090;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_read_timeout 60s;
}
```

### 19.3 Security Headers and Resource Denial Rules

Blocks attempts to download hidden files and security assets:
```nginx
location ~ /\.(env|git|htaccess|sql) {
    deny all;
    return 404;
}
```

---

## 20. Monitoring and Observability

### 20.1 Prometheus Architecture and Scrape Targets

Configured in [`docker/prometheus/prometheus.yml`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docker/prometheus/prometheus.yml):
- Scrapes `nginx-exporter:9113` every 15s.
- Scrapes `mysqld-exporter:9104` every 15s.
- Scrapes application telemetry endpoints every 30s.

### 20.2 Specialized Exporters (`mysqld-exporter`, `nginx-exporter`)

- **`mysqld-exporter`:** Exposes query throughput, InnoDB buffer pool hit ratios, lock waits, and active connections.
- **`nginx-exporter`:** Exposes accepted connections, active reading/writing/waiting states, and request rates.

### 20.3 Grafana Dashboards and Visualizations

Pre-provisioned dashboards on port `3100`:
- **Nginx Operational Metrics:** Request rates, HTTP status distribution, active connections.
- **MySQL Database Health:** Queries per second, InnoDB dirty pages, table lock waits, network I/O.

### 20.4 System Health & Telemetry Endpoints

- `GET /health`: Returns operational JSON payload including PHP version, database ping latency, and memory consumption.
- `GET /api/v1/health`: Clean unauthenticated operational check for container healthchecks and load balancers.

---

## 21. Terraform and Infrastructure as Code

### 21.1 Infrastructure as Code Architecture

Terraform introduces declarative lifecycle management to the containerized environment using provider `kreuzwerker/docker` (`~> 3.0.2`).

### 21.2 Modular Layout (`network`, `database`, `app`, `monitoring`)

Located in [`terraform/modules/`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/terraform/modules/):
- **`modules/network`:** User-defined bridge network `hris_network`.
- **`modules/database`:** MySQL 8.0 container and `hris_db_data` volume.
- **`modules/app`:** Apache/PHP application container and Nginx edge proxy.
- **`modules/monitoring`:** Prometheus, Grafana, and exporter containers.

### 21.3 Multi-Environment Provisioning (`dev`, `prod`)

Environments are declared in `terraform/environments/dev/` and `terraform/environments/prod/`, isolating state files.

### 21.4 State Tracking, Locking, and Drift Detection

```powershell
cd terraform/environments/dev
terraform init
terraform plan
terraform apply
```

---

## 22. GitHub Actions and CI/CD

### 22.1 CI/CD Workflows Directory

All workflows reside in [`.github/workflows/`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/.github/workflows/):

| Workflow File | Trigger Events | Purpose |
|---|---|---|
| `unit-tests.yml` | `push`, `pull_request` | PHPUnit, PHPCS (PSR-12), PHPStan Level 5+ |
| `e2e-tests.yml` | `push`, `pull_request` | Ephemeral MySQL, built-in PHP 8.5 server, Headless Chrome Selenium |
| `terraform.yml` | `push`, `pull_request` | `terraform fmt`, `terraform validate`, TFLint |
| `docker.yml` | `push`, `pull_request` | Hadolint Dockerfile linting, container build check |
| `jira.yml` | `push`, `pull_request` | Jira issue branch transition automation |

### 22.2 PHPUnit & Static Analysis Pipeline (`unit-tests.yml`)

1. Sets up PHP 8.5 with required extensions.
2. Runs `composer install`.
3. Runs `vendor/bin/phpcs` (PSR-12).
4. Runs `vendor/bin/phpstan` (Level 5+).
5. Runs `vendor/bin/phpunit` across all 357 tests.

### 22.3 Selenium E2E Test Suite Pipeline (`e2e-tests.yml`)

1. Provisions ephemeral MySQL 8.0 service container.
2. Applies schema, migrations, and seed data.
3. Starts built-in PHP application server: `php -S 127.0.0.1:8080 -t public/`.
4. Executes 29 headless Chrome Selenium tests via pytest.
5. Uploads `e2e-tests/reports/report.html` and screenshots as GitHub workflow artifacts.

### 22.4 Terraform Validation Pipeline (`terraform.yml`)

Validates HCL formatting and schema validity across all environments.

### 22.5 Docker Build & Hadolint Pipeline (`docker.yml`)

Lints `Dockerfile` with Hadolint and builds the application image.

---

## 23. Security Guidelines

### 23.1 Password Hashing and Storage Standards

- Standard: Native PHP `password_hash()` using `PASSWORD_BCRYPT` with cost factor 12.
- Verified with `password_verify()` against timing attacks.

### 23.2 Session Hardening and Inactivity Deadlines

- `session.use_strict_mode = 1`, `session.use_only_cookies = 1`.
- `HttpOnly = true`, `SameSite = Lax`.
- Inactivity timeout: 30 minutes (1800s). Background polling includes `X-HRIS-Background: 1` to prevent unauthorized session extension.

### 23.3 Cross-Site Request Forgery (CSRF) Mitigation

- CSRF tokens generated via `bin2hex(random_bytes(32))`.
- Validated on all state-changing verbs (`POST`, `PUT`, `DELETE`) using `hash_equals()`.

### 23.4 SQL Injection and XSS Defenses

- **SQLi:** Zero dynamic SQL string concatenation. All database queries use parameterized PDO prepared statements.
- **XSS:** All view output escaped through `e()` helper wrapping `htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.

### 23.5 Zero Backdoors & Hardcoded Credential Protections

- No demo bypass logins or backdoor authentication routes.
- Real OTP challenge validation strictly required.

---

## 24. Logging and Notifications

### 24.1 Immutable Activity Audit Trail

Logged to `activity_logs`:
- Authentication logins, logouts, and failed attempts.
- Employee creation, modification, and deactivation.
- Department and position changes.
- Leave filing, approval, rejection, and cancellation.

### 24.2 In-System Notification Center

- Rendered via `/notifications`.
- Increments unread badge counter in navbar and sidebar.
- Bulk mark-as-read and individual read state transitions.

### 24.3 Real-Time WebSocket Push Architecture (Ratchet)

- Driven by `bin/realtime-server.php`.
- Single-use 30s tickets issued by `POST /realtime/ticket`.
- Client connects to `ws://localhost:8080/ws` and pushes real-time event alerts.

---

## 25. Database Backup and Recovery

### 25.1 Daily Logical Backup Automation (`backup-db.sh`)

Located at [`scripts/backup/backup-db.sh`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/scripts/backup/backup-db.sh):
- Executes `mysqldump` with `--single-transaction --quick --routines --triggers`.
- Streams output directly to Gzip.

Execute manual backup:
```bash
./scripts/backup/backup-db.sh
```

### 25.2 SHA-256 Checksum Validation & Retention Standards

- Every backup generates a matching `.sha256` integrity manifest.
- Automatic rotation prunes backups older than 14 days.

### 25.3 Disaster Recovery and Restoration (`restore-db.sh`)

Located at [`scripts/restore/restore-db.sh`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/scripts/restore/restore-db.sh):
- Verifies SHA-256 checksum before execution.
- Restores data in a transaction, halting on errors.

### 25.4 Point-in-Time Recovery (PITR) with Binary Logs

Enables replaying transactions up to a specific timestamp:
```bash
mysqlbinlog --start-datetime="2026-10-31 02:00:00" --stop-datetime="2026-10-31 09:30:00" mysql-bin.000001 | mysql -u root -p hris_db
```

---

## 26. Development Workflow

### 26.1 Branching Convention (`HRIS-*`)

- Feature and task branches are named following Jira issue keys: `HRIS-{issue_number}-{short_description}` (e.g., `HRIS-39-setup-ui-testing`).
- Main integration branches: `development` and `main`.

### 26.2 Jira Issue Synchronization

Branch commits referencing Jira issue keys trigger automated status transitions in Jira via `.github/workflows/jira.yml`.

### 26.3 Pull Request Protocol and Review Criteria

- All CI checks (`unit-tests`, `e2e-tests`, `terraform`, `docker`) must pass.
- Zero regression in PHPUnit or Selenium test suites.
- Code must adhere to PSR-12 and pass PHPStan analysis.

---

## 27. Coding Standards

### 27.1 PSR-12 Coding Style Compliance

Enforced by PHP_CodeSniffer:
```powershell
vendor/bin/phpcs --standard=PSR12 app/ core/
```

### 27.2 Static Analysis Standards (PHPStan Level 5+)

Enforced by PHPStan:
```powershell
vendor/bin/phpstan analyse -l 5 app/ core/
```

### 27.3 Formatting and Shell Script Linter (Actionlint / Shellcheck)

GitHub Actions workflow files are validated locally using Actionlint:
```powershell
docker run --rm -v ${PWD}:/repo -w /repo rhysd/actionlint:latest
```

---

## 28. Troubleshooting

### 28.1 Web Gateway & HTTP 502 Bad Gateway

- **Cause:** Upstream `hris_app` is not running or crashed.
- **Resolution:**
  ```powershell
  docker compose ps app
  docker compose logs --tail=50 app
  docker compose restart app
  ```

### 28.2 MySQL Database Connection Errors

- **Cause:** MySQL container `hris_db` is still initializing or healthy check has not completed.
- **Resolution:** Verify container health:
  ```powershell
  docker inspect --format='{{json .State.Health.Status}}' hris_db
  ```

### 28.3 Port Binding Conflicts (3306, 3307, 8080)

- **Cause:** Local service (e.g., local XAMPP MySQL or another web server) is occupying port 3307 or 8080.
- **Resolution:** Check listening ports via `netstat -ano | findstr :3307` and update `DB_PORT` in `.env`.

### 28.4 Email OTP Delivery and Timezone Synchronization

- **Symptom:** "Verification code has expired."
- **Cause:** Timezone mismatch between PHP (`Asia/Manila`, UTC+8) and MySQL container (UTC).
- **Resolution:** The system uses relative intervals (`DATE_ADD(created_at, INTERVAL 15 MINUTE)`) to eliminate cross-timezone drift.

### 28.5 Selenium Renderer Timeouts & Stale Reference Resolution

- **Cause:** Aggressive command timeouts during renderer navigation with `page_load_strategy = "eager"`.
- **Resolution:** Handled automatically by [`BasePage::find_all()`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/e2e-tests/pages/base_page.py) retry loops and URL synchronization (`wait_for_url_contains`).

### 28.6 Brave Browser Driver Resolution Issues

- **Cause:** Selenium Manager cannot locate Brave executable.
- **Resolution:** Specify `BRAVE_BINARY` explicitly in `e2e-tests/.env`:
  ```ini
  BRAVE_BINARY=C:\Program Files\BraveSoftware\Brave-Browser\Application\brave.exe
  ```

---

## 29. Deployment and Operations

### 29.1 Production Readiness Checklist

1. Set `APP_ENV=production` in `.env`.
2. Disable detailed error display (`display_errors = Off`, `log_errors = On`).
3. Enforce TLS/HTTPS termination at Nginx.
4. Set strong database credentials and restrict MySQL host port binding.
5. Generate production OpenAPI assets (`composer swagger:generate`).
6. Pre-warm autoloader: `composer install --optimize-autoloader --no-dev`.

### 29.2 Nginx & Apache Server Hardening

- Hide server signatures: `server_tokens off;` in Nginx, `ServerTokens Prod` in Apache.
- Enable HTTP Strict Transport Security (HSTS) and X-Frame-Options (`SAMEORIGIN`).

### 29.3 Automated Smoke Testing (`smoke-test.sh`)

Located at [`scripts/deploy/smoke-test.sh`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/scripts/deploy/smoke-test.sh):
```bash
./scripts/deploy/smoke-test.sh http://localhost:8080
```

### 29.4 Future VPS Provisioning Roadmap

Detailed in `docs/devops/vps-provisioning.md` and `docs/devops/future-vps-deployment.md`:
- Automated deployment to Linux VPS (Ubuntu 24.04 LTS).
- Systemd unit management for outbox worker and Ratchet server.
- Automated Certbot Let's Encrypt TLS certificate renewal.

---

## 30. Known Limitations

### 30.1 Out-of-Scope Enterprise Modules

As an academic software project and targeted HRIS system, payroll computation, tax generation, and biometric hardware integrations are deliberately excluded.

### 30.2 Working-Day Calendar Constraints

The business-day engine currently accounts for Monday through Friday, omitting Saturdays and Sundays. It does not dynamically load statutory national public holidays.

### 30.3 Single-Tier Approval Chains

Leave requests are directly reviewed and approved/rejected by HR Officers. Multi-tier approval workflows (Team Lead $\to$ Manager $\to$ HR) are not implemented.

---

## 31. Future Improvements

### 31.1 Dynamic Holiday Management Integration

Add a `holidays` database table and HR management interface, enabling the working-day calculation service to exclude national and company non-working holidays automatically.

### 31.2 Hardware TOTP Authenticator Support (RFC 6238)

Complete integration of `App\Services\TotpService` with QR code provisioning for Google Authenticator / Microsoft Authenticator, providing an alternative to Email OTP.

### 31.3 Multi-Tier Hierarchical Approval Workflows

Extend the leave approval workflow to support multi-stage approval hierarchies based on employee reporting lines.

---
*Document compiled and consolidated from authoritative system specifications, codebase implementations, and verification logs.*

