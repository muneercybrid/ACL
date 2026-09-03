# Agent Customization

How to write a new ACL agent, adapt behaviour that an upstream agent gets wrong, and
change an existing one without breaking the system.

**The governing rule: never edit an upstream agent.** All 230 upstream files are
byte-identical to their originals, and keeping them that way is what makes an upstream
refresh a mechanical operation. If an upstream agent is wrong for ACL, write or extend
an `acl-*` agent that says what ACL does — and note the disagreement in
[`AGENCY_AGENT_COVERAGE.md`](AGENCY_AGENT_COVERAGE.md) so the next reader knows it was
a decision.

---

## Anatomy of an ACL agent

```markdown
---
name: ACL Example Architect
description: One sentence on what it owns, then when to invoke it. This is the text
  the model reads when choosing an agent, so make the triggers concrete.
color: "#2563EB"
emoji: 🏛️
vibe: One memorable line that fixes the agent's attitude.
---

# ACL Example Architect

Who it is, in two sentences, including what makes it ACL-specific.

## Read before you write
The authoritative files, by path. Name the nearest existing example in the codebase.

## Conventions you follow without being asked
The rules taken from real files, with the file named.

## What you produce
The deliverable, plus the test, plus the doc line.

## What you refuse
The concrete failure modes. This section does more work than any other.

## Collaboration
Who to hand to, and who decides what this agent does not.
```

### Frontmatter rules

| Key | Required | Notes |
|---|---|---|
| `name` | yes | Human-readable and unique across both agent directories. ACL agents start with `ACL ` |
| `description` | yes | Used for selection. State the trigger conditions, not just the subject |
| `color` | no | Hex, quoted |
| `emoji` | no | One character |
| `vibe` | no | Upstream convention ACL keeps; it makes an agent's stance memorable |
| `tools` | **avoid** | See below |

### Why `tools:` is almost always omitted

Tool names differ between harnesses — the delegation tool is `Task` in standard Claude
Code and `Agent` in some SDK hosts. An agent pinned to a name that does not exist in
the current harness loses that capability silently.

ACL therefore omits `tools:` from ten of its eleven agents, letting each inherit the
full set. The exception is `acl-release-gatekeeper.md`, which declares
`tools: Read, Grep, Glob, Bash` — only stable, universally present names — because its
authority depends on being unable to edit anything.

Restrict tools only to *remove* a capability that would compromise the agent's role,
never to enumerate what it should have.

## Writing rules for ACL agents

1. **Name real files.** An agent that says "follow existing patterns" is useless. Say
   `app/Services/EntitlementService.php`.
2. **State the current truth, and mark it verifiable.** Where an agent describes the
   codebase, say so as an inspection result — ACL's own agents open with a "verify
   before relying on this" note where the fact will age.
3. **Name stale documents explicitly.** `docs/database/README.md` still describes
   SQLite; several `docs/*/README.md` files are empty. An agent that does not know this
   will cite them.
4. **The refusal list is the agent.** Anyone can list good practices. The value is in
   the specific things this agent will not do.
5. **No secrets, ever** — not a real key, not a plausible-looking fake one, not a
   password in an example. Use an obvious placeholder.
6. **No duplicate ownership.** Two agents claiming the same files breaks *one writer
   per file*. If ownership is unclear, that is a
   [governance](AGENT_GOVERNANCE.md) question, not a wording question.
7. **Keep it around 100 lines.** Every agent's text is a per-session context cost.

## Adding an agent

```bash
# 1. write it
$EDITOR .claude/agents/acl-your-agent.md

# 2. add its manifest row: file, category, loaded, upstream_path, adapted, sha256
#    category = ACL, upstream_path = -, adapted = n-a
$EDITOR .claude/agent-manifest.tsv

# 3. document it in the coverage matrix and the right domain file
$EDITOR docs/agent-system/AGENCY_AGENT_COVERAGE.md
$EDITOR docs/agent-system/agents/<domain>.md

# 4. route to it
$EDITOR docs/agent-system/AGENT_ROUTING.md

# 5. validate
bash scripts/agents/validate-agent-system.sh
```

All five steps belong in one commit. An agent nobody routes to will never be invoked;
a manifest row without a file, or a file without a row, fails validation.

## Changing an existing ACL agent

Read it first, change the smallest thing that fixes the problem, and update the
manifest hash by re-running the validation script. If the change alters who owns what,
update [`AGENT_GOVERNANCE.md`](AGENT_GOVERNANCE.md) in the same commit — a
responsibility table that disagrees with the agent definitions is worse than none.

## Activating or retiring an upstream agent

```bash
git mv .claude/agents-available/<agent>.md .claude/agents/<agent>.md   # activate
git mv .claude/agents/<agent>.md .claude/agents-available/<agent>.md   # retire
```

Then update the `category` and `loaded` columns in the manifest, move the row in the
coverage matrix, and say why in the commit message. Move, never copy — a file in both
directories is two agents with the same `name`, which is a collision the validation
script rejects.

## Anti-patterns

- Editing an upstream agent instead of writing an ACL one.
- Copying an upstream agent to `acl-*` and changing three lines. Write what ACL needs
  and reference the upstream agent for depth instead.
- An agent whose instructions restate the constitution. Link to it.
- An agent with no refusals.
- Adding an agent to feel thorough. 66 loaded definitions already cost context; the
  reserve exists so that restraint is free.
