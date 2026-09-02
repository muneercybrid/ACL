# ACL development image.
#
# Starts from the Microsoft devcontainers PHP image and adds the one thing it
# is missing for this project: the pdo_mysql driver. That image compiles PHP
# from source without --with-pdo-mysql, so MariaDB is unreachable until the
# extension is built against that exact PHP build. Doing it here rather than
# in postCreateCommand means the compile happens once, is cached in the image
# layer, and every subsequent container rebuild is fast.
#
# See .devcontainer/build-pdo-mysql.sh for the full explanation.

FROM mcr.microsoft.com/devcontainers/php:1-8.4-bookworm

USER root

# Build dependencies for the phpize build. Removed from the layer afterwards
# is not worth it here: this is a development image, not a production one,
# and the compilers are useful for future PECL extensions.
RUN set -eux; \
    apt-get update; \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        build-essential \
        autoconf \
        pkg-config \
        default-libmysqlclient-dev \
        ca-certificates \
        curl; \
    rm -rf /var/lib/apt/lists/*

COPY build-pdo-mysql.sh /usr/local/bin/acl-build-pdo-mysql
RUN chmod +x /usr/local/bin/acl-build-pdo-mysql && /usr/local/bin/acl-build-pdo-mysql

# Fail the build rather than the developer's first migration.
RUN php -m | grep -qix pdo_mysql \
    && php -r 'exit(in_array("mysql", PDO::getAvailableDrivers(), true) ? 0 : 1);'

USER vscode
