# Deploying ACL

ACL deploys as a container image built from [`Dockerfile`](../../Dockerfile) in
this repository, onto Render, described by
[`render.yaml`](../../render.yaml). The reasoning behind both choices — and why
the database is *not* on Render — is
[ADR-0006](../adr/0006-deploy-on-render-with-docker.md).

## Read this first

**Nothing here has been executed.** The image has never been built: there is no
Docker on the development laptop, and no environment is live. This document is a
procedure, not a record of a working system. Expect the first build to surface
something, and read the build log rather than assuming.

**Two things are missing before ACL is genuinely usable in production**, both
called out in full below:

1. There is no CI pipeline, and `render.yaml` sets `autoDeploy: true`. Every
   push to `main` deploys without the test suite having run. See Step 3.
2. There is no production-safe seeder. `php artisan db:seed` creates the
   development accounts, and `RbacSeeder` cannot run without them. A freshly
   migrated production database has **no users and no roles**, so the login page
   renders and nobody can get past it. See Step 6.

**Why Render needs Docker at all:** Render's native runtimes are Node, Python,
Ruby, Go, Rust and Elixir. There is no PHP runtime. A repository with no
`Dockerfile` gives Render nothing to build — the most likely reason the earlier
attempt failed, though without that build log it stays a hypothesis.

## Before you start

You need:

- A Render account, with a workspace on the Hobby plan or higher.
- The Namecheap domain, using **Namecheap BasicDNS or PremiumDNS**. If the
  domain points at custom nameservers, editing Namecheap's Advanced DNS tab has
  no effect at all — fix that before Step 7.
- A MariaDB (or MySQL) instance ACL may connect to, plus an empty schema on it.
- `main` pushed. Render builds a branch, not your working tree.

Everything below runs in a browser or in Render's shell. Nothing needs Docker on
your laptop, except the optional local build in the troubleshooting section.

## Step 1 — Provision MariaDB

Render has managed PostgreSQL and a Redis-compatible Key Value store, and **no
managed MySQL or MariaDB**. [ADR-0005](../adr/0005-adopt-mariadb.md) requires
MariaDB in every environment, so the database comes from somewhere else.

### Option A — a managed MariaDB/MySQL provider (recommended)

Any provider that gives you a host, port, schema, user and password works:
Aiven, PlanetScale, Scaleway, DigitalOcean Managed MySQL, or the managed MariaDB
you already have. You get backups, point-in-time recovery and failover from a
vendor whose job that is.

1. Create the instance in the region **closest to Frankfurt** — that is where
   `render.yaml` puts the web service, and every query pays the round trip.
2. Create an empty schema. `acl` is a fine name. **Laravel does not create the
   schema**; `php artisan migrate` connects *to* it and fails if it is absent.
3. Create a user with privileges on that schema only:

   ```sql
   CREATE DATABASE acl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'acl_user'@'%' IDENTIFIED BY 'a-long-random-password';
   GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON acl.* TO 'acl_user'@'%';
   FLUSH PRIVILEGES;
   ```

   `CREATE`, `ALTER`, `INDEX`, `DROP` and `REFERENCES` are needed because
   migrations run on start. **Not** `root`, and no `WITH GRANT OPTION`.
4. Restrict inbound access to Render's egress IP addresses — Render publishes
   them per region on the service's **Connect → Outbound** panel — and require
   TLS. Do not leave the database open to `0.0.0.0/0`.
5. Download the provider's CA bundle if it requires TLS. You will paste its
   *path* into `MYSQL_ATTR_SSL_CA`, so the file has to be inside the image or
   fetched at start; if you have no clean way to ship it, use a provider whose
   certificate chains to a public root and leave `MYSQL_ATTR_SSL_CA` unset.
   `config/database.php` already reads that variable.

**If you are reusing the managed MariaDB whose root password appeared in a
commented-out line of a working `.env`: rotate that credential first, and create
`acl_user` rather than connecting as root.**

### Option B — MariaDB as a Render private service

