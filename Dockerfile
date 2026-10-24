# =============================================================================
# Employee HRIS & Leave Management System - Dockerfile
# Base: PHP 8.5.12 with Apache (Debian 13 "trixie")
#
# Pinned to an exact PHP patch release and Debian suite so every build ships
# the same runtime. To apply a new PHP security release, bump the tag below
# (see https://www.php.net/supported-versions.php), rebuild, and run the tests.
# =============================================================================

FROM php:8.5.12-apache-trixie

# Set non-interactive debian frontend
ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies and MySQL client
RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    zip \
    unzip \
    default-mysql-client \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install required PHP extensions
RUN docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    zip

# Enable Apache modules (mod_rewrite, mod_headers)
RUN a2enmod rewrite headers

# Copy Apache VirtualHost configuration
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Copy custom PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom-php.ini

# Install Composer from official image (pinned; bump deliberately)
COPY --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer

# Set container working directory
WORKDIR /var/www/html

# Copy application source code into image
COPY . /var/www/html

# Ensure proper permissions for Apache user
RUN chown -R www-data:www-data /var/www/html

# Expose standard Apache HTTP port
EXPOSE 80

# Launch Apache in the foreground
CMD ["apache2-foreground"]
