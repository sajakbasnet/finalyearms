import { useEffect, useState } from 'react'
import { fetchDashboard } from '../api/auth'
import { useAuth } from '../contexts/AuthContext'

function StatTile({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70 px-5 py-4">
      <p className="text-xs uppercase tracking-[0.14em] text-[var(--color-ink-muted)]">{label}</p>
      <p className="mt-2 font-[family-name:var(--font-display)] text-3xl text-[var(--color-sea-deep)]">
        {value}
      </p>
    </div>
  )
}

export function DashboardPage() {
  const { user } = useAuth()
  const [data, setData] = useState<Record<string, unknown> | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    async function load() {
      try {
        const dashboard = await fetchDashboard()
        setData(dashboard)
      } catch {
        setError('Could not load dashboard data.')
      }
    }

    void load()
  }, [])

  if (error) {
    return <p className="text-[var(--color-danger)]">{error}</p>
  }

  if (!data) {
    return <p className="text-[var(--color-ink-muted)]">Loading dashboard…</p>
  }

  const role = user?.role?.slug

  // Coordinators get the same institution-wide figures as an admin; their
  // oversight duties need the identical picture. Mirrors DashboardService.
  if (role === 'institution_admin' || role === 'coordinator') {
    const cards = (data.cards ?? {}) as Record<string, number>
    return (
      <div className="space-y-8 animate-[fadeRise_500ms_ease-out]">
        <header>
          <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
            Department overview
          </h1>
          <p className="mt-2 text-[var(--color-ink-muted)]">
            Track students, supervisors, and proposal throughput across the portal.
          </p>
        </header>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <StatTile label="Total students" value={cards.total_students ?? 0} />
          <StatTile label="Total teachers" value={cards.total_teachers ?? 0} />
          <StatTile label="Total projects" value={cards.total_projects ?? 0} />
          <StatTile label="Pending proposals" value={cards.pending_proposals ?? 0} />
          <StatTile label="Approved projects" value={cards.approved_projects ?? 0} />
          <StatTile label="Rejected projects" value={cards.rejected_projects ?? 0} />
        </div>
      </div>
    )
  }

  if (role === 'supervisor') {
    const cards = (data.cards ?? {}) as Record<string, number>
    const students = (data.assigned_students ?? []) as Array<{
      id: number
      name: string
      registration_number: string
      department: string | null
    }>

    return (
      <div className="space-y-8 animate-[fadeRise_500ms_ease-out]">
        <header>
          <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
            Supervisor desk
          </h1>
          <p className="mt-2 text-[var(--color-ink-muted)]">
            Review assigned students and pending proposal work.
          </p>
        </header>

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatTile label="Assigned students" value={cards.assigned_students ?? 0} />
          <StatTile label="Pending reviews" value={cards.pending_reviews ?? 0} />
          <StatTile label="Approved projects" value={cards.approved_projects ?? 0} />
          <StatTile label="Upcoming meetings" value={cards.upcoming_meetings ?? 0} />
        </div>

        <section>
          <h2 className="text-lg font-semibold text-[var(--color-ink)]">Assigned students</h2>
          <div className="mt-4 overflow-hidden rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70">
            <table className="w-full text-left text-sm">
              <thead className="bg-[var(--color-paper-deep)]/60 text-[var(--color-ink-muted)]">
                <tr>
                  <th className="px-4 py-3 font-medium">Name</th>
                  <th className="px-4 py-3 font-medium">Registration</th>
                  <th className="px-4 py-3 font-medium">Department</th>
                </tr>
              </thead>
              <tbody>
                {students.length === 0 ? (
                  <tr>
                    <td colSpan={3} className="px-4 py-6 text-[var(--color-ink-muted)]">
                      No students assigned yet.
                    </td>
                  </tr>
                ) : (
                  students.map((student) => (
                    <tr key={student.id} className="border-t border-[var(--color-paper-deep)]">
                      <td className="px-4 py-3 font-medium">{student.name}</td>
                      <td className="px-4 py-3">{student.registration_number}</td>
                      <td className="px-4 py-3">{student.department ?? '—'}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </section>
      </div>
    )
  }

  const cards = (data.cards ?? {}) as {
    supervisor?: {
      name: string
      email: string
      designation: string | null
      department: string | null
    } | null
    current_status?: string
    upcoming_deadline?: string | null
    progress?: number
  }
  const project = data.project as { title: string; status_label: string } | null
  const student = data.student as {
    registration_number: string
    batch: string | null
    department: string | null
    session: string | null
  } | undefined

  return (
    <div className="space-y-8 animate-[fadeRise_500ms_ease-out]">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Your project workspace
        </h1>
        <p className="mt-2 text-[var(--color-ink-muted)]">
          Follow proposal status, supervisor feedback, and upcoming deadlines.
        </p>
      </header>

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70 px-5 py-4 lg:col-span-1">
          <p className="text-xs uppercase tracking-[0.14em] text-[var(--color-ink-muted)]">Supervisor</p>
          {cards.supervisor ? (
            <div className="mt-3 space-y-1">
              <p className="font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
                {cards.supervisor.name}
              </p>
              <p className="text-sm text-[var(--color-ink-muted)]">{cards.supervisor.designation}</p>
              <p className="text-sm">{cards.supervisor.department}</p>
              <p className="text-sm text-[var(--color-sea)]">{cards.supervisor.email}</p>
            </div>
          ) : (
            <p className="mt-3 text-sm text-[var(--color-ink-muted)]">No supervisor assigned yet.</p>
          )}
        </div>

        <StatTile label="Current status" value={cards.current_status ?? 'Not started'} />
        <StatTile label="Progress" value={`${cards.progress ?? 0}%`} />
      </div>

      <section className="grid gap-4 md:grid-cols-2">
        <div className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70 px-5 py-4">
          <p className="text-xs uppercase tracking-[0.14em] text-[var(--color-ink-muted)]">Project</p>
          <p className="mt-2 font-[family-name:var(--font-display)] text-2xl text-[var(--color-sea-deep)]">
            {project?.title ?? 'No project started'}
          </p>
          <p className="mt-2 text-sm text-[var(--color-ink-muted)]">
            Status: {project?.status_label ?? '—'}
          </p>
        </div>

        <div className="rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/70 px-5 py-4">
          <p className="text-xs uppercase tracking-[0.14em] text-[var(--color-ink-muted)]">Profile</p>
          <dl className="mt-3 space-y-2 text-sm">
            <div className="flex justify-between gap-4">
              <dt className="text-[var(--color-ink-muted)]">Registration</dt>
              <dd className="font-medium">{student?.registration_number ?? '—'}</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-[var(--color-ink-muted)]">Batch</dt>
              <dd className="font-medium">{student?.batch ?? '—'}</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-[var(--color-ink-muted)]">Department</dt>
              <dd className="font-medium">{student?.department ?? '—'}</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-[var(--color-ink-muted)]">Session</dt>
              <dd className="font-medium">{student?.session ?? '—'}</dd>
            </div>
          </dl>
        </div>
      </section>

      <style>{`
        @keyframes fadeRise {
          from { opacity: 0; transform: translateY(12px); }
          to { opacity: 1; transform: translateY(0); }
        }
      `}</style>
    </div>
  )
}
