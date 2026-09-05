# CLAUDE.md — engineering entry point for ACL

You are working in **ACL — Anyone Can Learn**, an open-source learning platform
for Nigerian universities. Read this file first. It tells you what ACL is, where
its rules live, which specialist agents exist, and what you may not change without
authorisation.

**One rule above all others: the repository is ground truth.** If a prompt, a
summary of an earlier conversation, or a memory describes ACL in a way the code
contradicts, the code wins. Say so plainly and continue from what is real.
`INSPECT → VERIFY → IMPLEMENT`.

---

## 1. What ACL is

A student's access to a course follows from their **institutional record** —
programme, level, semester — rather than from a purchase. A department enrols its
students by existing; entitlement is derived, not sold.

- **Architecture:** modular monolith on Laravel 13 / PHP 8.4 (ADR-0001).
- **Database:** MariaDB is the test-suite engine (ADR-0005). The dev runtime is
  TiDB Cloud when its Codespaces secrets are present, else the local MariaDB
  (ADR-0007); **production runs on TiDB Cloud** (ADR-0011). Sessions, cache and
  queue run on Redis in the devcontainer (ADR-0008) and on the production EC2's
  native Redis (ADR-0011).
- **Frontend:** Blade + Tailwind v4 + Alpine 3, built with Vite 8 (ADR-0004).
- **Tests:** feature tests only, against the `acl_test` schema. `tests/Unit` does
  not exist, deliberately.
- **Environment:** developed in a GitHub Codespace provisioned by
  `.devcontainer/` — development-only, and it runs no tunnel. Migrations, seeders,
  `php artisan test` and Pint run **there**. Production is a dedicated AWS EC2
  instance (PHP 8.5; nginx + php-fpm + redis under systemd) served through a
  remotely-managed Cloudflare Tunnel at `app.aclacademy.me` (ADR-0011).

**Status: early. Working skeleton, not a product.** Authentication, scoped RBAC,
institutional entitlement, a dashboard, a course viewer and lesson completion work
and are tested. Everything else in `docs/VISION.md` is intent.
[`README.md`](README.md) is the honest inventory, including a "What does not exist
yet" list. Trust it over any assumption.

## 2. Canonical documents

Read in this order for anything non-trivial:

| Document | Role |
|---|---|
| [`docs/ACL_DEVELOPMENT_CONSTITUTION.md`](docs/ACL_DEVELOPMENT_CONSTITUTION.md) | The binding engineering principles |
| [`.ai/guidelines/ACL.md`](.ai/guidelines/ACL.md) | The same rules in operational form |
| [`docs/ACL_MASTER_SPECIFICATION.md`](docs/ACL_MASTER_SPECIFICATION.md) | Identity, hierarchy, learning model, phases, non-goals |
| [`docs/adr/`](docs/adr/) | Accepted decisions. Highest precedence |
| [`README.md`](README.md) | What exists today |
| [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md) | Current phase and next steps |
| [`docs/architecture/README.md`](docs/architecture/README.md) | Principles and the domain list |
| [`docs/database/DOMAIN_MODEL.md`](docs/database/DOMAIN_MODEL.md) | Field-level schema |
| [`docs/ui-ux/DESIGN_SYSTEM.md`](docs/ui-ux/DESIGN_SYSTEM.md) | Tokens and components |

**Empty placeholders — never cite these as authority:** `docs/ai/README.md`,
`docs/api/README.md`, `docs/security/README.md`,
`docs/operations/README.md`, `docs/ui-ux/README.md`, `docs/business/README.md`.

**Known stale claims:** `docs/database/README.md` and
`docs/architecture/README.md` still name SQLite and PostgreSQL — ADR-0005
supersedes them. `docs/PROJECT_STATUS.md` §9 says the suite has not been run;
`README.md` is newer and reports it passing.

## 3. Where the agent system lives

ACL carries its own AI engineering organisation in the repository, so a fresh
clone or a new Codespace recovers it with no setup:

