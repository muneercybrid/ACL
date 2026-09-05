# ADR-0011: Promote a dedicated AWS EC2 instance to the production tier, served via a remotely-managed Cloudflare Tunnel

## Status

Accepted.

- **Supersedes [ADR-0009](0009-serve-from-codespace-via-cloudflare-tunnel.md)** —
  serving ACL *from the Codespace* and running *no separate production tier*.
  There is now a dedicated production tier, and the Codespace reverts to
  development-only and runs no tunnel.
- **Supersedes [ADR-0010](0010-locally-managed-cloudflare-tunnel.md)** — the
  locally-managed, credentials-file tunnel on the Codespace. The tunnel is now
  remotely-managed and runs on the EC2 instance.
- **Amends [ADR-0005](0005-adopt-mariadb.md)** — MariaDB is no longer the
  production engine; the production database is TiDB Cloud Serverless. MariaDB
  remains the engine for the test suite.
- **Extends [ADR-0007](0007-tidb-cloud-dev-runtime.md)** — TiDB Cloud, adopted
  there for the development runtime only, is now also the production database.

This is the "real production tier" [ADR-0009](0009-serve-from-codespace-via-cloudflare-tunnel.md)'s
Future Considerations anticipated — "the retained container image plus a new ADR
is the path to a supervised, always-on host."

Date: 2026-09-05

## Context

ADR-0009 made a deliberate, honest choice: serve the development Codespace
through a Cloudflare Tunnel and run **no** separate production tier, because the
near-term need was to *show* ACL at a real domain and nobody was staffed to
operate a production environment. ADR-0010 then had to route the tunnel around a
Cloudflare Zero Trust payment wall by running it as a locally-managed tunnel from
a credentials file on the Codespace.

Two facts have since changed, and together they remove the constraints that
produced both decisions:

- **A dedicated AWS EC2 instance now exists and is prepped** — Ubuntu 26.04 with
  PHP 8.5, nginx, php-fpm and redis installed natively on the host. This is a
  real always-on box, which the Codespace never was: the Codespace sleeps on
  idle, serves from a single-worker `php artisan serve`, and *is* the developer's
  machine.
- **The Cloudflare card / Zero Trust paywall that forced ADR-0010 has been
  cleared.** The remotely-managed model — a connector token plus ingress
  configured in the Zero Trust dashboard — is available again.

With a host to run production on, ACL can stop conflating its development machine
with its public surface — retiring ADR-0009's central risk, that "development and
the public surface are now the same machine."

## Problem

Now that a dedicated always-on host exists and the Zero Trust paywall is cleared,
how should ACL run a real production tier at `app.aclacademy.me` — where it runs,
how it is served, how it is deployed, and what database it uses — while keeping
ADR-0009's rule that no secret enters the repository, and without turning the
Codespace back into a production machine?

## Options Considered

**Where the tunnel runs and how it is managed**

1. **Adopt (migrate) the existing locally-managed Codespace tunnel into Zero
   Trust.** Rejected: `cloudflared` tunnel migration is effectively one-way, the
   migrated tunnel is still ingress-only, and it lives on the wrong machine — the
   Codespace, which is reverting to development-only.
2. **Create a fresh remotely-managed tunnel on the EC2 instance.** Chosen: the
   connector runs where production runs, its ingress is configured in the
   dashboard, and it leaves the Codespace with no tunnel at all.

**How production is deployed**

3. **Deploy ADR-0009's retained Docker image on the EC2.** Portable and already
   reviewed, but it adds a build/push/run loop and a supervisord process layer to
   a box already prepared to run the stack natively under systemd.
4. **Native deploy on the host** — nginx + php-fpm 8.5 + redis, reusing the
   repository's `docker/` runtime configs adapted to systemd. Chosen: the minimal
   path on a box that is already native. The retained image stays available but is
   not the serving path.

**Production database**

5. **MariaDB on the EC2** — self-hosted, preserving ADR-0005's dev/prod parity.
   Rejected: another service to install, secure, patch and back up on the host,
   for no gain over a managed database already in use.
