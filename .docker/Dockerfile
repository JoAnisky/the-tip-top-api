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

# Installer Xdebug et ses dépendances
RUN pecl install xdebug-3.3.1 \
    && docker-php-ext-enable xdebug

# Configurer Xdebug pour le débogage à distance
RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Créer user dev
RUN adduser --uid 1000 --disabled-password --gecos "" dev
USER dev

# Copier tout le projet avec les bons droits
COPY --chown=1000:1000 . .

# Installer les dépendances dev (optionnel, mieux vaut lancer la commande manuellement pour ne pas le faire a chaque rebuild)
# RUN composer install --prefer-dist --no-scripts --no-progress --no-suggest --no-interaction

# ============================
# 4. Prod (user www-data, packages dev exclus)
# ============================
FROM deps AS prod

ARG APP_ENV=prod
ARG APP_SECRET=dummysecret
ENV APP_ENV=${APP_ENV}
ENV APP_SECRET=${APP_SECRET}

COPY --from=deps /var/www/html/vendor /var/www/html/vendor
COPY --chown=www-data:www-data . .

RUN mkdir -p var/cache var/log config/jwt \
    && chown -R www-data:www-data var config/jwt \
    && composer install --no-dev --optimize-autoloader --no-progress --no-suggest \
    && php bin/console assets:install --no-interaction --no-debug \
    && php bin/console cache:clear --env=${APP_ENV} --no-warmup || true \
    && php bin/console cache:warmup || true \
    && chown -R www-data:www-data /var/www/html

USER www-data
