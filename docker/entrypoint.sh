#!/usr/bin/env bash
# ACL container entrypoint.
#
# Everything below happens at container START, not at image build time, because
# the hosting platform injects environment variables at run time. A build-time
# `config:cache` would bake in an empty APP_KEY and a database host of
# 127.0.0.1, and the image would be broken in a way that only shows up on the
# first request.
#
# Fails loudly and early rather than serving a half-configured application.
set -euo pipefail

cd /var/www/html

say()  { printf '\033[36m[acl]\033[0m %s\n' "$1"; }
warn() { printf '\033[33m[acl] %s\033[0m\n' "$1" >&2; }
die()  { printf '\033[31m[acl] %s\033[0m\n' "$1" >&2; exit 1; }

# ---------------------------------------------------------------------------
# 1. Bind nginx to the port the platform chose
# ---------------------------------------------------------------------------
: "${PORT:=10000}"
export PORT
say "Binding nginx to port ${PORT}"

# The allow-list matters: without it envsubst would also replace nginx's own
# $uri, $query_string and $realpath_root with empty strings, and the config
# would pass `nginx -t` while serving nothing.
envsubst '${PORT}' \
    < /etc/nginx/templates/site.conf.template \
    > /etc/nginx/conf.d/default.conf
nginx -t

# ---------------------------------------------------------------------------
# 2. Refuse to start misconfigured
# ---------------------------------------------------------------------------
if [ -z "${APP_KEY:-}" ]; then
    die "APP_KEY is empty.
       Generate one ONCE, locally, with:  php artisan key:generate --show
       then set it as an environment variable on the service.
       It is deliberately not generated here: a new key on every deploy
       invalidates every session and makes every encrypted column unreadable."
fi

if [ "${APP_DEBUG:-false}" = "true" ]; then
    warn "APP_DEBUG=true -- stack traces, environment values and SQL will be shown to visitors. Set APP_DEBUG=false."
fi

if [ "${APP_ENV:-production}" != "production" ]; then
    warn "APP_ENV=${APP_ENV:-} rather than 'production'."
fi

case "${APP_URL:-}" in
    https://*) ;;
    "")        warn "APP_URL is unset; generated links and password-reset URLs will be wrong." ;;
    *)         warn "APP_URL=${APP_URL} is not https://; generated links will point at plain HTTP." ;;
esac

# ---------------------------------------------------------------------------
# 3. Caches
# ---------------------------------------------------------------------------
# package:discover writes bootstrap/cache/packages.php. Composer's autoload
# dump ran with --no-scripts in the build, so this has not happened yet; doing
# it here means the first real request is not the one that pays for it.
say "Discovering packages and building caches"
php artisan package:discover --no-ansi
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ---------------------------------------------------------------------------
# 4. Database
# ---------------------------------------------------------------------------
# Set ACL_RUN_MIGRATIONS=false and use the platform's pre-deploy hook once this
# service runs more than one instance: concurrent `migrate` on boot races.
if [ "${ACL_RUN_MIGRATIONS:-true}" = "true" ]; then
    say "Waiting for the database"
    ready=false
    for attempt in $(seq 1 30); do
        if php artisan db:show --json >/dev/null 2>&1; then
            ready=true
            say "Database reachable after ${attempt} attempt(s)."
            break
        fi
        sleep 2
    done

    if [ "$ready" != "true" ]; then
        php artisan db:show 2>&1 | tail -20 >&2 || true
        die "Database unreachable after 60s. Check DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD; that the schema already exists (Laravel does not create it); and that the provider allows connections from this service's egress addresses."
    fi

    say "Applying migrations"
    php artisan migrate --force --no-interaction
else
    say "ACL_RUN_MIGRATIONS is not 'true' -- skipping migrations."
fi

# ---------------------------------------------------------------------------
# 5. Hand over
# ---------------------------------------------------------------------------
# Everything above ran as root. php-fpm serves as www-data and still needs to
# write compiled views and the log stream's fallback file, so give those two
# trees back before the workers start. Sessions, cache and the queue are all in
# the database, so nothing else here is written at request time.
chown -R www-data:www-data storage bootstrap/cache

say "Starting php-fpm and nginx"
exec "$@"
