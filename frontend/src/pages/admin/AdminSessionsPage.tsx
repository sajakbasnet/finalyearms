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
import { Modal } from '../../components/Modal'

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
  const [isSessionModalOpen, setIsSessionModalOpen] = useState(false)
  const [expanded, setExpanded] = useState<number | null>(null)
  const [keyDates, setKeyDates] = useState<Record<number, SessionKeyDate[]>>({})
  const [selectedSessionForDate, setSelectedSessionForDate] = useState<SessionOption | null>(null)
  const [dateForm, setDateForm] = useState(emptyDateForm)
  const [isDateModalOpen, setIsDateModalOpen] = useState(false)
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

  function openCreateSession() {
    setForm(emptyForm)
    setError(null)
    setSuccess(null)
    setIsSessionModalOpen(true)
  }

  function closeSessionModal() {
    setIsSessionModalOpen(false)
    setForm(emptyForm)
  }

  async function handleCreateSession(event: FormEvent) {
    event.preventDefault()
    setIsSaving(true)
    setError(null)
    setSuccess(null)

    try {
      await createSession(form)
      setSuccess('Academic session created.')
      closeSessionModal()
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
      setError(extractError(err, 'Could not delete the session.'))
    }
  }

  async function toggleDates(session: SessionOption) {
    if (expanded === session.id) {
      setExpanded(null)
      return
    }

    setExpanded(session.id)

    try {
      setKeyDates((current) => ({ ...current, [session.id]: current[session.id] ?? [] }))
      const dates = await fetchSessionDates(session.id)
      setKeyDates((current) => ({ ...current, [session.id]: dates }))
    } catch (err) {
      setError(extractError(err, 'Could not load key dates.'))
    }
  }

  function openAddDateModal(session: SessionOption) {
    setSelectedSessionForDate(session)
    setDateForm(emptyDateForm)
    setError(null)
    setSuccess(null)
    setIsDateModalOpen(true)
  }

  function closeDateModal() {
    setIsDateModalOpen(false)
    setSelectedSessionForDate(null)
    setDateForm(emptyDateForm)
  }

  async function handleAddDate(event: FormEvent) {
    event.preventDefault()
    if (!selectedSessionForDate) return

    setIsSaving(true)
    setError(null)

    try {
      await createSessionDate(selectedSessionForDate.id, dateForm)
      closeDateModal()
      const dates = await fetchSessionDates(selectedSessionForDate.id)
      setKeyDates((current) => ({ ...current, [selectedSessionForDate.id]: dates }))
      setSuccess(`Key date added to ${selectedSessionForDate.name}.`)
    } catch (err) {
      setError(extractError(err, 'Could not add the key date.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleDeleteDate(session: SessionOption, date: SessionKeyDate) {
    try {
      await deleteSessionDate(session.id, date.id)
      const dates = await fetchSessionDates(session.id)
      setKeyDates((current) => ({ ...current, [session.id]: dates }))
      setSuccess('Key date removed.')
    } catch (err) {
      setError(extractError(err, 'Could not remove the key date.'))
    }
  }

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 tracking-tight">Academic Calendar</h1>
          <p className="mt-1 text-slate-500">
            Sessions students, groups and projects pin to, plus the key deadlines each runs to.
          </p>
        </div>
        <button
          type="button"
          onClick={openCreateSession}
          className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 active:bg-blue-800"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M12 4v16m8-8H4" />
          </svg>
          Add Session
        </button>
      </div>

      {error && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">
          {error}
        </div>
      )}
      {success && (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
          {success}
        </div>
      )}

      {/* Sessions List */}
      <div className="space-y-4">
        {isLoading ? (
          <div className="rounded-xl border border-slate-200 bg-white py-12 text-center text-slate-400 shadow-sm">
            Loading academic calendar…
          </div>
        ) : sessions.length === 0 ? (
          <div className="rounded-xl border border-slate-200 bg-white py-12 text-center text-slate-500 shadow-sm">
            No academic sessions found. Click &quot;Add Session&quot; above to create one.
          </div>
        ) : (
          sessions.map((session) => (
            <div
              key={session.id}
              className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:shadow-md"
            >
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <div className="flex items-center gap-3">
                    <h2 className="text-xl font-bold text-slate-900">{session.name}</h2>
                    {session.is_active ? (
                      <span className="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                        Active Session
                      </span>
                    ) : (
                      <span className="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">
                        Inactive
                      </span>
                    )}
                  </div>
                  <div className="mt-2 flex items-center gap-2 text-sm text-slate-500">
                    <svg className="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>
                      {session.start_date} <span className="text-slate-400">→</span> {session.end_date}
                    </span>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => void toggleDates(session)}
                    className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                  >
                    {expanded === session.id ? 'Hide Key Dates' : 'View Key Dates'}
                  </button>
                  <button
                    type="button"
                    onClick={() => openAddDateModal(session)}
                    className="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100 transition"
                  >
                    + Add Date
                  </button>
                  {!session.is_active && (
                    <button
                      type="button"
                      onClick={() => void handleActivate(session)}
                      className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition"
                    >
                      Make Active
                    </button>
                  )}
                  <button
                    type="button"
                    onClick={() => void handleDelete(session)}
                    className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 transition"
                  >
                    Delete
                  </button>
                </div>
              </div>

              {/* Key Dates Drawer/Table */}
              {expanded === session.id && (
                <div className="mt-6 border-t border-slate-100 pt-5">
                  <div className="flex items-center justify-between mb-3">
                    <h3 className="text-sm font-bold uppercase tracking-wider text-slate-500">
                      Key Dates & Deadlines for {session.name}
                    </h3>
                  </div>

                  {keyDates[session.id]?.length === 0 ? (
                    <p className="rounded-lg bg-slate-50 p-4 text-center text-sm text-slate-500">
                      No key dates recorded yet. Click &quot;+ Add Date&quot; to define milestones.
                    </p>
                  ) : (
                    <div className="overflow-x-auto rounded-lg border border-slate-200">
                      <table className="w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs font-semibold text-slate-600">
                          <tr>
                            <th className="px-4 py-3">Label / Milestone</th>
                            <th className="px-4 py-3">Date</th>
                            <th className="px-4 py-3">Type</th>
                            <th className="px-4 py-3 text-right">Action</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                          {keyDates[session.id]?.map((date) => (
                            <tr key={date.id} className="hover:bg-slate-50/60">
                              <td className="px-4 py-3 font-semibold text-slate-800">{date.label}</td>
                              <td className="px-4 py-3 text-slate-600 tabular-nums">{date.date}</td>
                              <td className="px-4 py-3">
                                <span
                                  className={`inline-flex items-center rounded px-2 py-0.5 text-xs font-medium ${
                                    date.is_deadline
                                      ? 'bg-amber-50 text-amber-800'
                                      : 'bg-blue-50 text-blue-700'
                                  }`}
                                >
                                  {date.is_deadline ? 'Hard Deadline' : 'Informational'}
                                </span>
                              </td>
                              <td className="px-4 py-3 text-right">
                                <button
                                  type="button"
                                  onClick={() => void handleDeleteDate(session, date)}
                                  className="text-xs font-semibold text-red-600 hover:text-red-800"
                                >
                                  Remove
                                </button>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </div>
              )}
            </div>
          ))
        )}
      </div>

      {/* Modal: Create Session */}
      <Modal
        isOpen={isSessionModalOpen}
        onClose={closeSessionModal}
        title="Add Academic Session"
        subtitle="Configure a new academic year or semester cycle."
      >
        <form onSubmit={handleCreateSession} className="space-y-4">
          <label className="block space-y-1.5 text-sm font-medium text-slate-700">
            <span>Session Name</span>
            <input
              required
              placeholder="e.g. 2026-2027 Academic Year"
              value={form.name}
              onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
              className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            />
          </label>

          <div className="grid gap-4 sm:grid-cols-2">
            <label className="block space-y-1.5 text-sm font-medium text-slate-700">
              <span>Start Date</span>
              <input
                required
                type="date"
                value={form.start_date}
                onChange={(e) => setForm((p) => ({ ...p, start_date: e.target.value }))}
                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
            </label>
            <label className="block space-y-1.5 text-sm font-medium text-slate-700">
              <span>End Date</span>
              <input
                required
                type="date"
                value={form.end_date}
                onChange={(e) => setForm((p) => ({ ...p, end_date: e.target.value }))}
                className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
            </label>
          </div>

          <label className="flex items-center gap-2.5 text-sm text-slate-700 font-medium pt-2">
            <input
              type="checkbox"
              checked={form.is_active}
              onChange={(e) => setForm((p) => ({ ...p, is_active: e.target.checked }))}
              className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
            />
            <span>Set as currently active academic session</span>
          </label>

          <div className="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
            <button
              type="button"
              onClick={closeSessionModal}
              className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isSaving}
              className="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-60 transition"
            >
              {isSaving ? 'Saving…' : 'Create Session'}
            </button>
          </div>
        </form>
      </Modal>

      {/* Modal: Add Key Date */}
      <Modal
        isOpen={isDateModalOpen}
        onClose={closeDateModal}
        title={selectedSessionForDate ? `Add Key Date to ${selectedSessionForDate.name}` : 'Add Key Date'}
        subtitle="Define a proposal deadline, progress review, or submission date."
      >
        <form onSubmit={handleAddDate} className="space-y-4">
          <label className="block space-y-1.5 text-sm font-medium text-slate-700">
            <span>Milestone Label</span>
            <input
              required
              placeholder="e.g. Proposal Submission Deadline"
              value={dateForm.label}
              onChange={(e) => setDateForm((p) => ({ ...p, label: e.target.value }))}
              className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            />
          </label>

          <label className="block space-y-1.5 text-sm font-medium text-slate-700">
            <span>Date</span>
            <input
              required
              type="date"
              value={dateForm.date}
              onChange={(e) => setDateForm((p) => ({ ...p, date: e.target.value }))}
              className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            />
          </label>

          <label className="flex items-center gap-2.5 text-sm text-slate-700 font-medium pt-2">
            <input
              type="checkbox"
              checked={dateForm.is_deadline}
              onChange={(e) => setDateForm((p) => ({ ...p, is_deadline: e.target.checked }))}
              className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
            />
            <span>This is a strict deadline</span>
          </label>

          <div className="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
            <button
              type="button"
              onClick={closeDateModal}
              className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isSaving}
              className="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-60 transition"
            >
              {isSaving ? 'Saving…' : 'Add Key Date'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
