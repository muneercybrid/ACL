# Product & Learning Agents

The agents that decide **what is worth building and whether it teaches anything**. Ten
are loaded; one is ACL's own.

ACL's premise constrains all of it: **course access follows from an institutional record
— programme, level, semester — not from a purchase.** A product idea that requires a
learner to buy a course contradicts the platform, not just a feature.

**What the learning model actually is today** — verified 2026-09-03: courses contain
modules, modules contain lessons, and a learner reaches them through an enrolment that
`EntitlementService` derives from an active student membership. **What does not exist:**
assessments, questions, attempts, grading, certificates, prerequisites, adaptive
sequencing, progress analytics, offline access, notifications. Any agent in this file that
plans around those is designing them, not extending them.

---

### ACL Learning Experience Architect
`acl-learning-experience-architect.md` · ACL

**Invoke when** pedagogy, content structure or assessment design is the subject — before
anyone writes the schema for it.
**Reviews** whether a feature teaches or merely records, whether a unit is small enough to
finish, and whether feedback tells a learner what to do next.
**Produces** the learning design: content structure, assessment intent, feedback rules —
then hands the schema to someone else.
**Consult** `docs/ACL_MASTER_SPECIFICATION.md`, `docs/VISION.md` for intent,
`docs/database/DOMAIN_MODEL.md` for what content exists.
**Hands to** `ACL Database Architect` (schema), `ACL Backend Architect` (enforcement),
`ACL Frontend Architect` (presentation), `ACL AI Engineer` (only after the assessment
model exists — you cannot grade what you have not defined).
**Its seven principles**, in short: completion is not mastery; structure follows the
institution; units are small and finishable; assessment is designed before it is
automated; feedback beats scores; accessibility is pedagogy; learner data serves the
learner first.
**Defers on** schema, enforcement and styling.

### Corporate Training Designer
`corporate-training-designer.md` · SPECIALIST

**Invoke when** instructional-design depth is wanted: needs analysis, blended programme
structure, effectiveness evaluation.
**Reviews** curriculum design as a discipline.
**Produces** advice. Discount its enterprise-L&D assumptions — ACL serves university
students on a semester structure, not employees on a compliance calendar.
**Hands to** `ACL Learning Experience Architect`, which decides.

### UX Researcher
`design-ux-researcher.md` · SPECIALIST

**Invoke when** a decision rests on a belief about how learners behave.
**Reviews** the evidence behind that belief, and how it could be tested.
**Produces** a research plan, or an honest statement that no evidence exists yet — which
is ACL's true position on almost every learner question today.
**Consult** `docs/VISION.md`, `docs/ui-ux/DESIGN_SYSTEM.md`.
**Hands to** `ACL Learning Experience Architect`, `ACL Frontend Architect`.
**Constraint** any study touching real students is a privacy decision first — see
`Privacy Engineer`.

### Behavioral Nudge Engine
`product-behavioral-nudge-engine.md` · SPECIALIST

**Invoke when** motivation, streaks, reminders or progress pressure are proposed.
**Reviews** the mechanism and its effect on a learner who is already struggling.
**Produces** a proposal with the manipulation risk stated. For a platform whose users may
include minors, "engagement" is not self-evidently good: a nudge that raises completion by
shaming someone has failed.
**Hands to** `ACL Learning Experience Architect`, which has the final say on whether it is
pedagogically defensible, and `Privacy Engineer` for anything requiring behavioural
tracking.

### Product Manager
`product-manager.md` · SPECIALIST

**Invoke when** scope, sequencing or "should we build this at all" is the question.
**Reviews** the proposal against the premise, and against what already exists.
**Produces** a scoped definition with the outcome it is meant to change.
**Consult** `README.md`'s "What does not exist yet", `docs/PROJECT_STATUS.md`,
`docs/ACL_MASTER_SPECIFICATION.md`.
**Hands to** `ACL Software Architect` for anything architectural.
**Note** its tools are limited to `WebFetch, WebSearch, Read, Write, Edit` upstream — it
cannot run commands, so it cannot verify what exists. Give it the facts.

### Sprint Prioritizer
`product-sprint-prioritizer.md` · SPECIALIST

**Invoke when** several valid pieces of work compete for the same time.
**Reviews** value, dependency order and risk.
**Produces** a ranked sequence with the reasoning kept.
**Consult** `docs/PROJECT_STATUS.md`.
**Hands to** `ACL Orchestrator` to execute the top item.

### Senior Project Manager
`project-manager-senior.md` · SPECIALIST

**Invoke when** a specification needs turning into tasks that can actually be finished.
**Reviews** scope realism, and specifically refuses tasks that assume background processes
or capabilities ACL lacks.
**Produces** a task breakdown traceable to the spec.
**Consult** `docs/ACL_MASTER_SPECIFICATION.md`.
**Hands to** `ACL Orchestrator`.

### Analytics Reporter
`support-analytics-reporter.md` · SPECIALIST

**Invoke when** ACL reports on itself — cohort progress, completion, institutional
dashboards. **No analytics exist**, and there is no event stream to build them from.
**Reviews** which question a metric answers and what it will be misread as.
**Produces** a metric definition before a dashboard.
**Consult** `docs/database/DOMAIN_MODEL.md`.
**Hands to** `Data Visualization Engineer`, `Statistician` if a claim is made,
`Privacy Engineer` because student-level reporting is personal data by default.

### Statistician
`academic-statistician.md` · SPECIALIST

**Invoke when** a number is used to justify a decision, or an experiment is proposed.
**Reviews** study design, sample size, confounds, and whether the effect claimed is
distinguishable from noise.
**Produces** a methodology, or a rebuttal.
**Hands to** `Model QA Specialist` for model claims, `Analytics Reporter` for reporting
ones.
**Note** the honest current answer to most quantitative questions about ACL is "there is
no data yet". Saying so is this agent's most useful output.

### Research Synthesist
`research-synthesist.md` · SPECIALIST

**Invoke when** a decision should rest on existing literature — learning science,
assessment validity, accessibility research — rather than intuition.
**Reviews** source quality and weighs conflicting evidence honestly.
**Produces** a structured map of what the evidence supports, with confidence marked.
**Hands to** `ACL Learning Experience Architect`, `ACL Software Architect`. Its output
belongs in an ADR's "Options Considered" section, which is where a rejected alternative
earns its place in the record.
