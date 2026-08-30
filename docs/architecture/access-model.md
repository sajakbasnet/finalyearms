# Access model — roles, permissions, authentication

The seven roles, how they map onto the API, and how authentication and audit
work. Companion to [`multi-tenancy.md`](./multi-tenancy.md).

---

## 1. The seven roles

| Role | Responsibility | Access level | Lives in |
|---|---|---|---|
| Platform Admin | Tenant and platform management | Platform-wide | [`control-plane`](../control-plane.md) DB (`platform_admins`) |
| Institution Admin | Institution-level administration | Institution-wide | tenant `roles` |
| Coordinator | Academic and project administration | Institution/department | tenant `roles` |
| Supervisor | Project supervision | Assigned projects | tenant `roles` |
| Student | Project execution | Own project/team | tenant `roles` |
| Team Lead | Team and project coordination | Own team/project | **derived**, see §3 |
| Employer | Internship participation | Assigned internship | tenant `roles` |

Two are deliberately *not* rows in a tenant's `roles` table:

- **Platform Admin** is a control-plane identity. If a tenant database could
  grant it, an Institution Admin could escalate to cross-tenant reach.
  `TenantBootstrapSeeder` refuses to assign it and falls back to Coordinator.
- **Team Lead** is contextual — see §3.

`UserRole::assignable()` encodes both exclusions, and both are covered by tests.

### Migration from the previous three roles

```
admin   → institution_admin
teacher → supervisor          (the schema already said supervisor_id)
student → student
```

`2026_08_29_203732_migrate_roles_to_seven_role_model` renames the rows and adds
`coordinator` and `employer`. Existing `admin` users become Institution Admins;
Coordinator duties must be granted by promoting specific users, which the
migration cannot decide. Rolling back refuses if any user still holds one of the
new roles rather than orphaning their authentication.

---

## 2. Permissions, not roles, guard the API

Routes are guarded with `can:<key>`; the matrix lives in `config/rbac.php`.
Moving a capability between roles is a config change with no route edits.

`AuthServiceProvider` defines one Gate per catalogue key, applying two layers:

1. **Does the role hold the key** — `config/rbac.php`.
2. **Does this user own the subject** — for scoped keys ending `.own` /
   `.assigned`, and every `team.*` key.

The second layer matters because route model binding is unscoped: holding
`proposal.review` must not let a supervisor review someone else's proposal.

### The Institution Admin / Coordinator split

| Endpoint | Owner |
|---|---|
| `admin/departments` | Institution Admin |
| `admin/sessions`, `admin/sessions/{s}/dates` | Institution Admin |
| `admin/batches` | Institution Admin |
| `admin/teachers`, `admin/students` | Institution Admin |
| `admin/branding` | Institution Admin |
| `admin/students/{s}/assign-supervisor` | Coordinator |
| `admin/proposals` | Coordinator |
| `coordinator/project-types` | Coordinator |
| `coordinator/activity-templates` | Coordinator |

Coordinators hold `batch.view` and `session.view` but not the matching
`.manage` keys: oversight needs the context, but organisational structure is
the Institution Admin's remit. Supervisors hold `batch.view` too, so a student
list can show which cohort they belong to.

Publishing a template is a separate key (`activity-template.publish`) from
building one, so the ability to make a template usable can be split from the
ability to draft one without changing any routes.

Coordinators keep read access to departments, sessions and users — oversight
needs the context — but cannot mutate accounts or org structure.

### The project workflow keys

Six keys carry the student → team → supervisor → proposal flow, described in
full in [project-workflow.md](../project-workflow.md):

| Key | Held by | Guards |
|---|---|---|
| `project.create.own` | Student | Starting an individual or team project |
| `team.manage.own` | Student | Inviting, removing, handing over the lead |
| `team.invitation.respond` | Student | Answering an invitation sent to you |
| `supervisor.directory.view` | Student | Browsing available supervisors |
| `supervisor-request.send` | Student | Asking a supervisor to take the project |
| `supervisor-request.respond` | Supervisor | Accepting or declining a request |

