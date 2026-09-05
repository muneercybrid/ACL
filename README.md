# ACL — Anyone Can Learn

A learning platform for Nigerian universities, built so that a student's course
access follows from their **institutional record** — programme, level, semester —
rather than from a shopping cart.

> **Status: early. Working skeleton, not a product.**
> Login, a student dashboard, a course viewer, lesson completion, scoped RBAC and
> institutional entitlement all work. All of it is covered by tests except the
> dashboard, which the suite only proves redirects a guest to `/login` — the
> listing logic behind it never executes. Everything else in
> [docs/VISION.md](docs/VISION.md) is intent, not code. This README describes only
> what is actually in the repository.

- **Stack** — Laravel 13.25 · PHP 8.4 · MariaDB 10.11 · Redis 7 (dev) · Blade + Tailwind v4 + Alpine 3 · Vite 8
- **Tests** — 39 feature tests, 67 assertions, all passing (`php artisan test`)
- **Architecture decisions** — [docs/adr/](docs/adr/)
- **Detailed state** — [docs/PROJECT_STATUS.md](docs/PROJECT_STATUS.md)

---

## Quick start (GitHub Codespaces or VS Code Dev Containers)

The repository ships a devcontainer that installs and configures everything —
MariaDB, Redis and Mailpit included — with no manual steps.

1. Open the repo in a Codespace, or locally: **Dev Containers: Reopen in Container**.
2. Wait for setup to finish (~2 min on a warm image).
3. Start the app:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Open the forwarded port 8000. You should land on the sign-in page.

Sign in with a development account from
[docs/developer/DEV_ACCOUNTS.md](docs/developer/DEV_ACCOUNTS.md) — those accounts
exist only in the development seeder and are never rendered in the UI.

For server + queue worker + log tailer + Vite in one process group:

```bash
composer run dev
```

### What the devcontainer does for you

| Stage | Script | Runs |
|---|---|---|
| Image build | [`.devcontainer/Dockerfile`](.devcontainer/Dockerfile) | Compiles the `pdo_mysql` PHP extension (the base image ships without it) |
| `onCreateCommand` | [`install-services.sh`](.devcontainer/install-services.sh) | Installs MariaDB and Redis servers plus Mailpit; masks the distro's password-less Redis; verifies every required PHP extension |
| `postCreateCommand` | [`post-create.sh`](.devcontainer/post-create.sh) | Creates schemas + least-privilege DB user, `composer install`, `npm install`, writes `.env` (TiDB when its secrets are present, else local MariaDB; Redis + Mailpit always), migrates, seeds, `npm run build` |
| `postStartCommand` | [`start-services.sh`](.devcontainer/start-services.sh) | Starts MariaDB, Redis and Mailpit and **waits until each accepts connections** — on every container start, so they are all up again after a stop/resume |

All four are idempotent: re-running them on an existing container changes nothing
it does not need to change.

The fixed development values are declared **twice**, deliberately.
[`devcontainer.json`](.devcontainer/devcontainer.json)'s `containerEnv` exports
them into the container, and [`config.sh`](.devcontainer/config.sh) repeats them
as `${VAR:-default}` fallbacks so every script still works when you run it by hand
outside Codespaces. Because `containerEnv` produces real environment variables,
**the exported values win** — `config.sh` is the fallback, not the override.
Change a credential in one and you must change it in the other.

### Development credentials

Deliberately fixed and deliberately public. They unlock MariaDB, Redis and
Mailpit instances that exist only inside the container and are never reachable
from the internet.

| | |
|---|---|
| Database | `acl` (tests use `acl_test`) |
| DB user | `acl_user` / `acl_password` on `127.0.0.1:3306` |
| Redis | password `acl_redis_password` on `127.0.0.1:6379` — db0 sessions/queue, db1 cache |
| Mailpit | SMTP `acl` / `acl_mail_password` on `:1025`; inbox UI on `:8025` |

TiDB Cloud, the optional dev *runtime* database ([ADR-0007](docs/adr/0007-tidb-cloud-dev-runtime.md)),
is the exception: its credentials are real secrets, supplied as `ACL_TIDB_*`
Codespaces secrets and never committed. Production credentials likewise live in
the deployment environment and are never committed. `.env` is git-ignored;
[`.env.example`](.env.example) is the tracked template.

`mysql acl` opens a shell with no flags — `post-create.sh` writes a `0600`
`~/.my.cnf` so passwords never appear in `ps` or shell history.

---

## Why MariaDB and not MongoDB

The domain is a chain of foreign keys — organization → faculty → department →
programme → level → semester → course → offering → enrolment → entitlement — and
academic records need referential integrity enforced by the engine, not by
application code. On top of that, Laravel's `database` session, cache and queue
drivers — the portable default the project ships with, and what production runs —
are SQL-only, and Laravel has no first-party MongoDB support. Full reasoning:
[ADR-0005](docs/adr/0005-adopt-mariadb.md).

MongoDB stays on the table as an *addition* later, under its own ADR, for
genuinely document-shaped data such as AI transcripts or analytics events.

---

## What works today

