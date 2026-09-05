# Upstream Agency Agents — provenance

Where ACL's non-ACL agents came from, at exactly which version, and what was done to
them. This file exists so that an upstream update can be taken safely and so nobody
has to guess whether a definition is ACL's work or someone else's.

---

## Source

| | |
|---|---|
| Project | The Agency — AI Specialists |
| Repository | https://github.com/msitarzewski/agency-agents |
| Commit integrated | `4bab3cf4a222fc51717ba884c2f91a350cc57187` |
| Commit date | 2026-09-02 20:51:43 −0500 |
| Commit subject | `Revert "test(install): add a regression suite for install.sh + CI on Linux and macOS (#772)" (#827)` |
| Tag at that commit | none — the repository publishes no tags |
| Integrated into ACL | 2026-09-03 |
| Upstream license | MIT, © 2025 AgentLand Contributors |

The upstream repository has no release tags, so the commit hash is the only precise
version identifier. Record the new hash here whenever agents are refreshed.

**License note.** The upstream agents are MIT-licensed and are redistributed here
unchanged, so upstream's copyright and permission notice continue to apply to them.
ACL itself has not yet chosen a license (see `README.md`); that decision must not be
read as covering these files.

## What was taken

| | Count |
|---|---|
| Agent definitions upstream at that commit | 274 |
| Copied into this repository | 230 |
| Loaded in `.claude/agents/` | 55 |
| Held in `.claude/agents-available/` | 175 |
| Deliberately not copied | 44 |
| **Copied unchanged** | **230** |
| **Adapted** | **0** |

An "agent definition" here means a Markdown file with YAML frontmatter carrying both
`name:` and `description:`. Upstream's `strategy/`, `examples/` and `scripts/`
directories, and its root `README.md`, `CONTRIBUTING.md`, `SECURITY.md`,
`divisions.json` and `tools.json`, are documentation and tooling rather than agents,
and were not copied.

The 44 that were not copied are the `game-development/` (21), `gis/` (13),
`spatial-computing/` (6) and `healthcare/` (3) divisions, plus
`integrations/mcp-memory/backend-architect-with-memory.md` — a memory-augmented
duplicate of an agent ACL already loads. Rationale is in
[`AGENCY_AGENT_COVERAGE.md`](AGENCY_AGENT_COVERAGE.md).

Those five path prefixes are also recorded as data in
[`.claude/agent-exclusions.txt`](../../.claude/agent-exclusions.txt), each with its
reason. `check-upstream-agents.sh` reads that file so a deliberate exclusion is
reported as `EXCLUDED` rather than resurfacing as a new agent to triage on every run,
and `validate-agent-system.sh` fails if a prefix listed there is also tracked in the
manifest. Prose alone would not survive the next refresh; the file makes the decision
enforceable.

## Nothing was modified — and how that was verified

**All 230 copied files are byte-identical to their upstream originals.** ACL's
opinions live in the 11 `acl-*` agents and in this documentation set, never as edits
to an upstream file. That is a deliberate design choice: it makes an upstream refresh
a mechanical operation with no merge conflicts and no risk of silently losing an ACL
customisation.

Verified by full-content hashing rather than by inspection:

```bash
# every tracked upstream-derived agent, hashed and compared against the clone
sha256sum .claude/agents/*.md .claude/agents-available/*.md
sha256sum <upstream-clone>/<division>/<same-basename>.md
```

Result: 230 of 230 hashes matched. The per-file hashes are recorded in
[`.claude/agent-manifest.tsv`](../../.claude/agent-manifest.tsv), so drift is
detectable later without re-cloning.

An earlier pass reported 12 modified files. That was a false positive: the inventory
used a case-insensitive filename filter that excluded upstream's twelve
`security/security-*.md` **agents** along with its root `SECURITY.md`. Byte comparison
disproved it. The lesson is recorded here because the same mistake is easy to repeat
when writing the comparison script.

## Naming

Local filename ⇔ upstream path, exactly:

```
.claude/agents/engineering-code-reviewer.md
  ⇔ engineering/engineering-code-reviewer.md

.claude/agents-available/gis-analyst.md          (not copied — illustrative)
  ⇔ gis/gis-analyst.md
```

Upstream filenames already carry their division prefix, so the local basename is
identical to the upstream basename and the division is recoverable from the manifest.
Do not rename an upstream agent — renaming breaks the mapping the update script
depends on. If ACL needs different behaviour, write an `acl-*` agent instead.

Two agents present locally were **added during integration**, not inherited from an
earlier state of `.claude/agents/`:
`engineering/engineering-developer-tooling-engineer.md` and
`research/research-synthesist.md`. Both are byte-identical copies.

## Global versus project-local

Every agent is **project-local**. Nothing was installed into a user-level or global
agent directory, and nothing outside this repository is required for the system to
work. Consequences:

- `git clone` reproduces the full system on any machine.
- A new Codespace has it as soon as the repository is checked out.
- Deleting `.claude/` locally is repaired by `git checkout -- .claude`.
- A second developer gets the identical set, including the reserve.
- Reinstalling Claude Code changes nothing.

Upstream's own installer offers a global install. ACL does not use it, because a
global install is machine state and would defeat the purpose of tracking the agents
here.

## Refreshing

The process — how to compare, what to review, what to leave alone, and how to record
the new commit — is in [`AGENT_UPDATE_PROCESS.md`](AGENT_UPDATE_PROCESS.md). The short
form:

```bash
bash scripts/agents/check-upstream-agents.sh
```

**State at integration.** That script was run against `origin/main` on the day of
integration and reported the pinned commit as 3 commits behind
`04eadbd3e534`, with one tracked file changed upstream —
`engineering/engineering-developer-tooling-engineer.md` — and no additions, removals
or drift. That change was **deliberately not taken**: the integration is pinned, and
taking a refresh is a separate operation with its own review, its own commits and its
own row in the change log below. Pinning is the feature.

## Change log

| Date | Upstream commit | Action |
|---|---|---|
| 2026-09-03 | `4bab3cf4a222fc51717ba884c2f91a350cc57187` | Initial integration: 230 agents tracked, 55 loaded, 0 adapted. Curated from a previously flat 228-agent directory; 2 agents added, 175 moved to reserve |

Append a row here on every refresh. Do not rewrite existing rows.
