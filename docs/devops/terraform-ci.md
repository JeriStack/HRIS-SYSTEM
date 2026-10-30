# Terraform CI Validation Workflow

## 1. Overview and Purpose

The HRIS-SYSTEM automated continuous integration (CI) pipeline integrates static validation for all Infrastructure as Code (IaC) definitions. The workflow enforces strict formatting conventions and configuration validity across all Terraform configurations before any pull request is merged into protected branches.

Static validation runs independently of a live Docker daemon or cloud credentials, guaranteeing fast feedback, deterministic outcomes, and complete isolation from infrastructure modification or disruption.

---

## 2. GitHub Actions Workflow Configuration

The Terraform validation is implemented as a dedicated, parallel job (`terraform-validation`) within [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml).

### Workflow Triggers
- **Pushes:**
  - `main`
  - `master`
  - `develop`
  - `HRIS-*` (feature and task branches)
- **Pull Requests:**
  - `main`
  - `master`
  - `develop`

### Job Definition

```yaml
terraform-validation:
  name: Terraform Format and Static Validation
  runs-on: ubuntu-latest

  steps:
    - name: Checkout Code
      uses: actions/checkout@v4

    - name: Setup Terraform
      uses: hashicorp/setup-terraform@v3
      with:
        terraform_version: "1.16.6"

    - name: Check Terraform Formatting
      run: terraform fmt -check -recursive

    - name: Initialize & Validate Root Module (terraform/)
      run: |
        terraform -chdir=terraform init -backend=false
        terraform -chdir=terraform validate

    - name: Initialize & Validate Dev Environment (terraform/environments/dev/)
      run: |
        terraform -chdir=terraform/environments/dev init -backend=false
        terraform -chdir=terraform/environments/dev validate
```

---

## 3. Terraform Validation Steps Explained

### Step 1: Global Formatting Check (`terraform fmt -check -recursive`)
- Recursively scans the entire repository for `.tf` and `.tfvars` files.
- Ensures all Terraform files adhere strictly to canonical HashiCorp HCL style.
- Returns a non-zero exit code if any file requires formatting, printing the offending file paths.

### Step 2: Root Module Validation (`terraform/`)
- Command: `terraform -chdir=terraform init -backend=false`
  - Initializes provider plugins without requiring access to a remote state backend.
  - Downloads required provider schemas (`kreuzwerker/docker`).
- Command: `terraform -chdir=terraform validate`
  - Statically verifies syntax, type constraints, variable declarations, module arguments, and provider schema compliance.

### Step 3: Environment-Specific Validation (`terraform/environments/dev/`)
- Command: `terraform -chdir=terraform/environments/dev init -backend=false`
  - Initializes environment-level modules, variables, and outputs.
- Command: `terraform -chdir=terraform/environments/dev validate`
  - Validates composition of root modules and environment variable assignments.

---

## 4. Safety Constraints & Non-Destructive Principles

1. **No Live Docker Engine Dependency:**
   - Static validation operates purely on code syntax and provider schema definitions without attempting to establish connections to named pipes (`npipe:////./pipe/docker_engine`) or Unix sockets (`/var/run/docker.sock`).
2. **Zero Infrastructure Modification:**
   - CI never invokes `terraform apply`, `terraform destroy`, `terraform import`, or `terraform state`.
   - The pipeline cannot accidentally create, mutate, or terminate production or development resources.
3. **No Secrets or Credentials in CI:**
   - Backend initialization is explicitly disabled (`-backend=false`).
   - No cloud credentials, SSH keys, or database passwords are required or stored in CI secrets for static validation.

---

## 5. Local Reproduction and Pre-Commit Checks

Developers can run the identical validation checks locally prior to pushing changes:

### Windows (PowerShell)

```powershell
# 1. Format check
terraform fmt -check -recursive

# 2. Automatically format files if needed
terraform fmt -recursive

# 3. Validate root module
terraform -chdir=terraform init -backend=false
terraform -chdir=terraform validate

# 4. Validate dev environment
terraform -chdir=terraform/environments/dev init -backend=false
terraform -chdir=terraform/environments/dev validate
```

### Linux / macOS (Bash)

```bash
# 1. Format check
terraform fmt -check -recursive

# 2. Validate root module
terraform -chdir=terraform init -backend=false && terraform -chdir=terraform validate

# 3. Validate dev environment
terraform -chdir=terraform/environments/dev init -backend=false && terraform -chdir=terraform/environments/dev validate
```

