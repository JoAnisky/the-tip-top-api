# ============================
# 1. Base commune (extensions + outils)
# ============================
FROM php:8.3-apache AS base

# Activer mod_rewrite
RUN a2enmod rewrite

# Installer dépendances système + extensions PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
    locales git unzip libicu-dev g++ libpng-dev libxml2-dev libzip-dev libonig-dev libxslt-dev libpq-dev wget curl \
    && echo "en_US.UTF-8 UTF-8" > /etc/locale.gen \
    && echo "fr_FR.UTF-8 UTF-8" >> /etc/locale.gen \
    && locale-gen \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo pdo_mysql intl zip dom mbstring xsl gd \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

# Installer Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

# Config Apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Config PHP
COPY .docker/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

# ============================
# 2. Dépendances PHP (cache vendors)
# ============================
FROM base AS deps

# Copier seulement les fichiers de composer pour profiter du cache Docker
COPY composer.json composer.lock ./

# Composer install "safe" pour ne pas planter le build
RUN composer install --no-dev --prefer-dist --no-scripts --no-progress --no-suggest || true

# ============================
# 3. Dev (user dev:dev)
# ============================
FROM deps AS dev

# Créer user dev
RUN adduser --uid 1000 --disabled-password --gecos "" dev
USER dev

# Copier tout le projet
COPY --chown=1000:1000 . .

# Installer les dépendances dev
RUN composer install --prefer-dist --no-scripts --no-progress --no-suggest

# ============================
# 4. Prod (user www-data, packages dev exclus)
# ============================
FROM deps AS prod

# Copier tout le projet
COPY --chown=www-data:www-data . .

# Installer les packages prod + cache Symfony
RUN composer install --no-dev --optimize-autoloader --no-progress --no-suggest \
    && php bin/console cache:clear --env=prod --no-warmup \
    && php bin/console cache:warmup

USER www-data
