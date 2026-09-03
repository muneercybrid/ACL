# syntax=docker/dockerfile:1

# ACL production image.
#
# Three stages, so the shipped image carries no Composer, no npm, no compiler
# toolchain and no development dependencies -- only PHP, nginx and the built
# application:
#
#   vendor   composer install --no-dev
#   assets   npm ci && npm run build   (Blade's @vite needs public/build/manifest.json)
#   runtime  php-fpm + nginx under supervisor, listening on $PORT
#
# Build and run locally:
#     docker build -t acl:local .
#     docker run --rm -p 8000:8000 -e PORT=8000 --env-file .env.docker acl:local
#
# PARTIALLY VERIFIED (2026-09-03): this image builds successfully on Render, and
# the container starts, renders its nginx config and passes `nginx -t`. It has
# not yet served a request, connected to a database or run a migration -- the
# first deploy stopped at the entrypoint's APP_KEY guard, by design. There is no
# Docker on the development laptop. See docs/PROJECT_STATUS.md section 9.

# ---------------------------------------------------------------------------
# Stage 1 -- PHP dependencies
# ---------------------------------------------------------------------------
FROM composer:2.8 AS vendor

WORKDIR /app

# The manifests alone, first, so this layer is reused by every build that does
# not change a dependency. --no-scripts because Laravel's post-autoload-dump
# script boots the framework and the application code is not here yet.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
        --no-autoloader

COPY . .

# --optimize builds a classmap but keeps the PSR-4 fallback. Deliberately not
# --classmap-authoritative: the marginal gain is not worth a class-not-found
# failure that would only appear at runtime, in an image nobody can build here.
RUN composer dump-autoload --no-dev --optimize

# ---------------------------------------------------------------------------
# Stage 2 -- frontend assets
# ---------------------------------------------------------------------------
# Node 24 matches .devcontainer/devcontainer.json, so development and
# production build the bundle with the same major.
FROM node:24-bookworm-slim AS assets

WORKDIR /app

# npm ci, not npm install: it installs the lockfile exactly and fails loudly if
# package.json and package-lock.json disagree. That strictness is why
# package.json pins "name": "acl" -- npm otherwise derives the lockfile's root
# name from the containing directory, and the two files drift between machines.
#
# --ignore-scripts matches .devcontainer/post-create.sh. Tailwind's oxide binary
# and Vite's native bindings arrive as prebuilt platform packages, so no
# lifecycle script is needed to produce a working build.
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources

# CODESPACE_NAME is unset here, so vite.config.js leaves `server` undefined and
# builds with ordinary defaults. No codespace hostname reaches the bundle.
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 3 -- runtime
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-bookworm AS runtime

# The -dev headers are kept rather than purged. Purging them with
# --auto-remove is the usual size optimisation and also the usual way to
# uninstall the shared libraries the extensions just linked against; ~60 MB is
# a cheap price for an image that is certain to boot.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        default-mysql-client \
        gettext-base \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        nginx \
        supervisor \
    ; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    ; \
    rm -rf /var/lib/apt/lists/*

COPY docker/php.ini            /usr/local/etc/php/conf.d/zz-acl.ini
COPY docker/php-fpm-pool.conf  /usr/local/etc/php-fpm.d/zz-acl.conf
COPY docker/nginx.conf         /etc/nginx/nginx.conf
COPY docker/site.conf.template /etc/nginx/templates/site.conf.template
COPY docker/supervisord.conf   /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh      /usr/local/bin/acl-entrypoint
RUN chmod +x /usr/local/bin/acl-entrypoint

WORKDIR /var/www/html

# Application code first, then the two build outputs on top. vendor/ and
# public/build are in .dockerignore, so nothing here overwrites them.
COPY . .
COPY --from=vendor /app/vendor        ./vendor
COPY --from=assets /app/public/build  ./public/build

RUN set -eux; \
    mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug+rwX storage bootstrap/cache

# Render injects PORT and overrides this. 10000 is Render's own default and
# makes `docker run` without -e PORT work the same way.
ENV PORT=10000

EXPOSE 10000

# /up is Laravel's health endpoint, already wired in bootstrap/app.php.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${PORT}/up" > /dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/acl-entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
