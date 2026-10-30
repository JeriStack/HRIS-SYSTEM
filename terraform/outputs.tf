# ==============================================================================
# Terraform Outputs
# HRIS-SYSTEM — Local Infrastructure as Code
# ==============================================================================

output "test_container_id" {
  description = "Docker Container ID of the isolated test resource"
  value       = docker_container.test_sandbox.id
}

output "test_container_name" {
  description = "Assigned container name of the isolated test resource"
  value       = docker_container.test_sandbox.name
}

output "test_container_image" {
  description = "Resolved image reference of the isolated test resource"
  value       = docker_image.test_image.name
}

output "test_network_name" {
  description = "Name of the isolated Docker network created for the test resource"
  value       = docker_network.test_network.name
}

output "test_network_id" {
  description = "Docker Network ID of the isolated test network"
  value       = docker_network.test_network.id
}

