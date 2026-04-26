FROM php:8.4-apache

# เปิด mod_rewrite สำหรับ Laravel
RUN a2enmod rewrite

# ติดตั้ง dependencies สำหรับ PHP extensions ที่ Laravel ต้องการ
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath xml intl

# ติดตั้ง Node.js สำหรับรัน Vite (Frontend Build)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# ติดตั้ง Composer (เผื่อใช้งานใน container ภายหลัง)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ตั้งค่า Apache DocumentRoot ให้ชี้ไปที่โฟลเดอร์ public ของ Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# ตั้ง permission ให้โฟลเดอร์ทำงานได้
RUN chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html
