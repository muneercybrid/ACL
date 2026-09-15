# ACL — Master Engineering Context

## 1. PROJECT IDENTITY

ACL means:

> Anyone Can Learn

ACL is a general education ecosystem.

It is initially focused on Nigerian universities but is designed to expand globally.

ACL is NOT a cybersecurity-only platform.

The platform should support students, tutors/lecturers, administrators, external learners, educational partners, and platform/super administrators.

Production application:

https://app.aclacademy.me

GitHub repository:

https://github.com/muneercybrid/ACL

Server project:

~/ACL

Current deployment environment is an Ubuntu EC2 server.

---

## 2. PRODUCT PHILOSOPHY

ACL is organized around four major ideas:

1. Learn
2. Practice
3. Collaborate
4. Advance

The product should eventually provide an integrated educational ecosystem rather than simply being a course website.

Potential capabilities include:

- university academic content
- structured courses
- chapters and lessons
- learning materials
- practice questions
- student progress
- collaboration
- tutor/lecturer interaction
- forums/messaging
- live learning
- labs
- XP/badges
- credentials
- educational partners
- premium learning paths
- ACLi AI capabilities
- institution administration
- analytics
- future examination/practical/recognition capabilities

Do not implement all of these merely because they are listed here.

They represent product direction, not permission to expand the current task.

---

## 3. ARCHITECTURAL AUTHORITY

The repository contains an ACL Development Constitution.

Primary authoritative architecture document:

docs/ACL_DEVELOPMENT_CONSTITUTION.md

Operational instructions:

AGENTS.md

Master product/engineering context:

docs/ACL_MASTER_ENGINEERING_CONTEXT.md

Relevant ADRs and agent-system documentation must also be respected.

Precedence:

1. ACL Development Constitution
2. Relevant ADRs
3. AGENTS.md
4. ACL Master Engineering Context
5. Existing implementation
6. AI assumptions

The repository is the source of truth for current implementation.

Historical requirements must never be treated as proof that a feature currently exists.

---

## 4. CURRENT DEVELOPMENT PRIORITY

The immediate development sequence is:

1. Registration
2. Student Dashboard
3. Institution/Admin Dashboard

This sequence is intentional.

Do not move forward simply because a module appears partially implemented.

Each phase requires inspection, implementation, testing, and verification.

---

# 5. REGISTRATION DOMAIN

Registration is currently the highest priority.

The system has multiple registration paths:

- student registration
- external learner registration
- JAMB verification
- manual/semi-manual verification

The implementation must distinguish student identity verification from account creation.

---

## 5.1 STUDENT REGISTRATION CONCEPT

The intended student onboarding flow is approximately:

1. Student enters JAMB registration number.
2. ACL attempts to retrieve matriculation/verification information.
3. ACL normalizes the provider response.
4. Student reviews/confirm retrieved information where applicable.
5. Student supplies an active email.
6. Student creates a password.
7. ACL creates/activates the appropriate account state.
8. Student receives access according to verification and entitlement rules.

Do not assume every student can be verified automatically.

Students who cannot be verified through the provider must have an appropriate manual/semi-manual path.

---

## 5.2 JAMB REGISTRATION NUMBER REQUIREMENTS

JAMB registration numbers must support historical and current formats.

Input requirements:

- minimum: 1 character
- maximum: 15 characters

Do NOT:

- require exactly 14 characters
- hard-code a specific year
- hard-code a prefix
- assume only modern formats
- reject historical formats merely because they differ from newer formats

The validation rule should represent the actual accepted ACL input contract, not assumptions about JAMB formatting.

---

## 5.3 JAMB VERIFICATION STATES

Verification must distinguish between:

- verified
- not_found
- invalid_input
- provider_timeout
- provider_unavailable
- temporary_failure
- manual_verification_required
- ambiguous
- pending

Critical rule:

A provider outage, timeout, network failure, or technical error is NOT evidence that the student is invalid.

For example:

