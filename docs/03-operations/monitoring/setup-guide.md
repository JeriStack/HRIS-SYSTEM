# Setup & Deployment Operations Guide

**Project:** Employee HRIS & Leave Management System  
**Document:** `docs/docker-monitoring/setup-guide.md`

---

## 1. Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows with WSL2 backend, Linux, or macOS)
- Docker Compose v2.20+
- Host ports available: `8080`, `8081`, `3307`, `9090`, `3100`

---

## 2. Environment Configuration

Copy the Docker configuration template if not already present:

```bash
cp .env.docker.example .env
```

Review environment defaults:
```ini
APP_NAME="Employee HRIS & Leave Management System"
APP_ENV=development
BASE_URL="http://localhost:8080"
APP_PORT=8080
DB_HOST_PORT=3307
PMA_PORT=8081
PROMETHEUS_PORT=9090
GRAFANA_PORT=3100
GRAFANA_ADMIN_USER=admin
GRAFANA_ADMIN_PASSWORD=admin
```

---

## 3. Starting the Services

### Initial Launch (Builds custom image and launches all 8 containers)

```bash
docker compose up -d --build
```

### Regular Daily Startup

```bash
docker compose up -d
```

### Verifying Service State

```bash
docker compose ps
```

**Expected output:**
```text
NAME                   IMAGE                                   STATUS                  PORTS
hris_app               hris-system-app                         Up                      80/tcp
hris_db                mysql:8.0                               Up (healthy)            0.0.0.0:3307->3306/tcp
hris_grafana           grafana/grafana:10.4.1                  Up                      0.0.0.0:3100->3000/tcp
hris_mysqld_exporter   prom/mysqld-exporter:v0.15.1            Up                      9104/tcp
hris_nginx             nginx:alpine                            Up                      0.0.0.0:8080->80/tcp
hris_nginx_exporter    nginx/nginx-prometheus-exporter:1.1.0   Up                      
hris_phpmyadmin        phpmyadmin:latest                       Up                      0.0.0.0:8081->80/tcp
hris_prometheus        prom/prometheus:v2.51.0                 Up                      0.0.0.0:9090->9090/tcp
```

---

## 4. Stopping the Services

### Graceful Stop (Data Persisted)
```bash
docker compose down
```
*Note: Shuts down all containers gracefully and preserves `hris_db_data`, `hris_prometheus_data`, and `hris_grafana_data`.*

### Clean Factory Reset (Destroys Database Volume)
```bash
docker compose down -v
```
*Warning: Destroys persistent database volume. On next `up`, database will re-initialize from `01-schema.sql` and `02-seed.sql`.*

---

## 5. Live Log Streaming

```bash
# Follow logs for all services
docker compose logs -f

# Follow specific component
docker compose logs -f nginx
docker compose logs -f app
docker compose logs -f db
docker compose logs -f prometheus
docker compose logs -f grafana
```
