# ==============================================================================
# Network Module Outputs
# HRIS-SYSTEM — Modular Infrastructure as Code
# ==============================================================================

output "id" {
  description = "Docker Network ID"
  value       = docker_network.network.id
}

output "name" {
  description = "Docker Network Name"
  value       = docker_network.network.name
}

