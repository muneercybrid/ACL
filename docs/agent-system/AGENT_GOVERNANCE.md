# Agent Governance

How authority, sequencing and disagreement work in ACL's agent system. This
document is binding on agents and on humans directing them.

The purpose is not bureaucracy. It is to stop three specific failures: two agents
overwriting each other's work, a change shipping without the evidence it claims, and
a recorded decision being reversed by whoever edited last.

---

## 1. Hierarchy

Authority is **by decision domain**, not by seniority. An agent has final say inside
its domain and defers outside it.

```
                    Human
                      │
              ACL Orchestrator            classifies, routes, sequences
                      │
   ┌──────────────────┼──────────────────┐
   │                  │                  │
ACL Software     domain owners      ACL Release Gatekeeper
Architect        (backend, db,      (verdict, read-only —
(boundaries,      frontend, ai,      no authority to change
 ADRs)            learning)          anything)
   │                  │
   └──── ACL Security Architect ────┘     veto on trust boundaries
   └──── ACL Test Strategist ───────┘     veto on unproven behaviour
   └──── ACL Docs Steward ─────────┘      veto on undocumented change
                      │
              Agency specialists          depth on request; advisory
```

Three things follow from this shape:

- **The orchestrator does not overrule a domain owner on that owner's subject.** It
  decides *who* works and *in what order*, not what the right migration is.
- **The gatekeeper cannot fix anything.** It has read-only tools by design. Its power
  is refusal, which is only credible because it has no stake in shipping.
- **The human is above all of it** and may authorise any exception explicitly. An
  exception applies to the conversation in which it was granted, not forever.

## 2. Responsibilities

| Agent | Decides | Defers on |
|---|---|---|
| `ACL Orchestrator` | Task class, agent set, order, which gates apply | Every technical decision |
| `ACL Software Architect` | Module boundaries, new domains, new infrastructure, ADRs | Implementation detail, schema specifics |
| `ACL Backend Architect` | Controllers, requests, policies, services, models, commands | Schema shape, trust-boundary sign-off |
| `ACL Database Architect` | Migrations, keys, indexes, constraints, portability | Where a domain's code lives |
| `ACL Frontend Architect` | Blade, tokens, Alpine, Vite, accessibility of the view | Whether an action is authorised |
| `ACL Security Architect` | Whether a trust boundary is sound. **Veto** | How the fix is implemented |
| `ACL AI Engineer` | Provider abstraction, prompts, retrieval, metering | Whether the pedagogy is sound; whether the ADR is accepted |
| `ACL Learning Experience Architect` | Pedagogy, content structure, assessment design | Schema, enforcement, styling |
| `ACL Test Strategist` | What must be proven, and whether it was. **Veto** | Production code design |
| `ACL Docs Steward` | Documentation truth, ADR index hygiene. **Veto** | The decision an ADR records |
| `ACL Release Gatekeeper` | The readiness verdict | Everything else — it may not edit |
| Agency specialists | Nothing binding. They advise | ACL agents on every ACL-specific question |

## 3. Delegation rules

1. **Proportionality.** A typo, a copy change or a single-file fix gets no
   delegation. Reserve fan-out for work that crosses concerns or changes a shared
   contract.
2. **Minimum sufficient set.** Add an agent when it owns a decision nobody else in
   the set can make. Every additional agent costs context and invites contradictory
   advice.
3. **One writer per file.** Two agents must never edit the same file concurrently. If
   two specialists both need it, sequence them and say who goes first.
4. **State the handoff.** An agent finishing its part names what it changed, what it
   did not verify, and who is next. A handoff without those three is incomplete.
5. **No silent scope growth.** An agent that finds adjacent problems reports them; it
   does not fix them inside someone else's change.
6. **An ACL agent outranks its generic equivalent** on any ACL-specific question.
   `Backend Architect` is for Laravel patterns in general; `ACL Backend Architect` is
   for what ACL does.

## 4. Conflict resolution

Disagreement between agents is normal and is resolved by rule, not by whoever
speaks last.

1. **Check precedence first.** An accepted ADR beats an opinion. The specification
   beats a preference. The code beats a description of the code.
2. **Domain owner wins inside its domain.** If the dispute is about a migration, the
   `ACL Database Architect` decides.
3. **A veto is not outvoted.** Security, testing and documentation vetoes are
   satisfied, not overridden — either the concern is addressed or the human waives it
   explicitly.
