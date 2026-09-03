# Engineering Agents

The agents that write and review ACL's code: backend, database, frontend, and the
disciplines that keep a diff honest. Twenty-one are loaded; three are ACL's own.

Every agent here is project-local in `.claude/agents/`. `ACL *` agents outrank their
generic equivalents on ACL specifics. *One writer per file* is not negotiable — if two
agents need the same file, they are sequenced.

**Verified state of the code as of 2026-09-03** (re-check before relying on it): 3
controllers plus a base, 1 Form Request (`Auth/LoginRequest`), 20 models, 2 policies
(`CourseOfferingPolicy`, `LessonPolicy`), 1 service (`EntitlementService`), 1 console
command (`SyncInstitutionalEnrollments`), 24 migrations, 5 Blade views, 7 routes. No
jobs, no events, no API, no uploads.

---

## Backend

### ACL Backend Architect
`acl-backend-architect.md` · ACL

**Invoke when** anything under `app/` or `routes/` changes, and for any review of request
validation, authorization wiring or service design.
**Reviews** whether validation lives in a Form Request rather than the controller,
whether every privileged path has a policy, whether business logic sits in a service
instead of a controller, whether Eloquent relationships and casts match the migration.
**Produces** the controller, Form Request, policy, service, model or command — plus the
route registration and the name of the test that must now exist.
**Consult** `app/Services/EntitlementService.php` (the reference for service shape),
`app/Policies/`, `app/Http/Requests/Auth/LoginRequest.php`, `.ai/guidelines/ACL.md`,
[`ACL_DEVELOPMENT_CONSTITUTION.md`](../../ACL_DEVELOPMENT_CONSTITUTION.md) §2 and §9.
**Hands to** `ACL Test Strategist` (always), `ACL Security Architect` (anything
authorization-shaped), `ACL Database Architect` (if the schema must change first).
**Defers on** schema shape and trust-boundary sign-off.

### Backend Architect
`engineering-backend-architect.md` · SPECIALIST

**Invoke when** you want general Laravel or server-side depth — queue design, caching
strategy, service decomposition — behind an ACL implementation.
**Reviews** the design in general terms, with no knowledge of ACL's conventions.
**Produces** advice only.
**Consult** the same files the ACL agent is reading.
**Hands to** `ACL Backend Architect`, which decides. Watch for advice that assumes Redis
or a non-database queue driver: ACL uses Laravel's **database** drivers for session,
cache and queue, and changing that needs an ADR.

### Code Reviewer
`engineering-code-reviewer.md` · CORE

**Invoke when** a change is written and you want a second reading focused on correctness,
maintainability, security and performance rather than style.
**Reviews** the diff. Style is Pint's job, not a reviewer's.
**Produces** actionable findings with file and line.
**Consult** the diff, then the files around it.
**Hands to** whoever wrote the code. It does not edit.

### Minimal Change Engineer
`engineering-minimal-change-engineer.md` · CORE

**Invoke when** fixing a bug, and whenever a diff is growing past the problem it was
meant to solve.
**Reviews** scope. It will take three similar lines over a premature abstraction, and it
refuses to refactor inside a bug fix.
**Produces** the smallest diff that fixes the reported behaviour, and a separate list of
anything adjacent it deliberately left alone.
**Consult** the failing test or reproduction first.
**Hands to** `ACL Test Strategist` for the regression test that must accompany the fix.

### Git Workflow Master
`engineering-git-workflow-master.md` · CORE

**Invoke when** branching, splitting a large change into reviewable commits, writing
commit messages, or shaping a PR.
**Reviews** commit grouping, message quality, branch state against `main`.
**Produces** a commit sequence and a PR description.
**Consult** `git log` for the repository's existing message style — lowercase scoped
subjects that say what changed and why.
**Hands to** nobody. It never runs a destructive Git command without approval in the
current conversation: no force push, no `reset --hard`, no `clean -f`, no branch
deletion.

### Codebase Onboarding Engineer
`engineering-codebase-onboarding-engineer.md` · CORE

**Invoke when** someone needs to understand unfamiliar ACL code, or when a task starts
with "where is …".
**Reviews** source, and states only what the source says.
**Produces** a traced code path with file and line references.
**Consult** the code before any document — several `docs/*/README.md` files are empty and
two describe a database ACL no longer uses.
**Hands to** the domain owner once the ground truth is established.

### Codebase Archaeologist
`specialized-codebase-archaeologist.md` · SPECIALIST

