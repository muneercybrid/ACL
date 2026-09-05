# Deploying ACL

ACL is served in production from a **dedicated AWS EC2 instance** (Ubuntu 26.04,
PHP 8.5 native) through a **remotely-managed Cloudflare Tunnel**, at the Namecheap
domain **`app.aclacademy.me`**. The GitHub Codespace is **development-only and runs
no tunnel**. The reasoning is
[ADR-0011](../adr/0011-ec2-production-tier-and-remote-tunnel.md), which supersedes
the earlier "serve from the Codespace" decisions
([ADR-0009](../adr/0009-serve-from-codespace-via-cloudflare-tunnel.md),
[ADR-0010](../adr/0010-locally-managed-cloudflare-tunnel.md)); the step-by-step
procedure is [`DOMAIN_SETUP.md`](DOMAIN_SETUP.md).

> **This is a real always-on tier, but it is operated by hand.** There is no CI,
> no automated deploy, and reboot recovery has not been drilled. Deploying is a
> manual SSH step, so treat availability as best-effort until those gaps close.

## The serving path

```
browser ──HTTPS──▶ Cloudflare edge (TLS terminates here)
                     │  outbound-only tunnel (connector dials out; no inbound, no public IP)
                     ▼
                 cloudflared   (systemd service on the EC2)
                     │  plain HTTP, loopback only
                     ▼
                 nginx  127.0.0.1:8080   (loopback origin — never a public port)
                     │  FastCGI over a unix socket
                     ▼
                 php8.5-fpm  /run/php/php8.5-fpm.sock
                     │
                     ▼
                 Laravel ──▶ TiDB Cloud (MySQL-compatible, TLS :4000) + redis (127.0.0.1:6379)
```

The origin binds **loopback only** (`127.0.0.1:8080`), so nothing outside the box
can reach nginx directly — only `cloudflared` can. That is the condition that makes
`trustProxies(at: '*')` (`bootstrap/app.php`) safe: only Cloudflare can set the
`X-Forwarded-*` headers. The EC2 security group must **not** expose the app origin
to the internet; the connector dials out.

## Database and stores

- **Production runs on TiDB Cloud Serverless** (MySQL-compatible, over TLS on
  `:4000`) — the same managed engine the dev runtime uses
  ([ADR-0007](../adr/0007-tidb-cloud-dev-runtime.md)), extended to production by
  [ADR-0011](../adr/0011-ec2-production-tier-and-remote-tunnel.md). The production
  credential is **distinct from and rotated against** the development one, held only
  in the EC2 environment, and used with `MYSQL_ATTR_SSL_CA` pointing at the system CA
  bundle.
- **MariaDB remains the test-suite engine**
  ([ADR-0005](../adr/0005-adopt-mariadb.md)): every merge is validated against
  MariaDB, so MySQL-vs-MariaDB behaviour differences are caught by the suite before
  release.
- **Sessions, cache and queue run on the EC2's native redis** (`127.0.0.1:6379`) via
  the pure-PHP `predis` client ([ADR-0008](../adr/0008-redis-in-development.md)).

## Before it was exposed — the non-optional checks

1. **`APP_DEBUG=false`, `APP_ENV=production`, `APP_URL=https://app.aclacademy.me`,
   `SESSION_SECURE_COOKIE=true`** in the EC2 `.env`. `APP_DEBUG=true` on a public URL
   renders stack traces, environment values and SQL to any visitor.
2. **The seeded administrator was neutralised.** `DevelopmentSeeder` creates
   `admin@acl.local` / `password`, and `AppServiceProvider`'s `Gate::before` makes a
   platform administrator omnipotent. The three seeded demo passwords were rotated to
   strong values on the live box, so the well-known default no longer opens the door.
   **`admin@acl.local` is still omnipotent via `Gate::before`**, so the real fix — a
   production-safe bootstrap and the removal of blanket platform-admin — is a
   follow-up (see below), not yet done.

## Bootstrapping a real administrator

A freshly migrated database has no users, and `RbacSeeder` looks up the development
users by email — so it cannot create a clean administrator on its own. Until a
`ProductionSeeder` and an `acl:create-admin` command exist (see "What this does not
cover"), create the first real administrator by hand with `php artisan tinker`,
following the roles and models in `database/seeders/RbacSeeder.php`,
`app/Models/Role.php` and `app/Models/User.php` (`super.admin`, not `platform.admin`,
is the role that carries every permission). This is also the clean way to replace the
seeded `admin@acl.local`.

## The container image (retained, not the serving path)

The repository still carries a production-grade container image —
[`Dockerfile`](../../Dockerfile) plus [`docker/`](../../docker/) (nginx + php-fpm +
supervisor, health check on `/up`). ADR-0011 serves natively under systemd and reuses
the `docker/` **configs** (adapted to the host), so the image itself is **retained as
a portable fallback**, not the current serving path. Build it wherever Docker is
available with `docker build -t acl:local .`; moving to a container host would be a
new decision with its own ADR.

## What this does not cover

- **CI.** There is no `.github/` directory. Nothing runs `php artisan test` or Pint
  automatically, and nothing gates what reaches the EC2. This is the largest gap.
- **Automated deploy / reboot recovery.** Deploys are a manual `git pull` + build +
  config-cache over SSH; the systemd units (nginx, php-fpm, redis, cloudflared) start
  on boot by design, but a full reboot recovery has not been drilled.
- **A least-privilege production database user.** Production currently connects as a
  broad TiDB user; a scoped user is a follow-up (ADR-0011 security implications).
- **A production seeder / `acl:create-admin`.** Still unbuilt; the tinker recipe above
  is a manual workaround, and blanket platform-admin via `Gate::before` still applies.
- **A Content-Security-Policy header**, and **monitoring/alerting** — nobody is paged
  when the tunnel or the host goes down.
- **A queue worker.** `QUEUE_CONNECTION=redis` is set, but ACL dispatches no jobs yet
  and no worker service runs.
- **Mail.** `MAIL_MAILER=log` in production; no outbound mail is delivered yet.
