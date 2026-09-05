# ACL Architecture

## Project

ACL — Anyone Can Learn

ACL is a modular educational ecosystem designed to support university
students, external learners, tutors, lecturers, institutions, and
administrators.

## Architectural Style

ACL uses a modular monolith architecture built on Laravel.

The application is initially deployed as a single Laravel application,
while internal domains remain explicitly separated.

The architecture must allow individual domains to evolve independently
and eventually become services if scale requires it.

## Core Principles

1. Security by default.
2. Explicit authorization.
3. Tenant isolation.
4. Database integrity.
5. Domain separation.
6. Testability.
7. API readiness.
8. Auditability.
9. Observability.
10. Backward compatibility.
11. Minimal unnecessary infrastructure.
12. Prefer boring and reliable technology over unnecessary complexity.

## Initial Technology Stack

- Laravel 13
- PHP 8.4
- SQLite for initial local development
- PostgreSQL planned for production
- Blade / JavaScript for the initial frontend
- Vite
- Composer
- NPM
- GitHub
- GitHub Codespaces

## High-Level Domains

- Identity
- Organizations
- Academic Structure
- Users
- Roles & Permissions
- Courses
- Curriculum
- Learning
- Assessments
- Assignments
- Practical Labs
- Tutors
- Community
- Messaging
- Notifications
- Payments
- Certificates
- AI
- Analytics
- Audit & Security
- Administration

## Architectural Rule

Business logic must not be placed directly inside controllers when it
represents a reusable domain operation.

Controllers should coordinate HTTP requests and delegate business
operations to appropriate application/domain components.

## Database Rule

Database schema changes must be represented by Laravel migrations.

Production data must never depend on manually modifying the database.

## Security Rule

Every privileged operation must have an explicit authorization path.

Authentication alone is never considered authorization.

## Testing Rule

New business-critical functionality must include automated tests.

## Documentation Rule

Major architectural decisions must be recorded as ADRs under:

docs/adr/

