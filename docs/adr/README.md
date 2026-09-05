# Architecture Decision Records (ADRs)

This directory records significant architectural decisions for ACL. Each ADR
is immutable once accepted: to change a decision, add a **new** ADR that
supersedes the old one rather than rewriting history.

New ADRs follow this structure: Title, Status, Date, Context, Problem,
Options Considered, Decision, Reasoning, Consequences, Security Implications,
Future Considerations.

| ADR | Title | Status |
|---|---|---|
| [0001](0001-modular-monolith.md) | Modular monolith | Accepted |
| [0002](0002-database-strategy.md) | Database strategy (SQLite → PostgreSQL) | Superseded by 0005 |
| [0003](0003-application-layout.md) | Application layout | Accepted |
| [0004](0004-frontend-stack-and-design-language.md) | Frontend stack & design language | Accepted |
| [0005](0005-adopt-mariadb.md) | Adopt MariaDB across all environments | Accepted |
| [0006](0006-deploy-on-render-with-docker.md) | Deploy as a container on Render, MariaDB supplied externally | Accepted |
| [0007](0007-tidb-cloud-dev-runtime.md) | TiDB Cloud Serverless as the development runtime database | Accepted (amends 0005 for dev) |
| [0008](0008-redis-in-development.md) | Redis for sessions, cache & queue in development | Accepted (amends 0006 for dev) |
