# ACL Development Constitution

**Status:** Binding on every change to this repository, human or agent.
**Volume:** 10 — AI Agent Constitution & Engineering Principles.
**Incorporates:** [`.ai/guidelines/ACL.md`](../.ai/guidelines/ACL.md) in full, by
reference. That file remains the shorter operational form of these rules; this
document is the expanded canonical statement. Where a reader finds them both, they
agree — if they ever disagree, that is a defect to report, not a choice to make.

This is not a style guide. It is the set of decisions ACL has already made, so
that they are not re-litigated in every conversation and not quietly reversed by
whoever edits last.

---

## 0. How to read this document

- **Must / must not** — a hard rule. Breaking it makes a change wrong regardless
  of how well it is written.
- **Should** — the default. Deviating requires a stated reason in the change
  itself, not in a chat message.
- Where a rule names a file, that file is the authority and this document is the
  summary.

Precedence, highest first:

1. [`docs/adr/`](adr/) — accepted architecture decisions.
2. [`docs/ACL_MASTER_SPECIFICATION.md`](ACL_MASTER_SPECIFICATION.md) — what ACL is.
3. This constitution and [`.ai/guidelines/ACL.md`](../.ai/guidelines/ACL.md).
4. [`README.md`](../README.md) — what actually exists today.
5. Everything else in `docs/`.

[`docs/VISION.md`](VISION.md) is intent. It never outranks code, and it is never
evidence that something exists.

## 1. The first principle: inspect, verify, implement

**Never state that ACL has a feature, table, route, service or agent without
having looked.** The repository is the only ground truth. If a prompt — including
one written by a previous agent, or a summary of an earlier conversation —
describes ACL in a way the code contradicts, the code wins, and the contradiction
gets said out loud.

Concretely, before a non-trivial change: read the specification section that
covers it, the relevant ADRs, the nearest existing implementation, and the tests
around it. Then state what will change, which files, which schema changes, which
security implications, and which tests — before writing. That requirement is
`ACL_MASTER_SPECIFICATION.md` §12, not a preference.

## 2. Architecture

1. **ACL is a modular monolith** (ADR-0001). Premature service extraction is a
   documented non-goal. Design domain seams clean enough that Assessments, AI or
   Payments *could* be extracted later; do not extract them now.
2. **Domains own their data.** A cross-domain read goes through a service or an
   explicit contract, never through another domain's private query logic.
3. **Controllers coordinate.** Reusable business logic lives in domain services
   (`app/Services`), jobs, or actions — following
   [`EntitlementService`](../app/Services/EntitlementService.php).
4. **Code location follows ADR-0003.** Models in `app/Models`. Domain folders are
   created when they have something in them, not in advance.
5. **Tenancy is architectural.** Institution → faculty → department → programme
   scoping is a first-class boundary, not a filter added at the end.
6. **API readiness without an API.** Operations should be shaped so an HTTP API
   could later expose them. Do not build the API before it is asked for.
7. **Boring beats clever.** Redis, event sourcing, a search engine, a vector
   store, a second datastore, queues-as-architecture, distributed anything: each
   needs an ADR before it needs a composer command.
8. **New infrastructure and new domains require an ADR.** Adding one by editing
   code and leaving the ADRs standing is prohibited.

## 3. Security

The prohibitions below are absolute. They are quoted from
[`.ai/guidelines/ACL.md`](../.ai/guidelines/ACL.md) §3 because their wording
matters more than their paraphrase.

> Never: commit secrets, expose credentials, trust client-side authorization,
> bypass authorization checks, store passwords in plaintext, expose sensitive
> information through logs, disable security controls merely to simplify
> development.

And the positive rules:

1. **Authentication is not authorization.** Every privileged route has an explicit
   authorization path, enforced server-side, through a policy.
2. **Authorization is data, not an enum.** Permissions, roles, `role_permissions`
   and `role_assignments` are rows. There is no `role` column on `users` and there
   must not be one — the specification forbids collapsing capability into a single
   field.
