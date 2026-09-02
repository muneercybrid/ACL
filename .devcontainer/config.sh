# ACL devcontainer configuration -- the single source of truth for the
# container's fixed values. Sourced by install-services.sh,
# start-services.sh and post-create.sh so the credentials are declared in
# exactly one place and can never drift between scripts.
#
# THESE CREDENTIALS ARE DEVELOPMENT-ONLY AND DELIBERATELY PUBLIC.
# The database they unlock exists only inside this throwaway container and
# is never reachable from the internet. Production credentials live in the
# deployment environment and never in this repository. See docs/adr/0005.
#
# shellcheck shell=bash

# --- Database -------------------------------------------------------------
# ADR-0005: MariaDB across all environments. acl_user is least-privilege --
# it is granted rights on the acl and acl_test schemas only, never root,
# never GRANT OPTION, never access to the mysql schema.
ACL_DB_HOST="${ACL_DB_HOST:-127.0.0.1}"
ACL_DB_PORT="${ACL_DB_PORT:-3306}"
ACL_DB_NAME="${ACL_DB_NAME:-acl}"
ACL_DB_TEST_NAME="${ACL_DB_TEST_NAME:-acl_test}"
ACL_DB_USER="${ACL_DB_USER:-acl_user}"
ACL_DB_PASSWORD="${ACL_DB_PASSWORD:-acl_password}"
ACL_DB_CHARSET="utf8mb4"
ACL_DB_COLLATION="utf8mb4_unicode_ci"

# --- Ports ----------------------------------------------------------------
ACL_APP_PORT="${ACL_APP_PORT:-8000}"
ACL_VITE_PORT="${ACL_VITE_PORT:-5173}"

# --- Required PHP extensions ---------------------------------------------
# Laravel 13's own requirements plus pdo_mysql for MariaDB. Verified against
# `php -m` by install-services.sh, which fails loudly rather than letting a
# missing extension surface later as a confusing runtime error.
ACL_REQUIRED_PHP_EXTS="ctype curl dom fileinfo filter hash mbstring openssl pcre pdo pdo_mysql session tokenizer xml"

# Not required by the framework, but relied on across a Laravel app's
# lifetime (money maths, archive handling, localisation, image work).
# Reported as warnings, not failures.
ACL_OPTIONAL_PHP_EXTS="bcmath intl zip gd exif sodium"

# --- Repository ----------------------------------------------------------
# Resolve the repo root from this file's location so the scripts work no
# matter which directory they are invoked from.
ACL_REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
