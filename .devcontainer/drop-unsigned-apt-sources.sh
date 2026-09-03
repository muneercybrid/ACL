#!/usr/bin/env bash
# Remove the apt sources this image cannot verify, so apt-get update can exit 0.
#
# WHY THIS EXISTS
# ---------------
# mcr.microsoft.com/devcontainers/php:1-8.4-bookworm preconfigures Yarn's apt
# repository, and the key that signs it is no longer in the image's keyring.
# Every apt-get update therefore ends like this:
#
#   Err:4 https://dl.yarnpkg.com/debian stable InRelease
#     The following signatures couldn't be verified because the public key is
#     not available: NO_PUBKEY 62D54FD4003F6525
#   E: The repository 'https://dl.yarnpkg.com/debian stable InRelease' is not
#      signed.
#
# and exits 100. Debian's own repositories fetch perfectly; the failure is
# entirely this one extra source. Under `set -e` that took down the whole image
# build -- and a devcontainer build that fails does not leave a half-built dev
# container to poke at. Codespaces replaces it with a bare Alpine recovery
# container: no PHP, no Node, no MariaDB, and a PATH short enough to notice.
#
# Removing the source is the correct fix here, not the expedient one. Nothing
# in this project comes from it -- the frontend is npm, and every package the
# Dockerfile and install-services.sh install lives in Debian main -- so the
# alternatives (importing a key fetched over the network at build time, or
# reaching for --allow-unauthenticated / Acquire::AllowInsecureRepositories)
# would give up signature verification on all packages in exchange for
# packages we never install.
#
# Called as root, before apt-get update, from both the Dockerfile and
# install-services.sh. Idempotent: a second run finds nothing to do.
set -euo pipefail

say() { printf '\033[36m[ACL]\033[0m %s\n' "$1"; }

# Hosts whose apt sources are unusable in this image. Space-separated; the
# values are matched as extended regexes, hence the escaped dots.
UNSIGNED_APT_HOSTS='dl\.yarnpkg\.com'

removed=0

for host in $UNSIGNED_APT_HOSTS; do
  # sources.list.d holds one third-party repository per file -- never Debian's
  # own -- so a matching file can be deleted outright. Covers both the one-line
  # .list format and deb822 .sources.
  for f in /etc/apt/sources.list.d/*; do
    [ -f "$f" ] || continue
    if grep -qE "$host" "$f" 2>/dev/null; then
      rm -f "$f"
      say "Removed unverifiable apt source: ${f}"
      removed=1
    fi
  done

  # /etc/apt/sources.list is shared with Debian's own repositories, so comment
  # the offending lines out rather than deleting the file.
  if [ -f /etc/apt/sources.list ] && grep -qE "^[^#]*${host}" /etc/apt/sources.list; then
    sed -i -E "/${host}/ s|^|# disabled by ACL, unverifiable signing key: |" /etc/apt/sources.list
    say "Commented out unverifiable apt source in /etc/apt/sources.list"
    removed=1
  fi
done

[ "$removed" -eq 1 ] || say "No unverifiable apt sources present."
