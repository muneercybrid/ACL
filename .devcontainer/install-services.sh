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

# When a devcontainer image fails to build, Codespaces does not hand you a
# half-built container to poke at -- it discards it and starts a recovery
# container from mcr.microsoft.com/devcontainers/base:alpine instead. The
# workspace is still mounted, so everything looks normal until a command needs
# the tooling: no php, no mariadb, no apt-get. Without this guard the first
# symptom is a bare "sudo: apt-get: command not found" from the line below,
# which says nothing about why. Nothing this script does can repair that
# container; only a successful rebuild can.
if ! command -v apt-get >/dev/null 2>&1; then
  PRETTY="unknown"
  if [ -r /etc/os-release ]; then
    PRETTY="$(. /etc/os-release; printf '%s' "${PRETTY_NAME:-unknown}")"
  fi
  warn "This container is ${PRETTY}, which has no apt-get."
  if [ "${CODESPACES_RECOVERY_CONTAINER:-}" = "true" ]; then
    warn "CODESPACES_RECOVERY_CONTAINER=true -- the devcontainer image failed to build,"
    warn "so this is Codespaces' fallback container and not this project's environment."
    warn "The build error is in /workspaces/.codespaces/.persistedshare/creation.log."
  fi
  die "Cannot install packages here. Fix the image build and rebuild the container."
fi

say "Installing MariaDB and Redis servers..."
# A container built from the Dockerfile has had this done already; it matters
# for one built straight from the base image, where the leftover unverifiable
# apt source makes the update below exit 100 and abort this script.
sudo bash "$(dirname "${BASH_SOURCE[0]}")/drop-unsigned-apt-sources.sh"
sudo apt-get update -qq
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
  mariadb-server \
  mariadb-client \
  redis-server \
  >/dev/null

# apt's redis-server postinst registers -- and may start -- a default,
# PASSWORD-LESS redis on :6379 via the SysV init script. ACL runs its own
# instance from an ACL-owned config WITH a password (start-services.sh), so
# stop and disable the distro one here: two servers cannot share the port, and
# an unauthenticated redis must never be the one that wins it. Every form is
# tried because the container may have systemd, SysV, or neither.
sudo service redis-server stop      >/dev/null 2>&1 || true
sudo systemctl disable redis-server >/dev/null 2>&1 || true
sudo systemctl mask    redis-server >/dev/null 2>&1 || true
sudo update-rc.d redis-server disable >/dev/null 2>&1 || true

# ---------------------------------------------------------------------------
# Mailpit
# ---------------------------------------------------------------------------
# Development mail catcher. Not in apt, so it is installed from its official
# release. The upstream installer detects the architecture and drops a single
# static binary in /usr/local/bin -- there is no service, no daemon and no
# config file to manage; start-services.sh launches it. Guarded by command -v
# so a rebuild that kept /usr/local/bin does not re-download it.
if ! command -v mailpit >/dev/null 2>&1; then
  say "Installing Mailpit..."
  if command -v curl >/dev/null 2>&1; then
    # INSTALL_PATH is honoured by the upstream script; sudo because it writes
    # to /usr/local/bin. A specific release can be pinned by downloading
    # mailpit-linux-amd64.tar.gz from a chosen tag instead of using this.
    curl -fsSL https://raw.githubusercontent.com/axllent/mailpit/develop/install.sh \
      | sudo bash >/dev/null 2>&1 \
      || warn "Mailpit install failed; dev mail capture will be unavailable until it is installed."
  else
    warn "curl not found; cannot install Mailpit. Dev mail capture will be unavailable."
  fi
else
  say "Mailpit already installed ($(mailpit version 2>/dev/null | head -n1))."
fi

# ---------------------------------------------------------------------------
# cloudflared (Cloudflare Tunnel)
# ---------------------------------------------------------------------------
# Publishes the local dev app through a Cloudflare Tunnel (ADR-0009). Not in
# apt, so -- like Mailpit above -- it is a single static binary fetched from
# the official release into /usr/local/bin. Guarded by command -v so a rebuild
# that kept /usr/local/bin does not re-download it. The tunnel is *started*
# (only when the credentials secret is present) by start-services.sh, never
# here; installing the binary is all this step does.
if ! command -v cloudflared >/dev/null 2>&1; then
  say "Installing cloudflared..."
  if command -v curl >/dev/null 2>&1; then
    arch="$(dpkg --print-architecture 2>/dev/null || echo amd64)"   # amd64 | arm64
    if curl -fsSL "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-${arch}" -o /tmp/cloudflared; then
      sudo install -m 0755 /tmp/cloudflared /usr/local/bin/cloudflared
      rm -f /tmp/cloudflared
    else
      warn "cloudflared download failed; Cloudflare Tunnel will be unavailable until it is installed."
    fi
  else
    warn "curl not found; cannot install cloudflared. Cloudflare Tunnel will be unavailable."
  fi
else
  say "cloudflared already installed ($(cloudflared --version 2>/dev/null | head -n1))."
fi

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
