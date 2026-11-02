# Monitoring Architecture & Telemetry Dataflow

## 1. System Overview

The HRIS-SYSTEM production observability stack collects, aggregates, and visualizes system health, infrastructure utilization, and business domain metrics across all application tiers.

The architecture follows a pull-based telemetry model powered by **Prometheus v2.51.0** and visualized through **Grafana v10.4.1**.

```mermaid
flowchart TD
    subgraph Clients["External Clients & Operators"]
        Dev["Developer / Admin Browser"]
        User["HRIS End User Browser"]
    end

    subgraph EntryPoint["Edge & Routing Tier (Port 8080)"]
        Nginx["Nginx Gateway\n(hris_nginx:80)"]
    end

    subgraph MonitoringTier["Observability Tier (Isolated Network)"]
        Grafana["Grafana v10.4.1\n(hris_grafana:3000 -> 3100)"]
        Prometheus["Prometheus TSDB v2.51.0\n(hris_prometheus:9090)"]
    end

    subgraph Exporters["Exporters & Runtime Instrumentation"]
        NginxExp["Nginx Prometheus Exporter\n(hris_nginx_exporter:9113)"]
        MysqlExp["MySQL Server Exporter\n(hris_mysqld_exporter:9104)"]
        App["PHP-FPM HRIS App Runtime\n(hris_app:80/metrics)"]
    end

    subgraph DatabaseTier["Data Tier (Internal)"]
        MySQL[("MySQL 8.0 Server\n(hris_db:3306 -> 3307)")]
    end

    User -->|HTTP Requests| Nginx
    Nginx -->|Reverse Proxy / FastCGI| App
    App -->|PDO SQL Queries| MySQL

    Dev -->|View Dashboards :3100| Grafana
    Dev -->|Direct TSDB Queries :9090| Prometheus

    Grafana -->|Datasource Proxy (POST /api/v1/query)| Prometheus

    Prometheus -->|Scrape :9090/metrics (15s)| Prometheus
    Prometheus -->|Scrape :9113/metrics (15s)| NginxExp
    Prometheus -->|Scrape :9104/metrics (15s)| MysqlExp
    Prometheus -->|Scrape app:80/metrics (15s)| App

    NginxExp -->|Stub Status Query| Nginx
    MysqlExp -->|SHOW GLOBAL STATUS / InnoDB| MySQL
```

---

## 2. Telemetry Flow & Components

### 1. Prometheus Scraping Engine
- Prometheus runs inside container `hris_prometheus` on port `9090`.
- Executes HTTP GET requests to configured endpoints every 15 seconds (`scrape_interval: 15s`).
- Labels every time-series metric with standard metadata (`app`, `service`, `instance`, `job`).
- Persists metrics to time-series database storage backed by the Docker volume `prometheus_data`.

### 2. Exporter Ecosystem
- **Nginx Prometheus Exporter (`hris_nginx_exporter:9113`)**:
  - Connects to Nginx internal endpoint `http://nginx:80/stub_status`.
  - Exposes active client connections, accepted/handled requests, and reading/writing/waiting connection states.
- **MySQL Prometheus Exporter (`hris_mysqld_exporter:9104`)**:
  - Authenticates securely to `hris_db:3306` with restricted monitor privileges.
  - Collects thread counts, InnoDB buffer pool metrics, query command breakdown, connection counters, and replication/table locks.
- **PHP HRIS Application Runtime (`hris_app:80/metrics`)**:
  - Custom endpoint implemented in [`app/controllers/HomeController.php`](../../../app/controllers/HomeController.php#L39-L97).
  - Emits application process health (`hris_app_up`), database connectivity status, query latency, PHP memory usage, and business domain counters (`hris_workforce_total_employees`, `hris_leave_pending_requests`).

### 3. Grafana Dashboard Visualizations
- Grafana runs inside container `hris_grafana` exposed on host port `3100`.
- Proxies all metric queries through Prometheus data source (`uid: Prometheus`).
- Automatically loads dashboards from version-controlled JSON provisioning files without requiring manual configuration.

---

## 3. Network Topology & Port Mapping

All services communicate over the dedicated Docker bridge network `hris_network` (`driver: bridge`).

| Service | Container Name | Docker Internal Port | Host Mapped Port | Network Access Policy |
| :--- | :--- | :--- | :--- | :--- |
| **Nginx Gateway** | `hris_nginx` | `80/tcp` | `0.0.0.0:8080` | Public ingress |
| **PHP HRIS App** | `hris_app` | `80/tcp` | None (Internal) | Isolated to `hris_network` |
| **MySQL Database** | `hris_db` | `3306/tcp` | `0.0.0.0:3307` | Development / Migrations |
| **Prometheus** | `hris_prometheus` | `9090/tcp` | `0.0.0.0:9090` | Development / Query UI |
| **Grafana** | `hris_grafana` | `3000/tcp` | `0.0.0.0:3100` | Developer Observability |
| **Nginx Exporter** | `hris_nginx_exporter` | `9113/tcp` | None (Internal) | Isolated to `hris_network` |
| **MySQL Exporter** | `hris_mysqld_exporter` | `9104/tcp` | `9104/tcp` | Isolated to `hris_network` |
| **phpMyAdmin** | `hris_phpmyadmin` | `80/tcp` | `0.0.0.0:8081` | Dev database management |
| **Swagger UI** | `hris_swagger_ui` | `8080/tcp` | `0.0.0.0:8083` | API documentation |

---

## 4. Security & Data Integrity Considerations

1. **No Credentials in Metrics Payloads:**
   - Exporter outputs contain exclusively numerical gauges, counters, and histograms.
   - User credentials, session tokens, passwords, and PII are strictly excluded.
2. **Database Isolation:**
   - The application runtime communicates with MySQL via container DNS (`hris_db`) across the private Docker network.
   - MySQL metrics collection is read-only (`SHOW GLOBAL STATUS`, `SHOW ENGINE INNODB STATUS`).
3. **Volume Persistence:**
   - Prometheus metrics are stored in persistent volume `prometheus_data`.
   - Grafana configuration, user accounts, and dashboard metadata are stored in `grafana_data`.
   - Database tables and state are preserved in `db_data`.

