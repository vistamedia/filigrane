# Filigrane — image de développement
# Miroir de l'hébergement o2switch : Apache + PHP 8.4 + imagick (HEIC/HEIF/AVIF)
FROM php:8.4-apache

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip zip curl ca-certificates locales \
        libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
        libmagickwand-dev imagemagick \
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Délégués HEIC/AVIF pour ImageMagick (photos iPhone).
# Le paquet "-extra" n'existe pas sur toutes les versions de Debian : on l'installe au mieux.
RUN apt-get update \
    && (apt-get install -y --no-install-recommends libmagickcore-7.q16-10-extra \
        || apt-get install -y --no-install-recommends libmagickcore-6.q16-6-extra \
        || apt-get install -y --no-install-recommends libheif1 \
        || true) \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-jpeg --with-freetype --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd intl zip pdo_mysql exif bcmath opcache

RUN pecl install imagick \
    && docker-php-ext-enable imagick

RUN a2enmod rewrite headers expires \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-filigrane.ini
COPY vhost.conf /etc/apache2/sites-available/000-default.conf
COPY entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
