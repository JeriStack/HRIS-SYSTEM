# Docker, Nginx, and PHP Application Architecture
## HRIS-SYSTEM — Local Infrastructure and Reverse Proxy Topology

---

## 1. Architectural Overview

The **HRIS-SYSTEM** runs as a coordinated containerized architecture on Docker Desktop (Windows 11 / WSL2). It employs **Nginx** as an edge reverse proxy and WebSocket gateway, terminating client connections on port `8080` and routing them to the internal **Apache + PHP 8.5** application runtime and the **Ratchet WebSocket** real-time server, backed by an isolated **MySQL 8.0** database container.

```
Windows 11 Host (Developer Workstation)
│
├── Browser / HTTP Client (http://localhost:8080)
│   ├── Static Assets & Web Pages  ──►  GET /
│   ├── Dynamic AJAX & MVC Routes ──►  POST /login, /leave/apply
│   ├── Health & Telemetry        ──►  GET /health, /api/v1/health
│   └── Real-time WebSocket       ──►  ws://localhost:8080/ws
│
└── Docker Engine (Docker Desktop 4.97.0 / Engine 29.9.2)
    │
    └── User-Defined Bridge Network (hris_network: 172.20.0.0/16)
        │
        ├── [hris_nginx] (nginx:alpine)
        │   ├── Reverse Proxy (Port 80 ──► Host 8080)
        │   ├── Metrics Server (Port 8082 ──► Internal only)
        │   ├── Security Gatekeeper (Blocks .env, .git, .sql, .htaccess)
        │   └── Upstream Router:
        │       ├── hris_backend ──► app:80 (HTTP 1.1 keepalive)
        │       └── /ws          ──► realtime:8090 (WebSocket Upgrade)
        │
        ├── [hris_app] (php:8.5.12-apache-trixie)
        │   ├── Native PHP MVC Application Front Controller
        │   ├── DocumentRoot: /var/www/html/public
        │   ├── Internal Listen: port 80 (Not published to host)
        │   └── Database Client: PDO MySQL connecting to db:3306
        │
        ├── [hris_realtime] (php:8.5.12-apache-trixie CLI)
        │   ├── Ratchet WebSocket Server (bin/realtime-server.php)
        │   ├── Internal Listen: port 8090 (Not published to host)
        │   └── Dispatches profile, leave, and dashboard notifications
        │
        ├── [hris_db] (mysql:8.0)
        │   ├── MySQL 8.0 Enterprise Relational Store
        │   ├── Internal Listen: port 3306 (Attached to hris_network)
        │   ├── Host Access: port 3307 (Development tool access)
        │   └── Persistent Storage: Docker named volume `db_data`
        │
        └── [hris_phpmyadmin] (phpmyadmin:latest)
            ├── Development Database GUI (Port 80 ──► Host 8081)
            └── Internal link to db:3306
```

---

## 2. Serving Model: Apache Behind Nginx

### Why Apache + PHP 8.5 Behind Nginx?
The application utilizes the official `php:8.5.12-apache-trixie` image:
1. **Front-Controller Preservation:** Apache handles `.htaccess` URL rewriting (`mod_rewrite`) seamlessly, routing all non-file requests to `public/index.php`.
2. **Nginx Edge Layer:** Nginx acts as the performant edge reverse proxy, absorbing slow client connections, enforcing connection keepalives, managing WebSocket protocol upgrades, and blocking malicious request patterns before they hit PHP.
3. **Internal Security:** Port `80` of `hris_app` is **never published directly to the host**. All external traffic must pass through Nginx.

```
Client Request ──► Nginx:8080 (listen 80)
                        │
                        ├── location / ──► proxy_pass http://app:80
                        │                       │
                        │                       ▼
                        │                  Apache 2.4
                        │                       │
                        │                       ▼
                        │                  /var/www/html/public/index.php
                        │                       │
                        │                       ▼
                        │                  Core\Router ──► Controller ──► View
                        │
                        └── location = /ws ──► proxy_pass http://realtime:8090
                                                    │
                                                    ▼
                                               Ratchet Server (bin/realtime-server.php)
```

---

## 3. Network Segmentation & Port Allocations

| Service Name | Container Name | Internal Port | Host Port Binding | Network Attachment | Security Policy |
|---|---|:---:|:---:|---|---|
| **Nginx** | `hris_nginx` | `80`, `8082` | `8080:80` | `hris_network` | Primary public gateway. Metrics port 8082 is private. |
| **PHP App** | `hris_app` | `80` | *None* | `hris_network` | Internal only; reachable solely via Nginx upstream `app:80`. |
| **Realtime** | `hris_realtime` | `8090` | *None* | `hris_network` | Internal only; reachable solely via Nginx `/ws`. |
| **MySQL** | `hris_db` | `3306` | `3307:3306` | `hris_network` | Published on 3307 for local dev IDEs/clients (avoids XAMPP 3306). |
| **phpMyAdmin** | `hris_phpmyadmin`| `80` | `8081:80` | `hris_network` | Restricted to development environments only. |
| **Prometheus** | `hris_prometheus`| `9090` | `9090:9090` | `hris_network` | Internal telemetry scraping; development dashboard. |
| **Grafana** | `hris_grafana` | `3000` | `3100:3000` | `hris_network` | Telemetry visualization. |

---

## 4. Terraform Infrastructure Management Layer

Terraform operates as an out-of-band infrastructure orchestrator:
- **Connection Method:** Named pipe `npipe:////./pipe/docker_engine` on Windows 11.
- **Provider:** `kreuzwerker/docker` (version `~> 3.0.2`).
- **Resource Ownership:** Docker Compose owns the live application container mesh (`hris_nginx`, `hris_app`, `hris_db`). Terraform manages the declarative infrastructure definitions, environment configurations (`terraform/environments/dev/`), and isolated auxiliary infrastructure.

