# Serving ACL at app.aclacademy.me — Cloudflare Tunnel

*This document records the procedure for publishing the ACL Codespace at a public hostname through a Cloudflare Tunnel. The decision to serve this way — from the Codespace, with Render dropped — is [ADR-0009](../adr/0009-serve-from-codespace-via-cloudflare-tunnel.md); publishing the app and changing devcontainer provisioning are architectural changes under `CLAUDE.md` §6/§12, and this procedure is the authorised way to carry them out. Nothing here is wired up automatically — following it is the authorised step. All shell commands run **inside the Codespace terminal** unless a step says otherwise; the Windows laptop is only your editor and dashboard browser.*

> **The domain is publicly reachable, with no edge access gate.** An earlier draft of this guide put a Cloudflare Access email-OTP challenge in front of the app; that has been **removed at the maintainer's request**. The consequence: **ACL's own login is the only barrier between the internet and the app**, so before you expose the tunnel you must neutralise the seeded `admin@acl.local` / `password` administrator (see [Security notes](#security-notes)). Serving `php artisan serve` through a tunnel is a *development server made reachable*, not a production tier.

## How it fits together

A visitor reaches `https://app.aclacademy.me` at a Cloudflare edge server. TLS terminates **there** — Cloudflare presents the certificate for the hostname (its free Universal SSL). From the edge, the request rides a Cloudflare Tunnel: the `cloudflared` connector running inside your Codespace holds **outbound-only** connections to Cloudflare (port 7844), so the Codespace needs no public IP and no inbound ports opened. Inside the container, `cloudflared` forwards each request to the app over **plain HTTP on loopback** (`http://localhost:8000`, served by `php artisan serve`). The app never sees TLS and needs no certificate; it learns the original request was HTTPS only from the `X-Forwarded-Proto: https` header Cloudflare sets, which is why the trusted-proxy configuration in Part 4 matters.

```
        Your browser (Windows laptop)
             │
             │   HTTPS  (no edge access gate — ACL login is the only barrier)
             ▼
        Cloudflare edge  ─────────────  ◀── TLS TERMINATES HERE
             │
             │   encrypted tunnel; connector dials OUT (port 7844) — no inbound, no public IP
             ▼
        Cloudflare Tunnel
             │
             ▼
        cloudflared   (process inside the GitHub Codespace)
             │
             │   plain HTTP, loopback only
             ▼
        http://localhost:8000   (php artisan serve — the ACL app on 127.0.0.1)
```

The loopback leg is unencrypted **by design and is safe only because it is loopback**: nothing outside the container can reach `127.0.0.1:8000`, and the forwarded Codespace port stays private (see Security notes).

## Prerequisites

- The domain **`aclacademy.me`** registered at Namecheap, with access to change its nameservers.
- A free **Cloudflare** account (`https://dash.cloudflare.com`).
- The **ACL Codespace**, able to run `php artisan serve` on port `8000`.
- Permission to add a **GitHub Codespaces secret** at the repo or org level (Settings → Secrets and variables → Codespaces) — used in Part 3 for the tunnel token.
- Outbound network from the Codespace on TCP/UDP **7844** (Codespaces permits this; no inbound ports are needed).
- Order dependency: `aclacademy.me` must be **Active** on Cloudflare (Part 1) before the tunnel's routing step (Part 2) can offer it in the domain dropdown.

## Part 1 — Put aclacademy.me on Cloudflare

You are moving DNS authority for the domain to Cloudflare so it can terminate TLS and proxy the hostname.

**In Cloudflare** (`https://dash.cloudflare.com`, Free account):

1. Log in → top-level **Domains** → **Onboard a domain** (this button was formerly labelled "Add a Site").
2. Enter the apex domain exactly: `aclacademy.me` (no `www`, no subdomain).
3. **Select a plan → Free.** (Full DNS setup is the only mode on Free/Pro — that is what you want here.)
4. Cloudflare runs a DNS scan and shows a **Review DNS records** screen. For a brand-new domain that only needs `app`, there is usually nothing to keep — continue. Do **not** add the `app` record here; the tunnel creates it automatically in Part 2.
5. Cloudflare then shows **two assigned nameservers**, e.g. `xxxx.ns.cloudflare.com` and `yyyy.ns.cloudflare.com` (each a random first word, unique to your zone). **Copy the exact pair Cloudflare shows you.** If you lose them, they are always on the zone's **Overview** page.

**In Namecheap** (repoint the nameservers):

1. Log in → **Domain List** → **Manage** next to `aclacademy.me`.
2. On the **Domain** tab, scroll to the **NAMESERVERS** section.
3. Change the dropdown from **Namecheap BasicDNS** to **Custom DNS**.
4. Enter the **two Cloudflare nameservers** from step 5 above (one per field). Remove any pre-existing entries.
5. Click the **green checkmark ✓** to save.
6. If Namecheap shows **DNSSEC** enabled for this domain, **turn it off** first — changing nameservers while DNSSEC is active can make the domain unreachable. You can re-enable DNSSEC from Cloudflare later, after the domain is Active.

**Wait for "Active":** Cloudflare says to allow **up to 24 hours** for the registrar change to take effect (Namecheap's UI may quote up to 48; `.me` usually flips within minutes to a couple of hours). Cloudflare emails you when done, and the domain shows status **Active** on the **Domains** page (it reads "Pending Nameserver Update" until then).

Verify independently from any terminal:

```bash
dig NS aclacademy.me +short
```

This should return the two `*.ns.cloudflare.com` names.

## Part 2 — Create the tunnel and route app.aclacademy.me → localhost:8000

This uses a **remotely-managed (token-based)** tunnel: its whole configuration lives on Cloudflare and the connector needs only a token to run — no interactive `cloudflared tunnel login` browser step, which is the right fit for a headless Codespace.

### 2.1 Create the named tunnel and copy its token

1. Go to **Cloudflare One** (`https://one.dash.cloudflare.com`). On first use you are asked to pick a **team name** and choose the **Free** Zero Trust plan.
2. **Networks → Connectors → Cloudflare Tunnels** (older guides call this "Access → Tunnels" or "Zero Trust → Networks → Tunnels" — same feature, relocated). Being in the **Cloudflare Tunnel** connector section already selects the `cloudflared` connector.
3. Select **Create a tunnel**.
4. Enter a name (e.g. `acl-codespace`) → **Save tunnel** / **Create Tunnel**.
5. The next screen is **"Install and run a connector"**: choose **Debian/Ubuntu, 64-bit**. Cloudflare shows an install command that embeds the token — the token is the long **`eyJ...`** string. Per Cloudflare: *"Copy the cloudflared installation command into a text editor (do not run the command). The token is the `eyJ...` string."* **Copy that token now.**

Treat the token as a secret — anyone holding it can run your tunnel. You will store it as a Codespaces secret in Part 3. You can retrieve or rotate it later from the tunnel's **Configure** page.

### 2.2 Install cloudflared in the Codespace (for a first manual test)

This installs the binary into the **current** container so you can verify the path end-to-end now; Part 3 makes the install durable across rebuilds. Use the Cloudflare apt repository (gets updates via `apt`):

```bash
sudo mkdir -p --mode=0755 /usr/share/keyrings
```

```bash
curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg | sudo tee /usr/share/keyrings/cloudflare-main.gpg >/dev/null
```

```bash
echo "deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main" | sudo tee /etc/apt/sources.list.d/cloudflared.list
```

```bash
sudo apt-get update
```

```bash
sudo apt-get install cloudflared
```

Note: the repo line uses the component **`.../cloudflared any main`**. Older guides use `$(lsb_release -cs)` (e.g. `bookworm`, `jammy`); Cloudflare consolidated to the single **`any`** distribution, so use `any`.

*(Alternative — direct `.deb`, what the dashboard's default command uses):*

```bash
curl -L --output cloudflared.deb https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb
```

```bash
sudo dpkg -i cloudflared.deb
```

Verify either way:

```bash
cloudflared --version
```

### 2.3 Route the public hostname to the local app

Prerequisite: `aclacademy.me` is **Active** on Cloudflare (Part 1).

1. **Networks → Connectors → Cloudflare Tunnels** → select your tunnel (`acl-codespace`) → **Configure**, then open the **Routes** tab.
2. On the **Routes** tab, select **Add route → Published application**. (This is the renamed "Public Hostname → Add a public hostname" from older tutorials — the single biggest terminology change.)
3. Fill in:
   - **Subdomain:** `app`
   - **Domain:** select **`aclacademy.me`** from the dropdown.
   - **Path:** leave empty.
   - **Type / Service URL:** `HTTP`, host `localhost:8000` — i.e. the full **Service URL `http://localhost:8000`**.
4. Select **Add route** (Save).

Cloudflare **auto-creates the DNS record**: a proxied (orange-cloud) `CNAME` named `app` pointing at `<TUNNEL-UUID>.cfargotunnel.com`. You do not add this by hand — that is why the zone had to exist on Cloudflare first. TLS for `https://app.aclacademy.me` is terminated at Cloudflare's edge by Universal SSL automatically. (A single-level subdomain like `app` is covered by the free certificate; a multi-level host such as `x.app.aclacademy.me` would need an Advanced Certificate — not your case.)

### 2.4 Run the connector once and smoke-test

Make the token available to this terminal only. This `export` lives in the current shell and is **not** written to any file — Part 3 replaces it with a Codespaces secret so it survives restarts:

```bash
export CLOUDFLARE_TUNNEL_TOKEN='eyJ...paste-the-token-here...'
```

In a first terminal, start the app:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

In a second terminal, run the connector (the foreground `run --token` form is correct for a Codespace, which has no systemd for the dashboard's `service install` command):

```bash
cloudflared tunnel run --token "$CLOUDFLARE_TUNNEL_TOKEN"
```

The tunnel goes **HEALTHY** in the dashboard once connected. In a third terminal, confirm the full path answers:

```bash
curl -I https://app.aclacademy.me
```

Any HTTP response returned here (even an ACL error page — the app is not built or configured for HTTPS yet, that is Parts 4–5) proves the browser → edge → tunnel → app path works. A connection failure instead means the connector or the local `:8000` listener is down.

## Part 3 — Make the tunnel survive codespace restarts

In Codespaces the events differ: **stop/resume** pauses and resumes the *same* container — the writable filesystem (an installed binary) survives but running processes do not; **rebuild** builds a *new* container — only what `onCreateCommand`/`postCreateCommand` reinstall (or what is baked into the image) survives. This is the same split the repo already uses for Mailpit: install the binary where a rebuild still has it, and (re)start the daemon on every container start. The steps below mirror the existing `.devcontainer/` scripts exactly.

### 3.1 Store the token as a Codespaces secret

**Do this in GitHub, not in the repo.** Repo (or org) → **Settings → Secrets and variables → Codespaces** → add `CLOUDFLARE_TUNNEL_TOKEN` with the `eyJ...` value from Part 2.1. It arrives as an environment variable in the Codespace.

- Do **not** add it to `containerEnv` in `devcontainer.json`.
- Do **not** put it in a committed `.env`.
- Do **not** give it a default in `config.sh`.

The `if [ -z … ]` guard below is what keeps a token-less codespace booting cleanly.

### 3.2 Install cloudflared durably — `.devcontainer/install-services.sh`

Add this block right after the existing Mailpit block. It is the same shape and voice as that block: a single static binary fetched into `/usr/local/bin`, guarded by `command -v`, re-run on rebuild by `onCreateCommand`:

```bash
# ---------------------------------------------------------------------------
# cloudflared (Cloudflare Tunnel)
# ---------------------------------------------------------------------------
# Publishes the local dev app through a Cloudflare Tunnel. Not in apt, so --
# like Mailpit above -- it is a single static binary fetched from the official
# release into /usr/local/bin. Guarded by command -v so a rebuild that kept
# /usr/local/bin does not re-download it. The tunnel is *started* (only when a
# token is present) by start-services.sh, never here.
if ! command -v cloudflared >/dev/null 2>&1; then
  say "Installing cloudflared..."
  if command -v curl >/dev/null 2>&1; then
    arch="$(dpkg --print-architecture 2>/dev/null || echo amd64)"   # amd64 | arm64
    if curl -fsSL "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-${arch}" -o /tmp/cloudflared; then
      sudo install -m 0755 /tmp/cloudflared /usr/local/bin/cloudflared
      rm -f /tmp/cloudflared
    else
      warn "cloudflared download failed; Cloudflare Tunnel will be unavailable until it is installed."
    fi
  else
    warn "curl not found; cannot install cloudflared. Cloudflare Tunnel will be unavailable."
  fi
else
  say "cloudflared already installed ($(cloudflared --version 2>/dev/null | head -n1))."
fi
```

`dpkg --print-architecture` returns `amd64`/`arm64`, which are exactly the suffixes on cloudflared's release assets, so this is correct on both x86 and ARM codespaces.

*Alternative — bake it into the image layer instead.* Put this after the existing `apt-get install` that already provides `curl`/`ca-certificates` (around line 42 of `.devcontainer/Dockerfile`; hardcode `amd64`, since Codespaces is x86_64). The `install-services.sh` placement is recommended as primary because it is the precedent Mailpit set:

```dockerfile
RUN curl -fsSL https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 -o /usr/local/bin/cloudflared && chmod +x /usr/local/bin/cloudflared
```

### 3.3 Start the daemon on every container start — `.devcontainer/start-services.sh`

Add `ensure_cloudflared` after `ensure_mailpit` (the `}` closing that function is line 135). The name follows the file's existing `ensure_*` style; readiness comes from cloudflared's own metrics `/ready` endpoint (there is no inbound listener to probe), and the double-start guard is a `pgrep` since there is no port to check:

```bash
# --- Cloudflare Tunnel ----------------------------------------------------
# Publishes the local app at a stable public hostname across codespace
# stop/resume and rebuild. The token is a SECRET, read from the
# CLOUDFLARE_TUNNEL_TOKEN environment variable -- set as a GitHub Codespaces
# secret (Settings > Secrets and variables > Codespaces), NEVER committed and
# NEVER written to .env. When the variable is absent the tunnel is skipped, so
# a codespace without the secret still comes up cleanly.
#
# Unlike the services above, cloudflared has no inbound listener to probe: it
# makes outbound connections to Cloudflare's edge. So readiness comes from its
# own metrics server -- `/ready` returns 200 once at least one edge connection
# is up -- bound to loopback only, and the "don't start twice" guard is a
# pgrep on the process rather than a port check.
ACL_TUNNEL_METRICS_ADDR="127.0.0.1:${ACL_TUNNEL_METRICS_PORT:-60123}"
tunnel_ready() { curl -sf "http://${ACL_TUNNEL_METRICS_ADDR}/ready" >/dev/null 2>&1; }

ensure_cloudflared() {
  if [ -z "${CLOUDFLARE_TUNNEL_TOKEN:-}" ]; then
    say "No CLOUDFLARE_TUNNEL_TOKEN in the environment; skipping Cloudflare Tunnel."
    return 0
  fi
  command -v cloudflared >/dev/null 2>&1 \
    || { warn "cloudflared not installed; Cloudflare Tunnel unavailable. Run install-services.sh."; return 1; }
  if pgrep -x cloudflared >/dev/null 2>&1; then say "Cloudflare Tunnel already running."; return 0; fi

  say "Starting Cloudflare Tunnel..."
  # --metrics goes on the `tunnel` command, before `run`. --no-autoupdate: the
  # binary is managed by install-services.sh, not by cloudflared rewriting
  # itself under us. The token is passed on argv as cloudflared documents;
  # nothing here echoes it and it never touches disk.
  nohup cloudflared tunnel --no-autoupdate --metrics "$ACL_TUNNEL_METRICS_ADDR" \
    run --token "$CLOUDFLARE_TUNNEL_TOKEN" \
    >>"${ACL_STATE_DIR}/cloudflared.log" 2>&1 &
  disown 2>/dev/null || true

  for _ in $(seq 1 30); do
    if tunnel_ready; then say "Cloudflare Tunnel is up (edge connections established)."; return 0; fi
    sleep 1
  done
  tail -n 20 "${ACL_STATE_DIR}/cloudflared.log" 2>/dev/null || true
  warn "Cloudflare Tunnel did not report ready within 30s (log tail above)."
  return 1
}
```

Then extend the "Bring everything up" block at the bottom (lines 142–146) so a tunnel failure is reported but never breaks the container — matching the existing rule that only MariaDB's failure sets the exit code:

```bash
rc=0
ensure_mariadb     || rc=1
ensure_redis       || true
ensure_mailpit     || true
ensure_cloudflared || true
exit "$rc"
```

This reuses the file's `say`/`warn` helpers, its `${ACL_STATE_DIR}` log location, the same `nohup … & ; disown` detach `ensure_mailpit` uses, and `${CLOUDFLARE_TUNNEL_TOKEN:-}` is `set -u`-safe.

*Optional single-source note:* for full fidelity with the repo's "`config.sh` is the single source of truth for fixed values" rule, `ACL_TUNNEL_METRICS_PORT` ideally belongs in `.devcontainer/config.sh` alongside `ACL_APP_PORT`/`ACL_MAIL_UI_PORT`. It is loopback-only and internal, so it does **not** need adding to `forwardPorts`. Left inline with a `:-60123` default above to stay self-contained; promote it if you want the single-source property.

### 3.4 Apply it to the current container

`onCreateCommand` re-runs the install only on create/rebuild, so install into the container you are in now, then bring services up:

```bash
bash .devcontainer/install-services.sh
```

```bash
bash .devcontainer/start-services.sh
```

### 3.5 Honest caveat — nothing auto-starts the app itself

The tunnel connects to the edge whether or not anything listens on `:8000`; until the app answers, the public URL returns **502**. In this repo nothing starts the app automatically — `.devcontainer/post-create.sh` only *prints* `php artisan serve …` as an instruction. So after a resume the tunnel returns but the app is down until you run `php artisan serve` by hand.

If you want the whole path to self-heal, add a minimal guarded server (optional — bare `serve`, not `composer run dev`, which is an interactive foreground command). Do **not** block `ensure_cloudflared` on the app; they are independent:

```bash
ensure_app_server() {
  command -v php >/dev/null 2>&1 || return 0
  curl -sf "http://127.0.0.1:${ACL_APP_PORT}" >/dev/null 2>&1 && { say "App already listening on :${ACL_APP_PORT}."; return 0; }
  ( cd "$ACL_REPO_ROOT" && nohup php artisan serve --host=0.0.0.0 --port="${ACL_APP_PORT}" >>"${ACL_STATE_DIR}/artisan-serve.log" 2>&1 & disown 2>/dev/null || true )
  say "Started php artisan serve on :${ACL_APP_PORT}."
}
```

## Part 4 — ACL app configuration for HTTPS

**Headline: no application code change is required — only `.env` changes.** Trusted proxies is already wired, and the one code path that could interfere goes dormant the moment `APP_ENV=production`. Four `.env` keys turn the loopback dev server into one that is safe to reach over HTTPS through the tunnel; the rest of the settings below are already correct in the repo (ADR-0009).

### `.env` keys

Current values in the repo: `APP_ENV=local` (`.env:2`), `APP_DEBUG=true` (`.env:30`), `APP_URL=http://localhost:8000` (`.env:31`).

| Key | Value | Why |
|---|---|---|
| `APP_ENV` | `production` | Public URL serving real users. Also **disables** the local-only URL rewrite in `AppServiceProvider.php:22` (see code section) so URL generation flows cleanly from the proxy headers + `APP_URL`. Edit `.env:2`. |
| `APP_DEBUG` | `false` | `true` on a public URL renders stack traces, env values and SQL to any visitor — a security defect. Edit `.env:30`. |
| `APP_URL` | `https://app.aclacademy.me` | Root URL for console/queue-generated links and the fallback when no request context exists; must be the public `https://` host or every email/redirect points at `localhost`. Also feeds `Storage::url()` via `config/filesystems.php:44`. Edit `.env:31`. |
| `SESSION_SECURE_COOKIE` | `true` | `config/session.php:172` reads this with **no default** (null = Secure flag not forced). The browser↔Cloudflare leg is always HTTPS, so the session cookie must never be sendable over plain HTTP. **Add this line** (absent today). Caveat: with this `true`, hitting the origin directly over plain `http://localhost:8000` will not set the cookie → login/419 loop; only reach the app through the tunnel. |
| `SESSION_DOMAIN` | `null` (leave) | Already `null` at `.env:51`. Host-only cookie for the single host `app.aclacademy.me` is correct. Set `.aclacademy.me` only to share the cookie across subdomains — not needed here. **Already handled.** |
| `SESSION_SAME_SITE` | `lax` (leave, default) | Default at `config/session.php:202`. Same-site, same-origin form-post app (no cross-site POST), so `lax` works and is safer than `none`. **Already handled** — no key needed. |
| `ASSET_URL` | (leave unset) | Built Vite assets are served same-origin from `/build/...` through the tunnel; `@vite` builds URLs from `asset()`, which inherits the (now `https`) root. Set only if offloading `/build` to a separate CDN host — not the case. **Already handled.** |
| `SESSION_DRIVER` | `database` (leave) | Already `.env:47`; no HTTPS impact. **Already handled.** |
| `APP_KEY` | (already set) | Required for session/CSRF encryption; present and non-empty. Empty would 500 every request. **Already handled.** |

The four lines to change, in final form:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.aclacademy.me
SESSION_SECURE_COOKIE=true
```

Operational note: if `php artisan config:cache` was ever run with the old values, the cached config freezes them. After editing `.env`, clear it (or re-cache — Part 5):

```bash
php artisan config:clear
```

### Code edits — none required

Two relevant code paths, both already correct:

**1. Trusted proxies — already handled**, `bootstrap/app.php:17`:

```php
$middleware->trustProxies(at: '*');
```

With no explicit `headers` argument this trusts Laravel's default forwarded-header set, including `X-Forwarded-Proto`/`-For`/`-Host`/`-Port`. So cloudflared's `X-Forwarded-Proto: https` makes `request()->isSecure()` true → `url()`/`route()`/`asset()`/`@vite`/redirects all emit `https://`, and the Secure cookie is honored. **Trusting `'*'` is acceptable here only because the origin is reachable exclusively via the tunnel on loopback (`127.0.0.1:8000`)** — keep the Codespace's port 8000 *private* so nothing but cloudflared can inject forwarding headers. This is exactly the condition ADR-0009 records — trusting all proxies is safe only when the origin is reachable solely through a proxy that overwrites the forwarding headers; here that is the loopback-only tunnel, and Cloudflare sets these headers itself.

**2. The one path to understand — `app/Providers/AppServiceProvider.php:20-33`**, `configureLocalDevelopmentUrls()`:

```php
private function configureLocalDevelopmentUrls(): void
{
    if (! app()->environment('local') || app()->runningInConsole()) {
        return;                                   // lines 22–23 — production early-returns here
    }

    $host = request()->getHttpHost();

    $scheme = request()->header('x-forwarded-proto')
        ?? (str_ends_with($host, '.app.github.dev') ? 'https' : 'http');

    URL::forceRootUrl($scheme.'://'.$host);       // line 32
}
```

With `APP_ENV=production` this method **returns at line 22 and never calls `forceRootUrl`** — no conflict, URLs come from the proxy headers + `APP_URL`. That is why setting `APP_ENV=production` is load-bearing beyond debug hygiene. (Leaving `APP_ENV=local` would *coincidentally* still produce `https://app.aclacademy.me` because cloudflared passes the original `Host` through and sets `x-forwarded-proto: https` — but it is fragile and would drag `APP_DEBUG=true` along with it. Do not rely on it; set production.)

**No `TrustHosts` middleware is enabled** (none in `bootstrap/`, `app/`, `config/`), so host validation will not reject the new domain — nothing to add.

### Build step (required, not `.env`)

Layouts reference assets via `@vite([...])` (`resources/views/layouts/app.blade.php:11`, `resources/views/layouts/auth.blade.php:11`).

- **`npm run build` is the correct choice.** It compiles to `public/build/` + `manifest.json`, and `@vite` emits tags on the app's own origin (`https://app.aclacademy.me/build/...`), served through the tunnel — no dev-server dependency, no mixed content. `public/build/` does **not** exist yet, so without the build the page 500s with "Vite manifest not found." The `npm run build` command is in the Part 5 go-live sequence.
- **Do not use `npm run dev` for this shared URL.** It writes `public/hot` and points the browser at the Vite dev-server origin (a Codespace-private `…-5173.…` host) that an external visitor cannot reach. `public/hot` does not exist today (good); if anyone ever runs `npm run dev`, delete a leftover `public/hot` or `@vite` keeps serving from the unreachable dev server even after building.

### 419 / mixed-content notes when the scheme flips to https

- **Mixed content** is prevented by the trusted proxy + `APP_URL=https://…`: every generated URL (asset, form `action`, redirect) is `https://`.
- **419 causes to preempt:** (a) `SESSION_SECURE_COOKIE=true` while reaching the origin over plain HTTP — only use the https tunnel; (b) `APP_URL` not matching the browser host — set it exactly to `https://app.aclacademy.me`; (c) **do not add a Cloudflare "Cache Everything" rule over HTML** — Cloudflare's defaults already bypass cache when cookies are present, and a cached CSRF token would 419 every visitor; (d) empty `APP_KEY` — verified present, so fine.

## Part 5 — Go-live checklist

Run in order, inside the Codespace unless noted.

1. **Confirm the `.env` values** from Part 4 are set: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://app.aclacademy.me`, `SESSION_SECURE_COOKIE=true`. `APP_DEBUG=false` is the non-negotiable one — it stops stack traces, env values and SQL leaking to visitors.
2. **Build the front-end assets:**

```bash
npm run build
```

3. **Cache the production config** (also freezes the new `.env` values into the cached config):

```bash
php artisan config:cache
```

4. **Ensure the app is listening and the tunnel is up.** With Part 3 wired, `start-services.sh` starts the tunnel on container start; start the app if it is not already running:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

   (If you did not wire auto-start in Part 3, also run `cloudflared tunnel run --token "$CLOUDFLARE_TUNNEL_TOKEN"`.) Confirm the tunnel shows **HEALTHY** in Cloudflare One.
5. **Neutralise the seeded administrator — do this before the URL is shared with anyone.** If `DevelopmentSeeder` has run, `admin@acl.local` / `password` exists and `AppServiceProvider`'s `Gate::before` makes it omnipotent; with no edge gate that is an open admin door (see the security notes). Change its password, or do not run `DevelopmentSeeder` on the exposed instance. See "Bootstrapping a real administrator" in [`README.md`](README.md).
6. **Load the site** in a browser: `https://app.aclacademy.me`. There is no edge challenge — the app loads straight to ACL's own login.
7. **Confirm ACL's own login:** you reach ACL's `/login` and sign in with a real ACL account. ACL's authentication, scoped RBAC and institutional entitlement are the only barrier — there is no gate in front of them.
8. **Confirm the session cookie is Secure:** in DevTools → Application → Cookies → `app.aclacademy.me`, the Laravel session cookie shows the **Secure** flag (and `HttpOnly`, `SameSite=Lax`).

## Security notes

- **The tunnel token — and any Cloudflare API token — are secrets.** Store `CLOUDFLARE_TUNNEL_TOKEN` only as a GitHub Codespaces secret (Settings → Secrets and variables → Codespaces). Never commit it, never put it in a tracked `.env`, never give it a default in `config.sh`. Anyone holding the token can run your tunnel. This mirrors how the repo already treats `ACL_TIDB_*`: the contract is the variable name; the value lives only in the environment.
- **Process-args caveat:** `--token "$…"` puts the token in `ps` / `/proc/<pid>/cmdline` inside the container (never on disk, never in the log). To keep it out of `ps` entirely, cloudflared also accepts the token via the `TUNNEL_TOKEN` environment variable (`export TUNNEL_TOKEN="$CLOUDFLARE_TUNNEL_TOKEN"` and drop `--token`) — worth verifying against your cloudflared version; the `--token` form is the documented, example-confirmed one.
- **Trusting all proxies (`trustProxies(at: '*')`) is safe only because the origin is loopback-only behind the tunnel.** Keep the Codespace's port `8000` **private** (do not "make public" the forwarded port), so nothing but `cloudflared` can inject `X-Forwarded-*` headers; Cloudflare overwrites them itself. Map the tunnel route to **only** `app → 127.0.0.1:8000` — never Mailpit (`:8025`), Vite (`:5173`), or the database.
- **`SESSION_SECURE_COOKIE=true` means the origin must be reached only via the HTTPS tunnel.** Hitting `http://localhost:8000` directly will not set the cookie and produces a login/419 loop.
- **There is no edge access gate — ACL's own login is the only barrier.** The domain is publicly reachable ([ADR-0009](../adr/0009-serve-from-codespace-via-cloudflare-tunnel.md) removed the Cloudflare Access OTP gate at the maintainer's request), so ACL's authentication, scoped RBAC and institutional entitlement are the sole authority for who gets in and what they may do. **Before exposing the URL, neutralise the seeded administrator:** `DevelopmentSeeder` creates `admin@acl.local` / `password`, and `AppServiceProvider`'s `Gate::before` makes a platform administrator omnipotent — on a public URL with no edge gate, that well-known default is an open admin door. Change its password, or do not run `DevelopmentSeeder` on the exposed instance.
- **`APP_DEBUG=false` before exposing**, so stack traces, env values and SQL never render to a visitor. Do not add a Cloudflare "Cache Everything" rule over HTML, or a cached CSRF token will 419 every visitor.
- **Governance.** Under `CLAUDE.md` §6/§12, publishing the app and changing devcontainer provisioning are architectural changes needing authorisation in-conversation and an ADR; that decision is **[ADR-0009](../adr/0009-serve-from-codespace-via-cloudflare-tunnel.md)**, accepted. §8 forbids secrets in the repo. This document is the procedure ADR-0009 points to — applying it is the authorised step.
