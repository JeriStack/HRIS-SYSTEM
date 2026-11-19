# ==============================================================================
# Development Environment Infrastructure Layer (Modular Architecture)
# HRIS-SYSTEM — Local Infrastructure as Code
# ==============================================================================

# 1. Dev Network Child Module
module "dev_network" {
  source = "../../modules/network"

  network_name = var.test_network_name
  environment  = var.environment
}

# 2. Dev Sandbox Container Child Module
module "dev_sandbox" {
  source = "../../modules/sandbox"

  container_name = var.test_container_name
  image_name     = var.test_image_name
  network_name   = module.dev_network.name
  environment    = var.environment

  # Opt-in Nginx integration (default off keeps the sandbox fully isolated).
  external_network_name    = var.attach_to_hris_network ? var.hris_network_name : ""
  external_network_aliases = var.attach_to_hris_network ? ["tf-sandbox"] : []
  serve_http               = var.attach_to_hris_network
}
