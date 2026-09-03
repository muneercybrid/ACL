# ADR-0006: Deploy ACL as a Container on Render, with MariaDB Supplied Externally

## Status

Accepted. Does not supersede any ADR; in particular
[ADR-0005](0005-adopt-mariadb.md) stands unchanged.
Date: 2026-09-03

## Context

ACL had no deployment configuration of any kind: no `Dockerfile`, no CI
pipeline, no host, and an empty `docs/deployment/README.md`. `CLAUDE.md` §12
records that state and requires that production work be *designed*, with an
ADR, rather than improvised. This is that ADR.

The circumstances that forced the decision now:

- A domain has been registered at Namecheap.
- A deploy to Render was attempted and failed. The build log was not
  available when this was written, so the cause is inferred rather than
  confirmed — see "Problem".
- Render's native runtimes are Node, Python, Ruby, Go, Rust and Elixir.
  **There is no PHP runtime.** A PHP application on Render is a container
  image or it is nothing.
- Render's managed data stores are PostgreSQL and a Redis-compatible Key
  Value service. **There is no managed MySQL or MariaDB.** ADR-0005 requires
  MariaDB in every environment.
- ACL uses Laravel's `database` session, cache and queue drivers, so it needs
  exactly one backing service: a MariaDB schema. No Redis, no object store,
  no search engine, no broker.
- `bootstrap/app.php` already exposes `health: '/up'` and already calls
  `trustProxies(at: '*')`. Both were added for Codespaces port forwarding and
  are exactly what is needed behind a TLS-terminating platform proxy.

## Problem

How does ACL become a running service on a public domain — without reversing
ADR-0005, and without introducing infrastructure the project does not need?

Two coupled decisions: what runs the application, and what runs its database.

A third question sits underneath both: why did the first attempt fail? The
most likely answer is that Render found a repository with no `Dockerfile` and
no runtime it recognised, and had nothing to build. That is a hypothesis, not
a finding, and it is recorded here as one.

## Options Considered

### What runs the application

1. **Render, native runtime.** Not available. Render has no PHP runtime.
2. **Render, a Docker image built from this repository.** ACL owns the
   Dockerfile, so the production PHP version, extension set and web server
   become code — reviewable, diffable, and reproducible by whoever builds
   next. The same image runs on any other container host.
3. **A PHP-specific platform** — Laravel Cloud, Forge on a VPS, Fly.io,
   Railway. Several would fit Laravel better than Render does. But a domain is
   already registered, an attempt has already been made, and a container is
   portable, so choosing Render forecloses nothing.
4. **A hand-configured VPS.** Cheapest per gigabyte of RAM, most expensive
   per hour of attention: someone must patch it, renew its certificates and
   notice when it stops. Rejected — nobody is on call.

### What runs the database

1. **Render's managed PostgreSQL.** Reverses ADR-0005. That means a
   superseding ADR, a re-review of every `string`-plus-comment column that
   exists because MySQL `ENUM` was rejected, a second engine in the test
   matrix, and a migration audit. Rejected: a hosting platform's convenience
   is not a reason to change the project's data engine.
2. **Managed MariaDB or MySQL from a provider outside Render.** Keeps ADR-0005
   intact and buys backups, point-in-time recovery and failover from a vendor
   whose job that is. Costs one network hop out of Render's network, and
   requires TLS on the connection.
3. **MariaDB as a Render private service with a persistent disk.** Documented
   by Render, reachable only from services in the same workspace, never
   exposed to the internet. Everything stays on one bill. But it is a single
   container: no replication, no failover, and Render's own documentation
   warns that **disk snapshots will likely corrupt database files**, so
   `mysqldump` is the only backup that can be trusted.
4. **MariaDB on a separate VPS.** Same objection as option 4 above, applied to
   the component whose failure is least recoverable.

## Decision

1. ACL is deployed as a **container image built from a Dockerfile in this
   repository** (`Dockerfile` plus `docker/`), on **Render**, described by
   `render.yaml`.
2. The image serves through **nginx and php-fpm under supervisor**, binding
   the port the platform supplies in `$PORT`, with `/up` as its health check.
3. **MariaDB is supplied by a managed provider outside Render** — option 2.
   ADR-0005 stands; nothing here reverses it.
4. The Render private-service alternative — option 3 — is recorded here and
   kept commented in `render.yaml`, as the documented fallback for a
   single-vendor setup. Choosing it does not need a new ADR; it needs the
   backup caveat above taken seriously.
5. **No production configuration lives in this repository except structure.**
   Every secret in `render.yaml` is marked `sync: false` and set by hand once.

## Reasoning

**Docker was not chosen over a native runtime.** There was no native runtime
to choose. This is worth stating plainly because it is the whole explanation
for the failed first attempt.

**Owning the Dockerfile is the point, not a side effect.** The production PHP
version, its extensions and its web server stop being facts about a machine
somebody configured and become lines in a file that a reviewer can read. It
also makes the choice of Render reversible: the same image runs elsewhere.

