import { useEffect, useState, type FormEvent } from 'react'
import {
  activateSession,
  createSession,
  createSessionDate,
  deleteSession,
  deleteSessionDate,
  extractError,
  fetchSessionDates,
  fetchSessions,
  type SessionKeyDate,
  type SessionOption,
} from '../../api/admin'

const emptyForm = {
  name: '',
  start_date: '',
  end_date: '',
  is_active: true,
}

const emptyDateForm = {
  label: '',
  date: '',
  is_deadline: true,
}

export function AdminSessionsPage() {
  const [sessions, setSessions] = useState<SessionOption[]>([])
  const [form, setForm] = useState(emptyForm)
  const [expanded, setExpanded] = useState<number | null>(null)
  const [keyDates, setKeyDates] = useState<Record<number, SessionKeyDate[]>>({})
  const [dateForm, setDateForm] = useState(emptyDateForm)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  async function load() {
    setIsLoading(true)
    try {
      setSessions(await fetchSessions())
    } catch (err) {
      setError(extractError(err, 'Could not load sessions.'))
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setIsSaving(true)
    setError(null)
    setSuccess(null)

    try {
      await createSession(form)
      setSuccess('Academic session created.')
      setForm(emptyForm)
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not create the session.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleActivate(session: SessionOption) {
    setError(null)
    try {
      await activateSession(session.id)
      // Exactly one session is active; the API stands the others down.
      setSuccess(`${session.name} is now the active session.`)
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not activate the session.'))
    }
  }

  async function handleDelete(session: SessionOption) {
    if (!window.confirm(`Delete ${session.name}?`)) {
      return
    }

    setError(null)
    try {
      await deleteSession(session.id)
      setSuccess('Session deleted.')
      await load()
    } catch (err) {
      // The API refuses when students or projects still pin to it.
      setError(extractError(err, 'Could not delete the session.'))
    }
  }

  async function toggleDates(session: SessionOption) {
    if (expanded === session.id) {
      setExpanded(null)
      return
    }

    setExpanded(session.id)
    setDateForm(emptyDateForm)

    try {
      setKeyDates((current) => ({ ...current, [session.id]: current[session.id] ?? [] }))
      const dates = await fetchSessionDates(session.id)
      setKeyDates((current) => ({ ...current, [session.id]: dates }))
    } catch (err) {
      setError(extractError(err, 'Could not load key dates.'))
    }
  }

  async function handleAddDate(event: FormEvent, session: SessionOption) {
    event.preventDefault()
    setError(null)

    try {
      await createSessionDate(session.id, dateForm)
      setDateForm(emptyDateForm)
      setKeyDates((current) => ({ ...current, [session.id]: [] }))
      const dates = await fetchSessionDates(session.id)
      setKeyDates((current) => ({ ...current, [session.id]: dates }))
      setSuccess('Key date added.')
    } catch (err) {
      // Dates outside the session are refused; surface the reason.
      setError(extractError(err, 'Could not add the key date.'))
    }
  }

  async function handleDeleteDate(session: SessionOption, date: SessionKeyDate) {
    try {
      await deleteSessionDate(session.id, date.id)
      const dates = await fetchSessionDates(session.id)
      setKeyDates((current) => ({ ...current, [session.id]: dates }))
    } catch (err) {
      setError(extractError(err, 'Could not remove the key date.'))
    }
  }

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Academic calendar
        </h1>
        <p className="mt-2 max-w-2xl text-[var(--color-ink-muted)]">
          Sessions students, groups and projects pin to, plus the key dates each
          runs to. Exactly one session is active at a time.
        </p>
      </header>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}
      {success && <p className="text-[var(--color-success)]">{success}</p>}

      <div className="grid gap-6 lg:grid-cols-[340px_minmax(0,1fr)]">
        <form
          onSubmit={handleSubmit}
          className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)] p-5"
        >
          <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
            New session
          </h2>

          <div className="mt-4 grid gap-3">
            <input
              required
              placeholder="Name (e.g. 2026-2027)"
              value={form.name}
              onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
              className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
            />
            <label className="grid gap-1 text-sm">
              <span className="text-[var(--color-ink-muted)]">Starts</span>
              <input
                required
                type="date"
                value={form.start_date}
                onChange={(e) => setForm((p) => ({ ...p, start_date: e.target.value }))}
                className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
              />
            </label>
            <label className="grid gap-1 text-sm">
              <span className="text-[var(--color-ink-muted)]">Ends</span>
              <input
                required
                type="date"
                value={form.end_date}
                onChange={(e) => setForm((p) => ({ ...p, end_date: e.target.value }))}
                className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
              />
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={form.is_active}
                onChange={(e) => setForm((p) => ({ ...p, is_active: e.target.checked }))}
              />
              <span>Make this the active session</span>
            </label>
          </div>

          <button
            type="submit"
            disabled={isSaving}
            className="mt-4 rounded-lg bg-[var(--color-sea)] px-4 py-2.5 font-semibold text-[var(--color-on-brand)] transition hover:bg-[var(--color-sea-deep)] disabled:opacity-70"
          >
            {isSaving ? 'Saving…' : 'Create session'}
          </button>
        </form>

        <div className="space-y-3">
          {isLoading ? (
            <p className="text-[var(--color-ink-muted)]">Loading sessions…</p>
          ) : sessions.length === 0 ? (
            <p className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70 px-4 py-8 text-center text-[var(--color-ink-muted)]">
              No sessions yet.
            </p>
          ) : (
            sessions.map((session) => (
              <article
                key={session.id}
                className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70 p-5"
              >
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <div className="flex flex-wrap items-center gap-2">
                      <h2 className="font-semibold">{session.name}</h2>
                      {session.is_active && (
                        <span className="inline-flex rounded-md bg-[var(--tint-success)] px-2 py-1 text-xs font-semibold text-[var(--color-success)]">
                          Active
                        </span>
                      )}
                    </div>
                    <p className="mt-1 text-sm text-[var(--color-ink-muted)]">
                      {session.start_date} → {session.end_date}
                    </p>
                  </div>

                  <div className="flex flex-wrap gap-3 text-sm">
                    <button
                      type="button"
                      onClick={() => void toggleDates(session)}
                      className="text-[var(--color-sea)] hover:underline"
                    >
                      {expanded === session.id ? 'Hide key dates' : 'Key dates'}
                    </button>
                    {!session.is_active && (
                      <button
                        type="button"
                        onClick={() => void handleActivate(session)}
                        className="text-[var(--color-sea)] hover:underline"
                      >
                        Make active
                      </button>
                    )}
                    <button
                      type="button"
                      onClick={() => void handleDelete(session)}
                      className="text-[var(--color-danger)] hover:underline"
                    >
                      Delete
                    </button>
                  </div>
                </div>

                {expanded === session.id && (
                  <div className="mt-4 border-t border-[var(--color-paper-deep)] pt-4">
                    <ul className="grid gap-2">
                      {(keyDates[session.id] ?? []).length === 0 ? (
                        <li className="text-sm text-[var(--color-ink-muted)]">
                          No key dates yet.
                        </li>
                      ) : (
                        (keyDates[session.id] ?? []).map((date) => (
                          <li
                            key={date.id}
                            className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-[var(--color-paper)] px-3 py-2 text-sm"
                          >
                            <span>
                              <span className="font-medium">{date.label}</span>
                              {date.is_deadline && (
                                <span className="ml-2 inline-flex rounded-md bg-[var(--tint-amber)] px-2 py-0.5 text-xs font-semibold text-[var(--color-amber)]">
                                  Deadline
                                </span>
                              )}
                            </span>
                            <span className="flex items-center gap-3">
                              <span className="tabular-nums text-[var(--color-ink-muted)]">
                                {date.date}
                              </span>
                              <button
                                type="button"
                                onClick={() => void handleDeleteDate(session, date)}
                                className="text-[var(--color-danger)] hover:underline"
                              >
                                Remove
                              </button>
                            </span>
                          </li>
                        ))
                      )}
                    </ul>

                    <form
                      onSubmit={(e) => void handleAddDate(e, session)}
                      className="mt-3 flex flex-wrap items-end gap-2"
                    >
                      <input
                        required
                        placeholder="Label (e.g. Proposal deadline)"
                        value={dateForm.label}
                        onChange={(e) => setDateForm((p) => ({ ...p, label: e.target.value }))}
                        className="min-w-0 flex-1 rounded-lg border border-[var(--color-paper-deep)] px-3 py-2 text-sm"
                      />
                      <input
                        required
                        type="date"
                        min={session.start_date}
                        max={session.end_date}
                        value={dateForm.date}
                        onChange={(e) => setDateForm((p) => ({ ...p, date: e.target.value }))}
                        className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2 text-sm"
                      />
                      <label className="flex items-center gap-2 text-sm">
                        <input
                          type="checkbox"
                          checked={dateForm.is_deadline}
                          onChange={(e) =>
                            setDateForm((p) => ({ ...p, is_deadline: e.target.checked }))
                          }
                        />
                        <span>Deadline</span>
                      </label>
                      <button
                        type="submit"
                        className="rounded-lg bg-[var(--color-sea)] px-3 py-2 text-sm font-semibold text-[var(--color-on-brand)]"
                      >
                        Add
                      </button>
                    </form>
                  </div>
                )}
              </article>
            ))
          )}
        </div>
      </div>
    </div>
  )
}
