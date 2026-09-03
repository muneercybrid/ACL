# ACL Agent System

ACL carries its own AI engineering organisation inside the repository. A fresh
`git clone`, a new GitHub Codespace, a reinstalled laptop or a different developer
all recover the same agents, the same routing rules and the same governance gates
with no setup step.

That is the point of this directory: the agent system is **project state**, not
machine state.

## How it fits together

```
CLAUDE.md                          entry point — read first
docs/ACL_DEVELOPMENT_CONSTITUTION.md   the rules agents enforce
docs/agent-system/                 how the agents are organised (this directory)
.claude/agents/                     66 definitions Claude Code loads automatically
.claude/agents-available/          175 definitions held in reserve, tracked in Git
.claude/agent-manifest.tsv         category + upstream provenance for all 241
.claude/agent-exclusions.txt       the upstream divisions ACL deliberately does not track
.claude/skills/                      7 skills that travel with the repo
scripts/agents/                    validation and upstream comparison
```

Claude Code discovers agents by scanning `.claude/agents/` only. `agents-available/`
is therefore a **reserve that costs nothing per session** while remaining versioned
and reviewable. Activating an agent is `git mv` in one direction; retiring it is the
same command in the other.

## The documents in this directory

| File | Answers |
|---|---|
| [`AGENT_GOVERNANCE.md`](AGENT_GOVERNANCE.md) | Who decides, who defers, which gates must pass, how conflicts end |
| [`AGENT_ROUTING.md`](AGENT_ROUTING.md) | Given this task, which agents, in what order |
| [`AGENCY_AGENT_COVERAGE.md`](AGENCY_AGENT_COVERAGE.md) | Every upstream agent, its category, and why |
| [`UPSTREAM_AGENCY_AGENTS.md`](UPSTREAM_AGENCY_AGENTS.md) | Where the agents came from, at which commit, what was changed |
| [`AGENT_CUSTOMIZATION.md`](AGENT_CUSTOMIZATION.md) | How to write or adapt an agent for ACL |
| [`AGENT_UPDATE_PROCESS.md`](AGENT_UPDATE_PROCESS.md) | How to take upstream changes without losing ACL's work |
| [`AGENT_SECURITY.md`](AGENT_SECURITY.md) | The security model of running agents against this repository |
| [`agents/`](agents/) | Per-domain detail: when each agent is invoked, what it reviews, what it produces |

These documents describe **how the system operates**. They deliberately do not
duplicate the agent definitions themselves — the definition in `.claude/agents/` is
the single source for an agent's instructions.

## The two populations

**11 ACL agents** (`.claude/agents/acl-*.md`) were written for this codebase. They
name real files, real conventions and real ADRs, and they outrank their generic
equivalents for any ACL work.

**230 Agency Agents** came from
[msitarzewski/agency-agents](https://github.com/msitarzewski/agency-agents),
copied byte-for-byte. 55 are loaded; 175 are in reserve. None were modified —
ACL's opinions live in the ACL agents and in these documents, so an upstream
update never collides with an ACL edit. See
[`UPSTREAM_AGENCY_AGENTS.md`](UPSTREAM_AGENCY_AGENTS.md).

## Categories

| Category | Count | Meaning |
|---|---|---|
| `ACL` | 11 | Written for ACL. Loaded |
| `CORE` | 13 | Upstream agents ACL's routing table names for ordinary work. Loaded |
| `SPECIALIST` | 42 | Upstream depth for a specific class of work when it arises. Loaded |
| `PERIODIC` | 47 | Credible future ACL use. In reserve; activate by moving one file |
| `NOT_REQUIRED` | 128 | No credible ACL use. Kept only so provenance stays complete |

## Skills

`.claude/skills/` holds seven skills — `banner-design`, `brand`, `design`,
`design-system`, `slides`, `ui-styling`, `ui-ux-pro-max` — tracked here for the same
reason the agents are: so a clone reproduces them. They are third-party work, authored
by `claudekit` (`metadata.author` in each `SKILL.md`); four declare `license: MIT`,
`brand` and `slides` declare a version but no license, and `ui-ux-pro-max` declares no
metadata at all. There is no upstream commit to pin, because they were vendored as
files rather than cloned.

Two facts about them worth knowing before invoking one:

- **They carry executable code.** 172 files, ~4.6 MB, including 42 Python, Node and
  shell scripts. An agent definition can only persuade; a skill script runs. Read a
  script before running it, exactly as you would any other vendored dependency.
- **Some scripts want credentials and network access.** They read `GEMINI_API_KEY` and
  `GOOGLE_API_KEY` from the environment and fetch from `images.pexels.com`,
  `fonts.googleapis.com` and `github.com`. No key is stored in the repository — the
  secret scan in `validate-agent-system.sh` covers `.claude/skills/` — and none of
  these skills is required for any ACL engineering task.

The design skills also predate ACL's own design language. Where they disagree with
[`docs/ui-ux/DESIGN_SYSTEM.md`](../ui-ux/DESIGN_SYSTEM.md) or ADR-0004, ACL wins.

## Using it

Most of the time, do the work. The system exists for changes that cross concerns.

- Unsure who owns a task → `ACL Orchestrator`, or read
  [`AGENT_ROUTING.md`](AGENT_ROUTING.md).
- "Is this ready?" → `ACL Release Gatekeeper`.
- Changing an agent, or adding one → [`AGENT_CUSTOMIZATION.md`](AGENT_CUSTOMIZATION.md).

Validate the system after any change to it:

```bash
bash scripts/agents/validate-agent-system.sh
```

Check whether upstream has moved:

```bash
bash scripts/agents/check-upstream-agents.sh
```

## What this system does not do

It does not run itself. Nothing here schedules an agent, watches the repository, or
acts without a human asking. It also does not replace review: an agent's report is
a claim, and `ACL Release Gatekeeper` exists because claims need evidence.
