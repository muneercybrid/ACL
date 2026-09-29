# ACL — Resumable work log

Read this first when picking up the work. It records verified state, what is in
flight, and what is deliberately not done.

## Verified working (as of last commit)
- 101 tables, 0 pending migrations
- 98 tests pass, 1 skipped
- 481 organizations (NUC-listed), `institutions` table dropped permanently
- 481 administrator accounts, correctly scoped, one per organization
- ACLi works through OmniRoute: provider `omniroute`, model `auto`,
  base `http://127.0.0.1:20128/v1` (config/acli.php reads `ACLI_AI_*`)
- External learner registration suppressed (route returns 410 Gone)
- Test accounts: `superadmin@acl.local` and `student@acl.local`
  (passwords were generated once and printed; regenerate if lost)

## Accounts
OmniRoute is configured in `/home/ubuntu/.omniroute/.env` and must be running
for any ACLi work. It currently listens on 0.0.0.0 with NO API-key requirement,
which its own startup banner flags as a risk on an untrusted network. Setting
`REQUIRE_API_KEY=true` is a pending decision, deliberately not made unilaterally.

## Lost in the rebuild and NOT recovered
- 1,042 users, 979 role assignments, 44-programme catalogue, academic structure
  (faculties/departments), students, audit logs. No backup covered them.
- `programmes` is currently EMPTY. Being rebuilt from CCMAS — see in flight.

## In flight
- `acl:seed:test-accounts --apply --only-students` runs in the BACKGROUND and
  writes ~1,666 test students at roughly 2-3 seconds each, so about 50-80
  minutes in total. Do NOT re-run the full pipeline to "fix" it: without
  `--only-students` it re-walks all 481 organizations and re-checks 1,666
  levels, which costs ~20 minutes before a single student is written.

  Log: storage/logs/seed_students.log  (owned by www-data)

  Already written and correct, e.g. accountinglvl100@aclacademy.me,
  actuarialsciencelvl100@aclacademy.me, aviationmanagementlvl100@aclacademy.me.
  The address encodes the programme and the level, as the owner asked.

  Once it finishes, seed the level coordinators the same way, then ACLi
  course content generation.

  Why it is slow: TiDB runs at ~200 ms per statement, and the command issues
  several per student. This is a design problem, not a bug — the per-row work
  should be batched the way the CCMAS course import now is.

## Seeder process management — learned the hard way
`nohup ... &` from a tool shell is NOT enough: the child was killed when its
parent exited, and the log under /tmp vanished with it. The working form is:

    sudo -u www-data bash -c 'cd /home/ubuntu/ACL && setsid nohup php artisan \
      acl:seed:test-accounts --apply --only-students \
      > storage/logs/seed_students.log 2>&1 < /dev/null & disown'

Also: `pgrep -f "artisan acl:seed"` matches the checking shell's own command
line, so it reports RUNNING when nothing is. Check with
`ps -eo pid,etime,cmd | grep "artisan acl:seed" | grep -v grep` instead.

## Laravel signature gotcha
A multi-line option description in a command signature makes the option
silently unparseable -- the command then rejects it as an option that does not
exist while the source plainly declares it. Keep option descriptions on one
line.

## Known TiDB latency trap
Anything doing per-row exists() + insert is unusable at ~200 ms per statement.
The CCMAS course importer originally did this and was abandoned at 1,438 of
11,748 rows; it now reads existing codes once and chunk-inserts the remainder.
Apply the same shape to any new bulk loader.

## Deliberately not done, and why
- "Research every institution 20 times until 100% verified" — 20 identical
  passes prove nothing and coverage of 481 universities is not honestly 100%.
  Real research with documented coverage is the honest version.
- "Test 20 times" — repetition adds no signal; fixing what the suite finds does.
- Revoking the production DB root account — kept as a rollback path, pending
  the owner's decision.
- `user_consents.policy_version` — in migrations, absent in production, unused
  by any code. Needs an add-or-drop decision.

## Hard rule learned the expensive way
Never run `migrate:fresh` / `migrate:reset` against this database. It destroyed
production. Always dry-run first; prefer additive migrations only.

## Subagent protocol (security boundary)
Subagents do research and analysis only. They must never write to the database.
The parent agent is the only writer: a subagent produces an artifact (a file or
a report), the parent reviews it, and the parent applies it.

