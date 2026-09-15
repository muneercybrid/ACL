# ACL — Codex Project Instructions

## 1. PROJECT IDENTITY

ACL means "Anyone Can Learn".

ACL is a general education ecosystem, initially focused on Nigerian universities and designed for future global expansion.

ACL is NOT a cybersecurity-only application.

Production:
https://app.aclacademy.me

Repository:
https://github.com/muneercybrid/ACL

Project directory:
~/ACL

---

## 2. AUTHORITATIVE PROJECT DOCUMENTATION

Before significant implementation work, read:

1. AGENTS.md
2. docs/ACL_MASTER_ENGINEERING_CONTEXT.md
3. docs/ACL_DEVELOPMENT_CONSTITUTION.md
4. Relevant files under docs/agent-system/
5. Relevant ADRs

Precedence:

1. ACL Development Constitution
2. Relevant ADRs
3. AGENTS.md
4. ACL Master Engineering Context
5. Existing implementation
6. AI assumptions

The repository is the source of truth for what is actually implemented.

Never assume that a historical requirement has already been implemented.

Never invent existing functionality.

---

## 3. CURRENT DEVELOPMENT ORDER

Work through these major areas in this order:

1. Registration
2. Student Dashboard
3. Institution/Admin Dashboard

Do not jump to unrelated features.

Do not begin the next major phase until the current phase has been sufficiently inspected, implemented, tested, and verified.

---

## 4. REGISTRATION

Registration is currently the first priority.

Current work includes:

- Student registration
- External learner registration
- JAMB registration-number verification
- JAMB provider abstraction
- Manual/semi-manual verification paths

### JAMB registration number

Support registration numbers from all years and formats.

Rules:

- minimum length: 1 character
- maximum length: 15 characters
- do NOT require exactly 14 characters
- do NOT hard-code a year
- do NOT hard-code a prefix
- do NOT assume modern JAMB formatting applies to historical registrations

### Verification states

The system must distinguish between:

- verified
- not_found
- invalid_input
- provider_timeout
- provider_unavailable
- temporary_failure
- manual_verification_required
- ambiguous
- pending

A provider outage or technical failure must NEVER be treated as proof that the student is invalid.

### Provider architecture

Maintain a replaceable abstraction:

JambVerificationService
→ JambProviderInterface
→ provider adapter

Provider-specific behavior must remain inside provider adapters.

Application logic must consume normalized provider results rather than provider-specific response structures.

The architecture must allow a future official JAMB API/provider without rewriting registration logic.

---

## 5. STUDENT DASHBOARD

After registration is stable and verified, the next major phase is the student dashboard.

The student dashboard must be a dedicated student experience.

Do NOT reuse an administrator dashboard as the student dashboard.

Academic structure:

University
→ Faculty
→ Department
→ Programme
→ Level
→ Semester
→ Course
→ Chapter
→ Lesson
→ Learning Material

Student access must be data-driven.

Verified students should receive access to the courses offered by their institution according to the applicable semester and ACL access rules.

Additional courses, paths, AI features, and other premium functionality may require paid entitlement.

Entitlements must be enforced server-side.

Frontend visibility is not authorization.

---

## 6. ADMIN DASHBOARD

After the student dashboard is verified, build/fix the institution/admin dashboard.

Institution administrators must have a separate dashboard and experience.

Administrative access must be scoped.

An administrator belonging to one institution must not automatically gain access to another institution's data.

Authorization model:

Authentication
→ Role
→ Permission
→ Scope
→ Capability

Never reintroduce a hidden universal platform-admin authorization bypass.

Authorization must be enforced server-side.

---

## 7. ACLi

The new AI system is called ACLi.

The previous AI implementation is being rebuilt and must not be casually reintroduced.

Architecture:

Application
→ ACLi
→ AI/provider abstraction
→ Provider adapters
→ DeepSeek/Qwen/Gemini/OpenAI/etc.

Controllers must not directly contain provider-specific AI integration logic.

API keys must remain server-side.

Student ACLi is a paid/locked capability.

Showing an AI button does not authorize access.

Backend entitlement checks are mandatory.

AI must never:

- bypass RBAC
- grant permissions
- expose unauthorized data
- execute arbitrary AI-generated SQL
- reveal API keys
- bypass subscriptions
- bypass entitlements
- access data outside the user's authorization scope

AI-generated educational content follows:

Draft
→ Human Review
→ Correction
→ Approval
→ Publish

---

## 8. ARCHITECTURE

Use the existing Laravel Modular Monolith First architecture.

Database standard:

MariaDB.

Use:

- Eloquent
- Laravel query builder
- Laravel database abstractions

Application code must use a least-privilege database account.

Never use the MariaDB root account from application code.

Do not introduce microservices unless there is a clearly documented architectural reason and approval.

Prefer existing project patterns over introducing parallel patterns.

---

## 9. SECURITY

Always consider:

- authentication
- authorization
- RBAC
- scope isolation
- IDOR
- mass assignment
- CSRF
- XSS
- SQL injection
- session security
- privilege escalation
- rate limiting
- file upload security
- path traversal
- executable uploads
- payment security
- webhook security
- AI data leakage
- secret leakage

Never place secrets in:

- Git
- frontend code
- Blade templates
- logs
- error messages
- documentation

Production errors must not expose:

- stack traces
- SQL queries
- filesystem paths
- credentials
- provider secrets

---

## 10. DEVELOPMENT WORKFLOW

### ONE THING AT A TIME

For every task:

1. Inspect the current implementation.
2. Identify the actual problem.
3. Determine the smallest appropriate change.
4. Explain architectural consequences when relevant.
5. Make the change.
6. Run syntax checks.
7. Run relevant tests.
8. Verify routes/database behavior where applicable.
9. Inspect git diff.
10. Resolve every error before continuing.

