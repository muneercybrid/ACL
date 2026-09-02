# ADR-0005: Adopt MariaDB Across All Environments

## Status

Accepted — supersedes [ADR-0002](0002-database-strategy.md).
Date: 2026-09-02

## Context

ADR-0002 chose SQLite for development and PostgreSQL for production. In
practice the project moved to MariaDB without recording the decision:

- `.devcontainer/devcontainer.json` starts MariaDB (`postStartCommand:
  sudo service mariadb start`).
- The working `.env` connected to a managed MariaDB instance.
- `.env.example` documents MariaDB and already referenced "ADR-0005".

This left the repository contradicting itself: ADR-0002 and
`.ai/guidelines/ACL.md` said SQLite/PostgreSQL, `PROJECT_STATUS.md` said
SQLite, the README said MySQL/MariaDB, and the runtime used MariaDB. A
silent architectural decision of this size is exactly what the project's own
engineering rules forbid. This ADR records the real decision and reconciles
the documentation.

## Problem

Which relational engine should ACL standardize on across local, staging, and
production — and how do we remove the SQLite / PostgreSQL / MariaDB
contradiction?

## Options Considered

1. **SQLite (dev) + PostgreSQL (prod)** — the original ADR-0002. Rejected:
   dev/prod engine mismatch hides bugs until production, and the project is
   already running MariaDB.
2. **PostgreSQL everywhere** — technically strong, but diverges from the
   tooling already in place (devcontainer, managed instance) with no feature
   currently requiring Postgres.
3. **MariaDB everywhere** — matches the devcontainer, `.env`, and
   `.env.example`; MySQL-compatible; ubiquitous and cheap on the VPS hosts
   ACL targets; a solid fit for a modular monolith.

## Decision

Standardize on **MariaDB** for local development, staging, and production.
One engine across all environments to preserve dev/prod parity.

## Reasoning

- **Dev/prod parity** — the same engine locally and in production removes a
  whole class of "worked in dev, broke in prod" defects.
- **Already provisioned** — devcontainer and managed instance already use it.
- **Cost / availability** — MariaDB is standard on inexpensive VPS hosting,
  aligning with ACL's affordability and Africa-first goals.
- **Portability** — Eloquent and the schema builder abstract most engine
  differences, so migrations stay portable.

## Consequences

- Positive: single engine, realistic local testing, simpler deployment docs,
  migrations validated against the engine that actually runs in production.
- Negative: local development now requires a running MariaDB (no zero-config
  SQLite fallback).
- Migrations must use the portable Laravel schema builder and avoid
  engine-specific raw SQL (Postgres-, SQLite-, or MariaDB-only) unless a
  documented need justifies it.
- ADR-0002 is superseded; README, PROJECT_STATUS, and
  `.ai/guidelines/ACL.md` are updated to state MariaDB.

## Security Implications

- **Least privilege**: the application must NOT connect as `root`. Local and
  deployed environments use a scoped user (`acl_user`) granted only the
  privileges ACL needs. The prior `.env` connected as `root` over the public
  internet; that is replaced by a scoped local user for development.
- **Transport security**: any remote/managed MariaDB connection uses TLS via
  `MYSQL_ATTR_SSL_CA`. `DB_URL` silently drops `?ssl-mode`, so TLS must be
  configured with `MYSQL_ATTR_SSL_CA`, not the URL.
- **Secrets**: database credentials live only in `.env` (gitignored) and are
  never committed. If the previously-used managed `root` password was ever
  shared outside the developer's machine, it should be rotated.

## Future Considerations

- Revisit only if a concrete workload needs PostgreSQL-specific features
  (e.g. advanced JSON indexing, GIS, or richer full-text search). Any such
  change requires a new ADR superseding this one.
- A managed MariaDB is acceptable for staging/production provided it uses
  least-privilege users and TLS.