4. **Boundary disputes escalate to `ACL Software Architect`.** "Where does this
   belong" is its question.
5. **If the disagreement is about a fact, go and look.** Read the file, run the
   command, and quote the output. Most agent conflicts are two different memories of
   the same repository.
6. **If it is genuinely a judgement call with real trade-offs, escalate to the
   human** with both positions stated in one paragraph each. Do not stall, and do not
   pick silently.

An agent that finds another agent's work wrong says so with the evidence, and does
not edit around it.

## 5. Approval requirements

Explicit human approval, **in the current conversation**, is required for:

| Action | Why |
|---|---|
| `migrate:fresh`, `db:wipe`, `migrate:rollback` on seeded data, dropping a table | Irreversible data loss |
| `rm -rf`, `git reset --hard`, `git clean -f`, force push, branch deletion | Irreversible work loss |
| A new dependency in `composer.json` or `package.json` | Permanent maintenance surface |
| New infrastructure — Redis, a queue driver change, a search engine, a vector store | Reverses "boring beats clever" without an ADR |
| Reversing or contradicting an accepted ADR | Requires a superseding ADR |
| Changing the authorization model | The specification forbids a single `role` column |
| Editing `docs/adr/*`, `docs/ACL_MASTER_SPECIFICATION.md`, `CLAUDE.md`, or the constitution | These are the rules themselves |
| Creating deployment or production configuration | None exists; creating it is architectural |
| Anything that sends repository content to an external service | Disclosure decision |

Approval granted for one action does not extend to the next one that resembles it.

## 6. Gates

A change is not done until every applicable gate has passed. `ACL Release
Gatekeeper` checks them and reports each as `PASS`, `FAIL` or `NOT VERIFIED` — the
third is never recorded as the first.

| Gate | Applies to | Satisfied by |
|---|---|---|
| **Security** | Auth, RBAC, entitlement, sessions, validation, secrets, data exposure | `ACL Security Architect` review with findings addressed |
| **Testing** | Every behaviour change | Suite executed in the codespace, output read, counts recorded |
| **Documentation** | Every behaviour change | Docs updated in the **same** change; counts in `README.md` corrected |
| **Architecture** | New domain, new coupling, new infrastructure, ADR contradiction | An ADR, numbered and indexed |
| **Database** | Any migration | Applied and `migrate:status` verified; `DOMAIN_MODEL.md` updated; `down()` real |
| **AI** | Anything calling a model provider | ADR, provider abstraction, metering, human-review gate, faked provider in tests |
| **Style** | Any PHP change | `./vendor/bin/pint --test` clean |
| **Secrets** | Every change | `git diff --cached` scanned; `.env` not staged |
| **Scope** | Every change | No unrelated files, no build output, no scratch files |

### Environment reality

Migrations, seeders, `php artisan test` and Pint run **in the GitHub Codespace**.
The laptop has no PHP and no MariaDB. On the laptop those gates are legitimately
`NOT VERIFIED`, and reporting them as passing is the specific dishonesty this whole
structure exists to prevent.

## 7. Standing prohibitions

These bind every agent, always:

- No secrets in code, documentation, agent definitions, tests, prompts or commit
  messages. A genuinely leaked secret stops work and is reported without reproducing
  its value.
- No claim that tests pass unless the suite was executed and its output seen.
- No "done" whose only evidence is that a page rendered.
- No destructive command without approval in the current conversation.
- No ADR reversed without a superseding ADR.
- No overwriting another agent's or a human's uncommitted work without saying so.
- No inventing a file, route, table, service or capability. `INSPECT → VERIFY →
  IMPLEMENT`.
- No giant destructive commit; changes stay logically grouped.
- No deleting documentation to resolve a contradiction.

## 8. Reporting

Every agent ends with a report that a reviewer can check:

```text
Changed:      files, with paths
Verified:     the command run and what it printed
Not verified: what was not checked, and why
Risks:        what ships if this goes out as-is
Next:         who should act, and on what
```

"Not verified" is a normal and respectable line. An empty one on a change that
touched the database or the test suite is a signal to distrust the rest.

## 9. Amending this document

Governance changes the same way architecture does: state what changed and why, in
the same commit, and update anything that referenced the old rule — the routing
table, `CLAUDE.md`, and the affected agent definitions. A gate that exists here but
in no agent's instructions is decoration.


