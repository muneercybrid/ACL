# Deploying ACL

ACL is served from its **GitHub Codespace through a Cloudflare Tunnel**, at the
Namecheap domain **`app.aclacademy.me`**. There is no separate production tier and
no Render service. The reasoning is
[ADR-0009](../adr/0009-serve-from-codespace-via-cloudflare-tunnel.md); the
step-by-step procedure is [`DOMAIN_SETUP.md`](DOMAIN_SETUP.md).

> **This is a development server made reachable, not production.**
> `php artisan serve` behind a tunnel has no process supervisor, no zero-downtime
> deploy, one worker, and it stops when the Codespace stops. It is adequate for a
> low-traffic showcase and must not be described as a production deployment.

## The serving path

```
browser ──HTTPS──▶ Cloudflare edge (TLS terminates here)
                     │  outbound-only tunnel (no inbound, no public IP)
                     ▼
                 cloudflared  (inside the Codespace)
                     │  plain HTTP, loopback
                     ▼
                 http://127.0.0.1:8000   (php artisan serve)
```

Follow [`DOMAIN_SETUP.md`](DOMAIN_SETUP.md) in order: point the domain at
Cloudflare (Part 1), create the token tunnel and route `app` → `localhost:8000`
(Part 2), make it survive Codespace restarts (Part 3), set the four `.env` keys
for HTTPS-behind-a-proxy (Part 4), and run the go-live checklist (Part 5).

## Before you expose it — two things that are not optional

1. **`APP_DEBUG=false`, `APP_ENV=production`, `APP_URL=https://app.aclacademy.me`,
   `SESSION_SECURE_COOKIE=true`.** `APP_DEBUG=true` on a public URL renders stack
   traces, environment values and SQL to any visitor. See `DOMAIN_SETUP.md` Part 5.
2. **Neutralise the seeded administrator.** `DevelopmentSeeder` creates
   `admin@acl.local` / `password`, and `AppServiceProvider`'s `Gate::before`
   makes a platform administrator omnipotent. With no edge access gate (removed
   at the maintainer's request — ADR-0009), that well-known default is an open
   admin door on the public URL. **Change its password, or do not run
   `DevelopmentSeeder` on the exposed instance.** See `DOMAIN_SETUP.md` security
   notes.

## Bootstrapping a real administrator

A freshly migrated database has no users, and `RbacSeeder` looks up the
development users by email — so it cannot create a clean administrator on its
own. Until a `ProductionSeeder` and an `acl:create-admin` command exist (see
"What this does not cover"), create the first real administrator by hand in the
Codespace with `php artisan tinker`, following the roles and models in
`database/seeders/RbacSeeder.php`, `app/Models/Role.php` and `app/Models/User.php`
(`super.admin`, not `platform.admin`, is the role that carries every permission).
This is also the clean way to replace the seeded `admin@acl.local`.

## The container image (retained, not the current path)

The repository still carries a production-grade container image — `Dockerfile`
plus `docker/` (nginx + php-fpm + supervisor, built at start, health check on
`/up`). It is **kept as a portable, platform-neutral option** for a future
always-on production tier; it names no host. It is *not* how ACL is served today.

Build it yourself wherever Docker is available (the Codespace has it) — the
fastest way to find a build error:

```bash
docker build -t acl:local .
```

To run it you supply a database and an `APP_KEY` (the entrypoint refuses to boot
without the key, by design):

```bash
docker run --rm -p 8080:10000 \
  -e APP_KEY="base64:..." -e APP_URL="http://localhost:8080" \
  -e DB_HOST=host.docker.internal -e DB_PORT=3306 \
  -e DB_DATABASE=acl -e DB_USERNAME=acl_user -e DB_PASSWORD=secret \
  acl:local
```

On Linux use `--network host` and `DB_HOST=127.0.0.1`, where
`host.docker.internal` does not resolve. Moving to a real container host is a new
decision and wants its own ADR (ADR-0009, Future Considerations).

## What this does not cover

- **CI.** There is no `.github/` directory. Nothing runs `php artisan test` or
  Pint automatically. This is the largest remaining gap.
- **A production seeder or an admin-creation command.** The tinker recipe above
  is a manual workaround for a missing feature, not the intended design. It
  should ship with feature tests before ACL carries real students.
- **File uploads.** `FILESYSTEM_DISK=local` on an ephemeral Codespace filesystem.
  ACL has no upload feature yet; the first one needs its own decision.
- **A queue worker.** ACL dispatches no jobs yet.
- **Mail.** `MAIL_MAILER=log` in a plain clone; the devcontainer points it at
  Mailpit. Password reset reaches no one until a provider is configured.
- **A Content-Security-Policy header**, and **monitoring/alerting** — nobody is
  paged when the tunnel or the Codespace stops.
