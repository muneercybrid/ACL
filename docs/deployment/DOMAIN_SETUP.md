# Serving ACL at app.aclacademy.me — Cloudflare Tunnel

*This document records the procedure for publishing the ACL Codespace at a public hostname through a Cloudflare Tunnel. The decision to serve this way — from the Codespace, with Render dropped — is [ADR-0009](../adr/0009-serve-from-codespace-via-cloudflare-tunnel.md), with the locally-managed tunnel mechanism this guide uses pinned by [ADR-0010](../adr/0010-locally-managed-cloudflare-tunnel.md); publishing the app and changing devcontainer provisioning are architectural changes under `CLAUDE.md` §6/§12, and this procedure is the authorised way to carry them out. The devcontainer scripts start the tunnel automatically **only when its credentials secret is present** (Part 3); this guide is how you create the tunnel, supply that secret, and point DNS at it. All shell commands run **inside the Codespace terminal** unless a step says otherwise; the Windows laptop is only your editor and dashboard browser.*

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
- Permission to add a **GitHub Codespaces secret** at the repo or org level (Settings → Secrets and variables → Codespaces) — used in Part 3 for the tunnel **credentials**.
- Outbound network from the Codespace on TCP/UDP **7844** (Codespaces permits this; no inbound ports are needed).
- Order dependency: `aclacademy.me` must be **Active** on Cloudflare (Part 1) before the tunnel's routing step (Part 2, `cloudflared tunnel route dns`) can create the `app` record.

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

## Part 1.5 — Move the apex off the GitHub Pages site

Searching `aclacademy.me` still shows the GitHub Pages site because the domain was pointed at a GitHub repository (the custom-domain "user/organization site"). That pointing is **DNS**: four `A` records for the apex at GitHub's Pages addresses (`185.199.108.153`, `185.199.109.153`, `185.199.110.153`, `185.199.111.153`) and usually a `CNAME www → <user>.github.io`. When Cloudflare scanned the zone in Part 1 it most likely **imported those records**, so they are now yours to change **in Cloudflare, not in Namecheap** (Namecheap no longer serves this domain's DNS).

ACL is canonical at **`app.aclacademy.me`** (the tunnel). The simplest, recommended choice for the bare `aclacademy.me` and `www` is to **301-redirect both to `https://app.aclacademy.me`**.

**In the Cloudflare dashboard → your zone → DNS → Records:**

1. **Delete** the four Pages `A` records on `@` (`185.199.108–111.153`) and any `CNAME www → <user>.github.io`. This alone stops the GitHub site being served.
2. Add one **proxied** placeholder so the apex still resolves (the target is irrelevant — a Redirect Rule intercepts the request before it is forwarded):
   - `AAAA` `@` → `100::` — **Proxied** (orange cloud). (`100::` is the IPv6 discard prefix; a proxied `A @ 192.0.2.1` works equally well.)
   - `CNAME` `www` → `aclacademy.me` — **Proxied** (orange cloud).

**Then add the redirect — Cloudflare → your zone → Rules → Redirect Rules → Create rule:**

3. Match with a **custom filter expression**: `(http.host eq "aclacademy.me") or (http.host eq "www.aclacademy.me")`.
4. Then **Static redirect** → URL `https://app.aclacademy.me`, status **301**, **Preserve query string** on. **Deploy.**
5. Now `aclacademy.me` and `www` 301 to `https://app.aclacademy.me`, while `app.aclacademy.me` is served by the tunnel (Part 2). Browsers cache 301s hard — test in a private window and clear the cache if you had visited the Pages site.

Optional: to keep a real page on the apex instead of redirecting, leave the Pages `A` records (proxied) rather than deleting them in step 1 — but then ACL is not what the apex shows. The redirect above is recommended while `app.aclacademy.me` is the only thing to serve.

## Part 2 — Create the tunnel and route app.aclacademy.me → localhost:8000