provider_timeout != not_found

provider_unavailable != not_found

temporary_failure != not_found

The UI and account workflow must preserve this distinction.

---

## 5.4 JAMB PROVIDER ARCHITECTURE

The intended architecture is:

JambVerificationService
        ↓
JambProviderInterface
        ↓
Provider Adapter
        ↓
External JAMB verification mechanism

Application logic should depend on normalized verification results.

Provider-specific response structures must remain inside adapters.

This allows future replacement/addition of providers, including a possible official JAMB API, without rewriting registration business logic.

Potential providers may include:

- current web/browser extraction provider
- future official API
- alternative approved provider

Do not hard-code provider-specific behavior into controllers.

---

## 5.5 JAMB PROVIDER HISTORY

The project does not currently have direct official JAMB API access.

A direct HTTP approach is implemented against JAMB's ASP.NET Web Forms
"CheckMatriculationList" workflow.

`JambMatriculationVerificationService` performs a fresh GET (session cookies +
`__VIEWSTATE`/`__VIEWSTATEGENERATOR`/`__EVENTVALIDATION`), resolves the
examination option value from the live dropdown, then POSTs
`__EVENTTARGET=lnkSearch` with the examination value and registration number.
The HTML response is parsed semantically; no browser automation or
Zyte-style extraction is used.

This is historical context only.

Current repository implementation must always be inspected before assuming how JAMB verification works.

---

# 6. EXTERNAL LEARNER REGISTRATION

ACL is not restricted to verified Nigerian university students.

External learners are an important future/current platform audience.

External learner registration must remain separate from student institutional verification logic where their requirements differ.

External learners may eventually receive:

- paid courses
- learning paths
- selected free content
- ACLi access according to entitlement
- certificates/credentials
- other premium features

Do not grant institutional student privileges to external learners without an explicit authorization rule.

---

# 7. USER ROLES

Expected platform roles include:

- student
- external learner
- tutor/lecturer
- institution administrator
- educational partner
- platform administrator
- super administrator

Role dashboards should be distinct where the role's responsibilities differ.

Do not create one generic dashboard and merely hide menu items based on role.

---

# 8. AUTHORIZATION MODEL

ACL authorization should conceptually follow:

Authentication
→ Role
→ Permission
→ Scope
→ Capability

Authentication establishes identity.

Role establishes broad responsibilities.

Permission establishes allowed actions.

Scope establishes which records/institutions/resources the user may act upon.

Capability represents the actual operation being performed.

Authorization must be enforced server-side.

Frontend visibility is never a substitute for authorization.

---

## 8.1 INSTITUTION SCOPE

Institution administrators are scoped to their institution unless an explicit permission grants broader access.

An administrator belonging to Institution A must not automatically access Institution B.

Never rely on:

- hidden buttons
- frontend route hiding
- client-provided institution IDs
- browser state
- AI decisions

for authorization.

---

## 8.2 PLATFORM ADMINISTRATORS

The repository recently removed an obsolete universal platform-admin authorization bypass.

Do not reintroduce a hidden bypass.

If a platform/super administrator needs broad access, that access must be explicit, auditable, and represented in the authorization architecture.

---

# 9. STUDENT DASHBOARD

The student dashboard is the second major development phase.

It must be a dedicated student experience.

It must not simply reuse an administrator dashboard.

The student dashboard should eventually provide:

- academic overview
- enrolled/available courses
- current semester
- progress
- chapters
- lessons
- learning materials
- practice
- achievements
- relevant notifications
- ACLi entry where entitled
- account/profile functions

The exact implementation must be based on the current repository rather than assumptions.

---

# 10. ACADEMIC DATA MODEL

ACL must support multiple universities.

The core academic hierarchy is:

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

This hierarchy must remain data-driven.

Do not hard-code ACL around one university.

The data model should support:

- multiple universities
- multiple faculties
- multiple departments
- multiple programmes
- multiple levels
- multiple semesters
- different course offerings
- historical offerings
- discontinued offerings where appropriate

