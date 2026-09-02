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
MISSING=""
for ext in $ACL_REQUIRED_PHP_EXTS; do
  php -m | grep -qix "$ext" || MISSING="${MISSING} ${ext}"
done

# install-php-extensions ships in the devcontainers PHP image and resolves
# each extension's system libraries for us. Attempt a repair before giving
# up, but never pretend to succeed: a missing pdo_mysql would otherwise
# surface much later as an inscrutable "could not find driver" error.
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

for ext in $ACL_OPTIONAL_PHP_EXTS; do
  php -m | grep -qix "$ext" || warn "Optional PHP extension not present: ${ext}"
done

say "System packages ready."
