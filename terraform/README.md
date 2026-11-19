# Terraform Infrastructure as Code (IaC) Guide
## HRIS-SYSTEM — Local Docker Infrastructure Foundation

---

## 1. Purpose of Terraform in the HRIS Project

Terraform introduces **Infrastructure as Code (IaC)** to the **HRIS-SYSTEM** repository. Its primary purpose is to:
- Define infrastructure resources declaratively using standard HashiCorp Configuration Language (HCL).
- Track infrastructure state reproducibly and detect configuration drift.
- Automate multi-environment provisioning (local development, test, staging, and future cloud environments) using consistent workflows (`init`, `validate`, `plan`, `apply`, `destroy`).
- Provide an auditable, version-controlled baseline for container networks, storage volumes, and service configurations.

---

## 2. Comparing Terraform, Docker, and Docker Compose

Understanding the distinct role of each technology in the HRIS stack prevents operational conflicts:

| Dimension | Docker | Docker Compose | Terraform |
|---|---|---|---|
| **Primary Scope** | Container runtime & image builder | Local multi-container developer orchestration | Infrastructure lifecycle management & provisioning |
| **Typical Use Case** | Building & running individual images (`hris-system-app`) | Rapid local dev iteration, source mounts, hot-reloading | Provisioning networks, volumes, databases, cloud resources |
| **State Tracking** | Ephemeral host daemon state | Inspects live container metadata on the host | Maintains deterministic state file (`terraform.tfstate`) |
| **Cloud Portability** | Single-host only | Single-host local engine (Swarm for multi-node) | Universal: Docker, AWS, GCP, Azure, Kubernetes |
| **Drift Detection** | None | Limited to container existence | Comprehensive plan-time diffing against desired state |

---

## 3. Required Software and Supported Versions

To use Terraform with the HRIS project on Windows 11:

| Component | Minimum Version | Tested Version | Verification Command |
|---|:---:|:---:|---|
| **Terraform CLI** | `>= 1.5.0` | `1.16.6` | `terraform version` |
| **Docker Desktop** | `>= 4.20.0` | `4.97.0 (Engine 29.9.2)` | `docker version` |
| **kreuzwerker/docker** | `~> 3.0.2` | `3.0.2` | Inspect `.terraform.lock.hcl` |
| **PowerShell** | `5.1+` / `7+` | PowerShell 5.1 / pwsh | `$PSVersionTable.PSVersion` |

---

## 4. Environment Verification

### 4.1 Verify Docker Engine Availability
Before running Terraform, ensure Docker Desktop is started and responsive:

```powershell
docker version
docker info
```
Look for:
- `Server: Docker Desktop`
- `Engine: Version ... (healthy)`
- `OSType: linux`

### 4.2 Verify Terraform Installation
Verify that the Terraform binary is reachable in your execution path:

```powershell
terraform version
```
*(If installed via WinGet and not yet in the active terminal session, refresh your path:)*
```powershell
$env:Path = [System.Environment]::GetEnvironmentVariable("Path","User") + ";" + [System.Environment]::GetEnvironmentVariable("Path","Machine")
terraform version
```

---

## 5. Standard Terraform Workflow

### 5.1 Directory Layout
```text
terraform/
├── README.md                      # Comprehensive IaC guide
├── .gitignore                     # State and cache exclusions
├── .terraform.lock.hcl            # Pinned provider dependency lock
├── versions.tf                    # Terraform CLI and provider constraints
├── providers.tf                   # Docker provider configuration
├── variables.tf                   # Input variable definitions
├── main.tf                        # Root demonstration resources / reusable module
├── outputs.tf                     # Output attribute declarations
├── terraform.tfvars.example       # Template parameter overrides
└── environments/
    └── dev/                       # Development environment tier
        ├── versions.tf            # Pinned dev provider constraints
        ├── providers.tf           # Docker daemon connection
        ├── variables.tf           # Dev-specific variable declarations
        ├── main.tf                # Dev module invocation
        ├── outputs.tf             # Dev output exposure
        └── terraform.tfvars.example # Dev parameter template
```

