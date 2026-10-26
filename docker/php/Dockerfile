FROM php:8.4-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 mysqli mbstring gd \
    && rm -rf /var/lib/apt/lists/*

COPY php.ini /usr/local/etc/php/conf.d/fengcms.ini
RUN mkdir -p /var/lib/fengcms && chown www-data:www-data /var/lib/fengcms
ARG LOCAL_UID=1000
RUN test "$LOCAL_UID" -gt 0 && usermod -u "$LOCAL_UID" www-data
COPY entrypoint.sh /usr/local/bin/fengcms-entrypoint
RUN chmod 0755 /usr/local/bin/fengcms-entrypoint
ENTRYPOINT ["fengcms-entrypoint"]
CMD ["php-fpm"]
WORKDIR /var/www/html
