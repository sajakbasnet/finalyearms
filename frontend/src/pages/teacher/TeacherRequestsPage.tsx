import { useEffect, useState } from 'react'
import {
  acceptRequest,
  declineRequest,
  extractError,
  fetchIncomingRequests,
  type IncomingRequest,
  type RequestInbox,
} from '../../api/teacher'

/**
 * Requests awaiting this supervisor.
 *
 * Capacity is shown alongside, and counted in projects rather than students,
 * because a supervisor carries several teams and a team is several people.
 */
export function TeacherRequestsPage() {
  const [inbox, setInbox] = useState<RequestInbox | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [decliningId, setDecliningId] = useState<number | null>(null)
  const [note, setNote] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  async function load() {
    setIsLoading(true)
    try {
      setInbox(await fetchIncomingRequests())
    } catch (err) {
      setError(extractError(err, 'Could not load requests.'))
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [])

  async function handleAccept(request: IncomingRequest) {
    const size = request.team?.member_count ?? 1
    const label = request.team ? `${request.team.name} (${size} students)` : 'this project'

    if (!window.confirm(`Take on ${label}?`)) {
      return
    }

    setError(null)

    try {
      await acceptRequest(request.id)
      setSuccess('Accepted. The project is now yours to supervise.')
      await load()
    } catch (err) {
      // Capacity is re-checked at acceptance; it may have filled while waiting.
      setError(extractError(err, 'Could not accept the request.'))
    }
  }

  async function handleDecline(request: IncomingRequest) {
    setError(null)

    try {
      await declineRequest(request.id, note || undefined)
      setSuccess('Declined. The team can approach someone else.')
      setDecliningId(null)
      setNote('')
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not decline the request.'))
    }
  }

  const meta = inbox?.meta
  const atCapacity = meta ? meta.remaining_capacity <= 0 : false

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Supervision requests
        </h1>
        <p className="mt-2 max-w-2xl text-[var(--color-ink-muted)]">
          Teams and individual students asking you to take their project on.
        </p>
      </header>

      {meta && (
        <div
          className={[
            'rounded-xl border px-5 py-4',
            atCapacity
              ? 'border-[var(--color-danger)] bg-[var(--tint-danger)]'
              : 'border-[var(--color-paper-deep)] bg-[var(--color-surface)]',
          ].join(' ')}
        >
          <p className="text-sm tabular-nums">
            <span className="font-semibold">
              {meta.active_projects} of {meta.max_projects} projects
            </span>{' '}
            <span className="text-[var(--color-ink-muted)]">
              · {meta.remaining_capacity} slot
              {meta.remaining_capacity === 1 ? '' : 's'} free
            </span>
          </p>
          {atCapacity && (
            <p className="mt-1 text-sm text-[var(--color-danger)]">
              You are at capacity. Accepting will be refused until a project
              completes or your limit is raised.
            </p>
          )}
        </div>
      )}

      {error && <p className="text-[var(--color-danger)]">{error}</p>}
      {success && <p className="text-[var(--color-success)]">{success}</p>}

      {isLoading ? (
        <p className="text-[var(--color-ink-muted)]">Loading requests…</p>
      ) : (inbox?.data.length ?? 0) === 0 ? (
        <p className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70 px-4 py-10 text-center text-[var(--color-ink-muted)]">
          Nothing waiting on you.
        </p>
      ) : (
        <div className="grid gap-3">
          {inbox?.data.map((request) => (
            <article
              key={request.id}
              className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70 p-5"
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <h2 className="font-semibold">{request.project?.title}</h2>
                  <p className="text-sm text-[var(--color-ink-muted)]">
                    {request.team
                      ? `${request.team.name} · ${request.team.member_count} members`
                      : 'Individual project'}
                    {request.requested_by && ` · asked by ${request.requested_by}`}
                  </p>
                </div>

                <div className="flex shrink-0 gap-3 text-sm">
                  <button
                    type="button"
                    onClick={() => void handleAccept(request)}
                    className="rounded-lg bg-[var(--color-sea)] px-3 py-2 font-semibold text-[var(--color-on-brand)] transition hover:bg-[var(--color-sea-deep)]"
                  >
                    Accept
                  </button>
                  <button
                    type="button"
                    onClick={() => setDecliningId(request.id)}
                    className="text-[var(--color-danger)] hover:underline"
                  >
                    Decline
                  </button>
                </div>
              </div>

              <blockquote className="mt-3 border-l-2 border-[var(--color-sea)] bg-[var(--color-paper)] px-4 py-3 text-sm">
                {request.rationale}
              </blockquote>

              {request.team && (
                <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-[var(--color-ink-muted)]">
                  {request.team.members.map((member) => (
                    <li key={member.registration_number}>
                      {member.name}
                      {member.is_leader && ' (lead)'}
                    </li>
                  ))}
                </ul>
              )}

              {decliningId === request.id && (
                <div className="mt-4 border-t border-[var(--color-paper-deep)] pt-4">
                  <label className="grid gap-1 text-sm">
                    <span className="font-medium">Why? (optional, shown to the team)</span>
                    <textarea
                      rows={2}
                      value={note}
                      onChange={(e) => setNote(e.target.value)}
                      placeholder="Already carrying too much this session."
                      className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2.5"
                    />
                  </label>
                  <div className="mt-3 flex gap-3">
                    <button
                      type="button"
                      onClick={() => void handleDecline(request)}
                      className="rounded-lg bg-[var(--color-danger)] px-3 py-2 text-sm font-semibold text-[var(--color-surface)]"
                    >
                      Confirm decline
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        setDecliningId(null)
                        setNote('')
                      }}
                      className="rounded-lg border border-[var(--color-paper-deep)] px-3 py-2 text-sm"
                    >
                      Cancel
                    </button>
                  </div>
                </div>
              )}
            </article>
          ))}
        </div>
      )}
    </div>
  )
}
