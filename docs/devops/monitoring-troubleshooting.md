# Monitoring Troubleshooting and Operational Runbook

## 1. Overview

This runbook provides systematic diagnostic steps and remediation procedures for common operational issues encountered in the HRIS observability stack (Prometheus, Grafana, Exporters, and Application Telemetry).

---

## 2. Quick Health Check Reference

Run these commands from PowerShell or Bash to quickly pinpoint which component is failing:

```bash
# 1. Check all Docker containers
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

# 2. Check Prometheus target health
curl.exe -s http://localhost:9090/api/v1/targets | jq -r '.data.activeTargets[] | "\(.scrapePool): \(.health) (\(.lastError))"'

# 3. Check PHP application metrics endpoint directly
curl.exe -s http://localhost:8080/metrics

# 4. Check Grafana datasource health
curl.exe -s -u admin:admin http://localhost:3100/api/datasources
```

---

## 3. Troubleshooting Scenarios

### Scenario A: Target Shows `DOWN` in Prometheus

#### Symptoms
- Panel displays `DOWN` (Red) on Grafana overview dashboard.
- `GET http://localhost:9090/api/v1/targets` reports `health: "down"` for a specific target with an error message (e.g., `connection refused` or `no such host`).

#### Root Causes & Diagnostics
1. **Container is stopped or crashed:**
   ```bash
   docker ps -a --filter "name=<container_name>"
   # Check logs:
   docker logs --tail 50 <container_name>
   ```
2. **Network isolation issue:**
   - Verify the container is joined to `hris_network`:
   ```bash
   docker inspect <container_name> --format '{{range $k, $v := .NetworkSettings.Networks}}{{$k}}{{end}}'
   # Must return: hris_network
   ```
3. **Internal hostname or port resolution failure:**
   - Execute a DNS probe from the Prometheus container:
   ```bash
   docker exec hris_prometheus wget -qO- http://<target_hostname>:<target_port>/metrics
   ```

#### Remediation
- Restart container: `docker restart <container_name>`
- Verify `docker-compose.yml` service definitions include `networks: - hris_network`.

---

### Scenario B: PHP Application Metrics Endpoint Returns 500 or 404

#### Symptoms
- Prometheus target `hris-app` reports HTTP 404 or 500.
- `curl.exe http://localhost:8080/metrics` returns error or route not found.

#### Root Causes & Diagnostics
1. **Route registration missing:**
   - Inspect [`public/index.php`](../../public/index.php). Verify `$router->get('/metrics', [HomeController::class, 'metrics']);` is defined outside authenticated session gates.
2. **Database connection failure in metrics controller:**
   - In [`app/controllers/HomeController.php`](../../app/controllers/HomeController.php), `metrics()` establishes a PDO connection via `Database::getInstance()->getConnection()`.
   - If MySQL is down, `hris_app_database_connected` is set to 0. If uncaught exceptions occur, check error logs:
   ```bash
   docker logs --tail 50 hris_app
   ```

#### Remediation
- Ensure `Database.php` handles connectivity gracefully without throwing unhandled fatal errors during metric scraping.

---

### Scenario C: Grafana Displays "Data source not found" or Queries Fail

#### Symptoms
- Panels display error: `Data source not found` or `Error reading from data source`.
- `/api/ds/query` returns HTTP 404.

#### Root Causes & Diagnostics
1. **Datasource UID mismatch:**
   - Grafana identifies datasources by a unique ID (`uid`). If dashboards expect `uid: Prometheus` but the provisioned datasource was generated with a random string, panels fail to bind.
   - Inspect active datasources:
   ```bash
   curl.exe -s -u admin:admin http://localhost:3100/api/datasources
   ```
2. **Provisioning version not incremented:**
   - If `docker/grafana/provisioning/datasources/prometheus.yml` was edited but Grafana did not reload it, verify `version:` was incremented (e.g. `version: 2`).

#### Remediation
- Set `uid: Prometheus` and increment `version: 2` in `docker/grafana/provisioning/datasources/prometheus.yml`.
- Restart Grafana:
  ```bash
  docker restart hris_grafana
  ```

---

### Scenario D: Nginx or MySQL Exporter Returns Empty Metrics or Connection Refused

#### Symptoms
- `nginx` target DOWN with `context deadline exceeded` or `connection refused`.
- `mysql` target DOWN with `access denied for user`.

#### Root Causes & Diagnostics
1. **Nginx stub_status disabled:**
   - Test directly inside the network:
   ```bash
   docker exec hris_nginx wget -qO- http://localhost/stub_status
   ```
   - If 404 or 403, verify `docker/nginx/default.conf` contains:
   ```nginx
   location /stub_status {
       stub_status;
       allow all;
   }
   ```
2. **MySQL Exporter credentials invalid:**
   - Inspect environment flags in `docker-compose.yml`:
   ```yaml
   mysqld-exporter:
     environment:
       - DATA_SOURCE_NAME=root:${DB_ROOT_PASSWORD:-root}@tcp(db:3306)/
   ```
   - Check exporter logs:
   ```bash
   docker logs --tail 30 hris_mysqld_exporter
   ```

---

### Scenario E: Dashboards Show "No Data" for Time Range

#### Symptoms
- Stat panels or timeseries show `No Data` despite green target status.

#### Root Causes & Diagnostics
1. **Rate calculation interval too short:**
   - Expressions using `rate(...[1m])` require at least two scrape points within the interval. If Prometheus was recently restarted, allow 30–60 seconds for data accumulation.
2. **Time synchronization discrepancy:**
   - Verify host time matches Docker container clock:
   ```bash
   docker exec hris_prometheus date
   ```
3. **Metric label mismatch:**
   - Verify metric names in PromQL match actual exporter output by querying `http://localhost:9090/api/v1/targets` or testing expressions in the Prometheus query browser (`http://localhost:9090/graph`).

