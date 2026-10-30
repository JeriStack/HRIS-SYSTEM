# Grafana Provisioning and Dashboards Catalog

## 1. Provisioning Architecture

Grafana v10.4.1 utilizes automated file-based provisioning. All data sources, dashboard providers, and dashboard layouts are defined as code in version-controlled files under [`docker/grafana/`](../../docker/grafana/).

### Directory Structure

```text
docker/grafana/
├── provisioning/
│   ├── datasources/
│   │   └── prometheus.yml          # Prometheus data source configuration (UID: Prometheus)
│   └── dashboards/
│       └── dashboards.yml          # Dashboard provider scanning docker/grafana/dashboards/
└── dashboards/
    ├── infrastructure-overview.json # Stack and infrastructure health
    ├── database-overview.json       # MySQL engine, connection & buffer pool deep-dive
    ├── hris-overview.json           # HRIS PHP application, memory & workforce domain KPIs
    ├── mysql-deep-dive.json         # Extended MySQL query and engine metrics
    └── stack-overview.json          # High-level DevOps stack performance
```

### Data Source Definition (`docker/grafana/provisioning/datasources/prometheus.yml`)

```yaml
apiVersion: 1

datasources:
  - name: Prometheus
    uid: Prometheus
    type: prometheus
    access: proxy
    orgId: 1
    url: http://prometheus:9090
    isDefault: true
    version: 2
    editable: false
    jsonData:
      httpMethod: POST
      timeInterval: 15s
```

---

## 2. Dashboard Catalog

### Dashboard 1: HRIS Infrastructure Overview
- **File:** `docker/grafana/dashboards/infrastructure-overview.json`
- **Dashboard UID:** `hris-infrastructure-overview`
- **Folder:** HRIS Stack
- **Purpose:** Comprehensive operational health and resource metrics across all containerized tiers.

#### Panel Inventory & Metrics

| Panel Title | Visual Type | PromQL Query | Purpose / Thresholds |
| :--- | :--- | :--- | :--- |
| **Nginx Proxy Gateway** | `stat` | `up{job="nginx"}` | 1 = HEALTHY (Green), 0 = DOWN (Red) |
| **MySQL 8.0 Database** | `stat` | `up{job="mysql"}` | 1 = HEALTHY (Green), 0 = DOWN (Red) |
| **Prometheus Server** | `stat` | `up{job="prometheus"}` | 1 = HEALTHY (Green), 0 = DOWN (Red) |
| **Database Uptime** | `stat` | `mysql_global_status_uptime` | Total active database runtime in seconds |
| **Active Client Connections** | `gauge` | `nginx_connections_active` | Real-time concurrent connections to Nginx |
| **HTTP Request Rate** | `timeseries`| `rate(nginx_http_requests_total[1m])` | Inbound requests per second |
| **Connection States** | `timeseries`| `nginx_connections_reading`, `writing`, `waiting` | Nginx internal worker state distribution |
| **MySQL Active Threads** | `timeseries`| `mysql_global_status_threads_connected` | Active database connections |
| **InnoDB Buffer Pool Usage**| `timeseries`| `mysql_global_status_innodb_buffer_pool_bytes_data`, `size` | Memory usage vs allocated pool size |
| **SQL Execution by Type** | `timeseries`| `rate(mysql_global_status_commands_total{command=~"select|insert|update|delete|commit"}[1m])` | Command rate broken down by SQL verb |
| **Network I/O Bandwidth** | `timeseries`| `rate(mysql_global_status_bytes_received[1m])`, `bytes_sent` | Database network throughput in bytes/sec |
| **Scrape Duration** | `timeseries`| `scrape_duration_seconds` | Latency per scrape target in seconds |
| **Samples Processed** | `timeseries`| `scrape_samples_scraped` | Metrics count ingested per target scrape |

---

### Dashboard 2: HRIS Database Overview
- **File:** `docker/grafana/dashboards/database-overview.json`
- **Dashboard UID:** `hris-database-overview`
- **Folder:** HRIS Stack
- **Purpose:** Deep diagnostic visibility into the MySQL 8.0 database engine, connections, buffer pool performance, and slow queries.

#### Panel Inventory & Metrics

