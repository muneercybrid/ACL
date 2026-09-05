# ADR-0013: Honeybadger for error and uptime monitoring

## Status

Accepted.

- **Depends on [ADR-0012](0012-doppler-secrets-source-of-truth.md)** — the
  `HONEYBADGER_API_KEY` is a secret delivered through Doppler's `prd` config, read
  only in `config/honeybadger.php`, never in the repository.
- **Partially satisfies [ADR-0011](0011-ec2-production-tier-and-remote-tunnel.md)'s
  monitoring follow-up** — "ACL now operates infrastructure it must patch, secure
  and monitor" and "Availability now tracks the EC2." This closes the **application
  error-tracking and external-uptime** slice; host/infrastructure metrics (CPU,
  disk, php-fpm/redis/systemd health) and the reboot-recovery operations write-up
  remain **open**.
- **Uses existing surface** — the `/up` health route and the currently-empty
  `withExceptions` hook, both in `bootstrap/app.php`. Supersedes no ADR.

Adopting Honeybadger adds a new `composer.json` dependency
(`honeybadger-io/honeybadger-laravel`) and a new third-party service on the
production path; `CLAUDE.md` §6 puts both behind explicit authorisation and an
ADR. The user authorised it in the conversation that produced this ADR. This is
that authorisation and that ADR.

Date: 2026-09-05

## Context

ADR-0011 stood up an always-on, internet-reachable EC2 tier and explicitly named
monitoring as newly-owned operational surface, with availability now tracking the
box. Today ACL has **no** error tracking — `withExceptions` in `bootstrap/app.php`
is empty, so exceptions land only in the host log channel — and **no** external
uptime check. On a hand-operated box with no CI and manual deploys, an unnoticed
500 or a down site is invisible until a human happens to hit it.

## Problem

How does ACL get application error tracking and external uptime monitoring, with
alerting, for the production tier — without building and operating an observability
stack itself, without a new secret in the repository, and without leaking PII or
credentials into a third-party error service?

## Options Considered

1. **Logs only (status quo — the host log channel on the EC2).** No new
   dependency, but no alerting, no aggregation and no uptime check; it needs
   someone tailing a file. Rejected — it is the gap ADR-0011 named.
2. **Self-hosted** (Sentry self-hosted, GlitchTip, or Prometheus + Alertmanager +
   Blackbox exporter). Full control, but a whole stack to run on the one box ACL
   already must patch — the operational surface ADR-0009/0011 worked to bound.
   Rejected as premature.
3. **Sentry SaaS.** Capable and popular, but a heavier client and more product
   surface than an early single-box app needs. Viable, more than required.
4. **Honeybadger SaaS.** Error tracking, uptime checks and simple alerting in one
   product; a first-class Laravel package; a light client; a generous
   small-project tier; and an uptime monitor that can ping the existing public
   URL. Chosen — the smallest managed thing that closes ADR-0011's gap.

## Decision

1. **Adopt Honeybadger for the production tier**, for (a) application exception
   tracking and (b) external uptime monitoring of `app.aclacademy.me`.
2. **Dependency:** add `honeybadger-io/honeybadger-laravel` (pin `^5.1`; v5.1.0
   declares Laravel 11|12|13 and PHP ^8.2, so it resolves cleanly on ACL's
   L13.25 / PHP 8.5) to `composer.json` — a new, authorised dependency.
3. **Wiring — and the load-bearing detail:** the package's service provider is
   auto-registered by package discovery, **but on Laravel 11+ exception reporting
   is not automatic.** A `report` callback must be added to the existing
   `withExceptions` hook in `bootstrap/app.php`; installing the package alone
   captures nothing. The callback is guarded so it is inert when the service is
   not bound:
   ```php
   $exceptions->report(static function (Throwable $e): void {
       if (app()->bound('honeybadger')) {
           app('honeybadger')->notify($e, app('request'));
       }
   });
   ```
4. **Config and secret:** configuration lives in `config/honeybadger.php`;
   `HONEYBADGER_API_KEY` is sourced from Doppler (ADR-0012, `prd`) and read
   **only** in that config file, never `env()` at a call site, never in the
   repository.

5. **Uptime:** a Honeybadger uptime check targets the public site (the `/up`
   health route), exercising the whole path — Cloudflare edge → tunnel → EC2 →
   nginx → php-fpm — so it catches a tunnel-down or app-down end to end. No new
   route.
6. **Production only.** Honeybadger is inert in the Codespace and in tests — no key
   present, or reporting disabled (`APP_ENV=local` / `HONEYBADGER_REPORT_DATA=false`
   in the Codespace) — so development and the feature suite transmit nothing;
   `phpunit.xml` keeps the suite offline.
