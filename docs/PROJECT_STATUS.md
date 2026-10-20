# ACL Project Status & Handoff

_Last updated: 2026-10-20. Maintain this file at every milestone._

> **Monthly report:** [`docs/ACL_MONTHLY_REPORT_2026_10.md`](ACL_MONTHLY_REPORT_2026_10.md)
> (PDF: `docs/ACL_MONTHLY_REPORT_2026_10.pdf`) covers 20 Sep – 20 Oct 2026: 112 commits,
> 12 merged PRs, chapter coverage 6.8% → 100%.

## 0. Where the project stands (2026-10-20)
- **Curriculum coverage is complete.** All 5,928 catalogue courses now carry a full
  20-chapter set (minimum 20, maximum 39), 118,875 real chapters, all derived from the
  CCMAS national curriculum. **Zero placeholder chapters remain in the database.**
- **Chapter titles are done; chapter bodies are not.** `introduction`, `summary` and
  `key_takeaways` are still empty on generated chapters. The Start button degrades
  gracefully (title + note) so every chapter is usable, but the written material the
  specification calls for does not yet exist. This is the next material piece of work.
- **ACLi has a provider failover chain** (Token Harbor → Cloudflare → OmniRoute) with
  truncation recovery, quota backoff, and fail-fast on a dead-end 429.
- **Critical outstanding human action: rotate five provider credentials** exposed in
  chat. Then update `.env` and Doppler (`acl` / `prd`).

## 1. Repository
- GitHub: muneercybrid/ACL, branch: main, remote
  `https://github.com/muneercybrid/ACL.git`. **The code is pushed.** Twenty-one pull
  requests are merged (#1–#27), most recently #19–#27 (Google OAuth null password,
  student registration null organisation, concurrent registration, missing `courseRef`
  on enroll, ACLi chat feedback model, plus dependabot bumps).
- App root = repository root.
- Development runs in a **GitHub Codespace** for `muneercybrid/ACL`. The Windows
  laptop is the editing interface; the codespace is the runtime where migrations,
  seeders and tests execute (PHP 8.4 + MariaDB + Node are provisioned there by
  `.devcontainer/`). Code reaches the codespace via git (push here → pull there);
  `.env` is never pushed — the codespace generates its own via `post-create.sh`.

## 2. Stack (current)
- Laravel 13.25, PHP 8.4, Node 24 (devcontainer `node` feature), Vite 8,
  Tailwind v4, Alpine.js 3. The production EC2 host runs PHP 8.5 and Node 22
  (ADR-0011); migrations, seeders and the test suite still run in the codespace.
- Database: **MariaDB is the test-suite engine (ADR-0005)** — superseding the
  earlier SQLite → PostgreSQL plan (ADR-0002). The dev *runtime* is TiDB Cloud
  when its `ACL_TIDB_*` Codespaces secrets are present, else the local MariaDB
  (ADR-0007); the suite always runs on the local MariaDB. **Production runs on
  TiDB Cloud** (ADR-0011), with a credential rotated distinct from dev's.
- Cache / queue / session: Laravel `database` drivers in a plain clone; the
  devcontainer overrides all three to Redis via the pure-PHP `predis` client
  (ADR-0008), and the **production EC2 runs the same three on its native Redis**
  (ADR-0011).
- Serving: ACL is served in production from a **dedicated AWS EC2 instance**
  (Ubuntu 26.04, PHP 8.5 native) through a **remotely-managed Cloudflare Tunnel**
  at `app.aclacademy.me` (ADR-0011, superseding 0009/0010); the Codespace is
  development-only and runs no tunnel. Procedure in
  `docs/deployment/DOMAIN_SETUP.md`. The container image (`Dockerfile`, `docker/`,
  ADR-0006) is **retained as a portable fallback**, not the serving path;
  `render.yaml` was removed.

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
- MariaDB is the test-suite engine (ADR-0005); TiDB Cloud is the dev runtime
  (ADR-0007) and, per ADR-0011, the production database too; write portable
  migrations either way.
- Dev sessions/cache/queue run on Redis via `predis` (ADR-0008); the production
  EC2 runs the same three on its native Redis (ADR-0011).
