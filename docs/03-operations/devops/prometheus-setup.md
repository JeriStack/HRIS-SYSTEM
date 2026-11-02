# Prometheus Configuration and Scrape Target Specification

## 1. Overview

Prometheus v2.51.0 acts as the centralized time-series metrics collection and storage engine for the HRIS-SYSTEM. This document outlines the configuration, scrape jobs, target endpoints, reload mechanics, and verification procedures.

---

## 2. Configuration File (`docker/prometheus/prometheus.yml`)

The active configuration file is mounted directly into the `hris_prometheus` container at `/etc/prometheus/prometheus.yml`:

```yaml
global:
  scrape_interval: 15s
  evaluation_interval: 15s
  scrape_timeout: 10s

scrape_configs:
  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']
        labels:
          app: 'prometheus'
          service: 'monitoring'

  - job_name: 'nginx'
    static_configs:
      - targets: ['nginx-exporter:9113']
        labels:
          app: 'nginx'
          service: 'proxy'

  - job_name: 'mysql'
    static_configs:
      - targets: ['mysqld-exporter:9104']
        labels:
          app: 'mysql'
          service: 'database'

  - job_name: 'hris-app'
    metrics_path: '/metrics'
    static_configs:
      - targets: ['app:80']
        labels:
          app: 'hris'
          service: 'application'
```

---

## 3. Scrape Jobs Specification

| Job Name | Target URI | Internal Address | Metrics Path | Interval | Labels Applied | Description |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `prometheus` | `http://localhost:9090/metrics` | `localhost:9090` | `/metrics` | 15s | `app="prometheus"`, `service="monitoring"` | Prometheus internal TSDB performance and ingestion rates |
| `nginx` | `http://nginx-exporter:9113/metrics` | `nginx-exporter:9113` | `/metrics` | 15s | `app="nginx"`, `service="proxy"` | Nginx ingress connections, traffic, and request rates |
| `mysql` | `http://mysqld-exporter:9104/metrics` | `mysqld-exporter:9104` | `/metrics` | 15s | `app="mysql"`, `service="database"` | MySQL 8.0 query rates, thread counts, buffer pool, lock status |
| `hris-app` | `http://app:80/metrics` | `app:80` | `/metrics` | 15s | `app="hris"`, `service="application"` | PHP application uptime, memory, DB latency, workforce & leave KPIs |

---

## 4. Dynamic Reload Mechanics

The Prometheus container is launched with `--web.enable-lifecycle` in `docker-compose.yml`. This flag enables live configuration reloading via HTTP without restarting the container or losing collected metrics.

### Reload Command

```bash
# Trigger hot-reload via HTTP POST
curl.exe -X POST http://localhost:9090/-/reload
```

Upon receiving this request, Prometheus validates the configuration syntax in memory. If valid, it immediately updates its scrape loop with the new target inventory; if invalid, it retains the existing working configuration and logs the syntax error.

---

## 5. Verification Commands & Health Checking

### Check Prometheus Process Health
```bash
# Verify operational liveness
curl.exe -s http://localhost:9090/-/healthy
# Returns: Prometheus Server is Healthy.

# Verify target readiness
curl.exe -s http://localhost:9090/-/ready
# Returns: Prometheus Server is Ready.
```

### Inspect Live Active Scrape Targets
```bash
curl.exe -s http://localhost:9090/api/v1/targets | jq .data.activeTargets
```
Expected response: All four targets (`prometheus`, `nginx`, `mysql`, `hris-app`) return `"health": "up"` and `"lastError": ""`.

### Key PromQL Query Verification

Execute queries directly via Prometheus HTTP API:

```bash
# 1. Verify all targets are UP (returns 1 for each target)
curl.exe -s "http://localhost:9090/api/v1/query?query=up"

# 2. Verify PHP Application metric collection
curl.exe -s "http://localhost:9090/api/v1/query?query=hris_app_up"
curl.exe -s "http://localhost:9090/api/v1/query?query=hris_workforce_total_employees"
curl.exe -s "http://localhost:9090/api/v1/query?query=hris_leave_pending_requests"

# 3. Verify MySQL Exporter queries
curl.exe -s "http://localhost:9090/api/v1/query?query=mysql_global_status_uptime"
curl.exe -s "http://localhost:9090/api/v1/query?query=mysql_global_status_threads_connected"

# 4. Verify Nginx Exporter queries
curl.exe -s "http://localhost:9090/api/v1/query?query=nginx_connections_active"
curl.exe -s "http://localhost:9090/api/v1/query?query=rate(nginx_http_requests_total[1m])"
```

