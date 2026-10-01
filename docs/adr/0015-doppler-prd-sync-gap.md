# ADR-0015: Doppler prd was missing every application secret added after the initial sync

## Status

Accepted. Operational, not architectural — it records a gap in the delivery
channel ADR-0012 established, and closes it.

Date: 2026-10-04

## Context

ADR-0012 made Doppler the source of truth for ACL's secrets, under project
`acl` with a `dev` config for the Codespace runtime and a `prd` config for the
production tier.

While auditing the repository after the leaked `.env` backups were purged from
history, the Doppler inventory was compared against the running production
`.env`. Two gaps, both real:

1. **`dev` was empty.** It held only Doppler's own `DOPPLER_*` metadata. The
   Codespace runtime had never been populated, so ADR-0012's decision that
   "Doppler populates the environment" was true of `prd` and aspirational for
   `dev`.
2. **`prd` was missing every secret added since its initial sync** — 20 of them.
   These were the AI provider keys (`ACLI_AI_API_KEY`, `OMNIROUTE_API_KEY` and
   the model/route settings), the error-reporting DSN and sample rates, the
   Google OAuth client, the Zyte scraping key, the JAMB matriculation URL, and
   the Redis connection settings. `prd` held only the original Laravel skeleton
   plus the database credentials.

The failure mode is quiet. The application did not break, because the production
box still serves from a local `.env` rendered from the older Doppler snapshot.
The two sources had simply diverged, and nothing compared them.

## Problem

A secrets source of truth that is only partly synchronised is worse than no
source of truth, because it is believed to be authoritative. A secret added
locally and never pushed is invisible to the platform: it is not in the audit
log, not covered by the per-config access tokens, and not removed when someone
off-boards that credential.

## Decision

1. **The 20 missing secrets are pushed to `prd`.** Values are passed to the
   CLI on stdin, one secret per invocation, never as command-line arguments, so
   they do not enter the process list or shell history.
2. **`REDIS_PASSWORD` is not pushed.** It is empty in the running `.env` —
   deliberately, since the development Redis is loopback-only and
   deliberately public per ADR-0008. Pushing an empty value would write a
   misleading empty secret into the production config.
3. **`dev` is left empty and is reported as a known gap**, not silently filled.
   The local `.env` on this box is a *production* environment
   (`APP_ENV=production`, `APP_URL=https://app.aclacademy.me`, TiDB Cloud
   production host), so it is not a source for a `dev` config. Populating `dev`
   requires a Codespace runtime, which is not present on this machine.
4. **`ACLI_AI_MODEL` was corrected from `auto` to a real model slug.** The value
   `auto` is an omniroute route name. When the base URL was moved to OpenRouter,
   the model name was not moved with it, and OpenRouter does not offer a model
   called `auto` — it is absent from the 462-model catalogue. Every ACLi request
   was therefore making one guaranteed-failing primary call and succeeding on
   the first fallback. The failure was invisible because the fallback masked it.
5. **Rotation is still owed.** Purging the leaked `.env` backups from history
   removed them from the repository; it did not un-leak them. The leaked `APP_KEY`
   and database passwords have already been rotated — the values in use today
   differ from the committed ones — so no further action is required for those
   specific credentials. This is checked, not assumed.

## Consequences

- `prd` now holds 63 secrets and is a complete enough source of truth for the
  production tier.
- A drift check belongs in the release step. A secret that can be added locally
  and forgotten will be forgotten again; comparing the running environment
  against the Doppler config is the only thing that catches it. This is not
  implemented here — it is the natural next piece of work.
- `dev` remains unpopulated, so ADR-0012's Codespace path is still not
  operational in practice.

## Alternatives considered

- **Populate `dev` from the production `.env`.** Rejected. It would put
  production credentials into the lower-privilege config, defeating the
  per-config scoping ADR-0012 requires.
- **Write the secrets into `.env.example`.** Rejected; that is the value-less
  template, and the reason these files existed is that real values were
  committed in the first place.
- **Leave `prd` as it was and only note the gap.** Rejected. The drift is the
  defect, and it is cheap to close now.
