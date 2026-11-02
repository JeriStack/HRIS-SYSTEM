# ==============================================================================
# Sandbox Module Outputs
# HRIS-SYSTEM — Modular Infrastructure as Code
# ==============================================================================

output "container_id" {
  description = "Docker Container ID of the sandbox container"
  value       = docker_container.container.id
}

output "container_name" {
  description = "Assigned container name of the sandbox container"
  value       = docker_container.container.name
}

output "image_id" {
  description = "Resolved Docker Image ID"
  value       = docker_image.image.image_id
}

output "image_name" {
  description = "Resolved Docker Image Name"
  value       = docker_image.image.name
}

