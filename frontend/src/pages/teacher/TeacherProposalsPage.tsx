import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { StatusChip } from '../../components/StatusChip'
import {
  extractError,
  fetchTeacherProposals,
  type TeacherProposalSummary,
} from '../../api/teacher'

export function TeacherProposalsPage() {
  const [rows, setRows] = useState<TeacherProposalSummary[]>([])
  const [status, setStatus] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    setIsLoading(true)
    void fetchTeacherProposals(status || undefined)
      .then(setRows)
      .catch((err) => setError(extractError(err, 'Could not load proposals.')))
      .finally(() => setIsLoading(false))
  }, [status])

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 tracking-tight">Review Proposals</h1>
          <p className="mt-1 text-slate-500">
            Approve, reject, or request revisions for assigned student proposals.
          </p>
        </div>
      </div>

      <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <label htmlFor="proposal-status" className="text-sm font-medium text-slate-600">
          Filter by Status:
        </label>
        <select
          id="proposal-status"
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
        >
          <option value="">All statuses</option>
          <option value="submitted">Submitted</option>
          <option value="under_review">Under review</option>
          <option value="revision_requested">Revision requested</option>
          <option value="resubmitted">Resubmitted</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>

      {error && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">
          {error}
        </div>
      )}

      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        {isLoading ? (
          <div className="py-12 text-center text-slate-400">Loading proposals…</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[760px] text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="px-6 py-4">Proposal</th>
                  <th className="px-6 py-4">Student</th>
                  <th className="px-6 py-4">Status</th>
                  <th className="px-6 py-4">Version</th>
                  <th className="px-6 py-4 text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {rows.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-6 py-12 text-center text-slate-500">
                      No proposals to review.
                    </td>
                  </tr>
                ) : (
                  rows.map((row) => (
                    <tr key={row.id} className="transition hover:bg-blue-50/30">
                      <td className="px-6 py-4 font-semibold text-slate-900">{row.title}</td>
                      <td className="px-6 py-4">
                        <p className="font-semibold text-slate-800">{row.student.name}</p>
                        <p className="text-xs font-mono text-slate-500">{row.student.registration_number}</p>
                      </td>
                      <td className="px-6 py-4">
                        <StatusChip status={row.status} label={row.status_label} />
                      </td>
                      <td className="px-6 py-4 font-mono text-xs text-slate-600">v{row.version_number}</td>
                      <td className="px-6 py-4 text-right whitespace-nowrap">
                        <Link
                          to={`/teacher/proposals/${row.id}`}
                          className="inline-flex items-center rounded-lg bg-blue-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 transition shadow-sm"
                        >
                          Review Proposal
                        </Link>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}