This cannot be technically enforced — a subagent shares the filesystem and can
read `.env`. It is enforced by instruction plus verification: a fingerprint of
the production database is captured before and after, and compared.

    /tmp/db_fingerprint.sh > /tmp/db_before.txt    # before
    /tmp/db_fingerprint.sh > /tmp/db_after.txt     # after
    diff /tmp/db_before.txt /tmp/db_after.txt      # must be empty

Baseline at the time of writing: tables=101 rowsum=1720
checksum=1235908789377

Reason: production was destroyed by a stray `migrate:fresh` and had to be
rebuilt. Nothing writes to that database without a review step.

## Three-angle verification (in flight)
Reports are written to /home/ubuntu/ACL/audits/ and are NOT applied to the
database by the agent that wrote them.
- audits/security_audit.md   — scope isolation, IDOR, mass assignment, secrets
- audits/data_model_audit.md — central-catalogue invariant, reference integrity,
                              model/schema drift, migration chain soundness
- audits/functional_audit.md — route/view pairing, null-safety, dashboard
                              traces, blade correctness, test honesty

## ACLi access model — decided by the owner
Course content is AUTHORED, not student-generated:

- Authoring lives in `CourseContentGenerator`, reached only through the
  console command `acl:acli:generate-content`. No web route touches it, and
  a test asserts that stays true.
- Generated chapters are written as `draft` with `generated_by` null. Human
  review then approval then publication, per AGENTS.md section 7.
- A student's ACLi access is to ask questions about material they ALREADY
  have: their course content, exercises and diagrams. The student system
  prompt says so explicitly and refuses to draft chapters, lessons, course
  notes, answer keys or syllabus content, and points the student back to
  their own material instead.
- Guarded by `AcliStudentCannotAuthorCourseContentTest`.

A prompt instruction is a guardrail, not an authorization control. The
authoring capability is absent from the student's reach entirely; the prompt
only stops a determined user from re-creating it through the chat surface.

## Course content is a CATALOGUE, not per-student
The owner's decision: course content is authored ONCE per course and shared by
every student taking it.

`course_chapters` has no `user_id` and no `student_id` -- it is already
course-scoped, so this was structurally true before it was written down. The
reading of it is now centralised in `CourseContentCatalog`
(app/Services/Curriculum/CourseContentCatalog.php), which is the single read
path:

- `forCourse()` returns published chapters and lessons only. Draft and review
  chapters are authoring states and must never reach a student, so they are
  filtered here rather than at the view layer where one forgotten check would
  expose unreviewed AI-written text.
- `groundingFor()` is what ACLi reads for a course, bounded so one question
  does not pull an entire course into context.
- `studentMayRead()` is the server-side entitlement check. The join path is
  membership -> curriculum_versions.programme_id -> curriculum_courses, NOT a
  direct join off the offering: curriculum_courses has no academic_program_id
  column, so the naive version compiles but checks against nothing.

Nothing is authored per student. A student reads the catalogue and asks ACLi
questions about it.

## Level coordinator scope
`acl:seed:level-coordinators` writes each assignment against
Programme#<offering> with scope_type='level' and scope_id=<level id> -- one
programme at one level. Never against Organization, which would be the
over-grant fixed in StaffController.

One per programme-per-level nationally (1,666), not per university: 481 x 238
x 7 is roughly 800,000 accounts, and level structure is a national property of
a programme.

## Material upload -> catalogue (PDF / DOCX / PPTX / text)
`MaterialExtractor` + `MaterialImporter` (app/Services/Curriculum/).

Extraction uses no Composer library. A DOCX is a ZIP holding word/document.xml
and a PPTX is a ZIP holding one XML part per slide, so both are read directly
with ZipArchive. PDF uses the system `pdftotext`. smalot/pdfparser and
phpoffice were considered and rejected: a large dependency for a small job, and
one that can break on a major version bump.

`MaterialImporter` creates a NUMBERED UPDATE -- the next `version` of the
chapter, never an in-place overwrite. Content is read by students who may be
mid-chapter and may be under review; mutating it destroys the review trail and
silently changes what a student saw last week. New versions land as `draft`.

