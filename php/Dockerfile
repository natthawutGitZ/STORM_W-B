FROM php:8.2-apache

# เปิด mod rewrite
RUN a2enmod rewrite

# Install dependencies for GD and other extensions
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mysqli

# Copy PHP configuration
COPY custom.ini /usr/local/etc/php/conf.d/custom.ini

# ตั้ง permission
RUN chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html
