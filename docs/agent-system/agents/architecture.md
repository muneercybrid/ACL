# Architecture Agents

The agents that decide **where something belongs** and whether a change is allowed to
alter ACL's shape at all. Seven are loaded; two are ACL's own.

Every agent here is project-local in `.claude/agents/`. On any ACL-specific question an
`ACL *` agent outranks its generic equivalent — see
[`AGENT_GOVERNANCE.md`](../AGENT_GOVERNANCE.md) §3.6. Routing sets are in
[`AGENT_ROUTING.md`](../AGENT_ROUTING.md); categories in
[`AGENCY_AGENT_COVERAGE.md`](../AGENCY_AGENT_COVERAGE.md).

The binding constraints for this whole domain: **modular monolith** (ADR-0001), **models
in `app/Models`, domain folders only when populated** (ADR-0003), **MariaDB everywhere**
(ADR-0005), and no new infrastructure without an ADR and human approval.

---

### ACL Orchestrator
`acl-orchestrator.md` · ACL

**Invoke when** a task touches more than one concern, or you do not know who owns it.
Not for a one-file change — proportionality is its first rule.
**Reviews** the request itself: what class of work it is, which gates apply, what
evidence the result will need.
**Produces** a classification, the minimum sufficient agent set, an execution order that
respects *one writer per file*, and a refusal to call the work done before it is
verified in the codespace.
**Consult** [`AGENT_ROUTING.md`](../AGENT_ROUTING.md),
[`AGENT_GOVERNANCE.md`](../AGENT_GOVERNANCE.md), `CLAUDE.md`.
**Hands to** whichever specialists it selected. It decides *who* and *in what order*,
never what the right migration or policy is.

### ACL Software Architect
`acl-software-architect.md` · ACL

**Invoke when** introducing a new domain, coupling two existing ones, adding
infrastructure, designing something ACL has never had (an API, AI, deployment, payments,
multi-tenancy), or reversing a recorded decision.
**Reviews** module boundaries, dependency direction, whether a service belongs in
`app/Services` or in the domain that owns it, and whether an existing ADR already
answers the question.
**Produces** an ADR in `docs/adr/` — numbered, indexed, with options rejected and their
reasons — plus a sequenced implementation order for the specialists.
**Consult** `docs/adr/` (all of them; 0002 is superseded by 0005),
`docs/ACL_MASTER_SPECIFICATION.md`, `docs/architecture/`,
[`ACL_DEVELOPMENT_CONSTITUTION.md`](../../ACL_DEVELOPMENT_CONSTITUTION.md) §2.
**Hands to** `ACL Backend Architect`, `ACL Database Architect`, `ACL AI Engineer`;
escalates trust-boundary questions to `ACL Security Architect`.
**Note** `docs/architecture/README.md` still names SQLite and PostgreSQL. ADR-0005 is
authoritative; the README is a known stale document.

### Software Architect
`engineering-software-architect.md` · CORE

**Invoke when** you want general design depth — DDD vocabulary, pattern trade-offs,
coupling analysis — behind an ACL decision.
**Reviews** the design as design, without reference to ACL's specifics.
**Produces** advice. Nothing binding.
**Consult** whatever the ACL architect is already reading.
**Hands to** `ACL Software Architect`, which decides. Where the two disagree about how
ACL works, the ACL agent wins and the disagreement is worth stating out loud — it
usually means an ACL document is unclear.

### Master Plan Architect
`specialized-master-plan-architect.md` · CORE

**Invoke when** a plan exists and nobody has attacked it yet. Best used *after* the ADR
draft and *before* any code.
**Reviews** the plan's assumptions, sequencing, rollback story and failure modes. It
red-teams; it does not implement.
**Produces** a written critique and a revised implementation plan in Markdown.
**Consult** the draft ADR, `docs/PROJECT_STATUS.md`, `README.md`'s "what does not exist
yet" list — the most common plan defect in ACL is assuming something already exists.
**Hands to** `ACL Software Architect` to accept or reject each objection.

### Workflow Architect
`specialized-workflow-architect.md` · SPECIALIST

**Invoke when** a user-facing flow needs its branches enumerated before it is built —
enrolment, assessment submission, an approval chain.
**Reviews** happy path, every branch condition, failure and recovery paths, handoff
contracts, observable states.
**Produces** a build-ready flow spec that tests can be written against.
**Consult** `docs/ACL_MASTER_SPECIFICATION.md`, `app/Services/EntitlementService.php`
for the one non-trivial flow that already exists.
**Hands to** `ACL Backend Architect` to build, `ACL Test Strategist` to cover the
branches.

### Multi-Agent Systems Architect
`engineering-multi-agent-systems-architect.md` · SPECIALIST

**Invoke when** changing the agent system itself — topology, routing, gates, context
budget — or when ACL builds product features that orchestrate models.
**Reviews** delegation topology, context handling, trust between agents, human-in-the-
loop gating, observability.
**Produces** design proposals for `docs/agent-system/`.
**Consult** this whole directory, especially
[`AGENT_GOVERNANCE.md`](../AGENT_GOVERNANCE.md) and
[`AGENT_SECURITY.md`](../AGENT_SECURITY.md).
**Hands to** `ACL Docs Steward` — a governance change that is not documented did not
happen.

### API Platform Engineer
`engineering-api-platform-engineer.md` · SPECIALIST

**Invoke when** ACL designs a public, partner or mobile-facing API. **None exists
today** — `routes/api.php` is absent and `docs/api/README.md` is an empty placeholder.
**Reviews** contract-first design, versioning and deprecation policy, authentication and
quotas at the edge, SDK and developer-portal DX.
**Produces** an ADR first, then an OpenAPI contract. Not routes.
**Consult** `docs/ACL_MASTER_SPECIFICATION.md`,
[`ACL_DEVELOPMENT_CONSTITUTION.md`](../../ACL_DEVELOPMENT_CONSTITUTION.md) §5.
**Hands to** `ACL Software Architect` (does this belong here at all),
`ACL Security Architect` (token model, scope leakage), `Identity & Access Engineer`
(OAuth mechanics).
