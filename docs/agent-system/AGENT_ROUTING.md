# Agent Routing

Given a task, which agents work on it and in what order. `ACL Orchestrator`
executes this table; a human directing Claude Code directly can read it and do the
same.

**The first rule is proportionality.** Most changes need no delegation at all. This
table is for work that crosses concerns.

---

## 1. Classification

| Signal in the request | Class | Set |
|---|---|---|
| One file, no schema, no auth, no contract change | `trivial` | none, or one specialist |
| New route, controller or view inside an existing domain | `slice` | `ACL Backend Architect` → `ACL Test Strategist` → `ACL Docs Steward` |
| New or changed migration | `schema` | `ACL Database Architect` → `ACL Backend Architect` → `ACL Test Strategist` → `ACL Docs Steward` |
| Auth, RBAC, policies, entitlement, sessions | `security-critical` | `Identity & Access Engineer` → `ACL Security Architect` → `ACL Backend Architect` → `ACL Test Strategist` |
| New domain, new module boundary, cross-domain coupling | `architecture` | `ACL Software Architect` (+ ADR) → domain specialists → gates |
| Anything calling a model provider, embeddings, grading, tutoring | `ai` | `ACL AI Engineer` → `ACL Security Architect` → `Model QA Specialist` → `ACL Test Strategist` |
| Learner-facing pedagogy, assessment design, curriculum structure | `learning` | `ACL Learning Experience Architect` → `ACL Backend Architect` → `ACL Frontend Architect` |
| Blade, Tailwind, Alpine, tokens, layout | `frontend` | `ACL Frontend Architect` → `Accessibility Auditor` → `ACL Test Strategist` |
| Deployment, devcontainer, CI, infrastructure | `ops` | `DevOps Automator` → `SRE` → `ACL Release Gatekeeper` |
| "Is this ready?" / "is this done?" | `verification` | `ACL Release Gatekeeper` (+ `Evidence Collector`) |
| Bug with unclear cause | `diagnosis` | `Minimal Change Engineer` → `ACL Test Strategist` |
| Understanding unfamiliar code | `orientation` | `Codebase Onboarding Engineer` |

A request is often several classes at once. Union the sets, then **remove every
agent that is not making a decision**.

## 2. The five sets the specification calls out

### Migration

```
ACL Database Architect      schema, keys, indexes, constraints, portability
  → ACL Backend Architect   models, casts, relationships, services that use it
  → ACL Test Strategist     a test that proves the constraint refuses the bad row
  → ACL Docs Steward        DOMAIN_MODEL.md and the README counts
  → ACL Release Gatekeeper  migrate:status seen, suite run, docs updated
```
Add `ACL Security Architect` if the table holds credentials, authorization data,
audit records or anything personal.

### Authentication or authorization

```
Identity & Access Engineer  session, credential and token mechanics
  → ACL Security Architect  trust boundary, scope leakage, exploit path — has a veto
  → ACL Backend Architect   the policy, the Form Request, the wiring
  → ACL Test Strategist     allowed, denied, and sibling-scope denial
  → ACL Release Gatekeeper  policy + test present for every privileged path
```
`ACL Security Architect` signs off last among the implementers. It does not
implement.

### AI or RAG

```
ACL Software Architect      does this belong here, and does it need an ADR
  → ACL AI Engineer         abstraction, prompts, retrieval, metering, quotas
  → ACL Security Architect  what data leaves ACL, prompt-injection sinks, secrets
  → RAG Pipeline Engineer   chunking, hybrid retrieval, re-ranking (when retrieval exists)
  → Model QA Specialist     evals, calibration, honest quality claims
  → ACL Test Strategist     faked provider; never a live call in the suite
```
ACL has no AI implementation. The first output of this set is an ADR.

### Major architectural change

```
ACL Software Architect      the decision, the alternatives rejected, the ADR
  → Master Plan Architect   red-team the plan before anyone writes code
  → domain specialists      implementation, sequenced one writer per file
  → ACL Security Architect  new trust boundaries
  → ACL Test Strategist     coverage for the new seam
  → ACL Docs Steward        ADR indexed, old ADR marked superseded
  → ACL Release Gatekeeper  no ADR silently reversed
```

