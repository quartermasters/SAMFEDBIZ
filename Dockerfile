# samfedbiz.com Docker Container
# Owner: Quartermasters FZC | Stakeholder: AXIVAI.COM
# PHP 8.2 with Apache for Federal BD Platform - Enhanced for Live Testing
# Includes: Analytics, Quality Gates, SFBAI Chat, News Scanning, Brief Engine

FROM php:8.2-apache

# Set environment variables
ENV DEBIAN_FRONTEND=noninteractive
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV TZ=Asia/Dubai

# Install system dependencies including SQLite
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    cron \
    supervisor \
    nodejs \
    npm \
    default-mysql-client \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure Apache with enhanced security headers
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN a2enmod rewrite headers ssl expires

# Add security headers
RUN echo 'Header always set X-Content-Type-Options nosniff' >> /etc/apache2/apache2.conf
RUN echo 'Header always set X-Frame-Options DENY' >> /etc/apache2/apache2.conf
RUN echo 'Header always set X-XSS-Protection "1; mode=block"' >> /etc/apache2/apache2.conf
RUN echo 'Header always set Referrer-Policy "strict-origin-when-cross-origin"' >> /etc/apache2/apache2.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Create necessary directories and set permissions
RUN mkdir -p /var/log/samfedbiz \
    && mkdir -p /var/www/html/reports \
    && mkdir -p /var/www/html/build \
    && mkdir -p /var/www/html/storage/briefs \
    && mkdir -p /var/www/html/storage/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chown -R www-data:www-data /var/log/samfedbiz \
    && chmod -R 755 /var/www/html \
    && chmod -R 644 /var/log/samfedbiz \
    && chmod +x /var/www/html/scripts/*.sh

# Copy Apache configuration
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# Copy cron configuration
COPY docker/cron/samfedbiz-cron /etc/cron.d/samfedbiz-cron
RUN chmod 0644 /etc/cron.d/samfedbiz-cron
RUN crontab /etc/cron.d/samfedbiz-cron

# Copy supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create environment file template
RUN cp .env.example .env

# Install Node.js dependencies for quality gates
RUN npm install -g eslint stylelint axe-core

# Set timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Add comprehensive health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=90s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Expose port 80
EXPOSE 80

# Add startup script for live testing
COPY docker/startup.sh /startup.sh
RUN chmod +x /startup.sh

# Start supervisor (manages Apache + Cron + Quality Gates)
CMD ["/startup.sh"]