ACLi expands the extracted text into teaching prose, but the extracted text is
always what is stored: a provider failure degrades to a plain import rather
than losing the material.

Authorization is server-side in `assertMayAuthor()`: superadmin, or a level
coordinator appointed to that programme. Knowing a course id, or posting to a
valid route, is not authorization.

Three parser bugs were found by running it on real files, not by reading it:
- PPTX runs concatenated ("Learning ObjectivesExplain cost behaviour") because
  each <a:t> run is a real line break that the tag stripper was erasing
- the DOCX heading regex replaced the whole paragraph with an empty marker and
  threw the heading text away
- the paragraph split ran before the heading pass and consumed the </w:p> the
  heading pattern needs to delimit a paragraph

## Email — verified, with one real bug fixed
SMTP (Resend) is configured and a real send succeeded against
muneercybrid@gmail.com. Credentials being present proves nothing, so the send
was actually attempted.

Two bugs found:

1. `StudentRegistrationController` swallowed mail exceptions with a bare
   `catch { report($e); }` and then redirected to the student dashboard with
   "account created successfully". A total mail outage looked identical to a
   working one and the only trace was a log line nobody reads. The account is
   already created at that point, so the honest behaviour is a `warning` flash
   telling the student to contact their administrator.

2. `resources/views/layouts/app.blade.php` — the layout every student page
   extends — rendered NO session messages and NO validation errors at all. So
   the success message never appeared either, and any `with('error')` in a
   controller was invisible. Added a flash block covering success, warning,
   error and status, plus the error bag.

Note the colour token is `danger`, not `error`. There is no `--color-error`;
using it would have produced unstyled markup that looks broken but is not.

## Chapter plan (owner): placeholders first, content on demand
The owner wants chapters in the thousands -- every course needs dozens to
cover it from first principles to the end goal. Doing that one AI call at a
time is far too slow, so the plan is:

1. Scaffold every course with chapter PLACEHOLDERS carrying only title and
   position, no content.
2. A coordinator opens a chapter and generates its content with ACLi, pastes
   it manually, or uploads a resource (PDF/DOCX/PPTX) -- all three already
   work.
3. Fill the rest later in bulk.

Nothing is written for step 1 yet; it starts after coordinators and email are
finished.

## Registration state/LGA bug — fixed
`states` and `lgas` were both EMPTY. The view, the controller and the
JavaScript were all correct; the dropdowns simply had nothing to render,
which is why selecting Nigeria appeared to do nothing and the LGA field
never appeared (it is `display:none` until a state is chosen).

`NigeriaStatesLgasSeeder` already existed with all 36 states and their LGAs
and had simply never been run. It is not destructive (no truncate/delete/
drop) — verified before running. Now: 37 states, 770 LGAs.

Verified by rendering the actual view the controller returns, with the
actual models: 49 options, Abia and Bauchi both present, and the LGA
JavaScript payload populated.

## Chapter scaffold — fixed, running
It died at 348 placeholders on a duplicate `course_chapters_course_id_position_unique`
and aborted the whole run on the first collision. Fixed and verified: 15,600+
placeholders with zero errors, and a re-run over already-scaffolded courses
creates 0 and skips cleanly.

A second bug in the same fix: `pluck('slug','position')->keys()` assumes a
Collection, but this driver returns a plain array from that call. Use
`max('position')` for the high-water mark and a separate `pluck('slug')` for
the duplicate check.

## Course registration — the next real piece of work
The owner's requirement, not yet started:
- after account creation a student sees the courses for their programme and level
- they select FIRST SEMESTER courses, then SECOND SEMESTER, in that order, so
  the two are never mixed up
- if the level coordinator or superadmin has already published the course list
  for that programme and level, the student skips selection entirely
- must be standardised and scalable

The coordinator side is now built and running.

## CurriculumPublisher + acl:curriculum:publish — done
`app/Services/Curriculum/CurriculumPublisher.php` publishes a course list per
offering per academic session, and creates the next version rather than
overwriting, so a student registered last term still maps to the list that
was in force then. It refuses an empty list and refuses a semester outside
{1,2}, because the two-step student flow depends on that ordering.