6. **TiDB Cloud Serverless** — already provisioned, already the development
   runtime (ADR-0007), managed, MySQL-compatible over TLS. Chosen: minimal and
   managed, and it collapses the dev/prod engine divergence ADR-0007 introduced.

## Decision

1. **The production tier is a dedicated AWS EC2 instance** (Ubuntu 26.04, PHP 8.5
   native) serving **`app.aclacademy.me`**. **The GitHub Codespace reverts to
   development-only and runs no tunnel.** ADR-0009's "the served surface *is* the
   development Codespace" no longer holds.
2. **A remotely-managed Cloudflare Tunnel runs on the EC2 as a systemd service.**
   The connector authenticates with a **token** from the Cloudflare Zero Trust
   dashboard; ingress / the public hostname (`app.aclacademy.me` → the local
   origin) is configured **in the dashboard**, not a local `config.yml`. This
   replaces ADR-0010's locally-managed, `config.yml`-driven Codespace tunnel.
3. **Production is served natively under systemd** — nginx + php-fpm (PHP 8.5) +
   redis — **reusing the repository's `docker/` runtime configs** adapted to the
   host: `docker/nginx.conf`, `docker/site.conf.template`,
   `docker/php-fpm-pool.conf`, `docker/php.ini` and `docker/entrypoint.sh`.
   systemd takes the process-supervision role `docker/supervisord.conf` plays
   inside the image. The **container image retained by ADR-0009 remains an option
   but is not the serving path.**
4. **The production database is TiDB Cloud Serverless** (MySQL-compatible, over
   TLS on :4000) — the same managed database ADR-0007 adopted for the development
   runtime, now extended to production, with the development credential **rotated**
   for production use. ADR-0005's MariaDB remains the test-suite engine, so every
   merge is still validated against MariaDB.
5. **The Codespace devcontainer tunnel code is removed** — the `cloudflared`
   install/start logic in `.devcontainer/install-services.sh` and
   `.devcontainer/start-services.sh`, and the `ACL_TUNNEL_*` constants in
   `.devcontainer/config.sh` — and the **`CLOUDFLARE_TUNNEL_CREDENTIALS_B64`
   Codespaces secret is removed.** The Codespace returns to a plain development
   environment (TiDB or local MariaDB per ADR-0007, Redis per ADR-0008), reachable
   only through Codespaces port forwarding.
6. **No secret enters the repository.** The tunnel connector token lives only in
   the EC2 `cloudflared` service configuration; the production TiDB credential
   lives only in the EC2 environment. ADR-0009's principle is retained.

## Reasoning

- **The two constraints that produced ADR-0009 and ADR-0010 are both gone.** There
  is now a host to run an always-on production tier on, and the token model is
  usable again. Each decision was correct for its moment; this ADR records that the
  moment changed.
- **A separate production box retires ADR-0009's biggest risk** — that a mistake in
  the Codespace was a mistake in public. Development and the public surface are
  different machines again.
- **Native reuse is the minimal path on a prepped-native box.** The `docker/`
  configs already encode the runtime; adapting them to systemd is less work than a
  build-and-run image loop, and systemd already fills supervisord's role.
- **TiDB in production collapses a divergence rather than widening one.** ADR-0007
  left the dev runtime on TiDB and production on MariaDB; pointing production at
  TiDB too gives one runtime engine across both, while MariaDB stays the test gate
  so ADR-0005's "validated against the real engine before production" guarantee
  holds.
- **The remotely-managed tunnel is the right model for an always-on host** —
  ingress is visible and auditable in the dashboard, there is no local `config.yml`
  to drift, and the token model is now unblocked.
- **Boring beats clever.** No orchestrator, no new datastore beyond the one already
  provisioned, and existing artifacts reused rather than replaced.

## Consequences

**Good**

- A real always-on production tier with a genuine process supervisor (systemd),
  separate from the development machine.
- Dev and the public surface are different machines again; a Codespace mistake is
  no longer a public mistake.
- One runtime engine (TiDB) across development and production, with MariaDB
  retained as the merge gate.
- Ingress is dashboard-managed and auditable; no local `config.yml` to keep in
  sync.
- The Codespace simplifies: no tunnel, no `cloudflared`, one fewer secret.

**Bad**

