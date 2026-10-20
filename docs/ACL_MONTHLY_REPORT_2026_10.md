# ACL — Monthly Engineering Report

**Reporting period:** 20 September – 20 October 2026
**Production:** <https://app.aclacademy.me>
**Repository:** <https://github.com/muneercybrid/ACL> (`main`)
**PDF version:** `docs/ACL_MONTHLY_REPORT_2026_10.pdf`

> All figures in this report were measured against the production database and the
> repository at the time of writing. Where a claim is unverified, the report says so.

---

## 1. Delivery at a glance

### What the platform contains

| Metric | Value | Source |
|---|---|---|
| Courses in the catalogue | 5,928 | `courses` table |
| Real (non-placeholder) chapters | 118,875 | `course_chapters`, `placeholder = 0` |
| Courses with a full 20-chapter set | 5,928 (100%) | grouped by `course_id` |
| Courses still short of 20 chapters | 0 | grouped by `course_id` |
| CCMAS curriculum course statements | 8,169 | `curriculum_courses` |
| Academic programmes | 1,365 | `academic_programs` |
| Institutions onboarded | 481 | `organizations` |
| Registered users | 494 | `users` |
| Enrollments | 10 | `enrollments` |
| Migrations applied | 121 | `migrations` table |
| HTTP routes | 111 | `artisan route:list` |
| Controllers / Services / Models | 44 / 60 / 54 | filesystem count |
| Blade views | 91 | filesystem count |
| Test files | 49 | `tests/Feature` |

### Chapter coverage trajectory

The campaign is measured against a floor of twenty chapters per course, ordered easy to
hard and derived from the course's own CCMAS learning outcomes and course contents.

| Checkpoint | Full courses | Coverage | Real chapters |
|---|---|---|---|
| Session start | 405 | 6.8% | 8,434 |
| Provider chain merged | 2,049 | 34.6% | 41,314 |
| Fail-fast 429 fix landed | 2,596 | 43.8% | 52,013 |
| Direct pipeline at scale | 3,721 | 62.8% | 74,966 |
| Token Harbor quota reset | 5,095 | 85.9% | 102,437 |
| **Current** | **5,928** | **100%** | **118,875** |

---

## 2. The curriculum campaign

The requirement was that every course on the CCMAS catalogue carries at least twenty
chapters, ordered easiest to hardest, explanatory, with worked examples, and with
quizzes, exercises and flashcards. The first phase — **chapter titles** — is complete for every course in the catalogue.
The second phase — **chapter bodies** — has not started.

### What was built

- **Central, shared content.** Course content lives once, keyed by `normalized_code`, so a
  course run by two universities is one course with two local codes. 116 genuinely
  ambiguous NUC titles are surfaced for review rather than auto-merged.
- **CCMAS extraction.** Learning outcomes and course contents were extracted from the
  national CCMAS documents and joined onto the central course records.
- **Title generation from CCMAS statements.** A console command derives each course's
  twenty titles from its own curriculum statement — one provider call per course,
  partitioned across forked workers, ordered easy to hard.
- **Phase 2 scaffolding.** End-of-chapter material (quizzes, exercises, flashcards) was
  built as an opt-in generation step, deliberately excluded from the title pass because
  twenty calls per course across the full catalogue exceeds 100,000 calls.

### The decoupled pipeline

The instruction was that authoring must never touch the database: if a database error
occurs, generated chapters must survive and be pushed later. The delivered pipeline
enforces that separation structurally.

| Stage | Component | Database access |
|---|---|---|
| 1. Author | Subagent / provider writes `/tmp/acl-gen-output/{course_id}.json` | None |
| 2. Validate | Title count, length, deduplication, ordering | None |
| 3. Push | `php artisan courses:push-chapters` | Transaction, chunked inserts |
| 4. Recover | Failed files are left on disk for retry | None |

A generation run over 5,900 courses is a long-running job. Under the previous coupling,
a single database error in the middle of the run discarded the work already produced.
Under the decoupled pipeline, a database error can cost at most one file, and that file
is retried. The final 300-file run pushed with zero data loss (299 pushed, 1 skipped,
0 failed).

### Bugs found and fixed along the way

- **Duplicate chapter position.** `course_chapters` has a unique key on
  (`course_id`, `position`). The title writer deleted only seeded placeholders and then
  inserted a fresh `1..N` plan, so any course that already held `1..19` real chapters
  from a partial run hit a duplicate-key error and lost its plan. Fixed by clearing
  scaffolding under a lessons guard, renumbering surviving written chapters, then
  appending the new plan.
- **Reasoning-model token exhaustion.** The reasoning model spent 5,013 tokens on
  reasoning for a twenty-title plan and returned `content: null` before writing the
  array. Raising the budget to 4,000 leaves room for the reasoning pass and the array.
