# Testing Agents

The agents that decide what must be proven, and whether it was. Nine are loaded; two
are ACL's own, and both hold authority the others do not — `ACL Test Strategist` has a
**veto on unproven behaviour**, and `ACL Release Gatekeeper` issues the readiness verdict
while being unable to change anything.

**The suite as it stands** — verified 2026-09-03, 39 tests / 67 assertions passing:

| Test class | Proves |
|---|---|
| `tests/Feature/Auth/AuthenticationTest.php` | Login renders, valid and invalid credentials, guest redirect, logout, and that the login page never echoes a credential (6 tests) |
| `tests/Feature/Authorization/CourseAccessTest.php` | An enrolled user reaches an offering; an unenrolled one is refused (15 tests) |
| `tests/Feature/Authorization/RbacScopingTest.php` | A scoped role does not leak to a sibling entity (13 tests) |
| `tests/Feature/Entitlement/InstitutionalEntitlementTest.php` | `EntitlementService` grants from membership, and is idempotent (5 tests) |

`tests/Unit` is **deliberately absent** and must not be created — ACL tests behaviour
through the framework, not classes in isolation. The suite runs against the `acl_test`
MariaDB schema configured in `phpunit.xml`, which also blanks `DB_URL` so a value in
`.env` cannot redirect tests at a real database.

**A known gap:** login throttling is implemented in `Auth/LoginRequest.php` but no test
covers it. That is the kind of finding this domain exists to surface.

**Where tests run:** in the GitHub Codespace. The laptop has no PHP and no MariaDB, so on
the laptop every testing gate is legitimately `NOT VERIFIED`. Reporting one as passing
there is the specific dishonesty this system exists to prevent.

---

### ACL Test Strategist
`acl-test-strategist.md` · ACL

**Invoke when** any behaviour changes — which is nearly always — and whenever someone says
a change is done.
**Reviews** what the change could break, whether the new test would actually fail without
the fix, and whether the negative case is covered as well as the positive one.
**Produces** feature tests under `tests/Feature`, named as a sentence:
`test_department_scoped_role_does_not_grant_sibling_department`. For authorization, three
tests: allowed, denied, and sibling-scope denial. For a migration, a test proving the
constraint refuses the bad row.
**Consult** the four existing test classes as the style reference, `phpunit.xml`,
`tests/TestCase.php`.
**Hands to** `ACL Release Gatekeeper` with the command it ran and what it printed.
**Refuses** to report green without executing the suite, and refuses to create
`tests/Unit`.
**Diagnosis notes** a failing suite after a codespace resume is usually MariaDB not
started (`bash .devcontainer/start-services.sh`); a 500 in a view test is usually a stale
or missing `public/build`; a test that passes alone and fails in the suite is usually
shared data.

### ACL Release Gatekeeper
`acl-release-gatekeeper.md` · ACL · `tools: Read, Grep, Glob, Bash`

**Invoke when** the question is "is this ready?" or "is this done?".
**Reviews** ten gates — security, testing, documentation, architecture, database, AI,
style, secrets, scope, and environment honesty — and reports each as `PASS`, `FAIL` or
`NOT VERIFIED`. The third is never recorded as the first.
**Produces** a verdict:

```text
VERDICT: READY | NOT READY | READY WITH NOTED RISK
Gates:      1 PASS  2 PASS  3 FAIL  ...
Evidence:   the commands you ran and what they printed
Blocking:   the specific things that must change, with file paths
Unverified: what you could not check, and why
Risk:       what ships if this goes out as-is
```

**Consult** [`AGENT_GOVERNANCE.md`](../AGENT_GOVERNANCE.md) §6 for the gate table.
**Hands to** the human. **It is read-only by design** — it has no Edit or Write tool, and
that is the whole basis of its credibility. It cannot fix what it fails.

### Evidence Collector
`testing-evidence-collector.md` · CORE

**Invoke when** a claim needs proof attached rather than asserted.
**Reviews** the claim against what can actually be shown.
**Produces** the artefacts — command output, screenshots, response bodies.
**Hands to** `ACL Release Gatekeeper`, whose evidence line it fills.

### Reality Checker
`testing-reality-checker.md` · CORE

**Invoke when** something is about to be called production-ready.
**Reviews** the gap between what is claimed and what is demonstrated. It defaults to
"needs work" and requires overwhelming proof to say otherwise.
**Produces** a refusal with reasons, or a grudging certification.
**Consult** `README.md`'s "What does not exist yet" list and `docs/PROJECT_STATUS.md`.
**Note** those two documents currently disagree — §9 of `PROJECT_STATUS.md` says the suite
has not been executed while `README.md` reports 39 passing tests. Checking which is true
is exactly this agent's job.

### Accessibility Auditor
`testing-accessibility-auditor.md` · CORE

**Invoke when** any Blade view or interactive component changes.
**Reviews** WCAG conformance, keyboard reachability and traps, focus visibility, contrast
against ACL's dark palette, and screen-reader semantics. Its default assumption is that
untested means inaccessible.
**Produces** findings tied to specific markup, with the fix.
**Consult** `docs/ui-ux/DESIGN_SYSTEM.md` — every interactive element needs hover, focus
and active states, and the terminal-green focus ring is a documented token, not a
decoration.
**Hands to** `ACL Frontend Architect`.
**Note** for a learning platform, accessibility is pedagogy: a student who cannot operate
the interface cannot learn from it.

### Test Automation Engineer
`testing-test-automation-engineer.md` · SPECIALIST

**Invoke when** browser-level end-to-end coverage is genuinely needed. **None exists** —
no Playwright, no Cypress, no Dusk, and no CI to run them in.
**Reviews** selector resilience, test isolation, flake sources, parallelisation.
**Produces** a proposal that states its cost honestly. Feature tests cover ACL's current
surface; adding a browser runner is a dependency decision needing approval.
**Hands to** `ACL Test Strategist`, `DevOps Automator`.

### API Tester
`testing-api-tester.md` · SPECIALIST

**Invoke when** ACL has an API to test. It has none — there is no `routes/api.php`.
**Reviews** contract conformance, status codes, error shapes, auth on every endpoint.
**Produces** a test plan against a contract.
**Hands to** `API Platform Engineer`, `ACL Test Strategist`.

### Test Results Analyzer
`testing-test-results-analyzer.md` · SPECIALIST

**Invoke when** the suite is large enough for trends to mean something, or when failures
are intermittent.
**Reviews** pass rates over time, flake patterns, coverage gaps by area.
**Produces** an analysis with the numbers behind it. With 39 tests, "the suite is green"
is still readable by a human — this agent earns its place later.
**Hands to** `ACL Test Strategist`.

### Performance Benchmarker
`testing-performance-benchmarker.md` · SPECIALIST

**Invoke when** something is measurably slow, or before a change whose cost is disputed.
**Reviews** latency, query counts, memory, and whether the measurement is reproducible.
**Produces** a before/after measurement. Measure in the codespace; a laptop with no PHP
cannot produce one, and an unmeasured optimisation is not an optimisation.
**Hands to** `Database Optimizer` for query work, `ACL Backend Architect` for code.