### 5.2 Workflow Commands

Always run Terraform commands from your target directory (`terraform/` or `terraform/environments/dev/`):

```powershell
cd c:\xampp\htdocs\GitHub_Clone_File_Projects\HRIS-SYSTEM\terraform
# Or for dev environment:
# cd c:\xampp\htdocs\GitHub_Clone_File_Projects\HRIS-SYSTEM\terraform\environments\dev
```

### Step 1: Initialize Working Directory (`init`)
Downloads provider plugins (such as `kreuzwerker/docker`) and configures the local backend:

```powershell
terraform init
```
*Note: This command writes and validates the `.terraform.lock.hcl` provider lock file.*

### Step 2: Canonical Formatting Check (`fmt`)
Formats all HCL files according to official HashiCorp guidelines:

```powershell
# Check for unformatted files (read-only):
terraform fmt -check -recursive

# Apply formatting in-place:
terraform fmt -recursive
```

### Step 3: Syntax and Schema Validation (`validate`)
Verifies provider schemas, argument names, and expression types without connecting to Docker:

```powershell
terraform validate
```
*Expected output: `Success! The configuration is valid.`*

### Step 4: Preview Execution Plan (`plan`)
Inspects the live state of Docker, compares it against `main.tf`, and generates an execution plan:

```powershell
terraform plan
```
*Read-only command. Inspect the resources to be added (`+`), modified (`~`), or destroyed (`-`).*

### Step 5: Provision the Isolated Test Resource (`apply`)
Applies only the reviewed changes:

```powershell
terraform apply
```
*(Review the plan, then type `yes` to confirm).*

---

## 6. Inspecting Deployed Infrastructure

### 6.1 Verify with Docker CLI
Confirm that the isolated test container and network are active:

```powershell
# Inspect the running test container
docker ps --filter "name=hris_tf_test_sandbox"

# Verify isolated network attachment
docker network inspect hris_tf_test_network
```

### 6.2 Inspect Terraform State and Outputs
View structured outputs and inspected attributes without exposing secrets:

```powershell
# View all output values
terraform output

# View specific output
terraform output test_container_id

# Inspect full recorded state (read-only)
terraform show
```

### 6.3 Verify Zero Configuration Drift
Running a second plan confirms that real infrastructure matches configuration:

```powershell
terraform plan
```
*Expected output: `No changes. Your infrastructure matches the configuration.`*

---

## 7. Clean Up Isolated Resources (`destroy`)

When finished with the demonstration, destroy the isolated test container and network:

```powershell
# Preview destroy plan first
terraform plan -destroy

# Apply destruction safely
terraform destroy
```

Confirm removal:
```powershell
docker ps --filter "name=hris_tf_test_sandbox"
```

---

## 8. Docker Provider Connection & Troubleshooting

### Windows Named Pipe Configuration
On Windows 11 with Docker Desktop, Terraform communicates with Docker using Windows named pipes:
```hcl
provider "docker" {
  host = "npipe:////./pipe/docker_engine"
}
```
*Alternative named pipe if custom context is active: `npipe:////./pipe/dockerDesktopLinuxEngine`.*

### Common Issues & Troubleshooting

1. **`error during connect: open //./pipe/docker_engine: The system cannot find the file specified`**
   - **Cause:** Docker Desktop is stopped or still starting.
   - **Fix:** Open Docker Desktop from the Start Menu, wait until the whale icon is steady (Engine running), and retry `docker ps`.

2. **`Error: Failed to install provider: wsarecv connection attempt failed`**
   - **Cause:** Transient network timeout while downloading from GitHub release mirrors.
   - **Fix:** Rerun `terraform init`.

3. **Port Conflicts (`address already in use`)**
   - **Safeguard:** The demonstration configuration in `main.tf` intentionally publishes **zero host ports**. Never bind `8080`, `3307`, `9090`, `3100`, or `8081`, as these are reserved for the running Docker Compose HRIS services.

---

## 9. Resource Ownership Model

To prevent catastrophic resource contention, an explicit ownership model is established:

