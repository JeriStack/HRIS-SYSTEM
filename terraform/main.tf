# ==============================================================================
# Isolated Demonstration Resources (Modular Architecture Composition)
# HRIS-SYSTEM — Local Infrastructure as Code
# ==============================================================================
# This configuration demonstrates the Terraform lifecycle (init, plan, apply,
# state inspection, destroy) using reusable child modules.
#
# Boundary Safeguards:
# - Connects to dedicated test network 'hris_tf_test_network' (default: exclusively)
# - Opt-in only (attach_to_hris_network=true): also joins Compose's 'hris_network' via a
#   read-only data lookup so Nginx can proxy /terraform-sandbox/ to the sandbox
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

  # Opt-in Nginx integration (default off keeps the sandbox fully isolated).
  # Alias 'tf-sandbox' and port 8081 are the contract with docker/nginx/default.conf.
  external_network_name    = var.attach_to_hris_network ? var.hris_network_name : ""
  external_network_aliases = var.attach_to_hris_network ? ["tf-sandbox"] : []
  serve_http               = var.attach_to_hris_network
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