- Models in `app/Models`; domain folders only when populated (ADR-0003).
- Served in production from a dedicated AWS EC2 instance via a remotely-managed
  Cloudflare Tunnel (ADR-0011, superseding ADR-0009's serve-from-Codespace and
  ADR-0010's locally-managed tunnel; ADR-0009 had superseded ADR-0006's Render
  decision). The container image ACL owns is retained as a portable fallback.
- Secrets have one source of truth: Doppler, with per-environment `dev`/`prd`
  configs feeding the environment; the app still reads only from `config/` and no
  secret enters the repo (ADR-0012). Decided this cycle; wiring is a pending
  Codespace/EC2 step (see §9).
- Production error tracking and external uptime monitoring use Honeybadger, keyed
  from Doppler and enabled in production only (ADR-0013). Decided this cycle;
  wiring is a pending Codespace/EC2 step (see §9).
- AI is optional/pluggable; never a hard dependency.
- No bulk schema generation: schema follows the domain model, slice by slice.

## 5. Current Phase
The national-curriculum content phase is in progress: chapter titles are complete for
5,909 of 5,928 courses, and chapter bodies (Phase 2) are the next deliverable.
Registration and the student dashboard are implemented and served in production.
Immediate focus, in order:

1. Write chapter bodies — introduction, summary, key takeaways, plus quizzes, exercises
   and flashcards — via the existing opt-in generation path.
2. Rotate the five exposed provider credentials (human action), then sync `.env` to
   Doppler.
3. Fix the five failing feature tests. `CcmcasCurriculumIntegrationTest` inserts an
   `academic_programs` row with `department_id => 1`, violating the foreign key to
   `departments`. Four `StudentRegistrationCompleteTest` cases insert without the NOT
   NULL `student_registration_verifications.jamb_registration_number`. Both sets are
   pre-existing, not introduced by this cycle — confirmed by running the two files
   with and without this cycle's changes and getting identical results.
4. Organisation selection cascade (state/federal/private → searchable institution →
   organisation login) — requested, never started.
5. Correct `docs/database/README.md` (still describes SQLite/PostgreSQL) and the
   onboarding prerequisites (PostgreSQL listed instead of MariaDB).

## 6. Next Steps (in order)
1. Chapter bodies (Phase 2) across the catalogue.
2. Rotate exposed provider credentials; re-sync Doppler.
3. Organisation selection cascade for registration.
4. Fix the five failing feature tests (`CcmcasCurriculumIntegrationTest`,
   `StudentRegistrationCompleteTest`).
5. Generate assessment content — lesson blocks are currently zero.
6. Production hardening follow-ups from ADR-0011: a least-privilege TiDB production
   user (not the current broad `…root` grant); a Content-Security-Policy header; and
   host/infra metrics beyond Honeybadger's app-error + uptime slice.
7. A CI pipeline. There is still no `.github/` directory, so nothing runs the suite
   or Pint automatically — and, per ADR-0011, nothing gates what reaches the EC2.
8. Then: certificates, and 2FA/audit-logging foundations for staff.

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
- **Verified in production — 2026-10-20.** Chapter coverage measured against the
  production database by grouping chapters per course: **all 5,928 courses at a full
  20 chapters (100%), 118,875 real chapters, zero placeholders remaining.** The
  provider failover chain was exercised end-to-end (dead primary failing in 0.51s,
  chain falling through to the next provider). The chapter start button was exercised
  against a chapter with empty `introduction`, `summary` and `key_takeaways`. The
  contact form delivered a test message through the real Resend SMTP path.
  `https://app.aclacademy.me/`, `/login`, `/contact` and `/superadmin/login` all answer
  200.
