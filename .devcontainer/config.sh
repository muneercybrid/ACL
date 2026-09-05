# ACL devcontainer configuration -- the single source of truth for the
# container's fixed values. Sourced by install-services.sh,
# start-services.sh and post-create.sh so the credentials are declared in
# exactly one place and can never drift between scripts.
#
# THESE CREDENTIALS ARE DEVELOPMENT-ONLY AND DELIBERATELY PUBLIC.
# The services they unlock (MariaDB, Redis, Mailpit) exist only inside this
# throwaway container and are never reachable from the internet. Production
# credentials live in the deployment environment and never in this repository.
# See docs/adr/0005 (MariaDB), 0007 (TiDB for dev) and 0008 (Redis).
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

# --- Development runtime database: TiDB Cloud (ADR-0007) ------------------
# Optional and secret. When ACL_TIDB_* are present in the environment -- set
# as GitHub Codespaces secrets, never committed -- post-create.sh points the
# application at TiDB Cloud over TLS instead of the local MariaDB above. The
# local MariaDB above always remains: the test suite runs on it (phpunit pins
# acl_test to it) and it is the zero-config fallback when TiDB is not supplied.
#
# No defaults are set here on purpose. Unlike the loopback MariaDB credentials,
# these reach an internet-facing database and are genuine secrets. The contract
# is the five variable names below; the values live only in Codespaces secrets:
#   ACL_TIDB_HOST  ACL_TIDB_PORT  ACL_TIDB_DATABASE  ACL_TIDB_USERNAME  ACL_TIDB_PASSWORD

# --- Ports ----------------------------------------------------------------
ACL_APP_PORT="${ACL_APP_PORT:-8000}"
ACL_VITE_PORT="${ACL_VITE_PORT:-5173}"
ACL_MAIL_UI_PORT="${ACL_MAIL_UI_PORT:-8025}"

# --- Redis (ADR-0008) -----------------------------------------------------
# Backs sessions, cache and the queue in development. Runs inside the container
# bound to loopback only, so -- exactly like the MariaDB credentials above --
# this password is development-only and deliberately public: the server it
# unlocks is never reachable from the internet. Production supplies its own.
ACL_REDIS_HOST="${ACL_REDIS_HOST:-127.0.0.1}"
ACL_REDIS_PORT="${ACL_REDIS_PORT:-6379}"
ACL_REDIS_PASSWORD="${ACL_REDIS_PASSWORD:-acl_redis_password}"
ACL_REDIS_MAXMEMORY="${ACL_REDIS_MAXMEMORY:-256mb}"
# noeviction, NOT an LRU policy: db0 holds sessions, and silently evicting a
# session key would log a student out mid-request. Cache lives in its own db
# (ACL_REDIS_CACHE_DB) so cache growth cannot pressure sessions in the first
# place; if the 256mb ceiling is ever hit, a loud write error is the correct
# outcome for a dev box, not a mystery logout.
ACL_REDIS_MAXMEMORY_POLICY="${ACL_REDIS_MAXMEMORY_POLICY:-noeviction}"
# db0: sessions + queue. db1: cache. Kept apart so `cache:clear` (a FLUSHDB on
# db1) can never evict a live session sitting in db0.
ACL_REDIS_DB="${ACL_REDIS_DB:-0}"
ACL_REDIS_CACHE_DB="${ACL_REDIS_CACHE_DB:-1}"

# --- Mailpit --------------------------------------------------------------
# Catches every outbound mail in development so nothing is ever delivered to a
# real address from a codespace. Real SMTP on ACL_MAIL_SMTP_PORT, web inbox on
# ACL_MAIL_UI_PORT (forwarded). AUTH is enabled -- mirroring a real SMTP relay
# so the dev mail path exercises the same code prod will -- with these
# loopback-only, development-only credentials.
ACL_MAIL_SMTP_PORT="${ACL_MAIL_SMTP_PORT:-1025}"
ACL_MAIL_USER="${ACL_MAIL_USER:-acl}"
ACL_MAIL_PASSWORD="${ACL_MAIL_PASSWORD:-acl_mail_password}"

# --- Cloudflare Tunnel: public serving (ADR-0009) -------------------------
# Publishes the local app at a public hostname across codespace stop/resume
# and rebuild. Uses a LOCALLY-MANAGED tunnel: `cloudflared tunnel login`
# authenticates against the ordinary Cloudflare dashboard, so -- unlike a
# remotely-managed (token) tunnel -- it needs no Cloudflare Zero Trust
# onboarding and therefore no payment method on the free plan.
#
# The tunnel's credentials JSON is a genuine SECRET: it authorises running the
# tunnel. Exactly like the ACL_TIDB_* block above, no default is set here --
# the contract is the variable name, and the value lives ONLY in a GitHub
# Codespaces secret, base64-encoded, never committed:
#   CLOUDFLARE_TUNNEL_CREDENTIALS_B64   # base64 of ~/.cloudflared/<UUID>.json
#
# The public hostname and the internal metrics port ARE fixed values, so they
# live here. The metrics port is loopback-only (cloudflared's /ready probe),
# not an app port, so it is deliberately absent from forwardPorts.
ACL_TUNNEL_HOSTNAME="${ACL_TUNNEL_HOSTNAME:-app.aclacademy.me}"
ACL_TUNNEL_METRICS_PORT="${ACL_TUNNEL_METRICS_PORT:-60123}"

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