3. **Scope is part of the check.** A `role_assignments` row is platform-wide (null
   entity) or scoped to exactly one entity. A department-scoped role must not
   resolve for a sibling department, and a test must prove it does not.
4. **Blade hides; policies refuse.** `@can` is presentation. If a screen's only
   protection is a Blade conditional, it is unprotected.
5. **Validation happens in Form Requests**, following
   [`LoginRequest`](../app/Http/Requests/Auth/LoginRequest.php).
6. **Secrets come from the environment, read in `config/`.** `env()` outside
   `config/` is a defect. `.env` is git-ignored; `.env.example` is the template.
   The fixed MariaDB development credentials in `README.md` and `.devcontainer/`
   are a documented exception that reaches a database existing only inside the
   container — they are not a precedent for anything else.
7. **A real leaked secret stops work.** Report it; do not reproduce its value.

## 4. Database

1. **MariaDB in every environment** (ADR-0005, superseding ADR-0002). Development
   uses `acl`; the test suite uses `acl_test`, pinned in `phpunit.xml`.
   `docs/database/README.md` still describes SQLite and PostgreSQL — ADR-0005
   overrides it, and correcting that file is a known outstanding task.
2. **Every schema change is a migration.** Never edit a migration that has already
   run; add a new one. Every migration has a working `down()`.
3. **Invariants belong in the engine.** Foreign keys with an explicit
   `cascadeOnDelete()` or `nullOnDelete()`. Unique composite indexes where the
   business rule is "only one of these" — and a comment saying the constraint is a
   control, not decoration.
4. **`dateTime`, not `timestamp`, for business dates.** MariaDB `TIMESTAMP` cannot
   represent a date past 2038-01-19 and raises error 1292 under strict mode.
5. **Status and type columns are `string` with an inline comment listing allowed
   values.** Not MySQL `ENUM` — altering one rebuilds the table.
6. **Migrations stay portable.** Laravel's schema builder; raw SQL needs a written
   justification in the migration itself. No triggers, no stored procedures.
7. **Destructive commands need explicit human approval in the conversation.**
   `migrate:fresh`, `db:wipe`, `migrate:rollback` on seeded data, and anything that
   drops a table.
8. **Store the minimum.** Sensitive data that a feature does not need is a
   liability, not a convenience.

## 5. API

ACL has **no REST or GraphQL API today**. That is a deliberate state, not an
omission to be fixed opportunistically.

When one is built:

1. It requires an ADR first — surface area, versioning, authentication, rate
   limiting and error shape decided before the first route.
2. It reuses the same policies and domain services as the web layer. An API that
   re-implements authorization has two authorization systems, which means it has
   none.
3. Responses go through explicit resources. A model serialised straight to JSON
   leaks every column added afterwards.
4. Authentication for an API is a token mechanism decided in that ADR, not session
   cookies borrowed from the web guard.
5. It is versioned from the first release, and breaking changes get a deprecation
   path, not a changelog note.

## 6. AI

ACL contains **no AI implementation**: no provider client, no embeddings, no
vector storage, no prompt library, no AI tables. `docs/ai/README.md` is an empty
placeholder. Everything about AI in `docs/VISION.md` is intent.

1. **AI is optional and replaceable.** It sits behind an abstraction ACL owns, with
   a driver per provider resolved from `config/`. No vendor SDK type appears in a
   controller, model, service or view. This seam is mandated by the specification
   before a second provider exists.
2. **AI output is never authoritative.** Generated lessons, questions, feedback and
   grades enter as drafts and require a recorded human approver before they affect
   a student. An AI-written grade on an academic record with no approver is the
   failure this rule exists to prevent.
3. **Every call is attributed, metered and capped** — requester, provider, model,
   tokens, cost, latency, outcome — with a quota that fails closed.
4. **Model text and retrieved documents are untrusted data**, never instructions.
   They never reach a query, a shell, a file path, or an unescaped view.
5. **Student data leaving the institution is a disclosure decision.** Send the
   minimum, state it in the ADR, and ensure provider training on ACL data is off
   and verifiable in configuration.