This uses a **locally-managed** tunnel: you authenticate once with `cloudflared tunnel login` against the ordinary Cloudflare dashboard, create a named tunnel, and route the hostname — all from the CLI. Unlike a remotely-managed (token) tunnel it needs **no Cloudflare Zero Trust onboarding**, which is what avoids the "add a payment method" wall on the free plan. The trade-off: the connector is configured from a local `config.yml` + credentials file (Part 3 regenerates both on every start), not from the dashboard.

### 2.1 Install cloudflared in the Codespace

Part 3 makes this durable across rebuilds (it is already in `install-services.sh`); for a first manual run, fetch the single static binary into the current container:

```bash
sudo curl -fsSL "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-$(dpkg --print-architecture)" -o /usr/local/bin/cloudflared && sudo chmod 0755 /usr/local/bin/cloudflared
```

```bash
cloudflared --version
```

### 2.2 Authenticate once (browser — no card)

```bash
cloudflared tunnel login
```

This opens a Cloudflare URL; pick the `aclacademy.me` zone and authorise. It writes **`~/.cloudflared/cert.pem`** — the certificate that lets the CLI **manage** tunnels and DNS for that zone. It uses the ordinary dashboard, **not** Cloudflare Zero Trust, so no payment method is requested. `cert.pem` is needed only for the management commands below (create/route), never to *run* the tunnel.

### 2.3 Create the named tunnel

```bash
cloudflared tunnel create acl
```

