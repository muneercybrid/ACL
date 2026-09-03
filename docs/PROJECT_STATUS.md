# ACL Project Status & Handoff

_Last updated: 2026-09-03. Maintain this file at every milestone._

## 1. Repository
- GitHub: muneercybrid/ACL, branch: main, remote
  `https://github.com/muneercybrid/ACL.git`. **The code is pushed.** Three pull
  requests are merged — #1 (devcontainer image build + SSH), #2 (tracked Claude
  config), #3 (the ACL agent system) — plus the vendored Claude skills.
- App root = repository root.
- Development runs in a **GitHub Codespace** for `muneercybrid/ACL`. The Windows
  laptop is the editing interface; the codespace is the runtime where migrations,
  seeders and tests execute (PHP 8.4 + MariaDB + Node are provisioned there by
  `.devcontainer/`). Code reaches the codespace via git (push here → pull there);
  `.env` is never pushed — the codespace generates its own via `post-create.sh`.

## 2. Stack (current)
- Laravel 13.25, PHP 8.4, Node 24 (devcontainer `node` feature), Vite 8,
  Tailwind v4, Alpine.js 3.
- Database: **MariaDB across all environments (ADR-0005)**. This supersedes
  the earlier SQLite → PostgreSQL plan (ADR-0002).
- Cache / queue / session: Laravel database drivers. Redis later, only when
  justified.
- Deployment: a container image plus a Render blueprint exist
  (`Dockerfile`, `render.yaml`, ADR-0006). The image **builds on Render** and the
  container starts; it has **not yet served a request** — see §9.

## 3. Implemented in code (verified in the codespace — see §9)
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
- **Tests executed and green**: Auth, RBAC scoping, course access,
  institutional entitlement — 39 tests, 67 assertions.

## 4. Key Decisions (do not revisit without a new ADR)
- Modular monolith; API-ready; no microservices yet (ADR-0001).
- MariaDB everywhere (ADR-0005); write portable migrations.
- Models in `app/Models`; domain folders only when populated (ADR-0003).
- Deployed as a container ACL owns, on Render, with MariaDB supplied by a
  managed provider outside Render (ADR-0006). Render has no managed
  MySQL/MariaDB; ADR-0005 is unchanged, not reversed.
- AI is optional/pluggable; never a hard dependency.
- No bulk schema generation: schema follows the domain model, slice by slice.

## 5. Current Phase
Phase 1 (foundation) is implemented and verified in the codespace. Immediate
focus: the UI pass over the dashboard and course viewer per ADR-0004, then
Slice 5.

## 6. Next Steps (in order)
1. UI pass (dashboard + course viewer) per the ADR-0004 design language, and
   feature-test the dashboard listing that the suite currently does not reach.
2. Deploy: build the image, provision MariaDB, apply `render.yaml`, attach the
   Namecheap domain — procedure in `docs/deployment/README.md`.
3. A CI pipeline. There is still no `.github/` directory, so nothing runs the
   suite or Pint automatically.
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
- **Verified in the codespace on 2026-09-03.** `bash scripts/troubleshoot.sh`
  reported no failures across all four sections. `php artisan test` →
  **39 passed, 67 assertions, 4.85s**. `php artisan serve` came up on
  `0.0.0.0:8000` and answered a request.
- **Verified on Render, 2026-09-03:** the image **builds** — all stages complete,
  layers pushed. The container **starts**: `docker/entrypoint.sh` renders
  `/etc/nginx/conf.d/default.conf` from `$PORT` and `nginx -t` reports the
  configuration valid. The `APP_KEY` guard then refused to boot and the deploy
  exited 1, which is the guard working as designed on a service whose
  environment variables had not yet been set.
- **Still not verified:** anything past that guard. No database connection, no
  migration, no request served, no health check passed. php-fpm has never
  started. Do not describe the deployment path as working until a service has
  actually answered `/up`.
- The dashboard is exercised by only one assertion (a guest is redirected to
  `/login`). Its listing logic never executes in the suite.

## 10. Known Non-Issues
- "Xdebug: Could not connect to debugging client" — harmless warning; ignore.