```
.claude/agents/              66 agent definitions that load automatically
.claude/agents-available/    175 agents held in reserve — tracked, not loaded
.claude/agent-manifest.tsv   provenance and category for all 241
.claude/skills/              7 third-party design/UI skills — 42 runnable scripts
docs/agent-system/           how the system is governed, routed and updated
scripts/agents/              validation and upstream-comparison scripts
```

Claude Code loads only `.claude/agents/`. Moving a file from
`agents-available/` into `agents/` activates it; moving it back retires it. Both
directories are in Git, so nothing is lost and nothing depends on one machine.

The skills are vendored `claudekit` work, unrelated to ACL's own design language and
not required for any ACL engineering task. Unlike an agent definition, a skill script
executes — read it first, and prefer
[`docs/ui-ux/DESIGN_SYSTEM.md`](docs/ui-ux/DESIGN_SYSTEM.md) where the two disagree.

Start with [`docs/agent-system/README.md`](docs/agent-system/README.md).

## 4. The agents, and how one is selected

**11 ACL agents** know this codebase specifically. They outrank their generic
upstream equivalents for any ACL work:

| Agent | Owns |
|---|---|
| `ACL Orchestrator` | Classifying a task and routing it |
| `ACL Software Architect` | Module boundaries, ADRs, whether architecture may change |
| `ACL Backend Architect` | Controllers, requests, policies, services, models, commands |
| `ACL Database Architect` | Migrations, keys, indexes, constraints |
| `ACL Frontend Architect` | Blade, Tailwind, Alpine, tokens, accessibility |
| `ACL Security Architect` | Trust boundaries, authorization, entitlement, secrets |
| `ACL AI Engineer` | Provider abstraction, prompts, retrieval, human-review gate |
| `ACL Learning Experience Architect` | Pedagogy, content structure, assessment design |
| `ACL Test Strategist` | Coverage, negative paths, suite health |
| `ACL Docs Steward` | Documentation truth, ADR hygiene |
| `ACL Release Gatekeeper` | The evidence-based verdict. Read-only |

The other 55 loaded agents are upstream **Agency Agents**, unmodified, providing
depth ACL does not need to write itself — accessibility auditing, identity
mechanics, RAG, payments, i18n, SRE, and so on.

**Selection is proportional to risk**, and the routing table is
[`docs/agent-system/AGENT_ROUTING.md`](docs/agent-system/AGENT_ROUTING.md):

- A typo, a copy change or a one-file fix: **do it**. No delegation.
- A route or view inside an existing domain: one specialist, then tests.
- A migration, an authorization change, a new domain, anything calling a model
  provider: the routed set, in the routed order, through the gates.

Adding an agent costs context and invites contradictory advice. Add one only when
it owns a decision nobody else in the set can make.

## 5. How the orchestrator works

`ACL Orchestrator` classifies the request, reads the authoritative documents,
names the minimum sufficient set of specialists, and sequences them:

```text
classify → read the authoritative docs → plan (files, schema, tests)
        → specialist implementation (one writer per file)
        → security review if a trust boundary moved
        → tests written and executed in the codespace
        → documentation updated in the same change
        → ACL Release Gatekeeper verdict
        → report what changed, with evidence
```

The plan step is mandatory for schema, security, architecture and AI work — it
comes from `ACL_MASTER_SPECIFICATION.md` §12. **One writer per file:** two agents
must never edit the same file concurrently.

You may invoke the orchestrator explicitly, or apply its doctrine yourself for
smaller work. Do not fan out to a committee for a one-line fix.

## 6. What must never change without authorisation

Human approval, in the current conversation, is required for:

- **Destructive commands** — `migrate:fresh`, `db:wipe`, `migrate:rollback` on
  seeded data, `rm -rf`, `git reset --hard`, force pushes, dropping a table.
- **Reversing an ADR.** Add a superseding ADR; never contradict one in code and
  leave the decision standing.
