# The title registry

A cross-institution record of **approved** project titles, so a student at one
institution can find out whether their topic has already been done at another.

Hosted by the control plane, because each tenant database is isolated and no
single one can answer that question.

**OpenAPI:** `control-plane/public/openapi.yaml`, served at `/openapi.yaml`.

---

## 1. Why it lives in the control plane

The silo model gives every institution its own database. That is the right
boundary for student records, proposals and supervision — and exactly the wrong
one for "has anyone, anywhere, already done this?"

The registry is the single deliberate exception: the one piece of data that has
to cross the tenant boundary.

## 2. What crosses, and what does not

| Contributed | Returned by the public lookup |
|---|---|
| Title | ✅ |
| Institution | ✅ |
| Academic session, year | ✅ |
| Abstract | stored, **never returned** |
| Students, supervisors, contact details | never contributed at all |

A lookup gives you enough to go and read the work. It gives you nothing about
who did it. A test pins that field list, so widening it has to be a deliberate
change rather than an accident.

**Only approved titles.** A draft or a rejected proposal is not a claim on a
topic and is never contributed.

## 3. The isolation rule still holds

The rule the whole architecture rests on is that a tenant container never holds
control-plane **database** credentials. Contributing a title needs *some*
authentication, or anyone could pollute the registry — so each tenant gets a
**registry token** at provisioning.

That token is not database access. It identifies the contributor and permits
one thing: appending that institution's own approved titles.

- Stored **hashed** — a leaked database dump yields no working tokens
- Compared with `hash_equals`, so a wrong token cannot be narrowed by timing
- Reissuing revokes the previous one
- Attribution comes from the token, never the payload, so an institution cannot
  record under another's name
- Grants no operator access — there is a test asserting exactly this

## 4. The endpoints

```
GET  /api/titles/check?title=...   public, 60/min
GET  /api/titles/stats             public, totals only
POST /api/titles                   registry token required
```

### Checking

Public and unauthenticated on purpose: requiring credentials would defeat the
point.

```bash
curl 'https://control.supervisex.app/api/titles/check?title=Smart%20Campus%20Attendance%20System'
```

```json
{
  "data": {
    "query": "Smart Campus Attendance System",
    "exists": true,
    "match_count": 1,
    "matches": [
      {
        "title": "Smart Campus Attendance System",
        "institution": "Tribhuvan University",
        "academic_session": "2024-2025",
        "year": 2024,
        "similarity": 1.0,
        "is_exact": true
      }
    ]
  },
  "note": "Similar approved projects exist. Overlap may still be fine — read them before deciding."
}
```

### Contributing

```bash
curl -X POST https://control.supervisex.app/api/titles \
  -H "Authorization: Bearer $TITLE_REGISTRY_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"project_id": 412, "title": "Adaptive Irrigation Controller", "year": 2026}'
```

Posting the same `project_id` again **updates** rather than duplicating, which
makes retries safe.

## 5. How matching works

Deliberately plain string work rather than database full-text search, so it
behaves identically on SQLite in tests and Postgres in production, and so the
rule can be explained to a student.

1. Titles are normalised — lower-cased, punctuation stripped, whitespace
   collapsed — and that form is **stored**, so a lookup is an index hit rather
   than a scan that mangles every row.
2. Candidates are narrowed to an exact normalised match or rows sharing a
   significant word, capped at 500 so one generic word cannot pull the table
   into memory.
3. Candidates are scored with `similar_text` and filtered by threshold
   (default 0.75).

Casing and punctuation therefore do not hide a match: *"smart campus,
attendance system!"* finds *"Smart Campus Attendance System"*.

## 6. How a tenant uses it

Two hooks, both **best-effort** — the registry being slow or down must never
stop a student saving a proposal or a supervisor approving one.

| When | What happens |
|---|---|
| Student saves a proposal draft | Registry is searched; matches ride along in `warnings.similar_elsewhere` |
| Supervisor **approves** a proposal | Title is contributed, after the transaction commits |

Both failures are reported and swallowed. A tenant with no registry configured
behaves as though it found nothing, so a standalone deployment still works.

Local duplicate detection (`ProposalDuplicateChecker`) runs alongside and covers
the same institution; the two are merged into one warning so a student sees a
single list.

## 7. Configuration

Injected into the tenant container at provisioning:

```dotenv
TITLE_REGISTRY_URL=https://control.supervisex.app
TITLE_REGISTRY_TOKEN=reg_...        # issued once, stored hashed
TITLE_REGISTRY_TIMEOUT=5
```

Leaving `TITLE_REGISTRY_URL` unset disables the integration cleanly.

## 8. Coverage

`TitleRegistryTest` in the control plane — 16 tests covering the public lookup,
normalisation, the disclosure boundary, token authentication, hashing,
reissuing, cross-tenant attribution, and that a registry token opens no operator
door.

## 9. Not built

- No expiry or retention policy — titles stay indefinitely
- No way for an institution to withdraw a title it contributed
- No backfill command for titles approved before the registry existed
- The public endpoint is rate-limited per IP but otherwise unmetered
