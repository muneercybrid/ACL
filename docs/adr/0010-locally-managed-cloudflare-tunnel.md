# ADR-0010: Locally-managed Cloudflare Tunnel with a credentials-file secret

## Status

Accepted. **Amends [ADR-0009](0009-serve-from-codespace-via-cloudflare-tunnel.md)
point 6** (and the "No secret enters the repository" line of its Security
Implications): the tunnel is run as a **locally-managed** tunnel authenticated by
a credentials file, not a **remotely-managed** tunnel authenticated by a token.
Every other decision in ADR-0009 — serving from the Codespace via a Cloudflare
Tunnel at `app.aclacademy.me`, dropping Render, retaining the container image, and
running no separate production tier — stands unchanged. ADR-0009 is not superseded.
Date: 2026-09-05

## Context

ADR-0009 point 6 recorded the tunnel's secret as a `CLOUDFLARE_TUNNEL_TOKEN`
Codespaces secret. A token authenticates a **remotely-managed** tunnel, whose
configuration (ingress, routes) lives in the Cloudflare dashboard. Obtaining that
token requires creating the tunnel in the **Cloudflare Zero Trust** dashboard, and
Zero Trust onboarding prompts for a **payment method** before it will complete —
even on the free plan, and even though the tunnel itself is free. The maintainer
cannot clear that card wall right now, so the token flow is not usable.

Cloudflare offers a second, equivalent model: a **locally-managed** tunnel.
`cloudflared tunnel login` authenticates against the **ordinary** Cloudflare
dashboard (no Zero Trust, no card), `cloudflared tunnel create` writes a
credentials file, and the connector runs from a local `config.yml` that names that
file. This reaches the same edge, the same free TLS, and the same outbound-only
connector as the token model — without Zero Trust onboarding.

## Problem

How does ACL run the tunnel decided in ADR-0009 without passing the Cloudflare
Zero Trust payment wall the token model requires, while keeping ADR-0009's rule
that no secret enters the repository?

## Options Considered

1. **Keep the token model (ADR-0009 as written).** Blocked: acquiring the token
   requires Zero Trust onboarding, which demands a payment method the maintainer
   cannot supply now. Rejected as currently unusable.
2. **Locally-managed tunnel with a credentials-file secret.** No Zero Trust, no
   card; the run-time credential is the `<UUID>.json` file `cloudflared tunnel
   create` produces. Stored as the base64 Codespaces secret
   `CLOUDFLARE_TUNNEL_CREDENTIALS_B64` and reconstructed on each container start.
   Chosen.
3. **A Cloudflare Quick Tunnel** (`trycloudflare.com`). Card-free, but yields a
   random hostname per run and cannot serve a custom domain. Wrong for a stable
   `app.aclacademy.me`. Rejected.

## Decision

1. The tunnel is **locally-managed**. It is created once with `cloudflared tunnel
   login` → `cloudflared tunnel create acl` → `cloudflared tunnel route dns acl
   app.aclacademy.me`, and run from `~/.cloudflared/config.yml`.
2. The run-time secret is the **base64 of the `~/.cloudflared/<UUID>.json`
   credentials file**, supplied only as the `CLOUDFLARE_TUNNEL_CREDENTIALS_B64`
   GitHub Codespaces secret. **`CLOUDFLARE_TUNNEL_TOKEN` is not used** and its name
   from ADR-0009 point 6 is retired.
3. `.devcontainer/start-services.sh` decodes that secret on every start, writes the
   credentials file and `config.yml` (both mode 600) inside the container's private
   home, and starts `cloudflared … tunnel run`. When the secret is absent the
   tunnel is skipped, so a credential-less codespace still boots.
   `.devcontainer/install-services.sh` installs the binary; `.devcontainer/config.sh`
   holds the fixed `ACL_TUNNEL_HOSTNAME` and the loopback metrics port.
4. `cert.pem`, written by `cloudflared tunnel login`, is a **management-only**
   credential (create/route/delete). It is never committed and is not needed to
   *run* the tunnel.
5. **Nothing secret enters the repository** — ADR-0009's principle is unchanged;
   only the secret's name and shape change. The procedure is
   [`docs/deployment/DOMAIN_SETUP.md`](../deployment/DOMAIN_SETUP.md).

## Reasoning

- **Unblocks the decision already made.** ADR-0009's serving model is sound; only
  its secret mechanism was blocked by an unrelated paywall. This amendment changes
  the minimum needed to make it run.
- **Same security posture, different secret shape.** Both models keep the secret
  out of the repo and in a Codespaces secret. The credentials file is reconstructed
  to a 600 file in the throwaway container rather than passed as a token on
  `--token`, so the secret never lands on the process argv (`ps`).
- **No new infrastructure or dependency.** Same `cloudflared` binary, same edge,
  same free TLS. This is a configuration choice within ADR-0009, not a new platform.

## Consequences

**Good**

- The tunnel can be created and run with no payment method and no Zero Trust
  onboarding.
- Ingress can never drift: `start-services.sh` regenerates `config.yml` from
  `config.sh` on every start.

**Bad**

- The run-time credential exists as an on-disk file inside the container (mode 600)
  for the connector to read, rather than living only in process memory from a token.
- Tunnel configuration lives in a local `config.yml`, not the dashboard, so there
  is no dashboard view of the ingress; the file is the source of truth.

**Neutral**

- The tunnel UUID and `cert.pem` are additional local artifacts; the UUID is not
  secret, and `cert.pem` is management-only.

## Security Implications

- **`CLOUDFLARE_TUNNEL_CREDENTIALS_B64` is a secret** and lives only in the
  Codespaces secret store — never committed, never in a tracked `.env`, no default
  in `config.sh`. This replaces ADR-0009's `CLOUDFLARE_TUNNEL_TOKEN` line; the "no
  secret in the repository" rule is unchanged.
- **`cert.pem` is a separate, management-only secret.** It authorises
  creating/routing/deleting tunnels and DNS for the zone; it is not required to run
  the tunnel and is likewise never committed.
- Every other security implication of ADR-0009 — no edge access gate, the seeded
  administrator open door, and `trustProxies(at: '*')` being safe only behind the
  loopback-only tunnel — is inherited unchanged.

## Future Considerations

- If the payment wall is later cleared, the token model becomes available again,
  but there is no reason to switch back: the locally-managed model already serves
  the domain. A future move to a real production tier (ADR-0009's retained image
  path) would decide its own tunnel or ingress mechanism then.
