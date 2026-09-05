# ADR-0001: Use a Modular Monolith as the Initial ACL Architecture

## Status

Accepted

## Context

ACL is intended to become a large educational platform supporting multiple institutions, users, academic structures, learning systems, assessments, payments, community features, AI functionality and enterprise integrations.

Starting directly with microservices would introduce substantial operational complexity before the product has established stable domain boundaries.

## Decision

ACL will initially use a modular monolith implemented with Laravel.

The application will be internally modularized by domain.

Potential future modules include:

- Identity
- Institutions
- Academic Structure
- Learning
- Content
- Assessments
- Community
- Payments
- AI
- Notifications
- Analytics
- Administration
- Audit

The architecture must maintain clear boundaries so that individual domains can later be extracted into services if scale or organizational requirements justify doing so.

## Consequences

### Positive

- Faster development
- Simpler deployment
- Lower infrastructure requirements
- Easier local development
- Shared database transactions where appropriate
- Lower operational complexity
- Easier debugging

### Negative

- Requires discipline to maintain module boundaries
- Poor boundaries could create a monolithic codebase with high coupling
- Future extraction may require additional work

## Database

PostgreSQL is the target production relational database.

SQLite is permitted for early development and testing where appropriate.

## Review Trigger

This decision should be reconsidered when:

- a domain requires independent scaling
- deployment independence becomes necessary
- organizational boundaries require service ownership
- database load creates a clear isolation requirement
- asynchronous workloads justify independent infrastructure
