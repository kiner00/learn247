FROM php:8.3-fpm-alpine

# ─── System deps ──────────────────────────────────────────────────────────────
RUN apk add --no-cache \
    autoconf \
    bash \
    curl \
    g++ \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    make \
    oniguruma-dev \
    icu-dev \
    unzip \
    zip

# ─── PHP extensions ───────────────────────────────────────────────────────────
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        gd \
        mbstring \
        exif \
        pcntl \
        bcmath \
        zip \
        intl \
        opcache

# Redis extension
RUN pecl install redis \
 && docker-php-ext-enable redis

# PCOV for code coverage
RUN pecl install pcov \
 && docker-php-ext-enable pcov

# ─── Composer ─────────────────────────────────────────────────────────────────
COPY --from=composer:2.7 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www

EXPOSE 9000

CMD ["php-fpm"]