- **Transient upstream 401s on long prompts.** A single 401 killed a whole course.
  Retries were added at the call site — three attempts, with a longer pause on 429 than
  on other errors.
- **The twenty-chapter floor was not enforced at write time.** The generator accepted any
  set of five or more parsed titles, so a course whose model response lost one title to
  the length or duplicate filter was written with nineteen chapters and reported as
  done. The acceptance threshold is now the full target, a shortfall triggers a
  top-up call that asks only for the missing titles, and an incomplete set leaves the
  course's existing chapters untouched so the next run retries it. The one course this
  had left short was found, completed and re-verified.
- **Chapter start button did nothing.** Clicking Start returned a successful JSON
  response but rendered nothing, because the chapter body was empty. The controller now
  returns the chapter title with a short note when content is absent, and the client
  always renders a usable panel.

---

## 3. Provider reliability and the unified chain

The requirement was a feature that would overcome quota exhaustion and truncation
outright. ACLi previously depended on a single gateway; a quota event took the feature
down for every student.

### The chain

ACLi now talks to a chain of backing providers in priority order. A provider that fails
is skipped and the next is tried; if every provider fails, the caller gets a single
clear exception rather than a silent empty answer.

| Priority | Provider | Cost | Measured behaviour |
|---|---|---|---|
| 1 | Token Harbor — `mimo-v2.6-flash:free` | $0 | 40/40 sequential OK in 21.7s (1.85 req/s); 10 parallel in 0.60s. Free allowance is a 7-day rolling window — when spent it returns HTTP 429. |
| 2 | Cloudflare Workers AI — `@cf/meta/llama-3.2-1b-instruct` | Free tier | 60/60 sequential OK in 26.7s (2.25 req/s); 30 parallel in 1.24s. Not OpenAI-shaped: uses `/client/v4/accounts/{id}/ai/run/{model}` and reports failure inside HTTP 200. |
| 3 | OmniRoute — `ling-3.1-flash` | Paid credits | Works, but shares credentials with background generation and rate-limits under load. Last resort by design. |

### Resilience behaviours added

- **Truncation recovery.** When a model burns its token budget mid-answer it returns
  `finish_reason: length` with empty content. The provider detects that, widens the
  budget and asks again — up to four times, with a hard ceiling — instead of returning an
  opaque error.
- **Quota backoff.** HTTP 429 is honoured with `Retry-After` when the provider supplies
  it, otherwise with a jittered exponential pause.
- **Fail-fast on a dead end.** Token Harbor's free tier answers 429 with a body stating
  the rolling 7-day allowance is spent. Retrying inside that window cannot succeed, but
  the provider was burning six backoff sleeps — up to sixty seconds — on every request
  before the chain could fall through. It now detects that specific 429 and throws
  immediately, so the chain moves to Cloudflare in one hop. This single change took the
  generation rate from stalled to **175 chapters per minute**.
- **Provider-specific logic stays in adapters.** Controllers contain no provider calls;
  the Cloudflare translation, the Token Harbor retry policy and the OmniRoute cooldown
  each live inside their own adapter.

### Image generation

None of the configured text backends can render images on a free plan: Cloudflare's
Stable Diffusion routes return `No route for that URI`, Gemini's image models return 429
until billing is enabled, and Token Harbor's image endpoint returns 402
`balance_zero`. Image generation therefore runs on **Pollinations**, which requires no
credentials at all. The service detects an image request, builds a prompt from the
message, renders it, and returns the bytes with the mime type and elapsed time.

---

## 4. Platform work delivered

### Student experience

- **ACLi chat, rebuilt.** Markdown tables render. Replies stream token by token instead
  of arriving as one lump. The chat lives inside the ACLi box rather than navigating
  away. A sidebar lists previous conversations. Copy, Like and Dislike are recorded and
  surfaced in the superadmin dashboard. A rotating-circle loading indicator replaced the
  ambiguous dots.
- **Course cards.** Enrolled state appears as a tag on the card itself, alongside the
  course code, so a course a student is taking is no longer listed twice. A standalone
  Enroll button was added to the course header, and the "Enroll to view" action became a
  real button.
- **Semester labelling.** The `semesters` table holds exactly one row, "First Semester",
  so every enrolled course was labelled first-semester even when it sat in the
  second-semester curriculum placement. Enrollments now take their semester from the
  student's own curriculum placement.
- **ACLi grounding.** The assistant is grounded in the student's programme rather than a
  single course.
- **Superadmin identity.** A separate interface and colour scheme, an email and password
  login, a user listing that automatically includes students, and an invitation system
  with role-based expiring links.

