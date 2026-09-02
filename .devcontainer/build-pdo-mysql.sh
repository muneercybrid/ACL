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
phpize >/dev/null

# mysqlnd is PHP's bundled native driver and needs no system client library;
# prefer it. If a standalone phpize build cannot see the bundled mysqlnd
# headers, fall back to the libmysqlclient headers the image already has.
if ! ./configure --with-php-config="$(command -v php-config)" --with-pdo-mysql=mysqlnd >/dev/null 2>&1; then
  say "mysqlnd build not viable here; configuring against libmysqlclient..."
  ./configure --with-php-config="$(command -v php-config)" --with-pdo-mysql >/dev/null \
    || die "configure failed for pdo_mysql."
fi

make -j"$(nproc)" >/dev/null
make install >/dev/null

# Enable it via the scan directory rather than editing php.ini, so the change
# is additive and survives an image-level php.ini replacement.
SCAN_DIR="$(php -i | awk -F'=> ' '/^Scan this dir for additional .ini files/ {print $2; exit}')"
[ -n "$SCAN_DIR" ] || die "Could not determine PHP's conf.d scan directory."
mkdir -p "$SCAN_DIR"
printf 'extension=pdo_mysql.so\n' > "${SCAN_DIR}/pdo_mysql.ini"

php -m | grep -qix pdo_mysql || die "pdo_mysql built but did not load; check ${SCAN_DIR}/pdo_mysql.ini"
php -r 'in_array("mysql", PDO::getAvailableDrivers(), true) || exit(1);' \
  || die "pdo_mysql loaded but PDO does not report the mysql driver."

say "pdo_mysql built and enabled. PDO drivers: $(php -r 'echo implode(", ", PDO::getAvailableDrivers());')"
