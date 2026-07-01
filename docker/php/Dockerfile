FROM php:8.4-fpm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev librdkafka-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql zip \
    && pecl install redis rdkafka \
    && docker-php-ext-enable redis rdkafka opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-scripts --no-interaction --prefer-dist --no-progress

COPY . .

# Dev-only JWT keypair baked into the image (passphrase matches .env default).
ARG JWT_PASSPHRASE=0bb3870d244cf1c1f8a15a064bc0f643bd60858d1ff77b7c6b10a06bb13bb937
RUN mkdir -p config/jwt \
    && openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa \
        -pkeyopt rsa_keygen_bits:4096 -pass pass:"$JWT_PASSPHRASE" \
    && openssl pkey -in config/jwt/private.pem -passin pass:"$JWT_PASSPHRASE" \
        -out config/jwt/public.pem -pubout \
    && composer dump-autoload --optimize \
    && php bin/console assets:install public --no-interaction \
    && chown -R www-data:www-data /var/www

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]