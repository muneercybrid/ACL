# ADR-0009: Serve ACL from the Codespace via a Cloudflare Tunnel; drop Render as the host

## Status

Accepted. **Supersedes the hosting decision of [ADR-0006](0006-deploy-on-render-with-docker.md)**
— Render as the host, with a managed MariaDB supplied externally. The container
image ADR-0006 introduced (`Dockerfile`, `docker/`) is **retained as a portable,
platform-neutral artifact**, but it is no longer the serving path. ADR-0005
(MariaDB) is untouched.
Date: 2026-09-05

## Context

ADR-0006 chose Render as the host and a managed MariaDB from a provider outside
Render. The image was proven to build and boot as far as its `APP_KEY` guard;
nothing past that guard ever ran — no request served (see
[`docs/PROJECT_STATUS.md`](../PROJECT_STATUS.md) §9). A domain was registered at
Namecheap.

Since ADR-0006 the Codespace became a complete runtime in its own right: the dev
runtime database moved to TiDB Cloud ([ADR-0007](0007-tidb-cloud-dev-runtime.md)),
and sessions, cache and queue moved to a loopback Redis
([ADR-0008](0008-redis-in-development.md)).

The project's actual near-term need is to **show** ACL at a real domain to a
small, known audience — not to operate a separate, always-on production tier that
nobody is staffed to run. Against that need, Render adds a second platform, a
second bill, an external managed-MariaDB to provision and secure, a deploy path
proven only as far as a guard, and `autoDeploy: true` with no CI, so `main` would
reach the public URL unverified.

## Problem

How does ACL reach a public domain now, with the least new operational surface,
and without standing up a separate production environment there is no one to
operate?

## Options Considered

1. **Keep ADR-0006 — Render plus an external MariaDB.** Portable and reviewable,
   but two platforms and two bills, an external database to run and secure, a
   path proven only to the `APP_KEY` guard, and unverified auto-deploy. More
   machinery than a showcase-stage project needs.
2. **Serve the Codespace through a Cloudflare Tunnel at the Namecheap domain.**
   No new host account, no external database (the Codespace already has its
   runtime DB and Redis), free edge TLS, and an outbound-only connector needing
   no public IP or inbound ports. It is a *development server made reachable*,
   not a production tier — and is honest about being exactly that. Chosen.
3. **A PHP-native host** — Laravel Cloud, Forge on a VPS, Fly.io. Each fits
   Laravel well, but each reintroduces the separate environment and cost this
   decision is deliberately avoiding right now. Deferred, not rejected: the
   retained container image keeps this open with no new work.

## Decision

1. ACL is served from the **GitHub Codespace via a Cloudflare Tunnel** at
   **`app.aclacademy.me`**. The procedure is
   [`docs/deployment/DOMAIN_SETUP.md`](../deployment/DOMAIN_SETUP.md).
2. **Render is dropped as the host.** `render.yaml` is deleted. ADR-0006's
   Render-plus-external-MariaDB decision no longer governs.
3. The **container image (`Dockerfile`, `docker/`) is retained** as a portable,
   platform-neutral deployment artifact — it names no host — for a later move to
   a real production platform. It is not the current serving path.
4. **There is no separate production environment.** The served surface *is* the
   development Codespace, so it runs ADR-0007's runtime database (TiDB Cloud, or
   the local MariaDB) and ADR-0008's Redis for sessions, cache and queue. Those
   ADRs now describe the served environment, not merely an isolated dev box.
5. **No externally-supplied managed MariaDB is required.** That requirement
   existed only because a stateless Render container had no database of its own.
6. The tunnel token is a secret, supplied only as the `CLOUDFLARE_TUNNEL_TOKEN`
   GitHub Codespaces secret. Nothing secret enters the repository.

## Reasoning

- **Fewest moving parts.** No second platform, no second bill, no external
  database, no image build-and-push loop, no auto-deploy that outruns a
  test suite that does not yet run in CI.
- **Honest about what it is.** `php artisan serve` behind a tunnel is a
  development server exposed to the internet: adequate for a low-traffic
  showcase, and explicitly not a hardened production tier. Naming that in an ADR
  is what stops the URL being mistaken for one.
- **ADR-0005 stands.** MariaDB remains the engine for the test suite, and TiDB's
  MySQL compatibility keeps the runtime on the same dialect. Nothing here
  reverses the data-engine decision.
- **Keeping the image costs nothing and preserves the one durable good ADR-0006
  produced:** a reviewable, portable definition of the runtime. When a real
  production tier is needed, that image plus a new ADR is the path — to Render or
  anywhere else.

## Consequences

**Good**

- One environment to run and reason about: the thing visitors reach is the thing
  developers run.
- Free TLS, no inbound exposure, no public IP — the connector dials out only.
- No external database to provision, secure and pay for.

**Bad**

- **The served surface is a development server.** No process supervisor, no
  zero-downtime deploy, a single worker, and it stops when the Codespace stops.
  It is not production and must not be described as production.
- **Development and the public surface are now the same machine**, so a mistake
  in the Codespace is a mistake in public. The `APP_ENV`/`APP_DEBUG` discipline
  in `DOMAIN_SETUP.md` Part 4 is load-bearing, not hygiene.
- **Availability tracks the Codespace:** it sleeps on inactivity and stops on the
  account's idle timeout; the URL is down until someone resumes it and restarts
  the app.

**Neutral**

- ADR-0008 recorded that production uses Laravel's `database` drivers — a
  statement about the Render image. With that image no longer the serving path,
  the served store is ADR-0008's Redis. If a real production tier is later built
  from the retained image, that ADR's `database`-driver production stands for it,
  decided then.

## Security Implications

- **The public URL has no platform-level access gate.** An earlier draft of
  `DOMAIN_SETUP.md` fronted the domain with Cloudflare Access (email one-time
  PIN); that gate has been **removed at the maintainer's request**. ACL's own
  Laravel authentication is therefore the *sole* barrier between the internet and
  the application.
- **The seeded development administrator is an open door on that URL.**
  `DevelopmentSeeder` creates `admin@acl.local` / `password`, and
  `AppServiceProvider`'s `Gate::before` grants a platform administrator every
  ability. On a publicly reachable instance with no edge gate, anyone who knows
  that well-known default is a full administrator. **Before exposing the tunnel,
  change that account's password — or do not run `DevelopmentSeeder` on the
  exposed instance.** This restates the standing rule that development accounts
  must never be reachable in a production-like setting.
- **Trusting all proxies (`trustProxies(at: '*')`) is safe only because the
  origin is loopback-only behind the tunnel.** The Codespace's forwarded port
  8000 must stay private, so only `cloudflared` can set `X-Forwarded-*` headers;
  Cloudflare overwrites them at the edge. This condition is inherited unchanged
  from ADR-0006.
- **No secret enters the repository.** The tunnel token lives only in the
  `CLOUDFLARE_TUNNEL_TOKEN` Codespaces secret.

## Future Considerations

- **A real production tier.** When ACL carries real students, the retained
  container image plus a new ADR is the path to a supervised, always-on host.
  That decision revisits the `database`-versus-Redis driver choice for that
  environment, and would restore a platform-level access gate or a hardened
  auth story.
- **A production bootstrap path.** ACL still has no production-safe seeder and no
  `acl:create-admin` command; the first administrator is made by hand. That gap
  is unchanged by this ADR and is application work with its own tests.
- **Self-healing the app process.** Nothing auto-starts `php artisan serve` after
  a Codespace resume; `DOMAIN_SETUP.md` Part 3.5 sketches an optional guarded
  starter that does not block the tunnel.