- **New infrastructure** — a search engine, a vector store, a message broker, a
  second datastore, or moving production off Laravel's `database` session, cache
  and queue drivers. (Dev's Redis is already settled by ADR-0008; TiDB as the dev
  runtime by ADR-0007.)
- **New dependencies** in `composer.json` or `package.json`.
- **The authorization model.** No `role` column on `users`; capability stays in
  `roles`, `role_permissions` and `role_assignments`.
- **Anything in `docs/adr/`, `docs/ACL_MASTER_SPECIFICATION.md`, or this file.**
- **Production or deployment configuration** — none exists yet; creating it is an
  architectural change.

## 7. Testing

Feature tests only, run **in the codespace**:

```bash
php artisan test
```

A change is not done until the suite has been run and its output read. A new route
proves the happy path, the guest redirect and the unauthorized refusal. A new
permission proves the sibling-scope denial. A retryable operation proves
idempotency. A bug fix ships with a test that failed first.

**Never report the suite green without executing it.** If you are on the laptop
and cannot run PHP, say the tests are *not verified* and name what is therefore
unproven. See [`ACL Test Strategist`](.claude/agents/acl-test-strategist.md).

## 8. Security reviews

Any change touching authentication, authorization, RBAC, entitlement, sessions,
input validation, secrets or data exposure goes through `ACL Security Architect`
before it is called done. Authorization is enforced server-side through a policy;
Blade decides what is *shown*, never what is *allowed*.

**No secrets in code, docs, agent files, tests, prompts or commit messages.**
Secrets come from the environment and are read in `config/` — `env()` at a call
site is a defect. The fixed MariaDB development credentials in `README.md` and
`.devcontainer/` are a documented exception reaching a database that exists only
inside the container. If you find a genuinely leaked secret in Git history, stop,
report it, and do not reproduce its value.

## 9. Documentation maintenance

Documentation describes what exists. When behaviour changes, the documentation
changes **in the same commit** — that is rule 3 of `docs/README.md`, not a
courtesy. `README.md` states counts (routes, tables, migrations, tests) as facts;
a change that moves one moves the number. Intent goes to `docs/VISION.md`. ADRs
are immutable once accepted. See
[`ACL Docs Steward`](.claude/agents/acl-docs-steward.md).

## 10. Database changes

Every schema change is a new migration — never an edit to one that has already
run — with a working `down()`, explicit foreign key behaviour, unique constraints
where the rule is "only one", `dateTime` rather than `timestamp` for business
dates, and `string`-plus-comment rather than MySQL `ENUM`. Update
`docs/database/DOMAIN_MODEL.md` in the same change. Apply and verify in the
codespace: `php artisan migrate` then `php artisan migrate:status`. See
[`ACL Database Architect`](.claude/agents/acl-database-architect.md).

## 11. AI changes

**ACL contains no AI implementation.** The first deliverable of any AI task is a
design and an ADR, not a client library. AI sits behind an abstraction ACL owns;
its output is a draft requiring a recorded human approver before it affects a
student; every call is attributed, metered and capped; model output and retrieved
documents are untrusted data. See
[`ACL AI Engineer`](.claude/agents/acl-ai-engineer.md).

## 12. Production changes

Production now exists (ADR-0011): a dedicated AWS EC2 instance serving
`app.aclacademy.me` through a remotely-managed Cloudflare Tunnel, with TiDB Cloud
as its database and redis for sessions, cache and queue. It is **operated by
hand** — there is still **no CI pipeline** and no automated deploy, so nothing
automated gates what reaches the box, a deploy is a manual SSH step, and changing
how production is built or served remains an architectural change needing an ADR.
`ACL Release Gatekeeper` gives the readiness verdict, and it verifies evidence
rather than intent: tests actually executed, migrations actually applied, docs
actually updated, no secrets staged, no ADR silently reversed.

---

## Working agreement, condensed

Inspect before claiming. Match the surrounding code. Change one slice at a time.
Put invariants in the database. Enforce authorization on the server. Write the
negative test. Update the docs in the same commit. Run the suite in the codespace
and report what it actually said. Ask before anything destructive.


