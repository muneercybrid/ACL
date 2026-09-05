# Security Agents

The agents that decide whether a trust boundary is sound. Eight are loaded; one is
ACL's own, and that one holds a **veto**.

A security veto is satisfied, not outvoted: either the finding is addressed or a human
waives it explicitly, in the conversation where it was raised
([`AGENT_GOVERNANCE.md`](../AGENT_GOVERNANCE.md) §4.3).

**The four trust boundaries that actually exist today** — verified 2026-09-03:

| Boundary | Enforced in |
|---|---|
| Unauthenticated → authenticated | `app/Http/Controllers/Auth/LoginController.php`, `Auth/LoginRequest.php`, the `auth` middleware in `routes/web.php` |
| Authenticated → entitled to an offering | `app/Policies/CourseOfferingPolicy.php`, `app/Services/EntitlementService.php`, `enrollments.unique(user_id, course_offering_id)` |
| Authenticated → entitled to a lesson | `app/Policies/LessonPolicy.php` |
| Role held → scope it applies in | `role_assignments` (`entity_type`/`entity_id` null = platform-wide, otherwise exactly one entity) |

There is **no single `role` column on `users`**, and adding one is forbidden by the
specification. Permissions are rows: `permissions`, `roles`, `role_permissions`,
`role_assignments`.

The secrets rule and the agent system's own threat model are in
[`AGENT_SECURITY.md`](../AGENT_SECURITY.md).

---

### ACL Security Architect
`acl-security-architect.md` · ACL

**Invoke when** authentication, authorization, RBAC, policies, entitlement, sessions,
validation, secrets or data exposure are touched — and always **last** among the
implementers, so it reviews the finished shape.
**Reviews** with a fixed six-question procedure: who can reach this, what proves they
may, what happens if the identifier is changed to someone else's, what leaks in the
response, what is logged, and what the test proves rather than asserts.
**Produces** findings in a fixed format — severity, file and line, the exploit path in
concrete terms, the fix, and the test that must now exist. It reviews and decides; it does
not implement.
**Consult** the four files above,
[`ACL_DEVELOPMENT_CONSTITUTION.md`](../../ACL_DEVELOPMENT_CONSTITUTION.md) §3,
`docs/security/README.md` (empty placeholder — filling it is real work).
**Hands to** `ACL Backend Architect` (implements the fix), `ACL Test Strategist` (allowed,
denied, and sibling-scope denial), `ACL Release Gatekeeper`.
**On secrets** the committed development database values in `README.md` and
`.devcontainer/` are deliberate and container-only; they are **not a precedent**. If you
find a real secret in Git history: stop, report it, and do not reproduce its value.

### Security Architect
`security-architect.md` · CORE

**Invoke when** a whole subsystem needs threat modelling rather than a diff needing
review.
**Reviews** trust boundaries, attacker goals, defence in depth, risk ranking.
**Produces** a threat model.
**Consult** `docs/ACL_MASTER_SPECIFICATION.md`, the ADRs.
**Hands to** `ACL Security Architect`, which owns the ACL-specific verdict, and
`Application Security Engineer` for code-level follow-through.

### Senior SecOps Engineer
`security-senior-secops.md` · CORE

**Invoke when** a change needs a defensive sweep before anything else: secrets and
sensitive-data exposure first, then the control set — auth, tokens, cookies, HTTP
headers, CORS, rate limiting, CSP, input validation, logging.
**Reviews** the diff for exposure, then the controls against a standard.
**Produces** a prioritised findings list.
**Consult** `config/session.php`, `.env.example`, `bootstrap/app.php` for middleware.
**Hands to** `ACL Security Architect`. Note what already exists so you do not "fix" it
twice: login throttling is implemented in `Auth/LoginRequest.php`
(`ensureIsNotRateLimited()`, five attempts per email + IP, keyed via `throttleKey()`).
There is no published `config/cors.php` and no security-header middleware.

### Application Security Engineer
`security-appsec-engineer.md` · SPECIALIST

**Invoke when** secure code review at scale, SAST/DAST, or SDLC hardening is the subject.
**Reviews** code patterns across the repository rather than one change.
**Produces** findings mapped to CWE, plus tooling recommendations. ACL has **no CI**, so
any recommendation that assumes a pipeline must say what it depends on.
**Consult** `composer.json` for what static analysis already exists (Pint only).
**Hands to** `ACL Security Architect`, `DevOps Automator`.

### AI-Generated Code Security Auditor
`security-ai-generated-code-auditor.md` · SPECIALIST

**Invoke when** reviewing code that an AI tool wrote — which, in this repository, is most
of it.
**Reviews** the specific defects assistants ship by default: hardcoded credentials,
authorization checked in the view instead of a policy, mass assignment, missing
validation, and prompt-injection sinks once AI features exist.
**Produces** CWE-mapped findings and drives a scan → fix → rescan loop.
**Consult** `git log` for what was generated and when.
**Hands to** `ACL Security Architect`, `ACL Backend Architect`.

### Identity & Access Engineer
`engineering-identity-access-engineer.md` · CORE

**Invoke when** session, credential, token, passkey or SSO mechanics are the subject —
and **first** in the authentication routing set, before the security review.
**Reviews** session lifecycle and fixation, credential handling, token scope, multi-tenant
authorization models.
**Produces** the mechanism design. University SSO (SAML/OIDC) and SCIM provisioning are
plausible ACL futures and are exactly this agent's subject; neither exists today.
**Consult** `config/auth.php`, `config/session.php` (database driver),
`app/Http/Controllers/Auth/LoginController.php`.
**Hands to** `ACL Security Architect` for sign-off, `ACL Backend Architect` to wire it.
**Note** ACL has no registration, no password reset, and no email sending. An identity
design that assumes a verification email is designing that too.

### Secrets & Credential Hygiene Engineer
`security-secrets-credential-engineer.md` · SPECIALIST

**Invoke when** a credential is added, rotated, or suspected leaked — and before any AI or
deployment work, which is where ACL's first real secrets will arrive.
**Reviews** detection, prevention, vaulting, rotation, and leak response.
**Produces** the rotation and response procedure, and a scan.
**Consult** `.env.example`, `.gitignore`, [`AGENT_SECURITY.md`](../AGENT_SECURITY.md) §2.
**Hands to** `ACL Security Architect`. On a real leak: rotate first, report without
reproducing the value, and treat history rewriting as a separate decision.

### Privacy Engineer
`engineering-privacy-engineer.md` · SPECIALIST

**Invoke when** student data is collected, copied, exported, indexed or sent anywhere —
including to a model provider.
**Reviews** what personal data exists, why each field is needed, retention, consent,
deletion, and pseudonymisation.
**Produces** the technical controls a privacy policy only promises: a data inventory, a
minimisation decision per field, and a deletion path.
**Consult** `docs/database/DOMAIN_MODEL.md`, the `users` and membership migrations.
**Hands to** `ACL Security Architect`, `ACL Database Architect`, `ACL AI Engineer`.
**Note** ACL holds records about students at Nigerian universities, some of whom may be
minors. Data minimisation is not a compliance formality here; it is the design.
