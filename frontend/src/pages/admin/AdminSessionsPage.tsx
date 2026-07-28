import { useEffect, useState, type FormEvent } from 'react'
import {
  createSession,
  extractError,
  fetchSessions,
  type SessionOption,
} from '../../api/admin'

const emptyForm = {
  name: '',
  start_date: '',
  end_date: '',
  is_active: true,
}

export function AdminSessionsPage() {
  const [sessions, setSessions] = useState<SessionOption[]>([])
  const [form, setForm] = useState(emptyForm)
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
      setError(extractError(err, 'Could not create session.'))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Academic sessions
        </h1>
        <p className="mt-2 text-[var(--color-ink-muted)]">
          Create FYP sessions used when enrolling students into departments and batches.
        </p>
      </header>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}
      {success && <p className="text-[var(--color-success)]">{success}</p>}

      <div className="grid gap-6 xl:grid-cols-[1fr_1.2fr]">
        <form onSubmit={handleSubmit} className="rounded-xl border border-[var(--color-paper-deep)] bg-white/80 p-5">
          <h2 className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
            Add session
          </h2>
          <div className="mt-4 grid gap-3">
            <input
              required
              placeholder="Name (e.g. 2026-2027)"
              value={form.name}
              onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
              className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
            />
            <label className="block space-y-1 text-sm">
              <span className="text-[var(--color-ink-muted)]">Start date</span>
              <input
                required
                type="date"
                value={form.start_date}
                onChange={(e) => setForm((p) => ({ ...p, start_date: e.target.value }))}
                className="w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
              />
            </label>
            <label className="block space-y-1 text-sm">
              <span className="text-[var(--color-ink-muted)]">End date</span>
              <input
                required
                type="date"
                value={form.end_date}
                onChange={(e) => setForm((p) => ({ ...p, end_date: e.target.value }))}
                className="w-full rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
              />
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={form.is_active}
                onChange={(e) => setForm((p) => ({ ...p, is_active: e.target.checked }))}
              />
              Set as active session
            </label>
          </div>
          <button
            type="submit"
            disabled={isSaving}
            className="mt-4 rounded-lg bg-[var(--color-sea)] px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-70"
          >
            {isSaving ? 'Saving…' : 'Create session'}
          </button>
        </form>

        <div className="overflow-hidden rounded-xl border border-[var(--color-paper-deep)] bg-white/80">
          {isLoading ? (
            <p className="px-4 py-6 text-[var(--color-ink-muted)]">Loading…</p>
          ) : (
            <table className="w-full text-left text-sm">
              <thead className="bg-[var(--color-paper-deep)]/60 text-[var(--color-ink-muted)]">
                <tr>
                  <th className="px-4 py-3 font-medium">Session</th>
                  <th className="px-4 py-3 font-medium">Period</th>
                  <th className="px-4 py-3 font-medium">Status</th>
                </tr>
              </thead>
              <tbody>
                {sessions.map((session) => (
                  <tr key={session.id} className="border-t border-[var(--color-paper-deep)]">
                    <td className="px-4 py-3 font-semibold">{session.name}</td>
                    <td className="px-4 py-3">
                      {session.start_date} → {session.end_date}
                    </td>
                    <td className="px-4 py-3">
                      {session.is_active ? (
                        <span className="text-[var(--color-success)]">Active</span>
                      ) : (
                        <span className="text-[var(--color-ink-muted)]">Inactive</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  )
}
