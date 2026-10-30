# ==============================================================================
# Production Environment Variables
# HRIS-SYSTEM — Production Linux VPS Infrastructure
# ==============================================================================

variable "environment" {
  type        = string
  description = "Target deployment environment label."
  default     = "production"
}

variable "vps_hostname" {
  type        = string
  description = "Hostname for the production Linux VPS."
  default     = "hris-prod-vps"
}

variable "vps_os" {
  type        = string
  description = "Operating system image for the VPS."
  default     = "Ubuntu 24.04 LTS"
}

variable "vps_vcpu" {
  type        = number
  description = "Allocated virtual CPU cores."
  default     = 2
}

variable "vps_ram_gb" {
  type        = number
  description = "Allocated system RAM in Gigabytes."
  default     = 4
}

variable "vps_disk_gb" {
  type        = number
  description = "Allocated NVMe/SSD storage in Gigabytes."
  default     = 80
}

variable "docker_host" {
  type        = string
  description = "Docker daemon socket path on production Linux host."
  default     = "unix:///var/run/docker.sock"
}

variable "production_network_name" {
  type        = string
  description = "Isolated Docker bridge network for production containers."
  default     = "hris_network"
}

variable "app_domain" {
  type        = string
  description = "Fully qualified domain name for production application."
  default     = "hris.example.com"
}

