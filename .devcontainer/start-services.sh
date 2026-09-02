#!/usr/bin/env bash
# Start MariaDB and wait until it actually accepts connections.
#
# Runs on EVERY container start (postStartCommand), not only on create, so
# the database is up whenever you attach -- including after a codespace has
# been stopped and resumed. Safe to run by hand at any time:
#
#     bash .devcontainer/start-services.sh
#
# Idempotent: starting an already-running server is a no-op.
set -uo pipefail

# shellcheck source=./config.sh
source "$(dirname "${BASH_SOURCE[0]}")/config.sh"

say() { printf '\033[36m[ACL]\033[0m %s\n' "$1"; }
die() { printf '\033[31m[ACL] %s\033[0m\n' "$1" >&2; exit 1; }

# `mysqladmin ping` succeeds only once the server is answering on the
# socket. This is the check the old script was missing: `service start`
# returns as soon as the init script has forked, several seconds before the
# server is ready, so the very next `mysql` or `php artisan migrate` call
# raced it and failed with "Connection refused".
db_ready() {
  mysqladmin --protocol=socket ping >/dev/null 2>&1 \
    || mysqladmin -h "$ACL_DB_HOST" -P "$ACL_DB_PORT" --protocol=tcp ping >/dev/null 2>&1
}

if db_ready; then
  say "MariaDB already running."
  exit 0
fi

if ! command -v mariadbd >/dev/null 2>&1 && ! command -v mysqld >/dev/null 2>&1; then
  die "MariaDB is not installed. Rebuild the container, or run: bash .devcontainer/install-services.sh"
fi

say "Starting MariaDB..."
# Codespaces containers have no systemd, so `service` runs the SysV init
# script directly. If that path is unavailable, fall back to launching the
# daemon ourselves.
sudo service mariadb start >/dev/null 2>&1 \
  || sudo service mysql start >/dev/null 2>&1 \
  || sudo -b mariadbd-safe --skip-syslog >/dev/null 2>&1 \
  || true

say "Waiting for MariaDB to accept connections..."
for _ in $(seq 1 60); do
  if db_ready; then
    say "MariaDB is up."
    exit 0
  fi
  sleep 1
done

printf '\n'
sudo tail -n 30 /var/log/mysql/error.log 2>/dev/null \
  || sudo tail -n 30 /var/log/mysql/*.err 2>/dev/null \
  || true
die "MariaDB did not become ready within 60s (log tail above)."
