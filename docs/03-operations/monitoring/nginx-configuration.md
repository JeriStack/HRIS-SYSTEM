# Nginx Reverse Proxy Configuration & Ingress Architecture

**Project:** Employee HRIS & Leave Management System  
**Document:** `docs/docker-monitoring/nginx-configuration.md`

---

## 1. Overview

Nginx operates as the edge reverse proxy for the HRIS environment. It intercepts incoming client traffic on host port `8080` (or configured `APP_PORT`) and proxies it internally to the Apache/PHP backend (`app:80`).

---

## 2. Configuration Analysis (`docker/nginx/default.conf`)

### Upstream Definition
```nginx
upstream hris_backend {
    server app:80;
    keepalive 32;
}
```
- **Connection Reuse:** Employs persistent HTTP/1.1 keepalive connections to Apache, reducing TCP connection handshake overhead between proxy and app.

### Proxy Headers & Request Forwarding
```nginx
server {
    listen 80;
    server_name localhost _;

    client_max_body_size 64M;

    proxy_connect_timeout 60s;
    proxy_send_timeout 60s;
    proxy_read_timeout 60s;

    proxy_http_version 1.1;
    proxy_set_header Connection "";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Host $host;
    proxy_set_header X-Forwarded-Port $server_port;

    proxy_buffers 16 16k;
    proxy_buffer_size 16k;

    location / {
        proxy_pass http://hris_backend;
    }
}
```

- **Header Preservation:** Preserves `Host`, client IP (`X-Real-IP`, `X-Forwarded-For`), and port so PHP `base_url()` and `redirect()` build accurate fully qualified URLs.
- **Upload Limit:** Configured with `client_max_body_size 64M` to match PHP's `upload_max_filesize` and `post_max_size`.
- **Buffer Sizing:** 16x 16k proxy buffers accommodate large HTML responses without writing temporary buffer files to disk.

---

## 3. Internal Metrics Server (`stub_status`)

```nginx
server {
    listen 8082;
    server_name localhost _;

    access_log off;

    location /stub_status {
        stub_status;
        access_log off;
    }

    location /healthz {
        access_log off;
        default_type text/plain;
        return 200 "healthy\n";
    }
}
```

- **Port Isolation:** Listens strictly on internal port `8082`, which is **not** exposed in `ports:` to the host system.
- **Access Restrictions:** Reachable only by internal containers on `hris_network` (such as `nginx-exporter`).
- **Telemetry Data Provided:**
  - Active connections
  - Accepted and handled connection totals
  - Requests processed
  - Current connection states: `Reading`, `Writing`, `Waiting`
