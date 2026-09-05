# Documentation Agents

Two agents are loaded. One is ACL's own, and it holds a **veto on undocumented change**:
a behaviour change ships with its documentation in the *same* change, or it does not ship.

`docs/README.md` sets three rules that bind both agents:

1. Documentation describes what exists, not what is planned — planned work is marked as
   planned.
2. A document that contradicts the code is a bug in the document.
3. ADRs are immutable once accepted. A decision is changed by a new ADR that supersedes
   the old one, never by editing it.

**Known contradictions, as of 2026-09-03.** These are recorded rather than quietly fixed,
because fixing them is its own reviewable change:

| Document | Problem |
|---|---|
| `docs/database/README.md` | Says SQLite is in use and PostgreSQL is planned. ADR-0005 chose MariaDB everywhere |
| `docs/architecture/README.md` | Same stale database claim |
| `docs/PROJECT_STATUS.md` §9 | Says the suite has not been executed; `README.md` reports 39 tests passing |
| `docs/ai/README.md`, `api/`, `deployment/`, `operations/`, `security/`, `ui-ux/README.md`, `business/README.md` | Empty placeholders — 0 lines each |

An agent that cites one of these without knowing it is stale will propagate the error.
That is the specific failure this domain exists to prevent.

---

### ACL Docs Steward
`acl-docs-steward.md` · ACL

**Invoke when** any behaviour changes, any count in `README.md` moves, an ADR is written,
or a document is suspected of being out of date.
**Reviews** whether the documentation matches the code, whether counts are right (routes,
migrations, tables, tests), whether a new ADR is numbered and indexed, and whether a
superseded ADR is marked as superseded on both sides.
**Produces** the documentation edit in the same commit as the change it describes, and the
`docs/adr/README.md` index row.
**Consult** `docs/README.md` for the three rules and the volume map, `docs/adr/README.md`
for the ADR structure (Title, Status, Date, Context, Problem, Options Considered, Decision,
Reasoning, Consequences, Security Implications, Future Considerations).
**Hands to** whoever owns the code the document describes — it fixes documents, not code.
**Refuses** to delete documentation to resolve a contradiction. If two documents disagree,
find out which is true, correct the false one, and say so. Deleting the inconvenient one
destroys the record of the disagreement.
**Register** ACL's documentation says what is true in plain sentences. It does not
advertise, does not use "seamless", "robust" or "powerful", and does not describe planned
work in the present tense.

### Technical Writer
`engineering-technical-writer.md` · CORE

**Invoke when** something long needs writing well: a tutorial, a reference page, an
onboarding guide, the API documentation ACL will eventually need.
**Reviews** structure, accuracy, and whether a reader can act on it.
**Produces** the document. It must inspect the code first — every claim in ACL's
documentation is checkable, and the empty placeholders exist precisely because nobody has
inspected those areas yet.
**Consult** the code, then `README.md` as the model for register and honesty — including
its "What does not exist yet" section, which is the most useful part of it.
**Hands to** `ACL Docs Steward`, which owns whether the document may land and whether the
index needs updating.
**Note** filling one of the seven empty placeholders is genuinely useful work, but only
with the domain owner: `docs/security/README.md` written without `ACL Security Architect`
would be a description of security rather than a statement of ACL's.
