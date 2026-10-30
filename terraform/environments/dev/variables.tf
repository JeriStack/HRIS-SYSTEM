variable "docker_host" {
  type        = string
  description = "Docker daemon endpoint URI. Defaults to Windows named pipe for Docker Desktop."
  default     = "npipe:////./pipe/docker_engine"
}

variable "environment" {
  type        = string
  description = "Target deployment environment label."
  default     = "dev"
}

variable "test_container_name" {
  type        = string
  description = "Container name for dev test sandbox."
  default     = "hris_dev_test_sandbox"
}

variable "test_image_name" {
  type        = string
  description = "Pinned container image tag."
  default     = "alpine:3.21.3"
}

variable "test_network_name" {
  type        = string
  description = "Bridge network name for dev test sandbox."
  default     = "hris_dev_test_network"
}

