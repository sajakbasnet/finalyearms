# Project workflow

From a student with an idea to a supervisor who has approved it.

```
  student ──> team ──> supervisor request ──> proposal ──> review
              (4–6)        accept/decline      draft       approve /
                                                           revise /
                                                           reject
```

Every step is guarded by a permission from
[the access model](./architecture/access-model.md), and every rule below is
covered by `TeamFormationTest` (17 tests), `SupervisorMatchingTest` (22, which
also covers duplicate detection) and `Sprint3WorkflowDoDTest` (3).

---

## 1. Starting a project

`POST /api/student/projects` — permission `project.create.own`.

A student chooses **individual** (exactly one member) or **team**. Creating a
team makes the creator its **lead**; the lead is a contextual role derived from
`student_group_members.is_leader`, not a row in `roles`.

**One active project per student per session.** A student who already holds one
cannot start another — the second attempt is rejected rather than silently
replacing the first. Configurable via `PROJECT_MAX_ACTIVE_PER_STUDENT`.

## 2. Building the team

| Action | Endpoint | Who |
|---|---|---|
| Invite | `POST /api/student/team/invitations` | lead only (`team.manage.own`) |
| Accept | `POST /api/student/invitations/{id}/accept` | the invitee (`team.invitation.respond`) |
| Decline | `POST /api/student/invitations/{id}/decline` | the invitee |
| Remove a member | `DELETE /api/student/team/members/{id}` | lead only |
| Hand over the lead | `POST /api/student/team/lead/{member}` | lead only |
| View | `GET /api/student/team` | any member |

### The rules

- **Size: 4–6 members**, the lead included (`PROJECT_TEAM_MIN` /
  `PROJECT_TEAM_MAX`).
- **Pending invitations count towards the maximum.** Otherwise a lead could
  invite twenty students and let the race decide, which is how a team of six
  becomes a team of eleven.
- **Members must share a department** — a project belongs to one department's
  programme.
- **A student already on a team cannot be invited**, so an invitation cannot be
  used to poach.
- **Invitations lapse after 14 days** (`PROJECT_INVITE_EXPIRY_DAYS`). A stale
  invitation must not be acceptable into a team that has since filled up or
  disbanded.
- **Only the invitee can answer.** A third party accepting someone else's
  invitation is a 403, not a 404 — the invitation exists, they just have no
  standing.
- **A member can leave; only the lead can remove others.**
- **The lead cannot be removed while they are the lead** — hand over first.
  This is deliberate: it makes losing a team's only lead an explicit act.
- **Leaving frees the student** to join or start another team.

## 3. Asking a supervisor

| Action | Endpoint | Who |
|---|---|---|
| Browse | `GET /api/student/supervisors` | `supervisor.directory.view` |
| Ask | `POST /api/student/supervisor-requests` | `supervisor-request.send` |
| Withdraw | `POST /api/student/supervisor-requests/{id}/withdraw` | the asking team |
| Incoming | `GET /api/teacher/supervisor-requests` | `supervisor-request.respond` |
| Accept | `POST /api/teacher/supervisor-requests/{id}/accept` | the asked supervisor |
| Decline | `POST /api/teacher/supervisor-requests/{id}/decline` | the asked supervisor |

### The rules

- **The team must be complete first.** An undersized team cannot ask — the
  supervisor is agreeing to a specific group of people.
- **The directory is scoped to the student's department** and shows live
  availability, so students are not queueing for someone who cannot take them.
- **A rationale is required** and must actually say something.
- **One open request at a time.** A team cannot shop itself around to five
  supervisors simultaneously. Withdrawing frees it to ask again.
- **A supervisor at capacity cannot be asked**, and capacity is **rechecked on
  accept** — two teams can both ask a supervisor with one slot left, and only
  the first accept succeeds.
- **A declined request records the reason**, so the team learns something.
- **A settled request cannot be answered twice.**
- A **Coordinator can override** and assign a supervisor directly
  (`POST /api/admin/students/{student}/assign-supervisor`) — but the override
  still respects capacity.

### Capacity is counted in projects, not students

`teachers.max_projects` — renamed from `maximum_students`, which said one thing
and meant another. A supervisor takes on **teams**, and a team of six is one
supervision load, not six.

Completed and rejected projects do not count, so capacity frees up as cohorts
finish.

## 4. The proposal

| Action | Endpoint |
|---|---|
| Save a draft | `POST /api/student/proposals` |
| Submit | `POST /api/student/proposals/submit` |
| History | `GET /api/student/proposals/history` |

Each submission creates a **new version** rather than overwriting the last, so
the review thread stays attached to the text it was written about. The history
page shows every version newest-first with its comments and replies.

### Duplicate detection

Two checks run when a draft is saved, and both are **warnings, never blocks**:

| Check | Scope | Where |
|---|---|---|
| `ProposalDuplicateChecker` | this institution | local database |
| `TitleRegistryClient` | every participating institution | [title registry](./title-registry.md) |

Titles are compared on a normalised form — lower-cased, punctuation stripped —
so casing and punctuation cannot hide a match. Thresholds:
`PROPOSAL_TITLE_SIMILARITY` (0.75) and `PROPOSAL_ABSTRACT_SIMILARITY` (0.55).

The two results are merged into one list, so a student sees a single warning
rather than two competing ones. A project is never flagged against itself.

Blocking on similarity would make the check something to route around; overlap
with earlier work is often legitimate.

## 5. Review

| Action | Endpoint |
|---|---|
| Queue | `GET /api/teacher/proposals` |
| Read one | `GET /api/teacher/proposals/{id}` |
| Comment | `POST /api/teacher/proposals/{id}/comments` |
| Decide | `POST /api/teacher/proposals/{id}/review` |

Three outcomes: **approve**, **request revisions**, **reject**. Comments can be
attached to a named section, and a student can reply — the thread lives on the
version it was written about.

**On approval the title is contributed to the registry**, after the transaction
commits. That contribution is best-effort: a registry that is down or slow must
never stop a supervisor approving work. Failures are logged and swallowed.

## 6. Frontend

| Page | Route | Role |
|---|---|---|
| My Team | `/student/team` | Student |
| Find a Supervisor | `/student/find-supervisor` | Student |
| My Supervisor | `/student/supervisor` | Student |
| Proposal | `/student/proposal` | Student |
| Proposal History | `/student/proposal/history` | Student |
| Supervision Requests | `/teacher/requests` | Supervisor |
| Proposal queue | `/teacher/proposals` | Supervisor |
| Project Types | `/coordinator/project-types` | Coordinator |
| Activity Templates | `/coordinator/templates` | Coordinator |

Account pages, reachable by every role: `/forgot-password`, `/reset-password`
and `/account/password`.

## 7. Not built

- Runtime enforcement of sequential activity templates
- Applying an activity template to a live project
- Editing a project type or a key date after creation
- Internship / employer schema — the `employer` role has no feature surface yet
- No notification when an invitation is about to lapse
