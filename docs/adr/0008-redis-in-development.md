# ADR-0008: Redis for Sessions, Cache and Queue in Development

## Status

Accepted. Amends the "no Redis" premise of
[ADR-0006](0006-deploy-on-render-with-docker.md) for the **development
environment only**; it does **not** supersede it. Production continues to use
Laravel's `database` drivers as ADR-0006 decided. Follows from
[ADR-0007](0007-tidb-cloud-dev-runtime.md).
Date: 2026-09-05

## Context

ADR-0006 recorded, correctly at the time, that "ACL uses Laravel's `database`
session, cache and queue drivers, so it needs exactly one backing service: a
MariaDB schema. No Redis." ADR-0007 then moved the development runtime database
to TiDB Cloud — a remote engine reached over TLS at ~150–330 ms per round-trip
versus ~10 ms for a loopback server.

That latency changes the arithmetic for the three `database`-backed subsystems:

- **Sessions** are read and written on nearly every authenticated request.
- **Cache** exists to be faster than the thing it caches; a cache that costs a
  remote round-trip per read is close to pointless.
- **Queue** polling against a remote database is both wasteful and slow.

On the dev runtime each of these now pays the TiDB round-trip. A loopback Redis
answers in microseconds and removes that cost, which is why introducing it is a
direct consequence of ADR-0007 rather than an independent want.

Adding Redis is **new infrastructure and a new dependency**, both of which
`CLAUDE.md` §6 puts behind explicit authorisation and an ADR. This is that
authorisation and that ADR.

## Problem

How does the development environment get a fast, local session/cache/queue
store — without a compiled PHP extension, without a real secret, and without
reversing ADR-0006's production decision?

## Options Considered

1. **Leave sessions/cache/queue on the `database` driver.** Zero new
   infrastructure, but every session and cache read on the dev runtime pays the
   TiDB round-trip (ADR-0007). Untenable for interactive development.
2. **Redis via the `phpredis` compiled C extension.** Fastest client, but it
   must be compiled and enabled against the container's exact PHP build, then
   matched again in the Docker image — precisely the extension-matching pain
   ADR-0005 avoided for MongoDB.
3. **Redis via `predis`, a pure-PHP client.** No compiled extension: a normal
   Composer dependency that behaves identically in the codespace and in the
   image. Marginally slower than `phpredis`, immaterial against a loopback
   server. Chosen.
4. **A second local database schema for cache/queue.** Keeps the driver but not
   the round-trip once the runtime DB is remote. Solves nothing that matters.

## Decision

1. **Development uses Redis** for `SESSION_DRIVER`, `CACHE_STORE` and
   `QUEUE_CONNECTION`, via the **`predis`** pure-PHP client (a new, authorised
   `composer.json` dependency).
2. Redis runs **inside the container, bound to loopback, password-protected**,
   provisioned by `.devcontainer/start-services.sh` on every start so it
   survives a codespace stop/resume and a rebuild.
3. **Key-space layout: db0 holds sessions and the queue; db1 holds the cache.**
   Kept apart so `cache:clear` (a `FLUSHDB` on db1) can never evict a live
   session in db0.
4. **`maxmemory-policy noeviction`**, not an LRU policy: db0 holds sessions, and
   silently evicting a session key would log a student out mid-request. At the
   256 MB ceiling a loud write error is the correct dev outcome, not a mystery
   logout.
5. **Production is unchanged.** ADR-0006's `database` drivers stand; this ADR
   does not put Redis into the deployed image.

## Reasoning

- **predis keeps the dependency rule intact.** Being pure PHP, `composer
  install` alone provisions the client in both the devcontainer and the Docker
  image — no `docker-php-ext-install`, no build step that can drift from the
  running PHP version.
- **Loopback + password + noeviction is the safe default.** The server is never
  internet-facing (its credential is a deliberately public container-only value,
  like the MariaDB one in ADR-0005), sessions cannot be silently dropped, and
  cache growth cannot pressure sessions because they live in different logical
  databases.
- **The divergence from production is honest and small.** Dev uses Redis; prod
  uses `database` drivers. Laravel abstracts all three subsystems behind the
  same contracts, so application code is identical either way — only the backing
  store differs. The alternative, `database` sessions against remote TiDB, would
  make the dev environment slow enough to change how people work.
## Consequences

**Good**

- Sessions, cache and queue return in microseconds on the dev runtime,
  cancelling most of ADR-0007's latency for the hot path.
- No compiled extension; an identical client in the codespace and the image.
- Cache and sessions are isolated by logical database.

**Bad**

- Dev now diverges from production in the session/cache/queue backing store.
  Recorded here deliberately; revisited under "Future Considerations".
- One more service the devcontainer must start and keep healthy — handled by
  `start-services.sh`, which regenerates the config under `$HOME/.acl` on every
  start.

**Neutral**

- `phpunit.xml` already pins the suite away from Redis (`CACHE_STORE=array`,
  `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=array`), so tests neither need nor
  touch it — that isolation predates this ADR and still holds.

## Security Implications

- **The Redis credential is a container-only dev value**, deliberately public
  like the MariaDB one, protecting a server bound to loopback that the internet
  cannot reach. Its exposure changes nothing.
- **`requirepass` is set regardless**, so nothing on the container talks to
  Redis unauthenticated even locally, and the distro's default password-less
  redis on :6379 is stopped and masked by `install-services.sh` so an
  unauthenticated server can never win the port.
- **`noeviction` protects session integrity**: no session key is dropped to
  make room, so authorization state cannot silently vanish.
- **No secret enters the repository.** The password is a fixed dev value in
  `.devcontainer/config.sh`, documented there as container-only.

## Future Considerations

- **Production parity.** If prod ever needs Redis, Render offers a
  Redis-compatible Key Value service (noted in ADR-0006); adopting it would
  bring prod's session/cache/queue store in line with dev and would be a small
  addition to `render.yaml`, not a new engine. Until a measured need exists,
  `database` drivers in production stay — they add no service to operate.
- **Queue worker.** ACL dispatches no jobs yet. When the first one exists, the
  Redis queue is already the dev backing store; the worker goes where
  `docker/supervisord.conf` reserves it (ADR-0006).
- **phpredis** could replace predis later if profiling ever shows the pure-PHP
  client is a real bottleneck — unlikely against loopback. That swap is a
  dependency change and wants a note, not necessarily a new ADR.

