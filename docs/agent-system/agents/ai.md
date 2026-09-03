# AI Agents

The agents for work that calls a model provider. Six are loaded; one is ACL's own.

**ACL contains no AI implementation.** Verified 2026-09-03: no provider client, no API
key handling, no embeddings, no vector storage, no prompt library, no AI-related table,
and `docs/ai/README.md` is an empty placeholder. Every agent in this file therefore
produces a *design* before it produces code, and the first deliverable of any AI work is
an ADR — not a package install.

The seven non-negotiables that apply to all of it, from `acl-ai-engineer.md` and
[`ACL_DEVELOPMENT_CONSTITUTION.md`](../../ACL_DEVELOPMENT_CONSTITUTION.md) §6:

1. A provider abstraction from the first line of code — never a vendor SDK called from a
   controller.
2. No key in the repository, ever. Environment variables and Codespaces secrets only.
3. AI output is a **draft**. Anything that reaches a learner as fact, a grade, or a
   credential has a recorded human approver.
4. Every call is attributed to a user, metered, and capped.
5. Failure is normal. Timeouts, refusals and malformed output are the expected path.
6. Model input and model output are both untrusted.
7. Boring infrastructure. A vector store is new infrastructure and needs its own ADR.

---

### ACL AI Engineer
`acl-ai-engineer.md` · ACL

**Invoke when** anything touches a model provider, embeddings, retrieval, grading,
tutoring, metering or quotas.
**Reviews** whether the abstraction survives a provider swap, where keys come from, what
data leaves the institution, whether the human-review gate exists, whether cost is
bounded per user, and what happens when the provider returns nothing.
**Produces** the ADR first; then the provider interface, the prompt store, the metering
table and the faked provider the test suite uses.
**Consult** `docs/ai/README.md` (empty — filling it is part of the work),
`docs/ACL_MASTER_SPECIFICATION.md`, `docs/adr/`,
[`ACL_DEVELOPMENT_CONSTITUTION.md`](../../ACL_DEVELOPMENT_CONSTITUTION.md) §6.
**Hands to** `ACL Security Architect` (what leaves ACL, prompt-injection sinks, secrets —
it has a veto), `Model QA Specialist` (whether the quality claim is honest),
`ACL Test Strategist` (never a live provider call in the suite),
`ACL Learning Experience Architect` (whether the pedagogy is sound).
**Defers on** whether the pedagogy is right and whether the ADR is accepted.

### AI Engineer
`engineering-ai-engineer.md` · SPECIALIST

**Invoke when** you want general ML and AI-integration depth behind an ACL decision:
serving, batching, embedding models, evaluation harnesses.
**Reviews** the technical design in general terms.
**Produces** advice only.
**Consult** the same ADR draft.
**Hands to** `ACL AI Engineer`, which decides. Discount any advice that assumes a Python
service, a GPU, or infrastructure ACL does not have — the constitution's precedence order
puts ACL's ADRs above general practice.

### Prompt Engineer
`engineering-prompt-engineer.md` · SPECIALIST

**Invoke when** a prompt needs to be reliable rather than merely plausible — tutoring
turns, feedback generation, rubric application.
**Reviews** instruction clarity, failure modes, injection resistance, output-format
stability, token cost.
**Produces** a versioned prompt with the test cases that show where it breaks. Prompts are
source code: they live in the repository, are reviewed in a diff, and are never edited in
a provider's console.
**Consult** the pedagogy the prompt is meant to serve, not just the task text.
**Hands to** `Model QA Specialist` for evaluation, `ACL Security Architect` for injection
review.

### RAG Pipeline Engineer
`engineering-rag-pipeline-engineer.md` · SPECIALIST

**Invoke when** retrieval over ACL's own content is designed — course materials, lessons,
institutional documents.
**Reviews** chunking strategy against the actual content shape, hybrid lexical + vector
retrieval, re-ranking, and whether retrieval quality is measured or assumed.
**Produces** a pipeline design with an evaluation set. A RAG system without a measured
retrieval quality number is a demo.
**Consult** `docs/database/DOMAIN_MODEL.md` for what content exists to retrieve — today
that is courses, modules and lessons, nothing else.
**Hands to** `ACL AI Engineer` (the abstraction), `Search Relevance Engineer` (the lexical
half), `ACL Database Architect` (any new table), `Privacy Engineer` (what gets indexed).

### Search Relevance Engineer
`engineering-search-relevance-engineer.md` · SPECIALIST

**Invoke when** search is built, whether or not AI is involved. ACL has no search today.
**Reviews** analyzer and index design, BM25 tuning, hybrid retrieval, nDCG-style
judgement sets.
**Produces** a design that starts with what MariaDB can already do. Elasticsearch or
OpenSearch is new infrastructure: ADR and human approval first.
**Consult** `docs/adr/0005-*`, [`../AGENT_GOVERNANCE.md`](../AGENT_GOVERNANCE.md) §5.
**Hands to** `ACL Software Architect`, `Database Optimizer`.

### Model QA Specialist
`specialized-model-qa.md` · SPECIALIST

**Invoke when** an AI feature is about to be described as working, and whenever a quality
number is quoted to a stakeholder.
**Reviews** replication, calibration, interpretability, drift, and the honesty of every
claim made about the model.
**Produces** an audit-grade report that says what was measured, on what data, and what
remains unknown.
**Consult** the evaluation set and the prompt versions under review.
**Hands to** `ACL AI Engineer` and `ACL Release Gatekeeper`. For an educational platform
this is the agent that matters most: a grading feature that is 90% accurate is a feature
that is wrong about one student in ten, and that has to be stated in those terms.
