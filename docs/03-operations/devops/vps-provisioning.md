# HRIS-SYSTEM: Production VPS Host Provisioning & Hardening Guide

**Document:** `docs/devops/vps-provisioning.md`  
**Target OS:** Ubuntu 24.04 LTS (Noble Numbat)  
**Hardware Specifications:** 2 vCPUs, 4 GB RAM, 80 GB SSD  
**Architecture:** Linux VPS -> Docker Engine -> Nginx Reverse Proxy -> PHP 8.5 / Apache -> MySQL 8.0  
**Status:** Production Standard  

---

## 1. Overview & Security Baseline

This guide details the step-by-step procedure for provisioning and hardening a bare Linux Virtual Private Server (VPS) for hosting the **Employee HRIS & Leave Management System**. All configurations enforce the principle of least privilege, defense-in-depth, and operational resilience.

---

## 2. Initial System Update & Dedicated User Creation

Connect to the VPS as `root` via initial console/SSH:

```bash
# 1. Update package index and upgrade existing packages
apt-get update && apt-get upgrade -y

# 2. Install essential system utilities
apt-get install -y --no-install-recommends \
    curl wget git ufw fail2ban unattended-upgrades \
    apt-transport-https ca-certificates gnupg lsb-release htop jq

# 3. Create a dedicated non-root deployer user (e.g., deployer)
adduser --gecos "" deployer
usermod -aG sudo deployer

# 4. Authorize SSH public key for deployer
mkdir -p /home/deployer/.ssh
chmod 700 /home/deployer/.ssh
cp /root/.ssh/authorized_keys /home/deployer/.ssh/authorized_keys
chown -R deployer:deployer /home/deployer/.ssh
chmod 600 /home/deployer/.ssh/authorized_keys
```

> [!IMPORTANT]
> Test logging in as `deployer` in a separate terminal before closing your root session!

---

## 3. SSH Daemon Hardening

Create a dedicated hardening drop-in configuration:

```bash
cat << 'EOF' > /etc/ssh/sshd_config.d/99-hris-hardened.conf
# Enforce modern secure SSH protocol and keys
Protocol 2
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
AuthenticationMethods publickey
X11Forwarding no
MaxAuthTries 3
LoginGraceTime 30
ClientAliveInterval 300
ClientAliveCountMax 2
EOF

# Validate syntax and reload SSH
sshd -t && systemctl restart ssh
```

---

## 4. Host Firewall Configuration (UFW)

The HRIS production stack operates strictly behind Nginx. Only SSH (22), HTTP (80 for ACME challenges and HTTPS redirects), and HTTPS (443) should be reachable from the public internet.

```bash
# 1. Set default policies
ufw default deny incoming
ufw default allow outgoing

# 2. Allow SSH ingress (port 22)
ufw allow 22/tcp comment 'SSH Administration'

# 3. Allow Web ingress (HTTP:80 and HTTPS:443)
ufw allow 80/tcp comment 'Nginx HTTP / ACME'
ufw allow 443/tcp comment 'Nginx HTTPS'

# 4. Enable firewall
ufw --force enable

# 5. Verify status
ufw status verbose
```

> [!WARNING]
> Never open ports 3306, 3307, 8080, 8081, 8082, 8083, 8090, 9090, or 3100 in UFW. All database, WebSocket, and metrics services communicate exclusively across the internal Docker bridge network `hris_network` or VPS loopback (`127.0.0.1`).

---

## 5. Intrusion Prevention (Fail2ban)

Protect SSH and Nginx from automated brute-force attacks:

```bash
cat << 'EOF' > /etc/fail2ban/jail.local
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5

[sshd]
enabled = true
port    = 22
mode    = aggressive
EOF

systemctl enable fail2ban
systemctl restart fail2ban
fail2ban-client status sshd
```

---

## 6. Swap & Memory Optimization (4 GB RAM Sizing)

To prevent MySQL InnoDB and PHP processes from triggering the Linux Out-Of-Memory (OOM) killer during traffic spikes or batch jobs, configure a 2 GB swapfile on the SSD:

```bash
# Create 2GB swapfile
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile

# Persist swap across reboots
echo '/swapfile none swap sw 0 0' >> /etc/fstab

# Tune swappiness (prefer RAM, swap only under heavy pressure)
sysctl vm.swappiness=10
sysctl vm.vfs_cache_pressure=50
echo 'vm.swappiness=10' >> /etc/sysctl.d/99-hris-memory.conf
echo 'vm.vfs_cache_pressure=50' >> /etc/sysctl.d/99-hris-memory.conf
```

---

## 7. Docker Engine & Compose Installation

Install official Docker packages for Ubuntu 24.04:

```bash
# Add Docker official GPG key
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc

# Add repository to apt sources
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  tee /etc/apt/sources.list.d/docker.list > /dev/null

apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Grant deployer user Docker management permissions
usermod -aG docker deployer
```

### Docker Daemon Hardening (`/etc/docker/daemon.json`)

Configure global container log rotation to prevent SSD exhaustion:

```bash
cat << 'EOF' > /etc/docker/daemon.json
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "50m",
    "max-file": "3"
  },
  "live-restore": true,
  "userland-proxy": false
}
EOF

systemctl restart docker
```

---

## 8. Automated Security Patches (`unattended-upgrades`)

Ensure the VPS automatically installs security updates:

```bash
cat << 'EOF' > /etc/apt/apt.conf.d/20auto-upgrades
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
EOF

systemctl restart unattended-upgrades
```

---

## 9. TLS / SSL Certificate Provisioning (Certbot)

To issue Let's Encrypt certificates before launching the production stack:

```bash
# Install certbot
apt-get install -y certbot

# Issue standalone certificate for your domain (ensure DNS A record points to VPS IP)
certbot certonly --standalone \
  --preferred-challenges http \
  -d hris.example.com \
  --email admin@example.com \
  --agree-tos \
  --no-eff-email

# Set up automated certificate renewal hook
cat << 'EOF' > /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh
#!/bin/bash
docker exec hris_nginx nginx -s reload
EOF
chmod +x /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh
```

---

## 10. Summary Verification Checklist

- [ ] Non-root `deployer` user created and in `sudo` + `docker` groups.
- [ ] SSH root login and password authentication disabled.
- [ ] UFW active allowing only ports 22, 80, and 443.
- [ ] Fail2ban active protecting SSH.
- [ ] 2 GB swapfile active with swappiness=10.
- [ ] Docker daemon configured with log rotation (50MB max-size, 3 files).
- [ ] Unattended security upgrades enabled.
- [ ] TLS certificate issued and renewal hook configured.

