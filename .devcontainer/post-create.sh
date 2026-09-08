#!/usr/bin/env bash
# Provision the ACL application inside the devcontainer.
#
# Runs once at container CREATE time, after install-services.sh. Everything
# here is idempotent, because a codespace REBUILD re-runs it against an
# existing .env, an existing database and an existing vendor/ directory.
#
# Re-runnable by hand at any time:
#     bash .devcontainer/post-create.sh
set -euo pipefail

# shellcheck source=./config.sh
source "$(dirname "${BASH_SOURCE[0]}")/config.sh"
cd "$ACL_REPO_ROOT"

say()  { printf '\n\033[36m[ACL]\033[0m \033[1m%s\033[0m\n' "$1"; }
info() { printf '       %s\n' "$1"; }
warn() { printf '\033[33m[ACL] %s\033[0m\n' "$1"; }
die()  { printf '\033[31m[ACL] %s\033[0m\n' "$1" >&2; exit 1; }

# ---------------------------------------------------------------------------
# 1. Database service
# ---------------------------------------------------------------------------
# Delegated so there is exactly one implementation of "start the database and
# wait until it is genuinely ready", shared with postStartCommand.
say "Starting backing services (MariaDB, Redis, Mailpit)"
bash "$ACL_REPO_ROOT/.devcontainer/start-services.sh"

