import { useEffect, useState } from 'react'
import { StatusChip } from '../../components/StatusChip'
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
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 tracking-tight">
            All Proposals
          </h1>
          <p className="mt-1 text-slate-500">
            Browse and monitor student proposals across every department and session.
          </p>
        </div>
        <div className="text-sm font-medium text-slate-500">
          Total: <span className="font-bold text-slate-900">{total}</span> proposal{total === 1 ? '' : 's'}
        </div>
      </div>

      <div className="grid gap-3 sm:grid-cols-[1fr_1fr_1.4fr] rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <select
          value={departmentId}
          onChange={(e) => setDepartmentId(e.target.value)}
          className="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
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
          className="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
        >
          {STATUS_OPTIONS.map((option) => (
            <option key={option.value || 'all'} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>

        <div className="relative">
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search title, student, registration…"
            className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 pl-10 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
          />
          <svg
            className="absolute left-3.5 top-2.5 h-4 w-4 text-slate-400"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
      </div>

      {error && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">
          {error}
        </div>
      )}

      {isLoading ? (
        <div className="rounded-xl border border-slate-200 bg-white py-12 text-center text-slate-400 shadow-sm">
          Loading proposals…
        </div>
      ) : (
        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="overflow-x-auto">
            <table className="w-full min-w-[760px] text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="px-6 py-4">Proposal</th>
                  <th className="px-6 py-4">Student</th>
                  <th className="px-6 py-4">Department</th>
                  <th className="px-6 py-4">Supervisor</th>
                  <th className="px-6 py-4">Status</th>
                  <th className="px-6 py-4">Version</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {proposals.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-6 py-12 text-center text-slate-500">
                      No proposals found for the selected filters.
                    </td>
                  </tr>
                ) : (
                  proposals.map((proposal) => (
                    <tr key={proposal.id} className="transition hover:bg-blue-50/30 align-top">
                      <td className="px-6 py-4">
                        <p className="font-semibold text-slate-900">{proposal.title}</p>
                        <p className="mt-1 line-clamp-2 text-xs text-slate-500">
                          {proposal.abstract ?? 'No abstract'}
                        </p>
                      </td>
                      <td className="px-6 py-4">
                        <p className="font-semibold text-slate-800">{proposal.student?.name ?? '—'}</p>
                        <p className="text-xs font-mono text-slate-500">
                          {proposal.student?.registration_number ?? '—'}
                        </p>
                      </td>
                      <td className="px-6 py-4">
                        <span className="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-700/10">
                          {proposal.student?.department?.name ?? '—'}
                        </span>
                      </td>
                      <td className="px-6 py-4">
                        {proposal.supervisor?.name ? (
                          <span className="text-slate-800 font-medium text-xs">
                            {proposal.supervisor.name}
                          </span>
                        ) : (
                          <span className="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                            Unassigned
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4">
                        <StatusChip status={proposal.status} label={proposal.status_label} />
                      </td>
                      <td className="px-6 py-4 font-mono text-xs text-slate-600">v{proposal.version_number}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  )
}