| Panel Title | Visual Type | PromQL Query | Purpose / Thresholds |
| :--- | :--- | :--- | :--- |
| **Database Engine Status** | `stat` | `mysql_up` | 1 = ONLINE (Green), 0 = OFFLINE (Red) |
| **Connected Client Threads**| `stat` | `mysql_global_status_threads_connected` | Active open sessions |
| **Max Configured Connections**| `stat` | `mysql_global_variables_max_connections` | Database connection limit ceiling |
| **Slow Queries Recorded** | `stat` | `mysql_global_status_slow_queries` | Queries exceeding long_query_time threshold |
| **Connection Utilization %**| `gauge` | `(mysql_global_status_threads_connected / mysql_global_variables_max_connections) * 100` | Yellow: 70%, Red: 85% |
| **InnoDB Buffer Pool Hit Ratio**| `gauge` | `(1 - (rate(mysql_global_status_innodb_buffer_pool_reads[5m]) / rate(mysql_global_status_innodb_buffer_pool_read_requests[5m]))) * 100` | Target > 95% (Green) |
| **Query Throughput (QPS)** | `timeseries`| `rate(mysql_global_status_queries[1m])`, `rate(mysql_global_status_questions[1m])` | Queries and Questions executed per second |
| **InnoDB Buffer Pool Memory**| `timeseries`| `mysql_global_status_innodb_buffer_pool_bytes_data`, `dirty`, `free`, `total` | Memory allocation breakdown in bytes |
| **Thread Lifecycle Breakdown**| `timeseries`| `threads_connected`, `threads_running`, `threads_cached` | Thread concurrency and thread cache efficiency |
| **SQL Command Execution** | `timeseries`| `rate(mysql_global_status_commands_total[1m])` | Rate of SELECT, INSERT, UPDATE, DELETE |
| **Aborted Connections** | `timeseries`| `rate(mysql_global_status_aborted_connects[1m])`, `aborted_clients` | Connection errors and client disconnections |

---

### Dashboard 3: HRIS Application Overview
- **File:** `docker/grafana/dashboards/hris-overview.json`
- **Dashboard UID:** `hris-application-overview`
- **Folder:** HRIS Stack
- **Purpose:** Application-tier health, PHP runtime resources, database roundtrip latency, and core HR business domain indicators.

#### Panel Inventory & Metrics

| Panel Title | Visual Type | PromQL Query | Purpose / Thresholds |
| :--- | :--- | :--- | :--- |
| **Application Process Health**| `stat` | `hris_app_up` | 1 = HEALTHY (Green), 0 = CRITICAL (Red) |
| **Database Connectivity** | `stat` | `hris_app_database_connected` | 1 = CONNECTED (Green), 0 = DISCONNECTED (Red) |
| **Total Active Employees** | `stat` | `hris_workforce_total_employees` | Workforce headcount in database |
| **Pending Leave Requests** | `stat` | `hris_leave_pending_requests` | Leave requests awaiting manager approval |
| **DB Latency Roundtrip** | `gauge` | `hris_app_database_latency_seconds` | Latency from PHP PDO ping to MySQL |
| **PHP Memory Usage** | `gauge` | `hris_app_memory_bytes` | Current allocated memory in MB |
| **PHP Memory Peak Footprint**| `gauge` | `hris_app_memory_peak_bytes` | Peak memory consumed during request cycle |
| **Front-End Request Throughput**| `timeseries`| `rate(nginx_http_requests_total[1m])` | HTTP requests routed to application per sec |
| **Active Client Connections** | `timeseries`| `nginx_connections_active` | Concurrent browser connections |
| **Connection States** | `timeseries`| `nginx_connections_reading`, `writing`, `waiting` | Active connection state breakdown |
| **Application Metadata** | `table` | `hris_app_info` | Environment, PHP version, App release |

---

## 3. Verification & API Inspection

Grafana exposes its REST API for programmatic validation:

```bash
# 1. Search all registered dashboards
curl.exe -s -u admin:admin http://localhost:3100/api/search | jq .

# 2. Inspect Dashboard 1 metadata
curl.exe -s -u admin:admin http://localhost:3100/api/dashboards/uid/hris-infrastructure-overview | jq .meta

# 3. Inspect Dashboard 2 metadata
curl.exe -s -u admin:admin http://localhost:3100/api/dashboards/uid/hris-database-overview | jq .meta

# 4. Inspect Dashboard 3 metadata
curl.exe -s -u admin:admin http://localhost:3100/api/dashboards/uid/hris-application-overview | jq .meta
```

