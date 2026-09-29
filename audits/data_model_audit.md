# Data Model & Architectural Integrity Audit — ACL

**Scope:** data model, schema↔model alignment, reference integrity, migration-chain soundness.
**Method:** read-only. No database was contacted. Schema facts were derived by statically parsing all
100 migration files in `database/migrations/` into a table→column→foreign-key map
(`scripts/audit_schema_map.php` → `scripts/audit_schema_map.json`, 101 tables), then cross-referenced
against every model in `app/Models/` (`scripts/audit_model_drift.php`,
`scripts/audit_required_columns.php`). No `migrate`, `db:seed`, `tinker` or write query was run.
AGENTS.md §4, §5, §12, §14, §17 observed: no code was modified, no database touched, no destructive action taken.

**Severity scale**
| | |
|---|---|
| **CRITICAL** | Breaks on a fresh database, or corrupts/losses data, or is a live authorization hole. |
| **HIGH** | Real production bug: a code path silently violates a stated invariant or writes a row that cannot be read back. |
| **MEDIUM** | Design flaw or partial enforcement: the model permits an invalid state even though normal callers avoid it. |
| **LOW** | Inconsistency or documentation debt. |
| *DESIGN* | Deliberate choice, correctly implemented. Not a bug — documented so it is not "re-found" and mis-flagged later. |

---

## How to read this document

Each finding is tagged **[BUG]** (real defect) or **[DESIGN]** (deliberate choice worth writing down).
Line numbers refer to the state of the repository at the time of the audit.

---

# 1. Does the model actually enforce the central-catalogue invariant?

**The invariant:** a *programme* is one row in a national catalogue and is never owned by an organization.
An organization's *offering* lives in `academic_programs` and points at exactly one catalogue row via
`nuc_programme_id`.

### 1.1 [BUG · HIGH] `programmes.organization_id` is a real foreign key, so the schema permits an organization to own a catalogue programme

- **File/line:** `database/migrations/2026_09_11_200000_create_programmes_table.php:13`
  ```php
  $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
  ```
- **What is wrong:** the column is not a soft reference; it carries a genuine foreign-key constraint onto
  `organizations`. The stated invariant is that a catalogue programme has *no* owning organization. The
  schema encodes the opposite of the design. It is `nullable`, so the happy path is honoured, but nothing
  prevents a programme from being bound to an organization, and once bound, `nullOnDelete` silently detaches
  it when the organization goes away.
- **Who can write it:** `Programme::$fillable` (`app/Models/Curriculum/Programme.php:14-29`) correctly omits
  `organization_id`, so mass assignment through the model is blocked. However:
  - `app/Console/Commands/InstitutionRebuild.php:588` treats `['programmes','organization_id']` as a live
    reference edge to probe for orphan institutions.
  - Any `Programme::create([...])` with `forceFill`, a raw `DB::table('programmes')` insert, or a future
    `$fillable` edit can set it. There is no model guard, no `saving` hook, and no DB-level rule preventing it.
- **Consequence:** the "one row per degree, not per university" property is enforced only by the current
  `$fillable` list — i.e. by the absence of a code path, not by a rule. One careless edit reintroduces
  per-university programmes and the near-duplicate catalogue the migration was written to eliminate.
- **Smallest correct fix:** drop the FK and the column, or keep the column but make it explicitly *not* a
  catalogue field. The minimal, safe, reversible step is a follow-up migration that removes the foreign-key
  constraint and comments the column as deprecated (data-preserving), then a later migration drops the
  column once no code references it. Do **not** drop it in the same change as the code change.

  ```php
  // new migration, data-preserving
  Schema::table('programmes', function (Blueprint $table) {
      $table->dropForeign(['organization_id']);   // remove the only rule that ties a programme to an org
  });
  ```
  (There is no `down()` risk: the FK was created inline in `create_programmes_table`, so Laravel named it
  `programmes_organization_id_foreign`.)

### 1.2 [BUG · HIGH] `academic_programs.nuc_programme_id` has no foreign key, so "exactly one catalogue programme" is not enforced at the database

- **File/line:** `database/migrations/2026_09_16_030000_add_production_only_columns.php:50-51`
  ```php
  $table->unsignedBigInteger('organization_id')->nullable();
  $table->unsignedBigInteger('nuc_programme_id')->nullable();
  ```
  Both columns are plain unsigned big integers. `nuc_disciplines` (added in the same file at line 71) is also
  a soft reference, and so is `programmes.nuc_discipline_id`
  (`database/migrations/2026_09_12_000300_ensure_curriculum_tables.php:66`).
- **Consequence:** the only thing that makes an offering reference a catalogue programme is the presence of
  the id. The database will happily accept `nuc_programme_id = 99999` or `NULL`. An offering that references
  no catalogue programme — the exact "disconnected state" the `AcademicProgram` docblock says the model
  exists to prevent — is storable and undetectable. The only guard is the application passing the value.
- **Note (already-fixed, do not re-flag):** `nuc_programme_id` *was* missing from `AcademicProgram::$fillable`
  and that has been fixed (`app/Models/AcademicProgram.php:32-33`). The residual bug is the missing FK, not
  the missing `$fillable` entry. The test suite comment at `tests/Feature/CourseVisibilityTest.php:52-54`
  explicitly notes the column exists and that a *different* column (`academic_programs.nuc_discipline_id`)
  does **not** — confirming `nuc_programme_id` on `academic_programs` is a soft reference by design in this
  codebase, not an accident.
