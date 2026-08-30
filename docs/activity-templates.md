# Activity templates

A reusable plan of work a Coordinator publishes and projects later adopt.
Built in Sprint 2; nothing applies one to a project yet.

---

## 1. The shape

```
project_type            Research / Development / Industry Internship / Capstone
  └── activity_template    a named, versioned plan
        └── items          typed steps, ordered by sort_order
```

A template belongs to a project type (or to none, if it is general-purpose),
carries a version and a status, and holds an ordered list of items.

## 2. Item types

| Type | What it is | Settings in `config` |
|---|---|---|
| `task` | A deliverable submitted by a due date | — |
| `approval_gate` | Requires sign-off before later items may start | `approver_role` |
| `meeting_milestone` | Satisfied by holding and logging a supervision meeting | `minimum_meetings`, `duration_minutes` |
| `recurring_log` | Repeats on a cadence — a weekly logbook, say | `cadence`, `occurrences` |

`config` is a JSON column rather than a spread of nullable ones: an approval
gate's approver role means nothing to a recurring log, and an institution can
extend a type without a migration.

That looseness stops at the request boundary.
`ActivityItemType::configRules()` validates each type's settings and **rejects
unrecognised keys** — a misspelled setting would otherwise be stored, look
applied, and do nothing.

A student cannot be an `approver_role`; approving your own gate defeats the
point of having one.

## 3. Timing

Items carry a **`due_offset_days` from project start**, not an absolute date,
so a template survives into the next academic session unchanged. Fixed
institutional dates live separately, in `academic_session_dates`.

A recurring log occupies a span rather than a point:

```
end_offset = due_offset_days + interval_days × (occurrences − 1)

weekly × 5 starting day 10  →  10 + 7 × 4  =  day 38
```

`ActivityTemplate::spanDays()` returns the furthest an item reaches, which is
what a template's real length is.

Cadences: `weekly` (7 days), `fortnightly` (14), `monthly` (30, approximated).

## 4. Sequential templates

Setting `is_sequential` means items must be completed in `sort_order`. It is
checked at publish time, because an incoherent plan is far more expensive to
discover once projects have adopted it. Publishing is refused when:

- two items claim the same position — ordering would be undefined
- an item has no due offset — there is nothing to order it by
- an item is due **before the item preceding it finishes**, including a
  recurring log that overruns the next item
- the **last item blocks progression** — it blocks nothing, which is always a
  mistake

`blocks_progression` defaults to true for an approval gate and false for
everything else, and can be overridden per item.

> **Not yet enforced at runtime.** These rules govern the template. Enforcing
> order against real submissions belongs with applying a template to a project,
> which does not exist yet.

## 5. Publishing and versioning

```
draft ──publish──> published ──new version──> draft (v+1) ──publish──> published (v+1)
  │                    │
  └──archive──> archived
```

**A published template is immutable.** Editing one is refused; you open a new
draft version instead. This is the rule the whole design rests on: adopting a
template must give a project a plan that cannot change underneath it.

| Action | Result |
|---|---|
| `POST /activity-templates` | Draft, v1 |
| `POST /{id}/publish` | Validates coherence, freezes it, sets `published_at` |
| `POST /{id}/versions` | New draft at v+1, `parent_id` = the published one |
| `POST /{id}/clone` | New draft at **v1** under a new name, new version line |
| `DELETE /{id}` | Archives — never deletes |

Versioning continues a line; cloning starts one. `parent_id` records where
each came from, so lineage is traceable without a separate table.

**One open draft per published version.** Two people versioning the same
template would otherwise create diverging v2s with no way to reconcile them.

Deleting archives rather than removes, because a published version may already
be in use and a seeded default would return at the next provisioning run.

## 6. Shipped defaults

Every tenant is provisioned with four project types and three templates
(Development, Research, Industry Internship), seeded **published** so a new
institution has something usable on day one rather than a draft someone has to
find and publish.

They exercise every item type — the Development timeline has a task, a
supervisor approval gate, a weekly log across 20 occurrences, and a meeting
milestone.

Institutions start from them by **cloning**, which produces an editable draft
and leaves the default intact.

## 7. API

All under `/api/coordinator`, guarded by `activity-template.*` permissions.

```
GET    /activity-templates/item-types     types + cadences, so a builder UI
                                          does not hard-code them
GET    /activity-templates                ?status= &project_type_id=
GET    /activity-templates/{id}
POST   /activity-templates
PUT    /activity-templates/{id}           draft only
DELETE /activity-templates/{id}           archives
POST   /activity-templates/{id}/publish
POST   /activity-templates/{id}/versions
POST   /activity-templates/{id}/clone
```

Sending `items` replaces them wholesale — the editor submits the full ordered
list, and a partial merge would strand rows it removed. Omitting `items`
leaves them untouched. `sort_order` is reassigned from array position, so
stored order always matches what was submitted.

## 8. Coverage

`ActivityTemplateBuilderTest` — 29 tests across item types, per-type config
validation, cadence arithmetic, publishing, sequential coherence, versioning,
cloning and archiving. `Sprint2ConfigurationDoDTest` walks the whole
configuration path end to end.

## 9. The builder UI

`/coordinator/templates` in the SPA. The library lists every version with its
status; the builder edits one.

The UI mirrors the API rather than duplicating its rules: when a template is not
editable the whole form is rendered read-only, and the actions offered depend on
status — a draft can be edited and published, a published one can only be
versioned or cloned. Item types and cadences are fetched from
`/activity-templates/item-types` rather than hard-coded, so adding a type on the
server surfaces it in the builder without a frontend change.

Changing an item's type clears its `config`: an approval gate's approver role is
meaningless on a recurring log, and the API rejects unknown keys.

**Publish saves first**, so what gets validated is exactly what is on screen.
Per-item validation errors are surfaced against the fields that caused them.

## 10. Not built

- Applying a template to a project, and runtime order enforcement
- Department-scoped templates (everything is institution-wide)
- Anchoring an item to an `academic_session_dates` entry rather than an offset
