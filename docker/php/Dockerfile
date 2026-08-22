# syntax=docker/dockerfile:1
FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true \
    CHROME_PATH=/usr/bin/chromium

# --- نظام التشغيل والاعتماديات -------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip zip ca-certificates gnupg \
        libpq-dev libzip-dev libicu-dev libonig-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
        chromium fonts-noto fonts-noto-core fonts-noto-cjk \
        fonts-kacst fonts-freefont-ttf \
    && rm -rf /var/lib/apt/lists/*

# --- إضافات PHP ----------------------------------------------------------------
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql gd zip intl bcmath pcntl exif opcache sockets \
    && pecl install redis \
    && docker-php-ext-enable redis

# --- Composer -------------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Node 24 (Vite / التوليد الأمامي) --------------------------------------------
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# --- مستخدم غير جذري --------------------------------------------------------------
RUN groupadd -g "${GID}" app 2>/dev/null || true \
    && useradd -u "${UID}" -g "${GID}" -m -s /bin/bash app 2>/dev/null || true

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

WORKDIR /var/www/html
USER app

EXPOSE 9000
CMD ["php-fpm"]