This prints a **tunnel UUID** and writes `~/.cloudflared/<UUID>.json` — the run-time **credentials** (Part 3's secret): that file alone lets `cloudflared` run this tunnel, no `cert.pem` required. The UUID itself is not secret. List tunnels anytime with `cloudflared tunnel list`.

### 2.4 Route the public hostname to the tunnel

Prerequisite: `aclacademy.me` is **Active** on Cloudflare (Part 1).

```bash
cloudflared tunnel route dns acl app.aclacademy.me
```

This **auto-creates** the proxied (orange-cloud) `CNAME` `app` → `<UUID>.cfargotunnel.com` in the Cloudflare zone — the record the dashboard would otherwise add, done from the CLI. That is why the zone had to exist on Cloudflare first. TLS for `https://app.aclacademy.me` is terminated at Cloudflare's edge by Universal SSL automatically (a single-level subdomain like `app` is covered by the free certificate). Re-running is idempotent; it errors only if a conflicting record exists, which you clear in the Cloudflare **DNS** dashboard.

### 2.5 Write a config and smoke-test

Create `~/.cloudflared/config.yml` (Part 3 regenerates this on every container start — this is the manual first run), substituting the UUID from 2.3:

```yaml
tunnel: <UUID>
credentials-file: /home/vscode/.cloudflared/<UUID>.json
ingress:
  - hostname: app.aclacademy.me
    service: http://localhost:8000
  - service: http_status:404
```

In a first terminal, start the app:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

In a second terminal, run the connector (no token, no `cert.pem` needed — it reads the credentials file named in the config):

```bash
cloudflared tunnel --config ~/.cloudflared/config.yml run
```

In a third terminal, confirm the full path answers:

```bash
curl -I https://app.aclacademy.me
```

Any HTTP response here (even an ACL error page — the app is not built or configured for HTTPS yet, that is Parts 4–5) proves browser → edge → tunnel → app works. `cloudflared tunnel info acl` shows the active connector; a connection failure instead means the connector or the local `:8000` listener is down.

## Part 3 — Make the tunnel survive codespace restarts

In Codespaces the events differ: **stop/resume** pauses and resumes the *same* container — the writable filesystem (an installed binary) survives but running processes do not; **rebuild** builds a *new* container — only what `onCreateCommand`/`postCreateCommand` reinstall (or what is baked into the image) survives. This is the same split the repo already uses for Mailpit: install the binary where a rebuild still has it, and (re)start the daemon on every container start. The steps below mirror the existing `.devcontainer/` scripts exactly.

### 3.1 Store the tunnel credentials as a Codespaces secret

The run-time credentials are the `~/.cloudflared/<UUID>.json` file written in Part 2.3 (`<UUID>` is the tunnel ID `cloudflared tunnel create` printed; `cloudflared tunnel list` shows it again). Base64-encode that file as a single unwrapped line:

```bash
base64 -w0 ~/.cloudflared/<UUID>.json; echo
```

Copy the one-line output. **Do this in GitHub, not in the repo:** repo (or org) → **Settings → Secrets and variables → Codespaces** → **New repository secret** → name `CLOUDFLARE_TUNNEL_CREDENTIALS_B64`, value = that base64 string. It is injected as an environment variable on **every** codespace start (create *and* resume), which is what lets the tunnel come back after a stop/resume or a rebuild.

- Do **not** paste the base64 value into a chat, an issue, or a commit message.
- Do **not** add it to `containerEnv` in `devcontainer.json`, and do **not** put it in a committed `.env`.
- Do **not** give it a default in `config.sh` — the contract is the variable name only, exactly like `ACL_TIDB_*`.

The `[ -n "${CLOUDFLARE_TUNNEL_CREDENTIALS_B64:-}" ]` guard in `start-services.sh` keeps a credential-less codespace booting cleanly: the tunnel is simply skipped.

### 3.2 Install cloudflared durably — `.devcontainer/install-services.sh`

**Already in the repo.** `install-services.sh` (run by `onCreateCommand` on create/rebuild) fetches the single static `cloudflared` binary into `/usr/local/bin`, right after the Mailpit block and in the same shape: guarded by `command -v` so a rebuild that kept `/usr/local/bin` does not re-download it, and `warn`-ing rather than failing if the download is unavailable. `dpkg --print-architecture` selects `amd64`/`arm64`, which are exactly cloudflared's release-asset suffixes, so it is correct on both x86 and ARM codespaces. See the block in [`.devcontainer/install-services.sh`](../../.devcontainer/install-services.sh); the tunnel is only *installed* here — it is *started* (and only when the credentials secret is present) by `start-services.sh`, never here.

### 3.3 Reconstruct and start the tunnel on every container start — `.devcontainer/start-services.sh`

**Already in the repo.** `start-services.sh` (run by `postStartCommand` on every start, resume included) defines `ensure_cloudflared`, which:

1. **skips cleanly** when `CLOUDFLARE_TUNNEL_CREDENTIALS_B64` is absent, so a credential-less codespace still boots;
2. base64-decodes the secret, reads the `TunnelID` out of it, and writes the credentials back to `~/.cloudflared/<UUID>.json` (mode 600);
3. regenerates `~/.cloudflared/config.yml` (mode 600) from `config.sh`'s `ACL_TUNNEL_HOSTNAME` + metrics port on every start, so the ingress can never drift;
4. starts `cloudflared --config … tunnel run` with `nohup … & disown` (the same detach `ensure_mailpit` uses), guarded by `pgrep -x cloudflared` so it never double-starts;
5. reports readiness from cloudflared's own loopback `/ready` metric — there is no inbound listener to probe.

The fixed values live in [`.devcontainer/config.sh`](../../.devcontainer/config.sh) (`ACL_TUNNEL_HOSTNAME=app.aclacademy.me`, `ACL_TUNNEL_METRICS_PORT=60123`); the metrics port is loopback-only and deliberately **absent** from `forwardPorts`. The bring-up block runs `ensure_cloudflared || true`, so — matching the rule that only MariaDB's failure sets the exit code — a tunnel problem is reported but never breaks the container. Read the function in [`.devcontainer/start-services.sh`](../../.devcontainer/start-services.sh).

Nothing echoes the secret, and the decoded credentials never leave the container's private home. Unlike a `--token` on argv, the secret never appears on the process command line (`ps`); the trade is a 600 credentials file on the throwaway container's own disk.

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

4. **Ensure the app is listening and the tunnel is up.** With Part 3 in place, `start-services.sh` starts the tunnel on container start (when the credentials secret is set); start the app if it is not already running:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

   (If the tunnel is not already running, start it with `cloudflared tunnel --config ~/.cloudflared/config.yml run`.) Confirm it is connected with `cloudflared tunnel info acl`, or `curl -sf http://127.0.0.1:60123/ready` inside the container.
5. **Neutralise the seeded administrator — do this before the URL is shared with anyone.** If `DevelopmentSeeder` has run, `admin@acl.local` / `password` exists and `AppServiceProvider`'s `Gate::before` makes it omnipotent; with no edge gate that is an open admin door (see the security notes). Change its password, or do not run `DevelopmentSeeder` on the exposed instance. See "Bootstrapping a real administrator" in [`README.md`](README.md).
6. **Load the site** in a browser: `https://app.aclacademy.me`. There is no edge challenge — the app loads straight to ACL's own login.
7. **Confirm ACL's own login:** you reach ACL's `/login` and sign in with a real ACL account. ACL's authentication, scoped RBAC and institutional entitlement are the only barrier — there is no gate in front of them.
8. **Confirm the session cookie is Secure:** in DevTools → Application → Cookies → `app.aclacademy.me`, the Laravel session cookie shows the **Secure** flag (and `HttpOnly`, `SameSite=Lax`).

## Security notes

- **The tunnel credentials JSON — and any Cloudflare API token — are secrets.** Store the tunnel's `<UUID>.json` only as the base64 GitHub Codespaces secret `CLOUDFLARE_TUNNEL_CREDENTIALS_B64` (Settings → Secrets and variables → Codespaces). Never commit it, never put it in a tracked `.env`, never give it a default in `config.sh`. Anyone holding it can run your tunnel. This mirrors how the repo already treats `ACL_TIDB_*`: the contract is the variable name; the value lives only in the environment. `cert.pem` is a separate management credential (create/route/delete) and is likewise never committed; it is not needed to *run* the tunnel.
- **On-disk credentials caveat:** `start-services.sh` reconstructs the credentials file at `~/.cloudflared/<UUID>.json` (mode 600) and the config at `~/.cloudflared/config.yml` (mode 600) on every start — inside the container's private home, never in the repo tree. Unlike a token on `--token`, nothing puts the secret on the process argv / `ps`; the trade is a 600 file on the container's own disk, which is appropriate for a throwaway dev container.
- **Trusting all proxies (`trustProxies(at: '*')`) is safe only because the origin is loopback-only behind the tunnel.** Keep the Codespace's port `8000` **private** (do not "make public" the forwarded port), so nothing but `cloudflared` can inject `X-Forwarded-*` headers; Cloudflare overwrites them itself. Map the tunnel route to **only** `app → 127.0.0.1:8000` — never Mailpit (`:8025`), Vite (`:5173`), or the database.
- **`SESSION_SECURE_COOKIE=true` means the origin must be reached only via the HTTPS tunnel.** Hitting `http://localhost:8000` directly will not set the cookie and produces a login/419 loop.
- **There is no edge access gate — ACL's own login is the only barrier.** The domain is publicly reachable ([ADR-0009](../adr/0009-serve-from-codespace-via-cloudflare-tunnel.md) removed the Cloudflare Access OTP gate at the maintainer's request), so ACL's authentication, scoped RBAC and institutional entitlement are the sole authority for who gets in and what they may do. **Before exposing the URL, neutralise the seeded administrator:** `DevelopmentSeeder` creates `admin@acl.local` / `password`, and `AppServiceProvider`'s `Gate::before` makes a platform administrator omnipotent — on a public URL with no edge gate, that well-known default is an open admin door. Change its password, or do not run `DevelopmentSeeder` on the exposed instance.
- **`APP_DEBUG=false` before exposing**, so stack traces, env values and SQL never render to a visitor. Do not add a Cloudflare "Cache Everything" rule over HTML, or a cached CSRF token will 419 every visitor.
- **Governance.** Under `CLAUDE.md` §6/§12, publishing the app and changing devcontainer provisioning are architectural changes needing authorisation in-conversation and an ADR; that decision is **[ADR-0009](../adr/0009-serve-from-codespace-via-cloudflare-tunnel.md)**, accepted (its tunnel-secret mechanism amended by **[ADR-0010](../adr/0010-locally-managed-cloudflare-tunnel.md)**). §8 forbids secrets in the repo. This document is the procedure ADR-0009 points to — applying it is the authorised step.
