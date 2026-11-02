# DevOps and Infrastructure Troubleshooting Guide
## HRIS-SYSTEM — Docker, Nginx, PHP, MySQL, and Terraform

---

## 1. Nginx and Web Gateway Issues

### 1.1 HTTP 502 Bad Gateway
- **Symptom:** Browser displays "502 Bad Gateway" when navigating to `http://localhost:8080/`.
- **Root Cause:** Nginx cannot reach the upstream Apache/PHP backend (`app:80`). Either `hris_app` is still starting, crashed, or stopped.
- **Resolution Steps:**
  1. Check container status:
     ```powershell
     docker compose ps app
     ```
  2. Inspect PHP error logs:
     ```powershell
     docker compose logs --tail=50 app
     ```
  3. Restart the application container:
     ```powershell
     docker compose restart app
     ```

### 1.2 Nginx Configuration Test Failure (`nginx -t`)
- **Symptom:** `docker exec hris_nginx nginx -s reload` fails or container crashes on startup.
- **Resolution Steps:**
  1. Run the built-in syntax checker:
     ```powershell
     docker exec hris_nginx nginx -t
     ```
  2. Review exact line number reported in error output.
  3. Validate upstream addresses and semicolon terminations in `docker/nginx/default.conf`.

---

## 2. Database Connectivity Issues (MySQL)

### 2.1 `SQLSTATE[HY000] [2002] Connection refused`
- **Symptom:** PHP throws PDO connection exception on page load or `/health`.
- **Root Cause:** MySQL container `hris_db` is still initializing or MySQL daemon has not completed startup routines.
- **Resolution Steps:**
  1. Check MySQL readiness health check:
     ```powershell
     docker inspect --format='{{json .State.Health.Status}}' hris_db
     ```
     *(Must return `"healthy"`).*
  2. If starting up, wait 10 seconds for initial database scripts to execute.
  3. Verify port mapping: inside Docker network, PHP connects to `db:3306`; host tools connect to `localhost:3307`.

### 2.2 Host Port 3307 Conflict
- **Symptom:** Docker Compose reports `driver failed programming external connectivity on endpoint hris_db: Bind for 0.0.0.0:3307 failed: port is already allocated`.
- **Resolution Steps:**
  1. Identify process listening on port 3307:
     ```powershell
     netstat -ano | findstr :3307
     ```
  2. Or change `DB_HOST_PORT` in your local `.env` (e.g., `DB_HOST_PORT=3308`) and restart:
     ```powershell
     docker compose up -d
     ```

---

## 3. Real-Time WebSocket Server Issues (`/ws`)

### 3.1 Browser Console: `WebSocket connection to 'ws://localhost:8080/ws' failed`
- **Root Cause:** Ratchet CLI daemon is not listening on internal port 8090, or Nginx is not negotiating the HTTP Upgrade handshake.
- **Resolution Steps:**
  1. Verify `hris_realtime` container is active:
     ```powershell
     docker compose ps realtime
     ```
  2. Check WebSocket logs for ticket authentication or origin errors:
     ```powershell
     docker compose logs --tail=50 realtime
     ```
  3. Ensure `WS_ALLOWED_ORIGINS` in `.env` includes `http://localhost:8080`.

---

## 4. Terraform Execution Troubleshooting

### 4.1 `terraform: The term 'terraform' is not recognized`
- **Root Cause:** Active terminal process was started prior to WinGet installation, possessing a stale `$env:PATH`.
- **Resolution:**
  Refresh the path in your active PowerShell session:
  ```powershell
  $env:Path = [System.Environment]::GetEnvironmentVariable("Path","User") + ";" + [System.Environment]::GetEnvironmentVariable("Path","Machine")
  ```
  *(Or close and reopen the terminal tab in your IDE).*

### 4.2 `Error: open //./pipe/docker_engine: The system cannot find the file specified`
- **Root Cause:** Docker Desktop is stopped or named pipe permissions are restricted.
- **Resolution:**
  1. Verify Docker Desktop is running from system tray.
  2. Verify pipe availability:
     ```powershell
     [System.IO.Directory]::GetFiles("\\.\\pipe\\") | Select-String "docker_engine"
     ```
  3. In `terraform/variables.tf`, confirm `docker_host` is set to `"npipe:////./pipe/docker_engine"`.

### 4.3 Provider Installation Timeout (`wsarecv: A connection attempt failed`)
- **Root Cause:** Transient network drop while downloading binary from GitHub release mirrors.
- **Resolution:**
  Rerun initialization:
  ```powershell
  terraform init
  ```

