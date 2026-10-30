output "dev_container_id" {
  description = "Docker Container ID in development environment"
  value       = module.hris_dev_infrastructure.test_container_id
}

output "dev_container_name" {
  description = "Assigned container name in development environment"
  value       = module.hris_dev_infrastructure.test_container_name
}

output "dev_network_name" {
  description = "Name of the Docker network in development environment"
  value       = module.hris_dev_infrastructure.test_network_name
}

