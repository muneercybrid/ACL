# ACL Project Status & Handoff

_Last updated: 2026-09-02. Maintain this file at every milestone._

## 1. Repository
- GitHub: muneercybrid/ACL, branch: main. Remote is configured
  (`git@github.com:muneercybrid/ACL.git`) but **no commits have been pushed
  yet** — the first commit is intentionally held until the test suite runs
  green locally (see §9).
- App root = repository root.
- Development runs in a **GitHub Codespace** for `muneercybrid/ACL`, driven
  from the local Windows laptop over `gh codespace ssh` (decided 2026-09-02).
  The laptop is the editing interface and the source of truth for code; the
  codespace is the runtime where migrations, seeders, and tests execute
  (PHP 8.4 + MariaDB + Node are provisioned there by `.devcontainer/`). Code
  reaches the codespace via git (push here → pull/reset there); `.env` is
  never pushed — the codespace generates its own via `post-create.sh`.

## 2. Stack (current)
- Laravel 13.17+, PHP 8.4, Node 20 (devcontainer), Vite 8, Tailwind v4,
  Alpine.js 3.
- Database: **MariaDB across all environments (ADR-0005)**. This supersedes
  the earlier SQLite → PostgreSQL plan (ADR-0002).
- Cache / queue / session: Laravel database drivers. Redis later, only when
  justified.

## 3. Implemented in code (local verification pending — see §9)
- Laravel 13 foundation; default tables (users, cache, jobs, sessions).
- `.devcontainer/`; `.ai/guidelines/ACL.md` (engineering constitution).
- `docs/` skeleton; ADR-0001..0005.
- **Slice 1** — Organizations & academic hierarchy (organizations,
  organization_memberships, faculties, departments, academic_programs).
- **Slice 2** — RBAC (permissions, roles, role_permissions, role_assignments)
  with a scoped design.
- **Slice 3** — academic_sessions, semesters, levels, courses,
  course_offerings, course_offering_targets.
- **Slice 4** — enrollments + entitlements (`EntitlementService`,
  `SyncInstitutionalEnrollments` command).
- **Content** — chapters, lessons, lesson_blocks, lesson_progress + course
  viewer (`CourseViewerController`).
- **Auth** — session-based login (`LoginController`, `LoginRequest`).
- **Authorization** — `CourseOfferingPolicy`, `LessonPolicy`.
- **Dev data** — `DevelopmentSeeder`, `RbacSeeder`.
- **Tests present** (not yet executed locally): Auth, RBAC scoping, course
  access, institutional entitlement.

## 4. Key Decisions (do not revisit without a new ADR)
- Modular monolith; API-ready; no microservices yet (ADR-0001).
- MariaDB everywhere (ADR-0005); write portable migrations.
- Models in `app/Models`; domain folders only when populated (ADR-0003).
- AI is optional/pluggable; never a hard dependency.
- No bulk schema generation: schema follows the domain model, slice by slice.

## 5. Current Phase
Phase 1 (foundation) is largely implemented in code. Immediate focus:
push the codebase to the repo, bootstrap the codespace, and verify
migrations + `php artisan test` **in the codespace**, then continue with
Slice 5.

## 6. Next Steps (in order)
1. First commit + push to muneercybrid/ACL (clean initial history).
2. Codespace verification: `migrate`, `db:seed`, `php artisan test`
   (the toolchain is provisioned in the codespace).
3. UI pass (dashboard + course viewer) per ADR-0004 design language.
4. Slice 5: authentication hardening (2FA for staff) + audit-logging
   foundation.
5. Then: assessments, then certificates.

## 7. Rules for Any AI Agent Continuing This Project
1. Read this file, `.ai/guidelines/ACL.md`, and `docs/adr/` BEFORE changing
   architecture.
2. Never run destructive commands (`rm -rf`, `migrate:fresh`, reset) without
   explicit approval.
3. One slice at a time; verify with `php artisan migrate:status` and
   `php artisan test`.
4. Commit after each verified slice with a descriptive message.
5. Do not install packages or create tables outside the current slice.
6. Flag ambiguity instead of inventing requirements.

## 8. Credentials & accounts
- DB credentials live only in `.env` (gitignored). Local dev uses a
  least-privilege `acl_user` (not root) per ADR-0005.
- Dev login accounts are created by `DevelopmentSeeder` and documented in
  `docs/developer/DEV_ACCOUNTS.md`. They must never appear in any UI or in
  production.

## 9. Verification status (be honest about this)
- The test suite has **not** been executed yet. "Implemented in code" is not
  the same as "verified". It is being run in the codespace now (PHP 8.4 +
  MariaDB are present there). Do not claim anything is green until the suite
  actually passes; record the result here once it does.

## 10. Known Non-Issues
- "Xdebug: Could not connect to debugging client" — harmless warning; ignore.
