# ==============================================================================
# Isolated Demonstration Resources (Modular Architecture Composition)
# HRIS-SYSTEM — Local Infrastructure as Code
# ==============================================================================
# This configuration demonstrates the Terraform lifecycle (init, plan, apply,
# state inspection, destroy) using reusable child modules.
#
# Boundary Safeguards:
# - Connects exclusively to dedicated test network 'hris_tf_test_network'
# - Does NOT publish host ports (zero collision with hris_nginx:8080, db:3307, etc.)
# - Does NOT mount host filesystem or database persistent volumes
# - Operates with restart="no" and unprivileged execution
# ==============================================================================

# 1. Reusable Network Child Module
module "network" {
  source = "./modules/network"

  network_name = var.test_network_name
  environment  = var.environment
}

# 2. Reusable Sandbox Container Child Module
module "sandbox" {
  source = "./modules/sandbox"

  container_name = var.test_container_name
  image_name     = var.test_image_name
  network_name   = module.network.name
  environment    = var.environment
}

# ==============================================================================
# State Migration (Declarative Refactoring Mappings)
# Maps legacy flat resource addresses to modular child paths without recreation
# ==============================================================================
moved {
  from = docker_network.test_network
  to   = module.network.docker_network.network
}

moved {
  from = docker_image.test_image
  to   = module.sandbox.docker_image.image
}

moved {
  from = docker_container.test_sandbox
  to   = module.sandbox.docker_container.container
}
