# syntax=docker/dockerfile:1
#
# One image, one tenant per running container.
#
# The image is tenant-agnostic: everything that differs between institutions
# (database credentials, APP_KEY, hostname, admin identity) arrives as
# environment variables at container start. Nothing tenant-specific is baked
# in, so the same tag can be rolled out to every tenant.
#
# The SPA is served from this container too, which is what lets the frontend
# reach its own backend at a same-origin /api and lets branding be applied
# without a per-tenant build.

# ---------------------------------------------------------------------------
# 1. Build the single-page app
# ---------------------------------------------------------------------------
FROM node:24-alpine AS frontend

WORKDIR /build

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ ./
RUN npm run build

# ---------------------------------------------------------------------------
# 2. Resolve PHP dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /build

COPY backend/composer.json backend/composer.lock ./
# No scripts here: artisan isn't present yet, and package discovery runs in the
# final stage where the full application exists.
RUN composer install \
      --no-dev \
      --no-scripts \
      --no-autoloader \
      --prefer-dist \
      --no-interaction

COPY backend/ ./
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---------------------------------------------------------------------------
# 3. Runtime
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4-alpine AS runtime

# pdo_pgsql/pdo_mysql for the tenant database; intl + zip are Laravel staples;
# gd handles the uploaded logos.
RUN apk add --no-cache curl \
 && install-php-extensions \
      pdo_pgsql \
      pdo_mysql \
      intl \
      zip \
      gd \
      opcache \
      pcntl

WORKDIR /app

COPY --from=vendor /build /app
COPY --from=frontend /build/dist/ /app/public/

COPY infra/tenant-app/Caddyfile /etc/frankenphp/Caddyfile
COPY infra/tenant-app/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Route and view caches are environment-independent, so they can be baked.
# config:cache deliberately is NOT — it would freeze build-time env values and
# ignore the per-tenant variables injected at start. The entrypoint runs it.
RUN php artisan route:cache \
 && php artisan view:cache \
 && chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Uploaded theses, posters, and branding assets live here. Mount a volume or
# this data dies with the container.
VOLUME ["/app/storage/app"]

ENV SERVER_NAME=:80
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["entrypoint"]