Note `team.manage.own` rather than the contextual `team.member.manage`: route
middleware cannot supply a group subject, so a contextual key guarding a route
resolves against nothing and denies everyone. See §3. The lead-versus-member
check happens inside `TeamService`, where the group is known.

### A trap worth knowing about

Every role holds `notification.view.own`. Guarding the student notification
routes on that key alone let an **Institution Admin reach `/api/student/*`**,
which resolves a Student profile. Fixed with `student.workspace`, a Student-only
key guarding the whole prefix. Regression test:
`RbacAuthorizationTest::test_non_students_cannot_reach_student_notification_routes`.

The general rule: **a shared permission must never guard a role-scoped prefix.**

---

## 3. Team Lead is contextual

`users.role_id` holds exactly one value, and a team lead still submits their own
work — so they remain a **Student**. Leadership is read from
`student_group_members.is_leader`, which is per-group.

This is not a modelling preference. Making Team Lead a `roles` row would mean:

- `User::isStudent()` returns false for them
- their `student` profile relation goes unused
- `/api/student/*` rejects them
- `is_leader` contradicts `users.role_id`

So `UserRole::TeamLead` exists in the enum and the matrix, but its permissions
are only ever merged in when a group is supplied *and* the user leads it:

```php
$user->hasPermission('team.member.manage', $group);
```

Unscoped, they never appear. A student leading group A gets nothing extra in
group B — covered by
`RbacAuthorizationTest::test_team_lead_permissions_apply_only_to_the_group_they_lead`.

**A contextual key can never guard a route.** `can:team.member.manage` in route
middleware resolves with no group, so it denies everyone including the lead —
it returns 403 for every caller, which reads like a permissions bug rather than
a design one. Route guards use a plain key (`team.manage.own`) and the
group-scoped check happens in the service, where the group is actually known.

---

## 4. Authentication

Two separate systems: the tenant portal uses Sanctum bearer tokens, the control
plane uses server-side sessions. They share no accounts.

### Tenant portal

Sanctum bearer tokens. Endpoints:

| Route | Throttle | Notes |
|---|---|---|
| `POST /api/auth/login` | 10/min | plus per email+IP lockout |
| `POST /api/auth/logout` | — | |
| `POST /api/auth/change-password` | — | revokes all tokens |
| `POST /api/auth/forgot-password` | 5/min | always 200 |
| `POST /api/auth/reset-password` | 5/min | revokes all tokens |

### Decisions worth keeping

- **Lockout**: 5 failed attempts per email+IP, 15 minutes. A success clears the
  counter. Route throttles sit in front as defence in depth.
- **No account enumeration**: `forgot-password` returns the same response and
  message whether or not the address exists, and its Form Request has no
  `exists` rule. Login compares against a constant bcrypt digest when the user
  is missing so response latency does not leak existence either.
- **Reset revokes every token.** A reset is the remedy for a compromised
  account, so sessions predating it must not survive.
- **Deactivation is immediate.** `is_active` used to be checked only at login,
  so a disabled account kept working until its token was deleted by hand.
  `EnsureUserIsActive` now runs on every authenticated request and revokes
  tokens on the way out.
- Reset links point at the SPA via `config('app.frontend_url')`, not a Blade
  view.

### Control plane

Platform Admins sign in to `control-plane/` with a **session**, not a token —
it is a server-rendered operator tool, so there is no token plumbing or CORS.
Accounts live in `platform_admins`, and `config/auth.php` points the default
provider at that model.

- **Lockout**: 5 failed attempts per email+IP, 15 minutes — the same shape as
  the portal, but keyed **`cp-login:`** rather than `login:`. Clearing one does
  not clear the other.
- **No self-registration and no password reset.** Accounts come from
  `php artisan platform:admin`; a lost password means a new account.
- **`is_active` is folded into the credential query**, and
  `EnsurePlatformAdminIsActive` re-checks every authenticated request, so
  deactivating an operator ends a live session immediately — the same reasoning
  as `EnsureUserIsActive` in the portal.
- Session id regenerated on sign-in (fixation), invalidated on sign-out.
- **No per-action authorization.** Any active operator can do everything; the
  control plane has no roles of its own.