### Production deployment

```
ACL Software Architect      ADR — none of this exists yet, so it is a design task
  → DevOps Automator        pipeline, image, environment configuration
  → SRE                     SLOs, observability, failure behaviour
  → Secrets & Credential Hygiene Engineer   no secret enters the repository
  → Database Reliability Engineer           backups, recovery, online migration
  → ACL Security Architect  production trust boundaries
  → ACL Release Gatekeeper  final verdict
```

## 3. Reaching for an upstream specialist

The loaded Agency agents are depth ACL has not written for itself. Route to one when
its subject actually appears:

| Subject that appears | Agent |
|---|---|
| WCAG, screen readers, keyboard traps | `Accessibility Auditor` |
| Session, token, passkey, SSO mechanics | `Identity & Access Engineer` |
| Slow query, missing index, N+1 | `Database Optimizer` |
| Replication, backup, online schema change | `Database Reliability Engineer` |
| Chunking, embeddings, re-ranking | `RAG Pipeline Engineer` |
| Prompt reliability and evaluation | `Prompt Engineer`, `Model QA Specialist` |
| Payment provider, subscription, webhook, SCA | `Payments & Billing Engineer` |
| Translation, plural rules, RTL, locale formatting | `Internationalization Engineer` |
| Student data, DSAR, retention, minimisation | `Privacy Engineer` |
| Secret scanning, rotation, leak response | `Secrets & Credential Hygiene Engineer` |
| Threat model of a whole subsystem | `Security Architect` |
| SAST/DAST, secure code review at scale | `Application Security Engineer` |
| Devcontainer, CI, image, pipeline | `DevOps Automator` |
| SLOs, error budgets, observability, toil | `SRE` |
| Playwright or Cypress end-to-end tests | `Test Automation Engineer` |
| A claim that needs independent proof | `Evidence Collector`, `Reality Checker` |
| Suite-wide quality trends | `Test Results Analyzer` |
| Branching, rebasing, commit hygiene, PR shape | `Git Workflow Master` |
| Diff discipline on a bug fix | `Minimal Change Engineer` |
| Long-form docs, tutorials, reference | `Technical Writer` |
| Instructional design depth | `Corporate Training Designer` |
| Learner research, usability studies | `UX Researcher` |
| Design system depth beyond DESIGN_SYSTEM.md | `UI Designer`, `UX Architect` |
| Generic UI that could be any product | `UI Finish-Gate Reviewer` |
| Drift across many AI-tool sessions | `Codebase Archaeologist` |
| Statistics, experiment design, evidence weighing | `Statistician`, `Research Synthesist` |
| CLI or internal tooling DX | `Developer Tooling Engineer` |
| Charts and honest data encoding | `Data Visualization Engineer` |
| Public or partner API contract design | `API Platform Engineer` |
| Roadmap, scope, prioritisation | `Product Manager`, `Sprint Prioritizer` |
| Agent-system design itself | `Multi-Agent Systems Architect` |

An upstream agent's advice is **advisory on ACL specifics**. When it conflicts with
an ACL agent about how ACL works, the ACL agent wins — and the conflict is worth
saying out loud, because it usually means an ACL document is unclear.

## 4. Activating a reserve agent

175 agents sit in `.claude/agents-available/`, tracked but not loaded. When a task
genuinely needs one:

```bash
git mv .claude/agents-available/<agent>.md .claude/agents/<agent>.md
bash scripts/agents/validate-agent-system.sh
```

Then update the row in `.claude/agent-manifest.tsv` and the table in
`AGENCY_AGENT_COVERAGE.md` in the same commit, and say why in the commit message.
Retiring one is the same move in reverse. Do not copy — move, so the file exists in
exactly one place.

## 5. When not to route

- The fix is one line and you can see it.
- The answer is a fact in the repository — read the file.
- You already know which specialist owns it. Call that one; skip the orchestrator.
- The task is to explain something. Explaining is not delegating.

Fan-out is a cost. Spend it where a decision needs an owner.

