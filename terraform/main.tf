# ==============================================================================
# Isolated Demonstration Resources
# HRIS-SYSTEM — Local Infrastructure as Code
# ==============================================================================
# This configuration demonstrates the Terraform lifecycle (init, plan, apply,
# state inspection, destroy) using an isolated, non-disruptive Docker container.
#
# Boundary Safeguards:
# - Connects exclusively to dedicated test network 'hris_tf_test_network'
# - Does NOT publish host ports (zero collision with hris_nginx:8080, db:3307, etc.)
# - Does NOT mount host filesystem or database persistent volumes
# - Operates with restart="no" and unprivileged execution
# ==============================================================================

# 1. Isolated Bridge Network for Demonstration Resource
resource "docker_network" "test_network" {
  name   = var.test_network_name
  driver = "bridge"

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

# 2. Pinned Lightweight Image
resource "docker_image" "test_image" {
  name         = var.test_image_name
  keep_locally = true
}

# 3. Isolated Test Sandbox Container
resource "docker_container" "test_sandbox" {
  name    = var.test_container_name
  image   = docker_image.test_image.image_id
  restart = "no"

  # Non-destructive sleep command keeping the container alive for lifecycle inspection
  command = ["sleep", "3600"]

  # Isolated network attachment (strictly isolated from 'hris_network')
  network_mode = "bridge"
  networks_advanced {
    name = docker_network.test_network.name
  }

  # Security: unprivileged execution, zero host port publishing, zero volume mounts
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

  labels {
    label = "purpose"
    value = "iac-lifecycle-demonstration"
  }
}