`render.yaml` carries this as a commented block at the bottom. Uncomment it, set
`DB_HOST` to `acl-mariadb` and `DB_PORT` to `3306` on the web service, and both
services live on one bill with the database never exposed to the internet.

Understand what you are accepting: one container, no replication, no failover,
and Render's own documentation warns that **disk snapshots will likely corrupt
database files**. A scheduled `mysqldump` becomes mandatory, not optional. The
disk must mount at exactly `/var/lib/mysql` — anywhere else and the data sits on
the ephemeral filesystem and is destroyed by the next deploy.

## Step 2 — Generate `APP_KEY`

Once, in the codespace:

```bash
php artisan key:generate --show
```

Copy the whole `base64:...` string. Keep it somewhere you will not lose it.

This is the one value that must never change after the first deploy. Rotating it
invalidates every session and makes every encrypted column unreadable.
`docker/entrypoint.sh` refuses to boot without it, and deliberately never
generates one for you — a key generated per deploy would destroy data quietly.

## Step 3 — Create the Render service

`render.yaml` is a **blueprint**: Render reads it and creates the service for
you, so the settings are reviewable in Git instead of remembered from a
dashboard.

1. Render dashboard → **New** → **Blueprint**.
2. Connect the GitHub repository `muneercybrid/ACL` and pick branch `main`.
3. Render shows what it parsed from `render.yaml`: one web service named `acl`,
   `runtime: docker`, region Frankfurt, plan Starter. If it instead reports that
   it found no services or cannot parse the file, stop — that is a YAML problem,
   not a build problem, and the log names the line.
4. Render lists every variable marked `sync: false` and asks for a value. Fill
   them in from Step 4 **before** the first build.
5. Confirm. Render clones, builds the image and starts it.

Three settings in `render.yaml` are worth knowing you chose:

| Setting | Value | Why, and what it costs |
|---|---|---|
| `region` | `frankfurt` | Closest Render region to Nigeria — roughly half the round trip of the US regions. **Changing it later means recreating the service**, so it is worth getting right now. |
| `plan` | `starter` | `free` also works and costs nothing, but it spins down after inactivity and the next visitor waits for a cold start that re-runs migrations and rebuilds every cache. `starter` also gives you a shell, which Steps 6 and 8 use. |
| `autoDeploy` | `true` | Every push to `main` deploys. **There is no CI pipeline in this repository** — no `.github/` directory, nothing runs `php artisan test` or Pint first. Until that exists, `main` reaches production unverified. If that is not acceptable yet, set `autoDeploy: false` and deploy by hand from the dashboard. |

## Step 4 — Set the environment variables

These are the `sync: false` entries. Everything else in `render.yaml` already has
a value and needs no attention.

| Variable | Value | Notes |
|---|---|---|
| `APP_KEY` | the `base64:...` string from Step 2 | Never changes again. |
| `APP_URL` | `https://your-domain.com` | The **public** URL, with `https://`. Wrong here means wrong links in every redirect and email. Until the domain is attached, use `https://acl.onrender.com` (your real `onrender.com` hostname) and change it in Step 7. |
| `DB_HOST` | the database hostname | Option A: the provider's host. Option B: `acl-mariadb`. |
| `DB_PORT` | `3306` | Or whatever the provider gives you. |
| `DB_DATABASE` | `acl` | The schema you created, which must already exist. |
| `DB_USERNAME` | `acl_user` | Not `root`. |
| `DB_PASSWORD` | the password | |
| `MYSQL_ATTR_SSL_CA` | a path to a CA bundle | Only if the provider requires TLS *and* you can get the file into the container. Leave unset otherwise. |

**None of these values belong in Git.** `render.yaml` declares only their names;
`.dockerignore` lists `.env` first so no build context can carry your local file
into a layer.

After changing any variable, Render redeploys. That matters because
`docker/entrypoint.sh` runs `config:cache` at start — a changed variable does not
take effect until the container restarts.

## Step 5 — First deploy: what to watch

The build takes several minutes. Composer and npm both run, and there is no
cache on the first attempt.

**In the build log**, in order:

1. `composer install --no-dev` completes. If it fails on a missing extension,
   the `runtime` stage's `docker-php-ext-install` list needs the name it printed.
