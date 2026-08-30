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
    <div className="space-y-6">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Review proposals
        </h1>
        <p className="mt-2 text-[var(--color-ink-muted)]">
          Approve, reject, or request revisions for assigned student proposals.
        </p>
      </header>

      <select
        value={status}
        onChange={(e) => setStatus(e.target.value)}
        className="rounded-lg border border-[var(--color-paper-deep)] bg-[var(--color-surface)] px-3 py-2.5"
      >
        <option value="">All statuses</option>
        <option value="submitted">Submitted</option>
        <option value="under_review">Under review</option>
        <option value="revision_requested">Revision requested</option>
        <option value="resubmitted">Resubmitted</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}

      <div className="overflow-x-auto rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/80">
        {isLoading ? (
          <p className="px-4 py-6 text-[var(--color-ink-muted)]">Loading…</p>
        ) : (
          <table className="w-full min-w-[760px] text-left text-sm">
            <thead className="bg-[var(--color-paper-deep)]/60 text-[var(--color-ink-muted)]">
              <tr>
                <th className="px-4 py-3 font-medium">Proposal</th>
                <th className="px-4 py-3 font-medium">Student</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Version</th>
                <th className="px-4 py-3 font-medium">Action</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-[var(--color-ink-muted)]">
                    No proposals to review.
                  </td>
                </tr>
              ) : (
                rows.map((row) => (
                  <tr key={row.id} className="border-t border-[var(--color-paper-deep)]">
                    <td className="px-4 py-3 font-semibold">{row.title}</td>
                    <td className="px-4 py-3">
                      <p>{row.student.name}</p>
                      <p className="text-[var(--color-ink-muted)]">{row.student.registration_number}</p>
                    </td>
                    <td className="px-4 py-3">
                      <StatusChip status={row.status} label={row.status_label} />
                    </td>
                    <td className="px-4 py-3">v{row.version_number}</td>
                    <td className="px-4 py-3">
                      <Link to={`/teacher/proposals/${row.id}`} className="text-[var(--color-sea)] hover:underline">
                        Review
                      </Link>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        )}
      </div>
    </div>
  )
}
