# ==============================================================================
# Development Environment Infrastructure Layer
# HRIS-SYSTEM — Local Infrastructure as Code
# ==============================================================================

module "hris_dev_infrastructure" {
  source = "../../"

  docker_host         = var.docker_host
  environment         = var.environment
  test_container_name = var.test_container_name
  test_image_name     = var.test_image_name
  test_network_name   = var.test_network_name
}

