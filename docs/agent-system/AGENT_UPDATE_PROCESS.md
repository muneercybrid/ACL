# Agent Update Process

How to take a newer version of the upstream Agency agents without losing ACL's work.

The design that makes this safe is already in place: **no upstream file has been
edited**, so a refresh is a file replacement, not a merge. ACL's opinions live in the
eleven `acl-*` agents and in `docs/agent-system/`, which a refresh never touches.

---

## Before you start

| | |
|---|---|
| Current upstream commit | recorded in [`UPSTREAM_AGENCY_AGENTS.md`](UPSTREAM_AGENCY_AGENTS.md) |
| Per-file hashes | [`.claude/agent-manifest.tsv`](../../.claude/agent-manifest.tsv), column `sha256` |
| Files that may change | `.claude/agents/*.md` and `.claude/agents-available/*.md` **except** `acl-*.md` |
| Files that must not change | every `acl-*.md`, everything under `docs/` |

Work on a branch, and never on `main`.

## 1. See what changed

```bash
bash scripts/agents/check-upstream-agents.sh
```

The script clones (or fetches into) `/tmp/agency-agents-upstream`, resolves each
manifest row's `upstream_path`, hashes it, and compares against the recorded `sha256`.
It writes no files inside the repository and changes nothing. Its report has four
sections:

| Section | Meaning | Default action |
|---|---|---|
| `CHANGED` | Upstream edited a file ACL tracks | Review the diff, then take it |
| `ADDED` | Upstream has an agent ACL has never seen | Triage — see below |
| `REMOVED` | Upstream deleted a file ACL tracks | Usually keep ACL's copy; decide explicitly |
| `DRIFT` | A local file no longer matches its own manifest hash | **Stop.** Someone edited an upstream file |

`DRIFT` is the one that matters. It means the byte-identity guarantee has been broken,
so investigate the change before anything else — either it is an accident to revert, or
it is a real customisation that belongs in an `acl-*` agent instead.

## 2. Read the diffs

```bash
UP=/tmp/agency-agents-upstream
diff -u .claude/agents/engineering-code-reviewer.md \
        "$UP/engineering/engineering-code-reviewer.md"
```

Read every changed file. This is the review, and it is the whole point of pinning a
commit. Three questions per file:

1. Does it still describe the same role? A rewritten agent may no longer deserve its
   category, or its slot in the routing table.
2. Does it now contradict ACL? An upstream agent that starts recommending Redis, a
   `role` column or SQLite conflicts with an ADR. ACL wins; note the conflict in the
   coverage matrix.
3. Does it introduce a `tools:` list, an `Agent`/`Task` assumption, or a hook that
   assumes upstream's installer? Those are portability problems worth recording even
   when the file is taken as-is.

## 3. Take the change

Copy, do not patch — the goal is byte-identity with the new upstream, not a
hand-reconciled hybrid:

```bash
cp "$UP/engineering/engineering-code-reviewer.md" \
   .claude/agents/engineering-code-reviewer.md
```

An agent that lives in `.claude/agents-available/` is refreshed exactly the same way.
Its path in the manifest is what tells you which directory it belongs in — never change
the directory during a refresh, because that is an activation decision, not an update.

## 4. Triage additions

A new upstream agent is not automatically wanted. Decide with the same test the
original integration used:

- Does it own work ACL will plausibly do? If not, do not copy it — add its path prefix
  to [`.claude/agent-exclusions.txt`](../../.claude/agent-exclusions.txt) with the
  reason, and record the division in `UPSTREAM_AGENCY_AGENTS.md`. That file is what
  makes `check-upstream-agents.sh` report a known exclusion as `EXCLUDED` instead of
  presenting it as new on every run.
- If yes but not now → copy into `.claude/agents-available/`, category `PERIODIC`.
- If yes and now → copy into `.claude/agents/`, give it a category, and add it to
  [`AGENT_ROUTING.md`](AGENT_ROUTING.md). An agent nobody routes to is dead weight.

The excluded divisions (`game-development/`, `gis/`, `spatial-computing/`,
`healthcare/`) stay excluded unless ACL's scope actually changes — and changing that
list is a scope decision that belongs in the same commit as the documentation
explaining it.

## 5. Update the records

Four files, one commit:

```bash
# manifest — re-hash everything, then rewrite the sha256 column
bash scripts/agents/validate-agent-system.sh   # reports hash mismatches
$EDITOR .claude/agent-manifest.tsv

# provenance — new commit hash, new counts, a new change-log row
$EDITOR docs/agent-system/UPSTREAM_AGENCY_AGENTS.md

# coverage — categories for anything added, removed or reclassified
$EDITOR docs/agent-system/AGENCY_AGENT_COVERAGE.md

# routing — only if a newly loaded agent needs a trigger
$EDITOR docs/agent-system/AGENT_ROUTING.md
```

In `UPSTREAM_AGENCY_AGENTS.md`, **append** a change-log row. Do not rewrite an existing
one; the history of what was taken when is the reason the file exists.

## 6. Validate and verify

```bash
bash scripts/agents/validate-agent-system.sh
git diff --cached | grep -nEi '(api[_-]?key|secret|token|password|BEGIN [A-Z ]*PRIVATE KEY)'
```

Then confirm the system still works in the way that actually matters: start a session
and invoke two or three refreshed agents on a real question. A definition that parses
is not a definition that behaves.

## 7. Commit

Group the commits so a reviewer can read them:

```
chore(agents): refresh upstream agents to <short-sha>
docs(agents): record upstream refresh <short-sha> and reclassify N agents
```

Never mix a refresh with an ACL agent change. If reviewing the upstream diff makes you
want to change an `acl-*` agent, that is a second commit with its own reasoning.

## If an upstream refresh goes wrong

Everything is tracked, project-local and recoverable:

```bash
git checkout -- .claude/                     # discard an in-progress refresh
git revert <refresh-commit>                  # undo a merged one
```

Because no upstream file carries an ACL edit, reverting a refresh loses nothing but the
refresh itself.

## Cadence

There is no schedule, and a refresh for its own sake has no value. Refresh when:

- ACL is about to work in a domain whose upstream agent has likely improved.
- Upstream announces a change to the frontmatter format or the agent contract.
- `check-upstream-agents.sh` reports `DRIFT`, which needs investigating whenever it
  appears.

Pinning a commit is the feature. An unpinned dependency that silently rewrites the
instructions your agents follow is worse than an old one you can read.
