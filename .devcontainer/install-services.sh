#!/usr/bin/env bash
# Install the system packages the ACL container needs.
#
# Runs once, at container CREATE time (onCreateCommand), before
# post-create.sh. Split out from post-create so that the slow, cacheable
# apt work is separate from the fast, repeatable application setup -- and so
# a broken app setup can be re-run without reinstalling the OS packages.
#
# Idempotent: apt-get install on an already-installed package is a no-op.
set -euo pipefail

# shellcheck source=./config.sh
source "$(dirname "${BASH_SOURCE[0]}")/config.sh"

say()  { printf '\033[36m[ACL]\033[0m %s\n' "$1"; }
warn() { printf '\033[33m[ACL] %s\033[0m\n' "$1"; }
die()  { printf '\033[31m[ACL] %s\033[0m\n' "$1" >&2; exit 1; }

say "Installing MariaDB server and client..."
sudo apt-get update -qq
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
  mariadb-server \
  mariadb-client \
  >/dev/null

say "Verifying required PHP extensions..."

# pdo_mysql is normally baked into the image by the Dockerfile. This is the
# self-healing path for a container built straight from the base image, where
# PHP is compiled without --with-pdo-mysql and nothing can reach MariaDB.
if ! php -m | grep -qix pdo_mysql; then
  warn "pdo_mysql is not compiled into this PHP build; building it now."
  # `env "PATH=$PATH"` rather than plain sudo: sudo's secure_path drops
  # /usr/local/php/<version>/bin, and the script needs php and phpize.
  sudo env "PATH=$PATH" "$(dirname "${BASH_SOURCE[0]}")/build-pdo-mysql.sh"
fi

MISSING=""
for ext in $ACL_REQUIRED_PHP_EXTS; do
  php -m | grep -qix "$ext" || MISSING="${MISSING} ${ext}"
done

# Never pretend to succeed: a missing pdo_mysql otherwise surfaces much later
# as "could not find driver" from the middle of a migration.
if [ -n "$MISSING" ]; then
  warn "Missing PHP extensions:${MISSING}"
  if command -v install-php-extensions >/dev/null 2>&1; then
    say "Attempting to install them..."
    # shellcheck disable=SC2086
    sudo install-php-extensions $MISSING || true
  fi
  STILL_MISSING=""
  for ext in $MISSING; do
    php -m | grep -qix "$ext" || STILL_MISSING="${STILL_MISSING} ${ext}"
  done
  [ -z "$STILL_MISSING" ] || die "PHP extensions still missing:${STILL_MISSING}. Install them and re-run this script."
  say "Extensions installed."
else
  say "All required PHP extensions present."
fi

info_drivers="$(php -r 'echo implode(", ", PDO::getAvailableDrivers());')"
say "PDO drivers: ${info_drivers}"

for ext in $ACL_OPTIONAL_PHP_EXTS; do
  php -m | grep -qix "$ext" || warn "Optional PHP extension not present: ${ext}"
done

say "System packages ready."