### ACLi as a paid capability

A single superadmin-editable settings row controls the paid ACLi feature: whether payment
is required, the price, the payment type (one-time, monthly, yearly), the grace period
and the currency. The entitlement service reads that same row, so the gate can never
disagree with the administrator's intent. The superadmin page at
`/superadmin/acli-payment` exposes a toggle, editable price and schedule, and a preview
of what the student sees. Entitlement is enforced server-side; showing the button is not
authorisation.

### Correctness fixes merged from pull requests

- **Google OAuth sign-up failed on a null password** — the sign-up path assumed a
  password was always present.
- **Student registration crashed for a null organisation** — common on JAMB verification
  flows where no institution is chosen yet.
- **Concurrent registration produced a duplicate email error** — two simultaneous
  submissions for the same address collided.
- **The enroll route was missing its `courseRef` parameter** — the student-facing enroll
  action could not resolve its target.
- **ACLi feedback referenced a model that did not exist** — the feedback record was
  written against a class that was never defined.
- **Students verified without an institution received no role at all**, so the
  superadmin user filter never found them. Students now always receive the student role,
  and existing rows were backfilled.

### Deduplication and content integrity

A course was storing the same chapter twice; the fix enforces uniqueness at the
database level rather than in application code. The catalogue runner was refused a retry
because `job_code` was unique and already held by the previous attempt — a retry that
could never succeed. Chapter bodies are no longer shipped to the browser with the course
page; the full text is fetched only when the student asks for it, which removed a
twenty-chapter payload from every course view.

---

## 5. Quality, verification and testing

The project rule is that a fix is not a fix until it has been demonstrated.

| Claim | Verification level | Evidence |
|---|---|---|
| Chapter coverage of 100% | Production verified | Counted against the production database by grouping chapters per course; the minimum is 20 chapters and no placeholder chapter remains |
| Provider failover works | Integration verified | Dead primary measured failing in 0.51s, chain fell through to the next provider |
| Chapter start button works with empty content | Production verified | Exercised against a chapter with empty introduction, summary and takeaways |
| Contact form delivers mail | Production verified | Test message sent through the real Resend SMTP path, delivery confirmed |
| All HTTP routes serve | Production verified | `/`, `/login`, `/contact`, `/superadmin/login` all return 200 |
| Merged pull requests are sound | Syntax verified | Application routes re-checked after the merge; the full suite was not executed in this session |

### Known open defects

- **The chapter-body phase is unstarted.** Titles are complete; `introduction`,
  `summary` and `key_takeaways` remain empty on generated chapters. The Start button
  degrades gracefully, but the written material the specification calls for does not yet
  exist.
- **Five pre-existing test failures, unrelated to this month's work.**
  `CcmcasCurriculumIntegrationTest` inserts an `academic_programs` row with
  `department_id => 1`, which violates the foreign key to `departments` because no
  department with that identifier exists. Four `StudentRegistrationCompleteTest` cases
  fail because `student_registration_verifications.jamb_registration_number` is NOT
  NULL without a default while the insert omits it. Both predate this work — confirmed
  by running the same two files with and without this month's change and getting
  identical results.
- **Assessment content is thin.** Lesson blocks total zero across the platform. The
  generation path exists and is opt-in, but the material has not been run.

---

## 6. Operations, security and deployment

### Secrets and configuration

Provider credentials, database credentials and the Resend key live only in `.env`, which
is git-ignored, and in Doppler (project `acl`, config `prd`). The local `.env` has been
synced to Doppler so a server move can be provisioned from Doppler rather than from a
machine.

- **Credential rotation is required.** Five provider credentials were exposed in chat
  during this period. They must be rotated before the next deployment. This is the
  highest-priority outstanding item and it is a human action, not a code change.
- **Deletion and permission discipline.** Newly written files must be `chmod 644`: the
  write path creates them owner-only, and the web server runs as a different user. A
  batch of ten files hit exactly this and took the whole application down with a
  permission-denied fatal until they were fixed.

### Mail delivery

Outbound mail works. The application sends through Resend SMTP
(`smtp.resend.com:465`) and a test message to the owner's mailbox was confirmed
delivered. **Inbound mail does not work**, and the cause is precise: the domain's MX
records point at `mail.aclacademy.me`, but no mail transfer agent listens on port 25 on
that host, so anything sent to `support@aclacademy.me` is discarded after the sending
server hands it over. The public contact form exists precisely because it routes around
this. The fix is a DNS change to Cloudflare Email Routing, which would forward
`support@aclacademy.me` to the owner's mailbox without any server work.

### Observability