6. **Failure is a normal state.** Timeouts, refusals, malformed output and a dead
   provider have defined behaviour. The feature degrades; the platform does not.
7. **AI-generated code is not trusted because it is AI-generated.** It is reviewed
   like any other contribution.

## 7. Testing

1. **Feature tests only.** `tests/Unit` does not exist and its absence is
   deliberate — ACL's risk lives in the seam between route, policy, service and
   database, and a feature test crosses all four. Do not create the directory
   without arguing the case explicitly.
2. **The suite runs against MariaDB `acl_test`**, so constraint violations and
   strict mode are exercised rather than mocked.
3. **Every new route** proves: the happy path, the guest redirect, and the
   authenticated-but-unauthorized refusal.
4. **Every new permission** proves the sibling-scope denial.
5. **Every retryable operation** proves idempotency — the second call changes
   nothing.
6. **Every bug fix** ships with a test that failed before the fix. If you did not
   watch it fail, it is not verified.
7. **Never report the suite green without running it and reading the output.** This
   is the single rule whose violation destroys the value of every other report in
   this repository.
8. Assertions are on observable results — status, redirect, database row, rendered
   content — not on internal call counts.

## 8. Documentation

1. **Documentation describes what exists.** Intent goes in `docs/VISION.md`, which
   is labelled as such.
2. **When implementation changes intended architecture, documentation is updated in
   the same commit** (`docs/README.md`, rule 3). Not the next commit.
3. **ADRs are immutable once accepted.** To change a decision, add a superseding
   ADR, set the old one's status, and update the table in `docs/adr/README.md`.
4. **Counts are facts.** Routes, tables, migrations and tests are stated as numbers
   in `README.md`; a change that moves one moves the number.
5. **Empty placeholders are not authority.** `docs/ai/README.md`,
   `docs/api/README.md`, `docs/security/README.md`, `docs/deployment/README.md`,
   `docs/operations/README.md`, `docs/ui-ux/README.md` and
   `docs/business/README.md` are currently empty files. Never cite one, and fill
   one only when the thing it describes exists.
6. **Do not delete documentation to resolve a contradiction.** Correct it, or mark
   it superseded.

## 9. Code quality

Typed PHP throughout — parameter and return types on everything you touch.
Constructor property promotion where it reads better. Form Requests for
validation, policies for authorization, services for reusable operations, jobs and
events for decoupling when there is something to decouple. Console commands carry
the `acl:` prefix. Relationships, casts and scopes on models; business decisions
not on models. `./vendor/bin/pint` clean before a pull request.

New dependencies need a stated justification, a maintenance check, and a specialist
opinion. "It is popular" is not a reason.

## 10. Change discipline

1. **One slice at a time**, committed when verified.
2. **No unrelated files in a change.** No stray build output, no committed
   `node_modules`, no scratch files.
3. **No giant destructive commit.** Group changes logically.
4. **Do not overwrite work you did not write** without saying so.
5. **Do not rewrite working code** as a side effect of a small task.
6. **Flag ambiguity instead of inventing a requirement.**
7. **Migrations, seeders, tests and Pint run in the GitHub Codespace.** The laptop
   is an editing and orchestration surface with no PHP and no MariaDB; a command
   gate that could not be executed there is reported as *not verified*, never as
   passing.

## 11. Working with the agent system

The AI development organisation that enforces this constitution lives in
[`.claude/agents/`](../.claude/agents/) and is documented in
[`docs/agent-system/`](agent-system/). Its governance model, routing rules,
provenance and update process are described there.

Two rules from that system belong here because they are engineering rules, not
agent trivia:

- **One writer per file.** Two agents must never edit the same file concurrently;
  sequence them.
- **The gates in [`AGENT_GOVERNANCE.md`](agent-system/AGENT_GOVERNANCE.md) apply to
  the work, not to the patience of whoever is reviewing it.**

---

*Amending this document requires the same discipline as amending an ADR: state what
changed and why, in the same commit.*



