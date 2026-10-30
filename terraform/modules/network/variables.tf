# ==============================================================================
# Network Module Variables
# HRIS-SYSTEM — Modular Infrastructure as Code
# ==============================================================================

variable "network_name" {
  type        = string
  description = "Unique bridge network name for Docker container traffic."
}

variable "driver" {
  type        = string
  description = "Docker network driver to use. Defaults to bridge."
  default     = "bridge"
}

variable "environment" {
  type        = string
  description = "Target environment label (e.g., local, dev, staging, prod)."
  default     = "local"
}

