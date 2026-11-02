# Prometheus Configuration & Metrics Collection

**Project:** Employee HRIS & Leave Management System  
**Document:** `docs/docker-monitoring/prometheus-configuration.md`

---

## 1. Overview

Prometheus runs as the central metrics scraping and time-series aggregation service. It polls three endpoints across the isolated container network on a 15-second collection cadence.

---

## 2. Configuration Analysis (`docker/prometheus/prometheus.yml`)

```yaml
global:
  scrape_interval: 15s
  evaluation_interval: 15s
  scrape_timeout: 10s

scrape_configs:
  # 1. Prometheus Self-Monitoring
  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']
        labels:
          service: 'monitoring'
          app: 'prometheus'

  # 2. Nginx Reverse Proxy Metrics
  - job_name: 'nginx'
    static_configs:
      - targets: ['nginx-exporter:9113']
        labels:
          service: 'proxy'
          app: 'nginx'

  # 3. MySQL Database Metrics
  - job_name: 'mysql'
    static_configs:
      - targets: ['mysqld-exporter:9104']
        labels:
          service: 'database'
          app: 'mysql'
```

---

## 3. Scrape Targets & Key Metrics

### Target 1: `nginx` (`nginx-exporter:9113`)
- **Exporter:** `nginx/nginx-prometheus-exporter:1.1.0`
- **Scrape Source:** `http://nginx:8082/stub_status`
- **Key Metrics Collected:**
  - `nginx_http_requests_total`: Monotonically increasing counter of all requests received.
  - `nginx_connections_active`: Current open client connections including idle.
  - `nginx_connections_reading`: Current connections reading request headers/body.
  - `nginx_connections_writing`: Connections writing responses back to clients.
  - `nginx_connections_waiting`: Idle keepalive client connections waiting for requests.

### Target 2: `mysql` (`mysqld-exporter:9104`)
- **Exporter:** `prom/mysqld-exporter:v0.15.1`
- **Scrape Source:** MySQL Server on `db:3306` via `.my.cnf` configuration
- **Key Metrics Collected:**
  - `mysql_global_status_uptime`: Seconds since database startup.
  - `mysql_global_status_queries`: Total SQL queries executed by the server.
  - `mysql_global_status_slow_queries`: Count of queries exceeding `long_query_time`.
  - `mysql_global_status_threads_connected`: Active client connections.
  - `mysql_global_variables_max_connections`: Configured connection ceiling.
  - `mysql_global_status_innodb_buffer_pool_bytes_data`: InnoDB memory cache utilization.
  - `mysql_global_variables_innodb_buffer_pool_size`: Total allocated InnoDB cache buffer.
  - `mysql_global_status_bytes_received` / `bytes_sent`: Network I/O throughput.

### Target 3: `prometheus` (`localhost:9090`)
- **Self-Monitoring Target:** Tracks TSDB storage ingestion rates, memory chunks, and scrape duration latencies.

---

## 4. Retention & Storage Policy

- **TSDB Path:** `/prometheus` mapped to persistent volume `hris_prometheus_data`
- **Retention Time:** 15 days (`--storage.tsdb.retention.time=15d`)
- **Lifecycle API:** Enabled (`--web.enable-lifecycle`) allowing runtime configuration reloads via `POST /-/reload`.
