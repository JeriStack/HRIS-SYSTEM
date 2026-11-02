# Docker Deployment & Setup Guide

**Project:** Employee HRIS & Leave Management System  
**Stack:** PHP 8.2 (Apache) + MySQL 8.0 + phpMyAdmin  
**Architecture:** 3-Tier Containerized Stack with Isolated Bridge Network & Persistent Volumes

---

## 1. Stack Architecture Overview

The containerized stack implements a 3-service architecture matching container specifications:

```
+-------------------------------------------------------------+
|                         Your Browser                        |
|   App UI: http://localhost:8080                             |
|   Database Admin: http://localhost:8081                     |
+------------------------------+------------------------------+
                               |
                               v
+-------------------------------------------------------------+
|                     PHP + Apache Container                  |
|  - Service: `app` (Container: `hris_app`)                   |
|  - Host Port: 8080 -> Container Port: 80                    |
|  - Web Root: /var/www/html/public                           |
|  - Modules: mod_rewrite, mod_headers                        |
|  - Volume: Live code bind mount (`.:/var/www/html`)         |
+------------------------------+------------------------------+
                               |
                               v
+-------------------------------------------------------------+
|                        MySQL Container                      |
|  - Service: `db` (Container: `hris_db`)                     |
|  - Host Port: 3307 -> Container Port: 3306                  |
|    (Port 3307 prevents conflict with local XAMPP MySQL 3306)|
|  - Engine: MySQL 8.0 with InnoDB & utf8mb4                  |
|  - Persistent Volume: `hris_db_data`                        |
|  - Auto-Init: 01-schema.sql, 02-seed.sql                    |
+------------------------------+------------------------------+
                               ^
                               |
+------------------------------+------------------------------+
|                     phpMyAdmin Container                    |
|  - Service: `phpmyadmin` (Container: `hris_phpmyadmin`)     |
|  - Host Port: 8081 -> Container Port: 80                    |
|  - Browser database administration & SQL query inspection   |
+-------------------------------------------------------------+
```

---

## 2. Services Specification

| Service | Container Name | Image / Build | Host Port | Purpose |
| :--- | :--- | :--- | :--- | :--- |
| `app` | `hris_app` | Custom `Dockerfile` (`php:8.2-apache`) | `8080:80` | PHP and Apache: HRIS application, routing, controllers, views |
| `db` | `hris_db` | `mysql:8.0` | `3307:3306` | MySQL database with persistent storage for employee, user, and leave data |
| `phpmyadmin` | `hris_phpmyadmin` | `phpmyadmin:latest` | `8081:80` | Browser-based database management interface |

---

## 3. Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows with WSL2 backend or macOS / Linux)
- Docker Compose v2.0+

---

## 4. Quick Start Commands

### Step 1: Start the Entire Stack

From the project root directory, run:

```bash
docker compose up -d
```

Docker will:
1. Build the custom PHP 8.2 Apache image with `pdo_mysql`, `zip`, Apache `rewrite` and `headers` modules.
2. Pull MySQL 8.0 and phpMyAdmin images.
3. Create the `hris_network` bridge network and `hris_db_data` volume.
4. Execute `01-schema.sql` and `02-seed.sql` on first launch into `hris_db`.
5. Launch `hris_db`, wait for its healthcheck to pass, then launch `hris_app` and `hris_phpmyadmin`.

### Step 2: Verify Container Health

```bash
docker compose ps
```

Expected status:
- `hris_db`: `Up (healthy)`
- `hris_app`: `Up`
- `hris_phpmyadmin`: `Up`

### Step 3: Access the Services

- **Application Web UI:** [http://localhost:8080](http://localhost:8080)
- **phpMyAdmin UI:** [http://localhost:8081](http://localhost:8081)
- **Direct Database Host Connection:** `127.0.0.1:3307` (e.g. from DBeaver, MySQL Workbench, TablePlus)

---

## 5. Seed Accounts & Credentials

### Web Application Logins ([http://localhost:8080/login](http://localhost:8080/login))

| Role | Username | Password | Full Name / Description |
| :--- | :--- | :--- | :--- |
| **HR Admin** | `hr01` | `Admin@123` | Marcus Tan (HR Director) |
| **HR Officer** | `hr02` | `Admin@123` | Sofia Reyes (HR Officer) |
| **Employee** | `emp004` | `Employee@123` | David Cruz (Senior Software Engineer) |
| **Employee** | `emp005` | `Employee@123` | Maria Santos (Junior Software Engineer) |
| **Employee** | `emp006` | `Employee@123` | Juan Dela Cruz (Systems Administrator) |
| **Employee** | `emp007` | `Employee@123` | Beatrice Gomez (Finance Manager) |

### Database Credentials

| Scope | Host | Port | Database | Username | Password |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Internal Docker** | `db` | `3306` | `hris_db` | `hris_user` | `hris_password` |
| **Host Tools** | `127.0.0.1` | `3307` | `hris_db` | `root` | `rootpassword` |
| **phpMyAdmin** | Auto-login | - | `hris_db` | `root` / `hris_user` | `rootpassword` / `hris_password` |

---

## 6. Management & Operational Commands

### View Container Logs
```bash
# Follow logs of all services
docker compose logs -f

# Follow logs of specific service
docker compose logs -f app
docker compose logs -f db
docker compose logs -f phpmyadmin
```

### Run Commands Inside the App Container
```bash
# Open interactive shell in app container
docker compose exec app bash

# Run test suite inside container
docker compose exec app php tests/run_all_tests.php
```

### Stop the Stack
```bash
# Stop containers without removing volume data
docker compose down

# Stop containers and wipe database volume (clean reset)
docker compose down -v
```

### Rebuild After Modifying Dockerfile or Configs
```bash
docker compose up -d --build
```
