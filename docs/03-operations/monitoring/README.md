# HRIS Docker & Observability Stack

**Project:** Employee HRIS & Leave Management System  
**Observability Architecture:** Nginx Reverse Proxy + Prometheus Time-Series Storage + Grafana Visualization + Specialized Monitoring Exporters

---

## 1. Executive Summary

This directory documents the production-grade containerized architecture and observability infrastructure integrated into the Employee HRIS & Leave Management System. The deployment preserves the core PHP/Apache execution model and MySQL transactional database while introducing an edge reverse proxy and automated Prometheus/Grafana telemetry.

---

## 2. Infrastructure Directory Index

| Document | Description |
| :--- | :--- |
| **[`architecture.md`](architecture.md)** | End-to-end request flow, service responsibilities, and container network topology. |
| **[`setup-guide.md`](setup-guide.md)** | Step-by-step instructions for starting, stopping, verifying, and configuring the environment. |
| **[`nginx-configuration.md`](nginx-configuration.md)** | Reverse proxy routing, upstream keepalive, client buffer limits, and metrics stub status. |
| **[`prometheus-configuration.md`](prometheus-configuration.md)** | Scrape intervals, target configurations, self-monitoring, and metric definitions. |
| **[`grafana-dashboards.md`](grafana-dashboards.md)** | Automated provisioning of datasources, dashboard JSON schema, and visualization panels. |
| **[`network-and-security.md`](network-and-security.md)** | Port mapping strategy, least-privilege exporter accounts, and container isolation. |
| **[`troubleshooting.md`](troubleshooting.md)** | Diagnostic playbooks, common log warnings, port conflict resolutions, and repairs. |
| **[`verification-report.md`](verification-report.md)** | Empirical test logs, target health reports, and end-to-end regression test results. |

---

## 3. Quick Access URLs & Credentials

| Service | Access URL | Port (Host:Container) | Credentials (Default) |
| :--- | :--- | :--- | :--- |
| **HRIS Application** | [http://localhost:8080](http://localhost:8080) | `8080:80` (Nginx) | `hr01` / `Admin@123`<br>`emp004` / `Employee@123` |
| **phpMyAdmin** | [http://localhost:8081](http://localhost:8081) | `8081:80` | `root` / `rootpassword` |
| **Prometheus** | [http://localhost:9090](http://localhost:9090) | `9090:9090` | Public in development |
| **Grafana** | [http://localhost:3100](http://localhost:3100) | `3100:3000` | `admin` / `admin` |
| **MySQL Direct** | `127.0.0.1:3307` | `3307:3306` | `root` / `rootpassword`<br>`hris_user` / `hris_password` |
