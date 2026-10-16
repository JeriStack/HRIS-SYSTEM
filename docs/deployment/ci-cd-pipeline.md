# CI/CD Pipeline Guide

## Employee HRIS & Leave Management System

This document outlines the Continuous Integration (CI) and Continuous Deployment (CD) pipeline implemented with GitHub Actions for the Employee HRIS & Leave Management System.

---

## 1. CI/CD Lifecycle Overview

The automation pipeline follows a 5-stage release lifecycle:

```
+-----------------------------------------------------------------------+
| 1. Write Code                                                         |
| Make changes in your development or feature branch using VS Code.     |
+-----------------------------------+-----------------------------------+
                                    |
                                    v
+-----------------------------------------------------------------------+
| 2. Push Code to GitHub                                                |
| Commit changes and push branch or open a Pull Request.                |
+-----------------------------------+-----------------------------------+
                                    |
                                    v
+-----------------------------------------------------------------------+
| 3. GitHub Actions Runs CI (`ci.yml`)                                  |
| - Verify no .env secrets are tracked                                  |
| - Set up PHP 8.3 with required extensions (PDO, mbstring, zip)       |
| - Validate Composer dependencies & lockfile presence                  |
| - Validate PHP syntax (`php -l`) across all tracked PHP files         |
| - Execute automated unit test suites (DSA algorithms & structures)    |
| - Check PHPUnit readiness                                             |
| - Validate Docker image build                                         |
+-----------------------------------+-----------------------------------+
                                    |
                                    v
+-----------------------------------------------------------------------+
| 4. Review and Merge                                                   |
| Code review by team. Pull requests only merge when CI checks pass.   |
+-----------------------------------+-----------------------------------+
                                    |
                                    v
+-----------------------------------------------------------------------+
| 5. CD Deploys Application (`cd.yml`)                                  |
| - Triggered automatically on push to `main` or release tag (`v*`)     |
| - Verifies production directory structure & webroot isolation         |
| - Builds production Docker image                                      |
| - Packages clean production tarball (excluding dev artifacts)         |
| - Deploys to configured server via SSH (or publishes release package) |
+-----------------------------------------------------------------------+
```

---

## 2. CI Pipeline (`.github/workflows/ci.yml`)

The CI workflow triggers on:
- Pushes to `main`, `development`, and `develop`
- Pull Requests targeting `main`, `development`, and `develop`
- Manual execution via `workflow_dispatch`

### CI Jobs and Verification Steps

| Step | Action | Purpose |
| :--- | :--- | :--- |
| **Secrets Verification** | `git ls-files --error-unmatch .env` | Guarantees `.env` and production secrets are never committed into version control. |
| **PHP Setup** | `shivammathur/setup-php@v2` (PHP 8.3) | Configures a clean PHP 8.3 CLI environment with `pdo_mysql`, `mbstring`, `zip`, and `json`. |
| **Composer Validation** | `composer validate` & lockfile check | If `composer.json` exists, verifies `composer.lock` is present. Skips gracefully if Composer is not yet used. |
| **PHP Syntax Check** | `php -l "$file"` | Runs linter on all tracked `.php` files to catch syntax errors, unclosed brackets, and parse errors before merge. |
| **Automated Unit Tests** | `php scratch/test_dsa_suite.php` | Runs 65 automated tests verifying data structures (Queue, Stack, Linked List, BST, Hash Table) and sorting/searching algorithms. |
| **PHPUnit Readiness** | `vendor/bin/phpunit` | Runs PHPUnit if installed. Skips cleanly if not yet configured, preventing false positives. |
| **Docker Build Check** | `docker build -t hris-app:ci .` | Verifies that the containerized stack builds cleanly and Apache configurations are valid. |

---

## 3. CD Pipeline (`.github/workflows/cd.yml`)

The CD workflow triggers on:
- Push/merge to `main`
- Semantic version tags (`v*`)
- Manual trigger (`workflow_dispatch`) with environment selection (`production` or `staging`)

### CD Process

1. **Production Layout Validation:**
   - Confirms that `public/index.php`, `.htaccess`, and `config/` exist.
   - Enforces DocumentRoot isolation.
   - Ensures no `.env` or development credentials enter the distribution artifact.
2. **Docker Production Build:**
   - Builds container image tagged with commit SHA and `latest`.
3. **Artifact Packaging:**
   - Bundles verified production code into `hris-system-release-<SHA>.tar.gz`.
   - Excludes `.git`, `.github`, `scratch`, `node_modules`, and temporary files.
   - Uploads artifact to GitHub Actions for deployment or manual retrieval.
4. **Automated Server Deployment (Optional / Secrets-based):**
   - If server SSH credentials are configured in GitHub Repository Secrets, automatically transfers and extracts the release on the production host, setting directory permissions (755/644/600).
   - If secrets are not yet configured, provides structured diagnostics and keeps the verified package available in Actions artifacts.

---

## 4. Important Notes Before Committing

- **Commit `composer.lock` with `composer.json`:** If you add dependencies using Composer, always commit both `composer.json` and `composer.lock` together.
- **Never commit `.env`:** Keep database passwords, API keys, and session secrets in `.env` (ignored by `.gitignore`). Use `.env.example` as a template.
- **Run tests locally before pushing:**
  ```bash
  # Check syntax of all PHP files
  git ls-files '*.php' | xargs -n 1 php -l

  # Run automated test suites
  php scratch/test_dsa_suite.php
  ```
- **Configuring GitHub Repository Secrets for CD:**
  Navigate to **Settings &rarr; Secrets and variables &rarr; Actions** in your repository and add:
  - `SERVER_HOST`: Production server hostname or public IP
  - `SERVER_USER`: Deployment user (e.g., `www-data` or `deploy`)
  - `SERVER_SSH_KEY`: Private SSH key for server access
  - `SERVER_PATH`: Deployment target directory (e.g., `/var/www/hris`)