- ACL now **operates infrastructure** it must patch, secure and monitor: an
  internet-adjacent EC2 host, nginx, php-fpm, redis and a systemd `cloudflared`
  service. This is exactly the operational surface ADR-0009 avoided; it is accepted
  now because a real production tier is the actual need.
- **There is still no CI.** Deploying to the box is a manual, out-of-band step, and
  nothing automated gates what reaches production.
- **The MySQL-vs-MariaDB divergence still exists** (TiDB runtime, MariaDB tests):
  engine-specific behaviour can pass the runtime and fail the suite — the bounded
  risk ADR-0007 named, now present in production too, and still pointing the right
  way (caught by tests before release).
- Production configuration — nginx/php-fpm/systemd units and a repeatable deploy
  procedure — must be written and documented; adapting the `docker/` configs to
  systemd is real work, not a copy.

**Neutral**

- The retained container image is still retained — now genuinely the fallback path
  ADR-0009 described, not the serving path.
- ADR-0008 recorded production on Laravel's `database` session/cache/queue drivers
  (a statement about the defunct Render image). The EC2 tier runs redis natively,
  so redis is available for those stores as in development; the exact driver
  configuration for production is a deploy-time detail this ADR does not pin.

## Security Implications

- **The seeded development administrator must be neutralised before the box is
  internet-reachable.** `database/seeders/DevelopmentSeeder.php` creates
  `admin@acl.local` / `password`, and `AppServiceProvider`'s `Gate::before` grants
  a platform administrator every ability. This was ADR-0009's "open door" on the
  Codespace URL; on an always-on production host it is worse, because the host does
  not sleep. `DevelopmentSeeder` must not run on the production box and no such
  account may exist there. The first administrator is bootstrapped by hand — the
  gap ADR-0009 flagged (no production-safe seeder, no `acl:create-admin` command)
  is still open and is now a **precondition for exposure**, not a nicety.
- **`trustProxies(at: '*')` (`bootstrap/app.php`) is safe only behind the tunnel.**
  The EC2 origin (nginx → php-fpm) must be reachable only through the local
  `cloudflared` connector, never on a public inbound port, so that only Cloudflare
  can set `X-Forwarded-*`. The instance security group must not expose the app
  origin to the internet; the connector dials out. This is the loopback-only
  condition ADR-0009 and ADR-0006 named, now applied to a real host and its
  security group.
- **There is no Content-Security-Policy yet.** The application sends no CSP header;
  this is unchanged by this ADR and remains an application-security gap to close for
  real traffic.
- **The tunnel connector token is a secret held only in the EC2 `cloudflared`
  service configuration**, never in the repository. It replaces ADR-0010's
  `CLOUDFLARE_TUNNEL_CREDENTIALS_B64`, which is removed.
- **TiDB Cloud is internet-facing.** Production requires a **least-privilege**
  database user (not a broad or administrative grant — ADR-0005's least-privilege
  rule) and a **rotated** production credential distinct from the development one,
  held only in the EC2 environment and used over TLS. A leaked production credential
  must be rotated immediately.
- **No secret enters the repository** — ADR-0009's principle, retained: connector
  token and production database credential live only on the box.

## Future Considerations

- **CI/CD.** There is still no pipeline; deploys are manual. The natural next
  decision is CI that runs the MariaDB suite and gates the EC2 deploy — its own ADR.
- **A production bootstrap path.** `acl:create-admin`, or a production-safe seeder,
  is still unbuilt and is now a precondition for exposure, not a convenience. It is
  application work with its own tests.
- **Operations.** TiDB Cloud is managed, but the EC2 host, its systemd units and a
  restart-after-reboot story need an operations write-up. Availability now tracks
  the EC2, not the Codespace.
- **CSP and a hardened auth story** — deferred application-security work, more
  pressing on an always-on, internet-reachable host than on a sleepy Codespace.
- **Documentation reconciliation (out of scope for this ADR).**
  `docs/deployment/DOMAIN_SETUP.md`, `docs/deployment/README.md` and `CLAUDE.md` §1
  still describe the Codespace-tunnel serving model and MariaDB-in-production; they
  must be updated in the same change that implements this move. This ADR records the
  decision only.
