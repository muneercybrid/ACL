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
if ! php -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);' 2>/dev/null; then
  warn "pdo_mysql is not compiled into this PHP build; building it now."
  # `env "PATH=$PATH"` rather than plain sudo: sudo's secure_path drops
  # /usr/local/php/<version>/bin, and the script needs php and phpize.
  sudo env "PATH=$PATH" "$(dirname "${BASH_SOURCE[0]}")/build-pdo-mysql.sh"
fi

has_ext() { php -r 'exit(extension_loaded($argv[1]) ? 0 : 1);' "$1" 2>/dev/null; }

MISSING=""
for ext in $ACL_REQUIRED_PHP_EXTS; do
  has_ext "$ext" || MISSING="${MISSING} ${ext}"
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
    has_ext "$ext" || STILL_MISSING="${STILL_MISSING} ${ext}"
  done
  [ -z "$STILL_MISSING" ] || die "PHP extensions still missing:${STILL_MISSING}. Install them and re-run this script."
  say "Extensions installed."
else
  say "All required PHP extensions present."
fi

info_drivers="$(php -r 'echo implode(", ", PDO::getAvailableDrivers());')"
say "PDO drivers: ${info_drivers}"

for ext in $ACL_OPTIONAL_PHP_EXTS; do
  has_ext "$ext" || warn "Optional PHP extension not present: ${ext}"
done

# ---------------------------------------------------------------------------
# Xdebug
# ---------------------------------------------------------------------------
# The base image enables Xdebug with mode=debug and start_with_request=yes, so
# every single PHP invocation tries to reach a debug client on localhost:9003
# and prints "Could not connect to debugging client" when nothing is listening
# -- which, on a CLI-driven project, is most of the time. `trigger` keeps the
# extension loaded and the debugger one variable away (XDEBUG_TRIGGER=1, or the
# VS Code launch config, which sets it for you) without the noise on every
# artisan and composer command.
#
# Written as a separate file rather than by editing the image's xdebug.ini:
# PHP reads conf.d in alphabetical order and later files win, so the zz- prefix
# is what makes this override rather than get overridden.
#
# The directory comes from PHP's own compiled-in constant, not from parsing
# `php -i`. `php -i | awk '/Scan this dir/ {print; exit}'` looks equivalent and
# is not: `php -i` writes ~80 KB, more than a pipe buffer holds, so awk's early
# exit closes the pipe mid-write, php dies of SIGPIPE, and `set -o pipefail`
# aborts this whole script here without a word. (`php -m | grep -q` above is
# the same shape but safe -- its output fits in the buffer.)
say "Quieting Xdebug's step debugger..."
XDEBUG_SCAN_DIR="$(php -r 'echo PHP_CONFIG_FILE_SCAN_DIR;' 2>/dev/null || true)"
if [ -n "$XDEBUG_SCAN_DIR" ] && [ -d "$XDEBUG_SCAN_DIR" ]; then
  printf '%s\n' \
    '; Written by .devcontainer/install-services.sh -- see the comment there.' \
    'xdebug.start_with_request = trigger' \
    | sudo tee "${XDEBUG_SCAN_DIR}/zz-acl-xdebug.ini" >/dev/null
  say "Xdebug starts on trigger only; set XDEBUG_TRIGGER=1 to debug a command."
else
  warn "Could not find PHP's conf.d directory; leaving Xdebug as the image set it."
fi

say "System packages ready."
