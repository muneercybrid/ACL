# ACL Database Architecture

## Development Database

SQLite is currently used during the initial development phase.

This provides a zero-configuration database for GitHub Codespaces and
early application development.

## Production Database

PostgreSQL is the planned production database.

The production schema must therefore avoid unnecessary SQLite-specific
assumptions.

## Database Principles

- Every schema change uses a Laravel migration.
- Foreign keys must be used where appropriate.
- Important business invariants must be enforced at the database level
  where practical.
- UUID/ULID strategy must be decided before large-scale schema creation.
- Soft deletion must only be used where business semantics justify it.
- Sensitive information must not be stored unnecessarily.
- Passwords and authentication secrets must never be stored in plaintext.
- Audit-sensitive records should have appropriate immutable history.

## Planned Database Domains

### Identity

Users, credentials, sessions, authentication-related records.

### Organizations

Universities, faculties, departments, organizations and tenancy.

### Academic

Programs, courses, semesters, levels, academic sessions and curriculum.

### Learning

Lessons, modules, learning resources, progress and completion.

### Assessment

Questions, question banks, assessments, attempts, grading and results.

### Commerce

Products, enrollments, orders, payments and entitlements.

### Community

Forums, discussions, comments, reactions and moderation.

### AI

AI providers, models, usage records, requests, responses and quotas.

### Security

Audit logs, security events, authentication events and administrative
actions.

## Migration Strategy

The default Laravel migrations are considered the framework foundation.

ACL domain migrations will be introduced incrementally.

Do not create hundreds of tables before their domain requirements have
been established.

