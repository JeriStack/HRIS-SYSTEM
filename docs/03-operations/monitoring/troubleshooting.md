# Troubleshooting Playbook & Diagnostic Reference

**Project:** Employee HRIS & Leave Management System  
**Document:** `docs/docker-monitoring/troubleshooting.md`

---

## 1. Diagnostic Decision Tree

```
                       [ Issue Detected ]
                               |
            +------------------+------------------+
            |                                     |
    [ Container Down / Error ]           [ Endpoint Unreachable ]
            |                                     |
            v                                     v
1. docker compose ps                  1. Check port collisions
2. docker compose logs <service>         (netstat -ano | findstr <port>)
3. docker compose config              2. Verify service health
                                         (docker compose ps)
                                      3. Verify Docker DNS
                                         (docker compose exec app ping db)
```

---

## 2. Common Scenarios & Resolutions

### Scenario A: Host Port Already Allocated (e.g., Port 3000 Collision)
- **Symptom:** `Error response from daemon: Bind for 0.0.0.0:3000 failed: port is already allocated`
- **Cause:** Local development tools like BrowserSync or React dev server are occupying host port `3000`.
- **Resolution:**
  1. Grafana is configured by default to bind to host port `3100` (`${GRAFANA_PORT:-3100}:3000`).
  2. If customization is desired, set `GRAFANA_PORT=3200` in `.env` and run `docker compose up -d`.

### Scenario B: MySQL Exporter Fails on Startup
- **Symptom:** `hris_mysqld_exporter` restarts with `failed to validate config: no user specified in section or parent`
- **Cause:** mysqld_exporter v0.15+ requires credentials passed via `--config.my-cnf` instead of deprecated URI flags.
- **Resolution:**
  1. Ensure `docker/mysql/my-exporter.cnf` exists and defines:
     ```ini
     [client]
     user = exporter
     password = exporter_password
     host = db
     port = 3306
     ```
  2. Verify `mysqld-exporter` mounts the file: `volumes: - ./docker/mysql/my-exporter.cnf:/etc/.my.cnf:ro`

### Scenario C: Nginx Reverse Proxy Returns 502 Bad Gateway
- **Symptom:** Visiting `http://localhost:8080` displays Nginx `502 Bad Gateway`.
- **Cause:** The Apache `app` backend container is either stopped, unhealthy, or rebuilding.
- **Resolution:**
  1. Inspect app logs: `docker compose logs --tail=50 app`
  2. Restart the app container: `docker compose restart app`
  3. Verify Nginx can resolve `app`: `docker compose exec nginx ping -c 2 app`

### Scenario D: Prometheus Target Reports `DOWN`
- **Symptom:** Visiting `http://localhost:9090/targets` displays red `DOWN` status for a scrape job.
- **Resolution:**
  1. For `nginx`: Verify `nginx-exporter` is running and can scrape Nginx:
     `docker compose exec nginx-exporter wget -qO- http://nginx:8082/stub_status`
  2. For `mysql`: Verify `mysqld-exporter` can ping MySQL:
     `docker compose exec mysqld-exporter /bin/mysqld_exporter --config.my-cnf=/etc/.my.cnf --version`
  3. Reload Prometheus configuration: `docker compose exec prometheus kill -HUP 1`

---

## 3. Useful Diagnostic Commands

```bash
# Check container status
docker compose ps

# Check logs for specific service
docker compose logs --tail=100 nginx
docker compose logs --tail=100 app
docker compose logs --tail=100 db
docker compose logs --tail=100 prometheus
docker compose logs --tail=100 grafana
docker compose logs --tail=100 mysqld-exporter
docker compose logs --tail=100 nginx-exporter

# Execute interactive shell in app container
docker compose exec app bash

# Query Prometheus targets status via CLI
curl -s http://localhost:9090/api/v1/targets | jq .

# Query Grafana health via CLI
curl -s http://localhost:3100/api/health
```
