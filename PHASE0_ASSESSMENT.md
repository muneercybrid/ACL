# PHASE 0 — Assessment (Written Before Any Changes)

Date: 2026-09-17
Author: Agent (in session)

## 1. Current State Inspection Results

### Existing NUC CCMAS dataset (in DB, pre-existing):
- `nuc_disciplines`: **17 records** — ALL 17 official NUC CCMAS disciplines present (ADM, AGR, AHS, ARC, ART, BMS, CMP, CMS, EDU, ENG, ENV, LAW, MED, PHA, SCI, SOC, VET) ✓
- `programmes`: **44 records** linked to disciplines ✓ (12 ADM, 3 AGR, 2 AHS, 1 ARC, 2 ART, 2 BMS, 4 CMP, 1 CMS, 2 EDU, 3 ENG, 1 ENV, 1 LAW, 1 MED, 1 PHA, 4 SCI, 3 SOC, 1 VET)
- `curriculum_versions`: **41 records** (across programmes)
- `curriculum_courses`: **754 records** (level/semester/course linkage)
- `courses`: **179 records** (course table)
- `course_disciplines`: **0 records** ⚠ (NUC↔course mapping not populated)
- `course_outlines`: **0 records** ⚠ (per spec, should exist per course)
- `outline_questions`: **0 records** ⚠
- `student_course_outlines`: MISSING
- `levels`: **3 records only** (100/200 for academic_program 1; 100 for academic_program 2) — most programmes lack level records ⚠
- `semesters`: 4 records (need verification of proper 1/2 separation per academic session)

### Existing Academic Ontology:
- `students` table: user_id, acl_student_id, verification_method/status, nationality, state, lga, admission_year, region
- `course_offerings`: course_id, semester_id, department_id, custom_code, is_active (with unique course_semester_dept)
- `programmes`: nuc_discipline_id, name, code, degree_type, duration_years, scope, verification_status, source_document, source_url, date_verified
- `Course` model: code, title, slug, credit_units, description, is_active — **no provenance/source fields**
- `NucDiscipline`: code, name, status only (no document metadata)
- `CurriculumCourse`: curriculum_version_id, course_id, level, semester, course_type, credit_units, status

### Auth/RBAC:
- Standard Laravel Breeze auth
- Roles: superadmin, institution admin, coordinator, moderator (verified via institution_staff_invitations)
- Students via `students` table linked to users
- Course access via `CourseOfferingPolicy`/`LessonPolicy` + `scopeBindings`

### ACLi State:
- `acli:test` and `acli:diagnose` commands created
- `AcliOrchestrator` exists, `OmniRouteProvider`/`DeepSeekProvider` exist
- API key stored ONLY in `.env` (not in source)

### UI State (current session):
- `/student` 200, layout rebuilt (hero + stats + courses)
- `/login` 200, login 11s, dashboard 7s (improved via cache/batch-loading)
- Profile avatar gradient + upload button
- Email banner fixed (no broken route)

## 2. Gaps Against the NUC CCMAS Redesign Spec

### CRITICAL gaps (blocking architecture):
1. **course_disciplines empty**: courses not mapped to NUC disciplines (spec requires NUC↔university course mapping)
2. **No source documents infrastructure**: spec requires `source_documents` table (URL, hash, retrieval date, version/year) — currently only `source_document` string on programmes
3. **No course provenance fields**: `courses` table lacks source_type, verification_status, source_url, provenance — spec requires NUC vs university vs department vs verified-admin vs student classification
4. **`Course.code` has global UNIQUE constraint**: spec explicitly forbids global unique course code; code must be scoped to institution/curriculum (e.g., NUK-CYB101 vs CSC101 vs COS101)
5. **No NUC course vs university course distinction**: spec requires separate representation of NUC/reference concept + university-specific course + mapping/relationship
6. **Levels only exist for 2 academic programmes**: spec wants 100-500+ per programme
7. **No learning outcomes tables**: spec requires programme learning outcomes, discipline learning outcomes, course learning outcomes — NONE exist
8. **No course topics/subtopics**: spec requires course topics and subtopics (CourseChapter exists but only 1 record)
9. **No course outline content**: `course_outlines`, `outline_questions` tables exist (migrations) but 0 records
10. **No orientation system**: no platform orientation or course orientation/readiness quiz system (spec Phases 10-11)
11. **No student course resolution system**: no automated semester/level/programme-aware course resolution per spec Phase 7
12. **`course_chapters` only 1 record**: ACL learning content is essentially empty

### Migration safety concerns (per spec Phase "Migration Safety"):
1. Changing `courses.code` from global unique → scoped requires careful migration preserving existing 179 courses
2. Adding university-specific course layer: need new table/model without breaking existing data
3. Adding provenance fields: nullable additions, safe
4. Levels table redesign: need compatibility path (currently per-academic_program)
5. `course_disciplines` table exists (migration) but empty — safe to populate

## 3. Migration Safety Assessment

Existing working data to preserve:
- 17 NUC disciplines (KEEP)
- 44 programmes (KEEP)
- 41 curriculum versions (KEEP)
- 754 curriculum_courses (KEEP)
- 179 courses (KEEP — but add scoped code + provenance columns)
- 9 students (KEEP)

Safe additions:
- New tables: `source_documents`, `course_provenance`, `course_mappings`, `learning_outcomes`, `course_topics`, `source_document_sections`, `curriculum_source_references`, `orientation_records`
- New columns on `courses`: `scope`, `source_type`, `verification_status`, `source_url`, `institution_id` (nullable)
- New model: `UniversityCourse` (institution-specific courses)
- New model: `CourseMapping` (NUC ↔ university course)

Unsafe changes to avoid:
- `migrate:fresh`, `db:wipe`, `truncate` — FORBIDDEN
- Deleting `courses.code` unique constraint — must do via migration with data preserved
- Modifying existing 179 course records — only add nullable columns

## 4. Recommended Phase 0 → Phase 1 Path

Phase 1 (Design) should produce:
1. Updated data model (17 existing tables + new tables)
2. Course identity rules (NUC code ≠ university code; scoping rules)
3. Provenance model (source_document, retrieval date, hash, page refs)
4. Course mapping entity (NUC course ↔ university course ↔ institutional code)
5. Course code uniqueness constraint (UNIQUE(institution_id, code, curriculum_version_id))
6. Learning outcomes hierarchy (discipline → programme → course)
7. Orientation system model (platform + per-course with completion state)

Next step: Get user approval on this assessment, then proceed to Phase 1 design, then implement incrementally per staged process.

## 5. Existing Functionality (Must NOT be destroyed)
- 17 NUC disciplines with full programme/curriculum data
- Student registration flow (JAMB verify → complete)
- Login/session/auth system
- ACLi chat (working)
- Dashboard UI (claymorphism, working)
- Profile (with avatar upload)
- Course/lesson/chapter system
- Admin RBAC and institution scoping
