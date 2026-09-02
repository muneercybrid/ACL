#!/usr/bin/env bash
set -euo pipefail

DB_NAME="acl"
DB_USER="acl_user"
DB_PASS="acl_password"

# Replace KEY=... in .env if present, otherwise append it. The previous
# version of this script used bare `sed -i` calls that were silent no-ops:
# .env already existed (so the `cp` below was skipped) and contained no
# DB_* lines for the patterns to match.
set_env() {
  local key="$1" value="$2"
  if grep -qE "^${key}=" .env; then
    # Use | as the delimiter so values containing / are safe.
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    printf '%s=%s\n' "$key" "$value" >> .env
  fi
}

echo "[ACL] Installing MariaDB..."
sudo apt-get update
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y mariadb-server

echo "[ACL] Starting MariaDB..."
sudo service mariadb start

echo "[ACL] Creating database and local user..."
sudo mysql <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS ${DB_NAME}_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON ${DB_NAME}_test.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON ${DB_NAME}_test.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "[ACL] Installing PHP dependencies..."
composer install --no-interaction

echo "[ACL] Installing Node dependencies..."
npm install --ignore-scripts

if [ ! -f ".env" ]; then
  cp .env.example .env
fi

echo "[ACL] Applying database settings to .env..."
set_env DB_CONNECTION mariadb
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "${DB_NAME}"
set_env DB_USERNAME "${DB_USER}"
set_env DB_PASSWORD "${DB_PASS}"

echo "[ACL] Preparing application..."
# Only generate a key when one is missing. `key:generate --force` on every
# rebuild would invalidate all existing sessions and encrypted values.
if ! grep -qE '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

php artisan migrate --force

# `|| echo 0` matters: on a fresh database the users table does not exist,
# mysql exits non-zero, and `set -e` would abort the script before seeding.
SEED_COUNT="$(mysql -u"${DB_USER}" -p"${DB_PASS}" -h127.0.0.1 -Nse \
  "SELECT COUNT(*) FROM ${DB_NAME}.users WHERE email='admin@acl.local';" 2>/dev/null || echo 0)"
if [ "$SEED_COUNT" = "0" ]; then
  php artisan db:seed --force
fi

echo "[ACL] Building frontend assets..."
npm run build

php artisan migrate:status

echo "[ACL] Post-create setup completed."
echo "[ACL] Run 'php artisan serve' to start, or 'bash scripts/troubleshoot.sh' to diagnose."