---

## 10.1 UNIVERSITY COURSE ACCESS

Verified students should receive access to courses associated with their institution and applicable academic context.

Course access should consider:

- institution
- programme
- level
- semester
- course offering
- verification status
- ACL entitlement rules

Premium or additional courses may require paid entitlement.

Access must be enforced on the backend.

---

# 11. ENTITLEMENTS

Subscription state and entitlement state are separate concepts.

A subscription may produce one or more entitlements, but application authorization should ultimately evaluate whether the user possesses the required entitlement for the requested capability/resource.

Examples:

- course access entitlement
- premium learning-path entitlement
- ACLi entitlement
- advanced practice entitlement
- external learner content entitlement

Never trust a frontend boolean such as:

isSubscribed=true

or:

paymentSuccessful=true

as proof of authorization.

---

# 12. PAYMENTS

Payment integrations must be server-authoritative.

Payment callbacks/webhooks must be:

- server verified
- idempotent
- replay resistant where applicable
- independent of browser claims

The browser must never be the authority for granting premium access.

Payment state and entitlement state must be represented correctly in the backend.

---

# 13. ACLi

The new AI system is named:

> ACLi

The previous AI implementation was intentionally removed/rebuilt.

Do not casually restore obsolete AI architecture.

The intended abstraction is:

Application
→ ACLi
→ AI Provider Abstraction
→ Provider Adapter
→ Model Provider

Potential providers include:

- DeepSeek
- Qwen
- Gemini
- OpenAI
- other compatible providers

The application should not depend directly on one provider.

---

## 13.1 ACLi CAPABILITIES

Potential ACLi capabilities include:

### Student

- study assistant
- highlighted phrase explanation
- paragraph explanation
- equation explanation
- question explanation
- progress analysis
- study recommendations
- practice assistance

### Content creation

- course generation
- chapter generation
- lesson generation
- educational image generation
- slide generation

### Administration

- institution analytics
- student counts
- department statistics
- administrative assistance
- controlled platform analytics

These are product capabilities, not authorization grants.

---

## 13.2 ACLi STUDENT ACCESS

Student ACLi is currently intended to be a paid/locked capability.

Important:

Visibility != authorization.

The UI may show an ACLi entry point, but backend entitlement checks must prevent unauthorized usage.

A student without the required entitlement must not be able to invoke ACLi by:

- directly calling an endpoint
- manipulating request parameters
- modifying frontend JavaScript
- calling hidden routes
- replaying requests

---

## 13.3 ACLi SECURITY

ACLi must never:

- bypass RBAC
- grant permissions
- expose unauthorized records
- bypass institution scope
- reveal API keys
- expose provider credentials
- bypass subscriptions
- bypass entitlements
- execute arbitrary AI-generated SQL
- access arbitrary database tables
- make authorization decisions without server-side enforcement

If ACLi needs data, it should access controlled application services/tools that already enforce authorization.

---

## 13.4 AI PROVIDER SECURITY

Provider API keys must remain:

- server-side
- environment/config based
- excluded from Git
- excluded from frontend bundles
- excluded from logs
- excluded from user-visible errors

Provider-specific logic belongs in adapters.

Controllers should not contain raw provider integration logic.

---

## 13.5 AI CONTENT WORKFLOW

AI-generated educational content should follow:

Draft
→ Human Review
→ Correction
→ Approval
→ Publish

AI generation alone must not automatically publish authoritative educational content unless an explicit approved workflow permits it.

---

# 14. PROGRESS DOMAIN

Student progress belongs to the application/domain layer.

ACLi may analyze authorized progress data, but ACLi does not own the underlying truth.

Examples of progress data:

- attempted questions
- scores
- chapters completed
- lessons completed
- course progress
- study history

The progress system remains authoritative.

ACLi should consume authorized progress information through controlled application services.

---

# 15. DATABASE

ACL uses MariaDB as the database standard.

