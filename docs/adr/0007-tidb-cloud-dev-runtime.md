# ADR-0007: TiDB Cloud Serverless as the Development Runtime Database

## Status

Accepted. Amends [ADR-0005](0005-adopt-mariadb.md) for the **development
runtime only**; it does **not** supersede it. MariaDB remains the engine for
the test suite and for production.
Date: 2026-09-05

## Context

ADR-0005 standardised on MariaDB "across all environments" to preserve dev/prod
parity. That decision stands for the two environments where it matters most:
the test suite runs against a local MariaDB `acl_test` schema (pinned by
`phpunit.xml`), and production runs against a managed MariaDB (ADR-0006).

The development *runtime* — the database a developer's browser session hits
while building a feature in the codespace — is a third case ADR-0005 did not
separate out. Two things pulled it away from the local MariaDB:

- A TiDB Cloud Serverless account was available, free at the tier ACL needs,
  and reachable from anywhere without the developer running a server.
- Working against a hosted database mirrors the production *shape* — a remote
  engine over TLS with real latency — more faithfully than a loopback MariaDB
  does, surfacing N+1 queries and missing indexes while they are cheap to fix.

TiDB speaks the **MySQL 8.0 wire protocol**, not MariaDB's. It is MySQL-family
and SQL-compatible, but it is a different server, so this is a genuine engine
change for the one environment it touches — and it must be recorded as one
rather than left as an undocumented `.env` edit, the exact failure ADR-0005
itself was written to correct.

## Problem

Can the development runtime use TiDB Cloud Serverless without (a) reversing
ADR-0005, (b) weakening the test suite's guarantee that code is validated
against the production engine, or (c) putting a real secret in the repository?

## Options Considered

1. **Local MariaDB for everything, including the dev runtime** — the strict
   ADR-0005 reading. Zero divergence, but no hosted-database realism and a
   server to keep running for every developer.
2. **TiDB Cloud for the dev runtime, MariaDB for tests and production** —
   accept a bounded, recorded divergence in exchange for realism and
   zero-server access. Tests still run on MariaDB, so the production engine is
   still the one every merge is validated against.
3. **TiDB everywhere, including tests and production** — maximum parity with
   the dev runtime, but reverses ADR-0005 wholesale, changes the production
   engine, and would need its own migration and constraint audit. Far larger
   than the problem.

## Decision

1. The **development runtime** database is **TiDB Cloud Serverless**, reached
   over TLS on port 4000 with `DB_CONNECTION=mysql`.
2. It is enabled **only** when the `ACL_TIDB_*` GitHub Codespaces secrets are
   present. When they are absent the devcontainer falls back to the local
   MariaDB unchanged, so a fresh clone still works offline.
3. **The test suite is unchanged.** `phpunit.xml` pins tests to the local
   MariaDB `acl_test` schema; no test ever runs against TiDB.
4. **Production is unchanged.** ADR-0006's managed MariaDB stands.
5. TiDB credentials are **secrets**, not the container-only dev credentials of
   ADR-0005/0008. They live in Codespaces secrets and never in the repository.

## Reasoning

- **The divergence is bounded and recorded.** The engine differs in exactly one
  environment — the dev runtime — while the environment that gates merges (the
  test suite) still runs on the production engine. Migrations use the portable
  Laravel schema builder (ADR-0005), so they apply to both.
- **TLS is not optional and not URL-driven.** TiDB refuses non-TLS connections
  on :4000. Laravel discards `?ssl-mode=` from a `DB_URL`, so the CA bundle is
  passed through the `options` array in `config/database.php`
  (`MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt`, the OS trust store
  that already validates TiDB's public CA).
- **Foreign keys — ACL's whole data model — are enforced.** TiDB has supported
  FK constraints as GA since v8.5, so the referential integrity ADR-0005
  depends on holds on the dev runtime too.
- **Latency is the point, not only a cost.** Round-trips to a remote region
  make a slow query obvious in development instead of in production. That same
  latency is what motivates Redis for sessions, cache and queue — see
  [ADR-0008](0008-redis-in-development.md).
## Consequences

**Good**

- Hosted-database realism in development: TLS, latency, a remote engine.
- No local database server to run for the dev runtime; access from anywhere.
- Test and production guarantees are untouched — both remain MariaDB.

**Bad**

- A MySQL-vs-MariaDB engine difference now exists in one environment. It is
  bounded by keeping tests on MariaDB, but engine-specific behaviour (a
  function one server has and the other lacks) could pass on the dev runtime
  and fail the suite. That is the correct direction for a failure to point —
  caught by tests, before production.
- Every dev-runtime query crosses the internet (~150–330 ms vs ~10 ms local).
  Mitigated, not removed, by moving sessions/cache/queue to Redis (ADR-0008).
- Offline development requires the MariaDB fallback (unset the secrets).

**Neutral**

- Two provisioning paths in `post-create.sh` (TiDB when secrets present, local
  MariaDB otherwise). The three-way selection there also leaves a hand-set
  `.env` untouched on a rebuild, so switching engines by hand survives.

## Security Implications

- **TiDB credentials are genuine secrets.** They reach an internet-facing
  database, so — unlike the loopback MariaDB/Redis/Mailpit credentials, which
  are deliberately public container-only values — they live only in Codespaces
  secrets (`ACL_TIDB_HOST/PORT/DATABASE/USERNAME/PASSWORD`) and never in the
  repository, `.env.example`, or a commit.
- **TLS is mandatory and verified against the OS trust store**, not disabled
  and not weakened to a permissive `ssl-mode`.
- **A leaked TiDB credential must be rotated**, because unlike a container-only
  password it protects real hosted data.

## Future Considerations

- If TiDB-specific behaviour is ever *relied upon*, that reliance breaks
  ADR-0005's portability guarantee and needs its own ADR.
- If the dev/runtime divergence ever causes a real defect, the cheap answer is
  to point the dev runtime back at the local MariaDB (unset the secrets); the
  expensive answer (TiDB in tests too) needs a superseding ADR.
- A managed MariaDB could replace TiDB for the dev runtime with no code change
  — only the secrets change — if a single-engine dev runtime is later wanted.
