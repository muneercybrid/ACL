# ACL developer setup

ACL is developed inside a container. PHP, MariaDB, Node, both schemas, the seed
data and the built frontend assets are all provisioned for you by
[`.devcontainer/`](../../.devcontainer/). There are no manual setup steps.

The **source of truth is the `.devcontainer/` directory in this repository**, at
whatever path the workspace is checked out to — `/workspaces/ACL` in a Codespace.
A rebuild reads those files from the workspace on disk, not from a fresh clone, so
`git pull` **before** you rebuild or the rebuild replays the old scripts.

## What the container gives you

| | |
|---|---|
| PHP | 8.4 (`composer.json` requires `^8.4.1`) |
| Node | 24, from the devcontainer `node` feature — Vite 8 needs ≥ 20.19 |
| MariaDB | 10.11, on `127.0.0.1:3306` |
| Databases | `acl` for development, `acl_test` for the test suite |
| Database user | `acl_user` / `acl_password`, granted on those two schemas only |
| App | port `8000`, forwarded |
| Vite | port `5173`, forwarded |

Those credentials are fixed and deliberately public — they unlock a database that
exists only inside the container and is never reachable from the internet. They
are declared in [`devcontainer.json`](../../.devcontainer/devcontainer.json)'s
`containerEnv` (which wins, being real environment variables) and repeated as
`${VAR:-default}` fallbacks in [`config.sh`](../../.devcontainer/config.sh) so the
scripts also work when run by hand. Change one, change both.

## The container lifecycle

Four things run, in this order. All are idempotent — re-running any of them on an
existing container changes only what needs changing.

| Hook | Script | Runs | Does |
|---|---|---|---|
| image build | [`Dockerfile`](../../.devcontainer/Dockerfile) | once per build | Compiles the `pdo_mysql` extension, which the base image ships without |
| `onCreateCommand` | [`install-services.sh`](../../.devcontainer/install-services.sh) | once per container | Installs the MariaDB server and client; verifies every required PHP extension |
| `postCreateCommand` | [`post-create.sh`](../../.devcontainer/post-create.sh) | once per container | Everything below |
| `postStartCommand` | [`start-services.sh`](../../.devcontainer/start-services.sh) | **every start** | Starts MariaDB and waits until it actually accepts connections |

`start-services.sh` runs on every start, not only on create, so the database is up
again after a Codespace is stopped and resumed. It polls `mysqladmin ping` rather
than trusting `service start`, which returns several seconds before the server is
ready.

### What `post-create.sh` does

1. Starts the database, by delegating to `start-services.sh`.
2. Creates the `acl` and `acl_test` schemas, and `acl_user` on both `localhost`
   and `127.0.0.1`, with privileges on those two schemas only. Tests need
   `CREATE`/`DROP` on `acl_test` because `RefreshDatabase` rebuilds it each run.
3. Writes a `0600 ~/.my.cnf`, so `mysql acl` needs no flags and no password ever
   appears on a command line where `ps` could read it.
4. `composer install`, asserts Node ≥ 20, then
   `npm install --ignore-scripts --no-audit --no-fund`.
5. Creates `.env` from `.env.example` if absent, then rewrites **only** the `DB_*`
   keys and `APP_URL` — every other line you have edited is left alone. Blanks a
   stale `DB_URL` (it would silently override `DB_HOST`). Generates `APP_KEY` only
   if one is missing, so a rebuild does not invalidate existing sessions. Clears
   the config, route and view caches.
6. `php artisan migrate --force`, then seeds **only when the `users` table is
   empty**, so a rebuild does not duplicate rows.
7. `npm run build`. This is not optional: Blade's `@vite` reads
   `public/build/manifest.json`, and without it the first page request throws.
8. Ensures `storage/` and `bootstrap/cache` are writable.
9. Reports its own health with `migrate:status` and `php artisan about`.

## Running the app

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Open the forwarded port 8000; you should land on the sign-in page. Sign in with an
account from [DEV_ACCOUNTS.md](DEV_ACCOUNTS.md) — those exist only in the
development seeder and are never rendered in the UI.

For the server, queue worker, log tailer and Vite together:

```bash
composer run dev
```

## Validating the environment

Start here whenever something looks wrong. It checks the toolchain, the
dependencies, `.env`, and the database connection, and reports **every** problem
rather than stopping at the first:

```bash
bash scripts/troubleshoot.sh
```

Then the suite, which runs against `acl_test`:

```bash
php artisan test
```

## Recovery

Re-run the application setup by hand — safe at any time:

```bash
bash .devcontainer/post-create.sh
```

Just the database:

```bash
bash .devcontainer/start-services.sh
```

If the image itself is broken, the container comes up as a minimal recovery
container instead. Check which one you are in, and read the build log:

```bash
echo "recovery=${CODESPACES_RECOVERY_CONTAINER:-false}"
cat /workspaces/.codespaces/.persistedshare/creation.log
```

That log is rewritten on every attempt rather than appended to, so read it before
starting another rebuild.

## Known non-issues

- `Xdebug: Could not connect to debugging client` — harmless; no listener attached.
- A line like `~ 500.47ms` in the `artisan serve` log is a **duration**, not an
  HTTP 500.
- `php artisan db:seed` is not idempotent (`DevelopmentSeeder` uses `create()`, so
  a second run collides on unique slugs). That is why `post-create.sh` guards it.
