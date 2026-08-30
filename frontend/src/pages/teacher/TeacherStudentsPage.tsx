import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { extractError, fetchTeacherStudents, type TeacherStudentRow } from '../../api/teacher'

export function TeacherStudentsPage() {
  const [rows, setRows] = useState<TeacherStudentRow[]>([])
  const [error, setError] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    void fetchTeacherStudents()
      .then(setRows)
      .catch((err) => setError(extractError(err, 'Could not load assigned students.')))
      .finally(() => setIsLoading(false))
  }, [])

  return (
    <div className="space-y-6">
      <header>
        <h1 className="font-[family-name:var(--font-display)] text-4xl text-[var(--color-sea-deep)]">
          Assigned students
        </h1>
        <p className="mt-2 text-[var(--color-ink-muted)]">
          Students under your supervision for the current academic session.
        </p>
      </header>

      {error && <p className="text-[var(--color-danger)]">{error}</p>}

      <div className="overflow-x-auto rounded-xl border border-[var(--color-paper-deep)] bg-[var(--color-surface)]/80">
        {isLoading ? (
          <p className="px-4 py-6 text-[var(--color-ink-muted)]">Loading…</p>
        ) : (
          <table className="w-full min-w-[760px] text-left text-sm">
            <thead className="bg-[var(--color-paper-deep)]/60 text-[var(--color-ink-muted)]">
              <tr>
                <th className="px-4 py-3 font-medium">Student</th>
                <th className="px-4 py-3 font-medium">Department</th>
                <th className="px-4 py-3 font-medium">Project</th>
                <th className="px-4 py-3 font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-4 py-6 text-[var(--color-ink-muted)]">
                    No assigned students yet.
                  </td>
                </tr>
              ) : (
                rows.map((row) => (
                  <tr key={row.assignment_id} className="border-t border-[var(--color-paper-deep)]">
                    <td className="px-4 py-3">
                      <p className="font-semibold">{row.student.name}</p>
                      <p className="text-[var(--color-ink-muted)]">
                        {row.student.registration_number} · {row.student.email}
                      </p>
                    </td>
                    <td className="px-4 py-3">
                      {row.student.department ?? '—'}
                      <div className="text-[var(--color-ink-muted)]">Batch {row.student.batch ?? '—'}</div>
                    </td>
                    <td className="px-4 py-3">
                      {row.project ? (
                        <>
                          <p className="font-medium">{row.project.title}</p>
                          <p className="text-[var(--color-ink-muted)]">{row.project.status_label}</p>
                        </>
                      ) : (
                        <span className="text-[var(--color-ink-muted)]">No project</span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      {row.project && (
                        <Link
                          to={`/teacher/projects/${row.project.id}`}
                          className="text-[var(--color-sea)] hover:underline"
                        >
                          Open workspace
                        </Link>
                      )}
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
