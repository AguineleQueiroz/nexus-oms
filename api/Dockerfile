FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    libpq-dev \
    librabbitmq-dev \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql pcntl sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

COPY . .

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
