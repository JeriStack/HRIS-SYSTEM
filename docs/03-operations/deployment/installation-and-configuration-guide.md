# NEXORA HR — Complete Installation, Configuration & Deployment Runbook

**Project:** NEXORA HR — Human Resource Information System  
**Tagline:** People. Processes. Progress.  
**Phase:** 16.4 — Installation and Deployment Deliverable  
**Implementation Status:** Verified on Windows 11 (XAMPP & Docker Desktop) and Ubuntu 24.04 LTS (Docker Compose & Native VPS)

---

## 1. Verified System Requirements

| Requirement | Minimum / Verified Specification | Purpose |
| :--- | :--- | :--- |
| **Operating System** | Windows 11 (64-bit), macOS 13+, or Ubuntu 22.04 / 24.04 LTS | Host operating system |
| **PHP Runtime** | PHP `8.2` – `8.5` (Verified on **PHP `8.5.12`**) | Core application, WebSocket daemon, Queue Worker, and CLI Kernel |
| **Required PHP Extensions** | `pdo_mysql`, `mbstring`, `openssl`, `json`, `ctype`, `filter`, `sockets` | Database connectivity, AES-256-GCM encryption, HMAC-SHA256 OTP peppering, Ratchet WebSockets |
| **Dependency Manager** | Composer `2.6+` | PSR-4 autoloading and library management (`phpmailer`, `phpdotenv`, `ratchet`, `swagger-php`) |
| **Database Engine** | MySQL `8.0+` (Verified on **MySQL `8.0.46`** with `InnoDB` & `utf8mb4_unicode_ci`) | Transactional persistence, row-level locking (`SELECT ... FOR UPDATE`), Outbox & Jobs queue |
| **Container Runtime (Recommended)** | Docker Engine `24.0+` & Docker Compose `v2.20+` | 10-container orchestrated stack (`docker-compose.yml`) |
| **Version Control** | Git `2.40+` | Source control and CI hooks |
| **Web Browser** | Brave Browser, Google Chrome, Microsoft Edge, or Firefox (modern ES6+ & WebSocket support) | Client UI & Selenium E2E automation |

---

## 2. Option A: Docker Compose Installation (Recommended)

The `docker-compose.yml` file orchestrates all **10 services** (`nginx`, `app`, `realtime`, `scheduler`, `worker`, `db`, `phpmyadmin`, `nginx-exporter`, `mysqld-exporter`, `prometheus`, `grafana`, `swagger-ui`, `mailpit`) on an isolated bridge network (`hris_network`).

### Step 1: Clone the Repository & Prepare Environment
```bash
git clone https://github.com/JeriStack/HRIS-SYSTEM.git
cd HRIS-SYSTEM
cp .env.example .env
```

Generate a cryptographic `OTP_PEPPER_SECRET` (minimum 16 characters) and set it in `.env`:
```bash
php -r "echo 'OTP_PEPPER_SECRET=' . bin2hex(random_bytes(32)) . PHP_EOL;"
```

### Step 2: Build and Start the Multi-Container Stack
```bash
docker compose up -d --build
```

Verify all containers are healthy:
```bash
docker compose ps
```

### Step 3: Initialize Database Schema & Run Migrations
On first startup, `hris_db` automatically executes `docker/mysql/init/` scripts. Run the idempotent migration runner to ensure all incremental migrations in `database/migrations/` are applied:
```bash
docker compose exec app php bin/migrate.php
```

### Step 4: Provision the Initial Administrator Securely (CLI Only)
Public Administrator registration is prohibited. Provision the initial Administrator account via the CLI utility (`bin/create_admin_user.php`):
```bash
docker compose exec app php bin/create_admin_user.php \
  --email="admin@company.local" \
  --username="admin.nexora" \
  --first-name="System" \
  --last-name="Administrator" \
  --password="ChangeMeImmediately!2026"
```
*(If an active Administrator already exists and you wish to provision an additional one via CLI, append `--force`.)*

### Step 5: Access Application & Supporting Services
- **NEXORA HR Web Application:** `http://localhost:8080`
- **Mailpit (Local 2FA OTP Inbox):** `http://localhost:8025`
- **Swagger UI (OpenAPI 3.0 Explorer):** `http://localhost:8083` (or `http://localhost:8080/docs`)
- **phpMyAdmin:** `http://localhost:8081`
- **Prometheus Telemetry:** `http://localhost:9090`
- **Grafana Monitoring Dashboards:** `http://localhost:3100`

---

## 3. Option B: Native Windows (XAMPP) / Linux Host Installation

### Step 1: Install Composer Dependencies
```bash
composer install
```

### Step 2: Configure `.env` for Local MySQL
Copy `.env.example` to `.env` and configure your local MySQL connection and `BASE_URL`:
```ini
APP_NAME="NEXORA HR"
APP_ENV=development
BASE_URL="http://localhost/GitHub_Clone_File_Projects/HRIS-SYSTEM/public"
TIMEZONE="Asia/Manila"
SESSION_LIFETIME=1800

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hris_db
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4

OTP_PEPPER_SECRET=replace_with_64_hex_chars_from_random_bytes
```

### Step 3: Create the Database, Import Schema & Run Migrations
```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS hris_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root hris_db < database/schema.sql
php bin/migrate.php
```

### Step 4: Provision the Initial Administrator
```bash
php bin/create_admin_user.php
```

### Step 5: Start Background Daemons (WebSocket, Queue Worker & Scheduler)
In separate terminal sessions (or managed via `systemd` / Windows Task Scheduler):
```bash
# 1. Start Ratchet Real-Time WebSocket Server (:8090)
php bin/realtime-server.php

# 2. Start Asynchronous MySQL Queue Worker
php bin/queue-worker.php --sleep=2 --lease=300 --max-runtime=3600

# 3. Run Scheduled Tasks (Cron every minute in production)
php bin/console schedule:run
```

---

## 4. Running Automated Tests & Quality Gates

```bash
# 1. Initialize isolated test database (hris_test)
php bin/init-test-db.php

# 2. Run full PHPUnit test suite (669 tests)
vendor/bin/phpunit

# 3. Run PSR-12 coding standard baseline check
php bin/check-phpcs-baseline.php

# 4. Run PHPStan static analysis
vendor/bin/phpstan analyse --memory-limit=1G
```