2. `npm ci` completes. If it fails with `npm ci can only install packages when
   your package.json and package-lock.json are in sync`, the lockfile is stale —
   run `npm install` in the codespace, commit the lockfile, push.
3. `npm run build` writes to `public/build`. Vite prints the file list.
4. The final image is pushed.

**In the deploy (runtime) log**, `docker/entrypoint.sh` narrates itself:

```
[acl] rendering nginx config for port 10000
[acl] nginx config OK
[acl] building caches
[acl] waiting for the database
[acl] running migrations
```

Then supervisor starts php-fpm and nginx, and Render reports the service live
once `/up` answers.

What failure looks like, and what it means:

| Log line | Cause | Fix |
|---|---|---|
| `APP_KEY is not set` and the container exits | The variable is empty or was never set | Step 2, then Step 4 |
| `the database did not become reachable` | Wrong host/port, the schema does not exist, or Render's egress addresses are not allow-listed | Step 1.2 and 1.4 |
| `Access denied for user` | Wrong credentials, or the user has no privileges on that schema | Step 1.3 |
| `nginx: [emerg] ... /etc/nginx/conf.d/default.conf` | `envsubst` produced an invalid config | Report it — `entrypoint.sh` runs `nginx -t` precisely so this fails loudly at start instead of serving blank pages |
| Health check fails, no PHP error anywhere | php-fpm is up but got no environment | `clear_env = no` in `docker/php-fpm-pool.conf` is what prevents this; check it is still there |
| A 500 with no detail | Correct behaviour. `APP_DEBUG=false` hides stack traces from visitors | Read the stderr log in Render, where Laravel writes it |

**Do not set `APP_DEBUG=true` to debug production.** It renders stack traces,
environment values and SQL to whoever asks. The entrypoint warns loudly about it
at every start for that reason. The stderr log has the same information.

## Step 6 — Make the application usable (the seeding gap)

**Read this before you conclude the deploy failed.**

After Step 5 the container is healthy and `/login` renders. **You cannot log in.**
Migrations create tables; they do not create rows. The database has no users, no
roles and no permissions.

And there is currently no safe way to fix that with a seeder:

- `php artisan db:seed` runs `DatabaseSeeder`, which calls `DevelopmentSeeder`
  first — that creates `admin@acl.local`, `lecturer@acl.local` and
  `student@acl.local`. **Development accounts must never exist in production.**
- `php artisan db:seed --class=RbacSeeder` does not work either. `RbacSeeder`
  creates the permissions and roles, then looks up those same development users
  by email and a `computer-science` department to assign roles to. With none of
  them present it throws on a null.

So the honest position: **ACL has no production bootstrap path, and closing that
gap is application work with its own tests, not a deployment step.** Until it
exists, create the first administrator by hand in Render's shell.

Render dashboard → the `acl` service → **Shell**:

```bash
php artisan tinker
```

Then, pasting one block at a time and reading the output:

```php
// 1. Permissions and roles, copied from RbacSeeder's first half.
$permissions = collect([
    ['name' => 'Manage Users',         'slug' => 'users.manage',         'group' => 'users'],
    ['name' => 'Manage Organizations', 'slug' => 'organizations.manage', 'group' => 'organizations'],
    ['name' => 'Create Courses',       'slug' => 'courses.create',       'group' => 'courses'],
    ['name' => 'Publish Courses',      'slug' => 'courses.publish',      'group' => 'courses'],
    ['name' => 'Submit Grades',        'slug' => 'grades.submit',        'group' => 'grades'],
])->map(fn ($p) => \App\Models\Permission::firstOrCreate(['slug' => $p['slug']], $p));

$role = \App\Models\Role::firstOrCreate(
    ['slug' => 'super.admin'],
    ['name' => 'Super Admin', 'scope_level' => 'platform', 'is_system' => true]
);
$role->permissions()->sync($permissions->pluck('id'));
```

`super.admin`, not `platform.admin`. In `RbacSeeder` only `super.admin` receives
every permission — `platform.admin` is created and attached to nothing, so a user
holding it can authorize nothing. Create the other four roles the same way when
you have real institutions to scope them to.