7. **Data minimisation is mandatory, not optional.** The error stream is an egress
   of student-data-adjacent payloads, so `config/honeybadger.php` must scrub it:
   extend `request.filter` beyond the two Honeybadger defaults (`password`,
   `password_confirmation`) with `current_password`, the password-reset `token`,
   `_token`, `remember`, and the PII fields `email`/`name`; add the sensitive
   request headers (`HTTP_COOKIE`, `HTTP_AUTHORIZATION`, the XSRF/CSRF headers) and
   `QUERY_STRING`/`REQUEST_URI` to `environment.filter`; keep `environment.include`
   **empty** (adding a secret env name there is the one action that defeats the
   built-in environment whitelist that keeps `DB_PASSWORD`/`APP_KEY`/`DOPPLER_TOKEN`
   out of a report); and register a `beforeNotify` callback to drop the session
   (`password_hash_web`, `_token`, `login.web.*`) and redact any DSN in an
   exception message. A feature test with a spy transport asserts an exception on
   an auth route reports no password, no `acl-session` cookie and no password
   hash — run in the Codespace before sign-off.

Installing the package (`composer require`), running
`php artisan honeybadger:install` (which creates `config/honeybadger.php`, writes
the key to `.env`, and sends a **live** test notification, so it runs only in the
environment that owns the real key), writing the filtering config and the
spy-test, and creating the uptime check are a manual step verified out-of-band in
the Codespace and on the box (no CI, ADR-0011). This ADR records the decision.

## Reasoning

- **It turns ADR-0011's "must monitor" from intent into coverage** with the
  smallest managed piece: errors captured and alertable, and an external check
  that knows the site is down before a student does.
- **It reuses what already exists** — the `/up` route and the empty
  `withExceptions` hook — so the application change is additive and small.
- **SaaS over self-hosted** keeps ACL from operating yet another service on the one
  box, consistent with ADR-0011's deliberately boring posture.
- **The secret rides ADR-0012's Doppler path**, so no new secret-handling
  exception is created.

## Consequences

**Good**

- Production exceptions become visible and alertable, and an external uptime check
  watches the site — a real paging path for a hand-operated box.
- Partially closes ADR-0011's monitoring follow-up.
- No behavioural change to the app beyond wiring `withExceptions`; dev and tests
  transmit nothing.

**Bad**

- **Error payloads leave the box to a third party and must be scrubbed** — the
  Constitution's "never expose sensitive information through logs" now extends to
  an external error service. The filtering config and its spy-test are a
  precondition, not a nicety.
- A new dependency and vendor coupling; swapping to Sentry later is a superseding
  ADR plus a client swap.
- A new secret to manage (mitigated by ADR-0012); still no CI to gate the change
  onto the box.

**Neutral**

- **Scope boundary:** Honeybadger closes only the application-error and uptime
  slice. Host/infrastructure metrics and the systemd/reboot operations write-up
  from ADR-0011 stay **open**.## Security Implications

- **Error reports can carry sensitive data** — request bodies, headers, the
  session and credentials. Scrub and deny by default (Decision §7); treat the
  error stream as an egress of potentially-sensitive data.
- **`HONEYBADGER_API_KEY` is a secret via Doppler** (ADR-0012), rotated on leak. It
  is a write/ingest key, not a data-read key, which bounds the blast radius.
- **The uptime check hits only `/up`**, which exposes no sensitive data.
- **Production-only**, so no development or test data is transmitted, and no secret
  enters the repository.
- **Adjacent production checks this work surfaces** (verify on the box,
  independent of Honeybadger): `APP_DEBUG=false` (Laravel's debug renderer dumps
  env and bindings *before* any Honeybadger filter runs); `SESSION_SECURE_COOKIE=true`
  behind the TLS tunnel (`config/session.php` reads it with no default, so it is
  currently unset); `MYSQL_ATTR_SSL_CA` set (unset silently drops TiDB TLS); and
  `trustProxies('*')` safe only while the origin is tunnel-only.

## Future Considerations

- **Host/infrastructure metrics and a systemd/reboot operations write-up**
  (ADR-0011) remain open; Honeybadger **check-ins** could later monitor a queue
  worker, the scheduler or a deploy job once those exist, proving the EC2 and its
  cron are alive.
- **When CI arrives**, Honeybadger deploy/release tracking becomes available —
  additive, no ADR.
- **If error volume or product needs outgrow Honeybadger**, revisit versus Sentry
  under a superseding ADR.