- **Smallest correct fix:** none required *if* the soft reference is intentional (it matches this codebase's
  established convention of loose academic references — see §2). **If** hard integrity is wanted, add
  `->index()` at minimum so the offering→catalogue join is not a table scan, and document the soft reference
  in the ADR. The correct call is a design decision, not a one-line change; flagging it here so the owner
  decides explicitly.

### 1.3 [BUG · MEDIUM] `Programme::booted()` backfills `normalized_name` and `code` from `name`, but `code` is only backfilled when `blank`, and the offering path can produce collisions

- **File/line:** `app/Models/Curriculum/Programme.php:61-71`
  ```php
  if (blank($programme->normalized_name) && filled($programme->name)) { ... }
  if (blank($programme->code) && filled($programme->name)) { $programme->code = ProgrammeCatalogue::deriveCode(...); }
  ```
- **What is wrong:** `deriveCode` (`app/Services/ProgrammeCatalogue.php:69-85`) is deterministic and truncating
  (first 3 chars of word 1 + first 2 of up to 3 more words). Two different national programmes whose names
  differ only in later words ("Computer Science" vs "Computer Science Education") can derive the *same* code
  if the first four words match after normalisation. `backfillCodes` (`ProgrammeCatalogue.php:107-148`)
  handles this collision with a numeric suffix, but the `booted()` hook does **not** — it writes the raw
  derived code and will happily create a duplicate if two programmes are created in the same request.
- **Consequence:** `Programme::create(['name' => X])` for two distinct programmes can produce two rows with the
  same `code`. There is **no unique index on `programmes.code`** (verified: `programmes` unique_columns is
  empty), so the duplicate persists silently. `offeringFor` copies `$programme->code` into the offering's
  `code`, and `academic_programs` *does* have `unique(['department_id','code'])` — so two organizations
  offering two different catalogue programmes that collided on code, in the same department, will hit a
  unique-constraint error at offering-creation time.
- **Smallest correct fix:** in `booted()`, only backfill `code` when it is safe, or make `deriveCode` collision-
  resistant by appending a short hash of the full normalized name. The minimal change is to have `booted()`
  call a collision-aware version of the same logic `backfillCodes` uses, rather than the raw derive.

### 1.4 [BUG · MEDIUM] `offeringFor()` can race and create duplicate offerings for the same (organization, programme)

- **File/line:** `app/Services/OrganizationOnboarding.php:280-301`
  ```php
  $existing = AcademicProgram::where('organization_id', $organization->id)
      ->where('nuc_programme_id', $programme->id)->first();
  if ($existing) { return $existing; }
  return AcademicProgram::create([...]);
  ```
- **What is wrong:** this is a read-then-write with no unique index and no transaction/lock. The only unique
  constraints on `academic_programs` are `unique(['department_id','slug'])` and `unique(['department_id','code'])`
  (`database/migrations/2026_08_13_140004_create_academic_programs_table.php:23-24`), and the slug/code are
  derived from the organization and programme, so in the common case the constraint *does* catch a second
  insert — but only for the *same* organization+programme (same slug). If two different organizations offer the
  same programme they get different slugs, so that is fine. The genuine gap: if the same organization offers the
  same programme **in two different departments** (legitimate — a faculty may offer the degree in two
  departments with different codes), the (department_id, slug) unique key differs and a duplicate
  (organization, nuc_programme) pair is created silently, because there is no `unique(['organization_id','nuc_programme_id'])`.
- **Consequence:** a student can end up attached to one of several duplicate offerings of the same degree; the
  "one offering per organization per programme" property is not guaranteed. The test
  (`tests/Feature/OrganizationOnboardingTest.php:176-177`) only exercises the happy single-department path and
  would not catch this.
- **Smallest correct fix:** add a unique index on `academic_programs (organization_id, nuc_programme_id)`.
  This is safe on the current data (which has no such duplicates per the design) and closes the race. If
  multiple departments per offering is a real requirement, the uniqueness should instead be on
  `(organization_id, nuc_programme_id, department_id)` and the duplicate-detection query updated to match.

### 1.5 [BUG · MEDIUM] `offeringFor()` does not validate that `$departmentId` belongs to `$organization`

- **File/line:** `app/Services/OrganizationOnboarding.php:280,293`
  ```php
  public static function offeringFor(Organization $organization, Programme $programme, ?int $departmentId = null): AcademicProgram
  ...
  'department_id' => $departmentId,
  ```
- **What is wrong:** the department is written straight through with no check that it is a department of this
  organization. `departments.faculty_id → faculties.organization_id` gives a two-hop path to verify ownership;
  nothing uses it.
- **Consequence:** an offering can be created that points at a department of a *different* organization. Because
  `department_id` carries a real FK, the insert succeeds; the offering is simply wrong. The student's membership
  then points at an offering whose department belongs to another university.
- **Smallest correct fix:** before `AcademicProgram::create`, if `$departmentId` is non-null, assert
  `Department::where('id',$departmentId)->whereHas('faculty', fn($f)=>$f->where('organization_id',$organization->id))->exists()`,
  or resolve the department from the organization and reject a mismatch.

### 1.6 [BUG · HIGH] `Programme::$fillable` contains `slug`, which is not a column on `programmes` — silent write failure

