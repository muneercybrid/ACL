# ADR-0002: Database Strategy

## Status

Superseded by [ADR-0005](0005-adopt-mariadb.md) on 2026-09-02.

Originally: Accepted. The SQLite → PostgreSQL direction described below was
never implemented; the project standardized on MariaDB instead. This record
is retained for historical context — see ADR-0005 for the current decision.

## Context

ACL is intended to become a large educational platform supporting
multiple institutions, users, courses, learning activities,
assessments, commerce, AI workloads and analytics.

The development environment should remain inexpensive and easy to
reproduce.

## Decision

SQLite will be used during the initial development stage.

PostgreSQL will be the target production relational database.

The application must avoid unnecessary SQLite-specific behavior so that
migration to PostgreSQL remains straightforward.

## Rationale

SQLite provides:

- zero configuration
- fast startup
- excellent suitability for early development
- simple Codespace setup
- no separate database service

PostgreSQL provides the production capabilities required by ACL,
including stronger concurrency characteristics, richer indexing,
advanced relational features and better suitability for a large
multi-user platform.

## Consequences

Development can begin immediately without provisioning a database
server.

Before production deployment, the application will be tested against
PostgreSQL and the schema will be validated for PostgreSQL compatibility.

