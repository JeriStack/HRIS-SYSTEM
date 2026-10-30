# ==============================================================================
# Production Environment Infrastructure Layer (Linux VPS Stack)
# HRIS-SYSTEM — Production Infrastructure as Code
#
# Hardware Targets:
# - OS: Ubuntu 24.04 LTS
# - Compute: 2 vCPUs, 4 GB RAM, 80 GB SSD
# - Ingress: Ports 80 (HTTP) and 443 (HTTPS)
# ==============================================================================

# 1. Production Isolated Bridge Network
resource "docker_network" "production_network" {
  name   = var.production_network_name
  driver = "bridge"

  labels {
    label = "environment"
    value = var.environment
  }

  labels {
    label = "managed_by"
    value = "terraform"
  }
}

# 2. Production Database Storage Volume (InnoDB Data Preservation)
resource "docker_volume" "db_data" {
  name = "hris_db_data"

  labels {
    label = "environment"
    value = var.environment
  }

  labels {
    label = "workload"
    value = "mysql"
  }
}

# 3. Production Monitoring Storage Volume (Prometheus TSDB)
resource "docker_volume" "prometheus_data" {
  name = "hris_prometheus_data"

  labels {
    label = "environment"
    value = var.environment
  }
}

# 4. Production Dashboard Storage Volume (Grafana)
resource "docker_volume" "grafana_data" {
  name = "hris_grafana_data"

  labels {
    label = "environment"
    value = var.environment
  }
}

# 5. Production SSL/TLS Certificate Volume (Let's Encrypt / Certbot)
resource "docker_volume" "certbot_certs" {
  name = "hris_certbot_certs"

  labels {
    label = "environment"
    value = var.environment
  }
}

# 6. Production ACME Webroot Challenge Volume
resource "docker_volume" "certbot_www" {
  name = "hris_certbot_www"

  labels {
    label = "environment"
    value = var.environment
  }
}

