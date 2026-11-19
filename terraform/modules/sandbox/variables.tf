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

variable "external_network_name" {
  type        = string
  description = "Name of an existing, externally owned Docker network (e.g. Compose's hris_network) to also attach to. Empty string disables the attachment."
  default     = ""
}

variable "external_network_aliases" {
  type        = list(string)
  description = "DNS aliases the container answers to on the external network (used by Nginx upstream resolution)."
  default     = []
}

variable "serve_http" {
  type        = bool
  description = "Run a static read-only demo HTTP responder (busybox httpd, user nobody) instead of the default command."
  default     = false
}

variable "http_port" {
  type        = number
  description = "Unprivileged port for the demo HTTP responder. Must match the upstream port in docker/nginx/default.conf."
  default     = 8081

  validation {
    condition     = var.http_port >= 1024 && var.http_port <= 65535
    error_message = "http_port must be an unprivileged port between 1024 and 65535."
  }
}


