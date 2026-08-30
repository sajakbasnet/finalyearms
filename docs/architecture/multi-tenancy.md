# Multi-tenancy architecture — SupervisEx

How SupervisEx serves multiple institutions: the tenancy model, the container
topology, and per-tenant theming. Written against the state of `main` plus the
work described in [Implementation status](#implementation-status).

---

## 1. The model: silo, not shared schema

Each institution gets **its own database and its own application container**.
There is no `tenant_id` column anywhere, and no `stancl/tenancy`.

### Why this schema suits it

Every one of the 22 domain tables reaches a single institution through a chain
of foreign keys, and **no table joins two entities that could belong to
different tenants**:

| Root | Everything beneath it |
|------|----------------------|
| `departments` | `teachers`, `students` |
| `academic_sessions` | `students`, `student_groups`, `supervisor_assignments`, `projects` |
| `users` | `teachers`, `students`, all comment/reply/log authorship |
| `projects` | `proposal_versions` → `proposal_comments` → `proposal_replies`, `progress_reports` → `progress_comments`, `milestones`, `evaluations`, `meeting_logs`, `final_submissions`, `project_members` |

Even the join tables are safe: `student_group_members`, `project_members`,
`supervisor_assignments`, and `evaluations` all pair entities that are
necessarily within one institution. Nothing had to be split or duplicated —
the usual thing that kills a multi-tenancy retrofit is absent here.

### What the silo model buys

Because the connection *is* the tenant boundary, none of the following were
needed:

- `tenant_id` columns on 22 tables
- composite uniques on `users.email`, `students.registration_number`,
  `departments.code`, `academic_sessions.name`
- a `BelongsToTenant` trait and Eloquent global scopes
- per-tenant file path prefixes

**The application code is effectively unchanged.** The cost moves entirely to
infrastructure and a control plane.

It also neutralises a latent risk. Route model binding is unscoped throughout
(`{department}`, `{project}`, `{proposal}`, …) and there are only two ownership
checks in ~1,600 lines of service code (`StudentService.php:447`,
`TeacherService.php:402`). Under a shared schema that would be a
cross-institution data leak; under the silo model a container physically cannot
resolve another tenant's IDs.

> This is *not* a reason to leave it alone. It remains a same-institution
> authorization gap — `role:teacher` confirms *a* teacher, not *the*
> supervisor — and should be fixed on its own merits.

### The trade-offs, stated plainly

- **Cross-tenant reporting becomes fan-out.** "Projects across all
  institutions" is no longer a query. Platform-wide analytics need a reporting
  pipeline into the control plane.
- **Idle cost.** Each container idles at roughly 150–250 MB. Fifty tenants is
  ~10 GB doing nothing. Scale-to-zero helps, at the price of cold starts.
- **Migration fan-out** is the main ongoing operational cost — see §4.
- **Per-tenant workers.** `QUEUE_CONNECTION=database`, so every tenant runs its
  own queue worker and scheduler inside its container.

At a few dozen institutions this is comfortable. At several hundred, the
economics argue for consolidating small tenants into shared containers — which
this schema still permits, since nothing prevents adding `stancl/tenancy` later.

---

## 2. Topology

```
                        ┌───────────────────────────────┐
   *.supervisex.app ───►│  Traefik (TLS, host routing)  │
                        └───────────────┬───────────────┘
                    ┌───────────────────┼───────────────────┐
                    ▼                   ▼                   ▼
            ┌──────────────┐    ┌──────────────┐    ┌──────────────┐
            │ tenant-tu    │    │ tenant-ku    │    │ tenant-pu    │
            │ SPA + API    │    │ SPA + API    │    │ SPA + API    │
            │ queue+sched  │    │ queue+sched  │    │ queue+sched  │
            └──────┬───────┘    └──────┬───────┘    └──────┬───────┘
                   │                   │                   │
                   ▼                   ▼                   ▼
            ┌──────────────────────────────────────────────────┐
            │  Postgres — one database + one role per tenant    │
            │  tenant_tu   tenant_ku   tenant_pu                │
            └──────────────────────────────────────────────────┘

            ┌──────────────────────────────────────────────────┐
            │  control_plane DB  ◄── provisioner only           │
            │  tenants · domains · credentials · jobs · audit   │
            └──────────────────────────────────────────────────┘
```

### The rule that makes it worth doing

**A tenant container must never be able to reach the control-plane database.**

Tenant credentials are injected into each container's environment at
provisioning time. The tenant application is entirely self-contained and has no
notion of a master database. If tenant containers held a control-plane
connection, one compromised tenant would yield credentials for every other
institution — paying the full cost of containerisation while giving back the
isolation it buys.

This is why `stancl/tenancy` is absent. That package exists to swap database
connections per request inside a shared process; when one container serves
exactly one tenant, there is nothing to swap.

### Container per tenant ≠ database server per tenant

These are separate decisions. The current topology uses **one Postgres server
with a database and a scoped login role per tenant**: identical
credential-injection model, one backup target, one thing to tune. Isolated
database containers are an option later if a specific requirement demands it.

SQLite is not suitable here and is used only for local development and tests.

---

## 3. Provisioning

Owned by the **control-plane application** (`control-plane/`), a separate
Laravel app with its own database. A crash-safe state machine, one row per
`(tenant, step)` in `provisioning_jobs`. Every step is idempotent, so a failed
run is resumed by re-running the same command rather than unpicking partial
state.

```
register tenant  →  create_database  →  create_role
                 →  run_migrations   →  seed_bootstrap
                 →  start_container  →  health_check  →  active
```

Driven either from the control-plane web UI (**Add institution**, which queues
the pipeline and shows each step advancing) or from the CLI, which runs
synchronously:

```bash
cd control-plane
php artisan tenant:provision tu "Tribhuvan University" coordinator@tu.edu.np
php artisan tenant:list
```

Operator accounts, screens, audit and configuration are documented in
[The control plane](../control-plane.md).

The side-effecting half sits behind `TenantInfrastructure`, so ordering, resume
and failure handling are tested against a fake — those are the parts most
likely to be wrong and the hardest to exercise for real.

Each tenant receives its **own `APP_KEY`**, stored encrypted alongside its
database password. A shared key would make signed cookies and encrypted
payloads valid across institutions.

`TenantBootstrapSeeder` creates the five assignable roles, the default project
types and activity templates, one Coordinator account, and a branding row. Demo
data lives in `DatabaseSeeder` and must never reach a customer database — this
is enforced by a test.

**Platform Admin lives only here.** `platform_admins` is a control-plane table;
`platform_admin` is deliberately absent from every tenant's `roles` table, so an
Institution Admin cannot escalate to cross-tenant reach. `TenantBootstrapSeeder`
refuses to assign it and falls back to Coordinator.

---

## 4. Migrations across tenants

The main ongoing cost. A schema change must run against N databases, and a
failure partway through leaves version skew against an already-updated image.

Two things make this survivable:

1. **`tenant_deployments.schema_version`** records where each tenant got to, so
   a partial rollout can be identified rather than guessed at. There is no
   fan-out command yet — migrations run per tenant (see
   [Operations](../operations.md#5-rolling-out-a-schema-change)) and a failure
   stops at that tenant, leaving the rest untouched.
2. **Expand/contract migrations only.** Add columns → deploy → backfill → drop
   the old shape in a *later* release. Old and new code must both work against
   either schema during a rollout. Never combine an additive and a destructive
   change in one release.

`tenant_deployments.schema_version` is what turns "something failed somewhere"
into "these three tenants are behind".

---

## 5. Storage

`FILESYSTEM_DISK=local` with theses, posters, demo videos, and branding assets.
Container filesystems are ephemeral: **without a mounted volume a restart
destroys every submission.** Each tenant mounts its own volume at
`/app/storage/app`.

Uploaded branding assets are served under `/storage`, which the Caddyfile
routes to Laravel rather than to the SPA fallback.

---

## 6. Per-tenant theming

### Why it needs no per-tenant build

Every colour in the frontend is written as `bg-[var(--color-sea)]`, which
compiles to `background-color: var(--color-sea)` — resolved by the browser at
paint time, never baked into the bundle. Across the app there are **419**
`var(--color-*)` / `var(--font-*)` references and **zero** hardcoded Tailwind
palette classes.

Overriding those custom properties on the root element re-themes the entire
portal at runtime. **One build artifact, one image, N themes.**

### The token contract

Only **four** colours are tenant-supplied. The rest of the palette is derived
in `applyBranding()` using CSS `color-mix()`, which keeps the surface an
institution has to fill in small enough that results stay coherent:

| Tenant supplies | Derives |
|---|---|
| `primary` | `--color-sea`, `--color-sea-deep`, `--color-sea-soft`, `--color-on-brand` |
| `accent` | `--color-amber`, `--color-amber-soft` |
| `ink` | `--color-ink`, `--color-ink-deep`, `--color-ink-muted`, `--color-on-ink` |
| `paper` | `--color-paper`, `--color-paper-deep`, `--color-surface` |

`--color-danger` and `--color-success` are **never themed**. Status colours
carry meaning; a "danger" matched to someone's crimson identity stops reading
as an error.

### Contrast guard

`readableForeground()` computes WCAG relative luminance and picks white or
near-black for text on a brand surface. Without it, an institution whose brand
is a pale gold gets white-on-white in the sidebar and primary buttons. Bad
input falls back to white rather than blanking the UI.

### Delivery

`GET /api/branding` is **public and unauthenticated** — the login screen must
render themed before any token exists. It is throttled (60/min) as the only
public read endpoint.

Flash of default theme is avoided by caching the resolved branding in
`localStorage`, keyed by hostname, and applying it in `main.tsx` *before* React
mounts. First visit shows a brief default; every subsequent visit is instant.

Because the SPA is served from the tenant's own container, `/api` is
same-origin — no CORS, and a request cannot reach the wrong tenant's backend.

### Security decisions worth keeping

- **SVG uploads are rejected** for logo and favicon. Assets are served from the
  tenant's own origin, so an SVG carrying `<script>` would execute as
  first-party code against a signed-in admin.
- **Font stylesheet URLs are host-pinned** to `fonts.googleapis.com`. An
  arbitrary stylesheet URL injected into `<head>` is a first-party injection
  point.
- **Demo credentials are gated behind `import.meta.env.DEV`** and never render
  on a tenant deployment.

---

## Implementation status

Done and tested on this branch:

| | |
|---|---|
| `branding` table, model, service, public + admin endpoints | ✅ 11 tests |
| `TenantBootstrapSeeder`, split from demo data | ✅ 3 tests |
| Frontend theming: derived tokens, `applyBranding`, `BrandingProvider`, `BrandMark`, contrast guard | ✅ typecheck + build |
| Colour-literal cleanup, `StatusChip` extraction (replaced 3 duplicated helpers) | ✅ |
| Hostname-derived API base URL | ✅ |
| `infra/` — Dockerfile, Caddyfile, entrypoint, compose + Traefik | ⚠️ syntax-checked only |

**Not yet validated:** the `infra/` files have never been built or run —
Docker was unavailable in the development environment. Treat the first
`docker compose build` as a debugging session, not a deploy.

**Not yet built:**

- Admin UI for editing branding (the API exists; nothing calls it yet)
- Centralised log aggregation — per-container logs otherwise mean exec-ing
  into containers to debug
- Deprovisioning with a database retention window
- Backup strategy per tenant database
