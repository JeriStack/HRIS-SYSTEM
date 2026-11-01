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

# Auto-detect container environment:
# If running inside the container (/.dockerenv exists), run tools directly.
# If running on the host, route commands through docker compose exec app.
ifeq ($(wildcard /.dockerenv),)
    EXEC_APP := $(DOCKER_COMPOSE) exec app
else
    EXEC_APP :=
endif

.PHONY: help up down restart ps logs build install migrate test lint e2e

help: ## Display available commands and targets
	@echo "======================================================================"
	@echo " Employee HRIS and Leave Management System — Task Automation"
	@echo "======================================================================"
	@echo "Usage: make [target]"
	@echo ""
	@echo "Docker & Environment:"
	@echo "  up         Start all Docker services in background"
	@echo "  down       Stop all Docker services safely (preserves database volume)"
	@echo "  restart    Restart all Docker services"
	@echo "  ps         Display status of all running containers"
	@echo "  logs       Follow application container logs"
	@echo "  build      Build or rebuild Docker application images"
	@echo ""
	@echo "Dependencies & Migrations:"
	@echo "  install    Install Composer dependencies inside app container"
	@echo "  migrate    Execute pending database migrations inside app container"
	@echo ""
	@echo "Testing & Quality Assurance:"
	@echo "  test       Execute PHPUnit backend test suite inside app container"
	@echo "  lint       Run PHP_CodeSniffer and PHPStan static analysis"
	@echo "  e2e        Run Selenium browser UI tests in headless mode"
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

lint: ## Run PHP linting & static analysis inside app container
	$(EXEC_APP) composer lint

e2e: ## Run Selenium UI tests
	$(PYTEST) e2e-tests/tests/ -v --headless
