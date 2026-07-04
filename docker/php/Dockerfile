FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip default-mysql-client \
        libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install pdo_mysql bcmath gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Defesa anti-PAN: argumentos de funções nunca entram em stack traces de log
RUN echo "zend.exception_ignore_args=On" > /usr/local/etc/php/conf.d/zz-security.ini

WORKDIR /var/www/html
