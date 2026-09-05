# ADR-0012: Doppler as the secrets source of truth

## Status

Accepted.

- **Amends [ADR-0007](0007-tidb-cloud-dev-runtime.md)** (secret delivery only) —
  the `ACL_TIDB_*` values, delivered today as GitHub Codespaces secrets, now have
  Doppler as their source of truth. TiDB Cloud as the development runtime is
  unchanged and **not** superseded.
- **Amends [ADR-0011](0011-ec2-production-tier-and-remote-tunnel.md)** (secret
  delivery only) — the production TiDB credential and the application secrets held
  in the EC2 environment are now sourced from Doppler. The EC2 tier, the
  remotely-managed tunnel and TiDB-in-production are unchanged and **not**
  superseded.
- **Relates to [ADR-0005](0005-adopt-mariadb.md) and
  [ADR-0008](0008-redis-in-development.md)** — their deliberately-public,
  container-only MariaDB and Redis development credentials are explicitly **out of
  scope** and stay in `.devcontainer/config.sh`; Doppler manages secrets, not
  public container-only values.
- **Reaffirms `CLAUDE.md` §8 / the Constitution's secrets rule** — "secrets come
  from the environment and are read in `config/`; `env()` at a call site is a
  defect; no secret enters the repository." Doppler sits **upstream** of the
  environment, so this ADR strengthens that rule rather than contradicting it.

Adopting Doppler is new external infrastructure and a devcontainer/host tooling
change, both of which `CLAUDE.md` §6 puts behind explicit authorisation and an
ADR. The user authorised it in the conversation that produced this ADR. This is
that authorisation and that ADR.

Date: 2026-09-05

## Context

ACL's secrets are scattered across four delivery channels with no single
inventory, no rotation workflow and no access log:

- the `ACL_TIDB_*` GitHub Codespaces secrets for the development runtime
  (ADR-0007);
- the production TiDB credential and application secrets in the EC2 process
  environment (ADR-0011);
- the `cloudflared` connector token in the tunnel's service configuration
  (ADR-0011); and
- hand-maintained `.env` files on each machine.

ADR-0011 stood up a real production tier holding live-data credentials and stated
that a leaked credential "must be rotated immediately" — but gave no managed place
to do that. As production secrets multiply on a hand-operated box, the absence of
a source of truth is an operational gap, not a stylistic one. What is **not** in
question is the rule that no secret enters the repository; only the source of
truth and the delivery *upstream* of the environment.

## Problem

How does ACL centralise its secrets into one auditable source of truth — with
rotation and per-environment scoping across the Codespace dev runtime and the EC2
production tier — without (a) putting any secret in the repository, (b) breaking
the offline fresh-clone path that depends on deliberately-public, container-only
credentials (ADR-0005, ADR-0007, ADR-0008), or (c) introducing `env()` outside
`config/` or otherwise contradicting the established secrets discipline?

## Options Considered

1. **Status quo — secrets scattered across Codespaces secrets, the EC2
   environment, the `cloudflared` config and hand-edited `.env` files.** No new
   dependency, but no inventory, rotation or audit surface; it degrades as
   production credentials multiply. Rejected.
2. **An encrypted secret committed to the repository** (`php artisan env:encrypt`,
   or `sops`/`age`). Keeps everything in Git, but an encrypted secret in the repo
   is the exact pattern "no secret enters the repository" exists to prevent: it
   moves the problem to key management and offers no rotation or audit. Rejected.
3. **A cloud KMS or HashiCorp Vault** (AWS Secrets Manager, Vault). Powerful, but
   Vault is another service to operate and Secrets Manager couples ACL to one
   cloud with IAM plumbing — too heavy for a single hand-operated box. Rejected as
   premature.
4. **Doppler — a managed secrets manager.** Free tier is sufficient; per-project,
   per-environment configs; a CLI that renders the environment at deploy time;
   dashboard rotation and an access log; first-class GitHub Codespaces
   integration; nothing in the repository. Chosen.

## Decision

1. **Doppler is the source of truth for ACL's application and runtime secrets**,
   under one project (`acl`) with two configs: **`dev`** for the Codespace runtime
   and **`prd`** for the EC2 production tier. The default `stg` config is unused.
   The test suite uses no secrets (`phpunit.xml` pins array/loopback drivers), so
   it needs no Doppler config.
2. **Doppler populates the environment; it does not change how the app reads it.**
   Secrets still arrive as environment variables and are read only in `config/`;
   `env()` outside `config/` remains a defect; `.env` stays git-ignored and
   `.env.example` stays the value-less template. **No secret enters the
   repository.**
3. **Delivery is per environment.** In production, the release step renders
   `~/ACL/.env` from Doppler's `prd` config
   (`doppler secrets download --no-file --format env`) *before*
   `php artisan config:cache`, so the compiled config cache — Laravel's runtime
   source once cached — is built from Doppler-sourced values; php-fpm is **not**
   wrapped in `doppler run`, so the box still boots and serves from local files if
   Doppler is briefly unreachable. In the Codespace, a `dev`-scoped token (stored
   as a GitHub Codespaces secret) lets the CLI render `.env` on container start;
   the ADR-0007 fallback to local MariaDB when no runtime secret is present is
   retained, so a fresh clone with no Doppler access still works offline.
