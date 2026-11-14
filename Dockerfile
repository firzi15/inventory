FROM php:8.2-apache-bookworm

# Install ekstensi PHP yang dibutuhkan aplikasi (tanpa bergantung repo eksternal)
RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    bcmath \
    opcache

# Aktifkan modul Apache rewrite & headers
RUN a2enmod rewrite headers

# Konfigurasi custom PHP
RUN { \
    echo 'upload_max_filesize = 64M'; \
    echo 'post_max_size = 64M'; \
    echo 'memory_limit = 256M'; \
    echo 'max_execution_time = 300'; \
    echo 'date.timezone = Asia/Jakarta'; \
} > /usr/local/etc/php/conf.d/custom-php.ini

# Set working directory
WORKDIR /var/www/html

# Salin source code project
COPY . /var/www/html/

# Siapkan entrypoint script & hak akses
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/database /var/www/html/app/Config

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]

