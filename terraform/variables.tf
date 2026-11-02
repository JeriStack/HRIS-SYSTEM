# ==============================================================================
# Terraform Variables
# HRIS-SYSTEM — Local Infrastructure as Code
# ==============================================================================

variable "docker_host" {
  type        = string
  description = "Docker daemon connection URI. Defaults to Windows named pipe (npipe:////./pipe/docker_engine) for Docker Desktop."
  default     = "npipe:////./pipe/docker_engine"
}

variable "environment" {
  type        = string
  description = "Target deployment environment label (e.g., local, dev, staging, prod)."
  default     = "local"
}

variable "test_container_name" {
  type        = string
  description = "Unique container name for the isolated demonstration resource to avoid collisions."
  default     = "hris_tf_test_sandbox"
}

variable "test_image_name" {
  type        = string
  description = "Pinned container image tag used for the isolated demonstration container."
  default     = "alpine:3.21.3"
}

variable "test_network_name" {
  type        = string
  description = "Unique bridge network name for isolated demonstration traffic."
  default     = "hris_tf_test_network"
}

