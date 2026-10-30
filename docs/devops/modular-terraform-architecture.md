# Modular Terraform Architecture Specification

## 1. Overview & Architecture Goals

The HRIS-SYSTEM Infrastructure as Code (IaC) layer has been refactored from a flat configuration into a **reusable, modular architecture**. The primary goals of this architecture are:

1. **Encapsulation & Reusability:** Define self-contained infrastructure components under [`terraform/modules/`](../../terraform/modules/) that can be composed across multiple deployment tiers (local, dev, staging, prod).
2. **Strict State Continuity:** Use declarative `moved` blocks to guarantee zero resource recreation or state file corruption when migrating from monolithic resource addresses to module paths.
3. **Preserved Resource Ownership:** Maintain clean boundaries between **Docker Compose** (which manages the stateful production application, database, and monitoring services) and **Terraform** (which manages declarative infrastructure lifecycles).

---

## 2. Directory Structure

```text
terraform/
├── versions.tf                          # Pinned CLI version (>= 1.5.0) and Docker provider (~> 3.0.2)
├── providers.tf                         # Docker provider daemon configuration
├── variables.tf                         # Root input variable declarations
├── main.tf                              # Root composition of modules + declarative moved blocks
├── outputs.tf                           # Root outputs referencing composed modules
├── terraform.tfvars.example             # Input parameter template
├── .tflint.hcl                          # TFLint local module ruleset configuration
├── modules/
│   ├── network/                         # Reusable Docker network module
│   │   ├── main.tf                      # docker_network resource with standardized metadata labels
│   │   ├── variables.tf                 # network_name, driver, environment
│   │   └── outputs.tf                   # id, name
│   └── sandbox/                         # Reusable isolated test/demonstration service module
│       ├── main.tf                      # docker_image and docker_container resources
│       ├── variables.tf                 # container_name, image_name, network_name, command, environment
│       └── outputs.tf                   # container_id, container_name, image_id, image_name
└── environments/
    └── dev/                             # Development environment composition
        ├── versions.tf                  # Dev provider version constraints
        ├── providers.tf                 # Docker daemon connection
        ├── variables.tf                 # Dev environment parameter declarations
        ├── main.tf                      # Composes ../../modules/network and ../../modules/sandbox
        ├── outputs.tf                   # Exposes dev_container_id, dev_network_name, etc.
        └── terraform.tfvars.example     # Dev parameter template
```

---

## 3. Module Responsibilities & Specifications

### 1. `modules/network`
- **Purpose:** Provisions isolated Docker bridge networks with standard operational and environment metadata tags.
- **Resources Managed:** `docker_network.network`
- **Inputs:**
  - `network_name` (string, required): Unique bridge network name.
  - `driver` (string, optional, default: `"bridge"`): Docker network driver.
  - `environment` (string, optional, default: `"local"`): Environment classification label.
- **Outputs:**
  - `id`: Docker network identifier.
  - `name`: Assigned network name.

### 2. `modules/sandbox`
- **Purpose:** Manages isolated demonstration and test container workloads, completely decoupled from networking.
- **Resources Managed:** `docker_image.image`, `docker_container.container`
- **Inputs:**
  - `container_name` (string, required): Unique container name.
  - `image_name` (string, optional, default: `"alpine:3.21.3"`): Pinned container image tag.
  - `network_name` (string, required): Network name output from `modules/network`.
  - `command` (list(string), optional, default: `["sleep", "3600"]`): Container execution arguments.
  - `environment` (string, optional, default: `"local"`): Environment classification label.
- **Outputs:**
  - `container_id`: Docker container ID.
  - `container_name`: Assigned container name.
  - `image_id`: SHA256 image ID.
  - `image_name`: Container image tag.

---

## 4. Resource Ownership & Boundary Protection

| Resource Tier | Component | Managing Tool | State Location | Justification |
| :--- | :--- | :--- | :--- | :--- |
| **Edge Proxy** | `hris_nginx` (:8080) | Docker Compose | `docker-compose.yml` | Fronts application and WebSocket services. |
| **Application** | `hris_app`, `hris_realtime` | Docker Compose | `docker-compose.yml` | PHP-FPM / Apache runtime mounts repository workspace. |
| **Database** | `hris_db` (:3307), `hris_phpmyadmin` | Docker Compose | Volume `hris_db_data` | **CRITICAL:** Persistent database storage. Must never be destroyed or managed by ephemeral Terraform modules. |
| **Monitoring** | Prometheus, Grafana, Exporters | Docker Compose | Volumes `prometheus_data`, `grafana_data` | Observability stack runs continuously in local dev. |
| **IaC Modules** | Isolated networks & sandbox services | Terraform | `terraform/terraform.tfstate` | Declarative infrastructure verification and lifecycle management. |

---

## 5. State Migration Strategy (`moved` Blocks)

When resources were moved from the monolithic root configuration into child modules, standard Terraform `moved` blocks were added to [`terraform/main.tf`](../../terraform/main.tf):

```hcl
moved {
  from = docker_network.test_network
  to   = module.network.docker_network.network
}

moved {
  from = docker_image.test_image
  to   = module.sandbox.docker_image.image
}

moved {
  from = docker_container.test_sandbox
  to   = module.sandbox.docker_container.container
}
```

### Plan Impact
Running `terraform plan` verifies:
- `docker_network.test_network` **moved** to `module.network.docker_network.network` (0 destroyed, ID preserved).
- `docker_image.test_image` **moved** to `module.sandbox.docker_image.image` (0 destroyed, ID preserved).
- Total destructive changes: **0 to destroy**.

---

## 6. How Future Staging & Production Environments Reuse Modules

To add a new environment (e.g., `terraform/environments/staging/` or `prod/`):

1. Create directory `terraform/environments/staging/`.
2. Define `versions.tf` and `providers.tf`.
3. Create `main.tf` composing the child modules with environment-specific variables:
   ```hcl
   module "staging_network" {
     source = "../../modules/network"

     network_name = "hris_staging_network"
     environment  = "staging"
   }

   module "staging_sandbox" {
     source = "../../modules/sandbox"

     container_name = "hris_staging_sandbox"
     image_name     = "alpine:3.21.3"
     network_name   = module.staging_network.name
     environment    = "staging"
   }
   ```
4. Configure an isolated backend (e.g. S3, GCS, or separate local state path) so environments never share state files.

---

## 7. Verification Commands

```powershell
# 1. Format check across all modules and environments
terraform fmt -check -recursive

# 2. Validate Root Module
terraform -chdir=terraform init -backend=false
terraform -chdir=terraform validate

# 3. Validate Development Environment
terraform -chdir=terraform/environments/dev init -backend=false
terraform -chdir=terraform/environments/dev validate

# 4. Run TFLint on all configurations
docker run --rm -v "${PWD}:/data" -w /data ghcr.io/terraform-linters/tflint:latest --chdir=terraform
docker run --rm -v "${PWD}:/data" -w /data ghcr.io/terraform-linters/tflint:latest --chdir=terraform/environments/dev

# 5. Non-destructive Plan Preview
terraform -chdir=terraform plan
```

