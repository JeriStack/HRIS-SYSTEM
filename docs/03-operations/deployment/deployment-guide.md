# NEXORA HR — Deployment & Operations Guide

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Version:** 3.1  
**Status:** Authoritative Operations & Deployment Guide  
**Date:** November 2026  
**Document Owner:** DevOps & Infrastructure Engineering Team  

---

## 1. Deployment Topology & Environments

NEXORA HR supports two primary deployment topologies:
1. **Containerized Multi-Service (Recommended / Default):** Docker Compose orchestrating Nginx (:8080), Apache/PHP 8.5 (:80), MySQL 8.0 (:3306), Ratchet WebSocket Server (:8090), Prometheus (:9090), and Grafana (:3000).
2. **Direct Linux VPS / Bare-Metal:** Ubuntu 22.04 / 24.04 LTS host with native Nginx, PHP-FPM / Apache, MySQL 8.0, and Systemd-managed background daemons.

---

## 2. Docker Compose Deployment (Local & Staging)

### 2.1 Prerequisites
* Docker Engine 24.0+ and Docker Compose v2.20+
* Git
* Allocated RAM: Minimum 2GB (4GB recommended)

### 2.2 Environment Setup
1. Clone the repository and navigate to the project root:
   ```bash
   git clone https://github.com/your-org/HRIS-SYSTEM.git
   cd HRIS-SYSTEM
   ```
2. Copy and configure the environment variables:
   ```bash
   cp .env.example .env
   ```
3. Verify required `.env` values:
   ```ini
   APP_ENV=production
   APP_KEY=base64:your-generated-cryptographic-key-here
   APP_URL=http://localhost:8080

   DB_CONNECTION=mysql
   DB_HOST=db
   DB_PORT=3306
   DB_DATABASE=hris_db
   DB_USERNAME=hris_user
   DB_PASSWORD=your_secure_db_password

   MAIL_MAILER=smtp
   MAIL_HOST=smtp.sendgrid.net
   MAIL_PORT=587
   MAIL_USERNAME=apikey
   MAIL_PASSWORD=your_sendgrid_api_key
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@yourdomain.com
   MAIL_FROM_NAME="NEXORA HR"

   REALTIME_HOST=0.0.0.0
   REALTIME_PORT=8090
   REALTIME_CLIENT_URL=ws://localhost:8080/ws
   ```

### 2.3 Starting Services
Build and start all containers in detached mode:
```bash
docker compose up -d --build
```

### 2.4 Verifying Container Health
```bash
docker compose ps
```
All containers (`web`, `app`, `db`, `realtime`, `nginx-exporter`, `mysqld-exporter`, `prometheus`, `grafana`) should report `healthy` or `running`.

### 2.5 Running Database Migrations & Seeds
```bash
# Run schema migrations (including 2026_11_20_create_hr_to_admin_support_module.sql)
docker compose exec app php bin/migrate.php

# Ensure private attachment directories are writable by www-data and protected by .htaccess
docker compose exec app mkdir -p storage/uploads/support_attachments storage/uploads/hr_support_attachments

# (Optional) Seed initial demo workforce
docker compose exec app php database/seeds/DatabaseSeeder.php
```

---

## 3. Production Cloud VPS Deployment (Ubuntu 24.04 LTS)

### 3.1 Host Preparation
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx php8.5-fpm php8.5-mysql php8.5-curl php8.5-mbstring php8.5-xml php8.5-zip mysql-server certbot python3-certbot-nginx
```

### 3.2 Web Server Reverse Proxy (Nginx)
Configure `/etc/nginx/sites-available/nexora-hr`:
```nginx
server {
    listen 80;
    server_name hris.yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name hris.yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/hris.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/hris.yourdomain.com/privkey.pem;

    root /var/www/hris/public;
    index index.php index.html;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Static Assets Cache
    location /assets/ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }

    # Main Application
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM FastCGI Handler
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # WebSocket Reverse Proxy
    location /ws {
        proxy_pass http://127.0.0.1:8090;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;
    }
}
```

### 3.3 Managing Daemons via Systemd

#### Ratchet WebSocket Daemon (`/etc/systemd/system/nexora-websocket.service`)
```ini
[Unit]
Description=NEXORA HR Ratchet WebSocket Server
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/hris
ExecStart=/usr/bin/php bin/realtime-server.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

#### Transactional Outbox Worker (`/etc/systemd/system/nexora-outbox.service`)
```ini
[Unit]
Description=NEXORA HR Outbox Polling Worker
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/hris
ExecStart=/usr/bin/php bin/outbox-worker.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Enable and start services:
```bash
sudo systemctl daemon-reload
sudo systemctl enable --now nexora-websocket
sudo systemctl enable --now nexora-outbox
```

---

## 4. Backup & Disaster Recovery

### 4.1 Daily Automated MySQL Backup
Create automated cron job (`/etc/cron.daily/backup-nexora-db`):
```bash
#!/bin/bash
BACKUP_DIR="/var/backups/nexora_hr"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
mkdir -p "$BACKUP_DIR"

mysqldump -u root -p"rootpassword" --single-transaction --quick --routines --triggers hris_db | gzip > "$BACKUP_DIR/hris_db_$TIMESTAMP.sql.gz"

# Retain backups for 30 days
find "$BACKUP_DIR" -type f -name "*.sql.gz" -mtime +30 -delete
```
Make executable: `chmod +x /etc/cron.daily/backup-nexora-db`.

### 4.2 Restoring from Backup
```bash
gunzip < /var/backups/nexora_hr/hris_db_YYYYMMDD_HHMMSS.sql.gz | mysql -u root -p"rootpassword" hris_db
```

### 4.3 Docker Persistent Volumes
> [!CAUTION]
> The database container stores persistent data in the `db_data` volume. Never run `docker compose down -v` or `docker volume prune` in production, as this permanently destroys database tables.

