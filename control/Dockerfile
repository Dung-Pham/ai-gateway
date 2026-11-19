# Giao diện điều khiển (Laravel 12 + Filament 3). Ba giai đoạn: thư viện PHP -> tài nguyên giao diện -> ảnh chạy.
# Ảnh không chứa bí mật: APP_KEY, mật khẩu CSDL, mã quản trị được nạp lúc chạy từ Docker secrets (docker/entrypoint.sh).
ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-cli-bookworm AS vendor
COPY --from=ghcr.io/mlocati/php-extension-installer:2.7 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions intl zip bcmath pdo_mysql pdo_sqlite gd
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader

FROM node:20-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY --from=vendor /app/vendor ./vendor
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY resources ./resources
COPY app ./app
RUN npm run build

FROM php:${PHP_VERSION}-fpm-bookworm AS app
COPY --from=ghcr.io/mlocati/php-extension-installer:2.7 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions intl zip bcmath pdo_mysql pdo_sqlite gd opcache \
    && rm -f /usr/local/bin/install-php-extensions \
    && apt-get update && apt-get install -y --no-install-recommends tini && rm -rf /var/lib/apt/lists/*
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-aigw.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-aigw.conf
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/aigw-entrypoint
RUN chmod 0555 /usr/local/bin/aigw-entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan package:discover --ansi \
    && APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan filament:cache-components \
    && APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan icons:cache \
    && APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan view:cache \
    && chmod -R a+rwX storage bootstrap/cache   # chạy với uid tùy ý (AIGW_UID); volume chỉ của container này
USER www-data
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr LOG_LEVEL=warning
ENTRYPOINT ["/usr/bin/tini", "--", "aigw-entrypoint"]
CMD ["php-fpm", "-F"]
EXPOSE 9000

# Cửa trước: Caddy phục vụ tệp tĩnh và chuyển PHP sang php-fpm. HTTP mặc định; bật TLS bằng WEB_MODE=tls (xem deploy/).
FROM caddy:2.8-alpine AS web
COPY --from=app /app/public /srv/public
COPY docker/http.Caddyfile docker/tls.Caddyfile /etc/caddy/
