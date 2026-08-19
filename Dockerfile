# syntax=docker/dockerfile:1.7
#
# Produkčný obraz pre Laravel 12 + Inertia/Vue 3 (nginx + php-fpm + queue worker).
#
#   docker build -t ball-app .
#   docker run --rm -p 8080:8080 --env-file .env.production ball-app
#
# Konfigurácia sa načítava výhradne z premenných prostredia kontajnera
# (APP_KEY, DB_*, MAIL_* ...). Do obrazu sa žiadny .env nekopíruje.

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22


##############################################################################
# 1) Základ: PHP + rozšírenia (spoločné pre build aj beh)
##############################################################################
FROM php:${PHP_VERSION}-fpm-alpine AS php-base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

# gd + zip = dompdf a fast-excel; pdo_* = podporované databázy
RUN install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        zip \
    && rm -rf /tmp/*

WORKDIR /var/www/html


##############################################################################
# 2) PHP závislosti
##############################################################################
FROM php-base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

# Najprv len composer súbory – vrstva sa cachuje, kým sa nezmenia závislosti.
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-progress

COPY . .

# Skripty (package:discover) sa spúšťajú až v entrypointe, keď je známe prostredie.
RUN composer dump-autoload --no-dev --optimize --no-scripts


##############################################################################
# 3) Frontend assety (Vite)
##############################################################################
FROM node:${NODE_VERSION}-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm \
    npm ci --no-audit --no-fund

COPY vite.config.js postcss.config.js tailwind.config.js jsconfig.json ./
COPY resources ./resources

# app.js importuje ZiggyVue z vendor/, tailwind skenuje vendor/laravel/framework.
COPY --from=vendor /var/www/html/vendor ./vendor

RUN npm run build


##############################################################################
# 4) Finálny obraz
##############################################################################
FROM php-base AS app

RUN apk add --no-cache nginx supervisor tzdata

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PORT=8080

# --- PHP ------------------------------------------------------------------
COPY <<'EOF' /usr/local/etc/php/conf.d/zz-app.ini
memory_limit = 512M
upload_max_filesize = 25M
post_max_size = 25M
max_execution_time = 120
expose_php = Off

opcache.enable = 1
opcache.memory_consumption = 192
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
EOF

# clear_env=no – bez toho by sa premenné prostredia nedostali do PHP procesov.
COPY <<'EOF' /usr/local/etc/php-fpm.d/zz-app.conf
[www]
clear_env = no
catch_workers_output = yes
decorate_workers_output = no
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500
EOF

# --- nginx ----------------------------------------------------------------
COPY <<'EOF' /etc/nginx/nginx.conf
user www-data;
worker_processes auto;
error_log /dev/stderr notice;
pid /run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    access_log /dev/stdout;
    sendfile on;
    tcp_nopush on;
    keepalive_timeout 65;
    client_max_body_size 25m;
    server_tokens off;

    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/javascript application/javascript application/json image/svg+xml;

    server {
        listen 8080;
        server_name _;
        root /var/www/html/public;
        index index.php;
        charset utf-8;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        # Vite assety majú hash v názve – môžu sa cachovať natrvalo.
        location /build/ {
            expires 1y;
            access_log off;
            add_header Cache-Control "public, immutable";
            try_files $uri =404;
        }

        location = /favicon.ico { access_log off; log_not_found off; }
        location = /robots.txt  { access_log off; log_not_found off; }

        location ~ \.php$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            fastcgi_hide_header X-Powered-By;
            fastcgi_read_timeout 120;
        }

        location ~ /\.(?!well-known).* {
            deny all;
        }

        error_page 404 /index.php;
    }
}
EOF

# --- supervisor -----------------------------------------------------------
COPY <<'EOF' /etc/supervisord.conf
[supervisord]
nodaemon=true
user=root
logfile=/dev/null
logfile_maxbytes=0
pidfile=/run/supervisord.pid

[program:php-fpm]
command=php-fpm -F
priority=10
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g "daemon off;"
priority=20
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:queue]
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
priority=30
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
EOF

# --- entrypoint -----------------------------------------------------------
COPY <<'EOF' /usr/local/bin/docker-entrypoint
#!/bin/sh
set -e

cd /var/www/html

if [ "${PORT:-8080}" != "8080" ]; then
    sed -i "s/listen 8080;/listen ${PORT};/" /etc/nginx/nginx.conf
fi

if [ -z "${APP_KEY}" ]; then
    echo "VAROVANIE: APP_KEY nie je nastavený – nastav ho v prostredí kontajnera." >&2
fi

# SQLite: súbor musí existovať pred migráciami.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    mkdir -p "$(dirname "$DB_FILE")"
    [ -f "$DB_FILE" ] || touch "$DB_FILE"
fi

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

php artisan package:discover --ansi

# Cache sa stavia až tu – pri builde ešte nie sú známe skutočné env premenné.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

chown -R www-data:www-data storage bootstrap/cache database

exec "$@"
EOF

RUN chmod +x /usr/local/bin/docker-entrypoint \
    && mkdir -p /run/nginx /var/lib/nginx/tmp \
    && chown -R www-data:www-data /run/nginx /var/lib/nginx

# --- aplikácia ------------------------------------------------------------
COPY --from=vendor --chown=www-data:www-data /var/www/html ./
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD wget -qO- "http://127.0.0.1:${PORT:-8080}/up" > /dev/null || exit 1

ENTRYPOINT ["docker-entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