**Invoke when** you suspect drift from many AI-tool sessions: dead code, two
implementations of one idea, documentation that describes a previous design.
**Reviews** the repository as a whole for silent divergence.
**Produces** an inventory of contradictions, ranked, with evidence.
**Consult** `docs/PROJECT_STATUS.md` and `README.md` — the two documents most likely to
have aged.
**Hands to** `ACL Docs Steward` for documentation contradictions, the relevant domain
owner for code ones. It reports; it does not fix other people's files.

### Developer Tooling Engineer
`engineering-developer-tooling-engineer.md` · SPECIALIST

**Invoke when** changing anything under `scripts/` or `.devcontainer/`, or adding an
Artisan command meant for humans.
**Reviews** command naming, error messages, exit codes, cross-platform behaviour.
**Produces** scripts in pure bash matching `scripts/troubleshoot.sh` and
`.devcontainer/*.sh`. **No `jq` and no `php` on the laptop** — a script that needs either
works only in the codespace and must say so.
**Consult** `scripts/`, `.devcontainer/`, `composer.json` scripts.
**Hands to** `DevOps Automator` for anything that runs in CI.

## Database

### ACL Database Architect
`acl-database-architect.md` · ACL

**Invoke when** a migration is created or changed, or when keys, indexes or constraints
are in question.
**Reviews** column types, foreign keys, cascade behaviour, uniqueness as a security
control, index names against MariaDB's 64-character limit, and whether `down()` is real.
**Produces** the migration, following the conventions the existing ones already
establish: `$table->id()`; `foreignId('x_id')->constrained()->cascadeOnDelete()`;
status and type columns as `string` with an inline `// a|b|c` comment rather than a MySQL
ENUM; **`dateTime` not `timestamp`**, because MariaDB's TIMESTAMP cannot exceed
2038-01-19 and fails with error 1292 under strict mode.
**Consult** `docs/adr/0005-*` (MariaDB everywhere, superseding 0002),
`docs/database/DOMAIN_MODEL.md`, and the two reference migrations —
`*_create_enrollments_table.php` and `*_create_role_assignments_table.php`.
**Do not consult** `docs/database/README.md`: it still says SQLite is in use and
PostgreSQL is planned. Both claims are dead.
**Hands to** `ACL Backend Architect` (models and casts), `ACL Test Strategist` (a test
proving the constraint refuses the bad row), `ACL Docs Steward` (`DOMAIN_MODEL.md` and
the README counts).
**Refuses** editing a migration that has already run, and `migrate:fresh`, `db:wipe` or
`migrate:rollback` on seeded data without approval in the current conversation.

### Database Optimizer
`engineering-database-optimizer.md` · SPECIALIST

**Invoke when** a query is slow, an index is missing, or an N+1 appears.
**Reviews** query plans, index selectivity, eager-loading.
**Produces** a diagnosis with the measurement that supports it, and a proposed index or
query change.
**Consult** the migrations for what indexes already exist.
**Hands to** `ACL Database Architect`, which owns the migration that adds one. Measure in
the codespace; the laptop has no MariaDB, so an unmeasured optimisation is a guess.

### Database Reliability Engineer
`engineering-database-reliability-engineer.md` · SPECIALIST

**Invoke when** backups, replication, failover, connection pooling or an online schema
change are the subject. **None of this exists** — there is no production environment.
**Reviews** availability and recovery design.
**Produces** an ADR before anything else.
**Consult** `docs/adr/0005-*`, `docs/deployment/README.md` (empty placeholder).
**Hands to** `ACL Software Architect` and `SRE`.

### Data Engineer
`engineering-data-engineer.md` · SPECIALIST

**Invoke when** ACL needs pipelines, warehousing or analytics-ready extracts. Nothing of
the kind exists today.
**Reviews** pipeline design, idempotency, schema evolution.
**Produces** an ADR first. ACL's only batch process today is
`php artisan acl:sync-enrollments`, which is idempotent through `firstOrCreate` **and** a
unique index — that belt-and-braces pattern is the standard to match.
**Consult** `app/Console/Commands/SyncInstitutionalEnrollments.php`.
**Hands to** `ACL Database Architect`, `Privacy Engineer` (student data leaving its
original table is a privacy decision).

## Frontend

### ACL Frontend Architect
`acl-frontend-architect.md` · ACL

