# ==============================================================================
# Sandbox Module Variables
# HRIS-SYSTEM — Modular Infrastructure as Code
# ==============================================================================

variable "container_name" {
  type        = string
  description = "Unique container name for the isolated demonstration/sandbox container."
}

variable "image_name" {
  type        = string
  description = "Pinned container image tag used for the isolated sandbox container."
  default     = "alpine:3.21.3"
}

variable "network_name" {
  type        = string
  description = "Name of the Docker network to which the sandbox container attaches."
}

variable "environment" {
  type        = string
  description = "Target environment label (e.g., local, dev, staging, prod)."
  default     = "local"
}

variable "command" {
  type        = list(string)
  description = "Container command array."
  default     = ["sleep", "3600"]
}

variable "keep_image_locally" {
  type        = bool
  description = "Whether to retain the pulled Docker image locally upon resource destruction."
  default     = true
}