```php
// 2. One real administrator. Use a real address and a password you generated.
$user = \App\Models\User::create([
    'name'     => 'Your Name',
    'email'    => 'you@your-domain.com',
    'password' => \Illuminate\Support\Facades\Hash::make('paste-a-long-random-password'),
]);

\App\Models\RoleAssignment::create([
    'user_id'     => $user->id,
    'role_id'     => $role->id,
    'entity_type' => null,   // platform scope has no specific entity
    'entity_id'   => null,
]);
```

Verify before leaving the shell:

```php
$user->roleAssignments()->count();     // expect 1
\App\Models\Permission::count();       // expect 5
$role->permissions()->count();         // expect 5
```

Every name above is taken from `database/seeders/RbacSeeder.php`,
`app/Models/Role.php` and `app/Models/User.php` as they stand today. If a
migration later renames `scope_level` or `group`, this recipe goes stale — read
the models rather than trusting this block.

The proper fix, when you get to it, is a `ProductionSeeder` that creates
permissions and roles with no user dependency, plus an `acl:create-admin`
console command that takes an email and prompts for a password. That is a
backend change with feature tests, and it should ship before ACL carries real
students.

## Step 7 — Connect the Namecheap domain

Order matters: **add the domain in Render first, then edit DNS.** Render needs to
know the hostname before it can verify it or issue a certificate.

### 7.0 — Check which nameservers the domain actually uses

The most common way this step wastes an afternoon: editing Namecheap's Advanced
DNS tab on a domain that does not use Namecheap's nameservers. The records save,
look correct, and are ignored by the entire internet.

Namecheap → **Domain List** → **MANAGE** on your domain → the **NAMESERVERS**
section. It must read **Namecheap BasicDNS** (or PremiumDNS). If it says *Custom
DNS* and lists something else, either switch it to Namecheap BasicDNS — which can
take up to a few hours to propagate — or make the equivalent records at whichever
DNS provider those nameservers belong to.

### 7.1 — Add the domain in Render

Render dashboard → the `acl` service → **Settings** → **Custom Domains** → **Add
Custom Domain**.

Enter the apex form, `your-domain.com`. Render then adds `www.your-domain.com`
automatically and sets it to redirect to the apex — you do not add both, and you
do not need a redirect service. (Enter `www.your-domain.com` instead if you want
`www` to be canonical; Render adds and redirects the apex.)

Render now shows the DNS records it expects. Leave that tab open.

### 7.2 — Edit DNS at Namecheap

Namecheap → **Domain List** → **MANAGE** → the **Advanced DNS** tab.

**Delete before you add.** A fresh Namecheap domain ships with parking records
that will conflict:

| Delete | Why |
|---|---|
| Any **A Record** on Host `@` | You are replacing it. Two A records on `@` send visitors to two different servers at random. |
| Any **CNAME Record** or **URL Redirect Record** on Host `www` | Namecheap's default points `www` at `parkingpage.namecheap.com`. |
| Any **URL Redirect Record** on Host `@` | Namecheap will not accept an A record on `@` while one exists. |
| **Every AAAA Record** | Render's documentation is explicit: `AAAA` records can interfere with Render hosting your custom domains. Delete them, do not edit them. |

Then add exactly two records:

