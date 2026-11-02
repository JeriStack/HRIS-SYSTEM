# ==============================================================================
# Production Environment Infrastructure Outputs
# HRIS-SYSTEM — Production Infrastructure as Code
# ==============================================================================

output "vps_target_specifications" {
  description = "Target VPS host specifications."
  value = {
    hostname = var.vps_hostname
    os       = var.vps_os
    vcpu     = var.vps_vcpu
    ram_gb   = var.vps_ram_gb
    disk_gb  = var.vps_disk_gb
    domain   = var.app_domain
  }
}

output "production_network_id" {
  description = "Production Docker network ID."
  value       = docker_network.production_network.id
}

output "production_network_name" {
  description = "Production Docker network name."
  value       = docker_network.production_network.name
}

output "production_volumes" {
  description = "Persistent volumes declared for production data."
  value = [
    docker_volume.db_data.name,
    docker_volume.prometheus_data.name,
    docker_volume.grafana_data.name,
    docker_volume.certbot_certs.name,
    docker_volume.certbot_www.name
  ]
}
