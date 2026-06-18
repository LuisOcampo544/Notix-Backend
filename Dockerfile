FROM php:8.2-fpm

# Instalar Nginx y herramientas necesarias
RUN apt-get update && apt-get install -y nginx unzip libzip-dev && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install mysqli pdo pdo_mysql zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar proyecto y configuración de Nginx
COPY . /var/www/html/
COPY nginx.conf /etc/nginx/sites-available/default

# Instalar dependencias PHP
RUN composer install --no-dev --optimize-autoloader

# Ajustar permisos
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

# Iniciar PHP-FPM en segundo plano y Nginx en primer plano
CMD sh -c "php-fpm -D && nginx -g 'daemon off;'"