# Agency Agent Coverage

Which agents from [msitarzewski/agency-agents](https://github.com/msitarzewski/agency-agents)
ACL uses, which it holds in reserve, which it has no use for, and why. Provenance
(commit, verification method) is in
[`UPSTREAM_AGENCY_AGENTS.md`](UPSTREAM_AGENCY_AGENTS.md); the machine-readable
record is [`.claude/agent-manifest.tsv`](../../.claude/agent-manifest.tsv).

**Nothing here was installed because of its category.** Every loaded agent was
checked against work ACL actually does or has explicitly planned.

## Categories

| Category | Count | Location | Loaded by Claude Code |
|---|---|---|---|
| `ACL` | 11 | `.claude/agents/` | yes |
| `CORE` | 13 | `.claude/agents/` | yes |
| `SPECIALIST` | 42 | `.claude/agents/` | yes |
| `PERIODIC` | 47 | `.claude/agents-available/` | no — `git mv` to activate |
| `NOT_REQUIRED` | 128 | `.claude/agents-available/` | no |
| **Total** | **241** | | **66 loaded** |

All agents, loaded or not, are **project-local and tracked in Git**. None is
installed globally, so a clone on any machine gets the same system and a deleted
local `.claude` is restored by `git checkout`. The `PERIODIC` and `NOT_REQUIRED`
populations are kept in the repository rather than deleted so that provenance stays
complete and activation is a one-line move rather than a re-download.

Per-agent detail — when invoked, what it reviews, what it produces, which ACL
documents it must read, who it collaborates with — is in
[`agents/`](agents/), split by domain. This file is the index and the rationale.

---

## ACL agents (11)

Written for this codebase. They name real files, real conventions and real ADRs, and
they outrank the generic upstream equivalent on any ACL question. Full detail in
[`agents/`](agents/); definitions in [`.claude/agents/`](../../.claude/agents/).

| Agent | File | Owns | Domain doc |
|---|---|---|---|
| ACL Orchestrator | `acl-orchestrator.md` | Classification, routing, sequencing, gates | [architecture](agents/architecture.md) |
| ACL Software Architect | `acl-software-architect.md` | Boundaries, new domains, infrastructure, ADRs | [architecture](agents/architecture.md) |
| ACL Backend Architect | `acl-backend-architect.md` | Controllers, requests, policies, services, models | [engineering](agents/engineering.md) |
| ACL Database Architect | `acl-database-architect.md` | Migrations, keys, indexes, constraints | [engineering](agents/engineering.md) |
| ACL Frontend Architect | `acl-frontend-architect.md` | Blade, Tailwind, Alpine, tokens, accessibility | [engineering](agents/engineering.md) |
| ACL Security Architect | `acl-security-architect.md` | Trust boundaries, authorization, entitlement, secrets | [security](agents/security.md) |
| ACL AI Engineer | `acl-ai-engineer.md` | Provider abstraction, prompts, retrieval, review gate | [ai](agents/ai.md) |
| ACL Learning Experience Architect | `acl-learning-experience-architect.md` | Pedagogy, content structure, assessment design | [product](agents/product.md) |
| ACL Test Strategist | `acl-test-strategist.md` | Coverage, negative paths, suite health | [testing](agents/testing.md) |
| ACL Docs Steward | `acl-docs-steward.md` | Documentation truth, ADR hygiene | [documentation](agents/documentation.md) |
| ACL Release Gatekeeper | `acl-release-gatekeeper.md` | The evidence-based verdict (read-only) | [testing](agents/testing.md) |

## CORE (13)

Upstream agents ACL's routing table names for ordinary work. Loaded, unmodified.

| Agency name | Original file | ACL purpose | Invoked when |
|---|---|---|---|
| Code Reviewer | `engineering/engineering-code-reviewer.md` | Correctness and maintainability review on every non-trivial change | Before a change is proposed as done |
| Minimal Change Engineer | `engineering/engineering-minimal-change-engineer.md` | Keeps bug-fix diffs small; ACL commits one slice at a time | A bug fix, or a change that is growing |
| Identity & Access Engineer | `engineering/engineering-identity-access-engineer.md` | Session, credential and token mechanics behind ACL's auth | Any `security-critical` task |
| Security Architect | `security/security-architect.md` | Threat modelling and trust-boundary depth behind ACL Security Architect | A whole subsystem's security model |
| Senior SecOps Engineer | `security/security-senior-secops.md` | Scans a submission for secrets and sensitive data first | Every change; first pass |
| Accessibility Auditor | `testing/testing-accessibility-auditor.md` | WCAG audit of learner-facing views — a requirement, not polish | Any `frontend` task |
| Evidence Collector | `testing/testing-evidence-collector.md` | Produces proof for claims; ACL forbids "the UI rendered" as evidence | `verification` tasks |
| Reality Checker | `testing/testing-reality-checker.md` | Independent readiness challenge; defaults to NEEDS WORK | Before calling a slice complete |
| DevOps Automator | `engineering/engineering-devops-automator.md` | Devcontainer, image and pipeline work; ACL has no CI yet | Any `ops` task |
| SRE (Site Reliability Engineer) | `engineering/engineering-sre.md` | SLOs, observability and failure behaviour for a future deployment | Any `ops` task |
| Technical Writer | `engineering/engineering-technical-writer.md` | Long-form documentation depth behind ACL Docs Steward | New reference or tutorial content |
| Git Workflow Master | `engineering/engineering-git-workflow-master.md` | Branch, commit and PR discipline every change ends in | Grouping commits, opening a PR |
| Master Plan Architect | `specialized/specialized-master-plan-architect.md` | Red-teams a plan before code is written; writes no code | Any `architecture` task |

## SPECIALIST (42)

Loaded because ACL has a real or explicitly planned need, but invoked only when that
subject appears. Grouped by the ACL concern they serve.

### Backend, architecture and data

| Agency name | Original file | ACL purpose | Invoked when |
|---|---|---|---|
| Backend Architect | `engineering/engineering-backend-architect.md` | Generic Laravel and API patterns behind ACL Backend Architect | A pattern question ACL has not settled |
| Software Architect | `engineering/engineering-software-architect.md` | DDD and pattern depth behind ACL Software Architect | Boundary design needing general theory |
| Database Optimizer | `engineering/engineering-database-optimizer.md` | Query plans, indexing, N+1 | A slow query or a growing table |
| Database Reliability Engineer | `engineering/engineering-database-reliability-engineer.md` | Backups, recovery, online schema change | Deployment design, data-safety work |
| Data Engineer | `engineering/engineering-data-engineer.md` | Importing institutional records at scale | Bulk import from a university system |
| API Platform Engineer | `engineering/engineering-api-platform-engineer.md` | Contract-first design for the API ACL does not have yet | The API ADR |
| Developer Tooling Engineer | `engineering/engineering-developer-tooling-engineer.md` | CLI and script DX — ACL has `acl:` commands and `scripts/` | A new command or script |
| Codebase Onboarding Engineer | `engineering/engineering-codebase-onboarding-engineer.md` | Explains unfamiliar code from source, not memory | `orientation` tasks |
| Codebase Archaeologist | `specialized/specialized-codebase-archaeologist.md` | Drift between docs, code and past AI sessions | Periodic audit; suspected stale docs |
| Multi-Agent Systems Architect | `engineering/engineering-multi-agent-systems-architect.md` | Design of this agent system itself | Changing governance or routing |
| Workflow Architect | `specialized/specialized-workflow-architect.md` | Complete workflow trees including failure paths | A multi-step user journey |

### Frontend, design and accessibility

| Agency name | Original file | ACL purpose | Invoked when |
|---|---|---|---|
| Frontend Developer | `engineering/engineering-frontend-developer.md` | General frontend depth behind ACL Frontend Architect | A technique ACL has not used before |
| UI Designer | `design/design-ui-designer.md` | Visual system depth beyond `DESIGN_SYSTEM.md` | New component families |
| UX Architect | `design/design-ux-architect.md` | CSS architecture and implementation guidance | Restructuring the view layer |
| UX Researcher | `design/design-ux-researcher.md` | Learner behaviour and usability evidence | Before a learner-facing redesign |
| UI Finish-Gate Reviewer | `design/design-ui-finish-gate-reviewer.md` | Catches generic UI that could be any product | Before shipping a new screen |
| Internationalization Engineer | `engineering/engineering-i18n-engineer.md` | Nigerian languages, plural rules, locale formatting | The localisation ADR |

### AI

| Agency name | Original file | ACL purpose | Invoked when |
|---|---|---|---|
| AI Engineer | `engineering/engineering-ai-engineer.md` | ML/AI integration depth behind ACL AI Engineer | Designing an AI capability |
| RAG Pipeline Engineer | `engineering/engineering-rag-pipeline-engineer.md` | Chunking, hybrid retrieval, re-ranking, eval-driven iteration | Retrieval over course content |
| Prompt Engineer | `engineering/engineering-prompt-engineer.md` | Reliable, testable prompts | Any prompt that reaches a student |
| Model QA Specialist | `specialized/specialized-model-qa.md` | Independent audit, calibration, honest quality claims | Before an AI feature is trusted |

### Security and privacy

| Agency name | Original file | ACL purpose | Invoked when |
|---|---|---|---|
| Application Security Engineer | `security/security-appsec-engineer.md` | Secure code review and SDLC controls at scale | A security sweep beyond one change |
| AI-Generated Code Security Auditor | `security/security-ai-generated-code-auditor.md` | Finds the defects AI-written code ships by default | Auditing agent-written code |
| Secrets & Credential Hygiene Engineer | `security/security-secrets-credential-engineer.md` | Detection, vaulting, rotation, leak response | Deployment design; a suspected leak |
| Privacy Engineer | `engineering/engineering-privacy-engineer.md` | Student data minimisation, retention, DSAR | Any personal-data decision |

### Testing and verification

| Agency name | Original file | ACL purpose | Invoked when |
|---|---|---|---|
| Test Automation Engineer | `testing/testing-test-automation-engineer.md` | End-to-end coverage if ACL adds Playwright or Cypress | Browser-level journeys |
| Test Results Analyzer | `testing/testing-test-results-analyzer.md` | Suite-wide quality and flake trends | The suite grows or misbehaves |
| API Tester | `testing/testing-api-tester.md` | Contract testing for a future API | After the API ADR |
| Performance Benchmarker | `testing/testing-performance-benchmarker.md` | Measuring before optimising | A performance complaint |
| Tool Evaluator | `testing/testing-tool-evaluator.md` | Assessing a proposed dependency; ACL requires justification | Any new dependency |

### Product, learning and analysis

| Agency name | Original file | ACL purpose | Invoked when |
|---|---|---|---|
| Corporate Training Designer | `specialized/corporate-training-designer.md` | Instructional-design method behind the learning architect | Curriculum and programme design |
| Product Manager | `product/product-manager.md` | Scope, lifecycle, outcome measurement | Deciding what to build next |
| Sprint Prioritizer | `product/product-sprint-prioritizer.md` | Sequencing slices by value | Planning a phase |
| Senior Project Manager | `project-management/project-manager-senior.md` | Converting a spec into slices without scope drift | A multi-slice feature |
| Behavioral Nudge Engine | `product/product-behavioral-nudge-engine.md` | Motivation and streak mechanics that do not punish weak connections | Designing gamification |
| Statistician | `academic/academic-statistician.md` | Sound experiment design; separating signal from noise | Learning-outcome claims |
| Research Synthesist | `research/research-synthesist.md` | Weighing evidence across sources honestly | Pedagogical or technical literature review |
| Analytics Reporter | `support/support-analytics-reporter.md` | Turning progress data into something a lecturer can act on | The analytics ADR |
| Data Visualization Engineer | `engineering/engineering-data-visualization-engineer.md` | Perceptually honest, accessible charts | Any dashboard or progress chart |

### Explicitly planned, not yet built

Loaded because ACL has named these as intended features, so the design opinion should
be present the moment work starts — not because any of it exists.

| Agency name | Original file | ACL purpose | Invoked when |
|---|---|---|---|
| Payments & Billing Engineer | `engineering/engineering-payments-billing-engineer.md` | Idempotent payment flows behind a replaceable provider | The payments ADR |
| Search Relevance Engineer | `engineering/engineering-search-relevance-engineer.md` | Course and content search that returns the right thing | The search ADR |
| Realtime Collaboration Engineer | `engineering/engineering-realtime-collaboration-engineer.md` | Presence, live sessions, offline-first sync for weak networks | The offline/PWA ADR |

## PERIODIC (47)

In `.claude/agents-available/`. Tracked in Git, **not loaded**, so they cost no
session context. Each has a credible ACL use case that is not current work.
Activate with `git mv` — see [`AGENT_ROUTING.md`](AGENT_ROUTING.md) §4.

### Future platform surfaces

| Agency file | Credible ACL use |
|---|---|
| `engineering/engineering-mobile-app-builder.md` | A mobile client, named as a future direction |
| `engineering/engineering-mobile-release-engineer.md` | Store release pipeline for that client |
| `engineering/engineering-video-streaming-engineer.md` | Video lesson blocks on constrained bandwidth |
| `engineering/engineering-voice-ai-integration-engineer.md` | Voice tutoring and low-literacy access paths |
| `engineering/engineering-webassembly-engineer.md` | In-browser practical labs (a named domain) |
| `engineering/engineering-network-engineer.md` | Campus network and on-premise institutional deployment |

### Authoring, content and curriculum

| Agency file | Credible ACL use |
|---|---|
| `engineering/engineering-cms-developer.md` | The authoring UI ACL does not have — content is seeded today |
| `engineering/engineering-knowledge-graph-engineer.md` | Prerequisite and curriculum graphs for adaptive sequencing |
| `engineering/engineering-email-intelligence-engineer.md` | Notifications and email delivery, both absent today |
| `specialized/specialized-document-generator.md` | Certificates and transcripts |
| `specialized/language-translator.md` | Nigerian-language content and interface localisation |
| `design/design-visual-storyteller.md` | Explanatory diagrams inside lessons |
| `design/design-inclusive-visuals-specialist.md` | Imagery that represents the actual learner population |
| `design/design-brand-guardian.md` | Brand consistency once ACL has a brand to guard |

### Institutional deployment and operations

| Agency file | Credible ACL use |
|---|---|
| `engineering/engineering-incident-response-commander.md` | Production incidents once ACL is deployed |
| `engineering/engineering-it-service-manager.md` | Integrating with university IT service processes |
| `engineering/engineering-finops-engineer.md` | AI and hosting cost control at institutional scale |
| `engineering/engineering-ai-data-remediation-engineer.md` | Cleaning imported student and programme records |
| `specialized/change-management-consultant.md` | Rolling ACL out inside a university |
| `support/support-infrastructure-maintainer.md` | Ongoing environment maintenance |
| `security/security-cloud-security-architect.md` | Cloud posture for a real deployment |
| `security/security-incident-responder.md` | Handling a live security incident |
| `security/security-penetration-tester.md` | Authorised testing before an institutional launch |
| `security/security-threat-detection-engineer.md` | Detection once there is production traffic |
| `security/security-threat-intelligence-analyst.md` | Threat context for the education sector |
| `security/security-compliance-auditor.md` | Procurement and regulatory audits |
| `engineering/engineering-section-508-specialist.md` | Formal accessibility compliance for procurement |
| `specialized/data-privacy-officer.md` | Student-data governance and NDPR-style obligations |
| `support/support-legal-compliance-checker.md` | Licensing — ACL has not chosen a license yet |

### Agent system, tooling and delivery

| Agency file | Credible ACL use |
|---|---|
| `specialized/agents-orchestrator.md` | Upstream's generic orchestrator, kept as a reference model |
| `specialized/automation-governance-architect.md` | Governance if agent automation grows |
| `specialized/agentic-identity-trust.md` | Trust and identity between agents |
| `specialized/specialized-mcp-builder.md` | MCP tooling for ACL-specific agent capabilities |
| `specialized/lsp-index-engineer.md` | Code intelligence as the codebase grows |
| `engineering/engineering-autonomous-optimization-architect.md` | Autonomous coding workflows |
| `engineering/engineering-llm-post-training-engineer.md` | Fine-tuned tutoring models, far future |
| `engineering/engineering-rapid-prototyper.md` | Throwaway spikes that must stay throwaway |
| `engineering/engineering-senior-developer.md` | Surge implementation capacity |
| `testing/testing-workflow-optimizer.md` | CI and workflow efficiency once CI exists |
| `project-management/project-management-experiment-tracker.md` | A/B tests on learning features |
| `project-management/project-management-project-shepherd.md` | Long-running multi-phase delivery |

### Community, research and funding

| Agency file | Credible ACL use |
|---|---|
| `academic/academic-psychologist.md` | Learning psychology and motivation evidence |
| `product/product-feedback-synthesizer.md` | Synthesising student and lecturer feedback |
| `product/product-trend-researcher.md` | Education-technology landscape |
| `specialized/specialized-developer-advocate.md` | Open-source contributor community |
| `specialized/grant-writer.md` | Funding an open-source education platform |
| `marketing/marketing-seo-specialist.md` | Public course pages need to be findable — the only marketing agent with an ACL use |

## NOT REQUIRED (128 held in reserve + 44 never copied)

Grouped rather than enumerated, because listing 172 agent names ACL will not use
would obscure the ones it does. The machine-readable per-file record is
[`.claude/agent-manifest.tsv`](../../.claude/agent-manifest.tsv) — filter on
`category == NOT_REQUIRED`.

### Held in `.claude/agents-available/` (128)

| Group | Count | Why not |
|---|---|---|
| `marketing/*` except the SEO specialist | 35 | Social-platform growth, China-market and campaign agents. ACL reaches universities through institutional relationships, not TikTok strategy |
| `specialized/*` — business, legal, healthcare, retail, real estate, HR, finance operations | 42 | Whole-business role-play agents for industries ACL is not in |
| `sales/*` | 9 | ACL has no sales motion; access is granted by a department, not sold to a student |
| `paid-media/*` | 7 | No advertising spend to manage |
| `finance/*` | 5 | Business accounting and investment analysis, not payment engineering. Payment *engineering* is loaded as SPECIALIST |
| `engineering/*` — WordPress, Drupal, Feishu, WeChat, Solidity, IoT, embedded, GaussDB, Rust, USWDS, Filament, desktop, OrgScript | 15 | Stacks ACL does not use. Adopting any of them would need an ADR first, at which point the agent can be activated |
| `academic/*` — anthropologist, geographer, historian, narratologist | 4 | Subject-matter research personas, not platform engineering. Course *content* is authored by academics, not generated by these |
| `project-management/*` — Jira, meeting notes, studio operations, studio producer | 4 | Tooling and agency-studio workflows ACL does not run |
| `support/*` — executive summaries, finance tracking, support responder | 3 | No support desk, no executive reporting line |
| `design/*` — image prompt engineer, persona walkthrough, whimsy injector | 3 | Visual-generation and playful-copy agents that would fight ACL's fixed design language |
| `security/security-blockchain-security-auditor.md` | 1 | No blockchain anywhere in ACL |

### Never copied into ACL (44)

Excluded at integration time rather than carried, because there is no plausible path
from a learning platform to any of them:

| Upstream division | Count | Contents |
|---|---|---|
| `game-development/` | 21 | Unity, Unreal, Godot, Roblox, Blender, level and narrative design |
| `gis/` | 13 | Geospatial analysis, cartography, spatial data engineering |
| `spatial-computing/` | 6 | visionOS, XR, Metal, cockpit interaction |
| `healthcare/` | 3 | Clinical evidence, health-system strategy |
| `integrations/mcp-memory/` | 1 | A memory-augmented duplicate of `backend-architect` |

Upstream also ships `strategy/`, `examples/` and `scripts/`, which contain
documentation and helper scripts rather than agent definitions. They are outside the
scope of this matrix.

---

## Reconciliation

| | Count |
|---|---|
| Upstream agent definitions at the integrated commit | 274 |
| Tracked in this repository | 230 |
| — loaded (`.claude/agents/`) | 55 |
| — reserve (`.claude/agents-available/`) | 175 |
| Never copied | 44 |
| ACL-written agents | 11 |
| **Loaded in total** | **66** |

Verify these numbers rather than trusting them:

```bash
bash scripts/agents/validate-agent-system.sh
```




