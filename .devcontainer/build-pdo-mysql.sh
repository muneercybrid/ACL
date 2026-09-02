#!/usr/bin/env bash
# Build and enable the pdo_mysql PHP extension.
#
# WHY THIS EXISTS
# ---------------
# mcr.microsoft.com/devcontainers/php:1-8.4-bookworm compiles PHP from source
# and its configure line does not include --with-pdo-mysql:
#
#   php -i | grep 'Configure Command'
#     './configure' '--prefix=/usr/local/php/8.4.15' ... '--with-curl'
#     '--with-libedit' '--enable-mbstring' '--with-openssl' '--with-zlib'
#     '--with-password-argon2' '--with-sodium=shared' '--with-pear'
#
# so PDO::getAvailableDrivers() returns only ["sqlite"], and every database
# call fails with "could not find driver". Unlike the official php:8.4
# images, this one also ships no docker-php-ext-install / install-php-extensions
# helper, and pdo_mysql is a bundled extension rather than a PECL package,
# so `pecl install` cannot supply it either. The only route is to compile it
# against this exact PHP build with phpize.
#
# Called from two places, so there is exactly one implementation:
#   - the Dockerfile, at image build time (result is cached; rebuilds are fast)
#   - install-services.sh, but only if pdo_mysql is still missing (self-heals
#     a container built from the plain image)
set -euo pipefail

say() { printf '\033[36m[ACL]\033[0m %s\n' "$1"; }
die() { printf '\033[31m[ACL] %s\033[0m\n' "$1" >&2; exit 1; }

if php -m 2>/dev/null | grep -qix pdo_mysql; then
  say "pdo_mysql already present."
  exit 0
fi

command -v phpize >/dev/null 2>&1 || die "phpize not found; cannot build pdo_mysql."

PHP_VERSION="$(php -r 'echo PHP_VERSION;')"
SRC_DIR="/usr/src/php-${PHP_VERSION}"
TARBALL="/tmp/php-${PHP_VERSION}.tar.gz"

say "Building pdo_mysql for PHP ${PHP_VERSION}..."

# The extension must be built from the source tree of the SAME PHP release,
# otherwise the resulting .so is rejected at load time as an API mismatch.
if [ ! -d "${SRC_DIR}/ext/pdo_mysql" ]; then
  say "Fetching PHP ${PHP_VERSION} source..."
  curl -fsSL -o "$TARBALL" "https://www.php.net/distributions/php-${PHP_VERSION}.tar.gz" \
    || die "Could not download PHP ${PHP_VERSION} source from php.net."
  mkdir -p "$SRC_DIR"
  tar -xzf "$TARBALL" -C "$SRC_DIR" --strip-components=1
  rm -f "$TARBALL"
fi

cd "${SRC_DIR}/ext/pdo_mysql"

PHP_CONFIG="$(command -v php-config)"

# Two ways to build pdo_mysql, and only one of them works on this image:
#
#   mysqlnd  -- PHP's bundled native driver, and what `--with-pdo-mysql` means
#               when given no value. `configure` accepts it happily, then the
#               compile dies with "ext/mysqlnd/mysqlnd.h: No such file or
#               directory": this image's installed header tree carries no
#               mysqlnd headers, and PHP here was not built with mysqlnd at
#               all, so even a linked build would fail to load. Verified by
#               running it: configure succeeds, make fails.
#   libmysqlclient -- links against the system MySQL/MariaDB client library
#               that default-libmysqlclient-dev provides. Self-contained, and
#               the path that actually succeeds here.
#
# So libmysqlclient is tried first and mysqlnd is only a fallback for some
# future image that does ship the headers. Because the mysqlnd failure happens
# at `make` and not at `configure`, each attempt has to be judged on whether
# the compile finished -- not on whether configure exited 0.
if ! command -v mysql_config >/dev/null 2>&1 && ! command -v mariadb_config >/dev/null 2>&1; then
  say "No mysql_config found; installing MySQL client development headers..."
  apt-get update -qq >/dev/null 2>&1 || true
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --no-install-recommends \
    default-libmysqlclient-dev >/dev/null 2>&1 \
    || say "Could not install default-libmysqlclient-dev; continuing anyway."
fi

CANDIDATES="/usr"
if [ -f "$($PHP_CONFIG --include-dir)/ext/mysqlnd/mysqlnd.h" ] && php -m | grep -qix mysqlnd; then
  CANDIDATES="${CANDIDATES} mysqlnd"
fi

BUILT=0
for candidate in $CANDIDATES; do
  say "Trying --with-pdo-mysql=${candidate}..."
  # Regenerate the build system for every attempt. A Makefile / config.cache
  # left behind by a failed variant otherwise survives into the next one and
  # makes it fail for the wrong reason.
  phpize --clean >/dev/null 2>&1 || true
  phpize >/dev/null 2>&1 || die "phpize failed in ${SRC_DIR}/ext/pdo_mysql."
  if [ "$candidate" = "mysqlnd" ]; then
    CONFIGURE_ARG="--with-pdo-mysql=mysqlnd"
  else
    CONFIGURE_ARG="--with-pdo-mysql=${candidate}"
  fi
  # shellcheck disable=SC2086
  if ./configure --with-php-config="$PHP_CONFIG" $CONFIGURE_ARG >/tmp/acl-pdo-configure.log 2>&1 \
     && make -j"$(nproc)" >/tmp/acl-pdo-make.log 2>&1; then
    BUILT=1
    say "Built with --with-pdo-mysql=${candidate}."
    break
  fi
  say "That variant failed; trying the next one."
done

if [ "$BUILT" -ne 1 ]; then
  printf '\n--- configure log (tail) ---\n'; tail -n 20 /tmp/acl-pdo-configure.log 2>/dev/null || true
  printf '\n--- make log (tail) ---\n';      tail -n 30 /tmp/acl-pdo-make.log 2>/dev/null || true
  die "Could not build pdo_mysql with any known configuration (logs above)."
fi

make install >/dev/null

# Enable it via the scan directory rather than editing php.ini, so the change
# is additive and survives an image-level php.ini replacement.
SCAN_DIR="$(php -i | awk -F'=> ' '/^Scan this dir for additional .ini files/ {print $2; exit}')"
if [ -z "$SCAN_DIR" ] || [ "$SCAN_DIR" = "(none)" ]; then
  # A PHP build with no conf.d at all: append to the loaded php.ini instead.
  PHP_INI="$(php -r 'echo php_ini_loaded_file() ?: "";')"
  [ -n "$PHP_INI" ] || die "PHP has neither a conf.d scan directory nor a loaded php.ini; cannot enable pdo_mysql."
  grep -q '^extension=pdo_mysql.so' "$PHP_INI" || printf '\nextension=pdo_mysql.so\n' >> "$PHP_INI"
  ENABLED_VIA="$PHP_INI"
else
  mkdir -p "$SCAN_DIR"
  printf 'extension=pdo_mysql.so\n' > "${SCAN_DIR}/pdo_mysql.ini"
  ENABLED_VIA="${SCAN_DIR}/pdo_mysql.ini"
fi

php -m | grep -qix pdo_mysql || die "pdo_mysql built but did not load; check ${ENABLED_VIA}"
php -r 'in_array("mysql", PDO::getAvailableDrivers(), true) || exit(1);' \
  || die "pdo_mysql loaded but PDO does not report the mysql driver."

say "pdo_mysql built and enabled. PDO drivers: $(php -r 'echo implode(", ", PDO::getAvailableDrivers());')"
