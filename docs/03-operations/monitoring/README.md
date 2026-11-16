# NEXORA HR — Observability & Monitoring Stack

**Project:** NEXORA HR — Human Resource Information System  
**Observability Architecture:** Nginx Reverse Proxy + Prometheus Time-Series Storage (:9090) + Grafana Visualization (:3100) + Specialized Monitoring Exporters (`nginx-exporter`, `mysqld-exporter`) + Application `/metrics` Endpoint

---

## 1. Executive Summary

This directory documents the production-grade observability infrastructure integrated into NEXORA HR. The stack collects, stores, and visualizes real-time telemetry across the Nginx edge reverse proxy, PHP/Apache application runtime (`/metrics`), MySQL 8.0 InnoDB database, background Queue Worker, and WebSocket server.

---

## 2. Monitoring Documentation Index

| Document | Description |
| :--- | :--- |
| **[`monitoring-architecture.md`](monitoring-architecture.md)** | End-to-end telemetry dataflow, exporter topology, and business domain metrics (`/metrics`). |
| **[`prometheus-setup.md`](prometheus-setup.md)** | Prometheus v2.51.0 scrape jobs, target endpoints, intervals, reload mechanics, and verification queries. |
| **[`grafana-dashboards.md`](grafana-dashboards.md)** | Automated file-based datasource and dashboard provisioning under `docker/grafana/`. |
| **[`monitoring-troubleshooting.md`](monitoring-troubleshooting.md)** | Diagnostic playbooks and remediation procedures for Prometheus targets, exporters, and Grafana panels. |

---

## 3. Quick Access URLs & Default Ports

| Service | Access URL | Port (Host:Container) | Notes |
| :--- | :--- | :--- | :--- |
| **NEXORA HR Application** | `http://localhost:8080` | `8080:80` (Nginx) | Exposes Prometheus metrics at `/metrics` |
| **Prometheus UI** | `http://localhost:9090` | `9090:9090` | Time-series scrape status & PromQL explorer |
| **Grafana UI** | `http://localhost:3100` | `3100:3000` | Pre-provisioned HRIS observability dashboards |
| **phpMyAdmin** | `http://localhost:8081` | `8081:80` | Local database inspection UI |
| **Swagger UI** | `http://localhost:8083` | `8083:8080` | Interactive OpenAPI 3.0 specification viewer |
