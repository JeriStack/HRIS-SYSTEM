# ==============================================================================
# Network Module
# HRIS-SYSTEM — Modular Infrastructure as Code
# ==============================================================================

terraform {
  required_version = ">= 1.5.0"
  required_providers {
    docker = {
      source  = "kreuzwerker/docker"
      version = "~> 3.0.2"
    }
  }
}

resource "docker_network" "network" {
  name   = var.network_name
  driver = var.driver

  labels {
    label = "managed_by"
    value = "terraform"
  }

  labels {
    label = "project"
    value = "hris-system"
  }

  labels {
    label = "environment"
    value = var.environment
  }
}

