# ==============================================================================
# Sandbox Module
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

resource "docker_image" "image" {
  name         = var.image_name
  keep_locally = var.keep_image_locally
}

resource "docker_container" "container" {
  name    = var.container_name
  image   = docker_image.image.image_id
  restart = "no"

  command = var.command

  network_mode = "bridge"
  networks_advanced {
    name = var.network_name
  }

  privileged = false

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

