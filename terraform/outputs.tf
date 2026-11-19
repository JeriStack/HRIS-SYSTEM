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

output "hris_network_attached" {
  description = "Whether the sandbox is attached to the Compose hris_network for Nginx proxying"
  value       = var.attach_to_hris_network
}

output "nginx_sandbox_path" {
  description = "Path on the Nginx host port that proxies to the sandbox (null when not attached)"
  value       = var.attach_to_hris_network ? "/terraform-sandbox/" : null
}
