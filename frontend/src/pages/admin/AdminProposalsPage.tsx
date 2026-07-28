import { useEffect, useState } from 'react'
import {
  fetchDepartments,
  fetchProposals,
  type DepartmentOption,
  type ProposalListItem,
} from '../../api/admin'

const STATUS_OPTIONS = [
  { value: '', label: 'All statuses' },
  { value: 'draft', label: 'Draft' },
  { value: 'submitted', label: 'Submitted' },
  { value: 'under_review', label: 'Under Review' },
  { value: 'revision_requested', label: 'Revision Requested' },
  { value: 'resubmitted', label: 'Resubmitted' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
]

function statusTone(status: string): string {
  switch (status) {
    case 'approved':
      return 'bg-[rgba(47,107,79,0.12)] text-[var(--color-success)]'
    case 'rejected':
    case 'cancelled':
      return 'bg-[rgba(166,61,61,0.12)] text-[var(--color-danger)]'
    case 'revision_requested':
      return 'bg-[rgba(196,122,44,0.14)] text-[var(--color-amber)]'
    default:
      return 'bg-[var(--color-sea-soft)] text-[var(--color-sea-deep)]'
  }
}

export function AdminProposalsPage() {
  const [departments, setDepartments] = useState<DepartmentOption[]>([])
  const [proposals, setProposals] = useState<ProposalListItem[]>([])
  const [departmentId, setDepartmentId] = useState('')
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [total, setTotal] = useState(0)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    void fetchDepartments()
      .then(setDepartments)
      .catch(() => setError('Could not load departments.'))
  }, [])

  useEffect(() => {
    async function load() {
      setIsLoading(true)
      setError(null)
      try {
        const response = await fetchProposals({
          department_id: departmentId || undefined,
          status: status || undefined,
          search: search || undefined,
        })
        setProposals(response.data)
        setTotal(response.meta.total)
      } catch {
        setError('Could not load proposals.')
      } finally {
        setIsLoading(false)
      }
    }

    const timer = window.setTimeout(() => {
      void load()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [departmentId, status, search])

  return (
    <div className="space-y-6 animate-[fadeRise_500ms_ease-out]">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          All proposals
        </h1>
        <p className="mt-2 text-[var(--color-ink-muted)]">
          Browse student proposals across every department.
        </p>
      </header>

      <div className="grid gap-3 md:grid-cols-[1fr_1fr_1.4fr]">
        <select
          value={departmentId}
          onChange={(e) => setDepartmentId(e.target.value)}
          className="rounded-lg border border-[var(--color-paper-deep)] bg-white px-3 py-2.5 outline-none focus:border-[var(--color-sea)]"
        >
          <option value="">All departments</option>
          {departments.map((department) => (
            <option key={department.id} value={department.id}>
              {department.name} ({department.code})
            </option>
          ))}
        </select>

        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="rounded-lg border border-[var(--color-paper-deep)] bg-white px-3 py-2.5 outline-none focus:border-[var(--color-sea)]"
        >
          {STATUS_OPTIONS.map((option) => (
            <option key={option.value || 'all'} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>

        <input
          type="search"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search title, student, registration…"
          className="rounded-lg border border-[var(--color-paper-deep)] bg-white px-3 py-2.5 outline-none focus:border-[var(--color-sea)]"
        />
      </div>

      <p className="text-sm text-[var(--color-ink-muted)]">{total} proposal{total === 1 ? '' : 's'}</p>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}

      {isLoading ? (
        <p className="text-[var(--color-ink-muted)]">Loading proposals…</p>
      ) : (
        <div className="overflow-hidden rounded-xl border border-[var(--color-paper-deep)] bg-white/70">
          <table className="w-full min-w-[760px] text-left text-sm">
            <thead className="bg-[var(--color-paper-deep)]/60 text-[var(--color-ink-muted)]">
              <tr>
                <th className="px-4 py-3 font-medium">Proposal</th>
                <th className="px-4 py-3 font-medium">Student</th>
                <th className="px-4 py-3 font-medium">Department</th>
                <th className="px-4 py-3 font-medium">Supervisor</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Version</th>
              </tr>
            </thead>
            <tbody>
              {proposals.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-4 py-8 text-[var(--color-ink-muted)]">
                    No proposals found for the selected filters.
                  </td>
                </tr>
              ) : (
                proposals.map((proposal) => (
                  <tr key={proposal.id} className="border-t border-[var(--color-paper-deep)] align-top">
                    <td className="px-4 py-3">
                      <p className="font-semibold text-[var(--color-ink)]">{proposal.title}</p>
                      <p className="mt-1 line-clamp-2 text-[var(--color-ink-muted)]">
                        {proposal.abstract ?? 'No abstract'}
                      </p>
                    </td>
                    <td className="px-4 py-3">
                      <p className="font-medium">{proposal.student?.name ?? '—'}</p>
                      <p className="text-[var(--color-ink-muted)]">
                        {proposal.student?.registration_number ?? '—'}
                      </p>
                    </td>
                    <td className="px-4 py-3">
                      {proposal.student?.department?.name ?? '—'}
                    </td>
                    <td className="px-4 py-3">
                      {proposal.supervisor?.name ?? (
                        <span className="text-[var(--color-amber)]">Unassigned</span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex rounded-md px-2 py-1 text-xs font-semibold ${statusTone(proposal.status)}`}>
                        {proposal.status_label}
                      </span>
                    </td>
                    <td className="px-4 py-3">v{proposal.version_number}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
