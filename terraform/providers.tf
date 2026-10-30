# ==============================================================================
# Docker Provider Configuration
# HRIS-SYSTEM — Local Infrastructure as Code
# ==============================================================================

provider "docker" {
  # Connects to the local Docker daemon
  # Windows 11 Docker Desktop: npipe:////./pipe/docker_engine
  # Linux / macOS: unix:///var/run/docker.sock
  host = var.docker_host
}