Full detail in [The control plane](../control-plane.md).

---

## 5. Authentication audit

`auth_audit_logs`, append-only (`UPDATED_AT = null`). Separate from
`activity_logs`, which records domain changes by an authenticated user; this
table records the authentication boundary itself, **including failures**, where
there is often no user to attribute the row to.

Events: `login.succeeded`, `login.failed`, `login.blocked`, `logout`,
`password_reset.requested`, `password_reset.completed`,
`password_reset.failed`, `password.changed`.

Each row carries the user (nullable), the email as typed (lower-cased), success,
a reason, IP, user agent, and a JSON context.

`email` is stored alongside `user_id` deliberately: a failed login may name an
account that does not exist, and that is exactly the case worth auditing.

Auditing never breaks the thing it observes — `AuthAuditLogger` reports write
failures rather than throwing.

### Control-plane audit

Operator actions are recorded separately, in `control_plane_audit_logs` — also
append-only, carrying actor, tenant, IP and a JSON detail payload. Six actions:
`login.succeeded`, `login.failed`, `login.blocked`, `logout`,
`tenant.provision_requested`, `tenant.provision_retried`.

Note that the **CLI provisioning path records nothing** — auditing is wired into
the web controllers only.

---

## 6. Coordinator templates

`project_types`, `activity_templates`, `activity_template_items`.

Every tenant is seeded with four project types (Research, Development, Industry
Internship, Capstone) and three timelines. `is_default` marks seeded rows, which
lets re-seeding be idempotent and lets the UI distinguish shipped from
institution-authored.

- Template items use **`due_offset_days`** from project start, not absolute
  dates, so a template survives into the next academic session unchanged.
- Deleting a seeded default **deactivates** it instead — re-provisioning would
  recreate it, leaving a coordinator unable to make the removal stick.
- Updating items **replaces** them wholesale; a partial merge would strand rows
  the editor removed.

---

## Test coverage

| Area | File | Tests |
|---|---|---|
| Role matrix, prefix boundaries, Team Lead scoping | `RbacAuthorizationTest` | 13 |
| Reset flow, enumeration, token revocation | `PasswordResetTest` | 6 |
| Every audit event, lockout, append-only | `AuthAuditLogTest` | 10 |
| Templates, defaults, item replacement | `CoordinatorTemplatesTest` | 11 |
| Batch entity, scoping, retirement, payload round-trip | `BatchManagementTest` | 16 |
| Batch backfill migration (real `up()`/`down()`) | `BatchBackfillTest` | 7 |
| Session lifecycle, key dates, nesting | `AcademicCalendarTest` | 15 |
| Typed items, cadence, sequencing, versioning | `ActivityTemplateBuilderTest` | 29 |
| Sprint 2 configuration end to end | `Sprint2ConfigurationDoDTest` | 3 |
| Teams, invitations, size and lead rules | `TeamFormationTest` | 17 |
| Supervisor directory, requests, capacity, duplicates | `SupervisorMatchingTest` | 22 |
| Sprint 3 workflow end to end | `Sprint3WorkflowDoDTest` | 3 |
| Bootstrap, idempotency, role exclusions, credential output | `TenantBootstrapSeederTest` | 7 |
| Provisioning order, resume, credentials | `ProvisioningPipelineTest` (control-plane) | 20 |
| Operator UI: auth, provisioning, audit | `ControlPlaneUiTest` (control-plane) | 26 |
| Title registry: lookup, disclosure, token auth | `TitleRegistryTest` (control-plane) | 16 |

Tenant app: **239 passing**. Control plane: **68 passing**.

## Not built

- **Runtime sequential enforcement** — `is_sequential` and `blocks_progression`
  are validated when a template is published, but nothing applies a template to
  a project yet, so ordering is not yet enforced against real work
- JSON API for the control plane — the operator UI is server-rendered, so there
  is no programmatic access
- Admin UI for branding and for template editing (APIs exist)
- Internship schema — Employer has role, permissions and audit, no features
- Editing a project type or a key date after it is created
- Impersonation, tenant suspension/deprovisioning via the control-plane app
