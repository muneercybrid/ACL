# DevOps Agents

The agents for the environment ACL runs in. Three are loaded; none is ACL's own, because
ACL has no operations to have an opinion about yet.

**What exists** — verified 2026-09-03:

| | |
|---|---|
| Development environment | `.devcontainer/` — PHP 8.4, MariaDB 10.11, Node 24, built from a Dockerfile because the base image lacks `pdo_mysql` |
| Lifecycle scripts | `install-services.sh` (onCreate), `post-create.sh` (postCreate), `start-services.sh` (postStart) |
| Forwarded ports | 8000 (Laravel), 5173 (Vite) |
| Troubleshooting | `scripts/troubleshoot.sh` |
| CI pipeline | **none** — no `.github/workflows/` |
| Deployment configuration | **none** — `docs/deployment/README.md` and `docs/operations/README.md` are empty placeholders |

Creating deployment or production configuration is an **architectural** act: it needs an
ADR and explicit human approval ([`AGENT_GOVERNANCE.md`](../AGENT_GOVERNANCE.md) §5).
Nobody adds a workflow file or a Dockerfile for production as a convenience.

**Environment split.** The laptop edits; the codespace runs. Migrations, seeders,
`php artisan test` and `./vendor/bin/pint` execute in the codespace, because the laptop
has no PHP, no MariaDB and no `jq`. Any script written here must work in bash without
those, or state plainly that it is codespace-only.

---

### DevOps Automator
`engineering-devops-automator.md` · CORE

**Invoke when** the devcontainer, a lifecycle script, or a CI pipeline is the subject —
and first in the `ops` routing set.
**Reviews** whether the container comes up from a cold `git clone` with no manual step,
whether a script is idempotent across a rebuild and a resume, and whether a failure is
loud rather than silent.
**Produces** changes to `.devcontainer/` and `scripts/`, in bash matching the existing
files' style. When it proposes CI, it proposes the smallest useful pipeline first — Pint
and the feature suite against a MariaDB service — not a matrix.
**Consult** `.devcontainer/devcontainer.json`, the three lifecycle scripts, `README.md`'s
devcontainer stage table, `composer.json`, `package.json`.
**Hands to** `SRE`, then `ACL Release Gatekeeper`.
**History worth knowing:** two devcontainer failures are already fixed and should not be
reintroduced — a script that parsed `php -i` and silently killed both setup scripts, and
an apt source that failed every image build. Read `git log` on `.devcontainer/` before
changing it.

### SRE (Site Reliability Engineer)
`engineering-sre.md` · CORE

**Invoke when** SLOs, error budgets, observability, alerting, failure behaviour or toil
are the subject. **None of this exists**, and there is nothing in production to observe.
**Reviews** what would be measured, what would page a human, and what would degrade
gracefully.
**Produces** an ADR before instrumentation. Adding an observability stack is new
infrastructure.
**Consult** `config/logging.php` for what logging exists today (Laravel's default stack),
`docs/operations/README.md` (empty).
**Hands to** `ACL Software Architect`, `Database Reliability Engineer` for data-layer
recovery, `ACL Release Gatekeeper`.
**Note** the honest sequence for ACL is: deployment target decided → deployment
configuration written → *then* SLOs. An SLO for a system with no users and no environment
is a document nobody can act on.

### Tool Evaluator
`testing-tool-evaluator.md` · SPECIALIST

**Invoke when** a dependency, service or platform is being considered — a CI provider, a
monitoring service, a browser-test runner, a queue driver.
**Reviews** fit against ACL's actual constraints, total cost including maintenance, the
exit path, and whether something already in the stack does the job.
**Produces** a recommendation with the alternatives it rejected and why — the input to an
ADR, not a substitute for one.
**Consult** `composer.json`, `package.json`, the ADRs. ACL's bias is explicit: **boring
beats clever**, and Laravel's database drivers for session, cache and queue in production
are a decision, not an accident (the devcontainer overrides them to Redis for dev per
ADR-0008 — equally a decision, and recorded as one).
**Hands to** `ACL Software Architect`. Every new entry in `composer.json` or
`package.json` needs human approval in the current conversation, because a dependency is
permanent maintenance surface.
