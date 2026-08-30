# Operations

Creating and running institutions. For local development see
[Getting started](./getting-started.md).

---

## 1. What the control plane is

`control-plane/` is a Laravel application **you** run; institutions never see it.
It owns a database holding the tenant registry — which institutions exist, their
hostnames, their encrypted credentials, deployment state, provisioning progress,
and your operator accounts.

**A tenant container must never hold credentials for this database.** Each tenant
receives only its own database login, injected as environment variables when its
container starts. This is also why `platform_admin` is not a row in any tenant's
`roles` table — an Institution Admin has nothing to escalate into.

Screens, operator accounts, audit, security behaviour and configuration are
documented in **[The control plane](./control-plane.md)**. What follows is the
task-level flow.

---

## 2. Provisioning an institution

Two ways in — both drive the same pipeline.

### From the web UI

Sign in at the control plane, then **Add institution**. The form registers the
tenant immediately and queues the rest, so you land on a detail page that shows
each of the six steps as it completes, refreshing every five seconds.

Provisioning takes about a minute, so **a queue worker must be running** or
nothing will progress:

```bash
cd control-plane
php artisan queue:work
```

Operator accounts are created from the CLI — there is no self-registration:

```bash
php artisan platform:admin you@example.com --name="Your Name" --generate
```

See [The control plane](./control-plane.md) for the screens, what happens with no
worker running, and the security behaviour.

### From the CLI

```bash
php artisan tenant:provision tu "Tribhuvan University" coordinator@tu.edu.np
```

Arguments: **slug**, **institution name**, **first Coordinator's email**. This
path runs synchronously and needs no queue worker.

The slug becomes the subdomain (`tu.supervisex.app`), the database name
(`tenant_tu`) and the container name (`tenant-tu`). It must be 2–40 characters,
lowercase, starting with a letter and ending alphanumeric. Hyphens are allowed
between and become underscores in the database name (`tu-pulchowk` →
`tenant_tu_pulchowk`), because hyphens are legal in hostnames but not in
Postgres identifiers.

### What it does

```
register tenant
   ↓ create_database     empty database for this institution
   ↓ create_role         login role scoped to that database only
   ↓ run_migrations      builds the schema
   ↓ seed_bootstrap      5 roles, default templates, first Coordinator
   ↓ start_container     docker compose up
   ↓ health_check        polls /up until it answers
active
```

Registration also generates, and stores encrypted:

- a random 32-character database password
- an **`APP_KEY` unique to this tenant** — sharing one across tenants would make
  signed cookies and encrypted payloads from one institution valid at another

### The Coordinator's password

`seed_bootstrap` generates a 16-character password and prints it **once**, to
whichever process runs it:

```
Coordinator created: coordinator@tu.edu.np / xK9$mQ2vN8pL4wRt
```

| Path | Where it appears |
|---|---|
| CLI (`tenant:provision`) | Your terminal |
| Web UI | The **queue worker's** output — not the browser |

It is never stored in plaintext, never rendered in the interface, and cannot be
recovered. Capture it, deliver it over a channel you trust, and have them change
it on first sign-in. If it is lost, use password reset on the tenant portal
rather than re-provisioning.

The first account is a **Coordinator**, not an Institution Admin — it is the
role that sets up templates and teams. Override with `TENANT_ADMIN_ROLE`.

### When a step fails

The pipeline stops, marks the tenant `failed`, and records the error against
the step in `provisioning_jobs`. Every step is idempotent, so the fix is to
resolve the cause and retry — completed steps are skipped.

In the UI, the detail page shows which step failed and its error, with a
**Retry provisioning** button. From the CLI, **run exactly the same command
again**:

```bash
php artisan tenant:provision tu "Tribhuvan University" coordinator@tu.edu.np
#   [create_database] already done, skipping
#   [create_role]     already done, skipping
#   [run_migrations]  running
```

Never unpick partial state by hand. Inspect what happened with:

```sql
SELECT step, state, attempts, last_error FROM provisioning_jobs
WHERE tenant_id = (SELECT id FROM tenants WHERE slug = 'tu');
```

### Listing

```bash
php artisan tenant:list
```

Shows slug, name, status, domain, image tag, schema version and health.

### Required configuration

Set these in `control-plane/.env` before provisioning for real:

```dotenv
BASE_DOMAIN=supervisex.app

# Superuser DSN, used ONLY to create databases and roles.
# Never given to a tenant container.
POSTGRES_ADMIN_DSN=postgresql://postgres:secret@localhost:5432/postgres

TENANT_DB_HOST=postgres
TENANT_DB_PORT=5432
TENANT_DB_DRIVER=pgsql
TENANT_IMAGE_TAG=latest
COMPOSE_FILE=../infra/compose.yaml

# Health-check budget: 30 x 2s is the "about a minute" the UI quotes
PROVISION_HEALTH_ATTEMPTS=30
PROVISION_HEALTH_DELAY=2
```

