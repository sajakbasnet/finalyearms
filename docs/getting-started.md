# Getting started

Running SupervisEx on your own machine. No Docker required — local development
runs one institution directly on SQLite.

## Prerequisites

| Need | Version | Check |
|---|---|---|
| PHP | 8.3+ | `php -v` |
| Composer | 2.x | `composer -V` |
| Node | 20+ | `node -v` |

> **On Windows with WSL:** run every command below inside WSL
> (`wsl -d Ubuntu`), not PowerShell. PHP and Composer are installed in the
> distro, and Node is under `nvm` rather than the login `PATH` — if `node`
> is not found, run `source ~/.nvm/nvm.sh` first.

## 1. The tenant app (`backend/`)

This is the portal itself. Locally you run a single institution.

```bash
cd backend
composer install

# Only if .env is missing:
cp .env.example .env
php artisan key:generate
touch database/database.sqlite

php artisan migrate --seed
php artisan serve                 # http://localhost:8000
```

`--seed` loads demo data: two departments, an academic session, a handful of
users, and one project mid-workflow. It also runs the tenant bootstrap
(roles, default project types and activity templates).

### Demo accounts

All use the password `password`.

| Email | Role | Can do |
|---|---|---|
| `admin@fyp.local` | Institution Admin | Departments, calendar, user accounts, branding |
| `coordinator@fyp.local` | Coordinator | Templates, supervisor overrides, proposal oversight |
| `teacher@fyp.local` | Supervisor | Review proposals and progress for assigned projects |
| `teacher2@fyp.local` | Supervisor | A second supervisor, for testing assignment |
| `student@fyp.local` | Student | Own proposal, progress, final submission |
| `student2@fyp.local` | Student | A student with no supervisor assigned yet |

These exist only in `DatabaseSeeder`. A real tenant is provisioned with
`TenantBootstrapSeeder`, which creates **no** demo data — enforced by a test.

## 2. The SPA (`frontend/`)

```bash
cd frontend
npm install
npm run dev                       # http://localhost:5173
```

`frontend/.env` points the SPA at the API:

```
VITE_API_URL=http://127.0.0.1:8000/api
```

That override only matters in development. In production the SPA and API are
served from the same container on the same hostname, so the client resolves
`/api` on its own origin — see `src/api/client.ts`.

## 3. The control plane (`control-plane/`)

Only needed when working on tenant provisioning. It is a **separate Laravel
app** with its own database.

```bash
cd control-plane
composer install
php artisan migrate
php artisan serve --port=8001     # http://localhost:8001
```

> **Use a different port.** Both apps ship with `APP_URL=http://localhost:8000`
> in `.env`. If you run them together, change the control plane's to
> `http://localhost:8001` or the two will clash.

Create yourself an operator account, then sign in at
<http://localhost:8001>:

```bash
php artisan platform:admin you@example.com --name="Your Name" --generate
```

`--generate` prints a password once; omit it to be prompted instead (which
keeps the password out of your shell history).

Because provisioning runs on the queue, a worker has to be running for anything
to happen after you submit the form:

```bash
php artisan queue:work
```

The same operations are available from the CLI:

```bash
php artisan tenant:list
php artisan tenant:provision tu "Tribhuvan University" coordinator@tu.edu.np
```

Provisioning a real tenant needs Postgres and Docker; without them the pipeline
fails at the first step and the UI shows you exactly where. See
[Operations](./operations.md).

## Running everything at once

Three terminals:

```bash
# 1
cd backend && php artisan serve

# 2
cd frontend && npm run dev

# 3  (only if working on provisioning)
cd control-plane && php artisan serve --port=8001
```

Then open <http://localhost:5173>.

In `backend/`, `composer dev` runs the API, queue worker and log tailer together
(it no longer tries to start Vite — the frontend is a separate directory), and
`composer setup` does first-time setup end to end:

```bash
cd backend && composer setup   # install, .env, key, sqlite, migrate --seed
cd backend && composer dev     # server + queue + logs
```

The SPA still needs its own `npm run dev` in `frontend/`.

## Tests

```bash
cd backend       && php artisan test      # 239 tests
cd control-plane && php artisan test      #  68 tests
cd frontend      && npx tsc -b && npx oxlint && npx vite build
```

Narrow to one file while working:

```bash
php artisan test --compact tests/Feature/RbacAuthorizationTest.php
php artisan test --compact --filter=test_coordinator_manages_templates
```

## Formatting

PHP is formatted with Pint. Run it before finishing any change:

```bash
cd backend && vendor/bin/pint --dirty
```

## Common problems

**`Class "App\Models\X" not found` after adding a model**
`composer dump-autoload`.

**Frontend shows a stale theme**
Branding is cached in `localStorage` per hostname to avoid a flash of the
default palette. Clear site data, or hard-reload.

**Login always fails after several attempts**
That is the lockout working: five failed attempts per email+IP locks that pair
for 15 minutes. Clear it with

```bash
# tenant portal, from backend/
php artisan tinker --execute="RateLimiter::clear('login:you@example.com|127.0.0.1');"

# control plane, from control-plane/ — note the different prefix
php artisan tinker --execute="RateLimiter::clear('cp-login:you@example.com|127.0.0.1');"
```

**`no such table` in the control plane**
Its database is separate from the backend's. Run `php artisan migrate` inside
`control-plane/`.

**A stray `backend/database/tenant_acme.sqlite`**
Left over from an abandoned experiment. It is gitignored and unused — safe to
delete.