Application code should use:

- Eloquent
- Laravel query builder
- Laravel database abstractions

The production application must use a least-privilege database account.

The MariaDB root account must never be used by application code.

Database changes must respect existing migrations, relationships, foreign keys, and data.

Do not casually drop tables or delete data.

---

# 16. ARCHITECTURAL STYLE

ACL follows:

> Modular Monolith First

Laravel remains the primary application framework.

Do not introduce microservices merely because they appear architecturally sophisticated.

A new service should be introduced only when there is a concrete architectural reason.

Prefer extending existing domain patterns over creating duplicate parallel systems.

---

# 17. SECURITY BASELINE

Every meaningful change should consider:

- authentication
- authorization
- RBAC
- institution scope
- IDOR
- mass assignment
- CSRF
- XSS
- SQL injection
- session security
- privilege escalation
- rate limiting
- file upload validation
- path traversal
- executable uploads
- payment security
- webhook security
- AI data leakage
- secret leakage

Production errors must not reveal:

- stack traces
- SQL queries
- filesystem paths
- credentials
- API keys
- provider secrets

---

# 18. FILE UPLOAD SECURITY

Any future upload feature must validate:

- authorization
- file type
- MIME type
- extension
- file size
- filename
- storage location
- path traversal
- executable-file risk

Uploaded files must not become an unintended code execution mechanism.

---

# 19. PERFORMANCE

ACL is intended to serve Nigerian users, including users on mobile devices and limited/expensive bandwidth.

Design priorities include:

- lightweight pages
- optimized images
- efficient database queries
- pagination
- caching where justified
- compressed assets
- PDFs
- embedded YouTube where appropriate
- minimal unnecessary JavaScript

Avoid unnecessary video hosting infrastructure.

---

# 20. UI / DESIGN DIRECTION

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

The landing page should be public-facing and should not be login-first.

The application should have consistent:

- header
- footer
- branding
- favicon
- email branding

The ACL logo supplied by the project owner should eventually be used consistently across the application and communications.

Do not replace the branding direction with a generic technology/cybersecurity aesthetic.

---

# 21. EMAILS

Application emails should eventually use ACL branding consistently.

This includes:

- logo
- typography
- appropriate colors
- professional educational tone
- correct application URL

Do not leak secrets or internal implementation information through email.

---

# 22. CURRENT SERVER / DEVELOPMENT ENVIRONMENT

The current application is being developed/deployed on an AWS EC2 Ubuntu environment.

Known environment context:

- Ubuntu 26.04.1 LTS
- approximately 7.6 GiB RAM
- approximately 7.0 GiB available in prior inspection
- approximately 28.9 GiB disk available in prior inspection
- no swap in prior inspection
- repository: /home/ubuntu/ACL
- MariaDB 11.8
- Redis 8
- Supervisor
- Nginx
- PHP and Laravel dependencies
- APP_URL=https://app.aclacademy.me

Cloudflare is used/planned for the production networking/security layer.

---

# 23. CURRENT GIT STATE CONTEXT

At the time this context was created, the repository contained important uncommitted registration work.

Known modified files included:

app/Http/Controllers/Auth/ExternalLearnerRegistrationController.php
app/Http/Controllers/Auth/StudentRegistrationController.php
app/Models/AcademicProgram.php
app/Providers/AppServiceProvider.php
routes/web.php

Known deleted file:

app/Services/Jamb/JambMatriculationVerificationService.php

Known untracked work included:

AGENTS.md
acl-dump.txt
app/Services/Jamb/JambProvider.php
app/Services/Jamb/Providers/
app/Services/Registration/
database/migrations/2026_09_10_000001_make_jamb_exam_value_nullable_on_student_registration_verifications.php
resources/views/auth/register-external.blade.php
resources/views/auth/register-student-school.blade.php

This state is historical context only.

Always run git status before making changes and treat the actual current repository state as authoritative.

Never discard these changes merely to simplify development.

