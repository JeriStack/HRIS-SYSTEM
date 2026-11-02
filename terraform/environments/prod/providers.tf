# ==============================================================================
# Production Docker Provider Configuration
# HRIS-SYSTEM — Linux VPS Infrastructure
# ==============================================================================

provider "docker" {
  # Default Linux VPS Docker daemon Unix socket
  host = var.docker_host
}