- **File/line:** `app/Models/Curriculum/Programme.php:18` lists `'slug'` in `$fillable`.
- **Schema:** `programmes` columns are `organization_id, nuc_discipline_id, name, code, degree_type, duration_years, scope, verification_status, source_type, source_document, source_url, date_verified, status, normalized_name`. **There is no `slug` column.**
- **Consequence:** Eloquent silently discards `slug` from a `create`/`fill`, so any caller that passes a slug
  gets a catalogue row with no slug and no error. If a future migration adds a `slug` column, callers relying on
  today's silent drop will start writing it with no review.
- **Smallest correct fix:** remove `'slug'` from `Programme::$fillable` (one line). If a slug is wanted, add the
  column first via migration, then keep the `$fillable` entry.

### 1.7 [DESIGN · correct] The offering is correctly resolved through `academic_programs`, and the membership points at the offering, not the catalogue

- **File/line:** `app/Services/OrganizationOnboarding.php:213-225`
  ```php
  // academic_program_id points at `academic_programs`, NOT at the
  // central `programmes` catalogue. ...
  $offering = self::offeringFor($organization, $programme, $departmentId);
  $membership->update(['academic_program_id' => $offering->id]);
  ```
  The test at `tests/Feature/OrganizationOnboardingTest.php:173-177` asserts exactly this. The `nucProgramme()`
  relation on `AcademicProgram` (`app/Models/AcademicProgram.php:62-65`) is the correct outward hop. This part
  of the design is sound and should be left alone.

---

# 2. Reference integrity — `*_id` columns with no foreign key

Every `*_id` column in the schema that has **no** declared FK, classified as deliberate soft reference vs.
accidental omission. Derived from the full schema map (101 tables).

## 2a. Deliberate soft references (NOT bugs — documented, correctly implemented)

ACL uses loose academic references deliberately in several places, with a stated reason in the migration
("Loose reference to avoid FK constraint naming collisions in fresh test DBs"). These are **not** defects and
should not be reported as such:

| Table | Column(s) | Why it is soft (evidence) |
|---|---|---|
| `courses` | `nuc_discipline_id`, `institution_id`, `import_batch_id` | Academic-provenance links; `institution_id` is a `string(30)` provenance string, not an id FK (`2026_09_17_200001_add_remaining_provenance.php:8-11`). |
| `programmes` | `nuc_discipline_id` | Added as `unsignedBigInteger(...)->index()` with a comment "Loose reference" (`2026_09_12_000300_ensure_curriculum_tables.php:14-15,64-68`). |
| `academic_programs` | `organization_id`, `nuc_programme_id` | See §1.2 — flagged there as a *design decision to confirm*, listed here as consistent with the loose-academic convention. |
| `curriculum_versions` | `academic_session_id` | Plain `unsignedBigInteger(...)->nullable()` (`2026_09_12_000300:38`); `programme_id` on the same table *does* have a real FK — inconsistent but intentional per the same file. |
| `learning_outcomes` | `discipline_id`, `programme_id`, `course_id` | Curriculum graph; no FK by design. |
| `forum_threads` | `organization_id`, `academic_program_id` | Discussion scoping. |
| `system_alerts` | `organization_id` | Explicit comment: "Loose reference to avoid FK constraint naming collisions" (`2026_09_16_011249:187-188`). |
| `admin_audit_logs`, `superadmin_audit_logs` | `resource_id`, `request_id` | `resource_type`+`resource_id` is a polymorphic audit pointer; `request_id` is a correlation string. |
| `admin_auth_events` | `device_id`, `session_id`, `request_id` | Correlation identifiers, not table references. |
| `personal_access_tokens` | `tokenable_id` | Laravel's polymorphic `tokenable_type`+`tokenable_id` (sanctum). |
| `content_versions` | `versionable_id` | Polymorphic `versionable_type`+`versionable_id`. |
| `role_assignments` | `entity_id`, `scope_id` | The polymorphic scope (see §4). Deliberate and correct. |
| `institutions`, `organizations` | `canonical_institution_id`, `canonical_organization_id` | Self-referential de-duplication pointer for the NUC import duplicates; a row that points at a canonical row is a known duplicate. Cannot be an FK-to-self with cascade; a soft self-ref is the right call. |
| `users` | `provider_id` | External OAuth provider subject, not a table id. |
| `students` | `acl_student_id` | ACL's own public student number, not a foreign key (AGENTS §4 identity rules). |
| `subscriptions` | `stripe_id` | External payment provider reference. |
| `source_documents` | `import_batch_id` | Import-batch provenance string. |
| `course_mappings` | `source_document_id` | Provenance pointer; `nuc_course_id`/`institution_course_id` on the same table *do* have real FKs. |
| `course_disciplines` | `nuc_discipline_id` | Pivot table; FKs on the other side. |
| `curriculum_change_logs` | `request_id` | Correlation id. |

## 2b. Genuine inconsistencies worth fixing

### 2.1 [BUG · LOW] `curriculum_versions` gives `programme_id` a real FK but leaves `academic_session_id` soft, on the same table

- **File/line:** `database/migrations/2026_09_12_000300_ensure_curriculum_tables.php:37-38`
  ```php
  $t->foreignId('programme_id')->constrained()->cascadeOnDelete();
  $t->unsignedBigInteger('academic_session_id')->nullable();
  ```
- **Consequence:** inconsistent, but not dangerous — `academic_session_id` is genuinely optional (a curriculum
  version can predate a session). It is a defensible deliberate choice. Flagging only so it is recorded rather
  than re-discovered. Not a bug; note it and move on.

