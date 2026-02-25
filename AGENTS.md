# AGENTS.md

## Cursor Cloud specific instructions

### Overview

This is a **Laravel 12 / FilamentPHP v4** POS backend ("POS Stripe") for Norwegian market compliance. It provides:
- A REST API consumed by a FlutterFlow mobile app (Sanctum token auth)
- A Filament v4 admin panel (multi-tenant, scoped by Store)
- Stripe Connect integration for payments, terminals, subscriptions

### System dependencies (pre-installed in VM snapshot)

- **PHP 8.4** with extensions: bcmath, curl, dom, fileinfo, gd, intl, mbstring, pdo_pgsql, pdo_sqlite, pgsql, redis, xml, zip, imagick
- **Composer** (global, `/usr/local/bin/composer`)
- **Node.js 22** (via nvm) + npm
- **PostgreSQL 16** (for tests; dev uses SQLite by default)

### Composer authentication (required)

Two paid packages need auth tokens via `auth.json` (gitignored). Before running `composer install`, create `/workspace/auth.json`:

```json
{
    "http-basic": {
        "packages.filamentphp.com": {
            "username": "<FILAMENT_LICENSE_EMAIL>",
            "password": "<FILAMENT_LICENSE_KEY>"
        },
        "filament-workflow-engine.composer.sh": {
            "username": "<WORKFLOW_ENGINE_LICENSE_EMAIL>",
            "password": "<WORKFLOW_ENGINE_LICENSE_KEY>"
        }
    }
}
```

These are injected from environment secrets `FILAMENT_LICENSE_EMAIL`, `FILAMENT_LICENSE_KEY`, `WORKFLOW_ENGINE_LICENSE_EMAIL`, `WORKFLOW_ENGINE_LICENSE_KEY`.

### Database setup

- **Development**: SQLite (`database/database.sqlite`), `DB_CONNECTION=sqlite` in `.env`
- **Tests**: PostgreSQL, `DB_CONNECTION=pgsql`, database `pos_stripe_test`. Ensure PostgreSQL is running: `sudo pg_ctlcluster 16 main start`
- PHPUnit overrides the DB connection to `pgsql` in `phpunit.xml`

### Key commands

| Task | Command |
|------|---------|
| Dev server (all services) | `composer dev` (runs artisan serve + queue + pail + vite concurrently) |
| PHP linting | `./vendor/bin/pint` |
| Run tests | `php artisan test` or `./vendor/bin/pest` |
| Build frontend | `npm run build` |
| Vite dev server | `npm run dev` |
| Migrations | `php artisan migrate` |
| Generate app key | `php artisan key:generate` |

### Gotchas

- `npm run build` fails without `vendor/` (Vite references `vendor/filament/` CSS). Always run `composer install` before `npm run build`.
- The `.env.example` defaults to SQLite for dev and `database` queue driver (no Redis needed for basic dev).
- Tests in `phpunit.xml` use PostgreSQL (`pos_stripe_test` database) with `DB_USERNAME` from `.env` (set to `ubuntu` in Cloud VM).
- The `composer dev` script uses `npx concurrently` to run 4 processes: `php artisan serve`, `php artisan queue:listen`, `php artisan pail`, and `npm run dev`.
- Stripe API keys are needed for full E2E testing of payment features but not for basic app startup or unit tests.
