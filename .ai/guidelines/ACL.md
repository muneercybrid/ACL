# ACL Engineering Constitution

This file is the short form, loaded automatically by assistant tooling. Its expanded
form is `docs/ACL_DEVELOPMENT_CONSTITUTION.md`, which states the same principles with
the file paths, ADR references and enforcement gates that make them checkable, and
`docs/agent-system/` describes the agents that enforce them. Where the two differ in
detail, the expanded form is authoritative; where they differ in principle, that is a
bug in one of them and belongs in a single commit that fixes both.

## Project

ACL — Anyone Can Learn

ACL is a large-scale educational ecosystem designed to support universities, students, tutors, professional learners, administrators and educational partners.

## Core Engineering Principles

### 1. Modular Monolith First

ACL will initially be implemented as a modular monolith using Laravel.

Modules must have clear boundaries and responsibilities.

The architecture must permit future extraction of services where justified.

### 2. Database

MariaDB is the standard relational database across all environments — local,
staging, and production (see ADR-0005).

Local development uses a least-privilege MariaDB user, never root.

Database access must use Laravel's database abstraction and Eloquent where
appropriate, and migrations must stay portable (no engine-specific raw SQL
without a documented justification).

### 3. Security First

Security is a first-class requirement.

Never:

- commit secrets
- expose credentials
- trust client-side authorization
- bypass authorization checks
- store passwords in plaintext
- expose sensitive information through logs
- disable security controls merely to simplify development

### 4. Authorization

Authorization must be enforced server-side.

Roles and permissions must not be represented solely through frontend state.

### 5. Multi-Tenancy

University and institutional boundaries must be treated as first-class architectural concerns.

Tenant isolation must be enforced at the application and database-access layers where appropriate.

### 6. Code Quality

Prefer:

- small cohesive classes
- explicit dependencies
- meaningful names
- typed PHP
- reusable domain services
- policies for authorization
- Form Requests for validation
- events for decoupled workflows
- jobs for asynchronous work

Avoid:

- giant controllers
- duplicated business logic
- hidden global state
- unnecessary abstractions
- premature microservices

### 7. Database Changes

Every schema change must use a migration.

Never manually modify production schema without an equivalent migration.

### 8. Testing

Important business rules require automated tests.

Security-sensitive functionality requires tests.

Critical workflows should have feature/integration coverage.

### 9. Documentation

Major architectural decisions must be documented.

Architecture Decision Records belong in `docs/adr/`.

### 10. AI-Assisted Development

AI-generated code is not automatically trusted.

Generated code must be:

- reviewed
- tested
- security-checked
- consistent with the architecture
- documented when architecturally significant

### 11. Dependency Discipline

Do not install packages merely because they are convenient.

Every major dependency should have a clear purpose.

### 12. Production Readiness

Development shortcuts must not silently become production architecture.

Every temporary implementation must have a clear migration path when necessary.
