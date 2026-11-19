# ==============================================================================
# Sandbox Module
# HRIS-SYSTEM — Modular Infrastructure as Code
# ==============================================================================

terraform {
  required_version = ">= 1.5.0"
  required_providers {
    docker = {
      source  = "kreuzwerker/docker"
      version = ">= 3.0.2"
    }
  }
}

locals {
  attach_external = var.external_network_name != ""

  # Static, read-only demo responder so Nginx has something real to proxy to.
  # Listens on an unprivileged port as 'nobody'. The alias and port below are the
  # contract with docker/nginx/default.conf (location /terraform-sandbox/).
  http_command = [
    "sh", "-c",
    "mkdir -p /tmp/www && echo 'hris terraform sandbox ok' > /tmp/www/index.html && exec httpd -f -p ${var.http_port} -h /tmp/www"
  ]
}

# Read-only lookup of a network owned by another stack (Docker Compose's
# 'hris_network'). Terraform never creates, modifies, or destroys it. The lookup
# fails at plan time if the network does not exist: start the Compose stack first.
data "docker_network" "external" {
  count = local.attach_external ? 1 : 0
  name  = var.external_network_name
}

resource "docker_image" "image" {
  name         = var.image_name
  keep_locally = var.keep_image_locally
}

resource "docker_container" "container" {
  name    = var.container_name
  image   = docker_image.image.image_id
  restart = "no"

  command = var.serve_http ? local.http_command : var.command
  user    = var.serve_http ? "nobody" : null

  network_mode = "bridge"
  networks_advanced {
    name = var.network_name
  }

  # Optional second attachment to the Compose network so Nginx can resolve this
  # container by alias (Option 1). Absent unless external_network_name is set.
  dynamic "networks_advanced" {
    for_each = toset(data.docker_network.external[*].name)
    content {
      name    = networks_advanced.value
      aliases = var.external_network_aliases
    }
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
