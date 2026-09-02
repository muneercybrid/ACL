# ACL Developer Setup (Phase Zero)

This repository is configured for GitHub Codespaces with:

- PHP 8.4
- Node 20
- MariaDB 10.11

## Codespaces bootstrap

The source of truth is:

- `/home/runner/work/acl_v0/acl_v0/.devcontainer/devcontainer.json`
- `/home/runner/work/acl_v0/acl_v0/.devcontainer/post-create.sh`

When a Codespace is created, the post-create script will:

1. Install and start MariaDB.
2. Create database `acl_v0`.
3. Create user `acl_user` with password `acl_password` (localhost + 127.0.0.1).
4. Run `composer install`.
5. Run `npm install --ignore-scripts`.
6. Create `.env` (if missing), apply MariaDB config, generate app key.
7. Run migrations.
8. Seed database only when `admin@acl.local` does not already exist.

## Manual recovery commands

If setup stops midway, run:

```bash
bash .devcontainer/post-create.sh
```

## Validation commands

```bash
php -v
composer -V
php artisan migrate:status
php artisan test
```