### 2.2 [BUG · LOW] `course_mappings` has real FKs on `nuc_course_id`/`institution_course_id` (both → `courses`) but no FK on `institution_id`

- **File/line:** `database/migrations/2026_09_17_200003_create_nuc_course_mapping_table.php` (see schema map).
- **Consequence:** `institution_id` can name an organization that does not exist. Consistent with the
  loose-academic convention. Deliberate. Not a bug.

---

# 3. Model ↔ schema drift

Method: for each of the 51 models, compare `$fillable` and `casts` against the real column set, and find
NOT NULL/no-default columns the model never sets (checked against `$fillable`, literal assignments, and
boot hooks). Three models have drift.

### 3.1 [BUG · HIGH] `MissingCourseRequest` model has no table — its migration is missing entirely

- **File/line:** `app/Models/MissingCourseRequest.php:11` declares `protected $table = 'missing_course_requests';`
  and lines 12-40 list 24 columns in `$fillable` plus casts on `status`, `submitted_at`, `reviewed_at`.
- **Schema:** **no migration creates `missing_course_requests`.** A full scan of all 100 migrations and all of
  `app/` finds the string only in the model itself (`grep -rn "missing_course_requests"` → single hit). Its
  `belongsTo` relations point at `institution`, `faculty`, `department`, `programme`, `curriculum_version`,
  `reviewer` — all real tables, so the model was clearly written against a schema that once existed.
- **Consequence:** any code path that touches `MissingCourseRequest` (query or create) throws a
  `Base table or view not found` SQL error. It is currently unreferenced by other code (only the model file
  exists), so nothing has tripped over it yet — but the model is live code that will fatal the moment it is
  used. On a fresh database this is a guaranteed fatal.
- **Smallest correct fix:** either (a) add the missing `create_missing_course_requests_table` migration
  matching the model's `$fillable`, or (b) if the feature is retired, delete the model. Because a table this
  size was clearly real in production, **(a) is almost certainly correct** — the migration is simply missing
  from the chain (the same class of gap that `student_institution_records` had, which the chain already
  documents in `2026_09_16_020848`).

### 3.2 [BUG · MEDIUM] `Curriculum/AcademicSession` does not set `start_date` / `end_date` (NOT NULL, no default)

- **File/line:** `app/Models/Curriculum/AcademicSession.php` — `start_date` and `end_date` are NOT NULL with no
  default (`2026_08_13_160001_create_academic_sessions_table.php`) and are absent from `$fillable`.
- **Consequence:** `AcademicSession::create(['name'=>...])` fails on a missing default. The one caller that
  creates sessions (`database/seeders/DevelopmentSeeder.php:46`) does pass them, so the currently-exercised path
  is safe; but any *other* caller (a future seeder, an admin "create academic session" form, a test fixture that
  builds an session with only name/slug) will fail at the database with a NOT NULL error.
- **Smallest correct fix:** add `start_date` and `end_date` to `$fillable` (they are real, writable columns —
  the seeder proves this). The schema is correct; the model is incomplete.

### 3.3 [BUG · MEDIUM] `Programme` — see §1.6 above (`slug` in `$fillable`, no column). This is the only `$fillable`/column mismatch in the entire model set once `AcademicProgram` is excluded.

### 3.4 [DESIGN · correct, already fixed] `AcademicProgram::$fillable` now includes `organization_id` and `nuc_programme_id`

- **File/line:** `app/Models/AcademicProgram.php:32-33`. These match real columns. The prior omission (which
  caused silent write failures) has been fixed. Noted so it is not re-flagged.

### 3.5 [DESIGN · correct] `Course` backfills its NOT NULL `normalized_code` in a `booted()` hook

- **File/line:** `app/Models/Course.php:36-47` derives `normalized_code` and `normalized_title` on save. The
  schema declares `normalized_code` NOT NULL with no default (`2026_09_16_030000:67`), which would otherwise
  fail every write. The hook covers every write path. Correct and well-documented. Not a bug.

### 3.6 [DESIGN · correct] `students.acl_student_id` (NOT NULL, no default) is issued by a retrying generator

- **File/line:** `app/Services/OrganizationOnboarding.php:257-267` issues it in a uniqueness loop; NOT NULL is
  intentional (every student must have one). Correct.

---

# 4. `role_assignments.scope_type` / `scope_id` — are they populated consistently?

The columns were added in `database/migrations/2026_09_29_200002_add_scope_columns_to_role_assignments.php`
(nullable strings, no FK — deliberate, see §2a). The intent: a level coordinator is scoped to
programme **and** level, so a 100-level coordinator cannot reach 200 level or another programme.

Every `RoleAssignment` creation site in the codebase was enumerated. Results:

| # | Site | File/line | `scope_type`/`scope_id` | Verdict |
|---|---|---|---|---|
| 1 | Level-coordinator appointment | `app/Services/OrganizationOnboarding.php:167-174` | `scope_type='level'`, `scope_id=(string)$level`, `entity_type=Programme`, `entity_id=$programme->id` | **Correct.** Exactly the intended granularity. |
| 2 | Org administrator issuance | `app/Services/OrganizationOnboarding.php:111-116` | not set (null); `entity_type=Organization`, `entity_id=$organization->id` | **Correct.** Org-wide scope needs no sub-scope; null scope == "the whole entity". |
| 3 | Institution admin provisioning | `app/Services/Institution/InstitutionAdminService.php:142-147` | not set; `entity_type=Institution` (see §6 — stale) | Scope-consistent, but entity is wrong. |
| 4 | Staff invitation | `app/Services/Institution/StaffInvitationService.php:29-34` | not set; `entity_type=get_class($institution)` | **Bug — see 4.1.** |
| 5 | Superadmin role editor | `app/Http/Controllers/Superadmin/UserController.php:121-131` | not settable (see 4.2) | **Gap — see 4.2.** |
| 6 | Superadmin staff invite (existing user) | `app/Http/Controllers/Superadmin/StaffController.php:77-84` | not set; `entity_type=Organization` | **Bug — see 4.1.** |
| 7 | Superadmin assign-org-admin | `app/Http/Controllers/Superadmin/InstitutionController.php:235-243` | not set; `entity_type=Organization` | Org-admin role, org-wide scope — correct. |
| 8 | Student registration | `app/Http/Controllers/Auth/StudentRegistrationController.php:325-330` | not set; `entity_type=get_class($verification->organization)` (Organization) | Student role is org-wide — correct. |
| 9 | RBAC seeder (student) | `database/seeders/RbacSeeder.php:61-66` | not set; `entity_type=Organization` | Seed/demo data, org-wide — correct. |

### 4.1 [BUG · HIGH] Both staff-invitation paths accept and validate a scope, then silently discard it

- **File/line (a):** `app/Http/Controllers/Superadmin/StaffController.php:62-64,77-84`
  ```php
  'scope' => ['nullable', 'array'],
  'scope.academic_program_id' => ['nullable', 'integer'],
  'scope.level_id' => ['nullable', 'integer'],
  ...
  RoleAssignment::updateOrCreate([
      'user_id' => ..., 'role_id' => ..., 'entity_type' => Organization::class,
      'entity_id' => $organization->id,          // <-- scope.academic_program_id / scope.level_id never used
  ], ['updated_at' => now()]);
  ```
  The request **validates** `scope.academic_program_id` and `scope.level_id`, and the audit log even records
  `'scope' => $data['scope'] ?? null` (line 116) — but the role assignment is written with org-wide scope and
  no `scope_type`/`scope_id`. A superadmin inviting a `level.coordinator` through this form produces a
  coordinator with authority over the **whole organization**, not over one level of one programme.
- **File/line (b):** `app/Services/Institution/StaffInvitationService.php:14,29-34` — same shape: the signature
  accepts `?int $academicProgramId = null, ?int $levelId = null` and both are **never referenced** in the body;
  the assignment is always org-wide.
- **Consequence:** the one control the scope columns were added to provide (narrow a coordinator to
  programme+level) is bypassed on the two paths a superadmin actually uses to appoint staff. This is the exact
  over-grant §6 of AGENTS.md and the scope-isolation rule forbid.
- **Smallest correct fix:** in both places, when the role is `level.coordinator`, set
  `scope_type='level'` and `scope_id` from the validated level, and narrow `entity_type/entity_id` to the
  programme (resolving `academic_program_id` → its `nuc_programme_id`) exactly as
  `OrganizationOnboarding::appointLevelCoordinator` already does. The correct logic exists — it just needs to
  be called from these two entry points.

### 4.2 [BUG · MEDIUM] The superadmin role editor cannot express a scoped role at all

- **File/line:** `app/Http/Controllers/Superadmin/UserController.php:105-131` — validation accepts only
  `roles.*.role_id`, `roles.*.entity_type`, `roles.*.entity_id`. There is no `scope_type`/`scope_id` input, and
  `RoleAssignment::updateOrCreate` matches on `(user, role, entity_type, entity_id)` only.
- **Consequence:** a superadmin reviewing a user's roles sees and can only manage entity-level scope. If a
  level-coordinator assignment created by `appointLevelCoordinator` (scope_type='level') passes through this
  editor, the `updateOrCreate` match key **ignores scope**, so it will find the existing scoped row and leave the
  scope intact (good) — but it can neither create a new scoped assignment nor display/edit the existing scope.
  The editor is a functional blind spot rather than a security hole (it fails toward over-broad org scope, which
  the superadmin chose deliberately).
- **Smallest correct fix:** add `scope_type`/`scope_id` to the validation and to the `updateOrCreate` match key,
  and render the current scope in the editor. Small, contained change in one controller + one view.

### 4.3 [DESIGN · correct] `entity_type`-null means platform-wide, and that rule is honoured consistently

- **File/line:** `app/Models/User.php:122-160` — `hasPermission`/`hasRole` treat `entity_type IS NULL` as
  platform-wide and require a matching `(entity_type, entity_id)` for scoped checks. The scoping test
  (`tests/Feature/Authorization/RbacScopingTest.php`) pins this. The superadmin guard
  (`UserController.php:117-119`) forces the superadmin role to platform scope. Correct; do not add a bypass here.

---

# 5. Migration-chain soundness on a fresh database

The chain currently applies cleanly on production (0 pending). The question is whether it would build the same
schema from empty. Findings, by class:

### 5.1 No `whereDoesntHave` / relation-method misuse on the query builder remains

- The one occurrence is `2026_09_17_000000_fix_missing_versions_and_courses.php:63-66`, and it is already
  fixed — the comment explains the hazard and the code uses `whereNotExists` on the query builder correctly.
