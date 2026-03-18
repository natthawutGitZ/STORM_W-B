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

# Pass Docker environment variables to PHP via Apache
RUN echo 'PassEnv GOOGLE_CLIENT_ID' >> /etc/apache2/conf-enabled/passenv.conf \
    && echo 'PassEnv GOOGLE_CLIENT_SECRET' >> /etc/apache2/conf-enabled/passenv.conf \
    && echo 'PassEnv DISCORD_BOT_API_URL' >> /etc/apache2/conf-enabled/passenv.conf \
    && echo 'PassEnv MYSQL_DATABASE' >> /etc/apache2/conf-enabled/passenv.conf \
    && echo 'PassEnv MYSQL_USER' >> /etc/apache2/conf-enabled/passenv.conf \
    && echo 'PassEnv MYSQL_PASSWORD' >> /etc/apache2/conf-enabled/passenv.conf \
    && echo 'PassEnv DOCKER_CONTAINER' >> /etc/apache2/conf-enabled/passenv.conf

WORKDIR /var/www/html