Inspect acl-dump.txt before deciding whether it can be removed.

---

# 24. RECENT AUTHORIZATION WORK

The project recently addressed authorization behavior related to platform administration.

Recent commits included work around:

- scoped RBAC permission resolution
- removal of an obsolete platform-admin authorization bypass
- checkpointing before removing that bypass

Do not assume the current authorization implementation is perfect.

Inspect the actual code and tests before modifying it.

Do not reintroduce a universal authorization bypass.

---

# 25. DEVELOPMENT METHOD

For every implementation task:

1. Inspect.
2. Understand.
3. Identify the concrete problem.
4. Determine the smallest appropriate change.
5. Implement.
6. Syntax-check.
7. Run relevant tests.
8. Verify routes/database behavior where relevant.
9. Inspect diff.
10. Resolve every error.
11. Verify again.

Do not claim something is fixed merely because source code changed.

Use accurate verification terminology:

- implemented
- syntax verified
- test verified
- integration verified
- production verified

Only use the level actually demonstrated.

---

# 26. ERROR HANDLING WORKFLOW

If a command or test fails:

STOP.

Do not continue stacking changes on top of an unresolved error.

Instead:

1. capture the exact error
2. identify the root cause
3. inspect relevant code/configuration
4. make the smallest corrective change
5. rerun the failed verification
6. confirm success
7. continue only after verification

---

# 27. DATABASE CHANGE WORKFLOW

Before database changes:

1. inspect migration status
2. inspect relevant schema
3. inspect existing migrations
4. inspect relationships/foreign keys
5. understand existing data
6. determine whether the change is backward-compatible
7. implement
8. run migration/test verification

Never delete production data merely to make tests pass.

---

# 28. TESTING PRIORITY

Tests should cover behavior, not merely implementation details.

Registration testing should include at minimum:

- valid JAMB input within 1–15 characters
- 15-character input
- rejection beyond 15 characters
- rejection of empty input
- historical/non-modern formatting
- provider success
- provider not found
- provider timeout
- provider unavailable
- temporary failure
- ambiguous result
- manual verification path
- account creation behavior
- duplicate/conflicting registration behavior
- authorization behavior

Exact test names and implementation must follow the current repository.

---

# 29. CURRENT REGISTRATION PHASE

Before making further registration changes, inspect:

- routes/web.php
- StudentRegistrationController
- ExternalLearnerRegistrationController
- JambVerificationService
- JambProviderInterface
- provider adapters
- registration services
- AcademicProgram
- verification models
- migrations
- registration views
- existing tests
- authorization middleware/policies
- current database state where relevant

Determine what is actually implemented.

Do not rebuild registration blindly.

---

# 30. REGISTRATION PHASE EXIT CONDITIONS

Registration should not be considered complete until:

- routes work
- validation works
- JAMB input accepts 1–15 characters
- no hard-coded 14-character/year/prefix assumption remains
- provider success is handled correctly
- not-found is distinct from provider failure
- provider timeout is distinct from invalid student
- provider unavailable is distinct from invalid student
- manual/semi-manual verification is understood
- successful account creation works
- duplicate/conflicting cases are handled
- relevant tests pass
- authorization remains correct
- no unrelated regressions are introduced

Only after this gate should student dashboard work become the primary task.

---

# 31. STUDENT DASHBOARD PHASE

Once registration passes its gate:

Inspect the current implementation first.

Determine:

- existing student routes
- existing student controllers
- existing student views
- existing layout/components
- current academic models
- course access logic
- progress logic
- entitlement logic
- current navigation
- current authentication state

Then design the smallest coherent student dashboard implementation.

Do not assume the dashboard should be rebuilt from scratch.

---

# 32. ADMIN DASHBOARD PHASE

After the student dashboard gate passes:

Inspect:

- admin routes
- admin controllers
- admin views
- role/permission system
- institution scoping
- policies
- gates/middleware
- institution models
- analytics/query logic

