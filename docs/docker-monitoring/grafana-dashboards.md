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

## 3. Provisioned Dashboard: `HRIS Stack Monitoring & Performance`

**File:** `docker/grafana/dashboards/stack-overview.json`  
**UID:** `hris-stack-overview`  
**URL:** [http://localhost:3100/d/hris-stack-overview/hris-stack-monitoring-and-performance](http://localhost:3100/d/hris-stack-overview/hris-stack-monitoring-and-performance)

### Row 1: Stack & Service Health Overview
- **Nginx Proxy Status:** Single-stat panel querying `up{job="nginx"}` (Healthy = 1, Down = 0).
- **MySQL Database Status:** Single-stat panel querying `up{job="mysql"}`.
- **Prometheus Monitor Status:** Single-stat panel querying `up{job="prometheus"}`.

### Row 2: Nginx Reverse Proxy Metrics
- **Nginx Inbound Request Rate:** Time series tracking `rate(nginx_http_requests_total[1m])` in requests per second.
- **Nginx Active Connections Breakdown:** Time series displaying `nginx_connections_active`, `nginx_connections_reading`, `nginx_connections_writing`, and `nginx_connections_waiting`.

### Row 3: MySQL Database Performance & Resource Metrics
- **MySQL Query Execution Rate:** Time series tracking total queries/sec (`rate(mysql_global_status_queries[1m])`) and slow queries/sec.
- **MySQL Client Connections:** Gauge panel tracking `mysql_global_status_threads_connected` against max allowable ceiling (151).
- **MySQL InnoDB Buffer Pool:** Time series tracking buffer pool used (`mysql_global_status_innodb_buffer_pool_bytes_data`) against total pool size (`mysql_global_variables_innodb_buffer_pool_size`).
- **MySQL Network Throughput:** Time series measuring inbound (`rate(mysql_global_status_bytes_received[1m])`) and outbound bandwidth (`rate(mysql_global_status_bytes_sent[1m])`).
- **MySQL Database Uptime:** Stat panel tracking total server uptime in seconds (`mysql_global_status_uptime`).

---

## 4. Administrator Credentials & Access

- **Web URL:** [http://localhost:3100](http://localhost:3100)
- **Default Username:** `admin` (or configured `GRAFANA_ADMIN_USER`)
- **Default Password:** `admin` (or configured `GRAFANA_ADMIN_PASSWORD`)
- **Self-Registration:** Disabled (`GF_USERS_ALLOW_SIGN_UP: "false"`).