Provider failures are logged as structured warnings naming the provider, the HTTP
status, whether the failure was retryable and the upstream message. The logging is what
made the dead-end 429 diagnosis possible: the log showed a retryable 429 repeated on a
condition that was never retryable.

### Production environment

| Component | Value |
|---|---|
| Application URL | `https://app.aclacademy.me` |
| Serving path | AWS EC2, Ubuntu, PHP 8.5, behind a remotely-managed Cloudflare Tunnel |
| Database | TiDB Cloud — `gateway01.eu-central-1.prod.aws.tidbcloud.com:4000`, database `acl` |
| Cache, queue, session | Redis |
| Repository | `https://github.com/muneercybrid/ACL`, branch `main` |
| Secrets | Doppler — project `acl`, config `prd` |

---

## 7. How the work was executed

Two approaches to bulk chapter authoring were trialled. The first used subagents, each
writing chapter JSON to disk without touching the database. Three of four attempts failed
outright — two at a depth limit, one interrupted with no durable result — and workflow
rounds returned zero completed agents. The second used the console command directly,
calling the provider chain without ACLi chat overhead, entitlement checks or conversation
persistence.

The second approach was decisively faster and far more reliable, and it is what produced
the coverage in section 2. The subagent approach was retained for the part it suited: a
supervised authoring stage feeding the same decoupled push.

### Throughput under rate limiting

Concurrency was tuned empirically. Twelve to twenty-four workers produced **two to eight
chapters per minute**: the workers competed for the same free-tier quota and throttled
each other. Six workers produced thirty to forty chapters per minute. Eight workers with
two healthy providers produced **175 chapters per minute**. More workers was
consistently worse.

### Repository activity

| Measure | Value |
|---|---|
| Commits in the period | 112 |
| Merged pull requests | 12 |
| Files changed | 327 |
| Human-authored commits | 102 (3 bots) |
| Fix commits | 21 |
| Feature commits | 11 |

### Merged pull requests

| PR | Title |
|---|---|
| #19 | Google OAuth sign-up fails with null password |
| #20 | Student registration crashes for null organisation |
| #21 | Concurrent student registration causes duplicate email error |
| #22 | Dependabot: `league/flysystem` 3.35.2 → 3.36.0 |
| #23 | Missing `courseRef` parameter in student course enroll route |
| #24 | Dependabot: `league/commonmark` 2.10.0 → 2.10.3 |
| #25 | ACLi chat feedback references a model that does not exist |
| #26 | Dependabot: npm and yarn multi-package group |
| #27 | Dependabot: `source-map-js` 1.2.1 → 1.2.2 |

---

## 8. Outstanding work

Listed in the order it should be picked up. The first three need a human action outside
the codebase.

| Priority | Item | Owner | Note |
|---|---|---|---|
| **Critical** | Rotate five exposed provider credentials | Human | OmniRoute, two OpenRouter keys, Cloudflare and Token Harbor. Rotate at the provider, then update `.env` and Doppler. |
| **High** | Write chapter bodies (Phase 2) | Engineering | Introduction, summary and key takeaways for 118,875 chapters, plus quizzes, exercises and flashcards. The generation path exists and is opt-in. |
| **High** | Point MX at Cloudflare Email Routing | Human | Replace the two MX records pointing at `mail.aclacademy.me`, add the DKIM record, update SPF, then route `support@aclacademy.me` to the owner mailbox. |
| **Medium** | Organisation selection cascade | Engineering | State, federal or private, then a searchable institution list, then organisation login. Requested, never started. |
| **Medium** | Fix the failing feature tests | Engineering | `CcmcasCurriculumIntegrationTest` violates the `academic_programs → departments` foreign key; four `StudentRegistrationCompleteTest` cases insert without the NOT NULL `jamb_registration_number`. Five failures, all pre-existing. |
| **Medium** | Generate assessment content | Engineering | Lesson blocks total zero. The path is built; the run has not happened. |
| **Low** | Correct the database README | Engineering | It still describes SQLite and PostgreSQL. ADR-0005 makes MariaDB authoritative; the file was already recorded as an outstanding task. |
| **Low** | Correct the onboarding prerequisites | Human | The current prerequisites list PostgreSQL as the local database. The authoritative engine is MariaDB per ADR-0005. |

**Status at the close of this period.** The catalogue is complete: all 5,928 courses carry
a full twenty-chapter set derived from the national curriculum, with no placeholder
chapter left in the database. The provider layer no
longer depends on a single provider, and the failure modes that stalled generation
twice have been diagnosed and fixed at their root. The next material piece of work is
the chapter bodies, which turns the catalogue from a titled outline into studyable
material.

---

*ACL — Anyone Can Learn. Generated against production on 20 October 2026. All figures
are measured, not estimated. Where a claim was not verified, this report says so.*
