FROM php:8.2-fpm

# Instalar Nginx y herramientas necesarias
RUN apt-get update && apt-get install -y nginx unzip libzip-dev && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install mysqli pdo pdo_mysql zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar proyecto
COPY . /var/www/html/

# Copiar configuración de Nginx
COPY nginx.conf /etc/nginx/sites-available/default

# Instalar dependencias PHP
RUN composer install --no-dev --optimize-autoloader

# Ajustar permisos
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

# Arrancar PHP-FPM y Nginx
CMD service php8.2-fpm start && nginx -g "daemon off;"