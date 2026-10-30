# Grafana Provisioning & Visualization Dashboards

**Project:** Employee HRIS & Leave Management System  
**Document:** `docs/docker-monitoring/grafana-dashboards.md`

---

## 1. Overview

Grafana is provisioned fully declaratively using configuration files. No manual data source setup or dashboard imports are required upon container creation.

---

## 2. Declarative Provisioning Structure

### Data Source Provisioning (`docker/grafana/provisioning/datasources/prometheus.yml`)
```yaml
apiVersion: 1
datasources:
  - name: Prometheus
    type: prometheus
    access: proxy
    orgId: 1
    url: http://prometheus:9090
    isDefault: true
    version: 1
    editable: false
    jsonData:
      httpMethod: POST
      timeInterval: 15s
```
- **Internal Addressing:** Connects directly to `http://prometheus:9090` across `hris_network`.
- **Default Status:** Set as the default data source (`isDefault: true`).

### Dashboard Provider (`docker/grafana/provisioning/dashboards/provider.yml`)
```yaml
apiVersion: 1
providers:
  - name: 'HRIS Monitoring Dashboards'
    orgId: 1
    folder: 'HRIS Stack'
    type: file
    disableDeletion: false
    updateIntervalSeconds: 30
    allowUiUpdates: true
    options:
      path: /var/lib/grafana/dashboards
```
- Automatically registers and refreshes all JSON dashboards placed in `/var/lib/grafana/dashboards`.

---

## 3. Provisioned Dashboards

### Dashboard 1: `HRIS Infrastructure Overview` (Recommended Executive View)

**File:** `docker/grafana/dashboards/infrastructure-overview.json`  
**UID:** `hris-infrastructure-overview`  
**URL:** [http://localhost:3100/d/hris-infrastructure-overview/hris-infrastructure-overview](http://localhost:3100/d/hris-infrastructure-overview/hris-infrastructure-overview)

- **Row 1 — Stack & Service Health Overview:** Nginx Proxy Gateway, MySQL 8.0 Database, Prometheus Server status cards with dynamic green/red badges and Database Uptime duration.
- **Row 2 — Inbound Traffic & Web Gateway Analytics (Nginx):** Total HTTP requests processed counter, real-time HTTP inbound request rate (`req/sec`), active connections breakdown (Active, Reading, Writing, Waiting), and connection drop verification (`accepted` vs `handled`).
- **Row 3 — MySQL Database Performance & Operations:** Query execution rate (QPS) with slow queries rate alert, connection saturation gauge against max ceiling (151 connections), and InnoDB buffer pool memory utilization.
- **Row 4 — SQL Commands Breakdown & Network Throughput:** Live operations breakdown by command (`SELECT`, `INSERT`, `UPDATE`, `DELETE`, `COMMIT`) and inbound/outbound network I/O bandwidth (`Bytes/sec`).
- **Row 5 — Monitoring Engine Health & Scraping Metrics (Prometheus):** Scrape latency per exporter target and metric samples processed per cycle.

---

### Dashboard 2: `HRIS MySQL Database Deep-Dive`

**File:** `docker/grafana/dashboards/mysql-deep-dive.json`  
**UID:** `hris-mysql-deep-dive`  
**URL:** [http://localhost:3100/d/hris-mysql-deep-dive/hris-mysql-database-deep-dive](http://localhost:3100/d/hris-mysql-deep-dive/hris-mysql-database-deep-dive)

- **Instance Health & KPIs:** Instance status, server uptime, cumulative queries processed counter, and slow query tally.
- **Throughput & CRUD Operations:** Total QPS time series and detailed multi-series CRUD command distribution.
- **Memory & Connections:** Connection pool saturation gauge, InnoDB buffer pool data vs total size, and network bandwidth in/out.

---

### Dashboard 3: `HRIS Stack Monitoring & Performance`

**File:** `docker/grafana/dashboards/stack-overview.json`  
**UID:** `hris-stack-overview`  
**URL:** [http://localhost:3100/d/hris-stack-overview/hris-stack-monitoring-and-performance](http://localhost:3100/d/hris-stack-overview/hris-stack-monitoring-and-performance)

- **Row 1:** Stack & Service Health Overview (Nginx, MySQL, Prometheus).
- **Row 2:** Nginx Inbound Request Rate and Connections Breakdown.
- **Row 3:** MySQL Query Execution Rate, Client Connections Gauge, InnoDB Buffer Pool, Network Throughput, and Database Uptime.

---

## 4. Administrator Credentials & Access

- **Web URL:** [http://localhost:3100](http://localhost:3100)
- **Default Username:** `admin` (or configured `GRAFANA_ADMIN_USER`)
- **Default Password:** `admin` (or configured `GRAFANA_ADMIN_PASSWORD`)
- **Self-Registration:** Disabled (`GF_USERS_ALLOW_SIGN_UP: "false"`).
