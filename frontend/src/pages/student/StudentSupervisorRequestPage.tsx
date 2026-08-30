import { useEffect, useState, type FormEvent } from 'react'
import { extractError } from '../../api/admin'
import {
  fetchSupervisorRequests,
  fetchSupervisors,
  requestSupervisor,
  withdrawSupervisorRequest,
  type SupervisorListing,
  type SupervisorRequestItem,
} from '../../api/team'

/**
 * The supervisor directory and the request flow.
 *
 * Capacity is shown live and counted in projects, because a supervisor carries
 * several teams. Only one request can be open at a time — two supervisors could
 * otherwise both accept the same project.
 */
export function StudentSupervisorRequestPage() {
  const [supervisors, setSupervisors] = useState<SupervisorListing[]>([])
  const [requests, setRequests] = useState<SupervisorRequestItem[]>([])
  const [search, setSearch] = useState('')
  const [availableOnly, setAvailableOnly] = useState(false)
  const [chosen, setChosen] = useState<SupervisorListing | null>(null)
  const [rationale, setRationale] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [isSending, setIsSending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  async function load() {
    setIsLoading(true)
    try {
      const [directory, sent] = await Promise.all([
        fetchSupervisors({ search: search || undefined, available_only: availableOnly }),
        fetchSupervisorRequests(),
      ])
      setSupervisors(directory)
      setRequests(sent)
    } catch (err) {
      setError(extractError(err, 'Could not load supervisors.'))
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    const timer = window.setTimeout(() => void load(), 250)
    return () => window.clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search, availableOnly])

  const openRequest = requests.find((request) => request.status === 'pending') ?? null

  async function handleSend(event: FormEvent) {
    event.preventDefault()

    if (chosen === null) {
      return
    }

    setIsSending(true)
    setError(null)

    try {
      await requestSupervisor({ teacher_id: chosen.id, rationale })
      setSuccess(`Request sent to ${chosen.name}. You will be notified when they respond.`)
      setChosen(null)
      setRationale('')
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not send the request.'))
    } finally {
      setIsSending(false)
    }
  }

  async function handleWithdraw(request: SupervisorRequestItem) {
    if (!window.confirm(`Withdraw your request to ${request.supervisor.name}?`)) {
      return
    }

    try {
      await withdrawSupervisorRequest(request.id)
      setSuccess('Request withdrawn. You can approach someone else.')
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not withdraw the request.'))
    }
  }

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Find a supervisor
        </h1>
        <p className="mt-2 max-w-2xl text-[var(--color-ink-muted)]">
          Supervisors in your department, with how much they are currently
          carrying. One request can be open at a time.
        </p>
      </header>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}
      {success && <p className="text-[var(--color-success)]">{success}</p>}

      {requests.length > 0 && (
        <section className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70 p-5">
          <h2 className="font-semibold">Your requests</h2>
          <ul className="mt-3 grid gap-2">
            {requests.map((request) => (
              <li
                key={request.id}
                className="flex flex-wrap items-start justify-between gap-3 rounded-lg bg-[var(--color-paper)] px-4 py-3 text-sm"
              >
                <span className="min-w-0">
                  <span className="font-medium">{request.supervisor.name}</span>
                  <span
                    className={[
                      'ml-2 inline-flex rounded-md px-2 py-0.5 text-xs font-semibold',
                      request.status === 'accepted'
                        ? 'bg-[var(--tint-success)] text-[var(--color-success)]'
                        : request.status === 'declined'
                          ? 'bg-[var(--tint-danger)] text-[var(--color-danger)]'
                          : 'bg-[var(--tint-amber)] text-[var(--color-amber)]',
                    ].join(' ')}
                  >
                    {request.status_label}
                  </span>
                  {request.response_note && (
                    <span className="mt-1 block text-[var(--color-ink-muted)]">
                      “{request.response_note}”
                    </span>
                  )}
                </span>

                {request.status === 'pending' && (
                  <button
                    type="button"
                    onClick={() => void handleWithdraw(request)}
                    className="text-[var(--color-danger)] hover:underline"
                  >
                    Withdraw
                  </button>
                )}
              </li>
            ))}
          </ul>
        </section>
      )}

      {openRequest !== null && (
        <p className="rounded-lg bg-[var(--tint-amber)] px-4 py-3 text-sm text-[var(--color-amber)]">
          You are waiting on {openRequest.supervisor.name}. Withdraw that request
          before approaching someone else.
        </p>
      )}

      <div className="flex flex-wrap gap-3">
        <input
          type="search"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search by name…"
          className="min-w-0 flex-1 rounded-lg border border-[var(--color-paper-deep)] bg-[var(--color-surface)] px-3 py-2.5"
        />
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={availableOnly}
            onChange={(e) => setAvailableOnly(e.target.checked)}
          />
          <span>Only those with capacity</span>
        </label>
      </div>

      {isLoading ? (
        <p className="text-[var(--color-ink-muted)]">Loading supervisors…</p>
      ) : supervisors.length === 0 ? (
        <p className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70 px-4 py-8 text-center text-[var(--color-ink-muted)]">
          No supervisors match. Try clearing the filters.
        </p>
      ) : (
        <div className="grid gap-3 md:grid-cols-2">
          {supervisors.map((supervisor) => (
            <article
              key={supervisor.id}
              className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70 p-5"
            >
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <h2 className="font-semibold">{supervisor.name}</h2>
                  <p className="text-sm text-[var(--color-ink-muted)]">
                    {supervisor.designation ?? 'Supervisor'} · {supervisor.department}
                  </p>
                </div>
                <span
                  className={[
                    'shrink-0 rounded-md px-2 py-1 text-xs font-semibold',
                    supervisor.is_available
                      ? 'bg-[var(--tint-success)] text-[var(--color-success)]'
                      : 'bg-[var(--tint-danger)] text-[var(--color-danger)]',
                  ].join(' ')}
                >
                  {supervisor.is_available ? 'Available' : 'At capacity'}
                </span>
              </div>

              <p className="mt-3 text-sm tabular-nums text-[var(--color-ink-muted)]">
                {supervisor.active_projects} of {supervisor.max_projects} projects ·{' '}
                {supervisor.supervisee_count} student
                {supervisor.supervisee_count === 1 ? '' : 's'}
              </p>

              <button
                type="button"
                disabled={!supervisor.is_available || openRequest !== null}
                onClick={() => setChosen(supervisor)}
                className="mt-4 rounded-lg bg-[var(--color-sea)] px-4 py-2 text-sm font-semibold text-[var(--color-on-brand)] transition hover:bg-[var(--color-sea-deep)] disabled:cursor-not-allowed disabled:opacity-50"
              >
                Request
              </button>
            </article>
          ))}
        </div>
      )}

      {chosen !== null && (
        <section className="rounded-xl border border-[var(--color-sea)] bg-[var(--color-surface)] p-5">
          <h2 className="font-semibold">Request {chosen.name}</h2>
          <p className="mt-1 text-sm text-[var(--color-ink-muted)]">
            Say why this supervisor suits your project. They read this before
            deciding, so a couple of specific sentences beat a general one.
          </p>

          <form onSubmit={handleSend} className="mt-4 grid gap-3">
            <textarea
              required
              rows={4}
              minLength={40}
              value={rationale}
              onChange={(e) => setRationale(e.target.value)}
              placeholder="Your work on… lines up with what we are building because…"
              className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
            />
            <p className="text-xs text-[var(--color-ink-muted)] tabular-nums">
              {rationale.length} characters — at least 40.
            </p>

            <div className="flex gap-3">
              <button
                type="submit"
                disabled={isSending || rationale.length < 40}
                className="rounded-lg bg-[var(--color-sea)] px-4 py-2.5 font-semibold text-[var(--color-on-brand)] transition hover:bg-[var(--color-sea-deep)] disabled:opacity-60"
              >
                {isSending ? 'Sending…' : 'Send request'}
              </button>
              <button
                type="button"
                onClick={() => setChosen(null)}
                className="rounded-lg border border-[var(--color-paper-deep)] px-4 py-2.5"
              >
                Cancel
              </button>
            </div>
          </form>
        </section>
      )}
    </div>
  )
}
