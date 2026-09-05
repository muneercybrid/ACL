# ACL — Anyone Can Learn · Definitive Project Brief & Master Prompt

> **Snapshot: 2026-09-05.** This document is a point-in-time briefing meant to be
> handed to a person or an AI so they understand ACL completely before touching
> it. It deliberately separates **what exists today** from **what is intended**.
> The repository is always the final authority: if this brief and the code ever
> disagree, the code wins and this brief is stale. `INSPECT → VERIFY → IMPLEMENT`.

---

## 0. One-paragraph summary

ACL (**Anyone Can Learn**) is an open-source learning platform for Nigerian —
and more broadly African — universities, built to a premium engineering
standard on Laravel 13 / PHP 8.4. Its defining idea is that a student's access
to a course is **derived from their institutional record** (programme, level,
semester) rather than bought. A department enrols its students simply by
existing; entitlement is computed, never sold. Today ACL is an honest, tested
skeleton — authentication, scoped role-based access control, institutional
entitlement, a dashboard, a course viewer and lesson completion all work and are
covered by feature tests. Everything else is intent, recorded as such.

---

## 1. What the name means, and the thesis

- **"Anyone Can Learn"** is the mission, not decoration: remove the commercial
  and administrative friction that keeps a registered student from the material
  they are already entitled to.
- **The thesis — entitlement, not commerce.** Most learning platforms model
  access as a purchase: a cart, a price, a transaction. ACL rejects that for its
  core. Access follows a chain of institutional facts:

  `organization → faculty → department → programme → level → semester → course → offering → enrolment → entitlement`

  If a student belongs to a department, and that department's programme places a
  course in the student's current level and semester, the student is entitled to
  that course offering. No money changes hands to unlock learning.
- **Who it serves.** Nigerian university students first — which drives real
  constraints: affordability, low-bandwidth tolerance, cheap and ubiquitous
  hosting, and correctness of academic records above cleverness.

---

## 2. The quality standard ACL must reach

ACL is held to a **high, premium** engineering bar. "Premium" here does not mean
ornate UI; it means the following are non-negotiable and enforced:

- **Correctness over features.** A smaller system that is right beats a larger
  one that is plausible. Invariants live in the database (foreign keys, unique
  constraints), not only in application code.
- **Security-first, server-side.** Authorization is enforced on the server
  through policies; the UI decides only what is *shown*, never what is
  *allowed*. Capability lives in `roles` / `role_permissions` /
  `role_assignments`, never in a `role` column on `users`.
- **The repository is ground truth.** Documentation describes what exists, and
  changes in the same commit as the behaviour it documents. Intent goes to
  `docs/VISION.md`; accepted decisions are immutable ADRs.
- **Nothing is "done" until verified.** Tests are written and actually executed;
  migrations are actually applied; the suite's real output is reported. Claims
  are never made without evidence.
- **Honest engineering.** Failures are reported plainly. Skipped steps are named.
  No imaginary features, no green-without-running.
- **Accessibility and affordability** are treated as correctness, not polish.

---

## 3. Technology stack (as it actually runs today)

| Layer | Choice | Notes |
|---|---|---|
| Language | **PHP 8.4** | Strict, typed, modern. |
| Framework | **Laravel 13** | Modular monolith (ADR-0001). |
| Dev runtime DB | **TiDB Cloud Serverless** (MySQL 8.0 wire) | `DB_CONNECTION=mysql`, TLS on :4000 (ADR-0007). |
| Test & prod DB | **MariaDB** | Suite runs on local `acl_test`; prod is external MariaDB (ADR-0005, ADR-0006). |
| Runtime store | **Redis 7** via `predis` (pure-PHP) | Sessions, cache, queue in dev (ADR-0008). |
| Mail (dev) | **Mailpit** | Catches all outbound mail; SMTP :1025, inbox :8025. |
| Frontend | **Blade + Tailwind v4 + Alpine 3**, built with **Vite 8** | Server-rendered; Alpine for light interactivity (ADR-0004). |
| Dev environment | **GitHub Codespace** via `.devcontainer/` | One-command reproducible; MariaDB, Redis, Mailpit provisioned automatically. |
| Deployment (designed) | **Docker image on Render**, MariaDB external | nginx + php-fpm + supervisor (ADR-0006). |
| Tests | **Feature tests only**, against MariaDB `acl_test` | No `tests/Unit` by design. |

**Deliberate divergence to know about:** dev *runtime* is TiDB (MySQL-family)
while the *test* engine and *production* engine are MariaDB. The two are
wire- and SQL-compatible, migrations stay engine-portable, and the suite runs on
the production engine — so the divergence is bounded and recorded in ADR-0007.