Then implement/fix the institution/admin dashboard.

Institution scope must be verified with tests.

---

# 33. NO SCOPE CREEP

Do not use a registration task to:

- redesign the entire application
- rebuild unrelated dashboards
- introduce ACLi
- refactor unrelated modules
- replace the authorization system
- introduce microservices
- modify unrelated routes
- delete unrelated data
- install unnecessary packages

If a discovered problem does not block the current task:

document it

and return to the current task.

---

# 34. GIT WORKFLOW

Before risky work:

git status --short
git diff --stat
git diff

After logical work:

- inspect diff
- run tests
- create a focused commit when appropriate

Never use destructive commands to hide or bypass problems.

Never overwrite user work without explicit approval.

---

# 35. CODEx INITIAL AUDIT REQUIREMENT

When Codex begins work on this project, it must first read:

AGENTS.md
docs/ACL_MASTER_ENGINEERING_CONTEXT.md
docs/ACL_DEVELOPMENT_CONSTITUTION.md

Then inspect the repository.

For the current phase, the first action should be an audit only.

Report:

1. current git state
2. uncommitted changes
3. registration routes
4. registration controllers
5. registration services
6. JAMB provider architecture
7. registration models
8. registration migrations
9. registration views
10. registration tests
11. current student dashboard
12. current admin dashboard
13. security/authorization concerns
14. concrete registration problems
15. smallest next fix

Do not modify code during this initial audit.

Wait for explicit approval before implementing the first fix.

---

# 36. IMPORTANT DISTINCTION

The following documents describe intended behavior and engineering rules.

They do NOT prove implementation.

Always distinguish:

Requirement
vs
Design
vs
Implementation
vs
Verification

Only repository evidence and successful verification establish implementation status.

---

# 37. CURRENT WORKING PRINCIPLE

The project is being developed incrementally.

The correct sequence is:

Inspect
→ Understand
→ Change one thing
→ Verify
→ Resolve errors
→ Verify again
→ Commit/checkpoint
→ Continue

Never optimize for speed by sacrificing correctness.

Never optimize for architectural complexity when a simple Laravel solution is sufficient.

Never optimize for AI convenience at the expense of authorization, privacy, security, or maintainability.

---

# 38. FINAL RULE

ACL should be developed as a serious education platform.

Preserve the product identity.

Preserve the architecture.

Preserve user work.

Respect authorization.

Protect data.

Verify claims.

One task at a time.

ONE TASK
→ ONE CHANGE
→ VERIFY
→ RESOLVE ERRORS
→ VERIFY AGAIN

---

# 39. IMPLEMENTED REGISTRATION ARCHITECTURE (2026-09-10)

Student registration is implemented as `StudentRegistrationController` →
`JambMatriculationVerificationService`, which communicates directly with
JAMB's CheckMatriculationList endpoint using the ASP.NET Web Forms postback
workflow (GET for session/state, POST with `__EVENTTARGET=lnkSearch`,
examination value, and registration number, then semantic HTML parsing).
No browser automation or Zyte extraction is involved. The server accepts JAMB
registration values from 1–15 characters without a 14-character, year, prefix,
or alphanumeric-format assumption. Optional examination metadata is a provider
hint, not identity validation.

Only `verified` results reconciled to exactly one active ACL organization can
reach account creation. `not_found`, `invalid_input`, `provider_timeout`,
`provider_unavailable`, `temporary_failure`, `manual_verification_required`,
`ambiguous`, and `pending` remain distinct persisted states. Manual-review UI
is intentionally deferred to the admin phase; uncertain records cannot create
institutional memberships.

Student account creation is transactional, assigns the student role only at
the reconciled organization, syncs institutional enrolments, and records a
unique JAMB identity hash on the user. External registration remains separate
and grants only the platform-scoped `external.learner` role. Feature tests fake
the provider and cover validation, outcomes, reconciliation, conflicts, and
role scope.
→ ONLY THEN CONTINUE.
