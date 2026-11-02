# Stack Verification & Test Execution Report

**Project:** Employee HRIS & Leave Management System  
**Date:** 2026-10-14  
**Document:** `docs/docker-monitoring/verification-report.md`

---

## 1. Container Health Status Verification

**Command:** `docker compose ps`  
**Execution Timestamp:** 2026-10-14T22:42:00+08:00

| Service | Container Name | Image | Status | Published Ports |
| :--- | :--- | :--- | :--- | :--- |
| `nginx` | `hris_nginx` | `nginx:alpine` | **Up (healthy)** | `0.0.0.0:8080->80/tcp` |
| `app` | `hris_app` | `hris-system-app` | **Up (healthy)** | `80/tcp` (Internal) |
| `db` | `hris_db` | `mysql:8.0` | **Up (healthy)** | `0.0.0.0:3307->3306/tcp` |
| `phpmyadmin` | `hris_phpmyadmin`| `phpmyadmin:latest` | **Up (healthy)** | `0.0.0.0:8081->80/tcp` |
| `nginx-exporter`| `hris_nginx_exporter` | `nginx/nginx-prometheus-exporter:1.1.0` | **Up (healthy)** | `9113/tcp` (Internal) |
| `mysqld-exporter`| `hris_mysqld_exporter` | `prom/mysqld-exporter:v0.15.1` | **Up (healthy)** | `9104/tcp` (Internal) |
| `prometheus` | `hris_prometheus`| `prom/prometheus:v2.51.0` | **Up (healthy)** | `0.0.0.0:9090->9090/tcp` |
| `grafana` | `hris_grafana` | `grafana/grafana:10.4.1` | **Up (healthy)** | `0.0.0.0:3100->3000/tcp` |

---

## 2. Prometheus Target Scrape Verification

**Endpoint:** `http://localhost:9090/api/v1/targets`  
**Result:** Verified 3 of 3 targets active and healthy (`"health": "up"`):

| Job Name | Target URL | Scrape Interval | Status | Last Scrape Error |
| :--- | :--- | :--- | :--- | :--- |
| `prometheus` | `http://localhost:9090/metrics` | 15s | **UP** | None |
| `nginx` | `http://nginx-exporter:9113/metrics` | 15s | **UP** | None |
| `mysql` | `http://mysqld-exporter:9104/metrics`| 15s | **UP** | None |

---

## 3. Grafana Datasource & Dashboard Verification

- **Datasource Health Check:** `http://localhost:3100/api/datasources/uid/PBFA97CFB590B2093/health`
  - Output: `{"status":"OK","message":"Successfully queried the Prometheus API."}`
- **Dashboard Search Check:** `http://localhost:3100/api/search`
  - Output: `[{"uid":"hris-stack-overview","title":"HRIS Stack Monitoring & Performance","folderTitle":"HRIS Stack"}]`
- **Dashboard Panels Verified:**
  1. Nginx Proxy Status (`up{job="nginx"}`) -> 1 (HEALTHY)
  2. MySQL Database Status (`up{job="mysql"}`) -> 1 (HEALTHY)
  3. Prometheus Status (`up{job="prometheus"}`) -> 1 (HEALTHY)
  4. Nginx Active Connections (`nginx_connections_active`) -> 1
  5. MySQL Uptime (`mysql_global_status_uptime`) -> 6,529s (Database persisted without restart)
  6. MySQL Buffer Pool & Query Rates -> Real metrics actively reporting

---

## 4. End-to-End Regression Test Execution Results

All automated test suites were executed through the Nginx reverse proxy gateway:

| Test Suite | File | Assertions Run | Passed | Failed | Pass Rate |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Phase 8 Leave Live HTTP Integration** | `scratch/test_phase8_http.php` | 26 | 26 | 0 | **100%** |
| **Phase 8 Leave Model & Transactions** | `scratch/test_phase8_leave_suite.php` | 60 | 60 | 0 | **100%** |
| **Deployment Readiness & UI Workflow** | `scratch/test_deployment_ui.php` | 42 | 42 | 0 | **100%** |
| **2-Role RBAC Authorization & Isolation** | `scratch/test_rbac_suite.php` | 44 | 44 | 0 | **100%** |
| **Phase 7 Core HRIS CRUD Operations** | `scratch/test_phase7_crud.php` | 51 | 51 | 0 | **100%** |
| **Phase 7 Core HRIS Live HTTP Routes** | `scratch/test_phase7_http.php` | 34 | 34 | 0 | **100%** |
| **Data Structures & Algorithms Laboratory** | `scratch/test_dsa_suite.php` | 65 | 65 | 0 | **100%** |
| **TOTAL VERIFIED ASSERTIONS** | - | **322** | **322** | **0** | **100%** |

---

## 5. Regression & Security Conclusion

The integration of Nginx, Prometheus, and Grafana into the existing HRIS container environment achieved **100% compliance** with zero downtime or data loss on the active MySQL database. Ingress routing, `.htaccess` rewrites, BCRYPT authentication, session management, and CSRF token protection remain 100% operational.
