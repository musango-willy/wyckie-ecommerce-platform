# Use an official production PHP image with Apache pre-installed
FROM php:8.2-apache

# Install native Linux system dependencies and GD image libraries
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install gd pdo pdo_mysql

# Enable Apache mod_rewrite for custom route handling
RUN a2enmod rewrite

# Install Composer globally inside the cloud container
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy your local ecommerce repository files directly into the web root
WORKDIR /var/www/html
COPY . .

# Run Composer install automatically during the container build process
RUN composer install --no-interaction --optimize-autoloader

# Adjust file storage permissions for your media optimization uploads folder
RUN mkdir -p uploads && chmod -R 775 uploads && chown -R www-data:www-data uploads

# Configure Apache to bind to Render's dynamic $PORT environment variable
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g' /etc/apache2/sites-available/000-default.conf

# Set default port fallback for Render environment consistency
ENV PORT=80
EXPOSE ${PORT}