**Authentication** — session-based sign-in, sign-out, validation, throttling.
Guests hitting an authenticated route are redirected to `/login`.

**Scoped RBAC** — permissions and roles are rows, not enum strings. A
`role_assignments` record can be platform-wide (no entity) or scoped to one
entity (`organization`, `faculty`, `department`, …). A department-scoped role does
not leak to a sibling department; 13 tests cover exactly that.

**Institutional entitlement** — [`EntitlementService`](app/Services/EntitlementService.php)
resolves the offerings a student may access from their active student membership
(programme + current level) against `course_offering_targets` inside active
semesters of active academic sessions. `syncInstitutionalEnrollments()` grants
free access idempotently — guarded by both `firstOrCreate` and a unique index.
Exposed as `php artisan acl:sync-enrollments` (`--user=` to limit it to one user).

**Course viewing** — the dashboard lists the signed-in user's enrolments; a
course page lists chapters and lessons; lessons render typed content blocks and
can be marked complete. Authorization goes through [`CourseOfferingPolicy`](app/Policies/CourseOfferingPolicy.php)
and [`LessonPolicy`](app/Policies/LessonPolicy.php): unenrolled students,
expired or withdrawn enrolments, draft lessons and cross-offering lesson IDs are
all rejected server-side.

### Routes

| Method | URI | Name |
|---|---|---|
| GET | `/` | → `dashboard` or `login` |
| GET / POST | `/login` | `login` |
| POST | `/logout` | `logout` |
| GET | `/dashboard` | `dashboard` |
| GET | `/courses/{courseOffering}` | `courses.show` |
| GET | `/courses/{courseOffering}/lessons/{lesson}` | `courses.lessons.show` |
| POST | `/lessons/{lesson}/complete` | `lessons.complete` |

### Data model

24 migrations, 28 tables — 8 Laravel defaults plus 20 ACL tables, in five groups:

- **Tenancy** — `organizations`, `faculties`, `departments`, `academic_programs`, `organization_memberships`
- **Authorization** — `permissions`, `roles`, `role_permissions`, `role_assignments`
- **Academic calendar** — `academic_sessions`, `semesters`, `levels`, `courses`, `course_offerings`, `course_offering_targets`
- **Access** — `enrollments`
- **Content** — `chapters`, `lessons`, `lesson_blocks`, `lesson_progress`

Field-level reference: [docs/database/DOMAIN_MODEL.md](docs/database/DOMAIN_MODEL.md).

## What does not exist yet

Named plainly so nobody goes looking: no registration or password reset, no
authoring UI (content is created by seeders), no payments or subscriptions, no
AI tutor, no REST/GraphQL API, no notifications and no email the app sends
(Mailpit is wired up in dev to *catch* mail, but nothing dispatches any yet), no
admin panel, no file/media uploads, no forums or messaging, no certificates, no
analytics, no CI pipeline. `tests/Unit` does not exist — the suite is feature
tests only, by design.

There **is** now a container image and a Render blueprint
([`Dockerfile`](Dockerfile), [`render.yaml`](render.yaml), ADR-0006). As of
2026-09-03 the image builds on Render and the container starts, but it has never
served a request: the first deploy stopped at the entrypoint's `APP_KEY` guard,
and no environment is live. Treat
[docs/deployment/README.md](docs/deployment/README.md) as the procedure, not as a
record of a running system.

---

## Common commands

```bash
php artisan test                    # 39 feature tests against the acl_test schema
php artisan migrate                 # apply pending migrations
php artisan migrate:status          # what has run
bash scripts/troubleshoot.sh        # diagnose a broken environment
npm run build                       # rebuild public/build (Blade @vite needs the manifest)
npm run dev                         # Vite with HMR
```

`php artisan db:seed` is **not** idempotent — `DevelopmentSeeder` uses `create()`,
so a second run collides on unique slugs. `post-create.sh` only seeds when the
`users` table is empty.

Tests run against MariaDB, not SQLite, so the suite exercises the same engine as
development ([`phpunit.xml`](phpunit.xml) pins them to `acl_test`).

## Layout

```
app/
  Http/Controllers/     3 controllers (auth, dashboard, course viewer)
  Models/               20 Eloquent models
  Policies/             CourseOfferingPolicy, LessonPolicy
  Services/             EntitlementService
  Console/Commands/     SyncInstitutionalEnrollments
database/
  migrations/           24 migrations
  seeders/              DatabaseSeeder, DevelopmentSeeder, RbacSeeder
resources/views/        5 Blade views
tests/Feature/          4 test classes, 39 tests
docs/                   ADRs, domain model, design system, project status
.ai/guidelines/ACL.md   engineering rules that changes must follow
.devcontainer/          the environment described above
```

## Contributing

Read [`.ai/guidelines/ACL.md`](.ai/guidelines/ACL.md) first — it is binding on
every change. In particular: authorization is enforced server-side and never in
Blade alone; migrations stay portable; new dependencies need justification; and
documentation must describe what exists, not what is planned.

Before opening a PR: `php artisan test` green, `./vendor/bin/pint` clean, no
secrets staged.

## License

Not yet chosen. Until one is added, no permissions are granted beyond viewing the
source.