- Full migration scan for `whereDoesntHave`/`doesntHave`/`whereHas`/`->has(` on `DB::table(...)`: **no findings.**
  This class of bug is closed.

### 5.2 No index names exceed the 64-character MariaDB limit

- `institutions` originally needed `institutions_ownership_institution_status_onboarding_status_index`
  (74 chars). That is **already fixed** — `2026_09_17_200007:22-28` names it `institutions_status_lookup_idx`
  with a comment explaining why. Scan of all explicit index names: **none over 64 chars.**
- Auto-generated names: the only candidate over 64 was `student_institution_records_…_unique` (66), and that
  migration (`2026_09_16_020848:30-36`) names all its indexes explicitly and short, so Laravel never
  auto-generates the long form. **No finding.**

### 5.3 `Schema::hasTable` guards that would silently skip a richer definition on a fresh DB

- `2026_09_12_000300_ensure_curriculum_tables.php` guards all four creates with `if (! Schema::hasTable(...))`.
  On a fresh DB, `nuc_disciplines`, `curriculum_versions`, `curriculum_courses` do **not** yet exist, so they are
  created in full; `academic_sessions` **does** exist (from `2026_08_13_160001`) so the richer
  `2026_09_12` definition is skipped and the table is instead topped up by `2026_09_16_030000` — which is exactly
  the intent, and the end state is correct. **Not a bug**, but worth knowing the table's column set is the union
  of two migrations, not one.
- No other `hasTable` guard causes a skipped definition. **No finding.**

### 5.4 Migrations that assume prior data

- `2026_09_17_000000` (fix_missing_versions_and_courses) is a **data** migration keyed to hard-coded
  programme ids (28, 33, 34, 35). It was already hardened: it now filters against `existing programme ids` before
  inserting (lines 28-36) precisely so a fresh/empty `programmes` table does not abort the batch. The course
  backfill likewise uses `whereNotExists` and skips when a course code is absent. **Already fixed; correct.**
- `2026_09_28_170000` and `2026_09_28_190000` are data migrations over `institutions`, guarded by
  `if (! Schema::hasTable('institutions'))`. On a fresh DB `institutions` exists (created earlier), so they run
  and are no-ops on an empty table. **Correct.**

### 5.5 ENUM values vs the values application code writes

- `programmes.scope` enum = `['national','university','faculty','department','programme']`. Application code
  never writes `scope` to `programmes` (the `Programme` model does not expose it in writes except via the
  default `'programme'`). `UniversityCourseService.php:33` writes `scope='university'` but to the **`courses`**
  table, whose `scope` is a plain `string`, not an enum. **No enum violation.** (See §6.3 for the concept issue.)
- `institutions.onboarding_status` enum vs `organizations.onboarding_status` string: the institution
  `InstitutionController` filters `institution_status`/`onboarding_status` against the *institution* enum values
  (`ACTIVE`, `ARCHIVED`, `NOT_ONBOARDED`) — matches. The `OrganizationOnboarding` service writes
  `organizations.onboarding_status = 'INVITED'` (string), and the comment on the organizations column enumerates
  `INVITED` as valid. **Consistent.**
- `institution_onboardings.status` enum = `['pending','invited','started','partially_completed','awaiting_review','completed','suspended']`. `OrganizationOnboarding::recordFor` writes `'pending'` and `issueAdministrator` writes `'invited'` — both in the enum. **No violation.**
- No enum mismatches found between declared values and written values.

### 5.6 NOT NULL columns added to a table that may have rows, with no default

- `2026_09_16_030000_add_production_only_columns.php:67`:
  ```php
  $table->string('normalized_code', 30);   // NOT NULL, no default, added to existing `courses`
  ```
  On a table with rows this backfills an empty string (or fails in strict mode). It is a real hazard, **but it is
  the single known production-only column** and `Course::booted()` now always derives it (see §3.5), so on a
  fresh DB every write supplies it. On the existing production table the column is already present so the ALTER
  is a no-op there. **Low residual risk; flag as LOW, not a fresh-DB blocker.**
- `2026_09_16_030000:88-90` adds `is_mandatory` with `->default(1)` — has a default. Fine.
- No other NOT NULL/no-default ALTER-to-existing-table cases.

### 5.7 `down()` that drops a column its own `up()` never created

- `2026_09_16_030000_add_production_only_columns.php` `down()` (lines 103-225) drops columns including
  `semesters.number` (line 169) and `curriculum_courses.delivery_mode` (line 214) — but its own `up()` never
  creates either (they are created in `2026_09_29_100000`). It also drops `is_current` (line 172) which it *does*
  create. So `migrate:rollback` on this batch would fail on `semesters.number` / `curriculum_courses.delivery_mode`
  (unknown column). **This is a real rollback defect, but it only affects `down()` (rollback), not the `up()`
  chain that production runs.** Severity LOW for the live chain, but it means **this batch cannot be rolled back
  safely** — which is exactly the situation a database-safety rule (§12) cares about.
- **Smallest correct fix:** remove the two mismatched `dropColumn` calls (or wrap them in
  `if (Schema::hasColumn(...))`). Do **not** attempt a rollback on production to "test" this.

### 5.8 Ordering bugs (a migration reads/writes a column a later migration adds)

- `2026_09_16_030000` (adds `courses.normalized_code`) runs **before** `2026_09_17_000000` (reads
  `courses.normalized_code`) and before `2026_09_17_200001` — ordering is correct.