# ---------------------------------------------------------------------------
# 2. Schemas, user and privileges
# ---------------------------------------------------------------------------
# `sudo mysql` authenticates as root over the unix socket; no root password
# is involved or created. acl_user is granted rights on the two ACL schemas
# only -- least privilege per ADR-0005. Tests need CREATE/DROP on acl_test
# because RefreshDatabase rebuilds that schema on every run.
say "Provisioning schemas and the least-privilege user"
sudo mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${ACL_DB_NAME}\`      CHARACTER SET ${ACL_DB_CHARSET} COLLATE ${ACL_DB_COLLATION};
CREATE DATABASE IF NOT EXISTS \`${ACL_DB_TEST_NAME}\` CHARACTER SET ${ACL_DB_CHARSET} COLLATE ${ACL_DB_COLLATION};
CREATE USER IF NOT EXISTS '${ACL_DB_USER}'@'localhost' IDENTIFIED BY '${ACL_DB_PASSWORD}';
CREATE USER IF NOT EXISTS '${ACL_DB_USER}'@'127.0.0.1' IDENTIFIED BY '${ACL_DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${ACL_DB_NAME}\`.*      TO '${ACL_DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${ACL_DB_NAME}\`.*      TO '${ACL_DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${ACL_DB_TEST_NAME}\`.* TO '${ACL_DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${ACL_DB_TEST_NAME}\`.* TO '${ACL_DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
info "${ACL_DB_NAME} + ${ACL_DB_TEST_NAME} ready; ${ACL_DB_USER} granted on those two schemas only."

# ---------------------------------------------------------------------------
# 3. Password-free mysql client
# ---------------------------------------------------------------------------
# Writing the credentials to a 0600 ~/.my.cnf means no later command has to
# put a password on its argv, where `ps` would expose it to every process in
# the container. It also makes `mysql acl` work with no flags.
say "Configuring the mysql client"
cat > "$HOME/.my.cnf" <<CNF
# Written by .devcontainer/post-create.sh -- development container only.
[client]
host = ${ACL_DB_HOST}
port = ${ACL_DB_PORT}
user = ${ACL_DB_USER}
password = ${ACL_DB_PASSWORD}
CNF
chmod 600 "$HOME/.my.cnf"
info "Run 'mysql ${ACL_DB_NAME}' to open a shell -- no flags needed."

# ---------------------------------------------------------------------------
# 4. Dependencies
# ---------------------------------------------------------------------------
say "Installing PHP dependencies"
composer install --no-interaction --prefer-dist

# Vite 8 needs Node >= 20.19. Asserted rather than assumed: if the feature
# ever resolves a different major, `npm run build` fails with a much less
# obvious message than this one.
NODE_MAJOR="$(node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0)"
[ "$NODE_MAJOR" -ge 20 ] || die "Node ${NODE_MAJOR} is too old; Vite 8 requires Node 20.19+. Check the node feature in devcontainer.json."

say "Installing Node dependencies"
info "node $(node --version), npm $(npm --version)"
# --ignore-scripts: no dependency lifecycle script needs to run here, and
# skipping them keeps a fresh container from executing arbitrary postinstall
# code out of node_modules.
npm install --ignore-scripts --no-audit --no-fund

# ---------------------------------------------------------------------------
# 5. Environment file
# ---------------------------------------------------------------------------
# .env is gitignored, so a freshly cloned container has none. It is created
# from .env.example, then only the keys this container owns are rewritten --
# every other line the developer has edited is left exactly as it was.
set_env() {
  local key="$1" value="$2"
  if grep -qE "^${key}=" .env; then
    # | as the delimiter so values containing / (URLs, base64) are safe.
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    printf '%s=%s\n' "$key" "$value" >> .env
  fi
}

say "Configuring .env"
if [ ! -f .env ]; then
  [ -f .env.example ] || die ".env.example is missing; cannot bootstrap .env."
  cp .env.example .env
  ENV_CREATED=1
  info "Created .env from .env.example."
else
  ENV_CREATED=0
  info ".env already exists; only container-owned keys are rewritten."
fi

# The database the app is pointed at is chosen three ways:
#   1. ACL_TIDB_* secrets present   -> TiDB Cloud over TLS (ADR-0007).
#   2. else a freshly created .env  -> the local MariaDB (zero-config default).
#   3. else (rebuild, .env survived)-> keep a valid local MariaDB config, or
#      repair stale remote values to the local fallback. A remote runtime DB
#      must be supplied through ACL_TIDB_* so its credentials are not silently
#      reused after a codespace is rebuilt.
if [ -n "${ACL_TIDB_HOST:-}" ] && [ -n "${ACL_TIDB_USERNAME:-}" ]; then
  say "Pointing the app at TiDB Cloud (ACL_TIDB_* present)"
  set_env DB_CONNECTION mysql
  set_env DB_HOST     "${ACL_TIDB_HOST}"
  set_env DB_PORT     "${ACL_TIDB_PORT:-4000}"
  set_env DB_DATABASE "${ACL_TIDB_DATABASE:-test}"
  set_env DB_USERNAME "${ACL_TIDB_USERNAME}"
  set_env DB_PASSWORD "${ACL_TIDB_PASSWORD}"
  # Laravel drops ?ssl-mode= from a DB_URL, so TLS to TiDB is turned on by the
  # CA bundle in config/database.php's mysql `options`, not by the DSN.
  set_env MYSQL_ATTR_SSL_CA /etc/ssl/certs/ca-certificates.crt
  DB_WRITTEN=1
elif [ "$ENV_CREATED" = "1" ] \
  || ! grep -qE "^DB_CONNECTION=mariadb$" .env \
  || ! grep -qE "^DB_HOST=${ACL_DB_HOST}$" .env \
  || ! grep -qE "^DB_PORT=${ACL_DB_PORT}$" .env \
  || ! grep -qE "^DB_DATABASE=${ACL_DB_NAME}$" .env \
  || ! grep -qE "^DB_USERNAME=${ACL_DB_USER}$" .env \
  || ! grep -qE "^DB_PASSWORD=${ACL_DB_PASSWORD}$" .env \
  || ! grep -qE '^DB_URL=$' .env 2>/dev/null; then
  if [ "$ENV_CREATED" != "1" ]; then
    warn "Existing DB_* values are not the local MariaDB and no ACL_TIDB_* secrets are present; switching to local MariaDB."
  fi
  say "Pointing the app at the local MariaDB"
  set_env DB_CONNECTION mariadb
  set_env DB_HOST     "${ACL_DB_HOST}"
  set_env DB_PORT     "${ACL_DB_PORT}"
  set_env DB_DATABASE "${ACL_DB_NAME}"
  set_env DB_USERNAME "${ACL_DB_USER}"
  set_env DB_PASSWORD "${ACL_DB_PASSWORD}"
  DB_WRITTEN=1
else
  info "Leaving existing DB_* untouched (no TiDB secrets, .env pre-existing)."
  DB_WRITTEN=0
fi

# A stale DB_URL silently wins over every discrete DB_* key, pointing the app at
# whatever host that URL names. Neutralise it only when we set DB_* ourselves.
if [ "$DB_WRITTEN" = "1" ] && grep -qE '^DB_URL=.+' .env; then
  warn "DB_URL was set and overrides DB_HOST/DB_DATABASE -- blanking it."
  set_env DB_URL ""
fi

# Redis and Mailpit run inside this container in every case, so these keys are
# always container-owned and re-asserted on each provision (ADR-0008). predis
# is pure PHP, so `composer install` alone gives the app a Redis client -- no
# compiled extension to match against the container's PHP build.
set_env REDIS_CLIENT     predis
set_env REDIS_HOST       "${ACL_REDIS_HOST}"
set_env REDIS_PORT       "${ACL_REDIS_PORT}"
set_env REDIS_PASSWORD   "${ACL_REDIS_PASSWORD}"
set_env REDIS_DB         "${ACL_REDIS_DB}"
set_env REDIS_CACHE_DB   "${ACL_REDIS_CACHE_DB}"
set_env SESSION_DRIVER   redis
set_env CACHE_STORE      redis
set_env QUEUE_CONNECTION redis
set_env MAIL_MAILER   smtp
set_env MAIL_HOST     127.0.0.1
set_env MAIL_PORT     "${ACL_MAIL_SMTP_PORT}"
set_env MAIL_USERNAME "${ACL_MAIL_USER}"
set_env MAIL_PASSWORD "${ACL_MAIL_PASSWORD}"
# null scheme: plaintext SMTP to loopback Mailpit, which offers no STARTTLS.
set_env MAIL_SCHEME   null

# Inside a codespace the browser reaches the app over a forwarded HTTPS
# hostname, not localhost. url()/asset() read APP_URL, so without this every
# generated link points somewhere unreachable.
if [ -n "${CODESPACE_NAME:-}" ] && [ -n "${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-}" ]; then
  set_env APP_URL "https://${CODESPACE_NAME}-${ACL_APP_PORT}.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
  info "APP_URL set to the forwarded codespace hostname."
fi

# Only generate a key when one is missing. `key:generate --force` on every
# rebuild would invalidate existing sessions and every encrypted value.
if grep -qE '^APP_KEY=base64:' .env; then
  info "APP_KEY already set; leaving it alone."
else
  say "Generating APP_KEY"
  php artisan key:generate --force
fi

# Any config/route cache baked before .env was rewritten would still hold the
# old values. Clearing is unconditional and cheap.
say "Clearing stale caches"
php artisan config:clear
php artisan route:clear
php artisan view:clear

# ---------------------------------------------------------------------------
# 6. Schema and seed data
# ---------------------------------------------------------------------------
say "Running migrations"
php artisan migrate --force

# Seed only when the users table is genuinely empty, so a rebuild does not
# duplicate rows. The count goes through the app's own connection (artisan),
# not the local mysql client, so it reads whichever database DB_* now points
# at -- the local MariaDB or TiDB Cloud -- rather than always the local one.
# Schema::hasTable guards the pre-migration case; tr keeps only the digits so a
# stray warning on stdout cannot corrupt the numeric compare below.
USER_COUNT="$(php artisan tinker --execute='echo \Schema::hasTable("users") ? \DB::table("users")->count() : 0;' 2>/dev/null | tr -cd '0-9')"
USER_COUNT="${USER_COUNT:-0}"

if [ "$USER_COUNT" = "0" ]; then
  say "Seeding development data"
  php artisan db:seed --force
else
  say "Seed data already present (${USER_COUNT} users) -- skipping db:seed"
fi

# ---------------------------------------------------------------------------
# 7. Frontend assets
# ---------------------------------------------------------------------------
# Blade calls @vite(...), which reads public/build/manifest.json. Without a
# build the very first page request throws a ViteManifestNotFoundException,
# so this step is what makes the app openable rather than optional.
say "Building frontend assets"
npm run build

# ---------------------------------------------------------------------------
# 8. Writable paths
# ---------------------------------------------------------------------------
say "Ensuring storage paths are writable"
mkdir -p \
  storage/framework/{cache/data,sessions,testing,views} \
  storage/logs \
  bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# ---------------------------------------------------------------------------
# 9. Verification
# ---------------------------------------------------------------------------
# The container reports its own health rather than claiming success. If any
# of this is wrong, it is far cheaper to see it here than after attaching.
say "Verifying the environment"
php artisan migrate:status || warn "migrate:status reported a problem."
php artisan about --only=environment,drivers 2>/dev/null || true

say "Setup complete"
cat <<SUMMARY
       Database   ${ACL_DB_NAME} (tests: ${ACL_DB_TEST_NAME}) as ${ACL_DB_USER} on ${ACL_DB_HOST}:${ACL_DB_PORT}
       Start app  php artisan serve --host=0.0.0.0 --port=${ACL_APP_PORT}
       Dev mode   composer run dev          (server + queue + logs + vite)
       Run tests  php artisan test
       Diagnose   bash scripts/troubleshoot.sh
       Dev logins docs/developer/DEV_ACCOUNTS.md
SUMMARY
