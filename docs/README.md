# ACL Documentation

Authoritative structure per the ACL Official Engineering Standard (AES).

## Documentation Volumes
| Volume | Document | Status |
|---|---|---|
| -1 | Master Blueprint & Documentation Constitution | Established |
| 0 | Project Charter & Vision | Pending |
| 1 | Product Specification | Pending |
| 2 | Software Requirements Specification | Pending |
| 3 | System Architecture | Pending |
| 4 | Database Engineering | Pending |
| 5 | UI/UX Standards | Pending |
| 6 | Security Architecture | Pending |
| 7 | AI Architecture | Pending |
| 8 | API Engineering | Pending |
| 9 | Developer Handbook | In progress |
| 10 | AI Agent Constitution | Established — [`ACL_DEVELOPMENT_CONSTITUTION.md`](ACL_DEVELOPMENT_CONSTITUTION.md) and [`agent-system/`](agent-system/), expanding [`.ai/guidelines/ACL.md`](../.ai/guidelines/ACL.md) |
| 11 | Deployment & DevOps | Pending |
| 12 | Operations Manual | Pending |
| 13 | Business & Growth Manual | Pending |

Volume 10 has three parts, in order of authority:
[`ACL_DEVELOPMENT_CONSTITUTION.md`](ACL_DEVELOPMENT_CONSTITUTION.md) states the rules,
[`agent-system/`](agent-system/) describes the agents that enforce them, and
[`.ai/guidelines/ACL.md`](../.ai/guidelines/ACL.md) remains the short form that
assistant tooling loads.

## Supporting Directories
- architecture/ database/ security/ api/ ai/ ui-ux/ developer/ deployment/ operations/ business/ adr/ agent-system/

## Rules
1. Documentation overrides assumptions.
2. Architecture decisions are recorded as ADRs in adr/.
3. When implementation changes intended architecture, update docs in the SAME commit.