- `2026_09_16_030000` (adds `semesters.is_current`, `curriculum_courses.is_mandatory`) runs before
  `2026_09_29_100000` (adds `semesters.number`, `curriculum_courses.delivery_mode`) — correct.
- `2026_09_28_170000`/`190000` (over `institutions`) run before `2026_09_29_200000` (widens `organizations`) —
  the two tables are independent until the console-command fold, so no ordering dependency. Correct.
- **No ordering bugs found.**

### 5.9 Duplicate/competing definitions of the same index

- None. Each index name is declared once. `organizations` gets `normalized_name` UNIQUE in
  `2026_09_29_200000` and there is no competing unique elsewhere. **No finding.**

**Q5 verdict:** the `up()` chain is sound for a fresh database. The one defect is a **rollback-only** asymmetry
in `2026_09_16_030000.down()` (§5.7, LOW). The `hasTable` guards and the data migration are all already
hardened. This is the area that is in the best shape.

---

# 6. Duplicated / parallel "university" vs "organization" concepts

### 6.1 [BUG · HIGH] `Institution` model and `institutions` table still exist and are still the primary entity in several services and one superadmin controller

- **Files:** `app/Models/Institution.php`; `app/Services/Institution/InstitutionAdminService.php` (type-hints
  `Institution` throughout, `entity_type => Institution::class` at lines 134,145,157);
  `app/Http/Controllers/Superadmin/InstitutionController.php:6,24,43,45` (queries `Institution::query()`);
  `app/Console/Commands/InstitutionProvisionAdmins.php`, `InstitutionProvision.php`; `app/Http/Controllers/Institution/ClaimController.php`.
- **What is wrong:** the fold moved the *data* into `organizations` (via the console command
  `ConsolidateInstitutionsIntoOrganizations`), but the `Institution` model, the `institutions` table, and every
  service/controller that type-hints `Institution` were left in place. The superadmin institutions index
  (`InstitutionController::index`) still reads `institution_status` and `onboarding_status`, columns that exist
  on `institutions` but **not** on `organizations` (organizations has only a string `onboarding_status` and no
  `institution_status`). So the "superadmin institutions" list reads a table that is a frozen historical
  snapshot, while every other screen reads `organizations`. This is the "two parallel notions" problem the
  2026-09-29 migration was written to kill, still alive in the read path.
- **This is a live, reachable page, not dead code.** `php artisan route:list --path=superadmin/institutions`
  returns 8 routes; 6 of them bind `{organization}` (the Organization model) — `show`, `update`, `edit`,
  `assign-admin`, `toggle`, `store` — while `index` and `create` are served by the `Institution`-querying
  controller methods. The listing page and the detail page therefore read **different tables for the same
  entity**, and the listing filters on columns (`institution_status`, `code`) that `organizations` does not have.
- **Note on intent:** the console command comment says "Nothing is deleted. The institutions table is left in
  place, read-only, so a rollback is a matter of repointing back." So *keeping* the table is deliberate. The bug
  is that **live controllers still treat it as the system of record**, not that the table exists.
- **Smallest correct fix:** repoint `Superadmin/InstitutionController` and `InstitutionAdminService` at
  `Organization` (the model already has everything needed: `institution_status` maps to
  `organizations.status`/`is_active`; `onboarding_status` exists as a string). Leave `Institution` and the table
  in place, read-only, until the data is confirmed stable. This is a controller/service swap, not a schema change.

### 6.2 [DESIGN · correct, documented] `users.institution_id` is a soft reference that now holds an `organizations` id — deliberately kept under the old name

- **File/line:** `app/Models/User.php:46-64` documents this explicitly: "The column is still called
  `institution_id`, but since the consolidation it holds an `organizations` id … The column is left as-is so the
  rename does not become a schema migration touching a hot table." Both `institution()` and `organization()`
  relations point at `Organization`. This is a **deliberate, well-documented decision** — the column name is
  misleading but the behaviour is correct. Not a bug. It is the kind of thing a future engineer might "fix"
  without realising the data is already organizations-id; the comment is the guard, and it is present. Good.

### 6.3 [BUG · MEDIUM] `UniversityCourseService` models a "university course" as a parallel course concept rather than a offering of a catalogue course

- **File/line:** `app/Services/UniversityCourseService.php:26-39`
  ```php
  $course = Course::create([... 'scope' => 'university', 'source_type' => $data['source_type'] ?? 'university', ...]);
  ```
- **What is wrong:** this service creates a *new row in `courses`* (the global course catalogue) with
  `scope='university'` to represent "this university's own version of a course". That is a per-university course
  definition living in the shared catalogue table — the same anti-pattern the programme restructure eliminated,
  one level down. The correct shape, mirroring the programme design, is: a university-specific course is an
  *offering* linked to the catalogue course (which is exactly what `course_mappings` /
  `Curriculum\CourseMapping` is for — see the `linkToNucReference` method right below it). The service even
  provides `linkToNucReference` to map the duplicate back to the NUC course, but it first *creates the duplicate*.
  Also note `'source_type' => 'university'` and `'import_batch_id' => 'manual-'.date(...)` are written as free-text
  provenance, and `institution_id` is cast to `(string)`.
- **Consequence:** the global `courses` table accumulates near-duplicate rows that differ only by
  `institution_id`/`scope`, the same near-duplication problem `ProgrammeCatalogue` was written to solve for
  programmes. A course shared between two universities that agreed on the spelling cannot be shared, and
  visibility/authorization keyed on `course_id` fragments.
