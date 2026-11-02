# ==============================================================================
# Terraform Outputs
# HRIS-SYSTEM — Local Infrastructure as Code
# ==============================================================================

output "test_container_id" {
  description = "Docker Container ID of the isolated test resource"
  value       = module.sandbox.container_id
}

output "test_container_name" {
  description = "Assigned container name of the isolated test resource"
  value       = module.sandbox.container_name
}

output "test_container_image" {
  description = "Resolved image reference of the isolated test resource"
  value       = module.sandbox.image_name
}

output "test_network_name" {
  description = "Name of the isolated Docker network created for the test resource"
  value       = module.network.name
}

output "test_network_id" {
  description = "Docker Network ID of the isolated test network"
  value       = module.network.id
}
