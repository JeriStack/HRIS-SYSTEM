# HRIS-SYSTEM: Local Deployment Validation Guide

**Document:** `docs/devops/local-deployment-validation.md`  
**Target Environment:** Local Windows 11 with Docker Desktop & XAMPP  
**Status:** Verified Local Baseline  

---

## 1. Overview

This document provides clear, step-by-step procedures to validate the **HRIS-SYSTEM** container stack, API endpoints, background daemons, and database transactions locally before initiating any cloud deployment.

---

## 2. Local Container Stack Validation

### 2.1 Starting the Services

Launch the complete local development stack using Docker Compose:

```bash
docker compose up -d
```

Verify that all containers are healthy:

```bash
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
```

Expected healthy services:
- `hris_app`: PHP 8.5 + Apache front controller (internal port 80).
- `hris_realtime`: Ratchet WebSocket daemon (internal port 8090, status: healthy).
- `hris_nginx`: Front reverse proxy (`0.0.0.0:8080->80/tcp`).
- `hris_db`: MySQL 8.0 InnoDB (`0.0.0.0:3307->3306/tcp`, status: healthy).
- `hris_prometheus`: Prometheus metrics engine (`0.0.0.0:9090->9090/tcp`).
- `hris_grafana`: Monitoring dashboards (`0.0.0.0:3100->3000/tcp`).
- `hris_nginx_exporter` & `hris_mysqld_exporter`: Metrics exporters.

---

## 3. Automated Local Quality & Security Gates

Execute the full verification sequence from the project root in PowerShell:

```powershell
# 1. Full PHPUnit Application Test Suite (481 tests)
php vendor/bin/phpunit

# 2. Data Structures & Algorithms Suite (65 tests)
php scratch/test_dsa_suite.php

# 3. Static Analysis (PHPStan)
vendor/bin/phpstan analyse --memory-limit 512M

# 4. PSR-12 Coding Standards (PHP_CodeSniffer)
php bin/check-phpcs-baseline.php

# 5. Infrastructure as Code Validation (Terraform)
terraform fmt -check -recursive
terraform -chdir=terraform/environments/dev validate
terraform -chdir=terraform/environments/prod validate

# 6. GitHub Actions Workflow Syntax Check (Actionlint)
docker run --rm -v "${PWD}:/repo" -w /repo rhysd/actionlint:latest
```

---

## 4. Live Endpoint Smoke Testing

Execute the automated smoke test script against your running Docker reverse proxy:

```powershell
bash scripts/deploy/smoke-test.sh http://localhost:8080
```

### Probed Endpoints & Expected Assertions

1. `GET /health` -> `HTTP 200` (JSON status `healthy`, database connected).
2. `GET /status` -> `HTTP 200` (Diagnostics alias).
3. `GET /api/v1/health` -> `HTTP 200` (REST API v1 status).
4. `GET /login` -> `HTTP 200` (HTML authentication form).
5. `GET /metrics` -> `HTTP 200` (Prometheus metrics exposition).
6. `GET /.env` -> `HTTP 403` (Blocked by Nginx security rule).

---

## 5. Validating Production Configuration Locally

You can validate production container definitions without starting a second set of containers:

```powershell
# Validates production compose syntax, volume declarations, and network isolation:
docker compose -f docker-compose.prod.yml config
```

### Production Security Boundaries Confirmed:
- `db` service does NOT publish any host ports (isolated to `hris_network`).
- `app` and `realtime` communicate internally with Nginx over private bridge network.
- Docker log drivers enforce `max-size: 50m` and `max-file: 3`.

