# System Architecture & Request Topology

**Project:** Employee HRIS & Leave Management System  
**Document:** `docs/docker-monitoring/architecture.md`

---

## 1. Request & Monitoring Architecture Diagram

```
+------------------------------------------------------------------------------------+
|                                    Client Tier                                     |
|  - Web Browser: http://localhost:8080 (HRIS App) & http://localhost:3100 (Grafana) |
|  - Developer Terminal / Database Client: localhost:3307                            |
+------------------------------------------------------------------------------------+
                                 |                          |
               (HTTP :8080)      v                          v  (HTTP :3100)
+----------------------------------------------+  +----------------------------------+
|            Nginx Reverse Proxy               |  |             Grafana              |
|  - Container: hris_nginx                     |  |  - Container: hris_grafana       |
|  - Host Port: 8080 -> Container Port: 80     |  |  - Host Port: 3100               |
|  - Internal Metrics Port: 8082 (/stub_status)|  |  - Auto-provisioned Dashboards   |
+----------------------------------------------+  +----------------------------------+
                        |                                           ^
       (Reverse Proxy)  v                                           | (PromQL Queries)
+----------------------------------------------+  +----------------------------------+
|           Apache / PHP Application           |  |            Prometheus            |
|  - Container: hris_app                       |  |  - Container: hris_prometheus    |
|  - Internal Port: 80 (Unpublished to host)   |  |  - Host Port: 9090               |
|  - DocumentRoot: /var/www/html/public        |  |  - Scrapes: 15s interval         |
|  - PHP 8.2 + PDO MySQL + Rewrites            |  |  - Volume: hris_prometheus_data  |
+----------------------------------------------+  +----------------------------------+
                        |                                    ^             ^
       (PDO Connection) v                                    |             |
+----------------------------------------------+             |             |
|                MySQL Database                |             |             |
|  - Container: hris_db                        |             |             |
|  - Host Port: 3307 -> Container: 3306        |             |             |
|  - Persistent Volume: hris_db_data           |             |             |
+----------------------------------------------+             |             |
           |                                                 |             |
           | (Scrapes DB)                                    |             |
           v                                                 |             |
+----------------------------------------------+             |             |
|            MySQL Exporter Service            |-------------+             |
|  - Container: hris_mysqld_exporter           |                           |
|  - Port: 9104 (Internal bridge network)      |                           |
|  - Least-Privilege Account: 'exporter'@'%'   |                           |
+----------------------------------------------+                           |
                                                                           |
+----------------------------------------------+                           |
|            Nginx Exporter Service            |---------------------------+
|  - Container: hris_nginx_exporter            |
|  - Port: 9113 (Internal bridge network)      |
|  - Scrapes http://nginx:8082/stub_status     |
+----------------------------------------------+
```

---

## 2. Logical Service Components

### 1. Reverse Proxy (`nginx`)
- **Technology:** `nginx:alpine`
- **Role:** Front-facing reverse proxy and ingress gateway. Accepts all incoming browser traffic on port 8080 and forwards requests to the Apache backend.
- **Port Isolation:** Apache is not exposed to host interfaces; only Nginx terminates host port 8080.
- **Metrics Exposure:** An internal virtual host listens on port `8082` exposing `/stub_status` solely to Docker's internal network (`hris_network`).

### 2. Application Server (`app`)
- **Technology:** `php:8.2-apache`
- **Role:** Executes application logic, handles session management, CSRF validation, database transactions, and algorithmic workflows.
- **Routing:** Powered by Apache `mod_rewrite` and front controller `/public/index.php`.

### 3. Database Server (`db`)
- **Technology:** `mysql:8.0`
- **Role:** Relational transactional data store holding employee records, authentication hashes, leave applications, balances, and immutable activity logs.
- **Persistence:** Bound to named volume `hris_db_data` ensuring zero data loss across container lifecycle events.

### 4. Database Administrator (`phpmyadmin`)
- **Technology:** `phpmyadmin:latest`
- **Role:** Web-based GUI for ad-hoc SQL inspection and database maintenance, available on host port `8081`.

### 5. Ingress Metrics Agent (`nginx-exporter`)
- **Technology:** `nginx/nginx-prometheus-exporter:1.1.0`
- **Role:** Translates raw Nginx stub_status counters (active connections, accepted connections, reading/writing/waiting) into Prometheus metrics format on internal port `9113`.

### 6. Database Metrics Agent (`mysqld-exporter`)
- **Technology:** `prom/mysqld-exporter:v0.15.1`
- **Role:** Collects engine performance, buffer pool saturation, query rates, slow queries, and thread counts via a dedicated least-privilege `exporter` user on internal port `9104`.

### 7. Time-Series Engine (`prometheus`)
- **Technology:** `prom/prometheus:v2.51.0`
- **Role:** Core time-series database. Scrapes exporters every 15 seconds, applies retention policies, and serves PromQL queries.

### 8. Observability UI (`grafana`)
- **Technology:** `grafana/grafana:10.4.1`
- **Role:** Visualization workbench with pre-provisioned Prometheus data source and performance dashboard.
