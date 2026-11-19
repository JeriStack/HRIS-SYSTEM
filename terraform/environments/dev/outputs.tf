# ==============================================================================
# Development Environment Outputs
# HRIS-SYSTEM — Modular Infrastructure as Code
# ==============================================================================

output "dev_container_id" {
  description = "Docker Container ID in development environment"
  value       = module.dev_sandbox.container_id
}

output "dev_container_name" {
  description = "Assigned container name in development environment"
  value       = module.dev_sandbox.container_name
}

output "dev_network_name" {
  description = "Name of the Docker network in development environment"
  value       = module.dev_network.name
}

output "dev_hris_network_attached" {
  description = "Whether the dev sandbox is attached to the Compose hris_network for Nginx proxying"
  value       = var.attach_to_hris_network
}
