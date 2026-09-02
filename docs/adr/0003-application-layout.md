# ADR-0003: Application Layout Convention

## Status
Accepted

## Context
Empty domain folders (app/Domain/*) were created before any domain code existed,
while models were generated into Laravel's default app/Models. Two competing
layouts risk long-term drift and confuse AI agents and developers.

## Decision
1. Keep Laravel default conventions: app/Models, app/Policies, app/Enums,
   app/Http, database/migrations.
2. Domain/module directories are created ONLY when they contain real code
   (services, actions, events, jobs).
3. No empty scaffold directories are committed.
4. When a domain grows beyond ~5-8 cohesive classes, group it under
   app/Domain/<Name>/ (Models, Services, Policies) and move related classes
   in one deliberate refactor commit, updating namespaces and tests.

## Consequences
- Fewer namespace surprises; artisan generators keep working by default.
- Modularization happens when justified by actual code, not speculation.
