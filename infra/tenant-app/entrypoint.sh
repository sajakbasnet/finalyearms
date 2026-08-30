#!/bin/sh
#
# Container start-up for a single tenant.
#
# Runs after the per-tenant environment has been injected, which is why the
# config cache is built here rather than at image build time.

set -eu

: "${APP_KEY:?APP_KEY must be set — each tenant needs its own, or encrypted payloads and tokens become portable between tenants}"
: "${DB_DATABASE:?DB_DATABASE must be set}"

cd /app

# public/storage -> storage/app/public, so uploaded logos are reachable.
if [ ! -L /app/public/storage ]; then
    php artisan storage:link --quiet || true
fi

php artisan config:cache
php artisan event:cache

# One-off command (`docker compose run <tenant> php artisan migrate`). The
# caches above still apply, but no server or workers are started.
if [ "$#" -gt 0 ]; then
    exec "$@"
fi

# Schema changes are normally driven by the control plane across all tenants at
# once (see infra/control-plane/provision.sh migrate). This switch exists for
# single-tenant or development deployments where that pipeline isn't in play.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "entrypoint: running migrations"
    php artisan migrate --force --isolated
fi

if [ "${RUN_TENANT_BOOTSTRAP:-false}" = "true" ]; then
    echo "entrypoint: seeding tenant bootstrap data"
    php artisan db:seed --class=Database\\Seeders\\TenantBootstrapSeeder --force
fi

# QUEUE_CONNECTION=database, so each tenant needs its own worker. Kept in this
# container to preserve the one-container-per-tenant boundary.
if [ "${RUN_QUEUE_WORKER:-true}" = "true" ]; then
    php artisan queue:work --tries=3 --max-time=3600 --sleep=1 &
    echo "entrypoint: queue worker started (pid $!)"
fi

if [ "${RUN_SCHEDULER:-true}" = "true" ]; then
    php artisan schedule:work &
    echo "entrypoint: scheduler started (pid $!)"
fi

exec frankenphp run --config /etc/frankenphp/Caddyfile
