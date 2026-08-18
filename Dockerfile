# syntax=docker/dockerfile:1

# RSMS production image for Coolify.
# Three stages: build frontend assets, install PHP dependencies, assemble
# the final runtime. Nothing here talks to a database -- migrate/seed/
# bootstrap-admin are explicit Coolify deploy-hook commands, run after the
# container starts against real runtime credentials, never at build time.

# ---------------------------------------------------------------------------
# Stage 1: frontend assets (Vite + Tailwind v4). Node is build-time only --
# no Node runtime ships in the final image.
# ---------------------------------------------------------------------------
FROM node:22-bookworm-slim AS frontend-build
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources

RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2: PHP dependencies via Composer, using composer.lock exactly.
# No `composer update`. No application command that touches a database.
# ---------------------------------------------------------------------------
FROM php:8.4-cli-bookworm AS vendor-build
WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# php:8.4-cli-bookworm doesn't compile gd/zip in by default. Composer's
# platform check (correctly) refuses to resolve phpoffice/phpspreadsheet
# without them, since neither has a meaningful userland polyfill (unlike
# e.g. mbstring, which a bundled symfony/polyfill package silently satisfies
# here). Install exactly the extensions actually missing, rather than
# suppressing the check.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd zip

COPY composer.json composer.lock ./
COPY database ./database
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY routes ./routes
COPY resources ./resources
COPY artisan ./artisan

# composer's post-autoload-dump hook runs `artisan package:discover`, which
# boots the framework -- it needs bootstrap/cache/ (packages/services
# manifest) and storage/framework/views (Blade's compiled-view cache path,
# resolved via realpath() -- which returns false, not a path, for a
# directory that doesn't exist yet, and Laravel's Compiler rejects that as
# "please provide a valid cache path") to exist and be writable. storage/
# was never copied into this stage at all, and .dockerignore excludes
# bootstrap/cache/*.php (stale generated files aren't shipped), leaving
# that directory empty -- COPY doesn't materialize an empty directory
# either way. RUN uses /bin/sh (dash on Debian), which does NOT support
# brace expansion -- each path is listed explicitly, not as one {a,b,c} glob.
RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

# ---------------------------------------------------------------------------
# Stage 3: runtime -- Nginx + PHP-FPM under supervisord, one container,
# one clear pair of foreground processes (see docker/supervisord.conf).
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-bookworm AS runtime

# Exact extensions justified by laravel/framework + phpoffice/phpspreadsheet
# (verified against composer.lock's own require blocks -- see DEPLOYMENT.md).
# ctype/filter/hash/openssl/session/tokenizer/iconv/zlib/json ship compiled
# in to the base php image already; install-php-extensions no-ops on those
# and only builds what's actually missing.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    pdo_pgsql \
    mbstring \
    dom \
    fileinfo \
    gd \
    simplexml \
    xml \
    xmlreader \
    xmlwriter \
    zip \
    opcache

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/99-rsms.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# Application source (excludes tests/, .git, node_modules, local env files --
# see .dockerignore). vendor/ and public/build/ come from the earlier stages,
# never rebuilt or re-fetched here.
COPY . .
COPY --from=vendor-build /app/vendor ./vendor
COPY --from=frontend-build /app/public/build ./public/build

# Same /bin/sh-has-no-brace-expansion note as the vendor-build stage above --
# each path listed explicitly.
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS -H "Accept: application/json" http://127.0.0.1:8080/up || exit 1

RUN apt-get update && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