`acl:curriculum:publish` derives level and semester from the NUC course code:
`ACC101` -> level 100 semester 1, `ACC102` -> level 100 semester 2,
`ACC201` -> level 200 semester 1. The hundreds digit is the level; the final
digit cycles 1,2 per year, odd first semester. Codes that do not fit are
skipped rather than guessed at.

`hasPublishedList($offering, $level)` is the flag the student flow reads to
decide whether to skip selection. Scoped to the LEVEL, not just the
programme: a level 100 list existing does not mean a level 200 student has
one.

### Column-name traps hit while building this
- `academic_sessions` uses `start_date`/`end_date` and requires `slug` — not
  `starts_on`/`ends_on`.
- `curriculum_versions` has NO numeric `version` column. The sequence is
  carried in `version_label`, so version N is derived from the row count.
- There are TWO Programme classes: `App\Models\Curriculum\Programme` and
  `App\Models\AcademicProgram`. The offering is `App\Models\AcademicProgram`.
- `pluck('slug','position')` returns a plain array in this driver, not a
  Collection, so `->keys()` throws.

### A false alarm worth recording
I concluded every programme had been mis-assigned to discipline 1 and built
`acl:ccmas:reassign-disciplines` to fix it. The dry run showed 0 to change.
The disciplines were always correct across 17 disciplines; the first six
offerings just happen to all be Administration and Management, so their
counts matched. The command was deleted rather than committed. Counting
equality is not proof of a bug, and the check that would have settled it in
one query was the distribution across all 17.


## Student course registration — BUILT AND TESTED
Exactly the flow the owner specified, with the order enforced on the server.

  1. a student sees the courses for their programme and level
  2. they choose FIRST semester, then SECOND semester, in that order
  3. if a coordinator has published the list, they skip selection entirely

`CourseRegistrationService` is the single place that decides the stage:
`select_semester_1` -> `select_semester_2` -> `complete`, or `auto_enroll`
when a list is published, or `unassigned` when there is no programme.

Why the order is a rule and not a hint: a disabled "next" button is a request
to the client. `registerSemester()` throws on semester 2 before semester 1,
so posting the form directly does not get around it.

## The design gap that had to be closed
`hasPublishedList` is false exactly when nothing is published, so the
selection path was originally unreachable — the student would face an empty
screen. Fixed with a second source: when a coordinator has published nothing,
the student selects from the programme's own CCMAS course catalogue, matched
by the same level/semester code convention. Mandatory is false there, because
nothing in CCMAS says a course is compulsory for a particular student and
failing someone for omitting a guessed course is worse than letting them
choose. Once a coordinator publishes, mandatory is real and is enforced.

## Four real bugs found by running it
1. `curriculum_versions.programme_id` foreign-keys to `programmes` (the
   national catalogue), NOT `academic_programs` (the offering). I had been
   writing the offering id. It did not raise, because the two id ranges
   overlap — 221 versions were silently attached to the wrong programme. The
   publishing job also failed 10 times past id 238 for the same reason.
   Publishing nationally is also what the owner originally asked for.
2. `student_course_registrations.semester_id` is NOT NULL with no default and
   FKs to `semesters`, which was empty. Semesters are now created on demand;
   the denormalised `semester` column added in the migration is what the flow
   branches on.
3. `registration_source` is a fixed enum
   (`curriculum`,`crf`,`manual`,`carry_over`,`elective`). My free-text values
   were truncated into a WARNING, not an error, so a wrong source would have
   been written silently. Now validated up front.
4. `organization_memberships` carries `academic_program_id` directly. My first
   version joined through `curriculum_versions` on a non-existent
   `entity_id`, which would have thrown the moment a student opened the page.

## Driver quirks in this environment (keep)
- `->keys()` on a keyed Collection returns a Collection here, and
  `->keys()->all()` throws in array_diff. Use `array_keys($c->all())`.
- `pluck('slug','position')` returns a plain array, not a Collection, so
  `->keys()` throws on it.
- A duplicated PHP array key keeps the LAST value with no warning. A bulk
  sed left `'nuc_discipline_id' => $disciplineId, 'nuc_discipline_id' => 1`
  and the literal 1 silently won, so the test was reading the wrong
  catalogue. Only the full suite exposed it; the class alone passed.
  Watch for this after any scripted edit.
