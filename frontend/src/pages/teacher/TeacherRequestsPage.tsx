import { useEffect, useState } from 'react'
import {
  acceptRequest,
  declineRequest,
  extractError,
  fetchIncomingRequests,
  type IncomingRequest,
  type RequestInbox,
} from '../../api/teacher'
import { Modal } from '../../components/Modal'

export function TeacherRequestsPage() {
  const [inbox, setInbox] = useState<RequestInbox | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [decliningRequest, setDecliningRequest] = useState<IncomingRequest | null>(null)
  const [note, setNote] = useState('')
  const [isSaving, setIsSaving] = useState(false)
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
      setSuccess('Accepted. The project is now assigned to you for supervision.')
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not accept the request.'))
    }
  }

  async function handleDeclineConfirm() {
    if (!decliningRequest) return
    setIsSaving(true)
    setError(null)

    try {
      await declineRequest(decliningRequest.id, note || undefined)
      setSuccess('Request declined. The team has been notified.')
      setDecliningRequest(null)
      setNote('')
      await load()
    } catch (err) {
      setError(extractError(err, 'Could not decline the request.'))
    } finally {
      setIsSaving(false)
    }
  }

  const meta = inbox?.meta
  const atCapacity = meta ? meta.remaining_capacity <= 0 : false

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 tracking-tight">Supervision Requests</h1>
          <p className="mt-1 text-slate-500">
            Student teams asking you to take their final year project on.
          </p>
        </div>
      </div>

      {meta && (
        <div
          className={`rounded-xl border p-5 shadow-sm bg-white ${
            atCapacity ? 'border-red-200 bg-red-50/50' : 'border-slate-200'
          }`}
        >
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div>
              <span className="text-xs font-bold uppercase tracking-wider text-slate-500">
                Supervision Workload
              </span>
              <p className="text-base font-bold text-slate-900 mt-0.5">
                {meta.active_projects} of {meta.max_projects} Projects Supervised
              </p>
            </div>
            <span
              className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${
                atCapacity
                  ? 'bg-red-100 text-red-800'
                  : 'bg-emerald-50 text-emerald-700 border border-emerald-200'
              }`}
            >
              {meta.remaining_capacity} free supervision slot{meta.remaining_capacity === 1 ? '' : 's'}
            </span>
          </div>
          {atCapacity && (
            <p className="mt-2 text-xs font-medium text-red-600">
              You are currently at your project limit. Acceptance will be restricted until an existing project finishes or your department quota is adjusted.
            </p>
          )}
        </div>
      )}

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

      {isLoading ? (
        <div className="rounded-xl border border-slate-200 bg-white py-12 text-center text-slate-400 shadow-sm">
          Loading supervision requests…
        </div>
      ) : (inbox?.data.length ?? 0) === 0 ? (
        <div className="rounded-xl border border-slate-200 bg-white py-12 text-center text-slate-500 shadow-sm">
          No pending supervision requests at this time.
        </div>
      ) : (
        <div className="grid gap-4">
          {inbox?.data.map((request) => (
            <div
              key={request.id}
              className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:shadow-md"
            >
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <h2 className="text-xl font-bold text-slate-900">{request.project?.title}</h2>
                  <p className="text-sm text-slate-500 mt-1">
                    {request.team
                      ? `${request.team.name} · ${request.team.member_count} member(s)`
                      : 'Individual project'}
                    {request.requested_by && ` · Requested by ${request.requested_by}`}
                  </p>
                </div>

                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => void handleAccept(request)}
                    disabled={atCapacity}
                    className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-50 transition"
                  >
                    Accept Team
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      setDecliningRequest(request)
                      setNote('')
                    }}
                    className="rounded-xl border border-red-200 px-4 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 transition"
                  >
                    Decline
                  </button>
                </div>
              </div>

              <div className="mt-4 rounded-xl border border-blue-100 bg-blue-50/50 p-4 text-sm text-slate-800">
                <span className="font-semibold text-blue-900 block text-xs uppercase tracking-wider mb-1">
                  Team Rationale & Goals:
                </span>
                <p className="italic">{request.rationale}</p>
              </div>

              {request.team && (
                <div className="mt-4 border-t border-slate-100 pt-3">
                  <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 block mb-2">
                    Team Members
                  </span>
                  <div className="flex flex-wrap gap-2">
                    {request.team.members.map((member) => (
                      <span
                        key={member.registration_number}
                        className="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-800"
                      >
                        {member.name} {member.is_leader && '(Lead)'} · {member.registration_number}
                      </span>
                    ))}
                  </div>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Modal: Decline Request */}
      <Modal
        isOpen={decliningRequest !== null}
        onClose={() => setDecliningRequest(null)}
        title="Decline Supervision Request"
        subtitle={
          decliningRequest
            ? `Decline request from ${decliningRequest.team?.name ?? 'student'}.`
            : undefined
        }
      >
        <div className="space-y-4">
          <p className="text-sm text-slate-600">
            Declining allows the student group to select and approach another faculty supervisor.
          </p>

          <label className="block space-y-1.5 text-sm font-medium text-slate-700">
            <span>Feedback / Reason for Students (Optional)</span>
            <textarea
              rows={3}
              value={note}
              onChange={(e) => setNote(e.target.value)}
              placeholder="e.g. Current research focus is in another domain; reaching supervision limit for this semester."
              className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            />
          </label>

          <div className="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
            <button
              type="button"
              onClick={() => setDecliningRequest(null)}
              className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
            >
              Cancel
            </button>
            <button
              type="button"
              disabled={isSaving}
              onClick={() => void handleDeclineConfirm()}
              className="rounded-xl bg-red-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 disabled:opacity-60 transition"
            >
              {isSaving ? 'Declining…' : 'Confirm Decline'}
            </button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
