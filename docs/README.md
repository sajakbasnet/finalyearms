# SupervisEx documentation

Final year project supervision portal, built to serve multiple institutions.

## Start here

| I want to… | Read |
|---|---|
| Run it on my machine | [Getting started](./getting-started.md) |
| Create or manage an institution | [Operations](./operations.md) |
| Run the control plane | [The control plane](./control-plane.md) |
| Build a project timeline | [Activity templates](./activity-templates.md) |
| Run a team, supervisor request or proposal | [Project workflow](./project-workflow.md) |
| Check a title against other institutions | [Title registry](./title-registry.md) |
| Understand the tenancy design | [Architecture — multi-tenancy](./architecture/multi-tenancy.md) |
| Understand roles and permissions | [Architecture — access model](./architecture/access-model.md) |
| Look up a table or column | [Database](./db/README.md) |

## The 60-second version

The repository holds **four** pieces. Two are the product; two are how it runs.

```
finalyearms/
├── backend/         Laravel API — ONE COPY RUNS PER INSTITUTION
├── frontend/        React SPA — one build serves every institution
├── control-plane/   Laravel app YOU run to create and manage institutions
└── infra/           Dockerfile, Caddy and Compose config
```

Each institution ("tenant") gets **its own database and its own container**.
There are no `tenant_id` columns anywhere — the container boundary *is* the
tenant boundary. That is called the **silo model**.

```
  operator ──> control-plane ──> creates DB + credentials, starts container
                            └──> docker compose (infra/)

  student  ──> tu.supervisex.app ──> Traefik ──> tenant-tu container
                                                   (API + SPA, one image)
                                                          │
                                                          ▼
                                                   tenant_tu database
```

The rule everything else follows from: **a tenant container never holds
control-plane credentials.** One compromised institution must not be able to
reach another's data.

## Roles at a glance

| Role | Reach | Where it lives |
|---|---|---|
| Platform Admin | Platform-wide | control-plane DB |
| Institution Admin | Institution-wide | tenant `roles` |
| Coordinator | Institution/department | tenant `roles` |
| Supervisor | Assigned projects | tenant `roles` |
| Student | Own project/team | tenant `roles` |
| Team Lead | Own team/project | derived from `is_leader` |
| Employer | Assigned internship | tenant `roles` |

Details and the reasoning in [access-model.md](./architecture/access-model.md).

## Status

| Piece | State |
|---|---|
| `backend/` — API, RBAC, auth, audit, institution config, templates | Working, 239 tests |
| `frontend/` — SPA with per-tenant theming | Working, builds clean |
| `control-plane/` — operator UI, provisioning, CLI | Working, 68 tests |
| `infra/` — Dockerfile, Compose, Caddy | **Never built or run** |

`control-plane`'s ordering, resume and credential handling are tested against a
fake. The parts that shell out to `psql` and `docker compose` are not — Docker
was unavailable on the development machine. See
[Operations → Containers](./operations.md#4-containers-and-deployment).
