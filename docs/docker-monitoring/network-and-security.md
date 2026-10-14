# Network Architecture & Security Boundaries

**Project:** Employee HRIS & Leave Management System  
**Document:** `docs/docker-monitoring/network-and-security.md`

---

## 1. Port Mapping & Exposure Strategy

To safeguard the application from unauthorized access while maintaining a developer-friendly local environment, port mappings strictly delineate public ingress from internal services:

| Service | Internal Port | Host Port | Exposure Scope | Rationale |
| :--- | :--- | :--- | :--- | :--- |
| **`nginx`** | `80` | `8080` | **Public Host** | Application ingress gateway for browser access. |
| **`app`** | `80` | *None* | **Internal Network Only** | Apache is shielded behind Nginx; direct host access is blocked. |
| **`db`** | `3306` | `3307` | **Host Loopback / Tools** | Port 3307 prevents conflict with local XAMPP MySQL (3306). |
| **`phpmyadmin`**| `80` | `8081` | **Public Host** | Browser database management tool. |
| **`nginx-exporter`**| `9113` | *None* | **Internal Network Only** | Scraped only by Prometheus; not exposed to host. |
| **`mysqld-exporter`**| `9104` | *None* | **Internal Network Only** | Scraped only by Prometheus; not exposed to host. |
| **`prometheus`** | `9090` | `9090` | **Public Host** | Time-series query interface for local monitoring. |
| **`grafana`** | `3000` | `3100` | **Public Host** | Avoids port collision with BrowserSync on host port 3000. |

---

## 2. Docker Network Isolation

All services reside on a dedicated user-defined bridge network:

```yaml
networks:
  hris_network:
    name: hris_network
    driver: bridge
```

- **Service Discovery:** Containers resolve peer services exclusively by Docker Compose service names (`nginx`, `app`, `db`, `prometheus`, `grafana`, `nginx-exporter`, `mysqld-exporter`).
- **No Hardcoded IPs:** IP addresses are dynamically assigned by the Docker daemon within the bridge subnet.

---

## 3. Least-Privilege Exporter Authentication

Rather than granting root or administrative privileges, the MySQL Prometheus exporter uses a dedicated monitoring account with strictly limited read privileges:

```sql
CREATE USER IF NOT EXISTS 'exporter'@'%' IDENTIFIED BY 'exporter_password' WITH MAX_USER_CONNECTIONS 3;
GRANT PROCESS, REPLICATION CLIENT, SELECT ON *.* TO 'exporter'@'%';
FLUSH PRIVILEGES;
```

### Privileges Justification
- `PROCESS`: Grants access to `SHOW GLOBAL STATUS` and query threads without exposing query parameters containing sensitive employee data.
- `REPLICATION CLIENT`: Allows inspection of master/replica replication health states.
- `SELECT ON *.*`: Allows inspection of performance schema and information schema table sizes and buffer pool metrics.
- `MAX_USER_CONNECTIONS 3`: Hard limit ensures exporter polling cannot exhaust database connection pools or starve application transactions.