| Type | Host | Value | TTL |
|---|---|---|---|
| **A Record** | `@` | `216.24.57.1` | **1 min** |
| **CNAME Record** | `www` | `acl.onrender.com` *(your service's real `onrender.com` hostname, with the trailing dot if Namecheap adds one)* | **1 min** |

Notes that matter:

- `216.24.57.1` is Render's shared load-balancer address for apex domains. It is
  the same for every Render customer; it is not secret and not per-service.
- Copy the CNAME value from the Render tab. It is your service's hostname, which
  is `acl.onrender.com` only if that name was free when the service was created —
  Render appends a suffix otherwise.
- **TTL 1 minute** for both, deliberately. Render's own instruction: a short TTL
  lets verification succeed sooner. Raise it to something ordinary later if you
  like.
- Namecheap sometimes shows a **CNAME Record** row it will not let you delete on
  `@`. Leave it; the A record on `@` is what answers.
- For any further subdomain — `staging`, `api` — add another CNAME to the same
  `onrender.com` hostname and add that subdomain in Render too.
- If you have any **CAA record**, it must authorize `letsencrypt.org` and
  `pki.goog`, or certificate issuance fails silently. No CAA record at all is
  fine.

Save. Namecheap applies changes within about half an hour, usually much faster.

### 7.3 — Verify in Render

Back on the **Custom Domains** panel, click **Verify** next to each domain.

Render checks DNS, then issues a TLS certificate automatically and renews it
automatically. HTTP is redirected to HTTPS for you — which is exactly why
`render.yaml` sets `SESSION_SECURE_COOKIE=true`.

If verification fails, it is nearly always propagation or a leftover record.
Check what the world actually sees:

```bash
nslookup your-domain.com
```

```bash
nslookup www.your-domain.com
```

The first must answer `216.24.57.1`. The second must show the `onrender.com`
hostname. If either shows something else, or shows an IPv6 address, the old
record is still live — recheck the delete list in 7.2.

### 7.4 — Point `APP_URL` at the real domain

This is the step people forget, and the symptom is confusing: the site loads, but
redirects and generated links go to `onrender.com`.

Render → the `acl` service → **Environment** → set `APP_URL` to
`https://your-domain.com` → save. Render redeploys, and the entrypoint's
`config:cache` picks up the new value on start.

## Step 8 — Verify the deployment

From your laptop:

```bash
curl -i https://your-domain.com/up
```

Expect `HTTP/2 200`. That is Laravel's health endpoint, wired in
`bootstrap/app.php`, and it is what Render's health check polls.

```bash
curl -sI http://your-domain.com | head -n 3
```

Expect a `301` to the `https://` URL — Render's redirect, not the application's.

Then in a browser:

- `https://your-domain.com/` renders the welcome page.
- `https://your-domain.com/login` renders the login form.
- Signing in with the administrator from Step 6 reaches the dashboard.
- The page is styled. Unstyled means Vite's manifest or `public/build` did not
  reach the image — check the build log for `npm run build`.

In Render's **Shell**, confirm the state the container actually sees:

```bash
php artisan about
```

Read four lines: `Environment` is `production`, `Debug Mode` is `OFF`,
`Config` / `Routes` / `Views` are `CACHED`, and the `Database` section names
`mariadb`.

```bash
php artisan migrate:status
```

Every migration must read `Ran`.

## Operating it

**Logs.** Render → the service → **Logs**. Laravel writes to stderr
(`LOG_CHANNEL=stderr`), nginx access to stdout, so everything is in one stream.
There is no `storage/logs/laravel.log` worth reading — the container is
ephemeral, and a file nothing rotates on a disk nobody backs up is not a log.

**Shell.** Render → the service → **Shell**. Available on `starter` and above.
It runs in a live container; anything you write to the filesystem disappears on
the next deploy.

**Deploy.** Push to `main`. With `autoDeploy: true` that is the whole procedure.
Manually: Render → **Manual Deploy** → *Deploy latest commit*.

**Rollback.** Render → **Deploys** → pick a previous successful deploy →
**Rollback to this version**. This restores the *image*. It does not reverse a
migration — a deploy that added a column and a rollback that removes the code
using it leaves the column in place, which is harmless, but a destructive
migration is not reversed by rolling back the image. Write reversible
migrations, as `CLAUDE.md` §10 already requires.

**Backups.** Option A: your provider's job — confirm they are on and test a
restore once. Option B: **your job, and mandatory.** Render's documentation warns
that disk snapshots will likely corrupt database files, so `mysqldump` is the
only backup you can trust:

```bash
mysqldump --single-transaction --routines --triggers -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" > acl-backup.sql
```

`default-mysql-client` is installed in the image, so this runs in Render's shell.
Copy the file off the container; it does not survive a deploy. A backup you have
never restored is a hypothesis.

**Migrations at scale.** `docker/entrypoint.sh` runs `php artisan migrate` on
start. That is correct for one instance and wrong for several — two containers
booting together race. Before scaling past one instance, set
`ACL_RUN_MIGRATIONS=false` and move migrations to a Render pre-deploy command.

## Building the image yourself

You do not need this to deploy — Render builds the image. It is how you find a
build error in two minutes instead of after a push.

Wherever Docker is available (the codespace has it):

```bash
docker build -t acl:local .
```

To run it, it needs a database and an `APP_KEY`. Against a MariaDB reachable from
the container:

```bash
docker run --rm -p 8080:10000 -e APP_KEY="base64:..." -e APP_URL="http://localhost:8080" -e DB_HOST=host.docker.internal -e DB_PORT=3306 -e DB_DATABASE=acl -e DB_USERNAME=acl_user -e DB_PASSWORD=secret acl:local
```

Then `curl -i http://localhost:8080/up`. Use `--network host` and
`DB_HOST=127.0.0.1` on Linux, where `host.docker.internal` does not resolve.

Do not skip `APP_KEY` to "see if it starts" — the entrypoint refuses, by design.

## Troubleshooting

| Symptom | Most likely cause |
|---|---|
| Render reports no services in the blueprint | `render.yaml` is not on the branch Render is reading, or failed to parse. The log names the line. |
| Build fails immediately, no Composer output | Render did not find `Dockerfile` at the repository root, or `dockerfilePath` is wrong. |
| `Could not open input file: artisan` | The build context excluded too much. Check `.dockerignore` against what the stage copies. |
| Container starts, then exits with `APP_KEY is not set` | Step 2 and Step 4. Working as intended. |
| `the database did not become reachable after 60s` | Wrong `DB_HOST`/`DB_PORT`, the schema does not exist, or Render's egress addresses are not allow-listed. The entrypoint prints the driver's own error under this message — read that line. |
| `SQLSTATE[HY000] [2002]` | The host resolves but nothing answers on the port. Firewall or wrong port. |
| `Unknown database 'acl'` | The schema was never created. Laravel does not create it. Step 1.2. |
| Health check fails but PHP logs nothing | php-fpm has no environment. `clear_env = no` in `docker/php-fpm-pool.conf`. |
| Site loads unstyled | `public/build` or Vite's manifest is missing. Check `npm run build` in the build log. |
| 419 on every login attempt | `APP_URL` does not match the address in the browser, or `SESSION_SECURE_COOKIE=true` over plain HTTP. Step 7.4. |
| Redirects go to `onrender.com` | `APP_URL` still points there. Step 7.4. |
| Custom domain will not verify | A leftover AAAA record, or the domain does not use Namecheap's nameservers. Steps 7.0 and 7.2. |
| Certificate never issues | A CAA record that does not authorize `letsencrypt.org` and `pki.goog`. |
| Everything works, nobody can log in | Expected. Step 6. |

## What this document does not cover

- **CI.** There is no `.github/` directory in this repository. Nothing runs
  `php artisan test` or Pint before a deploy, and `autoDeploy: true` means `main`
  reaches production unverified. This is the largest remaining gap, and it is the
  next infrastructure change worth making.
- **A production seeder or an admin-creation command.** Step 6 is a manual
  workaround for a missing feature, not the intended design.
- **Staging.** One more service block in `render.yaml`, on a different branch,
  with its own schema.
- **File uploads.** `FILESYSTEM_DISK=local` writes to a filesystem destroyed on
  every deploy. ACL has no upload feature, so nothing is lost today; the first one
  needs object storage and its own decision.
- **A queue worker.** ACL dispatches no jobs yet. `docker/supervisord.conf`
  records where the worker goes.
- **Mail.** `MAIL_MAILER=log` writes messages to stderr instead of pretending to
  deliver them. Password reset will not reach anyone until a provider is
  configured.
- **A Content-Security-Policy header.** Deliberately absent from
  `docker/site.conf.template`: what a correct policy allows depends on Vite's
  output and Alpine's inline behaviour, so it belongs with the frontend work.
- **Monitoring and alerting.** Nobody is paged when this stops.
