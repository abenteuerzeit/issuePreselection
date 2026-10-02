FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev libjpeg-dev libfreetype6-dev libicu-dev libzip-dev \
    libxml2-dev libcurl4-openssl-dev libonig-dev zlib1g-dev \
    git unzip curl ca-certificates default-mysql-client \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd intl zip mysqli pdo_mysql bcmath fileinfo ftp mbstring opcache soap exif

RUN a2enmod rewrite headers

RUN printf '%s\n' \
    'memory_limit = 512M' \
    'max_execution_time = 300' \
    'upload_max_filesize = 128M' \
    'post_max_size = 128M' \
    'max_input_vars = 5000' \
    'date.timezone = UTC' \
    'display_errors = On' \
    'display_startup_errors = On' \
    'log_errors = On' \
    'error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT' \
    > /usr/local/etc/php/conf.d/custom-ojs.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ARG OJS_VERSION=stable-3_5_0
ARG OJS_REPO=https://github.com/pkp/ojs.git

RUN git clone --depth 1 --branch ${OJS_VERSION} \
    --recurse-submodules --shallow-submodules \
    ${OJS_REPO} /var/www/html

RUN cd /var/www/html/lib/pkp && composer install --no-dev

RUN if [ -d "/var/www/html/plugins/paymethod/paypal" ]; then \
        cd /var/www/html/plugins/paymethod/paypal && \
        composer install --no-dev --no-scripts; \
    fi

RUN if [ -d "/var/www/html/plugins/generic/citationStyleLanguage" ]; then \
        cd /var/www/html/plugins/generic/citationStyleLanguage && \
        composer install --no-dev --no-scripts; \
    fi

RUN npm install && npm run build

RUN mkdir -p /var/www/files /var/www/html/cache /var/www/html/public \
    && chown -R www-data:www-data /var/www/files /var/www/html/cache /var/www/html/public

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]