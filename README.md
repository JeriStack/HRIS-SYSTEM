# Employee HRIS & Leave Management System

[![PHP Version](https://img.shields.io/badge/PHP-8.2%20--%208.5-blue.svg)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL%208.0-orange.svg)](https://www.mysql.com/)
[![Architecture](https://img.shields.io/badge/Architecture-Custom%20MVC%20%7C%202--Role%20RBAC-green.svg)]()
[![Backend Tests](https://img.shields.io/badge/PHPUnit-357%20Passed%20(100%25)-brightgreen.svg)]()
[![UI Tests](https://img.shields.io/badge/Selenium%20E2E-29%20Passed%20(Brave%20%7C%20Chrome)-success.svg)]()

> A robust, production-ready Human Resource Information and Leave Management System built with native PHP and MySQL, featuring an integrated Computer Science Data Structures & Algorithms (DSA) laboratory, real-time WebSocket notifications, and automated Selenium end-to-end testing with Brave Browser.

📖 **For complete technical specifications, architectural decisions, and operational guides, see [PROJECT_DOCUMENTATION.md](PROJECT_DOCUMENTATION.md).**

---

## 1. Project Overview

The **Employee HRIS & Leave Management System** fulfills a dual mandate:

1. **Operational Enterprise HRIS:** Centralized management of employee master records, organizational departments, job positions, annual leave quotas, transactional leave approvals, audit logging, and reporting under a strict **2-Role Architecture (Human Resources & Employee Self-Service)**.
2. **Academic Computer Science Laboratory:** An interactive workbench featuring native PHP implementations of classic sorting algorithms (Bubble, Selection, Insertion, Merge, Quick sorts), searching algorithms (Linear, Binary search), and data structures (Array Wrapper, Singly Linked List, Stack, Queue, Hash Table, Binary Search Tree) benchmarked across dataset sizes ($N = 10$ to $10,000$).

---

## 2. Main Features

- **Strict 2-Role RBAC:** Mutually exclusive portals for Human Resources (`hr`) and Employees (`employee`).
- **Two-Factor Email OTP:** Mandatory 6-digit numeric OTP authentication via PHPMailer SMTP with single-use atomic consumption and rate-limiting.
- **Working-Day Leave Engine:** Automatic business-day calculation (Monday through Friday, excluding weekends) with date collision detection.
- **ACID Leave Approvals:** First-In-First-Out (FIFO) review queue with pessimistic row locking (`SELECT ... FOR UPDATE`) preventing balance overdrafts.
- **Real-Time Push Notifications:** Low-latency alert dispatch powered by Ratchet WebSockets (`/ws`) and a Transactional Outbox pattern.
- **Native DSA Workbench:** In-memory algorithm benchmarking tracking execution time (`hrtime`), memory usage, and operation counts.
- **RESTful API & Swagger UI:** OpenAPI 3.0 specification with interactive Swagger UI exploration portal.
- **Brave Browser Selenium Testing:** Automated UI test suite targeting Brave Browser on Windows 11 with temporary profile sandboxing and CI support.

---

## 3. Technology Stack

- **Backend:** PHP 8.5 (compatible with 8.2–8.5), Apache 2.4, Ratchet WebSocket Server (`cboden/ratchet`).
- **Database:** MySQL 8.0 Community Server (InnoDB storage engine, `utf8mb4_unicode_ci`).
- **Edge Reverse Proxy:** Nginx 1.25 Alpine (handles HTTP `:8080` and WebSocket `:8090`).
- **Monitoring & Telemetry:** Prometheus (`:9090`), Grafana (`:3100`), `mysqld-exporter`, `nginx-exporter`.
- **Infrastructure as Code:** Terraform 1.16+ (`kreuzwerker/docker`).
- **Testing:** PHPUnit 13 (357 backend tests), Selenium WebDriver 4 + pytest (29 UI tests).

---

## 4. Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) $\ge 4.20$ (WSL2 backend on Windows 11).
- [Composer](https://getcomposer.org/) $\ge 2.2$.
- [Brave Browser](https://brave.com/) (for local Selenium UI automation on Windows 11).
- Python 3.10+ (for running Selenium test suites).

---

## 5. Quick-Start Guide (Docker)

The fastest and recommended way to run the entire HRIS platform is using Docker Compose:

```bash
# 1. Clone the repository
git clone https://github.com/JeriStack/HRIS-SYSTEM.git
cd HRIS-SYSTEM

# 2. Start the containerized stack
docker compose up -d

# 3. Run database migrations (inside app container)
docker compose exec app php bin/migrate.php
```

### Accessing Services

| Interface | URL | Default Credentials |
|---|---|---|
| **HRIS Web Application** | [http://localhost:8080](http://localhost:8080) | See pre-seeded accounts below |
| **phpMyAdmin** | [http://localhost:8081](http://localhost:8081) | Host: `db`, User: `root`, Pass: `root_password` |
| **Swagger UI Documentation** | [http://localhost:8083](http://localhost:8083) | *No authentication required* |
| **Prometheus Telemetry** | [http://localhost:9090](http://localhost:9090) | *No authentication required* |
| **Grafana Dashboards** | [http://localhost:3100](http://localhost:3100) | User: `admin` / Password: `admin` |
| **Mailpit (Local Email Capture)** | [http://localhost:8025](http://localhost:8025) | *Inspect OTPs during testing* |

### Pre-Seeded Test Accounts

| Account Email | Role | Password | Target Dashboard |
|---|---|---|---|
| `marcus.tan@company.local` | Human Resources (`hr`) | `Admin@123` | `/hr/dashboard` |
| `sofia.reyes@company.local` | Human Resources (`hr`) | `Admin@123` | `/hr/dashboard` |
| `david.cruz@company.local` | Employee (`employee`) | `Employee@123` | `/employee/dashboard` |
| `maria.santos@company.local` | Employee (`employee`) | `Employee@123` | `/employee/dashboard` |

*Note: During 2FA login, check the Mailpit interface ([http://localhost:8025](http://localhost:8025)) to retrieve the dispatched 6-digit OTP.*

---

## 6. Running Tests

### Backend Testing (PHPUnit)

The project includes 357 passing backend tests covering unit, database integration, and HTTP feature scenarios:

```bash
# Run all PHPUnit test suites:
vendor/bin/phpunit

# Run unit tests only:
vendor/bin/phpunit --testsuite Unit

# Run integration tests only:
vendor/bin/phpunit --testsuite Integration
```

### UI & End-to-End Automation (Selenium & pytest)

The UI test suite uses Selenium WebDriver 4 and pytest, configured for Brave Browser on Windows 11:

```powershell
# Navigate to e2e directory:
cd e2e-tests

# Set up Python virtual environment (first time):
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
cp .env.example .env

# Run smoke tests in headless Brave:
pytest tests/ -m smoke --headless

# Run tests interactively (watch Brave launch and interact):
pytest tests/test_login.py

# Run full UI automation suite with HTML report:
pytest tests/ -v --html=reports/report.html --self-contained-html
```

---

## 7. Project Documentation

For comprehensive technical documentation, refer to:

👉 **[PROJECT_DOCUMENTATION.md](PROJECT_DOCUMENTATION.md)**

### Documentation Contents
- **System Requirements & Business Rules:** Detailed leave policies, overlap checks, working-day calculations.
- **Relational Database Design:** Complete data dictionary for all 13 tables, ER diagrams, foreign key policies.
- **Authentication & Security:** 2FA OTP mechanics, session hardening, CSRF defense, IDOR protection.
- **Domain-Driven Design (DDD):** Aggregates, domain events, transactional outbox pattern.
- **Data Structures & Algorithms:** Implementation details, complexity tables, benchmarking engine.
- **DevOps & Infrastructure:** Docker topology, Nginx reverse proxy, Prometheus/Grafana, Terraform IaC.
- **Disaster Recovery:** Daily automated backups (`scripts/backup/backup-db.sh`), PITR, and restoration.
- **Troubleshooting:** Solutions for 502 Bad Gateway, database connection issues, and Selenium timeouts.

---

## 8. License & Governance

This project is proprietary and maintained for enterprise human resource operations and academic demonstrations. All rights reserved.
