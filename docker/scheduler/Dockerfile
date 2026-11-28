# =============================================================================
# NEXORA HR — Dedicated Cron Scheduler Container (Option A)
# Base: PHP 8.5.12 CLI (Debian 13 "trixie")
# =============================================================================

FROM php:8.6.0-cli-trixie

ENV DEBIAN_FRONTEND=noninteractive

# Install cron, MySQL client, zip/unzip, and system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    cron \
    default-mysql-client \
    libzip-dev \
    zip \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install required PHP extensions
RUN docker-php-ext-install -j"$(nproc)" \
    pdo \
    pdo_mysql \
    zip

# Copy custom PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom-php.ini

# Set working directory
WORKDIR /var/www/html

# Copy crontab and entrypoint script
COPY docker/scheduler/crontab /etc/cron.d/hris-scheduler
COPY docker/scheduler/entrypoint.sh /usr/local/bin/scheduler-entrypoint.sh

RUN chmod 0644 /etc/cron.d/hris-scheduler \
    && chmod +x /usr/local/bin/scheduler-entrypoint.sh \
    && touch /var/log/cron.log

ENTRYPOINT ["/usr/local/bin/scheduler-entrypoint.sh"]
CMD ["cron", "-f", "-L", "15"]

