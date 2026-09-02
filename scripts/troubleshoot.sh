#!/usr/bin/env bash
# ACL environment diagnostics.
#
# Reports every problem it finds rather than stopping at the first one, so
# deliberately no `set -e`. Exits non-zero if any check failed.

cd "$(dirname "$0")/.." || exit 1

FAILURES=0

pass() { printf '  \033[32mOK\033[0m    %s\n' "$1"; }
warn() { printf '  \033[33mWARN\033[0m  %s\n' "$1"; }
fail() { printf '  \033[31mFAIL\033[0m  %s\n' "$1"; FAILURES=$((FAILURES + 1)); }
section() { printf '\n\033[1m%s\033[0m\n' "$1"; }

section "Toolchain"

if command -v php >/dev/null 2>&1; then
  PHP_VERSION="$(php -r 'echo PHP_VERSION;')"
  if php -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);'; then
    pass "PHP ${PHP_VERSION}"
  else
    fail "PHP ${PHP_VERSION} -- composer.json requires ^8.4.1"
  fi
else
  fail "php not found on PATH"
fi

if command -v composer >/dev/null 2>&1; then
  pass "composer $(composer --version --no-ansi 2>/dev/null | awk '{print $3}')"
else
  fail "composer not found on PATH"
fi

if command -v node >/dev/null 2>&1; then
  pass "node $(node --version)"
else
  fail "node not found on PATH"
fi

for ext in pdo_mysql mbstring openssl; do
  if php -m 2>/dev/null | grep -qi "^${ext}$"; then
    pass "php extension: ${ext}"
  else
    fail "php extension missing: ${ext}"
  fi
done

section "Dependencies"

[ -d vendor ]       && pass "vendor/ present"       || fail "vendor/ missing -- run: composer install"
[ -d node_modules ] && pass "node_modules/ present" || fail "node_modules/ missing -- run: npm install"
[ -d public/build ] && pass "public/build present"  || warn "public/build missing -- run: npm run build (or npm run dev)"

section "Configuration"

if [ -f .env ]; then
  pass ".env present"

  if grep -qE '^APP_KEY=base64:' .env; then
    pass "APP_KEY is set"
  else
    fail "APP_KEY is empty -- run: php artisan key:generate"
  fi

  DB_CONNECTION="$(grep -E '^DB_CONNECTION=' .env | head -1 | cut -d= -f2-)"
  DB_DATABASE="$(grep -E '^DB_DATABASE=' .env | head -1 | cut -d= -f2-)"
  if [ -n "$DB_CONNECTION" ]; then
    pass "DB_CONNECTION=${DB_CONNECTION}, DB_DATABASE=${DB_DATABASE}"
  else
    fail "DB_CONNECTION is not set in .env"
  fi

  # Laravel reads DB_URL. DATABASE_URL is a common mistake and is ignored
  # entirely, leaving the app silently pointed at the DB_CONNECTION default.
  if grep -qE '^DATABASE_URL=' .env; then
    fail "DATABASE_URL is set but Laravel only reads DB_URL -- rename it"
  fi
else
  fail ".env missing -- run: cp .env.example .env && php artisan key:generate"
fi

[ -f .env.example ] && pass ".env.example present" || fail ".env.example missing"

section "Database"

if [ -d vendor ] && [ -f .env ]; then
  if php artisan db:show --json >/dev/null 2>&1; then
    pass "database connection succeeded"
    PENDING="$(php artisan migrate:status --pending 2>/dev/null | grep -c 'Pending')"
    if [ "${PENDING:-0}" -gt 0 ]; then
      warn "${PENDING} pending migration(s) -- run: php artisan migrate"
    else
      pass "all migrations applied"
    fi
  else
    fail "cannot connect to the database"
    printf '        %s\n' "Is MariaDB running?  sudo service mariadb start"
    printf '        %s\n' "Check credentials in .env against .devcontainer/post-create.sh"
    printf '\n'
    php artisan db:show 2>&1 | sed 's/^/        /' | head -20
  fi
else
  warn "skipped -- needs vendor/ and .env"
fi

section "Result"

if [ "$FAILURES" -eq 0 ]; then
  printf '  \033[32mNo failures.\033[0m Start the app with: php artisan serve\n\n'
  exit 0
fi

printf '  \033[31m%s check(s) failed.\033[0m See docs/developer/README.md for setup.\n\n' "$FAILURES"
exit 1
