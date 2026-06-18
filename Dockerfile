FROM php:8.2-apache

# Cache bust (cambia este número si vuelves a tener problemas de caché)
ENV CACHE_BUST=1

# Forzar solo mpm_prefork eliminando cualquier otro MPM
RUN rm -rf /etc/apache2/mods-enabled/mpm_* && \
    a2enmod mpm_prefork

# Instalar herramientas y extensiones necesarias
RUN apt-get update && apt-get install -y unzip libzip-dev && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install mysqli pdo pdo_mysql zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar proyecto
COPY . /var/www/html/

# Instalar dependencias PHP
RUN composer install --no-dev --optimize-autoloader

# Habilitar mod_rewrite para el .htaccess
RUN a2enmod rewrite

# Configurar Apache para leer .htaccess en la carpeta public/
RUN echo '<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# Establecer public/ como DocumentRoot
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

EXPOSE 80