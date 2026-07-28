import { useEffect, useState } from 'react'
import { extractError, fetchOverview, type StudentOverview } from '../../api/student'

export function StudentSupervisorPage() {
  const [data, setData] = useState<StudentOverview | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    void fetchOverview()
      .then(setData)
      .catch((err) => setError(extractError(err, 'Could not load supervisor details.')))
  }, [])

  if (error) {
    return <p className="text-[var(--color-danger)]">{error}</p>
  }

  if (!data) {
    return <p className="text-[var(--color-ink-muted)]">Loading…</p>
  }

  return (
    <div className="space-y-6">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          My supervisor
        </h1>
        <p className="mt-2 text-[var(--color-ink-muted)]">
          Contact details for your assigned final-year project supervisor.
        </p>
      </header>

      <div className="grid gap-4 md:grid-cols-2">
        <section className="rounded-xl border border-[var(--color-paper-deep)] bg-white/80 p-5">
          {data.supervisor ? (
            <>
              <p className="text-xs uppercase tracking-[0.14em] text-[var(--color-ink-muted)]">Supervisor</p>
              <h2 className="mt-2 font-[family-name:var(--font-display)] text-3xl text-[var(--color-sea-deep)]">
                {data.supervisor.name}
              </h2>
              <dl className="mt-4 space-y-2 text-sm">
                <div className="flex justify-between gap-4">
                  <dt className="text-[var(--color-ink-muted)]">Designation</dt>
                  <dd>{data.supervisor.designation ?? '—'}</dd>
                </div>
                <div className="flex justify-between gap-4">
                  <dt className="text-[var(--color-ink-muted)]">Department</dt>
                  <dd>{data.supervisor.department ?? '—'}</dd>
                </div>
                <div className="flex justify-between gap-4">
                  <dt className="text-[var(--color-ink-muted)]">Employee ID</dt>
                  <dd>{data.supervisor.employee_id}</dd>
                </div>
                <div className="flex justify-between gap-4">
                  <dt className="text-[var(--color-ink-muted)]">Email</dt>
                  <dd className="text-[var(--color-sea)]">{data.supervisor.email}</dd>
                </div>
                <div className="flex justify-between gap-4">
                  <dt className="text-[var(--color-ink-muted)]">Phone</dt>
                  <dd>{data.supervisor.phone ?? '—'}</dd>
                </div>
              </dl>
            </>
          ) : (
            <p className="text-[var(--color-amber)]">No supervisor assigned yet. Contact your admin.</p>
          )}
        </section>

        <section className="rounded-xl border border-[var(--color-paper-deep)] bg-white/80 p-5">
          <p className="text-xs uppercase tracking-[0.14em] text-[var(--color-ink-muted)]">Your profile</p>
          <dl className="mt-4 space-y-2 text-sm">
            <div className="flex justify-between gap-4">
              <dt className="text-[var(--color-ink-muted)]">Registration</dt>
              <dd>{data.student.registration_number}</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-[var(--color-ink-muted)]">Batch</dt>
              <dd>{data.student.batch ?? '—'}</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-[var(--color-ink-muted)]">Department</dt>
              <dd>{data.student.department ?? '—'}</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-[var(--color-ink-muted)]">Session</dt>
              <dd>{data.student.session ?? '—'}</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-[var(--color-ink-muted)]">Project status</dt>
              <dd>{data.project?.proposal_status_label ?? data.project?.status_label ?? 'Not started'}</dd>
            </div>
          </dl>
        </section>
      </div>
    </div>
  )
}
