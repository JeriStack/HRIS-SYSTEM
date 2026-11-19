# ==============================================================================
# HRIS-SYSTEM — Development Task Automation Makefile
# ==============================================================================
# Supported Environments:
#   - Windows 11 (via GNU Make / winget / choco / Git Bash)
#   - WSL Ubuntu (via make)
#   - Application Container (docker compose exec app make ...)
# ==============================================================================

.DEFAULT_GOAL := help

# Configurable Executables
DOCKER_COMPOSE ?= docker compose
PYTEST         ?= pytest
E2E_BROWSER    ?= chrome
TERRAFORM      ?= terraform

# Auto-detect container environment:
# If running inside the container (/.dockerenv exists), run tools directly.
# If running on the host, route commands through docker compose exec app.
ifeq ($(wildcard /.dockerenv),)
    EXEC_APP := $(DOCKER_COMPOSE) exec app
else
    EXEC_APP :=
endif

.PHONY: help up down restart ps logs build install migrate test test-realtime lint audit swagger tf-validate e2e clean

help: ## Display available commands and targets
	@echo "======================================================================"
	@echo " Employee HRIS and Leave Management System — Task Automation"
	@echo "======================================================================"
	@echo "Usage: make [target]"
	@echo ""
	@echo "Docker & Environment:"
	@echo "  up            Start all Docker services in background"
	@echo "  down          Stop all Docker services safely (preserves database volume)"
	@echo "  restart       Restart all Docker services"
	@echo "  ps            Display status of all running containers"
	@echo "  logs          Follow application container logs"
	@echo "  build         Build or rebuild Docker application images"
	@echo ""
	@echo "Dependencies, Docs & Migrations:"
	@echo "  install       Install Composer dependencies inside app container"
	@echo "  migrate       Execute pending database migrations inside app container"
	@echo "  swagger       Regenerate OpenAPI 3.0 YAML & JSON specifications"
	@echo ""
	@echo "Testing, Security & Quality Assurance:"
	@echo "  test          Execute PHPUnit backend test suite inside app container"
	@echo "  test-realtime Run live WebSocket E2E tests on the isolated test DB"
	@echo "  lint          Run PHP_CodeSniffer baseline and PHPStan static analysis"
	@echo "  audit         Run Composer security advisory audit (--locked)"
	@echo "  tf-validate   Check Terraform formatting and validate root & dev configs"
	@echo "  e2e           Run Selenium browser UI tests in headless mode"
	@echo "  clean         Safely purge disposable test caches, reports, and screenshots"
	@echo "======================================================================"

up: ## Start Docker services
	$(DOCKER_COMPOSE) up -d

down: ## Stop Docker services safely (never removes volumes)
	$(DOCKER_COMPOSE) down

restart: ## Restart Docker services
	$(DOCKER_COMPOSE) restart

ps: ## Display container status
	$(DOCKER_COMPOSE) ps

logs: ## Follow application logs
	$(DOCKER_COMPOSE) logs -f app

build: ## Build Docker images
	$(DOCKER_COMPOSE) build

install: ## Install Composer dependencies inside app container
	$(EXEC_APP) composer install

migrate: ## Run database migrations inside app container
	$(EXEC_APP) php bin/migrate.php

test: ## Run PHPUnit tests inside app container
	$(EXEC_APP) vendor/bin/phpunit

test-realtime: ## Run live WebSocket E2E tests (throwaway realtime server on the test DB)
	$(EXEC_APP) sh scripts/test-realtime-e2e.sh

lint: ## Run PHP linting & static analysis inside app container
	$(EXEC_APP) composer lint

audit: ## Run Composer security advisory audit inside app container
	$(EXEC_APP) composer audit --locked

swagger: ## Regenerate OpenAPI 3.0 specifications inside app container
	$(EXEC_APP) composer swagger:generate

tf-validate: ## Check Terraform formatting and validate root & dev configurations
	$(TERRAFORM) fmt -check -recursive terraform
	$(TERRAFORM) -chdir=terraform validate
	$(TERRAFORM) -chdir=terraform/environments/dev validate

e2e: ## Run Selenium UI tests
	$(PYTEST) e2e-tests/tests/ -v --browser=$(E2E_BROWSER) --headless

clean: ## Safely purge disposable test caches and generated reports
ifeq ($(OS),Windows_NT)
	@powershell -NoProfile -Command "Remove-Item -Recurse -Force -ErrorAction SilentlyContinue .phpunit.cache, .phpstan.cache, .phpcs.cache; Get-ChildItem -Path e2e-tests/reports, e2e-tests/screenshots -Include *.html, *.png -Recurse -ErrorAction SilentlyContinue | Remove-Item -Force"
else
	@rm -rf .phpunit.cache .phpstan.cache .phpcs.cache 2>/dev/null || true
	@rm -f e2e-tests/reports/*.html e2e-tests/screenshots/*.png 2>/dev/null || true
endif
	@echo "Clean completed: Disposable test caches and reports purged safely."
