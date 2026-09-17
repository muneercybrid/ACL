# Phase 1 — Curriculum Domain Design (Post Phase 0 Assessment)

Source: Phase 0 assessment at PHASE0_ASSESSMENT.md
Status: DESIGN ONLY — no migrations applied yet
Approach: preserve all existing data; add tables/columns; never destructive

## Key Design Decisions

### 1. Course Identity (Critical fix for spec)
- Existing `courses` table: `code` has global UNIQUE — must become scoped
- Migration: drop unique constraint on `code` alone; add compound unique `institution_id, code, curriculum_version_id` where appropriate; for pure NUC reference, scope by discipline/version
- Add `scope` enum to `courses`: `nuc`, `university`, `department`, `verified`, `student_submitted`, `other`
- Add `source_type`, `verification_status`, `source_url`, `institution_id` (nullable), `course_version` to `courses`
- Existing 179 courses become `scope='university'` with `institution_id` set to default (Northwest) or mapped

### 2. NUC Course vs University Course (Critical distinction per spec)
- New model: `App\Models\Curriculum\NucCourse` (or `NucCurriculumCourse`) — reference layer from CCMAS
- Existing `courses` becomes university/institution-specific layer
- New mapping table: `course_mappings` — links `nuc_course_id` ↔ `course_id` with relation type (`exact`, `approximate`, `institution_variant`)
- This prevents false equivalence (e.g., NUK-CYB101 vs CSC101)

### 3. Source Document Tracking (required ingestion)
- New table: `source_documents`
  - `id`, `title`, `source_url`, `document_version`, `retrieval_date`, `file_path`, `file_hash`, `status` (active/review/failed)
- New table: `source_document_sections` — pages/sections mapped to disciplines/programmes
- Existing `programmes.source_document` / `source_url` kept as legacy; migrate to this when needed

### 4. Course Provenance (existing `courses` + new fields)
- Add nullable `provenance_notes`, `extraction_method`, `import_batch_id`
- Existing `courses` rows kept; provenance initially null/unknown then filled when source linked

### 5. Course-Discipline Mapping (existing empty table)
- `course_disciplines`: `nuc_discipline_id`, `course_id`, `source_type`, `status`
- Populate from existing `programmes` + CCMAS mapping logic

### 6. Learning Outcomes (missing entirely)
- New table: `learning_outcomes`
  - `id`, `discipline_id` (nullable), `programme_id` (nullable), `course_id` (nullable), `level`, `semester`, `statement`, `type` (knowledge/skill/attitude)
- New table: `programme_learning_outcomes` (if needed separately — design to use `learning_outcomes` with nullable scope)

### 7. Course Topics / Subtopics (partially exists via chapters)
- `course_chapters` exists (1 record) — expand rather than replace
- Add `course_topics` (topic-level, above chapter): `course_id`, `title`, `position`, `parent_topic_id` (subtopics)
- Existing `CourseChapter` keeps position/intro/summary structure

### 8. Student Academic Identity / Resolution (partially exists)
- `students` table exists with `user_id`, `verification_method/status`, `state`, `lga`
- `academic_programs` exists (from 2026 migration)
- Need `student_programmes` / `student_curriculum_assignments`: link student to programme + curriculum version + level + semester
- Resolution engine (service): student → institution → programme → curriculum version → level → semester → courses

### 9. Student Course Visibility / Selection
- `enrollments` exists (1 record) — needs expansion for course-level enrollment with semester/level context
- Add `student_courses` (or expand `enrollments`): `student_id`, `course_id`, `curriculum_version_id`, `level`, `semester`, `status`, `enrolled_at`
- Must separate FIRST vs SECOND semester — not one undifferentiated list

### 10. Orientation System (new)
- `platform_orientations`: `user_id`, `completed_at`, `status`
- `course_orientations`: `course_id`, `user_id`, `completed_at`, `quiz_score`, `status`
- `course_readiness_quizzes`: `course_id`, `questions` (JSON or linked to `outline_questions`)

### 11. ACLi Curriculum Grounding
- Existing `AcliOrchestrator` needs `ContextPackage` that includes student's `curriculum_version_id`, `level`, `semester`, `course_ids`
- Add `curriculum_context` resolution to service layer

## Migration Plan (safe order)

1. Add `scope`, `source_type`, `verification_status`, `institution_id`, `provenance_notes` to `courses` (nullable)
2. Drop unique on `courses.code` alone; add compound if needed
3. Create `nuc_disciplines` reference table (already exists — 17 records OK)
4. Create `course_mappings` (NUC ↔ university)
5. Create `source_documents`, `source_document_sections`
6. Create `learning_outcomes`
7. Create `course_topics`
8. Create `student_programmes` / `student_curriculum_assignments`
9. Create `course_orientations`, `platform_orientations`
10. Populate `course_disciplines` (map existing 179 courses to NUC disciplines via programme links)
11. Create migration tests

No destructive drops. Preserve all 179 courses, 44 programmes, 754 curriculum links.