## 4. Architecture

- **Modular monolith** (ADR-0001): one deployable Laravel app, internally
  organised by domain, not a swarm of services. Boundaries are guarded; crossing
  them or adding infrastructure requires an ADR.
- **The domain chain** (the heart of the schema): organization → faculty →
  department → programme → level → semester → course → course offering →
  enrolment → **entitlement**. Referential integrity is enforced by foreign keys
  in the engine, because an academic record that can dangle is a bug, not an
  edge case. This relational shape is why MongoDB was explicitly rejected
  (ADR-0005).
- **Scoped RBAC.** Capability is data: `roles`, `role_permissions`,
  `role_assignments` (a role, a user, and a *scope* row). A lecturer's authority
  over one department's offering does not leak to a sibling department. There is
  no `role` column on `users`, ever.
- **Entitlement is derived, then enforced server-side.** A policy decides access;
  Blade never does.
- **Decisions are ADRs** in `docs/adr/`, immutable once accepted; you supersede,
  never rewrite.

## 5. The process — how ACL is built

ACL carries its **own engineering organisation inside the repository**, so a
fresh clone recovers it with no setup. This is unusual and deliberate.

- **Governance documents** (read in this order for anything non-trivial):
  `docs/ACL_DEVELOPMENT_CONSTITUTION.md` → `.ai/guidelines/ACL.md` →
  `docs/ACL_MASTER_SPECIFICATION.md` → `docs/adr/` → `README.md` →
  `docs/PROJECT_STATUS.md`.
- **A specialist agent system** lives in `.claude/`: 11 ACL-specific agents that
  know this codebase (Orchestrator, Software/Backend/Database/Frontend/Security/
  AI/Learning-Experience Architects, Test Strategist, Docs Steward, Release
  Gatekeeper) plus a large reserve of general agents. Work is routed to the
  *minimum sufficient* set of specialists in a defined order, through gates.
- **The loop:** classify → read the authoritative docs → plan (files, schema,
  tests) → implement (one writer per file) → security review if a trust boundary
  moved → tests written and run in the codespace → docs updated in the same
  change → Release Gatekeeper verdict → report what changed, with evidence.
- **Selection is proportional to risk.** A typo is just fixed. A migration, an
  authorization change or anything calling a model provider goes through the full
  routed set and the gates.
- **Testing discipline:** a new route proves the happy path, the guest redirect
  and the unauthorized refusal; a new permission proves the sibling-scope denial;
  a retryable operation proves idempotency; a bug fix ships with a test that
  failed first. Feature tests only, run against MariaDB, never reported green
  without being executed.

## 6. The security model (what "secure" concretely means here)

- **Authentication:** session-based login; sessions regenerated on login and
  invalidated on logout.
- **Authorization:** enforced server-side via policies and scoped RBAC. Route
  model binding is scoped so a lesson from another offering 404s before the
  controller runs.
- **Entitlement:** access to a course offering is checked against the student's
  derived institutional entitlement, not a flag they can set.
- **Secrets:** never in code, docs, tests, prompts or commit messages. Config
  reads the environment; `env()` at a call site is a defect. Dev credentials for
  services that exist only inside the throwaway container are a documented,
  deliberate exception.
- **Transport:** any remote database (TiDB, managed MariaDB) is reached over TLS.
- **The line that never moves:** no `role` column on `users`; capability stays in
  the RBAC tables. Dev/seed accounts must never appear in any UI or in production.

---

## 7. Progress — what genuinely exists today (2026-09-05)

**Working and tested (feature tests, run against MariaDB `acl_test`):**

- **Authentication** — login/logout, session regeneration, guest vs. auth
  routing.
- **Scoped RBAC** — roles, permissions and scoped role assignments; server-side
  policy enforcement; sibling-scope denial.
- **Institutional entitlement** — enrolments derived from the institutional
  hierarchy; a `acl:sync-enrollments` command; entitlement drives dashboard and
  course access.
- **Dashboard** — shows a student's enrolled offerings for the current semester.
- **Course viewer** — a course offering page and per-lesson pages, with scoped
  route-model binding.
- **Lesson completion** — marking a lesson complete.
- **Test suite** — feature tests passing (39 passed / 67 assertions at last run),
  insulated by `phpunit.xml` from the dev Redis/TiDB configuration.

**Infrastructure stood up and verified this cycle:**