Defaults live in `control-plane/config/provisioning.php`.

---

## 3. Tenant status

| Status | Meaning |
|---|---|
| `provisioning` | Setup in progress |
| `active` | Serving users |
| `failed` | A step failed; re-run to resume |
| `suspended` | Container stopped, database retained |
| `deleting` / `deleted` | Being removed |

Suspension and deletion are **not yet implemented** in the control-plane app.
Stop a container manually for now:

```bash
docker compose -f infra/compose.yaml stop tenant-tu
```

---

## 4. Containers and deployment

> ⚠️ **Nothing in `infra/` has ever been built or run.** Docker was unavailable
> on the development machine, so these files are syntax-checked only. Treat the
> first `docker compose build` as a debugging session, not a deploy.

### What is in `infra/`

| File | Does |
|---|---|
| `tenant-app/Dockerfile` | Builds one image serving any institution: SPA + API |
| `tenant-app/entrypoint.sh` | Boots a container — caches config, starts worker and scheduler |
| `tenant-app/Caddyfile` | Inside a container: `/api/*` → Laravel, everything else → SPA |
| `compose.yaml` | Traefik routing, Postgres, example tenant service |

The image is **tenant-agnostic**. Nothing institution-specific is baked in —
identity arrives as environment variables at container start, so the same tag
can be rolled out to every tenant.

### Building

```bash
docker compose -f infra/compose.yaml build
```

Build context is the repository root, because the image needs both `backend/`
and `frontend/`.

### Environment for `compose.yaml`

```dotenv
BASE_DOMAIN=supervisex.app
ACME_EMAIL=ops@yourdomain
POSTGRES_PASSWORD=<strong password>
IMAGE_TAG=latest
```

### One database server, many databases

"A container per tenant" does **not** mean a database server per tenant. The
topology uses one Postgres server with a database and a scoped login role per
institution: same isolation for the credentials that matter, one backup target,
one thing to tune. Move to isolated database containers only if a specific
requirement demands it.

### Persistent storage — read this before the first real tenant

Theses, posters, demo videos and uploaded logos are written to
`/app/storage/app`. **Container filesystems are ephemeral: without a mounted
volume, a restart destroys every submission.** Each tenant service in
`compose.yaml` mounts its own volume. If you add a tenant service by hand, add
the volume too.

### Per-tenant background work

`QUEUE_CONNECTION=database`, so every tenant runs its own queue worker and
scheduler inside its container (started by `entrypoint.sh`). That is N× worker
processes — one of the real costs of container-per-tenant.

---

## 5. Rolling out a schema change

The main ongoing cost of the silo model: a migration must run against every
tenant database, and a failure partway leaves version skew against an image
that has already been updated.

### Expand and contract

Never combine an additive and a destructive change in one release. Old and new
code must both work against either schema while a rollout is in flight.

```
release 1   add the new column, write to both        ← safe to roll back
release 2   backfill, switch reads to the new column
release 3   drop the old column                      ← only now
```

### Running it

Migrations are executed inside each tenant's own container, so credentials never
leave it:

```bash
docker compose -f infra/compose.yaml run --rm tenant-tu php artisan migrate --force
```

`tenant_deployments.schema_version` records where each tenant got to — that is
what turns "something failed somewhere" into "these three tenants are behind".

> A control-plane command to fan this out across all tenants is **not yet
> built**. Today it is per-tenant, by hand.

---

## 6. Institution branding

Each tenant themes its own portal. An Institution Admin sets it via
`POST /api/admin/branding` on their own deployment; the login screen reads it
unauthenticated from `GET /api/branding`.

They supply four colours — primary, accent, ink, paper — and the rest of the
palette is derived. Status colours (danger, success) are never themed: a "red"
matched to someone's brand stops reading as an error.

Logo and favicon accept PNG/JPG/WebP only. **SVG is rejected** — assets are
served from the institution's own origin, so an SVG carrying a script would run
as first-party code against a signed-in admin.

No admin UI for this yet; the API works.

---

## 7. What is not built

| Gap | Impact |
|---|---|
| Tenant suspend / deprovision from the UI | Stop containers manually |
| JSON API for the control plane | UI is server-rendered; no programmatic access |
| Migration fan-out command | Migrate tenants one at a time |
| Branding and template admin screens | APIs exist, no UI |
| Centralised log aggregation | Per-container logs mean `docker exec` to debug |
| Per-tenant backup strategy | Not designed |
| Internship schema | Employer role exists with no features |
