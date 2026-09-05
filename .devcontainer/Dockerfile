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

# Must come before the first apt-get update: the base image carries an apt
# source it can no longer verify, which makes apt-get update exit 100 and, under
# set -e, fails this build. See drop-unsigned-apt-sources.sh.
COPY drop-unsigned-apt-sources.sh /usr/local/bin/acl-drop-unsigned-apt-sources
RUN chmod +x /usr/local/bin/acl-drop-unsigned-apt-sources \
    && /usr/local/bin/acl-drop-unsigned-apt-sources

# Build dependencies for the phpize build. Removed from the layer afterwards
# is not worth it here: this is a development image, not a production one,
# and the compilers are useful for future PECL extensions.
#
# openssh-server, and the package index left in place rather than deleted, are
# both for Codespaces' own bootstrap: it installs and starts an SSH server
# inside this container the first time anything connects to it, and on an image
# with no apt lists and no openssh-server that attempt fails with
# "failed to start SSH server", which locks you out of the container entirely.
# A few megabytes of package index is a cheap price for being able to get in.
RUN set -eux; \
    apt-get update; \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        build-essential \
        autoconf \
        pkg-config \
        default-libmysqlclient-dev \
        ca-certificates \
        curl \
        openssh-server

COPY build-pdo-mysql.sh /usr/local/bin/acl-build-pdo-mysql
RUN chmod +x /usr/local/bin/acl-build-pdo-mysql && /usr/local/bin/acl-build-pdo-mysql

# Fail the build rather than the developer's first migration.
RUN php -m | grep -qix pdo_mysql \
    && php -r 'exit(in_array("mysql", PDO::getAvailableDrivers(), true) ? 0 : 1);'

# Deliberately no `USER vscode` here. devcontainer.json's remoteUser already
# makes every terminal, task and lifecycle command run as vscode, while the
# container itself keeps starting as root -- which Codespaces' agent needs in
# order to install its server and sshd into a running container. An image that
# ends as vscode looks tidier and costs you SSH access to the codespace.
