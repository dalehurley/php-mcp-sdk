# Multi-stage Dockerfile for PHP MCP SDK Examples
# Supports both server and client examples with all dependencies

# Stage 1: Base PHP image with extensions
FROM php:8.2-cli as base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_sqlite \
    pcntl \
    sockets

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Stage 2: Development image with all tools
FROM base as development

# Install additional development tools
RUN apt-get update && apt-get install -y \
    vim \
    htop \
    procps \
    && rm -rf /var/lib/apt/lists/*

# Install development dependencies
RUN composer install --optimize-autoloader

# Copy application code
COPY . .

# Make scripts executable
RUN chmod +x examples/server/*.php \
    && chmod +x examples/client/*.php \
    && chmod +x examples/utils/*.php

# Expose port for HTTP servers (if needed)
EXPOSE 3000

# Default command runs the simple server
CMD ["php", "examples/server/simple-server.php"]

# Stage 3: Production server image
FROM base as server

# Copy only necessary files for production
COPY src/ ./src/
COPY examples/server/ ./examples/server/
COPY examples/docker/entrypoint-server.sh /entrypoint.sh

# Make entrypoint executable
RUN chmod +x /entrypoint.sh

# Create non-root user
RUN groupadd -r mcpuser && useradd -r -g mcpuser mcpuser
RUN chown -R mcpuser:mcpuser /app
USER mcpuser

ENTRYPOINT ["/entrypoint.sh"]

# Stage 4: Client image for testing
FROM base as client

# Copy client examples and utilities
COPY src/ ./src/
COPY examples/client/ ./examples/client/
COPY examples/utils/ ./examples/utils/
COPY examples/docker/entrypoint-client.sh /entrypoint.sh

# Make entrypoint executable
RUN chmod +x /entrypoint.sh

# Create non-root user
RUN groupadd -r mcpuser && useradd -r -g mcpuser mcpuser
RUN chown -R mcpuser:mcpuser /app
USER mcpuser

ENTRYPOINT ["/entrypoint.sh"]

# Stage 5: Laravel integration image
FROM php:8.2-fpm as laravel

# Install system dependencies for Laravel
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    nginx \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions for Laravel
RUN docker-php-ext-install \
    pdo \
    pdo_sqlite \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    sockets

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js for Inertia.js
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs

WORKDIR /var/www/html

# Copy Laravel integration examples
COPY examples/laravel/ ./

# Copy supervisor and nginx configs
COPY examples/docker/laravel/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY examples/docker/laravel/nginx.conf /etc/nginx/nginx.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80 3000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