**The image is a superset of the development contract.** Its extension set is
`scripts/troubleshoot.sh`'s required list plus that script's optional list, so
a container that boots satisfies the same contract the codespace does.

**Caches are built at container start, never at image build.** The platform
injects environment variables at run time. A build-time `config:cache` would
bake in an empty `APP_KEY` and a database host of `127.0.0.1`, producing an
image that is broken in a way that only appears on the first request.

**`clear_env = no` in the php-fpm pool.** php-fpm empties each worker's
environment by default, which discards every variable the platform injects.
This is the single most common way a correctly built PHP image serves a
completely broken application, and it is one line to prevent.

**Keeping MariaDB outside Render is the option that changes least.** Postgres
would be a larger change to ACL than the entire deployment is.

## Consequences

**Good**

- ACL becomes deployable on any container platform, not only this one.
- The production environment is described in two reviewable files rather than
  in a dashboard nobody can diff.
- ADR-0005 is untouched: one engine, MariaDB, in development, test and
  production.
- No new application dependency. nginx, php-fpm and supervisor are packages
  inside an image; `composer.json` and `package.json` are unchanged.

**Bad**

- Two vendors and two bills, one for the application and one for the database.
- Cross-network database latency, and a TLS connection to configure, where a
  same-platform managed database would have neither.
- Image build time on every deploy: Composer and npm both run.
- The container is stateless by necessity. `FILESYSTEM_DISK=local` writes to a
  filesystem that is destroyed on every deploy. ACL has no upload feature, so
  nothing is lost today; the first upload feature needs object storage or a
  mounted disk, and its own decision.

**Neutral**

- Migrations run in the entrypoint, which is correct for one instance and
  wrong for several. `ACL_RUN_MIGRATIONS=false` plus a platform pre-deploy
  command is the documented answer when that day comes.
- No queue worker runs. ACL dispatches no jobs yet;
  `docker/supervisord.conf` records where the worker goes.

## Security Implications

- **No secret enters the repository.** `render.yaml` declares the *names* of
  `APP_KEY`, `DB_PASSWORD` and the rest with `sync: false`, and nothing else.
  `.dockerignore` lists `.env` first, so no build context can carry it into a
  layer.
- **`APP_KEY` is never generated at start-up.** `docker/entrypoint.sh` refuses
  to boot without one. Generating a key per deploy would silently invalidate
  every session and make every encrypted column unreadable — a data-loss bug
  wearing the costume of a convenience.
- **`APP_DEBUG=true` is warned about loudly** at every start. In production it
  renders stack traces, environment values and SQL to visitors.
- **`SESSION_SECURE_COOKIE=true`.** Render terminates TLS and redirects HTTP,
  so the session cookie has no reason to be sendable over plain HTTP.
- **The application is served by php-fpm as `www-data`, not root**, and only
  `storage/` and `bootstrap/cache` are writable.
- **Only `public/index.php` is ever executed.** The nginx virtual host denies
  every other `.php` path and every dotfile, so a file that should not be
  interpreted cannot be, even if one appeared under `public/`.
- **The database is not reachable from the internet in either option.** An
  external managed provider must be restricted to Render's egress addresses
  and required to use TLS (`MYSQL_ATTR_SSL_CA`, which
  `config/database.php` already reads). A Render private service has no public
  address at all.
- **The database user is not root**, per ADR-0005: privileges on the ACL schema
  only.
- **The previously exposed managed-MariaDB root password must not be reused.**
  It appeared in commented-out lines of a working `.env`. If that instance is
  the one used in production, rotate the credential first.
- **Trusting all proxies (`trustProxies(at: '*')`) is safe only behind a proxy
  that overwrites the forwarding headers.** Render does. If ACL is ever put
  behind something that merely appends to them, a client can spoof its own IP
  and defeat rate limiting, and that call needs narrowing.

## Future Considerations

- **A CI pipeline.** There is still no `.github/` directory: nothing runs the
  test suite or Pint before a deploy, and `autoDeploy: true` means `main`
  reaches production unverified. This is the largest remaining gap and wants
  its own change, not its own ADR.
- **A staging service.** One more block in `render.yaml` on a different branch
  with its own schema.
- **Migrations as a pre-deploy step**, when the service scales past a single
  instance.
- **Object storage**, when the first upload feature exists. Its own decision.
- **A queue worker**, when the first job exists.
- **A Content-Security-Policy header.** Deliberately absent from
  `docker/site.conf.template`: what a correct policy allows is determined by
  Vite's output and Alpine's inline behaviour, so it belongs with the frontend
  work rather than guessed at in a web-server config.
- **Backups.** An external managed provider supplies them. A Render private
  service does not, and its disk snapshots are documented as unsafe for
  database files — a scheduled `mysqldump` would be mandatory, not optional.
- **Portability is retained deliberately.** Nothing in the image knows it is
  on Render; it reads `$PORT` and its environment. Moving hosts means a new
  `render.yaml` equivalent, not a new image.


