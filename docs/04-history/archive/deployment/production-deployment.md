# Production Deployment Guide

## Employee HRIS & Leave Management System

### 1. Overview & System Requirements
The Employee HRIS & Leave Management System is architected as a lightweight, framework-independent enterprise application built on native PHP 8.x and MySQL 8.x / MariaDB with PDO.

#### Minimum Server Requirements
- **Operating System:** Linux (Ubuntu 22.04 LTS / Debian 12 / Rocky Linux 9) or Windows Server (Apache 2.4)
- **Web Server:** Apache HTTP Server 2.4+ with `mod_rewrite`, `mod_headers`, and `mod_expires` enabled
- **PHP Version:** PHP 8.1 or higher (PHP 8.2+ recommended)
- **PHP Extensions Required:** `pdo`, `pdo_mysql`, `mbstring`, `session`, `json`, `filter`, `date`
- **Database:** MySQL 8.0+ or MariaDB 10.6+ (InnoDB engine, `utf8mb4` encoding)
- **SSL/TLS Certificate:** Valid HTTPS certificate (Let's Encrypt / DigiCert)

---

### 2. Virtual Host & Web Server Configuration
The production web server **must serve strictly from the `public/` directory as the DocumentRoot**. Internal application directories (`app/`, `core/`, `config/`, `database/`, `scratch/`, `docs/`) and `.env` must never be directly accessible over HTTP.

#### Recommended Apache VirtualHost Configuration
```apache
<VirtualHost *:80>
    ServerName hris.example.com
    ServerAdmin admin@example.com

    # Enforce HTTPS Redirect
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName hris.example.com
    ServerAdmin admin@example.com

    # Set DocumentRoot strictly to public directory
    DocumentRoot /var/www/hris/public

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/hris.example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/hris.example.com/privkey.pem

    <Directory /var/www/hris/public>
        Options -Indexes -MultiViews +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Strictly deny access to parent project directory and hidden files
    <Directory /var/www/hris>
        <FilesMatch "^\.">
            Require all denied
        </FilesMatch>
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/hris_error.log
    CustomLog ${APACHE_LOG_DIR}/hris_access.log combined
</VirtualHost>
```

---

### 3. Step-by-Step Production Deployment Procedure

#### Step 1: Deploy Source Code
Clone or unpack the release artifact into the target production directory:
```bash
git clone https://github.com/organization/Employee-HRIS-Leave-Management-System.git /var/www/hris
cd /var/www/hris
```

#### Step 2: Configure Environment
Copy `.env.example` to `.env` and populate production-grade secrets:
```bash
cp .env.example .env
chmod 600 .env
chown www-data:www-data .env
```
Set `APP_ENV=production` and specify dedicated database credentials.

#### Step 3: Set Filesystem Permissions
Ensure correct permissions so the web server cannot write to executable code:
```bash
# Set base directory ownership
chown -R www-data:www-data /var/www/hris

# Restrict permissions: directories 755, files 644
find /var/www/hris -type d -exec chmod 755 {} \;
find /var/www/hris -type f -exec chmod 644 {} \;

# Restrict sensitive configuration and internal files
chmod 600 /var/www/hris/.env
chmod 700 /var/www/hris/config
```

#### Step 4: Database Migration & Schema Verification
Import or migrate the database schema using the migration script:
```bash
mysql -u hris_user -p -h 127.0.0.1 hris_db < database/schema.sql
```

#### Step 5: Smoke Testing & Verification
Execute the release verification script:
```bash
php scratch/test_rbac_suite.php
php scratch/test_phase7_http.php
```

#### Step 6: Verify HTTP Headers and Directory Protection
Confirm that requesting internal paths returns HTTP 403 Forbidden:
```bash
curl -I https://hris.example.com/app/
curl -I https://hris.example.com/.env
curl -I https://hris.example.com/config/config.php
```
Ensure all return `403 Forbidden` or redirect to `public/`.
