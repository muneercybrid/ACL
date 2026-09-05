# Serving ACL at app.aclacademy.me — AWS EC2 + remotely-managed Cloudflare Tunnel

*This document records the procedure that produced ACL's current production tier:
a dedicated AWS EC2 instance served at `https://app.aclacademy.me` through a
remotely-managed Cloudflare Tunnel. The decision is
[ADR-0011](../adr/0011-ec2-production-tier-and-remote-tunnel.md), which supersedes
the earlier "serve from the Codespace" procedure
([ADR-0009](../adr/0009-serve-from-codespace-via-cloudflare-tunnel.md)) and its
locally-managed tunnel
([ADR-0010](../adr/0010-locally-managed-cloudflare-tunnel.md)). The GitHub Codespace
is now development-only and runs no tunnel. Commands below run on the EC2 host over
SSH unless noted; the Windows laptop is only your editor and dashboard browser.*

> **This is a real always-on tier, operated by hand.** There is no CI and no
> automated deploy; a release is a manual `git pull` + build + config-cache over
> SSH. Reboot recovery of the systemd units has not been drilled. Treat
> availability as best-effort until those gaps close.

## How it fits together

A visitor reaches `https://app.aclacademy.me` at a Cloudflare edge server, where
TLS terminates (Cloudflare's free Universal SSL). From the edge the request rides
the tunnel: the `cloudflared` connector on the EC2 holds **outbound-only**
connections to Cloudflare (port 7844), so the host needs no public inbound port and
no elastic IP. On the box, `cloudflared` forwards each request over **plain HTTP on
loopback** to nginx, which binds `127.0.0.1:8080` only. nginx serves static files
and passes PHP to php-fpm over a unix socket; Laravel talks to TiDB Cloud over TLS
and to the host's redis.

```
        browser
          │  HTTPS  (ACL's own login is the only barrier — no edge access gate)
          ▼
      Cloudflare edge  ──────────────  ◀── TLS TERMINATES HERE
          │  encrypted tunnel; connector dials OUT (7844) — no inbound, no public IP
          ▼
      cloudflared      (systemd service on the EC2)
          │  plain HTTP, loopback only
          ▼
      nginx  127.0.0.1:8080   (loopback-only origin — never a public port)
          │  FastCGI over a unix socket
          ▼
      php8.5-fpm  /run/php/php8.5-fpm.sock   (www-data)
          │
          ▼
      Laravel ──▶ TiDB Cloud (MySQL-compatible, TLS :4000)  +  redis (127.0.0.1:6379)
```

The loopback origin is the load-bearing control: nothing outside the box can reach
nginx, so only the local `cloudflared` can inject `X-Forwarded-*` headers, and
Cloudflare overwrites them at the edge. That is what makes `trustProxies(at: '*')`
(`bootstrap/app.php`) safe. The EC2 security group must **not** expose the origin
port; the connector dials out.

## Prerequisites

- A dedicated **AWS EC2 instance**, Ubuntu 26.04, reachable over SSH with your key
  pair. Keep the private key off the repo and out of every commit — reference its
  path, never its contents.
- The domain **`aclacademy.me`** active on **Cloudflare**. If the zone is not yet on
  Cloudflare, ADR-0009's onboarding step is unchanged.
- A **Cloudflare Zero Trust** account able to create a tunnel (the paywall that
  forced ADR-0010's locally-managed tunnel has since been cleared).
- A **TiDB Cloud Serverless** cluster with a production database and a credential
  **rotated distinct from** the development one.
- Outbound network from the EC2 on **7844** (the tunnel) and **443** (TiDB TLS,
  package mirrors).

## Part 1 — Provision the host

Install the native runtime: PHP 8.5 with the extensions ACL needs (the same set
`.devcontainer/config.sh` lists), nginx, and redis.

```bash
sudo apt-get update
sudo apt-get install -y \
  nginx redis-server \
  php8.5-fpm php8.5-cli php8.5-mysql php8.5-mbstring php8.5-xml \
  php8.5-curl php8.5-zip php8.5-bcmath php8.5-intl php8.5-gd
```

Confirm the pieces are present and enabled:

```bash
php -v                       # 8.5.x
systemctl is-enabled nginx php8.5-fpm redis-server
php -m | grep -E 'pdo_mysql|mbstring'
```

redis runs on `127.0.0.1:6379`, **passwordless and loopback-only** — acceptable
only because nothing off-box can reach it, which is why the app config sets
`REDIS_PASSWORD=null`. If you ever bind redis to a non-loopback interface, give it a
password first.

## Part 2 — Deploy the application

Clone to `~/ACL` and install production dependencies:

```bash
git clone https://github.com/muneercybrid/ACL.git ~/ACL
cd ~/ACL
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # compiles public/build + manifest.json
```

`npm run build` (never `npm run dev`) is required: Blade's `@vite` needs
`public/build/manifest.json`, and the Vite dev-server origin is unreachable in
production.

### The `.env`

Write `~/ACL/.env` **on the box only** — it is git-ignored and must never be
committed. The keys below are the production contract; the actual secret values
(the TiDB password, the generated `APP_KEY`) live only in this file and are shown
here by name, not value:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.aclacademy.me
APP_KEY=            # php artisan key:generate --force writes this
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=gateway01.<region>.prod.aws.tidbcloud.com
DB_PORT=4000
DB_DATABASE=acl
DB_USERNAME=<tidb-user>
DB_PASSWORD=<tidb-password — secret, set on the box only>
MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt

SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0
REDIS_CACHE_DB=1

MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@aclacademy.me"
```

Generate the key, then cache config, routes and views:

```bash
php artisan key:generate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

`MYSQL_ATTR_SSL_CA` pointing at the system CA bundle is what lets PDO verify TiDB's
TLS certificate; without it the connection fails closed. `REDIS_CLIENT=predis`
selects the pure-PHP client (ADR-0008), so no `phpredis` extension is needed.

## Part 3 — nginx (the loopback origin)

Create `/etc/nginx/sites-available/acl`, adapted from the repository's
`docker/site.conf.template`. The non-negotiable line is `listen 127.0.0.1:8080` —
the origin must never bind a public interface:

```nginx
server {
    listen 127.0.0.1:8080;
    server_name app.aclacademy.me;
    root /home/ubuntu/ACL/public;
    index index.php;

    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options SAMEORIGIN;
    add_header Referrer-Policy strict-origin-when-cross-origin;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ ^/index\.php$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ \.php$ { deny all; }      # only index.php is a front controller
    location ~ /\.    { deny all; }      # no dotfiles (.env, .git)

    location /build/ {                   # hashed Vite assets, immutable
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

Enable it, drop the default site, test, reload:

```bash
sudo ln -sf /etc/nginx/sites-available/acl /etc/nginx/sites-enabled/acl
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

## Part 4 — php-fpm

php-fpm 8.5 serves the `www-data` pool over `/run/php/php8.5-fpm.sock` — the socket
nginx points at in Part 3. The stock pool is fine; just confirm it is running and
the socket exists:

```bash
systemctl status php8.5-fpm
test -S /run/php/php8.5-fpm.sock && echo "socket present"
```

Because nginx runs as `www-data` and the app files are group-owned by `www-data`
(Part 6), php-fpm reads the code without widening permissions.

## Part 5 — The remotely-managed Cloudflare Tunnel

Unlike ADR-0010's locally-managed tunnel, the connector is configured **from the
Zero Trust dashboard** and authenticates with a **token**. There is no local
`config.yml` and no `cert.pem` on the box.

1. In **Cloudflare Zero Trust → Networks → Tunnels → Create a tunnel**, choose
   **Cloudflared**, name it, and copy the install command it shows.
2. On the EC2, install `cloudflared` and register the connector as a systemd
   service with the token from step 1:

   ```bash
   sudo cloudflared service install <CONNECTOR_TOKEN>
   ```

   The **token is a production secret**: it is not stored in the repo and should
   not be pasted where it will be logged. If it is ever exposed, use **Refresh
   token** in the dashboard.
3. Back in the dashboard, add a **public hostname** for the tunnel:
   - **Subdomain** `app`, **Domain** `aclacademy.me`
   - **Type** `HTTP`, **URL** `localhost:8080` (the loopback origin from Part 3)
4. Saving the public hostname **auto-creates** the proxied `CNAME`
   `app → <tunnel-id>.cfargotunnel.com`. If you see **"A DNS record with this name
   already exists,"** an old `app` record (from a previous tunnel or a Pages site)
   is in the way — delete it under **DNS → Records**, then save the hostname again.

Confirm the connector is healthy, then that only one tunnel is live (delete any
duplicate you created while setting this up — confirm which is serving first):

```bash
systemctl status cloudflared
```

Only ever map the public hostname to `localhost:8080`. Never expose redis, the TiDB
port, or any other service through the tunnel.

## Part 6 — Filesystem permissions

The app runs as `www-data` (php-fpm) but is owned by `ubuntu`; the two share files
through the `www-data` group, and the `.env` stays unreadable to anyone else:

```bash
sudo chown -R ubuntu:www-data ~/ACL
sudo find ~/ACL -type d -exec chmod 750 {} \;
sudo find ~/ACL -type f -exec chmod 640 {} \;
sudo chmod -R 2770 ~/ACL/storage ~/ACL/bootstrap/cache   # group-writable, setgid
sudo chmod 640 ~/ACL/.env
chmod o+x /home/ubuntu                                    # let www-data traverse in
```

The `chmod o+x /home/ubuntu` matters: a `0750` home directory otherwise blocks
`www-data` from traversing into `~/ACL` at all — the symptom is a blanket 403/500
with a permission error in the php-fpm log.

## Part 7 — Go-live and verification

1. **Neutralise the seeded administrator before the URL is shared.**
   `DevelopmentSeeder` creates `admin@acl.local` / `password`, and
   `AppServiceProvider`'s `Gate::before` makes a platform administrator omnipotent.
   Either do not run `DevelopmentSeeder` on the box, or rotate the seeded passwords
   to strong values. Replacing this with a production-safe bootstrap
   (`acl:create-admin`) and removing blanket platform-admin is the tracked
   follow-up in ADR-0011 — until then `admin@acl.local` is a superuser.
2. **Confirm the full path answers**, from anywhere:

   ```bash
   for p in up login ""; do
     curl -s -o /dev/null -w "/$p -> %{http_code}\n" "https://app.aclacademy.me/$p"
   done
   ```

   Expect `/up -> 200`, `/login -> 200`, `/ -> 302` (redirect to login). `/up` is
   Laravel's built-in health route.
3. **Confirm the session cookie is Secure.** After signing in, the Laravel session
   cookie should show `Secure`, `HttpOnly`, `SameSite=Lax` in browser devtools.

### Redeploying

A release is manual and out-of-band (there is no CI):

```bash
cd ~/ACL && git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo systemctl reload php8.5-fpm
```

`migrate --force` is required to run non-interactively in production; run it only
when a change actually adds migrations. Production shares the TiDB `acl` database,
so treat every migration with the same care as live data.

## Security notes

- **The origin is loopback-only.** nginx binds `127.0.0.1:8080` and the EC2 security
  group exposes no inbound app port, so only the local `cloudflared` can reach the
  origin or set `X-Forwarded-*`. This is the sole condition that makes
  `trustProxies(at: '*')` safe.
- **`APP_DEBUG=false`, `APP_ENV=production`** so stack traces, env values and SQL
  never render to a visitor; **`SESSION_SECURE_COOKIE=true`** so the session cookie
  is never sent over plain HTTP.
- **Secrets live only on the box.** The tunnel connector token is held by the
  `cloudflared` systemd service; the TiDB production password lives only in
  `~/ACL/.env` (mode 640). Neither is ever committed. Rotate on exposure — the TiDB
  console for the DB credential, **Refresh token** for the tunnel.
- **Open follow-ups (ADR-0011):** a least-privilege TiDB production user (not a
  broad or `…root` grant), an `acl:create-admin` / production seeder to retire the
  seeded admin and blanket platform-admin, a **Content-Security-Policy** header, and
  monitoring/alerting for the host and the tunnel.

## Governance

Under `CLAUDE.md` §6/§12, promoting a production tier and changing devcontainer
provisioning are architectural changes requiring an ADR and in-conversation
authorisation; that decision is
[ADR-0011](../adr/0011-ec2-production-tier-and-remote-tunnel.md). §8 forbids any
secret in the repository — this procedure keeps the connector token and the TiDB
credential on the box, exactly as the earlier procedure kept its secret in a
Codespaces secret.