- **TiDB Cloud Serverless** as the dev runtime database, reached over TLS
  (`DB_CONNECTION=mysql`, CA bundle via `MYSQL_ATTR_SSL_CA`).
- **Redis** (loopback, password-protected, `noeviction`) backing sessions, cache
  and queue via `predis`.
- **Mailpit** capturing outbound mail (SMTP :1025 with AUTH, inbox on :8025).
- **Deployability** — a `Dockerfile`, `docker/` config and `render.yaml` exist so
  ACL can build as a container on Render behind an external MariaDB (ADR-0006).
  Note: an end-to-end production deploy has **not** been proven green.
- **Devcontainer hardening** — Redis and Mailpit are provisioned by
  `.devcontainer/` so they survive a codespace stop/resume and a full rebuild,
  rather than being hand-started.

## 8. The steps taken to get here

1. Working Laravel skeleton: identity, hierarchy, RBAC, entitlement, dashboard,
   course viewer, lesson completion — each with feature tests.
2. Architectural decisions recorded as ADRs 0001–0006 (modular monolith,
   application layout, frontend stack, MariaDB, Render/Docker deployment).
3. A reproducible GitHub Codespace via `.devcontainer/` that provisions the
   database, the app and assets with one command and reports its own health.
4. An in-repo agent system and governance documents so the engineering method
   travels with the code.
5. Deployment *designed* (not improvised): container image + `render.yaml`, with
   secrets kept out of the repo.
6. Dev infrastructure upgraded for realism and speed: TiDB (ADR-0007), Redis
   (ADR-0008) and Mailpit — then hardened into the devcontainer so they are
   durable, with the documentation corrected in the same change.

---

## 9. Future implementation (intent — not yet built)

These are recorded as direction in `docs/VISION.md` and the master
specification. None of them exist in code yet; do not describe them as if they
do.

- **AI-assisted learning** — tutoring, generation, grading, recommendation —
  behind an abstraction ACL owns. Every AI output is a *draft* requiring a
  recorded human approver before it reaches a student; every call is attributed,
  metered and capped; model output and retrieved documents are untrusted data.
  The first AI deliverable is a design and an ADR, not a client library.
- **Assessment & mastery** — quizzes, graded work, and completion/mastery rules
  richer than "lesson marked done".
- **Content authoring** — structured lessons, chapters and content-block types.
- **Gamification** — XP and streaks (the dashboard already reserves space for
  them, showing `0`).
- **Delivery hardening** — a CI pipeline (`.github/` does not exist; `main`
  currently reaches Render unverified), a Content-Security-Policy header,
  production-safe seeding (no `admin@acl.local` on a real database), and a proven
  green production deploy.
- **Production launch** on a real domain behind a proxy (Cloudflare / Render).

## 10. Non-goals and guardrails (do not cross without an ADR + human approval)

- No purchase/checkout flow for core course access — entitlement is derived.
- No `role` column on `users` — capability stays in the RBAC tables.
- No new infrastructure (new datastore, search engine, broker, vector store) and
  no new `composer`/`npm` dependency without authorisation and an ADR.
- No reversing an accepted ADR in code — supersede it with a new ADR.
- No destructive commands (`migrate:fresh`, `db:wipe`, rollback on seeded data,
  `rm -rf`, force pushes, dropping tables) without explicit human approval.
- No secret in the repository. No test reported green without being run. No
  documentation that describes features that do not exist.

## 11. Working agreement (the short version)

Inspect before claiming. Match the surrounding code. Change one slice at a time.
Put invariants in the database. Enforce authorization on the server. Write the
negative test. Update the docs in the same commit. Run the suite in the codespace
and report what it actually said. Ask before anything destructive.

## 12. Pointers

- **Repository:** `muneercybrid/ACL` (canonical; `main` is the trunk).
- **Entry point for engineers/AI:** `CLAUDE.md`.
- **Canonical docs:** `docs/ACL_DEVELOPMENT_CONSTITUTION.md`,
  `docs/ACL_MASTER_SPECIFICATION.md`, `docs/adr/`, `README.md`,
  `docs/PROJECT_STATUS.md`, `docs/database/DOMAIN_MODEL.md`,
  `docs/ui-ux/DESIGN_SYSTEM.md`.
- **Honest inventory of what exists:** `README.md` (trust it over any assumption).
- **Intent / roadmap:** `docs/VISION.md`.
- **The agent system:** `docs/agent-system/README.md`, `.claude/agents/`.

> Remember the one rule above all others: **the repository is ground truth.**
> This brief is a snapshot; when in doubt, read the code and the ADRs.