**Invoke when** Blade, Tailwind, Alpine, Vite, design tokens or layout change.
**Reviews** token use (no raw hex in Blade), hover/focus/active states on every
interactive element, dark-first defaults, keyboard reachability.
**Produces** Blade views and CSS. The five existing views are the reference:
`layouts/app.blade.php`, `layouts/auth.blade.php`, `auth/login.blade.php`,
`dashboard.blade.php`, `courses/show.blade.php`.
**Consult** `resources/css/app.css` for the tokens, `docs/ui-ux/DESIGN_SYSTEM.md` for
what they mean, `docs/adr/0004-*` for the stack decision. `docs/ui-ux/README.md` is an
empty placeholder.
**Hands to** `Accessibility Auditor`, `ACL Test Strategist`.
**Where authorization lives: not here.** Blade decides what is *shown*; a policy decides
what is *allowed*. Hiding a link is not access control.
**Note** Tailwind v4 is configured in CSS, not a JS config file, and `@vite` needs
`public/build` — a missing `npm run build` produces a 500, not an unstyled page.

### Frontend Developer
`engineering-frontend-developer.md` · SPECIALIST

**Invoke when** you want general frontend depth. Most of its React/Vue knowledge does not
apply: ACL is Blade with Alpine, and introducing a SPA framework would reverse ADR-0004.
**Reviews** component structure, performance, browser behaviour.
**Produces** advice.
**Hands to** `ACL Frontend Architect`, which decides.

### UI Designer
`design-ui-designer.md` · SPECIALIST

**Invoke when** a new component needs to exist and `DESIGN_SYSTEM.md` does not describe
it yet.
**Reviews** visual consistency, spacing rhythm, state coverage.
**Produces** a component spec that names the tokens it uses.
**Consult** `docs/ui-ux/DESIGN_SYSTEM.md` — abyss, surface, raised, edge, brand,
terminal, glow; Inter and JetBrains Mono; the TryHackMe/Hack The Box register.
**Hands to** `ACL Frontend Architect` to build, `ACL Docs Steward` to record the new
component.

### UX Architect
`design-ux-architect.md` · SPECIALIST

**Invoke when** a CSS system or interaction foundation needs designing rather than a
single component.
**Reviews** structure, tokens, responsive strategy.
**Produces** implementation guidance for the frontend architect.
**Consult** `resources/css/app.css`, `docs/ui-ux/DESIGN_SYSTEM.md`.
**Hands to** `ACL Frontend Architect`.

### UI Finish-Gate Reviewer
`design-ui-finish-gate-reviewer.md` · SPECIALIST

**Invoke when** a screen is "done" and you want to know whether it looks like ACL or like
any generic dashboard.
**Reviews** the interface against the written design contract and real product evidence.
**Produces** a pass/fail with specific, fixable observations.
**Consult** `docs/ui-ux/DESIGN_SYSTEM.md`.
**Hands to** `ACL Frontend Architect`.

### Internationalization Engineer
`engineering-i18n-engineer.md` · SPECIALIST

**Invoke when** ACL needs more than one language or locale-aware formatting. **Nothing is
localised today** — strings are inline in Blade and `lang/` is not in use.
**Reviews** string extraction, ICU MessageFormat, CLDR plural rules, RTL, date and
number formatting.
**Produces** an extraction plan and a locale strategy. Retrofitting is the expensive
version of this work, which is why the agent is loaded before it is needed.
**Hands to** `ACL Frontend Architect`, `ACL Docs Steward`.

### Data Visualization Engineer
`engineering-data-visualization-engineer.md` · SPECIALIST

**Invoke when** progress, cohort or assessment data is charted. No charts exist today.
**Reviews** chart-type choice against the question being asked, perceptual honesty,
colourblind-safe palettes, accessible alternatives to colour.
**Produces** a chart spec using ACL's tokens, plus the text equivalent.
**Consult** `docs/ui-ux/DESIGN_SYSTEM.md`.
**Hands to** `ACL Frontend Architect`, `Accessibility Auditor`, `Statistician` if the
chart makes a claim.

## Domains ACL does not have yet

### Payments & Billing Engineer
`engineering-payments-billing-engineer.md` · SPECIALIST

**Invoke when** payments are genuinely on the table. They are not: ACL's premise is that
**course access follows from an institutional record, not a purchase**, and
`EntitlementService` implements exactly that.
**Reviews** idempotency, webhook handling, SCA/3DS, reconciliation, PCI scope.
**Produces** an ADR that must first argue why payment does not contradict the premise.
**Hands to** `ACL Software Architect`, `ACL Security Architect`.

### Realtime Collaboration Engineer
`engineering-realtime-collaboration-engineer.md` · SPECIALIST

**Invoke when** live presence, collaborative editing or offline sync is designed. None
exists; there is no WebSocket layer and no broadcast driver configured.
**Reviews** transport choice, reconnect safety, conflict resolution, fan-out cost.
**Produces** an ADR. Realtime infrastructure is exactly the kind of "clever" that ACL's
constitution asks you to justify against "boring".
**Hands to** `ACL Software Architect`, `SRE`.