```
┌─────────────────────────────────────────────────────────────┐
│                    RESOURCE OWNERSHIP                       │
├──────────────────────────────┬──────────────────────────────┤
│    Docker Compose Owned      │       Terraform Owned        │
│    (Application Stack)       │      (IaC Demonstration)     │
├──────────────────────────────┼──────────────────────────────┤
│ • hris_nginx (8080)          │ • hris_tf_test_sandbox       │
│ • hris_app (80)              │ • hris_tf_test_network       │
│ • hris_realtime (8090)       │ • alpine:3.21.3 image        │
│ • hris_db (3307)             │                              │
│ • hris_grafana (3100)        │                              │
│ • hris_prometheus (9090)     │                              │
│ • hris_phpmyadmin (8081)     │                              │
│ • hris_network (bridge)      │                              │
│ • hris_db_data (volume)      │                              │
└──────────────────────────────┴──────────────────────────────┘
```

### Architectural Policy:
- **No Overlapping Ownership:** Terraform and Docker Compose will **never** manage the same container, volume, or network.
- **Data Protection:** Persistent database volumes (`hris_db_data`) remain strictly under Compose ownership to eliminate risk of accidental deletion via `terraform destroy`.

### Future Evolution Paths:
- **Option A (Hybrid Architecture — Recommended for Local Dev):** Compose manages fast-reloading application services (`app`, `realtime`, `nginx`); Terraform manages shared auxiliary infrastructure (monitoring networks, shared logging endpoints).
- **Option B (Full IaC Migration — Staging & Production):** Gradual migration of services into modular Terraform definitions after formal staging validation. Never execute Option B without a verified migration plan and tested backups.

### Opt-in Nginx Integration (Hybrid, implemented)

By default the sandbox stays isolated on `hris_tf_test_network`. Setting `attach_to_hris_network=true` additionally:

1. **Network (read-only):** looks up Compose's `hris_network` with a `data "docker_network"` source and attaches the sandbox with the DNS alias `tf-sandbox`. Terraform never creates, changes, or destroys that network (`terraform destroy` leaves it intact).
2. **Responder:** runs a static busybox `httpd` on port `8081` as user `nobody`.
3. **Nginx:** `docker/nginx/default.conf` proxies `/terraform-sandbox/` to `http://tf-sandbox:8081` (resolved at request time, so Nginx still starts if the sandbox is absent and simply returns 502). Access is limited to loopback/private ranges.

```powershell
docker compose up -d                                   # hris_network must exist first
terraform -chdir=terraform apply -var attach_to_hris_network=true
curl http://localhost:8080/terraform-sandbox/          # -> "hris terraform sandbox ok"
```

> **Security:** attaching removes the sandbox's isolation from `hris_db` and other services on `hris_network`. Keep it off unless you are testing this integration; do not enable it in both the root and `environments/dev` at once (shared alias). Production Nginx (`nginx.prod.conf`) is intentionally not changed.

---

## 10. Security & State File Hygiene

1. **Never Commit State Files:** `terraform.tfstate` contains plaintext descriptions of all provisioned attributes. State files are strictly ignored in both root `.gitignore` and `terraform/.gitignore`.
2. **Track the Dependency Lock File:** `.terraform.lock.hcl` must always be committed to Git to guarantee bit-for-bit identical provider binary installations across developer machines and CI/CD pipelines.
3. **No Hardcoded Secrets:** Never store database passwords or API keys in `.tf` or `.tfvars` files. Use environment variables (`TF_VAR_...`) or secret managers.
4. **Isolated Network Boundaries:** Test containers run in dedicated bridge networks and cannot access internal HRIS databases.

---

## 11. Proposed Next Steps for Development & Staging

1. **Remote State Backend:** For team environments, migrate local state to a secure remote backend (e.g., S3-compatible bucket or Terraform Cloud) with distributed state locking.
2. **Reusable Modules:** Refactor `terraform/` into modular components (`modules/network`, `modules/database`, `modules/monitoring`).
3. **CI Pipeline Integration:** Add `terraform fmt -check` and `terraform validate` steps to `.github/workflows/ci.yml` for automated linting on every pull request.

