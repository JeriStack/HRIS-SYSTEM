# ==============================================================================
# HRIS-SYSTEM — Development Task Automation Makefile
# ==============================================================================
# Supported Environments:
#   - Windows 11 (GNU Make via winget/choco/Git Bash)
#   - WSL Ubuntu / Linux / macOS
#   - Inside Application Container (docker compose exec app make ...)
# ==============================================================================

.DEFAULT_GOAL := help

# Configurable Executables & Defaults
DOCKER_COMPOSE ?= docker compose
TERRAFORM      ?= terraform
SERVICE        ?= app
PHPUNIT_ARGS   ?=
E2E_BROWSER    ?= chrome
E2E_APP_URL    ?= http://localhost:8080
E2E_ARGS       ?=

# Auto-detect project virtualenv for Selenium E2E pytest before falling back to PATH
ifeq ($(OS),Windows_NT)
    VENV_PYTEST_CHECK := e2e-tests/.venv/Scripts/pytest.exe
    VENV_PYTEST_EXEC  := e2e-tests\.venv\Scripts\pytest.exe
else
    VENV_PYTEST_CHECK := e2e-tests/.venv/bin/pytest
    VENV_PYTEST_EXEC  := e2e-tests/.venv/bin/pytest
endif

ifneq ($(wildcard $(VENV_PYTEST_CHECK)),)
    PYTEST ?= $(VENV_PYTEST_EXEC)
else
    PYTEST ?= pytest
endif

# Auto-detect container environment:
# - Inside container (/.dockerenv exists): run tools directly.
# - On host: route batch commands through `docker compose exec -T` (TTY-safe for CI/IDE).
ifeq ($(wildcard /.dockerenv),)
    EXEC_APP       := $(DOCKER_COMPOSE) exec -T app
    EXEC_SCHEDULER := $(DOCKER_COMPOSE) exec -T scheduler
    EXEC_TTY       := $(DOCKER_COMPOSE) exec
else
    EXEC_APP       :=
    EXEC_SCHEDULER :=
    EXEC_TTY       :=
endif

.PHONY: help up down restart ps logs shell build \
        install migrate swagger backup schedule-run worker-once \
        test test-unit test-realtime test-security \
        lint lint-fix audit check tf-validate e2e clean

help: ## Display available commands and targets
	$(info ======================================================================)
	$(info  Employee HRIS and Leave Management System — Task Automation)
	$(info ======================================================================)
	$(info Usage: make [target] [VAR=value])
	$(info )
	$(info Docker & Environment:)
	$(info   up            Start all Docker services in background)
	$(info   down          Stop all Docker services safely (preserves DB volume))
	$(info   restart       Restart all Docker services)
	$(info   ps            Display status and health of all containers)
	$(info   logs          Follow container logs (override: SERVICE=worker|scheduler|realtime))
	$(info   shell         Open an interactive shell in container (default: SERVICE=app))
	$(info   build         Build or rebuild Docker application images)
	$(info )
	$(info Dependencies, Data & Operations:)
	$(info   install       Install Composer dependencies inside app container)
	$(info   migrate       Execute pending database migrations inside app container)
	$(info   swagger       Regenerate OpenAPI 3.0 YAML & JSON specifications)
	$(info   backup        Create a timestamped MySQL database backup)
	$(info   schedule-run  Trigger one cron scheduler tick in scheduler container)
	$(info   worker-once   Process one batch of queued background jobs)
	$(info )
	$(info Testing, Security & Quality Assurance:)
	$(info   test          Run full PHPUnit suite (override: PHPUNIT_ARGS="--filter ..."))
	$(info   test-unit     Run fast PHPUnit Unit testsuite only)
	$(info   test-realtime Run live WebSocket E2E tests on isolated hris_test DB)
	$(info   test-security Run Audit Trail, RBAC/DRBAC, Cron, and Queue Worker suites)
	$(info   lint          Run PHP_CodeSniffer baseline and PHPStan static analysis)
	$(info   lint-fix      Auto-fix PHPCS formatting issues via PHPCBF)
	$(info   audit         Run Composer security advisory audit (--locked))
	$(info   check         Run full pre-push quality gate (lint + audit + test))
	$(info   tf-validate   Check Terraform fmt and validate root, dev, and prod configs)
	$(info   e2e           Run Selenium UI tests (auto-detects e2e-tests/.venv))
	$(info   clean         Safely purge disposable test caches, reports, and screenshots)
	$(info ======================================================================)
	@:

# ------------------------------------------------------------------------------
# Docker & Environment
# ------------------------------------------------------------------------------

up: ## Start Docker services
	$(DOCKER_COMPOSE) up -d

down: ## Stop Docker services safely (never removes volumes)
	$(DOCKER_COMPOSE) down

restart: ## Restart Docker services
	$(DOCKER_COMPOSE) restart

ps: ## Display container status
	$(DOCKER_COMPOSE) ps

logs: ## Follow logs for a service (default: app)
	$(DOCKER_COMPOSE) logs -f $(SERVICE)

shell: ## Open an interactive shell in a service container (default: app)
	$(EXEC_TTY) $(SERVICE) sh

build: ## Build Docker images
	$(DOCKER_COMPOSE) build

# ------------------------------------------------------------------------------
# Dependencies, Data & Operations
# ------------------------------------------------------------------------------

install: ## Install Composer dependencies inside app container
	$(EXEC_APP) composer install

migrate: ## Run database migrations inside app container
	$(EXEC_APP) php bin/migrate.php

swagger: ## Regenerate OpenAPI 3.0 specifications inside app container
	$(EXEC_APP) composer swagger:generate

backup: ## Run database backup script
ifeq ($(OS),Windows_NT)
	powershell -NoProfile -ExecutionPolicy Bypass -File scripts/backup/backup-db.ps1
else
	sh scripts/backup/backup-db.sh
endif

schedule-run: ## Execute one cron scheduler tick
	$(EXEC_SCHEDULER) php bin/schedule-run.php

worker-once: ## Process one batch of pending queue jobs
	$(EXEC_APP) php bin/queue-worker.php --once

# ------------------------------------------------------------------------------
# Testing, Security & Quality Assurance
# ------------------------------------------------------------------------------

test: ## Run full PHPUnit test suite inside app container
	$(EXEC_APP) vendor/bin/phpunit $(PHPUNIT_ARGS)

test-unit: ## Run PHPUnit Unit testsuite only
	$(EXEC_APP) vendor/bin/phpunit --testsuite Unit $(PHPUNIT_ARGS)

test-realtime: ## Run live WebSocket E2E tests (throwaway realtime server on hris_test)
	$(EXEC_APP) sh scripts/test-realtime-e2e.sh

test-security: ## Run Audit Trail, RBAC/DRBAC, Cron, and Queue Worker suites
	$(EXEC_APP) vendor/bin/phpunit --filter "AuditTrailTest|AdminRoleRbacTest|AuthorizationTest|DynamicRbacTest|AnnouncementRbacAndSecurityTest|HrSupportAccessUnitTest|CronSchedulerTest|QueueWorkerTest" --testdox

lint: ## Run PHP linting & static analysis inside app container
	$(EXEC_APP) composer lint

lint-fix: ## Auto-fix PHPCS formatting issues via PHPCBF
	$(EXEC_APP) composer lint:phpcbf

audit: ## Run Composer security advisory audit inside app container
	$(EXEC_APP) composer audit --locked

check: lint audit test ## Run full pre-push quality gate

tf-validate: ## Check Terraform formatting and validate root, dev, and prod configs
	$(TERRAFORM) fmt -check -recursive terraform
	$(TERRAFORM) -chdir=terraform validate
	$(TERRAFORM) -chdir=terraform/environments/dev validate
	$(TERRAFORM) -chdir=terraform/environments/prod validate

e2e: ## Run Selenium UI tests in headless mode
	$(PYTEST) e2e-tests/tests/ -v --browser=$(E2E_BROWSER) --headless --app-url=$(E2E_APP_URL) $(E2E_ARGS)

clean: ## Safely purge disposable test caches and generated reports
ifeq ($(OS),Windows_NT)
	@powershell -NoProfile -Command "Remove-Item -Recurse -Force -ErrorAction SilentlyContinue .phpunit.cache, .phpunit.result.cache, .phpstan.cache, .phpcs.cache, e2e-tests/.pytest_cache; Get-ChildItem -Path e2e-tests/reports, e2e-tests/screenshots -Include *.html, *.png -Recurse -ErrorAction SilentlyContinue | Remove-Item -Force"
else
	@rm -rf .phpunit.cache .phpunit.result.cache .phpstan.cache .phpcs.cache e2e-tests/.pytest_cache 2>/dev/null || true
	@rm -f e2e-tests/reports/*.html e2e-tests/screenshots/*.png 2>/dev/null || true
endif
	@echo Clean completed: Disposable test caches and reports purged safely.