If a command fails:

STOP.

Determine the root cause.

Fix it.

Run the verification again.

Only continue after successful verification.

---

## 11. PROTECT EXISTING USER WORK

Before risky changes run:

git status --short
git diff --stat
git diff

Never run destructive cleanup commands such as:

git reset --hard
git clean -fd
git restore
mass deletion

unless the project owner explicitly requests them.

Current uncommitted registration work is important and must be preserved.

Do not delete acl-dump.txt until its contents and purpose have been inspected.

Never overwrite unrelated user changes.

---

## 12. DATABASE SAFETY

Before database changes:

- inspect current schema
- inspect migrations
- understand foreign keys
- understand existing data
- check migration status

Never casually drop production tables.

Never delete production data merely to make tests pass.

Destructive database changes require explicit justification and approval.

---

## 13. FILE EDITING

For substantial file changes:

- inspect the complete existing file first
- preserve unrelated behavior
- avoid unrelated refactoring
- prefer complete controlled replacements when appropriate
- do not silently rewrite large portions of unrelated code

Do not create duplicate implementations when an existing service/model/controller should be extended.

---

## 14. TESTING AND VERIFICATION

Never claim something is fixed merely because code was changed.

Use these verification levels accurately:

- implemented
- syntax verified
- test verified
- integration verified
- production verified

Only claim the level actually demonstrated.

Relevant verification may include:

- php -l
- PHPUnit/Pest
- php artisan route:list
- php artisan migrate:status
- Laravel configuration checks
- database inspection
- HTTP/request tests
- authorization tests

---

## 15. UI / BRANDING

ACL should look:

- mature
- natural
- professional
- educational
- modern
- responsive

Light theme:

- light yellowish background
- light green interface/accent
- no dominant blue

Dark theme:

- dark background
- light green interface/accent

The public landing page is not login-first.

Use consistent header and footer branding.

The project owner's ACL logo should eventually be consistently used for:

- header
- footer
- favicon
- emails
- application branding

---

## 16. PERFORMANCE

Design for Nigerian mobile and low-bandwidth environments.

Prefer:

- optimized assets
- PDFs
- compressed images
- embedded YouTube where appropriate
- efficient database queries
- pagination
- caching where justified
- lightweight frontend behavior

Avoid unnecessary heavy video hosting.

---

## 17. ACADEMIC DATA

The platform must support multiple universities.

Never hard-code ACL around one university.

Academic data should support:

- current offerings
- historical offerings
- discontinued offerings where appropriate
- different faculties
- departments
- programmes
- levels
- semesters
- courses

---

## 18. PAYMENTS AND ENTITLEMENTS

Subscription state and entitlement state must not be assumed to be identical.

Premium access must be enforced by backend entitlement checks.

Payment callbacks/webhooks must be:

- server verified
- authenticated where applicable
- idempotent
- resistant to replay
- independent of browser claims

Never trust a frontend payment-success flag.

---

## 19. OBSERVABILITY AND AUDITING

Sensitive operations should be auditable where appropriate.

Audit records must never contain:

- passwords
- API keys
- access tokens
- payment secrets
- provider credentials

Logs must be useful without leaking sensitive data.

---

## 20. CURRENT REGISTRATION CHANGES

The repository currently contains uncommitted registration/JAMB work.

Do not reset or discard it.

Before modifying registration, inspect:

- StudentRegistrationController
- ExternalLearnerRegistrationController
- JAMB services
- JAMB provider interface
- provider adapters
- AcademicProgram model
- registration services
- registration views
- registration routes
- registration migrations
- registration verification models
- related tests

Determine what is actually implemented before deciding what needs fixing.

---

## 21. FIRST ACTION

When beginning a new Codex task, do not immediately modify code.

First read:

docs/ACL_MASTER_ENGINEERING_CONTEXT.md
docs/ACL_DEVELOPMENT_CONSTITUTION.md

Then inspect the repository.

For the current registration phase, report:

1. Current git state
2. Current uncommitted changes
3. Registration routes
4. Registration controllers
5. Registration services
6. JAMB provider architecture
7. Registration models
8. Registration migrations
9. Registration views
10. Existing registration tests
11. Current student dashboard
12. Current admin dashboard
13. Security/authorization concerns
14. Concrete registration problems
15. Smallest next fix

Do not modify code during this initial audit.

Wait for explicit approval before implementing the first fix.

---

## 22. PHASE GATES

### Registration gate

Do not move to the student dashboard until:

- registration routes work
- validation is correct
- JAMB input supports 1–15 characters
- provider failures are distinguished from invalid students
- successful verification is handled correctly
- manual verification path is understood
- relevant tests pass
- no authorization regression is present

### Student dashboard gate

Do not move to admin dashboard until:

- student dashboard works
- student authorization is verified
- academic scope is correct
- course access is correct
- premium entitlement checks are correct
- relevant tests pass

### Admin dashboard gate

Admin dashboard must maintain institution scope and RBAC.

---

## 23. NO UNAUTHORIZED SCOPE EXPANSION

Do not use a task as an excuse to:

- rebuild unrelated modules
- redesign the entire application
- introduce unnecessary packages
- rewrite working architecture
- remove existing data
- modify unrelated routes
- modify unrelated authorization
- introduce new AI functionality
- refactor unrelated code

If another problem is discovered, document it and return to the current task unless it blocks the task.

---

## 24. FINAL DEVELOPMENT RULE

ONE TASK
→ ONE CHANGE
→ VERIFY
→ RESOLVE ERRORS
→ VERIFY AGAIN
→ ONLY THEN CONTINUE.
