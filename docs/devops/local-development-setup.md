# Local Development Environment Setup Guide
## HRIS-SYSTEM — Terraform, Docker, Nginx, PHP, and MySQL

---

## 1. Prerequisites and System Requirements

Ensure the following tools are installed on your Windows 11 workstation:

1. **Docker Desktop for Windows** (v4.20+ with WSL2 backend enabled).
2. **Terraform CLI** (`>= 1.5.0`, installed at `C:\Users\jeril\AppData\Local\Microsoft\WinGet\Packages\Hashicorp.Terraform_...` or in Composer `vendor/bin`).
3. **PowerShell 5.1+** or **PowerShell Core (pwsh 7+)**.
4. **Git for Windows**.

---

## 2. Quick-Start Development Stack

### Step 1: Start the Docker Infrastructure
From the repository root, start the entire containerized application stack:

```powershell
docker compose up -d
```

### Step 2: Verify Running Containers
Verify that all 10 core containers are running and healthy:

```powershell
docker compose ps
```

Expected healthy output:
- `hris_db`: `Up (healthy)`
- `hris_app`: `Up`
- `hris_realtime`: `Up (healthy)`
- `hris_nginx`: `Up`
- `hris_phpmyadmin`: `Up`

### Step 3: Verify Application Endpoints

Open your browser or run PowerShell checks:

| Service | URL | Expected Response | Description |
|---|---|:---:|---|
| **Main HRIS Portal** | `http://localhost:8080/` | `HTTP 200` | Landing page & Employee directory |
| **Authentication** | `http://localhost:8080/login` | `HTTP 200` | HR & Employee login screen |
| **Diagnostics** | `http://localhost:8080/health` | `HTTP 200` | System & MySQL connection latency check |
| **REST Health API** | `http://localhost:8080/api/v1/health` | `HTTP 200` | JSON health probe endpoint |
| **phpMyAdmin** | `http://localhost:8081/` | `HTTP 200` | MySQL database administration interface |
| **Grafana** | `http://localhost:3100/` | `HTTP 200/302` | Metrics and performance dashboards |
| **Prometheus** | `http://localhost:9090/-/healthy` | `HTTP 200` | Telemetry scraping engine |

---

## 3. Terraform Infrastructure Management

Terraform manages declarative infrastructure layers and isolated environments.

### 3.1 Managing the Development Environment
Navigate to the development environment workspace:

```powershell
cd terraform\environments\dev
```

Initialize the provider and modules:
```powershell
terraform init
```

Validate configuration:
```powershell
terraform validate
```

Preview infrastructure plan:
```powershell
terraform plan
```

Apply infrastructure changes:
```powershell
terraform apply
```

Inspect output metadata:
```powershell
terraform output
```

Clean up development resources:
```powershell
terraform destroy
```

---

## 4. Useful Day-to-Day Development Commands

### Live Application Logs
```powershell
# Tail PHP Application logs
docker compose logs -f app

# Tail Nginx access and error logs
docker compose logs -f nginx

# Tail WebSocket Realtime server logs
docker compose logs -f realtime

# Tail Database logs
docker compose logs -f db
```

### Reload Nginx Configuration In-Place
When modifying `docker/nginx/default.conf`, reload Nginx without dropping database connections:
```powershell
docker exec hris_nginx nginx -t
docker exec hris_nginx nginx -s reload
```

### Run Automated Tests Inside Docker Container
```powershell
docker compose exec app php vendor/bin/phpunit
```

### Execute MySQL CLI Inside Container
```powershell
docker compose exec db mysql -u hris_user -phris_password hris_db
```