- **Smallest correct fix:** do not create a new `courses` row. Create/resolve the NUC catalogue course (or leave
  the university's course unmapped as an *offering* via `course_mappings`), and represent the university-specific
  variant through the offering/mapping tables rather than by cloning the catalogue row. This mirrors
  `offeringFor()` for programmes. (Design change; flag for the owner rather than prescribe a schema migration here.)

### 6.4 [DESIGN · correct] `Programme::offerings()` and `AcademicProgram::nucProgramme()` form the correct central-catalogue → offering bridge

- **File/line:** `app/Models/Curriculum/Programme.php:52-55` (`offerings()` via `nuc_programme_id`) and
  `app/Models/AcademicProgram.php:62-65` (`nucProgramme()`). Together these are the intended relationship and are
  correct. This is the pattern the rest of the academic model should be brought in line with (cf. §6.3).

---

# Summary table

| # | Finding | Sev | Type |
|---|---|---|---|
| 1.1 | `programmes.organization_id` is a real FK — schema permits a programme to be owned by an organization | HIGH | BUG |
| 1.2 | `academic_programs.nuc_programme_id` has no FK — offering→catalogue link not DB-enforced | HIGH | BUG (design decision to confirm) |
| 1.3 | `Programme::booted()` code backfill can collide (no unique on `programmes.code`) | MEDIUM | BUG |
| 1.4 | `offeringFor()` read-then-write race; no `unique(organization_id, nuc_programme_id)` | MEDIUM | BUG |
| 1.5 | `offeringFor()` does not verify department belongs to organization | MEDIUM | BUG |
| 1.6 | `Programme::$fillable` has `slug`, no such column — silent write drop | HIGH | BUG |
| 2.1 | `curriculum_versions.academic_session_id` soft while sibling FK is hard | LOW | note |
| 3.1 | `MissingCourseRequest` model has no table — no migration exists | HIGH | BUG |
| 3.2 | `Curriculum\AcademicSession` does not set NOT NULL `start_date`/`end_date` | MEDIUM | BUG |
| 4.1 | Both staff-invitation paths validate a scope then discard it → over-broad coordinator | HIGH | BUG |
| 4.2 | Superadmin role editor cannot express/edit scope | MEDIUM | BUG |
| 5.1 | `whereDoesntHave` misuse on query builder | — | none (already fixed) |
| 5.2 | Index name > 64 chars | — | none (already fixed) |
| 5.7 | `2026_09_16_030000.down()` drops columns its `up()` never created (rollback breaks) | LOW | BUG |
| 6.1 | `institutions` table + `Institution` model still the system of record in superadmin/institution-admin read+write paths | HIGH | BUG |
| 6.3 | `UniversityCourseService` clones catalogue courses per-university (`scope='university'`) | MEDIUM | BUG |
| 1.7, 2a, 3.4-3.6, 4.3, 6.2, 6.4 | Correct / deliberate design | — | DESIGN |

**Real bugs by severity:** 6 HIGH, 5 MEDIUM, 2 LOW.
**Single most important:** **1.1 / 6.1 cluster** — the "fold institutions into organizations" and "programmes are a
central catalogue" restructures are **only half-applied**. The *data* moved (via a console command), but the
*schema still permits a programme to be owned by an organization (§1.1)* and the *read/write paths still treat the
frozen `institutions` table as the system of record (§6.1)*. Until both halves are closed, the invariants the
restructure was meant to establish are enforced by nothing and can be silently violated by any code path.

---

## Provenance and reproduction

No database was contacted. The schema map was produced by statically parsing the 100 migration files:

- `scripts/audit_schema_map.php` → `scripts/audit_schema_map.json` — table → columns (type, nullability,
  default, enum values) → declared foreign keys, for all 101 tables. Handles both `function (Blueprint $t)`
  and untyped `function ($t)` closures, both `$table` and `$t` blueprint variables, and restricts parsing to
  each migration's `up()` body so `down()` teardown does not erase recorded columns.
- `scripts/audit_model_drift.php` — cross-references each model's `$fillable` and `casts` against that map.
- `scripts/audit_required_columns.php` — finds NOT NULL / no-default columns the model never sets, checking
  `$fillable`, literal assignments and `booted()` hooks.

All three are read-only and open no database connection. Re-run with `php scripts/audit_schema_map.php`.
They are untracked additions under `scripts/`; **no file under `app/` or `database/` was modified**
(confirmed with `git status --short`). The only other commands run were `php -l` on the three scripts and
`php artisan route:list --path=superadmin/institutions`.

### Known limits of this method

- The schema map is derived from migration source, so it describes what the chain *would* build. Where a
  migration is guarded by `Schema::hasTable`/`hasColumn`, the map records the column the guard would add on a
  fresh database (e.g. `semesters.number`, `curriculum_courses.delivery_mode`, both from
  `2026_09_29_100000`) — these are correct for a fresh build and are already present in production.
- Polymorphic pairs (`entity_type`+`entity_id`, `versionable_type`+`versionable_id`,
  `tokenable_type`+`tokenable_id`) are treated as deliberate soft references throughout.
- Drift detection compares `$fillable`/`casts` only. A model with no `$fillable` (guarded-by-default) would not
  be flagged by the `$fillable` check; the NOT NULL check in `audit_required_columns.php` does cover those.