4. **Least privilege by config.** The EC2 holds a **read-only service token scoped
   to `prd` only**; the Codespace holds a separate, lower-privilege token scoped to
   `dev` only (or a per-developer personal login). No machine holds a token wider
   than its own environment; a `dev` token cannot read `prd`.
5. **Deliberately-public, container-only credentials are out of scope** — the
   fixed MariaDB (ADR-0005) and Redis (ADR-0008) development passwords in
   `.devcontainer/config.sh` are not secrets (they guard loopback-only
   in-container services) and stay there, so the offline fresh-clone path is
   unaffected.
6. **The Cloudflare connector token stays where ADR-0011 placed it** — in the
   `cloudflared` service configuration on the EC2 — for now. Bringing it under
   Doppler for a single inventory is a recorded follow-up, not part of this
   adoption.
7. **Doppler is on the critical path of a production release, so it fails
   closed:** a failed secret fetch must not truncate `.env` or boot the app with
   blank credentials (the render step writes atomically to a temp file and moves
   it into place). A root-only, out-of-band `EnvironmentFile` is the documented
   break-glass if Doppler is unreachable at deploy time.

Implementation — installing the CLI in the devcontainer and on the EC2, minting
the tokens, and adding the render step to the manual deploy — is a manual,
documented step (there is still no CI, ADR-0011) carried out and verified
out-of-band in the Codespace and on the box. This ADR records the decision.

## Reasoning

- **It collapses four scattered stores into one inventory** with rotation and an
  access log, executing ADR-0011's rotate-on-leak requirement instead of leaving
  it as a manual scramble.
- **It is compatible by construction with the existing discipline.** Doppler feeds
  environment variables; the `config/`-reads-`env` pattern, the `.env` git-ignore
  and "no secret in the repository" are untouched. This is a source-and-delivery
  change, not a reversal — which is why it *amends* ADR-0007 and ADR-0011 rather
  than superseding them.
- **Render-`.env`-at-deploy fits Laravel's model** and avoids wrapping the
  long-lived php-fpm master (and cron, and queue workers) in `doppler run`: once
  `config:cache` has run, `env()` reads only real system environment anyway, so a
  build-time render is both simpler and enough. Doppler need only be reachable at
  deploy time, not on every request or reboot.
- **Boring beats clever.** A managed CLI that renders environment files is far less
  machinery than Vault or a KMS with IAM, and right-sized for one hand-operated
  box.
- **It preserves offline development** by leaving the container-only public values
  in the repository; Doppler is purely additive.

## Consequences

**Good**

- One auditable source of truth, with rotation and per-environment scoping;
  onboarding a machine becomes `doppler setup`, not a scavenger hunt across four
  stores.
- Production credential handling finally matches ADR-0011's stated rotate-on-leak
  requirement.
- The stale `ACL_TIDB_*` Codespaces secret (which still holds the pre-rotation dev
  password) gets a single place to be corrected.

**Bad**

- **A new third party is now on the production boot path and holds every secret** —
  its compromise is broad. Mitigated by fail-closed behaviour, a root-only
  break-glass `EnvironmentFile`, and per-environment token scoping.
- A new account to administer, and the `doppler` CLI is a new package in the
  devcontainer and on the EC2 (a tooling change).
- Still **no CI** (ADR-0011): the render step is one more thing a manual deploy
  must not forget; forgetting it serves a stale `.env`.

**Neutral**

- `.env` and `.env.example` are unchanged in spirit; `.env.example` may gain a
  comment naming Doppler as the source.
- Once Doppler delivers the `ACL_TIDB_*` values, the Codespaces secrets become a
  redundant delivery channel — a documentation reconciliation, not a conflict.## Security Implications

- **Doppler is a high-value single point.** Its service token is itself a secret,
  held only in the OS environment or a mode-0600 file per machine, never in the
  repository, scoped per environment, and rotated on leak.
- **Least privilege:** the EC2 token reads only `prd`; the Codespace token reads
  only `dev`. No machine holds a token wider than its environment.
- **No secret enters the repository** — reaffirmed. Doppler is strictly upstream of
  the environment; the `config/`-reads-`env` pattern is unchanged.
- **Fail closed.** A missing or failed secret fetch must never boot the app with
  blank credentials or truncate `.env`.
- **Container-only public values are explicitly not migrated**, so their intended
  publicness is never mistaken for a Doppler-managed secret.
- **Credentials exposed while setting this up must be treated as compromised now:**
  rotate the TiDB production password and reissue any exposed token, push the fresh
  values into Doppler only, and never paste new values back into a chat. Because
  development and production still share the one TiDB `acl` database, a password
  rotation must be coordinated across both the EC2 and the Codespace.

## Future Considerations

- **The Cloudflare connector token** may later move under Doppler for one inventory
  and one rotation path; until then it stays in the `cloudflared` service config
  (ADR-0011).
- **CI is still absent (ADR-0011).** When it arrives, its secrets come from a
  CI-scoped Doppler config, keeping the model uniform and giving the deploy gate a
  clean secret source.
- **Separate the shared TiDB database.** Doppler's per-config values are what let
  `dev` point `DB_DATABASE` at `acl_dev` while `prd` keeps `acl`, retiring the
  standing hazard that a development `migrate:fresh` could clobber live production
  data. Pair it with a least-privilege dev database user (no DDL), independent of
  this ADR.
- **If Doppler is ever dropped**, a superseding ADR restores per-environment
  environment delivery; the application is unaffected because it only ever read
  environment variables.