- **Syntax verified only — 2026-10-16.** The nine PRs merged from remote (#19–#27) were
  pulled and the application routes re-checked afterwards. **The full test suite was not
  executed in that session**, so the suite result below still stands from 2026-09-03 and
  does not cover those merges. Re-run it before relying on it.
- **Verified in the codespace on 2026-09-03.** `bash scripts/troubleshoot.sh`
  reported no failures across all four sections. `php artisan test` →
  **39 passed, 67 assertions, 4.85s**. `php artisan serve` came up on
  `0.0.0.0:8000` and answered a request.
- **Decisions only, not implemented — 2026-09-05.** ADR-0012 (Doppler as the
  secrets source of truth) and ADR-0013 (Honeybadger error + uptime monitoring)
  were authored and accepted this turn. **Neither is wired:** no `composer`
  package was added (`composer.json`/`composer.lock` unchanged), no
  `config/honeybadger.php`, no `bootstrap/app.php` `withExceptions` callback, no
  Doppler CLI in the devcontainer or on the EC2. The laptop cannot run
  `composer`, `honeybadger:install` or the suite, so implementation and its
  verification (including the required spy-transport filtering test) are a
  Codespace/EC2 step. The 39-passing suite result below is unaffected and was
  **not** re-run.
- **Not yet verified — this hardening cycle (2026-09-05):** ADR-0007 (TiDB dev
  runtime) and ADR-0008 (Redis for dev sessions/cache/queue) landed with their
  devcontainer wiring — `install-services.sh` installing Redis + Mailpit,
  `start-services.sh` starting all three, `post-create.sh` selecting TiDB or the
  local MariaDB and writing the Redis/Mail keys, and `predis` added to
  `composer.json`. **None of this has been exercised in a fresh or rebuilt
  codespace yet, and the suite has not been re-run since.** Treat the
  three-service startup as designed-but-unproven until `start-services.sh` has
  brought MariaDB, Redis and Mailpit all up and `php artisan test` has been
  re-run in the codespace and its output recorded here. The 39-passing result
  above predates this change and does not cover it.
- **Observed during the Render build attempt, 2026-09-03** (Render is no longer
  ACL's host — ADR-0009 — but this stays the record of what was proven about the
  now-retained container image): the image **builds** — all stages complete,
  layers pushed. The container **starts**: `docker/entrypoint.sh` renders
  `/etc/nginx/conf.d/default.conf` from `$PORT` and `nginx -t` reports the
  configuration valid. The `APP_KEY` guard then refused to boot and the deploy
  exited 1, which is the guard working as designed on a service whose
  environment variables had not yet been set.
- **Still not verified (container image):** anything past that guard. No database
  connection, no migration, no request served, no health check passed. php-fpm has
  never started. Do not describe the container-image serving path as working until a
  container has actually answered `/up`.
- **Verified live (production serving path), 2026-09-05.** The EC2 + remotely-
  managed Cloudflare Tunnel path (ADR-0011, `docs/deployment/DOMAIN_SETUP.md`) is
  stood up and reachable end-to-end: `https://app.aclacademy.me/up` → 200,
  `/login` → 200, `/` → 302, through Cloudflare to nginx (`127.0.0.1:8080`) →
  php8.5-fpm (unix socket) → Laravel → TiDB (TLS :4000) + redis. This was an
  infra/config deploy with **no application-code change**, so it does not alter
  the suite result above and the suite was **not** re-run for it. **Not yet
  drilled:** reboot recovery of the systemd units (nginx, php-fpm, redis,
  cloudflared). **Still no CI** gating what reaches the box.
- The dashboard is exercised by only one assertion (a guest is redirected to
  `/login`). Its listing logic never executes in the suite.

## 10. Known Non-Issues
- "Xdebug: Could not connect to debugging client" — harmless warning; ignore.

## 11. Audit finding — programmes (unfixed, documented, not blocking)
- Schema (`programmes` 2026-09-11): has `organization_id` (nullable FK), no `slug` column.
- Model (`App\Models\Curriculum\Programme`): `$fillable` includes `slug` (no column → silent discard / error) and omits `organization_id` (present column → unfillable via mass assignment).
- Action deferred: requires a migration (add `slug` OR remove from $fillable; add `organization_id` to $fillable) approved explicitly; kept out of this pass to avoid unverified schema change on TiDB production.
- Related: `course_chapters` placeholders complete (140,064 draft), level co-ordinators seeded (1,666), student-registration flow verified (5 tests, isolated pass; full-suite pollution tracked to RefreshDatabase cross-test slugs), contact form delivers to the address in `MAIL_CONTACT_RECIPIENT` (currently `offaclacademy@gmail.com`), email diagnosis complete (SPF/DKIM/port 25 documented, not fixed — needs Cloudflare + AWS SG changes).
